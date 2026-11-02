<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
use \Ramblur\CIUtils\ModelHelper;
class Admin extends Tabbed_Controller {

	protected $title = 'administration';
	
	protected $indexRedirect = '/admin/subjects-approve';
	
	/**
	 * Generates tabs and landing page for admin pages.
	 */
	public function index() {
		if(!$this->initialiseAdmin()) {
			return;
		}
		parent::index();
	}
	
	public function subjects() {
		redirect('/admin/subjects-approve');
	}
	/**
	 * Handles the data for the unapproved topics admin page. 
	 * 
	 * @param int $subjectId
	 */
	public function subjects_approve($suggestedSubjectId = null) {
		if($this->initialiseAdmin() === false) {
			return;
		}
		$this->load->model('group');
		
		$data = [];
		if($this->input->post('submit')) {
			if(!is_null($suggestedSubjectId)) {
				$this->load->model('message');
				$messageData = [];
				$checked = false;
				$error = false;
				$this->db->trans_start();
				if($this->input->post('submit') == 'approve') {
					if(!$this->input->post('parentSubjectName')) {
						$error = 'You must provide a parent subject name so that the new subject can be created in the right place in the hierarchy.';
					} else {
						$parentSubjectId = $this->group->findByName($this->input->post('parentSubjectName'));
						if(!$parentSubjectId) {
							$error = 'The parent subject could not be found.';
						} else {
							$checked = $this->group->adminChecked($suggestedSubjectId, true, $this->input->post('parentSubjectName'), $error);
						}
					}
					
				} else if ($this->input->post('submit') == 'deny') {
					$checked = $this->group->adminChecked($suggestedSubjectId, false, null, $error);
				}
				
				if($this->db->trans_status() !== false && $error === false && $checked) {
					$this->db->trans_commit();
				} else {
					$this->db->trans_rollback();
					if($error) {
						$data['error'] = $error;
					} else {
						$data['error'] = 'Something went wrong, the check did not complete successfully. Try again.';
					}
				}
			}
		}
		// Show unapproved subjects
		$data["unapproved"] = $this->group->unapproved();
		$this->loadViews('admin/adminSubjects', $data, 'subjects-approve');
	}
	
	/**
	 * Handles retrieval of data for the topic moderators admin page.
	 * Paginates list of subjects for easier viewing.
	 * @param int $page
	 */
	public function subjects_manage($page = 1) {
		if($this->initialiseAdmin() === false) {
			return;
		}
		$this->load->model('group');
		$this->load->library('pagination');

		$config = [];
		$config['base_url'] = base_url() . "admin/subjects_manage";
		$config['total_rows'] = $this->group->countAll(group::TYPE_RAMBLUR);
		$config['per_page'] = 20;
		$config['uri_segment'] = 3;
		$config['use_page_numbers'] = TRUE;
		$config['num_links'] = 5;
		$config['attributes'] = array('class' => 'pagination-link');
		
		$this->pagination->initialize($config);
		$data['topics'] = $this->group->all($page - 1, $config['per_page'], group::TYPE_RAMBLUR);
		$data['links'] = $this->pagination->create_links();
		
		// Show moderators
		$moderators = $this->user->findRamblurModerators();
		$topicCount = count($data['topics']);
		for ($i = 0; $i < $topicCount; $i++) {
			$subjectId = $data['topics'][$i]['id'];
			$data['topics'][$i]['availableModerators'] = [];
			$data['topics'][$i]['existingModerators'] = [];
			foreach($moderators as $m) {
				$subjectIndex = array_search($subjectId, array_column($m['subjects'], 'id'));
				if($subjectIndex !== false) {
					// Keep this subject only
					$modSubject = $m['subjects'][$subjectIndex];
					unset($m['subjects']);
					$m['subject'] = $modSubject;
					if($modSubject["moderator"]) {
						$data['topics'][$i]['existingModerators'][] = $m;
					} else {
						$data['topics'][$i]['availableModerators'][] = $m;
					}
				}
			}
		}
		$data['currentPage'] = $page;

		$this->loadViews('admin/adminSubjects', $data, 'subjects-manage');
	}
	
