<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Forum_invitation extends CI_Controller {

	public function index() {
		$this->load->model('user');
		$this->load->model('group');
		$userId = $this->user->authCheck();
		
		$data = ['loggedIn' => $userId];
		
		// accepting invitation
		if ($this->input->post()) {
			$invite = $this->getInvite($this->input->post('submit'));
			if (!$invite) {
				$data['message'] = 'The forum invitation could not be found.';
			} else {
				$user = $this->user->info($userId, 'true');
				$forumName = $this->group->getName($invite['subjectId']);
				if ($user['email'] !== $invite['email'] && $userId !== $invite['userId']) {
					$data['message'] = 'The forum invitation is invalid.';
				} else if ($invite['result'] !== null) {
					$data['message'] = 'You have already responded to this invitation.';
				} else {
					// Store the result
					$this->db->update('SubjectInvite', ['result' => group::SUBJECTINVITE_ACCEPT], ['id' => $invite['id']]);
					
					$isPrivate = $this->group->getForumPrivate($invite['subjectId']);
					if ($this->isUserFollowingInvitedForum($userId, $invite['subjectId'], $isPrivate)) {
						$data['message'] = 'You are already following the forum \''.$forumName.'\'.';
					} else {
						// add user to forum
						$this->load->model('Subject');
						$this->Subject->follow_subject($invite['subjectId'], $userId, 0, 1);
						if ($isPrivate) {
							$this->db->update('UserToSubject', ['privateAccess' => 1], ['userId' => $userId, 'subjectId' => $invite['subjectId']]);
						}
						//if ($this->db->affected_rows() > 0) { // <<<<< doesn't work with stored procedures, so can't really tell if it was successful.
						// Maybe query the db UserToSUbject table for a proper check
							//$data['message'] = 'You have successfully joined the forum \''.$forumName.'\'';
							$url = '/';
							if ($isPrivate) {
								$url .= 'private/';
							}
							$url .= urlencode(str_replace(' ', '-', $forumName)).'-forum/'.$invite['subjectId'];
							redirect($url);
					//	} else {
					//		$data['message'] = 'Something went wrong. Please try again, or ask the forum manager for another invitation.';
					//	}
					}
				}
			}
		// using the invitation link
		} else {
			// can't be here without a token
			if (!$this->input->get('invite')) {
				redirect();
			}
			
			$invite = $this->getInvite($this->input->get('invite'));
			if (!$invite) {
				$data['message'] = 'We could not find this invitation in our records. Please request another from the forum manager.';
			} else {
				$data['forumName'] = $this->group->getName($invite['subjectId']);
				
				if ($userId) {
					if ($this->isUserFollowingInvitedForum($userId, $invite['subjectId'], $this->group->getForumPrivate($invite['subjectId']))) {
						// Store the result
						$this->db->update('SubjectInvite', ['result' => group::SUBJECTINVITE_INVALID], ['id' => $invite['id']]);
						
						$data['message'] = 'You are already following the forum \''.$data['forumName'].'\'.';
					} else if ($invite['result'] !== null) {
						$data['message'] = 'You have already responded to this invitation.';
					} else {
						$user = $this->user->info($userId, 'true');
					
						if ($user['email'] !== $invite['email']) {
							$data['message'] = 'This forum invitation was not sent to you. Please request an invitation for yourself from the manager of \''.$data['forumName'].'\'.';
						} else {
							$data['token'] = $invite['token'];
						}
					}
				}
			}
		}
		$this->load->view('forumInvitation', $data);
	}
	private function getInvite($token) {
		$this->db->from('SubjectInvite')
			->where([
				'token' => $token
			]);
		$invite = $this->db->get();
		if ($invite->num_rows() === 0) {
			return false;
		} else {
			return $invite->first_row('array');
		}
	}
	
	private function isUserFollowingInvitedForum($userId, $subjectId, $private) {
		$this->db->select('userId')
			->from('UserToSubject')
			->where([
				'userId' => $userId,
				'subjectId' => $subjectId
			]);
		if ($private) {
			$this->db->where('privateAccess', 1);
		}
		return $this->db->get()->num_rows() > 0;
	}
}