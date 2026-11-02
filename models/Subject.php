<?php

use Entities\Forum\Attribute;

class Subject extends CI_Model {

	function __construct() {
		// Call the Model constructor
		parent::__construct();
	}

	function get_subject_branch($subjectId, $userId, $depth, $getRoot) {
		$query = "CALL getSubjectBranch(?,?,?,?);";
		$query = $this->db->query($query, array($subjectId, $userId, $depth, $getRoot));
		return $query->result_array();
	}

	function get_subject_base($userId, $letter) {
		$query = "CALL getSubjectBase(?,?);";
		$query = $this->db->query($query, array($userId, $letter));
		return $query->result_array();
	}

	function get_top_500($userId, $subjectType) {
		$query = "CALL getTop500Subjects(?,?,1000);";
		$query = $this->db->query($query, array($userId, $subjectType));
		return $query->result_array();
	}

	function get_subject($subjectId, $userId) {
		$query = "CALL getSubjectNode(?,?);";
		$query = $this->db->query($query, array($subjectId, $userId));
		return $query->result_array();
	}

	function get_selected_subjectId($subjectName) {
		$query = "CALL getSelectedSubjectId(?);";
		$query = $this->db->query($query, array($subjectName));
		return $query->result_array();
	}

	function check_access($subjectId, $userId) {
		$query = "CALL checkUser(?,?,@result,false);";
		$result = $this->db->query($query, array($subjectId, $userId));
		$query = $this->db->query("select @result as result;");

		$result = $query->row();
		return $result->result;
	}

	function follow_subject($subjectId, $userId, $groupWith, $action) {
		// Don't allow supervisors to follow subjects themselves
		if ($this->user->isSupervisor($userId)) {
			return false;
		}
		// Don't allow if the subject is club18 and the user is underage
		if ($this->user->isUnder18($userId) && (int) $this->group->getType($subjectId) === group::TYPE_CLUB18) {
			return false;
		}
		
		//check they are not the last moderator for this forum
		if ($this->user->isModeratorSubject($userId, $subjectId) && $this->user->moderatorCount($subjectId) <= 1) {
			return 'lastMod';
		}

		$forumsFollowed = $this->user->getUserForumIds($userId);
		$query = "CALL addSubjectToUserList(?,?,?,?);";

		// If user is only following a single forum at the moment, they must have joined through
		// a 3rd party link, so now they should be added to main feed and my feed, since they're joining
		// more forums than they signed up for
		if (count($forumsFollowed) === 1) {
			$mainFeedId = $this->group->findByNameAndType('main feed', group::TYPE_RAMBLUR);
			$myFeedId = $this->group->findByNameAndType('my feed', group::TYPE_RAMBLUR);
			$this->db->query($query, array($userId, $mainFeedId, 0, 1));
			$this->db->query($query, array($userId, $myFeedId, 0, 1));
		}
		$query = $this->db->query($query, array($userId, $subjectId, $groupWith, $action));

		// Also remove action buttons/conversations for this user and subject
		$this->group->deleteActionButtons($subjectId, $userId);

		// Create history record
		$this->user->addUserToSubjectHistory($userId, $subjectId, $action);

		return true;
	}

	function follow_subject_group($userId, $subjectId, $setOffSpring) {
		// Don't allow supervisors to follow subjects themselves
		if ($this->user->isSupervisor($userId)) {
			return false;
		}
		$query = "CALL addSubjectBranchToUserList(?,?,?);";
		$query = $this->db->query($query, array($userId, $subjectId, $setOffSpring));
	}

	function group_subjects($groupOwner, $groupSubject, $userId, $action) {
		if ($action == FALSE) {
			$query = "CALL deleteGroupSubject(?,?,?);";
			$query = $this->db->query($query, array($userId, $groupOwner, $groupSubject));
		} else {
			$query = "CALL groupSubjects(?,?,?,?);";
			$query = $this->db->query($query, array($userId, $groupOwner, $groupSubject, $action));
		}
		//return $query->result_array();
	}

	function group_subject_branch($userId, $groupOwner) {
		$query = "CALL groupSubjectBranch(?,?);";
		$query = $this->db->query($query, array($userId, $groupOwner));

		//return $query->result_array();
	}

	function get_subject_groups($userId, $subjectId) {
		$query = "CALL getSubjectGroups(?,?);";
		$query = $this->db->query($query, array($userId, $subjectId));
		return $query->result_array();
	}

