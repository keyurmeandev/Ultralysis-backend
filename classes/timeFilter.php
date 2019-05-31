<?php
namespace filters;

use projectsettings;
use db;
use utils;
use datahelper;

class timeFilter
{
    public static $FromWeek     = "";
    public static $FromWeek_PRV = "";
    
    public static $FromYear     = "";
    public static $FromYear_PRV = "";
    
    public static $ToWeek       = "";
    public static $ToWeek_PRV   = "";
    
    public static $ToYear       = "";
    public static $ToYear_PRV   = "";
    
    public static $tyTimeframeRange  = "";
    public static $lyTimeframeRange  = "";

    public static $tyWeekRange  = "";
    public static $lyWeekRange  = "";
    public static $ppWeekRange  = "";

    public static $daysTimeframe  = '';
    public static $tyDaysRange  = "";
    public static $lyDaysRange  = "";
    public static $mydateRange  = '';
    
    public static $ty14DaysRange  = "";

    public static $FromSeason  = "";

    public static $totalWeek;
    
    public static $tyFromDate;
    public static $tyToDate;
    public static $lyFromDate;
    public static $lyToDate;
    public static $seasonalTimeframeExtraWhere;

    public static $SeasonalTimeframeID;
    
    public static function getSlice($settingVars){
        $data                   = explode("-", $_REQUEST["FromWeek"]);
        self::$FromWeek         = $data[0];
        self::$FromYear         = $data[1];
    
        if (isset($_REQUEST["ToWeek"])) {
            $data                   = explode("-", $_REQUEST["ToWeek"]);
            self::$ToWeek           = $data[0];
            self::$ToYear           = $data[1];
        }
        
        //PREPARE TY AND LY WEEKRANGE TO USE IN QUERY
        self::prepareTyLyWeekRange($settingVars);
        
        //PREPARE PREVIOUS PERIOD VARIABLES WHEN NEEDED
        if(isset($_REQUEST["FromWeekPrv"]) && isset($_REQUEST["ToWeekPrv"]))
        {
            $data                   = explode("-", $_REQUEST["FromWeekPrv"]);
            self::$FromWeek_PRV     = $data[0];
            self::$FromYear_PRV     = $data[1];
        
            $data                   = explode("-", $_REQUEST["ToWeekPrv"]);
            self::$ToWeek_PRV       = $data[0];
            self::$ToYear_PRV       = $data[1];
        
            self::getExtraSlice($settingVars);
       }
    }

    public static function getSliceMonth($settingVars){
        $data                   = explode("-", $_REQUEST["FromWeek"]);
        self::$FromWeek         = $data[0];
        self::$FromYear         = $data[1];
    
        $data                   = explode("-", $_REQUEST["ToWeek"]);
        self::$ToWeek           = $data[0];
        self::$ToYear           = $data[1];
        
        //PREPARE TY AND LY WEEKRANGE TO USE IN QUERY
        self::prepareTyLyMonthRange($settingVars);
        
        if(isset($_REQUEST["FromWeekPrv"]) && isset($_REQUEST["ToWeekPrv"]))
        {
            $data                   = explode("-", $_REQUEST["FromWeekPrv"]);
            self::$FromWeek_PRV     = $data[0];
            self::$FromYear_PRV     = $data[1];
        
            $data                   = explode("-", $_REQUEST["ToWeekPrv"]);
            self::$ToWeek_PRV       = $data[0];
            self::$ToYear_PRV       = $data[1];
        
            self::getExtraSlice($settingVars);
       }        
        
    }

    /****
     * This is used for structure type MEASURE_AND_TYLY_AS_COLUMN
     * For Nielsen type project structure
     ****/
    public static function getSliceTimeframe($settingVars, $timeframe = '') {
        if (!empty($timeframe))
            self::$daysTimeframe = $timeframe;
        else
            self::$daysTimeframe = $_REQUEST['timeFrame'];

        self::prepareTyLyTimeframeRange($settingVars);
    }

    /****
     * This is used for structure type MEASURE_AND_TYLY_AS_COLUMN
     * For Nielsen type project structure
     ****/
    public static function getSliceSeasonal($settingVars, $timeframe = '') {
        //$data                   = explode("-", $_REQUEST["FromSeason"]);
        $data                   = explode("-", $_REQUEST["timeFrame"]);
        self::$FromSeason       = $data[0];
        self::$FromYear         = $data[1];

        self::prepareTyLySeasonalRange($settingVars);
    }

    public static function getDateSlice($settingVars){
    
        self::$tyFromDate   = $_REQUEST["FromWeek"];
    
        if (isset($_REQUEST["ToWeek"]))
            self::$tyToDate = $_REQUEST["ToWeek"];
        
        if(isset($_REQUEST["FromWeekPrv"]) && isset($_REQUEST["ToWeekPrv"]))
        {
            self::$lyFromDate   = $_REQUEST["FromWeekPrv"];
            self::$lyToDate     = $_REQUEST["ToWeekPrv"];
        }
        
        self::prepareTyLyDateRange($settingVars);
    }
    
    /****
     * Prepares Ty and Ly weekrange to use in query, This is used for structure type MEASURE_AND_TYLY_AS_COLUMN
     * For Nielsen type project structure
     ****/
    public static function prepareTyLyTimeframeRange($settingVars){
        //PREPARE TY-WEEK-RANGE AND LY-WEEK-RANGE
        self::$tyWeekRange  = ' 1 = 1 ';
        self::$lyWeekRange  = ' 1 = 1 ';

        self::$tyTimeframeRange  = (self::$daysTimeframe != 'YTD') ? 'L'.self::$daysTimeframe.'_TY' : self::$daysTimeframe.'_TY';
        self::$lyTimeframeRange  = (self::$daysTimeframe != 'YTD') ? 'L'.self::$daysTimeframe.'_LY' : self::$daysTimeframe.'_LY';
    }
    
    /****
     * Prepares Ty and Ly weekrange to use in query, given that Week and Year vars are set [$ToWeek,$FromWee.....]
     ****/
    public static function prepareTyLyWeekRange($settingVars){
        //PREPARE TY-WEEK-RANGE AND LY-WEEK-RANGE
        $connect = self::$FromYear==self::$ToYear ? " AND ": " OR ";
        $yearDiff = (int) self::$ToYear - (int) self::$FromYear;
        if(self::$FromYear!=self::$ToYear && $yearDiff > 1){
            $stYear = (int) self::$FromYear;
            $edYear = (int) self::$ToYear;
            $LyYearDiff = $yearDiff+1;
            $prepareTyMissingYear = $prepareLyMissingYear = '';
            for($i=$stYear+1;$i<$edYear;$i++){
                $prepareTyMissingYear.= "(".$settingVars->yearperiod."=".$i.")".$connect;
                $prepareLyMissingYear.= "(".$settingVars->yearperiod."=".($i-$LyYearDiff).")".$connect;
            }
            self::$tyWeekRange  = " ((". $settingVars->yearperiod ."=". self::$FromYear ." AND ". $settingVars->weekperiod .">=". self::$FromWeek .") ".$connect." ".$prepareTyMissingYear." (". $settingVars->yearperiod ."=". self::$ToYear ." AND ". $settingVars->weekperiod ."<=". self::$ToWeek .")) ";
            
            self::$lyWeekRange  = " ((". $settingVars->yearperiod ."=". (self::$FromYear-$LyYearDiff) ." AND ". $settingVars->weekperiod .">=". self::$FromWeek .") ".$connect." ".$prepareLyMissingYear." (". $settingVars->yearperiod ."=". (self::$ToYear-$LyYearDiff) ." AND ". $settingVars->weekperiod ."<=". self::$ToWeek .")) ";
            
        }else{
            self::$tyWeekRange  = " ((". $settingVars->yearperiod ."=". self::$FromYear ." AND ". $settingVars->weekperiod .">=". self::$FromWeek .")$connect(". $settingVars->yearperiod ."=". self::$ToYear ." AND ". $settingVars->weekperiod ."<=". self::$ToWeek .")) ";
            self::$lyWeekRange  = " ((". $settingVars->yearperiod ."=". (self::$FromYear-1) ." AND ". $settingVars->weekperiod .">=". self::$FromWeek .")$connect(". $settingVars->yearperiod ."=". (self::$ToYear-1) ." AND ". $settingVars->weekperiod ."<=". self::$ToWeek .")) ";
        }
    }
    
