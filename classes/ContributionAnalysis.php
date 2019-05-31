<?php

namespace classes;

use datahelper;
use projectsettings;
use filters;
use utils;
use db;
use config;

class ContributionAnalysis extends config\UlConfig {

    public $accountArray = array();
    public $allOtherCount;
    public $accountName;
    public $getField;
    public $getPageAnalysisField;
    public $skipDbcolumnArray = false;
    public $dbColumnsArray;
    public $displayCsvNameArray;
    //public $getTableName;
    public $pageName;

    public function __construct() {
        $this->allOtherCount = 20;
    }

    public function go($settingVars) {
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES
        $this->settingVars->pageName = (empty($this->settingVars->pageName)) ? $this->settingVars->pageID . '_ContributionAnalysis' : $settingVars->pageName;

        $this->redisCache = new utils\RedisCache($this->queryVars);
        $this->settingVars->useRequiredTablesOnly = false;
        $this->queryPart = $this->getAll();  //PREPARE TABLE JOIN STRING USING this class getAll

        $action = $_REQUEST["action"];
        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
            $this->fetchConfig(); // Fetching filter configuration for page
            /*[START] CODE FOR INLINE FILTER STYLE*/
                $filtersDisplayType = $this->getPageConfiguration('filter_disp_type', $this->settingVars->pageID);
                if(!empty($filtersDisplayType) && isset($filtersDisplayType[0])) {
                    $this->jsonOutput['gridConfig']['enabledFiltersDispType'] = $filtersDisplayType[0];
                }
            /*[END] CODE FOR INLINE FILTER STYLE*/
        }else if($action == 'skuChange') {
            $this->configurePage();
            $this->skuSelect();
        } else if($action == 'fetchInlineMarketAndProductFilter') {
            $this->settingVars->pageName = '';
            $this->fetchInlineMarketAndProductFilterData();
        }else{
            $this->configurePage();
            $this->GridAll(); //ADDING TO OUTPUT
        }

