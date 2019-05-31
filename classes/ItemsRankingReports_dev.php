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
    
    public $crossGridData;
     
     
    public function go($settingVars) {
        unset($_REQUEST["FSG"]);
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES
        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll

        $this->redisCache = new utils\RedisCache($this->queryVars);
        
        $this->settingVars->pageName = (empty($this->settingVars->pageName) && !empty($this->settingVars->pageID)) ? $this->settingVars->pageID.'_ItemsRankingReportsPage' : $this->settingVars->pageName;

        if ($this->settingVars->isDynamicPage) {
            $this->skuField = $this->getPageConfiguration('sku_field', $this->settingVars->pageID)[0];
            $this->accountFields = $this->getPageConfiguration('account_list_field', $this->settingVars->pageID);
            $this->showSegmentGrid = $this->getPageConfiguration('show_segment_grid', $this->settingVars->pageID)[0];
            $this->showSegmentChart = $this->getPageConfiguration('show_segment_chart', $this->settingVars->pageID)[0];

            $tempBuildFieldsArray = array($this->storeField,$this->skuField);
            if(is_array($this->accountFields) && !empty($this->accountFields))
                $tempBuildFieldsArray = array_merge($tempBuildFieldsArray, $this->accountFields);

            $buildDataFields = array();
            foreach ($tempBuildFieldsArray as $value)
                if (!empty($value) && !in_array($value, $buildDataFields))
                    $buildDataFields[] = $value;
            
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
        }
		
        return $this->jsonOutput;
    }

    private function prepareData() {
        $this->prepareSummaryGrid();
        $this->prepareMarketCrossTabGrid(0, "itemsRankingReportsWithoutFlag");
        if($this->showSegmentGrid === "true" || $this->showSegmentChart === "true" )
        	$this->prepareMarketCrossTabGrid(1, "segmentRankingReportGridData");  // flag = 1 change for check
        
    }

    function downloadXlsx() {
        $this->prepareSummaryGrid();
        if ($_REQUEST['gridTag'] == "itemsRanking") {
            $this->prepareMarketCrossTabGrid(0, "itemsRankingReportsWithoutFlag");
        } else {
            $this->prepareMarketCrossTabGrid(1, "segmentRankingReportGridData");
        }
    }

    function prepareSummaryGrid() {
		$options = $summaryGridData = array();

        $performanceBoxSettings = array(
            array('timeFrame' => 4, 'title' => 'LAST 4 WEEK VS LY', 'alias' => 'L4', 'TY_WEEKS' => array(), 'LY_WEEKS' => array()),
            array('timeFrame' => 12, 'title' => 'LAST 12 WEEK VS LY', 'alias' => 'L12', 'TY_WEEKS' => array(), 'LY_WEEKS' => array()),
            array('timeFrame' => 'YTD', 'title' => 'YEAR TO DATE VS LY', 'alias' => 'YTD', 'TY_WEEKS' => array(), 'LY_WEEKS' => array()),
            array('timeFrame' => 52, 'title' => 'LAST 52 WEEK VS LY', 'alias' => 'L52', 'TY_WEEKS' => array(), 'LY_WEEKS' => array()),
        );
        
		$qPart = "";
		if(is_array($_REQUEST['marketID']) && !empty($_REQUEST['marketID']))
		{
            $measureArr = $measureSelectionArr = array();
            
            filters\timeFilter::getTimeFrame(52, $this->settingVars);
            
            foreach ($this->settingVars->pageArray["MEASURE_SELECTION_LIST"] as $key => $measureVal) 
            {
                $options = array();
                $measureKey = 'M' . $measureVal['measureID'];
                $measure = $this->settingVars->measureArray[$measureKey];
                
                $measureValue = $measure['ALIASE'];
                
                if(($measureVal['measureID'] == $_REQUEST['ValueVolume']))
                {
                    $currentMeasure = $measure['ALIASE'];
                    $requiredFields[] = $measureValue.'_TY';
                    $requiredFields[] = $measureValue.'_LY';
                    $options['tyLyRange'][$measureValue.'_TY'] =  " ".filters\timeFilter::$tyWeekRange." ";
                    $options['tyLyRange'][$measureValue.'_LY'] =  " ".filters\timeFilter::$lyWeekRange." ";
                    $measureArr[$measureKey] = config\MeasureConfigure::prepareSelect($this->settingVars, $this->queryVars, array($measureKey), $options);
                    $measureSelectionArr = array_merge($measureSelectionArr,$measureArr[$measureKey]);    
                }
            }

            $havingPart[] = $currentMeasure."_TY > 0 ";
            $havingPart[] = $currentMeasure."_LY > 0 ";
            
			$requiredFields = $this->settingVars->tableUsedForQuery = $this->measureFields = array();
            datahelper\Common_Data_Fetching_Functions::$settingVars = $this->settingVars;
            datahelper\Common_Data_Fetching_Functions::$queryVars = $this->queryVars;
            $measureFields = datahelper\Common_Data_Fetching_Functions::getMeasuresExtraTable();
            $this->measureFields = $measureFields;            
        	$this->measureFields[] = $this->storeIDField;
            $this->measureFields[] = $this->skuNameField;
        	$this->measureFields[] = $this->controlFlagField;

            $this->gPartForCrossGrid[] = $requiredFields[] = $groupByPart[] = "'SKU_NAME'";
            
			foreach ($this->accountsName as $key => $data) {
                $this->measureFields[] = $this->settingVars->dataArray[$data]['NAME'];
                $selectPart[] = $this->settingVars->dataArray[$data]['NAME'] . " AS '" . $this->settingVars->dataArray[$data]['NAME_ALIASE'] . "'";
                $this->gPartForCrossGrid[] = $groupByPart[] = "'" . $this->settingVars->dataArray[$data]['NAME_ALIASE'] . "'";
                $outputcols[] = $requiredFields[] = $this->settingVars->dataArray[$data]['NAME_ALIASE'];
	        }

	        $this->settingVars->useRequiredTablesOnly = true;
	        if (is_array($this->measureFields) && !empty($this->measureFields)) {
	            $this->prepareTablesUsedForQuery($this->measureFields);
	        }
	        $this->queryPart = $this->getAll(). " AND (" . filters\timeFilter::$tyWeekRange . " OR " . filters\timeFilter::$lyWeekRange . ") AND ".$this->storeIDField." IN (".implode(",", $_REQUEST['marketID']).") ";
            
            $selectPart[] = $this->storeIDField." AS SNO ";
            $selectPart[] = $this->controlFlagField." AS PL ";
            $selectPart[] = "CONCAT(".$this->settingVars->yearperiod.",LPAD(".$this->settingVars->weekperiod.",2,'0')) AS YEARWEEK ";
            
            $groupByPart[] = "SNO";
            $groupByPart[] = "PL";
            $groupByPart[] = "YEARWEEK";
            
			$query = "SELECT  ".$this->skuNameField." as SKU_NAME, "
					. implode(",", $selectPart) . " " .
					" , ".implode(", ", $measureSelectionArr)." " .
					" FROM " . $this->settingVars->tablename . $this->queryPart .
                	" GROUP BY " . implode(",", $groupByPart).
					" HAVING " . implode(" OR ", $havingPart);
            
            //echo $query; exit;
            
            $weekObj = new datahelper\Time_Selection_DataCollectors($this->settingVars);
            $weeks = $weekObj->getAllWeek($this->jsonOutput, false, true);
            
            foreach($weeks as $key => $data)
            {
                $wk = explode("-",$data['data']);
                $week = ($wk[0]<=9 ? "0".$wk[0] : $wk[0]);

                if($key == 0)
                {
                    $maxYear = $wk[1];
                    $maxWeek = $week;
                }
                
                foreach ($performanceBoxSettings as $bKey => $boxSetting)
                {
                    if($key <= $boxSetting['timeFrame']-1 && $boxSetting['timeFrame'] != 'YTD')
                    {
                        $performanceBoxSettings[$bKey]['TY_WEEKS'][] = $wk[1].$week;
                        $performanceBoxSettings[$bKey]['LY_WEEKS'][] = ($wk[1]-1).$week;
                    }
                    
                    if($boxSetting['timeFrame'] == 'YTD' && $wk[1] == $maxYear)
                    {
                        $performanceBoxSettings[$bKey]['TY_WEEKS'][] = $wk[1].$week;
                        $performanceBoxSettings[$bKey]['LY_WEEKS'][] = ($wk[1]-1).$week;
                    }
                }
            }
            
            $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);

            if ($redisOutput === false) {
                $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
                $this->redisCache->setDataForHash($result);
            } else {
                $result = $redisOutput;
            }
            
            $summaryArray = array();
            
            if(is_array($result) && !empty($result))
            {
                foreach($result as $key => $data)
                {
                    foreach ($performanceBoxSettings as $boxSetting) 
                    {
                        if(in_array($data['YEARWEEK'], $boxSetting['TY_WEEKS']))
                            $summaryArray["SALES_".$boxSetting['alias']."_TY_".$data['SNO']] += $data[$currentMeasure.'_TY'];

                        if(in_array($data['YEARWEEK'], $boxSetting['LY_WEEKS']))
                            $summaryArray["SALES_".$boxSetting['alias']."_LY_".$data['SNO']] += $data[$currentMeasure.'_LY'];
                            
                        // FOT TOTAL    
                        if(in_array($data['YEARWEEK'], $boxSetting['TY_WEEKS']) && $boxSetting['alias'] == 'YTD')
                            $summaryArray["SALES_".$boxSetting['alias']."_TY_".$data['SNO']."_".$data['PL']] += $data[$currentMeasure.'_TY'];

                        if(in_array($data['YEARWEEK'], $boxSetting['LY_WEEKS']) && $boxSetting['alias'] == 'YTD')
                            $summaryArray["SALES_".$boxSetting['alias']."_LY_".$data['SNO']."_".$data['PL']] += $data[$currentMeasure.'_LY'];
                        
                        if( (in_array($data['YEARWEEK'], $boxSetting['TY_WEEKS']) || in_array($data['YEARWEEK'], $boxSetting['LY_WEEKS'])) && $boxSetting['alias'] == 'YTD')
                        {
                            foreach ($_REQUEST['marketID'] as $marketIndex => $currentMarketID) 
                            {
                                if (!empty($currentMarketID) && $currentMarketID != "" && $currentMarketID == $data['SNO']) 
                                {
                                    $group = array();
                                    foreach ($this->gPartForCrossGrid as $gPart) {
                                        $gPart = trim($gPart,"'");
                                        $group[] = $data[$gPart];
                                    }
                                    
                                    $keyIndex = implode("_", $group);

                                    foreach ($this->gPartForCrossGrid as $gPart) {
                                        $gPart = trim($gPart,"'");
                                        $finalArray[$data['PL']][$keyIndex][$gPart] = $data[$gPart];
                                    }
                                    $finalArray[$data['PL']][$keyIndex]['SALES_TY_' . $marketIndex] 	+= $data[$currentMeasure.'_TY'];
                                    $finalArray[$data['PL']][$keyIndex]['SALES_LY_' . $marketIndex] 	+= $data[$currentMeasure.'_LY'];
                                }
                            }
                        }
                    }
                }
            }
            
			foreach ($_REQUEST['marketID'] as $marketIndex => $currentMarketID)
            {
				if(is_array($summaryArray) && !empty($summaryArray))
				{
					$rowData = $summaryArray;

                    foreach ($performanceBoxSettings as $key => $boxSetting) {
                        $temp = array();

                        $temp['title'] = $boxSetting['title'];
                        $temp['SALES_TY_VS'] = number_format(($rowData['SALES_'.$boxSetting['alias'].'_TY_'.$currentMarketID] - $rowData['SALES_'.$boxSetting['alias'].'_LY_'.$currentMarketID]),0,'.',',');
                        $temp['SALES_TY_VAR'] = (($rowData['SALES_'.$boxSetting['alias'].'_LY_'.$currentMarketID] != 0) ? number_format( ((($rowData['SALES_'.$boxSetting['alias'].'_TY_'.$currentMarketID] - $rowData['SALES_'.$boxSetting['alias'].'_LY_'.$currentMarketID])/$rowData['SALES_'.$boxSetting['alias'].'_LY_'.$currentMarketID])*100), 1, '.' , ',' ) : 0);
                        $temp['SALES_TY'] = number_format($rowData['SALES_'.$boxSetting['alias'].'_TY_'.$currentMarketID],0,'.',',');
                        $temp['SALES_LY'] = number_format($rowData['SALES_'.$boxSetting['alias'].'_LY_'.$currentMarketID],0,'.',',');

                        if($boxSetting['alias'] == 'YTD')
                        {
                            $this->total[$currentMarketID][0] = $rowData['SALES_'.$boxSetting['alias'].'_TY_'.$currentMarketID."_0"] + $rowData['SALES_'.$boxSetting['alias'].'_LY_'.$currentMarketID."_0"];
                            $this->total[$currentMarketID][1] = $rowData['SALES_'.$boxSetting['alias'].'_TY_'.$currentMarketID."_1"] + $rowData['SALES_'.$boxSetting['alias'].'_LY_'.$currentMarketID."_1"];
                        }
                        
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
        
        $this->finalArray = $finalArray;
        $this->jsonOutput['itemsRankingReportsSummary'] = $summaryGridData;
    }

    private function prepareMarketCrossTabGrid($flagCondition, $jsonTagName) {
		
		$finalArray = $allChartDataArr = array();

		if(is_array($_REQUEST['marketID']) && !empty($_REQUEST['marketID']))
		{
            $finalArray = $this->finalArray[$flagCondition];

            $accumulatedShare = array();
            foreach ($_REQUEST['marketID'] as $marketIndex => $currentMarketID) 
            {
                $finalArray = utils\SortUtility::sort2DArray($finalArray, 'SALES_TY_' . $marketIndex, utils\SortTypes::$SORT_DESCENDING);
                
                foreach($finalArray as $fKey => $finalData)
                {
                    $variance = $finalData['SALES_TY_'.$marketIndex] - $finalData['SALES_LY_'.$marketIndex];
                    $finalData['VAR_PCT_'.$marketIndex] = $finalData['SALES_LY_'.$marketIndex] != 0 ? ($variance / $finalData['SALES_LY_'. $marketIndex]) * 100 : 0;
                    
                    $share  = $this->total[$currentMarketID][$flagCondition] != 0 ? ($finalData['SALES_TY_'.$marketIndex] / $this->total[$currentMarketID][$flagCondition]) * 100 : 0;
                    
                    $shareLY    = $this->total[$currentMarketID][$flagCondition] != 0 ? ($finalData['SALES_LY_'.$marketIndex] / $this->total[$currentMarketID][$flagCondition]) * 100 : 0;
                    
                    $shareVar = $share - $shareLY;
                    
                    $accumulatedShare[$marketIndex] 	+= $finalData['SHARE_' . $marketIndex] = number_format($share, 2, '.', ',');
                    $finalData['CUM_SHARE_' . $marketIndex] 	= $accumulatedShare[$marketIndex];
                    
                    $finalArray[$fKey]['RANK_' . $marketIndex] 		= $fKey + 1;
                    $finalArray[$fKey]['SHARE_' . $marketIndex] 	+= number_format($share, 1, '.', '');
                    $finalArray[$fKey]['SHRCHG_' . $marketIndex] 	= number_format($shareVar, 1, '.', '');
                    $finalArray[$fKey]['CUM_SHARE_' . $marketIndex]	= number_format($finalData['CUM_SHARE_' . $marketIndex], 1, '.', ',');
                    $finalArray[$fKey]['VAR_' . $marketIndex] 		= number_format($finalData['VAR_PCT_' . $marketIndex], 1, '.', ',');
                    $finalArray[$fKey]['SALES_TY_' . $marketIndex]  = number_format($finalData['SALES_TY_'.$marketIndex], 0, '.', ',');
                    $finalArray[$fKey]['SALES_LY_' . $marketIndex]  = number_format($finalData['SALES_LY_'.$marketIndex], 0, '.', ',');
                    $finalArray[$fKey]['VARIANCE_' . $marketIndex]  = number_format($variance, 0, '.', ',');
                }
            }
            
			if ($_REQUEST['action'] == "downloadXlsx") {
				$finalArray = utils\SortUtility::sort2DArray($finalArray, 'RANK_0', utils\SortTypes::$SORT_ASCENDING);
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
        $objPHPExcel->getProperties()->setCreator("Ultralysis")
                ->setTitle("Item Ranking Report Export File")
                ->setSubject("Item Ranking Report Export File");
        $newWorkSheet = new \PHPExcel_Worksheet($objPHPExcel, "Summary");
        $objPHPExcel->addSheet($newWorkSheet, 0);
        $objPHPExcel->setActiveSheetIndex(0);

        $sheet = $objPHPExcel->getActiveSheet();
        $primaryMarketStyle = array('fill' => array('type' => \PHPExcel_Style_Fill::FILL_SOLID,'color' => array('argb' => 'FFFACE')));
        $secondaryMarketStyle = array('fill' => array('type' => \PHPExcel_Style_Fill::FILL_SOLID,'color' => array('argb' => 'CED3FF')));

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
                        }else if($key == 'SALES_TY' || $key == 'VARIANCE'){
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