<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Reported_ad extends CI_Model {
	
	// Defines the total number of users required to report an ad before it will be removed pending review
	const REPORT_AD_COUNT = 6;
	//const REPORT_AD_COUNT = 1;
	
	/**
	 * Creates an ad report.
	 * 
	 * @param array $data <br>
	 * 		['reason'] string The reason for the report <br>
	 * 		['subjectId'] string The subject id for the forum reported from <br>
	 * 		['adId'] string Id of the ad being reported
	 * @param mixed $error
	 * @return boolean
	 */
	function createReport($data, &$error) {
		$this->load->model('user');
		$this->load->model('ad');
		$this->load->model('message');
		
		// Validate data
		$userId = $this->user->authCheck();
		if(!$userId) {
			$error = 'Only logged in users can report ads.';
			return false;
		}
		if(!isset($data['reason']) || $data['reason'] == '') {
			$error = 'To report an ad, you must provide a reason.';
			return false;
		}
		//@todo - possibly should check subject exists (and ad is targeted to it?)
		if(!isset($data['subjectId'])) {
			$error = 'To report an ad, you must do so from a specific forum.';
			return false;
		}
		if(!isset($data['adId'])) {
			$error = 'To report an ad you must provide the ad id.';
			return false;
		}
		$adId = $data['adId'];
		
		$adArchiveId = $this->getCurrentAdArchiveId($adId);
		if(!$adArchiveId) {
			$error = 'The ad could not be found.';
			return false;
		}
		
		// Allow each user to report the ad only once for each set of ad content
		$this->db->select('id')
			->from('ReportedAds')
			->where(array(
				'adId' => $adId,
				'userId' => $userId,
				'adArchiveId' => $adArchiveId
		));
		if($this->db->get()->num_rows() > 0) {
			$error = 'You have already reported this ad in it\'s current form, and cannot do so again.';
			return false;
		}
		$this->load->helper(array('htmlpurifier'));
		// Record report. Want all reports, even if it has been reviewed already
		$this->db->insert(
			'ReportedAds',
			array(
				'adId' => $adId,
				'adArchiveId' => $adArchiveId,
				'userId' => $userId,
				'subjectId' => $data['subjectId'],
				'reason' => html_purify($data['reason'],'post')
		));
		$adReported = $this->db->affected_rows() > 0;
		if($this->db->affected_rows() === 0) {
			return false;
		}
		
		// Check if ad has already been reviewed. If not, check if there have been enough reports to mark as needing review
		$this->db->from('ReportedAdReviews')
			->where(array(
				'adId' => $adId,
				'adArchiveId' => $adArchiveId
		));
		$adReviews = $this->db->get();
		if($adReviews->num_rows() > 0) {
			// allow report, but send message to reporter detailing results of review
			// eg. this ad has already been reviewed, and deemed suitable. Thanks for your concern.
			
			// should this be disallowed??
			$this->message->systemMessage($adReviews->first_row('array')['reviewerId'], $this->user->authCheck(), $data['subjectId'], message::MSG_ADREPORT_THANK);
			
		} else {
			$this->db->select('COUNT(id) AS reportCount')
				->from('ReportedAds')
				->where(array(
					'adId' => $adId,
					'adArchiveId' => $adArchiveId
			));
			$reportCount = (int)$this->db->get()->first_row('array')['reportCount'];
			if($reportCount === self::REPORT_AD_COUNT) {
				// flag ad as underReview
				$this->db->update('Advert',
					array(
						'modUnderReview' => 1,
						'modPaused' => 1
					),
					array('adId' => $adId)
				);
				// notify creator their ad has been paused
				$ad = $this->getAdArchiveDetails($adArchiveId);
				$this->message->systemMessage(1, $ad['userId'], $data['subjectId'], message::MSG_ADREPORT_PENDING, array(), array('id' => $adArchiveId, 'type' => 'adArchive'));
			}
		}
		return true;
	}
	
	/**
	 * Retrieves reported ads needing review by ramblur personnel
	 * 
	 * @return array
	 */
	function getAllActiveRamblurReportedAds() {
		$this->load->model('group');
		
		$this->db->select("a.adId, arAll.adArchiveId, a.filename, a.style, a.headerText AS 'title', a.footerText AS 'description'")
			->from('Advert AS a')
			->join('adArchive AS arAll', 'arAll.adId = a.adId')
			->join('adArchive AS arRecent', 'arAll.adId = arRecent.adId AND arAll.modifiedDate < arRecent.modifiedDate', 'LEFT')
			->join('adToForum AS af', 'af.adId = a.adId', 'LEFT')
			->join('Subject AS s', 's.subjectId = af.forumId AND s.type != ' . group::TYPE_RAMBLUR, 'left')
			->where(array(
				's.subjectId' => NULL,
				'a.modUnderReview' => 1, // @todo - what about ads reported that have been changed since they were reported? Just ignore indefinitely?
				'arRecent.adArchiveId' => NULL
			));
		return $this->db->get()->result_array();
		
	}
	
	/**
	 * Retrieves reported ads needing review in the given subject. Only ads from nonRamblur forums to be retrieved in this way.
	 * 
	 * @param int $subjectId
	 * 
	 * @return array
	 */
	function getNonRamblurSubjectActiveReportedAds($subjectId) {
		$this->load->model('group');
		
		$this->db->select("a.adId, arAll.adArchiveId, a.filename, a.style, a.headerText AS 'title', a.footerText AS 'description'")
			->from('Advert AS a')
			->join('adArchive AS arAll', 'arAll.adId = a.adId')
			->join('adArchive AS arRecent', 'arAll.adId = arRecent.adId AND arAll.modifiedDate < arRecent.modifiedDate', 'LEFT')
			->join('adToForum AS af', 'af.adId = a.adId')
			->join('Subject AS s', 's.subjectId = af.forumId AND s.type != ' . group::TYPE_RAMBLUR)
			->where(array(
				's.subjectId' => $subjectId,
				'a.modUnderReview' => 1,
				'arRecent.adArchiveId' => NULL
		));
		return $this->db->get()->result_array();
	}
	
	/**
	 * Returns details for a reported ad.
	 * 
	 * @param int|string $adArchiveId
	 * @return boolean|array
	 */
	function getReportedAdDetails($adArchiveId) {
		$data = array();
		$adId = $this->getAdId($adArchiveId);
		if(!$adId) {
			return false; 
		}
		// @todo - Gets current ad. Need reported ad instead/as well
		$ad = $this->ad->getAdDetails($adId)[0];
		if(is_null($ad)) {
			return false;
		}
		$data['ad'] = $ad;
		$data['ad']['user'] = $this->user->info($ad['userId'], true);
		// Reviews for this ad
		$this->db->select('id, reviewerId, reviewResult, reviewDate')
			->from('ReportedAdReviews')
			->where('adId', $adId);
		
		$data['reviews'] = $this->db->get()->result_array();
		
		$reasons = $this->getReportReasons($adArchiveId);
		$data['reportReasons'] = array();
		foreach($reasons as $r) {
			$data['reportReasons'][] = $r['reason'];
		}

		// How many times has this ad been reported, in any form?
		$this->db->select("COUNT(id) AS 'reportCount'")
			->from('ReportedAds')
			->where('adId', $adId);
		$data['reportCount'] = (int)$this->db->get()->first_row('array')['reportCount'];
		
		// Ad reports for this user
		$this->db->select("COUNT(DISTINCT a.adId) AS 'adCount'")
			->from('ReportedAds AS r')
			->join('Advert AS a', 'a.adId = r.adId')
			->where('a.userId', $ad['userId']);
		$data['previousReportCount'] = (int)$this->db->get()->first_row('array')['adCount'];
		
		// Ad review results for this user
		$data['previousWarnings'] = 0;
		$data['previousRemovals'] = 0;
		
		$this->db->select("r.reviewResult")
			->from('ReportedAdReviews AS r')
			->join('Advert AS a', 'a.adId = r.adId')
			->where('a.userId', $ad['userId']);
		$reviewResults = $this->db->get()->result_array();
		foreach($reviewResults as $r) {
			if($r['reviewResult'] === 'WARNING') {
				$data['previousWarnings']++;
			} else if($r['reviewResult'] === 'REMOVE') {
				$data['previousRemovals']++;
			}
		}
		
		// Number of ads this user has booked
		$this->db->select("COUNT(adId) AS 'adCount'")
			->from('Advert')
			->where('userId', $ad['userId']);
		$data['adCount'] = (int)$this->db->get()->first_row('array')['adCount'];
		
		$data['adArchiveId'] = $adArchiveId;
		
		return $data;
	}
	
	/**
	 * Returns the report/review history of the given ad.
	 * 
	 * @param int|string $adId
	 * @return array
	 */
	function getReportedAdHistory($adId) {
		$this->db->select("
				a.adArchiveId,
				a.fileName,
				a.userId AS 'creatorId',
				a.url,
				a.status,
				a.headerText as 'title',
				a.footerText AS 'description',
				a.style,
				a.type,
				a.modifiedDate,
				r.id AS 'reviewId',
				r.reviewResult,
				r.reviewerId,
				r.reviewDate,
				u.userName AS 'reviewerUserName'")
			->from('adArchive AS a')
			->join('ReportedAdReviews AS r', 'r.adArchiveId = a.adArchiveId', 'LEFT')
			->join('User AS u', 'u.userId = r.reviewerId', 'LEFT')
			->where('a.adId', $adId)
			->order_by('a.modifiedDate', 'DESC');
		$archived = $this->db->get()->result_array();
		
		return $archived;
	}
	
	/**
	 * Handles an ad review, which can either be ignored, result in a warning to the ad creator,
	 * and require an edit, or result in the ad being removed permanently from circulation.
	 * 
	 * @param array $review <br>
	 * 		['archiveAdId'] string - id of the archived ad being reviewed <br>
	 * 		['result'] string - result of the review. Either ignore, warn or remove <br>
	 * 		['reportingUserAction'] string - (optional) Action to be taken on the users who reported the ad in its current form.
	 * 
	 * @param mixed $error
	 * 
	 * @return boolean
	 */
	function review($review, $archiveAdId, &$error) {
		$result = $review['result'];
		$adArchiveId = $review['adArchiveId'];
		
		$this->db->select("r.adId, r.userId AS 'reporterId', r.subjectId, a.userId as 'creatorId'")
			->from('ReportedAds AS r')
			->join('Advert AS a', 'a.adId = r.adId')
			->where('r.adArchiveId', $adArchiveId);
		$reportedAds = $this->db->get();
		if($reportedAds->num_rows() === 0) {
			$error = 'No reports for that ad have been recorded.';
			return false;
		}
		$reportedAds = $reportedAds->result_array();
		$adId = $reportedAds[0]['adId'];
		$adCreatorId = $reportedAds[0]['creatorId'];
		$subjectId =  $reportedAds[0]['subjectId'];
		
		// block if already reviewed
		$this->db->select('id')
			->from('ReportedAdReviews')
			->where('adArchiveId', $adArchiveId);
		if($this->db->get()->num_rows() > 0) {
			$error = 'This ad has already been reviewed in its current form and cannot be reviewed again unless the creator changes it.';
			return false;
		}
		
		// Set up ad update so that it will be marked as reviewed, regardless of further actions
		$updateAdFields = array('modUnderReview' => 0);

		$reportingUserMsgData = array();
		$reportingUserBan = false;

		if($result === 'ignore') {
			if (!isset($review['reportingUserAction'])) {
				$error = 'reportingUserAction must be set when ignoring ad reports.';
				return false;
			}
			// determine which message to send to reporting users
			if($review['reportingUserAction'] === 'none') {
				$reportingUserMsg = message::MSG_ADREPORT_THANK;
			} else if($review['reportingUserAction'] === 'warn') {
				$reportingUserMsg = message::MSG_ADREPORT_WARNED;
			} else if ($review['reportingUserAction'] == 28 || $review['reportingUserAction'] == 90) {
				$reportingUserMsg = message::MSG_ADREPORT_BANNED;
				$reportingUserMsgData['banPeriod'] = $review['reportingUserAction'];
				$reportingUserBan = $review['reportingUserAction'];
			} else {
				$error = 'The action for the reporting users is not valid.';
				return false;
			}
			$reportedUserMsg = message::MSG_ADREPORT_RESTORE;
			
			// restore ad
			$updateAdFields['modPaused'] = 0;
			
			$returnData = array('ignored' => true);
		} else if ($result === 'warn') {
			
			$reportingUserMsg = message::MSG_ADREPORT_THANK;
			$reportedUserMsg = message::MSG_ADREPORT_REPOST;
			$returnData = array('warned' => true);
			// ad remains paused, so no ad update necessary
			
		} else if ($result === 'remove') {
			
			$reportingUserMsg = message::MSG_ADREPORT_THANK;
			$reportedUserMsg = message::MSG_ADREPORT_REMOVE;
			$returnData = array('removed' => true);
			
			// unpause ad, and remove
			$updateAdFields['modPaused'] = 0;
			$updateAdFields['modRemoved'] = 1;
		} else {
			$error = 'That action is not valid.';
			return false;
		}
	
		// update ad
		$this->db->update('Advert',
			$updateAdFields,
			array('adId' => $adId)
		);
	
		// store review
		$this->load->helper(array('htmlpurifier'));
		$this->db->insert('ReportedAdReviews',
			array(
			'adId' => $adId,
			'adArchiveId' => $adArchiveId,
			'reviewerId' => $this->user->authCheck(),
			'reviewResult' => html_purify(mb_strtoupper($result),'post')
		));
		
		// send message to reportingUsers
		if (isset($reportingUserMsg)) {
			$to = array();
			foreach($reportedAds as $a) {
				$to[] = $a['reporterId'];
				if($reportingUserBan) {
					$this->user->banFromSubject($a['reporterId'], $subjectId, $reportingUserBan);
				}
			}
			$this->message->systemMessage($this->user->authCheck(), $to, $subjectId, $reportingUserMsg, $reportingUserMsgData);
		}
		
		// send message to ad creator
		if (isset($reportedUserMsg)) {
			$this->message->systemMessage($this->user->authCheck(), $adCreatorId, $subjectId, $reportedUserMsg, [], ['id' => $adArchiveId, 'type' => 'adArchive']);
		}
		
		if($this->db->affected_rows() > 0) {
			return $returnData;
		} else {
			return false;
		}
	}
	
	/**
	 * Retrieves ad reviews for ads targeted to the given subject. Should be used only for nonRamblur forums, by the creator/owner of the forum.
	 * 
	 * @param int $subjectId
	 * 
	 * @return array
	 */
	function getSubjectReportedAdReviews($subjectId) {
		$this->db->select('r.id')
			->from('ReportedAdReviews AS r')
			->join('adToForum AS f', 'f.adId = r.adId')
			->where('f.forumId', $subjectId)
			->order_by('reviewDate', 'DESC');;
		return $this->db->get()->result_array();
	}
	
	/**
	 * Retrieves ad reviews carried out by the given user. Should be used only for admins/managers.
	 * 
	 * @param int $userId
	 * 
	 * @return array
	 */
	function getUserReportedAdReviews($userId) {
		$this->db->select('id')
			->from('ReportedAdReviews')
			->where('reviewerId', $userId)
			->order_by('reviewDate', 'DESC');
		return $this->db->get()->result_array();
	}
	
	/**
	 * Retrieves a specific ad review.
	 * 
	 * @param int|string $reviewId
	 * @return array
	 */
	function getAdReview($reviewId) {
		$this->db->select('id, adId, adArchiveId, reviewerId, reviewResult, reviewDate')
			->from('ReportedAdReviews')
			->where('id', $reviewId)
			->order_by('reviewDate', 'DESC');
		$review = $this->db->get()->first_row('array');
		
		// These details need to be from adArchive table ie. show ad contents at time of review, not time of retrieval
		$review['adArchive'] = $this->getAdArchiveDetails($review['adArchiveId']);
		$reasons = $this->getReportReasons($review['adArchiveId']);
		$review['reportReasons'] = array();
		foreach($reasons as $r) {
			$review['reportReasons'][] = $r['reason'];
		}
		unset($review['adId']);
		return $review;
	}
	
	/****************************** HELPERS **********************************/
	
	/**
	 * Retrieves the current adArchive row for the given ad.
	 * 
	 * @param int|string $adId
	 * @return boolean|array
	 */
	function getCurrentAdArchiveId($adId) {
		$this->db->select('adArchiveId')
			->from('adArchive')
			->where('adId', $adId)
			->order_by('modifiedDate', 'DESC')
			->limit(1);
		$ad = $this->db->get();
		if($ad->num_rows() === 0) {
			return false;
		} else {
			return $ad->first_row('array')['adArchiveId'];
		}
	}
	
	/**
	 * Returns the id of the ad related to the given adArchiveId.
	 * 
	 * @param int|string $adArchiveId
	 * @return boolean|string
	 */
	function getAdId($adArchiveId) {
		$this->db->select('adId')
			->from('adArchive')
			->where('adArchiveId', $adArchiveId);
		$ad = $this->db->get();
		if($ad->num_rows() === 0) {
			return false;
		} else {
			return $ad->first_row('array')['adId'];
		}
	}
	
	/**
	 * Returns all the reasons the given adArchive has been reported.
	 * 
	 * @param unknown $adArchiveId
	 */
	function getReportReasons($adArchiveId) {
		$this->db->select('reason')
			->from('ReportedAds')
			->where('adArchiveId', $adArchiveId);
		return $this->db->get()->result_array();
	}
	
	/**
	 * Returns whether or not a user is allowed to review an ad. They can if they have admin or manager privileges,
	 * or if they own a forum the ad is targeted to, and the ad is not targeted to any Ramblur forums.
	 * ie. only managers handle ads targeted to ramblur forums, even if they are also targeted to nonramblur forums.
	 *
	 * @param int $adId
	 * @param int $userId
	 * @return boolean
	 */
	function canReviewAd($adId, $userId) {
		$userLevel = $this->user->getUserLevel($userId);
		if($userLevel === user::USER_ADMIN || $userLevel === user::USER_MANAGER) {
			return true;
		}
	
		$this->load->model('group');
		$this->db->select("s.subjectId")
			->from('adToForum AS a')
			->join('Subject AS s', 'a.forumId = s.subjectId')
			->join('Subject AS t', 't.subjectId = a.forumId AnD t.`type` = '.group::TYPE_RAMBLUR)
			->where(array(
				'a.adId' => $adId,
				's.createdBy' => $userId,
				't.subjectId' => NULL
		));
		return $this->db->get()->num_rows() > 0;
	}
	
	/**
	 * Returns details for the given adArchive.
	 * 
	 * @param int|string $adArchiveId
	 * @return array|boolean
	 */
	function getAdArchiveDetails($adArchiveId) {
	
		$this->db->select("
			a.adId AS 'id',
			a.adArchiveId,
			a.userId,
			a.mainAdCategory,
			a.type,
			a.style,
			a.url,
			a.filename AS 'media',
			a.deviceFileName,
			ad.modUnderReview AS 'underReview',
			ad.modPaused AS 'paused',
			ad.modRemoved AS 'removed',
			a.headerText AS 'title',
			a.footerText AS 'description',
			a.status
		")
		->select("
			DATE_FORMAT(a.startDate,'%b %d %Y %h:%i:%s %p') AS 'startDate',
			DATE_FORMAT(a.endDate,'%b %d %Y %h:%i:%s %p') AS 'endDate'
		", false)
		->from('adArchive AS a')
		->join('Advert AS ad', 'ad.adId = a.adId')
		->where('a.adArchiveId', $adArchiveId);
	
		$query = $this->db->get();
	
		if ($query->num_rows() > 0) {
			$result = $query->first_row('array');
			$result['underReview'] = (boolean)$result['underReview'];
			$result['paused'] = (boolean)$result['paused'];
			$result['removed'] = (boolean)$result['removed'];
			$result['user'] = $this->user->info($result['userId'], true);
			return $result;
		} else {
			return false;
		}
	}
}