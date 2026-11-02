<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

use Ramblur\Auth\IPNetworkAuthenticator;
use Ramblur\Network\ClientIPAddress;

class Api extends CI_Controller {

	/*
	 * Single endpoint for all API requests
	 */

	public function index() {
		
		// start with a blank result
		$result = array();

		// store the required vars
		$key = $this->input->post('key');
		$primary = $this->input->post('primary');
		$secondary = $this->input->post('secondary');
		$data = json_decode($this->input->post('data'), true);
		
		Logger::getLogger()->addDebug("API data provided was: ",[$key,$primary,$secondary,$data]);
		
		// make sure the minimum required data was provided
		if (!($key && $primary && $secondary)
			|| !(mb_strlen($key) > 0 && mb_strlen($primary) > 0 && mb_strlen($secondary) > 0)
			|| !isset($this->requests[$primary])
			|| !isset($this->requests[$primary][$secondary])
		) {
			$this->error('Primary:' . $primary . ' and secondary:' . $secondary . ' pair is invalid');
			return false;
		}
		// load commonly used classes
		$this->load->model('globals');
		$this->load->model('node');
		$this->load->model('group');
		$this->load->model('user');
		$this->load->model('post');
		$this->load->model('category');
		$this->load->model('ad');
		$this->load->model('file');
		$this->load->model('search');
		$this->load->model('message');
		$this->load->model('library');
		$this->load->model('subject');
		$this->load->model('subjectAttribute');
		$this->load->helper(array('ramblurfiltervar'));
		$this->load->helper(array('ramblurfilenamevalidate'));
		$this->load->helper(array('colourvalidate'));
		
		// Get the rules specific for this request
		$rules = $this->requests[$primary][$secondary];
		
		// Check authentication if required
		if ($rules['authN']) {
			if (!$this->user->authCheck()) {
				// Send back 401 - unauthorised 
				$this->output->set_status_header(401);
				// WWW-authenticate header required with 401 code, but the authentication protocol we use
				// isn't recognised by any standard, so just fake one
				$this->output->set_header('WWW-Authenticate: ramblurauth');
				return false;
			}
		}
		
		// Check authorization if required
		/* if ($rules['authZ']) {
			// check using yet to be built authorization layer
			// If it fails, send back 403 - forbidden
		} */
		
		// Run the function specific to this request
		$functionName = $primary.'Request';
		$result['json'] = $this->$functionName($secondary, $data);
		
		// If something was returned
		if ($result["json"] !== false) {
		
			/**
			 *  Kludge alert:  Hashing a print_r of the data instead of json_encode as json_encode returned slight variations
			 *  in the data string => different hashes and unnecessary refreshes of threads.  mn 20150709
			 *  Also use a reg exp to remove the age value from the hash input, age obviously changes all the time :)
			 */
		
			// work out the hash of the data
			$tohash = print_r($result["json"],true);
		
			$searchPattern = '/\[age\] => [0-9]{1,}/';
			$replace = "";
			$tohash = preg_replace($searchPattern, $replace, $tohash);
			$hash = md5($tohash);
		
			// if the client is only looking for the hash
			if (isset($data) && isset($data["hash"]) && $data["hash"] != "") {
		
				// empty the result as they don't need it
				$result["json"] = array();
			}
		
			if(is_array($data)) {
				$keys = array_keys($data);
				$returns = array();
				for ( $i = 0; $i < count($keys); $i++ ) {
					if ($keys[$i] == "hash" || mb_substr($keys[$i], -2, 2) == "Id") {
						$returns[$keys[$i]] = $data[$keys[$i]];
					}
				}
		
				if (count($returns) > 0 && is_array($result["json"])) {
					// Cannot use a scalar value as an array
					$result["json"]["data"] = $returns;
					$result["json"]["hash"] = $hash;
				}
			}
		
		} else {
			$result["json"] = array();
		}
		
		// If there was an error, the response has been handled already.
		if (!$this->hasError) {
			$this->load->view('api', $result);
		}
	}
	
	private $hasError = false;
	
	/**
	 * Sets an error message to return as the response
	 */
	public function error($msg = "Unknown API error.") {
		$this->hasError = true;
		Logger::getLogger()->addAlert("API Error", [$msg]);
		$this->load->view('api', array("json" => array("error" => $msg)));
	}
	
	private function userRequest($secondary, $data) {
		$returnData = false;
		
		// attempt to create a user
		if ($secondary == "create" ) {// input data html_purified, date validated in User.php, default Prmissions (lowest level) used
			if( !isset($data) || !is_array($data) || rfilter_var($data['email'],FILTER_VALIDATE_EMAIL)===false){
				$this->error('Your email address did not pass validation');
				return false;
			}
			$returnData = $this->user->create($data);
		
		} else if ($secondary == "getSocialFollowing") {//returns zero when not logged in
		
			$returnData = $this->user->getSocialFollowing();
		
		} else if ($secondary == "following") {//returns null if not logged in
		
			$returnData = $this->user->following();
		
		} else if ($secondary == "getSubjectColor") {//not logged in returns false and subjetcId==0 returns false
			if(!isset($data["subjectId"]) || rfilter_var($data["subjectId"],FILTER_VALIDATE_INT,false)===false) {
				$this->error('Valid subjectId must be supplied');
				return false;
			}
			$returnData = $this->user->getSubjectColor($data);
		
		} else if ($secondary === 'isUnderage') {
		
			$userId = $this->user->authCheck();
			if ($userId) {
				$underage = $this->user->isUnder18($userId);
			} else {
				$underage = false;
			}
			$returnData = ['isUnderage' => $underage];
			
		} else if ($secondary === 'setForumMode') {
			$userId = $this->user->authCheck();
			if (!$userId) {
				return false;
			}
			if (isset($data["forumMode"]) && !preg_match('/^[a-zA-Z]{1,}$/',$data["forumMode"])) {
				$this->error('setForumMode: Valid string must be supplied for forumMode');
				return false;
			}
			if (isset($data['copyOnly']) && rfilter_var($data["copyOnly"], FILTER_VALIDATE_BOOLEAN)===false) {
				$this->error('setForumMode: copyOnly must be a valid boolean');
				return false;
			}
			$returnData = $this->user->setForumMode($userId, $data["forumMode"], $data["copyOnly"]);
		
			} else if ($secondary === 'getWallpaperModeSettings') {
			$userId = $this->user->authCheck();
			if (!$userId) {
				return false;
			}
			if (!isset($data["subjectId"]) || rfilter_var($data["subjectId"],FILTER_VALIDATE_INT,true)===false) {
				$this->error('getWallpaperModeSettings: Valid subjectId must be supplied');
				return false;
			}
			$returnData = $this->user->getWallpaperModeSettings($userId, $data["subjectId"]);
			
		} else if ($secondary == "countryDD") {//just gets data from Country table
		
			$returnData = $this->user->countryDD();
		
		} else if ($secondary == "getStatesByCountry") { //returns false if countryId is not valid
			if (!isset($data["countryId"]) || !preg_match('/^[A-Z]{2}$/',$data["countryId"])) {
				$this->error('Valid countryId must be supplied');
				return false;
			}
			$returnData = $this->user->getStatesByCountry($data);
		
		} else if ($secondary == "getSuburbsByPostcode") {//relying on api.geonames.org to validate postCode and return null if invalid
		
			$returnData = $this->user->getSuburbsByPostcode($data);
		
			// attempt to log on a user
		} else if ($secondary == "auth") {
		
			if (!(isset($data['identity']) && isset($data['password']))) {
				$this->error('Username/email and password must be supplied.');
				return false;
			}
			if (isset($data['rememberMe']) && rfilter_var($data["rememberMe"], FILTER_VALIDATE_BOOLEAN)===false) {
				$this->error('Invalid data.');
				return false;
			}
			
			$identity = $data["identity"];
			$password = $data["password"];
		
			$error = false;
			$returnData = ['success' => $this->user->auth($identity, $password, $error, isset($data['rememberMe']) ? $data['rememberMe'] : false), 'message' => $error];
		
		} else if ($secondary == "socialAuth") {// relies on hybridauthlib
		
			$this->load->library('HybridAuthLib');
		
			// call Hybrid_Auth::getSessionData() to get stored data
			$hybridauth_session_data = $this->hybridauthlib->getSessionData();
			if (isset($hybridauth_session_data) && $hybridauth_session_data!== null) {
				$error = false;
				$returnData = ['success' => $this->user->socialAuth($error), 'message' => $error];
			} else {
				$this->error('Login failed. Please try again.');
				return;
			}
		
		// check if a user is logged in and the session is valid
		} else if ($secondary == "checkAuth") {
			$userId = $this->user->authCheck();
			$returnData = $userId ? ['authorised' => true, 'id' => $userId] : ['authorised' => false, 'id' => null];
		
			// fetch some account info
		} else if ($secondary == "info") {//NB do not call user->info with an unchecked, user provided value for the first parameter
		
			if( isset($data["direct"]) && rfilter_var($data["direct"],FILTER_VALIDATE_BOOLEAN)===false){
				$this->error('Valid boolean must be supplied for param "direct"');
				return false;
			}
		
			if(isset($data['direct']) && $data['direct']) {
				$returnData = $this->user->info(false, true);
			} else {
				$returnData = $this->user->info();
			}
		
			// fetch some profile info about a specific user
		} else if ($secondary == "profile") {//$data['user']===0 not allowed
			if (isset($data["forumPostId"])&& rfilter_var($data["forumPostId"],FILTER_VALIDATE_INT,false)===false) {
				$this->error('Valid forumPostId was not supplied');
				return false;
			}
			if (!isset($data["user"]) || rfilter_var($data["user"],FILTER_VALIDATE_INT,false)===false
			){
				$this->error('Valid user must be supplied');
				return false;
			}
			$returnData = $this->user->profile($data);
		
		} else if ($secondary == "profileUpdate") {//NB added check of logged in user against supplied data[id] to user->profileUpdate
			// also added whitelist of permissible column updates
		
			if(!isset($data["id"])
				|| rfilter_var($data["id"],FILTER_VALIDATE_INT,false)===false
				|| (isset($data["stateId"]) && rfilter_var($data["stateId"],FILTER_VALIDATE_INT,true)===false)
			){
				$this->error('Valid id and stateId must be supplied');
				return false;
			}
			$returnData = $this->user->profileUpdate($data);
		
		} else if ($secondary == "settingsProfileUpdate") {
			if (!isset($data) || !is_array($data) || (isset($data['email']) && rfilter_var($data['email'],FILTER_VALIDATE_EMAIL)===false)) {
				$this->error('Your email address did not pass validation');
				return false;
			}
			$returnData = $this->user->settingsProfileUpdate($data);
		
		// fetch some groups
		} else if ($secondary == "groupsQuickCount") {
		
			$returnData = $this->user->groupsQuickCount();
		
		} else if ($secondary == "groups") {// Not sure if this needs to return a random selection if not logged in anymore?
			if (isset($data['includeHiddenGroups']) && rfilter_var($data['includeHiddenGroups'],FILTER_VALIDATE_BOOLEAN) === false) {
				$this->error('Valid includeHiddenGroups must be supplied.');
				return;
			}
			$returnData = $this->user->groups(isset($data['includeHiddenGroups']) ? $data['includeHiddenGroups'] : false);
		
		// fetch some groups - mobile version
		} else if ( $secondary == "groups2" ) {//groups2 no longer accepts a userId as an argument, always checks the cookie.
		
			$returnData = $this->user->groups2();
		
		// fetch the users active conversations
		} else if ($secondary == "buttons") { //Now makes no difference if $data["hash'] is set or not
				
			$returnData = $this->user->buttons();
		
		// fetch a specific active conversation
		} else if ($secondary == "button") {
		
			if( !isset($data["id"]) || rfilter_var($data["id"],FILTER_VALIDATE_INT,false)===false){
				$this->error('Valid id must be supplied');
				return false;
			}
			if(!isset($data["type"]) || $data["type"]!=='thread'){//Not very future proof whitelisting only one string
				$this->error('Valid type must be supplied');
				return false;
			}
			$returnData = $this->user->button($data);
		
		} else if ($secondary == "getThreadButtonIds") {
				
			$returnData = $this->user->getThreadButtonIds();
		
		} else if ($secondary == "getUnreadMessagesCount") {
		
			$returnData = $this->user->getUnreadMessagesCount();
		
		} else if ($secondary == "profileDetails") {//added an early exit if authCheck fails
		
			$returnData = $this->user->profileDetails();
		
		} else if ($secondary == "getSearchChoice") {//Added early return if authCheck returns false
		
			$returnData = $this->user->getSearchChoice();
		
		} else if ($secondary == "setSearchChoice") {//$data['forum'] is white listed in User.php, added early return if authcheck returns false, action is white listed in User.php
		
			$returnData = $this->user->setSearchChoice($data);
		
		} else if ($secondary == "featuredGroups") {// returns false if authCheck fails
			if (!isset($data["fgId"]) || rfilter_var($data["fgId"],FILTER_VALIDATE_INT,false)===false) {
				$this->error('Valid fgId must be supplied');
				return false;
			}
			$returnData = $this->user->featuredGroups($data);
		
		} else if ($secondary == "searchChoice") {
			if (!isset($data["searchChoice"]) || rfilter_var($data["searchChoice"],FILTER_VALIDATE_INT,true,0,15)===false) {
				$this->error('Valid searchChoice must be supplied');
				return false;
			}
			$returnData = $this->user->storeSearchChoice($data["searchChoice"]);
		
		} else if ($secondary == "getBasicDetails") {// turned off blockCheck==false code for now, potential leak of user details
			if (!isset($data["blockCheck"]) || rfilter_var($data["blockCheck"],FILTER_VALIDATE_BOOLEAN)===false) {
				$this->error('Valid boolean must be supplied as parameter "blockCheck".');
				return false;
			}
			if (!isset($data["user"]) || rfilter_var($data["user"],FILTER_VALIDATE_INT,false)===false) {
				$this->error('Valid user must be supplied');
				return false;
			}
			$returnData = $this->user->getBasicDetails($data);
		
		} else if ($secondary == "isForumSubscribed") {
			if (!isset($data["subjectId"]) || rfilter_var($data["subjectId"],FILTER_VALIDATE_INT,true)===false) {
				$this->error('Valid subjectId must be supplied');
				return false;
			}
			$returnData = $this->user->isForumSubscribed($data['subjectId']);
		
		} else if ($secondary == "updateForumSettingsDevice") {
			if (!isset($data["pricolfont"]) || rfilter_var($data["pricolfont"],FILTER_VALIDATE_INT,true)===false) {
				$this->error('Valid pricolfont must be supplied');
				return false;
			}
			if (!isset($data["seccolfont"]) || rfilter_var($data["seccolfont"],FILTER_VALIDATE_INT,true)===false) {
				$this->error('Valid seccolfont must be supplied');
				return false;
			}
			if (!isset($data["forum"]) || rfilter_var($data["forum"],FILTER_VALIDATE_INT,true)===false) {
				$this->error('Valid forum must be supplied');
				return false;
			}
			$returnData = $this->user->updateForumSettingsDevice($data);
		
		} else if ($secondary == "addMapMarker") {//added early return if authcheck returns false
			$this->load->helper(array('ramblurfiltervar'));
			if (isset($data["popupId"]) && !(rfilter_var($data["popupId"],FILTER_VALIDATE_INT,true)===true || rfilter_var($data["popupId"],FILTER_VALIDATE_BOOLEAN)===true)) {
				$this->error('Valid popupId must be supplied');
				return false;
			}
			if (!isset($data["subjectId"]) || rfilter_var($data["subjectId"],FILTER_VALIDATE_INT,false)===false) {
				$this->error('Valid subjectId must be supplied');
				return false;
			}
			if (!isset($data["nodeId"]) || rfilter_var($data["nodeId"],FILTER_VALIDATE_INT,false)===false) {
				$this->error('Valid nodeId must be supplied');
				return false;
			}
		
			$returnData = $this->user->addMapMarker($data);
		
		} else if ($secondary == "getMapMarkers") {
			if (!isset($data["subjectId"]) || rfilter_var($data["subjectId"],FILTER_VALIDATE_INT,false)===false) {
				$this->error('Valid subjectId must be supplied');
				return false;
			}
			$returnData = $this->user->getMapMarkers($data['subjectId']);
		
		} else if ($secondary === 'removeFromForum') {
			if (!(isset($data['group']) && isset($data['userId']))
				|| rfilter_var($data["userId"],FILTER_VALIDATE_INT,false)===false
				|| rfilter_var($data["group"],FILTER_VALIDATE_INT,false)===false
			) {
				$this->error('Invalid data.');
				return;
			}
			$returnData = $this->group->removeUserAccess($data['group'], $data['userId']);
		
		} else if ($secondary == "getBackgroundOpacity") {//added immediate return of NULL if authCheck fails
			if (!isset($data["userId"]) || rfilter_var($data["userId"],FILTER_VALIDATE_INT,false)===false) {
				$this->error('Valid userId must be supplied');
				return false;
			}
			$returnData = $this->user->getBackgroundOpacity($data);
				
		} else if ($secondary == "setBackgroundOpacity") {//added immediate return of false if authCheck fails
			if (!isset($data["val"]) || rfilter_var($data["val"],FILTER_VALIDATE_FLOAT,true)===false) {
				$this->error('Valid opacity must be supplied');
				return false;
			}
			$returnData = $this->user->setBackgroundOpacity($data);
		
		} else if ($secondary == "submitDeviceErrorReport") {//summary and description htmlpurified in User.php
		
			$returnData = $this->user->submitDeviceErrorReport($data);
		
		} else if ($secondary == "invite") {// emails is a more complicated data structure
			// emails must be set and must be an array, and a message must be set
			if (!isset($data['emails']) || !is_array($data['emails']) || !isset($data['message'])) {
				$this->error('invalid friend invite data');
				return;
			}
			$returnData = ['success' => $this->user->sendFriendInvites($data['emails'], $data['message'], $data["fromName"], $data["fromEmail"])];
		//Note this api call is not currently used but dealt with directlyh in post.php	
		} else if ($secondary == "sendNotificationEmail") {
			if (!isset($data["action"])) {
				$this->error('sendNotificationEmail() : action must be supplied');
				return false;
			}
			if (!isset($data['userId']) || rfilter_var($data["userId"],FILTER_VALIDATE_INT,false)===false) {
				$this->error('sendNotificationEmail() : Valid userId must be supplied');
				return false;
			}
			if (!isset($data["postId"]) || rfilter_var($data["postId"],FILTER_VALIDATE_INT,false)===false) {
				$this->error('sendNotificationEmail() : Valid postId must be supplied');
				return false;
			}
			if (!isset($data["nodeId"]) || rfilter_var($data["nodeId"],FILTER_VALIDATE_INT,false)===false) {
				$this->error('sendNotificationEmail() : Valid nodeId must be supplied');
				return false;
			}
			if (!isset($data["subjectId"]) || rfilter_var($data["subjectId"],FILTER_VALIDATE_INT,false)===false) {
				$this->error('sendNotificationEmail() : Valid subjectId must be supplied');
				return false;
			}
			if (!isset($data["subjectName"])) {
				$this->error('sendNotificationEmail() : subjectName must be supplied');
				return false;
			}
			$this->load->model("email");
			if($data["action"] === "reply") {
				$returnData = ['success' => $this->email->sendReplyNotificationEmail($data['userId'], $data["postId"], $data["nodeId"], $data["subjectId"], $data["subjectName"])];	
			} 
		} else if ($secondary === "updateHelpBubbleStatus") {
			if (!(isset($data['userId']) && isset($data['helpBubblesShown']))
				|| rfilter_var($data["userId"],FILTER_VALIDATE_INT,false) === false
				|| rfilter_var($data["helpBubblesShown"],FILTER_VALIDATE_BOOLEAN) === false
			) {
				$this->error('Invalid data.');
				return;
			}
			$returnData = $this->user->updateHelpBubbleStatus($data['userId'], $data['helpBubblesShown']);
						
		} else if ($secondary === "getUserForumIdsAndNames") {
			if (!isset($data['userId']) || rfilter_var($data["userId"],FILTER_VALIDATE_INT,false)===false) {
				$this->error('Invalid data.');
				return;
			}
			$returnData = $this->user->getUserForumIdsAndNames($data['userId']);
		} else if ($secondary == "setWideView") {
			if (!isset($data["userId"]) || rfilter_var($data["userId"],FILTER_VALIDATE_INT,false)===false) {
				$this->error('setWideView: Valid userId must be supplied');
				return false;
			}
			if (isset($data["id"]) && rfilter_var($data["id"],FILTER_VALIDATE_BOOLEAN)===false) {
				$this->error('setWideView: valid id must be supplied');
				return false;
			}
			$returnData = $this->user->setWideView($data["userId"], $data["id"]);
		} else if ($secondary == "isUserInWideView") {
			if (!isset($data["userId"]) || rfilter_var($data["userId"],FILTER_VALIDATE_INT,false)===false) {
				$this->error('isUserInWideView: Valid userId must be supplied');
				return false;
			}
			$returnData = array("inWideView" => $this->user->isUserInWideView($data["userId"]));
		} else if ($secondary == "getTotalNotificationCount") {
			if (!isset($data["userId"]) || rfilter_var($data["userId"],FILTER_VALIDATE_INT,false)===false) {
				$this->error('getTotalNotificationCount: Valid userId must be supplied');
				return false;
			}
			$returnData = array("unreadThreadCount" => $this->user->getTotalNotificationCount($data["userId"]));
		}
		return $returnData;
	}
		
