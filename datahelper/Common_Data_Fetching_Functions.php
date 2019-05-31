<?php

namespace datahelper;

use projectsettings;
use filters;
use utils;
use db;
use config;
use projectstructure;

class Common_Data_Fetching_Functions {

    public static $settingVars;
    public static $queryVars;
    public static $ValueVolume;

    /**
     * Use when you need to pull data for realtime filtering
     * Works with 'DataGrid_With_Realtime_Filtering' Class
     * */
    public static function gridFunctionAllData($querypart, $selectPart, $groupByPart, $jsonTag, &$jsonOutput, $indexInDataArray, $havingTYValue = "TYVALUE", $havingLYValue = "LYVALUE") {
        // $measureSelectionArr = self::getMeasures();
        $valueVolume = getValueVolume(self::$settingVars);

        /*$measureArr = $measureSelectionArr = array();
        if(is_array(self::$settingVars->pageArray["MEASURE_SELECTION_LIST"]) && !empty(self::$settingVars->pageArray["MEASURE_SELECTION_LIST"])){
            foreach (self::$settingVars->pageArray["MEASURE_SELECTION_LIST"] as $key => $measureVal) {
                $options = array();
                $measureKey = 'M' . $measureVal['measureID'];
                $measure = self::$settingVars->measureArray[$measureKey];
                
                if (!empty(filters\timeFilter::$tyWeekRange)) {
                    if(($measureVal['measureID'] == $_REQUEST['ValueVolume']))
                        $havingTYValue = "TY" . $measure['ALIASE'];
                    $measureTYValue = "TY" . $measure['ALIASE'];
                    $options['tyLyRange'][$measureTYValue] = trim(filters\timeFilter::$tyWeekRange);
                    // $measureSelectionArr[] = "SUM( (CASE WHEN " . filters\timeFilter::$tyWeekRange . " THEN 1 ELSE 0 END) * " . $measure['VAL'] . ") AS TY" . $measure['ALIASE'];
                }

                if (!empty(filters\timeFilter::$lyWeekRange)) {
                    if(($measureVal['measureID'] == $_REQUEST['ValueVolume']))
                        $havingLYValue = "LY" . $measure['ALIASE'];
                    $measureLYValue = "LY" . $measure['ALIASE'];
                    $options['tyLyRange'][$measureLYValue] = trim(filters\timeFilter::$lyWeekRange);
                    // $measureSelectionArr[] = "SUM( (CASE WHEN " . filters\timeFilter::$lyWeekRange . " THEN 1 ELSE 0 END) * " . $measure['VAL'] . ") AS LY" . $measure['ALIASE'];
                }
                $measureArr[$measureKey] = config\MeasureConfigure::prepareSelect(self::$settingVars, self::$queryVars, array($measureKey), $options);           
                $measureSelectionArr = array_merge($measureSelectionArr,$measureArr[$measureKey]);
            }
        }*/

        $projectStructureType = "projectstructure\\".self::$settingVars->projectStructureType;
        $structureClass = new $projectStructureType();
        $measureSelectPart = $structureClass->prepareMeasureSelectPart(self::$settingVars, self::$queryVars);
        $measureSelectionArr = $measureSelectPart['measureSelectionArr'];
        $havingTYValue = $measureSelectPart['havingTYValue'];
        $havingLYValue = $measureSelectPart['havingLYValue'];
        
        $jsonNodes = array();

        $tablename = explode(",", self::$settingVars->tablename);
        if (self::$settingVars->dataArray[$indexInDataArray]['special'] == 1 && !in_array(self::$settingVars->dataArray[$indexInDataArray]['tablename'], $tablename)) {
            $tableName = self::$settingVars->tablename . "," . self::$settingVars->dataArray[$indexInDataArray]['tablename'];
            $queryPart = $querypart . " AND " . self::$settingVars->dataArray[$indexInDataArray]['connectingField'];
            $tablename = explode(",", self::$settingVars->tablename);
        } else {
            $tableName = self::$settingVars->tablename;
            $queryPart = $querypart;
        }

        $havingArray = $filterArray = array();

        if (!empty(filters\timeFilter::$tyWeekRange)) {
            $filterArray[] = filters\timeFilter::$tyWeekRange;
            $havingArray[] = $havingTYValue."<>0";
        }

        if (!empty(filters\timeFilter::$lyWeekRange)) {
            $filterArray[] = filters\timeFilter::$lyWeekRange;
            $havingArray[] = $havingLYValue."<>0";
        }

        $daysWhere = (!empty($filterArray)) ? " AND (" . implode(" OR ", $filterArray) . ") " : "";

        //ADD SUM/COUNT PART TO THE QUERY
        /*$query = "SELECT " . implode(",", $selectPart) . "," . implode(",", $measureSelectionArr) . " " .
                "FROM " . $tableName . $queryPart . $daysWhere .
                "GROUP BY " . implode(",", $groupByPart) . " " .
                "HAVING  ".implode(" OR ", $havingArray)."  ORDER BY ".$havingTYValue." DESC";*/

        $query = "SELECT " . implode(",", $selectPart) . ", " . implode(",", $measureSelectionArr) . " " .
                "FROM " . $tableName . $queryPart . trim($daysWhere) .' '.
                "GROUP BY " . implode(",", $groupByPart);

        $redisCache = new utils\RedisCache(self::$queryVars);
        $redisOutput = $redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = self::$queryVars->queryHandler->runQuery($query, self::$queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }

        /*$requiredGridFields[] = $havingTYValue;
        $requiredGridFields[] = $havingLYValue;
        if(isset($selectPart) && is_array($selectPart) && count($selectPart)>0){
            foreach ($selectPart as $ky => $selPtVal) {
                $colVal = explode(' AS ', $selPtVal);
                if(isset($colVal[1]) && !empty($colVal[1])) {
                    $requiredGridFields[] = trim($colVal[1]);
                }else{
                    $requiredGridFields[] = trim($selPtVal);
                }
            }
        }
        $result = $redisCache->getRequiredData($result, $requiredGridFields, $havingTYValue, $havingTYValue, $havingLYValue);

        $tempResult = array();
        foreach ($result as $key => $data) {
            //COLLECT ALL QUERY ALIASES TO ADD AS JSON-TAG 
            if (empty($jsonNodes) || count($jsonNodes) != count($data))
                $jsonNodes = array_keys($data);
            $temp = array();
            foreach ($jsonNodes as $index => $node) {
                $temp[$node] = ($data[$node]) ? htmlspecialchars_decode($data[$node]) : 0;
            }
            $tempResult[] = $temp;
        }*/
        $jsonOutput[$jsonTag] = $result;
    }

    /**
     * Use when you need to pull data without realtime filtering
     * */
    /*public static function gridFunction($querypart, $selectPart, $groupByPart, $jsonTag, &$jsonOutput) {
        $jsonNodes = array();
        self::$ValueVolume = getValueVolume(self::$settingVars);
        $filterArray = array();

        if (!empty(filters\timeFilter::$tyWeekRange)) {
            $selectPart[] = "SUM( (CASE WHEN " . filters\timeFilter::$tyWeekRange . " THEN 1 ELSE 0 END) * " . self::$ValueVolume . ") AS TY" . self::$settingVars->measureArray['M' . ($_GET['ValueVolume'] - 1)]['ALIASE'];
            $filterArray[] = filters\timeFilter::$tyWeekRange;
        }

        if (!empty(filters\timeFilter::$lyWeekRange)) {
            $selectPart[] = "SUM( (CASE WHEN " . filters\timeFilter::$lyWeekRange . " THEN 1 ELSE 0 END) * " . self::$ValueVolume . ") AS LY" . self::$settingVars->measureArray['M' . ($_GET['ValueVolume'] - 1)]['ALIASE'] . " ";
            $filterArray[] = filters\timeFilter::$lyWeekRange;
        }

        $daysWhere = (!empty($filterArray)) ? " AND (" . implode(" OR ", $filterArray) . ") " : "";

        $query = "SELECT " . implode(",", $selectPart) .
                "FROM " . self::$settingVars->tablename . $querypart . $daysWhere .
                "GROUP BY " . implode(",", $groupByPart) . " " .
                "HAVING TY" . self::$settingVars->measureArray['M' . ($_GET['ValueVolume'] - 1)]['ALIASE'] . "<>0 OR LY" . self::$settingVars->measureArray['M' . ($_GET['ValueVolume'] - 1)]['ALIASE'] . "<>0 " .
                "ORDER BY TY" . self::$settingVars->measureArray['M' . ($_GET['ValueVolume'] - 1)]['ALIASE'] . " DESC";
        //print $query;exit;
        $result = self::$queryVars->queryHandler->runQuery($query, self::$queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
        foreach ($result as $key => $data) {

            //COLLECT ALL QUERY ALIASES TO ADD AS JSON-TAG 
            if (empty($jsonNodes) || count($jsonNodes) != count($data))
                $jsonNodes = array_keys($data);

            foreach ($jsonNodes as $index => $node) {
                $temp = array();
                $temp[$node] = htmlspecialchars_decode($data[$node]);
            }
            $jsonOutput[$jsonTag][] = $temp;
        }
    }*/

