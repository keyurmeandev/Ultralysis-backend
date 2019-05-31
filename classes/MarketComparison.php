<?php

namespace classes;

ini_set("memory_limit", "350M");

/** PHPExcel_IOFactory */
require_once '../ppt/Classes/PHPExcel/IOFactory.php';

use datahelper;
use projectsettings;
use filters;
use db;
use config;

class MarketComparison extends config\UlConfig {
    private $objPHPExcel;
    private $positionOffset = 0;
    public  $isexport = false;
    public  $rollupFg = 'N';
    public function go($settingVars) {
    	unset($_REQUEST["FSG"]);
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES
        $this->queryPart = $this->getAll(); //" AND CONTROL_FLAG IN (2,0) ";  //PREPARE TABLE JOIN STRING USING this class getAll

        $this->settingVars->pageName = (empty($this->settingVars->pageName) && !empty($this->settingVars->pageID)) ? $this->settingVars->pageID.'_MarketComparisonPage' : $this->settingVars->pageName;

        $this->timeArray  = array( '4' => 'LW4', '13' => 'LW13', '52' => 'LW52' );
        //$this->timeArray  = array( '52' => 'LW52', '13' => 'LW13', '4' => 'LW4' );
        if ($this->settingVars->isDynamicPage) {
            $this->gridField = $this->getPageConfiguration('grid_field', $this->settingVars->pageID)[0];
            $fieldArray = array($this->gridField);

            $this->buildDataArray($fieldArray);
            $this->buildPageArray();
        }else{
        	$this->configurationFailureMessage();
        }

        $action = $_REQUEST["action"];
        $this->isexport = ($action == 'exportGrid') ? true : false;
        switch ($action) {
            case "exportGrid":{
                $this->rollupFg = isset($_REQUEST["defaultYearWeek"]) && !empty($_REQUEST["defaultYearWeek"]) && $_REQUEST["defaultYearWeek"]=='Rollup 4' ? 'Y' : 'N';
                $this->exportDafYrWk = isset($_REQUEST["defaultYearWeek"]) && !empty($_REQUEST["defaultYearWeek"]) && $_REQUEST["defaultYearWeek"]!='Rollup 4' ? $_REQUEST["defaultYearWeek"] : '52';
                $this->getMarketGridData();
                $this->getMarketChartData();
                $this->exportXls();
                break;
            }
            case "getData" : {
                $this->getMarketGridData();
                $this->getMarketChartData();
                break;
            }
        }

        return $this->jsonOutput;
    }