	private function subjectRequest($secondary, $data) {
		$returnData = false;
		
		if ($secondary == "follow_subject2") {//added return of null on auth fail
			if (!isset($data["subjectId"])
				|| rfilter_var($data["subjectId"],FILTER_VALIDATE_INT,true) === false
				|| (isset($data["groupWithId"]) && rfilter_var($data["groupWithId"],FILTER_VALIDATE_INT,true) === false)
			){
				$this->error('Valid subjectId and groupWithId must be supplied');
				return false;
			}
			$returnData = $this->subject->follow_subject2($data);
		
		} else if ($secondary == "group_subjects2") {//added return of null on auth fail
			if (!isset($data["groupWithId"]) || rfilter_var($data["groupWithId"],FILTER_VALIDATE_INT,false) === false) {
				$this->error('Valid groupWithId must be supplied');
				return false;
			}
			$returnData = $this->subject->group_subjects2($data);
		
		} else if ($secondary == "getSubjectGroups") {
			if (!isset($data["subjectId"]) || rfilter_var($data["subjectId"],FILTER_VALIDATE_INT,false)===false) {
				$this->error('Valid subjectId must be supplied');
				return false;
			}
			$returnData = $this->subject->getSubjectGroups($data);
		
		} else if($secondary == "groupSubjectBranch") {//early return if auth fail
			if (!isset($data["subjectId"]) || rfilter_var($data["subjectId"],FILTER_VALIDATE_INT,false)===false) {
				$this->error('Valid subjectId must be supplied');
				return false;
			}
			$returnData = $this->subject->groupSubjectBranch($data);
		
		} else if ($secondary == "getBackgroundImage") {
			if (!isset($data["subjectId"]) || rfilter_var($data["subjectId"],FILTER_VALIDATE_INT,false)===false) {
				$this->error('Valid subjectId must be supplied');
				return false;
			}
		
			$returnData = $this->subject->getBackgroundImage($data);
		
		} else if ($secondary == "removeGroup") {
			if (!isset($data["subjectId"]) || rfilter_var($data["subjectId"],FILTER_VALIDATE_INT,false)===false) {
				$this->error('Valid subjectId must be supplied');
				return false;
			}
			if (isset($data["fgListAction"]) && rfilter_var($data["fgListAction"],FILTER_VALIDATE_INT,true)===false) {
				$this->error('Valid fgListAction must be supplied');
				return false;
			}
			$returnData = $this->subject->removeGroup($data);
			
		} else if ($secondary == "isBannedFromForum") {
			if (!isset($data['userId']) || rfilter_var($data["userId"],FILTER_VALIDATE_INT,false)===false) {
				$this->error('isBannedFromForum() : Valid userId must be supplied');
				return;
			}
			if (!isset($data["subjectId"]) || rfilter_var($data["subjectId"],FILTER_VALIDATE_INT,false)===false) {
				$this->error('isBannedFromForum() : Valid subjectId must be supplied');
				return false;
			}
			$returnData = $this->subject->isBannedFromForum($data["userId"], $data["subjectId"]);
		}
		
		return $returnData;
	}
	
	private function nodeRequest($secondary, $data) {
		$returnData = false;
		
		// follow a group/user
		if ($secondary == "follow") { // groupId checked in Node.php
			if (!isset($data["socialId"]) || rfilter_var($data["socialId"],FILTER_VALIDATE_INT,false)===false) {
				$this->error('Valid socialId must be supplied');
				return false;
			}
		
			$returnData = $this->node->follow($data['socialId'], $data['groupId']);
		
		// follow a group/user
		} else if ($secondary == "follow2") {//action white listed, not used in a query anyway
			if (!isset($data["socialId"])
				|| !isset($data["groupId"])
				|| rfilter_var($data["socialId"],FILTER_VALIDATE_INT,true)===false
				|| rfilter_var($data["groupId"],FILTER_VALIDATE_INT,true)===false
			){
				$this->error('Valid socialId and groupId must be supplied');
				return false;
			}
			$returnData = $this->node->follow2($data);
		
		// like a node
		} else if ($secondary == "like") {
			if (isset($data["liked"]) && rfilter_var($data["liked"],FILTER_VALIDATE_BOOLEAN)===false) {
				$this->error('Something like a boolean must be supplied for param "liked"');
				return false;
			}
			if (!isset($data["nodeId"]) || rfilter_var($data["nodeId"],FILTER_VALIDATE_INT,false)===false) {
				$this->error('Valid nodeId must be supplied');
				return false;
			}
			$returnData = $this->node->like($data);
		
		// tick a node
		} else if ($secondary == "tick") {
			if (!isset($data["ticked"]) || rfilter_var($data["ticked"],FILTER_VALIDATE_BOOLEAN)===false) {
				$this->error('Valid boolean must be supplied for param "ticked"');
				return false;
			}
			if (!isset($data["nodeId"]) || rfilter_var($data["nodeId"],FILTER_VALIDATE_INT,false)===false) {
				$this->error('Valid nodeId must be supplied');
				return false;
			}
			$returnData = $this->node->tick($data);
		
		}else if ($secondary == "updateNode") {
			if (!isset($data["postId"]) || rfilter_var($data["postId"],FILTER_VALIDATE_INT,false)===false) {
				$this->error('Valid postId must be supplied to updateNode');
				return false;
			}
			if (isset($data["category"])
				&& !empty($data["category"])
				&& $data["category"] != 'undefined'
				&& $data["category"] !== 'null'
				&& rfilter_var($data["category"],FILTER_VALIDATE_INT,true)===false
			){
				$this->error('Valid category must be supplied to updateNode');
				return false;
			}
			if (!isset($data["nodeId"]) || rfilter_var($data["nodeId"],FILTER_VALIDATE_INT,false)===false) {
				$this->error('Valid nodeId must be supplied');
				return false;
			}
			$returnData = $this->node->updateNode($data);
		
		}else if ($secondary == "info") {
			if (!isset($data["nodeId"]) || rfilter_var($data["nodeId"],FILTER_VALIDATE_INT,false)===false) {
				$this->error('Valid nodeId must be supplied');
				return false;
			}
			$returnData = $this->node->info($data["nodeId"]);
		
		}else if ($secondary == "setVisibility") {
			if (!isset($data["visibility"]) || rfilter_var($data["visibility"],FILTER_VALIDATE_BOOLEAN)===false) {
				$this->error('Valid boolean must be supplied for param "visibility"');
				return false;
			}
			if (!isset($data["nodeId"]) || rfilter_var($data["nodeId"],FILTER_VALIDATE_INT,false)===false) {
				$this->error('Valid nodeId must be supplied');
				return false;
			}
			$returnData = $this->node->setVisibility($data);
		
		// subscribe to a node
		} else if ($secondary == "subscribe") {
			if (!isset($data["postId"]) || rfilter_var($data["postId"],FILTER_VALIDATE_INT,false)===false) {
				$this->error('Valid postId must be supplied to subscribe');
				return false;
			}
			$returnData = $this->node->subscribe($data);
		
		// unsubscribe from a node
		} else if ($secondary == "unsubscribe") {
			if (!isset($data["postId"]) || rfilter_var($data["postId"],FILTER_VALIDATE_INT,false)===false) {
				$this->error('Valid postId must be supplied to unsubscribe');
				return false;
			}
			$returnData = $this->node->unsubscribe($data);
		
		} else if ($secondary == "shortUserInfo") {// group validated in Node.php
		
			$returnData = $this->node->shortUserInfo($data['group']);
		
		} else if ($secondary == "toggleNode") {
			if (!isset($data["nodeId"]) || rfilter_var($data["nodeId"],FILTER_VALIDATE_INT,false)===false) {
				$this->error('Valid nodeId must be supplied');
				return false;
			}
			if (!isset($data["active"]) || rfilter_var($data["active"],FILTER_VALIDATE_BOOLEAN)===false) {
				$this->error('Valid boolean must be supplied for param "active"');
				return false;
			}
			$returnData = $this->node->toggleNode($data['nodeId'], $data['active']);
			
		// admin can mark a node as read
		} else if ($secondary == "adminToggleNodeMark"){
			if(!$userId = $this->user->authCheck()){
				$this->error("Authentication required");
				return false;
			}
			if(!$this->user->adminCheck()){
				$this->error("Admin privileges rquired");
				return false;
			}
			if (!isset($data["nodeId"]) || rfilter_var($data["nodeId"],FILTER_VALIDATE_INT,false)===false) {
				$this->error('Valid nodeId must be supplied');
				return false;
			}
			$this->load->model("adminTasks");			
			$returnData = $this->adminTasks->toggleNodeMarked((int)$data["nodeId"],(int)$userId) ;
		}
		
		return $returnData;
	}
	
	private function messageRequest($secondary, $data) {
		$returnData = false;
	
		// send a message
		if ($secondary == "create") {//data['group'] validated, body and title htmlpurified in Message.php
			if (!isset($data["to"])
				|| !isset($data['title'])
				|| !isset($data['body'])
				|| rfilter_var($data["to"],FILTER_VALIDATE_INT,false)===false
			) {
				$this->error('Valid value of "to" must be supplied');
				return false;
			}
			$returnData = $this->message->create($data);
		
		// get list of social friends - added cjt
		} else if ($secondary === "getSocialNames") {
				
			$returnData = $this->message->getSocialNames();
		
		// insert a reply to a message - added cjt
		} else if ($secondary === "reply") {//message htmlpurified in Message.php
			if (!isset($data["parentNodeId"]) || rfilter_var($data["parentNodeId"],FILTER_VALIDATE_INT,true)===false) {
				$this->error('Valid parentNodeId must be supplied');
				return false;
			}
			if (!isset($data["postId"]) || rfilter_var($data["postId"],FILTER_VALIDATE_INT,false)===false) {
				$this->error('Valid postId must be supplied to reply');
				return false;
			}
			$returnData = $this->message->reply($data);
		
		// update a message thread status - added cjt
		} else if ($secondary === "action") {//action and usrOwn white listed in Message.php
			if (!isset($data["postId"]) || rfilter_var($data["postId"],FILTER_VALIDATE_INT,false)===false) {
				$this->error('message->action(): Valid postId(s) must be supplied');
				return false;
			}
			if (isset($data["rcptUserId"]) && rfilter_var($data["rcptUserId"],FILTER_VALIDATE_INT,false)===false) {
				$this->error('message->action(): Valid rcptUserId must be supplied if sent');
				return false;
			}
			$returnData = $this->message->action($data);
		
		// fetch a list of messages for a user
		} else if ($secondary == "messages") {
			if (!isset($data["status"])) {// data[action] is white listed in Messages.php, added early exit if authCheck fails to User.php
				$this->error("status must be provided so the right messages can be retrieved. eg. '0' for unread messages.");
			} else {
				$returnData = array('messages' => $this->message->messages($data['status'], isset($data['count']) ? $data['count'] : false, isset($data['isDevice']) ? true : false));
			}
		}
		return $returnData;
	}
	
