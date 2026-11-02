<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

class Advertising extends CI_Controller {

	function index() {
		
		redirect('/advertising/manage');
	}
	
	private function initialiseUser() {
		$this->load->model('user');
		$this->load->model('globals');
	
		// check the authentication
		$user = $this->user->authCheck();
		if($user === false) {
			$this->load->view('register', array("title" => 'Advertising'));
			return false;
		}
		
		//temp 
		//make sure only certain users can get to ads if they know the function names!!!!!
		if($user != 7 && $user != 53 && $user != 62) {
			show_error('ramblur advertising will be coming soon!');
			return; //not needed!
		}
		//end temp
		
		return $user;
	}
	
	private function loadViews($viewName, $data = array()) {
		$this->load->view('advertising/advertising-header');
		$this->load->view($viewName, $data);
		$this->load->view('advertising/advertising-footer');
	}

	/*
	 * main advertising entry function
	 *
	 * ad type can be new, corporate, standard, classified
	 *
	 * @todo remove controller_param concept and replace with ad and cost model
	 */

	public function adCreateMenu($ad_type = '', $mainAdCategory = '', $real_ad_type = '', $adStyle = '') {

		$data = array();
		// check the authentication
		if (!$data["user"] = $this->initialiseUser()) {
			return;
		}
		$this->load->model('ad');
		$this->load->model('globals');
		$this->load->model('file');
		$this->load->model('node');
		$this->load->library('session');
		$this->load->library('browserdetection');
		$this->load->helper(array('htmlpurifier'));

		$obj = new browserdetection();
		$data["browser"] = $obj->detect()->getBrowser();

		$data["adtype"] = $ad_type; //this is new, corp, std or classified, or free
		
		$data["checkAdAdminUser"] = $this->ad->checkAdAdminUser($data["user"]);
		$data["freeAds"] = NULL;
		if ($ad_type === 'free' || $this->input->post('freeAds') === 'freeAds') {
			$data["freeAds"] = "freeAds";
		}
		
		$data["adStage"] = $this->input->post('adStage');
                $data["statusId"] = $this->input->post('statusId');
                if(!$data["joinBonusAdId"] = $this->input->post('joinBonusAdId')) {
                    $data["joinBonusAdId"] = 0;
                }
			
		$data["mainAdCategory"] = $mainAdCategory;
		$data["adStyle"] = $adStyle;
		$data["cost_model"] = $cost_model = $this->input->post('cost_model');

		$data["adselected"] = $this->input->post('adselected'); //this is banner_fix, banner_cpm, sky or thumb

		$data["saved"] = FALSE;
		$data["createAd"] = FALSE;
		$data["popoverTitle"] = NULL;
		$data["popoverText"] = NULL;
		$data["adStatusClassColor"] = NULL;
		$data["popoverAnchor"] = NULL;
		$data["btnClassColor"] = NULL;
		$data["btnStatus"] = NULL;
		
		$data["statusId"] = $this->input->post('statusId');

		$currency_symbol = $this->config->item('currency_symbol');
		$currency_code = $this->config->item('currency_code');
		$data["currency_symbol"] = $currency_symbol;
		$data["currency_code"] = $currency_code;

		$data["credit"] = $this->ad->getAdCredit($data["user"]);
		//jb check this !!
		//	 $this->session->set_userdata('credit', $data["credit"]); //may not need to set this here

		$submit_status = $this->input->post('submit');
		$data["submit_status"] = $submit_status;
		//goes to ad menu 1
		if (!$submit_status || $submit_status === 'create' 
				|| $this->input->post('submitQuote') || $this->input->post('submitCancel')) {
			if (!$submit_status && $ad_type !== 'new') {
				$this->saveQuote($ad_type);
			}
			$data["ad_row_titles"] = $this->ad->getAdRowTitles('screen_1');
			if (empty($data["ad_row_titles"])) {
				log_message('error', 'adCreateMenu(): getAdRowTitles() returned no ad titles.');
				show_error("No active ad titles found");
			}
			$data["ad_types_main"] = $this->ad->getAdTypes();
			if (empty($data["ad_types_main"])) {
				log_message('error', 'adCreateMenu(): getAdTypes() returned no active ad types.');
				show_error("No active ad types found");
			}
			$data["quote"] = '';

			$this->loadViews('adMenuScreen1', $data);
		}

		$data["ad_row_titles"] = $this->ad->getAdRowTitles('screen_2');
		if (empty($data["ad_row_titles"])) {
			log_message('error', 'adCreateMenu(): getAdRowTitles() returned no ad titles.');
			show_error("No active ad titles found");
		}
		$data["ad_dropdowns"] = $this->ad->getAdDropdowns();
		
		// goes to ad menu 2
		if ($submit_status === 'select') {
			$data["ad_row_styles"] = $this->ad->getAdStyles($ad_type, '');
			if (empty($data["ad_row_styles"])) {
				log_message('error', 'adCreateMenu(): getAdStyles() returned no active ad styles.');
				show_error("No active ad styles found");
			}
			$data["selected"] = FALSE;
			$this->loadViews('adMenuScreen2', $data);
		}

		$data["adselected"] = $this->input->post('adselected'); //banner_cpm, banner_fix, sky or thumb
		$data["store_impressions"] = $this->input->post('store_impressions');
		$data["store_clicks"] = $this->input->post('store_clicks');
		$data["store_numbforums"] = $this->input->post('store_numbforums');
		$data["store_international"] = $this->input->post('store_international');
		$data["store_numnations"] = $this->input->post('store_numnations');
		$data["store_numbstates"] = $this->input->post('store_numbstates');
		$data["store_gender"] = $this->input->post('store_gender');
		$data["store_numberages"] = $this->input->post('store_numberages');
		$data["store_numbpostcodes"] = $this->input->post('store_numbpostcodes');
		
		$data["store_title"] = html_purify($this->input->post('store_title'), 'post');
		$data["store_description"] = html_purify($this->input->post('store_description'), 'post');
		if (!$data["forums_selected"] = json_decode($this->input->post('store_forums'))) {
			$data["forums_selected"] = array("All Users");
		}

		$data["international_selected"] = $this->input->post('store_international');

		if (!$data["countries_selected"] = $this->ad->getCountryNames(json_decode($this->input->post('store_countries')))) {
			$data["countries_selected"] = array("None");
		}

		if (!$data["states_selected"] = $this->ad->getStateNames(json_decode($this->input->post('store_states')))) {
			$data["states_selected"] = array("None");
		}

		if (!$data["postcodes_selected"] = $this->ad->getPostcodeNames(json_decode($this->input->post('store_postcodes')))) {
			$data["postcodes_selected"] = array("None");
		}

		if (!$data["agegroups_selected"] = json_decode($this->input->post('store_agegroups'))) {
			$data["agegroups_selected"] = array("All");
		}
		$data["gender_selected"] = $this->input->post('store_gender');

		//Select new main ad type - corporate, standard or classified

		/*		 * ******************** create ad ********************************************* */

		if ($this->input->post('submit') === 'Create Ad') {
                        $this->createAd($data);
                }

		/*		 * ************************* Save ad ******************************************* */

		if ($this->input->post('submit') === 'Save Ad') {

			$data["saved"] = TRUE;
			$data["selected"] = TRUE;

			$ret_array = array();

			$preAdId = $this->input->post('preAdId');
			$cost_model = $this->input->post('cost_model');
			
			// landing page selections
			$landingPageAd = $this->input->post('landingPageAd') === 'on' ? 1 : 0;
			$landingPageHomeFeed = $this->input->post('landingPageHomeFeed') === 'on' ? 1 : 0;
			$landingPageHomeFeedOnly = $this->input->post('landingPageRadio') === 'homeFeedOnly' ? 1 : 0;
			$selectedForums = $this->input->post('landingPageRadio') === 'selectedForums' ? 1 : 0;
			$allForums = $this->input->post('landingPageRadio') === 'allForums' ? 1 : 0;
                        
			$data["store_url"] = html_purify($this->input->post('store_url'), 'url');
			if ($data["store_url"] === 'blacklisted') {
				$data["store_url"] = NULL;
			}
			if (!empty($data["store_url"]) && !$this->file->checkURLWithGoogle($data["store_url"])) {
				$data["store_url"] = 'blacklisted';
			}

			$data["numeric_price"] = $this->ad->getAdPrice($preAdId, $data["user"])->price;
			//$this->->userdata($mainAdCategory . '_' . $real_ad_type . '_' . $cost_model);

			$data["quote"] = $currency_symbol . number_format($data["numeric_price"], 2) . $currency_code;

			$data["element_id_bonus1"] = ''; //$this->session->userdata($mainAdCategory . '_' . $cost_model . '_' . $real_ad_type . '_bonus1');
			$data["element_id_bonus2"] = ''; //$this->session->userdata($mainAdCategory . '_' . $cost_model . '_' . $real_ad_type . '_bonus2');
			//string number_format ( float $number , int $decimals = 0 , string $dec_point = "." , string $thousands_sep = "," )
			$unit_price1 = number_format($data["numeric_price"], 2);

			// $unit_price1 = number_format($this->session->userdata($mainAdCategory . '_' . $real_ad_type . '_' . $cost_model), 2);

			$unit_price = floatval(preg_replace('/[^\d.]/', '', $unit_price1));

			//$data["credit_price"] = $this->session->userdata('credit_to_use' . $preAdId);
			$data["credit_price"] = $this->ad->getAdCreditPrice($preAdId);
			$data["totalPrice"] = $unit_price + $data["credit_price"];
			
			$ret_array = $this->saveAd($preAdId, $mainAdCategory, $data["adselected"], 
            $cost_model, $unit_price, $data["credit_price"], 
			$data["totalPrice"], $landingPageAd, $this->input->post('artwork'), $data["store_url"], $data["joinBonusAdId"]);

			$data["ad_media"] = $ret_array["media"]["ad_media"];
            $data["ad_media_verified"] = $ret_array["media"]["ad_media_verified"];
			$data["ad_media_device"] = $ret_array["media"]["ad_media_device"];
            $data["ad_media_device_verified"] = $ret_array["media"]["ad_media_device_verified"];
			$adId = $data["adId"] = $preAdId;

			$adAdminFreeAd = 0;
			if ($this->input->post('freeAds') === 'freeAds') {
				$adAdminFreeAd = 1;
			}
			$ad_order_params = array(
				'adId' => $data["adId"],
				'company_name' => html_purify($this->input->post('company_name'), 'post'),
				'firstname' => html_purify($this->input->post('firstname'), 'post'),
				'lastname' => html_purify($this->input->post('lastname'), 'post'),
				'email' => html_purify($this->input->post('email'), 'post'),
				'street_no' => html_purify($this->input->post('street_no'), 'post'),
				'street' => html_purify($this->input->post('street'), 'post'),
				'city' => html_purify($this->input->post('city'), 'post'),
				'state' => html_purify($this->input->post('state'), 'post'),
				'postcode' => html_purify($this->input->post('postcode'), 'post'),
				'country' => html_purify($this->input->post('country'), 'post'),
				'phone' => html_purify($this->input->post('phone'), 'post'),
				'web' => html_purify($this->input->post('web'), 'post'),
				'adAdminFreeAd' => $adAdminFreeAd
			);

			$this->ad->saveAdOrder($ad_order_params);
			$data["ad_order"] = $ad_order_params;

			//$data["user"] = $this->node->userInfo($data["user"]);
			$data["ad_row_styles"] = $this->ad->getAdStyles($ad_type, $data["cost_model"], $data["adselected"]);
			if (empty($data["ad_row_styles"])) {
				log_message('error', 'adCreateMenu(): getAdStyles() returned no active ad styles.');
				show_error("No active ad styles found");
			}

			//get all ad details for payment 
			$data["ad"] = $this->ad->getAdDetails($adId, $data["user"]);
			$data["adforums"] = $this->ad->getAdForums($adId, $data["user"]);
			
			$data["private"] = FALSE;
			if($this->ad->isPrivateForum($data["adforums"][0]["subjectName"])) {
				$data["private"] = TRUE;
			}
			
			$data["adinternational"] = $this->ad->getAdInternational($adId, $data["user"]);
			$data["adcountries"] = $this->ad->getAdCountries($adId, $data["user"]);
			$data["adagegroups"] = $this->ad->getAdAgeGroups($adId, $data["user"]);
			$data["adstates"] = $this->ad->getAdStateNames($adId, $data["user"]);
			$data["adpostcodes"] = $this->ad->getAdPostCodeNames($adId, $data["user"]);
			$data["adagegroups"] = $this->ad->getAdAgeGroups($adId, $data["user"]);
			$data["adgender"] = $this->ad->getAdGender($adId, $data["user"]);
			$data["credit"] = $this->ad->getAdCredit($data["user"]);

			$data["ad_order_details"] = $this->ad->getAdOrderDetails($adId);
			$data["bonusAds"] = $this->ad->getPotentialBonusAds($adId);
			$data["ad"] = $data["ad"][0];
			
			if ($data["ad"]["mainAdCategory"] === 'classified') {
				$data["balance_due"] = $data["ad"]["totalPrice"] - $data["credit"];
				if ($data["balance_due"] < 0) {
					$data["balance_due"] = 0;
				}

				$data["new_credit_price"] = $data["ad"]["totalPrice"] - $data["balance_due"];
				$this->ad->setAdPrice($data["user"], $adId, $data["balance_due"], $data["new_credit_price"]);
			}
			
			//check if landing Page Ad and do the necessary
			if ($landingPageAd) {
				$this->ad->insertLandingPageAd($adId, $data["adforums"], $landingPageHomeFeed, $landingPageHomeFeedOnly, $selectedForums, $allForums);
				$data["landingPageAllForums"] = $this->ad->landingPageAllForums($adId, $data["user"]);
				$data["landingPageHomeFeed"] = $this->ad->landingPageHomeFeed($adId, $data["user"]);
				if ($this->ad->landingPageHomeFeed($adId, $data["user"]) && 
						(($data["ad"]["style"] !== 'thumb' && $data["ad"]["style"] !== 'classified') || $this->config->item('thumb_landing_page_ads'))) {
					array_push($data["adforums"], array("subjectName" => "Landing Page Main Feed"));
				}
			}

			//main payment screen
			$data["existing"] = TRUE;
			$data["mainAdPayment"] = TRUE;
			$data["adStage"] = 'payment';
			
			$data['adDetailsPanel'] = $this->load->view('components/ads/adDetailsPanel', $data, TRUE);
			$data['adBonusDetailsPanel'] = $this->load->view('components/ads/adBonusDetailsPanel', $data, TRUE);
			$data['fileInputName'] = 'ad_media';
			$data['preImageText'] = NULL;
			if ($data["ad"]["style"] === 'skyscraper') {
				$data['preImageText'] = 'standard (non-mobile device) '; 
			}
			$data['adImagePanel'] = $this->load->view('components/ads/adImagePanel', $data, TRUE);
			
			if ($data["ad"]["style"] === 'skyscraper') {
				$tempMedia = $data["ad"]["media"];
				$data["ad"]["media"] = $data["ad"]["deviceFileName"];
				$data['fileInputName'] = 'ad_media_device';
				$data['preImageText'] = 'mobile device '; 
				$data['adImagePanel2'] = $this->load->view('components/ads/adImagePanel', $data, TRUE);
				$data["ad"]["media"] = $tempMedia; //set it back to what is was
			}
			
			$data['adBillingDetailsPanel'] = $this->load->view('components/ads/adBillingDetailsPanel', $data, TRUE);
			$data["paymentTitle"] = "Ad Price";
			$data["paymentLogo"] = $this->config->item('MAIN_PAYMENT_LOGO');
                        $data["verification"] = crypt($adId . $data["ad"]["unitPrice"], rand());
			$data['payPalPaymentComponent'] = $this->load->view('components/ads/payPalPaymentComponent', $data, TRUE);
			$this->loadViews('adMenuScreen4', $data);

			/*other payment methods
			//$this->load->view('masterCardApi');
			//$this->load->view('masterCard', $data);
			*/
		}
	}
        
