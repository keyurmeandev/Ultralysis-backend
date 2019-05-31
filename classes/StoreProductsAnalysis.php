<?php

namespace classes;

use datahelper;
use projectsettings;
use filters;
use db;
use config;

class StoreProductsAnalysis extends config\UlConfig {
    
    public function go($settingVars) {
    	$this->initiate($settingVars);

        $this->settingVars->pageName = (empty($this->settingVars->pageName) && !empty($this->settingVars->pageID)) ? $this->settingVars->pageID.'_StoreProductsAnalysisPage' : $this->settingVars->pageName;

        if ($this->settingVars->isDynamicPage) {
            $this->gridField = $this->getPageConfiguration('store_field', $this->settingVars->pageID)[0];
            $fieldArray = array($this->gridField);

            $this->buildDataArray($fieldArray);
            $this->buildPageArray();
        }else{
        	$this->configurationFailureMessage();
        }

        if(isset($_REQUEST['ValueVolume']) && !empty($_REQUEST['ValueVolume'])){
            $this->ValueVolume = $this->settingVars->measureArray['M'.$_REQUEST['ValueVolume']]['usedFields'][0];
        }
        
        $action = $_REQUEST["action"];
        switch ($action) {
            case "getData" : {
                    $this->getProductsAnalysisData();
                    break;
                }
        }
        return $this->jsonOutput;
    }

    private function getProductsAnalysisData(){
        $productAnalysisList = [];
        $localQueryPart = $this->queryPart;

        $this->measureFields[] = $this->gridFieldName;
        $this->prepareTablesUsedForQuery($this->measureFields);
        $this->settingVars->useRequiredTablesOnly = true;
        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll
        
        $query = "SELECT 
                    ".$this->gridFieldID." AS SNO, 
                    ".$this->gridFieldName." AS SNAME,".
                    " SUM((CASE WHEN " . filters\timeFilter::$tyWeekRange . " THEN 1 ELSE 0 END) * " . $this->ValueVolume . " ) AS sales, " .
                    " COUNT(DISTINCT ".$this->settingVars->maintable.".PIN) AS prod_selling, 
                      FORMAT((SUM((CASE WHEN " . filters\timeFilter::$tyWeekRange . " THEN 1 ELSE 0 END) * " . $this->ValueVolume . " )/COUNT(DISTINCT ".$this->settingVars->maintable.".PIN)),3) AS avg_sales ". 
                    "FROM ". $this->settingVars->tablename . $this->queryPart .
                    " AND (" . filters\timeFilter::$tyWeekRange . ") " .
                    " AND ". $this->ValueVolume ." > 0 ".
                    "GROUP BY SNO,SNAME ".
                    "ORDER BY sales DESC;";
        $productAnalysisList = $this->queryVars->queryHandler->runQuery($query,$this->queryVars->linkid,db\ResultTypes::$TYPE_OBJECT);
        $this->jsonOutput['productAnalysisList'] = $productAnalysisList;
    }

    public function buildPageArray() {

        $fetchConfig = false;
        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
            $fetchConfig = true;
            $this->jsonOutput['pageConfig'] = array(
                'enabledFilters' => $this->getPageConfiguration('filter_settings', $this->settingVars->pageID)
            );

            $this->jsonOutput["gridColumns"]['ACCOUNT'] = $this->dbColumnsArray;
            $this->jsonOutput["timeSelection"] = $this->timeArray;
        }

        if ($this->settingVars->hasGlobalFilter) {
            $globalFilterField = $this->settingVars->globalFilterFieldDataArrayKey;

            $this->storeIDField = (isset($this->settingVars->dataArray[$globalFilterField]) && isset($this->settingVars->dataArray[$globalFilterField]['ID'])) ? $this->settingVars->dataArray[$globalFilterField]['ID'] : $this->settingVars->dataArray[$globalFilterField]['NAME'];
            $globalFilterFieldIDAlias = (isset($this->settingVars->dataArray[$globalFilterField]) && isset($this->settingVars->dataArray[$globalFilterField]['ID'])) ? $this->settingVars->dataArray[$globalFilterField]['ID_ALIASE'] : $this->settingVars->dataArray[$globalFilterField]['NAME_ALIASE'];
            $this->storeNameField = $this->settingVars->dataArray[$globalFilterField]['NAME'];
            $globalFilterFieldNameAlias = $this->settingVars->dataArray[$globalFilterField]['NAME_ALIASE'];
        }else{
            $this->configurationFailureMessage("Global filter configuration not found");
        }

        $gridField = strtoupper(implode('_', $this->dbColumnsArray));
        $this->gridFieldName = $this->settingVars->dataArray[$gridField]['NAME'];
        $this->gridFieldID   = $this->settingVars->dataArray[$gridField]['ID'];
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