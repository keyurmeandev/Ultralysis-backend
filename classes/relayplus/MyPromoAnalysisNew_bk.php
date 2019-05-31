<?php
namespace classes\relayplus;
ini_set("memory_limit", "1024M");

use datahelper;
use projectsettings;
use filters;
use db;
use config;
use utils;

class MyPromoAnalysisNew extends config\UlConfig{

    private $prePromoTyWeekRange;
    private $promoTyWeekRange;
    private $postPromoTyWeekRange;
    
    private $totalPrePromoWeek;
    private $totalPromoWeek;
    private $totalPostPromoWeek;
    
    private $aggregateSku;


    /**
     * Default gateway function, should be similar in all DATA CLASS
     * 
     * @param array $settingVars project settingsGateway variables
     *
     * @return Array
     */
	public function go($settingVars)
	{
		$this->initiate($settingVars);

		$this->settingVars->pageName = (empty($this->settingVars->pageName) && !empty($this->settingVars->pageID)) ? $this->settingVars->pageID.'_PromoAnalysisPage' : $this->settingVars->pageName;

        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll
        //$this->fetchConfig(); // Fetching filter configuration for page        
        
        if ((isset($_REQUEST['action']) && $_REQUEST['action'] != 'groupChange' && $_REQUEST['action'] != 'territoryChange') || !isset($_REQUEST['action'])) 
        {
			$this->getGroupList();
		}
        else if(isset($_REQUEST['action']) && $_REQUEST['action'] == 'groupChange')
        {
            $this->fetch_all_product_and_marketSelection_data();
            $this->fetchProductAndMarketTabsSettings();            
        }

/* 		$action = $_REQUEST['action'];
		switch ($action) {
			case "getConfig":
				$this->getSkuSelectionTab();
				break;
			case "filterChange":
				$this->prepareWeekRanges();	    
				$this->promoProductLineChart();
				$this->promoTopGrid();			
				break;
		} */
		
		return $this->jsonOutput;
	}

    public function getGroupList() {
        $query = "SELECT DISTINCT M.GID as GID, G.gname as GNAME FROM ".$this->settingVars->maintable." as M, ".
            $this->settingVars->grouptable." as G WHERE M.GID = G.gid";

        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
        
        $this->jsonOutput['groupList'] = $result;
    }    
    
    public function fetch_all_product_and_marketSelection_data() {
        
        $dataHelpers = array();
        if (!empty($this->settingVars->pageArray[$this->settingVars->pageName]["DH"]))
            $dataHelpers = explode("-", $this->settingVars->pageArray[$this->settingVars->pageName]["DH"]); //COLLECT REQUIRED PRODUCT FILTERS DATA FROM PROJECT SETTING     

        if (!empty($dataHelpers)) {
            
            foreach ($dataHelpers as $key => $account) {
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
    }    
    
    public function fetchProductAndMarketTabsSettings() {
        if (is_array($this->settingVars->productOptions_DisplayOptions) && !empty($this->settingVars->productOptions_DisplayOptions)) {
            
            foreach ($this->settingVars->productOptions_DisplayOptions as $key => $productSelectionTab) {
                $xmlTagAccountName  = $this->settingVars->dataArray[$productSelectionTab['data']]['NAME'];
                $xmlTag             = preg_match('/(,|\)|\()/',$xmlTagAccountName)==1 ? $this->settingVars->dataArray[$productSelectionTab['data']]['NAME_ALIASE'] : strtoupper($xmlTagAccountName);
                
                if (isset($this->settingVars->dataArray[$productSelectionTab['data']]['use_alias_as_tag']))
                    $xmlTag = ($this->settingVars->dataArray[$productSelectionTab['data']]['use_alias_as_tag']) ? strtoupper($this->settingVars->dataArray[$productSelectionTab['data']]['NAME_ALIASE']) : $xmlTag;

                if (is_array($this->jsonOutput[$xmlTag]) && !empty($this->jsonOutput[$xmlTag])) {
                    if(isset($_REQUEST['commonFilterPage']) && $_REQUEST['commonFilterPage'] == true)
                    {
                        $this->settingVars->productOptions_DisplayOptions[$key]['dataList'] = $this->jsonOutput['commonFilter'][$xmlTag]['dataList'];
                        $this->settingVars->productOptions_DisplayOptions[$key]['selectedDataList'] = (isset($this->jsonOutput['commonFilter'][$xmlTag]['selectedDataList'])) ? $this->jsonOutput['commonFilter'][$xmlTag]['selectedDataList'] : array();
                    }
                    else
                        $this->settingVars->productOptions_DisplayOptions[$key]['dataList'] = $this->jsonOutput[$xmlTag];
                        
                }
            }
        }

        $this->jsonOutput['productSelectionTabs'] = $this->settingVars->productOptions_DisplayOptions;
    }    
    
}
?>