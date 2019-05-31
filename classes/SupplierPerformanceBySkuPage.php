<?php

namespace classes;

use projectsettings;
use datahelper;
use filters;
use config;
use utils;
use db;
use lib;

class SupplierPerformanceBySkuPage extends config\UlConfig {

    public $dbColumnsArray;
    public $displayCsvNameArray;
    public $redisCache;

    public function __construct() {
        $this->jsonOutput = array();
    }

    /*****
     * Default gateway function, should be similar in all DATA CLASS
     * arguments:
     *  $settingVars [project settingsGateway variables]
     * @return $jsonOutput with all data that should be sent to client app
    *****/
    public function go($settingVars) {
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES
        $this->redisCache = new utils\RedisCache($this->queryVars);

        if ($this->settingVars->isDynamicPage) {
            $this->tableField = $this->getPageConfiguration('table_field', $this->settingVars->pageID)[0];
            $this->defaultSelectedField = $this->getPageConfiguration('table_settings_default_field', $this->settingVars->pageID)[0];

            if($this->defaultSelectedField)
                $tempBuildFieldsArray[] = $this->defaultSelectedField;

            $buildDataFields = array();
            foreach ($tempBuildFieldsArray as $value){
                if (!empty($value) && !in_array($value, $buildDataFields))
                    $buildDataFields[] = $value;
            }

            $this->buildDataArray($buildDataFields);
            $this->buildPageArray();
        } else {
            $message = "Page isn't configured properly.";
            $response = array("configuration" => array("status" => "fail", "messages" => array($message)));
            echo json_encode($response);
            exit();
        }

        $action = $_REQUEST["action"];
        switch ($action) {
            case "fetchData":
                $this->chartData();
                break;
        }
        return $this->jsonOutput;
    }

