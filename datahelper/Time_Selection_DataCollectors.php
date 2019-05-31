<?php

namespace datahelper;

use projectsettings;
use db;
use utils;
use filters;

class Time_Selection_DataCollectors {

    private $queryVars;
    private $settingVars;

    public function __construct($settingVars) {
        $this->queryVars = projectsettings\settingsGateway::getInstance();
        $this->settingVars = $settingVars;
    }

    /*     * **
     * Finds out Maximum and Minimum Time_Selection_DataCollectors of current year and set time vars .. [$FromWeek,$ToWeek,$FromYear,$ToYear]
     * and calls header/getExtraSlice function to prepare $tyWeekRange and $lyWeekRange
     * ** */

    function getLatestWeek() {
        $query = "SELECT MAX(" . $this->settingVars->yearperiod . ") FROM " . $this->settingVars->timeHelperTables . " " . $this->settingVars->timeHelperLink;
        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_ARRAY);
        $data = $result[0];
        filters\timeFilter::$ToYear = $data[0];
        filters\timeFilter::$FromYear = filters\timeFilter::$ToYear;


        $query = "SELECT MAX(" . $this->settingVars->weekperiod . ")" .
                ",MIN(" . $this->settingVars->weekperiod . ") " .
                "FROM " . $this->settingVars->timeHelperTables . $this->settingVars->timeHelperLink . " AND " . $this->settingVars->yearperiod . "=" . filters\timeFilter::$ToYear;
        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_ARRAY);
        $data = $result[0];
        filters\timeFilter::$ToWeek = $data[0];
        filters\timeFilter::$FromWeek = $data[1];


        //USEFUL WHEN DEBUGGING, DON'T DELETE
        //print 'Max Week: '.filters\timeFilter::$ToWeek." Min Week: ".filters\timeFilter::$FromWeek;
        //exit;

        filters\timeFilter::getExtraSlice($this->settingVars);
    }

    /*     * **
     * Returns all existing year-Time_Selection_DataCollectors combination
     * ** */

    function getAllWeek(&$jsonOutput, $includeDate = true, $returnArray  = false) {

        $redisCache = new utils\RedisCache($this->queryVars);

        $mydateSelect = $this->settingVars->getMydateSelect($this->settingVars->dateField);
        $query = "SELECT " . $this->settingVars->yearperiod . " AS YEAR" .
                ",".$this->settingVars->weekperiod . " AS WEEK" .
                (($this->settingVars->dateField) ? ",".$mydateSelect." " : " ") .
                "FROM " . $this->settingVars->timeHelperTables . $this->settingVars->timeHelperLink .
                "GROUP BY YEAR,WEEK " .
                "ORDER BY YEAR DESC,WEEK DESC";
        $queryHash = md5($query);
        $redisOutput = $redisCache->checkAndReadByStaticHashFromCache('time_filter_data', $queryHash);

        if ($redisOutput === false) {
            $dateList = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_ARRAY);
            $redisCache->setDataForSubKeyHash($dateList, $queryHash);
        } else {
            $dateList = $redisOutput;
        }

        //FINDING MAXIMUM YEAR   

        /*$query = "SELECT MAX(" . $this->settingVars->yearperiod . ") FROM " . $this->settingVars->timeHelperTables . $this->settingVars->timeHelperLink;
        $queryHash = md5($query);
        $redisOutput = $redisCache->checkAndReadByStaticHashFromCache('time_filter_data', $queryHash);

        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_ARRAY);
            $redisCache->setDataForSubKeyHash($result, $queryHash);
        } else {
            $result = $redisOutput;
        }*/

        $data = $dateList[0];
        
        if (isset($dateList[0][2]))
            $this->settingVars->latestMydate = $dateList[0][2];

        $maxYear = $data[0];
        $maxWeekOfMaxYear = $data[1];

        $maxYearAllWeek = $yearList = array();
        $prevEntryYear = 0;
        if (is_array($dateList) && !empty($dateList)) {
            foreach ($dateList as $dateDetail) {
                $yearList[$dateDetail[0]] = $dateDetail[0];

                if ($dateDetail[0] != $prevEntryYear)
                    $maxDateOfAllYear[$dateDetail[0]] = $dateDetail;

                $prevEntryYear = $dateDetail[0];

                if ($dateDetail[0] == $maxYear) {
                    $maxYearAllWeek[] = $dateDetail;
                }
            }
        }

        $maxYearAllWeek = utils\SortUtility::sort2DArray($maxYearAllWeek, 1, utils\SortTypes::$SORT_ASCENDING);

        foreach ($yearList as $key => $data) {
            if (isset($maxDateOfAllYear[$data]) && !empty($maxDateOfAllYear[$data]) && 
                $maxDateOfAllYear[$data][1] > 52) {
                $weekNum = $maxDateOfAllYear[$data][1];
            } else {
                $weekNum = 52;
            }

            for ($i = $weekNum; $i >= 1; $i--) {
                $tempArr = array("year" => $data, "week" => $i, "yearweek" => $i . "-" . $data);
                $yearWeekArr[] = $tempArr;
            }
        }

        $minCombination = $dateList[count($dateList)-1];
        $minYear = $minCombination[0];
        $minWeekOfMinYear = $minCombination[1];

        // print_r($maxYearAllWeek);exit();

        //FINDING MAXIMUM AND MINIMUM WEEKS USING MAXIMUM YEAR
        // $query = "SELECT MAX(" . $this->settingVars->weekperiod . ")," .
        //         "MIN(" . $this->settingVars->weekperiod . ") " .
        //         "FROM " . $this->settingVars->timeHelperTables . $this->settingVars->timeHelperLink .
        //         "AND " . $this->settingVars->yearperiod . "=" . $maxYear;
        // $queryHash = md5($query);
        // $redisOutput = $redisCache->checkAndReadByStaticHashFromCache('time_filter_data', $queryHash);

        // if ($redisOutput === false) {
        //     $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_ARRAY);
        //     $redisCache->setDataForSubKeyHash($result, $queryHash);
        // } else {
        //     $result = $redisOutput;
        // }

        // $data = $result[0];
        $maxCombination = $maxYearAllWeek[count($maxYearAllWeek)-1];
        $minCombination = $maxYearAllWeek[0];
        $maxWeek = $maxCombination[1];
        $minWeek = $minCombination[1];

        $mydateArr = array();
        foreach ($dateList as $key => $data) {
            $mydate = date("jS M Y", strtotime($data[2]));
            $mydateArr[$data[0]][$data[1]] = $mydate;
        }

        for ($i = 0; $i < count($yearWeekArr); $i++) {
            if ($minYear == $yearWeekArr[$i]['year'] && $minWeekOfMinYear == $yearWeekArr[$i]['week']) {
                $minCounter = $i;
                // break;
            }

            if ($maxYear == $yearWeekArr[$i]['year'] && $maxWeekOfMaxYear == $yearWeekArr[$i]['week']) {
                $maxCounter = $i;
                // break;
            }
        }

        //USEFUL WHEN DEBUGGING, DON'T DELETE  
        //print 'Max Week: '.$maxWeek." Min Week: ".$minWeek;
        //exit;

        // print_r($dateList);
        // exit();
        /*$tempArr = array();
        foreach ($dateList as $j => $data) {

            if ($data[1] == $minWeek && $maxYear == $data[0])
                $jsonOutput['selectedIndexFrom'] = $j; //MINIMUM WEEK OF MAXIMUM YEAR  = FROM WEEK SELECTED ITEM

            if ($data[1] == $minWeek && ($maxYear-1) == $data[0])
                $jsonOutput['selectedLyIndexFrom'] = $j; //MINIMUM WEEK OF MAXIMUM YEAR  = FROM WEEK SELECTED ITEM

            if ($data[1] == $maxWeek && ($maxYear-1) == $data[0])
                $jsonOutput['selectedLyIndexTo'] = $j; //MINIMUM WEEK OF MAXIMUM YEAR  = FROM WEEK SELECTED ITEM

            $jsonOutput['selectedIndexTo'] = 0;

            $dateperiod = '';
            if ($includeDate && $this->settingVars->dateperiod)
                $dateperiod = date("jS M Y", strtotime($data[2]));

            $temp = array(
                'data' => $data[1] . "-" . $data[0]
                , 'label' => $data[1] . "-" . $data[0] . (($dateperiod) ? " (" . $dateperiod . ")" : "")
                , 'numdata' => $j
            );

            $tempArr[] = $temp;
        }*/

        $tempArr = array();
        for ($i = $maxCounter; $i <= $minCounter; $i++) {
            $mydateVal = "";
            if (key_exists($yearWeekArr[$i]['year'], $mydateArr)) {
                if (key_exists($yearWeekArr[$i]['week'], $mydateArr[$yearWeekArr[$i]['year']]))
                    $mydateVal = $mydateArr[$yearWeekArr[$i]['year']][$yearWeekArr[$i]['week']];
            }

            if ($minWeek == $yearWeekArr[$i]['week'] && $maxYear == $yearWeekArr[$i]['year'])
                $jsonOutput['selectedIndexFrom'] = $i - $maxCounter;

            if ($maxWeek == $yearWeekArr[$i]['week'] && $maxYear == $yearWeekArr[$i]['year'])
                $jsonOutput['selectedIndexTo'] = $i - $maxCounter;

            if ($minWeek == $yearWeekArr[$i]['week'] && ($maxYear-1) == $yearWeekArr[$i]['year'])
                $jsonOutput['selectedLyIndexFrom'] = $i - $maxCounter;

            if ($maxWeek == $yearWeekArr[$i]['week'] && ($maxYear-1) == $yearWeekArr[$i]['year'])
                $jsonOutput['selectedLyIndexTo'] = $i - $maxCounter;

            if ($includeDate)
                if($mydateVal != '')
                    $mydateVal = " (".$mydateVal.")";
                else
                    $mydateVal = '';
            else
                $mydateVal = "";

            $temp = array(
                'data' => $yearWeekArr[$i]['yearweek']
                , 'label' => $yearWeekArr[$i]['yearweek'] . $mydateVal
                , 'numdata' => $i - $maxCounter
            );
            $tempArr[] = $temp;
        }
        
        if ($returnArray)
            return $tempArr;
        else
            $jsonOutput['gridWeek'] = $tempArr;
    }
	
	/*     * **
     * Returns all existing year-Time_Selection_DataCollectors combination
     * ** */

    function getAllMonth(&$jsonOutput) {
        //FINDING MAXIMUM YEAR			
        $query = "SELECT MAX(" . $this->settingVars->yearperiod . ") FROM " . $this->settingVars->timeHelperTables . $this->settingVars->timeHelperLink;
        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_ARRAY);
        $data = $result[0];
        $maxYear = $data[0];


        //FINDING MAXIMUM AND MINIMUM WEEKS USING MAXIMUM YEAR
        $query = "SELECT MAX(" . $this->settingVars->monthperiod . ") MAXMONTH," .
                "MIN(" . $this->settingVars->monthperiod . ") MINMONTH " .
                "FROM " . $this->settingVars->timeHelperTables . $this->settingVars->timeHelperLink .
                "AND " . $this->settingVars->yearperiod . "=" . $maxYear;
        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
        $data = $result[0];
        $maxMonth = $data['MAXMONTH'];
        $minMonth = $data['MINMONTH'];

        //USEFUL WHEN DEBUGGING, DON'T DELETE  
        //print 'Max Week: '.$maxWeek." Min Week: ".$minWeek;
        //exit;

        $mydateSelect = $this->settingVars->getMydateSelect($this->settingVars->dateField);
        $query = "SELECT " . $this->settingVars->yearperiod . " AS YEAR" .
                ",".$this->settingVars->monthperiod . " AS MONTH" .
                // (($this->settingVars->dateperiod) ? ",MAX(" . $this->settingVars->dateperiod . ") " : " ") .
                (($this->settingVars->dateField) ? ",".$mydateSelect." " : " ") .
                "FROM " . $this->settingVars->timeHelperTables . $this->settingVars->timeHelperLink .
                "GROUP BY YEAR,MONTH " .
                "ORDER BY YEAR DESC,MONTH DESC";
        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_ARRAY);
        foreach ($result as $j => $data) {

            if ($data[1] == $minMonth && $maxYear == $data[0])
                $jsonOutput['selectedIndexFrom'] = $j; //MINIMUM WEEK OF MAXIMUM YEAR  = FROM WEEK SELECTED ITEM
            $jsonOutput['selectedIndexTo'] = 0;

            $dateperiod = '';
            if ($includeDate && $this->settingVars->dateperiod)
                $dateperiod = date("jS M Y", strtotime($data[2]));

            $temp = array(
                'data' => $data[1] . "-" . $data[0]
                , 'label' => $data[1] . "-" . $data[0] . (($dateperiod) ? " (" . $dateperiod . ")" : "")
                , 'numdata' => $j
            );
            $jsonOutput['gridWeek'][] = $temp;
        }
    }
	
	/****
    * Returns all existing year-Time_Selection_DataCollectors combination
    ****/
    function getAllMydate(&$jsonOutput, $returnArray  = false){
        if (empty($this->settingVars->dateField))
            return false;

        $redisCache = new utils\RedisCache($this->queryVars);

        $query = "SELECT " . $this->settingVars->dateField ." AS MYDATE ".
                "FROM " . $this->settingVars->timeHelperTables . $this->settingVars->timeHelperLink .
                "GROUP BY MYDATE " .
                "ORDER BY MYDATE DESC";
        $queryHash = md5($query);
        $redisOutput = $redisCache->checkAndReadByStaticHashFromCache('time_filter_data', $queryHash);

        if ($redisOutput === false) {
            $dateList = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_ARRAY);
            $redisCache->setDataForSubKeyHash($dateList, $queryHash);
        } else {
            $dateList = $redisOutput;
        }


        //print_r($dateList);

        if (is_array($dateList) && !empty($dateList)) {
            $maxYear = date('Y', strtotime($dateList[0][0]));
            $this->settingVars->latestMydate = $dateList[0][0];

            foreach ($dateList as $dateDetail) {
                if (date('Y', strtotime($dateDetail[0])) == $maxYear) {
                    $maxYearAllDate[] = $dateDetail;
                }
            }

            //FINDING MAXIMUM AND MINIMUM WEEKS USING MAXIMUM YEAR
            $maxYearAllDate = utils\SortUtility::sort2DArray($maxYearAllDate, 0, utils\SortTypes::$SORT_ASCENDING);
            $maxDate = $maxYearAllDate[count($maxYearAllDate)-1];
            $minDate = $maxYearAllDate[0];

            if(isset($this->settingVars->fromToDateRange)) {
                $maxDate[0]     = $maxYear.'-'.$this->settingVars->fromToDateRange['toMonth'].'-'.$this->settingVars->fromToDateRange['toDay'];
                $minDate[0]     = ($maxYear-1).'-'.$this->settingVars->fromToDateRange['fromMonth'].'-'.$this->settingVars->fromToDateRange['fromDay'];

                $toLyDate[0]  = ($maxYear-1).'-'.$this->settingVars->fromToDateRange['toMonth'].'-'.$this->settingVars->fromToDateRange['toDay'];
                $fromLyDate[0] = ($maxYear-2).'-'.$this->settingVars->fromToDateRange['fromMonth'].'-'.$this->settingVars->fromToDateRange['fromDay'];
            }
        }

        // print_r($maxDate);
        // print_r($minDate);


        //FINDING MAXIMUM AND MINIMUM WEEKS USING MAXIMUM YEAR
        /*$query      = "SELECT MAX(".$this->settingVars->dateField.") MAXDATE,".
                    "MIN(".$this->settingVars->dateField.") MINDATE ".
                    "FROM ".$this->settingVars->timeHelperTables.$this->settingVars->timeHelperLink.
                    "AND YEAR(".$this->settingVars->dateField.")=(select YEAR(MAX(distinct ".$this->settingVars->dateField.")) FROM ".$this->settingVars->timeHelperTables.$this->settingVars->timeHelperLink.")";
        $result     = $this->queryVars->queryHandler->runQuery($query,$this->queryVars->linkid,db\ResultTypes::$TYPE_OBJECT);
        $data       = $result[0];
        $maxDate    = $data['MAXDATE'];
        $minDate    = $data['MINDATE'];*/


        //USEFUL WHEN DEBUGGING, DON'T DELETE  
        //print 'Max Week: '.$maxWeek." Min Week: ".$minWeek;
        //exit;
        
        /*$query      = "SELECT ".$this->settingVars->dateField." AS MYDATE".
                    " FROM ".$this->settingVars->timeHelperTables.$this->settingVars->timeHelperLink.
                    "GROUP BY MYDATE ".
                    "ORDER BY MYDATE DESC";
        $result     = $this->queryVars->queryHandler->runQuery($query,$this->queryVars->linkid,db\ResultTypes::$TYPE_ARRAY);*/
     
        $weeksList  = array();
        foreach($dateList as $j=>$data)
        {
            $jsonOutput['selectedIndexTo'] = 0;
            if ($data[0] == $minDate[0])
                $jsonOutput['selectedIndexFrom'] = $j; //MINIMUM WEEK OF MAXIMUM YEAR  = FROM WEEK SELECTED ITEM
        
            if(isset($this->settingVars->fromToDateRange)) {
                if ($data[0] == $fromLyDate[0])
                    $jsonOutput['selectedLyIndexFrom'] = $j;

                if ($data[0] == $toLyDate[0])
                    $jsonOutput['selectedLyIndexTo'] = $j;
            }
            
            $dateperiod      = date("jS M Y", strtotime($data[0]));
            $temp        = array();
            $temp['data']    = $data[0];
            $temp['label']   = $dateperiod;
            
            $temp['numdata'] = $j;
            $weeksList[] = $temp;
        }
        
        if ($returnArray)
            return $weeksList;
        else{
            $jsonOutput['yearWeekList'] = $weeksList;
            $jsonOutput['gridWeek'] = $weeksList;
        }
    }

    /****
    * Returns all existing year-Time_Selection_DataCollectors combination
    ****/
    function getAllPeriod(&$jsonOutput){
    
        $mydateSelect = $this->settingVars->getMydateSelect($this->settingVars->dateField, false);
        $query = "select YEAR(MAX(distinct ".$mydateSelect.")) as year FROM ".$this->settingVars->timeHelperTables.$this->settingVars->timeHelperLink;
        $result 	= $this->queryVars->queryHandler->runQuery($query,$this->queryVars->linkid,db\ResultTypes::$TYPE_OBJECT);
        
        $year = $result[0]['year'];
        
		//FINDING MAXIMUM AND MINIMUM WEEKS USING MAXIMUM YEAR
        $mydateSelect = $this->settingVars->getMydateSelect($this->settingVars->dateField, false);
		$query 		= "SELECT MAX(".$this->settingVars->periodField.") MAXPERIOD,".
					"MIN(".$this->settingVars->periodField.") MINPERIOD, year ".
					"FROM ".$this->settingVars->timeHelperTables.$this->settingVars->timeHelperLink.
					"AND YEAR(".$mydateSelect.")= $year GROUP BY year ";
		$result 	= $this->queryVars->queryHandler->runQuery($query,$this->queryVars->linkid,db\ResultTypes::$TYPE_OBJECT);
        
		$data 		= $result[0];
		$maxPeriod 	= (int)$data['MAXPERIOD'].$data['year'];
		$minPeriod 	= (int)$data['MINPERIOD'].$data['year'];

		//USEFUL WHEN DEBUGGING, DON'T DELETE  
		//print 'Max Week: '.$maxWeek." Min Week: ".$minWeek;
		//exit;
		
        /*$query = "SELECT ".$this->settingVars->periodField." AS PERIOD".
					" FROM ".$this->settingVars->timeHelperTables.$this->settingVars->timeHelperLink.
					"GROUP BY PERIOD ".
					"ORDER BY PERIOD DESC"; */
                    
		$query 		= "SELECT ".$this->settingVars->weekField." as week, ".$this->settingVars->yearField." as year, ".$this->settingVars->periodField." AS PERIOD".
					" FROM ".$this->settingVars->timeHelperTables.$this->settingVars->timeHelperLink.
					"GROUP BY week, year, PERIOD ".
					"ORDER BY year DESC, PERIOD DESC";                    
                    
		$result 	= $this->queryVars->queryHandler->runQuery($query,$this->queryVars->linkid,db\ResultTypes::$TYPE_ARRAY);
        
		$periodList 	= array();
        foreach($result as $j=>$data)
        {
            if ((int)$data[2].$data[1] == $minPeriod)
                $jsonOutput['selectedIndexFrom']    = $j; //MINIMUM WEEK OF MAXIMUM YEAR  = FROM WEEK SELECTED ITEM
            
            $jsonOutput['selectedIndexTo']      = 0;
			
			$temp 	     = array();
            //$week = ($data[0]<9) ? '0'.$data[0] : $data[0];
			$temp['data']    = $data[0].'-'.$data[1]; // year-week
			$temp['label']   = $data[2].'-'.$data[1];
			
			$temp['numdata'] = $j;
			$periodList[] = $temp;
		}
        
        $jsonOutput['gridWeek'] = $periodList;
    }

    /****
    * Returns all existing Seasonal Year-Time_Selection_DataCollectors combination
    ****/
    function getAllSeasonal(&$jsonOutput){
        $query = "SELECT ".$this->settingVars->weekField." as week, ".$this->settingVars->yearField." as year ".
                    " FROM ".$this->settingVars->timeHelperTables.$this->settingVars->timeHelperLink." AND ".$this->settingVars->yearField." <> 0 ".
                    " GROUP BY week, year".
                    " ORDER BY year DESC";

        $periodList = array();
        $result = $this->queryVars->queryHandler->runQuery($query,$this->queryVars->linkid,db\ResultTypes::$TYPE_ARRAY);
        foreach($result as $j => $data){
            $temp          = array();
            $temp['data']  = $data[0].'-'.$data[1];
            $temp['label'] = $data[0].'-'.$data[1];
            $temp['selected'] = false;
            if($j==0) {
                $maxYear = $data[1];
                $temp['selected'] = true;
            }
            $periodList[]  = $temp;
        }
        
        $this->settingVars->timeSelectionStyleDDArray = $periodList;
    }

    function getAllSeasonalHardStopDates(&$jsonOutput,$maxYear, $outputData = true){

        $fromYear       = $maxYear;
        $fromYear       = (isset($this->settingVars->fromToDateRange['fromYear']) && !empty($this->settingVars->fromToDateRange['fromYear'])) ? $this->settingVars->fromToDateRange['fromYear'] : $fromYear;
        
        $toYear         = (($this->settingVars->fromToDateRange['fromMonth']-$this->settingVars->fromToDateRange['toMonth']) > 0) ? $maxYear+1 : $maxYear;
        $tyFromDate     = $fromYear.'-'.$this->settingVars->fromToDateRange['fromMonth'].'-'.$this->settingVars->fromToDateRange['fromDay'];
        $tyToDate       = $toYear.'-'.$this->settingVars->fromToDateRange['toMonth'].'-'.$this->settingVars->fromToDateRange['toDay'];

        $query = "SELECT ADDDATE('".$tyFromDate."', INTERVAL @i:=@i+1 DAY) AS DAY
                    FROM (
                        SELECT a.a
                            FROM (SELECT 0 AS a UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) AS a
                        CROSS JOIN (SELECT 0 AS a UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) AS b
                        CROSS JOIN (SELECT 0 AS a UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) AS c
                    ) a
                    JOIN (SELECT @i := -1) r1 
                WHERE @i < DATEDIFF('".$tyToDate."', '".$tyFromDate."') ORDER BY DAY DESC";

        $redisCache = new utils\RedisCache($this->queryVars);
        $queryHash = md5($query);
        $redisOutput = $redisCache->checkAndReadByStaticHashFromCache('time_filter_data', $queryHash);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $redisCache->setDataForSubKeyHash($result, $queryHash);
        } else {
            $result = $redisOutput;
        }
        $jsonOutput['allSeasonalHardStopDatesHashKey'] = $queryHash;

        $dateList = array();
        $maxDateReached = false;
        $selectedIndex = 0;
        $this->settingVars->tyDates = $this->settingVars->hardStopDates = array();
        if (is_array($result) && !empty($result)) {
            foreach ($result as $j => $date) {
                $temp        = array();
                //$week = ($data[0]<9) ? '0'.$data[0] : $data[0];
                $temp['data']    = $date['DAY']; // year-week
                $temp['label']   = date('d-M-Y', strtotime($date['DAY']));
                
                $temp['numdata'] = $j;
                $dateList[] = $temp;

                if (isset($this->settingVars->fromToDateRange['maxDate']) && 
                    !empty($this->settingVars->fromToDateRange['maxDate']) && 
                    strtotime($date['DAY']) == strtotime($this->settingVars->fromToDateRange['maxDate'])) {
                    $selectedIndex = $j;
                }

                $maxDate = (isset($_REQUEST["toDate"]) && !empty($_REQUEST["toDate"])) ? 
                    $_REQUEST["toDate"] : ((isset($this->settingVars->fromToDateRange['maxDate']) && !empty($this->settingVars->fromToDateRange['maxDate'])) ? $this->settingVars->fromToDateRange['maxDate'] : '');
                if (!empty($maxDate) && strtotime($date['DAY']) <= strtotime($maxDate)) {
                    $this->settingVars->tyDates[] = $date['DAY'];
                }
                $this->settingVars->hardStopDates[] = $date['DAY'];
            }

            if (empty($this->settingVars->tyDates))
                $this->settingVars->tyDates = $this->settingVars->hardStopDates;
        }

        $this->settingVars->tyDates = array_reverse($this->settingVars->tyDates);
        $this->settingVars->hardStopDates = array_reverse($this->settingVars->hardStopDates);


        // LY CALCULATION STARTED
        $lyFromDate     = ($fromYear-1).'-'.$this->settingVars->fromToDateRange['fromMonth'].'-'.$this->settingVars->fromToDateRange['fromDay'];
        
        // if (isset($_REQUEST["toDate"]) && !empty($_REQUEST["toDate"])) {
        //     $tyToDate   = $_REQUEST["toDate"];
        // } else {
        //     $tyToDate       = $toYear.'-'.$this->settingVars->fromToDateRange['toMonth'].'-'.$this->settingVars->fromToDateRange['toDay'];
        // }
        $lyToDate       = ($toYear-1).'-'.$this->settingVars->fromToDateRange['toMonth'].'-'.$this->settingVars->fromToDateRange['toDay'];

        $query = "SELECT ADDDATE('".$lyFromDate."', INTERVAL @i:=@i+1 DAY) AS DAY
                    FROM (
                        SELECT a.a
                            FROM (SELECT 0 AS a UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) AS a
                        CROSS JOIN (SELECT 0 AS a UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) AS b
                        CROSS JOIN (SELECT 0 AS a UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) AS c
                    ) a
                    JOIN (SELECT @i := -1) r1 
                WHERE @i < DATEDIFF('".$lyToDate."', '".$lyFromDate."') ORDER BY DAY DESC";

        $redisCache = new utils\RedisCache($this->queryVars);
        $queryHash = md5($query);
        $redisOutput = $redisCache->checkAndReadByStaticHashFromCache('time_filter_data', $queryHash);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $redisCache->setDataForSubKeyHash($result, $queryHash);
        } else {
            $result = $redisOutput;
        }
        $jsonOutput['allSeasonalHardStopDatesHashKeyLy'] = $queryHash;

        $this->settingVars->lyDates = array();
        if (is_array($result) && !empty($result)) {
            $maxDate = (isset($_REQUEST["toDate"]) && !empty($_REQUEST["toDate"])) ? 
                $_REQUEST["toDate"] : ((isset($this->settingVars->fromToDateRange['maxDate']) && !empty($this->settingVars->fromToDateRange['maxDate'])) ? $this->settingVars->fromToDateRange['maxDate'] : '');
            $maxDatePart = explode("-", $maxDate);
            $maxLyDate = ($maxDatePart[0]-1)."-".$maxDatePart[1]."-".$maxDatePart[2];
            foreach ($result as $j => $date) {
                if (!empty($maxLyDate) && strtotime($date['DAY']) <= strtotime($maxLyDate)) {
                    $this->settingVars->lyDates[] = $date['DAY'];
                }
            }
        }
        $this->settingVars->lyDates = array_reverse($this->settingVars->lyDates);

        if ($outputData) {
            $jsonOutput['seasonalHardStopDates'] = $dateList;
            $jsonOutput['selectedIndexTo'] = $selectedIndex;
        }
    }

    /****
    * Returns only week based on client class configuration
    * Time_Selection_DataCollectors combination
    ****/
    function getOnlyWeek(&$jsonOutput){
        $gridWeek = $this->getAllWeek($jsonOutput, false, true);
        
        $weeksList  = array();
        if (is_array($this->settingVars->weekSelectionArray) && !empty($this->settingVars->weekSelectionArray) &&
            is_array($gridWeek) && !empty($gridWeek)) 
        {
            $cnt = 0;
            foreach ($this->settingVars->weekSelectionArray as $week => $weekLabel) {
                if (isset($gridWeek[$week-1])) {
                    $temp['data']    = $gridWeek[$week-1]['data'];
                    $temp['label']   = $weekLabel;
                    
                    $temp['numdata'] = $cnt++;
                    $weeksList[] = $temp;
                }
            }
        }

        $jsonOutput['gridWeek'] = $weeksList;
    }

    /****
    * Returns only week based on client class configuration
    * Time_Selection_DataCollectors combination
    ****/
    function getOnlyDays(&$jsonOutput, $jsonKeyPrefix = ''){
        $datesList = $this->getAllMydate($jsonOutput, true);
        
        $daysList  = array();
        if (is_array($this->settingVars->daysSelectionArray) && !empty($this->settingVars->daysSelectionArray) &&
            is_array($datesList) && !empty($datesList))
        {
            $cnt = 0;
            foreach ($this->settingVars->daysSelectionArray as $day => $dayLabel) {
                if (isset($datesList[$day-1])) {
                    $temp['data']    = $datesList[$day-1]['data'];
                    $temp['label']   = $dayLabel;
                    $temp['days']    = $day;
                    
                    $temp['numdata'] = $cnt++;
                    $temp['active'] = false;
                    $daysList[] = $temp;
                }
            }
            $daysList[count($daysList)-1]['active'] = true;
            $jsonOutput[$jsonKeyPrefix.'selectedIndexFrom']    = $daysList[count($daysList)-1]['numdata']; //MINIMUM WEEK OF MAXIMUM YEAR  = FROM WEEK SELECTED ITEM
        }

        $jsonOutput[$jsonKeyPrefix.'gridWeek'] = $daysList;
    }

    /*     * ***
     * Collects and returns a xml data structure of WEEK-YEAR
     * In an addition, it also includes FUTURE DATES for custom time selection
     * $year: 	Value of current year, usefull when calculating selected index of from and to combobox
     * *** */

    function getAllWeek_with_future_dates(&$jsonOutput, $jsonKeyPrefix = '', $includeDate = true) {

        $redisCache = new utils\RedisCache($this->queryVars);

        $mydateSelect = $this->settingVars->getMydateSelect($this->settingVars->dateField);
        $query = "SELECT " . $this->settingVars->yearperiod . " AS YEAR" .
                ",".$this->settingVars->weekperiod . " AS WEEK" .
                (($this->settingVars->dateField) ? "," . $mydateSelect . " " : " ") .
                "FROM " . $this->settingVars->timeHelperTables . $this->settingVars->timeHelperLink .
                "GROUP BY YEAR,WEEK " .
                "ORDER BY YEAR DESC,WEEK DESC";
        $queryHash = md5($query);
        $redisOutput = $redisCache->checkAndReadByStaticHashFromCache('time_filter_data', $queryHash);

        if ($redisOutput === false) {
            $dateList = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_ARRAY);
            $redisCache->setDataForSubKeyHash($dateList, $queryHash);
        } else {
            $dateList = $redisOutput;
        }


        $data = $dateList[0];
        if (isset($dateList[0][2]))
            $this->settingVars->latestMydate = $dateList[0][2];

        $maxYear = $data[0];

        $maxYearAllWeek = $yearList = array();
        if (is_array($dateList) && !empty($dateList)) {
            foreach ($dateList as $detail) {
                $yearList[$detail[0]] = $detail[0];

                if ($detail[0] == $maxYear) {
                    $maxYearAllWeek[] = $detail;
                }
            }
        }

        /*$query = "SELECT DISTINCT " . $this->settingVars->yearperiod . " FROM " . 
            $this->settingVars->timeHelperTables . $this->settingVars->timeHelperLink . " AND " . 
            $this->settingVars->yearperiod . ">1970 ORDER BY " . $this->settingVars->yearperiod . " DESC";
        $queryHash = md5($query);
        $redisOutput = $redisCache->checkAndReadByStaticHashFromCache('time_filter_data', $queryHash);

        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_ARRAY);
            $redisCache->setDataForSubKeyHash($result, $queryHash);
        } else {
            $result = $redisOutput;
        }*/

        foreach ($yearList as $key => $data) {
            for ($i = 52; $i >= 1; $i--) {
                $tempArr = array("year" => $data, "week" => $i, "yearweek" => $i . "-" . $data);
                $yearWeekArr[] = $tempArr;
            }
        }

        /*$query = "SELECT " . $this->settingVars->yearperiod . " AS YEAR" .
                ",MIN(" . $this->settingVars->weekperiod . ") AS WEEK " .
                "FROM " . $this->settingVars->timeHelperTables . $this->settingVars->timeHelperLink . " " .
                "AND " . $this->settingVars->yearperiod . " = (SELECT MIN(" . $this->settingVars->yearperiod . ") FROM " . $this->settingVars->timeHelperTables . $this->settingVars->timeHelperLink . ") " .
                "GROUP BY YEAR";
        $queryHash = md5($query);
        $redisOutput = $redisCache->checkAndReadByStaticHashFromCache('time_filter_data', $queryHash);

        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $redisCache->setDataForSubKeyHash($result, $queryHash);
        } else {
            $result = $redisOutput;
        }

        foreach ($result as $key => $data) {
            $minYear = $data['YEAR'];
            $minWeekOfMinYear = $data['WEEK'];
        }*/
        //print "developer working: ".$minYear."----".$minWeekOfMinYear;exit;

        $minCombination = $dateList[count($dateList)-1];
        $minYear = $minCombination[0];
        $minWeekOfMinYear = $minCombination[1];

        /*$query = "SELECT MAX(" . $this->settingVars->weekperiod . ")" .
                ",MIN(" . $this->settingVars->weekperiod . ") " .
                "FROM " . $this->settingVars->timeHelperTables . $this->settingVars->timeHelperLink . "AND " . $this->settingVars->yearperiod . "=" . filters\timeFilter::$ToYear;
        $queryHash = md5($query);
        $redisOutput = $redisCache->checkAndReadByStaticHashFromCache('time_filter_data', $queryHash);

        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_ARRAY);
            $redisCache->setDataForSubKeyHash($result, $queryHash);
        } else {
            $result = $redisOutput;
        }

        $data = $result[0];
        $maxWeek = $data[0];
        $minWeek = $data[1];*/
        //print "developer working: ".$minWeek."----".$maxWeek;exit;

        $maxYearAllWeek = utils\SortUtility::sort2DArray($maxYearAllWeek, 1, utils\SortTypes::$SORT_ASCENDING);

        $maxCombination = $maxYearAllWeek[count($maxYearAllWeek)-1];
        $minCombination = $maxYearAllWeek[0];
        $maxWeek = $maxCombination[1];
        $minWeek = $minCombination[1];

        /*$query = "SELECT " . $this->settingVars->yearperiod .
                "," . $this->settingVars->weekperiod .
                (($this->settingVars->dateField) ? "," . $mydateSelect . " " : " ") .
                "FROM " . $this->settingVars->timeHelperTables . $this->settingVars->timeHelperLink . " AND " . $this->settingVars->yearperiod . ">1970 " .
                "GROUP BY " . $this->settingVars->yearperiod . "," . $this->settingVars->weekperiod . " " .
                "ORDER BY " . $this->settingVars->yearperiod . " DESC," . $this->settingVars->weekperiod . " DESC";*/

        /*$mydateSelect = $this->settingVars->getMydateSelect($this->settingVars->dateField);
        $query = "SELECT " . $this->settingVars->yearperiod . " AS YEAR" .
                ",".$this->settingVars->weekperiod . " AS WEEK" .
                (($this->settingVars->dateField) ? "," . $mydateSelect . " " : " ") .
                "FROM " . $this->settingVars->timeHelperTables . $this->settingVars->timeHelperLink .
                "GROUP BY YEAR,WEEK " .
                "ORDER BY YEAR DESC,WEEK DESC";
        $queryHash = md5($query);
        $redisOutput = $redisCache->checkAndReadByStaticHashFromCache('time_filter_data', $queryHash);

        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_ARRAY);
            $redisCache->setDataForSubKeyHash($result, $queryHash);
        } else {
            $result = $redisOutput;
        }*/

        $mydateArr = array();
        foreach ($dateList as $key => $data) {
            $mydate = date("jS M Y", strtotime($data[2]));
            $mydateArr[$data[0]][$data[1]] = $mydate;
        }

        for ($i = 0; $i < count($yearWeekArr); $i++) {
            if ($minYear == $yearWeekArr[$i]['year'] && $minWeekOfMinYear == $yearWeekArr[$i]['week']) {
                $minCounter = $i;
                break;
            }
        }


        for ($i = 0; $i <= $minCounter; $i++) {
            $mydateVal = "";
            if (key_exists($yearWeekArr[$i]['year'], $mydateArr)) {
                if (key_exists($yearWeekArr[$i]['week'], $mydateArr[$yearWeekArr[$i]['year']]))
                    $mydateVal = $mydateArr[$yearWeekArr[$i]['year']][$yearWeekArr[$i]['week']];
            }

            if ($minWeek == $yearWeekArr[$i]['week'] && filters\timeFilter::$FromYear == $yearWeekArr[$i]['year'])
                $jsonOutput[$jsonKeyPrefix.'selectedIndexFrom'] = $i;

            if ($maxWeek == $yearWeekArr[$i]['week'] && filters\timeFilter::$ToYear == $yearWeekArr[$i]['year'])
                $jsonOutput[$jsonKeyPrefix.'selectedIndexTo'] = $i;

            if ($minWeek == $yearWeekArr[$i]['week'] && (filters\timeFilter::$FromYear-1) == $yearWeekArr[$i]['year'])
                $jsonOutput[$jsonKeyPrefix.'selectedLyIndexFrom'] = $i;

            if ($maxWeek == $yearWeekArr[$i]['week'] && (filters\timeFilter::$ToYear-1) == $yearWeekArr[$i]['year'])
                $jsonOutput[$jsonKeyPrefix.'selectedLyIndexTo'] = $i;

            if ($includeDate)
                if($mydateVal != '')
                    $mydateVal = " (".$mydateVal.")";
                else
                    $mydateVal = '';
            else
                $mydateVal = "";

            if ($mydateVal != '') {
                $temp = array(
                    'data' => $yearWeekArr[$i]['yearweek']
                    , 'label' => $yearWeekArr[$i]['yearweek'] . $mydateVal
                    , 'numdata' => $i
                );
                $jsonOutput[$jsonKeyPrefix.'gridWeek'][] = $temp;
            } else {
                $temp = array(
                    'data' => $yearWeekArr[$i]['yearweek']
                    , 'label' => $yearWeekArr[$i]['yearweek']
                    , 'numdata' => $i
                );
                $jsonOutput[$jsonKeyPrefix.'gridWeek'][] = $temp;
            }
        }
    }

    /*     * ***
     * Collects and returns a xml data structure of MONTH-YEAR
     * In an addition, it also includes FUTURE DATES for custom time selection
     * $year: 	Value of current year, usefull when calculating selected index of from and to combobox
     * *** */

    function getAllMonth_with_future_dates(&$jsonOutput, $jsonKeyPrefix = '', $includeDate = true) {
        $yearMonthArr = array();

        $query = "SELECT DISTINCT " . $this->settingVars->yearperiod . " " .
                "FROM " . $this->settingVars->timeHelperTables . $this->settingVars->timeHelperLink .
                " AND " . $this->settingVars->yearperiod . ">1970 " .
                "ORDER BY " . $this->settingVars->yearperiod . " DESC";
        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_ARRAY);
        foreach ($result as $key => $data) {
            for ($i = 12; $i >= 1; $i--) {
                $tempArr = array("year" => $data[0], "month" => $i, "yearmonth" => $i . "-" . $data[0]);
                $yearMonthArr[] = $tempArr;
            }
        }

        $query = "SELECT " . $this->settingVars->yearperiod . " AS YEAR" .
                ",MIN(" . $this->settingVars->monthperiod . ") AS MONTH " .
                "FROM " . $this->settingVars->timeHelperTables . $this->settingVars->timeHelperLink .
                " AND " . $this->settingVars->yearperiod . " = (SELECT MIN(" . $this->settingVars->yearperiod . ") FROM " . $this->settingVars->timeHelperTables . $this->settingVars->timeHelperLink . ") " .
                "GROUP BY YEAR";
        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
        foreach ($result as $key => $data) {
            $minYear = $data['YEAR'];
            $minMonthOfMinYear = $data['MONTH'];
        }
        //print "developer working: ".$minYear."----".$minWeekOfMinYear;exit;


        $query = "SELECT MIN(" . $this->settingVars->monthperiod . ")" .
                ",MAX(" . $this->settingVars->monthperiod . ") " .
                "FROM " . $this->settingVars->timeHelperTables . $this->settingVars->timeHelperLink .
                " AND " . $this->settingVars->yearperiod . "=" . filters\timeFilter::$ToYear;
        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_ARRAY);
        $data = $result[0];
        $minMonth = $data[0];
        $maxMonth = $data[1];

        $mydateSelect = $this->settingVars->getMydateSelect($this->settingVars->dateField, false);
        $monthNameArr = array();
        $query = "SELECT " . $this->settingVars->yearperiod . " AS YEAR" .
                "," . $this->settingVars->monthperiod . " AS MONTH" .
                ( ($this->settingVars->dateField != "" ) ?  ",DATE_FORMAT(MAX(" . $mydateSelect . "),'%M') AS MYDATE " : "" ).
                " FROM " . $this->settingVars->timeHelperTables . $this->settingVars->timeHelperLink .
                " AND " . $this->settingVars->yearperiod . ">1970 " .
                "GROUP BY YEAR,MONTH " .
                "ORDER BY YEAR DESC,MONTH DESC";
        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_ARRAY);
        foreach ($result as $key => $data) {
            $monthNameArr[$data[0]][$data[1]] = $data[2];
        }

        for ($i = 0; $i < count($yearMonthArr); $i++) {
            if ($minYear == $yearMonthArr[$i]['year'] && $minMonthOfMinYear == $yearMonthArr[$i]['month']) {
                $minCounter = $i;
                break;
            }
        }

        for ($i = 0; $i <= $minCounter; $i++) {
            if ($minMonth == $yearMonthArr[$i]['month'] && filters\timeFilter::$ToYear == $yearMonthArr[$i]['year'])
                $jsonOutput[$jsonKeyPrefix.'selectedIndexFrom'] = $i;

            if ($maxMonth == $yearMonthArr[$i]['month'] && filters\timeFilter::$ToYear == $yearMonthArr[$i]['year'])
                $jsonOutput[$jsonKeyPrefix.'selectedIndexTo'] = $i;

            if ($minMonth == $yearMonthArr[$i]['month'] && (filters\timeFilter::$ToYear-1) == $yearMonthArr[$i]['year'])
                $jsonOutput[$jsonKeyPrefix.'selectedLyIndexFrom'] = $i;

            if ($maxMonth == $yearMonthArr[$i]['month'] && (filters\timeFilter::$ToYear-1) == $yearMonthArr[$i]['year'])
                $jsonOutput[$jsonKeyPrefix.'selectedLyIndexTo'] = $i;                
                
            $temp = array(
                'data' => $yearMonthArr[$i]['yearmonth']
                , 'label' => $yearMonthArr[$i]['yearmonth']
                , 'numdata' => $i
            );
            $jsonOutput[$jsonKeyPrefix.'gridWeek'][] = $temp;
        }
    }

    /*     * ***
     * Collects and returns a xml data structure of DATE
     * *** */

    function getAllDates(&$jsonOutput) {
        $dateList = array();
        if (!empty($this->settingVars->dateField) && !empty($this->settingVars->yearperiod) && !empty($this->settingVars->weekperiod)) 
        {
            $redisCache = new utils\RedisCache($this->queryVars);

            $mydateSelect = $this->settingVars->getMydateSelect($this->settingVars->dateField);
            $query = "SELECT " . $this->settingVars->yearperiod . " AS YEAR" .
                    ",".$this->settingVars->weekperiod . " AS WEEK" .
                    (($this->settingVars->dateField) ? ",".$mydateSelect." " : " ") .
                    "FROM " . $this->settingVars->timeHelperTables . $this->settingVars->timeHelperLink .
                    "GROUP BY YEAR,WEEK " .
                    "ORDER BY YEAR DESC,WEEK DESC";
            $queryHash = md5($query);
            $redisOutput = $redisCache->checkAndReadByStaticHashFromCache('time_filter_data', $queryHash);

            if ($redisOutput === false) {
                $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_ARRAY);
                $redisCache->setDataForSubKeyHash($result, $queryHash);
            } else {
                $result = $redisOutput;
            }


			// $mydateSelect = $this->settingVars->getMydateSelect($this->settingVars->dateField, false);
   //          $query = "SELECT " . $mydateSelect . " AS MYDATE " .
   //                  "FROM " . $this->settingVars->timeHelperTables . $this->settingVars->timeHelperLink .
   //                  "GROUP BY MYDATE " .
   //                  "ORDER BY MYDATE DESC";
   //          $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $dateList = array();
            foreach ($result as $key => $data) {
                $mydate = date("jS M Y", strtotime($data[2]));
                $temp = array(
                    'data' => $data[2]
                    , 'label' => $mydate
                );
                $dateList[] = $temp;
            }
        }
        $jsonOutput['dateList'] = $dateList;
    }

}

?>
