<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class PaypalIPN extends CI_Controller {
    /*
     * Possible responses from PayPal after the request is issued.
     * 
     */

    const VALID = 'VERIFIED';
    const INVALID = 'INVALID';

    /**
     * Sets the IPN verification mode 
     * Set in config/paypal.php
     * @return bool
     * 
     */
    public function useSandbox() {
        return $this->config->item('IPN_SANDBOX');
    }

    /**
     * Determine endpoint to post the verification data to.
     * @return string
     */
    public function getPaypalUri() {
        if ($this->useSandbox()) {
            return $this->config->item('SANDBOX_VERIFY_URI');
        } else {
            return $this->config->item('VERIFY_URI');
        }
    }

    /**
     * Verification Function
     * Sends the incoming post data back to paypal using cURL
     * do it can be verified before use.
     *
     * @return bool
     * @throws Exception
     * 
     */
    public function index() {
        
        if (!$this->input->post()) {
            log_message('error', 'paypalIPN listener received no post data');
            throw new Exception("Missing POST Data");
        }
        
        $this->load->model('paypalmodel');

        $paypalIpnPostData = file_get_contents('php://input');

        /*  for testing only
         * 
         * 
         * Mass Pay Test
         * 
         * 
         * $paypalIpnPostData =
         * 
         * "mc_fee_4=0.00
         * &mc_fee_5=0.00
         * &payer_id=ZH62ZUKP2DYRW
         * &payment_date=19:01:53 Oct 11, 2016 PDT
         * &payment_gross_1=
         * &payment_gross_2=
         * &payment_gross_3=
         * &payment_gross_4=
         * &payment_gross_5=
         * &payment_status=Completed
         * &receiver_email_1=paypal@ramblur.com
         * &receiver_email_2=paypal@ramblur.com
         * &charset=windows-1252
         * &receiver_email_3=paypal@ramblur.com
         * &receiver_email_4=paypal@ramblur.com
         * &mc_currency_1=AUD
         * &masspay_txn_id_1=6MG87039GK208534T
         * &receiver_email_5=paypal@ramblur.com
         * &mc_currency_2=AUD
         * &masspay_txn_id_2=1EE84922G4223643U
         * &mc_currency_3=AUD
         * &masspay_txn_id_3=47L73201SR453631F
         * &mc_currency_4=AUD
         * &masspay_txn_id_4=83F29447XW411032S
         * &first_name=ramblur
         * &mc_currency_5=AUD
         * &masspay_txn_id_5=9RE740352F407193B
         * &unique_id_1=1
         * &notify_version=3.8
         * &unique_id_2=2
         * &unique_id_3=3
         * &unique_id_4=4
         * &unique_id_5=5
         * &payer_status=verified
         * &verify_sign=AFcWxV21C7fd0v3bYYYRCpSSRl31ACBoTuPn6TinwsUZBpOiTUVMMnYa
         * &payer_email=ram20@ramblur.com
         * &payer_business_name=ramblur Buchanan's Test Store
         * &last_name=Buchanan
         * &status_1=Completed
         * &status_2=Completed
         * &status_3=Completed
         * &txn_type=masspay
         * &status_4=Completed
         * &status_5=Completed
         * &mc_gross_1=0.01
         * &mc_gross_2=0.01
         * &mc_gross_3=0.01
         * &payment_fee_1=
         * &residence_country=AU
         * &test_ipn=1
         * &mc_gross_4=0.01
         * &payment_fee_2=
         * &mc_gross_5=0.01
         * &payment_fee_3=
         * &mc_fee_1=0.00
         * &payment_fee_4=
         * &mc_fee_2=0.00
         * &payment_fee_5=
         * &mc_fee_3=0.00
         * &ipn_track_id=1e729e11eeded"
         * 
         * 
         * 
         * 
         * Express Checkout Test (cart)
         * 
         * Note txn_type is cart (but can also be express_checkout if only 1 item sent I believe)
         * 
         * $paypalIpnPostData =
         * 
         * "mc_gross=1750.00
         * &protection_eligibility=Eligible
         * &item_number1=292
         * &payer_id=KRYTNRYFY6B5Q
         * &tax=159.09
         * &payment_date=21:30:54 Oct 11, 2016 PDT
         * &payment_status=Completed
         * &charset=windows-1252
         * &mc_shipping=0.00
         * &mc_handling=0.00
         * &first_name=Ramblur
         * &mc_fee=42.30
         * &notify_version=3.8
         * &custom=&
         * payer_status=verified
         * &business=ram20@ramblur.com
         * &num_cart_items=1
         * &verify_sign=AtzgbXK1X.9xdTmY4kIxKn.3Mc4LAw3JbGeDsa8Bh1THSGA6PL5n1fbH
         * &payer_email=paypal@ramblur.com
         * &tax1=0.00
         * &txn_id=7WU27983AA029933F
         * &payment_type=instant
         * &payer_business_name=Ramblur Forum's Test Store
         * &last_name=Forum
         * &item_name1=Corporate cpm Banner Ad
         * &receiver_email=ram20@ramblur.com
         * &payment_fee=
         * &quantity1=1
         * &receiver_id=ZH62ZUKP2DYRW
         * &txn_type=cart
         * &mc_gross_1=1590.91
         * &mc_currency=AUD
         * &residence_country=AU
         * &test_ipn=1
         * &transaction_subject=
         * &payment_gross=
         * &ipn_track_id=9fc161c595a99";
         * 
         * 
         * 
         * end test
         * 
         */

        //may want to flag pending!!

        log_message('error', $paypalIpnPostData);
        $paypalIpnPostArr = explode('&', $paypalIpnPostData);

        $ipnMessage = [];
        foreach ($paypalIpnPostArr as $keyval) {
            $keyval = explode('=', $keyval);
            if (count($keyval) == 2) {
                // Since we do not want the plus in the datetime string to be encoded to a space, we manually encode it.
                if ($keyval[0] === 'payment_date') {
                    if (mb_substr_count($keyval[1], '+') === 1) {
                        $keyval[1] = str_replace('+', '%2B', $keyval[1]);
                    }
                }
                $ipnMessage[$keyval[0]] = urldecode($keyval[1]);
            }
        }

        // Build the body of the verification post request, adding the _notify-validate command.
        $request = 'cmd=_notify-validate';
        $get_magic_quotes_exists = false;
        if (function_exists('get_magic_quotes_gpc')) {
            $get_magic_quotes_exists = true;
        }
        foreach ($ipnMessage as $key => $value) {
            if ($get_magic_quotes_exists == true && get_magic_quotes_gpc() == 1) {
                $value = urlencode(stripslashes($value));
            } else {
                $value = urlencode($value);
            }
            $request .= "&$key=$value";
        }

        $this->paypalmodel->logPaypalIpn($ipnMessage["txn_type"], $ipnMessage["ipn_track_id"]);
        //testing only
        echo "verifiction post to paypal :";
        echo $request;
        echo '<br/>';

        $paypalResponse = $this->getPaypalIpnVerification($request);

        // Check if paypal verfifes the IPN data, and if so, return true.
        if ($paypalResponse == self::VALID) {
            $this->processIpn($ipnMessage);
            return TRUE;
        } else {
            log_message('error', 'paypalIPN handler - PayPal did not verifiy the IPN');
            return FALSE;
        }
    }

    /*
     * Post the IPN data back to paypal, using curl, 
     * to get a verification that it is valid.
     *  
     * @return string
     * @throws Exception
     * 
     */

    function getPaypalIpnVerification($request) {

        $ch = curl_init($this->getPaypalUri());

        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $request);
        //curl_setopt($ch, CURLOPT_SSLVERSION, 6);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        // This is often required if the server is missing a global cert bundle, or is using an outdated one.
        if ($this->config->item('USE_LOCAL_CERTS')) {
            curl_setopt($ch, CURLOPT_CAINFO, __DIR__ . "/cert/cacert.pem");
        }
        curl_setopt($ch, CURLOPT_FORBID_REUSE, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Connection: Close']);

        $paypalResponse = curl_exec($ch);
        $info = curl_getinfo($ch);
        $http_code = $info['http_code'];

        if ($http_code != 200) {
            log_message('error', 'paypalIPN handler - PayPal responded with http code: ' . $http_code);
            throw new Exception("PayPal responded with http code $http_code");
        }

        if (!($paypalResponse)) {
            $errno = curl_errno($ch);
            $errstr = curl_error($ch);
            curl_close($ch);
            log_message('error', 'paypalIPN handler - cURL error: ' . '[' . $errno . ']' . $errstr);
            throw new Exception("cURL error: [$errno] $errstr");
        }
        curl_close($ch);
        return $paypalResponse;
    }

    function processIpn($ipn) {

        if (!empty($ipn)) {
            if ($ipn["txn_type"] === 'masspay') {
                $this->processMassPayIpn($ipn);
            } else if ($ipn["txn_type"] === 'cart' || $ipn["txn_type"] === 'express_checkout') {
                $this->processExpressCheckoutIpn($ipn);
            }
        } else {
            log_message('error', 'paypalIPN handler - Invalid IPN message');
        }
    }

    function processMassPayIpn($ipn) {
        
        $this->load->model('paypalmodel');

        log_message('error', 'tranny type =:::: ' . $ipn["txn_type"]);
        log_message('error', 'payer ID =:::: ' . $ipn["payer_id"]);
        log_message('error', 'ipn_track_id =:::: ' . $ipn["ipn_track_id"]);


        for ($i = 1; $i <= 250; $i++) {
            if (isset($ipn["unique_id_" . $i])) {
                log_message('error', 'unox' . $i . ':' . $ipn["unique_id_" . $i]);
                log_message('error', 'gross' . $i . ':' . $ipn["mc_gross_" . $i]);

                $pendingReason = NULL;
                if (isset($ipn["pending_reason"])) {
                    $pendingReason = $ipn["pending_reason"];
                }

                $reasonCode = NULL;
                if (isset($ipn["reason_code"])) {
                    $reasonCode = $ipn["reason_code"];
                }

                $reasonCode_x = NULL;
                if (isset($ipn["reason_code" . $i])) {
                    $reasonCode_x = $ipn["reason_code" . $i];
                }

                $paymentClaimed = 0;
                $paymentClaimedDate = NULL;
                if ($ipn["status_" . $i] === 'Completed') {
                    $paymentClaimed = 1;
                    $paymentClaimedDate = $ipn["payment_date"];
                }

                $payerBusinessName = NULL;
                if (isset($ipn["payer_business_name"])) {
                    $payerBusinessName = $ipn["payer_business_name"];
                }

                $IpnRcd[] = array(
                    'paymentId' => $ipn["unique_id_" . $i],
                    'paymentClaimed' => $paymentClaimed,
                    'paymentClaimedDate' => $paymentClaimedDate === NULL ? NULL : date("Y-m-d h:i:s", strtotime($paymentClaimedDate)),
                    'txn_type' => $ipn["txn_type"],
                    'masspay_txn_id' => $ipn["masspay_txn_id_" . $i],
                    'payer_id' => $ipn["payer_id"],
                    'unique_id' => $ipn["unique_id_" . $i],
                    'ipn_track_id' => $ipn["ipn_track_id"],
                    'notify_version' => $ipn["notify_version"],
                    'verify_sign' => $ipn["verify_sign"],
                    'payment_status' => $ipn["payment_status"],
                    'pending_reason' => $pendingReason,
                    'reason_code' => $reasonCode,
                    'reason_code_x' => $reasonCode_x,
                    'payment_date' => date("Y-m-d h:i:s", strtotime($ipn["payment_date"])),
                    'receiver_email' => $ipn["receiver_email_" . $i],
                    'status' => $ipn["status_" . $i],
                    'mc_fee' => $ipn["mc_fee_" . $i],
                    'mc_gross' => $ipn["mc_gross_" . $i],
                    'mc_currency' => $ipn["mc_currency_" . $i],
                    'first_name' => $ipn["first_name"],
                    'last_name' => $ipn["last_name"],
                    'payer_status' => $ipn["payer_status"],
                    'payer_email' => $ipn["payer_email"],
                    'payer_business_name' => $payerBusinessName,
                    'residence_country' => $ipn["residence_country"]
                );
                $this->paypalmodel->updateDistributedPayments($IpnRcd);
            }
        }
    }

    function processExpressCheckoutIpn($ipn) {
        
        $this->load->model('paypalmodel');

        log_message('error', 'tranny type =:::: ' . $ipn["txn_type"]);
        log_message('error', 'payer ID =:::: ' . $ipn["payer_id"]);
        log_message('error', 'ipn_track_id =:::: ' . $ipn["ipn_track_id"]);

        $i = 1;
        if (isset($ipn["item_number" . $i])) {
            log_message('error', 'unox' . $i . ':' . $ipn["item_number" . $i]);
            log_message('error', 'gross' . $i . ':' . $ipn["mc_gross_" . $i]);

            $pendingReason = NULL;
            if (isset($ipn["pending_reason"])) {
                $pendingReason = $ipn["pending_reason"];
            }

            $reasonCode = NULL;
            if (isset($ipn["reason_code"])) {
                $reasonCode = $ipn["reason_code"];
            }

            $reasonCode_x = NULL;
            if (isset($ipn["reason_code" . $i])) {
                $reasonCode_x = $ipn["reason_code" . $i];
            }

            $test_ipn = NULL;
            if (isset($ipn["test_ipn"])) {
                $test_ipn = $ipn["test_ipn"];
            }

            $payerBusinessName = NULL;
            if (isset($ipn["payer_business_name"])) {
                $payerBusinessName = $ipn["payer_business_name"];
            }

            $business = NULL;
            if (isset($ipn["business"])) {
                $business = $ipn["business"];
            }
            
            //Unique ID generated during guest checkout (payment by credit card without logging in).
            $receiptId = NULL;
            if (isset($ipn["receipt_id"])) {
                $receiptId = $ipn["receipt_id"];
            }

            $IpnRcd = array(
                'txn_type' => $ipn["txn_type"],
                'txn_id' => $ipn["txn_id"],
                'item_number' => $ipn["item_number" . $i],
                'item_name' => $ipn["item_name" . $i],
                'payer_id' => $ipn["payer_id"],
                'receiver_id' => $ipn["receiver_id"],
                'receipt_id' => $receiptId,
                'ipn_track_id' => $ipn["ipn_track_id"],
                'test_ipn' => $test_ipn,
                'notify_version' => $ipn["notify_version"],
                'verify_sign' => $ipn["verify_sign"],
                'payment_status' => $ipn["payment_status"],
                'payment_type' => $ipn["payment_type"],
                'pending_reason' => $pendingReason,
                'reason_code' => $reasonCode,
                'reason_code_x' => $reasonCode_x,
                'payment_date' => date("Y-m-d h:i:s", strtotime($ipn["payment_date"])),
                'receiver_email' => $ipn["receiver_email"],
                'business' => $business,
                'num_cart_items' => $ipn["num_cart_items"],
                'quantity' => $ipn["quantity" . $i],
                'mc_fee' => $ipn["mc_fee"],
                'mc_gross' => $ipn["mc_gross_" . $i],
                'tax' => $ipn["tax"],
                'mc_currency' => $ipn["mc_currency"],
                'first_name' => $ipn["first_name"],
                'last_name' => $ipn["last_name"],
                'payer_status' => $ipn["payer_status"],
                'payer_email' => $ipn["payer_email"],
                'payer_business_name' => $payerBusinessName,
                'residence_country' => $ipn["residence_country"]
            );
            $this->paypalmodel->insertPaypalExpressCheckoutIPN($IpnRcd);
        }
    }

    

}
