<?php

namespace classes;

use db;
use config;
use filters;
use datahelper;
use utils;

class RetailerReportV1 extends config\UlConfig {
    /*     * ***
     * Default gateway function, should be similar in all DATA CLASS
     * arguments:
     *  $settingVars [project settingsGateway variables]
     * @return $xmlOutput with all data that should be sent to client app
     * *** */

    public function go($settingVars) {
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES
        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll

        $action = $_REQUEST["action"];
        $this->redisCache = new utils\RedisCache($this->queryVars);

		if ($this->settingVars->isDynamicPage) {
			$this->supplierField = $this->getPageConfiguration('supplier_field', $this->settingVars->pageID)[0];
			$this->supplierOrderByField = $this->getPageConfiguration('supplier_order_by_field', $this->settingVars->pageID)[0];
			$this->brandField = $this->getPageConfiguration('brand_field', $this->settingVars->pageID)[0];
			$this->mainFilterField = $this->getPageConfiguration('main_filter_field', $this->settingVars->pageID)[0];
			$this->timeFilterSettings = $this->getPageConfiguration('time_filter_settings', $this->settingVars->pageID);
            
			$buildDataFields = array($this->supplierField, $this->brandField, $this->mainFilterField, $this->supplierOrderByField);
			
            $this->buildDataArray($buildDataFields);
            $this->buildPageArray();
		
            if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
                
            }
		}

        switch ($action) {
            case "genarateRetailerReport":
                $this->genarateRetailerReport();
                break;
        }

