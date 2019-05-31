<?php
namespace projectstructure;

use config;
use filters;
use utils;
use db;
use datahelper;

class MEASURE_WITH_TYLY_AS_DIFF_COLUMN_PAGES
{
	public function itemRankingReportTopBoxesLogic($settingVars, $queryVars, $storeIDField, $marketIds = array(), $performanceBoxSettings = array()) 
	{
		return "";
	}

	public function itemRankingReportMarketGridLogic($settingVars, $queryVars, $storeIDField, $marketIds = array()) 
	{
		return "";
	}

	public function rangeEfficiencyCustomSelectPart($settingVars, $countAccount = "")
    {
       return "";
    }
    
    public function extraWhereClause($settingVars, &$measureFields)
    {
        return "";
    }

    public function extraWhereClauseForTopBoxes($settingVars, &$measureFields)
    {
        return "";
    }

    public function excelPosTrackerTyLyLogic($settingVars, $queryVars, $timeval, $measureVal, $selcetdClorCode)
    {
    	$measureArr = $options = $arrAliase = $arrAliaseMap = $arrAliaseColorCd = array();

		$key = array_search($measureVal, array_column($settingVars->pageArray["MEASURE_SELECTION_LIST"], 'measureID'));
		$measureName = (isset($settingVars->pageArray["MEASURE_SELECTION_LIST"][$key])) ? $settingVars->pageArray["MEASURE_SELECTION_LIST"][$key]['measureName'] : "";
		$measureKey = 'M' . $measureVal;
		$measure = $settingVars->measureArray[$measureKey];
		$arrAliase[$measure['ALIASE']] = $measure['ALIASE'];
		$arrAliaseMap[$measure['ALIASE']] = (!empty($measureName)) ? $measureName : $measure['ALIASE'];
		
		if(isset($selcetdClorCode[$measureVal]) && !empty($measure['ALIASE'])) {
		    $arrAliaseColorCd[$measure['ALIASE']] = $selcetdClorCode[$measureVal];
		}

		if (!empty(filters\timeFilter::$tyWeekRange)){
            filters\timeFilter::$lyWeekRange = filters\timeFilter::$tyWeekRange;

		    if($timeval == 'YTD' && $mkey == 0)
		        $orderBy = $timeval.'_TY_'.$measure['ALIASE'];
		    $options['tyLyRange'][$timeval.'_TY_'.$measure['ALIASE']] = filters\timeFilter::$tyWeekRange;
		    $options['tyLyFieldData'][$timeval.'_TY_'.$measure['ALIASE']] = '_ty';

            if (!empty(filters\timeFilter::$lyWeekRange)){
                $options['tyLyRange'][$timeval.'_LY_'.$measure['ALIASE']] = filters\timeFilter::$lyWeekRange;
                $options['tyLyFieldData'][$timeval.'_LY_'.$measure['ALIASE']] = '_ly';
            }
		}

		$measureArr[$measureKey] = config\MeasureConfigure::prepareSelect($settingVars, $queryVars, array($measureKey), $options);
		return [$measureArr, $arrAliase, $arrAliaseMap, $arrAliaseColorCd, $orderBy, $measureKey];
    }