    /****
     * Prepares Ty and Ly weekrange to use in query, given that Week and Year vars are set [$ToWeek,$FromWee.....]
     ****/
    public static function prepareTyLyMonthRange($settingVars){
        //PREPARE TY-MONTH -RANGE AND LY-MONTH-RANGE
        $connect        = self::$FromYear==self::$ToYear ? " AND ": " OR ";
        self::$tyWeekRange  = " ((". $settingVars->yearperiod ."=". self::$FromYear ." AND ". $settingVars->monthperiod .">=". self::$FromWeek .")$connect(". $settingVars->yearperiod ."=". self::$ToYear ." AND ". $settingVars->monthperiod ."<=". self::$ToWeek .")) ";
        self::$lyWeekRange  = " ((". $settingVars->yearperiod ."=". (self::$FromYear-1) ." AND ". $settingVars->monthperiod .">=". self::$FromWeek .")$connect(". $settingVars->yearperiod ."=". (self::$ToYear-1) ." AND ". $settingVars->monthperiod ."<=". self::$ToWeek .")) ";
    }
    
    /****
     * Prepares Ty and Ly weekrange to use in query, given that Week and Year vars are set [$ToWeek,$FromWee.....]
     ****/
    public static function prepareTyLySeasonalRange($settingVars){
        
        $fromYear       = self::$FromYear;
        $fromYear       = (isset($settingVars->fromToDateRange['fromYear']) && !empty($settingVars->fromToDateRange['fromYear'])) ? $settingVars->fromToDateRange['fromYear'] : self::$FromYear;
        $toYear         = (($settingVars->fromToDateRange['fromMonth']-$settingVars->fromToDateRange['toMonth']) > 0) ? self::$FromYear+1 : self::$FromYear;
        $tyFromDate     = $fromYear.'-'.$settingVars->fromToDateRange['fromMonth'].'-'.$settingVars->fromToDateRange['fromDay'];
        $lyFromDate     = ($fromYear-1).'-'.$settingVars->fromToDateRange['fromMonth'].'-'.$settingVars->fromToDateRange['fromDay'];
        
        if (isset($_REQUEST["toDate"]) && !empty($_REQUEST["toDate"])) {
            $tyToDate   = $_REQUEST["toDate"];
            /*$tyToArr    = explode('-', $_REQUEST["toDate"]);
            $tyToArr[0] = $tyToArr[0]-1;
            $lyToDate   = implode('-', $tyToArr);*/
        } else {
            $tyToDate   = $settingVars->fromToDateRange['maxDate'];
            /*$tyToDate   = $toYear.'-'.$settingVars->fromToDateRange['toMonth'].'-'.$settingVars->fromToDateRange['toDay'];
            $lyToDate   = ($toYear-1).'-'.$settingVars->fromToDateRange['toMonth'].'-'.$settingVars->fromToDateRange['toDay'];*/
        }
        $tyToArr    = explode('-', $tyToDate);
        $tyToArr[0] = $tyToArr[0]-1;
        $lyToDate   = implode('-', $tyToArr);

        if (isset($_REQUEST['facings']) && !empty($_REQUEST['facings'])) {
            $sign = ($_REQUEST['facings'] >= 0) ? " + " : " - ";
            $lyFromDate = date('Y-m-d', strtotime($lyFromDate . $sign . abs($_REQUEST['facings']) . ' days'));
            $lyToDate = date('Y-m-d', strtotime($lyToDate . $sign . abs($_REQUEST['facings']) . ' days'));
        }

        self::$tyWeekRange  = " ((". $settingVars->dateField ." BETWEEN '". $tyFromDate ."' AND '". $tyToDate ."') AND ". $settingVars->weekField ."='".self::$FromSeason."' )";
        self::$lyWeekRange  = " ((". $settingVars->dateField ." BETWEEN '". $lyFromDate ."' AND '". $lyToDate ."')  AND ". $settingVars->weekField ."='".self::$FromSeason."' )";
    }


    /****
     * Prepares Ty and Ly mydate range to use in query, given that timeframe vars are set [$daysTimeframe.....]
     ****/
    public static function prepareTyLyMydateRange($settingVars){
        self::$daysTimeframe = $_REQUEST['timeFrame'];
        
        self::$tyDaysRange = self::getPeriodWithinRange(0, self::$daysTimeframe, $settingVars);
        self::$tyWeekRange = (!empty(self::$tyDaysRange)) ? "( (".$settingVars->period." IN ('" . implode("','", self::$tyDaysRange) . "')) )" : "";

        self::$lyDaysRange = self::getPeriodWithinRange(self::$daysTimeframe, self::$daysTimeframe, $settingVars);
        self::$lyWeekRange = (!empty(self::$lyDaysRange)) ? "( (".$settingVars->period." IN ('" . implode("','", self::$lyDaysRange) . "')) )" : "";
    }

    /****
     * Prepares Ty and Ly mydate range to use in query, given that timeframe vars are set [$daysTimeframe.....]
     ****/
    public static function prepareTyLyDaysRange($settingVars){
        self::$tyWeekRange = (!empty(self::$tyDaysRange)) ? "( (".$settingVars->dateField." IN ('" . implode("','", self::$tyDaysRange) . "')) )" : "";
        self::$lyWeekRange = (!empty(self::$lyDaysRange)) ? "( (".$settingVars->dateField." IN ('" . implode("','", self::$lyDaysRange) . "')) )" : "";
    }

    /****
     * Prepares Ty and Ly mydate range to use in query, given that timeframe vars are set [$daysTimeframe.....]
     ****/
    public static function prepareTyLyDateRange($settingVars)
    {
        $mydateSelect = $settingVars->getMydateSelect($settingVars->dateField, false);
        self::$tyWeekRange  = " (". $mydateSelect ." BETWEEN '". self::$tyFromDate ."' AND '". self::$tyToDate ."') ";
        self::$lyWeekRange = "";
        
        if(isset(self::$lyFromDate) && !empty(self::$lyFromDate) && isset(self::$lyToDate) && !empty(self::$lyToDate))
            self::$lyWeekRange = " (". $mydateSelect ." BETWEEN '". self::$lyFromDate ."' AND '". self::$lyToDate ."') ";
        else
            self::$lyWeekRange = "";
    }
    
