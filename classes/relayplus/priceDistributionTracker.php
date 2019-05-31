<?php
namespace classes\relayplus;

use datahelper;
use projectsettings;
use filters;
use db;
use config;
use utils;

class PriceDistributionTracker extends config\UlConfig {

	/**
     * Default gateway function, should be similar in all DATA CLASS
     * 
     * @param array $settingVars project settingsGateway variables
     *
     * @return Array
     */
	public function go($settingVars)
	{
		$this->initiate($settingVars); //INITIATE COMMON VARIABLES
		$this->redisCache = new \utils\RedisCache($this->queryVars);
		$this->doNotIncludeID = false;
		$this->settingVars->pageName = (empty($this->settingVars->pageName)) ? $this->settingVars->pageID.'_PriceDistributionTrackerPage' : $this->settingVars->pageName;
		$this->ValueVolume = getValueVolume($this->settingVars);
		
		if ($this->settingVars->isDynamicPage) {
            if(isset($_REQUEST['selectedField']) && $_REQUEST['selectedField'] != "")
                $this->accountField = $_REQUEST['selectedField'];
            else    
                $this->accountField = $this->getPageConfiguration('account_field', $this->settingVars->pageID)[0];
                
			$this->storeCountField = $this->getPageConfiguration('store_count_field', $this->settingVars->pageID)[0];
			$this->buildDataArray(array($this->accountField));
			$this->buildPageArray();
		} else {
            if (!isset($this->settingVars->pageArray[$this->settingVars->pageName]) || empty($this->settingVars->pageArray[$this->settingVars->pageName]))
                $this->configurationFailureMessage();

            $this->accountID = (isset($this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['ACCOUNT']]) && isset($this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['ACCOUNT']]['ID'])) ? $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['ACCOUNT']]['ID'] : "";
            if($this->accountID == "")
            {
                $this->accountID = $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['ACCOUNT']]['NAME'];
                $this->doNotIncludeID = true;
            }
            $this->accountName = $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['ACCOUNT']]['NAME'];
			$this->storeCount = (isset($this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['STORE_COUNT']]) && isset($this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['STORE_COUNT']]['ID'])) ? $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['STORE_COUNT']]['ID'] : $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['STORE_COUNT']]['NAME'];
	    }		
		
		$this->queryPart = $this->getAll();  //PREPARE TABLE JOIN STRING USING this class getAll
		
		if(isset($_REQUEST['timeFrame']) && ($_REQUEST['timeFrame'] !='')) {
			filters\timeFilter::getTimeFrame($_REQUEST['timeFrame'],$this->settingVars);
		}
		
		$action = $_REQUEST["action"];

		switch ($action) {
            case "skuSelect": 
                $this->skuSelect();
                break;
			case "topGridData":
				$this->distributionGrid();
				break;
        }

		return $this->jsonOutput;
	}

	/**
     * distributionGrid()
     * It will prepare distribution grid as per filter requested
     *
     * @return void
     */
	private function distributionGrid()
	{
        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $this->measureFields[] = $this->accountID;
        $this->measureFields[] = $this->accountName;
		
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->queryPart = $this->getAll();

        $options = array();
        if (!empty(filters\timeFilter::$tyWeekRange))
            $options['tyLyRange']['SALES'] = filters\timeFilter::$tyWeekRange;

        $measureSelect = config\MeasureConfigure::prepareSelect($this->settingVars, $this->queryVars, array('M'.$_REQUEST['ValueVolume']), $options);
        $measureSelect = implode(", ", $measureSelect);			
		
		/*Note(18-07-2017): Currently we have not enabled the Measure configuration from the backend. If in future there are in use of Measures at that time we need to optimize this query for all measures. */
		$query = "SELECT ".$this->accountID." AS ID".
				 ",".$this->accountName." AS ACCOUNT".
				 ",MAX(".$this->storeCountField.") AS STORE ".
				 ", ".$measureSelect." ".
				 //",SUM( (CASE WHEN ".filters\timeFilter::$tyWeekRange." THEN 1 ELSE 0 END) * " . $this->ValueVolume . " ) AS SALES ".
				 "FROM ".$this->settingVars->tablename.$this->queryPart." AND (".filters\timeFilter::$tyWeekRange.") ".
				 "GROUP BY ID, ACCOUNT ".
				 "ORDER BY SALES DESC";
		
		$redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }
		
		$tempGridArr = array();
		if (is_array($result) && !empty($result)) {
			foreach($result as $key=>$row) {
				$temp 					=array();
                $temp['SKUID']     		= $row['ID'];
				$temp['SKU_NAME']     	= $row['ACCOUNT'];
				$temp['L13_SALE']     	= number_format($row['SALES'], 2, '.', ',');
				$temp['L13_AVE_SALE']   = number_format(($row['SALES']/13), 2, '.', ',');
				$temp['STORES']     	= number_format($row['STORE'], 0, '.', ',');
				$tempGridArr[] 			= $temp;
			}
		}
        
		$this->jsonOutput['distributionGrid'] = $tempGridArr;
		$this->jsonOutput['doNotIncludeID'] = $this->doNotIncludeID;
	}


	/**
     * skuSelect()
     * It will prepare graph data as per selected SKU
     *
     * @return void
     */
    function skuSelect() {
	
		$chartData = array();

        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $this->measureFields[] = $this->accountID;
        $this->measureFields[] = $this->accountName;
		
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->queryPart = $this->getAll();		

        $query = "SELECT ".$this->accountName." AS ACCOUNT, ".
				 $this->settingVars->weekperiod." AS WEEK,".
				 " MAX( ".$this->settingVars->yearperiod." ) AS YEAR ".
				 ",SUM( (CASE WHEN ".filters\timeFilter::$tyWeekRange." THEN 1 ELSE 0 END) * ".$this->storeCountField." ) AS STORE_TY ".
				 ",SUM( (CASE WHEN ".filters\timeFilter::$lyWeekRange." THEN 1 ELSE 0 END) * ".$this->storeCountField." ) AS STORE_LY ".
				 ",SUM( (CASE WHEN ".filters\timeFilter::$tyWeekRange." THEN 1 ELSE 0 END) * " . $this->settingVars->ProjectValue . " ) AS TYEAR ".
				 ",SUM( (CASE WHEN ".filters\timeFilter::$lyWeekRange." THEN 1 ELSE 0 END) * " . $this->settingVars->ProjectValue . " ) AS LYEAR ".
				 ",SUM( (CASE WHEN ".filters\timeFilter::$lyWeekRange." THEN 1 ELSE 0 END) * " . $this->settingVars->ProjectVolume . " ) AS QTY_LY ".
				 ",SUM( (CASE WHEN ".filters\timeFilter::$tyWeekRange." THEN 1 ELSE 0 END) * " . $this->settingVars->ProjectVolume . " ) AS QTY_TY ".
				 "FROM ".$this->settingVars->tablename.$this->queryPart." AND ".$this->accountID." IN ('".rawurldecode($_REQUEST['ACCOUNT'])."') ".
				 " AND (".filters\timeFilter::$tyWeekRange." OR ".filters\timeFilter::$lyWeekRange.") ".
				 " GROUP BY WEEK, ACCOUNT ".
				 "ORDER BY WEEK ASC";
		
		$redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }

		$title = $result[0]['ACCOUNT'];
		$table = array();
		/* Get data array*/
		if (is_array($result) && !empty($result)) {

			foreach ($result as $key => $row) {
				$weekRange = $row['WEEK'].'-'.$row['YEAR'];
				$i = $weekRange;	
				$chartData['TYEAR'][] = number_format($row['TYEAR'], 0, '.', '');
				$chartData['LYEAR'][] = number_format($row['LYEAR'], 0, '.', '');	
				$chartData['AVE_TYEAR'][] = ($row['QTY_TY']>0) ? number_format(($row['TYEAR']/$row['QTY_TY']), 2, '.', '') : '';
				$chartData['AVE_LYEAR'][] = ($row['QTY_LY']>0) ? number_format(($row['LYEAR']/$row['QTY_LY']), 2, '.', '') : '';
				$chartData['STORE_TY'][] = $row['STORE_TY'];
				$chartData['STORE_LY'][] = $row['STORE_LY'];
				$chartData['week'][] = $weekRange ;				
			}

			$table['rows']  =$chartData;
			$table['title'] =$title;
		}

		$this->jsonOutput['distributionLinechart'] = $table;
	}
	
	/**
	 * getAll()
     * COLLECTS TABLE JOINING STRING FROM PROJECT SETTINGS
     * ALSO APPLYS FILTER CONDITIONS TO THE STRING
     * AND RETURNS $tablejoins_and_filters
     *
     * @return String
     */
	public function getAll() {
		//$tablejoins_and_filters       = $this->settingVars->link;
		$tablejoins_and_filters       = parent::getAll();

		/* if (isset($_REQUEST["FS"]) && $_REQUEST["FS"] != '') {
           $tablejoins_and_filters   .= filters\productAndMarketFilter::include_product_and_market_filters($this->queryVars,$this->settingVars);
        } */

		//To avoid duplicate weeks
		if($_REQUEST['action'] == 'skuSelect') {
			/*$tablejoins_and_filters .= " AND ".$this->settingVars->skutable.".PIN=".$this->settingVars->skutable.".PIN_ROLLUP ";*/
		}

		return $tablejoins_and_filters;
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
	
	public function buildPageArray() {

		$fetchConfig = false;
		if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
		    $fetchConfig = true;
			$this->jsonOutput['pageConfig'] = array(
				'enabledFilters' => $this->getPageConfiguration('filter_settings', $this->settingVars->pageID)
			);
        }

        $gridConfig = array("id_label" => "SKU ID", "name_label" => "SKU NAME");
        
		$accountFieldPart = explode("#", $this->accountField);
		$this->accountField = $accountFieldPart[0];
		if (count($accountFieldPart) > 1) {
			$accountFieldID = $accountFieldPart[1];
			$this->settingVars->pageArray[$this->settingVars->pageName]["BOTTOM_GRID_COLUMN_NAME"]['ID'] = $accountFieldID;
		}
		
		$this->settingVars->pageArray[$this->settingVars->pageName]["BOTTOM_GRID_COLUMN_NAME"]['name'] = $this->accountField;

        $accountField = strtoupper($this->dbColumnsArray[$this->accountField]);
        $accountField = (count($accountFieldPart) > 1) ? strtoupper($accountField."_".$this->dbColumnsArray[$accountFieldPart[1]]) : $accountField;
        $storeField = strtoupper($this->dbColumnsArray[$this->storeField]);

        $this->accountID = (isset($this->settingVars->dataArray[$accountField]) && isset($this->settingVars->dataArray[$accountField]['ID'])) ? $this->settingVars->dataArray[$accountField]['ID'] : "";
        
        $gridConfig['id_label'] = (isset($this->settingVars->dataArray[$accountField]) && isset($this->settingVars->dataArray[$accountField]['ID_CSV'])) ? $this->settingVars->dataArray[$accountField]['ID_CSV'] : $gridConfig['id_label'];
       
        if($this->accountID == "")
        {
            $this->accountID = $this->settingVars->dataArray[$accountField]['NAME'];
            $this->doNotIncludeID = true;
        }        
        
        $this->accountName = $this->settingVars->dataArray[$accountField]['NAME'];
        $gridConfig['name_label'] = $this->settingVars->dataArray[$accountField]['NAME_CSV'];
        $this->storeID = (isset($this->settingVars->dataArray[$storeField]) && isset($this->settingVars->dataArray[$storeField]['ID'])) ? $this->settingVars->dataArray[$storeField]['ID'] : $this->settingVars->dataArray[$storeField]['NAME'];
        $this->storeName = $this->settingVars->dataArray[$storeField]['NAME'];

        $this->jsonOutput['gridConfig'] = $gridConfig;
        return;
    }	
}
?>