	/**
	 * Handles actions for the topic moderators admin page.
	 * After the action is one, redirects to subject_manage. Done this way to allow for pagination of subjects
	 * @todo - error handling
	 */ 
	public function subjects_assign($subjectId = null) {
		if($this->initialiseAdmin() === false) {
			return;
		}
		$this->load->model('group');
		
		$data = [];
		if($this->input->post('submit')) {
			if(!is_null($subjectId)) {
				if ($this->input->post('submit')) {
					$action = $this->input->post('submit');
					if($action === 'assign') {
						$moderatorId = $this->input->post('assigned_id');
						$assign = true;
					} else if ($action === 'unassign') {
						$moderatorId = $this->input->post('removed_id');
						$assign = false;
					}
					if(isset($moderatorId) && $moderatorId !== false) {
						$this->user->setModeratorAssignedToSubject($moderatorId, $subjectId, $assign);
					}
				}
			}
		}
		if($this->input->post('page_number') !== false && ctype_digit($this->input->post('page_number'))) {
			$pageNumber = $this->input->post('page_number');
		} else {
			$pageNumber = 1;
		}
		// Redirect instead of calling function directly so that a) URLs are correct b) pagination links are built properly, since pagination library uses uri segment
		redirect('/admin/subjects_manage/'.$pageNumber);
	}
	
