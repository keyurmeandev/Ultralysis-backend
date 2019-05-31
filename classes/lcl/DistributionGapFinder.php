<?php

namespace classes\lcl;

use projectsettings;
use datahelper;
use filters;
use db;
use config;

class DistributionGapFinder extends config\UlConfig {

    public $timeRange;
    public $timeFrame;
    public $sellingNotsellingField;
    public $accountFields;
    public $sellingStoresFields;
    public $totalStoresFields;
    public $accountsName;
    public $sellingStoresAccounts;
    public $totalStoresAccounts;
    public $dbColumnsArray;
    public $displayCsvNameArray;
    public $displayDbColumnArray;

    /*****
     * Default gateway function, should be similar in all DATA CLASS
     * arguments:
     *  $settingVars [project settingsGateway variables]
     * @return $jsonOutput with all data that should be sent to client app
     *****/
    public function go($settingVars) {
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES
        $this->redisCache = new \utils\RedisCache($this->queryVars);

        /*[START] ON THIS PAGE WE DONT REQUIRED THE TIMEFILTES*/
        filters\timeFilter::$tyWeekRange = NULL;
        filters\timeFilter::$lyWeekRange = NULL;
        /*[END] ON THIS PAGE WE DONT REQUIRED THE TIMEFILTES*/

        $this->settingVars->pageName = (empty($this->settingVars->pageName)) ? $this->settingVars->pageID . '_DistributionGapFinderPage' : $this->settingVars->pageName;
        $this->ValueVolume = getValueVolume($this->settingVars);

        if ($this->settingVars->isDynamicPage) {
            $this->sellingNotsellingField = $this->getPageConfiguration('selling_not_selling_field', $this->settingVars->pageID)[0];
            $this->accountFields          = $this->getPageConfiguration('account_list_field', $this->settingVars->pageID);
            $this->allExportAccountFields = $this->getPageConfiguration('all_export_list_field', $this->settingVars->pageID);
            $this->sellingStoresFields    = $this->getPageConfiguration('selling_stores_accounts', $this->settingVars->pageID);
            $this->totalStoresFields      = $this->getPageConfiguration('total_stores_accounts', $this->settingVars->pageID);
            $this->pinField               = $this->getPageConfiguration('pin_field', $this->settingVars->pageID)[0];
            $this->bottomGridAccounts     = $this->getPageConfiguration('bottom_grid_accounts', $this->settingVars->pageID);
            $this->stockQtyField          = $this->getPageConfiguration('stock_qty_field', $this->settingVars->pageID)[0];

            if(($this->settingVars->calculateVsiOhq && empty($this->pinField)) || (!empty($this->stockQtyField) && empty($this->pinField))) {
                $response = array("configuration" => array("status" => "fail", "messages" => array("Page isn't configured properly.")));
                echo json_encode($response);
                exit();
            }

            $tempBuildFieldsArray = array($this->sellingNotsellingField);
            if(!empty($this->stockQtyField))
                $tempBuildFieldsArray[] = $this->stockQtyField;

            $tempBuildFieldsArray = array_merge($tempBuildFieldsArray, $this->accountFields, $this->allExportAccountFields, $this->totalStoresFields, $this->sellingStoresFields, $this->bottomGridAccounts);

            $buildDataFields = array();
            foreach ($tempBuildFieldsArray as $value)
                if (!in_array($value, $buildDataFields))
                    $buildDataFields[] = $value;


            $this->buildDataArray($buildDataFields);
            $this->buildPageArray();
        } else {
            //$this->bannerAccount = (isset($this->settingVars->dataArray['F11']) && isset($this->settingVars->dataArray['F11']['ID'])) ? $this->settingVars->dataArray['F11']['ID'] : $this->settingVars->dataArray['F11']['NAME'];
            //$this->provinceAccount = (isset($this->settingVars->dataArray['F15']) && isset($this->settingVars->dataArray['F15']['ID'])) ? $this->settingVars->dataArray['F15']['ID'] : $this->settingVars->dataArray['F15']['NAME'];
        }

        if (isset($_REQUEST["timeFrame"]) && !empty($_REQUEST["timeFrame"])) {
            $this->timeFrame = $_REQUEST["timeFrame"];
        } else {
            $this->timeFrame = 12;
        }

        $this->getAll();
        $action = $_REQUEST["action"];

        switch ($action) {
            case "reload":                
                $this->reload();
                break;
            case "getallcomb":
                $this->getAllSkusNonSellingStores();
                break;
            case "getSellingNotSelling": 
                $this->changeGrid();
                break;
        }
        return $this->jsonOutput;
    }