    public static function getMeasuresExtraTable() {
        $fieldsList = array();
        if(is_array(self::$settingVars->pageArray["MEASURE_SELECTION_LIST"]) && !empty(self::$settingVars->pageArray["MEASURE_SELECTION_LIST"])){
            foreach (self::$settingVars->pageArray["MEASURE_SELECTION_LIST"] as $key => $measureVal) {
                $measureKey = 'M' . $measureVal['measureID'];
                $measure = self::$settingVars->measureArray[$measureKey];
                //foreach (self::$settingVars->measureArray as $key => $measure) {
                    if (array_key_exists('usedFields', $measure) && is_array($measure['usedFields']) && !empty($measure['usedFields'])) {
                        foreach ($measure['usedFields'] as $usedField) {
                            $fieldsList[] = $usedField;
                        }
                    }
                //}
            }
        }

        if(isset($_REQUEST['requestedChartMeasure']) && !empty($_REQUEST['requestedChartMeasure']) ){
            $measureKey = $_REQUEST['requestedChartMeasure'];
            $measure = self::$settingVars->measureArray[$measureKey];
            if (is_array($measure) && array_key_exists('usedFields', $measure) && is_array($measure['usedFields']) && !empty($measure['usedFields'])) {
                foreach ($measure['usedFields'] as $usedField) {
                    $fieldsList[] = $usedField;
                }
            }
        }
        return $fieldsList;
    }

    public static function getAllMeasuresExtraTable() {
        $fieldsList = array();
        if(is_array(self::$settingVars->measureArray) && !empty(self::$settingVars->measureArray)){
            foreach (self::$settingVars->measureArray as $key => $measureVal) {
                $measure = self::$settingVars->measureArray[$key];
                if (array_key_exists('usedFields', $measure) && is_array($measure['usedFields']) && !empty($measure['usedFields'])) {
                    foreach ($measure['usedFields'] as $usedField) {
                        $fieldsList[] = $usedField;
                    }
                }
            }
        }

        if(isset($_REQUEST['requestedChartMeasure']) && !empty($_REQUEST['requestedChartMeasure']) ){
            $measureKey = $_REQUEST['requestedChartMeasure'];
            $measure = self::$settingVars->measureArray[$measureKey];
            if (is_array($measure) && array_key_exists('usedFields', $measure) && is_array($measure['usedFields']) && !empty($measure['usedFields'])) {
                foreach ($measure['usedFields'] as $usedField) {
                    $fieldsList[] = $usedField;
                }
            }
        }
        return $fieldsList;
    }    
    
    /*     * *
     * RETURNS AN ARRAY CONTAINING 'SUM' CLAUSE OF MEASURES, EQUIPED WITH TIME SELECTION RANGE
     * * */

    // public static function getMeasures() {
    //     $measureSelectionArr = array();
    //     foreach (self::$settingVars->measureArray as $key => $measure) {
    //         if ($measure['attr'] == 'SUM') { //INCLUDE ONLY THOSE MEASURES HAVE 'SUM' AS THEIR 'attr'
    //             if (!empty(filters\timeFilter::$tyWeekRange))
    //                 $measureSelectionArr[] = "SUM( (CASE WHEN " . filters\timeFilter::$tyWeekRange . " THEN 1 ELSE 0 END) * " . $measure['VAL'] . ") AS TY" . $measure['ALIASE'];

    //             if (!empty(filters\timeFilter::$lyWeekRange))
    //                 $measureSelectionArr[] = "SUM( (CASE WHEN " . filters\timeFilter::$lyWeekRange . " THEN 1 ELSE 0 END) * " . $measure['VAL'] . ") AS LY" . $measure['ALIASE'];
    //         }
    //     }

    //     /** Usefull when debugging, DON'T DELETE * */
    //     //header('Content-Type: application/json');
    //     //print_r($measureSelectionArr);exit;

    //     return $measureSelectionArr;
    // }

    /*     * *
     * RETURNS AN ARRAY CONTAINING 'SUM' AND 'COUNT' CLAUSE OF MEASURES
     * SINCE DDB IS ALL ABOUT TY , THE RETURNING CLAUSES ARE NOT EQUIPED WITH TIME SELECTION RANGE
     * * 
    public static function getMeasuresForDDB() {
        $measureSelectionArr    = array();
        $measureHavingArr       = array();

        foreach(self::$settingVars->measureArray as $key=>$currentMeasure){
            switch($currentMeasure['attr']){
                case "SUM":
                    $measureSelectionArr[]      = "SUM(IFNULL(" . $currentMeasure['VAL'] . ",0)) AS '" . $currentMeasure['ALIASE']."'";
                    if( in_array($currentMeasure['ALIASE'],array('VALUE','VOLUME') ))
                        $measureHavingArr[]         = $currentMeasure['ALIASE']." <>0 ";
                    break;
                case "COUNT":
                    $measureSelectionArr[]      = "COUNT(DISTINCT(CASE WHEN ". self::$settingVars->ProjectVolume .">0 THEN ".$currentMeasure['VAL']." END)) AS '" . $currentMeasure['ALIASE']."'";
                    break;
            }
        }

        /*
        * Usefull when debuggin, DON'T DELETE
        * header('Content-Type: application/json');
        * print_r($measureSelectionArr);exit;
        

        return  array(
                    'selectPart'=>$measureSelectionArr,
                    'havingPart'=>$measureHavingArr
                );
    }*/

    /*     * *
     * RETURNS AN ARRAY CONTAINING 'SUM' CLAUSE OF MEASURES, NOT EQUIPED WITH TIME SELECTION RANGE
     * * */

  //   public static function getMeasuresForLineChart($measureArray = array()) {
  //       $measureSelectionArr = array();
  //       $measureArray = (is_array($measureArray) && !empty($measureArray)) ? $measureArray : self::$settingVars->measureArray;
  //       foreach ($measureArray as $key => $measure) 
		// {
		// 	switch ($measure['attr']) 
		// 	{
		// 		case "SUM":
		// 			$measureSelectionArr[] = "SUM(IFNULL(" . $measure['VAL'] . ",0)) AS " . $measure['ALIASE'];
		// 			break;
		// 		case "COUNT":
		// 			$measureSelectionArr[] = "COUNT(" . $measure['VAL'] . ") AS " . $measure['ALIASE'];
		// 			break;
  //               case 'FUNCTION':
  //                   $className = $measure['CLASS_NAME'];
  //                   $functionName = $measure['FUNCTION_NAME'];
  //                   $measureSelectionArr[] = $className::$functionName(self::$settingVars) . " AS " . $measure['ALIASE'];
  //                   break;
		// 		default:
		// 			$measureSelectionArr[] = $measure['VAL'] . " AS " . $measure['ALIASE'];
		// 	}

  //       }

  //       /** Usefull when debuggin, DON'T DELETE * */
  //       //header('Content-Type: application/json');
  //       //print_r($measureSelectionArr);exit;