    /****
    * Finds out Maximum and Minimum week of current year and set time vars .. [$FromWeek,$ToWeek,$FromYear,$ToYear]
    * and calls prepareTyLyWeekRange function to prepare $tyWeekRange and $lyWeekRange
    ****/
    public static function getYTD($settingVars){
        $queryVars  = projectsettings\settingsGateway::getInstance();
        
        $redisCache = new utils\RedisCache($queryVars);

        $mydateSelect = $settingVars->getMydateSelect($settingVars->dateField);
        $query = "SELECT " . $settingVars->yearperiod . " AS YEAR" .
                ",".$settingVars->weekperiod . " AS WEEK" .
                (($settingVars->dateField) ? ",".$mydateSelect." " : " ") .
                "FROM " . $settingVars->timeHelperTables . $settingVars->timeHelperLink .
                "GROUP BY YEAR,WEEK " .
                "ORDER BY YEAR DESC,WEEK DESC";
        $queryHash = md5($query);
        $redisOutput = $redisCache->checkAndReadByStaticHashFromCache('time_filter_data', $queryHash);

        if ($redisOutput === false) {
            $dateList = $queryVars->queryHandler->runQuery($query, $queryVars->linkid, db\ResultTypes::$TYPE_ARRAY);
            $redisCache->setDataForSubKeyHash($dateList, $queryHash);
        } else {
            $dateList = $redisOutput;
        }

        
        /*//COLLECT CURRENT YEAR
        $query  = "SELECT MAX(".$settingVars->yearperiod.") FROM ".$settingVars->timeHelperTables . $settingVars->timeHelperLink;
        $queryHash = md5($query);
        $redisOutput = $redisCache->checkAndReadByStaticHashFromCache('time_filter_data', $queryHash);

        if ($redisOutput === false) {
            $result = $queryVars->queryHandler->runQuery($query,$queryVars->linkid,db\ResultTypes::$TYPE_ARRAY);
            $redisCache->setDataForSubKeyHash($result, $queryHash);
        } else {
            $result = $redisOutput;
        }

        $data   = $result[0];*/

        $data = $dateList[0];
        $maxYear = $data[0];
        self::$ToYear   = $data[0];
        self::$FromYear = self::$ToYear;

        $settingVars->maxYearWeekCombination = $data;
        
        /*//MAXIMUM AND MINIMUM YEAR
        $query  = "SELECT MAX(".$settingVars->weekperiod.")".
                   ",MIN(".$settingVars->weekperiod.") ".
                   "FROM ".$settingVars->timeHelperTables.$settingVars->timeHelperLink."AND ".$settingVars->yearperiod."=".self::$ToYear;
        $queryHash = md5($query);
        $redisOutput = $redisCache->checkAndReadByStaticHashFromCache('time_filter_data', $queryHash);

        if ($redisOutput === false) {
            $result = $queryVars->queryHandler->runQuery($query,$queryVars->linkid,db\ResultTypes::$TYPE_ARRAY);
            $redisCache->setDataForSubKeyHash($result, $queryHash);
        } else {
            $result = $redisOutput;
        }
        
        $data   = $result[0];*/

        $maxYearAllWeek = array();
        if (is_array($dateList) && !empty($dateList)) {
            foreach ($dateList as $dateDetail) {
                if ($dateDetail[0] == $maxYear) {
                    $maxYearAllWeek[] = $dateDetail;
                }
            }
        }

        $maxYearAllWeek = utils\SortUtility::sort2DArray($maxYearAllWeek, 1, utils\SortTypes::$SORT_ASCENDING);

        $maxCombination = $maxYearAllWeek[count($maxYearAllWeek)-1];
        $minCombination = $maxYearAllWeek[0];
        $maxWeek = $maxCombination[1];
        $minWeek = $minCombination[1];

        if(empty($_GET['ignoreLatest_N_Weeks']) || $_GET['ignoreLatest_N_Weeks'] == 0){
            self::$ToWeek   = $maxWeek;
            self::$FromWeek = $minWeek;
        }else{
            self::$FromWeek = $minWeek;
            $query      = "SELECT DISTINCT(".$settingVars->weekperiod.") AS WEEKS ".
                            "FROM ".$settingVars->timeHelperTables . $settingVars->timeHelperLink." AND ".$settingVars->yearperiod."=".self::$ToYear." ".
                            "ORDER BY WEEKS DESC ".
                            "LIMIT ".$_GET['ignoreLatest_N_Weeks'].",1";
            $result         = $queryVars->queryHandler->runQuery($query,$queryVars->linkid,db\ResultTypes::$TYPE_ARRAY);
            self::$ToWeek   = $result[0][0];
        }

        //PREPARE TY AND LY WEEKRANGE TO USE IN QUERY
        self::prepareTyLyWeekRange($settingVars);

        //USEFUL WHEN DEBUGGING, DON'T DELETE
        //print 'Max Week: '.self::$ToWeek." Min Week: ".self::$FromWeek;
        //exit; 
    }
    
    /****
    * Finds out Maximum and Minimum week of current year and set time vars .. [$FromWeek,$ToWeek,$FromYear,$ToYear]
    * and calls prepareTyLyWeekRange function to prepare $tyWeekRange and $lyWeekRange
    ****/
    public static function getYTDMonth($settingVars){
        $queryVars  = projectsettings\settingsGateway::getInstance();
        
        //COLLECT CURRENT YEAR
        $query  = "SELECT MAX(".$settingVars->yearperiod.") FROM ".$settingVars->timeHelperTables. " " . $settingVars->timeHelperLink;
        $result = $queryVars->queryHandler->runQuery($query,$queryVars->linkid,db\ResultTypes::$TYPE_ARRAY);
        $data   = $result[0];
        self::$ToYear   = $data[0];
        self::$FromYear = self::$ToYear;
        
        //MAXIMUM AND MINIMUM YEAR
        $query  = "SELECT MAX(".$settingVars->monthperiod.")".
                   ",MIN(".$settingVars->monthperiod.") ".
                   "FROM ".$settingVars->timeHelperTables.$settingVars->timeHelperLink." AND ".$settingVars->yearperiod."=".self::$ToYear;
        $result = $queryVars->queryHandler->runQuery($query,$queryVars->linkid,db\ResultTypes::$TYPE_ARRAY);
        $data   = $result[0];
        self::$ToWeek   = $data[0];
        self::$FromWeek = $data[1];
           
        //PREPARE TY AND LY WEEKRANGE TO USE IN QUERY
        self::prepareTyLyMonthRange($settingVars);

        //USEFUL WHEN DEBUGGING, DON'T DELETE
        //print 'Max Week: '.self::$ToWeek." Min Week: ".self::$FromWeek;
        //exit; 
    }

