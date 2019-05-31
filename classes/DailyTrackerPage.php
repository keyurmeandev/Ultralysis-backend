<?php

namespace classes;

use projectsettings;
use datahelper;
use filters;
use config;
use utils;
use db;
use lib;

class DailyTrackerPage extends config\UlConfig {

    public $gridNameArray;
    public $pageName;
    public $gridFields;
    public $countOfGrid;
    public $dbColumnsArray;
    public $displayCsvNameArray;
    public $lyHavingField;
    public $tyHavingField;
    public $dh;
    public $redisCache;
    public $excludeNonCurrentCustomerData;
    public $excludeNonCurrentCustomerQuery;
    public $aggregateSelection;
    public $allSeasonalHardStopDatesHashKey;
    public $allSeasonalHardStopDatesHashKeyLy;

    public function __construct() {
        $this->lyHavingField = "VALUE";
        $this->tyHavingField = "VALUE";

        $this->gridNameArray = array();
        $this->jsonOutput = array();
    }

    /*****
     * Default gateway function, should be similar in all DATA CLASS
     * arguments:
     *  $settingVars [project settingsGateway variables]
     * @return $jsonOutput with all data that should be sent to client app
    *****/
    public function go($settingVars) {
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES
        $this->redisCache = new utils\RedisCache($this->queryVars);

        if ($this->settingVars->isDynamicPage) {
            $this->productAndMarketFilterData = $this->redisCache->checkAndReadByStaticHashFromCache('productAndMarketFilterData');
            
            if(empty($this->productAndMarketFilterData))
                $this->productAndMarketFilterData = $this->prepareProductAndMarketFilterData();
            //$this->gridFields = $this->getPageConfiguration('grid_fields', $this->settingVars->pageID);
            /*if(isset($_REQUEST['customAccount']) && $_REQUEST['customAccount'] != "")
                $this->gridFields[0] = $_REQUEST['customAccount'];

            $this->dh = $this->settingVars->pageArray[$this->settingVars->pageName]['DH'];
            $this->settingVars->pageName = (empty($this->settingVars->pageName)) ? implode("_", $this->gridFields)."_PERFORMANCE" : $settingVars->pageName;*/

            //$this->countOfGrid = count($this->gridFields);
            //$this->buildDataArray($this->gridFields);
            $this->buildPageArray();
        } else {
            $message = "Page isn't configured properly.";
            $response = array("configuration" => array("status" => "fail", "messages" => array($message)));
            echo json_encode($response);
            exit();
        }

        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
            $this->dh = $this->settingVars->pageArray[$this->settingVars->pageName]['DH'];
            $productFilterData = $this->getAllFilterData();
            $filterDataConfig = array();
            
            $dataHelpers = explode("-", $this->dh);
            $defaultSelectedField = '';
            $rnkDyArr = [];
            if (!empty($dataHelpers)) {
                foreach ($dataHelpers as $key => $account) {
                    if($account != "") {
                        $combineAccounts = explode("#", $account);
                        foreach ($combineAccounts as $accountKey => $singleAccount) {
                                $tempId = key_exists('ID', $this->settingVars->dataArray[$singleAccount]) ? $this->settingVars->dataArray[$singleAccount]['ID'] : "";
                                if ($tempId != "")
                                    $keyName = $this->settingVars->dataArray[$singleAccount]['NAME_ALIASE']."#".$this->settingVars->dataArray[$singleAccount]['ID_ALIASE'];
                                else
                                    $keyName = $this->settingVars->dataArray[$singleAccount]['NAME_ALIASE'];
                                
                                $filterDataConfig[$keyName]["data"] = ($tempId != "") ? $this->settingVars->dataArray[$singleAccount]['NAME_ALIASE']."#".$this->settingVars->dataArray[$singleAccount]['ID_ALIASE'] : $this->settingVars->dataArray[$singleAccount]['NAME_ALIASE'];
                                
                                $filterDataConfig[$keyName]["label"] = $this->settingVars->dataArray[$singleAccount]['NAME_CSV'];
                                
                                $filterDataConfig[$keyName]["indexName"] = ($tempId != "") ? 'FS['.strtoupper($this->settingVars->dataArray[$singleAccount]['NAME'])."_".strtoupper($this->settingVars->dataArray[$singleAccount]['ID']).']' : 'FS['.strtoupper($this->settingVars->dataArray[$singleAccount]['NAME']).']';

                                $filterDataConfig[$keyName]["field"] = ($tempId != "") ? $this->settingVars->dataArray[$singleAccount]['NAME']."##".$this->settingVars->dataArray[$singleAccount]['ID'] : $this->settingVars->dataArray[$singleAccount]['NAME'];
                                
                                $filterDataConfig[$keyName]["TYPE"] = $this->settingVars->dataArray[$singleAccount]['TYPE'];
                                
                                $rnkDyArr[$this->settingVars->dataArray[$singleAccount]['TYPE']] = isset($rnkDyArr[$this->settingVars->dataArray[$singleAccount]['TYPE']]) ? $rnkDyArr[$this->settingVars->dataArray[$singleAccount]['TYPE']]+1 : 1;

                                $filterDataConfig[$keyName]["RANK"] = isset($rnkDyArr[$this->settingVars->dataArray[$singleAccount]['TYPE']]) ? $rnkDyArr[$this->settingVars->dataArray[$singleAccount]['TYPE']] : 1;
                                
                                $filterDataConfig[$keyName]["dataList"] = array();
                                $filterDataConfig[$keyName]["selectedDataList"] = array();

                        }
                    }
                }
            }

            /*[START] SORTING THE ORDER BASED ON THE FILTER TYPE AND ITS ORDERING GIVE FROM THE PROJECT MANAGER*/
            $typeArray = array_column($filterDataConfig, 'TYPE');
            $rankArray = array_column($filterDataConfig, 'RANK');
            array_multisort($typeArray, SORT_ASC, SORT_STRING, $rankArray, SORT_ASC, SORT_NUMERIC, $filterDataConfig);

            if(!empty($filterDataConfig)) {
                $cntr = 1;
                foreach ($filterDataConfig as $ky => $vl) {
                $filterDataConfig[$ky]['RANK'] = $cntr;
                    $cntr++;
                }
            }
            /*[END] SORTING THE ORDER BASED ON THE FILTER TYPE AND ITS ORDERING GIVE FROM THE PROJECT MANAGER*/

            //$this->jsonOutput["filterDataConfig"] = $filterDataConfig;
            $defaultSelectedField = array_values($filterDataConfig)[0];
            $this->jsonOutput["defaultSelectedField"] = $defaultSelectedField['field'];
            $this->jsonOutput["defaultSelectedFieldName"] = $defaultSelectedField['label'];
            //$this->jsonOutput["productAndMarketFilterData"] = $productFilterData;
        }

        if (isset($_REQUEST['action']) && $_REQUEST['action'] != 'getTreeMapData')
        {
            $showAsOutput = (isset($_REQUEST['fetchHardStopDate']) && $_REQUEST['fetchHardStopDate'] == 'YES') ? true : false;
            $requestedCombination = explode("-", $_REQUEST['timeFrame']);
            $requestedYear        = $requestedCombination[1];
            $requestedSeason      = $requestedCombination[0];

            $objDatahelper = new datahelper\Time_Selection_DataCollectors($this->settingVars);
            $objDatahelper->getAllSeasonalHardStopDates($this->jsonOutput,$requestedYear, $showAsOutput);

            if(isset($this->jsonOutput['allSeasonalHardStopDatesHashKey']) && !empty($this->jsonOutput['allSeasonalHardStopDatesHashKey'])){
                $this->allSeasonalHardStopDatesHashKey = $this->jsonOutput['allSeasonalHardStopDatesHashKey'];
                $this->allSeasonalHardStopDatesHashKeyLy = $this->jsonOutput['allSeasonalHardStopDatesHashKeyLy'];
                unset($this->jsonOutput['allSeasonalHardStopDatesHashKey'], $this->jsonOutput['allSeasonalHardStopDatesHashKeyLy']);
            }
        }