        public function createAd($data) {
			
			// check the authentication
			if (!$this->initialiseUser()) {
				return;
			}
            
            $data["selected"] = TRUE;
			$data["createAd"] = TRUE;

			$data["quote"] = ''; //$currency_symbol . number_format($this->session->userdata($mainAdCategory . '_' . $real_ad_type . '_' . $cost_model), 2) . $currency_code;

			$data["element_id_bonus1"] = ''; //$this->session->userdata($mainAdCategory . '_' . $cost_model . '_' . $real_ad_type . '_bonus1');
			$data["element_id_bonus2"] = ''; //$this->session->userdata($mainAdCategory . '_' . $cost_model . '_' . $real_ad_type . '_bonus2');

			//$groups = array();
			if ($data["freeAds"] === 'freeAds') {
				//get private and public forums
				$data["groups"] = $this->ad->getForums(2);
			} else {
				$data["groups"] = $this->ad->getForums(0);
			}
			//$data["groups"] = $groups;
			$data["ad_row_styles"] = $this->ad->getAdStyles($data["adtype"], $data["cost_model"], $data["adselected"]);
			log_message('error', 'adCreateMenu(): ad->getAdStyles() returned no active ad styles.' . $data["adtype"] . $data["adselected"]);
			if (empty($data["ad_row_styles"])) {
				log_message('error', 'adCreateMenu(): ad->getAdStyles() returned no active ad styles.' . $data["adtype"] . $data["adselected"]);
				show_error("No active ad styles found");
			}

			$query = "CALL addAd(?,?,?);";
			$params = array($data["user"], '', $data["cost_model"]);
			$ret_query = $this->db->query($query, $params);

			if ($ret_query->num_rows() > 0) {
				$query_result = $ret_query->row();
				$adId = $query_result->adId;
				$ret_array["adId"] = $adId;
				$data["preAdId"] = $ret_array["adId"];
			}
							 
			//If you want initial postcode values to be pre-populated, uncomment
			//this line, but will have a screen load delay !!!!!
			//$data["statesAndPostcodes"] = $this->globals->getAllStatesAndPostcodes();
			$data["statesAndPostcodes"] = NULL;
			$data["countries"] = $this->globals->countries();
			$this->loadViews('adMenuScreen3', $data);
		}

