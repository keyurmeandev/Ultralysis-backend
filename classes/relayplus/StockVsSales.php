<?php

namespace classes\relayplus;

use datahelper;
//use projectsettings;
use filters;
//use utils;
use db;
use config;

class StockVsSales extends config\UlConfig {
    /*     * ***
     * Default gateway function, should be similar in all DATA CLASS
     * arguments:
     *  $settingVars [project settingsGateway variables]
     * @return $xmlOutput with all data that should be sent to client app
     * *** */
	
	public $pageName;
	
    public function go($settingVars) {
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES		
        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll

        $this->settingVars->pageName = (empty($this->settingVars->pageName)) ? $this->settingVars->pageID . '_StockvsSalesPage' : $this->settingVars->pageName;

        if ($this->settingVars->isDynamicPage) {
            $this->accountField = $this->getPageConfiguration('account_field', $this->settingVars->pageID)[0];
            $this->buildDataArray(array($this->accountField));
            $this->buildPageArray();
        }else{
            $this->accountID = key_exists('ID', $this->settingVars->dataArray[$_REQUEST['account']]) ? $this->settingVars->dataArray[$_REQUEST['account']]['ID'] : $this->settingVars->dataArray[$_REQUEST['account']]['NAME'];
            $this->accountName = $this->settingVars->dataArray[$_REQUEST['account']]['NAME'];
        }
        
		if (!isset($_REQUEST["fetchConfig"]) || empty($_REQUEST["fetchConfig"]) || $_REQUEST["fetchConfig"] != 'true') {
			$this->salesVsStockFilter();
        }
		
        return $this->jsonOutput;
    }

    function getCustomSelectPart(){

        $customSelectPart = " ,SUM((CASE WHEN ".$this->settingVars->ProjectVolume.">0 THEN 1 ELSE 0 END)*".$this->settingVars->ProjectVolume.") AS SALES " .
                            " ,SUM((CASE WHEN " . $this->settingVars->dateField . "='" . filters\timeFilter::$tyDaysRange[0] . "' THEN 1 ELSE 0 END)*".$this->ohq.") AS STOCK ";

        return $customSelectPart;
    }

	function salesVsStockFilter() {

		$this->settingVars->tableUsedForQuery = $this->measureFields = array();
		$this->measureFields[] = $this->accountID;
        $this->measureFields[] = $this->accountName;
		$this->measureFields[] = $this->ohq;
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll

        $customSelectPart = $this->getCustomSelectPart();

        $query = "SELECT $this->accountID AS accountID, $this->accountName AS accountName ". $customSelectPart .
                " FROM " . $this->settingVars->tablename . " " . $this->queryPart.
                " AND " . $this->settingVars->dateField . " IN('" . implode("','", filters\timeFilter::$tyDaysRange) . "') " .
				" GROUP BY accountID, accountName";
		//echo $query;exit;
		$result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);

        $bindingData = array();

        $i = 0;
		if (is_array($result) && !empty($result))
		{			
			foreach ($result as $key => $value) {
					$bindingData[$i]['accountID'] 	= $value['accountID'];
					$bindingData[$i]['accountName'] 	= $value['accountName'];
					$bindingData[$i]['SALES'] 	= (int) $value['SALES'];
					$bindingData[$i]['STOCK'] 	= (int) $value['STOCK'];					
					$i++;
			}
		}
        $this->jsonOutput['salesVsStockDataFilter'] = $bindingData;
    }
	
    /*     * ***
     * COLLECTS TABLE JOINING STRING FROM PROJECT SETTINGS
     * ALSO APPLYS FILTER CONDITIONS TO THE STRING
     * AND RETURNS $tablejoins_and_filters
     * *** */

    public function getAll() {

        $tablejoins_and_filters = "";
        
        $tablejoins_and_filters .= " AND ".$this->settingVars->maintable.".SNO < 7258 ";
        
        $extraFields[] = $this->settingVars->skutable.".supplier";
        $tablejoins_and_filters .= " AND ".$this->settingVars->skutable.".supplier='FERRERO UK LTD' ";
		
        $this->prepareTablesUsedForQuery($extraFields);
        $tablejoins_and_filters1 = parent::getAll();
        $tablejoins_and_filters1 .= $tablejoins_and_filters;

        return $tablejoins_and_filters1;
		
    }

    public function buildPageArray() {

        $fetchConfig = false;
        
        $accountFieldPart = explode("#", $this->accountField);
        $this->accountField = $accountFieldPart[0];

        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
            $fetchConfig = true;
            $this->jsonOutput['pageConfig'] = array(
                'enabledFilters' => $this->getPageConfiguration('filter_settings', $this->settingVars->pageID),
            );
            $this->jsonOutput['gridColumns']['accountID'] =  (count($accountFieldPart) > 1) ? $this->displayCsvNameArray[$accountFieldPart[1]] : $this->displayCsvNameArray[$this->accountField];
            $this->jsonOutput['gridColumns']['accountName'] =  $this->displayCsvNameArray[$this->accountField];

            $showBottomGrid = $this->getPageConfiguration('show_bottom_grid', $this->settingVars->pageID)[0];

            $this->jsonOutput['showBottomGrid'] =  ($showBottomGrid == 'true') ? true : false;
        }

        $accountField = strtoupper($this->dbColumnsArray[$this->accountField]);
        $accountField = (count($accountFieldPart) > 1) ? strtoupper($accountField."_".$this->dbColumnsArray[$accountFieldPart[1]]) : $accountField;
        

        $this->accountID = (isset($this->settingVars->dataArray[$accountField]) && isset($this->settingVars->dataArray[$accountField]['ID'])) ? $this->settingVars->dataArray[$accountField]['ID'] : $this->settingVars->dataArray[$accountField]['NAME'];
        $this->accountName = $this->settingVars->dataArray[$accountField]['NAME'];

        $this->ohq      = $this->settingVars->dataArray['F12']['NAME'];

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