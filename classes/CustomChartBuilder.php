<?php

namespace classes;

use datahelper;
use projectsettings;
use filters;
use utils;
use db;
use config;

class CustomChartBuilder extends config\UlConfig {
    public $isMyDateExist = true;

    public function go($settingVars) {

        $this->initiate($settingVars); //INITIATE COMMON VARIABLES
        $this->redisCache = new utils\RedisCache($this->queryVars);
        $this->fetchConfig(); // Fetching filter configuration for page
        
        $action = $_REQUEST['action'];
        $this->isDownloadExcelFile = $_REQUEST['isDownloadExcelFile'];
        
        switch ($action) {
            case "attributeChange":
                $this->getGridData();
                break;
            case "getChartData":
                $this->getChartData();
                break;
        }
        
        return $this->jsonOutput;
    }
    
    public function getChartData()
    {
            //!isset($_REQUEST['selectedAttrValues']) || empty($_REQUEST['selectedAttrValues'])
        if(!isset($_REQUEST['selectedAttributes']) || $_REQUEST['selectedAttributes'] == "")
            return ;

        $this->settingVars->tableUsedForQuery = $this->measureFields = $gridConfig = array();
        $selectedField = $_REQUEST['selectedAttributes'];
        //$selectedAttrValues = explode(',', $_REQUEST['selectedAttrValues']);

        $name           = $this->settingVars->dataArray[$selectedField]["NAME"];
        $selectFields   = "$name AS ACCOUNT";
        $selectGroupBy  = "ACCOUNT";
        $gridConfig['ACCOUNT']['STATUS'] = false;
        $gridConfig['ACCOUNT']['TITLE']  = isset($this->settingVars->dataArray[$selectedField]['NAME_CSV']) ? $this->settingVars->dataArray[$selectedField]['NAME_CSV'] : '';
        $gridConfig['ID']['STATUS']      = true;

        //$wherePart = $name; 
        if (isset($this->settingVars->dataArray[$selectedField]["ID"]) && !empty($this->settingVars->dataArray[$selectedField]["ID"])) {
            $id = $this->settingVars->dataArray[$selectedField]["ID"];
            $selectFields .= ",$id AS ID";
            $selectGroupBy.= ",ID";
            $gridConfig['ID']['STATUS'] = false;
            $gridConfig['ID']['TITLE']  = isset($this->settingVars->dataArray[$selectedField]['ID_CSV']) ? $this->settingVars->dataArray[$selectedField]['ID_CSV'] : '';
            //$wherePart = $id; 
        }

        $this->measureFields[] = $name;
        $this->measureFields[] = $id;
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->queryPart = $this->getAll();
        $queryString = array();
        $queryString[] = "SUM((CASE WHEN " . filters\timeFilter::$tyWeekRange . " THEN 1 ELSE 0 END) * " . $this->settingVars->ProjectValue . ") AS VALUE ";
        $queryString[] = "SUM((CASE WHEN " . filters\timeFilter::$tyWeekRange . " THEN 1 ELSE 0 END) * " . $this->settingVars->ProjectVolume . ") AS VOLUME ";
        
        $query = " SELECT " . $this->settingVars->yearperiod . " AS YEAR," . $this->settingVars->weekperiod . " AS WEEK,";

        //print_r($this->settingVars->timeSelectionUnit);
        
        $mydateSelect = $this->settingVars->getMydateSelect($this->settingVars->dateField);
        $query .= ($this->settingVars->dateField !='') ? " ".$mydateSelect . " AS MYDATE, " : "";

        $query .= $selectFields.",".implode(",", $queryString) . " ";
        $query .= "FROM " . $this->settingVars->tablename .' '. trim($this->queryPart) . " AND (" . filters\timeFilter::$tyWeekRange . ") " .
            //' AND '.$wherePart.' IN ("' . implode('", "', $selectedAttrValues) . '") '.
            "GROUP BY YEAR,WEEK,$selectGroupBy ".
            "ORDER BY YEAR ASC,WEEK ASC";


        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }
        