	// manage an existing ad
	public function manage($adId = '', $cost_type = '', $paid = '') {

		$this->load->model('ad');
		$this->load->model('group');
		$this->load->model('node');
		$this->load->model('file');

		$data = array();

		if (!$data["user"] = $this->initialiseUser()) {
			return;
		}
		
		$data["checkAdAdminUser"] = $this->ad->checkAdAdminUser($data["user"]);

		$data["paid"] = $paid;
                
		$data["ad_media_verified"] = NULL;
		$data["ad_media_device_verified"] = NULL;
		if ($cost_type === 'virus') {
			$data["cost_type"] = NULL;
			$data["ad_media_verified"] = 'virus';
		} else if ($cost_type === 'virus_device') {
			$data["cost_type"] = NULL;
			$data["ad_media_device_verified"] = 'virus';
		} else {
			$data["cost_type"] = $cost_type;
		}

		$data["btnStatus"] = NULL;
		$data["btnClassColor"] = 'orange'; //get this from config!
		$data["btnActivateStatus"] = NULL;
		$data["btnActivateClassColor"] = 'orange'; 
                $data["freeAds"] = $this->input->post('freeAds'); 
                
                
		// if an ad is selected
		if (($adId !== false ) && ($adId != '' )) {

			$data["existing"] = TRUE;
			$data["ad"] = $this->ad->getAdDetails($adId, $data["user"]);
			//if the post ad has not yet been created, send any node id to the view to prevent errors
			$data["postAdNodeData"] = $this->node->drawableInfo($this->ad->getDummyNodeId());

			if ($data["ad"][0]["type"] === 'post') {
				$data["adforums"] = $this->ad->getAdForums($data["ad"][0]["parentAdId"], $data["user"]);
				$data["post_ad"] = $this->ad->getPostAdDetails(null, $data["ad"][0]["parentAdId"]);
				$data["adinternational"] = $this->ad->getAdInternational($data["ad"][0]["parentAdId"], $data["user"]);				
				$data["adcountries"] = $this->ad->getAdCountries($data["ad"][0]["parentAdId"], $data["user"]);
				$data["adstates"] = $this->ad->getAdStateNames($data["ad"][0]["parentAdId"], $data["user"]);
				$data["adpostcodes"] = $this->ad->getAdPostCodeNames($data["ad"][0]["parentAdId"], $data["user"]);
				$data["adagegroups"] = $this->ad->getAdAgeGroups($data["ad"][0]["parentAdId"], $data["user"]);
				$data["adgender"] = $this->ad->getAdGender($data["ad"][0]["parentAdId"], $data["user"]);
				$data["post_ad_image"] = null;
				if (!empty($data["post_ad"]->postId)) {
					$data["post_ad_image"] = $this->ad->getPostAdImage($data["post_ad"]->postId);
				}
				
				if ($data["ad"][0]["status"] !== 'Free Post Ad - available') {
					$nodeData = array("postId" => $this->ad->getPostId($data["ad"][0]["parentAdId"]),
									  "threadTypeId" => "post",
									  "headNodeId" => $this->ad->getNodeId($this->ad->getPostId($data["ad"][0]["parentAdId"])),
									  "scrollNodeId" => NULL,
									  "bumpViewCount" => FALSE
									  );
					// @todo - is there a way to get zings displayed at once?	
					$data["postAdNodeData"] = $this->group->thread($nodeData);
				   
							
							//$this->node->drawableInfo(
							//$this->ad->getNodeId($this->ad->getPostId($data["ad"][0]["parentAdId"])));
				}
				
			} else {
				$data["adforums"] = $this->ad->getAdForums($adId, $data["user"]);
				if ($this->ad->landingPageHomeFeed($adId, $data["user"]) && 
						(($data["ad"][0]["style"] !== 'thumb' && $data["ad"][0]["style"] !== 'classified') || $this->config->item('thumb_landing_page_ads'))) {
					array_push($data["adforums"], array("subjectName" => "Landing Page Main Feed"));
				}
				$data["landingPageHomeFeed"] = $this->ad->landingPageHomeFeed($adId, $data["user"]);
				$data["landingPageAllForums"] = $this->ad->landingPageAllForums($adId, $data["user"]);
				$data["adinternational"] = $this->ad->getAdInternational($adId, $data["user"]);
				$data["adcountries"] = $this->ad->getAdCountries($adId, $data["user"]);
				$data["adstates"] = $this->ad->getAdStateNames($adId, $data["user"]);
				$data["adpostcodes"] = $this->ad->getAdPostCodeNames($adId, $data["user"]);
				$data["adagegroups"] = $this->ad->getAdAgeGroups($adId, $data["user"]);
				$data["adgender"] = $this->ad->getAdGender($adId, $data["user"]);
			}
			
			$data["private"] = FALSE;
			if($this->ad->isPrivateForum($data["adforums"][0]["subjectName"])) {
				$data["private"] = TRUE;
			}
			
			$data["credit"] = $this->ad->getAdCredit($data["user"]);

			if ($data["ad"][0]["mainAdCategory"] === 'classified' && ($data["ad"][0]["status"] === 'quote' || $data["ad"][0]["status"] === 'Incomplete')) {
				$data["balance_due"] = $data["ad"][0]["totalPrice"] - $data["credit"];
				if ($data["balance_due"] < 0) {
					$data["balance_due"] = 0;
				}
				$data["new_credit_price"] = $data["ad"][0]["totalPrice"] - $data["balance_due"];
				$this->ad->setAdPrice($data["user"], $adId, $data["balance_due"], $data["new_credit_price"]);
			}

			if (!empty($data["ad"][0]["parentAdId"])) {
				$data["ad_order_details"] = $this->ad->getAdOrderDetails($data["ad"][0]["parentAdId"]);
			} else {
				$data["ad_order_details"] = $this->ad->getAdOrderDetails($adId);
			}

			$data["currency_symbol"] = $this->config->item('currency_symbol');
			$data["currency_code"] = $this->config->item('currency_code');

			if (!empty($data["ad"])) {
				$data["ad"] = $data["ad"][0]; //remove array index up 1 level
			}

			if ($this->input->post('paid')) {

				/*
				 * @todo store paid status in table as well!!!!
				 */
				$data["paid"] = TRUE;
			}

			if ($data["ad"]["paid"]) {
				$data["bonusAds"] = $this->ad->getChildBonusAds($adId);
			} else {
				$data["bonusAds"] = $this->ad->getPotentialBonusAds($adId);
			}
			
			$data["adStatusClassColor"] = NULL;
			$data["popoverAnchor"] = NULL;
			$data["popoverTitle"] = NULL;
			$data["popoverText"] = NULL;
			if ($data["ad"]['status'] === 'Finished' || $data["ad"]['status'] === 'Expired' || $data["ad"]['status'] === 'Deleted'
					  || $data["ad"]['modUnderReview'] || $data["ad"]['modPaused'] || $data["ad"]['modRemoved']) { 
				$data["btnStatus"] = 'disabled="disabled"'; 
				$data["btnClassColor"] = "gray"; 
				$data["btnActivateStatus"] = 'disabled="disabled"';
				$data["btnActivateClassColor"] = 'gray'; 
				if ($data["ad"]['modRemoved']) {
					$this->ad->updateCurrentAdState($adId, 10, 0, 'Moderator Disabled Ad');
					$data["ad"]['status'] = 'Moderator Disabled Ad';
					$data["adStatusClassColor"] = 'gray';
					$data["popoverAnchor"] = "popover-anchor";
					$data["popoverTitle"] = "AD REMOVED BY MODERATOR";
					$data["popoverText"] = "This Ad has been removed from the system "
											. "(permanently disabled) due to inappropriate content. Please "
											. "refer to recent messages from the forum moderator for full details.";
				} else if ($data["ad"]['modUnderReview']) {
					$this->ad->updateCurrentAdState($adId, 11, 0, 'Under Moderator Review');
					$data["ad"]['status'] = 'Ad Under Moderator Review';
					$data["adStatusClassColor"] = 'red';
					$data["popoverAnchor"] = "popover-anchor";
					$data["popoverTitle"] = "MODERATOR IS REVIEWING AD";
					$data["popoverText"] = "The forum moderator has paused this Ad as the content is being reviewed. " 
										   . "Once the process has completed you will be notified of the outcome. " 
										   . "Please refer to recent messages from the moderator for full details.";
				} else if ($data["ad"]['modPaused']) {
					$this->ad->updateCurrentAdState($adId, 12, 0, 'Moderator Paused Ad');
					$data["ad"]['status'] = 'Moderator Paused Ad';
					$data["adStatusClassColor"] = 'green';
					$data["popoverAnchor"] = "popover-anchor";
					$data["popoverTitle"] = "AD PAUSED BY MODERATOR";
					$data["popoverText"] = "Your Ad has been reviewed by the forum moderator and you must remove inapropriate content "
											. "if you wish to continue runnning this Ad. " 
											. "You must now update your Ad content and re-activate your Ad. Please refer to recent "
											.  "messages from the moderator for full details.";
				}
				if ($data["ad"]['modPaused'] && !$data["ad"]['modUnderReview'] && !$data["ad"]['modRemoved']) {
					$data["btnStatus"] = NULL;
					$data["btnClassColor"] = 'orange'; 
				}
			} 
			
			//check for main payment screen
			$data["adStage"] = $this->input->post('adStage');
			//load components
			$data['adDetailsPanel'] = $this->load->view('components/ads/adDetailsPanel', $data, TRUE);
			$data['adBonusDetailsPanel'] = $this->load->view('components/ads/adBonusDetailsPanel', $data, TRUE);
			$data['preImageText'] = NULL;
			$data['fileInputName'] = 'ad_media';
			$data['preImageText'] = NULL;
			if ($data["ad"]["style"] === 'skyscraper') {
				$data['preImageText'] = 'standard (non-mobile device) '; 
			}
			$data['adImagePanel'] = $this->load->view('components/ads/adImagePanel', $data, TRUE);
			
			if ($data["ad"]["style"] === 'skyscraper') {
				$tempMedia = $data["ad"]["media"];
				$data["ad"]["media"] = $data["ad"]["deviceFileName"];
				$data['fileInputName'] = 'ad_media_device';
				$data['preImageText'] = 'mobile device '; 
				$data['adImagePanel2'] = $this->load->view('components/ads/adImagePanel', $data, TRUE);
				$data["ad"]["media"] = $tempMedia; //set it back to what is was
			}
			
			$data['adBillingDetailsPanel'] = $this->load->view('components/ads/adBillingDetailsPanel', $data, TRUE);
                        
                        $data["verification"] = crypt($adId . $data["ad"]["unitPrice"], rand());
                        
			if ($data["adStage"] === 'payment') {
				//main payment screen
				$data["adId"] = $adId;
				$data["adtype"] = $data["ad"]["mainAdCategory"];
				$data["adselected"] = $data["ad"]["style"];
				$data["existing"] = TRUE;
				$data["mainAdPayment"] = TRUE;
				$data["submit_status"] = 'Save Ad';
				$data["paymentTitle"] = "Ad Price";
				$data["paymentLogo"] = $this->config->item('MAIN_PAYMENT_LOGO');
				$data['payPalPaymentComponent'] = $this->load->view('components/ads/payPalPaymentComponent', $data, TRUE);
				$this->loadViews('adMenuScreen4', $data);
			} else {
				if($data["ad"]["userId"] === $data['user']) {
					$data["paymentTitle"] = "Quoted Ad Price";
					$data["paymentLogo"] = $this->config->item('QUOTE_PAYMENT_LOGO');
					$data['payPalPaymentComponent'] = $this->load->view('components/ads/payPalPaymentComponent', $data, TRUE);
					$this->loadViews('advertisingManage', $data);
				} else {
					return;				}
			}
		} else {
			// else fetch all the ads as id_type is null
			$paidAds = $this->ad->getAdDetails($adId, $data["user"], 'paid');
			$freeAds = $this->ad->getAdDetails($adId, $data["user"], 'free'); //bonus ads
			$allAds = [];
			if (!is_null($paidAds)) {
				foreach ($paidAds as $paidAd) {
					$allAds[] = $paidAd;
					if (!is_null($freeAds)) { //bonus ads
						foreach ($freeAds as $freeAd) {
							if ($freeAd["parentAdId"] === $paidAd["id"]) {
								$allAds[] = $freeAd;
							}
						}
					}
				}
			}
			$data["ads"] = $allAds;
			//$data["ads"] = $this->ad->getAdDetails($adId, $data["user"], 'free');
			$this->loadViews('advertisingOverview', $data);
		}
	}

