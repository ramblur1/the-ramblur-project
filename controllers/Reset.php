<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Reset extends CI_Controller {

    const INVALID_MSG = 'Reset a forgotten password';

    public function index() {
    	
    	// check the authentication
    	$this->load->model('user');
    	$user = $this->user->authCheck();
    	if($user) {
    		redirect('/');
    		return;
    	}

        $head['referrer'] = 'reset';
		$head['loggedIn'] = false;
        $data = array();
        $data['invalid'] = self::INVALID_MSG;
        $this->load->view('generic-header', $head);
        $this->load->view('reset', $data);
        $this->load->view('generic-footer');     

    }

    public function c() {
    	
    	// check the authentication
    	$this->load->model('user');
    	$user = $this->user->authCheck();
    	if($user) {
    		redirect('/');
    		return;
    	}

        $head['referrer'] = 'reset';

        $data = array();
        $data['reset'] = 0;
        $data['code'] = (isset($_POST)&&isset($_POST['reset_code'])) ? $_POST['reset_code'] : $this->uri->segment(3);

        $this->load->view('generic-header', $head);

        $this->load->helper(array('form', 'url'));
        $this->load->library('form_validation');        
        $this->load->model('user');

        //print_r($data);

        // we're having a go
        if(!empty($_POST)) {

            $this->form_validation->set_rules('email', 'Email', 'trim|required|callback_valid_code_email_check');
            $this->form_validation->set_rules('password', 'New Password', 'trim|min_length[6]|alpha_dash|required|callback_password_check');
            $this->form_validation->set_rules('password2', 'New Password confirmation', 'trim|min_length[6]|alpha_dash|required');

            // bad form, naughty form
            if ($this->form_validation->run() === false) {
                $this->load->view('reset', $data);
            // good form!
            } else {

                // update password
                $data['reset'] = ($this->user->resetPassword($_POST['email'], $_POST['reset_code'], $_POST['password'])) ? 1 : 2;

                $this->load->view('reset', $data);

            }       

        } elseif (empty($data['code']) || $data['code'] == '' || !$this->user->resetCodeExists($data['code'])) {

            $data['invalid'] = self::INVALID_MSG;
            $this->load->view('reset', $data);

        // initial page load
        } else $this->load->view('reset', $data);

        $this->load->view('generic-footer');       

    }

    public function valid_code_email_check ($email) {

        $array = array('email' => $email, 'verificationCode' => $_POST['reset_code']);

        $this->db->select("*")->from('User')->where($array);
        $query = $this->db->get();

        if ($query->num_rows()!==1) {
            $this->form_validation->set_message('valid_code_email_check', 'This email does not match your verification code');
            return false;
        }
        return true;

    }

    public function password_check () {

        if ($_POST['password'] !== $_POST['password2']) {
            $this->form_validation->set_message('password_check', 'New passwords do not match');
            return false;
        }
        return true;

    }

}