    public static function getDays($settingVars) {
        $json="";
        $timeSelectionDataCollectors = new datahelper\Time_Selection_DataCollectors($settingVars);
        $datesList = $timeSelectionDataCollectors->getAllMydate($json, true);
        
        if (is_array($datesList) && !empty($datesList) && is_array($settingVars->daysSelectionArray) && !empty($settingVars->daysSelectionArray)) {
            $daysInterval = array_keys($settingVars->daysSelectionArray)[count($settingVars->daysSelectionArray)-1];
            foreach ($datesList as $key => $date) {

                if($key < 14){
                    $ty14Range[] = $date['data'];
                }

                if ($key < $daysInterval) {
                    self::$daysTimeframe = $date['numdata']+1;
                    $tyRange[] = $date['data'];
                }

                if ($key >= $daysInterval && $key < $daysInterval*2)
                    $lyRange[] = $date['data'];
            }
            self::$tyDaysRange = $tyRange;
            self::$lyDaysRange = $lyRange;
            self::$ty14DaysRange = $ty14Range;

            //PREPARE TY AND LY WEEKRANGE TO USE IN QUERY
            self::prepareTyLyDaysRange($settingVars);
        }
    }

    public static function getDaysSlice($settingVars) {
        $json="";
        $timeSelectionDataCollectors = new datahelper\Time_Selection_DataCollectors($settingVars);
        $datesList = $timeSelectionDataCollectors->getAllMydate($json, true);
        
        if (is_array($datesList) && !empty($datesList) && is_array($settingVars->daysSelectionArray) && !empty($settingVars->daysSelectionArray)) {
            $data = $_REQUEST["FromDate"];
            foreach ($datesList as $key => $date) {

                if($key < 14){
                    $ty14Range[] = $date['data'];
                }

                if ($tyRangeReached && $key < count($tyRange)*2)
                    $lyRange[] = $date['data'];

                if (!$tyRangeReached) {
                    $tyRange[] = $date['data'];
                    if ($date['data'] == $data) {
                        $tyRangeReached = true;
                        self::$daysTimeframe = $date['numdata']+1;
                    }
                }
            }
            self::$daysTimeframe = (isset(self::$daysTimeframe) && !empty(self::$daysTimeframe)) ? self::$daysTimeframe : count($tyRange);
            self::$tyDaysRange = $tyRange;
            self::$lyDaysRange = $lyRange;
            self::$ty14DaysRange = $ty14Range;

            //PREPARE TY AND LY WEEKRANGE TO USE IN QUERY
            self::prepareTyLyDaysRange($settingVars);
        }
    }
    