        $lineChartData = $mydateArray = array();
        if(is_array($result) && !empty($result)){
            foreach($result as $key => $data)
            {
                if(isset($data['MYDATE'])){
                   $this->isMyDateExist = true;
                   $data['MYDATE'] = date('j M y', strtotime($data['MYDATE']));
                }
                else{
                   $this->isMyDateExist = false;
                   $result[$key]['MYDATE'] = $data['WEEK'].'-'.$data['YEAR'];
                   $data['MYDATE'] = $data['WEEK'].'-'.$data['YEAR'];
                }

                $dbKy = $data['ACCOUNT'];
                if (isset($this->settingVars->dataArray[$selectedField]["ID"]) && !empty($this->settingVars->dataArray[$selectedField]["ID"])) {
                    $data['ACCOUNT'] = $data['ACCOUNT']." (".$data['ID'].")";
                    $dbKy             = $data['ID']."-".$data['ACCOUNT'];
                }
                $lineChartData[$dbKy][] = $data;
                
                $mydateArray[$data['MYDATE']] = array("YEAR" => $data['YEAR'], "WEEK" => $data['WEEK']);
                
            }
        }
        
        $finalChartArray = array();
        foreach($mydateArray as $mKey => $mydateData)
        {
            foreach($lineChartData as $sKey => $chartData)
            {
                $searchKey = array_search($mKey, array_column($chartData, "MYDATE"));
                if($searchKey !== false)
                    $finalChartArray[$sKey][] = $lineChartData[$sKey][$searchKey];
                else {
                    $account = explode("-", $sKey);
                    if(count($account) > 1)
                        $account = $account[1]." (".$account[0].")";
                    else
                        $account = $account[0];
                        
                    $finalChartArray[$sKey][] = array("MYDATE" => $mKey, "VALUE" => 0, "VOLUME" => 0, "YEAR" => $mydateData['YEAR'], "WEEK" => $mydateData['WEEK'], "ACCOUNT" => $account, "ID" => $account[0]);
                }
            }
        }
        
        //print_r($finalChartArray); exit;
        
        $maxLength = array();
        foreach($lineChartData as $key => $data)
            $maxLength[$key] = count($data);
        
        $value = max($maxLength);
        $maxLength = array_search($value, $maxLength);

