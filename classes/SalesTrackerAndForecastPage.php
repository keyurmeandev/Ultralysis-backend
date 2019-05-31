<?php
namespace classes;

use filters;
use db;
use config;

class SalesTrackerAndForecastPage extends config\UlConfig {

    public $pageName;
    public $skuField;
    public $displayCsvNameArray;
    public $dbColumnsArray;

    /** ***
     * Default gateway function, should be similar in all DATA CLASS
     * arguments:
     *  $settingVars [project settingsGateway variables]
     * @return $jsonOutput with all data that should be sent to client app
     * *** */
    public function go($settingVars) {
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES        
        $this->redisCache = new \utils\RedisCache($this->queryVars);
		$this->settingVars->pageName = (empty($this->settingVars->pageName)) ? $this->settingVars->pageID.'_SalesTrackerAndForecastPage' : $this->settingVars->pageName;
		$this->ValueVolume = getValueVolume($this->settingVars);
        
		if ($this->settingVars->isDynamicPage) {
			$this->skuField = $this->getPageConfiguration('sku_field', $this->settingVars->pageID)[0];
            $this->buildDataArray(array($this->skuField));
			$this->buildPageArray();
		} else {
            $message = "Page isn't configured properly.";
            $response = array("configuration" => array("status" => "fail", "messages" => array($message)));
            echo json_encode($response);
            exit();
	    }
		
        $this->queryPart = $this->getAll();
        $action = $_REQUEST["action"];
        switch ($action) {
            case "reload": 
    			if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true'){
                    //////////////
                }else{
                    $this->reload();
                }
    			break;
            case "skuSelect":
                $this->setLineChartData();
                break;
        }
		return $this->jsonOutput;
    }
    
    public function reload() {
        $this->gridData();
    }

    public function gridData() {

		$this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $measureSelectRes = $this->prepareMeasureSelectPart();
        $this->measureFields = $measureSelectRes['measureSelectionArr'];
		$this->measureFields[] = $this->skuID;
        $this->measureFields[] = $this->skuName;

		$this->settingVars->useRequiredTablesOnly = true;
		if (is_array($this->measureFields) && !empty($this->measureFields)) {
			$this->prepareTablesUsedForQuery($this->measureFields);
		}
		$this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll
        $measuresFldsAll = implode(',',$measureSelectRes['measureSelectionArr']);
        list($LYYEAR,$LYMAXWEEK,$LatestTYWYr) = $this->getLastMaxYearWeek($this->settingVars);

        $latestTYrWk = "SUM((CASE WHEN (period_list.accountyear=".$LatestTYWYr[0]." AND period_list.accountweek=".$LatestTYWYr[1].") THEN 1 ELSE 0 END) *  AveStoreStock) AS SUMAVESTORESTOCK,";
        
        $latestTYrWkDepot = "SUM((CASE WHEN (period_list.accountyear=".$LatestTYWYr[0]." AND period_list.accountweek=".$LatestTYWYr[1].") THEN 1 ELSE 0 END) *  AveDepotStock) AS SUMAVEDEPOTSTOCK,";

        $ytgWeekRange = "(period_list.accountyear=".$LYYEAR." AND period_list.accountweek>".filters\timeFilter::$ToWeek.") AND (period_list.accountyear=".$LYYEAR." AND period_list.accountweek<=".$LYMAXWEEK.")";

        $query = "SELECT ".
                    $this->skuID." AS ID, " .
                    $this->skuName ." AS ACCOUNT, " .
                    $latestTYrWk.
                    $latestTYrWkDepot.
                    $measuresFldsAll.
                ",SUM((CASE WHEN (".$ytgWeekRange.") THEN 1 ELSE 0 END) * ".$this->settingVars->ProjectVolume." ) AS YEAR_TO_GO_LY".
                " FROM " . $this->settingVars->tablename . $this->queryPart . " AND (" . filters\timeFilter::$tyWeekRange ." OR ".filters\timeFilter::$lyWeekRange ." OR (".$ytgWeekRange.") ) ".
                " AND ".$this->settingVars->skutable.".pin IN (5564959) ".
                "GROUP BY ID,ACCOUNT ORDER BY TYVOLUME DESC";

        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }

        $gridData = array();
        if(isset($result) && !empty($result)) {
            foreach ($result as $key => $data) {
                $temp = array();
                $temp['ID'] = $data['ID'];
                $temp['ACCOUNT'] = htmlspecialchars_decode($data['ACCOUNT']);

                $temp['ACTUAL_POS_QTY_TY']      = $data['TYVOLUME'];
                $temp['ACTUAL_POS_QTY_LY']      = $data['LYVOLUME'];
                $temp['ACTUAL_POS_QTY_VAR']     = $data['TYVOLUME'] - $data['LYVOLUME'];
                $temp['ACTUAL_POS_QTY_VAR_PER'] = ($data['LYVOLUME'] > 0) ? ($temp['ACTUAL_POS_QTY_VAR'] / $data['LYVOLUME'])*100 : 0;
                $temp['YEAR_TO_GO_LY'] = $data['YEAR_TO_GO_LY'];
                $temp['YEAR_TO_GO_FORECAST'] = (!empty($temp['ACTUAL_POS_QTY_LY'])) ? $data['YEAR_TO_GO_LY'] * ($temp['ACTUAL_POS_QTY_TY'] / $temp['ACTUAL_POS_QTY_LY']) : 0;
                $temp['TOTAL_STOCK_STR_DC']     = $data['SUMAVESTORESTOCK'] + $data['SUMAVEDEPOTSTOCK'];
                
                $gridData[] = $temp;
            }
        }
        $this->jsonOutput['gridData'] = $gridData;
    }

    public function setLineChartData() {
        if(!isset($_REQUEST['selectedID']) || empty($_REQUEST['selectedID']))
            return '';

        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $measureSelectRes = $this->prepareMeasureSelectPart();
        $this->measureFields = $measureSelectRes['measureSelectionArr'];
        $this->measureFields[] = $this->skuID;
        
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll
        $measuresFldsAll = implode(',',$measureSelectRes['measureSelectionArr']);

        list($LYYEAR,$LYMAXWEEK,$LatestTYWYr) = $this->getLastMaxYearWeek($this->settingVars);
        $ytgWeekRange = "(period_list.accountyear=".$LYYEAR." AND period_list.accountweek>".filters\timeFilter::$ToWeek.") AND (period_list.accountyear=".$LYYEAR." AND period_list.accountweek<=".$LYMAXWEEK.")";

        $query = "SELECT CONCAT(" . $this->settingVars->weekperiod . ",'-'," . $this->settingVars->yearperiod . ") AS PERIOD, " .
            $this->settingVars->yearperiod . " AS YEAR,".
            $this->settingVars->weekperiod . " AS WEEK,".
            "SUM(" . $this->settingVars->ProjectVolume . ") AS QTY, " .
            "SUM(AveStoreStock) AS SUMAVESTORESTOCK, " .
            "SUM(AveDepotStock) AS SUMAVEDEPOTSTOCK " .
            " FROM " . $this->settingVars->tablename . $this->queryPart . 
            //" AND (" . filters\timeFilter::$tyWeekRange ." OR ".filters\timeFilter::$lyWeekRange ." OR (".$ytgWeekRange.") ) ".
            " AND ".$this->skuID." = '".$_REQUEST['selectedID']."'".
            " GROUP BY PERIOD,YEAR,WEEK " . 
            " ORDER BY " . $this->settingVars->yearperiod . " ASC," . $this->settingVars->weekperiod . " ASC";

        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }

        $FnArr = $FnSArr = array(); 
        $Tyear = filters\timeFilter::$FromYear;
        $Lyear = $Tyear - 1;
        $ToWeek = filters\timeFilter::$ToWeek;

        if(is_array($result) && count($result)>0){
            foreach ($result as $key => $value) {
                $FnArr[$value['WEEK']]['PERIOD'] = $value['WEEK'].'-'.$Tyear;
                if($value['YEAR']==$Tyear){

                    $FnSArr[$value['WEEK']]['TYPERIOD'] = $value['WEEK'].'-'.$value['YEAR'];
                    $FnSArr[$value['WEEK']]['SUMAVESTORESTOCK']  = (float) $value['SUMAVESTORESTOCK'];
                    $FnSArr[$value['WEEK']]['SUMAVEDEPOTSTOCK']  = (float) $value['SUMAVEDEPOTSTOCK'];

                    $FnArr[$value['WEEK']]['ACTUAL_POS_QTY_TY'] = (float) $value['QTY'];
                    $FnArr[$value['WEEK']]['TOTAL_STOCK_STR_DC']  = (float) ($value['SUMAVESTORESTOCK'] + $value['SUMAVEDEPOTSTOCK']);
                    if(!isset($FnArr[$value['WEEK']]['ACTUAL_POS_QTY_LY']))
                        $FnArr[$value['WEEK']]['ACTUAL_POS_QTY_LY'] = 0;
                }else{
                    $FnArr[$value['WEEK']]['LYPERIOD'] = $value['WEEK'].'-'.$value['YEAR'];
                    $FnArr[$value['WEEK']]['ACTUAL_POS_QTY_LY'] = (float) $value['QTY'];

                    if($ToWeek < $value['WEEK'])
                        $FnArr[$value['WEEK']]['FORECAST_POS_QTY'] = (float) ($value['QTY'] * 0.51);

                    /*$FnArr[$value['WEEK']]['SUMAVESTORESTOCK']  = 0;
                    $FnArr[$value['WEEK']]['SUMAVEDEPOTSTOCK']  = 0;*/

                    $FnArr[$value['WEEK']]['TOTAL_STOCK_STR_DC'] = 0;
                    if(!isset($FnArr[$value['WEEK']]['ACTUAL_POS_QTY_TY']))
                        $FnArr[$value['WEEK']]['ACTUAL_POS_QTY_TY'] = 0;                
                }
            }
        ksort($FnArr); ksort($FnSArr);
        }
        $this->jsonOutput['Linechart'] = array_values($FnArr);
        $this->jsonOutput['StackLinechart'] = array_values($FnSArr);
    }

	public function buildPageArray() {
        $fetchConfig = false;
        
        $skuFieldPart = explode("#", $this->skuField);
        $skuField = strtoupper($this->dbColumnsArray[$skuFieldPart[0]]);
        $skuField = (count($skuFieldPart) > 1) ? strtoupper($skuField . "_" . $this->dbColumnsArray[$skuFieldPart[1]]) : $skuField;
        $this->skuID = (isset($this->settingVars->dataArray[$skuField]) && isset($this->settingVars->dataArray[$skuField]['ID'])) ? $this->settingVars->dataArray[$skuField]['ID'] : $this->settingVars->dataArray[$skuField]['NAME'];
        $this->skuName = $this->settingVars->dataArray[$skuField]['NAME'];

		if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
		    $fetchConfig = true;

            $this->jsonOutput['pageConfig']['enabledFilters'] = $this->getPageConfiguration('filter_settings', $this->settingVars->pageID);
            $this->jsonOutput['pageConfig']['gridFldName']    = (isset($this->settingVars->dataArray[$skuField]) && isset($this->settingVars->dataArray[$skuField]['ID'])) ? $this->settingVars->dataArray[$skuField]['NAME_CSV'] : 'SKU';

            if($this->skuID != $this->skuName){
                $this->jsonOutput['pageConfig']['gridFldId']    = (isset($this->settingVars->dataArray[$skuField]) && isset($this->settingVars->dataArray[$skuField]['ID'])) ? $this->settingVars->dataArray[$skuField]['ID_CSV'] : 'ID';
            }
        }
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

    public function getLastMaxYearWeek($settingVars){
        $mydateSelect = $settingVars->getMydateSelect($settingVars->dateField);
        
        $query = "SELECT " . $settingVars->yearperiod . " AS YEAR" .
                ",".$settingVars->weekperiod . " AS WEEK" .
                (($settingVars->dateField) ? ",".$mydateSelect." " : " ") .
                "FROM " . $settingVars->timeHelperTables . $settingVars->timeHelperLink .
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

        $latestYearWeek = isset($dateList[0]) ? $dateList[0] : '';

        $maxYear = (filters\timeFilter::$ToYear - 1); //getting last year
        $maxYearAllWeek = array();
        if (is_array($dateList) && !empty($dateList)) {
            foreach ($dateList as $dateDetail) {
                if ($dateDetail[0] == $maxYear) {
                    $maxYearAllWeek[] = $dateDetail;
                }
            }
        }

        $maxYearAllWeek = \utils\SortUtility::sort2DArray($maxYearAllWeek, 1, \utils\SortTypes::$SORT_ASCENDING);
        $maxCombination = $maxYearAllWeek[count($maxYearAllWeek)-1];
        $minCombination = $maxYearAllWeek[0];
        $minWeek = $minCombination[1];
        $maxWeek = $maxCombination[1];
        return array($maxYear, $maxWeek, $latestYearWeek);
    }
}
?>