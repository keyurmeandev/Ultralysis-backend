<?php

namespace classes\tabapp;

use datahelper;
use projectsettings;
use filters;
use utils;
use db;
use config;
use lib;

class SummaryPage extends \classes\SummaryPage {

    private $TY_total;      //Share_By_Name Function uses this to create 'TOTAL' tag
    private $LY_total;      //Share_By_Name Function uses this to create 'TOTAL' tag
    public $pageName;

    public $lineChart;

    public $lyHavingField;
    public $tyHavingField;
    public $lineChartAllFunction;
    public $lineChartAllPpFunction;
    
    /* ****
     * Default gateway function, should be similar in all DATA CLASS
     * arguments:
     *  $settingVars [project settingsGateway variables]
     * @return $jsonOutput with all data that should be sent to client app
     * ****/
    public function __construct() {
        $this->lyHavingField = "LYVALUE";
        $this->tyHavingField = "TYVALUE";
        $this->lineChartAllFunction = 'LineChartAllData';
        $this->lineChartAllPpFunction = 'LineChartAllData_for_PP';
        
        $this->jsonOutput = array();
    }

    public function go($settingVars) {
        $this->pageName = $settingVars->pageName = (empty($settingVars->pageName)) ? 'SummaryPage' : $settingVars->pageName; // set pageName in settingVars
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES
        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll
        $this->fetchConfig(); // Fetching filter configuration for page

        //Redis Cache
        $this->redisCache = new utils\RedisCache($this->queryVars);

        $action = $_REQUEST["action"];
        switch ($action) {
            case "territoryChange":
                $this->getGroupList();
                break;
            case "groupChange":
                $this->territoryChange();
                $this->jsonOutput["measureSelectionList"] = $this->settingVars->pageArray["MEASURE_SELECTION_LIST"];
                break;
            case "timeSelectionUnitChange":
                $configureProject = new config\ConfigureProject($this->settingVars, $this->queryVars);
                $configureProject->fetch_all_timeSelection_data(); //collecting time selection data
                $this->jsonOutput['dateList'] = $configureProject->jsonOutput['dateList'];
                $this->jsonOutput['gridWeek'] = $configureProject->jsonOutput['gridWeek'];
                break;
            case "getProductFilterData":
                $this->getProductFilterData();
                break;
            case "getLineChartData":
                //$this->saveRdeTrackerData(); //Un-Comment this line when making it on Live
                filters\timeFilter::calculateTotalWeek($settingVars); //Total Weeks
                $this->queryPart = $this->getAll(); //USING OWN getAll function
                datahelper\Common_Data_Fetching_Functions::$settingVars = $this->settingVars;
                datahelper\Common_Data_Fetching_Functions::$queryVars = $this->queryVars;
                //START PREPARING XML FORMATED DATA
                $this->prepareChartData();
                break;
            case 'uploadSlideImage':
                $this->uploadSlideImage(); exit;
                break;
            case 'uploadImage':
                $this->uploadImage(); exit;
                break;
            default:
                $this->getUploadedImagesList();
                $this->getTerritoryList();
                $this->jsonOutput["currencySign"] = $this->settingVars->currencySign;
                if(isset($this->settingVars->pageArray) && isset($this->settingVars->pageArray['PROJECT_NAME']))
                    $this->jsonOutput["project_name"] = $this->settingVars->pageArray['PROJECT_NAME'];

                if(isset($this->settingVars->timeSelectionUnitOptions) && is_array($this->settingVars->timeSelectionUnitOptions) && !empty($this->settingVars->timeSelectionUnitOptions))
                    $this->jsonOutput["timeSelectionUnitOptions"] = $this->settingVars->timeSelectionUnitOptions;

                $this->jsonOutput["customerLogoPath"] = $this->settingVars->get_full_url()."/assets/img/apk-group-logo/";
                if(isset($this->settingVars->clicktoaddTitleText) && !empty($this->settingVars->clicktoaddTitleText))
                    $this->jsonOutput["clicktoaddTitleText"] = $this->settingVars->clicktoaddTitleText;
        }

        return $this->jsonOutput;
    }

