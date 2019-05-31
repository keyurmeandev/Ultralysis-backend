<?php
namespace classes;

use datahelper;
use projectsettings;
use filters;
use db;
use config;
use utils;

class ActualBudgetComparisonAnalysis extends config\UlConfig {

    /** ***
	 * go()
     * Default gateway function, should be similar in all DATA CLASS
     * arguments:
     * $settingVars [project settingsGateway variables]
     * @return $this->jsonOutput with all data that should be sent to client app
     * *** */
     
    public function go($settingVars) {
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES
        $this->redisCache = new \utils\RedisCache($this->queryVars);
        
        if ($this->settingVars->isDynamicPage) 
        {
        	$extraColumns = $this->getPageConfiguration('extra_columns', $this->settingVars->pageID);

            if (!empty($extraColumns) && is_array($extraColumns)){
                foreach ($extraColumns as $ky => $vl) {
                    $fields[] = $vl;
                }
            }

            $this->buildDataArray($fields);

            if (!empty($extraColumns) && is_array($extraColumns)){
                foreach ($extraColumns as $ky => $vl) {
                    $this->settingVars->pageArray[$this->settingVars->pageName]["ExtraColumns"][] = ["ALIASE" => $this->settingVars->dataArray[strtoupper($this->dbColumnsArray[$vl])]['NAME_ALIASE'], "COLUMN" => strtoupper($this->settingVars->dataArray[strtoupper($this->dbColumnsArray[$vl])]['NAME'])];

                    $extraColumnsArray[$this->settingVars->dataArray[strtoupper($this->dbColumnsArray[$vl])]['NAME_ALIASE']] = $this->settingVars->dataArray[strtoupper($this->dbColumnsArray[$vl])]['NAME_CSV'];
                }
            }

            if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true')
                $this->jsonOutput['extraColumnsArray'] = $extraColumnsArray;

            $this->buildPageArray();
        } else {
            $this->configurationFailureMessage();
        }

        $action = $_REQUEST['action'];
        switch ($action) {
            case 'topGridData';
                $this->getData();
				break;
        }
        
        return $this->jsonOutput;
    }
	
	/**
	 * topGridData()
     * This Function is used to retrieve list based on set parameters     
	 * @access private	
     * @return $this->jsonOutput with all data that should be sent to client app     
     */
    private function getData() 
    {
        $measureColumns = $this->preparePageLevelSelectedMeasure();
        $this->jsonOutput['measureColumns'] = $measureColumns;

        $query = "SELECT ".$this->settingVars->yearField." AS YEAR, ". $this->settingVars->weekField." AS MONTH, " . implode(',',$this->measureFields). " FROM " . $this->settingVars->tablename .' '. trim($this->queryPart) .
                    " AND (" . filters\timeFilter::$tyWeekRange . " OR " . filters\timeFilter::$lyWeekRange . ") GROUP BY YEAR, MONTH ORDER BY YEAR, MONTH";

        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }
        
        if (is_array($result) && !empty($result))
        {
            foreach($result as $key => $data)
            {
                $result[$key]['PERIOD'] = $data['YEAR']."-".$data['MONTH'];
                foreach($data as $innerKey => $row)
                    $result[$key][$innerKey] = (double)$row;
            }
        }

        $this->jsonOutput['chartData'] = $result;