	function get_featured_group($userId) {
		$query = "CALL getFeaturedGroup(?);";
		$query = $this->db->query($query, array($userId));
		//## a kludge b/s this query returns null when featuredGroup == 0
		if ($query->result_array() == null) {
			$resultx[0]['featuredGroup'] = 0;
			$resultx[0]['subjectName'] = 'null';
			$resultx[0]["searchChoice"] = 15;
			return $resultx;
		} else {
			return $query->result_array();
		}
	}

	function set_featured_group($userId, $subjectId) {
		$query = "CALL setFeaturedGroup(?,?);";
		$query = $this->db->query($query, array($userId, $subjectId));
	}

	function unset_featured_group($userId, $subjectId) {
		$query = "UPDATE User SET featuredGroup = 0 WHERE featuredGroup = ? AND userId = ?;";
		$query = $this->db->query($query, array($subjectId, $userId));
		$query = "DELETE FROM subjectGroups WHERE subjectId = ? AND userId = ?;";
		$query = $this->db->query($query, array($subjectId, $userId));
	}

	function get_root_subjectname_from_subjectId($userId, $subjectId) {
		$query = "CALL getRootSubjectNameFromSubjectId(?,?);";
		$query = $this->db->query($query, array($userId, $subjectId));
		return $query->result_array();
	}

	function get_subjectid_from_subjectName($subjectName) {
		$query = "CALL getSelectedSubjectId(?);";
		$query = $this->db->query($query, array($subjectName));
		return $query->result_array();
	}

	function get_root_subjectname($subjectName, $userId) {
		$k = 0;
		$arr_results_sets = array();
		/* execute multi query */
		if (mysqli_multi_query($this->db->conn_id, 'CALL getRootSubjectName("' . $subjectName . '",' . $userId . ')')) {
			do {
				$result = mysqli_store_result($this->db->conn_id);
				// print_r($result);
				if ($result) {
					$l = 0;
					while ($row = $result->fetch_assoc()) {
						$arr_results_sets[$k] = $row;
						$l++;
					}
				}
				$k++;
			} while (mysqli_more_results($this->db->conn_id) && mysqli_next_result($this->db->conn_id));
		}
		return($arr_results_sets);
	}

	function get_rf_status($subjectId) {
		$query = "SELECT subjectId FROM Subject WHERE parentSubjectId = ? AND subjectName='ramblur forums';";
		$query = $this->db->query($query, array($subjectId));
		$result = $query->result_array();
		if (!empty($result[0])) {
			return ($result[0]['subjectId']);
		} else {
			return false;
		}
	}

	function isBannedFromForum($userId, $subjectId) {

		$this->db->select('active')
				->where('currentlyBanned', 1)
				->where('active', 0)
				->where('userId', $userId)
				->where('subjectId', $subjectId);

		$query = $this->db->get('UserToSubject');

		if ($query->num_rows() > 0) {
			return true;
		}
		return false;
	}

	/* added by cjt - temp or otherwise */

