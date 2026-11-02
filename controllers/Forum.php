<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Forum extends CI_Controller {

	public function __construct() {

		parent::__construct();
		require_once APPPATH.'/third_party/Mobile_Detect.php';
		$this->detect = new Mobile_Detect;
	}

	private function isOldMobile() {

		if(
			($this->detect->is('AndroidOS')&&(float)str_replace('_', '.', $this->detect->version('Android')) < 5) ||
			($this->detect->is('iOS')&&(float)str_replace('_', '.', $this->detect->version('iPhone')) < 5) ||
			//($this->detect->is('iOS')&&(float)str_replace('_', '.', $this->detect->version('iPod')) < 5) ||
			($this->detect->is('Windows Phone OS')&&$this->detect->version('IE') < 10)
		) {

			$cookie = array(
				'name'   => 'ramblur_iom',
				'value'  => TRUE,
				'expire' => '31556926' // a year
			);
			$this->input->set_cookie($cookie);
			return true;

		}
		return false;

	}

	public function index() {
		// load the global functions
		$this->load->model('globals');
		$this->load->model('user');
		$userId = $this->user->authCheck();

		// MOBILE - check if it's an 'Old' Mobile
		if($this->detect->isMobile()&&!$this->detect->isTablet()) {

			// helper required for 'get_cookie'
			$this->load->helper('cookie');

			if(get_cookie('ramblur_iom')===NULL&&$this->isOldMobile()) {

				redirect($this->config->base_url().'browser/mobile');
				exit;

			}

		}

		$this->load->view('forumHeader', ['showLoading' => true, 'pageTitle' => 'ramblur - a world of forum communities', 'description' => 'ramblur is an exciting new social media platform that allows you to follow all of your interests in one place']);
		$this->load->view('forumFooter',array('postName' => '', 'subjectName' => ''));		

	}

	public function _remap($forumId,$postName) {
		
		$uri = $this->uri->segment_array();
		$segmentsCount = count($uri);
		
		// If no URI segments, we want the home page
		if ($segmentsCount === 0) {
			$this->index();
			return;
		}
		$this->load->helper('not_found_response');
		
		// forum id MUST be a number, or not specified
		if (!is_numeric($forumId) && $forumId !== 'index') {
			not_found_response();
			return false;
		}

		$wantPrivate = false;
		if ($uri[1] === 'private') {
			if ($segmentsCount < 2) {
				not_found_response();
				return false;
			}
			$wantPrivate = true;
			$forumName = urldecode($uri[2]);
		} else {
			$forumName = urldecode($uri[1]);
		}

		// mb_strrpos searches for the last occurence of the string, as we should allow for forums with 'forum' in their name
		// eg. phils treehouse forum
		$validURL = mb_strrpos($forumName, '-forum') === mb_strlen($forumName) - 6;
		if (!$validURL) {
			not_found_response();
			return false;
		}
		// Strip off the trailing '-forum', and replace dashes with spaces
		// @todo - what if a dash defines a forum name eg. 2 forums created, one 'video-gaming' and the other 'video gaming'?
		$forumName = str_replace("-", " ", mb_substr($forumName, 0, mb_strlen($forumName) - 6));
		
		$this->load->model('user');
		$this->load->model('group');
		$this->load->model('globals');
		
		$userId = $this->user->authCheck();
		
		// Need to know if the user is coming from a '3rd party' site
		$thirdParty = false;
		$thirdPartyPrivate = false;
		$thirdPartyPrivateMember = false;
		
		if ($this->session->thirdparty) {
			$thirdParty = true;
			unset($_SESSION['thirdparty']);
		}
		if (!empty($_SERVER["HTTP_REFERER"]) && !preg_match('/ramblur\.com/',$_SERVER["HTTP_REFERER"])) {
			$thirdParty = true;
		}
		// Check the forum exists
		// 'index' when a ramblur forum url doesn't have an id (eg. just '/surfing-forum', not '/surfing-forum/525')
		if ($forumId === 'index') {
			$forumId = $this->group->findByNameAndType($forumName, group::TYPE_RAMBLUR);
			if ($forumId) {
				$forum = ['subjectName' => $forumName, 'private' => false, 'type' => group::TYPE_RAMBLUR, 'requireAgeCheck' => false];
			} else {
				not_found_response();
				return;
			}
		} else {
			$this->db->select('subjectName, private, type, requireAgeCheck')
				->from('Subject')
				->where('subjectId', $forumId);
			$forumResult = $this->db->get();
			if ($forumResult->num_rows() === 0) {
				not_found_response();
				return;
			}
			$forum = $forumResult->first_row('array');
		}
		
		if ($thirdParty && $forum['private']) {
			$thirdPartyPrivate = true;
		}
		
		// If the user is logged in, need to know if they are old enough to view the forum
		// If they're not logged in, need to show an age check if the forum owner has flagged it
		$underage = false;
		$requireAgeCheck = false;
		
		if ((int)$forum['type'] === group::TYPE_CLUB18) {
			if ($userId) {
				$underage = $this->user->isUnder18($userId);
			} else {
				$requireAgeCheck = (boolean)$forum['requireAgeCheck'];
			}
		}
		
		// Check the user can access the forum
		// Should be able to access all public forums (unless underage and it's a club18 forum).
		// Can only access private forums if coming from a '3rd party' site, or following it
		$accessCheck = $this->db->query('CALL checkUser(?, ?, @outParam, true)', [$forumId, $userId]);
		if(($accessCheck->num_rows() === 0 || !$accessCheck->result_array()[0]['valid']) && !$thirdPartyPrivate) {
			// If they don't have access because they're underage, not found is NOT an inappropriate response,
			// so only give not found for 
			if (!$underage) {
				not_found_response();
				return;
			}
		}
		
		// Check if the user is a member of the private forum
		if ($thirdPartyPrivate) {
			if ($this->group->getUserAccess($forumId, $userId)) {
				$thirdPartyPrivateMember = true;
			}
		}
		
		// Check the forum name in the URL matches the forum specified by the id
		if ($forumName !== str_replace("-", " ", $forum['subjectName'])) {
			not_found_response();
			return;
		}
		
		// Check the forum is private if private was requested and vice versa, the forum is public if private wan't requested
		if (($wantPrivate && !$forum['private']) || (!$wantPrivate && $forum['private'])) {
			not_found_response();
			return;
		}

		// Check the post requested exists.
		if (isset($postName[0])) {
			$postName = $postName[0];
		} else {
			$postName = '';
		}
		if ($postName != '') {
			$postDetails = $this->checkPostName($postName, $userId, $forumId);
		}else{
			$postDetails = array("postName"=>'', "postType"=>'', "postImage"=>'', "postTitle"=>'', "postId"=>'', "postText"=>'');
		}
		if ($postDetails === false) {
			not_found_response();
			return;
		}

		// Get category filterlist
		if ($userId != false) {
			$catList = $this->globals->getCatFilterList($userId, $forumId);
		} else { 
			$catList='';
		}

		$subjectDetails = $this->group->getSubjectDetails($forumId);
		if (!$subjectDetails) {
			$subjectDetails["subjectName"] = '';
			$subjectDetails["description"] = '';
			$subjectDetails["type"] = '';
		}
		
		// All non-ramblur forums will be displayed as if it is a third party view
		// regardless of referer
		if ((int)$subjectDetails["type"] !== group::TYPE_RAMBLUR) {
			$thirdParty = true;
		}

		// MOBILE - check if it's an 'Old' Mobile
		if($this->detect->isMobile()&&!$this->detect->isTablet()) {

			// helper required for 'get_cookie'
			$this->load->helper('cookie');

			if(get_cookie('ramblur_iom')===NULL&&$this->isOldMobile()) {

				$this->session->onwardUrl = $uri;

				if($thirdPartyPrivate) {

					$redirectpath = 'browser/mobile/tpp';
					$this->session->onwardTP = true;

				} elseif ($thirdParty) {

					$redirectpath = 'browser/mobile/tp';
					$this->session->onwardTP = true;

				} else {

					$redirectpath = 'browser/mobile';
					$this->session->onwardTP = false;

				}
				
				redirect($this->config->base_url().$redirectpath);
				exit;

			}

		}

		$forumString = ($subjectDetails['type']==='0') ? 'official ramblur '.$subjectDetails["subjectName"] : $subjectDetails["subjectName"];
		$postBodySnippet = htmlentities(trim($postDetails["postText"])); // limited to 200 in checkPostName function below
		$postTitle = htmlentities(trim($postDetails["postTitle"]));
		$description = htmlentities(trim($subjectDetails["description"]));

		/*

		PAGE TITLE TAG

		*/

		$pageTitle = $this->generateSeoPageTitle($forumString, $postBodySnippet, $postTitle, $postDetails["postName"], $postDetails["postType"], $forumId, $subjectDetails['type'], $subjectDetails['subjectName']);		

		/*

		PAGE META DESCRIPTION

		*/

		$metaDesc = $this->generateSeoMetaDescription($description, $forumString, $postBodySnippet);

			
		// A forum has been specified and the user has access so set values for client side
		$this->load->model('subjectAttribute');
		$this->load->view('forumHeader', array(
			'groupId' => $forumId,
			'pageTitle' => $pageTitle,
			'postName' => $postDetails["postName"],
			'postTitle' => $postTitle,
			'postType' => $postDetails["postType"],
			'postImage' => $postDetails["postImage"],
			'postId' => $postDetails["postId"],
			'postText' => $postBodySnippet,
			'forumPrivate' => $wantPrivate,
			'description' => $metaDesc,
			'subjectName' => $forumString,
			'showLoading' => !($thirdPartyPrivate && !$thirdPartyPrivateMember)
		));
		
		$this->load->view('forumFooter', array(
			'groupId' => $forumId,
			'groupType' => $this->group->getTypeLabel($subjectDetails["type"]),
			'groupActiveFollowed' => $userId === false ? false : (boolean)$this->user->isForumSubscribed($forumId),
			'postName' => $postDetails["postName"],
			'pageTitle' => $pageTitle,			
			'subjectName' => $subjectDetails["subjectName"],
			'catFilterList' => $catList,
			'thirdPartyPrivate' => $thirdPartyPrivate,
			'thirdPartyPrivateMember' => $thirdPartyPrivateMember,
			'thirdParty' => $thirdParty,
			'openModeratorPopup' => $this->input->get('moderator', FALSE),
			'isUserUnderage' => $underage,
			'requireAgeCheck' => $requireAgeCheck,
			'groupAttributes' => $this->subjectAttribute->getSubjectAttributesArray($forumId)
		));
	}

	private function generateSeoPageTitle($forumString, $postBodySnippet, $postTitle, $postName, $postType, $forumId, $forumType, $forumName) {

		// FORUM PAGE
		if(!empty($forumString)&&empty($postName)) {

			// RF = forum name + | ramblur
			// RF Main feed = main feed + | ramblur
			// NRF = forum name + forum (no length restrictions yet, so "black and white one legged albanian cartoonists association of australia forum" is subject to the whims of the search engines truncation)

			$pageTitle = ($forumId==='714') ? str_replace(' forum', '', $forumString) : str_replace('official ramblur', '', $forumString).' forum';

			// ramblur forum's have this appended
			if($forumType==='0') {
				$pageTitle .= ' | ramblur';
			}

		// SINGLE POST
		} else {

			// which text is used, depends on type
			$textForTitle = ($postType==='post') ? $postBodySnippet : $postTitle;

			// POST or QUESTION
			if ($postType==='post'||$postType==='question') {

				// Setup some values for final construction depending on type (RF or NRF):
				// single post RAMBLUR forum
				if($forumType==='0') {

					$textMaxLen = 50;
					$textSubstrNum = 47;
					$textForTitleSuffix = ' | ramblur';

				// single post NRF
				} else {

					$textMaxLen = 60;
					$textSubstrNum = 57;
					$textForTitleSuffix = ' | '.$forumName.' forum';

				}

			// W/A ITEM [Article/Blog/Event/Review/Vlog]
			} else {

				// figure out here the length of the postType so that the $textMaxLen can be increased
				// eg. blog = 4, article = 7, so blog can have 3 more chars on the title

				// RAMBLUR FORUM
				if($forumType==='0') {

					$textMaxLen = 40;
					$textSubstrNum = 37;
					$textForTitleSuffix = ' | '.$postType.' | ramblur';

				// NRF
				} else {

					$textMaxLen = 50;
					$textSubstrNum = 47;
					$textForTitleSuffix = ' | '.$postType.' | '.$forumName. ' forum';

				}

			}

			// if there's more text that the length allowed, cut it down
			if(mb_strlen($textForTitle)>$textMaxLen) {
				$textForTitle = mb_substr($textForTitle, 0, $textSubstrNum).'...';
			}

			// DONE!
			$pageTitle = $textForTitle.$textForTitleSuffix;


		}

		return trim($pageTitle);

	}

	private function generateSeoMetaDescription($description, $forumString, $postBodySnippet) {

		if(!empty($description)&&empty($postBodySnippet)) {

			$metaDesc = $description; // when a forum has a description (eg. crabtopia)

		} else {

			// forum
			if(!empty($forumString)) {

				$metaDesc = (!empty($postBodySnippet)) ? $postBodySnippet : 'Read the latest posts and current topics on the '.$forumString.' forum';

				if(mb_strlen($metaDesc)>300){
					$metaDesc .= '...';
				}

			} else {

				// default
				$metaDesc = 'ramblur - A social media platform that allows you to follow all of your interests in one place';
				
			}

		}

		return $metaDesc;

	}

	private function checkPostName($postName, $userId, $forumId) {

		$myPID = explode('-', $postName);

		// formerly checkPostName stored procedure
		$where = array(
			'Post.visible' => true,
			'Nodes.parentNodeId' => 0,
			//'Subject.private' => false, // privacy is irrelevant here, as access has already been checked. We just want the post result
			'Post.postId' => $myPID[count($myPID)-1]
		);

		$this->db->select('Post.postId as postId, Post.subjectId, Post.title, Post.postType, Nodes.nodeId, Nodes.text, Subject.subjectName, Subject.type')
			->from('Post')
			->join('Nodes', 'Post.postId = Nodes.postId')
			->join('Subject', 'Post.subjectId = Subject.subjectId')			
			->where($where)
			->where('(postType="post" OR postType="question" OR postType="article" OR postType="blog" OR postType="review" OR postType="event" OR postType="vlog")');
		$result = $this->db->get();
		$row = $result->first_row('array');

		if (!isset($row["postId"]) || $row["subjectId"] != $forumId) {
			return false;
		}

		// Check for an image attachment
		$postImage = $this->checkForImageAttachment($row["nodeId"]); // returns '' or filename

		return array(
			"postName" => $postName,
			"postTitle" => ($row["postType"]==='post') ? ucwords($row["subjectName"].' '.$row["title"]) : mb_substr($row["title"], 0, 100),
			"postType" => $row["postType"],
			"postImage" => $postImage,
			"postId" => $row["postId"],
			"postText" => mb_substr($row["text"], 0, 300)
		);

	}

	// ADDED CJT - 0917
	// TODO: Only check public forums here, so that $wantPrivate doesn't need to be passed to ogMeta in the forumHeader view
	private function checkForImageAttachment($nodeId) {

		$where = array(
			'ContentToNodes.nodeId' => $nodeId,
			'Content.modDeleted' => 0,
			'ContentToNodes.deleted' => 0,
			'Content.fileType' => 'photo' // TODO: Consider other file types here?  (eg. link - they're screenshot images)
		);

		$this->db->select('Content.fileName')
			->from('Content')
			->join('ContentToNodes', 'Content.fileName = ContentToNodes.fileName')
			->where($where)
			->order_by('Content.timeStamp', 'ASC')
			->limit(1);
		$result = $this->db->get();
		$row = $result->first_row('array');

		return ($result->num_rows()===1) ? $row['fileName'] : '';

	}

}