        $ExtraColumnAggregateFields = $ExtraColumnAllArr = $GroupByFieldArr = array();
        if(isset($this->settingVars->pageArray[$this->settingVars->pageName]) && isset($this->settingVars->pageArray[$this->settingVars->pageName]["ExtraColumns"]) && is_array($this->settingVars->pageArray[$this->settingVars->pageName]["ExtraColumns"]) && count($this->settingVars->pageArray[$this->settingVars->pageName]["ExtraColumns"])>0){
            $ExtraColumnAllArr = $this->settingVars->pageArray[$this->settingVars->pageName]["ExtraColumns"];

            if ($ExtraColumnAllArr) {
                foreach ($ExtraColumnAllArr as $key => $value) {
                    $ExtraColumnAggregateFields[] = $value['COLUMN']." AS ".$value['ALIASE'];
                    $GroupByFieldArr[] = $value['ALIASE'];
                    $this->measureFieldsCols[] = strtolower($value['COLUMN']);
                }
            }
        }

        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFieldsCols) && !empty($this->measureFieldsCols)) {
            $this->prepareTablesUsedForQuery($this->measureFieldsCols);
        }
        $this->queryPart = $this->getAll();

        $query = "SELECT ".(!empty($ExtraColumnAggregateFields) && count($ExtraColumnAggregateFields)>0 ? implode(',', $ExtraColumnAggregateFields) : "").", ". implode(',',$this->measureFields) . " FROM " . $this->settingVars->tablename .' '. trim($this->queryPart) .
                    " AND (" . filters\timeFilter::$tyWeekRange . " OR " . filters\timeFilter::$lyWeekRange . ") GROUP BY ".(!empty($GroupByFieldArr) && count($GroupByFieldArr)>0 ? implode(',', $GroupByFieldArr) : "");

        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }

        if (is_array($result) && !empty($result))
        {
            foreach($result as $key => $data)
            {
                foreach($data as $innerKey => $row){
                    $result[$key]["TY_".$measureColumns[0]] = (double)$data["TY_".$measureColumns[0]];
                    $result[$key]["TY_".$measureColumns[1]] = (double)$data["TY_".$measureColumns[1]];
                    $result[$key]["VAR_".$measureColumns[0]] = $data["TY_".$measureColumns[0]] - $data["LY_".$measureColumns[0]];
                    $result[$key]["VAR_PER_".$measureColumns[0]] = ($data["LY_".$measureColumns[0]] > 0) ? (( ($data["TY_".$measureColumns[0]] - $data["LY_".$measureColumns[0]])/$data["LY_".$measureColumns[0]] )*100 ) : 0;

                    $result[$key]["LY_".$measureColumns[0]] = (double)$data["LY_".$measureColumns[0]];
                    $result[$key]["LY_".$measureColumns[1]] = (double)$data["LY_".$measureColumns[1]];
                    $result[$key]["VAR_".$measureColumns[1]] = $data["TY_".$measureColumns[1]] - $data["LY_".$measureColumns[1]];
                    $result[$key]["VAR_PER_".$measureColumns[1]] = ($data["LY_".$measureColumns[1]] > 0) ? (( ($data["TY_".$measureColumns[1]] - $data["LY_".$measureColumns[1]])/$data["LY_".$measureColumns[1]] )*100 ) : 0;
                }
            }
        }
        $this->jsonOutput['gridData'] = $result;

    }

    public function buildDataArray($fields) {
        if (empty($fields))
            return false;

        $configurationCheck = new config\ConfigurationCheck($this->settingVars, $this->queryVars);
        $configurationCheck->buildDataArray($fields);
        $this->dbColumnsArray = $configurationCheck->dbColumnsArray;
        $this->displayCsvNameArray = $configurationCheck->displayCsvNameArray;
        $this->displayDbColumnArray = $configurationCheck->displayDbColumnArray;
        return;
    }

    public function buildPageArray() {

        $fetchConfig = false;

        $this->settingVars->prepareMeasureForActualBudgetComparisonAnalysis();

        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
            $fetchConfig = true;
            $this->jsonOutput['pageConfig'] = array(
                'enabledFilters' => $this->getPageConfiguration('filter_settings', $this->settingVars->pageID)
            );

            $this->jsonOutput['measureSelectionList'] = $this->settingVars->pageArray["MEASURE_SELECTION_LIST"];
        }

        return;
    }

    public function preparePageLevelSelectedMeasure($accountField = array()) {

        $measureColumns = $measureExtraFields = $this->measureFields = $this->mappingMeasure = array();
        $selectedValueVolume = 1;
        if(isset($_REQUEST['ValueVolume']) && !empty($_REQUEST['ValueVolume'])){
            $selectedValueVolume = $_REQUEST['ValueVolume'];
        }
        $this->mappingMeasure = $this->settingVars->measureArrayMapping[$selectedValueVolume];

        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        
        $projectStructureTypePage = "projectstructure\\".$this->settingVars->projectStructureType."_PAGES";
        $structurePageClass = new $projectStructureTypePage();
        $measureSelectRes = $structurePageClass->ActualBudgetComparisonAnalysisSelectPart($this->settingVars, $this->queryVars, $this->mappingMeasure);

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
	
}
?>