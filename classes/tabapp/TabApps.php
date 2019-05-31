<?php

namespace classes\tabapp;

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


        $action = $_REQUEST['action'];
                
        switch ($action) {
            case 'uploadSlideImage':
                $this->uploadSlideImage();exit;
                break;
            case 'uploadImage':
                $this->uploadImage();exit;
                break;
            default:
                
                //$this->saveRdeTrackerData();
                filters\timeFilter::calculateTotalWeek($settingVars); //Total Weeks

                $this->queryPart = $this->getAll(); //USING OWN getAll function
                
                datahelper\Common_Data_Fetching_Functions::$settingVars = $this->settingVars;
                datahelper\Common_Data_Fetching_Functions::$queryVars = $this->queryVars;
                //START PREPARING XML FORMATED DATA
                $this->prepareChartData();
                
                //$this->getUploadedImagesList();
                break;
        }
        

        return $this->jsonOutput;
    }

    public function saveRdeTrackerData(){

        $fromPeriod = (isset($_REQUEST['FromWeek']) && !empty($_REQUEST['FromWeek'])) ? $_REQUEST['FromWeek'] : "";
        $toPeriod = (isset($_REQUEST['ToWeek']) && !empty($_REQUEST['ToWeek'])) ? $_REQUEST['ToWeek'] : "";
        $GID = (isset($_REQUEST['GID']) && !empty($_REQUEST['GID'])) ? $_REQUEST['GID'] : "";
        $territory = (isset($_REQUEST['filtered_territory']) && !empty($_REQUEST['filtered_territory'])) ? $_REQUEST['filtered_territory'] : "";
        $SNO = (isset($_REQUEST['FS']['SNAME']) && !empty($_REQUEST['FS']['SNAME'])) ? $_REQUEST['FS']['SNAME'] : "";

        $searchKey = (is_array($this->settingVars->pageArray["MEASURE_SELECTION_LIST"]) && !empty($this->settingVars->pageArray["MEASURE_SELECTION_LIST"])) ? array_search($_REQUEST['ValueVolume'], array_column($this->settingVars->pageArray["MEASURE_SELECTION_LIST"], 'measureID')) : '';
        $measure = ($searchKey !== false ) ? $this->settingVars->pageArray["MEASURE_SELECTION_LIST"][$searchKey]['measureName'] : "";

        $query = "INSERT INTO ".$this->settingVars->rdetrackertable.
                " VALUES (0 , '".$this->settingVars->aid."', '".$this->settingVars->projectID."', '".$SNO."' , '".$GID."' , '".$measure."' , '".$territory."' , '".$fromPeriod."', '".$toPeriod."' , CURRENT_TIMESTAMP )";

        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$DML);

    }

    public function uploadSlideImage(){
        $this->settingVars->uploadDir = __DIR__ . "/../../../".$_REQUEST['projectDIR']."/slide/";
        $this->settingVars->uploadUrl = $this->settingVars->get_full_url()."/".$_REQUEST['projectDIR']."/slide/";
        error_reporting(E_ALL | E_STRICT);
        $options = array(
            'upload_dir' => $this->settingVars->uploadDir,
            'upload_url' => $this->settingVars->uploadUrl,
        );
        $upload_handler = new UploadHandler($options);
    }

    public function uploadImage(){
        error_reporting(E_ALL | E_STRICT);
        $options = array(
            'upload_dir' => $this->settingVars->uploadDir,
            'upload_url' => $this->settingVars->uploadUrl,
            'min_width' => 1024,
            'min_height' => 768,
        );
        $upload_handler = new UploadHandler($options);
    }

    public function prepareChartData() {
        $lineChartAllFunction = $this->lineChartAllFunction;
        $lineChartAllPpFunction = $this->lineChartAllPpFunction;

        //IF CHART OF THE PERFORMANCE PAGE NEEDS TO SHOW DATA USING OTHER ACCOUNT, RATHER THAN YEAR-WEEK
        if (isset($_REQUEST["lineChartType"])) {
            $dataId = key_exists('ID', $this->settingVars->dataArray[$_REQUEST["lineChartType"]]) ? $this->settingVars->dataArray[$_REQUEST["lineChartType"]]["ID"] : "";
            $dataName = $this->settingVars->dataArray[$_REQUEST["lineChartType"]]["NAME"];
            $dataJsonTag = $_REQUEST["lineChartType"];

            $measuresFields = datahelper\Common_Data_Fetching_Functions::getMeasuresExtraTable();
            $measuresFields[] = $dataId;
            $measuresFields[] = $dataName;
            
            $this->prepareTablesUsedForQuery($measuresFields);
            $this->settingVars->useRequiredTablesOnly = true;
            $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll            
            
            datahelper\Common_Data_Fetching_Functions::gridFunctionAllData($this->queryPart, $dataId, $dataName, $dataJsonTag, $this->jsonOutput);
        } else {
            $measuresFields = datahelper\Common_Data_Fetching_Functions::getMeasuresExtraTable();
            if(isset($_REQUEST['filtered_territory']) && !empty($_REQUEST['filtered_territory']))
                $measuresFields[] = $this->settingVars->territoryField;
            $this->prepareTablesUsedForQuery($measuresFields);
            $this->settingVars->useRequiredTablesOnly = true;
            $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll
        }

        //CONVENTIONAL YEAR-WEEK BASED DATA FOR USUAL LINE CHART
		if ($_REQUEST['TSM'] == 1)
			datahelper\Common_Data_Fetching_Functions::$lineChartAllFunction($this->queryPart, $this->jsonOutput);
		else
			datahelper\Common_Data_Fetching_Functions::$lineChartAllPpFunction($this->queryPart, $this->jsonOutput);

        if(!isset($this->jsonOutput['LineChart'])){
            $this->jsonOutput['errorMsg'] = "No data found for this combination";
        }

    }

    /*     * ***
     * COLLECTS TABLE JOINING STRING FROM PROJECT SETTINGS
     * ALSO APPLYS FILTER CONDITIONS TO THE STRING
     * AND RETURNS $tablejoins_and_filters
     * *** */

    public function getAll() {
        $tablejoins_and_filters = parent::getAll();

        if(isset($_REQUEST['filtered_territory']) && !empty($_REQUEST['filtered_territory']))
        {
            $tablejoins_and_filters .= " AND ".$this->settingVars->territoryField." =  '".$_REQUEST['filtered_territory']."' ";            
        }

        return $tablejoins_and_filters;
    }
}