	function follow_subject2($data) {
		$userId = $this->user->authCheck();
		if ($userId === false) {
			return NULL;
		}

		if ($this->isBannedFromForum($userId, $data['subjectId'])) {
			return array('message' => 'banned');
		}

		// Don't allow if the subject is club18 and the user is underage
		if ($this->user->isUnder18($userId) && (int) $this->group->getType($data['subjectId']) === group::TYPE_CLUB18) {
			return false;
		}

		$action = ($data['action'] === 'sub') ? true : false;

		if ($action) {
			// can't follow my feed or main feed
			if (($this->group->getName($data['subjectId']) === 'main feed' && (int) $this->group->getType($data['subjectId'])) === group::TYPE_RAMBLUR || $data['subjectId'] == 1) {
				return false;
			}
			$forumEntity = $this->subjectAttribute->getForumEntity($data['subjectId']);

			// Can't follow forums which have been marked as requiring an invitation to join
			if ($forumEntity->is(Attribute::INVITE_ONLY())) {
				return false;
			}
		} else {
			//check they are not the last moderator for this forum
			if($this->user->isModeratorSubject($userId, $data['subjectId']) && $this->user->moderatorCount($data['subjectId']) <=1) {
				$result['message'] = 'lastMod';
				return $result;
			}
				
		}

		$forumsFollowed = $this->user->getUserForumIds($userId);

		// groupWith (related to inFeed)
		// set, then change as required
		$groupWith = 0;

		if (isset($data['type']) && $data['type'] == 'group') {
			$groupWith = $data['groupWithId'];
		}

		$query = "CALL addSubjectToUserList(?,?,?,?);";

		// If user is only following a single forum at the moment, they must have joined through
		// a 3rd party link, so now they should be added to main feed and my feed, since they're joining
		// more forums than they signed up for
		if (count($forumsFollowed) === 1) {
			$mainFeedId = $this->group->findByNameAndType('main feed', group::TYPE_RAMBLUR);
			$myFeedId = $this->group->findByNameAndType('my feed', group::TYPE_RAMBLUR);
			$this->db->query($query, array($userId, $mainFeedId, 0, 1));
			$this->db->query($query, array($userId, $myFeedId, 0, 1));
		}

		// groupWith hardcoded to 0 until more info about known - cjt
		// more now known about groupWith - presuming that if groupWith is "false/0" then "inFeed" flag is toggled....june16
		$query = $this->db->query($query, array($userId, $data['subjectId'], $groupWith, $action));

		// Also remove action buttons/conversations for this user and subject
		$this->group->deleteActionButtons($data['subjectId'], $userId);

		// Create history record
		$this->user->addUserToSubjectHistory($userId, $data['subjectId'], $action);

		$result = array();

		//$result['message'] = ($numrows>0) ? 'true' : 'false';
		$result['message'] = 'true'; // see SP and gitlab #137 for this change
		$result['subjectAttributes'] = $this->subjectAttribute->getSubjectAttributesArray($data['subjectId']);
		$result['subjectVisibility'] = $this->subjectAttribute->getForumVisibility($data['subjectId']);
		
		if ($action) { //subscribe
			$this->db->select('subjectId')
					->select('forumThemeSet')
					->select('primaryColour')
					->select('secondaryColour')
					->select('themeColourOptionId')
					->select('primaryThemeFontOptionId')
					->select('secondaryThemeFontOptionId')
					->select('opacity')
					->from('UserToSubject')
					->where('subjectId', $data["subjectId"])
					->where('userId', $userId);

			$forum = $this->db->get();
			if ($forum->num_rows() > 0) {
				$forum = $forum->result_array();

				$this->db->select('forumTheme')
						->from('UserToSubject')
						->where('subjectId', 1)
						->where('userId', $userId);
				$forumTheme = $this->db->get();
				if ($forumTheme->num_rows() > 0) {
					$forumTheme = $forumTheme->result_array();

					$this->db->set('primaryColourOriginal', $forum[0]["primaryColour"])
							->set('secondaryColourOriginal', $forum[0]["secondaryColour"])
							->set('forumTheme', $forumTheme[0]["forumTheme"])
							->set('forumThemeSet', 1)
							->set('themeColourOptionIdOriginal', $forum[0]["themeColourOptionId"])
							->set('primaryThemeFontOptionIdOriginal', $forum[0]["primaryThemeFontOptionId"])
							->set('secondaryThemeFontOptionIdOriginal', $forum[0]["secondaryThemeFontOptionId"])
							->set('opacityOriginal', $forum[0]["opacity"])
							->where('userId', $userId)
							->where('subjectId', $data["subjectId"])
							->update('UserToSubject');
					
					if ($forumTheme[0]["forumTheme"] === "nightMode") {
						$this->user->setForumTheme($userId, $forumTheme[0]["forumTheme"], '#818181', '#697389', 2);
					} else if ($forumTheme[0]["forumTheme"] === "defaultMode") {
						$this->user->setForumTheme($userId, $forumTheme[0]["forumTheme"], '#787E87', '#738DA7', 1);
					}
					
				}
			}
		}

		return $result;
	}

	/* added by cjt - temp or otherwise */