    public function reload() {
        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
            $this->getTotalStoresAccountsFields();
            $this->setSellingNotsellingColumn(); // GETTING SELLING NOTSELLING COLUMNS
            
            if(is_array($this->bottomGridAccounts) && !empty($this->bottomGridAccounts))
            {
                foreach($this->bottomGridAccounts as $data)
                    $this->extrColsName[$this->settingVars->dataArray[$data]['NAME_ALIASE']] = $this->settingVars->dataArray[$data]['NAME_CSV'];
                $this->jsonOutput['extrColsNameForBottomGrids'] = $this->extrColsName;
            }
            
        }else{
            $this->gridValue(); //ADDING TO OUTPUT        
        }
    }

    private function changeGrid() {
        $this->getSellingStores(); //ADDING TO OUTPUT
        $this->getNotSellingStores(); //ADDING TO OUTPUT
    }

    public function getTotalStoresAccountsFields() {
        foreach ($this->accountsName as $key => $data) {
            $nameAlias = $this->settingVars->dataArray[$data]["NAME_ALIASE"];
            $this->jsonOutput["FIELD_NAMES"][] = array($this->displayCsvNameArray[$this->accountFields[$key]] => $nameAlias);
        }
    }

    public function setSellingNotsellingColumn() {

        $tempCol = array();
        foreach ($this->accountsName as $key => $value) {
            $tempCol[$this->settingVars->dataArray[strtoupper($this->accountsName[$key])]['NAME_ALIASE']] = $this->displayCsvNameArray[$this->accountFields[$key]];
        }
        $tempCol["SALES"] = "SALES";

        $tempCol["TTL_STORES"] = "TOTAL STORES";
        $tempCol["SLNG_STORES"] = "STORES SELLING";
        $tempCol["DIST_PCT"] = "DIST %";
        $tempCol["GAP_VALUE"] = "GAP";
        $this->jsonOutput["TOP_GRID_COLUMN_NAME"] = $tempCol;


        $selling = explode("#", $this->sellingNotsellingField);
        $notsellingNotsellingName = $sellingNotsellingName = $selling[0];
        $notsellingNotsellingID = $sellingNotsellingID = (count($selling) > 1) ? $selling[1] : 'ID';
        $sales = "SALES";

        /* $notselling = explode("#", $this->sellingNotsellingField);
          $notsellingNotsellingID = $notselling[0];
          $notsellingNotsellingName = count($notselling)>1 ? $notselling[1] : $notselling[0].' NAME'; */
        $SALES_LW = "TOTAL COMPANY";

        // SELLING GRID COLUMN
        if($this->settingVars->calculateVsiOhq)
            $this->jsonOutput["SELLING"] = array("SNO" => $this->displayCsvNameArray[$sellingNotsellingID], "SNAME" => $this->displayCsvNameArray[$sellingNotsellingName], "SALES" => $sales, "ValidStore" => "Valid Store", "stock" => "Stock");
        else if(!empty($this->stockQtyField))
            $this->jsonOutput["SELLING"] = array("SNO" => $this->displayCsvNameArray[$sellingNotsellingID], "SNAME" => $this->displayCsvNameArray[$sellingNotsellingName], "SALES" => $sales, "stock" => "Stock");
        else
            $this->jsonOutput["SELLING"] = array("SNO" => $this->displayCsvNameArray[$sellingNotsellingID], "SNAME" => $this->displayCsvNameArray[$sellingNotsellingName], "SALES" => $sales);

        // NOT SELLING COLUMN
        if($this->settingVars->calculateVsiOhq)
            $this->jsonOutput["NOTSELLING"] = array("SNO" => $this->displayCsvNameArray[$notsellingNotsellingID], "SNAME" => $this->displayCsvNameArray[$notsellingNotsellingName], "ACTIVE" => "ACTIVE", "SALES_LW" => $SALES_LW, "DATE_LAST_SOLD" => "DATE LAST SOLD", "ValidStore" => "Valid Store", "stock" => "Stock");
        else if(!empty($this->stockQtyField))
            $this->jsonOutput["NOTSELLING"] = array("SNO" => $this->displayCsvNameArray[$notsellingNotsellingID], "SNAME" => $this->displayCsvNameArray[$notsellingNotsellingName], "ACTIVE" => "ACTIVE", "SALES_LW" => $SALES_LW, "DATE_LAST_SOLD" => "DATE LAST SOLD", "stock" => "Stock");
        else
            $this->jsonOutput["NOTSELLING"] = array("SNO" => $this->displayCsvNameArray[$notsellingNotsellingID], "SNAME" => $this->displayCsvNameArray[$notsellingNotsellingName], "ACTIVE" => "ACTIVE", "SALES_LW" => $SALES_LW, "DATE_LAST_SOLD" => "DATE LAST SOLD");

        if(isset($this->pinField) && !empty($this->pinField)) {
            $paramsName = explode(".",$this->pinField);
            if(isset($paramsName[1]) && $paramsName[1] == 'PIN') {
                $this->jsonOutput["SELLING"] = array_slice($this->jsonOutput["SELLING"], 0, 1, true) + array('PIN'=>'PIN') + array_slice($this->jsonOutput["SELLING"], 1, count($this->jsonOutput["SELLING"]) - 1, true) ;
                $this->jsonOutput["NOTSELLING"] = array_slice($this->jsonOutput["NOTSELLING"], 0, 1, true) + array('PIN'=>'PIN') + array_slice($this->jsonOutput["NOTSELLING"], 1, count($this->jsonOutput["NOTSELLING"]) - 1, true) ;
            }
        }
    }

    public function gridValue() {
        //COLLECT TOTAL STORES
        $this->collectTotalStores();
        //COLLECT SELLING STORES
        $this->collectSellingStores();
        
        $selectPart = array();
        $groupByPart = array();
        $gridAccountListToFetchQueryDataLater = array();
        
        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $measureSelectRes    = $this->prepareMeasureSelectPart();
        $this->measureFields = $measureSelectRes['measureFields'];
        $measureSelectionArr = $measureSelectRes['measureSelectionArr'];
        $havingTYValue       = $measureSelectRes['havingTYValue'];
        $havingLYValue       = $measureSelectRes['havingLYValue'];

        foreach ($this->accountsName as $key => $data) {
            if (key_exists('ID', $this->settingVars->dataArray[$data])) {
                $this->measureFields[] = $this->settingVars->dataArray[$data]['ID'];
                $selectPart[] = $this->settingVars->dataArray[$data]['ID'] . " AS '" . $this->settingVars->dataArray[$data]['ID_ALIASE'] . "'";
                $groupByPart[] = $gridAccountListToFetchQueryDataLater[] = "'" . $this->settingVars->dataArray[$data]['ID_ALIASE'] . "'";
                $GroupByFieldArr[]  = $this->settingVars->dataArray[$data]['ID_ALIASE'];

                $this->measureFields[] = $this->settingVars->dataArray[$data]['NAME'];
                $selectPart[] = "MAX(" . $this->settingVars->dataArray[$data]['NAME'] . ") AS '" . $this->settingVars->dataArray[$data]['NAME_ALIASE'] . "'";
                $gridAccountListToFetchQueryDataLater[] = $this->settingVars->dataArray[$data]['NAME_ALIASE'];
            } else {
                $this->measureFields[] = $this->settingVars->dataArray[$data]['NAME'];
                $selectPart[] = $this->settingVars->dataArray[$data]['NAME'] . " AS '" . $this->settingVars->dataArray[$data]['NAME_ALIASE'] . "'";
                $groupByPart[] = $gridAccountListToFetchQueryDataLater[] = "'" . $this->settingVars->dataArray[$data]['NAME_ALIASE'] . "'";
                $GroupByFieldArr[]  = $this->settingVars->dataArray[$data]['NAME_ALIASE'];
            }
        }

        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->getAll();

        /*$options = array();
        if (!empty($this->timeRange))
            $options['tyLyRange']['SALES'] = " 1=1 ";
        $measureSelect = config\MeasureConfigure::prepareSelect($this->settingVars, $this->queryVars, array('M'.$_REQUEST['ValueVolume']), $options);*/

        $measureSelect = implode(", ", $measureSelectionArr);
        $query = "SELECT " . implode(",", $selectPart) .
                " , ".$measureSelect." " .
                " FROM " . $this->settingVars->tablename . $this->queryPart . $this->timeRange . " " .
                " GROUP BY " . implode(",", $groupByPart);
                /*" ORDER BY SALES DESC ";*/

        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }

        $requiredGridFields = array_merge($GroupByFieldArr,[$havingTYValue]);
        $result = $this->redisCache->getRequiredData($result, $requiredGridFields, $havingTYValue, $havingTYValue, $havingTYValue);
        
        $tempResult = array();
        foreach ($result as $key => $data) {
            $sellingStoresIndexStr = "";
            foreach ($GLOBALS['sellingStoresGroupByPart'] as $account) {
                $sellingStoresIndexStr .='[$data[' . $account . ']]';
            }

            $sellingStoresCode = '$sellingStores = $GLOBALS[sellingStoresArray]' . $sellingStoresIndexStr . ';';
            eval($sellingStoresCode);
            $totalStoresIndexStr = "";
            foreach ($GLOBALS['totalStoresGroupByPart'] as $account) {
                $totalStoresIndexStr .='[$data[' . $account . ']]';
            }

            $totalStoresCode = '$totalStores = $GLOBALS[totalStoresArray]' . $totalStoresIndexStr . ';';
            eval($totalStoresCode);

            $distPct = $totalStores > 0 ? ($sellingStores / $totalStores) * 100 : 0;

            $temp = array();
            foreach ($gridAccountListToFetchQueryDataLater as $gridAccount) {
                $xmlTag = str_replace("'", "", str_replace(" ", "", $gridAccount));
                $temp[$xmlTag] = htmlspecialchars_decode($data[str_replace("'", "", $gridAccount)]);
            }

            $temp["SALES"] = $data[$havingTYValue];
            $temp["TTL_STORES"] = $totalStores; //
            $temp["SLNG_STORES"] = $sellingStores;
            $temp["DIST_PCT"] = number_format($distPct, 1, '.', '');
            $temp["GAP_VALUE"] = ($sellingStores > 0) ? number_format(($data[$havingTYValue] / $sellingStores) * ($totalStores - $sellingStores), 0, ',', '') : 0;
            
            if( $_REQUEST['hideZeroGapStores'] == "true" && ($totalStores - $sellingStores == 0) )
                continue;
            
            $tempResult[] = $temp;
            
        }
        $this->jsonOutput["gridData"] = $tempResult;
    }

    private function collectTotalStores() {
        $selectPart = array();
        $GLOBALS['totalStoresGroupByPart'] = array();
        
        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        
        foreach ($this->totalStoresAccounts as $key => $data) {
            if (key_exists('ID', $this->settingVars->dataArray[$data])) {
                $this->measureFields[] = $this->settingVars->dataArray[$data]['ID'];
                $selectPart[] = $this->settingVars->dataArray[$data]['ID'] . " AS '" . $this->settingVars->dataArray[$data]['ID_ALIASE'] . "'";
                $GLOBALS['totalStoresGroupByPart'][] = "'" . $this->settingVars->dataArray[$data]['ID_ALIASE'] . "'";
            } else {
                $this->measureFields[] = $this->settingVars->dataArray[$data]['NAME'];
                $selectPart[] = $this->settingVars->dataArray[$data]['NAME'] . " AS '" . $this->settingVars->dataArray[$data]['NAME_ALIASE'] . "'";
                $GLOBALS['totalStoresGroupByPart'][] = "'" . $this->settingVars->dataArray[$data]['NAME_ALIASE'] . "'";
            }
        }
        
        $this->measureFields[] = $this->sellingNotsellingID;
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        //$this->queryPart = $this->getAll();
        $this->getAll();
        $query = "SELECT " . implode(",", $selectPart);
        $query .= (count($selectPart) > 0 ? ",": "");
        $query .= "COUNT(DISTINCT((CASE WHEN " . $this->settingVars->ProjectVolume . ">0 THEN 1 END)*" . $this->sellingNotsellingID . ")) AS TOTAL_STORES FROM " . $this->settingVars->tablename . $this->queryPart . $this->timeRange . " ";
        $query .= (count($GLOBALS['totalStoresGroupByPart']) > 0 ? " GROUP BY ": "");
        $query .= implode(",", $GLOBALS['totalStoresGroupByPart']);

        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }

        $GLOBALS['totalStoresArray'] = array();
        foreach ($result as $key => $data) {
            $codeStr = "";
            $indexStr = "";
            foreach ($GLOBALS['totalStoresGroupByPart'] as $account) {
                $indexStr .='[$data[' . $account . ']]';
            }
            $codeStr = '$GLOBALS["totalStoresArray"]' . $indexStr . ' = $data["TOTAL_STORES"];';
            eval($codeStr);
        }
    }

    public function collectSellingStores() {
        $selectPart = array();
        $GLOBALS['sellingStoresGroupByPart'] = array();
        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        foreach ($this->sellingStoresAccounts as $key => $data) {
            if (key_exists('ID', $this->settingVars->dataArray[$data])) {
                $this->measureFields[] = $this->settingVars->dataArray[$data]['ID'];
                $selectPart[] = $this->settingVars->dataArray[$data]['ID'] . " AS '" . $this->settingVars->dataArray[$data]['ID_ALIASE'] . "'";
                $GLOBALS['sellingStoresGroupByPart'][] = "'" . $this->settingVars->dataArray[$data]['ID_ALIASE'] . "'";
            } else {
                $this->measureFields[] = $this->settingVars->dataArray[$data]['NAME'];
                $selectPart[] = $this->settingVars->dataArray[$data]['NAME'] . " AS '" . $this->settingVars->dataArray[$data]['NAME_ALIASE'] . "'";
                $GLOBALS['sellingStoresGroupByPart'][] = "'" . $this->settingVars->dataArray[$data]['NAME_ALIASE'] . "'";
            }
        }
        
        $this->measureFields[] = $this->sellingNotsellingID;
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->getAll();
        $query = "SELECT " . implode(",", $selectPart) .
                ",COUNT(DISTINCT((CASE WHEN " . $this->settingVars->ProjectVolume . ">0 THEN 1 END)*" . $this->sellingNotsellingID . ")) AS STORES " .
                "FROM " . $this->settingVars->tablename . $this->queryPart . $this->timeRange . " " .
                "GROUP BY " . implode(",", $GLOBALS['sellingStoresGroupByPart']);

        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }

        $GLOBALS['sellingStoresArray'] = array();
        foreach ($result as $key => $data) {
            $codeStr = "";
            $indexStr = "";
            foreach ($GLOBALS['sellingStoresGroupByPart'] as $account) {
                $indexStr .='[$data[' . $account . ']]';
            }
            $codeStr = '$GLOBALS["sellingStoresArray"]' . $indexStr . ' = $data["STORES"];';
            eval($codeStr);
        }
    }

    private function getAllSkusNonSellingStores() {
        $selectPartTotalSno = $orderByPart = $groupByPart = $selectPart = array();
        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $this->measureFields = $this->prepareTablesUsedForMeasure($_REQUEST['ValueVolume']);

        $csvHeader = array();
        
        foreach ($this->allExportAccountsName as $key => $data) {
            if (key_exists('ID', $this->settingVars->dataArray[$data])) {
                $this->measureFields[] = $this->settingVars->dataArray[$data]['ID'];
                $selectPartTotalSno[] = "MAX(" . $this->settingVars->dataArray[$data]['ID'] . ") AS '" . $this->settingVars->dataArray[$data]['ID_ALIASE'] . "'";
                $selectPart[] = $this->settingVars->dataArray[$data]['ID'] . " AS '" . $this->settingVars->dataArray[$data]['ID_ALIASE'] . "'";
                $groupByPart[] = "'" . $this->settingVars->dataArray[$data]['ID_ALIASE'] . "'";
                $orderByPart[] = "" . $this->settingVars->dataArray[$data]['ID_ALIASE'] . " DESC";
                
                $this->measureFields[] = $this->settingVars->dataArray[$data]['NAME'];
                $selectPartTotalSno[] = $selectPart[] = "MAX(" . $this->settingVars->dataArray[$data]['NAME'] . ") AS '" . $this->settingVars->dataArray[$data]['NAME_ALIASE'] . "'";
                if ($key == 0) {
                    $pinAlias = $this->settingVars->dataArray[$data]['ID_ALIASE'];
                    $pnameAlias = $this->settingVars->dataArray[$data]['NAME_ALIASE'];
                }
            } else {
                $this->measureFields[] = $this->settingVars->dataArray[$data]['NAME'];
                $selectPart[] = $selectPartTotalSno[] = "MAX(" . $this->settingVars->dataArray[$data]['NAME'] . ") AS '" . $this->settingVars->dataArray[$data]['NAME_ALIASE'] . "'";

                if ($key == 0) {
                    $pinAlias = $this->settingVars->dataArray[$data]['NAME_ALIASE'];
                    $pnameAlias = $this->settingVars->dataArray[$data]['NAME_ALIASE'];
                }
            }
            
            if(isset($this->settingVars->dataArray[$data]))
            {
                if(isset($this->settingVars->dataArray[$data]['ID_CSV']) && isset($this->settingVars->dataArray[$data]['NAME_CSV']))
                {
                    $csvHeader[] = $this->settingVars->dataArray[$data]['ID_CSV'];
                    $csvHeader[] = $this->settingVars->dataArray[$data]['NAME_CSV'];
                }
                else
                    $csvHeader[] = $this->settingVars->dataArray[$data]['NAME_CSV'];
            }
            
        }
        
        if(!empty($csvHeader))
            $csvHeader = implode(",", $csvHeader);
        
        $this->measureFields[] = $this->sellingNotsellingID;
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->getAll();
        //COLLECTING NOT SELLING STORES 
        $totalStoreQuery = "SELECT " . $this->sellingNotsellingID . " AS SNO1, " . implode(",", $selectPartTotalSno) . " FROM " . $this->settingVars->tablename . $this->queryPart .
                $this->timeRange . " GROUP BY SNO1 ORDER BY SNO1 DESC";
        $totalStores = $this->queryVars->queryHandler->runQuery($totalStoreQuery, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);

        $this->settingVars->tableUsedForQuery = array();
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->getAll();

        $options = array();
        if (!empty($this->timeRange))
            $options['tyLyRange']['SALES'] = "1=1";

        $measureSelect = config\MeasureConfigure::prepareSelect($this->settingVars, $this->queryVars, array('M'.$_REQUEST['ValueVolume']), $options);
        $measureSelect = implode(", ", $measureSelect);

        //COLLECTING SELLING STORES
        $query = "SELECT " . implode(",", $selectPart) . 
                ", " . $measureSelect . " " .
                "FROM " . $this->settingVars->tablename . $this->queryPart . $this->timeRange;

            if(is_array($groupByPart) && count($groupByPart)>0){
                $query.= " GROUP BY " . implode(",", $groupByPart) . " HAVING SALES > 0 "." ORDER BY " . implode(",", $orderByPart);
            }

        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);

        $pnameArray = $combineData = array();
        if (is_array($result) && !empty($result)) {
            foreach ($result as $k => $pinSno) {
                $combineData[$pinSno[$pinAlias]][] = $pinSno[$this->sellingNotsellingIDAlias];
                $pnameArray[$pinSno[$pinAlias]] = $pinSno[$pnameAlias];
            }
        }

        $finalNotSellingPinStores = array();
        $csv_output = "";
        if (is_array($combineData) && !empty($combineData)) {
            foreach ($combineData as $pinKey => $pinStores) {
                foreach ($totalStores as $storeDetail) {
                    if (!in_array($storeDetail[$this->sellingNotsellingIDAlias], $pinStores)) {
                        unset($storeDetail['SNO1']);
                        $data = $storeDetail;
                        $data[$pinAlias] = $pinKey;
                        $data[$pnameAlias] = $pnameArray[$pinKey];

                        if (empty($csv_output)) {
                            //$csvHeaderForItems = implode(",", array_keys($data));
                            $csvHeaderForItems = $csvHeader;
                            $csv_output .= $csvHeaderForItems . "\n";
                        }

                        $csv_output.= implode(",", array_values($data)) . "\r\n";
                    }
                }
            }
        }
        
        $this->exportAsCSV($csv_output);
    }

    private function exportAsCSV($csvData) {
        $basename = basename(getcwd());
        $this->path = getcwd() . "/uploads/zip/";
        $value = array();
        $item = array();
        $date = date('Y_m_j_H\hi');

        /*         * *************** SCAN FOLDER ******************** */
        $folderToScan = scandir($this->path);
        $files = array_values(array_diff($folderToScan, array('.', '..')));
        $dt = date("Y_m_j");
        foreach ($files as $file) {
            $fileExist = strpos($file, $dt);
            if ($fileExist === false) {
                unlink($this->path . $file);
            }
        }

        /*         * *************** CREATING CSV FILE ******************** */
        $csv_filename = $date . ".csv";
        $fd = fopen($this->path . $csv_filename, "w");
        fputs($fd, $csvData);
        fclose($fd);

        chdir($this->path);

        /*         * ****************CREATING ZIP FILE********************* */
        $zip = new \ZipArchive;
        $zip->open($this->path . $date . '.zip', \ZipArchive::CREATE);
        $zip->addFile($date . ".csv");
        $zip->close();

        $fullPath = "/" . $basename . "/uploads/zip/" . $date . ".zip";

        $this->jsonOutput["allNotSellingStores"] = array('fullPath' => $fullPath);
    }

    private function getSellingStores() {

        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $this->measureFields = $this->prepareTablesUsedForMeasure($_REQUEST['ValueVolume']);
        
        $qpart = $this->getSellingNotSellingQueryPart($this->sellingStoresFields, $this->sellingStoresAccounts);
        
        $extraSelectPart = $extraGroupByPart = array();
        if(is_array($this->bottomGridAccounts) && !empty($this->bottomGridAccounts))
        {
            foreach($this->bottomGridAccounts as $data)
            {
                $this->measureFields[] = $data;
                $extraSelectPart[] = " MAX($data) as ".$this->settingVars->dataArray[$data]['NAME_ALIASE']." ";
                $extraGroupByPart[] = $this->settingVars->dataArray[$data]['NAME_ALIASE'];
            }
        }
        
        $this->measureFields[] = $this->sellingNotsellingID;
        $this->measureFields[] = $this->sellingNotsellingName;
        if(!empty($this->stockQtyField)){
            $this->measureFields[] = $this->skuAccountIDField;
        }
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->getAll();
        
        if ($this->settingVars->calculateVsiOhq || !empty($this->stockQtyField)) {
            $query = "SELECT MAX(".$this->settingVars->dateField.") as latestPeriod FROM ".$this->settingVars->tablename . $this->queryPart . $qpart . $this->timeRange;
            $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
            if ($redisOutput === false) {
                $latestPeriod = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
                $this->redisCache->setDataForHash($latestPeriod);
            } else {
                $latestPeriod = $redisOutput;
            }
        }

        $options = array();
        if (!empty($this->timeRange))
            $options['tyLyRange']['SALES'] = "1=1";

        $measureSelect = config\MeasureConfigure::prepareSelect($this->settingVars, $this->queryVars, array('M'.$_REQUEST['ValueVolume']), $options);
        $measureSelect = implode(", ", $measureSelect);

        //COLLECTING SELLING STORES
        $query = "SELECT ".$this->sellingNotsellingID . " AS SNO" .
                ",MAX(".$this->sellingNotsellingName . ") AS NAME".
                ", ".$measureSelect." ";
                
        if(!empty($extraSelectPart))
            $query .= ",".implode(",", $extraSelectPart);
                
        if($this->settingVars->calculateVsiOhq){
            $paramsName = explode(".",$this->pinField);
            $query .= ",MAX(CASE WHEN " . $this->settingVars->maintable . ".VSI = 1 AND ".$this->settingVars->dateField."=".$latestPeriod[0][       'latestPeriod']." THEN 'YES' ELSE 'NO' END) AS validStore " .
            ",SUM((CASE WHEN ".$this->pinField." = ".$_REQUEST[$paramsName[1]]." AND ".$this->settingVars->dateField."=".$latestPeriod[0]['latestPeriod']." THEN 1 ELSE 0 END)*".$this->settingVars->maintable.".OHQ) AS stock ";
        }

        /*[START] STOCK QTY RELATED CHANGES */
        if(!empty($this->stockQtyField)){
            $paramsName = explode(".",$this->pinField);
            $query .= " ,SUM((CASE WHEN ".$this->skuAccountIDField." = ".$_REQUEST[$paramsName[1]]." AND ".$this->settingVars->dateField."='".$latestPeriod[0]['latestPeriod']."' THEN 1 ELSE 0 END) * ".$this->stockQtyAccountField.") AS STOCK_QTY ";
        }
        /*[END] STOCK QTY RELATED CHANGES */
        $query .= " FROM " . $this->settingVars->tablename . $this->queryPart . $this->timeRange . $qpart .
                 " GROUP BY SNO HAVING SALES > 0 ORDER BY SALES DESC";

        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }

        $this->sellingStores = array();
        for ($i = 0; $i < count($result); $i++) {
            $data = $result[$i];
            array_push($this->sellingStores, $data['SNO']);
            $temp = array();
            $temp["SNO"] = $data['SNO'];
            if(isset($this->pinField) && !empty($this->pinField)) {
                $paramsName = explode(".",$this->pinField);
                if(isset($_REQUEST[$paramsName[1]]) && !empty($_REQUEST[$paramsName[1]])) {
                    $temp["PIN"] = $_REQUEST[$paramsName[1]];   
                }
            } 
            $temp["SNAME"] = htmlspecialchars_decode($data['NAME']);
            $temp["SALES"] = $data['SALES'];
            if($this->settingVars->calculateVsiOhq){
                $temp["ValidStore"] = $data['validStore'];
                $temp["stock"] = $data['stock'];
            }else if(!empty($this->stockQtyField)) {
                $temp["stock"] = $data['STOCK_QTY'];
            }

            foreach($extraGroupByPart as $extra)
                $temp[$extra] = $data[$extra];
            
            $this->jsonOutput["sellingStores"][] = $temp;
        }
    }

    private function getNotSellingStores() {

        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $this->measureFields = $this->prepareTablesUsedForMeasure($_REQUEST['ValueVolume']);

        $qpart = $this->getSellingNotSellingQueryPart($this->totalStoresFields, $this->totalStoresAccounts);
        
        $extraSelectPart = $extraGroupByPart = array();
        if(is_array($this->bottomGridAccounts) && !empty($this->bottomGridAccounts))
        {
            foreach($this->bottomGridAccounts as $data)
            {
                $this->measureFields[] = $data;
                $extraSelectPart[] = " MAX($data) as ".$this->settingVars->dataArray[$data]['NAME_ALIASE']." ";
                $extraGroupByPart[] = $this->settingVars->dataArray[$data]['NAME_ALIASE'];
            }
        }        
        
        $this->measureFields[] = $this->sellingNotsellingID;
        $this->measureFields[] = $this->sellingNotsellingName;
        
        if($this->settingVars->calculateVsiOhq)
            $this->measureFields[] = $this->pinField;
            
        if(!empty($this->stockQtyField)){
            $this->measureFields[] = $this->skuAccountIDField;
        }
        
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->getAll();
        
        if($this->settingVars->calculateVsiOhq || !empty($this->stockQtyField))
        {
            $query = "SELECT MAX(".$this->settingVars->dateField.") as latestPeriod FROM ".$this->settingVars->tablename . $this->queryPart . $qpart . $this->timeRange;
            $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
            if ($redisOutput === false) {
                $latestPeriod = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
                $this->redisCache->setDataForHash($latestPeriod);
            } else {
                $latestPeriod = $redisOutput;
            }
        }
        $notSellingStores = array();

        $options = array();
        if (!empty($this->timeRange))
            $options['tyLyRange']['SALES_LW'] = " 1=1 ";

        $measureSelect = config\MeasureConfigure::prepareSelect($this->settingVars, $this->queryVars, array('M'.$_REQUEST['ValueVolume']), $options);
        $measureSelect = implode(", ", $measureSelect);

        //COLLECTING NOT SELLING STORES 
        $query = "SELECT " . $this->sellingNotsellingID . " AS SNO" .
                ",MAX(" . $this->sellingNotsellingName . ") AS NAME " .
                ", " . $measureSelect . " ".
                ",if (SUM(" . $this->settingVars->ProjectVolume . ")>0,'YES','NO') AS ISactive ";
                
        if(!empty($extraSelectPart))
            $query .= ",".implode(",", $extraSelectPart);
                
        if($this->settingVars->calculateVsiOhq){
            $paramsName = explode(".",$this->pinField);
            $query .= ",MAX(CASE WHEN ".$this->pinField." = ".$_REQUEST[$paramsName[1]]." AND " . $this->settingVars->maintable . ".VSI = 1 AND ".$this->settingVars->dateField."=".$latestPeriod[0]['latestPeriod']." THEN 'YES' ELSE 'NO' END) AS validStore " .
            ",SUM((CASE WHEN ".$this->pinField." = ".$_REQUEST[$paramsName[1]]." AND ".$this->settingVars->dateField."=".$latestPeriod[0]['latestPeriod']." THEN 1 ELSE 0 END)*".$this->settingVars->maintable.".OHQ) AS stock ";
        }

        /*[START] STOCK QTY RELATED CHANGES */
        if(!empty($this->stockQtyField)) {
            $paramsName = explode(".",$this->pinField);
            $query .= " ,SUM((CASE WHEN ".$this->skuAccountIDField." = ".$_REQUEST[$paramsName[1]]." AND ".$this->settingVars->dateField."='".$latestPeriod[0]['latestPeriod']."' THEN 1 ELSE 0 END) * ".$this->stockQtyAccountField.") AS STOCK_QTY ";
        }
        /*[END] STOCK QTY RELATED CHANGES */

        $query .= "FROM " . $this->settingVars->tablename . $this->queryPart . $qpart . $this->timeRange;
        
        if (is_array($this->sellingStores) && !empty($this->sellingStores))
            $query .= " AND " . $this->sellingNotsellingID . " NOT IN ('" . implode("','", $this->sellingStores) . "') ";

        $query .= "GROUP BY SNO ORDER BY SALES_LW DESC";
        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $notSellingData = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($notSellingData);
        } else {
            $notSellingData = $redisOutput;
        }

        foreach ($notSellingData as $key => $data) {
            $notSellingStores[] = $data['SNO'];
        }

        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $qpart = $this->getSellingNotSellingQueryPart($this->sellingStoresFields, $this->sellingStoresAccounts);
        $this->measureFields[] = $this->sellingNotsellingID;
        $this->measureFields[] = $this->settingVars->dateField;
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->getAll();
        $dateLastSold = array();
        if (count($notSellingStores) > 0) {
            //COLLECTING DATES_LAST_SOLD : the latest mydate where sumValue>0 for that pin/SNO
            $query = "SELECT " . $this->sellingNotsellingID . " AS SNO" .
                    ",DATE_FORMAT(MAX(" . $this->settingVars->getMydateSelect($this->settingVars->dateField, false) . ") , '%d %b %Y') AS DATE_LAST_SOLD " .
                    "FROM " . $this->settingVars->tablename . $this->queryPart . $qpart . " " .
                    "AND " . $this->sellingNotsellingID . " IN (" . implode(",", $notSellingStores) . ") " .
                    "AND " . $this->settingVars->ProjectVolume . ">0 " .
                    "GROUP BY SNO ";
            $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
            if ($redisOutput === false) {
                $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
                $this->redisCache->setDataForHash($result);
            } else {
                $result = $redisOutput;
            }

            foreach ($result as $key => $data) {
                $dateLastSold[$data['SNO']] = $data['DATE_LAST_SOLD'];
            }
        }
        
        foreach ($notSellingData as $key => $data) {
            if ($data['SALES_LW'] <= 0)
                continue;

            $temp = array();
            $temp["SNO"] = $data['SNO'];
            if(isset($this->pinField) && !empty($this->pinField)) {
                $paramsName = explode(".",$this->pinField);
                if(isset($_REQUEST[$paramsName[1]]) && !empty($_REQUEST[$paramsName[1]])) {
                    $temp["PIN"] = $_REQUEST[$paramsName[1]];   
                }
            }
            $temp["SNAME"] = htmlspecialchars_decode($data['NAME']);
            $temp["ACTIVE"] = $data['ISactive'];
            $temp["SALES_LW"] = $data['SALES_LW'];
            $temp["DATE_LAST_SOLD"] = $dateLastSold[$data['SNO']];
            if($this->settingVars->calculateVsiOhq){
                $temp["ValidStore"] = $data['validStore'];
                $temp["stock"] = $data['stock'];
            }else if(!empty($this->stockQtyField)) {
                $temp["stock"] = $data['STOCK_QTY'];
            }

            foreach($extraGroupByPart as $extra)
                $temp[$extra] = $data[$extra];
            $this->jsonOutput["notSellingStores"][] = $temp;
        }
    }

    public function makeFieldsToAccounts($srcArray) {
        $tempArr = array();
        foreach ($srcArray as $value) {
            $value = explode("#", $value);
            if (count($value) > 1)
                $tempArr[] = strtoupper($this->dbColumnsArray[$value[0]] . "_" . $this->dbColumnsArray[$value[1]]);
            else
                $tempArr[] = strtoupper($this->dbColumnsArray[$value[0]]);
        }
        return $tempArr;
    }

    public function buildPageArray() {

        $fetchConfig = false;
        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
            $fetchConfig = true;
            $this->jsonOutput['pageConfig'] = array(
                'enabledFilters' => $this->getPageConfiguration('filter_settings', $this->settingVars->pageID)
            );
        }

        $sellingNotsellingFieldPart = explode("#", $this->sellingNotsellingField);
        $sellingNotsellingField = strtoupper($this->dbColumnsArray[$sellingNotsellingFieldPart[0]]);
        $sellingNotsellingField = (count($sellingNotsellingFieldPart) > 1) ? strtoupper($sellingNotsellingField . "_" . $this->dbColumnsArray[$sellingNotsellingFieldPart[1]]) : $sellingNotsellingField;

        $this->sellingNotsellingID = (isset($this->settingVars->dataArray[$sellingNotsellingField]) && isset($this->settingVars->dataArray[$sellingNotsellingField]['ID'])) ? $this->settingVars->dataArray[$sellingNotsellingField]['ID'] : $this->settingVars->dataArray[$sellingNotsellingField]['NAME'];
        $this->sellingNotsellingIDAlias = (isset($this->settingVars->dataArray[$sellingNotsellingField]) && isset($this->settingVars->dataArray[$sellingNotsellingField]['ID'])) ? $this->settingVars->dataArray[$sellingNotsellingField]['ID_ALIASE'] : $this->settingVars->dataArray[$sellingNotsellingField]['NAME_ALIASE'];
        $this->sellingNotsellingName = $this->settingVars->dataArray[$sellingNotsellingField]['NAME'];
        $this->sellingNotsellingNameAlias = $this->settingVars->dataArray[$sellingNotsellingField]['NAME_ALIASE'];

        $this->accountsName = $this->makeFieldsToAccounts($this->accountFields);
        $this->allExportAccountsName = $this->makeFieldsToAccounts($this->allExportAccountFields);
        $this->totalStoresAccounts = $this->makeFieldsToAccounts($this->totalStoresFields);
        $this->sellingStoresAccounts = $this->makeFieldsToAccounts($this->sellingStoresFields);
        $this->bottomGridAccounts = $this->makeFieldsToAccounts($this->bottomGridAccounts);

        $this->skuAccountIDField = $this->settingVars->dataArray[strtoupper($this->dbColumnsArray[$this->pinField])]["NAME"];
        if(!empty($this->stockQtyField)) {
            $this->stockQtyAccountField = $this->settingVars->dataArray[strtoupper($this->dbColumnsArray[$this->stockQtyField])]["NAME"];
        }
        return;
    }

    public function buildDataArray($fields) {
        if (empty($fields))
            return false;

        $configurationCheck = new config\ConfigurationCheck($this->settingVars, $this->queryVars);
        $configurationCheck->buildDataArray($fields);
        $this->dbColumnsArray = $configurationCheck->dbColumnsArray;
        $this->displayCsvNameArray = $configurationCheck->displayCsvNameArray;
        $this->displayDbColumnArray = $configurationCheck->displayDbColumnArray;
        $this->requestCsvNameArray = $configurationCheck->requestCsvNameArray;
        return;
    }

    public function getAll() {
        $this->queryPart = parent::getAll();
        $this->timeRange = "AND CONCAT(" . $this->settingVars->yearperiod . "," . $this->settingVars->weekperiod . ") IN (" . implode(",", filters\timeFilter::getYearWeekWithinRange(0, $this->timeFrame, $this->settingVars)) . ") ";
    }

    public function getSellingNotSellingQueryPart($fields, $accounts) {
        $qpart = "";

        foreach ($fields as $key => $requestKey) {
            $requestVar = $this->requestCsvNameArray[$requestKey];
            if (isset($_REQUEST[$requestVar]) && $_REQUEST[$requestVar] != "") {
                $dataArrRequestKey = $accounts[$key];
                $searchdata = htmlspecialchars_decode(urldecode($_REQUEST[$requestVar]));
                $qpart.= " AND " . $this->settingVars->dataArray[$dataArrRequestKey]["NAME"] . " = '" . $searchdata . "' ";
                $this->measureFields[] = $this->settingVars->dataArray[$dataArrRequestKey]["NAME"];
            }
        }
        return $qpart;
    }

}

?> 