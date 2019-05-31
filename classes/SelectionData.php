<?php
namespace classes;
use db;
use filters;
use config;
use datahelper;
use utils;

class SelectionData extends config\UlConfig{

	public $filterType;
	public $DisplayOptions;
	
    public function go($settingVars){
		$this->initiate($settingVars);//INITIATE COMMON VARIABLES

		if($_REQUEST['filterType'] == 'product')
		{
			$this->filterType = "product";
			$this->DisplayOptions = $this->settingVars->productOptions_DisplayOptions;
		}
		else if($_REQUEST['filterType'] == 'market')
		{
			$this->filterType = "market";
			$this->DisplayOptions = $this->settingVars->marketOptions_DisplayOptions;
		}		
		
		$action	= $_REQUEST["action"];
		switch($action) 
		{
			case "initFirstTab":
				$this->initFirstTab();
				break; 
			case "getKeyData": 
				$this->getKeyData();
				break;
			case "getTabDatalist": 
				$this->getTabDatalist();
				break;
		}
		return $this->jsonOutput;
    }
	
	function initFirstTab()
	{	
		if (is_array($this->DisplayOptions) && !empty($this->DisplayOptions))
		{
			$this->fetch_all_product_and_marketSelection_data($this->DisplayOptions[0]['data']);
			foreach ($this->DisplayOptions as $key => $selectionTab) {
				$xmlTagAccountName  = $this->settingVars->dataArray[$selectionTab['data']]['NAME'];
				$xmlTag             = preg_match('/(,|\)|\()/',$xmlTagAccountName)==1 ? $this->settingVars->dataArray[$selectionTab['data']]['NAME_ALIASE'] : strtoupper($xmlTagAccountName);
				
				if (isset($this->settingVars->dataArray[$selectionTab['data']]['use_alias_as_tag']))
					$xmlTag = ($this->settingVars->dataArray[$selectionTab['data']]['use_alias_as_tag']) ? strtoupper($this->settingVars->dataArray[$selectionTab['data']]['NAME_ALIASE']) : $xmlTag;

				if (is_array($this->jsonOutput[$xmlTag]) && !empty($this->jsonOutput[$xmlTag])) {
					if(isset($_REQUEST['commonFilterPage']) && $_REQUEST['commonFilterPage'] == true)
					{
						$this->DisplayOptions[$key]['dataList'] = $this->jsonOutput['commonFilter'][$xmlTag]['dataList'];
						$this->DisplayOptions[$key]['selectedDataList'] = (isset($this->jsonOutput['commonFilter'][$xmlTag]['selectedDataList'])) ? $this->jsonOutput['commonFilter'][$xmlTag]['selectedDataList'] : array();
					}
					else
						$this->DisplayOptions[$key]['dataList'] = $this->jsonOutput[$xmlTag];
				}
				$this->DisplayOptions[$key]['isDataLoaded'] = ($key == 0) ? true : false;
			}
		}
		$this->jsonOutput[$this->filterType.'SelectionTabs'] = $this->DisplayOptions;
	}

