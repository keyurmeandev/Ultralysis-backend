<?php

namespace classes;

use projectsettings;
use datahelper;
use filters;
use config;
use utils;
use db;
use lib;

class FactoryShipmentOverview extends config\UlConfig {

    public function go($settingVars) {
        $this->initiate($settingVars);

        $this->settingVars->pageName = (empty($this->settingVars->pageName)) ? $this->settingVars->pageID.'_FactoryShipmentOverviewPage' : $this->settingVars->pageName;
        $this->redisCache = new utils\RedisCache($this->queryVars);
        if ($this->settingVars->isDynamicPage) {
            $this->productBaseAccountField = $this->getPageConfiguration('product_base_account_field', $this->settingVars->pageID)[0];
            $this->productBaseAccountMaxRecordFields = $this->getPageConfiguration('product_base_account_max_record_fields', $this->settingVars->pageID);
            $this->productBaseAccountOtherRecordField = $this->getPageConfiguration('product_base_account_other_record_field', $this->settingVars->pageID)[0];

            $otherColumns = array($this->productBaseAccountField, $this->productBaseAccountOtherRecordField);
            
            $this->productSpecialGridValueField = $this->getPageConfiguration('product_special_grid_value_field', $this->settingVars->pageID);
            
            if (!empty($this->productSpecialGridValueField))
                $this->productSpecialGridValueField = $this->productSpecialGridValueField[0];
            else
                $this->productSpecialGridValueField = "";

            $this->productSpecialGridField = $this->getPageConfiguration('product_special_grid_field', $this->settingVars->pageID);

            if (!empty($this->productSpecialGridField)) {
                $this->productSpecialGridField = $this->productSpecialGridField[0];
                $otherColumns[] = $this->productSpecialGridField;
            }
            else
                $this->productSpecialGridField = "";

            $this->buildDataArray(array_merge($this->productBaseAccountMaxRecordFields, $otherColumns));
            $this->buildPageArray();
        } else {
            $this->configurationFailureMessage();
        }

        $action = $_REQUEST["action"];
        switch ($action) {
            case "allSalesGrid":
                $this->totalSales();
                $this->getGridData();

                $this->redisCache->requestHash = $this->redisCache->prepareCommonHash();
                $this->redisCache->setDataForStaticHash($this->jsonOutput);
                $this->jsonOutput['exportDataHash'] = $this->redisCache->requestHash;
                break;
            case "exportData":
                $this->exportData();
            default:
                break;
        }
        return $this->jsonOutput;
    }

    private function totalSales() {
        $measureColumns = $this->preparePageLevelSelectedMeasure();
        $query = "SELECT ". implode(',',$this->measureFields). " FROM " . $this->settingVars->tablename .' '. trim($this->queryPart) .
                    " AND (" . filters\timeFilter::$tyWeekRange . " OR " . filters\timeFilter::$lyWeekRange . ") ";

        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }

        if(is_array($result) && !empty($result)){
            foreach($result as $k => $row){
                $result[$k]['ACCOUNT'] = 'TOTAL';
                foreach ($this->mappingMeasure as $ky => $val) {
                    $tyValue = $row['TY_'.$this->settingVars->measureArray['M'.$val]['ALIASE']]*1;
                    $lyValue = $row['LY_'.$this->settingVars->measureArray['M'.$val]['ALIASE']]*1;
                    $var     = $tyValue - $lyValue;
                    $result[$k]['TY_'.$this->settingVars->measureArray['M'.$val]['ALIASE']] = $tyValue;
                    $result[$k]['LY_'.$this->settingVars->measureArray['M'.$val]['ALIASE']] = $lyValue;
                    $result[$k]['VAR_'.$this->settingVars->measureArray['M'.$val]['ALIASE']] = $var;
                    $result[$k]['VAR_PER_'.$this->settingVars->measureArray['M'.$val]['ALIASE']] = $lyValue!=0 ? (($var/$lyValue)*100) : 0;
                    $result[$k]['SHARE_'.$this->settingVars->measureArray['M'.$val]['ALIASE']] = 100;
                    $result[$k]['SHARE_CHANGE_'.$this->settingVars->measureArray['M'.$val]['ALIASE']] = 0;
                }
            }
        }

        /*
        print_r($measureColumns);
        print_r($result);
        exit;*/

