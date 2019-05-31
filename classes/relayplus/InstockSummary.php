<?php

namespace classes\relayplus;

use datahelper;
use projectsettings;
use filters;
use db;
use config;

class InstockSummary extends config\UlConfig {
    /*     * ***
     * go()
     * Default gateway function, should be similar in all DATA CLASS
     * arguments:
     * $settingVars [project settingsGateway variables]
     * @return $this->jsonOutput with all data that should be sent to client app
     * *** */

    public function go($settingVars) {
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES
        $this->redisCache = new \utils\RedisCache($this->queryVars);
        $this->checkConfiguration();
        $this->buildDataArray();
        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll

        $action = $_REQUEST['action'];
        //COLLECTING TOTAL SALES  
        switch ($action) {
            case "skuChange":
                $this->skuSelect();
                break;
            case 'filterBySku':
                $this->outofstockGrid();
                break;
            case 'instockSummaryGrid':
                $this->instockSummaryGrid();
                break;
            case 'downloadExcelFile':
                $this->downloadExcelFile();
                break;                
        }

        return $this->jsonOutput;
    }
    
    private function downloadExcelFile()
    {
        $extraTable = $territoryPart = "";
        if (isset($_REQUEST["Level"]) && $_REQUEST["Level"] != "" && isset($_REQUEST["territoryLevel"]) && $_REQUEST["territoryLevel"] != "") {     
            if($_REQUEST["Level"] == '1')
            {
                $territoryPart = " AND " . $this->settingVars->territoryTable . ".Level".$_REQUEST["territoryLevel"]." <> 'NOT CALLED' AND ".$this->settingVars->territoryTable.".GID = ".$this->settingVars->GID." AND ".$this->settingVars->ranged_items.".SNO = ".$this->settingVars->territoryTable.".SNO AND ".$this->settingVars->territoryTable.".accountID = ".$this->settingVars->aid." AND ".$this->settingVars->ranged_items.".GID = ".$this->settingVars->territoryTable.".GID AND ".$this->settingVars->territoryTable.".accountID = ".$this->settingVars->aid." ";
                $extraTable = ", ".$this->settingVars->territoryTable;
            }
            else if($_REQUEST["Level"] == '2')
            {
                $territoryPart = " AND " . $this->settingVars->territoryTable . ".Level".$_REQUEST["territoryLevel"]." = 'NOT CALLED' AND ".$this->settingVars->territoryTable.".GID = ".$this->settingVars->GID." AND ".$this->settingVars->ranged_items.".SNO = ".$this->settingVars->territoryTable.".SNO AND ".$this->settingVars->territoryTable.".accountID = ".$this->settingVars->aid." AND ".$this->settingVars->ranged_items.".GID = ".$this->settingVars->territoryTable.".GID AND ".$this->settingVars->territoryTable.".accountID = ".$this->settingVars->aid." ";
                $extraTable = ", ".$this->settingVars->territoryTable;
            }
        }

        if (isset($_REQUEST["FS"]["F19"]) && $_REQUEST["FS"]["F19"] != "") { 
            $territoryPart .= " AND " . $this->region . " = '".$_REQUEST["FS"]["F19"]."' AND ".$this->settingVars->storetable.".SNO = ".$this->settingVars->ranged_items.".SNO AND ".$this->settingVars->storetable.".GID = ".$this->settingVars->ranged_items.".GID AND ".$this->settingVars->storetable.".GID = ".$this->settingVars->GID." AND ".$this->settingVars->storetable.".SNO = ".$this->settingVars->ranged_items.".SNO AND ".$this->settingVars->storetable.".GID = ".$this->settingVars->ranged_items.".GID ";
            // $extraTable .= ", ".$this->settingVars->storetable;
        }
        
        $selectPart = "";
        $excelHeaderArray = [];
        
        $accountFields = explode("-", $this->settingVars->pageArray[$this->settingVars->pageName]["ACCOUNT"]);
        
        foreach($accountFields as $data)
        {
            $selectPart .= $this->settingVars->dataArray[$data]['NAME']." AS ".$this->settingVars->dataArray[$data]['NAME_ALIASE'].", ";
            $excelHeaderArray[] = $this->settingVars->dataArray[$data]['NAME_CSV'];
        }
            
        $selectPart = trim($selectPart, ", ");
        
        $query = "SELECT ".$selectPart." ".
                    "FROM ".$this->settingVars->ranged_items." ". $extraTable.", ". $this->settingVars->storetable." ".
                    "WHERE clientID='".$this->settingVars->clientID."' AND opendate<insertdate AND ".$this->settingVars->ranged_items.".GID = ".$this->settingVars->GID . 
                    " AND ".$this->settingVars->ranged_items.".SNO = ".$this->settingVars->storetable.".SNO ".
                    " AND ".$this->settingVars->ranged_items.".GID = ".$this->settingVars->storetable.".gid ".
                    " AND ".$this->settingVars->storetable.".gid = ". $this->settingVars->GID.
                    " AND ".$this->settingVars->ranged_items.".skuID = ".$_REQUEST['selectedPIN']." ".
                    $territoryPart;
        //echo $query; exit;
        
        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }
        $storeArray = array();
        