	public function getTabDatalist()
	{
		$account = $_REQUEST['account'];
		$selectedData = (isset($_REQUEST['selectedData']) && !empty($_REQUEST['selectedData'])) ? explode(",",urldecode($_REQUEST['selectedData'])) : array();

		$offset = (isset($_REQUEST['page']) && !empty($_REQUEST['page'])) ? ($_REQUEST['page']-1)*$this->settingVars->productAndMarketFilterTabDataLoadLimit : 0;

		$filterValue = (isset($_REQUEST['filterValue']) && !empty($_REQUEST['filterValue'])) ? $_REQUEST['filterValue'] : '';

		$selectedKeyData = array();
		if(isset($_REQUEST['account']) && !empty($_REQUEST['account']) && 
		   isset($this->settingVars->dataArray[$_REQUEST['account']]) && !empty($this->settingVars->dataArray[$_REQUEST['account']]))
		{

			$redisCache = new utils\RedisCache($this->queryVars);
            $requestHash = 'productAndMarketFilterAllData';
            $filterAllData = $redisCache->checkAndReadByStaticHashFromCache($requestHash);

            if ($filterAllData === false) {
                $configureProject = new config\ConfigureProject($this->settingVars, $this->queryVars);
                $configureProject->fetch_all_product_and_marketSelection_data();
                $filterAllData = $redisCache->checkAndReadByStaticHashFromCache($requestHash);
            }

            $filterType = $this->settingVars->dataArray[$account]['TYPE'];

            $filterParamsArray = (isset($_REQUEST['filterParams']) && !empty($_REQUEST['filterParams'])) ? json_decode(stripslashes($_REQUEST['filterParams'])) : array();

            $filterToConsider = array();
            if(isset($filterParamsArray) && !empty($filterParamsArray)) {
				$filterParamsArray = json_decode( json_encode($filterParamsArray), true);
				foreach ($filterParamsArray as $filterParam) {
					if ($filterParam['key'] != $account && $this->settingVars->dataArray[$filterParam['key']]['TYPE'] == $filterType) {
						$filterToConsider[$filterParam['key']] = explode(",", htmlspecialchars_decode(urldecode($filterParam['filterParams'])));
					}
				}
			}

			$requestedDataArray = $this->settingVars->dataArray[$account];
			$nameAliase = $requestedDataArray['NAME_ALIASE'];
            $idAliase = isset($requestedDataArray['ID_ALIASE']) ? $requestedDataArray['ID_ALIASE'] : "";

			if ($this->settingVars->enableFilteringFilterWithTabData && is_array($filterToConsider) && !empty($filterToConsider) && isset($filterAllData[$filterType]) && !empty($filterAllData[$filterType])) {
				$filterTypeData = $filterAllData[$filterType];
				foreach ($filterTypeData as $originalData) {
					$matchFound = array();
					foreach ($filterToConsider as $filterKey => $filteredData) {
						$filterDataArray = $this->settingVars->dataArray[$filterKey];
            			$filterAliase = isset($filterDataArray['ID_ALIASE']) ? $filterDataArray['ID_ALIASE'] : $filterDataArray['NAME_ALIASE'];
						if (in_array($originalData[$filterAliase], $filteredData))
							$matchFound[$filterKey] = true;
					}

					if (count($matchFound) == count($filterToConsider)) {
						$alias = (!empty($idAliase)) ? $idAliase : $nameAliase;
						if (!in_array($originalData[$alias], $selectedData)){
							$finalData[] = $originalData;
						}
					}
				}

				$finalOutputData = $this->prepareDataAfterFilter($account, $finalData);
				$datalistKey['dataList'] = $finalOutputData;
				$selectedKeyData[] = $datalistKey;
			} else {
				$redisCache = new utils\RedisCache($this->queryVars);
	            $requestHash = 'productAndMarketFilterTabData';
	            $filterData = $redisCache->checkAndReadByStaticHashFromCache($requestHash);

	            if ($filterData === false) {
	                $configureProject = new config\ConfigureProject($this->settingVars, $this->queryVars);
	                $configureProject->fetch_all_product_and_marketSelection_data();
	                $filterData = $redisCache->checkAndReadByStaticHashFromCache($requestHash);
	            }

				$xmlTagAccountName  = $this->settingVars->dataArray[$account]['NAME'];
				$xmlTag             = preg_match('/(,|\)|\()/',$xmlTagAccountName)==1 ? $this->settingVars->dataArray[$account]['NAME_ALIASE'] : strtoupper($xmlTagAccountName);
	                
	                $xmlTag = ($this->settingVars->dataArray[$account]['use_alias_as_tag']) ? strtoupper($this->settingVars->dataArray[$account]['NAME_ALIASE']) : $xmlTag;
	            
	                if(isset($this->settingVars->dataArray[$account]['ID_ALIASE']) && !empty($this->settingVars->dataArray[$account]['ID_ALIASE']))
	                    $xmlTag .= "_". $this->settingVars->dataArray[$account]['ID_ALIASE'];

				if (is_array($filterData[$xmlTag]) && !empty($filterData[$xmlTag])) {
					if(isset($_REQUEST['commonFilterPage']) && $_REQUEST['commonFilterPage'] == true)
					{
						$selectedKeyData[$key]['dataList'] = $filterData['commonFilter'][$xmlTag]['dataList'];
						$selectedKeyData[$key]['selectedDataList'] = (isset($filterData['commonFilter'][$xmlTag]['selectedDataList'])) ? $filterData['commonFilter'][$xmlTag]['selectedDataList'] : array();
					} else {
						// print_r($filterData);
						$filteredArray = $filterData[$xmlTag];

						$dataSlice = $filteredArray;
						if (is_array($selectedData) && !empty($selectedData)) {
							$datalistKey['dataList'] = [];
							foreach ($dataSlice as $filterDataArray) {
								if (!in_array($filterDataArray['data'], $selectedData)){
									$datalistKey['dataList'][] = $filterDataArray;
								}
							}
						} else {
							$datalistKey['dataList'] = $dataSlice;
							$datalistKey['selectedDataList'] = [];
						}
					}
					$selectedKeyData[] = $datalistKey;
				}
			}
            
		}
		$this->jsonOutput['selectionTabsData'] = $selectedKeyData;
	}

