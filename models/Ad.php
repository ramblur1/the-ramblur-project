<?php

/***************************************
 * Ad Status Id's (Advert.statusId) 
 *************************************** 
 * 
 * 1 - Active
 * 2 - De-activated
 * 3 - Finished (not classified)
 * 4 - Finished (classified), (i.e. Item Sold)
 * 5 - Expired
 * 6 - Quote
 * 7 - Incomplete
 * 8 - Payment Pending
 * 9 - Deleted
 * 10 - modRemoved (priority 1)
 * 11 - modUnderReview (priority 2)
 * 12 - modPaused (priority 3) 
 * 13 - Waiting Artwork 
 * 14 - Image Disallowed
 * (possible virus) 
 * 16 - Image Type Disallowed
 * (not jpeg, png or gif) 
 * 17 - Free Bonus Ad on Joining Ramblur (used as placeholder until real ad is created)
 * 18 - Free Bonus Ad on Joining Ramblur where the real ad has been created so the placeholder is no longer needed
 * 
 *****************************************************************/
/* Ad Order adAdminFreeAd Id's
 * ****************************************************************
 * 
 * 1 = free ad admin ad - not yet bought
 * 2 = free ad admin ad purchased via bypassing payment gateway
 * 3 = free ad admin ad purchased vi sandbox
 * 4 = free ad admin ad purchased with payment gateway for normal funds or credit (if classified).
 * 
 * 
*******************************************************************/