        if (isset($_REQUEST['action']) && $_REQUEST['action']=='export') {
            list($preparedQuery,$thisYear,$lastYear,$activeXlsWsheet,$havingTYValue,$havingLYValue,$appliedFilters,$tyFromDate,$tyToDate,$excludeNonCurrentCustomerDataCondition) = $this->prepareExportData();

            $excludeNonCurrentCustomerDataHash = '';
            if($this->excludeNonCurrentCustomerData && count($excludeNonCurrentCustomerDataCondition) > 0){
                $redisCache = new utils\RedisCache($this->queryVars);
                $redisCache->requestHash = $redisCache->prepareQueryHash('dailyTrackerReportExcludeNonCurrentCustomerData');
                $redisCache->setDataForStaticHash($excludeNonCurrentCustomerDataCondition);
                $excludeNonCurrentCustomerDataHash = $redisCache->requestHash;
            }

            $appliedFiltersTxt = implode('$$', $appliedFilters);
            $actXlsFldNm = implode("##", array_keys($activeXlsWsheet));
            $actXlsValNm = implode("##", $activeXlsWsheet);

            $fileName      = "Daily-Tracker-Report-" . date("Y-m-d-h-i-s") . ".xlsx";
            $savePath      = dirname(__FILE__)."/../uploads/Daily-Tracker-Report/";
            $imgLogoPath   = dirname(__FILE__)."/../../global/project/assets/img/ultralysis_logo.png";
            $filePath      = $savePath.$fileName;
            $projectID     = $this->settingVars->projectID;
            $RedisServer   = $this->queryVars->RedisHost.":".$this->queryVars->RedisPort;
            $RedisPassword = $this->queryVars->RedisPassword;
            $facings       = (isset($_REQUEST['facings']) && !empty($_REQUEST['facings'])) ? $_REQUEST['facings'] : 0;
            
            /*echo '/usr/bin/perl '.dirname(__FILE__).'/../batch/DailyTrackerReport.pl "'.$filePath.'" "'.$preparedQuery.'" "'.$thisYear.'" "'.$lastYear.'" "'.$actXlsFldNm.'" "'.$actXlsValNm.'" "'.$havingTYValue.'" "'.$havingLYValue.'" "'.$appliedFiltersTxt.'" "'.$imgLogoPath.'" "'.$tyFromDate.'" "'.$tyToDate.'" "'.$excludeNonCurrentCustomerDataHash.'" "'.$projectID.'" "'.$RedisServer.'" "'.$RedisPassword.'" "'.$this->aggregateSelection.'" "'.$this->allSeasonalHardStopDatesHashKey.'##'.$this->allSeasonalHardStopDatesHashKeyLy.'" "'.$facings.'"';
            exit;*/

            $result = shell_exec('/usr/bin/perl '.dirname(__FILE__).'/../batch/DailyTrackerReport.pl "'.$filePath.'" "'.$preparedQuery.'" "'.$thisYear.'" "'.$lastYear.'" "'.$actXlsFldNm.'" "'.$actXlsValNm.'" "'.$havingTYValue.'" "'.$havingLYValue.'" "'.$appliedFiltersTxt.'" "'.$imgLogoPath.'" "'.$tyFromDate.'" "'.$tyToDate.'" "'.$excludeNonCurrentCustomerDataHash.'" "'.$projectID.'" "'.$RedisServer.'" "'.$RedisPassword.'" "'.$this->aggregateSelection.'" "'.$this->allSeasonalHardStopDatesHashKey.'##'.$this->allSeasonalHardStopDatesHashKeyLy.'" "'.$facings.'"');

            $this->jsonOutput['downloadLink'] = $this->settingVars->get_full_url()."/".basename(dirname(dirname(__FILE__)))."/uploads/Daily-Tracker-Report/".$fileName;
        } elseif (isset($_REQUEST['action']) && $_REQUEST['action']=='fetchGrid') {
            $this->gridData();
            $this->getColumnChartData();
            if(isset($this->settingVars->morrisonsGid) && !empty($this->settingVars->morrisonsGid))
                $this->jsonOutput['morrisonsItemStatus'] = $this->findMorrisonsItemStatus();
        } elseif (isset($_REQUEST['action']) && $_REQUEST['action']=='getTreeMapData') {
            $this->buildTreeMaps();
        } else if (isset($_REQUEST['action']) && ($_REQUEST['action'] == 'fetchInlineMarketAndProductFilter')) {
            $this->fetchInlineMarketAndProductFilterData();
        }

