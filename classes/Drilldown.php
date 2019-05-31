<?php

namespace classes;

use datahelper;
use projectsettings;
use filters;
use db;
use config;

class Drilldown extends config\UlConfig {

    private $categoryArray = array();
    private $columnConfig;

    public function go($settingVars) {
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES
        $this->redisCache = new \utils\RedisCache($this->queryVars);

        $this->queryPart = $this->getAll(); //" AND CONTROL_FLAG IN (2,0) ";  //PREPARE TABLE JOIN STRING USING this class getAll

        $this->settingVars->pageName = (empty($this->settingVars->pageName) && !empty($this->settingVars->pageID)) ? $this->settingVars->pageID.'_DrillDownPage' : $this->settingVars->pageName;

        if ($this->settingVars->isDynamicPage) {
        	$this->tableField = $this->getPageConfiguration('table_field', $this->settingVars->pageID)[0];
        	$this->accountFields = $this->getPageConfiguration('account_list_field', $this->settingVars->pageID);

        	$this->buildDataArray($this->accountFields);
            $this->buildPageArray();

        }else{
        	$this->configurationFailureMessage();
        }

        $selectField = isset($_REQUEST['FIELD']) ? $_REQUEST['FIELD'] : '';
        $selectFieldPart = explode(".", $selectField);
        $selectField = (count($selectFieldPart) > 1) ? $this->tableField.".".$selectFieldPart[1] : $selectField; 

        $action = $_REQUEST["action"];
        switch ($action) {
            case "selectItem": {
                    $this->CATEGORY_DRIVERS($selectField,1);
                    break;
                }
            case "getData" : {
                    $this->CATEGORY_DRIVERS($selectField);
                    break;
                }
        }
        return $this->jsonOutput;
    }

