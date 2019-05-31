<?php

namespace classes;

use datahelper;
use projectsettings;
use filters;
use utils;
use db;
use config;

class TyLyMeasuresPage extends config\UlConfig {

    public $customSelectPart;

    public function go($settingVars) {
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES
        $this->queryPart = $this->getAll(); //USES OWN getAll FUNCTION

        $this->settingVars->pageName = (empty($this->settingVars->pageName)) ? $this->settingVars->pageID.'_TyLyMeasuresPage' : $settingVars->pageName;

        $this->redisCache = new utils\RedisCache($this->queryVars);
        
        if ($this->settingVars->isDynamicPage) {
            $this->accountField = $this->getPageConfiguration('grid_field', $this->settingVars->pageID)[0];
            $this->measureFilterSettings = $this->getPageConfiguration('measure_filter_settings', $this->settingVars->pageID);
            $this->buildDataArray(array($this->accountField));
            $this->buildPageArray();
        }else{
            $this->configurationFailureMessage();
        }
                                  
        if (!isset($_REQUEST["fetchConfig"]) )
            $this->prepareGridData(); //ADDING TO OUTPUT

        return $this->jsonOutput;
    }

    function prepareGridData() {
        $arr = array();
        $temp = array();

        $this->settingVars->tableUsedForQuery = $this->measureFields = array();

        datahelper\Common_Data_Fetching_Functions::$settingVars = $this->settingVars;
        datahelper\Common_Data_Fetching_Functions::$queryVars = $this->queryVars;
        $this->measureFields = datahelper\Common_Data_Fetching_Functions::getAllMeasuresExtraTable($this->measureFilterSettings);

        if (!empty($this->accountID))
            $this->measureFields[] = $this->accountID;

        $this->measureFields[] = $this->accountName;

        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->queryPart = $this->getAll();

        $projectStructureTypePage = "projectstructure\\".$this->settingVars->projectStructureType."_PAGES";
        $structurePageClass = new $projectStructureTypePage();

        $measureSelectionArr = $orderBy = array();
        if (is_array($this->userSelectedMeasure) && !empty($this->userSelectedMeasure)) {
            foreach ($this->userSelectedMeasure as  $measure) {
                $measureArrTmp = $orderByTmp = [];
                list($measureArrTmp, $orderByTmp, $measureKey) = $structurePageClass->tyLyMeasurePageLogic($this->settingVars, $this->queryVars, $measure);

                $measureSelectionArr = array_merge($measureSelectionArr,$measureArrTmp[$measureKey]);
                
                if(!empty($orderByTmp))
                    $orderBy[] = $orderByTmp." DESC";
            }
        }

        $measureSelect = implode(", ", $measureSelectionArr);

        /* Removed the DISTINCT and added the GROUP BY */
        $query = "SELECT " .
                ((!empty($this->accountID)) ? $this->accountID . " AS ID, " : "") .
                $this->accountName . " AS ACCOUNT, " .
                $measureSelect." ".
                " FROM " . $this->settingVars->tablename . $this->queryPart.
                " AND (" . filters\timeFilter::$tyWeekRange . " OR " . filters\timeFilter::$lyWeekRange . ") " .
                " GROUP BY ".((!empty($this->accountID)) ? "ID, " : "")."ACCOUNT ".
                ((!empty($orderBy)) ? " ORDER BY ".implode(", ", $orderBy).", ACCOUNT ASC" : " ");

        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);

        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }

        $measureGridData = array();
        if (is_array($result) && !empty($result)) {
            foreach ($result as $measureGrid) {
                $tmpData = [];
                if (isset($measureGrid['ID']))
                    $tmpData['ID'] = $measureGrid['ID'];

                $tmpData['ACCOUNT'] = $measureGrid['ACCOUNT'];

                if (is_array($this->userSelectedMeasure) && !empty($this->userSelectedMeasure)) {
                    foreach ($this->userSelectedMeasure as  $measure) {
                        if (isset($measureGrid["TY_".$measure['measureAlias']]) && isset($measureGrid["LY_".$measure['measureAlias']])) {
                            $tmpData["TY_".$measure['measureAlias']] = (float) $measureGrid["TY_".$measure['measureAlias']];
                            $tmpData["LY_".$measure['measureAlias']] = (float) $measureGrid["LY_".$measure['measureAlias']];
                            $tmpData["VAR_".$measure['measureAlias']] = $measureGrid["TY_".$measure['measureAlias']] - $measureGrid["LY_".$measure['measureAlias']];
                            $tmpData["VARPER_".$measure['measureAlias']] = ($measureGrid["LY_".$measure['measureAlias']] > 0) ? (($tmpData["VAR_".$measure['measureAlias']]/$tmpData["LY_".$measure['measureAlias']]) * 100) : 0;
                        }
                    }
                }
                $measureGridData[] = $tmpData;
            }
        }

        $this->jsonOutput["gridData"] = $measureGridData;
    }

    public function buildPageArray() {

        $this->userSelectedMeasure = array();
        if(is_array($this->settingVars->measureArray) && !empty($this->settingVars->measureArray)) {
            foreach ($this->settingVars->measureArray as  $mkey => $measure) {
                if (in_array(str_replace("M", "", $mkey), $this->measureFilterSettings)) {
                    $this->userSelectedMeasure[] = array(
                        'measureID' => $mkey,
                        'measureName' => $measure['measureName'],
                        'measureAlias' => $measure['ALIASE'],
                        'decimalPlaces' => $measure['dataDecimalPlaces']
                    );
                }
            }
        }

        $accountFieldPart = explode("#", $this->accountField);
        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true'){
            $this->jsonOutput['pageConfig']['enabledFilters'] = $this->getPageConfiguration('filter_settings', $this->settingVars->pageID);

            if (count($accountFieldPart) > 1)
                $this->jsonOutput["gridColumns"]['ID'] = $this->displayCsvNameArray[$accountFieldPart[1]];

            $this->jsonOutput["gridColumns"]['ACCOUNT'] = $this->displayCsvNameArray[$accountFieldPart[0]];
            $this->jsonOutput["measureList"] = $this->userSelectedMeasure;
        }        

        $accountField = strtoupper($this->dbColumnsArray[$accountFieldPart[0]]);
        $accountField = (count($accountFieldPart) > 1) ? strtoupper($accountField."_".$this->dbColumnsArray[$accountFieldPart[1]]) : $accountField;
        
        $this->accountID = (isset($this->settingVars->dataArray[$accountField]) && isset($this->settingVars->dataArray[$accountField]['ID'])) ? $this->settingVars->dataArray[$accountField]['ID'] : '';
        $this->accountName = $this->settingVars->dataArray[$accountField]['NAME'];

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