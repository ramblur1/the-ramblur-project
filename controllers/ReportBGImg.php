<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class ReportBGImg extends CI_Controller {

    public function index() {

        $head['referrer'] = 'reportBGImg';
		$data["showNavigationBtns"] = true;
        $this->load->model('user');
        $head['loggedIn'] = $this->user->authCheck() !== false;
		
		$data["moderator"] = $this->input->get('mod', TRUE);

        $this->load->view('generic-header', $head);

        $this->load->view('forumNavBar/reportBGImg', $data);

        $this->load->view('generic-footer');        

    } 


}
