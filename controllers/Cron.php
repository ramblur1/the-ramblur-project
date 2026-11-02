<?php
class Cron extends CI_Controller {
	
	private $jobs = NULL;
	
	function __construct() {
		parent::__construct();
		if(!is_cli()) {
			show_error('Web access is not allowed.');
		}
		// Build array of jobs which should be run on defined schedules
		$this->jobs = array(
			'now' => array(
			),
			'15Min' => array(
				'removeUnrelatedContent'
				//'updatePostTimestamps' // update Post Ad timestamps to show at correct time each day
			),
			'hourly' => array(),
			'daily' => array(
				'checkForResolvedErrorReports',
				'recordReportedNodeStats'
			),
			'daily2' => array(
				'emailCheck',
				'checkEvents' // send messages to subscribers who have events in next 24 hours
				//'processMonthlyDistributedPayments' // send authorized moderator payments to PayPal
			),
			'weekly' => array(),
			'monthly' => array('recordMonthlyStatistics'),
			//'2ndOfEachMonth' => array('calculateMonthlyDistributedPayments'), //calculate moderator payments
			'yearly' => array()
		//	'ondemand' => array() // @todo - allow function to be called directly from cli
		);
	}
	
	/**
	 * Called by cronjob (or otherwise from CLI) with the desired schedule. Runs each job assigned to 
	 * the schedule. eg. php {webroot}/index.php cron/run/{schedule}
	 * @param string $schedule
	 * @throws Exception
	 */
	function run($schedule = false) {
		try {
			if(!$schedule) {
				throw new Exception('You must provide a schedule to determine which jobs should be run.');
			}
			if(!isset($this->jobs[$schedule])) {
				throw new Exception('The schedule specified has not been defined.');
			}
			foreach($this->jobs[$schedule] as $job) {
				$this->$job();
			}
		} catch (Exception $e) {
			echo $e->getMessage();
		}
	}
	
