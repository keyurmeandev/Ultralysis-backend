<?php

namespace classes;

use projectsettings;
use datahelper;
use filters;
use config;
use db;
use lib;

class OverUnderSellers extends config\UlConfig {

    private $pageDetails;
    private $gridNameArray;
    private $jsonTagArray;
    private $countOfGrid;
    private $pageName;
    private $skuField;
    private $storeField;
    private $displayCsvNameArray;
    private $dbColumnsArray;

    public function __construct() {
        $this->gridNameArray = array();
        $this->jsonTagArray = array("gridStore", "gridGroup", "gridCategory", "gridBrand", "gridSKU");
    }

    public function go($settingVars) {
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES

        if ($this->settingVars->isDynamicPage) {
            $this->gridFields = $this->getPageConfiguration('grid_fields', $this->settingVars->pageID);
            $this->settingVars->pageName = (empty($this->settingVars->pageName)) ? implode("_", $this->gridFields) . "_OverUnderSellerPage" : $this->settingVars->pageName;
            $this->countOfGrid = count($this->gridFields);

            $this->skuField = $this->getPageConfiguration('sku_field', $this->settingVars->pageID)[0];
            $this->storeField = $this->getPageConfiguration('store_field', $this->settingVars->pageID)[0];

            $this->buildDataArray(array_merge(array($this->skuField, $this->storeField), $this->gridFields));
            $this->buildPageArray($this->gridFields);
        } else {
            if (!isset($this->settingVars->pageArray[$this->settingVars->pageName]) || !isset($this->settingVars->pageArray[$this->settingVars->pageName]["GRID_FIELD"]) || empty($this->settingVars->pageArray[$this->settingVars->pageName]["GRID_FIELD"]))
                $this->configurationFailureMessage();

            $this->skuField = $this->settingVars->pageArray[$this->settingVars->pageName]["SKU_FIELD"];
            $this->storeField = $this->settingVars->pageArray[$this->settingVars->pageName]["STORE_FIELD"];
            $this->countOfGrid = count($this->settingVars->pageArray[$this->settingVars->pageName]["GRID_FIELD"]);
        }
        
        $this->setPageDetails();
        if (!isset($_REQUEST["fetchConfig"]) || empty($_REQUEST["fetchConfig"]) || $_REQUEST["fetchConfig"] != 'true') {
            filters\timeFilter::calculateTotalWeek($settingVars); //Total Weeks
            datahelper\Common_Data_Fetching_Functions::$settingVars = $this->settingVars;
            datahelper\Common_Data_Fetching_Functions::$queryVars = $this->queryVars;
            $this->requestedGridName = $_REQUEST['gridFetchName'];

            //START PREPARING XML FORMATED DATA 
            $this->prepareGridData();

            if (isset($this->jsonOutput["GRID_FIRST_COLUMN_NAMES"]))
                unset($this->jsonOutput["GRID_FIRST_COLUMN_NAMES"]);

            if (isset($this->jsonOutput["GRID_FIRST_ID_NAMES"]))
                unset($this->jsonOutput["GRID_FIRST_ID_NAMES"]);

        }

        return $this->jsonOutput;
    }

