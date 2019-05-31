<?php

namespace classes;

use datahelper;
use projectsettings;
use filters;
use utils;
use db;
use config;

class StoreScatterByProduct extends config\UlConfig {
    /*     * ***
	 * go()
     * Default gateway function, should be similar in all DATA CLASS
     * arguments:
     * $settingVars [project settingsGateway variables]
     * @return $this->jsonOutput with all data that should be sent to client app
     * *** */

    public function go($settingVars) {
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES
        $this->redisCache = new \utils\RedisCache($this->queryVars);
        $this->checkConfiguration();

        $this->settingVars->pageName = (empty($this->settingVars->pageName) && !empty($this->settingVars->pageID)) ? $this->settingVars->pageID.'_StoreScatterByProductPage' : $this->settingVars->pageName;
        
        if ($this->settingVars->isDynamicPage) {
            $this->skuField = $this->getPageConfiguration('sku_field', $this->settingVars->pageID)[0];
            /*
                $this->accountFields        = $this->getPageConfiguration('account_list_field', $this->settingVars->pageID);
                $this->showSegmentGrid      = $this->getPageConfiguration('show_segment_grid', $this->settingVars->pageID)[0];
                $this->showSegmentChart     = $this->getPageConfiguration('show_segment_chart', $this->settingVars->pageID)[0];
                $this->showTopGrid          = $this->getPageConfiguration('show_top_grid', $this->settingVars->pageID)[0];
                $this->tableField           = $this->getPageConfiguration('table_field', $this->settingVars->pageID)[0];
                $this->defaultSelectedField = $this->getPageConfiguration('table_settings_default_field', $this->settingVars->pageID)[0];
                $tempBuildFieldsArray       = array($this->storeField,$this->skuField);
                if($this->defaultSelectedField)
                    $tempBuildFieldsArray[] = $this->defaultSelectedField;
                    
                if(is_array($this->accountFields) && !empty($this->accountFields))
                    $tempBuildFieldsArray = array_merge($tempBuildFieldsArray, $this->accountFields);

                $buildDataFields = array();
                foreach ($tempBuildFieldsArray as $value)
                    if (!empty($value) && !in_array($value, $buildDataFields))
                        $buildDataFields[] = $value;
            */

            $buildDataFields[] = $this->skuField;
            $pageBasedPaginationSettingCount = $this->getPageConfiguration('page_based_pagination_setting_count', $this->settingVars->pageID);
            if(!empty($pageBasedPaginationSettingCount) && isset($pageBasedPaginationSettingCount[0]))
                $this->jsonOutput['pageBasedPaginationSettingCount'] = (int) $pageBasedPaginationSettingCount[0];

            $this->buildDataArray($buildDataFields);
            $this->buildPageArray();
        } else {
            $this->configurationFailureMessage();
        }

        $action = $_REQUEST['action'];
        //COLLECTING TOTAL SALES  
        switch ($action) {
            case 'filterByPlano':
                $this->filterByPlano();
                break;
            case 'skuVsStoreData':
                $this->skuVsStoreData();
                break;
            case 'skuChange':
                $this->skuSelect();
                break;
            /*case 'storeVsStoreData':
                $this->crossStoreAnalysisGrid();
                break;*/
            case "planoOverviewGrid":
                $this->planoOverviewGrid();
                break;
        }

        return $this->jsonOutput;
    }

    /**
     * skuVsStoreData()
     * This Function is used to retrieve list based on set parameters
     * @access private
     * @return $this->jsonOutput with all data that should be sent to client app
     */
    private function skuVsStoreData() {

        if (isset($_REQUEST['selectedSkuID']) && $_REQUEST['selectedSkuID'] != '')
            $selSkuID = $_REQUEST['selectedSkuID'];
        
        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $addTerritoryColumn = '';
        $addTerritoryGroup = '';

        // For Territory
        /*if (isset($_REQUEST["territoryLevel"]) && $_REQUEST["territoryLevel"] != "") {
            $addTerritoryColumn = ",".$this->settingVars->territoryTable . ".Level".$_REQUEST["territoryLevel"]." AS TERRITORY";
            $addTerritoryGroup = ",TERRITORY";

            $this->measureFields[] = $this->settingVars->territoryTable . ".Level".$_REQUEST["territoryLevel"];
        } else {
            $addTerritoryColumn = '';
            $addTerritoryGroup = '';
        }*/

        $this->settingVars->setCluster();
        $this->measureFields[] = $this->skuNameField;
        $this->measureFields[] = $this->skuNameFieldId;
        $this->measureFields[] = $this->storeName;
        $this->measureFields[] = $this->msq;
        $this->measureFields[] = $this->settingVars->clusterID;
        
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }

