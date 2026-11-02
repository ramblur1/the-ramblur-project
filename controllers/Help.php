<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Help extends CI_Controller {

    public function index() {

        $head['referrer'] = 'mainMenuHelp';
		$data["showNavigationBtns"] = true;
        $this->load->model('user');
        $head['loggedIn'] = $this->user->authCheck() !== false;
		
		$data["moderator"] = $this->input->get('mod', TRUE);

        $this->load->view('generic-header', $head);

        $this->load->view('help/help', $data);

        $this->load->view('generic-footer');        

    } 


}