    public function territoryChange() {
        $configureProject = new config\ConfigureProject($this->settingVars, $this->queryVars);
        $this->settingVars->productOptions_DisplayOptions = array();
        $configureProject->fetch_all_product_and_marketSelection_data("marketDataHelper"); //collecting product and market filter data
        $configureProject->fetchProductAndMarketTabsSettings();
        $this->jsonOutput['marketSelectionTabs'] = $configureProject->jsonOutput['marketSelectionTabs'];
        $configureProject->fetch_all_timeSelection_data(); //collecting time selection data
        $this->jsonOutput['dateList'] = $configureProject->jsonOutput['dateList'];
        $this->jsonOutput['gridWeek'] = $configureProject->jsonOutput['gridWeek'];
    }
    
    private function getMydatesByWeekYear() {
        $query  = "SELECT distinct (".$this->settingVars->timetable.'.'.$this->settingVars->dateperiod.") mydate ".
                  "FROM ".$this->settingVars->timeHelperTables.$this->settingVars->timeHelperLink.
                  "AND ".filters\timeFilter::$tyWeekRange;
        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }

        $data = [];
        if (!empty($result))
            $data = array_column($result, 'mydate');

        return $data;
    }

    public function getProductFilterData() {
        $dataHelpers = array();
        if (!empty($this->settingVars->pageArray[$this->settingVars->pageName]["DH"]))
            $dataHelpers = explode("-", $this->settingVars->pageArray[$this->settingVars->pageName]["DH"]); //COLLECT REQUIRED PRODUCT FILTERS DATA FROM PROJECT SETTING
        $filter = "productDataHelper";
        if (!empty($filter) && !empty($this->settingVars->$filter))
            $dataHelpers = explode("-", $this->settingVars->$filter); //COLLECT REQUIRED PRODUCT FILTERS DATA FROM PROJECT SETTING 
        
        if (!empty($dataHelpers)) {
            $account = end($dataHelpers);
            if (isset($_REQUEST["filtered_product_list"]) && $_REQUEST["filtered_product_list"] != '') {
                $account =  $_REQUEST["filtered_product_list"];
            }
            else{
                $dataHelpers = array_filter($dataHelpers);
                $strAllLen = count($dataHelpers);
                $cntr = 1;
                foreach ($dataHelpers as $key => $account) {
                $this->jsonOutput["allProductsList"][] = ['label'=>(isset($this->settingVars->dataArray[$account]) && isset($this->settingVars->dataArray[$account]['NAME_CSV'])) ? $this->settingVars->dataArray[$account]['NAME_CSV'] : $account, 'data'=>$account, 'selected'=> ($strAllLen == $cntr) ? true : false];
                $cntr++;
                }
            }
            
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
                    $tempName = $this->settingVars->dataArray[$singleAccount]['NAME'];
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

                $mydateList = $this->getMydatesByWeekYear();
                $customQueryPart = " AND ".$this->settingVars->maintable.".SNO=".$_REQUEST["FS"]['STORE.SNAME_STORE.SNO']." ";
                $customQueryPart .= " AND ".$this->settingVars->dateField." IN ('".implode("','", $mydateList)."') ";
                $this->ValueVolume = getValueVolume($this->settingVars);

                $query = "SELECT " . implode(",", $selectPart) . " " .
                        ",SUM(".$this->ValueVolume.") AS SALES " .
                        "FROM $helperTableName $helperLink $customQueryPart" .
                        "GROUP BY " . implode(",", $groupByPart) . " " .
                        "HAVING PRIMARY_LABEL <>'' AND SALES > 0 " .
                        "ORDER BY PRIMARY_LABEL ASC ";
                
                $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
                if ($redisOutput === false) {
                    $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
                    $this->redisCache->setDataForHash($result);
                } else {
                    $result = $redisOutput;
                }

                $productList = array();
                if( !empty($result) ){
                    foreach ($result as $key => $data) {
                        $dataVal = in_array('PRIMARY_ID', $groupByPart) ? $data['PRIMARY_ID'] : $data['PRIMARY_LABEL'];
                        $temp = array(
                            'data' => htmlspecialchars($dataVal)
                            , 'label' => ($includeIdInLabel) ? htmlspecialchars_decode($data['PRIMARY_LABEL'] . " ( " . $data['PRIMARY_ID'] . " ) ") : htmlspecialchars_decode($data['PRIMARY_LABEL'])
                            , 'label_hash_secondary' => htmlspecialchars_decode($data['PRIMARY_LABEL'] . " #" . $data['SECONDARY_LABEL'])
                        );
                        $productList[] = $temp;
                    }
                }
                $this->jsonOutput["productList"] = $productList;
                $this->jsonOutput["productDataKey"] = $combineAccounts[0];
            }
        }
    }

    public function getGroupList() {
        $query = "SELECT DISTINCT M.GID as GID, G.gname as GNAME FROM " . $this->settingVars->maintable . " as M, " .
                $this->settingVars->grouptable . " as G, ".$this->settingVars->territorytable." WHERE M.GID = G.gid AND M.GID = territory.gid AND M.SNO = territory.SNO";

        if(isset($_REQUEST['filtered_territory']) && !empty($_REQUEST['filtered_territory']))
            $query .= " AND ".$this->settingVars->territoryField. " = '".$_REQUEST['filtered_territory']."'";

        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }
        $this->jsonOutput['groupList'] = $result;
    }

    public function getTerritoryList() {
        $query = "SELECT DISTINCT " . $this->settingVars->territoryField . " as Territory " .
                " FROM " . $this->settingVars->territorytable . "  " .
                " WHERE " . $this->settingVars->territorytable . ".GID IN (" . $this->settingVars->GID . ") " .
                " AND " . $this->settingVars->territorytable . ".accountID=" . $this->settingVars->aid.
                " ORDER BY Territory ";

        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }
        $this->jsonOutput['territoryList'] = $result;
    }

    public function getUploadedImagesList() {
        $result = array();
        if (is_dir($this->settingVars->uploadDir)) {
            $cdir = scandir($this->settingVars->uploadDir);
            foreach ($cdir as $key => $value) {
                if (!in_array($value, array(".", ".."))) {
                    if (!is_dir($this->settingVars->uploadDir . DIRECTORY_SEPARATOR . $value)) {
                        //list($width, $height, $type, $attr) = getimagesize($this->settingVars->uploadUrl . $value);
                        list($width, $height, $type, $attr) = getimagesize($this->settingVars->uploadDir . DIRECTORY_SEPARATOR . $value);
                        $result[] = array(
                            'name' => $value,
                            'url' => $this->settingVars->uploadUrl . $value,
                            'thumbnailUrl' => $this->settingVars->uploadUrl . 'thumbnail/' . $value,
                            'selected' => false,
                            'width' => $width,
                            'height' => $height,
                        );
                    }
                }
            }
        }
        $this->jsonOutput['imageList'] = $result;
    }

    /* ****
     * COLLECTS TABLE JOINING STRING FROM PROJECT SETTINGS
     * ALSO APPLYS FILTER CONDITIONS TO THE STRING
     * AND RETURNS $tablejoins_and_filters
     * ****/
    public function getAll() {
        $tablejoins_and_filters = parent::getAll();
        if (isset($_REQUEST['filtered_territory']) && !empty($_REQUEST['filtered_territory'])) {
            $tablejoins_and_filters .= " AND ".$this->settingVars->territoryField." =  '".$_REQUEST['filtered_territory']."' ";
        }
        return $tablejoins_and_filters;
    }

    public function saveRdeTrackerData() {
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

    public function uploadSlideImage() {
        $this->settingVars->uploadDir = __DIR__ . "/../../../".$_REQUEST['projectDIR']."/slide/";
        $this->settingVars->uploadUrl = $this->settingVars->get_full_url()."/".$_REQUEST['projectDIR']."/slide/";
        error_reporting(E_ALL | E_STRICT);
        $options = array(
            'upload_dir' => $this->settingVars->uploadDir,
            'upload_url' => $this->settingVars->uploadUrl,
        );
        $upload_handler = new UploadHandler($options);
    }

    public function uploadImage() {
        error_reporting(E_ALL | E_STRICT);
        $options = array(
            'upload_dir' => $this->settingVars->uploadDir,
            'upload_url' => $this->settingVars->uploadUrl,
            'min_width'  => 1024,
            'min_height' => 768,
        );
        $upload_handler = new UploadHandler($options);
    }
}
?>