<?php

namespace SalesforceBulkApi\services;

use SalesforceBulkApi\api\BatchApiSF;
use SalesforceBulkApi\api\JobApiSF;
use SalesforceBulkApi\conf\LoginParams;
use SalesforceBulkApi\dto\BatchInfoDto;
use SalesforceBulkApi\dto\CreateJobDto;
use SalesforceBulkApi\objects\SFBatchErrors;
use SalesforceBulkApi\objects\SFJob;

class JobSFApiService
{
    /**
     * @var ApiSalesforce
     */
    private $api;

    /**
     * @var SFJob
     */
    private $job;

    /**
     * @param LoginParams $params
     * @param array       $guzzleHttpClientConfig
     */
    public function __construct(LoginParams $params, array $guzzleHttpClientConfig = ['timeout' => 3])
    {
        $this->api = new ApiSalesforce($params, $guzzleHttpClientConfig);
    }

    /**
     * @param CreateJobDto $dto
     *
     * @return $this
     */
    public function initJob(CreateJobDto $dto)
    {
        $this->job = new SFJob();
        $this->job->setJobInfo(JobApiSF::create($this->api, $dto));

        return $this;
    }

    /**
     * @param array $data
     *
     * @return $this
     */
    public function addBatchToJob(array $data)
    {
        $data  = json_encode($data);
        $batch = BatchApiSF::addToJob($this->api, $this->job->getJobInfo(), $data);
        $this->job->addBatchInfo($batch);

        return $this;
    }

    /**
     * @return $this
     */
    public function closeJob()
    {
        $job = JobApiSF::close($this->api, $this->job->getJobInfo());
        $this->job->setJobInfo($job);

        return $this;
    }

    /**
     * @return $this
     */
    public function waitingForComplete()
    {
        $batches = BatchApiSF::infoForAllInJob($this->api, $this->job->getJobInfo());
        foreach ($batches as $batch) {
            if (in_array($batch->getState(), [BatchInfoDto::STATE_IN_PROGRESS, BatchInfoDto::STATE_QUEUED])) {
                sleep(rand(1, 3));
                $this->waitingForComplete();
            }
        }
        $this->job->setBatchesInfo($batches);

        return $this;
    }

    /**
     * @return SFBatchErrors[]
     */
    public function getErrors()
    {
        $errors = [];
        foreach ($this->job->getBatchesInfo() as $batchInfoDto) {
            $error = new SFBatchErrors();
            $error->setBatchInfo($batchInfoDto);
            if ($batchInfoDto->getState() != BatchInfoDto::STATE_COMPLETED) {
                if ($batchInfoDto->getState() == BatchInfoDto::STATE_FAILED) {
                    $errors[] = $error;
                    continue;
                }
            }
            $results = BatchApiSF::results($this->api, $batchInfoDto);
            $i       = 0;
            foreach ($results as $result) {
                if (!$result->isSuccess()) {
                    $error->addError($i, json_encode($result->getErrors()));
                }
                ++$i;
            }
            if (!empty($error->getErrorNumbers())) {
                $errors[] = $error;
            }
        }

        return $errors;
    }

    /**
     * @return string
     */
    public function getJobId()
    {
        return $this->job->getJobInfo()->getId();
    }

    /**
     * @return string
     */
    public function getJobObject()
    {
        return $this->job->getJobInfo()->getObject();
    }
}