	/**
	 * Stores monthly statistics, and resets any monthly cumulative data in the database eg. monthly logins
	 */
	function recordMonthlyStatistics() {
		$this->load->model('statistics');
		$loginTypeId = $this->statistics->getStatisticTypeId('logins');
		
		// Login counts for the previous month 
		$loginSql = "INSERT INTO MonthlyStatistics (year, month, countryId, typeId, total, males, females, `12-18`, `19-28`, `29-36`, `36-45`, `46-55`, `56+`, `administrators`, `managers`, `supervisors`, `moderators`, `standard`) 
			SELECT
			YEAR(DATE_SUB(NOW(),INTERVAL 1 DAY)),
			MONTH(DATE_SUB(NOW(),INTERVAL 1 DAY)),
			countryId,
			$loginTypeId,
			SUM(loginCountThisMonth),
			SUM(IF(`gender` = 'M', loginCountThisMonth, 0)),
			SUM(IF(`gender` = 'F', loginCountThisMonth, 0)),
			SUM(IF(`DOB` BETWEEN DATE_SUB(NOW(),INTERVAL 18 YEAR) AND DATE_SUB(NOW(),INTERVAL 12 YEAR), loginCountThisMonth, 0)),
			SUM(IF(`DOB` BETWEEN DATE_SUB(NOW(),INTERVAL 28 YEAR) AND DATE_SUB(NOW(),INTERVAL 19 YEAR), loginCountThisMonth, 0)),
			SUM(IF(`DOB` BETWEEN DATE_SUB(NOW(),INTERVAL 35 YEAR) AND DATE_SUB(NOW(),INTERVAL 29 YEAR), loginCountThisMonth, 0)),
			SUM(IF(`DOB` BETWEEN DATE_SUB(NOW(),INTERVAL 45 YEAR) AND DATE_SUB(NOW(),INTERVAL 36 YEAR), loginCountThisMonth, 0)),
			SUM(IF(`DOB` BETWEEN DATE_SUB(NOW(),INTERVAL 55 YEAR) AND DATE_SUB(NOW(),INTERVAL 46 YEAR), loginCountThisMonth, 0)),
			SUM(IF(`DOB` > DATE_SUB(NOW(),INTERVAL 56 YEAR), loginCountThisMonth, 0)),
			SUM(IF(`p`.`isAdministrator` = 1, loginCountThisMonth, 0)),
			SUM(IF(`p`.`isManager` = 1 AND `p`.`isAdministrator` = 0, loginCountThisMonth, 0)),
			SUM(IF(`p`.`canBeSupervisor` = 1 AND `p`.`isManager` = 0 AND `p`.`isAdministrator` = 0, loginCountThisMonth, 0)),
			SUM(IF(`p`.`canBeModerator` = 1 AND `p`.`canBeSupervisor` = 0 AND `p`.`isManager` = 0 AND`p`.`isAdministrator` = 0, loginCountThisMonth, 0)),
			SUM(IF(`p`.`canBeModerator` = 0 AND `p`.`canBeSupervisor` = 0 AND `p`.`isManager` = 0 AND `p`.`isAdministrator` = 0, loginCountThisMonth, 0))
			FROM User AS u
			JOIN Permissions AS p ON p.userId = u.userId
			WHERE u.includeInStats = 1
			GROUP BY countryId";
		$this->db->query($loginSql);
		
		// Use transaction in an attempt to make sure logins while the query is running don't mess up data
		// Is this necessary? Logins aren't in transactions anyway.
		// @todo - Update query on entire user table is probably a bad idea. Look into alternatives
		// probably separate into different table
		$this->db->trans_start();
		// rest monthly login count for EVERY user
		$this->db->update('User',
			array('loginCountThisMonth' => 0));
		$this->db->trans_complete();
		
		// Forum member counts for right now. Since no date data is stored apart from this insert, just use a total at the 
		// start of every month (midnight on the first day of the month if the cron is run as @monthly) as the forum membership for the previous month
		
		$forumTypeId = $this->statistics->getStatisticTypeId('forum_members');
		
		$forumSql = "INSERT INTO MonthlyStatistics (year, month, subjectId, typeId, total, males, females, `12-18`, `19-28`, `29-36`, `36-45`, `46-55`, `56+`, `administrators`, `managers`, `supervisors`, `moderators`, `standard`) 
			SELECT
			YEAR(DATE_SUB(NOW(),INTERVAL 1 DAY)),
			MONTH(DATE_SUB(NOW(),INTERVAL 1 DAY)),
			s.subjectId,
			$forumTypeId,
			COUNT(s.userId),
			COUNT(IF(`u`.`gender` = 'M', s.userId, NULL)),
			COUNT(IF(`u`.`gender` = 'F', s.userId, NULL)),
			COUNT(IF(`u`.`DOB` BETWEEN DATE_SUB(NOW(),INTERVAL 18 YEAR) AND DATE_SUB(NOW(),INTERVAL 12 YEAR), s.userId, NULL)),
			COUNT(IF(`u`.`DOB` BETWEEN DATE_SUB(NOW(),INTERVAL 28 YEAR) AND DATE_SUB(NOW(),INTERVAL 19 YEAR), s.userId, NULL)),
			COUNT(IF(`u`.`DOB` BETWEEN DATE_SUB(NOW(),INTERVAL 35 YEAR) AND DATE_SUB(NOW(),INTERVAL 29 YEAR), s.userId, NULL)),
			COUNT(IF(`u`.`DOB` BETWEEN DATE_SUB(NOW(),INTERVAL 45 YEAR) AND DATE_SUB(NOW(),INTERVAL 36 YEAR), s.userId, NULL)),
			COUNT(IF(`u`.`DOB` BETWEEN DATE_SUB(NOW(),INTERVAL 55 YEAR) AND DATE_SUB(NOW(),INTERVAL 46 YEAR), s.userId, NULL)),
			COUNT(IF(`u`.`DOB` > DATE_SUB(NOW(),INTERVAL 56 YEAR), s.userId, NULL)),
			COUNT(IF(`p`.`isAdministrator` = 1, s.userId, NULL)),
			COUNT(IF(`p`.`isManager` = 1 AND `p`.`isAdministrator` = 0, s.userId, NULL)),
			COUNT(IF(`p`.`canBeSupervisor` = 1 AND `p`.`isManager` = 0 AND `p`.`isAdministrator` = 0, s.userId, NULL)),
			COUNT(IF(`p`.`canBeModerator` = 1 AND `p`.`canBeSupervisor` = 0 AND `p`.`isManager` = 0 AND`p`.`isAdministrator` = 0 AND m.userId IS NOT NULL, s.userId, NULL)),
			COUNT(IF(`p`.`canBeSupervisor` = 0 AND `p`.`isManager` = 0 AND `p`.`isAdministrator` = 0  AND m.userId IS NULL, s.userId, NULL))
			FROM UserToSubject AS s
			JOIN User AS u ON u.userId = s.userId
			JOIN Permissions AS p ON p.userId = u.userId
			LEFT JOIN ModeratorToSubject AS m ON m.userId = u.userId AND m.moderator = 1 AND m.approved = 1
			WHERE u.includeInStats = 1
			GROUP BY s.subjectId";
		$this->db->query($forumSql);
	}
	
