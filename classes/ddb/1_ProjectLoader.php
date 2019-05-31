<?php
namespace classes\ddb;

use datahelper;
use projectsettings;
use SimpleXMLElement;
use filters;
use db;
use config;
use utils;
use lib;

class ProjectLoader extends config\UlConfig{
    public $userList;
    public $breakdownData = array();

    /*****
    * Default gateway function, should be similar in all DATA CLASS
    * arguments:
    *  $settingVars [project settingsGateway variables]
    * @return $jsonOutput with all data that should be sent to client app
    *****/
    public function go($settingVars){ 
        $settingVars->pageName = 'DDBProjectLoader';
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES
        $this->queryPart = $this->getAll();

        if (isset($_REQUEST['action']) && $_REQUEST['action'] == 'fetchSavedFilter') {
            //$this->fetchSavedFilterList();
        } else {
            $this->fetchMeasureOptions(); 
            //$this->fetchSavedFilterList(); 
            
            $this->fetchTimeSelectionData(); //collecting time selection data
            $this->fetchProductAndMarketSelectionData(); //collecting product and market filter data
            $this->fetchProductAndMarketTabsSettings();
        }
        $this->jsonOutput['projectID'] = utils\Encryption::encode($this->queryVars->projectID);
        $this->jsonOutput['projectName'] = $this->settingVars->pageArray['PROJECT_NAME'];
        $this->jsonOutput['filterPages'] = $this->settingVars->filterPages;
		$this->jsonOutput['timeSelectionUnit'] = $this->settingVars->timeSelectionUnit;
		$this->jsonOutput['outputDateOptions'] = $this->settingVars->outputDateOptions;
        return $this->jsonOutput;
    }

    private function fetchProductAndMarketTabsSettings() {
	
        foreach($this->settingVars->filterPages as $mainKey => $filterPage)
        {
			if($filterPage['isDynamic'])
			{

				foreach($filterPage['tabsConfiguration'] as $key => $tabsConfiguration)
				{
					$xmlTagAccountName  = $this->settingVars->dataArray[$tabsConfiguration['data']]['NAME'];
					$xmlTag             = preg_match('/(,|\)|\()/',$xmlTagAccountName)==1 ? $this->settingVars->dataArray[$tabsConfiguration['data']]['NAME_ALIASE'] : strtoupper($xmlTagAccountName);

					if (isset($this->settingVars->dataArray[$tabsConfiguration['data']]['use_alias_as_tag']))
						$xmlTag = ($this->settingVars->dataArray[$tabsConfiguration['data']]['use_alias_as_tag']) ? strtoupper($this->settingVars->dataArray[$tabsConfiguration['data']]['NAME_ALIASE']) : $xmlTag;

					if (is_array($this->jsonOutput['filters'][$xmlTag]) && !empty($this->jsonOutput['filters'][$xmlTag])) {
						if(isset($_REQUEST['commonFilterPage']) && $_REQUEST['commonFilterPage'] == true)
						{
							$this->settingVars->filterPages[$mainKey]['tabsConfiguration'][$key]['dataList'] = $this->jsonOutput['filters']['commonFilter'][$xmlTag]['dataList'];
							$this->settingVars->filterPages[$mainKey]['tabsConfiguration'][$key]['selectedDataList'] = (isset($this->jsonOutput['filters']['commonFilter'][$xmlTag]['selectedDataList'])) ? $this->jsonOutput['filters']['commonFilter'][$xmlTag]['selectedDataList'] : array();
						}
						else
							$this->settingVars->filterPages[$mainKey]['tabsConfiguration'][$key]['dataList'] = $this->jsonOutput['filters'][$xmlTag];
					}

                    // to check the flag if breakdown exists or not
                    $this->settingVars->filterPages[$mainKey]['tabsConfiguration'][$key]['breakdown'] = $this->getBreakdownData($tabsConfiguration['data'], $this->settingVars->filterPagesReplication[$mainKey]['config']);
				}

                // for breakdown list
                $this->settingVars->filterPages[$mainKey]['breakDowns'] = $this->getBreakdownDataList($filterPage['tabsConfiguration'], $this->settingVars->filterPagesReplication[$mainKey]['config']);
			}
		}
    }


