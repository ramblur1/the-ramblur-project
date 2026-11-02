<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class Paypalmodel extends CI_Model {

    function __construct() {
        // Call the Model constructor
        parent::__construct();
    }


    function logPaypalSetCheckout($response, $order_id) {
        $L_ERRORCODE0 = '';
        if (!empty($response ['L_ERRORCODE0'])) {
            $L_ERRORCODE0 = urldecode($response ['L_ERRORCODE0']);
        }

        $L_SHORTMESSAGE0 = '';
        if (!empty($response ['L_SHORTMESSAGE0'])) {
            $L_SHORTMESSAGE0 = urldecode($response ['L_SHORTMESSAGE0']);
        }

        $L_LONGMESSAGE0 = '';
        if (!empty($response ['L_LONGMESSAGE0'])) {
            $L_LONGMESSAGE0 = urldecode($response ['L_LONGMESSAGE0']);
        }

        $L_SEVERITYCODE0 = '';
        if (!empty($response ['L_SEVERITYCODE0'])) {
            $L_SEVERITYCODE0 = urldecode($response ['L_SEVERITYCODE0']);
        }

        // if there is an error a token will not be returned
        $token = '';
        if (!empty($response ['TOKEN'])) {
            $token = urldecode($response ['TOKEN']);
        }
        
        $build = '';
        if (!empty($response['BUILD'])) {
            $build = urldecode($response['BUILD']);
        }
        
        $version = '';
        if (!empty($response['VERSION'])) {
            $version = urldecode($response['VERSION']);
        }
        
        $ack = '';
        if (!empty($response['ACK'])) {
            $ack = urldecode($response['ACK']);
        }
        
        $correlationid = '';
        if (!empty($response['CORRELATIONID'])) {
            $correlationid = urldecode($response['CORRELATIONID']);
        }
        
        $timestamp = '';
        if (!empty($response['TIMESTAMP'])) {
            $timestamp = date("Y-m-d h:i:s", strtotime(urldecode($response ['TIMESTAMP'])));
        }
        

        $set_checkout_response = array(
            "order_id" => $order_id,
            "token" => $token,
            "timestamp" => $timestamp,
            "correlation_id" => $correlationid,
            "ack" => $ack,
            "version" => $version,
            "build" => $build,
            "L_ERRORCODE0" => $L_ERRORCODE0,
            "L_SHORTMESSAGE0" => $L_SHORTMESSAGE0,
            "L_LONGMESSAGE0" => $L_LONGMESSAGE0,
            "L_SEVERITYCODE0" => $L_SEVERITYCODE0
        );

        $this->db->insert('paypal_set_checkout', $set_checkout_response);
        
    }


    function logPaypalCheckoutPayment($response) {

        // if ACK is SUCCESSWITHWARNING (toupper) then will get these extra 4 error fields
        // if ACK is FAILURE then will get these 4 error fields but not all the rest of the stuff
        
        // Note only collecting level 0 errors
        
        $L_ERRORCODE0 = '';
        if (!empty($response ['L_ERRORCODE0'])) {
            $L_ERRORCODE0 = urldecode($response ['L_ERRORCODE0']);
        }
        
        $L_SHORTMESSAGE0 = '';
        if (!empty($response ['L_SHORTMESSAGE0'])) {
            $L_SHORTMESSAGE0 = urldecode($response ['L_SHORTMESSAGE0']);
        }

        $L_LONGMESSAGE0 = '';
        if (!empty($response ['L_LONGMESSAGE0'])) {
            $L_LONGMESSAGE0 = urldecode($response ['L_LONGMESSAGE0']);
        }

        $L_SEVERITYCODE0 = '';
        if (!empty($response ['L_SEVERITYCODE0'])) {
            $L_SEVERITYCODE0 = urldecode($response ['L_SEVERITYCODE0']);
        }

        // This is not returned when paying by Paypal account
        $PAYMENTINFO_0_RECEIPTID = '';
        if (!empty($response ['PAYMENTINFO_0_RECEIPTID'])) {
            $PAYMENTINFO_0_RECEIPTID = urldecode($response ['PAYMENTINFO_0_RECEIPTID']);
        }

        $PAYMENTINFO_0_FEEAMT = '';
        if (!empty($response ['PAYMENTINFO_0_FEEAMT'])) {
            $PAYMENTINFO_0_FEEAMT = urldecode($response ['PAYMENTINFO_0_FEEAMT']);
        }
        
        $build = '';
        if (!empty($response['BUILD'])) {
            $build = urldecode($response['BUILD']);
        }
        
        $version = '';
        if (!empty($response['VERSION'])) {
            $version = urldecode($response['VERSION']);
        }
        
        $ack = '';
        if (!empty($response['ACK'])) {
            $ack = urldecode($response['ACK']);
        }
        
        $correlationid = '';
        if (!empty($response['CORRELATIONID'])) {
            $correlationid = urldecode($response['CORRELATIONID']);
        }
        
        $timestamp = '';
        if (!empty($response['TIMESTAMP'])) {
            $timestamp = date("Y-m-d h:i:s", strtotime(urldecode($response['TIMESTAMP'])));
        }
        
        
        $token = '';
        if (!empty($response ['TOKEN'])) {
            $token = urldecode($response ['TOKEN']);
        }
        
        $successpageredirectrequested = '';
        if (!empty($response ['SUCCESSPAGEREDIRECTREQUESTED'])) {
            $successpageredirectrequested = urldecode($response ['SUCCESSPAGEREDIRECTREQUESTED']);
        }
        
        $insuranceoptionselected = '';
        if (!empty($response ['INSURANCEOPTIONSELECTED'])) {
            $insuranceoptionselected = urldecode($response ['INSURANCEOPTIONSELECTED']);
        }
        
        $shippingoptionisdefault = '';
        if (!empty($response ['SHIPPINGOPTIONISDEFAULT'])) {
            $shippingoptionisdefault = urldecode($response ['SHIPPINGOPTIONISDEFAULT']);
        }
        
        
        if (mb_strtoupper($response ['ACK']) == 'FAILURE') {

            $paypal_checkout_payment = array(
                "TIMESTAMP" => $timestamp,
                "CORRELATIONID" => $correlationid,
                "ACK" => $ack,
                "VERSION" => $version,
                "BUILD" => $build,
                "L_ERRORCODE0" => $L_ERRORCODE0,
                "L_SHORTMESSAGE0" => $L_SHORTMESSAGE0,
                "L_LONGMESSAGE0" => $L_LONGMESSAGE0,
                "L_SEVERITYCODE0" => $L_SEVERITYCODE0
            );
        } else {

            $paypal_checkout_payment = array(
                "TOKEN" => $token,
                "SUCCESSPAGEREDIRECTREQUESTED" => $successpageredirectrequested,
                "TIMESTAMP" => $timestamp,
                "CORRELATIONID" => $correlationid,
                "ACK" => $ack,
                "VERSION" => $version,
                "BUILD" => $build,
                "INSURANCEOPTIONSELECTED" => $insuranceoptionselected,
                "SHIPPINGOPTIONISDEFAULT" => $shippingoptionisdefault,
                'PAYMENTINFO_0_TRANSACTIONID' => urldecode($response ['PAYMENTINFO_0_TRANSACTIONID']),
                'PAYMENTINFO_0_RECEIPTID' => $PAYMENTINFO_0_RECEIPTID,
                'PAYMENTINFO_0_TRANSACTIONTYPE' => urldecode($response ['PAYMENTINFO_0_TRANSACTIONTYPE']),
                'PAYMENTINFO_0_PAYMENTTYPE' => urldecode($response ['PAYMENTINFO_0_PAYMENTTYPE']),
                'PAYMENTINFO_0_ORDERTIME' => urldecode($response ['PAYMENTINFO_0_ORDERTIME']),
                'PAYMENTINFO_0_AMT' => urldecode($response ['PAYMENTINFO_0_AMT']),
                'PAYMENTINFO_0_FEEAMT' => $PAYMENTINFO_0_FEEAMT,
                'PAYMENTINFO_0_TAXAMT' => urldecode($response ['PAYMENTINFO_0_TAXAMT']),
                'PAYMENTINFO_0_CURRENCYCODE' => urldecode($response ['PAYMENTINFO_0_CURRENCYCODE']),
                'PAYMENTINFO_0_PAYMENTSTATUS' => urldecode($response ['PAYMENTINFO_0_PAYMENTSTATUS']),
                'PAYMENTINFO_0_PENDINGREASON' => urldecode($response ['PAYMENTINFO_0_PENDINGREASON']),
                'PAYMENTINFO_0_REASONCODE' => urldecode($response ['PAYMENTINFO_0_REASONCODE']),
                'PAYMENTINFO_0_PROTECTIONELIGIBILITY' => urldecode($response ['PAYMENTINFO_0_PROTECTIONELIGIBILITY']),
                'PAYMENTINFO_0_PROTECTIONELIGIBILITYTYPE' => urldecode($response ['PAYMENTINFO_0_PROTECTIONELIGIBILITYTYPE']),
                'PAYMENTINFO_0_SECUREMERCHANTACCOUNTID' => urldecode($response ['PAYMENTINFO_0_SECUREMERCHANTACCOUNTID']),
                'PAYMENTINFO_0_ERRORCODE' => urldecode($response ['PAYMENTINFO_0_ERRORCODE']),
                'PAYMENTINFO_0_ACK' => urldecode($response ['PAYMENTINFO_0_ACK']),
                "L_ERRORCODE0" => $L_ERRORCODE0,
                "L_SHORTMESSAGE0" => $L_SHORTMESSAGE0,
                "L_LONGMESSAGE0" => $L_LONGMESSAGE0,
                "L_SEVERITYCODE0" => $L_SEVERITYCODE0
            );
        }

        $this->db->insert('paypal_checkout_payment', $paypal_checkout_payment);
    }

    /*
     * Inserts an ad order received from PayPal
     *
     * @param array $http_parsed_response_arr
     *
     *
     */

    function insertOrderReceipt($http_parsed_response_arr) {

        /*
         *
         * PAYMENTREQUESTINFO_n_SHORTMESSAGE Payment error short message. You can specify up to 10 payments, where n is a digit between 0 and 9, inclusive.
         * PAYMENTREQUESTINFO_n_LONGMESSAGE Payment error long message. You can specify up to 10 payments, where n is a digit between 0 and 9, inclusive.
         * PAYMENTREQUESTINFO_n_ERRORCODE Payment error code. You can specify up to 10 payments, where n is a digit between 0 and 9, inclusive.
         * PAYMENTREQUESTINFO_n_SEVERITYCODE Payment error severity code. You can specify up to 10 payments, where n is a digit between 0 and 9, inclusive.
         *
         */

        // if ACK is FAILURE or SUCCESSWITHWARNING (toupper) then will get these extra 4 error fields
        $PAYMENTREQUESTINFO_0_ERRORCODE = '';
        if (!empty($http_parsed_response_arr ['PAYMENTREQUESTINFO_0_ERRORCODE'])) {
            $PAYMENTREQUESTINFO_0_ERRORCODE = urldecode($http_parsed_response_arr ['PAYMENTREQUESTINFO_0_ERRORCODE']);
        }

        $PAYMENTREQUESTINFO_0_SHORTMESSAGE = '';
        if (!empty($http_parsed_response_arr ['PAYMENTREQUESTINFO_0_SHORTMESSAGE'])) {
            $PAYMENTREQUESTINFO_0_SHORTMESSAGE = urldecode($http_parsed_response_arr ['PAYMENTREQUESTINFO_0_SHORTMESSAGE']);
        }

        $PAYMENTREQUESTINFO_0_LONGMESSAGE = '';
        if (!empty($http_parsed_response_arr ['PAYMENTREQUESTINFO_0_LONGMESSAGE'])) {
            $PAYMENTREQUESTINFO_0_LONGMESSAGE = urldecode($http_parsed_response_arr ['PAYMENTREQUESTINFO_0_LONGMESSAGE']);
        }

        $PAYMENTREQUESTINFO_0_SEVERITYCODE = '';
        if (!empty($http_parsed_response_arr ['PAYMENTREQUESTINFO_0_SEVERITYCODE'])) {
            $PAYMENTREQUESTINFO_0_SEVERITYCODE = urldecode($http_parsed_response_arr ['PAYMENTREQUESTINFO_0_SEVERITYCODE']);
        }

        
     //note need to check for all values below as above !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
        $order_receipt_arr = array(
	    "adId" => 0,
            "amount" => urldecode($http_parsed_response_arr ['AMT']),
            "item_amount" => urldecode($http_parsed_response_arr ['ITEMAMT']),
            "tax_amount" => urldecode($http_parsed_response_arr ['TAXAMT']),
            "currency_code" => urldecode($http_parsed_response_arr ['CURRENCYCODE']),
            "product_name" => urldecode($http_parsed_response_arr ['L_NAME0']),
            "order_id" => urldecode($http_parsed_response_arr ['L_NUMBER0']),
            "quantity" => urldecode($http_parsed_response_arr ['L_QTY0']),
            "l_tax_amt" => urldecode($http_parsed_response_arr ['L_TAXAMT0']),
            "l_amt" => urldecode($http_parsed_response_arr ['L_AMT0']),
            "token" => urldecode($http_parsed_response_arr ['TOKEN']),
            "checkout_status" => urldecode($http_parsed_response_arr ['CHECKOUTSTATUS']),
            "timestamp" => date("Y-m-d h:i:s", strtotime(urldecode($http_parsed_response_arr ['TIMESTAMP']))),
            "correlation_id" => urldecode($http_parsed_response_arr ['CORRELATIONID']),
            "ack" => urldecode($http_parsed_response_arr ['ACK']),
            "payer_id" => urldecode($http_parsed_response_arr ['PAYERID']),
            "payer_status" => urldecode($http_parsed_response_arr ['PAYERSTATUS']),
            "firstname" => urldecode($http_parsed_response_arr ['FIRSTNAME']),
            "lastname" => urldecode($http_parsed_response_arr ['LASTNAME']),
            "email" => urldecode($http_parsed_response_arr ['EMAIL']),
            "country_code" => urldecode($http_parsed_response_arr ['COUNTRYCODE']),
            "rqst_error_code" => urldecode($http_parsed_response_arr ['PAYMENTREQUESTINFO_0_ERRORCODE']),
            "rqst_transaction_id" => urldecode($http_parsed_response_arr ['PAYMENTREQUEST_0_TRANSACTIONID']),
            "rqstinfo_transaction_id" => urldecode($http_parsed_response_arr ['PAYMENTREQUESTINFO_0_TRANSACTIONID']),
            "rqst_currency_code" => urldecode($http_parsed_response_arr ['PAYMENTREQUEST_0_CURRENCYCODE']),
            "rqst_amount" => urldecode($http_parsed_response_arr ['PAYMENTREQUEST_0_AMT']),
            "rqst_item_amount" => urldecode($http_parsed_response_arr ['PAYMENTREQUEST_0_ITEMAMT']),
            "rqst_tax_amount" => urldecode($http_parsed_response_arr ['PAYMENTREQUEST_0_TAXAMT']),
            "L_PAYMENTREQUEST_0_NUMBER0" => urldecode($http_parsed_response_arr ['L_PAYMENTREQUEST_0_NUMBER0']),
            "L_PAYMENTREQUEST_0_TAXAMT0" => urldecode($http_parsed_response_arr ['L_PAYMENTREQUEST_0_TAXAMT0']),
            "L_PAYMENTREQUEST_0_AMT0" => urldecode($http_parsed_response_arr ['L_PAYMENTREQUEST_0_AMT0']),
            "PAYMENTREQUESTINFO_0_ERRORCODE" => $PAYMENTREQUESTINFO_0_ERRORCODE,
            "PAYMENTREQUESTINFO_0_SHORTMESSAGE" => $PAYMENTREQUESTINFO_0_SHORTMESSAGE,
            "PAYMENTREQUESTINFO_0_LONGMESSAGE" => $PAYMENTREQUESTINFO_0_LONGMESSAGE,
            "PAYMENTREQUESTINFO_0_SEVERITYCODE" => $PAYMENTREQUESTINFO_0_SEVERITYCODE
                )
        // "billingagreementstatus" => urldecode($http_parsed_response_arr['BILLINGAGREEMENTACCEPTEDSTATUS']),
        ;

        $this->db->insert('ad_order_receipt', $order_receipt_arr);
    }
    
    
    function logPaypalMassPayResponse($response) {

        $correlationId = '';
        if (!empty($response['CORRELATIONID'])) {
            $correlationId = urldecode($response['CORRELATIONID']);
        }

        $ack = '';
        if (!empty($response['ACK'])) {
            $ack = urldecode($response['ACK']);
        }

        $version = '';
        if (!empty($response['VERSION'])) {
            $version = urldecode($response['VERSION']);
        }

        $build = '';
        if (!empty($response['BUILD'])) {
            $build = urldecode($response['BUILD']);
        }

        $L_ERRORCODE0 = '';
        if (!empty($response ['L_ERRORCODE0'])) {
            $L_ERRORCODE0 = urldecode($response ['L_ERRORCODE0']);
        }

        $L_SHORTMESSAGE0 = '';
        if (!empty($response ['L_SHORTMESSAGE0'])) {
            $L_SHORTMESSAGE0 = urldecode($response ['L_SHORTMESSAGE0']);
        }

        $L_LONGMESSAGE0 = '';
        if (!empty($response ['L_LONGMESSAGE0'])) {
            $L_LONGMESSAGE0 = urldecode($response ['L_LONGMESSAGE0']);
        }

        $L_SEVERITYCODE0 = '';
        if (!empty($response ['L_SEVERITYCODE0'])) {
            $L_SEVERITYCODE0 = urldecode($response ['L_SEVERITYCODE0']);
        }

        /*
          // just to check if any level 1 errors are returned
          $L_ERRORCODE1 = '';
          if (!empty($response ['L_ERRORCODE1'])) {
          $L_ERRORCODE1 = urldecode($response ['L_ERRORCODE1']);
          }

          $L_SHORTMESSAGE1 = '';
          if (!empty($response ['L_SHORTMESSAGE1'])) {
          $L_SHORTMESSAGE1 = urldecode($response ['L_SHORTMESSAGE1']);
          }

          $L_LONGMESSAGE1 = '';
          if (!empty($response ['L_LONGMESSAGE5'])) {
          $L_LONGMESSAGE1 = urldecode($response ['L_LONGMESSAGE5']);
          }

          $L_SEVERITYCODE1 = '';
          if (!empty($response ['L_SEVERITYCODE1'])) {
          $L_SEVERITYCODE1 = urldecode($response ['L_SEVERITYCODE1']);
          }
         * 
         */

        $timestamp = '';
        if (!empty($response['TIMESTAMP'])) {
            $timestamp = date("Y-m-d h:i:s", strtotime(urldecode($response ['TIMESTAMP'])));
        }

        $paypalMassPaymentResonse = array(
            "CORRELATIONID" => $correlationId,
            "ACK" => $ack,
            "VERSION" => $version,
            "BUILD" => $build,
            "L_ERRORCODE0" => $L_ERRORCODE0,
            "L_SHORTMESSAGE0" => $L_SHORTMESSAGE0,
            "L_LONGMESSAGE0" => $L_LONGMESSAGE0,
            "L_SEVERITYCODE0" => $L_SEVERITYCODE0,
            "TIMESTAMP" => $timestamp
        );

        $this->db->insert('paypalDistributedPaymentsResponse', $paypalMassPaymentResonse);
    }
    
    
    
    // Posts the data to PayPal
    function sendPayPalTransaction($methodName_, $nvpStr_, $live) {
        
        /* auth check done in the controller as this is also run by cron! */
        
        // Set up your API credentials, PayPal end point, and API version.
        if ($live) {
            $API_UserName = urlencode($this->config->item('paypalApiUserName'));
            $API_Password = urlencode($this->config->item('paypalApiPassword'));
            $API_Signature = urlencode($this->config->item('paypalApiSignature'));
            $API_Endpoint = $this->config->item('PAYPAL_LIVE_API_ENDPOINT');
        } else {
            $API_UserName = urlencode($this->config->item('paypalApiUserNameSandBox'));
            $API_Password = urlencode($this->config->item('paypalApiPasswordSandBox'));
            $API_Signature = urlencode($this->config->item('paypalApiSignatureSandBox'));
            $API_Endpoint = $this->config->item('PAYPAL_SANDBOX_API_ENDPOINT');
        }
        $version = urlencode($this->config->item('API_VERSION'));

        // Set the curl parameters.
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $API_Endpoint);
        curl_setopt($ch, CURLOPT_VERBOSE, 1);

        // Turn off the server and peer verification (TrustManager Concept).
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        
        /**************************************************************/
        // configure proxy server if required
        /**************************************************************/
        //
        //
        curl_setopt($ch, CURLOPT_PROXY, $this->config->item('ramblur_internal_proxy') . $this->config->item('ramblur_internal_proxy_port'));
        //curl_setopt($ch, CURLOPT_PROXYUSERPWD, $this->config->item('CURLOPT_PROXYUSERPWD'));
        //curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, 1);
        //curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP); 
        //
        /**************************************************************/

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);

        // Set the API operation, version, and API signature in the request.
        $nvpreq = "METHOD=$methodName_&VERSION=$version&PWD=$API_Password&USER=$API_UserName&SIGNATURE=$API_Signature$nvpStr_";

        // Set the request as a POST FIELD for curl.
        curl_setopt($ch, CURLOPT_POSTFIELDS, $nvpreq);

        // Get the response from the server.
        $httpResponse = curl_exec($ch);

        if (!$httpResponse) {
            exit("$methodName_ failed: " . curl_error($ch) . '(' . curl_errno($ch) . ')');
        }

        // Extract the response details.
        $httpResponseAr = explode("&", $httpResponse);

        $http_parsed_response_arr = array();
        foreach ($httpResponseAr as $i => $value) {
            $tmpAr = explode("=", $value);
            if (sizeof($tmpAr) > 1) {
                $http_parsed_response_arr[$tmpAr[0]] = $tmpAr[1];
            }
        }

        if ((0 == sizeof($http_parsed_response_arr)) || !array_key_exists('ACK', $http_parsed_response_arr)) {
            exit("Invalid HTTP Response for POST request($nvpreq) to $API_Endpoint.");
        }

        return $http_parsed_response_arr;
    } 
    
    function setPaymentSession($id, $itemName, $itemCode, $itemPrice, $itemQty, $taxTotal, $grandTotal, $userId, $adAdminFreeAd) {
        
        $sessionArray = array (   
                'id' => $id,
                'itemName' => $itemName,
                'itemCode' => $itemCode,
                'itemPrice' => $itemPrice,
                'itemQty' => $itemQty,
                'taxTotal' => $taxTotal,
                'grandTotal' => $grandTotal,
                'userId' => $userId,
                'adAdminFreeAd' => $adAdminFreeAd
                );
        
        $this->db->insert('paymentSession', $sessionArray);
        
    }
    
    function getPaymentSession($id, $userId) {

        $this->db->select('*');
        $this->db->from('paymentSession');
        $this->db->where('id', $id);
        $this->db->where('userId', $userId);
        $query = $this->db->get();

        if ($query->num_rows() > 0) {
            $sessionArr = $query->result_array();
            return ($sessionArr);
        } else {
            return (NULL);
        }
    }

    /* IPN functions */
    
    function insertPaypalExpressCheckoutIPN($IpnArray) {

        $this->db->insert('paypalExpressCheckoutIPN', $IpnArray);
    }

    function updateDistributedPayments($IpnArray) {

        $this->db->update_batch('distributedPayments', $IpnArray, 'paymentId');
    }

    function logPaypalIpn($txn_type, $ipn_track_id) {

        $this->db->insert('paypalIpnLog', array('txn_type' => $txn_type, 'ipn_track_id' => $ipn_track_id));
    }
    
    /* end IPN functions */
        
    
}