  //       return $measureSelectionArr;
  //   }

    /**
     * Use when you need to pull data for realtime filtering
     * Works with 'CustomizableChart_With_Realtime_Filtering' Class
     * */
    public static function LineChartAllData($querypart, &$jsonOutput) {
        $projectStructureTypePage = "projectstructure\\".self::$settingVars->projectStructureType."_PAGES";
        $structurePageClass = new $projectStructureTypePage();
        $structurePageClass->LineChartAllDataLogic(self::$settingVars, self::$queryVars, $jsonOutput, $querypart);
    }

    public static function LineChartAllData_CT($querypart, &$jsonOutput, $selectPart = array() , $groupByPart = array()) {
        $tyMydate = $tyValues = array();
        $lyMydate = $lyValues = array();
        $measuresArray = array();
        // $measureSelectionArr = self::getMeasuresForLineChart();

        $measureKey = 'M' . $_REQUEST['ValueVolume'];
        $measureIDs[] = $measureKey;
        $measuresArray[] = self::$settingVars->measureArray[$measureKey];

        $options = array();
        $measureSelectionArr = config\MeasureConfigure::prepareSelect(self::$settingVars, self::$queryVars, $measureIDs, $options);
        
        // $measureSelectionArr = self::getMeasuresForLineChart($measuresArray);
        $xaxisArray = self::$settingVars->tabsXaxis;

        /* MAKING A NUMERICAL FORMATION OF TIME SELECTION RANGES , MAKES IT EASY TO COMPARE '<' AND '>'
         * IF FromWeek AND ToWeek ARE '2014-1' AND '2014-10' RESPECTEDLY, THEN NUMERICAL FORMAT FOR THEM WILL BE -
         * $numberFrom=>'201401' AND $numberTo=>'201410' */
        $numberFrom = filters\timeFilter::$FromYear . (filters\timeFilter::$FromWeek < 10 ? "0" . filters\timeFilter::$FromWeek : filters\timeFilter::$FromWeek);
        $numberTo   = filters\timeFilter::$ToYear . (filters\timeFilter::$ToWeek < 10 ? "0" . filters\timeFilter::$ToWeek : filters\timeFilter::$ToWeek);

        $query = " SELECT " . self::$settingVars->yearperiod . " AS YEAR," . self::$settingVars->weekperiod . " AS WEEK,";
        $mydateSelect = self::$settingVars->getMydateSelect(self::$settingVars->dateField);
		$query .= (self::$settingVars->dateField !='') ? " ".$mydateSelect . " AS MYDATE, " : "";
        
        $query .= (!empty($selectPart)) ? implode(",", $selectPart). ", " : " ";
		//ADD SUM/COUNT PART TO THE QUERY
        $query .= implode(",", $measureSelectionArr) . " ";
        $query .= "FROM " . self::$settingVars->tablename . $querypart . " AND (" . filters\timeFilter::$tyWeekRange . ") " .
                "GROUP BY YEAR,WEEK " .((!empty($groupByPart)) ? ",".implode(",", $groupByPart)." ": " ").
                "ORDER BY YEAR ASC,WEEK ASC";
		//echo $query;exit;

        $redisCache = new utils\RedisCache(self::$queryVars);
        $redisOutput = $redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = self::$queryVars->queryHandler->runQuery($query, self::$queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }

        foreach ($result as $dataKey => $data) {
            $account = $data['YEAR'] . "-" . $data['WEEK']; //SAMPLE '2014-5'
			//$account = $data['SKU_ID']; //SAMPLE '2014-5'
			
            //JUST LIKE $numberFrom WE MAKE A NUMERICAL FORMATION OF $account TO COMPARE WITH $numberFrom AND $numberTo
            $numberAccount = $data['YEAR'] . ($data['WEEK'] < 10 ? "0" . $data['WEEK'] : $data['WEEK']); //SAMPLE '201405'
            //BASED ON $numberAccount WE DECIDE WHETHER TO PUT THE QUERY RESULT IN $tyValues OR IN $lyValues
            if ($numberAccount >= $numberFrom && $numberAccount <= $numberTo) { //$numberFrom AND $numberTo COMES HANDY HERE
                foreach ($measuresArray as $measureKey => $measure) {
                    $tyValues[$account][$measure['ALIASE']][$data['SKU_ID']] = $data[$measure['ALIASE']];
                }
				if (isset($data['MYDATE']))
					$tyMydate[$account] = date('j M y', strtotime($data['MYDATE']));
            } /*else {
                foreach ($measuresArray as $measureKey => $measure) {
                    $lyValues[$account][$measure['ALIASE']][$data['SKU_ID']] = $data[$measure['ALIASE']];
                }
				if (isset($data['MYDATE']))
					$lyMydate[$account] = date('j M y', strtotime($data['MYDATE']));
            }*/
        }

        
        /**
         * WHEN TIME SELECTION IS '2014-1' TO '2014-10' , IT'S POSSIBLE THAT SOME OF THE WEEKS HAVE NO SALES
         * IN THIS CASE LENGTH OF $tyValues AND $lyValues DOESN'T MATCH
         * THE FOLLOWING IF-ELSE BLOCK MAKES A DECISIOIN AND SET SOME MONITOR VARIABLES SO THAT WE CAN KEEP A TRACK
         * */
        //if (count($tyValues) >= count($lyValues)) {
            $validTimeSlot = "TY";
            $validArray = $tyValues;
        /*} else {
            $validTimeSlot = "LY";
            $validArray = $lyValues;
        }*/

        //print_r($validArray);
        //$validArray MEANS THE LONGEST OF $tyValues AND $lyValues
        //WE TRAVARSE THROUGH $validArray AND USE $key WHICH IS ACTUALLY A YEAR-WEEK COMBINATION e.g: 2014-05
        $value = array();
        $tempMeasureResultArray = array();
        foreach ($validArray as $key => $data) {
            $temp = array();
            //SEPERATE YEAR AND WEEK FROM YEAR-WEEK COMBINATION
            $temp = explode("-", $key);
            $year = $temp[0];
            $week = $temp[1];

            //if ($validTimeSlot == "TY") { //MEANS $tyValues IS THE LONGEST , SO WE HAVE TO MAKE $lyKey.
                $tyKey = $key;
                //$lyKey = ($year - 1) . "-" . $week; //IT'S ACTUALLY THE CORRESPONDING LY YEAR-WEEK COMBINATION
            //} else { //MEANS $tyValues IS THE LONGEST , SO WE HAVE TO MAKE $lyKey.
              //  $tyKey = ($year + 1) . "-" . $week; //.THE CORRESPONDING TY YEAR-WEEK COMBINATION
               // $lyKey = $key;
            //}
        
            $tempMeasureArray = array();
            foreach ($measuresArray as $key => $measure) {
                    foreach ($_REQUEST['skuIds'] as $skuKey => $skuValue) {
                        $tempMeasureArray[$skuKey.$measure['ALIASE']] = (key_exists($skuValue, $data[$measure['ALIASE']]) ? $data[$measure['ALIASE']][$skuValue] : 0);    
                    }
                  //$tempMeasureArray["TY" . $measure['ALIASE']] = (key_exists($tyKey, $tyValues) ? $tyValues[$tyKey][$measure['ALIASE']] : 0);
                  //$tempMeasureArray["LY" . $measure['ALIASE']] = (key_exists($lyKey, $lyValues) ? $lyValues[$lyKey][$measure['ALIASE']] : 0);
            }
            
            if(!empty($xaxisArray))
            {
                if($xaxisArray[$_REQUEST['requestedChartMeasure']] == "date"){
                    if (isset($tyMydate[$tyKey]))
                        $tempMeasureArray["TYACCOUNT"] = $tyMydate[$tyKey];
                    
                    /*if (isset($lyMydate[$lyKey]))
                        $tempMeasureArray["LYACCOUNT"] = $lyMydate[$lyKey];*/

                    $tempMeasureArray["ACCOUNT"] = htmlspecialchars($tyMydate[$tyKey]);
                } else {
                    $tempMeasureArray["TYACCOUNT"] = $tyKey;
                   // $tempMeasureArray["LYACCOUNT"] = $lyKey;
                    $tempMeasureArray["ACCOUNT"] = htmlspecialchars($tyKey);
                }
            } else {
                $tempMeasureArray["TYACCOUNT"] = $tyKey;
                //$tempMeasureArray["LYACCOUNT"] = $lyKey;
                $tempMeasureArray["ACCOUNT"] = htmlspecialchars($tyKey);
            }
			
			if (isset($tyMydate[$tyKey]))
				$tempMeasureArray["TYMYDATE"] = $tyMydate[$tyKey];
            
			/*if (isset($lyMydate[$lyKey]))
				$tempMeasureArray["LYMYDATE"] = $lyMydate[$lyKey];*/

            $tempMeasureResultArray[] = $tempMeasureArray;   
            
        }
        $jsonOutput['LineChart'] = $tempMeasureResultArray;
    }