	/*
	 * Classified ads can be purchased with any credit or part credit the user has
	 */

	public function buyWithCredit($adId = '', $credit_price = 0, $totalAdPrice) {
		$data = array();

		if (!$data["user"] = $this->initialiseUser()) {
			return;
		}

		$this->load->model('ad');

		if ($this->ad->getAdCredit($data["user"]) >= $totalAdPrice) {

			$this->ad->setAdCredit($data["user"], $credit_price, '0', $adId);

			$ad = $this->ad->getAdDetails($adId, $data["user"]);
			// not do not need to check for ad type as can't buy skyscraper with credit
			if (!empty($ad[0]["fileName"])) {
				$this->ad->setAdStatus($adId, 1, 1, 'Active', 1);
			} else {
				$this->ad->setAdStatus($adId, 0, 13, 'Awaiting Artwork', 1);
			}
			//set the status of the free placeholder ad
			if ($ad[0]["joinBonusAdId"]) {
				$this->ad->setAdStatusId($ad[0]["joinBonusAdId"], 18);
			}

			/*
			 * @todo need to ad post ads to this check in case things change in future!
			 *
			 */

			// check here to see if any bonus ads are applicable
			$this->ad->createAllBonusAds($adId);
		} else {
			log_message('error', 'buyWithCredit() - insufficient credit to purchase adid ' . $adId);
		}
		redirect('/advertising/manage/');
	}

	/* adAdmin users can bypass payment gateway */
	
	 public function buyFreeAd($adId = '', $credit_price = 0) {

		if (!$userId = $data["user"] = $this->initialiseUser()) {
			return;
		}

		$this->load->model('ad');

		$ad = $this->ad->getAdDetails($adId, $userId);

		//check auth
		if ($this->ad->checkAdAdminUser($userId) && $this->ad->checkAdAdminFreeAd($adId)) {
			if (!empty($ad[0]["fileName"])) {
				$this->ad->setAdStatus($adId, 1, 1, 'Active', 1);
			} else {
				$this->ad->setAdStatus($adId, 0, 13, 'Awaiting Artwork', 1);
			}
			//$this->ad->activateAd($adId);
			$this->ad->setAdAdminFreeAdStatus($adId, 2);
			$ad = $this->ad->getAdReceiptDetails($adId);
			// check here to see if any bonus ads are applicable
			if ($ad->style === 'banner' && $ad->cost_model === 'fixed') {
				if ($ad->postChildId == 0 || $ad->postChildId == '') { //ensures only 1 post ad is created even if page is refreshed
					$this->ad->createBonusAd($adId, 'post', 'post', 'FREE', 'Free Post Ad - available');
				}
			}
			$this->ad->createAllBonusAds($adId);
		} else {
			log_message('error', 'settings.php advertising_buyFreeAd() - User not adAdmin or not a free ad');
			show_error('authorization failed for free ad purchase');
		}

		redirect('/advertising/manage/');
	}

