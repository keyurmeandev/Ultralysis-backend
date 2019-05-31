<?php

namespace classes\lcl;

use projectsettings;
use datahelper;
use filters;
use config;
use db;
use lib;

class TabApps extends config\UlConfig {

    public $pageName;
    public $lineChart;
	public $lyHavingField;
	public $tyHavingField;

    public $lineChartAllFunction;
    public $lineChartAllPpFunction;

    public function __construct() {
		$this->lyHavingField = "LYVALUE";
		$this->tyHavingField = "TYVALUE";

        $this->lineChartAllFunction = 'LineChartAllData';
        $this->lineChartAllPpFunction = 'LineChartAllData_for_PP';
        
        $this->jsonOutput = array();
    }

    /*     * ***
     * Default gateway function, should be similar in all DATA CLASS
     * arguments:
     *  $settingVars [project settingsGateway variables]
     * @return $jsonOutput with all data that should be sent to client app
     * *** */

    public function go($settingVars) {
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES

/*        if (isset($this->settingVars->pageArray[$this->settingVars->pageName]['configuration']))
            $this->jsonOutput['configuration'] = $this->settingVars->pageArray[$this->settingVars->pageName]['configuration'];
        
        $this->countOfGrid = count($this->settingVars->pageArray[$this->settingVars->pageName]["GRID_FIELD"]);
*/
        filters\timeFilter::calculateTotalWeek($settingVars); //Total Weeks

        $this->queryPart = $this->getAll(); //USING OWN getAll function

        datahelper\Common_Data_Fetching_Functions::$settingVars = $this->settingVars;
        datahelper\Common_Data_Fetching_Functions::$queryVars = $this->queryVars;

        //START PREPARING XML FORMATED DATA
        $this->prepareChartData();

        return $this->jsonOutput;
    }

    public function prepareChartData() {
        $lineChartAllFunction = $this->lineChartAllFunction;
        $lineChartAllPpFunction = $this->lineChartAllPpFunction;

        //IF CHART OF THE PERFORMANCE PAGE NEEDS TO SHOW DATA USING OTHER ACCOUNT, RATHER THAN YEAR-WEEK
        if (isset($_REQUEST["lineChartType"])) {
            $dataId = key_exists('ID', $this->settingVars->dataArray[$_REQUEST["lineChartType"]]) ? $this->settingVars->dataArray[$_REQUEST["lineChartType"]]["ID"] : "";
            $dataName = $this->settingVars->dataArray[$_REQUEST["lineChartType"]]["NAME"];
            $dataJsonTag = $_REQUEST["lineChartType"];

            datahelper\Common_Data_Fetching_Functions::gridFunctionAllData($this->queryPart, $dataId, $dataName, $dataJsonTag, $this->jsonOutput);
        }

        //CONVENTIONAL YEAR-WEEK BASED DATA FOR USUAL LINE CHART
		if ($_REQUEST['TSM'] == 1)
			datahelper\Common_Data_Fetching_Functions::$lineChartAllFunction($this->queryPart, $this->jsonOutput);
		else
			datahelper\Common_Data_Fetching_Functions::$lineChartAllPpFunction($this->queryPart, $this->jsonOutput);
    }

    /*     * ***
     * COLLECTS TABLE JOINING STRING FROM PROJECT SETTINGS
     * ALSO APPLYS FILTER CONDITIONS TO THE STRING
     * AND RETURNS $tablejoins_and_filters
     * *** */

    public function getAll() {
        $tablejoins_and_filters = parent::getAll();

        return $tablejoins_and_filters;
    }
}
?> 