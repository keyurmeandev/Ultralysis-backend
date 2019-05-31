<?php

namespace classes\lcl;

use projectsettings;
use datahelper;
use filters;
use db;
use config;
use utils;

class DistributionGapSpecifier extends config\UlConfig {

    public $timeRange;
    public $timeFrame;
    public $dbColumnsArray;
    public $displayCsvNameArray;
    public $displayDbColumnArray;

    /*     * ***
     * Default gateway function, should be similar in all DATA CLASS
     * arguments:
     *  $settingVars [project settingsGateway variables]
     * @return $jsonOutput with all data that should be sent to client app
     * *** */

    public function go($settingVars) {
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES

        $this->settingVars->pageName = (empty($this->settingVars->pageName)) ? $this->settingVars->pageID . '_DistributionGapSpecifierPage' : $this->settingVars->pageName;
        $this->ValueVolume = getValueVolume($this->settingVars);

        if ($this->settingVars->isDynamicPage) {
            $this->skuField = $this->getPageConfiguration('sku_field', $this->settingVars->pageID)[0];
            $this->storeField = $this->getPageConfiguration('store_field', $this->settingVars->pageID)[0];
            $this->buildDataArray(array($this->skuField, $this->storeField));
            $this->buildPageArray();
        } else {
            if (!isset($this->settingVars->pageArray[$this->settingVars->pageName]) || empty($this->settingVars->pageArray[$this->settingVars->pageName]))
                $this->configurationFailureMessage();
        }

        if (isset($_REQUEST["timeFrame"]) && !empty($_REQUEST["timeFrame"])) {
            $this->timeFrame = $_REQUEST["timeFrame"];
        } else {
            $this->timeFrame = 12;
        }

        $this->getAll();
        $action = $_REQUEST["action"];

        switch ($action) {
            case "getSKUData":                
                $this->getSKUData();
                $this->reload();
                break;
            case "reload":                
                $this->reload();
                break;
        }
        return $this->jsonOutput;
    }

