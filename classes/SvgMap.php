<?php

namespace classes;

use datahelper;
use filters;
use config;
use utils;

class SvgMap extends config\UlConfig {

    private $gridNameArray;
    private $jsonTagArray;
    private $countOfGrid;
    private $mapAccount;
    private $displayCsvNameArray;
    private $dbColumnsArray;

    public function __construct() {
        $this->gridNameArray = array();
        $this->jsonTagArray = array("gridStore", "gridGroup", "gridCategory", "gridBrand", "gridSKU");
        $this->jsonOutput = array();
    }

    public function go($settingVars) {
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES

        if ($this->settingVars->isDynamicPage) {
            $this->gridFields = $this->getPageConfiguration('grid_fields', $this->settingVars->pageID);
            $this->mapField = $this->getPageConfiguration('map_field', $this->settingVars->pageID);
            $this->settingVars->pageName = (empty($this->settingVars->pageName)) ? implode("_", $this->gridFields) . "_MAP" : $this->settingVars->pageName;
            $this->countOfGrid = count($this->gridFields);

            $allFields = array_merge($this->gridFields, $this->mapField);
            $this->buildDataArray($allFields);
            $this->buildPageArray($allFields);
        } else {
            if (!isset($this->settingVars->pageArray[$this->settingVars->pageName]) || !isset($this->settingVars->pageArray[$this->settingVars->pageName]["GRID_FIELD"]) || empty($this->settingVars->pageArray[$this->settingVars->pageName]["GRID_FIELD"]))
                $this->configurationFailureMessage();

            $this->mapAccount = $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]["mapAccount"]]['NAME'];
            $this->countOfGrid = count($this->settingVars->pageArray[$this->settingVars->pageName]["GRID_FIELD"]);
        }

        $this->setPageDetails();

        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
            $gridFieldArray = $this->settingVars->pageArray[$this->settingVars->pageName]["GRID_FIELD"];
            if (is_array($gridFieldArray) && !empty($gridFieldArray)) {
                foreach ($gridFieldArray as $gridFieldKey => $gridFieldArrayValue) {
                    $this->jsonOutput["GRID_FIELD"][$gridFieldKey] = array_values($gridFieldArrayValue)[0];
                }
            }
        }

        //$this->queryPart = $this->getAll();

        $redisCache = new utils\RedisCache($this->queryVars);

        /*if (!isset($_REQUEST["fetchConfig"]) || empty($_REQUEST["fetchConfig"]) || $_REQUEST["fetchConfig"] != 'true') {
            $_REQUEST['requestFieldName'] = $this->gridNameArray[$_REQUEST['gridFetchName']];
            $jsonOutput = $redisCache->checkAndReadFromCache('prepareCommonHashForPerformance');
        } else
            $jsonOutput = $redisCache->checkAndReadFromCache();*/

        if (!isset($_REQUEST["fetchConfig"]) || empty($_REQUEST["fetchConfig"]) || $_REQUEST["fetchConfig"] != 'true')
            $redisCache->requestHash = $redisCache->prepareCommonHashForPerformance();
        else
            $redisCache->requestHash = $redisCache->prepareCommonHash();

        /*if ($jsonOutput !== false) {
            if (isset($_REQUEST['requestFieldName']) && !empty($_REQUEST['requestFieldName'])) {
                $jsonOutput[$_REQUEST['gridFetchName']] = $jsonOutput[$_REQUEST['requestFieldName']];
                unset($jsonOutput[$_REQUEST['requestFieldName']]);
            }
            if(isset($jsonOutput[$_REQUEST['gridFetchName']]) && !empty($jsonOutput[$_REQUEST['gridFetchName']]) ){
                $requiredGridFields = array('ACCOUNT');
                $indexInDataArray = $this->gridNameArray[$_REQUEST['gridFetchName']];
                $tempId = key_exists('ID', $this->settingVars->dataArray[$indexInDataArray]) ? $this->settingVars->dataArray[$indexInDataArray]['ID'] : "";
                
                if ($tempId != "") {
                    $requiredGridFields[] = "ID";
                }
                $requiredGridFields = $this->getRequiredFieldsArray($requiredGridFields, false, $this->settingVars->measureArray);
                $orderBy = $tyField = $lyField = "";
                if(isset($_REQUEST['ValueVolume']) && !empty($_REQUEST['ValueVolume'])){
                    $measureKey = 'M' . $_REQUEST['ValueVolume'];
                    $measure = $this->settingVars->measureArray[$measureKey];
                    $tyField = $orderBy = "TY" . $measure['ALIASE'];
                    $lyField = "LY" . $measure['ALIASE'];
                }
                $jsonOutput[$_REQUEST['gridFetchName']] = $this->getRequiredData($jsonOutput[$_REQUEST['gridFetchName']],$requiredGridFields,$orderBy,$tyField,$lyField);
            }

            return $jsonOutput;
        }*/
        
        if (!isset($_REQUEST["fetchConfig"]) && empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] != 'true') {
            datahelper\Common_Data_Fetching_Functions::$settingVars = $this->settingVars;
            datahelper\Common_Data_Fetching_Functions::$queryVars = $this->queryVars;
            $this->requestedGridName = $_REQUEST['gridFetchName'];
            
            $this->prepareGridData();
        }

        $redisJSON = $this->jsonOutput;
        if (isset($_REQUEST['requestFieldName']) && !empty($_REQUEST['requestFieldName'])) {
            $redisJSON[$_REQUEST['requestFieldName']] = $redisJSON[$this->requestedGridName];
            $redisJSON['measureArray'] = $this->settingVars->measureArray;
            unset($redisJSON[$this->requestedGridName]);
        }
        $redisCache->setDataForHash($redisJSON);

        if(isset($this->jsonOutput[$_REQUEST['gridFetchName']]) && !empty($this->jsonOutput[$_REQUEST['gridFetchName']]) ) {
            $requiredGridFields = array("ACCOUNT", "ID");
            $indexInDataArray = $this->gridNameArray[$_REQUEST['gridFetchName']];
            $requiredGridFields = $redisCache->getRequiredFieldsArray($requiredGridFields, false, $this->settingVars->measureArray);
            $orderBy = $tyField = $lyField = "";

            if(isset($_REQUEST['ValueVolume']) && !empty($_REQUEST['ValueVolume'])){
                $measureKey = 'M' . $_REQUEST['ValueVolume'];
                $measure = $this->settingVars->measureArray[$measureKey];
                $tyField = $orderBy = "TY" . $measure['ALIASE'];
                $lyField = "LY" . $measure['ALIASE'];
            }
            $this->jsonOutput[$_REQUEST['gridFetchName']] = $redisCache->getRequiredData($this->jsonOutput[$_REQUEST['gridFetchName']],$requiredGridFields,$orderBy,$tyField,$lyField);
        }

        if (!isset($_REQUEST["fetchConfig"]) && empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] != 'true') {
            if (isset($this->jsonOutput["GRID_FIRST_COLUMN_NAMES"]))
                unset($this->jsonOutput["GRID_FIRST_COLUMN_NAMES"]);

            if (isset($this->jsonOutput["GRID_FIRST_ID_NAMES"]))
                unset($this->jsonOutput["GRID_FIRST_ID_NAMES"]);
        }

        return $this->jsonOutput;
    }

    private function setPageDetails() {
        // CONFIGURING ACTIVE GRIDS AND GETTING UP FIRST COLUMN NAME OF BOTTOM GRID INDIVIDUALLY
        $temp = array();
        $tempID = array();
        if (!empty($this->settingVars->pageArray[$this->settingVars->pageName]["GRID_FIELD"])) {
            foreach ($this->settingVars->pageArray[$this->settingVars->pageName]["GRID_FIELD"] as $gridName => $columnAndFieldName) {
                foreach ($columnAndFieldName as $columnName => $fieldName) {
                    $this->gridNameArray[$gridName] = $fieldName;
                    $temp[$gridName] = $this->displayCsvNameArray[$columnName];
                    $idColumn = key_exists('ID_CSV', $this->settingVars->dataArray[$fieldName]) ? $this->settingVars->dataArray[$fieldName]['ID_CSV'] : "";
                    $tempID[$gridName] = $idColumn;
                }
            }
        }

        $this->jsonOutput["GRID_FIRST_COLUMN_NAMES"] = $temp;
        $this->jsonOutput["GRID_FIRST_ID_NAMES"] = $tempID;
    }

    private function prepareGridData() {
        $totalGrid = count($this->jsonTagArray);

        $measuresFields = datahelper\Common_Data_Fetching_Functions::getMeasuresExtraTable();

        for ($i = ($totalGrid - $this->countOfGrid); $i < $totalGrid; $i++) {

            $selectPart = array();
            $this->settingVars->tableUsedForQuery = $groupByPart = array();
            $fieldsList = $measuresFields;

            if ($this->jsonTagArray[$i] == $this->requestedGridName) {
                $indexInDataArray = $this->gridNameArray[$this->jsonTagArray[$i]];

                $tempId = key_exists('ID', $this->settingVars->dataArray[$indexInDataArray]) ? $this->settingVars->dataArray[$indexInDataArray]['ID'] : "";

                if ($tempId != "") {
                    $fieldsList[] = $tempId;

                    $selectPart[] = $tempId . " AS ID";
                    $groupByPart[] = 'ID';
                }

                $nameList = explode("-", $this->settingVars->dataArray[$indexInDataArray]['NAME']);

                foreach ($nameList as $key => $name) {
                    $fieldsList[] = $name;
                    if ($key == 0) {
                        $selectPart[] = $name . " AS ACCOUNT";
                        $groupByPart[] = "ACCOUNT";
                    } else {
                        $selectPart[] = $name . " AS " . strtoupper($name);
                        $groupByPart[] = strtoupper($name);
                    }
                }

                $this->prepareTablesUsedForQuery($fieldsList);
                $this->settingVars->useRequiredTablesOnly = true;
                $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll

                $dataJsonTag = $this->jsonTagArray[$i];
                datahelper\Common_Data_Fetching_Functions::gridFunctionAllData($this->queryPart, $selectPart, $groupByPart, $dataJsonTag, $this->jsonOutput, $indexInDataArray);
                
                if($this->requestedGridName == "gridSKU"){

                    /*[START] Functions to create the common Measeure QUERY */
                        $measureSelectRes = $this->prepareMeasureSelectPart();
                        $this->measureFields = $measureSelectRes['measureFields'];
                        $this->measureFields[] = $this->mapAccount;
                        $this->prepareTablesUsedForQuery($this->measureFields);
                        $this->settingVars->useRequiredTablesOnly = true;
                        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll
                    /*[END] Functions to create the common Measeure QUERY */

                    if ($_REQUEST['map'] == "varpct") {
                        datahelper\Common_Data_Fetching_Functions::gridFlexTreeForVarPct($this->queryPart, $this->mapAccount, 'mapData', $this->jsonOutput, $measureSelectRes);
                    } else {
                        datahelper\Common_Data_Fetching_Functions::gridFlexTreeForVariance($this->queryPart, $this->mapAccount, 'mapData', $this->jsonOutput, $measureSelectRes);
                    }
                }
            }
            
        }

        
    }

    //OVERRIDING PARENT CLASS'S getAll FUNCTION
    public function getAll() {
        $extraFields = array();

        foreach ($this->gridNameArray as $gridName => $indexInDataArray) {
            $keyName = strtoupper(str_replace('grid', '', $gridName));
            if ($_REQUEST[$keyName] != "") {
                if (!key_exists('ID', $this->settingVars->dataArray[$indexInDataArray])) {
                    $extraFields[] = $this->settingVars->dataArray[$indexInDataArray]["NAME"];
                    $tablejoins_and_filters .= " AND " . $this->settingVars->dataArray[$indexInDataArray]["NAME"] . "='" . $_REQUEST[$keyName] . "'";
                } else {
                    $extraFields[] = $this->settingVars->dataArray[$indexInDataArray]["ID"];
                    $tablejoins_and_filters .= " AND " . $this->settingVars->dataArray[$indexInDataArray]["ID"] . "='" . $_REQUEST[$keyName] . "'";
                }
            }
        }

        $this->prepareTablesUsedForQuery($extraFields);
        $tablejoins_and_filters1 = parent::getAll();
        $tablejoins_and_filters1 .= $tablejoins_and_filters;

        return $tablejoins_and_filters1;
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

    public function buildPageArray($gridFields) {
        if (empty($gridFields))
            return false;

        $startIndex = count($this->jsonTagArray) - $this->countOfGrid;
        $fetchConfig = false;

        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
            $fetchConfig = true;
            $this->jsonOutput['gridConfig'] = array(
                'gridCount' => count($this->gridFields),
                'leftFirstGridCol' => strtoupper(str_replace('grid', '', $this->jsonTagArray[$startIndex])),
                'leftFirstGridName' => $this->jsonTagArray[$startIndex],
                'enabledGrids' => array(),
                'enabledFilters' => $this->getPageConfiguration('filter_settings', $this->settingVars->pageID)
            );

            $this->jsonOutput['mapTemplate'] = $this->getPageConfiguration('map_settings', $this->settingVars->pageID)[0];
        }

        foreach ($this->gridFields as $gridField) {
            $gridFieldPart = explode("#", $gridField);
            $tempGridField = strtoupper($this->dbColumnsArray[$gridFieldPart[0]]);
            $tempGridField = (count($gridFieldPart) > 1) ? strtoupper($tempGridField."_".$this->dbColumnsArray[$gridFieldPart[1]]) : $tempGridField;

            $this->settingVars->pageArray[$this->settingVars->pageName]["GRID_FIELD"][$this->jsonTagArray[$startIndex]] = array($gridFieldPart[0] => $tempGridField);
            if ($fetchConfig)
                $this->jsonOutput['gridConfig']['enabledGrids'][] = $this->jsonTagArray[$startIndex];

            $startIndex++;
        }

        $mapAccount = explode("#", $this->mapField[0])[0];
        $mapAccount = strtoupper($this->dbColumnsArray[$mapAccount]);
        $this->mapAccount = $this->settingVars->dataArray[$mapAccount]['NAME'];
        return;
    }

}

?>