	private function groupRequest($secondary, $data) {
		$returnData = false;
	
		if ($secondary == "create") {//forum name validated in and relatedTo checked where appropriate in Group.php
			$this->load->helper(array('ramblurfilenamevalidate'));
			/* if (isset($data['data']["memberBackgrounds"]) && !rfname_validate($data['data']['memberBackgrounds'])) {
				$this->error('Valid file names must be supplied');
				return false;
			} */
			/* if (isset($data['data']["thirdPartyBackgrounds"]) && !rfname_validate($data['data']['thirdPartyBackgrounds'])) {
				$this->error('Valid file names must be supplied');
				return false;
			} */
			/* if (!isset($data['data']["private"]) || rfilter_var($data['data']["private"],FILTER_VALIDATE_BOOLEAN)===false) {
				$this->error('Valid boolean must be supplied for param "private"');
				return false;
			} */
			if (isset($data['data']["adsEnabled"]) && rfilter_var($data['data']["adsEnabled"],FILTER_VALIDATE_BOOLEAN)===false) {
				$this->error('Valid boolean must be supplied for param "adsEnabled"');
				return false;
			}
			if (isset($data['data']["ageBlock"]) && rfilter_var($data['data']["ageBlock"],FILTER_VALIDATE_BOOLEAN)===false) {
				$this->error('Valid boolean must be supplied for param "ageBlock"');
				return false;
			}
			if (isset($data['data']["opacity"]) && rfilter_var($data['data']["opacity"],FILTER_VALIDATE_FLOAT,false)===false) {
				$this->error('Valid float must be supplied for param "opacity"');
				return false;
			}
			if (!isset($data['data']["type"])) {
				$this->error('Valid value of "type" must be supplied');
				return false;
			}
			if (!isset($data['data']["name"])) {
				$this->error('forum name must be supplied');
				return false;
			}
			// Want all or nothing so use transaction
			$this->db->trans_begin();
			$error = FALSE;
			$created = $this->group->create($data['data'], true, $error);
		
			// Check everything was successful
			if($created && $this->db->trans_status() !== FALSE && $error === FALSE) {
				$this->db->trans_commit();
				$returnData = ['forum' => ['id' => $created]];
			} else {
				$this->db->trans_rollback();
				if($error) {
					$this->error($error);
				} else {
					$this->error('The forum was not created.');
				}
			}
		} else if ($secondary == "update") {
		
			if (isset($data['data']["memberBackgrounds"]) && !rfname_validate($data['data']['memberBackgrounds'])) {
				$this->error('Valid file names must be supplied');
				return false;
			}
			if (isset($data['data']["thirdPartyBackgrounds"]) && !rfname_validate($data['data']['thirdPartyBackgrounds'])) {
				$this->error('Valid file names must be supplied');
				return false;
			}
			if (isset($data['data']["avatar"]) && !rfname_validate($data['data']['avatar']['fileName'])) {
				$this->error('Valid file names must be supplied');
				return false;
			}
			if (isset($data['data']["adsEnabled"]) && rfilter_var($data['data']["adsEnabled"],FILTER_VALIDATE_BOOLEAN)===false) {
				$this->error('Valid boolean must be supplied for param "adsEnabled"');
				return false;
			}
			if (isset($data['data']["ageBlock"]) && rfilter_var($data['data']["ageBlock"],FILTER_VALIDATE_BOOLEAN)===false) {
				$this->error('Valid boolean must be supplied for param "ageBlock"');
				return false;
			}
			if (isset($data['data']["opacity"]) && rfilter_var($data['data']["opacity"],FILTER_VALIDATE_FLOAT,false)===false) {
				$this->error('Valid opacity must be supplied');
				return false;
			}
			if (!isset($data["group"]) || rfilter_var($data["group"],FILTER_VALIDATE_INT,false)===false) {
				$this->error('Valid group must be supplied');
				return false;
			}
			if ((isset($data['data']["primaryColour"]) && colourvalidate($data['data']["primaryColour"])===false)
				|| (isset($data['data']["secondaryColour"]) && colourvalidate($data['data']["secondaryColour"])===false)
				|| (isset($data['data']["primaryFontColour"]) && colourvalidate($data['data']["primaryFontColour"])===false)
				|| (isset($data['data']["secondaryFontColour"]) && colourvalidate($data['data']["secondaryFontColour"])===false)
			) {
				$this->error('Valid colours must be supplied');
				return false;
			}
			if (isset($data['data']['theme']) && !($data['data']['theme']==="light" || $data['data']["theme"]==="dark")) {
				$this->error('Valid theme must be supplied');
				return false;
			}
			// Want all or nothing so use transaction
			$this->db->trans_begin();
			$error = FALSE;
			$updated = $this->group->update($data['group'], $data['data'], $error);

			// Check everything was successful
			if($updated && $this->db->trans_status() !== FALSE && $error === FALSE) {
				$this->db->trans_commit();
				$returnData = ['forum' => ['id' => $updated]];
			} else {
				$this->db->trans_rollback();
				if($error) {
					$this->error($error);
				} else {
					$this->error('The forum was not updated.');
				}
			}
		
		// fetch a groups post feed
		} else if ($secondary == "feed") {//data[type] white listed in Group.php, group validated in Group.php
			$this->load->helper(array('ramblurfilenamevalidate'));
			if (isset($data['fileName']) && !empty($data["filename"]) && !rfname_validate($data['fileName'])) {
				return false;
			}
			if (isset($data["commentedPostId"]) && rfilter_var($data["commentedPostId"],FILTER_VALIDATE_INT,true)===false) {
				return false;
			}
			if (isset($data["getNodeInfo"]) && rfilter_var($data["getNodeInfo"],FILTER_VALIDATE_BOOLEAN)===false) {
				$this->error('Valid boolean must be supplied for param "getNodeInfo"');
				return false;
			}
			if (isset($data["hash"]) && rfilter_var($data["hash"],FILTER_VALIDATE_BOOLEAN)===false) {
				$this->error('Valid boolean must be supplied for param "hash"');
				return false;
			}
			if (isset($data["limit"]) && rfilter_var($data["limit"],FILTER_VALIDATE_INT,true,0,200)===false) {
				$this->error('Valid limit must be supplied');
				return false;
			}
			$returnData = $this->group->feed($data);
		
		// fetch ramblur home page (landing page) feed
		} else if ($secondary == "getLandingPageFeed") {
			if (isset($data["offset"]) && rfilter_var($data["offset"],FILTER_VALIDATE_INT,true)===false) {
				$this->error('Valid offset must be supplied');
				return false;
			}
			if (isset($data["limit"]) && rfilter_var($data["limit"],FILTER_VALIDATE_INT,true,0,100)===false) {
				$this->error('Valid limit must be supplied');
				return false;
			}
		
			$dateFormat = 'Y-m-d H:i:s';
			if (isset($data['maxDate'])) {
				$d = DateTime::createFromFormat($dateFormat, $data['maxDate']);
				if (!$d || !$d->format($dateFormat) == $data['maxDate']) {
					$this->error("maxDate must be either NULL or in the forum 'Y-m-d H:i:s'");
					return false;
				}
				$maxDate = $data['maxDate'];
			} else {
				$maxDate = gmdate($dateFormat);
			}
			$returnData = ['posts' => $this->group->getLandingPageFeed($data["offset"], $data["limit"], $maxDate), 'maxDate' => $maxDate];
			
		// fetch draft posts
		} else if ($secondary == "getDraftPosts") {
			//if (isset($data["draftStatus"]) && rfilter_var($data["draftStatus"],FILTER_VALIDATE_INT,true)===false) {
			//	$this->error('Valid draft status must be supplied');
			//	return false;
			//}
			if (isset($data["offset"]) && rfilter_var($data["offset"],FILTER_VALIDATE_INT,true)===false) {
				$this->error('Valid offset must be supplied');
				return false;
			}
			if (isset($data["limit"]) && rfilter_var($data["limit"],FILTER_VALIDATE_INT,true,0,100)===false) {
				$this->error('Valid limit must be supplied');
				return false;
			}
		
			$dateFormat = 'Y-m-d H:i:s';
			if (isset($data['maxDate'])) {
				$d = DateTime::createFromFormat($dateFormat, $data['maxDate']);
				if (!$d || !$d->format($dateFormat) == $data['maxDate']) {
					$this->error("maxDate must be either NULL or in the forum 'Y-m-d H:i:s'");
					return false;
				}
				$maxDate = $data['maxDate'];
			} else {
				$maxDate = gmdate($dateFormat);
			}
			$returnData = ['posts' => $this->group->getDraftPosts($data["draftStatus"], $data["postType"], $data["singlePost"], $data["forumId"], $data["draftUserId"], $data["ramblurAuthorSelection"], $data["draftPriorityDatePost"], $data["newForum"], $data["newUserId"], $data["postId"], $data["nodeId"], $data["offset"], $data["limit"], $maxDate), 'maxDate' => $maxDate];	
		
		} else if ($secondary == "getMessageThread") {
			if (!isset($data["postId"]) || rfilter_var($data["postId"],FILTER_VALIDATE_INT,false)===false) {
				$this->error('getMessageThread: Valid postId must be supplied');
				return false;
			}
			$returnData = $this->group->getMessageThread($data["postId"]);	
			
		} else if ($secondary == "updateDraftPost") {
			if (!isset($data["postId"]) || rfilter_var($data["postId"],FILTER_VALIDATE_INT,false)===false) {
				$this->error('updateDraftPost: Valid postId must be supplied');
				return false;
			}
			if (!isset($data["nodeId"]) || rfilter_var($data["nodeId"],FILTER_VALIDATE_INT,false)===false) {
				$this->error('updateDraftPost: Valid nodeId must be supplied');
				return false;
			}
			if (isset($data["status"]) && rfilter_var($data["status"],FILTER_VALIDATE_INT,true)===false) {
			$this->error('updateDraftPost: Valid status must be supplied');
			return false;
			}
			$returnData = $this->group->updateDraftPost($data["postId"], $data["nodeId"], $data["status"]);

		} else if ($secondary == "updateDraftPostStatus") {
			if (!isset($data["postId"]) || rfilter_var($data["postId"],FILTER_VALIDATE_INT,false)===false) {
				$this->error('updateDraftPostStatus: Valid postId must be supplied');
				return false;
			}
			if (!isset($data["nodeId"]) || rfilter_var($data["nodeId"],FILTER_VALIDATE_INT,false)===false) {
				$this->error('updateDraftPostStatus: Valid nodeId must be supplied');
				return false;
			}
			if (isset($data["status"]) && rfilter_var($data["status"],FILTER_VALIDATE_INT,true)===false) {
			$this->error('updateDraftPostStatus: Valid status must be supplied');
			return false;
			}
			$returnData = $this->group->updateDraftPostStatus($data["postId"], $data["nodeId"], $data["status"]);
		
		  /*} else if ($secondary == "updateDraftNode") {
			if (!isset($data["postId"]) || rfilter_var($data["postId"], FILTER_VALIDATE_INT, false) === false) {
				$this->error('updateDraftnode: Valid postId must be supplied');
				return false;
			}
			if (!isset($data["nodeId"]) || rfilter_var($data["nodeId"], FILTER_VALIDATE_INT, false) === false) {
				$this->error('updateDraftnode: Valid nodeId must be supplied');
				return false;
			}
			if (isset($data["status"]) && rfilter_var($data["status"], FILTER_VALIDATE_INT, true) === false) {
				$this->error('updateDraftnode: Valid status must be supplied');
				return false;
			}
			$returnData = $this->group->updateDraftnode($data["postId"], $data["nodeId"], $data["status"]);/*
			 * 
			 */
			
		} else if ($secondary == "updateDraftNode") {
			if (!isset($data["postId"]) || rfilter_var($data["postId"], FILTER_VALIDATE_INT, false) === false) {
				$this->error('updateDraftnode: Valid postId must be supplied');
				return false;
			}
			if (!isset($data["nodeId"]) || rfilter_var($data["nodeId"], FILTER_VALIDATE_INT, false) === false) {
				$this->error('updateDraftnode: Valid nodeId must be supplied');
				return false;
			}
			if (isset($data["hasZing"]) && rfilter_var($data["hasZing"], FILTER_VALIDATE_BOOLEAN) === false) {
				$this->error('updateDraftnode: Valid boolean must be supplied for param "hasZing"');
				return false;
			}
			if (isset($data["status"]) && rfilter_var($data["status"], FILTER_VALIDATE_INT, true) === false) {
				$this->error('updateDraftnode: Valid status must be supplied');
				return false;
			}
			$returnData = $this->group->updateDraftnode($data["postId"], $data["nodeId"], $data["hasZing"], $data["status"]);
		} else if ($secondary == "deleteDraftNode") {
			if (!isset($data["postId"]) || rfilter_var($data["postId"],FILTER_VALIDATE_INT,false)===false) {
					$this->error('deleteDraftnode: Valid postId must be supplied');
					return false;
				}
				if (!isset($data["nodeId"]) || rfilter_var($data["nodeId"],FILTER_VALIDATE_INT,false)===false) {
					$this->error('deleteDraftnode: Valid nodeId must be supplied');
				return false;
			}
			$returnData = $this->group->deleteDraftnode($data["postId"], $data["nodeId"]);
			
		} else if ($secondary == "clearDraftReplyCount") {
			if (!isset($data["postId"]) || rfilter_var($data["postId"],FILTER_VALIDATE_INT,false)===false) {
				$this->error('clearDraftReplyCount: Valid postId must be supplied');
				return false;
			}
			$returnData = $this->group->clearDraftReplyCount($data["postId"]);	
		
			
		} else if ($secondary == "setToPending") {
			if (!isset($data["postId"]) || rfilter_var($data["postId"],FILTER_VALIDATE_INT,false)===false) {
				$this->error('setToPending: Valid postId must be supplied');
				return false;
			}
			$returnData = $this->group->setToPending($data["postId"]);	
			
		} else if ($secondary == "getDraftUsers") {
			if (!isset($data["userId"]) || rfilter_var($data["userId"],FILTER_VALIDATE_INT,false)===false) {
				$this->error('getDraftUsers: Valid userId must be supplied');
				return false;
			}
			if (!isset($data["subjectId"]) || rfilter_var($data["subjectId"],FILTER_VALIDATE_INT,false)===false) {
			$this->error('getDraftUsers: Valid subjectId must be supplied');
			return false;
			}
			$returnData = ['users' => $this->group->getDraftUsers($data["userId"], $data["subjectId"])];
			
		} else if ($secondary == "getDraftForumsByStatus") {
			if (isset($data["draftStatus"]) && rfilter_var($data["draftStatus"], FILTER_VALIDATE_INT, true) === false) {
				$this->error('getDraftForumsByStatus: Valid draftSatus must be supplied');
				return false;
			}
			$returnData = $this->group->getDraftForumsNamesAndIdsByStatus($data["draftStatus"]);	
			
		} else if ($secondary == "getForumsNamesAndIds") {
			$returnData = $this->group->getForumsNamesAndIds();	
			
		} else if ($secondary == "setNodePriorityDate") {
		if (!isset($data["nodeId"]) || rfilter_var($data["nodeId"], FILTER_VALIDATE_INT, false) === false) {
			$this->error('setNodePriorityDate: Valid nodeId must be supplied');
			return false;
		}
		$returnData = $this->group->setNodePriorityDate($data["dateVal"], $data["nodeId"]);	
			
		} else if ($secondary == "checkNodeOnLandingPage") {
			if (!isset($data["nodeId"]) || rfilter_var($data["nodeId"],FILTER_VALIDATE_INT,false)===false) {
				$this->error('checkNodeOnLandingPage: Valid nodeId must be supplied');
				return false;
			}
			$returnData = $this->group->checkNodeOnLandingPage($data["nodeId"]);			
			
		} else if ($secondary == "excludePost") {
			if (!isset($data["postId"]) || rfilter_var($data["postId"],FILTER_VALIDATE_INT,false)===false) {
				$this->error('Valid postId must be supplied');
				return false;
			}
			if (isset($data["landingPage"]) && rfilter_var($data["landingPage"],FILTER_VALIDATE_INT,true,0,1)===false) {
				$this->error('Valid landingPage must be supplied');
				return false;
			}
			if (isset($data["homePage"]) && rfilter_var($data["homePage"],FILTER_VALIDATE_INT,true,0,1)===false) {
				$this->error('Valid homePage must be supplied');
				return false;
			}
			if (isset($data["forum"]) && rfilter_var($data["forum"],FILTER_VALIDATE_INT,true,0,1)===false) {
				$this->error('Valid forum must be supplied');
				return false;
			}
			if (!isset($data["subjectId"]) || rfilter_var($data["subjectId"],FILTER_VALIDATE_INT,false)===false) {
				$this->error('Valid subjectId must be supplied');
				return false;
			}
			if (isset($data["edit"]) && rfilter_var($data["edit"],FILTER_VALIDATE_BOOLEAN)===false) {
				$this->error('Valid boolean must be supplied for param "edit"');
				return false;
			}
			if (isset($data["draft"]) && rfilter_var($data["draft"],FILTER_VALIDATE_BOOLEAN)===false) {
				$this->error('Valid boolean must be supplied for param "draft"');
				return false;
			}
			$returnData = $this->group->excludePost($data["postId"], $data["landingPage"], $data["mainFeed"], $data["forum"], $data["subjectId"], $data["edit"], $data["draft"]);
				
		} else if ($secondary == "includePost") {
			if (!isset($data["postId"]) || rfilter_var($data["postId"],FILTER_VALIDATE_INT,false)===false) {
				$this->error('Valid postId must be supplied');
				return false;
			}
			if (isset($data["landingPage"]) && rfilter_var($data["landingPage"],FILTER_VALIDATE_INT,true,0,1)===false) {
				$this->error('Valid landingPage must be supplied');
				return false;
			}
			if (isset($data["homePage"]) && rfilter_var($data["homePage"],FILTER_VALIDATE_INT,true,0,1)===false) {
				$this->error('Valid homePage must be supplied');
				return false;
			}
			if (isset($data["forum"]) && rfilter_var($data["forum"],FILTER_VALIDATE_INT,true,0,1)===false) {
				$this->error('Valid forum must be supplied');
				return false;
			}
			if (!isset($data['postUserId']) || rfilter_var($data["postUserId"],FILTER_VALIDATE_INT,false)===false) {
				$this->error('Invalid user id');
				return;
			}
			if( !isset($data["postSubjectId"]) || rfilter_var($data["postSubjectId"],FILTER_VALIDATE_INT,false)===false) {
				$this->error('Valid post subjectId must be supplied');
				return false;
			}
			if( !isset($data["subjectId"]) || rfilter_var($data["subjectId"],FILTER_VALIDATE_INT,false)===false) {
				$this->error('Valid subjectId must be supplied');
				return false;
			}
			if (isset($data["draft"]) && rfilter_var($data["draft"],FILTER_VALIDATE_BOOLEAN)===false) {
				$this->error('Valid boolean must be supplied for param "draft"');
				return false;
			}
			$returnData = $this->group->includePost($data["postId"], $data["postUserId"], $data["postSubjectId"], $data["subjectId"], $data["landingPage"], $data["mainFeed"], $data["forum"], $data["draft"]);
		
		// fetch a single thread (after a post was made)
		} else if ($secondary == "thread") {
		
			if (!isset($data["postId"]) || rfilter_var($data["postId"],FILTER_VALIDATE_INT,false)===false) {
				$this->error('Valid postId must be supplied to thread');
				return false;
			}
			if (isset($data["resetHeadNode"]) && rfilter_var($data["resetHeadNode"],FILTER_VALIDATE_BOOLEAN)===false) {
				$this->error('Valid boolean must be supplied for param "restHeadNode"');
				return false;
			}
			if (isset($data["scrollNodeId"]) && !preg_match('/^[a-zA-Z0-9]{1,}$/',$data["scrollNodeId"])) {
				$this->error('Valid string must be supplied for param "scrollNodeId"');
				return false;
			}
			if (isset($data["zing"]) && rfilter_var($data["zing"],FILTER_VALIDATE_INT,true)===false) {
				return false;
			}
			if (isset($data["bumpViewCount"])
				&& rfilter_var($data["bumpViewCount"],FILTER_VALIDATE_BOOLEAN)===false
				&& $data["bumpViewCount"]!='true'
				&& $data["bumpViewCount"]!='false'
			) {
				$this->error('Valid boolean must be supplied for param "bumpViewCount"');
				return false;
			}
			if (isset($data["headNodeId"]) && rfilter_var($data["headNodeId"],FILTER_VALIDATE_INT,true)===false) {
				$this->error('Valid headNodeId must be supplied to thread');
				return false;
			}
			if (isset($data["numZings"]) && rfilter_var($data["numZings"],FILTER_VALIDATE_INT,true)===false) {
				$this->error('Valid numZings must be supplied');
				return false;
			}
			if (isset($data["markViewed"]) && rfilter_var($data["markViewed"],FILTER_VALIDATE_BOOLEAN)===false) {
				$this->error('Valid boolean must be supplied as param "markViewed"');
				return false;
			}
			$returnData = $this->group->thread($data);
	
		// fetch info about a group
		} else if ($secondary == "info") {//group validated in Group.php
			if (isset($data["dashboard"]) && rfilter_var($data["dashboard"],FILTER_VALIDATE_BOOLEAN)===false) {
				$this->error('Valid boolean must be supplied for param "dashboard"');
				return false;
			}
			$returnData = $this->group->info($data);
		
		} else if ($secondary == "getThirdPartyAvatar") {// subjectname escaped in Group.php
			if(!isset($data["subjectId"])) {
				$this->error('subjectId must be supplied');
				return false;
			}
			$returnData = $this->group->getThirdPartyAvatar($data['subjectId']);
		
		// fetch all the extras belonging to the group
		// gallery, articles, reviews, blogs
		} else if ($secondary == "extras") {// group validated in Group.php
			if (isset($data["page"]) && !filter_var($data["page"],FILTER_VALIDATE_INT)) {
				$this->error('Valid page number must be supplied');
				return false;
			}
			$returnData = $this->group->extras($data);
		
		// fetch all the backgrounds belonging to the group
		} else if ($secondary == "backgrounds") {
			if (!isset($data["groupId"]) || rfilter_var($data["groupId"],FILTER_VALIDATE_INT,false)===false) {
				$this->error('Valid groupId must be supplied');
				return false;
			}
			$returnData = $this->group->backgrounds($data);
			
		} else if ($secondary === "getDefaultBackgrounds") {
			
			// Get the current user
			$userId = $this->user->authCheck();
			
			if (!isset($data["maxCount"]) || rfilter_var($data["maxCount"],FILTER_VALIDATE_INT,false)===false) {
				$this->error('Valid maxCount must be supplied');
				return false;
			}
			
			$returnData = $this->group->getDefaultBackgrounds($userId, $data["maxCount"]);
	
		} else if ($secondary == "getId") { // subjectName escaped in group.php
			if (!isset($data["subjectName"])) {
				$this->error('subject name must be supplied');
				return false;
			}
			$returnData = $this->group->getId($data);
	
		} else if ($secondary == "getDisplayDetails") {
				
			if (!isset($data['subjectId']) || rfilter_var($data["subjectId"],FILTER_VALIDATE_INT,true)===false) {
				$this->error('subject id must be supplied');
				return false;
			}
			$returnData = $this->group->getDisplayDetails($data['subjectId']);
	
		} else if ( $secondary == "getDeviceDisplayDetails" ) {
				
			if (!isset($data['subjectId']) || rfilter_var($data["subjectId"],FILTER_VALIDATE_INT,true)===false) {
				$this->error('subject id must be supplied');
				return false;
			}
			$returnData = $this->group->getDeviceDisplayDetails($data['subjectId']);
	
		} else if ($secondary == "getSocials") {// group validated in Group.php
	
			$returnData = $this->group->getSocials($data);
	
		} else if ($secondary == "setSocials") {//subjectName and socialname htmlpurified and escaped in Group.php
	
			$returnData = $this->group->setSocials($data);
	
		} else if ($secondary == "getThumbnails") {
			if (isset($data["nodeId"]) && rfilter_var($data["nodeId"],FILTER_VALIDATE_INT,false)===false) {
				$this->error('Valid nodeId must be supplied');
				return false;
			}
			$returnData = $this->group->getThumbnails($data);
	
		} else if ($secondary == "getForumCategories") {
			if (!isset($data["subjectId"]) || rfilter_var($data["subjectId"],FILTER_VALIDATE_INT,false)===false) {
				$this->error('Valid subjectId must be supplied');
				return false;
			}
			$returnData = $this->category->getForumCategories($data);
	
		} else if ($secondary == "getUsersGroupsAndCats") {
	
			$returnData = $this->group->getUsersGroupsAndCats($data["groupActive"], $data["useCatFilterListDefaults"]); //don't use the word subjectId here!
				
		} else if ($secondary == "updateUsersGroupsAndCats") {// Needs a helper to validate arrays of input...
	
			$returnData = $this->group->updateUsersGroupsAndCats($data);
	
		} else if ($secondary == "searchAtoZ") {// Needs a helper to validate arrays of input...
	
			$returnData = $this->group->searchAtoZ($data);
	
		} else if ($secondary == "displaySubTopicsByLevel") {//data[forumStates] white listed in Group.php
			if (!isset($data["subjectId"]) || rfilter_var($data["subjectId"],FILTER_VALIDATE_INT,true)===false) {
				$this->error('Valid subjectId must be supplied');
				return false;
			}
			if (isset($data["otherType"]) && rfilter_var($data["otherType"],FILTER_VALIDATE_INT,true)===NULL) {
				$this->error('Valid otherType must be supplied');
				return false;
			}
			if (isset($data["level"]) && rfilter_var($data["level"],FILTER_VALIDATE_INT,true)===NULL) {
				$this->error('Valid level must be supplied');
				return false;
			}
			$returnData = $this->group->displaySubTopicsByLevel($data);
	
		} else if ($secondary == "loadFeed") {//group validated, type whitelisted in Group.php
			/*if( isset($data["ctSearch"]) && rfilter_var($data["ctSearch"],FILTER_VALIDATE_BOOLEAN)===false){
			$this->error('Valid ctSearch must be supplied');
			return false;
			}
			if( isset($data["filter"]) && rfilter_var($data["filter"],FILTER_VALIDATE_INT,TRUE)===false){
			$this->error('Valid filter must be supplied');
			return false;
			}
			if( isset($data["limit"]) && rfilter_var($data["limit"],FILTER_VALIDATE_INT,true)===false){
			$this->error('Valid limit must be supplied');
			return false;
			}
			if( isset($data["offset"]) && rfilter_var($data["offset"],FILTER_VALIDATE_INT,TRUE)===false){
			$this->error('Valid offset must be supplied');
			return false;
			}*/

			$returnData = $this->group->loadFeed($data);
			$userId = $this->user->authCheck();
			if (is_array($returnData)) {
				$returnData['isForumMod'] = ($this->user->canManageSubject($userId, $data['group'])) ? 1 : 0;
				$returnData['forumAttributes'] = $this->subjectAttribute->getSubjectAttributesArray($data['group']);
				$returnData['forumVisibility'] = $this->subjectAttribute->getForumVisibility($data['group']);
			}

		} else if ($secondary == "nodeWithZings") {//data[threadType] white listed in Group.php
			if( !isset($data["zingNode"]) || rfilter_var($data["zingNode"],FILTER_VALIDATE_INT,false)===false) {
				$this->error('Valid zingNode must be supplied');
				return false;
			}
			if( isset($data["postId"]) && rfilter_var($data["postId"],FILTER_VALIDATE_INT,false)===false) {
				$this->error('Valid zingNode must be supplied');
				return false;
			}
			if( isset($data["headNodeId"]) && rfilter_var($data["headNodeId"],FILTER_VALIDATE_INT,true)===false) {
				$this->error('Valid headNodeId must be supplied');
				return false;
			}
			$returnData = $this->group->nodeWithZings($data);
	
		} else if ($secondary == "getCategories") {//group validated in Group.php
	
			$returnData = $this->group->getCategories($data);
	
		} else if ($secondary == "getBasicThreadData") {//added access control to Group.php
			if (!isset($data["postId"])
				|| !isset($data["subjectId"])
				|| rfilter_var($data["postId"],FILTER_VALIDATE_INT,false)===false
				|| rfilter_var($data["subjectId"],FILTER_VALIDATE_INT,false)===false
			) {
				$this->error('Valid postId and subjectId must be supplied to getBasicThreadData');
				return false;
			}
			$returnData = $this->group->getBasicThreadData($data['postId'], $data['subjectId']);
						
		} else if ($secondary == "setFilter") {
			if (!isset($data["filter"]) || !preg_match('/^[0-9,]{0,}$/',$data["filter"])) {
				$this->error('Valid filter must be supplied');
				return false;
			}
			$returnData = $this->group->setFilter($data);
	
		} else if ($secondary === 'users') {
	
			// want all users (with publicly accessible username, firstName, lastName or email?) who follow the group
			// and who are verified, not deleted or suspended.
			// if the group is private, want those with a userToSubject row, even if they don't have access, so the forum admin can reallow them access
			// return displayName, banned, ban date, id, private access, avatar
	
			// want some sort of pagination. offset and limit, or all for a letter. (what if username starts with non alpha character ie. numnber _ etc)
			// offset and limit probably easier. 100 at a time
	
			// Just return all for now, client can paginate in grid itself
			// Order by displayName
	
			if (!isset($data['group']) || rfilter_var($data["group"],FILTER_VALIDATE_INT,false)===false) {
				$this->error('invalid data');
				return;
			}
			$returnData = ['users' => $this->group->getMembers($data['group'])];
	
		} else if ($secondary === 'getStickies') {//added access control
			if (!isset($data['group']) || rfilter_var($data["group"],FILTER_VALIDATE_INT,false)===false) {
				//$this->error('invalid data');
				return;
			}
			$returnData = ['stickies' => $this->group->getStickies($data['group'])];
	
		} else if ($secondary === 'suggest') {
			if (!isset($data['name']) || !isset($data['description']) || !isset($data['category']) || !isset($data['subCategory'])) {
				$this->error('Invalid data');
				return;
			}
			$error = false;
			$suggestResult = $this->group->suggestForums($data['name'], $data['description'], isset($data['category']) ? $data['category'] : '', isset($data['subCategory']) ? $data['subCategory'] : '', $error);
	
			if ($suggestResult) {
				$returnData = ['success' => true];
			} else {
				if ($error) {
					$this->error($error);
					return;
				} else {
					$returnData = ['success' => false];
				}
			}
	
		} else if ($secondary === 'invite') {
			// group, users and email must be set. users and emails must be arrays
			if (!isset($data['group'])
				|| !isset($data['users'])
				|| !isset($data['emails'])
				|| !is_array($data['users'])
				|| !is_array($data['emails'])
				|| rfilter_var($data["group"],FILTER_VALIDATE_INT,false)===false
				|| rfilter_var($data["users"],FILTER_VALIDATE_INT,false)===false
				|| rfilter_var($data["emails"],FILTER_VALIDATE_EMAIL)===false
				|| !isset($data['message'])
			) {
				$this->error('invalid invite data');
				return;
			}
			$returnData = ['success' => $this->group->sendInvites($data['group'], $data['users'], $data['emails'], $data['message'], $data['fromName'], $data['fromEmail'])];
	
		} else if ($secondary === 'requestInvite') {//group validated in Group.php, message htmlpurified
			if (!isset($data['group'])
				|| !isset($data['name'])
				|| !isset($data['email'])
				|| !isset($data['message'])
				|| rfilter_var($data["email"],FILTER_VALIDATE_EMAIL)===false
			) {
				$this->error('invalid data');
				return;
			}
			$returnData = ['success' => $this->group->requestInvite($data['group'], $data['name'], $data['email'], $data['message'])];
						
		} else if ($secondary === 'respondToInvite') {
			// accept: true or false
			// invite id
			if (!isset($data['accept'])
				|| !isset($data['inviteId'])
				|| rfilter_var($data["accept"],FILTER_VALIDATE_BOOLEAN)===false
				|| rfilter_var($data["inviteId"],FILTER_VALIDATE_INT,false)===false
			) {
				$this->error('invalid data');
				return;
			}
			$result = $this->group->respondToInvite($data['accept'], $data['inviteId']);
			$returnData = ['success' => $result !== false];
			if ($result) {
				$returnData['groupId'] = $result;
			}
	
		} else if ($secondary == 'validateName') {
	
			if (!isset($data['name'])) {
				$this->error('invalid data');
				return;
			}
			$error = false;
			$name = $this->group->validateForumName($data['name'], $error);
	
			$returnData = ['name' => $name, 'valid' => $error === false, 'errorMessage' => $error];
		} else if ($secondary == 'infoFeed') {
			if (!isset($data["subjectId"]) || rfilter_var($data["subjectId"],FILTER_VALIDATE_INT,true)===false) {
				$this->error('infoFeed() - valid subjectId must be supplied');
				return false;
			}
			$returnData = $this->group->infoFeed($data['subjectId']);
		} else if ($secondary == 'getTopForums') {
			$returnData = $this->group->getTopForums();
		} else if ($secondary == 'updateForumAboutUsText') {
			if (!isset($data["subjectId"]) || rfilter_var($data["subjectId"],FILTER_VALIDATE_INT,true)===false) {
				$this->error('updateForumAboutUsText() - valid subjectId must be supplied');
				return false;
			}
			if (!isset($data["forumAboutUsText"])) {
				$this->error("updateForumAboutUsText() : valid forum about us text need to be provided");
				return;
			}
			$returnData = $this->group->updateForumAboutUsText($data['subjectId'], $data['forumAboutUsText']);
		}
		return $returnData;
	}
	