    /*public static function getSelectForMeasure($measure) {
        if (!is_array($measure) || empty($measure))
            return '';

        switch ($measure['attr']) 
        {
            case "SUM":
                $measureSelect = "SUM(IFNULL(" . $measure['VAL'] . ",0)) AS " . $measure['ALIASE'];
                break;
            case "COUNT":
                $measureSelect = "COUNT(" . $measure['VAL'] . ") AS " . $measure['ALIASE'];
                break;
            case 'FUNCTION':
                $className = $measure['CLASS_NAME'];
                $functionName = $measure['FUNCTION_NAME'];
                $measureSelect = $className::$functionName(self::$settingVars) . " AS " . $measure['ALIASE'];
                break;
            default:
                $measureSelect = $measure['VAL'] . " AS " . $measure['ALIASE'];
        }

        return $measureSelect;
    }*/

    /**
     * Use when you need to pull data for realtime filtering
     * Works with 'CustomizableChart_With_Realtime_Filtering' Class
     * */
    public static function LineChartDaysAllData($querypart, &$jsonOutput) {
        $tyValues = array();
        $lyValues = array();
        // $measureSelectionArr = self::getMeasuresForLineChart();

        $measuresArray = self::$settingVars->measureArray;
        $measureIDs = array_keys($measuresArray);

        $options = array();
        $measureSelectionArr = config\MeasureConfigure::prepareSelect(self::$settingVars, self::$queryVars, $measureIDs, $options);

        $query = " SELECT ".self::$settingVars->period." AS PERIOD, ";
        //ADD SUM/COUNT PART TO THE QUERY
        $query .= implode(",", $measureSelectionArr) . " ";
        
        $filterArray = array();
        if (!empty(filters\timeFilter::$tyWeekRange))
            $filterArray[] = filters\timeFilter::$tyWeekRange;

        if (!empty(filters\timeFilter::$lyWeekRange))
            $filterArray[] = filters\timeFilter::$lyWeekRange;

        $daysWhere = (!empty($filterArray)) ? " AND (" . implode(" OR ", $filterArray) . ") " : "";
        $query .= "FROM " . self::$settingVars->tablename . $querypart . $daysWhere .
                "GROUP BY PERIOD " .
                "ORDER BY PERIOD ASC";
        //echo $query;exit;
        $result = self::$queryVars->queryHandler->runQuery($query, self::$queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);

        if (is_array($result) && !empty($result)) {
            foreach ($result as $key => $data) {
                $account = $data['PERIOD'];
                if (in_array($account, filters\timeFilter::$tyDaysRange)) { //$numberFrom AND $numberTo COMES HANDY HERE
                    foreach (self::$settingVars->measureArray as $key => $measure) {
                        $tyValues[$account][$measure['ALIASE']] = $data[$measure['ALIASE']];
                    }
                } else {
                    foreach (self::$settingVars->measureArray as $key => $measure) {
                        $lyValues[$account][$measure['ALIASE']] = $data[$measure['ALIASE']];
                    }
                }
            }
        }

        /**
         * WHEN TIME SELECTION IS '2014-1' TO '2014-10' , IT'S POSSIBLE THAT SOME OF THE WEEKS HAVE NO SALES
         * IN THIS CASE LENGTH OF $tyValues AND $lyValues DOESN'T MATCH
         * THE FOLLOWING IF-ELSE BLOCK MAKES A DECISIOIN AND SET SOME MONITOR VARIABLES SO THAT WE CAN KEEP A TRACK
         * */
        if (count($tyValues) >= count($lyValues)) {
            $validTimeSlot = "TY";
            $validArray = $tyValues;
        } else {
            $validTimeSlot = "LY";
            $validArray = $lyValues;
        }

        //$validArray MEANS THE LONGEST OF $tyValues AND $lyValues
        //WE TRAVARSE THROUGH $validArray AND USE $key WHICH IS ACTUALLY A YEAR-WEEK COMBINATION e.g: 2014-05
        $value = array();
        foreach ($validArray as $key => $data) {
            if ($validTimeSlot == "TY") { //MEANS $tyValues IS THE LONGEST , SO WE HAVE TO MAKE $lyKey.
                $tyKey = $key;
                $tyIndex = array_search($key, filters\timeFilter::$tyDaysRange);
                $lyKey = (isset(filters\timeFilter::$lyDaysRange[$tyIndex])) ? filters\timeFilter::$lyDaysRange[$tyIndex] : "";
            } else { //MEANS $tyValues IS THE LONGEST , SO WE HAVE TO MAKE $lyKey.
                $lyKey = $key;
                $lyIndex = array_search($key, filters\timeFilter::$lyDaysRange);
                $tyKey = (isset(filters\timeFilter::$tyDaysRange[$lyIndex])) ? filters\timeFilter::$tyDaysRange[$lyIndex] : "";
            }

            $tempMeasureArray = array();
            foreach (self::$settingVars->measureArray as $key => $measure) {
                if (!empty($tyValues))
                    $tempMeasureArray["TY" . $measure['ALIASE']] = (key_exists($tyKey, $tyValues) ? $tyValues[$tyKey][$measure['ALIASE']] : 0);

                if (!empty($lyValues))
                    $tempMeasureArray["LY" . $measure['ALIASE']] = (key_exists($lyKey, $lyValues) ? $lyValues[$lyKey][$measure['ALIASE']] : 0);
            }
            $tempMeasureArray["TYACCOUNT"] = $tyKey;
            $tempMeasureArray["LYACCOUNT"] = $lyKey;
            $tempMeasureArray["ACCOUNT"] = ($tyKey) ? htmlspecialchars($tyKey) : htmlspecialchars($lyKey);
            $jsonOutput['LineChart'][] = $tempMeasureArray;
        }
    }