    private function setPageDetails_old() {
        //$this->countOfGrid = $_REQUEST["gridCount"];
        // CONFIGURING ACTIVE GRIDS AND GETTING UP FIRST COLUMN NAME OF BOTTOM GRID INDIVIDUALLY
        $temp = array();
        $tempID = array();
        if (!empty($this->settingVars->pageArray[$this->settingVars->pageName]["GRID_FIELD"]))
            foreach ($this->settingVars->pageArray[$this->settingVars->pageName]["GRID_FIELD"] as $gridName => $columnAndFieldName) {
                foreach ($columnAndFieldName as $columnName => $fieldName) {
                    $this->gridNameArray[$gridName] = $fieldName;
                    $temp[$gridName] = $this->displayCsvNameArray[$columnName];
                    $idColumn = key_exists('ID_CSV', $this->settingVars->dataArray[$fieldName]) ? $this->settingVars->dataArray[$fieldName]['ID_CSV'] : "";
                    $tempID[$gridName] = $idColumn;
                }
            }
        $this->jsonOutput["GRID_FIRST_COLUMN_NAMES"] = $temp;
        $this->jsonOutput["GRID_FIRST_ID_NAMES"] = $tempID;
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

        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
            $this->jsonOutput["GRID_FIRST_COLUMN_NAMES"] = $temp;
            $this->jsonOutput["GRID_FIRST_ID_NAMES"] = $tempID;
        }
    }

    private function prepareGridData() {
        $totalGrid = count($this->jsonTagArray);
        $this->countOfGrid = (isset($_REQUEST["gridCount"])) ? $_REQUEST["gridCount"] : $this->countOfGrid;
        $measuresFields = datahelper\Common_Data_Fetching_Functions::getMeasuresExtraTable();

        for ($i = ($totalGrid - $this->countOfGrid); $i < $totalGrid; $i++) {
            
            $selectPart = array();
            $this->settingVars->tableUsedForQuery = $groupByPart = array();
            $fieldsList = $measuresFields;
            

            if ($this->jsonTagArray[$i] == $this->requestedGridName) {
                $indexInDataArray = $this->gridNameArray[$this->jsonTagArray[$i]];
                
                $dataId = (isset($this->settingVars->dataArray[$indexInDataArray]) && isset($this->settingVars->dataArray[$indexInDataArray]['ID'])) ? $this->settingVars->dataArray[$indexInDataArray]["ID"] : $this->settingVars->dataArray[$indexInDataArray]["NAME"];
                $dataName = $this->settingVars->dataArray[$indexInDataArray]["NAME"];
                $storeField = (isset($this->settingVars->dataArray[$this->storeField]) && isset($this->settingVars->dataArray[$this->storeField]['ID'])) ? $this->settingVars->dataArray[$this->storeField]["ID"] : $this->settingVars->dataArray[$this->storeField]["NAME"];
                $skuField = (isset($this->settingVars->dataArray[$this->skuField]) && isset($this->settingVars->dataArray[$this->skuField]['ID'])) ? $this->settingVars->dataArray[$this->skuField]["ID"] : $this->settingVars->dataArray[$this->skuField]["NAME"];

                $fieldsList[] = $skuField;
                $fieldsList[] = $storeField;
                $fieldsList[] = $dataName;
                
                $tempId = key_exists('ID', $this->settingVars->dataArray[$indexInDataArray]) ? $this->settingVars->dataArray[$indexInDataArray]['ID'] : "";
                if ($tempId != "") {
                    $fieldsList[] = $tempId;
                }

                $nameList = explode("-", $this->settingVars->dataArray[$indexInDataArray]['NAME']);

                foreach ($nameList as $key => $name) {
                    $fieldsList[] = $name;
                }

                $this->prepareTablesUsedForQuery($fieldsList);
                $this->settingVars->useRequiredTablesOnly = true;
                $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll

                $dataJsonTag = $this->jsonTagArray[$i];
                datahelper\Common_Data_Fetching_Functions::gridFunction_for_overUnder(
                        $this->queryPart, $dataId, $dataName, $storeField, $skuField, $dataJsonTag, $this->jsonOutput
                );
            }
            
        }
    }

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

        $this->settingVars->pageName = implode("_", $gridFields) . "_OVERUNDERSELLER";
        $startIndex = count($this->jsonTagArray) - $this->countOfGrid;
        $fetchConfig = false;

        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
            $fetchConfig = true;
            $this->jsonOutput['gridConfig'] = array(
                'gridCount' => count($gridFields),
                'leftFirstGridCol' => strtoupper(str_replace('grid', '', $this->jsonTagArray[$startIndex])),
                'leftFirstGridName' => $this->jsonTagArray[$startIndex],
                'enabledGrids' => array(),
                'enabledTabs' => $this->getPageConfiguration('tabs_settings', $this->settingVars->pageID),
                'enabledFilters' => $this->getPageConfiguration('filter_settings', $this->settingVars->pageID)
            );
        }

        $skuFieldPart = explode("#", $this->skuField);
        $skuField = strtoupper($this->dbColumnsArray[$skuFieldPart[0]]);
        $skuField = (count($skuFieldPart) > 1) ? strtoupper($skuField . "_" . $this->dbColumnsArray[$skuFieldPart[1]]) : $skuField;

        $storeFieldPart = explode("#", $this->storeField);
        $storeField = strtoupper($this->dbColumnsArray[$storeFieldPart[0]]);
        $storeField = (count($storeFieldPart) > 1) ? strtoupper($storeField . "_" . $this->dbColumnsArray[$storeFieldPart[1]]) : $storeField;

        $this->skuField = $skuField;
        $this->storeField = $storeField;

        foreach ($gridFields as $gridField) {
            $gridFieldPart = explode("#", $gridField);
            $tempGridField = strtoupper($this->dbColumnsArray[$gridFieldPart[0]]);
            $tempGridField = (count($gridFieldPart) > 1) ? strtoupper($tempGridField."_".$this->dbColumnsArray[$gridFieldPart[1]]) : $tempGridField;

            $this->settingVars->pageArray[$this->settingVars->pageName]["GRID_FIELD"][$this->jsonTagArray[$startIndex]] = array($gridFieldPart[0] => $tempGridField);
            if ($fetchConfig)
                $this->jsonOutput['gridConfig']['enabledGrids'][] = $this->jsonTagArray[$startIndex];


             if (count($gridFieldPart) > 1) {
                $originalFieldPart = explode('@', $this->gridFieldsOriginal[$fieldKey]);
                $showIncludeIdByDefault = (count($originalFieldPart) > 1) ? $originalFieldPart[1] : 0;
                $showIncludeIdByDefault = ($showIncludeIdByDefault == 1) ? true : false;
                $this->jsonOutput['gridConfig']['showIncludeIdByDefault'][$this->jsonTagArray[$startIndex]] = $showIncludeIdByDefault;
            } 
            else
                $this->jsonOutput['gridConfig']['showIncludeIdByDefault'][$this->jsonTagArray[$startIndex]] = false;

            $startIndex++;
        }

        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
            $gridFieldArray = $this->settingVars->pageArray[$this->settingVars->pageName]["GRID_FIELD"];
            if (is_array($gridFieldArray) && !empty($gridFieldArray)) {
                foreach ($gridFieldArray as $gridFieldKey => $gridFieldArrayValue) {
                    $this->jsonOutput["GRID_FIELD"][$gridFieldKey] = array_values($gridFieldArrayValue)[0];
                }
            }
        }

        return;
    }

}

?> 