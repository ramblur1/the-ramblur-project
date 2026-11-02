<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Dashboard extends CI_Controller {

	public function index() {
		$this->load->model('globals');
		$this->load->model('user');
		$data = array();
		
		$this->load->view('dashboard');

	}
	
	
	// @todo - provide support for /dashboard/surfing-forum/725 URL style??
	public function _remap($forumId) {
		$forumIdStore = $this->input->get('fId');
		$this->load->model('group');
		
		$this->load->model('user');
		
		// check the authentication
		$user = $this->user->authCheck();
		
		// If user has clicked a link, and had to log in, redirect to original link
		if ($user && $forumId === 'index' && $forumIdStore) {
			redirect('/dashboard/'.$forumIdStore);
			return;
		}
		
		// Simple way to check that forum exists
		$forum = false;
		if ($forumId !== 'index') {
			$forum = $this->group->getName($forumId);
		}

		if($forum) {
			$canAccess = false;
			$accessCheck = $this->db->query('CALL checkUser(?, ?, @outParam, true)', [$forumId, $user ? $user : null]);
			if($accessCheck->result_array()[0]['valid']) {
				// user must also be following
				if ($this->user->isForumSubscribed($forumId)) {
					$this->load->view('dashboard', ['subjectId' => $forumId]);
				} else {
					redirect('/dashboard');
				}
			} else {
				redirect('/dashboard'.($forumIdStore ? '?fId='.$forumIdStore : ''));
			}
		} else {
			// creating
			if($forumId === 'index') {
				$this->index();
			} else {
				redirect('/dashboard');
			}
		}
	}
}