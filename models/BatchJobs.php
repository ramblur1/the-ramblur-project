<?php

if (!defined('BASEPATH'))
	exit('No direct script access allowed');

/**
 * 
 * @author pberry
 */
use Ramblur\Batch\BatchJob;
use Ramblur\Batch\BatchJobStatusEnum;
use Ramblur\Batch\BatchTypeEnum;
use \Ramblur\Batch\BatchRunnerFactory;

class BatchJobs extends CI_Model {

	const BATCH_JOBS_TABLE = "BatchJobs";
	const BATCH_TYPES_TABLE = "BatchTypes";
	const BATCH_JOB_LOGS_TABLE = "BatchJobLogs";
	const BATCH_JOB_STATUSES_TABLE = "BatchJobStatuses";

	/**
	 * Create a job from a BatchJob object
	 * @param BatchJob $job
	 * @param int $userId
	 */
	public function queueJob(BatchJob $job, int $userId) : bool{
		return $this->createJob($job->getBatchJobType(), $job->getBatchableData(), $userId);
	}
	
	/**
	 * 
	 * @param \Ramblur\Batch\BatchTypeEnum $type
	 * @param string $jobData
	 * @param int|bool $userId
	 */
	public function createJob(BatchTypeEnum $type, string $jobData, int $userId) : bool {

		if (!($jobTypeId = $this->getJobTypeId($type))) {
			Logger::getLogger()->addAlert("Batch job type does not exist for given id", [$type]);
			return false;
		}

		$defaultStatusId = $this->getJobStatusId(BatchJobStatusEnum::getDefaultStatus());

		if (!$defaultStatusId) {
			Logger::getLogger()->addAlert("Could not find default job status in db", [BatchJobStatusEnum::getDefaultStatus()]);
			return false;
		}

		$status = $this->db->insert(self::BATCH_JOBS_TABLE, [
			'batchJobTypeId' => $jobTypeId,
			'jobData' => $jobData,
			'batchJobStatusId' => $defaultStatusId,
			'createdByUserId' => $userId
		]);
		if ($status) {
			return true;
		}

		return false;
	}

	/**
	 * Get the given job from the database if it exists
	 * @param int $batchJobId
	 * @return array|boolean
	 */
	public function getJob(int $batchJobId) {
		$result = $this->db->select("*")
				->where("id", $batchJobId)
				->get(self::BATCH_JOBS_TABLE)
				->row_array();

		if (isset($result)) {
			return $result;
		}
		return false;
	}

	public function getJobByBatchRun(int $runId) {
		$sql = "SELECT * FROM " . self::BATCH_JOBS_TABLE . " "
				. "WHERE id IN "
				. "    (SELECT batchJobId FROM " . BatchRuns::BATCH_RUNS_JOBS_LOOKUP_TABLE . " WHERE batchRunId = ?)";

		$result = $this->db->query($sql, $runId)
				->result_array();
		if (isset($result)) {
			return $result;
		}
		return false;
	}

	/**
	 * Update the job status of the given batch job
	 * @param int $batchJobId
	 * @param BatchJobStatusEnum $jobStatus
	 * @return boolean
	 */
	public function updateJob(int $batchJobId, BatchJobStatusEnum $jobStatus, int $runTime = null) {
		$statusId = $this->getJobStatusId($jobStatus);
		if ($statusId === false) {
			return false;
		}

		if (!$this->getJob($batchJobId)) {
			return false;
		}

		$status = $this->db->update(self::BATCH_JOBS_TABLE, [
			'batchJobStatusId' => $statusId,
			'runTime' => $runTime
				], ['id' => $batchJobId]);

		return $status;
	}

	/**
	 * Get the db id of the given job type
	 * @param BatchTypeEnum $type
	 * @return boolean
	 */
	public function getJobTypeId(BatchTypeEnum $type) {
		$row = $this->db->select("id")
				->where("jobType", $type->getValue())
				->get(self::BATCH_TYPES_TABLE)
				->row_array();
		if (!empty($row)) {
			return $row['id'];
		}
		return false;
	}