        include_once $_SERVER['DOCUMENT_ROOT']."/ppt/Classes/PHPExcel.php";
        $objPHPExcel = new \PHPExcel();
        $objPHPExcel->getProperties()->setCreator("Ultralysis")->setTitle("Instock Summary")->setSubject("Instock Summary");
        
        $retailersDataSheet = new \PHPExcel_Worksheet($objPHPExcel, "Store List DL");
        $objPHPExcel->addSheet($retailersDataSheet, 0);
        $objPHPExcel->setActiveSheetIndex(0);
        $dataSheet = $objPHPExcel->getActiveSheet();
        
        $dataSheet->setCellValueByColumnAndRow(0, 1, "PIN: ".$_REQUEST['selectedPIN'].", SKU NAME: ".rawurldecode($_REQUEST['selectedPNAME']));
        
        $dataSheet->mergeCells('A1:F1');
        
        $col = 0;
        foreach($excelHeaderArray as $key => $rowData)
        {
            $dataSheet->setCellValueByColumnAndRow($col, 2, $rowData);
            $col++;
        }
        
        if (is_array($result) && !empty($result)) {
            foreach($result as $key => $data) {
                $col = 0;
                foreach($data as $innerKey => $rowData) {
                    $dataSheet->setCellValueByColumnAndRow($col, $key + 3, $rowData);
                    $col++;
                }
            }
        }
        
        $style = array('alignment' => array('horizontal' => \PHPExcel_Style_Alignment::HORIZONTAL_LEFT));
        $dataSheet->getDefaultStyle()->applyFromArray($style);        
        
