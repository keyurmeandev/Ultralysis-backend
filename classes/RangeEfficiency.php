<?php

namespace classes;

use projectsettings;
use datahelper;
use filters;
use utils;
use db;
use config;

class RangeEfficiency extends config\UlConfig {
    /*     * ***
     * Default gateway function, should be similar in all DATA CLASS
     * arguments:
     *  $settingVars [project settingsGateway variables]
     * @return $jsonOutput with all data that should be sent to client app
     * *** */

    public $pageName;
    public $barAccountName;
    public $countcolumn;
    public $dbColumnsArray;
    public $displayCsvNameArray;

    public function go($settingVars) {
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES       
        $this->scannedStoreFieldLabel = 'SCANNED STORES';

        $this->settingVars->pageName = (empty($this->settingVars->pageName)) ? $this->settingVars->pageID . '_Efficiency' : $this->settingVars->pageName;

        $this->redisCache = new utils\RedisCache($this->queryVars);
        if ($this->settingVars->isDynamicPage) {
            $this->getAccountField();
        } else {
            if (!isset($this->settingVars->pageArray[$this->settingVars->pageName]) ||
                    empty($this->settingVars->pageArray[$this->settingVars->pageName]))
                $this->configurationFailureMessage();
        }

        $this->jsonOutput['accountTitle'] = $this->settingVars->pageArray[$this->settingVars->pageName]["BAR_ACCOUNT_TITLE"];
        
        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
            $this->fetchConfig(); // Fetching filter configuration for page
         } else if(isset($_REQUEST["action"]) && $_REQUEST["action"] == 'fetchInlineMarketAndProductFilter') {
            $this->settingVars->pageName = '';
            $this->fetchInlineMarketAndProductFilterData();
        } else {
            
            $this->barAccountName = $this->settingVars->pageArray[$this->settingVars->pageName]["BAR_ACCOUNT_TITLE"];
            $account = $this->settingVars->pageArray[$this->settingVars->pageName]["ACCOUNT"];

            $this->queryPart = $this->getAll();  //PREPARE TABLE JOIN STRING USING PARENT getAll
            /* $id = !key_exists('ID', $this->settingVars->dataArray[$account]) ? $this->settingVars->dataArray[$account]["NAME"] : $this->settingVars->dataArray[$account]["ID"]; */
            $name = $this->settingVars->dataArray[$account]["NAME"];
            if ($_REQUEST['projectType'] == 'relayplus')
                $this->customSelectPartForRL();
            else
                $this->customSelectPart();

            $this->GridSKU($name);
        }

