<?php

namespace classes;

use datahelper;
use projectsettings;
use filters;
use utils;
use db;
use config;
use lib;

class CategorySubCategorySankey extends config\UlConfig {

    public $measureFields = array();
    /**
     * Default gateway function, should be similar in all DATA CLASS
     * 
     * @param array $settingVars project settingsGateway variables
     *
     * @return Array
     */
    public function go($settingVars) {
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES
        $this->queryPart = $this->getAll();

        $this->redisCache = new utils\RedisCache($this->queryVars);

        /*$this->gridTimeSelectionUnit = isset($this->settingVars->timeSelectionUnit) && $this->settingVars->timeSelectionUnit == 'weekMonth' ? 'Month' : 'Week';*/
        $this->settingVars->pageName = (empty($this->settingVars->pageName) && !empty($this->settingVars->pageID)) ? $this->settingVars->pageID.'_CategorySubCategorySankeyPage' : $this->settingVars->pageName;
        if ($this->settingVars->isDynamicPage) {
            $this->categoryField = $this->getPageConfiguration('category_field', $this->settingVars->pageID)[0];
            $this->subCategoryField = $this->getPageConfiguration('sub_category_field', $this->settingVars->pageID)[0];
            $this->measureFilterSettings = $this->getPageConfiguration('measure_filter_settings', $this->settingVars->pageID);

            if (empty($this->measureFilterSettings)) {
                $this->measureFilterSettings = array_column($this->settingVars->pageArray["MEASURE_SELECTION_LIST"], 'measureID');
            }

            $fieldArray = array($this->categoryField,$this->subCategoryField);
            $this->buildDataArray($fieldArray);
            $this->buildPageArray();
        } else {
            $this->configurationFailureMessage();
        }

        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
            $this->getGridColumnsConfig();
        }