    /**
     * Use when you need to pull data without realtime filtering
     * */
    /*public static function LineChart($querypart, &$jsonOutput) {
        $tyValues = array();
        $lyValues = array();
        $measureSelectionArr = self::getMeasuresForLineChart();
        self::$ValueVolume = getValueVolume(self::$settingVars);

        $query = " SELECT " . self::$settingVars->yearperiod . " AS YEAR" .
                "," . self::$settingVars->weekperiod . " AS WEEK" .
                ",SUM(IFNULL(" . self::$ValueVolume . ",0)) AS " . self::$settingVars->measureArray['M' . ($_GET['ValueVolume'] - 1)]['ALIASE'] . " " .
                "FROM " . self::$settingVars->tablename . $querypart . " AND (" . filters\timeFilter::$tyWeekRange . " OR " . filters\timeFilter::$lyWeekRange . ") " .
                "GROUP BY YEAR,WEEK " .
                "ORDER BY YEAR ASC,WEEK ASC";
        $result = self::$queryVars->queryHandler->runQuery($query, self::$queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
        foreach ($result as $key => $data) {
            $account = $data['YEAR'] . "-" . $data['WEEK'];
            $numberAccount = $data['YEAR'] . ($data['WEEK'] < 10 ? "0" . $data['WEEK'] : $data['WEEK']);
            $numberFrom = filters\timeFilter::$FromYear . (filters\timeFilter::$FromWeek < 10 ? "0" . filters\timeFilter::$FromWeek : filters\timeFilter::$FromWeek);
            $numberTo = filters\timeFilter::$ToYear . (filters\timeFilter::$ToWeek < 10 ? "0" . filters\timeFilter::$ToWeek : filters\timeFilter::$ToWeek);

            if ($numberAccount >= $numberFrom && $numberAccount <= $numberTo) {
                $tyValues[$account]['VALUE'] = $data[self::$settingVars->measureArray['M' . ($_GET['ValueVolume'] - 1)]['ALIASE']];
            } else {
                $lyValues[$account]['VALUE'] = $data[self::$settingVars->measureArray['M' . ($_GET['ValueVolume'] - 1)]['ALIASE']];
            }
        }

        if (count($tyValues) >= count($lyValues)) {
            $validTimeSlot = "TY";
            $validArray = $tyValues;
        } else {
            $validTimeSlot = "LY";
            $validArray = $lyValues;
        }

        $value = array();
        foreach ($validArray as $key => $data) {
            $temp = array();
            $temp = explode("-", $key);
            $year = $temp[0];
            $week = $temp[1];

            if ($validTimeSlot == "TY") {
                $tyKey = $key;
                $lyKey = ($year - 1) . "-" . $week;
            } else {
                $tyKey = ($year + 1) . "-" . $week;
                $lyKey = $key;
            }

            $value = array(
                'TYACCOUNT' => $tyKey
                , 'LYACCOUNT' => $lyKey
                , 'ACCOUNT' => htmlspecialchars($tyKey)
                , "TY" . self::$settingVars->measureArray['M' . ($_GET['ValueVolume'] - 1)]['ALIASE'] => (key_exists($tyKey, $tyValues) ? $tyValues[$tyKey]['VALUE'] : 0)
                , "LY" . self::$settingVars->measureArray['M' . ($_GET['ValueVolume'] - 1)]['ALIASE'] => (key_exists($lyKey, $lyValues) ? $lyValues[$lyKey]['VALUE'] : 0)
            );

            $jsonOutput['LineChart'][] = $value;
        }
    }*/