    public function buildPageArray() {
        $fetchConfig = false;
        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
            $fetchConfig = true;
            $this->jsonOutput['pageConfig'] = array(
                'enabledFilters' => $this->getPageConfiguration('filter_settings', $this->settingVars->pageID)
            );

            $this->jsonOutput['defaultSelectedField'] = '';
            $this->jsonOutput['tableField'] = ($this->tableField !== "") ? $this->tableField : $this->settingVars->skutable;
            $this->jsonOutput['defaultSelectedField'] = ($this->defaultSelectedField !== "") ? strtoupper($this->settingVars->dataArray[strtoupper($this->dbColumnsArray[$this->defaultSelectedField])]['NAME']) : "";
            /*if($this->defaultSelectedField !== ""){
                $dbClmNameVars = strtolower($this->dbColumnsArray[$this->defaultSelectedField]);
                if(isset($this->queryVars->projectConfiguration) && isset($this->queryVars->projectConfiguration['product_settings'])){
                    $product_settings_db_flds = explode('|', $this->queryVars->projectConfiguration['product_settings']);
                    if(!empty($product_settings_db_flds) && count($product_settings_db_flds)>0){
                        foreach ($product_settings_db_flds as $k => $flds) {
                            $fld = explode('#', $flds);
                            $cmpnm = strtolower($this->tableField.'.'.$fld[0]);
                            if($cmpnm == $dbClmNameVars){
                                $this->defaultSelectedField = $this->tableField.'.'.$fld[0];
                                if(isset($fld[1]) && !empty($fld[1]))
                                    $this->defaultSelectedField .='#'.$this->tableField.'.'.$fld[1];
                            }
                        }
                    }
                }
            //$this->jsonOutput['defaultSelectedField'] = $this->settingVars->dataArray[strtoupper($this->defaultSelectedField)]['NAME'];
            $this->jsonOutput['defaultSelectedField'] = $this->defaultSelectedField;
            }*/
            $this->jsonOutput['pageType'] = 'Supplier Performance by Sku';
            $tables = ($this->tableField !== "") ? $this->tableField : $this->settingVars->skutable;
            $this->prepareFieldsFromFieldSelectionSettings([$tables]);
        }
        return;
    }

    public function chartData() {
        $this->settingVars->dateField = '';
        $this->settingVars->yearperiod = $this->settingVars->ferreroTescoSbaTable.'.year';
        $this->settingVars->weekperiod = $this->settingVars->ferreroTescoSbaTable.'.week';
        $this->settingVars->timeHelperTables = $this->settingVars->ferreroTescoSbaTable;
        $this->settingVars->timeHelperLink = ' WHERE accountID = '.$this->settingVars->aid.' AND GID = '.$this->settingVars->GID.' ';
        filters\timeFilter::getTimeFrame(12, $this->settingVars);
        $tyWeekRange = filters\timeFilter::$tyWeekRange;
        //$lyWeekRange = filters\timeFilter::$lyWeekRange;

        $custom_where = '';
        if(isset($_REQUEST['selectedField']) && $_REQUEST['selectedField'] != '' && isset($_REQUEST['itemSelectionField']) && $_REQUEST['itemSelectionField'] != ''){
            $fldNm = explode('#', $_REQUEST['selectedField']);
            if(count($fldNm) > 1)
                $selectedFld = $fldNm[1];
            else
                $selectedFld = $fldNm[0];
            $custom_where = " AND ".$selectedFld." = '".$_REQUEST['itemSelectionField']."' ";
        }

        $this->queryPart = $this->getAll();
        $query = "SELECT ".$this->settingVars->ferreroTescoSbaTable.".week as WEEK, ".
                 $this->settingVars->ferreroTescoSbaTable.".year as YEAR, ".
            " SUM(".$this->settingVars->ferreroTescoSbaTable.".sba_gaps) AS SBA_GAPS, ". 
            " SUM(".$this->settingVars->ferreroTescoSbaTable.".sba_ranged) AS SBA_RANGED,". 
            " SUM(".$this->settingVars->ferreroTescoSbaTable.".lost_sales) AS LOST_SALES,". 
            " SUM(".$this->settingVars->ferreroTescoSbaTable.".sales_singles) AS SALES_SINGLES,". 
            " SUM(".$this->settingVars->ferreroTescoSbaTable.".dotcom_picked) AS DOTCOM_PICKED,". 
            " SUM(".$this->settingVars->ferreroTescoSbaTable.".dotcom_ordered) AS DOTCOM_ORDERED,". 
            " SUM(".$this->settingVars->ferreroTescoSbaTable.".not_available_gaps) AS NOT_AVAILABLE_GAPS ". 
            " FROM ".$this->settingVars->ferreroTescoSbaTable .",".$this->settingVars->skutable.
            " WHERE ".$this->settingVars->ferreroTescoSbaTable.".accountID = ".$this->settingVars->aid.
                    " AND ".$this->settingVars->ferreroTescoSbaTable.".GID = ".$this->settingVars->GID.
                    " AND ".$this->settingVars->ferreroTescoSbaTable.".PIN = ".$this->settingVars->skutable.".PIN ".
                    " AND ".$this->settingVars->skutable.".clientID = '".$this->settingVars->clientID."' AND ".$this->settingVars->skutable.".GID = ".$this->settingVars->GID.' '.$this->queryPart.' AND '.$tyWeekRange.$custom_where.
            " GROUP BY WEEK,YEAR ORDER BY WEEK ASC, YEAR DESC";

        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }

        $resultData = $value = array();
        if (is_array($result) && !empty($result)){
            foreach ($result as $data) {
                $value = array();
                $value['FORMATED_DATE'] = $data['WEEK'].'-'.$data['YEAR'];
                /*[SBA % CALCULATION]*/
                $value['SBA_GAPS']       = ($data['SBA_GAPS'] * 1);
                $value['SBA_RANGED']     = ($data['SBA_RANGED'] * 1);
                if ($value['SBA_RANGED']!='' && $value['SBA_RANGED']!=0)
                        $value['SBA_PERCENTAGE'] = (1 - ($value['SBA_GAPS'] / $value['SBA_RANGED'])) * 100;
                    else
                        $value['SBA_PERCENTAGE'] = 100;

                /*[CUSTOMER SERVICE % CALCULATION]*/
                $value['LOST_SALES']     = ($data['LOST_SALES'] * 1);
                $value['SALES_SINGLES']  = ($data['SALES_SINGLES'] * 1);
                $acSum = ($value['LOST_SALES'] + $value['SALES_SINGLES']);
                    if ($acSum!=0)
                        $value['CUSTOMER_SERVICE_PERCENTAGE'] = (1 - ($value['LOST_SALES'] / $acSum)) * 100;
                    else
                        $value['CUSTOMER_SERVICE_PERCENTAGE'] = 100;

                /*[DOTCOM_SERVICE_LEVEL % CALCULATION]*/
                $value['DOTCOM_PICKED']  = ($data['DOTCOM_PICKED'] * 1);
                $value['DOTCOM_ORDERED'] = ($data['DOTCOM_ORDERED'] * 1);
                if ($value['DOTCOM_ORDERED']!='' && $value['DOTCOM_ORDERED']!=0)
                    $value['DOTCOM_SERVICE_LEVEL_PERCENTAGE'] = ($value['DOTCOM_PICKED'] / $value['DOTCOM_ORDERED']) * 100;
                else
                    $value['DOTCOM_SERVICE_LEVEL_PERCENTAGE'] = 100;

                $value['NOT_AVAILABLE_GAPS'] = ($data['NOT_AVAILABLE_GAPS'] * 1);
                $resultData[] = $value;
            }

            $chartData['chart1']['WEEK'] = array_column($resultData, 'FORMATED_DATE');
            $chartData['chart1']['SALES_SINGLES'] = array_column($resultData, 'SALES_SINGLES');
            $chartData['chart1']['SBA_PERCENTAGE'] = array_column($resultData, 'SBA_PERCENTAGE');
            $chartData['chart1']['CUSTOMER_SERVICE_PERCENTAGE'] = array_column($resultData, 'CUSTOMER_SERVICE_PERCENTAGE');

            $chartData['chart2']['WEEK'] = array_column($resultData, 'FORMATED_DATE');
            $chartData['chart2']['DOTCOM_ORDERED'] = array_column($resultData, 'DOTCOM_ORDERED');
            $chartData['chart2']['DOTCOM_PICKED'] = array_column($resultData, 'DOTCOM_PICKED');
            $chartData['chart2']['DOTCOM_SERVICE_LEVEL_PERCENTAGE'] = array_column($resultData, 'DOTCOM_SERVICE_LEVEL_PERCENTAGE');

            $chartData['chart3']['WEEK'] = array_column($resultData, 'FORMATED_DATE');
            $chartData['chart3']['NOT_AVAILABLE_GAPS'] = array_column($resultData, 'NOT_AVAILABLE_GAPS');
        }
        $this->jsonOutput['chartData'] = $chartData;
    }

    public function getall(){
        $tablejoins_and_filters = '';
        $this->productFilterData = $this->marketFilterData = [];
        if (isset($_REQUEST["FS"]) && $_REQUEST["FS"] != ''){
            $tablejoins_and_filters   .= filters\productAndMarketFilter::include_product_and_market_filters($this->queryVars,$this->settingVars);
            foreach($_REQUEST['FS'] as $key=>$data){
                if(!empty($data) && isset($this->settingVars->dataArray[$key])){
                        $filterKey      = !key_exists('ID',$this->settingVars->dataArray[$key]) ? $this->settingVars->dataArray[$key]['NAME'] : $settingVars->dataArray[$key]['ID'];
                        if($filterKey=="CLUSTER") {
                            $this->settingVars->tablename    = $this->settingVars->tablename.",".$this->settingVars->clustertable;
                            $tablejoins_and_filters .= " AND ".$this->settingVars->maintable.".PIN=".$this->settingVars->clustertable.".PIN ";
                        }
                        
                        $dataList = $data;
                        if(isset($this->settingVars->dataArray[$key]['ID'])) {
                            $dataList = $this->getAllDataFromIds($this->settingVars->dataArray[$key]['TYPE'],$key,$dataList);
                        }
                        if($this->settingVars->dataArray[$key]['TYPE'] == 'P') {
                            $this->productFilterData[$this->settingVars->dataArray[$key]['NAME_CSV']] = urldecode($dataList);
                        }else if($this->settingVars->dataArray[$key]['TYPE'] == 'M') {
                            $this->marketFilterData[$this->settingVars->dataArray[$key]['NAME_CSV']] = urldecode($dataList);
                        }
                }
            }
        }
    return $tablejoins_and_filters;
    }

    public function getAllDataFromIds($type,$id,$data){
        $redisCache = new utils\RedisCache($this->queryVars);
        $redisOutput = $redisCache->checkAndReadByStaticHashFromCache('productAndMarketSelectionTabsRedisList');
        if ($redisOutput === false) {
            return $data;
        } else {
            
            $selectionTabsRedisList = $redisOutput[$type];
            if(!is_array($selectionTabsRedisList) || empty($selectionTabsRedisList))
                 return $data;

            if($type == 'FSG')
            {
                $storeList = array_column($selectionTabsRedisList, 'data');
                $Skey = array_search($data, $storeList);
                if(is_numeric($Skey))
                {
                    $data = $selectionTabsRedisList[$Skey]['label'];
                }
            }
            else
            {
                $aKey = array_search($id, array_column($selectionTabsRedisList, 'data'));
                if(isset($selectionTabsRedisList[$aKey]) && isset($selectionTabsRedisList[$aKey]['dataList']) && is_array($selectionTabsRedisList[$aKey]['dataList']) && count($selectionTabsRedisList[$aKey]['dataList'])>0){

                    $mainArr = array_column($selectionTabsRedisList[$aKey]['dataList'], 'label','data');
                    $fndata = [];
                    $data = explode(',', $data);
                    if(is_array($data) && count($data)>0){
                        foreach ($data as $k => $vl) {
                            if(isset($mainArr[$vl]) && !empty($mainArr[$vl]))
                                $fndata[] = $mainArr[$vl];
                            else
                                $fndata[] = $vl;
                        }
                        $data = implode(',', $fndata);
                    }
                }
            }
            return $data;
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
}
?>