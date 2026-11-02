<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

require_once APPPATH.'interfaces/IReportableContent.php';


use \Ramblur\CIUtils\ConfigHelper;
class File extends CI_Model implements IReportableContent {

	private $logger ;
	private $configHelper;
	
	function __construct() {
		$this->load->library('logging/LoggerFactory', []);
		
		// Call the Model constructor
		parent::__construct();		
		$this->load->library('assets/DiskDirectoryAssetPath', []);
		$this->load->library('assets/FileAssetPath', []);		
		$this->load->library('assets/IncompleteDirectoryAssetPath', []);
		
		$this->load->library('assets/AssetMover', []);
		$this->load->library('assets/PotentialMove', []);
		$this->load->library('assets/PotentialMoves', []);
		
		$this->config->load("logging") ;				
		$this->logger = LoggerFactory::makeLogger($this->config->item("logging_default_logger"), "FILE") ;	
		
		$this->configHelper = new ConfigHelper(get_instance());
		
		
	}

	// sourced from http://stackoverflow.com/questions/12661/efficient-jpeg-image-resizing-in-php
	// author: Tim Eckel
	// original: http://us.php.net/manual/en/function.imagecopyresampled.php#77679
	function fastimagecopyresampled (&$dst_image, $src_image, $dst_x, $dst_y, $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h, $quality = 3) {
		// Plug-and-Play fastimagecopyresampled function replaces much slower imagecopyresampled.
		// Just include this function and change all "imagecopyresampled" references to "fastimagecopyresampled".
		// Typically from 30 to 60 times faster when reducing high resolution images down to thumbnail size using the default quality setting.
		// Author: Tim Eckel - Date: 09/07/07 - Version: 1.1 - Project: FreeRingers.net - Freely distributable - These comments must remain.
		//
		// Optional "quality" parameter (defaults is 3). Fractional values are allowed, for example 1.5. Must be greater than zero.
		// Between 0 and 1 = Fast, but mosaic results, closer to 0 increases the mosaic effect.
		// 1 = Up to 350 times faster. Poor results, looks very similar to imagecopyresized.
		// 2 = Up to 95 times faster.  Images appear a little sharp, some prefer this over a quality of 3.
		// 3 = Up to 60 times faster.  Will give high quality smooth results very close to imagecopyresampled, just faster.
		// 4 = Up to 25 times faster.  Almost identical to imagecopyresampled for most images.
		// 5 = No speedup. Just uses imagecopyresampled, no advantage over imagecopyresampled.

		if (empty($src_image) || empty($dst_image) || $quality <= 0) {
			return false;
		}
		if ( $quality < 5 && (($dst_w * $quality) < $src_w || ($dst_h * $quality) < $src_h) ) {
			$temp = imagecreatetruecolor ($dst_w * $quality + 1, $dst_h * $quality + 1);
			imagecopyresized ($temp, $src_image, 0, 0, $src_x, $src_y, $dst_w * $quality + 1, $dst_h * $quality + 1, $src_w, $src_h);
			imagecopyresampled ($dst_image, $temp, $dst_x, $dst_y, 0, 0, $dst_w, $dst_h, $dst_w * $quality, $dst_h * $quality);
			imagedestroy ($temp);
		} else imagecopyresampled ($dst_image, $src_image, $dst_x, $dst_y, $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h);

		return true;
	}
	
	//get the file info(background info)
	function fileInfo($fileName) {
		$query = "SELECT fileType,userId,fileName,Content.subjectId,private,bgParams FROM Content LEFT JOIN Subject ON Content.subjectId=Subject.subjectId WHERE fileName = ?;";
		$query = $this->db->query($query,array($fileName));
		if($query->num_rows() === 0) {
			return false;
		}
		
		$result = $query->row();
		return $result;
	}
	
