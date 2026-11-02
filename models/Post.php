<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Post extends CI_Model {

  public $workareaTypes = ['article', 'blog', 'event', 'review', 'vlog'] ;
  public $publicPostTypes = [ 'article', 'blog', 'event', 'review', 'vlog', 'post','question' ] ;

  function __construct() {
    // Call the Model constructor
    parent::__construct();

    $this->load->model('email');
  }
  
  function getBlacklist() {
  	return array('mofo', 'shit');
  }

  // this might be a duplicate, or have a better home, but for now, here it is! cjt 171116
  function getSubjectNameById ($subjectId) {

		$this->db->select('subjectName')->from('Subject')->where('subjectId', $subjectId);
		return ($result = $this->db->get()->row()) ? $result->subjectName : ''; // failure = empty string, or false? spose drm, front end can interpret...

  }
  
	/**
	 * Finds the subject id for the given post. If the post does not exist, returns false.
	 *
	 * @param int $postId
	 * @return boolean|int
	 */
	public function getSubjectId($postId) {
		$this->db->select('subjectId')
			->from('Post')
			->where('postId', $postId);
		$result = $this->db->get();
		if($result->num_rows() == 0) {
			return false;
		} else {
			return $result->first_row('array')["subjectId"];
		}
	}

	/**
	 * Creates a new post or reply to an existing post. Not used for messages, but should be used for all
	 * other post types: article, blog, comment, event, newsPost, post, question, review, vlog
	 */
	
	function storeNode($data) {
		
if(!isset($data["sendModNotifications"])) {
	$data["sendModNotifications"] = false;
}

		// check if the user is logged in
		$userId = $this->user->authCheck();

		if (!$userId) {
			return false;
		}

		$this->load->helper(array('htmlpurifier', 'censor','ramblurfiltervar'));
		
		// Validate data
		if (!preg_match("/^[\x30-\x39]{4}-(\x30[\x31-\x39]|\x31[\x30-\x32])-(\x30[\x31-\x39]|[\x31-\x32][\x30-\x39]|\x33[\x30-\x31])/u",$data['startTime'])){
			$data['startTime'] = '1000-01-01 00:00:00';
		}
		if (!preg_match("/^[\x30-\x39]{4}-(\x30[\x31-\x39]|\x31[\x30-\x32])-(\x30[\x31-\x39]|[\x31-\x32][\x30-\x39]|\x33[\x30-\x31])/u",$data['endTime'])){
			$data['endTime'] = '1000-01-01 00:00:00';
		}

		/*if($data['author']=='self'){
			$data['author'] = $this->user->getAuthUserName();
		}*/

		if (isset($data["parent"]) && !empty($data["parent"]) && rfilter_var($data["parent"],FILTER_VALIDATE_INT,true)===false){
			return false;
		}
		
		$subjectId = $this->group->findByName($data['group']);

		// Censor and html purify user input text
		if (!isset($data["subject"]) || $data["subject"] == '') {
			$data["subject"] = "Post";
		} else {
			$data["subject"] = mb_substr(censor_text(html_purify($data["subject"], 'post')), 0, 160); // backend limit to 100 -> 160 chars for question/subject - cjt
		}
		$data["body"] = censor_text(html_purify($data["body"], 'post'));
		$data["type"] = censor_text(html_purify($data["type"], 'post'));
		$data["author"] = censor_text(html_purify($data["author"], 'post'));
		$data["about"] = censor_text(html_purify($data["about"], 'post'));
		$data["group"] = censor_text(html_purify($data["group"], 'post'));
		
		// If it's a news post, check if they're allowed to manage that subject
		if ($data["type"] === 'newsPost') {
			if (!$this->user->canManageSubject($userId, $subjectId)) {
				return false;
			}
		}
		
		// event timezones
		if ($data["type"] === 'event' && !empty($data["timezone"])) { //and timezone is not null !!!!!!jbevent
			$this->load->model("Geo");
			$data["startTime"] = $this->Geo->convertToUTC($data["startTime"], $data["timezone"])->format('Y-m-d H:i:s');
			$data["endTime"] = $this->Geo->convertToUTC($data["endTime"], $data["timezone"])->format('Y-m-d H:i:s');
		}

		// set timezone to null if timezone not present in data
		if(!isset($data['timezone'])) {
			$data['timezone'] = NULL;
		}

		if (!isset($data["user"]) || empty($data["user"]) || !filter_var($data["user"],FILTER_VALIDATE_INT)) {
			$data["user"] = 0;
		}

		if (!empty($_SERVER['HTTP_X_FORWARDED_FOR']) && (filter_var($_SERVER['HTTP_X_FORWARDED_FOR'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) || filter_var($_SERVER['HTTP_X_FORWARDED_FOR'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6))) {
			$ip=$_SERVER['HTTP_X_FORWARDED_FOR'];
		} else {
			$ip=$_SERVER['REMOTE_ADDR'];
		}
	
		if ($data['type'] === 'comment' && $data['group'] === '') {
			$data['group'] = $this->getSubjectId($data['commentedPostId']);
		}

		// Is this a new post, question or message?
		if (!isset($data["post"]) || $data["post"] == 'undefined' || $data["post"] == '' || $data["post"]==NULL) {
			$query12="CALL addPost(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,@newPostId12,@newNodeId12);";
			if ($data["type"] === 'post_ad') {
				$data["group"] = 'my feed';
			}
			// might want to pass, or only have group as id
			if(is_numeric($data['group'])) {
				$data['group'] = $this->getSubjectNameById($data['group']);
			}
			
			// Check user can write to this forum. Stored procedure checkNodeWriteAccess
			// doesn't block users who aren't following a public forum.
			if ((!$this->user->adminCheck() && !$this->user->isForumSubscribed($subjectId)) || $this->subject->isBannedFromForum($userId, $subjectId)) {
				return false;
			}
			
			$data["commentedPostId"] = (isset($data["commentedPostId"]) && $data['commentedPostId'] != '') ? $data["commentedPostId"] : NULL;
			$data["fileName"] = (isset($data["fileName"])) ? $data["fileName"] : NULL;
			// NB: $data["group"] below is a *name* - addPost does id lookup
			$params12=array($userId,$data["group"],$data["category"] === '' ? 0 : $data['category'],$data["subject"],$data["body"],$data["type"],$data["author"],$data["about"],$data["startTime"],$data["endTime"],$data["timezone"],$ip,$data["fileName"],$data["commentedPostId"], $this->user->isDraftUser($userId));
			$query12 = $this->db->query($query12, $params12);
			
			$newIds = $this->db->query("SELECT @newPostId12 AS 'postId', @newNodeId12 AS 'nodeId'")->first_row('array');
			$postId = $newIds['postId'];
			$nodeId = $newIds['nodeId'];
			
			//check if a reported user, and if so, generate a content report for the node
			if (!empty($nodeId)) {
				$this->load->model('reported_content');
				if ($reportingUserId = $this->reported_content->isReportedUser($userId, $subjectId)) {
					$this->reported_content->createReport(
							array('reason' => 'AUTO',
						'reportingUserId' => $reportingUserId,
						'id' => $nodeId,
						'contentType' => 'node'), $error);
				}
			}

			// nb. this code below ought to be within the if node/post aren't null test?! cjt
			// url validated and html_purified in storePostAd, postAdVotingText1/2 html_purified in same
			if ($data["type"] === 'post_ad') {
				$this->storePostAd($userId, $postId, $nodeId, $data["postAdId"], $data["url"], $data["postAdVotingText1"], $data["postAdVotingText2"]);
			}
			// Notify subscribers if this is a new blog/vlog in a series
			if ($data["type"] === 'blog' || $data["type"] === 'vlog') {
				if ($subscribers = $this->anySubscribers($postId, $this->user->authCheck(), $data['type'])) {
					$subscription_params = array($userId, $subscribers[0]['subjectName'], $data["category"] === '' ? NULL : $data["category"],$data["subject"], $data["body"], 'subscription_msg', $data["author"], $data["about"], $data["startTime"], $data["endTime"],$data["timezone"],$ip, '', null, 0);
					$subscriptionPostId = $this->addPost($subscription_params);
					$this->db->update('Post', array('systemMsg' => 1), array('postId' => $subscriptionPostId));
						
					//need to update userToSubscriptionMsg here with the subscribed user id and the postId
					foreach ($subscribers as $subscriber) {
						$this->updateUserToSubscriptionMsg($subscriptionPostId, $subscriber['userId'], $postId, 0);
					}
				}
			}
			
			//if moderator, then send a new content notification to forum members
			//if($this->user->isModeratorSubject($userId, $subjectId) && $data["type"] !== "comment" && $data["sendModNotifications"]) {
				//$this->load->model('email');
				$this->email->sendNewModContentNotificationEmail($postId, $nodeId, $subjectId);
			//}
	
			if(!is_null($nodeId) && !is_null($postId)) {
				$this->load->library('siteanalytics');
				$this->siteanalytics->trackEvent($data["type"], "New", $data["subject"]);
	
				// SEND A COMMENT NOTIFICATION MESSAGE - cjt (commentedPostId should be reliable from the line where this is set, above)
				if(!is_null($data['commentedPostId'])) $this->sendCommentNotification($data['commentedPostId'], $nodeId, $postId);
	
				return array('nodeId' => $nodeId, 'postId' => $postId);
			}else{
				return false;
			}
			
		
		// Update existing post
		} else {
			
			// Check user can write to this forum. Stored procedure checkNodeWriteAccess
			// doesn't block users who aren't following a public forum.
			$subjectId = $this->getSubjectId($data['post']);
			if ((!$this->user->adminCheck() && !$this->user->isForumSubscribed($subjectId)) || $this->subject->isBannedFromForum($userId, $subjectId)) {
				return false;
			}
			
			$ts = date('Y-m-d H:i:s');
			$query = "CALL addNode(?,?,?,?,?,?,?,false,?,@newNodeId)";
			$params=array($this->user->isDraftUser($userId), $data["post"],$userId,$data["type"],$ts,$data["body"],$data["parent"],$ip);
			$query = $this->db->query($query, $params);
			$nodeId = $this->db->query("SELECT @newNodeId AS 'nodeId'")->first_row('array')['nodeId'];
			
			//check if a reported user, and if so, generate a content report for the node
			if (!empty($nodeId)) {
				$this->load->model('reported_content');
				if ($reportingUserId = $this->reported_content->isReportedUser($userId, $subjectId)) {
					$this->reported_content->createReport(
							array('reason' => 'AUTO',
						'reportingUserId' => $reportingUserId,
						'id' => $nodeId,
						'contentType' => 'node'), $error);
				}
			}

			if($this->user->isDraftUser($userId)) {
				$this->group->updateDraftPostReplyCount($data["post"]);
			} else {
				$this->group->ifValidDraftPostKillReplies($data["post"]);
			}
			//If this is post can be subscribed to, then check for any subscribers.
			//If there is at least one subscriber, create a subscription post  
			
			if ($data["type"] === 'post' 
				|| $data["type"] === 'article' 
				|| $data["type"] === 'blog' 
				|| $data["type"] === 'question' 
				|| $data["type"] === 'vlog'
				|| $data["type"] === 'event'
				|| $data["type"] === 'review'
			) {
			
				if ($subscribers = $this->anySubscribers($data["post"], $this->user->authCheck(), $data['type'])) {
				
					$subscription_params = array($userId, $subscribers[0]['subjectName'], $data["category"],$data["subject"], $data["body"], 'subscription_msg', $data["author"], $data["about"], $data["startTime"], $data["endTime"],$ip, '', null);
					$subscription = $this->addPost($subscription_params);
				
					//need to update userToSubscriptionMsg here with the subscribed user id and the data[post]
					foreach ($subscribers as $subscriber) {
						$this->updateUserToSubscriptionMsg($subscription[0]['newPostId'], $subscriber['userId'], $data['post'], 0);
					}
				}
			}
			
			if($data["type"] !== "comment") {
				$this->load->model('email');
				$this->email->sendReplyNotificationEmail($data["parent"], $subjectId); 
			}
			
			if (!is_null($nodeId)) {
				return array('nodeId' => $nodeId, 'postId' => $data["post"]);
			} else {
				return false;
			}
		}
	}

	/**
	 * Deals with sending or incrementing an existing comment notification message
	 * 
	 * @param int $cpId - (commentedPostId)
	 * @param int $nodeId
	 * @return array
	 */

	function sendCommentNotification ($cpId, $nodeId, $commentPostId='') {
                 
		if($userId = $this->user->authCheck()) {
			
			// get toUser & postType from the OP for new system message
			$this->db->select("userId, title, postType, numComments")
				->from('Post')
				->where('postId', $cpId);
			$sql = $this->db->get();

			$post = $sql->row();				
			$toUser = $post->userId;
			$title = $post->title;
			$postType = $post->postType;
			$numPostComments = $post->numComments;
			
			$this->db->select("Post.fileName AS fileName, Content.fileType AS fileType, Content.numComments")
				->from('Post')
				->where('postId', $commentPostId)
				->join('Content', 'Post.fileName = Content.fileName', 'LEFT');	
			$sql2 = $this->db->get();
			
			$post2 = $sql2->row();
			$postFileName = $post2->fileName;
			$postFileType = $post2->fileType;
			$fileNameNumComments = $post2->numComments;
			
			// only start doing stuff if it's not me commenting on my own post
			if($userId!==$toUser) {
				
				// get this user's avatar, regardless of whether this is the 1st comment or not
				$this->db->select("avatar")
					->from('User')
					->where('userId', $userId);
				$avatar = ($this->db->get()->row()->avatar);

				// if an avatar isn't set, avatar is NULL in user table, so insert something to play with
				if(empty($avatar)||is_null($avatar)) $avatar = 'avatar-'.$userId;

				// get the subjectId :\
				$subjectId = $this->node->getSubjectId($nodeId);

				// Check for an unread Message related to this post
				$this->db->select("mtc.messageId, mtc.commenters")
					->from('MessagesToComments mtc')
					->join('MessagesToUser mtu', 'mtu.messageId = mtc.messageId', 'left')
					->where('commentedPostId', $cpId)
					->where('mtu.msgStatus', '0');
				$sql = $this->db->get();

				$existingComment = ($sql->num_rows()===1) ? true : false; // 1 row = prior unread comments = true, there are existing comments

				if($existingComment) {

					$row = $sql->row();				
					$messageId = $row->messageId;
					$existingCommenters = $row->commenters;

					// Unserialize avatars, add new avatar
					$commentersArray = unserialize($existingCommenters);
					// ...but only if the avatar hasn't already commented
					// ...and not if you're commenting on your own thing
					// ...and we only display up to 15 (5 x 3) avatars
					if(count($commentersArray)<15){
						if(!in_array($avatar, $commentersArray)) {
							$commentersArray[] = $avatar;
						}
					}
					// Reserialize
					$updatedCommenters = serialize($commentersArray);

					// UPDATE MessageToUser with incremented numComments/new commenters
					$this->db->where('messageId', $messageId);
					$this->db->set('numComments', 'numComments + 1', FALSE);
					$this->db->set('commenters', $updatedCommenters);
					$this->db->update('MessagesToComments'); 

				} else {

					$messageData = [
						'commentNotification' => $cpId,
						'avatar' => $avatar,
						'title' => $title,
						'postType' => $postType
					];
					
					$this->message->systemMessage($userId, $toUser, $subjectId, message::MSG_COMMENT_NOTIFICATION, $messageData);

				}

			}
			
			//update the Post comment count unless the comment is against an image or video
		if((empty($postFileName) || $postFileName == 'null') || ($postType === 'vlog' && $postFileType === 'link')) { //can be the string null!
				$this->db->set('numComments', $numPostComments + 1)
						->where('postId', $cpId)
						->update('Post');
			} //else {
	
			//@todo note , see if there is a bug when re-commenting on vlogs etc before a refresh.
			if(!empty($postFileName) || $postFileName !== 'null') { //can be the string null!	
				
			//	$this->db->select("numComments")
			//			->from('Content')
			//			->where('fileName', $postFileName);
			//	$sql3 = $this->db->get();
			
			//	$post3 = $sql3->row();
			//	$fileNameNumComments = $post3->numComments;
				//$postFileType = $post2->fileType;
				
				$this->db->set('numComments', $fileNameNumComments + 1)
						->where('fileName', $postFileName)
						->update('Content');
			}
							

		}
		return false;

	}

	/**
	 * Retrieves all post ids for all admin created posts directed at a user.
	 * 
	 * @param int $userId
	 * @return array
	 */
	function getUserToAdminPosts($userId) {
		$this->db->select("u.id, u.userId")
			->from('UserToAdminPost AS u')
			->join('Post AS p', 'p.postId = u.postId')
			->where('u.userId', $userId)
			->where('p.visible', 1)
			->order_by('u.readIt ASC, p.timeStamp DESC');
		return $this->db->get()->result_array();
	}
	
	/**
	 * Retrieves details for an admin post that has been targeted to a user.
	 * 
	 * @param int $userId
	 * @return boolean|array
	 */
	function getUserToAdminPost($id) {
		$this->db->select('a.userId, a.postId, n.nodeId, a.readIt, u.userName')
			->from('Nodes AS n')
			->join('UserToAdminPost AS a', 'a.postId = n.postId')
			->join('User AS u', 'u.userId = a.userId')
			->join('Post AS p', 'p.postId = a.postId')
			->where(array(
				'a.id' => $id,
			));
		$post = $this->db->get();
		if($post->num_rows() == 0) {
			return false;
		} else {
			return $post->result_array()[0];
		}
	}

	/**
	 * Sets the readIt value for a a UserToAdminPost row matching the provided userId and postId.
	 * 
	 * @param int $postId
	 * @param int $userId
	 * @param boolean $value
	 */
	function setUserToAdminPostReadIt($id, $value) {
		$this->db->update(
			'UserToAdminPost',
			array('readIt' => $value),
			array('id' => $id)
		);
		return $this->db->affected_rows() > 0;
	}
	
	/**
	 * Returns enough information for the given node that the thread/post can be retrieved.
	 * 
	 * @param int $nodeId
	 * @return boolean|array
	 */
	function getNewsPost($nodeId, $getDeviceInfo = false) {
		$this->db->select("n.nodeId, n.postId, n.userId, n.reason")
			->from('Nodes AS n')
			->join('Post as p', 'p.postId = n.postId')
			->where(array(
					'n.nodeId' => $nodeId,
					'p.postType' => 'newsPost'
			));
		$node = $this->db->get();
		if($node->num_rows() == 0) {
			return false;
		} else {
			$node = $node->first_row('array');
			$post = $this->group->post($node['postId'],$nodeId,$this->user->authCheck(),0,0,'post', NULL, true, $getDeviceInfo);
			$defaultTheme = $this->group->getDefaultThemeColourOption()['value'];
			$defaultFontColour = $this->group->getDefaultThemeFontOption()['value'];
			// Override colours to default
			$post['theme'] = $defaultTheme;
			$post['opacity'] = group::DEFAULT_OPACITY;
			$post['primaryColour'] = group::DEFAULT_PRIMARYCOLOUR;
			$post['secondaryColour'] = group::DEFAULT_SECONDARYCOLOUR;
			$post['primaryFontColour'] = $defaultFontColour;
			$post['secondaryFontColour'] = $defaultFontColour;
			for($i = 0; $i < count($post['children']); $i++) {
				$post['children'][$i]['theme'] = $defaultTheme;
				$post['children'][$i]['opacity'] = group::DEFAULT_OPACITY;
				$post['children'][$i]['primaryColour'] = group::DEFAULT_PRIMARYCOLOUR;
				$post['children'][$i]['secondaryColour'] = group::DEFAULT_SECONDARYCOLOUR;
				$post['children'][$i]['primaryFontColour'] = $defaultFontColour;
				$post['children'][$i]['secondaryFontColour'] = $defaultFontColour;
			}
			return $post;
		}
	}
	
	function setNewsPostVisibility($nodeId, $value) {
		$this->db->update('Nodes',
			array('visible' => $value),
			array('nodeId' => $nodeId));
		return $this->db->affected_rows() > 0;
	}
	
	/**
	 * Handles adding censor markup to text within a node (title, body or attachment descriptions).
	 * Checks that the original text isn't changed, and will only be censored by strikethrough.
	 * Returns true if all text given was successfully edited, false otherwise.
	 * 
	 * @param int $nodeId
	 * @param string | null $text
	 * @param string | null $title
	 * @param array $attachments
	 * @param $error - provided by reference so error can be checked by the caller
	 * 
	 * @return boolean
	 */
	function censorText($nodeId, $text, $title, $attachments, &$error) {
	  $this->load->helper(array('htmlpurifier', 'censor', 'stripper'));
		
		$editedContent = array();
		if(!is_null($text)) {
			$editedContent[] = array('type' => 'text', 'text' => html_purify($text, 'post'), 'attachmentId' => NULL);
		}
		if(!is_null($title)) {
			$editedContent[] = array('type' => 'title', 'text' => html_purify($title, 'post'), 'attachmentId' => NULL);
		}
		if(!empty($attachments)) {
			foreach($attachments as $a) {
				$editedContent[] = array('type' => 'attachment', 'text' => html_purify($a['description'], 'post'), 'attachmentId' => $a["id"]);
			}
		}
		$editedCount = 0;
		foreach($editedContent as $e) {
			$existingContent = $this->node->getContent($nodeId, $e['type'], $e['attachmentId']);
			if(!$existingContent) {
				$error = 'The post could not be found.';
				break;
			} else {
				$uncensoredContent = remove_censor_markup($e["text"]);
				$uncensoredExistingContent = remove_censor_markup($existingContent);
					
				if($uncensoredContent !== $uncensoredExistingContent) {
					$error = 'You cannot change the contents of a post, only mark and unmark them as censored.';
				} else {
					if($this->node->updateContent($nodeId, $e['text'], $e['type'], 'moderator censored', $e['attachmentId'])) {
						$editedCount++;
					}
				}
			}
		}
		return $editedCount === count($editedContent);
	}
	
	/**
	 * Marks a post as having been deleted.
	 * Permissions should be checked before calling this.
	 * Used as part of forum moderation.
	 * Returns true if at least one row was updated.
	 * 
	 * @param int|string $postId
	 * @return boolean
	 */
	function markAsDeleted($postId) {
		return $this->updateModDeleted($postId, true);
	}
	
	/**
	 * Marks a post so that it is not considered to be deleted.
	 * Permissions should be checked before calling this.
	 * Used as part of forum moderation.
	 * Returns true if at least one row was updated.
	 *
	 * @param int|string $postId
	 * @return boolean
	 */
	function markAsNotDeleted($postId) {
		return $this->updateModDeleted($postId, false);
	}
	
	/**
	 * Updates modDeleted field of the Post row determined by postId
	 * 
	 * @param int|string $postId
	 * @param boolean $value
	 * @return boolean
	 */
	function updateModDeleted($postId, $value) {
		$this->db->update('Post', ['modDeleted' => $value], ['postId' => $postId]);
		return $this->db->affected_rows() > 0;
	}
	
	function getAllUserIds($postId) {
		$this->db->select("DISTINCT n.userId AS 'userId'")
			->from('Post AS p')
			->join('Nodes AS n', 'n.postId = p.postId')
			->where('p.postId', $postId);
		
		$result = $this->db->get();
		$userIds = [];
		
		if($result->num_rows() > 0) {
			foreach($result->result_array() as $r) {
				$userIds[] = $r['userId'];
			}
		}
		return $userIds;
	}
        
          /*
     * Find out if there is at leat one subscriber to the post 
     * and if so, get the subjectname
     * 
     */

    function anySubscribers($subscriptionId, $authorId, $postType) {
    	// Need to retrieve subscribers to any blog or vlog post from the same user with the same title in the same subject as the post with the provided id
    	if($postType === 'blog' || $postType === 'vlog') {
    		$this->db->select('subjectId, title')
    			->from('Post')
    			->where('postId', $subscriptionId);
    		$postResult = $this->db->get();
    		// post not found
    		if($postResult->num_rows() === 0) {
    			return false;
    		}
    		$postResult = $postResult->first_row('array');
    		$this->db->select('postId')
    			->from('Post')
    			->where(array(
    				'userId' => $authorId,
    				'title' => $postResult['title'],
    				'subjectId' => $postResult['subjectId'],
    				'postType' => $postType
    		));
    		$seriesResult = $this->db->get();
    		// no others blogs/vlogs, so just the provided one
    		if($seriesResult->num_rows() === 0) {
    			$this->db->where('subscriptionId', $subscriptionId);
    		// found blogs/vlogs in series
    		} else {
    			$postIds = array($subscriptionId);
    			foreach($seriesResult->result_array() as $r) {
    				$postIds[] = $r['postId'];
    			}
    			$this->db->where_in('subscriptionId', $postIds);
    		}
    	} else {
	        $this->db->where('subscriptionId', $subscriptionId);
    	}

        $this->db->select('s.userId, sub.subjectName')
        	->from('subscription AS s')
        	->join('Post AS p', 'p.postId = s.subscriptionId')
        	->join('Subject AS sub', 'sub.subjectId = p.subjectId');
        $query = $this->db->get();
        $subscribers = $query->result_array();

        if ($query->num_rows() > 0) {

            return $subscribers;
        } else {

            return FALSE;
        }
    }
    
    /**
     * Returns how many users are subscribed to the post. If the post is a blog or vlog, 
     * checks how many are subscribed to any blog/vlog in the series.
     * 
     * @param int|string $postId
     * @param string $postType
     * 
     * @return string
     */
    public function getSubscribedCount($postId, $postType) {
    	if($postType === 'blog' || $postType === 'vlog') {
    		$this->db->select("COUNT(DISTINCT s.userId) AS 'count'")
	    		->from('Post AS p')
	    		->join('Post AS t', 't.title = p.title and t.userId = p.userId and t.subjectId = p.subjectId AND p.postType = t.postType', 'left')
	    		->join('subscription AS s', 't.postId = s.subscriptionId')
	    		->where('p.postId', $postId);
    	} else {
    		$this->db->select("COUNT(subscriptionId) AS 'count'")
	    		->from('subscription')
	    		->where('subscriptionId', $postId);
    	}
    	return $this->db->get()->first_row('array')["count"];
    }
    
    /**
     * Returns true if the provided user is subscribed to the post provided, or is subscribed to any other
     * blog/vlog in the series.
     * 
     * @param int|string $postId
     * @param int|string $userId
     * @param string $postType
     * @return boolean
     */
    public function getSubscribed($postId, $userId, $postType = false) {
    	if(!$postType) {
    		$this->db->select('postType')
    			->from('Post')
    			->where('postId', $postId);
    		$post = $this->db->get();
    		if($post->num_rows() === 0) {
    			return false;
    		}
    		$postType = $post->first_row('array')['postType'];
    	}
    	if($postType === 'blog' || $postType === 'vlog') {
    		$this->db->select("COUNT(DISTINCT s.userId) AS 'subscribed'")
	    		->from('Post AS p')
	    		->join('Post AS t', 't.title = p.title and t.userId = p.userId and t.subjectId = p.subjectId AND p.postType = t.postType', 'left')
	    		->join('subscription AS s', 't.postId = s.subscriptionId')
	    		->where(array(
    				'p.postId' => $postId,
    				's.userId' => $userId
    		));
    	} else {
    		$this->db->select('COUNT(userId) AS subscribed')
    		->from('subscription')
    		->where(array(
    			'userId' => $userId,
    			'subscriptionId' => $postId
    		));
    	}
    	return (boolean)$this->db->get()->first_row('array')["subscribed"];
    }

    public function getEvents() {

        $timeStamp = strtotime("+1 days");

        $dayBegins = date('Y-m-d 00:00:00', $timeStamp);
        $dayEnds = date('Y-m-d 23:59:59', $timeStamp);

        return $this->db->select('*')
                        ->from('Post')
                        ->where('startTime >', $dayBegins)->where('startTime <', $dayEnds)->where('postType', 'event')
                        ->get()->result();
    }

   public function getPost($postId) {
			return $this->db->select('*')
                        ->from('Post')
                        ->where('postId',$postId)
                        ->get()->row_array();
    }
	
	/**
	 * Retrieves the posts associated with the given post ids
	 * @param array $postIds
	 * @return array of posts or boolean false on failure
	 */
	public function getPostsByIds(array $postIds) {
		if(empty($postIds)){
			return false;
		}
		
		$posts = [];
		// If there are too many postIds supplied CI dies when it puts them into
		// the IN statement, split them up and do multiple queries if there's more than 200 ids
		foreach (array_chunk($postIds, 200) as $chunkOfIds) {
			$this->db->select('*')
					->from('Post')
					->where_in('postId',$chunkOfIds) ;
			$result = $this->db->get() ;
			
			if ($result->num_rows () > 0) {
				$posts = array_merge($posts, $result->result_array());	
			} 			
		}
		if(!empty($posts)){
			return $posts;
		}
		return false;
		
    }
	
	/**
	 * For the given contentIds return postId,fileName
	 * @param type $contentIds
	 * @return boolean
	 */
	 public function getPostIdsWithFileNameFromContentIds($contentIds) {
		if(empty($contentIds)){
			 return false;
		}
		$this->db->select("Post.postId, ContentToNodes.fileName")
			->from("Post")
			->Join('Nodes', 'Post.postId = Nodes.postId')
			->join('ContentToNodes', 'Nodes.nodeId = ContentToNodes.nodeId')
			->where_in('ContentToNodes.fileName', $contentIds) ;
			$result = $this->db->get();
			if ($result->num_rows() > 0) {
			  return $result->result_array();			  
			}
			return false;
    }
	
    
    function updateUserToSubscriptionMsg($postId, $userId, $parentPostId, $reminderSent = 0) {
        
        $this->db->insert('userToSubscriptionMsg', array('userId' => $userId,
            'postId' => $postId,
            'msgStatus' => '0',
            'parentPostId' => $parentPostId,
            'reminderSent' => $reminderSent
        ));
    }

    function eventReminderSent($userId, $parentPostId) {

        $this->db->select('userId');
        $this->db->select('parentPostId');
        $this->db->from('userToSubscriptionMsg');
        $this->db->where('userId', $userId);
        $this->db->where('parentPostId', $parentPostId);
        $this->db->where('reminderSent', 1);
        $query = $this->db->get();

        if ($query->num_rows() > 0) {
            return TRUE;
        } else {
            return FALSE;
        }
    }

		function addPost($params) {

			$query = "CALL addPost(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,@newSubPostId,@newSubNodeId);";
			$query = $this->db->query($query, $params);
			$result = $this->db->query('SELECT @newSubPostId AS postId');
			if ($result->num_rows() > 0) {
				return $result->first_row('array')['postId'];
			} else {
				return FALSE;
			}
		}
        
        function storePostAd($userId, $postId, $nodeId, $parentAdId, $url, $postAdVotingText1, $postAdVotingText2) {

	  //NB. $url needs whitelisting for protocol and sanitising - done below
	  $this->load->helper(array('htmlpurifier', 'censor'));

	  $postAdVotingText1 = censor_text(html_purify($postAdVotingText1, 'post'));
	  $postAdVotingText2 = censor_text(html_purify($postAdVotingText2, 'post'));

	  $this->load->model('file');
	  $url=$this->model->checkURL($url);
	  if($url === false){return NULL;}

        $this->load->model('ad');

        $start_date = date('Y-m-d H:i:s');

        if (!empty($postId) && !empty($nodeId)) {
            $post_ad_ar = array(
                'userId' => $userId,
                'postId' => $postId,
                'nodeId' => $nodeId,
                'parentAdId' => $parentAdId,
                'url' => $url,
                'voteText1' => $postAdVotingText1,
                'voteText2' => $postAdVotingText2,
                'startDate' => $start_date,
                'endDate' => date('Y-m-d H:i:s', strtotime(date($start_date) . " + 1 year")),
                'booked' => 0
            );

            $this->db->insert('post_ads', $post_ad_ar);
            $this->ad->updatePostAdUrl($parentAdId, $url);
            $this->ad->updateAdVotingText(null, $parentAdId, $postAdVotingText1, $postAdVotingText2, 0);
            $this->ad->deactivatePostAd($parentAdId);
            //$this->ad->updateAdStatus($this->ad->getChildAdId($parentAdId, 'post')->adId, 'Active');
            
        } else {
            return NULL;
        }
    }

    // added cjt
	function getReplies($postId, $nodeId, $userId, $limit=0, $groupId=null){

		// get replies from post

		//check nodewhitelist if home feed (landing page)
		$groupId === "home" ? $sql = "CALL getBranchForLandingPage(?,4,?,?, 6);" : $sql = "CALL getBranch(?, 4, ?, ?, 6);";
	    $params = array($nodeId, $postId, $userId);
	    $sql = $this->db->query($sql, $params);

		$replydata = $sql->result_array();

		//print_r($replydata);
		//mysqli_next_result($this->db->conn_id); // added cjt - this helped clear a db error about 'can't run this command now'

		foreach($replydata as &$reply){
		
			// get icon statuses for all replies
			// ie. do i "like" this reply, and "am i following this person in this subject"
			// FUTURE DEV: Could this be moved into a subquery in getBranch?  Or moved out of getBranch? Something to make it more efficient....
			$statuses = $this->node->iconStatuses($reply["userId"], $reply["subjectId"], $reply['id'], $postId);
			$reply['liked'] = $statuses['liked'];
			$reply['following'] = $statuses['following'];

			// and are we subscribed info
			// this returns '0' for not logged in, so will work onwards in js
			$reply["isSubd"] = $this->user->isForumSubscribed($reply["subjectId"]);

			//mysqli_next_result($this->db->conn_id); // added cjt - this helped clear a db error about 'can't run this command now'
			// get any attachments
			//$sql = "CALL getNodeFiles(?, ?);";

			/*$sql = "SELECT Content.*, Content.fileType AS 'type', Content.description AS 'comment', s.private FROM Content JOIN ContentToNodes ON Content.fileName = ContentToNodes.fileName JOIN Subject as s ON s.subjectId = Content.subjectId WHERE ContentToNodes.nodeId = ? ORDER BY Content.timeStamp;";
			$params = array($reply['id']);
			$sql = $this->db->query($sql, $params);

			if($sql->num_rows()>0) $reply["attachments"] = $sql->result_array();*/

			$query = "CALL getNodeFiles(?,?);"; 
			$params = array($userId, $reply['id']);
			$query = $this->db->query($query,$params);

			if ($query->num_rows() > 0) {
				$reply["attachments"] = $query->result_array();
			}			

		}

		return $replydata;

	}

	function addContentToNodes($data){
		// born from mobile (now used by Attach.js when connecting new node to attachments - cjt 02/17)
		// adding attachment during "submit q/p" page, once q/p is successfully submitted, relate any "attachments" to the new node
		// july 16 - also now used from Feed/Reply/Thread & Workarea add new
		// filename, node id, user id

		//print_r($data);

		if($userId = $this->user->authCheck()) {

			$subjectId = $this->node->getSubjectId($data['nodeId']);
			if (!$subjectId) {
				return false;
			}
			$private = $this->group->getForumPrivate($subjectId);
			
			// an array of attachments from desktop
			if(isset($data['atts'])) {

				//print_r($data);

				$attnum = count($data['atts']);
				$attdun = 0; // attdun?! geddit?
				$this->load->helper(array('ramblurfilenamevalidate'));
				foreach($data['atts'] as $att) {

					// Kludge to set fileType = 'pinloc' after all the file processing has happened.
					// The backend image processing by the utility server requires fileType to be link,
					// so we retrospectively update the record here
					if (isset($att['fileName']) && isset($att['type'])) {
						if(!rfname_validate($att['fileName'])) {
							return false;
						}
						if (!preg_match('/^[A-Za-z]{1,16}$/',$att['type'])) {
							return false;
						}
						$this->db->update('Content', ['fileType' => $att['type']], ['fileName' => $att['fileName']]);
						$att = $att['fileName'];
					}
					
					$sql = "CALL addContentToNodes(?, ?, ?, ?);";
					$params = array($att, $data['nodeId'], $userId, $this->user->isDraftUser($userId));
					if ($sql = $this->db->query($sql, $params)) {
						if ($private) {
							$this->file->moveFilesToPrivate($att);
						}
						$attdun++;
					}
					
					//if a reported user, create a content report for the attachment
					$this->load->model('reported_content');
					if ($reportingUserId = $this->reported_content->isReportedUser($userId, $subjectId)) {
						$this->reported_content->createReport(
								array('reason' => 'AUTO',
							'reportingUserId' => $reportingUserId,
							'id' => $att,
							'contentType' => 'file'), $error);
					}
				}

				// CAPTIONS - also update (borrowed from node::updateNode)
				if (isset($data['comments'])&&!empty($data['comments'])) {

					$this->load->helper(array('htmlpurifier', 'censor', 'stripper'));

					for ($i=0; $i<count($data["comments"]); $i++) {

						$commarr = explode('_', $data['comments'][$i]);

						$comment = censor_text(mb_substr(stripper($commarr[0]),0,256));

						$query = "CALL updateContentComment(?, ?, ?, ?, false)";
						$params = array($userId, $data['nodeId'], $comment, $commarr[1]);
						$query = $this->db->query($query, $params);
					}
				}

				return $attdun;

			// a single attachment from mobile
			} else {

				$sql = "CALL addContentToNodes(?, ?, ?, ?);";
				$params = array($data['fileName'], $data['nodeId'], $data['userId'], $this->user->isDraftUser($userId));
				
				//if a reported user, create a content report for the attachment
				$this->load->model('reported_content');
				if ($reportingUserId = $this->reported_content->isReportedUser($userId, $subjectId)) {
					$this->reported_content->createReport(
							array('reason' => 'AUTO',
						'reportingUserId' => $reportingUserId,
						'id' => $data['fileName'],
						'contentType' => 'file'), $error);
				}

				if ($sql = $this->db->query($sql, $params)) {
					if ($private) {
						$this->file->moveFilesToPrivate($data['fileName']);
					}
					return 'y';  // experimenting with passing string back to js - PHP boolean values appear to be tricky to evaluate via json with 100% certainty
				}
				return 'n';
			}
		}
	}

	// the number of comments (replies) to a post
	function getReplyCount ($postId) {

		$query = $this->db->select('COUNT(nodeId) as count')
			->from('Nodes')
			->where('postId', $postId)
			->where('parentNodeId != 0')
			->where('(draftNodeVisible = 1 OR draftNode = 0)')
			->get()
			->row_array();
		return $query['count'];

	}
	
	function getDraftReplyCount ($postId) {
		
		$query = $this->db->select('COUNT(nodeId) as count')
				->from('Nodes')
				->where('postId', $postId)
				->where('parentNodeId != 0')
				->where('draftNodeVisible', 0)
				->get()->row_array();
		return $query['count'];

	}
	
	function getNextDraftPriorityDate ($postId) {
		
		$this->db->select('nodePriorityDate')
				->from('Nodes')
				->where('postId', $postId)
				->where('draftNode', 1)
				->where('draftNodeVisible', 0)						
				->where('nodePriorityDate !=', NULL)
				->order_by('nodePriorityDate ASC')
				->limit(1);
		
		$query = $this->db->get();

		if ($query->num_rows() > 0) {
			return $query->row()->nodePriorityDate;
		}
		return FALSE;		

	}
	
	function getPostSnippet ($postId) {

		$query = $this->db->select('text')
				->from('Nodes')
				->where('postId', $postId)
				->where('nodeType', 'rootNode');
		$query = $this->db->get();
		if ($query->num_rows() > 0) {
			$snippetBeforeTrunc = $query->row()->text;
			$snippet = substr($snippetBeforeTrunc,0,40);
			if(strlen($snippetBeforeTrunc) >= 40) {
				$snippet = $snippet . "...";
			}
			return $snippet;
		}
		return NULL;

	}
	
	function getReplyEmailNotificationData($nodeId) {
		
		if(!$this->user->authCheck()) {
			return false;
		}

		$query = $this->db->select(
				  'Nodes.text, '
				. 'Nodes.postId, '
				. 'Nodes.userId, '
				. 'Subject.subjectName, '
				. 'Subject.private, '
				. 'Subject.type, '
				. 'Subject.primaryColour, '
				. 'Subject.secondaryColour, '
				. 'u.primaryColour AS userprimaryColour, '
				. 'u.secondaryColour AS usersecondaryColour, ' 
				. 'ff.value AS themeFontPrimary, '
				. 'f.value AS themeFontSecondary, '
				. 'ContentToNodes.fileName, '
				. 'Content.fileType, '
				. 'Content.mimeType'
				)
				->from('Nodes')
				->join('Post', 'Nodes.postId = Post.postId')
				->join('Subject', 'Post.subjectId = Subject.subjectId')
				//->join('UserToSubject AS u', 'u.subjectId = Subject.subjectId AND u.active = 1 AND u.userId '.($nodeUserId ? '= ' . $nodeUserId : 'IS NULL'), 'LEFT')
				->join('UserToSubject AS u', 'u.subjectId = Subject.subjectId AND u.active = 1 AND u.userId = Nodes.userId', 'LEFT')
				->join('ThemeColourOptions AS t', 't.id = IF(u.themeColourOptionId IS NULL, Subject.themeColourOptionId, u.themeColourOptionId)', 'INNER', FALSE)
				->join('ThemeFontOptions AS f', 'f.id = IF(u.secondaryThemeFontOptionId IS NULL, Subject.secondaryThemeFontOptionId, u.secondaryThemeFontOptionId)', 'INNER', FALSE)
				->join('ThemeFontOptions AS ff', 'ff.id = IF(u.primaryThemeFontOptionId IS NULL, Subject.primaryThemeFontOptionId, u.primaryThemeFontOptionId)', 'INNER', FALSE)
				->join('ContentToNodes', 'Nodes.nodeId = ContentToNodes.nodeId', 'LEFT')
				->join('Content', 'Content.fileName = ContentToNodes.fileName', 'LEFT')
				->where('Nodes.nodeId', $nodeId);
		$query = $this->db->get();
		if ($query->num_rows() > 0) {
			$snippetBeforeTrunc = $query->row()->text;
			$snippet = substr($snippetBeforeTrunc,0,256);
			if(strlen($snippetBeforeTrunc) >= 256) {
				$snippet = $snippet . "...";
			}
			$returnArray = array(
								"snippet" => $snippet, 
								"subjectName" => $query->row()->subjectName, 
								"private" => $query->row()->private, 
								"type" => $query->row()->type,
								"primaryColour" => $query->row()->primaryColour,
								"secondaryColour" => $query->row()->secondaryColour,
								"userprimaryColour" => $query->row()->userprimaryColour,
								"usersecondaryColour" => $query->row()->usersecondaryColour,
								"themeFontPrimary" => $query->row()->themeFontPrimary,
								"themeFontSecondary" => $query->row()->themeFontSecondary,
								"mediaFileName" => $query->row()->fileName,
								"mediaFileType" => $query->row()->fileType,
								"mediaMimeType" => $query->row()->mimeType,
								"postId" => $query->row()->postId,
								"nodeUserId" => $query->row()->userId
							);
			return $returnArray;
		}
		return NULL;

	}
	

	function getTotalLivePostCountByForums(array $subjectIds) {		
		if(empty($subjectIds)){
			return false;
		}
		
		$postCounts = [];		
		foreach (array_chunk($subjectIds, 200) as $ids) {
			$query = $this->db->select('subjectId, COUNT(postId) as postCount')
						->from('Post')
						->where_in('subjectId', $ids)
						->where_in("postType",$this->publicPostTypes)
						->where("(visible=1 AND draftPost = 0 OR (draftPostVisible=1))")					
						->group_by("subjectId")					
						->get();

			if ($query->num_rows() > 0) {
				$postCountsChunk = $query->result_array();
				if (is_array($postCountsChunk)) {
						$postCounts = array_merge($postCounts, $postCountsChunk);
					}
				}
		}
		
        if (count($postCounts)) {
            return $postCounts;
        }
        return false;
        
		
	}
	
	function getTotaldraftPostCountByForum($subjectId) {
		$query = $this->db->select('COUNT(postId) as postCount')
					->from('Post')
					->where('subjectId', $subjectId)
					->where('draftPost',1)
					->where('draftPostVisible',0)
					->where('draftPostStatus',1)
					->get()->row_array();
		return $query['postCount'];
	}
	
	function getTotaldraftPostReplyCountByForum($subjectId) {
		$query = $this->db->select('SUM(draftPostReplyCount) as replyCount')
					->from('Post')
					->where('subjectId', $subjectId)
					->where('draftPost', 1)
					->where('draftPostStatus',1)
					->get()->row_array();
		return $query['replyCount'];
	}

	// added cjt - to get comment count for workarea types on 'device'
	// includes replies to comments
	// NB: also added to Group::post for accurate comment count (for both device & desktop) - cjt 080817
	function getCommentCount($postId) {

		$query = $this->db->select('COUNT(nodeId) as count')
			->from('Nodes n')
			->join('Post p', 'n.postId = p.postId')
			->where('commentedPostId', $postId)
			->get()
			->row_array();
		return $query['count'];


	}

	// the number of 'replies' to a workarea 'comment'
	function getCommentReplyCount($postId) {

		$query = $this->db->select('COUNT(*) as count')->from('Nodes')->where('postId', $postId)->where('parentNodeId != 0')->get()->row_array();
		return $query['count'];		

	}

	// the number of subscribers to a post (eg. blog/vlog/etc)
	function getSubscriberCount ($postId) {
		 $this->load->helper(array('ramblurfiltervar'));
	         if( !isset($postId) || rfilter_var($postId,FILTER_VALIDATE_INT,false)===false){
	           return false;
	        }
		//SELECT COUNT(*) as 'count' FROM subscription WHERE subscriptionId = 5263
		$query = $this->db->select('COUNT(*) as count')->from('subscription')->where('subscriptionId', $postId)->get()->row_array();
		return $query['count'];

	}
        
	// remove a pinloc from a post
	function deletePinloc($nodeId) {

		if($userId = $this->user->authCheck()) {

			/*
			NB:
			Ver 1 of pinlocs had a screenshot entry in ContentToNodes
			Remove if it's there
			*/
			$this->db->select('c.fileName, s.private')
				->from('ContentToNodes AS n')
				->join('Content AS c', 'n.fileName = c.fileName')
				->join('Subject AS s', 's.subjectId = c.subjectId')
				->where(
					['n.nodeID' => $nodeId,
					'c.fileType' => 'pinloc',
					'c.userId' => $userId
					]);
			$fileResult = $this->db->get();
			
			if ($fileResult->num_rows() > 0) {
				$fileDetails = $fileResuls->first_row('array');
				$fileName = $fileDetails['fileName'];
				
				$this->file->removeFiles($fileName, $fileDetails['private']);
				
				$this->db->where(['nodeID' => $nodeId, 'fileName' => $fileName]);
				$query = $this->db->delete('ContentToNodes');
				
				$this->db->where('fileName', $fileName);
				$query = $this->db->delete('Content');
			}

			$this->db->where(['nodeId' => $nodeId, 'userId' => $userId]);
			$query = $this->db->delete('UserToMapMarker');

			return $this->db->affected_rows() > 0;
		}
		return false;
	}
}
