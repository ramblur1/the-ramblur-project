<?php
if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}


/*
 * 
 * MasterCard Payment Gateway (allows all major credit cards).
 * 
 */
class Mastercard extends CI_Controller {

    const GATEWAYURL = "https://secure.uat.tnspayments.com/api/nvp/version/36";
    
    //private $merchantId             = "TESTJONNYB-TEST";
    private $merchantId = "TESTHTEST1";
    //private $apiUserName            = "Merchant.TESTJONNYB-TEST";
    private $apiUserName = "Merchant.TESTHTEST1";
    //private $apiPassword            = "5be49e88d2e0b72698788f2294ecaf41"; //TESTJONNYB-TEST
    private $apiPassword = "4ee8df85ad582242d39f28dfb3abf9e7"; //TESTHTEST1";

    const MERCHANTNAME = 'Merchant Name Goes Here'; 
    /*
     * Only 1 ad is ever purchased at a time.
     * If this requirement changes, item quantity will be passed 
     * in as a parameter as part of the order. 
     *  
     */

    const ITEMQUANTITY = '1';
    //note that the system curr code in ad_config has a space in front for formatting
    const CURRENCYCODE = 'AUD';
    const TAX_TYPE_CODE = 'GST';
    const TAX_RATE_PERCENT = '0.10';
    const DEBUG = FALSE;

    private $curlobj;

    function __construct() {
        parent::__construct();
        $this->curlObj = curl_init();
    }

    // free cURL resources/session
    function __destruct() {
        curl_close($this->curlObj);
    }

    
    function pay() {
        
         $this->load->model('user');
        // check the authentication
        if (!$this->user->authCheck()) {
            return;
        }
        
        $this->load->model('ad');

        $order = new stdClass();
        $order->adId = $this->input->post('item_code')[0];
        $order->total = $this->input->post('Amount');
        $order->currencyCode = self::CURRENCYCODE;
        $order->productDescription = $this->input->post('item_desc')[0];

        
        // Get price from database for security reasons
        $ad = $this->ad->get_ad_price($order->adId);

        if ($ad->price != $order->total) {
            log_message('error', 'mastercard.php - pay() : Order prices sent and retrieved do not match for order ID: ' . $order->adId);
            return;
        }


        $taxExclusivePrice = round($ad->price / (1 + self::TAX_RATE_PERCENT), 2);
        $totalTaxAmount = $ad->price - $taxExclusivePrice;
        $productDescription = $ad->product_desc . ' ' . $ad->product_name;

        $request = $this->buildRequest($order, $ad, $taxExclusivePrice, $totalTaxAmount, $productDescription);

        $response = $this->sendTransaction($request);

        $parsed_array = $this->parse_from_nvp($response);

        //Store the successIndicator for later use in the receipt page.
        $successIndicator = $parsed_array['successIndicator'];

        //The session ID is passed to the Checkout.configure() in order for a 
        //return to Ramblur after payment completes
        $data['sessionId'] = $parsed_array['session.id'];

        $data['order'] = $order;
        $data['merchantId'] = $this->merchantId;
        $data['merchantName'] = self::MERCHANTNAME;
        $this->load->view('masterCardPayment', $data);
                
    }

    public function parse_from_nvp($string) {
        
         $this->load->model('user');
        // check the authentication
        if (!$this->user->authCheck()) {
            return;
        }
        
        $array = array();
        $pairArray = array();
        $param = array();
        if (mb_strlen($string) != 0) {
            $pairArray = explode("&", $string);
            foreach ($pairArray as $pair) {
                $param = explode("=", $pair);
                $array[urldecode($param[0])] = urldecode($param[1]);
            }
        }
        return $array;
    }

    // builds an NVP formatted request
    private function buildRequest($order, $ad, $taxExclusivePrice, $totalTaxAmount, $productDescription) {
        
         $this->load->model('user');
        // check the authentication
        if (!$this->user->authCheck()) {
            return;
        }
        
        $request = null;
        $request .= 'apiOperation=' . urlencode("CREATE_CHECKOUT_SESSION") . '&';
        //this would be the receipt screen on ramblur:
        // $request .= 'interaction.returnUrl=' . urlencode("http://localhost/advertising") . '&'; 
        $request .= 'interaction.cancelUrl=' . urlencode(base_url("advertising")) . '&';        

        $merchant = null;
        $merchant .= 'merchant=' . urlencode($this->merchantId) . '&';
        $merchant .= 'apiUsername=' . urlencode($this->apiUserName) . '&';
        $merchant .= 'apiPassword=' . urlencode($this->apiPassword);

        $orderItems = null;
        $index = 0; // if there is more than one order item in future, can loop here to build order.
        $orderItems .= '&order.item['.$index.'].description=' . urlencode($productDescription);
        $orderItems .= '&order.item['.$index.'].name=' . urlencode($order->adId);
        $orderItems .= '&order.item['.$index.'].quantity=' . urlencode(self::ITEMQUANTITY);
        $orderItems .= '&order.item['.$index.'].unitPrice=' . urlencode($taxExclusivePrice);
        $orderItems .= '&order.item['.$index.'].unitTaxAmount=' . urlencode($totalTaxAmount);
        $orderItems .= '&order.item['.$index.'].sku=' . urlencode($order->adId);

        $orderSummary = null;
        $orderSummary .= '&order.id=' . urlencode($order->adId);
        $orderSummary .= '&order.amount=' . urlencode($ad->price);
        $orderSummary .= '&order.currency=' . urlencode(self::CURRENCYCODE);


        return($request . $merchant . $orderItems . $orderSummary);
    }

    // Send transaction to the payment gateway server
    private function SendTransaction($request) {
        
         $this->load->model('user');
        // check the authentication
        if (!$this->user->authCheck()) {
            return;
        }
        

        curl_setopt($this->curlObj, CURLOPT_POSTFIELDS, $request);
        curl_setopt($this->curlObj, CURLOPT_URL, self::GATEWAYURL);

        // set the content length HTTP header
        curl_setopt($this->curlObj, CURLOPT_HTTPHEADER, array("Content-Length: " . mb_strlen($request)));

        // set the charset to UTF-8 (requirement of payment server)
        curl_setopt($this->curlObj, CURLOPT_HTTPHEADER, array("Content-Type: application/x-www-form-urlencoded;charset=UTF-8"));

        curl_setopt($this->curlObj, CURLOPT_RETURNTRANSFER, TRUE);

        // this is used for debugging only
        if (self::DEBUG) {
            curl_setopt($this->curlObj, CURLOPT_HEADER, TRUE);
            curl_setopt($this->curlObj, CURLINFO_HEADER_OUT, TRUE);
        }

        // send the transaction
        $response = curl_exec($this->curlObj);

        // this is used for debugging only
        if (self::DEBUG) {
            $requestHeaders = curl_getinfo($this->curlObj);
            $response = $requestHeaders["request_header"] . $response;
        }

        // assigns the cURL error to response if something went wrong so the caller can echo the error
        if (curl_error($this->curlObj)) {
            $response = "cURL Error: " . curl_errno($this->curlObj) . " - " . curl_error($this->curlObj);
        }

        // respond with the transaction result, or a cURL error message if it failed
        return $response;
    }

}
