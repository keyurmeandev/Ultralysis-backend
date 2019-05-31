<?php

namespace classes\retailer;

use datahelper;
use projectsettings;
use filters;
use db;
use config;
use utils;

class SeasonalPromoTracker extends config\UlConfig {
    /*     * ***
     * Default gateway function, should be similar in all DATA CLASS
     * arguments:
     *  $settingVars [project settingsGateway variables]
     * @return $xmlOutput with all data that should be sent to client app
     * *** */

    public function go($settingVars) {
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES
        //$this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll

        $this->settingVars->pageName = (empty($this->settingVars->pageName)) ? $this->settingVars->pageID . '_SeasonalPromoTracker' : $this->settingVars->pageName;

        if ($this->settingVars->isDynamicPage) {
            $this->accountField = $this->getPageConfiguration('account_field', $this->settingVars->pageID)[0];
            $this->buildDataArray(array($this->accountField));
            $this->buildPageArray();
        }else{
        	$this->configurationFailureMessage();
        }
		
        $action = $_REQUEST["action"];
		
        switch ($action) {
            case "getSeasonalPromo":
                $this->getSeasonalPromo();
                break;
			case "getAllData":
				$this->zeroSalesGrid();
				break;
			case "getPerformanceChartData":
				$this->getPerformanceChartData();
				break;
			case "getBottomGridData":
				$this->getBottomGridData();
				break;
        }
		
        return $this->jsonOutput;
    }

	function getBottomGridData()
	{

		$caseSize =  $this->settingVars->retailerPromoDetailsTable.".case_size";

		$this->settingVars->tableUsedForQuery = $this->measureFields = array();
		$this->measureFields[] = $this->accountID;
		$this->measureFields[] = $this->accountName;
		$this->measureFields[] = $caseSize;
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll
		
		$query = "SELECT DISTINCT ".$this->accountID." as accountID, ".$this->accountName." as accountName, ".
				" ".$caseSize." as CASE_SIZE, total_buy as TOTAL_BUY_AGREED, 0 as TOTAL_BUY_ACTUAL FROM ".$this->settingVars->tablename.$this->queryPart.
				" AND ".$this->settingVars->retailerPromoDetailsTable.".PROMOID = 1";
		//echo $query;
		$result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
		if(!empty($result) && is_array($result))
		{
			foreach($result as $key => $data)
				$result[$key]['VAR'] = $data['TOTAL_BUY_AGREED'] - $data['TOTAL_BUY_ACTUAL'];
		}
		$this->jsonOutput['bottomGridData'] = $result;
	}
	
	function getPerformanceChartData()
	{
		$this->settingVars->tableUsedForQuery = array();
        $this->settingVars->useRequiredTablesOnly = true;
        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll

        $query = "SELECT " . "MYDATE as MYDATE ". 
				",SUM((CASE WHEN ".$this->settingVars->recordType." = 'DCS' THEN 1 ELSE 0 END)*".$this->settingVars->ProjectVolume.") AS SUM_DCS " .
                ",SUM((CASE WHEN ".$this->settingVars->recordType." = 'STK' THEN 1 ELSE 0 END)*".$this->settingVars->ProjectVolume.") AS SUM_STK " .
				",SUM((CASE WHEN ".$this->settingVars->recordType." = 'SAL' THEN 1 ELSE 0 END)*".$this->settingVars->ProjectVolume.") AS SUM_SAL " .
                "FROM " . $this->settingVars->tablename . " " . $this->queryPart .
                "AND " . $this->settingVars->dateperiod . " IN ('" . implode("','", filters\timeFilter::$tyDaysRange) . "') GROUP BY MYDATE ";
        //echo $query;
		$result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
		if(!empty($result) && is_array($result))
		{
			foreach($result as $key => $data)
			{
				$result[$key]['SUM_DCS'] = (int)$data['SUM_DCS'];
				$result[$key]['SUM_STK'] = (int)$data['SUM_STK'];
				$result[$key]['SUM_SAL'] = (int)$data['SUM_SAL'];
				$result[$key]['MYDATE_LABEL'] = date("d/m/Y", strtotime($data['MYDATE']));
			}
		}
		$this->jsonOutput['performanceChartData'] = $result;
	}
	
	function getSeasonalPromo()
	{
		$this->settingVars->tableUsedForQuery = array();
        $this->settingVars->useRequiredTablesOnly = true;
        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll

        $query = "SELECT " .
				"SUM((CASE WHEN ".$this->settingVars->recordType." = 'DCS' THEN 1 ELSE 0 END)*".$this->settingVars->ProjectVolume.") AS SUM_DCS " .
                ",SUM((CASE WHEN ".$this->settingVars->recordType." = 'STK' THEN 1 ELSE 0 END)*".$this->settingVars->ProjectVolume.") AS SUM_STK " .
                "FROM " . $this->settingVars->tablename . " " . $this->queryPart .
                "AND " . $this->settingVars->dateperiod . " = '" . filters\timeFilter::getLatestMydate($this->settingVars) . "'";
		$result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);		
		
		$sum = 0;
		if(!empty($result) && is_array($result))
		{
			$sum = $result[0]['SUM_DCS'] + $result[0]['SUM_STK'];
		}
	
		$this->jsonOutput['seasonalPromo'] = array("DAYS_TO_LAUNCH" => "10th Jun 2016", "DAYS_TO_EVENT" => "19th Jun 2016", "DAYS_TO_STORE_LOADING" => "7th Jun 2016", "TOTAL_STOCK_HOLDING" => $sum);
	}

	public function buildPageArray() {

        $fetchConfig = false;
        
        $accountFieldPart = explode("#", $this->accountField);
        $this->accountField = $accountFieldPart[0];

        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
            $fetchConfig = true;
            $this->jsonOutput['pageConfig'] = array(
                'enabledFilters' => $this->getPageConfiguration('filter_settings', $this->settingVars->pageID)
            );
            $this->jsonOutput['gridColumns']['accountID'] =  (count($accountFieldPart) > 1) ? $this->displayCsvNameArray[$accountFieldPart[1]] : $this->displayCsvNameArray[$this->accountField];
            $this->jsonOutput['gridColumns']['accountName'] =  $this->displayCsvNameArray[$this->accountField];
        }

        $accountField = strtoupper($this->dbColumnsArray[$this->accountField]);
        $accountField = (count($accountFieldPart) > 1) ? strtoupper($accountField."_".$this->dbColumnsArray[$accountFieldPart[1]]) : $accountField;

        $this->accountID = (isset($this->settingVars->dataArray[$accountField]) && isset($this->settingVars->dataArray[$accountField]['ID'])) ? $this->settingVars->dataArray[$accountField]['ID'] : $this->settingVars->dataArray[$accountField]['NAME'];
        $this->accountName = $this->settingVars->dataArray[$accountField]['NAME'];

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
   
}
?>