        if($this->isDownloadExcelFile == "true")
        {
            $this->setDataIntoExcelFile($finalChartArray, $result);
        }
        else
        {
            $this->jsonOutput['maxLength'] = $maxLength;
            $this->jsonOutput["chartData"] = $finalChartArray;
        }
    }
    
    public function setDataIntoExcelFile($lineChartData, $result)
    {
        include_once $_SERVER['DOCUMENT_ROOT']."/ppt/Classes/PHPExcel.php";
        $objPHPExcel = new \PHPExcel();
        $objPHPExcel->getProperties()->setCreator("Ultralysis")->setTitle("Custom Chart Builder")->setSubject("Custom Chart Builder");
        
        // VALUE DATA SHEET //
        /* $retailersDataSheet = new \PHPExcel_Worksheet($objPHPExcel, "valueData");
        $objPHPExcel->addSheet($retailersDataSheet, 0);
        $objPHPExcel->setActiveSheetIndex(0);
        $dataSheet = $objPHPExcel->getActiveSheet(); */

        $mydate = array_values(array_unique(array_column($result, "MYDATE")));
        $category[] = "";
        
        $valueArray = $volumeArray = array();
        foreach($lineChartData as $key => $data)
        {
            $tmp = array();
            foreach($data as $cData)
                $tmp[$cData['MYDATE']] = $cData["VALUE"];
                
            $valueArray[$key] = $tmp;
            
            $tmp = array();
            foreach($data as $cData)
                $tmp[$cData['MYDATE']] = $cData["VOLUME"];
                
            $volumeArray[$key] = $tmp;
            
            if(!in_array($key, $category))
                $category[] = $key;
        }

        //$this->setRowData($category, $mydate, $lineChartData, $valueArray, 2, 2, $dataSheet, 1);
        // VALUE DATA SHEET //
        
        // VOLUME DATA SHEET //
        /* $retailersDataSheet = new \PHPExcel_Worksheet($objPHPExcel, "volumeData");
        $objPHPExcel->addSheet($retailersDataSheet, 1);
        $objPHPExcel->setActiveSheetIndex(1);
        $dataSheet = $objPHPExcel->getActiveSheet(); */
        
        //$this->setRowData($category, $mydate, $lineChartData, $volumeArray, 2, 2, $dataSheet, 1);
        // VOLUME DATA SHEET //
        
        // VALUE CHART SHEET //
        $retailersDataSheet = new \PHPExcel_Worksheet($objPHPExcel, "Value");
        $objPHPExcel->addSheet($retailersDataSheet, 0);
        $objPHPExcel->setActiveSheetIndex(0);
        $dataSheet = $objPHPExcel->getActiveSheet();
        
        $chart = $this->setChartData($category, $mydate, 'Value', 'Value-Chart');
        
        $dataSheet->addChart($chart);
        $this->setRowData($category, $mydate, $lineChartData, $valueArray, 23, 23, $dataSheet, 22, 'Value');
        // VALUE CHART SHEET //
        
        // VOLUME CHART SHEET //
        $retailersDataSheet = new \PHPExcel_Worksheet($objPHPExcel, "Volume");
        $objPHPExcel->addSheet($retailersDataSheet, 1);
        $objPHPExcel->setActiveSheetIndex(1);
        $dataSheet = $objPHPExcel->getActiveSheet();
        
        $chart = $this->setChartData($category, $mydate, 'Volume', 'Volume-Chart');
        $dataSheet->addChart($chart);
        $this->setRowData($category, $mydate, $lineChartData, $volumeArray, 23, 23, $dataSheet, 22, 'Volume');
        // VOLUME CHART SHEET //
        
        $objPHPExcel->getSheetByName('Worksheet')->setSheetState(\PHPExcel_Worksheet::SHEETSTATE_HIDDEN);
        $objPHPExcel->setActiveSheetIndex(0);
        
        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
        $objWriter->setPreCalculateFormulas(true);
        $objWriter->setIncludeCharts(TRUE);
        $fileName = "Custom-Chart-Builder-" . date("Y-m-d-h-i-s") . ".xlsx";
        $savePath = dirname(__FILE__)."/../uploads/Custom-Chart-Builder/";
        chdir($savePath);
        $objWriter->save($fileName);
        $this->jsonOutput['downloadLink'] = $this->settingVars->get_full_url()."/".basename(dirname(dirname(__FILE__)))."/uploads/Custom-Chart-Builder/".$fileName;
    }
    
    public function setChartData($category, $mydate, $sheetName, $chartName)
    {
        $xAxisTickValues = array(
            new \PHPExcel_Chart_DataSeriesValues('String', $sheetName.'!'.'$'.'A'.'$'.'23:'.'$'.'A'.'$'.(count($mydate)+22), null, count($mydate)),
        );
        
        $workSheetDataInx = 'B';
        for($cntri=1; $cntri < count($category); $cntri++){
            $wkshtname = (String) $sheetName.'!$'.$workSheetDataInx.''.'$'.'22';
            $dataseriesLabels[] = new \PHPExcel_Chart_DataSeriesValues('String', $wkshtname, null, 1);
            $workSheetDataInx++;
        }
        
        $workSheetDataInx = 'B';
        for($cntri=1; $cntri < count($category); $cntri++){
            $wkshtname = (String) $sheetName.'!$'.$workSheetDataInx.''.'$'.'23:$'.$workSheetDataInx.''.'$'.(count($mydate)+22);
            $dataSeriesValues[] = new \PHPExcel_Chart_DataSeriesValues('Number', $wkshtname, null, count($mydate),[],'none');
            $workSheetDataInx++;
        }
        
        $series = new \PHPExcel_Chart_DataSeries(
            \PHPExcel_Chart_DataSeries::TYPE_LINECHART,       // plotType
            \PHPExcel_Chart_DataSeries::GROUPING_STANDARD,  // plotGrouping
            range(0, count($dataSeriesValues)-1),           // plotOrder
            $dataseriesLabels,                              // plotLabel
            $xAxisTickValues,                             // plotCategory
            $dataSeriesValues                               // plotValues
        );
        
        $series->setPlotDirection(\PHPExcel_Chart_DataSeries::DIRECTION_VERTICAL);
        
        $plotarea = new \PHPExcel_Chart_PlotArea(null, array($series));
        $legend = new \PHPExcel_Chart_Legend(\PHPExcel_Chart_Legend::POSITION_BOTTOM, null, false);
        $title = new \PHPExcel_Chart_Title("");
        $xAxisLabel = new \PHPExcel_Chart_Title("");
        $yAxisLabel = new \PHPExcel_Chart_Title($sheetName);
        
        $chart = new \PHPExcel_Chart(
            $chartName,  // name
            $title,         // title
            $legend,        // legend
            $plotarea,      // plotArea
            true,           // plotVisibleOnly
            0,              // displayBlanksAs
            $xAxisLabel,           // xAxisLabel
            $yAxisLabel     // yAxisLabel
        );
        
        //$chart->setTopLeftPosition('C0');
        $chart->setBottomRightPosition('S20');
        
        return $chart;
    }
    
    public function setRowData($category, $mydate, $lineChartData, $dataArray, $row, $rowPlus, $dataSheet, $catRowStart, $sheetName)
    {
        foreach($category as $key => $data)
        {
            $account = explode("-", $data);
            if(count($account) > 1)
                $account = $account[1];
            else
                $account = $account[0];        
                
            $dataSheet->setCellValueByColumnAndRow($key, $catRowStart, $account);
        }
            
        
        foreach($mydate as $cKey => $cData)
        {
            $dataSheet->setCellValueByColumnAndRow(0, $row, $cData);
            $row++;
        }
        
        foreach($category as $key => $data)
        {
            if(isset($lineChartData[$data]))
            {
                foreach($mydate as $cKey => $cData)
                {
                    if($this->isMyDateExist == true)
                        $cData = date('j M y', strtotime($cData));
                    
                    $dataSheet->setCellValueByColumnAndRow($key, $cKey+$rowPlus, ($dataArray[$data][$cData] != "") ? $dataArray[$data][$cData] : 0);
                    $cell = $dataSheet->getCellByColumnAndRow($key, $cKey+$rowPlus)->getColumn().($cKey+$rowPlus);
                    /* if($sheetName == "Value")
                        $dataSheet->getStyle($cell)->getNumberFormat()->setFormatCode('#,##0.00');
                    else */
                    $dataSheet->getStyle($cell)->getNumberFormat()->setFormatCode('#,##');
                }
            }
        }
    }
    
    public function fetchConfig()
    {
        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
            $filterTypes = $this->getPageConfiguration('custom_chart_builder_filter_type_settings', $this->settingVars->pageID);
            $this->jsonOutput['gridConfig'] = array(
                'enabledFilters' => $this->getPageConfiguration('filter_settings', $this->settingVars->pageID)
            );
            
            $filterTypesArray = array();
            foreach($filterTypes as $fType){
                $filterTypesArray[] = array("data" => $fType, "label" => ucfirst($fType));
            }
            $this->jsonOutput['filterTypes'] = $filterTypesArray;
        }
    }
    
    public function getGridData()
    {
        if (isset($_REQUEST['selectedAttributes']) && !empty($_REQUEST['selectedAttributes'])) {
            $this->settingVars->tableUsedForQuery = $this->measureFields = $gridConfig = $finalResult = array();
            $selectedField = $_REQUEST['selectedAttributes'];
            $name = $this->settingVars->dataArray[$selectedField]["NAME"];
            $selectFields   = "$name AS ACCOUNT";
            $selectGroupBy  = "ACCOUNT";
            $gridConfig['ACCOUNT']['STATUS'] = false;
            $gridConfig['ACCOUNT']['TITLE'] = isset($this->settingVars->dataArray[$selectedField]['NAME_CSV']) ? $this->settingVars->dataArray[$selectedField]['NAME_CSV'] : '';
            $gridConfig['ID']['STATUS'] = true;

            if (isset($this->settingVars->dataArray[$selectedField]["ID"]) && !empty($this->settingVars->dataArray[$selectedField]["ID"])) {
                $id = $this->settingVars->dataArray[$selectedField]["ID"];
                $selectFields .= ",$id AS ID";
                $selectGroupBy.= ",ID";
                $gridConfig['ID']['STATUS'] = false;
                $gridConfig['ID']['TITLE'] = isset($this->settingVars->dataArray[$selectedField]['ID_CSV']) ? $this->settingVars->dataArray[$selectedField]['ID_CSV'] : '';
            }

            $this->measureFields[] = $name;
            $this->measureFields[] = $id;
            $this->settingVars->useRequiredTablesOnly = true;
            if (is_array($this->measureFields) && !empty($this->measureFields)) {
                $this->prepareTablesUsedForQuery($this->measureFields);
            }
            $this->queryPart = $this->getAll();
            $queryString = array();
            $queryString[] = "SUM((CASE WHEN " . filters\timeFilter::$tyWeekRange . " THEN 1 ELSE 0 END) * " . $this->settingVars->ProjectValue . ") AS VALUE ";
            $queryString[] = "SUM((CASE WHEN " . filters\timeFilter::$tyWeekRange . " THEN 1 ELSE 0 END) * " . $this->settingVars->ProjectVolume . ") AS VOLUME ";
            
            $query = "SELECT $selectFields, ". implode(", ",$queryString)." ";
            $query .= "FROM " . $this->settingVars->tablename .' '. trim($this->queryPart)." AND ". filters\timeFilter::$tyWeekRange;
            $query .= " GROUP BY $selectGroupBy ORDER BY VALUE DESC";
            
            $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
            if ($redisOutput === false) {
                $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
                $this->redisCache->setDataForHash($result);
            } else {
                $result = $redisOutput;
            }
            
            if(is_array($result) && !empty($result)) {
                $ROWINDEX = 1;
                foreach($result as $key => $data) 
                {
                    if($data['VALUE'] != 0 || $data['VOLUME'] != 0)
                    {
                        $finalKey = $ROWINDEX-1;
                        $finalResult[$finalKey]['VALUE']  = (double) $data['VALUE'];
                        $finalResult[$finalKey]['VOLUME'] = (int) $data['VOLUME'];
                        $finalResult[$finalKey]['DATA']   = $data['ACCOUNT'];
                        $finalResult[$finalKey]['ACCOUNT']   = $data['ACCOUNT'];
                        if (isset($this->settingVars->dataArray[$selectedField]["ID"]) && !empty($this->settingVars->dataArray[$selectedField]["ID"])) {
                            $finalResult[$finalKey]['DATA'] = $data['ID'];
                        }
                        $finalResult[$finalKey]['ROWINDEX'] = $ROWINDEX;
                        $ROWINDEX++;
                    }
                }
            }
            $this->jsonOutput["gridDataConfig"] = $gridConfig;
            $this->jsonOutput["gridData"] = $finalResult;
        }
    }
}