    private function getMarketGridData() {

        $marketList = (isset($_REQUEST['marketIds']) && !empty($_REQUEST['marketIds'])) ? explode(",", $_REQUEST['marketIds']) : array();
        
		$marketGridData = array();

        if(is_array($marketList) && !empty($marketList)){

            $this->settingVars->tableUsedForQuery = $this->measureFields = $options = array();
            $this->measureFields[] = $this->storeIDField;
            $this->measureFields[] = $this->storeNameField;
            $this->measureFields[] = $this->gridFieldName;

            //$this->measureFields = $this->prepareTablesUsedForMeasure($_REQUEST['ValueVolume']);
            $_REQUEST['requestedChartMeasure'] = $measureKey = 'M' . $_REQUEST['ValueVolume'];
            $measure = $this->settingVars->measureArray[$measureKey];
            $this->measureAliase = $measure['ALIASE'];

    		$measureSelectionArr = array();
            $qpart = "";
            if(is_array($this->timeArray) && !empty($this->timeArray)){
                foreach ($this->timeArray as $timekey => $timeval) {
                    filters\timeFilter::getTimeFrame($timekey, $this->settingVars);

                    foreach ($marketList as $marketkey => $marketval) {
                        if (!empty(filters\timeFilter::$tyWeekRange)){
                            $options['tyLyRange'][$timeval.'_TY_'.$measure['ALIASE']."_".$marketkey] = filters\timeFilter::$tyWeekRange.
                            " AND ".$this->storeIDField."= '".$marketval."' ";
                        }

                        if (!empty(filters\timeFilter::$lyWeekRange)){
                            $options['tyLyRange'][$timeval.'_LY_'.$measure['ALIASE']."_".$marketkey] = filters\timeFilter::$lyWeekRange.
                            " AND ".$this->storeIDField."= '".$marketval."' ";
                        }
                    }

                    if($timekey == '52'){
                        $qpart = " AND (" . filters\timeFilter::$tyWeekRange . " OR " . filters\timeFilter::$lyWeekRange . ") ";
                    }
                }
                $measureSelectionArr = config\MeasureConfigure::prepareSelect($this->settingVars, $this->queryVars, array($measureKey), $options);
            }
    		$this->settingVars->useRequiredTablesOnly = true;
            if (is_array($this->measureFields) && !empty($this->measureFields)) {
                $this->prepareTablesUsedForQuery($this->measureFields);
            }

    		$this->queryPart = $this->getAll(). $qpart. " AND ".$this->storeIDField." IN (".$_REQUEST['marketIds'].") ";

            $measureSelect = implode(", ", $measureSelectionArr);
    		
            $query 	= "SELECT trim(".$this->gridFieldName.") as ACCOUNT, ". $measureSelect.", ".
                    $this->storeIDField." as marketID, Max(".$this->storeNameField.") as MARKET ".
                    " FROM " . $this->settingVars->tablename . $this->queryPart." ".
                    " GROUP BY ACCOUNT, marketID ".
                    " Order by marketID ";
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);

            foreach ($marketList as $marketkey => $marketval) {
                $marketGridData[$marketval] = array();
                if(is_array($result) && !empty($result))
                {
                    foreach ($result as $key => $row) {
                        if($row['marketID'] == $marketval){
                            $temp = array();
                            $temp['MARKET'] = $row['MARKET'];
                            $temp['ACCOUNT'] = $row['ACCOUNT'];
                            $temp['marketID'] = $row['marketID'];

                            if(is_array($this->timeArray) && !empty($this->timeArray)){
                                foreach ($this->timeArray as $timekey => $timeval) {
                                    $temp[$timeval.'_TY_'.$measure['ALIASE']] = (float) $row[$timeval.'_TY_'.$measure['ALIASE']."_".$marketkey];
                                    $temp[$timeval.'_LY_'.$measure['ALIASE']] = (float) $row[$timeval.'_LY_'.$measure['ALIASE']."_".$marketkey];

                                    $temp[$timeval.'_'.$measure['ALIASE'].'_Var'] = ($row[$timeval.'_LY_'.$measure['ALIASE']."_".$marketkey] != 0 ) ? ((($row[$timeval.'_TY_'.$measure['ALIASE']."_".$marketkey]-$row[$timeval.'_LY_'.$measure['ALIASE']."_".$marketkey])/$row[$timeval.'_LY_'.$measure['ALIASE']."_".$marketkey])*100) : 0 ;
                                }
                            }

                            $marketGridData[(int)$marketval][] = $temp;
                        }    
                    }
                }
            }
            
        }

