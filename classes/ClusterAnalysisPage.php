<?php

namespace classes;

use projectsettings;
use datahelper;
use filters;
use db;
use config;
use utils;

class ClusterAnalysisPage extends config\UlConfig {

    private $maps;
    private $querypart;
    private $TOTAL_TY_SALES;
    private $pageName;
    private $dbColumnsArray;
    private $displayCsvNameArray;
    private $isYearWeekPeriodActive = true;
    public $requestAccountField;

    /*     * ***
     * Default gateway function, should be similar in all DATA CLASS
     * arguments:
     *  $settingVars [project settingsGateway variables]
     * @return $jsonOutput with all data that should be sent to client app
     * *** */

    public function go($settingVars) {
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES

        $this->settingVars->pageName = (empty($this->settingVars->pageName)) ? $this->settingVars->pageID . '_CLUSTER_ANALYSIS' : $settingVars->pageName;
        $this->redisCache = new utils\RedisCache($this->queryVars);

        if (!isset($this->settingVars->yearperiod) && !isset($this->settingVars->weekperiod))
            $this->isYearWeekPeriodActive = false;

        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true')
        {
            $this->getAllClusters();
            $showCustomerSelection = $this->getPageConfiguration('show_customer_selection', $this->settingVars->pageID)[0];
            if($showCustomerSelection == "true"){
                $this->getCustomerList();
            } else {
                $_REQUEST['GID'] = $this->settingVars->GID;
                $this->getSkuList();
            }
            
            $this->jsonOutput['showCustomerSelection'] = $showCustomerSelection;
            $tables = $this->getPageConfiguration('table_field', $this->settingVars->pageID)[0];
            $this->jsonOutput['selectedTable'] = $tables;
            
            $this->prepareFieldsFromFieldSelectionSettings([$tables]);
            $this->getGridCols();
            $this->jsonOutput['pageConfig'] = array('enabledFilters' => $this->getPageConfiguration('filter_settings', $this->settingVars->pageID));
        }

        $this->msqName = $this->settingVars->dataArray['F14']['NAME'];
        $this->ohqName = $this->settingVars->dataArray['F12']['NAME'];
        $this->gsqName = $this->settingVars->dataArray['F9']['NAME'];
        $this->ohaqName = $this->settingVars->dataArray['F10']['NAME'];
        $this->baqName = $this->settingVars->dataArray['F11']['NAME'];
 
        switch($_REQUEST['action'])
        {
            case "getDataForClusterAnalysis":
                $this->getDataForClusterAnalysis();
                break;
            
            case "getDataForClusterAnalysisGridData":
                $this->getDataForClusterAnalysis(true);
                break;
                
            case "getGapIdentifierData":
                $this->getGapIdentifierData();
                break;
                
            case "getSkuList":
                $this->getSkuList();
                break;

            case "fetchSkusByPin":
                $this->getGapIdentifierData(true);
                break;

            case "skuChange":
                $this->skuSelect();
                break;
        }
        
        return $this->jsonOutput;
    }

    private function skuSelect() {

        $getLastDaysDate = filters\timeFilter::getLastN14DaysDate($this->settingVars);

        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $this->measureFields[] = $this->ohqName;
        $this->measureFields[] = $this->ohaqName;
        $this->measureFields[] = $this->baqName;
        $this->measureFields[] = $this->gsqName;
        
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->queryPart = $this->getAll();
        
        $query = "SELECT DATE_FORMAT(" . $this->settingVars->DatePeriod . ",'%a %e %b') AS DAY" .
                ",SUM(".$this->settingVars->ProjectVolume.") AS SALES " .
                ",SUM((CASE WHEN " . $this->ohqName . ">0 THEN 1 ELSE 0 END)*" . $this->ohqName . ") AS STOCK " .
                ",SUM(" . $this->ohaqName .") AS OHAQ " .
                ",SUM(" . $this->baqName .") AS BAQ " .    
                ",SUM((CASE WHEN " . $this->gsqName . ">0 THEN 1 ELSE 0 END)*" . $this->gsqName . ") AS GSQ " .
                "FROM " . $this->settingVars->tablename . " " . $this->queryPart .
                "AND " . $this->settingVars->DatePeriod . " IN (" . implode(',', $getLastDaysDate) . ") " .
                "GROUP BY DAY, " . $this->settingVars->DatePeriod . " " .
                "ORDER BY " . $this->settingVars->DatePeriod . " ASC";
        //echo $query;exit;
        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }

        $value = array();
        if (is_array($result) && !empty($result)) {
            foreach ($result as $data) {
                $value['SALES'][] = $data['SALES'];
                $value['STOCK'][] = $data['STOCK'];             
                $value['DAY'][]   = $data['DAY'];
                $value['ADJ'][]   = $data['OHAQ']+$data['BAQ'];
                $value['GSQ'][]   = $data['GSQ'];
            }
        }
        