    public function LineChartAllDataLogic($settingVars, $queryVars, &$jsonOutput, $querypart)
    {
    	$tyMydate = $tyValues = array();
        $lyMydate = $lyValues = array();
        $measuresArray = $measureIDs = array();

        $measureKey = $_REQUEST['requestedChartMeasure'];

        if (!empty($measureKey) && $measureKey != undefined ) {
            $measureIDs[] = $measureKey;
            $measuresArray[] = $settingVars->measureArray[$measureKey];
            
            $jsonOutput['measureJsonKey'] = $settingVars->measureArray[$measureKey]['ALIASE'];
            
            if(is_array($settingVars->pageArray["MEASURE_SELECTION_LIST"]) && !empty($settingVars->pageArray["MEASURE_SELECTION_LIST"])){
                foreach ($settingVars->pageArray["MEASURE_SELECTION_LIST"] as $key => $measureVal) {
                    $measureKey = 'M' . $measureVal['measureID'];
                    if(!in_array($measureKey, $measureIDs)){
                        $measureIDs[] = $measureKey;
                        $measuresArray[] = $settingVars->measureArray[$measureKey];
                    }
                }
            }
            
        } else {
            $measuresArray = $settingVars->measureArray;
            $measureIDs = array_keys($measuresArray);
        }

        $options = array('tyLyFieldPart' => '_ty');
        $measureSelectionArr = config\MeasureConfigure::prepareSelect($settingVars, $queryVars, $measureIDs, $options);

        $options = array('tyLyFieldPart' => '_ly');
        $measureSelectionArrLy = config\MeasureConfigure::prepareSelect($settingVars, $queryVars, $measureIDs, $options);
        $measureSelectionArr = array_merge($measureSelectionArr, $measureSelectionArrLy);
        
        $xaxisArray = $settingVars->tabsXaxis;

        /* MAKING A NUMERICAL FORMATION OF TIME SELECTION RANGES , MAKES IT EASY TO COMPARE '<' AND '>'
         * IF FromWeek AND ToWeek ARE '2014-1' AND '2014-10' RESPECTEDLY, THEN NUMERICAL FORMAT FOR THEM WILL BE -
         * $numberFrom=>'201401' AND $numberTo=>'201410' */
         
        $numberFrom = filters\timeFilter::$FromYear . (filters\timeFilter::$FromWeek < 10 ? "0" . filters\timeFilter::$FromWeek : filters\timeFilter::$FromWeek);
        $numberTo = filters\timeFilter::$ToYear . (filters\timeFilter::$ToWeek < 10 ? "0" . filters\timeFilter::$ToWeek : filters\timeFilter::$ToWeek);

        $query = " SELECT " . $settingVars->yearperiod . " AS YEAR," . $settingVars->weekperiod . " AS WEEK,";
        $mydateSelect = $settingVars->getMydateSelect($settingVars->dateField);
        $query .= ($settingVars->dateField !='') ? " ".$mydateSelect . " AS MYDATE, " : "";
        
        if($settingVars->timeSelectionUnit == "period")
            $query .= (!empty($settingVars->periodField)) ? " MAX(".$settingVars->periodField . ") AS PERIOD, " : "";
        
        //ADD SUM/COUNT PART TO THE QUERY
        $query .= implode(",", $measureSelectionArr) . " ";
        $query .= "FROM " . $settingVars->tablename . $querypart . " AND (" . filters\timeFilter::$tyWeekRange . " OR " . filters\timeFilter::$lyWeekRange . ") " .
                "GROUP BY YEAR,WEEK " .
                "ORDER BY YEAR ASC,WEEK ASC";
        // echo $query; exit;
        
        $redisCache = new utils\RedisCache($queryVars);
        $redisOutput = $redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $queryVars->queryHandler->runQuery($query, $queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }
        
        $timeQuery = " SELECT " . $settingVars->yearperiod . " AS YEAR," . $settingVars->weekperiod . " AS WEEK ";
        $timeQuery .= ($settingVars->dateField !='') ? " ,".$mydateSelect . " AS MYDATE " : "";
        
        if($settingVars->timeSelectionUnit == "period")
            $timeQuery .= (!empty($settingVars->periodField)) ? " ,MAX(".$settingVars->periodField . ") AS PERIOD " : "";

        $timeQuery .= "FROM " . $settingVars->timeHelperTables . $settingVars->timeHelperLink .
                " AND (" . filters\timeFilter::$tyWeekRange . " OR " . filters\timeFilter::$lyWeekRange . ") " .
                "GROUP BY YEAR,WEEK " .
                "ORDER BY YEAR ASC,WEEK ASC";
        $queryHash = md5($timeQuery);
        $redisOutput = $redisCache->checkAndReadByStaticHashFromCache('time_filter_data', $queryHash);

        if ($redisOutput === false) {
            $dateList = $queryVars->queryHandler->runQuery($timeQuery, $queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $redisCache->setDataForSubKeyHash($dateList, $queryHash);
        } else {
            $dateList = $redisOutput;
        }


        $dateListTyLy = array();
        if (is_array($dateList) && !empty($dateList)) {
            foreach ($dateList as $key => $data) {
                if($settingVars->timeSelectionUnit == "period")
                    $account = $data['YEAR'] . "-" . $data['PERIOD']; //SAMPLE '2014-5'
                else    
                    $account = $data['YEAR'] . "-" . $data['WEEK']; //SAMPLE '2014-5'

                //JUST LIKE $numberFrom WE MAKE A NUMERICAL FORMATION OF $account TO COMPARE WITH $numberFrom AND $numberTo
                $numberAccount = $data['YEAR'] . ($data['WEEK'] < 10 ? "0" . $data['WEEK'] : $data['WEEK']); //SAMPLE '201405'
                //BASED ON $numberAccount WE DECIDE WHETHER TO PUT THE QUERY RESULT IN $tyValues OR IN $lyValues

                if (isset($data['MYDATE']))
                    $data['MYDATE'] = date('j M y', strtotime($data['MYDATE']));

                if ($numberAccount >= $numberFrom && $numberAccount <= $numberTo) { //$numberFrom AND $numberTo COMES HANDY HERE
                    $dateListTyLy['TY'][$account] = $data;
                } else {
                    $dateListTyLy['LY'][$account] = $data;
                }
            }
        }

        foreach ($result as $key => $data) {
            if($settingVars->timeSelectionUnit == "period")
                $account = $data['YEAR'] . "-" . $data['PERIOD']; //SAMPLE '2014-5'
            else    
                $account = $data['YEAR'] . "-" . $data['WEEK']; //SAMPLE '2014-5'
            
            //JUST LIKE $numberFrom WE MAKE A NUMERICAL FORMATION OF $account TO COMPARE WITH $numberFrom AND $numberTo
            $numberAccount = $data['YEAR'] . ($data['WEEK'] < 10 ? "0" . $data['WEEK'] : $data['WEEK']); //SAMPLE '201405'
            //BASED ON $numberAccount WE DECIDE WHETHER TO PUT THE QUERY RESULT IN $tyValues OR IN $lyValues
            if ($numberAccount >= $numberFrom && $numberAccount <= $numberTo) { //$numberFrom AND $numberTo COMES HANDY HERE
                foreach ($measuresArray as $key => $measure) {
                    $tyValues[$account][$measure['ALIASE']] = ($data[$measure['ALIASE']."_ty"]) ? $data[$measure['ALIASE']."_ty"] : 0;
                    $lyValues[$account][$measure['ALIASE']] = ($data[$measure['ALIASE']."_ly"]) ? $data[$measure['ALIASE']."_ly"] : 0;
                }
                // if (isset($data['MYDATE']))
                //     $tyMydate[$account] = date('j M y', strtotime($data['MYDATE']));
            }
        }
        
        /**
         * WHEN TIME SELECTION IS '2014-1' TO '2014-10' , IT'S POSSIBLE THAT SOME OF THE WEEKS HAVE NO SALES
         * IN THIS CASE LENGTH OF $tyValues AND $lyValues DOESN'T MATCH
         * THE FOLLOWING IF-ELSE BLOCK MAKES A DECISIOIN AND SET SOME MONITOR VARIABLES SO THAT WE CAN KEEP A TRACK
         * */
        if (count($dateListTyLy['TY']) >= count($dateListTyLy['LY'])) {
            $validTimeSlot = "TY";
            $validArray = $dateListTyLy['TY'];
        } else {
            $validTimeSlot = "LY";
            $validArray = $dateListTyLy['LY'];
        }

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

            if ($validTimeSlot == "TY") { //MEANS $tyValues IS THE LONGEST , SO WE HAVE TO MAKE $lyKey.
                $tyKey = $key;
                $lyKey = $key;
            } else { //MEANS $tyValues IS THE LONGEST , SO WE HAVE TO MAKE $lyKey.
                $tyKey = $key;
                $lyKey = $key;
            }
        
            $tempMeasureArray = array();
            foreach ($measuresArray as $key => $measure) {
                $tempMeasureArray["TY" . $measure['ALIASE']] = (key_exists($tyKey, $tyValues) ? $tyValues[$tyKey][$measure['ALIASE']] : 0);
                $tempMeasureArray["LY" . $measure['ALIASE']] = (key_exists($lyKey, $lyValues) ? $lyValues[$lyKey][$measure['ALIASE']] : 0);
            }
            
            if(!empty($xaxisArray))
            {
                if($xaxisArray[$_REQUEST['requestedChartMeasure']] == "date"){
                    if (isset($dateListTyLy['TY'][$tyKey]) && isset($dateListTyLy['TY'][$tyKey]['MYDATE']))
                        $tempMeasureArray["TYACCOUNT"] = $dateListTyLy['TY'][$tyKey]['MYDATE'];

                    if (isset($dateListTyLy['LY'][$lyKey]) && isset($dateListTyLy['LY'][$lyKey]['MYDATE']))
                        $tempMeasureArray["LYACCOUNT"] = $dateListTyLy['LY'][$lyKey]['MYDATE'];

                    $tempMeasureArray["ACCOUNT"] = htmlspecialchars($dateListTyLy['TY'][$tyKey]['MYDATE']);
                } else {
                    $tempMeasureArray["TYACCOUNT"] = $tyKey;
                    $tempMeasureArray["LYACCOUNT"] = $lyKey;
                    $tempMeasureArray["ACCOUNT"] = htmlspecialchars($tyKey);
                }
            } else {
                $tempMeasureArray["TYACCOUNT"] = $tyKey;
                $tempMeasureArray["LYACCOUNT"] = $lyKey;
                $tempMeasureArray["ACCOUNT"] = htmlspecialchars($tyKey);
            }

            if (isset($dateListTyLy['TY'][$tyKey]) && isset($dateListTyLy['TY'][$tyKey]['MYDATE']))
                $tempMeasureArray["TYMYDATE"] = $dateListTyLy['TY'][$tyKey]['MYDATE'];

            if (isset($dateListTyLy['LY'][$lyKey]) && isset($dateListTyLy['LY'][$lyKey]['MYDATE']))
                $tempMeasureArray["LYMYDATE"] = $dateListTyLy['LY'][$lyKey]['MYDATE'];

            $tempMeasureResultArray[] = $tempMeasureArray;
        }

        $jsonOutput['LineChart'] = $tempMeasureResultArray;
    }
}