<?php

if (!defined('BASEPATH'))
	exit('No direct script access allowed');

/**
 * Rough and ready CLI class that allows various things to be run directly from the command line
 */
	class FileAdminCli extends MY_MultiOutputController{

	public function __construct() {
		parent::__construct();

		if (!is_cli()) {
			show_error('Web access is not yet allowed.');
		}

		$this->load->model('file');
		$this->load->model('group');
		$this->load->model('user');
		
		$this->logger = LoggerFactory::makeLogger($this->config->item("logging_default_logger"), "FILE");
	}
	
	public function addForumBgThumbs($forumName) {
		
		$subjectId = $this->getSubjectId($forumName);
		
		$subjectPrivacy = $this->group->getForumPrivate($subjectId);
		
		$backgrounds = $this->getForumBackgrounds($subjectId);
		
		// Loop over all wallpapers and create the thumbnail based on the -background file
		foreach($backgrounds as $b) {
			$filename = $b['fileName'];
			$this->output(null, ['output' => "Processing file: " . $filename]);
			// If the thumb already exists don't create one
			if (!$this->file->checkFilePath($filename.'-bgthumb', $subjectPrivacy)) {
				$this->createForumBgThumb($filename, $subjectPrivacy);
			}
		}
		exit(0);
	}
	
	public function updateForumBgThumbs($forumName) {
		$subjectId = $this->getSubjectId($forumName);
		
		$subjectPrivacy = $this->group->getForumPrivate($subjectId);
		
		$backgrounds = $this->getForumBackgrounds($subjectId);
		
		foreach($backgrounds as $b) {
			$filename = $b['fileName'];
			$this->output(null, ['output' => "Processing file: " . $filename]);
			
			// If a thumbnail already exists, delete it
			$thumbnailFilename = $filename.'-bgthumb';
			if ($this->file->checkFilePath($thumbnailFilename, $subjectPrivacy)) {
				$this->output(null, ['output' => $thumbnailFilename." file already exists... deleting..."]);
				
				if (!$this->file->deleteFile($thumbnailFilename, $subjectPrivacy)) {
					$this->output(null, ['output' => $thumbnailFilename." file could not be deleted. Aborting processing for this background."], 1);
					
					// Don't want to exit the script, as we should process the rest of the backgrounds,
					// but we can't create this one
					continue;
				}
				$this->output(null, ['output' => "Deleted successfully"]);
			}
			
			$this->createForumBgThumb($filename, $subjectPrivacy);
			
		}
		exit(0);
	}
	
	private function createForumBgThumb($filename, $private) {
		$this->output(null, ['output' => "Creating background thumbnail"]);
		
		$this->file->createBackgroundThumbnailFromFilename($filename, $private);
		
		$this->output(null, ['output' => "Background thumbnail created"]);
	}
	
	private function getSubjectId($forumName) {
		$subjectId = $this->group->findByName($forumName);
		if (!$subjectId) {
			$this->output(null, ['output' => "Could not find a forum that matches: $forumName"]);
			exit(1);
		}
		return $subjectId;
	}
	
	private function getForumBackgrounds($forumId) {
		// Get all the non-personalised wallpapers (members and nonMembers) for this forum
		$backgrounds = $this->group->getForumBackgrounds($forumId);
		$this->output(null, ['output' => count($backgrounds)." backgrounds found"]);
		return $backgrounds;
	}
	
	public function generateContentFileSizes($type, $subjectId) {
		
		$thumbsizes = array(
			"lightbox-med" => "1024x768",
			"lightbox-lg" => "1280x720",
			"lightbox-sm" => "800x600"
			
		);
		
		$files = $this->file->getContent($type, $subjectId);
		
		foreach($files as $file) {
			$this->file->generateContentFileSizes($file["fileName"],$file["fileType"],$file["userId"],null,0,$file["private"], $thumbsizes);
		}
		$this->output(null, ['output' => "Files generated"]);
	}
}
