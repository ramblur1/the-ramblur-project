<?php

/**
 *
 * @author pberry
 */
class BatchJobCLI extends CI_Controller {

	public function __construct() {
		parent::__construct();

		if (!is_cli()) {
			return false;
		}
		$this->load->model('batchRuns');
	}

	public function getQueuedEmailJobs() {
		try {
			
			// Super simple locking, if the process id for the last run exists, don't run again
			/**
			 *  @todo Expand this to do job type based locking, to be distributable across machines, etc
			 */
			$lastProcessId = $this->batchRuns->isLastProcessStillRunning();			
			if(!$lastProcessId){
				$jobs = $this->batchRuns->startBatchRun(Ramblur\Batch\BatchTypeEnum::EMAIL());
			}else{
				Logger::getLogger()->addDebug("Last batch run is still running", [$lastProcessId]);
			}
		} catch (Exception $e) {
			echo $e->getMessage();
		}
	}

}
