<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class ForumRules extends CI_Controller {

    public function index() {

        $head['referrer'] = 'forumRules';
        $this->load->model('user');
        $head['loggedIn'] = $this->user->authCheck() !== false;
		
		$data["moderator"] = $this->input->get('mod', TRUE);

        $this->load->view('generic-header', $head);

        $this->load->view('infoFeed/forumRules', $data);

        $this->load->view('generic-footer');        

    } 


}