        return $this->jsonOutput;
    }

    public function configurePage() {
        $extraColumns = $this->getPageConfiguration('extra_columns', $this->settingVars->pageID);
        /*[START] GETTING THE EXTRA FIELDS DETAILS*/
            if (isset($extraColumns) && !empty($extraColumns) && is_array($extraColumns)){
                $this->buildDataArray($extraColumns,true,false);
                foreach ($extraColumns as $ky => $vl) {
                    $this->settingVars->pageArray[$this->settingVars->pageName]["EXTRACOLUMNS"][] = ["BAR_ACCOUNT_TITLE" => $this->displayCsvNameArray[$vl], "ACCOUNT" => strtoupper($this->dbColumnsArray[$vl])];
                }
            }
        /*[END] GETTING THE EXTRA FIELDS DETAILS*/
        if (isset($_REQUEST['pageAnalysisField']) && !empty($_REQUEST['pageAnalysisField']))
            $this->getPageAnalysisField = $_REQUEST['pageAnalysisField'];

        if (isset($_REQUEST['Field']) && !empty($_REQUEST['Field'])) {
            $getField = $_REQUEST['Field'];
        } else {
            $this->buildPageArray();
            $getField = $this->settingVars->pageArray[$this->settingVars->pageName]["ACCOUNT"]; // DEFINED ACCOUNT NAME FROM PAGE SETTING
            $this->jsonOutput['selectedField'] = $getField;
        }
        
        $this->isShowSkuIDCol = false;
        $this->buildDataArray(array($getField), true, false);
        $gridFieldPart = explode("#", $getField);
        $accountField = strtoupper($this->dbColumnsArray[$gridFieldPart[0]]);
        $accountField = (count($gridFieldPart) > 1) ? strtoupper($accountField."_".$this->dbColumnsArray[$gridFieldPart[1]]) : $accountField;
        
        $this->isShowSkuIDCol = (count($gridFieldPart) > 1) ? true : false;
        
        $this->accountID = (isset($this->settingVars->dataArray[$accountField]) && isset($this->settingVars->dataArray[$accountField]['ID'])) ? $this->settingVars->dataArray[$accountField]['ID'] : $this->settingVars->dataArray[$accountField]['NAME'];
        $this->accountName = $this->settingVars->dataArray[$accountField]['NAME'];

        if ($this->isShowSkuIDCol)
            $this->jsonOutput['skuIDColName'] = $this->settingVars->dataArray[$accountField]['ID_CSV'];

        $filtersColumns = $this->getPageConfiguration('filter_settings', $this->settingVars->pageID);
        if (in_array('hardstop-selection', $filtersColumns) && isset($_REQUEST['timeFrame']) &&  isset($_REQUEST['fetchHardStopDate']) && $_REQUEST['fetchHardStopDate'] == 'YES') {
            $showAsOutput = (isset($_REQUEST['fetchHardStopDate']) && $_REQUEST['fetchHardStopDate'] == 'YES') ? true : false;
            $requestedCombination = explode("-", $_REQUEST['timeFrame']);
            $requestedYear        = $requestedCombination[1];
            $requestedSeason      = $requestedCombination[0];

            $objDatahelper = new datahelper\Time_Selection_DataCollectors($this->settingVars);
            $objDatahelper->getAllSeasonalHardStopDates($this->jsonOutput,$requestedYear, $showAsOutput);    
        }
    }

    public function fetchConfig() {
        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
            $this->jsonOutput['gridConfig'] = array(
                'enabledFilters' => $this->getPageConfiguration('filter_settings', $this->settingVars->pageID)
            );
            $this->jsonOutput['showContributionAnalysisPopup'] = $this->settingVars->showContributionAnalysisPopup;
        }
    }

    public function buildDataArray($fields, $isCsvColumn, $appendTableNameWithDbColumn = false ) {
        if (empty($fields))
            return false;

        $configurationCheck = new config\ConfigurationCheck($this->settingVars, $this->queryVars);
        $configurationCheck->buildDataArray($fields, $isCsvColumn, $appendTableNameWithDbColumn);
        $this->dbColumnsArray = $configurationCheck->dbColumnsArray;
        $this->displayCsvNameArray = $configurationCheck->displayCsvNameArray;
        return;
    }

    public function buildPageArray() {
        $tables = array();
        if ($this->settingVars->isDynamicPage){
            $tables = $this->getPageConfiguration('table_settings', $this->settingVars->pageID);
            $tablesSelectedField = $this->getPageConfiguration('table_settings_default_field', $this->settingVars->pageID);
        }else {
            if (isset($this->getPageAnalysisField) && $this->getPageAnalysisField == 'Product')
                $tables = array($this->settingVars->skutable);
            else if (isset($this->getPageAnalysisField) && $this->getPageAnalysisField == 'Store')
                $tables = array("market");
        }

        if (is_array($tables) && !empty($tables)) {
            $fields = $tmpArr = array();
            $fields = $this->prepareFieldsFromFieldSelectionSettings($tables, false);
            /*foreach ($tables as $table) {
                if(is_array($this->queryVars->projectConfiguration) && isset($this->queryVars->projectConfiguration[$table . "_settings"]) ){
                    $settings = explode("|", $this->queryVars->projectConfiguration[$table . "_settings"]);
                    foreach ($settings as $key => $field) {
                        $val = explode("#", $field);
                        
                        if($table == 'market')
                        {
                            $tbl = ($table == 'market') ? $this->settingVars->storetable : $table;
                            $fields[] = (count($val) > 1) ? $tbl.".".$val[0]."#".$tbl.".".$val[1] : $tbl.".".$val[0];
                            //$fields[] = (($table == 'market') ? $this->settingVars->storetable : $table).".".$val[0];
                        }
                        elseif($table == 'account')
                        {
                            $tbl = ($table == 'account') ? $this->settingVars->accounttable : $table;
                            $fields[] = (count($val) > 1) ? $tbl.".".$val[0]."#".$tbl.".".$val[1] : $tbl.".".$val[0];
                            //$fields[] = (($table == 'account') ? $this->settingVars->accounttable : $table).".".$val[0];
                        }
                        elseif($table == 'product')
                        {
                            $tbl = (isset($this->settingVars->skutable) && !empty($this->settingVars->skutable)) ? $this->settingVars->skutable : $table;
                            $fields[] = (count($val) > 1) ? $tbl.".".$val[0]."#".$tbl.".".$val[1] : $tbl.".".$val[0];
                            //$fields[] = ((isset($this->settingVars->skutable) && !empty($this->settingVars->skutable)) ? $this->settingVars->skutable : $table).".".$val[0];
                        }
                        else
                        {
                            $fields[] = (count($val) > 1) ? $table.".".$val[0]."#".$table.".".$val[1] : $table.".".$val[0];
                            //$fields[] = $table.".".$val[0];
                        }
                        
                        if ($key == 0) {
                            if($table == 'market')
                                $appendTable = ($table == 'market') ? $this->settingVars->storetable : $table;
                            elseif($table == 'account')
                                $appendTable = ($table == 'account') ? $this->settingVars->accounttable : $table;
                            elseif($table == 'product')
                                $appendTable = (isset($this->settingVars->skutable) && !empty($this->settingVars->skutable)) ? $this->settingVars->skutable : $table;
                            else
                                $appendTable = $table;
                            
                            //$this->settingVars->pageArray[$this->settingVars->pageName]["ACCOUNT"] = $appendTable . "." . $val[0];
                            $this->settingVars->pageArray[$this->settingVars->pageName]["ACCOUNT"] = (count($val) > 1) ? $appendTable.".".$val[0]."#".$appendTable.".".$val[1] : $appendTable.".".$val[0];
                        }
                    }
                }
            }*/

            $this->buildDataArray($fields, false, true);
            if(isset($tablesSelectedField) && is_array($tablesSelectedField) && !empty($tablesSelectedField)){
                $this->settingVars->pageArray[$this->settingVars->pageName]["ACCOUNT"] = $tablesSelectedField[0];
            } else {
                $account = explode("#", $this->settingVars->pageArray[$this->settingVars->pageName]["ACCOUNT"]);
                $search = array_search($account[0],$this->dbColumnsArray);
                if($search !== false) {
                    if (count($account) > 1) {
                        $search1 = array_search($account[1],$this->dbColumnsArray);
                        $this->settingVars->pageArray[$this->settingVars->pageName]["ACCOUNT"] = $search."#".$search1;
                    }
                    else
                        $this->settingVars->pageArray[$this->settingVars->pageName]["ACCOUNT"] = $search;
                }
            }
            
            foreach ($fields as $field) {
                $val = explode("#", $field);
                $search = array_search($val[0],$this->dbColumnsArray);
                
                if($search !== false) {
                    if ($this->settingVars->pageArray[$this->settingVars->pageName]["ACCOUNT"] == $search && $search !== false) {
                        if(count($val) > 1) {
                            $search1 = array_search($val[1],$this->dbColumnsArray);
                            $this->settingVars->pageArray[$this->settingVars->pageName]["ACCOUNT"] = $search."#".$search1;
                        }
                        else
                            $this->settingVars->pageArray[$this->settingVars->pageName]["ACCOUNT"] = $search;
                    }

                    if(count($val) > 1) {
                        $search1 = array_search($val[1],$this->dbColumnsArray);
                        $tmpArr[] = array('label' => $this->displayCsvNameArray[$search], 'data' => $search."#".$search1);
                    }
                    else
                        $tmpArr[] = array('label' => $this->displayCsvNameArray[$search], 'data' => $search);
                }
            }
            $this->jsonOutput['fieldSelection'] = $tmpArr;

        } elseif (isset($this->settingVars->pageArray[$this->settingVars->pageName]["ACCOUNT"]) &&
                !empty($this->settingVars->pageArray[$this->settingVars->pageName]["ACCOUNT"])) {
            $this->skipDbcolumnArray = true;
        } else {
            $response = array("configuration" => array("status" => "fail", "messages" => array("Page isn't configured properly.")));
            echo json_encode($response);
            exit();
        }
    }

    public function fetchInlineMarketAndProductFilterData() {
        /*[START] PREPARING THE PRODUCT INLINE FILTER DATA */
        if(isset($this->queryVars->projectConfiguration) && isset($this->queryVars->projectConfiguration['has_product_market_filter_disp_type']) && $this->queryVars->projectConfiguration['has_product_market_filter_disp_type']==1) {
            $configureProject = new config\ConfigureProject($this->settingVars, $this->queryVars);
            $configureProject->fetch_all_product_and_marketSelection_data(true);
            $extraWhere = filters\timeFilter::$seasonalTimeframeExtraWhere." AND (" .filters\timeFilter::$tyWeekRange. " OR ".filters\timeFilter::$lyWeekRange.") ";
            $this->getAllProductAndMarketInlineFilterData($this->queryVars, $this->settingVars, $extraWhere);
        }
        /*[END] PREPARING THE PRODUCT INLINE FILTER DATA */
    }

     public function GridAll() {
        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        
        $measureSelectRes = $this->prepareMeasureSelectPart();
        $this->measureFields = $measureSelectRes['measureFields'];
        
        $projectStructureTypePage = "projectstructure\\".$this->settingVars->projectStructureType."_PAGES";
        $structurePageClass = new $projectStructureTypePage();
        $extraWhereClause = $structurePageClass->extraWhereClause($this->settingVars, $this->measureFields);        

        $this->measureFields[] = $this->accountID;
        $this->measureFields[] = $this->accountName;
        $ExtraCols = [];
        if(isset($this->settingVars->pageArray[$this->settingVars->pageName]) && isset($this->settingVars->pageArray[$this->settingVars->pageName]["EXTRACOLUMNS"]) && is_array($this->settingVars->pageArray[$this->settingVars->pageName]["EXTRACOLUMNS"]) && count($this->settingVars->pageArray[$this->settingVars->pageName]["EXTRACOLUMNS"])>0){
            foreach ($this->settingVars->pageArray[$this->settingVars->pageName]["EXTRACOLUMNS"] as $ky => $extraValue) {

                $ExtraCols[] = ['NAME' => $this->settingVars->dataArray[$extraValue['ACCOUNT']]["NAME"]." AS ".$this->settingVars->dataArray[$extraValue['ACCOUNT']]['NAME_ALIASE'], 'NAME_ALIASE' => $this->settingVars->dataArray[$extraValue['ACCOUNT']]['NAME_ALIASE'], 'NAME_CSV' => $this->settingVars->dataArray[$extraValue['ACCOUNT']]['NAME_CSV']];

                $this->measureFields[] = $this->settingVars->dataArray[$extraValue['ACCOUNT']]["NAME"];
            }
        }
        
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->queryPart = $this->getAll(). $extraWhereClause;
        
        $measureSelectionArr = $measureSelectRes['measureSelectionArr'];
        $havingTYValue = $measureSelectRes['havingTYValue'];
        $havingLYValue = $measureSelectRes['havingLYValue'];

        $measureSelect = implode(",", $measureSelectionArr);
        $query = "SELECT MAX(".$this->accountID.") as skuID, ".$this->accountName." AS ACCOUNT" . ", ". $measureSelect." ".
                (!empty($ExtraCols) && count($ExtraCols)>0 ? ", " . implode(',', array_column($ExtraCols,'NAME'))." " : "");
                if(filters\timeFilter::$lyWeekRange == "")
                    $query .= ",0 AS LYEAR ";
                $query .= "FROM " . $this->settingVars->tablename .' '. trim($this->queryPart);
                if(filters\timeFilter::$lyWeekRange != "")
                    $query .= " AND (" . filters\timeFilter::$tyWeekRange . " OR " . filters\timeFilter::$lyWeekRange . ") ";
                else
                    $query .= " AND (" . filters\timeFilter::$tyWeekRange. ") ";
                $query .= "GROUP BY ACCOUNT".(!empty($ExtraCols) && count($ExtraCols)>0 ? ", " . implode(',', array_column($ExtraCols,'NAME_ALIASE')) : "");
        
        /*$query = "SELECT a.ID" .
                ",a.ACCOUNT" .
                ",a.TYEAR" .
                ",a.LYEAR" .
                ",(a.TYEAR-a.LYEAR) AS VAR " .
                "FROM (
                SELECT $id AS ID" .
                ",$name AS ACCOUNT" .
                ", ". $measureSelect." ";

                if(filters\timeFilter::$lyWeekRange == "")
                    $query .= ",0 AS LYEAR ";

                $query .= "FROM " . $this->settingVars->tablename . $this->queryPart;
                if(filters\timeFilter::$lyWeekRange != "")
                    $query .= " AND (" . filters\timeFilter::$tyWeekRange . " OR " . filters\timeFilter::$lyWeekRange . ") ";
                else
                    $query .= " AND (" . filters\timeFilter::$tyWeekRange. ") ";
                $query .= "GROUP BY ID,ACCOUNT
                ) AS a ORDER BY ACCOUNT ASC";*/
        //echo $query; exit;
        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $resultCached = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($resultCached);
        } else {
            $resultCached = $redisOutput;
        }
        
        if(!empty($ExtraCols) && count($ExtraCols)>0)
            $requiredGridFields = array_merge(array("ACCOUNT", "skuID", $havingTYValue, $havingLYValue),array_column($ExtraCols,'NAME_ALIASE'));
        else
            $requiredGridFields = array("ACCOUNT", "skuID", $havingTYValue, $havingLYValue);

        $resultCached = $this->redisCache->getRequiredData($resultCached, $requiredGridFields, $havingTYValue, $havingTYValue, $havingLYValue);

        $result = [];
        if(!empty($ExtraCols) && count($ExtraCols)>0)
            $EXTRA_FIELDS = array_column($ExtraCols,'NAME_ALIASE');

        if(isset($resultCached) && is_array($resultCached) && count($resultCached)>0){
            foreach ($resultCached as $key => $val) {
                $result[$key]['ID']      = $val['skuID'];
                $result[$key]['ACCOUNT'] = $val['ACCOUNT'];
                $result[$key]['TYEAR']   = $val[$havingTYValue];
                $result[$key]['LYEAR']   = $val[$havingLYValue];
                $result[$key]['VAR']     = ($val[$havingTYValue]-$val[$havingLYValue]);

                if(!empty($EXTRA_FIELDS) && count($EXTRA_FIELDS)>0){
                    foreach($EXTRA_FIELDS as $ky=>$extrafields){
                        $result[$key][$extrafields] = htmlspecialchars_decode($val[$extrafields]);
                    }
                }
            }
            $result = utils\SortUtility::sort2DArray($result, 'ACCOUNT', utils\SortTypes::$SORT_ASCENDING);
        }
        $newArray = array();
        $growArray = array();
        $dropArray = array();
        $declineArray = array();
        $value = array();
        foreach ($result as $key => $data) {
            if ($data['TYEAR'] > 0 && $data['LYEAR'] == 0) {
                $newArray['TOTAL'] += $data['VAR']; //ADD TO NEW-ITEM-TOTAL
                $newArray['DATA'][] = $data; //STORING ALL NEW-ITEM
            } else if ($data['TYEAR'] > $data['LYEAR'] && $data['LYEAR'] != 0) {
                $growArray['TOTAL'] += $data['VAR']; //ADD TO GROWER-ITEM-TOTAL
                $growArray['DATA'][] = $data; //STORING ALL GROWER-ITEM
            } else if ($data['TYEAR'] == 0 && $data['LYEAR'] > 0) {
                $dropArray['TOTAL'] += $data['VAR']; //ADD TO DROP-ITEM-TOTAL
                $dropArray['DATA'][] = $data; //STORING ALL DROP-ITEM
            } else if ($data['LYEAR'] > $data['TYEAR'] && $data['TYEAR'] != 0) {
                $declineArray['TOTAL'] += $data['VAR']; //ADD TO DECLINER-ITEM-TOTAL
                $declineArray['DATA'][] = $data; //STORING ALL DECLINE-ITEM
            }
        }

        //SORT BY 'VAR' DESCENDING AND THEN TAKE TOP 20 ITEMS FROM ALL-NEW-ITEM STORAGE
        $newGridArray = array_slice(utils\SortUtility::sort2DArray($newArray['DATA'], 'VAR', utils\SortTypes::$SORT_DESCENDING), 0, 20);
        //CALCULATE TOP-20 VAR SUM
        $topTwentyVarsSum = array_sum(array_column($newGridArray, 'VAR'));
        //IF TOTAL ITEM EXCEEDS 20 , PUSH 'ALL OTHER' HAVING ACCUMULATED VARIANCE OF ALL OTHER ITEMS
        if (count($newArray['DATA']) > $this->allOtherCount) {
            $data = array('ID' => 'ALL OTHER'
                , 'ACCOUNT' => 'ALL OTHER'
                , 'VAR' => $newArray['TOTAL'] - $topTwentyVarsSum);
            $newGridArray[] = $data;
        }

        //SORT BY 'VAR' DESCENDING AND THEN TAKE TOP 20 ITEMS FROM ALL-GROWER-ITEM STORAGE
        $growersGridArray = array_slice(utils\SortUtility::sort2DArray($growArray['DATA'], 'VAR', utils\SortTypes::$SORT_DESCENDING), 0, 20);
        //CALCULATE TOP-20 VAR SUM
        $topTwentyVarsSum = array_sum(array_column($growersGridArray, 'VAR'));
        //IF TOTAL ITEM EXCEEDS 20 , PUSH 'ALL OTHER' HAVING ACCUMULATED VARIANCE OF ALL OTHER ITEMS
        if (count($growArray['DATA']) > $this->allOtherCount) {
            $data = array('ID' => 'ALL OTHER'
                , 'ACCOUNT' => 'ALL OTHER'
                , 'VAR' => $growArray['TOTAL'] - $topTwentyVarsSum);
            $growersGridArray[] = $data;
        }


        //SORT BY 'VAR' ASCENDING AND THEN TAKE TOP 20 ITEMS FROM ALL-DROP-ITEM STORAGE 
        $dropGridArray = array_slice(utils\SortUtility::sort2DArray($dropArray['DATA'], 'VAR', utils\SortTypes::$SORT_ASCENDING), 0, 20);
        //CALCULATE TOP-20 VAR SUM
        $topTwentyVarsSum = array_sum(array_column($dropGridArray, 'VAR'));
        //IF TOTAL ITEM EXCEEDS 20 , PUSH 'ALL OTHER' HAVING ACCUMULATED VARIANCE OF ALL OTHER ITEMS
        if (count($dropArray['DATA']) > $this->allOtherCount) {
            $data = array('ID' => 'ALL OTHER'
                , 'ACCOUNT' => 'ALL OTHER'
                , 'VAR' => $dropArray['TOTAL'] - $topTwentyVarsSum);
            $dropGridArray[] = $data;
        }

        //SORT BY 'VAR' ASCENDING AND THEN TAKE TOP 20 ITEMS FROM ALL-DECLINER-ITEM STORAGE 
        $declinerGridArray = array_slice(utils\SortUtility::sort2DArray($declineArray['DATA'], 'VAR', utils\SortTypes::$SORT_ASCENDING), 0, 20);
        //CALCULATE TOP-20 VAR SUM
        $topTwentyVarsSum = array_sum(array_column($declinerGridArray, 'VAR'));
        //IF TOTAL ITEM EXCEEDS 20 , PUSH 'ALL OTHER' HAVING ACCUMULATED VARIANCE OF ALL OTHER ITEMS
        if (count($declineArray['DATA']) > $this->allOtherCount) {
            $data = array('ID' => 'ALL OTHER'
                , 'ACCOUNT' => 'ALL OTHER'
                , 'VAR' => $declineArray['TOTAL'] - $topTwentyVarsSum);
            $declinerGridArray[] = $data;
        }


        $gridNames = array('NPD' => $newGridArray, 'GROWERS' => $growersGridArray, 'DROPS' => $dropGridArray, 'DECLINERS' => $declinerGridArray);
        $totals = array('NPD' => $newArray['TOTAL'], 'GROWERS' => $growArray['TOTAL'], 'DROPS' => $dropArray['TOTAL'], 'DECLINERS' => $declineArray['TOTAL']);

        
        //PREPARE XML OUTPUT DATA
        if(!empty($ExtraCols) && count($ExtraCols)>0){
            $EXTRA_FIELDS = array_column($ExtraCols,'NAME_ALIASE');
        }
        foreach ($gridNames as $gridKey => $currentGrid) { //TRAVERSING ALL GRID NAMES ['NPD' , .......]
            $tempResult = array();
            foreach ($currentGrid as $data) {
                $temp['ID'] = htmlspecialchars_decode($data['ID']);
                $temp['ACCOUNT'] = htmlspecialchars_decode($data['ACCOUNT']);
                $temp['VAR'] = $data['VAR'];
                $temp['PERCENT'] = (float)number_format(($data['VAR'] / $totals[$gridKey]) * 100, 1, '.', '');

                if(!empty($EXTRA_FIELDS) && count($EXTRA_FIELDS)>0){
                    foreach($EXTRA_FIELDS as $ky=>$extrafields){
                        $temp[$extrafields] = htmlspecialchars_decode($data[$extrafields]);
                    }
                }

                $tempResult[] = $temp;
            }
            $this->jsonOutput[$gridKey] = $tempResult;
        }
        
        //ADD TOTALS DATA TO XML OUTPUT DATA
        $acummulatedTotal = 0;
        $tempData = array();
        foreach ($totals as $key => $data) {
            $acummulatedTotal += $data;
            //$tempData[$key] = number_format($data, 0, '', '');
            $tempData[$key] = (!empty($data)) ? $data : 0;
        }
        $tempData['TOTAL'] = $acummulatedTotal;
        $this->jsonOutput['TOTALS'] = $tempData;
        $this->jsonOutput['extraFields'] = $ExtraCols;
        $this->jsonOutput['isShowSkuIDCol'] = $this->isShowSkuIDCol;
    }

    public function getAll() {
        $tablejoins_and_filters = parent::getAll();

        if (isset($_GET['SUPPLIER']) && $_GET['SUPPLIER'] == "YES") {
            $tablejoins_and_filters .= " " . $this->settingVars->supplierHelperLink . " ";
        }

        return $tablejoins_and_filters;
    }

    private function skuSelect() {
        /*REQUEST vars used on the LineChartAllData function to get all available measures */
        $_REQUEST['requestedChartMeasure'] = 'M'.$_REQUEST['ValueVolume'];
        
        $this->settingVars->tableUsedForQuery = array();
        datahelper\Common_Data_Fetching_Functions::$settingVars = $this->settingVars;
        datahelper\Common_Data_Fetching_Functions::$queryVars = $this->queryVars;

        $measuresFields = datahelper\Common_Data_Fetching_Functions::getAllMeasuresExtraTable();
        $measuresFields[] = $this->accountName;

        $this->prepareTablesUsedForQuery($measuresFields);
        $this->settingVars->useRequiredTablesOnly = true;
        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll

        if(isset($_REQUEST['ACCOUNT']) && !empty(urldecode($_REQUEST['ACCOUNT'])))
            $this->queryPart .=" AND ".$this->accountName." = '".trim(urldecode($_REQUEST['ACCOUNT']))."' ";

        datahelper\Common_Data_Fetching_Functions::LineChartAllData($this->queryPart, $this->jsonOutput);

        $requiredChartFields = array('ACCOUNT','TYACCOUNT','LYACCOUNT','TYMYDATE','LYMYDATE');
        $requiredChartFields = $this->redisCache->getRequiredFieldsArray($requiredChartFields,true, $this->settingVars->measureArray);
        $lineChartData = $this->redisCache->getRequiredData($this->jsonOutput['LineChart'],$requiredChartFields);

        /*[START] Checking for the mission date range*/
        if(isset($lineChartData) && is_array($lineChartData) && count($lineChartData)>0 && !empty($this->settingVars->dateField)) {
                $this->settingVars->tableUsedForQuery = $this->measureFields = array();
                $mydateSelect = $this->settingVars->getMydateSelect($this->settingVars->dateField);
                $query = "SELECT " . $this->settingVars->yearperiod . " AS YEAR" .
                        ",". $this->settingVars->weekperiod . " AS WEEK" .
                        (( $this->settingVars->dateField) ? ",".$mydateSelect." " : " ") .
                        "FROM " .  $this->settingVars->timeHelperTables .  $this->settingVars->timeHelperLink .
                        "GROUP BY YEAR,WEEK " .
                        "ORDER BY YEAR DESC,WEEK DESC";
                $queryHash = md5($query);
                $redisOutput = $this->redisCache->checkAndReadByStaticHashFromCache('time_filter_data', $queryHash);
                if ($redisOutput === false) {
                    $dateList = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_ARRAY);
                    $this->redisCache->setDataForSubKeyHash($dateList, $queryHash);
                } else {
                    $dateList = $redisOutput;
                }

                $allMeasurePeriod = [];
                if(isset($dateList) && is_array($dateList) && count($dateList)>0){
                    foreach ($dateList as $ky => $val) {
                        if(isset($val[2]) && !empty($val[2]))
                            $allMeasurePeriod[$val[0].'-'.$val[1]] = date('j M y', strtotime($val[2])); 
                    }
                }
                if(count($allMeasurePeriod)>0){
                    foreach ($lineChartData as $key => $value) {
                        if(isset($allMeasurePeriod[$value['ACCOUNT']]) && !empty($allMeasurePeriod[$value['ACCOUNT']]))
                            $lineChartData[$key]['TYMYDATE'] = $allMeasurePeriod[$value['ACCOUNT']];
                    }
                }
        }
        /*[END] Checking for the mission date range*/
        $this->jsonOutput['LineChart'] = $lineChartData;
    }
}
?>