	/**
	 * Pulls issues from external reporting system that are resolved, but have yet to be marked as such
	 * in the ramblur system. Marks found issues as resolved, and notifies reporter.
	 */
	function checkForResolvedErrorReports()	{
		$this->load->model('jira_issue', 'issue');
		$unmarkedIssues = $this->issue->getUnmarkedResolvedIssues();
		if($unmarkedIssues) {
			$keys = array();
			$issuesByKey = array();
			foreach($unmarkedIssues as $i) {
				$k = $i["key"];
				$keys[] = $k;
				$issuesByKey[$k] = $i;
			}
			$issueKeysToUpdate = array_map(function($ele) { return $ele["key"]; }, $unmarkedIssues);
			$issueKeysAsInString = '("' . implode('","', $issueKeysToUpdate) . '")';
			$this->db->update('UserToIssue', array('resolved' => 1), 'issueId IN ' . $issueKeysAsInString);
			$this->db->select('userId, issueId')
				->from('UserToIssue')
				->where('issueId IN ' . $issueKeysAsInString);
			$usersToMsg = $this->db->get()->result_array();
			$this->load->model('message');
			$this->load->model('group');
			$to = array();
			foreach($usersToMsg as $row) {
				$to[] = $row['userId'];
			}
			// @todo - system messages need to be able to be sent from the system, ie. from no specific user
			$this->message->systemMessage(1, $to, 1, message::MSG_ISSUE_RESOLVED, array('reportSummary' => $issuesByKey[$row["issueId"]]["summary"]));
		
		}
	}
	
