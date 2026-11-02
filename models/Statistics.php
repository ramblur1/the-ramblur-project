<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Statistics extends CI_Model {

	function __construct() {
		// Call the Model constructor
		parent::__construct();
	}
	
	function getStatisticTypeId($statisticType) {
		$this->db->select('id')
			->from('StatisticTypes')
			->where('type', $statisticType);
		$type = $this->db->get();
		if($type->num_rows() > 0) {
			$typeId = $type->first_row('array')['id'];
		} else {
			$this->db->insert('StatisticTypes', array('type' => 'logins'));
			$typeId = $this->db->insert_id();
		}
		return $typeId;
	}

	/**
	 * Retrieves all statistics for the admin statistics page. 
	 * @return array
	 */
	function all() {

		$stats = array();
		
		// find out the total number of subjects
		$stats["Subjects"] = $this->db->select('subjectId')
			->from('Subject')
			->count_all_results();
		
		// find out the total number of adverts
		$stats["Ads"] = $this->db->select('adId')
			->from('Advert')
			->count_all_results();
		
		// find out the total number of users
		$stats["Users"] = $this->db->select('userId')
			->from('User')
			->count_all_results();

		// find out how many users are logged in atm
		$stats["Users: logged in"] = $this->db->select('userId')
			->from('User')
			->where('session !=', '')
			->count_all_results();

		// find out the total number of nodes
		$stats["Nodes"] = $this->db->select('nodeId')
			->from('Nodes')
			->count_all_results();
			
		// find out the total number of nodes by type
		$this->db->select("nodeType, COUNT(nodeId) as 'node_count'")
			->from('Nodes')
			->group_by('nodeType');
		$query = $this->db->get();
		
		if ( $query->num_rows() > 0 ) {

			foreach($query->result_array() as $row) {
				$stats["Nodes: " . $row["nodeType"]] = $row['node_count'];
			}
		}
		ksort($stats);
		return $stats;
	}
	
	function getForumPostsStatistics($year = NULL, $month = NULL) {
		$this->db->select("`s`.`subjectName`,
				# Use DISTINCT to remove duplicate rows for posts with > 1 related Content row
				COUNT(DISTINCT IF(`n`.`parentNodeId` = 0, `n`.`nodeId`, NULL)) AS 'threads',
				COUNT(DISTINCT IF(`p`.`postType` = 'post' AND `n`.`parentNodeId` = 0, `n`.`nodeId`, NULL)) AS 'posts',
				COUNT(DISTINCT IF(`p`.`postType` = 'question' AND `n`.`parentNodeId` = 0, `n`.`nodeId`, NULL)) AS 'questions',
				COUNT(DISTINCT IF(`p`.`postType` = 'article' AND `n`.`parentNodeId` = 0, `n`.`nodeId`, NULL)) AS 'articles',
				COUNT(DISTINCT IF(`p`.`postType` = 'blog' AND `n`.`parentNodeId` = 0, `n`.`nodeId`, NULL)) AS 'blogs',
				COUNT(DISTINCT IF(`p`.`postType` = 'review' AND `n`.`parentNodeId` = 0,`n`.`nodeId`, NULL)) AS 'reviews',
				COUNT(DISTINCT n.nodeId) AS 'nodes',
				ROUND(COUNT(DISTINCT n.nodeId)/COUNT(DISTINCT IF(`n`.`parentNodeId` = 0, `n`.`nodeId`, NULL)), 1) AS 'aveNodesPerThread',
				COUNT(IF(`c`.`fileType` = 'video', 1, NULL)) AS 'videos',
				COUNT(IF(`c`.`fileType` = 'photo' OR c.fileType = 'jpg' OR c.fileType = 'png', 1, NULL)) AS 'photos',
				COUNT(IF(`c`.`fileType` = 'drawing', 1, NULL)) AS 'drawings',
				COUNT(IF(`c`.`fileType` = 'sound', 1, NULL)) AS 'sounds',
				COUNT(IF(`c`.`fileType` = 'unknown', 1, NULL)) AS 'unknown',
				COUNT(IF(rn.nodeId IS NOT NULL, 1, NULL)) AS 'reports'
				", FALSE)
			->from('Subject AS s')
			->join('Post AS p', 'p.subjectId = s.subjectId', 'left')
			->join('Nodes AS n', 'n.postId = p.postId', 'left')
			->join('ContentToNodes AS cn', 'cn.nodeID = n.nodeId', 'left')
			->join('Content AS c', 'c.fileName = cn.fileName', 'left')
			// @todo - Date of node creation looked at, rather than date of report. Change?
			->join('ReportedNodes AS rn', 'rn.nodeId = n.nodeId', 'left')
			// Don't include message or chat posts
			->where('`p`.`postType` != ', 'message')
			->where('`p`.`postType` != ', 'chat')
			->group_by('s.subjectId')
			->order_by('COUNT(DISTINCT n.nodeId)', 'desc');
		// Year is optional
		if(!is_null($year)) {
			$this->db->where('YEAR(`p`.`timestamp`)', $year);
			// Month is optional, but only included if year also set
			if(!is_null($month)) {
				$this->db->where('MONTH(p.timestamp) =', $month);
			}
		}
		// Add stats for publicly created versus ramblur created forums
		$results = $this->db->get()->result_array();
		$fields = array(
			'subjectName' => array('label' => 'Forum', 'total' => null),
			'threads' => array('label' => 'Threads', 'total' => 0),
			'posts' => array('label' => 'Posts', 'total' => 0),
			'questions' => array('label' => 'Questions', 'total' => 0),
			'articles' => array('label' => 'Articles', 'total' => 0),
			'blogs' => array('label' => 'Blogs', 'total' => 0),
			'reviews' => array('label' => 'Reviews', 'total' => 0),
			'nodes' => array('label' => 'Total Nodes', 'total' => 0),
			'aveNodesPerThread' => array('label' => 'Average Nodes Per Thread', 'total' => array()),
			'videos' => array('label' => 'Videos', 'total' => 0),
			'photos' => array('label' => 'Photos', 'total' => 0),
			'drawings' => array('label' => 'Drawings', 'total' => 0),
			'sounds' => array('label' => 'Sounds', 'total' => 0),
			'unknown' => array('label' => 'Unknown File Types', 'total' => 0),
			'reports' => array('label' => 'Abuse Reports', 'total' => 0),
		);
		
		return $this->processStatisticResults($results, $fields);
	}
	
	function getForumMembersStatistics($year = NULL, $month = NULL) {
		
		// for these stats, all time stats will reflect the current membership numbers
		if (is_null($year)) {
			$sql = "
				SELECT
				sub.subjectName,
				COUNT(s.userId) AS 'total',
				COUNT(IF(`u`.`gender` = 'M', s.userId, NULL)) AS 'males',
				COUNT(IF(`u`.`gender` = 'F', s.userId, NULL)) AS 'females',
				COUNT(IF(`u`.`DOB` BETWEEN DATE_SUB(NOW(),INTERVAL 18 YEAR) AND DATE_SUB(NOW(),INTERVAL 12 YEAR), s.userId, NULL))  AS '12',
				COUNT(IF(`u`.`DOB` BETWEEN DATE_SUB(NOW(),INTERVAL 28 YEAR) AND DATE_SUB(NOW(),INTERVAL 19 YEAR), s.userId, NULL))  AS '19',
				COUNT(IF(`u`.`DOB` BETWEEN DATE_SUB(NOW(),INTERVAL 35 YEAR) AND DATE_SUB(NOW(),INTERVAL 29 YEAR), s.userId, NULL))  AS '29',
				COUNT(IF(`u`.`DOB` BETWEEN DATE_SUB(NOW(),INTERVAL 45 YEAR) AND DATE_SUB(NOW(),INTERVAL 36 YEAR), s.userId, NULL)) AS '36',
				COUNT(IF(`u`.`DOB` BETWEEN DATE_SUB(NOW(),INTERVAL 55 YEAR) AND DATE_SUB(NOW(),INTERVAL 46 YEAR), s.userId, NULL)) AS '46',
				COUNT(IF(`u`.`DOB` > DATE_SUB(NOW(),INTERVAL 56 YEAR), s.userId, NULL)) AS '56',
				COUNT(IF(`p`.`isAdministrator` = 1, s.userId, NULL)) AS 'admin',
				COUNT(IF(`p`.`isManager` = 1 AND `p`.`isAdministrator` = 0, s.userId, NULL)) AS 'manager',
				COUNT(IF(`p`.`canBeSupervisor` = 1 AND `p`.`isManager` = 0 AND `p`.`isAdministrator` = 0, s.userId, NULL))  AS 'super',
				COUNT(IF(`p`.`canBeModerator` = 1 AND `p`.`canBeSupervisor` = 0 AND `p`.`isManager` = 0 AND`p`.`isAdministrator` = 0 AND m.userId IS NOT NULL, s.userId, NULL)) AS 'mod',
				COUNT(IF(`p`.`canBeSupervisor` = 0 AND `p`.`isManager` = 0 AND `p`.`isAdministrator` = 0  AND m.userId IS NULL, s.userId, NULL)) AS 'standard'
				FROM UserToSubject AS s
				JOIN User AS u ON u.userId = s.userId
				JOIN Permissions AS p ON p.userId = u.userId
				LEFT JOIN ModeratorToSubject AS m ON m.userId = u.userId AND m.moderator = 1 AND m.approved = 1
				JOIN Subject AS sub ON sub.subjectId = s.subjectId
				WHERE u.includeInStats = 1
				GROUP BY s.subjectId
			";
			$results = $this->db->query($sql)->result_array();
		} else {
			$this->db->select("s.subjectName,
				SUM(m.males) AS 'males',
				SUM(m.females) AS 'females',
				SUM(m.total) AS 'total',
				SUM(m.administrators) AS 'admin',
				SUM(m.managers) AS 'manager',
				SUM(m.supervisors) AS 'super',
				SUM(m.moderators) AS 'mod',
				SUM(m.standard) AS 'standard',
				SUM(m.`12-18`) AS '12',
				SUM(m.`19-28`) AS '19',
				SUM(m.`29-36`) AS '29',
				SUM(m.`36-45`) AS '36',
				SUM(m.`46-55`) AS '46',
				SUM(m.`56+`) AS '56'")
			->from('MonthlyStatistics AS m')
			->join('Subject AS s', 's.subjectId = m.subjectId')
			->where("m.typeId = (SELECT `id` FROM `StatisticTypes` WHERE `type` = 'forum_members')", NULL, FALSE)
			->group_by('m.subjectId')
			->where('m.year', $year);
			
			// Month is optional
			if(!is_null($month)) {
				$this->db->where('m.month', $month);
			}
			$results = $this->db->get()->result_array();
		}
		
		// Add stats for publicly created versus ramblur created forums
		$fields = array(
			'subjectName' => array('label' => 'Forum', 'total' => null),
			'males' => array('label' => 'Male Members', 'total' => 0),
			'females' => array('label' => 'Female Members', 'total' => 0),
			'total' => array('label' => 'Total Members', 'total' => 0),
			'admin' => array('label' => 'Administrators', 'total' => 0),
			'manager' => array('label' => 'Managers', 'total' => 0),
			'super' => array('label' => 'Supervisors', 'total' => 0),
			'mod' => array('label' => 'Moderators', 'total' => 0),
			'standard' => array('label' => 'Standard Users', 'total' => 0),
			'12' => array('label' => 'Age Group - 12 to 18', 'total' => 0),
			'19' => array('label' => 'Age Group - 19 to 28', 'total' => 0),
			'29' => array('label' => 'Age Group - 29 to 35', 'total' => 0),
			'36' => array('label' => 'Age Group - 36 to 45', 'total' => 0),
			'46' => array('label' => 'Age Group - 46 to 55', 'total' => 0),
			'56' => array('label' => 'Age Group - 56+', 'total' => 0)
		);
	
		return $this->processStatisticResults($results, $fields);
	}
	
	function getMemberCountsStatistics($year = NULL, $month = NULL) {
		$this->db->select("`c`.`name` AS 'country',
				COUNT(IF(`u`.`gender` = 'M', 1, NULL)) AS 'males',
				COUNT(IF(`u`.`gender` = 'F', 1, NULL)) AS 'females',
				COUNT(`u`.`userId`) AS 'total',
				COUNT(IF(`p`.`isAdministrator` = 1, 1, NULL)) AS 'admin',
				COUNT(IF(`p`.`isManager` = 1 AND `p`.`isAdministrator` = 0, 1, NULL)) AS 'manager',
				COUNT(IF(`p`.`canBeSupervisor` = 1 AND `p`.`isManager` = 0 AND `p`.`isAdministrator` = 0, 1, NULL)) AS 'super',
				COUNT(IF(`p`.`canBeModerator` = 1 AND `p`.`canBeSupervisor` = 0 AND `p`.`isManager` = 0 AND`p`.`isAdministrator` = 0, 1, NULL)) AS 'mod',
				COUNT(IF(`p`.`canBeModerator` = 0 AND `p`.`canBeSupervisor` = 0 AND `p`.`isManager` = 0 AND`p`.`isAdministrator` = 0, 1, NULL)) AS 'standard',
				COUNT(IF(`u`.`DOB` BETWEEN DATE_SUB(NOW(),INTERVAL 18 YEAR) AND DATE_SUB(NOW(),INTERVAL 12 YEAR), 1, NULL)) AS '12',
				COUNT(IF(`u`.`DOB` BETWEEN DATE_SUB(NOW(),INTERVAL 28 YEAR) AND DATE_SUB(NOW(),INTERVAL 19 YEAR), 1, NULL)) AS '19',
				COUNT(IF(`u`.`DOB` BETWEEN DATE_SUB(NOW(),INTERVAL 35 YEAR) AND DATE_SUB(NOW(),INTERVAL 29 YEAR), 1, NULL)) AS '29',
				COUNT(IF(`u`.`DOB` BETWEEN DATE_SUB(NOW(),INTERVAL 45 YEAR) AND DATE_SUB(NOW(),INTERVAL 36 YEAR), 1, NULL)) AS '36',
				COUNT(IF(`u`.`DOB` BETWEEN DATE_SUB(NOW(),INTERVAL 55 YEAR) AND DATE_SUB(NOW(),INTERVAL 46 YEAR), 1, NULL)) AS '46',
				COUNT(IF(`u`.`DOB` > DATE_SUB(NOW(),INTERVAL 56 YEAR), 1, NULL)) AS '56'", FALSE)
			->from('User AS u')
			->join('Country AS c', 'c.countryId = u.countryId')
			->join('Permissions AS p', 'p.userId = u.userId')
			->group_by('c.countryId')
			->order_by('COUNT(u.userId) ', 'desc');
		// Year is optional
		if(!is_null($year)) {
			// Month is optional, but only included if year is also set
			if(is_null($month)) {
				$this->db->where('YEAR(u.joinDate) <=', $year);
			} else {
				$this->db->where("DATE(u.joinDate) <= LAST_DAY('$year-$month-01')", NULL, FALSE);
			}
		}
		$results = $this->db->get()->result_array();
		
		$fields = array(
			'country' => array('label' => 'Country', 'total' => null),
			'males' => array('label' => 'Males', 'total' => 0),
			'females' => array('label' => 'Females', 'total' => 0),
			'total' => array('label' => 'Total', 'total' => 0),
			'admin' => array('label' => 'Administrators', 'total' => 0),
			'manager' => array('label' => 'Managers', 'total' => 0),
			'super' => array('label' => 'Supervisors', 'total' => 0),
			'mod' => array('label' => 'Moderators', 'total' => 0),
			'standard' => array('label' => 'Standard Users', 'total' => 0),
			'12' => array('label' => 'Age Group - 12 to 18', 'total' => 0),
			'19' => array('label' => 'Age Group - 19 to 28', 'total' => 0),
			'29' => array('label' => 'Age Group - 29 to 35', 'total' => 0),
			'36' => array('label' => 'Age Group - 36 to 45', 'total' => 0),
			'46' => array('label' => 'Age Group - 46 to 55', 'total' => 0),
			'56' => array('label' => 'Age Group - 56+', 'total' => 0)
		);
		return $this->processStatisticResults($results, $fields);
	}
	
	function getMemberLoginsStatistics($year = NULL, $month = NULL) {
		$this->db->select("c.name AS 'country',
				SUM(m.males) AS 'males',
				SUM(m.females) AS 'females',
				SUM(m.total) AS 'total',
				SUM(m.administrators) AS 'admin',
				SUM(m.managers) AS 'manager',
				SUM(m.supervisors) AS 'super',
				SUM(m.moderators) AS 'mod',
				SUM(m.standard) AS 'standard',
				SUM(m.`12-18`) AS '12',
				SUM(m.`19-28`) AS '19',
				SUM(m.`29-36`) AS '29',
				SUM(m.`36-45`) AS '36',
				SUM(m.`46-55`) AS '46',
				SUM(m.`56+`) AS '56'")
			->from('MonthlyStatistics AS m')
			->join('Country AS c', 'c.countryId = m.countryId')
			->where("m.typeId = (SELECT `id` FROM `StatisticTypes` WHERE `type` = 'logins')", NULL, FALSE)
			->group_by('m.countryId');
		// Year is optional
		if(!is_null($year)) {
			$this->db->where('m.year', $year);
			// Month is optional but only included if year also set
			if(!is_null($month)) {
				$this->db->where('m.month', $month);
			}
		}

		$results = $this->db->get()->result_array();

		$fields = array(
			'country' => array('label' => 'Country', 'total' => null),
			'males' => array('label' => 'Males', 'total' => 0),
			'females' => array('label' => 'Females', 'total' => 0),
			'total' => array('label' => 'Total', 'total' => 0),
			'admin' => array('label' => 'Administrators', 'total' => 0),
			'manager' => array('label' => 'Managers', 'total' => 0),
			'super' => array('label' => 'Supervisors', 'total' => 0),
			'mod' => array('label' => 'Moderators', 'total' => 0),
			'standard' => array('label' => 'Standard Users', 'total' => 0),
			'12' => array('label' => 'Age Group - 12 to 18', 'total' => 0),
			'19' => array('label' => 'Age Group - 19 to 28', 'total' => 0),
			'29' => array('label' => 'Age Group - 29 to 35', 'total' => 0),
			'36' => array('label' => 'Age Group - 36 to 45', 'total' => 0),
			'46' => array('label' => 'Age Group - 46 to 55', 'total' => 0),
			'56' => array('label' => 'Age Group - 56+', 'total' => 0)
		);
		return $this->processStatisticResults($results, $fields);
	}
	
	function getMemberJoinsStatistics($year = NULL, $month = NULL) {
		$this->db->select("`c`.`name` AS 'country',
			COUNT(IF(`u`.`gender` = 'M', 1, NULL)) AS 'males',
			COUNT(IF(`u`.`gender` = 'F', 1, NULL)) AS 'females',
			COUNT(`u`.`userId`) AS 'total',
			COUNT(IF(`p`.`isAdministrator` = 1, 1, NULL)) AS 'admin',
			COUNT(IF(`p`.`isManager` = 1 AND `p`.`isAdministrator` = 0, 1, NULL)) AS 'manager',
			COUNT(IF(`p`.`canBeSupervisor` = 1 AND `p`.`isManager` = 0 AND `p`.`isAdministrator` = 0, 1, NULL)) AS 'super',
			COUNT(IF(`p`.`canBeModerator` = 1 AND `p`.`canBeSupervisor` = 0 AND `p`.`isManager` = 0 AND`p`.`isAdministrator` = 0, 1, NULL)) AS 'mod',
			COUNT(IF(`p`.`canBeModerator` = 0 AND `p`.`canBeSupervisor` = 0 AND `p`.`isManager` = 0 AND`p`.`isAdministrator` = 0, 1, NULL)) AS 'standard',
			COUNT(IF(`u`.`DOB` BETWEEN DATE_SUB(NOW(),INTERVAL 18 YEAR) AND DATE_SUB(NOW(),INTERVAL 12 YEAR), 1, NULL)) AS '12',
			COUNT(IF(`u`.`DOB` BETWEEN DATE_SUB(NOW(),INTERVAL 28 YEAR) AND DATE_SUB(NOW(),INTERVAL 19 YEAR), 1, NULL)) AS '19',
			COUNT(IF(`u`.`DOB` BETWEEN DATE_SUB(NOW(),INTERVAL 35 YEAR) AND DATE_SUB(NOW(),INTERVAL 29 YEAR), 1, NULL)) AS '29',
			COUNT(IF(`u`.`DOB` BETWEEN DATE_SUB(NOW(),INTERVAL 45 YEAR) AND DATE_SUB(NOW(),INTERVAL 36 YEAR), 1, NULL)) AS '36',
			COUNT(IF(`u`.`DOB` BETWEEN DATE_SUB(NOW(),INTERVAL 55 YEAR) AND DATE_SUB(NOW(),INTERVAL 46 YEAR), 1, NULL)) AS '46',
			COUNT(IF(`u`.`DOB` > DATE_SUB(NOW(),INTERVAL 56 YEAR), 1, NULL)) AS '56'", FALSE)
		->from('User AS u')
		->join('Country AS c', 'c.countryId = u.countryId')
		->join('Permissions AS p', 'p.userId = u.userId')
		->group_by('c.countryId')
		->order_by('COUNT(u.userId) ', 'desc');
		// Year is optional
		if(!is_null($year)) {
			$this->db->where("YEAR(u.joinDate)", $year);
			// Month is optional, but only included if year is also set
			if(!is_null($month)) {
				$this->db->where('MONTH(u.joinDate)', $month);
			}
		}
		$results = $this->db->get()->result_array();
	
		$fields = array(
			'country' => array('label' => 'Country', 'total' => null),
			'males' => array('label' => 'Male Members', 'total' => 0),
			'females' => array('label' => 'Female Members', 'total' => 0),
			'total' => array('label' => 'Total Members', 'total' => 0),
			'admin' => array('label' => 'Administrators', 'total' => 0),
			'manager' => array('label' => 'Managers', 'total' => 0),
			'super' => array('label' => 'Supervisors', 'total' => 0),
			'mod' => array('label' => 'Moderators', 'total' => 0),
			'standard' => array('label' => 'Standard Users', 'total' => 0),
			'12' => array('label' => 'Age Group - 12 to 18', 'total' => 0),
			'19' => array('label' => 'Age Group - 19 to 28', 'total' => 0),
			'29' => array('label' => 'Age Group - 29 to 35', 'total' => 0),
			'36' => array('label' => 'Age Group - 36 to 45', 'total' => 0),
			'46' => array('label' => 'Age Group - 46 to 55', 'total' => 0),
			'56' => array('label' => 'Age Group - 56+', 'total' => 0)
		);
		return $this->processStatisticResults($results, $fields);
	}
	
	function getGeneralStatistics($year = NULL, $month = NULL) {
		// customer feedback notices

		$this->db->select("c.name AS 'country',
				COUNT(i.userId) AS 'errors',
				'0' AS 'feedback'", FALSE)
			->from('UserToIssue AS i')
			->join('User AS u', 'u.userId = i.userId')
			->join('Country AS c', 'c.countryId = u.countryId')
			->group_by('c.countryId')
			->order_by('COUNT(i.userId)', 'desc');
		
		// Year is optional
		if(!is_null($year)) {
			$this->db->where('YEAR(i.dateCreated) =', $year);
			// Month is optional but only included if year also set
			if(!is_null($month)) {
				$this->db->where('MONTH(i.dateCreated)', $month);
			}
		}
		
		$results = $this->db->get()->result_array();
		$fields = array(
			'country' => array('label' => 'Country', 'total' => null),
			'errors' => array('label' => 'Error Reports', 'total' => 0),
			'feedback' => array('label' => 'Customer Feedback', 'total' => 0)
		);
		return $this->processStatisticResults($results, $fields);
		
	}
	
	private function processStatisticResults($results, $fields) {
		if(empty($results)) {
			return false;
		}
		$gridData = array();
		$totals = array();

		foreach($results as $r) {
			$row = array();
			foreach($fields as $name => $f) {
				$row[$name] = array('label' => $f['label'], 'value' => $r[$name]);
				if(!isset($totals[$name])) {
					$totals[$name] = $f['total'];
				}
				if(is_array($totals[$name])) {
					$totals[$name][] = $r[$name];
				} else if (!is_null($totals[$name])) {
					$totals[$name] += $r[$name];
				}
			}
			$gridData[] = $row;
		}
		$totalRow = array();
		foreach($totals as $name => $v) {
			if (is_null($v)) {
				$totalRow[$name] = array('label' => $fields[$name]['label'], 'value' => 'Total');
			} else if(is_array($v)) {
				$totalRow[$name] = array('label' => $fields[$name]['label'], 'value' => count($v) === 0 ? 0 : round(array_sum($v) / count($v), 1));
			} else {
				$totalRow[$name] = array('label' => $fields[$name]['label'], 'value' => $v);
			}
		}
		$gridData[] = $totalRow;
		return $gridData;
	}
}