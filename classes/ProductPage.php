<?php

namespace classes;

use projectsettings;
use datahelper;
use filters;
use config;
use utils;
use db;
use lib;

class IniitialiseCSVPage extends config\UlConfig {

    public $gridNameArray;
    public $pageName;
    public $gridFields;
    public $countOfGrid;
    public $lineChart;
    public $dbColumnsArray;
    public $displayCsvNameArray;
	public $lyHavingField;
	public $tyHavingField;

    public $lineChartAllFunction;
    public $lineChartAllPpFunction;

    public function __construct() {
/*		$this->lyHavingField = "LYVALUE";
		$this->tyHavingField = "TYVALUE";

        $this->lineChartAllFunction = 'LineChartAllData';
        $this->lineChartAllPpFunction = 'LineChartAllData_for_PP';
        
        $this->gridNameArray = array();
        $this->jsonTagArray = array("gridStore", "gridGroup", "gridCategory", "gridBrand", "gridSKU");
        $this->jsonOutput = array();
    }

    /*     * ***
     * Default gateway function, should be similar in all DATA CLASS
     * arguments:
     *  $settingVars [project settingsGateway variables]
     * @return $jsonOutput with all data that should be sent to client app
     * *** */

    public function go($settingVars) {
       
    }


}
?> 