        $this->jsonOutput['totalSalesColumns'] = $measureColumns;
        $this->jsonOutput['totalSales'] = $result;
    }

    private function getGridData() {

        $measureColumns = $this->preparePageLevelSelectedMeasure([$this->accountField]);
        $query = "SELECT ".$this->accountField." AS ACCOUNT, ". implode(',',$this->measureFields). " FROM " . $this->settingVars->tablename .' '. trim($this->queryPart) .
                    " AND (" . filters\timeFilter::$tyWeekRange . " OR " . filters\timeFilter::$lyWeekRange . ") GROUP BY ACCOUNT ORDER BY LY_".$measureColumns[0]." DESC";

        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }

        $arrayTotalTy = [];
        if(is_array($result) && !empty($result)){
            foreach($result as $k => $row){
                foreach ($this->mappingMeasure as $ky => $val) {

                    if(!isset($arrayTotal[$result[$k]['ACCOUNT']]['TY_'.$this->settingVars->measureArray['M'.$val]['ALIASE']])){
                        $tyTotalValue = array_sum(array_column($result, 'TY_'.$this->settingVars->measureArray['M'.$val]['ALIASE']));
                        $lyTotalValue = array_sum(array_column($result, 'LY_'.$this->settingVars->measureArray['M'.$val]['ALIASE']));
                        $arrayTotal[$result[$k]['ACCOUNT']]['TY_'.$this->settingVars->measureArray['M'.$val]['ALIASE']] = $tyTotalValue;
                        $arrayTotal[$result[$k]['ACCOUNT']]['LY_'.$this->settingVars->measureArray['M'.$val]['ALIASE']] = $lyTotalValue;
                    }
                    
                    $tyValue = $row['TY_'.$this->settingVars->measureArray['M'.$val]['ALIASE']]*1;
                    $lyValue = $row['LY_'.$this->settingVars->measureArray['M'.$val]['ALIASE']]*1;
                    $result[$k]['TY_'.$this->settingVars->measureArray['M'.$val]['ALIASE']] = $tyValue;
                    $result[$k]['LY_'.$this->settingVars->measureArray['M'.$val]['ALIASE']] = $lyValue;
                    $var     = $tyValue - $lyValue;
                    $result[$k]['VAR_'.$this->settingVars->measureArray['M'.$val]['ALIASE']] = $var;
                    $result[$k]['VAR_PER_'.$this->settingVars->measureArray['M'.$val]['ALIASE']] = $lyValue!=0 ? (($var/$lyValue)*100) : 0;

                    $shareTY = $tyTotalValue !=0 ? (($tyValue/$tyTotalValue)*100) : 0;
                    $shareLY = $lyTotalValue !=0 ? (($lyValue/$lyTotalValue)*100) : 0;
                    $shareChange    = $shareTY - $shareLY;

                    $result[$k]['SHARE_'.$this->settingVars->measureArray['M'.$val]['ALIASE']] = $shareTY;
                    $result[$k]['SHARE_CHANGE_'.$this->settingVars->measureArray['M'.$val]['ALIASE']] = $shareChange;
                }
            }
        }

        $this->jsonOutput['salesColumns'] = $measureColumns;
        $this->jsonOutput['salesAccountColumns'] = ['ACCOUNT'=>$this->accountName];
        $this->jsonOutput['salesData'] = $result;

        /*[START] GIRD DATA PREPARATION BASED ON THE MAX SALES PRODUCT*/
        $maxSalesProduct = $this->jsonOutput['salesData'][0]['ACCOUNT'];
        if(isset($this->productBaseAccountMaxRecordFields) && is_array($this->productBaseAccountMaxRecordFields) && count($this->productBaseAccountMaxRecordFields) > 0) {
            foreach ($this->productBaseAccountMaxRecordFields as $key => $actFld) {
                $accountField = strtoupper($this->dbColumnsArray[$actFld]);
                $accountFieldsArr[$this->settingVars->dataArray[$accountField]['NAME_ALIASE']] = $this->settingVars->dataArray[$accountField]['NAME_CSV'];
                $accountDbFields[$this->settingVars->dataArray[$accountField]['NAME_ALIASE']] = $this->settingVars->dataArray[$accountField]['NAME']." AS ".$this->settingVars->dataArray[$accountField]['NAME_ALIASE'];
            }

            $accounts = array_keys($accountDbFields);
            $query = "SELECT ".implode(',', $accountDbFields).", ". implode(',',$this->measureFields). " FROM " . $this->settingVars->tablename .' '. trim($this->queryPart) .
                    " AND (" . filters\timeFilter::$tyWeekRange . " OR " . filters\timeFilter::$lyWeekRange . ") 
                      AND ".$this->accountField." = '".$maxSalesProduct."' GROUP BY ".implode(',', $accounts)." ORDER BY LY_".$measureColumns[0]." DESC";

            $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
            if ($redisOutput === false) {
                $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
                $this->redisCache->setDataForHash($result);
            } else {
                $result = $redisOutput;
            }

            $maxSalesProductData = [];
            if(is_array($result) && !empty($result)){
                foreach ($result as $k => $row) {
                    foreach ($accountDbFields as $key => $value) {
                        foreach ($this->mappingMeasure as $ky => $val) {
                            $maxSalesProductData[$key][$row[$key]]['ACCOUNT'] = $row[$key];

                            $maxSalesProductData[$key][$row[$key]]['TY_'.$this->settingVars->measureArray['M'.$val]['ALIASE']] += $row['TY_'.$this->settingVars->measureArray['M'.$val]['ALIASE']];

                            $maxSalesProductData[$key][$row[$key]]['LY_'.$this->settingVars->measureArray['M'.$val]['ALIASE']] += $row['LY_'.$this->settingVars->measureArray['M'.$val]['ALIASE']];

                            $maxSalesProductData[$key][$row[$key]]['VAR_'.$this->settingVars->measureArray['M'.$val]['ALIASE']] = $maxSalesProductData[$key][$row[$key]]['TY_'.$this->settingVars->measureArray['M'.$val]['ALIASE']] - $maxSalesProductData[$key][$row[$key]]['LY_'.$this->settingVars->measureArray['M'.$val]['ALIASE']];

                            $maxSalesProductData[$key][$row[$key]]['VAR_PER_'.$this->settingVars->measureArray['M'.$val]['ALIASE']] = ($maxSalesProductData[$key][$row[$key]]['LY_'.$this->settingVars->measureArray['M'.$val]['ALIASE']]!=0) ? (($maxSalesProductData[$key][$row[$key]]['VAR_'.$this->settingVars->measureArray['M'.$val]['ALIASE']]/$maxSalesProductData[$key][$row[$key]]['LY_'.$this->settingVars->measureArray['M'.$val]['ALIASE']])*100) : 0;

                            $totalAccountTy = isset($arrayTotal[$maxSalesProduct]) && isset($arrayTotal[$maxSalesProduct]['TY_'.$this->settingVars->measureArray['M'.$val]['ALIASE']]) ? $arrayTotal[$maxSalesProduct]['TY_'.$this->settingVars->measureArray['M'.$val]['ALIASE']] : 0;

                            $totalAccountLy = isset($arrayTotal[$maxSalesProduct]) && isset($arrayTotal[$maxSalesProduct]['LY_'.$this->settingVars->measureArray['M'.$val]['ALIASE']]) ? $arrayTotal[$maxSalesProduct]['LY_'.$this->settingVars->measureArray['M'.$val]['ALIASE']] : 0;

                            $shareTY = $totalAccountTy !=0 ? (($maxSalesProductData[$key][$row[$key]]['TY_'.$this->settingVars->measureArray['M'.$val]['ALIASE']]/$totalAccountTy)*100) : 0;

                            $shareLY = $totalAccountLy !=0 ? (($maxSalesProductData[$key][$row[$key]]['LY_'.$this->settingVars->measureArray['M'.$val]['ALIASE']]/$totalAccountLy)*100) : 0;

                            $shareChange    = $shareTY - $shareLY;
                            $maxSalesProductData[$key][$row[$key]]['SHARE_'.$this->settingVars->measureArray['M'.$val]['ALIASE']] = $shareTY;
                            $maxSalesProductData[$key][$row[$key]]['SHARE_CHANGE_'.$this->settingVars->measureArray['M'.$val]['ALIASE']] = $shareChange;
                        }
                    }
                }
            }

            foreach ($maxSalesProductData as $key => $value) {
                $maxSalesProductData[$key] = array_values($value);
            }
            
            $this->jsonOutput['maxSalesProduct'] = $maxSalesProduct;
            $this->jsonOutput['maxSalesProductAccountColumns'] = $accountFieldsArr;
            $this->jsonOutput['maxSalesProductData'] = $maxSalesProductData;
        }
        /*[END] GIRD DATA PREPARATION BASED ON THE MAX SALES PRODUCT*/

        /*[START] GIRD DATA PREPARATION BASED ON ALL OTHER SALES PRODUCTS*/
        $allOtherSalesProducts = array_slice($this->jsonOutput['salesData'],1,count($this->jsonOutput['salesData']));
        $productBaseAccountOtherRecordFields = array_column($allOtherSalesProducts, 'ACCOUNT');
        $actRemoveKey = array_search($this->productSpecialGridValueField,$productBaseAccountOtherRecordFields);
        if ($actRemoveKey !== false) {
            unset($productBaseAccountOtherRecordFields[$actRemoveKey]);
            $productBaseAccountOtherRecordFields = array_values($productBaseAccountOtherRecordFields);
        }

        if(isset($this->productBaseAccountOtherRecordField) && !empty($this->productBaseAccountOtherRecordField)) {

            $accountField = strtoupper($this->dbColumnsArray[$this->productBaseAccountOtherRecordField]);
            $this->productBaseAccountOtherRecordField = $this->settingVars->dataArray[$accountField]['NAME'];
            $this->productBaseAccountOtherRecordFieldName = $this->settingVars->dataArray[$accountField]['NAME_CSV'];

            $measureColumns = $this->preparePageLevelSelectedMeasure([$this->accountField,$this->productBaseAccountOtherRecordField]);
            $query = "SELECT ".$this->productBaseAccountOtherRecordField." AS ACCOUNT, ".$this->accountField." AS MAIN_ACCOUNT, ". implode(',',$this->measureFields). " FROM " . $this->settingVars->tablename .' '. trim($this->queryPart) .
                        " AND (" . filters\timeFilter::$tyWeekRange . " OR " . filters\timeFilter::$lyWeekRange . ") AND ".$this->accountField." IN ('".implode("','",$productBaseAccountOtherRecordFields)."') GROUP BY MAIN_ACCOUNT,ACCOUNT ORDER BY LY_".$measureColumns[0]." DESC";

            $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
            if ($redisOutput === false) {
                $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
                $this->redisCache->setDataForHash($result);
            } else {
                $result = $redisOutput;
            }

            $productBaseAccountOtherData = $tmpProductBaseAccountOtherRecordFields = [];
            foreach ($productBaseAccountOtherRecordFields as $key => $value) {
                $tmparr = array_keys(array_column($result, 'MAIN_ACCOUNT'), $value);
                $main_key = strtoupper(str_replace(' ', '_', $value));
                $tmpProductBaseAccountOtherRecordFields[$main_key] = $value;
                if (!empty($tmparr)) {
                    for ($i=0; $i<count($tmparr); $i++) {
                        $productBaseAccountOtherData[$main_key][] = $result[$tmparr[$i]];
                    }
                }
            }

            if(!empty($productBaseAccountOtherData)){
                foreach ($productBaseAccountOtherData as $main_key => $result) {
                    foreach ($result as $k => $row) {

                        foreach ($this->mappingMeasure as $ky => $val) {
                            $tyValue = $row['TY_'.$this->settingVars->measureArray['M'.$val]['ALIASE']]*1;
                            $lyValue = $row['LY_'.$this->settingVars->measureArray['M'.$val]['ALIASE']]*1;

                            $productBaseAccountOtherData[$main_key][$k]['TY_'.$this->settingVars->measureArray['M'.$val]['ALIASE']] = $tyValue;
                            $productBaseAccountOtherData[$main_key][$k]['LY_'.$this->settingVars->measureArray['M'.$val]['ALIASE']] = $lyValue;
                            $var     = $tyValue - $lyValue;
                            $productBaseAccountOtherData[$main_key][$k]['VAR_'.$this->settingVars->measureArray['M'.$val]['ALIASE']] = $var;
                            $productBaseAccountOtherData[$main_key][$k]['VAR_PER_'.$this->settingVars->measureArray['M'.$val]['ALIASE']] = $lyValue!=0 ? (($var/$lyValue)*100) : 0;

                            $totalAccountTy = isset($arrayTotal[$tmpProductBaseAccountOtherRecordFields[$main_key]]) && isset($arrayTotal[$tmpProductBaseAccountOtherRecordFields[$main_key]]['TY_'.$this->settingVars->measureArray['M'.$val]['ALIASE']]) ? $arrayTotal[$tmpProductBaseAccountOtherRecordFields[$main_key]]['TY_'.$this->settingVars->measureArray['M'.$val]['ALIASE']] : 0;

                            $totalAccountLy = isset($arrayTotal[$tmpProductBaseAccountOtherRecordFields[$main_key]]) && isset($arrayTotal[$tmpProductBaseAccountOtherRecordFields[$main_key]]['LY_'.$this->settingVars->measureArray['M'.$val]['ALIASE']]) ? $arrayTotal[$tmpProductBaseAccountOtherRecordFields[$main_key]]['LY_'.$this->settingVars->measureArray['M'.$val]['ALIASE']] : 0;

                            $shareTY = $totalAccountTy !=0 ? (($tyValue/$totalAccountTy)*100) : 0;
                            $shareLY = $totalAccountLy !=0 ? (($lyValue/$totalAccountLy)*100) : 0;
                            $shareChange = $shareTY - $shareLY;

                            $productBaseAccountOtherData[$main_key][$k]['SHARE_'.$this->settingVars->measureArray['M'.$val]['ALIASE']] = $shareTY;
                            $productBaseAccountOtherData[$main_key][$k]['SHARE_CHANGE_'.$this->settingVars->measureArray['M'.$val]['ALIASE']] = $shareChange;
                        }
                    }
                }
            }

            $this->jsonOutput['productBaseAccountOtherAccountName'] = $this->productBaseAccountOtherRecordFieldName;
            $this->jsonOutput['productBaseAccountOtherAccountColumns'] = $tmpProductBaseAccountOtherRecordFields;
            $this->jsonOutput['productBaseAccountOtherData'] = $productBaseAccountOtherData;
        }
        /*[END] GIRD DATA PREPARATION BASED ON ALL OTHER SALES PRODUCTS*/

        /*[START] GIRD DATA PREPARATION BASED ON SPECIAL VALUE PRODUCTS*/
        if(isset($this->productSpecialGridValueField) && isset($this->productSpecialGridField) && !empty($this->productSpecialGridValueField) && !empty($this->productSpecialGridField)) {

            $accountField = strtoupper($this->dbColumnsArray[$this->productSpecialGridField]);
            $this->productSpecialGridField = $this->settingVars->dataArray[$accountField]['NAME'];
            $this->productSpecialGridFieldName = $this->settingVars->dataArray[$accountField]['NAME_CSV'];

            $measureColumns = $this->preparePageLevelSelectedMeasure([$this->productSpecialGridField]);
            $query = "SELECT ".$this->productSpecialGridField." AS ACCOUNT, ". implode(',',$this->measureFields). " FROM " . $this->settingVars->tablename .' '. trim($this->queryPart) .
                        " AND (" . filters\timeFilter::$tyWeekRange . " OR " . filters\timeFilter::$lyWeekRange . ") AND ".$this->accountField." = '".$this->productSpecialGridValueField."' GROUP BY ACCOUNT ORDER BY LY_".$measureColumns[0]." DESC";

            $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
            if ($redisOutput === false) {
                $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
                $this->redisCache->setDataForHash($result);
            } else {
                $result = $redisOutput;
            }

            if(is_array($result) && !empty($result)){
                foreach($result as $k => $row){
                    foreach ($this->mappingMeasure as $ky => $val) {
                        $tyValue = $row['TY_'.$this->settingVars->measureArray['M'.$val]['ALIASE']]*1;
                        $lyValue = $row['LY_'.$this->settingVars->measureArray['M'.$val]['ALIASE']]*1;

                        $result[$k]['TY_'.$this->settingVars->measureArray['M'.$val]['ALIASE']] = $tyValue;
                        $result[$k]['LY_'.$this->settingVars->measureArray['M'.$val]['ALIASE']] = $lyValue;
                        $var     = $tyValue - $lyValue;
                        $result[$k]['VAR_'.$this->settingVars->measureArray['M'.$val]['ALIASE']] = $var;
                        $result[$k]['VAR_PER_'.$this->settingVars->measureArray['M'.$val]['ALIASE']] = $lyValue!=0 ? (($var/$lyValue)*100) : 0;

                        $totalAccountTy = isset($arrayTotal[$this->productSpecialGridValueField]) && isset($arrayTotal[$this->productSpecialGridValueField]['TY_'.$this->settingVars->measureArray['M'.$val]['ALIASE']]) ? $arrayTotal[$this->productSpecialGridValueField]['TY_'.$this->settingVars->measureArray['M'.$val]['ALIASE']] : 0;

                        $totalAccountLy = isset($arrayTotal[$this->productSpecialGridValueField]) && isset($arrayTotal[$this->productSpecialGridValueField]['LY_'.$this->settingVars->measureArray['M'.$val]['ALIASE']]) ? $arrayTotal[$this->productSpecialGridValueField]['LY_'.$this->settingVars->measureArray['M'.$val]['ALIASE']] : 0;

                        $shareTY = $totalAccountTy !=0 ? (($tyValue/$totalAccountTy)*100) : 0;
                        $shareLY = $totalAccountLy !=0 ? (($lyValue/$totalAccountLy)*100) : 0;
                        $shareChange = $shareTY - $shareLY;

                        $result[$k]['SHARE_'.$this->settingVars->measureArray['M'.$val]['ALIASE']] = $shareTY;
                        $result[$k]['SHARE_CHANGE_'.$this->settingVars->measureArray['M'.$val]['ALIASE']] = $shareChange;
                    }
                }
            }

            $this->jsonOutput['productSpecialGridAccountName'] = $this->productSpecialGridValueField;
            $this->jsonOutput['productSpecialGridAccountColumns'] = ['ACCOUNT'=>$this->productSpecialGridFieldName];
            $this->jsonOutput['productSpecialGridValueData'] = $result;
        }
        /*[END] GIRD DATA PREPARATION BASED ON SPECIAL VALUE PRODUCTS*/
    }

    private function exportData() {

        if (!isset($_REQUEST['exportDataHash']) || empty($_REQUEST['exportDataHash']))
            return '';

        $pageName    = isset($_REQUEST['pageTitle']) ? trim($_REQUEST['pageTitle']) : 'Factory Shipment Overview';
        $fileName    = str_replace(' ', '-', $pageName)."-" . date("Y-m-d-h-i-s") . ".xlsx";
        $savePath    = dirname(__FILE__)."/../uploads/Factory-Shipment-Overview/";
        $imgLogoPath = dirname(__FILE__)."/../../global/project/assets/img/ultralysis_logo.png";
        $filePath    = $savePath.$fileName;
        $projectID   = $this->settingVars->projectID;
        $RedisServer   = $this->queryVars->RedisHost.":".$this->queryVars->RedisPort;
        $RedisPassword = $this->queryVars->RedisPassword;

        /*
            $timeSelectiondata = '';
            if(isset($_REQUEST['FromWeek']) && isset($_REQUEST['ToWeek']) && isset($_REQUEST['FromWeekPrv']) && isset($_REQUEST['ToWeekPrv'])){
                $timeSelectiondata = $_REQUEST['FromWeek']." To ".$_REQUEST['ToWeek']." VS ".$_REQUEST['FromWeekPrv']." To ".$_REQUEST['ToWeekPrv'];
                $timeSelectiondata = "Time Selection : ".$timeSelectiondata;
            }
            $storeSelectiondata = 'Store Selection:';
        */

        $appliedFilters = [];
        $enabledFilters = $this->getPageConfiguration('filter_settings', $this->settingVars->pageID);
        /*[START] ADDING THE FILTERS AT THE TOP */
            /*[START] Time*/
            if(!empty($enabledFilters) && is_array($enabledFilters) && in_array('time-selection', $enabledFilters)){
                $titleTimeSelection = '';
                if(isset($_REQUEST['FromWeek']) && isset($_REQUEST['ToWeek']) && isset($_REQUEST['FromWeekPrv']) && isset($_REQUEST['ToWeekPrv'])){
                    $titleTimeSelection = $_REQUEST['FromWeek']." To ".$_REQUEST['ToWeek']." VS ".$_REQUEST['FromWeekPrv']." To ".$_REQUEST['ToWeekPrv'];
                    $appliedFilters[] = "Time Selection##".$titleTimeSelection;
                }
            }
            /*[END] Time*/

            /*[START] Product*/
            $productFilterData = $marketFilterData = [];
            if(isset($_REQUEST['FS']) && is_array($_REQUEST['FS']) && count($_REQUEST['FS'])>0){
                foreach($_REQUEST['FS'] as $ky=>$valDt) {
                    if(!empty($valDt)) {
                        if (isset($this->settingVars->dataArray) && isset($this->settingVars->dataArray[$ky])) {
                            $dataList = $valDt;
                            if(isset($this->settingVars->dataArray[$ky]['ID'])) {
                                $dataList = $this->getAllDataFromIds($this->settingVars->dataArray[$ky]['TYPE'],$ky,$dataList);
                            }
                            if($this->settingVars->dataArray[$ky]['TYPE'] == 'P' || $this->settingVars->dataArray[$ky]['TYPE'] == 'K') {
                                $productFilterData[$this->settingVars->dataArray[$ky]['NAME_CSV']] = urldecode($dataList);
                            }else if($this->settingVars->dataArray[$ky]['TYPE'] == 'M' || $this->settingVars->dataArray[$ky]['TYPE'] == 'A' || $this->settingVars->dataArray[$ky]['TYPE'] == 'T') {
                                $marketFilterData[$this->settingVars->dataArray[$ky]['NAME_CSV']] = urldecode($dataList);
                            }
                        }
                    }
                }
            }

            if(!empty($enabledFilters) && is_array($enabledFilters) && in_array('product-selection', $enabledFilters)){
                $objRichText = '';
                if(count($productFilterData)>0){
                    $lstV = count($productFilterData);
                    $i = 0;
                    foreach ($productFilterData as $kIds => $Val) {
                        if($lstV == $i)
                            $Val = $Val;
                        else    
                            $Val = $Val."\n";
                        $objRichText.= $kIds.' : '.$Val;
                    }
                }else{
                    $objRichText = 'All';
                }
                $objRichText = trim($objRichText, "\n");
                $appliedFilters[] = 'Product Selection##'.$objRichText;
            }
            /*[END] Procuct*/
            
            /*[START] Market*/
            if(!empty($enabledFilters) && is_array($enabledFilters) && in_array('market-selection', $enabledFilters)){
                $objRichText = '';
                if(count($marketFilterData)>0){
                    $lstV = count($marketFilterData);
                    foreach ($marketFilterData as $kIds => $Val) {
                        if($lstV == $i)
                            $Val = $Val;
                        else    
                            $Val = $Val."\n";
                        $objRichText .= $kIds.' : '.$Val;
                    }
                }else{
                    $objRichText = 'All';
                }
                $objRichText = trim($objRichText, "\n");
                $appliedFilters[] =  'Market Selection##'.$objRichText;
            }
            /*[END] Market*/

            /*[START] Measure*/
            if(isset($_REQUEST['ValueVolume']) && !empty($_REQUEST['ValueVolume']) && !empty($enabledFilters) && is_array($enabledFilters) && in_array('measure-selection', $enabledFilters)){
                $measNameArr = [];
                $measureVal = $_REQUEST['ValueVolume'];
                $key = array_search($measureVal, array_column($this->settingVars->pageArray["MEASURE_SELECTION_LIST"], 'measureID'));
                $measNameArr[] = (isset( $this->settingVars->pageArray["MEASURE_SELECTION_LIST"][$key])) ? $this->settingVars->pageArray["MEASURE_SELECTION_LIST"][$key]['measureName'] : '';
                $appliedFilters[] =  'Measure Selection##'.implode(',', $measNameArr);
            }
            /*[END] Measure*/
        /*[END] ADDING THE FILTERS AT THE TOP */
        $appliedFiltersTxt = implode('$$', $appliedFilters);

        if (isset($_REQUEST['exportDataHash']) && !empty($_REQUEST['exportDataHash']))
            $dataHash = trim($_REQUEST['exportDataHash']);

        $redisOutput = $this->redisCache->checkAndReadByStaticHashFromCache($dataHash);
        if ($redisOutput === false) {
            $this->jsonOutput['exportDataFailMsg'] = 'No data belong to this hash.';
        } else {
            //print_r($redisOutput);
            /*echo '/usr/bin/perl '.dirname(__FILE__).'/../batch/FactoryShipmentOverview.pl "'.$filePath.'" "'.$dataHash.'" "'.$imgLogoPath.'" "'.$projectID.'" "'.$RedisServer.'" "'.$RedisPassword.'" "'.$appliedFiltersTxt.'" "'.$pageName.'"';
            exit;*/
            $result = shell_exec('/usr/bin/perl '.dirname(__FILE__).'/../batch/FactoryShipmentOverview.pl "'.$filePath.'" "'.$dataHash.'" "'.$imgLogoPath.'" "'.$projectID.'" "'.$RedisServer.'" "'.$RedisPassword.'" "'.$appliedFiltersTxt.'" "'.$pageName.'"');

            $this->jsonOutput['fileName'] = $this->settingVars->get_full_url()."/".basename(dirname(dirname(__FILE__)))."/uploads/Factory-Shipment-Overview/".$fileName;
        }
    }

    public function preparePageLevelSelectedMeasure($accountField = array()) {

        $measureColumns = $measureExtraFields = $this->measureFields = $this->mappingMeasure = array();
        $selectedValueVolume = 1;
        if(isset($_REQUEST['ValueVolume']) && !empty($_REQUEST['ValueVolume'])){
            $selectedValueVolume = $_REQUEST['ValueVolume'];
        }
        $this->mappingMeasure = $this->settingVars->measureArrayMapping[$selectedValueVolume];

        /*foreach ($this->mappingMeasure as $ky => $val) {
            $measureColumns[] = $this->settingVars->measureArray['M'.$val]['ALIASE'];
            $measureColumnsNames[$this->settingVars->measureArray['M'.$val]['ALIASE']] = $this->settingVars->measureArray['M'.$val]['NAME'];

            $this->measureFields[] = " SUM((CASE WHEN  ".filters\timeFilter::$tyWeekRange." THEN 1 ELSE 0 END) * IFNULL(".$this->settingVars->measureArray['M'.$val]['VAL'].",0)) AS TY_".$this->settingVars->measureArray['M'.$val]['ALIASE'];
            $this->measureFields[] = " SUM((CASE WHEN  ".filters\timeFilter::$lyWeekRange." THEN 1 ELSE 0 END) * IFNULL(".$this->settingVars->measureArray['M'.$val]['VAL'].",0)) AS LY_".$this->settingVars->measureArray['M'.$val]['ALIASE'];

            if(isset($this->settingVars->measureArray['M'.$val]['usedFields']))
                $measureExtraFields = array_merge($measureExtraFields,$this->settingVars->measureArray['M'.$val]['usedFields']);
        }*/

        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        
        $projectStructureTypePage = "projectstructure\\".$this->settingVars->projectStructureType."_PAGES";
        $structurePageClass = new $projectStructureTypePage();
        $measureSelectRes = $structurePageClass->factoryShipmentPrepareMeasureSelectPart($this->settingVars, $this->queryVars, $this->mappingMeasure);
        $measureExtraFields = (isset($measureSelectRes['measureFields'])) ? $measureSelectRes['measureFields'] : [];
        $this->measureFields = (isset($measureSelectRes['measureSelectionArr'])) ? $measureSelectRes['measureSelectionArr'] : [];

        if(count($accountField) > 0)
            $measureExtraFields = array_merge($accountField, $measureExtraFields);
        
        if (count($measureExtraFields) > 0)
            $this->prepareTablesUsedForQuery(array_unique($measureExtraFields));

        $this->settingVars->useRequiredTablesOnly = true;
        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll

        $this->jsonOutput['measureColumnsNamesMapping'] = (isset($measureSelectRes['measureColumnsNames'])) ? $measureSelectRes['measureColumnsNames'] : [];
        return (isset($measureSelectRes['measureColumns'])) ? $measureSelectRes['measureColumns'] : [];
    }
    
    public function buildPageArray() {
        $accountFieldPart = explode("#", $this->productBaseAccountField);
        $fetchConfig = false;

        //Update page level measure
        $this->settingVars->prepareMeasureForFactoryShipmentOverview();

        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
            $fetchConfig = true;
            $this->jsonOutput['pageConfig'] = array(
                'enabledFilters' => $this->getPageConfiguration('filter_settings', $this->settingVars->pageID)
            );
            $this->jsonOutput['gridColumns']['ACCOUNT'] = $this->displayCsvNameArray[$accountFieldPart[0]];
            $this->jsonOutput['measureSelectionList'] = $this->settingVars->pageArray["MEASURE_SELECTION_LIST"];
        }

        $accountField = strtoupper($this->dbColumnsArray[$accountFieldPart[0]]);
        $this->accountField = $this->settingVars->dataArray[$accountField]['NAME'];
        $this->accountName = $this->settingVars->dataArray[$accountField]['NAME_CSV'];
        return;
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
}
?>