	/**
	 * Get the db id of the given status
	 * @param BatchJobStatusEnum $status
	 * @return int|boolean
	 */
	public function getJobStatusId(BatchJobStatusEnum $status) {
		$row = $this->db->select("id")
				->where("status", $status->getValue())
				->get(self::BATCH_JOB_STATUSES_TABLE)
				->row_array();
		if (!empty($row)) {
			return $row['id'];
		}
		return false;
	}

	/**
	 * set the job to failed state
	 * @param int $jobId
	 * @return boolean
	 */
	public function setJobStatus(int $jobId, BatchJobStatusEnum $status, string $reason, int $runTime) {
		$this->db->trans_start();

		if ($this->updateJob($jobId, $status, $runTime) === false) {
			return false;
		}
		$this->addJobLog($jobId, $reason);

		$this->db->update(self::BATCH_JOBS_TABLE, [
			'hasRun' => '1'
				], ['id' => $jobId]);

		$this->db->trans_complete();
		if ($this->db->trans_status() === FALSE) {
			Logger::getLogger()->addAlert("Could not set the job status to failed - a db error occurred");
			return false;
		}
		return true;
	}

	/**
	 * Add a log entry for the job
	 * @param type $jobId
	 * @param type $logData
	 */
	public function addJobLog($jobId, $logData) {
		return $this->db->insert(self::BATCH_JOB_LOGS_TABLE, [
					'batchJobId' => $jobId,
					'logData' => $logData
		]);
	}

	/**
	 * Get available jobs of the given type
	 * Available is if they're not already loaded and haven't been run
	 * @param BatchTypeEnum $type
	 * @return boolean
	 */
	public function getAvailableJobs(BatchTypeEnum $type) {

		$createdStatusId = $this->getJobStatusId(BatchJobStatusEnum::CREATED());
		if ($createdStatusId === false) {
			Logger::getLogger()->addAlert("Status does not exist in database", [BatchJobStatusEnum::CREATED()]);
			return false;
		}

		$typeId = $this->getJobTypeId($type);
		if (!$typeId) {
			Logger::getLogger()->addAlert("Batch type does not exist in database", [$type]);
			return false;
		}

		$sql = "SELECT * FROM ".self::BATCH_JOBS_TABLE. " "
				. "WHERE "
				. "hasRun = ? "
				. "AND batchJobStatusId = ? "
				. "AND batchJobTypeId = ? "
				. "AND id NOT IN (SELECT batchJobId FROM " . BatchRuns::BATCH_RUNS_JOBS_LOOKUP_TABLE . ") "
				. "ORDER BY created ASC" ;
		
		$jobs = $this->db->query($sql, [0,$createdStatusId, $typeId])
				->result_array();

		if (!empty($jobs)) {
			return $jobs;
		}

		return false;
	}

	public function getJobTypeData(BatchTypeEnum $type) {
		$row = $this->db->select("*")
				->where("jobType", $type->getValue())
				->get(self::BATCH_TYPES_TABLE)
				->row_array();
		if (!empty($row)) {
			return $row;
		}
		return false;
	}

	public function runAvailableJobs() {
		$this->config->load("aws");
		$confHelper = new \Ramblur\CIUtils\ConfigHelper(get_instance());

		$dbJobs = $this->getAvailableJobs();

		if (!$dbJobs) {
			return false;
		}
		try {
			$jobsCol = Ramblur\Batch\BatchJobsCol::buildFromDBArray($dbJobs);
		} catch (TypeError $ex) {
			Logger::getLogger()->addCritical("A batch job is corrupt, cannot run!", [$dbJobs, $ex]);
			return false;
		}

		//Run email jobs
		$emailJobs = $jobsCol->getJobsByType(BatchTypeEnum::EMAIL());

		if ($emailJobs->hasRunnables()) {
			$sdkSES = Ramblur\Email\AWSSimpleEmailService::build(
							$confHelper, \Ramblur\AWS\AWSSDK::getInstance());

			$emailMaster = BatchRunnerFactory::createRunner(BatchTypeEnum::EMAIL(), $confHelper->getConfig("aws_ses_max_send_rate", 1), $sdkSES);
			$emailMaster->runJobs($emailJobs, $this);
		}
	}

}
