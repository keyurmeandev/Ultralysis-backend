<?php
namespace classes\tsd;

use datahelper;
use projectsettings;
use filters;
use db;
use config;

class MyStock extends config\UlConfig {
    /*     * ***
	 * go()
     * Default gateway function, should be similar in all DATA CLASS
     * arguments:
     * $settingVars [project settingsGateway variables]
     * @return $this->jsonOutput with all data that should be sent to client app
     * *** */
    public $isPPCInclude = 'N';
    public function go($settingVars) {
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES
        $this->redisCache = new \utils\RedisCache($this->queryVars);
        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll	

        if ($this->settingVars->isDynamicPage) {
        	$this->topGridField = $this->getPageConfiguration('top_grid_field', $this->settingVars->pageID)[0];
        	$this->bottomGridField = $this->getPageConfiguration('bottom_grid_field', $this->settingVars->pageID)[0];
        	$this->showPiecesPerCaseCol = $this->getPageConfiguration('show_pieces_per_case', $this->settingVars->pageID);
            $this->showStockedStores = $this->getPageConfiguration('show_stocked_stores', $this->settingVars->pageID);

        	$tempBuildFieldsArray = array($this->topGridField, $this->bottomGridField);

        	$this->buildDataArray($tempBuildFieldsArray);
            $this->buildPageArray();

            
            $this->gridColumnHeader = []; 
            $this->isStockedStores = false;
            if(is_array($this->showStockedStores) && !empty($this->showStockedStores) && $this->showStockedStores[0] == true){
                $this->gridColumnHeader['STOCKED_STORES']   = 'STOCKED STORES';
                $this->isStockedStores = true;
            }

            if(isset($this->settingVars->projectTypeID) && $this->settingVars->projectTypeID == 27) 
            {
                if(is_array($this->showPiecesPerCaseCol) && !empty($this->showPiecesPerCaseCol) && $this->showPiecesPerCaseCol[0] == true)
                    $this->gridColumnHeader['PPC']   = 'PIECES PER CASE';

                $this->gridColumnHeader['STOCK_UNITS']= 'STOCK UNITS';
                $this->isPPCInclude = 'Y';
                $this->jsonOutput['gridColumnNames'] = $this->gridColumnHeader;
                $this->jsonOutput['OHSQColHeader'] = "STOCK (CASES)";
            }

            if(count($this->gridColumnHeader) > 0)
                $this->jsonOutput['gridColumnNames'] = $this->gridColumnHeader;

        } else {
            $this->configurationFailureMessage();
        }

        $action = $_REQUEST['action'];
        //COLLECTING TOTAL SALES
        switch ($action) {
            case 'filterByTopGridID':
                $this->bottomGridData();
                break;
            case 'topGridData';
                $this->topGridData();
				break;
			case 'bottomGridData';
                $this->bottomGridData();
                break;
            case "skuChange":
                $this->skuSelect();
                break;
        }
        
        return $this->jsonOutput;
    }
	