    /*****
     * If time selection mode of client app [TSM] is set to 2, set ppWeekRange as lyWeekRange
     ****/
    public static function getExtraSlice($settingVars){     
    $connect        = self::$FromYear_PRV==self::$ToYear_PRV ? " AND ":" OR ";
    self::$ppWeekRange  = " ((". $settingVars->yearperiod ."=". self::$FromYear_PRV ." AND ". $settingVars->weekperiod .">=". self::$FromWeek_PRV .")$connect(". $settingVars->yearperiod ."=". self::$ToYear_PRV ." AND ". $settingVars->weekperiod ."<=". self::$ToWeek_PRV .")) ";
    
    if(!empty($_REQUEST['TSM']))
    {
        if($_REQUEST['TSM']==2)
        self::$lyWeekRange = self::$ppWeekRange;
    }
    }
    
    
    /***
     * USUALLY CALLED WHEN IN 'Product KBI' PAGE
     * CALCULATES PREVIOUS PERIOD TIME RANGES. ON OTHER PAGES 'FromWeek_PRV' AND 'ToWeek_PRV' ARE RECEIVED AS REQUEST PARAMS
     * BUT IN 'Product KBI' PAGES, WE DON'T HAVE TSM AVAILABLE , AND WE NEED TO SHOW PREVIOUS PERIOD SALES ALONG WITH TY AND LY SALES
     * SO WE NEED TO CALCULATE '$ppWeekRange' WITH QUERY WITH THE HELP OF '$tyWeekRange'
     ***/
    public static function getExtraSlice_ByQuery($settingVars){
        $queryVars  = projectsettings\settingsGateway::getInstance();
        
        $redisCache = new utils\RedisCache($queryVars);
        $mydateSelect = $settingVars->getMydateSelect($settingVars->dateField);
        $query = "SELECT " . $settingVars->yearperiod . " AS YEAR" .
                ",".$settingVars->weekperiod . " AS WEEK" .
                (($settingVars->dateField) ? ",".$mydateSelect." " : " ") .
                "FROM " . $settingVars->timeHelperTables . $settingVars->timeHelperLink .
                "GROUP BY YEAR,WEEK " .
                "ORDER BY YEAR DESC,WEEK DESC";
        $queryHash = md5($query);
        $redisOutput = $redisCache->checkAndReadByStaticHashFromCache('time_filter_data', $queryHash);

        if ($redisOutput === false) {
            $dateList = $queryVars->queryHandler->runQuery($query, $queryVars->linkid, db\ResultTypes::$TYPE_ARRAY);
            $redisCache->setDataForSubKeyHash($dateList, $queryHash);
        } else {
            $dateList = $redisOutput;
        }

        if (is_array($dateList) && !empty($dateList)) {
            foreach ($dateList as $key => $dateDetail) {
                if ($dateDetail[0] == self::$FromYear && $dateDetail[1] == self::$FromWeek ) {
                    $fromKey = $key;
                }

                if ($dateDetail[0] == self::$ToYear && $dateDetail[1] == self::$ToWeek ) {
                    $toKey = $key;
                }
            }
            
            $weekDiff = ($fromKey - $toKey);
            self::$totalWeek = $toWeekPrvKey = $weekDiff+1;
            $fromWeekPrvKey = $toWeekPrvKey+$weekDiff;
            if (isset($dateList[$toWeekPrvKey]) && !empty($dateList[$toWeekPrvKey])) {
                self::$ToWeek_PRV   = $dateList[$toWeekPrvKey][1];
                self::$ToYear_PRV   = $dateList[$toWeekPrvKey][0];
            }

            if (isset($dateList[$fromWeekPrvKey]) && !empty($dateList[$fromWeekPrvKey])) {
                self::$FromWeek_PRV     = $dateList[$fromWeekPrvKey][1];
                self::$FromYear_PRV     = $dateList[$fromWeekPrvKey][0];
            }
        }
        
        //ONCE '_PRV' VARS ARE SET , WE PREPARE $ppWeekRange
        //FOR PRODUCT KBI PAGE , WE KEEP $lyWeekRange AS IT WAS, FOR OTHER TYPES WE REPLACE $lyWeekRange VALUES BY $ppWeekRange
        if(self::$FromYear_PRV != "" && self::$ToYear_PRV != "" && self::$FromWeek_PRV != "" && self::$ToYear_PRV != "")
        {
            $connect = self::$FromYear_PRV==self::$ToYear_PRV ? " AND ":" OR ";
            self::$ppWeekRange = " ((". $settingVars->yearperiod ."=". self::$FromYear_PRV ." AND ". $settingVars->weekperiod .">=". self::$FromWeek_PRV .")$connect(". $settingVars->yearperiod ."=". self::$ToYear_PRV ." AND ". $settingVars->weekperiod ."<=". self::$ToWeek_PRV .")) ";
        }
        else
            self::$ppWeekRange  = 0;

        if(!empty($_REQUEST['TSM']))
        {
            if($_REQUEST['TSM']==2)
            self::$lyWeekRange = self::$ppWeekRange;
        }
    }
    
    
    /*****
    * returns an Array containing latest year-week combination as 'YEAR.WEEK'
    * query limits [$from ,  $to ] defined by user  
    *****/
    public static function getYearWeekWithinRange($from,$to,$settingVars,$hasTYLYRange = false,$returnMyDateOnly = false){
        $queryVars  = projectsettings\settingsGateway::getInstance();
        $redisCache = new utils\RedisCache($queryVars);
        $yearWeekArray = array(); 

        $mydateSelect = $settingVars->getMydateSelect($settingVars->dateField);
        $query = "SELECT " . $settingVars->yearperiod . " AS YEAR" .
                    ",".$settingVars->weekperiod . " AS WEEK" .
                    (($settingVars->dateField) ? ",".$mydateSelect." " : " ") .
                    "FROM " . $settingVars->timeHelperTables . $settingVars->timeHelperLink .
                    "GROUP BY YEAR,WEEK " .
                    "ORDER BY YEAR DESC,WEEK DESC";

        $queryHash = md5($query);
        $redisOutput = $redisCache->checkAndReadByStaticHashFromCache('time_filter_data', $queryHash);
        if ($redisOutput === false) {
            $dateList = $queryVars->queryHandler->runQuery($query, $queryVars->linkid, db\ResultTypes::$TYPE_ARRAY);
            $redisCache->setDataForSubKeyHash($dateList, $queryHash);
        } else {
            $dateList = $redisOutput;
        }
        $result = array_slice($dateList, $from, $to);
        foreach($result as $key=>$data){
            if($hasTYLYRange == true){
                $yearWeekArray['TY'][] =  $data[0].$data[1];
                $yearWeekArray['LY'][] =  ($data[0]-1).$data[1];
            }else if($returnMyDateOnly == true){
                array_push($yearWeekArray , $data[2]);
            }
            else            
                array_push($yearWeekArray , $data[0].$data[1]);
        }
        return $yearWeekArray;
    }
    
    
    /*****
    * Calculates total number of YEAR-WEEK combination and sets self::$totalWeek
    *****/
    public static function calculateTotalWeek($settingVars){
        if (!empty($settingVars->yearperiod) && !empty($settingVars->weekperiod)) {
            $queryVars  = projectsettings\settingsGateway::getInstance();    
            $query      = "SELECT ".$settingVars->yearperiod." AS YEAR".
                    ",".$settingVars->weekperiod." AS WEEK ".
                    "FROM ".$settingVars->timeHelperTables . $settingVars->timeHelperLink." ".
                    "AND ".self::$tyWeekRange." ".
                    "GROUP BY 1,2";
            self::$totalWeek  = $queryVars->queryHandler->runQuery($query,$queryVars->linkid,db\ResultTypes::$NUM_OF_ROWS);
        }
    }
    
    
    /****
    * Custom Latest Year-Week retreiver for SSCover Page, sets time vars [$FromWeek,$ToWeek,$FromYear,$ToYear]
    * after retreiving calls header.getExtraSlice function to prepare $tyWeekRange,$lyWeekRange
    ****/
    public static function collectLatestYearSSCover($settingVars){
    $queryVars      = projectsettings\settingsGateway::getInstance();   
    $query      = "SELECT ".$settingVars->weekperiod." AS WEEK".
                ",".$settingVars->yearperiod." AS YEAR ".
                "FROM ".$settingVars->timeHelperTables . $settingVars->timeHelperLink." ".
                "GROUP BY ".$settingVars->timetable.".".$settingVars->period.",WEEK,YEAR ".
                "ORDER BY ".$settingVars->timetable.".".$settingVars->period." DESC ".
                "LIMIT 1,1";
    $result     = $queryVars->queryHandler->runQuery($query,$queryVars->linkid,db\ResultTypes::$TYPE_OBJECT);  
    $data       = $result[0];
    self::$FromWeek = $data['WEEK'];
    self::$FromYear = $data['YEAR'];
    
    $query      = "SELECT ".$settingVars->weekperiod." AS WEEK".
                ",".$settingVars->timetable.".".$settingVars->period." AS PERIOD ".
                "FROM ".$settingVars->timeHelperTables . $settingVars->timeHelperLink." ".
                "GROUP BY PERIOD,".$settingVars->yearperiod.",WEEK ".
                "ORDER BY PERIOD ASC";
    $result     = $queryVars->queryHandler->runQuery($query,$queryVars->linkid,db\ResultTypes::$TYPE_OBJECT);  
    $data       = $result[0];
    self::$ToWeek   = $data['WEEK'];
    self::$ToYear   = self::$FromYear;
    
    //PREPARE TY AND LY WEEKRANGE TO USE IN QUERY
    self::prepareTyLyWeekRange($settingVars);
    }
    
    
    /*****
    * returns an Array containing latest periods [$from and $to defined by user ]
    * this function is used by retailink projects mostly
    *****/
    public static function getPeriodWithinRange($from , $to , $settingVars, $limit=true){
        $queryVars  = projectsettings\settingsGateway::getInstance();

        $query  = "SELECT DISTINCT ".$settingVars->period." AS PERIOD ".
                "FROM ".$settingVars->maintable." ".
                "GROUP BY PERIOD ".
                "ORDER BY PERIOD DESC ";
        if($limit)
            $query .= "LIMIT $from,$to";
        
        $result = $queryVars->queryHandler->runQuery($query,$queryVars->linkid,db\ResultTypes::$TYPE_OBJECT);
        $periodArray = array();
        if (is_array($result) && !empty($result))
            $periodArray = array_column($result, 'PERIOD');
        
        return $periodArray;
    }

    /*****
    * returns an Array containing latest periods [$from and $to defined by user ]
    * this function is used by retailink projects mostly
    *****/
    public static function fetchPeriodWithinRange($from , $to , $settingVars){
        $queryVars  = projectsettings\settingsGateway::getInstance();

        $query  = "SELECT DISTINCT ".$settingVars->period." AS PERIOD ".
                "FROM ".$settingVars->maintable." ".
                "GROUP BY PERIOD ".
                "ORDER BY PERIOD DESC ".
                "LIMIT $from,$to";
        $result = $queryVars->queryHandler->runQuery($query,$queryVars->linkid,db\ResultTypes::$TYPE_OBJECT);
        $periodArray = array();
        if (is_array($result) && !empty($result))
            $periodArray = array_column($result, 'PERIOD');

        self::$mydateRange = (is_array($periodArray) && !empty($periodArray)) ? " (".$settingVars->period." IN ('" . implode("','", $periodArray) . "')) " : "";
    }
    