    private function getBreakdownList($tableName){

        $query = "SELECT id as breakdownID, breakdown_name as breakdownName, status FROM ".$this->settingVars->breakdownTable." WHERE accountID=".$this->settingVars->aid." AND projectID = ".$this->settingVars->projectID." AND status = 1 AND db_table='{$tableName}' ";
        $breakdownData = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->projectManagerLinkid, db\ResultTypes::$TYPE_OBJECT);
        
        // Listing Active breakdown Data
        $breakdownList = [];
        foreach ($breakdownData as $key => $value) {
            $breakdownList[$value['breakdownID']] = $value;
        }

        return $breakdownList;
    }


    public function getBreakdownDataList($tabList, $config){

        $tableName = strtolower($config['table_name']);
        $breakdownSettingName = str_replace('settings', 'breakdown_settings', $config['setting_name']); 

        $tabDetails = [];
        foreach($tabList as $tab){
            $column = explode(".", $tab['data']);
            $column = strtolower($column[1]);
            $tabDetails[$column] = $tab;
        }

        // which data are used
        $usedBreakdownData = $this->queryVars->projectConfiguration[$breakdownSettingName];
        $extractedUsedBDData = explode("|",$usedBreakdownData);

        $usedBreakdownArr = [];
        foreach($extractedUsedBDData as $key => $value)
        {
            $extractedDBColAndValue = explode("#", $value);
            if ( !empty($extractedDBColAndValue[1]) ){                    
                $usedBreakdownArr[$extractedDBColAndValue[1]][] = strtolower($extractedDBColAndValue[0]);
            }
        }

        $breakdownList = $this->getBreakdownList($tableName);

        $outputList = [];
        foreach($usedBreakdownArr as $breakdownID=>$columnArr){
            if ( array_key_exists($breakdownID, $breakdownList) ){
                $outputList[$breakdownID]['breakdown'] = $breakdownList[$breakdownID];

                foreach($columnArr as $columnName){
                    $outputList[$breakdownID]['columns'][] = $tabDetails[$columnName];
                }
            }
        }

        return $outputList;
            
    }


    public function getBreakdownData($tableField, $config){

        $extractTableField = explode('.', $tableField);
        // $tableName = strtolower($extractTableField[0]); 
        $dbColumn = strtolower($extractTableField[1]); 

        $tableName = strtolower($config['table_name']);
        $breakdownSettingName = str_replace('settings', 'breakdown_settings', $config['setting_name']);

        // if not exists
        if ( !isset($this->breakdownData[$tableName]) ){

            $breakdownData = $this->queryVars->projectConfiguration[$breakdownSettingName];
            $extractedBDData = explode("|",$breakdownData);

            $breakdownList = $this->getBreakdownList($tableName);

            $breakdownExistsList = [];
            foreach($extractedBDData as $key => $value)
            {
                $extractedDBColAndValue = explode("#", $value);
                if ( !empty($extractedDBColAndValue[1]) ){                    
                    if ( array_key_exists($extractedDBColAndValue[1], $breakdownList) ){
                        $breakdownExistsList[strtolower($extractedDBColAndValue[0])] = $breakdownList[$extractedDBColAndValue[1]];
                    }
                }
            }

            $this->breakdownData[$tableName] = $breakdownExistsList;
        }

        return isset($this->breakdownData[$tableName][$dbColumn]) ? $this->breakdownData[$tableName][$dbColumn] : 0;
            
    }


    
    /*****
    * COLLECTS ALL TIME SELECTION DATA HELPERS AND CONCATE THEM WITH GLOBAL $jsonOutput 
    *****/
    public function fetchTimeSelectionData(){
        $timeSelectionDataCollectors = new datahelper\Time_Selection_DataCollectors($this->settingVars);
        if ($this->settingVars->timeSelectionUnit != 'date')
            filters\timeFilter::getYTD($this->settingVars);//ALTERNATIVE OF getSlice WHEN THE PROJECT IS LOADING FOR FIRST TIME
        
        //COLLECT TIME SELECTION DATA
        if($_REQUEST['includeFutureDates']=='true'){
            switch($this->settingVars->timeSelectionUnit)
            {
                case 'week':
                    $timeSelectionDataCollectors->getAllWeek_with_future_dates($this->jsonOutput);
                    break;
                case 'month':
                    $timeSelectionDataCollectors->getAllMonth_with_future_dates($this->jsonOutput);
                    break;
				case 'date':
                    $timeSelectionDataCollectors->getAllMydate($this->jsonOutput);
                    break;
                default:
                    $timeSelectionDataCollectors->getAllWeek_with_future_dates($this->jsonOutput);
            }
        } else {
			switch($this->settingVars->timeSelectionUnit)
            {
                case 'week':
                    $includeDate = (isset($this->settingVars->includeDateInTimeFilter)) ? $this->settingVars->includeDateInTimeFilter : true;
                    $timeSelectionDataCollectors->getAllWeek($this->jsonOutput, $includeDate);
                    break;
                case 'month':
                    $timeSelectionDataCollectors->getAllMonth($this->jsonOutput);
                    break;
				case 'date':
                    $timeSelectionDataCollectors->getAllMydate($this->jsonOutput);
                    break;
                case 'period':
                    $timeSelectionDataCollectors->getAllPeriod($this->jsonOutput);
                    break;
                default:
                    $timeSelectionDataCollectors->getAllWeek($this->jsonOutput);
            }
		}
        if(isset($this->jsonOutput['gridWeek']))
            $this->jsonOutput['yearWeekList'] = $this->jsonOutput['gridWeek'];
    }
    
    /*****
    * COLLECTS ALL PRODUCT AND MARKET SELECTION DATA HELPERS AND CONCATE THEM WITH GLOBAL $jsonOutput 
    *****/
    public function fetchProductAndMarketSelectionData(){
	    $dataHelpers = array();
        /*if (!empty($this->settingVars->dataHelpers))
            $dataHelpers = explode("-", $this->settingVars->dataHelpers); //COLLECT REQUIRED PRODUCT FILTERS DATA FROM PROJECT SETTING		
        */

        if (isset($this->settingVars->pageArray) && isset($this->settingVars->pageArray[$this->settingVars->pageName]["DH"]) && !empty($this->settingVars->pageArray[$this->settingVars->pageName]["DH"]))
            $dataHelpers = explode("-", $this->settingVars->pageArray[$this->settingVars->pageName]["DH"]); //COLLECT REQUIRED PRODUCT FILTERS DATA FROM PROJECT SETTING

        if (!empty($dataHelpers)) {

            $selectPart = $resultData = $helperTables = $helperLinks = $tagNames = $includeIdInLabels = $groupByPart = array();
            
            foreach ($dataHelpers as $key => $account) {
                if($account != "")
                {
                    //IN RARE CASES WE NEED TO ADD ADITIONAL FIELDS IN GROUP BY CLUAUSE AND ALSO AS LABEL WITH THE ORIGINAL ACCOUNT
                    //E.G: FOR RUBICON LCL - SKU DATA HELPER IS SHOWN AS 'SKU #UPC'
                    //IN ABOVE CASE WE SEND DH VALUE = F1#F2 [ASSUMING F1 AND F2 ARE SKU AND UPC'S INDEX IN DATAARRAY]
                    $combineAccounts = explode("#", $account);
                    //echo "<pre>";
                    //print_r($this->settingVars->dataArray);
                    //print_r($combineAccounts);
                    foreach ($combineAccounts as $accountKey => $singleAccount) {
                        $tempId = key_exists('ID', $this->settingVars->dataArray[$singleAccount]) ? $this->settingVars->dataArray[$singleAccount]['ID'] : "";
                        if ($tempId != "") {
                            $selectPart[$this->settingVars->dataArray[$singleAccount]['TYPE']][] = $tempId . " AS " . str_replace('.','_',$this->settingVars->dataArray[$singleAccount]['ID_ALIASE']);
                            $groupByPart[$this->settingVars->dataArray[$singleAccount]['TYPE']][] = str_replace('.','_',$this->settingVars->dataArray[$singleAccount]['ID_ALIASE']);
                        }
                        
                        $tempName = $this->settingVars->dataArray[$singleAccount]['NAME'];
                        $selectPart[$this->settingVars->dataArray[$singleAccount]['TYPE']][] = $tempName . " AS " . str_replace('.','_',$this->settingVars->dataArray[$singleAccount]['NAME_ALIASE']);
                        $groupByPart[$this->settingVars->dataArray[$singleAccount]['TYPE']][] = str_replace('.','_',$this->settingVars->dataArray[$singleAccount]['NAME_ALIASE']);
                        
                    }
                    
                    $helperTables[$this->settingVars->dataArray[$combineAccounts[0]]['TYPE']] = $this->settingVars->dataArray[$combineAccounts[0]]['tablename'];
                    $helperLinks[$this->settingVars->dataArray[$combineAccounts[0]]['TYPE']] = $this->settingVars->dataArray[$combineAccounts[0]]['link'];
                    
                    //datahelper\Product_And_Market_Filter_DataCollector::collect_Filter_Data($selectPart, $groupByPart, $tagName, $helperTableName, $helperLink, $this->jsonOutput, $includeIdInLabel, $account);
                }
            }
            
            if(is_array($selectPart) && !empty($selectPart)){
                //echo "<pre>";
                //print_r($selectPart);
                foreach ($selectPart as $type => $sPart) {
                    $resultData[$type] = datahelper\Product_And_Market_Filter_DataCollector::collect_Filter_Query_Data($sPart, $groupByPart[$type], $helperTables[$type], $helperLinks[$type]);
                }
            }

            foreach ($dataHelpers as $key => $account) {
                if($account != "")
                {
                    $combineAccounts = explode("#", $account);

                    $tagNameAccountName = $this->settingVars->dataArray[$combineAccounts[0]]['NAME'];

                    //IF 'NAME' FIELD CONTAINS SOME SYMBOLS THAT CAN'T PASS AS A VALID XML TAG , WE USE 'NAME_ALIASE' INSTEAD AS XML TAG
                    //AND FOR THAT PARTICULAR REASON, WE ALWAYS SET 'NAME_ALIASE' SO THAT IT IS A VALID XML TAG
                    $tagName = preg_match('/(,|\)|\()|\./', $tagNameAccountName) == 1 ? $this->settingVars->dataArray[$combineAccounts[0]]['NAME_ALIASE'] : strtoupper($tagNameAccountName);

                    if (isset($this->settingVars->dataArray[$combineAccounts[0]]['use_alias_as_tag']))
                        $tagName = ($this->settingVars->dataArray[$combineAccounts[0]]['use_alias_as_tag']) ? strtoupper($this->settingVars->dataArray[$combineAccounts[0]]['NAME_ALIASE']) : $tagName;

                    $includeIdInLabel = false;
                    if (isset($this->settingVars->dataArray[$combineAccounts[0]]['include_id_in_label']))
                        $includeIdInLabel = ($this->settingVars->dataArray[$combineAccounts[0]]['include_id_in_label']) ? true : false;

                    $tempId = key_exists('ID', $this->settingVars->dataArray[$combineAccounts[0]]) ? $this->settingVars->dataArray[$combineAccounts[0]]['ID'] : "";
                    
                    $nameAliase = str_replace('.','_',$this->settingVars->dataArray[$combineAccounts[0]]['NAME_ALIASE']);
                    $idAliase = isset($this->settingVars->dataArray[$combineAccounts[0]]['ID_ALIASE']) ? str_replace('.','_',$this->settingVars->dataArray[$combineAccounts[0]]['ID_ALIASE']) : "";

                    $type = $this->settingVars->dataArray[$combineAccounts[0]]['TYPE'];

                    datahelper\Product_And_Market_Filter_DataCollector::getFilterData( $nameAliase, $idAliase, $tempId, $resultData[$type], $tagName , $this->jsonOutput, $includeIdInLabel, $account);
                }
            }
        }
    }
    
    /*****
    * ADDS ALL MEASURE FIELDS  TO GLOBAL $jsonOutput 
    *****/
    public function fetchMeasureOptions(){
        $measure_DisplayOptions = [];
        if(isset($this->settingVars->pageArray['MEASURE_SELECTION_LIST'])){
            foreach ($this->settingVars->pageArray['MEASURE_SELECTION_LIST'] as $key => $value) {
                $measure_DisplayOptions[] = ['data' => $value['measureID'], 'label' => $value['measureName'], 'selected' => $value['selected']];
            }
        }
        $this->jsonOutput['measureOptions'] =  $measure_DisplayOptions;
    }
}
?>