        $objPHPExcel->getSheetByName('Worksheet')->setSheetState(\PHPExcel_Worksheet::SHEETSTATE_HIDDEN);
        $objPHPExcel->setActiveSheetIndex(0);        
        
        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
        $fileName = "Instock-Summary-StoreListDL-" . date("Y-m-d-h-i-s") . ".xlsx";
        $savePath = dirname(__FILE__)."/../../uploads/Instock-Summary/";
        chdir($savePath);
        $objWriter->save($fileName);
        $this->jsonOutput['downloadLink'] = $this->settingVars->get_full_url()."/".basename(dirname(dirname(dirname(__FILE__))))."/uploads/Instock-Summary/".$fileName;
    }
    
    /**
     * instockSummaryGrid()
     * This Function is used to retrieve list based on set parameters     
     * @access private  
     * @return $this->jsonOutput with all data that should be sent to client app     
     */
    private function instockSummaryGrid() {
        
        /**
         * COLLECTING VALID AND TRAITED STORES
         * VALID STORES:     COUNT OF STORES WHERE VSI = 1 IN RELAY_PLUS_TV TABLE
         * TRAITED STORES:   COUNT OF STORES WHERE TSI = 1 IN RELAY_PLUS_TV TABLE
         **/
        $extraTable = $territoryPart = "";
        if (isset($_REQUEST["Level"]) && $_REQUEST["Level"] != "" && isset($_REQUEST["territoryLevel"]) && $_REQUEST["territoryLevel"] != "") {     
            if($_REQUEST["Level"] == '1')
            {
                $territoryPart = " AND " . $this->settingVars->territoryTable . ".Level".$_REQUEST["territoryLevel"]." <> 'NOT CALLED' AND ".$this->settingVars->territoryTable.".GID = ".$this->settingVars->GID." AND ".$this->settingVars->ranged_items.".SNO = ".$this->settingVars->territoryTable.".SNO AND ".$this->settingVars->territoryTable.".accountID = ".$this->settingVars->aid." AND ".$this->settingVars->ranged_items.".GID = ".$this->settingVars->territoryTable.".GID AND ".$this->settingVars->territoryTable.".accountID = ".$this->settingVars->aid." ";
                $extraTable = ", ".$this->settingVars->territoryTable;
            }
            else if($_REQUEST["Level"] == '2')
            {
                $territoryPart = " AND " . $this->settingVars->territoryTable . ".Level".$_REQUEST["territoryLevel"]." = 'NOT CALLED' AND ".$this->settingVars->territoryTable.".GID = ".$this->settingVars->GID." AND ".$this->settingVars->ranged_items.".SNO = ".$this->settingVars->territoryTable.".SNO AND ".$this->settingVars->territoryTable.".accountID = ".$this->settingVars->aid." AND ".$this->settingVars->ranged_items.".GID = ".$this->settingVars->territoryTable.".GID AND ".$this->settingVars->territoryTable.".accountID = ".$this->settingVars->aid." ";
                $extraTable = ", ".$this->settingVars->territoryTable;
            }
        }

        if (isset($_REQUEST["FS"]["F19"]) && $_REQUEST["FS"]["F19"] != "") { 
            $territoryPart .= " AND " . $this->region . " = '".$_REQUEST["FS"]["F19"]."' AND ".$this->settingVars->storetable.".SNO = ".$this->settingVars->ranged_items.".SNO AND ".$this->settingVars->storetable.".GID = ".$this->settingVars->ranged_items.".GID AND ".$this->settingVars->storetable.".GID = ".$this->settingVars->GID." AND ".$this->settingVars->storetable.".SNO = ".$this->settingVars->ranged_items.".SNO AND ".$this->settingVars->storetable.".GID = ".$this->settingVars->ranged_items.".GID ";
            $extraTable .= ", ".$this->settingVars->storetable;
        }
        
        $query = "SELECT DISTINCT skuID AS SKUID" .
                    ",COUNT(CASE WHEN tsi=1 THEN ".$this->settingVars->ranged_items.".SNO END) AS TRAITED_STORES ".                
                    ",COUNT(CASE WHEN vsi=1 THEN ".$this->settingVars->ranged_items.".SNO END) AS VALID_STORES ".                
                    "FROM ".$this->settingVars->ranged_items." ". $extraTable." 
                     WHERE clientID='".$this->settingVars->clientID."' AND opendate<insertdate AND ".$this->settingVars->ranged_items.".GID = ".$this->settingVars->GID . $territoryPart ." ORDER BY SKUID";
        //echo $query; exit;
        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }
        $storeCounterArray = array();
        
        if (is_array($result) && !empty($result)) {
            foreach ($result as $key => $data) {
                $storeCounterArray[$data['SKUID']]['VALID_STORES']   = $data['VALID_STORES'];
                $storeCounterArray[$data['SKUID']]['TRAITED_STORES'] = $data['TRAITED_STORES'];
            }        
        } // end if
        

        //THIS SUBQUERY COLLECTS ALL STORES HAVING OHQ>0 ON LATEST DATE , AND PROVIDES SIN,SNO COMBINATION FOR PARENT QUERY
        $subQueryToCollectStoresHavingValidOHQ = "(SELECT SIN AS SIN".
                    ",SNO AS SNO".
                    ",SUM(OHQ) AS SumOfOHQ ". 
                    "FROM ".$this->settingVars->maintable." ". 
                    "WHERE ".$this->settingVars->DatePeriod."= '" . filters\timeFilter::$tyDaysRange[0] . "' ".
                    "AND OpenDate < insertdate ".
                    "GROUP BY SIN,SNO ".
                    "HAVING SumOfOHQ>0)";
        
        /**
         * COLLECTING VALID STORES HAVING STOCK [INSTOCK STORES]
         * INSTOCK STORES: COUNT OF VALID STORES HAVING OHQ>0 ON LATEST DATE
         **/
        /* $query  = "SELECT a.SIN SKUID".
                    ",COUNT(*) AS INSTOCK_STORES ".
                    "FROM $subQueryToCollectStoresHavingValidOHQ a INNER JOIN ".$this->settingVars->ranged_items." b ".
                    "ON (a.SIN = b.skuID AND a.SNO = b.SNO) ".
                    "WHERE b.VSI = 1 AND b.clientID='".$this->settingVars->clientID."' ".
                    "GROUP BY SKUID ".
                    "ORDER BY SKUID"; */

                /*AND ".$this->settingVars->ranged_items.".GID = ".$this->settingVars->GID .*/

        $query  = "SELECT a.SIN SKUID".
                    ",COUNT(*) AS INSTOCK_STORES ".
                    "FROM $subQueryToCollectStoresHavingValidOHQ a INNER JOIN ".$this->settingVars->ranged_items." ".
                    "ON (a.SIN = ".$this->settingVars->ranged_items.".skuID AND a.SNO = ".$this->settingVars->ranged_items.".SNO) ".$extraTable.
                    " WHERE ".$this->settingVars->ranged_items.".VSI = 1 AND ".$this->settingVars->ranged_items.".clientID='".$this->settingVars->clientID."' AND ".$this->settingVars->ranged_items.".GID = ".$this->settingVars->GID." $territoryPart GROUP BY SKUID ".
                    "ORDER BY SKUID";
        //echo $query; exit;
        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }
        
        $instockStoresData = array();
        
        if (is_array($result) && !empty($result)) {
            foreach ($result as $key => $data){
                $storeCounterArray[$data['SKUID']]['INSTOCK_STORES'] = $data['INSTOCK_STORES'];
            }    
        } // end if
        
        /**
         * COLLECTING NOT-VALID STORES HAVING STOCK [NOT VALID W/STOCK]
         * NOT VALID W/STOCK: COUNT OF STORES THOSE ARE NOT IN RELAY_PLUS_TV TABLE AND HAVING OHQ>0 ON LATEST DATE  
         **/
        $territoryPart = $joinPart = "";
        if (isset($_REQUEST["Level"]) && $_REQUEST["Level"] != "" && isset($_REQUEST["territoryLevel"]) && $_REQUEST["territoryLevel"] != "") {     
            if($_REQUEST["Level"] == '1')
            {
                $joinPart .= " LEFT OUTER JOIN ".$this->settingVars->territoryTable." t ON (a.SNO = t.SNO) ";
                $territoryPart .= " AND t.Level".$_REQUEST["territoryLevel"]." <> 'NOT CALLED' AND t.accountID = ".$this->settingVars->aid." AND t.GID = ".$this->settingVars->GID." ";
            }
            else if($_REQUEST["Level"] == '2')
            {
                $joinPart .= " LEFT OUTER JOIN ".$this->settingVars->territoryTable." t ON (a.SNO = t.SNO) ";
                $territoryPart .= " AND t.Level".$_REQUEST["territoryLevel"]." = 'NOT CALLED' AND t.accountID = ".$this->settingVars->aid." AND t.GID = ".$this->settingVars->GID." ";
            }
        }         
         
        if (isset($_REQUEST["FS"]["F19"]) && $_REQUEST["FS"]["F19"] != "") {        
            $joinPart .= "LEFT OUTER JOIN ".$this->settingVars->storetable." ON (".$this->settingVars->storetable.".SNO = b.SNO AND ".$this->settingVars->storetable.".GID = b.GID) ";
            $territoryPart .= " AND ".$this->region." = '".$_REQUEST["FS"]["F19"]."' AND ".$this->settingVars->storetable.".GID = ".$this->settingVars->GID." ";
        }           
         
        $query  = "SELECT a.SIN SKUID".
                    ",COUNT(*) AS INVALID_WSTOCK_STORES ".
                    "FROM $subQueryToCollectStoresHavingValidOHQ a LEFT OUTER JOIN ".$this->settingVars->ranged_items." b ".
                    "ON (a.SIN = b.skuID AND a.SNO = b.SNO) $joinPart 
                    WHERE b.skuID IS NULL OR b.SNO IS NULL AND b.clientID='".$this->settingVars->clientID."' AND b.GID = ".$this->settingVars->GID."  $territoryPart GROUP BY SKUID ".
                    "ORDER BY SKUID";
        //echo $query; exit;
        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }
        
        $instockStoresData = array();
        
        if (is_array($result) && !empty($result)) {
            foreach ($result as $key => $data){
                $storeCounterArray[$data['SKUID']]['INVALID_WSTOCK_STORES'] = $data['INVALID_WSTOCK_STORES'];
            }
        }
        
        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $this->measureFields[] = $this->skuName;
        $this->measureFields[] = $this->storeID;
        
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->queryPart = $this->getAll();
        
        //MAIN TABLE QUERY
        $query = "SELECT ".$this->settingVars->maintable.".SIN AS SKUID " .
                ",".$this->skuName." AS SKU " .
                ",SUM((CASE WHEN ".$this->settingVars->DatePeriod." IN('" . filters\timeFilter::$tyDaysRange[0] . "') THEN ".$this->ohq." ELSE 0 END)) AS OHQ" .
                /* ",SUM((CASE WHEN ".$this->settingVars->DatePeriod." IN('" . filters\timeFilter::$tyDaysRange[0] . "') THEN ".$this->storeTrans."+".$this->storeWhs."+".$this->storeOrder."+".$this->ohq." END)) ALLSTOCKQTY" . */
                ",SUM((CASE WHEN ".filters\timeFilter::$tyWeekRange ." THEN ".$this->settingVars->ProjectValue." ELSE 0 END)) AS SALES" .
                " FROM " . $this->settingVars->tablename . $this->queryPart .
                /* " AND " . $this->settingVars->maintable . ".SNO=" . $this->settingVars->ranged_items . ".SNO" .
                " AND " . $this->settingVars->maintable . ".SIN=" . $this->settingVars->ranged_items . ".skuID" .
                " AND " . $this->settingVars->ranged_items . ".clientID='" . $this->settingVars->clientID."'".
                " AND " . $this->settingVars->ranged_items . ".GID='" . $this->settingVars->GID."'".
                " AND " . $this->settingVars->ranged_items . ".opendate < " . $this->settingVars->ranged_items . ".insertdate" . */
                " GROUP BY SKUID, SKU";
        //echo $query;exit;
        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }

        $dataSet = array();
        $cnt = 0;
        if ((is_array($result) && !empty($result)) && (is_array($storeCounterArray) && !empty($storeCounterArray))) {
            
            foreach ($result as $key => $value) {

                $index                  = $value['SKUID'];
                $validStores            = array_key_exists($value['SKUID'], $storeCounterArray) ? (int)$storeCounterArray[$index]['VALID_STORES'] : 0;
                $traitedStores          = array_key_exists($value['SKUID'], $storeCounterArray) ? (int)$storeCounterArray[$index]['TRAITED_STORES'] : 0;
                $instockStores          = array_key_exists($value['SKUID'], $storeCounterArray) ? (int)$storeCounterArray[$index]['INSTOCK_STORES'] : 0;
                $notValidWStockStores   = array_key_exists($value['SKUID'], $storeCounterArray) ? (int)$storeCounterArray[$index]['INVALID_WSTOCK_STORES'] : 0;

                if ($traitedStores == 0)
                    continue;
                
                $dataSet[$cnt]['SKUID']             = $value['SKUID'];
                $dataSet[$cnt]['SKU']               = $value['SKU'];
                $dataSet[$cnt]['SALES']             = number_format($value['SALES'], 2, '.', '');
                $dataSet[$cnt]['TSI']               = $traitedStores;
                $dataSet[$cnt]['VSI']               = $validStores;
                $dataSet[$cnt]['INSTOCK_STORES']    = $instockStores;
                $dataSet[$cnt]['INSTOCK_GAP']       = $validStores - $instockStores;
                $dataSet[$cnt]['INSTOCK']           = $validStores == 0 ? 0 : number_format(($instockStores / $validStores) * 100, 1, '.', '');
                $dataSet[$cnt]['NVWS']              = $notValidWStockStores;
                $dataSet[$cnt]['OHQ']               = (int)$value['OHQ'];
                //$dataSet[$cnt]['ALLSTOCKQTY']       = (int)$value['ALLSTOCKQTY'];

                $cnt++;
            }
            
        } // end if

        $this->jsonOutput['InstockSummaryStoreListDLCol'] = $this->settingVars->InstockSummaryStoreListDLCol;
        $this->jsonOutput['instockSummaryGrid'] = $dataSet;
    }

    /**
     * outofstockGrid()
     * This Function is used to retrieve list based on set parameters     
     * @access private  
     * @return $this->jsonOutput with all data that should be sent to client app     
     */
    private function outofstockGrid() {
        
        $outofstockGridDataBinding = array();

        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $this->measureFields[] = $this->skuID;
        $this->measureFields[] = $this->storeID;
        
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->queryPart = $this->getAll();     
        
        $lastSalesDays      = datahelper\Common_Data_Fetching_Functions::getLastSalesDays($this->settingVars->maintable.".SIN", $this->settingVars->maintable.".SNO", $this->settingVars, $this->queryPart);

        // get VSI according to all mydate
        $query = "SELECT distinct skuID AS TPNB" .
                ",SNO " .
                ",VSI " .
                ",TSI " .
                ",StoreTrans " .
                "FROM " . $this->settingVars->ranged_items.
                " WHERE clientID='".$this->settingVars->clientID."' AND GID = ".$this->settingVars->GID;
        //echo $query; exit;
        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }

        $tsiStatusData = $vsiStatusData = $storeTransData = array();
        
        if (is_array($result) && !empty($result)) {
            
            foreach ($result as $key => $row) {
                $vsiStatusData[$row['TPNB'] . '_' . $row['SNO']] = $row['VSI'];
                $tsiStatusData[$row['TPNB'] . '_' . $row['SNO']] = $row['TSI'];
                $storeTransData[$row['TPNB'] . '_' . $row['SNO']] = $row['StoreTrans'];
            }
            
            // For Territory
            if (isset($_REQUEST["territoryLevel"]) && $_REQUEST["territoryLevel"] != "") {
                $addTerritoryColumn = ",".$this->settingVars->territoryTable . ".Level".$_REQUEST["territoryLevel"]." AS TERRITORY";
                $addTerritoryGroup = ",TERRITORY";
            }
            else
            {
                $addTerritoryColumn = '';
                $addTerritoryGroup = '';
            }
            
            $query = "SELECT " . $this->skuID . " AS TPNB" .
                    ",TRIM(MAX(" . $this->skuName . ")) AS SKU" .
                    $addTerritoryColumn.
                    "," . $this->storeID . " AS SNO " .
                    ",TRIM(MAX(" . $this->storeName . ")) AS STORE " .
                    ",TRIM(MAX(" . $this->settingVars->clusterID . ")) AS CLUSTER " .
                    ",TRIM(MAX(" . $this->ana . ")) AS ANA " .
                    ",SUM((CASE WHEN " . $this->settingVars->ProjectVolume . ">0 THEN 1 ELSE 0 END)*" . $this->settingVars->ProjectVolume . ") AS SALES " .
                    ",SUM((CASE WHEN " . $this->settingVars->DatePeriod . "= '" . filters\timeFilter::$tyDaysRange[0] . "' THEN 1 ELSE 0 END)*" . $this->ohq . ") AS STOCK " .
                    ",SUM((CASE WHEN " . $this->settingVars->DatePeriod . "= '" . filters\timeFilter::$tyDaysRange[0] . "' THEN 1 ELSE 0 END)*" . $this->msq . ") AS SHELF " .
                    ",TRIM(MAX(" . $this->planogram . ")) AS PLANOGRAM " .
                    "FROM " . $this->settingVars->tablename . " " . $this->queryPart .
                    "AND " . filters\timeFilter::$tyWeekRange .
                    "AND ". $this->odate ." < ". $this->idate .
                    " GROUP BY TPNB,SNO ".$addTerritoryGroup." HAVING STOCK<1 ";
            //echo $query;exit;
            
            $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
            if ($redisOutput === false) {
                $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
                $this->redisCache->setDataForHash($result);
            } else {
                $result = $redisOutput;
            }
            
            if (is_array($result) && !empty($result)) {
                
                foreach ($result as $value) {

                    $index  = $value['TPNB'] .'_'. $value['SNO'];
                    $status = (array_key_exists($index, $vsiStatusData) && $vsiStatusData[$index] == 1) ? 1 : 0;

                    if( $status == 1 ) {

                        if ($tsiStatusData[$index] == 1 && $vsiStatusData[$index] == 1)
                            $tsiVsi = "Y/Y";
                        else if ($tsiStatusData[$index] == 1 && $vsiStatusData[$index] == 0)
                            $tsiVsi = "Y/N";
                        else if ($tsiStatusData[$index] == 0 && $vsiStatusData[$index] == 1)
                            $tsiVsi = "N/Y";
                        else
                            $tsiVsi = "N/N";
                            
                        $aveDailySales          = $value['SALES'] / filters\timeFilter::$daysTimeframe;

                        $row                    = array();              
                        $row['SNO']             = $value['SNO'];
                        $row['STORE']           = utf8_encode($value['STORE']);
                        $row['CLUSTER']         = utf8_encode($value['CLUSTER']);
                        $row['ANA']             = utf8_encode($value['ANA']);
                        $row['SKUID']           = $value['TPNB'];
                        $row['SKU']             = utf8_encode($value['SKU']);
                        $row['STOCK']           = $value['STOCK'];
                        $row['TRANSIT']         = $storeTransData[$index];
                        $row['SHELF']           = $value['SHELF'];
                        $row['AVE_DAILY_SALES'] = number_format($aveDailySales, 2, '.', '');
                        $row['LAST_SALE']       = $lastSalesDays[$row['SKUID'] . "_" . $row['SNO']];
                        $row['PLANOGRAM']       = ($value['PLANOGRAM'] == null) ? '' : $value['PLANOGRAM'];
                        $row['TSIVSI']          = $tsiVsi;
                        
                        if($value['TERRITORY'])
                            $row['TERRITORY'] = $value['TERRITORY'];

                        array_push($outofstockGridDataBinding, $row);
                   }
                }

                foreach ($outofstockGridDataBinding as $key => $value) {
                    //still going to sort by firstname
                    $emp[$key] = $value['AVE_DAILY_SALES'];
                }
                if (is_array($emp) && !empty($emp))
                    array_multisort($emp, SORT_DESC, $outofstockGridDataBinding);
                
            }
        
        }
        
        $this->jsonOutput['outofStockGrid'] = $outofstockGridDataBinding;
    }

    private function skuSelect() {
        
        $this->settingVars->useRequiredTablesOnly = true;
        $this->queryPart = $this->getAll();     
    
        $query = "SELECT " . $this->settingVars->DatePeriod . ",  DATE_FORMAT(" . $this->settingVars->DatePeriod . ",'%a %e %b') AS DAY" .
                ",SUM(" . $this->settingVars->ProjectVolume . ") AS SALES " .
                ",SUM(".$this->ohaq.") AS OHAQ" .
                ",SUM(".$this->baq.") AS BAQ" .
                ",SUM(" . $this->ohq . ") AS STOCK " .                
                ",SUM((CASE WHEN " . $this->gsq . ">0 THEN 1 ELSE 0 END)*" . $this->gsq . ") AS GSQ " .
                // ",SUM((CASE WHEN " . $storeTrans . ">0 THEN 1 ELSE 0 END)*" . $storeTrans . ") AS TRANSIT " .
                "FROM " . $this->settingVars->tablename . " " . $this->queryPart .
                //"AND " . filters\timeFilter::$tyWeekRange .
                "GROUP BY DAY, ". $this->settingVars->DatePeriod ." " .
                "ORDER BY ". $this->settingVars->DatePeriod ." ASC";
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

                $value['SALES'][]   = $data['SALES'];
                $value['STOCK'][]   = $data['STOCK'];               
                $value['TRANSIT'][] = 0;
                $value['ADJ'][]     = $data['OHAQ'] + $data['BAQ'];
                $value['DAY'][]     = $data['DAY'];
                $value['GSQ'][]     = $data['GSQ'];
            }
            
        } // end if

        $this->jsonOutput['skuSelect'] = $value;
    }

    public function checkConfiguration(){
        if(!isset($this->settingVars->ranged_items) || empty($this->settingVars->ranged_items))
            $this->configurationFailureMessage("Relay Plus TV configuration not found.");

        $configurationCheck = new config\ConfigurationCheck($this->settingVars, $this->queryVars);
        $configurationCheck->checkClusterConfiguration();

        return ;
    }

    public function buildDataArray() {

        $this->skuID    = key_exists('ID', $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['SKU_FIELD']]) ? $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['SKU_FIELD']]['ID'] : $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['SKU_FIELD']]['NAME'];
        $this->storeID  = key_exists('ID', $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['STORE_FIELD']]) ? $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['STORE_FIELD']]['ID'] : $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['STORE_FIELD']]['NAME'];
        $this->storeName= $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['STORE_FIELD']]['NAME'];
        $this->skuName  = $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['SKU_FIELD']]['NAME'];
        $this->catName  = $this->settingVars->dataArray['F1']['NAME'];  
        $this->ohq      = $this->settingVars->dataArray['F12']['NAME'];
        $this->storeTrans = $this->settingVars->dataArray['F13']['NAME'];
        $this->msq      = $this->settingVars->dataArray['F14']['NAME'];
        $this->planogram= $this->settingVars->dataArray['F6']['NAME'];
        $this->tsi      = $this->settingVars->dataArray['F7']['NAME'];
        $this->vsi      = $this->settingVars->dataArray['F8']['NAME'];
        $this->ana        = $this->settingVars->dataArray['F5']['NAME'];
        $this->storeOrder = $this->settingVars->dataArray['F16']['NAME'];
        $this->storeWhs   = $this->settingVars->dataArray['F17']['NAME'];
        $this->odate      = $this->settingVars->dataArray['F21']['NAME'];
        $this->idate      = $this->settingVars->dataArray['F22']['NAME'];
        $this->gsq      = $this->settingVars->dataArray['F9']['NAME'];
        $this->ohaq     = $this->settingVars->dataArray['F10']['NAME'];
        $this->baq      = $this->settingVars->dataArray['F11']['NAME'];
        $this->region      = $this->settingVars->dataArray['F19']['NAME'];

    }

}

?>