	public function updateAd($adId, $style, $mainAdCategory, $admedia = '', $postId = '') {
		
		if (!$user = $this->initialiseUser()) {
			return;
		}
		
		$this->load->model('ad');
		$this->load->model('file');
		$this->load->helper(array('htmlpurifier'));

		$data['adId'] = $adId;
		$data['style'] = $style;
		$data['mainAdCategory'] = $mainAdCategory;
		$data['parentAdId'] = $this->input->post('parentAdId');
		$data['admedia'] = $admedia;
		$data['ad_media_device'] = $admedia;

		if ($style === 'thumb' || $style === 'classified') {
			$text_ar = $this->ad->getAdText($adId);
			$data['adtitle'] = $text_ar['title'];
			$data['addesc'] = $text_ar['description'];
		}
		$data['postId'] = $postId;
		$data['update_image'] = $this->input->post('update_image');
		$data['url'] = html_purify($this->input->post('url_update'), 'url');
                //url is now verfied at next stage
                //if(!$this->file->checkURLWithGoogle($data['url'])) {
                ///    $data['url'] = NULL;
                //}
		  if (empty($data['votingText1'] = html_purify($this->input->post('voting_text1'), 'post'))) {
			$data['votingText1'] = NULL;
		}
		  if (empty($data['votingText2'] = html_purify($this->input->post('voting_text2'), 'post'))) {
			$data['votingText2'] = NULL;
		}

		if ($style === 'post') {
                    $data['admedia'] = null;
                    $post_ad = $this->ad->getPostAdImage($postId);
                    //log_message('error', 'post ad filename ' . $post_ad->fileName);
                    if (!empty($post_ad->fileName)) {
                        $data['admedia'] = $post_ad->fileName;
                    }
                    if ($this->input->post('job') === 'postTextUpdate') {
                        $data["currentPostAdText"] = $this->ad->getPostAdText($postId);
                    }
                }
				
		if ($style === 'post') {
			$data["adforums"] = $this->ad->getAdForums($data["ad"][0]["parentAdId"], $user);
		} else {
			$data["adforums"] = $this->ad->getAdForums($adId, $user);
		}
		
		$data["adforums"] = $this->ad->getAdForums($adId, $user);
		
		$data["private"] = FALSE;
		if($this->ad->isPrivateForum($data["adforums"][0]["subjectName"])) {
			$data["private"] = TRUE;
		}		

        //find out if this is during a payment
		$data["adStage"] = $this->input->post('adStage');
		$data["freeAds"] = $this->input->post('freeAds');
		$data['fileInputName'] = $this->input->post('fileInputName');
		$this->loadViews('adUpdate', $data);
	}

	// updates an ad
	public function update($adId = '', $ad_type = '', $cost_type = '') {
		$data = array();
		if (!$data['user'] = $this->initialiseUser()) {
			return;
		}
		$this->load->model('join');
		$this->load->model('group');
		$this->load->model('node');
		$this->load->model('file');
		$this->load->model('ad');

		if (( $adId !== false ) && ( $adId != '' )) {

			// if a form was submitted
			if ($this->input->post('submit') !== false) {

				if ($this->input->post('submit') == 'delete') {

					$status = $this->ad->getAdStatus($adId);
					if ($this->input->post('mainAdCategory') === 'classified' && $status->status !== 'Finished') {

						//make sure credit is not given twice if add finished then deleted!
						$this->ad->finishAd($adId); //this disables add before deleting

						$current_credit = $this->ad->getAdCredit($data["user"]);
						$credit_rebate = $this->ad->calcCreditRebate($adId);

						$this->ad->refundCredit($data["user"], $current_credit + $credit_rebate);
					}

					$this->deleteAd($adId);
					$data["ads"] = $this->ad->getAdDetails('', $data["user"]);
					$this->loadViews('advertisingOverview', $data);
				}

			}

			// forward the user back to the management of the ad
			// redirect('/advertising/manage/' . $adId . '/' . $cost_type);
		} else {

			// forward the user to the front end

			redirect('/advertising/manage/');
		}
	}

	/*
	 * updates an ad status
	 */

	public function book($adId = '', $parentAdId = '', $style = '') {

		$this->load->model('user');
		// check the authentication
		if (!$data["user"] = $this->initialiseUser()) {
			return;
		}

		$this->load->model('ad');

		if ($this->input->post('submit') == 'activate') {
			$this->ad->activateAd($adId);
			if ($style === 'post') {
				$this->ad->activatePostAd($parentAdId);
			}
			redirect('/advertising/manage/' . $adId);
		}

		if ($this->input->post('submit') == 'deactivate') {
			$this->ad->deactivateAd($adId);
			if ($style === 'post') {
				$this->ad->deactivatePostAd($parentAdId);
			}
			redirect('/advertising/manage/' . $adId);
		}

		if ($this->input->post('submit') == 'finish') {
			$this->ad->finishAd($adId);

			if ($this->input->post('mainAdCategory') == 'classified') {
				$current_credit = $this->ad->getAdCredit($data["user"]);
				$credit_rebate = $this->ad->calcCreditRebate($adId);
				$this->ad->refundCredit($data["user"], $current_credit + $credit_rebate);
			}
		}

		redirect('/advertising/manage/' . $adId);
	}