        return $this->jsonOutput;
    }

    public function fetchInlineMarketAndProductFilterData()
    {
        /*[START] PREPARING THE PRODUCT INLINE FILTER DATA */
        if(isset($this->queryVars->projectConfiguration) && isset($this->queryVars->projectConfiguration['has_product_market_filter_disp_type']) && $this->queryVars->projectConfiguration['has_product_market_filter_disp_type']==1){
            $configureProject = new config\ConfigureProject($this->settingVars, $this->queryVars);
            $configureProject->fetch_all_product_and_marketSelection_data(true); //collecting time selection data

            $extraWhere = filters\timeFilter::$seasonalTimeframeExtraWhere." AND (" .filters\timeFilter::$tyWeekRange. " OR ".filters\timeFilter::$lyWeekRange.") ";

            $this->getAllProductAndMarketInlineFilterData($this->queryVars, $this->settingVars, $extraWhere);
        }
        /*[END] PREPARING THE PRODUCT INLINE FILTER DATA */
    }

    public function findMorrisonsItemStatus()
    {
        $requestedCombination = explode("-", $_REQUEST['timeFrame']);
        $requestedSeason      = $requestedCombination[0];
        $requestedYear        = $requestedCombination[1];
        
        $status = false;
        
        $query = "SELECT ".$this->settingVars->pnameField." as PNAME FROM ".$this->settingVars->maintable." WHERE ".$this->settingVars->pnameField." NOT IN (SELECT DISTINCT ".$this->settingVars->pnameMorrisonsPriceTableField." FROM ".$this->settingVars->seasonalmorrisonspricetable." WHERE GID = ".$this->settingVars->morrisonsGid." ) AND GID = ".$this->settingVars->morrisonsGid." AND accountID = ".$this->settingVars->aid." AND seasonal_description = '".$requestedSeason."' AND seasonal_year = ".$requestedYear." GROUP BY PNAME";
        
        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }
        
        if(count($result) > 0)
            $status = true;
            
        if(!$status)
        {
            $query = "SELECT DISTINCT ".$this->settingVars->pnameMorrisonsPriceTableField." FROM ".$this->settingVars->seasonalmorrisonspricetable.
                ", ".$this->settingVars->maintable." WHERE ".
                $this->settingVars->maintable.".PNAME=".$this->settingVars->seasonalmorrisonspricetable.".PNAME AND ".$this->settingVars->seasonalmorrisonspricetable.".GID = ".$this->settingVars->morrisonsGid." AND ".$this->settingVars->seasonalmorrisonspricetable.".PRICE = 0.01 AND ".$this->settingVars->maintable.".accountID = ".$this->settingVars->aid." AND ".$this->settingVars->maintable.".seasonal_description = '".$requestedSeason."' AND ".$this->settingVars->maintable.".seasonal_year = ".$requestedYear;

            $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
            if ($redisOutput === false) {
                $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);

                $this->redisCache->setDataForHash($result);
            } else {
                $result = $redisOutput;
            }
            
            if(count($result) > 0)
                $status = true;
        }
        
        return $status;
    }    
    
    public function buildTreeMaps()
    {
        if($this->excludeNonCurrentCustomerData && empty($this->excludeNonCurrentCustomerQuery)){
            $this->jsonOutput['treeMapNoData'] = 'No data found for this combination.';
            return;
        }
        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $field = strtoupper($_REQUEST['accountField']);
        $this->measureFields = $this->prepareTablesUsedForMeasure($_REQUEST['ValueVolume']);
        
        $this->measureFields[] = $this->settingVars->dataArray[$field]['NAME'];
        $this->prepareTablesUsedForQuery($this->measureFields);
        $this->settingVars->useRequiredTablesOnly = true;
        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll

        $this->Tree($this->settingVars->dataArray[$field]['NAME'], $field, $field);
    }
    
    public function buildPageArray() {
        
        $fetchConfig = false;
        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
            $fetchConfig = true;
            $tabsSettings = $this->getPageConfiguration('tabs_settings', $this->settingVars->pageID);
            $firstTabMeasure = (is_array($tabsSettings) && !empty($tabsSettings)) ? $this->settingVars->performanceTabMappings[$tabsSettings[0]] : "";

            if (is_array($this->settingVars->measureArray) && !empty($this->settingVars->measureArray)) {
                foreach ($this->settingVars->measureArray as $mKey => $measure) {
                    $measureAliase[$mKey] = array(
                        "ALIASE" => $measure['ALIASE'], 
                        "dataDecimalPlaces" => (isset($measure['dataDecimalPlaces']) && !empty($measure['dataDecimalPlaces'])) ? $measure['dataDecimalPlaces'] : 0, 
                        'NAME' => (isset($measure['measureName']) && !empty($measure['measureName'])) ? $measure['measureName'] : $measure['ALIASE']
                    );
                }
            }
            
            $this->jsonOutput['gridConfig'] = array(
                'measuresAliases' => $measureAliase,
                'enabledFilters' => $this->getPageConfiguration('filter_settings', $this->settingVars->pageID),
            );

            /*[START] CODE FOR INLINE FILTER STYLE*/
            $filtersDisplayType = $this->getPageConfiguration('filter_disp_type', $this->settingVars->pageID);
            if(!empty($filtersDisplayType) && isset($filtersDisplayType[0])) {
                $this->jsonOutput['gridConfig']['enabledFiltersDispType'] = $filtersDisplayType[0];
            }
            /*[END] CODE FOR INLINE FILTER STYLE*/
        }
        
        /*[START] SETTING THE AGGREGATE SELECTION DATA */
        if(isset($_REQUEST['aggregateSelection']) && !empty($_REQUEST['aggregateSelection']))
            $this->aggregateSelection = $_REQUEST['aggregateSelection'];
        else
            $this->aggregateSelection = 'days';
        /*[END] SETTING THE AGGREGATE SELECTION DATA */

        /*[START] SETTING THE EXCLUDE NON CURRENT CUTOMERS DATA */
        $this->setExcludeNonCurrentCutomers();
        /*[END] SETTING THE EXCLUDE NON CURRENT CUTOMERS DATA */
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

    public function setExcludeNonCurrentCutomers($accountField = '',$conditionType=''){
        $this->excludeNonCurrentCustomerData = false;
        if(isset($_REQUEST['excludeNonCurrentCustomerData']) && $_REQUEST['excludeNonCurrentCustomerData']=='true' && isset($_REQUEST["toDate"]) && !empty($_REQUEST["toDate"]) && isset($_REQUEST['ACCOUNT']) && !empty($_REQUEST['ACCOUNT'])) {

            if(empty($accountField)){
                $accountField = $_REQUEST["ACCOUNT"];
                if (isset($_REQUEST['action']) && $_REQUEST['action']=='getTreeMapData' && isset($_REQUEST['accountField']) && !empty($_REQUEST['accountField'])) {
                    $field = strtoupper($_REQUEST['accountField']);
                    $accountField = $this->settingVars->dataArray[$field]['NAME'];
                }
            }

            $this->excludeNonCurrentCustomerData = true;
            $hardStopToDate = $_REQUEST["toDate"];

            $this->settingVars->tableUsedForQuery = $this->measureFields = array();
            $measureSelectRes = $this->prepareMeasureSelectPart();
            $havingTYValue = $measureSelectRes['havingTYValue'];
            $havingLYValue = $measureSelectRes['havingLYValue'];
            $this->measureFields = $measureSelectRes['measureSelectionArr'];
            $this->measureFields[] = $accountField;
            
            $this->settingVars->useRequiredTablesOnly = true;
            if (is_array($this->measureFields) && !empty($this->measureFields)) {
                $this->prepareTablesUsedForQuery($this->measureFields);
            }
            $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll    
            $measuresFldsAll = implode(',',$measureSelectRes['measureSelectionArr']);

            $aggDateFromat = '%e-%c';
            if($this->aggregateSelection == 'weeks'){
                //$aggDateFromat = '%u-%b';
                $aggDateFromat = '%u';
            }
            else if($this->aggregateSelection == 'months')
                $aggDateFromat = '%M';

            $query = "SELECT DISTINCT ".$accountField." AS ACCOUNT, ".
                    "MAX(".$this->settingVars->maintable.".mydate) as MYDATE, ".
                    "DATE_FORMAT(".$this->settingVars->maintable.".mydate, '".$aggDateFromat."') as FORMATED_DATE, ".
                     $measuresFldsAll.
                     " FROM " . $this->settingVars->tablename .' '. trim($this->queryPart) . 
                     " AND (" . filters\timeFilter::$tyWeekRange . " OR " . filters\timeFilter::$lyWeekRange . ")".
                     " AND ".$this->settingVars->maintable.".mydate = '".$hardStopToDate."'".
                     " GROUP BY ACCOUNT, FORMATED_DATE".
                     " HAVING ".$havingTYValue." > 0 ";

            $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
            if ($redisOutput === false) {
                $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
                $this->redisCache->setDataForHash($result);
            } else {
                $result = $redisOutput;
            }

            $this->excludeNonCurrentCustomerQuery = '';
            if (is_array($result) && !empty($result)) {
                $allActAccount = array_column($result, 'ACCOUNT');
                if($conditionType == 'arg'){ // GENERATE THE STRING WHICH WILL USED TO SEND THE DATA AS ARGUMENT ON PERL SCRIPT
                    return $allActAccount;
                }else{
                    $data           = implode(',',$allActAccount);
                    $data           = mysqli_real_escape_string($this->queryVars->linkid,$data);
                    $arr            = explode(",", $data);
                    $str            = implode("','", array_map('trim',$arr));
                    $allActAcc            = "'" . $str . "'";
                    //$allActAcc = mysqli_real_escape_string($this->queryVars->linkid, '"' . implode( '","', $allActAccount) . '"');
                    $this->excludeNonCurrentCustomerQuery = " AND ".$accountField." IN (".$allActAcc.") ";
                }
            }
        }
    }

    public function getColumnChartData() {

        if($this->excludeNonCurrentCustomerData && empty($this->excludeNonCurrentCustomerQuery)){
            $this->jsonOutput['columnChartNoData'] = 'No data found for this combination.';
            return ;
        }

        $requestedCombination = explode("-", $_REQUEST['timeFrame']);
        $requestedYear        = $requestedCombination[1];
        $requestedSeason      = $requestedCombination[0];
        $this->settingVars->tableUsedForQuery = $this->measureFields = array();

        /*$measureArrayBkp = $this->settingVars->measureArray;
        foreach ($this->settingVars->measureArray as $key => $value) {
            if($value['ATTR'] == 'SUM'){
                $this->settingVars->measureArray[$key]['ATTR'] = 'AVG';
            }else if('PRICE'){
                $this->settingVars->measureArray[$key]['ATTR'] = 'AVGPRICE';
            }
        }*/
        $measureSelectRes = $this->prepareMeasureSelectPart();
        //$this->settingVars->measureArray = $measureArrayBkp;
        
        $havingTYValue = $measureSelectRes['havingTYValue'];
        $havingLYValue = $measureSelectRes['havingLYValue'];
        $this->measureFields = $measureSelectRes['measureSelectionArr'];
        
        if(isset($_REQUEST['ACCOUNT']) && !empty($_REQUEST['ACCOUNT'])){
            $this->measureFields[] = $_REQUEST['ACCOUNT'];
            $accountField = $_REQUEST['ACCOUNT'];
        }else if(isset($this->jsonOutput["defaultSelectedField"]) && !empty($this->jsonOutput["defaultSelectedField"])){
            $this->measureFields[] = $this->jsonOutput["defaultSelectedField"];
            $accountField = $this->jsonOutput["defaultSelectedField"];
        } else {
            $accountField = $this->pnameField;
            $this->measureFields[] = $this->pnameField;
        }
        
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll

        $timeFilters = '';
        $maintable = $this->settingVars->maintable;
        $this->jsonOutput['thisYear'] = $ty = (int)$requestedYear;
        $this->jsonOutput['lastYear'] = $ly = $requestedYear - 1;

        if (is_array($this->settingVars->tyDates) && !empty($this->settingVars->tyDates)) {
            foreach ($this->settingVars->tyDates as $tyDate) {
                $tyMydatePart = explode('-', $tyDate);
                $tyMydatePart[0] = $tyMydatePart[0]-1;
                $lyDate = implode('-', $tyMydatePart);

                $colsHeader['TY'][] = array("FORMATED_DATE" => date('D-d-M', strtotime($tyDate)), "FORMATED_DATE_DAY" => date('D', strtotime($tyDate)) ,"MYDATE" => $tyDate);
                $colsHeader['LY'][] = array("FORMATED_DATE" => date('D-d-M', strtotime($lyDate)), "FORMATED_DATE_DAY" => date('D', strtotime($lyDate)) ,"MYDATE" => $lyDate);
                $dateArray[$tyDate] = date('j-n', strtotime($tyDate));
            }
        }
        $measuresFldsAll = implode(',',$measureSelectRes['measureSelectionArr']);
        
        $addDaysTy   = array_column($colsHeader['TY'], 'FORMATED_DATE_DAY');
        $daysTyCount = array_count_values($addDaysTy);

        $addDaysLy   = array_column($colsHeader['LY'], 'FORMATED_DATE_DAY');
        $daysLyCount = array_count_values($addDaysLy);
        
        $query = "SELECT ".
            "DATE_FORMAT(".$maintable.".mydate, '%w') as FORMATED_DATE_NUM, ".
            "DATE_FORMAT(".$maintable.".mydate, '%a') as FORMATED_DATE, ".
            $measuresFldsAll.
            " FROM " . $this->settingVars->tablename .' '. trim($this->queryPart) . 
            " AND (" . filters\timeFilter::$tyWeekRange . " OR " . filters\timeFilter::$lyWeekRange . ")".
            $this->excludeNonCurrentCustomerQuery.
            " GROUP BY FORMATED_DATE, FORMATED_DATE_NUM ORDER BY FORMATED_DATE_NUM ASC";

        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }
        if (is_array($result) && !empty($result)) {
            $requiredGridFields = array("FORMATED_DATE", $havingTYValue, $havingLYValue);
            $result = $this->redisCache->getRequiredData($result, $requiredGridFields, '', $havingTYValue, $havingLYValue);
            
            foreach ($result as $key => $value) {
                if(isset($daysTyCount) && isset($daysTyCount[$value['FORMATED_DATE']])) {
                    $tyDayCnt = $daysTyCount[$value['FORMATED_DATE']];
                    $result[$key][$havingTYValue] = $tyDayCnt > 0 ? ($value[$havingTYValue] / $tyDayCnt) : 0; 
                }else{
                    $result[$key][$havingTYValue] = 0; 
                }

                if(isset($daysLyCount) && isset($daysLyCount[$value['FORMATED_DATE']])) {
                    $lyDayCnt = $daysLyCount[$value['FORMATED_DATE']];
                    $result[$key][$havingLYValue] = $lyDayCnt > 0 ? ($value[$havingLYValue] / $lyDayCnt) : 0; 
                }else{
                    $result[$key][$havingLYValue] = 0; 
                }
            }

            $columnChartDataDisp = array_column($result, 'FORMATED_DATE');
            $columnChartDataTY   = array_column($result, $havingTYValue);
            $columnChartDataLY   = array_column($result, $havingLYValue);
            $columnChartDataSeries[] = ['name'=>'This Year','data'=>$columnChartDataTY,'color'=>'#21558E','spacing'=>0];
            $columnChartDataSeries[] = ['name'=>'Last Year','data'=>$columnChartDataLY,'color'=>'#BD191A','spacing'=>0];
            $this->jsonOutput['columnChartData']['columnChartDataDisp'] = $columnChartDataDisp;
            $this->jsonOutput['columnChartData']['columnChartDataSeries'] = $columnChartDataSeries;
        }
    }

    public function gridData() {

        if($this->excludeNonCurrentCustomerData && empty($this->excludeNonCurrentCustomerQuery)){
            $this->jsonOutput['gridDataNoData'] = 'No data found for this combination.';
            return ;
        }

        $requestedCombination = explode("-", $_REQUEST['timeFrame']);
        $requestedYear        = $requestedCombination[1];
        $requestedSeason      = $requestedCombination[0];
        
        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $measureSelectRes = $this->prepareMeasureSelectPart();
        $havingTYValue = $measureSelectRes['havingTYValue'];
        $havingLYValue = $measureSelectRes['havingLYValue'];
        $this->measureFields = $measureSelectRes['measureSelectionArr'];
        // $this->measureFields[] = $this->settingVars->skutable.'.pname';
        
        // $accountField = $this->settingVars->maintable.".PNAME";
        if(isset($_REQUEST['ACCOUNT']) && !empty($_REQUEST['ACCOUNT'])){
            $this->measureFields[] = $_REQUEST['ACCOUNT'];
            $accountField = $_REQUEST['ACCOUNT'];
        } else if(isset($this->jsonOutput["defaultSelectedField"]) && !empty($this->jsonOutput["defaultSelectedField"])){
            $this->measureFields[] = $this->jsonOutput["defaultSelectedField"];
            $accountField = $this->jsonOutput["defaultSelectedField"];
        } else {
            $accountField = $this->pnameField;
            $this->measureFields[] = $this->pnameField;
        }
        
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll

        $timeFilters = '';
        $maintable = $this->settingVars->maintable;
        $this->jsonOutput['thisYear'] = $ty = (int)$requestedYear;
        $this->jsonOutput['lastYear'] = $ly = $requestedYear - 1;

        $aggDateFromat        = '%e-%c';
        $aggDateColHeadFormat = 'D-d-M';
        $aggDateArrayFormat   = 'j-n';
        $aggDate              = 'Y-m-d';
        if($this->aggregateSelection == 'weeks'){
            //$aggDateFromat = '%u-%b';
            //$aggDateColHeadFormat = 'W-M';
            //$aggDateArrayFormat = 'W-M';
            //$aggDate = 'Y-m-W';
            $aggDateFromat = '%u';
            $aggDateColHeadFormat = 'W';
            $aggDateArrayFormat = 'W';
            $aggDate = 'Y-W';
        }
        else if($this->aggregateSelection == 'months'){
            $aggDateFromat = '%M';
            $aggDateColHeadFormat = 'M';
            $aggDateArrayFormat = 'F';
            $aggDate = 'Y-m';
        }
        if (is_array($this->settingVars->tyDates) && !empty($this->settingVars->tyDates)) {
            /*array_walk($this->settingVars->tyDates, function (&$item, $key, $fmt) { $item = date($fmt, strtotime($item)); },$aggDate);
            $this->settingVars->tyDates = array_values(array_unique($this->settingVars->tyDates));*/
            $arrayTmp['TY'] = [];
            $arrayTmp['LY'] = [];
            
            if (count($this->settingVars->tyDates) > count($this->settingVars->lyDates)) {
                $dates = $this->settingVars->tyDates;
                $dateKey = "TY";
            } else {
                $dates = $this->settingVars->lyDates;
                $dateKey = "LY";
            }

            foreach ($dates as $tyDate) {
                $tyMydatePart = explode('-', $tyDate);
                $tyDateHasError = $lyDateHasError = false;

                if ($dateKey == 'TY') {
                    $tyMydatePart[0] = $tyMydatePart[0]-1;
                    if ($this->aggregateSelection == 'days' && !checkdate($tyMydatePart[1], $tyMydatePart[2], $tyMydatePart[0])) {
                        $lyDateHasError = true;
                        $tyMydatePart[0] = $tyMydatePart[0]+1;
                    }
                    $lyDate = implode('-', $tyMydatePart);
                } else {
                    $lyDate = $tyDate;
                    $tyMydatePart[0] = $tyMydatePart[0]+1;
                    if ($this->aggregateSelection == 'days' && !checkdate($tyMydatePart[1], $tyMydatePart[2], $tyMydatePart[0])) {
                        $tyDateHasError = true;
                        $tyMydatePart[0] = $tyMydatePart[0]-1;
                    }

                    $tyDate = implode('-', $tyMydatePart);
                }
                
                $tmpDtFmt = date($aggDate, strtotime($tyDate));
                if(!isset($arrayTmp['TY'][$tmpDtFmt])){
                    $dateArray[$tmpDtFmt] = date($aggDateArrayFormat, strtotime($tyDate));
                    $colsHeader['TY'][] = array("FORMATED_DATE" => ((!$tyDateHasError) ? date($aggDateColHeadFormat, strtotime($tyDate)) : "NULL"), "MYDATE" => $tmpDtFmt);
                    $arrayTmp['TY'][$tmpDtFmt] = $tmpDtFmt;
                }

                $tmpDtLyFmt = date($aggDate, strtotime($lyDate));
                if(!isset($arrayTmp['LY'][$tmpDtLyFmt])){
                    if (isset($_REQUEST['facings']) && !empty($_REQUEST['facings'])) {
                        $sign = ($_REQUEST['facings'] >= 0) ? " + " : " - ";
                        $lyDate = date('Y-m-d', strtotime($lyDate . $sign . abs($_REQUEST['facings']) . ' days'));
                    }

                    $colsHeader['LY'][] = array("FORMATED_DATE" => ((!$lyDateHasError) ? date($aggDateColHeadFormat, strtotime($lyDate)) : "NULL"), "MYDATE" => $tmpDtLyFmt);
                    $arrayTmp['LY'][$tmpDtLyFmt] = $tmpDtLyFmt;
                }
            }
        }
        $measuresFldsAll = implode(',',$measureSelectRes['measureSelectionArr']);

        $query = "SELECT ".$accountField." AS ACCOUNT, ".
            "MAX(".$maintable.".mydate) as MYDATE, ".
            "DATE_FORMAT(".$maintable.".mydate, '".$aggDateFromat."') as FORMATED_DATE, ".
            "MAX(".$maintable.".seasonal_year) as YEAR, ".
            $measuresFldsAll.
            " FROM " . $this->settingVars->tablename .' '. trim($this->queryPart) . 
            " AND (" . filters\timeFilter::$tyWeekRange . " OR " . filters\timeFilter::$lyWeekRange . ") ".
            $this->excludeNonCurrentCustomerQuery.
            " GROUP BY ACCOUNT, FORMATED_DATE ORDER BY MYDATE ASC";
         // echo $query;
        // exit();

        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }

        $dataPnameSum = [];
        $dataPnameSumIndexed = [];
        if (is_array($result) && !empty($result)) {
            if (isset($_REQUEST['facings']) && !empty($_REQUEST['facings'])) {

                foreach ($result as $seasonalDetail) {
                    $seasonalAccountData[$seasonalDetail['ACCOUNT']][] = $seasonalDetail;
                }

                foreach ($seasonalAccountData as $account => $accountData) {
                    foreach ($accountData as $key => $seasonalData) {
                        if (!in_array($seasonalData['MYDATE'], array_keys($dateArray))) {
                            $lyMydatePart = explode('-', $seasonalData['MYDATE']);
                            $lyMydatePart[0] = $lyMydatePart[0]+1;
                            $tyDate = implode('-', $lyMydatePart);
                            $sign = ($_REQUEST['facings'] >= 0) ? " - " : " + ";
                            $tyDate = date($aggDate, strtotime($tyDate . $sign . abs($_REQUEST['facings']) . ' days'));

                            $searchKey = array_search($tyDate, array_column($accountData, 'MYDATE'));

                            if ($searchKey === false && in_array($tyDate, array_keys($dateArray))) {
                                $seasonalData['MYDATE'] = $tyDate;
                                $seasonalData['YEAR'] = date('Y', strtotime($tyDate));
                                $seasonalData['FORMATED_DATE'] = date($aggDateArrayFormat, strtotime($tyDate));
                                $seasonalDataArray[$seasonalData['ACCOUNT']][$seasonalData['FORMATED_DATE']] = $seasonalData;
                                $dataPnameSum[$seasonalData['ACCOUNT']] += $seasonalData[$havingTYValue];
                            }
                            continue;
                        }

                        $tyMydatePart = explode('-', $seasonalData['MYDATE']);
                        $tyMydatePart[0] = $tyMydatePart[0]-1;
                        $lyDate = implode('-', $tyMydatePart);
                        $sign = ($_REQUEST['facings'] >= 0) ? " + " : " - ";
                        $lyDate = date($aggDateArrayFormat, strtotime($lyDate . $sign . abs($_REQUEST['facings']) . ' days'));

                        $searchKey = array_search($lyDate, array_column($accountData, 'FORMATED_DATE'));

                        if (is_numeric($searchKey)) {
                            $seasonalData[$havingLYValue] = $accountData[$searchKey][$havingLYValue];
                        }

                        $seasonalDataArray[$seasonalData['ACCOUNT']][$seasonalData['FORMATED_DATE']] = $seasonalData;
                        $dataPnameSum[$seasonalData['ACCOUNT']] += $seasonalData[$havingTYValue];
                    }
                }
            } else {
                foreach ($result as $seasonalData) {
                    $seasonalDataArray[$seasonalData['ACCOUNT']][$seasonalData['FORMATED_DATE']] = $seasonalData;
                    $dataPnameSum[$seasonalData['ACCOUNT']] += $seasonalData[$havingTYValue];
                }
            }
            arsort($dataPnameSum);
            $rankCnt = 2;
            foreach ($dataPnameSum as $pnm=>$pdt) {
                $dataPnameSum[$pnm] = $rankCnt;
                $rankCnt++;
            }
        }

        /*print_r($seasonalDataArray);
        print_r($dateArray);
        exit;*/

        $cnt = 0; $cmpTyTotal = $cmpLyTotal = [];
        foreach (array_keys($seasonalDataArray) as $account) {
            $tmp = $tmp1 = $tmp2 = $cumTmp = $cumTmp1 = $cumTmp2 = array();
            $tmp['ACCOUNT'] = $account;
            $tmp['TOTAL'] = (isset($dataPnameSum[$account])) ? $dataPnameSum[$account] : 0;

            $tmp1['ACCOUNT'] = $account;
            $tmp1['TOTAL'] = (isset($dataPnameSum[$account])) ? $dataPnameSum[$account] : 0;

            $tmp2['ACCOUNT'] = $account;
            $tmp2['TOTAL'] = (isset($dataPnameSum[$account])) ? $dataPnameSum[$account] : 0;

            $cumTmp['ACCOUNT'] = $account;
            $cumTmp['TOTAL'] = (isset($dataPnameSum[$account])) ? $dataPnameSum[$account] : 0;

            $cumTmp1['ACCOUNT'] = $account;
            $cumTmp1['TOTAL'] = (isset($dataPnameSum[$account])) ? $dataPnameSum[$account] : 0;

            $cumTmp2['ACCOUNT'] = $account;
            $cumTmp2['TOTAL'] = (isset($dataPnameSum[$account])) ? $dataPnameSum[$account] : 0;

            $cumTyValue = $cumLyValue = 0;
            foreach ($dateArray as $dayMydate => $dayMonth) {
                $tyMydate = $dayMydate;
                
                if (isset($seasonalDataArray[$account][$dayMonth])) {
                    // $dateKey = 'dt'.
                    $data = $seasonalDataArray[$account][$dayMonth];
                    $dtKey = 'dt'.str_replace('-','',$tyMydate);
                    $dtTotalKey = 'dt'.str_replace('-','',$tyMydate);

                    $tmp[$dtKey] = $data[$havingTYValue]*1;
                    $tmp['YEAR'] = $ty;
                    $tmp['RANK'] = (int)'1'.$data['YEAR'];
                    $tmp['ROWDESC'] = '';
                    $tmp['ROWDESCCHART'] = 'Daily Sales';
                    $tmp['highlightRow'] = 1;

                    $cmpTyTotal[$dtTotalKey] += $data[$havingTYValue];
                    
                    $tmp1[$dtKey] = $data[$havingLYValue]*1;
                    $tmp1['YEAR'] = $ly;
                    $tmp1['RANK'] = (int)'1'.($data['YEAR']-1);
                    $tmp1['ROWDESC'] = 'Daily Sales';
                    $tmp1['ROWDESCCHART'] = 'Daily Sales';
                    $tmp1['highlightRow'] = 1;
                    
                    $cmpLyTotal[$dtTotalKey] += $data[$havingLYValue];

                    $tmp2[$dtKey] = ($data[$havingLYValue] > 0) ? (($data[$havingTYValue] - $data[$havingLYValue]) / $data[$havingLYValue])*100 : 0;
                    $tmp2[$dtKey] = $tmp2[$dtKey];
                    $tmp2['YEAR'] = 'YOY% daily';
                    $tmp2['RANK'] = '1YOY% daily';
                    $tmp2['ROWDESC'] = '';
                    $tmp2['ROWDESCCHART'] = 'Daily Sales';
                    $tmp2['highlightRow'] = 2;

                    //$cumTyValue += round($data[$havingTYValue]);
                    $cumTyValue += $data[$havingTYValue];
                    $cumTmp[$dtKey] = $cumTyValue;
                    $cumTmp['YEAR'] = $ty;
                    $cumTmp['RANK'] = (int)'2'.$data['YEAR'];
                    $cumTmp['ROWDESC'] = '';
                    $cumTmp['ROWDESCCHART'] = 'Cumulative Sales';
                    $cumTmp['highlightRow'] = 0;
                    
                    //$cumLyValue += round($data[$havingLYValue]);
                    $cumLyValue += $data[$havingLYValue];
                    $cumTmp1[$dtKey] = $cumLyValue;
                    $cumTmp1['YEAR'] = $ly;
                    $cumTmp1['RANK'] = (int)'2'.($data['YEAR']-1);
                    $cumTmp1['ROWDESC'] = 'Cumulative Sales';
                    $cumTmp1['ROWDESCCHART'] = 'Cumulative Sales';
                    $cumTmp1['highlightRow'] = 0;

                    $cumTmp2[$dtKey] = ($cumLyValue > 0) ? (($cumTyValue - $cumLyValue) / $cumLyValue)*100 : 0;
                    $cumTmp2[$dtKey] = $cumTmp2[$dtKey];
                    $cumTmp2['YEAR'] = 'YOY% Cum.';
                    $cumTmp2['RANK'] = '2YOY% Cum.';
                    $cumTmp2['ROWDESC'] = '';
                    $cumTmp2['ROWDESCCHART'] = 'Cumulative Sales';
                    $cumTmp2['highlightRow'] = 2;
                }
            }
            $finalData[] = $tmp1;
            $finalData[] = $tmp;
            $finalData[] = $tmp2;
            $finalData[] = $cumTmp1;
            $finalData[] = $cumTmp;
            $finalData[] = $cumTmp2;
            $cnt++;
        }

        /*[START] ADDING THE TOTAL COLUMN*/
        $cmpTyLyYoyTotal = $cmpTyLyYoyCumTotal = $cmpTyCumTotal = $cmpLyCumTotal = [];

        ksort($cmpTyTotal);
        ksort($cmpLyTotal);
        
        if(isset($cmpTyTotal) && isset($cmpLyTotal)) {
            $cumTyValue = $cumLyValue = 0;
            foreach ($cmpTyTotal as $cpTykey => $cmTyVal) {
                $cpTykey = $cpTykey; 
                $cumTyValue += $cmTyVal;
                $cmpTyCumTotal[$cpTykey] = $cumTyValue;
                $cmpTyLyYoyTotal[$cpTykey] = ($cmpLyTotal[$cpTykey] > 0) ? ((($cmTyVal - $cmpLyTotal[$cpTykey]) / $cmpLyTotal[$cpTykey])*100) : 0;

                /*[START] GETTING CUMULATIVE LY VALUE*/
                    $cumLyValue += $cmpLyTotal[$cpTykey];
                    $cmpLyCumTotal[$cpTykey] = $cumLyValue;

                    $cmpTyLyYoyCumTotal[$cpTykey] = ($cmpLyCumTotal[$cpTykey] > 0) ? ((($cmpTyCumTotal[$cpTykey] - $cmpLyCumTotal[$cpTykey]) / $cmpLyCumTotal[$cpTykey])*100) : 0;
                /*[END] GETTING CUMULATIVE LY VALUE*/
            }

            $cmpTyTotal['TOTAL']        = 1;
            $cmpLyTotal['TOTAL']        = 1;
            $cmpTyLyYoyTotal['TOTAL']   = 1;
            $cmpTyCumTotal['TOTAL']     = 1;
            $cmpLyCumTotal['TOTAL']     = 1;
            $cmpTyLyYoyCumTotal['TOTAL']= 1;
            $dataPnameSum['TOTAL']      = 1;

            $cmpTyTotal['ACCOUNT']      = 'TOTAL';
            $cmpTyTotal['YEAR']         = $ty;
            $cmpTyTotal['RANK']         = (int)'1'.$ty;
            $cmpTyTotal['ROWDESC']      = '';
            $cmpTyTotal['ROWDESCCHART'] = 'Daily Sales';
            $cmpTyTotal['highlightRow'] = 1;

            $cmpLyTotal['ACCOUNT']   = 'TOTAL';
            $cmpLyTotal['YEAR']    = $ly;
            $cmpLyTotal['RANK']    = (int)'1'.$ly;
            $cmpLyTotal['ROWDESC'] = 'Daily Sales';
            $cmpLyTotal['ROWDESCCHART'] = 'Daily Sales';
            $cmpLyTotal['highlightRow'] = 1;
            
            $cmpTyLyYoyTotal['ACCOUNT'] = 'TOTAL';
            $cmpTyLyYoyTotal['YEAR']  = 'YOY% daily';
            $cmpTyLyYoyTotal['RANK']  = '1YOY% daily';
            $cmpTyLyYoyTotal['ROWDESC'] = '';
            $cmpTyLyYoyTotal['ROWDESCCHART'] = 'Daily Sales';
            $cmpTyLyYoyTotal['highlightRow'] = 2;

            $cmpTyCumTotal['ACCOUNT'] = 'TOTAL';
            $cmpTyCumTotal['YEAR']  = $ty;
            $cmpTyCumTotal['RANK']  = (int)'2'.$ty;
            $cmpTyCumTotal['ROWDESC'] = '';
            $cmpTyCumTotal['ROWDESCCHART'] = 'Cumulative Sales';
            $cmpTyCumTotal['highlightRow'] = 0;

            $cmpLyCumTotal['ACCOUNT'] = 'TOTAL';
            $cmpLyCumTotal['YEAR']  = $ly;
            $cmpLyCumTotal['RANK']  = (int)'2'.$ly;
            $cmpLyCumTotal['ROWDESC'] = 'Cumulative Sales';
            $cmpLyCumTotal['ROWDESCCHART'] = 'Cumulative Sales';
            $cmpLyCumTotal['highlightRow'] = 0;

            $cmpTyLyYoyCumTotal['ACCOUNT'] = 'TOTAL';
            $cmpTyLyYoyCumTotal['YEAR']  = 'YOY% Cum.';
            $cmpTyLyYoyCumTotal['RANK']  = '2YOY% Cum.';
            $cmpTyLyYoyCumTotal['ROWDESC'] = '';
            $cmpTyLyYoyCumTotal['ROWDESCCHART'] = 'Cumulative Sales';
            $cmpTyLyYoyCumTotal['highlightRow'] = 2;
        
            $finalData[] = $cmpTyTotal;
            $finalData[] = $cmpLyTotal;
            $finalData[] = $cmpTyLyYoyTotal;
            $finalData[] = $cmpTyCumTotal;
            $finalData[] = $cmpLyCumTotal;
            $finalData[] = $cmpTyLyYoyCumTotal;
        }
        /*[END] ADDING THE TOTAL COLUMN*/
        
        $totalArray = array_column($finalData, 'TOTAL');
        //array_multisort($totalArray, SORT_DESC, SORT_NUMERIC, $finalData);
        array_multisort($totalArray, SORT_ASC, SORT_NUMERIC, $finalData);

        $this->jsonOutput['gridAllColumnsHeader'] = $colsHeader;
        $this->jsonOutput['gridData'] = $finalData;
        
        if(!empty($colsHeader)) {
            $dtFrm = 'TY';
            //$this->jsonOutput['gridDataRang'] = date('d F Y',strtotime($colsHeader[$dtFrm][0]['MYDATE']))." To ".date('d F Y',strtotime($colsHeader[$dtFrm][(count($colsHeader[$dtFrm])-1)]['MYDATE']));
        }

        if(isset($dataPnameSum) && count($dataPnameSum)>0){
            foreach ($dataPnameSum as $k1 => $v1) {
               $dataPnameSumIndexed['val'.$v1]=$k1;
            }
        }
        $this->jsonOutput['gridDataTotal'] = $dataPnameSumIndexed;
    }

    public function prepareExportData(){
        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $measureSelectRes = $this->prepareMeasureSelectPart();
        
        $havingTYValue = $measureSelectRes['havingTYValue'];
        $havingLYValue = $measureSelectRes['havingLYValue'];
        
        $selectionFields = []; $selectionGroupBy = [];
        ksort($redisOutput);
        foreach ($this->productAndMarketFilterData as $key => $value) {
            if(!empty($value)){
                $tbl = '';
                foreach ($value as $k => $val) {
                    if(!empty($val)){
                        $tbl = explode('.', $val);
                        if(!empty($tbl[0])){
                             $selectionFields[] = $val;
                        }
                        if(!empty($tbl[1])){
                            $asCnt = explode(' AS ', $tbl[1]);
                            if(!empty($asCnt[1]))
                                $selectionGroupBy[] = $asCnt[1];
                        }
                    }
                }
            }
        }

        /*[START] ADDING THE EXCLUDE NON CURRENT CUSTOMR FILTER*/
        $excludeNonCurrentCustomerDataCondition = [];
        if($this->excludeNonCurrentCustomerData && count($selectionFields) > 0){
           foreach ($selectionFields as $k1 => $v1) {
                $asCnt = explode(' AS ', $v1);
                if(!empty($asCnt[0])){
                    $arrExcludeNCCDTmp = $this->setExcludeNonCurrentCutomers(trim($asCnt[0]),'arg');
                    $excludeNonCurrentCustomerDataCondition[$asCnt[1]] = $arrExcludeNCCDTmp;
                }
            }
        }
        /*[END] ADDING THE EXCLUDE NON CURRENT CUSTOMR FILTER*/
        
        $allActiveFiltersNames = array_column($this->settingVars->dataArray,'NAME_CSV','NAME_ALIASE');
        $this->measureFields = array_merge(array_values($selectionFields),$measureSelectRes['measureSelectionArr']);
        $this->measureFields[] = $this->settingVars->skutable.'.pname_rollup2';
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll

        $timeFilters = '';
        $maintable = $this->settingVars->maintable;
        //$requestedCombination = explode("-", $_REQUEST['FromSeason']);
        $requestedCombination = explode("-", $_REQUEST['timeFrame']);
        $requestedYear = $requestedCombination[1];
        $requestedSeason = $requestedCombination[0];

        $thisYear = $ty = (int)$requestedYear;
        $lastYear = $ly = $requestedYear - 1;

        $selPart = ', '.$maintable.'.seasonal_year AS DATAYEAR ';
        $measuresFldsAll = implode(',',$measureSelectRes['measureSelectionArr']);
        
        $aggDateFromat        = '%e-%c';
        if($this->aggregateSelection == 'weeks'){
            //$aggDateFromat = '%u-%b';
            $aggDateFromat = '%u';
        }
        else if($this->aggregateSelection == 'months')
            $aggDateFromat = '%M';

        $query = "SELECT ". 
            implode(',', $selectionFields).
            ", MAX(".$maintable.".mydate) as MYDATE, ".
            "DATE_FORMAT(".$maintable.".mydate, '".$aggDateFromat."') as FORMATED_DATE, ".
            "MAX(".$maintable.".seasonal_year) as YEAR, ".
            $measuresFldsAll.
            " FROM " . $this->settingVars->tablename .' '. trim($this->queryPart) . 
            " AND (" . filters\timeFilter::$tyWeekRange . " OR " . filters\timeFilter::$lyWeekRange . ")".
            //$excludeNonCurrentCustomerDataCondition.
            " GROUP BY ".implode(',',$selectionGroupBy)." ,FORMATED_DATE ORDER BY MYDATE ASC";

        //$activeXlsWsheet = ['PNAME'=>'SKU'];
        $activeXlsWsheet = [];
        if(!empty($selectionGroupBy)){
            foreach ($selectionGroupBy as $key => $sgb) {
                if(isset($allActiveFiltersNames[$sgb]))
                    $activeXlsWsheet[$sgb] = $allActiveFiltersNames[$sgb];
            }
        }
        //ksort($activeXlsWsheet);
        /*[START] GETTING THE SELECTED FILTERS*/
            $appliedFilters = [];
            if(isset($_REQUEST['timeFrame']) && !empty($_REQUEST['timeFrame'])){
                $appliedFilters[] = 'Time Selection##'.$_REQUEST['timeFrame'];
            }
            if(isset($this->aggregateSelection) && !empty($this->aggregateSelection)){
                $appliedFilters[] = 'Time Scale##'. ucfirst($this->aggregateSelection);
            }
            if(isset($_REQUEST['toDate']) && !empty($_REQUEST['toDate'])){
                $appliedFilters[] = 'Hard Stop##'.date('d-M',strtotime($_REQUEST['toDate']));
            }
            if($this->excludeNonCurrentCustomerData){
                $appliedFilters[] = 'Exclude Non-Current Customer Data##YES';
            }
            foreach ($this->settingVars->pageArray["MEASURE_SELECTION_LIST"] as $key => $measureVal) {
                if($measureVal['measureID'] == $_REQUEST['ValueVolume'])
                    $appliedFilters[] = 'Measure Selection##'.$measureVal['measureName'];
            }

            if(isset($_REQUEST['FS']) && is_array($_REQUEST['FS']) && count($_REQUEST['FS'])>0){
                foreach($_REQUEST['FS'] as $ky=>$valDt) {
                    if(!empty($valDt)) {
                        if (isset($this->settingVars->dataArray) && isset($this->settingVars->dataArray[$ky])) {
                            $dataList = $valDt;
                            if(isset($this->settingVars->dataArray[$ky]['ID'])) {
                                //$dataList = $this->getAllDataFromIds($this->settingVars->dataArray[$ky]['TYPE'],$ky,$dataList);
                            }
                            //if($this->settingVars->dataArray[$ky]['TYPE'] == 'P') {
                                $appliedFilters[] = $this->settingVars->dataArray[$ky]['NAME_CSV'].'##'.urldecode($dataList);
                            //}else if($this->settingVars->dataArray[$ky]['TYPE'] == 'M') {
                                //$marketFilterData[$this->settingVars->dataArray[$ky]['NAME_CSV']] = urldecode($dataList);
                            //}
                        }
                    }
                }
            }
        /*[END] GETTING THE SELECTED FILTERS*/
        
        /*[START] GETTING THE START AND END DATE LOGIC*/
            $fromYear       = $thisYear;
            $toYear         = (($this->settingVars->fromToDateRange['fromMonth']-$this->settingVars->fromToDateRange['toMonth']) > 0) ? $fromYear+1 : $fromYear;
            $tyFromDate     = $fromYear.'-'.$this->settingVars->fromToDateRange['fromMonth'].'-'.$this->settingVars->fromToDateRange['fromDay'];
            if (isset($_REQUEST["toDate"]) && !empty($_REQUEST["toDate"])) {
                $tyToDate = $_REQUEST["toDate"];
            } else {
                $tyToDate = $this->settingVars->fromToDateRange['maxDate'];
            }
        /*[END] GETTING THE START AND END DATE LOGIC*/
    return array($query,$thisYear,$lastYear,$activeXlsWsheet,$havingTYValue,$havingLYValue,$appliedFilters,$tyFromDate,$tyToDate,$excludeNonCurrentCustomerDataCondition);
    }

    public function getAllFilterData(){
        $selectionFields = []; $selectionTables = []; 
        foreach ($this->productAndMarketFilterData as $key => $value) {
            if(!empty($value)){
                $tbl = '';
                foreach ($value as $k => $val) {
                    if(!empty($val)){
                        $tbl = explode('.', $val);
                        if(!empty($tbl[0])){
                             $selectionTables[] = $tbl[0];
                             $selectionFields[] = $val;
                        }
                    }
                }
            }
        }

        $this->measureFields = array_values($selectionFields);
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->queryPart = $this->getAll();
        $query = "SELECT DISTINCT ".implode(',', $this->measureFields)." FROM ".$this->settingVars->tablename." ". $this->queryPart;

        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }
        return $result;
    }

    public function getAllDataFromIds($type,$id,$data){
        $redisOutput = $this->redisCache->checkAndReadByStaticHashFromCache('productAndMarketSelectionTabsRedisList');
        if ($redisOutput === false) {
            return $data;
        } else {
            
            $selectionTabsRedisList = $redisOutput[$type];
            if(!is_array($selectionTabsRedisList) || empty($selectionTabsRedisList))
                 return $data;

            if($type == 'FSG')
            {
                $storeList = array_column($selectionTabsRedisList, 'data');
                $Skey = array_search($data, $storeList);
                if(is_numeric($Skey))
                {
                    $data = $selectionTabsRedisList[$Skey]['label'];
                }
            }
            else
            {
                $aKey = array_search($id, array_column($selectionTabsRedisList, 'data'));
                if(isset($selectionTabsRedisList[$aKey]) && isset($selectionTabsRedisList[$aKey]['dataList']) && is_array($selectionTabsRedisList[$aKey]['dataList']) && count($selectionTabsRedisList[$aKey]['dataList'])>0){

                    $mainArr = array_column($selectionTabsRedisList[$aKey]['dataList'], 'label','data');
                    $fndata = [];
                    $data = explode(',', $data);
                    if(is_array($data) && count($data)>0){
                        foreach ($data as $k => $vl) {
                            if(isset($mainArr[$vl]) && !empty($mainArr[$vl]))
                                $fndata[] = $mainArr[$vl];
                            else
                                $fndata[] = $vl;
                        }
                        $data = implode(',', $fndata);
                    }
                }
            }
            return $data;
            
        }
    }
    
    public function Tree($name, $tagName, $indexInDataArray) {
        $negcolor = array('EE0202', 'D20202', 'B50202', 'A00202', '8C0101', '760101', '640101', '510101', '400101', '2E0101');
        $color = array('002D00', '014301', '015901', '016B01', '018001', '019701', '01AC01', '02C502', '02DB02', '02FB02');
        
        $dataStore = array();
        $max = 0;
        $min = 0;

        $measureSelectRes = $this->prepareMeasureSelectPart();
        $this->measureFields = $measureSelectRes['measureFields'];
        
        $projectStructureTypePage = "projectstructure\\".$this->settingVars->projectStructureType."_PAGES";
        $structurePageClass = new $projectStructureTypePage();
        $extraWhereClause = $structurePageClass->extraWhereClause($this->settingVars, $this->measureFields);
        
        $this->measureFields[] = $name;
        
        $measureSelectionArr = $measureSelectRes['measureSelectionArr'];
        $havingTYValue = $measureSelectRes['havingTYValue'];
        $havingLYValue = $measureSelectRes['havingLYValue'];
        
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->queryPart = $this->getAll(). $extraWhereClause;        
        
        $query = "SELECT $name AS ACCOUNT, ". implode(",", $measureSelectionArr).
                " FROM " . $this->settingVars->tablename .' '. trim($this->queryPart) .
                " AND (" . filters\timeFilter::$tyWeekRange . " OR " . filters\timeFilter::$lyWeekRange . ") " .
                $this->excludeNonCurrentCustomerQuery.
                "GROUP BY ACCOUNT";

        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);

        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }

        $total = array_column($result, $havingTYValue);
        $this->TOTAL_TY_SALES = array_sum($total);
        
        $fields = $this->redisCache->getRequiredFieldsArray(array("ACCOUNT"), false, $this->settingVars->pageArray["MEASURE_SELECTION_LIST"]);
        
        $this->redisCache->getRequiredData($result, $fields, $havingTYValue);
        
        foreach ($result as $key => $row) {
            if($key < 250) {
                $row['ACCOUNT'] = str_replace('\'', ' ', $row['ACCOUNT']);
                $thisyearval = $row[$havingTYValue];
                $lastyearval = $row[$havingLYValue];

                if ($lastyearval > 0) {
                    $var = (($thisyearval - $lastyearval) / $lastyearval) * 100;
                    if ($var > $max)
                        $max = $var;
                    if ($var < $min)
                        $min = $var;
                    array_push($dataStore, $row['ACCOUNT'] . "#" . $thisyearval . "#" . $var);
                }else {
                    $var = 0;
                    array_push($dataStore, $row['ACCOUNT'] . "#" . $thisyearval . "#" . $var);
                }
            }
        }

        $tempResult = array();
        for ($i = 0; $i < count($dataStore); $i++) {
            $d = explode('#', $dataStore[$i]);

            if ($this->TOTAL_TY_SALES == 0 || $this->TOTAL_TY_SALES == NULL) {
                $percent = number_format(0);
            } else {
                $percent = number_format(($d[1] / $this->TOTAL_TY_SALES) * 100, 1);
                $chartval2 = number_format((($this->TOTAL_TY_SALES - $d[1]) / $this->TOTAL_TY_SALES) * 100, 1);
            }

            if ($d[2] >= 0) {
                $c = 0;
                $range = 10;
                for ($j = 0; $j <= $max; $j+=$range) {
                    if ($d[1] > 0) {
                        if (number_format($d[2], 2, '.', '') >= 100) {
                            $temp = array(
                                //'@attributes' => array(
                                'name' => htmlspecialchars_decode(strtoupper($d[0]))
                                , 'value' => $d[1]
                                , 'color' => $color[9]
                                , 'alpha' => 1
                                , 'varp' => $d[2]
                                , 'chartval1' => $percent
                                , 'chartval2' => $chartval2
                                    // )
                            );
                            $tempResult[$tagName][] = $temp;
                            break;
                        } else {
                            if ((number_format($d[2], 5, '.', '') >= number_format($j, 5, '.', '')) && (number_format($d[2], 5, '.', '') < number_format(($j + $range), 5, '.', ''))) {
                                $temp = array(
                                    //'@attributes' => array(
                                    'name' => htmlspecialchars_decode(strtoupper($d[0]))
                                    , 'value' => $d[1]
                                    , 'color' => $color[$c]
                                    , 'alpha' => 1
                                    , 'varp' => $d[2]
                                    , 'chartval1' => $percent
                                    , 'chartval2' => $chartval2
                                        //)
                                );
                                $tempResult[$tagName][] = $temp;
                                break;
                            }
                            $c++;
                        }
                    }
                }
            } else {
                $c = 0;
                $range = abs($min / 10);
                for ($j = $min; $j <= 0; $j+=$range) {
                    if ((number_format($d[2], 5, '.', '') >= number_format($j, 5, '.', '')) && (number_format($d[2], 5, '.', '') < number_format(($j + $range), 5, '.', ''))) {
                        $temp = array(
                            //'@attributes' => array(
                            'name' => htmlspecialchars_decode(strtoupper($d[0]))
                            , 'value' => $d[1]
                            , 'color' => $negcolor[$c]
                            , 'alpha' => 1
                            , 'varp' => $d[2]
                            , 'chartval1' => $percent
                            , 'chartval2' => $chartval2
                                //)
                        );
                        $tempResult[$tagName][] = $temp;
                        break;
                    }
                    $c++;
                }
            }
        }
        $this->jsonOutput['treeMapData'] = $tempResult;
    }    
    
}
?> 