	public function subjects_applications($subjectId = null) {
		if($this->initialiseAdmin() === false) {
			return;
		}
		
		$this->load->model('group');
		$viewData = [];
		// if not approved, approved = 0, else = 1
		// store actions in log - applicationApprove, applicationDeny
		
		if($this->input->post('submit') && !is_null($subjectId)) {
			if(!$this->input->post('user')) {
				$viewData['message'] = 'The user applying to be a moderator is required.';
			} else {
				$userId = $this->input->post('user');
				$this->db->select('subjectId')
					->from('Subject')
					->where([
						'subjectId' => $subjectId,
						'type' => group::TYPE_RAMBLUR,
						'visible' => 1
					]);
				if($this->db->get()->num_rows() === 0) {
					$viewData['message'] = 'The subject could not be found.';
				} else {
					// Check the user has applied
					$this->db->select('userId')
						->from('ModeratorToSubject')
						->where([
							'userId' => $userId,
							'subjectId' => $subjectId,
							'approved' => NULL
						]);
					if($this->db->get()->num_rows() === 0) {
						$viewData['message'] = 'The user has not applied to be a moderator of that subject, or the application has already been processed.';
					} else {
						if($this->input->post('submit') === 'approve') {
							$this->db->update('ModeratorToSubject',
								['approved' => 1],
								[
									'userId' => $userId,
									'subjectId' => $subjectId,
									'approved' => NULL
								]
							);
							if($this->db->affected_rows() === 0) {
								$viewData['message'] = 'The application has not been approved. Check the user is eligible and try again.';
							} else {
								$viewData['message'] = 'The user has been approved as a moderator. To assign them to that subject, go to the MODERATORS tab.';
							}
							$this->db->update('Permissions', ['canBeModerator' => 1], ['userId' => $userId]);
						} else if ($this->input->post('submit') === 'deny') {
							$this->db->update('ModeratorToSubject',
								['approved' => 0],
								[
									'userId' => $userId,
									'subjectId' => $subjectId,
									'approved' => NULL
								]
							);
							if($this->db->affected_rows() === 0) {
								$viewData['message'] = 'The application was not denied. Check it and try again.';
							} else {
								$viewData['message'] = 'The application to be a moderator has been denied.';
							}
						}
						$this->db->insert('ModeratorToSubjectLog',
							[
								'agentId' => $this->user->authCheck(),
								'moderatorId' => $userId,
								'subjectId' => $subjectId,
								'action' => 'application'.ucfirst($this->input->post('submit'))
							]
						);
					}
				}
			}
		}
		// Retrieve applications
		$this->db->select("
				`m`.`userId`,
				`u`.`userName`,
				CONCAT(`u`.`firstName`, ' ', `u`.`lastName`) AS 'fullName',
				`m`.`text`,
				`m`.`publicProfile`,
				`s`.`subjectId`,
				`sub`.`subjectName`", false)
			->from('Moderator AS m')
			->join('ModeratorToSubject AS s', 's.userId = m.userId')
			->join('User AS u', 'u.userId = m.userId')
			->join('Subject AS sub', 'sub.subjectId = s.subjectId')
			->where([
				'm.ramblurEnabled' => 1,
				's.approved' => NULL,
				'sub.type' => group::TYPE_RAMBLUR,
				'sub.visible' => 1
		]);
		$mods = $this->db->get()->result_array();
		$modsById = [];
		foreach($mods as $m) {
			$modId = $m['userId'];
			if(!isset($modsById[$modId])) {
				$modsById[$modId] = [
					'userId' => $modId,
					'userName' => $m['userName'],
					'fullName' => $m['fullName'],
					'text' => $m['text'],
					'publicProfile' => $m['publicProfile'],
					'subjects' => []
				];
			}
			$modsById[$modId]['subjects'][] = ['id' => $m['subjectId'], 'name' => $m['subjectName']];
		}
		$viewData['moderators'] = $modsById;
		$this->loadViews('admin/adminApplications', $viewData, 'subjects-applications');
	}
	
	/**
	 * Displays an index of all posts ordered by last edit date
	 * @param type $page
	 */
	public function posts_summary($page = 0){		
		if(!$this->initialiseAdmin()) {
			return;
		}
		$currentPage = (int)$page ?? 0;
		$maxResults = 50;
		
		$this->load->model("adminTasks");
		$this->load->model("user");
		$this->load->model("group");
		$this->load->model("node");
		$this->load->model("file");
		$this->load->model("geo");
		$this->load->library("pagination");
		$this->load->helper('url');
		
		$viewData = [] ;
		$nodeContent = [];
		$nodeContentMap = new \Ds\Map();
		$markedNodesMap = new \Ds\Map();
		//Get the current user data for timezone
		$userInfo = $this->user->profileDetails();		
		
		//Node and content retrieval
		$total = $this->group->getViewableNodeCount();		
		$nodes = $this->group->getViewableNodesSummary($currentPage, $maxResults);
		if($nodes){
			$nodeIds = array_unique(array_column($nodes,"nodeId")) ;						
			$nodeContent = $this->node->getNodeIdsWithFileNameFromContentIds($nodeIds);
			if($nodeContent){
				$nodeContentMap->putAll(ModelHelper::getSafeMapIndexedByColumn("nodeId", $nodeContent));	
			}
			
			$markedNodes = $this->adminTasks->getMarkedNodes($nodeIds,$this->user->authCheck());
			if($markedNodes){
				$markedNodesMap->putAll(ModelHelper::getMapIndexedByColumn("nodeId", $markedNodes));
			}
		}else{
			$nodes = [];
		}
				
		//Pagination
		$paginatorConfig['base_url'] = base_url() . 'admin/posts-summary';
		$paginatorConfig['total_rows'] = $total ;
		$paginatorConfig['per_page'] = $maxResults;
		$paginatorConfig['num_links'] = 20;
		$this->pagination->initialize($paginatorConfig);
		
		//View data
		$viewData['baseUrl'] = base_url();
		$viewData['fileModel'] = $this->file;
		$viewData['geoModel'] = $this->geo;
		$viewData['nodes'] = $nodes;
		$viewData['nodeContentMap'] = $nodeContentMap;
		$viewData['markedNodesMap'] = $markedNodesMap;
		$viewData['timeZone'] = $userInfo['timeZone'] ?? "UTC";
		$viewData['paginatorLinks'] = $this->pagination->create_links();
		
		$this->loadViews('admin/postSummary', $viewData, 'subjects-applications');
	}
	
	public function alerts() {
		redirect('/admin/alerts-create');
	}
	
	/**
	 * Handles creation of global messages by admin
	 */
	public function alerts_create() {
		if($this->initialiseAdmin() === false) {
			return;
		}
		$data = [];
		
		if($this->input->post('submit')) {
			$this->load->library('form_validation');
			$this->load->model('file');
			
			$this->form_validation->set_rules('alert_title', 'title', 'required');
			$this->form_validation->set_rules('alert_type', 'type', 'required');
			$this->form_validation->set_rules('alert_content', 'content/text', 'required');
			$this->form_validation->set_rules('alert_country', 'target country', 'alpha|exact_length[2]');
			$this->form_validation->set_rules('alert_usertype', 'target user type', 'enum[moderator, supervisor]');
		
			if ($this->form_validation->run() === FALSE) {
				$data["error"] = validation_errors();
			} else {
				
				// Important that this is atomic, so use a database transaction.
				$this->db->trans_begin();
				
				// Clear validation data so blank form can be displayed on success
				$this->form_validation->clear_field_data();
				$this->load->model('post');
				$createPostData = [];
				$createPostData["type"] = $this->input->post('alert_type');
				$createPostData["user"] = $this->user->authCheck();
				$createPostData["group"] = $this->input->post('alert_subject') ? $this->input->post('alert_subject') : 'my feed';
				$createPostData["rating"] = '';
				$createPostData["subject"] = $this->input->post('alert_title');
				$createPostData["body"] = $this->input->post('alert_content');
				$createPostData["parent"] = '';
				$createPostData["post"] = '';
				$createPostData["author"] = $this->user->getAuthUserName();
				$createPostData["about"] = '';
				$createPostData['startTime'] = '';
				$createPostData['endTime'] = '';
				$createPostData['category'] = NULL;
				$createPostData["sendModNotifications"] = false;
				
				$data["newPost"] = $this->post->storeNode($createPostData);
				if(!$data["newPost"]) {
					$data["error"] = 'There was an error and the alert was not created.';
				} else {
					if($this->input->post('alert_usertype')) {
						
						if($this->input->post('alert_usertype') == 'moderator') {
							// Insert rows into UserToAdminPost
							$this->db->query(
								"INSERT INTO UserToAdminPost (userId, postId)
									SELECT m.userId, n.postId
									FROM Nodes AS n
									JOIN Moderator AS m
									WHERE n.nodeId = ? 
									AND n.level = 0", 
								[$data["newPost"]]);
							
						} else if($this->input->post('alert_usertype') == 'supervisor'){
							// Insert rows into UserToAdminPost
							$this->db->query(
								"INSERT INTO UserToAdminPost (userId, postId)
									SELECT p.userId, n.postId
									FROM Nodes AS n
									JOIN Permissions AS p
									WHERE n.nodeId = ? 
									AND p.canBeSupervisor = 1
									AND n.level = 0", 
								[$data["newPost"]]);
						}
					}
					if($this->input->post('alert_country')) {
						// Store against target country
						$this->db->query(
								"INSERT INTO PostToCountry (postId, countryId)
								SELECT n.postId, c.countryId
								FROM Nodes AS n
								JOIN Country AS c
								WHERE n.nodeId = ?
								AND c.countryId = ?
								AND n.level = 0",
								[$data["newPost"], $this->input->post('alert_country')]);
					}
					if(isset($_FILES["alert_file"]) && $_FILES["alert_file"]["size"] !== 0) {
						$uploadErrorMsg = 'There was an error uploading the file, so the global message was not created. Please try again.';
						if($_FILES["alert_file"]["error"] > 0) {
							$data["error"] = $uploadErrorMsg;
						} else {
							$uuid = $this->file->store(['local' => $_FILES["alert_file"]["tmp_name"], 'parent' => $data["newPost"]['nodeId'], 'group' => 1, 'comment' => '', 'description' => '']);
							if(!$uuid) {
								$data["error"] = $uploadErrorMsg;
							}
						}
					}
				}
				if(isset($data["error"]) || $this->db->trans_status() === FALSE) {
					if($this->db->trans_status() === FALSE) {
						//$data["error"] = $this->db->_error_message();
						$data["error"] = 'A database error occurred. Please try again.';
					}
					$this->db->trans_rollback();
				} else {
					$this->db->trans_commit();
				}
			}
		}

		$this->loadViews('admin/adminAlerts', $data);
	}
	
	//public function deleteContent_forums() {
	//	redirect('/Teardown');
	//}
	
	/* landing page admin tools */
	
	//public function landingpageposts() {
	//	redirect('/admin/landingpageposts-postblacklist');
	//}

	public function landingpageposts_postblacklist() {
		$this->blacklistForum();
	}

	public function blacklistForum() {
		if($this->initialiseAdmin() === false) {
			return;
		}
		$data["action"] = 'index';
		$this->loadViews('admin/landingPagePosts', $data);
	}

	/* end landing page admin tools */
	
	
	/* draft post admin tool */
	
	public function draftPosts_draftPosts() {
		$this->draftPosts();
	}

	public function draftPosts() {

			if ($this->initialiseAdmin() === false) {
				return;
			}
			
			if($this->uri->segment(3) == 'manage') {

			$this->load->model('group');
			$this->load->model('user');

			$data["userId"] = $this->user->authCheck();
			$data["isAdmin"] = true;
			$data["action"] = 'index';
			$data["forums"] = $this->group->getAdminDraftForumsNamesAndIds();
			$data["draftMetaUsers"] = $this->group->getDraftMetaUsers();
			$data["draftUsers"] = $this->group->getDraftUsers();
			$data["draftPriorityDates"] = $this->group->getDraftPriorityDates();
			$this->loadViews('admin/draftPosts', $data);
		} else if ($this->uri->segment(3) == 'messages') {
			$this->draftPostsMessages();
		} else {
			$this->draftPostsOverview();
		}
		
	}

	public function draftPostsOverview_draftPostsOverview() {
		$this->draftPostsOverView();
	}
	
	public function draftPostsOverview() {
		if($this->initialiseAdmin() === false) {
			return;
		}
		
		$this->load->model('group');
		$this->load->model('user');
		
		$data["userId"] = $this->user->authCheck();
		$data["isAdmin"] = true;
		$data["action"] = 'index';
		$data["forums"] = $this->group->getAdminDraftForumsNamesAndIds();
		$data["draftMetaUsers"] = $this->group->getDraftMetaUsers();
		$data["draftUsers"] = $this->group->getDraftUsers();
		$data["draftPriorityDates"] = $this->group->getDraftPriorityDates();

		$data["forumSelection"] = $this->input->post('forumSelection');
		$data["priorityDatesOnly"] = $this->input->post('priorityDatesOnly');
		
		$data['groupForumHeaders'] = true;
		if($data['priorityDatesOnly']){
			$data['groupForumHeaders'] = false;
		}
		
		$data["draftPostsOverview"] = $this->group->getDraftPostsOverview($data["forumSelection"], $data["priorityDatesOnly"]);
		
		$this->load->helper("date_utils_helper");
		$this->loadViews('admin/draftPostsOverview', $data);
	}
	
	public function draftPostsMessages() {
		
		$this->load->model('message');
		
		$data["isAdmin"] = true;
		$data["draftMessages"] = $this->message->getDraftMessages();
		
		$this->loadViews('admin/draftPostsMessages', $data);
	}
	
	public function draftPosts_setDraftUser() {
		$this->setDraftUser();
	}
	
	public function setDraftUser() {
		if ($this->initialiseAdmin() === false) {
			return;
		}
		$this->load->model('user');
		$this->load->model('group');

		if ($this->input->post()) {
			
			if($this->input->post('submit') === 'cancel') {
				$data["action"] = 'index';
				$this->loadViews('admin/setDraftUser', $data);
			} else if($this->input->post('submit') === 'set') {
				
				if($this->input->post('userId')) {
					$this->user->updateUserType($this->input->post('userId'), 1);
					$data["action"] = 'userSet';
					$data["setUserId"] = $this->input->post('userId');
					$this->loadViews('admin/setDraftUser', $data);
				} else {
					$data["action"] = 'index';
					$this->loadViews('admin/setDraftUser', $data);
				}
				
			} else if($this->input->post('submit') === 'unset') {
				
				if($this->input->post('userId')) {
					$this->user->updateUserType($this->input->post('userId'), 0);
					$data["action"] = 'userUnset';
					$data["setUserId"] = $this->input->post('userId');
					$this->loadViews('admin/setDraftUser', $data);
				} else {
					$data["action"] = 'index';
					$this->loadViews('admin/setDraftUser', $data);
				}
				
			}
			
		} else {

			$this->load->model('group');
			$this->load->model('user');

			$data["userId"] = $this->user->authCheck();
			$data["isAdmin"] = true;
			$data["action"] = 'index';
			$data["forums"] = $this->group->getAdminDraftForumsNamesAndIds();
			$data["draftMetaUsers"] = $this->group->getDraftMetaUsers();
			$data["draftUsers"] = $this->group->getDraftUsers();
			$data["draftPriorityDates"] = $this->group->getDraftPriorityDates();
			$this->loadViews('admin/setDraftUser', $data);
		}
	}
	
	
	public function draftPosts_mapDraftUser() {
		$this->mapDraftUser();
	}
	
	public function mapDraftUser() {
		if ($this->initialiseAdmin() === false) {
			return;
		}
		$this->load->model('user');
		$this->load->model('group');

		if ($this->input->post()) {
			
			if($this->input->post('submit') === 'cancel') {
				$data["action"] = 'index';
				$this->loadViews('admin/mapDraftUser', $data);
			} else if($this->input->post('submit') === 'set') {
				
				if($this->input->post('metaUserId') && $this->input->post('draftUserId')) {
					$this->group->mapDraftUserToMetaUser($this->input->post('metaUserId'), $this->input->post('draftUserId'));
					$data["action"] = 'userSet';
					$data["metaUserId"] = $this->input->post('metaUserId');
					$data["draftUserId"] = $this->input->post('draftUserId');
					$this->loadViews('admin/mapDraftUser', $data);
				} else {
					$data["action"] = 'index';
					$this->loadViews('admin/mapDraftUser', $data);
				}
				
			} else if($this->input->post('submit') === 'unset') {
				
				if($this->input->post('userId')) {
					$this->user->updateUserType($this->input->post('userId'), 0);
					$data["action"] = 'userUnset';
					$data["setUserId"] = $this->input->post('userId');
					$this->loadViews('admin/mapDraftUser', $data);
				} else {
					$data["action"] = 'index';
					$this->loadViews('admin/mapDraftUser', $data);
				}
				
			}
			
		} else {

			$this->load->model('group');
			$this->load->model('user');

			$data["userId"] = $this->user->authCheck();
			$data["isAdmin"] = true;
			$data["action"] = 'index';
			$data["forums"] = $this->group->getAdminDraftForumsNamesAndIds();
			$data["draftMetaUsers"] = $this->group->getDraftMetaUsers();
			$data["draftUsers"] = $this->group->getDraftUsers();
			$data["draftPriorityDates"] = $this->group->getDraftPriorityDates();
			$this->loadViews('admin/mapDraftUser', $data);
		}
	}

	
	/* end post admin tool */
	
	
	public function statistics() {
		redirect('/admin/statistics-forum');
	}
	
	/**
	 * Handles forum post statistics.
	 */
	public function statistics_forumposts() {
		$this->getStatistics('forumPosts');
	}
	/**
	 * Handles forum membership statistics.
	 */
	public function statistics_forummembers() {
		$this->getStatistics('forumMembers', false);
	}
	/**
	 * Handles member signup statistics.
	 */
	public function statistics_memberjoins() {
		$this->getStatistics('memberJoins');
	}
	/**
	 * Handles member login statistics.
	 */
	public function statistics_memberlogins() {
		$this->getStatistics('memberLogins');
	}
	/**
	 * Handles member count statistics.
	 */
	public function statistics_membercounts() {
		$this->getStatistics('memberCounts');
	}
	/**
	 * Handles general statistics.
	 */
	public function statistics_general() {
		$this->getStatistics('general');
	}
	
	/**
	 * Handles retrieval of statistics and statistical output for any type.
	 * @param string $type
	 */
	private function getStatistics($type, $includeYearly = true) {
		if($this->initialiseAdmin() === false) {
			return;
		}
		$this->load->model('statistics');
		
		$data = [];
		$data["type"] = $type;
		$data['includeYearly'] = $includeYearly;
		$getter = 'get'.ucfirst($type).'Statistics';
		
		// validate year and month
		if($this->input->post('stats_year') || $this->input->post('stats_month')) {
			$this->load->library('form_validation');
				
			$this->form_validation->set_rules('stats_year', 'year', 'required|integer');
			$this->form_validation->set_rules('stats_month', 'month', 'required|integer');
			
			if ($this->form_validation->run() === FALSE) {
				$data["error"] = validation_errors();
				$data["error"] .= 'Data is shown based on other options or the current month and year.';
			} else {
				$year = $this->input->post('stats_year');
				$month = $this->input->post('stats_month');
			}
		}
		// alltime checked overrides all other options
		if($this->input->post('stats_allTime')) {
			$data["stats"] = $this->statistics->$getter();
		} else {
			if(!isset($year)) {
				$year = date('Y');
			}
			if(!isset($month)) {
				$month = date('m');
			}
			$data["stats"] = $this->statistics->$getter($year, $this->input->post('stats_wholeYear') ? NULL : $month);
		}
		// send back current options
		if(isset($month)) {
			$data["month"] = $month;
		}
		if(isset($year)) {
			$data["year"] = $year;
		}
		if($this->input->post('stats_wholeYear')) {
			$data['wholeYear'] = true;
		}
		if($this->input->post('stats_allTime')) {
			$data['allTime'] = true;
		}
		$this->loadViews('admin/adminStatistics', $data, 'statistics-' . $type);
	}
	
	public function users() {
		redirect('/admin/users-view');
	}
	
	/**
	 * Handles searching for users, displaying found results and details for the selected user.
	 * @param int $userId
	 */
	
	public function users_view($userId = NULL) {
		if($this->initialiseAdmin() === false) {
			return;
		}
		$this->load->model('file');
		$this->load->model('group');
		$data = [];
		if(is_null($userId)) {
			if($this->input->post('submit') === 'search' && ($this->input->post('search_string') || $this->input->post('search_usertype'))) {
				if($this->input->post('search_string') && $this->input->post('search_usertype')) {
					$data["error"] = "You can search by either name/email or user type, not both.";
				} else {
					if($this->input->post('search_string')) {
						$data["users"] = $this->user->search($this->input->post('search_string'));
						$data['search_string'] = $this->input->post('search_string');
					} else if ($this->input->post('search_usertype')) {
						$userType = $this->input->post('search_usertype');
						$data['search_usertype'] = $this->input->post('search_usertype');
						if ($userType === 'standard') {
							$data['users'] = $this->user->findStandardUsers();
						} else if($userType === 'moderator') {
							$data["users"] = $this->user->findRamblurModerators();
						} else if ($userType === 'supervisor') {
							$data["users"] = $this->user->findSupervisors();
						} else if ($userType === 'manager') {
							$data["users"] = $this->user->findManagers();
						} else if ($userType === 'admin') {
							$data["users"] = $this->user->findAdministrators();
						} else {
							$data["error"] = 'The user type is not supported.';
						}
					} else {
						$data["error"] = 'Please search by either name/email or user type.';
					}
				}
			}
		} else {
			$this->load->model('jira_issue', 'issue');
			$data["user"] = [];
			$data["user"]["info"] = $this->user->info($userId, true);
			$data["user"]["followedSubjects"] = $this->user->getFollowedSubjects($userId);
			$bannedSubjects = $this->user->getBannedSubjects($userId);
			if($bannedSubjects) {
				$followedCount = count($data['user']['followedSubjects']);
				for($i = 0; $i < $followedCount; $i++) {
					$banned = false;
					foreach($bannedSubjects as $b) {
						if($b['id'] == $data['user']['followedSubjects'][$i]['id']) {
							$banned = true;
							$data['user']['followedSubjects'][$i]['ban'] = $b['bannedUntil'];
							break;
						}
					}
				}
			}
			$data["user"]["suggestedSubjects"] = $this->user->getSuggestedSubjects($userId);
			$data["user"]["issues"] = $this->issue->getIssuesByUser($userId);
			if($data["user"]["info"]["level"] === 'moderator') {
				$data["user"]["moderatorSubjects"] = $this->user->getModeratorSubjects($userId);
			}
			$data["user"]["moderatorProfile"] = $this->user->getModeratorProfile($userId);
			$componentData = [];
			$componentData["user"] = $this->user->info($userId);
			if($this->input->post('submit') === 'edit') {
				$componentData["saveHandler"] = 'admin/users-edit/'.$userId;
				$componentData['completePercentage'] = $this->user->getProgressbarPercentage($componentData["user"]["properties"]);
				$data["user"]["userEditView"] = $this->load->view('components/editableUserProfile', $componentData, TRUE);
			}
			if($this->input->post('search_string')) {
				$data['search_string'] = $this->input->post('search_string');
			} else if ($this->input->post('search_usertype')) {
				$data['search_usertype'] = $this->input->post('search_usertype');
			}
		}
		$this->loadViews('admin/adminUsers', $data);
	}
	
	/**
	 * Handles submission of a form to edit user details.
	 * @param int $userId
	 */
	// Probably better to change to /api call, rather than form submission and page reload. 
	// Same could be said for all actions on admin pages
	public function users_edit($userId) {
		if($this->initialiseAdmin() === false) {
			return;
		}
		$this->load->model('file');
		if($this->input->post('submit')) {
			$action = $this->input->post('submit');
			if($action == 'save') {
				if ($this->input->post('updateType') === 'profile') {
				
					$this->load->library('form_validation');
					$this->form_validation->set_rules('store_firstName', 'First Name', 'trim|min_length[2]|max_length[128]');
					$this->form_validation->set_rules('store_lastName', 'Last Name', 'trim|min_length[2]|max_length[128]');
					$this->form_validation->set_rules('store_skype', 'Skype', 'trim');
					$this->form_validation->set_rules('store_facetime', 'Facetime', 'trim');
					$this->form_validation->set_rules('store_postcode', 'Postcode', 'trim|alpha_numeric|min_length[2]|max_length[20]');
					$this->form_validation->set_rules('store_mobile', 'Mobile', 'trim|numeric|regex_match[/^[0-9]{10}$/]');
				
					if ($this->form_validation->run() === FALSE) {
						$componentData["error"] = '<span class="sttsErrorTxt">'.validation_errors().'</span>';
					} else {
						$profileData = [];
							
						$storeVars = array();
						$postVars = $this->input->post(NULL, TRUE);
						foreach($postVars AS $k => $v) {
							// if we're meant to store it
							if (mb_substr($k, 0, 6) == 'store_') {
								$storeVars[mb_substr($k, 6)] = $v;
							}
						}
				
						// if there are any left to store
						if (count($storeVars) > 0) {
							$success = $this->user->updateUserInfo($userId, $storeVars);
						}
						if ($success) {
							$componentData['message'] = '<span class="sttsSuccessTxt"><i class="fa fa-thumbs-up fa-lg"></i>The details were successfully updated</span>';
						} else {
							$componentData["error"] = '<span class="sttsErrorTxt">The details could not be updated. Try again.</span>';
						}
					}
				} else if ($this->input->post('updateType') === 'avatar') {
					if (isset($_FILES["store_avatar"]) && $_FILES["store_avatar"]["error"] <= 0) {
						// upload the avatar
						$error = false;
						$result = $this->user->uploadAvatar($userId, $_FILES["store_avatar"]["tmp_name"], $error);
							
						if ($error !== false) {
							$componentData["avatarError"] = '<span class="sttsErrorTxt">'.$error.' Try again.</span>';
						} else {
							$componentData['avatarMessage'] = '<span class="sttsSuccessTxt"><i class="fa fa-thumbs-up fa-lg"></i>The new avatar was successfully updated</span>';
						}
					}
				}
				
			} else if ($action == 'deactivate' || $action == 'activate') {
				if($this->input->post('subject_id')) {
					$this->user->approveModeratorSubject($userId, $this->input->post('subject_id'), $action == 'activate');
				}
			}
		}
		$this->users_view($userId);
	}
	
	public function errors() {
		redirect('/admin/errors-view');
	}
	
	/**
	 * Handles retrieval of system issues reported by users.
	 */
	public function errors_view() {
		if($this->initialiseAdmin() === false) {
			return;
		}
		$data = [];
		$this->load->model('jira_issue', 'issue');
		$data['reportedErrors'] = $this->issue->getAllIssues();
		
		$this->loadViews('admin/adminErrors', $data);
	}
	
	protected function getTabData($userId) {
		$data = [];
		
		$data["tool"] = "admin";
		$data['user'] = $userId;
		
		// set up which tabs to use
		$data["tabs"] = [];
		$data["tabs"][] = [
			"name" => "subjects",
			"label" => "MANAGE FORUMS",
			"subTabs" => [
				["name" => "approve","label" => "SUGGESTED"],
				["name" => "manage","label" => "MODERATORS"],
				["name" => "applications", "label" => "APPLICATIONS"]
				// wallpapers
				// create
				// delete
				// move
				// public/private etc
			]
		];
		$data["tabs"][] =[
			"name" => "alerts",
			"label" => "GLOBAL MESSAGES",
			"subTabs" => [
				["name" => "create","label" => "CREATE GLOBAL MESSAGE"]
			]
		];
		$data["tabs"][] = [
			"name" => "statistics",
			"label" => "STATISTICS",
			"subTabs" => [
				["name" => "forumPosts","label" => "FORUM POSTS"],
				["name" => "forumMembers","label" => "FORUM MEMBERS"],
				["name" => "memberJoins","label" => "MEMBER SIGNUPS"],
				["name" => "memberLogins","label" => "MEMBER LOGINS"],
				["name" => "memberCounts","label" => "MEMBER COUNTS"],
				["name" => "general","label" => "GENERAL"]
			]
		];
		$data["tabs"][] = [
			"name" => "users",
			"label" => "MEMBER PROFILES",
			"subTabs" => [
				["name" => "view","label" => "VIEW MEMBER PROFILE"]
			]
		];
		$data["tabs"][] =[
			"name" => "errors",
			"label" => "REPORTED ERRORS",
			"subTabs" => [
				["name" => "view","label" => "VIEW REPORTED ERRORS"]
				// separate into advertising errors and other system errors
			]
		];
		
		/*$data["tabs"][] =[
			"name" => "landingpageposts",
			"label" => "LANDING PAGE POSTS",
			"subTabs" => [
				["name" => "postblacklist", "label" => "BLACKLIST POST"],
				["name" => "forumblacklist", "label" => "BLACKLIST FORUM"],
				["name" => "userblacklist", "label" => "BLACKLIST USER"],
				["name" => "postwhitelist", "label" => "WHITELIST POST"],
				["name" => "forumwhitelist", "label" => "WHITELIST FORUM"]
			]
		];*/
		
		$data["tabs"][] =[
			"name" => "draftPosts",
			"label" => "APPROVAL",
			"subTabs" => [
				["name" => "draftPosts","label" => "DRAFT POSTS"],
				["name" => "setDraftUser","label" => "SET DRAFT USERS"],
				["name" => "mapDraftUser","label" => "MAP DRAFT USERS"]
                               
			]
		];
		
		/*
		 $data["tabs"][] =[
			"name" => "deleteContent",
			"label" => "DELETE CONTENT",
			"subTabs" => [
				["name" => "forums","label" => "DELETE FORUMS"]
				
			]
		];
		 */
		$data["tabs"][] = [
			"name" => "posts",
			"label" => "POSTS",
			"subTabs" => [
				["name"=>"summary", "label"=>"POSTS"]
			]
		];
		
		$data["tabs"][] = [
			"name" => "settings",
			"label" => "SETTINGS",
			"subTabs" => [
				["name" => "settings","label" => "SETTINGS", "redirect" => base_url('settings')]
			]
		];
		return $data;
	}
	
	protected $viewToTabMap = [
		'admin/adminAlerts' => 'alerts-create',
		'admin/adminErrors' => 'errors-view',
		//'admin/adminStatistics' => 'statistics',
		//'admin/adminSubjects' => 'subjects',
		'admin/adminUsers' => 'users-view',
		'admin/landingPagePosts' => 'landingpageposts-postblacklist',
		'admin/draftPosts' => 'draftPosts-draftPosts',
		'admin/draftPostsOverview' => 'draftPosts-draftPosts',
		'admin/setDraftUser' => 'draftPosts-setDraftUser',
		'admin/mapDraftUser' => 'draftPosts-mapDraftUser',
		'admin/draftPostMessages' => 'draftPosts-draftPosts',
		'admin/postSummary' => 'posts-summary'
	];
}