	function group_subjects2($data) {

		$userId = $this->user->authCheck();
		if ($userId === false) {
			return NULL;
		}
		// adding or removing from group or featured group
		// first count how many there are in subjectGroups now
		// if 1 and unsub = remove Group/FG too
		// if 0 and sub = create FG (group create done from A-Z search page)
		$query = "SELECT subjectId FROM subjectGroups WHERE userId = ? AND groupWith = ?";
		$result = $this->db->query($query, array($userId, $data['groupWithId']));
		$numSubs = $result->num_rows();

		// check for existing Featured Group
		// MIGHT NOT NEED IF TOGGLE ALL OFF BY CLASS, then TOGGLE ON by Id works...
		if ($data['type'] === 'fg') {

			$sql = "SELECT featuredGroup FROM User WHERE userId = ?";
			$params = array($userId);
			if ($result = $this->db->query($sql, $params)) {

				$fg = $result->result_array();
				$featuredGroup = $fg[0]['featuredGroup'];
			}
		}

		$response = array();

		// REMOVE
		if ($data['action'] === 'unsub') {

			$query = "DELETE FROM subjectGroups WHERE userId = ? AND subjectId = ? AND groupWith = ?";
			if ($query = $this->db->query($query, array($userId, $data['subjectId'], $data['groupWithId']))) {

				if ($numSubs === 1) { // ie. this is the last member of the group being removed
					if ($data['type'] === 'fg') {

						$sql = "UPDATE User SET featuredGroup = 0 WHERE userId = ?";
						$sql = $this->db->query($sql, array($userId));
					}

					if ($data['type'] === 'group') {

						$sql = "UPDATE UserToSubject SET grouped = 0, allSelected = 0 WHERE subjectId = ? AND userId = ?";
						$result = $this->db->query($sql, array($data['groupWithId'], $userId));
					}

					$response['message'] = '2';
				} else
					$response['message'] = '1';
			} else
				$response['message'] = '0';

			// ADD
		} else {

			// Add a new FG, but there is a current GROUPING
			// remove topics from subjectGroups
			// if there are existing entries in subjectGroups and we're dealing with a new FG (existing User.featuredGroup = 0)
			if ($numSubs > 0 && $data['type'] == 'fg' && $featuredGroup == 0) {

				// first delete any grouped topics from subjectGroups as we now switch to FG mode
				// otherwise duplicate key entry MySQL error!
				$sql = "DELETE FROM subjectGroups WHERE groupWith = ? AND userId = ?";
				$result = $this->db->query($sql, array($data['groupWithId'], $userId));
				// reset $numSubs for later condition
				$numSubs = 0;
				$response['groupToFG'] = 1;
			}

			$query = "INSERT INTO subjectGroups (userId, subjectId, groupWith) VALUES (?, ?, ?)";
			if ($query = $this->db->query($query, array($userId, $data['subjectId'], $data['groupWithId']))) {

				$query = "UPDATE UserToSubject SET grouped = 1 WHERE subjectId = ? AND userId = ?";
				if ($query = $this->db->query($query, array($data['subjectId'], $userId))) {

					if ($numSubs === 0 && $data['type'] === 'fg') { // ie. this is the first member of the new group
						// ?? also add groupWith Id to subjectGroups? does it matter?  fgId is in User table...
						$sql = "UPDATE User SET featuredGroup = ? WHERE userId = ?";
						$result = $this->db->query($sql, array($data['groupWithId'], $userId));

						$response['message'] = '3';

						// if existing FG exists, pass this back
						if ($featuredGroup !== 0)
							$response['oldFG'] = $featuredGroup;
					} else
						$response['message'] = '1';
				}
			} else
				$response['message'] = '0';
		}

		return $response;
	}

	// added - cjt
	// trouble passing similar arguments via my mobile Data "class"
	function getSubjectGroups($data) {

		$userId = $this->user->authCheck();
		if ($userId === false) {
			return false;
		}
		$query = "CALL getSubjectGroups(?, ?);";

		if ($query = $this->db->query($query, array($userId, $data['subjectId']))) {
			return $query->result_array();
		}
		return false;
	}

	// takes a topic and groups all the sub-topics (in subjectGroups)
	function groupSubjectBranch($data) {

		$userId = $this->user->authCheck();
		if ($userId === false) {
			return '0';
		}
		$query = "CALL groupSubjectBranch(?, ?);"; // BUG: this is setting the parent topic active status to 0...:\
		if ($query = $this->db->query($query, array($userId, $data['subjectId']))) {
			// --- SHORT TERM FIX FOR COMMENT ABOVE --- //
			// not sure about this 'active' business
			$sql = "UPDATE UserToSubject SET active = 1 WHERE subjectId = ? AND userId = ?";
			$result = $this->db->query($sql, array($data['subjectId'], $userId));
			return '1';
		}
		return '0';
	}

