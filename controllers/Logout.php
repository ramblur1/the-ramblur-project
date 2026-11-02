<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Logout extends CI_Controller {

	public function index() {
		$this->load->model('user');
		$this->load->model('group');
		
		// Get the user so we can retrieve the forums, but no need to check if they are currently authenticated,
		// as there could be cases where the session has timed out, and the cookie should be cleared below.

		$userId = $this->user->authCheck(false);

		$forumPage = '';
		// authCheck might fail eg. the user has just deleted their account, or the session has timed out
		if ($userId) {
			// If user is only following a single forum, they should be redirected to that forum home page, rather than 
			// the ramblur home page
			$forumsFollowed = $this->user->getUserForumIds($userId);
			if (count($forumsFollowed) === 1) {
				$forumPage = urlencode(str_replace(' ', '-', $this->group->getName($forumsFollowed[0]['forumId']))) . '-forum/' . $forumsFollowed[0]['forumId'];
				if ($this->group->getForumPrivate($forumsFollowed[0]['forumId'])) {
					$this->session->thirdparty = true;
					$forumPage = 'private/' . $forumPage;
				}
			}
		}
	
		$unauthResult = $this->user->unauth();
		if (!$unauthResult && ENVIRONMENT === 'development') {
			var_dump('unauth failed. session: ', $this->session);
		}
		
		header('Expires: Tue, 01 Jan 1980 1:00:00 GMT');
		header("Cache-Control: no-store, no-cache, must-revalidate");
		header('Cache-Control: post-check=0, pre-check=0', false);
		header('Pragma: no-cache'); // HTTP 1.0.
		header('Last-Modified: ' . gmdate( 'D, d M Y H:i:s' ) . ' GMT' );
		redirect(base_url($forumPage), 'refresh');
	}
}