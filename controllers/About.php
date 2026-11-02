<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class About extends CI_Controller {

    public function index() {

        $head['referrer'] = 'about';
        $this->load->model('user');
        $head['loggedIn'] = $this->user->authCheck() !== false;

        $this->load->view('generic-header', $head);

        $this->load->view('about');

        $this->load->view('generic-footer');        

    } 


}
