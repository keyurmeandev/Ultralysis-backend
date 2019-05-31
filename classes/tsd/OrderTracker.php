<?php

namespace classes\tsd;

use datahelper;
use projectsettings;
use filters;
use db;
use config;

class OrderTracker extends config\UlConfig {

	/** ***
	* Default gateway function, should be similar in all DATA CLASS
	* arguments:
	* $settingVars [project settingsGateway variables]
	* @return $xmlOutput with all data that should be sent to client app
	* *** */

    public function go($settingVars) {
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES
        $this->redisCache = new \utils\RedisCache($this->queryVars);
        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll
		
		/*if (empty($_REQUEST["requestDays"])) {
            $_REQUEST["requestDays"] = 14;
        } */
        $action = $_REQUEST["action"];
		if ($this->settingVars->isDynamicPage) {
			$this->skuField 	= $this->getPageConfiguration('sku_field', $this->settingVars->pageID);
			
			$tempBuildFieldsArray = array();
			$tempBuildFieldsArray = array_merge($tempBuildFieldsArray, $this->skuField);
			
            $buildDataFields = array();
            foreach ($tempBuildFieldsArray as $value)
                if (!in_array($value, $buildDataFields))
                    $buildDataFields[] = $value;

            $this->buildDataArray($buildDataFields);
            $this->buildPageArray();
			
			$this->pinIdField 	= $this->settingVars->dataArray[strtoupper($this->skuField[0])]['ID'];
			$this->pinNameField = $this->settingVars->dataArray[strtoupper($this->skuField[0])]['NAME'];
		}
		
        switch ($action) {
            case "orderTrackerGrid":
                $this->orderTrackerGrid();
                break;
        }

        return $this->jsonOutput;
    }
    
    function orderTrackerGrid() {
        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $this->measureFields[] = $this->settingVars->sku_supplier_daily.".skuID";
        $this->measureFields[] = $this->pinNameField;
        $accountid = ''; $accountidgroupby = '';
        if(isset($this->pinIdField) && !empty($this->pinIdField)){
        	$this->measureFields[] = $this->pinIdField;
        	$accountid = ", ".$this->pinIdField." AS ACCOUNT_ID";
        	$accountidgroupby = ",ACCOUNT_ID";
        }else{
            $accountid = ", ".$this->pinNameField." AS ACCOUNT_ID";
            $accountidgroupby = ",ACCOUNT_ID";
        }
        
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->queryPart = $this->getAll();        
        
        /*[START] QUERY WITH STORE WHERE MATCHED KEY IS supplier_number*/
        /*$query = "SELECT " .
                $this->pinNameField. " AS ACCOUNT " .$accountid.
                ",".$this->settingVars->sku_supplier_daily.".mydate AS DATE".
                ",SUM(IFNULL(".$this->settingVars->sku_supplier_daily.".units,0)) AS UNIT_SALES".
                ",SUM(IFNULL(".$this->settingVars->sku_supplier_daily.".orders_cases,0)) AS ORDERED_CASES".
                ",SUM(IFNULL(".$this->settingVars->sku_supplier_daily.".received_cases,0)) AS RECEIVED_CASES".
                " FROM " . $this->settingVars->sku_supplier_daily.", ".$this->settingVars->skutable.", ".$this->settingVars->storetable.
                " WHERE ".
                $this->settingVars->sku_supplier_daily.".accountID=".$this->settingVars->aid." AND ".
                $this->settingVars->sku_supplier_daily.".gid=".$this->settingVars->GID." AND ".
                $this->settingVars->sku_supplier_daily.".skuID=".$this->settingVars->skutable.".PIN AND ".
                $this->settingVars->sku_supplier_daily.".mydate IN ('" . implode("','", filters\timeFilter::$tyDaysRange) . "') AND " .
                $this->settingVars->skutable.".gid = ".$this->settingVars->GID." AND ".
                $this->settingVars->sku_supplier_daily.".gid=".$this->settingVars->skutable.".gid AND ".
                $this->settingVars->skutable.".clientID = '".$this->settingVars->clientID."' AND hide<>1 AND ".
                $this->settingVars->sku_supplier_daily.".supplier_number=".$this->settingVars->storetable.".SNO AND ".
                $this->settingVars->storetable.".gid = ".$this->settingVars->GID." AND ".
                $this->settingVars->sku_supplier_daily.".gid=".$this->settingVars->storetable.".gid ".
                " GROUP BY ACCOUNT ".$accountidgroupby.",DATE ORDER BY UNIT_SALES DESC";*/
		/*[END] QUERY WITH STORE WHERE MATCHED KEY IS supplier_number*/

		$query = "SELECT " .
                $this->pinNameField. " AS ACCOUNT " .$accountid.
                ",".$this->settingVars->sku_supplier_daily.".mydate AS DATE".
                ",SUM(IFNULL(".$this->settingVars->sku_supplier_daily.".units,0)) AS UNIT_SALES".
                ",SUM(IFNULL(".$this->settingVars->sku_supplier_daily.".orders_cases,0)) AS ORDERED_CASES".
                ",SUM(IFNULL(".$this->settingVars->sku_supplier_daily.".received_cases,0)) AS RECEIVED_CASES".
                " FROM " . $this->settingVars->sku_supplier_daily.", ".$this->settingVars->skutable.
                " WHERE ".
                $this->settingVars->sku_supplier_daily.".accountID=".$this->settingVars->aid." AND ".
                $this->settingVars->sku_supplier_daily.".gid=".$this->settingVars->GID." AND ".
                $this->settingVars->sku_supplier_daily.".skuID=".$this->settingVars->skutable.".PIN AND ".
                $this->settingVars->sku_supplier_daily.".mydate IN ('" . implode("','", filters\timeFilter::$tyDaysRange) . "') AND " .
                $this->settingVars->skutable.".gid = ".$this->settingVars->GID." AND ".
                $this->settingVars->sku_supplier_daily.".gid=".$this->settingVars->skutable.".gid AND ".
                $this->settingVars->skutable.".clientID = '".$this->settingVars->clientID."' AND hide<>1 ".
                " GROUP BY ACCOUNT ".$accountidgroupby.",DATE HAVING (ORDERED_CASES > 0 OR RECEIVED_CASES > 0) ORDER BY UNIT_SALES DESC";
		
		$redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }

