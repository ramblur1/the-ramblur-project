<?php

/*
 * 
 * PayPal Express Payment Gateway
 * 
 */

if (!defined('BASEPATH'))
    exit('No direct script access allowed');


class Paypal extends CI_Controller {
      

    public function index() {
        
         $this->load->model('user');
        // check the authentication
        if (!$userId = $this->user->authCheck()) {
            return;
        }
        
        $this->load->model('paypalmodel');
        $this->load->model('ad');
        $this->load->helper('date');   
        $this->load->model('globals');
        $this->load->model('node');     
        //$this->load->library('session');
      
        //$paypalmode = ($this->config->item('PAYPAL_MODE') == 'sandbox') ? '.sandbox' : '';   
        $payPalReturnUrl = $this->config->item('base_url') . 'paypal'; // Returns here after payment
        $payPalCancelUrl = $this->config->item('base_url') . 'advertising'; //Cancel URL if user clicks cancel
        
        
        // if(0) - use this instead of the if post() to turn off PayPal for debugging!
        
        /* if it a POST - set up PayPal ExpressCheckout */
        
        if ($this->input->post()) { 
  
            $paypal_data = '';
            $itemTotalPrice = 0;
            $totalTaxAmount = 0;
            $taxExclusivePrice = 0;
            $subtotal = 0;
            $grandTotal = 0;

            foreach ($this->input->post('item_name') as $key => $itemname) {

                $product_code = filter_var($this->input->post('item_code')[$key], FILTER_SANITIZE_STRING);
                
                // Get price from database for security reasons
                $adObj = $this->ad->getAdPaymentPrice($product_code, $userId); 
                
                /*
                 * 
                 * verify the hidden posted data has not been tampered with
                 * 
                 */
                if ($this->input->post('verification') !== crypt($product_code . $adObj->price, $this->input->post('verification'))) {
                    log_message('error','paypal controller() - posted values do not match retrieved values!');
                    show_error('There is an order mismatch. Please return to Manage Ads and try to make the payment again.');
                }
        
                if(!empty($adObj)) {
                    $taxExclusivePrice = round($adObj->price / (1 + $this->config->item('TAX_RATE_PERCENT')),2); 
                    $totalTaxAmount = $adObj->price - $taxExclusivePrice;
                    // item price times quantity
                    $subtotal = ($adObj->price * $this->input->post('item_qty')[$key]);
                    $subtotal = $subtotal = ($taxExclusivePrice * $this->input->post('item_qty')[$key]);
                }
                //total price
                $itemTotalPrice = $itemTotalPrice + $subtotal;

                $order = $this->ad->getAdOrderDetails($product_code);

                //This array is used for the session when returning from SetExpressCheckout
                $product_arr['items'][] = array(
                        'itm_name' => $itemname,
                        'itm_price' => $taxExclusivePrice, //$adObj->price from database,
                        'itm_code' => $this->input->post('item_code')[$key],
                        'itm_qty' => $this->input->post('item_qty')[$key]
                );
                          
                $paypal_data .= '&L_PAYMENTREQUEST_0_NAME' .    $key . '=' . urlencode($itemname); // product name
                $paypal_data .= '&L_PAYMENTREQUEST_0_NUMBER' .  $key . '=' . urlencode($this->input->post('item_code')[$key]);  // product code
                $paypal_data .= '&L_PAYMENTREQUEST_0_AMT' .     $key . '=' . $taxExclusivePrice; // price calculated from db * item amount
                $paypal_data .= '&L_PAYMENTREQUEST_0_QTY' .     $key . '=' . urlencode($this->input->post('item_qty')[$key]); // item amount
                
            }
            
            $grandTotal = $itemTotalPrice + $totalTaxAmount;         

            //totals for the session array
            $product_arr['assets'] = array('tax_total' => $totalTaxAmount,
                                           'grandTotal' => $grandTotal
                                           );
            
            /* Could also pass:
             *      
                'shipping_cost'
                'shipping_discount'
                'handling_cost'
                'insurance_cost'
                 etc.
             *
            */
            
            //Parameters for SetExpressCheckout to be sent to PayPal
            $ppdata = 
                    '&METHOD=SetExpressCheckout' .
                    '&RETURNURL=' . urlencode($payPalReturnUrl) .
                    '&CANCELURL=' . urlencode($payPalCancelUrl) .
                    '&PAYMENTREQUEST_0_PAYMENTACTION=' . urlencode("SALE") .
                    '&SOLUTIONTYPE=Sole&LANDINGPAGE=Billing' . //do we need these - do they prevent auto paypal acc login?
                    '&USERSELECTEDFUNDINGSOURCE=CreditCard' . //this is under test
                    $paypal_data .
                    '&NOSHIPPING=1' . // Product does not require shipping so hide shipping address
                    '&PAYMENTREQUEST_0_ITEMAMT=' . urlencode($itemTotalPrice) .
                    '&PAYMENTREQUEST_0_TAXAMT=' . urlencode($totalTaxAmount) .
                    '&PAYMENTREQUEST_0_AMT=' . urlencode($grandTotal) .
                    '&PAYMENTREQUEST_0_CURRENCYCODE=' . urlencode($this->config->item('PAYPAL_CUR_CODE')) .
                    '&LOCALECODE=' . $this->config->item('LOCALE_CODE') . //PayPal pages to match the language of website.
                    //HDRIMG does not work but is in doc at https://developer.paypal.com/docs/classic/api/merchant/SetExpressCheckout_API_Operation_NVP/
                    //'&HDRIMG=' . base_url() . $this->config->item('HEADER_LOGO') . 
                    //logo disabled for time being
                    //'&LOGOIMG=' . base_url() . $this->config->item('LOGO') .
                    '&CARTBORDERCOLOR=' . $this->config->item('CART_BORDER_COLOR') . 
                    '&ALLOWNOTE=1' .
                    '&BRANDNAME=' . $this->config->item('BRANDNAME') .
                    
                    // Purchaser details
                    '&PAYMENTREQUEST_0_SHIPTONAME=' . urlencode($order->firstname . ' ' . $order->lastname) .
                    '&PAYMENTREQUEST_0_SHIPTOSTREET=' . urlencode($order->street_no) .
                    '&PAYMENTREQUEST_0_SHIPTOSTREET2=' . urlencode($order->street) .
                    '&PAYMENTREQUEST_0_SHIPTOCITY=' . urlencode($order->city) .
                    '&PAYMENTREQUEST_0_SHIPTOSTATE=' . urlencode($order->state) .
                    '&PAYMENTREQUEST_0_SHIPTOCOUNTRYCODE=' . urlencode($order->country) .
                    '&PAYMENTREQUEST_0_SHIPTOZIP=' . urlencode($order->postcode) .
                    //'&PAYMENTREQUEST_0_EMAIL='whythisnotwork@hotmail.com'.  -- PayPal API doc is wrong here!
                    '&EMAIL=' . urlencode($order->email) .
                    
                    /*
                       Note - PP Express requires phone numbers for non-account holders
                       The option to turn it off in the account is just referring to passing it on
                       to the merchants or not!!! 
                     */
                    '&PAYMENTREQUEST_0_SHIPTOPHONENUM=' . urlencode($order->phone);
            
            // End of arameters for SetExpressCheckout to be sent to PayPal
            
            /*
             * 
             * If a classified ad, need to do a last minute credit check in case another classified 
             * ad has been purchased in last few seconds for example!
             * 
             */
            if($adObj->product_desc === 'classified') {
                if($this->input->post('credit') != $this->ad->getAdCredit($userId)) {
                    log_message('error', 'paypal controller() - credit mismatch for order ' . $this->input->post('item_code')[$key]);
                    show_error('Credit mismatch for the order. Please return to Manage Ads and try to make the payment again.');
                }
            }
                    
            $live = TRUE;
            $adAdminFreeAd = 0;
            //check if a free ad and if so double check auth
            if(($this->input->post('freeAd') && $this->ad->checkAdAdminUser($userId) && $this->ad->checkAdAdminFreeAd($order->adId))
                    || !$this->config->item('live')) {
                if($this->input->post('freeAd')) {
                    $adAdminFreeAd = 1;
                }
                $live = FALSE;
                }
                
            // Call "SetExpressCheckOut" to obtain a paypal token
            $http_parsed_response_arr = $this->paypalmodel->sendPayPalTransaction('SetExpressCheckout', $ppdata, $live);

            // log PayPal response to database even if an error has occured
            $this->paypalmodel->logPaypalSetCheckout($http_parsed_response_arr, $product_code);

            // Check response from Paypal - SUCCESS or FAIL at this stage
            if ("SUCCESS" == mb_strtoupper($http_parsed_response_arr["ACK"]) || "SUCCESSWITHWARNING" == mb_strtoupper($http_parsed_response_arr["ACK"])) {

                $data['SetExpressCheckout'] = 'SUCCESS';
                        
                $token = $http_parsed_response_arr["TOKEN"];
                
                //create unique product session variable for use when returning from SetExpressCheckout
                //$this->session->set_userdata(array('paypal_session' . urldecode($token) => $product_arr));
                
                //session for extra check here
                //$this->session->set_userdata('grandTotal'. urldecode($token), $grandTotal);
                
                // re-direct user to PayPal using Token
                
                // $paypalurl = 'https://www' . $paypalmode . '.paypal.com/cgi-bin/webscr?cmd=_express-checkout&token=' . $token . '';
                // Using useraction=commit - This skips order confirmation page so checkout completes on paypal
                
                if(!$live) {
                    $paypalurl = $this->config->item('PAYPAL_SANDBOX_URL') . $token . '';
                } else {
                    $paypalurl = $this->config->item('PAYPAL_LIVE_URL') . $token . ''; //change this to live!!!!!!!!!!!!!!!!1
                }
                
                //set the freeAd session var so we know how it was paid for (sandbox or live)
                //$this->session->set_userdata('adAdminFreeAd'. urldecode($token), $adAdminFreeAd);
                
                $this->paypalmodel->setPaymentSession(
                        urldecode($token), 
                        $itemname, 
                        $this->input->post('item_code')[$key], 
                        $taxExclusivePrice,
                        $this->input->post('item_qty')[$key], 
                        $totalTaxAmount, 
                        $grandTotal,
                        $userId,
                        $adAdminFreeAd);
                
                header('Location: ' . $paypalurl);
                        
            } else {
               
                $data['SetExpressCheckout'] = 'FAILED';
                $data['http_parsed_response_arr'] = $http_parsed_response_arr;
                $this->load->view('paymentReceipt', $data);
            }
        }

        
        /*  Re-direct back here from PayPal */
   
        /*  Paypal re-directs back to this page using '$payPalReturnUrl' 
         *  hopefully sending a TOKEN and the Payer ID.
         *  No payment has been made yet as the token and Payer Id are needed 
         *  for calling DoExpressCheckoutPayment to process the payment.
         */
        
        // check the authentication
        if (!$userId = $this->user->authCheck()) {
            return;
        }
        
        if ($this->input->get("token") && $this->input->get("PayerID")) {
       
            $token = $this->input->get("token");
            $payer_id = $this->input->get("PayerID");
           
            //get the product session variables
            //$product_arr = $this->session->userdata('paypal_session' .urldecode($token));
            
            $productSessionArr = $this->paypalmodel->getPaymentSession(urldecode($token), $userId);
            
            
            $paypal_data = '';
            $itemTotalPrice = 0;

            if (!empty($productSessionArr)) {
                
                $adObj = $this->ad->getAdPaymentPrice($productSessionArr[0]['itemCode'], $userId); 
              
              //  foreach ($product_arr['items'] as $key => $product_item) {
                foreach ($productSessionArr AS $product_item) {
                    $paypal_data .= '&L_PAYMENTREQUEST_0_QTY0=' . urlencode($product_item['itemQty']);
                    $paypal_data .= '&L_PAYMENTREQUEST_0_AMT0=' . urlencode($product_item['itemPrice']);
                    $paypal_data .= '&L_PAYMENTREQUEST_0_NAME0=' . urlencode($product_item['itemName']);
                    $paypal_data .= '&L_PAYMENTREQUEST_0_NUMBER0=' . urlencode($product_item['itemCode']);

                    /*
                     * $paypal_data .= '&L_PAYMENTREQUEST_0_QTY' . $key . '=' . urlencode($product_item['itm_qty']);
                    $paypal_data .= '&L_PAYMENTREQUEST_0_AMT' . $key . '=' . urlencode($product_item['itm_price']);
                    $paypal_data .= '&L_PAYMENTREQUEST_0_NAME' . $key . '=' . urlencode($product_item['itm_name']);
                    $paypal_data .= '&L_PAYMENTREQUEST_0_NUMBER' . $key . '=' . urlencode($product_item['itm_code']);
                     */
                    // item price times quantity
                    $subtotal = ($product_item['itemPrice'] * $product_item['itemQty']);

                    //total price
                    $itemTotalPrice = ($itemTotalPrice + $subtotal);
                }

                $ppdata = 
                    '&TOKEN=' . urlencode($token) .
                    '&PAYERID=' . urlencode($payer_id) .
                    '&PAYMENTREQUEST_0_PAYMENTACTION=' . urlencode("SALE") .
                    $paypal_data .
                    '&PAYMENTREQUEST_0_ITEMAMT=' . urlencode($itemTotalPrice) .
                    '&PAYMENTREQUEST_0_TAXAMT=' . urlencode($productSessionArr[0]['taxTotal']) .
                    '&PAYMENTREQUEST_0_AMT=' . urlencode($productSessionArr[0]['grandTotal']) .
                    '&PAYMENTREQUEST_0_CURRENCYCODE=' . urlencode($this->config->item('PAYPAL_CUR_CODE'));
 
    
                //extra check!
                $grandTotalsMatch = TRUE; 
                if ($adObj->price != $productSessionArr[0]['grandTotal']) {
                    $grandTotalsMatch = FALSE;
                    log_message('error', 'PayPal Controller - Product totals do not match. Aborting Payment.');
                    show_error("Product totals do not match. Aborting payment");
                }
                 
   
                $live = TRUE;
                if(($this->ad->checkAdAdminFreeAd($productSessionArr[0]["itemCode"]))
                    || !$this->config->item('live')) {
                        $live = FALSE;
                }
                //Call DoExpressCheckoutPayment to process payment
                $http_parsed_response_arr = $this->paypalmodel->sendPayPalTransaction('DoExpressCheckoutPayment', $ppdata, $live);


                //log the transaction even if there has been an error
                $this->paypalmodel->logPaypalCheckoutPayment($http_parsed_response_arr);
                

                $this->processTransactionResponse(
			$http_parsed_response_arr, 
			$token, 
			$ppdata, 
			$grandTotalsMatch, 
			$productSessionArr[0]['taxTotal'],
			$subtotal,
			$productSessionArr[0]['grandTotal'],
                        $productSessionArr[0]['adAdminFreeAd']
			);
            } else {
                log_message('error', 'PayPal Controller - Cannot retrieve payment session data.');
                show_error("Cannot retrieve payment session data.. Aborting payment");
            }
        }
    }

    
    private function processTransactionResponse(
	    $http_parsed_response_arr, 
	    $token, $ppdata, 
	    $grandTotalsMatch, 
	    $totalTaxAmount, 
	    $subTotal,
	    $grandTotal,
            $adAdminFreeAd
	    ) {
        
         $this->load->model('user');
        // check the authentication
        if (!$this->user->authCheck()) {
            return;
        }

        //must have been successful if got to this stage
        $data['SetExpressCheckout'] = 'SUCCESS';
        
        $data['currency'] = $this->config->item('CURRENCY');
        $data['paypal_curr_code'] = $this->config->item('PAYPAL_CUR_CODE');
        $data['tax_type_code'] = $this->config->item('TAX_TYPE_CODE');
            
        $data['http_parsed_response_arr'] = $http_parsed_response_arr;
        
        // Check if the payment was successful
        if (("SUCCESS" == mb_strtoupper($http_parsed_response_arr["ACK"]) || "SUCCESSWITHWARNING" == mb_strtoupper($http_parsed_response_arr["ACK"])) && $grandTotalsMatch) 
            {
            $data['DoExpressCheckoutPayment'] = 'SUCCESS';       
            
            $order_obj = $this->ad->getAdOrderId(urldecode($token));
            $order = $this->ad->getAdOrderDetails($order_obj->order_id);
            $data['order'] = $order;
            $data['order_id'] = $order_obj->order_id;
            $data['ad'] = $this->ad->getAdReceiptDetails($order_obj->order_id);
             
            $data["cost_model_string"] = '';
            if ($data["ad"]->cost_model  === 'cpm') {
                $data["cost_model_string"] = $data['ad']->impressionsPurchased . ' Impressions';
            } else 
            if ($data["ad"]->cost_model  === 'ppc') {
                $data["cost_model_string"] = $data['ad']->clicks . ' Clicks';
            } else 
            if ($data["ad"]->cost_model  === 'fixed') {
                $data["cost_model_string"] = '- End Date ' . date("d-m-Y h:i:sa", strtotime($data['ad']->endDate));
            } 
            
            
            $live = TRUE;
            if(($this->ad->checkAdAdminFreeAd($order_obj->order_id))
                    || !$this->config->item('live')) {
                        $live = FALSE;
            }
                        
            // Transaction details can be retreived using GetTransactionDetails or GetExpressCheckoutDetails
            // GetTransactionDetails requires a Transaction ID, and GetExpressCheckoutDetails requires the Token returned by SetExpressCheckOut
            $ppdata = '&TOKEN=' . urlencode($token);
            $http_parsed_response_arr2 = $this->paypalmodel->sendPayPalTransaction('GetExpressCheckoutDetails', $ppdata, $live);

            //insert the GetExpressCheckoutDetails even if an error is returned
            $this->paypalmodel->insertOrderReceipt($http_parsed_response_arr2);
            $data['http_parsed_response_arr2'] = $http_parsed_response_arr2;
            $data['PAYMENTSTATUS'] = $http_parsed_response_arr["PAYMENTINFO_0_PAYMENTSTATUS"];


            if ("SUCCESS" == mb_strtoupper($http_parsed_response_arr2["ACK"]) || "SUCCESSWITHWARNING" == mb_strtoupper($http_parsed_response_arr2["ACK"])) {

                $data['GetExpressCheckoutDetails'] = 'SUCCESS';
              
                /*
                 * The payment status is returned from DoExpressCheckoutPayment, however we need a lot
                 * of the receipt info from GetExpressCheckoutDetails, so if that fails, dont send the receipts
                 * out but output a message in the view.
                */   
            
                if($data['PAYMENTSTATUS'] == 'Completed') {  //otherwise the payment could be pending
                
                    /* email the receipt */
                    $html_email_receipt = $this->load->view('paymentEmailReceipt', $data, TRUE);
        
                    $mail_receipt = array(
                                    "from" => 'server',
                                    "to" => $order->email,
                                    "subject" => 'Transaction Receipt from Ramblur',
                                    "message" => $html_email_receipt
                                );
		    
		    $this->ad->updateAdOrder($order_obj->order_id, $totalTaxAmount, $subTotal, $grandTotal, 1, date('Y-m-d H:i:s'));
				
                    $this->globals->externalMail($mail_receipt, 'NO_LINE_BREAKS');
        
                    // Activate the ad on Ramblur as payment was successful (paid set to 1) unless
                    // the ad has no image (awaiting artwork).
                    $activate = TRUE;
                    if ($data["ad"]->style === 'skyscraper') {
                        if (empty($data["ad"]->fileName) || empty($data["ad"]->deviceFileName)) {
                            $activate = FALSE;
                        }
                    } else {
                        if (empty($data["ad"]->fileName)) {
                            $activate = FALSE;
                        }
                    }

                    if ($activate) {
                    $this->ad->setAdStatus($order_obj->order_id, 1, 1,  'Active', 1);
                        $data["initialStatusId"] = 1;
                    } else {
                        $this->ad->setAdStatus($order_obj->order_id, 0, 13,  'Awaiting Artwork', 1);
                        $data["initialStatusId"] = 13;
                    }
                    
                    //if a free ad admin ad - update status
                    if ($this->ad->checkAdAdminFreeAd($order_obj->order_id)) {
                        if ($adAdminFreeAd) {
                            $statusId = 3; //free ad via paypal sandbox
                        } else {
                            $statusId = 4; //free ad via paypal live (i.e. paid for in normal way)
                        }
                        $this->ad->setAdAdminFreeAdStatus($order_obj->order_id, $statusId);
                        
                    //if it is a join free ad, update status so the placeholder is not seen again!
                        
                    }
                    if ($data["ad"]->joinBonusAdId) {
                        $this->ad->setAdStatusId($data["ad"]->joinBonusAdId, 18);
                    }
                        
        
                    /*
                     * @todo Change this hardish coding!
                     * This info needs to come from ref data as may change 
                     * - jb - !
                     */
                    if ($data["ad"]->style === 'banner' && $data["ad"]->cost_model === 'fixed') {  
                        if ($data["ad"]->postChildId == 0 || $data["ad"]->postChildId == '') { //ensures only 1 post ad is created even if page is refreshed
                            $this->ad->createBonusAd($order_obj->order_id, 'post', 'post', 'FREE', 'Free Post Ad - available');
                        }
                    }
                    ////end hardish coding!////
        
                    $this->ad->createAllBonusAds($order_obj->order_id);
        
                    // if is is a classified ad, then need to deduct any credit used during payment
                    if ($data["ad"]->mainAdCategory === 'classified') { 
                        $this->ad->setAdCredit($data["ad"]->userId, $data["ad"]->creditUsed);
                    }
        
                } else {             
                                        
                    if($data['PAYMENTSTATUS'] === 'Pending') {
                        $this->ad->updateAdStatus($order_obj->order_id, 'Payment Pending');
                    }
                }    
                    
            } else { // GetExpressCheckoutDetails failed
              
                $data['GetExpressCheckoutDetails'] = 'FAILED';
                              
            }
        
        } else { // DoExpressCheckoutPayment failed
            
            // DoExpressCheckoutPayment failed as the ACK returned was not SUCCESS or SUCCESSWITHWARNING 
            // or grand totals before and after did not match.
                  
            $data['DoExpressCheckoutPayment'] = 'FAILED';
        }
        $this->load->view('paymentReceipt', $data);
    }
    
}


