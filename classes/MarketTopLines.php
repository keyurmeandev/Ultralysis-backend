<?php

namespace classes;

use datahelper;
use projectsettings;
use filters;
use db;
use config;

class MarketTopLines extends config\UlConfig {

    public function go($settingVars) {
    	unset($_REQUEST["FSG"]);
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES
        $this->redisCache = new \utils\RedisCache($this->queryVars);
        $this->queryPart = $this->getAll(); //" AND CONTROL_FLAG IN (2,0) ";  //PREPARE TABLE JOIN STRING USING this class getAll

        $this->settingVars->pageName = (empty($this->settingVars->pageName) && !empty($this->settingVars->pageID)) ? $this->settingVars->pageID.'_MarketTopLinesPage' : $this->settingVars->pageName;

        if ($this->settingVars->isDynamicPage) {
        	
            $this->buildPageArray();

        }else{
        	$this->configurationFailureMessage();
        }

        $action = $_REQUEST["action"];
        switch ($action) {
            case "getData" : {
                    $this->getMarketGridData();
                    break;
                }
        }

        return $this->jsonOutput;
    }

    private function getMarketGridData() {

		$marketGridData = $this->settingVars->tableUsedForQuery = $measureSelectionArr = $this->measureFields = array();
        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $measureSelectRes = $this->prepareMeasureSelectPart();
        $this->measureFields = array_merge($measureSelectRes['measureFields'],[$this->storeIDField,$this->storeNameField]);
        
        $this->prepareTablesUsedForQuery($this->measureFields);
        $this->settingVars->useRequiredTablesOnly = true;
        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll

        $measureSelectionArr = $measureSelectRes['measureSelectionArr'];
        /*$havingTYValue = $measureSelectRes['havingTYValue']; $havingLYValue = $measureSelectRes['havingLYValue'];*/

        if($this->storeIDField == $this->storeNameField){
            $fldNames = $this->storeNameField.' AS ACCOUNT';
            $flgGroup = 'ACCOUNT';
        }else{
            $fldNames = $this->storeIDField.' AS ID,'.$this->storeNameField.' AS ACCOUNT';
            $flgGroup = 'ID,ACCOUNT';
        }

        $query = "SELECT ".$fldNames.", " .implode(",", $measureSelectionArr).
                " FROM " . $this->settingVars->tablename .' '.trim($this->queryPart).
                " AND (" . filters\timeFilter::$tyWeekRange . " OR " . filters\timeFilter::$lyWeekRange . ") " .
                "GROUP BY ".$flgGroup;

        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }

        /* SetUp ordering as per the ly of first measeure */
        if(isset($measureSelectionArr[0]) && !empty($measureSelectionArr[0])){
            $ordStr = explode(' AS ', $measureSelectionArr[0]);
            if(isset($ordStr[1]) && !empty($ordStr[1]))
                $result = \utils\SortUtility::sort2DArray($result, trim($ordStr[1]), \utils\SortTypes::$SORT_DESCENDING);
        }

        if(is_array($result) && !empty($result))
		{
			foreach ($result as $key => $row) {
				$temp = array();
                if($this->storeIDField == $this->storeNameField){
                    $temp['MARKET']     = $row['ACCOUNT'];
                    $temp['marketID']   = $row['ACCOUNT'];
                }else{
                    $temp['MARKET']     = $row['ACCOUNT'];
                    $temp['marketID']   = $row['ID'];
                }

				if(is_array($this->settingVars->pageArray["MEASURE_SELECTION_LIST"]) && !empty($this->settingVars->pageArray["MEASURE_SELECTION_LIST"])){
		            foreach ($this->settingVars->pageArray["MEASURE_SELECTION_LIST"] as $key => $measureVal) {
		                $measureKey = 'M' . $measureVal['measureID'];
		                $measure = $this->settingVars->measureArray[$measureKey];
		                
		                if (!empty(filters\timeFilter::$tyWeekRange)) {
		                    $measureTYValue = "TY" . $measure['ALIASE'];
		                }

		                if (!empty(filters\timeFilter::$lyWeekRange)) {
		                    $measureLYValue = "LY" . $measure['ALIASE'];
		                }
		                
		        		$temp[$measureTYValue] = (float)$row[$measureTYValue];
						$temp[$measureLYValue] = (float)$row[$measureLYValue];        

						$temp[$measure['ALIASE']."_Var"] = ($row[$measureLYValue] != 0 ) ? ((($row[$measureTYValue]-$row[$measureLYValue])/$row[$measureLYValue])*100) : 0 ;
		            }
		        }
				$marketGridData[] = $temp;
			}
		}
        $this->jsonOutput['marketGridData'] = $marketGridData;
    }

    public function buildPageArray() {

        $fetchConfig = false;
        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
            $fetchConfig = true;
            $this->jsonOutput['pageConfig'] = array(
                'enabledFilters' => $this->getPageConfiguration('filter_settings', $this->settingVars->pageID)
            );
        }

        if ($this->settingVars->hasGlobalFilter) {
            $globalFilterField = $this->settingVars->globalFilterFieldDataArrayKey;

            $this->storeIDField = (isset($this->settingVars->dataArray[$globalFilterField]) && isset($this->settingVars->dataArray[$globalFilterField]['ID'])) ? $this->settingVars->dataArray[$globalFilterField]['ID'] : $this->settingVars->dataArray[$globalFilterField]['NAME'];
            $globalFilterFieldIDAlias = (isset($this->settingVars->dataArray[$globalFilterField]) && isset($this->settingVars->dataArray[$globalFilterField]['ID'])) ? $this->settingVars->dataArray[$globalFilterField]['ID_ALIASE'] : $this->settingVars->dataArray[$globalFilterField]['NAME_ALIASE'];
            $this->storeNameField = $this->settingVars->dataArray[$globalFilterField]['NAME'];
            $globalFilterFieldNameAlias = $this->settingVars->dataArray[$globalFilterField]['NAME_ALIASE'];
        }else{
            $this->configurationFailureMessage("Global filter configuration not found");
        }

        return;
    }

}

?>