    private function CATEGORY_DRIVERS($selectField = '', $callSelectItem = 0) {
			$arr = $options = $selectPart = $groupByPart = $totalArr = $this->settingVars->tableUsedForQuery = $this->measureFields = array();
			$this->settingVars->tableUsedForQuery = $this->measureFields = $options = $catIndicatorRows = array();

			$measureSelectRes = $this->prepareMeasureSelectPart();
			$this->measureFields = $measureSelectRes['measureFields'];

            $projectStructureTypePage = "projectstructure\\".$this->settingVars->projectStructureType."_PAGES";
            $structurePageClass = new $projectStructureTypePage();
            $extraWhereClause = $structurePageClass->extraWhereClause($this->settingVars, $this->measureFields);
            
			$measureSelectionArr = $measureSelectRes['measureSelectionArr'];
			$havingTYValue 		 = $measureSelectRes['havingTYValue'];
			$havingLYValue 		 = $measureSelectRes['havingLYValue'];

			/*if(!empty(filters\timeFilter::$tyWeekRange))
				$options['tyLyRange']['CASES_TYEAR'] =  filters\timeFilter::$tyWeekRange;

			if(!empty(filters\timeFilter::$lyWeekRange))
				$options['tyLyRange']['CASES_LYEAR'] =  filters\timeFilter::$lyWeekRange;*/

			if(!empty($selectField)){
				$this->measureFields[] = $selectField;	
				$selectPart[] 		   = $selectField . " as account";
				$groupByPart[] 		   = "account";
			}

			/*if(isset($this->columnConfig) && is_array($this->columnConfig)){
				foreach ($this->columnConfig as $key => $clmnConf) {
					$lblClmn = str_replace('"','_',str_replace("'",'_',str_replace(' ', '', $clmnConf['label'])));
					$selectPart[] = $clmnConf['data']. " AS ".$lblClmn;
					$this->measureFields[] = $clmnConf['data'];
					$groupByPart[] = $lblClmn;

					if($selectField == $clmnConf['data'])
						$selectFieldLbl = $lblClmn;
				}
			}*/

			foreach ($this->accountsName as $key => $data) {
	                $this->measureFields[] = $this->settingVars->dataArray[$data]['NAME'];
	                $selectPart[] 	= $this->settingVars->dataArray[$data]['NAME'] . " AS '" . $this->settingVars->dataArray[$data]['NAME_ALIASE'] . "'";
	                $groupByPart[] 	= "'" . $this->settingVars->dataArray[$data]['NAME_ALIASE'] . "'";
	                $GroupByFieldArr[] 	= $this->settingVars->dataArray[$data]['NAME_ALIASE'];
	        }

			$this->settingVars->useRequiredTablesOnly = true;
	        if (is_array($this->measureFields) && !empty($this->measureFields)) {
	            $this->prepareTablesUsedForQuery($this->measureFields);
	        }

			$this->queryPart = $this->getAll(). " AND (" . filters\timeFilter::$tyWeekRange . " OR " . filters\timeFilter::$lyWeekRange . ") ". $extraWhereClause;
	        /*$measureSelect = config\MeasureConfigure::prepareSelect($this->settingVars, $this->queryVars, array('M'.$_REQUEST['ValueVolume']), $options);
	        $measureSelect = implode(", ", $measureSelect);*/

			/*[START] ADDING EXTRA FIELD BASED ON THE MAINCLASS */
			$ExtraColumnAggregateFields = ''; $ExtraColumnAllArr = $ExtraColumnArr = array();
	        if(isset($this->settingVars->pageArray["DrillDown"]) && isset($this->settingVars->pageArray["DrillDown"]["ExtraColumns"]) && is_array($this->settingVars->pageArray["DrillDown"]["ExtraColumns"]) && count($this->settingVars->pageArray["DrillDown"]["ExtraColumns"])>0){
				$ExtraColumnAllArr = $this->settingVars->pageArray["DrillDown"]["ExtraColumns"];
	        	if ($ExtraColumnAllArr) {
	        		foreach ($ExtraColumnAllArr as $key => $value) 
                    {
                        if($key != "AVE_AC_DIST_CHG")
                        {
                            if(isset($value['TIME_RANGE']) && $value['TIME_RANGE'] != "")
                            {
                                if($value['TIME_RANGE'] == "TY")
                                    $ExtraColumnAggregateFields .= ', MAX((CASE WHEN '.filters\timeFilter::$tyWeekRange.' THEN '.$value['FIELDS'].' ELSE 0 END)) AS '. str_replace(" ", "_", $value['TITLE']);
                                    
                                if($value['TIME_RANGE'] == "LY")
                                    $ExtraColumnAggregateFields .= ', MAX((CASE WHEN '.filters\timeFilter::$lyWeekRange.' THEN '.$value['FIELDS'].' ELSE 0 END)) AS '. str_replace(" ", "_", $value['TITLE']);
                                    
                                $ExtraColumnArr[] = $key;
                            }
                            else
                            {
                                $ExtraColumnAggregateFields .= ','.$value['FIELDS'];
                                $ExtraColumnArr[] = $key;
                            }
                        }
                    }
	        		$GroupByFieldArr = array_merge($GroupByFieldArr,$ExtraColumnArr);
                    
                    $isChange = array_key_exists("AVE_AC_DIST_CHG", $ExtraColumnAllArr);
                    
	        	}
	        }
	        /*[END] ADDING EXTRA FIELD BASED ON THE MAINCLASS */

			$query = "SELECT "
					. implode(",", $selectPart) . " " .
					", ".implode(",", $measureSelectionArr)." ".
					$ExtraColumnAggregateFields." ".
					"FROM " . $this->settingVars->tablename . $this->queryPart . " " .
					" GROUP BY " . implode(",", $groupByPart);
					//" HAVING CASES_TYEAR > 0 OR CASES_LYEAR > 0 ORDER BY CASES_TYEAR DESC, CASES_LYEAR DESC";

			$redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
	        if ($redisOutput === false) {
	            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
	            $this->redisCache->setDataForHash($result);
	        } else {
	            $result = $redisOutput;
	        }

	        $requiredGridFields = array_merge($GroupByFieldArr,['account',$havingTYValue, $havingLYValue]);
			$result = $this->redisCache->getRequiredData($result, $requiredGridFields, $havingTYValue, $havingTYValue, $havingLYValue);
			$result = \utils\SortUtility::sort2DArray($result, $havingTYValue, \utils\SortTypes::$SORT_DESCENDING);
			
			/* When request comes from the grid click */
	        if(isset($_REQUEST['selectedField']) && !empty($_REQUEST['selectedField']) && isset($_REQUEST['selectedFieldVal']) && !empty($_REQUEST['selectedFieldVal'])) {
	        	$gridSelectedField 	  = $_REQUEST['selectedField'];
	        	$gridSelectedFieldVal = $_REQUEST['selectedFieldVal'];
	        	$isAccountChecking 	  = !empty($gridSelectedFieldVal) && !empty($selectField) ? 1 : 0;
	        }

			if(is_array($result) && !empty($result)) {

				$category_drivers = $category_indicators = [];
				foreach ($result as $key => $row) {
					if($isAccountChecking == 1 && $row['account'] != $gridSelectedFieldVal)
						continue;

					$dyncKey = ''; $tmpctdarr = [];
					/*Generating the unique key*/
					for($i=0;$i<count($GroupByFieldArr);$i++){
						$dyncKey.=str_replace(' ','_',str_replace('"','_',str_replace("'", "_", $row[$GroupByFieldArr[$i]])));
					}

					/*Mapping of all the dynnamic columns */
					for($i=0;$i<count($GroupByFieldArr);$i++){
						if($GroupByFieldArr[$i] != $havingTYValue && $GroupByFieldArr[$i] != $havingLYValue)
							$category_drivers[$dyncKey][$GroupByFieldArr[$i]] = $row[$GroupByFieldArr[$i]];
					}
					$category_drivers[$dyncKey]['CASES_TYEAR']+= $row[$havingTYValue];
					$category_drivers[$dyncKey]['CASES_LYEAR']+= $row[$havingLYValue];
					if (!empty($selectField)) {
						$catIndicatorRows[$row['account']]['account'] =  $row['account'];
						$catIndicatorRows[$row['account']]['CASES_TYEAR']+= $row[$havingTYValue];
						$catIndicatorRows[$row['account']]['CASES_LYEAR']+= $row[$havingLYValue];
					}
                    
                    if($isChange)
                    {
                        $category_drivers[$dyncKey]['AVE_AC_DIST_CHG'] = $row['AVE_AC_DIST'] - $row['AVE_AC_DIST_LY'];
                    }
				}

				if (!empty($category_drivers)) {
					/*[START] CALCULATING THE ALL COUNT*/
						$allCasesTyear = array_sum(array_column($category_drivers, 'CASES_TYEAR'));
						$allCasesLyear = array_sum(array_column($category_drivers, 'CASES_LYEAR'));
						$totalArr['CASES_TYEAR'] = number_format($allCasesTyear, 0, '', '');
						$totalArr['CASES_LYEAR'] = number_format($allCasesLyear, 0, '', '');
						$totalArr['CASES_VAR']   = number_format(($allCasesTyear - $allCasesLyear), 1, '.', '');
						if ($allCasesLyear > 0)
							$totalArr['VAR_CASES'] = number_format((($allCasesTyear - $allCasesLyear) / $allCasesLyear) * 100, 1, '.', '');
						else
							$totalArr['VAR_CASES'] = 0;
						$totalArr['ALLIGNMENT'] = 0;
					/*[END] CALCULATING THE ALL COUNT*/

					foreach ($category_drivers as $key => $row) {
						if ($row['CASES_LYEAR'] > 0)
							$row['VAR'] = number_format(($row['CASES_TYEAR'] - $row['CASES_LYEAR']), 1, '.', '');
						else
							$row['VAR'] = 0;

						if ($row['CASES_LYEAR'] > 0)
							$row['VAR_CASES'] = number_format((($row['CASES_TYEAR'] - $row['CASES_LYEAR']) / $row['CASES_LYEAR']) * 100, 1, '.', '');
						else
							$row['VAR_CASES'] = 0;

						if ($totalArr['CASES_TYEAR'] > 0)
							$row['CASES_SHARE'] = number_format(($row['CASES_TYEAR'] / $totalArr['CASES_TYEAR']) * 100, 1, '.', '');
						else
							$row['CASES_SHARE'] = 0;

						if ($totalArr['CASES_LYEAR'] > 0)
						    $row['CASES_SHARE_LY'] = number_format(($row['CASES_LYEAR'] / $totalArr['CASES_LYEAR']) * 100, 1, '.', '');
						else
						    $row['CASES_SHARE_LY'] = 0;

						$row['CASES_TYEAR'] = number_format($row['CASES_TYEAR'], 0, '', ',');
						$row['CASES_LYEAR'] = number_format($row['CASES_LYEAR'], 0, '', ',');
						$arr[] = $row;
					}
				}

				if (!empty($selectField) && isset($catIndicatorRows) && is_array($catIndicatorRows)) {

					/*[START] CALCULATING THE ALL COUNT*/
						//print_r($catIndicatorRows); exit;
						$allCasesTyear = array_sum(array_column($catIndicatorRows, 'CASES_TYEAR'));
						$allCasesLyear = array_sum(array_column($catIndicatorRows, 'CASES_LYEAR'));

						$category_indicators[0]['ACCOUNT'] 	     = 'All';
						$category_indicators[0]['CASES_SHARE']   = '100';
						$category_indicators[0]['CASES_TYEAR']   = number_format($allCasesTyear, 0, '', '');
						$category_indicators[0]['CASES_LYEAR']   = number_format($allCasesLyear, 0, '', '');
						$category_indicators[0]['CASES_VAR']   	 = number_format(($allCasesTyear - $allCasesLyear), 1, '.', '');
						if ($allCasesLyear > 0)
							$category_indicators[0]['CASES_VAR_PER'] = number_format((($allCasesTyear - $allCasesLyear) / $allCasesLyear) * 100, 1, '.', '');
						else
							$category_indicators[0]['CASES_VAR_PER'] = 0;
					/*[END] CALCULATING THE ALL COUNT*/
					
					foreach ($catIndicatorRows as $key => $row) {
						/*[START] MAKING THE CATEGORY INDICATOR GRID ARRAY*/
							$temp = array();
							$temp['ACCOUNT'] 		= isset($row['account']) ? $row['account'] : '';
							$temp['CASES_TYEAR'] 	= number_format($row['CASES_TYEAR'], 0, '', '');
							$temp['CASES_LYEAR'] 	= number_format($row['CASES_LYEAR'], 0, '', '');
							$temp['CASES_VAR']   	= number_format(($row['CASES_TYEAR'] - $row['CASES_LYEAR']), 1, '.', '');

							if ($row['CASES_LYEAR'] > 0)
								$temp['CASES_VAR_PER'] = number_format((($row['CASES_TYEAR'] - $row['CASES_LYEAR']) / $row['CASES_LYEAR']) * 100, 1, '.', '');
							else
								$temp['CASES_VAR_PER'] = 0;

							if ($category_indicators[0]['CASES_TYEAR'] > 0)
								$temp['CASES_SHARE'] = number_format(($row['CASES_TYEAR'] / $category_indicators[0]['CASES_TYEAR']) * 100, 1, '.', '');
							else
								$temp['CASES_SHARE'] = 0;
							
							// USED IN CHARTS
							$temp['CASES_SHARE_TY'] = $temp['CASES_SHARE'];
							if ($category_indicators[0]['CASES_LYEAR'] > 0)
							    $temp['CASES_SHARE_LY'] = number_format(($row['CASES_LYEAR'] / $category_indicators[0]['CASES_LYEAR']) * 100, 1, '.', '');
							else
							    $temp['CASES_SHARE_LY'] = 0;
							
							$category_indicators[]	= $temp;
						/*[END] MAKING THE CATEGORY INDICATOR GRID ARRAY*/
					}
				}
			}

        $this->jsonOutput['CATEGORY_DRIVERS'] = $arr;
		if (empty($selectField) || $callSelectItem == 1) {
				//$this->jsonOutput['CATEGORY_INDICATORS'] = array();
				//$this->jsonOutput['SUBCAT_BARCAHRT'] 	 = array();
			return;
		}else{
			$category_indicators_finalArray = \utils\SortUtility::sort2DArray($category_indicators, 'CASES_TYEAR', \utils\SortTypes::$SORT_DESCENDING);
			$this->jsonOutput['CATEGORY_INDICATORS'] = $category_indicators_finalArray;
			$series = [];
			if(is_array($category_indicators) && !empty($category_indicators)){
				foreach ($category_indicators as $key => $value) {
					$temp = array();
					if($value['ACCOUNT'] != 'All'){
					   $temp['name'] 	  = $value['ACCOUNT'];
					   $temp['data'][] 	  = (int)$value['CASES_TYEAR'];
					   $temp['data'][] 	  = (int)$value['CASES_LYEAR'];
			           $temp['share'][]   = (double)$value['CASES_SHARE_TY'];
			           $temp['share'][]   = (double)$value['CASES_SHARE_LY'];
					   $series[] 		  = $temp;
					}
				}
			}
			$this->jsonOutput['SUBCAT_BARCAHRT'] = $series;
		}
    }