    /****
     * IDENTICAL TO getPeriodWithinRange FUNCTION DEFINED BEFORE
     * DIFFERENCE BETWEEN TWO FUNCTIONS:
     *  -> THIS FUNCTION USES $timeHelperTable FOR DATA FETCHING AND THE OTHER ONE USES ONLY MAINTABLE
     *  -> IT RETURNS STRING INSTEAD OF AN ARRAY
     ****/
    public static function getPeriodWithinRangeTwin($settingVars , $from=0 , $to=1){
    $queryVars  = projectsettings\settingsGateway::getInstance(); 
    $query = "SELECT ".$settingVars->timetable.".".$settingVars->period." AS PERIOD ".
            "FROM ".$settingVars->timeHelperTables . $settingVars->timeHelperLink." ".
            "GROUP BY ".$settingVars->timetable.".".$settingVars->period." ".
            "ORDER BY ".$settingVars->timetable.".".$settingVars->period." DESC ".
            "LIMIT $from,$to";
    $result = $queryVars->queryHandler->runQuery($query,$queryVars->linkid,db\ResultTypes::$TYPE_ARRAY);
    $periodList = "";
    foreach($result as $key=>$data){
        $periodList .= $data[0] . ',';
    }
    return substr($periodList, 0, -1);
    }
    
    
    /****
    * Calculates $FromYear,$FromWeek,$ToYear,$ToWeek from according to passed $limit
    ****/
    public static function grabLatestPeriod($limit,$settingVars){
    $queryVars  = projectsettings\settingsGateway::getInstance();
    //setting "TO" variables to LATEST YEAR AND IT'S LATEST WEEK
    $query  = "SELECT ".$settingVars->yearperiod.
            ",".$settingVars->weekperiod." ".
            "FROM ".$settingVars->timeHelperTables.$settingVars->timeHelperLink." ".
            "GROUP BY ".$settingVars->timetable.".".$settingVars->period." , ".$settingVars->yearperiod." , ".$settingVars->weekperiod." ".
            "ORDER BY ".$settingVars->timetable.".".$settingVars->period." DESC ".
            "LIMIT 1,1";    
    $result = $queryVars->queryHandler->runQuery($query,$queryVars->linkid,db\ResultTypes::$TYPE_ARRAY);  
    $data   = $result[0];
    self::$ToWeek       = $data[1];
    self::$ToYear       = $data[0];
      
      
    //setting "FROM" variables depends on passed $limit
    $query  = "SELECT ".$settingVars->yearperiod.
            ",".$settingVars->weekperiod." ".
            "FROM ".$settingVars->timeHelperTables.$settingVars->timeHelperLink." ".
            "GROUP BY ".$settingVars->timetable.".".$settingVars->period." , ".$settingVars->yearperiod." , ".$settingVars->weekperiod." ".
            "ORDER BY ".$settingVars->timetable.".".$settingVars->period." DESC ".
            "LIMIT $limit,1";
    $result = $queryVars->queryHandler->runQuery($query,$queryVars->linkid,db\ResultTypes::$TYPE_ARRAY);  
    $data   = $result[0];
    self::$FromWeek     = $data[1];
    self::$FromYear     = $data[0];
    
    //PREPARE TY AND LY WEEKRANGE TO USE IN QUERY
    self::prepareTyLyWeekRange($settingVars);
    }

    /****
    * Calculates $tyWeekRange and $lyWeekRange from passed 'TimeFrame'.
    * Important to note that 'TimeFrame' and 'pageID' are same in concept
    ****/
    public static function calculate_Ty_And_Ly_WeekRange_From_TimeFrame($timeFrame,$settingVars){
        $queryVars  = projectsettings\settingsGateway::getInstance();
        //setting "TO" variables to LATEST YEAR AND IT'S LATEST WEEK
        $query  = "SELECT ".$settingVars->yearperiod." AS YEAR".
                ",".$settingVars->weekperiod." AS WEEK ".
                "FROM ".$settingVars->timeHelperTables.$settingVars->timeHelperLink." ".
                "GROUP BY YEAR,WEEK ".
                "ORDER BY YEAR DESC,WEEK DESC ";
        //print $query;exit;
        $result = $queryVars->queryHandler->runQuery($query,$queryVars->linkid,db\ResultTypes::$TYPE_ARRAY);
        $resultCount = count($result);
        $data   = $result[0];
        self::$ToWeek       = $data[1];
        self::$ToYear       = $data[0];
          
        if($resultCount >= $timeFrame ){
            $data   = $result[$timeFrame-1];
            self::$FromWeek     = $data[1];
            self::$FromYear     = $data[0];
        }else{
            $data   = $result[$resultCount-1];
            self::$FromWeek     = $data[1];
            self::$FromYear     = $data[0];
        } 
        
        //PREPARE TY AND LY WEEKRANGE TO USE IN QUERY
        self::prepareTyLyWeekRange($settingVars);
    }

    public static function getTimeFrame($n, $settingVars, $distributionWeek = '') {

        if ($n == 'YTD') {
            self::getYTD($settingVars);
        } else {
            /*$queryVars = projectsettings\settingsGateway::getInstance();
            $query = "SELECT " . $settingVars->yearperiod . " as YEAR" .
                    "," . $settingVars->weekperiod . " as WEEK " .
                    "FROM " . $settingVars->timeHelperTables . " " . $settingVars->timeHelperLink . " " .
                    "GROUP BY 1,2 " .
                    "ORDER BY 1 DESC,2 DESC " .
                    "LIMIT 0,$n";
            $result = $queryVars->queryHandler->runQuery($query, $queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);*/

            $queryVars  = projectsettings\settingsGateway::getInstance();

            $redisCache = new utils\RedisCache($queryVars);

            $mydateSelect = $settingVars->getMydateSelect($settingVars->dateField);
            $query = "SELECT " . $settingVars->yearperiod . " AS YEAR" .
                    ",".$settingVars->weekperiod . " AS WEEK" .
                    (($settingVars->dateField) ? ",".$mydateSelect." " : " ") .
                    "FROM " . $settingVars->timeHelperTables . $settingVars->timeHelperLink .
                    "GROUP BY YEAR,WEEK " .
                    "ORDER BY YEAR DESC,WEEK DESC";
            $queryHash = md5($query);
            $redisOutput = $redisCache->checkAndReadByStaticHashFromCache('time_filter_data', $queryHash);

            if ($redisOutput === false) {
                $dateList = $queryVars->queryHandler->runQuery($query, $queryVars->linkid, db\ResultTypes::$TYPE_ARRAY);
                $redisCache->setDataForSubKeyHash($dateList, $queryHash);
            } else {
                $dateList = $redisOutput;
            }

            $result = array_slice($dateList, 0, $n);

            self::$ToWeek = $result[0][1];
            self::$ToYear = $result[0][0];

            self::$ToWeek_PRV = $result[0][1];
            self::$ToYear_PRV = ($result[0][0] - 1);

            end($result);
            self::$FromWeek = $result[key($result)][1];
            self::$FromYear = $result[key($result)][0];

            self::$FromWeek_PRV = $result[key($result)][1];
            self::$FromYear_PRV = ($result[key($result)][0] - 1);

            self::prepareTyLyWeekRange($settingVars);
        }
        /*elseif ($distributionWeek != '') {
            $queryVars = projectsettings\settingsGateway::getInstance();
            $query = "SELECT " . $settingVars->yearperiod . " as YEAR" .
                    "," . $settingVars->weekperiod . " as WEEK " .
                    "FROM " . $settingVars->timeHelperTables . " " . $settingVars->timeHelperLink . " " .
                    "GROUP BY 1,2 " .
                    "ORDER BY 1 DESC,2 DESC " .
                    "LIMIT $n,$distributionWeek";
            $result = $queryVars->queryHandler->runQuery($query, $queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);

            self::$ToWeek = $result[0]['WEEK'];
            self::$ToYear = ($result[0]['YEAR'] + 1);

            self::$ToWeek_PRV = $result[0]['WEEK'];
            self::$ToYear_PRV = $result[0]['YEAR'];

            end($result);
            self::$FromWeek = $result[key($result)]['WEEK'];
            self::$FromYear = ($result[key($result)]['YEAR'] + 1);

            self::$FromWeek_PRV = $result[key($result)]['WEEK'];
            self::$FromYear_PRV = $result[key($result)]['YEAR'];

            self::prepareTyLyWeekRange($settingVars);
        }*/ 
    }   
    