	public function prepareDataAfterFilter($account, $filteredData)
	{
		$requestedDataArray = $this->settingVars->dataArray[$account];
		$tagNameAccountName = $requestedDataArray['NAME'];

        //IF 'NAME' FIELD CONTAINS SOME SYMBOLS THAT CAN'T PASS AS A VALID XML TAG , WE USE 'NAME_ALIASE' INSTEAD AS XML TAG
        //AND FOR THAT PARTICULAR REASON, WE ALWAYS SET 'NAME_ALIASE' SO THAT IT IS A VALID XML TAG
        $tagName = preg_match('/(,|\)|\()|\./', $tagNameAccountName) == 1 ? $requestedDataArray['NAME_ALIASE'] : strtoupper($tagNameAccountName);

        if (isset($requestedDataArray['use_alias_as_tag']))
        {
            $tagName = ($requestedDataArray['use_alias_as_tag']) ? strtoupper($requestedDataArray['NAME_ALIASE']) : $tagName;
            
            if(isset($requestedDataArray['ID_ALIASE']) && !empty($requestedDataArray['ID_ALIASE']))
                $tagName .= "_". $requestedDataArray['ID_ALIASE'];
        }

        $includeIdInLabel = false;
        if (isset($requestedDataArray['include_id_in_label']))
            $includeIdInLabel = ($requestedDataArray['include_id_in_label']) ? true : false;

        $tempId = key_exists('ID', $requestedDataArray) ? $requestedDataArray['ID'] : "";
        
        $nameAliase = $requestedDataArray['NAME_ALIASE'];
        $idAliase = isset($requestedDataArray['ID_ALIASE']) ? $requestedDataArray['ID_ALIASE'] : "";

        $type = $requestedDataArray['TYPE'];
        $requestedTabData = array();
        datahelper\Product_And_Market_Filter_DataCollector::getFilterData( $nameAliase, $idAliase, $tempId, $filteredData, $tagName , $requestedTabData, $includeIdInLabel, $account);

        if (isset($requestedTabData['filters']) && is_array($requestedTabData['filters']) && isset($requestedTabData['filters'][$tagName]) && !empty($requestedTabData['filters'][$tagName]))
        	return $requestedTabData['filters'][$tagName];
        else
        	return array();
	}
	
	function getKeyData()
	{
		if (is_array($this->DisplayOptions) && !empty($this->DisplayOptions))
		{
			$selectedKeyData = array();
			$this->fetch_all_product_and_marketSelection_data($_REQUEST['account']);
			foreach ($this->DisplayOptions as $key => $selectionTab) 
			{
				if($this->settingVars->dataArray[$selectionTab['data']]['NAME_ALIASE'] == $_REQUEST['account'])
				{
					$xmlTagAccountName  = $this->settingVars->dataArray[$selectionTab['data']]['NAME'];
					$xmlTag             = preg_match('/(,|\)|\()/',$xmlTagAccountName)==1 ? $this->settingVars->dataArray[$selectionTab['data']]['NAME_ALIASE'] : strtoupper($xmlTagAccountName);
					
					if (isset($this->settingVars->dataArray[$selectionTab['data']]['use_alias_as_tag']))
						$xmlTag = ($this->settingVars->dataArray[$selectionTab['data']]['use_alias_as_tag']) ? strtoupper($this->settingVars->dataArray[$selectionTab['data']]['NAME_ALIASE']) : $xmlTag;

					if (is_array($this->jsonOutput[$xmlTag]) && !empty($this->jsonOutput[$xmlTag])) {
						if(isset($_REQUEST['commonFilterPage']) && $_REQUEST['commonFilterPage'] == true)
						{
							$selectedKeyData[$key]['dataList'] = $this->jsonOutput['commonFilter'][$xmlTag]['dataList'];
							$selectedKeyData[$key]['selectedDataList'] = (isset($this->jsonOutput['commonFilter'][$xmlTag]['selectedDataList'])) ? $this->jsonOutput['commonFilter'][$xmlTag]['selectedDataList'] : array();
						}
						else
							$selectedKeyData[]['dataList'] = $this->jsonOutput[$xmlTag];
					}
				}
			}
		}
		$this->jsonOutput['selectionTabsData'] = $selectedKeyData;
	}
	