	function recordReportedNodeStats() {
		// Record number of unactioned moderator reports per forum
		$this->load->model('reported_content');
		$this->load->model('statistics');
		
		$this->db->query($this->getUnactionedQuery('moderator'));
		$this->db->query($this->getUnactionedQuery('supervisor'));
		
		$modActionedId = $this->statistics->getStatisticTypeId('mod_actioned');
		$superActionedId = $this->statistics->getStatisticTypeId('super_actioned');
		
		// Record how many nodes/100 users in the subject were actioned by moderators today
		$modActionedSql = "
			INSERT INTO `ReportedContentStatistics` (`typeId`, `date`, `subjectId`, `count`)
			SELECT
			$modActionedId,
			UTC_DATE(),
			`sub`.`subjectId`,
			COUNT(`r`.`id`) / COUNT(DISTINCT `s`.`userId`) * 100
			FROM `ReportedContentReviewsToUser` AS `r`
			JOIN `Moderator` AS `m` ON `m`.`userId` = `r`.`reviewerId`
			LEFT JOIN `ReportedNodes` AS `rn` ON `rn`.`reportedContentId` = `r`.`reportedContentId`
			LEFT JOIN `Nodes` AS `n` ON `n`.`nodeId` = `rn`.`nodeId`
			LEFT JOIN `Post` AS `p` ON `p`.`postId` = `n`.`postId`
			LEFT JOIN `ReportedFiles` AS `rf` ON `rf`.`reportedContentId` = `r`.`reportedContentId`
			LEFT JOIN `Content` AS `f` ON `f`.`fileName` = `rf`.`fileName`
			JOIN `Subject` AS `sub` ON `sub`.`subjectId` = `p`.`subjectId` OR `sub`.`subjectId` = `f`.`subjectId`
			JOIN `UserToSubject` AS `s` ON `s`.`subjectId` = `sub`.`subjectId`
			WHERE DATE(`r`.`reviewDate`) = UTC_DATE()
			GROUP BY `sub`.`subjectId`
		";
		$this->db->query($modActionedSql);
		
		// Record how many nodes/100 users in the subject were actioned by supervisors today
		$superActionedSql = "
			INSERT INTO `ReportedContentStatistics` (`typeId`, `date`, `subjectId`, `count`)
			SELECT
			$superActionedId,
			UTC_DATE(),
			`sub`.`subjectId`,
			COUNT(`r`.`id`) / COUNT(DISTINCT `s`.`userId`) * 100
			FROM `ReportedContentReviewsToUser` AS `r`
			JOIN `SupervisorToSubject` AS `ss` ON `ss`.`userId` = `r`.`reviewerId`
			LEFT JOIN `ReportedNodes` AS `rn` ON `rn`.`reportedContentId` = `r`.`reportedContentId`
			LEFT JOIN `Nodes` AS `n` ON `n`.`nodeId` = `rn`.`nodeId`
			LEFT JOIN `Post` AS `p` ON `p`.`postId` = `n`.`postId`
			LEFT JOIN `ReportedFiles` AS `rf` ON `rf`.`reportedContentId` = `r`.`reportedContentId`
			LEFT JOIN `Content` AS `f` ON `f`.`fileName` = `rf`.`fileName`
			JOIN `Subject` AS `sub` ON `sub`.`subjectId` = `p`.`subjectId` OR `sub`.`subjectId` = `f`.`subjectId`
			JOIN `UserToSubject` AS `s` ON `s`.`subjectId` = `sub`.`subjectId`
			WHERE DATE(`r`.`reviewDate`) = UTC_DATE()
			GROUP BY `sub`.`subjectId`
		";
		$this->db->query($superActionedSql);
		
		// Delete records older than 30 days
		$this->db->where('`date` < DATE_SUB(UTC_DATE(), INTERVAL 30 DAY)', NULL, FALSE);
		$this->db->delete('ReportedContentStatistics');
		
		// if todays reports are >2SD above average of last 30 days, notify manager
		$this->db->select("s.subjectId, ANY_VALUE(c.count) AS 'today', AVG(s.count) AS 'average', STDDEV(s.count) AS 'standardDeviation'", FALSE)
			->from('ReportedContentStatistics AS s')
			->join('StatisticTypes AS t', 't.id = s.typeId')
			->join("(SELECT s.subjectId, s.count
					FROM ReportedContentStatistics s
					JOIN StatisticTypes AS t on t.id = s.typeId
					WHERE s.date = UTC_DATE()
					AND t.`type` = 'mod_unactioned') AS c", 'c.subjectId = s.subjectId', 'INNER', FALSE)
			->where('t.type', 'mod_unactioned')
			->where('s.date >= DATE_SUB(UTC_DATE(), INTERVAL 30 DAY)', null, FALSE)
			->where('s.date !=', 'UTC_DATE()', FALSE)
			->group_by('s.subjectId');
		$monthAverages = $this->db->get();
		if($monthAverages->num_rows() > 0) {
			$monthAverages = $monthAverages->result_array();
			foreach($monthAverages AS $m) {
				if($m['today'] > $m['average'] + 2 * $m['standardDeviation']) {
					// notify manager
					$this->load->model(array('message', 'user', 'group'));
					$managers = $this->user->findManagers();
					foreach($managers as $manager) {
						$this->message->systemMessage(1, $manager['userId'], $m['subjectId'], message::MSG_MANAGER_SPIKE);
					}
				}
			}
		}
	}
	
