<?php
namespace classes;

use datahelper;
use projectsettings;
use filters;
use utils;
use db;
use config;

class FirstHitWeeklySummaryPage extends config\UlConfig {

    public $accountArray = array();
    public $allOtherCount;
    public $accountName;
    public $getField;
    public $getPageAnalysisField;
    public $skipDbcolumnArray = false;
    public $dbColumnsArray;
    public $displayCsvNameArray;
    public $pageName;

    public function __construct() {
        $this->allOtherCount = 20;
    }

    public function go($settingVars) {
        $this->initiate($settingVars); 
        
        $this->settingVars->pageName = (empty($this->settingVars->pageName)) ? $this->settingVars->pageID . '_FirstHitWeeklySummaryPage' : $settingVars->pageName;

        $this->redisCache = new utils\RedisCache($this->queryVars);
        $this->settingVars->useRequiredTablesOnly = false;
        $this->queryPart = $this->getAll(); 
        $this->commonQueryPart = " file_type = 'FIRST HIT WEEKLY'";

        $action = $_REQUEST["action"];
        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {

            /* Fetch Table Settings Start */
            $tables = $this->getPageConfiguration('table_settings', $this->settingVars->pageID);
            $tablesSelectedField = $this->getPageConfiguration('table_settings_default_field', $this->settingVars->pageID);
            /* Fetch Table Settings End */

            /* Fetch Filter Settings Start */
            $this->jsonOutput['pageConfig'] = array(
                'enabledFilters' => $this->getPageConfiguration('filter_settings', $this->settingVars->pageID)
            );
            /* Fetch Filter Settings End */
            
            /* Pagination Settings Starts */
            $pagination_settings_arr = $this->getPageConfiguration('pagination_settings', $this->settingVars->pageID);
            if(count($pagination_settings_arr) > 0){
                $this->jsonOutput['is_pagination'] = $pagination_settings_arr[0];
                $this->jsonOutput['per_page_row_count'] = (int) $pagination_settings_arr[1];
            }
            /* Pagination Settings End */

            $this->buildPageArray();

        }else{
            if($action == 'accountChange') {
                $cummulativeFlag = $_REQUEST["cummulativeFlag"];
                $this->accountChange($cummulativeFlag);
            } else {
                $this->getLatestDateOfMultsProject();
                $this->topGridData();
            }
            
            
        }
        return $this->jsonOutput;
    }

    /**
     * For overright header text
    */
    function getLatestDateOfMultsProject() {
        
        $commontables   = $this->settingVars->dataTable['default']['tables'];
        $commonlink     = $this->settingVars->dataTable['default']['link'];
        $skutable        = $this->settingVars->dataTable['product']['tables'];
        $skulink        = $this->settingVars->dataTable['product']['link'];

        $dateSelect = 'mydate';
        // if($this->fileType == 'FIRST HIT DAILY'){
        //     $dateSelect = 'order_due_date';
        // } 

        $query = " SELECT MAX(distinct $dateSelect) AS MYDATE
                    FROM ".$commontables.", ".$skutable. 
                    $commonlink. $skulink . " AND ".
                    $this->commonQueryPart ." ORDER BY MYDATE";

        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }
        