        $this->jsonOutput['marketGridData'] = $marketGridData;
    }

    public function getMarketChartData(){
        $marketList = (isset($_REQUEST['marketIds']) && !empty($_REQUEST['marketIds'])) ? explode(",", $_REQUEST['marketIds']) : array();
        
        $marketChartData = $yearWeekArr = $supplierArr = $supplierDataArr = $selectPart = $groupByPart = $tyMydate = $measuresArray = $marketIds = array();

        if(is_array($marketList) && !empty($marketList)){
            if($this->isexport){
                filters\timeFilter::getTimeFrame($this->exportDafYrWk, $this->settingVars);
                if($this->rollupFg == "Y")
                    $timeRange = filters\timeFilter::$tyWeekRange . " OR ". filters\timeFilter::$lyWeekRange;
                else
                    $timeRange = filters\timeFilter::$tyWeekRange;
            }else{
                filters\timeFilter::getTimeFrame('52', $this->settingVars);
                $timeRange = filters\timeFilter::$tyWeekRange . " OR ". filters\timeFilter::$lyWeekRange;
            }
            $measuresFields = datahelper\Common_Data_Fetching_Functions::getMeasuresExtraTable();
            $measuresFields[] = $this->storeIDField;
            $measuresFields[] = $this->storeNameField;
            $measuresFields[] = $this->gridFieldName;
            $this->prepareTablesUsedForQuery($measuresFields);
            $this->settingVars->useRequiredTablesOnly = true;
            $this->queryPart = $this->getAll() ." AND ".$this->storeIDField." IN ( ".$_REQUEST['marketIds']." ) "; //PREPARE TABLE JOINING STRINGS USING PARENT getAll
            
            $selectPart[] = $this->storeIDField ." AS marketID ";
            $selectPart[] = " trim(".$this->gridFieldName .") AS Supplier ";
            $groupByPart[] = " marketID, Supplier ";

            $measureKey = 'M' . $_REQUEST['ValueVolume'];
            $measureIDs[] = $measureKey;
            $measuresArray[] = $this->settingVars->measureArray[$measureKey];

            $options = array();
            $measureSelectionArr = config\MeasureConfigure::prepareSelect($this->settingVars, $this->queryVars, $measureIDs, $options);
            
            $query = " SELECT " . $this->settingVars->yearperiod . " AS YEAR," . $this->settingVars->weekperiod . " AS WEEK,";
            $mydateSelect = $this->settingVars->getMydateSelect($this->settingVars->dateField);
            $query .= ($this->settingVars->dateField !='') ? " ".$mydateSelect . " AS MYDATE, " : "";
            

            $query .= (!empty($selectPart)) ? implode(",", $selectPart). ", " : " ";
            //ADD SUM/COUNT PART TO THE QUERY
            $query .= implode(",", $measureSelectionArr) . " ";
            $query .= "FROM " . $this->settingVars->tablename . $this->queryPart . " AND (" . $timeRange . ") " .
                    "GROUP BY YEAR,WEEK " .((!empty($groupByPart)) ? ",".implode(",", $groupByPart)." ": " ").
                    "ORDER BY YEAR ASC, WEEK ASC, marketID ASC ";
            
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);

            sort($marketList);
            foreach ($marketList as $marketkey => $marketval) {
                $supplierArr[$marketval] = array();
                $supplierDataArr[$marketval] = array();
            }

            if(is_array($result) && !empty($result))
            {

                foreach ($result as $key => $row) {
                    if(!in_array($row['Supplier'], $supplierArr[$row['marketID']])){
                        $supplierArr[$row['marketID']][] = $row['Supplier'];
                        $supplierColorCodeArr[$row['Supplier']]  = $row['Supplier'];
                    }

                    $account = $row['YEAR'] . "-" . $row['WEEK'];

                    foreach ($measuresArray as $measureKey => $measure) {
                        $supplierDataArr[$row['marketID']][$row['Supplier']][$account][$measure['ALIASE']] = $row[$measure['ALIASE']];
                    }

                    if(!in_array($account, $yearWeekArr)){
                                $yearWeekArr[] = $account;
                            
                        if (isset($row['MYDATE']))
                            $tyMydate[$account] = date('j M y', strtotime($row['MYDATE']));
                    }
                }
            }

            if(is_array($supplierColorCodeArr) && count($supplierColorCodeArr)>0){
                $colorArr = ['#2196f3','#f44336','#8bc34a','#ffeb3b','#3f51b5','#ffc107','#795548','#9c27b0','#4caf50','#03a9f4','#607d8b'];
                $clrcnt = 0;
                foreach ($supplierColorCodeArr as $ky => $v) {
                    if($clrcnt > 10){ $clrcnt = 0; }
                        $supplierColorCodeArr[$ky] = $colorArr[$clrcnt];
                    $clrcnt++;
                }
            }

            if(is_array($yearWeekArr) && !empty($yearWeekArr))
            {
                foreach ($yearWeekArr as $yearWeek) {

                    foreach ($marketList as $marketkey => $marketval) {
                        $marketChartData[$marketval] = (isset($marketChartData[$marketval])) ? $marketChartData[$marketval] : array();

                        $temp = array();

                        $temp['ACCOUNT'] = $yearWeek;

                        if(isset($tyMydate[$yearWeek]))
                            $temp['TYMYDATE'] = $tyMydate[$yearWeek];    

                        foreach ($measuresArray as $measureKey => $measure) {  
                            if(is_array($supplierArr[$marketval]) && !empty($supplierArr[$marketval])) {
                                foreach ($supplierArr[$marketval] as $sKey => $supplier) {
                                    $temp[$measure['ALIASE']."_".$sKey] = isset($supplierDataArr[$marketval][$supplier][$yearWeek][$measure['ALIASE']]) ? $supplierDataArr[$marketval][$supplier][$yearWeek][$measure['ALIASE']] : 0;

                                }
                            }
                        }
                        $marketChartData[$marketval][] = $temp;
                    }
                }
            }

        }
        $this->jsonOutput['LineChart'] = $marketChartData;
        $this->jsonOutput['supplierList'] = $supplierArr;
        $this->jsonOutput['supplierColorCodeList'] = $supplierColorCodeArr;
    }

    public function buildPageArray() {

        $fetchConfig = false;
        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
            $fetchConfig = true;
            $this->jsonOutput['pageConfig'] = array(
                'enabledFilters' => $this->getPageConfiguration('filter_settings', $this->settingVars->pageID)
            );

            $this->jsonOutput["gridColumns"]['ACCOUNT'] = $this->displayCsvNameArray[$this->gridField];
            $this->jsonOutput["timeSelection"] = $this->timeArray;
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

        $gridField = strtoupper($this->dbColumnsArray[$this->gridField]);
        $this->gridFieldName = $this->settingVars->dataArray[$gridField]['NAME'];

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

    private function exportXls() {
        $this->objPHPExcel = new \PHPExcel();
        $this->sheetCount = 0;
        $this->addData();
        $this->fileName = "market_comparison.xlsx";
        $this->saveAndDownload();
    }

    public function addData() {
        
        $aliase = isset($this->measureAliase) && !empty($this->measureAliase) ? $this->measureAliase : 'Value';

        if(isset($this->jsonOutput['LineChart']) && is_array($this->jsonOutput['LineChart']) && COUNT($this->jsonOutput['LineChart'])>0){
            $newDataSource = [];
            foreach ($this->jsonOutput['LineChart'] as $marketgdid => $val) {
                $valCntr = count($val);
                $cntr = 0;
                $tmpArrVal = [];
                foreach($val as $k1=>$v){
                    if($cntr%4 == 0 || $this->rollupFg != 'Y'){

                        if($cntr%4 == 0 && $this->rollupFg == 'Y' && !empty($tmpArrVal))
                                $newDataSource[$marketgdid][] = array_map('strval', array_values($tmpArrVal));

                        $tmpArrVal = [];
                        $tmpArrVal[] = $v['ACCOUNT'];
                    }

                    if(isset($this->jsonOutput['supplierList'][$marketgdid])){
                        foreach ($this->jsonOutput['supplierList'][$marketgdid] as $kid => $supName) {
                            if(!isset($tmpArrVal[$aliase.'_'.$kid]))
                                    $tmpArrVal[$aliase.'_'.$kid] = 0;

                            $tvl = isset($v[$aliase.'_'.$kid]) && !empty($v[$aliase.'_'.$kid]) ? $v[$aliase.'_'.$kid] : 0;
                            $tmpArrVal[$aliase.'_'.$kid] += (float) $tvl;
                        }
                    }
                    if($this->rollupFg != 'Y')
                        $newDataSource[$marketgdid][] = array_map('strval', array_values($tmpArrVal));
                    else if(($cntr+1) == $valCntr)
                        $newDataSource[$marketgdid][] = array_map('strval', array_values($tmpArrVal));

                $cntr++;
                }
            }
        }
                
        if(isset($this->jsonOutput['supplierList']) && is_array($this->jsonOutput['supplierList']) && COUNT($this->jsonOutput['supplierList'])>0){
            $headArr = [];
            foreach ($this->jsonOutput['supplierList'] as $marketgdid => $marketVal) {
                if(is_array($marketVal) && count($marketVal)>0){
                    $tmpArr = [];
                    foreach ($marketVal as $k2 => $v1) {
                        $tmpArr[$k2] = $v1;
                    }
                    array_unshift($tmpArr, '');
                    $headArr[$marketgdid][] = $tmpArr;
                }
            }
        }

        
        if(isset($headArr) && is_array($headArr) && COUNT($headArr)>0){
            foreach ($headArr as $mid => $hdData) {
                if($hdData[0] && isset($newDataSource[$mid]) && is_array($newDataSource[$mid]) && count($newDataSource[$mid])>0){
                    $dataSource = array_merge($hdData,$newDataSource[$mid]);
                    $chartTitle = $this->jsonOutput['marketGridData'][$mid][0]['MARKET'].' ('.$this->jsonOutput['marketGridData'][$mid][0]['marketID'].')';
                    $chartTitle = str_replace('&', '-',$chartTitle);
                    $countColmns = count($hdData[0]);

                    $key = $this->sheetCount = $this->sheetCount+1;
                    $newWorkSheet   = new \PHPExcel_Worksheet($this->objPHPExcel, (String) 'Data'.$mid);
                    $this->objPHPExcel->addSheet($newWorkSheet,$key);
                    $this->objPHPExcel->setActiveSheetIndex($key);
                    $this->addChartInExcelWithGridData($dataSource,$chartTitle,$countColmns,'Data'.$mid,$mid);
                }
            }
        }
        $this->objPHPExcel->setActiveSheetIndex(0);
    }

    public function saveAndDownload() {
        $this->objPHPExcel->setActiveSheetIndex(0);
        $filepath = $this->saveXlsxFileToServer();
        $this->jsonOutput = [];
        $this->jsonOutput['fileName'] = $filepath;
    }

    public function saveXlsxFileToServer() {
        global $objPHPExcel;
        $objWriter = \PHPExcel_IOFactory::createWriter($this->objPHPExcel, 'Excel2007');
        $objWriter->setIncludeCharts(true);
        $objWriter->save(getcwd() . DIRECTORY_SEPARATOR . "../zip/" . $this->fileName);
        $filePath = "/zip/" . $this->fileName;
        return $filePath;
    }

    private function addChartInExcelWithGridData($data,$chartTitle,$countColmns,$workSheetName,$marketID){
        $objWorksheet = $this->objPHPExcel->getActiveSheet();
        $objWorksheet->fromArray($data);
        //  Set the Labels for each data series we want to plot
        //      Datatype
        //      Cell reference for data
        //      Format Code
        //      Number of datapoints in series
        //      Data values
        //      Data Marker

        $workSheetDataInx = 'B';
        for($cntri=1; $cntri < $countColmns; $cntri++){
            $wkshtname = (String) $workSheetName.'!$'.$workSheetDataInx.'$1';
            $dataseriesLabels[] = new \PHPExcel_Chart_DataSeriesValues('String', "$wkshtname", null, 1);
            $workSheetDataInx++;
        }
        
        //  Set the X-Axis Labels
        //      Datatype
        //      Cell reference for data
        //      Format Code
        //      Number of datapoints in series
        //      Data values
        //      Data Marker
        $wkshtname = (String) $workSheetName.'!$A$2:$A$'.count($data);
        $xAxisTickValues = array(
            new \PHPExcel_Chart_DataSeriesValues('String', "$wkshtname", null, count($data)-1),
        );
        //  Set the Data values for each data series we want to plot
        //      Datatype
        //      Cell reference for data
        //      Format Code
        //      Number of datapoints in series
        //      Data values
        //      Data Marker
        $workSheetDataInx = 'B';
        for($cntri=1; $cntri < $countColmns; $cntri++){
            $wkshtname = (String) $workSheetName.'!$'.$workSheetDataInx.'$2:$'.$workSheetDataInx.'$'.count($data);
            $dataSeriesValues[] = new \PHPExcel_Chart_DataSeriesValues('Number', "$wkshtname", null, count($data)-1,[],'none');
            $workSheetDataInx++;
        }

        //  Build the dataseries
        $series = new \PHPExcel_Chart_DataSeries(
            \PHPExcel_Chart_DataSeries::TYPE_LINECHART,      // plotType
            \PHPExcel_Chart_DataSeries::GROUPING_STANDARD,   // plotGrouping
            range(0, count($dataSeriesValues)-1),            // plotOrder
            $dataseriesLabels,                               // plotLabel
            $xAxisTickValues,                                // plotCategory
            $dataSeriesValues                                // plotValues
        );


        //  Set the series in the plot area
        $plotarea = new \PHPExcel_Chart_PlotArea(null, array($series));
        //  Set the chart legend
        $legend = new \PHPExcel_Chart_Legend(\PHPExcel_Chart_Legend::POSITION_BOTTOM, null, false);
        $title = new \PHPExcel_Chart_Title($chartTitle);

        $aliase = isset($this->measureAliase) && !empty($this->measureAliase) ? $this->measureAliase : 'Value';
        $yAxisLabel = new \PHPExcel_Chart_Title($aliase);

        //  Create the chart
        $chart = new \PHPExcel_Chart(
            (String) $workSheetName, // name
            $title,         // title
            $legend,        // legend
            $plotarea,      // plotArea
            true,           // plotVisibleOnly
            0,              // displayBlanksAs
            null,           // xAxisLabel
            $yAxisLabel     // yAxisLabel
        );

        //  Set the position where the chart should appear in the worksheet
        /*$chart->setTopLeftPosition('A7');
        $chart->setBottomRightPosition('H20');*/

        $this->positionOffset = 2+$this->positionOffset;
        $chart->setTopLeftPosition(\PHPExcel_Cell::stringFromColumnIndex(0).''.($this->positionOffset));
        $this->positionOffset = 20+$this->positionOffset;
        $chart->setBottomRightPosition(\PHPExcel_Cell::stringFromColumnIndex(15).''.($this->positionOffset));

        $this->positionOffset =  $this->positionOffset+2;

        // Make HIDDEN current worksheet
        $this->objPHPExcel->getSheetByName($workSheetName)->setSheetState(\PHPExcel_Worksheet::SHEETSTATE_HIDDEN);

        //  Add the chart to the worksheet
        $objWorksheet = $this->objPHPExcel->setActiveSheetIndex(0);
        $objWorksheet->addChart($chart);

        $csvFstClm = isset($this->displayCsvNameArray[$this->gridField]) ? $this->displayCsvNameArray[$this->gridField] : ' - ';
        /*[START] Adding grid for the same Chart*/
            $headerColomnsArray = [$csvFstClm, 'Last 52 week TY '.$aliase, 'VAR %', 'Last 13 week TY '.$aliase, 'VAR %', 'Last 4 week TY '.$aliase, 'VAR %'];
            $hdInnercol = 1; $hdInnerRow = $this->positionOffset;
            $cellAlignmentStyle = array(
                'horizontal' => \PHPExcel_Style_Alignment::HORIZONTAL_CENTER,
                'vertical'   => \PHPExcel_Style_Alignment::VERTICAL_CENTER
            );
            foreach ($headerColomnsArray as $headerVal) {
                $this->objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow($hdInnercol, $hdInnerRow, $headerVal);
                $cell = $this->objPHPExcel->getActiveSheet()->getCellByColumnAndRow($hdInnercol,$hdInnerRow)->getColumn().$hdInnerRow;
                $this->objPHPExcel->getActiveSheet()->getStyle($cell)->getAlignment()->applyFromArray($cellAlignmentStyle);
                $this->objPHPExcel->getActiveSheet()->getStyle($cell)->getFill()->applyFromArray(array('type' => \PHPExcel_Style_Fill::FILL_SOLID,'startcolor' => array('rgb' =>'CEAF96')));
                $hdInnercol++;
            }

            $hdInnerRow = $this->positionOffset =  $this->positionOffset+1;
            if(isset($this->jsonOutput['marketGridData'][$marketID]) && is_array($this->jsonOutput['marketGridData'][$marketID]) && count($this->jsonOutput['marketGridData'][$marketID])>0){

                foreach ($this->jsonOutput['marketGridData'][$marketID] as $key => $dataVal) {
                    $hdInnercol = 1;
                    $this->objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow($hdInnercol, $hdInnerRow, $dataVal['ACCOUNT']);
                    $cell = $this->objPHPExcel->getActiveSheet()->getCellByColumnAndRow($hdInnercol,$hdInnerRow)->getColumn().$hdInnerRow;
                    $this->objPHPExcel->getActiveSheet()->getStyle($cell)->getAlignment()->applyFromArray($cellAlignmentStyle);
                    
                    $hdInnercol++;
                    $this->objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow($hdInnercol, $hdInnerRow, number_format($dataVal['LW52_TY_'.$aliase],0));
                    $cell = $this->objPHPExcel->getActiveSheet()->getCellByColumnAndRow($hdInnercol,$hdInnerRow)->getColumn().$hdInnerRow;
                    $this->objPHPExcel->getActiveSheet()->getStyle($cell)->getAlignment()->applyFromArray($cellAlignmentStyle);
                    
                    $hdInnercol++;
                    $this->objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow($hdInnercol, $hdInnerRow, number_format($dataVal['LW52_'.$aliase.'_Var'],1));
                    $cell = $this->objPHPExcel->getActiveSheet()->getCellByColumnAndRow($hdInnercol,$hdInnerRow)->getColumn().$hdInnerRow;
                    $this->objPHPExcel->getActiveSheet()->getStyle($cell)->getAlignment()->applyFromArray($cellAlignmentStyle);
                    
                    $hdInnercol++;
                    $this->objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow($hdInnercol, $hdInnerRow, number_format($dataVal['LW13_TY_'.$aliase],0));
                    $cell = $this->objPHPExcel->getActiveSheet()->getCellByColumnAndRow($hdInnercol,$hdInnerRow)->getColumn().$hdInnerRow;
                    $this->objPHPExcel->getActiveSheet()->getStyle($cell)->getAlignment()->applyFromArray($cellAlignmentStyle);
                    
                    $hdInnercol++;
                    $this->objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow($hdInnercol, $hdInnerRow, number_format($dataVal['LW13_'.$aliase.'_Var'],1));
                    $cell = $this->objPHPExcel->getActiveSheet()->getCellByColumnAndRow($hdInnercol,$hdInnerRow)->getColumn().$hdInnerRow;
                    $this->objPHPExcel->getActiveSheet()->getStyle($cell)->getAlignment()->applyFromArray($cellAlignmentStyle);
                    
                    $hdInnercol++;
                    $this->objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow($hdInnercol, $hdInnerRow, number_format($dataVal['LW4_TY_'.$aliase],0));
                    $cell = $this->objPHPExcel->getActiveSheet()->getCellByColumnAndRow($hdInnercol,$hdInnerRow)->getColumn().$hdInnerRow;
                    $this->objPHPExcel->getActiveSheet()->getStyle($cell)->getAlignment()->applyFromArray($cellAlignmentStyle);
                    
                    $hdInnercol++;
                    $this->objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow($hdInnercol, $hdInnerRow, number_format($dataVal['LW4_'.$aliase.'_Var'],1));
                    $cell = $this->objPHPExcel->getActiveSheet()->getCellByColumnAndRow($hdInnercol,$hdInnerRow)->getColumn().$hdInnerRow;
                    $this->objPHPExcel->getActiveSheet()->getStyle($cell)->getAlignment()->applyFromArray($cellAlignmentStyle);
                
                $hdInnerRow = $this->positionOffset =  $this->positionOffset+1;
                }
            }
        /*[END] Adding grid for the same Chart*/
    $this->positionOffset =  $this->positionOffset+2;
    }
}
?>