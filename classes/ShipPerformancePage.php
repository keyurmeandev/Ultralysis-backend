<?php

namespace classes;

use projectsettings;
use datahelper;
use filters;
use config;

class ShipPerformancePage extends config\UlConfig {

    private $gridNameArray;
    private $pageName;

    public function __construct() {
        $this->gridNameArray = array();
        $this->pageDetails = new PageDetails();
        $this->jsonTagArray = array("gridStore", "gridGroup", "gridCategory", "gridBrand", "gridSKU");
        $this->jsonOutput = array();
    }

    /*     * ***
     * Default gateway function, should be similar in all DATA CLASS
     * arguments:
     *  $settingVars [project settingsGateway variables]
     * @return $xmlOutput with all data that should be sent to client app
     * *** */

    public function go($settingVars) {
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES
        $this->pageName = $_REQUEST["pageName"];
        
        $this->settingVars->pageName = $this->pageName; // set pageName in settingVars
        
        //SET PERFORMANCE PAGE'S GRID AND CHART PROPERTIES
        $this->setPageDetails();

        $this->queryPart = $this->getAll(); //USING OWN getAll function

        datahelper\Common_Data_Fetching_Functions::$settingVars = $this->settingVars;
        datahelper\Common_Data_Fetching_Functions::$queryVars = $this->queryVars;
        
        //START PREPARING XML FORMATED DATA 
        $this->prepareGridData();
        $this->prepareChartData();

        return $this->jsonOutput;
    }

    private function setPageDetails() {
        $this->pageDetails->countOfGrid = $_REQUEST["gridCount"];
        if (isset($_REQUEST["LINECHART"]) && strtolower($_REQUEST["LINECHART"]) == "true")
            $this->pageDetails->lineChart = true;

        // CONFIGURING ACTIVE GRIDS AND GETTING UP FIRST COLUMN NAME OF BOTTOM GRID INDIVIDUALLY
        $temp = array();
        if (!empty($this->settingVars->pageArray[$this->pageName]["GRID_FIELD"]))
            foreach ($this->settingVars->pageArray[$this->pageName]["GRID_FIELD"] as $gridName => $columnAndFieldName) {
                foreach ($columnAndFieldName as $columnName => $fieldName) {
                    $this->gridNameArray[$gridName] = $fieldName;
                    $temp[$gridName] = $columnName;
                }
            }
        $this->jsonOutput["GRID_FIRST_COLUMN_NAMES"] = $temp;
    }

    private function prepareGridData() {
        $totalGrid = count($this->jsonTagArray);
        for ($i = ($totalGrid - $this->pageDetails->countOfGrid); $i < $totalGrid; $i++) {
            $selectPart = array();
            $groupByPart = array();
            
            $indexInDataArray = $this->gridNameArray[$this->jsonTagArray[$i]];
            $tempId = key_exists('ID', $this->settingVars->dataArray[$indexInDataArray]) ? $this->settingVars->dataArray[$indexInDataArray]['ID'] : "";
            if ($tempId != "") {
                $selectPart[] = $tempId . " AS ID";
                $groupByPart[] = 'ID';
            }

            $nameList = explode("-", $this->settingVars->dataArray[$indexInDataArray]['NAME']);

            foreach ($nameList as $key => $name) {
                if ($key == 0) {
                    $selectPart[] = $name . " AS ACCOUNT";
                    $groupByPart[] = "ACCOUNT";
                } else {
                    $selectPart[] = $name . " AS " . strtoupper($name);
                    $groupByPart[] = strtoupper($name);
                }
            }

            $dataJsonTag = $this->jsonTagArray[$i];
            datahelper\Common_Data_Fetching_Functions::gridFunction_For_ShipAnalysis($this->queryPart, $selectPart, $groupByPart, $dataJsonTag, $this->jsonOutput);
            
        }
        
    }

    private function prepareChartData() {
        if ($this->pageDetails->lineChart == true) {
            datahelper\Common_Data_Fetching_Functions::lineChart_For_ShipAnalysis($this->queryPart, $this->jsonOutput);
        }
    }

    /*     * ***
     * COLLECTS TABLE JOINING STRING FROM PROJECT SETTINGS
     * ALSO APPLYS FILTER CONDITIONS TO THE STRING
     * AND RETURNS $tablejoins_and_filters
     * *** */

    public function getAll() {
        $tablejoins_and_filters = parent::getAll();

        if ($_REQUEST["STORE"] != "") {
            if (!key_exists('ID', $this->settingVars->dataArray[$this->gridNameArray["gridStore"]]))
                $tablejoins_and_filters .= " AND " . $this->settingVars->dataArray[$this->gridNameArray["gridStore"]]["NAME"] . "='" . $_REQUEST['STORE'] . "'";
            else
                $tablejoins_and_filters .= " AND " . $this->settingVars->dataArray[$this->gridNameArray["gridStore"]]["ID"] . "='" . $_REQUEST['STORE'] . "'";
        }
        if ($_REQUEST["GROUP"] != "") {
            if (!key_exists('ID', $this->settingVars->dataArray[$this->gridNameArray["gridGroup"]]))
                $tablejoins_and_filters .= " AND " . $this->settingVars->dataArray[$this->gridNameArray["gridGroup"]]["NAME"] . "='" . $_REQUEST['GROUP'] . "'";
            else
                $tablejoins_and_filters .= " AND " . $this->settingVars->dataArray[$this->gridNameArray["gridGroup"]]["ID"] . "='" . $_REQUEST['GROUP'] . "'";
        }
        if ($_REQUEST["CATEGORY"] != "") {
            if (!key_exists('ID', $this->settingVars->dataArray[$this->gridNameArray["gridCategory"]]))
                $tablejoins_and_filters .= " AND " . $this->settingVars->dataArray[$this->gridNameArray["gridCategory"]]["NAME"] . "='" . $_REQUEST['CATEGORY'] . "'";
            else
                $tablejoins_and_filters .= " AND " . $this->settingVars->dataArray[$this->gridNameArray["gridCategory"]]["ID"] . "='" . $_REQUEST['CATEGORY'] . "'";
        }
        if ($_REQUEST["BRAND"] != "") {
            if (!key_exists('ID', $this->settingVars->dataArray[$this->gridNameArray["gridBrand"]]))
                $tablejoins_and_filters .= " AND " . $this->settingVars->dataArray[$this->gridNameArray["gridBrand"]]["NAME"] . "='" . $_REQUEST['BRAND'] . "'";
            else
                $tablejoins_and_filters .= " AND " . $this->settingVars->dataArray[$this->gridNameArray["gridBrand"]]["ID"] . "='" . $_REQUEST['BRAND'] . "'";
        }
        if ($_REQUEST["SKU"] != "") {
            if (!key_exists('ID', $this->settingVars->dataArray[$this->gridNameArray["gridSKU"]]))
                $tablejoins_and_filters .= " AND " . $this->settingVars->dataArray[$this->gridNameArray["gridSKU"]]["NAME"] . "='" . $_REQUEST['SKU'] . "'";
            else
                $tablejoins_and_filters .= " AND " . $this->settingVars->dataArray[$this->gridNameArray["gridSKU"]]["ID"] . "='" . $_REQUEST['SKU'] . "'";
        }

        return $tablejoins_and_filters;
    }

}

/* * ********** VIRTUAL OBJECT CLASS ************ */

class PageDetails {

    public $countOfGrid = 0;
    public $lineChart = false;

}
/* * ******** VIRTUAL OBJECT CLASS ENDS ********* */
?> 