	/**
	 * Property names from fileInfo are not what javascript client expects when drawing 
	 * the file node, so use this function instead.
	 * 
	 * @param string $fileName
	 */
	public function drawableInfo($fileName) {
		$this->db->select("
			c.userTitle AS 'title',
			c.description,
			c.URL AS 'url',
			c.URL,
			c.numViews,
			c.fileName AS 'filename',
			c.fileName AS 'post',
			c.fileName AS 'original',
			c.fileType AS 'type',
			c.timeStamp AS 'created',
			c.modDeleted AS 'deleted',
			s.private,
			s.subjectName,
			u.userId,
			u.userName,
			u.gender,
			u.age,
			o.name AS 'country',
			o.countryId")
		->select("LCASE(modDeleteReason) AS 'deleteReason'", FALSE)
		->select("CONCAT(u.avatar, '-avatar') AS 'avatar'", FALSE)
		->from('Content AS c')
		->join('User AS u', 'u.userId = c.userId')
		->join('Country AS o', 'o.countryId = u.countryId', 'left')
		->join('Subject AS s', 's.subjectId = c.subjectId')
		->where('fileName', $fileName);
		$file = $this->db->get();
		if($file->num_rows() > 0) {
			$file = $file->first_row('array');
			// photo draw expect count, so include here. Always only one file, so count = 0
			$file['count'] = 0;
			return $file;
		} else {
			return false;
		}
	}
	
	/**
	 * Returns the subjectId related to the given fileName.
	 * 
	 * @param string $fileName
	 */
	public function getSubjectId($fileName) {
		$fileInfo = $this->fileInfo($fileName);
		return isset($fileInfo->subjectId) ? $fileInfo->subjectId : false;
	}
	
	/**
	 * Handles adding censor markup to text within a file (title and/or description).
	 * Checks that the original text isn't changed, and will only be censored by strikethrough.
	 * Returns true if text given was successfully edited, false otherwise.
	 * 
	 * @param string $fileName
	 * @param string $description
	 * @param string $title
	 * @param mixed $attachments - ignored, but necessary for IReportableContent interface compatibility
	 * @param string|boolean $error
	 * @return boolean
	 */
	public function censorText($fileName, $description, $title, $attachments, &$error) {
		if(is_null($description) && is_null($title)) {
			$error = 'No text has been included to censor.';
			return false;
		}
		$this->load->helper(array('htmlpurifier', 'censor'));
		
		$updateFields = array();
		if(!is_null($description)) {
			$purifiedDescription =  html_purify($description, 'post');
			$updateFields['description'] = $purifiedDescription;
		}
		if(!is_null($title)) {
			$purifiedTitle =  html_purify($title, 'post');
			$updateFields['userTitle'] = $purifiedTitle;
		}
		
		$existingContent = $this->drawableInfo($fileName);
		if(!$existingContent) {
			$error = 'The file could not be found.';
			return false;
		}
		$textChangedMsg = 'You cannot change the text of a file, only mark and unmark them as censored.';
		if(isset($purifiedDescription)) {
			$uncensoredDescription = remove_censor_markup($purifiedDescription);
			$uncensoredExistingDescription = remove_censor_markup($existingContent['description']);
			if($uncensoredDescription !== $uncensoredExistingDescription) {
				$error = $textChangedMsg;
				return false;
			}
		}
		if(isset($purifiedTitle)) {
			$uncensoredTitle = remove_censor_markup($purifiedTitle);
			$uncensoredExistingTitle = remove_censor_markup($existingContent['title']);
			if($uncensoredTitle !== $uncensoredExistingTitle) {
				$error = $textChangedMsg;
				return false;
			}
		}

		$this->db->update('Content',
			$updateFields, 
			array(
				'fileName' => $fileName
		));
		return $this->db->affected_rows() > 0;
	}
	
	/**
	 * Marks a file as having been deleted, along with a reason.
	 * Permissions should be checked before calling this.
	 * Returns true if at least one row was updated.
	 *
	 * @param string $fileName
	 * @param string $reason
	 * @return boolean
	 */
	function markAsDeleted($fileName, $reasons, $parentId = null) {
		$this->load->model('reported_content', 'reportedContent');
		for ($i = 0; $i < count($reasons); $i++) {
			$reason = mb_strtoupper($reasons[$i]);
			if(!in_array($reason,  $this->reportedContent->getReasons())) {
				$reasons[$i] = 'OTHER';
			}
		}

		$this->db->update(
			'Content',
			array(
				'modDeleted' => 1,
				'modDeleteReason' => implode(',', $reasons)
			),
			array(
				'fileName' => $fileName
			));
		return $this->db->affected_rows() > 0;
	}
	
	/**
	 * Marks a file so that it is not considered deleted. Used to
	 * restore deleted files.
	 * Permissions should be checked before calling this.
	 * Returns true if at least one row was updated.
	 *
	 * @param string $fileName
	 * @return boolean
	 */
	function markAsNotDeleted($fileName, $nodeId = null) {
		$this->db->update(
			'Content',
			array(
				'modDeleted' => 0,
				'modDeleteReason' => ''
			),
			array(
				'fileName' => $fileName
			));
		return $this->db->affected_rows() > 0;
	}
	
	/**
	 * Background is updated according to the params provided in the data array. All parameters
	 * (left, top, size, flipH and flipV) must be provided. Changes will be made to the original
	 * uncropped image, and saved as a new -background image, with a new filename (to avoid
	 * caching issues). -mobile image will be created according to the new -background image, and
	 * all other images will be moved into the new folder.
	 * 
	 * @param array $data
	 * @return boolean|string[]|unknown[]
	 */
	function updateBackground($data) {
		// Must be logged in
		$userId = $this->user->authCheck();
		if(!$userId) {
			return false;
		}
		// Is data valid?
		if(!(isset($data['id']) && isset($data['left']) && isset($data['top'])
			&& isset($data['size']) && is_numeric($data['size']) && isset($data['flipH']) && isset($data['flipV']))
		) {
			return false;
		}
		
		// Is the background one of our defaults?
		if (substr($data['id'], 0, 6) === 'sample') {
			// Copy it into user files
			$id = $this->copyAssetFileToContent('img/backgrounds/'.substr($data['id'], 6).'.jpg', $userId);
		} else {
			$id = $data['id'];
		}
		
		$backgroundInfo = $this->fileInfo($id);
		// File not found, or not a photo
		if(!$backgroundInfo || !($backgroundInfo->fileType == "photo" || $backgroundInfo->fileType == 'jpg')) {
			return false;
		}
		
		// user permissions - admin or file owner allowed
		// @todo - if the bg is linked to a forum, allow the owner of the forum as well
		if(!($this->user->adminCheck() || $userId == $backgroundInfo->userId)) {
			return false;
		}
		// Make requested updates to the file
		$changeMade = false;

		$bgParams = json_decode($backgroundInfo->bgParams, true);
		if (!$bgParams) {
			$bgParams = [
				'left' => 0,
				'top' => 0,
				'size' => 100,
				'flipH' => false,
				'flipV' => false,
			];
		}
		
		// all backgrounds are 1920x1200
		// load the UNCROPPED background in to memory
		ini_set('memory_limit', '256M');
		$image = imagecreatefromjpeg($this->setFilePath($backgroundInfo->fileName."-ucbackground",$backgroundInfo->private));
		
		// horizontal flip
		// Image could already be flipped, or require flipping
		if($data['flipH']) {
			imageflip($image, IMG_FLIP_HORIZONTAL);
		}
		// Check if a change is being made
		if ($data['flipH'] !== $bgParams['flipH']) {
			$changeMade = true;
			$bgParams['flipH'] = $data['flipH'];
		}
		
		// vertical flip
		// Image could already be flipped, or require flipping
		if ($data['flipV']) {
			imageflip($image, IMG_FLIP_VERTICAL);
		}
			// Check if a change is being made
		if($data['flipV'] !== $bgParams['flipV']) {
			$changeMade = true;
			$bgParams['flipV'] = $data['flipV'];
		}
		
		// crop (moved and/or zoomed)
		if((is_numeric($data['top']) && $data['top'] != $bgParams['top']) || (is_numeric($data['left']) && $data['left'] != $bgParams['left']) || (is_numeric($data['size']) && $data['size'] !== $bgParams['size'])) {
			
			// find out the original width and height of the ucbackground
			$imageSize = getimagesize($this->setFilePath($backgroundInfo->fileName."-ucbackground",$backgroundInfo->private));
			$imageWidth = $imageSize[0];
			$imageHeight = $imageSize[1];
			$ratio = $imageWidth / 1920;
			
			// create a new image for the adjusted bit
			$dest = imagecreatetruecolor(1920, 971);
			
			// do the resampled bit
			$resampleResult = $this->fastimagecopyresampled($dest, $image, $data['left'], $data['top'], 0, 0, (($imageWidth / 100) * $data['size']), (($imageHeight / 100) * $data['size']), $imageWidth, $imageHeight);
			if($resampleResult) {
				$changeMade = true;
				// working with the new image now
				// don't need the old image anymore
				imagedestroy($image);
				$image = $dest;
			}
			$bgParams['left'] = $data['left'];
			$bgParams['top'] = $data['top'];
			$bgParams['size'] = $data['size'];
		}
		
		if(!$changeMade) {
			imagedestroy($image);
			return false;
		}
		
		// figure out a new filename to prevent cache problems
		// instead of this, just replace -background file, and recalculate mobile background.
		// How to get round cache issues?
		// use a new filename, but copy all thumbnails etc to new dir, replace -background and -mobilebackground, and delete old dir
		$filename = $this->globals->uuid();
		
		// then save it
		imagejpeg($image, $this->setFilePath($filename.'-background', $backgroundInfo->private), 90);
		
		// Update mobile and thumb background as well, based off the new background image
		$this->createBackgroundThumbnail($image, $filename, $backgroundInfo->private);
		$this->createMobileBackground($image, $filename, $backgroundInfo->private);
		
		// don't need the image anymore
		imagedestroy($image);

		// if the file worked
		if (file_exists($this->getFilePath($filename.'-background', $backgroundInfo->private))) {
		
			// Update the content table to use the new file, and with new params
			// No need to update the subjectDisplay table which is where backgrounds are retrieved from, as the Content update cascades
			$this->db->update('Content', ['fileName' => $filename, 'bgParams' => json_encode($bgParams)], ['fileName' => $backgroundInfo->fileName]);

			$thumbs = array(
				// also want the unsuffixed file
				"",
				"-ucbackground",
				"-maxresdefault",
				"-sddefault",
				"-hqdefault",
				"-mqdefault",
				"-avatar",
				"-default",
				"-icon64",
				"-icon48",
				"-icon24",
				"-icon16"
			);
			
			foreach($thumbs as $s) {
				if (file_exists($this->getFilePath($backgroundInfo->fileName.$s, $backgroundInfo->private))) {
					copy($this->getFilePath($backgroundInfo->fileName.$s, $backgroundInfo->private), $this->getFilePath($filename.$s, $backgroundInfo->private));
				}
			}
			
			// delete files, including directories which would be empty, as there is no reference to the filename any more, and the contents have been copied
			$this->removeFiles($backgroundInfo->fileName, $backgroundInfo->private);
			return [
				'id' => $filename,
				'src' => $filename . '-ucbackground'
			];
		}
		return false;
	}

	/**
	 * [store stores the file(photo,audio,video,link,drawing.....)]
	 * @param  [type] $data [description]
	 * @return [type]       [description]
	 */
	function store($data, $fromServer=false) {
	
		if (isset($data["group"]) && !filter_var($data["group"],FILTER_VALIDATE_INT) && $data["group"] !== 0) {
			$this->logger->addError("Group data failed to validate",[$data]);
			return false;
		}				
		
		$this->load->helper(array('htmlpurifier', 'censor', 'stripper'));

		// File is from a user, check if the user is logged in
		$userId = $this->user->authCheck();

		// Allow anonymous, but only if uploading to no specific subject ie. groupId = 0
		if ($userId === false && (!isset($data["group"]) || $data["group"] !== 0)) {
			return false;
		}
		$results = array();
		
		// added - cjt
		$results["title"] = (isset($data['userTitle'])&&!empty($data['userTitle'])) ? stripper($data['userTitle']) : '';
		$results["license"]='';
		// generate a random filename
		$filename = $this->globals->uuid();
		
		if ($filename === false) {
			$this->logger->addError("No filename set", $data);
			return false;
		}
		
		if (!isset($data['userFileName'])) {
			$data['userFileName'] = '';
		}
		
		$this->logger->addDebug("Uploading...",["fileName"=>$filename,"userFileName"=>$data['userFileName']]) ;

		// Check if file is uploaded to a public or private forum

		// "device" uploads not passing subjectName -> intercept and check for private via subjectId instead - cjt
		if(!isset($data["subjectName"])) {
	
			$query = "SELECT private from Subject WHERE subjectId = ?";
			$params = array($data["group"]);

		} else {

			$query="SELECT private from Subject WHERE subjectName = ?";
			$params=array($data["subjectName"]);

		}
		if ($data["group"] == 0) {
			$private = false;
			$data['group'] = null;
		} else {
			$query = $this->db->query($query, $params);
			$private = $query->result_array();
			$private = $private[0]["private"];
			$serverFile = NULL;
		}

		// if any supported properties exist
		if (isset($data['local'])
			|| isset($data['file'])
			|| isset($data['photo'])
			|| isset($data['drawing'])
			|| isset($data['sound'])
			|| isset($data['link'])
		) {

			// if a file was provided
			// !!! SECURITY RISK ALLOWING $data["local"] - FIX THIS !!! ????
			if (isset($data['local'])
				|| isset($data['file'])
				|| isset($data['photo'])
				|| isset($data['drawing'])
				|| isset($data['sound'])
			) {

				if (isset($data['local']) && $data['local'] != '') {
					// move the local file to the new spot
					copy($data['local'], $this->setFilePath($filename,$private));
				} else {
					// store the file
					if (isset($data['file'])) {
						if ($data['file'] == '') {
							return false;
						}
						// Check file type is allowed
						$allowedFileExt = ['pdf', 'docx', 'xlsx', 'pptx', 'odt', 'rtf', 'txt', 'gif', 'jpg', 'jpeg', 'png', 'wav', 'mp3'];

						if (!isset($data['fileExt']) || !in_array($data['fileExt'], $allowedFileExt)) {
							return false;
						}
						$serverFile = $data["file"];
						$tmp = mb_substr($data["file"],mb_strpos($data["file"],",")+1);
						
					} else if (isset($data['photo'])) {
						if ($data['photo'] == '') {
							return false;
						}
						$serverFile = $data["photo"]; 
						//$tmp = mb_substr($data["photo"], 22);
						$tmp = mb_substr($data["photo"],mb_strpos($data["photo"],",")+1);
						
					} else if (isset($data['video'])) {
						if ($data['video'] == '') {
							return false;
						}
						$serverFile = $data["video"];  
						$tmp = mb_substr($data["video"],mb_strpos($data["video"],",")+1);
						
					} else if (isset($data['drawing'])) {
						if ($data['drawing'] == '') {
							return false;
						}
						$serverFile = $data["drawing"];  
						$tmp = mb_substr($data["drawing"],mb_strpos($data["drawing"],",")+1);
						
					} else if (isset($data['sound'])) {
						if ($data['sound'] == '') {
							return false;
						}
						$serverFile = $data["sound"];  
						$tmp = mb_substr($data["sound"],mb_strpos($data["sound"],",")+1);
					}
					
					$tmp = str_replace(' ','+',$tmp);
					if ($fromServer) {
						$tmp = base64_decode($serverFile,true);
					} else {
						$tmp = base64_decode($tmp,true);
					}
					if($tmp === false){
						$this->logger->addError("FileStore base64_decode failed", $data);									
						return false;
					}

					$filePath = $this->setFilePath($filename,$private);
					if(!file_put_contents($filePath, $tmp)){
						$this->logger->addCritical("Could not save the uploaded file",["filePath"=>$filePath,"data"=>$data]);
					}
					
					if (isset($data['file'])) {
						$this->logger->addDebug("Copying file so it has extension",["fileName"=>$filename,"isPrivate"=>$private,"data"=>$data]);
						
						$fileAssetPath = $this->getFileAssetPath($filename,$private) ;
						$DAP = $this->getDirectoryAssetPath($filename, $private);						
						if(!$DAP->copyFileAssetWithin($fileAssetPath,$filename.'.'.$data['fileExt'])){
							$this->logger->addCritical("Could not copy file",["filePath"=>$DAP->getFullPath(),"fileName"=>$filename,"isPrivate"=>$private,"FA"=>$fileAssetPath->getFullPath()]);
						}
						
					}
				}
					
				// clear the vars as we don't need the data anymore.
				$tmp = $serverFile = NULL;

				// call to clamAV daemon here, return false if clamav has a hit or error.
				// could be some false hits but we will accept that for now
				// Note:  Use of the --fdpass option means that the input file needs to be
				// accessible from the host executing clamav daemon.

				$cmd = "/usr/bin/clamdscan --fdpass " . $this->getFilePath($filename,$private);

				exec($cmd,$output,$result);

				if ($result !== 0){
					$this->logger->addError("fileStore clamdscan failed", ['fileName' => $filename, 'private' => $private, 'inputData' => $data, 'cmd'=>$cmd, 'output'=>$output]);					
					return false;
				}

				if (file_exists($this->setFilePath($filename,$private))) {
						
					// delete file if it's too big
					if (filesize($this->setFilePath($filename,$private)) > $this->config->item('mufs')) {
						$this->removeFiles($filename, $private);
						return false;
					}
					// create the node
					if (isset($data['drawing'])) {
						$results["type"] = 'drawing';
						$results["filename"] = $filename;
						$results['URL'] = '';
						$results['desc'] = '';
					} else {
						// find the mime type of the newly uploaded file
						// as people will upload the wrong thing of course
						$finfo = finfo_open(FILEINFO_MIME_TYPE); // return mime type ala mimetype extension

						$fileType = finfo_file($finfo, $this->setFilePath($filename,$private));
						finfo_close($finfo);
						if ($fileType == "image/png" || $fileType == "image/jpeg" || $fileType == "image/gif" ) {
							$results["type"] = 'photo';//do we need to add background type aswell?
							$results["filename"] = $filename;
							$results["URL"] = '';
							//photo doesnt have a comment
							if (isset($data["comment"]) && $data['comment'] != '') {
								//$results["desc"]=mb_substr(censor_text(html_purify($data["comment"], 'post')), 0, 119);  // could/oughta change
								// Filter out all characters except unicode alphanumerics and separators b/s this value gets written to data- attribute
								// later on
							 	$results["desc"] = mb_substr(censor_text(stripper($data["comment"])), 0, 119);

							} else {
								$results["desc"] = '';
							}
							$results["duration"] = '';
							$results["numViews"]='';
						} else if ($fileType == "application/ogg"
							|| $fileType == "video/ogg"
							|| $fileType == "video/mp4"
							|| $fileType == "video/mpeg"
							|| $fileType == "video/webm"
							|| $fileType == "video/avi"
							|| $fileType == "video/x-msvideo"
							|| $fileType == "video/quicktime"
						) {
							$results["filename"] = $filename;
							// how do we want the videos to be saved
							$format = "webm";

							// if it's an odd format
							if ($fileType != "video/".$format) {

								// !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
								// SHIFT THIS TO A BACKGROUND PROCESS AT SOME POINT
								// !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!

								// convert it
								// use -pix_fmt yuv420p to stay compatible with chrome?
								$cmd = '/usr/bin/avconv -i '. $this->getFilePath($filename,$private) . ' ' . $this->getFilePath($filename,$private) . '.' . $format . '';
								$tmp = `$cmd`;

								// if it worked, move the file over the original
								if (file_exists($this->setFilePath($filename,$private).".".$format."")) {
									rename($this->setFilePath($filename,$private).".".$format."", $this->setFilePath($filename,$private));
								// if it didn't work, delete the file as we can't show it anyway
								} else {
									unlink($this->setFilePath($filename,$private));
								}
							}

							// if we still have a file
							if (file_exists($this->setFilePath($filename,$private))) {

								if (isset($data["comment"]) && $data['comment'] != '') {
									// Filter out all characters except unicode alphanumerics and separators b/s this value gets written to data- attribute
									// later on
									$results["desc"] = mb_substr(censor_text(stripper($data["comment"])), 0, 119);
									//$results["desc"] = mb_substr(censor_text(html_purify($data["comment"], 'post')), 0, 119);
								} else {
									$results["desc"] = '';
								}

								$results["type"] = 'video'; 
								$results["URL"] = ''; 
								$results["duration"] = ''; 
								$results["numViews"] = ''; 

								// videos are a little different
								//$dstfile = $this->globals->uuid();
								// exec("/usr/bin/ffmpeg -itsoffset -105 -i ".$this->getFilePath($filename)." -vcodec mjpeg -vframes 1 -an -f rawvideo -s 320x180 ".$this->getFilePath($dstfile));
								$cmd = '/usr/bin/avconv -itsoffset 105 -i ' . $this->getFilePath($filename,$private) . ' -t 1 -vsync 1 -r 1 -an -s 320x180 ' . $this->getFilePath($filename) . '.jpg';
								$tmp = `$cmd`;

								// save it to the node
								if (file_exists($this->setFilePath($filename,$private).'.jpg')) {
									// rename the file
									rename($this->setFilePath($filename,$private).'.jpg', $this->setFilePath($filename,$private).'-mqdefault');
								}
								$cmd = '/usr/bin/avconv -itsoffset 105 -i ' . $this->getFilePath($filename,$private) . ' -t 1 -vsync 1 -r 1 -an -s 120x90 ' . $this->getFilePath($filename,$private) . '.jpg';
								$tmp = `$cmd`;

								// save it to the node
								if (file_exists($this->setFilePath($filename,$private).'.jpg')) {
									// rename the file
									rename($this->setFilePath($filename,$private).'.jpg', $this->setFilePath($filename,$private).'-default');
								}
							}
						} else if ($fileType == "audio/wav"
							|| $fileType == "audio/x-wav"
							|| $fileType == "audio/mpeg"
							|| $fileType == "audio/mp3"
							|| $fileType == "audio/ogg"
						) {
							$results["filename"] = $filename;
							$format = "mp3";
							// if it's an odd format
							if ($fileType != "audio/".$format) {
								// convert it
								$cmd = '/usr/bin/avconv -i ' . $this->getFilePath($filename,$private) . ' ' . $this->getFilePath($filename,$private) . '.' . $format . '';
								$tmp = `$cmd`;
								
								// if it worked, move the file over the original
								if (file_exists($this->setFilePath($filename,$private).".".$format."")) {
									rename($this->setFilePath($filename,$private).".".$format."", $this->setFilePath($filename,$private));
									// if it didn't work, delete the file as we can't show it anyway
								} else {
									unlink($this->setFilePath($filename,$private));
									return false;
								}
							}
								
							if (isset($data["comment"]) && $data['comment'] != '') {
								// Filter out all characters except unicode alphanumerics and separators b/s this value gets written to data- attribute
								// later on
								$results["desc"] = mb_substr(censor_text(stripper($data["comment"])), 0, 119);
								//$results["desc"] = mb_substr(censor_text(html_purify($data["comment"], 'post')), 0, 119);
							} else {
								$results["desc"] = '';
							}

							$results["type"] = 'sound';
							$results["URL"] = ''; 
							$results["duration"] = ''; 
							$results["numViews"] = ''; 

						} else {

							if (isset($data["comment"]) && $data['comment'] != '') {
								// Filter out all characters except unicode alphanumerics and separators b/s this value gets written to data- attribute
								// later on
								$results["desc"] = mb_substr(censor_text(stripper($data["comment"])), 0, 119);
								//$results["desc"] = mb_substr(censor_text(html_purify($data["comment"], 'post')), 0, 119);
							} else {
								$results["desc"] = '';
							}

							$results["type"] = 'file';
							$results["filename"] = $filename;
							$results["URL"] = ''; 
							$results["duration"] = ''; 
							$results["numViews"] = ''; 
						}
					}
				}
					
			// if a link was submitted
			} else if (isset($data['link']) || isset($data['video'])) {

				$data["link"] = $this->checkURL($data["link"]);
				if ($data["link"] == false) {
					$this->logger->addError("URL could not be verified", $data) ;					
					return (false);
				}
				$results["type"] = 'link';

				$results["filename"] = $filename;

				$results["URL"] = $data["link"];
				// if the link is a youtube video
				if (stripos($data["link"], "youtube") !== false || stripos($data["link"], "youtu.be") !== false) {
				
					// test link
					// https://www.youtube.com/watch?v=57dzaMaouXA
					// https://www.googleapis.com/youtube/v3/videos?part=snippet&id=57dzaMaouXA&fields=items%2Fsnippet&key=AIzaSyC2VGnY-KOi90ym6QzX3lfg1bqMpFIcpqU
					// https://www.googleapis.com/youtube/v3/videos?part=statistics&id=VorRingNbKg&fields=items%28statistics%28viewCount%29%29&key=AIzaSyBobbCj5F26t85qW1AU7N61C9RPxIT3BEk
					// https://www.googleapis.com/youtube/v3/videos?part=statistics,contentDetails,snippet&id=VorRingNbKg&fields=items(contentDetails(duration),statistics(viewCount),snippet)&key=AIzaSyBobbCj5F26t85qW1AU7N61C9RPxIT3BEk
					// if its a valid link, for example,  https://www.youtube.com/watch?v=Kf1MHtHtITE or https://www.youtube.com/watch?feature=player_detailpage&v=Kf1MHtHtITE or <iframe width="640" height="360" src="https://www.youtube.com/embed/Kf1MHtHtITE?feature=player_detailpage" frameborder="0" allowfullscreen></iframe>

					if (stripos($data["link"], "youtu.be") !== false
						|| (stripos($data["link"], "?") !== false && stripos($data["link"], "v=") !== false)
					) {

						// parse it
						// returns as $tmp
						if (stripos($data["link"], "youtube") !== false) {
							parse_str(parse_url($data["link"], PHP_URL_QUERY), $tmp);
						}else{
							$tmp["v"] = basename($data["link"]);
						}
						// if we have something
						if ($tmp !== false && count($tmp) > 0 && isset($tmp["v"]) && $tmp["v"] != "") {
							// youtube shared links can contain a query string (eg. for a playlist), so remove
							if (mb_strpos($tmp["v"], '?') !== false) {
								$tmp['v'] = mb_strstr($tmp['v'], '?', true);
							}
							$data["URL"] = "https://www.youtube.com/embed/".$tmp['v']."?rel=0";
							// API key:		AIzaSyBobbCj5F26t85qW1AU7N61C9RPxIT3BEk //AIzaSyC2VGnY-KOi90ym6QzX3lfg1bqMpFIcpqU
							// IPs:			120.146.177.39, 192.168.0.0/16
							// Activation date:	Feb 9, 2015, 5:29:00 AM  // Mar 23, 2014 4:17 PM
							// Activated by:	michael@zenure.com //lukebeales@gmail.com
							//$apiKey = "AIzaSyC2VGnY-KOi90ym6QzX3lfg1bqMpFIcpqU";
							$apiKey = "AIzaSyBobbCj5F26t85qW1AU7N61C9RPxIT3BEk";
							// call the youtube api to fetch a thumbnail

							//mn 20150313 $result = file_get_contents("https://www.googleapis.com/youtube/v3/videos?part=snippet&id=".$tmp["v"]."&fields=items(id%2Csnippet)&key=".$apiKey);
							// Only call google youtube api from here when on development (ie a single machine with full outgoing access to the internet)
							if ($this->config->item("authenticate_assets") == false) {
								
								// use key 'http' even if you send the request to https://...
								$options = array(
									'http' => array(
										'header'  => "HTTP/1.1 Content-type: application/json\r\nReferer: http://www.ramblur.com\r\n",
										//'proxy' => $this->config->item("ramblur_internal_proxy").$this->config->item("ramblur_internal_proxy_port"),
										'request_fulluri' => true
									)
								);
								$context  = stream_context_create($options);
								$url = "https://www.googleapis.com/youtube/v3/videos?";
								$options = [
									'part' => 'snippet,contentDetails,statistics',
									'id' => $tmp['v'],
									'fields' => 'items(id,snippet(channelTitle,thumbnails,title),contentDetails(duration),statistics(viewCount))',
									'key' => $apiKey
								];
								$url .= http_build_query($options, '', '&');
								$result = file_get_contents($url, false, $context);

								if ($result !== false ) {
									// decode it
									$result = json_decode($result, true);
									if (!isset($result["error"])) {
										// list all the possible youtube thumbnails from largest to smallest
										$sizes = array("maxres", "standard", "high", "medium", "default");

										// get the largest thumbnail available as we'll resize ourselves
										for ($i = 0, $ilen = count($sizes); $i < $ilen; $i++) {
											// if the thumbnail exists for this video
											if (isset($result["items"][0]["snippet"]["thumbnails"][$sizes[$i]])) {
												// download the thumbnail
												file_put_contents($this->setFilePath($filename,$private), fopen($result["items"][0]["snippet"]["thumbnails"][$sizes[$i]]["url"], 'r'));
												//if ( file_exists($this->setFilePath($dstfile)) ) {
												$results["filename"]=$filename;
												//}
												// break out of the loop as we have our goodies
												break;
											}
										}

										// get the title & channelname (which will be stored as the 'description' in the case of youtube videos)
										if (!empty($result["items"][0]["snippet"]["title"])) { //need to error handle here - jb
											$results["title"] = stripper($result["items"][0]["snippet"]["title"]);
							
											// Filter out all characters except unicode alphanumerics and separators b/s this value gets written to
											// data- attribute later on													
											$results["desc"] = 'by ' + stripper($result["items"][0]["snippet"]["channelTitle"]);
											$results["embed"] = "https://www.youtube.com/embed/".$result["items"][0]["id"]."?rel=0";
											$results["URL"] = $this->checkURL($results["embed"]);
											$results["duration"] = $this->reformatDuration($result["items"][0]["contentDetails"]["duration"]);
											$results["numViews"] = number_format($result["items"][0]["statistics"]["viewCount"]);
										}

									} else {
										return false;
									}
								}
							}
						}
					}
				// a link to vimeo
				} else if (stripos($data["link"],"vimeo.com") !== false && preg_match('/\/[0-9]{1,}$/',$data["link"], $matches)) {
					$vimeo_id=mb_substr($matches[0],1);
					// Only call vimeo api from here when on development (ie a single machine with full outgoing access to the internet)
					if ($this->config->item("authenticate_assets") == false) {
								
						// use key 'http' even if you send the request to https://...
						$options = array(
							'http' => array(
								'header'  => "Content-type: application/json\r\nReferer: ".base_url()."\r\n",
								'proxy' => $this->config->item("ramblur_internal_proxy").$this->config->item("ramblur_internal_proxy_port"),
								'request_fulluri' => true
							)
						);
						$context = stream_context_create($options);		
						$result = file_get_contents("https://vimeo.com/api/oembed.json?url=https://vimeo.com/".$vimeo_id, false, $context);

						if ($result !== false) {
							// decode it

							$result = json_decode($result, true);

							file_put_contents($this->setFilePath($filename,$private), fopen($result["thumbnail_url"], 'r'));
							$results["filename"]=$filename;
				
							if (!empty($result["title"])) { //need to error handle here - jb
								$results["title"] = $result["title"];
								// Filter out all characters except unicode alphanumerics and separators b/s this value gets written to
								// data- attribute later on													
								$results["desc"] = 'by ' + stripper($result["author_name"]);
								$results["embed"] = "https://player.vimeo.com/video/".$vimeo_id;
								$results["URL"] = $this->checkURL($results["embed"]);
								$results["duration"] = $result["duration"];
								$results["numViews"] = '';
							} 
						}
					}
				// NOT A YOUTUBE LINK OR A VIMEO LINK
				} else {

					$results['type'] = 'link'; // moved from maps condition below - no distinction req. now

					// ###########################################################################
					// FIX THIS, PHANTOMJS DOESN"T WORK WITHIN PHP BUT DOES EVERYWHERE ELSE
					// ###########################################################################

					// call the script to fetch a website thingy
					// Only call phantomjs from here when on development (ie a single machine with full outgoing access to the internet)
					if ($this->config->item("authenticate_assets") == false) {
						//$cmd="QT_QPA_PLATFORM=offscreen /usr/bin/phantomjs ". $this->config->item("phantomjs_proxy_call")." --ssl-protocol=any ".$this->config->item('root_dir')."utilities/scripts/webThumbnail.js ". $data["link"] ." ".$this->setFilePath($filename,$private);
						$cmd="/var/www/phantomjs ". $this->config->item("phantomjs_proxy_call")." --ssl-protocol=any ".$this->config->item('root_dir')."../rmbutils/config/phantomjs.js ". $data["link"] ." ".$this->setFilePath($filename,$private);
						exec($cmd,$output,$result);

						if($result !== 0){
							return(false);
						}

						chmod($this->setFilePath($filename,$private).".jpeg",0755);
						rename($this->setFilePath($filename,$private).".jpeg",$this->setFilePath($filename,$private));

						$tmp = `$cmd`;

						// RETURNS A TITLE & DESCRIPTION OF THE WEBSITE
						$tmp = json_decode($tmp, true);

						$results["title"] = html_purify($tmp["title"],'post');

						$results["desc"] = stripper($tmp["description"]);

						//$results["mime"] = $results['type']; // rem'd cjt - also set further down?
					}
				}
				// if we ended up with a nice file
				if (file_exists($this->getFilePath($filename,$private))) {
					
					$results["filename"] = $filename;
					
					$results["duration"] = '';
					$results["numViews"] = '';
				}/*  else {
					$this->logErrorMessage('FILE UPLOAD ERROR: fileStore link not saved.', ['fileName' => $filename, 'private' => $private, 'inputData' => $data]);
				} */
			}

			if (!isset($data["parent"]) || !filter_var($data["parent"],FILTER_VALIDATE_INT) ) {
				$data["parent"] = 0;
			}
					
			// if the file exists, work out some generic properties
			if (file_exists($this->setFilePath($filename,$private)) || ($results["type"] == 'link' && file_exists($this->setFilePath($filename,$private).'.jpeg'))) {

				// store the license
				$results["license"] = 'unknown';
	
				// get the size of the file
				$tmp = round(filesize($this->setFilePath($filename,$private)) / 8);
				
				$results["size"] = $tmp;
	
				$fileFiles = array('doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'rtf', 'odt', 'txt');

				// get the type of the file - spoof it if it's going to make finfo_open bork
				if (isset($data['file']) && in_array($data['fileExt'], $fileFiles)) {

					$fileType = 'application/'.$data['fileExt'];

				} else {

					$finfo = finfo_open(FILEINFO_MIME_TYPE); // return mime type ala mimetype extension
					$fileType = finfo_file($finfo, $this->setFilePath($filename,$private));
					finfo_close($finfo);

				}
						
				$results["mime"] = html_purify($fileType,'post');

				// IF MAP LINK is from Workarea Pin Location, change the fileType value
				//if(isset($data['pinloc'])) $results["type"] = "pinloc";   //not being called for pinclocs

				if (!isset($results["backgroundOrder"]) || $results["backgroundOrder"]=='undefined') { // storing a backgroundOrder
					$results["backgroundOrder"] = 999;
				}
						
				if(!isset($results["URL"])) {
					$results["URL"] = '';
				}
				if(!isset($results["desc"])) {
					$results["desc"] = '';
				}
				if(!isset($results["duration"])) {
					$results["duration"] = '';
				}
				if(!isset($results["numViews"])) {
					$results["numViews"] = 0;
				}

				// A kludge to eliminate php error messages
				if ($userId === false) {
					$userId = null;
				}

				$query = "CALL addUserContent(?,?,?,?,?,?,?,?,?,?,?,?,?,?,0,NULL,NULL,NULL,NULL)";
				$params = array(
					$results["filename"],
					stripper($results["title"]),
					$userId,
					$results["mime"],
					$results["size"],
					$results["type"],
					stripper($results["license"]),
					$data["group"],
					$data["parent"],
					$results["URL"],
					mb_substr(stripper($results["desc"]),0,120),
					stripper($data['userFileName']),
					$results["duration"],
					$results["numViews"]
				);
				$query = $this->db->query($query, $params);
				foreach ($query->result_array() as $row) {
					$nodeId = $row["thisContentId"];
					$private = $row["private"];
				}
				// update more details
				$this->updateDetails($results["filename"],$results["type"],$userId,$data["group"],$data["parent"],$private);
			
			} else if ($this->config->item("authenticate_assets") == true) {

				$query = "CALL addUserContent(?,?,?,?,?,?,?,?,?,?,?,?,?,?,1,NULL,NULL,NULL,NULL)";
				$params = array(
					$results["filename"],
					'',
					$userId,
					'image/png',
					4339,
					'link',
					'',
					$data["group"],
					$data["parent"],
					$results["URL"],
					'',
					'',
					'',
					''
				);
				$query = $this->db->query($query, $params);
				foreach ($query->result_array() as $row ) {
					$nodeId = $row["thisContentId"];
					$private = $row["private"];
				}
			} else {
				$this->logger->addError(" fileStore file not stored.", ['fileName' => $filename, 'private' => $private, 'inputData' => $data]) ;				
			}

					
			// need additional info from the upload for displaying a preview of the background
			if (isset($data['background']) && $data['background']) {
				$this->db->select('bgParams')
					->from('Content')
					->where('fileName', $filename);
				$bgParams = json_decode($this->db->get()->first_row('array')['bgParams'], true);
				return [[
					'fileName' => $filename,
					'left' => $bgParams['left'],
					'top' => $bgParams['top'],
					'size' => $bgParams['size'],
					'flipH' => $bgParams['flipH'],
					'flipV' => $bgParams['flipV'],
				]];
			} else {
				// return the filename that was created and the privacy status of the forum it was created for
				return array($filename,$private);
			}
		
		} else { //didnt find expected input(local,photo,video,sound,drawing,map,file,link)
			$this->logger->addError("fileStore invalid input.",$data) ;			
			return false;
		}
	}
	
	function URLFileExists($URL) {
		$file_headers = @get_headers($URL);
		if ($file_headers[0] == 'HTTP/1.1 404 Not Found') {
			return false;
		} else {
			return true;
		}
	}
	
	function copyAssetFileToContent($pathRelativeToAssets, $userId = false, $type = 'photo') {
		// verify source exists
		$srcFile = $this->config->item('static_assets_internal_url').$pathRelativeToAssets;
		if (!$this->URLFileExists($srcFile)) {
			$this->logger->addError("FILE COPY ERROR: File path invalid.",['file path' => $srcFile]) ;			
			return false;
		}
		// copy to uuid
		
		$filename = $this->globals->uuid();		
		if ($filename === false) {
			$this->logger->addCritical("FILE COPY ERROR: No filename could be created by the uuid function",['assets file' => $srcFile]);			
			return false;
		}
		
		$proxyExceptions = $this->config->item("ramblur_internal_proxy_exceptions") ;
		if(!$proxyExceptions){
			$proxyExceptions = [];
		}
		if(\Ramblur\Network\ProxyUtils::isUrlProxied($srcFile,  $proxyExceptions)){
			$options = array(
				'http' => array(
					'proxy' => $this->config->item("ramblur_internal_proxy").$this->config->item("ramblur_internal_proxy_port"),
					'request_fulluri' => true 
				)
			);
		}
		$context  = stream_context_create($options);
		
		$DAP = new DiskDirectoryAssetPath($filename,$this->getPublicRootFilePath(), $this->logger);
		if(!$DAP->createOnDisk()){
			$this->logger->addError("DIRECTORY CREATION ERROR: Directory wasn't created.",['source' => $srcFile, 'destination' => $DAP->getFullPath()]) ;			
			return false;
		}
			
		$fileAsset = $DAP->copyFileIn($srcFile, $filename, $context);
		if ($fileAsset === false) {
			$this->logger->addError("FILE COPY ERROR: File wasn't copied.",['source' => $srcFile, 'destination' => $DAP->getFullPath().$filename]) ;			
			return false;
		}
		
		// process
		
		// get mimeType
		$finfo = finfo_open(FILEINFO_MIME_TYPE);
		$mimeType = finfo_file($finfo, $fileAsset->getFullPath());
		$query = "CALL addUserContent(?,?,?,?,?,?,?,?,?,?,?,?,?,?,0,NULL,NULL,NULL,NULL)";
		$params = array(
			$filename,
			'',
			$userId ? $userId : null,
			$mimeType,
			round(filesize($fileAsset->getFullPath()) / 8),
			$type,
			'',
			0,
			0,
			'',
			'',
			'',
			'',
			0
		);
		$query = $this->db->query($query, $params);

		// update more details
		$this->updateDetails($filename, $type, $userId, 0, 0, false);
		
		return $filename;
	}

	/*
	 * checks for viruses
	 */
	function verifyFile($fileName) {

		$cmd = "/usr/bin/clamdscan --fdpass " . $fileName;

		exec($cmd,$output,$result);

		if ($result !== 0) {
			return FALSE;
		} else {
			return TRUE;
		}
	}

	/*
	 * checks for allowed image files
	 */
	function verifyImageFileType($fileName, $private=FALSE) {

		$finfo = finfo_open(FILEINFO_MIME_TYPE); // return mimetype extension
		$fileType = finfo_file($finfo, $this->getFilePath($fileName, $private));
		finfo_close($finfo);
		if (($fileType == "image/png") || ($fileType == "image/jpeg") || ($fileType == "image/gif")) {
			return $fileType;
		} else {
			return FALSE;
		}
	}

	// created to 'unattach' files via device - added cjt
	function unstore($data){

		if($userId = $this->user->authCheck()){

			// delete from the content table
			$sql = "DELETE FROM Content WHERE fileName = ? AND userId = ?"; 
			$params = array($data['fileName'], $userId);

			if($sql = $this->db->query($sql, $params)){
				return 1;
			}
			return false;

		}
		return false;
	}

	//  - added cjt
	// initially mobile only, now used by post edit	
	// NB: images are removed from ContentToNodes so that they don't remain in a 'gallery'
	// eg. if you upload 20 images, delete 7, you don't want 7 'owner deleted' images
	// file still remains in content table
	function detach ($data) {

		$return = array();//jbhere  $this->user->adminCheck()

		if($userId = $this->user->authCheck()){
			$this->load->helper(array('ramblurfiltervar','ramblurfilenamevalidate'));
			if (!isset($data["file"]) || !rfname_validate($data['file'])) {
				$this->error('Valid file must be supplied');
				return false;
			}
			if (isset($data["node"]) && rfilter_var($data["node"],FILTER_VALIDATE_INT,true) === false) {
				$this->error('Valid node must be supplied');
				return false;
			}
			// delete from the ContentToNodes table
			// deleting by nodeId - as fileName can be attached to other nodes
			$sql = "DELETE n.* 
				FROM ContentToNodes AS n 
				JOIN Content AS c ON c.fileName = n.fileName
				WHERE n.nodeId = ?
				AND n.fileName = ?
				AND (c.userId = ? OR (? AND n.draftNode = TRUE))"; 
			$params = array($data['node'], $data['file'], $userId, $this->user->adminCheck());

			if($sql = $this->db->query($sql, $params)){
				$return['result'] = true;
			} else $return['result'] = false;

			return $return;

		}
		return $return;
	}

	//  - added cjt
	function editAttachmentCaption ($data) {

		if ($userId = $this->user->authCheck()) {
			$this->load->helper(array('stripper','ramblurfilenamevalidate'));
			if(!isset($data['file']) ||
				rfname_validate($data['file'])===false){
				return false;
			}
			$result = array();
			//print_r($data);
			//print_r($userId);
			// @todo - htmlpurify and censor description?
			$sql = "UPDATE Content SET description = ? WHERE userId = ? AND fileName = ?"; 
			$params = array(stripper($data['caption']), $userId, $data['file']);
			
			$result['response'] = ($sql = $this->db->query($sql, $params)) ? true : false;

			return $result;
		}
		return false;

	}
	
	// generates thumbnails for a node with media attached (named 'original' with a mime type)
	function updateDetails($filename,$type,$userId,$subjectId,$nodeId,$private) {
		
		// if there is an original sized image to work from
		if ($filename === false || !file_exists($this->setFilePath($filename,$private))) {
			error_log('FILE UPLOAD ERROR: updateDetails no file specified. fileName = ' . $filename . ', private = ' . ($private ? 'true' : 'false'));
			return false;
		}

		// specific image/photo bits
		if (!($type == "video" || $type == "photo" || $type == "drawing" || $type == "link" || $type == "XXXpinloc" )) { //not being called for pinlocs
			error_log('FILE UPLOAD ERROR: updateDetails unknown filetype');
			return false;
		}
		// work out the details
		$tmp = @getimagesize($this->setFilePath($filename,$private));
		
		if ($tmp === false) {
			error_log('FILE UPLOAD ERROR: updateDetails getimagesize failed. filename: '. $filename);
			return false;
		}

		$file = array();
		if ($tmp[2] == IMAGETYPE_PNG) {
			$file["image_type"] = 'png';
		} else if ($tmp[2] == IMAGETYPE_JPEG) {
			$file["image_type"] = 'jpg';
		} else if ($tmp[2] == IMAGETYPE_GIF) {
			$file["image_type"] = 'gif';
		} else {
			$file["image_type"] = 'unknown';
		}

		// if there is a width and height to play with
		if ($tmp[0] == 0 || $tmp[1] == 0) {
			error_log('FILE UPLOAD ERROR: updateDetails imagesize gave no width or height');
			return false;
		}

		$file["image_width"] = $tmp[0];
		$file["image_height"] = $tmp[1];
		$file["image_ratio"] = $file["image_width"] / $file["image_height"];

		if ( $file["image_ratio"] > 1 ) {
			$file["image_orientation"] = "landscape";
		} else if ( $file["image_ratio"] < 1 ) {
			$file["image_orientation"] = "portrait";
		} else {
			$file["image_orientation"] = "square";
		}

		// if we can create at least one of the thumbnails
		if (!($file["image_width"] > 120 || $file["image_height"] > 90)) {
			error_log('FILE UPLOAD ERROR: updateDetails file too small');
			return false;
		}

		// Get image orientation if that is available in metadata
		$exif=@exif_read_data($this->getFilePath($filename,$private));
							
		// open image
		if ($file["image_type"] == "jpg" || $file["image_type"] == "jpeg") {
			$src = imagecreatefromjpeg($this->setFilePath($filename,$private));
		} else if ($file["image_type"] == "png") {
			$src = imagecreatefrompng($this->setFilePath($filename,$private));
		} else if ($file["image_type"] == "gif") {
			$src = imagecreatefromgif($this->setFilePath($filename,$private));
		}

		if (!isset($src) || $src === false) {
			error_log('FILE UPLOAD ERROR: updateDetails couldn\'t open image');
			return false;
		}

		// work out common thumbnails

		// POST is <= fixed width to fit a post, height will be scaled to keep aspect.
		// SQUARE / AVATAR is largest centered square area available of image
		// UCBACKGROUND is the original background proportionately resized to cover the background area
		// EVERYTHING ELSE is just scaled to fit within the boundaries

		// BACKGROUND is worked out off of the ucbackground and therefore is not listed here.  It is the centremost 1920x1200 portion of the ucbackground image.
		// MOBILE is worked out off of the background and therefore is not listed here.  It is the 2nd 1/4 from the left of the background image.

		/*$thumbsizes = array(
			"qxga" => "2048x1536",
			"ucbackground" => "1920x971",
			"maxresdefault" => "1920x1080",
			"uxga" => "1600x1200",
			"sxga" => "1280x1024",
			"hd720" => "1280x720",
			"xga" => "1024x768",
			"svga" => "800x600",
			"wvga" => "800x480",
			"pal" => "768x576",
			"sddefault" => "640x480",
			"hqdefault" => "480x360",
			"square" => "320x320",
			"mqdefault" => "320x180",
			"avatar" => "160x160",
			"default" => "120x90",
			"icon128" => "128x128",
			"icon96" => "96x96",
			"icon64" => "64x64",
			"icon48" => "48x48",
			"icon24" => "24x24",
			"icon16" => "16x16",
			"post" => "320x?"
		);*/
					
		$thumbsizes = array(
			"ucbackground" => "1920x971",
			"maxresdefault" => "1920x1080",
			"lightbox-lg" => "1280x720",
			"lightbox-med" => "1024x768",
			"lightbox-sm" => "800x600",
			"sddefault" => "640x480",
			"hqdefault" => "480x360",
			"mqdefault" => "320x180",
			"avatar" => "160x160",
			"default" => "120x90",
			"icon64" => "64x64",
			"icon48" => "48x48",
			"icon24" => "24x24",
			"icon16" => "16x16"
		);

		foreach ($thumbsizes as $key => $value) {

			// split the size in to w & h
			$tmp = explode('x', $value);

			// if its a landscape image
			if ( $file["image_ratio"] >= 1 ) {
				$w = $tmp[0];
				$h = $tmp[1];
			// for portrait images, flip the values around
			} else {
				$w = $tmp[1];
				$h = $tmp[0];
			}

			// set some default crop values just to be safe
			$cx = 0;
			$cy = 0;
			$cw = $file["image_width"];
			$ch = $file["image_height"];

			// if we are doing the uncropped background
			if ($key == "ucbackground") {

				// make a new filename as this is being done regardless
				//$dstname = $this->globals->uuid();
				$dstname = $filename.'-'.$key;
				// if either side is too small
				if ($file["image_width"] < $w || $file["image_height"] < $h) {

					// figure out which side to resize by based on which ratio is smaller
					if ($file["image_width"] / $w < $file["image_height"] / $h) {

						// resize based on the width
						$scale = $w / $file["image_width"];

					} else {

						// resize based on the height
						$scale = $h / $file["image_height"];

					}

					// fix up the width and height
					$w = round($file["image_width"] * $scale);
					$h = round($file["image_height"] * $scale);
					
				// image is bigger than what is required, save as is!
				} else {
					$w = $cw;
					$h = $ch;
				}

			// if we're doing the magic post sized thumbnail (limited by width only)
			} else if ($w == "?" || $h == "?") {

				// if width is the unknown, fix it up
				if ($w == "?") {
					$w = $h;
				}

				// if the image is smaller than the width
				if ($file["image_width"] <= $w) {
					// just use the original file as there isn't much we can do
				} else {

					// make a new filename
					//$dstname = $this->globals->uuid();
					$dstname = $filename.'-'.$key;
					// scale down to the width here
					$scale = $w / $file["image_width"];

					// now fix up the target width and height using the scale
					$w = round($file["image_width"] * $scale);
					$h = round($file["image_height"] * $scale);

				}
		
			// square formats that require cropping also
			} else if ($w == $h) {
				$dstname = $filename.'-'.$key;
				// if the image is a valid width & height
				if ($file["image_width"] >= $w && $file["image_height"] >= $h) {

					// make a new filename
					//$dstname = $this->globals->uuid();
					$dstname = $filename.'-'.$key;
					// check which side to scale by
					// get the scale of the width and see if the height fits
					if ($file["image_height"] * ($w / $file["image_width"]) <= $h) {
						// landscape
						$scale = $h / $file["image_height"];

						$cy = 0;
						$ch = $file["image_height"];

						$cx = round(($file["image_width"] - ($w / $scale)) / 2);
						$cw = $file["image_height"];
					} else {
						// portrait
						$scale = $w / $file["image_width"];

						$cy = round(($file["image_height"] - ($h / $scale)) / 2);
						$ch = $file["image_width"];

						$cx = 0;
						$cw = $file["image_width"];
					}

				}

			// any other thumbnail size
			} else {

				if ($file["image_width"] >= $w || $file["image_height"] >= $h) {

					// make a new filename
					//$dstname = $this->globals->uuid();
					$dstname = $filename.'-'.$key;
					// get the scale of the width and see if the height fits
					if ($file["image_height"] * ($w / $file["image_width"]) <= $h) {
						$scale = $w / $file["image_width"];
					} else {
						$scale = $h / $file["image_height"];
					}

					// now fix up the target width and height using the scale
					$w = round($file["image_width"] * $scale);
					$h = round($file["image_height"] * $scale);
				
				//what if the original image is smaller than the desired size?
				} else {
					copy($this->setFilePath($filename,$private),$this->setFilePath($filename.'-'.$key,$private));
				}
			}

			// if we're all good to do our trickery
			if (isset($dstname) && $dstname != '') {
				//should we increase the limit in php.ini??? ini_set('memory_limit', '256M');
				// create the destination now we have the right width & height
				$dst = imagecreatetruecolor($w, $h);

				// copy the portion from the other image in to this one.
				$this->fastimagecopyresampled($dst, $src, 0, 0, $cx, $cy, $w, $h, $cw, $ch); 

				if(!empty($exif['Orientation'])) {
					switch($exif['Orientation']) {
						case 8:
							$dst = imagerotate($dst,90,0);
							break;
						case 3:
							$dst = imagerotate($dst,180,0);
							break;
						case 6:
							$dst = imagerotate($dst,-90,0);
							break;
						default:
							break;
					}
				}
				imagejpeg($dst, $this->setFilePath($dstname,$private), 87);

				// Keep one piece of EXIF data from the original: orientation. This gets orientation for mobile-orig images
				// right for firefox and mobile safari but not anything else...
				//$cmd = '/usr/bin/exiftool -tagsfromfile '. $this->getFilePath($filename,$private) . ' -orientation ' . $this->getFilePath($dstname,$private) ;
				//$tmp = `$cmd`;

				//alternatively, read the orientation from the orignal and apply it to each and every copy...(doing that just above)

				// background needs a cropped area, based off the ucbackground image
				if ($key == "ucbackground") {

					// make a new filename
					//$dstname = $this->globals->uuid();
					$dstname = $filename.'-background';
					$dst2 = imagecreatetruecolor(1920, 971);

					// copy the centre portion from the other image in to this one.

						// if the source is bigger than 1920x1200
						if ($w > 1920 && $h > 971) {

							// figure out which side to resize by based on which ratio is smaller
							if ($w / 1920 < $h / 971) {

								// resize based on the width
								$scale = 1920 / $w;

							} else {

								// resize based on the height
								$scale = 971 / $h;

							}

							// fix up the width and height
							$nw = round($w * $scale);
							$nh = round($h * $scale);

							// create the new shrunk image
							$dstmp = imagecreatetruecolor($nw, $nh);
							$this->fastimagecopyresampled($dstmp, $dst, 0, 0, 0, 0, $nw, $nh, $w, $h); 

							// override the original
							$dst = $dstmp;

							// free up some memory
							unset($dstmp);
						} else {
							$nw = $w;
							$nh = $h;
							$scale = 1;
						}

						$x = 0 - round(($nw - 1920) / 2);
						$y = 0 - round(($nh - 971) / 2);
						$this->fastimagecopyresampled($dst2, $dst, $x, $y, 0, 0, $nw, $nh, $nw, $nh); 

						imagejpeg($dst2, $this->setFilePath($dstname,$private), 75);

						if (file_exists($this->setFilePath($dstname,$private))) {
							// store the new display parameters
							$this->db->update('Content', [
								'bgParams' => json_encode([
									'left' => $x,
									'top' => $y,
									'size' => round($scale * 100, 2),
									'flipH' => false,
									'flipV' => false
								])
							], ['fileName' => $filename]);
						}

						// mobile needs a cropped area, based off the background image
						$this->createMobileBackground($dst2, $filename, $private);

						// create a thumbnail from the background image
						$this->createBackgroundThumbnail($dst2, $filename, $private);

					// destroy the memory data
					imagedestroy($dst2);

				}

				// destroy the memory data
				imagedestroy($dst);

			}

			// clear the filename for the next round
			unset($dstname);

		}

		// One last resample to get the original rotated
		if (!empty($exif['Orientation'])) {
			switch($exif['Orientation']) {
				case 8:
					$src = imagerotate($src,90,0);
					break;
				case 3:
					$src = imagerotate($src,180,0);
					break;
				case 6:
					$src = imagerotate($src,-90,0);
					break;
				default:
					break;
			}
		}
		imagejpeg($src, $this->setFilePath($filename.'-orig',$private), 87);

		// remove the image as we don't need it no more.
		imagedestroy($src);
		$query="CALL updateUserContent(?,?,?,?,?,?,?,?,?)";
	
		$params = array(
			$filename,
			$userId,
			$subjectId,
			$nodeId,
			$file["image_type"],
			$file["image_width"],
			$file["image_height"],
			$file["image_ratio"],
			$file["image_orientation"]
		);
		$query = $this->db->query($query, $params);
	}
	
	/**
	 * Creates a new image file from the provided image that will be used as the background picture for mobile devices 
	 * 
	 * @param resource $backgroundFile the image resource identifier for the processed/cropped/zoomed background file
	 * @param string $filename
	 * @param boolean $private
	 */
	function createMobileBackground($backgroundFile, $filename, $private) {
		// Update mobile background as well, based off the new background image
		
		// create the mobile sized image
		$mobileBg = imagecreatetruecolor(1920 / 4, 971);
		
		// copy the portion from the -background image in to this one.
		$this->fastimagecopyresampled($mobileBg, $backgroundFile, 0, 0, 1920 / 4, 0, 1920 / 4, 971, 1920 / 4, 971);
		
		imagejpeg($mobileBg, $this->setFilePath($filename.'-mobile', $private), 75);
		
		// don't need the image anymore
		imagedestroy($mobileBg);
	}
	
	/**
	 * Creates a new image file from the provided image that will be used as a thumbnail for the background image
	 *
	 * @param resource $backgroundFile the image resource identifier for the processed/cropped/zoomed background file
	 * @param string $filename
	 * @param boolean $private
	 */
	function createBackgroundThumbnail($backgroundFile, $filename, $private) {
		// define the dimensions for this file
		$width = 184;
		$height = 105;
		
		// create the thumbnail sized image
		$thumb = imagecreatetruecolor($width, $height);
		
		// copy the portion from the -background image in to this one.
		$this->fastimagecopyresampled($thumb, $backgroundFile, 0, 0, 0, 0, $width, $height, 1920, 971);
		
		imagejpeg($thumb, $this->setFilePath($filename.'-bgthumb', $private), 75);
		
		// don't need the image anymore
		imagedestroy($thumb);
	}
	
	/**
	 * Creates a new image file from the provided filename
	 *
	 * @param string $filename the filename for which to create a new background thumbnail
	 * @param boolean $private
	 */
	function createBackgroundThumbnailFromFilename($filename, $private) {
		$fileHandle = imagecreatefromjpeg($this->setFilePath($filename."-background",$private));
		$this->createBackgroundThumbnail($fileHandle, $filename, $private);
		imagedestroy($fileHandle);
	}
	
	/**
	 * Returns a DirectoryAssetPath object
	 * @param type $filename
	 * @param type $private
	 * @return \DiskDirectoryAssetPath
	 */
	function getDirectoryAssetPath($filename, $private) : DiskDirectoryAssetPath{			
		if($private == true){
			$DAP = new DiskDirectoryAssetPath($filename,$this->config->item('base_filepath')."private/", $this->logger);			
		}else{
			$DAP = new DiskDirectoryAssetPath($filename,$this->config->item('base_filepath')."public/", $this->logger);						
		}
		return $DAP;
	}
	
	/**
	 * Returns a FileAssetPath object
	 * @param type $filename
	 * @param type $private
	 * @return \FileAssetPath
	 */
	function getFileAssetPath($filename, $private) : FileAssetPath{
		$DAP = $this->getDirectoryAssetPath($filename, $private) ;		
		return new FileAssetPath($filename, $DAP);
	}

	/**
	 * Get the realpath to the given filename
	 * @param type $filename
	 * @param type $private
	 * @return type
	 */
	function getFilePath($filename,$private){		
		$fileAssetPath = $this->getFileAssetPath($filename, $private) ;
		return $fileAssetPath->getFullPath() ;
	}
	
	function getRelFilePath($filename,$private){		
		if($private == true){
			$DAP = new IncompleteDirectoryAssetPath($filename, "/".$this->config->item('rel_filepath')."private/", $this->logger);						
		}else{
			$DAP = new IncompleteDirectoryAssetPath($filename, "/".$this->config->item('rel_filepath')."public/", $this->logger);						
		}
		$fileAssetPath = new FileAssetPath($filename, $DAP);
		return $fileAssetPath->getFullPath() ;
	}
	
	function getPrivateRootFilePath(){
		return $this->getRootFilePath(true);
	}
	
	function getPublicRootFilePath(){
		return $this->getRootFilePath(false);
	}

	function getRootFilePath($private) {
		if($this->config->item('authenticate_assets')==false){
			$dirname="public/";
		} else {
			$dirname="public/";
		}
		if($private == true) {
			return $this->config->item('base_filepath')."private/";
		} else {
			return $this->config->item('base_filepath').$dirname;
		}
	}

	/**
	 * Checks if the file path exists
	 * @param type $filename
	 * @param type $private - check private, otherwise check public
	 * @return type
	 */
	function checkFilePath($filename, $private){
		return file_exists($this->getFilePath($filename, $private)) ;
	}

	/**
	 * Determines the full directory path to the given asset id, if it's not
	 * on disk, creates the path then returns it
	 * @param type $filename
	 * @param type $private
	 * @return type
	 */
	function setFilePath($filename,$private){

		if($private == false && $this->config->item('authenticate_assets')==false){
			$dirname="public/";
		}else if($private == false && $this->config->item('authenticate_assets')==true){
			$dirname="public/";
		}else{
			$dirname="private/";
		}
		
		$DAP = new DiskDirectoryAssetPath($filename,$this->config->item('base_filepath').$dirname);
		if(!$DAP->isOnDisk()){			
			$DAP->createOnDisk();
		}
		
		$fileAsset = new FileAssetPath($filename, $DAP);		
		return $fileAsset->getFullPath();
	}
	
	function deleteFile($filename, $private) {
		$DAP = $this->getDirectoryAssetPath($filename, $private);
		$fileAsset = $this->getFileAssetPath($filename, $private);
		
		return $DAP->deleteFileAssetFromDisk($fileAsset);
	}

	function setAvatar($filename,$userId){

		$query="UPDATE User SET avatar = ? WHERE userId =?"; 
		$params=array($filename,$userId); 
		$query = $this->db->query($query, $params); 
	}

	function reformatDuration($subject){

		$pattern1 = '/([\x30-\x39]{1,})H/u'; 
		$pattern2 = '/([\x30-\x39]{0,})M/u'; 
		$pattern3 = '/([\x30-\x39]{0,})S/u'; 

		preg_match($pattern1, $subject, $matches1); 
		preg_match($pattern2, $subject, $matches2); 
		preg_match($pattern3, $subject, $matches3); 

		$result=''; 

		if(isset($matches3[1])){
			$result = $matches3[1] . $result;
		} 
		if(isset($matches2[1])){
			$result = $matches2[1] . ':' . $result;
		} 
		if(isset($matches1[1])){
			$result = $matches1[1] . ':' . $result;
		}
		return ($result);
	}

	function checkURL($testURL) {
		$scheme = parse_url($testURL,PHP_URL_SCHEME);

		// Whitelist the permissible URL schemes
		if (!is_null($scheme)) {
			$scheme = strtolower($scheme);
			if ($scheme != 'http' && $scheme != 'https') {
				return false;
			}
		} else { // try to salvage a url when no scheme was included
			$testURL = "http://".$testURL;
		}
		// Further validate the URL
		if (filter_var($testURL, FILTER_VALIDATE_URL) === FALSE) {
			return(false);
		}

		// Sanitise link with htmlpurifier, removes obvious xss
		$this->load->helper(array('htmlpurifier'));
		$testURL = html_purify($testURL, 'url');

		// Compare submitted link to Google's blacklist
		if (!$this->checkURLWithGoogle($testURL)) {
			return false;
		}
		return $testURL;
	}
	
	function checkURLWithGoogle($testURL){
		
		if (ENVIRONMENT === 'development') {
			return true;
		}
		$googleURL = 'https://safebrowsing.googleapis.com/v4/threatMatches:find?key=AIzaSyBobbCj5F26t85qW1AU7N61C9RPxIT3BEk';
		$client = array('clientId' => 'azure101','clientVersion'=>'1.0.0');
		$threatEntries = array();
		$threatEntries[0] = array('url'=> $testURL);

		$data = array(
			'client' => $client,
			'threatInfo' => array(
				"threatTypes"=>array('MALWARE', 'SOCIAL_ENGINEERING'),
				"platformTypes"=>array('WINDOWS','LINUX','IOS'),
				"threatEntryTypes"=>array('URL'),
				"threatEntries"=>$threatEntries
				)
			);

		// use key 'http' even if you send the request to https://...
		$options = array(
			'http' => array(
				'header'  => "HTTP/1.1 Content-type: application/json\r\nReferer: ".base_url()."\r\n",
				'method'  => 'POST',
				'proxy' => $this->config->item("ramblur_internal_proxy").$this->config->item("ramblur_internal_proxy_port"), 
				'request_fulluri' => true,
				'content' => json_encode($data)
			)
		);
		$context  = stream_context_create($options);
		$result = file_get_contents($googleURL, false, $context);
		if ($result === FALSE) {
			$this->logger->addError('LINK UPLOAD ERROR: Google URL API could not be contacted: '.[$testURL, $options]) ;			
			return false;
		}
		if (mb_substr($result,0,2) === '{}') {
			return true;
		}else{
			return false;
		}
	}
	
	/**
	 * Creates a directory path underneath the base_filepath asset structure
	 * returns false if the given path is outside the structure, or mkdir fails
	 * @param type $path
	 * @return boolean
	 */
	public function createAssetDir($path){
		$realAssetFilePath = $this->configHelper->getConfig("base_filepath") ;
		$realDesiredPath = $path;
		
		if(strpos($realDesiredPath, $realAssetFilePath) === false){
			$this->logger->addError("Path is not a valid subdirectory",[$realDesiredPath, $realAssetFilePath]);
			return false;
		}
		if(file_exists($realDesiredPath) && is_writable($realDesiredPath)){
			return true;
		}elseif(file_exists($realDesiredPath) && !is_writable($realDesiredPath)){
			$this->logger->addError("Path is not writable",[$realDesiredPath]);
			return false;
		}
		return mkdir($realDesiredPath,0770, true);
	}

	/**
	 * Deletes the file path for a given file name. Checks that the folder is empty before deleting it.
	 * @param string $filename
	 */
	function removeFiles($fileName, $private) {
		/*
		 * Top level not necessarily deletable, so check if more than one item in each level. If there is, can't delete, so move down a level,
		 * until there is only one item in the dir, or we have reached the bottom level
		 */
		// check filename is valid
		if(!preg_match('/^[\x61-\x66\x30-\x39]{8}-[\x61-\x66\x30-\x39]{4}-[\x61-\x66\x30-\x39]{4}-[\x61-\x66\x30-\x39]{4}-[\x61-\x66\x30-\x39]{12}$/u',$fileName)) {
			return;
		}

		$DAP = new DiskDirectoryAssetPath($fileName, $this->getRootFilePath($private), $this->logger);
		if($DAP->isOnDisk()){
			return $DAP->deleteFromDisk();
		}
		
		return false;
		
	}
	
	/**
	 * Moves a filepath from the public folder to the private folder. Only moves the dirs that are empty. If non-empty, creates them in private
	 * @param string $fileName
	 */
	function moveFilesToPrivate($fileName) {
		$publicRoot = $this->getPublicRootFilePath();
		$privateRoot = $this->getPrivateRootFilePath();

		//Create the new private directory asset path structure (DAP)
		$privateDirectory = new DiskDirectoryAssetPath($fileName,$privateRoot,$this->logger) ;
		
		//If the target private asset directory already exists we're going to not obliterate it, this is safe.
		if($privateDirectory->isOnDisk()){
			$this->logger->error("Could not move files to private as directory already exists",["fileName"=>$fileName,"target"=>$privateDirectory->getFullPath()]) ;
			return false;
		}
		
		if(!$privateDirectory->createOnDisk()){
			return false;
		}
						
		//Copy the existing files from the public DAP to the new private DAP
		$publicDirectory = new DiskDirectoryAssetPath($fileName,$publicRoot,$this->logger) ;
		if($publicDirectory->copyFilesTo($privateDirectory)){
			if(!$publicDirectory->deleteFromDisk()){
				$this->logger->error("FILE MOVE ERROR: Could not delete the old public directory from disk",["fileName"=>$fileName,"target"=>$publicDirectory->getFullPath()]) ;				
				return false;
			}
			return true;
		}
		
		return false;
	}
	
	/**
	 * Moves a filepath from the private folder to the public folder. Only moves the dirs that are empty
	 * @param string $fileName
	 */
	function moveFilesToPublic($fileName) {
		$privateRoot = $this->getPrivateRootFilePath();
		$publicRoot = $this->getPublicRootFilePath();
		
		$publicDirectory = new DiskDirectoryAssetPath($fileName,$publicRoot,$this->logger) ;
		
		//If the target public asset directory already exists we're going to not obliterate it, this is safe.
		if($publicDirectory->isOnDisk()){
			$this->logger->error("Could not move files to public as directory already exists",["fileName"=>$fileName,"target"=>$publicDirectory->getFullPath()]) ;			
			return false;
		}
		
		if(!$publicDirectory->createOnDisk()){
			return false;
		}
				
		$privateDirectory = new DiskDirectoryAssetPath($fileName,$privateRoot,$this->logger) ;
		if($privateDirectory->copyFilesTo($publicDirectory)){			
			if(!$privateDirectory->deleteFromDisk()){				
				$this->logger->error("FILE MOVE ERROR: Could not delete the old private directory from disk",["fileName"=>$fileName,"target"=>$privateDirectory->getFullPath()]) ;				
				return false;
			}
			return true;
		}
		return false;
	}
	
	
	/**
	 * Move the given fileAssets from public to private or vice a versa
	 * 
	 * This is dangerous so it fails fast and does lots of logging
	 * 
	 * 
	 * @param array $fileAssetNames
	 * @param type $toPrivate	 
	 * @param type $backupPath - Must be under base_filepath
	 * @return boolean
	 */
	function moveFileAssetCollection(array $fileAssetNames, bool $toPrivate, string $backupPath){
		
		if ($toPrivate) { 
			$srcRoot = $this->getPublicRootFilePath();
			$destRoot = $this->getPrivateRootFilePath();
		} else {				
			$srcRoot = $this->getPrivateRootFilePath();
			$destRoot = $this->getPublicRootFilePath();
		}
		
		$mover = new AssetMover();
		$mover->setNullSrcSkippable(true);
		$mover->addMoves(PotentialMoves::fromArray($fileAssetNames, $srcRoot, $destRoot));

		//Check if there are any actual moves to be made
		if(!$mover->hasPotentialMoves()){
			$this->logger->addError("There are no potential moves in the asset mover: ".$mover->getDescriptiveErrorStr());
			return false;
		}
		
		// Backup the asset files
		if(!$this->createAssetDir(dirname($backupPath))){
			$this->logger->addError("Could not create the backup path: ".dirname($backupPath));
			return false;
		}else{
			try{
				$mover->backupFiles(new PharData($backupPath));
			}catch(\Exception $e){
				$this->logger->addError("Could not backup the asset files",[dirname($backupPath),$fileAssetNames, $e]);
				return false;
			}
		}
		
		//Move the files
		if($mover->testMove()){
			if($mover->copyFiles()){
				if($mover->verifyMovedFiles()){
					$this->logger->addInfo("Successfully moved the file assets: ".$mover->getSuccessMessagesStr());					
					if(!$mover->deleteSrcFiles()){
						$this->logger->addError("Could not delete the source files: ".$mover->getDescriptiveErrorStr());
						return false;
					}
					return true; //Success
				}else{
					$this->logger->addError("Could not verify that the assets copied were the same: ".$mover->getDescriptiveErrorStr());					
					if(!$mover->rollBackCopy()){
						$this->logger->addError("Could not rollback the asset move as there were errors: ".$mover->getDescriptiveErrorStr());				
					}
					return false;
				}
			}else{
				$this->logger->addError("Could not perform the asset move as there were errors: ".$mover->getDescriptiveErrorStr());				
				if(!$mover->rollBackCopy()){
					$this->logger->addError("Could not rollback the asset move as there were errors: ".$mover->getDescriptiveErrorStr());				
				}
			}
			return false;
		}else{			
			$this->logger->addError("Could not perform the asset move as the test run showed there were errors: ".$mover->getDescriptiveErrorStr());
			return false;
		}
		
		return false;

	}
	
	/**
	 * Test run an asset move
	 * @param array $fileAssetNames
	 * @param type $toPrivate	 
	 * @return boolean true on success, an error messsage string on failure
	 */
	function testMoveFileAssetCollection(array $fileAssetNames, $toPrivate){
		
		if ($toPrivate) { 
			$srcRoot = $this->getPublicRootFilePath();
			$destRoot = $this->getPrivateRootFilePath();
		} else {				
			$srcRoot = $this->getPrivateRootFilePath();
			$destRoot = $this->getPublicRootFilePath();
		}
		
		$mover = new AssetMover();
		$mover->setNullSrcSkippable(true);
		$mover->addMoves(PotentialMoves::fromArray($fileAssetNames, $srcRoot, $destRoot));
		if($mover->testMove()){
			return true;
		}
		return $mover->getDescriptiveErrorStr() ;
		
	}

	function isDirectoryEmpty($dir) {
		// http://stackoverflow.com/questions/12801370/count-how-many-files-in-directory-php
		$fi = new FilesystemIterator($dir, FilesystemIterator::SKIP_DOTS);
		$itemCount = iterator_count($fi);
		if($itemCount > 1) {
			return false;
		} else {
			return true;
		}
	}
	
	function html_strip($string){
	  $string = preg_replace("/[\W^\s]/", '', $string);
	  return ($string);
	}
	function checkFileUploadStatus($fileName) {

		$this->db->select('status');
		$this->db->where('fileName', $fileName["fileName"]);
		$query = $this->db->get('Content');

		if ($query->num_rows() > 0) {
			$status = $query->result_array();
			return ($status[0]);
		} else {
			return (NULL);
		}
	}
	
	function generateContentFileSizes($filename,$type,$userId,$subjectId,$nodeId,$private,$thumbsizes) {
		
		// if there is an original sized image to work from
		if ($filename === false || !file_exists($this->setFilePath($filename,$private))) {
			error_log('FILE UPLOAD ERROR: updateDetails no file specified. fileName = ' . $filename . ', private = ' . ($private ? 'true' : 'false'));
			return false;
		}

		// specific image/photo bits
		if (!($type == "video" || $type == "photo" || $type == "drawing" || $type == "link")) {
			error_log('FILE UPLOAD ERROR: updateDetails unknown filetype');
			return false;
		}
		// work out the details
		$tmp = @getimagesize($this->setFilePath($filename,$private));
		
		if ($tmp === false) {
			error_log('FILE UPLOAD ERROR: updateDetails getimagesize failed. filename: '. $filename);
			return false;
		}
		
		//ChromePhp::log($this->setFilePath($filename . "-" . key($thumbsizes),$private));
		
		if(file_exists($this->setFilePath($filename . "-" . key($thumbsizes),$private))) {
			//return;
		}

		$file = array();
		if ($tmp[2] == IMAGETYPE_PNG) {
			$file["image_type"] = 'png';
		} else if ($tmp[2] == IMAGETYPE_JPEG) {
			$file["image_type"] = 'jpg';
		} else if ($tmp[2] == IMAGETYPE_GIF) {
			$file["image_type"] = 'gif';
		} else {
			$file["image_type"] = 'unknown';
		}

		// if there is a width and height to play with
		if ($tmp[0] == 0 || $tmp[1] == 0) {
			error_log('FILE UPLOAD ERROR: updateDetails imagesize gave no width or height');
			return false;
		}

		$file["image_width"] = $tmp[0];
		$file["image_height"] = $tmp[1];
		$file["image_ratio"] = $file["image_width"] / $file["image_height"];

		if ( $file["image_ratio"] > 1 ) {
			$file["image_orientation"] = "landscape";
		} else if ( $file["image_ratio"] < 1 ) {
			$file["image_orientation"] = "portrait";
		} else {
			$file["image_orientation"] = "square";
		}

		// if we can create at least one of the thumbnails
		if (!($file["image_width"] > 120 || $file["image_height"] > 90)) {
			error_log('FILE UPLOAD ERROR: updateDetails file too small');
			return false;
		}

		// Get image orientation if that is available in metadata
		$exif=@exif_read_data($this->getFilePath($filename,$private));
							
		// open image
		if ($file["image_type"] == "jpg" || $file["image_type"] == "jpeg") {
			$src = imagecreatefromjpeg($this->setFilePath($filename,$private));
		} else if ($file["image_type"] == "png") {
			$src = imagecreatefrompng($this->setFilePath($filename,$private));
		} else if ($file["image_type"] == "gif") {
			$src = imagecreatefromgif($this->setFilePath($filename,$private));
		}

		if (!isset($src) || $src === false) {
			error_log('FILE UPLOAD ERROR: updateDetails couldn\'t open image');
			return false;
		}

		foreach ($thumbsizes as $key => $value) {

			// split the size in to w & h
			$tmp = explode('x', $value);

			// if its a landscape image
			if ( $file["image_ratio"] >= 1 ) {
				$w = $tmp[0];
				$h = $tmp[1];
			// for portrait images, flip the values around
			} else {
				$w = $tmp[1];
				$h = $tmp[0];
			}

			// set some default crop values just to be safe
			$cx = 0;
			$cy = 0;
			$cw = $file["image_width"];
			$ch = $file["image_height"];

			// if we are doing the uncropped background
			if ($key == "ucbackground") {

				// make a new filename as this is being done regardless
				//$dstname = $this->globals->uuid();
				$dstname = $filename.'-'.$key;
				// if either side is too small
				if ($file["image_width"] < $w || $file["image_height"] < $h) {

					// figure out which side to resize by based on which ratio is smaller
					if ($file["image_width"] / $w < $file["image_height"] / $h) {

						// resize based on the width
						$scale = $w / $file["image_width"];

					} else {

						// resize based on the height
						$scale = $h / $file["image_height"];

					}

					// fix up the width and height
					$w = round($file["image_width"] * $scale);
					$h = round($file["image_height"] * $scale);
					
				// image is bigger than what is required, save as is!
				} else {
					$w = $cw;
					$h = $ch;
				}

			// if we're doing the magic post sized thumbnail (limited by width only)
			} else if ($w == "?" || $h == "?") {

				// if width is the unknown, fix it up
				if ($w == "?") {
					$w = $h;
				}

				// if the image is smaller than the width
				if ($file["image_width"] <= $w) {
					// just use the original file as there isn't much we can do
				} else {

					// make a new filename
					//$dstname = $this->globals->uuid();
					$dstname = $filename.'-'.$key;
					// scale down to the width here
					$scale = $w / $file["image_width"];

					// now fix up the target width and height using the scale
					$w = round($file["image_width"] * $scale);
					$h = round($file["image_height"] * $scale);

				}
		
			// square formats that require cropping also
			} else if ($w == $h) {
				$dstname = $filename.'-'.$key;
				// if the image is a valid width & height
				if ($file["image_width"] >= $w && $file["image_height"] >= $h) {

					// make a new filename
					//$dstname = $this->globals->uuid();
					$dstname = $filename.'-'.$key;
					// check which side to scale by
					// get the scale of the width and see if the height fits
					if ($file["image_height"] * ($w / $file["image_width"]) <= $h) {
						// landscape
						$scale = $h / $file["image_height"];

						$cy = 0;
						$ch = $file["image_height"];

						$cx = round(($file["image_width"] - ($w / $scale)) / 2);
						$cw = $file["image_height"];
					} else {
						// portrait
						$scale = $w / $file["image_width"];

						$cy = round(($file["image_height"] - ($h / $scale)) / 2);
						$ch = $file["image_width"];

						$cx = 0;
						$cw = $file["image_width"];
					}

				}

			// any other thumbnail size
			} else {

				if ($file["image_width"] >= $w || $file["image_height"] >= $h) {

					// make a new filename
					//$dstname = $this->globals->uuid();
					$dstname = $filename.'-'.$key;
					// get the scale of the width and see if the height fits
					if ($file["image_height"] * ($w / $file["image_width"]) <= $h) {
						$scale = $w / $file["image_width"];
					} else {
						$scale = $h / $file["image_height"];
					}

					// now fix up the target width and height using the scale
					$w = round($file["image_width"] * $scale);
					$h = round($file["image_height"] * $scale);
				
				//what if the original image is smaller than the desired size?
				} else {
					copy($this->setFilePath($filename,$private),$this->setFilePath($filename.'-'.$key,$private));
				}
			}

			// if we're all good to do our trickery
			if (isset($dstname) && $dstname != '') {
				//should we increase the limit in php.ini??? ini_set('memory_limit', '256M');
				// create the destination now we have the right width & height
				$dst = imagecreatetruecolor($w, $h);

				// copy the portion from the other image in to this one.
				$this->fastimagecopyresampled($dst, $src, 0, 0, $cx, $cy, $w, $h, $cw, $ch); 

				if(!empty($exif['Orientation'])) {
					switch($exif['Orientation']) {
						case 8:
							$dst = imagerotate($dst,90,0);
							break;
						case 3:
							$dst = imagerotate($dst,180,0);
							break;
						case 6:
							$dst = imagerotate($dst,-90,0);
							break;
						default:
							break;
					}
				}
				imagejpeg($dst, $this->setFilePath($dstname,$private), 87);

				// Keep one piece of EXIF data from the original: orientation. This gets orientation for mobile-orig images
				// right for firefox and mobile safari but not anything else...
				//$cmd = '/usr/bin/exiftool -tagsfromfile '. $this->getFilePath($filename,$private) . ' -orientation ' . $this->getFilePath($dstname,$private) ;
				//$tmp = `$cmd`;
				//file_put_contents('/home/tech/DATABASE/chk',$cmd. ' '.$tmp);

				//alternatively, read the orientation from the orignal and apply it to each and every copy...(doing that just above)

				// background needs a cropped area, based off the ucbackground image
				if ($key == "ucbackground") {

					// make a new filename
					//$dstname = $this->globals->uuid();
					$dstname = $filename.'-background';
					$dst2 = imagecreatetruecolor(1920, 971);

					// copy the centre portion from the other image in to this one.

						// if the source is bigger than 1920x1200
						if ($w > 1920 && $h > 971) {

							// figure out which side to resize by based on which ratio is smaller
							if ($w / 1920 < $h / 971) {

								// resize based on the width
								$scale = 1920 / $w;

							} else {

								// resize based on the height
								$scale = 971 / $h;

							}

							// fix up the width and height
							$nw = round($w * $scale);
							$nh = round($h * $scale);

							// create the new shrunk image
							$dstmp = imagecreatetruecolor($nw, $nh);
							$this->fastimagecopyresampled($dstmp, $dst, 0, 0, 0, 0, $nw, $nh, $w, $h); 

							//imagejpeg($dstmp, "./assets/files/debug.jpg", 75);

							// override the original
							$dst = $dstmp;

							// free up some memory
							unset($dstmp);
						} else {
							$nw = $w;
							$nh = $h;
							$scale = 1;
						}

						$x = 0 - round(($nw - 1920) / 2);
						$y = 0 - round(($nh - 971) / 2);
						$this->fastimagecopyresampled($dst2, $dst, $x, $y, 0, 0, $nw, $nh, $nw, $nh); 

						imagejpeg($dst2, $this->setFilePath($dstname,$private), 75);

						if (file_exists($this->setFilePath($dstname,$private))) {
							// store the new display parameters
							$this->db->update('Content', [
								'bgParams' => json_encode([
									'left' => $x,
									'top' => $y,
									'size' => round($scale * 100, 2),
									'flipH' => false,
									'flipV' => false
								])
							], ['fileName' => $filename]);
						}

						// mobile needs a cropped area, based off the background image
						//$this->createMobileBackground($dst2, $filename, $private);

						// create a thumbnail from the background image
						//$this->createBackgroundThumbnail($dst2, $filename, $private);

					// destroy the memory data
					imagedestroy($dst2);

				}

				// destroy the memory data
				imagedestroy($dst);

			}

			// clear the filename for the next round
			unset($dstname);

		}

		// One last resample to get the original rotated
		if (!empty($exif['Orientation'])) {
			switch($exif['Orientation']) {
				case 8:
					$src = imagerotate($src,90,0);
					break;
				case 3:
					$src = imagerotate($src,180,0);
					break;
				case 6:
					$src = imagerotate($src,-90,0);
					break;
				default:
					break;
			}
		}
		imagejpeg($src, $this->setFilePath($filename.'-orig',$private), 87);

		// remove the image as we don't need it no more.
		imagedestroy($src);
		
	}
	function getContent($type, $subjectId) {

		$this->db->select('Content.fileName')
			->select('Content.fileType')
			->select('Content.userId')
			->select('Subject.private')	
			->from('Content')
			->where('Content.fileType', $type)
			->where('Content.subjectId !=', '0')
			->where('Content.subjectId', $subjectId)	
			->join('Subject', 'Content.subjectId = Subject.subjectId', 'LEFT')
			->order_by('timestamp', 'DESC');
		$query = $this->db->get();
		
		if ($query->num_rows() > 0) {
			return $query->result_array();
		}
		return [];
	}

}
?>
