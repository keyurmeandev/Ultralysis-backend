<?php
namespace classes\ddb;

use datahelper;
use projectsettings;
use SimpleXMLElement;
use filters;
use db;
use config;

class LoadSavedFilter extends ProjectLoader {

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

        $this->fetchMeasureOptions(); 
		$this->fetchTimeSelectionData(); //collecting time selection data
        //$this->fetchProductAndMarketSelectionData(); //collecting product and market filter data
        
        $configureProject = new config\ConfigureProject($settingVars, $this->queryVars);
        $configureProject->fetch_all_product_and_marketSelection_data();
        $this->jsonOutput['filters'] = $configureProject->jsonOutput['filters'];
        
        $this->fetchFilter(); //collecting product and market filter data
        $this->fetchProductAndMarketTabsSettings();

		$this->jsonOutput['filterPages'] = $this->settingVars->filterPages;	
		// $this->jsonOutput['outputDateOptions'] = $this->settingVars->outputDateOptions;
		$this->jsonOutput['timeSelectionUnit'] = $this->settingVars->timeSelectionUnit;
        $this->jsonOutput['fetchProductAndMarketFilterOnTabClick'] = $this->settingVars->fetchProductAndMarketFilterOnTabClick;
        unset($this->jsonOutput['filters']);
        return $this->jsonOutput;
    }

    private function fetchProductAndMarketTabsSettings() {
        foreach($this->settingVars->filterPages as $mainKey => $filterPage)
        {
            if($filterPage['isDynamic'])
            {
                $this->settingVars->filterPages[$mainKey]['breakDowns'] = $this->getBreakdownDataList($filterPage['tabsConfiguration'], $this->settingVars->filterPagesReplication[$mainKey]['config']);
                
                foreach($filterPage['tabsConfiguration'] as $key => $tabsConfiguration)
                {
                    $xmlTagAccountName  = $this->settingVars->dataArray[$tabsConfiguration['data']]['NAME'];
                    $xmlTag             = preg_match('/(,|\)|\()/',$xmlTagAccountName)==1 ? $this->settingVars->dataArray[$tabsConfiguration['data']]['NAME_ALIASE'] : strtoupper($xmlTagAccountName);

                    if (isset($this->settingVars->dataArray[$tabsConfiguration['data']]['use_alias_as_tag']))
                        $xmlTag = ($this->settingVars->dataArray[$tabsConfiguration['data']]['use_alias_as_tag']) ? strtoupper($this->settingVars->dataArray[$tabsConfiguration['data']]['NAME_ALIASE']) : $xmlTag;

                    if(isset($this->settingVars->dataArray[$tabsConfiguration['data']]['ID_ALIASE']) && !empty($this->settingVars->dataArray[$tabsConfiguration['data']]['ID_ALIASE']))
                        $xmlTag .= "_". $this->settingVars->dataArray[$tabsConfiguration['data']]['ID_ALIASE'];                        
                        
                    /* if (is_array($this->jsonOutput['filters'][$xmlTag]) && !empty($this->jsonOutput['filters'][$xmlTag])) {
                        $this->settingVars->filterPages[$mainKey]['tabsConfiguration'][$key]['dataList'] = (is_array($this->jsonOutput['filters'][$xmlTag]) && !empty($this->jsonOutput['filters'][$xmlTag])) ? $this->jsonOutput['filters'][$xmlTag] : array();
                    } */
                    
                    if (is_array($this->jsonOutput['SELECTED_'.$xmlTag]) && !empty($this->jsonOutput['SELECTED_'.$xmlTag])) {
                        $this->settingVars->filterPages[$mainKey]['tabsConfiguration'][$key]['selectedDataList'] = (isset($this->jsonOutput['SELECTED_'.$xmlTag]) && !empty($this->jsonOutput['SELECTED_'.$xmlTag])) ? $this->jsonOutput['SELECTED_'.$xmlTag] : array();
                    }
                    unset($this->jsonOutput['SELECTED_'.$xmlTag]);
                    
                    // to check the flag if breakdown exists or not
                    $this->settingVars->filterPages[$mainKey]['tabsConfiguration'][$key]['breakdown'] = $this->hasBreakdown[$tabsConfiguration['data']];
                }
            }
        }
    }

    public function getBreakdownDataList($tabList, $config)
    {
        $this->hasBreakdown = array();
        $tableName = strtolower($config['table_name']);
        $breakdownSettingName = str_replace('settings', 'breakdown_settings', $config['setting_name']); 

        $tabDetails = [];
        foreach($tabList as $tab){
            $column = explode(".", $tab['data']);
            $column = strtolower($column[1]);
            $columnMap[$column] = $tab['data'];
            $this->hasBreakdown[$tab['data']] = 0;
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

        $breakdownList = $this->setBreakdownList();

        $outputList = [];
        foreach($usedBreakdownArr as $breakdownID=>$columnArr){
            if ( array_key_exists($breakdownID, $breakdownList) ){
                $outputList[$breakdownID]['breakdown'] = $breakdownList[$breakdownID];

                foreach($columnArr as $columnName){
                    $this->hasBreakdown[$columnMap[$columnName]] = 1;
                    $outputList[$breakdownID]['columns'][] = $tabDetails[$columnName];
                }
            }
        }

        return $outputList;
    }    
    
    private function setBreakdownList(){
        $query = "SELECT id as breakdownID, breakdown_name as breakdownName, status FROM ".$this->settingVars->breakdownTable." WHERE accountID=".$this->settingVars->aid." AND projectID = ".$this->settingVars->projectID." AND status = 1 ";
        $breakdownData = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->projectManagerLinkid, db\ResultTypes::$TYPE_OBJECT);
        
        // Collection of Active breakdown Data
        foreach ($breakdownData as $key => $value) {
            $breakdownList[$value['breakdownID']] = $value;
        }

        return $breakdownList;
    }    
    
	private function fetchOutputColumnsDateOptions($configId)
	{
		$selectQuery = "SELECT * FROM ".$this->settingVars->outputColumnsTable." WHERE config_id = ".$configId;
        $result = $this->queryVars->queryHandler->runQuery($selectQuery, $this->queryVars->ddbLinkid, db\ResultTypes::$TYPE_OBJECT);
		
		$tmp = array();
        if (is_array($result) && !empty($result)) 
		{
            $selectedOutputColumns = explode('-', $result[0]['selected_column_code']);

			foreach($this->settingVars->outputDateOptions as $key => $date)
			{
				if (in_array($date['id'], $selectedOutputColumns))
					$this->settingVars->outputDateOptions[$key]['selected'] = true;
			}
        }
		$this->jsonOutput['outputDateOptions'] = $this->settingVars->outputDateOptions;
	}
	
    private function fetchFilter()
    {
        $filterId = $_REQUEST['filterId'];
        if ($filterId != '') {
            $filterDetailQuery = "SELECT * FROM ".$this->settingVars->ddbconfigTable.' WHERE '.$this->settingVars->ddbconfigTable.'.id = '.$filterId;
            $filterDetail = $this->queryVars->queryHandler->runQuery($filterDetailQuery, $this->queryVars->ddbLinkid, db\ResultTypes::$TYPE_OBJECT);
			
            if (is_array($filterDetail) && !empty($filterDetail)) {
                $this->fetchTimeFilter($filterDetail[0]['id']);
                $this->fetchMeasureSelectionFilter($filterDetail[0]['id']);
                $this->fetchOutputColumns($filterDetail[0]['id']);
                $this->fetchProductSelectionFilter($filterDetail[0]['id']);
				$this->fetchOutputColumnsDateOptions($filterDetail[0]['id']);
            }
            else {
                // NOTE: Need to write logic for wrong filter id
            }
        }
    }
    
    private function fetchTimeFilter($configId)
    {
        $selectQuery = "SELECT * FROM ".$this->settingVars->timeSelectionTable." WHERE config_id = ".$configId;
        $result = $this->queryVars->queryHandler->runQuery($selectQuery, $this->queryVars->ddbLinkid, db\ResultTypes::$TYPE_OBJECT);

        if (is_array($result) && !empty($result)) {
            if ($result[0]['week_range'] > 0) {
                $this->jsonOutput['fuzzy_selection'] = true;
                $selectedIndexTo = $this->jsonOutput['selectedIndexTo'];
                $selectedIndexFrom = ($selectedIndexTo+$result[0]['week_range'])-1;
            }
            else if($result[0]['week_range'] == -1)
            {
                $this->jsonOutput['fuzzy_selection'] = true;
                $selectedIndexTo = $this->jsonOutput['selectedIndexTo'];
                $selectedIndexFrom = $this->jsonOutput['selectedIndexFrom'];
                $result[0]['week_range'] = ($selectedIndexFrom-$selectedIndexTo)+1;
                $this->jsonOutput['weekRangeText'] = "YTD";
            }
            else {
                $this->jsonOutput['fuzzy_selection'] = false;
                $selectedIndexFrom = (is_array($this->jsonOutput['yearWeekList']) && !empty($this->jsonOutput['yearWeekList'])) ? array_search($result[0]['from_week_year'], array_column($this->jsonOutput['yearWeekList'], 'data')) : '';
                $selectedIndexTo = (is_array($this->jsonOutput['yearWeekList']) && !empty($this->jsonOutput['yearWeekList'])) ? array_search($result[0]['to_week_year'], array_column($this->jsonOutput['yearWeekList'], 'data')) : '';
            }
			
            $this->jsonOutput['weekRange'] = $result[0]['week_range'];
            $this->jsonOutput['selectedIndexTo'] = $selectedIndexTo;

            if($this->settingVars->timeSelectionUnit == "days")
                $this->jsonOutput['selectedIndexFrom'] = (int) $result[0]['from_week_year'];
            else{
                $this->jsonOutput['selectedIndexFrom'] = $selectedIndexFrom;
            }

            $this->jsonOutput['weekSeparatedReport'] = ($result[0]['week_separated_report']) ? 2 : 1;
            if (isset($result[0]['ty_ly_option'])) {
                $this->jsonOutput['timeFilterExportOption'] = $result[0]['ty_ly_option'];
            }
        }
    }

    private function fetchMeasureSelectionFilter($configId)
    {
        $selectQuery = "SELECT * FROM ".$this->settingVars->measuresSelectionTable." WHERE config_id = ".$configId;
        $result = $this->queryVars->queryHandler->runQuery($selectQuery, $this->queryVars->ddbLinkid, db\ResultTypes::$TYPE_OBJECT);

        if (is_array($result) && !empty($result)) {
            $measureOptions = explode('-', $result[0]['selected_measures_code']);
            foreach ($this->jsonOutput['measureOptions'] as $key => $measureOption) {
                if (in_array($measureOption['data'], $measureOptions))
                    $this->jsonOutput['measureOptions'][$key]['selected'] = true;
                else
                    $this->jsonOutput['measureOptions'][$key]['selected'] = false;
            }
        }
    }

    private function fetchproductSelectionFilter($configId)
    {
        if (isset($this->settingVars->pageArray) && isset($this->settingVars->pageArray[$this->settingVars->pageName]["DH"]) && !empty($this->settingVars->pageArray[$this->settingVars->pageName]["DH"]))
            $productSubFilters = explode("-", $this->settingVars->pageArray[$this->settingVars->pageName]["DH"]);

        if (is_array($productSubFilters) && !empty($productSubFilters)) {
            foreach ($productSubFilters as $productSubFilter) {

                $xmlTagAccountName  = $this->settingVars->dataArray[$productSubFilter]['NAME'];
                $xmlTag             = preg_match('/(,|\)|\()/',$xmlTagAccountName)==1 ? $this->settingVars->dataArray[$productSubFilter]['NAME_ALIASE'] : strtoupper($xmlTagAccountName);
                
				if (isset($this->settingVars->dataArray[$productSubFilter]['use_alias_as_tag']))
					$xmlTag = ($this->settingVars->dataArray[$productSubFilter]['use_alias_as_tag']) ? strtoupper($this->settingVars->dataArray[$productSubFilter]['NAME_ALIASE']) : $xmlTag;
				
				
                $selectQuery = "SELECT * FROM ".$this->settingVars->filterSettingTable." WHERE config_id = ".$configId." AND filter_code = '".$productSubFilter."'";
                $result = $this->queryVars->queryHandler->runQuery($selectQuery, $this->queryVars->ddbLinkid, db\ResultTypes::$TYPE_OBJECT);
				
                if (is_array($result) && !empty($result)) 
				{
                    $userSelection = $result[0]['user_selection'];
                    $userSelections = explode(',', $userSelection);
                    $selectedKeys = $unselectedOptions = $selectedOptions = array();
					
                    foreach ($userSelections as $key => $userSelect) 
					{
                        $searchIndex = (is_array($this->jsonOutput['filters'][$xmlTag]) && !empty($this->jsonOutput['filters'][$xmlTag])) ? array_search($userSelect, array_column($this->jsonOutput['filters'][$xmlTag], 'data')) : '';
                        if (is_numeric($searchIndex)) 
						{
                            $selectedOptions[] = $this->jsonOutput['filters'][$xmlTag][$searchIndex];
                            $selectedKeys[] = $searchIndex;
                        }
                    }
                    
                    foreach ($this->jsonOutput['filters'][$xmlTag] as $k => $option) 
					{
                        if (!in_array($k, $selectedKeys))
                            $unselectedOptions[] = $this->jsonOutput['filters'][$xmlTag][$k];
                    }
                    
                    if (is_array($selectedOptions) && !empty($selectedOptions))
                        $this->jsonOutput['SELECTED_'.$xmlTag] = $selectedOptions;

                    $this->jsonOutput['filters'][$xmlTag] = $unselectedOptions;
                }
            }
        }
    }

    private function fetchOutputColumns($configId)
    {
        $selectQuery = "SELECT * FROM ".$this->settingVars->outputColumnsTable." WHERE config_id = ".$configId;
        $result = $this->queryVars->queryHandler->runQuery($selectQuery, $this->queryVars->ddbLinkid, db\ResultTypes::$TYPE_OBJECT);
		
        if (is_array($result) && !empty($result)) 
		{
            $selectedOutputColumns = explode('-', $result[0]['selected_column_code']);
			
			foreach($this->settingVars->filterPages as $mainKey => $filterPage)
			{
				if($filterPage['isDynamic'])
				{
					foreach($filterPage['tabsConfiguration'] as $key => $tabsConfiguration)
					{			
						if (in_array($tabsConfiguration['data'], $selectedOutputColumns))
							$this->settingVars->filterPages[$mainKey]['tabsConfiguration'][$key]['selected'] = true;
						else
							$this->settingVars->filterPages[$mainKey]['tabsConfiguration'][$key]['selected'] = false;
					}
				}
			}
        }
    }

}
?>