if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class Ad extends CI_Model {

    function __construct() {
        // Call the Model constructor
        parent::__construct();
    }

    /*
     * Returns all active ad row titles depending on which view we are in
     *
     * @param string $adScreen
     * @return array|boolean
     *
     */

    function getAdRowTitles($adScreen) {
        $query = $this->db->get_where('adRowTitles', array(
            'adScreen' => $adScreen
        ));

        if ($query->num_rows() > 0) {
            $ad_titles = $query->result_array();
            return ($ad_titles);
        } else {
            log_message('error', 'ad->getAdRowTitles(): No ad titles found.');
            return (NULL);
        }
    }

    /*
     * Returns details for all active main ad category types
     *
     * @return array|boolean
     *
     */

    function getAdTypes() {
        $query = $this->db->get_where('ad_types', array(
            'ad_status' => 'active'
        ));

        if ($query->num_rows() > 0) {
            $ad_types = $query->result_array();
            return ($ad_types);
        } else {
            log_message('error', 'ad->getAdTypes(): No active ad types found.');
            return (NULL);
        }
    }

    /*
     * Returns details for all active ad styles per main ad category
     *
     * @return array|boolean
     *
     */

    function getAdStyles($mainAdCategory, $costModel='', $style='') {
        if ($costModel != '' && $style != '') {
        if ($style === 'banner_cpm' || $style === 'banner_fix') {
            $style = 'banner';
        }
            $query = $this->db->get_where('ad_styles', array(
                'mainAdCategory' => $mainAdCategory,
                'cost_model' => $costModel,
                'ad_type' => $style,
                'ad_status' => 'active'
            ));
        } else {
            $query = $this->db->get_where('ad_styles', array(
                'mainAdCategory' => $mainAdCategory,
                'ad_status' => 'active'
            ));
        }

        if ($query->num_rows() > 0) {
            $ad_styles = $query->result_array();
            return ($ad_styles);
        } else {
            log_message('error', 'ad->getAdStyles(): No active ad styles found.');
            return (NULL);
        }
    }

    /*
     * Checks if an ad should be expired and if so,
     * deactivates and expires.
     *
     * @param bigint $adId
     * @return true
     */

    function recordAdImpAndCheckExp($adId, $src, $recordImpression) {
       
        $impressionRecorded = FALSE;
        
        if($recordImpression) {
            $impressionsColumn = ($src === 'desktop') ? 'impressions' : 'impressionsDevice';

            $this->db->where('adId', $adId);
            $this->db->set($impressionsColumn, $impressionsColumn.' + 1', false);
            $this->db->update('Advert');
        
            if ($this->db->affected_rows() === 1) {
                $impressionRecorded = TRUE;
            }
        }
        
        
         $select_array = array(
            'status' => 'status',
            'style' => 'style',
            'costModel' => 'costModel',
            'impressions' => 'impressions',
            'impressionsPurchased' => 'impressionsPurchased',
            'numHits' => 'numHits',
            'clicks' => 'clicks',
            'endDate' => 'endDate'
        );
        
        
        // need to check ad cost type and then check for clicks / date / imps
        $query = $this->db->select($select_array);
        $this->db->where('adId', $adId);
        $query = $this->db->get('Advert');

        if ($query->num_rows() > 0) {
            $row = $query->row();
            
            if($row->status === 'Expired' || $row->status === 'De-activated' || $row->status === 'Finished') {
                return array('expired' => TRUE, 'adId' => $adId, 'impressionRecorded' => $impressionRecorded, 'costModel' => $row->costModel, 'style' => $row->style);
            }

            if ($row->costModel === 'cpm' && !empty($row->impressionsPurchased) && !empty($row->impressions)) {
                if ($row->impressions >= $row->impressionsPurchased) {
                    $this->deactivateAd($adId);
                    $this->expireAd($adId);
                    return array('expired' => TRUE, 'adId' => $adId, 'impressionRecorded' => $impressionRecorded, 'costModel' => $row->costModel, 'style' => $row->style);
                }
            } else if ($row->costModel === 'ppc' && !empty($row->clicks) && !empty($row->numHits)) {

                if ($row->numHits >= $row->clicks) {
                    $this->deactivateAd($adId);
                    $this->expireAd($adId);
                    return array('expired' => TRUE, 'adId' => $adId, 'impressionRecorded' => $impressionRecorded, 'costModel' => $row->costModel, 'style' => $row->style);
                }
            } else if ($row->costModel === 'fixed' && !empty($row->endDate)) {

                $curr_date = date_timestamp_get(date_create());
                $end_dt = strtotime($row->endDate);

                if ($curr_date >= $end_dt) {
                    $this->deactivateAd($adId);
                    $this->expireAd($adId);
                    return array('expired' => TRUE, 'adId' => $adId, 'impressionRecorded' => $impressionRecorded, 'costModel' => $row->costModel, 'style' => $row->style);
                }
            }
            return array('expired' => FALSE, 'adId' => $adId, 'impressionRecorded' => $impressionRecorded, 'costModel' => $row->costModel, 'style' => $row->style);
        }
    }

    /*
     * Expires status of an ad
     *
     *
     * @param bigint $adId
     *
     */

    function expireAd($adId) {
        $this->deactivateAd($adId);

        $this->db->set('status', 'Expired');
        $this->db->where('adId', $adId);
        $this->db->update('Advert');

        $this->updateAdArchiveStatus($adId, 'Expired');
    }

    function expirePostAd($parentAdId) {

        $this->db->set('status', 'Expired');
        $this->db->set('booked', '0');
        $this->db->where('parentAdId', $parentAdId);
        $this->db->where('style', 'post');
        $this->db->update('Advert');
    }

    /*
     * Sets the ad status to Deleted (does not actually delete the ad but 
     * leaves it on the system for a record).
     *
     *
     * @param bigint $adId
     *
     */

    function deleteAd($adId) {
        $this->deactivateAd($adId);
        $this->db->set('status', 'Deleted');
        $this->db->where('adId', $adId);
        $this->db->update('Advert');

        $this->adArchiveInsert($adId);
    }

    /*
     * Physically deletes an ad from the system.
     * @todo delete ad images if required
     * 
     * Note for live version will not permanently delete
     * the add but use the deleteAd() function above.
     * 
     */

    //not used
    function permanentlyDeleteAd($adId) {
        $this->db->delete('adLocation', array(
            'adId' => $adId
        ));


        // Don't delete from ad_orders as children need them
        $this->db->delete('ad_orders', array('adId' => $adId));

        $this->db->delete('Advert', array(
            'adId' => $adId
        ));


        //Do we want to delete all child ads as well?
        $this->db->delete('Advert', array(
            'parentAdId' => $adId
        ));

        $this->adArchiveInsert($adId);
    }

    /*
     * Returns count of a particular ad type
     *
     * @param int $user_id
     * @param string $mainAdCategory
     * @param string $cost_model
     * @param string $ad_type
     * @return int
     *
     */

    private function getNumberOfAds($user_id, $mainAdCategory, $cost_model, $ad_type) {
        $count = 0;

        $this->db->where('userId', $user_id);
        $this->db->where('mainAdCategory', $mainAdCategory); // counts ad types under certain category e.g. corporate
        $this->db->where('costModel', $cost_model);
        $this->db->where('type', $ad_type);
        $this->db->from('Advert');
        $count = $this->db->count_all_results();
        return $count;
    }

    /*
     * Returns bonus ads if any
     *
     * @param string $mainAdCategory
     * @param string $cost_model
     * @param string $ad_type
     * @return single row object|NULL
     *
     */

    function getBonusAds($mainAdCategory, $cost_model, $ad_type) {

        $this->db->select('bonus_ad1');
        $this->db->select('bonus_ad2');
        $this->db->select('bonus_ad3');
        $this->db->select('bonus_cost_model1');
        $this->db->select('bonus_cost_model2');
        $this->db->where('mainAdCategory', $mainAdCategory);
        $this->db->where('cost_model', $cost_model);
        $this->db->where('ad_type', $ad_type);
        $this->db->where('bonus_ad1 is NOT NULL');
        $this->db->where('bonus_ad1 !=', '');
        $query = $this->db->get('ad_styles');

        if ($query->num_rows() > 0) {
            $bonus_ads = $query->row();
            return ($bonus_ads);
        } else {
            return (NULL);
        }
    }

    /*
     * Returns child bonus ads for an adId if any
     *
     * @param string $adId
     * @return array | NULL
     *
     */

    function getChildBonusAds($adId) {

        $this->db->select('mainAdCategory');
        $this->db->select('style');
        $this->db->select('costModel');
        $this->db->select('startDate');
        $this->db->select('endDate');
        $this->db->select('clicks');
        $this->db->select('impressionsPurchased');
        $this->db->where('parentAdId', $adId);
        $this->db->where('freeAd', 1);
        $query = $this->db->get('Advert');

        if ($query->num_rows() > 0) {
            $bonusAds = $query->result_array();
            return ($bonusAds);
        } else {
            return (NULL);
        }
    }

    /*
     * Returns potential child bonus ads for an adId if any
     *
     * @param string $adId
     * @return array | NULL
     *
     */

    function getPotentialBonusAds($adId) {

        $this->db->select('mainAdCategory');
        $this->db->select('style');
        $this->db->select('cost_model AS costModel');
        $this->db->select('start_date AS endDate');
        $this->db->select('end_date AS startDate');
        $this->db->select('clicks');
        $this->db->select('impressions AS impressionsPurchased');
        $this->db->where('parentAdId', $adId);
        $query = $this->db->get('bonus_ads');

        if ($query->num_rows() > 0) {
            $bonusAds = $query->result_array();
            return ($bonusAds);
        } else {
            return (NULL);
        }
    }

    function adInsertBonusAds($user_id, $adId, $mainAdCategory, $cost_model, $ad_type, $impressions, $clicks) {

	  $this->load->helper(array('htmlpurifier', 'censor'));

	  $mainAdCategory = censor_text(html_purify($mainAdCategory, 'post'));
	  $cost_model = censor_text(html_purify($cost_model, 'post'));
	  $ad_type = censor_text(html_purify($ad_type, 'post'));

        $start_date = null;
        $end_date1 = null;
        $end_date2 = null;
        $bonus_clicks = 0;
        $bonus_imps = 0;

        $ad = $this->getAdDetails($adId, $user_id); // check for errors calling getAdDetails here!!!!!!
        $ad = $ad [0];
        $start_date = date("Y-m-d h:i:s", strtotime($ad ['startDate']));

        $bonus_ads = $this->getBonusAds($mainAdCategory, $cost_model, $ad_type);

        if ($bonus_ads != NULL) {

            if ($cost_model === 'cpm') {
                if (!empty($impressions)) {
                    $bonus_clicks = $this->getBonus($mainAdCategory, $cost_model, $ad_type, 'bonus_clicks', 'num_impressions', $impressions);
                    $bonus_clicks = $bonus_clicks->bonus_clicks;
                }
            } else if ($cost_model === 'ppc') {

                if (!empty($clicks)) {
                    $bonus_imps = $this->getBonus($mainAdCategory, $cost_model, $ad_type, 'bonus_impressions', 'num_clicks', $clicks);
                    $bonus_imps = $bonus_imps->bonus_impressions;
                }
            } else if ($cost_model === 'fixed') {

                $no_of_fixed_banners = $this->getNumberOfAds($user_id, $mainAdCategory, $cost_model, $ad_type);

                if ($no_of_fixed_banners > 10) {
                    $no_of_fixed_banners = 10;
                }

                $bonus_imps = $this->getBonus($mainAdCategory, 'fixedBonus', $ad_type, 'bonus_impressions', 'number_of_ads', $no_of_fixed_banners);
                $bonus_imps = $bonus_imps->bonus_impressions;
                $bonus_clicks = $this->getBonus($mainAdCategory, 'fixedBonus', $ad_type, 'bonus_clicks', 'number_of_ads', $no_of_fixed_banners);
                $bonus_clicks = $bonus_clicks->bonus_clicks;
            }

            if ($bonus_ads->bonus_cost_model1 === 'fixed') {
                $end_date1 = date("Y-m-d h:i:s", strtotime($ad ['endDate']));
            }
            if ($bonus_ads->bonus_cost_model2 === 'fixed') {
                $end_date2 = date("Y-m-d h:i:s", strtotime($ad ['endDate']));
            }

            if ($bonus_ads->bonus_ad1 === 'Skyscraper' || $bonus_ads->bonus_ad1 === 'Thumb') {
                $type = 'ppc';
            } else {
                $type = 'banner';
            }

            $bonus_ad_ar = array(
                "user_id" => $user_id,
                "bonusAdId" => 0,
                "parentAdId" => $adId,
                "mainAdCategory" => $mainAdCategory,
                "cost_model" => $bonus_ads->bonus_cost_model1,
                "type" => $type, // may want to just write LV to bonus ad table in first place!!!!!
                "style" => lcfirst($bonus_ads->bonus_ad1),
                "impressions" => $bonus_imps,
                "clicks" => $bonus_clicks,
                "start_date" => $start_date,
                "end_date" => $end_date1,
                "status" => 'pending'
            );

			$this->db->delete('bonus_ads', array("parentAdId" => $adId, "style" => lcfirst($bonus_ads->bonus_ad1)));
            $this->db->insert('bonus_ads', $bonus_ad_ar);

            if (!empty($bonus_ads->bonus_ad2)) {

                if ($bonus_ads->bonus_ad2 === 'Skyscraper' || $bonus_ads->bonus_ad2 === 'Thumb') {
                    $type = 'ppc';
                } else {
                    $type = 'banner';
                }

                $bonus_ad_ar = array(
                    "user_id" => $user_id,
                    "parentAdId" => $adId,
                    "mainAdCategory" => $mainAdCategory,
                    "cost_model" => $bonus_ads->bonus_cost_model2,
                    "type" => $type,
                    "style" => lcfirst($bonus_ads->bonus_ad2),
                    "impressions" => $bonus_imps,
                    "clicks" => $bonus_clicks,
                    "start_date" => $start_date,
                    "end_date" => $end_date2,
                    "status" => 'pending'
                );

				$this->db->delete('bonus_ads', array("parentAdId" => $adId, "style" => lcfirst($bonus_ads->bonus_ad2)));
                $this->db->insert('bonus_ads', $bonus_ad_ar);
            }
            
            if (!empty($bonus_ads->bonus_ad3)) {

                $bonus_ad_ar = array(
                    "user_id" => $user_id,
                    "parentAdId" => $adId,
                    "mainAdCategory" => $mainAdCategory,
                    "cost_model" => 'fixed',
                    "type" => 'Post Ad',
                    "style" => 'Post Ad',
                    "impressions" => 0,
                    "clicks" => 0,
                    "start_date" => $start_date,
                    "end_date" => $end_date2,
                    "status" => 'pending'
                );

				$this->db->delete('bonus_ads', array("parentAdId" => $adId, "style" => 'Post Ad'));
                $this->db->insert('bonus_ads', $bonus_ad_ar);
            }
        } // end of if NULL
    }

    function getBonus($mainAdCategory, $cost_model, $ad_type, $column, $compare, $value) {
        $this->db->select($column);
        $this->db->where($compare, $value);
        $this->db->where('mainAdCategory', $mainAdCategory);
        $this->db->where('ad_type', $ad_type);
        $query = $this->db->get($cost_model . 'Pricing');

        if ($query->num_rows() > 0) {
            $bonus = $query->row();

            return ($bonus);
        } else {
            return (NULL);
        }
    }

    /*
     * Returns a quote for a CPM cost model ad via the API
     *
     * @param object $adQuote
     * @return array $quoteArr
     *
     */

    function getCpmAdQuote($adQuote) {
        //   $this->load->library('session');

        $quote = 0.0;
        $mainAdCategory = $adQuote ['mainAdCategory'];
        $ad_type = $adQuote ['adType'];
        $cost_model = $adQuote ['costModel'];
        $ad_costmodel = $ad_type . '_' . $adQuote ['costModel'];
        $num_impressions = $ad_costmodel . '_' . 'store_impressions';
        $international = $ad_costmodel . '_' . 'store_international';
        $num_countries = $ad_costmodel . '_' . 'store_numnations';
        $num_states = $ad_costmodel . '_' . 'store_numbstates';
        $num_postcodes = $ad_costmodel . '_' . 'store_numbpostcodes';
        $num_forums = $ad_costmodel . '_' . 'store_numbforums';
        $num_ages = $ad_costmodel . '_' . 'store_numberages';
        $gender = $ad_costmodel . '_' . 'store_gender';
        $forums_quote = 0;
        $agegroups_quote = 0;
        $gender_quote = 0;
        $ret_bonus1 = '';
        $ret_bonus2 = '';

        if (!empty($adQuote ['bonus1']) && !empty($adQuote [$num_impressions])) {
            $bonus1 = $this->getBonus($mainAdCategory, 'cpm', $ad_type, 'bonus_' . lcfirst($adQuote ['bonus1']), 'num_impressions', $adQuote [$num_impressions]);
            $ret_bonus1 = $bonus1->{"bonus_" . lcfirst($adQuote ['bonus1'])} . ' ' . $adQuote ['bonus1'];
        }

        if (!empty($adQuote ['bonus2']) && !empty($adQuote [$num_impressions])) {
            $bonus2 = $this->getBonus($mainAdCategory, 'cpm', $ad_type, 'bonus_' . lcfirst($adQuote ['bonus2']), 'num_impressions', $adQuote [$num_impressions]);
            $ret_bonus2 = $bonus2->{"bonus_" . lcfirst($adQuote ['bonus2'])} . ' ' . $adQuote ['bonus2'];
        }

        $quoteArr = array(
            "preAdId" => $adQuote['preAdId'],
            "quoteElementId" => $adQuote ['quoteElementId'],
            "quoteResult" => 0.0,
            "bonusElementId1" => $adQuote ['bonusElementId1'],
            "bonus1" => $ret_bonus1,
            "bonusElementId2" => $adQuote ['bonusElementId2'],
            "bonus2" => $ret_bonus2
        );

        $price_field = '';
        if (isset($adQuote [$international])) {
            if ($adQuote [$international] == 'Yes') {
                $price_field = 'international';
            }
        }

        if (isset($adQuote [$num_countries])) {
            if (!empty($adQuote [$num_countries])) {
                $price_field = 'countries';
            }
        }

        if (isset($adQuote [$num_states])) {
            if (!empty($adQuote [$num_states])) {
                $price_field = 'states';
            }
        }

        if (isset($adQuote [$num_postcodes])) {
            if (!empty($adQuote [$num_postcodes])) {
                $price_field = 'postcodes';
            }
        }

        if (isset($adQuote [$num_impressions])) {

            if ($adQuote [$num_impressions] !== 0) {
                if ($price_field != '') {

                    $quote = $this->getPrices("cpmPricing", $price_field, "num_impressions", $adQuote [$num_impressions], $mainAdCategory, $ad_type);

                    if (isset($adQuote [$num_forums])) {

                        $forums_price = $this->getPrices("cpm_forums_pricing", "price", "numb_forums", $adQuote [$num_forums], $mainAdCategory, $ad_type);
                        $forums_quote = ($quote * $forums_price / 100);
                    }

                    if (isset($adQuote [$num_ages])) {

                        $agegroups_price = $this->getPrices("cpm_agegroups_pricing", "price", "numb_agegroups", $adQuote [$num_ages], $mainAdCategory, $ad_type);
                        $agegroups_quote = ($quote * $agegroups_price / 100);
                    }

                    if (isset($adQuote [$gender])) {

                        $gender_price = $this->getPrices("cpm_gender_pricing", "price", "gender", $adQuote [$gender], $mainAdCategory, $ad_type);
                        $gender_quote = ($quote * $gender_price / 100);
                    }

                    $quote = $quote + $forums_quote + $agegroups_quote + $gender_quote;

                    $quoteArr = array(
                        "preAdId" => $adQuote['preAdId'],
                        "quoteElementId" => $adQuote['quoteElementId'],
                        "quoteResult" => $quote,
                        "bonusElementId1" => $adQuote['bonusElementId1'],
                        "bonus1" => $ret_bonus1,
                        "bonusElementId2" => $adQuote['bonusElementId2'],
                        "bonus2" => $ret_bonus2
                    );
                }
            }
        }


        if (!empty($adQuote['preAdId'])) {
            $this->updateAdPrice($adQuote['preAdId'], $quote);
        }

        return array(
            "quoteArr" => $quoteArr
        );
    }

    /*
     * Returns a quote for a FIXED cost model ad via the API
     *
     * @param object $adQuote
     * @return array $quoteArr
     *
     */

    function getFixedAdQuote($adQuote) {

        $quote = 0.0;
        $mainAdCategory = $adQuote ['mainAdCategory'];
        $ad_type = $adQuote ['adType'];
        $cost_model = $adQuote ['costModel'];
        $ad_costmodel = $ad_type . '_' . $adQuote ['costModel'];
        $num_impressions = $ad_costmodel . '_' . 'store_impressions';
        $international = $ad_costmodel . '_' . 'store_international';
        $num_countries = $ad_costmodel . '_' . 'store_numnations';
        $num_states = $ad_costmodel . '_' . 'store_numbstates';
        $num_postcodes = $ad_costmodel . '_' . 'store_numbpostcodes';
        $num_forums = $ad_costmodel . '_' . 'store_numbforums';
        $num_ages = $ad_costmodel . '_' . 'store_numberages';
        $gender = $ad_costmodel . '_' . 'store_gender';
        $quote = 0;
        $forums_quote = 0;
        $agegroups_quote = 0;
        $gender_quote = 0;
        $forums_price = 0;
        $agegroups_price = 0;
        $gender_price = 0;
        $geo_field = '';
        $ret_bonus1 = '';
        $ret_bonus2 = '';

        $userId = $this->user->authCheck(); // /do other stuff here!

        $no_of_fixed_banners = $this->getNumberOfAds($userId, $mainAdCategory, $cost_model, $ad_type);

        if ($no_of_fixed_banners > 10) {
            $no_of_fixed_banners = 10;
        }

        if (!empty($adQuote ['bonus1'])) {
            $bonus1 = $this->getBonus($mainAdCategory, 'fixedBonus', $ad_type, 'bonus_' . lcfirst($adQuote ['bonus1']), 'number_of_ads', $no_of_fixed_banners);
            $ret_bonus1 = $bonus1->{"bonus_" . lcfirst($adQuote ['bonus1'])} . ' ' . $adQuote ['bonus1'];
        }

        if (!empty($adQuote ['bonus2'])) {
            $bonus2 = $this->getBonus($mainAdCategory, 'fixedBonus', $ad_type, 'bonus_' . lcfirst($adQuote ['bonus2']), 'number_of_ads', $no_of_fixed_banners);
            $ret_bonus2 = $bonus2->{"bonus_" . lcfirst($adQuote ['bonus2'])} . ' ' . $adQuote ['bonus2'];
        }

        $quoteArr = array(
            "preAdId" => $adQuote['preAdId'],
            "quoteElementId" => $adQuote ['quoteElementId'],
            "quoteResult" => 0.0,
            "bonusElementId1" => $adQuote ['bonusElementId1'],
            "bonus1" => $ret_bonus1,
            "bonusElementId2" => $adQuote ['bonusElementId2'],
            "bonus2" => $ret_bonus2
        );

        if (isset($adQuote [$international])) {
            if ($adQuote [$international] == 'Yes') {
                $geo_field = 'international';
                $quote_field = $international;
            }
        }

        if (isset($adQuote [$num_countries])) {
            if ($adQuote [$num_countries] != '0' && $adQuote [$num_countries] != '') {
                $geo_field = 'countries';
                $quote_field = $num_countries;
            }
        }

        if (isset($adQuote [$num_states])) {
            if ($adQuote [$num_states] != '0' && $adQuote [$num_states] != '') {
                $geo_field = 'states';
                $quote_field = $num_states;
            }
        }

        if (isset($adQuote [$num_postcodes])) {
            if ($adQuote [$num_postcodes] != '0' && $adQuote [$num_postcodes] != '') { // this should be set r not set for postcodes as 0 is valid!!!!!!!
                $geo_field = 'postcodes';
                $quote_field = $num_postcodes;
            }
        }

        if ($geo_field != '') {

            $quote = $this->getPrices("fixedPricing", "price", "number", $adQuote [$quote_field], $mainAdCategory, $ad_type, $geo_field);

            if (isset($adQuote [$num_forums])) {

                $forums_price = $this->getPrices("fixed_forums_pricing", "price", "numb_forums", $adQuote [$num_forums], $mainAdCategory, $ad_type);
            }

            if (isset($adQuote [$num_ages])) {

                $agegroups_price = $this->getPrices("fixed_agegroups_pricing", "price", "numb_agegroups", $adQuote [$num_ages], $mainAdCategory, $ad_type);
                $agegroups_quote = ($quote * $agegroups_price / 100);
            }

            if (isset($adQuote [$gender])) {

                $gender_price = $this->getPrices("fixed_gender_pricing", "price", "gender", $adQuote [$gender], $mainAdCategory, $ad_type);
                $gender_quote = ($quote * $gender_price / 100);
            }

            $quoteArr = array(
                "preAdId" => $adQuote['preAdId'],
                "quoteElementId" => $adQuote ['quoteElementId'],
                "quoteResult" => $quote + $forums_price + $agegroups_quote + $gender_quote,
                "bonusElementId1" => $adQuote ['bonusElementId1'],
                "bonus1" => $ret_bonus1,
                "bonusElementId2" => $adQuote ['bonusElementId2'],
                "bonus2" => $ret_bonus2
            );
        }

        if (!empty($adQuote['preAdId'])) {
            $this->updateAdPrice($adQuote['preAdId'], $quote);
        }


        return array(
            "quoteArr" => $quoteArr
        );
    }

    /*
     * Returns a quote for a PPC cost model ad via the API
     *
     * @param object $adQuote
     * @return array $quoteArr
     *
     */

    function getPpcAdQuote($adQuote) {

        $userId = $this->user->authCheck();

        $credit = $this->getAdCredit($userId);
        $initial_credit = $credit;
        $price_to_pay = 0.0;
        $credit_price = 0.0;

        $quote = 0.0;
        $final_quote = 0.0;
        $mainAdCategory = $adQuote ['mainAdCategory'];
        $ad_type = $adQuote ['adType'];
        $cost_model = $adQuote ['costModel'];
        $ad_costmodel = $ad_type . '_' . $adQuote ['costModel'];
        $num_impressions = $ad_costmodel . '_' . 'store_impressions';
        $num_clicks = $ad_costmodel . '_' . 'store_clicks';
        $international = $ad_costmodel . '_' . 'store_international';
        $num_countries = $ad_costmodel . '_' . 'store_numnations';
        $num_states = $ad_costmodel . '_' . 'store_numbstates';
        $num_postcodes = $ad_costmodel . '_' . 'store_numbpostcodes';
        $num_forums = $ad_costmodel . '_' . 'store_numbforums';
        $num_ages = $ad_costmodel . '_' . 'store_numberages';
        $gender = $ad_costmodel . '_' . 'store_gender';
        $forums_quote = 0;
        $agegroups_quote = 0;
        $gender_quote = 0;
        $location_price = 0;
        $location_quote = 0;
        $ret_bonus1 = '';
        $ret_bonus2 = '';


        if (!empty($adQuote ['bonus1']) && !empty($adQuote [$num_clicks])) {
            $bonus1 = $this->getBonus($mainAdCategory, 'ppc', $ad_type, 'bonus_' . lcfirst($adQuote ['bonus1']), 'num_clicks', $adQuote [$num_clicks]);
            $ret_bonus1 = $bonus1->{"bonus_" . lcfirst($adQuote ['bonus1'])} . ' ' . $adQuote ['bonus1'];
        }

        if (!empty($adQuote ['bonus2']) && !empty($adQuote [$num_clicks])) {
            $bonus2 = $this->getBonus($mainAdCategory, 'ppc', $ad_type, 'bonus_' . lcfirst($adQuote ['bonus2']), 'num_clicks', $adQuote [$num_clicks]);
            $ret_bonus2 = $bonus2->{"bonus_" . lcfirst($adQuote ['bonus2'])} . ' ' . $adQuote ['bonus2'];
        }

        $quoteArr = array(
            "preAdId" => $adQuote['preAdId'],
            "mainAdCategory" => $adQuote ['mainAdCategory'],
            "quoteElementId" => $adQuote ['quoteElementId'],
            "creditElementId" => $adQuote ['quoteElementId'] . '_credit',
            "totalPriceElementId" => $adQuote ['quoteElementId'] . '_totalPrice',
            "quoteResult" => 0,
            "credit" => $initial_credit,
            "credit_price" => 0,
            "price" => $price_to_pay,
            "bonusElementId1" => $adQuote ['bonusElementId1'],
            "bonus1" => $ret_bonus1,
            "bonusElementId2" => $adQuote ['bonusElementId2'],
            "bonus2" => $ret_bonus2
        );

        $location_field = '';
        $price_field = '';

        if (isset($adQuote [$international])) {
            if ($adQuote [$international] == 'Yes') {
                $location_field = 'international';
                $price_field = $international;
            }
        }

        if (isset($adQuote [$num_countries])) {
            if (!empty($adQuote [$num_countries])) {
                $location_field = 'countries';
                $price_field = $num_countries;
            }
        }

        if (isset($adQuote [$num_states])) {
            if (!empty($adQuote [$num_states])) {
                $location_field = 'states';
                $price_field = $num_states;
            }
        }

        if (isset($adQuote [$num_postcodes])) {
            if (!empty($adQuote [$num_postcodes])) {
                $location_field = 'postcodes';
                $price_field = $num_postcodes;
            }
        }

        if (isset($adQuote [$num_clicks])) {

            if ($adQuote [$num_clicks] !== 0) {
                if ($location_field != '') {

                    $location_price = $this->getPrices("ppc_location_pricing", $location_field, 'num_' . $location_field, $adQuote [$price_field], $mainAdCategory, $ad_type);
                    $quote = $this->getPrices("ppcPricing", "price", "num_clicks", $adQuote [$num_clicks], $mainAdCategory, $ad_type);
                    $quote *= $adQuote [$num_clicks];

                    if (isset($adQuote [$num_forums])) {

                        $forums_price = $this->getPrices("ppc_forums_pricing", "price", "numb_forums", $adQuote [$num_forums], $mainAdCategory, $ad_type);
                        $forums_quote = ($quote * $forums_price / 100);
                    }

                    if (isset($adQuote [$num_ages])) {

                        $agegroups_price = $this->getPrices("ppc_agegroups_pricing", "price", "numb_agegroups", $adQuote [$num_ages], $mainAdCategory, $ad_type);
                        $agegroups_quote = ($quote * $agegroups_price / 100);
                    }

                    if (isset($adQuote [$gender])) {

                        $gender_price = $this->getPrices("ppc_gender_pricing", "price", "gender", $adQuote [$gender], $mainAdCategory, $ad_type);
                        $gender_quote = ($quote * $gender_price / 100);
                    }

                    $location_quote = ($quote * $location_price / 100);
                    $final_quote = $quote + $forums_quote + $agegroups_quote + $gender_quote + $location_quote;
                    $price_to_pay = $final_quote - $credit;
                    $credit = $credit - $final_quote;
                    $credit_price = $final_quote;

                    if ($final_quote >= $initial_credit) {
                        $credit_price = $initial_credit;
                    }

                    if ($credit < 0) {
                        $credit = 0.00;
                    }

                    if ($price_to_pay < 0) {
                        $price_to_pay = 0.00;
                    }

                    $quoteArr = array(
                        "preAdId" => $adQuote['preAdId'],
                        "mainAdCategory" => $mainAdCategory,
                        "quoteElementId" => $adQuote ['quoteElementId'],
                        "creditElementId" => $adQuote ['quoteElementId'] . '_credit',
                        "totalPriceElementId" => $adQuote ['quoteElementId'] . '_totalPrice',
                        "quoteResult" => $final_quote,
                        "credit" => $initial_credit,
                        "credit_price" => $credit_price,
                        "price" => $price_to_pay,
                        "bonusElementId1" => $adQuote ['bonusElementId1'],
                        "bonus1" => $ret_bonus1,
                        "bonusElementId2" => $adQuote ['bonusElementId2'],
                        "bonus2" => $ret_bonus2
                    );
                }
            }
        }


        if ($mainAdCategory == 'classified') {
            // jb - bug needs to be fixed in classified where credit can get mixed up when more then one purchase!!!!
            //    if (!empty($adQuote['preAdId'])) {
            //  $this->updateAdPrice($adQuote['preAdId'], $price_to_pay, $credit_price);
            //  
            //}

            if (!empty($adQuote['preAdId'])) {
                $this->updateAdPrice($adQuote['preAdId'], $price_to_pay, $credit_price);

                //need to reduce the credit here and write it to the user table as working_credit!!!!!!!!!!!!!!
                //so if 2 classified adds are being purchased at same time the remainig credit will be correct.
            }


            //  $this->session->set_userdata($quote_session . $adQuote['preAdId'], $price_to_pay);
            // $this->session->set_userdata('credit_to_use' . $adQuote['preAdId'], $credit_price);
        } else {
            if (!empty($adQuote['preAdId'])) {
                $this->updateAdPrice($adQuote['preAdId'], $quote);
            }
            // $this->session->set_userdata($quote_session, $final_quote);
        }

        // $this->session->unset_userdata('credit');
        //   $this->session->set_userdata('credit' . $adQuote['preAdId'], $credit);
        //dont want to set users credit here dfo you?
        // $this->storeAdCredit($userId, $credit);

        return array(
            "quoteArr" => $quoteArr
        );
    }

    /*
     * helper function to return pricing table prices based on cols/tabls/params passed
     */

    function getPrices($table, $select_name, $column_name, $column_value, $mainAdCategory, $ad_type, $geo_field = '') {
        $query = $this->db->select($select_name);
        $this->db->where('mainAdCategory', $mainAdCategory);
        $this->db->where('ad_type', $ad_type);
        $this->db->where($column_name, $column_value);
        if ($geo_field != '') {
            $this->db->where('geo_field', $geo_field);
        }
        $this->db->where('status', 'Active');
        $query = $this->db->get($table);

        if ($query->num_rows() > 0) {
            $row = $query->row();

            return $row->$select_name;
        }

        return 0;
    }

    /*
     * Returns a users current credit
     *
     * @param int $user_id
     * @return double credit
     *
     */

    function getAdCredit($user_id) {
        $query = $this->db->select('credit');
        $this->db->where('userId', $user_id);
        $query = $this->db->get('User');

        if ($query->num_rows() > 0) {
            $row = $query->row();

            return (double) $row->credit;
        }

        return 0.00;
    }

    function getAdCreditPrice($adId) {
        $query = $this->db->select('creditUsed');
        $this->db->where('adId', $adId);
        $query = $this->db->get('Advert');

        if ($query->num_rows() > 0) {
            $row = $query->row();

            return (double) $row->creditUsed;
        }

        return 0.00;
    }

    function storeAdCredit($user_id, $credit = 0) {
        $this->db->set('credit', $credit);
        $this->db->where('userId', $user_id);
        $this->db->update('User');
    }

    function setAdCredit($user_id, $credit_price, $unit_price = '', $adId = '') {
        $remaining_credit = $this->getAdCredit($user_id) - $credit_price;
        if ($remaining_credit < 0) {
            $remaining_credit = 0.00;
        }

        $this->db->set('credit', $remaining_credit);
        $this->db->where('userId', $user_id);
        $this->db->update('User');

        if ($unit_price != '' && $adId != '') {
            $this->db->set('unitPrice', $unit_price);
            $this->db->set('creditUsed', $credit_price);
            $this->db->where('userId', $user_id);
            $this->db->where('adId', $adId);
            $this->db->update('Advert');
        }
    }

    function refundCredit($user_id, $credit) {
        $this->db->set('credit', $credit);
        $this->db->where('userId', $user_id);
        $this->db->update('User');
    }

    function setAdPrice($user_id, $adId, $unit_price, $credit_price) {
        $this->db->set('unitPrice', $unit_price);
        $this->db->set('creditUsed', $credit_price);
        $this->db->where('userId', $user_id);
        $this->db->where('adId', $adId);
        $this->db->update('Advert');
    }

    function updateAdPrice($adId, $unit_price, $credit_price = 0) {
        $this->db->set('unitPrice', $unit_price);
        $this->db->set('creditUsed', $credit_price);
        // $this->db->where('userid', $user_id);
        $this->db->where('adId', $adId);
        $this->db->update('Advert');
    }

    
    //split paid and activate out !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!1111
    function activateAd($adId, $cost_model = '', $paid = '0') {
        $this->db->set('booked', '1');
        $this->db->set('status', 'Active');
        if (!empty($paid)) {
            $this->db->set('paid', $paid);
        }
        $this->db->where('adId', $adId);
        $this->db->update('Advert');

        //if (!empty($paid)) {
        //update ad archive as it is a new ad (can't do this above)
        $this->updateAdArchiveActive($adId, $paid);
        //}
    }
    
    function setAdStatus($adId, $bookedStatusId, $statusId, $statusDesc, $paid = '0') {
        $this->db->set('booked', $bookedStatusId);
        $this->db->set('statusId', $statusId);
        $this->db->set('status', $statusDesc);
        if (!empty($paid)) {
            $this->db->set('paid', $paid);
        }
        $this->db->where('adId', $adId);
        $this->db->update('Advert');

        $this->setAdArchiveStatus($adId, $bookedStatusId, $statusId, $statusDesc, $paid);
    }
    
     function setAdStatusId($adId, $statusId) {
        $this->db->set('statusId', $statusId);
        $this->db->where('adId', $adId);
        $this->db->update('Advert');

        $this->setAdArchiveStatusId($adId, $statusId);
    }
    
    function setAdArchiveStatusId($adId, $statusId) {
        $this->db->set('statusId', $statusId);
        $this->db->where('adId', $adId);
        $this->db->update('adArchive');

    }
    
    function setAdArchiveStatus($adId, $bookedStatusId, $statusId, $statusDesc, $paid = '0') {
        
        $query = $this->db->select('adArchiveId');
        $this->db->where('adId', $adId);
        $this->db->order_by('modifiedDate', 'DESC');
        $this->db->limit(1);
        $query = $this->db->get('adArchive');
        if ($query->num_rows() > 0) {
            $row = $query->row();
            $this->db->set('booked', $bookedStatusId);
            $this->db->set('status', $statusDesc);
            if (!empty($paid)) {
                $this->db->set('paid', $paid);
            }
            $this->db->where('adArchiveId', $row->adArchiveId);
            $this->db->update('adArchive');
        }
    }
    
    function setAdAdminFreeAdStatus($adId, $statusId) {
        $this->db->set('adAdminFreeAd', $statusId);
        $this->db->where('adId', $adId);
        $this->db->update('ad_orders');
    }

    function updateAdArchiveActive($adId, $paid = '0') {

        $query = $this->db->select('adArchiveId');
        $this->db->where('adId', $adId);
        $this->db->order_by('modifiedDate', 'DESC');
        $this->db->limit(1);
        $query = $this->db->get('adArchive');
        if ($query->num_rows() > 0) {
            $row = $query->row();
            $this->db->set('booked', '1');
            $this->db->set('status', 'Active');
            $this->db->set('paid', $paid);
            $this->db->where('adArchiveId', $row->adArchiveId);
            $this->db->update('adArchive');
        }
    }

    function updateAdArchiveStatus($adId, $status = '') {

        $query = $this->db->select('adArchiveId');
        $this->db->where('adId', $adId);
        $this->db->order_by('modifiedDate', 'DESC');
        $this->db->limit(1);
        $query = $this->db->get('adArchive');
        if ($query->num_rows() > 0) {
            $row = $query->row();
            $this->db->set('booked', '0');
            $this->db->set('status', $status);
            $this->db->where('adArchiveId', $row->adArchiveId);
            $this->db->update('adArchive');
        }
    }

    function activatePostAd($parentAdId) {
        $this->db->set('booked', '1');
        $this->db->where('parentAdId', $parentAdId);
        $this->db->update('post_ads');
        
        $this->db->set('visible', '1');
        $this->db->where('postId', $this->getPostId($parentAdId));
        $this->db->update('Post');
        
    }

    function deactivateAd($adId) {
        $this->db->set('booked', '0');
        $this->db->set('status', 'De-activated');
        $this->db->where('adId', $adId);
        $this->db->update('Advert');

        $this->updateAdArchiveStatus($adId, 'De-activated');
    }
    
    function updateCurrentAdState($adId, $statusId, $bookedId, $status) {
        $this->db->set('statusId', $statusId)
                 ->set('booked', $bookedId)
                 ->set('status', $status);
        $this->db->where('adId', $adId);
        $this->db->update('Advert');
        
//sore this out!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!1
        //$this->updateAdArchiveStatus($adId, 'De-activated');
    }
    
    function deactivatePostAd($parentAdId) {
        $this->db->set('booked', '0');
        $this->db->where('parentAdId', $parentAdId);
        $this->db->update('post_ads');
        
        $this->db->set('visible', '0');
        $this->db->where('postId', $this->getPostId($parentAdId));
        $this->db->update('Post');
    }

    function finishAd($adId) {
        $this->db->set('booked', '0');
        $this->db->set('status', 'Finished');
        $this->db->where('adId', $adId);
        $this->db->update('Advert');

        $this->updateAdArchiveStatus($adId, 'Finished');
    }

    function calcCreditRebate($adId) {
        $current_credit = $this->getAdCredit('7'); // user will come from controller
        $ad = $this->getAdDetails($adId);
        $ad = $ad [0];
        $price = $this->getPpcCreditPrice($ad ["num_clicks"]);
        $clicks_remaining = $ad ["num_clicks"] - $ad ["clicks"];

        return $price->price * $clicks_remaining;
    }

    /*
     * find the nearest number of clicks in the pricing table to what was originally ordered, and return price/*
     *
     *
     * @param int $clicks
     * @return double $price
     *
     */

    function getPpcCreditPrice($clicks) {
        $this->db->select('price', 'price');
        $this->db->where('mainAdCategory', 'classified');
        $this->db->where('num_clicks !=', '0');
        $this->db->order_by("ABS(num_clicks-$clicks)");
        $query = $this->db->get('ppcPricing', 1);

        $price = $query->row();

        if ($query->num_rows() > 0) {
            return ($price);
        } else {
            return (NULL);
        }
    }

    function getDropdownData($column, $mainAdCategory, $ad_type, $table, $geo_field = '', $alias = '', $status = '') {
        $this->db->order_by($column, 'ASC');
        $this->db->where('mainAdCategory', $mainAdCategory);
        $this->db->where('ad_type', $ad_type);
        if ($geo_field != '') {
            $this->db->select($column . ' AS ' . $alias);
            $this->db->where('geo_field', $geo_field);
        } else {
            $this->db->select($column);
        }
        if ($status != '') {
            $this->db->where('status', $status);
        }
        $query = $this->db->get($table);

        return $query->result_array();
    }

    function getAdDropdowns() {
        $cost_models = [
            'cpm',
            'fixed',
            'ppc'
        ];
        $main_ad_types = [
            'corporate',
            'standard',
            'classified'
        ];
        $ad_types = [
            'banner',
            'skyscraper',
            'thumb'
        ];

        foreach ($cost_models as $cost_model) {
            foreach ($main_ad_types as $main_ad_type) {
                foreach ($ad_types as $ad_type) {

                    if ($cost_model == 'cpm') {
                        $dropdown [$cost_model] [$main_ad_type] [$ad_type] ['imps'] = $this->getDropdownData('num_impressions', $main_ad_type, $ad_type, $cost_model . 'Pricing');
                        $dropdown [$cost_model] [$main_ad_type] [$ad_type] ['countries'] = $this->getDropdownData('num_countries', $main_ad_type, $ad_type, $cost_model . '_geo_dropdown');
                        $dropdown [$cost_model] [$main_ad_type] [$ad_type] ['states'] = $this->getDropdownData('num_states', $main_ad_type, $ad_type, $cost_model . '_geo_dropdown');
                        $dropdown [$cost_model] [$main_ad_type] [$ad_type] ['postcodes'] = $this->getDropdownData('num_postcodes', $main_ad_type, $ad_type, $cost_model . 'Pricing');
                    }

                    if ($cost_model == 'ppc') {
                        $dropdown [$cost_model] [$main_ad_type] [$ad_type] ['clicks'] = $this->getDropdownData('num_clicks', $main_ad_type, $ad_type, $cost_model . 'Pricing');
                        $dropdown [$cost_model] [$main_ad_type] [$ad_type] ['countries'] = $this->getDropdownData('num_countries', $main_ad_type, $ad_type, $cost_model . '_location_pricing');
                        $dropdown [$cost_model] [$main_ad_type] [$ad_type] ['states'] = $this->getDropdownData('num_states', $main_ad_type, $ad_type, $cost_model . '_location_pricing');
                        $dropdown [$cost_model] [$main_ad_type] [$ad_type] ['postcodes'] = $this->getDropdownData('num_postcodes', $main_ad_type, $ad_type, $cost_model . '_location_pricing');

                        // This is so we don't get empty dropdowns
                        $dropdown [$cost_model] [$main_ad_type] [$ad_type] ['imps'] = $this->getDropdownData('num_impressions', $main_ad_type, $ad_type, $cost_model . 'Pricing');
                    }

                    if ($cost_model == 'fixed') {
                        $dropdown [$cost_model] [$main_ad_type] [$ad_type] ['countries'] = $this->getDropdownData('number', $main_ad_type, $ad_type, $cost_model . 'Pricing', 'countries', 'num_countries');
                        $dropdown [$cost_model] [$main_ad_type] [$ad_type] ['states'] = $this->getDropdownData('number', $main_ad_type, $ad_type, $cost_model . 'Pricing', 'states', 'num_states');
                        $dropdown [$cost_model] [$main_ad_type] [$ad_type] ['postcodes'] = $this->getDropdownData('number', $main_ad_type, $ad_type, $cost_model . 'Pricing', 'postcodes', 'num_postcodes');
                    }

                    $dropdown [$cost_model] [$main_ad_type] [$ad_type] ['forums'] = $this->getDropdownData('numb_forums', $main_ad_type, $ad_type, $cost_model . '_forums_pricing', '', '', 'Active');

                    $dropdown [$cost_model] [$main_ad_type] [$ad_type] ['numb_agegroups'] = $this->getDropdownData('numb_agegroups', $main_ad_type, $ad_type, $cost_model . '_agegroups_pricing');
                    $dropdown [$cost_model] [$main_ad_type] [$ad_type] ['actual_agegroups'] = $this->getDropdownData('agegroups', $main_ad_type, $ad_type, $cost_model . '_agegroups_pricing');
                }
            }
        }

        $this->db->order_by('international', 'ASC');
        $query = $this->db->get('adDropdownInternational');

        $dropdown ['intl'] = $query->result_array();

        $this->db->order_by('agegroups', 'ASC');
        $query = $this->db->get('adDropdownAgeGroups');

        $dropdown ['agegroups'] = $query->result_array();

        return ($dropdown);
    }

    function saveAdOrder($adOrderParams) {
	  $this->load->helper(array('htmlpurifier', 'censor'));

	  $adOrderParams["company_name"] = censor_text(html_purify($adOrderParams["company_name"], 'post'));
	  $adOrderParams["firstname"] = censor_text(html_purify($adOrderParams["firstname"], 'post'));
	  $adOrderParams["lastname"] = censor_text(html_purify($adOrderParams["lastname"], 'post'));
	  $adOrderParams["email"] = censor_text(html_purify($adOrderParams["email"], 'post'));
	  $adOrderParams["street_no"]  = censor_text(html_purify($adOrderParams["street_no"], 'post'));
	  $adOrderParams["street"] = censor_text(html_purify($adOrderParams["street"], 'post'));
	  $adOrderParams["city"]   = censor_text(html_purify($adOrderParams["city"], 'post'));
	  $adOrderParams["state"]     = censor_text(html_purify($adOrderParams["state"], 'post'));
	  $adOrderParams["country"]    = censor_text(html_purify($adOrderParams["country"], 'post'));
	  $adOrderParams["postcode"]  = censor_text(html_purify($adOrderParams["postcode"], 'post'));
	  $adOrderParams["phone"] = censor_text(html_purify($adOrderParams["phone"], 'post'));
	  $adOrderParams["web"] = censor_text(html_purify($adOrderParams["web"], 'post'));// NB needs whitelisting to http/https only
   
        //check ad order has not already been inserted first
        $this->db->where('ad_orders.adId', $adOrderParams['adId']);
        $query = $this->db->get('ad_orders');

        //$ad_order = $query->row();

        if ($query->num_rows() > 0) {
            return;
        } else {
            $this->db->insert('ad_orders', $adOrderParams);
        }
    }

    function updateAdOrder($orderId, $tax, $subTotal, $totalPrice, $paid = 0, $purchaseDate) {

        if ($orderId != '') {
            $this->db->set('gst', $tax);
            $this->db->set('subTotal', $subTotal);
            $this->db->set('totalPrice', $totalPrice);
            $this->db->set('paid', $paid);
            $this->db->set('purchaseDate', $purchaseDate);
            $this->db->where('adId', $orderId);
            $this->db->update('ad_orders');
        } else {
            return null;
        }
    }
    
      function getAdOrderDetails($adId) {
        $this->db->where('ad_orders.adId', $adId);
        $query = $this->db->get('ad_orders');

        $ad_order = $query->row();

        if ($query->num_rows() > 0) {
            return ($ad_order);
        } else {
            return (NULL);
        }
    }

    function getAdOrderDetailsArr($adId) {
        $this->db->where('ad_orders.adId', $adId);
        $query = $this->db->get('ad_orders');

        $ad_order = $query->result_array();

        if ($query->num_rows() > 0) {
            return ($ad_order[0]);
        } else {
            return (NULL);
        }
    }
    
    function checkAdAdminUser($userId) {
        $this->db->select('userId');
        $this->db->where('userId', $userId)
                 ->where('adAdmin', 1);
        $query = $this->db->get('Permissions');

        $ad_order = $query->row();

        if ($query->num_rows() > 0) {
            return TRUE;
        } else {
            return FALSE;
        }
    }
    
    
     function checkAdAdminFreeAd($adId) {
        $this->db->select('adId');
        $this->db->where('adId', $adId)
                 ->where('adAdminFreeAd', 1);
        $query = $this->db->get('ad_orders');

        $ad_order = $query->row();

        if ($query->num_rows() > 0) {
            return TRUE;
        } else {
            return FALSE;
        }
    }
    
    
    function insertLandingPageAd($adId, $forumsArr, $landingPageHomefeed, $landingPagehomeFeedOnly, $selectedForums, $allForums) {

        if (!empty($adId)) {

            if ($allForums) {
                $forumValues[] = array(
                    'adId' => $adId,
                    'forumId' => 0, // landing page home feed 
                    'allForums' => $allForums
                );
            }

            // ad home feed
            if ($landingPageHomefeed) {
                $forumValues[] = array(
                    'adId' => $adId,
                    'forumId' => -1, // landing page home feed 
                    'allForums' => $allForums
                );
            }

            // add rest of selected forums if required
            if ($selectedForums) {
                // get the forums ids
                foreach ($forumsArr as $forum) {
                    if ($forum == 'All Users') {
                        $forumValues[] = array(
                            'adId' => $adId,
                            'forumId' => -1, // landing page home feed
                            'allForums' => $allForums
                        );
                    } else {
                        $this->db->select('subjectId');
                        $this->db->from('Subject');
                        $this->db->where('subjectName', $forum["subjectName"]);
                        $this->db->limit(1);
                        $query = $this->db->get();
                        $row = $query->row();

                        if ($query->num_rows() > 0) {
                            $forumValues[] = array(
                                'adId' => $adId,
                                'forumId' => $row->subjectId,
                                'allForums' => 0
                            );
                        }
                    }
                }
            }
            $this->db->insert_batch('landingPageAds', $forumValues);
        }
    }

    /*
     * Inserts ad criteria into the databased for an existing adId
     */

    function adInsert($adId, $mainAdCategory, $cost_model, $unit_price, 
                      $credit_price = 0, $totalPrice, $forums, $user_id, 
                      $ad_media, $ad_media_device, $international, $countries, $states, $postcode, 
                      $postCodeTypeListed, $url, $ad_type, $ad_style, $agegroups, 
                      $gender, $impressionsPurchased, $clicks, $title, $description, $landingPageAd, $joinBonusAdId) {

	  $this->load->helper(array('htmlpurifier', 'censor'));

        $end_dt = null;
        $start_dt = date('Y-m-d H:i:s');

        //if(empty($credit)) {
        //	$credit = 0;
        //}
        if (empty($credit_price)) {
            $credit_price = 0;
        }

		//hack!
		if($ad_type === 'banner') {
			$size = null;
		} else {
			$size = 'small';
		}
		

        if (!empty($cost_model) && $cost_model == 'fixed') {
            $end_dt = date('Y-m-d H:i:s', strtotime(date($start_dt) . " + 1 year"));
        }
		$ad_params = array(
			'mainAdCategory' => censor_text(html_purify($mainAdCategory, 'post')),
			'unitPrice' => $unit_price,
			'creditUsed' => $credit_price,
			'totalPrice' => $totalPrice,
			'filename' => $ad_media,
			'deviceFilename' => $ad_media_device,
			'userid' => $user_id,
			'url' => censor_text(html_purify($url, 'url')),
			// 'credit' => $credit,
			'type' => censor_text(html_purify($ad_type, 'post')),
			'style' => censor_text(html_purify($ad_style, 'post')),
			'landingPageAd' => censor_text(html_purify($landingPageAd, 'post')),
			'impressionsPurchased' => $impressionsPurchased,
			'clicks' => $clicks,
			'headerText' => censor_text(html_purify($title, 'post')),
			'footerText' => censor_text(html_purify($description, 'post')),
			'startDate' => $start_dt,
			'endDate' => $end_dt,
			'status' => 'Incomplete',
			'joinBonusAdId' => $joinBonusAdId,
			'size' => $size //tmp solution!!!
		);

		$this->db->set($ad_params);
        $this->db->where('adId', $adId);
        $this->db->update('Advert');

        // get the forums ids
        foreach ($forums as $forumName) {

            if ($forumName == 'All Users') {
                $forumValues[] = array(
                    'adId' => $adId,
                    'forumId' => 1
                        ); // home feed
                $forumValues[] = array(
                    'adId' => $adId,
                    'forumId' => 714
                        ); // main feed
                
            } else {
                $this->db->select('subjectId');
                $this->db->from('Subject');
                $this->db->where('subjectName', $forumName);
                $this->db->limit(1);
                $query = $this->db->get();
                $row = $query->row();

                if ($query->num_rows() > 0) {
                    $forumValues[] = array(
                        'adId' => $adId,
                        'forumId' => $row->subjectId
                    );
                }
            }
        }
		//delete any existing entries as could be a page refresh!
		$this->db->delete('adToForum', array('adId' => $adId));
        // map the ads to the forum groups
        $this->db->insert_batch('adToForum', $forumValues);

        // insert the international value
        $this->insertSingleAdCriteria($adId, $international, 'adToInternational', 'international');

        // insert the countries
        $this->insertAdCriteria($adId, $countries, 'adToCountry', 'country');

        // insert the states
        $this->insertAdCriteria($adId, $states, 'adToState', 'state');

        // insert the postcodes
        //$this->insertAdCriteria($adId, $postcode, 'adToPostCode', 'postcode');
        $this->insertAdPostCodeCriteria($adId, $postcode, $postCodeTypeListed, $states, $countries); 

        // age groups
        $this->insertAdCriteria($adId, $agegroups, 'adToAgeGroup', 'agegroup');

        // insert ad to gender
        $this->insertSingleAdCriteria($adId, $gender, 'adToGender', 'gender');
    }

    function insertSingleAdCriteria($adId, $criteria, $table, $column) {
	  $this->load->helper(array('htmlpurifier', 'censor'));
        $criteria_values = array(
            'adId' => $adId,
            $column => censor_text(html_purify($criteria, 'post'))
        );
		$this->db->delete($table, array('adId' => $adId));
        $this->db->insert($table, $criteria_values);
    }
	
    function insertAdCriteria($adId, $criteriaArr, $table, $column) {
	  $this->load->helper(array('htmlpurifier', 'censor'));
        foreach ($criteriaArr as $criteria) {
            $criteriaValues[] = array(
                'adId' => $adId,
                $column => censor_text(html_purify($criteria, 'post'))
            );
        }
		$this->db->delete($table, array('adId' => $adId));
        $this->db->insert_batch($table, $criteriaValues);
    }
	
    function insertAdPostCodeCriteria($adId, $postcodes, $postCodeTypeListed, $states, $countries) {
        foreach ($postcodes as $postcodeId) {
            if($postCodeTypeListed && $postcodeId !== 'None') {
                $postCode = $this->getPostCode($postcodeId);
                $postCode = $postCode[0]["postCode"];
            } else {
                $postCode = $postcodeId; 
                $postcodeId = 0;
				$states[0] = 0;
                $countries[0] = 0;
            }
            $criteriaValues[] = array(
                'adId' => $adId,
                'postcode' => $postCode,
                'postcodeId' => $postcodeId,
                'stateId' => $states[0],
                'countryId' => $countries[0]
            );
        }
		$this->db->delete('adToPostCode', array('adId' => $adId));
        $this->db->insert_batch('adToPostCode', $criteriaValues);
    }
    
    function insertAdPostCodeCriteriaObj($adId, $postcodes, $states, $countries) {
        foreach ($postcodes as $postcodeId) {
            $criteriaValues[] = array(
                'adId' => $adId,
                'postcode' => $this->getPostCode($postcodeId->postcodeId)->postCode,
                'postcodeId' => $postcodeId->postcodeId,
                'stateId' => $states[0]->state,
                'countryId' => $countries[0]->country
            );
        }
		$this->db->delete('adToPostCode', array('adId' => $adId));
        $this->db->insert_batch('adToPostCode', $criteriaValues);
    }
    
    function getParentAdPostCodes($adId = '', $user = '') {
        if ($adId != '') {

            $this->db->select('*');
            $this->db->from('adToPostCode');
            $this->db->where('adToPostCode.adId', $adId);
            $query = $this->db->get();
            $postcodes = $query->result_array();

            if ($query->num_rows() > 0) {
                return ($postcodes);
            } else {
                return (NULL);
            }
        }
    }
    
    function insertParentAdPostCodes($childId = '', $postCodesArr) {
        foreach ($postCodesArr as $postCode) {
            $postCodeParams[] = array(
                'adId' => $childId,
                'postcode' => $postCode["postcode"],
                'postcodeId' => $postCode["postcodeId"],
                'stateId' => $postCode["stateId"],
                'countryId' => $postCode["countryId"]
            );
        }
		$this->db->delete('adToPostCode', array('adId' => $childId));
        $this->db->insert_batch('adToPostCode', $postCodeParams);
    }
    
    function insertAdCriteriaObj($adId, $criteria_obj, $table, $object_field) {
	  $this->load->helper(array('htmlpurifier', 'censor'));
        foreach ($criteria_obj as $criteria) {
            $criteriaValues[] = array(
                'adId' => $adId,
                $object_field => censor_text(html_purify($criteria->$object_field,'post'))
            );
        }
		$this->db->delete($table, array('adId' => $adId));
        $this->db->insert_batch($table, $criteriaValues);
    }
	
	function isPrivateForum($forumName) {
		
		$this->db->select('private')
				->from('Subject')
				->where('subjectName', $forumName)
				->where('private', 1);
		
		$query = $this->db->get();

		if ($query->num_rows() > 0) {
			return TRUE;
		} else {
			return FALSE;
        }
	}

    function getAdForums($adId = '', $user = '') {
        if ($adId != '') {

            $this->db->distinct();
            $this->db->select('subjectName');
            $this->db->from('Subject');
            $this->db->join('adToForum', 'adToForum.forumId = Subject.subjectId');
            $this->db->where('adToForum.adId', $adId);
            $query = $this->db->get();
            $forumNames = $query->result_array();

            if ($query->num_rows() > 0) {
                return ($forumNames);
            } else {
                return (NULL);
            }
        }
    }

    function getAdforumIds($adId = '', $user = '') {
        if ($adId != '') {

            $this->db->select('adToForum.forumId');
            $this->db->from('adToForum');
            $this->db->where('adToForum.adId', $adId);
            $query = $this->db->get();
            $forumIds = $query->result();

            if ($query->num_rows() > 0) {
                return ($forumIds);
            } else {
                return (NULL);
            }
        }
    }
    
    function isLandingPageAd($adId = '', $user = '') {
        if ($adId != '') {

            $this->db->select('adId');
            $this->db->from('landingPageAds');
            $this->db->where('adId', $adId);
            $query = $this->db->get();
            $forumIds = $query->result();

            if ($query->num_rows() > 0) {
                return TRUE;
            } else {
                return FALSE;
            }
        }
    }
    
    function landingPageHomeFeed($adId = '', $user = '') {
        if ($adId != '') {

            $this->db->select('forumId');
            $this->db->from('landingPageAds');
            $this->db->where('adId', $adId)
                     ->where('forumId', -1);
            $query = $this->db->get();
            $forumIds = $query->result();

            if ($query->num_rows() > 0) {
                return TRUE;
            } else {
                return FALSE;
            }
        }
    }
    
     function landingPageAllForums($adId = '', $user = '') {
        if ($adId != '') {

            $this->db->select('forumId');
            $this->db->from('landingPageAds');
            $this->db->where('adId', $adId)
                     ->where('allForums', 1);
            $query = $this->db->get();
            $forumIds = $query->result();

            if ($query->num_rows() > 0) {
                return TRUE;
            } else {
                return FALSE;
            }
        }
    }

    function getAdInternational($adId = '', $user = '') {
        if ($adId != '') {

            $this->db->select('international');
            $this->db->from('adToInternational');
            $this->db->where('adToInternational.adId', $adId);
            $query = $this->db->get();
            $international = $query->result_array();

            if ($query->num_rows() > 0) {
                return ($international);
            } else {
                return (NULL);
            }
        }
    }

    function getCountryNames($countryIdArr) {
        if (!empty($countryIdArr)) {

            $this->db->distinct();
            $this->db->select('name');
            $this->db->from('Country');
            $this->db->where_in('countryId', $countryIdArr);
            $query = $this->db->get();
            $countries = $query->result();

            if ($query->num_rows() > 0) {
                return ($countries);
            } else {
                return (NULL);
            }
        }
    }

    function getStateNames($stateIdArr) {
        if (!empty($stateIdArr)) {

            $this->db->select('name');
            $this->db->from('State');
            $this->db->where_in('stateId', $stateIdArr);
            $query = $this->db->get();
            $states = $query->result();

            if ($query->num_rows() > 0) {
                return ($states);
            } else {
                return (NULL);
            }
        }
    }

    function getPostcodeNames($postcodeIdArr) {
        if (!empty($postcodeIdArr)) {

            $this->db->select("CONCAT(postCode, '  ', locality) AS 'postcodeName'", FALSE);
            $this->db->from('postCode');
            $this->db->where_in('postcodeId', $postcodeIdArr);
            $query = $this->db->get();
            $postcodes = $query->result();

            if ($query->num_rows() > 0) {
                return ($postcodes);
            } else {
                return (NULL);
            }
        }
    }
    
     function getPostcode($postcodeId) {
        if (!empty($postcodeId)) {
            $this->db->select('postCode');
            $this->db->from('postCode');
            $this->db->where('postCodeId', $postcodeId);
            $query = $this->db->get();
            $postcode = $query->result_array();
            if ($query->num_rows() > 0) {
                log_message('error', 'ret postcodes:' . $postcode[0]["postCode"]);
                return ($postcode);
                 
            } else {
                 log_message('error', 'ret BULLLLLpostcodes:');
                return (NULL);
            }
        }
    }

    function getPostcodeId($postcode) {
        if (!empty($postcode)) {

            $this->db->select('postCodeId');
            $this->db->from('postCode');
            $this->db->where('postcode', $postcode);
            $query = $this->db->get();
            $postcodeId = $query->result();

            if ($query->num_rows() > 0) {
                return ($postcodeId);
            } else {
                return (NULL);
            }
        }
    }

    function getAdCountries($adId = '', $user = '') {
        if ($adId != '') {

            $this->db->select('name AS country');
            $this->db->from('Country');
            $this->db->join("adToCountry", "adToCountry.country = Country.countryId");
            $this->db->where('adToCountry.adId', $adId);
            $query = $this->db->get();
            $countries = $query->result_array();

            if ($query->num_rows() > 0) {
                return ($countries);
            } else {
                return (NULL);
            }
        }
    }

    function getAdPostCodeNames($adId = '', $user = '') {
        if ($adId != '') {
            $this->db->select("CONCAT(postCode.postCode, '  ', postCode.locality) AS 'postcodeName'", FALSE);
            $this->db->from('postCode');
            $this->db->join("adToPostCode", "adToPostCode.postcodeId = postCode.postcodeId");
            $this->db->where('adToPostCode.adId', $adId);
            $query = $this->db->get();
            $postcodes = $query->result_array();
            if ($query->num_rows() > 0) {
                return ($postcodes);
            } else {
                $this->db->select('postcode AS postcodeName');
                $this->db->from('adToPostCode');
                $this->db->where('adToPostCode.adId', $adId);
                $query = $this->db->get();
                $postcodes = $query->result_array();
                 if ($query->num_rows() > 0) {
                return ($postcodes);
                 } else {
                    return (NULL);
                }
            }
        }
    }

    function getAdStateNames($adId = '', $user = '') {
        if ($adId != '') {

            $this->db->select('name AS stateName');
            $this->db->from('State');
            $this->db->join("adToState", "adToState.state = State.stateId");
            $this->db->where('adToState.adId', $adId);
            $query = $this->db->get();
            $states = $query->result_array();

            if ($query->num_rows() > 0) {
                return ($states);
            } else {
                return (NULL);
            }
        }
    }

    function getAdCountryIds($adId = '', $user = '') {
        if ($adId != '') {

            $this->db->select('country');
            $this->db->from('adToCountry');
            $this->db->where('adToCountry.adId', $adId);
            $query = $this->db->get();
            $countries = $query->result();

            if ($query->num_rows() > 0) {
                return ($countries);
            } else {
                return (NULL);
            }
        }
    }

    function getAdStates($adId = '', $user = '') {
        if ($adId != '') {

            $this->db->select('state');
            $this->db->from('adToState');
            $this->db->where('adToState.adId', $adId);
            $query = $this->db->get();
            $states = $query->result_array();

            if ($query->num_rows() > 0) {
                return ($states);
            } else {
                return (NULL);
            }
        }
    }

    // note that the above fn also returns the IDS
    function getAdStateIds($adId = '', $user = '') {
        if ($adId != '') {

            $this->db->select('state');
            $this->db->from('adToState');
            $this->db->where('adToState.adId', $adId);
            $query = $this->db->get();
            $states = $query->result();

            if ($query->num_rows() > 0) {
                return ($states);
            } else {
                return (NULL);
            }
        }
    }

    function getAdPostCodes($adId = '', $user = '') {
        if ($adId != '') {

            $this->db->distinct();
            $this->db->select('postcode');
            $this->db->from('adToPostCode');
            $this->db->where('adToPostCode.adId', $adId);
            $query = $this->db->get();
            $postcodes = $query->result_array();

            if ($query->num_rows() > 0) {
                return ($postcodes);
            } else {
                return (NULL);
            }
        }
    }

    function getAdPostCodeIds($adId = '', $user = '') {
        if ($adId != '') {

            $this->db->distinct();
            $this->db->select('postcodeId');
            $this->db->from('adToPostCode');
            $this->db->where('adToPostCode.adId', $adId);
            $query = $this->db->get();
            $postcodes = $query->result();

            if ($query->num_rows() > 0) {
                return ($postcodes);
            } else {
                return (NULL);
            }
        }
    }

    function getAdAgeGroups($adId = '', $user = '') {
        if ($adId != '') {

            $this->db->select('agegroup');
            $this->db->from('adToAgeGroup');
            $this->db->where('adToAgeGroup.adId', $adId);
            $query = $this->db->get();
            $agegroups = $query->result_array();

            if ($query->num_rows() > 0) {
                return ($agegroups);
            } else {
                return (NULL);
            }
        }
    }

    function getAdAgeGroupsObj($adId = '', $user = '') {
        if ($adId != '') {

            $this->db->select('agegroup');
            $this->db->from('adToAgeGroup');
            $this->db->where('adToAgeGroup.adId', $adId);
            $query = $this->db->get();
            $agegroups = $query->result();

            if ($query->num_rows() > 0) {
                return ($agegroups);
            } else {
                return (NULL);
            }
        }
    }

    function getAdGender($adId = '', $user = '') {
        if ($adId != '') {

            $this->db->select('gender');
            $this->db->from('adToGender');
            $this->db->where('adToGender.adId', $adId);
            $query = $this->db->get();
            $gender = $query->result_array();

            if ($query->num_rows() > 0) {
                return ($gender);
            } else {
                return (NULL);
            }
        }
    }

    function getUserDetails($user_id = '') {

        if ($user_id != '') {

            $user_columns = array(
                'firstName' => 'firstname',
                'lastName' => 'lastname',
                'avatar' => 'avatar',
                'gender' => 'gender',
                'username' => 'username',
                'email' => 'email',
                'skype' => 'skype',
                'mobile' => 'mobile',
                'mobile' => 'mobile',
                'age' => 'age',
                'dob' => 'dob',
                'mobile' => 'mobile',
                'countryId' => 'countryId',
                'stateId' => 'stateId',
                'postcode' => 'postcode',
                'timezone' => 'timezone',
                'userId' => 'userId'
            );

            $this->db->select($user_columns);
            $this->db->from('User');
            $this->db->where('User.userId', $user_id);
            $query = $this->db->get();
            $user_details = $query->result_array();

            if ($query->num_rows() > 0) {
                return ($user_details);
            } else {
                return (NULL);
            }
        }
    }

    function getUserDetailsObj($user_id = '') {

        if ($user_id != '') {

            $user_columns = array(
                'firstName' => 'firstname',
                'lastName' => 'lastname',
                'avatar' => 'avatar',
                'gender' => 'gender',
                'username' => 'username',
                'email' => 'email',
                'skype' => 'skype',
                'mobile' => 'mobile',
                'mobile' => 'mobile',
                'age' => 'age',
                'dob' => 'dob',
                'mobile' => 'mobile',
                'countryId' => 'countryId',
                'stateId' => 'stateId',
                'postcode' => 'postcode',
                'timezone' => 'timezone',
                'userId' => 'userId'
            );

            $this->db->select($user_columns)->from('User')->where('User.userId', $user_id);

            $query = $this->db->get();
            $user_details = $query->result();
            $user_details = $user_details [0];

            if ($query->num_rows() > 0) {

                return ($user_details);
            } else {

                return (NULL);
            }
        }
    }

    function getAdStatus($adId = '') {
        if ($adId != '') {

            $this->db->select('status');
            $this->db->from('Advert');
            $this->db->where('Advert.adId', $adId);
            $query = $this->db->get();
            $status = $query->row();

            if ($query->num_rows() > 0) {
                return ($status);
            } else {
                return (NULL);
            }
        }
    }

    function createBonusAd($adId, $type, $style, $cost_model, $status, $clicks = 0, $impressions = 0, $user = '') {
	  $this->load->helper(array('htmlpurifier', 'censor'));   
        $ad = $this->getAdDetails($adId);
        $ad = $ad [0];

        if ($cost_model === 'ppc') {
            $impressions = 0;
        } else if ($cost_model === 'cpm') {
            $clicks = 0;
        }

        $ad_params = array(
            'parentAdId' => $adId,
            'freeAd' => 1,
            'mainAdCategory' => $ad ['mainAdCategory'],
            'costmodel' => censor_text(html_purify($cost_model,'post')),
            'type' => censor_text(html_purify($type,'post')),
            'style' => censor_text(html_purify($style,'post')),
            'userId' => $ad ['userId'],
            'status' => censor_text(html_purify($status,'post')),
            'booked' => '0',
            'clicks' => $clicks,
            'impressionsPurchased' => $impressions,
            'unitPrice' => '0',
            'startDate' => date("Y-m-d h:i:s", strtotime($ad ['startDate'])),
            'endDate' => date("Y-m-d h:i:s", strtotime($ad ['endDate']))
        );

        $this->db->insert('Advert', $ad_params);
        $child_id = $this->db->insert_id();

        $this->adArchiveInsert($child_id);

        if ($type === 'post') {
            $this->load->model('post');
            
            $nodeData = array(
                'about' => "",
                'author' => "",
                'body' => "",
                'category' => "",
                'commentedPostId' => "",
                'endTime' => " ",
                'fileName' => "",
                'group' => "my feed",
                'parent' => "",
                'post' => "",
                'fileName' => "",
                'startTime' => " ",
                'subject' => "",
                'type' => "post_ad",
                'user' => "",
                'postAdId' => $adId,
                'url' => "",
                'postAdVotingText1' => "",
                'postAdVotingText2' => ""
                );
                
                
            //$post = $this->post->storeNode($nodeData);
            //$this->setVisibilty();
            $this->updatePostChildId($adId, $child_id);
        } else {

            $this->updateBonusChildAds($adId, $child_id, $ad ['mainAdCategory'], $cost_model, $style);

            $parent_forums = $this->getAdForumIds($adId);
            // map the ad to the forum group
            $this->insertAdCriteriaObj($child_id, $parent_forums, 'adToForum', 'forumId');

            $international = $this->getAdInternational($adId);
            $international = $international [0];
            // insert the international value
            $this->insertAdCriteria($child_id, $international, 'adToInternational', 'international');

            $countries = $this->getAdCountryIds($adId);
            // insert the countries
            $this->insertAdCriteriaObj($child_id, $countries, 'adToCountry', 'country');

            $states = $this->getAdStateIds($adId);
            // insert the states
            $this->insertAdCriteriaObj($child_id, $states, 'adToState', 'state');

            //$postcodes = $this->getAdPostCodeIds($adId);
            $postcodesArr = $this->getParentAdPostCodes($adId);
            // insert the postcodes
            //$this->insertAdCriteriaObj($child_id, $postcodes, 'adToPostCode', 'postcode');
            //$this->insertAdPostCodeCriteriaObj($child_id, $postcodes, $states, $countries);
            $this->insertParentAdPostCodes($child_id, $postcodesArr);

            $agegroups = $this->getAdAgeGroupsObj($adId);
            // age groups
            $this->insertAdCriteriaObj($child_id, $agegroups, 'adToAgeGroup', 'agegroup');

            $gender = $this->getAdGender($adId);
            $gender = $gender [0];
            // insert ad to gender
            $this->insertAdCriteria($child_id, $gender, 'adToGender', 'gender');
        }
    }

    /*
     * Creates entries in bonus_ads for a user's bonus ad entitlement
     * when purchasing a new ad
     *
     * @param bigint $adId
     *
     */

    function createAllBonusAds($adId) {
        if ($adId != '') {
            $this->db->select('mainAdCategory');
            $this->db->select('cost_model');
            $this->db->select('type');
            $this->db->select('style');
            $this->db->select('user_id');
            $this->db->select('impressions');
            $this->db->select('clicks');
            $this->db->select('start_date');
            $this->db->select('end_date');
            $this->db->select('status');
            $this->db->from('bonus_ads');
            $this->db->where('bonus_ads.parentAdId', $adId)
                     ->where('bonus_ads.bonusAdId', '0')
                     ->where('bonus_ads.status', 'pending')
                     ->where('bonus_ads.style !=', 'Post Ad'); //these are handled else where
            $query = $this->db->get();

            if ($query->num_rows() > 0) {
                foreach ($query->result() as $bonus_ads) {
                    $new_status = 'Free ' . ucfirst($bonus_ads->style) . ' Ad - available';
                    $this->createBonusAd($adId, lcfirst($bonus_ads->type), $bonus_ads->style, $bonus_ads->cost_model, $new_status, $bonus_ads->clicks, $bonus_ads->impressions, $bonus_ads->user_id);
                }
            }
        }
    }

    function updatePostChildId($adId, $child_id) {
        if ($adId != '') {
            $this->db->set('postChildId', $child_id);
            $this->db->where('adId', $adId);
            $this->db->update('Advert');
        }
    }

    function updateBonusChildAds($adId, $child_id, $mainAdCategory, $cost_model, $style) {
        if ($adId != '') {

            $this->db->set('bonusAdId', $child_id);
            $this->db->set('status', 'created');
            $this->db->where('parentAdId', $adId);
            $this->db->where('bonusAdId', '0');
            $this->db->where('mainAdCategory', $mainAdCategory);
            $this->db->where('cost_model', $cost_model);
            $this->db->where('style', $style);
            $this->db->update('bonus_ads');
        }
    }

    function getAdDetails($adId = '', $user = '', $free = 0) {

        if ($adId != '') {
            $this->db->where('Advert.adId', $adId);
        }

        if ($user != '') {
            $this->db->where('Advert.userId', $user);
        }

        $advert_columns = array(
            'id' => 'Advert.adId AS id',
            'mainAdCategory' => 'Advert.mainAdCategory',
            'type' => 'Advert.type',
            'clicks' => 'Advert.numHits AS clicks',
            'clicksDevice' => 'Advert.numHitsDevice AS clicksDevice',
            'num_clicks' => 'Advert.clicks AS num_clicks',
            'impressions' => 'Advert.impressions',
            'impressionsDevice' => 'Advert.impressionsDevice',
            'impressionsPurchased' => 'Advert.impressionsPurchased AS num_imps',
            'style' => 'Advert.style',
            'costModel' => 'Advert.costModel',
            //'credit' => 'Advert.credit',
            'unitPrice' => 'Advert.unitPrice',
            'totalPrice' => 'Advert.totalPrice',
            'creditUsed' => 'Advert.creditUsed', // this is any credit used for this ad
            'url' => 'Advert.url',
            'media' => 'Advert.filename AS media',
            'booked' => 'Advert.booked',
            'title' => 'Advert.headerText AS title',
            'description' => 'Advert.footerText AS description',
            'status' => 'Advert.status',
            'statusId' => 'Advert.statusId',
            'modUnderReview' => 'Advert.modUnderReview',
            'modPaused' => 'Advert.modPaused',
            'modRemoved' => 'Advert.modRemoved',
            'startDate' => "DATE_FORMAT(Advert.startDate,'%b %d %Y %h:%i:%s %p') AS startDate",
            'endDate' => "DATE_FORMAT(Advert.endDate,'%b %d %Y %h:%i:%s %p') AS endDate",
            'price' => 'Advert.unitPrice AS price',
            'userId' => 'Advert.userId',
            'parentAdId' => 'Advert.parentAdId',
            'postChildId' => 'Advert.postChildId',
            'freeAd' => 'Advert.freeAd',
            'paid' => 'Advert.paid',
            'fileName' => 'Advert.fileName',
            'deviceFileName' => 'Advert.deviceFileName',
            'landingPageAd' => 'Advert.landingPageAd',
            'joinBonusAdId' => 'Advert.joinBonusAdId',
            'voteText1' => 'Advert.voteText1',
            'voteText2' => 'Advert.voteText2'
        );

        $this->db->where('status !=', 'NULL');
        $this->db->where('statusId !=', 18); //join bonus ad placeholder - now been created
        if (!empty($free) && $free === 'free') {
            $this->db->where('freead', 1);
        } else if (!empty($free) && $free === 'paid') {
            $this->db->where('freead', 0);
        }
       
        $this->db->select($advert_columns);
        $this->db->order_by('id', 'DESC');

        $this->db->from('Advert');

        $query = $this->db->get();

        if ($query->num_rows() > 0) {
            $result = $query->result_array();
            return ($result);
        } else {
            log_message('error', 'ad->getAdDetails(): No ad details found for adId: ' . $adId);
            return (NULL);
        }
    }

    /*
     * This function is used for testing only
     */

    function getEndDate($adId) {

        $this->db->select('endDate');
        $this->db->from('Advert');
        $this->db->where('Advert.adId', $adId);
        $query = $this->db->get();

        if ($query->num_rows() > 0) {
            $endDate = $query->row();
            return ($endDate);
        } else {
            return (NULL);
        }
    }

    function getAdPrice($adId, $userId) {
        if ($adId != '') {
            $this->db->select('unitPrice as price');
            $this->db->select('type AS product_name');
            $this->db->select('mainAdCategory AS product_desc');
            $this->db->from('Advert');
            $this->db->where('Advert.adId', $adId);
            $this->db->where('Advert.userId', $userId);
            $query = $this->db->get();
            $price_info = $query->row();

            if ($query->num_rows() > 0) {
                return ($price_info);
            } else {
                log_message('error', 'ad->getAdPrice(): No ad price found for adId:' . $adId);
                return (NULL);
            }
        }
    }
    
    function getAdPaymentPrice($adId, $userId) {
        if ($adId != '') {
            $this->db->select('unitPrice as price');
            $this->db->select('type AS product_name');
            $this->db->select('mainAdCategory AS product_desc');
            $this->db->from('Advert');
            $this->db->where('Advert.adId', $adId);
            $this->db->where('Advert.userId', $userId);
            $this->db->where('Advert.statusId !=', 1);
            $this->db->where('Advert.paid', 0);
            $this->db->where("(Advert.status = 'Incomplete' OR Advert.status = 'quote')", NULL, FALSE);
            $query = $this->db->get();
            $price_info = $query->row();

            if ($query->num_rows() > 0) {
                return ($price_info);
            } else {
                log_message('error', 'ad->getAdPrice(): No ad price found for adId:' . $adId);
                return (NULL);
            }
        }
    }

    // could use get ad details here but that returns and array and this returns an object
    function getAdReceiptDetails($adId) {
        if ($adId != '') {

            $this->db->select('mainAdCategory');
            $this->db->select('style');
            $this->db->select('costModel AS cost_model');
            $this->db->select('userId');
            $this->db->select('postChildId');
            $this->db->select('startDate');
            $this->db->select('endDate');
            $this->db->select('clicks');
            $this->db->select('impressionsPurchased');
            $this->db->select('creditUsed');
            $this->db->select('fileName');
            $this->db->select('deviceFileName');
            $this->db->select('statusId');
            $this->db->select('joinBonusAdId');
            $this->db->from('Advert');
            $this->db->where('Advert.adId', $adId);
            $query = $this->db->get();
            $receipt_details = $query->row();

            if ($query->num_rows() > 0) {
                return ($receipt_details);
            } else {
                log_message('error', 'ad->getAdReceiptDetails(): No ad receipt details found for adId:' . $adId);
                return (NULL);
            }
        }
    }

    function getAdOrderId($token) {
        if ($token != '') {

            $this->db->select('order_id');
            $this->db->from('paypal_set_checkout');
            $this->db->where('paypal_set_checkout.token', $token);
            $query = $this->db->get();
            $order_id = $query->row();

            if ($query->num_rows() > 0) {
                return ($order_id);
            } else {
                return (NULL);
            }
        }
    }

    function getChildAdId($parentAdId, $style) {


        if ($parentAdId != '') {

            $this->db->select('adId');
            $this->db->from('Advert');
            $this->db->where('parentAdId', $parentAdId);
            $this->db->where('style', $style);
            $query = $this->db->get();
            $child_adId = $query->row();

            if ($query->num_rows() > 0) {
                return ($child_adId);
            } else {
                return (NULL);
            }
        }
    }

    function getAdText($adId) {
        if ($adId != '') {

            $this->db->select('headerText AS title');
            $this->db->select('footerText AS description');
            $this->db->from('Advert');
            $this->db->where('Advert.adId', $adId);
            $query = $this->db->get();
            $ad_text = $query->result_array();

            if ($query->num_rows() > 0) {
                return ($ad_text [0]);
            } else {
                return (NULL);
            }
        }
    }

    function updateAdText($adId, $ad_text) {
        if ($adId != '') {
	  $this->load->helper(array('htmlpurifier', 'censor'));

	  $ad_text['title'] = censor_text(html_purify($ad_text['title'], 'post'));
	  $ad_text['description'] = censor_text(html_purify($ad_text['description'], 'post'));

            $this->db->set('headerText', $ad_text ['title']);
            $this->db->set('footerText', $ad_text ['description']);
            $this->db->where('adId', $adId);
            $this->db->update('Advert');
        }
    }

    function updateAdImage($adId, $filename, $mobile='') {
        if ($adId != '') {

            if($mobile) {
                $this->db->set('devicefileName', $filename);
            } else {
                $this->db->set('fileName', $filename);
            }
            
            $this->db->where('adId', $adId);
            $this->db->update('Advert');
        }
    }

    function getNodeId($postId) {
        if ($postId != '') {

            $this->db->select('nodeId');
            $this->db->from('Nodes');
            $this->db->where('Nodes.postId', $postId);
            $query = $this->db->get();
            $node = $query->row();

            if ($query->num_rows() > 0) {
                return ($node->nodeId);
            } else {
                return (NULL);
            }
        }
    }

    function getDummyNodeId() {
        
        $this->db->select('nodeId');
        $this->db->from('Nodes');
        $this->db->limit(1);
        $query = $this->db->get();
        $node = $query->row();

        if ($query->num_rows() > 0) {
            return ($node->nodeId);
        } else {
            return (NULL);
        }
    }

    function getPostAds() {

        $this->db->select('post_ads.postId');
        $this->db->select('Post.timeStamp');
        $this->db->from('post_ads');
        $this->db->join('Post', 'Post.postId = post_ads.postId');
        $this->db->where('post_ads.booked', '1');
        //$this->db->where('now() <=', 'post_ads.endDate');
        $query = $this->db->get();
        $ads = $query->result_array();
        if ($query->num_rows() > 0) {
            // $ads = $ads[0];
            return ($ads);
        } else {
            return (NULL);
        }
    }
    
    function removeContentFromNode($postId, $fileName) {
        
       $this->load->model('user');
       
        if ($postId != '') {
            $userId = $this->user->authCheck();
            $nodeId = $this->getNodeId($postId); 
            
            $params = array('filename' => $fileName,
                'nodId' => $nodeId,
                'userId' => $userId
            );

            $query = "CALL removeContentFromNodes(?,?,?);";
            $query = $this->db->query($query, $params);
        }
        
    }
    

    function updatePostAdImage($postId, $current_filename = '') {
        
        $this->load->model('user');
        if ($postId != '') {
            $userId = $this->user->authCheck();
            $nodeId = $this->getNodeId($postId);

            $params = array('filename' => $current_filename,
                'nodId' => $nodeId,
                'userId' => $userId
            );
            $query = "CALL removeContentFromNodes(?,?,?);";
            $query = $this->db->query($query, $params);
            // if they're uploading media
            if (isset($_FILES["post_ad_media"])) {
                $filename = $this->file->store(array(
                    'local' => $_FILES["post_ad_media"]["tmp_name"],
                    'group' => 1,
                    'comment' => '',
                    'photo' => base64_encode(file_get_contents($_FILES["post_ad_media"]["tmp_name"])),
                    'parent' => $nodeId
                        ), TRUE
                );

                $newFileName = NULL;
                if (!empty($filename)) {
                    $newFileName = $filename[0];
                }

                $data = array('filename' => $newFileName,
                    'nodeId' => $nodeId
                );

                //$this->addContentToNode($data);
                //update the Advert table for archive and other reasons
                $parentId = $this->getPostAdParentId($postId);
                $this->db->set('filename', $newFileName);
                $this->db->where('parentAdId', $parentId);
                $this->db->where('style', 'post');
                $this->db->update('Advert');
            } 
        }
        return $newFileName;
    }

    function addContentToNode($params) {
        $this->db->insert('ContentToNodes', $params);
    }

    function updateAdUrl($adId, $url, $style = '', $parentAdId = '') {

        if ($adId != '') {
	    $this->load->helper(array('htmlpurifier'));
            if(!empty($url) && !filter_var($url, FILTER_VALIDATE_URL)) {
                $url = 'invalid';
            } else if(!empty($url) && !$this->file->checkURLWithGoogle($url)) {
                $url = 'blacklisted';
            }

            if ($style === 'post') {
	      $this->db->set('url', html_purify($url,'url'));
                $this->db->where('parentAdId', $parentAdId);
                $this->db->update('post_ads');
                
            }
            
            $this->db->set('url', html_purify($url,'url'));
            $this->db->where('adId', $adId);
            $this->db->update('Advert');
        }
    }
    
      function updatePostAdUrl($adId, $url, $style = '', $parentAdId = '') {

        if ($adId != '') {
	  $this->load->helper(array('htmlpurifier'));
            if(!empty($url) && !filter_var($url, FILTER_VALIDATE_URL)) {
                $url = 'invalid';
            } else if(!empty($url) && !$this->file->checkURLWithGoogle($url)) {
                $url = 'blacklisted';
            }
            
            $this->db->set('url', html_purify($url,'url'));
            $this->db->where('parentAdId', $adId);
            $this->db->update('post_ads');

            $this->db->set('url', html_purify($url,'url'));
            $this->db->where('parentAdId', $adId)
                    ->where('style', 'post');
            $this->db->update('Advert');
        }
    }

    function updateAdVotingText($adId, $parentAdId, $voting_text1, $voting_text2, $reset_votes) {
        
        if(!$adId) {
            $adId = $this->getPostAdChildId($parentAdId);
        }

        if ($parentAdId != '') {
	  $this->load->helper(array('htmlpurifier', 'censor'));

	  $voting_text1 = censor_text(html_purify($voting_text1, 'post'));
	  $voting_text2 = censor_text(html_purify($voting_text2, 'post'));


            //this will be removed from post ads and only be in Advert
            $this->db->set('voteText1', $voting_text1);
            $this->db->set('voteText2', $voting_text2);

            if ($reset_votes) {
                $this->db->set('vote1', 0);
                $this->db->set('vote2', 0);
               
            }

            //the above will be migrated to soley to Advert soon for archive / reporting reasons
            $this->db->where('parentAdId', $parentAdId);
            $this->db->update('post_ads');

            $this->db->set('voteText1', $voting_text1);
            $this->db->set('voteText2', $voting_text2);

            if ($reset_votes) {
                $this->db->set('vote1', 0);
                $this->db->set('vote2', 0);
            }

            $this->db->where('adId', $adId);
            $this->db->update('Advert');
            $this->ad->adArchiveInsert($adId);
            
            if ($reset_votes) {
                $this->db->delete('userVoteToPostAd', array(
                    'postId' => $this->getPostId($parentAdId)
                ));
            }
        }
    }

    function updateAdStatus($adId, $value) {

        if ($adId != '' && $adId != 'free' && $adId != 'new' && $adId != 'manage' && $adId != 'adCreateMenu') { 
			if ($adId != '') { 	
				$this->db->set('status', $value);
				$this->db->where('adId', $adId);
				$this->db->update('Advert');
			}
		}
    }
    
    function clearModStatus($adId) {
        if ($adId != '') {

            $this->db->set('modPaused', 0)
                     ->set('status', 'De-activated')
                     ->set('statusId', 2);
            $this->db->where('adId', $adId);
            $this->db->update('Advert');
        }
    }

    function savePostAdTime($parentAdId, $post_time) {

        if ($post_time != '') {

            // @todo - sort diff timezones out!
            //$post_date = date('Y-m-d H:i', strtotime($post_time . ' + 10 HOURS'));
            $post_date = date('Y-m-d H:i', strtotime($post_time));

            $this->db->set('timeStamp', $post_date);
            $this->db->where('postId', $this->getPostId($parentAdId));
            $this->db->update('Post');

            $this->db->set('displayDate', $post_date);
            $this->db->set('startDate', $post_date);
            $this->db->set('endDate', date('Y-m-d H:i', strtotime($post_date . ' + 1 YEAR')));
            $this->db->where('parentAdId', $parentAdId);
            $this->db->update('post_ads');

            return true;
        } else {
            return false;
        }
    }

    //This is now called by cron from cronjobs.php
    function updatePostTimestamps() {


        $current_date = getdate(time());

        $post_ads = $this->getPostAds();

        foreach ($post_ads as $ad) {
            if (date("Y-m-d H:i:s") >= date("Y-m-d H:i:s", strtotime($ad['timeStamp'] . ' +24 HOURS'))) {

                $scheduled_time = getdate(strtotime($ad['timeStamp']));
                $this->setPostTimestamp($ad['postId'], date("r", mktime($scheduled_time["hours"], $scheduled_time["minutes"], 0, $current_date["mon"], $current_date["mday"], $current_date["year"])));

                $this->db->set('displayDate', date("r", mktime($scheduled_time["hours"], $scheduled_time["minutes"], 0, $current_date["mon"], $current_date["mday"], $current_date["year"])));
                $this->db->where('postId', $ad['postId']);
                $this->db->update('post_ads');
            }
        }
    }

    function setPostTimestamp($postId, $timeStamp) {

        if ($postId != '') {

            $this->db->set('timeStamp', date('Y-m-d H:i', strtotime($timeStamp)));
            $this->db->where('postId', $postId);
            $this->db->update('Post');

            $this->db->set('displayDate', date('Y-m-d H:i', strtotime($timeStamp)));
            $this->db->where('postId', $postId);
            $this->db->update('post_ads');

            if (date('Y-m-d H:i', strtotime($timeStamp)) >= $this->getPostAdEndDate($postId)) {
                $parent = $this->getPostAdParentId($postId);
                $this->deactivatePostAd($parent);
                $this->expirePostAd($parent);
            }
        }
    }

    function getPostAdTime($postId) {

        if ($postId != '') {

            $this->db->select('displayDate');
            $this->db->from('post_ads');
            $this->db->where('post_ads.postId', $postId);
            $query = $this->db->get();
            $time = $query->row();

            if ($query->num_rows() > 0) {
                return ($time->displayDate);
            } else {
                return (NULL);
            }
        }
    }

    function getPostAdEndDate($postId) {
        if ($postId != '') {

            $this->db->select('endDate');
            $this->db->from('post_ads');
            $this->db->where('post_ads.postId', $postId);
            $query = $this->db->get();
            $time = $query->row();

            if ($query->num_rows() > 0) {
                return ($time->endDate);
            } else {
                return (NULL);
            }
        }
    }

    function getPostTimestamp($postId) {


        $this->db->select('timeStamp');
        $this->db->from('Post');
        $this->db->where('postId', $postId);
        $query = $this->db->get();
        $post = $query->row();

        if ($query->num_rows() > 0) {
            return ($post->timeStamp);
        } else {
            return (NULL);
        }
    }

    function getPostId($parentAdId) {
        if ($parentAdId != '') {

            $this->db->select('postId');
            $this->db->from('post_ads');
            $this->db->where('post_ads.parentAdId', $parentAdId);
            $query = $this->db->get();
            $post = $query->row();

            if ($query->num_rows() > 0) {
                return ($post->postId);
            } else {
                return (NULL);
            }
        }
    }

    function getPostAdParentId($postId) {
        if ($postId != '') {

            $this->db->select('parentAdId');
            $this->db->from('post_ads');
            $this->db->where('post_ads.postId', $postId);
            $query = $this->db->get();
            $post = $query->row();

            if ($query->num_rows() > 0) {
                return ($post->parentAdId);
            } else {
                return (NULL);
            }
        }
    }

     function getPostAdChildId($parentAdId) {
         
        if ($parentAdId != '') {
            $this->db->select('postChildId');
            $this->db->from('Advert');
            $this->db->where('adId', $parentAdId);
            $query = $this->db->get();

            if ($query->num_rows() > 0) {
                $post = $query->row();
                return ($post->postChildId);
            } else {
                return (NULL);
            }
        }
    }
    /*
     * fetch ads to display in the forum
     *
     * @param array $data
     * @return boolean|array $ads
     *
     */

    function fetch($data) {

        $this->load->model('node');
        $this->load->model('user');

        $userId = $this->user->authCheck();

        if ((isset($data)) && ($data != "") && (isset($data ["groupId"])) && ($data ["groupId"] != "")) {

            $groupId = $data["groupId"];
			$adType = $data["type"];
            $user_info = $this->getUserDetails($userId);
            $user_info = $user_info [0];

            $user_country_id = $user_info['countryId'];
            $user_state_id = $user_info['stateId'];
            $user_postcode = $user_info['postcode'];
            $user_country_id = $user_info['countryId'];
            $gender_query = $user_info["gender"];
            $user_age = $user_info['age'];
            $userName = $user_info['username'];

            $this->db->select('Advert.adId as id, style, mainAdCategory, url, Advert.type,fileName as media, headerText as title, footerText as description,private, Advert.userId, User.userName, User.email');
            $this->db->from('Advert');
            $this->db->join('adToForum', 'Advert.adId = adToForum.adId');
			
			/* disabled for Issac */
            //$this->db->join('UserToSubject', 'UserToSubject.subjectId = adToForum.forumId');
			
			
            $this->db->join('Subject', 'Subject.subjectId = adToForum.forumId');
            
            //@todo - thinking about getting rid of 'None' values and just use null (need to check why I originally did it!)
			
			
			/* 
			 * 
			 * disabled for Issac plus few others (fishing,bodyboarding,skateboarding)
            $this->db->join("adToCountry", "Advert.adId = adToCountry.adId AND (adToCountry.country = '$user_country_id' OR adToCountry.country = 'None')");
            $this->db->join("adToState", "Advert.adId = adToState.adId AND (adToState.state = '$user_state_id' or adToState.state = 'None')");
            $this->db->join("adToPostCode", "Advert.adId = adToPostCode.adId AND (adToPostCode.postcode = '$user_postcode' or adToPostCode.postcode = 'None')");
            $this->db->join("adToAgeGroup", "Advert.adId = adToAgeGroup.adId");
            // could change this to a min and max value!
            $this->db->join("agegroup_mapping", "adToAgeGroup.agegroup = agegroup_mapping.agegroup AND (agegroup_mapping.agegroup_mapping = 'All' OR agegroup_mapping.agegroup_mapping like '%$user_age%')");
            $this->db->join("adToGender", "Advert.adId = adToGender.adId AND (substr(adToGender.gender,1,1) = '$gender_query' OR adToGender.gender = 'Both')");
			 * 
			 * 
			 */
			
			
            $this->db->join('User', 'User.userId = Advert.userId');
             $this->db->where('Advert.type', $adType)
                 ->where('Advert.booked', 1)
                 ->where('Advert.status', 'Active')
                 ->where('Advert.landingPageAd', 0)    
                 ->where('Advert.modPaused', 0)
				 ->where('Advert.size', $data["size"])
                 ->where('adToForum.forumId NOT IN (SELECT forumId FROM adsDisabled where status = 1)')
					 
				 /* 
				  * disabled for Issac
				  * 
				  
                 ->where('UserToSubject.userId', $userId)
                 ->where('UserToSubject.subjectId', $groupId)
				  * 
				  */
					 
				/* 
				 * added for Issace
				 */
				->where('Subject.subjectId', $groupId)	 
					 
				->where('Advert.modRemoved', 0);
				
				 /* 
				  * disabled for Issac
				  * 
                // ->or_where("Advert.type = '$adType' AND Advert.landingPageAd = 1 AND Advert.booked = 1 AND Advert.adId in (select adId from landingPageAds where landingPageAds.forumId = '$groupId') AND '$userId' = ''")
                // ->or_where("Advert.type = '$adType' AND Advert.landingPageAd = 1 AND Advert.booked = 1 AND Advert.adId in (select adId from landingPageAds where landingPageAds.allForums = 1) AND '$groupId' != -1 AND '$userId' = ''");
				 *
				 */
			 
            $query = $this->db->get();
            
            // if any were returned
            if ($query->num_rows() > 0) {
                
                $rawAds = $query->result_array(); // raw bunch of ads straight from the DB
                $numAds = $this->config->item('numberOfAdsToFetch'); // how many ads to fetch from server during each refresh
                $ads = array(); // stores the ads to send on to the client
                
                // if we're after ppc ads
                if ($data ["type"] === "ppc") {

                    $styles = array(
                        "skyscraper"
                    );
                    
                    if(!empty ($userId) || $this->config->item('thumb_landing_page_ads')) {
                        array_push($styles, 'thumb'); 
                    }
                    $style = $styles [mt_rand(0, count($styles) - 1)]; // mn should this be a random choice? Or a 50-50 choice?   
                } 

                
                // find the ads to fill the spots
                $i = 0;
                while (count($ads) < $numAds && $i < $numAds) { // mn do we need the limit of 100?
                    // choose a random ad for now
                    // $tmp = $this->node->info($rawAds[mt_rand(0, count($rawAds)-1)]['nodeId']);
                    $tmp = $rawAds [mt_rand(0, count($rawAds) - 1)];

                    /*
                     * 
                     * this is really shoddy but it should work for now (not written by jb!).
                     *  if ($tmp ['style'] != $style) {
                     *      $i ++;
                     *      continue;
                     *  } // Creates an endless loop when condition fulfilled? Yes until bump of $i was added
                     *
                     */
                    
                    // check if the ad has been used already
                    $match = false;
                    for ($j = 0; $j < count($ads); $j ++) {
                        if ($ads [$j] ["id"] == $tmp ["id"]) {
                            $match = true;
                            break;
                        }
                    }

                    // if the ad hasn't been used
                    if ($match === false) {

                        $ads [] = array(
                            "id" => $tmp ["id"],
                            "style" => $tmp["style"], 
                            "url" => $tmp ["url"],
                            "media" => $tmp ["media"],
                            "mainAdCategory" => $tmp ["mainAdCategory"],
                            "userId" => $tmp["userId"],
                            "userName" => $tmp["userName"],
                            "email" => $tmp["email"],
                            "mobile" => $user_info ["mobile"],
                            "private" => $tmp ["private"]
                        );

                        // if this ad needs text along with it
                        if($tmp["style"] === 'thumb') {    
                            $ads [count($ads) - 1] ["title"] = $tmp ["title"];
                            $ads [count($ads) - 1] ["description"] = $tmp ['description'];
                        }

                        // add an impression to the ad as its technically been viewed
                        // $this->ad->impression($tmp["id"]);

                        // Check if an ad should now be expired
                        // $this->checkAdExpiration($tmp ["id"]);
                    }

                    $i ++;
                }
                // if we ended up with some ads
                if (count($ads) > 0) {
                    return array(
                        "ads" => $ads
                    );
                } else {
                    //return $data; //return the panel id so it can be cleared if required
					return false; //return the panel id so it can be cleared if required
                }
            } else {

                //return $data;  //return the panel id so it can be cleared if required
				return false; //return the panel id so it can be cleared if required
            }
        } else {

            return null;
        }
    }

    /*
     * add a 'vote' to an existing Post Ad
     */
    function adVote($vote, $postId) {

        $this->load->model('user');
        $userId = $this->user->authCheck();
        
        $post_ad = $this->getPostAdDetails($postId);
        $data["alreadyVoted"] = TRUE;
        
        if (!$this->userVoted($userId, $postId)) {
            
            $data["alreadyVoted"] = FALSE;

            if (!empty($post_ad)) {
                if ($vote === 'vote1') {
                    $this->db->set('vote1', ++$post_ad->vote1);
                }

                if ($vote === 'vote2') {
                    $this->db->set('vote2', ++$post_ad->vote2);
                }

                $this->db->where('postId', $postId);
                $this->db->update('post_ads');

                //the following will be migrated solely to Advert soon for archive / reportgin reasons
                if ($vote === 'vote1') {
                    $this->db->set('vote1', $post_ad->vote1);
                }

                if ($vote === 'vote2') {
                    $this->db->set('vote2', $post_ad->vote2);
                }

                $parentId = $this->getPostAdParentId($postId);
                $this->db->where('parentAdId', $parentId);
                $this->db->where('style', 'post');
                $this->db->update('Advert');
                
                $this->db->insert('userVoteToPostAd', array('userId' => $userId, 'postId' => $postId));
            }
        }
        
        $data ["postId"] = $postId;

        // return vote text here is any;
        $data ["voteText1"] = $post_ad->voteText1;
        $data ["voteText2"] = $post_ad->voteText2;

        // return vote percentages here if any;
        $data ["vote1"] = ($post_ad->vote1 / ($post_ad->vote1 + $post_ad->vote2)) * 100;
        $data ["vote2"] = ($post_ad->vote2 / ($post_ad->vote1 + $post_ad->vote2)) * 100;

        return $data;
    }

    function userVoted($userId, $postId) {
        
        $this->db->select('userId');
        $this->db->from('userVoteToPostAd');
        $this->db->where('userId', $userId)
                ->where('postId', $postId);
        $query = $this->db->get();
        if ($query->num_rows() > 0) {
            return TRUE;
        } else {
            return FALSE;
        }
    }
        

    function getPostAdDetails($postId = '', $parentAdId = '') {


        $this->db->select('*');
        if ($parentAdId) {
            $this->db->where('post_ads.parentAdId', $parentAdId);
        } else {
            $this->db->where('post_ads.postId', $postId);
        }
        $query = $this->db->get('post_ads');

        $post_ad = $query->row();

        if ($query->num_rows() > 0) {
            return ($post_ad);
        } else {
            return (NULL);
        }
    }

    function getPostAdImage($postId = '') {
        $this->db->select('Post.postId');
        $this->db->select('Nodes.nodeId');
        $this->db->select('ContentToNodes.fileName');
        $this->db->from('Post');
        $this->db->join('Nodes', 'Post.postId = Nodes.postId');
        $this->db->join('ContentToNodes', 'Nodes.nodeId = ContentToNodes.nodeId');
        $this->db->join('Content', 'ContentToNodes.fileName = Content.fileName');
        $this->db->where('Post.postId', $postId);
        //$this->db->where('Content.fileType', 'photo');
        //$this->db->or_where('Content.fileType', 'background');
        $query = $this->db->get();

        $data = $query->row();

        if ($query->num_rows() > 0) {
            return ($data);
        } else {
            return (null);
        }
    }
    
     function getPostAdText($postId) {
        $this->db->select('Nodes.text');
        $this->db->from('Nodes');
        $this->db->where('Nodes.postId', $postId)
                ->where('Nodes.parentNodeId', '0');
        $query = $this->db->get();
        $text = $query->row();
        if ($query->num_rows() > 0) {
            return ($text);
        } else {
            return (null);
        }
    }
    
    function updatePostAdText($postId, $newText) {
        if ($postId != '') {
	  $this->load->helper(array('htmlpurifier', 'censor'));

	  $newText = censor_text(html_purify($newText, 'post'));

            $this->db->set('text', $newText);
            $this->db->where('postId', $postId)
                    ->where('parentNodeId','0');
            $this->db->update('Nodes');
        }
    }

    

    /*
     * 
     * add a 'click' to an existing ad, the if should be expired, and return the url
     * 
     */

    function click($id, $src) {

        $this->load->model('user');

        $userId = $this->user->authCheck();

        $ad = $this->getAdDetails($id, '');
        $ad = $ad[0];

        $hitColumn = ($src==='device') ? 'numHitsDevice' : 'numHits';

        $this->storeAdViews($ad['id'], $ad['media'], $userId, $hitColumn);

        //do not want to record an impression for a click - so send FALSE
        $arr = $this->recordAdImpAndCheckExp($ad["id"], $src, FALSE); 

        if (empty($ad['url'])) {
            $ad['url'] = ''; //make sure null not returned
        }
        
        if($arr["expired"]) {
            return $arr;
        } else {
            return $ad['url'];
        }
    }

    function adArchiveInsert($adId) {

        $this->db->select('*');
        $this->db->where('Advert.adId', $adId);
        $this->db->from('Advert');

        $query = $this->db->get();

        if ($query->num_rows() > 0) {
            $this->db->insert('adArchive', $query->row());
            $adArchiveId = $this->db->insert_id();

            $this->db->set('modifiedDate', date('Y-m-d H:i:s'));
            $this->db->where('adArchiveId', $adArchiveId);
            $this->db->update('adArchive');
        } else {
            return null;
        }
    }

    /* SINGLE AD - added cjt - initially mobile specific */

    function fetchAd($data) {

        if (isset($data['groupId'])) {

            $sql = "SELECT adv.adId, adv.style, adv.url, "
					. "adv.type, adv.fileName, adv.deviceFileName, "
					. "adv.headerText, adv.footerText, adv.mainAdCategory, s.private AS isPrivate "
					. "FROM Advert adv "
					. "JOIN adToForum atf ON adv.adId = atf.adId "
					. "JOIN Subject s ON atf.forumId = s.subjectId "
					. "WHERE atf.forumId = ? "
					. "AND adv.booked = TRUE "   //naughty ChrisT
					. "AND adv.filename IS NOT NULL " //ditto!
					. "ORDER BY RAND() LIMIT 1;"; 
			
            $params = array($data['groupId']);

            if ($sql = $this->db->query($sql, $params)) {

                $ad = $sql->result_array();
                return $ad;
                
            }
            return false;
        }
        return false;
    }
    
    /*
     * record an ad impression
     *
     * @param array $data
     * @return boolean
     *
     */    
    function recordImpression ($data) {

        $impressionsColumn = ($data['src']==='desktop') ? 'impressions' : 'impressionsDevice';

        $this->db->where('adId', $data['adId']);
        $this->db->set($impressionsColumn, $impressionsColumn.' + 1', false);
        $this->db->update('Advert');

        return ($this->db->affected_rows()===1) ? true : false;

    }
    
    function storeAdViews($adId,$fileName,$userName,$type) {
    	$userId = $this->user->authCheck();
    
    	$query = "UPDATE Advert SET ". $type ."= ".$type." + 1 WHERE adId = ? AND fileName = ? ";
    	 
    	$query = $this->db->query( $query,array($adId,$fileName) );
    
    	return true;
    }

    /*
     * gives credit and creates bonus ads 
    */

    function createJoinBonuses($newUserId) {
        
        $this->db->set('credit', $this->getJoinCredit()->credit);
        $this->db->where('userId', $newUserId);
        $this->db->update('User');
    
        $this->db->select('*');
        $this->db->From('adJoinBonuses');
        $this->db->where('bonusAdStatus', 1);
        
        $query = $this->db->get();
        //$bonusJoinAds = $query->result_array();
        if ($query->num_rows() > 0) {
            foreach($query->result_array() as $bonusJoinAd) {
                 $bonusJoinAds[] = array('userId' => $newUserId,
                                 'mainAdCategory' => $bonusJoinAd["mainAdCategory"],
                                 'costModel' => $bonusJoinAd["costModel"],
                                 'style' => $bonusJoinAd["style"],
                                 'type' => 'ppc',
                                 'statusId' => 17,
                                 'status' => 'Free ' . $bonusJoinAd["mainAdCategory"] . ' ' . $bonusJoinAd["style"] . ' Ad'
                          );
            }
            $this->db->insert_batch('Advert', $bonusJoinAds);
            return TRUE;
        } else {
            return NULL;
        }
        
    }

    function getJoinCredit($typeId='') {
        
        $this->db->select('SUM(credit) AS credit');
        $this->db->from('adJoinBonuses');
        if($typeId) {
            $this->db->where('adTypeId', $typeId);
        }
        $query = $this->db->get();
        $creditTotal = $query->row();

        if ($query->num_rows() > 0) {
            return $creditTotal;
        } else {
            return 0;
        }
    }
    
     // Retrieves forum groups apart from single letters
    function getForums($private) {
        $this->db->select("subjectName AS 'group'");
        $this->db->from('Subject');
        $this->db->where('LENGTH(subjectName) > 1');
        $this->db->where('subjectName !=', 'main feed');    
        $this->db->where('subjectName !=', 'my feed');
        $this->db->where('subjectName !=', 'general forums');
        $this->db->where('subjectName !=', 'top 500 general');
        $this->db->where('subjectName !=', 'top 500 schools');
        $this->db->where('subjectName !=', 'top 500 club18');
        if($private === 0) {
            $this->db->where('createdBy', $private);
        }
        if($private !== 2) {
            $this->db->where('private', $private);
        }
        $this->db->order_by("subjectName");
            
        $query = $this->db->get();        

        if ($query->num_rows() > 0) {
            $groups = $query->result_array();
            return $groups;
        } else {
            return false;
        }
    }
    
    
}

    /*
     * Example of a PDO function but now using Codeignitor Query Builder Class
     *
      function getAdTypes() {

	  try {

	  $conn = new PDO('mysql:host=127.0.0.1;dbname=zenure1;charset=utf8', 'xxxx', 'xxxx');
	  // set the PDO error mode to exception


	  $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	  $stmt = $conn->prepare("SELECT * FROM ad_types where ad_status = 'active'");
	  $stmt->execute();

	  // set the resulting array to associative
	  // $result = $stmt->setFetchMode(PDO::FETCH_ASSOC);
	  $result = $stmt->fetchall();

	  return($result);
	  } catch (PDOException $e) {
	  echo "Error: " . $e->getMessage();
	  }
	  $conn = null;
	  }
	 * 
	 */


	/*
	 * More PDO examples - not used
	 * 
	 * 
	  function getAdStyles($mainAdCategory, $adselected) {

	  try {

	  $conn = new PDO('mysql:host=127.0.0.1;dbname=zenure1;charset=utf8', 'xxxx', 'xxxx');
	  // set the PDO error mode to exception

	  $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	  //$stmt = $conn->prepare("SELECT name, description, forum_symbol FROM ad_types");

	  if ($adselected != '') {
	  $stmt = $conn->prepare("SELECT * FROM ad_styles where mainAdCategory = :mainAdCategory and controller_param = :adselected and ad_status = 'active'");
	  $stmt->bindParam(':adselected', $adselected, PDO::PARAM_STR);
	  } else {
	  $stmt = $conn->prepare("SELECT * FROM ad_styles where mainAdCategory = :mainAdCategory and ad_status = 'active'");
	  }

	  $stmt->bindParam(':mainAdCategory', $mainAdCategory, PDO::PARAM_STR);
	  $stmt->execute();

	  // set the resulting array to associative
	  // $result = $stmt->setFetchMode(PDO::FETCH_ASSOC);
	  $result = $stmt->fetchall();

	  return($result);
	  } catch (PDOException $e) {
	  echo "Error: " . $e->getMessage();
	  }
	  $conn = null;
	  }

	  function getAdRowTitles($adScreen) {

	  try {

	  $conn = new PDO('mysql:host=127.0.0.1;dbname=zenure1;charset=utf8', 'xxxx', 'xxxx');
	  // set the PDO error mode to exception


	  $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	  //$stmt = $conn->prepare("SELECT name, description, forum_symbol FROM ad_types");
	  $stmt = $conn->prepare("SELECT * FROM ad_row_titles where adScreen = :adScreen");
	  $stmt->bindParam(':adScreen', $adScreen, PDO::PARAM_STR);
	  $stmt->execute();

	  // set the resulting array to associative
	  // $result = $stmt->setFetchMode(PDO::FETCH_ASSOC);
	  $result = $stmt->fetchall();

	  return($result);
	  } catch (PDOException $e) {
	  echo "Error: " . $e->getMessage();
	  }
	  $conn = null;
	  }

	 *
	 */