        $this->jsonOutput['LatestMultsDate'] = date("D d M Y", strtotime($result[0]['MYDATE']));
    }
    
    /**
     * topGridData()
     * This Function is used to retrieve list based on set parameters     
     * @access private  
     * @return $this->jsonOutput with all data that should be sent to client app     
     */
    private function topGridData($action = false) {
        
        $getField = $_REQUEST['Field'];
        $gridFieldPart = explode("#", $getField);
        $gridField = strtoupper($gridFieldPart[0]);
        $gridField = (count($gridFieldPart) > 1) ? strtoupper($gridField . "_" . $gridFieldPart[1]) : $gridField;
        $this->queryPart = filters\productAndMarketFilter::include_product_and_market_filters($this->queryVars,$this->settingVars);
        $this->accountIdField = (isset($this->settingVars->dataArray[$gridField]) && isset($this->settingVars->dataArray[$gridField]['ID'])) ? 
            $this->settingVars->dataArray[$gridField]['ID'] : $this->settingVars->dataArray[$gridField]['NAME'];
        $this->accountNameField = $this->settingVars->dataArray[$gridField]['NAME'];

        $poTable = $this->settingVars->tesco_po_details;
        $timetable = $this->settingVars->timetable;
        $skutable = $this->settingVars->skutable;

        $query = "SELECT ".$this->accountNameField." AS ACCOUNT " .
                ",SUM((CASE WHEN " . filters\timeFilter::$tyWeekRange . " THEN 1 ELSE 0 END) * total_orders_cases) AS  ORDERS_TY".
                ",SUM((CASE WHEN " . filters\timeFilter::$lyWeekRange . " THEN 1 ELSE 0 END) * total_orders_cases) AS  ORDERS_LY".
                ",SUM((CASE WHEN " . filters\timeFilter::$tyWeekRange . " THEN 1 ELSE 0 END) * (fta_code1_cases+fta_code2_cases+fta_code3_cases+fta_code4_primary_cases+fta_code5_cases+fta_code6_cases+total_shortage_cases+suppressed_qty)) AS SHORTAGES_TY".
                ",SUM((CASE WHEN " . filters\timeFilter::$lyWeekRange . " THEN 1 ELSE 0 END) * (fta_code1_cases+fta_code2_cases+fta_code3_cases+fta_code4_primary_cases+fta_code5_cases+fta_code6_cases+total_shortage_cases+suppressed_qty)) AS  SHORTAGES_LY ".
                /*",SUM((CASE WHEN " . filters\timeFilter::$tyWeekRange . " THEN 1 ELSE 0 END) * (fta_code1_cases+fta_code2_cases+fta_code3_cases+fta_code4_primary_cases+fta_code5_cases+fta_code6_cases)) AS FTA_TY".
                ",SUM((CASE WHEN " . filters\timeFilter::$lyWeekRange . " THEN 1 ELSE 0 END) * (fta_code1_cases+fta_code2_cases+fta_code3_cases+fta_code4_primary_cases+fta_code5_cases+fta_code6_cases)) AS FTA_LY".*/
                ",SUM((CASE WHEN " . filters\timeFilter::$tyWeekRange . " THEN 1 ELSE 0 END) * ".$poTable.".delivered_on_time ) AS  DELIV_ON_TIME_TY".
                ",SUM((CASE WHEN " . filters\timeFilter::$lyWeekRange . " THEN 1 ELSE 0 END) * ".$poTable.".delivered_on_time ) AS  DELIV_ON_TIME_LY".
                " FROM ".$poTable.", ".$skutable.", ".$timetable. 
                " WHERE ".$poTable.".GID = ".$skutable.".GID ". 
                " AND " .$poTable.".skuID = ".$skutable.".PIN ".
                " AND " .$poTable.".GID = ".$timetable.".GID ".
                " AND " .$poTable.".".$this->settingVars->dateperiod." = ".$timetable.".".$this->settingVars->dateperiod .
                " AND " .$skutable.".clientID = '".$this->settingVars->clientID."' ".
                " AND " .$skutable.".hide <> 1 ".
                " AND " .$skutable.".GID = ".$this->settingVars->GID.
                " AND " .$timetable.".GID = ".$this->settingVars->GID.
                (!empty($extraWhere) ? " AND ". $extraWhere : "").
                (!empty($this->commonQueryPart) ? " AND ".$this->commonQueryPart: "").
                (!empty($this->queryPart) ? $this->queryPart : "") .
                " AND (" . filters\timeFilter::$tyWeekRange . " OR " . filters\timeFilter::$lyWeekRange . ") " .
                " GROUP BY ACCOUNT ORDER BY ORDERS_TY DESC";
        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }
        
        if (is_array($result) && !empty($result))
        {
            foreach($result as $key => $data)
            {
               $result[$key]['ORDERS_LY'] = (int) $result[$key]['ORDERS_LY'];
               $result[$key]['ORDERS_TY'] = (int) $result[$key]['ORDERS_TY'];
               $result[$key]['ORDERS_VAR_PER'] = (isset($result[$key]['ORDERS_LY']) &&  $result[$key]['ORDERS_LY'] != 0) ? (($result[$key]['ORDERS_TY'] - $result[$key]['ORDERS_LY']) / $result[$key]['ORDERS_LY']) * 100 : 0;
               $result[$key]['SHORTAGES_LY'] = (int) $result[$key]['SHORTAGES_LY'];
               $result[$key]['SHORTAGES_TY'] = (int) $result[$key]['SHORTAGES_TY'];
               $result[$key]['DELIV_ON_TIME_TY'] = (int) $result[$key]['DELIV_ON_TIME_TY'];
               $result[$key]['DELIV_ON_TIME_LY'] = (int) $result[$key]['DELIV_ON_TIME_LY'];

               $result[$key]['DELIV_ON_TIME_VAR_PER'] = (isset($result[$key]['DELIV_ON_TIME_LY']) &&  $result[$key]['DELIV_ON_TIME_LY'] != 0) ? (($result[$key]['DELIV_ON_TIME_TY'] - $result[$key]['DELIV_ON_TIME_LY']) / $result[$key]['DELIV_ON_TIME_LY']) * 100 : 0;

               /*$result[$key]['S2DCSL_LY'] = ($result[$key]['ORDERS_LY'] != 0) ? ((($result[$key]['ORDERS_LY']-$result[$key]['SHORTAGES_LY'])/$result[$key]['ORDERS_LY'])*100) : 0;
               $result[$key]['S2DCSL_TY'] = ($result[$key]['ORDERS_TY'] != 0) ? ((($result[$key]['ORDERS_TY']-$result[$key]['SHORTAGES_TY'])/$result[$key]['ORDERS_TY'])*100) : 0;*/

               $result[$key]['S2DCSL_LY'] = ($result[$key]['DELIV_ON_TIME_LY'] != 0 && $result[$key]['ORDERS_LY'] != 0) ? ($result[$key]['DELIV_ON_TIME_LY'] / $result[$key]['ORDERS_LY']) * 100 : 0;
               $result[$key]['S2DCSL_TY'] = ($result[$key]['DELIV_ON_TIME_TY'] != 0 && $result[$key]['ORDERS_TY'] != 0) ? ($result[$key]['DELIV_ON_TIME_TY'] / $result[$key]['ORDERS_TY']) * 100 : 0;

               

               /*$result[$key]['FTA_LY'] = (int) $result[$key]['FTA_LY'];
               $result[$key]['FTA_TY'] = (int) $result[$key]['FTA_TY'];
                $result[$key]['FTA_VAR_PER'] = (isset($result[$key]['FTA_LY']) &&  $result[$key]['FTA_LY'] != 0) ? (($result[$key]['FTA_TY'] - $result[$key]['FTA_LY']) / $result[$key]['FTA_LY']) * 100 : 0;*/
            }
            $AllRowTotal['ACCOUNT'] = "TOTAL";
            $AllRowTotal['ORDERS_LY'] = array_sum(array_column($result,'ORDERS_LY'));
            $AllRowTotal['ORDERS_TY'] = array_sum(array_column($result,'ORDERS_TY'));
            $AllRowTotal['ORDERS_VAR_PER'] = (isset($AllRowTotal['ORDERS_LY']) &&  $AllRowTotal['ORDERS_LY'] != 0) ? (( $AllRowTotal['ORDERS_TY'] - $AllRowTotal['ORDERS_LY']) / $AllRowTotal['ORDERS_LY'] ) * 100 : 0;
            $AllRowTotal['SHORTAGES_TY'] = array_sum(array_column($result,'SHORTAGES_TY'));
            $AllRowTotal['SHORTAGES_LY'] = array_sum(array_column($result,'SHORTAGES_LY'));
            $AllRowTotal['DELIV_ON_TIME_TY'] = array_sum(array_column($result,'DELIV_ON_TIME_TY'));
            $AllRowTotal['DELIV_ON_TIME_LY'] = array_sum(array_column($result,'DELIV_ON_TIME_LY'));

            $AllRowTotal['DELIV_ON_TIME_VAR_PER'] = (isset($AllRowTotal['DELIV_ON_TIME_LY']) &&  $AllRowTotal['DELIV_ON_TIME_LY'] != 0) ? (( $AllRowTotal['DELIV_ON_TIME_TY'] - $AllRowTotal['DELIV_ON_TIME_LY']) / $AllRowTotal['DELIV_ON_TIME_LY'] ) * 100 : 0;

           /*$AllRowTotal['S2DCSL_LY'] = ($AllRowTotal['ORDERS_LY'] != 0) ? ((($AllRowTotal['ORDERS_LY'] - $AllRowTotal['SHORTAGES_LY'])/$AllRowTotal['ORDERS_LY'])*100) : 0;
           $AllRowTotal['S2DCSL_TY'] = ($AllRowTotal['ORDERS_TY'] != 0) ? ((($AllRowTotal['ORDERS_TY'] - $AllRowTotal['SHORTAGES_TY'])/$AllRowTotal['ORDERS_TY'])*100) : 0;*/

           $AllRowTotal['S2DCSL_LY'] = ($AllRowTotal['DELIV_ON_TIME_LY'] != 0 && $AllRowTotal['ORDERS_LY'] != 0) ? ($AllRowTotal['DELIV_ON_TIME_LY'] / $AllRowTotal['ORDERS_LY']) * 100 : 0;
           $AllRowTotal['S2DCSL_TY'] = ($AllRowTotal['DELIV_ON_TIME_TY'] != 0 && $AllRowTotal['ORDERS_TY'] != 0) ? ($AllRowTotal['DELIV_ON_TIME_TY'] / $AllRowTotal['ORDERS_TY']) * 100 : 0;
           

            /*$AllRowTotal['FTA_TY'] = array_sum(array_column($result,'FTA_TY'));
            $AllRowTotal['FTA_LY'] = array_sum(array_column($result,'FTA_LY'));
            $AllRowTotal['FTA_VAR_PER'] = (isset($AllRowTotal['FTA_LY']) &&  $AllRowTotal['FTA_LY'] != 0) ? (( $AllRowTotal['FTA_TY'] - $AllRowTotal['FTA_LY']) / $AllRowTotal['FTA_LY'] ) * 100 : 0;*/

            array_unshift($result,$AllRowTotal);
        }

        $this->bottomGridData();
        
        $this->jsonOutput['topGridData'] = $result;
    }

    /**
     * bottomGridData()
     * This Function is used to retrieve list based on set parameters     
     * @access private  
     * @return $this->jsonOutput with all data that should be sent to client app     
     */
    private function bottomGridData($action = false) {

        $getField = $_REQUEST['Field'];
        $gridFieldPart = explode("#", $getField);
        $gridField = strtoupper($gridFieldPart[0]);
        $gridField = (count($gridFieldPart) > 1) ? strtoupper($gridField . "_" . $gridFieldPart[1]) : $gridField;
        $this->queryPart = filters\productAndMarketFilter::include_product_and_market_filters($this->queryVars,$this->settingVars);
        $this->accountIdField = (isset($this->settingVars->dataArray[$gridField]) && isset($this->settingVars->dataArray[$gridField]['ID'])) ? 
            $this->settingVars->dataArray[$gridField]['ID'] : $this->settingVars->dataArray[$gridField]['NAME'];
        $this->accountNameField = $this->settingVars->dataArray[$gridField]['NAME'];

        $poTable = $this->settingVars->tesco_po_details;
        $timetable = $this->settingVars->timetable;
        $skutable = $this->settingVars->skutable;

        /* Query to get week period */
        $query = "SELECT CONCAT(" . $this->settingVars->yearperiod .",".$this->settingVars->weekperiod . ") AS YEARWEEK ".
        " FROM " . $this->settingVars->timeHelperTables . $this->settingVars->timeHelperLink .
        " AND ".filters\timeFilter::$tyWeekRange .
        " GROUP BY ".$this->settingVars->yearperiod. ",". $this->settingVars->weekperiod .", YEARWEEK ".
        " ORDER BY " . $this->settingVars->yearperiod ." DESC,".$this->settingVars->weekperiod . " DESC";
        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }
        /* End Query to get week period */

        /* Set week period */
        if (is_array($result) && !empty($result))
        {
            foreach ($result as $dtKey => $tyDate) {

                //$tmpDtFmt = date('Y-m', strtotime($tyDate));
                if(!isset($dateArray[$tyDate])) {
                    $colsHeader[] = array("field" => "dt_".$tyDate['YEARWEEK'], "headerName" => $tyDate['YEARWEEK'], "width" => 20);
                }
            }
        }
        /* End week period */

        
        $query = "SELECT ".$this->accountNameField." AS ACCOUNT " .
                ", CONCAT(" . $this->settingVars->yearperiod .",".$this->settingVars->weekperiod . ") AS YEARWEEK ".
                ", SUM(".$poTable.".delivered_on_time) AS DELIV_ON_TIME ".
                ", SUM(".$poTable.".total_orders_cases) AS ORDERS_TY ".
                " FROM ".$poTable.", ".$skutable.", ".$timetable. 
                " WHERE ".$poTable.".GID = ".$skutable.".GID ". 
                " AND " .$poTable.".skuID = ".$skutable.".PIN ".
                " AND " .$poTable.".GID = ".$timetable.".GID ".
                " AND " .$poTable.".".$this->settingVars->dateperiod." = ".$timetable.".".$this->settingVars->dateperiod .
                " AND " .$skutable.".clientID = '".$this->settingVars->clientID."' ".
                " AND " .$skutable.".hide <> 1 ".
                " AND " .$skutable.".GID = ".$this->settingVars->GID.
                " AND " .$timetable.".GID = ".$this->settingVars->GID.
                (!empty($extraWhere) ? " AND ". $extraWhere : "").
                (!empty($this->commonQueryPart) ? " AND ".$this->commonQueryPart: "").
                (!empty($this->queryPart) ? $this->queryPart : "") .
                " AND (" . filters\timeFilter::$tyWeekRange . " ) " .
                " GROUP BY ACCOUNT, YEARWEEK ORDER BY ORDERS_TY DESC";
        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }

        
        if (is_array($result) && !empty($result))
        {
            foreach ($result as $key => $weekData) {
                $weekDataArray[$weekData['ACCOUNT']][$weekData['YEARWEEK']] = $weekData;
            }
            $dateArray = array_unique(array_column($result, 'YEARWEEK'));

            foreach (array_keys($weekDataArray) as $account) {

                $dataResult['ACCOUNT'] = $account;

                foreach ($dateArray as $dayMydate => $dayMonth) {

                    if (isset($weekDataArray[$account][$dayMonth])) {

                        $data = $weekDataArray[$account][$dayMonth];
                        $dtKey = 'dt_'.$dayMonth;
                        /*$dataResult[$dtKey]['DELIV_ON_TIME'] = $data['DELIV_ON_TIME'];
                        $dataResult[$dtKey]['ORDERS_TY'] = $data['ORDERS_TY'];*/
                        $dataResult[$dtKey] = (isset($data['ORDERS_TY']) && $data['ORDERS_TY'] > 0) ? ($data['DELIV_ON_TIME'] / $data['ORDERS_TY']) * 100 : 0;
                    }
                }
                $finalExeclData[] = $dataResult;
            }
        }
        $this->jsonOutput['bottomGridData'] = $finalExeclData;
        $this->jsonOutput['bottomGridHeader'] = $colsHeader;
    }

    private function accountChange($cummulativeFlag = false) {
        
        $getField = $_REQUEST['Field'];
        $gridFieldPart = explode("#", $getField);
        $gridField = strtoupper($gridFieldPart[0]);
        $gridField = (count($gridFieldPart) > 1) ? strtoupper($gridField . "_" . $gridFieldPart[1]) : $gridField;
        $this->queryPart = filters\productAndMarketFilter::include_product_and_market_filters($this->queryVars,$this->settingVars);
        $this->accountIdField = (isset($this->settingVars->dataArray[$gridField]) && isset($this->settingVars->dataArray[$gridField]['ID'])) ? 
            $this->settingVars->dataArray[$gridField]['ID'] : $this->settingVars->dataArray[$gridField]['NAME'];
        $this->accountNameField = $this->settingVars->dataArray[$gridField]['NAME'];
        $this->accountNameCsv = $this->settingVars->dataArray[$gridField]['NAME_CSV'];

        if(isset($_REQUEST['ACCOUNT']) && !empty($_REQUEST['ACCOUNT']))
            $this->queryPart .=" AND ".$this->accountNameField." = '".trim($_REQUEST['ACCOUNT'])."' ";

        $poTable = $this->settingVars->tesco_po_details;
        $timetable = $this->settingVars->timetable;
        $skutable = $this->settingVars->skutable;

        $query = "SELECT MAX(".$this->accountNameField.") AS ACCOUNT " .
                ",".$timetable.".mydate AS MYDATE ".
                ",MAX((CASE WHEN " . filters\timeFilter::$tyWeekRange . " THEN 'TY' ELSE 'LY' END)) AS  TY_LY".
                ",SUM((CASE WHEN " . filters\timeFilter::$tyWeekRange . " THEN 1 ELSE 0 END) * total_orders_cases) AS  ORDERS_TY".
                ",SUM((CASE WHEN " . filters\timeFilter::$lyWeekRange . " THEN 1 ELSE 0 END) * total_orders_cases) AS  ORDERS_LY".
                ",SUM((CASE WHEN " . filters\timeFilter::$tyWeekRange . " THEN 1 ELSE 0 END) * ".$poTable.".delivered_on_time ) AS  DELIV_ON_TIME_TY".
                ",SUM((CASE WHEN " . filters\timeFilter::$lyWeekRange . " THEN 1 ELSE 0 END) * ".$poTable.".delivered_on_time ) AS  DELIV_ON_TIME_LY".
                " FROM ".$poTable.", ".$skutable.", ".$timetable. 
                " WHERE ".$poTable.".GID = ".$skutable.".GID ". 
                " AND " .$poTable.".skuID = ".$skutable.".PIN ".
                " AND " .$poTable.".GID = ".$timetable.".GID ".
                " AND " .$poTable.".".$this->settingVars->dateperiod." = ".$timetable.".".$this->settingVars->dateperiod .
                " AND " .$skutable.".clientID = '".$this->settingVars->clientID."' ".
                " AND " .$skutable.".hide <> 1 ".
                " AND " .$skutable.".GID = ".$this->settingVars->GID.
                " AND " .$timetable.".GID = ".$this->settingVars->GID.
                (!empty($extraWhere) ? " AND ". $extraWhere : "").
                (!empty($this->commonQueryPart) ? " AND ".$this->commonQueryPart: "").
                (!empty($this->queryPart) ? $this->queryPart : "") .
                " AND (" . filters\timeFilter::$tyWeekRange . " OR " . filters\timeFilter::$lyWeekRange . ") " .
                " GROUP BY MYDATE ORDER BY MYDATE ASC";

        // echo $query;exit;
        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }

        if (is_array($result) && !empty($result))
        {
            $dataList = array();
            foreach ($result as $key => $listData) {
                if ($listData['TY_LY'] == 'TY') {
                    $dataList['TY'][] = $listData;
                }
                if ($listData['TY_LY'] == 'LY') {
                    $dataList['LY'][] = $listData;
                }
            }

            $finalData = array();
            $deliveOnTimeTY = $deliveOnTimeLY = $ordersTY = $ordersLY = 0;
            foreach($dataList['TY'] as $key => $data)
            {
               // $result[$key]['ORDERS_LY'] = (int) $result[$key]['ORDERS_LY'];
               // $result[$key]['ORDERS_TY'] = (int) $result[$key]['ORDERS_TY'];

               // $result[$key]['DELIV_ON_TIME_TY'] = (int) $result[$key]['DELIV_ON_TIME_TY'];
               // $result[$key]['DELIV_ON_TIME_LY'] = (int) $result[$key]['DELIV_ON_TIME_LY'];

               // $result[$key]['S2DCSL_LY'] = ($result[$key]['DELIV_ON_TIME_LY'] != 0 && $result[$key]['ORDERS_LY'] != 0) ? round((($result[$key]['DELIV_ON_TIME_LY'] / $result[$key]['ORDERS_LY']) * 100),1) : 0;
               // $result[$key]['S2DCSL_TY'] = ($result[$key]['DELIV_ON_TIME_TY'] != 0 && $result[$key]['ORDERS_TY'] != 0) ? round((($result[$key]['DELIV_ON_TIME_TY'] / $result[$key]['ORDERS_TY']) * 100),1) : 0;

               // $data['DELIV_ON_TIME_TY'] += $data['DELIV_ON_TIME_TY'];
               // $data['ORDERS_TY'] += $data['ORDERS_TY'];

               
               $tmp = array();
               if($cummulativeFlag == 'true') {
                    $deliveOnTimeTY = $deliveOnTimeTY + $data['DELIV_ON_TIME_TY'];
                    $ordersTY = $ordersTY + $data['ORDERS_TY'];

                    $deliveOnTimeLY = $deliveOnTimeLY + $dataList['LY'][$key]['DELIV_ON_TIME_LY'];
                    $ordersLY = $ordersLY + $dataList['LY'][$key]['ORDERS_LY'];

                    $tmp['S2DCSL_TY'] = ($deliveOnTimeTY != 0 && $ordersTY != 0) ? round((($deliveOnTimeTY / $ordersTY) * 100),1) : 0;
                    $tmp['S2DCSL_LY'] = ($deliveOnTimeLY != 0 && $ordersLY != 0) ? round((($deliveOnTimeLY / $ordersLY) * 100),1) : 0;
               } else {
                    $tmp['S2DCSL_TY'] = ($data['DELIV_ON_TIME_TY'] != 0 && $data['ORDERS_TY'] != 0) ? round((($data['DELIV_ON_TIME_TY'] / $data['ORDERS_TY']) * 100),1) : 0;
                    $tmp['S2DCSL_LY'] = ($dataList['LY'][$key]['DELIV_ON_TIME_LY'] != 0 && $dataList['LY'][$key]['ORDERS_LY'] != 0) ? round((($dataList['LY'][$key]['DELIV_ON_TIME_LY'] / $dataList['LY'][$key]['ORDERS_LY']) * 100),1) : 0;
               }

               $tmp['MYDATE']            = date("jS M Y", strtotime($data['MYDATE']));
               $tmp['LYMYDATE']            = date("jS M Y", strtotime($dataList['LY'][$key]['MYDATE']));
               // $pieChartMyDateDD[$data['ORDER_DUE_DATE']] = ['YEAR'=>$data['YEAR'], 'DUE_DATE_LABEL'=>$data['ORDER_DUE_DATE'], 'LABEL' => date("jS M Y", strtotime($data['ORDER_DUE_DATE']))];

               $finalData[] = $tmp;
            }
        }

        $sccountField = explode(".",$this->accountNameField);
        $sccountField = strtoupper($sccountField[1]);

        $this->jsonOutput['LineChart'] = $finalData;
        // $this->jsonOutput['LineChartTitle'] = $this->accountNameCsv.' : '.$_REQUEST['ACCOUNT'];

    }

    public function buildDataArray($fields, $isCsvColumn, $appendTableNameWithDbColumn = false ) {
        if (empty($fields))
            return false;
        $configurationCheck = new config\ConfigurationCheck($this->settingVars, $this->queryVars);
        $configurationCheck->buildDataArray($fields, $isCsvColumn, $appendTableNameWithDbColumn);
        $this->dbColumnsArray = $configurationCheck->dbColumnsArray;
        $this->displayCsvNameArray = $configurationCheck->displayCsvNameArray;
        return;
    }

    public function buildPageArray() {
        /* Filter : Field Selection Start*/
        $tables = array();
        if ($this->settingVars->isDynamicPage){
            $tables = $this->getPageConfiguration('table_settings', $this->settingVars->pageID);
            $tablesSelectedField = $this->getPageConfiguration('table_settings_default_field', $this->settingVars->pageID);
        }else {
            if (isset($this->getPageAnalysisField) && $this->getPageAnalysisField == 'Product')
                $tables = array($this->settingVars->skutable);
            else if (isset($this->getPageAnalysisField) && $this->getPageAnalysisField == 'Store')
                $tables = array("market");
        }

        if (is_array($tables) && !empty($tables)) {
            $fields = $tmpArr = array();
            $fields = $this->prepareFieldsFromFieldSelectionSettings($tables, false);
            $this->buildDataArray($fields, false, true);
            foreach ($fields as $field) {
                $val = explode("#", $field);
                $search = array_search($val[0],$this->dbColumnsArray);
                if($search !== false) {
                    if(count($val) > 1) {
                        $search1 = array_search($val[1],$this->dbColumnsArray);
                        $tmpArr[] = array('label' => $this->displayCsvNameArray[$search], 'data' => $val[0]."#".$val[1]);
                    }
                    else
                        $tmpArr[] = array('label' => $this->displayCsvNameArray[$search], 'data' => $val[0]);
                }
            }
            $this->jsonOutput['fieldSelection'] = $tmpArr;

        } elseif (isset($this->settingVars->pageArray[$this->settingVars->pageName]["ACCOUNT"]) &&
                !empty($this->settingVars->pageArray[$this->settingVars->pageName]["ACCOUNT"])) {
            $this->skipDbcolumnArray = true;
        } else {
            $response = array("configuration" => array("status" => "fail", "messages" => array("Page isn't configured properly.")));
            echo json_encode($response);
            exit();
        }
    }
}
?>