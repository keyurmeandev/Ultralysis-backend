<?php

namespace classes\lcl;

use db;
use filters;
use config;
use utils;
use datahelper;

class DetailedDriverAnalysis extends config\UlConfig {

    public function go($settingVars) {
        
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES

        $this->settingVars->pageName = (empty($this->settingVars->pageName)) ? $this->settingVars->pageID . '_DetailDriverAnalysisPage' : $this->settingVars->pageName;
        $this->queryPart = $this->getAll(); //USES OWN getAll FUNCTION

        $this->redisCache = new utils\RedisCache($this->queryVars);
        
        $this->showSSChart = false;

        if ($this->settingVars->isDynamicPage) {
            $enabledCharts = $this->getPageConfiguration('chart_settings', $this->settingVars->pageID);
            $this->enabledMeasureListOutput = $this->enabledMeasureList = $this->requiredMeasureList = array();
            
            foreach ($enabledCharts as $chart) {
                $chart = explode("#", $chart);
                if($chart[1] == "" && $this->settingVars->hasMeasureFilter) {
                    $response = array("configuration" => array("status" => "fail", "messages" => array("Measure selection is not configured.")));
                    echo json_encode($response);
                    exit();
                }
                else if($chart[1] == "" )
                    $this->enabledMeasureList[$chart[0]] = $this->settingVars->detailedDriverAnalysisChartTabMappings[$chart[0]];
                else
                    $this->enabledMeasureList[$chart[0]] = "M".$chart[1];
                $this->enabledMeasureListOutput[] = $chart[0];
                if($chart[1] != "")
                    $this->requiredMeasureList[] = $chart[1];
                if ($chart[0] == 'sellingStoreChart') {
                    $this->showSSChart = true;
                    $this->storeField = $this->getPageConfiguration('store_field', $this->settingVars->pageID)[0];
                    $this->buildDataArray(array($this->storeField));
                    $this->buildPageArray();
                }
            }
            
        } else {
            $this->showSSChart = true;
            if (!isset($this->settingVars->pageArray[$this->settingVars->pageName]) ||
                    empty($this->settingVars->pageArray[$this->settingVars->pageName]))
                $this->configurationFailureMessage();

            $this->storeID = (isset($this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['STORE_FIELD']]) &&
                    isset($this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['STORE_FIELD']]['ID'])) ?
                    $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['STORE_FIELD']]['ID'] :
                    $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['STORE_FIELD']]['NAME'];
        }

        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
            $this->jsonOutput['pageConfig']['enabledFilters'] = $this->getPageConfiguration('filter_settings', $this->settingVars->pageID);
            $this->jsonOutput['enabledCharts'] = $this->enabledMeasureListOutput;
        } else {
            $this->prepare_DDA_Data(); //ADDING TO OUTPUT [DDA => DETAILED DRIVER ANALYSIS]
        }

        return $this->jsonOutput;
    }

    public function prepare_DDA_Data() {
        /*         * ** ADDING YEAR AND WEEKS THOSE ARE AVAILABLE IN DATABASE *** */
        $redisCache = new utils\RedisCache($this->queryVars);
        $mydateSelect = $this->settingVars->getMydateSelect($this->settingVars->dateField);
        $query = "SELECT " . $this->settingVars->yearperiod . " AS YEAR" .
                ",".$this->settingVars->weekperiod . " AS WEEK" .
                (($this->settingVars->dateField) ? ",".$mydateSelect." AS MYDATE " : " ") .
                "FROM " . $this->settingVars->timeHelperTables . $this->settingVars->timeHelperLink .
                " AND (" . filters\timeFilter::$tyWeekRange . " OR " . filters\timeFilter::$lyWeekRange . ") ".
                "GROUP BY YEAR,WEEK " .
                "ORDER BY YEAR ASC,WEEK ASC";
        $queryHash = md5($query);
        $redisOutput = $redisCache->checkAndReadByStaticHashFromCache('time_filter_data', $queryHash);

        if ($redisOutput === false) {
            $dateList = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $redisCache->setDataForSubKeyHash($dateList, $queryHash);
        } else {
            $dateList = $redisOutput;
        }
        
		$data = $dateList[0];
		$maxYear = $data[0];
		$maxWeek = $data[1];

        $numberFrom = filters\timeFilter::$FromYear . (filters\timeFilter::$FromWeek < 10 ? "0" . filters\timeFilter::$FromWeek : filters\timeFilter::$FromWeek);
        $numberTo = filters\timeFilter::$ToYear . (filters\timeFilter::$ToWeek < 10 ? "0" . filters\timeFilter::$ToWeek : filters\timeFilter::$ToWeek);
        
        $result = $maxYearAllWeek = array();
        if (is_array($dateList) && !empty($dateList)) {
            foreach ($dateList as $key => $data) {
                $account = $data['YEAR'] . "-" . $data['WEEK']; //SAMPLE '2014-5'

                //JUST LIKE $numberFrom WE MAKE A NUMERICAL FORMATION OF $account TO COMPARE WITH $numberFrom AND $numberTo
                $numberAccount = $data['YEAR'] . ($data['WEEK'] < 10 ? "0" . $data['WEEK'] : $data['WEEK']); //SAMPLE '201405'
                //BASED ON $numberAccount WE DECIDE WHETHER TO PUT THE QUERY RESULT IN $tyValues OR IN $lyValues

                if (isset($data['MYDATE']))
                    $data['MYDATE'] = date('j M y', strtotime($data['MYDATE']));

                if ($numberAccount >= $numberFrom && $numberAccount <= $numberTo) { //$numberFrom AND $numberTo COMES HANDY HERE
                    $dateListTyLy['TY'][$account] = $data;
                } else {
                    $dateListTyLy['LY'][$account] = $data;
                }
            }
        }

        /*         * *********** AVAILABLE YEAR AND WEEK FETCHING COMPLETE ************ */
        
        datahelper\Common_Data_Fetching_Functions::$settingVars = $this->settingVars;
        datahelper\Common_Data_Fetching_Functions::$queryVars = $this->queryVars;        
        $measuresFields = datahelper\Common_Data_Fetching_Functions::getAllMeasuresExtraTable();
        $this->settingVars->tableUsedForQuery = array();
        $this->prepareTablesUsedForQuery($measuresFields);
        $this->settingVars->useRequiredTablesOnly = true;
        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll        
        
        $measuresArray = $measureIDs = array();
        
        if(is_array($this->settingVars->measureArray) && !empty($this->settingVars->measureArray)){
            foreach ($this->settingVars->measureArray as $key => $measureVal) {
                if(!in_array($key, $measureIDs)){
                    $measureIDs[] = $key;
                    $measuresArray[] = $this->settingVars->measureArray[$measureKey];
                }
                $keyAlias[$key] = $measureVal['ALIASE'];
            }
        }
        
        $options = array();
        $measureSelectionArr = config\MeasureConfigure::prepareSelect($this->settingVars, $this->queryVars, $measureIDs, $options);
        
        $query = " SELECT " . $this->settingVars->yearperiod . " AS YEAR," . $this->settingVars->weekperiod . " AS WEEK,";
        $mydateSelect = $this->settingVars->getMydateSelect($this->settingVars->dateField);
        $query .= ($this->settingVars->dateField !='') ? " ".$mydateSelect . " AS MYDATE, " : "";        
        $query .= implode(",", $measureSelectionArr) . " ";
        $query .= "FROM " . $this->settingVars->tablename . $this->queryPart . " AND (" . filters\timeFilter::$tyWeekRange . " OR " . filters\timeFilter::$lyWeekRange . ") " .
                "GROUP BY YEAR,WEEK " .
                "ORDER BY YEAR ASC,WEEK ASC";
        // echo $query; exit;
        
        $redisOutput = $redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }

        $finalResult = $tempResult = array();

        if (is_array($result) && !empty($result)) {
            foreach ($result as $key => $data) {
                $account = $data['YEAR'] . "-" . $data['WEEK']; //SAMPLE '2014-5'

                //JUST LIKE $numberFrom WE MAKE A NUMERICAL FORMATION OF $account TO COMPARE WITH $numberFrom AND $numberTo
                $numberAccount = $data['YEAR'] . ($data['WEEK'] < 10 ? "0" . $data['WEEK'] : $data['WEEK']); //SAMPLE '201405'
                //BASED ON $numberAccount WE DECIDE WHETHER TO PUT THE QUERY RESULT IN $tyValues OR IN $lyValues

                if (isset($data['MYDATE']))
                    $data['MYDATE'] = date('j M y', strtotime($data['MYDATE']));

                if ($numberAccount >= $numberFrom && $numberAccount <= $numberTo) { //$numberFrom AND $numberTo COMES HANDY HERE
                    $finalResult['TY'][$account] = $data;
                } else {
                    $finalResult['LY'][$account] = $data;
                }
            }
        }

        if (count($dateListTyLy['TY']) >= count($dateListTyLy['LY'])) {
            $validTimeSlot = "TY";
            $validArray = $dateListTyLy['TY'];
        } else {
            $validTimeSlot = "LY";
            $validArray = $dateListTyLy['LY'];
        }

        foreach ($validArray as $key => $data) {
            //SEPERATE YEAR AND WEEK FROM YEAR-WEEK COMBINATION
            $yearWeekPart = explode("-", $key);
            $year = $yearWeekPart[0];
            $week = $yearWeekPart[1];
            
            if ($validTimeSlot == "TY") { //MEANS $tyValues IS THE LONGEST , SO WE HAVE TO MAKE $lyKey.
                $tyKey = $key;
                $lyKey = ($year - 1) . "-" . $week; //IT'S ACTUALLY THE CORRESPONDING LY YEAR-WEEK COMBINATION
            } else { //MEANS $tyValues IS THE LONGEST , SO WE HAVE TO MAKE $lyKey.
                $tyKey = ($year + 1) . "-" . $week; //.THE CORRESPONDING TY YEAR-WEEK COMBINATION
                $lyKey = $key;
            }

            if(isset($this->enabledMeasureList["valueChart"]))
            {
                $temp = array();
                $temp['ACCOUNT'] = $week;
                $temp['COST_CUR'] = (float)(key_exists($tyKey, $finalResult['TY']) ? $finalResult['TY'][$tyKey][$keyAlias[$this->enabledMeasureList['valueChart']]] : 0);
                $temp['COST_PRE'] = (float)(key_exists($lyKey, $finalResult['LY']) ? $finalResult['LY'][$lyKey][$keyAlias[$this->enabledMeasureList['valueChart']]] : 0);
                // $temp['COST_CUR'] = $data[$keyAlias[$this->enabledMeasureList['valueChart']]];
                // $temp['COST_PRE'] = $data2[$yrKey][$keyAlias[$this->enabledMeasureList['valueChart']]];
                $tempResult['ValueChart'][] = $temp;
            }
            
            if(isset($this->enabledMeasureList["unitChart"]))
            {
                $temp = array();
                $temp['ACCOUNT'] = $week;
                $temp['COST_CUR'] = (float)(key_exists($tyKey, $finalResult['TY']) ? $finalResult['TY'][$tyKey][$keyAlias[$this->enabledMeasureList['unitChart']]] : 0);
                $temp['COST_PRE'] = (float)(key_exists($lyKey, $finalResult['LY']) ? $finalResult['LY'][$lyKey][$keyAlias[$this->enabledMeasureList['unitChart']]] : 0);
                // $temp['COST_CUR'] = $data[$keyAlias[$this->enabledMeasureList['unitChart']]];
                // $temp['COST_PRE'] = $data2[$yrKey][$keyAlias[$this->enabledMeasureList['unitChart']]];
                $tempResult['UnitChart'][] = $temp;
            }
            
            if(isset($this->enabledMeasureList["priceChart"]))
            {
                $temp = array();
                $temp['ACCOUNT'] = $week;
                $temp['COST_CUR'] = (float)(key_exists($tyKey, $finalResult['TY']) ? number_format($finalResult['TY'][$tyKey][$keyAlias[$this->enabledMeasureList['priceChart']]],1,".","") : 0);
                $temp['COST_PRE'] = (float)(key_exists($lyKey, $finalResult['LY']) ? number_format($finalResult['LY'][$lyKey][$keyAlias[$this->enabledMeasureList['priceChart']]],1,".","") : 0);
                // $temp['COST_CUR'] = number_format($data[$keyAlias[$this->enabledMeasureList['priceChart']]],1,".","");
                // $temp['COST_PRE'] = number_format($data2[$yrKey][$keyAlias[$this->enabledMeasureList['priceChart']]],1,".","");
                $tempResult['PriceChart'][] = $temp;
            }
            
            if ($this->showSSChart) {
                // Selling Stores. it will chart count unique SNO where sales>0        

                $temp = array();
                $temp['ACCOUNT'] = $week;
                $temp['COST_CUR'] = (int)(key_exists($tyKey, $finalResult['TY']) ? $finalResult['TY'][$tyKey][$keyAlias[$this->enabledMeasureList['sellingStoreChart']]] : 0);
                $temp['COST_PRE'] = (int)(key_exists($lyKey, $finalResult['LY']) ? $finalResult['LY'][$lyKey][$keyAlias[$this->enabledMeasureList['sellingStoreChart']]] : 0);
                // $temp['COST_CUR'] = $data[$keyAlias[$this->enabledMeasureList['sellingStoreChart']]];
                // $temp['COST_PRE'] = $data2[$yrKey][$keyAlias[$this->enabledMeasureList['sellingStoreChart']]];
                $tempResult['SSChart'][] = $temp;
            }
        }

        $this->jsonOutput = $tempResult;
    }

    public function buildPageArray() {

        $storeFieldPart = explode("#", $this->storeField);
        $storeField = strtoupper($this->dbColumnsArray[$storeFieldPart[0]]);
        $storeField = (count($storeFieldPart) > 1) ? strtoupper($storeField . "_" . $this->dbColumnsArray[$storeFieldPart[1]]) : $storeField;

        $this->storeID = (isset($this->settingVars->dataArray[$storeField]) && isset($this->settingVars->dataArray[$storeField]['ID'])) ?
                $this->settingVars->dataArray[$storeField]['ID'] : $this->settingVars->dataArray[$storeField]['NAME'];

        return;
    }

    public function buildDataArray($fields) {

        if (empty($fields))
            return false;

        $configurationCheck = new config\ConfigurationCheck($this->settingVars, $this->queryVars);
        $configurationCheck->buildDataArray($fields);
        $this->dbColumnsArray = $configurationCheck->dbColumnsArray;
        return;
    }

}

?>