        $action = $_REQUEST['action'];
        switch ($action) {
            case "getGridData":
            $this->getCategoryData();
            break;
        }
        return $this->jsonOutput;
    }

    public function getGridColumnsConfig(){
        $measureArr = $timeSelectionSettings = $measureFilterSettingsIds = array();
        if(is_array($this->measureFilterSettings) && !empty($this->measureFilterSettings)){
            foreach ($this->measureFilterSettings as  $mkey => $measureVal) {
                $key = array_search($measureVal, array_column($this->settingVars->pageArray["MEASURE_SELECTION_LIST"], 'measureID'));
                $measureName = (isset( $this->settingVars->pageArray["MEASURE_SELECTION_LIST"][$key])) ? $this->settingVars->pageArray["MEASURE_SELECTION_LIST"][$key]['measureName'] : '';
                $measureKey = 'M' . $measureVal;
                $measure = $this->settingVars->measureArray[$measureKey];
                $measureName = (!empty($measureName)) ? $measureName : $measure['ALIASE'];
                $measureArr[] = $measureName;
                $measureFilterSettingsIds[$measureName] = $measureVal;
            }
        }

        if(is_array($this->timeArray) && !empty($this->timeArray)){
            foreach ($this->timeArray as $timekey => $timeval) {
                $timeSelectionSettings[$timekey] = $timeval;
            }
        }

        $this->jsonOutput['measureFilterSettings'] = $measureArr;
        $this->jsonOutput['measureFilterSettingsIds'] = $measureFilterSettingsIds;
        $this->jsonOutput['timeSelectionSettings'] = $timeSelectionSettings;
    }

     /**
     * getCategoryData()
     * It will list all category 
     * 
     * @return void
     */
    private function getCategoryData() {

        filters\timeFilter::$lyWeekRange = '';
        
        $measureSelectRes = $this->prepareMeasureSelectPart();
        $this->measureFields = $measureSelectRes['measureFields'];
        $this->measureFields[] = $this->categoryName;
        $this->measureFields[] = $this->subCategoryName;

        $this->prepareTablesUsedForQuery($this->measureFields);
        $this->settingVars->useRequiredTablesOnly = true;
        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll

        $measureSelectionArr = $measureSelectRes['measureSelectionArr'];
        $havingTYValue = $measureSelectRes['havingTYValue'];
        $havingLYValue = $measureSelectRes['havingLYValue'];
        
        //. " OR " . filters\timeFilter::$lyWeekRange 
        $query = "SELECT $this->categoryName AS CAT, $this->subCategoryName AS SUB_CAT, " .implode(",", $measureSelectionArr).
            " FROM " . $this->settingVars->tablename .' '. trim($this->queryPart) .
            " AND (" . filters\timeFilter::$tyWeekRange . ") " .
            "GROUP BY CAT,SUB_CAT";

        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }

        $selMeasureNm = $this->settingVars->measureArray['M'.$_REQUEST['ValueVolume']]['ALIASE'];
        $requiredGridFields = array("CAT", "SUB_CAT", $havingTYValue);
        $result = $this->redisCache->getRequiredData($result, $requiredGridFields, $havingTYValue, $havingTYValue, $havingLYValue);

        $links = $nodes = [];
        $allCatSum = $allSubCatSum = [];

        foreach ($result as $key => $value) {
            $allCatSum[$value['CAT']] += $value[$havingTYValue];
            $allSubCatSum[$value['SUB_CAT']] += $value[$havingTYValue];
        }
        arsort($allCatSum);
        arsort($allSubCatSum);

        $nodesCat = $nodesCatAllOthers = [];
        if(count($allCatSum) > 5){
            $nodesCat = array_keys(array_slice($allCatSum,0,5));
            $nodesCatAllOthers = array_keys(array_slice($allCatSum,5));
        } else {
            $nodesCat = array_keys($allCatSum);
        }

        $nodesSubCat = $nodesSubCatAllOthers = [];
        if(count($allSubCatSum) > 5){
            $nodesSubCat = array_keys(array_slice($allSubCatSum,0,5));
            $nodesSubCatAllOthers = array_keys(array_slice($allSubCatSum,5));
        } else {
            $nodesSubCat = array_keys($allSubCatSum);
        }

        /*$nodesCat = array_values(array_unique(array_column($result, 'CAT')));
        $nodesSubCat = array_values(array_unique(array_column($result, 'SUB_CAT')));*/

        $nodesSubCat = "(SUB CAT) ".implode(',(SUB CAT) ', $nodesSubCat);
        $nodesSubCat = explode(',', $nodesSubCat);
        $nodesTmps = array_merge($nodesCat,$nodesSubCat);
        $nodesCatNames = array_flip($nodesTmps);
        foreach ($nodesTmps as $key => $value) {
            $nodes[] = ['name'=>$value];
        }

        $nodesCatAllOtherId = '';
        if (count($nodesCatAllOthers) > 0) {
            $nodesCatAllOtherId = count($nodes);
            $nodes[$nodesCatAllOtherId] = ['name'=>'All Others'];
        }

        $nodesSubCatAllOtherId = '';
        if (count($nodesSubCatAllOthers) > 0) {
            $nodesSubCatAllOtherId = count($nodes);
            $nodes[$nodesSubCatAllOtherId] = ['name'=>'All Others'];
        }

        $allOtherLinks = [];
        foreach ($result as $key => $value) {
            if (isset($nodesCatNames[$value['CAT']]) && isset($nodesCatNames['(SUB CAT) '.$value['SUB_CAT']])){
                $links[] = ['source'=>$nodesCatNames[$value['CAT']], 'target'=>$nodesCatNames['(SUB CAT) '.$value['SUB_CAT']], 'value'=>$value[$havingTYValue]];
            }
            else{
                $source = $nodesCatNames[$value['CAT']];
                if (in_array($value['CAT'], $nodesCatAllOthers))
                    $source = $nodesCatAllOtherId;
                
                $target = $nodesCatNames['(SUB CAT) '.$value['SUB_CAT']];
                if (in_array($value['SUB_CAT'], $nodesSubCatAllOthers))
                    $target = $nodesSubCatAllOtherId;

                $allOtherLinks[$source][$target] += $value[$havingTYValue];
            }
        }


        if(count($allOtherLinks) > 0){
            foreach ($allOtherLinks as $keySource => $valTarget) {
                foreach ($valTarget as $keyTarget => $valData) {
                    $links[] = ['source'=>$keySource, 'target'=>$keyTarget, 'value'=>$valData];
                }
            }
        }

        $this->jsonOutput['sankeyData'] = ['nodes'=>$nodes,'links'=>$links];
        //print_r($nodes);
        /*$this->jsonOutput['sankeyData'] = json_decode('{
            "nodes":[
            {"name":"Wealth Management"},
            {"name":"WMA"},
            {"name":"Retail/Corporate"},
            {"name":"GAM"},
            {"name":"Investment Bank"},
            {"name":"Americas"},
            {"name":"APAC"},
            {"name":"EMEA"},
            {"name":"Switzerland"}
        ],
        "links":[
            {"source":0,"target":5,"value":100},
            {"source":1,"target":5,"value":1800},
            {"source":2,"target":5,"value":0.1},
            {"source":3,"target":5,"value":200},
            {"source":4,"target":5,"value":800},
            {"source":0,"target":6,"value":600},
            {"source":1,"target":6,"value":0.1},
            {"source":2,"target":6,"value":0.1},
            {"source":3,"target":6,"value":100},
            {"source":4,"target":6,"value":700},
            {"source":0,"target":7,"value":1000},
            {"source":1,"target":7,"value":0.1},
            {"source":2,"target":7,"value":0.1},
            {"source":3,"target":7,"value":100},
            {"source":4,"target":7,"value":800},
            {"source":0,"target":8,"value":400},
            {"source":1,"target":8,"value":0.1},
            {"source":2,"target":8,"value":1000},
            {"source":3,"target":8,"value":100},
            {"source":4,"target":8,"value":400}
        ]}');*/
    }

    /**
     * getAll()
     * COLLECTS TABLE JOINING STRING FROM PROJECT SETTINGS
     * ALSO APPLYS FILTER CONDITIONS TO THE STRING
     * AND RETURNS $tablejoins_and_filters
     *
     * @return String
     */
    public function getAll() {
        $tablejoins_and_filters = parent::getAll();
        return $tablejoins_and_filters;
    }

    public function buildPageArray() {

        $fetchConfig = false;
        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
            $fetchConfig = true;
            $this->jsonOutput['pageConfig'] = array(
                'enabledFilters' => $this->getPageConfiguration('filter_settings', $this->settingVars->pageID)
            );
            $this->jsonOutput['groupName'] = $this->settingVars->groupName;
        }
        
        $categoryField = strtoupper($this->dbColumnsArray[$this->categoryField]);
        $subCategoryField = strtoupper($this->dbColumnsArray[$this->subCategoryField]);
        $this->categoryName = $this->settingVars->dataArray[$categoryField]['NAME'];
        $this->subCategoryName = $this->settingVars->dataArray[$subCategoryField]['NAME'];
        
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