	/**
	 * topGridData()
     * This Function is used to retrieve list based on set parameters     
	 * @access private	
     * @return $this->jsonOutput with all data that should be sent to client app     
     */
    private function topGridData() {
		
		$this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $this->measureFields[] = $this->topGridIdField;
        $this->measureFields[] = $this->topGridNameField;
        $IncludePPC = '';
        if($this->isPPCInclude == 'Y'){
            $this->measureFields[] = $this->settingVars->ppcColumnField;
            $IncludePPC = ",MAX(".$this->settingVars->ppcColumnField.") AS PPC ";
        }
		
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->queryPart = $this->getAll();
        
        //MAIN TABLE QUERY
        /*$query = "SELECT ".$this->settingVars->maintable.".SIN AS SKUID " .
                ",".$this->skuName." AS SKU " .
                ",SUM((CASE WHEN ".$this->settingVars->DatePeriod." IN('" . filters\timeFilter::$tyDaysRange[0] . "') THEN ".$this->ohq." ELSE 0 END)) AS OHQ" .
                ",SUM((CASE WHEN ". $this->settingVars->DatePeriod." IN('" . implode("','", filters\timeFilter::$ty14DaysRange) . "') THEN ".$this->settingVars->ProjectValue." ELSE 0 END)) AS SALES" .
				",".$this->itemStatus." AS ITEMSTATUS " .
                " FROM " . $this->settingVars->tablename . " " . $this->queryPart .                 
                "GROUP BY SKUID, SKU, ITEMSTATUS ORDER BY OHQ DESC";*/

        if(isset($this->settingVars->projectTypeID) && $this->settingVars->projectTypeID == 27)
        {
			$query = "SELECT ".$this->topGridIdField." AS topGridID " .
					",SUM((CASE WHEN ".$this->settingVars->DatePeriod." IN ('" . filters\timeFilter::$tyDaysRange[0] . "') THEN (stock*".$this->settingVars->ppcColumnField.") ELSE 0 END)) AS PPC ".
                    " FROM " . $this->settingVars->tablename . " " . $this->queryPart .                 
					"GROUP BY topGridID";
                    
            $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
            if ($redisOutput === false) {
                $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
                $this->redisCache->setDataForHash($result);
            } else {
                $result = $redisOutput;
            }
            
            $ppcArray = array();
            if(is_array($result) && !empty($result))
            {
                foreach($result as $data)
                {
                    $ppcArray[$data['topGridID']] += $data['PPC'];
                }
            }
        }

        $stockedStoresArr = [];
        if ($this->isStockedStores) {

            /*$query = "SELECT ".$this->topGridIdField." AS topGridID" .
            ",COUNT(DISTINCT ".$this->settingVars->maintable.".SNO) AS CNTSNO".
            ",SUM((CASE WHEN ".$this->settingVars->DatePeriod." IN ('" . filters\timeFilter::$tyDaysRange[0] . "') THEN stock ELSE 0 END)) AS OHQ ".
            " FROM " . $this->settingVars->tablename . " " . $this->queryPart .
            "GROUP BY topGridID HAVING OHQ > 0";*/

            $query = "SELECT ".$this->topGridIdField." AS topGridID" .
            ", COUNT(DISTINCT((CASE WHEN stock > 0  AND ".$this->settingVars->DatePeriod." IN ('" . filters\timeFilter::$tyDaysRange[0] . "') THEN ".$this->settingVars->maintable.".SNO END))) AS CNTSNO".
            " FROM " . $this->settingVars->tablename . " " . $this->queryPart .
            "GROUP BY topGridID";
            
            $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
            if ($redisOutput === false) {
                $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
                $this->redisCache->setDataForHash($result);
            } else {
                $result = $redisOutput;
            }

            $stockedStoresArr = array_column($result, 'CNTSNO','topGridID');
        }
                
        $query = "SELECT ".$this->topGridIdField." AS topGridID " .
                ",".$this->topGridNameField." AS topGridName " .
                $IncludePPC.
                ",SUM((CASE WHEN ".$this->settingVars->DatePeriod." IN('" . filters\timeFilter::$tyDaysRange[0] . "') THEN stock ELSE 0 END)) AS OHQ" .
                ",SUM((CASE WHEN ". $this->settingVars->DatePeriod." IN('" . implode("','", filters\timeFilter::$ty14DaysRange) . "') THEN ".$this->settingVars->ProjectValue." ELSE 0 END)) AS SALES" .
				",status AS ITEMSTATUS ";
        
        $query .= " FROM " . $this->settingVars->tablename . " " . $this->queryPart .                 
                "GROUP BY topGridID, topGridName, ITEMSTATUS ORDER BY OHQ DESC";
		//echo $query;exit;

        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }

