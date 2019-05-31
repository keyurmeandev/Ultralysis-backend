<?php

namespace classes\nielsen;

use projectsettings;
use db;
use config;
use utils;
use datahelper;
use lib;

class ItemsRankingReports extends config\UlConfig {
    /*     * ***
     * Default gateway function, should be similar in all DATA CLASS
     * arguments:
     *  $settingVars [project settingsGateway variables]
     * @return $xmlOutput with all data that should be sent to client app
     * *** */

    public function go($settingVars) {
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES

        $this->pageName = $_REQUEST["pageName"]; // Identify the page name
        $this->settingVars->pageName = $this->pageName; // set pageName in settingVars

        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll
        if ($_GET['DataHelper'] == "true") {
            $this->fetch_all_product_and_marketSelection_data();
            $this->fetchProductAndMarketTabsSettings(); // setting product and market tabs data
            $this->getProjectSettings();
        }
        switch ($_REQUEST['action']) {
            case "gridData":
                $this->prepareData();
                break;

            case "downloadXlsx":
                $this->downloadXlsx();
                break;
            default:
                $this->marketListhelpingData();
                //$this->getLastMyDate();
                $this->prepareData();
                break;
        }
		$this->jsonOutput['clientLogo'] = $this->settingVars->clientLogo;
		$this->jsonOutput['headerText'] = $this->settingVars->headerText;
        return $this->jsonOutput;
    }

    private function getProjectSettings() {
        $cid = $this->settingVars->aid;
        $settingName = $this->settingVars->menuArray['MF5']['SETTING_NAME'];
        $settingValue = $this->settingVars->menuArray['MF5']['SETTING_VALUE'];
        $settings = $this->settingVars->defaultProjectSettings;

        if (!empty($settingName) && !empty($settingValue)) {
            $table = $this->settingVars->configTable;
            $query = "SELECT ".$settingName." as SETTING_NAME, ".$settingValue." as SETTING_VALUE FROM ".$table." WHERE accountID = ".$cid.$this->settingVars->projectHelperLink;
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->projectManagerLinkid, db\ResultTypes::$TYPE_OBJECT);

            if (is_array($result) && !empty($result)) {
                foreach ($result as $detail)
                    $settings[$detail['SETTING_NAME']] = $detail['SETTING_VALUE'];
            }

            if (isset($settings['has_kdr']) && $settings['has_kdr'] == '1')
                $settings['has_product_filter'] = '1';

            if (isset($settings['has_territory']) && $settings['has_territory'] == '1')
                $settings['has_market_filter'] = '1';
            
            if (isset($settings['has_account']) && $settings['has_account'] == '1')
                $settings['has_market_filter'] = '1';
        }

        $this->jsonOutput["settings"] = $settings;
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

        if (is_array($this->settingVars->marketOptions_DisplayOptions) && !empty($this->settingVars->marketOptions_DisplayOptions)) {
            foreach ($this->settingVars->marketOptions_DisplayOptions as $key => $marketSelectionTab) {
                $xmlTagAccountName  = $this->settingVars->dataArray[$marketSelectionTab['data']]['NAME'];
                $xmlTag             = preg_match('/(,|\)|\()/',$xmlTagAccountName)==1 ? $this->settingVars->dataArray[$marketSelectionTab['data']]['NAME_ALIASE'] : strtoupper($xmlTagAccountName);

                if (isset($this->settingVars->dataArray[$marketSelectionTab['data']]['use_alias_as_tag']))
                    $xmlTag = ($this->settingVars->dataArray[$marketSelectionTab['data']]['use_alias_as_tag']) ? strtoupper($this->settingVars->dataArray[$marketSelectionTab['data']]['NAME_ALIASE']) : $xmlTag;

                if (is_array($this->jsonOutput[$xmlTag]) && !empty($this->jsonOutput[$xmlTag])) {
					if(isset($_REQUEST['commonFilterPage']) && $_REQUEST['commonFilterPage'] == true)
					{
						$this->settingVars->marketOptions_DisplayOptions[$key]['dataList'] = $this->jsonOutput['commonFilter'][$xmlTag]['dataList'];
						$this->settingVars->marketOptions_DisplayOptions[$key]['selectedDataList'] = (isset($this->jsonOutput['commonFilter'][$xmlTag]['selectedDataList'])) ? $this->jsonOutput['commonFilter'][$xmlTag]['selectedDataList'] : array();
					}
					else
						$this->settingVars->marketOptions_DisplayOptions[$key]['dataList'] = $this->jsonOutput[$xmlTag];
                }
            }
        }

