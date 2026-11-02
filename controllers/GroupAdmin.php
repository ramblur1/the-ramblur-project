<?php

/**
 * Controller for forum admin tasks
 *
 * @author pberry
 */
use Entities\Forum\Visibility;

class GroupAdmin extends MY_MultiOutputController {

	function __construct() {
		parent::__construct();
		if (!is_cli()) {
			show_error('Web access is not yet allowed.');
		}

		$this->load->model('user');
		$this->load->model('group');
		$this->load->model('file');
		$this->load->model('SubjectAttribute');

		$this->logger = LoggerFactory::makeLogger($this->config->item("logging_default_logger"), "FILE");
	}

	/**
	 * Sets a forums privacy level and moves its assets if moving from public to private visiblity
	 * 
	 * The privacy level is the desired level to move the forum to must match one
	 * in the Visibility class
	 * 
	 * Setting ignoreAssets to false prevents the assets from moving and only
	 * does the DB changes
	 * 
	 * Assets are only moved if moving from public types to private type or vice a versa
	 * they aren't moved if staying within the public or private boundary - that is
	 * if the function is run many times with the same privacy level only the db
	 * changes occur.  
	 * 
	 * ignoreAssets is a little dangerous as it leaves the assets where they are and can 
	 * cause confusing states.  Its for forcing the db to change regardless of 
	 * the state of the assets (missing files, etc). Use it sparingly
	 * 
	 * 
	 * @param type $forumName 
	 * @param type $privacyLevel  The desired privacy level (Visibility class)
	 * @param $ignoreAssets  Do database changes only
	 */
	public function setForumPrivacy($forumName, $privacyLevel, $ignoreAssets = false) {
		if (!is_cli()) {
			show_error('Web access is not yet allowed.');
		}

		$subjectId = $this->group->findByName($forumName);
		if (!$subjectId) {
			$this->output(null, ['output' => "Could not find a forum that matches: $forumName"], 1);
			return false;
		}

		if (!in_array($privacyLevel, ["private", "public", "closed"])) {
			$this->output(null, ['output' => "$privacyLevel must be 'public',  'private' or 'closed'"], 1);
			return false;
		}

		/** Retrieve the current forum privacy level for later rollback **/
		$forumIsCurrentlyPrivate = $this->group->getForumPrivate($subjectId);
		$currentForumVisibility = new Visibility($this->SubjectAttribute->getForumVisibility($subjectId, $forumIsCurrentlyPrivate));
		if ($forumIsCurrentlyPrivate === null) {
			$this->output(null, ['output' => "Could not retrieve the privacy level of the existing forum"], 1);
			return false;
		}

		//Determine if the forum is going from open to private or vice a versa
		$desiredVisibility = new Visibility($privacyLevel);
		if ($desiredVisibility->equals(Visibility::PUBLIC_()) || $desiredVisibility->equals(Visibility::CLOSED_())) {
			$toBecomePrivate = 0;
		} else {
			$toBecomePrivate = 1;
		}

		//Test run the asset move
		$moveAssets = false;
		if (!$ignoreAssets) {
			//If the forum isn't private and it is to become private, 
			//or the forum is private and is to become not private we need to move the assets
			if ((!$forumIsCurrentlyPrivate && $toBecomePrivate) || ($forumIsCurrentlyPrivate && !$toBecomePrivate)) {
				// Get the filenames for the subject
				$fileNames = array_column($this->group->getForumFilenames($subjectId), "fileName");
				if (empty($fileNames)) {
					$moveAssets = false;
				} else {
					$moveAssets = $this->file->testMoveFileAssetCollection($fileNames, $toBecomePrivate);
					if ($moveAssets !== true) {
						$this->logger->addError("Failed to test move the assets",[$moveAssets]);
						$this->output(null, ['output' => "The test run of the asset mover failed: " . $forumName], 1);
						return false;
					}
				}
			}
		}

		//Update the database to set the subject to private
		$updatedSubject = $this->group->updateForumPrivacy($subjectId, $desiredVisibility);
		if (!$updatedSubject) {
			$this->output(null, ['output' => "Could not update the database privacy level for: " . $forumName], 1);
			return false;
		}				

		//Actually move the assets
		if ($moveAssets === true) {
			$config = new \Ramblur\CIUtils\ConfigHelper($this->get_instance()) ;
			$archivePath = $config->getConfig("base_filepath");
			$backupFilePath = $archivePath."/asset-backups/asset-archive-" . $forumName."-".time() . ".tar";
			//Move the file assets
			if ($this->file->moveFileAssetCollection($fileNames, $toBecomePrivate, $backupFilePath)) {
				$this->output(null, ['output' => "Successfully moved the file assets and changed the privacy level for: " . $forumName . "\nAssets were backed up to $backupFilePath"], 0);
				return true;
			} else {
				//Assets failed to move, return the database to as it was to begin with
				$updatedSubject = $this->group->updateForumPrivacy($subjectId, $currentForumVisibility);
				$this->output(null, ['output' => "Could not move the asset files for: " . $forumName . "\nAssets were backed up to $backupFilePath"], 1);
				return false;
			}

			return false;
		}
		return true;
	}

}