    /** ***
     * COLLECTS ALL PRODUCT AND MARKET SELECTION DATA HELPERS AND CONCATE THEM WITH GLOBAL $jsonOutput 
     * *** 
     */
    public function fetch_all_product_and_marketSelection_data($account) {
        
		if($account != "")
		{
			//IN RARE CASES WE NEED TO ADD ADITIONAL FIELDS IN GROUP BY CLUAUSE AND ALSO AS LABEL WITH THE ORIGINAL ACCOUNT
			//E.G: FOR RUBICON LCL - SKU DATA HELPER IS SHOWN AS 'SKU #UPC'
			//IN ABOVE CASE WE SEND DH VALUE = F1#F2 [ASSUMING F1 AND F2 ARE SKU AND UPC'S INDEX IN DATAARRAY]
			$combineAccounts = explode("#", $account);
			$selectPart = array();
			$groupByPart = array();
			
			foreach ($combineAccounts as $accountKey => $singleAccount) {
				$tempId = key_exists('ID', $this->settingVars->dataArray[$singleAccount]) ? $this->settingVars->dataArray[$singleAccount]['ID'] : "";
				if ($tempId != "") {
					$selectPart[] = $tempId . " AS " . getAdjectiveForIndex($accountKey) . '_ID';
					$groupByPart[] = getAdjectiveForIndex($accountKey) . '_ID';
				}
				
				$tempName = $this->settingVars->dataArray[$singleAccount]['NAME'];
				$selectPart[] = $tempName . " AS " . getAdjectiveForIndex($accountKey) . '_LABEL';
				$groupByPart[] = getAdjectiveForIndex($accountKey) . '_LABEL';
				
			}
			
			$helperTableName = $this->settingVars->dataArray[$combineAccounts[0]]['tablename'];
			$helperLink = $this->settingVars->dataArray[$combineAccounts[0]]['link'];
			$tagNameAccountName = $this->settingVars->dataArray[$combineAccounts[0]]['NAME'];

			//IF 'NAME' FIELD CONTAINS SOME SYMBOLS THAT CAN'T PASS AS A VALID XML TAG , WE USE 'NAME_ALIASE' INSTEAD AS XML TAG
			//AND FOR THAT PARTICULAR REASON, WE ALWAYS SET 'NAME_ALIASE' SO THAT IT IS A VALID XML TAG
			$tagName = preg_match('/(,|\)|\()|\./', $tagNameAccountName) == 1 ? $this->settingVars->dataArray[$combineAccounts[0]]['NAME_ALIASE'] : strtoupper($tagNameAccountName);

			if (isset($this->settingVars->dataArray[$combineAccounts[0]]['use_alias_as_tag']))
				$tagName = ($this->settingVars->dataArray[$combineAccounts[0]]['use_alias_as_tag']) ? strtoupper($this->settingVars->dataArray[$combineAccounts[0]]['NAME_ALIASE']) : $tagName;

			$includeIdInLabel = false;
			if (isset($this->settingVars->dataArray[$combineAccounts[0]]['include_id_in_label']))
				$includeIdInLabel = ($this->settingVars->dataArray[$combineAccounts[0]]['include_id_in_label']) ? true : false;
			
			datahelper\Product_And_Market_Filter_DataCollector::collect_Filter_Data($selectPart, $groupByPart, $tagName, $helperTableName, $helperLink, $this->jsonOutput, $includeIdInLabel, $account);
		}
    }	
}
?> 