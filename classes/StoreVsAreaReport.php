<?php

namespace classes;

use projectsettings;
use datahelper;
use filters;
use db;
use config;

class StoreVsAreaReport extends config\UlConfig {

    private $queryPartForArea;
    private $accountFieldID;
    private $accountField;
    private $accountFilterField;
    private $accountFilterTable;
    private $areaField;
    private $productField;
    private $areaArray;
    private $pageName;

    /*     * ***
     * Default gateway function, should be similar in all DATA CLASS
     * arguments:
     *  $settingVars [project settingsGateway variables]
     * @return $jsonOutput with all data that should be sent to client app
     * *** */

    public function go($settingVars) {
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES

        $this->settingVars->pageName = (empty($this->settingVars->pageName)) ? $this->settingVars->pageID . '_AreaReportPage' : $this->settingVars->pageName;

        $this->redisCache = new \utils\RedisCache($this->queryVars);

        if ($this->settingVars->isDynamicPage) {
            $this->areaField = $this->getPageConfiguration('area_field', $this->settingVars->pageID)[0];
            $this->topGridField = $this->getPageConfiguration('top_grid_field', $this->settingVars->pageID)[0];
            $this->bottomGridField = $this->getPageConfiguration('bottom_grid_field', $this->settingVars->pageID)[0];
            $this->buildDataArray(array($this->areaField, $this->topGridField, $this->bottomGridField));
            $this->buildPageArray();
        } else {
            if (!isset($this->settingVars->pageArray[$this->settingVars->pageName]) || empty($this->settingVars->pageArray[$this->settingVars->pageName]))
                $this->configurationFailureMessage();

            $this->accountFieldID = (isset($this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['ACCOUNT_FIELD']]) && isset($this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['ACCOUNT_FIELD']]['ID'])) ? $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['ACCOUNT_FIELD']]['ID'] : $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['ACCOUNT_FIELD']]['NAME'];
            $this->accountField = $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['ACCOUNT_FIELD']]['NAME'];
            $this->accountFilterField = $this->accountFieldID;
            $this->accountFilterTable = $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['ACCOUNT_FIELD']]['tablename'];
            $this->accountFilterLink = $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['ACCOUNT_FIELD']]['link'];
            $this->areaField = (isset($this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['AREA_FIELD']]) && isset($this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['AREA_FIELD']]['ID'])) ? $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['AREA_FIELD']]['ID'] : $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['AREA_FIELD']]['NAME'];
            $this->productFieldID = (isset($this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['PRODUCT_FIELD']]) && isset($this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['PRODUCT_FIELD']]['ID'])) ? $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['PRODUCT_FIELD']]['ID'] : $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['PRODUCT_FIELD']]['NAME'];
            $this->productField = $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['PRODUCT_FIELD']]['NAME'];
        }

        $this->queryPart = $this->getAll();
        $this->queryPartForArea = $this->getAll_forArea();

        $action = $_REQUEST["action"];
        switch ($action) {
            case "reload": return $this->reload();
                break;
            case "storechange": return $this->changeStore();
                break;
        }
    }

    private function reload() {
        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true'){
            $this->jsonOutput['pageConfig']['enabledFilters'] = $this->getPageConfiguration('filter_settings', $this->settingVars->pageID);
        }else{
            $this->getAreaData();
            $this->storeGridData(); //adding to output data
        }
        return $this->jsonOutput;
    }

    private function changeStore() {
        $this->getAreaData('ForSku');
        $this->skuGridData(); //adding to output data

        return $this->jsonOutput;
    }

    private function storeGridData() {
        if($this->accountField == $this->accountFieldID){
            $arrFields = [$this->accountField=>'ACCOUNT'];
            $gropbyFields = 'ACCOUNT';
        }else{
            $arrFields = [$this->accountFieldID=>'ID',$this->accountField=>'ACCOUNT'];
            $gropbyFields = 'ID,ACCOUNT';
        }
        list($result,$store_area_sum_ty,$store_area_sum_ly) = $this->buildMeasureFilters($arrFields,$gropbyFields,0);
        $tempResult = array();
        if(isset($result) && is_array($result) && count($result)>0){
            foreach ($result as $key => $data) {
                $storeVariance  = $data[$store_area_sum_ty] - $data[$store_area_sum_ly];
                $storeVarPct    = $data[$store_area_sum_ly] > 0 ? ($storeVariance / $data[$store_area_sum_ly]) * 100 : 0;

                if($this->accountField == $this->accountFieldID){
                    $data['ID'] =  $data['ACCOUNT'];
                }

                $AREA = $this->areaArray['IDs'][$data['ID']];
                $area_sum_ty = $this->areaArray[$AREA]['TY'];
                $area_sum_ly = $this->areaArray[$AREA]['LY'];

                $bannRegVariance = $area_sum_ty - $area_sum_ly;
                $bannRegVariancePct = $area_sum_ly > 0 ? ($bannRegVariance / $area_sum_ly) * 100 : 0.0;
                $VAR_PCT = number_format($bannRegVariancePct, 1, ".", ",");
                $overUnder = $storeVarPct - $VAR_PCT;

                //$storeVarPct <= 100.00 && $VAR_PCT <= 100.00 && 
                if ($data[$store_area_sum_ty] > 0) {
                    $temp = array();
                    $temp['ID'] = htmlspecialchars_decode($data['ID']);
                    $temp['ACCOUNT'] = htmlspecialchars_decode($data['ACCOUNT']);
                    $temp['AREA'] = htmlspecialchars_decode($AREA);
                    $temp['STORE_SUM'] = $data[$store_area_sum_ty];
                    $temp['STORE_VAR_PCT'] = number_format($storeVarPct, 1, ".", ",");
                    $temp['AREA_VAR_PCT'] = $VAR_PCT;
                    $temp['OVER_UNDER'] = number_format($overUnder, 1, ".", "");
                    $tempResult[] = $temp;
                }
            }
        }
        $this->jsonOutput['storeGridData'] = $tempResult;
    }

    private function getAreaData($fnData = '') {
        if($fnData=='ForSku'){
            $fldArr = [$this->areaField=>'ID',$this->productFieldID=>'ACCOUNT'];
        }else{
            $fldArr = [$this->accountFieldID=>'ID',$this->areaField=>'ACCOUNT'];
        }
        
        list($result,$area_sum_ty,$area_sum_ly) = $this->buildMeasureFilters($fldArr,'ID,ACCOUNT',1);
        $this->areaArray = array();
        if(isset($result) && is_array($result) && count($result)>0){
            foreach ($result as $key => $data) {
                $this->areaArray['IDs']['AREA'] = $data['ID'];
                $this->areaArray['IDs'][$data['ID']] = $data['ACCOUNT'];
                $this->areaArray[$data['ACCOUNT']]['TY'] += $data[$area_sum_ty];
                $this->areaArray[$data['ACCOUNT']]['LY'] += $data[$area_sum_ly];
            }
        }
    }

    private function skuGridData() {
        if($this->productField == $this->productFieldID){
            $arrFields = [$this->productFieldID=>'ACCOUNT'];
            $gropbyFields = 'ACCOUNT';
        }else{
            $arrFields = [$this->productFieldID=>'ID',$this->productField=>'ACCOUNT'];
            $gropbyFields = 'ID,ACCOUNT';
        }
        list($result,$store_area_sum_ty,$store_area_sum_ly) = $this->buildMeasureFilters($arrFields,$gropbyFields,0);
        $tempResult = array();
        if(isset($result) && is_array($result) && count($result)>0){
            foreach ($result as $key => $data) {
                $skuVariance = $data[$store_area_sum_ty] - $data[$store_area_sum_ly];
                $skuVarPct = $data[$store_area_sum_ly] > 0 ? ($skuVariance / $data[$store_area_sum_ly]) * 100 : 0;

                if($this->productField == $this->productFieldID){
                    $data['ID'] =  $data['ACCOUNT'];
                }

                $AREA = $this->areaArray['IDs']['AREA'];
                $area_sum_ty = $this->areaArray[$data['ID']]['TY'];
                $area_sum_ly = $this->areaArray[$data['ID']]['LY'];

                $skuVariance = $area_sum_ty - $area_sum_ly;
                $skuAreaVarPct = $area_sum_ly > 0 ? ($skuVariance / $area_sum_ly) * 100 : 0.0;
                $VAR_PCT = number_format($skuAreaVarPct, 1, ".", ",");
                $overUnder = $skuVarPct - $VAR_PCT;

                if ($data[$store_area_sum_ty] > 0) {
                    $temp = array();
                    $temp['SKUID'] = $data['ID'];
                    $temp['SKU'] = htmlspecialchars_decode($data['ACCOUNT']);
                    $temp['AREA'] = htmlspecialchars_decode($AREA);
                    $temp['STORE_SUM'] = $data[$store_area_sum_ty];
                    $temp['STORE_VAR_PCT'] = number_format($skuVarPct, 1, ".", ",");
                    $temp['AREA_VAR_PCT'] = $VAR_PCT;
                    $temp['OVER_UNDER'] = number_format($overUnder, 1, ".", "");
                    $tempResult[] = $temp;
                }
            }
        }
        $this->jsonOutput['skuGridData'] = $tempResult;
    }

    /*     * ***
     * COLLECTS TABLE JOINING STRING FROM PROJECT SETTINGS
     * ALSO APPLYS FILTER CONDITIONS TO THE STRING
     * AND RETURNS $tablejoins_and_filters
     * *** */

    public function getAll() {
        // $tablejoins_and_filters = parent::getAll();
        $tablejoins_and_filters = "";

        if ($_REQUEST['ACCOUNT'] != "") {
            $extraFields[] = $this->accountFieldID;
            $tablejoins_and_filters.= " AND " . $this->accountFieldID . "='" . $_REQUEST['ACCOUNT'] . "' ";
        }

        $this->prepareTablesUsedForQuery($extraFields);
        $tablejoins_and_filters1 = parent::getAll();
        $tablejoins_and_filters1 .= $tablejoins_and_filters;

        return $tablejoins_and_filters1;
    }

    private function getAll_forArea() {
        $tablejoins_and_filters = ""; //$this->settingVars->link;
        if ($_REQUEST['ACCOUNT'] != "") {
            //$tablejoins_and_filters.= " AND " . $this->areaField . " IN(SELECT " . $this->areaField . " FROM " . $this->accountFilterTable . $this->accountFilterLink . " AND " . $this->accountFilterField . " = '" . $_REQUEST['ACCOUNT'] . "') ";
            $tablejoins_and_filters.= " AND " . $this->areaField . " = '" .$_REQUEST['AREA']."'";
        }

        $this->prepareTablesUsedForQuery($extraFields);
        $tablejoins_and_filters1 = parent::getAll();
        $tablejoins_and_filters1 .= $tablejoins_and_filters;        
        
        return $tablejoins_and_filters1;
    }

    public function buildPageArray() {
        $areaFieldPart = explode("#", $this->areaField);
        $areaField = strtoupper($this->dbColumnsArray[$areaFieldPart[0]]);
        $areaField = (count($areaFieldPart) > 1) ? strtoupper($areaField . "_" . $this->dbColumnsArray[$areaFieldPart[1]]) : $areaField;

        $this->areaField = (isset($this->settingVars->dataArray[$areaField]) && isset($this->settingVars->dataArray[$areaField]['ID'])) ? $this->settingVars->dataArray[$areaField]['ID'] : $this->settingVars->dataArray[$areaField]['NAME'];

        $topGridFieldPart = explode("#", $this->topGridField);
        $topGridField = strtoupper($this->dbColumnsArray[$topGridFieldPart[0]]);
        $topGridField = (count($topGridFieldPart) > 1) ? strtoupper($topGridField . "_" . $this->dbColumnsArray[$topGridFieldPart[1]]) : $topGridField;

        $this->accountFieldID = (isset($this->settingVars->dataArray[$topGridField]) && isset($this->settingVars->dataArray[$topGridField]['ID'])) ? $this->settingVars->dataArray[$topGridField]['ID'] : $this->settingVars->dataArray[$topGridField]['NAME'];
        $this->accountField = $this->settingVars->dataArray[$topGridField]['NAME'];

        $this->accountFilterField = $this->accountFieldID;
        $this->accountFilterTable = $this->settingVars->dataArray[$topGridField]['tablename'];
        $this->accountFilterLink = $this->settingVars->dataArray[$topGridField]['link'];

        $bottomGridFieldPart = explode("#", $this->bottomGridField);
        $bottomGridField = strtoupper($this->dbColumnsArray[$bottomGridFieldPart[0]]);
        $bottomGridField = (count($bottomGridFieldPart) > 1) ? strtoupper($bottomGridField . "_" . $this->dbColumnsArray[$bottomGridFieldPart[1]]) : $bottomGridField;

        $this->productFieldID = (isset($this->settingVars->dataArray[$bottomGridField]) && isset($this->settingVars->dataArray[$bottomGridField]['ID'])) ? $this->settingVars->dataArray[$bottomGridField]['ID'] : $this->settingVars->dataArray[$bottomGridField]['NAME'];
        $this->productField = $this->settingVars->dataArray[$bottomGridField]['NAME'];

        return;
    }

    public function buildDataArray($fields) {

        if (empty($fields))
            return false;

        $configurationCheck = new config\ConfigurationCheck($this->settingVars, $this->queryVars);
        $configurationCheck->buildDataArray($fields);
        $this->dbColumnsArray = $configurationCheck->dbColumnsArray;
        return;
    }

    public function buildMeasureFilters($customFields = array(),$groupByFld,$is_area_query = 0){
        /*[START] Getting common cached MEASURES query from the Redis*/
            $this->settingVars->tableUsedForQuery = $this->measureFields = array();
            $measureSelectRes = $this->prepareMeasureSelectPart();
            $this->measureFields = $measureSelectRes['measureFields'];
            $this->measureFields = array_merge($this->measureFields,array_keys($customFields));
            
            $this->prepareTablesUsedForQuery($this->measureFields);
            $this->settingVars->useRequiredTablesOnly = true;
            if($is_area_query == 1)
                $this->queryPart = $this->getAll_forArea(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll
            else    
                $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll

            $measureSelectionArr = $measureSelectRes['measureSelectionArr'];
            $havingTYValue = $measureSelectRes['havingTYValue'];
            $havingLYValue = $measureSelectRes['havingLYValue'];

            $result = [];
            if(count($customFields)>0){
                $strSelect = ''; $allFieldsAlias = [];
                foreach ($customFields as $field => $alias) {
                    $strSelect .= $field.' AS '.$alias.',';
                    $allFieldsAlias[] = $alias;
                }

                $queryPart = trim($this->queryPart);
                //if($is_area_query == 1){
                //    $queryPart .= ' '.trim($this->queryPartForArea);
                //}

                $query = "SELECT ".$strSelect." " .implode(",", $measureSelectionArr).
                    " FROM " . $this->settingVars->tablename .' '.$queryPart.
                    " AND (" . filters\timeFilter::$tyWeekRange . " OR " . filters\timeFilter::$lyWeekRange . ") " .
                    "GROUP BY ".$groupByFld;

                $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
                if ($redisOutput === false) {
                    $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
                    $this->redisCache->setDataForHash($result);
                } else {
                    $result = $redisOutput;
                }

                $requiredGridFields = array_merge($allFieldsAlias,array($havingTYValue, $havingLYValue));
                $result = $this->redisCache->getRequiredData($result, $requiredGridFields, $havingTYValue, $havingTYValue, $havingLYValue);
            }
            return array($result,$havingTYValue,$havingLYValue);
        /*[END] Getting common cached MEASURES query from the Redis*/
    }
}

?> 