    /**
     * Use when you need to pull data for realtime filtering using PREVIOUS PERIOD
     * Works with 'CustomizableChart_With_Realtime_Filtering' Class
     * */
    public static function LineChartAllData_for_PP($querypart, &$jsonOutput) {
        $tyAccounts = array();
        $lyAccounts = array();
        $tyValues = array();
        $lyValues = array();
        $accountsArray = array();
        // $measureSelectionArr = self::getMeasuresForLineChart();

        /*$measuresArray = self::$settingVars->measureArray;
        $measureIDs = array_keys($measuresArray);*/

        $measuresArray = $measureIDs = array();

        $measureKey = $_REQUEST['requestedChartMeasure'];

        if (!empty($measureKey) && $measureKey != undefined ) {
            $measureIDs[] = $measureKey;
            $measuresArray[] = self::$settingVars->measureArray[$measureKey];
            
            $jsonOutput['measureJsonKey'] = self::$settingVars->measureArray[$measureKey]['ALIASE'];
            
            if(is_array(self::$settingVars->pageArray["MEASURE_SELECTION_LIST"]) && !empty(self::$settingVars->pageArray["MEASURE_SELECTION_LIST"])){
                foreach (self::$settingVars->pageArray["MEASURE_SELECTION_LIST"] as $key => $measureVal) {
                    $measureKey = 'M' . $measureVal['measureID'];
                    if(!in_array($measureKey, $measureIDs)){
                        $measureIDs[] = $measureKey;
                        $measuresArray[] = self::$settingVars->measureArray[$measureKey];
                    }
                }
            }
            
        } else {
            $measuresArray = $settingVars->measureArray;
            $measureIDs = array_keys($measuresArray);
        }

        $options = array();
        $measureSelectionArr = config\MeasureConfigure::prepareSelect(self::$settingVars, self::$queryVars, $measureIDs, $options);


        //COLLECT TY ACCOUNTS
        //WHEN $FromYear MATCHES $ToYear , THEN WE JUST NEED TO INCREASE BY WEEK
        if (filters\timeFilter::$FromYear == filters\timeFilter::$ToYear) {
            $year = filters\timeFilter::$FromYear;
            for ($week = filters\timeFilter::$FromWeek; $week <= filters\timeFilter::$ToWeek; $week++) {
                $account = $year . "-" . $week;
                array_push($tyAccounts, $account);
            }
        } else {
            //IN CASE WHEN $FromYear DOESN'T MATCH $ToYear , WE LOGICALLY ASSUME WE NEED TO TAKE ALL WEEKS FOLLOWING $FromWeek
            $year = filters\timeFilter::$FromYear;
            for ($week = filters\timeFilter::$FromWeek; $week <= (in_array(self::$settingVars->timeSelectionUnit, array('week', 'weekYear')) ? 52 : 12); $week++) {
                $account = $year . "-" . $week;
                array_push($tyAccounts, $account);
            }

            //AND TAKE WEEKS OF $ToYear WITHIN $ToWeek
            $year = filters\timeFilter::$ToYear;
            for ($week = 1; $week <= filters\timeFilter::$ToWeek; $week++) {
                $account = $year . "-" . $week;
                array_push($tyAccounts, $account);
            }
        }

        //COLLECT LY ACCOUNTS USING SAME LOGIC AS WE USED TO COLLECT TY ACCOUNTS
        if (filters\timeFilter::$FromYear_PRV == filters\timeFilter::$ToYear_PRV) {
            $year = filters\timeFilter::$FromYear_PRV;
            for ($week = filters\timeFilter::$FromWeek_PRV; $week <= filters\timeFilter::$ToWeek_PRV; $week++) {
                $account = $year . "-" . $week;
                array_push($lyAccounts, $account);
            }
        } else {
            $year = filters\timeFilter::$FromYear_PRV;
            for ($week = filters\timeFilter::$FromWeek_PRV; $week <= 52; $week++) {
                $account = $year . "-" . $week;
                array_push($lyAccounts, $account);
            }

            $year = filters\timeFilter::$ToYear_PRV;
            for ($week = 1; $week <= filters\timeFilter::$ToWeek_PRV; $week++) {
                $account = $year . "-" . $week;
                array_push($lyAccounts, $account);
            }
        }

        //USE OF $tyAccounts AND $lyAccounts ENDS HERE
        //FROM NOW ON WE'LL USE $accountsArray , WHERE WE PUT ALL TY ACCOUNTS AND CORRESPONDING LY ACCOUNTS ON THE SAME INDEX
        foreach ($tyAccounts as $key => $data) {
            $accountsArray[$key]['TY'] = $tyAccounts[$key];
            $accountsArray[$key]['LY'] = $lyAccounts[$key];
        }

        /* HELPFUL WHEN DEBUGGING, DON'T DELETE */
        //header("Content-Type:application/json");
        //print_r($accountsArray);exit;



        /**
         * ON THE FOLLOWING TWO BLOCKS WE RUN QUERIES TO FETCH TY AND LY DATA
         * AND THE QUERY RESULTS WILL HAVE THE YEAR-WEEK COMBINATION THAT WE'VE POPULATED $accountsArray WITH ALREADY
         * */
        //TY QUERY
        $query = " SELECT " . self::$settingVars->yearperiod . " AS YEAR," . self::$settingVars->weekperiod . " AS WEEK,";
        //ADD SUM/COUNT PART TO THE QUERY
        $query .= implode(",", $measureSelectionArr) . " ";
        $query .= "FROM " . self::$settingVars->tablename . $querypart . " AND " . filters\timeFilter::$tyWeekRange . " " .
                "GROUP BY YEAR,WEEK " .
                "ORDER BY YEAR ASC,WEEK ASC";

        $result = self::$queryVars->queryHandler->runQuery($query, self::$queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);


        foreach ($result as $key => $data) {
            $account = $data['YEAR'] . "-" . $data['WEEK'];
            // foreach (self::$settingVars->measureArray as $key => $measure) {
            foreach ($measuresArray as $key => $measure) {
                //if ($measure['attr'] == "SUM") {
                    $tyValues[$account][$measure['ALIASE']] = $data[$measure['ALIASE']];
                    //$tyValues[$account][$measure['ALIASE']] = $data[$measure['ALIASE']];
                //}
            }

            /*
              $tyValues[$account]['VALUE'] 	= $data['VALUE'];
              $tyValues[$account]['VOLUME']	= $data['VOLUME'];
             */
        }


        //LY QUERY
        $query = " SELECT " . self::$settingVars->yearperiod . " AS YEAR," . self::$settingVars->weekperiod . " AS WEEK,";
        //ADD SUM/COUNT PART TO THE QUERY
        $query .= implode(",", $measureSelectionArr) . " ";
        $query .= "FROM " . self::$settingVars->tablename . $querypart . " AND " . filters\timeFilter::$lyWeekRange . " " .
                "GROUP BY YEAR,WEEK " .
                "ORDER BY YEAR ASC,WEEK ASC";
        $result = self::$queryVars->queryHandler->runQuery($query, self::$queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
        foreach ($result as $key => $data) {
            $account = $data['YEAR'] . "-" . $data['WEEK'];
            // foreach (self::$settingVars->measureArray as $key => $measure) {
            foreach ($measuresArray as $key => $measure) {
                //if ($measure['attr'] == "SUM") {
                   // $lyValues[$account][$measure['ALIASE']] = $data[$measure['ALIASE']];
                    $lyValues[$account][$measure['ALIASE']] = $data[$measure['ALIASE']];
               // }
            }

            /*
              $lyValues[$account]['VALUE']    = $data['VALUE'];
              $lyValues[$account]['VOLUME']   = $data['VOLUME'];
             */
        }
        $tempMeasureResultArray = array();
        foreach ($accountsArray as $key => $data) {
            $tempMeasureArray = array();
            // foreach (self::$settingVars->measureArray as $key => $measure) {
            foreach ($measuresArray as $key => $measure) {
                //if ($measure['attr'] == "SUM") {
                    $tempMeasureArray["TY" . $measure['ALIASE']] = $tyValues[$data['TY']][$measure['ALIASE']];
                    $tempMeasureArray["LY" . $measure['ALIASE']] = $lyValues[$data['LY']][$measure['ALIASE']];
               // }
            }
            $tempMeasureArray["TYACCOUNT"] = $data['TY'];
            $tempMeasureArray["LYACCOUNT"] = $data['LY'];
            $tempMeasureArray["ACCOUNT"] = htmlspecialchars($data['TY']);
            $tempMeasureResultArray[] = $tempMeasureArray;
            
        }
        $jsonOutput['LineChart'] = $tempMeasureResultArray;
    }


    /*     * *
     * ONLY USED IN OVER/UNDER PAGES TO PREPARE GRID DATA
     * * */

    public static function gridFunction_for_overUnder($querypart, $id, $name, $storeField, $skuField, $jsonTag, &$jsonOutput) {

        $options = array();
        if (!empty(filters\timeFilter::$tyWeekRange))
            $options['tyLyRange']['SALES'] = filters\timeFilter::$tyWeekRange;

        $measureSelect = config\MeasureConfigure::prepareSelect(self::$settingVars, self::$queryVars, array('M'.$_REQUEST['ValueVolume']), $options);
        $measureSelect = implode(", ", $measureSelect);


		// self::$ValueVolume = getValueVolume(self::$settingVars);
        $query = "SELECT " . $measureSelect . " " .
                // "SUM( (CASE WHEN " . filters\timeFilter::$tyWeekRange . " THEN 1 ELSE 0 END) * " . self::$ValueVolume . " ) AS SALES " .
                ",COUNT( DISTINCT (CASE WHEN (" . filters\timeFilter::$tyWeekRange . " AND " . self::$settingVars->ProjectVolume . ">0)  THEN " . $storeField . " END)) AS STORES " .
                ",COUNT( DISTINCT (CASE WHEN (" . filters\timeFilter::$tyWeekRange . " AND " . self::$settingVars->ProjectVolume . ">0)  THEN " . $skuField . " END)) AS SKUS " .
                "FROM " . self::$settingVars->tablename . $querypart;
		//echo $query; exit;
        $result = self::$queryVars->queryHandler->runQuery($query, self::$queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
        $data = $result[0];
        $totalSkus = $data['SKUS'];
        $totalStores = $data['STORES'];
        $totalSales = $data['SALES'];

        //CHECK IF THERE SHOULD BE ID IN THE QUERY, IF YES, ADD ID IN GROUP BY CLAUSE TOO
        if (empty($id) || $id == "") {
            $query = "SELECT $name AS ACCOUNT,";
            $groupBy = "GROUP BY ACCOUNT";
        } else {
            $query = "SELECT $id AS ID,$name AS ACCOUNT,";
            $groupBy = "GROUP BY ID,ACCOUNT";
        }

        $query .= $measureSelect . " ".
                ",COUNT( DISTINCT (CASE WHEN (" . filters\timeFilter::$tyWeekRange . " AND " . self::$settingVars->ProjectVolume . ">0)  THEN " . $storeField . " END)) AS STORES" .
                ",COUNT( DISTINCT (CASE WHEN (" . filters\timeFilter::$tyWeekRange . " AND " . self::$settingVars->ProjectVolume . ">0)  THEN " . $skuField . " END)) AS SKUS " .
                "FROM " . self::$settingVars->tablename . $querypart . " " .
                "$groupBy ORDER BY ACCOUNT ASC";
        // echo $query; exit;
        $result = self::$queryVars->queryHandler->runQuery($query, self::$queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
        $value = array();
        $tempResult = array();
        foreach ($result as $key => $data) {
            $avg = ($data['STORES'] > 0 && $data['SKUS'] > 0) ? ($data['SALES'] / $data['STORES']) / filters\timeFilter::$totalWeek / $data['SKUS'] : 0;
            if ($avg > 0) {
                $idVal = $id == "" ? $data['ACCOUNT'] : $data['ID'];
                $value = array(
                    'ID' => htmlspecialchars_decode($idVal)
                    , 'ACCOUNT' => htmlspecialchars_decode($data['ACCOUNT'])
                    , 'SALES' => (float) $data['SALES']
                    , 'STORES' => (float) $data['STORES']
                    , 'SKUS' => $data['SKUS']
                    , 'TOTALWEEK' => filters\timeFilter::$totalWeek
                    , 'COST_CUR' => (float) $avg
                );
                $tempResult[] = $value;
            }
        }
        $jsonOutput[$jsonTag] = $tempResult;

        if ($totalStores * filters\timeFilter::$totalWeek * $totalSkus > 0)
            $summaryAvg = $totalSales / $totalStores / filters\timeFilter::$totalWeek / $totalSkus;
        $value = array();
        $value = array(
            'avg' => (float) $summaryAvg, 
            'stores' => (float) $totalStores
        );
        $jsonOutput[$jsonTag . "_summary"] = $value;
    }

    /*     * *
     * USE WHEN YOU NEED A MAP DATA [USUALLY FOR SVG MAP] BASED ON VAR % ([TY-LY]/LY)%
     * * */

    public static function gridFlexTreeForVarPct($querypart, $name, $tagName, &$jsonOutput, $measureSelectRes = array()) {
        $totalTY = 0;
        $store = array();
        $max = 0;
        $min = 0;
        $com = "";

        $measureSelect = $measureSelectRes['measureSelectionArr'];
        $havingTYValue = $measureSelectRes['havingTYValue'];
        $havingLYValue = $measureSelectRes['havingLYValue'];
    
            /*$options = array();
            if (!empty(filters\timeFilter::$tyWeekRange))
                $options['tyLyRange']['TYEAR'] = filters\timeFilter::$tyWeekRange;

            if (!empty(filters\timeFilter::$lyWeekRange))
                $options['tyLyRange']['LYEAR'] = filters\timeFilter::$lyWeekRange;

            $measureSelect = config\MeasureConfigure::prepareSelect(self::$settingVars, self::$queryVars, array('M'.$_REQUEST['ValueVolume']), $options);
            $measureSelect = implode(", ", $measureSelect);*/

            /*$query = "SELECT $name AS ACCOUNT, " .
                    $measureSelect . " ".
                    "FROM " . self::$settingVars->tablename . $querypart . " AND (" . filters\timeFilter::$tyWeekRange . " OR " . filters\timeFilter::$lyWeekRange . ") " .
                    "GROUP BY ACCOUNT " .
                    "ORDER BY TYEAR DESC ";*/
        
        $query = "SELECT $name AS ACCOUNT, " .implode(",", $measureSelect).
            " FROM " . self::$settingVars->tablename .' '. trim($querypart) .
            " AND (" . filters\timeFilter::$tyWeekRange . " OR " . filters\timeFilter::$lyWeekRange . ") " .
            "GROUP BY ACCOUNT";

        $redisCache = new utils\RedisCache(self::$queryVars);
        $redisOutput = $redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = self::$queryVars->queryHandler->runQuery($query, self::$queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }

        $requiredGridFields = array("ACCOUNT", $havingTYValue, $havingLYValue);
        $result = $redisCache->getRequiredData($result, $requiredGridFields, $havingTYValue, $havingTYValue, $havingLYValue);

        $value = array();
        $tempResult = array();
        foreach ($result as $key => $data) {
            $var = $data[$havingTYValue] - $data[$havingLYValue];
            $varPct = $data[$havingLYValue] != 0 ? ($var / $data[$havingLYValue]) * 100 : 0;
            $color = self::getColor($varPct);

            $value = array(
                "@attributes" => array(
                    'name' => htmlspecialchars_decode(strtoupper($data['ACCOUNT']))
                    , 'data' => $varPct
                    , 'color' => $color
                )
            );
            $tempResult[] = $value;
        }
        $jsonOutput[$tagName] = $tempResult;
    }

    /*     * *
     * USE WHEN YOU NEED A MAP DATA [USUALLY FOR SVG MAP] BASED ON VARIANCE [TY-LY]
     * * */

    public static function gridFlexTreeForVariance($querypart, $name, $tagName, &$jsonOutput, $measureSelectRes = array()) {
        $negcolor = array('#EE0202', '#D20202', '#B50202', '#A00202', '#8C0101', '#760101', '#640101', '#510101', '#400101', '#2E0101');
        $color = array('#002D00', '#014301', '#015901', '#016B01', '#018001', '#019701', '#01AC01', '#02C502', '#02DB02', '#02FB02');

        $totalTY = 0;
        $store = array();
        $max = 0;
        $min = 0;
        $com = "";

        $measureSelect = $measureSelectRes['measureSelectionArr'];
        $havingTYValue = $measureSelectRes['havingTYValue'];
        $havingLYValue = $measureSelectRes['havingLYValue'];

        /*$options = array();
        if (!empty(filters\timeFilter::$tyWeekRange))
            $options['tyLyRange']['TYEAR'] = filters\timeFilter::$tyWeekRange;

        if (!empty(filters\timeFilter::$lyWeekRange))
            $options['tyLyRange']['LYEAR'] = filters\timeFilter::$lyWeekRange;

        $measureSelect = config\MeasureConfigure::prepareSelect(self::$settingVars, self::$queryVars, array('M'.$_REQUEST['ValueVolume']), $options);
        $measureSelect = implode(", ", $measureSelect);*/

        /*$query = " SELECT $name AS ACCOUNT, " .
                $measureSelect . " ".
                // ",SUM((CASE WHEN " . filters\timeFilter::$tyWeekRange . " THEN 1 ELSE 0 END) * " . $ValueVolume . ") AS TYEAR" .
                // ",SUM((CASE WHEN " . filters\timeFilter::$lyWeekRange . " THEN 1 ELSE 0 END) * " . $ValueVolume . ") AS LYEAR " .
                "FROM " . self::$settingVars->tablename . $querypart . " AND (" . filters\timeFilter::$tyWeekRange . " OR " . filters\timeFilter::$lyWeekRange . ") " .
                "GROUP BY ACCOUNT " .
                "ORDER BY TYEAR DESC ";*/

        $query = "SELECT $name AS ACCOUNT, " .implode(",", $measureSelect).
            " FROM " . self::$settingVars->tablename .' '. trim($querypart) .
            " AND (" . filters\timeFilter::$tyWeekRange . " OR " . filters\timeFilter::$lyWeekRange . ") " .
            "GROUP BY ACCOUNT";

        $redisCache = new utils\RedisCache(self::$queryVars);
        $redisOutput = $redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = self::$queryVars->queryHandler->runQuery($query, self::$queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }

        $requiredGridFields = array("ACCOUNT", $havingTYValue, $havingLYValue);
        $result = $redisCache->getRequiredData($result, $requiredGridFields, $havingTYValue, $havingTYValue, $havingLYValue);

        foreach ($result as $key => $data) {
            $data['ACCOUNT'] = str_replace('\'', ' ', $data['ACCOUNT']);
            if ($data[$havingLYValue] > 0) {
                $var = $data[$havingTYValue] - $data[$havingLYValue];
                $varPct = ($var / $data[$havingLYValue]) * 100;

                if ($var > $max)
                    $max = $var;
                if ($var < $min)
                    $min = $var;
                array_push($store, $data['ACCOUNT'] . "," . $var . "," . $varPct);
            }else {
                $var = $data[1] - $data[2];
                $varPct = 0;
                array_push($store, $data[0] . "," . $var . "," . $varPct);
            }
        }

        $tempResult = array();
        for ($i = 0; $i < count($store); $i++) {
            $d = explode(',', $store[$i]);

            if ($d[1] >= 0) {
                $c = 0;
                $range = abs(($max - $min) / 10);
                $value = array();
                for ($j = 0; $j <= $max; $j+=$range) {
                    if ((number_format($d[1], 5, '.', '') >= number_format($j, 5, '.', '')) && (number_format($d[1], 5, '.', '') < number_format(($j + $range), 5, '.', ''))) {
                        if ($c >= count($color) - 1) {
                            $value = array(
                                "@attributes" => array(
                                    'name' => htmlspecialchars_decode(strtoupper($d[0]))
                                    , 'data' => $d[1]
                                    , 'color' => $color[9]
                                )
                            );
                            //$jsonOutput[$tagName][] = $value;
                            $tempResult[] = $value;
                            break;
                        } else {

                            $value = array(
                                "@attributes" => array(
                                    'name' => htmlspecialchars_decode(strtoupper($d[0]))
                                    , 'data' => $d[1]
                                    , 'color' => $color[$c]
                                )
                            );
                            //$jsonOutput[$tagName][] = $value;
                            $tempResult[] = $value;
                            break;
                        }
                    }
                    $c++;
                }
            } else {
                $c = 0;
                $range = abs($min / 10);
                $value = array();
                for ($j = $min; $j <= 0; $j+=$range) {

                    if ((number_format($d[1], 5, '.', '') >= number_format($j, 5, '.', '')) && (number_format($d[1], 5, '.', '') < number_format(($j + $range), 5, '.', ''))) {
                        if ($c >= count($negcolor) - 1) {
                            $value = array(
                                "@attributes" => array(
                                    'name' => htmlspecialchars_decode(strtoupper($d[0]))
                                    , 'data' => $d[1]
                                    , 'color' => $negcolor[9]
                                )
                            );
                            //$jsonOutput[$tagName][] = $value;
                            $tempResult[] = $value;
                            break;
                        } else {
                            $value = array(
                                "@attributes" => array(
                                    'name' => htmlspecialchars_decode(strtoupper($d[0]))
                                    , 'data' => $d[1]
                                    , 'color' => $negcolor[$c]
                                )
                            );
                            //$jsonOutput[$tagName][] = $value;
                            $tempResult[] = $value;
                            break;
                        }
                    }
                    $c++;
                }
            }
        }
        $jsonOutput[$tagName] = $tempResult;
    }

    /*     * *
     * DETERMINES AND RETURNS THE LEAF COLOR OF TREE MAP DEPENDING ON $varPct VALUES PASSED BY CALLING FUNCTION
     * * */

    public static function getColor($varPct) {
        $negcolor = array('#EE0202', '#D20202', '#B50202', '#A00202', '#8C0101', '#760101', '#640101', '#510101', '#400101', '#2E0101');
        $color = array('#002D00', '#014301', '#015901', '#016B01', '#018001', '#019701', '#01AC01', '#02C502', '#02DB02', '#02FB02');

        if ($varPct >= 100)
            return $color[9];
        elseif ($varPct <= -100)
            return $negcolor[9];
        else {
            $dividentByTen = number_format($varPct / 10, 0, "", "");

            if (abs($dividentByTen) == 0) {
                return $varPct > 0 ? $color[$dividentByTen] : $negcolor[abs($dividentByTen)];
            }

            return $dividentByTen > 0 ? $color[$dividentByTen - 1] : $negcolor[abs($dividentByTen + 1)];
        }
    }

    /**
     * FUNCTION TO PREPARE DATA ON SHIP-PERFORMANCE PAGE'S GRID
     * */
    public static function gridFunction_For_ShipAnalysis($querypart, $selectPart, $groupByPart, $jsonTag, &$jsonOutput) {
        $jsonNodes = array();
        $ValueVolumes = getValueVolumeForShipAnalysis(self::$settingVars);

        $query = "SELECT " . implode(",", $selectPart) .
                ",SUM( (CASE WHEN " . filters\timeFilter::$tyWeekRange . " THEN 1 ELSE 0 END) * " . $ValueVolumes['SALE_FIELD'] . ") AS SALES" .
                ",SUM( (CASE WHEN " . filters\timeFilter::$lyWeekRange . " THEN 1 ELSE 0 END) * " . $ValueVolumes['SHIP_FIELD'] . ") AS SHIPS " .
                "FROM " . self::$settingVars->tablename . $querypart . " AND (" . filters\timeFilter::$tyWeekRange . " OR " . filters\timeFilter::$lyWeekRange . ") " .
                "GROUP BY " . implode(",", $groupByPart) . " " .
                "ORDER BY SALES DESC";
        //print $query;exit;
        $result = self::$queryVars->queryHandler->runQuery($query, self::$queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
        $value = array();
        foreach ($result as $key => $data) {

            //COLLECT ALL QUERY ALIASES TO ADD AS XML-TAG 
            if (empty($jsonNodes) || count($jsonNodes) != count($data))
                $jsonNodes = array_keys($data);

            //WE ADD ACCOUNT AND/OR ID DYNAMICALLY
            $value = array();
            foreach ($jsonNodes as $index => $node) {
                $value[$node] = htmlspecialchars_decode($data[$node]);
            }            

            //NOW ADDING DIFF AND SELL THROUGH
            $diff = $data['SALES'] - $data['SHIPS'];
            $sellThrough = $data['SHIPS'] != 0 ? (($data['SALES'] / $data['SHIPS']) * 100) : 0;
            $value["DIFF"] = number_format($diff, 0, '.', '');
            $value["SELL_TH"] = number_format($sellThrough, 1, '.', '');

            $jsonOutput[$jsonTag][] = $value;
        }
    }
    
    public static function lineChart_For_ShipAnalysis($querypart, &$jsonOutput) {
        $ValueVolumes = getValueVolumeForShipAnalysis(self::$settingVars);

        $query = "SELECT MAX(" . self::$settingVars->maintable.".".self::$settingVars->period . ") AS PERIOD" .
                ",SUM(( CASE WHEN " . filters\timeFilter::$tyWeekRange . " THEN 1 ELSE 0 END) * " . $ValueVolumes['SALE_FIELD'] . " ) AS SALES" .
                ",SUM(( CASE WHEN " . filters\timeFilter::$lyWeekRange . " THEN 1 ELSE 0 END) * " . $ValueVolumes['SHIP_FIELD'] . " ) AS SHIPS " .
                "FROM " . self::$settingVars->tablename . $querypart . " " .
                "GROUP BY " . self::$settingVars->weekperiod . " " .
                "HAVING (SALES<>0 AND SHIPS<>0) " .
                "ORDER BY PERIOD ASC";
        //echo $query; exit;
        
        $result = self::$queryVars->queryHandler->runQuery($query, self::$queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);

        foreach ($result as $key => $data) {
            $value = array();
            $value["ACCOUNT"] = $data['PERIOD'];
            $value["SHIPS"] = number_format($data['SHIPS'], 0, '.', '');
            $value["SALES"] = number_format($data['SALES'], 0, '.', '');
            //NOW ADDING DIFF AND SELL THROUGH
            $diff = $data['SALES'] - $data['SHIPS'];
            $sellThrough = $data['SHIPS'] != 0 ? (($data['SALES'] / $data['SHIPS']) * 100) : 0;
            $value["DIFF"] = number_format($diff, 0, '.', '');
            $value["SELL_TH"] = number_format($sellThrough, 1, '.', '');
            $jsonOutput["LineChart"][] = $value;
        }
    }

    public static function findLastSalesDays($setting, $pinField = "PIN"){

        $queryVars  = projectsettings\settingsGateway::getInstance();
        //$getLastDaysDate = filters\timeFilter::getLastN14DaysDate($setting);
        $query = "SELECT $pinField AS SKU".
                ",SNO AS SNO".
                ",MAX((CASE WHEN ".$setting->ProjectValue.">0 THEN ".$setting->dateperiod." END)) AS DATE ".
                ",MAX(".$setting->dateperiod.") AS MYDATE ".
                "FROM ". $setting->maintable." ". 
                "GROUP BY SKU,SNO";
        
        $result     = $queryVars->queryHandler->runQuery($query, $queryVars->linkid,db\ResultTypes::$TYPE_OBJECT);

        $salesArray = array();
        $temp = array();
        foreach ($result as $value) {

        if(!empty($value ['DATE']))
        {
            $datetime1 = new \DateTime($value ['MYDATE']);
            $datetime2 = new \DateTime($value ['DATE']);

            $interval = $datetime1->diff($datetime2);   

            $salesArray[$value['SKU']."_".$value['SNO']] = $interval->d;
        }
        else
            $salesArray[$value['SKU']."_".$value['SNO']] = 14;   

        }

        return $salesArray;
    }

    public static function getLastSalesDays( $skuID, $sno, $setting, $querypart, $getMyDtOnly = 'N'){
        $queryVars  = projectsettings\settingsGateway::getInstance();
        //$getLastDaysDate = filters\timeFilter::getLastN14DaysDate($setting);
        $query = "SELECT $skuID AS SKU".
                ",$sno AS SNO".
                ",MAX((CASE WHEN ".$setting->ProjectValue.">0 THEN ".$setting->dateperiod." END)) AS DATE ".
                ",MAX(".$setting->dateperiod.") AS MYDATE ".
                "FROM ". $setting->tablename." ".$querypart ." ". 
                "GROUP BY SKU,SNO";

        $redisCache = new utils\RedisCache($queryVars);
        $redisOutput = $redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $queryVars->queryHandler->runQuery($query, $queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }

        $salesArray = array();
        $temp = array();
        foreach ($result as $value) {
            if($getMyDtOnly == 'Y'){
                $salesArray[$value['SKU']."_".$value['SNO']] = $value['MYDATE'];
            }else{
                if(!empty($value ['DATE']))
                {
                    $datetime1 = new \DateTime($value ['MYDATE']);
                    $datetime2 = new \DateTime($value ['DATE']);
                    $interval = $datetime1->diff($datetime2);   
                    $salesArray[$value['SKU']."_".$value['SNO']] = $interval->d;
                }
                else
                    $salesArray[$value['SKU']."_".$value['SNO']] = 14;
            }
        }
        return $salesArray;
    }
}
?>