	private function getUnactionedQuery($userType) {
		// Find how many nodes/100 users in the forum were reported today that the user type provided needs to action
		$unactionedId = $this->statistics->getStatisticTypeId(($userType == 'moderator' ? 'mod' : 'super') . "_unactioned");
		return "
			INSERT INTO `ReportedContentStatistics` (`typeId`, `date`, `subjectId`, `count`)
			SELECT
			$unactionedId,
			UTC_DATE(),
			`q`.`subjectId`,
			COUNT(IF(`q`.`currentReviewId` IS NOT NULL, `q`.`id`,
				IF((`q`.`lastReviewPercentage` IS NULL AND (`q`.`currentPercentage` >= 6 OR `q`.`abuseCount` > 0 OR `q`.`moderatorReportedCount` > 0))
					OR (`q`.`lastReviewPercentage` < 10 AND `q`.`currentPercentage` >= 10)
					OR (`q`.`lastReviewPercentage` < 20 AND `q`.`currentPercentage` >= 20)
					OR (`q`.`lastReviewPercentage` < 30 AND `q`.`currentPercentage` >= 30)
					OR (`q`.`lastReviewPercentage` < 40 AND `q`.`currentPercentage` >= 40)
					OR (`q`.`lastReviewPercentage` < 50 AND `q`.`currentPercentage` >= 50)
					OR (`q`.`lastReviewPercentage` < 60 AND `q`.`currentPercentage` >= 60)
					OR (`q`.`lastReviewPercentage` < 70 AND `q`.`currentPercentage` >= 70)
					OR (`q`.`lastReviewPercentage` < 80 AND `q`.`currentPercentage` >= 80)
					OR (`q`.`lastReviewPercentage` < 90 AND `q`.`currentPercentage` >= 90)
					OR (`q`.`lastReviewPercentage` < 100 AND `q`.`currentPercentage` >= 100),
				`q`.`id`, NULL
				)
			)) / ANY_VALUE(`q`.`userCount`) * 100 AS 'count'
			FROM (
				SELECT
				ANY_VALUE(`sub`.`subjectId`) AS 'subjectId',
				`r`.`id`,
				`c`.`id` AS 'currentReviewId',
				IF(`authorPer`.`canBeModerator` AND ANY_VALUE(`authorModSub`.`userId`) IS NOT NULL, 1, 0) AS 'authorIsModerator',
				IF(`authorPer`.`canBeSupervisor` AND ANY_VALUE(`authorSuperSub`.`userId`) IS NOT NULL, 1, 0) AS 'authorIsSupervisor',
				ROUND(COUNT(DISTINCT `u`.`reportingUserId`) / COUNT(DISTINCT `s`.`userId`) * 100, 0) AS 'currentPercentage',
				COUNT(DISTINCT CASE WHEN `u`.`reason` = 'EXTREME' THEN `u`.`reportingUserId` END) AS 'extremeCount',
				COUNT(DISTINCT CASE WHEN `u`.`reason` = 'COMPLAINT' THEN `u`.`reportingUserId` END) AS 'complaintCount',
				COUNT(DISTINCT IF(`rc`.`resolvedReviewId` IS NULL, `rc`.`id`, NULL)) AS 'activeComplaintCount',
				COUNT(DISTINCT CASE WHEN `u`.`reason` = 'ABUSE' THEN `u`.`reportingUserId` END) AS 'abuseCount',
				COUNT(DISTINCT `s`.`userId`) AS 'userCount',
				`v`.`latestPctUsersReported` AS 'lastReviewPercentage',
				`v`.`latestReviewDate` AS 'lastReviewDate',
				# @todo - include manager privileges, possibly removing admin from here
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
				# Make sure only counts subjects current user is a moderator of
				JOIN `ModeratorToSubject` AS `ms` ON `ms`.`subjectId` = `sub`.`subjectId` AND `ms`.`moderator` = 1 AND `ms`.`approved` = 1
				JOIN `UserToSubject` AS `s` ON `s`.`subjectId` = `sub`.`subjectId`
				JOIN `Permissions` AS `per` ON `per`.`userId` = `u`.`reportingUserId`
				# Track reports by any moderator so it's included directly
				LEFT JOIN `ModeratorToSubject` AS `ms2` ON `ms2`.`userId` = `u`.`reportingUserId` AND `ms2`.`subjectId` = `sub`.`subjectId` AND `ms2`.`moderator` = 1 AND `ms2`.`approved` = 1
				LEFT JOIN (
					SELECT `a`.*
					FROM `ReportedContentReviews` AS `a`
					LEFT JOIN `ReportedContentReviews` AS `b` ON `a`.`reportedContentId` = `b`.`reportedContentId` AND `a`.`latestReviewDate` < `b`.`latestReviewDate`
					WHERE `b`.`id` IS NULL
					) AS `v` ON `v`.`reportedContentId` = `r`.`id`
				LEFT JOIN `ReportedContentReviews` AS `c` ON `c`.`reportedContentId` = `r`.`id` AND (`c`.`latestUserBan` IS NULL OR `c`.`latestUserWarning` IS NULL OR `c`.`latestPostDeleted` IS NULL OR `c`.`latestPostEdited` IS NULL)
				# Exclude active complaints
				LEFT JOIN `ReportComplaints` AS `rc` ON `rc`.`reportedContentId` = `r`.`id`
				GROUP BY `r`.`id`, `c`.`id`, `v`.`id`
				HAVING " . $this->reported_content->getReportsHavingClause($userType, true) . "
				) AS `q`
			GROUP BY `q`.`subjectId`
		";
	}