        $orderTrackerGrid = $bottomOrderTrackerGrid = [];
        if (is_array($result) && !empty($result)){
        	foreach ($result as $key => $value) {
        		//$value['DATE'] = (!empty($value['DATE'])) ? date('d M Y',strtotime($value['DATE'])) : '';
        		if($value['ORDERED_CASES'] != $value['RECEIVED_CASES']) {
        			$value['SHORT'] = $value['ORDERED_CASES'] - $value['RECEIVED_CASES'];
                    $value['FULFILMENT'] = (!empty($value['ORDERED_CASES'])) ? ($value['RECEIVED_CASES']/$value['ORDERED_CASES'])*100 : 0;
        			$orderTrackerGrid[] = $value;
        		}else if($value['ORDERED_CASES'] == $value['RECEIVED_CASES'] && $value['ORDERED_CASES'] > 0 && $value['RECEIVED_CASES'] > 0){
        			$bottomOrderTrackerGrid[] = $value;
        		}
        	}
		}

		$unitsSalesTop = array_column($orderTrackerGrid, 'UNIT_SALES');
		array_multisort($unitsSalesTop, SORT_DESC, $orderTrackerGrid);

		$unitsSalesBottom = array_column($bottomOrderTrackerGrid, 'UNIT_SALES');
		array_multisort($unitsSalesBottom, SORT_DESC, $bottomOrderTrackerGrid);

        $this->jsonOutput['orderTrackerGrid'] = $orderTrackerGrid;
        $this->jsonOutput['bottomOrderTrackerGrid'] = $bottomOrderTrackerGrid;
    }
	
	public function buildDataArray($fields) {
        if (empty($fields))
            return false;

        $configurationCheck = new config\ConfigurationCheck($this->settingVars, $this->queryVars);
        $configurationCheck->buildDataArray($fields);
        $this->dbColumnsArray = $configurationCheck->dbColumnsArray;
        $this->displayCsvNameArray = $configurationCheck->displayCsvNameArray;
        $this->displayDbColumnArray = $configurationCheck->displayDbColumnArray;
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
       	$this->skuField = $this->makeFieldsToAccounts($this->skuField);
        return;
    }
	
    private function makeFieldsToAccounts($srcArray) {
        $tempArr = array();
        foreach ($srcArray as $value) {
            $value = explode("#", $value);
            if (count($value) > 1)
                $tempArr[] = $this->dbColumnsArray[$value[0]] . "_" . $this->dbColumnsArray[$value[1]];
            else
                $tempArr[] = $this->dbColumnsArray[$value[0]];
        }
        return $tempArr;
    }
   
    /*public function getAll() {
        $tablejoins_and_filters = "";

        if (isset($_REQUEST["cl"]) && $_REQUEST["cl"] != "") {
            $extraFields[] = $this->settingVars->clusterID;
            $tablejoins_and_filters .= " AND " . $this->settingVars->clusterID . " ='" . $_REQUEST["cl"] . "' ";
        }
        
        if (isset($_REQUEST["FS"]) && $_REQUEST["FS"] != '')
        {
            if (isset($_REQUEST["FS"]['PIN']) && $_REQUEST["FS"]['PIN'] != '' ){
                $extraFields[] = $this->pinIdField;
                $tablejoins_and_filters .= " AND " . $this->pinIdField . " = '" . $_REQUEST["FS"]['PIN']."' ";
            }

            if (isset($_REQUEST["FS"]['SNO']) && $_REQUEST["FS"]['SNO'] != '') 
            {
                $extraFields[] = $this->snoIdField;
                $tablejoins_and_filters .= " AND " . $this->snoIdField ." = '".$_REQUEST["FS"]['SNO']."' ";
            }
        }

        $this->prepareTablesUsedForQuery($extraFields);
        $tablejoins_and_filters1 = parent::getAll();
        $tablejoins_and_filters1 .= $tablejoins_and_filters;

        return $tablejoins_and_filters1;
    }*/    
    
}

?>