    public static function getLatest_n_dates($s = '0', $n, $settingVars, $addLy = false, $yearWeekSeparator = "") {
        $queryVars = projectsettings\settingsGateway::getInstance();
        $redisCache = new utils\RedisCache($queryVars);
        $mydateSelect = $settingVars->getMydateSelect($settingVars->dateField);
        $query = "SELECT " . $settingVars->yearperiod . " AS YEAR" .
                ",".$settingVars->weekperiod . " AS WEEK" .
                (($settingVars->dateField) ? ",".$mydateSelect." " : " ") .
                "FROM " . $settingVars->timeHelperTables . $settingVars->timeHelperLink .
                "GROUP BY YEAR,WEEK " .
                "ORDER BY YEAR DESC,WEEK DESC";
        $queryHash = md5($query);
        $redisOutput = $redisCache->checkAndReadByStaticHashFromCache('time_filter_data', $queryHash);
        if ($redisOutput === false) {
            $result = $queryVars->queryHandler->runQuery($query, $queryVars->linkid, db\ResultTypes::$TYPE_ARRAY);
            $redisCache->setDataForSubKeyHash($result, $queryHash);
        } else {
            $result = $redisOutput;
        }        

        $daysArray = array();
        $daysArrayLY = array();
        if(is_array($result) && count($result)>0){
            $result = array_slice($result, $s, $n);
            foreach ($result as $ky => $value) {
                array_push($daysArray, $value[0] .$yearWeekSeparator. $value[1]);
                $LY = ($value[0] - 1);
                array_push($daysArrayLY, $LY .$yearWeekSeparator. $value[1]);
            }
        }
        if($addLy){
            // combine TY and LY array with year and week combination and returning them. In calling function split them by # and use them  
                return implode(",", $daysArray) . "#" . implode(",", $daysArrayLY);
        }else{
                return $daysArray;
        }
    }
    
    public static function getLatest_n_dates_ly($s = '0', $n, $settingVars) {
        $queryVars = projectsettings\settingsGateway::getInstance();
        $query = "SELECT " . $settingVars->yearperiod . " as YEAR" .
                "," . $settingVars->weekperiod . " as WEEK " .
                "FROM " . $settingVars->timeHelperTables . " " . $settingVars->timeHelperLink . " " .
                "GROUP BY 1,2 " .
                "ORDER BY 1 DESC,2 DESC " .
                "LIMIT $s,$n";
        $result = $queryVars->queryHandler->runQuery($query, $queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);

        $daysArray = array();
        $daysArrayLY = array();

        for ($i = 0; $i < count($result); $i++) {
            $data = $result[$i];
            array_push($daysArray, $data['YEAR'] . $data['WEEK']);
            $LY = ($data['YEAR'] - 1);
            array_push($daysArrayLY, $LY . $data['WEEK']);
        }

        // combine TY and LY array with year and week combination and returning them. In calling function split them by # and use them  
        return implode(",", $daysArray) . "#" . implode(",", $daysArrayLY);
    }
    