	 /*
	 * Used to update the post timestamps to +24 hours when they are 24 hours old
	 * i.e. they will run each day at the owner's chosen post time until expiry
	 * (which is currently 12 months). 
	 * Note may change the 24 hour period to ref data is it is likely to be 
	 * changed often
	 * 
	 * @todo - use timezones  
	 */

	function updatePostTimestamps() {

		$this->load->model('ad');

		$current_date = getdate(time());
		$post_ads = $this->ad->getPostAds();
		// mn added test of array $post_ads
		if(isset($post_ads)){
			foreach ($post_ads as $ad) {
				if (date("Y-m-d H:i:s") >= date("Y-m-d H:i:s", strtotime($ad['timeStamp'] . ' +24 HOURS'))) {
					$scheduled_time = getdate(strtotime($ad['timeStamp']));
					$this->ad->setPostTimestamp($ad['postId'], date("r", mktime($scheduled_time["hours"], $scheduled_time["minutes"], 0, $current_date["mon"], $current_date["mday"], $current_date["year"])));
				}
			}
		}
	}

	/* 
	 * Should run once a day.
	 * 
	 * Checks if any users have subscribed to an event which is scheduled
	 * in the next 24 hours, and if so, sends them a reminder message.
	 * 
	 * @todo Add subjectId to userToSubjectMsg table for possible future
	 * use then database is distributed by subjects (forum groups).
	 */

	function checkEvents() {

		$this->load->model('post');

		$events = $this->post->getEvents();

		if (!empty($events)) {
			foreach ($events as $event) {

				/*
				 * if there is at least one subscriber to the event which is sheduled in the
				 * next 24 hours, create an event reminder message. Only one message will be created
				 * in any 24 hour period, and will be sent to all subscribers once only.
				 * 
				 */
				if ($subscribers = $this->post->anySubscribers($event->postId, $event->userId, 'event')) {
					$subscription_params = array($event->userId, $subscribers[0]['subjectName'], $event->categoryId, 'Event Reminder', 'You have subscribed to an event which is scheduled to begin on '. date('F j, Y, g:i a', strtotime($event->startTime)), 'subscription_msg', $event->author, $event->aboutAuthor, time(), time(), '', '', null);
					$subscription = $this->post->addPost($subscription_params);
					
					/*
					 * need to update userToSubscriptionMsg here with the subscribed user id and the data[post]
					 * if they have not already been sent a reminder.
					 * 
					 */
					foreach ($subscribers as $subscriber) {
						if (!$this->post->eventReminderSent($subscriber['userId'], $event->postId)) {
							$this->post->updateUserToSubscriptionMsg($subscription[0]['newPostId'], $subscriber['userId'], $event->postId, 1);
						}
					}
				}
			}
		}
	}
	
	/*
	 * 
	 * The manual version of this function is in the settings controller
	 * 
	 * This function Should be scheduled to run after the target month end. Recommend the
	 * 2nd of each month to allow for different time zones to complete.
	 * 
	 */
	
	public function calculateMonthlyDistributedPayments() {

		$this->load->model('adadminmodel');
		
		if (!$this->adadminmodel->automatedDistributedPayments()) {
			return;
		}
		
		$month = date('F', strtotime(date('F') . " last month"));
		$year = date("Y");
		if ($this->adadminmodel->paymentsNotAlreadyCalculated($month, $year, 0)) {
			$this->db->trans_start();
			$this->adadminmodel->setProcessLock('currentlyCalculating', 1);
			$moderatorsForMonth = $data["moderatorsForMonth"] = $this->adadminmodel->getModeratorsForMonth($month, $year);
			$this->adadminmodel->calculateMonthlyDistributedPayments($month, $year, $moderatorsForMonth);
			//if ($this->adadminmodel->distributedPaymentsRequireAuth()) { //turned off initially
			$this->adadminmodel->sendPaymentAuthMessage($month, $year);
			$this->adadminmodel->setProcessLock('currentlyCalculating', 0);
			$this->db->trans_complete();
			//}
		} else {
			log_message('error', "cronjob calculateMonthlyDistributedPayments() - The Distributed Moderator Payments have previously been calculated for $month $year");
		}
	}
	
	/*
	 * 
	 * Should run daily, but will only usually pick up payments
	 * after the 2nd of the month once they have been authorized.
	 * 
	 */
	
