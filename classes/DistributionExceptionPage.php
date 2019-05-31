<?php
namespace classes;

use datahelper;
use projectsettings;
use filters;
use utils;
use db;
use config;

class DistributionExceptionPage extends config\UlConfig {

    public $dbColumnsArray;
    public $displayCsvNameArray;

    public function go($settingVars) {
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES
        $this->redisCache = new utils\RedisCache($this->queryVars);
        //$this->queryPart = $this->getAll(); //USES OWN getAll FUNCTION

        $this->settingVars->pageName = (empty($this->settingVars->pageName)) ? $this->settingVars->pageID.'_DistributionExceptionPage' : $settingVars->pageName;

        if ($this->settingVars->isDynamicPage) {
            $this->accountField = $this->getPageConfiguration('account_field', $this->settingVars->pageID)[0];
            $this->customerField = $this->getPageConfiguration('customer_field', $this->settingVars->pageID)[0];
            $this->storeField = $this->getPageConfiguration('store_field', $this->settingVars->pageID)[0];

            $buildDataFields = array($this->accountField, $this->customerField, $this->storeField);
            
            $this->buildDataArray($buildDataFields);
            $this->buildPageArray();
        }

        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true'){
            $this->fetchConfig(); //ADDING TO OUTPUT
        }


        $action = $_REQUEST["action"];

        switch ($action) {
            case "reload":                
                $this->prepareGridData();
                break;
            case "getBottomGridData":
                $this->getBottomGridData();
                break;
        }