	private function postRequest($secondary, $data) {
		$returnData = false;
		
		if ($secondary == "storeNode") {//starttime and end time whitelisted, subject htmlpurifed  in File.php
		
			if (isset($data['fileName'])
				&& !empty($data["fileName"])
				&& $data["fileName"]!=='undefined'
				&& $data["fileName"] != 'null'
				&& !rfname_validate($data['fileName'])
			) {
				$this->error('Valid file name must be supplied to storeNode');
				return false;
			}
			if ((
					isset($data["parent"])
					&& !empty($data["parent"])
					&& $data["parent"] != 'undefined'
					&& $data["parent"] !== 'null'
					&& rfilter_var($data["parent"],FILTER_VALIDATE_INT,true)===false
				) || (
					isset($data["post"])
					&& !empty($data["post"])
					&& rfilter_var($data["post"],FILTER_VALIDATE_INT,true)===false
				)
			) {
				$this->error('Valid data must be supplied to storeNode');
				return false;
			}
			if (isset($data["category"])
				&& !empty($data["category"])
				&& $data["category"] !== 'undefined'
				&& $data["category"] !== 'null'
				&& rfilter_var($data["category"],FILTER_VALIDATE_INT,true)===false
			) {
				$this->error('Valid category must be supplied');
				return false;
			}
			if (isset($data["commentedPostId"])
				&& !empty($data["commentedPostId"])
				&& $data["commentedPostId"] !== 'undefined'
				&& $data["commentedPostId"] !== 'null'
				&& rfilter_var($data["commentedPostId"],FILTER_VALIDATE_INT,true)===false
			) {
				$this->error('Valid commentedPostId must be supplied');
				return false;
			}
			$returnData = $this->post->storeNode($data);

		/*} else if($secondary == "getReplies"){

			$returnData = $this->post->getReplies($data);*/
		
		} else if ($secondary == "addContentToNodes") {//atts[filename] and atts[type] validated.comments stripped in Group.php
		
			if (isset($data['fileName']) && !rfname_validate($data['fileName'])) {
				$this->error('Valid file name must be supplied');
				return false;
			}
			if (isset($data["nodeId"]["nodeId"])) {
				$data["nodeId"] = $data["nodeId"]["nodeId"];
			}
			if (!isset($data["nodeId"]) || rfilter_var($data["nodeId"],FILTER_VALIDATE_INT,false)===false) {
				$this->error('Valid nodeId must be supplied');
				return false;
			}
			$returnData = $this->post->addContentToNodes($data);
		
		} else if ($secondary == "deletePinloc") {
			if (!isset($data["node"]) || rfilter_var($data["node"],FILTER_VALIDATE_INT,false)===false) {
				$this->error('Valid node must be supplied');
				return false;
			}
			$returnData = ['result' => $this->post->deletePinloc($data['node'])];
		
		}
		return $returnData;
	}
	
	private function chatRequest($secondary, $data) {
		$returnData = false;
	
		if ($secondary === 'checkInvite') {
			if (!isset($data['toUserId'])
				|| !isset($data['fromUserId'])
				|| rfilter_var($data["toUserId"],FILTER_VALIDATE_INT,false)===false
				|| rfilter_var($data["fromUserId"],FILTER_VALIDATE_INT,false)===false
			) {
				$this->error('Data invalid.');
				return;
			}
			$returnData = ['result' => $this->user->checkChatInvite($data['toUserId'], $data['fromUserId'])];
		}
		return $returnData;
	}
	
	private function fileRequest($secondary, $data) {
		$returnData = false;
		
		// store a file
		if ($secondary == "store") {
			//usertitle stripped, subjectName escaped,comment stripped...inFile.php
		
			if (isset($data["parent"]) && !empty($data["parent"]) && rfilter_var($data["parent"],FILTER_VALIDATE_INT,true)===false) {
				$this->error('Valid parent must be supplied');
				return false;
			}
			if (!isset($data["group"]) || rfilter_var($data["group"],FILTER_VALIDATE_INT,true)===false) {
				$this->error('Valid group must be supplied');
				return false;
			}
			$returnData = $this->file->store($data);
		
		// unstore a file
		} else if ($secondary == "unstore") {
			$this->load->helper(array('ramblurfilenamevalidate'));
			if (!isset($data["fileName"]) || !rfname_validate($data['fileName'])) {
				$this->error('Valid file name must be supplied');
				return false;
			}
			$returnData = $this->file->unstore($data);
		
		// detach a file from a node
		} else if ($secondary == "detach") {
			if (!isset($data["file"]) || !rfname_validate($data['file'])) {
				$this->error('Valid file must be supplied');
				return false;
			}
			if (isset($data["node"]) && rfilter_var($data["node"],FILTER_VALIDATE_INT,true)===false) {
				$this->error('Valid node must be supplied');
				return false;
			}
			$returnData = $this->file->detach($data);
		
		// update an attachment's caption
		} else if ($secondary == "editAttachmentCaption") {// caption stripped in File.php
			if (!isset($data['caption']) || !isset($data['file']) || rfname_validate($data['file'])===false) {
				$this->error('Valid file name and caption must be supplied');
				return false;
			}
			$returnData = $this->file->editAttachmentCaption($data);
		
		// check file upload status in Content
		} else if ( $secondary == "status" ) {
			if (!isset($data['fileName']) || rfname_validate($data['fileName'])===false) {
				$this->error('Valid file name');
				return false;
			}
			$returnData = $this->file->checkFileUploadStatus($data);
		
		// utility server file processing results
		} else if ($secondary == 'updateLinkContent') {
		
			$clientIp = new ClientIPAddress(
					\IPLib\Factory::addressFromString($this->input->ip_address())
					);
			// only utility server is allowed to call this
			$netAuth = new IPNetworkAuthenticator($this->config->item('utility_servers'));
			if (!$netAuth->authenticate($clientIp)) {
				$this->error('Access denied');				
				return;
			}
			
			if (!isset($data['fileName']) || !isset($data['status'])
				|| rfname_validate($data["fileName"])===false
				|| rfilter_var($data["status"],FILTER_VALIDATE_INT,true)===false
			) {
				$this->error('Invalid data. fileName and status are required.');
				return;
			}
			$this->db->select('userId')
				->from('Content')
				->where('fileName', $data['fileName']);
			$fileResult = $this->db->get();
			if ($fileResult->num_rows() === 0) {
				$this->error('File could not be found.');
				return;
			}
			$updateData = ['status' => $data['status']];
			$allowedFields = ['userTitle', 'description', 'fileType', 'mimeType', 'numViews', 'URL', 'duration'];
			// Don't update file details if only status is being updated
			$updateDetails = false;
			foreach ($allowedFields as $f) {
				if (isset($data[$f])) {
					$updateData[$f] = $data[$f];
					$updateDetails = true;
				}
			}

			$this->db->update('Content', $updateData, ['fileName' => $data['fileName']]);
			if ($this->db->affected_rows() == 0) {
				$this->error('Update content row failed.');
				return;
			}
			$returnData = true;
		
		} else if ($secondary === 'addLinkThumbnail') {
		
			$clientIp = new ClientIPAddress(
					\IPLib\Factory::addressFromString($this->input->ip_address())
					);
			// only utility server is allowed to call this
			$netAuth = new IPNetworkAuthenticator($this->config->item('utility_servers'));
			if (!$netAuth->authenticate($clientIp)) {
				$this->error('Access denied');
				return;
			}
			
			if (!isset($data['fileName']) || !isset($data["image"]) || rfname_validate($data["fileName"])===false) {
				$this->error('Invalid data. fileName and image are required.');
				return;
			}
			$this->db->select('userId')
				->from('Content')
				->where('fileName', $data['fileName']);
			$fileResult = $this->db->get();
			if ($fileResult->num_rows() === 0) {
				$this->error('File could not be found.');
				return;
			}
		
			// Copy the supplied file to its proper location
			$fileSuccess = file_put_contents($this->file->setFilePath($data["fileName"],false), base64_decode(str_replace(' ','+',$data["image"])));
			if (!$fileSuccess) {
				$this->error('File wasn\'t saved.');
				return;
			}
			$permSuccess = chmod($this->file->getFilePath($data["fileName"],false),0644);
			if (!$permSuccess) {
				$this->error('File permission change failed.');
				return;
			}
		
			/*
			 * NOTE: production env has multiple webservers. To avoid problems that seem to be arising from filesystem
			 * caching the following check was put in
			 */
			$fileWait = 0;
			while (!file_exists($this->file->getFilePath($data["fileName"],false))) {
				if ($fileWait >= 5) {
					$this->error('File does not exist.'.$data["fileName"]);
					return;
				}
				sleep(1);
				$fileWait++;
			}
			// Carry out additional file processing
			$this->file->updateDetails($data["fileName"],"photo",$fileResult->first_row('array')['userId'],0,0,false);
		
			$returnData = true;
		
		} else if ($secondary == "getPath") {
		
			if (!isset($data['fileName']) || rfname_validate($data["fileName"])===false) {
				$this->error('Invalid data. Valid fileName is required.');
				return;
			}
		
			$returnData = ['path' => $this->file->getRelFilePath($data['fileName'], false)];
		}
	
		return $returnData;
	}
	
	private function adRequest($secondary, $data) {
		$returnData = false;
	
		// fetch an ad
		if ($secondary == "fetch" ) {
			if (!isset($data["groupId"]) || rfilter_var($data["groupId"],FILTER_VALIDATE_INT,true)===false) {
				$this->error('Valid groupId must be supplied');
				return false;
			}
			$returnData = $this->ad->fetch($data);
				
		// create an ad report
		} else if ($secondary === 'report') {//data[reason] htmlpurified in ad.php
			if (!isset($data["subjectId"]) || rfilter_var($data["subjectId"],FILTER_VALIDATE_INT,false)===false) {
				$error = 'To report an ad, you must do so from a specific forum.';
				return false;
			}
			if (!isset($data["adId"]) || rfilter_var($data["adId"],FILTER_VALIDATE_INT,false)===false) {
				$this->error('Valid adId must be supplied');
				return false;
			}
			$this->load->model('reported_ad');
			$this->db->trans_begin();
		
			$error = false;
			$reportResult = $this->reported_ad->createReport($data, $error);
			if($reportResult && $this->db->trans_status() !== false && $error === false) {
				$this->db->trans_commit();
				$returnData = array('reported' => true);
			} else {
				$this->db->trans_rollback();
				if($error) {
					$this->error($error);
				} else {
					$returnData = array('reported' => false);
				}
			}
		
		// fetch ad reports for the requesting user
		} else if ($secondary === 'getReports') {
			$user = $this->user->authCheck();
			if(!$user) {
				$this->error('Must be logged in');
				return;
			}
			$this->load->model('reported_ad');
			// managers get all ad reports for ramblur targeted forums
			if($this->user->managerCheck()) {
				$returnData = array('reportedAds' => $this->reported_ad->getAllActiveRamblurReportedAds());
			} else {
				// non-managers can retrieve ad reports only from forums they own/manage
				if (!isset($data['subjectId']) || rfilter_var($data["subjectId"],FILTER_VALIDATE_INT,false)===false) {
					$this->error('subjectId must be set.');
					return;
				}
				$this->db->select('createdBy')
				->from('Subject')
				->where(array(
						'subjectId' => $data['subjectId'],
						'createdBy' => $user
				));
				if($this->db->get()->num_rows() === 0) {
					$this->error('Only the forum owner can retrieve reported ads.');
					return;
				}
				$returnData = array('reportedAds' => $this->reported_ad->getNonRamblurSubjectActiveReportedAds($data['subjectId']));
			}
		
			// fetch a single ad report, with fill details
		} else if ($secondary === 'getReport') {
			$user = $this->user->authCheck();
		
			if (!$user) {
				$this->error('Must be logged in.');
				return;
			}
			if (!isset($data['id']) || rfilter_var($data["id"],FILTER_VALIDATE_INT,false)===false) {
				$this->error('id must be set.');
				return;
			}
			$this->load->model('reported_ad');
			if (!$this->reported_ad->canReviewAd($data['id'], $user)) {
				$this->error('You are not allowed to access this reported ad.');
				return;
			}
			$returnData = array('reportedAd' => $this->reported_ad->getReportedAdDetails($data['id']));
		
		// fetch ad history
		} else if ($secondary === 'getHistory') {
			$user = $this->user->authCheck();
				
			if (!$user) {
				$this->error('Must be logged in.');
				return;
			}
			if (!isset($data['id']) || rfilter_var($data["id"],FILTER_VALIDATE_INT,false)===false) {
				$this->error('id must be set.');
				return;
			}
			$this->load->model('reported_ad');
			if(!$this->reported_ad->canReviewAd($data['id'], $user)) {
				$this->error('You are not allowed to access this reported ad.');
				return;
			}
			$returnData = array('adHistory' => $this->reported_ad->getReportedAdHistory($data['id']));
				
		// review an ad report
		} else if ($secondary === 'review') {//data[review] is a more complex data object
			$user = $this->user->authCheck();
		
			if(!$user) {
				$this->error('Must be logged in.');
				return;
			}
			$this->load->model(array('ad', 'reported_ad'));
			$reviewData = $data['review'];
		
			if (!isset($reviewData['result']) || !isset($reviewData['adArchiveId']) || rfilter_var($reviewData["adArchiveId"],FILTER_VALIDATE_INT,false)===false) {
				$this->error('adArchiveId and result must be set to review an ad.');
				return;
			}
			$adArchiveId = $reviewData['adArchiveId'];
			if (!$this->reported_ad->canReviewAd($adArchiveId, $user)) {
				$this->error('You are not allowed to review this reported ad.');
				return;
			}
			$ad = $this->ad->getAdDetails($this->reported_ad->getAdId($adArchiveId));
			if (is_null($ad)) {
				$this->error('The ad could not be found.');
				return;
			}
			$this->db->trans_begin();
		
			$error = false;
			$reviewResult = $this->reported_ad->review($reviewData, $adArchiveId, $error);
		
			if ($reviewResult && $this->db->trans_status() !== false && $error === false) {
				$this->db->trans_commit();
				$returnData = $reviewResult;
			} else {
				$this->db->trans_rollback();
				if($error) {
					$this->error($error);
				} else {
					$returnData = array('reviewed' => false);
				}
			}
		
		// retrieve single ad review
		} else if ($secondary === 'getReview') {//checked
			$user = $this->user->authCheck();
		
			if(!$user) {
				$this->error('Must be logged in.');
				return;
			}
		
			if (!isset($data['id']) || rfilter_var($data["id"],FILTER_VALIDATE_INT,false)===false) {
				$this->error('id must be set.');
				return;
			}
			$this->load->model(array('ad', 'reported_ad'));
			$review = $this->reported_ad->getAdReview($data['id']);
			if(!$this->user->canManageUser($user, $review['reviewerId'])) {
				$this->error('You are not allowed to view that ad review.');
				return ;
			}
			$returnData = array('adReview' => $review);
		
		// retrieve ad reviews for the user
		} else if ($secondary === 'getReviews') {//
			$user = $this->user->authCheck();
		
			if (!$user) {
				$this->error('Must be logged in.');
				return;
			}
			$this->load->model('reported_ad');
			if ($this->user->managerCheck()) {
				$returnData = array('reportedAdReviews' => $this->reported_ad->getUserReportedAdReviews($user));
			} else {
				// non-managers can retrieve ad reports only from forums they own/manage
				if (!isset($data['subjectId']) || rfilter_var($data["subjectId"],FILTER_VALIDATE_INT,false)===false) {
					$this->error('subjectId must be set.');
					return;
				}
				$this->db->select('createdBy')
					->from('Subject')
					->where(array(
						'subjectId' => $data['subjectId'],
						'createdBy' => $user
				));
				if($this->db->get()->num_rows() === 0) {
					$this->error('Only the forum owner can retrieve reported ad reviews.');
					return;
				}
				$returnData = array('reportedAdReviews' => $this->reported_ad->getSubjectReportedAdReviews($data['subjectId']));
			}
		
		} else if ($secondary === "fetchAd") {
			if (isset($data["groupId"]) && rfilter_var($data["groupId"],FILTER_VALIDATE_INT,false)===false) {
				$this->error('Valid groupId must be supplied');
				return false;
			}
			$returnData = $this->ad->fetchAd($data);
		
		
		} else if ($secondary === "recordImpression") {//data[src] white listed in Ad.php
			if( isset($data["adId"]) && rfilter_var($data["adId"],FILTER_VALIDATE_INT,false)===false) {
				$this->error('Valid adId must be supplied');
				return false;
			}
			$returnData = $this->ad->recordImpression($data);
		
		} else if ($secondary === "recordAdImpAndCheckExp" ) {//src white listed in Ad.php
			if (!isset($data["adId"])
				|| rfilter_var($data["adId"],FILTER_VALIDATE_INT,false)===false
				|| (isset($data["recordImpression"])
					&& rfilter_var($data["recordImpression"],FILTER_VALIDATE_BOOLEAN)===false)
			){
				$this->error('Valid adId must be supplied');
				return false;
			}
			$returnData = $this->ad->recordAdImpAndCheckExp($data["adId"], $data["src"], $data["recordImpression"]);
		}
		return $returnData;
	}
	
	private function reportedContentRequest($secondary, $data) {
		$returnData = false;
	
		// report abuse
		if ($secondary == 'report') {//data[reason] is white listed in Reported_content.php, data[comments] appears unused.
				
			// check if the user is logged in
			$userId = $this->user->authCheck();
		
			if ($userId === false ) {
				$this->error("Only logged in users can report content.");
				return;
			}
			if (isset($data["reportingUserId"]) && rfilter_var($data["reportingUserId"],FILTER_VALIDATE_INT,false)===false) {
				$this->error('Valid reportingUserId must be supplied');
				return false;
			}
			if (!isset($data["id"]) || ( rfilter_var($data["id"],FILTER_VALIDATE_INT,false)===false && !rfname_validate($data['id']))) {
				$this->error('Valid id must be supplied');
				return false;
			}
			if(!(isset($data["reason"]))) {
				$this->error("A reason for the report and the id must be provided.");
				return;
			}
			if(!isset($data['comments'])) {
				$data['comments'] = null;
			}
			$this->load->model('reported_content');
			$data['reportingUserId'] = $userId;
			$error = false;
			$reported = $this->reported_content->createReport($data, $error);
			if($error) {
				$this->error($error);
			} else {
				$returnData = array("result" => $reported);
			}
				
		} else if ($secondary == 'complaint') {
			if (!(isset($data["reportId"]) && isset($data["reviewId"]))
				|| rfilter_var($data["reportId"],FILTER_VALIDATE_INT,false)===false
				|| rfilter_var($data["reviewId"],FILTER_VALIDATE_INT,false)===false
			) {
				$this->error("The reportId and reviewId must be provided.");
				return;
			} else {
				$this->load->model('reported_content');
				$error = false;
				$complained = $this->reported_content->createComplaint($data["reportId"], $data["reviewId"], $error);
				if($error) {
					$this->error($error);
				} else {
					$returnData = array("result" => $complained);
				}
			}
		}
		return $returnData;
	}
	
	private function adQuoteRequest($secondary, $data) {
		$returnData = false;
	
		// calculate a quote for an ad
		if ($secondary == "cpm") {//uses a more complex data structure
		
			$returnData = $this->ad->getCpmAdQuote($data["adQuote"]);
		
		} else if ($secondary == "fixed") {//uses a more complex data structure
		
			$returnData = $this->ad->getFixedAdQuote($data["adQuote"]);
		
		} else if ($secondary == "ppc") {//uses a more complex data structure
		
			$returnData = $this->ad->getPpcAdQuote($data["adQuote"]);
		}
		return $returnData;
	}
	
	private function adClickRequest($secondary, $data) {
		$returnData = false;
	
		// Register an ad click
		if ($secondary == "adClick") {//$data['type'] is white listed in Ad.php
			if( !isset($data["adId"]) || rfilter_var($data["adId"],FILTER_VALIDATE_INT,false)===false) {
				$this->error('Valid adId must be supplied');
				return false;
			}
			$src = (!isset($data['src'])) ? 'desktop' : 'device';
			if($src!='desktop' && $src != 'device') {
				$this->error('src must be set correctly.');
				return;
			}
			$returnData = array('url' => $this->ad->click($data['adId'], $src));
		}
		return $returnData;
	}
	
	private function postAdRequest($secondary, $data) {
		$returnData = false;
		
		// Register an ad click
		if ($secondary == "savePostTime") {//data[postTime] run through strtotime in Ad.php
		
			if (!isset($data['parentAdId']) || rfilter_var($data["parentAdId"],FILTER_VALIDATE_INT,false)===false) {
				$this->error('parentAdId must be set.');
				return;
			}
			$returnData = $this->ad->savePostAdTime($data['parentAdId'], $data['postTime']);
		}
	
		return $returnData;
	}
	
	private function adVoteRequest($secondary, $data) {
		$returnData = false;
	
		// Register a post ad vote
		if ($secondary == "postAd") {//vote whitelisted in Ad.php
			if (!isset($data["postId"]) || rfilter_var($data["postId"],FILTER_VALIDATE_INT,false)===false) {
				$this->error('Valid postId must be supplied to postAd');
				return false;
			}
			$returnData = $this->ad->adVote($data['vote'], $data['postId']);
		}
		return $returnData;
	}
	
	private function adPostCodesRequest($secondary, $data) {
		$returnData = false;
	
		if ($secondary == "getPostCodes") {
			$returnData = $this->globals->getAllStatesAndPostcodes();
		}
		return $returnData;
	}
	
	private function payRequest($secondary, $data) {
		$returnData = false;
	
		// MasterCard Payment Gateway
		if ($secondary == "masterCard") {//Needs more attention
			if(!isset($data['order'])
				|| !isset($data['order']['adId'])
				|| rfilter_var($data['order']['adId'],FILTER_VALIDATE_INT,false)===false
			){
				$this->error('Valid adId must be supplied');
				return false;
			}
			$this->load->model('mastercard');
			$returnData = $this->mastercard->pay($data['order']);
		}
		return $returnData;
	}
	