    public function makeFieldsToAccounts($srcArray) {
        $tempArr = array();
        foreach ($srcArray as $value) {
            $value = explode("#", $value);
            if (count($value) > 1)
                $tempArr[] = strtoupper($this->dbColumnsArray[$value[0]] . "_" . $this->dbColumnsArray[$value[1]]);
            else
                $tempArr[] = strtoupper($this->dbColumnsArray[$value[0]]);
        }
        return $tempArr;
    }

    public function buildPageArray() {
        $fetchConfig = false;
        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
            $fetchConfig = true;
            $this->jsonOutput['pageConfig'] = array(
                'enabledFilters' => $this->getPageConfiguration('filter_settings', $this->settingVars->pageID)
            );
        }

        $this->accountsName = $this->makeFieldsToAccounts($this->accountFields);
        $this->columnNames = array();
        foreach ($this->accountsName as $key => $data) {
            $nameAlias = $this->settingVars->dataArray[$data]["NAME_ALIASE"];
            $this->columnNames[$nameAlias] = $this->displayCsvNameArray[$this->accountFields[$key]];
        }

        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
        	$this->jsonOutput["FIELD_NAMES"] = $this->columnNames;

			if(isset($this->settingVars->pageArray["DrillDown"]) && isset($this->settingVars->pageArray["DrillDown"]["ExtraColumns"]) && is_array($this->settingVars->pageArray["DrillDown"]["ExtraColumns"]) && count($this->settingVars->pageArray["DrillDown"]["ExtraColumns"])>0){
	        	$ExtraColumnArr = $this->settingVars->pageArray["DrillDown"]["ExtraColumns"];
	        	if ($ExtraColumnArr) {
	        		foreach ($ExtraColumnArr as $key => $value) {
	        			unset($value['FIELDS']);
	        			$this->jsonOutput["EXTRA_FIELD_NAMES"][$key] = $value;
	        		}
	        	}
	        }