        return $this->jsonOutput;
    }

    function fetchConfig(){
        $accountFieldPart = explode("#", $this->accountField);
        $storeFieldPart = explode("#", $this->storeField);

        $this->jsonOutput['pageConfig']['enabledFilters'] = $this->getPageConfiguration('filter_settings', $this->settingVars->pageID);

        $this->jsonOutput['gridColumns']['ID'] = (count($accountFieldPart) > 1) ? $this->displayCsvNameArray[$accountFieldPart[1]] : $this->displayCsvNameArray[$accountFieldPart[0]]; 
        $this->jsonOutput['gridColumns']['PRODUCT'] = $this->displayCsvNameArray[$accountFieldPart[0]]; 
        $this->jsonOutput['bottomGridColumns']['SNO'] = (count($storeFieldPart) > 1) ? $this->displayCsvNameArray[$storeFieldPart[1]] : $this->displayCsvNameArray[$storeFieldPart[0]]; ; 
        $this->jsonOutput['bottomGridColumns']['STORE'] = $this->displayCsvNameArray[$storeFieldPart[0]]; 
            
    }

    function getBottomGridData(){
    	/*[START] ON THIS PAGE WE DONT REQUIRED THE TIMEFILTES*/
        filters\timeFilter::$tyWeekRange = NULL;
        filters\timeFilter::$lyWeekRange = NULL;
        /*[END] ON THIS PAGE WE DONT REQUIRED THE TIMEFILTES*/

		$timeRange = filters\timeFilter::getYearWeekWithinRange(0,$_REQUEST['base'],$this->settingVars); // For T4Store
		$timeRangeL4StoreArr = filters\timeFilter::getYearWeekWithinRange($_REQUEST['comparison'],$_REQUEST['base'],$this->settingVars);//For L4Store
		$timeRangeDt = array_merge($timeRange,$timeRangeL4StoreArr);

		$timeRangeT4Store = " CONCAT(".$this->settingVars->yearperiod.",".$this->settingVars->weekperiod.") IN (".implode("," , $timeRange).") ";
		$timeRangeL4Store = " CONCAT(".$this->settingVars->yearperiod.",".$this->settingVars->weekperiod.") IN (".implode("," , $timeRangeL4StoreArr).") ";

        filters\timeFilter::$tyWeekRange = $timeRangeT4Store;
        filters\timeFilter::$lyWeekRange = $timeRangeL4Store;

        if(empty($timeRangeL4StoreArr))
            filters\timeFilter::$lyWeekRange = NULL;
        
		$this->settingVars->tableUsedForQuery = $this->measureFields = array();
		$measureSelectRes    = $this->prepareMeasureSelectPart();
		$this->measureFields = $measureSelectRes['measureFields'];
        $measureSelectionArr = $measureSelectRes['measureSelectionArr'];
		$havingTYValue       = $measureSelectRes['havingTYValue'];
		$havingLYValue       = $measureSelectRes['havingLYValue'];

		$this->measureFields[] = $this->accountID;
        $this->measureFields[] = $this->accountName;
        $this->measureFields[] = $this->customerName;
        $this->measureFields[] = $this->storeName;
        $this->measureFields[] = $this->settingVars->grouptable.".gid";

        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll
		$tablename = $this->settingVars->tablename;
        // $querypart = $this->queryPart . " AND ".$this->settingVars->grouptable.".gid = ".$this->settingVars->maintable.".GID ";
        $this->queryPart .= " AND CONCAT(".$this->settingVars->yearperiod.",".$this->settingVars->weekperiod.") IN (".implode("," , $timeRangeDt).") ";
        $this->queryPart .= " AND ".$this->accountID." = '".$_REQUEST['ID']."' AND ".$this->customerName." = '".$_REQUEST['CUSTOMER']."' ";
		$measureSelect = implode(", ", $measureSelectionArr);

		$query = "SELECT ".
            " ".$this->storeName." AS STORE, ".
            " ".$this->storeID." AS SNO, ".
            " CONCAT(".$this->settingVars->yearperiod.",".$this->settingVars->weekperiod.") AS PERIOD,".
            " ".$measureSelect." ".
            " FROM ".$tablename . $this->queryPart.
            " GROUP BY STORE,SNO,PERIOD ";

		$redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }

		$requiredGridFields = ['STORE','SNO','PERIOD', $havingTYValue, $havingLYValue];
        $result = $this->redisCache->getRequiredData($result, $requiredGridFields, $havingTYValue, $havingTYValue, $havingLYValue);

		$storesGain = $storesLost = array();
		if(is_array($result) && count($result)>0){
			$LyArr = $TyArr = [];
			foreach ($result as $data) {
				$gdKy = $data['SNO'].str_replace("'","_",str_replace('"','_',str_replace(' ','', $data['STORE'])));
				if(in_array($data['PERIOD'], $timeRangeL4StoreArr)){
					$LyArr[$gdKy]['SNO']   = $data['SNO'];
					$LyArr[$gdKy]['STORE'] = $data['STORE'];
					$LyArr[$gdKy]['SALES'] +=$data[$havingLYValue];
				}else if(in_array($data['PERIOD'], $timeRange)){
					$TyArr[$gdKy]['SNO']   = $data['SNO'];
					$TyArr[$gdKy]['STORE'] = $data['STORE'];
					$TyArr[$gdKy]['SALES'] +=$data[$havingTYValue];
				}
			}
			if(count($TyArr) > 0 && count($LyArr) > 0){
				$storesGain = array_diff_key($TyArr,$LyArr);
				$storesLost = array_diff_key($LyArr,$TyArr);
				$storesGain = utils\SortUtility::sort2DArray($storesGain,'SALES', utils\SortTypes::$SORT_DESCENDING);
				$storesLost = utils\SortUtility::sort2DArray($storesLost,'SALES', utils\SortTypes::$SORT_DESCENDING);
				$storesGain = array_values($storesGain);
				$storesLost = array_values($storesLost);
			}else 
			if(count($TyArr)>0){
				$storesGain = $TyArr;
				$storesGain = utils\SortUtility::sort2DArray($storesGain,'SALES', utils\SortTypes::$SORT_DESCENDING);
				$storesGain = array_values($storesGain);
			}else 
			if(count($LyArr)>0){
				$storesLost = $LyArr;
				$storesLost = utils\SortUtility::sort2DArray($storesLost,'SALES', utils\SortTypes::$SORT_DESCENDING);
				$storesLost = array_values($storesLost);
			}
		}
		$this->jsonOutput["storesGainGridValue"] = $storesGain;
        $this->jsonOutput["storesLostGridValue"] = $storesLost;
    }

    function prepareGridData() {
        /*[START] ON THIS PAGE WE DONT REQUIRED THE TIMEFILTES*/
        filters\timeFilter::$tyWeekRange = NULL;
        filters\timeFilter::$lyWeekRange = NULL;
        /*[END] ON THIS PAGE WE DONT REQUIRED THE TIMEFILTES*/

		$timeRange = filters\timeFilter::getYearWeekWithinRange(0,$_REQUEST['base'],$this->settingVars); // For T4Store
		$timeRangeL4Store = $timeRangeL4StoreTmp = filters\timeFilter::getYearWeekWithinRange($_REQUEST['comparison'],$_REQUEST['base'],$this->settingVars);//For L4Store
		$timeRangeDt = array_merge($timeRange,$timeRangeL4Store);

		$timeRangeT4Store = " CONCAT(".$this->settingVars->yearperiod.",".$this->settingVars->weekperiod.") IN (".implode("," , $timeRange).") ";
        $timeRangeL4Store = " CONCAT(".$this->settingVars->yearperiod.",".$this->settingVars->weekperiod.") IN (".implode("," , $timeRangeL4Store).") ";

        filters\timeFilter::$tyWeekRange = $timeRangeT4Store;
        filters\timeFilter::$lyWeekRange = $timeRangeL4Store;

        if(empty($timeRangeL4Store))
            filters\timeFilter::$lyWeekRange = NULL;

		$this->settingVars->tableUsedForQuery = $this->measureFields = array();
		$measureSelectRes    = $this->prepareMeasureSelectPart();
		$this->measureFields = $measureSelectRes['measureFields'];
        $measureSelectionArr = $measureSelectRes['measureSelectionArr'];
		$havingTYValue       = $measureSelectRes['havingTYValue'];
		$havingLYValue       = $measureSelectRes['havingLYValue'];

        $this->measureFields[] = $this->accountID;
        $this->measureFields[] = $this->accountName;
        $this->measureFields[] = $this->customerName;
        $this->measureFields[] = $this->storeName;
        $this->measureFields[] = $this->settingVars->grouptable.".gid";

        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll
		$tablename = $this->settingVars->tablename;
        // $querypart = $this->queryPart . " AND ".$this->settingVars->grouptable.".gid = ".$this->settingVars->maintable.".GID ";
        $this->queryPart .= " AND CONCAT(".$this->settingVars->yearperiod.",".$this->settingVars->weekperiod.") IN (".implode("," , $timeRangeDt).") ";
		$measureSelect = implode(", ", $measureSelectionArr);
		$query = "SELECT ".$this->accountID." as ID , 
        				 ".$this->accountName." AS  PRODUCT , 
        				 ".$this->customerName." AS CUSTOMER, 
        				 "." COUNT(DISTINCT(CASE WHEN ".$this->settingVars->ProjectVolume." > 0 AND ".$timeRangeT4Store." THEN 1 END) * ".$this->storeID." ) AS T4STORES, ";

        if(!empty($timeRangeL4StoreTmp)) 
            $query .= " COUNT(DISTINCT(CASE WHEN ".$this->settingVars->ProjectVolume." > 0 AND ".$timeRangeL4Store." THEN 1 END) * ".$this->storeID.") AS L4STORES, ";
        else
            $query .= " 0 AS L4STORES, ";

        $query .= " ".$measureSelect." "." FROM ".$tablename . $this->queryPart ." GROUP BY ID,PRODUCT,CUSTOMER ";
		            //" ORDER BY L4SALES DESC";

        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }

        $requiredGridFields = ['ID', 'PRODUCT', 'CUSTOMER', 'T4STORES', 'L4STORES', $havingTYValue, $havingLYValue];
        $result = $this->redisCache->getRequiredData($result, $requiredGridFields, $havingTYValue, $havingTYValue, $havingLYValue);
		$result = utils\SortUtility::sort2DArray($result,$havingTYValue, utils\SortTypes::$SORT_DESCENDING);
		$rows = array();
        if(isset($result) && !empty($result))
        {
            foreach ($result as $key => $data) {
              	if(isset($data['T4STORES'])) {
	                $data['L4STORESVAR'] = $data['L4STORES'];
	                $data['VAR'] = $data['T4STORES'] - $data['L4STORESVAR'];
	                $data['VARPCT'] = ($data['L4STORESVAR']>0) ? ($data['VAR']/$data['L4STORESVAR'])*100 : 0;
	                $data['L4STORES'] = $data['T4STORES'];

	                $data['L4SALESVAR'] = $data[$havingLYValue];
	                $data['VAR2'] = $data[$havingTYValue] - $data['L4SALESVAR'];
	                $data['VARPCT2'] = ($data['L4SALESVAR']>0) ? ($data['VAR2']/$data['L4SALESVAR'])*100 : 0;
					$data['L4SALES'] = $data[$havingTYValue];

	                if($data['VARPCT']  > $_REQUEST['deviation'] || $data['VARPCT']  < -$_REQUEST['deviation']){
	                    $rows[] = $data;
	                }
            	}
            }
        }
        $this->jsonOutput["gridValue"] = $rows;
    }

	public function buildPageArray() {

        $accountFieldPart = explode("#", $this->accountField);
        
        $accountField = strtoupper($this->dbColumnsArray[$accountFieldPart[0]]);
        $accountField = (count($accountFieldPart) > 1) ? strtoupper($accountField."_".$this->dbColumnsArray[$accountFieldPart[1]]) : $accountField;

        $this->accountID = (isset($this->settingVars->dataArray[$accountField]) && isset($this->settingVars->dataArray[$accountField]['ID'])) ? $this->settingVars->dataArray[$accountField]['ID'] : $this->settingVars->dataArray[$accountField]['NAME'];
        $this->accountName = $this->settingVars->dataArray[$accountField]['NAME'];

        $customerField = strtoupper($this->dbColumnsArray[$this->customerField]);
        $this->customerName = $this->settingVars->dataArray[$customerField]['NAME'];


        $storeFieldPart = explode("#", $this->storeField);
        $storeField = strtoupper($this->dbColumnsArray[$storeFieldPart[0]]);
        $storeField = (count($storeFieldPart) > 1) ? strtoupper($storeField."_".$this->dbColumnsArray[$storeFieldPart[1]]) : $storeField;

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