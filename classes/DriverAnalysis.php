<?php

namespace classes;

use db;
use filters;
use config;
use utils;

class DriverAnalysis extends config\UlConfig {

    private $pageName;
    private $skuID;
    private $skuName;
    private $storeID;
    private $storeName;
    private $displayCsvNameArray;
    private $dbColumnsArray;
    private $percentValue;

    public function go($settingVars) {
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES

        $this->settingVars->pageName = (empty($this->settingVars->pageName)) ? $this->settingVars->pageID . "_GrowthDeclineDriverPage" : $this->settingVars->pageName;

        $this->redisCache = new utils\RedisCache($this->queryVars);
        
        if ($this->settingVars->isDynamicPage) {
            $this->skuField = $this->getPageConfiguration('sku_field', $this->settingVars->pageID)[0];
            $this->storeField = $this->getPageConfiguration('store_field', $this->settingVars->pageID)[0];
            $this->buildDataArray(array($this->skuField, $this->storeField));
            $this->buildPageArray();
        } else {
            if (!isset($this->settingVars->pageArray[$this->settingVars->pageName]) || empty($this->settingVars->pageArray[$this->settingVars->pageName]))
                $this->configurationFailureMessage();

            $this->skuID = $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['SKU_FIELD']]['ID'];
            $this->skuName = $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['SKU_FIELD']]['NAME'];
            $this->storeID = $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['STORE_FIELD']]['ID'];
            $this->storeName = $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['STORE_FIELD']]['NAME'];
        }

        $this->queryPart = $this->getAll();

        $action = $_REQUEST["action"];
        switch ($action) {
            case "reload":
                $this->Reload();
                break;
            case "ChangeSku":
                $this->ChangeSKU();
                break;
            default:
                $this->defaultLoad();
                
        }
        return $this->jsonOutput;
    }
    
    public function defaultLoad(){
        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
            $skuFieldPart = explode("#", $this->skuField);
            $this->jsonOutput['gridConfig'] = array(
                'enabledFilters' => $this->getPageConfiguration('filter_settings', $this->settingVars->pageID),
                'ACCOUNT_NAME' => $this->displayCsvNameArray[$skuFieldPart[0]]
            );
        }
    }

    public function buildDataArray($gridFields) {
        if (empty($gridFields))
            return false;

        $configurationCheck = new config\ConfigurationCheck($this->settingVars, $this->queryVars);
        $configurationCheck->buildDataArray($gridFields);
        $this->dbColumnsArray = $configurationCheck->dbColumnsArray;
        $this->displayCsvNameArray = $configurationCheck->displayCsvNameArray;
        return;
    }

    public function buildPageArray() {

        $skuFieldPart = explode("#", $this->skuField);
        $skuField = strtoupper($this->dbColumnsArray[$skuFieldPart[0]]);
        $skuField = (count($skuFieldPart) > 1) ? strtoupper($skuField . "_" . $this->dbColumnsArray[$skuFieldPart[1]]) : $skuField;

        $storeFieldPart = explode("#", $this->storeField);
        $storeField = strtoupper($this->dbColumnsArray[$storeFieldPart[0]]);
        $storeField = (count($storeFieldPart) > 1) ? strtoupper($storeField . "_" . $this->dbColumnsArray[$storeFieldPart[1]]) : $storeField;

        $this->skuID = (isset($this->settingVars->dataArray[$skuField]) && isset($this->settingVars->dataArray[$skuField]['ID'])) ? $this->settingVars->dataArray[$skuField]['ID'] : $this->settingVars->dataArray[$skuField]['NAME'];
        $this->skuName = $this->settingVars->dataArray[$skuField]['NAME'];
        $this->storeID = (isset($this->settingVars->dataArray[$storeField]) && isset($this->settingVars->dataArray[$storeField]['ID'])) ? $this->settingVars->dataArray[$storeField]['ID'] : $this->settingVars->dataArray[$storeField]['NAME'];
        $this->storeName = $this->settingVars->dataArray[$storeField]['NAME'];
        return;
    }

    private function Reload() {
        $this->GridSKU();
    }

    private function ChangeSKU() {
        $this->Chart();
    }

    private function GridSKU() {
        
        $this->measureFields = $this->settingVars->tableUsedForQuery = array();
        $this->measureFields[] = $this->skuID;
        $this->measureFields[] = $this->skuName;
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->queryPart = $this->getAll();
        
        $query = "SELECT " . ($this->skuID!='' ? $this->skuID." AS ID, " : "" ) . $this->skuName . " AS ACCOUNT " .
                ",SUM( (CASE WHEN " . filters\timeFilter::$tyWeekRange . " THEN 1 ELSE 0 END) * " . $this->settingVars->ProjectValue . ") AS MCOST" .
                ",SUM( (CASE WHEN " . filters\timeFilter::$lyWeekRange . " THEN 1 ELSE 0 END) * " . $this->settingVars->ProjectValue . ") AS PCOST" .
                " FROM " . $this->settingVars->tablename . $this->queryPart . " " .
                "GROUP BY ".($this->skuID!='' ? " ID, " : "" )." ACCOUNT";
        
        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);

        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }
        
        $fields = $this->redisCache->getRequiredFieldsArray(array("ID", "ACCOUNT", "MCOST", "PCOST"), false, $this->settingVars->measureArray);
        $result = $this->redisCache->getRequiredData($result, $fields, "MCOST");

        $tyTotal = array_sum(array_column($result, "MCOST"));
        $lyTotal = array_sum(array_column($result, "PCOST"));
        
        $this->percentValue = $lyTotal > 0 ? number_format(((($tyTotal - $lyTotal) / $lyTotal) * 100), 1, '.', ',') : 0;
        $temp = array();
        $temp['value'] = $this->percentValue;
        $this->jsonOutput['avgpercent'] = $temp;
        
        foreach ($result as $key => $data) {
            if ($data["MCOST"] != 0 || $data["PCOST"] != 0) {
                $percent = $data["PCOST"] > 0 ? number_format(((($data["MCOST"] - $data["PCOST"]) / $data["PCOST"]) * 100), 1, '.', ',') : 0;
                if ($percent != 0) {
                    if (($percent - $this->percentValue) >= 5)
                        $tagName = "gridSKU1";
                    else if (($this->percentValue - $percent) >= 5)
                        $tagName = "gridSKU3";
                    else
                        $tagName = "gridSKU2";
                } else
                    $tagName = "gridSKU1";

                $temp = array();

                if ($this->settingVars->isDynamicPage) {
                    $temp['DATA'] = $data['ID'];
                    $temp['ACCOUNT'] = htmlspecialchars_decode($data['ACCOUNT']);
                } else {
                    $temp['SNO'] = $data['DATA'];
                    $temp['SNAME'] = htmlspecialchars_decode($data['ACCOUNT']);
                }
                $temp['TYEAR'] = $data["MCOST"];
                $temp['LYEAR'] = $data["PCOST"];
                $temp['PERCENT'] = $percent;
                $this->jsonOutput[$tagName][] = $temp;
            }
        }
    }

    private function Chart() {
    
        $this->measureFields = $this->settingVars->tableUsedForQuery = array();
        $this->measureFields[] = $this->storeID;
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->queryPart = $this->getAll();
        
        $query = "SELECT COUNT( DISTINCT( CASE WHEN ".filters\timeFilter::$tyWeekRange." AND ".$this->settingVars->ProjectVolume . ">0 THEN ".$this->storeID." END )) AS TYSTORE, " .
                "COUNT( DISTINCT( CASE WHEN ".filters\timeFilter::$lyWeekRange." AND ".$this->settingVars->ProjectVolume . ">0 THEN ".$this->storeID." END )) AS LYSTORE, " .
                "SUM( (CASE WHEN " .filters\timeFilter::$tyWeekRange. " THEN 1 ELSE 0 END) * " . $this->settingVars->ProjectVolume . ") AS TYEAR, " .
                "SUM( (CASE WHEN " .filters\timeFilter::$lyWeekRange. " THEN 1 ELSE 0 END) * " . $this->settingVars->ProjectVolume . ") AS LYEAR " .
                "FROM " . $this->settingVars->tablename . $this->queryPart . 
                " AND (" . filters\timeFilter::$tyWeekRange . " OR " . filters\timeFilter::$lyWeekRange . ") ";
        
        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);

        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }
        
        if(is_array($result) && !empty($result))
        {
            /* Chart1 */
            $this->jsonOutput['disChart'][] = array("account" => "LAST YEAR", "LYEAR" => (int)$result[0]['LYSTORE']);
            $this->jsonOutput['disChart'][] = array("account" => "THIS YEAR", "TYEAR" => (int)$result[0]['TYSTORE']);
            
            $percent = $result[0]['LYSTORE'] > 0 ? number_format(((($result[0]['TYSTORE'] - $result[0]['LYSTORE']) / $result[0]['LYSTORE']) * 100), 1) : 0;
            $this->jsonOutput['disChartPercent'] = array("percent" => $percent);
            
            /* Chart2 */
            $this->jsonOutput['salesChart'][] = array("account" => "LAST YEAR", "LYEAR" => $result[0]['LYEAR']);
            $this->jsonOutput['salesChart'][] = array("account" => "THIS YEAR", "TYEAR" => $result[0]['TYEAR']);
            $percent = $result[0]['LYEAR'] > 0 ? number_format(((($result[0]['TYEAR'] - $result[0]['LYEAR']) / $result[0]['LYEAR']) * 100), 1) : 0;
            $this->jsonOutput['salesChartPercent'] = array("percent" => $percent);
        }
    }

    /*     * * OVERRIDING PARENT CLASS'S getAll FUNCTION ** */

    public function getAll() {
        // $tablejoins_and_filters = parent::getAll();
        $tablejoins_and_filters = "";

        if ($_REQUEST["SKU"] != "") {
            $extraFields[] = $this->skuID;
            $tablejoins_and_filters .=" AND " . $this->skuID . "='" . $_REQUEST['SKU'] . "'";
        }

        $this->prepareTablesUsedForQuery($extraFields);
        $tablejoins_and_filters1 = parent::getAll();
        $tablejoins_and_filters1 .= $tablejoins_and_filters;

        return $tablejoins_and_filters1;
    }

}

?> 