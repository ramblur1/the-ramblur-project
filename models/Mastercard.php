<?php

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Mastercard extends CI_Model {

    const GATEWAYURL = "https://secure.uat.tnspayments.com/api/nvp/version/36";

    //private $merchantId             = "TESTJONNYB-TEST";
    private $merchantId = "TESTHTEST1";
    //private $apiUserName            = "Merchant.TESTJONNYB-TEST";
    private $apiUserName = "Merchant.TESTHTEST1";
    //Merchant.TESTHTEST1
    // private $apiPassword            = "5be49e88d2e0b72698788f2294ecaf41";
    private $apiPassword = "4ee8df85ad582242d39f28dfb3abf9e7";

    /*
     * Only 1 ad is ever purchased at a time.
     * If this requirement changes, item quantity will be passed 
     * in as a parameter as part of the order. 
     *  
     */

    const ITEMQUANTITY = '1';
    //not that the system curr code in ad_config has a space in front!!!
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

    function pay($order) {

        $this->load->model('ad');

        $order = json_decode($order);

        log_message('error', 'Welcome to the MasterCard Payment Gateway. adId is : ' . $order->adId);
        log_message('error', 'Welcome to the MasterCard Payment Gateway. Total is : ' . $order->total);
        log_message('error', 'Welcome to the MasterCard Payment Gateway. Curr code is : ' . $order->currencyCode);
        log_message('error', 'Welcome to the MasterCard Payment Gateway. Company Name is : ' . $order->companyName);


        // Get price from database for security reasons
        $ad = $this->ad->get_ad_price($order->adId);

        if ($ad->price != $order->total) {
            log_message('error', 'mastercard.php - pay() : Order prices sent and retrieved do not match for order ID: ' . $order->adId);
            return;
        }


        $taxExclusivePrice = round($ad->price / (1 + self::TAX_RATE_PERCENT), 2);
        $totalTaxAmount = $ad->price - $taxExclusivePrice;
        $productDescription = $ad->product_desc . ' ' . $ad->product_name;

        //not sure if i need to call this yet as customer order details can be passed with order - see later!!!!
        //$customer = $this->ad->get_ad_order_details($product_code);


        $request = $this->buildRequest($order, $ad, $taxExclusivePrice, $totalTaxAmount, $productDescription);

        //return($request);

        $response = $this->sendTransaction($request);


        $parsed_array = $this->parse_from_nvp($response);

        //Store the successIndicator for later use in the receipt page.
        $successIndicator = $parsed_array['successIndicator'];

        //The session ID is passed to the Checkout.configure() function below.
        $session_id = $parsed_array['session.id'];

        //This will return the whole order detail, not just the session Id
        return($session_id);
    }

    public function parse_from_nvp($string) {
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

        $request = null;
        $request .= 'apiOperation=' . urlencode("CREATE_CHECKOUT_SESSION") . '&';

        $merchant = null;
        $merchant .= 'merchant=' . urlencode($this->merchantId) . '&';
        $merchant .= 'apiUsername=' . urlencode($this->apiUserName) . '&';
        $merchant .= 'apiPassword=' . urlencode($this->apiPassword);

        $orderItems = null;
        $index = 0; // if there is more than one order item in future, can loop here to build order
        $orderItems .= '&order.item[' . $index . '].description=' . urlencode($productDescription);
        $orderItems .= '&order.item[' . $index . '].name=' . urlencode($order->adId);
        $orderItems .= '&order.item[' . $index . '].quantity=' . urlencode(self::ITEMQUANTITY);
        $orderItems .= '&order.item[' . $index . '].unitPrice=' . urlencode($taxExclusivePrice);
        $orderItems .= '&order.item[' . $index . '].unitTaxAmount=' . urlencode($totalTaxAmount);
        $orderItems .= '&order.item[' . $index . '].sku=' . urlencode($order->adId);

        $orderSummary = null;
        $orderSummary .= '&order.id=' . urlencode($order->adId);
        $orderSummary .= '&order.amount=' . urlencode($ad->price);
        $orderSummary .= '&order.currency=' . urlencode(self::CURRENCYCODE);


        return($request . $merchant . $orderItems . $orderSummary);
    }

    // Send transaction to the payment gateway server
    private function SendTransaction($request) {

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
