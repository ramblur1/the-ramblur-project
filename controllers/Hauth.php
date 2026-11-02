<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/* @property hybridauthlib $hybridauthlib */
class HAuth extends CI_Controller {

	public function __construct()
	{
		// Constructor to auto-load HybridAuthLib
		parent::__construct();
		ini_set('session.cookie_domain', $this->config->item('cookie_domain'));
		$this->load->library('HybridAuthLib');
		$this->load->model('user');
	}

	public function index()
	{
		// Send to the view all permitted services as a user profile if authenticated
		$login_data['providers'] = $this->hybridauthlib->getProviders();
		foreach($login_data['providers'] as $provider=>$d) {
			if ($d['connected'] == 1) {
				$login_data['providers'][$provider]['user_profile'] = $this->hybridauthlib->authenticate($provider)->getUserProfile();
			}
		}
		//$this->load->view('settings/socialLogin', $login_data);
		header('Location: ' . $this->config->base_url());
	}

	public function login()
	{
		$provider = $_GET["provider"];
		log_message('debug', "controllers.HAuth.login($provider) called");
		try
		{  
			log_message('debug', 'controllers.HAuth.login: loading HybridAuthLib');
			$this->load->library('HybridAuthLib');
			if ($this->hybridauthlib->providerEnabled($provider))
			{// check if Facebook service is enabled or not (or other provider)
				log_message('debug', "controllers.HAuth.login: service $provider enabled, trying to authenticate.");
				// authenticate to Facebook (or other provider)
				if (!isset($service))
				{
					$service = $this->hybridauthlib->authenticate($provider);
				}	
				if ($service->isUserConnected())
				{
					log_message('debug', 'controller.HAuth.login: user authenticated.');
					
					// get the user profile
					$user_profile = $service->getUserProfile();

					$user_exists = $this->user->get_user_by_provider_and_id($provider, $user_profile->identifier);//note:twitter is failing due to identifier
					
					// if user didn't authenticate using the selected provider before,create a new entry on database.
					if(!$user_exists){
			 			$data = array();
	    				// fetch countries from DB
				        $data['countries'] = $this->getCountriesList();

				        //ask for username and country
			 			$this->load->view('socialUserForm',$data);
						//$this->index();
					}else{
						echo "<script type='text/javascript'>";
						echo "window.close();";
						echo "</script>";
					}
				}
				else 
				{// Not authenticated.
					show_error('Cannot authenticate user');
				}
			}
			else // This service is not enabled.
			{
				log_message('error', 'controllers.HAuth.login: This provider is not enabled ('.$provider.')');
				show_404($_SERVER['REQUEST_URI']);
			}
		}
		catch(Exception $e)
		{
			$error = 'Unexpected error';
			switch($e->getCode())
			{
				case 0 : $error = 'Unspecified error.'; break;
				case 1 : $error = 'Hybriauth configuration error.'; break;
				case 2 : $error = 'Provider not properly configured.'; break;
				case 3 : $error = 'Unknown or disabled provider.'; break;
				case 4 : $error = 'Missing provider application credentials.'; break;
				case 5 : log_message('debug', 'controllers.HAuth.login: Authentification failed. The user has canceled the authentication or the provider refused the connection.');
				         //redirect();
				         if (isset($service))
				         {
				         	log_message('debug', 'controllers.HAuth.login: logging out from service.');
				         	$service->logout();
				         }
				         show_error('User has cancelled the authentication or the provider refused the connection.');
				         break;
				case 6 : $error = 'User profile request failed. Most likely the user is not connected to the provider and he should to authenticate again.';
				         break;
				case 7 : $error = 'User not connected to the provider.';
				         break;
			}

			if (isset($service))
			{
				$service->logout();
			}
			log_message('error', 'controllers.HAuth.login: '.$error);
			show_error('Error authenticating user.');
		}
	}

	public function userInsert()
	{	
		try{
			$data = array();
			$data['countries'] = array();
			if(!empty($_POST)) {//if user not provided username/country

	            // load up some shit, yo
	            $this->load->helper(array('form', 'url'));
	            $this->load->library('form_validation');

	            // FORM VALIDATION RULES
	            $this->form_validation->set_rules('username', 'Username', 'trim|required|alpha_numeric|callback_username_check');
	            $this->form_validation->set_rules('country', 'Country', 'required');

	            if ($this->form_validation->run() === FALSE) {
	                $data["error"] = validation_errors();
	    				// fetch countries from DB
			        $data['countries'] = $this->getCountriesList();
			        $data['selectedCountry']=$_POST['country'];
	            } else {
	        		$connectedProviders=$this->hybridauthlib->getConnectedProviders();
					foreach($connectedProviders as $providers => $provider) {
						$service = $this->hybridauthlib->getAdapter($provider);	

						// get the user profile
						$user_profile = $service->getUserProfile();//var_dump($user_profile);die();
						//$service->logout(); 
					}
					log_message('info', 'controllers.HAuth.login: user profile:'.PHP_EOL.print_r($user_profile, TRUE));
					
					if( $user_profile )
					{ // add user information to database
						if($user_profile->birthYear && $user_profile->birthMonth && $user_profile->birthDay)
						{$DOB=$user_profile->birthYear.'-'.$user_profile->birthMonth.'-'.$user_profile->birthDay;}
						else{$DOB=NULL;}

						if(!is_int($user_profile->age)){$user_profile->age=null;}//google provides agerange or min. age only (with plus.login scope)
						
						$hybridauth_session_data = $this->hybridauthlib->getSessionData();
						
						$newUserId	= $this->user->create_new_hybridauth_user(
							$_POST['username'],//$user_profile->displayName,
							$user_profile->phone,
							$user_profile->email,
							$hybridauth_session_data,
							$user_profile->gender,
							$user_profile->firstName,
							$user_profile->lastName,
							$user_profile->age,
							$DOB,
							$_POST['country'],//$user_profile->country,
							$user_profile->region,
							$user_profile->zip,
							$user_profile->photoURL,
							$provider,
							$user_profile->identifier
						);
					}		 		
					echo "<script type='text/javascript'>";
					echo "window.close();";
					echo "</script>";
				}
			}	
			$this->load->view('socialUserForm', $data);	
		}
		catch(Exception $e){
			console.log('error', 'controller.HAuth.userInsert: '.$e);
		}
	}

	public function username_check($str) {
        $this->load->model('user');   
        return $this->user->username_check($str);
    }

    public function getCountriesList()
	{
		$this->db->select("*")->from('Country')->order_by('name', 'ASC');
		return $this->db->get()->result_array();

	}

	public function endpoint()
	{
		log_message('debug', 'controllers.HAuth.endpoint called.');
		log_message('info', 'controllers.HAuth.endpoint: $_REQUEST: '.print_r($_REQUEST, TRUE));
		if ($_SERVER['REQUEST_METHOD'] === 'GET')
		{
			log_message('debug', 'controllers.HAuth.endpoint: the request method is GET, copying REQUEST array into GET array.');
			$_GET = $_REQUEST;
		}

		log_message('debug', 'controllers.HAuth.endpoint: loading the original HybridAuth endpoint script.');
		require_once APPPATH.'third_party/hybridauth/hybridauth/index.php';
	}
}

/* End of file hauth.php */
/* Location: ./application/controllers/hauth.php */