	public function saveAdImage($adId, $style, $postId = '', $current_filename = '') {

		// check the authentication
		if (!$data["user"] = $this->initialiseUser()) {
			return;
		}
		$this->load->model('ad');
		$this->load->model('file');
		$this->load->helper(array('htmlpurifier'));

		$ad_text['title'] = NULL;
		$ad_text['description'] = NULL;
		$data["adStage"] = $this->input->post('adStage');

		$data["ad"] = $this->ad->getAdDetails($adId, $data["user"]);
		$data["checkAdAdminUser"] = $this->ad->checkAdAdminUser($data["user"]);

		$currentTitle = $data["ad"][0]["title"];
		$currentDescription = $data["ad"][0]["description"];

		$currentFileName = $data["ad"][0]["fileName"];
		$currentDeviceFileName = $data["ad"][0]["deviceFileName"];

		if ($data["ad"][0]["type"] === 'post') {
			$data["adforums"] = $this->ad->getAdForums($data["ad"][0]["parentAdId"], $data["user"]);
		} else {
			$data["adforums"] = $this->ad->getAdForums($adId, $data["user"]);
		}

		$data["private"] = FALSE;
		$private = FALSE;
		if ($this->ad->isPrivateForum($data["adforums"][0]["subjectName"])) {
			$private = TRUE;
			$data["private"] = TRUE;
		}

		$newFilename = null;
		$newDeviceFilename = null;
		if ($style !== 'post') {
			$newFilename = $this->storeAdMedia("ad_media", $private);
			if ($newFilename === 'virus' || $newFilename == 16) {
				$newFilename = NULL;
				$virus = $data["ad_media_verified"] = 'virus';
				if ($data["ad"][0]["status"] !== 'Incomplete') {
					$this->ad->deActivateAd($adId);
				}
			} else {
				$data["ad_media_verified"] = TRUE;
				$virus = NULL;
			}
			$newDeviceFilename = $this->storeAdMedia("ad_media_device", $private);
			if ($newDeviceFilename === 'virus' || $newDeviceFilename == 16) {
				$newDeviceFilename = NULL;
				$data["ad_media_device_verified"] = 'virus';
				$virus = 'virus_device';
				if ($ad[0]["status"] !== 'Incomplete') {
					$this->ad->deActivateAd($adId);
				}
			} else {
				$data["ad_media_device_verified"] = TRUE;
			}
			if ($newFilename || $data["ad_media_verified"] === 'virus') { //put in no file as virus
				$this->ad->updateAdImage($adId, $newFilename, FALSE);
			} else
			if ($newDeviceFilename || $data["ad_media_device_verified"] === 'virus') {
				$this->ad->updateAdImage($adId, $newDeviceFilename, TRUE);
			}
			if ($style === 'thumb' || $style === 'classified') {

			        $ad_text['title'] = html_purify($this->input->post('store_title'), 'post');
				$ad_text['description'] = html_purify($this->input->post('store_description'), 'post');
				$this->ad->updateAdText($adId, $ad_text);
			}
		} else { //post ad
			if (empty($postAdImageFileName = $this->ad->updatePostAdImage($postId, $current_filename))) {
				$this->ad->setAdStatusId($adId, 14); //image disallowed (probably contains a virus)
			} else {
				$this->ad->setAdStatusId($adId, 1);
			}

			if (!empty($postAdImageFileName)) {
				if (!$this->file->verifyImageFileType($postAdImageFileName)) {
					$this->ad->removeContentFromNode($postId, $postAdImageFileName);
					$this->ad->setAdStatusId($adId, 16); //image type disallowed or image removed
					$postAdImageFileName = NULL;
				} else {
					$this->ad->setAdStatusId($adId, 1);
				}
			}
		}
		$this->ad->adArchiveInsert($adId);
		if ($newFilename || $virus === 'virus') {
			$data["ad"][0]["media"] = $newFilename;
		}
		if ($newDeviceFilename || $virus === 'virus') {
			$data["ad"][0]["media_device"] = $newDeviceFilename;
		}

		//check is modPaused
		if ($data["ad"][0]['modPaused'] && !$data["ad"][0]['modUnderReview'] && !$data["ad"][0]['modRemoved'] && 
				(($currentFileName != $filename && $filename != '') || $currentTitle != $ad_text['title'] || 
				    $currentDescription != $ad_text['description'])) {
			$this->ad->clearModStatus($adId);
		}
		//are we in the main payment screen
		if ($data["adStage"] === 'payment') {
			//get all ad details to resume payment 
			/*
			 * @todo - change the following to a function as it is called in 3 places!!
			 * 
			 */
			$data["freeAds"] = NULL;
			if ($this->input->post('freeAds') === 'freeAds') {
				$data["freeAds"] = "freeAds";
			}

			if ($data["ad"][0]["type"] === 'post') {
				//$data["adforums"] = $this->ad->getAdForums($data["ad"][0]["parentAdId"], $data["user"]);
				$data["post_ad"] = $this->ad->getPostAdDetails(null, $data["ad"][0]["parentAdId"]);
				$data["adcountries"] = $this->ad->getAdCountries($data["ad"][0]["parentAdId"], $data["user"]);
				$data["post_ad_image"] = null;
				if (!empty($data["post_ad"]->postId)) {
					$data["post_ad_image"] = $this->ad->getPostAdImage($data["post_ad"]->postId);
				}
			} else {
				//$data["adforums"] = $this->ad->getAdForums($adId, $data["user"]);
				$data["adcountries"] = $this->ad->getAdCountries($adId, $data["user"]);
			}

			$data["adinternational"] = $this->ad->getAdInternational($adId, $data["user"]);
			$data["adstates"] = $this->ad->getAdStateNames($adId, $data["user"]);
			$data["adpostcodes"] = $this->ad->getAdPostCodeNames($adId, $data["user"]);
			$data["adagegroups"] = $this->ad->getAdAgeGroups($adId, $data["user"]);
			$data["adgender"] = $this->ad->getAdGender($adId, $data["user"]);
			$data["credit"] = $this->ad->getAdCredit($data["user"]);

			$data["ad_order_details"] = $this->ad->getAdOrderDetails($adId);
			$data["bonusAds"] = $this->ad->getPotentialBonusAds($adId);
			$data["ad"] = $data["ad"][0];

			$data["currency_symbol"] = $this->config->item('currency_symbol');
			$data["currency_code"] = $this->config->item('currency_code');

			if ($data["ad"]["mainAdCategory"] === 'classified') {
				$data["credit_price"] = $this->ad->getAdCreditPrice($adId);
				$data["numeric_price"] = $this->ad->getAdPrice($adId, $data["user"])->price;
				$data["balance_due"] = $data["ad"]["totalPrice"] - $data["credit"];
				if ($data["balance_due"] < 0) {
					$data["balance_due"] = 0;
				}
				$data["new_credit_price"] = $data["ad"]["totalPrice"] - $data["balance_due"];
			}

			//main payment screen
			$data["adId"] = $adId;
			$data["mainAdCategory"] = $data["ad"]["mainAdCategory"];
			$data["adtype"] = $data["ad"]["mainAdCategory"];
			$data["adselected"] = $data["ad"]["style"];
			$data["existing"] = TRUE;
			$data["mainAdPayment"] = TRUE;
			$data["submit_status"] = 'Save Ad';

			/*
			 * This is in case user goes to edit url or edit image from payment screen 
			 * @ todo - in this scenario, only billing selections, imps, clicks and url are retained presently.
			 * If all values need to be retained, then they must be passed or retrieved 
			 * from db to populate dropdowns.
			 * 
			 */
			$data["ad_order"] = $this->ad->getAdOrderDetailsArr($adId);
			$data["ad_dropdowns"] = $this->ad->getAdDropdowns();
			$data["cost_model"] = $data["ad"]["costModel"];
			$data["store_impressions"] = $data["ad"]["num_imps"];
			$data["store_clicks"] = $data["ad"]["num_clicks"];
			$data["store_url"] = $data["ad"]["url"];
			/* end */


			//check if landing Page Ad and do the necessary
			if ($data["landingPageAd"] = $this->ad->isLandingPageAd($adId, $data["user"])) {
				$data["landingPageAllForums"] = $this->ad->landingPageAllForums($adId, $data["user"]);
				$data["landingPageHomeFeed"] = $this->ad->landingPageHomeFeed($adId, $data["user"]);
				if ($this->ad->landingPageHomeFeed($adId, $data["user"]) &&
						(($data["ad"]["style"] !== 'thumb' && $data["ad"]["style"] !== 'classified') || $this->config->item('thumb_landing_page_ads'))) {
					array_push($data["adforums"], array("subjectName" => "Landing Page Main Feed"));
				}
			}
			$data["adStatusClassColor"] = NULL;
			$data["popoverAnchor"] = NULL;
			$data["popoverTitle"] = NULL;
			$data["popoverText"] = NULL;
			$data["btnClassColor"] = 'orange';
			$data["btnStatus"] = NULL;

			$data['adDetailsPanel'] = $this->load->view('components/ads/adDetailsPanel', $data, TRUE);
			$data['adBonusDetailsPanel'] = $this->load->view('components/ads/adBonusDetailsPanel', $data, TRUE);
			$data['adImagePanel'] = $this->load->view('components/ads/adImagePanel', $data, TRUE);
			$data['preImageText'] = NULL;
			$data['fileInputName'] = 'ad_media';
			$data['preImageText'] = NULL;
			if ($data["ad"]["style"] === 'skyscraper') {
				$data['preImageText'] = 'standard (non-mobile device) ';
			}
			$data['adImagePanel'] = $this->load->view('components/ads/adImagePanel', $data, TRUE);

			//tidy this up!
			//not coming here is in mgmt - only payments so sort it out!!!!!!!!!!!!!!!11
			if ($data["ad"]["style"] === 'skyscraper') {

				$tempMedia = $data["ad"]["media"];
				if ($newDeviceFilename || $data["ad_media_device_verified"] === 'virus') {
					$data["ad"]["media"] = $newDeviceFilename;
				} else {
					$data["ad"]["media"] = $data["ad"]["deviceFileName"];
				}
				$data['fileInputName'] = 'ad_media_device';
				$data['preImageText'] = 'mobile device ';
				$data["mobileImage"] = TRUE;
				$data["ad_media_verified"] = FALSE;
				$data['adImagePanel2'] = $this->load->view('components/ads/adImagePanel', $data, TRUE);
				//$data["ad"]["media"] = $tempMedia; //set it back to what is was
			}

			$data['adBillingDetailsPanel'] = $this->load->view('components/ads/adBillingDetailsPanel', $data, TRUE);
			$data["paymentTitle"] = "Ad Price";
			$data["paymentLogo"] = $this->config->item('MAIN_PAYMENT_LOGO');
			$data["verification"] = crypt($adId . $data["ad"]["unitPrice"], rand());
			$data['payPalPaymentComponent'] = $this->load->view('components/ads/payPalPaymentComponent', $data, TRUE);
			$this->loadViews('adMenuScreen4', $data);
		} else {
			redirect('/advertising/manage/' . $adId . '/' . $virus);
		}
	}

