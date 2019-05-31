<?php
namespace classes;

use datahelper;
use projectsettings;
use filters;
use db;
use config;
use utils;

class SupplierToDcAnalysis extends config\UlConfig {

    /** ***
	 * go()
     * Default gateway function, should be similar in all DATA CLASS
     * arguments:
     * $settingVars [project settingsGateway variables]
     * @return $this->jsonOutput with all data that should be sent to client app
     * *** */
     
    public function go($settingVars) {
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES
        $this->redisCache = new \utils\RedisCache($this->queryVars);
        $this->commonQueryPart = " file_type = 'ITEM LEVEL'";
        
        if ($this->settingVars->isDynamicPage) {
        	$this->accountField = $this->getPageConfiguration('account_field', $this->settingVars->pageID)[0];
            $this->fileType = (!empty($this->getPageConfiguration('page_type', $this->settingVars->pageID)[0])) ? $this->getPageConfiguration('page_type', $this->settingVars->pageID)[0] : 'ITEM LEVEL';

            $this->commonQueryPart = " file_type = '".$this->fileType."'";

            $this->fullLayout = false;
            if($this->fileType == 'FIRST HIT DAILY' || $this->fileType == 'FIRST HIT WEEKLY' || $this->fileType == 'DC TO STORE WEEKLY'){
                $this->fullLayout = true;
            }

        	$tempBuildFieldsArray = array($this->accountField);

            if(isset($_REQUEST["fetchConfig"]) &&  $_REQUEST["fetchConfig"] == true) {
                $pagination_settings_arr = $this->getPageConfiguration('pagination_settings', $this->settingVars->pageID);
                if(count($pagination_settings_arr) > 0){
                    $this->jsonOutput['is_pagination'] = $pagination_settings_arr[0];
                    $this->jsonOutput['per_page_row_count'] = (int) $pagination_settings_arr[1];
                }
            }

        	$this->buildDataArray($tempBuildFieldsArray);
            $this->buildPageArray();
        } else {
            $this->configurationFailureMessage();
        }

        $action = $_REQUEST['action'];
        switch ($action) {
            case 'topGridData';
                $this->getLatestDateOfMultsProject();
                $this->topGridData();
				break;
            case 'skuChange';
                $cummulativeFlag = $_REQUEST["cummulativeFlag"];
                $this->skuChange(false, $cummulativeFlag);
                if($this->fullLayout){
                   $this->topGridData(true);
                   $this->bottomGridData();
                   $this->supressedGridData();
                   $this->rightPieChartData();
                }
                break;
            case 'cummulativeChange';
                $cummulativeFlag = $_REQUEST["cummulativeFlag"];
                $this->skuChange(false, $cummulativeFlag);
                break;
            case 'pieChartDataChange';
                $cummulativeFlag = $_REQUEST["cummulativeFlag"];
                $this->skuChange(true, $cummulativeFlag);
                if($this->fullLayout){
                    $this->rightPieChartData(true);
                    $this->bottomGridData(true); 
                    $this->supressedGridData(true);
                }
                break;
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
        if($this->fileType == 'FIRST HIT DAILY'){
            $dateSelect = 'order_due_date';
        } 

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
		
        $this->queryPart = filters\productAndMarketFilter::include_product_and_market_filters($this->queryVars,$this->settingVars);
        
        $poTable = $this->settingVars->tesco_po_details;
        $timetable = $this->settingVars->timetable;
        $skutable = $this->settingVars->skutable;



        $query_fields_data = ",SUM(fta_code1_cases+fta_code2_cases+fta_code3_cases+fta_code4_primary_cases+fta_code5_cases+fta_code6_cases+total_shortage_cases+suppressed_qty) AS TOTAL_SHORTAGES,SUM(fta_code1_cases+fta_code2_cases+fta_code3_cases+fta_code4_primary_cases+fta_code5_cases+fta_code6_cases) AS FTA_TOTAL ".",SUM(total_shortage_cases) AS SHORTAGES ";

        if($this->fileType == 'FIRST HIT DAILY'){
            $query_fields_data = " ,SUM(fta_code1_cases+fta_code2_cases+fta_code3_cases+fta_code4_primary_cases+fta_code4_supplier_cases+fta_code5_cases+fta_code6_cases+back_scheduled_cases+suppressed_qty) AS TOTAL_SHORTAGES, SUM(fta_code4_supplier_cases) AS FTA4SUPPLIER ,SUM(fta_code1_cases+fta_code2_cases+fta_code3_cases+fta_code4_primary_cases+fta_code4_supplier_cases+fta_code5_cases+fta_code6_cases+fta_code9_cases) AS FTA_TOTAL, MAX(central_PTMM_YN) AS STAR_LINE ";
        }

        if($this->fileType == 'FIRST HIT WEEKLY'){
            $query_fields_data = ",SUM(fta_code1_cases+fta_code2_cases+fta_code3_cases+fta_code4_primary_cases+fta_code5_cases+back_scheduled_cases+suppressed_qty+short_delivered_cases) AS TOTAL_SHORTAGES,SUM(fta_code1_cases+fta_code2_cases+fta_code3_cases+fta_code4_primary_cases+fta_code5_cases+fta_code6_cases+fta_code4_supplier_cases) AS FTA_TOTAL ".",SUM(total_shortage_cases) AS SHORTAGES, SUM(fta_code4_supplier_cases) AS FTA4SUPPLIER, MAX(central_PTMM_YN) AS STAR_LINE ";
        }

        if($this->fileType == 'DC TO STORE WEEKLY'){
            $query_fields_data = ",SUM(fta_code1_cases+fta_code2_cases+fta_code3_cases+fta_code4_primary_cases+fta_code5_cases+fta_code6_cases+fta_code4_supplier_cases) AS FTA_TOTAL ".",SUM(short_delivered_cases) AS SHORTAGES, SUM(fta_code4_supplier_cases) AS FTA4SUPPLIER, MAX(central_PTMM_YN) AS STAR_LINE, SUM(total_delivered_cases) AS DELIVERED, SUM(total_shortage_cases) AS TOTAL_SHORTAGES";
        }



        $query_group = $query_order = '';
        if($action){
            $query_fields_data .= ", order_due_date AS ORDER_DUE_DATE ";
            $query_group = "ORDER_DUE_DATE, ";
            $query_order = "ORDER_DUE_DATE DESC, ";
            if(isset($_REQUEST['SKUID']) && !empty(urldecode($_REQUEST['SKUID'])))
                $this->queryPart .=" AND ".$this->accountIdField." = '".trim(urldecode($_REQUEST['SKUID']))."' ";
        }
        
        $query = "SELECT ".$this->accountIdField." AS SKUID " .
                ",".$this->accountNameField." AS ACCOUNT " .
                ",SUM(total_orders_cases) AS TOTAL_ORDERS_CASE ".
                // ",SUM(total_shortage_cases) AS SHORTAGES ".
                ",SUM(suppressed_qty) AS SUPPRESSED_QTY ". 
                ",SUM(fta_code1_cases) AS FTA1 ".
                ",SUM(fta_code2_cases) AS FTA2 ".
                ",SUM(fta_code3_cases) AS FTA3 ".
                ",SUM(fta_code4_primary_cases) AS FTA4 ".
                ",SUM(fta_code5_cases) AS FTA5 ".
                ",SUM(fta_code6_cases) AS FTA6 ".
                ",SUM(delivered_on_time) AS DELIV_ON_TIME ".
                $query_fields_data .
                " FROM ".$poTable.", ".$skutable.", ".$timetable. 
                " WHERE ".$poTable.".GID = ".$skutable.".GID AND ". 
                $poTable.".skuID = ".$skutable.".PIN AND ".
                $poTable.".GID = ".$timetable.".GID AND ".
                $poTable.".".$this->settingVars->dateperiod." = ".$timetable.".".$this->settingVars->dateperiod." AND ".
                $skutable.".clientID = '".$this->settingVars->clientID."' AND ".
                $skutable.".hide <> 1 AND ".
                $skutable.".GID = ".$this->settingVars->GID." AND ".
                $timetable.".GID = ".$this->settingVars->GID." AND ".
                $extraWhere .
                $this->commonQueryPart . 
                (!empty($this->queryPart) ? $this->queryPart : "") ." AND (" . 
                filters\timeFilter::$tyWeekRange . ") GROUP BY SKUID,  ".$query_group." ACCOUNT ORDER BY ".$query_order." TOTAL_ORDERS_CASE DESC";
        //echo $query;exit;
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
                if($this->fileType == 'FIRST HIT DAILY'){
                    $result[$key]['STAR_LINE'] = $data['STAR_LINE'];
                }
                if($action){
                    $result[$key]['ORDER_DUE_DATE'] = $data['ORDER_DUE_DATE'];
                }
                $result[$key]['TOTAL_ORDERS_CASE'] =(int) $data['TOTAL_ORDERS_CASE'];
                $result[$key]['DELIV_ON_TIME'] = (int) $data['DELIV_ON_TIME'];

                if($this->fileType == 'DC TO STORE WEEKLY'){
                    $result[$key]['DELIVERED'] = (int) $data['DELIVERED'];
                    $result[$key]['TOTAL_SHORTAGES'] = (int) $data['TOTAL_SHORTAGES'];
                } else {
                    $data['TOTAL_SHORTAGES'] = $data['TOTAL_ORDERS_CASE'] - $data['DELIV_ON_TIME'];
                    $result[$key]['TOTAL_SHORTAGES'] = (int) $data['TOTAL_SHORTAGES'];
                }

                if($this->fileType != 'FIRST HIT DAILY'){
                    $result[$key]['SHORTAGES'] = (int) $data['SHORTAGES'];
                }
                $result[$key]['FTA_TOTAL'] = (int) $data['FTA_TOTAL'];
                $result[$key]['FTA1'] = (int) $data['FTA1'];
                $result[$key]['FTA2'] = (int) $data['FTA2'];
                $result[$key]['FTA3'] = (int) $data['FTA3'];
                $result[$key]['FTA4'] = (int) $data['FTA4'];
                if($this->fileType == 'FIRST HIT DAILY'){
                    $result[$key]['FTA4SUPPLIER'] = (int) $data['FTA4SUPPLIER'];
                }
                $result[$key]['FTA5'] = (int) $data['FTA5'];
                $result[$key]['FTA6'] = (int) $data['FTA6'];
                $result[$key]['SUPPRESSED_QTY'] = (int) $data['SUPPRESSED_QTY'];
                

                $result[$key]['S2DCSL_PER'] = ($data['TOTAL_ORDERS_CASE'] != 0 && $data['DELIV_ON_TIME'] != 0) ? ($result[$key]['DELIV_ON_TIME'] / $result[$key]['TOTAL_ORDERS_CASE']) * 100 : 0;
                $result[$key]['S2DCSL_PER'] = (double) number_format($result[$key]['S2DCSL_PER'], 1, '.', '');

                if($this->fileType == 'DC TO STORE WEEKLY'){
                    $result[$key]['DC2STORE_PER'] = ($data['TOTAL_ORDERS_CASE'] != 0 && $data['DELIVERED'] != 0) ? ($result[$key]['DELIVERED'] / $result[$key]['TOTAL_ORDERS_CASE']) * 100 : 0;
                    $result[$key]['DC2STORE_PER'] = (double) number_format($result[$key]['DC2STORE_PER'], 1, '.', '');
                }
                
                // $result[$key]['OTHER_SHORTAGES'] = $data['DELIV_ON_TIME'] - $data['TOTAL_SHORTAGES'];
                $result[$key]['OTHER_SHORTAGES'] = $data['TOTAL_SHORTAGES'] - (($this->fileType != 'FIRST HIT DAILY' ? $result[$key]['SHORTAGES'] : 0)) - $result[$key]['SUPPRESSED_QTY'] - $result[$key]['FTA_TOTAL'];
            }

            $AllRowTotal['SKUID'] = '';
            $AllRowTotal['ACCOUNT'] = 'TOTAL';
            if($this->fileType == 'FIRST HIT DAILY'){
                $AllRowTotal['STAR_LINE'] = '';
            }
            $AllRowTotal['TOTAL_ORDERS_CASE'] = array_sum(array_column($result,'TOTAL_ORDERS_CASE'));

            if($this->fileType == 'DC TO STORE WEEKLY'){
                $AllRowTotal['DELIVERED'] = array_sum(array_column($result,'DELIVERED'));
            }

            $AllRowTotal['TOTAL_SHORTAGES'] = array_sum(array_column($result,'TOTAL_SHORTAGES'));
            if($this->fileType != 'FIRST HIT DAILY'){
                $AllRowTotal['SHORTAGES'] = array_sum(array_column($result,'SHORTAGES'));
            }
            $AllRowTotal['FTA_TOTAL'] = array_sum(array_column($result,'FTA_TOTAL'));
            $AllRowTotal['FTA1'] = array_sum(array_column($result,'FTA1'));
            $AllRowTotal['FTA2'] = array_sum(array_column($result,'FTA2'));
            $AllRowTotal['FTA3'] = array_sum(array_column($result,'FTA3'));
            $AllRowTotal['FTA4'] = array_sum(array_column($result,'FTA4'));
            if($this->fileType == 'FIRST HIT DAILY' || $this->fileType == 'FIRST HIT WEEKLY' || $this->fileType == 'DC TO STORE WEEKLY'){
                $AllRowTotal['FTA4SUPPLIER'] = array_sum(array_column($result,'FTA4SUPPLIER'));
            }
            $AllRowTotal['FTA5'] = array_sum(array_column($result,'FTA5'));
            $AllRowTotal['FTA6'] = array_sum(array_column($result,'FTA6'));
            $AllRowTotal['SUPPRESSED_QTY'] = array_sum(array_column($result,'SUPPRESSED_QTY'));
            $AllRowTotal['DELIV_ON_TIME'] = array_sum(array_column($result,'DELIV_ON_TIME'));

            $AllRowTotal['S2DCSL_PER'] = ($AllRowTotal['TOTAL_ORDERS_CASE'] != 0 && $AllRowTotal['DELIV_ON_TIME'] != 0) ? ($AllRowTotal['DELIV_ON_TIME'] / $AllRowTotal['TOTAL_ORDERS_CASE']) * 100 : 0;
            $AllRowTotal['S2DCSL_PER'] = (double) number_format($AllRowTotal['S2DCSL_PER'], 1, '.', '');

            if($this->fileType == 'DC TO STORE WEEKLY'){
                $AllRowTotal['DC2STORE_PER'] = ($AllRowTotal['TOTAL_ORDERS_CASE'] != 0 && $AllRowTotal['DELIVERED'] != 0) ? ($AllRowTotal['DELIVERED'] / $AllRowTotal['TOTAL_ORDERS_CASE']) * 100 : 0;
                $AllRowTotal['DC2STORE_PER'] = (double) number_format($AllRowTotal['DC2STORE_PER'], 1, '.', '');
            }

            
            
            $AllRowTotal['OTHER_SHORTAGES'] = array_sum(array_column($result,'OTHER_SHORTAGES'));

            array_unshift($result,$AllRowTotal);
        }
        if($action) {
            $this->jsonOutput['topGridBySkuData'] = $result;
        } else {
            $this->jsonOutput['topGridData'] = $result;
        }
    }

    /**
     * skuChange()
     * This Function is used to retrieve list based on set parameters     
     * @access private  
     * @return $this->jsonOutput with all data that should be sent to client app     
     */
    private function bottomGridData($isGridOnly = false) {

        $this->queryPart = filters\productAndMarketFilter::include_product_and_market_filters($this->queryVars,$this->settingVars);
        if(isset($_REQUEST['SKUID']) && !empty(urldecode($_REQUEST['SKUID'])))
            $this->queryPart .=" AND ".$this->accountIdField." = '".trim(urldecode($_REQUEST['SKUID']))."' ";

        $poTable    = $this->settingVars->tesco_po_details;
        $timetable  = $this->settingVars->timetable;
        $skutable   = $this->settingVars->skutable;
        
        $poTable = $this->settingVars->tesco_po_details;
        $timetable = $this->settingVars->timetable;
        $skutable = $this->settingVars->skutable;

        $mydateSelectedField = ($this->fileType == 'FIRST HIT DAILY') ? 'order_due_date' : 'mydate';
        $extraWhere = '';
        if($isGridOnly && isset($_REQUEST['selectedMyDate']) && !empty($_REQUEST['selectedMyDate'])) {
            $orderDueDate = date("Y-m-d", strtotime($_REQUEST['selectedMyDate']));
            $extraWhere .= ' '.$mydateSelectedField.' = '. "'$orderDueDate' AND ";
            $isAllData = false;
        }

        $query_fields_data = ",SUM(fta_code1_cases+fta_code2_cases+fta_code3_cases+fta_code4_primary_cases+fta_code5_cases+fta_code6_cases+total_shortage_cases+suppressed_qty) AS TOTAL_SHORTAGES,SUM(fta_code1_cases+fta_code2_cases+fta_code3_cases+fta_code4_primary_cases+fta_code5_cases+fta_code6_cases) AS FTA_TOTAL ".",SUM(total_shortage_cases) AS SHORTAGES ";

        $query_group = '';
        if($this->fileType == 'FIRST HIT DAILY'){
            $query_fields_data = ",SUM(fta_code1_cases+fta_code2_cases+fta_code3_cases+fta_code4_primary_cases+fta_code4_supplier_cases+fta_code5_cases+fta_code6_cases+back_scheduled_cases+suppressed_qty) AS TOTAL_SHORTAGES, SUM(fta_code4_supplier_cases) AS FTA4SUPPLIER ,SUM(fta_code1_cases+fta_code2_cases+fta_code3_cases+fta_code4_primary_cases+fta_code4_supplier_cases+fta_code5_cases+fta_code6_cases+fta_code9_cases) AS FTA_TOTAL, MAX(central_PTMM_YN) AS STAR_LINE, order_due_date AS ORDER_DUE_DATE, depot_number AS DEPOT_NUMBER ";
        }

        if($this->fileType == 'FIRST HIT WEEKLY') {
            $query_fields_data = ",SUM(fta_code1_cases+fta_code2_cases+fta_code3_cases+fta_code4_primary_cases+fta_code5_cases+back_scheduled_cases+suppressed_qty+short_delivered_cases) AS TOTAL_SHORTAGES,SUM(fta_code1_cases+fta_code2_cases+fta_code3_cases+fta_code4_primary_cases+fta_code5_cases+fta_code6_cases+fta_code4_supplier_cases) AS FTA_TOTAL ".",SUM(total_shortage_cases) AS SHORTAGES, SUM(fta_code4_supplier_cases) AS FTA4SUPPLIER, MAX(central_PTMM_YN) AS STAR_LINE, mydate AS ORDER_DUE_DATE, depot_number AS DEPOT_NUMBER ";
        }

        if($this->fileType == 'DC TO STORE WEEKLY'){
            $query_fields_data = ",SUM(fta_code1_cases+fta_code2_cases+fta_code3_cases+fta_code4_primary_cases+fta_code5_cases+fta_code6_cases+fta_code4_supplier_cases) AS FTA_TOTAL ".",SUM(short_delivered_cases) AS SHORTAGES, SUM(fta_code4_supplier_cases) AS FTA4SUPPLIER, MAX(central_PTMM_YN) AS STAR_LINE, mydate AS ORDER_DUE_DATE, depot_number AS DEPOT_NUMBER, SUM(total_delivered_cases) AS DELIVERED, SUM(total_shortage_cases) AS TOTAL_SHORTAGES ";
        }

        $query_group = "ORDER_DUE_DATE, DEPOT_NUMBER";
        
        $query = "SELECT ".$this->accountIdField." AS SKUID " .
                ",".$this->accountNameField." AS ACCOUNT " .
                ",SUM(total_orders_cases) AS TOTAL_ORDERS_CASE ".
                // ",SUM(total_shortage_cases) AS SHORTAGES ".
                ",SUM(suppressed_qty) AS SUPPRESSED_QTY ". 
                ",SUM(fta_code1_cases) AS FTA1 ".
                ",SUM(fta_code2_cases) AS FTA2 ".
                ",SUM(fta_code3_cases) AS FTA3 ".
                ",SUM(fta_code4_primary_cases) AS FTA4 ".
                ",SUM(fta_code5_cases) AS FTA5 ".
                ",SUM(fta_code6_cases) AS FTA6 ".
                ",SUM(delivered_on_time) AS DELIV_ON_TIME ". 
                $query_fields_data .
                " FROM ".$poTable.", ".$skutable.", ".$timetable. 
                " WHERE ".$poTable.".GID = ".$skutable.".GID AND ". 
                $poTable.".skuID = ".$skutable.".PIN AND ".
                $poTable.".GID = ".$timetable.".GID AND ".
                $poTable.".".$this->settingVars->dateperiod." = ".$timetable.".".$this->settingVars->dateperiod." AND ".
                $skutable.".clientID = '".$this->settingVars->clientID."' AND ".
                $skutable.".hide <> 1 AND ".
                $skutable.".GID = ".$this->settingVars->GID." AND ".
                $timetable.".GID = ".$this->settingVars->GID." AND ".
                // "ORDER_DUE_DATE IS NOT NULL AND " .
                $extraWhere .
                $this->commonQueryPart . 
                (!empty($this->queryPart) ? $this->queryPart : "") ." AND (" . 
                filters\timeFilter::$tyWeekRange . ") GROUP BY SKUID, ".$query_group.", ACCOUNT ORDER BY ORDER_DUE_DATE, TOTAL_ORDERS_CASE DESC";

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
                if($this->fullLayout){
                    $result[$key]['STAR_LINE'] = $data['STAR_LINE'];
                    $result[$key]['ORDER_DUE_DATE'] = date("jS M Y", strtotime($data['ORDER_DUE_DATE']));
                    $result[$key]['DEPOT_NUMBER'] = (int) $data['DEPOT_NUMBER'];

                }
                $result[$key]['TOTAL_ORDERS_CASE'] =(int) $data['TOTAL_ORDERS_CASE'];

                $result[$key]['DELIV_ON_TIME'] = (int) $data['DELIV_ON_TIME'];

                if($this->fileType == 'DC TO STORE WEEKLY'){
                    $result[$key]['DELIVERED'] = (int) $data['DELIVERED'];
                    $result[$key]['TOTAL_SHORTAGES'] = (int) $data['TOTAL_SHORTAGES'];
                } else {
                    $data['TOTAL_SHORTAGES'] = $data['TOTAL_ORDERS_CASE'] - $data['DELIV_ON_TIME'];
                    $result[$key]['TOTAL_SHORTAGES'] = (int) $data['TOTAL_SHORTAGES'];
                }
                
                if($this->fileType != 'FIRST HIT DAILY'){
                    $result[$key]['SHORTAGES'] = (int) $data['SHORTAGES'];
                }
                $result[$key]['FTA_TOTAL'] = (int) $data['FTA_TOTAL'];
                $result[$key]['FTA1'] = (int) $data['FTA1'];
                $result[$key]['FTA2'] = (int) $data['FTA2'];
                $result[$key]['FTA3'] = (int) $data['FTA3'];
                $result[$key]['FTA4'] = (int) $data['FTA4'];
                if($this->fileType == 'FIRST HIT DAILY' || $this->fileType == 'FIRST HIT WEEKLY' || $this->fileType == 'DC TO STORE WEEKLY'){
                    $result[$key]['FTA4SUPPLIER'] = (int) $data['FTA4SUPPLIER'];
                }
                $result[$key]['FTA5'] = (int) $data['FTA5'];
                $result[$key]['FTA6'] = (int) $data['FTA6'];
                $result[$key]['SUPPRESSED_QTY'] = (int) $data['SUPPRESSED_QTY'];
                $result[$key]['S2DCSL_PER'] = ($data['TOTAL_ORDERS_CASE'] != 0) ? ((($data['TOTAL_ORDERS_CASE']-$data['TOTAL_SHORTAGES'])/$data['TOTAL_ORDERS_CASE'])*100) : 0;
                $result[$key]['S2DCSL_PER'] = (double) number_format($result[$key]['S2DCSL_PER'], 1, '.', '');

                if($this->fileType == 'DC TO STORE WEEKLY'){
                    $result[$key]['DC2STORE_PER'] = ($data['TOTAL_ORDERS_CASE'] != 0) ? ((($data['TOTAL_ORDERS_CASE']-$data['TOTAL_SHORTAGES'])/$data['TOTAL_ORDERS_CASE'])*100) : 0;
                    $result[$key]['DC2STORE_PER'] = (double) number_format($result[$key]['DC2STORE_PER'], 1, '.', '');
                }

                $result[$key]['OTHER_SHORTAGES'] = $data['TOTAL_SHORTAGES'] - (($this->fileType != 'FIRST HIT DAILY' ? $result[$key]['SHORTAGES'] : 0)) - $result[$key]['SUPPRESSED_QTY'] - $result[$key]['FTA_TOTAL'];
            }

            $AllRowTotal['SKUID'] = '';
            $AllRowTotal['ACCOUNT'] = 'TOTAL';
            if($this->fullLayout){
                $AllRowTotal['STAR_LINE'] = '';
            }
            $AllRowTotal['TOTAL_ORDERS_CASE'] = array_sum(array_column($result,'TOTAL_ORDERS_CASE'));
            $AllRowTotal['TOTAL_SHORTAGES'] = array_sum(array_column($result,'TOTAL_SHORTAGES'));
            if($this->fileType != 'FIRST HIT DAILY'){
                $AllRowTotal['SHORTAGES'] = array_sum(array_column($result,'SHORTAGES'));
            }
            $AllRowTotal['FTA_TOTAL'] = array_sum(array_column($result,'FTA_TOTAL'));
            $AllRowTotal['FTA1'] = array_sum(array_column($result,'FTA1'));
            $AllRowTotal['FTA2'] = array_sum(array_column($result,'FTA2'));
            $AllRowTotal['FTA3'] = array_sum(array_column($result,'FTA3'));
            $AllRowTotal['FTA4'] = array_sum(array_column($result,'FTA4'));
            if($this->fileType == 'FIRST HIT DAILY' || $this->fileType == 'FIRST HIT WEEKLY' || $this->fileType == 'DC TO STORE WEEKLY'){
                $AllRowTotal['FTA4SUPPLIER'] = array_sum(array_column($result,'FTA4SUPPLIER'));
            }
            $AllRowTotal['FTA5'] = array_sum(array_column($result,'FTA5'));
            $AllRowTotal['FTA6'] = array_sum(array_column($result,'FTA6'));
            $AllRowTotal['SUPPRESSED_QTY'] = array_sum(array_column($result,'SUPPRESSED_QTY'));
            $AllRowTotal['S2DCSL_PER'] = ($AllRowTotal['TOTAL_ORDERS_CASE'] != 0) ? ((($AllRowTotal['TOTAL_ORDERS_CASE']-$AllRowTotal['TOTAL_SHORTAGES'])/$AllRowTotal['TOTAL_ORDERS_CASE'])*100) : 0;
            $AllRowTotal['S2DCSL_PER'] = (double) number_format($AllRowTotal['S2DCSL_PER'], 1, '.', '');

            $AllRowTotal['OTHER_SHORTAGES'] = array_sum(array_column($result,'OTHER_SHORTAGES'));

            array_unshift($result,$AllRowTotal);
        }

        $this->jsonOutput['bottomGridData'] = $result;
    }


    /**
     * skuChange()
     * This Function is used to retrieve list based on set parameters     
     * @access private  
     * @return $this->jsonOutput with all data that should be sent to client app     
     */
    private function supressedGridData($isGridOnly = false) {

        $this->queryPart = filters\productAndMarketFilter::include_product_and_market_filters($this->queryVars,$this->settingVars);
        if(isset($_REQUEST['SKUID']) && !empty(urldecode($_REQUEST['SKUID'])))
            $this->queryPart .=" AND ".$this->accountIdField." = '".trim(urldecode($_REQUEST['SKUID']))."' ";

        $poTable    = $this->settingVars->tesco_po_details;
        $timetable  = $this->settingVars->timetable;
        $skutable   = $this->settingVars->skutable;
        
        $poTable = $this->settingVars->tesco_po_details;
        $timetable = $this->settingVars->timetable;
        $skutable = $this->settingVars->skutable;

        $mydateSelectedField = ($this->fileType == 'FIRST HIT DAILY') ? 'order_due_date' : 'mydate';
        $extraWhere = '';
        if($isGridOnly && isset($_REQUEST['selectedMyDate']) && !empty($_REQUEST['selectedMyDate'])) {
            $orderDueDate = date("Y-m-d", strtotime($_REQUEST['selectedMyDate']));
            $extraWhere .= ' '.$mydateSelectedField.' = '. "'$orderDueDate' AND ";
            $isAllData = false;
        }

        $fileType = " file_type = 'SUPRESSED DAILY'";
        $query_group = "SUPRESSED_DUE_DATE, DEPOT_ID";
        
        $query = "SELECT ".$this->accountIdField." AS SKUID " .
                ",".$this->accountNameField." AS ACCOUNT " .
                ", order_due_date AS SUPRESSED_DUE_DATE" . 
                ",SUM(due_days) AS LEAD_TIME ".
                ",MAX(DATE_ADD(order_due_date, INTERVAL -due_days DAY)) AS SUPRESSED_RAISED_DATE" .
                ", depot_number DEPOT_ID" . 
                ",MAX(suppressed_qty) SUPRESSED_QTY" . 
                " FROM ".$poTable.", ".$skutable.", ".$timetable. 
                " WHERE ".$poTable.".GID = ".$skutable.".GID AND ". 
                $poTable.".skuID = ".$skutable.".PIN AND ".
                $poTable.".GID = ".$timetable.".GID AND ".
                $poTable.".".$this->settingVars->dateperiod." = ".$timetable.".".$this->settingVars->dateperiod." AND ".
                $skutable.".clientID = '".$this->settingVars->clientID."' AND ".
                $skutable.".hide <> 1 AND ".
                $skutable.".GID = ".$this->settingVars->GID." AND ".
                $timetable.".GID = ".$this->settingVars->GID." AND ".
                $extraWhere .
                $fileType . 
                (!empty($this->queryPart) ? $this->queryPart : "") ." AND (" . 
                filters\timeFilter::$tyWeekRange . ") GROUP BY SKUID, ".$query_group.", ACCOUNT ORDER BY SUPRESSED_DUE_DATE DESC";

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
                $result[$key]['SUPRESSED_DUE_DATE'] = date("jS M Y", strtotime($data['SUPRESSED_DUE_DATE']));
                $result[$key]['LEAD_TIME'] = (int) $data['LEAD_TIME'];
                $result[$key]['SUPRESSED_RAISED_DATE'] = date("jS M Y", strtotime($data['SUPRESSED_RAISED_DATE']));
                $result[$key]['DEPOT_ID'] = (int) $data['DEPOT_ID'];
                $result[$key]['SUPRESSED_QTY'] = (int) $data['SUPRESSED_QTY'];
            }

            $AllRowTotal['SKUID'] = '';
            $AllRowTotal['ACCOUNT'] = 'TOTAL';

            $AllRowTotal['LEAD_TIME'] = array_sum(array_column($result,'LEAD_TIME'));
            $AllRowTotal['SUPRESSED_QTY'] = array_sum(array_column($result,'SUPRESSED_QTY'));
            array_unshift($result,$AllRowTotal);
        }
        $this->jsonOutput['supressedGridData'] = $result;
    }


    public function rightPieChartData($isPieChartOnly = false) {
        $this->queryPart = filters\productAndMarketFilter::include_product_and_market_filters($this->queryVars,$this->settingVars);
        if(isset($_REQUEST['SKUID']) && !empty(urldecode($_REQUEST['SKUID'])))
            $this->queryPart .=" AND ".$this->accountIdField." = '".trim(urldecode($_REQUEST['SKUID']))."' ";

        $poTable    = $this->settingVars->tesco_po_details;
        $timetable  = $this->settingVars->timetable;
        $skutable   = $this->settingVars->skutable;
        $extraWhere = '';

        $mydateSelectedField = ($this->fileType == 'FIRST HIT DAILY') ? 'order_due_date' : 'mydate';
        if($isPieChartOnly && isset($_REQUEST['selectedMyDate']) && !empty($_REQUEST['selectedMyDate'])) {
            $orderDueDate = date("Y-m-d", strtotime($_REQUEST['selectedMyDate']));
            $extraWhere .= ' AND '.$mydateSelectedField.' = '. "'$orderDueDate'";
            $isAllData = false;
        }
        $query_group_data = '';

        if($this->fileType == 'DC TO STORE WEEKLY'){
            $query = "SELECT DISTINCT depot_number AS DEPOT_NUMBER, SUM(fta_code1_cases+fta_code2_cases+fta_code3_cases+fta_code4_primary_cases+fta_code5_cases+back_scheduled_cases+suppressed_qty+total_shortage_cases) AS CHART_VALUE";
        } else {
            $query = "SELECT DISTINCT depot_number AS DEPOT_NUMBER, SUM(fta_code1_cases+fta_code2_cases+fta_code3_cases+fta_code4_primary_cases+fta_code5_cases+back_scheduled_cases+suppressed_qty+short_delivered_cases) AS CHART_VALUE";
        }
    
        $query .= " FROM ".$poTable.", ".$skutable.", ".$timetable.
                            " WHERE ".$poTable.".GID = ".$skutable.".GID AND ".
                            $poTable.".skuID = ".$skutable.".PIN AND ".
                            $poTable.".GID = ".$timetable.".GID AND ".
                            $poTable.".".$this->settingVars->dateperiod." = ".$timetable.".".$this->settingVars->dateperiod." AND ".
                            $skutable.".clientID = '".$this->settingVars->clientID."' AND ".
                            $skutable.".hide <> 1 AND ".
                            $skutable.".GID = ".$this->settingVars->GID." AND ".
                            $timetable.".GID = ".$this->settingVars->GID." AND ".
                            $this->commonQueryPart . 
                            (!empty($this->queryPart) ? $this->queryPart : "") ." AND (" . 
                            filters\timeFilter::$tyWeekRange . ") ".
                            $extraWhere
                            .$query_group_data;
        
        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }

        
        if (is_array($result) && !empty($result)) {
            $array_value_sum = array_sum(array_column($result, 'CHART_VALUE'));
            if($array_value_sum != 0){
                $this->jsonOutput['PieChartRight'] = $result;    
            } else {
                $this->jsonOutput['PieChartRight'] = [];
            }
        } else {
            $this->jsonOutput['PieChartRight'] = [];
        }
    }

    private function skuChange($isPieChartOnly = false, $cummulativeFlag = false) {
        
        $this->queryPart = filters\productAndMarketFilter::include_product_and_market_filters($this->queryVars,$this->settingVars);
        if(isset($_REQUEST['SKUID']) && !empty(urldecode($_REQUEST['SKUID'])))
            $this->queryPart .=" AND ".$this->accountIdField." = '".trim(urldecode($_REQUEST['SKUID']))."' ";

        $poTable    = $this->settingVars->tesco_po_details;
        $timetable  = $this->settingVars->timetable;
        $skutable   = $this->settingVars->skutable;
        $extraWhere = ''; $isAllData = true;

        $mydateSelectedField = ($this->fileType == 'FIRST HIT DAILY') ? 'order_due_date' : 'mydate';

        if($isPieChartOnly && isset($_REQUEST['selectedMyDate']) && !empty($_REQUEST['selectedMyDate'])) {
            $orderDueDate = date("Y-m-d", strtotime($_REQUEST['selectedMyDate']));
            $extraWhere .= ' AND '.$mydateSelectedField.' = '. "'$orderDueDate'";
            $isAllData = false;
        } else if ($isPieChartOnly && isset($_REQUEST['selectedYear']) && !empty($_REQUEST['selectedYear']) && isset($_REQUEST['selectedWeek']) && !empty($_REQUEST['selectedYear'])) {
            $selectedYear = $_REQUEST['selectedYear'];
            $selectedWeek = $_REQUEST['selectedWeek'];
            $extraWhere .= ' AND '.$this->settingVars->yearperiod.' = '.$selectedYear.' AND '.$this->settingVars->weekperiod.' = '.$selectedWeek;
            $isAllData = false;
        }

        $query_fields_data = $this->settingVars->yearperiod . " AS YEAR" .",". $this->settingVars->weekperiod . " AS WEEK" . ",SUM(fta_code1_cases+fta_code2_cases+fta_code3_cases+fta_code4_primary_cases+fta_code5_cases+total_shortage_cases+suppressed_qty) AS TOTAL_SHORTAGES ".",SUM(total_shortage_cases) AS SHORTAGES,SUM(fta_code1_cases+fta_code2_cases+fta_code3_cases+fta_code4_primary_cases+fta_code5_cases+fta_code6_cases) AS FTA_TOTAL ";
        $query_group_data = " GROUP BY YEAR, WEEK ORDER BY YEAR ASC,WEEK ASC";

        if($this->fileType == 'FIRST HIT DAILY'){
            $query_fields_data = "order_due_date AS ORDER_DUE_DATE" . ", SUM(fta_code4_supplier_cases) AS FTA4SUPPLIER , SUM(fta_code1_cases+fta_code2_cases+fta_code3_cases+fta_code4_primary_cases+fta_code5_cases+back_scheduled_cases+suppressed_qty) AS TOTAL_SHORTAGES ,SUM(fta_code4_supplier_cases) AS FTA4SUPPLIER,SUM(fta_code1_cases+fta_code2_cases+fta_code3_cases+fta_code4_primary_cases+fta_code4_supplier_cases+fta_code5_cases+fta_code6_cases+fta_code9_cases) AS FTA_TOTAL ";
             $query_group_data = " GROUP BY ORDER_DUE_DATE ORDER BY ORDER_DUE_DATE ASC";
        }
        
        if($this->fileType == 'FIRST HIT WEEKLY'){
            $query_fields_data = "mydate AS ORDER_DUE_DATE" . ", SUM(fta_code4_supplier_cases) AS FTA4SUPPLIER, SUM(fta_code1_cases+fta_code2_cases+fta_code3_cases+fta_code4_primary_cases+fta_code5_cases+total_shortage_cases+suppressed_qty) AS TOTAL_SHORTAGES ".",SUM(total_shortage_cases) AS SHORTAGES,SUM(fta_code1_cases+fta_code2_cases+fta_code3_cases+fta_code4_primary_cases+fta_code5_cases+fta_code6_cases+fta_code4_supplier_cases) AS FTA_TOTAL ";
            $query_group_data = " GROUP BY mydate ORDER BY mydate ASC";
            $extraWhere .= " AND mydate IS NOT NULL ";
        }

        if($this->fileType == 'DC TO STORE WEEKLY'){
            $query_fields_data = "mydate AS ORDER_DUE_DATE" . ", SUM(fta_code4_supplier_cases) AS FTA4SUPPLIER ".",SUM(short_delivered_cases) AS SHORTAGES,SUM(fta_code1_cases+fta_code2_cases+fta_code3_cases+fta_code4_primary_cases+fta_code5_cases+fta_code6_cases+fta_code4_supplier_cases) AS FTA_TOTAL, SUM(total_delivered_cases) AS DELIVERED, SUM(total_shortage_cases) AS TOTAL_SHORTAGES ";
            $query_group_data = " GROUP BY mydate ORDER BY mydate ASC";
            $extraWhere .= " AND mydate IS NOT NULL ";
        }


        $query = "SELECT " .
                $query_fields_data.
                ",SUM(total_orders_cases) AS TOTAL_ORDERS_CASE ".
                // ",SUM(total_shortage_cases) AS SHORTAGES ".
                ",SUM(suppressed_qty) AS SUPPRESSED_QTY ". 
                ",SUM(fta_code1_cases) AS FTA1 ".
                ",SUM(fta_code2_cases) AS FTA2 ".
                ",SUM(fta_code3_cases) AS FTA3 ".
                ",SUM(fta_code4_primary_cases) AS FTA4 ".
                ",SUM(fta_code5_cases) AS FTA5 ".
                ",SUM(fta_code6_cases) AS FTA6 ".
                ",SUM(delivered_on_time) AS DELIV_ON_TIME ". 
                " FROM ".$poTable.", ".$skutable.", ".$timetable.
                " WHERE ".$poTable.".GID = ".$skutable.".GID AND ".
                $poTable.".skuID = ".$skutable.".PIN AND ".
                $poTable.".GID = ".$timetable.".GID AND ".
                $poTable.".".$this->settingVars->dateperiod." = ".$timetable.".".$this->settingVars->dateperiod." AND ".
                $skutable.".clientID = '".$this->settingVars->clientID."' AND ".
                $skutable.".hide <> 1 AND ".
                $skutable.".GID = ".$this->settingVars->GID." AND ".
                $timetable.".GID = ".$this->settingVars->GID." AND ".
                $this->commonQueryPart . 
                (!empty($this->queryPart) ? $this->queryPart : "") ." AND (" . 
                filters\timeFilter::$tyWeekRange . ") ".
                $extraWhere
                .$query_group_data;

        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }

        if (is_array($result) && !empty($result)) {
            $pieChartData = []; $pieChartMyDateDD = $pieChartTitleData =[];
            $totalOrderCases = $deliveOnTime = $delivered = 0;
            foreach($result as $key => $data) {

                if (!$isPieChartOnly){
                    
                    // $result[$key]['TOTAL_ORDERS_CASE'] = (int)$data['TOTAL_ORDERS_CASE'];
                    $result[$key]['DELIV_ON_TIME']     = (int) $data['DELIV_ON_TIME'];


                    if($this->fileType == 'DC TO STORE WEEKLY'){
                        $result[$key]['DELIVERED'] = (int) $data['DELIVERED'];
                        $result[$key]['TOTAL_SHORTAGES'] = (int) $data['TOTAL_SHORTAGES'];
                    } else {
                        $data['TOTAL_SHORTAGES'] = $data['TOTAL_ORDERS_CASE'] - $data['DELIV_ON_TIME'];
                        $result[$key]['TOTAL_SHORTAGES'] = (int) $data['TOTAL_SHORTAGES'];
                    }

                    $result[$key]['SUPPRESSED_QTY']     = (int) $data['SUPPRESSED_QTY'];

                    if($cummulativeFlag == 'true') {
                        $totalOrderCases = $totalOrderCases + $result[$key]['TOTAL_ORDERS_CASE'];
                        $deliveOnTime = $deliveOnTime + $result[$key]['DELIV_ON_TIME'];

                        $result[$key]['TOTAL_ORDERS_CASE'] = $totalOrderCases;

                        $result[$key]['S2DCSL_PER'] = ($totalOrderCases != 0 && $deliveOnTime != 0) ? ($deliveOnTime / $totalOrderCases) * 100 : 0;
                        $result[$key]['S2DCSL_PER'] = (double) number_format($result[$key]['S2DCSL_PER'], 1, '.', '');

                        if($this->fileType == 'DC TO STORE WEEKLY'){
                            $delivered = $delivered + $result[$key]['DELIVERED'];

                            $result[$key]['DC2STORE_PER'] = ($totalOrderCases != 0 && $delivered != 0) ? ($delivered / (int)$data['TOTAL_ORDERS_CASE']) * 100 : 0;
                            $result[$key]['DC2STORE_PER']        = (double) number_format($result[$key]['DC2STORE_PER'], 1, '.', '');
                        }
                    } else {
                        $result[$key]['TOTAL_ORDERS_CASE'] = (int)$data['TOTAL_ORDERS_CASE'];

                        $result[$key]['S2DCSL_PER'] = ($result[$key]['TOTAL_ORDERS_CASE'] != 0 && $result[$key]['DELIV_ON_TIME'] != 0) ? ($result[$key]['DELIV_ON_TIME'] / $result[$key]['TOTAL_ORDERS_CASE']) * 100 : 0;
                        $result[$key]['S2DCSL_PER']        = (double) number_format($result[$key]['S2DCSL_PER'], 1, '.', '');

                        if($this->fileType == 'DC TO STORE WEEKLY'){
                            $result[$key]['DC2STORE_PER'] = ($result[$key]['TOTAL_ORDERS_CASE'] != 0 && $result[$key]['DELIVERED'] != 0) ? ($result[$key]['DELIVERED'] / $result[$key]['TOTAL_ORDERS_CASE']) * 100 : 0;
                            $result[$key]['DC2STORE_PER']        = (double) number_format($result[$key]['DC2STORE_PER'], 1, '.', '');
                        }
                    }

                    


                    if($this->fullLayout){
                        $result[$key]['MYDATE']            = date("jS M Y", strtotime($data['ORDER_DUE_DATE']));
                        $pieChartMyDateDD[$data['ORDER_DUE_DATE']] = ['YEAR'=>$data['YEAR'], 'DUE_DATE_LABEL'=>$data['ORDER_DUE_DATE'], 'LABEL' => date("jS M Y", strtotime($data['ORDER_DUE_DATE']))];
                    } else {
                        $result[$key]['MYDATE']            = $data['WEEK'].'-'.$data['YEAR'];  
                        $pieChartMyDateDD[$data['WEEK'].'-'.$data['YEAR']] = ['WEEK'=>$data['WEEK'], 'YEAR'=>$data['YEAR'], 'LABEL'=>$data['WEEK'].'-'.$data['YEAR']];
                    }

                }else{
                    //if(!$isAllData){
                        if($this->fullLayout){
                            $pieChartTitleData['STAR_LINE']   += (int) $data['STAR_LINE'];
                        }

                        $pieChartTitleData['TOTAL_ORDERS_CASE'] += (int) $data['TOTAL_ORDERS_CASE'];
                        $pieChartTitleData['DELIV_ON_TIME'] += (int) $data['DELIV_ON_TIME'];

                        if($this->fileType == 'DC TO STORE WEEKLY'){
                            $pieChartTitleData['DELIVERED'] += (int) $data['DELIVERED'];
                            $pieChartTitleData['TOTAL_SHORTAGES'] += (int) $data['TOTAL_SHORTAGES'];
                        } else {
                            $data['TOTAL_SHORTAGES'] = $data['TOTAL_ORDERS_CASE'] - $data['DELIV_ON_TIME'];
                            $pieChartTitleData['TOTAL_SHORTAGES'] += (int) $data['TOTAL_SHORTAGES'];
                        }

                        $pieChartTitleData['SUPPRESSED_QTY']    += (int) $data['SUPPRESSED_QTY'];
                    //}
                }

                if($this->fileType != 'FIRST HIT DAILY'){
                    $pieChartData['SHORTAGES']      += $data['SHORTAGES'];
                }
                $pieChartData['TOTAL_ORDERS_CASE'] += $data['TOTAL_ORDERS_CASE'];
                $pieChartData['FTA1'] += $data['FTA1'];
                $pieChartData['FTA2'] += $data['FTA2'];
                $pieChartData['FTA3'] += $data['FTA3'];
                $pieChartData['FTA4'] += $data['FTA4'];

                if($this->fileType == 'FIRST HIT DAILY' || $this->fileType == 'FIRST HIT WEEKLY' || $this->fileType == 'DC TO STORE WEEKLY'){
                    $pieChartData['FTA4SUPPLIER'] += $data['FTA4SUPPLIER'];
                }
                $pieChartData['FTA5'] += $data['FTA5'];
                $pieChartData['FTA6'] += $data['FTA6'];
                $pieChartData['DELIV_ON_TIME'] += $data['DELIV_ON_TIME'];

                if($this->fileType == 'DC TO STORE WEEKLY'){
                    $pieChartData['DELIVERED'] += $data['DELIVERED'];
                    $pieChartData['TOTAL_SHORTAGES'] += $data['TOTAL_SHORTAGES'];
                } else {
                    $data['TOTAL_SHORTAGES'] = $data['TOTAL_ORDERS_CASE'] - $data['DELIV_ON_TIME'];
                    $pieChartData['TOTAL_SHORTAGES'] += $data['TOTAL_SHORTAGES'];
                }
                
                $pieChartData['FTA_TOTAL']      += $data['FTA_TOTAL'];
                $pieChartData['SUPPRESSED_QTY']      += $data['SUPPRESSED_QTY'];
   
            }
            
            if($this->fileType == 'DC TO STORE WEEKLY'){
                $pieChartData['OTHER_SHORTAGES'] = $pieChartData['TOTAL_SHORTAGES'] - $pieChartData['SHORTAGES'];
            } else {
                $pieChartData['OTHER_SHORTAGES'] = $pieChartData['TOTAL_SHORTAGES'] - (($this->fileType != 'FIRST HIT DAILY' ? $pieChartData['SHORTAGES'] : 0)) - $pieChartData['SUPPRESSED_QTY'] - $pieChartData['FTA_TOTAL'];
            }

            if ($isPieChartOnly){
                // $pieChartTitleData['S2DCSL_PER']        = ($pieChartTitleData['TOTAL_ORDERS_CASE'] != 0) ? ((($pieChartTitleData['TOTAL_ORDERS_CASE']-$pieChartTitleData['TOTAL_SHORTAGES'])/$pieChartTitleData['TOTAL_ORDERS_CASE'])*100) : 0;

                $pieChartTitleData['S2DCSL_PER'] = ($pieChartTitleData['TOTAL_ORDERS_CASE'] != 0 && $pieChartTitleData['DELIV_ON_TIME'] != 0) ? ($pieChartTitleData['DELIV_ON_TIME'] / $pieChartTitleData['TOTAL_ORDERS_CASE']) * 100 : 0;
                $pieChartTitleData['S2DCSL_PER']        = (double) number_format($pieChartTitleData['S2DCSL_PER'], 1, '.', '');

                if($this->fileType == 'DC TO STORE WEEKLY'){
                    $pieChartTitleData['DC2STORE_PER'] = ($pieChartTitleData['TOTAL_ORDERS_CASE'] != 0 && $pieChartTitleData['DELIVERED'] != 0) ? ($pieChartTitleData['DELIVERED'] / $pieChartTitleData['TOTAL_ORDERS_CASE']) * 100 : 0;
                    $pieChartTitleData['DC2STORE_PER']        = (double) number_format($pieChartTitleData['DC2STORE_PER'], 1, '.', '');
                } else {

                }
                $this->jsonOutput['pieChartTitleData'] = $pieChartTitleData;
            }

            if (array_sum($pieChartData) > 0) {
                if (!$isPieChartOnly){
                    $pieChartMyDateDD = array_values($pieChartMyDateDD);
                    array_unshift($pieChartMyDateDD,['WEEK'=>'ALL', 'YEAR'=>'ALL', 'LABEL'=>'ALL']);
                }

                if($this->fileType != 'DC TO STORE WEEKLY') {
                    $this->jsonOutput['PieChart'] = [['label'=>'FTA1','value'=>$pieChartData['FTA1']],
                                                     ['label'=>'FTA2','value'=>$pieChartData['FTA2']],
                                                     ['label'=>'FTA3','value'=>$pieChartData['FTA3']],
                                                     ['label'=>'FTA4 Primary','value'=>$pieChartData['FTA4']]
                                                    ];
                }
                if($this->fileType == 'FIRST HIT DAILY' || $this->fileType == 'FIRST HIT WEEKLY'){
                    $this->jsonOutput['PieChart'][] = ['label'=>'FTA4 Supplier','value'=>$pieChartData['FTA4SUPPLIER']];
                }

                if($this->fileType != 'DC TO STORE WEEKLY') {
                    $this->jsonOutput['PieChart'][] = ['label'=>'FTA5','value'=>$pieChartData['FTA5']];
                    $this->jsonOutput['PieChart'][] = ['label'=>'FTA6','value'=>$pieChartData['FTA6']];
                }

                $shortageLable = ($this->fileType == 'DC TO STORE WEEKLY') ? 'Pick Refuals' : 'Shortages';

                if($this->fileType != 'FIRST HIT DAILY'){
                    $this->jsonOutput['PieChart'][] = ['label'=>$shortageLable,'value'=>$pieChartData['SHORTAGES']];
                }
                if($this->fileType != 'DC TO STORE WEEKLY') {
                    $this->jsonOutput['PieChart'][] = ['label'=>'Suppressed Qty','value'=>$pieChartData['SUPPRESSED_QTY']];
                }
                $this->jsonOutput['PieChart'][] = ['label'=>'Other Shortages','value'=>$pieChartData['OTHER_SHORTAGES']];
            } else {
                $this->jsonOutput['PieChart'] = [];
                $pieChartMyDateDD = [];
            }

            if (!$isPieChartOnly){
                $this->jsonOutput['LineChart'] = $result;
                $this->jsonOutput['pieChartMyDateDD'] = $pieChartMyDateDD;
            }
        } else {
            $this->jsonOutput['PieChart'] = [];
            if (!$isPieChartOnly) {
                $this->jsonOutput['LineChart'] = [];
                $this->jsonOutput['pieChartMyDateDD'] = [];
            }
        }
    }
	
    public function buildDataArray($fields) {
        if (empty($fields))
            return false;

        $configurationCheck = new config\ConfigurationCheck($this->settingVars, $this->queryVars);
        $configurationCheck->buildDataArray($fields);
        $this->dbColumnsArray = $configurationCheck->dbColumnsArray;
        $this->displayCsvNameArray = $configurationCheck->displayCsvNameArray;
        $this->displayDbColumnArray = $configurationCheck->displayDbColumnArray;
        return;
    }

    public function buildPageArray() {

        $fetchConfig = false;
        $gridFieldPart = explode("#", $this->accountField);

        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
            $fetchConfig = true;
            $this->jsonOutput['pageConfig'] = array(
                'enabledFilters' => $this->getPageConfiguration('filter_settings', $this->settingVars->pageID)
            );
            $this->jsonOutput['fileType'] = $this->fileType;

            $SKUID =  (count($gridFieldPart) > 1) ? $this->displayCsvNameArray[$gridFieldPart[1]] : $this->displayCsvNameArray[$gridFieldPart[0]];
            $topGridColumns['SKUID']             = ['name'=>$SKUID,           'type'=>'string','size'=>170, 'filter' => 'agTextColumnFilter'];
            $topGridColumns['ACCOUNT']           = ['name'=>$this->displayCsvNameArray[$gridFieldPart[0]],'type'=>'string','size'=>170, 'filter' => 'agTextColumnFilter'];
            if($this->fullLayout){
               $topGridColumns['STAR_LINE']         = ['name'=>"STAR LINE?",   'type'=>'string', 'size'=>100, 'filter' => 'agTextColumnFilter'];
               $topGridColumns['ORDER_DUE_DATE']    = ['name'=>"ORDER DUE DATE",   'type'=>'string', 'size'=>130, 'filter' => 'agTextColumnFilter'];
               $topGridColumns['DEPOT_NUMBER']      = ['name'=>"DC",   'type'=>'number', 'size'=>100, 'decimalPoints'=>0, 'filter' => 'agNumberColumnFilter'];
               
            }

            if($this->fileType == 'DC TO STORE WEEKLY') {
                $topGridColumns['TOTAL_ORDERS_CASE'] = ['name'=>"STORE DEMAND",   'type'=>'number', 'size'=>100, 'decimalPoints'=>0, 'filter' => 'agNumberColumnFilter'];
                $topGridColumns['DELIVERED']         = ['name'=>"DELIVERED",   'type'=>'number', 'size'=>100, 'decimalPoints'=>0, 'filter' => 'agNumberColumnFilter'];
                $topGridColumns['SHORTAGES']         = ['name'=>"PICK REFUSALS",      'type'=>'number', 'size'=>100, 'decimalPoints'=>0, 'filter' => 'agNumberColumnFilter'];
                $topGridColumns['TOTAL_SHORTAGES']   = ['name'=>"TOTAL SHORTAGES",'type'=>'number', 'size'=>100, 'decimalPoints'=>0, 'filter' => 'agNumberColumnFilter'];
                $topGridColumns['DC2STORE_PER']      = ['name'=>"DC2STORE SL%",       'type'=>'number', 'size'=>100, 'decimalPoints'=>1, 'filter' => 'agNumberColumnFilter'];
            } else {
                $topGridColumns['TOTAL_ORDERS_CASE'] = ['name'=>"TOTAL ORDERS",   'type'=>'number', 'size'=>100, 'decimalPoints'=>0, 'filter' => 'agNumberColumnFilter'];
                $topGridColumns['DELIV_ON_TIME'] = ['name'=>"DELIV ON TIME",   'type'=>'number', 'size'=>100, 'decimalPoints'=>0, 'filter' => 'agNumberColumnFilter'];
                $topGridColumns['TOTAL_SHORTAGES']   = ['name'=>"TOTAL SHORTAGES",'type'=>'number', 'size'=>100, 'decimalPoints'=>0, 'filter' => 'agNumberColumnFilter'];
                $topGridColumns['S2DCSL_PER']        = ['name'=>"S2DC SL%",       'type'=>'number', 'size'=>100, 'decimalPoints'=>1, 'filter' => 'agNumberColumnFilter'];
            }
            
            // $topGridColumns['TOTAL_SHORTAGES']   = ['name'=>"TOTAL SHORTAGES",'type'=>'number', 'size'=>100, 'decimalPoints'=>0, 'filter' => 'agNumberColumnFilter'];
            // $topGridColumns['S2DCSL_PER']        = ['name'=>"S2DC SL%",       'type'=>'number', 'size'=>100, 'decimalPoints'=>1, 'filter' => 'agNumberColumnFilter'];


            if(!in_array($this->fileType, array('FIRST HIT DAILY' , 'DC TO STORE WEEKLY' ))) {
                $topGridColumns['SHORTAGES']         = ['name'=>"SHORTAGES",      'type'=>'number', 'size'=>100, 'decimalPoints'=>0, 'filter' => 'agNumberColumnFilter'];
            }

            if($this->fileType != 'DC TO STORE WEEKLY') {
                $topGridColumns['SUPPRESSED_QTY']    = ['name'=>"SUPPRESSED",     'type'=>'number', 'size'=>100, 'decimalPoints'=>0, 'filter' => 'agNumberColumnFilter'];
                $topGridColumns['FTA_TOTAL']         = ['name'=>"FTA TOTAL",      'type'=>'number', 'size'=>100, 'decimalPoints'=>0, 'filter' => 'agNumberColumnFilter'];
                $topGridColumns['FTA1']              = ['name'=>"FTA 1",          'type'=>'number', 'size'=>100, 'decimalPoints'=>0, 'filter' => 'agNumberColumnFilter'];
                $topGridColumns['FTA2']              = ['name'=>"FTA 2",          'type'=>'number', 'size'=>100, 'decimalPoints'=>0, 'filter' => 'agNumberColumnFilter'];
                $topGridColumns['FTA3']              = ['name'=>"FTA 3",          'type'=>'number', 'size'=>100, 'decimalPoints'=>0, 'filter' => 'agNumberColumnFilter'];
                $topGridColumns['FTA4']              = ['name'=>"FTA 4 Primary",  'type'=>'number', 'size'=>100, 'decimalPoints'=>0, 'filter' => 'agNumberColumnFilter'];
                if($this->fileType == 'FIRST HIT DAILY' || $this->fileType == 'FIRST HIT WEEKLY' || $this->fileType == 'DC TO STORE WEEKLY'){
                    $topGridColumns['FTA4SUPPLIER']      = ['name'=>"FTA 4 Supplier", 'type'=>'number', 'size'=>100, 'decimalPoints'=>0, 'filter' => 'agNumberColumnFilter'];
                }
                $topGridColumns['FTA5']              = ['name'=>"FTA 5",          'type'=>'number', 'size'=>100, 'decimalPoints'=>0, 'filter' => 'agNumberColumnFilter'];
                $topGridColumns['FTA6']              = ['name'=>"FTA 6",          'type'=>'number', 'size'=>100, 'decimalPoints'=>0, 'filter' => 'agNumberColumnFilter'];
                $topGridColumns['OTHER_SHORTAGES']   = ['name'=>"OTHER SHORTAGES", 'type'=>'number', 'size'=>100, 'decimalPoints'=>0, 'filter' => 'agNumberColumnFilter'];
            }

            $this->jsonOutput['topGridColumns']  = $topGridColumns;

            $supressedGridColumns['SKUID']             = ['name'=>$SKUID,           'type'=>'string','size'=>170, 'filter' => 'agTextColumnFilter'];
            $supressedGridColumns['ACCOUNT']           = ['name'=>$this->displayCsvNameArray[$gridFieldPart[0]],'type'=>'string','size'=>170, 'filter' => 'agTextColumnFilter'];
            $supressedGridColumns['SUPRESSED_DUE_DATE']    = ['name'=>"SUPRESSED DUE DATE",   'type'=>'string', 'size'=>130, 'filter' => 'agTextColumnFilter'];
            $supressedGridColumns['LEAD_TIME']      = ['name'=>"LEAD TIME",   'type'=>'number', 'size'=>100, 'decimalPoints'=>0, 'filter' => 'agNumberColumnFilter'];
            $supressedGridColumns['SUPRESSED_RAISED_DATE']    = ['name'=>"SUPRESSED RAISED DATE",   'type'=>'string', 'size'=>130, 'filter' => 'agTextColumnFilter'];
            $supressedGridColumns['DEPOT_ID']      = ['name'=>"DEPOT ID",   'type'=>'number', 'size'=>100, 'decimalPoints'=>0, 'filter' => 'agNumberColumnFilter'];
            $supressedGridColumns['SUPRESSED_QTY']      = ['name'=>"SUPRESSED QTY",   'type'=>'number', 'size'=>100, 'decimalPoints'=>0, 'filter' => 'agNumberColumnFilter'];

            $this->jsonOutput['supressedGridColumns']  = $supressedGridColumns;
        }

        $gridField = strtoupper($this->dbColumnsArray[$gridFieldPart[0]]);
        $gridField = (count($gridFieldPart) > 1) ? strtoupper($gridField . "_" . $this->dbColumnsArray[$gridFieldPart[1]]) : $gridField;

        $this->accountIdField = (isset($this->settingVars->dataArray[$gridField]) && isset($this->settingVars->dataArray[$gridField]['ID'])) ? 
            $this->settingVars->dataArray[$gridField]['ID'] : $this->settingVars->dataArray[$gridField]['NAME'];
        $this->accountNameField = $this->settingVars->dataArray[$gridField]['NAME'];

        return;
    }
	
}
?>