        return $this->jsonOutput;
    }

    function genarateRetailerReport()
    {
        datahelper\Common_Data_Fetching_Functions::$settingVars = $this->settingVars;
        datahelper\Common_Data_Fetching_Functions::$queryVars = $this->queryVars;

        $measuresFields = datahelper\Common_Data_Fetching_Functions::getMeasuresExtraTable();
        $measureSelectRes = $this->prepareMeasureSelectPart();
        $this->measureFields = $measureSelectRes['measureFields'];
        
        $this->measureFields[] = $this->supplierField;
        $this->measureFields[] = $this->brandField;
        $this->measureFields[] = $this->mainFilterField;
        $this->measureFields[] = $this->supplierOrderByField;
        $this->prepareTablesUsedForQuery($this->measureFields);
        $this->settingVars->useRequiredTablesOnly = true;
        $this->queryPart = $this->getAll();

        $selectPart[] = $this->supplierField ." AS SUPPLIER ";
        $selectPart[] = $this->brandField." as BRAND ";
        $selectPart[] = $this->mainFilterField." as TOP_FILTER ";
        $selectPart[] = "MAX(".$this->supplierOrderByField.") as ORG ";
        $selectPart[] = "MAX(customAgg3) as TOTAL ";
        $groupByPart[] = "SUPPLIER";
        $groupByPart[] = "BRAND";
        $groupByPart[] = "TOP_FILTER";

        foreach($this->timeFilterSettings as $data)
        {
            $filter = explode(" ", $data);
            $key = $filter[0];
            if($filter[0] != "YTD")
                $element = "LW".$filter[0];
            else
                $element = $filter[0];
                
            $this->timeArray[$key] = $element;
        }
        
        $measureArr = $measureSelectionArr = $arrAliase = array();
        $timeWhereCluase = '';
        $this->defaultTimeSelection = '';
        if(is_array($this->timeArray) && !empty($this->timeArray)){
            foreach ($this->timeArray as $timekey => $timeval) {
                if(is_array($this->settingVars->pageArray["MEASURE_SELECTION_LIST"]) && !empty($this->settingVars->pageArray["MEASURE_SELECTION_LIST"])){
                    filters\timeFilter::getTimeFrame($timekey, $this->settingVars);
                    foreach ($this->settingVars->pageArray["MEASURE_SELECTION_LIST"] as $mkey => $measureVal) {
                        if ($_REQUEST['ValueVolume'] != $measureVal['measureID'])
                            continue;

                        $options = array();
                        $key = $measureVal['measureID'];
                        $measureName = $measureVal['measureName'];
                        $measureKey = 'M' . $key;
                        $measure = $this->settingVars->measureArray[$measureKey];
                        $arrAliase[$measure['ALIASE']] = $measure['ALIASE'];

                        if (!empty(filters\timeFilter::$tyWeekRange)){
                            if($timeval == 'YTD' && $mkey == 0)
                                $orderBy = $timeval.'_TY_'.$measure['ALIASE'];
                            $options['tyLyRange'][$timeval.'_TY_'.$measure['ALIASE']] = filters\timeFilter::$tyWeekRange;
                        }

                        if (!empty(filters\timeFilter::$lyWeekRange))
                            $options['tyLyRange'][$timeval.'_LY_'.$measure['ALIASE']] = filters\timeFilter::$lyWeekRange;
                        
                        $measureArr[$measureKey] = config\MeasureConfigure::prepareSelect($this->settingVars, $this->queryVars, array($measureKey), $options);           
                        $measureSelectionArr = array_merge($measureSelectionArr,$measureArr[$measureKey]);
                        
                        if($_REQUEST['ValueVolume'] == $key)
                        {
                            // $requiredGridFields[] = $timeval.'_TY_'.$measure['ALIASE'];
                            // $requiredGridFields[] = $timeval.'_LY_'.$measure['ALIASE'];
                            $measureAliase = $measure['ALIASE'];
                        }
                        
                    }

                    if ($timekey == '52')
                        $timeWhereCluase = " AND (" . filters\timeFilter::$tyWeekRange . " OR " . filters\timeFilter::$lyWeekRange . ") ";
                }
            }
            $this->defaultTimeSelection = array_values($this->timeArray)[0];
        }

        $query  = " SELECT ";
        $query .= (!empty($selectPart)) ? implode(",", $selectPart). ", " : " ";
        $query .= implode(",", $measureSelectionArr) . " ";
        $query .= "FROM " . $this->settingVars->tablename . $this->queryPart . 
            " GROUP BY " .((!empty($groupByPart)) ? implode(",", $groupByPart)." ": " ");

        // $this->settingVars->maintable.".SNO IN (1,2,3,16,17,19,76,77,78,94,93,92,91,90,89,88,86,84,83,82,81,111,112,102,103)    

        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        // if ($redisOutput === false) {
        //     $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
        //     $this->redisCache->setDataForHash($result);
        // } else {
        //     $result = $redisOutput;
        // }

        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);

        if(!empty($this->defaultTimeSelection)) {
            $TY_VALUE    =  array_column($result, $this->defaultTimeSelection.'_TY_'.$measureAliase);
            $ORG         =  array_column($result, 'ORG');
            array_multisort($ORG, SORT_ASC, $TY_VALUE, SORT_DESC, $result);
        } else {
            $result = utils\SortUtility::sort2DArray($result, 'ORG', utils\SortTypes::$SORT_ASCENDING);
        }

        // $supplierList   = array_values(array_unique(array_column($result, "SUPPLIER")));
        $topFilterValue = array_values(array_unique(array_column($result, "TOP_FILTER")));
        sort($topFilterValue);

        // $requiredGridFields[] = "SUPPLIER";
        // $requiredGridFields[] = "BRAND";
        // $requiredGridFields[] = "TOP_FILTER";
        // $requiredGridFields[] = "TOTAL";
        // $result = $this->redisCache->getRequiredData($result, $requiredGridFields);
        // print_r($result);
        // exit();

        $skipRank = $reportData = $totalData = array();
        $allSupplierListData = $allSupplierListDataHead = [];
        if(is_array($result) && !empty($result))
        {
            foreach($result as $data)
            {
                foreach($this->timeArray as $key => $timeData)
                {
                    $totalTmp = $tmp = array();
                    $tmp["TIME_FILTER"] = ($key != 'YTD') ? $key.' WEEK ENDING' : "YTD";
                    $tmp["TOP_FILTER"] = $data['TOP_FILTER'];
                    $tmp["BRAND"] = $data['BRAND'];
                    $tmp["SUPPLIER"] = $data['SUPPLIER'];
                    $tmp["TY_VALUE"] = (double)$data[$timeData.'_TY_'.$measureAliase];
                    $tmp["LY_VALUE"] = (double)$data[$timeData.'_LY_'.$measureAliase];
                    // $tmp['TOTAL']   = ($data['TOTAL'] == 'TOTAL') ? 1 : 2;
                    
                    $reportData[] = $tmp;
                    
                    $totalData[$tmp["TIME_FILTER"].$tmp["TOP_FILTER"].$tmp["SUPPLIER"]] = array(
                    "TIME_FILTER" => $tmp["TIME_FILTER"],
                    "TOP_FILTER" => $data['TOP_FILTER'],
                    "BRAND" => "Total",
                    "SUPPLIER" => $data['SUPPLIER'],
                    // "TOTAL" => 0,
                    "TY_VALUE" => (double)$totalData[$tmp["TIME_FILTER"].$tmp["TOP_FILTER"].$tmp["SUPPLIER"]]["TY_VALUE"] + $data[$timeData.'_TY_'.$measureAliase],
                    "LY_VALUE" => (double)$totalData[$tmp["TIME_FILTER"].$tmp["TOP_FILTER"].$tmp["SUPPLIER"]]["LY_VALUE"] + $data[$timeData.'_LY_'.$measureAliase],
                    );
                }

                if($data['TOTAL'] == 'TOTAL'){
                    $skipRank[$data['SUPPLIER']] = true;
                    if(!isset($allSupplierListData[$data['SUPPLIER']])) {
                        $allSupplierListDataHead[$data['SUPPLIER']] =  $data['ORG'];
                    }
                }else{
                    if($data['TOP_FILTER'] == $topFilterValue[0] && !empty($this->defaultTimeSelection)) {
                        $allSupplierListData[$data['SUPPLIER']] += $data[$this->defaultTimeSelection.'_TY_VALUE'];
                    }
                }
            }
        }

        if(!empty($allSupplierListData) || !empty($allSupplierListDataHead)) {
            if (!empty($allSupplierListData))
                arsort($allSupplierListData);

            if (!empty($allSupplierListData) && !empty($allSupplierListDataHead))
                $supplierList = array_merge(array_keys($allSupplierListDataHead),array_keys($allSupplierListData));
            elseif (!empty($allSupplierListData))
                $supplierList = array_keys($allSupplierListData);
            elseif (!empty($allSupplierListDataHead))
                $supplierList = array_keys($allSupplierListDataHead);
        }

        // $TIME_FILTER =  array_column($reportData, 'TIME_FILTER');
        // $TOP_FILTER  =  array_column($reportData, 'TOP_FILTER');
        // $BRAND       =  array_column($reportData, 'BRAND');
        // $SUPPLIER    =  array_column($reportData, 'SUPPLIER');
        // $TY_VALUE    =  array_column($reportData, 'TY_VALUE');
        // array_multisort($TIME_FILTER, SORT_DESC, $TOP_FILTER, SORT_ASC, $BRAND, SORT_ASC, $TY_VALUE, SORT_DESC, $reportData);
        
        $reportData = array_merge(array_values($totalData), $reportData);
        // $reportData = utils\SortUtility::sort2DArray($reportData, 'TOTAL', utils\SortTypes::$SORT_ASCENDING);
        $allBrandList = array_values(array_unique(array_column($reportData, 'BRAND')));
        
        //$this->generateRetailerReportFile($reportData, $skipRank, $supplierList, $topFilterValue);
        $csvDataFilePath = $this->generateRetailerReportCsvDataFile($reportData, $skipRank);

        $fileName = "Retailer-Report-" . date("Y-m-d-h-i-s") . ".xlsx";
        $savePath = dirname(__FILE__)."/../uploads/Retailer-Report/";
        $filePath = $savePath.$fileName;
        // $filePath = "/home/dev/public_html/testsh/Retailer-Report-2017-shashank.xlsx";
        $maxMydate = (isset($this->settingVars->maxYearWeekCombination[2]) && $this->settingVars->maxYearWeekCombination[2] != "") ? date_format(date_create($this->settingVars->maxYearWeekCombination[2]), 'm/d/Y') : "";
        
        $brands = implode("##", $allBrandList);
        $supplierList = implode("##", $supplierList);
        $topFilterValue = implode("##", $topFilterValue);
        $timeFilterSettings = implode("##", $this->timeFilterSettings);
        $skipRankCnt = count($skipRank);

        // echo '/usr/bin/perl '.dirname(__FILE__).'/../batch/writeExcel.pl "'.$filePath.'" "'.$csvDataFilePath.'" "'.$maxMydate.'" "'.$brands.'" "'.$supplierList.'" "'.$topFilterValue.'" "'.$timeFilterSettings.'" "'.$skipRankCnt.'"';
        // exit();
        $result = shell_exec('/usr/bin/perl '.dirname(__FILE__).'/../batch/writeExcel.pl "'.$filePath.'" "'.$csvDataFilePath.'" "'.$maxMydate.'" "'.$brands.'" "'.$supplierList.'" "'.$topFilterValue.'" "'.$timeFilterSettings.'" "'.$skipRankCnt.'"');

        // var_dump($result);
        // exit();
        $this->jsonOutput['downloadLink'] = $this->settingVars->get_full_url()."/".basename(dirname(dirname(__FILE__)))."/uploads/Retailer-Report/".$fileName;
    }

    public function generateRetailerReportCsvDataFile($reportData, $skipRank)
    {
        // $csvArray[] = array("TIME_FILTER", "BANNER", "BRAND", "SUPPLIER", "TY_VALUE", "LY_VALUE", "VAR", "VAR_PER");
        // $restReportData = array();
        
        /*foreach($reportData as $key => $data)
        {
            if($data['TY_VALUE'] > 0 || $data['LY_VALUE'] > 0)
            {
                if($skipRank[$data['SUPPLIER']])
                {
                    $tmp = array();
                    $tmp[] = $data['TIME_FILTER'];
                    $tmp[] = $data['TOP_FILTER'];
                    $tmp[] = $data['BRAND'];
                    $tmp[] = $data['SUPPLIER'];
                    $tmp[] = $data['TY_VALUE'];
                    $tmp[] = $data['LY_VALUE'];
                    $chagYag = ($data["LY_VALUE"] > 0) ? ($data["TY_VALUE"] - $data["LY_VALUE"])/$data["LY_VALUE"] : 0;
                    $tmp[] = $chagYag;
                    $chgToLy = ($data["LY_VALUE"] > 0) ? (($data["TY_VALUE"] - $data["LY_VALUE"])/$data["LY_VALUE"])*100 : 0;
                    $tmp[] = $chgToLy;
                    $csvArray[] = $tmp;
                }
                else
                    $restReportData[] = $reportData[$key];
            }
        }*/
        
        foreach($reportData as $key => $data)
        {
            if($data['TY_VALUE'] > 0 || $data['LY_VALUE'] > 0)
            {
                // $tmp = array();
                // $tmp[] = $data['TIME_FILTER'];
                // $tmp[] = $data['TOP_FILTER'];
                // $tmp[] = $data['BRAND'];
                // $tmp[] = $data['SUPPLIER'];
                // $tmp[] = $data['TY_VALUE'];
                // $tmp[] = $data['LY_VALUE'];
                // $chagYag = ($data["LY_VALUE"] > 0) ? ($data["TY_VALUE"] - $data["LY_VALUE"])/$data["LY_VALUE"] : 0;
                // $tmp[] = $chagYag;
                // $chgToLy = ($data["LY_VALUE"] > 0) ? (($data["TY_VALUE"] - $data["LY_VALUE"])/$data["LY_VALUE"])*100 : 0;
                // $tmp[] = $chgToLy;

                $csvData = '"'.implode('","', $data).'"';
                $csvArray[] = $csvData;
            }
        }
        
        $csvFileContent = implode("\n", $csvArray);

        // foreach($csvArray as $line)
        // {
        //     foreach($line as $data)
        //         $csvFileContent .= '"'.$data.'",';
            
        //     $csvFileContent = trim($csvFileContent, ",");
        //     $csvFileContent .= "\n";
        // }
        
        $filePath = "";
        if(!empty($csvFileContent))
        {
            $fileName = "Retailer-Report-Data-" . date("Y-m-d-h-i-s") . ".csv";
            $savePath = dirname(__FILE__)."/../uploads/Retailer-Report/";
            chdir($savePath);
            $filePath = $savePath.$fileName;
        
            $csv_handler = fopen ($filePath,'w');
            fwrite ($csv_handler, $csvFileContent);
            fclose ($csv_handler);
        }
        return $filePath;
    }    
    
    public function generateRetailerReportFile($reportData, $skipRank, $supplierList, $topFilterValue)
    {
        $excelLibPath = $_SERVER['DOCUMENT_ROOT']."/ppt/Classes/PHPExcel.php";
        include_once $excelLibPath;
        
        $objPHPExcel = new \PHPExcel();
        
        $objPHPExcel->getProperties()->setCreator("Ultralysis")
                ->setTitle("Retailer Report")
                ->setSubject("Retailer Report");
                
        /* RAW DATA SHEET START */
        
        $retailersDataSheet = new \PHPExcel_Worksheet($objPHPExcel, "RetailersData");
        $objPHPExcel->addSheet($retailersDataSheet, 0);
        $objPHPExcel->setActiveSheetIndex(0);
        $dataSheet = $objPHPExcel->getActiveSheet();
        
        $row = 5;
        $rowFileStartNo = $row;
        $restReportData = array();
        
        foreach($reportData as $key => $data)
        {
            if($data['TY_VALUE'] > 0 || $data['LY_VALUE'] > 0)
            {
                if($skipRank[$data['SUPPLIER']])
                {
                    $dataSheet->setCellValueByColumnAndRow(0, $row, $data['TIME_FILTER']);
                    $dataSheet->setCellValueByColumnAndRow(1, $row, $data['TOP_FILTER']);
                    $dataSheet->setCellValueByColumnAndRow(2, $row, $data['BRAND']);
                    $dataSheet->setCellValueByColumnAndRow(3, $row, $data['SUPPLIER']);
                    $dataSheet->setCellValueByColumnAndRow(4, $row, $data['TY_VALUE']);
                    $dataSheet->setCellValueByColumnAndRow(5, $row, $data['LY_VALUE']);
                    $chagYag = ($data["LY_VALUE"] > 0) ? ($data["TY_VALUE"] - $data["LY_VALUE"])/$data["LY_VALUE"] : 0;
                    $dataSheet->setCellValueByColumnAndRow(6, $row, $chagYag);
                    $chgToLy = ($data["LY_VALUE"] > 0) ? (($data["TY_VALUE"] - $data["LY_VALUE"])/$data["LY_VALUE"])*100 : 0;
                    $dataSheet->setCellValueByColumnAndRow(7, $row, $chgToLy);
                    $row++;
                }
                else
                    $restReportData[] = $reportData[$key];
            }
        }
        
        //$restReportData = utils\SortUtility::sort2DArray($restReportData, 'TY_VALUE', utils\SortTypes::$SORT_DESCENDING);
        foreach($restReportData as $key => $data)
        {
            if($data['TY_VALUE'] > 0 || $data['LY_VALUE'] > 0)
            {
                $dataSheet->setCellValueByColumnAndRow(0, $row, $data['TIME_FILTER']);
                $dataSheet->setCellValueByColumnAndRow(1, $row, $data['TOP_FILTER']);
                $dataSheet->setCellValueByColumnAndRow(2, $row, $data['BRAND']);
                $dataSheet->setCellValueByColumnAndRow(3, $row, $data['SUPPLIER']);
                $dataSheet->setCellValueByColumnAndRow(4, $row, (double)$data['TY_VALUE']);
                $dataSheet->setCellValueByColumnAndRow(5, $row, (double)$data['LY_VALUE']);
                $chagYag = ($data["LY_VALUE"] > 0) ? ($data["TY_VALUE"] - $data["LY_VALUE"])/$data["LY_VALUE"] : 0;
                $dataSheet->setCellValueByColumnAndRow(6, $row, $chagYag);
                $chgToLy = ($data["LY_VALUE"] > 0) ? (($data["TY_VALUE"] - $data["LY_VALUE"])/$data["LY_VALUE"])*100 : 0;
                $dataSheet->setCellValueByColumnAndRow(7, $row, $chgToLy);
                $row++;
            }
        }
        
        $rowFileEndNo = $row-1;
        
        /* RAW DATA SHEET END */
        
        /* MAIN SHEET SHEET START */
        
        // $topFilterValue = array_values(array_unique(array_column($reportData, "TOP_FILTER")));
        // sort($topFilterValue);
        $allBrandList = array_values(array_unique(array_column($reportData, "BRAND")));
        
        /* $query = "SELECT * FROM ".$this->settingVars->mjnbrandsorttable." ORDER BY sort_order";
        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }
        
        $allBrandListTmp = array();
        if(is_array($result) && !empty($result))
        {
            $allBrandListTmp[] = "Total";
            foreach($result as $data)
            {
                if(in_array($data['brand'], $allBrandList))
                    $allBrandListTmp[] = $data['brand'];
            }
            $allBrandList = $allBrandListTmp;
        } */
        
        //$supplierList = array_values(array_unique(array_column($reportData, "SUPPLIER")));

        $colors = array("C0C0C0","9999FF","FFFFCC","CCFFFF","FF8080","CCCCFF","FFFF00","00FFFF","CCFFFF","CCFFCC","FFFF99","99CCFF","FF99CC","CC99FF","FFCC99","33CCCC","99CC00","FFCC00","FF9900","FF6600");

        $onlydBorderStyle = array(
            'borders' => array(
                'allborders' => array(
                    'style' => \PHPExcel_Style_Border::BORDER_THIN,
                    'color' => array('rgb' => '000000')
                )
            ),
        );
        
        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
        $fileName = "Retailer-Report-" . date("Y-m-d-h-i-s") . ".xlsx";
        $savePath = dirname(__FILE__)."/../uploads/Retailer-Report/";
        chdir($savePath);
        $objWriter->save($fileName);
        
        $excelFile = \PHPExcel_IOFactory::createReader('Excel2007');
        $objPHPExcel = $excelFile->load($fileName);
        
        unlink($fileName);
        
        $retailersSheet = new \PHPExcel_Worksheet($objPHPExcel, "Retailers");
        $objPHPExcel->addSheet($retailersSheet, 1);
        $objPHPExcel->setActiveSheetIndex(1);
        $objPHPExcelActiveSheet = $objPHPExcel->getActiveSheet();
        $objPHPExcelActiveSheet->setCellValueExplicit("B1", "Select Options From Dropdowns Below");
        $cell = $objPHPExcelActiveSheet->getCellByColumnAndRow(1, 0)->getColumn().'1';
        $objPHPExcelActiveSheet->getStyle($cell)->getFill()->applyFromArray(array('type' => \PHPExcel_Style_Fill::FILL_SOLID,'startcolor' => array('rgb' => 'CCFFCC')));
        $objPHPExcelActiveSheet->getStyle($cell)->applyFromArray($onlydBorderStyle);
        
        $objValidation = $objPHPExcelActiveSheet->getCell('B2')->getDataValidation();
        $objValidation->setType( \PHPExcel_Cell_DataValidation::TYPE_LIST );
        $objValidation->setErrorStyle( \PHPExcel_Cell_DataValidation::STYLE_INFORMATION );
        $objValidation->setAllowBlank(false);
        $objValidation->setShowInputMessage(true);
        $objValidation->setShowErrorMessage(true);
        $objValidation->setShowDropDown(true);
        $objValidation->setErrorTitle('Input error');
        $objValidation->setError('Value is not in list.');
        $objValidation->setPromptTitle('Pick from list');
        $objValidation->setPrompt('Please pick a value from the drop-down list.');
        $objValidation->setFormula1('"'.implode(",", $topFilterValue).'"');
        
        $objPHPExcelActiveSheet->getStyle("B2")->applyFromArray($onlydBorderStyle);

        $objValidation = $objPHPExcelActiveSheet->getCell('B3')->getDataValidation();
        $objValidation->setType( \PHPExcel_Cell_DataValidation::TYPE_LIST );
        $objValidation->setErrorStyle( \PHPExcel_Cell_DataValidation::STYLE_INFORMATION );
        $objValidation->setAllowBlank(false);
        $objValidation->setShowInputMessage(true);
        $objValidation->setShowErrorMessage(true);
        $objValidation->setShowDropDown(true);
        $objValidation->setErrorTitle('Input error');
        $objValidation->setError('Value is not in list.');
        $objValidation->setPromptTitle('Pick from list');
        $objValidation->setPrompt('Please pick a value from the drop-down list.');
        $objValidation->setFormula1('"'.implode(",", $this->timeFilterSettings).'"');
        
        $objPHPExcelActiveSheet->getStyle("B3")->applyFromArray($onlydBorderStyle);
        
        $objPHPExcelActiveSheet->setCellValueByColumnAndRow(1, 4, (isset($this->settingVars->maxYearWeekCombination[2]) && $this->settingVars->maxYearWeekCombination[2] != "") ? date_format(date_create($this->settingVars->maxYearWeekCombination[2]), 'm/d/Y') : "" );
        $cell = $objPHPExcelActiveSheet->getCellByColumnAndRow(1, 4)->getColumn().'4';
        $objPHPExcelActiveSheet->getStyle($cell)->applyFromArray($onlydBorderStyle);
        
        $objPHPExcelActiveSheet->getColumnDimension('B')->setWidth(35);
        
        $cell = $objPHPExcelActiveSheet->getCellByColumnAndRow(1, 6)->getColumn().'6';
        $objPHPExcelActiveSheet->getStyle($cell)->getFill()->applyFromArray(array('type' => \PHPExcel_Style_Fill::FILL_SOLID,'startcolor' => array('rgb' => 'C0C0C0')));

        $objPHPExcelActiveSheet->getStyle($cell)->applyFromArray($onlydBorderStyle);
        
        $row = 6;
        $col = 2;
        $headerArray = array("$ Rank","Current Dollars","YAGO Dollars","Dollar Change","% Change to LY","Category Share","Category Share Chg","Market Share","Market Share Chg");
        $colorKey = 0;
        foreach($allBrandList as $key => $data)
        {
            if(isset($colors[$colorKey]))
                $brandColor = $colors[$colorKey];
            else
            {
                $colorKey = 0;
                $brandColor = $colors[$colorKey];
            }
            
            $objPHPExcelActiveSheet->setCellValueByColumnAndRow($col, $row, $data);
            $cell = $objPHPExcelActiveSheet->getCellByColumnAndRow($col, $row)->getColumn().$row;
            $objPHPExcelActiveSheet->getStyle($cell)->getFill()->applyFromArray(array('type' => \PHPExcel_Style_Fill::FILL_SOLID,'startcolor' => array('rgb' => $brandColor)));
            $StartCol = $this->getNameFromNumber($col);
            $endCol = $this->getNameFromNumber($col+8);
            $objPHPExcelActiveSheet->mergeCells($StartCol."$row:".$endCol."$row");
            $style = array(
                'alignment' => array(
                    'horizontal' => \PHPExcel_Style_Alignment::HORIZONTAL_CENTER,
                ),
                'borders' => array(
                    'allborders' => array(
                        'style' => \PHPExcel_Style_Border::BORDER_THIN,
                        'color' => array('rgb' => '000000')
                    )
                ),                 
            );
            $objPHPExcelActiveSheet->getStyle($StartCol."$row:".$endCol."$row")->applyFromArray($style);
            
            $i = $col;
            foreach($headerArray as $data)
            {
                $objPHPExcelActiveSheet->setCellValueByColumnAndRow($i, 7, $data);
                $cell = $objPHPExcelActiveSheet->getCellByColumnAndRow($i, 2)->getColumn().'7';
                $objPHPExcelActiveSheet->getStyle($cell)->getFill()->applyFromArray(array('type' => \PHPExcel_Style_Fill::FILL_SOLID,'startcolor' => array('rgb' => $brandColor)));
                $objPHPExcelActiveSheet->getStyle($cell)->applyFromArray($style)->getAlignment()->setWrapText(true);                    
                $i++;
            }
            
            $col += 9;
            $colorKey++;
        }
        
        $objPHPExcelActiveSheet->setCellValueExplicit("B7", "Channel/Retailer");
        $cell = $objPHPExcelActiveSheet->getCellByColumnAndRow(1, 7)->getColumn().'7';
        $objPHPExcelActiveSheet->getStyle($cell)->getFill()->applyFromArray(array('type' => \PHPExcel_Style_Fill::FILL_SOLID,'startcolor' => array('rgb' => 'C0C0C0')));
        $objPHPExcelActiveSheet->getStyle($cell)->applyFromArray($onlydBorderStyle);
        
        $objPHPExcelActiveSheet->getRowDimension('7')->setRowHeight(55);
        
        $totalRow = count($supplierList) + 8;
        
        $objPHPExcelActiveSheet->setCellValue('B2',$topFilterValue[0])->setCellValue('B3',$this->timeFilterSettings[0]);
        
        $startRow = $row = 8;
        $selCol = $col = 2;
        $objPHPExcel->setActiveSheetIndex(1);
        $objPHPExcelActiveSheet = $objPHPExcel->getActiveSheet();
        $filterAdded = false;

        $getTheFormatRang = [];
        $stCntr = 0;
        foreach($supplierList as $sKey => $data)
        {
            if(!isset($skipRank[$data]) && !$filterAdded){
                $num_rows = $objPHPExcelActiveSheet->getHighestRow();
                $objPHPExcelActiveSheet->insertNewRowBefore($num_rows+1, 1);
                $filterAdded = true;
                
                $totalColsStr = $objPHPExcelActiveSheet->getHighestColumn();
                $totalCols = \PHPExcel_Cell::columnIndexFromString($totalColsStr);

                for($i=1; $i<=$totalCols-1; $i++)
                {
                    $cell = $objPHPExcelActiveSheet->getCellByColumnAndRow($i, 11)->getColumn().'11';
                    $objPHPExcelActiveSheet->getStyle($cell)->getFill()->applyFromArray(array('type' => \PHPExcel_Style_Fill::FILL_SOLID,'startcolor' => array('rgb' => '000000')));
                    $objPHPExcelActiveSheet->getStyle($cell)->applyFromArray($onlydBorderStyle);
                }

                $objPHPExcelActiveSheet->setAutoFilter('B11:'.$totalColsStr."11");
                $row++;
            }
        
            $objPHPExcelActiveSheet->setCellValueByColumnAndRow(1, $row, $data);
            $cell = $objPHPExcelActiveSheet->getCellByColumnAndRow(1, $row)->getColumn().$row;
            $objPHPExcelActiveSheet->getStyle($cell)->applyFromArray($onlydBorderStyle);

            foreach($allBrandList as $bKey => $bData)
            {
                // $ Rank
                $colName = $this->getNameFromNumber($selCol+1);
                if($stCntr==0) $getTheFormatRang['rank'][$this->getNameFromNumber($selCol)] = $row;
                if(!$skipRank[$data])
                    $objPHPExcelActiveSheet->setCellValueByColumnAndRow($selCol, $row, "=RANK(".$colName.$row.","."$".$colName."$".($startRow+4).":"."$".$colName."$".$totalRow.",0)");
                else
                    $objPHPExcelActiveSheet->setCellValueByColumnAndRow($selCol, $row, "");
                
                //$cell = $objPHPExcelActiveSheet->getCellByColumnAndRow($selCol, $row)->getColumn().$row;
                //$objPHPExcelActiveSheet->getStyle($cell)->applyFromArray($onlydBorderStyle);
                
                $bName = '"'.$bData.'"';
                // Current Dollars
                if($stCntr==0) $getTheFormatRang['current_dollars'][$this->getNameFromNumber($selCol+1)] = $row;
                $objPHPExcelActiveSheet->setCellValueByColumnAndRow($selCol+1, $row, "=SUMIFS('RetailersData'!"."$"."E$rowFileStartNo:"."$"."E$rowFileEndNo,'RetailersData'!"."$"."A$rowFileStartNo:"."$"."A$rowFileEndNo,Retailers!"."$"."B"."$"."3,'RetailersData'!"."$"."B$rowFileStartNo:"."$"."B$rowFileEndNo,Retailers!"."$"."B"."$"."2,'RetailersData'!"."$"."C$rowFileStartNo:"."$"."C$rowFileEndNo,$bName,'RetailersData'!"."$"."D$rowFileStartNo:"."$"."D$rowFileEndNo,"."$"."B".$row.")");
                //$cell = $objPHPExcelActiveSheet->getCellByColumnAndRow($selCol+1, $row)->getColumn().$row;
                //$objPHPExcelActiveSheet->getStyle($cell)->applyFromArray($onlydBorderStyle)->getNumberFormat()->setFormatCode('#,##');
                
                // YAGO Dollars
                if($stCntr==0) $getTheFormatRang['yago_dollars'][$this->getNameFromNumber($selCol+2)] = $row;
                $objPHPExcelActiveSheet->setCellValueByColumnAndRow($selCol+2, $row, "=SUMIFS('RetailersData'!"."$"."F$rowFileStartNo:"."$"."F$rowFileEndNo,'RetailersData'!"."$"."A$rowFileStartNo:"."$"."A$rowFileEndNo,Retailers!"."$"."B"."$"."3,'RetailersData'!"."$"."B$rowFileStartNo:"."$"."B$rowFileEndNo,Retailers!"."$"."B"."$"."2,'RetailersData'!"."$"."C$rowFileStartNo:"."$"."C$rowFileEndNo,$bName,'RetailersData'!"."$"."D$rowFileStartNo:"."$"."D$rowFileEndNo,"."$"."B".$row.")");
                //$cell = $objPHPExcelActiveSheet->getCellByColumnAndRow($selCol+2, $row)->getColumn().$row;
                //$objPHPExcelActiveSheet->getStyle($cell)->applyFromArray($onlydBorderStyle)->getNumberFormat()->setFormatCode('#,##');
   
                // Dollar Change
                $fColName = $this->getNameFromNumber(($selCol+3)-2);
                $lColName = $this->getNameFromNumber(($selCol+3)-1);
                if($stCntr==0) $getTheFormatRang['dollar_change'][$this->getNameFromNumber($selCol+3)] = $row;
                $objPHPExcelActiveSheet->setCellValueByColumnAndRow($selCol+3, $row, "=".$fColName.$row."-".$lColName.$row);
                //$cell = $objPHPExcelActiveSheet->getCellByColumnAndRow($selCol+3, $row)->getColumn().$row;
                //$objPHPExcelActiveSheet->getStyle($cell)->applyFromArray($onlydBorderStyle)->getNumberFormat()->setFormatCode('#,##');
   
                // % Change to LY
                if($stCntr==0) $getTheFormatRang['change_to_ly'][$this->getNameFromNumber($selCol+4)] = $row;
                $objPHPExcelActiveSheet->setCellValueByColumnAndRow($selCol+4, $row, "=IFERROR(".$fColName.$row."/".$lColName.$row."-1,0)");
                //$cell = $objPHPExcelActiveSheet->getCellByColumnAndRow($selCol+4, $row)->getColumn().$row;
                //$objPHPExcelActiveSheet->getStyle($cell)->applyFromArray($onlydBorderStyle)->getNumberFormat()->setFormatCode('#,##0.0%');

                // Category Share
                $fColName = $this->getNameFromNumber(($selCol+5)-4);
                if($stCntr==0) $getTheFormatRang['category_share'][$this->getNameFromNumber($selCol+5)] = $row;
                $objPHPExcelActiveSheet->setCellValueByColumnAndRow($selCol+5, $row, "=IFERROR(".$fColName.$row."/$"."D"."$".$startRow.",0)");
                //$cell = $objPHPExcelActiveSheet->getCellByColumnAndRow($selCol+5, $row)->getColumn().$row;
                //$objPHPExcelActiveSheet->getStyle($cell)->applyFromArray($onlydBorderStyle)->getNumberFormat()->setFormatCode('#,##0.0%');

                // Category Share Chg
                $fColName = $this->getNameFromNumber($selCol+5);
                $sColName = $this->getNameFromNumber($selCol+2);
                if($stCntr==0) $getTheFormatRang['category_share_chg'][$this->getNameFromNumber($selCol+6)] = $row;
                $objPHPExcelActiveSheet->setCellValueByColumnAndRow($selCol+6, $row, "=IFERROR((".$fColName.$row."-(".$sColName.$row."/$"."E".$row."))*100,0)");
                //$cell = $objPHPExcelActiveSheet->getCellByColumnAndRow($selCol+6, $row)->getColumn().$row;
                //$objPHPExcelActiveSheet->getStyle($cell)->applyFromArray($onlydBorderStyle)->getNumberFormat()->setFormatCode('#,##0.0');

                // Market Share
                $fColName = $this->getNameFromNumber($selCol+1);
                if($stCntr==0) $getTheFormatRang['market_share'][$this->getNameFromNumber($selCol+7)] = $row;
                $objPHPExcelActiveSheet->setCellValueByColumnAndRow($selCol+7, $row, "=IFERROR((".$fColName.$row."/$".$fColName.$startRow."),0)");
                //$cell = $objPHPExcelActiveSheet->getCellByColumnAndRow($selCol+7, $row)->getColumn().$row;
                //$objPHPExcelActiveSheet->getStyle($cell)->applyFromArray($onlydBorderStyle)->getNumberFormat()->setFormatCode('#,##0.0%');

                // Market Share Chg
                $fColName = $this->getNameFromNumber($selCol+7);
                $sColName = $this->getNameFromNumber($selCol+2);
                if($stCntr==0) $getTheFormatRang['market_share_chg'][$this->getNameFromNumber($selCol+8)] = $row;
                $objPHPExcelActiveSheet->setCellValueByColumnAndRow($selCol+8, $row, "=IFERROR((".$fColName.$row."-(".$sColName.$row."/".$sColName."$".$startRow."))*100,0)");
                //$cell = $objPHPExcelActiveSheet->getCellByColumnAndRow($selCol+8, $row)->getColumn().$row;
                //$objPHPExcelActiveSheet->getStyle($cell)->applyFromArray($onlydBorderStyle)->getNumberFormat()->setFormatCode('#,##0.0');
                
                $selCol += 9;
            }
            
            $stCntr++;
            $selCol = $col;
            $row++;
        }

        if(isset($getTheFormatRang)){
            foreach ($getTheFormatRang as $ty => $colArr) {
                foreach ($colArr as $colNm => $valStPos) {
                    switch($ty){
                        case "rank":
                            $objPHPExcelActiveSheet->getStyle($colNm.$valStPos.':'.$colNm.$row)->applyFromArray($onlydBorderStyle);
                            break;
                        case "current_dollars":
                            $objPHPExcelActiveSheet->getStyle($colNm.$valStPos.':'.$colNm.$row)->getNumberFormat()->setFormatCode('#,##');
                            break;
                        case "yago_dollars":
                            $objPHPExcelActiveSheet->getStyle($colNm.$valStPos.':'.$colNm.$row)->getNumberFormat()->setFormatCode('#,##');
                            break;
                        case "dollar_change":
                            $objPHPExcelActiveSheet->getStyle($colNm.$valStPos.':'.$colNm.$row)->getNumberFormat()->setFormatCode('#,##');
                            break;
                        case "change_to_ly":
                            $objPHPExcelActiveSheet->getStyle($colNm.$valStPos.':'.$colNm.$row)->getNumberFormat()->setFormatCode('#,##0.0%');
                            break;
                        case "category_share":
                            $objPHPExcelActiveSheet->getStyle($colNm.$valStPos.':'.$colNm.$row)->getNumberFormat()->setFormatCode('#,##0.0%');
                            break;
                        case "category_share_chg":
                            $objPHPExcelActiveSheet->getStyle($colNm.$valStPos.':'.$colNm.$row)->getNumberFormat()->setFormatCode('#,##0.0');
                            break;
                        case "market_share":
                            $objPHPExcelActiveSheet->getStyle($colNm.$valStPos.':'.$colNm.$row)->getNumberFormat()->setFormatCode('#,##0.0%');
                            break;
                        case "market_share_chg":
                            $objPHPExcelActiveSheet->getStyle($colNm.$valStPos.':'.$colNm.$row)->getNumberFormat()->setFormatCode('#,##0.0');
                            break;
                    }
                }
            }
        }
        
        $objPHPExcel->getDefaultStyle()->applyFromArray(
            array(
                'fill' => array(
                    'type'  => \PHPExcel_Style_Fill::FILL_SOLID,
                    'color' => array('argb' => 'FFFFFF')
                ),
            )
        );
        
        $objPHPExcelActiveSheet->setCellValueByColumnAndRow(1, $row, "Total");
        $cell = $objPHPExcelActiveSheet->getCellByColumnAndRow(1, $row)->getColumn().$row;
        $objPHPExcelActiveSheet->getStyle($cell)->applyFromArray($onlydBorderStyle);
        $selCol = 2;
        foreach($allBrandList as $bKey => $bData)
        {
            for ($i = 0; $i <= 8; $i++)
            {
                if($i != 0)
                {
                    if($i == 4) // % Change to LY
                    {
                        $fColName = $this->getNameFromNumber(($selCol)-3);
                        $lColName = $this->getNameFromNumber(($selCol)-2);
                        $objPHPExcelActiveSheet->setCellValueByColumnAndRow($selCol, $row, "=IFERROR(".$fColName.$row."/".$lColName.$row."-1,0)");
                        $cell = $objPHPExcelActiveSheet->getCellByColumnAndRow($selCol, $row)->getColumn().$row;
                        $objPHPExcelActiveSheet->getStyle($cell)->applyFromArray($onlydBorderStyle)->getNumberFormat()->setFormatCode('#,##0.0%');
                    }
                    elseif($i == 5) // Category Share
                    {
                        $fColName = $this->getNameFromNumber(($selCol)-4);
                        $objPHPExcelActiveSheet->setCellValueByColumnAndRow($selCol, $row, "=IFERROR(".$fColName.$row."/$"."D"."$".$startRow.",0)");
                        $cell = $objPHPExcelActiveSheet->getCellByColumnAndRow($selCol, $row)->getColumn().$row;
                        $objPHPExcelActiveSheet->getStyle($cell)->applyFromArray($onlydBorderStyle)->getNumberFormat()->setFormatCode('#,##0.0%');
                    }
                    elseif($i == 7) // Market Share
                    {
                        $fColName = $this->getNameFromNumber($selCol-6);
                        $objPHPExcelActiveSheet->setCellValueByColumnAndRow($selCol, $row, "=IFERROR((".$fColName.$row."/$".$fColName.$startRow."),0)");
                        $cell = $objPHPExcelActiveSheet->getCellByColumnAndRow($selCol, $row)->getColumn().$row;
                        $objPHPExcelActiveSheet->getStyle($cell)->applyFromArray($onlydBorderStyle)->getNumberFormat()->setFormatCode('#,##0.0%');
                    }
                    else
                    {
                        $colName = $this->getNameFromNumber($selCol);
                        $objPHPExcelActiveSheet->setCellValueByColumnAndRow($selCol, $row, "=SUM(".$colName.$startRow.":".$colName.$totalRow.")");
                        $cell = $objPHPExcelActiveSheet->getCellByColumnAndRow($selCol, $row)->getColumn().$row;
                        $objPHPExcelActiveSheet->getStyle($cell)->applyFromArray($onlydBorderStyle)->getNumberFormat()->setFormatCode('#,##');
                    }
                }
                else
                {
                    $cell = $objPHPExcelActiveSheet->getCellByColumnAndRow($selCol, $row)->getColumn().$row;
                    $objPHPExcelActiveSheet->getStyle($cell)->applyFromArray($onlydBorderStyle);
                }
                $selCol++;
            }
        }
        
        $objPHPExcel->getSheetByName('RetailersData')->setSheetState(\PHPExcel_Worksheet::SHEETSTATE_HIDDEN);
        $objPHPExcel->getSheetByName('Worksheet')->setSheetState(\PHPExcel_Worksheet::SHEETSTATE_HIDDEN);
        $objPHPExcel->getActiveSheet()->freezePane('C8');
        
        /* $objPHPExcel->getActiveSheet()->getColumnDimension('E')->setVisible(FALSE);
        $objPHPExcel->getActiveSheet()->getColumnDimension('F')->setVisible(FALSE);
        $objPHPExcel->getActiveSheet()->getColumnDimension('H')->setVisible(FALSE);
        $objPHPExcel->getActiveSheet()->getColumnDimension('I')->setVisible(FALSE); */

        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
        //$objWriter->setPreCalculateFormulas(true);
        $objWriter->setPreCalculateFormulas(false);
        $fileName = "Retailer-Report-" . date("Y-m-d-h-i-s") . ".xlsx";
        $savePath = dirname(__FILE__)."/../uploads/Retailer-Report/";
        chdir($savePath);
        $objWriter->save($fileName);
        $this->jsonOutput['downloadLink'] = $this->settingVars->get_full_url()."/".basename(dirname(dirname(__FILE__)))."/uploads/Retailer-Report/".$fileName;
    }
    
    public function getNameFromNumber($num) {
        $numeric = $num % 26;
        $letter = chr(65 + $numeric);
        $num2 = intval($num / 26);
        if ($num2 > 0) {
            return $this->getNameFromNumber($num2 - 1) . $letter;
        } else {
            return $letter;
        }
    }
    
    public function buildDataArray($fields) {
        if (empty($fields))
            return false;

        $configurationCheck = new config\ConfigurationCheck($this->settingVars, $this->queryVars);
        $configurationCheck->buildDataArray($fields);
        $this->dbColumnsArray = $configurationCheck->dbColumnsArray;
        $this->displayCsvNameArray = $configurationCheck->displayCsvNameArray;
        $this->displayDbColumnArray = $configurationCheck->displayDbColumnArray;
        return;
    }	
	
	public function buildPageArray() {

        $fetchConfig = false;
        $skuFieldPart = explode("#", $this->skuField);
        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
            $fetchConfig = true;
            $this->jsonOutput['pageConfig'] = array(
                'enabledFilters' => $this->getPageConfiguration('filter_settings', $this->settingVars->pageID)
            );
        }
        
        $supplierField = strtoupper($this->dbColumnsArray[$this->supplierField]);
        $brandField = strtoupper($this->dbColumnsArray[$this->brandField]);
        $mainFilterField = strtoupper($this->dbColumnsArray[$this->mainFilterField]);
        $supplierOrderByField = strtoupper($this->dbColumnsArray[$this->supplierOrderByField]);
        $this->supplierField = $this->settingVars->dataArray[$supplierField]['NAME'];
        $this->brandField = $this->settingVars->dataArray[$brandField]['NAME'];
        $this->mainFilterField = $this->settingVars->dataArray[$mainFilterField]['NAME'];
        $this->supplierOrderByField = $this->settingVars->dataArray[$supplierOrderByField]['NAME'];
        
        return;
    }
}

?>