        $this->jsonOutput['skuSelect'] = $value;
    }
    
    public function getGridCols()
    {
        $skuField = $this->getPageConfiguration('account_field', $this->settingVars->pageID);
        $this->buildDataArray(array($skuField[0]));
        $this->buildPageArray();
        
        $gridFieldPart = explode("#", $skuField[0]);
        $tempGridField = strtoupper($this->dbColumnsArray[$gridFieldPart[0]]);
        $tempGridField = (count($gridFieldPart) > 1) ? strtoupper($tempGridField."_".$this->dbColumnsArray[$gridFieldPart[1]]) : $tempGridField;
    
        $accountIDAlias = $accountAlias = $this->settingVars->dataArray[$tempGridField]["NAME_ALIASE"];
        
        if(count($gridFieldPart) > 1)
            $accountIDAlias = $this->settingVars->dataArray[$tempGridField]["ID_ALIASE"];
        
        $gridCols[$accountIDAlias] = $this->settingVars->dataArray[$tempGridField]["ID_CSV"];
        $gridCols[$accountAlias] = $this->settingVars->dataArray[$tempGridField]["NAME_CSV"];
        $this->jsonOutput['clusterAnalysisGridCols'] = $gridCols;
        $this->jsonOutput['clusterAnalysisGridData'] = array();
    }
    
    public function getGapIdentifierData($gridData = false)
    {
        $isMergeDates = (isset($_REQUEST['isMergeDates']) && $_REQUEST['isMergeDates'] == 1) ? true : false;
        $csvData = $brandUnits = array();
        $PIN = $_REQUEST['PIN'];
        
        $this->clusterID = (isset($_REQUEST["selectedCluster"]) && !empty($_REQUEST["selectedCluster"])) ? 
            $this->settingVars->storetable.'.cl'.$_REQUEST["selectedCluster"] : $this->settingVars->storetable.'.cl29';        
        
        $showCustomerSelection = $this->getPageConfiguration('show_customer_selection', $this->settingVars->pageID)[0];

        if($showCustomerSelection == "false")
            $GID = $this->settingVars->GID;
        elseif(!isset($_REQUEST['GID']))
            $GID = $this->settingVars->GID;
        else
            $GID = $_REQUEST['GID'];
        
        if(!empty($PIN) && !empty($GID))
        {
            $skuField = $this->getPageConfiguration('sku_field', $this->settingVars->pageID)[0];
            //$customerField = $this->getPageConfiguration('customer_field', $this->settingVars->pageID)[0];
            $storeField = $this->getPageConfiguration('store_field', $this->settingVars->pageID)[0];
            $brandField = $this->getPageConfiguration('brand_field', $this->settingVars->pageID)[0];

            $stockQtyField = $this->getPageConfiguration('stock_qty_field', $this->settingVars->pageID)[0];
            if(!empty($stockQtyField))
                $this->buildDataArray(array($skuField, $storeField, $brandField, $stockQtyField)); // $customerField
            else
                $this->buildDataArray(array($skuField, $storeField, $brandField)); // $customerField

            $this->buildPageArray();
            
            $this->measureFields[] = $this->settingVars->dataArray[strtoupper($this->dbColumnsArray[$brandField])]["NAME"];
            $this->measureFields[] = $this->settingVars->dataArray[strtoupper($this->dbColumnsArray[$_REQUEST['accountField']])]["NAME"];
            
            $brandAccountField = $this->settingVars->dataArray[strtoupper($this->dbColumnsArray[$brandField])]["NAME"];
            $brandAccountAlias = $this->settingVars->dataArray[strtoupper($this->dbColumnsArray[$brandField])]["NAME_ALIASE"];
            $brandCsv = $this->settingVars->dataArray[strtoupper($this->dbColumnsArray[$brandField])]["NAME_CSV"];
            
            $storeFieldPart = explode("#", $storeField);
            $storeField = strtoupper($this->dbColumnsArray[$storeFieldPart[0]]);
            $storeField = (count($storeFieldPart) > 1) ? strtoupper($storeField."_".$this->dbColumnsArray[$storeFieldPart[1]]) : $storeField;
            
            $this->measureFields[] = $this->settingVars->dataArray[$storeField]["NAME"];
            $this->measureFields[] = $this->requestAccountField;

            $this->settingVars->tableUsedForQuery = array();
            $this->settingVars->useRequiredTablesOnly = true;
            if (is_array($this->measureFields) && !empty($this->measureFields)) {
                $this->prepareTablesUsedForQuery($this->measureFields);
            }
            
            $this->queryPart = $this->getAll();
            
            $storeAccountIDField = $storeAccountField = $this->settingVars->dataArray[$storeField]["NAME"];
            $storeAccountIDAlias = $storeAccountAlias = $this->settingVars->dataArray[$storeField]["NAME_ALIASE"];
            $storeCsv = $this->settingVars->dataArray[$storeField]["NAME_CSV"];

            if(count($storeFieldPart) > 1){
                $storeAccountIDField = $this->settingVars->dataArray[$storeField]["ID"];
                $storeAccountIDAlias = $this->settingVars->dataArray[$storeField]["ID_ALIASE"];
                $storeIDCsv = $this->settingVars->dataArray[$storeField]["ID_CSV"];
            }

            $extraCols = $this->settingVars->dateField." as MYDATE, ";
            $extraGroupBy = ', MYDATE';
            if($isMergeDates){
                $extraCols = " MAX(".$this->settingVars->dateField.") as MYDATE, ";
                $extraGroupBy = ' ';
            }
            $query = "SELECT ".$storeAccountIDField." as ".$storeAccountIDAlias.", ".$extraCols." ".
                        " SUM((case when ".$this->requestAccountField." = '".$_REQUEST['accountFieldValue']."' then 1 end) * ".$this->settingVars->ProjectVolume.") as BRAND_UNITS ".
                        " FROM ".$this->settingVars->tablename.$this->queryPart.
                        " AND (".filters\timeFilter::$tyWeekRange." ) ".
                        " AND ".$this->settingVars->maintable.".gid IN (".$GID.") ".
                        " GROUP BY ".$storeAccountIDAlias." ".$extraGroupBy;
            
            $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);

            if ($redisOutput === false) {
                $brandUnitsResult = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
                $this->redisCache->setDataForHash($brandUnitsResult);
            } else {
                $brandUnitsResult = $redisOutput;
            }

            if(is_array($brandUnitsResult) && !empty($brandUnitsResult)){
                foreach ($brandUnitsResult as $value) {
                    $brandUnits[$value['SNO']][$value['MYDATE']] = $value['BRAND_UNITS'];
                }
            }
            /****** Main Query *****/
            
            $skuFieldPart = explode("#", $skuField);
            $skuField = strtoupper($this->dbColumnsArray[$skuFieldPart[0]]);
            $skuField = (count($skuFieldPart) > 1) ? strtoupper($skuField."_".$this->dbColumnsArray[$skuFieldPart[1]]) : $skuField;
            
            $this->measureFields[] = $this->settingVars->dataArray[$skuField]["NAME"];

            if(!empty($stockQtyField)){
                $this->measureFields[] = $this->settingVars->dataArray[strtoupper($this->dbColumnsArray[$stockQtyField])]["NAME"];
                $stockQtyAccountField = $this->settingVars->dataArray[strtoupper($this->dbColumnsArray[$stockQtyField])]["NAME"];
            }
            
            $this->settingVars->tableUsedForQuery = array();
            $this->settingVars->useRequiredTablesOnly = true;
            if (is_array($this->measureFields) && !empty($this->measureFields)) {
                $this->prepareTablesUsedForQuery($this->measureFields);
            }
            
            $this->queryPart = $this->getAll();
            
            $skuAccountIDField = $skuAccountField = $this->settingVars->dataArray[$skuField]["NAME"];
            $skuAccountIDAlias = $skuAccountAlias = $this->settingVars->dataArray[$skuField]["NAME_ALIASE"];
            $skuCsv = $this->settingVars->dataArray[$skuField]["NAME_CSV"];

            if(count($storeFieldPart) > 1){
                $skuAccountIDField = $this->settingVars->dataArray[$skuField]["ID"];
                $skuAccountIDAlias = $this->settingVars->dataArray[$skuField]["ID_ALIASE"];
                $skuIDCsv = $this->settingVars->dataArray[$skuField]["ID_CSV"];
            }
            
            $subQuery = "SELECT DISTINCT CONCAT(".$storeAccountIDField.",".$this->clusterID.") as SNO_CLUSTER FROM ". $this->settingVars->tablename.$this->queryPart." AND (".filters\timeFilter::$tyWeekRange.") AND ".$skuAccountIDField." = '".$PIN."' AND ".$this->settingVars->ProjectVolume." > 0 AND ".$this->clusterID." IN ('A','B','C','D','E','Z')";

            $subQueryStore = "SELECT DISTINCT ".$storeAccountIDField." as SNO FROM ". $this->settingVars->tablename.$this->queryPart." AND (".filters\timeFilter::$tyWeekRange.")".
                " AND  ".$this->requestAccountField." = '".$_REQUEST['accountFieldValue']."' AND ".$this->settingVars->ProjectVolume." > 0 AND ".$this->clusterID." IN ('A','B','C','D','E','Z')";

            $extraYearWeekQue = $this->settingVars->dateField." as MYDATE, ";
            $extraGroupBy = ' MYDATE,';
            $tyMaxWeek = '';
            if ($this->isYearWeekPeriodActive) {
                $extraYearWeekQue .= $this->settingVars->yearperiod." as YEAR, ".$this->settingVars->weekperiod." as WEEK, ";
                $extraGroupBy .= "YEAR, WEEK, ";

                $tyMaxWeek = " (". $this->settingVars->yearperiod ."=". filters\timeFilter::$ToYear ." AND ". $this->settingVars->weekperiod ."=". filters\timeFilter::$ToWeek .") ";

            }else{
                if (isset(filters\timeFilter::$tyDaysRange) && isset(filters\timeFilter::$tyDaysRange[0]))
                    $tyMaxWeek = $this->settingVars->DatePeriod." IN ('" . filters\timeFilter::$tyDaysRange[0] . "')";
                //$tyMaxWeek = "(CASE WHEN ".$this->settingVars->dateField." IN ('" . filters\timeFilter::$tyWeekRange[0] . "') ";
            }

            if($isMergeDates){
                $extraYearWeekQue = " MAX(".$this->settingVars->dateField.") as MYDATE, ";
                $extraGroupBy = ' ';
            }

            $stockQtyQuery = ' ';
            if(!empty($stockQtyField)){
                $stockQtyQuery =" SUM((CASE WHEN ".$skuAccountIDField." = '".$PIN."' AND ".$tyMaxWeek." THEN 1 ELSE 0 END) * ".$stockQtyAccountField.") AS STOCK_QTY, ";
            }

            $territoryFlds = '';
            if($this->settingVars->projectTypeID == 2 && isset($this->queryVars->projectConfiguration) && isset($this->queryVars->projectConfiguration['rp_has_territory']) && $this->queryVars->projectConfiguration['rp_has_territory'] == 1 && !empty($this->queryVars->projectConfiguration['rp_has_territory_level'])){
                $territoryFlds = ', MAX('.$this->settingVars->territoryTable.'.Level'.$this->queryVars->projectConfiguration['rp_has_territory_level'].') AS TERRITORY ';
            }else if(isset($this->queryVars->projectConfiguration) && isset($this->queryVars->projectConfiguration['has_territory']) && $this->queryVars->projectConfiguration['has_territory'] == 1 && !empty($this->queryVars->projectConfiguration['has_territory_level'])){
                $territoryFlds = ', MAX('.$this->settingVars->territoryTable.'.Level'.$this->queryVars->projectConfiguration['has_territory_level'].') AS TERRITORY ';
            }

            $query = "SELECT ".
                    $storeAccountIDField." as ".$storeAccountIDAlias.", ".
                    "Max(".$storeAccountField.") as ".$storeAccountAlias.", ".
                    $this->clusterID." as CLUSTER, ".$extraYearWeekQue.
                    $stockQtyQuery.
                    //"Max(".$skuAccountIDField.") as ".$skuAccountIDAlias.", ".
                    //"Max(".$skuAccountField.") as ".$skuAccountAlias.", ".
                    "Max(".$brandAccountField.") as ".$brandAccountAlias.", ".
                    //"SUM(".$this->settingVars->ProjectVolume.") as SKU_UNITS, ".
                    "SUM((case when ".$skuAccountIDField." = '".$PIN."' AND ".$this->clusterID." = 'A' then 1 else 0 end) * ".$this->settingVars->ProjectVolume.") as QTY_A,".
                    "SUM((case when ".$skuAccountIDField." = '".$PIN."' AND ".$this->clusterID." = 'B' then 1 else 0 end) * ".$this->settingVars->ProjectVolume.") as QTY_B,".
                    "SUM((case when ".$skuAccountIDField." = '".$PIN."' AND ".$this->clusterID." = 'C' then 1 else 0 end) * ".$this->settingVars->ProjectVolume.") as QTY_C,".
                    "SUM((case when ".$skuAccountIDField." = '".$PIN."' AND ".$this->clusterID." = 'D' then 1 else 0 end) * ".$this->settingVars->ProjectVolume.") as QTY_D,".
                    "SUM((case when ".$skuAccountIDField." = '".$PIN."' AND ".$this->clusterID." = 'E' then 1 else 0 end) * ".$this->settingVars->ProjectVolume.") as QTY_E,".
                    "SUM((case when ".$skuAccountIDField." = '".$PIN."' AND ".$this->clusterID." = 'Z' then 1 else 0 end) * ".$this->settingVars->ProjectVolume.") as QTY_Z ".$territoryFlds.
                    " FROM ".$this->settingVars->tablename.$this->queryPart.
                    " AND (".filters\timeFilter::$tyWeekRange.") ".
                    //" AND ".$skuAccountIDField." = '".$PIN."' ".
                    " AND ".$this->settingVars->maintable.".gid IN (".$GID.") ".
                    " AND ".$this->requestAccountField." = '".$_REQUEST['accountFieldValue']."'". 
                    " AND CONCAT(".$storeAccountIDField.",".$this->clusterID.") NOT IN (".$subQuery.") ".
                    " AND ".$storeAccountIDField." IN (".$subQueryStore.") ".
                    " GROUP BY ".$storeAccountIDAlias.", ".$extraGroupBy." CLUSTER ";
                    //" Having SKU_UNITS < 1 ".
                    //" ORDER BY ".$skuAccountAlias;
            //echo $query; exit;   
            $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
            
            if ($redisOutput === false) {
                $mainResult = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT); 
                $this->redisCache->setDataForHash($mainResult);
            } else {
                $mainResult = $redisOutput;
            }
            
            if(is_array($mainResult) && !empty($mainResult))
            {
                $csvData = array();
                $isTerritorySet = false;
                foreach ($mainResult as $value) 
                {
                    $tmp = array();
                    $tmp[$storeAccountIDAlias] = $value[$storeAccountIDAlias];
                    $tmp[$storeAccountAlias] = $value[$storeAccountAlias];
                    $tmp['CLUSTER'] = $value['CLUSTER'];
                    
                    if(!$isMergeDates){
                        if ($this->isYearWeekPeriodActive) {
                            $tmp['YEAR'] = $value['YEAR'];
                            $tmp['WEEK'] = $value['WEEK'];
                        }
                        $tmp['DATE'] = $value['MYDATE'];
                    }

                    $tmp[$skuAccountIDAlias] = $_REQUEST['PIN'];
                    $tmp[$skuAccountAlias] = str_replace(" (".$_REQUEST['PIN'].")","",rawurldecode($_REQUEST['selectedSkuName']));
                    $tmp[$brandAccountAlias] = $value[$brandAccountAlias];
                    $tmp['SKU_UNITS'] = $value['QTY_'.$value['CLUSTER']];

                    if (!empty($stockQtyField))
                        $tmp['STOCK_QTY'] = $value['STOCK_QTY'];
                    
                    if(isset($brandUnits[$value[$storeAccountIDAlias]][$value['MYDATE']]) && $brandUnits[$value[$storeAccountIDAlias]][$value['MYDATE']] > 0 )
                    {
                        $tmp['BRAND_UNITS'] =  $brandUnits[$value[$storeAccountIDAlias]][$value['MYDATE']];
                    }
                    
                    if(isset($value['TERRITORY'])){
                        $isTerritorySet = true;
                        $tmp['TERRITORY'] = $value['TERRITORY'];
                    }
                    
                    $csvData[] = $tmp;
                }

                if(count($csvData) > 0)
                {
                    if($gridData) {
                        $csvHeader[$storeAccountIDAlias] = $storeIDCsv;
                        $csvHeader[$storeAccountAlias] = $storeCsv;
                        $csvHeader['CLUSTER'] = 'CLUSTER';

                    } else {
                        $csvHeader = array($storeIDCsv, $storeCsv, "CLUSTER");    
                    }

                    if(!$isMergeDates){
                        if ($this->isYearWeekPeriodActive) {
                            $csvHeader['YEAR'] = "YEAR";
                            $csvHeader['WEEK'] = "WEEK";
                        }
                        $csvHeader['DATE'] = "DATE";
                    }
                    $csvHeader[$skuAccountIDAlias] = $skuIDCsv;
                    $csvHeader[$skuAccountAlias] = $skuCsv;
                    $csvHeader[$brandAccountAlias] = $brandCsv;
                    $csvHeader['SKU_UNITS'] = "SKU UNITS";
                    if (!empty($stockQtyField))
                        $csvHeader['STOCK_QTY'] = "STOCK QTY";

                    $csvHeader['BRAND_UNITS'] = "TOTAL BRAND UNITS";

                    if($isTerritorySet)
                        $csvHeader['TERRITORY'] = "TERRITORY";

                    if($gridData) {
                        $this->jsonOutput['gapIdentifierData'] = $csvData;
                        $this->jsonOutput['gapIdentifierDataHeader'] = $csvHeader;
                        return;
                    }

                    $fileName      = "Gap-Identifier-" . date("Y-m-d-h-i-s") . ".csv";
                    $savePath      = dirname(__FILE__)."/../uploads/Cluster-Analysis/";
                    $filePath      = $savePath.$fileName;
                    
                    $file = fopen($filePath, 'w');
                    fputcsv($file, $csvHeader);
                    foreach ($csvData as $row)
                    {
                        fputcsv($file, $row);
                    }
                    fclose($file);
                    
                    $this->jsonOutput['downloadLinkForGapIdentifier'] = $this->settingVars->get_full_url()."/".basename(dirname(dirname(__FILE__)))."/uploads/Cluster-Analysis/".$fileName;
                }
                else
                    $this->jsonOutput['noDataFoundGapIdentifier'] = true;
            }
            else{
                $this->jsonOutput['noDataFoundGapIdentifier'] = true;
            }
        }
        else
            $this->jsonOutput['noDataFoundGapIdentifier'] = true;
    }
    
    public function getSkuList()
    {
        if(isset($_REQUEST['territoryLevel']))
            unset($_REQUEST['territoryLevel']);

        if(!$_REQUEST['GID'])
            $GID = $this->settingVars->GID;
        else
            $GID = $_REQUEST['GID'];

        $skuField = $this->getPageConfiguration('sku_field', $this->settingVars->pageID);
        $this->buildDataArray(array($skuField[0]));
        $this->buildPageArray();
        
        $gridFieldPart = explode("#", $skuField[0]);
        $tempGridField = strtoupper($this->dbColumnsArray[$gridFieldPart[0]]);
        $tempGridField = (count($gridFieldPart) > 1) ? strtoupper($tempGridField."_".$this->dbColumnsArray[$gridFieldPart[1]]) : $tempGridField;
        
        $this->measureFields[] = $this->settingVars->dataArray[$tempGridField]["NAME"];
        
        $this->settingVars->tableUsedForQuery = array();
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        
        $this->queryPart = $this->getAll();
        
        $accountField = $this->settingVars->dataArray[$tempGridField]["NAME"];
        $accountAlias = $this->settingVars->dataArray[$tempGridField]["NAME_ALIASE"];
        $accountCsv = $this->settingVars->dataArray[$tempGridField]["NAME_CSV"];
        
        $idField = ""; $accountIDGroupBy = '';
        if(count($gridFieldPart) > 1){
            $accountIDField = $this->settingVars->dataArray[$tempGridField]["ID"];
            $accountIDAlias = $this->settingVars->dataArray[$tempGridField]["ID_ALIASE"];
            $idField = ($accountIDField ? ', '.$accountIDField.' as '.$accountIDAlias : '');
            $accountIDGroupBy = ', '.$accountIDAlias;
        }
        
        $query = "SELECT ".$accountField." as ".$accountAlias.$idField." FROM ".$this->settingVars->tablename.$this->queryPart." AND (".filters\timeFilter::$tyWeekRange." ) AND ".$this->settingVars->maintable.".gid IN (".$GID.")";

        if(isset($_REQUEST['accountField']) && isset($_REQUEST['accountFieldValue']))
            $query .= " AND ".$this->requestAccountField." = '".$_REQUEST['accountFieldValue']."'";
        
        $query .= " GROUP BY ".$accountAlias.$accountIDGroupBy." ORDER BY ".$accountAlias." ASC";
        
        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }
        
        $skuSelection = array();
        if(is_array($result) && !empty($result))
        {
            $skuSelection[] = array('label' => "Select Sku", 'data' => "");
            foreach($result as $data)
            {
                $label = (count($gridFieldPart) > 1) ? $data[$accountAlias]." (".$data[$accountIDAlias].")" : $data[$accountAlias];
                $value = (count($gridFieldPart) > 1) ? $data[$accountIDAlias] : $data[$accountAlias];
                
                $skuSelection[] = array('label' => $label, 'data' => $value);
            }
        }
        
        $this->jsonOutput['skuSelection'] = $skuSelection;
    }
    
    public function getDataForClusterAnalysis($isGridData = false)
    {
        $maintable = $this->settingVars->maintable;
        $projectVolume = $this->settingVars->ProjectVolume;
        $isMergeDates = (isset($_REQUEST['isMergeDates']) && $_REQUEST['isMergeDates'] == 1) ? true : false;

        if($_REQUEST['selectedCluster'] != "" && $_REQUEST['accountField'] != "" && $_REQUEST['accountFieldValue'] != "" && ($_REQUEST['FromWeek'] || $_REQUEST['FromDate']))
        {
            $csvHeader = array();
            $metricArray = array(
                'MAX_DIST'      => 'MAX DIST',
                'NUM_DIST'      => 'NUM DIST',
                'SALES'         => 'SALES',
                'SALES_A'       => 'SALES A',
                'SALES_B'       => 'SALES B',
                'SALES_C'       => 'SALES C',
                'SALES_D'       => 'SALES D',
                'SALES_E'       => 'SALES E',
                'MAX_DIST_A'    => 'MAX DIST A',
                'MAX_DIST_B'    => 'MAX DIST B',
                'MAX_DIST_C'    => 'MAX DIST C',
                'MAX_DIST_D'    => 'MAX DIST D',
                'MAX_DIST_E'    => 'MAX DIST E',
                'NUM_DIST_A'    => 'NUM DIST A',
                'NUM_DIST_B'    => 'NUM DIST B',
                'NUM_DIST_C'    => 'NUM DIST C',
                'NUM_DIST_D'    => 'NUM DIST D',
                'NUM_DIST_E'    => 'NUM DIST E',
                'WEIGHTED_DIST' => 'WEIGHTED DIST',
                'WEIGHTED_UROS' => 'WEIGHTED UROS'
            );
        
            if($isGridData)
            {
                $metricArray['SALES_Z'] = 'SALES Z';
                $metricArray['MAX_DIST_E'] = 'MAX DIST Z';
                $metricArray['NUM_DIST_E'] = 'NUM DIST E';
            }
        
            $skuField = $this->getPageConfiguration('account_field', $this->settingVars->pageID);
            $extraColumns = $this->getPageConfiguration('extra_columns', $this->settingVars->pageID);
            
            if (!empty($extraColumns) && is_array($extraColumns)){
                foreach ($extraColumns as $ky => $vl) {
                    $fields[] = $vl;
                }
            }
            
            $fields[] = $skuField[0];
            
            $this->buildDataArray($fields);
            $this->buildPageArray();
            
            if (!empty($extraColumns) && is_array($extraColumns)){
                foreach ($extraColumns as $ky => $vl) {
                    $this->settingVars->pageArray[$this->settingVars->pageName]["EXTRACOLUMNS"][] = ["ACCOUNT" => $this->displayCsvNameArray[$vl], "ALIAS" => strtoupper($this->dbColumnsArray[$vl])];
                }
            }
        
            $gridFieldPart = explode("#", $skuField[0]);
            $tempGridField = strtoupper($this->dbColumnsArray[$gridFieldPart[0]]);
            $tempGridField = (count($gridFieldPart) > 1) ? strtoupper($tempGridField."_".$this->dbColumnsArray[$gridFieldPart[1]]) : $tempGridField;
        
            $accountIDField = $accountField = $this->settingVars->dataArray[$tempGridField]["NAME"];
            $accountIDAlias = $accountAlias = $this->settingVars->dataArray[$tempGridField]["NAME_ALIASE"];
            $csvHeader[] = $this->settingVars->dataArray[$tempGridField]["NAME_CSV"];
            
            if(count($gridFieldPart) > 1){
                $accountIDField = $this->settingVars->dataArray[$tempGridField]["ID"];
                $accountIDAlias = $this->settingVars->dataArray[$tempGridField]["ID_ALIASE"];
            }
            
            if($isGridData)
            {
                $gridCols[$accountIDAlias] = $this->settingVars->dataArray[$tempGridField]["ID_CSV"];
                $gridCols[$accountAlias] = $this->settingVars->dataArray[$tempGridField]["NAME_CSV"];
                $this->jsonOutput['clusterAnalysisGridCols'] = $gridCols;
            }
            
            foreach ($this->settingVars->pageArray[$this->settingVars->pageName]["EXTRACOLUMNS"] as $ky => $extraValue) {
                
                $ExtraCols[] = ['NAME' => $this->settingVars->dataArray[$extraValue['ALIAS']]["NAME"]." AS ".$this->settingVars->dataArray[$extraValue['ALIAS']]['NAME_ALIASE'], 'NAME_ALIASE' => $this->settingVars->dataArray[$extraValue['ALIAS']]['NAME_ALIASE'], 'NAME_CSV' => $this->settingVars->dataArray[$extraValue['ALIAS']]['NAME_CSV']];

                $csvHeader[] = $this->settingVars->dataArray[$extraValue['ALIAS']]['NAME_CSV'];
                $this->measureFields[] = $this->settingVars->dataArray[$extraValue['ALIAS']]["NAME"];
            }

            $this->measureFields[] = $this->clusterID = (isset($_REQUEST["selectedCluster"]) && !empty($_REQUEST["selectedCluster"])) ? 
            $this->settingVars->storetable.'.cl'.$_REQUEST["selectedCluster"] : $this->settingVars->storetable.'.cl29';
            
            $this->settingVars->tableUsedForQuery = array();
            
            $this->settingVars->useRequiredTablesOnly = true;
            if (is_array($this->measureFields) && !empty($this->measureFields)) {
                $this->prepareTablesUsedForQuery($this->measureFields);
            }
            
            $this->queryPart = $this->getAll();
            
            $accountIDGroupBy = '';
            if($isGridData){
                $query = "SELECT ".$accountField." as ".$accountAlias.", ".$accountIDField." as ".$accountIDAlias.", ".$maintable.".gid as GID ";
                $accountIDGroupBy = $accountIDAlias.', ';
            }
            
            if(!$isGridData)
            {
                $query = "SELECT ".$accountField." as ".$accountAlias.", ".$maintable.".gid as GID ";
                $query .= (!empty($ExtraCols) && count($ExtraCols)>0 ? ", " . implode(',', array_column($ExtraCols,'NAME')) : "");
            }

            $extraYearWeekQue = $this->settingVars->dateField." as MYDATE, ";
            $extraGroupBy = ' ,MYDATE ';
            if ($this->isYearWeekPeriodActive) {
                $extraYearWeekQue .= $this->settingVars->yearperiod." as YEAR, ".$this->settingVars->weekperiod." as WEEK, ";
                $extraGroupBy .= " ,YEAR, WEEK ";
            }
            if($isMergeDates){
                $extraYearWeekQue = " MAX(".$this->settingVars->dateField.") as MYDATE, ";
                $extraGroupBy = ' ';
            }

            $query .= ", ".$extraYearWeekQue.
                " COUNT(DISTINCT(case when ".$projectVolume." > 0 then 1 end) * ".$maintable.".SNO) as NUM_DIST, ".
                " COUNT(DISTINCT(case when ".$projectVolume." > 0 AND ".$this->clusterID."='A' then 1 end) * ".$maintable.".SNO) as NUM_DIST_A, ".
                " COUNT(DISTINCT(case when ".$projectVolume." > 0 AND ".$this->clusterID."='B' then 1 end) * ".$maintable.".SNO) as NUM_DIST_B, ".
                " COUNT(DISTINCT(case when ".$projectVolume." > 0 AND ".$this->clusterID."='C' then 1 end) * ".$maintable.".SNO) as NUM_DIST_C, ".
                " COUNT(DISTINCT(case when ".$projectVolume." > 0 AND ".$this->clusterID."='D' then 1 end) * ".$maintable.".SNO) as NUM_DIST_D, ".
                " COUNT(DISTINCT(case when ".$projectVolume." > 0 AND ".$this->clusterID."='E' then 1 end) * ".$maintable.".SNO) as NUM_DIST_E, ".
                " COUNT(DISTINCT(case when ".$projectVolume." > 0 AND ".$this->clusterID."='Z' then 1 end) * ".$maintable.".SNO) as NUM_DIST_Z, ".
                " SUM(".$projectVolume.") as SALES, ".
                " SUM((case when ".$this->clusterID."='A' then 1 end) * ".$projectVolume.") as SALES_A, ".
                " SUM((case when ".$this->clusterID."='B' then 1 end) * ".$projectVolume.") as SALES_B, ".
                " SUM((case when ".$this->clusterID."='C' then 1 end) * ".$projectVolume.") as SALES_C, ".
                " SUM((case when ".$this->clusterID."='D' then 1 end) * ".$projectVolume.") as SALES_D, ".
                " SUM((case when ".$this->clusterID."='E' then 1 end) * ".$projectVolume.") as SALES_E, ".
                " SUM((case when ".$this->clusterID."='Z' then 1 end) * ".$projectVolume.") as SALES_Z ".
                " FROM ".$this->settingVars->tablename.$this->queryPart.
                " AND (".filters\timeFilter::$tyWeekRange." ) ".
                " AND  ".$this->requestAccountField." = '".$_REQUEST['accountFieldValue']."' ".
                " GROUP BY ".$accountAlias.", ".$accountIDGroupBy." GID ".$extraGroupBy;
                
            if(!$isGridData)
                $query .= (!empty($ExtraCols) && count($ExtraCols)>0 ? ", " . implode(',', array_column($ExtraCols,'NAME_ALIASE')) : "");
                
            $query .= " ORDER BY ".$accountAlias;
            $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);

            if ($redisOutput === false) {
                $mainResult = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
                $this->redisCache->setDataForHash($mainResult);
            } else {
                $mainResult = $redisOutput;
            }

            $extraCols = $this->settingVars->dateField." as MYDATE, ";
            $extraGroupBy = ', MYDATE';
            if ($isMergeDates) {
                $extraCols = " MAX(".$this->settingVars->dateField.") as MYDATE, ";
                $extraGroupBy = ' ';
            }

            $query = "SELECT ".$maintable.".gid as GID, ".$extraCols. 
                " COUNT(DISTINCT(case when ".$projectVolume." > 0 then 1 end) * ".$maintable.".SNO) as MAX_DIST, ".
                " COUNT(DISTINCT(case when ".$projectVolume." > 0 AND ".$this->clusterID."='A' then 1 end) * ".$maintable.".SNO) as MAX_DIST_A, ".
                " COUNT(DISTINCT(case when ".$projectVolume." > 0 AND ".$this->clusterID."='B' then 1 end) * ".$maintable.".SNO) as MAX_DIST_B, ".
                " COUNT(DISTINCT(case when ".$projectVolume." > 0 AND ".$this->clusterID."='C' then 1 end) * ".$maintable.".SNO) as MAX_DIST_C, ".
                " COUNT(DISTINCT(case when ".$projectVolume." > 0 AND ".$this->clusterID."='D' then 1 end) * ".$maintable.".SNO) as MAX_DIST_D, ".
                " COUNT(DISTINCT(case when ".$projectVolume." > 0 AND ".$this->clusterID."='E' then 1 end) * ".$maintable.".SNO) as MAX_DIST_E, ".
                " COUNT(DISTINCT(case when ".$projectVolume." > 0 AND ".$this->clusterID."='Z' then 1 end) * ".$maintable.".SNO) as MAX_DIST_Z ".
                " FROM ".$this->settingVars->tablename.$this->queryPart.
                " AND (".filters\timeFilter::$tyWeekRange." ) ".
                " AND  ".$this->requestAccountField." = '".$_REQUEST['accountFieldValue']."' ".
                " GROUP BY GID ".$extraGroupBy;

            $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
            if ($redisOutput === false) {
                $subResult = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
                $this->redisCache->setDataForHash($subResult);
            } else {
                $subResult = $redisOutput;
            }
            
            $subResultDetail = array();
            if (is_array($subResult) && !empty($subResult)) {
                foreach ($subResult as $subResultData) {
                    $subResultDetail[$subResultData['GID']][$subResultData['MYDATE']] = $subResultData;
                }
            }
            
            $clusterAnalysisGridData = array();
            
            if (is_array($mainResult) && !empty($mainResult)) {
                foreach ($mainResult as $mainResultData) {

                    if($mainResultData['SALES'] == 0)
                        continue;

                    $mainResultData['MAX_DIST'] = (isset($subResultDetail[$mainResultData['GID']][$mainResultData['MYDATE']])) ? $subResultDetail[$mainResultData['GID']][$mainResultData['MYDATE']]['MAX_DIST'] : 0;
                    $mainResultData['MAX_DIST_A'] = (isset($subResultDetail[$mainResultData['GID']][$mainResultData['MYDATE']])) ? (double)$subResultDetail[$mainResultData['GID']][$mainResultData['MYDATE']]['MAX_DIST_A'] : 0;
                    $mainResultData['MAX_DIST_B'] = (isset($subResultDetail[$mainResultData['GID']][$mainResultData['MYDATE']])) ? (double)$subResultDetail[$mainResultData['GID']][$mainResultData['MYDATE']]['MAX_DIST_B'] : 0;
                    $mainResultData['MAX_DIST_C'] = (isset($subResultDetail[$mainResultData['GID']][$mainResultData['MYDATE']])) ? (double)$subResultDetail[$mainResultData['GID']][$mainResultData['MYDATE']]['MAX_DIST_C'] : 0;
                    $mainResultData['MAX_DIST_D'] = (isset($subResultDetail[$mainResultData['GID']][$mainResultData['MYDATE']])) ? (double)$subResultDetail[$mainResultData['GID']][$mainResultData['MYDATE']]['MAX_DIST_D'] : 0;
                    $mainResultData['MAX_DIST_E'] = (isset($subResultDetail[$mainResultData['GID']][$mainResultData['MYDATE']])) ? (double)$subResultDetail[$mainResultData['GID']][$mainResultData['MYDATE']]['MAX_DIST_E'] : 0;
                    $mainResultData['MAX_DIST_Z'] = (isset($subResultDetail[$mainResultData['GID']][$mainResultData['MYDATE']])) ? (double)$subResultDetail[$mainResultData['GID']][$mainResultData['MYDATE']]['MAX_DIST_Z'] : 0;
                    
                    $mainResultData['WEIGHTED_DIST_A'] = (isset($mainResultData['MAX_DIST_A']) && $mainResultData['MAX_DIST_A'] > 0) ? ($mainResultData['NUM_DIST_A']/$mainResultData['MAX_DIST_A'])*5 : 0;
                    $mainResultData['WEIGHTED_DIST_B'] = (isset($mainResultData['MAX_DIST_B']) && $mainResultData['MAX_DIST_B'] > 0) ? ($mainResultData['NUM_DIST_B']/$mainResultData['MAX_DIST_B'])*4 : 0;
                    $mainResultData['WEIGHTED_DIST_C'] = (isset($mainResultData['MAX_DIST_C']) && $mainResultData['MAX_DIST_C'] > 0) ? ($mainResultData['NUM_DIST_C']/$mainResultData['MAX_DIST_C'])*3 : 0;
                    $mainResultData['WEIGHTED_DIST_D'] = (isset($mainResultData['MAX_DIST_D']) && $mainResultData['MAX_DIST_D'] > 0) ? ($mainResultData['NUM_DIST_D']/$mainResultData['MAX_DIST_D'])*2 : 0;
                    $mainResultData['WEIGHTED_DIST_E'] = (isset($mainResultData['MAX_DIST_E']) && $mainResultData['MAX_DIST_E'] > 0) ? ($mainResultData['NUM_DIST_E']/$mainResultData['MAX_DIST_E'])*1 : 0;

                    $mainResultData['WEIGHTED_DIST'] = (($mainResultData['WEIGHTED_DIST_A'] + $mainResultData['WEIGHTED_DIST_B'] + $mainResultData['WEIGHTED_DIST_C'] + $mainResultData['WEIGHTED_DIST_D'] + $mainResultData['WEIGHTED_DIST_E'])/15)*100;
                    $weightedPart = $mainResultData['MAX_DIST']*($mainResultData['WEIGHTED_DIST']/100);
                    $mainResultData['WEIGHTED_UROS'] = ($weightedPart > 0) ? $mainResultData['SALES']/$weightedPart : 0;
                    $mainResultData['WEIGHTED_DIST'] = number_format($mainResultData['WEIGHTED_DIST'], 1, '.', '');
                    $mainResultData['WEIGHTED_UROS'] = number_format($mainResultData['WEIGHTED_UROS'], 1, '.', '');

                    if($isGridData)
                    {
                        $mainResultData['DIST_PER_A'] = ($mainResultData['MAX_DIST_A'] > 0) ? ($mainResultData['NUM_DIST_A']/$mainResultData['MAX_DIST_A'])*100 : 0;
                        
                        $mainResultData['DIST_PER_B'] = ($mainResultData['MAX_DIST_B'] > 0) ? ($mainResultData['NUM_DIST_B']/$mainResultData['MAX_DIST_B'])*100 : 0;
                        
                        $mainResultData['DIST_PER_C'] = ($mainResultData['MAX_DIST_C'] > 0) ? ($mainResultData['NUM_DIST_C']/$mainResultData['MAX_DIST_C'])*100 : 0;
                        
                        $mainResultData['DIST_PER_D'] = ($mainResultData['MAX_DIST_D'] > 0) ? ($mainResultData['NUM_DIST_D']/$mainResultData['MAX_DIST_D'])*100 : 0;
                        
                        $mainResultData['DIST_PER_E'] = ($mainResultData['MAX_DIST_E'] > 0) ? ($mainResultData['NUM_DIST_E']/$mainResultData['MAX_DIST_E'])*100 : 0;
                        
                        $mainResultData['DIST_PER_Z'] = ($mainResultData['MAX_DIST_Z'] > 0) ? ($mainResultData['NUM_DIST_Z']/$mainResultData['MAX_DIST_Z'])*100 : 0;
                        
                        $mainResultData['NUM_DIST_A'] = (double)$mainResultData['NUM_DIST_A'];
                        $mainResultData['NUM_DIST_B'] = (double)$mainResultData['NUM_DIST_B'];
                        $mainResultData['NUM_DIST_C'] = (double)$mainResultData['NUM_DIST_C'];
                        $mainResultData['NUM_DIST_D'] = (double)$mainResultData['NUM_DIST_D'];
                        $mainResultData['NUM_DIST_E'] = (double)$mainResultData['NUM_DIST_E'];
                        $mainResultData['NUM_DIST_Z'] = (double)$mainResultData['NUM_DIST_Z'];

                        $mainResultData['NUM_DIST'] = (double)$mainResultData['NUM_DIST'];
                        $mainResultData['MAX_DIST'] = (double)$mainResultData['MAX_DIST'];
                        $mainResultData['DIST_PER'] = ($mainResultData['MAX_DIST'] > 0) ? ($mainResultData['NUM_DIST']/$mainResultData['MAX_DIST'])*100 : 0;
                        
                        unset($mainResultData['WEIGHTED_DIST_A']);
                        unset($mainResultData['WEIGHTED_DIST_B']);
                        unset($mainResultData['WEIGHTED_DIST_C']);
                        unset($mainResultData['WEIGHTED_DIST_D']);
                        unset($mainResultData['WEIGHTED_DIST_E']);
                        unset($mainResultData['SALES_A']);
                        unset($mainResultData['SALES_B']);
                        unset($mainResultData['SALES_C']);
                        unset($mainResultData['SALES_D']);
                        unset($mainResultData['SALES_E']);
                        unset($mainResultData['SALES_Z']);
                        unset($mainResultData['WEIGHTED_DIST']);
                        unset($mainResultData['WEIGHTED_UROS']);

                        if ($this->isYearWeekPeriodActive) {
                            unset($mainResultData['WEEK']);
                            unset($mainResultData['YEAR']);
                        }

                        unset($mainResultData['GID']);
                        //unset($mainResultData['NUM_DIST']);
                        //unset($mainResultData['MAX_DIST']);
                        $mainResultData["ID"] = $mainResultData[$accountIDAlias];
                        $mainResultData['SALES'] = (double)$mainResultData['SALES'];
                    }
                    
                    // MAX_DIST_A = MAX STORES A
                    // SALES_A = SELLING A
                    
                    $finalResult = array();

                    foreach ($metricArray as $metricKey => $metricVal) {
                        $finalResult['SKU'] = $mainResultData[$accountAlias];
                        
                        foreach ($extraColumns as $ky => $vl){
                            $finalResult[$this->settingVars->dataArray[strtoupper($this->dbColumnsArray[$vl])]["NAME_CSV"]] = $mainResultData[$this->settingVars->dataArray[strtoupper($this->dbColumnsArray[$vl])]["NAME_ALIASE"]];
                        }
                        
                        $finalResult['METRIC'] = $metricVal;
                        $finalResult['AMOUNT'] = isset($mainResultData[$metricKey]) ? $mainResultData[$metricKey] : 0 ;

                        if (!$isMergeDates) {
                            $finalResult['MYDATE'] = $mainResultData['MYDATE'];
                            if ($this->isYearWeekPeriodActive) {
                                $finalResult['YEAR'] = $mainResultData['YEAR'];
                                $finalResult['WEEK'] = $mainResultData['WEEK'];
                            }
                        }

                        $mainResultDetail[] = $finalResult;
                    }
                    
                    if($isGridData)
                        $clusterAnalysisGridData[] = $mainResultData;
                }
            }

            if(count($mainResultDetail) > 0)
            {
                if(!$isGridData)
                {
                    if ($this->isYearWeekPeriodActive)
                        $tmpHeaderArray = array('METRIC', 'AMOUNT', 'MYDATE', 'YEAR', 'WEEK');
                    else
                        $tmpHeaderArray = array('METRIC', 'AMOUNT', 'MYDATE');

                    if ($isMergeDates)
                        $tmpHeaderArray = array('METRIC', 'AMOUNT');
                    
                    $csvHeader = array_merge($csvHeader, $tmpHeaderArray);
                    $fileName      = "Cluster-Analysis-" . date("Y-m-d-h-i-s") . ".csv";
                    $savePath      = dirname(__FILE__)."/../uploads/Cluster-Analysis/";
                    $filePath      = $savePath.$fileName;
                    
                    $file = fopen($filePath, 'w');
                    fputcsv($file, $csvHeader);
                    foreach ($mainResultDetail as $row) {
                        fputcsv($file, $row);
                    }
                    fclose($file);
                    
                    $this->jsonOutput['downloadLink'] = $this->settingVars->get_full_url()."/".basename(dirname(dirname(__FILE__)))."/uploads/Cluster-Analysis/".$fileName;
                }
                else
                {
                    $this->jsonOutput['clusterAnalysisGridData'] = $clusterAnalysisGridData;
                }
            }
            else
                $this->jsonOutput['noDataFound'] = true;
        }
    }
    
    public function getAllClusters()
    {
        $query = "SELECT cl, cl_name FROM ".$this->settingVars->clustertable." WHERE cl_name != 'UNKNOWN'";
        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $clusters = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->projectManagerLinkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $clusters = $redisOutput;
        }

        if(isset($this->queryVars->projectConfiguration['has_cluster']) && $this->queryVars->projectConfiguration['has_cluster'] == 1 && in_array($this->settingVars->projectTypeID,[1,2,15,27])){
            if (!empty($clusters) && is_array($clusters)) {
                $clusterData = array();
                foreach($clusters as $data)
                    $clusterData[$data['cl']] = $data['cl_name'];
            }
            if (isset($this->queryVars->projectConfiguration['cluster_settings']) && !empty($this->queryVars->projectConfiguration['cluster_settings'])){

                $settings = explode("|", $this->queryVars->projectConfiguration['cluster_settings']);
                foreach($settings as $data)
                {
                    $tmp = array();
                    $tmp['cl'] = $data;
                    $tmp['cl_name'] = $clusterData[$data];
                    $clusterList[] = $tmp;
                }
            }
        } else {
            $clusterList = $clusters;
        }
        $clusterSelection = array();
        if (is_array($clusterList) && !empty($clusterList)) {
            foreach($clusterList as $data)
                $clusterSelection[] = array('label' => $data['cl_name'].' ('.$data['cl'].')', 'data' => $data['cl']);
        }
        $this->jsonOutput['clusterSelection'] = $clusterSelection;
    }
    
    public function getCustomerList() 
    {
        $customerField = $this->getPageConfiguration('customer_field', $this->settingVars->pageID);
        $skuField = $this->getPageConfiguration('sku_field', $this->settingVars->pageID);

        $this->buildDataArray(array($customerField[0], $skuField[0]));
        $this->buildPageArray();
        
        $gridFieldPart = explode("#", $skuField[0]);
        $tempGridField = strtoupper($this->dbColumnsArray[$gridFieldPart[0]]);
        $tempGridField = (count($gridFieldPart) > 1) ? strtoupper($tempGridField."_".$this->dbColumnsArray[$gridFieldPart[1]]) : $tempGridField;
        $skuAccountCsv = $this->settingVars->dataArray[$tempGridField]["NAME_CSV"];
        
        $this->measureFields[] = $this->settingVars->dataArray[strtoupper($this->dbColumnsArray[$customerField[0]])]["NAME"];
        
        $this->settingVars->tableUsedForQuery = array();
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        
        $this->queryPart = $this->getAll();
        
        $accountField = $this->settingVars->dataArray[strtoupper($this->dbColumnsArray[$customerField[0]])]["NAME"];
        $accountAlias = $this->settingVars->dataArray[strtoupper($this->dbColumnsArray[$customerField[0]])]["NAME_ALIASE"];
        $accountCsv = $this->settingVars->dataArray[strtoupper($this->dbColumnsArray[$customerField[0]])]["NAME_CSV"];
        
        $query = "SELECT ".$accountField." as ".$accountAlias.", ".$this->settingVars->maintable.".gid as GID FROM ".$this->settingVars->tablename.$this->queryPart." GROUP BY GID, ".$accountAlias;
        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }
        
        $customerSelection = array();
        if(is_array($result) && !empty($result))
        {
            $customerSelection[] = array('label' => 'Select '.$accountCsv, 'data' => '');
            foreach($result as $data)
                $customerSelection[] = array('label' => $data[$accountAlias], 'data' => $data['GID']);
        }
        
        $this->jsonOutput['customerSelectionLabel'] = $accountCsv;
        $this->jsonOutput['skuSelectionLabel'] = $skuAccountCsv;
        $this->jsonOutput['customerSelection'] = $customerSelection;
        $skuSelection[] = array('label' => 'Select '.$skuAccountCsv, 'data' => '');
        $this->jsonOutput['skuSelection'] = $skuSelection;
    }
    
    /*public function buildDataArray($fields) {
        if (empty($fields))
            return false;

        $configurationCheck = new config\ConfigurationCheck($this->settingVars, $this->queryVars);
        $configurationCheck->buildDataArray($fields);
        $this->dbColumnsArray = $configurationCheck->dbColumnsArray;
        $this->displayCsvNameArray = $configurationCheck->displayCsvNameArray;
        return;
    }*/

    public function buildDataArray($fields, $isCsvColumn = true, $appendTableNameWithDbColumn = false){
        if (empty($fields))
            return false;

        $configurationCheck = new config\ConfigurationCheck($this->settingVars, $this->queryVars);
        $configurationCheck->buildDataArray($fields, $isCsvColumn, $appendTableNameWithDbColumn);
        $this->dbColumnsArray       = $configurationCheck->dbColumnsArray;
        $this->displayCsvNameArray  = $configurationCheck->displayCsvNameArray;
        $this->displayDbColumnArray = $configurationCheck->displayDbColumnArray;
        return;
    }

    public function buildPageArray() {
        $this->requestAccountField = '';
        if (isset($_REQUEST['accountField']) && !empty($_REQUEST['accountField']) && isset($this->settingVars->dataArray)) {
            $this->requestAccountField = (isset($this->settingVars->dataArray[$_REQUEST['accountField']]) && isset($this->settingVars->dataArray[$_REQUEST['accountField']]['ID'])) ? $this->settingVars->dataArray[$_REQUEST['accountField']]['ID'] : $this->settingVars->dataArray[$_REQUEST['accountField']]['NAME'];
        } else {
            $this->requestAccountField = $_REQUEST['accountField'];
        }
    }
}
?>