        $arr = array();
		if (is_array($result) && !empty($result)) {
			foreach ($result as $key => $value) {
				$dataSet = array();
				$dataSet['topGridID']         = $value['topGridID'];
				$dataSet['topGridName']       = $value['topGridName'];
                $dataSet['SALES']             = number_format($value['SALES'], 2, '.', '');
				$dataSet['OHQ']               = (int) $value['OHQ'];

                if($this->isPPCInclude == 'Y'){
                    $dataSet['PPC']         = $value['PPC'];
                    //$dataSet['STOCK_UNITS'] = ($dataSet['OHQ']*$value['PPC']);
                    $dataSet['STOCK_UNITS'] = $ppcArray[$value['topGridID']];
                }

                if ($this->isStockedStores)
                    $dataSet['STOCKED_STORES'] = isset($stockedStoresArr[$value['topGridID']]) ? (double) $stockedStoresArr[$value['topGridID']] : 0;

				$dataSet['ITEMSTATUS']        = $value['ITEMSTATUS'];
                $arr[] = $dataSet;
			}
		} // end if
        $this->jsonOutput['topGridData'] = $arr;
    }
	
	/**
	 * bottomGridData()
     * This Function is used to retrieve list based on set parameters     
	 * @access private	
     * @return $this->jsonOutput with all data that should be sent to client app     
     */
    private function bottomGridData() {
		
            $this->gridColumnHeader['PPC']   = 'PIECES PER CASE';
            $this->jsonOutput['gridColumnNames'] = $this->gridColumnHeader;        
        
			// For Territory
			if (isset($_REQUEST["territoryLevel"]) && $_REQUEST["territoryLevel"] != "") {
				$addTerritoryColumn = ",".$this->settingVars->territoryTable . ".Level".$_REQUEST["territoryLevel"]." AS TERRITORY";
				$addTerritoryGroup = ",TERRITORY";
			}
			else
			{
				$addTerritoryColumn = '';
				$addTerritoryGroup = '';
			}
			
			$this->settingVars->tableUsedForQuery = $this->measureFields = array();
			$this->measureFields[] = $this->topGridIdField;
			$this->measureFields[] = $this->topGridNameField;
			$this->measureFields[] = $this->bottomGridIdField;
			$this->measureFields[] = $this->bottomGridNameField;
			$this->measureFields[] = $this->settingVars->clusterID;

            $IncludePPC = '';
			if($this->isPPCInclude == 'Y'){
                $this->measureFields[] = $this->settingVars->ppcColumnField;
                $IncludePPC = ",MAX(".$this->settingVars->ppcColumnField.") AS PPC ";
            }

			$this->settingVars->useRequiredTablesOnly = true;
			if (is_array($this->measureFields) && !empty($this->measureFields)) {
				$this->prepareTablesUsedForQuery($this->measureFields);
			}
			$this->queryPart = $this->getAll();
			
			//MAIN TABLE QUERY
			/*$query = "SELECT ".$this->settingVars->maintable.".skuID AS SKUID " .
					",TRIM(" . $this->skuName . ") AS SKU" .
					"," . $this->storeID . " AS SNO " .
					",TRIM(MAX(" . $this->storeName . ")) AS STORE " .
					$addTerritoryColumn.
                    ",SUM((CASE WHEN ". $this->settingVars->DatePeriod." IN('" . implode("','", filters\timeFilter::$ty14DaysRange) . "') THEN ".$this->settingVars->ProjectValue." ELSE 0 END)) AS SALES" .
					",TRIM(MAX(" . $this->settingVars->clusterID . ")) AS CLUSTER " .
					",(CASE WHEN ". $this->odate ." > ". $this->idate ." THEN 'N' ELSE 'Y' END) AS OPEN" .
					",TRIM(MAX((CASE WHEN ".$this->settingVars->DatePeriod." IN('" . filters\timeFilter::$tyDaysRange[0] . "') THEN ".$this->ohq." ELSE 0 END))) AS OHQ" .
					" FROM " . $this->settingVars->tablename . " " . $this->queryPart .                 
					" AND OHQ <> 0 ".
					"GROUP BY SKUID, SKU,SNO, OPEN ".$addTerritoryGroup." ORDER BY OHQ DESC";*/

			$query = "SELECT ".$this->topGridIdField." AS topGridID " .
					",TRIM(" . $this->topGridNameField . ") AS topGridName " .
					"," . $this->bottomGridIdField . " AS bottomGridID " .
					",TRIM(MAX(" . $this->bottomGridNameField . ")) AS bottomGridName " .
					$addTerritoryColumn.
                    $IncludePPC.
                    ",SUM((CASE WHEN ". $this->settingVars->DatePeriod." IN ('" . implode("','", filters\timeFilter::$ty14DaysRange) . "') THEN ".$this->settingVars->ProjectValue." ELSE 0 END)) AS SALES" .
					",TRIM(MAX(" . $this->settingVars->clusterID . ")) AS CLUSTER " .
					//",(CASE WHEN ". $this->odate ." > ". $this->idate ." THEN 'N' ELSE 'Y' END) AS OPEN" .
					",TRIM(MAX((CASE WHEN ".$this->settingVars->DatePeriod." IN ('" . filters\timeFilter::$tyDaysRange[0] . "') THEN stock ELSE 0 END))) AS OHQ ";

            $query .= ($this->isPPCInclude == 'Y') ? ", ".$this->settingVars->getMydateSelect($this->settingVars->DatePeriod)." as MYDATE " : ""; 
                    
            $query .= " FROM " . $this->settingVars->tablename . " " . $this->queryPart .                 
					  " AND stock <> 0 AND ".$this->settingVars->DatePeriod." IN ('" . filters\timeFilter::$ty14DaysRange[0] . "') ".
					"GROUP BY topGridID, topGridName,bottomGridID ".$addTerritoryGroup." ORDER BY OHQ DESC";
			//echo $query;exit;
			$redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
            if ($redisOutput === false) {
                $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
                $this->redisCache->setDataForHash($result);
            } else {
                $result = $redisOutput;
            }

			$cnt = 0;
			if (is_array($result) && !empty($result)) {
				foreach ($result as $key => $value) {
					$index 								= $value['topGridID'] . '_' . $value['bottomGridID'];								
					$dataSet[$cnt]['topGridID']         = $value['topGridID'];
					$dataSet[$cnt]['topGridName']       = $value['topGridName'];
                    $dataSet[$cnt]['SALES']             = $value['SALES'];
					$dataSet[$cnt]['bottomGridID'] 		= $value['bottomGridID'];
					$dataSet[$cnt]['bottomGridName'] 	= utf8_encode($value['bottomGridName']);
					$dataSet[$cnt]['CLUSTER'] 			= utf8_encode($value['CLUSTER']);
					$dataSet[$cnt]['OHQ']               = (int)$value['OHQ'];

                    if($this->isPPCInclude == 'Y'){
                        $dataSet[$cnt]['OHQ']               = (double)$value['OHQ'];
                        $dataSet[$cnt]['PPC']         = $value['PPC'];
                        $dataSet[$cnt]['STOCK_UNITS'] = (double)($value['OHQ']*$value['PPC']);
                        $dataSet[$cnt]['MYDATE']         = date("d-M-Y" ,strtotime($value['MYDATE']));
                    }

					if($value['TERRITORY'])
						$dataSet[$cnt]['TERRITORY'] 	= $value['TERRITORY'];

					$cnt++;
				}
			} // end if
        $this->jsonOutput['bottomGridData'] = $dataSet;
    }

    public function skuSelect() {

        $topGridId    = $_REQUEST['topGridID'];
        $bottomGridID = $_REQUEST['bottomGridID'];
        $IncludePPC = '';

        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $this->measureFields[] = $this->topGridIdField;
        //$this->measureFields[] = $this->topGridNameField;
        $this->measureFields[] = $this->bottomGridIdField;
        //$this->measureFields[] = $this->bottomGridNameField;
        //$this->measureFields[] = $this->settingVars->clusterID;

        $IncludePPC = '';
        if($this->isPPCInclude == 'Y'){
            $this->measureFields[] = $this->settingVars->ppcColumnField;
            $IncludePPC = ",MAX(".$this->settingVars->ppcColumnField.") AS PPC ";
        }

        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->queryPart = $this->getAll();

        $query = "SELECT " . $this->settingVars->DatePeriod . " AS DAY" .
                ",SUM(".$this->settingVars->ProjectValue.") AS SALES " .
                ",SUM(stock) AS STOCK ".
                $IncludePPC.
                "FROM " . $this->settingVars->tablename . " " . $this->queryPart .
                //" AND " . $this->settingVars->DatePeriod . " IN ('" . implode("','", filters\timeFilter::$tyDaysRange) . "') " .
                "AND " . $this->topGridIdField." = ".$topGridId." AND ". $this->bottomGridIdField." = ".$bottomGridID.
                " GROUP BY DAY ORDER BY DAY ASC";

        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }
        $value = array();
        if (is_array($result) && !empty($result)){
            foreach ($result as $data) {
                $value['SALES'][] = $data['SALES'];
                if($this->isPPCInclude == 'Y') {
                    $value['STOCK'][] = $data['STOCK']*$data['PPC'];
                } else {
                    $value['STOCK'][] = $data['STOCK'];
                }
                $value['DAY'][] = $data['DAY'];
            }
        }
        $this->jsonOutput['skuSelect'] = $value;
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
        $topGridFieldPart = explode("#", $this->topGridField);
        $bottomGridFieldPart = explode("#", $this->bottomGridField);

        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
            $fetchConfig = true;
            $this->jsonOutput['pageConfig'] = array(
                'enabledFilters' => $this->getPageConfiguration('filter_settings', $this->settingVars->pageID)
            );

            $this->jsonOutput['topGridColumns']['topGridID'] =  (count($topGridFieldPart) > 1) ? $this->displayCsvNameArray[$topGridFieldPart[1]] : $this->displayCsvNameArray[$topGridFieldPart[0]];
            $this->jsonOutput['topGridColumns']['topGridName'] =  $this->displayCsvNameArray[$topGridFieldPart[0]];

            $this->jsonOutput['bottomGridColumns']['bottomGridID'] = (count($bottomGridFieldPart) > 1) ? $this->displayCsvNameArray[$bottomGridFieldPart[1]] : $this->displayCsvNameArray[$bottomGridFieldPart[0]];
            $this->jsonOutput['bottomGridColumns']['bottomGridName'] = $this->displayCsvNameArray[$bottomGridFieldPart[0]];
            
        }

        $topGridField = strtoupper($this->dbColumnsArray[$topGridFieldPart[0]]);
        $topGridField = (count($topGridFieldPart) > 1) ? strtoupper($topGridField . "_" . $this->dbColumnsArray[$topGridFieldPart[1]]) : $topGridField;

        $this->topGridIdField = (isset($this->settingVars->dataArray[$topGridField]) && isset($this->settingVars->dataArray[$topGridField]['ID'])) ? 
            $this->settingVars->dataArray[$topGridField]['ID'] : $this->settingVars->dataArray[$topGridField]['NAME'];
        $this->topGridNameField = $this->settingVars->dataArray[$topGridField]['NAME'];

        $bottomGridField = strtoupper($this->dbColumnsArray[$bottomGridFieldPart[0]]);
        $bottomGridField = (count($bottomGridFieldPart) > 1) ? strtoupper($bottomGridField . "_" . $this->dbColumnsArray[$bottomGridFieldPart[1]]) : $bottomGridField;

        $this->bottomGridIdField = (isset($this->settingVars->dataArray[$bottomGridField]) && isset($this->settingVars->dataArray[$bottomGridField]['ID'])) ? 
            $this->settingVars->dataArray[$bottomGridField]['ID'] : $this->settingVars->dataArray[$bottomGridField]['NAME'];
        $this->bottomGridNameField = $this->settingVars->dataArray[$bottomGridField]['NAME'];

        return;
    }
	
	/* ****
     * COLLECTS TABLE JOINING STRING FROM PROJECT SETTINGS
     * ALSO APPLYS FILTER CONDITIONS TO THE STRING
     * AND RETURNS $tablejoins_and_filters
     * ****/
    public function getAll() {
        $extraFields = array();

        if (isset($_REQUEST["cl"]) && $_REQUEST["cl"] != "") {
            $extraFields[] = $this->settingVars->clusterID;
            $tablejoins_and_filters .= " AND " . $this->settingVars->clusterID . " ='" . $_REQUEST["cl"] . "' ";
        }

        if (isset($_REQUEST["selectedTopGridID"]) && $_REQUEST["selectedTopGridID"] != "") {
            $extraFields[] = $this->topGridIdField;
            $tablejoins_and_filters .= " AND " . $this->topGridIdField . " ='" . $_REQUEST["selectedTopGridID"] . "' ";
        }

        $this->prepareTablesUsedForQuery($extraFields);
        $tablejoins_and_filters1 = parent::getAll();
        $tablejoins_and_filters1 .= $tablejoins_and_filters;

        return $tablejoins_and_filters1;
    }
}
?>