	private function adAuthRequest($secondary, $data) {
		$returnData = false;
	
		$this->load->model('adadminmodel');
		// Authorize a distributed payment
		if ($secondary == "payment") {
			if (!isset($data["paymentId"]) || rfilter_var($data["paymentId"],FILTER_VALIDATE_INT,false)===false) {
				$this->error('Valid paymentId must be supplied');
				return false;
			}
			$returnData = $this->adadminmodel->preAuthPayment($data['paymentId']);
		
		} else if ($secondary == "paymentRange") {// month and year escaped in Adadminmodel.php
		
			$returnData = $this->adadminmodel->preAuthPaymentRange($data['month'], $data['year']);
		}
		return $returnData;
	}
	
	private function imageRequest($secondary, $data) {
		$returnData = false;
	
		if ($secondary == "verify") {
			if(!isset($data["imageName"]) || !rfname_validate($data["imageName"])) {
				$this->error('Valid file name must be supplied');
				return false;
			}
			$returnData = $this->file->verifyImage($data["imageName"]);
		
		}
		return $returnData;
	}
	
	private function backgroundRequest($secondary, $data) {
		$returnData = false;
	
		if ($secondary === 'update') {
			if (!isset($data["flipH"]) || rfilter_var($data["flipH"],FILTER_VALIDATE_BOOLEAN)===false) {
				$this->error('Valid flipH must be supplied');
				return false;
			}
			if (!isset($data["flipV"]) || rfilter_var($data["flipV"],FILTER_VALIDATE_BOOLEAN)===false) {
				$this->error('Valid flipV must be supplied');
				return false;
			}
			if (!isset($data["id"])/*  || rfname_validate($data["id"])===false */) {
				$this->error('id must be supplied');
				return false;
			}
			/* if (!isset($data["size"]) || rfilter_var($data["size"],FILTER_VALIDATE_INT,true)===false) {
				$this->error('Valid size must be supplied');
				return false;
			} */
			if (!isset($data["left"]) || rfilter_var($data["left"],FILTER_VALIDATE_INT,true, -300000000, 300000000)===false) {
				$this->error('Valid value of "left" must be supplied');
				return false;
			}
			if (!isset($data["top"]) || rfilter_var($data["top"],FILTER_VALIDATE_INT,true, -300000000, 300000000)===false) {
				$this->error('Valid value of "top" must be supplied');
				return false;
			}
			// require fileName, make sure user is admin or owner
			$background = $this->file->updateBackground($data);
			if($background) {
				$returnData = ['background' => $background];
			} else {
				$this->error('The background could not be updated.');
			}
		}
		return $returnData;
	}
	
	private function libraryRequest($secondary, $data) {
		$returnData = false;
		
		// store library entries
		if ($secondary == "updateLibrary") {
			if (!isset($data["fileNames"]) || !rfname_validate($data['fileNames'])) {
				$this->error('Valid file names must be supplied');
				return false;
			}
			if (!isset($data["subjectId"])) {
				$this->error('Valid subjectId must be supplied');
				return false;
			}
			if (isset($data["operation"]) && rfilter_var($data["operation"],FILTER_VALIDATE_BOOLEAN)===false) {
				$this->error('Valid operation must be supplied');
				return false;
			}
			$returnData = $this->library->updateLibrary($data);
		
		} else if ($secondary == "updateComments") {
			if (!isset($data["fileNames"]) || !rfname_validate($data['fileNames'])) {
				$this->error('Valid file names must be supplied');
				return false;
			}
			$returnData = $this->library->updateComments($data);
		
		} else if ($secondary == "deleteLibrary") {
			if (!isset($data["subjectId"])) {
				$this->error('Valid subjectId must be supplied');
				return false;
			}
			$returnData = $this->library->deleteLibrary($data);
		
		} else if ($secondary == "getLibraryContentByFilename") {
			if (!isset($data["fileName"]) || !rfname_validate($data['fileName'])) {
				$this->error('Valid file name must be supplied');
				return false;
			}
			$returnData = $this->library->getLibraryContentByFilename($data['fileName'], $data['type']);
		
		} else if ($secondary == "setContentVisibility") {
			if (!isset($data["fileNames"]) || !rfname_validate($data['fileNames'])) {
				$this->error('Valid file names must be supplied');
				return false;
			}
			$returnData = $this->library->setContentVisibility($data);
		
		} else if ($secondary == "getLibNames") {
			if (!isset($data["subjectId"])) {
				$this->error('Valid subjectId must be supplied');
				return false;
			}
			$returnData = $this->library->getLibNames($data);
		
		} else if ($secondary == "getUserContent") {
			if (!isset($data["subjectId"])) {
				$this->error('Valid subjectId must be supplied');
				return false;
			}
			$returnData = $this->library->getUserContent($data);
		
		} else if ($secondary == "getGalleryItems") {
			if (!isset($data["subjectId"]) || rfilter_var($data["subjectId"],FILTER_VALIDATE_INT,false)===false) {
				$this->error('Valid subjectId must be supplied');
				return false;
			}
			$returnData = $this->library->getGalleryItems($data);
		
		} else if ($secondary == "getLibraryContent") {
		
			if (isset($data["postId"]) && !empty($data['postId']) && $data['postId'] != 'file' && rfilter_var($data["postId"],FILTER_VALIDATE_INT,false)===false) {
				$this->error('Valid postId must be supplied to getlibrarycontent');
				return false;
			}
			if (!isset($data["subjectId"]) || rfilter_var($data["subjectId"],FILTER_VALIDATE_INT,false)===false) {
				$this->error('Valid subjectId must be supplied');
				return false;
			}
			if (isset($data["searchFlag"]) && rfilter_var($data["searchFlag"],FILTER_VALIDATE_BOOLEAN)===false) {
				$this->error('Valid searchFlag must be supplied');
				return false;
			}
			if (isset($data["fileType"]) && !preg_match('/^[a-z]{1,16}$/',$data["fileType"]) ) {
				$this->error('Valid file type must be supplied');
				return false;
			}
			$returnData = $this->library->getLibraryContent($data);
		
		} else if ($secondary == "subscribe") {
			if (!isset($data["subscriberId"]) || rfilter_var($data["subscriberId"],FILTER_VALIDATE_INT,false)===false) {
				$this->error('Valid subscriberId must be supplied');
				return false;
			}
			$returnData = $this->library->librarySubscribe($data);
			
		} else if ($secondary == "bumpNumViews") {
			if (!isset($data["postId"]) || rfilter_var($data["postId"],FILTER_VALIDATE_INT,false)===false) {
				$this->error('Valid postId must be supplied');
				return false;
			}
			$returnData = $this->library->bumpNumViews($data["postId"]);
			
		} else if ($secondary == "deviceBumpNumViews") {
			if (!isset($data["nodeId"]) || rfilter_var($data["nodeId"],FILTER_VALIDATE_INT,false)===false) {
				$this->error('Valid nodeId must be supplied');
				return false;
			}
			$returnData = $this->library->deviceBumpNumViews($data["nodeId"]);
		}
		return $returnData;
	}
	
	private function searchRequest($secondary, $data) {
		$returnData = false;
	
		if ($secondary == "autoSearchUli") {
		
			$returnData = $this->search->autoSearchUli($data);
		
		} else if ($secondary == "workarea") {
			if (!isset($data["subjectId"]) || rfilter_var($data["subjectId"],FILTER_VALIDATE_INT,false)===false) {
				$this->error('Valid subjectId must be supplied');
				return false;
			}
			$returnData = $this->search->workarea($data);
		
		} else if ($secondary == "site") {
			if (isset($data["forumState"]) && !preg_match('/^[a-z0-9]{1,16}$/',$data["forumState"])) {//also white listed in Search.php
				$this->error('Valid forumState must be supplied');
				return false;
			}
			if (!isset($data["searchby"])) {//white listed in Search.php
				$this->error('Valid search by must be supplied');
				return false;
			}
			$returnData = $this->search->site($data);
		}
		return $returnData;
	}
	
	private function countryRequest($secondary, $data) {
		$returnData = false;
	
		if ($secondary == "getCountries") {
			$returnData = $this->globals->countries();
			
		} else if($secondary == "getTimezones") {
			$returnData = $this->globals->getTimezones();
		
		} else if ($secondary == "getAllStates") {
			if(!isset($data['country'])) {
				$this->error('country must be set.');
				return;
			}
			$returnData = $this->globals->states($data["country"]);
		}
		return $returnData;
	}
	
	private function issueRequest($secondary, $data) {
		$returnData = false;
	
		// bug report
		if ($secondary == "create") {
			$userId = $this->user->authCheck();
			if (!$userId) {
				return false;
			}
			$this->load->model('jira_issue', 'issue');
		
			if (!(isset($data["summary"]) && isset($data["description"]))) {
				$this->error("Summary and description must both be set to report a bug.");
				return;
			} else {
				$returnData = $this->issue->createIssue($data["summary"], $data["description"], $userId, isset($_FILES["bug_file"]) && $_FILES["bug_file"]["size"] !== 0 ? $_FILES["bug_file"] : NULL);
			}
		}
		return $returnData;
	}
	