    public static function getLatestMydate($settingVars)
    {
        $queryVars   = projectsettings\settingsGateway::getInstance();
        $redisCache  = new utils\RedisCache($queryVars);
        $query       = "SELECT MAX(".$settingVars->dateperiod.") as MYDATE FROM ".$settingVars->maintable;
        $queryHash   = md5($query);
        $redisOutput = $redisCache->checkAndReadByStaticHashFromCache('time_filter_get_latest_mydate', $queryHash);
        if ($redisOutput === false) {
            $result  = $queryVars->queryHandler->runQuery($query, $queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $redisCache->setDataForSubKeyHash($result, $queryHash);
        } else {
            $result  = $redisOutput;
        }
        return $mydate = (is_array($result) && !empty($result)) ? $result[0]['MYDATE'] : '';
    }

    // This function is use for gaylea  gaint tiger
    public static function prepareTyLy($settingVars)
    {
        self::$tyWeekRange  = $settingVars->timePeriod . " = 'TY'";
        self::$lyWeekRange  = $settingVars->timePeriod . " = 'LY'";
    }

    // This function is use for Prepare TY, LY Gnielsen Format 1
    public static function gnielsenFormat1($settingVars,$timeframe='') 
    {
        if (!empty($timeframe))
            self::$daysTimeframe = $timeframe;
        else
            self::$daysTimeframe = $_REQUEST['timeFrame'];

        if (empty(self::$daysTimeframe)) {
            $isActive = '';
            foreach ($settingVars->timeSelectionStyleDDArray as $key => $value) {
                if(isset($value['selected']) && $value['selected'] == true)
                    $isActive = $value['data'];
            }
            self::$daysTimeframe = $isActive;
            $settingVars->timeSelectionStyleDDArray;
        }

        self::$tyWeekRange  = $settingVars->dateField . " = '".self::$daysTimeframe."0'";
        self::$lyWeekRange  = $settingVars->dateField . " = '".self::$daysTimeframe."1'";
    }

    public static function getLastNDaysDate($setting) {

        $queryVars = projectsettings\settingsGateway::getInstance();

        $query = "SELECT " . $setting->DatePeriod
                . " FROM " . $setting->maintable
                . " GROUP BY 1 ORDER BY " . $setting->DatePeriod . " DESC LIMIT 0, " . $_REQUEST["requestDays"];

        $result = $queryVars->queryHandler->runQuery($query, $queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
        $resultData = array();
        foreach ($result as $value) {
            foreach ($value as $mydate) {
                $resultData[] = "'" . $mydate . "'";
            }
        }
        return $resultData;
    }

     public static function getLastN14DaysDate($setting, $offset = 0, $limit = 14) {

        $queryVars = projectsettings\settingsGateway::getInstance();

        /*$query = "SELECT " . $setting->DatePeriod
                . " FROM " . $setting->maintable
                . " GROUP BY 1 ORDER BY " . $setting->DatePeriod . " DESC";
        $result = $queryVars->queryHandler->runQuery($query, $queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
        $resultData = array();
        foreach ($result as $value) {
            foreach ($value as $mydate) {
                $resultData[] = "'" . $mydate . "'";
            }
        }*/

        $redisCache = new utils\RedisCache($queryVars);
        $query = "SELECT " . $setting->dateField ." AS MYDATE ".
                "FROM " . $setting->timeHelperTables . $setting->timeHelperLink .
                "GROUP BY MYDATE " .
                "ORDER BY MYDATE DESC";

        $queryHash = md5($query);
        $redisOutput = $redisCache->checkAndReadByStaticHashFromCache('time_filter_data', $queryHash);
        if ($redisOutput === false) {
            $dateList = $queryVars->queryHandler->runQuery($query, $queryVars->linkid, db\ResultTypes::$TYPE_ARRAY);
            $redisCache->setDataForSubKeyHash($dateList, $queryHash);
        } else {
            $dateList = $redisOutput;
        }

        $dateList = array_slice($dateList, $offset, $limit);

        $resultData = array();
        foreach ($dateList as $mydate) {
            $resultData[] = "'" . $mydate[0] . "'";
        }
        return $resultData;
    }

    /* returns an Array containing latest dates
    * query limits [$from ,  $to ] defined by user  
    *****/
    public static function getDatesWithinRange($from,$to,$setting){
        $queryVars = projectsettings\settingsGateway::getInstance();

        $query = "SELECT " . $setting->DatePeriod . " as mydate"
                . " FROM " . $setting->maintable
                . " GROUP BY 1 ORDER BY 1 DESC LIMIT $from,$to";
        //echo "<br />".$query;
        $result = $queryVars->queryHandler->runQuery($query, $queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
        $resultData = array();
        foreach ($result as $value) {
                $resultData[] = "'" . $value['mydate'] . "'";
        }
        return $resultData;
    }

    public static function getLastNDaysDateFromDepotDaily($setting) {
        if (empty($setting->tesco_depot_daily))
            return "";

        $queryVars = projectsettings\settingsGateway::getInstance();

        $redisCache = new utils\RedisCache($queryVars);
        $query = "SELECT MAX(".$setting->DatePeriod. ") AS MYDATE FROM " . $setting->tesco_depot_daily." WHERE accountID = ".$setting->aid." AND GID = ".$setting->GID;
        $queryHash = md5($query);
        $redisOutput = $redisCache->checkAndReadByStaticHashFromCache('time_filter_from_depot_daily', $queryHash);
        if ($redisOutput === false) {
            $result = $queryVars->queryHandler->runQuery($query, $queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $redisCache->setDataForSubKeyHash($result, $queryHash);
        } else {
            $result = $redisOutput;
        }

        if(isset($result[0]) && isset($result[0]['MYDATE']))
            return "'" . $result[0]['MYDATE'] . "'";
    }

    public static function getLatestWeek($settingVars)
    {
        self::getTimeFrame('YTD', $settingVars);
        $settingVars->tyYTDWeekRange = self::$tyWeekRange;
        $settingVars->lyYTDWeekRange = self::$lyWeekRange;

        self::getTimeFrame(1, $settingVars);
        $settingVars->tyLastWeekRange = self::$tyWeekRange;
        $settingVars->lyLastWeekRange = self::$lyWeekRange;
        
        self::getTimeFrame(4, $settingVars);
        $settingVars->tyLast4WeekRange = self::$tyWeekRange;
        $settingVars->lyLast4WeekRange = self::$lyWeekRange;

        self::getTimeFrame(13, $settingVars);
        $settingVars->tyLast13WeekRange = self::$tyWeekRange;
        $settingVars->lyLast13WeekRange = self::$lyWeekRange;

        self::getTimeFrame(52, $settingVars);
        $settingVars->tyLast52WeekRange = self::$tyWeekRange;
        $settingVars->lyLast52WeekRange = self::$lyWeekRange;
        
    }

    public static function getTotalWeek($fromYear,$fromWeek,$toYear,$toWeek){
        $weekCnt = $yearWeekCnt = 0;

        if(empty($fromWeek) || empty($toWeek) || empty($fromYear) || empty($toYear))
            return 0;

        if($fromYear != $toYear){
            $totalWeek = date('W', strtotime('December 31th'.$fromYear));
            $totalWeek = ($totalWeek == "01") ? 52 : $totalWeek;
            $weekCnt = ($totalWeek - ($fromWeek-1)) + $toWeek;
            for ($i=$fromYear+1; $i < $toYear ; $i++) { 
                $totalWeek = date('W', strtotime('December 31th'.$i));
                $totalWeek = ($totalWeek == "01") ? 52 : $totalWeek;
                $yearWeekCnt +=$totalWeek;
            }
            $weekCnt = $weekCnt + $yearWeekCnt;
        }else{
            $weekCnt = ($toWeek - $fromWeek)+1;
        }

        return $weekCnt;
    }


    /*[START] PREPARE THE SEASONAL TIMEFRAME RANG FROM THE GEE*/
    public static function getSliceSeasonalTimeframe($settingVars) {
        $data = $_REQUEST["timeFrame"];
        self::$SeasonalTimeframeID = $data;
        self::prepareTyLySeasonalTimeframeRange($settingVars);
    }

    /****
     * Prepares Ty and Ly weekrange to use in query,
     ****/
    public static function prepareTyLySeasonalTimeframeRange($settingVars){
        $id = self::$SeasonalTimeframeID;
        if (isset($settingVars->seasonalTimeframeConfiguration) && count($settingVars->seasonalTimeframeConfiguration) > 0) {
            $sKey = array_search($id, array_column($settingVars->seasonalTimeframeConfiguration,'id'));
            $data = isset($settingVars->seasonalTimeframeConfiguration[$sKey]) ? $settingVars->seasonalTimeframeConfiguration[$sKey] : $settingVars->seasonalTimeframeConfiguration[0];
            
            $tyFromDate = $data['ty_start_date'];
            $tyToDate   = $data['ty_end_date'];
            $lyFromDate = $data['ly_start_date'];
            $lyToDate   = $data['ly_end_date'];
            $whereClause = $data['where_clause'];

            $filterFieldExtraWhere = '';
            if(!empty($whereClause)) {
                $whereData = explode("|#|", $whereClause);
                $fieldName      = $whereData['0'];
                $fieldOperator  = $whereData['1'];
                $fieldValue     = $whereData['2'];

                switch ($fieldOperator) {
                    case 'EQUALS_TO':
                        $filterFieldExtraWhere = ' AND '.$fieldName.' = "'.$fieldValue.'"';
                        break;
                    case 'NOT_EQUALS_TO':
                        $filterFieldExtraWhere = ' AND '.$fieldName.' != "'.$fieldValue.'"';
                        break;
                    case 'CONTAINS':
                        $filterFieldExtraWhere = ' AND '.$fieldName.' LIKE "%'.$fieldValue.'%"';
                        break;
                    case 'NOT_CONTAINS':
                        $filterFieldExtraWhere = ' AND '.$fieldName.' NOT LIKE "%'.$fieldValue.'%"';
                        break;
                    default:
                        $filterFieldExtraWhere = ' AND '.$fieldName.' LIKE "%'.$fieldValue.'%"';
                        break;
                }
            }
            
            if (isset($_REQUEST["toDate"]) && !empty($_REQUEST["toDate"])) {
                $tyToDate   = $_REQUEST["toDate"];
            }

            if (isset($_REQUEST['facings']) && !empty($_REQUEST['facings'])) {
                $sign = ($_REQUEST['facings'] >= 0) ? " + " : " - ";
                $lyFromDate = date('Y-m-d', strtotime($lyFromDate . $sign . abs($_REQUEST['facings']) . ' days'));
                $lyToDate = date('Y-m-d', strtotime($lyToDate . $sign . abs($_REQUEST['facings']) . ' days'));
            }

            self::$tyWeekRange  = " (". $settingVars->dateField ." BETWEEN '". $tyFromDate ."' AND '". $tyToDate ."') ";
            self::$lyWeekRange  = " (". $settingVars->dateField ." BETWEEN '". $lyFromDate ."' AND '". $lyToDate ."') ";
            self::$seasonalTimeframeExtraWhere  = $filterFieldExtraWhere;
        }
    }
}
?>