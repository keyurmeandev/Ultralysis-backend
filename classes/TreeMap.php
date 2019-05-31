<?php

namespace classes;

use projectsettings;
use datahelper;
use filters;
use db;
use config;
use utils;

class TreeMap extends config\UlConfig {

    private $maps;
    private $querypart;
    private $TOTAL_TY_SALES;
    private $pageName;
    private $dbColumnsArray;
    private $displayCsvNameArray;

    /*     * ***
     * Default gateway function, should be similar in all DATA CLASS
     * arguments:
     *  $settingVars [project settingsGateway variables]
     * @return $jsonOutput with all data that should be sent to client app
     * *** */

    public function go($settingVars) {
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES

        $this->settingVars->pageName = (empty($this->settingVars->pageName)) ? $this->settingVars->pageID . '_TREEMAP' : $settingVars->pageName;

        if ($this->settingVars->isDynamicPage) {
            $this->buildMaps();
        } else {
            if (!isset($this->settingVars->pageArray[$this->settingVars->pageName]) ||
                    !isset($this->settingVars->pageArray[$this->settingVars->pageName]["MAPS"]) ||
                    empty($this->settingVars->pageArray[$this->settingVars->pageName]["MAPS"]))
                $this->configurationFailureMessage();
        }

        $this->redisCache = new utils\RedisCache($this->queryVars);
        
        $action = $_REQUEST["action"];
        //PREPAREING MAPS DATA    
        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
             $this->fetchConfig(); // Fetching filter configuration for page
             $this->initialSettings();
        }else if($action == 'skuChange') {
            $this->configurePage();
            $this->skuSelect();
        }else{
            $this->getMap();
        }
 
        return $this->jsonOutput;
    }

    public function fetchConfig() {
        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
            $this->jsonOutput['gridConfig'] = array(
                'enabledFilters' => $this->getPageConfiguration('filter_settings', $this->settingVars->pageID)
            );
        }
    }
    
    public function initialSettings(){
        foreach ($this->settingVars->pageArray[$this->settingVars->pageName]["MAPS"] as $name => $field) {
            $tabList = array();
            $tabList["data"] = $field;
            $tabList["name"] = $name;
            $this->jsonOutput["TREE_TAB_LIST"][] = $tabList;
        }
    }
    
    public function getMap(){
        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $field = $_REQUEST['accountField'];
        $this->measureFields = $this->prepareTablesUsedForMeasure($_REQUEST['ValueVolume']);

        $this->measureFields[] = $this->settingVars->dataArray[$field]['NAME'];
        $this->prepareTablesUsedForQuery($this->measureFields);
        $this->settingVars->useRequiredTablesOnly = true;
        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll

        //COLLECTING TOTAL SALES  
        //$this->TOTAL_TY_SALES = $this->getTotal();

        $this->Tree($this->settingVars->dataArray[$field]['NAME'], $field, $field);
    }

    public function buildDataArray($fields, $isCsvColumn, $appendTableNameWithDbColumn) {
        if (empty($fields))
            return false;

        $configurationCheck = new config\ConfigurationCheck($this->settingVars, $this->queryVars);
        $configurationCheck->buildDataArray($fields, $isCsvColumn, $appendTableNameWithDbColumn);
        $this->dbColumnsArray = $configurationCheck->dbColumnsArray;
        $this->displayCsvNameArray = $configurationCheck->displayCsvNameArray;
        return;
    }

    public function buildMaps() {
        $tables = $this->getPageConfiguration('table_settings', $this->settingVars->pageID);

        if (is_array($tables) && !empty($tables)) {
            $fields = $tmpArr = array();
            foreach ($tables as $table) {
                if(is_array($this->queryVars->projectConfiguration) && isset($this->queryVars->projectConfiguration[$table . "_settings"]) ){
                    $settings = explode("|", $this->queryVars->projectConfiguration[$table . "_settings"]);
                    foreach ($settings as $field) {
                        $val = explode("#", $field);
                        $fieldName = (($table == 'market') ? $this->settingVars->storetable : $table).".".$val[0];
                        $fieldName = ($table == 'account') ? $this->settingVars->accounttable.".".$val[0] : $fieldName;
                        $fields[] = $fieldName;
                    }
                }
            }
            
            $this->buildDataArray($fields, false, true);

            foreach ($this->dbColumnsArray as $csvCol => $dbCol)
                $tmpArr[$this->displayCsvNameArray[$csvCol]] = strtoupper($dbCol);

            $this->settingVars->pageArray[$this->settingVars->pageName]["MAPS"] = $tmpArr;
        } else {
            $this->configurationFailureMessage();
        }
    }

    /**     * ****
     * Calculates total sales for selected time range
     * returns totalSales
     * ***** */
/*     public function getTotal() {
        $options = array();
        
        $measureSelect = config\MeasureConfigure::prepareSelect($this->settingVars, $this->queryVars, array('M'.$_REQUEST['ValueVolume']), $options);
        $measureSelect = implode(", ", $measureSelect);

        $query = "SELECT ".$measureSelect." FROM " . $this->settingVars->tablename . $this->queryPart . " AND " . filters\timeFilter::$tyWeekRange;
        //$query = "SELECT SUM(" . $this->ValueVolume . ") AS TYEAR FROM " . $this->settingVars->tablename . $this->queryPart . " AND " . filters\timeFilter::$tyWeekRange;
        // echo $query;exit();
        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_ARRAY);
        return $result[0][0];
    } */

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
                "GROUP BY ACCOUNT";

        //echo $query; exit();
        
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
        $this->jsonOutput = $tempResult;
    }

    private function skuSelect() {
        /*REQUEST vars used on the LineChartAllData function to get all available measures */
        $_REQUEST['requestedChartMeasure'] = 'M'.$_REQUEST['ValueVolume'];
        
        $this->settingVars->tableUsedForQuery = array();
        datahelper\Common_Data_Fetching_Functions::$settingVars = $this->settingVars;
        datahelper\Common_Data_Fetching_Functions::$queryVars = $this->queryVars;

        $measuresFields = datahelper\Common_Data_Fetching_Functions::getAllMeasuresExtraTable();
        $measuresFields[] = $this->accountName;

        if(isset($_REQUEST['accountField']) && !empty($_REQUEST['accountField'])){
            $this->accountName = $_REQUEST['accountField'];
        }else{
            $this->accountName = $this->settingVars->dataArray[$accountField]['NAME'];
        }

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
}

?>