        // [STATIC FIX TO BYPASS THE TERRITORY LEVEL FROM THE QUERY WHERE PART]
        if (isset($_REQUEST["Level"]) && $_REQUEST["Level"] != "" && isset($_REQUEST["territoryLevel"]) && $_REQUEST["territoryLevel"] != "") {
            $reqLevel = $_REQUEST["Level"]; $_REQUEST["Level"] = '';
                $this->queryPart = $this->getAll();
            $_REQUEST["Level"] = $reqLevel;
        }else{
            $this->queryPart = $this->getAll();
        }

        $query = "SELECT ".$this->plandesc."  AS PLANDESC " .
                ", ".$this->skuNameField." as SKU_NAME".
                ", ".$this->skuNameFieldId." as SKU_ID".
                ", ".$this->storeID." as SNO".
                ", ".$this->storeName." as SNAME".
                ", SUM(".$this->settingVars->maintable.".qty) as UNIT_SALES ".
                // ", SUM(".$this->settingVars->maintable.".OHQ) as OHQ ".
                ",SUM((CASE WHEN " . $this->settingVars->DatePeriod . "= '" . filters\timeFilter::$tyDaysRange[0] . "' THEN 1 ELSE 0 END)*".$this->settingVars->maintable.".OHQ) AS OHQ " .
                    //", COUNT(DISTINCT((CASE WHEN ".$this->settingVars->maintable.".qty > 0 THEN ".$this->settingVars->maintable.".SNO END))) AS STORES " .
                $addTerritoryColumn.
                /*
                    ",TRIM(MAX(" . $this->settingVars->clusterID . ")) AS CLUSTER " .
                    ",MAX(".$this->odate.") AS OPENDATE" .
                    ",SUM(".$this->settingVars->ProjectValue.") AS SALES" .
                    ",SUM((CASE WHEN " . $this->settingVars->DatePeriod . "= '" . filters\timeFilter::$tyDaysRange[0] . "' THEN 1 ELSE 0 END)*" . $this->msq . ") AS MSQ " .
                */
                " FROM ". $this->settingVars->tablename . " ,". $this->settingVars->ranged_items . $this->queryPart . 
                " AND " . $this->settingVars->maintable . ".SNO=" . $this->settingVars->ranged_items . ".SNO" .
                " AND " . $this->settingVars->maintable . ".SIN=" . $this->settingVars->ranged_items . ".skuID" .
                " AND " . $this->settingVars->ranged_items . ".clientID='" . $this->settingVars->clientID."'".
                " AND " . $this->settingVars->ranged_items . ".GID='" . $this->settingVars->GID."'".
                " AND " . $this->settingVars->ranged_items . ".opendate < " . $this->settingVars->ranged_items . ".insertdate" .
                " AND " . $this->settingVars->skutable . ".PIN = " . $selSkuID .
                " GROUP BY PLANDESC, SKU_NAME, SKU_ID, SNO, SNAME ".$addTerritoryGroup. " ORDER BY SNAME ASC";

        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }

        $skuVsStoreAnalysisGrid = array();
        $chartArray1 = array("color"=>"#33cc33","key"=>"Group 0");
        if(is_array($result) && !empty($result)){
            foreach($result as $key => $data){
                /*if (isset($_REQUEST["Level"]) && $_REQUEST["Level"] != "" && isset($_REQUEST["territoryLevel"]) && $_REQUEST["territoryLevel"] != "") {
                    if($_REQUEST["Level"] == '1' && $data['TERRITORY'] == 'NOT CALLED'){
                        continue;
                    }
                    else if($_REQUEST["Level"] == '2' && $data['TERRITORY'] != 'NOT CALLED'){
                        continue;
                    }
                }*/
                $data['UNIT_SALES']      = (double) $data['UNIT_SALES'];
                $data['OHQ']             = (int) $data['OHQ'];
                $skuVsStoreAnalysisGrid[] = $data;
            }
        }

        $this->jsonOutput['skuVsStoreAnalysisGrid'] = $skuVsStoreAnalysisGrid;
    }

    /**
     * skuSelect()
     * This Function is used to retrieve sku data based on set parameters for graph     
     * @access private  
     * @return $this->jsonOutput with all data that should be sent to client app     
     */
    private function skuSelect() {

        $this->settingVars->useRequiredTablesOnly = true;
        $this->queryPart = $this->getAll();

        $query = "SELECT " . $this->settingVars->DatePeriod . ",  DATE_FORMAT(" . $this->settingVars->DatePeriod . ",'%a %e %b') AS DAY" .
                    ",SUM(" . $this->settingVars->ProjectVolume . ") AS SALES " .
                    ",SUM((CASE WHEN " . $this->ohq . ">0 THEN 1 ELSE 0 END)*" . $this->ohq . ") AS STOCK " .
                    ",SUM(".$this->ohaq.") AS OHAQ" .
                    ",SUM(".$this->baq.") AS BAQ" .
                    ",SUM((CASE WHEN " . $this->gsq . ">0 THEN 1 ELSE 0 END)*" . $this->gsq . ") AS GSQ " .
                    // ",SUM((CASE WHEN " . $storeTrans . ">0 THEN 1 ELSE 0 END)*" . $storeTrans . ") AS TRANSIT " .
                    " FROM " . $this->settingVars->tablename . " " . $this->queryPart .
                    " AND ". $this->settingVars->maintable .".OpenDate < ". $this->settingVars->maintable .".insertdate ".
                    // "AND " . filters\timeFilter::$tyWeekRange .
                    " GROUP BY DAY, " . $this->settingVars->DatePeriod . " " .
                    " ORDER BY " . $this->settingVars->DatePeriod . " ASC";

        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }

        $value = array();
        
        if (is_array($result) && !empty($result)) {
            foreach ($result as $data) {

                $value['SALES'][]   = $data['SALES'];
                $value['STOCK'][]   = $data['STOCK'];
                $value['ADJ'][]     = $data['OHAQ'] + $data['BAQ'];
                    //$value['TRANSIT'][] = $data['TRANSIT'];
                $value['TRANSIT'][] = 0;
                $value['DAY'][]     = $data['DAY'];
                $value['GSQ'][]     = $data['GSQ'];
            }
        } // end if
        
        $this->jsonOutput['skuSelect'] = $value;
    }
	
	/**
	 * planoOverviewGrid()
     * This Function is used to retrieve list based on set parameters     
	 * @access private	
     * @return $this->jsonOutput with all data that should be sent to client app     
     */
    private function planoOverviewGrid() {

        $minMaxQuery = "SELECT ".$this->plandesc."  AS PLANDESC, SNO, SUM(".$this->settingVars->ProjectValue.") as SALES FROM ".
            $this->settingVars->maintable." WHERE ".$this->settingVars->ProjectValue." > 0 and ".$this->plandesc." IS NOT NULL ".
            " group by PLANDESC, SNO ORDER BY PLANDESC ASC,SALES ASC,SNO ASC";
        
        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($minMaxQuery);
        if ($redisOutput === false) {
            $minMaxResult = $this->queryVars->queryHandler->runQuery($minMaxQuery, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($minMaxResult);
        } else {
            $minMaxResult = $redisOutput;
        }

        $plandescMinMax = array();
        if (is_array($minMaxResult) && !empty($minMaxResult)) {
            foreach ($minMaxResult as $key => $minMax) {
                if (!in_array($minMax['PLANDESC'], array_keys($plandescMinMax)))
                    $plandescMinMax[$minMax['PLANDESC']]['MIN'] = $minMax['SALES'];

                if ($minMaxResult[$key+1]['PLANDESC'] != $minMax['PLANDESC'])
                    $plandescMinMax[$minMax['PLANDESC']]['MAX'] = $minMax['SALES'];
            }
        }

        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $this->settingVars->useRequiredTablesOnly = true;

        // For Territory
		if (isset($_REQUEST["territoryLevel"]) && $_REQUEST["territoryLevel"] != "") {
			$addTerritoryColumn = ",MAX(".$this->settingVars->territoryTable . ".Level".$_REQUEST["territoryLevel"].") AS TERRITORY";
		}else{
			$addTerritoryColumn = '';
		}

		// [STATIC FIX TO BYPASS THE TERRITORY LEVEL FROM THE QUERY WHERE PART]
		/*if (isset($_REQUEST["Level"]) && $_REQUEST["Level"] != "" && isset($_REQUEST["territoryLevel"]) && $_REQUEST["territoryLevel"] != "") {
			$reqLevel = $_REQUEST["Level"]; 
            $_REQUEST["Level"] = '';
			$this->queryPart = $this->getAll();
			$_REQUEST["Level"] = $reqLevel;
		}else{*/
			$this->queryPart = $this->getAll();
		// }
        
       $query = "SELECT " .
                "count(DISTINCT((CASE WHEN ".$this->settingVars->ProjectValue." > 0 AND ".$this->odate." < ".$this->idate." THEN ".$this->settingVars->maintable.".SNO END))) AS SNO " .
                ",".$this->plandesc."  AS PLANDESC " .                                                
                ",SUM(".$this->settingVars->ProjectValue.") AS SALES" .
                $addTerritoryColumn.
                " FROM " .$this->settingVars->tablename . " ,". $this->settingVars->ranged_items . $this->queryPart . 
                " AND " . $this->settingVars->maintable . ".SNO=" . $this->settingVars->ranged_items . ".SNO" .
                " AND " . $this->settingVars->maintable . ".SIN=" . $this->settingVars->ranged_items . ".skuID" .
                " AND " . $this->settingVars->ranged_items . ".clientID='" . $this->settingVars->clientID."'".
                " AND " . $this->settingVars->ranged_items . ".GID='" . $this->settingVars->GID."'".
                " AND " . $this->settingVars->ranged_items . ".opendate < " . $this->settingVars->ranged_items . ".insertdate" .
                " AND " . $this->settingVars->ProjectValue . " > 0" .
                " AND " .$this->plandesc." IS NOT NULL".
                " GROUP BY PLANDESC ORDER BY SALES DESC";

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
				if (isset($_REQUEST["Level"]) && $_REQUEST["Level"] != "" && isset($_REQUEST["territoryLevel"]) && $_REQUEST["territoryLevel"] != "") {
					if($_REQUEST["Level"] == '1' && $value['TERRITORY'] == 'NOT CALLED'){
						continue;
					}
					else if($_REQUEST["Level"] == '2' && $value['TERRITORY'] != 'NOT CALLED'){
						continue;
					}
				}
				$temp 				= array();
				$temp['SNO'] 		= $value['SNO'];
				$temp['PLANDESC'] 	= $value['PLANDESC'];
				$temp['SALES'] 		= $value['SALES'];
				$temp['AVE'] 		= ($value['SNO'] > 0) ? number_format(($value['SALES']/$value['SNO']), 2, '.', '') : 0;
				$temp['MINSALES'] 	= (array_key_exists($value['PLANDESC'], $plandescMinMax)) ? $plandescMinMax[$value['PLANDESC']]['MIN'] : 0;
				$temp['MAXSALES'] 	= (array_key_exists($value['PLANDESC'], $plandescMinMax)) ? $plandescMinMax[$value['PLANDESC']]['MAX'] : 0;
				$arr[] 				= $temp;
			}
		}
        $this->jsonOutput['planoOverviewGrid'] = $arr;
    }

    /**
     * filterByPlano()
     * This Function is used to retrieve list based on selected plano     
     * @access private  
     * @return $this->jsonOutput with all data that should be sent to client app     
     */
    private function filterByPlano() {
        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $addTerritoryColumn = '';
        $addTerritoryGroup = '';
		// For Territory
		/*if (isset($_REQUEST["territoryLevel"]) && $_REQUEST["territoryLevel"] != "") {
			$addTerritoryColumn = ",".$this->settingVars->territoryTable . ".Level".$_REQUEST["territoryLevel"]." AS TERRITORY";
			$addTerritoryGroup = ",TERRITORY";

            $this->measureFields[] = $this->settingVars->territoryTable . ".Level".$_REQUEST["territoryLevel"];
		} else {
			$addTerritoryColumn = '';
			$addTerritoryGroup = '';
		}*/

        $this->settingVars->setCluster();

        $this->measureFields[] = $this->skuNameField;
        $this->measureFields[] = $this->skuNameFieldId;
        //$this->measureFields[] = $this->storeName;
        $this->measureFields[] = $this->msq;
        $this->measureFields[] = $this->settingVars->clusterID;
        
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }

        // [STATIC FIX TO BYPASS THE TERRITORY LEVEL FROM THE QUERY WHERE PART]
		if (isset($_REQUEST["Level"]) && $_REQUEST["Level"] != "" && isset($_REQUEST["territoryLevel"]) && $_REQUEST["territoryLevel"] != "") {
			$reqLevel = $_REQUEST["Level"]; $_REQUEST["Level"] = '';
				$this->queryPart = $this->getAll();
			$_REQUEST["Level"] = $reqLevel;
		}else{
			$this->queryPart = $this->getAll();
		}

        $query = "SELECT ".$this->plandesc."  AS PLANDESC " .
                ", ".$this->skuNameField." as SKU_NAME".
                ", ".$this->skuNameFieldId." as SKU_ID".
                ", SUM(".$this->settingVars->maintable.".qty) as UNIT_SALES ".
                ", COUNT(DISTINCT((CASE WHEN ".$this->settingVars->maintable.".qty > 0 THEN ".$this->settingVars->maintable.".SNO END))) AS STORES " .
				$addTerritoryColumn.
                /*
                    ",TRIM(MAX(" . $this->settingVars->clusterID . ")) AS CLUSTER " .
                    ",MAX(".$this->odate.") AS OPENDATE" .
                    ",SUM(".$this->settingVars->ProjectValue.") AS SALES" .
                    ",SUM((CASE WHEN " . $this->settingVars->DatePeriod . "= '" . filters\timeFilter::$tyDaysRange[0] . "' THEN 1 ELSE 0 END)*" . $this->msq . ") AS MSQ " .
                */
                " FROM ". $this->settingVars->tablename . " ,". $this->settingVars->ranged_items . $this->queryPart . 
                " AND " . $this->settingVars->maintable . ".SNO=" . $this->settingVars->ranged_items . ".SNO" .
                " AND " . $this->settingVars->maintable . ".SIN=" . $this->settingVars->ranged_items . ".skuID" .
                " AND " . $this->settingVars->ranged_items . ".clientID='" . $this->settingVars->clientID."'".
                " AND " . $this->settingVars->ranged_items . ".GID='" . $this->settingVars->GID."'".
                " AND " . $this->settingVars->ranged_items . ".opendate < " . $this->settingVars->ranged_items . ".insertdate" .
                " GROUP BY PLANDESC, SKU_NAME, SKU_ID ".$addTerritoryGroup. " ORDER BY SUM(".$this->settingVars->maintable.".qty) DESC";

        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }

        $planoData = array();
        if(is_array($result) && !empty($result)){
            foreach($result as $key => $data){
				/*if (isset($_REQUEST["Level"]) && $_REQUEST["Level"] != "" && isset($_REQUEST["territoryLevel"]) && $_REQUEST["territoryLevel"] != "") {
					if($_REQUEST["Level"] == '1' && $data['TERRITORY'] == 'NOT CALLED'){
						continue;
					}
					else if($_REQUEST["Level"] == '2' && $data['TERRITORY'] != 'NOT CALLED'){
						continue;
					}
				}*/
                $data['UNIT_SALES']      = (double) $data['UNIT_SALES'];
                $data['STORES']          = (double) $data['STORES'];
                $data['UNITS_PER_STORE'] = ($data['STORES']!=0) ? ($data['UNIT_SALES'] / $data['STORES']) : 0;
                $planoData[] = $data;
            }
        }

        $this->jsonOutput['planoStores'] = $planoData;
    }

    /**
     * crossStoreAnalysisGrid()
     * It will prepare data for SKU trails and driving in selected stores
     * 
     * @param Store fields name value and other filters
     *
     * @return Void
     */
    /*private function crossStoreAnalysisGrid()
    {
        $primaryStoreID         = $_REQUEST['primaryStore'];
        $secondaryStoreID       = $_REQUEST['compareStore'];
        $crossStoreAnalysisGrid = array();
        $totalStoreSalesSum     = array();
        
        if (!empty($primaryStoreID) && !empty($secondaryStoreID)) {
            $this->settingVars->tableUsedForQuery = $this->measureFields = array();
            $this->measureFields[] = $this->storeID;
            $this->measureFields[] = $this->skuID;
            $this->measureFields[] = $this->skuName;
            $this->settingVars->useRequiredTablesOnly = true;
            if (is_array($this->measureFields) && !empty($this->measureFields)) {
                $this->prepareTablesUsedForQuery($this->measureFields);
            }

            $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll
            $query = "SELECT ".$this->skuID." AS SKUID " .
                    ",TRIM(".$this->skuName.") AS SKU " .                       
                    ",SUM(CASE WHEN ".$this->storeID." = ".$primaryStoreID." THEN ".$this->settingVars->ProjectValue." ELSE 0 END) AS SALES_0" .
                    ",SUM(CASE WHEN ".$this->storeID." = ".$secondaryStoreID." THEN ".$this->settingVars->ProjectValue." ELSE 0 END) AS SALES_1" .
                    " FROM " . $this->settingVars->tablename . " ".$this->queryPart ." AND " .$this->storeID." IN ('".$primaryStoreID."','".$secondaryStoreID."') ".
                    "GROUP BY SKUID,SKU HAVING SALES_0 > 0 AND SALES_1 > 0 ORDER BY SKUID DESC";

            $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
            if ($redisOutput === false) {
                $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
                $this->redisCache->setDataForHash($result);
            } else {
                $result = $redisOutput;
            }            
            
            $crossStoreAnalysisGrid = array();
            if (is_array($result) && !empty($result)) {

                $totalStoreSalesSum[0] = array_sum(array_column($result,'SALES_0'));
                $totalStoreSalesSum[1] = array_sum(array_column($result,'SALES_1'));

                $compareStores = array(
                    $primaryStoreID,
                    $secondaryStoreID
                );
                foreach ($compareStores as $storeIndex => $storeID) {
                    $result = \utils\SortUtility::sort2DArray($result, 'SALES_' . $storeIndex, \utils\SortTypes::$SORT_DESCENDING);
                    foreach ($result as $key => $value) {
                        $crossStoreAnalysisGrid[$value['SKUID']]['SKUID']               = $value['SKUID'];
                        $crossStoreAnalysisGrid[$value['SKUID']]['SKU']                 = utf8_encode($value['SKU']);
                        $crossStoreAnalysisGrid[$value['SKUID']]['RANK_'.$storeIndex]   = $key+1;
                        $crossStoreAnalysisGrid[$value['SKUID']]['SALES_'.$storeIndex]  = (isset($value['SALES_'.$storeIndex])) ? (float)$value['SALES_'.$storeIndex] : 0;
                        $crossStoreAnalysisGrid[$value['SKUID']]['SHARE_'.$storeIndex] = (isset($totalStoreSalesSum[$storeIndex])) && $totalStoreSalesSum[$storeIndex] > 0 ? (float) (($crossStoreAnalysisGrid[$value['SKUID']]['SALES_'.$storeIndex]/$totalStoreSalesSum[$storeIndex])*100) : 0;
                    }
                }
            } // end if
        }
        $this->jsonOutput['crossStoreAnalysisGrid'] = array_values($crossStoreAnalysisGrid);        
    }*/

    /*****
    * COLLECTS TABLE JOINING STRING FROM PROJECT SETTINGS
    * ALSO APPLYS FILTER CONDITIONS TO THE STRING
    * AND RETURNS $tablejoins_and_filters
    *****/
    public function getAll(){

        $tablejoins_and_filters = "";
        $extraFields = array();

        if ($_REQUEST["sku"] != '' && $_REQUEST['sku'] != 'undefined') {
            $extraFields[] = $this->skuNameFieldId;
            $extraFields[] = $this->skuNameField;
            $tablejoins_and_filters .= ' AND ('.$this->skuNameFieldId.' LIKE "%'.$_REQUEST["sku"].'%" OR '.$this->skuNameField.' LIKE "%'.$_REQUEST["sku"].'%") ';
        }

        if (isset($_REQUEST['SNO']) && !empty($_REQUEST['SNO'])){
            $extraFields[] = $this->storeID;
            $tablejoins_and_filters .= " AND ".$this->storeID." = ".$_REQUEST['SNO'];
        }

        if (isset($_REQUEST['SKUID']) && !empty($_REQUEST['SKUID'])){
            $extraFields[] = $this->skuNameFieldId;
            $tablejoins_and_filters .= " AND ".$this->skuNameFieldId." = '".$_REQUEST['SKUID']."'";
        }

        $this->prepareTablesUsedForQuery($extraFields);
        $tablejoins_and_filters1    = parent::getAll();
        $tablejoins_and_filters1    .= $tablejoins_and_filters;

        return $tablejoins_and_filters1;
    }

    public function checkConfiguration(){

        if(!isset($this->settingVars->ranged_items) || empty($this->settingVars->ranged_items))
            $this->configurationFailureMessage("Relay Plus TV configuration not found.");

        $configurationCheck = new config\ConfigurationCheck($this->settingVars, $this->queryVars);
        $configurationCheck->checkClusterConfiguration();

        return ;
    }

    public function buildPageArray() {
        $fetchConfig = false;
        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
            $fetchConfig = true;
            $this->jsonOutput['pageConfig'] = array(
                'enabledFilters' => $this->getPageConfiguration('filter_settings', $this->settingVars->pageID)
            );
        }
        
        $skuFieldPart = explode("#", $this->skuField);
        $skuField = strtoupper($this->dbColumnsArray[$skuFieldPart[0]]);
        $skuField = (count($skuFieldPart) > 1) ? strtoupper($skuField . "_" . $this->dbColumnsArray[$skuFieldPart[1]]) : $skuField;
        
        $this->skuNameField = $this->settingVars->dataArray[$skuField]['NAME'];
        $this->skuNameFieldId = isset($this->settingVars->dataArray[$skuField]['ID']) ? $this->settingVars->dataArray[$skuField]['ID'] : $this->settingVars->dataArray[$skuField]['NAME'];

        $this->columnNames = array();
        $this->columnNames["SKU_NAME"] = $this->displayCsvNameArray[$skuField];
        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
            $this->jsonOutput["FIELD_NAMES"] = $this->columnNames;
        }

        $this->storeID  = $this->settingVars->maintable.".SNO";
        $this->storeName= $this->settingVars->storetable.'.SNAME';
        $this->plandesc = $this->settingVars->dataArray['F6']['NAME'];
        $this->odate    = $this->settingVars->dataArray['F21']['NAME'];
        $this->idate    = $this->settingVars->dataArray['F22']['NAME'];
        $this->msq      = $this->settingVars->dataArray['F14']['NAME'];
        $this->skuID    = key_exists('ID', $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['SKU_FIELD']]) ? $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['SKU_FIELD']]['ID'] : $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['SKU_FIELD']]['NAME'];
        $this->skuName  = $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['SKU_FIELD']]['NAME'];
        return;
    }

    public function buildDataArray($fields) {
        if (empty($fields))
            return false;

        $configurationCheck = new config\ConfigurationCheck($this->settingVars, $this->queryVars);
        $configurationCheck->buildDataArray($fields);
        $this->dbColumnsArray = $configurationCheck->dbColumnsArray;
        $this->displayCsvNameArray = $configurationCheck->displayCsvNameArray;
        $this->ohq      = $this->settingVars->dataArray['F12']['NAME'];
        $this->gsq      = $this->settingVars->dataArray['F9']['NAME'];
        $this->ohaq     = $this->settingVars->dataArray['F10']['NAME'];
        $this->baq      = $this->settingVars->dataArray['F11']['NAME'];

        return;
    }
}
?>