	private function moderatorRequest($secondary, $data) {
		$returnData = false;
		
		$userId = $this->user->authCheck();
		
		if (!$this->user->moderatorCheck()) {
			$this->error('Only users with moderator privileges can access this content.');
			return false;
		}
		
		// Retrieve forums which can be managed by the logged in user
		if ($secondary == 'groups') {
			$subjects = $this->user->getManageableSubjects($userId);
				
			$returnData = array('userId' => $userId, 'groups' => $subjects);
	
		// Retrieves admin notices specifically directed to mods.
		} else if($secondary == 'getUpdates') {
				
			// If the user is a moderator, return just their admin posts
			if ($this->user->isModerator($userId)) {
				$returnData = array('updates' => $this->post->getUserToAdminPosts($userId));
				$returnData["unreadCount"] = $this->user->getUnacknowledgedAdminPostCount($userId);
			// If the user is higher than a moderator, return all admin posts for the subject
			} else {
				if(!isset($data['subjectId']) || rfilter_var($data["subjectId"],FILTER_VALIDATE_INT,false)===false) {
					$this->error('You must set subjectId so that all admin posts targeted to moderators for the subject can be retrieved.');
				} else {
					$subjectId = $data['subjectId'];
					if(!$this->user->canManageSubject($userId, $subjectId)) {
						$this->error('You can only retrieve admin updates for moderators from subjects you manage.');
					} else {
						$returnData = array('updates' => $this->group->getModeratorAdminPosts($subjectId));
						$returnData["unreadCount"] = $this->group->getModeratorUnacknowlegedAdminPostCount($subjectId);
					}
				}
			}
				
		// Retrieves single admin notice
		} else if($secondary == 'getUpdate') {
			if (!isset($data["id"]) || rfilter_var($data["id"],FILTER_VALIDATE_INT,false)===false) {
				$this->error('id (UserToAdminPostId) must be set.');
			} else {
				$id = $data["id"];
				$post = $this->post->getUserToAdminPost($id);
				if(!$post) {
					$this->error('The update could not be found.');
				} else {
					if($this->user->isModerator($userId) && $post['userId'] !== $userId) {
						$this->error('You can only retrieve your own admin updates.');
					} else if(!$this->user->canManageUser($this->user->authCheck(), $post['userId'])){
						$this->error('You can only retrieve admin updates for moderators from subjects you manage.');
					} else {
	
						$update = $this->group->post($post['postId'],$post["nodeId"],$post['userId'],0,0,'post', NULL);
						// Override colours to default
						$update['theme'] = $this->group->getDefaultThemeColourOption()['value'];
						$update['opacity'] = group::DEFAULT_OPACITY;
						$update['primaryColour'] = group::DEFAULT_PRIMARYCOLOUR;
						$update['secondaryColour'] = group::DEFAULT_SECONDARYCOLOUR;
						$update['primaryFontColour'] = $this->group->getDefaultThemeFontOption()['value'];
						$update['secondaryFontColour'] = $this->group->getDefaultThemeFontOption()['value'];
	
						$update["readIt"] = $post["readIt"] == 0 ? FALSE : TRUE;
						$update['adminToPostId'] = $id;
						if($userId != $post['userId']) {
							$update['targetUser'] = array('id' => $post['userId'], 'username' => $post['userName']);
						}
						$returnData = ['update' => $update];
					}
				}
			}
				
		// Marks update as having been read by this user
		} else if ($secondary == 'acknowledgeUpdate') {
			if(!isset($data["id"])  || rfilter_var($data["id"],FILTER_VALIDATE_INT,true)===false) {
				$this->error('id must be set.');
			} else {
				$post = $this->post->getUserToAdminPost($data["id"]);
				if(!$post) {
					$this->error('The update could not be found.');
				} else {
					if($this->user->isModerator($userId) && $post['userId'] !== $userId) {
						$this->error('You can only acknowledge your own admin updates.');
					} else if(!$this->user->canManageUser($userId, $post['userId'])){
						$this->error('You can only acknowledge admin updates for moderators from subjects you manage.');
					} else {
						$this->post->setUserToAdminPostReadIt($data["id"], TRUE);
						$returnData = array('id' => $data["id"]);
					}
				}
			}
				
		// Bans a member from the forum for the set time period (7, 21 or 90 days). If days is set to 0, the ban is removed.
		} else if ($secondary == 'banMember') {
			if(!isset($data["userId"])
				|| !isset($data["days"])
				|| !isset($data["reportId"])
				|| !isset($data['reasons'])
				|| rfilter_var($data["userId"],FILTER_VALIDATE_INT,false)===false
				|| rfilter_var($data["days"],FILTER_VALIDATE_INT,true)===false
				|| rfilter_var($data["reportId"],FILTER_VALIDATE_INT,false)===false
			) {
				$this->error('userId, reportId, reasons and days must be set to ban a member.');
				return;
			}
			if (isset($data["commentNotification"]) && rfilter_var($data["commentNotification"],FILTER_VALIDATE_INT,true)===false) {
				$this->error('Valid commentNotification must be supplied');
				return false;
			}
			if (!is_array($data['reasons'])) {//white listed in Reported_content.php
				$this->error('reasons must be provided as an array.');
				return;
			}
			$this->load->model('reported_content');

			$reportId = $data['reportId'];
			$memberId = $data['userId'];
			$reasons = array_unique($data['reasons']);
			$banTimePeriod = $data['days'];
			$subjectId =  $this->reported_content->getReportSubjectId($reportId);
			if(!$this->user->canManageSubject($userId, $subjectId)) {
				$this->error('You can only ban members from subjects you moderate.');
				return;
			}
			$this->db->trans_begin();
				
			$banned = $this->user->banFromSubject($memberId, $subjectId, $banTimePeriod);
			$this->load->model('email');
			$this->email->sendBanFromSubjectEmail($memberId, $reportId, $subjectId,$banTimePeriod, $reasons);
			$msgId = $this->message->systemMessage($userId, $memberId, $subjectId, message::MSG_USER_BANNED, array('banPeriod' => $banTimePeriod, 'banReason' => $this->reported_content->getMessageFormattedReasons($reasons)));
				
			// Mark user as reviewed
			$error = FALSE;
			
			$reviewed = $this->reported_content->review($reportId, $data['days'], NULL, NULL, NULL, NULL, NULL, NULL, $reasons, $msgId, $error);
				
			if($banned && $reviewed && $this->db->trans_status() !== FALSE && $error === FALSE) {
				$this->db->trans_commit();
				$returnData = array('banned' => TRUE, 'complete' => $reviewed['complete']);
			} else {
				$this->db->trans_rollback();
				if($error) {
					$this->error($error);
				} else {
					$returnData = array('banned' => FALSE);
				}
			}
			
		// Removes a ban from a member
		} else if ($secondary == 'unbanMember') {
		
			if (!isset($data["userId"])
				|| !isset($data["reportId"])
				|| rfilter_var($data["userId"],FILTER_VALIDATE_INT,false)===false
				|| rfilter_var($data["reportId"],FILTER_VALIDATE_INT,false)===false
			) {
				$this->error('userId and reportId must be set to unban a member.');
			} else {
				$this->load->model('reported_content');
				$reportId = $data['reportId'];
				$memberId = $data['userId'];
				$subjectId =  $this->reported_content->getReportSubjectId($reportId);
				if (!$this->user->canManageSubject($userId, $subjectId)) {
					$this->error('You can only unban members from subjects you moderate.');
				} else {
					$this->db->trans_begin();

					$this->load->model('email');
					$this->email->unbanFromSubjectEmail($memberId, $subjectId);
					$unbanned = $this->user->unbanFromSubject($memberId, $subjectId);
					$msgId = $this->message->systemMessage($userId, $memberId, $subjectId, message::MSG_USER_UNBANNED);

					// Mark user as reviewed
					$error = FALSE;
					$reviewed = $this->reported_content->review($reportId, 0, NULL, NULL, NULL, NULL, NULL, NULL, array('unban'), $msgId, $error);

					if ($unbanned && $reviewed && $this->db->trans_status() !== FALSE && $error === FALSE) {
						$this->db->trans_commit();
						$returnData = array('unbanned' => true, 'complete' => $reviewed['complete']);
					} else {
						$this->db->trans_rollback();
						$returnData = array('unbanned' => false);
					}
				}
			}

		// Sends the member a warning notice, and stores the warning on the forum (post)
		// @todo - allow multiple reasons
		} else if ($secondary == 'warnMember') {
			if (!isset($data["userId"])
				|| !isset($data["reasons"])
				|| !isset($data["reportId"])
				|| rfilter_var($data["userId"],FILTER_VALIDATE_INT,false)===false
				|| rfilter_var($data["reportId"],FILTER_VALIDATE_INT,false)===false
			) {
				$this->error('userId, reportId and reasons must be set to warn a member.');
				return;
			}
			if (isset($data["commentNotification"]) && rfilter_var($data["commentNotification"],FILTER_VALIDATE_INT,true)===false) {
				$this->error('Valid commentNotification must be supplied');
				return false;
			}
			if(!is_array($data['reasons'])) {//white listed in reported_Content.php
				$this->error('reasons must be provided as an array.');
				return;
			}
			$this->load->model('reported_content');
				
			$reportId = $data['reportId'];
			$reasons = array_unique($data['reasons']);
			$memberId = $data['userId'];
			$subjectId =  $this->reported_content->getReportSubjectId($reportId);
			if(!$this->user->canManageSubject($userId, $subjectId)) {
				$this->error('You can only warn members from subjects you moderate.');
				return;
			}
			$this->db->trans_begin();
			$warned = $this->user->warnUser($memberId, $subjectId);
			// Mark user as reviewed
			$msgId = $this->message->systemMessage($userId, $memberId, $subjectId, message::MSG_USER_WARNED, array('warningReason' => $this->reported_content->getMessageFormattedReasons($reasons)));

			$error = FALSE;
			$reviewed = $this->reported_content->review($reportId, NULL, TRUE, NULL, NULL, NULL, NULL, NULL, $reasons, $msgId, $error);
				
			if($warned && $reviewed && $this->db->trans_status() !== FALSE && $error === FALSE) {
				$this->db->trans_commit();
				$returnData = array('warned' => TRUE, 'complete' => $reviewed['complete']);
			} else {
				$this->db->trans_rollback();
				if($error) {
					$this->error($error);
				} else {
					$returnData = array('warned' => FALSE);
				}
			}
						
		// No further action needed on a user for a reportedPost. Mark as completed on the user side
		} else if ($secondary == 'reviewMember') {
			if (!isset($data["userId"])
				|| !array_key_exists('ban', $data)
				|| !array_key_exists('warning', $data)
				|| !isset($data["reportId"])
				|| rfilter_var($data["userId"],FILTER_VALIDATE_INT,false)===false
				|| rfilter_var($data["reportId"],FILTER_VALIDATE_INT,false)===false
			) {
				$this->error('userId, reportId, ban and warning must be set to review a post.');
				return;
			}
			$this->load->model('reported_content');
				
			$reportId = $data['reportId'];
			$subjectId =  $this->reported_content->getReportSubjectId($reportId);
			if(!$this->user->canManageSubject($userId, $subjectId)) {
				$this->error('You can only review posts from subjects you moderate.');
			} else {
				// Mark user as reviewed
				if(is_null($data['ban']) && is_null($data['warning'])) {
					$this->error('Ban and Warning cannot both be null, as nothing will be recorded.');
				} else if(!is_null($data['ban']) && $data['ban'] !== '0') {
					$this->error('Ban must be either null or "0". To set a ban, use secondary = banMember');
				} else if(!is_null($data['warning']) && $data['warning'] !== '0') {
					$this->error('Warning must be either null or "0". To send a warning, use secondary = warnMember');
				} else {
					$error = FALSE;
					$reviewResult = $this->reported_content->review($reportId, $data['ban'], $data['warning'], null, null, null, null, null, null, null, $error);
					if($error) {
						$this->error($error);
					} else {
						$returnData = $reviewResult ? array('userReviewed' => true, 'complete' => $reviewResult['complete']) : array('userReviewed' => false);
					}
				}
			}
						
		// Marks a post as deleted, with reason (so image can be displayed eg. No trolling)
		} else if($secondary == 'deletePost') {
	
			if (isset($data["parentNodeId"]) && rfilter_var($data["parentNodeId"],FILTER_VALIDATE_INT,true)===false) {
				$this->error('Valid parentNodeId must be supplied');
				return false;
			}
			if (isset($data["commentNotification"]) && rfilter_var($data["commentNotification"],FILTER_VALIDATE_INT,true)===false) {
				$this->error('Valid commentNotification must be supplied');
				return false;
			}
			// required data set
			if (!(
				isset($data["parentNodeId"])
				&& isset($data['deleteNodes'])
				&& is_array($data['deleteNodes'])
				&& isset($data['restoreNodes'])
				&& is_array($data['restoreNodes'])
				)
				|| rfilter_var($data["parentNodeId"],FILTER_VALIDATE_INT,false)===false
			) {
				$this->error('parentNodeId, restoreNodes and deleteNodes must be set. deleteNodes is expected to be an array of objects: [{id, reasons}].');
				return;
			}
			$parentNodeId = $data['parentNodeId'];
			$subjectId = $this->node->getSubjectId($parentNodeId);
			// post exists
			if (!$subjectId) {
				$this->error('The post could not be found.');
				return;
			}
				
			$this->load->model('reported_content');
			$report = $this->reported_content->getReportedContentByContentId($parentNodeId, 'node');
			if (!$report){
				$this->error('The report for that content could not be found.');
				return;
			}
			$reportId = $report['reportedContentId'];
				
			// process nodes to be deleted
			if (!empty($data['deleteNodes'])) {
				$deleteNodes = $data['deleteNodes'];
				$dataValid = true;
				foreach($deleteNodes as $node) {
					if (!(isset($node['id']) && (rfilter_var($node["id"],FILTER_VALIDATE_INT,false)===false || rfname_validate($node['id']) === false) && isset($node['reasons']))) {
						$dataValid = false;
					}
					if (isset($node['reasons'])) {
						if (!is_array($node['reasons'])) {
							$dataValid = false;
						}
						foreach($node['reasons'] as $reason) {
							if (!preg_match('/^[A-Za-z]{1,32}$/',$reason)) { // Also white listed in Reported_content.php
								$dataValid = false;
							}
						}
					}
				}
				if(!$dataValid) {
					$this->error('Each node to be deleted must include id and a reasons array.');
					return false;
				}
				$allReasons = array();
			}

			// process nodes to be restored
			if (!empty($data['restoreNodes'])) {
				foreach($data['restoreNodes'] as $id) {
					if(!rfilter_var($id,FILTER_VALIDATE_INT,false) && !rfname_validate($id)) {
						$this->error('Valid ids must be supplied');
						return false;
					}
				}
				$restoreNodes = $data['restoreNodes'];
			}
				
			// Check post is in subject that user has access to
			if (!$this->user->canManageSubject($userId, $subjectId)) {
				$this->error('That post is not in one of the subjects you moderate.');
				return;
			}
			// Check moderator isn't trying to restore deleted posts
			/* $isModerator = $this->user->isModerator($userId);
			if ($isModerator && isset($restoreNodes)) {
				$this->error('You are not allowed to restore deleted posts.');
				return;
			} */
				
			$this->db->trans_begin();
				
			// Need to know if acting on attachments or whole reported content.
			$deletePost = false;
			$restorePost = false;
			
			// carry out node deletion
			if (isset($deleteNodes)) {
				$deleted = true;
				foreach($data['deleteNodes'] as $node) {
					// Are we deleting attachments or the whole node?
					if ($node['id'] == $parentNodeId) {
						$deletePost = true;
					}
					$allReasons = array_merge($allReasons, $node['reasons']);
					if (!$this->node->markAsDeleted($node['id'], $node['reasons'], $parentNodeId)) {
						$deleted = false;
					}
				}
			}
				
			// carry out node restoration
			if (isset($restoreNodes)) {
				$restored = true;
				foreach($data['restoreNodes'] as $id) {
					// Are we restoring attachments or whole node?
					if ($id == $parentNodeId) {
						$restorePost = true;
					}
					if (!$this->node->markAsNotDeleted($parentNodeId, $id)) {
						$restored = false;
					}
				}
			}
				
			$restoreError = FALSE;
			$deleteError = FALSE;
				
			// inform users and mark as reviewed
			if (isset($deleteNodes) && $deleted) {

				$reasons = array_unique($allReasons);

				if ($deletePost) {
					$deleteMsgId = $this->message->systemMessage($userId, $this->reported_content->getReportedUserId($reportId), $subjectId, message::MSG_POST_DELETED, array('removalReason' => $this->reported_content->getMessageFormattedReasons($reasons)));
					// Mark post as reviewed
					$deleteReviewed = $this->reported_content->review($reportId, NULL, NULL, NULL, TRUE, NULL, NULL, NULL, $reasons, $deleteMsgId, $deleteError);
				} else {
					// Mark attachments as reviewed
					$deleteMsgId = $this->message->systemMessage($userId, $this->reported_content->getReportedUserId($reportId), $subjectId, message::MSG_POST_ATTACHMENT_DELETED, array('removalReason' => $this->reported_content->getMessageFormattedReasons($reasons)));
					$deleteReviewed = $this->reported_content->review($reportId, NULL, NULL, NULL, NULL, TRUE, NULL, NULL, $reasons, $deleteMsgId, $deleteError);
				}
			}
			if (isset($restoreNodes) && $restored) {
				// msg to user/mod who deleted it in the first place
				// review recorded (separate from delete ones?)
				// msg to user that their post is no longer deleted


				if ($restorePost) {
					// Include nodeId in all these messages, so message can include draw of node
					$restoreMsgId = $this->message->systemMessage($userId, $this->reported_content->getReportedUserId($reportId), $subjectId, message::MSG_POST_RESTORED);
					// Mark post as reviewed
					$restoreReviewed = $this->reported_content->review($reportId, NULL, NULL, NULL, FALSE, NULL, NULL, NULL, NULL, $restoreMsgId, $restoreError);
				} else {
					// Include nodeId in all these messages, so message can include draw of node
					$restoreMsgId = $this->message->systemMessage($userId, $this->reported_content->getReportedUserId($reportId), $subjectId, message::MSG_POST_ATTACHMENT_RESTORED);
					// Mark attachments as reviewed
					$restoreReviewed = $this->reported_content->review($reportId, NULL, NULL, NULL, NULL, FALSE, NULL, NULL, NULL, $restoreMsgId, $restoreError);
				}
			}
				
			// create response
			$deleteComplete = isset($deleteNodes) ? $deleteError === FALSE && $deleted && $deleteReviewed : true;
			$restoreComplete = isset($restoreNodes) ? $restoreError === FALSE && $restored && $restoreReviewed : true;
				
			if ($deleteComplete && $restoreComplete && $this->db->trans_status() !== FALSE) {
				$this->db->trans_commit();
				$returnData = array('deleted' => TRUE, 'complete' => isset($deleteReviewed) ? $deleteReviewed['complete'] : $restoreReviewed['complete']); // Can use either delete or restore review, as if one is complete, the other is
			} else {
				$this->db->trans_rollback();
				if($deleteError) {
					$this->error($deleteError);
				} else if ($restoreError) {
					$this->error($restoreError);
				} else {
					$returnData = array('deleted' => FALSE);
				}
			}
						
		} else if ($secondary === 'deleteFile') {
			if (!(isset($data["reportId"]) && isset($data['reasons']) && isset($data['action']) || rfilter_var($data["reportId"],FILTER_VALIDATE_INT,false)===false)) { // reasons, action
				$this->error('reportId, reasons and action must be set.');
				return;
			}
			if (isset($data["commentNotification"]) && rfilter_var($data["commentNotification"],FILTER_VALIDATE_INT,true)===false) {
				$this->error('Valid commentNotification must be supplied');
				return false;
			}
			$action = $data['action'];
			if (!($action === 'restore' || $action === 'delete')) {
				$this->error('action can only be restore or delete.');
				return;
			}
			/* if ($action === 'restore' && !$this->user->supervisorCheck()) {
				$this->error('You are not allowed to restore files.');
				return;
			} */
			$this->load->model('reported_content');
	
			$reportId = $data['reportId'];
			$subjectId =  $this->reported_content->getReportSubjectId($reportId);
			if (!$this->user->canManageSubject($userId, $subjectId)) {
				$this->error('You can only review files from subjects you moderate.');
				return false;
			} else {
				$reportedFile = $this->reported_content->getActualReportedContent($reportId);
				if (!$reportedFile) {
					$this->error('The report for that content could not be found.');
					return false;
				}
				$this->db->trans_begin();
	
				$error = FALSE;
				if ($action === 'delete') {
					$actionResult = $this->file->markAsDeleted($reportedFile['id'], $data['reasons']);
				} else {
					$actionResult = $this->file->markAsNotDeleted($reportedFile['id']);
				}
				$msgId = $this->message->systemMessage($userId, $this->reported_content->getReportedUserId($reportId), $subjectId, $action === 'delete' ? message::MSG_FILE_DELETED : message::MSG_FILE_RESTORED);
				$reviewResult = $this->reported_content->review($reportId, null, null, null, $action === 'delete', null, null, null, $data['reasons'], $msgId, $error);
				if ($actionResult && $reviewResult && $this->db->trans_status() !== FALSE && !$error) {
					$this->db->trans_commit();
					$returnData = array('deleted' => true, 'complete' => $reviewResult['complete']);
				} else {
					if ($error) {
						$this->error($error);
						return false;
					} else {
						$returnData = array('deleted' => FALSE);
					}
				}
			}
				
			// Edit a post. Text can only be marked as censored, not actually changed
		} else if ($secondary == 'censorPost') {
			if(!(
				isset($data["reportId"])
				&& isset($data['reasons'])
				&& array_key_exists("text", $data)
				&& array_key_exists('title', $data)
				&& array_key_exists('attachments', $data)
				)
				|| rfilter_var($data["reportId"],FILTER_VALIDATE_INT,false)===false
			) {
				$this->error('contentId, reasons, text, title and attachments must be set.');
				return;
			}
			// NB. Reasons, text and title are htmlpurified or stripped in Post.php
			foreach($data["attachments"] as $a){
				if ( !isset($a["id"]) || !rfname_validate($a["id"]) ){
					$this->error('attachment ids must be set correctly.');
					return;
				}
			}
			if (!is_array($data['reasons'])) {
				$this->error('reasons must be provided as an array.');
				return;
			}
			foreach($data['reasons'] as $reason){//also white listed in Reported_content.php against list in function getReasons
				if (!preg_match('/^[A-Za-z]{1,16}$/',$reason)){
					$this->error('Valid reasons must be supplied');
					return false;
				}
			}

			if (isset($data["commentNotification"]) && rfilter_var($data["commentNotification"],FILTER_VALIDATE_INT,true)===false) {
				$this->error('Valid commentNotification must be supplied');
				return false;
			}
			$reportId = $data["reportId"];
			$this->load->model('reported_content');
			$report = $this->reported_content->getReportedContent($reportId);
			if (!$report){
				$this->error('The report for that content could not be found.');
				return;
			}
			$reasons = array_unique($data['reasons']);
			$subjectId = $this->reported_content->getReportSubjectId($reportId);
			$errorMsg = FALSE;

			if (!$this->user->canManageSubject($userId, $subjectId)) {
				$this->error('You can only edit posts in subjects you moderate.');
				return;
			}
				
			$content = $this->reported_content->getActualReportedContent($reportId);
			if (!$content) {
				$this->error('The content could not be found.');
				return;
			}
			// Want all or nothing so use transaction
			$this->db->trans_begin();
				
			$edited = $this->{$content['contentType']}->censorText($content['id'], $data['text'], $data['title'], $data['attachments'], $errorMsg);
				
			$msgId = $this->message->systemMessage($userId, $this->reported_content->getReportedUserId($reportId), $subjectId, message::MSG_POST_CENSORED, array('censorReason' => $this->reported_content->getMessageFormattedReasons($reasons)));
			// Mark post as reviewed
			$error = FALSE;
			$reviewed = $this->reported_content->review($reportId, NULL, NULL, NULL, NULL, NULL, TRUE, NULL, $reasons, $msgId, $error);
				
			// Check everything was successful
			if ($edited && $reviewed && $this->db->trans_status() !== FALSE && $errorMsg === FALSE && $error === FALSE) {
				$this->db->trans_commit();
				$returnData = array('censored' => TRUE, 'complete' => $reviewed['complete']);
			} else {
				$this->db->trans_rollback();
				if($errorMsg) {
					$this->error($errorMsg);
				} else if($error) {
					$this->error($error);
				} else {
					$returnData = array('censored' => FALSE);
				}
			}
			
		} else if ($secondary === 'deleteThread') {
			
			if (!(isset($data["reportId"]) && isset($data['message']) && isset($data['reasons']) && isset($data['action']) || rfilter_var($data["reportId"],FILTER_VALIDATE_INT,false)===false)) { // reasons, action
				$this->error('reportId, message, reasons and action must be set.');
				return;
			}

			$action = $data['action'];
			if (!($action === 'restore' || $action === 'delete')) {
				$this->error('action can only be restore or delete.');
				return;
			}

			$this->load->model('reported_content');
			
			$this->load->helper(array('htmlpurifier'));
			
			$modMessage = html_purify($data['message'], 'post');
			
			$reportId = $data['reportId'];
			$subjectId =  $this->reported_content->getReportSubjectId($reportId);
			if (!$this->user->canManageSubject($userId, $subjectId)) {
				$this->error('You can only review threads from subjects you moderate.');
			} else {
				$reportedNode = $this->reported_content->getActualReportedContent($reportId);
				if (!$reportedNode) {
					$this->error('The report for that content could not be found.');
					return false;
				}
				$this->db->trans_begin();
			
				$error = FALSE;
				
				$postId = $this->node->getPostId($reportedNode['id']);
				
				if (!$postId) {
					$this->error('The thread for that content could not be found.');
					return false;
				}
				
				if ($action === 'delete') {
					$actionResult = $this->post->markAsDeleted($postId);
				} else {
					$actionResult = $this->post->markAsNotDeleted($postId);
				}
				
				// foreach node.userId in whole post
				// $node->getAllUsers
				$participants = $this->post->getAllUserIds($postId);
				$reviewMsgId = NULL;
				$reportedUserId = $this->reported_content->getReportedUserId($reportId);
				foreach ($participants as $msgUserId) {
					// don't send a msg to current user if they were involved in the post
					if ($msgUserId !== $userId) {
						$msgId = $this->message->systemMessage($userId, $msgUserId, $subjectId, $action === 'delete' ? message::MSG_THREAD_DELETED : message::MSG_THREAD_RESTORED, ['modMessage' => $modMessage]);
						// atm, don't want the msg sent attached to the post, so don't attach to the review here
						//if ($reportedUserId === $msgUserId) {
						//	$reviewMsgId = $msgId;
						//}
					}
				}
				$reviewResult = $this->reported_content->review($reportId, null, null, null, null, null, null, $action === 'delete', $data['reasons'], $reviewMsgId, $error);
				if ($actionResult && $reviewResult && $this->db->trans_status() !== FALSE && !$error) {
					$this->db->trans_commit();
					$returnData = array('deleted' => true, 'complete' => $reviewResult['complete']);
				} else {
					if ($error) {
						$this->error($error);
					} else {
						$returnData = array('deleted' => FALSE);
					}
				}
			}
			
		// No further action needed on a post for a reportedPost. Mark as completed on the post side
		} else if ($secondary == 'reviewContent') {
			if (!isset($data["userId"])
				|| !array_key_exists('edited', $data)
				|| !array_key_exists('deleted', $data)
				|| !isset($data["reportId"])
				|| rfilter_var($data["userId"],FILTER_VALIDATE_INT,false)===false
				|| rfilter_var($data["reportId"],FILTER_VALIDATE_INT,false)===false
			) {
				$this->error('userId, reportId, edited and deleted must be set to review a post.');
				return;
			}
				
			$this->load->model('reported_content');
			$reportId = $data['reportId'];
			$subjectId =  $this->reported_content->getReportSubjectId($reportId);
				
			if (!$this->user->canManageSubject($userId, $subjectId)) {
				$this->error('You can only review posts from subjects you moderate.');
			} else {
				// Mark user as reviewed
				if (is_null($data['edited']) && is_null($data['deleted'])) {
					$this->error('Edited and Deleted cannot both be null, as nothing gets recorded.');
				} else if (!is_null($data['edited']) && $data['edited'] !== '0') {
					$this->error('Edited must be either null or "0". To edit a post, use secondary = editPost');
				} else if (!is_null($data['deleted']) && $data['deleted'] !== '0') {
					$this->error('Deleted must be either null or "0". To remove post attachments, use secondary = deletePost');
				} else {
					$error = FALSE;
					$reviewResult = $this->reported_content->review($reportId, NULL, NULL, NULL, $data['deleted'], null, $data['edited'], null, null, null, $error);
					if ($error) {
						$this->error($error);
					} else {
						$returnData = $reviewResult ? array('contentReviewed' => true, 'complete' => $reviewResult['complete']) : array('contentReviewed' => false);
					}
				}
			}

		// Retrieves all active reported nodes for a forum, sorted by date
		} else if ($secondary == 'getContentReports') {

			if (isset($data["subjectId"]) && rfilter_var($data["subjectId"],FILTER_VALIDATE_INT,false)===false ) {
				$this->error('subjectId must be set.');
				return false;
			} else {
				
				if (isset($data["subjectId"]) && !$this->user->canManageSubject($userId, $data["subjectId"])) {
					$this->error('You can only get reported posts from subjects you moderate.');
					return false;
				} else {
					$this->load->model('reported_content');
					if ($this->user->adminCheck()) {
						$subjectId = true;
					} else {
						if(!isset($data["subjectId"])) {
							$subjectId = [];
							foreach ($this->user->getModeratorSubjects($userId) as $subject) {
								$subjectId[] = $subject["id"];
							}
							if (count($subjectId) === 0) {
								$subjectId = null;
							}
						} else {
							$subjectId = $data["subjectId"];
						}
					}
					$returnData = array('contentReports' => $this->reported_content->getAllModeratorSubjectReports($subjectId, NULL));
				}
			}
	
		// Retrieves all details for a single abuse report
		// @todo - add countryId to ModeratorToSubject, and allow retrieval only if node is in country mod is assigned to
		// @todo - maybe add this on top of canManageSubject ie. isInModeratorCountry
		} else if ($secondary == 'getContentReport') {
			if (!isset($data["reportId"]) || rfilter_var($data["reportId"],FILTER_VALIDATE_INT,false)===false) {
				$this->error('reportId must be set.');
			} else {
				$reportId = $data["reportId"];
				$this->load->model('reported_content');
				$report = $this->reported_content->getReportDetails($reportId);
				// Allow any user above moderator level to retrieve, regardless of user and subject, as is read-only
				if ($this->user->isModerator($userId) && !$this->user->canManageSubject($userId, $report["report"]["subjectId"])) {
					$this->error('You can only get reported posts from subjects you moderate.');
				} else {
					$returnData = $report;
				}
			}
				
		// Retrieves all inactive reported nodes for a forum, ordered by date
		} else if ($secondary == 'getReportedContentReviews') {
			if (!isset($data["subjectId"])  || rfilter_var($data["subjectId"],FILTER_VALIDATE_INT,false)===false) {
				$this->error('subjectId must be set.');
			} else {
				$subjectId = $data["subjectId"];
				if (!$this->user->canManageSubject($userId, $subjectId)) {
					$this->error('You can only get reported posts from subjects you moderate.');
				} else {
					$this->load->model('reported_content');
					$returnData = array('reportedContentReviews' => $this->reported_content->getAllModeratorInactiveSubjectReports($subjectId));
				}
			}
				
		// Retrieves ids (and supplementary user and subject info) of all reported nodes the given user has authored in the given subject
		} else if ($secondary == 'getUserReports') {
			if (!isset($data['userId'])
				|| !isset($data['subjectId'])
				|| rfilter_var($data["userId"],FILTER_VALIDATE_INT,false)===false
				|| rfilter_var($data["subjectId"],FILTER_VALIDATE_INT,false)===false
			) {
				$this->error('userId and subjectId must be set to retrieve report ids for a user.');
			} else {
				$reportedUserId = $data['userId'];
				$subjectId = $data['subjectId'];
				$returnData = array(
					'userReports' => $this->user->getPreviousReports($reportedUserId, $subjectId),
					'user' => array('id' => $reportedUserId, 'username' => $this->user->info($reportedUserId, true)['userName']),
					'subject' => array('id' => $subjectId, 'name' => $this->group->getName($subjectId))
				);
			}

		// Retrieve messages for moderator, filtered by any in moderated group or sender is admin, supervisor or manager
		} else if ($secondary == 'getMessages') {
			if (!(isset($data["status"]))) {
				$this->error("status must be provided so the right messages can be retrieved. eg. '0' for unread mesasges.");
				return;
			} //NB $data["status"] is whitelisted in Message.php
			if ($this->user->supervisorCheck() || $this->user->managerCheck() || $this->user->adminCheck()) {
				if (!isset($data['subjectId']) || rfilter_var($data["subjectId"],FILTER_VALIDATE_INT,false)===false) {
					$this->error('subjectId must be set.');
					return;
				}
				$subjectId = $data['subjectId'];
			}
			$returnData = array('messages' => $this->message->moderatorMessages($data['status'], $userId, isset($subjectId) ? $subjectId : null, isset($data['isDevice']) ? true : false));
				
		// Retrieves previous posts written by mod
		} else if ($secondary == 'getNewsPosts') {
			if (!isset($data["subjectId"]) || rfilter_var($data["subjectId"],FILTER_VALIDATE_INT,false)===false) {
				$this->error('subjectId must be set.');
			} else {
				if (!$this->user->canManageSubject($userId, $data["subjectId"])) {
					$this->error('You can only get news posts from subjects you moderate.');
				} else {
					$returnData = array('newsPosts' => $this->group->getNewsPosts($userId, $data["subjectId"]));
				}
			}
				
			// Retrieves a single previous post written by a mod
		} else if ($secondary == 'getNewsPost') {
			if (!isset($data["id"]) || rfilter_var($data["id"],FILTER_VALIDATE_INT,false)===false) {
				$this->error('id (node id) must be set.');
				return;
			}
			$post = $this->post->getNewsPost($data["id"], (isset($data['isDevice'])) ? true : false);
			if (!$post) {
				$this->error('That post is not a valid news post.');
				return;
			}
			if (!$this->user->canManageSubject($userId, $post["subjectId"])) {
				$this->error('That post is not in one of the subjects you moderate.');
				return;
			}
			if ($this->user->isModerator($userId) && $userId != $post['userId']) {
				$this->error('You can only retrieve your own news posts.');
				return;
			}
			$returnData = array('newsPost' => $post);
				
		// Post editing specifically for news posts
		} else if ($secondary === 'editNewsPost') {
	
			if (!(isset($data['id'])) || rfilter_var($data["id"],FILTER_VALIDATE_INT,false)===false) {
				$this->error('id (nodeId) must be set.');
				return;
			}
			$optionalFields = array('text', 'title', 'reason', 'commentList', 'deleteList', 'uploads');
			$updateData = array();
			foreach($optionalFields as $f) {
				if (isset($data[$f])) {
					$updateData[$f] = $data[$f];
				}
			}
			if (count($updateData) === 0) {
				$this->error('At least one of text, title, reason, uploads, commentList or deleteList must be set.');
				return;
			}
			if (isset($updateData['commentList'])) {
				foreach($updateData['commentList'] as $c) {
					if (count($c) !== 2 || !rfname_validate($c[1])) {//NB description is stripped in Node.php
						$this->error('Each item in the commentList must contain the description and filename in that order.');
						return;
					}
				}
			}
			if (isset($updateData['deleteList']) && rfname_validate($updateData['deleteList'])===false){
				$this->error('Each file name in the delete list must be valid');
				return;
			}
			//NB user file name, type and comment are sanitised in File.php => store
			if (isset($updateData['uploads'])) {
				foreach($updateData['uploads'] as $u) {
					if (!isset($u['type']) || !isset($u['file']) || !isset($u['comment'])) {
						$this->error('Each upload item must contain the type eg. "video", a comment (can be empty) and the file.');
						return;
					}
				}
			}
	
			$post = $this->post->getNewsPost($data['id']);
			if (!$post) {
				$this->error('That post is not a valid news post.');
				return;
			}
			if (!$this->user->canManageSubject($userId, $post["subjectId"])) {
				$this->error('That post is not in one of the subjects you moderate.');
				return;
			}
			if ($this->user->isModerator($userId) && $userId != $post['userId']) {
				$this->error('You can only edit your own news posts.');
				return;
			}
			$error = false;
			$this->db->trans_begin();
			$updateData['nodeId'] = $data['id'];
			$updateData['postId'] = $post['postId'];
			$edited = $this->node->updateNode($updateData, true, $error);
				
			$uploadOk = true;
			if (isset($updateData['uploads'])) {
				$allUploaded = true;
				foreach($updateData['uploads'] as $u) {
					$fileData = [
						'parent' => $data['id'],
						'group' => $post['subjectId'],
						$u['type'] => $u['file'],
						'comment' => $u['comment']
					];
					if (isset($u['fileExt'])) {
						$fileData['fileExt'] = $u['fileExt'];
					}
					$filename = $this->file->store($fileData);
					if (!$filename) {
						$uploadOk = false;
						$error = 'There was a problem uploading the files. Please try again later.';
					}
				}
			}
				
			if ($this->db->trans_status() !== false && $error === false && $edited && $uploadOk) {
				$this->db->trans_commit();
				$returnData = array('edited' => true);
			} else {
				$this->db->trans_rollback();
				if ($error) {
					$this->error($error);
				} else {
					$returnData = array('edited' => FALSE);
				}
			}
				
			// Setting post visibility specifically for news posts
		} else if ($secondary === 'setNewsPostVisibility') {
			if (!(isset($data['id']) && isset($data['visibility']))
				|| rfilter_var($data["id"],FILTER_VALIDATE_INT,false)===false
				|| rfilter_var($data["visibility"],FILTER_VALIDATE_BOOLEAN)===false
			) {
				$this->error('id (nodeId) and visibility must be set.');
				return;
			}
			$post = $this->post->getNewsPost($data["id"]);
			if (!$post) {
				$this->error('That post is not a valid news post.');
				return;
			}
			if (!$this->user->canManageSubject($userId, $post["subjectId"])) {
				$this->error('That post is not in one of the subjects you moderate.');
				return;
			}
			if ($this->user->isModerator($userId) && $userId != $post['userId']) {
				$this->error('You can only edit your own news posts.');
				return;
			}
			$returnData = array('edited' => $this->post->setNewsPostVisibility($post['id'], $data['visibility']));
						
		// Retrieves total number of moderator related items that haven't been read/dealt with
		// Includes messages, unactioned reports and admin updates
		} else if ($secondary == 'getUnreadCount') {
			// If the user is a moderator, return counts for all their subjects combined
			if ($this->user->isModerator($userId)) {
				$returnData = array('counts' => $this->user->getModeratorPendingCount($userId));
			// If the user is higher than a supervisor, return counts for a single subject
			} else {
				if (isset($data["subjectId"]) && rfilter_var($data["subjectId"],FILTER_VALIDATE_INT,true)===false) {
					$this->error('Valid subjectId must be supplied');
					return false;
				}
				$returnData = array('counts' => $this->group->getModeratorPendingCount(isset($data['subjectId']) ? $data['subjectId'] : null));
			}
				
		} else if ($secondary == 'socialMediaVisit') {
			if (!isset($data['type']) || !isset($data['subjectId']) || rfilter_var($data["subjectId"],FILTER_VALIDATE_INT,false)===false) {
				$this->error('You must set a type for the social media provider visited, along with the subjectId.');
			} else {
				if ($this->user->isModerator($userId)) {
					$type = mb_strtolower($data['type']);
					if ($type !== 'facebook'/* && $type !== 'twitter'*/) {
						//$this->error('Currently only Facebook and Twitter are supported for tracking purposes.');
						$this->error('Currently only Facebook is supported for tracking purposes.');
					} else {
						$totalField = 'total' . ucfirst($type) . 'Logins';
						$lastField = 'last' . ucfirst($type) . 'Login';
						$this->db->set($totalField, $totalField . '+1', FALSE);
						$this->db->set($lastField, 'NOW()', FALSE);
						$this->db->where(array('userId' => $userId, 'subjectId' => $data['subjectId']));
						$this->db->update('ModeratorToSubject');
					}
				}
			}

		// Records a moderator visit to a forum page
		} else if ($secondary == 'forumView') {
			if (!isset($data["subjectId"]) || rfilter_var($data["subjectId"],FILTER_VALIDATE_INT,false)===false) {
				$this->error('subjectId must be set to record.');
			} else {
				if(!$this->user->canManageSubject($userId, $data['subjectId'])) {
					$returnData = array('recorded' => FALSE);
				} else {
					$this->db->update(
						'ModeratorToSubject',
						array('lastVisited' => date("Y-m-d H:i:s")),
						array(
							'userId' => $userId,
							'subjectId' => $data['subjectId']
						));
					// Just return something
					$returnData = array('recorded' => TRUE);
				}
			}
		
		} else if ($secondary === 'createStickie') {
			// require text, subjectId
			if (!(isset($data['subjectId']) && isset($data['text']) || rfilter_var($data["subjectId"],FILTER_VALIDATE_INT,false)===false)) {
				$this->error('subjectId and text must be set.');
				return false;
			}// $data['text'] html_purified in Group.php
			if (!$this->user->canManageSubject($userId, $data['subjectId'])) {
				$returnData = array('created' => FALSE);
				return false;
			}
			$returnData = array('created' => $this->group->createStickie($data['subjectId'], $userId, $data['text']));
		
		} else if ($secondary === 'editStickie') {
			// require id and text
			if (!isset($data['id']) || !isset($data['text']) || rfilter_var($data["id"],FILTER_VALIDATE_INT,false)===false) {
				$this->error('id (stickie id) and text must be set.');
				return false;
			}// $data['text'] html_purified in Group.php
			$stickie = $this->group->getStickie($data['id']);
			if (!$this->user->canManageSubject($userId, $stickie['subjectId'])) {
				$returnData = array('edited' => FALSE);
				return false;
			}
			$returnData =  array('edited' => $this->group->editStickie($data['id'], $data['text']));
		
		} else if ($secondary === 'deleteStickie') {
			// require id
			if (!isset($data['id']) || rfilter_var($data["id"],FILTER_VALIDATE_INT,false)===false) {
				$this->error('id (stickie id) must be set.');
				return false;
			}
			$stickie = $this->group->getStickie($data['id']);
			if (!$this->user->canManageSubject($userId, $stickie['subjectId'])) {
				$returnData = array('deleted' => FALSE);
				return false;
			}
			$returnData =  array('deleted' => $this->group->deleteStickie($data['id']));
	
		} else if ($secondary === 'getOverseer') {
			// no proxy user, return overseer for logged in user, regardless of level
			if (isset($data['subjectId']) && rfilter_var($data["subjectId"],FILTER_VALIDATE_INT,false)===false) {
				$this->error('subjectId must be an integer.');
				return false;
			}
			$returnData = array('overseer' => $this->user->getOverseer($userId, isset($data['subjectId']) ? $data['subjectId'] : null));
		
		} else if ($secondary === 'flagReportedUser') {
			if (!isset($data["userId"]) || rfilter_var($data["userId"],FILTER_VALIDATE_INT,false)===false) {
				$this->error('flagReportedUser() - must be a valid userId');
				return false;
			}
			if (isset($data['subjectId']) && rfilter_var($data["subjectId"],FILTER_VALIDATE_INT,false)===false) {
				$this->error('flagReportedUser() - must be a valid subjectId.');
				return false;
			}
			if (isset($data['reportId']) && rfilter_var($data["reportId"],FILTER_VALIDATE_INT,false)===false) {
				$this->error('flagReportedUser() - must be a valid reportId.');
				return false;
			}
			if (!$this->user->canManageSubject($userId, $data['subjectId'])) {
				$returnData = array('created' => FALSE);
				return false;
			}
			$this->load->model('reported_content');
			$error = false;
			// @todo - do these as a transaction
			$flagReportedUser = $this->reported_content->flagReportedUser($data['userId'], $data['subjectId'], $error);
			
			$reviewed = $this->reported_content->review($data['reportId'], NULL, NULL, 1, NULL, NULL, NULL, NULL, ['auto'], NULL, $error);
			if($error) {
					$this->error($error);
				} else {
					$returnData = array("result" => (boolean)$flagReportedUser);
				}
			
		} else if ($secondary === 'unflagReportedUser') {
			if (!isset($data["userId"]) || rfilter_var($data["userId"],FILTER_VALIDATE_INT,false)===false) {
				$this->error('flagReportedUser() - must be a valid userId');
				return false;
			}
			if (isset($data['subjectId']) && rfilter_var($data["subjectId"],FILTER_VALIDATE_INT,false)===false) {
				$this->error('flagReportedUser() - must be a valid subjectId.');
				return false;
			}
			if (isset($data['reportId']) && rfilter_var($data["reportId"],FILTER_VALIDATE_INT,false)===false) {
				$this->error('flagReportedUser() - must be a valid reportId.');
				return false;
			}
			if (!$this->user->canManageSubject($userId, $data['subjectId'])) {
				$returnData = array('created' => FALSE);
				return false;
			}
			$this->load->model('reported_content');
			$error = false;
			// @todo - do these as a transaction
			$unflagReportedUser = $this->reported_content->unflagReportedUser($data['userId'], $data['subjectId'], $error);
			$reviewed = $this->reported_content->review($data['reportId'], NULL, NULL, 0, NULL, NULL, NULL, NULL, ['auto'], NULL, $error);
			
			if($error) {
					$this->error($error);
				} else {
					$returnData = array("result" => (boolean)$unflagReportedUser);
				}
		}
	
		return $returnData;
		
	}
	
