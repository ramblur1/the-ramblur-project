<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Recover extends CI_Controller {

    public function index() {
    	
    	$this->load->model('user');
    	
    	// check the authentication
    	$user = $this->user->authCheck();
    	if($user) {
    		redirect('/');
    		return;
    	}

        $head['referrer'] = 'recover';
		$head['loggedIn'] = false;
        $data = array();
        $data['sent'] = 0;

        $this->load->view('generic-header', $head);

        // we're having a go
        if(!empty($_POST)) {


            $this->load->model('user');
            $this->load->model('globals');

            // load up some shit, yo
            $this->load->helper(array('form', 'url'));
            $this->load->library('form_validation');

            // don't fill in both email fields
            if(!empty($_POST['neEmail'])&&!empty($_POST['email'])) {

                $data['sent'] = 6;
                $this->load->view('recover', $data);

            } else {

                // set validation rules depending on which recovery feature form is being used
                if(!empty($_POST['neEmail'])) {

                    $this->form_validation->set_rules('neEmail', 'New Email', 'trim|required|valid_email|callback_existing_email_check');
                    $this->form_validation->set_rules('neUsername', 'Username', 'trim|required');
                    $this->form_validation->set_rules('nePwd', 'Password', 'trim|required');
                    $recact = $_POST['neRecact'];

                } else {

                    $recact = (isset($_POST['recact'])) ? $_POST['recact'] : '';
                    if($recact==='rve') {
                        $this->form_validation->set_rules('email', 'Email', 'trim|required|valid_email|callback_email_check|callback_already_verified_check');
                    } else {
                        $this->form_validation->set_rules('email', 'Email', 'trim|required|valid_email|callback_email_check|callback_verified_check');
                    }

                }

                // bad form, naughty form
                if ($this->form_validation->run() === false) {
                    $this->load->view('recover', $data);
                // good form!
                // send the recovery email/link
                } else {

                    switch($recact) {

                        case "spr":

                            if($this->user->sendPasswordReset($_POST['email'])) {
                                
                                $data['sent'] = 1;

                            } else $data['sent'] = 2;

                            break;

                        case "sur":

                            if($this->user->sendUsernameReminder($_POST['email'])) {
                                
                                $data['sent'] = 3;

                            } else $data['sent'] = 2;

                            break;

                        case "rve":

                            if($this->user->resendVerificationEmail($_POST['email'])) {
                                
                                $data['sent'] = 4;

                            } else $data['sent'] = 5;

                            break;                        

                        case "nea":

                            // email is good, check un & pwd
                            // if  un/pwd good, change email, change verCode and resend email

                            if($user = $this->user->verifyUser($_POST['neUsername'], $_POST['nePwd'])) {

                                if($user==1) {

                                    $data['sent'] = 10; // 1 = verified (user already verified, they need to login!)

                                } else {

                                    if($this->user->updateEmailAddress($_POST['neEmail'], $user)) {
                                        
                                        $data['sent'] = 7; // all good

                                    } else $data['sent'] = 9; // email update failed
                                    
                                }

                            } else $data['sent'] = 8; // "login" details failed

                            break;  

                    }

                    $this->load->view('recover', $data);
                }

            }

        // initial page load
        } else $this->load->view('recover', $data);

        $this->load->view('generic-footer');        

    }

    public function email_check($email) {

        $this->db->select("userId")->from('User')->where('email', $email);
        $query = $this->db->get();

        if ($query->num_rows()===0) {
            $this->form_validation->set_message('email_check', 'This email is not registered with ramblur');
            return false;
        }
        return true;

    }

    public function existing_email_check($email) {

        $this->db->select("userId")->from('User')->where('email', $email);
        $query = $this->db->get();

        if ($query->num_rows()>0) {
            $this->form_validation->set_message('existing_email_check', 'This email is already registered with ramblur!');
            return false;
        }
        return true;

    }

    public function already_verified_check($email) {

        $this->db->select('Permissions.verified')->from('Permissions')->join('User', 'Permissions.userId = User.userId')->where('email', $email);
        $query = $this->db->get();
        $row = $query->row();

        if (intval($row->verified)===1) {
            $this->form_validation->set_message('already_verified_check', 'This account has already been verified!');
            return false;
        }
        return true;

    }    

    public function verified_check($email) {

        $this->db->select('Permissions.verified, User.verificationCode')->from('Permissions')->join('User', 'Permissions.userId = User.userId')->where('email', $email);
        $query = $this->db->get();
        $row = $query->row();

        if (intval($row->verified)===0) {
            $this->form_validation->set_message('verified_check', 'This account is not yet verified! A verification link reminder email has been sent.');

            $this->load->model('user');
            $this->load->model('globals');			
			$this->load->model('email');
			$this->email->sendVerificationReminder($email, $row->verificationCode) ;
            return false;
        }
        return true;

    }    

}
