<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

/**
 * Model for all geographical operations
 */
class Geo extends CI_Model {

    function __construct() {
        // Call the Model constructor
        parent::__construct();
    }

    function getAllCountryData() {

        $sql = "SELECT * FROM Country ORDER BY name ASC";
        if ($result = $this->db->query($sql)) {

            $countries = $result->result_array();
            return $countries;
        }
        return false;
    }

    function getCountryByIso2Code($countryISO2) {
        $sql = "SELECT * FROM Country WHERE countryId = ? ORDER BY name ASC";
        if ($result = $this->db->query($sql, array($countryISO2))) {
            return $result->row();
        }
        return false;
    }

    function getStatesByCountryId($countryISO2) {

        $sql = "SELECT * FROM State WHERE countryId = ?";
        if ($result = $this->db->query($sql, array($countryId))) {
            $states = $result->result_array();
            return $states;
        }
        return false;
    }

    function getStateByCountryIdAndStateId($countryISO2, $stateId) {

        $sql = "SELECT * FROM State WHERE countryId = ? AND stateId = ?";
        if ($result = $this->db->query($sql, array($countryISO2, $stateId))) {
            return $result->row();
        }
        return false;
    }

    function getStateByCountryIdAndStateName($countryISO2, $stateName) {

        $sql = "SELECT * FROM State WHERE countryId = ? AND name = ?";
        if ($result = $this->db->query($sql, array($countryISO2, $stateName))) {
            return $result->row();
        }
        return false;
    }

    function getStateByCountryIdAndStateAbbr($countryISO2, $stateAbbr) {
        $sql = "SELECT * FROM State WHERE countryId = ? AND abbreviation = ?";
        if ($result = $this->db->query($sql, array($countryISO2, $stateAbbr))) {
            return $result->row();
        }
        return false;
    }

    /**
     * Attempts to figure out a state by either its state name or state abbreviation
     * @param type $countryISO2
     * @param type $state
     */
    function getBestStateByCountryIdAndStateName($countryISO2, $stateName) {
        $state = $this->getStateByCountryIdAndStateName($countryISO2, $stateName);
        if ($state) {
            return $state;
        }
        return $this->getStateByCountryIdAndStateAbbr($countryISO2, $stateName);
    }

    /**
     * Retrieves a randome suburb for a given country and postcode
     * @param type $countryISO2
     * @param type $postcode
     * @return boolean
     */
    function getRandomSuburbByPostcode($countryISO2, $postcode) {
        $suburbs = $this->getSuburbsByPostcode($countryISO2, $postcode);
        if (empty($suburbs)) {
            return false;
        }
        return $suburbs[array_rand($suburbs)];
    }

    // USES GEONAMES API
    function getSuburbsByPostcode($countryISO2, $postcode) {

        //print_r($data);
        $ukpc = array('EN', 'WA', 'AB', 'UL'); // EIRE?
        if (in_array($countryISO2, $ukpc)) {
            $countryISO2 = 'GB';
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://api.geonames.org/postalCodeLookupJSON?postalcode=' . urlencode($postcode) . '&country=' . $countryISO2 . '&username=' . $this->config->item("geonamesUsername"));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_PROXY, $this->config->item("ramblur_internal_proxy") . $this->config->item("ramblur_internal_proxy_port"));
        $response = json_decode(curl_exec($ch), true);
        curl_close($ch);
        if (!$response) {
            return false;
        }
        return $response['postalcodes'];
    }
	
	 function convertToUTC($date, $timezone) {
        
        date_default_timezone_set($timezone);
        $tz = new DateTimeZone('UTC');
        $convertedDate = new DateTime($date);
        
        return($convertedDate->setTimezone($tz));
    }

    function convertFromUtc($date, $timezone) {
        
        $tz = new DateTimeZone($timezone);
        $convertedDate = new DateTime($date);
        return($convertedDate->setTimezone($tz));
    }
	
}