    public function reload() {
        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
            $this->getGridColumns();
            $this->getSKUData();
        }else{
            $this->getGridData(); //ADDING TO OUTPUT        
        }
    }

    public function getGridColumns() {
        $storeFieldPart = explode("#", $this->storeField);
        $this->jsonOutput["gridColumns"]['SNO'] = (count($storeFieldPart) > 1) ? $this->displayCsvNameArray[$storeFieldPart[1]] : $this->displayCsvNameArray[$storeFieldPart[0]];
        $this->jsonOutput["gridColumns"]['Store'] = $this->displayCsvNameArray[$storeFieldPart[0]];
    }

    public function getSKUData(){
        $skuData = array();

        $this->settingVars->tableUsedForQuery = $this->measureFields = array();

        $this->measureFields[] = $this->skuID;
        $this->measureFields[] = $this->skuName;
        
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }

        $this->getAll();

        $query = "SELECT ".
                $this->skuID . " AS skuID, " . 
                " MAX(".$this->skuName . ") AS skuName, " . 
                " SUM((CASE WHEN CONCAT(" . $this->settingVars->yearperiod . "," . $this->settingVars->weekperiod . ") IN (" . implode(",", filters\timeFilter::getYearWeekWithinRange(0, $this->timeFrame, $this->settingVars)) . ") THEN 1 ELSE 0 END) * " .$this->settingVars->ProjectVolume.") AS SumQTY ".
                " FROM " . $this->settingVars->tablename . $this->queryPart . $this->timeRange .
                " GROUP BY skuID " .
                " HAVING SumQTY > 0 ".
                " ORDER BY skuName ";    

        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);

        if(is_array($result) && !empty($result))
        {
            foreach ($result as $key => $data) {
                $temp = array();
                $temp['data'] = $data['skuID'];
                $temp['label'] = ($data['skuName'] == $data['skuID'] ) ? $data['skuName'] : $data['skuName'] ." ( " .$data['skuID'] . " ) " ;
                $skuData[$key] = $temp;
            }
        }

        $this->jsonOutput["skuData"] = $skuData;


    }

    public function getGridData() {
        $finalArray = array();
        $selectPart = array();

        if( !empty($_REQUEST['SKU_1']) && !empty($_REQUEST['SKU_2']) )
        {   

            $this->measureFields = $this->prepareTablesUsedForMeasure($_REQUEST['ValueVolume']);

            $this->measureFields[] = $this->skuID;
            $this->measureFields[] = $this->skuName;
            $this->measureFields[] = $this->storeID;
            $this->measureFields[] = $this->storeName;
            
            $this->settingVars->useRequiredTablesOnly = true;
            if (is_array($this->measureFields) && !empty($this->measureFields)) {
                $this->prepareTablesUsedForQuery($this->measureFields);
            }

            $this->getAll();

            $options = array();
            if (!empty($this->timeRange)){
                $options['tyLyRange']['SALES_1'] = $this->skuID."='" . $_REQUEST['SKU_1'] . "' ";
                $options['tyLyRange']['SALES_2'] = $this->skuID."='" . $_REQUEST['SKU_2'] . "' ";
            }


            $measureSelect = config\MeasureConfigure::prepareSelect($this->settingVars, $this->queryVars, array('M'.$_REQUEST['ValueVolume']), $options);
            $measureSelect = implode(", ", $measureSelect);

            $query = "SELECT ".$this->storeID . " AS SNO, " . 
                    "MAX(".$this->storeName . ") AS Store, " . 
                    " ".$measureSelect." ".
                    //" SUM((CASE WHEN ".$this->skuID."='" . $_REQUEST['SKU_1'] . "' THEN 1 ELSE 0 END) * " . $this->ValueVolume . " ) AS SALES_1, ".
                    //" SUM((CASE WHEN ".$this->skuID."='" . $_REQUEST['SKU_2'] . "' THEN 1 ELSE 0 END) * " . $this->ValueVolume . " ) AS SALES_2 ".
                    " FROM " . $this->settingVars->tablename . $this->queryPart . $this->timeRange . " " .
                    " AND ".$this->skuID. " IN ( '".$_REQUEST['SKU_1']."', '".$_REQUEST['SKU_2']."' ) ".
                    " GROUP BY SNO ".
                    " HAVING SALES_1 > 0 OR SALES_2 > 0 ";

            //echo $query;exit();
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);

            if(is_array($result) && !empty($result))
            {
                //SORT DATA ARRAY ON SKU 1
                $result = utils\SortUtility::sort2DArray($result, 'SALES_1', utils\SortTypes::$SORT_DESCENDING);

                foreach ($result as $key => $data) {

                    
                        $keyIndex                   = $data['SNO'];

                        
                        $finalArray[$keyIndex]['SNO']                       = $data['SNO'];
                        $finalArray[$keyIndex]['Store']                     = $data['Store'];
                        $finalArray[$keyIndex]['SKU_RANK_1']                = $key + 1;
                        $finalArray[$keyIndex]['SKU_SALES_1']               = $data['SALES_1'];
                    

                }

                //SORT DATA ARRAY ON SKU 2
                $result = utils\SortUtility::sort2DArray($result, 'SALES_2', utils\SortTypes::$SORT_DESCENDING);

                foreach ($result as $key => $data) {

                    $keyIndex                   = $data['SNO'];

                    
                    $finalArray[$keyIndex]['SNO']                       = $data['SNO'];
                    $finalArray[$keyIndex]['Store']                     = $data['Store'];
                    $finalArray[$keyIndex]['SKU_RANK_2']                = $key + 1;
                    $finalArray[$keyIndex]['SKU_SALES_2']               = $data['SALES_2'];

                }
            }
            

            $finalArray = utils\SortUtility::sort2DArray($finalArray, 'SKU_RANK_1', utils\SortTypes::$SORT_ASCENDING);
        }

        $this->jsonOutput["gridData"] = $finalArray;
    }

    public function buildPageArray() {

        $fetchConfig = false;
        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
            $fetchConfig = true;
            $this->jsonOutput['pageConfig'] = array(
                'enabledFilters' => $this->getPageConfiguration('filter_settings', $this->settingVars->pageID)
            );
        }

        $skuFieldPart = explode("#", $this->skuField);
        $skuField = strtoupper($this->dbColumnsArray[$skuFieldPart[0]]);
        $skuField = (count($skuFieldPart) > 1) ? strtoupper($skuField."_".$this->dbColumnsArray[$skuFieldPart[1]]) : $skuField;

        $this->skuID = (isset($this->settingVars->dataArray[$skuField]) && isset($this->settingVars->dataArray[$skuField]['ID'])) ? $this->settingVars->dataArray[$skuField]['ID'] : $this->settingVars->dataArray[$skuField]['NAME'];
        $this->skuName = $this->settingVars->dataArray[$skuField]['NAME'];
        
        $storeFieldPart = explode("#", $this->storeField);
        $storeField = strtoupper($this->dbColumnsArray[$storeFieldPart[0]]);
        $storeField = (count($storeFieldPart) > 1) ? strtoupper($storeField."_".$this->dbColumnsArray[$storeFieldPart[1]]) : $storeField;

        $this->storeID = (isset($this->settingVars->dataArray[$storeField]) && isset($this->settingVars->dataArray[$storeField]['ID'])) ? $this->settingVars->dataArray[$storeField]['ID'] : $this->settingVars->dataArray[$storeField]['NAME'];
        $this->storeName = $this->settingVars->dataArray[$storeField]['NAME']; 

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


}

?> 