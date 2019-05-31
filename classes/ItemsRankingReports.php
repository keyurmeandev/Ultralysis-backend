<?php

namespace classes;

use projectsettings;
use db;
use config;
use utils;
use filters;
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
        unset($_REQUEST["FSG"]);
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES
        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll
        
        $this->settingVars->pageName = (empty($this->settingVars->pageName) && !empty($this->settingVars->pageID)) ? $this->settingVars->pageID.'_ItemsRankingReportsPage' : $this->settingVars->pageName;

        if(isset($_REQUEST['marketID']))
        {
            foreach($_REQUEST['marketID'] as $key => $data)
                $_REQUEST['marketID'][$key] = rawurldecode($data);
        }

        if(isset($_REQUEST['marketLabel']))
        {
            foreach($_REQUEST['marketLabel'] as $key => $data)
                $_REQUEST['marketLabel'][$key] = rawurldecode($data);
        }
        
        if ($this->settingVars->isDynamicPage) {
            $this->skuField = $this->getPageConfiguration('sku_field', $this->settingVars->pageID)[0];
            $this->accountFields = $this->getPageConfiguration('account_list_field', $this->settingVars->pageID);
            $this->showSegmentGrid = $this->getPageConfiguration('show_segment_grid', $this->settingVars->pageID)[0];
            $this->showSegmentChart = $this->getPageConfiguration('show_segment_chart', $this->settingVars->pageID)[0];
            $this->showTopGrid = $this->getPageConfiguration('show_top_grid', $this->settingVars->pageID)[0];
            $this->tableField = $this->getPageConfiguration('table_field', $this->settingVars->pageID)[0];
            $this->defaultSelectedField = $this->getPageConfiguration('table_settings_default_field', $this->settingVars->pageID)[0];

            $tempBuildFieldsArray = array($this->storeField,$this->skuField);
            if($this->defaultSelectedField)
                $tempBuildFieldsArray[] = $this->defaultSelectedField;
                
            if(is_array($this->accountFields) && !empty($this->accountFields))
                $tempBuildFieldsArray = array_merge($tempBuildFieldsArray, $this->accountFields);

            $buildDataFields = array();
            foreach ($tempBuildFieldsArray as $value)
                if (!empty($value) && !in_array($value, $buildDataFields))
                    $buildDataFields[] = $value;
            
            
            /*$pageBasedPaginationSettingCount = $this->getPageConfiguration('page_based_pagination_setting_count', $this->settingVars->pageID);
            if(!empty($pageBasedPaginationSettingCount) && isset($pageBasedPaginationSettingCount[0]))
                $this->jsonOutput['pageBasedPaginationSettingCount'] = (int) $pageBasedPaginationSettingCount[0];

            $pageBasedNoPaginationRowCount = $this->getPageConfiguration('page_based_no_pagination_row_count', $this->settingVars->pageID);
            if(!empty($pageBasedNoPaginationRowCount) && isset($pageBasedNoPaginationRowCount[0]))
                $this->jsonOutput['pageBasedNoPaginationRowCount'] = (int) $pageBasedNoPaginationRowCount[0];*/
            if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
                $pagination_settings_arr = $this->getPageConfiguration('pagination_settings', $this->settingVars->pageID);
                if(count($pagination_settings_arr) > 0){
                    $this->jsonOutput['is_pagination'] = $pagination_settings_arr[0];
                    $this->jsonOutput['per_page_row_count'] = (int) $pagination_settings_arr[1];
                }
            }

            $this->buildDataArray($buildDataFields);
            $this->buildPageArray();
        } else {
            $this->configurationFailureMessage();
        }

        $this->controlFlagField = (isset($this->settingVars->controlFlagField) && !empty($this->settingVars->controlFlagField)) ? $this->settingVars->controlFlagField : "product.pl";

        switch ($_REQUEST['action']) {
            case "gridData":
                $this->prepareData();
                break;

            case "downloadXlsx":
                $this->downloadXlsx();
                break;
                
            case "changeFieldValues":
                $this->prepareMarketCrossTabGrid($this->controlFlagField." = 0 ", "topGridData", true);
                break;
        }   
		
        return $this->jsonOutput;
    }

    private function prepareData() {
        $this->prepareSummaryGrid();
        if($this->showSegmentGrid === "true" || $this->showSegmentChart === "true" ){
            
            $controlFlgCondition1 = $this->controlFlagField." = 0 ";
            $controlFlgCondition2 = $this->controlFlagField." <> 0 ";

            if(isset($this->settingVars->pageArray) && isset($this->settingVars->pageArray['ItemsRankingReports']) && isset($this->settingVars->pageArray['ItemsRankingReports']['itemsRankingReportsWithoutFlag']) && isset($this->settingVars->pageArray['ItemsRankingReports']['segmentRankingReportGridData'])) {

    	        $controlFlgCondition1 = $this->settingVars->pageArray['ItemsRankingReports']['itemsRankingReportsWithoutFlag']['Where'];
                $controlFlgCondition2 = $this->settingVars->pageArray['ItemsRankingReports']['segmentRankingReportGridData']['Where'];
            }

            $this->prepareMarketCrossTabGrid($controlFlgCondition1, "itemsRankingReportsWithoutFlag");
            $this->prepareMarketCrossTabGrid($controlFlgCondition2, "segmentRankingReportGridData");  // flag = 1 change for check
    	} else {
	        $this->prepareMarketCrossTabGrid("", "itemsRankingReportsWithoutFlag");
    	}
        
        if(isset($_REQUEST['selectedField']) && $_REQUEST['selectedField'] != "")
            $this->prepareMarketCrossTabGrid($this->controlFlagField." = 0 ", "topGridData", true);
        
    }

    function downloadXlsx() {
        if ($_REQUEST['gridTag'] == "itemsRanking") {
        	if($this->showSegmentGrid === "true" || $this->showSegmentChart === "true" ){

                $controlFlgCondition1 = $this->controlFlagField." = 0 ";
                if(isset($this->settingVars->pageArray) && isset($this->settingVars->pageArray['ItemsRankingReports']) && isset($this->settingVars->pageArray['ItemsRankingReports']['itemsRankingReportsWithoutFlag']))
                    $controlFlgCondition1 = $this->settingVars->pageArray['ItemsRankingReports']['itemsRankingReportsWithoutFlag']['Where'];

            	$this->prepareMarketCrossTabGrid($controlFlgCondition1, "itemsRankingReportsWithoutFlag");
            }
            else{
            	$this->prepareMarketCrossTabGrid("", "itemsRankingReportsWithoutFlag");
            }
        } elseif($_REQUEST['gridTag'] == "topGrid"){
            $this->prepareMarketCrossTabGrid($this->controlFlagField." = 0", "itemsRankingReportsWithoutFlag", true);
        }
        else {
            $controlFlgCondition2 = $this->controlFlagField." <> 0";
            if(isset($this->settingVars->pageArray) && isset($this->settingVars->pageArray['ItemsRankingReports']) && isset($this->settingVars->pageArray['ItemsRankingReports']['segmentRankingReportGridData']))
                $controlFlgCondition2 = $this->settingVars->pageArray['ItemsRankingReports']['segmentRankingReportGridData']['Where'];
        
            $this->prepareMarketCrossTabGrid($controlFlgCondition2, "segmentRankingReportGridData");
        }
    }

    function prepareSummaryGrid() {
		$summaryGridData = array();

        $performanceBoxSettings = array(
            array(
                'timeFrame' => 4,
                'title' => 'LAST 4 WEEK VS LY',
                'alias' => 'L4'
            ),
            array(
                'timeFrame' => 12,
                'title' => 'LAST 12 WEEK VS LY',
                'alias' => 'L12'
            ),
            array(
                'timeFrame' => 'YTD',
                'title' => 'YEAR TO DATE VS LY',
                'alias' => 'YTD'
            ),
            array(
                'timeFrame' => 52,
                'title' => 'LAST 52 WEEK VS LY',
                'alias' => 'L52'
            ),
        );

		$this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $this->measureFields[] = $this->storeIDField;

        $projectStructureTypePage = "projectstructure\\".$this->settingVars->projectStructureType."_PAGES";
        $structurePageClass = new $projectStructureTypePage();
        $extraWhereClauseForTopBoxes = $structurePageClass->extraWhereClauseForTopBoxes($this->settingVars, $this->measureFields);

        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }

        $queryPart = $this->getAll().$extraWhereClauseForTopBoxes; //PREPARE TABLE JOINING STRINGS USING PARENT getAll
        
		
		if(is_array($_REQUEST['marketID']) && !empty($_REQUEST['marketID']))
		{
	        $details = $structurePageClass->itemRankingReportTopBoxesLogic($this->settingVars, $this->queryVars, $this->storeIDField, $_REQUEST['marketID'], $performanceBoxSettings);

			$havingPart = (isset($details['havingPart']) && !empty($details['havingPart'])) ? $details['havingPart'] : array();
			$queryPart .= (isset($details['queryPart']) && !empty($details['queryPart'])) ? $details['queryPart'] : '';
			$measureSelect = (isset($details['measureSelect']) && !empty($details['measureSelect'])) ? $details['measureSelect'] : array();
			$measureSelect = implode(", ", $measureSelect);

			$query = "SELECT " . $measureSelect . " " .
					"FROM " . $this->settingVars->tablename . $queryPart . " ".
					((is_array($havingPart) && !empty($havingPart)) ? ("HAVING " . implode(" OR ", $havingPart)) : "");
			// echo $query;exit;
			$result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);

            //print_r($result);exit;

			foreach ($_REQUEST['marketID'] as $marketIndex => $currentMarketID) {
				if(is_array($result) && !empty($result))
				{
					$rowData = $result[0];

                        foreach ($performanceBoxSettings as $boxSetting) {
                            $temp = array();

                            $temp['title'] = $boxSetting['title'];
                            $temp['SALES_TY_VS'] = number_format(($rowData['SALES_'.$boxSetting['alias'].'_TY_'.$marketIndex] - $rowData['SALES_'.$boxSetting['alias'].'_LY_'.$marketIndex]),0,'.',',');
                            $temp['SALES_TY_VAR'] = (($rowData['SALES_'.$boxSetting['alias'].'_LY_'.$marketIndex] != 0) ? number_format( ((($rowData['SALES_'.$boxSetting['alias'].'_TY_'.$marketIndex] - $rowData['SALES_'.$boxSetting['alias'].'_LY_'.$marketIndex])/$rowData['SALES_'.$boxSetting['alias'].'_LY_'.$marketIndex])*100), 1, '.' , ',' ) : 0);
                            $temp['SALES_TY'] = number_format($rowData['SALES_'.$boxSetting['alias'].'_TY_'.$marketIndex],0,'.',',');
                            $temp['SALES_LY'] = number_format($rowData['SALES_'.$boxSetting['alias'].'_LY_'.$marketIndex],0,'.',',');

                            if ($temp['SALES_TY_VAR'] >= 0) {
                                $temp['SALES_TY_VS'] = "+" . $temp['SALES_TY_VS'];
                                $temp['SALES_TY_VAR'] = "+" . $temp['SALES_TY_VAR'];
                            } else {
                                $temp['SALES_TY_VS'] = $temp['SALES_TY_VS'] > 0 ? "-" . $temp['SALES_TY_VS'] : $temp['SALES_TY_VS'];
                                $temp['SALES_TY_VAR'] = $temp['SALES_TY_VAR'] > 0 ? "-" . $temp['SALES_TY_VAR'] : $temp['SALES_TY_VAR'];
                            }

                            $summaryGridData[$currentMarketID][] = $temp;
                        }
					
				}
			}
		}
        
        $this->jsonOutput['itemsRankingReportsSummary'] = $summaryGridData;
    }

    private function prepareMarketCrossTabGrid($flagCondition, $jsonTagName, $isSelectedField = false) {
		
		// filters\timeFilter::getSlice($this->settingVars);
		
		$finalArray 		= $allChartDataArr 	= $selectPart 	= $options	= $selectPartForTotal = array();


		$flagCondition = (!empty($flagCondition)) ? " AND " . $flagCondition . " " : "";
		$this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $this->measureFields[] = $this->storeIDField;
        $this->measureFields[] = $this->controlFlagField;

        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }

        $queryPart = $this->getAll();
		
		if(is_array($_REQUEST['marketID']) && !empty($_REQUEST['marketID']))
		{
            $projectStructureTypePage = "projectstructure\\".$this->settingVars->projectStructureType."_PAGES";
	        $structurePageClass = new $projectStructureTypePage();
	        $details = $structurePageClass->itemRankingReportMarketGridLogic($this->settingVars, $this->queryVars, $this->storeIDField, $_REQUEST['marketID']);

			$havingPart = (isset($details['havingPart']) && !empty($details['havingPart'])) ? $details['havingPart'] : array();
			$queryPart .= (isset($details['queryPart']) && !empty($details['queryPart'])) ? $details['queryPart'] : '';
			$measureSelectForTotal = (isset($details['measureSelectForTotal']) && !empty($details['measureSelectForTotal'])) ? $details['measureSelectForTotal'] : array();
			$measureSelectForTotal = implode(", ", $measureSelectForTotal);
            $orderByPart = (isset($details['orderByPart']) && !empty($details['orderByPart'])) ? $details['orderByPart'] : array();

			//PREPARE TOTAL SALES QUERY
			$query = "SELECT " . $measureSelectForTotal . " " .
					" FROM " . $this->settingVars->tablename . $queryPart . $flagCondition;
			//echo "<br><br>".$query;
			$totalSalesArray = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);

            //print_r($totalSalesArray);

			$this->settingVars->tableUsedForQuery = $this->measureFields = array();
        	$this->measureFields[] = $this->storeIDField;
            $this->measureFields[] = $this->skuNameField;
        	$this->measureFields[] = $this->controlFlagField;

        	$groupByPart[] = "SKU_NAME";

			foreach ($this->accountsName as $key => $data) {
	                $this->measureFields[] = $this->settingVars->dataArray[$data]['NAME'];
	                $selectPart[] = $this->settingVars->dataArray[$data]['NAME'] . " AS '" . $this->settingVars->dataArray[$data]['NAME_ALIASE'] . "'";
	                $groupByPart[] = "'" . $this->settingVars->dataArray[$data]['NAME_ALIASE'] . "'";
	        }

	        $this->settingVars->useRequiredTablesOnly = true;
	        if (is_array($this->measureFields) && !empty($this->measureFields)) {
	            $this->prepareTablesUsedForQuery($this->measureFields);
	        }
	        $queryPart = $this->getAll();

	        $havingPart = (isset($details['havingPart']) && !empty($details['havingPart'])) ? $details['havingPart'] : array();
			$queryPart .= (isset($details['queryPart']) && !empty($details['queryPart'])) ? $details['queryPart'] : '';
	        $measureSelect = (isset($details['measureSelect']) && !empty($details['measureSelect'])) ? $details['measureSelect'] : array();
            $measureSelect = implode(", ", $measureSelect);

            $query = "SELECT  ".$this->skuNameField." as SKU_NAME, MAX($this->controlFlagField) AS CONTROLFLAG, ";
            
            if($isSelectedField)
            {
                $selectPart = $groupByPart = array();
                
                $selectedField = explode("#", $_REQUEST['selectedField']);
                
                $selectPart[] = $selectedField[0] ." AS ACCOUNT";
                $groupByPart [] = "ACCOUNT";
                $this->columnNames = array();
                $this->columnNames["ACCOUNT"] = $this->settingVars->dataArray[strtoupper($selectedField[0])]['NAME_CSV'];
                
                if(count($selectedField) > 1)
                {
                    $selectPart[] = $selectedField[1] ." AS ID";
                    $groupByPart [] = "ID";
                }
                
                $query = "SELECT ";
                
                if(count($selectedField) > 1)
                {
                    $TOP_GRID_FIELD_NAMES = array();
                    $this->columnNames["ACCOUNT"] = $TOP_GRID_FIELD_NAMES["ACCOUNT"] = $this->settingVars->dataArray[strtoupper($selectedField[0]."_".$selectedField[1])]['NAME_CSV'];
                    $this->columnNames["ID"] = $TOP_GRID_FIELD_NAMES["ID"] = $this->settingVars->dataArray[strtoupper($selectedField[0]."_".$selectedField[1])]["ID_CSV"];
                    
                    $this->jsonOutput['TOP_GRID_FIELD_NAMES'] = $TOP_GRID_FIELD_NAMES;
                }
            }
            
            $query .= implode(",", $selectPart) . " " .
                " , ".$measureSelect." " .
                " FROM " . $this->settingVars->tablename . $queryPart . $flagCondition .
                " GROUP BY " . implode(",", $groupByPart) .
                " HAVING " . implode(" AND ", $havingPart);

                
                if($this->showSegmentGrid === "true" || $this->showSegmentChart === "true" ){
                    $orderByField = '';
                    if(isset($this->settingVars->pageArray) && isset($this->settingVars->pageArray['ItemsRankingReports']) && isset($this->settingVars->pageArray['ItemsRankingReports'][$jsonTagName])) {
                        if($this->settingVars->pageArray['ItemsRankingReports'][$jsonTagName]['OrderBy']){
                            if(!empty($this->settingVars->pageArray['ItemsRankingReports'][$jsonTagName]['OrderByField'])){
                                $orderByField = $this->settingVars->pageArray['ItemsRankingReports'][$jsonTagName]['OrderByField'].' '.$this->settingVars->pageArray['ItemsRankingReports'][$jsonTagName]['OrderByType'];
                            }
                            else{
                                $orderByField = implode(' '.$this->settingVars->pageArray['ItemsRankingReports'][$jsonTagName]['OrderByType'].', ', $orderByPart).' '.$this->settingVars->pageArray['ItemsRankingReports'][$jsonTagName]['OrderByType'];
                            }
                        }
                    }
                    if(!empty($orderByField)){
                        $query .= ' ORDER BY '.$orderByField;
                    }
                }
			$result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);

			$accumulatedShare = array();
			//print_r($result);exit;
			
			foreach ($_REQUEST['marketID'] as $marketIndex => $currentMarketID) {
				
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

							$group = array();
							foreach ($groupByPart as $gPart) {
								$gPart = trim($gPart,"'");
								$group[] = $data[$gPart];							
							}

							$keyIndex = implode("_", $group);


							//$keyIndex 					= $data['UPC_CORRECT'] . "_" . $data['SKU_MJN'] . "_" . $data['MFG_SHORT'];
							//                     echo $key . "  " . $data['SALES_TY_' . $marketIndex]. " " . $keyIndex . "<br/>";
							//ASSIGN RANK
							$variance 					 	 = $data['SALES_TY_' . $marketIndex] - $data['SALES_LY_' . $marketIndex];
							$data['VAR_PCT_' . $marketIndex] = $data['SALES_LY_' . $marketIndex] != 0 ? ($variance / $data['SALES_LY_' . $marketIndex]) * 100 : 0;

							//CALCULATE SPPD [SALES_TY/DIST_TY]
							//$data['SPPD_' . $marketIndex] = $data['DIST_TY_' . $marketIndex] != 0 ? $data['SALES_TY_' . $marketIndex] / $data['DIST_TY_' . $marketIndex] : 0;

							// CALCULATE SHARE [SALES_52_TY/TOTAL_SALES]
							$share 						= $totalSalesArray[0]['TOTAL_' . $marketIndex] != 0 ? ($data['SALES_TY_' . $marketIndex] / $totalSalesArray[0]['TOTAL_' . $marketIndex]) * 100 : 0;

							// CALCULATE SHARE [SALES_52_LY/TOTAL_SALES]
							$shareLY 					= $totalSalesArray[0]['TOTAL_' . $marketIndex] != 0 ? ($data['SALES_LY_' . $marketIndex] / $totalSalesArray[0]['TOTAL_' . $marketIndex]) * 100 : 0;

							// CALCULATE SHARE $share - $shareLY
							$shareVar = $share - $shareLY;

							$accumulatedShare[$marketIndex] 	+= $data['SHARE_' . $marketIndex] = (float) $share;
							$data['CUM_SHARE_' . $marketIndex] 	= $accumulatedShare[$marketIndex];

							foreach ($groupByPart as $gPart) {
								$gPart = trim($gPart,"'");
								$finalArray[$keyIndex][$gPart] = $data[$gPart];
							}

                            if ($_REQUEST['action'] == "downloadXlsx") {
    							$finalArray[$keyIndex]['RANK_' . $marketIndex] 		= $key + 1;
    							$finalArray[$keyIndex]['SALES_TY_' . $marketIndex] 	= number_format($data['SALES_TY_' . $marketIndex], '0', '.', ',');
    							$finalArray[$keyIndex]['SALES_LY_' . $marketIndex] 	= number_format($data['SALES_LY_' . $marketIndex], '0', '.', ',');
    							$finalArray[$keyIndex]['SHARE_' . $marketIndex] 	= number_format($share, 1, '.', '');
    							$finalArray[$keyIndex]['SHRCHG_' . $marketIndex] 	= number_format($shareVar, 1, '.', '');
    							$finalArray[$keyIndex]['CUM_SHARE_' . $marketIndex]	= number_format($data['CUM_SHARE_' . $marketIndex], 1, '.', ',');
    							$finalArray[$keyIndex]['VAR_' . $marketIndex] 		= number_format($data['VAR_PCT_' . $marketIndex], 1, '.', ',');
    							$finalArray[$keyIndex]['VARIANCE_' . $marketIndex]  = number_format($variance, 0, '.', ',');
                            } else {
                                $finalArray[$keyIndex]['RANK_' . $marketIndex]      = $key + 1;
                                $finalArray[$keyIndex]['SALES_TY_' . $marketIndex]  = (float) $data['SALES_TY_' . $marketIndex];
                                $finalArray[$keyIndex]['SALES_LY_' . $marketIndex]  = (float) $data['SALES_LY_' . $marketIndex];
                                $finalArray[$keyIndex]['SHARE_' . $marketIndex]     = (float) $share;
                                $finalArray[$keyIndex]['SHRCHG_' . $marketIndex]    = (float) $shareVar;
                                $finalArray[$keyIndex]['CUM_SHARE_' . $marketIndex] = (float) $data['CUM_SHARE_' . $marketIndex];
                                $finalArray[$keyIndex]['VAR_' . $marketIndex]       = (float) $data['VAR_PCT_' . $marketIndex];
                                $finalArray[$keyIndex]['VARIANCE_' . $marketIndex]  = (float) $variance;
                            }
						}
					}
				}
			}
			
			if ($_REQUEST['action'] == "downloadXlsx") {
				$finalArray = utils\SortUtility::sort2DArray($finalArray, 'RANK_0', utils\SortTypes::$SORT_ASCENDING);
                
                if($_REQUEST['gridTag'] == "topGrid")
                {
                    
                    $jsonTagName = "itemsRankingReportsWithoutFlag";
                }
                
				$this->customXlsx($_REQUEST['marketLabel'], $finalArray, $jsonTagName);
			}else{

				$finalArray = utils\SortUtility::sort2DArray($finalArray, 'RANK_0', utils\SortTypes::$SORT_DESCENDING);
				
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
							if($data['VAR_0'] >= 0 && $data['VAR_1'] >= 0)
							{
								$chartArray1['values'][]	= array("series"=>0, "SKU_NAME" => $data['SKU_NAME'], "size"=>$randomFloat, "shape"=>"cross", "x"=>(float)$data['VAR_1'], "y"=>(float)$data['VAR_0']);
							}
							else if($data['VAR_0'] <= 0 && $data['VAR_1'] <= 0)
							{
								$chartArray2['values'][]	= array("series"=>0, "SKU_NAME" => $data['SKU_NAME'], "size"=>$randomFloat, "shape"=>"cross", "x"=>(float)$data['VAR_1'], "y"=>(float)$data['VAR_0']);
							}
							else if($data['VAR_0'] >= 0 && $data['VAR_1'] <= 0)
							{
								$chartArray3['values'][]	= array("series"=>0, "SKU_NAME" => $data['SKU_NAME'], "size"=>$randomFloat, "shape"=>"triangle-up", "x"=>(float)$data['VAR_1'], "y"=>(float)$data['VAR_0']);
							}
							else if($data['VAR_0'] <= 0 && $data['VAR_1'] >= 0)
							{
								$chartArray4['values'][]	= array("series"=>0, "SKU_NAME" => $data['SKU_NAME'], "size"=>$randomFloat, "shape"=>"triangle-down", "x"=>(float)$data['VAR_1'], "y"=>(float)$data['VAR_0']);
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
		}		
		
		if ($_REQUEST['action'] != "downloadXlsx") {
        	$this->jsonOutput[$jsonTagName] = $finalArray;
            if(!$isSelectedField)
                $this->jsonOutput[$jsonTagName.'_Chart'] = $allChartDataArr;
    	}
    }

    private function customXlsx($mainHeading, $dataArray, $tagName) {
        /** Error reporting */
		//error_reporting(E_ALL);
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
        

        $primaryMarketStyle = array(
            'fill' => array
                (
                'type' => \PHPExcel_Style_Fill::FILL_SOLID,
                'color' => array('argb' => 'FFFACE')
            )
        );

        $secondaryMarketStyle = array(
            'fill' => array
                (
                'type' => \PHPExcel_Style_Fill::FILL_SOLID,
                'color' => array('argb' => 'CED3FF')
            )
        );

        // XL BODY START


        if ($tagName != "itemsRankingReportsWithoutFlag") {

        	$headerCols = array(
	        	"SALES_TY" => "SALES TY",
	        	"VARIANCE" => "VAR",
	        	"VAR" => "VAR %",
	        );

	        $headerRow = 1;
            $col = 1;

            $midCol = $col + (count($headerCols)/2) - 1;

            $cell = $sheet->getCellByColumnAndRow($midCol,$headerRow)->getColumn().$headerRow;
	        $sheet->setCellValue($cell, rawurldecode($mainHeading[0]));

            foreach ($headerCols as $key => $headerVal) {
            	$cell = $sheet->getCellByColumnAndRow($col,$headerRow)->getColumn().$headerRow;
            	$sheet->getStyle($cell)->applyFromArray($primaryMarketStyle);
	            $col++;
	        }

	        if (!empty($mainHeading[1])) {
	        	$col = 1 + count($headerCols);

	            $midCol = $col + (count($headerCols)/2) - 1;

	            $cell = $sheet->getCellByColumnAndRow($midCol,$headerRow)->getColumn().$headerRow;
		        $sheet->setCellValue($cell, rawurldecode($mainHeading[1]));

	            foreach ($headerCols as $key => $headerVal) {
	            	$cell = $sheet->getCellByColumnAndRow($col,$headerRow)->getColumn().$headerRow;
	            	$sheet->getStyle($cell)->applyFromArray($secondaryMarketStyle);
		            $col++;
		        }
	        }


	        $headerRow = 2;
	        $col = 0;
	        
	        $sheet->setCellValueByColumnAndRow($col, $headerRow, $this->columnNames['SKU_NAME']);
	        $col++;
	        
	        foreach ($headerCols as $key => $headerVal) {
	            $sheet->setCellValueByColumnAndRow($col, $headerRow, $headerVal);
	            $col++;
	        }

	        if (!empty($mainHeading[1])) {
	        	foreach ($headerCols as $key => $headerVal) {
		            $sheet->setCellValueByColumnAndRow($col, $headerRow, $headerVal);
		            $col++;
		        }
	        }

           
			
			if(is_array($dataArray) && !empty($dataArray))
			{

				$cellAlignmentStyleRight = array(
                    'horizontal' => \PHPExcel_Style_Alignment::HORIZONTAL_RIGHT,
                    'vertical'   => \PHPExcel_Style_Alignment::VERTICAL_CENTER
                );

				$row = 3;
				foreach ($dataArray as $value) {
					$col = 0;

					
			        $sheet->setCellValueByColumnAndRow($col, $row, $value['SKU_NAME']);
			        $col++;
			        
			        /*foreach ($headerCols as $key => $headerVal) {
			            $sheet->setCellValueByColumnAndRow($col, $row, $value[$key."_0"]);
			            $col++;
			        }*/

			        foreach ($headerCols as $key => $headerVal) {
                        $cell = $sheet->getCellByColumnAndRow($col,$row)->getColumn().$row;
                        if($key == 'VAR'){
                            $value[$key."_0"] = (float) str_replace(',','', $value[$key."_0"]);
                            $sheet->getStyle($cell)->getNumberFormat()->setFormatCode('#,##0.0');
                        }else if($key == 'SALES_TY'|| $key == 'VARIANCE') {
                            $value[$key."_0"] = (float) str_replace(',','', $value[$key."_0"]);
                            $sheet->getStyle($cell)->getNumberFormat()->setFormatCode('#,##0');
                        }
                        $sheet->getStyle($cell)->getAlignment()->applyFromArray($cellAlignmentStyleRight);
                        $sheet->setCellValueByColumnAndRow($col, $row, $value[$key."_0"]);
                        $col++;
                    }

			        if (!empty($mainHeading[1])) {
			        	foreach ($headerCols as $key => $headerVal) {
                            $cell = $sheet->getCellByColumnAndRow($col,$row)->getColumn().$row;
                            if($key == 'VAR'){
                                $value[$key."_1"] = (float) str_replace(',','', $value[$key."_1"]);
                                $sheet->getStyle($cell)->getNumberFormat()->setFormatCode('#,##0.0');
                            }else if($key == 'SALES_TY' || $key == 'VARIANCE'){
                                $value[$key."_1"] = (float) str_replace(',','', $value[$key."_1"]);
                                $sheet->getStyle($cell)->getNumberFormat()->setFormatCode('#,##0');
                            }
                            $sheet->getStyle($cell)->getAlignment()->applyFromArray($cellAlignmentStyleRight);
				            $sheet->setCellValueByColumnAndRow($col, $row, $value[$key."_1"]);
				            $col++;
				        }
			        }

					$row++;
				}
            }
        } else {

            $headerCols = array(
	        	"RANK" => "RANK",
	        	"SALES_TY" => "SALES TY",
	        	"SHARE" => "SHARE %",
	        	"SHRCHG" => "SHR % CHG",
	        	"CUM_SHARE" => "CUM SHARE",
	        	"VAR" => "VAR %",
	        );

            $headerRow = 1;
            $col = count($this->columnNames);

            $midCol = $col + (count($headerCols)/2) - 1;

            $cell = $sheet->getCellByColumnAndRow($midCol,$headerRow)->getColumn().$headerRow;
	        $sheet->setCellValue($cell, rawurldecode($mainHeading[0]));

            foreach ($headerCols as $key => $headerVal) {
            	$cell = $sheet->getCellByColumnAndRow($col,$headerRow)->getColumn().$headerRow;
            	$sheet->getStyle($cell)->applyFromArray($primaryMarketStyle);
	            $col++;
	        }

	        if (!empty($mainHeading[1])) {
	        	$col = count($this->columnNames) + count($headerCols);

	            $midCol = $col + (count($headerCols)/2) - 1;

	            $cell = $sheet->getCellByColumnAndRow($midCol,$headerRow)->getColumn().$headerRow;
		        $sheet->setCellValue($cell, rawurldecode($mainHeading[1]));

	            foreach ($headerCols as $key => $headerVal) {
	            	$cell = $sheet->getCellByColumnAndRow($col,$headerRow)->getColumn().$headerRow;
	            	$sheet->getStyle($cell)->applyFromArray($secondaryMarketStyle);
		            $col++;
		        }
	        }


	        $headerRow = 2;
	        $col = 0;
	        foreach ($this->columnNames as $key => $headerVal) {
	            $sheet->setCellValueByColumnAndRow($col, $headerRow, $headerVal);
	            $col++;
	        }

	        foreach ($headerCols as $key => $headerVal) {
	            $sheet->setCellValueByColumnAndRow($col, $headerRow, $headerVal);
	            $col++;
	        }

	        if (!empty($mainHeading[1])) {
	        	foreach ($headerCols as $key => $headerVal) {
		            $sheet->setCellValueByColumnAndRow($col, $headerRow, $headerVal);
		            $col++;
		        }
	        }
           
			if(is_array($dataArray) && !empty($dataArray))
			{

				$cellAlignmentStyleRight = array(
					'horizontal' => \PHPExcel_Style_Alignment::HORIZONTAL_RIGHT,
					'vertical'   => \PHPExcel_Style_Alignment::VERTICAL_CENTER
				);

				$row = 3;
				foreach ($dataArray as $value) {
					$col = 0;
					foreach ($this->columnNames as $key => $headerVal) {
			            $sheet->setCellValueByColumnAndRow($col, $row, $value[$key]);
			            $col++;
			        }

			        foreach ($headerCols as $key => $headerVal) {
			        	$cell = $sheet->getCellByColumnAndRow($col,$row)->getColumn().$row;
			        	if($key == 'SHARE' || $key == 'SHRCHG' || $key == 'CUM_SHARE' || $key == 'VAR'){
			        		$value[$key."_0"] = (float) str_replace(',','', $value[$key."_0"]);
			        		//$value[$key."_0"] = number_format((float) $value[$key."_0"], 1, '.', ',');
			        		$sheet->getStyle($cell)->getNumberFormat()->setFormatCode('#,##0.0');
						}else if($key == 'SALES_TY'){
							$value[$key."_0"] = (float) str_replace(',','', $value[$key."_0"]);
							$sheet->getStyle($cell)->getNumberFormat()->setFormatCode('#,##0');
						}
			            $sheet->getStyle($cell)->getAlignment()->applyFromArray($cellAlignmentStyleRight);
			            $sheet->setCellValueByColumnAndRow($col, $row, $value[$key."_0"]);
			            $col++;
			        }

			        if (!empty($mainHeading[1])) {
			        	foreach ($headerCols as $key => $headerVal) {
				            $sheet->setCellValueByColumnAndRow($col, $row, $value[$key."_1"]);
				            $col++;
				        }
			        }

					$row++;
				}
			}
        }

        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');

        $fileName = $tagName . "_" . date("Y-m-d-h-i-s") . ".xlsx";
        $savePath = dirname(__FILE__)."/../uploads/itemRanking/";
        chdir($savePath);
        $objWriter->save($fileName);
        $this->deleteFiles($savePath);
        
        $this->jsonOutput['downloadLink'] = $this->settingVars->get_full_url()."/".basename(dirname(dirname(__FILE__)))."/uploads/itemRanking/".$fileName;
        
    }

    public function deleteFiles($dirPath){

    	if(empty($dirPath))
    		return;
        //$dirPath =  $_SERVER['DOCUMENT_ROOT'] . "/comphp/uploads/itemRanking/";

        $date = date('Y-m-d');
        $cdir = scandir($dirPath);
        foreach ($cdir as $key => $value)
        {
            if (!in_array($value,array(".","..")))
            {
                if (strpos($value, $date) === false ) {
                    unlink($dirPath.$value);
                }
            }
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

            $this->jsonOutput['showSegmentChart'] = ($this->showSegmentChart === "true") ? true : false;
            $this->jsonOutput['showSegmentGrid'] = ($this->showSegmentGrid === "true" ) ? true : false;

            if($this->showTopGrid === "true")
            {
                $this->jsonOutput['showTopGrid'] = true;
                $this->jsonOutput['tableField'] = ($this->tableField !== "") ? $this->tableField : $this->settingVars->skutable;
                $this->jsonOutput['defaultSelectedField'] = ($this->defaultSelectedField !== "") ? strtoupper($this->settingVars->dataArray[strtoupper($this->dbColumnsArray[$this->defaultSelectedField])]['NAME']) : "";
                
                $TOP_GRID_FIELD_NAMES = array();
                $TOP_GRID_FIELD_NAMES["ACCOUNT"] = $this->settingVars->dataArray[strtoupper($this->dbColumnsArray[$this->defaultSelectedField])]['NAME_CSV'];
                
                if(key_exists('ID', $this->settingVars->dataArray[strtoupper($this->dbColumnsArray[$this->defaultSelectedField])]))
                    $TOP_GRID_FIELD_NAMES["ID"] = $this->settingVars->dataArray[strtoupper($this->dbColumnsArray[$this->defaultSelectedField])]["ID_CSV"];
                
                $this->jsonOutput['TOP_GRID_FIELD_NAMES'] = $TOP_GRID_FIELD_NAMES;
            }

            $tables = ($this->tableField !== "") ? $this->tableField : $this->settingVars->skutable;
            $this->prepareFieldsFromFieldSelectionSettings([$tables]);
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

        $skuFieldPart = explode("#", $this->skuField);
        $skuField = strtoupper($this->dbColumnsArray[$skuFieldPart[0]]);
        $skuField = (count($skuFieldPart) > 1) ? strtoupper($skuField . "_" . $this->dbColumnsArray[$skuFieldPart[1]]) : $skuField;

        $this->skuNameField = $this->settingVars->dataArray[$skuField]['NAME'];

        $this->accountsName = $this->makeFieldsToAccounts($this->accountFields);

        $this->columnNames = array();
        $this->columnNames["SKU_NAME"] = $this->displayCsvNameArray[$this->skuField];
        foreach ($this->accountsName as $key => $data) {
            $nameAlias = $this->settingVars->dataArray[$data]["NAME_ALIASE"];
            $this->columnNames[$nameAlias] = $this->displayCsvNameArray[$this->accountFields[$key]];
        }

        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
        	$this->jsonOutput["FIELD_NAMES"] = $this->columnNames;
    	}

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