        $this->jsonOutput['productSelectionTabs'] = $this->settingVars->productOptions_DisplayOptions;
        $this->jsonOutput['marketSelectionTabs'] = $this->settingVars->marketOptions_DisplayOptions;
        $this->jsonOutput['customFieldOptions'] = $this->settingVars->customField_DisplayOptions;
    }

    /*     * ***
     * COLLECTS ALL PRODUCT AND MARKET SELECTION DATA HELPERS AND CONCATE THEM WITH GLOBAL $jsonOutput 
     * *** */

    public function fetch_all_product_and_marketSelection_data() {
		
        $dataHelpers = array();
        if (!empty($this->settingVars->pageArray[$this->pageName]["DH"]))
            $dataHelpers = explode("-", $this->settingVars->pageArray[$this->pageName]["DH"]); //COLLECT REQUIRED PRODUCT FILTERS DATA FROM PROJECT SETTING		

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

    private function marketListhelpingData() {
        /*$whareCalusePart = "WHERE 1 AND ".$this->settingVars->dataArray['F1']['NAME']." <>'' AND accountID= ". $this->settingVars->aid;
        $groupByPart = " GROUP BY ACCOUNT, list_order";
        $orderByPart = " ORDER BY list_order ASC";*/
        /*$query = "SELECT " . $this->settingVars->dataArray['F1']['NAME'] . " AS ACCOUNT FROM " .
                $this->settingVars->dataArray['F1']['tablename'] . " " .
                $whareCalusePart . " " .
                $groupByPart . " " .
                $orderByPart;*/
        //echo $query;exit;    
        $groupByPart = " GROUP BY ACCOUNT ";
        $orderByPart = " ORDER BY ACCOUNT ASC";
        $this->marketField = $this->settingVars->dataArray['F1']['NAME'];

        $query = "SELECT " . $this->marketField . " AS ACCOUNT FROM " .
                $this->settingVars->tablename . " " .$this->queryPart.
                $groupByPart . " " .
                $orderByPart;
        //echo $query;exit;
        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);

		if(is_array($result) && !empty($result))
		{
			$this->marketID = $result[0]['ACCOUNT'];
			$_REQUEST['marketID'][0] = $this->marketID;
			$this->jsonOutput['marketList'] = $result;
		}
		else
			$this->jsonOutput['marketList'] = 'No Data';
    }

    private function prepareData() {
        $this->prepareSummaryGrid();
        $this->prepareMarketCrossTabGrid("CONTROL_FLAG IN(0,2) ", "itemsRankingReportsWithoutFlag");
        $this->prepareMarketCrossTabGrid("CONTROL_FLAG IN(1,127) ", "segmentRankingReportGridData");  // flag = 1 change for check
        
        // Menu object to create menu based on projectid. This is using global project-manager database.
        $menuBuilt = new lib\MenuBuilt($this->settingVars->projectID, $this->settingVars->aid, $this->queryVars);
        $this->jsonOutput["MENU_LIST"] = $menuBuilt->getMenus();
    }

    function downloadXlsx() {
        if ($_REQUEST['gridTag'] == "itemsRanking") {
            $this->prepareMarketCrossTabGrid("CONTROL_FLAG IN(0,2) ", "itemsRankingReportsWithoutFlag");
        } else {
            $this->prepareMarketCrossTabGrid("CONTROL_FLAG IN(1,127) ", "segmentRankingReportGridData");
        }
    }

    function getLastMyDate() {
        $query = "SELECT MAX(" . $this->settingVars->maintable . ".mydate) AS MYDATE FROM " . $this->settingVars->table_name . $this->queryPart;
        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);        
		if(is_array($result) && !empty($result))		
			$this->jsonOutput['mydate'] = $result[0];
    }

    function prepareSummaryGrid() {
		$temp = array();
        $selectPart = array();
		if($this->settingVars->defaultskuID != '')
			$queryPart = $this->getAll() . " AND " . $this->settingVars->skutable . ".skuID='".$this->settingVars->defaultskuID."' "; //PREPARE TABLE JOINING STRINGS USING PARENT getAll
		else
			$queryPart = $this->getAll(). " ";
        $i = 1;
		
		if(is_array($_REQUEST['marketID']) && !empty($_REQUEST['marketID']))
		{
			// PREPARE QUERY PART 
			foreach ($_REQUEST['marketID'] as $currentMarketID) {
				$selectPart[] = "SUM((CASE WHEN ".$this->marketField."='" . $currentMarketID . "' THEN 1 ELSE 0 END) * " . $this->settingVars->mesureArray[$_REQUEST['ValueVolume']] . ") AS SALES_L4_TY_$i";
				$selectPart[] = "SUM((CASE WHEN ".$this->marketField."='" . $currentMarketID . "' THEN 1 ELSE 0 END) * " . $this->settingVars->mesureArray[$_REQUEST['ValueVolume']] . ")-SUM((CASE WHEN ".$this->marketField."='" . $currentMarketID . "' THEN 1 ELSE 0 END) * " . $this->settingVars->mesureArray[$_REQUEST['ValueVolume']] . ") AS SALES_L4_TY_VS_$i";
				$selectPart[] = "ROUND(((SUM((CASE WHEN ".$this->marketField."='" . $currentMarketID . "' THEN 1 ELSE 0 END) * " . $this->settingVars->mesureArray[$_REQUEST['ValueVolume']] . ")-SUM((CASE WHEN ".$this->marketField."='" . $currentMarketID . "' THEN 1 ELSE 0 END) * " . $this->settingVars->mesureArray[$_REQUEST['ValueVolume']] . "))/SUM((CASE WHEN ".$this->marketField."='" . $currentMarketID . "' THEN 1 ELSE 0 END) * " . $this->settingVars->mesureArray[$_REQUEST['ValueVolume']] . "))*100, 1) AS SALES_L4_VAR_$i";

				/*$selectPart[] = "SUM((CASE WHEN ".$this->marketField."='" . $currentMarketID . "' THEN 1 ELSE 0 END) * " . $this->settingVars->mesureArray[$_REQUEST['ValueVolume']] . "_L12_TY) AS SALES_L12_TY_$i";
				$selectPart[] = "SUM((CASE WHEN ".$this->marketField."='" . $currentMarketID . "' THEN 1 ELSE 0 END) * " . $this->settingVars->mesureArray[$_REQUEST['ValueVolume']] . "_L12_TY)-SUM((CASE WHEN ".$this->marketField."='" . $currentMarketID . "' THEN 1 ELSE 0 END) * " . $this->settingVars->mesureArray[$_REQUEST['ValueVolume']] . "_L12_LY) AS SALES_L12_TY_VS_$i";
				$selectPart[] = "ROUND(((SUM((CASE WHEN ".$this->marketField."='" . $currentMarketID . "' THEN 1 ELSE 0 END) * " . $this->settingVars->mesureArray[$_REQUEST['ValueVolume']] . "_L12_TY)-SUM((CASE WHEN ".$this->marketField."='" . $currentMarketID . "' THEN 1 ELSE 0 END) * " . $this->settingVars->mesureArray[$_REQUEST['ValueVolume']] . "_L12_LY))/SUM((CASE WHEN ".$this->marketField."='" . $currentMarketID . "' THEN 1 ELSE 0 END) * " . $this->settingVars->mesureArray[$_REQUEST['ValueVolume']] . "_L12_LY))*100, 1) AS SALES_L12_VAR_$i";

				$selectPart[] = "SUM((CASE WHEN ".$this->marketField."='" . $currentMarketID . "' THEN 1 ELSE 0 END) * " . $this->settingVars->mesureArray[$_REQUEST['ValueVolume']] . "_YTD_TY) AS SALES_YTD_TY_$i";
				$selectPart[] = "SUM((CASE WHEN ".$this->marketField."='" . $currentMarketID . "' THEN 1 ELSE 0 END) * " . $this->settingVars->mesureArray[$_REQUEST['ValueVolume']] . "_YTD_TY)-SUM((CASE WHEN ".$this->marketField."='" . $currentMarketID . "' THEN 1 ELSE 0 END) * " . $this->settingVars->mesureArray[$_REQUEST['ValueVolume']] . "_YTD_LY) AS SALES_YTD_TY_VS_$i";
				$selectPart[] = "ROUND(((SUM((CASE WHEN ".$this->marketField."='" . $currentMarketID . "' THEN 1 ELSE 0 END) * " . $this->settingVars->mesureArray[$_REQUEST['ValueVolume']] . "_YTD_TY)-SUM((CASE WHEN ".$this->marketField."='" . $currentMarketID . "' THEN 1 ELSE 0 END) * " . $this->settingVars->mesureArray[$_REQUEST['ValueVolume']] . "_YTD_LY))/SUM((CASE WHEN ".$this->marketField."='" . $currentMarketID . "' THEN 1 ELSE 0 END) * " . $this->settingVars->mesureArray[$_REQUEST['ValueVolume']] . "_YTD_LY))*100, 1) AS SALES_YTD_VAR_$i";

				$selectPart[] = "SUM((CASE WHEN ".$this->marketField."='" . $currentMarketID . "' THEN 1 ELSE 0 END) * " . $this->settingVars->mesureArray[$_REQUEST['ValueVolume']] . "_L52_TY) AS SALES_L52_TY_$i";
				$selectPart[] = "SUM((CASE WHEN ".$this->marketField."='" . $currentMarketID . "' THEN 1 ELSE 0 END) * " . $this->settingVars->mesureArray[$_REQUEST['ValueVolume']] . "_L52_TY)-SUM((CASE WHEN ".$this->marketField."='" . $currentMarketID . "' THEN 1 ELSE 0 END) * " . $this->settingVars->mesureArray[$_REQUEST['ValueVolume']] . "_L52_LY) AS SALES_L52_TY_VS_$i";
				$selectPart[] = "ROUND(((SUM((CASE WHEN ".$this->marketField."='" . $currentMarketID . "' THEN 1 ELSE 0 END) * " . $this->settingVars->mesureArray[$_REQUEST['ValueVolume']] . "_L52_TY)-SUM((CASE WHEN ".$this->marketField."='" . $currentMarketID . "' THEN 1 ELSE 0 END) * " . $this->settingVars->mesureArray[$_REQUEST['ValueVolume']] . "_L52_LY))/SUM((CASE WHEN ".$this->marketField."='" . $currentMarketID . "' THEN 1 ELSE 0 END) * " . $this->settingVars->mesureArray[$_REQUEST['ValueVolume']] . "_L52_LY))*100, 1) AS SALES_L52_VAR_$i";
				$havingPart[] = "SALES_L52_TY_$i > 0";*/
				$i++;
			}

			$query = "SELECT " . implode(",", $selectPart) . " " .
					"FROM " . $this->settingVars->table_name . $queryPart ; 
					//(($this->settingVars->summaryControlFlag > 0) ? " AND ".$this->settingVars->skutable.".control_flag = ".$this->settingVars->summaryControlFlag . " " : "");
					//"HAVING " . implode(" OR ", $havingPart);
			//echo $query;exit;
			$result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
			
			

			$varSummary = array();

			$marketIndex = 1;

			foreach ($_REQUEST['marketID'] as $currentMarketID) {
				if(is_array($result) && !empty($result))
				{
					foreach ($result as $value) {
						$temp['SALES_L4_TY_' . $marketIndex] = number_format($value['SALES_L4_TY_' . $marketIndex], 0, '.', ',');
						$temp['SALES_L12_TY_' . $marketIndex] = number_format($value['SALES_L12_TY_' . $marketIndex], 0, '.', ',');
						$temp['SALES_YTD_TY_' . $marketIndex] = number_format($value['SALES_YTD_TY_' . $marketIndex], 0, '.', ',');
						$temp['SALES_L52_TY_' . $marketIndex] = number_format($value['SALES_L52_TY_' . $marketIndex], 0, '.', ',');

						$temp['SALES_L4_TY_VS_' . $marketIndex] = number_format($value['SALES_L4_TY_VS_' . $marketIndex], 0, '.', ',');
						$temp['SALES_L12_TY_VS_' . $marketIndex] = number_format($value['SALES_L12_TY_VS_' . $marketIndex], 0, '.', ',');
						$temp['SALES_YTD_TY_VS_' . $marketIndex] = number_format($value['SALES_YTD_TY_VS_' . $marketIndex], 0, '.', ',');
						$temp['SALES_L52_TY_VS_' . $marketIndex] = number_format($value['SALES_L52_TY_VS_' . $marketIndex], 0, '.', ',');
						
						if ($value['SALES_L4_VAR_' . $marketIndex] >= 0) {
							$temp['SALES_L4_TY_VS_' . $marketIndex] = "+" . $temp['SALES_L4_TY_VS_' . $marketIndex];
							$temp['SALES_L4_VAR_' . $marketIndex] = "+" . $value['SALES_L4_VAR_' . $marketIndex];
						} else {
							$temp['SALES_L4_TY_VS_' . $marketIndex] = $temp['SALES_L4_TY_VS_' . $marketIndex] > 0 ? "-" . $temp['SALES_L4_TY_VS_' . $marketIndex] : $temp['SALES_L4_TY_VS_' . $marketIndex];
							$temp['SALES_L4_VAR_' . $marketIndex] = $value['SALES_L4_VAR_' . $marketIndex] > 0 ? "-" . $value['SALES_L4_VAR_' . $marketIndex] : $value['SALES_L4_VAR_' . $marketIndex];
						}

						if ($value['SALES_L12_VAR_' . $marketIndex] >= 0) {
							$temp['SALES_L12_TY_VS_' . $marketIndex] = "+" . $temp['SALES_L12_TY_VS_' . $marketIndex];
							$temp['SALES_L12_VAR_' . $marketIndex] = "+" . $value['SALES_L12_VAR_' . $marketIndex];
						} else {
							$temp['SALES_L12_TY_VS_' . $marketIndex] = $temp['SALES_L12_TY_VS_' . $marketIndex] > 0 ? "-" . $temp['SALES_L12_TY_VS_' . $marketIndex] : $temp['SALES_L12_TY_VS_' . $marketIndex];
							$temp['SALES_L12_VAR_' . $marketIndex] = $value['SALES_L12_VAR_' . $marketIndex] > 0 ? "-" . $value['SALES_L12_VAR_' . $marketIndex] : $value['SALES_L12_VAR_' . $marketIndex];
						}

						if ($value['SALES_YTD_VAR_' . $marketIndex] >= 0) {
							$temp['SALES_YTD_TY_VS_' . $marketIndex] = "+" . $temp['SALES_YTD_TY_VS_' . $marketIndex];
							$temp['SALES_YTD_VAR_' . $marketIndex] = "+" . $value['SALES_YTD_VAR_' . $marketIndex];
						} else {
							$temp['SALES_YTD_TY_VS_' . $marketIndex] = $temp['SALES_YTD_TY_VS_' . $marketIndex] > 0 ? "-" . $temp['SALES_YTD_TY_VS_' . $marketIndex] : $temp['SALES_YTD_TY_VS_' . $marketIndex];
							$temp['SALES_YTD_VAR_' . $marketIndex] = $value['SALES_YTD_VAR_' . $marketIndex] > 0 ? "-" . $value['SALES_YTD_VAR_' . $marketIndex] : $value['SALES_YTD_VAR_' . $marketIndex];
						}

						//echo $value['SALES_YTD_VAR_1'];exit;

						if ($value['SALES_L52_VAR_' . $marketIndex] >=0) {
							$temp['SALES_L52_TY_VS_' . $marketIndex] = "+" . $temp['SALES_L52_TY_VS_' . $marketIndex];
							$temp['SALES_L52_VAR_' . $marketIndex] = "+" . $value['SALES_L52_VAR_' . $marketIndex];
						} else {
							$temp['SALES_L52_TY_VS_' . $marketIndex] = $temp['SALES_L52_TY_VS_' . $marketIndex] > 0 ? "-" . $temp['SALES_L52_TY_VS_' . $marketIndex] : $temp['SALES_L52_TY_VS_' . $marketIndex];
							$temp['SALES_L52_VAR_' . $marketIndex] = $value['SALES_L52_VAR_' . $marketIndex] > 0 ? "-" . $value['SALES_L52_VAR_' . $marketIndex] : $value['SALES_L52_VAR_' . $marketIndex];
						}

						$marketIndex++;
					}
				}
			}
		}
        $this->jsonOutput['itemsRankingReportsSummary'] = $temp;
    }

    private function prepareMarketCrossTabGrid($flagCondition, $jsonTagName) {
		
		
		$finalArray 		= array();
		$allChartDataArr 	= array();
        //PREPARE QUERY CHUNK FOR SELECTED MARKET ID(S)
        $selectPart 		= array();
        $marketIndex 		= 1;
        $selectPartForTotal = array();
		
		if(is_array($_REQUEST['marketID']) && !empty($_REQUEST['marketID']))
		{	
			
			foreach ($_REQUEST['marketID'] as $currentMarketID) {
				// PREPARE THE PARTS ONLY FOR THE SUPPLIED MARKET IDS  
				if (!empty($currentMarketID) && $currentMarketID != "") {
					//$selectPartForTotal[] = "SUM(SALES_" . $_REQUEST['timeFrame'] . "_TY) AS TOTAL_$marketIndex";
					//$selectPartForTotal[] = "SUM(SALES_" . $_REQUEST['timeFrame'] . "_TY) AS TOTAL_$marketIndex";
					$selectPartForTotal[] = "SUM((CASE WHEN ".$this->marketField."='" . $currentMarketID . "' THEN 1 ELSE 0 END) * SALES_" . $_REQUEST['timeFrame'] . "_TY) AS TOTAL_$marketIndex";

					$selectPart[] = "SUM((CASE WHEN ".$this->marketField."='" . $currentMarketID . "' THEN 1 ELSE 0 END) * " . $this->settingVars->maintable . "." . $this->settingVars->mesureArray[$_REQUEST['ValueVolume']] . "_" . $_REQUEST['timeFrame'] . "_TY) AS SALES_TY_$marketIndex";
					$selectPart[] = "SUM((CASE WHEN ".$this->marketField."='" . $currentMarketID . "' THEN 1 ELSE 0 END) * " . $this->settingVars->maintable . "." . $this->settingVars->mesureArray[$_REQUEST['ValueVolume']] . "_" . $_REQUEST['timeFrame'] . "_LY) AS SALES_LY_$marketIndex";
					$selectPart[] = "SUM((CASE WHEN ".$this->marketField."='" . $currentMarketID . "' THEN 1 ELSE 0 END) * " . $this->settingVars->maintable . ".DIST_" . $_REQUEST['timeFrame'] . "_TY) AS DIST_TY_$marketIndex";
					$havingPart[] = "SALES_TY_$marketIndex > 0 OR SALES_LY_$marketIndex > 0 ";
				}
				$marketIndex++;
			}

			//PREPARE TOTAL SALES QUERY
			$query = "SELECT " . implode(",", $selectPartForTotal) . " " .
					"FROM " . $this->settingVars->table_name . $this->queryPart . " AND " . $this->settingVars->skutable . "." . $flagCondition;
			
			$totalSalesArray = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);

			//PREPARE MARKET CROSS TAB QUERY
			// FOR USE MARKET ID FOR MANIPULATE RANK;
			$query = "SELECT  " . $this->settingVars->skutable . ".stage AS STAGE, "
					. $this->settingVars->skutable . ".type AS TYPE,"
					. $this->settingVars->skutable . ".upc_nielsen AS UPC_CORRECT,"
					. $this->settingVars->skutable . ".sku_mjn AS SKU_MJN,"
					. $this->settingVars->skutable . ".mfg_short AS MFG_SHORT," .
					implode(",", $selectPart) . " " .
					"FROM " . $this->settingVars->table_name . $this->queryPart . " AND " . $this->settingVars->skutable . "." . $flagCondition . " " .
					"GROUP BY STAGE,TYPE,UPC_CORRECT, SKU_MJN, MFG_SHORT " .
					"HAVING " . implode(" AND ", $havingPart);
	        //echo "<br><br>".$query;
			$result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);

			$accumulatedShare = array();
			//$finalArray = array();
			$marketIndex = 1;
			
			foreach ($_REQUEST['marketID'] as $marketKey => $currentMarketID) {
				
				if(is_array($result) && !empty($result))
				{
					//SORT DATA ARRAY ON CURRENT MARKET ID
					$result = utils\SortUtility::sort2DArray($result, 'SALES_TY_' . $marketIndex, utils\SortTypes::$SORT_DESCENDING);

					// PREPARE THE PARTS ONLY FOR THE SUPPLIED MARKET IDS  
					if (!empty($currentMarketID) && $currentMarketID != "") {

						$totalSalesTy = 0;
						$totalSalesLy = 0;
						$totalShare = 0;
						$totalCumShare = 0;
						$totalVarPct = 0;
						$totalAvAc = 0;
						$totalSppd = 0;

						foreach ($result as $key => $data) {

							$keyIndex 					= $data['UPC_CORRECT'] . "_" . $data['SKU_MJN'] . "_" . $data['MFG_SHORT'];
		//                     echo $key . "  " . $data['SALES_TY_' . $marketIndex]. " " . $keyIndex . "<br/>";
							//ASSIGN RANK
							$variance 					 	 = $data['SALES_TY_' . $marketIndex] - $data['SALES_LY_' . $marketIndex];
							$data['VAR_PCT_' . $marketIndex] = $data['SALES_LY_' . $marketIndex] != 0 ? ($variance / $data['SALES_LY_' . $marketIndex]) * 100 : 0;

							//CALCULATE SPPD [SALES_TY/DIST_TY]
							$data['SPPD_' . $marketIndex] = $data['DIST_TY_' . $marketIndex] != 0 ? $data['SALES_TY_' . $marketIndex] / $data['DIST_TY_' . $marketIndex] : 0;

							// CALCULATE SHARE [SALES_52_TY/TOTAL_SALES]
							$share 						= $totalSalesArray[0]['TOTAL_' . $marketIndex] != 0 ? ($data['SALES_TY_' . $marketIndex] / $totalSalesArray[0]['TOTAL_' . $marketIndex]) * 100 : 0;

							// CALCULATE SHARE [SALES_52_LY/TOTAL_SALES]
							$shareLY 					= $totalSalesArray[0]['TOTAL_' . $marketIndex] != 0 ? ($data['SALES_LY_' . $marketIndex] / $totalSalesArray[0]['TOTAL_' . $marketIndex]) * 100 : 0;

							// CALCULATE SHARE $share - $shareLY
							$shareVar = $share - $shareLY;

							$accumulatedShare[$marketIndex] 	+=$data['SHARE_' . $marketIndex] = number_format($share, 2, '.', ',');
							$data['CUM_SHARE_' . $marketIndex] 	= $accumulatedShare[$marketIndex];

							$finalArray[$keyIndex]['UPC'] 						= $data['UPC_CORRECT'];
							$finalArray[$keyIndex]['MJN'] 						= $data['SKU_MJN'];
							$finalArray[$keyIndex]['SHORT'] 					= $data['MFG_SHORT'];
							$finalArray[$keyIndex]['TYPE'] 						= $data['TYPE'];
							$finalArray[$keyIndex]['STAGE'] 					= $data['STAGE'];
							$finalArray[$keyIndex]['RANK_' . $marketIndex] 		= $key + 1;
							$finalArray[$keyIndex]['SALES_TY' . $marketIndex] 	= number_format($data['SALES_TY_' . $marketIndex], '0', '.', ',');
							$finalArray[$keyIndex]['SALES_LY' . $marketIndex] 	= number_format($data['SALES_LY_' . $marketIndex], '0', '.', ',');
							$finalArray[$keyIndex]['SHARE_' . $marketIndex] 	= number_format($share, 2, '.', ',');
							$finalArray[$keyIndex]['SHRCHG_' . $marketIndex] 	= number_format($shareVar, 2, '.', ',');
							$finalArray[$keyIndex]['CUM_SHARE' . $marketIndex]	= number_format($data['CUM_SHARE_' . $marketIndex], 1, '.', ',');
							$finalArray[$keyIndex]['VAR_' . $marketIndex] 		= number_format($data['VAR_PCT_' . $marketIndex], 1, '.', ',');
							$finalArray[$keyIndex]['AV_AC' . $marketIndex] 		= $data['DIST_TY_' . $marketIndex];
							$finalArray[$keyIndex]['SPPD_' . $marketIndex] 		= number_format($data['SPPD_' . $marketIndex], 2, '.', ',');

						}
					}
				}
				$marketIndex++;
			}

			$finalArray = utils\SortUtility::sort2DArray($finalArray, 'RANK_1', utils\SortTypes::$SORT_ASCENDING);
			
			if ($_REQUEST['action'] == "downloadXlsx") {
				$this->customXlsx($_REQUEST['marketID'], $finalArray, $jsonTagName);
			}

			$finalArray = utils\SortUtility::sort2DArray($finalArray, 'RANK_1', utils\SortTypes::$SORT_DESCENDING);
			
			if(isset($finalArray) && !empty($finalArray))
			{
				$chartArray1 = array("color"=>"#33cc33","key"=>"Group 0");
				$chartArray2 = array("color"=>"#FF0000","key"=>"Group 1");
				$chartArray3 = array("color"=>"#FF0000","key"=>"Group 2");
				$chartArray4 = array("color"=>"#FF0000","key"=>"Group 3");
				foreach($finalArray as $key=>$data)
				{	
					$randomFloat = rand(0, 10) / 10;
					if($key < 50){
						//Draw a graph
						if($data['VAR_1'] >= 0 && $data['VAR_2'] >= 0)
						{
							$chartArray1['values'][]	= array("series"=>0,"size"=>$randomFloat,"shape"=>"cross","x"=>(float)$data['VAR_2'],"y"=>(float)$data['VAR_1']);
						}
						else if($data['VAR_1'] <= 0 && $data['VAR_2'] <= 0)
						{
							$chartArray2['values'][]	= array("series"=>0,"size"=>$randomFloat,"shape"=>"cross","x"=>(float)$data['VAR_2'],"y"=>(float)$data['VAR_1']);
						}
						else if($data['VAR_1'] >= 0 && $data['VAR_2'] <= 0)
						{
							$chartArray3['values'][]	= array("series"=>0,"size"=>$randomFloat,"shape"=>"triangle-up","x"=>(float)$data['VAR_2'],"y"=>(float)$data['VAR_1']);
						}
						else if($data['VAR_1'] <= 0 && $data['VAR_2'] >= 0)
						{
							$chartArray4['values'][]	= array("series"=>0,"size"=>$randomFloat,"shape"=>"triangle-down","x"=>(float)$data['VAR_2'],"y"=>(float)$data['VAR_1']);
						}
						/* else
						{
							$chartArray1['values'][]	= array("series"=>0,"shape"=>"circle","size"=>6.6,"x"=>(float)0.0,"y"=>(float)0.0);
							$chartArray2['values'][]	= array("series"=>0,"shape"=>"square","size"=>6.6,"x"=>(float)0.0,"y"=>(float)0.0);
							$chartArray3['values'][]	= array("series"=>0,"shape"=>"diamond","size"=>6.6,"x"=>(float)0.0,"y"=>(float)0.0);
							$chartArray4['values'][]	= array("series"=>0,"shape"=>"triangle-up","size"=>6.6,"x"=>(float)0.0,"y"=>(float)0.0);
						} */
					}
				}				
			}
			
			if(!empty($chartArray1['values']))
				$allChartDataArr[] = $chartArray1;			
			if(!empty($chartArray2['values']))
				$allChartDataArr[] = $chartArray2;
			if(!empty($chartArray3['values']))
				$allChartDataArr[] = $chartArray3;
			if(!empty($chartArray4['values']))
				$allChartDataArr[] = $chartArray4;
		}		
		//if(!empty($chartArray1['values']) || !empty($chartArray2['values']) || !empty($chartArray3['values']) || !empty($chartArray4['values']))
			//$allChartDataArr = array($chartArray1,$chartArray2,$chartArray3,$chartArray4);
		//print("<pre>");print_r($allChartDataArr);
        $this->jsonOutput[$jsonTagName] = $finalArray;
        $this->jsonOutput[$jsonTagName.'_Chart'] = $allChartDataArr;
    }

    private function customXlsx($mainHeading, $dataArray, $tagName) {
        /** Error reporting */
//        error_reporting(E_ALL);
        ini_set('display_errors', TRUE);
        ini_set('display_startup_errors', TRUE);
        date_default_timezone_set('Europe/London');

        define('EOL', (PHP_SAPI == 'cli') ? PHP_EOL : '<br />');

        //-- Include PHPExcel

        $excelLibPath = $_SERVER['DOCUMENT_ROOT'];
        $excelLibPath .= "/ppt/Classes/PHPExcel.php";
        include_once $excelLibPath;

        //-- Create new PHPExcel object
        global $objPHPExcel;
        $objPHPExcel = new \PHPExcel();

        //-- Set document properties
        $objPHPExcel->getProperties()->setCreator("Byteshake Limited")
                ->setTitle("Product KBI Export File")
                ->setSubject("Product KBI Export File");
        $newWorkSheet = new \PHPExcel_Worksheet($objPHPExcel, "Summary");
        $objPHPExcel->addSheet($newWorkSheet, 0);
        $objPHPExcel->setActiveSheetIndex(0);


        $sheet = $objPHPExcel->getActiveSheet();
        $documentStartColumn = "A";
        $documentEndColumn = "A";
        $documentStartRow = 1;
        $document_currentColumn = $documentStartColumn;
        $currentRow = $documentStartRow;

        // XL BODY START

        if ($tagName != "itemsRankingReportsWithoutFlag") {
            $colNumber = 3;

            // SET COLOR
            $sheet->getStyle("A1")->applyFromArray(
                    array
                        (
                        'fill' => array
                            (
                            'type' => \PHPExcel_Style_Fill::FILL_SOLID,
                            'color' => array('argb' => 'FFFFFF')
                        )
                    )
            );
            // SET MARKET 1 HEADING COLOR
            $sheet->getStyle("B1:D1")->applyFromArray(
                    array
                        (
                        'fill' => array
                            (
                            'type' => \PHPExcel_Style_Fill::FILL_SOLID,
                            'color' => array('argb' => 'FFFACE')
                        )
                    )
            );

            // SET MARKET 1 HEADING
            $sheet->setCellValue("B1", $mainHeading[0]);
            $sheet->setCellValue("A2", "SKU");
            $sheet->setCellValue("B2", "SALES TY");
            $sheet->setCellValue("C2", "VAR %");
            $sheet->setCellValue("D2", "AV AC");

            if (!empty($mainHeading[1])) {
                $sheet->getStyle("E1:G1")->applyFromArray(
                        array
                            (
                            'fill' => array
                                (
                                'type' => \PHPExcel_Style_Fill::FILL_SOLID,
                                'color' => array('argb' => 'CED3FF')
                            )
                        )
                );
                // SET MARKET 2 HEADING
                $sheet->setCellValue("E1", $mainHeading[1]);
                $sheet->setCellValue("E2", "SALES TY");
                $sheet->setCellValue("F2", "VAR %");
                $sheet->setCellValue("G2", "AV AC");
            }
			
			if(is_array($dataArray) && !empty($dataArray))
			{
				foreach ($dataArray as $value) {
					$sheet->setCellValue('A' . $colNumber, $value['MJN']);
					$sheet->setCellValue('B' . $colNumber, $value['SALES_TY1']);
					$sheet->setCellValue('C' . $colNumber, $value['VAR_1']);
					$sheet->setCellValue('D' . $colNumber, $value['AV_AC1']);
					if (!empty($mainHeading[1])) {
						$sheet->setCellValue('E' . $colNumber, $value['SALES_TY2']);
						$sheet->setCellValue('F' . $colNumber, $value['VAR_2']);
						$sheet->setCellValue('G' . $colNumber, $value['AV_AC2']);
					}
					$colNumber++;
				}
            }
        } else {
            $colNumber = 3;

            // SET COLOR
            $sheet->getStyle("A1:E1")->applyFromArray(
                    array
                        (
                        'fill' => array
                            (
                            'type' => \PHPExcel_Style_Fill::FILL_SOLID,
                            'color' => array('argb' => 'FFFFFF')
                        )
                    )
            );
            // SET MARKET 1 HEADING COLOR
            $sheet->getStyle("F1:M1")->applyFromArray(
                    array
                        (
                        'fill' => array
                            (
                            'type' => \PHPExcel_Style_Fill::FILL_SOLID,
                            'color' => array('argb' => 'FFFACE')
                        )
                    )
            );

            // SET MARKET 1 HEADING
            $sheet->setCellValue("J1", $mainHeading[0]);
            $sheet->setCellValue("A2", "UPC");
            $sheet->setCellValue("B2", "SKU");
            $sheet->setCellValue("C2", "MFG");
            $sheet->setCellValue("D2", "TYPE");
            $sheet->setCellValue("E2", "STAGE");

            $sheet->setCellValue("F2", "RANK");
            $sheet->setCellValue("G2", "SALES TY");
            $sheet->setCellValue("H2", "SHARE %");
            $sheet->setCellValue("I2", "SHR % CHG");
            $sheet->setCellValue("J2", "CUM SHARE");
            $sheet->setCellValue("K2", "VAR %");
            $sheet->setCellValue("L2", "AV AC");
            $sheet->setCellValue("M2", "SPPD");

            if (!empty($mainHeading[1])) {
                $sheet->getStyle("N1:U1")->applyFromArray(
                        array
                            (
                            'fill' => array
                                (
                                'type' => \PHPExcel_Style_Fill::FILL_SOLID,
                                'color' => array('argb' => 'CED3FF')
                            )
                        )
                );
                // SET MARKET 2 HEADING
                $sheet->setCellValue("Q1", $mainHeading[1]);
                $sheet->setCellValue("N2", "RANK");
                $sheet->setCellValue("O2", "SALES TY");
                $sheet->setCellValue("P2", "SHARE %");
                $sheet->setCellValue("Q2", "SHR % CHG");
                $sheet->setCellValue("R2", "CUM SHARE");
                $sheet->setCellValue("S2", "VAR %");
                $sheet->setCellValue("T2", "AV AC");
                $sheet->setCellValue("U2", "SPPD");
            }
			
			if(is_array($dataArray) && !empty($dataArray))
			{
				foreach ($dataArray as $value) {
					$sheet->setCellValue('A' . $colNumber, $value['UPC']);
					$sheet->setCellValue('B' . $colNumber, $value['MJN']);
					$sheet->setCellValue('C' . $colNumber, $value['MFG']);
					$sheet->setCellValue('D' . $colNumber, $value['TYPE']);
					$sheet->setCellValue('E' . $colNumber, $value['STAGE']);

					$sheet->setCellValue('F' . $colNumber, $value['RANK_1']);
					$sheet->setCellValue('G' . $colNumber, $value['SALES_TY1']);
					$sheet->setCellValue('H' . $colNumber, $value['SHARE_1']);
					$sheet->setCellValue('I' . $colNumber, $value['SHRCHG_1']);
					$sheet->setCellValue('J' . $colNumber, $value['CUM_SHARE1']);
					$sheet->setCellValue('K' . $colNumber, $value['VAR_1']);
					$sheet->setCellValue('L' . $colNumber, $value['AV_AC1']);
					$sheet->setCellValue('M' . $colNumber, $value['SPPD_1']);
					if (!empty($mainHeading[1])) {
						$sheet->setCellValue('N' . $colNumber, $value['RANK_2']);
						$sheet->setCellValue('O' . $colNumber, $value['SALES_TY2']);
						$sheet->setCellValue('P' . $colNumber, $value['SHARE_2']);
						$sheet->setCellValue('Q' . $colNumber, $value['SHRCHG_2']);
						$sheet->setCellValue('R' . $colNumber, $value['CUM_SHARE2']);
						$sheet->setCellValue('S' . $colNumber, $value['VAR_2']);
						$sheet->setCellValue('T' . $colNumber, $value['AV_AC2']);
						$sheet->setCellValue('U' . $colNumber, $value['SPPD_2']);
					}
					$colNumber++;
				}
			}
        }

        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');

        $fileName = $tagName . "_" . md5(time()) . ".xlsx";
        $savePath = $_SERVER['DOCUMENT_ROOT'];
        $savePath .= "/zip";
        chdir($savePath);
        $objWriter->save($fileName);
        $callEndTime = microtime(true);
        if (file_exists($fileName)) {
            header('Content-Disposition: attachment; filename=' . $fileName);
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Length: ' . filesize($fileName));
            header('Content-Transfer-Encoding: binary');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            readfile($fileName);
        }
        unlink($fileName);
        exit;
    }

}

?>