	private function supervisorRequest($secondary, $data) {
		$returnData = false;
		
		$userId = $this->user->authCheck();
		
		if (!$this->user->supervisorCheck()) {
			$this->error('Only users with supervisor privileges can access this content.');
			return false;
		}
		
		// Retrieves admin notices specifically directed to supervisors.
		if ($secondary == 'getUpdates') {
			if ($this->user->isSupervisor($userId)) {
				$supervisorId = $userId;
			} else {
				if (!isset($data['userId']) || rfilter_var($data["userId"],FILTER_VALIDATE_INT,false)===false) {
					$this->error('userId must be set so data can be retrieved for that supervisor.');
					return;
				}
				$supervisorId = $data['userId'];
				if (!$this->user->canManageUser($userId, $supervisorId)) {
					$this->error('You do not have permission to access that user.');
					return;
				}
			}
			$returnData = array('updates' => $this->post->getUserToAdminPosts($supervisorId));
			$returnData["unreadCount"] = $this->user->getUnacknowledgedAdminPostCount($supervisorId);
				
		// Retrieves ids of active reports for the supervisor
		} else if($secondary == 'getContentReports') {
			$this->load->model('reported_content');
			if ($this->user->isSupervisor($userId)) {
				$supervisorId = $userId;
			} else {
				if (!isset($data['userId']) || rfilter_var($data["userId"],FILTER_VALIDATE_INT,false)===false) {
					$this->error('userId must be set so data can be retrieved for that supervisor.');
					return;
				}
				$supervisorId = $data['userId'];
				if (!$this->user->canManageUser($userId, $supervisorId)) {
					$this->error('You do not have permission to access that user.');
					return;
				}
			}
			$returnData = array('contentReports' => $this->reported_content->getAllSupervisorActiveReports($supervisorId));
				
		// Retrieves ids of completed reports for the supervisor
		} else if ($secondary == 'getReportedContentReviews') {
			$this->load->model('reported_content');
			if ($this->user->isSupervisor($userId)) {
				$supervisorId = $userId;
			} else {
				if (!isset($data['userId']) || rfilter_var($data["userId"],FILTER_VALIDATE_INT,false)===false) {
					$this->error('userId must be set so data can be retrieved for that supervisor.');
					return;
				}
				$supervisorId = $data['userId'];
				if (!$this->user->canManageUser($userId, $supervisorId)) {
					$this->error('You do not have permission to access that user.');
					return;
				}
			}
			$returnData = array('reportedContentReviews' => $this->reported_content->getAllSupervisorInactiveReportIds($supervisorId));
				
		// Retrieves ids of active reports from unmoderated subjects for the supervisor
		} else if ($secondary == 'getUnmoderatedContentReports') {
			$this->load->model('reported_content');
			if ($this->user->isSupervisor($userId)) {
				$supervisorId = $userId;
			} else {
				if (!isset($data['userId']) || rfilter_var($data["userId"],FILTER_VALIDATE_INT,false)===false) {
					$this->error('userId must be set so data can be retrieved for that supervisor.');
					return;
				}
				$supervisorId = $data['userId'];
				if (!$this->user->canManageUser($userId, $supervisorId)) {
					$this->error('You do not have permission to access that user.');
					return;
				}
			}
			$returnData = array('contentReports' => $this->reported_content->getAllSupervisorUnmoderatedActiveReports($supervisorId));
				
		// Retrieves ids of inactive reports from unmoderated subjects for the supervisor
		} else if ($secondary == 'getUnmoderatedReportedContentReviews') {
			$this->load->model('reported_content');
			if ($this->user->isSupervisor($userId)) {
				$supervisorId = $userId;
			} else {
				if (!isset($data['userId']) || rfilter_var($data["userId"],FILTER_VALIDATE_INT,false)===false) {
					$this->error('userId must be set so data can be retrieved for that supervisor.');
					return;
				}
				$supervisorId = $data['userId'];
				if (!$this->user->canManageUser($userId, $supervisorId)) {
					$this->error('You do not have permission to access that user.');
					return;
				}
			}
			$returnData = array('reportedContentReviews' => $this->reported_content->getAllSupervisorUnmoderatedInactiveReportIds($supervisorId));
	
		// Retrieves total number of supervisor related items that haven't been read/dealt with
		// Includes messages, unactioned supervisor specific reports and admin updates
		} else if ($secondary == 'getUnreadCount') {
			if ($this->user->isSupervisor($userId)) {
				$supervisorId = $userId;
			} else {
				if (!isset($data['userId']) || rfilter_var($data["userId"],FILTER_VALIDATE_INT,false)===false) {
					$this->error('userId must be set so data can be retrieved for that supervisor.');
					return;
				}
				$supervisorId = $data['userId'];
				if (!$this->user->canManageUser($userId, $supervisorId)) {
					$this->error('You do not have permission to access that user.');
					return;
				}
			}
			$returnData = array('counts' => $this->user->getSupervisorPendingCount($supervisorId));
	
		// Removes a ban from a member
		// @todo - should supers be able to reduce a ban? Not just remove completely or add to one
		} else if ($secondary == 'unbanMember') {
	
			if (!isset($data["userId"])
				|| !isset($data["reportId"])
				|| rfilter_var($data["userId"],FILTER_VALIDATE_INT,false)===false
				|| rfilter_var($data["reportId"],FILTER_VALIDATE_INT,false)===false
			) {
				$this->error('userId and reportId must be set to unban a member.');
			} else {
				$this->load->model('reported_content');
				$reportId = $data['reportId'];
				$memberId = $data['userId'];
				$subjectId =  $this->reported_content->getReportSubjectId($reportId);
				if (!$this->user->canManageSubject($userId, $subjectId)) {
					$this->error('You can only unban members from subjects you supervise.');
				} else {
					$this->db->trans_begin();

					$this->load->model('email');
					$this->email->unbanFromSubjectEmail($memberId, $subjectId);
					$unbanned = $this->user->unbanFromSubject($memberId, $subjectId);
					$msgId = $this->message->systemMessage($userId, $memberId, $subjectId, message::MSG_USER_UNBANNED);
						
					// Mark user as reviewed
					$error = FALSE;
					$reviewed = $this->reported_content->review($reportId, 0, NULL, NULL, NULL, NULL, NULL, NULL, array('unban'), $msgId, $error);

					if ($unbanned && $reviewed && $this->db->trans_status() !== FALSE && $error === FALSE) {
						$this->db->trans_commit();
						$returnData = array('unbanned' => true, 'complete' => $reviewed['complete']);
					} else {
						$this->db->trans_rollback();
						$returnData = array('unbanned' => false);
					}
				}
			}
					
		// Retrieves ids (and supplementary info) of all reported nodes the given user has authored
		} else if ($secondary == 'getUserReports') {
			if (!isset($data['userId']) || rfilter_var($data["userId"],FILTER_VALIDATE_INT,false)===false) {
				$this->error('userId  must be set to retrieve report ids for a user.');
			} else {
				$reportedUserId = $data['userId'];
				$returnData = array(
					'userReports' => $this->user->getPreviousReports($reportedUserId),
					'user' => array('id' => $reportedUserId, 'username' => $this->user->info($reportedUserId, true)['userName'])
				);
			}
				
			// Reviews a complaint
		} else if ($secondary == 'complaintReview') {
			if (!isset($data['reportId'])
				|| !isset($data['complaintId'])
				|| !isset($data['action'])
				|| rfilter_var($data["reportId"],FILTER_VALIDATE_INT,false)===false
				|| rfilter_var($data["complaintId"],FILTER_VALIDATE_INT,false)===false
			) {
				$this->error('reportId, complaintId and action must be set to review a complaint.');
			} else {
				$this->load->model('reported_content');
				$reportId = $data['reportId'];
				$complaintId = $data['complaintId'];
				$action = $data['action'];
				$subjectId =  $this->reported_content->getReportSubjectId($reportId);
				if (!$this->user->canManageSubject($userId, $subjectId)) {
					$this->error('You can only review complaints from subjects you supervise.');
				} else {
						
					$this->db->trans_begin();

					$error = FALSE;
					$reviewed = $this->reported_content->complaintReview($reportId, $complaintId, $action, isset($data['data']) ? $data['data'] : NULL, $error);
					if ($reviewed && $this->db->trans_status() !== FALSE && $error == FALSE) {
						$this->db->trans_commit();
						$returnData = array('reviewed' => TRUE, 'complete' => $this->reported_content->getActiveComplaintCount($reportId) == 0);
					} else {
						$this->db->trans_rollback();
						if ($error) {
							$this->error($error);
						} else {
							$returnData = array('reviewed' => FALSE, 'complete' => FALSE);
						}
					}
				}
			}

		// Reviews an extreme content report. Handled differently to normal reviews, so separate request
		} else if ($secondary == 'extremeReview') { // data[result] is white listed in Reported_content.php
			if (!isset($data['reportId']) || !isset($data['result']) || rfilter_var($data["reportId"],FILTER_VALIDATE_INT,false)===false) {
				$this->error('reportId and result (value = delete, ban or ignore) must be set to review an extreme content report.');
			} else {
				$this->load->model('reported_content');
	
				$reportId = $data['reportId'];
				$subjectId =  $this->reported_content->getReportSubjectId($reportId);
				if (!$this->user->canManageSubject($userId, $subjectId)) {
					$this->error('You can only review reports from subjects you supervise.');
					return;
				}
				$this->db->trans_begin();
	
				$error = FALSE;
				$reviewed = $this->reported_content->extremeReview($reportId, $data['result'], $error);
	
				if ($reviewed && $this->db->trans_status() !== FALSE && $error == FALSE) {
					$this->db->trans_commit();
					$returnData = array('reviewed' => TRUE, 'complete' => TRUE);
				} else {
					$this->db->trans_rollback();
					if ($error) {
						$this->error($error);
					} else {
						$returnData = array('reviewed' => FALSE, 'complete' => FALSE);
					}
				}
			}
				
			// Records a supervisor visit to a forum page
		} else if ($secondary == 'forumReview') {
			if (!$this->user->isSupervisor($userId)) {
				$this->error('Forum views are only recorded for supervisors.');
				return;
			}
			if (!isset($data["subjectId"]) || rfilter_var($data["subjectId"],FILTER_VALIDATE_INT,false)===false) {
				$this->error('subjectId must be set to record.');
			} else {
				$this->db->insert(
					'SupervisorToSubjectLog',
					array(
						'userId' => $userId,
						'subjectId' => $data['subjectId'],
						'action' => 'REVIEW'
				));
				$this->db->set('lastReviewed', 'UTC_TIMESTAMP()', FALSE);
				$this->db->where(array(
					'userId' => $userId,
					'subjectId' => $data['subjectId']
				));
				$this->db->update('SupervisorToSubject');
				// Just return something
				$returnData = array('recorded' => TRUE);
			}
		
		// Retrieve messages for supervisor, filtered by any from moderator in supervised group or sender is admin, supervisor or manager
		} else if ($secondary == 'getMessages') {
			if (!(isset($data["status"]))) {//white listed in Message.php
				$this->error("status must be provided so the right messages can be retrieved. eg. '0' for unread mesasges.");
				return;
			}
			if ($this->user->isSupervisor($userId)) {
				$supervisorId = $userId;
			} else {
				if (!isset($data['userId']) || rfilter_var($data["userId"],FILTER_VALIDATE_INT,false)===false) {
					$this->error('userId must be set so data can be retrieved for that supervisor.');
					return;
				}
				$supervisorId = $data['userId'];
				if (!$this->user->canManageUser($userId, $supervisorId)) {
					$this->error('You do not have permission to access that user.');
					return;
				}
			}
			$returnData = array('messages' => $this->message->supervisorMessages($data['status'], $supervisorId));
		}
	
		return $returnData;
	}
	
