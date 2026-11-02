<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class Adadminmodel extends CI_Model {

    function getAllStats($what, $groupBy) {

        if ($what === 'revenue') {
            $this->db->select('SUM(dummytotalPrice) AS total');
        } else if ($what === 'numbers') {
            $this->db->select('count(Advert.adId) AS total');
        }

        $this->db->select($groupBy . ' AS groupedBy');
        $this->db->from('Advert');
        $this->db->where('Advert.style !=', 'NULL');
        $this->db->where('Advert.style !=', '');
        $this->db->where('Advert.mainAdCategory !=', 'NULL');
        $this->db->where('Advert.mainAdCategory !=', '');
        $this->db->where('Advert.costModel !=', 'NULL');
        $this->db->where('Advert.costModel !=', '');
        $this->db->group_by($groupBy);

        $query = $this->db->get();
        $totals = $query->result_array();

        if ($query->num_rows() > 0) {
            return ($totals);
        } else {
            return (NULL);
        }
    }

    function getStatsByYearPeriod($year, $what, $month = '', $groupedBy = '', $group = '', $date1 = '', $date2 = '', $statusId = 1, $freeAds = 0) {

        if ($what === 'revenue') {
            $this->db->select('SUM(dummytotalPrice) AS monthTotal');
        } else if ($what === 'numbers') {
            $this->db->select('count(Advert.adId) AS monthTotal');
        }

        $this->db->select('MONTHNAME(dummystartDate) as month');
        $this->db->from('Advert');
        //if ($allTime) {
        $this->db->where('YEAR(dummystartDate)', $year);
        //}
        $this->db->where('Advert.style !=', 'NULL');
        $this->db->where('Advert.style !=', '');
        $this->db->where('Advert.mainAdCategory !=', 'NULL');
        $this->db->where('Advert.mainAdCategory !=', '');
        $this->db->where('Advert.costModel !=', 'NULL');
        $this->db->where('Advert.costModel !=', '');
        $this->db->where('Advert.freeAd', $freeAds);
        $this->db->where('Advert.statusId', $statusId);
        if (!empty($groupedBy) && !empty($group)) {
            $this->db->where('Advert.' . $groupedBy, $group);
        }

        $this->db->group_by('month');
        //$this->db->group_by('costModel');
        $this->db->order_by('month');


        $query = $this->db->get();
        $monthlyTotals = $query->result_array();

        if ($query->num_rows() > 0) {
            //var_dump($this->db->last_query());
            return ($monthlyTotals);
        } else {
            return (NULL);
        }
    }

    function testit($date1, $date2) {

        $this->db->select('dummystartDate as dummy1');
        //   $this->db->select("$date1 AS DATE1");
        //$sel = "STR_TO_DATE(dummystartDate, '%d-%m-%Y') as dummy2";
        //$this->db->select("DATE_FORMAT(dummystartDate, '%d-%m-%Y') as dummy2");
        //$this->db->select($sel);

        $this->db->from('Advert');
        $this->db->where('dummystartDate !=', '');
        $this->db->where('dummystartDate !=', 'NULL');
        $this->db->limit(1);
        $query = $this->db->get();
        $data = $query->result_array();
        return $data;
    }

    function getStatsByDateRangePeriod($year, $what, $month = '', $groupedBy = '', $group = '', $date1 = '', $date2 = '', $statusId = 1, $freeAds = 0) {

        //$this->db->select('MONTH(startDate) as monthId');

        if ($what === 'revenue') {
            $this->db->select('SUM(dummytotalPrice) AS monthTotal');
        } else if ($what === 'numbers') {
            $this->db->select('count(Advert.adId) AS monthTotal');
        }

        $fromDate = new DateTime($date1);
        $toDate = new DateTime($date2);

        /* @todo span years
          if date1 and dat2 and they span years etc
          $this->db->select("CONCAT(MONTHNAME(dummystartDate), YEAR(dummystartDate)) AS 'month'", FALSE);
         * 
         */

        $this->db->select('MONTHNAME(dummystartDate) as month');
        $this->db->from('Advert');
        $this->db->where("dummystartDate !=", '');
        $this->db->where("dummystartDate !=", 'NULL');
        $this->db->where("dummystartDate >=", $fromDate->format('Y-m-d H:i:s'));
        $this->db->where("dummystartDate <=", $toDate->format('Y-m-d H:i:s'));
        $this->db->where('Advert.style !=', 'NULL');
        $this->db->where('Advert.style !=', '');
        $this->db->where('Advert.mainAdCategory !=', 'NULL');
        $this->db->where('Advert.mainAdCategory !=', '');
        $this->db->where('Advert.costModel !=', 'NULL');
        $this->db->where('Advert.costModel !=', '');
        $this->db->where('Advert.freeAd', $freeAds);
        $this->db->where('Advert.statusId', $statusId);
        if (!empty($groupedBy) && !empty($group)) {
            $this->db->where('Advert.' . $groupedBy, $group);
        }

        $this->db->group_by('month');
        $this->db->order_by('MONTH(dummystartDate)');

        $query = $this->db->get();
        //var_dump($this->db->last_query());
        $monthlyTotals = $query->result_array();

        if ($query->num_rows() > 0) {
            return ($monthlyTotals);
        } else {
            return (NULL);
        }
    }

    function getStatsByMonthPeriod($year, $what, $month, $groupedBy = '', $group = '', $date1 = '', $date2 = '', $statusId = 1, $freeAds = 0) {

        //$this->db->select('MONTH(startDate) as monthId');

        if ($what === 'revenue') {
            $this->db->select('SUM(dummytotalPrice) AS monthTotal');
        } else if ($what === 'numbers') {
            $this->db->select('count(Advert.adId) AS monthTotal');
        }


        $this->db->select("DATE(dummystartDate) as month");
        $this->db->from('Advert');
        $this->db->where('MONTHNAME(dummystartDate)', $month);
        $this->db->where('YEAR(dummystartDate)', $year);
        $this->db->where('Advert.style !=', 'NULL');
        $this->db->where('Advert.style !=', '');
        $this->db->where('Advert.mainAdCategory !=', 'NULL');
        $this->db->where('Advert.mainAdCategory !=', '');
        $this->db->where('Advert.costModel !=', 'NULL');
        $this->db->where('Advert.costModel !=', '');
        $this->db->where('Advert.freeAd', $freeAds);
        $this->db->where('Advert.statusId', $statusId);
        if (!empty($groupedBy) && !empty($group)) {
            $this->db->where('Advert.' . $groupedBy, $group);
        }

        $this->db->group_by('month');
        $this->db->order_by('month');


        $query = $this->db->get();
        $dayTotals = $query->result_array();

        if ($query->num_rows() > 0) {
            return ($dayTotals);
        } else {
            return (NULL);
        }
    }

    //not going to offer range by day yet
    function _getStatsByDateRangeMonthPeriod($year, $what, $month, $groupedBy = '', $group = '', $date1, $date2, $statusId = 1, $freeAds = 0) {

        if ($what === 'revenue') {
            $this->db->select('SUM(dummytotalPrice) AS monthTotal');
        } else if ($what === 'numbers') {
            $this->db->select('count(Advert.adId) AS monthTotal');
        }

        $this->db->select("DATE(dummystartDate) as month");
        $this->db->from('Advert');
        $this->db->where('Advert.style !=', 'NULL');
        $this->db->where('Advert.style !=', '');
        $this->db->where('Advert.mainAdCategory !=', 'NULL');
        $this->db->where('Advert.mainAdCategory !=', '');
        $this->db->where('Advert.costModel !=', 'NULL');
        $this->db->where('Advert.costModel !=', '');
        $this->db->where("dummystartDate !=", '');
        $this->db->where("dummystartDate !=", 'NULL');
        $this->db->where("dummystartDate >=", $fromDate->format('Y-m-d H:i:s'));
        $this->db->where("dummystartDate <=", $toDate->format('Y-m-d H:i:s'));
        $this->db->where('Advert.freeAd', $freeAds);
        $this->db->where('Advert.statusId', $statusId);
        if (!empty($groupedBy) && !empty($group)) {
            $this->db->where('Advert.' . $groupedBy, $group);
        }

        $this->db->group_by('month');
        $this->db->order_by('month');


        $query = $this->db->get();
        $dayTotals = $query->result_array();

        if ($query->num_rows() > 0) {
            return ($dayTotals);
        } else {
            return (NULL);
        }
    }

    function getStatsByYearForum($year, $limit, $what, $month = '', $groupedBy = '', $group = '', $date1 = '', $date2 = '', $statusId = 1, $freeAds = 0) {

        if ($what === 'revenue') {
            $this->db->select('SUM(dummytotalPrice) AS total');
        } else if ($what === 'numbers') {
            $this->db->select('count(Advert.adId) AS total');
        }

        $this->db->select('subjectName as forumName');
        $this->db->from('adToForum');
        $this->db->join('Advert', 'Advert.adId = adToForum.adId');
        $this->db->join('Subject', 'Subject.subjectId = adToForum.forumId');
        $this->db->where('YEAR(dummystartDate)', $year);
        $this->db->where('Advert.style !=', 'NULL');
        $this->db->where('Advert.style !=', '');
        $this->db->where('Advert.mainAdCategory !=', 'NULL');
        $this->db->where('Advert.mainAdCategory !=', '');
        $this->db->where('Advert.costModel !=', 'NULL');
        $this->db->where('Advert.costModel !=', '');
        $this->db->where('Advert.freeAd', $freeAds);
        $this->db->where('Advert.statusId', $statusId);
        if (!empty($groupedBy) && !empty($group)) {
            $this->db->where('Advert.' . $groupedBy, $group);
        }
        if (!empty($style)) {
            $this->db->where('Advert.style', $style);
        }
        if ($limit) {
            $this->db->limit($limit);
        }
        $this->db->group_by('subjectName');
        $this->db->order_by('total', 'desc');

        $query = $this->db->get();
        $forumTotals = $query->result_array();

        if ($query->num_rows() > 0) {
            return ($forumTotals);
        } else {
            return null;
        }
    }

    function getStatsByDateRangeForum($year, $limit, $what, $month = '', $groupedBy = '', $group = '', $date1 = '', $date2 = '', $statusId = 1, $freeAds = 0) {

        if ($what === 'revenue') {
            $this->db->select('SUM(dummytotalPrice) AS total');
        } else if ($what === 'numbers') {
            $this->db->select('count(Advert.adId) AS total');
        }

        $fromDate = new DateTime($date1);
        $toDate = new DateTime($date2);

        $this->db->select('subjectName as forumName');
        $this->db->from('adToForum');
        $this->db->join('Advert', 'Advert.adId = adToForum.adId');
        $this->db->join('Subject', 'Subject.subjectId = adToForum.forumId');
        //$this->db->where('YEAR(dummystartDate)', $year);
        $this->db->where('Advert.style !=', 'NULL');
        $this->db->where('Advert.style !=', '');
        $this->db->where('Advert.mainAdCategory !=', 'NULL');
        $this->db->where('Advert.mainAdCategory !=', '');
        $this->db->where('Advert.costModel !=', 'NULL');
        $this->db->where('Advert.costModel !=', '');
        $this->db->where("dummystartDate !=", '');
        $this->db->where("dummystartDate !=", 'NULL');
        $this->db->where("dummystartDate >=", $fromDate->format('Y-m-d H:i:s'));
        $this->db->where("dummystartDate <=", $toDate->format('Y-m-d H:i:s'));
        $this->db->where('Advert.freeAd', $freeAds);
        $this->db->where('Advert.statusId', $statusId);
        if (!empty($groupedBy) && !empty($group)) {
            $this->db->where('Advert.' . $groupedBy, $group);
        }
        if (!empty($style)) {
            $this->db->where('Advert.style', $style);
        }
        if ($limit) {
            $this->db->limit($limit);
        }
        $this->db->group_by('subjectName');
        $this->db->order_by('total', 'desc');

        $query = $this->db->get();
        $forumTotals = $query->result_array();

        if ($query->num_rows() > 0) {
            return ($forumTotals);
        } else {
            return null;
        }
    }

    function getStatsByMonthForum($year, $limit, $what, $month, $groupedBy = '', $group = '', $date1 = '', $date2 = '', $statusId = 1, $freeAds = 0) {

        if ($what === 'revenue') {
            $this->db->select('SUM(dummytotalPrice) AS total');
        } else if ($what === 'numbers') {
            $this->db->select('count(Advert.adId) AS total');
        }

        $this->db->select('subjectName as forumName');
        $this->db->from('adToForum');
        $this->db->join('Advert', 'Advert.adId = adToForum.adId');
        $this->db->join('Subject', 'Subject.subjectId = adToForum.forumId');
        $this->db->where('MONTHNAME(dummystartDate)', $month);
        $this->db->where('YEAR(dummystartDate)', $year);
        $this->db->where('Advert.style !=', 'NULL');
        $this->db->where('Advert.style !=', '');
        $this->db->where('Advert.mainAdCategory !=', 'NULL');
        $this->db->where('Advert.mainAdCategory !=', '');
        $this->db->where('Advert.costModel !=', 'NULL');
        $this->db->where('Advert.costModel !=', '');
        $this->db->where('Advert.freeAd', $freeAds);
        $this->db->where('Advert.statusId', $statusId);
        if (!empty($groupedBy) && !empty($group)) {
            $this->db->where('Advert.' . $groupedBy, $group);
        }
        if ($limit) {
            $this->db->limit($limit);
        }
        $this->db->group_by('subjectName');
        $this->db->order_by('total', 'desc');

        $query = $this->db->get();
        $forumTotals = $query->result_array();

        if ($query->num_rows() > 0) {
            return ($forumTotals);
        } else {
            return null;
        }
    }

    function getStatsByYearRegion($year, $what, $limit = '', $month = '', $region, $groupedBy = '', $group = '', $date1 = '', $date2 = '', $statusId = 1, $freeAds = 0) {


        $regionTable = ucfirst($region);
        if ($region === 'postcode') {
            $regionTable = 'PostCode';
        }

        if ($what === 'revenue') {
            $this->db->select('SUM(dummytotalPrice) AS total');
        } else if ($what === 'numbers') {
            $this->db->select('COUNT(dummytotalPrice) AS total');
        }

        $this->db->from('Advert');
        $this->db->where('YEAR(dummystartDate)', $year);
        $this->db->where('Advert.style !=', 'NULL');
        $this->db->where('Advert.style !=', '');
        $this->db->where('Advert.mainAdCategory !=', 'NULL');
        $this->db->where('Advert.mainAdCategory !=', '');
        $this->db->where('Advert.costModel !=', 'NULL');
        $this->db->where('Advert.costModel !=', '');
        $this->db->where('Advert.freeAd', $freeAds);
        $this->db->where('Advert.statusId', $statusId);
        if (!empty($groupedBy) && !empty($group)) {
            $this->db->where('Advert.' . $groupedBy, $group);
        }

        $this->db->join('adTo' . $regionTable, 'Advert.adId = adTo' . $regionTable . '.adId');
        if ($region === 'international') {
            $this->db->select('adTo' . $regionTable . '.' . $region);
        } else if ($region === 'postcode') {
            $this->db->select("CONCAT(postCode.postCode, '  ', postCode.locality) AS 'postcode'", FALSE);
            $this->db->join('postCode', 'postCode.postCodeId = adToPostCode.postcode');
        } else {
            $this->db->select($regionTable . '.name AS ' . $region);
            $this->db->join($regionTable, $regionTable . '.' . $region . 'Id = adTo' . $regionTable . '.' . $region);
        }
        if ($limit) {
            $this->db->limit($limit);
        }
        $this->db->group_by($region);
        $this->db->order_by('total', 'DESC');

        $query = $this->db->get();
        $returnRegion = $query->result_array();

        if ($query->num_rows() > 0) {
            //var_dump($this->db->last_query());
            return ($returnRegion);
        } else {
            return (NULL);
        }
    }

    function getStatsByDateRangeRegion($year, $what, $limit = '', $month = '', $region, $groupedBy = '', $group = '', $date1, $date2 = '', $statusId = 1, $freeAds = 0) {

        $regionTable = ucfirst($region);
        if ($region === 'postcode') {
            $regionTable = 'PostCode';
        }

        if ($what === 'revenue') {
            $this->db->select('SUM(dummytotalPrice) AS total');
        } else if ($what === 'numbers') {
            $this->db->select('COUNT(dummytotalPrice) AS total');
        }

        $fromDate = new DateTime($date1);
        $toDate = new DateTime($date2);

        $this->db->from('Advert');
        $this->db->where('YEAR(dummystartDate)', $year);
        $this->db->where('Advert.style !=', 'NULL');
        $this->db->where('Advert.style !=', '');
        $this->db->where('Advert.mainAdCategory !=', 'NULL');
        $this->db->where('Advert.mainAdCategory !=', '');
        $this->db->where('Advert.costModel !=', 'NULL');
        $this->db->where('Advert.costModel !=', '');
        $this->db->where("dummystartDate !=", '');
        $this->db->where("dummystartDate !=", 'NULL');
        $this->db->where("dummystartDate >=", $fromDate->format('Y-m-d H:i:s'));
        $this->db->where("dummystartDate <=", $toDate->format('Y-m-d H:i:s'));
        $this->db->where('Advert.freeAd', $freeAds);
        $this->db->where('Advert.statusId', $statusId);
        if (!empty($groupedBy) && !empty($group)) {
            $this->db->where('Advert.' . $groupedBy, $group);
        }

        $this->db->join('adTo' . $regionTable, 'Advert.adId = adTo' . $regionTable . '.adId');
        if ($region === 'international') {
            $this->db->select('adTo' . $regionTable . '.' . $region);
        } else if ($region === 'postcode') {
            $this->db->select("CONCAT(postCode.postCode, '  ', postCode.locality) AS 'postcode'", FALSE);
            $this->db->join('postCode', 'postCode.postCodeId = adToPostCode.postcode');
        } else {
            $this->db->select($regionTable . '.name AS ' . $region);
            $this->db->join($regionTable, $regionTable . '.' . $region . 'Id = adTo' . $regionTable . '.' . $region);
        }
        if ($limit) {
            $this->db->limit($limit);
        }
        $this->db->group_by($region);
        $this->db->order_by('total', 'DESC');

        $query = $this->db->get();
        $returnRegion = $query->result_array();

        if ($query->num_rows() > 0) {
            return ($returnRegion);
        } else {
            return (NULL);
        }
    }

    function getStatsByMonthRegion($year, $what, $limit = '', $month, $region, $groupedBy = '', $group = '', $date1 = '', $date2 = '', $statusId = 1, $freeAds = 0) {

        $regionTable = ucfirst($region);
        if ($region === 'postcode') {
            $regionTable = 'PostCode';
        }

        if ($what === 'revenue') {
            $this->db->select('SUM(dummytotalPrice) AS total');
        } else if ($what === 'numbers') {
            $this->db->select('COUNT(dummytotalPrice) AS total');
        }

        $this->db->from('Advert');
        $this->db->where('MONTHNAME(dummystartDate)', $month);
        $this->db->where('YEAR(dummystartDate)', $year);
        $this->db->where('Advert.style !=', 'NULL');
        $this->db->where('Advert.style !=', '');
        $this->db->where('Advert.mainAdCategory !=', 'NULL');
        $this->db->where('Advert.mainAdCategory !=', '');
        $this->db->where('Advert.costModel !=', 'NULL');
        $this->db->where('Advert.costModel !=', '');
        $this->db->where('Advert.freeAd', $freeAds);
        $this->db->where('Advert.statusId', $statusId);
        if (!empty($groupedBy) && !empty($group)) {
            $this->db->where('Advert.' . $groupedBy, $group);
        }

        $this->db->join('adTo' . $regionTable, 'Advert.adId = adTo' . $regionTable . '.adId');
        if ($region === 'international') {
            $this->db->select('adTo' . $regionTable . '.' . $region);
        } else if ($region === 'postcode') {
            $this->db->select("CONCAT(postCode.postCode, '  ', postCode.locality) AS 'postcode'", FALSE);
            $this->db->join('postCode', 'postCode.postCodeId = adToPostCode.postcode');
        } else {
            $this->db->select($regionTable . '.name AS ' . $region);
            $this->db->join($regionTable, $regionTable . '.' . $region . 'Id = adTo' . $regionTable . '.' . $region);
        }
        if ($limit) {
            $this->db->limit($limit);
        }
        $this->db->group_by($region);
        $this->db->order_by('total', 'DESC');

        $query = $this->db->get();
        $returnRegion = $query->result_array();

        if ($query->num_rows() > 0) {
            return ($returnRegion);
        } else {
            return (NULL);
        }
    }

    function getStatsByYearAgeGroup($year, $what, $month = '', $groupedBy = '', $group = '', $date1 = '', $date2 = '', $statusId = 1, $freeAds = 0) {

        if ($what === 'revenue') {
            $this->db->select('SUM(dummytotalPrice) AS total');
        } else if ($what === 'numbers') {
            $this->db->select('count(Advert.adId) AS total');
        }

        $this->db->select('agegroup_mapping.agegroup');
        $this->db->from('Advert');
        $this->db->where('YEAR(dummystartDate)', $year);
        $this->db->where('Advert.style !=', 'NULL');
        $this->db->where('Advert.style !=', '');
        $this->db->where('Advert.mainAdCategory !=', 'NULL');
        $this->db->where('Advert.mainAdCategory !=', '');
        $this->db->where('Advert.costModel !=', 'NULL');
        $this->db->where('Advert.costModel !=', '');
        $this->db->where('Advert.freeAd', $freeAds);
        $this->db->where('Advert.statusId', $statusId);
        if (!empty($groupedBy) && !empty($group)) {
            $this->db->where('Advert.' . $groupedBy, $group);
        }

        $this->db->join('adToAgeGroup', 'Advert.adId = adToAgeGroup.adId');
        $this->db->join('agegroup_mapping', 'agegroup_mapping.agegroup = adToAgeGroup.agegroup');
        $this->db->group_by('agegroup');
        $this->db->order_by('total', 'desc');

        $query = $this->db->get();
        $ageGroupTotals = $query->result_array();

        if ($query->num_rows() > 0) {
            return ($ageGroupTotals);
        } else {
            return (NULL);
        }
    }

    function getStatsByDateRangeAgeGroup($year, $what, $month = '', $groupedBy = '', $group = '', $date1 = '', $date2 = '', $statusId = 1, $freeAds = 0) {

        if ($what === 'revenue') {
            $this->db->select('SUM(dummytotalPrice) AS total');
        } else if ($what === 'numbers') {
            $this->db->select('count(Advert.adId) AS total');
        }

        $fromDate = new DateTime($date1);
        $toDate = new DateTime($date2);

        $this->db->select('agegroup_mapping.agegroup');
        $this->db->from('Advert');
        $this->db->where('Advert.style !=', 'NULL');
        $this->db->where('Advert.style !=', '');
        $this->db->where('Advert.mainAdCategory !=', 'NULL');
        $this->db->where('Advert.mainAdCategory !=', '');
        $this->db->where('Advert.costModel !=', 'NULL');
        $this->db->where('Advert.costModel !=', '');
        $this->db->where("dummystartDate !=", '');
        $this->db->where("dummystartDate !=", 'NULL');
        $this->db->where("dummystartDate >=", $fromDate->format('Y-m-d H:i:s'));
        $this->db->where("dummystartDate <=", $toDate->format('Y-m-d H:i:s'));
        $this->db->where('Advert.freeAd', $freeAds);
        $this->db->where('Advert.statusId', $statusId);
        if (!empty($groupedBy) && !empty($group)) {
            $this->db->where('Advert.' . $groupedBy, $group);
        }

        $this->db->join('adToAgeGroup', 'Advert.adId = adToAgeGroup.adId');
        $this->db->join('agegroup_mapping', 'agegroup_mapping.agegroup = adToAgeGroup.agegroup');
        $this->db->group_by('agegroup');
        $this->db->order_by('total', 'desc');

        $query = $this->db->get();
        $ageGroupTotals = $query->result_array();

        if ($query->num_rows() > 0) {
            return ($ageGroupTotals);
        } else {
            return (NULL);
        }
    }

    function getStatsByMonthAgeGroup($year, $what, $month, $groupedBy = '', $group = '', $date1 = '', $date2 = '', $statusId = 1, $freeAds = 0) {

        if ($what === 'revenue') {
            $this->db->select('SUM(dummytotalPrice) AS total');
        } else if ($what === 'numbers') {
            $this->db->select('count(Advert.adId) AS total');
        }

        $this->db->select('agegroup_mapping.agegroup');
        $this->db->from('Advert');
        $this->db->where('MONTHNAME(dummystartDate)', $month);
        $this->db->where('YEAR(dummystartDate)', $year);
        $this->db->where('Advert.style !=', 'NULL');
        $this->db->where('Advert.style !=', '');
        $this->db->where('Advert.mainAdCategory !=', 'NULL');
        $this->db->where('Advert.mainAdCategory !=', '');
        $this->db->where('Advert.costModel !=', 'NULL');
        $this->db->where('Advert.costModel !=', '');
        $this->db->where('Advert.freeAd', $freeAds);
        $this->db->where('Advert.statusId', $statusId);
        if (!empty($groupedBy) && !empty($group)) {
            $this->db->where('Advert.' . $groupedBy, $group);
        }

        $this->db->join('adToAgeGroup', 'Advert.adId = adToAgeGroup.adId');
        $this->db->join('agegroup_mapping', 'agegroup_mapping.agegroup = adToAgeGroup.agegroup');
        $this->db->group_by('agegroup');
        $this->db->order_by('total', 'desc');

        $query = $this->db->get();
        $ageGroupTotals = $query->result_array();

        if ($query->num_rows() > 0) {
            return ($ageGroupTotals);
        } else {
            return (NULL);
        }
    }

    function getStatsByYearGender($year, $what, $month = '', $groupedBy = '', $group = '', $date1 = '', $date2 = '', $statusId = 1, $freeAds = 0) {

        if ($what === 'revenue') {
            $this->db->select('SUM(dummytotalPrice) AS total');
        } else if ($what === 'numbers') {
            $this->db->select('count(Advert.adId) AS total');
        }

        $this->db->select('gender');
        $this->db->from('Advert');
        $this->db->where('YEAR(dummystartDate)', $year);
        $this->db->where('Advert.style !=', 'NULL');
        $this->db->where('Advert.style !=', '');
        $this->db->where('Advert.mainAdCategory !=', 'NULL');
        $this->db->where('Advert.mainAdCategory !=', '');
        $this->db->where('Advert.costModel !=', 'NULL');
        $this->db->where('Advert.costModel !=', '');
        $this->db->where('Advert.statusId', $statusId);
        $this->db->where('Advert.freeAd', $freeAds);
        if (!empty($groupedBy) && !empty($group)) {
            $this->db->where('Advert.' . $groupedBy, $group);
        }

        $this->db->join('adToGender', 'Advert.adId = adToGender.adId');
        $this->db->group_by('gender');
        $this->db->order_by('total', 'desc');

        $query = $this->db->get();
        $genderTotals = $query->result_array();

        if ($query->num_rows() > 0) {
            return ($genderTotals);
        } else {
            return (NULL);
        }
    }

    function getStatsByDateRangeGender($year, $what, $month = '', $groupedBy = '', $group = '', $date1 = '', $date2 = '', $statusId = 1, $freeAds = 0) {

        if ($what === 'revenue') {
            $this->db->select('SUM(dummytotalPrice) AS total');
        } else if ($what === 'numbers') {
            $this->db->select('count(Advert.adId) AS total');
        }

        $fromDate = new DateTime($date1);
        $toDate = new DateTime($date2);

        $this->db->select('gender');
        $this->db->from('Advert');
        $this->db->where('Advert.style !=', 'NULL');
        $this->db->where('Advert.style !=', '');
        $this->db->where('Advert.mainAdCategory !=', 'NULL');
        $this->db->where('Advert.mainAdCategory !=', '');
        $this->db->where('Advert.costModel !=', 'NULL');
        $this->db->where('Advert.costModel !=', '');
        $this->db->where("dummystartDate !=", '');
        $this->db->where("dummystartDate !=", 'NULL');
        $this->db->where("dummystartDate >=", $fromDate->format('Y-m-d H:i:s'));
        $this->db->where("dummystartDate <=", $toDate->format('Y-m-d H:i:s'));
        $this->db->where('Advert.freeAd', $freeAds);
        $this->db->where('Advert.statusId', $statusId);
        if (!empty($groupedBy) && !empty($group)) {
            $this->db->where('Advert.' . $groupedBy, $group);
        }

        $this->db->join('adToGender', 'Advert.adId = adToGender.adId');
        $this->db->group_by('gender');
        $this->db->order_by('total', 'desc');

        $query = $this->db->get();
        $genderTotals = $query->result_array();

        if ($query->num_rows() > 0) {
            return ($genderTotals);
        } else {
            return (NULL);
        }
    }

    function getStatsByMonthGender($year, $what, $month, $groupedBy = '', $group = '', $date1 = '', $date2 = '', $statusId = 1, $freeAds = 0) {

        if ($what === 'revenue') {
            $this->db->select('SUM(dummytotalPrice) AS total');
        } else if ($what === 'numbers') {
            $this->db->select('count(Advert.adId) AS total');
        }

        $this->db->select('gender');
        $this->db->from('Advert');
        $this->db->where('YEAR(dummystartDate)', $year);
        $this->db->where('MONTHNAME(dummystartDate)', $month);
        $this->db->where('Advert.style !=', 'NULL');
        $this->db->where('Advert.style !=', '');
        $this->db->where('Advert.mainAdCategory !=', 'NULL');
        $this->db->where('Advert.mainAdCategory !=', '');
        $this->db->where('Advert.costModel !=', 'NULL');
        $this->db->where('Advert.costModel !=', '');
        $this->db->where('Advert.freeAd', $freeAds);
        $this->db->where('Advert.statusId', $statusId);
        if (!empty($groupedBy) && !empty($group)) {
            $this->db->where('Advert.' . $groupedBy, $group);
        }

        $this->db->join('adToGender', 'Advert.adId = adToGender.adId');
        $this->db->group_by('gender');
        $this->db->order_by('total', 'desc');

        $query = $this->db->get();
        $genderTotals = $query->result_array();

        if ($query->num_rows() > 0) {
            return ($genderTotals);
        } else {
            return (NULL);
        }
    }

    function getAdYears() {

        $this->db->select('YEAR(dummystartDate) AS year');
        $this->db->distinct();
        $this->db->from('Advert');
        $this->db->where('dummystartDate !=', 'NULL');
        $this->db->order_by('year', 'DESC');

        $query = $this->db->get();
        $years = $query->result_array();

        if ($query->num_rows() > 0) {
            return ($years);
        } else {
            return (NULL);
        }
    }

    /*
     * Returns all ad styles types
     *
     * @return array|boolean
     *
     */

    function getAllAdStyles() {

        $this->db->select('style');
        $this->db->distinct();
        $this->db->from('Advert');
        $this->db->where('style !=', 'NULL');
        $this->db->where('style !=', '');

        $query = $this->db->get();
        if ($query->num_rows() > 0) {
            $styles = $query->result_array();
            return ($styles);
        } else {
            log_message('error', 'ad->getAllAdStyles(): No ad styles found.');
            return (NULL);
        }
    }

    function getAllCostModels() {

        $this->db->select('costModel');
        $this->db->distinct();
        $this->db->from('Advert');
        $this->db->where('costModel !=', 'NULL');
        $this->db->where('costModel !=', '');
        $this->db->where('costModel !=', 'FREE');

        $query = $this->db->get();
        if ($query->num_rows() > 0) {
            $costModels = $query->result_array();
            return ($costModels);
        } else {
            log_message('error', 'ad->getAllCostModels(): No ad cost models found.');
            return (NULL);
        }
    }

    function getAllAdCategories() {

        $this->db->select('mainAdCategory');
        $this->db->distinct();
        $this->db->from('Advert');
        $this->db->where('mainAdCategory !=', 'NULL');
        $this->db->where('mainAdCategory !=', '');

        $query = $this->db->get();
        if ($query->num_rows() > 0) {
            $cats = $query->result_array();
            return ($cats);
        } else {
            log_message('error', 'ad->getAllAdCategories(): No ad categories found.');
            return (NULL);
        }
    }

    function getAllAdAgeGroups() {

        $this->db->select('agegroup');
        $this->db->from('agegroup_mapping');

        $query = $this->db->get();
        if ($query->num_rows() > 0) {
            $ages = $query->result_array();
            return ($ages);
        } else {
            log_message('error', 'ad->getAllAgeGroups(): No ad agegroups found.');
            return (NULL);
        }
    }

    function getForumId($name) {

        $this->db->select('subjectId');
        $this->db->from('Subject');
        $this->db->where('subjectName', $name);
        $query = $this->db->get();
        $states = $query->row();

        if ($query->num_rows() > 0) {
            return ($states);
        } else {
            return (NULL);
        }
    }

    // Retrieves all the forum groups
    function getForums($createUser) {

        $this->db->select('subjectId AS forumId')
                ->select('subjectName AS forumName');
        $this->db->from('Subject');
        $this->db->where('createdBy', $createUser)
                ->where('Subject.subjectId NOT IN (SELECT forumId FROM adsDisabled where status = 1)');
        $query = $this->db->get();

        if ($query->num_rows() > 0) {
            $forums = $query->result_array();
            return $forums;
        } else {
            return false;
        }
    }

    function getForumName($id) {

        $this->db->select('subjectName AS forumName');
        $this->db->from('Subject');
        $this->db->where('subjectId', $id);
        $query = $this->db->get();

        if ($query->num_rows() > 0) {
            $forumName = $query->row();
            return $forumName;
        } else {
            return false;
        }
    }

    function updateAdsDisabled($user, $forums) {

        foreach ($forums as $forum) {
            $adParams[] = array(
                'forumId' => $forum,
                'userId' => $user,
                'status' => 1, //1 is active i.e. ads disabled, 0 is inactive
                'lastModified' => date("Y-m-d h:i:s")
            );
        }
        $this->db->insert_batch('adsDisabled', $adParams);
    }

    function removeAdsDisabled($user, $forums) {

        foreach ($forums as $forum) {
            $adParams = array(
                'status' => 0, //1 is active (i.e. ads disabled, 0 is inactive)
                'lastModified' => date("Y-m-d h:i:s")
            );

            $this->db->where('forumId', $forum)
                    ->where('userId', $user)
                    ->where('status', 1);
            $this->db->update('adsDisabled', $adParams);
        }
    }

    function getAdsDisabled($user) {

        $this->db->select('forumId')
                ->select('Subject.subjectName AS forumName');
        $this->db->from('adsDisabled');
        $this->db->join('Subject', 'Subject.subjectId = adsDisabled.forumId');
        $this->db->where('userId', $user)
                ->where('status', 1);
        $query = $this->db->get();

        if ($query->num_rows() > 0) {
            $forums = $query->result_array();
            return $forums;
        } else {
            return false;
        }
    }

    /* Distributed Payments */


    /*
     * If a payee does not have an existing 
     * PayPal account, the PayPal e-mail describes 
     * how they can create a PayPal account so they can 
     * claim their payment. PayPal holds MassPay transactions 
     * for 30 days. If a payment remains unclaimed after 30 days, 
     * the amount is returned to the payer's PayPal account.
     * 
     * Important: First time users should contact their Account Manager 
     * or PayPal Customer Support to enable Mass Payments for their PayPal 
     * premier or business accounts. 
     * It is possible to test the Mass Pay API in the PayPal Sandbox without prior 
     * authorization, but live PayPal accounts must be white listed for the Mass Pay API.
     * 
     * 
     * Mass Pay Fees
     * 
     * According to PayPal doc, Mass Pay fees are 2% with a cap of $1.25AUD for local 
     * payments, and a cap of $60AUD for international payments.
     * https://www.paypal.com/au/cgi-bin/webscr?cmd=_batch-payment-overview-outside
     * 
     * Note that when testing payments to non-AU PayPal accounts (e.g. GB or US), the
     * fees charged on the sandbox was still only 2% or the LOCAL cap of 1.25AUD.
     * The residency_country of the non-AU accounts was returned as AU by PayPal IPN.
     * This could be a bug in the sandbox environment, so will do some further testing
     * when live is available.
     * 
     * For now, the fees work like this:
     * - For local payments, 2% or $1.25AUD (whichever is the smallest) is taken from the distributed payment amount
     * before sending.
     * - For international payments (i.e. the moderator is non-AU based so is assumed to have a non-AU PayPal account)
     * 2% or $60AUD (whichever is smallest) is taken from the payment before sending.
     * Fees can be changed/set in Ad Admin, plus other variables.
     * 
     * All payments will be sent in AUD, so there should be no currency conversion fees at our end.
     * 
     * Note values are configurable in distributedPaymentsConfig.
     * 
     */

    function calculateMonthlyAdRevenue($month, $year) {

        $this->db->select('SUM(subTotal) AS monthlyRevenueTotal')
                ->select('adToForum.forumId AS forumId')
                ->select('MONTHNAME(purchaseDate) AS month')
                ->select('YEAR(purchaseDate) AS year');
        $this->db->from('ad_orders');
        $this->db->join('adToForum', 'ad_orders.adId = adToForum.adId');
        $this->db->where('MONTHNAME(purchaseDate)', $month)
                ->where('YEAR(purchaseDate)', $year)
                ->where('ad_orders.paid', 1);
        $this->db->group_by('forumId');

        $query = $this->db->get();
        $monthlyTotal = $query->result_array();

        if ($query->num_rows() > 0) {
            return ($monthlyTotal);
        } else {
            return (NULL);
        }
    }

    function calculateMonthlyForumAdRevenue($month, $year, $forumId) {

        $this->db->select('SUM(subTotal) AS monthlyForumRevenueTotal');
        $this->db->from('ad_orders');
        $this->db->join('adToForum', 'ad_orders.adId = adToForum.adId');
        $this->db->where('MONTHNAME(purchaseDate)', $month)
                ->where('YEAR(purchaseDate)', $year)
                ->where('adToForum.forumId', $forumId)
                ->where('ad_orders.paid', 1);

        $query = $this->db->get();
        $monthlyForumRevenueTotal = $query->row();
        if ($query->num_rows() > 0) {
            return ($monthlyForumRevenueTotal);
        } else {
            return (NULL);
        }
    }

    //not used
    function _insertMonthlyAdRevenue($monthlyAdRevenue) {

        $this->db->insert_batch('adRevenue', $monthlyAdRevenue);
    }

    function getModeratorsForMonth($month, $year) {

        $this->db->select('ModeratorToSubject.userId')
                ->select('ModeratorToSubject.subjectId AS forumId')
                ->select('ModeratorToSubject.distributedPaymentPercentage')
                ->select('User.email')
                ->select('User.countryId')
                ->select("'$month' AS month", FALSE)
                ->select("'$year' AS year", FALSE);
        $this->db->from('ModeratorToSubject');
        $this->db->join('User', 'User.userId = ModeratorToSubject.UserId');
        $this->db->where('approved', 1)
                //  ->where('moderator', 1) //off for testing only!!!!
                ->where('distributedPayments', 1);
        $this->db->order_by('userId');

        $query = $this->db->get();
        $moderatorsForMonth = $query->result_array();

        if ($query->num_rows() > 0) {
            return ($moderatorsForMonth);
        } else {
            return (NULL);
        }
    }

    function insertModeratorsForMonth($moderatorsForMonth) {

        $this->db->insert_batch('distributedPayments', $moderatorsForMonth);
    }

    function calculateMonthlyDistributedPayments($month, $year, $moderatorsForMonth) {

        if (!empty($moderatorsForMonth)) {
            foreach ($moderatorsForMonth as $moderator) {
                $monthlyForumRevenue = $this->calculateMonthlyForumAdRevenue($month, $year, $moderator["forumId"])->monthlyForumRevenueTotal;
                if ($monthlyForumRevenue != 0) {
                    $numberOfForumMods = $this->getNumberOfForumModerators($moderator["forumId"]);
                    $actualPaymentPercentage = $moderator["distributedPaymentPercentage"] / $numberOfForumMods->number;
                    $grossPaymentAmount = round(($monthlyForumRevenue * $actualPaymentPercentage) / 100, 2);
                    $fee = $this->calculateMassPayFee($grossPaymentAmount, $moderator["countryId"]); //see note about fees above
                    $actualPaymentAmount = $grossPaymentAmount - $fee;
                    $distributedPaymentValues[] = array(
                        'userId' => $moderator["userId"],
                        'forumId' => $moderator["forumId"],
                        'receiver_email' => $moderator["email"],
                        'userResidenceCountry' => $moderator["countryId"],
                        'month' => $moderator["month"],
                        'year' => $moderator["year"],
                        'forumMonthlyRevenueTotal' => $monthlyForumRevenue,
                        'numberOfModerators' => $numberOfForumMods->number,
                        'distributedPaymentPercentage' => $moderator["distributedPaymentPercentage"],
                        'actualPaymentPercentage' => $actualPaymentPercentage,
                        'grossPaymentAmount' => $grossPaymentAmount,
                        'fee' => $fee,
                        'paymentAmount' => $actualPaymentAmount,
                        'paymentCurrCode' => $this->config->item('PAYPAL_CUR_CODE')
                    );
                }
            }
            if (!empty($distributedPaymentValues)) {
                $this->db->insert_batch('distributedPayments', $distributedPaymentValues);
            }
            //puts status as 0 for calculations as may want to use 1 for processing
            $paymentLog = array('month' => $month, 'year' => $year, 'status' => 0);
           // $this->db->insert('monthlyDistributedPaymentsLog', $paymentLog); //turned off for teswstin!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!1
        }
    }

    //see note about fees above

    function calculateMassPayFee($grossPaymentAmount, $countryId) {

        $this->db->select('*');
        $this->db->from('distributedPaymentsConfig');

        $query = $this->db->get();
        $config = $query->row();

        if ($query->num_rows() > 0) {

            if ($config->localCountryId === $countryId) {
                //a local payment
                $fee = round(($config->localFeePercentage / 100) * $grossPaymentAmount, 2);
                if ($fee > $config->localFeeCap) {
                    $fee = $config->localFeeCap;
                }
            } else {
                //an international payment
                $fee = round(($config->internationalFeePercentage / 100) * $grossPaymentAmount, 1);
                if ($fee > $config->internationalFeeCap) {
                    $fee = $config->internationalFeeCap;
                }
            }
            return ($fee);
        } else {
            return (NULL);
        }
    }

    function processMonthlyDistributedPayments() {

        $this->load->model('paypalmodel');
        
        $live = FALSE; 
        if($this->config->item('massPayLive')) {
            $live = TRUE;
        }

        /*
         * Possible values of the ACK response field are the following:

          Success — The request was successful.
          SuccessWithWarning — The request was successful but a warning error code was also returned.
          Failure — The API request failed. See error messages for details.
          FailureWithWarning — The API request failed and additional warning messages were returned.
         * 
         */
        
        if ($this->getProcessLock('currentlyCalculating') && $this->getProcessLock('currentlyAuthorizing')) {
            log_message('error', 'model fn processMonthlyDistributedPayments() -  sending locked');
            return;
        } else {
                $this->setProcessLock('currentlySending',1);
        }

        $this->db->select('distributedPayments.receiver_email')
                ->select('distributedPayments.paymentAmount')
                ->select('distributedPayments.paymentId')
                ->select('distributedPayments.month')
                ->select('distributedPayments.year');
        $this->db->from('distributedPayments');
        $this->db->where('paid', 0)
                ->where('authorized', 1);


        $query = $this->db->get();
        $monthlyPayments = $query->result_array();


        if ($query->num_rows() > 0) {

            $nvpSetupStr = "&RECEIVERTYPE=EmailAddress&CURRENCYCODE="
                    . urlencode($this->config->item('PAYPAL_CUR_CODE'))
                    . "&EMAILSUBJECT=Moderator Payment From R..";

            $nvpPaymentsStr = NULL;
            $i = 0;
            $outstanding = FALSE;
            foreach ($monthlyPayments as $monthlyPayment) {
                //if ($this->paymentAuthorized($monthlyPayment['paymentId'])) {
                $outstanding = TRUE;
                $this->insertPaymentsBeingProcessed($monthlyPayment['paymentId']);

                $nvpPaymentsStr .= "&L_EMAIL" . $i . "=" . $monthlyPayment['receiver_email'] .
                        "&L_AMT" . $i . "=" . $monthlyPayment['paymentAmount'] .
                        "&L_UNIQUEID" . $i . "=" . $monthlyPayment['paymentId'];

                if ($i === 249) { // only allowed to send 250 MassPay payments to PayPal at any one time  
                    $massPayResponse = $this->paypalmodel->sendPayPalTransaction('MassPay', $nvpSetupStr . $nvpPaymentsStr, $live);
                    var_dump($nvpPaymentsStr);
                    var_dump($massPayResponse);
                    $this->setFinalStatus($massPayResponse["ACK"], $massPayResponse["CORRELATIONID"]);
                    $this->paypalmodel->logPaypalMassPayResponse($massPayResponse);
                    $outstanding = FALSE;
                    $nvpPaymentsStr = NULL;
                    $i = -1;
                }

                $i++;
            }
            if ($outstanding) {
                $massPayResponse = $this->paypalmodel->sendPayPalTransaction('MassPay', $nvpSetupStr . $nvpPaymentsStr, $live);
                $this->setFinalStatus($massPayResponse["ACK"], $massPayResponse["CORRELATIONID"]);
                $this->paypalmodel->logPaypalMassPayResponse($massPayResponse);
                var_dump($massPayResponse);
            }

            $this->setProcessLock('currentlySending',0);
            return ($monthlyPayments);
        } else {
            $this->setProcessLock('currentlySending',0);
            return (NULL);
        }


        /* may want to use this one day
         *
         * L_NOTEn
         * (Optional)
         *
         * Custom note for each recipient.
         * Character length and limitations: 4,000 single-byte alphanumeric
         * 
         */
    }

    function getNumberOfForumModerators($forumId) {

        $this->db->select('COUNT(userId) AS number');
        $this->db->from('ModeratorToSubject');
        $this->db->where('subjectId', $forumId);

        $query = $this->db->get();
        $numberOfMods = $query->row();

        if ($query->num_rows() > 0) {
            return ($numberOfMods);
        } else {
            return (NULL);
        }
    }

    function paymentsNotAlreadyCalculated($month, $year, $status) {

        $this->db->select('*');
        $this->db->from('monthlyDistributedPaymentsLog');
        $this->db->where('month', $month);
        $this->db->where('year', $year);
        $this->db->where('status', $status);

        $query = $this->db->get();

        if ($query->num_rows() > 0) {
            return FALSE;
        } else {
            return TRUE;
        }
    }

    function paymentAlreadySent($paymentId) {

        $this->db->select('*');
        $this->db->from('distributedPayments');
        $this->db->where('paymentId', $paymentId)
                ->where('paid', 1);

        $query = $this->db->get();

        if ($query->num_rows() > 0) {
            return TRUE;
        } else {
            return FALSE;
        }
    }

    function paymentAuthorized($paymentId) {

        $this->db->select('*');
        $this->db->from('distributedPayments');
        $this->db->where('paymentId', $paymentId)
                ->where('authorized', 1);

        $query = $this->db->get();

        if ($query->num_rows() > 0) {
            return TRUE;
        } else {
            return FALSE;
        }
    }

    function deAuthAndUpdateStatus($paymentId) {

        $this->db->set('status', 'Error - Payment already sent so has been de-authorized')
                ->set('payment_status', 'Error - Payment already sent so has been de-authorized')
                ->set('authorized', 1);
        $this->db->where('paymentId', $paymentId);
        $this->db->update('distributedPayments');
    }

    

    function insertPaymentsBeingProcessed($paymentId) {

        $this->db->insert('paymentsBeingProcessed', array('paymentId' => $paymentId));

        //set status
        $this->db->set('status', 'Waiting for PayPal IPN')
                ->set('payment_status', 'Waiting for PayPal IPN');
        $this->db->where('paymentId', $paymentId);
        $this->db->update('distributedPayments');
    }

    function setFinalStatus($ack, $correlationId) {

        $this->db->select('paymentId');
        $this->db->from('paymentsBeingProcessed');

        $query = $this->db->get();
        $currentStatuses = $query->result_array();
        $status = 0;
        if ($ack === 'Success') {
            $status = 1;
        }
        if ($query->num_rows() > 0) {
            foreach ($currentStatuses as $currentStatus) {
                $statusArr[] = array(
                    'paymentId' => $currentStatus['paymentId'],
                    'paid' => $status,
                    'authorized' => $status, //de-authorize payment if error occurs
                    'preAuthorized' => $status, //de-authorize payment if error occurs
                    'ack' => $ack,
                    'correlationId' => $correlationId,
                    'datePaid' => date('Y-m-d H:i:s')
                );
            }
            $this->db->update_batch('distributedPayments', $statusArr, 'paymentId');
            $this->db->empty_table('paymentsBeingProcessed');
        }
    }

    function automatedDistributedPayments() {

        $this->db->select('distributedPaymentsAutomated');
        $this->db->from('distributedPaymentsConfig');
        $this->db->where('distributedPaymentsAutomated', 1);

        $query = $this->db->get();

        if ($query->num_rows() > 0) {
            return TRUE;
        } else {
            return FALSE;
        }
    }

    function enableAutomatedDistributedPaymentStatus() {
        $this->db->update('distributedPaymentsConfig', array('distributedPaymentsAutomated' => 1));
    }

    function disableAutomatedDistributedPaymentStatus() {
        $this->db->update('distributedPaymentsConfig', array('distributedPaymentsAutomated' => 0));
    }
    
    function updateDistributedPaymentsConfig($post) {
    
        $this->db->update('distributedPaymentsConfig', $post);
    
        
    }

    function getDistributedPayments($paid, $authorize = '', $processing = '') {

        $this->db->select('*');
        $this->db->from('distributedPayments');
        $this->db->where('paid', $paid);
        if ($authorize) {
            $this->db->where('authorized', 0);
        }
        if ($processing) {
            $this->db->where('authorized', 1);
        }

        $query = $this->db->get();
        $completedPayments = $query->result_array();

        if ($query->num_rows() > 0) {
            return $completedPayments;
        } else {
            return NULL;
        }
    }

    function getDistributedPaymentsSummary($paid, $authorize = '', $processing = '') {

        $this->db->select('SUM(paymentAmount) AS paymentAmount')
                ->select('SUM(forumMonthlyRevenueTotal) AS revenueTotal')
                ->select('month')
                ->select('year')
                ->select('authorized')
                ->select('preAuthorized')
                //->select('paymentId')
                ->select('payment_status')
                ->select('paid');
        $this->db->from('distributedPayments');
        $this->db->where('paid', $paid);
        if ($authorize) {
            $this->db->where('authorized', 0);
        }
        if ($processing) {
            $this->db->where('authorized', 1);
        }
        $this->db->group_by('month')
                ->group_by('year')
                ->group_by('authorized')
                ->group_by('preAuthorized')
                ->group_by('payment_status')
                ->group_by('paid');
        $this->db->order_by('year', 'DESC');
        //$this->db->order_by("STR_TO_DATE('month','%M')", FALSE);
        //@todo
        //need to order by month as well - desc

        $query = $this->db->get();
        $completedPayments = $query->result_array();
        if ($query->num_rows() > 0) {
            return $completedPayments;
        } else {
            return NULL;
        }
    }

    /*
     * auths / deauths a distributed payment
     */

    function preAuthPayment($paymentId) {

        $this->db->select('preAuthorized');
        $this->db->from('distributedPayments');
        $this->db->where('paymentId', $paymentId);

        $query = $this->db->get();
        $auth = $query->row();

        if ($query->num_rows() > 0) {
            $this->db->set('preAuthorized', !$auth->preAuthorized); //negate current value
            $this->db->where('paymentId', $paymentId);
            $this->db->update('distributedPayments');
            return TRUE;
        } else {
            return NULL;
        }
    }

    /*
     * auths / deauths a distributed payment for a given period
     */

    function preAuthPaymentRange($month, $year) {

        $this->db->select('preAuthorized');
        $this->db->from('distributedPayments');
        $this->db->where('month', $month)
                ->where('year', $year)
                ->where('authorized', 0);
        $this->db->limit(1);

        $query = $this->db->get();
        $auth = $query->row();

        if ($query->num_rows() > 0) {
            $this->db->set('preAuthorized', !$auth->preAuthorized); //negate current value
            $this->db->where('month', $month)
                    ->where('year', $year)
                    ->where('authorized', 0);
            $this->db->update('distributedPayments');
            return TRUE;
        } else {
            return NULL;
        }
    }

    function authPayments() {

        $this->db->set('authorized', 1);
        $this->db->where('preAuthorized', 1);
        $this->db->update('distributedPayments');

        $this->db->set('authorized', 0);
        $this->db->where('preAuthorized', 0);
        $this->db->update('distributedPayments');
    }

    function getDistributedPaymentMonths() {

        $this->db->select('MONTHNAME(purchaseDate) AS month')->distinct();
        $this->db->where('purchaseDate IS NOT NULL');
        $this->db->where('purchaseDate !=', '');
        $this->db->from('ad_orders');

        $query = $this->db->get();
        $months = $query->result_array();
        if ($query->num_rows() > 0) {
            return $months;
        } else {
            return NULL;
        }
    }

    function getDistributedPaymentYears() {

        $this->db->select('YEAR(purchaseDate) AS year')->distinct();
        $this->db->where('purchaseDate IS NOT NULL');
        $this->db->where('purchaseDate !=', '');
        $this->db->from('ad_orders');

        $query = $this->db->get();
        $years = $query->result_array();
        if ($query->num_rows() > 0) {
            return $years;
        } else {
            return NULL;
        }
    }
    
    
    function getDistributedPaymentConfig() {

        $this->db->select('*');
        $this->db->from('distributedPaymentsConfig');

        $query = $this->db->get();
        $config = $query->result_array();
        if ($query->num_rows() > 0) {
            return $config[0];
        } else {
            return NULL;
        }
    }
    
     function distributedPaymentsRequireAuth() {

        $this->db->select('distributedPaymentsRequireAuth');
        $this->db->from('distributedPaymentsConfig');
        $this->db->where('distributedPaymentsRequireAuth',1);

        $query = $this->db->get();
        if ($query->num_rows() > 0) {
            return TRUE;
        } else {
            return FALSE;
        }
    }
    
    
    function getAdAdminUsersIds() {

        $this->db->select('userId');
        $this->db->from('Permissions');
        $this->db->where('adAdmin', 1);

        $query = $this->db->get();
        $userIds = $query->result_array();
        if ($query->num_rows() > 0) {
            return $userIds;
        } else {
            return NULL;
        }
    }
    
    
    
     function getDistributedPaymentInfo() {

        $this->db->select('ModeratorToSubject.userId')
                ->select('ModeratorToSubject.subjectId')
                ->select('ModeratorToSubject.distributedPayments')
                ->select('ModeratorToSubject.distributedPaymentPercentage')
                ->select('User.userName')
                ->select('User.email')
                ->select('User.countryId')
                ->select('Subject.subjectName');
        $this->db->from('ModeratorToSubject');
        $this->db->join('User', 'User.userId = ModeratorToSubject.userId')
                ->join('Subject', 'Subject.subjectId = ModeratorToSubject.subjectId');

        $query = $this->db->get();
        $paymentInfo = $query->result_array();
        if ($query->num_rows() > 0) {
            return $paymentInfo;
        } else {
            return NULL;
        }
    }
    
     function updateDistributedPaymentInfo($post) {
         
        $this->db->update('ModeratorToSubject', $post, array('userId' => $post["userId"], 'subjectId' => $post['subjectId']));
    }
    
    
    function sendPaymentAuthMessage($month, $year) {
        
        $this->load->model('message');
        $this->load->model('group');
        
        $userIds = $this->getAdAdminUsersIds();
        
            foreach ($userIds as $userId) {
            $this->message->systemMessage(1, $userId["userId"], 1, message::MSG_PAYMENTS_AUTH, array('month' => $month, 'year' => $year), NULL);
        }
    }
    
    function setProcessLock($col, $lock) {
    
        $this->db->set($col, $lock);
        $this->db->update('distributedPaymentsConfig');
    }
    
    function getProcessLock($col) {
    
        $this->db->select($col);
        $this->db->from('distributedPaymentsConfig');
        $this->db->where($col,1);
        
        $query = $this->db->get();
        if ($query->num_rows() > 0) {
            return TRUE;
        } else {
            return FALSE;
        }
    }

    /* End Distributed Payments */
}
