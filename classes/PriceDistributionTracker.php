<?php
namespace classes;

use filters;
use db;
use config;

class PriceDistributionTracker extends config\UlConfig {
    public $pageName;
    public $accountField;
    public $storeField;
    public $customSelectPart;
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
		$this->settingVars->pageName = (empty($this->settingVars->pageName)) ? $this->settingVars->pageID.'_PriceDistributionTrackerPage' : $this->settingVars->pageName;
		$this->ValueVolume = getValueVolume($this->settingVars);
        
		if ($this->settingVars->isDynamicPage) {
			$this->settingVars->pageArray[$this->settingVars->pageName] = array(
				"TOP_GRID_COLUMN_NAME" => array(),
				"BOTTOM_GRID_COLUMN_NAME" => array()
			);
			$this->accountField = $this->getPageConfiguration('account_field', $this->settingVars->pageID)[0];
			$this->storeField = $this->getPageConfiguration('store_field', $this->settingVars->pageID)[0];
			$this->buildDataArray(array($this->accountField, $this->storeField));
			$this->buildPageArray();
		} else {
            if (!isset($this->settingVars->pageArray[$this->settingVars->pageName]) || empty($this->settingVars->pageArray[$this->settingVars->pageName]))
                $this->configurationFailureMessage();

            $this->accountID = (isset($this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['ACCOUNT']]) && isset($this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['ACCOUNT']]['ID'])) ? $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['ACCOUNT']]['ID'] : $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['ACCOUNT']]['NAME'];
            $this->accountName = $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['ACCOUNT']]['NAME'];
            $this->storeID = (isset($this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['STORE_FIELD']]) && isset($this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['STORE_FIELD']]['ID'])) ? $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['STORE_FIELD']]['ID'] : $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['STORE_FIELD']]['NAME'];
            $this->storeName = $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['STORE_FIELD']]['NAME'];
	    }
		
        $this->customSelectPart();
        
        $this->queryPart = $this->getAll();

        $action = $_REQUEST["action"];
        switch ($action) {
            case "reload": 
				if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true')
					$this->gridColumnName();
				else
					$this->reload();
                
				break;
            case "skuchange": 
				$this->changeSku();
                break;
        }
		
		return $this->jsonOutput;
    }

    public function customSelectPart()
    {
        $this->customSelectPart = "COUNT( DISTINCT (CASE WHEN " . $this->settingVars->ProjectValue . ">0 THEN " . $this->storeID . " END)) AS DIST ";
    }
    
    public function gridColumnName(){
        $this->jsonOutput["TOP_GRID_COLUMN_NAME"] = ($this->settingVars->pageArray[$this->settingVars->pageName]["TOP_GRID_COLUMN_NAME"] == null)?array():$this->settingVars->pageArray[$this->settingVars->pageName]["TOP_GRID_COLUMN_NAME"];
        $this->jsonOutput["BOTTOM_GRID_COLUMN_NAME"] = ($this->settingVars->pageArray[$this->settingVars->pageName]["BOTTOM_GRID_COLUMN_NAME"] == null)?array():$this->settingVars->pageArray[$this->settingVars->pageName]["BOTTOM_GRID_COLUMN_NAME"];
    }

    public function reload() {
        $this->chartData();
        $this->gridData();
    }

    public function changeSku() {
        $this->chartData();
    }

    public function chartData() {

        $temp = array();
        //COLLECTING TOTAL SELLING STORES
        if(isset($this->settingVars->getStoreCountType) && $this->settingVars->getStoreCountType != '')
        {
            $this->measureFields[] = $this->storeID;
			$this->settingVars->useRequiredTablesOnly = true;
			if (is_array($this->measureFields) && !empty($this->measureFields)) {
				$this->prepareTablesUsedForQuery($this->measureFields);
			}

			$this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll

			if($this->settingVars->getStoreCountType == "AVG")
                $query = "SELECT " . $this->customSelectPart . " FROM " . $this->settingVars->tablename . $this->queryPart . " AND " . filters\timeFilter::$tyWeekRange;
            else
                $query = "SELECT ". $this->customSelectPart ." FROM " . $this->settingVars->tablename . $this->queryPart . " AND " . filters\timeFilter::$tyWeekRange;

            //echo $query;exit;
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            
            if(isset($result) && !empty($result))                   
                $temp['STSELLING'] = $result[0]['DIST'];            
        }
        $this->jsonOutput['chartTotal'] = $temp;

		$this->settingVars->tableUsedForQuery = $this->measureFields = array();
		$this->measureFields[] = $this->storeID;
			$this->settingVars->useRequiredTablesOnly = true;
			if (is_array($this->measureFields) && !empty($this->measureFields)) {
				$this->prepareTablesUsedForQuery($this->measureFields);
			}

		$this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll
		
        $query = "SELECT CONCAT(" . $this->settingVars->weekperiod . ",'-'," . $this->settingVars->yearperiod . ") AS PERIOD, " .
            "SUM(" . $this->settingVars->ProjectValue . ") AS SALES, " .
            "SUM(" . $this->settingVars->ProjectVolume . ") AS UNITS, " .
            $this->customSelectPart .
            " FROM " . $this->settingVars->tablename . $this->queryPart . " AND " . filters\timeFilter::$tyWeekRange . " " .
            "GROUP BY PERIOD," . $this->settingVars->weekperiod . "," . $this->settingVars->yearperiod . " " . 
            "ORDER BY " . $this->settingVars->yearperiod . " ASC," . $this->settingVars->weekperiod . " ASC";

        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }
        $chartData = array();
        if(isset($result) && !empty($result)) {
            foreach ($result as $key => $data) {
                $avePriceP = 0.00;
                if ($data['UNITS'] > 0)
                    $avePriceP = ($data['SALES'] / $data['UNITS']) * 100;
                $temp = array();
                $temp['ACCOUNT'] = $data['PERIOD'];
                $temp['SALES'] = $data['SALES'];
                $temp['UNITS'] = $data['UNITS'];
                $temp['AVEPP'] = number_format($avePriceP, 0, ".", "");
                $temp['STSELLING'] = $data['DIST'];
                $chartData[] = $temp;
            }
        }
        $this->jsonOutput['chartData'] = $chartData;
    }

    public function gridData() {

		$this->settingVars->tableUsedForQuery = $this->measureFields = array();
		$this->measureFields[] = $this->accountID;
		$this->measureFields[] = $this->accountName;
		$this->measureFields[] = $this->storeID;
			$this->settingVars->useRequiredTablesOnly = true;
			if (is_array($this->measureFields) && !empty($this->measureFields)) {
				$this->prepareTablesUsedForQuery($this->measureFields);
			}

		$this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll

        $query = "SELECT ".$this->accountID." AS ID, " .
            $this->accountName ." AS ACCOUNT, " .
            "SUM(" . $this->settingVars->ProjectValue . ") AS SALES, " .
            "SUM(" . $this->settingVars->ProjectVolume . ") AS UNITS, " .
            $this->customSelectPart .
            " FROM " . $this->settingVars->tablename . $this->queryPart . " AND " . filters\timeFilter::$tyWeekRange . " " .
            "GROUP BY ID,ACCOUNT " .
            "ORDER BY SALES DESC";
        
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
                $avePriceP = 0.00;
                if ($data['SALES'] > 0)
                    $avePriceP = ($data['SALES'] / $data['UNITS']) * 100;
                $temp = array();
                $temp['ID'] = $data['ID'];
                $temp['ACCOUNT'] = htmlspecialchars_decode($data['ACCOUNT']);
                $temp['SALES'] = $data['SALES'];
                $temp['UNITS'] = $data['UNITS'];
                $temp['AVEPP'] = number_format($avePriceP, 0, ".", "");
                $temp['STSELLING'] = $data['DIST'];
                $gridData[] = $temp;
            }
        }
        $this->jsonOutput['gridData'] = $gridData;
    }

    /*     * ***
     * COLLECTS TABLE JOINING STRING FROM PROJECT SETTINGS
     * ALSO APPLYS FILTER CONDITIONS TO THE STRING
     * AND RETURNS $tablejoins_and_filters
     * *** */

    public function getAll() {
        // $tablejoins_and_filters = parent::getAll();
		$tablejoins_and_filters = "";

        if ($_REQUEST['SKU'] != "") {
			$extraFields[] = $this->accountID;
            $tablejoins_and_filters .=" AND ".$this->accountID."='" . $_REQUEST['SKU'] . "' ";
        }
		
		$this->prepareTablesUsedForQuery($extraFields);
        $tablejoins_and_filters1 = parent::getAll();
        $tablejoins_and_filters1 .= $tablejoins_and_filters;

        return $tablejoins_and_filters1;
    }
	
	
	public function buildPageArray() {

		$fetchConfig = false;
		if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
		    $fetchConfig = true;
			$this->jsonOutput['pageConfig'] = array(
				'enabledFilters' => $this->getPageConfiguration('filter_settings', $this->settingVars->pageID)
			);
        }

		$accountFieldPart = explode("#", $this->accountField);
		$this->accountField = $accountFieldPart[0];
		if (count($accountFieldPart) > 1) {
			$accountFieldID = $accountFieldPart[1];
			$this->settingVars->pageArray[$this->settingVars->pageName]["BOTTOM_GRID_COLUMN_NAME"]['ID'] = $this->displayCsvNameArray[$accountFieldID];
		}
		
		$this->settingVars->pageArray[$this->settingVars->pageName]["BOTTOM_GRID_COLUMN_NAME"]['name'] = $this->displayCsvNameArray[$this->accountField];

        $accountField = strtoupper($this->dbColumnsArray[$this->accountField]);
        $accountField = (count($accountFieldPart) > 1) ? strtoupper($accountField."_".$this->dbColumnsArray[$accountFieldPart[1]]) : $accountField;
        $storeField = strtoupper($this->dbColumnsArray[$this->storeField]);

        $this->accountID = (isset($this->settingVars->dataArray[$accountField]) && isset($this->settingVars->dataArray[$accountField]['ID'])) ? $this->settingVars->dataArray[$accountField]['ID'] : $this->settingVars->dataArray[$accountField]['NAME'];
        $this->accountName = $this->settingVars->dataArray[$accountField]['NAME'];
        $this->storeID = (isset($this->settingVars->dataArray[$storeField]) && isset($this->settingVars->dataArray[$storeField]['ID'])) ? $this->settingVars->dataArray[$storeField]['ID'] : $this->settingVars->dataArray[$storeField]['NAME'];
        $this->storeName = $this->settingVars->dataArray[$storeField]['NAME']; 

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