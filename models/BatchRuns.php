<?php

use \Ramblur\Batch\BatchTypeEnum;
use \Ramblur\Batch\BatchRunnerFactory;

/**
 *
 *
 * @author pberry
 */
class BatchRuns extends CI_Model {

	const BATCH_RUNS_TABLE = "BatchRuns";
	const BATCH_RUNS_JOBS_LOOKUP_TABLE = "BatchRunToBatchJobs";

	public function __construct() {
		parent::__construct();
		$this->load->model("batchJobs");
		$this->config->load("aws");
	}

	/**
	 * Starts a new batch run
	 * 
	 * - Checks if the previous run failed, if so don't run
	 * - Gets the ready jobs of the given type
	 * - Creates a new batch run in the db
	 * - Loads the ready jobs into the batch run
	 * - Creates the appropriate BatchRunner
	 * - Runs the jobs
	 * 
	 * @param BatchTypeEnum $jobType
	 * @return boolean
	 */
	public function startBatchRun(BatchTypeEnum $jobType) {

		$confHelper = new \Ramblur\CIUtils\ConfigHelper(get_instance());

		if($this->lastRunInError()){
			Logger::getLogger()->addCritical("The last batch run is reporting that it had a serious error, not running any until its fixed!");
			return false;
		}
		
		if (!$this->batchJobs->getAvailableJobs($jobType)) {
			return false;
		}

		$batchRunId = $this->createBatchRun(getmypid(), new \DateTime());
		if (!$batchRunId) {
			Logger::getLogger()->addCritical("Could not create batch run", [$jobType, $this->db->error()]);
			return false;
		}
		if (!$this->loadJobs($batchRunId, $jobType)) {
			Logger::getLogger()->addInfo("No jobs loaded", [$batchRunId, $jobType, $this->db->error()]);
			return false;
		}

		if (!($dbJobs = $this->getLoadedJobs($batchRunId))) {
			return false;
		}
		
		try {
			$jobsCol = Ramblur\Batch\BatchJobsCol::buildFromDBArray($dbJobs);
		} catch (TypeError $ex) {
			Logger::getLogger()->addCritical("A batch job is corrupt, cannot run!", [$dbJobs, $ex]);
			return false;
		}		

		if( $jobType->equals(BatchTypeEnum::EMAIL())){
			$sdkSES = Ramblur\Email\AWSSimpleEmailService::build(
							$confHelper, \Ramblur\AWS\AWSSDK::getInstance());

			$emailMaster = BatchRunnerFactory::createRunner(BatchTypeEnum::EMAIL(), $confHelper->getConfig("aws_ses_max_send_rate", 1), $sdkSES);
			$result = $emailMaster->runJobs($jobsCol, $this->batchJobs);
			if (!$this->updateBatchRun($batchRunId, new DateTime(), !$result, $emailMaster->getRunTime())) {
				Logger::getLogger()->addCritical("Could not update batch run", [$batchRunId, $this->db->error()]);
				return false;
			}else{
				return true;
			}
		}
	}

	/**
	 * Create a new batch run in the db
	 * @param int $processId
	 * @param \DateTime $started
	 * @return id|bool
	 */
	public function createBatchRun(int $processId, \DateTime $started) {
		$startedTimeStamp = $started->format('Y-m-d H:i:s');		
		$status = $this->db->insert(self::BATCH_RUNS_TABLE, [
			'processId' => $processId,
			'started' => $startedTimeStamp
		]);
		if ($status) {
			return $this->db->insert_id();
		}
		return false;
	}

	/**
	 * 
	 * @param int $runId
	 * @param \DateTime $finished
	 * @param bool $hadErrors
	 * @param int $runTime
	 * @return type
	 */
	public function updateBatchRun(int $runId, \DateTime $finished, bool $hadErrors, int $runTime) {
		$finishedTimestamp = $finished->format('Y-m-d H:i:s');
		$status = $this->db->update(self::BATCH_RUNS_TABLE, [
			'finished' => $finishedTimestamp,
			'hadErrors' => $hadErrors,
			'runTime' => $runTime
				], ['id' => $runId]);

		return $status;
	}

	public function isLastProcessStillRunning(){
		$lastRun = $this->getLastRun();
		if(!is_array($lastRun)){
			return false;
		}
		return (bool)posix_getpgid($lastRun['processId']);
	}
	
	/**
	 * Get loaded jobs for the runid
	 * @param int $runId
	 * @return boolean
	 */
	public function getLoadedJobs(int $runId) {
		if (!$this->getRun($runId)) {
			Logger::getLogger()->addAlert("Batch run does not exist!", $runId);
			return false;
		}
		return $this->batchJobs->getJobByBatchRun($runId);
	}

	/**
	 * Load jobs of the given type into the batch run
	 * @param int $runId
	 * @param BatchTypeEnum $jobType
	 * @return boolean
	 */
	public function loadJobs(int $runId, BatchTypeEnum $jobType) {
		$dbJobs = $this->batchJobs->getAvailableJobs($jobType);
		if (!$dbJobs) {
			return false;
		}
		$jobs = [];
		foreach ($dbJobs as $dbJob) {
			$jobs[] = [
				'batchRunId' => $runId,
				'batchJobId' => $dbJob['id']
			];
		}
		return $this->db->insert_batch(self::BATCH_RUNS_JOBS_LOOKUP_TABLE, $jobs);
	}

	/**
	 * Get the given run from the database if it exists
	 * @param int $batchRunId
	 * @return array|boolean
	 */
	public function getRun(int $batchRunId) {
		$result = $this->db->select("*")
				->where("id", $batchRunId)
				->get(self::BATCH_RUNS_TABLE)
				->row_array();

		if (isset($result)) {
			return $result;
		}
		return false;
	}
	
	/**
	 * Check if the last batch run is in error mode
	 * @return bool
	 */
	public function lastRunInError() : bool{
		$result = $this->db->select("*")
				->order_by("id desc")
				->limit("1")
				->get(self::BATCH_RUNS_TABLE)				
				->row_array();

		if (isset($result)) {
			return (bool)$result['hadErrors'];
		}
		return false;
	}

	/**
	 * Get the last batch run
	 * @return boolean
	 */
	public function getLastRun() {
		$result = $this->db->select("*")
				->order_by("id desc")
				->limit("1")
				->get(self::BATCH_RUNS_TABLE)				
				->row_array();

		if (isset($result)) {
			return $result;
		}
		return false;
	}

}