	private function managerRequest($secondary, $data) {
		$returnData = false;
		
		$userId = $this->user->authCheck();
		
		if (!$this->user->managerCheck()) {
			$this->error('Only users with manager privileges can access this content.');
			return false;
		}
		
		// Retrieves admin notices specifically directed to managers.
		if ($secondary == 'getUpdates') {
			if ($this->user->isManager($userId)) {
				$managerId = $userId;
			} else {
				if (!isset($data['userId']) || rfilter_var($data["userId"],FILTER_VALIDATE_INT,false)===false) {
					$this->error('userId must be set so data can be retrieved for that manager.');
					return;
				}
				$managerId = $data['userId'];
				if (!$this->user->canManageUser($userId, $managerId)) {
					$this->error('You do not have permission to access that user.');
					return;
				}
			}
			$returnData = array('updates' => $this->post->getUserToAdminPosts($managerId));
			$returnData["unreadCount"] = $this->user->getUnacknowledgedAdminPostCount($managerId);
	
		// Retrieves total number of manager related items that haven't been read/dealt with
		// Includes messages and admin updates
		} else if ($secondary == 'getUnreadCount') {
			if ($this->user->isManager($userId)) {
				$managerId = $userId;
			} else {
				if (!isset($data['userId']) || rfilter_var($data["userId"],FILTER_VALIDATE_INT,false)===false) {
					$this->error('userId must be set so data can be retrieved for that manager.');
					return;
				}
				$managerId = $data['userId'];
				if (!$this->user->canManageUser($userId, $managerId)) {
					$this->error('You do not have permission to access that user.');
					return;
				}
			}
			$returnData = array('counts' => $this->user->getManagerPendingCount($managerId));
	
		// Retrieve messages for manager, filtered by sender is admin, supervisor or manager
		} else if($secondary == 'getMessages') {
			if (!(isset($data["status"]))) { // white listed in Messages.php
				$this->error("status must be provided so the right messages can be retrieved. eg. '0' for unread mesasges.");
				return;
			}
			if ($this->user->isManager($userId)) {
				$managerId = $userId;
			} else {
				if (!isset($data['userId']) || rfilter_var($data["userId"],FILTER_VALIDATE_INT,false)===false) {
					$this->error('userId must be set so data can be retrieved for that manager.');
					return;
				}
				$managerId = $data['userId'];
				if (!$this->user->canManageUser($userId, $managerId)) {
					$this->error('You do not have permission to access that user.');
					return;
				}
			}
			$returnData = array('messages' => $this->message->managerMessages($data['status'], $managerId));
				
		} else if ($secondary == 'getContentReports') {
			$this->load->model('reported_content');
			$returnData = array('contentReports' => $this->reported_content->getAllManagerActiveReports());
				
		// Retrieves ids of completed reports for the manager
		} else if ($secondary == 'getReportedContentReviews') {
			$this->load->model('reported_content');
			if ($this->user->isManager($userId)) {
				$managerId = $userId;
			} else {
				if (!isset($data['userId']) || rfilter_var($data["userId"],FILTER_VALIDATE_INT,false)===false) {
					$this->error('userId must be set so data can be retrieved for that manager.');
					return;
				}
				$managerId = $data['userId'];
				if (!$this->user->canManageUser($userId, $managerId)) {
					$this->error('You do not have permission to access that user.');
					return;
				}
			}
			$returnData = array('reportedContentReviews' => $this->reported_content->getAllManagerInactiveReportIds($managerId));
				
		// Retrieve reported nodes which haven't been actioned yet by a moderator or supervisor, but should have been
		// 15 days, 5% or 30% of users
		} else if ($secondary === 'getOverdueReports') {
			$this->load->model('reported_content');
			$returnData = array('contentReports' => $this->reported_content->getOverdueReportIds());
		}
	
		return $returnData;
	}
	
	private function globalSearchRequest($secondary, $data) {
		
		$this->load->model('globalSearch');
		$returnData = false;
	
		if ($secondary == "setGlobalSearchSafetyLevel") {
			if (!isset($data["userId"]) || rfilter_var($data["userId"],FILTER_VALIDATE_INT,false)===false) {
				$this->error('globalSearchRequest: Valid userId must be supplied');
				return false;
			}
			if (isset($data["id"]) && !preg_match('/^[a-zA-Z]{1,}$/',$data["id"])) {
				$this->error('globalSearchRequest: Valid string must be supplied for safety level');
				return false;
			}
			$returnData = $this->globalSearch->setGlobalSearchSafetyLevel($data["userId"], $data["id"]);
		} else if ($secondary == "getGlobalSearchSafetyLevel") {
			if (!isset($data["userId"]) || rfilter_var($data["userId"],FILTER_VALIDATE_INT,false)===false) {
				$this->error('globalSearchRequest: Valid userId must be supplied');
				return false;
			}
			$returnData = $this->globalSearch->getGlobalSearchSafetyLevel($data["userId"]);
		}
		return $returnData;
	}
	
	private function testRequest($secondary, $data) {
		if (ENVIRONMENT !== 'development') {
			return false;
		}

		$context = new ZMQContext();
		$socket = $context->getSocket(ZMQ::SOCKET_PUSH, 'my pusher');
		$socket->connect("tcp://localhost:5555");
			
		$socket->send(json_encode(['id' => 'test', 'message' => $data['message']]));
	}
	
	/*
	 * Map of primary-secondary to requests and auth rules for each
	 * 
	 * authN = authentication - does the user need to be signed in/have credentials?
	 * authZ = authorization - does the user need permissions to access/change the data? Nb. Not used yet.
	 */
	private $requests = [
		'user' => [
			'create' => ['authN' => false, 'authZ' => false],
			'getSocialFollowing' => ['authN' => true, 'authZ' => false],
			'following' => ['authN' => true, 'authZ' => false],
			'getSubjectColor' => ['authN' => false, 'authZ' => false],
			'isUnderage' => ['authN' => false, 'authZ' => false],
			'setForumMode' => ['authN' => false, 'authZ' => false],
			'getWallpaperModeSettings' => ['authN' => false, 'authZ' => false],
			'countryDD' => ['authN' => false, 'authZ' => false],
			'getStatesByCountry' => ['authN' => false, 'authZ' => false],
			'getSuburbsByPostcode' => ['authN' => false, 'authZ' => false],
			'auth' => ['authN' => false, 'authZ' => false],
			'socialAuth' => ['authN' => false, 'authZ' => false],
			'checkAuth' => ['authN' => false, 'authZ' => false],
			'info' => ['authN' => true, 'authZ' => false],
			'profile' => ['authN' => true, 'authZ' => false],
			'profileUpdate' => ['authN' => true, 'authZ' => false],
			'settingsProfileUpdate' => ['authN' => true, 'authZ' => false],
			'groupsQuickCount' => ['authN' => true, 'authZ' => false],
			'groups' => ['authN' => true, 'authZ' => false],
			'groups2' => ['authN' => true, 'authZ' => false],
			'buttons' => ['authN' => true, 'authZ' => false],
			'button' => ['authN' => true, 'authZ' => false],
			'getThreadButtonIds' => ['authN' => true, 'authZ' => false],
			'getUnreadMessagesCount' => ['authN' => true, 'authZ' => false],
			'profileDetails' => ['authN' => true, 'authZ' => false],
			'getSearchChoice' => ['authN' => true, 'authZ' => false],
			'setSearchChoice' => ['authN' => true, 'authZ' => false],
			'featuredGroups' => ['authN' => true, 'authZ' => false],
			'searchChoice' => ['authN' => true, 'authZ' => false],
			'getBasicDetails' => ['authN' => true, 'authZ' => false],
			'isForumSubscribed' => ['authN' => false, 'authZ' => false],
			'updateForumSettingsDevice' => ['authN' => true, 'authZ' => false],
			'addMapMarker' => ['authN' => true, 'authZ' => false],
			'getMapMarkers' => ['authN' => true, 'authZ' => false],
			'removeFromForum' => ['authN' => true, 'authZ' => false],
			'getBackgroundOpacity' => ['authN' => true, 'authZ' => false],
			'setBackgroundOpacity' => ['authN' => true, 'authZ' => false],
			'submitDeviceErrorReport' => ['authN' => true, 'authZ' => false],
			'invite' => ['authN' => true, 'authZ' => false],
			'sendNotificationEmail' => ['authN' => true, 'authZ' => false],
			'updateHelpBubbleStatus' => ['authN' => true, 'authZ' => false],
			'getUserForumIdsAndNames' => ['authN' => true, 'authZ' => false],
			'isUserInWideView' => ['authN' => true, 'authZ' => false],
			'setWideView' => ['authN' => true, 'authZ' => false],
			'getTotalNotificationCount' => ['authN' => true, 'authZ' => false]
		],
		'subject' => [
			'follow_subject2' => ['authN' => true, 'authZ' => false],
			'group_subjects2' => ['authN' => true, 'authZ' => false],
			'getSubjectGroups' => ['authN' => true, 'authZ' => false],
			'groupSubjectBranch' => ['authN' => true, 'authZ' => false],
			'getBackgroundImage' => ['authN' => false, 'authZ' => false],
			'removeGroup' => ['authN' => true, 'authZ' => false],
			'isBannedFromForum' => ['authN' => true, 'authZ' => false]
		],
		'node' => [
			'adminToggleNodeMark' => ['authN' => true, 'authZ' => false],
			'follow' => ['authN' => true, 'authZ' => false],
			'follow2' => ['authN' => true, 'authZ' => false],
			'like' => ['authN' => true, 'authZ' => false],
			'tick' => ['authN' => true, 'authZ' => false],
			'updateNode' => ['authN' => true, 'authZ' => false],
			'info' => ['authN' => false, 'authZ' => false],
			'setVisibility' => ['authN' => true, 'authZ' => false],
			'subscribe' => ['authN' => true, 'authZ' => false],
			'unsubscribe' => ['authN' => true, 'authZ' => false],
			'shortUserInfo' => ['authN' => true, 'authZ' => false],
			'toggleNode' => ['authN' => true, 'authZ' => false]			
		],
		'message' => [
			'create' => ['authN' => true, 'authZ' => false],
			'getSocialNames' => ['authN' => true, 'authZ' => false],
			'reply' => ['authN' => true, 'authZ' => false],
			'action' => ['authN' => true, 'authZ' => false],
			'messages' => ['authN' => true, 'authZ' => false]
		],
		'group' => [
			'create' => ['authN' => true, 'authZ' => false],
			'update' => ['authN' => true, 'authZ' => false],
			'feed' => ['authN' => false, 'authZ' => false],
			'getLandingPageFeed' => ['authN' => false, 'authZ' => false],
			'excludePost' => ['authN' => true, 'authZ' => false],
			'includePost' => ['authN' => true, 'authZ' => false],
			'getDraftPosts' => ['authN' => true, 'authZ' => false],
			'getMessageThread' => ['authN' => true, 'authZ' => false],
			'getDraftUsers' => ['authN' => true, 'authZ' => false],
			'updateAvatar' => ['authN' => true, 'authZ' => false],
			'getDraftForumsByStatus' => ['authN' => true, 'authZ' => false],
			'updateDraftPost' => ['authN' => true, 'authZ' => false],
			'updateDraftPostStatus' => ['authN' => true, 'authZ' => false],
			'updateDraftNode' => ['authN' => true, 'authZ' => false],
			'deleteDraftNode' => ['authN' => true, 'authZ' => false],
			'clearDraftReplyCount' => ['authN' => true, 'authZ' => false],
			'setToPending' => ['authN' => true, 'authZ' => false],
			'getForumsNamesAndIds' => ['authN' => true, 'authZ' => false],
			'setNodePriorityDate' => ['authN' => true, 'authZ' => false],
			'checkNodeOnLandingPage' => ['authN' => true, 'authZ' => false],
			'thread' => ['authN' => false, 'authZ' => false],
			'info' => ['authN' => false, 'authZ' => false],
			'getThirdPartyAvatar' => ['authN' => false, 'authZ' => false],
			'extras' => ['authN' => false, 'authZ' => false],
			'backgrounds' => ['authN' => false, 'authZ' => false],
			'getDefaultBackgrounds' => ['authN' => false, 'authZ' => false],
			'getId' => ['authN' => false, 'authZ' => false],
			'getDisplayDetails' => ['authN' => false, 'authZ' => false],
			'getDeviceDisplayDetails' => ['authN' => false, 'authZ' => false],
			'getSocials' => ['authN' => true, 'authZ' => false],
			'setSocials' => ['authN' => true, 'authZ' => false],
			'getThumbnails' => ['authN' => true, 'authZ' => false],
			'getForumCategories' => ['authN' => false, 'authZ' => false],
			'getUsersGroupsAndCats' => ['authN' => true, 'authZ' => false],
			'updateUsersGroupsAndCats' => ['authN' => true, 'authZ' => false],
			'searchAtoZ' => ['authN' => false, 'authZ' => false],
			'displaySubTopicsByLevel' => ['authN' => false, 'authZ' => false],
			'loadFeed' => ['authN' => false, 'authZ' => false],
			'nodeWithZings' => ['authN' => false, 'authZ' => false],
			'getCategories' => ['authN' => false, 'authZ' => false],
			'getBasicThreadData' => ['authN' => false, 'authZ' => false],
			'setFilter' => ['authN' => true, 'authZ' => false],
			'users' => ['authN' => true, 'authZ' => false],
			'getStickies' => ['authN' => false, 'authZ' => false],
			'suggest' => ['authN' => true, 'authZ' => false],
			'invite' => ['authN' => true, 'authZ' => false],
			'requestInvite' => ['authN' => false, 'authZ' => false],
			'respondToInvite' => ['authN' => true, 'authZ' => false],
			'validateName' => ['authN' => false, 'authZ' => false],
			'infoFeed' => ['authN' => false, 'authZ' => false],
			'getTopForums' => ['authN' => false, 'authZ' => false],
			'updateForumAboutUsText' => ['authN' => true, 'authZ' => false]
		],
		'post' => [
			'storeNode' => ['authN' => true, 'authZ' => false],
			'addContentToNodes' => ['authN' => true, 'authZ' => false],
			'deletePinloc' => ['authN' => true, 'authZ' => false]
		],
		'chat' => [
			'checkInvite' => ['authN' => false, 'authZ' => false]
		],
		'file' => [
			'store' => ['authN' => false, 'authZ' => false],
			'unstore' => ['authN' => true, 'authZ' => false],
			'detach' => ['authN' => true, 'authZ' => false],
			'editAttachmentCaption' => ['authN' => true, 'authZ' => false],
			'status' => ['authN' => true, 'authZ' => false],
			// utility server only - how to represent?
			'updateLinkContent' => ['authN' => false, 'authZ' => false],
			// utility server only - how to represent?
			'addLinkThumbnail' => ['authN' => false, 'authZ' => false],
			'getPath' => ['authN' => false, 'authZ' => false]
		],
		'ad' => [
			'fetch' => ['authN' => false, 'authZ' => false],
			'report' => ['authN' => true, 'authZ' => false],
			'getReports' => ['authN' => true, 'authZ' => false],
			'getReport' => ['authN' => true, 'authZ' => false],
			'getHistory' => ['authN' => true, 'authZ' => false],
			'review' => ['authN' => true, 'authZ' => false],
			'getReview' => ['authN' => true, 'authZ' => false],
			'getReviews' => ['authN' => true, 'authZ' => false],
			'fetchAd' => ['authN' => false, 'authZ' => false],
			'recordImpression' => ['authN' => false, 'authZ' => false],
			'recordAdImpAndCheckExp' => ['authN' => false, 'authZ' => false]
		],
		'reportedContent' => [
			'report' => ['authN' => true, 'authZ' => false],
			'complaint' => ['authN' => true, 'authZ' => false]
		],
		'adQuote' => [
			'cpm' => ['authN' => true, 'authZ' => false],
			'fixed' => ['authN' => true, 'authZ' => false],
			'ppc' => ['authN' => true, 'authZ' => false]
		],
		'adClick' => [
			// should make sure only from our client. eg. CSRF token
			'adClick' => ['authN' => false, 'authZ' => false]
		],
		'postAd' => [
			'savePostTime' => ['authN' => true, 'authZ' => false]
		],
		'adVote' => [
			'adVote' => ['authN' => true, 'authZ' => false]
		],
		'adPostCodes' => [
			'getPostCodes' => ['authN' => false, 'authZ' => false]
		],
		'pay' =>[
				// @todo - Check auth rules for this one
			'masterCard' => ['authN' => false, 'authZ' => false]
		],
		'adAuth' =>[
			'payment' => ['authN' => true, 'authZ' => false],
			'paymentRange' => ['authN' => true, 'authZ' => false]
		],
		'image' =>[
			'verify' => ['authN' => false, 'authZ' => false]
		],
		'background' =>[
			'update' => ['authN' => true, 'authZ' => false]
		],
		'library' =>[
			'updateLibrary' => ['authN' => true, 'authZ' => false],
			'updateComments' => ['authN' => true, 'authZ' => false],
			'deleteLibrary' => ['authN' => true, 'authZ' => false],
			'getLibraryContentByFilename' => ['authN' => true, 'authZ' => false],
			'setContentVisibility' => ['authN' => true, 'authZ' => false],
			'getLibNames' => ['authN' => true, 'authZ' => false],
			'getUserContent' => ['authN' => true, 'authZ' => false],
			'getGalleryItems' => ['authN' => true, 'authZ' => false],
			'getLibraryContent' => ['authN' => true, 'authZ' => false],
			'subscribe' => ['authN' => true, 'authZ' => false],
			'bumpNumViews' => ['authN' => false, 'authZ' => false],
			'deviceBumpNumViews' => ['authN' => false, 'authZ' => false]
		],
		'search' =>[
			'autoSearchUli' => ['authN' => true, 'authZ' => false],
			'workarea' => ['authN' => true, 'authZ' => false],
			'site' => ['authN' => false, 'authZ' => false]
		],
		'country' =>[
			'getCountries' => ['authN' => false, 'authZ' => false],
			'getTimezones' => ['authN' => false, 'authZ' => false],
			'getAllStates' => ['authN' => false, 'authZ' => false]
		],
		'issue' =>[
			'create' => ['authN' => true, 'authZ' => false]
		],
		'moderator' =>[
			'groups' => ['authN' => true, 'authZ' => false],
			'getUpdates' => ['authN' => true, 'authZ' => false],
			'getUpdate' => ['authN' => true, 'authZ' => false],
			'acknowledgeUpdate' => ['authN' => true, 'authZ' => false],
			'banMember' => ['authN' => true, 'authZ' => false],
			'unbanMember' => ['authN' => true, 'authZ' => false],
			'warnMember' => ['authN' => true, 'authZ' => false],
			'reviewMember' => ['authN' => true, 'authZ' => false],
			'deletePost' => ['authN' => true, 'authZ' => false],
			'deleteFile' => ['authN' => true, 'authZ' => false],
			'deleteThread' => ['authN' => true, 'authZ' => false],
			'censorPost' => ['authN' => true, 'authZ' => false],
			'reviewContent' => ['authN' => true, 'authZ' => false],
			'getContentReports' => ['authN' => true, 'authZ' => false],
			'getContentReport' => ['authN' => true, 'authZ' => false],
			'getReportedContentReviews' => ['authN' => true, 'authZ' => false],
			'getUserReports' => ['authN' => true, 'authZ' => false],
			'getMessages' => ['authN' => true, 'authZ' => false],
			'getNewsPosts' => ['authN' => true, 'authZ' => false],
			'getNewsPost' => ['authN' => true, 'authZ' => false],
			'editNewsPost' => ['authN' => true, 'authZ' => false],
			'setNewsPostVisibility' => ['authN' => true, 'authZ' => false],
			'getUnreadCount' => ['authN' => true, 'authZ' => false],
			'socialMediaVisit' => ['authN' => true, 'authZ' => false],
			'forumView' => ['authN' => true, 'authZ' => false],
			'createStickie' => ['authN' => true, 'authZ' => false],
			'editStickie' => ['authN' => true, 'authZ' => false],
			'deleteStickie' => ['authN' => true, 'authZ' => false],
			'getOverseer' => ['authN' => true, 'authZ' => false],
			'flagReportedUser' => ['authN' => true, 'authZ' => false],
			'unflagReportedUser' => ['authN' => true, 'authZ' => false]
		],
		'supervisor' =>[
			'getUpdates' => ['authN' => true, 'authZ' => false],
			'getContentReports' => ['authN' => true, 'authZ' => false],
			'getReportedContentReviews' => ['authN' => true, 'authZ' => false],
			'getUnmoderatedContentReports' => ['authN' => true, 'authZ' => false],
			'getUnmoderatedReportedContentReviews' => ['authN' => true, 'authZ' => false],
			'getUnreadCount' => ['authN' => true, 'authZ' => false],
			'unbanMember' => ['authN' => true, 'authZ' => false],
			'getUserReports' => ['authN' => true, 'authZ' => false],
			'complaintReview' => ['authN' => true, 'authZ' => false],
			'extremeReview' => ['authN' => true, 'authZ' => false],
			'forumReview' => ['authN' => true, 'authZ' => false],
			'getMessages' => ['authN' => true, 'authZ' => false]
		],
		'manager' =>[
			'getUpdates' => ['authN' => true, 'authZ' => false],
			'getUnreadCount' => ['authN' => true, 'authZ' => false],
			'getMessages' => ['authN' => true, 'authZ' => false],
			'getContentReports' => ['authN' => true, 'authZ' => false],
			'getReportedContentReviews' => ['authN' => true, 'authZ' => false],
			'getOverdueReports' => ['authN' => true, 'authZ' => false]
		],
		'globalSearch' =>[
			'setGlobalSearchSafetyLevel' => ['authN' => true, 'authZ' => false],
			'getGlobalSearchSafetyLevel' => ['authN' => true, 'authZ' => false]
		],
		// For development environment only - how to represent?
		'test' => [
			'test' => ['authN' => false, 'authZ' => false]
		]
	];
}