<?php

namespace classes\relayplus;

use projectsettings;
use datahelper;
use filters;
use config;
use db;
use lib;

class MyPromoAnalysisNew extends config\UlConfig {

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
        
        filters\timeFilter::calculateTotalWeek($settingVars); //Total Weeks

        $this->queryPart = $this->getAll(); //USING OWN getAll function
        
        datahelper\Common_Data_Fetching_Functions::$settingVars = $this->settingVars;
        datahelper\Common_Data_Fetching_Functions::$queryVars = $this->queryVars;
        
        $action = $_REQUEST["action"];
        switch ($action) {
            case "getProductFilterData":
                $this->getProductFilterData();
                break;
            case "prepareChartData":
                $this->prepareChartData();
                break;
            default:
                $this->getGroupList();
                $this->jsonOutput["currencySign"] = $this->settingVars->currencySign;
        }        
        
        return $this->jsonOutput;
    }

    public function getProductFilterData() 
    {
        $mydateList = $this->getMydatesByWeekYear();
        
        $account            = $this->settingVars->dataArray["F31"]['NAME'];
        $accountID          = $this->settingVars->dataArray["F31"]['ID'];
        $alias              = $this->settingVars->dataArray["F31"]['NAME_ALIASE'];
        $aliasID            = $this->settingVars->dataArray["F31"]['ID_ALIASE'];
        $helperTableName    = $this->settingVars->dataArray["F31"]['tablename'];
        $helperLink         = $this->settingVars->dataArray["F31"]['link'];
        $customQueryPart    = " AND ".$this->settingVars->maintable.".period IN('".implode("','", $mydateList)."') ";
        
        $query = "SELECT " . $account . " as " . $alias . ", " .$accountID . " as " . $aliasID .
                ",SUM(sales) AS SALES " .
                "FROM $helperTableName $helperLink $customQueryPart" .
                "GROUP BY " . $alias . ", " . $aliasID . 
                " HAVING $alias <>'' AND SALES > 0 " .
                "ORDER BY $alias ASC ";
        
        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
        
        foreach ($result as $key => $data) {
            //$dataVal = in_array('PRIMARY_ID', $groupByPart) ? $data['PRIMARY_ID'] : $data['PRIMARY_LABEL'];
            $temp = array(
                'data' => $data[$aliasID], 
                'label' => $data[$alias]
                /* 'label' => ($includeIdInLabel) ? htmlspecialchars_decode($data['PRIMARY_LABEL'] . " ( " . $data['PRIMARY_ID'] . " ) ") : htmlspecialchars_decode($data['PRIMARY_LABEL']) ,
                'label_hash_secondary' => htmlspecialchars_decode($data['PRIMARY_LABEL'] . " #" . $data['SECONDARY_LABEL']) */
            );
            $this->jsonOutput["productList"][] = $temp;
        }
        
        /* print_r($this->jsonOutput["productList"]); exit;
        
        if( !empty($result) ){
            foreach ($result as $key => $data) {
                $dataVal = in_array('PRIMARY_ID', $groupByPart) ? $data['PRIMARY_ID'] : $data['PRIMARY_LABEL'];
                $temp = array(
                    'data' => htmlspecialchars($dataVal)
                    , 'label' => ($includeIdInLabel) ? htmlspecialchars_decode($data['PRIMARY_LABEL'] . " ( " . $data['PRIMARY_ID'] . " ) ") : htmlspecialchars_decode($data['PRIMARY_LABEL'])
                    , 'label_hash_secondary' => htmlspecialchars_decode($data['PRIMARY_LABEL'] . " #" . $data['SECONDARY_LABEL'])
                );
                $this->jsonOutput["productList"][] = $temp;
            } 
        }        
        
        $dataHelpers = array();
        if (!empty($this->settingVars->pageArray[$this->settingVars->pageName]["DH"]))
            $dataHelpers = explode("-", $this->settingVars->pageArray[$this->settingVars->pageName]["DH"]); //COLLECT REQUIRED PRODUCT FILTERS DATA FROM PROJECT SETTING 
        
        $filter = "productDataHelper";
        if (!empty($filter) && !empty($this->settingVars->$filter))
            $dataHelpers = explode("-", $this->settingVars->$filter); //COLLECT REQUIRED PRODUCT FILTERS DATA FROM PROJECT SETTING 
        
        if (!empty($dataHelpers)) {
            foreach ($dataHelpers as $key => $account) {
                if ($account != "") {
                    //IN RARE CASES WE NEED TO ADD ADITIONAL FIELDS IN GROUP BY CLUAUSE AND ALSO AS LABEL WITH THE ORIGINAL ACCOUNT
                    //E.G: FOR RUBICON LCL - SKU DATA HELPER IS SHOWN AS 'SKU #UPC'
                    //IN ABOVE CASE WE SEND DH VALUE = F1#F2 [ASSUMING F1 AND F2 ARE SKU AND UPC'S INDEX IN DATAARRAY]
                    $combineAccounts = explode("#", $account);
                    $selectPart = array();
                    $groupByPart = array();

                    foreach ($combineAccounts as $accountKey => $singleAccount) {
                        $tempId = key_exists('ID', $this->settingVars->dataArray[$singleAccount]) ? $this->settingVars->dataArray[$singleAccount]['ID'] : "";
                        if ($tempId != "") {
                            $selectPart[] = $tempId . " AS " . getAdjectiveForIndex($accountKey) . '_ID';
                            $groupByPart[] = getAdjectiveForIndex($accountKey) . '_ID';
                        }

                        $tempName = $this->settingVars->dataArray["F31"]['NAME'];
                        $selectPart[] = $tempName . " AS " . getAdjectiveForIndex($accountKey) . '_LABEL';
                        $groupByPart[] = getAdjectiveForIndex($accountKey) . '_LABEL';
                    }

                    $helperTableName = $this->settingVars->dataArray[$combineAccounts[0]]['tablename'];
                    $helperLink = $this->settingVars->dataArray[$combineAccounts[0]]['link'];
                    $tagNameAccountName = $this->settingVars->dataArray[$combineAccounts[0]]['NAME'];

                    //IF 'NAME' FIELD CONTAINS SOME SYMBOLS THAT CAN'T PASS AS A VALID XML TAG , WE USE 'NAME_ALIASE' INSTEAD AS XML TAG
                    //AND FOR THAT PARTICULAR REASON, WE ALWAYS SET 'NAME_ALIASE' SO THAT IT IS A VALID XML TAG
                    $tagName = preg_match('/(,|\)|\()|\./', $tagNameAccountName) == 1 ? $this->settingVars->dataArray[$combineAccounts[0]]['NAME_ALIASE'] : strtoupper($tagNameAccountName);

                    if (isset($this->settingVars->dataArray[$combineAccounts[0]]['use_alias_as_tag']))
                        $tagName = ($this->settingVars->dataArray[$combineAccounts[0]]['use_alias_as_tag']) ? strtoupper($this->settingVars->dataArray[$combineAccounts[0]]['NAME_ALIASE']) : $tagName;

                    $includeIdInLabel = false;
                    if (isset($this->settingVars->dataArray[$combineAccounts[0]]['include_id_in_label']))
                        $includeIdInLabel = ($this->settingVars->dataArray[$combineAccounts[0]]['include_id_in_label']) ? true : false;
                    
                    //$customQueryPart = " AND ".$this->settingVars->maintable.".SNO=".$_REQUEST["FS"]['SNAME']." ";
                    $customQueryPart = " AND ".$this->settingVars->maintable.".period IN('".implode("','", $mydateList)."') ";
                    
                    $query = "SELECT " . implode(",", $selectPart) . " " .
                            ",SUM(sales) AS SALES " .
                            "FROM $helperTableName $helperLink $customQueryPart" .
                            "GROUP BY " . implode(",", $groupByPart) . " " .
                            "HAVING PRIMARY_LABEL <>'' AND SALES > 0 " .
                            "ORDER BY PRIMARY_LABEL ASC ";
                    //echo $query; exit;
                    $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);

                    if( !empty($result) ){
                        foreach ($result as $key => $data) {
                            $dataVal = in_array('PRIMARY_ID', $groupByPart) ? $data['PRIMARY_ID'] : $data['PRIMARY_LABEL'];
                            $temp = array(
                                'data' => htmlspecialchars($dataVal)
                                , 'label' => ($includeIdInLabel) ? htmlspecialchars_decode($data['PRIMARY_LABEL'] . " ( " . $data['PRIMARY_ID'] . " ) ") : htmlspecialchars_decode($data['PRIMARY_LABEL'])
                                , 'label_hash_secondary' => htmlspecialchars_decode($data['PRIMARY_LABEL'] . " #" . $data['SECONDARY_LABEL'])
                            );
                            $this->jsonOutput["productList"][] = $temp;
                        } 
                    }
                    
                }
            }
        } */
    }    
    
    private function getMydatesByWeekYear(){
        $fromWeekYear = $_REQUEST["FromWeek"];
        $toWeekYear = $_REQUEST["ToWeek"];
        
        $fromWeek = explode("-", $fromWeekYear)[0];
        $fromYear = explode("-", $fromWeekYear)[1];
        
        $toWeek = explode("-", $toWeekYear)[0];
        $toYear = explode("-", $toWeekYear)[1];
        
        
        $query  = "SELECT distinct (".$this->settingVars->timetable.'.'.$this->settingVars->dateperiod.") mydate ".
                  "FROM ".$this->settingVars->timeHelperTables.$this->settingVars->timeHelperLink.
                  "AND (".$this->settingVars->weekperiod." >= $fromWeek and ".$this->settingVars->yearperiod.">=$fromYear) AND (".$this->settingVars->weekperiod." <= $toWeek and ".$this->settingVars->yearperiod." <= $toYear) ";
        //echo $query; exit;
        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_ARRAY);
        $data = array();
        foreach($result as $row){
            $data[] = $row[0];
        }
        return $data;
    }    
    
    public function getGroupList() {
        $query = "SELECT DISTINCT M.GID as GID, G.gname as GNAME FROM ".$this->settingVars->maintable." as M, ".
            $this->settingVars->grouptable." as G WHERE M.GID = G.gid AND M.gid = ". $this->settingVars->GID;

        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
        
        $this->jsonOutput['groupList'] = $result;
    }    
    
/*     public function prepareChartData() {
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
    } */
    
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

        return $tablejoins_and_filters;
    }
}
