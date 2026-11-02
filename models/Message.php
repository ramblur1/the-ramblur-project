<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Message extends CI_Model {

	/***** System message types *****/
	const MSG_MODERATOR_ASSIGNED		= 0;
	const MSG_MODERATOR_UNASSIGNED		= 1;
	const MSG_FORUM_APPROVED 			= 2;
	const MSG_FORUM_DENIED 				= 3;
	const MSG_USER_WARNED 				= 4;
	const MSG_USER_BANNED 				= 5;
	const MSG_POST_CENSORED				= 6;
	const MSG_POST_ATTACHMENT_DELETED	= 7;
	const MSG_POST_DELETED				= 8;
	const MSG_POST_RESTORED				= 9;
	const MSG_THREAD_DELETED			= 10;
	const MSG_THREAD_RESTORED			= 12;
	const MSG_ISSUE_RESOLVED			= 13;
	const MSG_USER_UNBANNED 			= 14;
	const MSG_EXTREME_THANKYOU			= 15;
	const MSG_EXTREME_BAN				= 16;
	const MSG_EXTREME_IGNORE			= 17;
	const MSG_COMPLAINT_IGNORE			= 18;
	const MSG_COMPLAINT_ACTION			= 19;
	const MSG_WARNING_IGNORE			= 20;
	const MSG_POST_ATTACHMENT_RESTORED	= 21;
	const MSG_FORUM_CREATE				= 22;
	const MSG_FORUM_ADOPT				= 23;
	const MSG_MANAGER_SPIKE				= 24;
	const MSG_MODERATOR_NEEDED			= 25;
	const MSG_ADREPORT_PENDING			= 26;
	const MSG_ADREPORT_THANK			= 27;
	const MSG_ADREPORT_WARNED			= 28;
	const MSG_ADREPORT_BANNED			= 29;
	const MSG_ADREPORT_RESTORE			= 30;
	const MSG_ADREPORT_REPOST			= 31;
	const MSG_ADREPORT_REMOVE			= 32;
	const MSG_FILE_DELETED				= 33;
	const MSG_FILE_RESTORED				= 34;
	const MSG_FILE_CENSORED				= 35;
	const MSG_PAYMENTS_AUTH				= 36;
	const MSG_WELCOME					= 37;
	const MSG_BETAPHASE					= 38;
	const MSG_WELCOME_INVITE			= 39;
	const MSG_FORUM_INVITE				= 40;
	const MSG_COMMENT_NOTIFICATION		= 41;
	const MSG_USER_REMOVED_FROM_FORUM	= 42;

	/***** Inbox statuses ****/
	const MSG_UNSEEN	= "0";
	const MSG_SENT		= "1";
	const MSG_DONE		= "2";
	const MSG_DELETED	= "3";
	
	function __construct() {
		// Call the Model constructor
		parent::__construct();
	}
	
	/**
	 * Creates a message. Should be used for automated messages from other processes.
	 * 
	 * Note the group model requires loading before calling this function 
	 * 
	 * @param int $from - userId of the user the message is from
	 * @param int $to - userId of the user the message will be sent to
	 * @param int $subjectId - subjectId of the forum relating to the message
	 * @param int $msgType - type of message. Template is defined by this. eg. message::MSG_USER_BANNED
	 * @param array $data (optional) - associative array of the data required by the message template. See message::$msgText
	 * @param array $relatedObject (optional) - associative array of an object to relate the message to. If provided, must include 
	 *											id and type eg ["id"=>1, "type"=>"node"]
	 */
	function systemMessage($from, $to, $subjectId, $msgType, $data = array(), $relatedObject = null) {

		$data['subjectName'] = $this->group->getName($subjectId);
		
		$messageText = $this->getMsgTemplate($msgType, $data);
		if(!$messageText) {
			return false;
		}
		if(!is_array($to)&&!isset($data['commentNotification'], $data['avatar'])) { // we don't want $to to be an array in comment notification message mode cjt
			$to = array($to);
		}
		$subject = $messageText['subject'];
		$body = $messageText['body'];

		$createDate = date('Y-m-d H:i:s');
		$postData = array(
			'userId' => $from,
			'subjectId' => $subjectId,
			'title' => $subject,
			'timeStamp' => $createDate,
			'postType' => 'message',
			'author' => $from,
			'lastInput' => $createDate,
			'systemMsg' => 1
		);
		$this->db->insert('Post', $postData);
		$postId = $this->db->insert_id();
		
		$nodeData = array(
			'postId' => $postId,
			'userId' => $from,
			'nodeType' => 'rootNode',
			'dateTime' => $createDate,
			'text' => $body,
			'nv' => 0,
			'dv' => 1,
			'snv' => 1,
			'sdv' => 0,
			'parentNodeId' => 0,
			'level' => 0,
			'visible' => 1,
			'readIt' => 0
		);
		$this->db->insert('Nodes', $nodeData);
		$nodeId = $this->db->insert_id();
		
		// only ever one comment at a time and once only (other unread comments after are added via post.php)...cjt
		if(isset($data['commentNotification'], $data['avatar'])) {

			$commenters = serialize(array($data['avatar']));
			$this->db->insert('MessagesToUser', array('messageId' => $nodeId, 'toUser' => $to));
			$this->db->insert('MessagesToComments', array('messageId' => $nodeId, 'numComments' => 1, 'commenters' => $commenters, 'commentedPostId' => $data['commentNotification']));

		} else {

			// Insert messagesToUser row for each recipient
			$insertData = array();

			foreach($to as $toUser) {
				$insertData[] = array('messageId' => $nodeId, 'toUser' => $toUser);
			}
			$this->db->insert_batch('MessagesToUser', $insertData);

		}
		
		// Store related object in relevant table. 
		if(!is_null($relatedObject) && isset($relatedObject['id']) && isset($relatedObject['type'])) {
			// Add more options as more objects are supported eg. node, file etc.
			if($relatedObject['type'] === 'adArchive') {
				$this->db->insert('MessagesToAdArchive', array('messageId' => $nodeId, 'adArchiveId' => $relatedObject['id']));
			}
			if ($relatedObject['type'] === 'subjectInvite') {
				$this->db->update('SubjectInvite', ['messageId' => $nodeId], ['token' => $relatedObject['id']]);
			}
		}
		return $nodeId;
	}
	
	/**
	 * Builds the query string for retrieving messages with the given status eg. unread
	 * Both standard one-on-one messages and messages regarding
	 * reported node actions will be retrieved.
	 * @todo - include notifications
	 * 
	 * @param int $msgStatus
	 */
	private function getMessagesQuery($msgStatus) {

		return "SELECT 
			t.userId AS 'to',
			f.userId AS 'from',
			p.postId AS 'id',
			p.postId,
			p.postType AS 'type',
			GREATEST(COALESCE(m.date, 0), COALESCE(p.timeStamp, 0)) AS 'created',
			p.title AS 'subject',
			IF(p.postType = 'subscription_msg', s.msgStatus, m.msgStatus) AS 'rms',
			p.ownerMsgStatus AS 'oms',
			p.numViews,
			mc.numComments,
			mc.commenters,
			mc.commentedPostId,
			n.text AS 'body',
			n.level AS 'depth',
			n.nodeId AS 'headNodeId',
			CONCAT(t.avatar, '-avatar') AS 'toav',
			CONCAT(f.avatar, '-avatar') AS 'fromav',
			f.userName AS 'fromName',
			f.gender AS fromGender,
			f.age AS fromAge,
			f.DOB AS fromDOB,
			f.countryId AS fromCountry,
			t.userName AS 'toName',
			r.id AS 'reviewId',
			r.reportedContentId,
			IF(rc.id IS NULL, 0, 1) AS 'complaintMade',
			rc.reportedReviewId AS 'complaintReportedReviewId',
			IF(rc.resolvedReviewId = r.id, 1, 0) AS 'isComplaintResponse',
			p.systemMsg AS 'isSystemMessage',
			s.id AS 'subscriptionMsgId',
			aa.adArchiveId,
			ANY_VALUE(si.id) AS 'subjectInviteId'
			FROM Post AS p
			JOIN Nodes AS n ON p.postId = n.postId
			LEFT JOIN MessagesToUser AS m ON m.messageId = n.nodeId
			LEFT JOIN MessagesToComments AS mc ON mc.messageId = n.nodeId
			LEFT JOIN ReportedContentReviewsToUser AS r on r.messageId = n.nodeId
			LEFT JOIN ReportComplaints AS rc ON rc.reportedReviewId = r.id OR rc.resolvedReviewId = r.id
			LEFT JOIN userToSubscriptionMsg AS s ON s.postId = p.postId
			JOIN User AS f ON f.userId = p.userId
			JOIN User AS t ON t.userId = IF(p.postType = 'subscription_msg', s.userId, m.toUser)
			LEFT JOIN MessagesToAdArchive AS aa ON aa.messageId = n.nodeId
			LEFT JOIN SubjectInvite AS si ON si.messageId = n.nodeId
			WHERE 
			(p.postType = 'message' OR p.postType = 'subscription_msg')
			AND (t.userId = ? AND (CASE WHEN p.postType = 'message' THEN (m.msgStatus = '$msgStatus') 
				ELSE (s.msgStatus = '$msgStatus') END) 
				OR (f.userId = ? && p.ownerMsgStatus = '$msgStatus' && p.systemMsg = 0))
			AND n.level = 0
			AND f.userId NOT IN (SELECT blockId FROM UserToBlock WHERE userId = ?)
			AND t.userId NOT IN (SELECT blockId FROM UserToBlock WHERE userId = ?)
			ORDER BY created DESC";
	}
	
	// return the messages that belong to the user
	// mn mn Should messages be 'zingable'?  Do not need to worry about accuracy of retrieving 'headNodeId' if not.
	// mn mn messages does not yet retrieve attachments.  FIX THIS.

	function messages($msgStatus = 'unseen', $count = false, $getDeviceInfo = false) {

		$userId = $this->user->authCheck();
		if($userId == false){
			return false;
		}

		if(!defined('self::MSG_' . mb_strtoupper($msgStatus))) {
			return false;
		} else {
			$msgStatusValue = constant('self::MSG_' . mb_strtoupper($msgStatus));
		}

		//$userId = $this->user->authCheck();
		$userLevel = $this->user->getUserLevel($userId);
		
		$params = array($userId, $userId, $userId, $userId);

		$messageResults = $this->db->query($this->getMessagesQuery($msgStatusValue), $params);

		if($messageResults->num_rows() === 0) {
			if($count) {
				return 0;
			} else {
				return '0';
			}
		} else {
			$rawMessages = $messageResults->result_array();

			$messages = $this->processRawMessages($rawMessages);
			// Filter out any messages that belong in the management inbox, instead of the main inbox
			$messages = $this->filterManagementMessages($msgStatus, $userId, $userLevel, $messages);
			
			// No need to finish processing messages if only count is required.
			if($count) {
				return count($messages);
			}
			$messages = $this->finaliseMessages($messages, $getDeviceInfo);
			//print_r($messages);
			return $messages;
		}
	}
	
	/**
	 * Handles initial processing of messages
	 * 	Removes/adds required data based on message type.
	 * 	Retrieves content of review messages (ie. file or node info).
	 * 	Arranges tags for review messages.
	 * 
	 * @param array $rawMessages
	 * @return array
	 */
	private function processRawMessages($rawMessages) {
		$this->load->model(array('reported_content', 'group', 'node', 'file'));

		$messages = [];
		$reviewMessagesByReportedContentId = [];
		foreach($rawMessages as $r) {
			$msg['relatedAd'] = null;
			if(is_null($r['reviewId'])) { // is normal message
				$msg = $r;
				unset($msg['reviewId']);
				unset($msg['reportedContentId']);
				unset($msg['complaintMade']);
				unset($msg['isComplaintResponse']);
				unset($msg['complaintReportedReviewId']);
				$msg['isSystemMessage'] = (boolean)$msg['isSystemMessage'];
				// Provide ad info for drawing related ad
				if(isset($msg['adArchiveId']) && !is_null($msg['adArchiveId'])) {
					$this->db->select("adId AS 'id', adArchiveId, fileName AS 'media', deviceFileName, style, headerText AS 'title', footerText AS 'description'")
						->from('adArchive')
						->where('adArchiveId', $msg['adArchiveId']);
					$ad = $this->db->get();
					if($ad->num_rows() > 0) {
						$msg['relatedAd'] = $ad->first_row('array');
					}
				}
				// Provide forum info for drawing forum invitation
				if (isset($msg['subjectInviteId']) && !is_null($msg['subjectInviteId'])) {
					$this->db->select("i.id, i.result, s.subjectId, s.subjectName, s.description AS 'subjectDescription', s.type AS 'subjectType', s.private AS 'subjectPrivate'")
						->from('SubjectInvite AS i')
						->join('Subject AS s', 's.subjectId = i.subjectId')
						->where('i.id', $msg['subjectInviteId']);
					$invite = $this->db->get();
					if ($invite->num_rows() > 0) {
						$msg['relatedInvite'] = $invite->first_row('array');
						switch ($msg['relatedInvite']['result']) {
							case null:
								$msg['relatedInvite']['result'] = null;
								break;
							case group::SUBJECTINVITE_DECLINE:
								$msg['relatedInvite']['result'] = 'declined';
								break;
							case group::SUBJECTINVITE_ACCEPT:
								$msg['relatedInvite']['result'] = 'accepted';
								break;
							case group::SUBJECTINVITE_INVALID:
							default:
								$msg['relatedInvite']['result'] = 'invalid';
								break;
						}
						$msg['relatedInvite']['subjectType'] = $this->group->getTypeLabel($msg['relatedInvite']['subjectType']);
						$msg['relatedInvite']['subjectVisibility']= $this->subjectAttribute->getForumVisibility($msg['relatedInvite']['subjectId'], $msg['relatedInvite']['subjectPrivate']);
						
						unset($msg['relatedInvite']['subjectId']);
						unset($msg['relatedInvite']['subjectPrivate']);
					}
				}
				if(!empty($r['commentedPostId'])) {
					$msg['commenters'] = unserialize($r['commenters']);
				}
				$messages[] = $msg;
			} else {					// is review message - sort by reported node, then get node info and add messages as tags
				$reportId = $r['reportedContentId'];
				if(!isset($reviewMessagesByReportedContentId[$reportId])) {
					$reviewMessagesByReportedContentId[$reportId] = [];
				}
				$reviewMessagesByReportedContentId[$reportId][] = $r;
			}
			unset($msg['adArchiveId']);
			unset($msg['subjectInviteId']);
		}
		foreach($reviewMessagesByReportedContentId as $reportId => $tags) {
			$actualContent = $this->reported_content->getActualReportedContent($reportId);
			$msg = [];
			$msg['reportedContent'] = $this->{$actualContent['contentType']}->drawableInfo($actualContent['id']);
			if($actualContent['contentType'] === 'file') {
				$msg['id'] = $actualContent['id'];
				$msg['subject'] = 'The following content has been reported';
			} else {
				$msg['id'] = $msg['reportedContent']['postId'];
				$msg['subject'] = 'The following post has been reported';
			}
			$msg['reportId'] = $reportId;
			$msg['type'] = 'reviewMessage';
			$msg['rms'] = $tags[0]['rms']; // get status of any of the messages, as they will all be the same at this stage
			$msg['reviews'] = [];
			$msg['isSystemMessage'] = true;
			$orderedTags = array();
			// sort reviews by date, most recent first
			usort($tags, function($a, $b) {
				return strtotime($b['created']) - strtotime($a['created']);
			});
			// arrange tags so that a complaint review is above the original complaint
			foreach($tags AS $tag) {
				if($tag['isComplaintResponse']) {
					$orderedTags[$tag['complaintReportedReviewId']] = array($tag);
				} else {
					if (isset($orderedTags[$tag['reviewId']])) {
						$orderedTags[$tag['reviewId']][] = $tag;
					} else {
						$orderedTags[$tag['reviewId']] = array($tag);
					}
				}
			}
			foreach($orderedTags as $id => $complaintTags) {
				foreach($complaintTags as $t) {
					$review = [];
					$review['id'] = $t['reviewId'];
					$review['messageId'] = $t['postId'];
					$review['body'] = $t['body'];
					$review['date'] = $t['created'];
					$review['complaintMade'] = (boolean)$t['complaintMade'];
					$review['isComplaintResponse'] = (boolean)$t['isComplaintResponse'];
					$review['reviewerLevel'] = $this->user->getUserLevel($this->reported_content->getReviewerId($t['reviewId']));
					$msg['reviews'][] = $review;
				}
			}
			// set date of message as most recent of all reviews
			$msg['created'] = $msg['reviews'][0]['date'];
			$messages[] = $msg;
		}
		return $messages;
	}
	
	/**
	 * Filters out messages that belong in the management area of the user, rather than the normal inbox.
	 * 
	 * @param int $userId
	 * @param string $userLevel
	 * @param array $messages
	 * @return array
	 */
	private function filterManagementMessages($msgStatus, $userId, $userLevel, $messages) {
		if ($userLevel !== user::USER_STANDARD) {
			$msgFunction = $userLevel.'Messages';
			if (method_exists($this, $msgFunction)) {
				$managementMessages = $this->$msgFunction($msgStatus, $userId);
				$managementMessageIds = [];
				foreach($managementMessages as $m) {
					if($m['type'] == 'reviewMessage') {
						foreach ($m['reviews'] as $r) {
							$managementMessageIds[] = $r['messageId'];
						}
					} else {
						$managementMessageIds[] = $m['postId'];
					}
				}
				$inboxMessages = [];
				foreach($messages as $m) {
					if($m['type'] == 'reviewMessage') {
						$found = false;
						foreach($m['reviews'] as $r) {
							if(in_array($r['messageId'], $managementMessageIds)) {
								$found = true;
							}
						}
						if(!$found) {
							$inboxMessages[] = $m;
						}
					} else {
						if(!in_array($m['id'], $managementMessageIds)) {
							$inboxMessages[] = $m;
						}
					}
				}
				$messages = $inboxMessages;
			}
		}
		return $messages;
	}
	
	/**
	 * Handles the final processing of messages - sorting and adding replies.
	 * 
	 * @param array $messages
	 * @return array
	 */

	private function finaliseMessages($messages, $getDeviceInfo) {

		// sort by most recent first
		usort($messages, function($a, $b) {
			return strtotime($b['created']) - strtotime($a['created']);
		});
		
		$messagesCount = count($messages);
		for ($i = 0; $i < $messagesCount; $i++) {
			if($messages[$i]['type'] === 'message') {
					
				// set subject to blank, so red stands out for reviews and notifications
				// not necessary when subject is removed from message creation
				if ($messages[$i]['type'] !== 'message') $messages[$i]['subject'] = '';
					
				// check standard message for replies, and add if so
				$sql = "SELECT n.nodeId as nid,
					n.userId,
					n.dateTime AS nodeDate,
					n.text,
					u.userName,
					u.avatar,
					u.age,
					u.DOB,
					u.gender,
					u.countryId
					FROM Nodes n, User u
					WHERE n.postId = ?
					AND n.userId = u.userId
					AND n.nodeId != ?
					ORDER BY n.nodeId ASC";
				$sql = $this->db->query($sql, array($messages[$i]['id'], $messages[$i]['headNodeId']));
					
				if ($sql->num_rows() > 0) {
					foreach ($sql->result_array() as $row) {
						if($row['DOB']==='1001-01-01') $row['age'] = NULL;
						// reply attachments
						if($getDeviceInfo) {
							$row['info'] = $this->node->info($row['nid']);
						}
						$messages[$i]['replies'][] = $row;
					}
				}
			}

			// get postType for device calls for subscription_msg
			if ($getDeviceInfo) {

				if(!isset($messages[$i]['fromDOB']) || $messages[$i]['fromDOB']==='1001-01-01') $messages[$i]['fromAge'] = NULL;

				if ($messages[$i]['type'] === 'subscription_msg' || $messages[$i]['isSystemMessage']===true) {

					// helpful for Messages module for .type to be present for all message types:
					if ($messages[$i]['isSystemMessage']===true) $messages[$i]['type'] = 'system';
					if(!isset($message[$i]['reportedContent'])&&!isset($messages[$i]['reviews'])) $messages[$i]['type'] = 'notification';

					// only get certain things if it's a commentedPostId
					if(isset($messages[$i]['commentedPostId'])) {

						// get the nodeId of the commentedPostId so that we can pass it along in the frontend

						$sql = $this->db->select("p.subjectId, p.postType, n.nodeId")->from('Post p, Nodes n')->where('p.postId', $messages[$i]['commentedPostId'])->where('n.postId', $messages[$i]['commentedPostId']);
						$row = $this->db->get()->row();

						$messages[$i]['parentNodeId'] = $row->nodeId;
						$messages[$i]['postType'] = $row->postType;
						$messages[$i]['subjectId'] = $row->subjectId;
						$messages[$i]['forumType'] = $this->group->getForumType($row->subjectId, false);

					} else {
						// postType
						if ($messages[$i]['type'] === 'subscription_msg') {
							$parentPost = $this->group->getParentPost($messages[$i]['id']);
							//print_r($parentPost);
							$messages[$i]['postType'] = mb_strtoupper($parentPost['postType']);
							$messages[$i]['parentNodeId'] = $parentPost['nodeId'];
							// info
							$messages[$i]['info'] = $this->node->info($parentPost['nodeId']);
							
						}
						// statuses
						// 1st quickly get subjectId - needed by iconStatuses and useful to pass back anyway, if not needed...
						// then get iconStatuses using the parentPost postId and nodeId (nb. not the ones from the subscription_msg)
						if ($messages[$i]['isSystemMessage']===false) {
							$messages[$i]['subjectId'] = $this->group->makeSubjectId($messages[$i]['info']['subjectName'], $messages[$i]['info']['userId']);
							$messages[$i]['statuses'] = $this->node->iconStatuses($messages[$i]['info']['userId'], $messages[$i]['subjectId'], $parentPost['nodeId'], $parentPost['parentPostId']);
							$messages[$i]['comments'] = $this->post->getReplyCount($parentPost['parentPostId']);
							$messages[$i]['subscribers'] = $this->post->getSubscriberCount($parentPost['parentPostId']);
						}

					}

				} else $messages[$i]['info'] = $this->node->info($messages[$i]['headNodeId']);

			}

		}
		return $messages;
	}

	/**
	 * Gets messages belonging to the user provided, or if a subject is provided, to all moderators in that subject.
	 * Retrieves not just headNodeId, but also enough info to draw a message on mobile, or to draw tags for a review message.
	 * 
	 * @param int $userId
	 * @param int $subjectId (optional)
	 * @param boolean $getDeviceInfo - for onward passage to finaliseMessages where it fetches extra data for a 'device' request
	 * @param int $count (optional) - If TRUE, the number of messages is returned, otherwise the full data set. FALSE by default.
	 * @return boolean|array|int
	 */
	function moderatorMessages($msgStatus = 'unseen', $userId, $subjectId = null, $getDeviceInfo = false, $count = FALSE) {
		if(!defined('self::MSG_' . mb_strtoupper($msgStatus))) {
			return false;
		} else {
			$msgStatus = constant('self::MSG_' . mb_strtoupper($msgStatus));
		}
		$userLevel = $this->user->getUserLevel($userId);
		
		$this->db->select("DISTINCT n.nodeId AS 'headNodeId',
				f.userId AS 'from',
				f.userName AS 'fromName',
				f.gender AS 'fromGender',
				f.DOB AS 'fromDOB',
				f.countryId AS 'fromCountry',
				t.userId AS 'to',
				t.userName AS 'toName',
				p.postType AS 'type',
				p.title AS 'subject',
				p.ownerMsgStatus AS 'oms',
				n.text AS 'body',
				n.level AS 'depth',
				r.id AS 'reviewId',
				r.reportedContentId,
				rc.reportedReviewId AS 'complaintReportedReviewId',
				p.systemMsg AS 'isSystemMessage',
				s.id AS 'subscriptionMsgId',
				aa.adArchiveId,
				ANY_VALUE(si.id) AS 'subjectInviteId'")
			->select("IF(rc.id IS NULL, 0, 1) AS 'complaintMade',
				GREATEST( COALESCE(m.date, 0), COALESCE(p.timeStamp, 0)) AS 'created',
				IF(rc.resolvedReviewId = r.id, 1, 0) AS 'isComplaintResponse',
				CONCAT(t.avatar, '-avatar') AS 'toav',
				CONCAT(f.avatar, '-avatar') AS 'fromav',
				IF(p.postType = 'subscription_msg', s.msgStatus, m.msgStatus) AS 'rms'", FALSE)
			->from('Post AS p')
			->join('Nodes AS n', 'n.postId = p.postId')
			->join('MessagesToUser AS m', 'm.messageId = n.nodeId', "LEFT")
			->join('userToSubscriptionMsg AS s', 's.postId = p.postId', "LEFT")
			->join('User AS f', 'f.userId = p.userId')
			->join('User AS t', "t.userId = IF(p.postType = 'subscription_msg', s.userId, m.toUser)", 'INNER', FALSE)
			->join('ReportedContentReviewsToUser AS r', 'r.messageId = n.nodeId', 'LEFT')
			->join('ReportComplaints AS rc', 'rc.reportedReviewId = r.id OR rc.resolvedReviewId = r.id', 'LEFT')
			->join('MessagesToAdArchive AS aa', 'aa.messageId = n.nodeId', 'LEFT')
			->join('SubjectInvite AS si', 'si.messageId = n.nodeId', 'LEFT')
			->where("(p.postType = 'message' OR p.postType = 'subscription_msg')")
			->where('n.level', 0)
			->order_by('created', 'desc');
		
		switch ($userLevel) {
			case User::USER_MODERATOR:
				$this->db->select("'$userId' AS 'userId'", FALSE)
					->select("p.postId AS 'id', p.postId")
					->join('Permissions AS a', "a.userId = f.userId OR a.userId = t.userId")
					->where("t.userId !=", $userId, false) // Get permissions for other user
					// get if post is in a subject moderated by this user
					->join('ModeratorToSubject AS ms', 'ms.subjectId = p.subjectId AND ms.approved = 1 AND ms.moderator = 1 AND ms.userId = ' . $userId, 'left', FALSE)
					// make sure other user is an admin, manager or supervisor, or the msg is in a subject the user moderates
					->where('(a.isAdministrator = 1 OR a.canBeSupervisor = 1 OR a.isManager = 1 OR ms.userId IS NOT NULL)')
					->where("(t.userId = $userId AND (CASE WHEN p.postType = 'message' THEN (m.msgStatus = '$msgStatus') ELSE (s.msgStatus = '$msgStatus') END) OR (f.userId = $userId && p.ownerMsgStatus = '$msgStatus' && p.systemMsg = 0))");
					//->group_by('p.postId');
				break;
			case User::USER_SUPERVISOR:
			case User::USER_MANAGER:
			case User::USER_ADMIN:
				if(is_null($subjectId)) {
					return false;
				}
				$this->db->select("ms.userId, u.userName, p.postId")
					->select("CONCAT(ms.userId,p.postId) AS 'id'", FALSE)
					// Get all moderators for this subject, and make sure message is either to or from them
					->join('(
							SELECT `userId`
							FROM `ModeratorToSubject` 
							WHERE `approved` = 1
							AND `moderator` = 1
							AND `subjectId` = ' . $subjectId . '
						) AS `ms`', "ms.userId = p.userId OR ms.userId = t.userId", 'INNER', FALSE)
					// Get permissions of other user
					->join("Permissions AS a", "a.userId != ms.userId AND (a.userId = f.userId OR a.userId = t.userId)", 'INNER', FALSE)
					->join('User AS u', 'u.userId = ms.userId')
					// post is either in this subject, or from an admin, manager, supervisor or another moderator
					// @todo - should the post be in any subject the moderator moderates?
					->where('(`p`.`subjectId` = ' . $subjectId . ' OR (`a`.`isAdministrator` = 1 OR `a`.`canBeModerator` = 1 OR `a`.`canBeSupervisor` = 1 OR `a`.`isManager` = 1))', NULL, FALSE)
					->where("((t.userId = a.userId && p.ownerMsgStatus = '$msgStatus') OR (f.userId = a.userId AND CASE WHEN p.postType = 'message' THEN m.msgStatus = '$msgStatus' ELSE s.msgStatus = '$msgStatus' END))")
					->where("IF(ms.userId = f.userId, p.systemMsg, 0) = 0", null, false) // only want system messages to the moderator
					->group_by('ms.userId, p.postId, n.nodeId, r.id, rc.id, s.id, m.toUser, t.userId');
				break;
			default:
				return false;
		}
		$results = $this->processRawMessages($this->db->get()->result_array());
		$results = $this->finaliseMessages($results, $getDeviceInfo);
		if($count) {
			return count($results);
		} else {
			return $results;
		}
	}
	
	/**
	 * Gets level specific messages belonging to the user provided.
	 * Retrieves not just headNodeId, but also enough info to draw a message on mobile, or to draw tags for a review message.
	 *
	 * @param int $userId
	 * @param string $level - the user level. Determines conditions for message retrieval.
	 * @param int $count (optional) - If TRUE, the number of messages is returned, otherwise the full data set. FALSE by default.
	 * @return boolean|array|int
	 */
	function supervisorAndHigherMessages($msgStatus, $userId, $type, $count = FALSE) {
		if(!defined('self::MSG_' . mb_strtoupper($msgStatus))) {
			return false;
		} else {
			$msgStatus = constant('self::MSG_' . mb_strtoupper($msgStatus));
		}
		$this->db->select("
				p.postId AS 'id',
				p.postId,
				n.nodeId AS 'headNodeId',
				t.userId AS 'to',
				f.userId AS 'from',
				p.postType AS 'type',
				p.timeStamp AS 'created',
				p.title AS 'subject',
				p.ownerMsgStatus AS 'oms',
				n.text AS 'body',
				n.level AS 'depth',
				r.id AS 'reviewId',
				r.reportedContentId,
				rc.reportedReviewId AS 'complaintReportedReviewId',
				p.systemMsg AS 'isSystemMessage',
				aa.adArchiveId,
				ANY_VALUE(si.id) AS 'subjectInviteId'")
			->select("IF(rc.id IS NULL, 0, 1) AS 'complaintMade',
				IF(rc.resolvedReviewId = r.id, 1, 0) AS 'isComplaintResponse',
				GREATEST(COALESCE(m.date, 0), COALESCE(p.timeStamp, 0)) AS 'created',
				CONCAT(CONCAT(f.avatar, '-avatar'), '-avatar') AS 'toav',
				CONCAT(t.avatar, '-avatar') AS 'fromav',
				IF(p.postType = 'subscription_msg', s.msgStatus, m.msgStatus) AS 'rms',
				'$userId'  AS 'userId'", FALSE)
			->from('Post AS p')
			->join('Nodes AS n', 'n.postId = p.postId')
			->join('MessagesToUser AS m', 'm.messageId = n.nodeId', "LEFT")
			->join('userToSubscriptionMsg AS s', 's.postId = p.postId', "LEFT")
			->join('User AS f', 'f.userId = p.userId')
			->join('User AS t', "t.userId = IF(p.postType = 'subscription_msg', s.userId, m.toUser)", 'INNER', FALSE)
			->join('ReportedContentReviewsToUser AS r', 'r.messageId = n.nodeId', 'LEFT')
			->join('ReportComplaints AS rc', 'rc.reportedReviewId = r.id OR rc.resolvedReviewId = r.id', 'LEFT')
			->join('Permissions AS a', 'a.userId = f.userId OR a.userId = t.userId')
			->join('MessagesToAdArchive AS aa', 'aa.messageId = n.nodeId', 'LEFT')
			->join('SubjectInvite AS si', 'si.messageId = n.nodeId', 'LEFT')
			->where('p.postType', 'message')
			->where('n.level', 0)
			->where('a.userId !=', $userId)
			->where("((t.userId = $userId AND CASE WHEN p.postType = 'message' THEN m.msgStatus = '$msgStatus' ELSE s.msgStatus = '$msgStatus' END) OR (f.userId = $userId && p.ownerMsgStatus = '$msgStatus' && p.systemMsg = 0))")
			->group_by('p.postId, n.nodeId, r.id, rc.id, s.id, m.toUser, t.userId')
			->order_by('created', 'desc');
		if($type == 'supervisor') {
			$this->db->join('SupervisorToSubject AS ss', 'ss.subjectId = p.subjectId AND ss.userId = '.$userId, 'LEFT', FALSE)
			//->join('ModeratorToSubject AS ms', 'ms.subjectId = p.subjectId AND ms.approved = 1 AND ms.moderator = 1 AND (ms.userId = n.toUser OR ms.userId = p.userId) AND ms.userId != '.$userId, 'LEFT', FALSE)
			->where('(a.isAdministrator = 1 OR a.canBeSupervisor = 1 OR a.isManager = 1 OR (ss.userId IS NOT NULL /*AND ms.userId IS NOT NULL*/))');
		} else if ($type === 'manager' || $type === 'administrator') {
			$this->db->where('(a.isAdministrator = 1 OR a.canBeSupervisor = 1 OR a.isManager = 1)');
		} else {
			return false;
		}
		
		$r = $this->processRawMessages($this->db->get()->result_array());
		$r = $this->finaliseMessages($r, false);
		if($count) {
			return count($r);
		} else {
			return $r;
		}
	}
	
	/**
	 * Gets supervisor specific messages belonging to the user provided.
	 *
	 * @param int $userId
	 * @param int $count (optional) - If TRUE, the number of messages is returned, otherwise the full data set. FALSE by default.
	 * @return boolean|array|int
	 */
	function supervisorMessages($msgStatus = 'unseen', $userId, $count = FALSE) {
		return $this->supervisorAndHigherMessages($msgStatus, $userId, 'supervisor', $count);
	}
	
	/**
	 * Gets manager specific messages belonging to the user provided.
	 *
	 * @param int $userId
	 * @param int $count (optional) - If TRUE, the number of messages is returned, otherwise the full data set. FALSE by default.
	 * @return boolean|array|int
	 */
	function managerMessages($msgStatus = 'unseen', $userId, $count = FALSE) {
		return $this->supervisorAndHigherMessages($msgStatus, $userId, 'manager', $count);
	}
	
	// added cjt - a "simpler" query to quickly retrieve numbers
	// FUTURE DEV: Move this somewhere else and add counts for chats and threads
	/**
	 * @deprecated Use message::messages($readStatus, true) to retrieve message count.
	 */
	function countMessages($data = false, $viewType){
	
		$userId = $this->user->authCheck();

		$subQuery = "SELECT blockId from UserToBlock WHERE userId = $userId";
		$this->db->select("COUNT(`p`.`postId`) AS 'numMessages'", FALSE)
			->from("User AS `u`")
			->join("Post AS `p`", "p.userId = u.userId")
			->join("Nodes AS `n`", "p.postId = n.postId", 'LEFT')
			->where(array(
				'p.postType' => 'message',
				'n.level' => 0
			))
			->where("p.userId NOT IN ($subQuery)", NULL, FALSE)
			->where("n.toUser NOT IN ($subQuery)", NULL, FALSE);
			// some condition to count either all in the messages box or unseen only
			if($viewType=='unseen'){
				$this->db->where("(n.toUser = $userId AND p.recipientMsgStatus = '".self::MSG_UNSEEN."') OR (p.userId = $userId AND p.ownerMsgStatus = '".self::MSG_UNSEEN."')");
			} else {
				$this->db->where("(n.toUser = $userId AND p.recipientMsgStatus IN ('".self::MSG_UNSEEN."', '".self::MSG_SENT."')) OR (p.userId = $userId AND p.ownerMsgStatus IN ('".self::MSG_UNSEEN."','".self::MSG_SENT."'))");
			}
		$result = $this->db->get()->result_array();
	
		return $result;
	
	}

	/**
	 * Handles creation of a new message post.
	 * 
	 * @param array $data
	 * @return boolean|array['nodeId','postId']
	 */
	function create($data) {
		// check if the user is logged in
		$userId = $this->user->authCheck();
		// must be logged in
		if ($userId === false) {
			return false;
		}

		$userName = $this->user->getAuthUserName();

		// data must be provided
		if (!is_array($data) || empty($data)) {
			return false;
		}
		
		// required fields are present
		if (!isset($data['title']) || !isset($data['body']) || !isset($data['to'])) {
			return false;
		}
		// and non-empty
		// commented out as sending an attachment only is possible, which would be no text, and messages may no longer have a title
		/*if ($data['body'] == '' || $data['title'] == '') {
			return false;
		} */
		// set group/subject/forum id
		if (isset($data['group'])) {
			if (!is_numeric($data['group'])) {
				$subjectId = $this->group->findByName($data['group']);
			} else {
				$subjectId = $data['group'];
			}
		}
		if(!isset($subjectId) || !$subjectId) {
			// home by default
			$subjectId = 1;
		}
		$ts = date('Y-m-d H:i:s');

		$this->load->helper('htmlpurifier');

		// create "post"
		$this->db->insert('Post',
			array(
				'userId' => $userId,
				'subjectId' => $subjectId,
				'title' => html_purify($data['title'],'post'),
				'postType' => 'message',
				'timeStamp' => $ts,
				'lastInput' => $ts,
				'visible' => 1,
				'author' => $userName,
				'ownerMsgStatus' => self::MSG_SENT
			));

		// post insert unsuccessful
		if ($this->db->affected_rows() === 0) {
			return false;
		}

		// create node
		$postId = $this->db->insert_id();
		
		// nv, dv etc: no calculation necessary, as created message will ALWAYS be a root node.
		$this->db->insert('Nodes',
			array(
				'postId' => $postId,
				'userId' => $userId,
				'nodeType' => 'rootNode',
				'dateTime' => $ts,
				'text' => html_purify($data['body'],'post'),
				'nv' => 0,
				'dv' => 1,
				'snv' => 1,
				'sdv' => 0,
				'parentNodeId' => 0,
				'level' => 0,
				'readIt' => 0
		));
		
		// node query failed - might need some better error handling to id what went wrong and where...
		if ($this->db->affected_rows() === 0) {
			return false;
		}
		// Add MessagesToUser row to store recipient data
		$nodeId = $this->db->insert_id();
		$this->db->insert('MessagesToUser', array(
			'toUser' => $data['to'],
			'messageId' => $nodeId,
			'msgStatus' => self::MSG_UNSEEN
		));

		// NB: dojo depends on this value - change at risk!  Risk taken mn 20160826 :)
		return array("nodeId"=> $nodeId,"postId"=> $postId);
	}
	
	/**
	 * Records a reply to a message, and updates the message status for both the message owner and recipient.
	 * 
	 * @param array $data
	 * @return boolean
	 */
	function reply($data) {
		$userId = $this->user->authCheck();
		
		if (!$userId) {
			return false;
		}
	
		// 1 - create new node for the reply
		$ts = date('Y-m-d H:i:s');

		if(!empty($_SERVER['HTTP_X_FORWARDED_FOR']) && (filter_var($_SERVER['HTTP_X_FORWARDED_FOR'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) || filter_var($_SERVER['HTTP_X_FORWARDED_FOR'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6))) {
			$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
		} else {
			$ip = $_SERVER['REMOTE_ADDR'];
		}

		//$subjectName = $this->group->getName($this->node->getSubjectId($data['parentNodeId']));
		//if(!$subjectName) $subjectName = 'my feed';
		$this->load->helper('htmlpurifier');
		$query = "CALL addNode(?,?,?,?,?,?,?,?,?,@newNodeId)";
		$params = array(0, $data["postId"], $userId , 'message', $ts, html_purify($data['message'],'post'), $data["parentNodeId"], 0, $ip);
	
		$query = $this->db->query($query, $params);

		if ($query->num_rows() > 0) {
			$nodeId = $this->db->query("SELECT @newNodeId AS 'nodeId'")->first_row('array')['nodeId'];
	
			// 2 - update the read statuses of the message for both recipient and writer
			// -> done for the writer of this reply, unread to the recipient. 
			// -> -> if the writer is the same as the post author, ownerMsgStatus is done (2), recipient msgStatus is unread (0)
			// -> -> otherwise, the writer is the recipient, so ownerMsgStatus is 0, recipient is 2
	
			$query = "
				UPDATE Post AS p
				JOIN Nodes AS n ON n.postId = p.postId
				JOIN MessagesToUser AS m ON m.messageId = n.nodeId
				SET m.msgStatus = IF(p.userId = ?, '".self::MSG_UNSEEN."', '".self::MSG_SENT."'),
					p.ownerMsgStatus = IF(p.userId = ?, '".self::MSG_SENT."', '".self::MSG_UNSEEN."')
				WHERE p.postId = ?
				AND n.level = 0";
			$params = array($userId, $userId, $data['postId']);
	
			$query = $this->db->query($query, $params);

			// Assume the above is successful. If the user is replying even though they've read it, found rows will be 0, so can't rely 
			// on affected_rows > 0
			return array("nodeId" => $nodeId,"postId"=>$data["postId"]);
		}
		return false;
	}
	
	// this should probably live in user model?  easier for me to leave here :[ cjt
	function getSocialNames(){
	
		$userId = $this->user->authCheck();
		if($userId===false){return '0';}
		/* 1. get any people who have blocked "this userId" */
	
		$sql = "SELECT userId FROM UserToBlock WHERE blockId = ?;";
		$params = array($userId);
		$sql = $this->db->query($sql, $params);
	
		$blockers = array();
	
		if ($sql->num_rows() > 0) {
	
			$row = $sql->result_array();
			foreach ($row as $blocker) $blockers[] = $blocker['userId'];
	
		}
	
		/* 2. get list of users user is following, minus those being blocked (the sub-query below) and minus those blocking this user (from query above) */
		// NB: not quite sure here what subjectId = 0 is for....
	
		$query = "SELECT
			DISTINCT(uts.socialId),
			u.userName,
			IF(u.avatar IS NULL, NULL, CONCAT(u.avatar, '-icon16')) AS 'userIcon'
			FROM UserToSocial AS uts 
			JOIN User AS u ON uts.socialId = u.userId
			WHERE uts.userId = ?
			AND uts.subjectId != 0 
			AND u.userId NOT IN (SELECT blockId from UserToBlock WHERE userId = ?)";
		if(!empty($blockers)){
			$query .= "AND uts.socialId NOT IN (".implode(",", $blockers).");";
		}
		$params = array($userId, $userId);
		$query = $this->db->query($query, $params);
	
		if ($query->num_rows()>0) {
			$names = $query->result_array();
		} else $names = '0';
	
		return $names;
	}

	/** 
	 * Performs an action on a message. Either changes the status of the message, or blocks a user from messaging the logged in user.
	 * Valid statuses:
	 * 		"unseen" = unread, belongs in unread inbox
	 * 		"done" = read, belongs in archive inbox
	 * 		"deleted" = status 3, hidden to user, unless other participant in conversation turns it zombie!  Must...eat.....brainzzzz....
	 *		"block" = blocking a user from their Messages
	 * 
	 * @param array $data 
	 * @return boolean
	 */
	function action($data){
		
		$userId = $this->user->authCheck();
		// must be logged in
		if ($userId === false) {
			return false;
		}
		if(!isset($data['postId'])) {
			return false;
		}
		
		// So it doesn't have to be checked every time, convert single post id to a single element array
		// where_in queries will still work with a single element in the array
		if (!is_array($data['postId'])) {
			$data['postId'] = [$data['postId']];
		}
		switch ($data['action']) {
			case 'unseen':
				$action = self::MSG_UNSEEN;
				break;
			case 'done':
				$action = self::MSG_DONE;
				break;
			case 'delete':
				$action = self::MSG_DELETED;
				break;
			case 'block':
				$action = $data['action'];
				break;
			default:
				return false;
		}
		// set some values for the table based upon data
		if(isset($data['msgType']) && $data['msgType'] === 'subscription_msg') {
			$this->db->where_in('id', $data['postId']);
			$this->db->where('userId', $userId);
			$this->db->update('userToSubscriptionMsg', array('msgStatus' => $action));
		// deleting a comment notification message
		} elseif (isset($data['msgType']) && $data['msgType'] === 'commdel' && $action === '3') {

			// set MessagesToUser status to 3
			$this->db->where('messageId', $data['postId'][0]);
			$this->db->update('MessagesToUser', array('msgStatus' => $action));

			// clean out the MessageToComments row
			$this->db->where('messageId', $data['postId'][0]);
			$this->db->delete('MessagesToComments'); 

		} else {
			// Check user is allowed to update these posts.
			$posts = [];
			if(!$this->user->adminCheck() && !$this->user->isDraftMetaUser($userId)) {
			foreach($data['postId'] as $p) {
				if($data['UsrOwn'] === 'r') {
					
					$this->db->select("messageId, p.userId AS 'otherUser'")
						->from('MessagesToUser AS m')
						->join('Nodes AS n', 'n.nodeId = m.messageId')
						->join('Post AS p', 'p.postId = n.postId')
						->where([
							'm.toUser' => $userId,
							'p.postId' => $p
						]);
					$post = $this->db->get();
					if ($post->num_rows() === 0) {
						// Not permitted as the current user isn't a recipient of the message
						return false;
					}
					$posts[] = $post;
				} else {
					$this->db->select("messageId, m.toUser AS 'otherUser'")
						->from('MessagesToUser AS m')
						->join('Nodes AS n', 'n.nodeId = m.messageId')
						->join('Post AS p', 'p.postId = n.postId')
						->where([
							'p.userId' => $userId,
							'p.postId' => $p
						]);
					$post = $this->db->get();
					if ($post->num_rows() === 0) {
						// Not permitted as the current user didn't write the message
						return false;
					}
					$posts[] = $post;
				}
			}
		}
			
			if ($action === 'block') {
				if (count($posts) !== 1) {
					// Can only block users through messages from 1-1 conversations
					return false;
				}

				//block 'em
				$bdata = array(
					'userId' => $userId,
					'blockId' => $posts[0]->first_row('array')['otherUser']
				);

				$this->db->insert('UserToBlock', $bdata);

			} else {
				$params = [$action];
				$where = '';
				// If user is the owner of the message, update post.msgStatus, otherwise update MessagesToUser.msgStatus
				if($data['UsrOwn'] === 'r') {
					$statusCol = 'm.msgStatus';
					// Set user condition for update query
					$where = "m.toUser = ?";
					if(!empty($data['rcptUserId']) && ($this->user->adminCheck() || $this->user->isDraftMetaUser($userId))) {
						$params[] = $data['rcptUserId'];
					} else {
						$params[] = $userId;
					}
				} else {
					$statusCol = 'p.ownerMsgStatus';
				}
				// postId can be a single id, or an array of ids
				if(is_array($data['postId'])) {
					$this->db->where_in('Post.postId', $data['postId']);
					if ($where !== '') {
						$where .= " AND ";
					}
					$where .= "p.postId IN (";
					$postCount = count($data['postId']);
					for ($i = 0; $i < $postCount; $i++) {
						if ($i === $postCount - 1) {
							$where .= '?';
						} else {
							$where .= '?, ';
						}
					}
					$where .= ')';
					$params = array_merge($params, $data['postId']);
				} else {
					if ($where !== '') {
						$where .= " AND ";
					}
					$where .= "p.postId = ?";
					$params[] = $data['postId'];
				}
				$sql = "
					UPDATE `Post` AS `p`
					JOIN `Nodes` AS `n` ON `n`.`postId` = `p`.`postId`
					JOIN `MessagesToUser` AS `m` ON `m`.`messageId` = `n`.`nodeId`
					SET $statusCol = ?
					WHERE $where
					";
				$this->db->query($sql, $params);
			}
		}
		if($this->db->affected_rows() > 0){
			return true;
		}
		return false;
	
	}
	
	/**
	 * Retrieves the template string for the given system message type, and builds the message text
	 * from the provided data. Checks that all required data has been included, and returns false if it hasn't.
	 * 
	 * @param int $msgType
	 * @param array $data
	 * @return boolean|array false if params invalid, array with [body, subject].
	 */
	private function getMsgTemplate($msgType, $data) {
		// Check message has been defined.
		if(!isset($this->msgText[$msgType])) {
			return false;
		}
		$msg = $this->msgText[$msgType];
		
		// Check required data has been sent
		if(isset($msg['required'])) {
			foreach($msg['required'] as $r) {
				if(!isset($data[$r])) {
					log_message('error', 'To send this system message, the data array must be sent when systemMessage is called, with key-value pairs '.implode(' ', $msg['required']).' set.');
					return false;
				}
			}
		}
		$body = $msg['body'];
		$subject = $msg['subject'];
		// Add data to template string
		if(isset($msg['required'])) {
			foreach($msg['required'] as $r) {
				$body = preg_replace('/\*\*'.$r.'\*\*/', $data[$r], $body);
				$subject = preg_replace('/\*\*'.$r.'\*\*/', $data[$r], $subject);
			}
		}
		return ['body' => $body, 'subject' => $subject];
	}
	
	/**
	 * Retrieves unactioned messages sent to fraft users
	 * 
	 * @return boolean|array
	 */
	function getDraftMessages() {
		
		$this->db->select("mtu.toUser")
				->select("u.userName AS toUserName")
				->select("u2.userName AS fromUserName")
				->select("u2.userType AS fromUserType")
				->select("SUBSTRING(n.text,1,40) AS messageText")
				->select("n.text AS messageText")
				->select("s.subjectName")
				->select("DATE_FORMAT(mtu.date, '%d %b %y') AS messageDate")
				->select("p.postId")
				->where("mtu.msgStatus = '0' AND mtu.toUser not in (select userId from approvalMessageBlacklist)")
				->join("User AS u", "u.userId = mtu.toUser AND u.userType = 1")
				->join("Nodes AS n", "n.nodeId = mtu.messageId")
				//->join("Post AS p", "p.postId = n.PostId AND p.postType = 'message' AND p.systemMsg = 0")
				->join("Post AS p", "p.postId = n.PostId "
						. "AND p.postType = 'message' "
						. "AND title not in ('Welcome!', 'Beta Testing Phase', 'Invite a friend', 'Beta phase')"
						. "AND p.userId not in (select userId from approvalMessageBlacklist)")
				->join("User AS u2", "u2.userId = n.userId")
				//->join("Subject AS s", "s.subjectId = p.subjectId AND s.private = 0")
				->join("Subject AS s", "s.subjectId = p.subjectId")
				->order_by('mtu.date', 'desc');
		
		$query = $this->db->get('MessagesToUser AS mtu');
		
		if ($query->num_rows() > 0) {
			return $query->result_array();
		} 
		return FALSE;
	}
	
	private $msgText = [
		self::MSG_MODERATOR_ASSIGNED => [
			'body' => 'You have just been assigned to the **subjectName** forum. Please proceed to act as a moderator for this from now on, as agreed in terms and conditions.',
			'subject' => 'New forum to moderate',
			'required' => ['subjectName']
		],
		self::MSG_MODERATOR_UNASSIGNED => [
			'body' => 'You have just been unassigned from moderating the **subjectName** forum. Thank you.',
			'subject' => 'No longer moderating **subjectName** forum',
			'required' => ['subjectName']
		],	
		self::MSG_FORUM_APPROVED => [
			'body' => 'Your request to create a new forum **suggestedName** has been approved.',
			'subject' => 'Suggested Forum',
			'required' => ['suggestedName']
		],
		self::MSG_FORUM_DENIED => [
			'body' => 'Your request to create a new forum **suggestedName** has been denied.',
			'subject' => 'Suggested Forum',
			'required' => ['suggestedName']
		],
		self::MSG_USER_WARNED => [
			'body' => 'You are close to being banned from the forum **subjectName** because of: **warningReason** Please consider this a warning. You may be banned if your poor behaviour continues.',
			'subject' => 'Behaviour Warning', // @todo - how to add "You may want to consider going to the Club 18 area instead of the general forums." if banReason == 'adult'
											// Possibly add **appendedText** at the end, allowing extra text to be added?
			'required' => ['subjectName', 'warningReason']
		],
		self::MSG_USER_BANNED => [
			'body' => 'You have been banned from the **subjectName** forum by one of the moderators for **banPeriod** days because of: **banReason**',
			'subject' => 'Temporary Ban', // @todo - how to add "You may want to consider going to the Club 18 area instead of the general forums." if banReason == 'adult'
			'required' => ['subjectName', 'banPeriod', 'banReason']
		],
		self::MSG_POST_CENSORED => [
			'body' => 'The content above has been censored because of: **censorReason**',
			'subject' => 'Content censored',
			'required' => ['censorReason']
		],
		self::MSG_POST_ATTACHMENT_DELETED => [
			'body' => 'The post above has had one or more attachments removed because of: **removalReason**',
			'subject' => 'Post attachment/s removed',
			'required' => ['removalReason']
		],
		self::MSG_POST_DELETED => [
			'body' => 'The post above has been removed because of: **removalReason**',
			'subject' => 'Post removed',
			'required' => ['removalReason']
		],
		self::MSG_POST_RESTORED => [
				'body' => 'The post above has been restored and will now be visible again.',
				'subject' => 'Post restored'
		],
		self::MSG_THREAD_DELETED => [
				'body' => 'A thread in **subjectName** you have participated in has been removed. Moderator comments:<pre>"**modMessage**"</pre>',
				'subject' => 'Thread removed',
				'required' => ['subjectName', 'modMessage']
		],
		self::MSG_THREAD_RESTORED => [
				'body' => 'A thread you were following in **subjectName** has been restored. The moderator said:<pre>**modMessage**</pre>',
				'subject' => 'Thread restored',
				'required' => ['subjectName', 'modMessage']
		],
		self::MSG_ISSUE_RESOLVED => [
			'body' => 'The error report with the summary: **reportSummary** has been resolved. Thank you for your feedback.',
			'subject' => 'Error report resolved',
			'required' => ['reportSummary']
		],
		self::MSG_USER_UNBANNED => [
			'body' => 'Your ban for the **subjectName** forum has been lifted.',
			'subject' => 'Ban Lifted',
			'required' => ['subjectName']
		],
		self::MSG_EXTREME_THANKYOU => [
			'body' => 'Thankyou for bringing the extreme content to our attention. The post has been permanently removed.',
			'subject' => 'Extreme report outcome'
		],
		self::MSG_EXTREME_BAN => [
			'body' => 'You reported a post for extreme content, which has now been reviewed, and found to be non-offensive. As a result you have been banned from **subjectName** for 7 days.',
			'subject' => 'Extreme report outcome',
			'required' => ['subjectName']
		],
		self::MSG_EXTREME_IGNORE => [
			'body' => 'You reported a post for extreme content, which has now been reviewed. As a result, we have deemed the content not bad enough to delete. In future, please report content like this for one of the other provided reasons so it can be dealt with more appropriately.',
			'subject' => 'Extreme report outcome'
		],
		self::MSG_COMPLAINT_IGNORE => [
			'body' => 'The complaint below has been reviewed, and the actions found to be suitable.',
			'subject' => 'Complaint outcome'
		],
		self::MSG_COMPLAINT_ACTION => [
			'body' => 'The complaint below has been reviewed, and the actions found to be unsuitable. They have been reversed.',
			'subject' => 'Complaint outcome'
		],
		self::MSG_WARNING_IGNORE => [
			'body' => 'You have been warned previously, but please ignore this.',
			'subject' => 'Disregard previous message.'
		],
		self::MSG_POST_ATTACHMENT_RESTORED => [
			'body' => 'Upon review of an action to remove content from one of your posts, it has been decided to restore it.',
			'subject' => 'Post restored'
		],
		self::MSG_FORUM_CREATE => [
			'body' => 'A new forum, <strong>**subjectName**</strong> has been created. You will be the moderator for it. Please go to your settings area to see what you can do with your new forum.',
			'subject' => 'New forum created',
			'required' => ['subjectName']
		],
		self::MSG_FORUM_ADOPT => [
			'body' => 'Thankyou for your request for us to adopt your forum. We will be in contact with you shortly.',
			'subject' => 'Adopt a Forum'
		],
		self::MSG_MANAGER_SPIKE => [
			'body' => 'There has been a spike in reported nodes for the forum **subjectName**. Please investigate.',
			'subject' => 'Spike in reports',
			'required' => ['subjectName']
		],
		self::MSG_MODERATOR_NEEDED => [
			'body' => '**moderatorName** has resigned as a moderator of **subjectName**, leaving the forum with no more moderators. Please take over moderation for this forum and let your manager know a replacement is needed.',
			'subject' => 'Moderator resignation',
			'required' => ['moderatorName', 'subjectName']
		],
		self::MSG_ADREPORT_PENDING => [
			'body' => 'The ad above has been reported to us. As a result, it will be inactivated pending a review, which will be carried out as soon as possible. You will be notified of the results when the review is complete.',
			'subject' => 'Ad under review'
		],
		self::MSG_ADREPORT_THANK => [
			'body' => 'Thankyou for reporting an ad to us. The ad in question has been reviewed, and suitable actions carried out.',
			'subject' => 'Ad report acknowledged'
		],
		self::MSG_ADREPORT_WARNED => [
			'body' => 'You reported an ad to us, the ad in question has already been reviewed, and has been deemed suitable. Please do not report ads in future unless there is offensive, extreme, illegal or otherwise unacceptable content. Further unnecessary reports could result in your being banned.',
			'subject' => 'Ad report acknowledged'
		],
		self::MSG_ADREPORT_BANNED => [
			'body' => 'You reported an ad to us, the ad in question has already been reviewed, and has been deemed suitable. Due to the unnecessary natue of the report, you have been banned for **banPeriod** days.',
			'subject' => 'Ad report acknowledged',
			'required' => ['banPeriod']
		],
		self::MSG_ADREPORT_RESTORE => [
			'body' => 'The ad above has been reported to us and reviewed. The content was deemed suitable, and the ad has been reactivated.',
			'subject' => 'Ad review complete'
		],
		self::MSG_ADREPORT_REPOST => [
			'body' => 'The ad above has been reported for unsuitable content and has been inactivated. Please make changes to the ad to reactivate it.',
			'subject' => 'Ad review complete'
		],
		self::MSG_ADREPORT_REMOVE => [
			'body' => 'The ad above has been reported for unsuitable content and has been inactivated permanently.',
			'subject' => 'Ad review complete'
		],
		self::MSG_FILE_DELETED => [
			'body' => 'The file above was removed, as it was reported. It will remain in your media, but will no longer be available to the public in the work area.',
			'subject' => 'File removed'
		],
		self::MSG_FILE_RESTORED => [
			'body' => 'The file above was reviewed and restored. It will be available to the public in the work area again.',
			'subject' => 'File restored'
		],
		self::MSG_PAYMENTS_AUTH => [
			'body' => 'Moderator Distributed Payments Require Authorization for **month** **year**. Please proceed to Ad Admin -> Payments -> Moderator Distributed Payments to authorize the required payments prior to completion.',
			'subject' => 'Moderator Distributed Payments Require Authorization for **month** **year**',
			'required' => ['month', 'year']
		],
		self::MSG_WELCOME => [
			'body' => '<h3>Welcome to ramblur!</h3><p>ramblur takes a modern approach to forum design, transforming a tired format by creating a more responsive, interactive and socially engaging concept that puts the user in control, viewing forums from their own customisable account.</p><p>ramblur hosts hundreds of forums both created by ramblur and created by users. You can search for any forums and follow all your interests from one easy to manage account.</p><p>You can also create your very own public, closed or private forum. Simply select the \'create a forum\' tab from the main menu in the top right of your user account to get started.</p>',
			'subject' => 'Welcome!'
		],
		self::MSG_BETAPHASE => [
			'body' => '<h3>Beta Testing Phase!</h3><p>ramblur is a brand new site as of June 2019, and as such is in a beta testing phase. During 2019 ramblur will add many new features as well as improve upon the design and functionality of features currently available throughout the site.</p><p>Help us make ramblur better by reporting any system errors that you encounter, or submitting any feedback you wish to provide. Simply select "feedback" or "report error" from the main menu which can be found at the top right of your user account.</p>',
			'subject' => 'Beta testing phase'
		],
		self::MSG_WELCOME_INVITE => [
			'body' => '<h3>Invite a Friend</h3>To help spread the word of ramblur to people you know and start building communities, you can <b>invite friends</b> to join. This option can be found in the main menu (or hamburger if on a device).',
			//'body' => '<h3>Invite a Friend</h3>To help spread the word of ramblur to people you know and start building communities, you can <a href="/settings/invite-invite"><b class="welcomeMessageLink">invite friends</b></a> to join.',
			'subject' => 'Invite a friend'
		],
		self::MSG_FORUM_INVITE => [
			'body' => '**message** <br/><br/>**inviteFromName** **inviteFromEmail** <br/>You have been invited to join a**visibility** forum**managedBy**. ',
			'subject' => 'Invitation to join a**visibility** forum',
			'required' => ['visibility', 'managedBy', 'message', 'inviteFromName', 'inviteFromEmail']
		],
		self::MSG_COMMENT_NOTIFICATION => [
			'body' => 'The following Ramblur users have commented on your <strong>**postType**</strong>, "**title**":',
			'subject' => 'Your **postType** has received feedback!',
			'required' => ['postType', 'title']
		],
		self::MSG_USER_REMOVED_FROM_FORUM => [
			'body' => "You are no longer following the **visibility** forum '**subjectName**' as the forum owner removed your access.",
			'subject' => 'Removed from forum',
			'required' => ['visibility', 'subjectName']
		]
	];
}