			if(isset($this->settingVars->pageArray["DrillDown"]) && isset($this->settingVars->pageArray["DrillDown"]["isShareChartActive"])){
	        	$this->jsonOutput["isShareChartActive"] = $this->settingVars->pageArray["DrillDown"]["isShareChartActive"];
	        }
    	}
        return;
    }

    public function buildDataArray($fields) {
        if (empty($fields))
            return false;

        $configurationCheck = new config\ConfigurationCheck($this->settingVars, $this->queryVars);
        $this->getFieldSettings($configurationCheck,$this->tableField);
    	
    	$query  = "SELECT db_column, csv_column,db_table FROM ".$this->settingVars->clientconfigtable.
        " WHERE cid=".$this->settingVars->aid." AND db_table='".$this->tableField."' ".
        " AND database_name = '".$this->settingVars->databaseName."' AND db_column IN ('".implode("','", $this->dbColumns)."') ORDER BY rank ASC";

		$redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
		if ($redisOutput === false) {
		    $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->projectManagerLinkid, db\ResultTypes::$TYPE_OBJECT);
		    $this->redisCache->setDataForHash($result);
		} else {
		    $result = $redisOutput;
		}
		$columnConfig = array();

        if (is_array($result) && !empty($result)) {
            foreach ($this->dbColumns as $dbColumn) {
            	$searchKey = array_search($dbColumn, array_column($result, 'db_column'));
            	if($searchKey !== false){
            		$columnConfig[] = array('data'=> strtoupper($result[$searchKey]['db_table'].".".$result[$searchKey]['db_column']), 'label' => $result[$searchKey]['csv_column'] );
	                if(!in_array($result[$searchKey]['db_table'].".".$result[$searchKey]['csv_column'], $fields))
	            		$fields[] = $result[$searchKey]['db_table'].".".$result[$searchKey]['csv_column'];
            	}
            }
        }

        $this->columnConfig = $columnConfig;
        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
        	$this->jsonOutput['customFieldList'] = $columnConfig;
		}

        $configurationCheck->buildDataArray($fields);
		$this->dbColumnsArray = $configurationCheck->dbColumnsArray;
        $this->displayCsvNameArray = $configurationCheck->displayCsvNameArray;
        return;
    }

    private function getFieldSettings($configurationCheck, $table){
        $this->dbColumns = array();

        if($table == 'store')
            $table = 'market';

        $hasSettingName = 'has_'.$table.'_filter';
        $filterSettingName = $table.'_settings';

        // Query to fetch settings 
        $result = (isset($configurationCheck->settings[$hasSettingName]) && !empty($configurationCheck->settings[$hasSettingName])) ? $configurationCheck->settings[$hasSettingName] : '';
        if (!empty($result)) {
            $filterSettingStr = (isset($configurationCheck->settings[$filterSettingName]) && !empty($configurationCheck->settings[$filterSettingName])) ? $configurationCheck->settings[$filterSettingName] : '';
            if (!empty($filterSettingStr)) {
                $settings = explode("|", $filterSettingStr);
                // Explode with # because we are getting some value with # ie (PNAME#PIN) And such column name combination not match with db_column. 
                foreach($settings as $key => $data)
                {
                    $originalCol = explode("#", $data);
                    if(is_array($originalCol) && !empty($originalCol))
                        $this->dbColumns[] = $originalCol[0];
                    //if(is_array($originalCol) && count($originalCol) > 1)
                        //$this->dbColumns[] = $originalCol[1];                        
                }
            }
            else{
                $this->configErrors[] = ucfirst($table)." filter configuration not found.";
            }
        }else{
            $this->configErrors[] = ucfirst($table)." filter configuration not found.";
        }
        if (!empty($this->configErrors)) {
            $response = array("configuration" => array("status" => "fail", "messages" => $this->configErrors));
            echo json_encode($response);
            exit();
        }
    }
}
?>