	public function updateAdUrl($adId, $style = '', $parentAdId = '') {

		// check the authentication
		if (!$data["user"] = $this->initialiseUser()) {
			return;
		}

		$this->load->model('ad');
                $this->load->model('file');
		$this->load->helper(array('htmlpurifier'));

		$data["ad"] = $this->ad->getAdDetails($adId, $data["user"]);

		$data["checkAdAdminUser"] = $this->ad->checkAdAdminUser($data["user"]);

		$currentUrl = $data["ad"][0]["url"];

		$data["ad"][0]["url"] = $url = html_purify($this->input->post('store_url'), 'url');
                if ($url === 'blacklisted') {
                    $data["ad"][0]["url"] = $url = NULL;
                }
                
                /* This URL check has been moved into the model
                if(!empty($url) && !$this->file->checkURLWithGoogle($url)) {
                    $data["ad"][0]["url"] = $url = 'blacklisted';
                }
                */
                
		$this->ad->updateAdUrl($adId, $url, $style, $parentAdId);

		//check if modPaused
		if ($data["ad"][0]['modPaused'] && !$data["ad"][0]['modUnderReview'] && !$data["ad"][0]['modRemoved'] && $currentUrl !== $url) {
			$this->ad->clearModStatus($adId);
		}

		$this->ad->adArchiveInsert($adId);
                
                $data["ad_media_verified"] = NULL;
                $data["ad_media_device_verified"] = NULL;

		//check for main payment screen
		$data["adStage"] = $this->input->post('adStage');
		if ($data["adStage"] === 'payment') {

			$data["freeAds"] = NULL;
			if ($this->input->post('freeAds') === 'freeAds') {
				$data["freeAds"] = "freeAds";
			}

			if ($data["ad"][0]["type"] === 'post') {
				$data["adforums"] = $this->ad->getAdForums($data["ad"][0]["parentAdId"], $data["user"]);
				$data["post_ad"] = $this->ad->getPostAdDetails(null, $data["ad"][0]["parentAdId"]);
				$data["adcountries"] = $this->ad->getAdCountries($data["ad"][0]["parentAdId"], $data["user"]);
				$data["post_ad_image"] = null;
				if (!empty($data["post_ad"]->postId)) {
					$data["post_ad_image"] = $this->ad->getPostAdImage($data["post_ad"]->postId);
				}
			} else {
				$data["adforums"] = $this->ad->getAdForums($adId, $data["user"]);
				$data["adcountries"] = $this->ad->getAdCountries($adId, $data["user"]);
			}

			$data["adinternational"] = $this->ad->getAdInternational($adId, $data["user"]);
			$data["adstates"] = $this->ad->getAdStateNames($adId, $data["user"]);
			$data["adpostcodes"] = $this->ad->getAdPostCodeNames($adId, $data["user"]);
			$data["adagegroups"] = $this->ad->getAdAgeGroups($adId, $data["user"]);
			$data["adgender"] = $this->ad->getAdGender($adId, $data["user"]);
			$data["credit"] = $this->ad->getAdCredit($data["user"]);

			$data["ad_order_details"] = $this->ad->getAdOrderDetails($adId);
			$data["ad_order"] = $this->ad->getAdOrderDetailsArr($adId);
			$data["bonusAds"] = $this->ad->getPotentialBonusAds($adId);
			$data["ad"] = $data["ad"][0];
			
			/* 
			 * This is in case user goes to edit url or edit image from payment screen 
			 * @ todo - in this scenario, only billing selections, imps, clicks and url are retained presently.
			 * If all values need to be retained, then they must be passed or retrieved 
			 * from db to populate dropdowns.
			 * 
			 */
			$data["ad_order"] = $this->ad->getAdOrderDetailsArr($adId);
			$data["ad_dropdowns"] = $this->ad->getAdDropdowns();
			$data["cost_model"] = $data["ad"]["costModel"];
			$data["store_impressions"] = $data["ad"]["num_imps"];
			$data["store_clicks"] = $data["ad"]["num_clicks"];
			$data["store_url"] = $data["ad"]["url"];
			/* end */

			$data["currency_symbol"] = $this->config->item('currency_symbol');
			$data["currency_code"] = $this->config->item('currency_code');

			if ($data["ad"]["mainAdCategory"] === 'classified') {
				$data["credit_price"] = $this->ad->getAdCreditPrice($adId);
				$data["numeric_price"] = $this->ad->getAdPrice($adId, $data["user"])->price;
				$data["balance_due"] = $data["ad"]["totalPrice"] - $data["credit"];
				if ($data["balance_due"] < 0) {
					$data["balance_due"] = 0;
				}
                                $data["new_credit_price"] = $data["ad"]["totalPrice"] - $data["balance_due"];
			}

			//main payment screen
			//check if landing Page Ad and do the necessary
			if ($data["landingPageAd"] = $this->ad->isLandingPageAd($adId, $data["user"])) {
				$data["landingPageAllForums"] = $this->ad->landingPageAllForums($adId, $data["user"]);
				$data["landingPageHomeFeed"] = $this->ad->landingPageHomeFeed($adId, $data["user"]);
				if ($this->ad->landingPageHomeFeed($adId, $data["user"]) &&
						(($data["ad"]["style"] !== 'thumb' && $data["ad"]["style"] !== 'classified') || $this->config->item('thumb_landing_page_ads'))) {
					array_push($data["adforums"], array("subjectName" => "Landing Page Main Feed"));
				}
			}
			$data["adStatusClassColor"] = NULL;
			$data["popoverAnchor"] = NULL;
			$data["popoverTitle"] = NULL;
			$data["popoverText"] = NULL;
			$data["btnClassColor"] = 'orange';
			$data["btnStatus"] = NULL;

			$data["adId"] = $adId;
			$data["mainAdCategory"] = $data["ad"]["mainAdCategory"];
			$data["adtype"] = $data["ad"]["mainAdCategory"];
			$data["adselected"] = $data["ad"]["style"];
			$data["existing"] = TRUE;
			$data["mainAdPayment"] = TRUE;
			$data["submit_status"] = 'Save Ad';

			$data['adDetailsPanel'] = $this->load->view('components/ads/adDetailsPanel', $data, TRUE);
			$data['adBonusDetailsPanel'] = $this->load->view('components/ads/adBonusDetailsPanel', $data, TRUE);
			$data['fileInputName'] = 'ad_media';
			$data['preImageText'] = NULL;
			if ($data["ad"]["style"] === 'skyscraper') {
				$data['preImageText'] = 'standard (non-mobile device) '; 
			}
			$data['adImagePanel'] = $this->load->view('components/ads/adImagePanel', $data, TRUE);
			
			if ($data["ad"]["style"] === 'skyscraper') {
				$tempMedia = $data["ad"]["media"];
				$data["ad"]["media"] = $data["ad"]["deviceFileName"];
				$data['fileInputName'] = 'ad_media_device';
				$data['preImageText'] = 'mobile device '; 
				$data['adImagePanel2'] = $this->load->view('components/ads/adImagePanel', $data, TRUE);
				$data["ad"]["media"] = $tempMedia; //set it back to what is was
			}
			
			$data['adBillingDetailsPanel'] = $this->load->view('components/ads/adBillingDetailsPanel', $data, TRUE);

			$data["paymentTitle"] = "Ad Price";
			$data["paymentLogo"] = $this->config->item('MAIN_PAYMENT_LOGO');
                        $data["verification"] = crypt($adId . $data["ad"]["unitPrice"], rand());
			$data['payPalPaymentComponent'] = $this->load->view('components/ads/payPalPaymentComponent', $data, TRUE);
			$this->loadViews('adMenuScreen4', $data);
		} else {
			redirect('/advertising/manage/' . $adId);
		}
	}

	public function updateAdVotingText($adId, $style = '', $parentAdId = '') {

		if (!$data["user"] = $this->initialiseUser()) {
			return false;
		}

		$this->load->model('ad');
		$this->load->helper(array('htmlpurifier'));

		$voting_text1 = html_purify($this->input->post('store_voting_text1'), 'post');
		$voting_text2 = html_purify($this->input->post('store_voting_text2'), 'post');
		$this->ad->updateAdVotingText($adId, $parentAdId, $voting_text1, $voting_text2, $this->input->post('reset_votes'));

		redirect('/advertising/manage/' . $adId);
	}

