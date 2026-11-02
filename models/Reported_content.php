<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Reported_content extends CI_Model {
	
	// Defines the total number of users required to report something for extreme content before it will be removed pending review
	const REPORT_EXTREME_COUNT = 6;
	
	/**
	 * Creates a report for user content. Requires contentType (node or file), reason, reporterUserId and id.
	 * 
	 * @param array $data
	 * @return booleanl
	 */
	function createReport(array $data, &$error) {
		
		if(!(isset($data["reason"]) && isset($data["reportingUserId"]) && isset($data["id"]) && isset($data['contentType']))) {
			$error = 'reason, reportingUserId, id and contentType must be set.';
			return false;
		}
		if(!($data['contentType'] === 'node' || $data['contentType'] === 'file')) {
			$error = 'Only nodes and files can be reported.';
			return false;
		}
		$id = $data["id"];
		$this->load->helper(array('htmlpurifier'));
		$contentType = html_purify($data['contentType'],'post');
		$reporterUserId = $data["reportingUserId"];
		$data["reason"] = mb_strtoupper($data["reason"]);
		if(!in_array($data["reason"], $this->getReasons())) {
			$data["reason"] = 'OTHER';
		}
		// Check the content exists
		if($contentType === 'file') {
			$this->db->select('userId')
				->from('Content')
				->where('fileName', $id);
			$file = $this->db->get();
			if($file->num_rows() > 0) {
				$reportedUserId = $file->result_array()[0]["userId"];
			} else {
				$error = 'The file being reported could not be found.';
				return false;
			}
		} else {
			$this->db->select('n.userId')
				->from('Nodes AS n')
				->join('Post AS p', 'p.postId = n.postId')
				->where('n.nodeId', $id)
				->where_not_in('p.postType', ['message', 'subscription_msg']);
			$node = $this->db->get();
			if($node->num_rows() > 0) {
				$reportedUserId = $node->result_array()[0]["userId"];
			} else {
				$error = 'The node being reported could not be found.';
				return false;
			}
		}
		// Check they're not reporting themselves.
		if($reportedUserId == $reporterUserId) {
			$error = 'You cannot report content you created.';
			return false;
		}
		
		// Check if content has been previously reported by any user
		$existingReport = $this->getReportedContentByContentId($id, $contentType);
		if(!$existingReport) {
			$this->db->insert('ReportedContent', array(
				'reportedUserId' => $reportedUserId
			));
			$reportedContentId = $this->db->insert_id();
			$this->db->insert('Reported' . ucfirst($contentType) . 's', array(
				'reportedContentId' => $reportedContentId,
				$contentType === 'file' ? 'fileName' : 'nodeId' => $id
			));
		} else {
			$reportedContentId = $existingReport['reportedContentId'];
		}
		// Insert user details
		$this->db->insert('ReportedContentToUser', array(
			'reportedContentId' => $reportedContentId,
			'reportingUserId' => $reporterUserId,
			'reason' => $data['reason']
		));
		
		// If reason is 'extreme' and has been reported 6 times total, deactivate
		if(mb_strtoupper($data['reason']) === 'EXTREME') {
			$this->db->select("COUNT(DISTINCT reportingUserId) AS 'count'")
				->from('ReportedContentToUser')
				->where(array(
					'reportedContentId' => $reportedContentId,
					'reason' => 'EXTREME'
				));
			// mark content as deleted
			if($this->db->get()->first_row('array')['count'] == self::REPORT_EXTREME_COUNT) {
				$this->db->update(
					$contentType === 'file' ? 'Content' : 'Nodes',
					array(
						$contentType === 'file' ? 'modDeleted' : 'deleted' => 1,
						$contentType === 'file' ? 'modDeleteReason' : 'deleteReason' => 'PENDING'
					),
					array(
						$contentType === 'file' ? 'fileName' : 'nodeId' => $data['id']
					)
				);
				// mark all attachents as deleted as well
				if($contentType !== 'file') {
					$this->db->update('ContentToNodes',
						array(
							'deleted'=> 1,
							'deleteReason' => 'PENDING'
						),
						array('nodeID' => $data['id'])
					);
				}
			}
		}
		return true;
	}
	
	/**
	 * Creates a complaint regarding a review that has been carried out.
	 * 
	 * @param int $reportedContentId
	 * @param int $reviewId
	 * @return boolean
	 */
	function createComplaint($reportedContentId, $reviewId, &$error) {
		
		$reportedContent = $this->getReportedContent($reportedContentId);
			
		if(!$reportedContent) {
			$error = "That content has not been reported, and so a complaint cannot be made."; // Must have been reported
			return false;
		}
		
		$reportedUserId = $reportedContent["reportedUserId"];
		if($reportedUserId != $this->user->authCheck()) {
			$error = "You can only make complaints about content you created."; // Only author can make complaint
			return false;
		}
		$this->db->from('ReportedContentReviewsToUser')
			->where('id', $reviewId);
		$review = $this->db->get();
		if($review->num_rows() == 0) {
			$error = "You can only make complaints about content that has been reported and reviewed."; // Can't complain until reviewed
			return false;
		}
		
		$fields = array(
			'reportedContentId' => $reportedContentId,
			'reportedReviewId' => $reviewId
		);
		$this->db->from('ReportComplaints')
			->where($fields);
		$existing = $this->db->get();
		if($existing->num_rows() > 0) {
			$error = "You have already made a complaint regarding this action, and can't do so again."; // Allow one complaint per review
			return false;
		}
		
		$this->db->insert('ReportComplaints', $fields);
		return $this->db->affected_rows() > 0;
	}
	
	/**
	 * Retrieves all reported content available (inactive AND active) to moderators for the given subject, ordered by date.
	 * @param int $subjectId
	 * @param string $countryId
	 * @return array
	 */
	function getAllModeratorSubjectReports($subjectId, $countryId = NULL) {
		$activeReports = $this->getAllModeratorActiveSubjectReports($subjectId);
		$inactiveReports = $this->getAllModeratorInactiveSubjectReports($subjectId);
		
		$allReports = array_merge($activeReports, $inactiveReports);
		usort($allReports, function($a, $b) {
			// Since date formats are directly from mysql, should be ok to do a simple comparison
			return $b['lastActiveDate'] <=> $a['lastActiveDate'];
		});
		
		return $allReports;
	}
	
	/**
	 * Retrieves all active reported content available to moderators for the given subject, ordered by date.
	 * @param int $subjectId
	 * @param string $countryId
	 * @return array
	 */
	function getAllModeratorActiveSubjectReports($subjectId, $countryId = NULL) {
		
		$sql = is_array($subjectId) ? $this->getActiveReportQuery('moderator', $subjectId) : $this->getActiveReportQuery('moderator', null);

		/* Extreme content reports, complaints and reports about moderator content have been filtered out in the HAVING clause.
		 	Return reports if any of the following conditions are met:
			a) they are being currently reviewed
			b) they have never been reviewed and the report reason is personal abuse OR a moderator (or higher) has reported it
				// what if it has been reviewed before and a mod has rereported it? Want it to go straight through
			c) a % threshold has been reached. Nb. This will include rereported mod reports and abuse reports @todo - Check if this is ok
		*/
		$params = array($subjectId, $subjectId);
		if(!is_null($countryId)) {
			$params[] = $countryId;
		}
		$allReports = $this->db->query($sql, $params)->result_array();
		$activeReports = array();
		foreach($allReports as $a) {
			if(!is_null($a['currentReviewId']) || $this->reportRowMeetsActiveRequirements($a, 'moderator')) {
				$a['majorityReportReason'] = $this->getMajorityReason($a, 'moderator');
				$a['active'] = true;
				$a['viewed'] = (boolean)$a['viewed'];
				$activeReports[] = $a;
			}
		}
		return $activeReports;
	}
	
	/**
	 * Returns supervisor specific reports ie. extreme content reports, complaints regarding moderator actions, and reported content 
	 * originally created by a moderator.
	 * @todo - make sure extreme content reports and complaints are only counted as active once.
	 * 
	 * @param id $supervisorId
	 * @return array
	 */
	function getAllSupervisorActiveReports($supervisorId) {
		
		$subjects = array_map(function($v){return (int)$v['id'];}, $this->user->getManageableSubjects($supervisorId));
		
		$sql = $this->getActiveReportQuery('supervisor', $subjects);
		
		/* Reports that should go to moderators have already been filtered out in the HAVING clause in the sql.
		 * Return reports if any of the following conditions are met:
		 * a) they are being currently reviewed
		 * b) they have never been reviewed and the report reason is extreme content or a complaint about a moderator
		 * c) a % threshold has been reached. Nb. This will include rereported mod reports and abuse reports @todo - Check if this is ok
		 */
		$allReports = $this->db->query($sql)->result_array();
		$activeReports = array();
		foreach($allReports as $a) {
			if((!is_null($a['currentReviewId']) || $this->reportRowMeetsActiveRequirements($a, 'supervisor'))) {
				$a['majorityReportReason'] = $this->getMajorityReason($a, 'supervisor');
				$activeReports[] = $a;
			}
		}
		return $activeReports;
	}
	
	/**
	 * Returns reports that are from unmoderated subjects that the supervisor is assigned to
	 *
	 * @param id $supervisorId
	 * @return array
	 */
	function getAllSupervisorUnmoderatedActiveReports($supervisorId) {
		$unmoderatedSubjects = $this->user->getSupervisorUnmoderatedSubjects($supervisorId);
		if (count($unmoderatedSubjects) === 0) {
			return [];
		}
		$subjects = array_map(function($v){return (int)$v['id'];}, $unmoderatedSubjects);

		$sql = $this->getActiveReportQuery('moderator', $subjects);
	
		/* Extreme content reports, complaints and reports about moderator content have been filtered out in the HAVING clause.
		 	Return reports if any of the following conditions are met:
			a) they are being currently reviewed
			b) they have never been reviewed and the report reason is personal abuse OR a moderator (or higher) has reported it
				// what if it has been reviewed before and a mod has rereported it? Want it to go straight through
			c) a % threshold has been reached. Nb. This will include rereported mod reports and abuse reports @todo - Check if this is ok
		*/
		$allReports = $this->db->query($sql)->result_array();
		$activeReports = array();
		foreach($allReports as $a) {
			if(!is_null($a['currentReviewId']) || $this->reportRowMeetsActiveRequirements($a, 'moderator')) {
				$a['majorityReportReason'] = $this->getMajorityReason($a, 'moderator');
				$activeReports[] = $a;
			}
		}
		return $activeReports;
	}
	
	/**
	 * Returns manager specific reports ie. complaints regarding supervisor actions and content
	 * originally created by a supervisor.
	 *
	 * @return array
	 */
	function getAllManagerActiveReports() {
		$sql = $this->getActiveReportQuery('manager', true);
		
		 /* Return reports if any of the following conditions are met:
		 * a) they are being currently reviewed
		 * b) they have never been reviewed and it is a complaint about a supervisor
		 */
		$allReports = $this->db->query($sql)->result_array();
		$activeReports = array();
		foreach($allReports as $a) {
			if((!is_null($a['currentReviewId']) || $this->reportRowMeetsActiveRequirements($a, 'manager'))) {
				$a['majorityReportReason'] = $this->getMajorityReason($a, 'manager');
				$activeReports[] = $a;
			}
		}
		return $activeReports;
	}
	
	/**
	 * Retrieves all inactive reported content for the given subject for a moderator, ordered by date.
	 * @param int $subjectId
	 * @param string $countryId
	 * @return array
	 */
	function getAllModeratorInactiveSubjectReports($subjectId, $countryId = NULL) {
		
		$sql = is_array($subjectId) ? $this->getInactiveReportQuery('moderator', $subjectId, $countryId) : $this->getInactiveReportQuery('moderator', null, $countryId);
		
		$params = array($subjectId, $subjectId);
		if(!is_null($countryId)) {
			$params[] = $countryId;
		}
		$reports = $this->db->query($sql, $params);
		if($reports->num_rows() > 0) {
			$reports = $reports->result_array();
			$inactiveReviews = array();
			foreach($reports as $r) {
				// Haven't been reported by a mod since last review and hasn't hit new threshold
				if($r['moderatorReportedCount'] == 0 && $this->reportRowMeetsInactiveRequirements($r)) {
					$r['active'] = false;
					$r['currentReviewId'] = null;
					$r['majorityReportReason'] = $this->getMajorityReason($r, 'moderator');
					$r['viewed'] = (boolean)$r['viewed'];
					$inactiveReviews[] = $r;
					
					// If viewed is true, reset to false, so it will show up as unviewed if the next threshold is triggered
					if ($r['viewed']) {
						$this->setViewedThisThreshold($r['reportId'], false);
					}
				}
			}
			return $inactiveReviews;
		} else {
			return array();
		}
	}
	
	/**
	 * Retrieves all inactive reported content for a supervisor, ordered by date.
	 * @param int $supervisorId
	 * @return array
	 */
	function getAllSupervisorInactiveReportIds($supervisorId) {
		
		$subjects = array_map(function($v){return (int)$v['id'];}, $this->user->getManageableSubjects($supervisorId));
		
		$sql = $this->getInactiveReportQuery('supervisor', $subjects);
		
		$reports = $this->db->query($sql);
		if ($reports->num_rows() > 0) {
			$reports = $reports->result_array();
			$inactiveReviewIds = array();
			foreach($reports as $r) {
				$lastReviewPercentage = $r['latestPctUsersReported'];
				$currentPercentage = $r['currentPercentage'];
				// Hasn't hit next % threshold and hasn't been reported by a mod since last review or has been reviewed for extreme content
				if ($r['extremeReviewed'] == 1 || ($r['moderatorReportedCount'] == 0 && $r['authorIsModerator'] && $this->reportRowMeetsInactiveRequirements($r))) {
					$inactiveReviewIds[] = array('id' => $r['reportId'], 'type' => $r['complaintCount'] > 1 ? 'complaint' : $r['extremeCount'] >= self::REPORT_EXTREME_COUNT ? 'extreme' : 'modAuthor');
				}
			}
			return $inactiveReviewIds;
		} else {
			return array();
		}
	}
	
	/**
	 * Retrieves all inactive reported content in subjects assigned to the supervisor that don't have a moderator.
	 * @param int $supervisorId
	 * @return array
	 */
	function getAllSupervisorUnmoderatedInactiveReportIds($supervisorId) {
		
		$unmoderatedSubjects = $this->user->getSupervisorUnmoderatedSubjects($supervisorId);
		if (count($unmoderatedSubjects) === 0) {
			return [];
		}
		$subjects = array_map(function($v){return (int)$v['id'];}, $unmoderatedSubjects);
		
		$sql = $this->getInactiveReportQuery('moderator', $subjects);

		$reports = $this->db->query($sql);
		if($reports->num_rows() > 0) {
			$reports = $reports->result_array();
			$inactiveReviewIds = array();
			foreach($reports as $r) {
				// Haven't been reported by a mod since last review and hasn't hit new threshold
				if($r['moderatorReportedCount'] == 0 && $this->reportRowMeetsInactiveRequirements($r)) {
					$inactiveReviewIds[] = array('id' => $r['reportId']);
				}
			}
			return $inactiveReviewIds;
		} else {
			return array();
		}
	}
	
	/**
	 * Retrieves all inactive reported content for a manager, ordered by date.
	 * @param int $managerId
	 * @return array
	 */
	function getAllManagerInactiveReportIds($managerId) {
		$sql = $this->getInactiveReportQuery('manager', true);
		
		$reports = $this->db->query($sql);
		if ($reports->num_rows() > 0) {
			$reports = $reports->result_array();
			$inactiveReviewIds = array();
			foreach($reports as $r) {
				$lastReviewPercentage = $r['latestPctUsersReported'];
				$currentPercentage = $r['currentPercentage'];
				// Hasn't hit next % threshold and hasn't been reported by a mod since last review
				if (($r['moderatorReportedCount'] == 0 && $r['authorIsSupervisor'] && $this->reportRowMeetsInactiveRequirements($r))) {
					$inactiveReviewIds[] = array('id' => $r['reportId']);
				}
			}
			return $inactiveReviewIds;
		} else {
			return array();
		}
	}
	
	/**
	 * Retrieves details for all reported content that should have been acted upon at the time of retrieval.
	 * Overdue is defined as >15 days old or between 5 and 6 % reported, or between 30 and 31 % reported
	 * @return array
	 */
	function getOverdueReportIds() {
		$sql = "
			SELECT 
			`r`.`id`,
			`r`.`reportedUserId`,
			`us`.`userName`,
			`vc`.`id` AS 'currentReviewId',
			IF(`authorPer`.`canBeModerator` AND ANY_VALUE(`authorModSub`.`userId`) IS NOT NULL, 1, 0) AS 'authorIsModerator',
			IF(`authorPer`.`canBeSupervisor` AND ANY_VALUE(`authorSuperSub`.`userId`) IS NOT NULL, 1, 0) AS 'authorIsSupervisor',
			ROUND(COUNT(DISTINCT `u`.`reportingUserId`) / COUNT(DISTINCT `s`.`userId`) * 100, 0) AS 'currentPercentage',
			# reports counted in total once per user, but user can report for multiple reasons and be counted each reason
			COUNT(DISTINCT `u`.`reportingUserId`) AS 'reportCount',
			COUNT(DISTINCT CASE WHEN `u`.`reason` = 'ADVERTISING' THEN `u`.`reportingUserId` END) AS 'advertisingCount',
			COUNT(DISTINCT CASE WHEN `u`.`reason` = 'TROLLING' THEN `u`.`reportingUserId` END) AS 'trollingCount',
			COUNT(DISTINCT CASE WHEN `u`.`reason` = 'ABUSE' THEN `u`.`reportingUserId` END) AS 'abuseCount',
			COUNT(DISTINCT CASE WHEN `u`.`reason` = 'INAPPROPRIATE' THEN `u`.`reportingUserId` END) AS 'inappropriateCount',
			COUNT(DISTINCT CASE WHEN `u`.`reason` = 'ADULT' THEN `u`.`reportingUserId` END) AS 'adultCount',
			COUNT(DISTINCT CASE WHEN `u`.`reason` = 'EXTREME' THEN `u`.`reportingUserId` END) AS 'extremeCount',
			COUNT(DISTINCT CASE WHEN `u`.`reason` = 'SPAM' THEN `u`.`reportingUserId` END) AS 'spamCount',
			COUNT(DISTINCT CASE WHEN `u`.`reason` = 'INAPBHVR' THEN `u`.`reportingUserId` END) AS 'inapbhvrCount',
			COUNT(DISTINCT CASE WHEN `u`.`reason` = 'OTHER' THEN `u`.`reportingUserId` END) AS 'otherCount',
			COUNT(DISTINCT `rc`.`id`) AS 'complaintCount',
			COUNT(DISTINCT IF(`rc`.`resolvedReviewId` IS NULL, `rc`.`id`, NULL)) AS 'activeComplaintCount',
			`u2`.`mostRecentReportDate`, 
			`v`.`latestPctUsersReported` AS 'lastReviewPercentage', 
			`v`.`latestReviewDate` AS 'lastReviewDate'
			FROM `ReportedContent` AS `r` 
			JOIN `ReportedContentToUser` AS `u` ON `u`.`reportedContentId` = `r`.`id`
			JOIN (
				SELECT
				`reportedContentId`,
				MAX(`reportDate`) AS 'mostRecentReportDate'
				FROM `ReportedContentToUser`
				GROUP BY `reportedContentId`
				) AS `u2` ON `u2`.`reportedContentId` = `u`.`reportedContentId`
			LEFT JOIN `ReportedNodes` AS `rn` ON `rn`.`reportedContentId` = `r`.`id`
			LEFT JOIN `Nodes` AS `n` ON `n`.`nodeId` = `rn`.`nodeId`
			LEFT JOIN `Post` AS `p` ON `p`.`postId` = `n`.`postId`
			LEFT JOIN `ReportedFiles` AS `rf` ON `rf`.`reportedContentId` = `r`.`id`
			LEFT JOIN `Content` AS `f` ON `f`.`fileName` = `rf`.`fileName`
			JOIN `Subject` AS `sub` ON `sub`.`subjectId` = `p`.`subjectId` OR `sub`.`subjectId` = `f`.`subjectId`
			JOIN `UserToSubject` AS `s` ON `s`.`subjectId` = `sub`.`subjectId`
			JOIN `User` AS `us` ON `us`.`userId` = `r`.`reportedUserId`
			JOIN `Permissions` AS `authorPer` ON `authorPer`.`userId` = `us`.`userId`
			LEFT JOIN `ModeratorToSubject` AS `authorModSub` ON `authorModSub`.`subjectId` = `sub`.`subjectId` AND `authorModSub`.`userId` = `us`.`userId` AND `authorModSub`.`moderator` = 1 AND `authorModSub`.`approved` = 1
			LEFT JOIN `SupervisorToSubject` AS `authorSuperSub` ON `authorSuperSub`.`subjectId` = `sub`.`subjectId` AND `authorSuperSub`.`userId` = `us`.`userId`
			LEFT JOIN (
				SELECT `a`.*
				FROM `ReportedContentReviews` AS `a`
				LEFT JOIN `ReportedContentReviews` AS `b` ON `a`.`reportedContentId` = `b`.`reportedContentId` AND `a`.`latestReviewDate` < `b`.`latestReviewDate`
				WHERE `b`.`id` IS NULL 
				AND `a`.`latestUserBan` IS NOT NULL
				AND `a`.`latestUserWarning` IS NOT NULL
				AND `a`.`latestUserAutoflag` IS NOT NULL
				AND `a`.`latestPostDeleted` IS NOT NULL
				AND `a`.`latestAttachmentDeleted` IS NOT NULL
				AND `a`.`latestPostEdited` IS NOT NULL
				AND `a`.`latestThreadDeleted` IS NOT NULL
				) AS `v` ON `v`.`reportedContentId` = `r`.`id`
			LEFT JOIN `ReportedContentReviews` AS `vc` ON `vc`.`reportedContentId` = `r`.`id` AND (
				`vc`.`latestUserBan` IS NULL 
				OR `vc`.`latestUserWarning` IS NULL
				OR `vc`.`latestUserAutoflag` IS NULL
				OR `vc`.`latestPostDeleted` IS NULL
				OR `vc`.`latestAttachmentDeleted` IS NULL
				OR `vc`.`latestPostEdited` IS NULL
				OR `vc`.`latestThreadDeleted` IS NULL)
			LEFT JOIN `ReportComplaints` AS `rc` ON `rc`.`reportedContentId` = `r`.`id`
			WHERE `sub`.`type` = ".group::TYPE_RAMBLUR."
			GROUP BY `r`.`id`, `v`.`id`, `vc`.`id`
			HAVING (`authorIsSupervisor` = 0 AND !(`activeComplaintCount` > 0 AND `authorIsModerator` = 1)) AND (mostRecentReportDate < DATE_SUB(UTC_TIMESTAMP, INTERVAL 15 DAY) OR currentPercentage BETWEEN 5 AND 6 OR currentPercentage BETWEEN 30 and 31)
			ORDER BY `u2`.`mostRecentReportDate` DESC
		";
		
		/* Return reports if any of the following conditions are met:
		 * a) they are being currently reviewed
		 * b) a % threshold has been reached
		 */
		$allReports = $this->db->query($sql)->result_array();
		$activeReports = array();
		foreach($allReports as $a) {
			if((!is_null($a['currentReviewId']) || $this->reportRowMeetsActiveRequirements($a, 'manager'))) {
				// prioritise reasons
				if($a['activeComplaintCount'] > 0) {
					$a['majorityReportReason'] = 'complaint';
				} else if($a['extremeCount'] >= self::REPORT_EXTREME_COUNT) {
					$a['majorityReportReason'] = 'extreme';
				} else if($a['abuseCount'] > 0) {
					$a['majorityReportReason'] = 'abuse';
				} else {
					$maxCount = 0;
					$majorityReason = '';
					foreach($this->getReasons() as $r) {
						$reason = mb_strtolower($r);
						if($a[$reason.'Count'] > $maxCount) {
							$maxCount = $a[$reason.'Count'];
							$majorityReason = $reason;
						}
					}
					$a['majorityReportReason'] = $majorityReason;
				}
				$activeReports[] = $a;
			}
		}
		return $activeReports;
	}
	
	/**
	 * Retrieves details about reported content and the users who reported it.
	 * @param int $reportId
	 * @return boolean|array
	 */
	function getReportDetails($reportId) {
		$reportInfo = array();
		
		$this->db->select("
			r.id AS 'reportId', 
			r.reportedUserId,
			n.nodeId,
			p.postId,
			r.viewedThisThreshold AS 'viewed',
			p.modDeleted AS 'threadDeleted',
			h.nodeId AS 'headNodeId',
			ru.reportingUserId,
			MAX(ru.reportDate) AS 'reportDate',
			v.id AS 'currentReviewId',
			rb.userBan AS 'latestUserBan',
			rb.reasons AS 'banReasons',
			rw.userWarning AS 'latestUserWarning',
			rw.reasons AS 'warningReasons',
			rfl.userAutoflag AS 'latestUserAutoflag',
			re.postEdited AS 'latestPostEdited',
			re.reasons AS 'editReasons',
			rd.postDeleted AS 'latestPostDeleted',
			rd.reasons AS 'deleteReasons',
			ra.attachmentDeleted AS 'latestAttachmentDeleted',
			ra.reasons AS 'deleteAttachmentReasons',
			rt.threadDeleted AS 'latestThreadDeleted',
			rt.reasons AS 'deleteThreadReasons',
			v.latestReviewDate,
			u.userName,
			u.gender,
			u.avatar,
			u.countryId,
			pe.canBeModerator,
			pe.canBeSupervisor,
			pe.isManager,
			pe.isAdministrator
			")
		->select("IF(n.nodeId IS NOT NULL, n.nodeId, f.fileName) AS 'contentId',
			TIMESTAMPDIFF(YEAR, u.dob, CURDATE()) AS 'age',
			IF(p.subjectId IS NULL, f.subjectId, p.subjectId) AS 'subjectId',
			IF(`rn`.`reportedContentId` IS NULL, 'file', 'node') AS 'contentType',
			GROUP_CONCAT(`ru`.`reason`) as 'reasons'", false) // each user can report a node for multiple reasons
		->from('ReportedContent AS r')
		->join('ReportedContentToUser AS ru', 'ru.reportedContentId = r.id')
		->join('User AS u', 'u.userId = ru.reportingUserId')
		->join('Permissions AS pe', 'pe.userId = u.userId')
		->join('ReportedContentReviews AS v', 'v.reportedContentId = r.id AND (v.latestUserBan IS NULL OR v.latestUserWarning IS NULL OR v.latestPostDeleted IS NULL OR v.latestPostEdited IS NULL)', 'LEFT')
		->join('ReportedContentReviewsToUser AS rb', 'rb.id = v.latestUserBan', 'left')
		->join('ReportedContentReviewsToUser AS rw', 'rw.id = v.latestUserWarning', 'left')
		->join('ReportedContentReviewsToUser AS rfl', 'rfl.id = v.latestUserAutoflag', 'left')
		->join('ReportedContentReviewsToUser AS re', 're.id = v.latestPostEdited', 'left')
		->join('ReportedContentReviewsToUser AS rd', 'rd.id = v.latestPostDeleted', 'left')
		->join('ReportedContentReviewsToUser AS ra', 'ra.id = v.latestAttachmentDeleted', 'left')
		->join('ReportedContentReviewsToUser AS rt', 'rt.id = v.latestThreadDeleted', 'left')
		->join('ReportedNodes AS rn', 'rn.reportedContentId = r.id', 'left')
		->join('Nodes AS n', 'n.nodeId = rn.nodeId', 'left')
		->join('Nodes AS h', 'h.postId = n.postId AND h.nodeType = "rootNode"', 'left')
		->join('Post AS p', 'p.postId = n.postId', 'left')
		->join('ReportedFiles AS rf', 'rf.reportedContentId = r.id', 'left')
		->join('Content AS f', 'f.fileName = rf.fileName', 'left')
		->join('Subject AS s', 'p.subjectId = s.subjectId OR f.subjectId = s.subjectId')
		->where(array(
			'r.id' => $reportId
		))
		->group_by('ru.reportingUserId, h.nodeId, v.id');
		
		$result = $this->db->get();

		$numReports = $result->num_rows();
		if($numReports == 0) {
			return false;
		}

		$maxDate = 0;
		$reportingUsers = array();
		$reviewingUsers = array();
		$reasonCounts = array();
		foreach($this->getReasons() as $r) {
			$reasonCounts[mb_strtolower($r).'Count'] = 0;
		}
		foreach($result->result_array() as $r) {
			// Record user details
			$reasons = array_unique(explode(',', $r["reasons"]));
			$reportDate = strtotime($r["reportDate"]);
			$reportingUserId = $r["reportingUserId"];
			// Only include reporting users if the reason is personal attack or they are a moderator or above, as there would be too many otherwise
			//if(in_array('abuse', $reasons) || $r['canBeModerator'] || $r['canBeSupervisor'] || $r['isManager']) {
				$user = array();
				$user["id"] =$reportingUserId;
				$user["userName"] = $r["userName"];
				$user["reason"] = $this->getReasonLabel($reasons);
				$user["reportDate"] = date('Y-m-d H:i:s', $reportDate);
				if($r['isAdministrator']) {
					$user['level'] = 'admin';
				} else if ($r['isManager']) {
					$user['level'] = 'manager';
				} else if ($r['canBeSupervisor']) {
					$user['level'] = 'supervisor';
				} else if ($r['canBeModerator']) {
					$user['level'] = 'moderator';
				} else {
					$user['level'] = 'standard';
				}
				$user["age"] = $r["age"];
				$user["gender"] = $r["gender"];
				$user["avatar"] =  $r["avatar"] ? $r["avatar"].'-avatar' : '';
				$user["country"] = $r["countryId"];
				$reportingUsers[] = $user;
			//}
			if($reportDate > $maxDate) {
				
				$maxDate = $reportDate;
			}
			
			// Record how many reports for each reason
			foreach($reasons AS $reason) {
				$reasonCounts[mb_strtolower($reason).'Count']++;
			}
		}
		// Add generic report info
		$firstRow = $result->first_row('array');
		$reportFields = array('reportId', 'contentId', 'viewed', 'contentType', 'reportedUserId', 'subjectId', 'nodeId', 'postId', 'threadDeleted', 'headNodeId');
		foreach($reportFields as $f) {
			$reportInfo[$f] = $firstRow[$f];
		}
		$reportInfo['viewed'] = (boolean)$reportInfo['viewed'];
		
		// Get if the user is autoflagged in this subject
		$reportInfo['reportedUserIsAutoFlagged'] = (boolean)$this->isReportedUser($firstRow['reportedUserId'], $firstRow['subjectId']);
		
		// Add content info
		$reportInfo['content'] = $this->{$reportInfo['contentType']}->drawableInfo($reportInfo['contentId']);
		if($reportInfo['contentType'] === 'node') {
			// Override colours to default, after storing original
			$reportInfo['content']['originalTheme'] = $reportInfo['content']['theme'];
			$reportInfo['content']['originalOpacity'] = $reportInfo['content']['opacity'];
			$reportInfo['content']['originalPrimaryColour'] = $reportInfo['content']['primaryColour'];
			$reportInfo['content']['originalSecondaryColour'] = $reportInfo['content']['secondaryColour'];
			$reportInfo['content']['originalPrimaryFontColour'] = $reportInfo['content']['primaryFontColour'];
			$reportInfo['content']['originalSecondaryFontColour'] = $reportInfo['content']['secondaryFontColour'];
			
			$reportInfo['content']['theme'] = $this->group->getDefaultThemeColourOption()['value'];
			$reportInfo['content']['opacity'] = group::DEFAULT_OPACITY;
			$reportInfo['content']['primaryColour'] = group::DEFAULT_PRIMARYCOLOUR;
			$reportInfo['content']['secondaryColour'] = group::DEFAULT_SECONDARYCOLOUR;
			$reportInfo['content']['primaryFontColour'] = $this->group->getDefaultThemeFontOption()['value'];
			$reportInfo['content']['secondaryFontColour'] = $this->group->getDefaultThemeFontOption()['value'];
		} else {
			// Notify client only a single file needs drawing
			$reportInfo['content']['count'] = 0;
		}
		
		// Add current report as object
		if(is_null($firstRow['currentReviewId'])) {
			$currentReview = NULL;
		} else {
			$currentReview = array();
			$currentReview['id'] = $firstRow['currentReviewId'];
			$currentReview['userBan'] = $firstRow['latestUserBan'];
			$currentReview['banReasons'] = is_null($firstRow['banReasons']) ? null : explode(',', mb_strtolower($firstRow['banReasons'])); 
			$currentReview['userWarning'] = $firstRow["latestUserWarning"];
			$currentReview['warningReasons'] = is_null($firstRow['warningReasons']) ? null : explode(',', mb_strtolower($firstRow['warningReasons']));
			$currentReview['userAutoflag'] = $firstRow['latestUserAutoflag'];
			$currentReview['postDeleted'] = $firstRow['latestPostDeleted'];
			$currentReview['deleteReasons'] = is_null($firstRow['deleteReasons']) ? null : explode(',', mb_strtolower($firstRow['deleteReasons']));
			$currentReview['attachmentDeleted'] = $firstRow['latestAttachmentDeleted'];
			$currentReview['deleteAttachmentReasons'] = is_null($firstRow['deleteAttachmentReasons']) ? null : explode(',', mb_strtolower($firstRow['deleteAttachmentReasons']));
			$currentReview['threadDeleted'] = $firstRow['latestThreadDeleted'];
			$currentReview['deleteThreadReasons'] = is_null($firstRow['deleteThreadReasons']) ? null : explode(',', mb_strtolower($firstRow['deleteThreadReasons']));
			$currentReview['postEdited'] = $firstRow['latestPostEdited'];
			$currentReview['editReasons'] = is_null($firstRow['editReasons']) ? null : explode(',', mb_strtolower($firstRow['editReasons']));
			$currentReview['date'] = $firstRow['latestReviewDate'];
		}
		$reportInfo['currentReview'] = $currentReview;
		
		$this->db->select("r.*, u.userName, rc.id AS 'complaintId', rc.resolvedReviewId")
			->select("IF(`p`.`isAdministrator`, 'admin', IF(`p`.`isManager` = 1, 'manager', IF(`p`.`canBeSupervisor` = 1, 'supervisor', 'moderator'))) AS 'userLevel'", FALSE)
			->from('ReportedContentReviewsToUser AS r')
			->join('User AS u', 'u.userId = r.reviewerId')
			->join('ReportComplaints AS rc', 'rc.reportedReviewId = r.id', 'LEFT')
			->join('Permissions AS p', 'p.userId = u.userId')
			->where('r.reportedContentId', $reportId);
		$reviews = $this->db->get();
		
		// Record reviewer details
		$complaintCount = 0;
		$activeComplaintCount = 0;
		if($reviews->num_rows() > 0) {
			foreach($reviews->result_array() as $r) {
				$reviewer = array();
				$reviewer["id"] = $r["reviewerId"];
				$reviewer["reviewId"] = $r["id"];
				$reviewer["userName"] = $r["userName"];
				$reviewer["userLevel"] = $r["userLevel"];
				$reviewer["userBan"] = $r["userBan"];
				$reviewer["userWarning"] = $r["userWarning"];
				$reviewer["userAutoflag"] = $r["userAutoflag"];
				$reviewer["postDeleted"] = $r["postDeleted"];
				$reviewer["attachmentDeleted"] = $r["attachmentDeleted"];
				$reviewer["threadDeleted"] = $r["threadDeleted"];
				$reviewer["postEdited"] = $r["postEdited"];
				$reviewer["date"] = date('Y-m-d H:i:s', strtotime($r["reviewDate"]));
				$reviewer["reasons"] = $this->getReasonLabel(explode(',', mb_strtolower($r['reasons'])));
				$complaint = null;
				if (!is_null($r["complaintId"])) {
					$complaint = array("id" => $r['complaintId'], "resolvedReviewId" => $r['resolvedReviewId']);
					$complaintCount++;
					if (is_null($r['resolvedReviewId'])) {
						$activeComplaintCount++;
					}
				}
				$reviewer["complaint"] = $complaint;
			
				$reviewingUsers[] = $reviewer;
			}
		}
		$reasonCounts['complaintCount'] = $complaintCount;
		
		$userId = $reportInfo["reportedUserId"];
		$subjectId = $reportInfo["subjectId"];

		// Retrieve other data
		$reportInfo["numReports"] = $numReports;
		$reportInfo["latestReportDate"] = date("Y-m-d H:i:s", $maxDate);
		$reportInfo["reasonCounts"] = $reasonCounts;
		
		$reportType = 'normal';
		if ($activeComplaintCount > 0) {
			$reportType = 'complaint';
		} else if ($reasonCounts['extremeCount'] > 0) {
			$reportType = 'extreme';
		} else if ($this->user->isModeratorSubject($userId, $subjectId)) {
			$reportType = 'post by moderator';
		}
		$reportInfo["reportType"] = $reportType;
		
		$data = array("report" => $reportInfo);
		
		$data["previousWarnings"] = $this->user->getWarningCount($userId, $subjectId);
		$data["previousBans"] = $this->user->getBanCount($userId, $subjectId);
		$data["previousEdits"] = $this->user->getEditedNodeCount($userId, $subjectId);
		$data["previousDeletes"] = $this->user->getDeletedNodeCount($userId, $subjectId);
		$data["previousReports"] = $this->countActionedReportsOnUser($userId, $subjectId);
		$data["percentage"] = $this->getReportPercentage($reportId);
		$data["reportingUsers"] = $reportingUsers;
		$data["reviewingUsers"] = $reviewingUsers;
		
		$this->db->select('DATEDIFF(`banned`, NOW()) AS "daysRemaining"', FALSE)
			->from('UserToSubject')
			->where([
				'userId' => $userId,
				'subjectId' => $subjectId,
				'currentlyBanned' => 1
			]);
		$userToSubject = $this->db->get();
		if($userToSubject->num_rows() == 0) {
			$data["currentBan"] = false;
		} else {
			$data["currentBan"] = (integer)$userToSubject->first_row('array')['daysRemaining'];
		}
		
		// if this report has not been marked as viewed, mark it now
		if (!$reportInfo['viewed']) {
			$this->setViewedThisThreshold($reportId, true);
		}
		
		return $data;
	}
	
	/**
	 * Records reviews for reported content. Tracks which review is currently in progress.
	 * Allows different user types different permissions for reviewing. eg. Supervisors can override actions, but moderators can't.
	 * 
	 * @param int $reportId
	 * @param int $userResult
	 * @param string $userWarning
	 * @param boolean $postDeleted
	 * @param boolean $postEdited
	 * @return boolean
	 */
	function review($reportId, $userBan = NULL, $userWarning = NULL, $userAutoflag = NULL, $postDeleted = NULL, $attachmentDeleted = NULL, $postEdited = NULL, $threadDeleted = NULL, $reasons, $msgId = NULL, &$error) {
		$reasonRequired = false;
		// Check node has been reported
		if(!$this->getReportedContent($reportId)) {
			$error = 'The report could not be found.';
			return false;
		}
		$actionCount = 0;
		// Ban must be null or integer (number of days)
		if(!is_null($userBan)) {
			$userBan = intval($userBan);
			if($userBan > 0) {
				$actionCount++;
			}
		}
		// Warning must be null or in allowed report reasons
		if(!is_null($userWarning)) {
			$userWarning = boolval($userWarning);
			if($userWarning) {
				$actionCount++;
			}
		}
		// autoflag must be null or in allowed report reasons
		if(!is_null($userAutoflag)) {
			$userAutoflag = boolval($userAutoflag);
			if($userAutoflag) {
				$actionCount++;
			}
		}
		// Post deleted must be null or boolean. Indicates if whole content has been removed.
		if(!is_null($postDeleted)) {
			$postDeleted = boolval($postDeleted);
			if($postDeleted) {
				$actionCount++;
			}
		}
		// Attachment deleted must be null or boolean. Indicates if one or more atatchments have been removed.
		if(!is_null($attachmentDeleted)) {
			$attachmentDeleted = boolval($attachmentDeleted);
			if($attachmentDeleted) {
				$actionCount++;
			}
		}
		//Post edited must be null or boolean. Indicates if any text has been censored.
		if(!is_null($postEdited)) {
			$postEdited = boolval($postEdited);
			if($postEdited) {
				$actionCount++;
			}
		}
		
		//Thread deleted must be null or boolean. Indicates if the entire thread has been removed.
		if(!is_null($threadDeleted)) {
			$threadDeleted = boolval($threadDeleted);
			if($threadDeleted) {
				$actionCount++;
			}
		}
		
		// Check reasons are provided and valid
		
		// Allow only one action at a time that has consequences to the reported user (Allows complaints to be made on them one at a time).
		if($actionCount > 1) {
			$error = 'Submit each action for review separately. ie. You cannot ban a user at the same time as warning them.';
			return false;
		} else if($actionCount == 1) {
			if(is_array($reasons) && !empty($reasons)) {
				foreach($reasons as $r) {
					if ($r === 'undo') {
						continue;
					}
					if(!in_array(mb_strtoupper($r), $this->getReasons())) {
						$error = $r . ' is an invalid reason.';
						return false;
					}
				}
			} else if (!$this->user->supervisorCheck()) {
				$error = 'A reason (or reasons) must be provided for the action you are about to take.';
				return false;
			}
		}
		$newReview = false;
		$currentReportPercentage = $this->getReportPercentage($reportId);
		
		$this->db->from('ReportedContentReviews')
			->where('reportedContentId', $reportId)
			->where('(`latestUserBan` IS NULL OR `latestUserWarning` IS NULL OR `latestUserAutoflag` IS NULL
					OR `latestPostDeleted` IS NULL OR `latestattachmentDeleted` IS NULL OR `latestPostEdited` IS NULL
					OR `latestThreadDeleted` IS NULL)', NULL, FALSE);
		$currentReview = $this->db->get();

		if($currentReview->num_rows() === 0) {
			// Find most recent finished reviews. If it is inside current threshold, update that. Otherwise, make a new one.
			
			$this->db->from('ReportedContentReviews')
				->where('reportedContentId', $reportId)
				->order_by('latestReviewDate', 'DESC')
				->limit(1);
			$finishedReviews = $this->db->get();
			if($finishedReviews->num_rows() === 0) {
				$lastReviewPercentage = NULL;
			} else {
				$lastReview = $finishedReviews->first_row('array');
				$lastReviewPercentage = $lastReview['latestPctUsersReported'];
			}
			
			// @todo - this needs to take abuse reports and moderator reports into account, as they may not hit the thresholds
			if((is_null($lastReviewPercentage)/*  && $currentReportPercentage >= 6 */)
					|| ($lastReviewPercentage < 10 && $currentReportPercentage >= 10)
					|| ($lastReviewPercentage < 20 && $currentReportPercentage >= 20)
					|| ($lastReviewPercentage < 30 && $currentReportPercentage >= 30)
					|| ($lastReviewPercentage < 40 && $currentReportPercentage >= 40)
					|| ($lastReviewPercentage < 50 && $currentReportPercentage >= 50)
					|| ($lastReviewPercentage < 60 && $currentReportPercentage >= 60)
					|| ($lastReviewPercentage < 70 && $currentReportPercentage >= 70)
					|| ($lastReviewPercentage < 80 && $currentReportPercentage >= 80)
					|| ($lastReviewPercentage < 90 && $currentReportPercentage >= 90)
					|| ($lastReviewPercentage < 100 && $currentReportPercentage >= 100)
					)
			{
				$newReview = true;
			} else {
				// update existing - only if supervisor
				/* if(!$this->user->supervisorCheck()) {
					$error = 'You are not allowed to change the review.';
					return false;
				} */
				$currentReviewId = $lastReview['id'];
			}

		} else {
			// node has been partially reviewed
			// Nb. Assumes there is only one partial review at a time, so this must be enforced.
			$currentReview = $currentReview->first_row('array');
			$currentReviewId = $currentReview['id'];
			
			/* // Check if attempting to override already completed action
			if(!$this->user->supervisorCheck() &&
					((!is_null($userBan) && !is_null($currentReview['latestUserBan']))
					|| (!is_null($userWarning) && !is_null($currentReview['latestUserWarning']))
					|| (!is_null($postDeleted) && !is_null($currentReview['latestPostDeleted']))
					|| (!is_null($postEdited) && !is_null($currentReview['latestPostEdited'])))
			) {
				//Already actioned that type - disallow as moderator can't undo actions.
				$error = 'You are not allowed to change the review.';
				return false;
			} */
		}
		// Always insert new ReportedNodeReviewsToUser row
		$this->load->helper(array('htmlpurifier'));
		$this->db->insert('ReportedContentReviewsToUser', array(
				'reportedContentId' => $reportId,
				'reviewerId' => $this->user->authCheck(),
				'userBan' => $userBan,
				'userWarning' => $userWarning,
				'userAutoflag' => $userAutoflag,
				'postDeleted' => $postDeleted,
				'attachmentDeleted' => $attachmentDeleted,
				'postEdited' => $postEdited,
				'threadDeleted' => $threadDeleted,
				'pctUsersReported' => $currentReportPercentage,
				'messageId' => $msgId,
				'reasons' => is_array($reasons) && !empty($reasons) ? html_purify(mb_strtoupper(implode(',', $reasons)),'post') : NULL
		));
		$userReviewId = $this->db->insert_id();

		if($newReview) {
			// insert ReportedContentReviews row
			$this->db->insert('ReportedContentReviews', array(
					'reportedContentId' => $reportId,
					'latestUserBan' => is_null($userBan) ? null : $userReviewId,
					'latestUserWarning' => is_null($userWarning) ? null : $userReviewId,
					'latestUserAutoflag' => is_null($userAutoflag) ? null : $userReviewId,
					'latestPostDeleted' => is_null($postDeleted) ? null : $userReviewId,
					'latestAttachmentDeleted' => is_null($attachmentDeleted) ? null : $userReviewId,
					'latestPostEdited' => is_null($postEdited) ? null : $userReviewId,
					'latestThreadDeleted' => is_null($threadDeleted) ? null : $userReviewId,
					'latestPctUsersReported' => $this->getReportPercentage($reportId)
			));
			$currentReviewId = $this->db->insert_id();
		} else {
			// Update existing review
			$updateData = array();
			if(!is_null($userBan)) {
				$updateData["latestUserBan"] = $userReviewId;
			}
			if(!is_null($userWarning)) {
				$updateData["latestUserWarning"] = $userReviewId;
			}
			if(!is_null($userAutoflag)) {
				$updateData["latestUserAutoflag"] = $userReviewId;
			}
			if(!is_null($postDeleted)) {
				$updateData["latestPostDeleted"] = $userReviewId;
			}
			if(!is_null($attachmentDeleted)) {
				$updateData["latestAttachmentDeleted"] = $userReviewId;
			}
			if(!is_null($postEdited)) {
				$updateData["latestPostEdited"] = $userReviewId;
			}
			if(!is_null($threadDeleted)) {
				$updateData["latestThreadDeleted"] = $userReviewId;
			}
			$updateData["latestPctUsersReported"] =  $currentReportPercentage;
			$this->db->update('ReportedContentReviews', $updateData, array('id' => $currentReviewId));
		}
		
		$this->db->from('ReportedContentReviews')
			->where('id', $currentReviewId);
		$review = $this->db->get()->first_row('array');
		
		$returnData = array();
		if(is_null($review['latestUserBan']) || is_null($review['latestUserWarning']) || is_null($review['latestUserAutoflag'])
			|| is_null($review['latestPostDeleted']) || is_null($review['latestAttachmentDeleted']) || is_null($review['latestPostEdited']) || is_null($review['latestThreadDeleted'])
		) {
			$returnData['complete'] = false;
		} else {
			$returnData['complete'] = true;
		}
		return $returnData;
	}
	
	/**
	 * Handles the review procedure for extreme content. Result options are: delete, ban and ignore.
	 * The reported content may be permanently deleted, or restored depending on the result.
	 * Permissions should be checked before calling this.
	 * 
	 * @param integer $reportId
	 * @param string $result
	 * @param mixed $error
	 * @return boolean
	 */
	function extremeReview($reportId, $result, &$error) {
		// option to ban?
		$this->db->select("r.reportedUserId, COUNT(DISTINCT CASE WHEN `u`.`reason` = 'EXTREME' THEN `u`.`reportingUserId` END) AS 'extremeCount'", FALSE)
			->from('ReportedContent AS r')
			->join('ReportedContentToUser AS u', 'u.reportedContentId = r.id')
			->where(array(
				'r.id' => $reportId,
				'r.extremeReviewed' => 0
			))
			->having('extremeCount >= ' . self::REPORT_EXTREME_COUNT);
		$valid = $this->db->get();
		if($valid->num_rows() == 0) {
			$error = 'The content could not be reviewed for extreme content. It may never have been reported, not been reported for extreme content, or already been reviewed for extreme content.';
			return false;
		} else {
			$reportedContent = $valid->first_row('array');
		}
		
		$actualContent = $this->getActualReportedContent($reportId);
		if(!$actualContent) {
			return false;
		}
		$reason = 'EXTREME';
		$contentType = $actualContent['contentType'];
		$contentId = $actualContent['id'];
		$subjectId = $this->$contentType->getSubjectId($contentId);
		
		$this->db->select('reportingUserId')
			->distinct()
			->from('ReportedContentToUser')
			->where(array(
				'reportedContentId' => $reportId,
				'reason' => $reason
			));
		$reportingUsers = $this->db->get()->result_array();
		
		switch ($result) {
			case 'delete':
				$this->$contentType->markAsDeleted($contentId, $reason, $contentId);
				$this->message->systemMessage($this->user->authCheck(), $reportedContent['reportedUserId'], $subjectId, message::MSG_POST_DELETED, array('removalReason' => $this->getReasonLabel(array($reason))));
				$to = array();
				foreach($reportingUsers as $r) {
					$to[] = $r['reportingUserId'];
				}
				$this->message->systemMessage($this->user->authCheck(), $to, $subjectId, message::MSG_EXTREME_THANKYOU);
				break;
			case 'ban':
			case 'ignore':
				// restore post
				$this->$contentType->markAsNotDeleted($contentId, $contentId);
				$to = array();
				if($result == 'ban') {
					$reporterMessage = message::MSG_EXTREME_BAN;
				} else {
					$reporterMessage = message::MSG_EXTREME_IGNORE;
				}
				foreach($reportingUsers as $r) {
					$to[] = $r['reportingUserId'];
					if($result == 'ban') {
						$this->user->banFromSubject($r['reportingUserId'], $subjectId);
					}
				}
				$this->message->systemMessage($this->user->authCheck(), $to, $subjectId, $reporterMessage);
				break;
			default:
				return false;
		}
		$this->db->update('ReportedContent', array('extremeReviewed' => 1), array('id' => $reportId));
		return $this->db->affected_rows() > 0;
	}
	
	/**
	 * Handles the review procedure for a complaint.
	 * 
	 * @param int $reportId
	 * @param int $complaintId
	 * @param string $action
	 * @param array|null $extraData
	 * @param $error
	 * @return boolean|array
	 */
	function complaintReview($reportId, $complaintId, $action, $extraData = null, &$error) {
		// Check a complaint has been made
		$this->db->from('ReportComplaints AS r')
			->join('ReportedContentReviewsToUser AS u', 'u.id = r.reportedReviewId')
			->where('r.id', $complaintId);
		$complaint = $this->db->get();
		if($complaint->num_rows() === 0) {
			$error = 'No complaint could be found.';
			return false;
		}
		$complaint = $complaint->first_row('array');
		if($reportId != $complaint['reportedContentId']) {
			$error = 'The reportId does not match the content that is being complained about.';
			return false;
		}
		
		$insertData = array(
			'reportedContentId' => $reportId,
			'reviewerId' => $this->user->authCheck(),
			'userBan' => NULL,
			'userWarning' => NULL,
			'postDeleted' => NULL,
			'postEdited' => NULL,
			'pctUsersReported' => $this->getReportPercentage($reportId)
		);
		
		$actualContent = $this->getActualReportedContent($reportId);
		if(!$actualContent) {
			return false;
		}
		$contentType = $actualContent['contentType'];
		$contentId = $actualContent['id'];
		switch ($action) {
			case 'ban':
				// undo ban
				// check they're still banned
				// if not, can't do anything
				// if they are, remove review.userBan days from their ban (ignore time since ban was added, as makes too complex)
				// insert ReportedNodeReviewsToUser row
				$insertData['userBan'] = 0;
				$msg = message::MSG_COMPLAINT_ACTION;
				break;
			case 'censor':
				if(!(array_key_exists("text", $extraData) && array_key_exists('title', $extraData)
				&& array_key_exists('attachments', $extraData))) {
					$error = 'text, title and attachments must be set to edit content.';
					return false;
				}
				$this->{$contentType}->censorText($contentId, $extraData['text'], $extraData['title'], $extraData['attachments'], $error);
				if($error) {
					return false;
				}
				$insertData['postEdited'] = 0;
				$msg = message::MSG_COMPLAINT_ACTION;
				break;
			case 'delete':
				if($contentType === 'node') {
					if(!(isset($extraData["parentNodeId"])
						&& isset($extraData['nodes'])
						&& isset($extraData['nodes']['deletedAttachments'])
						&& isset($extraData['nodes']['restoredAttachments']))) 
					{
						$error = 'parentNodeId and nodes[deletedAttachments, restoredAttachments] must be set to restore post attachments.';
						return false;
					}
					foreach($extraData['nodes']['restoredAttachments'] as $id) {
						$success = $this->node->markAsNotDeleted($extraData["parentNodeId"], $id);
						if(!$success) {
							$error = 'The attachment was not successfully restored.';
							return false;
						}
					}
				} else {
					$success = $this->file->markAsNotDeleted($contentId);
					if(!$success) {
						$error = 'The file was not successfully restored.';
						return false;
					}
				}
				$insertData['postDeleted'] = 0;
				$msg = message::MSG_COMPLAINT_ACTION;
				break;
			case 'warning':
				// undo warning
				$this->message->systemMessage($this->user->authCheck(), $this->getReportedUserId($reportId), $this->$contentType->getSubjectId($contentId), message::MSG_WARNING_IGNORE);
				$insertData['userWarning'] = 0;
				$msg = message::MSG_COMPLAINT_ACTION;
				break;
			case 'complaintReview':
				$msg = message::MSG_COMPLAINT_IGNORE;
				break;
			default:
				$error = 'That action is not allowed.';
				return false;
		}
		$msgId = $this->message->systemMessage($this->user->authCheck(), $this->getReportedUserId($reportId), $this->$contentType->getSubjectId($contentId), $msg);
		$insertData['messageId'] = $msgId;
		$this->db->insert('ReportedContentReviewsToUser', $insertData);
		$this->db->update('ReportComplaints',
			array('resolvedReviewId' => $this->db->insert_id()),
			array('id' => $complaintId));
		// Since only one action can be done at a time (enforced by this.review function), always complete by this stage
		return $this->db->affected_rows() > 0;
	}
	
	/**
	 * Collates statistics for moderator (and supervisor if present) activity in each forum supervisor is responsible for.
	 * Tailored specifically to organise statistics into rows for the supervisorPortal view. Activity data is retrieved since the 
	 * last review date for each forum. Outstanding actions are current. Logins are current.
	 * Returns an array of objects to plug into a dojo dgrid for display.
	 * 
	 * @param int $supervisorId
	 * @return stdClass[]
	 */
	function getSupervisorSummary($supervisorId) {
		
		$subjects = array_map(function($v){return (int)$v['id'];}, $this->user->getManageableSubjects($supervisorId));
		$subjectsImploded = implode(',', $subjects);
		
		// unactioned reports for each subject
		$allActiveReportsSql = "
			SELECT 
			`p`.`subjectId`,
			`r`.`id` AS 'reportId',
			`vc`.`id` AS 'currentReviewId',
			IF(`authorPer`.`canBeModerator` AND ANY_VALUE(`authorModSub`.`userId`) IS NOT NULL, 1, 0) AS 'authorIsModerator',
			ROUND(COUNT(DISTINCT `u`.`reportingUserId`) / COUNT(DISTINCT `s`.`userId`) * 100, 0) AS 'currentPercentage',
			COUNT(DISTINCT `u`.`reportingUserId`) AS 'reportCount',
			COUNT(DISTINCT CASE WHEN `u`.`reason` = 'ABUSE' THEN `u`.`reportingUserId` END) AS 'abuseCount',
			COUNT(DISTINCT CASE WHEN `u`.`reason` = 'EXTREME' THEN `u`.`reportingUserId` END) AS 'extremeCount',
			COUNT(DISTINCT `rc`.`id`) AS 'complaintCount',
			COUNT(DISTINCT IF(`rc`.`resolvedReviewId` IS NULL, `rc`.`id`, NULL)) AS 'activeComplaintCount',
			`v`.`latestPctUsersReported` AS 'lastReviewPercentage',
			COUNT(DISTINCT IF((`per`.`canBeSupervisor` OR `per`.`isManager` OR `ms`.`userId` IS NOT NULL) AND (`v`.`latestReviewDate` IS NULL OR `v`.`latestReviewDate` < `u`.`reportDate`), `u`.`reportingUserId`, NULL)) AS 'moderatorReportedCount',
			`r`.`extremeReviewed`	
			FROM `ReportedContent` AS `r` 
			JOIN `ReportedContentToUser` AS `u` ON `u`.`reportedContentId` = `r`.`id`
			LEFT JOIN `ReportedNodes` AS `rn` ON `rn`.`reportedContentId` = `r`.`id`
			LEFT JOIN `Nodes` AS `n` ON `n`.`nodeId` = `rn`.`nodeId`
			LEFT JOIN `Post` AS `p` ON `p`.`postId` = `n`.`postId`
			LEFT JOIN `ReportedFiles` AS `rf` ON `rf`.`reportedContentId` = `r`.`id`
			LEFT JOIN `Content` AS `f` ON `f`.`fileName` = `rf`.`fileName`
			JOIN `Subject` AS `sub` ON `sub`.`subjectId` = `p`.`subjectId` OR `sub`.`subjectId` = `f`.`subjectId`
			JOIN `UserToSubject` AS `s` ON `s`.`subjectId` = `sub`.`subjectId`
			JOIN `User` AS `us` ON `us`.`userId` = `r`.`reportedUserId`
			JOIN `Permissions` AS `authorPer` ON `authorPer`.`userId` = `us`.`userId`
			LEFT JOIN `ModeratorToSubject` AS `authorModSub` ON `authorModSub`.`subjectId` = `sub`.`subjectId` AND `authorModSub`.`userId` = `us`.`userId` AND `authorModSub`.`moderator` = 1 AND `authorModSub`.`approved` = 1
			JOIN `Permissions` AS `per` ON `per`.`userId` = `u`.`reportingUserId`
			JOIN `User` AS `usr` ON `usr`.`userId` = `per`.`userId`
			LEFT JOIN `ModeratorToSubject` AS `ms` ON `ms`.`subjectId` = `sub`.`subjectId` AND `ms`.`userId` = `u`.`reportingUserId` AND `ms`.`moderator` = 1 AND `ms`.`approved` = 1
			LEFT JOIN (
				SELECT `a`.*
				FROM `ReportedContentReviews` AS `a`
				LEFT JOIN `ReportedContentReviews` AS `b` ON `a`.`reportedContentId` = `b`.`reportedContentId` AND `a`.`latestReviewDate` < `b`.`latestReviewDate`
				LEFT JOIN `ReportedNodes` AS `rn` ON `rn`.`reportedContentId` = `a`.`reportedContentId`
				LEFT JOIN `Nodes` AS `n` ON `n`.`nodeId` = `rn`.`nodeId`
				LEFT JOIN `Post` AS `p` ON `p`.`postId` = `n`.`postId`
				LEFT JOIN `ReportedFiles` AS `rf` ON `rf`.`reportedContentId` = `a`.`reportedContentId`
				LEFT JOIN `Content` AS `f` ON `f`.`fileName` = `rf`.`fileName`
				JOIN `Subject` AS `sub` ON `sub`.`subjectId` = `p`.`subjectId` OR `sub`.`subjectId` = `f`.`subjectId`
				WHERE `b`.`id` IS NULL 
				AND `sub`.`subjectId` IN (" . $subjectsImploded . ")
				AND `a`.`latestUserBan` IS NOT NULL
				AND `a`.`latestUserWarning` IS NOT NULL
				AND `a`.`latestPostDeleted` IS NOT NULL
				AND `a`.`latestPostEdited` IS NOT NULL
				) AS `v` ON `v`.`reportedContentId` = `r`.`id`
			LEFT JOIN `ReportedContentReviews` AS `vc` ON `vc`.`reportedContentId` = `r`.`id` AND (
				`vc`.`latestUserBan` IS NULL 
				OR `vc`.`latestUserWarning` IS NULL
				OR `vc`.`latestUserAutoflag` IS NULL
				OR `vc`.`latestPostDeleted` IS NULL
				OR `vc`.`latestAttachmentDeleted` IS NULL
				OR `vc`.`latestPostEdited` IS NULL
				OR `vc`.`latestThreadDeleted` IS NULL)
			LEFT JOIN `ReportComplaints` AS `rc` ON `rc`.`reportedContentId` = `r`.`id`
			WHERE `sub`.`subjectId` IN (" . $subjectsImploded . ")
			GROUP BY `r`.`id`, `vc`.`id`, `v`.`id`
		";
		
		$allActiveReports = $this->db->query($allActiveReportsSql)->result_array();
		$activeReportsBySubjectId = array();
		foreach($allActiveReports as $a) {
			$include = false;
			// Don't include extreme content reports, complaints or reports about moderator posts, as they should not be available to moderators.
			if(($a['extremeCount'] < self::REPORT_EXTREME_COUNT || $a['extremeReviewed'] == 1) && $a['activeComplaintCount'] == 0 && !$a['authorIsModerator'] && (!is_null($a['currentReviewId']) || $this->reportRowMeetsActiveRequirements($a, 'moderator'))) {
				$subjectId = $a['subjectId'];
				if(!isset($activeReportsBySubjectId[$subjectId])) {
					$activeReportsBySubjectId[$subjectId] = array();
				}
				$activeReportsBySubjectId[$subjectId][] = $a;
			}
		}
		
		$this->db->select("subjectId AS 'id'")
			->select("COUNT(IF(`date` > DATE_SUB(NOW(),INTERVAL 7 DAY), id, NULL)) AS 'count', MAX(`date`) AS 'lastVisited'", FALSE)
			->from('SupervisorToSubjectLog')
			->where(array(
				'userId' => $supervisorId,
				'action' => 'REVIEW'
				))
			->where_in('subjectId', $subjects)
			->group_by('subjectId');
		$visitCounts = $this->db->get();
		$visitCountsBySubjectId = array();
		if($visitCounts->num_rows() > 0) {
			foreach($visitCounts->result_array() as $v) {
				$visitCountsBySubjectId[$v['id']] = $v;
			}
		}
		
		// moderator and supervisor review activity for each subject
		$reviewsSql = "
			SELECT
			`v`.`reviewerId`,
			`sub`.`subjectId`,
			COUNT(IF(`r`.`abuseCount` > 0 AND `r`.`modCount` = 0, 1, null)) AS 'actionedAbuseReports',
			COUNT(IF(`r`.`modCount` > 0, 1, null)) AS 'actionedModeratorReports',
			COUNT(IF(`r`.`abuseCount` = 0 AND `r`.`modCount` = 0, 1, null)) AS 'actionedUserReports',
			COUNT(IF((`v`.`userBan` = 0 AND `v`.`userWarning` = 0) OR (`v`.`postEdited` = 0 AND `v`.`postDeleted` = 0), 1, null)) AS 'noAction',
			COUNT(IF(`v`.`userWarning` = 1, 1, null)) AS 'warned',
			COUNT(IF(`v`.`postDeleted` = 1, 1, null)) AS 'deleted',
			COUNT(IF(`v`.`postEdited` = 1, 1, null)) AS 'edited',
			COUNT(IF(`v`.`userBan` > 0, 1, null)) AS 'banned',
			`sup`.`lastReviewed`,
			DATEDIFF(NOW(), `sup`.`lastReviewed`) AS 'daysSinceLastReview'
			FROM `ReportedContentReviewsToUser` AS `v`
			JOIN (
				SELECT
				`r`.`reportedContentId`,
				COUNT(DISTINCT IF(`r`.`reason` = 'ABUSE', `r`.`reportedContentId`, null)) AS 'abuseCount',
				COUNT(DISTINCT IF(`per`.`canBeSupervisor` OR `per`.`isManager` OR `ms`.`userId` IS NOT NULL, `r`.`reportedContentId`, null)) AS 'modCount'
				FROM `ReportedContentToUser` AS `r`
				LEFT JOIN `ReportedNodes` AS `rn` ON `rn`.`reportedContentId` = `r`.`reportedContentId`
				LEFT JOIN `Nodes` AS `n` ON `n`.`nodeId` = `rn`.`nodeId`
				LEFT JOIN `Post` AS `p` ON `p`.`postId` = `n`.`postId`
				LEFT JOIN `ReportedFiles` AS `rf` ON `rf`.`reportedContentId` = `r`.`reportedContentId`
				LEFT JOIN `Content` AS `f` ON `f`.`fileName` = `rf`.`fileName`
				JOIN `Subject` AS `sub` ON `sub`.`subjectId` = `p`.`subjectId` OR `sub`.`subjectId` = `f`.`subjectId`
				JOIN `Permissions` AS `per` ON `per`.`userId` = `r`.`reportingUserId`
				LEFT JOIN `ModeratorToSubject` AS `ms` ON `ms`.`subjectId` = `sub`.`subjectId` AND `ms`.`userId` = `r`.`reportingUserId` AND `ms`.`moderator` = 1 AND `ms`.`approved` = 1
				GROUP BY `r`.`reportedContentId`
			) AS `r` ON `r`.`reportedContentId` = `v`.`reportedContentId`
			LEFT JOIN `ReportedNodes` AS `rn` ON `rn`.`reportedContentId` = `v`.`reportedContentId`
			LEFT JOIN `Nodes` AS `n` ON `n`.`nodeId` = `rn`.`nodeId`
			LEFT JOIN `Post` AS `p` ON `p`.`postId` = `n`.`postId`
			LEFT JOIN `ReportedFiles` AS `rf` ON `rf`.`reportedContentId` = `v`.`reportedContentId`
			LEFT JOIN `Content` AS `f` ON `f`.`fileName` = `rf`.`fileName`
			JOIN `Subject` AS `sub` ON `sub`.`subjectId` = `p`.`subjectId` OR `sub`.`subjectId` = `f`.`subjectId`
			JOIN `Permissions` AS `per` ON `per`.`userId` = `v`.`reviewerId`
			JOIN `SupervisorToSubject` AS `sup` ON `sup`.`subjectId` = `sub`.`subjectId`
			WHERE `sub`.`subjectId` IN (".$subjectsImploded.")
				AND `per`.`isManager` = 0
				AND `sup`.`userId` = ?
				AND `v`.`reviewDate` > `sup`.`lastReviewed`
			GROUP BY `sub`.`subjectId`, `v`.`reviewerId`
		";
		
		$reviews = $this->db->query($reviewsSql, array($supervisorId))->result_array();
		$reviewsBySubjectIdReviewerId = array();
		foreach($reviews as $v) {
			$subjectId = $v['subjectId'];
			if(!isset($reviewsBySubjectIdReviewerId[$subjectId])) {
				$reviewsBySubjectIdReviewerId[$subjectId] = array();
			}
			$reviewsBySubjectIdReviewerId[$subjectId][$v['reviewerId']] = $v;
		}
		
		// moderator other activity for each subject
		$modActivitySql = "
			SELECT
			`m`.`userId`,
			`m`.`subjectId`,
			IF(`m`.`lastFaceBookLogin` > DATE_SUB(NOW(), INTERVAL 7 DAY), 1, CONCAT('+', DATEDIFF(DATE_SUB(NOW(), INTERVAL 7 DAY), `m`.`lastFaceBookLogin`))) AS 'recentlyUsedFacebook',
			`s`.`facebookLink`,
			COUNT(DISTINCT IF(`p`.`timeStamp` > `sup`.`lastReviewed`, `p`.`postId`, NULL)) AS 'newsPosts', 
			COUNT(DISTINCT `a`.`postId`) AS 'unreadUpdates',
			COUNT(DISTINCT `msg`.`postId`) AS 'unreadMessages',
			IF(`m`.`lastVisited` > DATE_SUB(NOW(), INTERVAL 7 DAY), 1, CONCAT('+', DATEDIFF(DATE_SUB(NOW(), INTERVAL 7 DAY), `m`.`lastVisited`))) AS 'recentlyVisited'
			FROM `ModeratorToSubject` AS `m`
			JOIN `Subject` AS `s` ON `s`.`subjectId` = `m`.`subjectId`
			JOIN `SupervisorToSubject` AS `sup` ON `sup`.`subjectId` = `m`.`subjectId` 
			LEFT JOIN `Post` AS `p` ON `p`.`userId` = `m`.`userId` AND `p`.`subjectId` = `m`.`subjectId` AND `p`.`postType` = 'newsPost'
			LEFT JOIN `UserToAdminPost` AS `a` ON `a`.`userId` = `m`.`userId` AND `a`.`readIt` = 0
			LEFT JOIN `MessagesToUser` AS `mu` ON `mu`.`toUser` = `m`.`userId`
			LEFT JOIN `Nodes` AS `n` ON `n`.`nodeId` = `mu`.`messageId`
			LEFT JOIN `Post` AS `msg` ON `msg`.`postType` = 'message' AND `msg`.`postId` = `n`.`postId` AND `msg`.`subjectId` = `m`.`subjectId`
			WHERE `m`.`subjectId` IN (" . $subjectsImploded . ")
				AND `m`.`approved` = 1
				AND `m`.`moderator` = 1
				AND `sup`.`userId` = ?
			GROUP BY `m`.`subjectId`, `m`.`userId`
		";
		
		$modActivity = $this->db->query($modActivitySql, array($supervisorId))->result_array();
		$modActivityBySubjectIdModId = array();
		foreach($modActivity as $m) {
			$subjectId = $m['subjectId'];
			if(!isset($modActivityBySubjectIdModId[$subjectId])) {
				$modActivityBySubjectIdModId[$subjectId] = array();
			}
			$modActivityBySubjectIdModId[$subjectId][$m['userId']] = $m;
		}
		
		// supervisor other activity for each subject
		$this->db->select("s.userId AS 'supervisorId', s.subjectId")
			->select("COUNT(DISTINCT `p`.`postId`) AS 'newsPosts'", FALSE)
			->from('SupervisorToSubject AS s')
			->join('SupervisorToSubject AS sup', 'sup.subjectId = s.subjectId')
			->join('Post AS p', 'p.userId = s.userId AND p.subjectId = s.subjectId AND p.postType = "newsPost"', 'left')
			->where_in('s.subjectId', $subjects)
			->where('sup.userId', $supervisorId)
			->where('p.timeStamp > sup.lastReviewed')
			->group_by('s.subjectId, s.userId');
		
		$superActivity = $this->db->get()->result_array();
		$superActivityBySubjectIdSuperId = array();
		foreach($superActivity as $s) {
			$subjectId = $s['subjectId'];
			if(!isset($superActivityBySubjectIdSuperId[$subjectId])) {
				$superActivityBySubjectIdSuperId[$subjectId] = array();
			}
			$superActivityBySubjectIdSuperId[$subjectId][$s['supervisorId']] = $s;
		}
		
		// All supervisors and moderators
		$this->db->select("`u`.`userId`, `u`.`userName`, IF(`s`.`userId` IS NOT NULL, `s`.`subjectId`, `m`.`subjectId`) AS 'subjectId'", FALSE)
			->select("IF(`s`.`userId` IS NOT NULL, 1, 0) AS 'isSupervisor'", FALSE)
			->from('User AS u')
			->join('SupervisorToSubject AS s', 's.userId = u.userId', 'left')
			->join('ModeratorToSubject AS m', 'm.userId = u.userId AND m.approved = 1 AND m.moderator = 1', 'left')
			->where('(s.userId IS NOT NULL OR m.userId IS NOT NULL)')
			->where('(m.subjectId IN (' . $subjectsImploded . ') OR s.subjectId IN (' . $subjectsImploded .'))');
		
		$modsBySubjectId = array();
		$supervisorsBySubjectId = array();
		$modsAndSupers = $this->db->get()->result_array();

		foreach($modsAndSupers as $u) {
			$subjectId = $u['subjectId'];
			if($u['isSupervisor']) {
				if(!isset($supervisorsBySubjectId[$subjectId])) {
					$supervisorsBySubjectId[$subjectId] = array();
				}
				$supervisorsBySubjectId[$subjectId][] = array('id' => $u['userId'], 'name' => $u['userName']);
			} else {
				if(!isset($modsBySubjectId[$subjectId])) {
					$modsBySubjectId[$subjectId] = array();
				}
				$modsBySubjectId[$subjectId][] = array('id' => $u['userId'], 'name' => $u['userName']);
			}
		}
		
		// Average stats to highlight outliers
		
		// Cache reused query part
		$this->db->start_cache();
		$this->db->select_avg('r.count', 'average')
			->select("STD(`count`) AS 'stdDev'", FALSE)
			->from('ReportedContentStatistics AS r')
			->join('StatisticTypes AS t', 't.id = r.typeId');
		$this->db->stop_cache();
		
		// Actioned stats
		$this->db->where('t.type', 'mod_actioned');
		$actionedStats = $this->db->get()->first_row('array');
		
		// Unactioned stats
		$this->db->where('t.type', 'mod_unactioned');
		$unactionedStats = $this->db->get()->first_row('array');
		
		// Flush cache
		$this->db->flush_cache();
		
		$rowsBySubjectId = array();
		
		// Add rows in order (sorted by unreviewed by supervisor by date)
		foreach($subjects as $s) {
			$subjectData = array();
			$subjectData['lastVisit'] = isset($visitCountsBySubjectId[$s]) ? $visitCountsBySubjectId[$s]['lastVisited'] : NULL;
			$subjectData['rows'] = array();
			
			// Subject row
			$subjectRow = new stdClass();
			$owner = new stdClass();
			$owner->id = $s;
			$owner->label = $this->group->getName($s);
			$owner->type = 'subject';
			$owner->status = null;
			$subjectRow->owner = $owner;
			$subjectRow->userReports = null;
			$subjectRow->moderatorReports = null;
			$subjectRow->personalAttacks = null;
			$subjectRow->unactionedNotices = null;
			$subjectRow->unactionedMessages = null;
			$subjectRow->totalUnactioned = null;
			$subjectRow->noAction = null;
			$subjectRow->warned = null;
			$subjectRow->editedAttachment = null;
			$subjectRow->editedContent = null;
			$subjectRow->banned = null;
			$subjectRow->newsPosts = null;
			$subjectRow->review =  true;
			$subjectRow->loginCount = isset($visitCountsBySubjectId[$s]) && (int)$visitCountsBySubjectId[$s]['count'] !== 0 ? (int)$visitCountsBySubjectId[$s]['count'] : 0;
			$subjectRow->recentActivity = is_null($subjectData['lastVisit']) ? 'never' : (strtotime($subjectData['lastVisit']) > strtotime('1 week ago') ? true : '+'. floor((strtotime('1 week ago') - strtotime($subjectData['lastVisit'])) / (60*60*24)));
			$subjectRow->facebook = null;
//			$subjectRow->twitter = null;
			
			$subjectData['rows'][] = $subjectRow;
			
			// Unactioned row
			if(isset($activeReportsBySubjectId[$s])) {
				$unactionedRow = new stdClass();
				$owner = new stdClass();
				$owner->id = null;
				$owner->label = 'unactioned';
				$owner->type = null;
				
				$userReportCount = 0;
				$modReportCount = 0;
				$abuseReportCount = 0;
				$totalCount = count($activeReportsBySubjectId[$s]);
				foreach($activeReportsBySubjectId[$s] as $report) {
					if($report['moderatorReportedCount'] > 0) {
						$modReportCount++;
					} else if($report['abuseCount'] > 0) {
						$abuseReportCount++;
					} else {
						$userReportCount++;
					}
				}
				// flag if >2SD above expected unactioned count
				$owner->status = $totalCount > $unactionedStats['average'] + (2 * $unactionedStats['stdDev']) || $abuseReportCount > 0 ? 'red' : null;
				
				$unactionedRow->owner = $owner;
				$unactionedRow->userReports = $userReportCount;
				$unactionedRow->moderatorReports = $modReportCount;
				$unactionedRow->personalAttacks = $abuseReportCount;
				$unactionedRow->unactionedNotices = '-';
				$unactionedRow->unactionedMessages = '-';
				$unactionedRow->totalUnactioned = $totalCount;
				$unactionedRow->noAction = '-';
				$unactionedRow->warned = '-';
				$unactionedRow->editedAttachment = '-';
				$unactionedRow->editedContent = '-';
				$unactionedRow->banned = '-';
				$unactionedRow->newsPosts = '-';
				$unactionedRow->review = null;
				$unactionedRow->loginCount = '-';
				$unactionedRow->recentActivity = null;
				$unactionedRow->facebook = null;
//				$unactionedRow->twitter = null;
				
				$subjectData['rows'][] = $unactionedRow;
			}
			if(isset($modsBySubjectId[$s])) {
				foreach($modsBySubjectId[$s] as $mod) {
					$modId = $mod['id'];
					if(isset($reviewsBySubjectIdReviewerId[$s]) && isset($reviewsBySubjectIdReviewerId[$s][$modId])) {
						$modReviews = $reviewsBySubjectIdReviewerId[$s][$modId];
					} else {
						$modReviews = false;
					}
					if(isset($modActivityBySubjectIdModId[$s][$modId])) {
						$modActivity = $modActivityBySubjectIdModId[$s][$modId];
					} else {
						$modActivity = false;
					}
					$modRow = new stdClass();
					$owner = new stdClass();
					$owner->id = $modId;
					$owner->type = 'moderator';
					$owner->label = $mod['name'];
					
					// Highlight productive and unproductive moderators
					if($modReviews) {
						$totalActioned = $modReviews['actionedUserReports'] + $modReviews['actionedModeratorReports'] + $modReviews['actionedAbuseReports'];
						$actionedPerDay = $totalActioned / $modReviews['daysSinceLastReview'];
						$owner->status = $actionedPerDay > $actionedStats['average'] + (2 * $actionedStats['stdDev']) 
											? 'green' 
											: $actionedPerDay < $actionedStats['average'] - (2 * $actionedStats['stdDev'])
												? 'red'
												: null;
					} else {
						$owner->status = null;
					}
					$modRow->owner = $owner;
					
					$modRow->userReports = $modReviews ? $modReviews['actionedUserReports'] : 0;
					$modRow->moderatorReports = $modReviews ? $modReviews['actionedModeratorReports'] : 0;
					$modRow->personalAttacks = $modReviews ? $modReviews['actionedAbuseReports'] : 0;
					$modRow->unactionedNotices = $modActivity ? $modActivity['unreadUpdates'] : 0;
					$modRow->unactionedMessages = $modActivity ? $modActivity['unreadMessages'] : 0;
					$modRow->totalUnactioned = null;
					$modRow->noAction = $modReviews ? $modReviews['noAction'] : 0;
					$modRow->warned = $modReviews ? $modReviews['warned'] : 0;
					$modRow->editedAttachment = $modReviews ? $modReviews['deleted'] : 0;
					$modRow->editedContent = $modReviews ? $modReviews['edited'] : 0;
					$modRow->banned = $modReviews ? $modReviews['banned'] : 0;
					$modRow->newsPosts = $modActivity ? $modActivity['newsPosts'] : 0;
					$modRow->review = null;
					$modRow->loginCount = '?';
					$modRow->recentActivity = $modActivity ? ($modActivity['recentlyVisited'] == 1 ? true : (is_null($modActivity['recentlyVisited']) ? 'never' : $modActivity['recentlyVisited'])) : '?';
					$modRow->facebook = (object)array('value' => $modActivity ? ($modActivity['recentlyUsedFacebook'] == 1 ? true : $modActivity['recentlyUsedFacebook']) : '-', 'link' => $modActivity['facebookLink']);
//					$modRow->twitter = (object)array('value' => $modActivity ? ($modActivity['recentlyUsedTwitter'] == 1 ? true : $modActivity['recentlyUsedTwitter']) : '-', 'link' => $modActivity['twitterLink']);
					
					$subjectData['rows'][] = $modRow;
				}
			}
			if(isset($supervisorsBySubjectId[$s])) {
				foreach($supervisorsBySubjectId[$s] as $supervisor) {
					$superId = $supervisor['id'];
					if(isset($reviewsBySubjectIdReviewerId[$s]) && isset($reviewsBySubjectIdReviewerId[$s][$superId])) {
						$superReviews = $reviewsBySubjectIdReviewerId[$s][$superId];
					} else {
						$superReviews = false;
					}
					if(isset($superActivityBySubjectIdSuperId[$s][$superId])) {
						$superActivity = $superActivityBySubjectIdSuperId[$s][$superId];
					} else {
						$superActivity = false;
					}
					// Only include supervisor info if they have reviewed some reports, or had other activity
					if($superReviews || $superActivity) {
						$superRow = new stdClass();
						$owner = new stdClass();
						$owner->id = $superId;
						$owner->type = 'supervisor';
						$owner->label = $supervisor['name'];
						$owner->status = null;
						$superRow->owner = $owner;
							
						$superRow->userReports = $superReviews ? $superReviews['actionedUserReports'] : 0;
						$superRow->moderatorReports = $superReviews ? $superReviews['actionedModeratorReports'] : 0;
						$superRow->personalAttacks = $superReviews ? $superReviews['actionedAbuseReports'] : 0;
						$superRow->unactionedNotices = null;
						$superRow->unactionedMessages = null;
						$superRow->totalUnactioned = null;
						$superRow->noAction = $superReviews ? $superReviews['noAction'] : 0;
						$superRow->warned = $superReviews ? $superReviews['warned'] : 0;
						$superRow->editedAttachment = $superReviews ? $superReviews['deleted'] : 0;
						$superRow->editedContent = $superReviews ? $superReviews['edited'] : 0;
						$superRow->banned = $superReviews ? $superReviews['banned'] : 0;
						$superRow->newsPosts = $superActivity? $superActivity['newsPosts'] : 0;
						$superRow->review = null;
						$superRow->loginCount = null;
						$superRow->recentActivity = null;
						$superRow->facebook = null;
//						$superRow->twitter = null;
							
						$subjectData['rows'][] = $superRow;
					}
				}
			}
			$rowsBySubjectId[$s] = $subjectData;
		}
		
		// sort by priority - not reviewed recently + no moderator activity
		usort($rowsBySubjectId, array("reported_content", 'sortSubjectRows'));
		
		$summary = array();
		foreach($rowsBySubjectId as $id => $subjectData) {
			$summary = array_merge($summary, $subjectData['rows']);
		}
		
		return $summary;
		//return array_merge($summary, $summary, $summary, $summary);
	}
	
	/**
	 * Collates statistics for basic supervisor activity.
	 * Returns data containing the username, number of forums supervising, number visited this week, average visited each week for
	 * each suypervisor in the system.
	 * 
	 * @return array
	 */
	public function getManagerSummary() {
		$data = [];
		// supervisor activity
		$sql = "
			SELECT
			u.userId,
			u.userName,
			COUNT(DISTINCT s.subjectId) AS 'numSubjects',
			COUNT(DISTINCT l.subjectId) AS 'viewsThisWeek',
			a.weeklyAverage
			FROM Permissions AS p
			JOIN User AS u on u.userId = p.userId
			LEFT Join SupervisorToSubject AS s ON s.userId = u.userId
			LEFT JOIN SupervisorToSubjectLog AS l ON l.userId = u.userId AND l.action = 'VIEW' AND l.`date` > DATE_SUB(UTC_TIMESTAMP(),INTERVAL 7 DAY)
			LEFT JOIN (
				SELECT userId, ROUND(AVG(weeklyCount), 1) AS 'weeklyAverage'
				FROM (
					SELECT
					userId,
					COUNT(DISTINCT subjectId) AS 'weeklyCount'
					FROM SupervisorToSubjectLog
					WHERE action = 'VIEW'
					GROUP BY userId, WEEK(`date`)
				) AS s
				GROUP BY userId
			) AS a ON a.userId = u.userId
			where p.canBeSupervisor = 1 AND p.isManager = 0 AND p.isAdministrator = 0
			GROUP BY u.userId
		";
		$data['supervisorData'] = $this->db->query($sql)->result_array();
		
		$lastThirtyDays = array();
		for($i = 30; $i >= 0; $i--) {
			$lastThirtyDays[] = date('Y-m-d', strtotime("now - $i days"));
		}
		
		// report trends
		$this->db->select("s.date, SUM(s.count) AS 'total'", FALSE)
			->from('ReportedContentStatistics AS s')
			->join('StatisticTypes AS t', 't.id = s.typeId')
			->like('t.type', '_unactioned', 'before')
			->where('s.date > DATE_SUB(UTC_DATE(), INTERVAL 30 DAY)', null, FALSE)
			->group_by('s.date');
		$reported = $this->db->get()->result_array();
		
		$reportedData = [];
		foreach($lastThirtyDays as $d) {
			$dateData = array();
			$dateData['date'] = $d;
			foreach($reported as $a) {
				if($a['date'] == $d) {
					$dateData['total'] = $a['total'];
				}
			}
			if(!isset($dateData['total'])) {
				$dateData['total'] = 0;
			}
			$reportedData[] = $dateData;
		}
		$data['reportedData'] = $reportedData;
		
		// actioned trends
		$this->db->select("s.date, SUM(s.count) AS 'total'", FALSE)
			->from('ReportedContentStatistics AS s')
			->join('StatisticTypes AS t', 't.id = s.typeId')
			->like('t.type', '_actioned', 'before')
			->where('s.date > DATE_SUB(UTC_DATE(), INTERVAL 30 DAY)', null, FALSE)
			->group_by('s.date');
		$actioned = $this->db->get()->result_array();
		
		$actionedData = [];
		foreach($lastThirtyDays as $d) {
			$dateData = array();
			$dateData['date'] = $d;
			foreach($actioned as $a) {
				if($a['date'] == $d) {
					$dateData['total'] = $a['total'];
				}
			}
			if(!isset($dateData['total'])) {
				$dateData['total'] = 0;
			}
			$actionedData[] = $dateData;
		}
		$data['actionedData'] = $actionedData;
		
		return $data;
	}
	
	
	/**************************************************************************
	*                           Helpers                                       *
	**************************************************************************/
	
	/**
	 * Retrieves the userId of the author of the reported content corresponding to the provided reportedContentId.
	 * Returns false if the report could not be found.
	 *
	 * @param int $reportId
	 * @return int | boolean
	 */
	function getReportedUserId($reportId) {
		$report = $this->getReportedContent($reportId);
		if(!$report) {
			return FALSE;
		} else {
			return $report['reportedUserId'];
		}
	}
	
	/**
	 * Retrieves the ReportedContent row for the given id.
	 * 
	 * @param int|string $reportedContentId
	 * @return boolean|array
	 */
	function getReportedContent($reportedContentId) {
		$this->db->from('ReportedContent')
			->where('id', $reportedContentId);
		$result = $this->db->get();
		if($result->num_rows() > 0) {
			return $result->first_row('array');
		} else {
			return FALSE;
		}
	}
	
	/**
	 * Returns reportedContent details by the content id, no matter what type of reported content it is.
	 * ie. both reported files and reported nodes can be retrieved this way, providing wither fileName or nodeId.
	 * 
	 * @param int|string $contentId
	 * @param string $contentType
	 * @return boolean|array
	 */
	function getReportedContentByContentId($contentId, $contentType) {
		$this->db->start_cache();
		$this->db->select('c.reportedUserId, c.extremeReviewed, r.reportedContentId');
		$this->db->join('ReportedContent AS c', 'c.id = r.reportedContentId');
		$this->db->stop_cache();
		if($contentType === 'node') {
			$this->db->select("r.nodeId AS 'contentId'")
				->from('ReportedNodes AS r')
				->where('r.nodeId', $contentId);
			$result = $this->db->get();
			$this->db->flush_cache();
			if($result->num_rows() > 0) {
				return $result->first_row('array');
			} else {
				return FALSE;
			}
		} else if($contentType === 'file') {
			$this->db->select("r.fileName AS 'contentId'")
				->from('ReportedFiles AS r')
				->where('r.fileName', $contentId);
			$result = $this->db->get();
			$this->db->flush_cache();
			if($result->num_rows() > 0) {
				return $result->first_row('array');
			} else {
				return FALSE;
			}
		}
		return FALSE;
	}
	
	/**
	 * Retrieves reportedContent based on the report id, returning contentType (node or file) and id (nodeId or fileName).
	 * 
	 * @param unknown $reportedContentId
	 * @return boolean
	 */
	function getActualReportedContent($reportedContentId) {
		$this->db->select("'node' AS 'contentType', nodeId AS 'id'", FALSE)
			->from('ReportedNodes')
			->where('reportedContentId', $reportedContentId);
		$result = $this->db->get();
		if($result->num_rows() > 0) {
			return $result->first_row('array');
		} else {
			$this->db->select("'file' AS 'contentType', fileName AS 'id'", FALSE)
				->from('ReportedFiles')
				->where('reportedContentId', $reportedContentId);
			$result = $this->db->get();
			if($result->num_rows() > 0) {
				return $result->first_row('array');
			} else {
				return FALSE;
			}
		}
	}
	
	/**
	 * Retrieves the row for the reported node with the provided nodeId. Returns false if the report could not be found.
	 *
	 * @param int $nodeId
	 * @return boolean | array
	 */
	function getReportedNode($nodeId) {
		$this->db->from('ReportedNodes')
			->where('nodeId', $nodeId);
		$result = $this->db->get();
		if($result->num_rows() == 0) {
			return FALSE;
		} else {
			return $result->first_row('array');
		}
	}
	
	/**
	 * Retrieves the row for the reported file with the provided fileName. Returns false if the report could not be found.
	 *
	 * @param string $fileName
	 * @return boolean | array
	 */
	function getReportedFile($fileName) {
		$this->db->from('ReportedFiles')
			->where('fileName', $fileName);
		$result = $this->db->get();
		if($result->num_rows() == 0) {
			return FALSE;
		} else {
			return $result->first_row('array');
		}
	}
	
	/**
	 * Returns the subjectId the reported content is related to.
	 * 
	 * @param int|string $reportId
	 */
	function getReportSubjectId($reportId) {
		$content = $this->getActualReportedContent($reportId);
		if(!$content) {
			return;
		}
		return $this->{$content['contentType']}->getSubjectId($content['id']);
	}
	
	/**
	 * Returns the user id of the user responsible for the given review.
	 
	 * @param int|string $reviewId
	 * @return boolean|string
	 */
	function getReviewerId($reviewId) {
		$this->db->select('reviewerId')
			->from('ReportedContentReviewsToUser')
			->where('id', $reviewId);
		$result = $this->db->get();
		if($result->num_rows() == 0) {
			return FALSE;
		} else {
			return $result->first_row('array')['reviewerId'];
		}
	}
	
	/**
	 * Returns the count of how many complaints are yet to be reviewed for reported content.
	 * 
	 * @param int $reportId
	 * @return number
	 */
	function getActiveComplaintCount($reportId) {
		$this->db->select("COUNT(`id`) AS 'count'", FALSE)
		->from('ReportComplaints')
		->where(array(
				'reportedContentId' => $reportId,
				'resolvedReviewId' => null
		))
		->group_by('reportedContentId');
		$result = $this->db->get();
		return (int)$result->num_rows() > 0 ? $result->first_row('array')['count'] : 0;
	}
	
	/**
	 * Returns how many times a user has written content that has been reported and reviewed.
	 *
	 * @param integer $userId
	 * @param integer $subjectId
	 * @return integer
	 */
	function countActionedReportsOnUser($userId, $subjectId) {
		$this->db->select("COUNT(DISTINCT r.id) AS 'report_count'")
		->from('ReportedContent AS r')
		->join('ReportedContentReviews AS v', 'v.reportedContentId = r.id')
		->join('ReportedNodes AS rn', 'rn.reportedContentId = r.id', 'left')
		->join('Nodes AS n', 'n.nodeId = rn.nodeId', 'left')
		->join('Post AS p', 'p.postId = n.postId', 'left')
		->join('ReportedFiles AS rf', 'rf.reportedContentId = r.id', 'left')
		->join('Content AS f', 'f.fileName = rf.fileName', 'left')
		->join('Subject AS s', 's.subjectId = p.subjectId OR s.subjectId = f.subjectId')
		->where(array(
			's.subjectId' => $subjectId,
			'r.reportedUserId' => $userId,
			'v.latestUserBan IS NOT NULL' => NULL,
			'v.latestUserWarning IS NOT NULL' => NULL,
			'v.latestUserAutoflag IS NOT NULL' => NULL,
			'v.latestPostDeleted IS NOT NULL' => NULL,
			'v.latestAttachmentDeleted IS NOT NULL' => NULL,
			'v.latestPostEdited IS NOT NULL' => NULL,
			'v.latestThreadDeleted IS NOT NULL' => NULL
		));
		return (int)$this->db->get()->first_row('array')["report_count"];
	}
	
	/**
	 * Returns the percentage of forum users who have reported the given content.
	 *
	 * @param integer $id
	 * @return float
	 */
	function getReportPercentage($id) {
		$this->db->select("ROUND(COUNT(DISTINCT r.reportingUserId) / COUNT(DISTINCT s.userId) * 100, 2) AS 'percentage'", FALSE)
		->from('ReportedContentToUser AS r')
		->join('ReportedNodes AS rn', 'rn.reportedContentId = r.reportedContentId', 'left')
		->join('Nodes AS n', 'n.nodeId = rn.nodeId', 'left')
		->join('Post AS p', 'p.postId = n.postId', 'left')
		->join('ReportedFiles AS rf', 'rf.reportedContentId = r.reportedContentId', 'left')
		->join('Content AS f', 'f.fileName = rf.fileName', 'left')
		->join('Subject AS sub', 'sub.subjectId = p.subjectId OR sub.subjectId = f.subjectId')
		->join('UserToSubject AS s', 's.subjectId = sub.subjectId')
		->where(array(
			'r.reportedContentId' => $id
		));
		$result = $this->db->get();
		if($result->num_rows() > 0) {
			return $result->first_row('array')["percentage"];
		} else {
			return 0;
		}
	}
	
	/**
	 * Returns true if a resulting row from a report query is an active report that needs review, otherwise false.
	 * 
	 * @param array $reportRow
	 * @param string $type
	 * @return boolean
	 */
	function reportRowMeetsActiveRequirements($reportRow, $type) {
		if(!($type == 'supervisor' || $type == 'moderator' || $type == 'manager')) {
			return false;
		}
		
		$currentPercentage = $reportRow['currentPercentage'];
		$lastReviewPercentage = $reportRow['lastReviewPercentage'];
		if(($type == 'moderator'
				// Include for moderator if a mod has rereported it, it hasn't been reviewed before and it meets the first threshold or is an abuse report
				? ($reportRow['moderatorReportedCount'] > 0 || (is_null($lastReviewPercentage) && ($currentPercentage >= 6 || $reportRow['abuseCount'] > 0)))
				// Include for supervisor if it hasn't been reviewed before and it meets the first threshold or is extreme content or a complaint
				: ($type == 'supervisor'
					? ((is_null($lastReviewPercentage) && $currentPercentage >= 6) || ($reportRow['moderatorReportedCount'] > 0 && $reportRow['authorIsModerator']) || ($reportRow['extremeCount'] >= self::REPORT_EXTREME_COUNT && $reportRow['extremeReviewed'] == 0) || $reportRow['activeComplaintCount'] > 0)
					// Include for manager if it hasn't been reviewed before and it meets the first threshold or is a complaint or is extreme content
					: ((is_null($lastReviewPercentage) && $currentPercentage >= 6) || ($reportRow['authorIsModerator'] && $reportRow['activeComplaintCount'] > 0) || ($reportRow['extremeCount'] >= self::REPORT_EXTREME_COUNT && $reportRow['extremeReviewed'] == 0)))
			)
			// Include for all if thresholds are met
			|| ($lastReviewPercentage < 6 && $currentPercentage >= 6)
			|| ($lastReviewPercentage < 10 && $currentPercentage >= 10)
			|| ($lastReviewPercentage < 20 && $currentPercentage >= 20)
			|| ($lastReviewPercentage < 30 && $currentPercentage >= 30)
			|| ($lastReviewPercentage < 40 && $currentPercentage >= 40)
			|| ($lastReviewPercentage < 50 && $currentPercentage >= 50)
			|| ($lastReviewPercentage < 60 && $currentPercentage >= 60)
			|| ($lastReviewPercentage < 70 && $currentPercentage >= 70)
			|| ($lastReviewPercentage < 80 && $currentPercentage >= 80)
			|| ($lastReviewPercentage < 90 && $currentPercentage >= 90)
			|| ($lastReviewPercentage < 100 && $currentPercentage >= 100)
			)
		{
			return true;
		}
		return false;
	}
	
	/**
	 * Returns true if a resulting row from a report query is an inactive report (ie. has been reviewed, 
	 * and hasn't hit the threshold for the next review), otherwise false.
	 *
	 * @param array $reportRow
	 * @return boolean
	 */
	function reportRowMeetsInactiveRequirements($reportRow) {
		$currentPercentage = $reportRow['currentPercentage'];
		$lastReviewPercentage = $reportRow['latestPctUsersReported'];
		
		if(!(($lastReviewPercentage < 6 && $currentPercentage >= 6)
			|| ($lastReviewPercentage < 10 && $currentPercentage >= 10)
			|| ($lastReviewPercentage < 20 && $currentPercentage >= 20)
			|| ($lastReviewPercentage < 30 && $currentPercentage >= 30)
			|| ($lastReviewPercentage < 40 && $currentPercentage >= 40)
			|| ($lastReviewPercentage < 50 && $currentPercentage >= 50)
			|| ($lastReviewPercentage < 60 && $currentPercentage >= 60)
			|| ($lastReviewPercentage < 70 && $currentPercentage >= 70)
			|| ($lastReviewPercentage < 80 && $currentPercentage >= 80)
			|| ($lastReviewPercentage < 90 && $currentPercentage >= 90)
			|| ($lastReviewPercentage < 100 && $currentPercentage >= 100)
			)) 
		{
			return true;
		}
		return false;
	}
	
	/**
	 * Builds an sql query to retrieve unactioned reports for the specified user type and provided subjects.
	 * Subjects can be an array of ids, a single id, or not provided, in which case it is set as a query parameter to be added when
	 * the query is carried out.
	 *
	 * @param string $userType
	 * @param mixed $subjects
	 * @param integer $countryId (optional)
	 *
	 * @return string
	 */
	function getActiveReportQuery($userType, $subjects, $countryId = NULL) {
		if(is_array($subjects)) {
			$subjectClause = 'IN (' . implode(',', $subjects) . ')';
		} else if(is_int($subjects)) {
			$subjectClause = '= ' . $subjects;
		} else if (is_bool($subjects) && $subjects) {
			$subjectClause = 'IS NOT NULL';
		} else {
			$subjectClause = '= ?';
		}
		
		return "
			SELECT 
			DISTINCT `r`.`id`,
			`sub`.`subjectId`,
			`sub`.`subjectName` AS `subjectName`,
			`r`.`reportedUserId`,
			`us`.`userName`,
			`vc`.`id` AS 'currentReviewId',
			`r`.`viewedThisThreshold` AS 'viewed',
			IF(`authorPer`.`canBeModerator` AND ANY_VALUE(`authorModSub`.`userId`) IS NOT NULL, 1, 0) AS 'authorIsModerator',
			IF(`authorPer`.`canBeSupervisor` AND ANY_VALUE(`authorSuperSub`.`userId`) IS NOT NULL, 1, 0) AS 'authorIsSupervisor',
			ROUND(COUNT(DISTINCT `u`.`reportingUserId`) / COUNT(DISTINCT `s`.`userId`) * 100, 0) AS 'currentPercentage',
			# reports counted in total once per user, but user can report for multiple reasons and be counted each reason
			COUNT(DISTINCT `u`.`reportingUserId`) AS 'reportCount',
			COUNT(DISTINCT CASE WHEN `u`.`reason` = 'ADVERTISING' THEN `u`.`reportingUserId` END) AS 'advertisingCount',
			COUNT(DISTINCT CASE WHEN `u`.`reason` = 'AUTO' THEN `u`.`reportingUserId` END) AS 'autoCount',
			COUNT(DISTINCT CASE WHEN `u`.`reason` = 'TROLLING' THEN `u`.`reportingUserId` END) AS 'trollingCount',
			COUNT(DISTINCT CASE WHEN `u`.`reason` = 'ABUSE' THEN `u`.`reportingUserId` END) AS 'abuseCount',
			COUNT(DISTINCT CASE WHEN `u`.`reason` = 'INAPPROPRIATE' THEN `u`.`reportingUserId` END) AS 'inappropriateCount',
			COUNT(DISTINCT CASE WHEN `u`.`reason` = 'ADULT' THEN `u`.`reportingUserId` END) AS 'adultCount',
			COUNT(DISTINCT CASE WHEN `u`.`reason` = 'EXTREME' THEN `u`.`reportingUserId` END) AS 'extremeCount',
			COUNT(DISTINCT CASE WHEN `u`.`reason` = 'SPAM' THEN `u`.`reportingUserId` END) AS 'spamCount',
			COUNT(DISTINCT CASE WHEN `u`.`reason` = 'INAPBHVR' THEN `u`.`reportingUserId` END) AS 'inapbhvrCount',
			COUNT(DISTINCT CASE WHEN `u`.`reason` = 'OTHER' THEN `u`.`reportingUserId` END) AS 'otherCount',
			COUNT(DISTINCT `rc`.`id`) AS 'complaintCount',
			COUNT(DISTINCT IF(`rc`.`resolvedReviewId` IS NULL, `rc`.`id`, NULL)) AS 'activeComplaintCount',
			`r`.`extremeReviewed`,
			`u2`.`mostRecentReportDate` AS 'lastActiveDate', 
			`v`.`latestPctUsersReported` AS 'lastReviewPercentage', 
			`v`.`latestReviewDate` AS 'lastReviewDate',
			# Mod report counted if admin, super or mod reported it, and the report date is after the most recent review, or never been reviewed
			COUNT(DISTINCT IF((`per`.`canBeSupervisor` OR `per`.`isManager` OR `ms`.`userId` IS NOT NULL) AND (`v`.`latestReviewDate` IS NULL OR `v`.`latestReviewDate` < `u`.`reportDate`), `u`.`reportingUserId`, NULL)) AS 'moderatorReportedCount',
			GROUP_CONCAT(DISTINCT IF((`per`.`canBeSupervisor` OR `per`.`isManager` OR `per`.`isAdministrator` OR `ms`.`userId` IS NOT NULL) AND (`v`.`latestReviewDate` IS NULL OR `v`.`latestReviewDate` < `u`.`reportDate`), `usr`.`username`, NULL) SEPARATOR ',') AS 'moderatorReportedUserName',
			GROUP_CONCAT(DISTINCT IF((`per`.`canBeSupervisor` OR `per`.`isManager` OR `per`.`isAdministrator` OR `ms`.`userId` IS NOT NULL) AND (`v`.`latestReviewDate` IS NULL OR `v`.`latestReviewDate` < `u`.`reportDate`), IF(`per`.`isAdministrator` = 1, 'admin', IF(`per`.`isManager` = 1, 'manager', IF(`per`.`canBeSupervisor` = 1, 'supervisor', 'moderator'))), NULL) SEPARATOR ',') AS 'moderatorReportedLevel'
			FROM `ReportedContent` AS `r` 
			JOIN `ReportedContentToUser` AS `u` ON `u`.`reportedContentId` = `r`.`id`
			JOIN (
				SELECT
				`reportedContentId`,
				MAX(`reportDate`) AS 'mostRecentReportDate'
				FROM `ReportedContentToUser`
				GROUP BY `reportedContentId`
				) AS `u2` ON `u2`.`reportedContentId` = `u`.`reportedContentId`
			LEFT JOIN `ReportedNodes` AS `rn` ON `rn`.`reportedContentId` = `r`.`id`
			LEFT JOIN `Nodes` AS `n` ON `n`.`nodeId` = `rn`.`nodeId` AND (`n`.draftNode = FALSE || `n`.draftNodeVisible = TRUE)
			LEFT JOIN `Post` AS `p` ON `p`.`postId` = `n`.`postId`
			LEFT JOIN `ReportedFiles` AS `rf` ON `rf`.`reportedContentId` = `r`.`id`
			LEFT JOIN `Content` AS `f` ON `f`.`fileName` = `rf`.`fileName`
			JOIN `Subject` AS `sub` ON `sub`.`subjectId` = `p`.`subjectId` OR `sub`.`subjectId` = `f`.`subjectId`
			JOIN `UserToSubject` AS `s` ON `s`.`subjectId` = `sub`.`subjectId`
			JOIN `User` AS `us` ON `us`.`userId` = `r`.`reportedUserId`
			JOIN `Permissions` AS `authorPer` ON `authorPer`.`userId` = `us`.`userId`
			LEFT JOIN `ModeratorToSubject` AS `authorModSub` ON `authorModSub`.`subjectId` = `sub`.`subjectId` AND `authorModSub`.`userId` = `us`.`userId` AND `authorModSub`.`moderator` = 1 AND `authorModSub`.`approved` = 1
			JOIN `Permissions` AS `per` ON `per`.`userId` = `u`.`reportingUserId`
			JOIN `User` AS `usr` ON `usr`.`userId` = `per`.`userId`
			LEFT JOIN `ModeratorToSubject` AS `ms` ON `ms`.`subjectId` = `sub`.`subjectId` AND `ms`.`userId` = `u`.`reportingUserId` AND `ms`.`moderator` = 1 AND `ms`.`approved` = 1
			LEFT JOIN `SupervisorToSubject` AS `authorSuperSub` ON `authorSuperSub`.`subjectId` = `sub`.`subjectId` AND `authorSuperSub`.`userId` = `us`.`userId`
			LEFT JOIN (
				SELECT `a`.*
				FROM `ReportedContentReviews` AS `a`
				LEFT JOIN `ReportedContentReviews` AS `b` ON `a`.`reportedContentId` = `b`.`reportedContentId` AND `a`.`latestReviewDate` < `b`.`latestReviewDate`
				LEFT JOIN `ReportedNodes` AS `rn` ON `rn`.`reportedContentId` = `a`.`reportedContentId`
				LEFT JOIN `Nodes` AS `n` ON `n`.`nodeId` = `rn`.`nodeId`
				LEFT JOIN `Post` AS `p` ON `p`.`postId` = `n`.`postId`
				LEFT JOIN `ReportedFiles` AS `rf` ON `rf`.`reportedContentId` = `a`.`reportedContentId`
				LEFT JOIN `Content` AS `f` ON `f`.`fileName` = `rf`.`fileName`
				JOIN `Subject` AS `sub` ON `sub`.`subjectId` = `p`.`subjectId` OR `sub`.`subjectId` = `f`.`subjectId`
				WHERE `b`.`id` IS NULL 
				AND `sub`.`subjectId` " . $subjectClause . "
				AND (`a`.`latestUserBan` IS NOT NULL
				OR `a`.`latestUserWarning` IS NOT NULL
				OR `a`.`latestUserAutoflag` IS NOT NULL
				OR `a`.`latestPostDeleted` IS NOT NULL
				OR `a`.`latestAttachmentDeleted` IS NOT NULL
				OR `a`.`latestPostEdited` IS NOT NULL
				OR `a`.`latestThreadDeleted` IS NOT NULL)
				) AS `v` ON `v`.`reportedContentId` = `r`.`id`
			LEFT JOIN `ReportedContentReviews` AS `vc` ON `vc`.`reportedContentId` = `r`.`id` AND (
				`vc`.`latestUserBan` IS NULL 
				AND `vc`.`latestUserWarning` IS NULL
				AND `vc`.`latestUserAutoflag` IS NULL
				AND `vc`.`latestPostDeleted` IS NULL
				AND `vc`.`latestAttachmentDeleted` IS NULL
				AND `vc`.`latestPostEdited` IS NULL
				AND `vc`.`latestThreadDeleted` IS NULL)
			LEFT JOIN `ReportComplaints` AS `rc` ON `rc`.`reportedContentId` = `r`.`id`
			WHERE `sub`.`subjectId` " . $subjectClause . "
			" . (is_null($countryId) ? '' : 'AND `us`.`countryId` = ?') . "
			GROUP BY `r`.`id`,`sub`.`subjectId`, `sub`.`subjectName`, `vc`.`id`, `v`.`id`
			HAVING " . $this->getReportsHavingClause($userType, true) . "
			ORDER BY `u2`.`mostRecentReportDate` DESC
		";
	}
	
	/**
	 * Builds an sql query to retrieve actioned reports for the specified user type and provided subjects.
	 * Subjects can be an array of ids, a single id, or not provided, in which case it is set as a query parameter to be added when 
	 * the query is carried out.
	 * 
	 * @param string $userType
	 * @param mixed $subjects
	 * @param integer $countryId (optional)
	 * 
	 * @return string
	 */
	function getInactiveReportQuery($userType, $subjects, $countryId = NULL) {
		
		if(is_array($subjects)) {
			$subjectClause = 'IN (' . implode(',', $subjects) . ')';
		} else if(is_int($subjects)) {
			$subjectClause = '= ' . $subjects;
		} else if (is_bool($subjects) && $subjects) {
			$subjectClause = 'IS NOT NULL';
		} else {
			$subjectClause = '= ?';
		}
		return "
			SELECT
			DISTINCT `v`.`reportedContentId` AS 'id',
			`sub`.`subjectId`,
			`sub`.`subjectName` AS `subjectName`,
			# reportId duplicated for backwards compatibility
			`v`.`reportedContentId` AS 'reportId',
			`r`.`viewedThisThreshold` AS 'viewed',
			`rr`.`latestPctUsersReported`,
			`rr`.`latestReviewDate` AS `lastActiveDate`,
			ROUND(COUNT(DISTINCT `ru`.`reportingUserId`) / COUNT(DISTINCT `s`.`userId`) * 100, 0) AS 'currentPercentage',
			# rereported by mod
			COUNT(DISTINCT IF((`per`.`canBeSupervisor` OR `per`.`isManager` OR `ms`.`userId` IS NOT NULL) AND `rr`.`latestReviewDate` < `ru`.`reportDate`, `ru`.`reportingUserId`, NULL)) AS 'moderatorReportedCount',
			GROUP_CONCAT(DISTINCT IF((`per`.`canBeSupervisor` OR `per`.`isManager` OR `per`.`isAdministrator` OR `ms`.`userId` IS NOT NULL) AND `rr`.`latestReviewDate` < `ru`.`reportDate`, `usr`.`username`, NULL) SEPARATOR ',') AS 'moderatorReportedUserName',
			GROUP_CONCAT(DISTINCT IF((`per`.`canBeSupervisor` OR `per`.`isManager` OR `per`.`isAdministrator` OR `ms`.`userId` IS NOT NULL) AND `rr`.`latestReviewDate` < `ru`.`reportDate`, IF(`per`.`isAdministrator` = 1, 'admin', IF(`per`.`isManager` = 1, 'manager', IF(`per`.`canBeSupervisor` = 1, 'supervisor', 'moderator'))), NULL) SEPARATOR ',') AS 'moderatorReportedLevel',
			IF(`authorPer`.`canBeModerator` AND ANY_VALUE(`authorModSub`.`userId`) IS NOT NULL, 1, 0) AS 'authorIsModerator',
			IF(`authorPer`.`canBeSupervisor` AND ANY_VALUE(`authorSuperSub`.`userId`) IS NOT NULL, 1, 0) AS 'authorIsSupervisor',
			# reports counted in total once per user, but user can report for multiple reasons and be counted each reason
			COUNT(DISTINCT `ru`.`reportingUserId`) AS 'reportCount',
			COUNT(DISTINCT CASE WHEN `ru`.`reason` = 'ADVERTISING' THEN `ru`.`reportingUserId` END) AS 'advertisingCount',
			COUNT(DISTINCT CASE WHEN `ru`.`reason` = 'AUTO' THEN `ru`.`reportingUserId` END) AS 'autoCount',
			COUNT(DISTINCT CASE WHEN `ru`.`reason` = 'TROLLING' THEN `ru`.`reportingUserId` END) AS 'trollingCount',
			COUNT(DISTINCT CASE WHEN `ru`.`reason` = 'ABUSE' THEN `ru`.`reportingUserId` END) AS 'abuseCount',
			COUNT(DISTINCT CASE WHEN `ru`.`reason` = 'INAPPROPRIATE' THEN `ru`.`reportingUserId` END) AS 'inappropriateCount',
			COUNT(DISTINCT CASE WHEN `ru`.`reason` = 'ADULT' THEN `ru`.`reportingUserId` END) AS 'adultCount',
			COUNT(DISTINCT CASE WHEN `ru`.`reason` = 'EXTREME' THEN `ru`.`reportingUserId` END) AS 'extremeCount',
			COUNT(DISTINCT CASE WHEN `ru`.`reason` = 'SPAM' THEN `ru`.`reportingUserId` END) AS 'spamCount',
			COUNT(DISTINCT CASE WHEN `ru`.`reason` = 'INAPBHVR' THEN `ru`.`reportingUserId` END) AS 'inapbhvrCount',
			COUNT(DISTINCT CASE WHEN `ru`.`reason` = 'OTHER' THEN `ru`.`reportingUserId` END) AS 'otherCount',
			COUNT(DISTINCT `rc`.`id`) AS 'complaintCount',
			COUNT(DISTINCT IF(`rc`.`resolvedReviewId` IS NULL, `rc`.`id`, NULL)) AS 'activeComplaintCount',
			`r`.`extremeReviewed`
			FROM `ReportedContentReviews` AS `v`
			JOIN `ReportedContent` AS `r` ON `r`.`id` = `v`.`reportedContentId`
			LEFT JOIN `ReportedContentReviews` AS `c` ON `c`.`reportedContentId` = `v`.`reportedContentId`
			JOIN `ReportedContentToUser` AS `ru` ON `ru`.`reportedContentId` = `v`.`reportedContentId`
			LEFT JOIN `ReportedNodes` AS `rn` ON `rn`.`reportedContentId` = `v`.`reportedContentId`
			LEFT JOIN `Nodes` AS `n` ON `n`.`nodeId` = `rn`.`nodeId` AND (`n`.draftNode = FALSE || `n`.draftNodeVisible = TRUE)
			LEFT JOIN `Post` AS `p` ON `p`.`postId` = `n`.`postId`
			LEFT JOIN `ReportedFiles` AS `rf` ON `rf`.`reportedContentId` = `v`.`reportedContentId`
			LEFT JOIN `Content` AS `f` ON `f`.`fileName` = `rf`.`fileName`
			JOIN `Subject` AS `sub` ON `sub`.`subjectId` = `p`.`subjectId` OR `sub`.`subjectId` = `f`.`subjectId`
			JOIN `User` AS `us` ON `us`.`userId` = `r`.`reportedUserId`
			JOIN `Permissions` AS `authorPer` ON `authorPer`.`userId` = `us`.`userId`
			LEFT JOIN `ModeratorToSubject` AS `authorModSub` ON `authorModSub`.`subjectId` = `sub`.`subjectId` AND `authorModSub`.`userId` = `us`.`userId` AND `authorModSub`.`moderator` = 1 AND `authorModSub`.`approved` = 1
			LEFT JOIN `SupervisorToSubject` AS `authorSuperSub` ON `authorSuperSub`.`subjectId` = `sub`.`subjectId` AND `authorSuperSub`.`userId` = `us`.`userId`
			JOIN `Permissions` AS `per` ON `per`.`userId` = `ru`.`reportingUserId`
			JOIN `User` AS `usr` ON `usr`.`userId` = `per`.`userId`
			LEFT JOIN `ModeratorToSubject` AS `ms` ON `ms`.`subjectId` = `sub`.`subjectId` AND `ms`.`userId` = `ru`.`reportingUserId` AND `ms`.`moderator` = 1 AND `ms`.`approved` = 1
			JOIN `UserToSubject` AS `s` ON `s`.`subjectId` = `sub`.`subjectId`
			LEFT JOIN (
				SELECT `a`.*
				FROM `ReportedContentReviews` AS `a`
				LEFT JOIN `ReportedContentReviews` AS `b` ON `a`.`reportedContentId` = `b`.`reportedContentId` AND `a`.`latestReviewDate` < `b`.`latestReviewDate`
				LEFT JOIN `ReportedNodes` AS `rn` ON `rn`.`reportedContentId` = `a`.`reportedContentId`
				LEFT JOIN `Nodes` AS `n` ON `n`.`nodeId` = `rn`.`nodeId`
				LEFT JOIN `Post` AS `p` ON `p`.`postId` = `n`.`postId`
				LEFT JOIN `ReportedFiles` AS `rf` ON `rf`.`reportedContentId` = `a`.`reportedContentId`
				LEFT JOIN `Content` AS `f` ON `f`.`fileName` = `rf`.`fileName`
				JOIN `Subject` AS `sub` ON `sub`.`subjectId` = `p`.`subjectId` OR `sub`.`subjectId` = `f`.`subjectId`
				WHERE `b`.`id` IS NULL
				AND `sub`.`subjectId` " . $subjectClause . "
				AND (`a`.`latestUserBan` IS NOT NULL
				OR `a`.`latestUserWarning` IS NOT NULL
				OR `a`.`latestUserAutoflag` IS NOT NULL
				OR `a`.`latestPostDeleted` IS NOT NULL
				OR `a`.`latestAttachmentDeleted` IS NOT NULL
				OR `a`.`latestPostEdited` IS NOT NULL
				OR `a`.`latestThreadDeleted` IS NOT NULL)
			) AS `rr` ON `v`.`reportedContentId` = `rr`.`reportedContentId`
			# Exclude active complaints
			LEFT JOIN `ReportComplaints` AS `rc` ON `rc`.`reportedContentId` = `r`.`id`
			WHERE (`v`.`latestUserBan` IS NOT NULL
				OR `v`.`latestUserWarning` IS NOT NULL
				OR `v`.`latestUserAutoflag` IS NOT NULL
				OR `v`.`latestPostDeleted` IS NOT NULL
				OR `v`.`latestAttachmentDeleted` IS NOT NULL
				OR `v`.`latestPostEdited` IS NOT NULL
				OR `v`.`latestThreadDeleted` IS NOT NULL
			)
			AND `c`.`reportedContentId` IS NOT NULL
			AND `sub`.`subjectId` " . $subjectClause . "
			" . (is_null($countryId) ? '' : 'AND `us`.`countryId` = ?') . "
			GROUP BY `v`.`reportedContentId`, `sub`.`subjectId`, `sub`.`subjectName`, `rr`.`id`
			HAVING `activeComplaintCount` = 0 AND (" . $this->getReportsHavingClause($userType, false) . ")
			ORDER BY `rr`.`latestReviewDate` DESC";
	}
	
	/**
	 * Returns the HAVING clause for a query finding reported nodes that excludes irrelevant rows based on the user type. Moved to a function 
	 * so it can be modified in one place.
	 * 
	 * @param srting $userType
	 * @param boolean $active
	 * @return string|boolean
	 */
	function getReportsHavingClause($userType, $active) {
		switch ($userType) {
			case 'moderator':
				return '`activeComplaintCount` = 0 AND (`extremeCount` < ' . self::REPORT_EXTREME_COUNT . ' OR `extremeReviewed` = 1) AND `authorIsModerator` = 0 AND `authorIsSupervisor` = 0';
				break;
			case 'supervisor':
				$h = '((`' . ($active ? 'activeC' : 'c') . 'omplaintCount` > 0 AND `authorIsModerator` = 0)';
				$h .= ' OR (`extremeCount` >= ' . self::REPORT_EXTREME_COUNT . ' AND `extremeReviewed` = ' . ($active ? '0' : '1') . ')';
				$h .= ' OR (`' . ($active ? 'activeC' : 'c') . 'omplaintCount` = 0 AND `authorIsModerator` = 1)) AND `authorIsSupervisor` = 0';
				return $h;
				break;
			case 'manager':
				return '(`' . ($active ? 'activeC' : 'c') . 'omplaintCount` > 0 AND `authorIsModerator` = 1) OR `authorIsSupervisor` = 1';
			default: 
				return false;
		}
	}
	
	/**
	 * Retrieves the count of unactioned reports for a specific user in either all their managed subjects, or a specific one.
	 *
	 * @param integer $userId
	 * @param integer $subjectId
	 * @param string $userType
	 *
	 * @return string
	 */
	function getActiveReportedPostCount($userId, $subjectId, $userType) {
		if($userType == 'manager') {
			return count($this->getAllManagerActiveReports());
		}
		if(is_null($userId) && ($userType == 'supervisor' || is_null($subjectId))) {
			return false;
		}
		switch ($userType) {
			case 'moderator':
				$initialIfCriteria = '`abuseCount` > 0 OR `moderatorReportedCount` > 0';
				$userToSubjectJoin = 'JOIN `ModeratorToSubject` AS `us` ON `us`.`subjectId` = `sub`.`subjectId` AND `us`.`moderator` = 1 AND `us`.`approved` = 1';
				$whereClause = is_null($userId) ? '`us`.`subjectId` = ?' : '`us`.`userId` = ?';
				break;
			case 'supervisor':
				$initialIfCriteria = '(`extremeCount` >= ' . self::REPORT_EXTREME_COUNT . ' AND `extremeReviewed` = 0) OR `activeComplaintCount` > 0';
				$userToSubjectJoin = 'JOIN `SupervisorToSubject` AS `us` ON `us`.`subjectId` = `sub`.`subjectId`';
				$whereClause = '`us`.`userId` = ?';
				break;
			default:
				return false;
		}
		$havingClause = $this->getReportsHavingClause($userType, true);
	
		$reportedPostSql = "
		SELECT
		COUNT(IF(`currentReviewId` IS NOT NULL, `id`,
			IF((`lastReviewPercentage` IS NULL AND (`currentPercentage` >= 6 OR " . $initialIfCriteria . "))
				OR (`lastReviewPercentage` < 10 AND `currentPercentage` >= 10)
				OR (`lastReviewPercentage` < 20 AND `currentPercentage` >= 20)
				OR (`lastReviewPercentage` < 30 AND `currentPercentage` >= 30)
				OR (`lastReviewPercentage` < 40 AND `currentPercentage` >= 40)
				OR (`lastReviewPercentage` < 50 AND `currentPercentage` >= 50)
				OR (`lastReviewPercentage` < 60 AND `currentPercentage` >= 60)
				OR (`lastReviewPercentage` < 70 AND `currentPercentage` >= 70)
				OR (`lastReviewPercentage` < 80 AND `currentPercentage` >= 80)
				OR (`lastReviewPercentage` < 90 AND `currentPercentage` >= 90)
				OR (`lastReviewPercentage` < 100 AND `currentPercentage` >= 100),
			`id`, NULL
			)
		)) AS 'count'
		FROM (
			SELECT
			`r`.`id`,
			`c`.`id` AS 'currentReviewId',
			IF(`authorPer`.`canBeModerator` AND ANY_VALUE(`authorModSub`.`userId`) IS NOT NULL, 1, 0) AS 'authorIsModerator',
			IF(`authorPer`.`canBeSupervisor` AND ANY_VALUE(`authorSuperSub`.`userId`) IS NOT NULL, 1, 0) AS 'authorIsSupervisor',
			ROUND(COUNT(DISTINCT `u`.`reportingUserId`) / COUNT(DISTINCT `s`.`userId`) * 100, 0) AS 'currentPercentage',
			COUNT(DISTINCT CASE WHEN `u`.`reason` = 'EXTREME' THEN `u`.`reportingUserId` END) AS 'extremeCount',
			COUNT(DISTINCT CASE WHEN `u`.`reason` = 'ABUSE' THEN `u`.`reportingUserId` END) AS 'abuseCount',
			COUNT(DISTINCT `rc`.`id`) AS 'complaintCount',
			COUNT(DISTINCT IF(`rc`.`resolvedReviewId` IS NULL, `rc`.`id`, NULL)) AS 'activeComplaintCount',
			`v`.`latestPctUsersReported` AS 'lastReviewPercentage',
			`v`.`latestReviewDate` AS 'lastReviewDate',
			COUNT(DISTINCT IF(`per`.`canBeSupervisor` OR `per`.`isManager` OR `ms`.`userId` IS NOT NULL, `u`.`reportingUserId`, NULL)) AS 'moderatorReportedCount',
			`r`.`extremeReviewed`
			FROM `ReportedContent` AS `r`
			JOIN `ReportedContentToUser` AS `u` ON `u`.`reportedContentId` = `r`.`id`
			LEFT JOIN `ReportedNodes` AS `rn` ON `rn`.`reportedContentId` = `r`.`id`
			LEFT JOIN `Nodes` AS `n` ON `n`.`nodeId` = `rn`.`nodeId`
			LEFT JOIN `Post` AS `p` ON `p`.`postId` = `n`.`postId`
			LEFT JOIN `ReportedFiles` AS `rf` ON `rf`.`reportedContentId` = `r`.`id`
			LEFT JOIN `Content` AS `f` ON `f`.`fileName` = `rf`.`fileName`
			JOIN `Subject` AS `sub` ON `sub`.`subjectId` = `p`.`subjectId` OR `sub`.`subjectId` = `f`.`subjectId`
			JOIN `Permissions` AS `authorPer` ON `authorPer`.`userId` = `r`.`reportedUserId`
			LEFT JOIN `ModeratorToSubject` AS `authorModSub` ON `authorModSub`.`subjectId` = `sub`.`subjectId` AND `authorModSub`.`userId` = `authorPer`.`userId` AND `authorModSub`.`moderator` = 1 AND `authorModSub`.`approved` = 1
			LEFT JOIN `SupervisorToSubject` AS `authorSuperSub` ON `authorSuperSub`.`subjectId` = `sub`.`subjectId` AND `authorSuperSub`.`userId` = `authorPer`.`userId`
			# Make sure only counts subjects current user can manage
			" . $userToSubjectJoin . "
			JOIN `UserToSubject` AS `s` ON `s`.`subjectId` = `sub`.`subjectId`
			JOIN `Permissions` AS `per` ON `per`.`userId` = `u`.`reportingUserId`
			# Track reports by any moderator so it's included directly
			LEFT JOIN `ModeratorToSubject` AS `ms` ON `ms`.`userId` = `u`.`reportingUserId` AND `ms`.`subjectId` = `sub`.`subjectId` AND `ms`.`moderator` = 1 AND `ms`.`approved` = 1
			LEFT JOIN (
				SELECT `a`.*
				FROM `ReportedContentReviews` AS `a`
				LEFT JOIN `ReportedContentReviews` AS `b` ON `a`.`reportedContentId` = `b`.`reportedContentId` AND `a`.`latestReviewDate` < `b`.`latestReviewDate`
				WHERE `b`.`id` IS NULL
				) AS `v` ON `v`.`reportedContentId` = `r`.`id`
			LEFT JOIN `ReportedContentReviews` AS `c` ON `c`.`reportedContentId` = `r`.`id` AND 
				(`c`.`latestUserBan` IS NULL
				AND `c`.`latestUserWarning` IS NULL
				AND `c`.`latestUserAutoflag` IS NULL
				AND `c`.`latestPostDeleted` IS NULL
				AND `c`.`latestAttachmentDeleted` IS NULL
				AND `c`.`latestPostEdited` IS NULL
				AND `c`.`latestThreadDeleted` IS NULL
			)
			LEFT JOIN `ReportComplaints` AS `rc` ON `rc`.`reportedContentId` = `r`.`id`
			WHERE " . $whereClause . "
			GROUP BY `r`.`id`, `c`.`id`, `v`.`id`
			HAVING " . $havingClause . "
			) q
		";
		return (int)$this->db->query($reportedPostSql, array(is_null($userId) ? $subjectId : $userId))->first_row('array')["count"];
	}
	
	/**
	 * Returns how many overdue reports there are in total.
	 * 
	 * @return number
	 */
	function getOverdueReportedPostCount() {
		$allOverdue = $this->getOverdueReportIds();
		return count($allOverdue);
	}
	
	/**
	 * Sort function for supervisor summary data. Sorts by personal attacks, then most outstanding, then last reviewed.
	 * @todo - check this function works for subjects which haven't been visited. Is arbitrary order ok for these?
	 * @param array $a
	 * @param array $b
	 */
	private static function sortSubjectRows($a, $b) {
		$aUnactionedRow = $a['rows'][0];
		$bUnactionedRow = $b['rows'][0];
		if($aUnactionedRow->personalAttacks == $bUnactionedRow->personalAttacks) {
			if($aUnactionedRow->userReports == $bUnactionedRow->userReports) {
				return strtotime($a['lastVisit']) < strtotime($b['lastVisit']) ? -1 : +1;
			}
			return $a['rows'][0]->userReports < $b['rows'][0]->userReports ? 1 : -1;
		}
		return $a['rows'][0]->personalAttacks < $b['rows'][0]->personalAttacks ? 1 : -1;
	}
	
	/**
	 * Returns a list of allowed reasons for reporting a node. Means reports can be searched for by reason easily.
	 * Other included as a fallback but shouldn't be used
	 * 
	 * @return array
	 */
	function getReasons() {
		return array(
			'AUTO', 
			'TROLLING',
			'ADVERTISING',
			'ABUSE',
			'INAPPROPRIATE',
			'ADULT',
			'EXTREME',
			'COMPLAINT',
			'SPAM',
			'INAPBHVR',
			'OTHER'
		);
	}
	
	/**
	 * Returns the user friendly text for the given reporting reason.
	 * 
	 * @param string $reason
	 * @return string
	 */
	function getReasonLabel($reasons) {
		$label = '';
		$reasonCount = count($reasons);
		for ($i = 0; $i < $reasonCount; $i++) {
			if (!array_key_exists($i, $reasons)) {
				continue;
			}
			$reason = mb_strtolower($reasons[$i]);
			if($i !== 0) {
				if($i === $reasonCount - 1) {
					$label .= ' and ';
				} else {
					$label .= ', ';
				}
			}
			switch ($reason) {
				case 'auto':
					$label .= 'automatically flagged';
					break;
				case 'trolling':
					$label .= 'trolling';
					break;
				case 'advertising':
					$label .= 'advertising';
					break;
				case 'abuse':
					$label .= 'personal attack directed at reporter';
					break;
				case 'inappropriate':
					$label .= 'inappropriate content';
					break;
				case 'adult':
					$label .= 'adult content';
					break;
				case 'extreme':
					$label .= 'extreme content';
					break;
				case 'complaint':
					$label .= 'user complaint';
					break;
				case 'spam':
					$label .= 'spam';
					break;
				case 'inapbhvr':
					$label .= 'inappropriate behaviour';
					break;
				case 'unban':
					$label .= 'ban removed';
					break;
				case 'undo':
					$label .= "a previous action being undone";
					break;
				case 'restored':
					$label .= 'content restored';
					break;
				default:
					$label .= '';
			}
		}
		return $label;
	}
	
	function getMessageFormattedReasons($reasons) {
		if (!is_array($reasons)) {
			$reasons = [$reasons];
		}
		
		$s = '<ul>';
		foreach($reasons as $r) {
			$s .= '<li>'.$this->getReasonLabel([$r]).'</li>';
		}
		$s .= '</ul>';
		return $s;
	}
	
	/**
	 * A report can be reported for multiple reasons, so get the major report reason by prioritising
	 * complaints, then the reason it was most reported for.
	 * 
	 * @param array $report - the full object from a report query
	 * @return string
	 */
	private function getMajorityReason($report, $type) {
		
		// active complaint the most important for supervisors and managers
		if ($type === 'supervisor' || 'manager') {
			if($report['activeComplaintCount'] > 0) {
				return 'complaint';
			}
		} 
		
		// extreme next in priority for supervisors
		if ($type === 'supervisor') {
			if($report['extremeCount'] >= self::REPORT_EXTREME_COUNT) {
				return 'extreme';
			}
		}
		
		// abuse report has priority over rest for moderators and supervisors
		if ($type === 'moderator' || $type === 'supervisor') {
			if($report['abuseCount'] > 0) {
				return 'abuse';
			}
		}

		// prioritise reasons
		$maxCount = 0;
		$majorityReason = '';
		foreach($this->getReasons() as $r) {
			$reason = mb_strtolower($r);
			if($report[$reason.'Count'] > $maxCount) {
				$maxCount = $report[$reason.'Count'];
				$majorityReason = $reason;
			}
		}
		return $majorityReason;
	}
	
	function setViewedThisThreshold($reportId, $value) {
		$this->db->update('ReportedContent', ['viewedThisThreshold' => $value], ['id' => $reportId]);
	}
	
	function flagReportedUser($userId, $subjectId, &$error) {
		
		$reportingUserId = $this->user->authCheck();
		if (!$reportingUserId || !$this->user->canManageSubject($reportingUserId, $subjectId)) {
			return false;
		}
		
		if($this->isReportedUser($userId, $subjectId)) {
			$error = 'This user is already flagged for automatic content.';
			return false;
		}

		$this->db->insert('ReportedUserToSubject', 
			array(  'reportedUserId' => $userId,
					'reportingUserId' => $reportingUserId,
					'subjectId' => $subjectId)
			);
		return true;
	}

	function unflagReportedUser($userId, $subjectId, &$error) {

		$reportingUserId = $this->user->authCheck();
		if (!$reportingUserId || !$this->user->canManageSubject($reportingUserId, $subjectId)) {
			return false;
		}
		
		if(!$this->isReportedUser($userId, $subjectId)) {
			$error = 'This user is not flagged for automatic content.';
			return false;
		}

		$this->db->delete('ReportedUserToSubject', 
			array(  'reportedUserId' => $userId,
					'subjectId' => $subjectId)
			);
		return $this->db->affected_rows();
	}

	function isReportedUser($userId, $subjectId) {

		$this->db->select('reportingUserId')
				->where('reportedUserId', $userId)
				->where('subjectId', $subjectId);
		
		$query = $this->db->get('ReportedUserToSubject');

		if ($query->num_rows() > 0) {
			return $query->row()->reportingUserId;
		}
		return false;
	}

}