        return $this->jsonOutput;
    }

    public function fetchConfig() {
        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
            $this->jsonOutput['gridConfig'] = array(
                'enabledFilters' => $this->getPageConfiguration('filter_settings', $this->settingVars->pageID)
            );
            
            $filterPosition = $this->getPageConfiguration('filter_position', $this->settingVars->pageID)[0];
            if($filterPosition != "")
                $this->jsonOutput['filterPosition'] = $filterPosition;
            else
                $this->jsonOutput['filterPosition'] = "LEFT";
                
            $filtersDisplayType = $this->getPageConfiguration('filter_disp_type', $this->settingVars->pageID);
            if(!empty($filtersDisplayType) && isset($filtersDisplayType[0])) {
                $this->jsonOutput['gridConfig']['enabledFiltersDispType'] = $filtersDisplayType[0];
            }
            
			if(isset($this->settingVars->pageArray["RangeEfficiency"]) && isset($this->settingVars->pageArray["RangeEfficiency"]["ExtraColumns"]) && is_array($this->settingVars->pageArray["RangeEfficiency"]["ExtraColumns"]) && count($this->settingVars->pageArray["RangeEfficiency"]["ExtraColumns"])>0){
	        	$ExtraColumnArr = $this->settingVars->pageArray["RangeEfficiency"]["ExtraColumns"];
	        	if ($ExtraColumnArr) {
	        		foreach ($ExtraColumnArr as $key => $value) {
	        			unset($value['FIELDS']);
	        			$this->jsonOutput["EXTRA_FIELD_NAMES"][$key] = $value;
	        		}
	        	}
	        }
        }
    }

    public function getAccountField() {
        if(isset($_REQUEST['selectedField']) && $_REQUEST['selectedField'] != "")
            $account = array($_REQUEST['selectedField']);
        else
            $account = $this->getPageConfiguration('account_field', $this->settingVars->pageID);

        $extraColumns = $this->getPageConfiguration('extra_columns', $this->settingVars->pageID);    
        $scannedStoreField = $this->getPageConfiguration('scanned_store_field', $this->settingVars->pageID);
        $scannedStoreFieldLabel = $this->getPageConfiguration('scanned_store_field_label', $this->settingVars->pageID);
        if(isset($scannedStoreFieldLabel) && !empty($scannedStoreFieldLabel))
            $this->scannedStoreFieldLabel = $scannedStoreFieldLabel[0];

        $range = $this->getPageConfiguration('range', $this->settingVars->pageID);

        if (is_array($account) && !empty($account) && is_array($range) && !empty($range)) {
            $fieldPart = explode("#", $account[0]);
            $fields[] = $fieldPart[0];
            
            if (is_array($scannedStoreField) && !empty($scannedStoreField))
                $fields[] = $scannedStoreField[0];

            if (!empty($extraColumns) && is_array($extraColumns)){
                foreach ($extraColumns as $ky => $vl) {
                    $fields[] = $vl;
                }
            }

            $this->buildDataArray($fields);

            $this->settingVars->pageArray[$this->settingVars->pageName]["BAR_ACCOUNT_TITLE"] = $this->displayCsvNameArray[$fieldPart[0]];
            $this->settingVars->pageArray[$this->settingVars->pageName]["ACCOUNT"] = strtoupper($this->dbColumnsArray[$fieldPart[0]]);

            if (is_array($scannedStoreField) && !empty($scannedStoreField))
                $this->settingVars->pageArray[$this->settingVars->pageName]["COUNT_ACCOUNT"] = strtoupper($this->dbColumnsArray[$scannedStoreField[0]]);
            else
                $this->settingVars->pageArray[$this->settingVars->pageName]["COUNT_ACCOUNT"] = "";

            if (!empty($extraColumns) && is_array($extraColumns)){
                foreach ($extraColumns as $ky => $vl) {
                    $this->settingVars->pageArray[$this->settingVars->pageName]["EXTRACOLUMNS"][] = ["BAR_ACCOUNT_TITLE" => $this->displayCsvNameArray[$vl], "ACCOUNT" => strtoupper($this->dbColumnsArray[$vl])];
                }
            }

            if (!isset($_REQUEST["skuPercent"]) || empty($_REQUEST["skuPercent"]))
                $_REQUEST["skuPercent"] = $this->jsonOutput['skuPercent'] = (int) $range[0];
        }
        else {
            $response = array("configuration" => array("status" => "fail", "messages" => array("Page isn't configured properly.")));
            echo json_encode($response);
            exit();
        }
    }

    public function buildDataArray($fields) {
        if (empty($fields))
            return false;

        $configurationCheck = new config\ConfigurationCheck($this->settingVars, $this->queryVars);
        $configurationCheck->buildDataArray($fields);
        $this->dbColumnsArray = $configurationCheck->dbColumnsArray;
        $this->displayCsvNameArray = $configurationCheck->displayCsvNameArray;
        return;
    }

    public function customSelectPart() {
        $countAccount = $this->settingVars->pageArray[$this->settingVars->pageName]["COUNT_ACCOUNT"];
        $this->customSelectPart = "";
        
        $projectStructureTypePage = "projectstructure\\".$this->settingVars->projectStructureType."_PAGES";
        $structurePageClass = new $projectStructureTypePage();
        $result = $structurePageClass->rangeEfficiencyCustomSelectPart($this->settingVars, $countAccount);
        $this->customSelectPart = $result['customSelectPart'];
        $this->countcolumn = $result['countcolumn'];
    }
    
    public function customSelectPartForRL() {
        $countAccount = $this->settingVars->pageArray[$this->settingVars->pageName]["COUNT_ACCOUNT"];
        $this->customSelectPart = "";

        if (!empty($countAccount)) {
            $this->countcolumn = !key_exists('ID', $this->settingVars->dataArray[$countAccount]) ? $this->settingVars->dataArray[$countAccount]["NAME"] : $this->settingVars->dataArray[$countAccount]["ID"];

            $this->customSelectPart = "MAX(CASE WHEN " . $this->settingVars->ProjectVolume . ">0 AND ". 
                $this->settingVars->yearperiod ."=". filters\timeFilter::$ToYear ." AND ". 
                $this->settingVars->weekperiod ."=". filters\timeFilter::$ToWeek ." THEN $this->countcolumn END) AS STORES ";
        }
    }

    public function fetchInlineMarketAndProductFilterData() {
        /*[START] PREPARING THE PRODUCT INLINE FILTER DATA */
        if(isset($this->queryVars->projectConfiguration) && isset($this->queryVars->projectConfiguration['has_product_market_filter_disp_type']) && $this->queryVars->projectConfiguration['has_product_market_filter_disp_type']==1) {
            $configureProject = new config\ConfigureProject($this->settingVars, $this->queryVars);
            $configureProject->fetch_all_product_and_marketSelection_data(true);
            $extraWhere = filters\timeFilter::$seasonalTimeframeExtraWhere." AND (" .filters\timeFilter::$tyWeekRange. " OR ".filters\timeFilter::$lyWeekRange.") ";
            $this->getAllProductAndMarketInlineFilterData($this->queryVars, $this->settingVars, $extraWhere);
        }
        /*[END] PREPARING THE PRODUCT INLINE FILTER DATA */
    }

    public function GridSKU($name) {
        
        $filtersColumns = $this->getPageConfiguration('filter_settings', $this->settingVars->pageID);
        if (in_array('hardstop-selection', $filtersColumns) && isset($_REQUEST['timeFrame']) &&  isset($_REQUEST['fetchHardStopDate']) && $_REQUEST['fetchHardStopDate'] == 'YES') {
            $showAsOutput = (isset($_REQUEST['fetchHardStopDate']) && $_REQUEST['fetchHardStopDate'] == 'YES') ? true : false;
            $requestedCombination = explode("-", $_REQUEST['timeFrame']);
            $requestedYear        = $requestedCombination[1];
            $requestedSeason      = $requestedCombination[0];

            $objDatahelper = new datahelper\Time_Selection_DataCollectors($this->settingVars);
            $objDatahelper->getAllSeasonalHardStopDates($this->jsonOutput,$requestedYear, $showAsOutput);    
        }        
        
        $this->measureFields = $this->prepareTablesUsedForMeasure($_REQUEST['ValueVolume']);
        $this->measureFields[] = $name;
        $this->measureFields[] = $this->countcolumn; // countcolumn from custom select part
        /*$ExtraCols = [];
        if(isset($this->settingVars->pageArray[$this->settingVars->pageName]) && isset($this->settingVars->pageArray[$this->settingVars->pageName]["EXTRACOLUMNS"]) && is_array($this->settingVars->pageArray[$this->settingVars->pageName]["EXTRACOLUMNS"]) && count($this->settingVars->pageArray[$this->settingVars->pageName]["EXTRACOLUMNS"])>0){
            foreach ($this->settingVars->pageArray[$this->settingVars->pageName]["EXTRACOLUMNS"] as $ky => $extraValue) {
                $ExtraCols[] = $this->settingVars->dataArray[$extraValue['ACCOUNT']]["NAME"];
                $this->measureFields[] = $this->settingVars->dataArray[$extraValue['ACCOUNT']]["NAME"];
            }
        }*/

        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->queryPart = $this->getAll();
        $skipScanStores = false;

        
        /*[START] ADDING EXTRA FIELD BASED ON THE MAINCLASS */
        $ExtraColumnAggregateFields = ''; $ExtraColumnAllArr = $ExtraColumnArr = array();
        if(isset($this->settingVars->pageArray["RangeEfficiency"]) && isset($this->settingVars->pageArray["RangeEfficiency"]["ExtraColumns"]) && is_array($this->settingVars->pageArray["RangeEfficiency"]["ExtraColumns"]) && count($this->settingVars->pageArray["RangeEfficiency"]["ExtraColumns"])>0){
            $ExtraColumnAllArr = $this->settingVars->pageArray["RangeEfficiency"]["ExtraColumns"];
            if ($ExtraColumnAllArr) {
                foreach ($ExtraColumnAllArr as $key => $value) {
                    $ExtraColumnAggregateFields .= ','.$value['FIELDS'];
                    $ExtraColumnArr[] = $key;
                }
                //$GroupByFieldArr = array_merge($GroupByFieldArr,$ExtraColumnArr);
            }
        }
        /*[END] ADDING EXTRA FIELD BASED ON THE MAINCLASS */        
        
        
        if (empty($this->customSelectPart))
            $skipScanStores = true;
        else
        {
            $query = "SELECT $name AS ACCOUNT " .
                (!empty($this->customSelectPart) ? ", " . $this->customSelectPart : "") . $ExtraColumnAggregateFields.
                //(!empty($ExtraCols) && count($ExtraCols)>0 ? ", " . implode(',', $ExtraCols) : "") .
                " FROM " . $this->settingVars->tablename . $this->queryPart .
                " AND (" . filters\timeFilter::$tyWeekRange . " OR " . filters\timeFilter::$lyWeekRange . ") " .
                "GROUP BY ACCOUNT ";
                //(!empty($ExtraCols) && count($ExtraCols)>0 ? ", " . implode(',', $ExtraCols) : "");

            $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);

            if ($redisOutput === false) {
                $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
                $this->redisCache->setDataForHash($result);
            } else {
                $result = $redisOutput;
            }
            
            $scanedStore = array();
            if(is_array($result) && !empty($result))
            {
                foreach($result as $data)
                    $scanedStore[$data['ACCOUNT']] = $data['STORES'];
            }
        }

        $measureSelectRes = $this->prepareMeasureSelectPart();
        $this->measureFields = $measureSelectRes['measureFields'];
        
        $measureSelectionArr = $measureSelectRes['measureSelectionArr'];
        $havingTYValue = $measureSelectRes['havingTYValue'];
        $havingLYValue = $measureSelectRes['havingLYValue'];

        $projectStructureTypePage = "projectstructure\\".$this->settingVars->projectStructureType."_PAGES";
        $structurePageClass = new $projectStructureTypePage();
        $extraWhereClause = $structurePageClass->extraWhereClause($this->settingVars, $this->measureFields);
        
        $this->settingVars->tableUsedForQuery = array();
        $this->measureFields[] = $name;
        $ExtraCols = [];
        if(isset($this->settingVars->pageArray[$this->settingVars->pageName]) && isset($this->settingVars->pageArray[$this->settingVars->pageName]["EXTRACOLUMNS"]) && is_array($this->settingVars->pageArray[$this->settingVars->pageName]["EXTRACOLUMNS"]) && count($this->settingVars->pageArray[$this->settingVars->pageName]["EXTRACOLUMNS"])>0){
            foreach ($this->settingVars->pageArray[$this->settingVars->pageName]["EXTRACOLUMNS"] as $ky => $extraValue) {

                $ExtraCols[] = ['NAME' => $this->settingVars->dataArray[$extraValue['ACCOUNT']]["NAME"]." AS ".$this->settingVars->dataArray[$extraValue['ACCOUNT']]['NAME_ALIASE'], 'NAME_ALIASE' => $this->settingVars->dataArray[$extraValue['ACCOUNT']]['NAME_ALIASE'], 'NAME_CSV' => $this->settingVars->dataArray[$extraValue['ACCOUNT']]['NAME_CSV']];

                $this->measureFields[] = $this->settingVars->dataArray[$extraValue['ACCOUNT']]["NAME"];
            }
        }

        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        
        $this->queryPart = $this->getAll(). $extraWhereClause;
        
        $query = "SELECT $name AS ACCOUNT, " .implode(",", $measureSelectionArr) . 
            (!empty($ExtraCols) && count($ExtraCols)>0 ? ", " . implode(',', array_column($ExtraCols,'NAME')) : "") .
            $ExtraColumnAggregateFields.
            " FROM " . $this->settingVars->tablename .' '. trim($this->queryPart) .
            " AND (" . filters\timeFilter::$tyWeekRange . " OR " . filters\timeFilter::$lyWeekRange . ") " .
            "GROUP BY ACCOUNT ".(!empty($ExtraCols) && count($ExtraCols)>0 ? ", " . implode(',', array_column($ExtraCols,'NAME_ALIASE')) : "");
        
        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);

        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }

        if(!empty($ExtraCols) && count($ExtraCols)>0)
            $selectedFld = array_merge(array("ACCOUNT"),array_column($ExtraCols,'NAME_ALIASE'), $ExtraColumnArr);
        elseif(!empty($ExtraColumnArr) && count($ExtraColumnArr)>0)
            $selectedFld = array_merge(array("ACCOUNT"),$ExtraColumnArr);
        else
            $selectedFld = array("ACCOUNT");

        $fields = $this->redisCache->getRequiredFieldsArray($selectedFld, false, $this->settingVars->measureArray);
        $result = $this->redisCache->getRequiredData($result, $fields, $havingTYValue);

        $totalSku = $totalSkuSum = $share = $cumShare = $topSku = $topSkuSum = 0;
        $stack = array();

        $totalSkuSum = array_sum(array_column($result, $havingTYValue));

        $tempResult = array();
        foreach ($result as $key => $data) {
            $tempVal = (float) $data[$havingTYValue];
            if ($tempVal > 0) {
                $totalSku++;
                $share = ($data[$havingTYValue] / $totalSkuSum) * 100;
                $diff = $data[$havingTYValue] - $data[$havingLYValue];
                $percent = $data[$havingLYValue] > 0 ? ($diff / $data[$havingLYValue]) * 100 : 0;

                if ($cumShare < $_REQUEST["skuPercent"]) {
                    $topSku ++;
                    $topSkuSum = $topSkuSum + $data[$havingTYValue];
                    $jsonTag = "topSKU";
                } else {
                    $jsonTag = "tailSKU";
                }

                $cumShare = $cumShare + $share;

                $temp = array();
                $temp['ACCOUNT'] = htmlspecialchars_decode($data['ACCOUNT']);

                if(!empty($ExtraCols) && count($ExtraCols)>0){
                    $EXTRA_FIELDS = array_column($ExtraCols,'NAME_ALIASE');
                    foreach($EXTRA_FIELDS as $ky=>$extrafields){
                        $temp[$extrafields] = htmlspecialchars_decode($data[$extrafields]);
                    }
                }

                $temp['TYEAR'] = $data[$havingTYValue];
                $temp['LYEAR'] = $data[$havingLYValue];

                if (!$skipScanStores)
                    $temp['STORES'] = (int)$scanedStore[$data['ACCOUNT']];

                $temp['DIFF'] = $diff;
                $temp['percent'] = $percent;
                $temp['share'] = $share;
                $temp['cumShare'] = $cumShare;
                
                if(!empty($ExtraColumnArr) && count($ExtraColumnArr)>0){
                    foreach($ExtraColumnArr as $extracols)
                        $temp[$extracols] = $data[$extracols];
                }
                
                $tempResult[$jsonTag][] = $temp;
                
            }
        }
        $this->jsonOutput['gridSKU'] = $tempResult;
        $this->jsonOutput['skipScanStores'] = $skipScanStores;
        $this->jsonOutput['scannedStoreFieldLabel'] = $this->scannedStoreFieldLabel;
        $this->jsonOutput['extraFields'] = $ExtraCols;
        
        $this->jsonOutput['skuDetail'] = array(
            'totalSku' => $totalSku,
            'totalSkuSum' => $totalSkuSum,
            'topSku' => $topSku,
            'topSkuSum' => (float) $topSkuSum,
            'tailSku' => ($totalSku - $topSku),
            'tailSkuSum' => (float) ($totalSkuSum - $topSkuSum)
        );

        if ($totalSku <> 0) {
            $temp = array();
            $temp['BNAME'] = "% of " . $this->barAccountName;
            $temp['value'] = ($topSku / $totalSku) * 100;
            $temp['sku'] = (($totalSku - $topSku) / $totalSku) * 100;
            $this->jsonOutput['barchart'][] = $temp;
        } else {
            $temp = array();
            $temp['BNAME'] = "% of " . $this->barAccountName;
            $temp['value'] = 0.0;
            $temp['sku'] = 0.0;
            $this->jsonOutput['barchart'][] = $temp;
        }

        $searchKey = (is_array($this->settingVars->pageArray["MEASURE_SELECTION_LIST"]) && !empty($this->settingVars->pageArray["MEASURE_SELECTION_LIST"])) ? array_search($_REQUEST['ValueVolume'], array_column($this->settingVars->pageArray["MEASURE_SELECTION_LIST"], 'measureID')) : '';
        $ValueVolumeText = $this->settingVars->pageArray["MEASURE_SELECTION_LIST"][$searchKey]['measureName'];

        if ($totalSkuSum <> 0) {
            $temp = array();
            $temp['BNAME'] = "% of " . htmlspecialchars($ValueVolumeText);
            $temp['value'] = ($topSkuSum / $totalSkuSum) * 100;
            $temp['sku'] = (($totalSkuSum - $topSkuSum) / $totalSkuSum) * 100;
            $this->jsonOutput['barchart'][] = $temp;
        } else {
            $temp = array();
            $temp['BNAME'] = "% of " . htmlspecialchars($ValueVolumeText);
            $temp['value'] = 0.0;
            $temp['sku'] = 0.0;
            $this->jsonOutput['barchart'][] = $temp;
        }
    }

}

?>