        public function updatePostAdText($adId, $style = '', $postId = '') {

		if (!$data["user"] = $this->initialiseUser()) {
			return false;
		}

		$this->load->model('ad');
		$this->load->helper(array('htmlpurifier'));
		$this->ad->updatePostAdText($postId, html_purify($this->input->post('newPostAdText'), 'post'));

		redirect('/advertising/manage/' . $adId);
	}
        
	private function deleteAd($adId = '') {
		$this->ad->deleteAd($adId);
		//$this->ad->permanentlyDeleteAd($adId);
	}

	private function saveQuote($adId) {
		$this->ad->updateAdStatus($adId, 'quote');
	}

	//stores the ad photo media in the correct directory
	//@todo - check file image!!!
	private function storeAdMedia($media, $private=FALSE) {
		if (isset($_FILES[$media])) {
			if (!isset($_FILES[$media]["error"]) || $_FILES[$media]["error"] <= 0) {
				if ($this->file->verifyFile($_FILES[$media]["tmp_name"])) {
					//we have a file
					$tmpFilename = $this->globals->uuid();
					// $result["origName"] = $_FILES["ad_media"]["name"];  do we need this?????
					move_uploaded_file($_FILES[$media]["tmp_name"], $this->file->setFilePath($tmpFilename, $private));
                 } else {
					log_message('error', 'storeAdMedia - possible virus found and blocked!');
                    return('virus');
                            }
			} else {
				return NULL;
			}
                        if (!$this->file->verifyImageFileType($tmpFilename, $private)) {
                            return(16);
                        } else {
                            return($tmpFilename);
                        }
		} else {
			return NULL;
		}
	}
	

	/*
	 * saves a new ad
	 *
	 * adselected is banner_cpm, banner_fix, standard or thumb
	 * change this to banner etc
	 * cost model is CPM, FIXED, PPC
	 *
	 */

	private function saveAd($adId, $mainAdCategory, $adselected, $cost_model, $unit_price, $credit_price = '', $totalPrice, $landingPageAd, $storeMedia='', $url='', $joinBonusAdId) {

		$data = array();
		$ret_array = array();

		// check the authentication (need the user Id in this function)
		if (!$data["user"] = $this->initialiseUser()) {
			return;
		}

		// if a form was submitted
		if ($this->input->post('submit') !== false) {

			//this is so the api picks it up
			if ($adselected == 'banner_fix' || $adselected == 'banner_cpm') {
				$sendadtype = 'banner';
				$ad_style = $sendadtype;
			} else if ($adselected == 'skyscraper' || $adselected == 'thumb') {
				$sendadtype = 'ppc';
				$ad_style = $adselected;
			}

			$postVars = $this->input->post(NULL, TRUE);
			$postKeys = array_keys($postVars);
			$result = array();

			// go through all the posted vars
			for ($i = 0, $ilen = count($postKeys); $i < $ilen; $i++) {

				// if we're meant to store it
				if (mb_substr($postKeys[$i], 0, 6) == 'store_') {
					$result[mb_substr($postKeys[$i], 6)] = $postVars[$postKeys[$i]];
				}
			}

                        $ret_array["media"]["ad_media_verified"] = FALSE;
                        $ret_array["media"]["ad_media_device_verified"] = FALSE;
			$result["ad_media"] = $result["ad_media_device"] = NULL;
			
			if (empty(json_decode($result["forums"]))) {
				$saveForums = array('All Users');
			} else {
				$saveForums = json_decode($result["forums"]);
			}
			
			//just check the first forum as you can't have mixed public and private (unless freeads wich are adadmin only).
			$private = FALSE;
			if($this->ad->isPrivateForum($saveForums[0])) {
				$private = TRUE;
			}
			if (!$storeMedia) {
                                $verified = $this->storeAdMedia("ad_media", $private);
                                if ($verified === 'virus' || $verified == 16) {
                                    $result["ad_media"] = NULL;
                                    $ret_array["media"]["ad_media_verified"] = 'virus';
                                } else {
                                    $result["ad_media"] = $verified;
                                    $ret_array["media"]["ad_media_verified"] = TRUE;
                                }
                                
                                $verified  = $this->storeAdMedia("ad_media_device", $private);
                                if ($verified === 'virus' || $verified == 16) {
                                    $result["ad_media_device"] = NULL;
                                    $ret_array["media"]["ad_media_device_verified"] = 'virus';
                                } else {
                                     $result["ad_media_device"] = $verified;
                                     $ret_array["media"]["ad_media_device_verified"] = TRUE;
                                }
			}

			
			if (empty(json_decode($result["countries"])) || $landingPageAd) {
				$saveCountries = array('None');
			} else {
				$saveCountries = json_decode($result["countries"]);
			}

			if (empty(json_decode($result["states"])) || $landingPageAd) {
				$saveStates = array('None');
			} else {
				$saveStates = json_decode($result["states"]);
			}

			if (empty(json_decode($result["postcodes"])) || $landingPageAd) {
				$savePostcodes = array('None');
			} else {
				$savePostcodes = json_decode($result["postcodes"]);
			}

			if (empty(json_decode($result["agegroups"])) || $landingPageAd) {
				$saveAgeGroups = array('All');
			} else {
				$saveAgeGroups = json_decode($result["agegroups"]);
			}
			
			if ($landingPageAd) {
				$result["international"] = 'Yes';
				$result["gender"] = 'Both';
			}

			
			$userId = $this->user->authCheck();
			
			/*
			 * @todo this ad_params array is not used but could be utilised!!
			 */
			/*($ad_params = array($userId, $adId, $result["ad_media"],
				$result["countries"], $result["states"], $result["postcodes"], 'Title', $result["url"], 'Description',
				$ad_style, '', $result["agegroups"], $result["gender"], $result["impressions"], $result["clicks"]);
			*/

			/*
			 * @todo change this to send an array!
			 * @todo drop credit and creditexpiry from advert?
			 *
			 */

			$this->ad->adInsert(
				$adId, $mainAdCategory, $cost_model, $unit_price, $credit_price, $totalPrice, 
				$saveForums, $userId, $result["ad_media"], $result["ad_media_device"], $result["international"], $saveCountries, 
				$saveStates, $savePostcodes, $this->input->post('postCodesInputType'), 
				$url, $sendadtype, $ad_style, $saveAgeGroups, 
				$result["gender"], $result["impressions"], 
				$result["clicks"], $result["title"], $result["description"], $landingPageAd, $joinBonusAdId
			);

			$this->ad->adArchiveInsert($adId);
			$this->ad->adInsertBonusAds($userId, $adId, $mainAdCategory, $cost_model, $ad_style, $result["impressions"], $result["clicks"]);
		}

		$ret_array["media"]["ad_media"] = $result["ad_media"];
		$ret_array["media"]["ad_media_device"] = $result["ad_media_device"];
		return($ret_array);
	}
	
	public function adFree() {
		
		if (!$data["user"] = $this->initialiseUser()) {
			return;
		}
		// @todo - make sure user has adAdmin privileges
		redirect('/advertising/adCreateMenu/free');
	}
        
        
        public function createFreeJoinAds($adId, $statusId) {

            $data = array();
            // check the authentication
            if (!$data["user"] = $this->initialiseUser()) {
                return;
            }

            $this->load->model('ad');
            
            /*
             * This is for adadmin free ads 
             * and not applicable to a new user free bonus ad
             */
            $data["freeAds"] = NULL; 
                
            $data["statusId"] = $statusId;
            $data["joinBonusAdId"] = $adId;
                                    
            $data["credit"] = $this->ad->getAdCredit($data["user"]);

            $data["ad_row_titles"] = $this->ad->getAdRowTitles('screen_1');
            if (empty($data["ad_row_titles"])) {
                log_message('error', 'createFreeJoinAds(): getAdRowTitles() returned no ad titles.');
                show_error("No active ad titles found");
            }
            $data["ad_types_main"] = $this->ad->getAdTypes();
            if (empty($data["ad_types_main"])) {
                log_message('error', 'createFreeJoinAds(): getAdTypes() returned no active ad types.');
                show_error("No active ad types found");
            }

            $this->loadViews('adMenuScreen1', $data);
        }

}