	// added in haste to get a solution
	// may be an existing method to use eventually?
	function removeGroup($data) {

		$userId = $this->user->authCheck();

		if (!$userId) {
			return false;
		}

		// [subjectGroups] - delete all records attached to this group/user
		$query = "DELETE FROM subjectGroups WHERE groupWith = ? AND userId = ?";
		if ($query = $this->db->query($query, array($data['subjectId'], $userId))) {

			$r = '1';

			// [UserToSubject] - remove group flag(s)
			// added "active = 1" to ensure that the mobile continues to "see" the subscription to the topic...at least until the "active" question is answered
			$sql = "UPDATE UserToSubject SET active = 1, grouped = 0 WHERE subjectId = ? AND userId = ?";
			if ($sql = $this->db->query($sql, array($data['subjectId'], $userId))) {

				if ($data['fgListAction'] === 1) {

					// [User] - if from FG call, also unset that
					$sql = "UPDATE User SET featuredGroup = 0 WHERE userId = ?";
					if ($sql = $this->db->query($sql, array($userId))) {

						$r = '2'; // not sure of the point of this!  Needs a different number for later error chking...
					}
				} else
					$r = '2';

				return $r;
			}
			return $r; // == 1, ie. the 2nd query hasn't occurred or has failed...handle somehow?  system email/front end?
		}
		return false;
	}

	//looks like only used for mob
	//also does not take intou account non-member wallpapers - need to check if user is following the subject
	function getBackgroundImage($data) {
		
		$userId = $this->user->authCheck();
		$anonymous = false;
		if ($userId === false) {
			$anonymous = true;
		}

		$this->db->select('sd.fileName, s.private as subjectPrivate')
				->from('SubjectDisplay as sd')
				->join('Subject as s', 'sd.subjectId = s.subjectId')
				->where([
					'sd.subjectId' => $data['subjectId'],
					'sd.active' => 1
				])
				->where("sd.type in ('memberBg', 'thirdPartyBg')")
				->where($anonymous ? 'userId IS NULL' : "(userId IS NULL OR userId = $userId)")
				->order_by('order', 'random');
		$result = $this->db->get()->result_array();
		return (empty($result)) ? 0 : $result[0];
	}
	
	function getSubjectDetailsForBan($userId,$subjectId) {
		
		if(!$this->user->authCheck()) {
			return false;
		}

		$query = $this->db->select(
				  'Subject.subjectName, '
				. 'Subject.private, '
				. 'Subject.type, '
				. 'Subject.primaryColour, '
				. 'Subject.secondaryColour, '
				. 'u.primaryColour AS userprimaryColour, '
				. 'u.secondaryColour AS usersecondaryColour, ' 
				. 'ff.value AS themeFontPrimary, '
				. 'f.value AS themeFontSecondary ,'
				. 'User.email'
				)
				->from('Subject')
				->join('UserToSubject AS u', 'u.subjectId = Subject.subjectId AND u.active = 1 AND u.userId = '.$userId, 'LEFT')
				->join('ThemeColourOptions AS t', 't.id = IF(u.themeColourOptionId IS NULL, Subject.themeColourOptionId, u.themeColourOptionId)', 'INNER', FALSE)
				->join('ThemeFontOptions AS f', 'f.id = IF(u.secondaryThemeFontOptionId IS NULL, Subject.secondaryThemeFontOptionId, u.secondaryThemeFontOptionId)', 'INNER', FALSE)
				->join('ThemeFontOptions AS ff', 'ff.id = IF(u.primaryThemeFontOptionId IS NULL, Subject.primaryThemeFontOptionId, u.primaryThemeFontOptionId)', 'INNER', FALSE)
				->join('User', 'User.userid = '.$userId)
				->where('Subject.subjectId', $subjectId);
		$query = $this->db->get();
		if ($query->num_rows() > 0) {
			$returnArray = array(
								"subjectName" => $query->row()->subjectName, 
								"private" => $query->row()->private, 
								"type" => $query->row()->type,
								"email" => $query->row()->email,
								"primaryColour" => $query->row()->primaryColour,
								"secondaryColour" => $query->row()->secondaryColour,
								"userprimaryColour" => $query->row()->userprimaryColour,
								"usersecondaryColour" => $query->row()->usersecondaryColour,
								"themeFontPrimary" => $query->row()->themeFontPrimary,
								"themeFontSecondary" => $query->row()->themeFontSecondary
							);
			return $returnArray;
		}
		return NULL;

	}



	// -- end added --
}

?>