	function processMonthlyDistributedPayments() {

		$this->load->model('paypalmodel');
		$this->load->model('adadminmodel');
		
		if (!$this->adadminmodel->automatedDistributedPayments()) {
			return;
		}
		
		if ($this->adadminmodel->getProcessLock('currentlyCalculating') && $this->adadminmodel->getProcessLock('currentlyAuthorizing')) {
			log_message('error', 'cronjob processMonthlyDistributedPayments() -  sending locked');
			return;
		} else {
				$this->db->trans_start();
				$this->adadminmodel->setProcessLock('currentlySending',1);
		}

		/*
		 * Possible values of the ACK response field are the following:
		 *
		 * Success — The request was successful.
		 * SuccessWithWarning — The request was successful but a warning error code was also returned.
		 * Failure — The API request failed. See error messages for details.
		 * FailureWithWarning — The API request failed and additional warning messages were returned.
		 * 
		 */

		$this->db->select('distributedPayments.receiver_email')
			->select('distributedPayments.paymentAmount')
			->select('distributedPayments.paymentId')
			->select('distributedPayments.month')
			->select('distributedPayments.year');
		$this->db->from('distributedPayments');
		$this->db->where('paid', 0)
			->where('authorized', 1);

		$query = $this->db->get();
		$monthlyPayments = $query->result_array();

		if ($query->num_rows() > 0) {
			$nvpSetupStr = "&RECEIVERTYPE=EmailAddress&CURRENCYCODE="
					. urlencode($this->config->item('PAYPAL_CUR_CODE'))
					. "&EMAILSUBJECT=Moderator Payment From R..";

			$nvpPaymentsStr = NULL;
			$i = 0;
			$outstanding = FALSE;
			foreach ($monthlyPayments as $monthlyPayment) {
				$outstanding = TRUE;
				$this->adadminmodel->insertPaymentsBeingProcessed($monthlyPayment['paymentId']);

				$nvpPaymentsStr .= "&L_EMAIL" . $i . "=" . $monthlyPayment['receiver_email'] .
						"&L_AMT" . $i . "=" . $monthlyPayment['paymentAmount'] .
						"&L_UNIQUEID" . $i . "=" . $monthlyPayment['paymentId'];

				if ($i === 249) { // only allowed to send 250 MassPay payments to PayPal at any one time  
					$massPayResponse = $this->paypalmodel->sendPayPalTransaction('MassPay', $nvpSetupStr . $nvpPaymentsStr);
					$this->adadminmodel->setFinalStatus($massPayResponse["ACK"], $massPayResponse["CORRELATIONID"]);
					$this->paypalmodel->logPaypalMassPayResponse($massPayResponse);
					$outstanding = FALSE;
					$nvpPaymentsStr = NULL;
					$i = -1;
				}

				$i++;
			}
			if ($outstanding) {
				$massPayResponse = $this->paypalmodel->sendPayPalTransaction('MassPay', $nvpSetupStr . $nvpPaymentsStr);
				$this->adadminmodel->setFinalStatus($massPayResponse["ACK"], $massPayResponse["CORRELATIONID"]);
				$this->paypalmodel->logPaypalMassPayResponse($massPayResponse);
			}

			$this->adadminmodel->setProcessLock('currentlySending',0);
			$this->db->trans_complete();
			return ($monthlyPayments);
		} else {
			$this->adadminmodel->setProcessLock('currentlySending',0);
			$this->db->trans_complete();
			return (NULL);
		}
	}
	
	function removeUnrelatedContent() {
		$this->load->model('file');
		$this->db->select('fileName')
			->from('Content')
			->where('subjectId', null)
			->where('timeStamp < DATE_SUB(NOW(), INTERVAL 6 HOUR)', null, false);
		$oldFiles = $this->db->get()->result_array();
		
		foreach($oldFiles as $f) {
			$this->file->removeFiles($f['fileName'], false);
			
		}
 		$this->db->where('subjectId', null)
			->where('timeStamp < DATE_SUB(NOW(), INTERVAL 6 HOUR)')
			->delete('Content');
	}
	
	function emailCheck() {
		$this->db->set('hasRun',0)
				->set('batchJobStatusId', 7)
				->set('runTime',Null)
				->where('id',356)
				->update('BatchJobs');
		
		$this->db->where('batchJobId', 356)
			->delete('BatchJobLogs');
		
		$this->db->where('batchJobId', 356)
			->delete('BatchRunToBatchJobs');
		
	}
}