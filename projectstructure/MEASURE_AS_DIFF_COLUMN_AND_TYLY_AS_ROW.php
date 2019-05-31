<?php
namespace projectstructure;

use config;
use filters;
use datahelper;

class MEASURE_AS_DIFF_COLUMN_AND_TYLY_AS_ROW implements BaseProjectStructure
{
    public function prepareMeasureSelectPart($settingVars, $queryVars)
    {
        $measureArr = $measureSelectionArr = $ddbMeasureHavingPart = array();
        
        foreach ($settingVars->pageArray["MEASURE_SELECTION_LIST"] as $key => $measureVal) {
            $options = array();
            $measureKey = 'M' . $measureVal['measureID'];
            $measure = $settingVars->measureArray[$measureKey];
            
            if (!empty(filters\timeFilter::$tyWeekRange)) {
                if(($measureVal['measureID'] == $_REQUEST['ValueVolume']))
                    $havingTYValue = "TY" . $measure['ALIASE'];
                $measureTYValue = "TY" . $measure['ALIASE'];
                $options['tyLyRange'][$measureTYValue] = trim(filters\timeFilter::$tyWeekRange);
            }else{
                if(($measureVal['measureID'] == $_REQUEST['ValueVolume']))
                    $havingTYValue = $measure['ALIASE'];
            }

            if (!empty(filters\timeFilter::$lyWeekRange)) {
                if(($measureVal['measureID'] == $_REQUEST['ValueVolume']))
                    $havingLYValue = "LY" . $measure['ALIASE'];
                $measureLYValue = "LY" . $measure['ALIASE'];
                $options['tyLyRange'][$measureLYValue] = trim(filters\timeFilter::$lyWeekRange);
            }else{
                if(($measureVal['measureID'] == $_REQUEST['ValueVolume']))
                    $havingLYValue = $measure['ALIASE'];
            }
            $measureArr[$measureKey] = config\MeasureConfigure::prepareSelect($settingVars, $queryVars, array($measureKey), $options);           
            $measureSelectionArr = array_merge($measureSelectionArr,$measureArr[$measureKey]);   

            // This array will use for ddb project only
            if($measure['attr'] == "SUM"){
                if (!empty(filters\timeFilter::$tyWeekRange)) {
                    $ddbMeasureHavingPart[] = "TY".$measure['ALIASE']." <>0 ";
                }
                
                if (!empty(filters\timeFilter::$lyWeekRange)) {
                    $ddbMeasureHavingPart[] = "LY".$measure['ALIASE']." <>0 ";
                }
            }
        }

        datahelper\Common_Data_Fetching_Functions::$settingVars = $settingVars;
        datahelper\Common_Data_Fetching_Functions::$queryVars = $queryVars;
        
        $measureFields = datahelper\Common_Data_Fetching_Functions::getMeasuresExtraTable();
        
        $result = array("measureSelectionArr" => $measureSelectionArr, "havingTYValue" => $havingTYValue, 
                        "havingLYValue" => $havingLYValue, "measureFields" => $measureFields, "ddbMeasureHavingPart" => $ddbMeasureHavingPart);
        return $result;
    }

	public function prepareMeasureSelect($settingVars, $queryVars, $measureIDs, $options) {

        $measureSelect = array();
        if (is_array($measureIDs) && !empty($measureIDs)) {
            foreach ($measureIDs as $measureID) {
                $measure = $settingVars->measureArray[$measureID];
                $dbColumnsArray = array();
                
                if (!is_array($measure) || empty($measure))
                    continue;

                $tyLyRange  = (isset($options['tyLyRange']) && !empty($options['tyLyRange'])) ? $options['tyLyRange'] : array();
                $extraCaseWhen = (isset($measure['CASE_WHEN']) && !empty($measure['CASE_WHEN'])) ? $measure['CASE_WHEN'] : '';

                if(!$settingVars->hasMeasureFilter) {
                    switch ($measure['attr'])
                    {
                        case 'SUM':
                            if (is_array($tyLyRange) && !empty($tyLyRange)) {
                                foreach ($tyLyRange as $aliasKey => $range) {
                                    $measureSelect[] = "SUM((CASE WHEN " . $range . $extraCaseWhen . " THEN 1 ELSE 0 END) * IFNULL(" . $measure['VAL'] . ",0) ) AS  ".$aliasKey;
                                }
                            } else {
                                if (!empty($extraCaseWhen))
                                    $measureSelect[] = "SUM((CASE WHEN 1=1 " . $extraCaseWhen . " THEN 1 ELSE 0 END) * IFNULL(" . $measure['VAL'] . ",0) ) AS  ".$measure['ALIASE'];
                                else 
                                    $measureSelect[] = "SUM(IFNULL(" . $measure['VAL'] . ",0)) AS " . $measure['ALIASE'];
                            }
                            break;
                        case 'AVG':
                            if (is_array($tyLyRange) && !empty($tyLyRange)) {
                                foreach ($tyLyRange as $aliasKey => $range) {
                                    $measureSelect[] = "AVG((CASE WHEN " . $range . $extraCaseWhen . " THEN 1 ELSE 0 END) * IFNULL(" . $measure['VAL'] . ",0) ) AS  ".$aliasKey;
                                }
                            } else {
                                if (!empty($extraCaseWhen))
                                    $measureSelect[] = "AVG((CASE WHEN 1=1 " . $extraCaseWhen . " THEN 1 ELSE 0 END) * IFNULL(" . $measure['VAL'] . ",0) ) AS  ".$measure['ALIASE'];
                                else 
                                    $measureSelect[] = "AVG(IFNULL(" . $measure['VAL'] . ",0)) AS " . $measure['ALIASE'];
                            }
                            break;
                        case 'COUNT':
                                $measureSelect[] = "COUNT(".$measure['VAL'].") AS " . $measure['ALIASE'];
                            break;
                        default:
                            $measureSelect[] = $measure['VAL'] . " AS " . $measure['ALIASE'];
                            break;
                    }
                } else {

                    $fields     = (isset($measure['usedFields']) && !empty($measure['usedFields'])) ? $measure['usedFields'] : array();
                    if (!empty($fields)) {
                        $configurationCheck = new config\ConfigurationCheck($settingVars, $queryVars);
                        $configurationCheck->buildDataArray($fields);

                        if (is_array($fields) && !empty($fields)) {
                            foreach ($fields as $field) {
                                $dbColumnsArray[] = $configurationCheck->dbColumnsArray[$field];
                            }
                        }
                    }

                    switch ($measure['ATTR'])
                    {
                        case 'SUM':
                            if (!empty($dbColumnsArray)) {
                                if (is_array($tyLyRange) && !empty($tyLyRange)) {
                                    foreach ($tyLyRange as $aliasKey => $range) {
                                        $measureSelect[] = "SUM((CASE WHEN " . $range . " THEN 1 ELSE 0 END) * IFNULL(" . $dbColumnsArray[0] . ",0) ) AS  ".$aliasKey;
                                    }
                                } else
                                    $measureSelect[] = "SUM(IFNULL(" . $dbColumnsArray[0] . ",0)) AS " . $measure['ALIASE'];
                            }
                            break;
                        case 'AVG':
                            if (!empty($dbColumnsArray)) {
                                if (is_array($tyLyRange) && !empty($tyLyRange)) {
                                    foreach ($tyLyRange as $aliasKey => $range) {
                                        $measureSelect[] = "AVG((CASE WHEN " . $range . " THEN 1 ELSE 0 END) * IFNULL(" . $dbColumnsArray[0] . ",0) ) AS  ".$aliasKey;
                                    }
                                } else
                                    $measureSelect[] = "AVG(IFNULL(" . $dbColumnsArray[0] . ",0)) AS " . $measure['ALIASE'];
                            }
                            break;
                        case 'PRICE':
                            if (!empty($dbColumnsArray)) {
                                if (is_array($tyLyRange) && !empty($tyLyRange)) {
                                    foreach ($tyLyRange as $aliasKey => $range) {
                                        $measureSelect[] = "SUM((CASE WHEN " . $range . " THEN 1 ELSE 0 END) * IFNULL(" . $dbColumnsArray[0] . ",0) )/SUM((CASE WHEN " . $range . " THEN 1 ELSE 0 END) * IFNULL(" . $dbColumnsArray[1] . ",0) ) AS  ".$aliasKey;
                                    }
                                } else
                                    $measureSelect[] = "(SUM(IFNULL(" . $dbColumnsArray[0] . ",0))/SUM(IFNULL(" . $dbColumnsArray[1] . ",1))) AS " . $measure['ALIASE'];
                            }
                            break;
                        case 'AVGPRICE':
                            if (!empty($dbColumnsArray)) {
                                if (is_array($tyLyRange) && !empty($tyLyRange)) {
                                    foreach ($tyLyRange as $aliasKey => $range) {
                                        $measureSelect[] = "AVG((CASE WHEN " . $range . " THEN 1 ELSE 0 END) * IFNULL(" . $dbColumnsArray[0] . ",0) )/AVG((CASE WHEN " . $range . " THEN 1 ELSE 0 END) * IFNULL(" . $dbColumnsArray[1] . ",0) ) AS  ".$aliasKey;
                                    }
                                } else
                                    $measureSelect[] = "(AVG(IFNULL(" . $dbColumnsArray[0] . ",0))/AVG(IFNULL(" . $dbColumnsArray[1] . ",1))) AS " . $measure['ALIASE'];
                            }
                            break;
                        case 'DISTRIBUTION':
                            if (!empty($dbColumnsArray)) {
                                if (is_array($tyLyRange) && !empty($tyLyRange)) {
                                    foreach ($tyLyRange as $aliasKey => $range) {
                                        $measureSelect[] = "COUNT(DISTINCT((CASE WHEN " . $dbColumnsArray[0] . " > 0 AND " . $range . " THEN " . $dbColumnsArray[1] . " END))) AS " . $aliasKey;
                                    }
                                } else
                                    $measureSelect[] = "COUNT(DISTINCT((CASE WHEN " . $dbColumnsArray[0] . " > 0  THEN " . $dbColumnsArray[1] . " END))) AS " . $measure['ALIASE'];
                            }
                            break;
                        case 'RL_DISTRIBUTION':
                            if (!empty($dbColumnsArray)) {
                                $measureSelect[] = "MAX(".$dbColumnsArray[0] . " ) AS " . $measure['ALIASE'];
                            }
                            break;
                        case 'UROS_CROS':
                            $fromWeek = (isset($_REQUEST["FromWeek"]) && !empty($_REQUEST["FromWeek"])) ? explode("-", $_REQUEST["FromWeek"])[0] : 0;
                            $fromYear = (isset($_REQUEST["FromWeek"]) && !empty($_REQUEST["FromWeek"])) ? explode("-", $_REQUEST["FromWeek"])[1] : 0;
                            $toWeek = (isset($_REQUEST["ToWeek"]) && !empty($_REQUEST["ToWeek"])) ? explode("-", $_REQUEST["ToWeek"])[0] : 0;
                            $toYear = (isset($_REQUEST["ToWeek"]) && !empty($_REQUEST["ToWeek"])) ? explode("-", $_REQUEST["ToWeek"])[1] : 0;
                            $weekCnt = filters\timeFilter::getTotalWeek($fromYear,$fromWeek,$toYear,$toWeek);

                            if (!empty($dbColumnsArray)) {
                                if (is_array($tyLyRange) && !empty($tyLyRange)) {
                                    foreach ($tyLyRange as $aliasKey => $range) {
                                        $measureSelect[] = "SUM(IFNULL((CASE WHEN " . $range . " THEN 1 ELSE 0 END) * " . $dbColumnsArray[0] . ",0) )/COUNT(DISTINCT((CASE WHEN " . $range . " AND " . $dbColumnsArray[1] . " > 0 THEN " . $dbColumnsArray[2] . " END)))/".$weekCnt." AS  ".$aliasKey;
                                    }
                                } else
                                    $measureSelect[] = "SUM(IFNULL(" . $dbColumnsArray[0] . ",0))/COUNT(DISTINCT((CASE WHEN " . $dbColumnsArray[1] . " > 0 THEN " . $dbColumnsArray[2] . " END))) AS " . $measure['ALIASE'];
                            }
                            break;
                        case 'KG':
                            if (!empty($dbColumnsArray)) {
                                if (is_array($tyLyRange) && !empty($tyLyRange)) {
                                    foreach ($tyLyRange as $aliasKey => $range) {
                                        $measureSelect[] = "SUM((CASE WHEN " . $range . " THEN 1 ELSE 0 END) * ( IFNULL(".$dbColumnsArray[0].",0)*IFNULL(".$dbColumnsArray[1].",0)) ) AS   ".$aliasKey;
                                    }
                                } else {
                                    $measureSelect[] = "SUM( ROUND( IFNULL(".$dbColumnsArray[0].",0)*IFNULL(".$dbColumnsArray[1].",0),0) ) AS ".$measure['ALIASE'];
                                }
                            }
                            break;
                        case 'MULTIPLY_TWO_FIELDS_WITH_SUM':
                            if (!empty($dbColumnsArray)) {
                                if (is_array($tyLyRange) && !empty($tyLyRange)) {
                                    foreach ($tyLyRange as $aliasKey => $range) {
                                        $measureSelect[] = "SUM((CASE WHEN " . $range . " THEN 1 ELSE 0 END) * ( IFNULL(".$dbColumnsArray[0].",0)*IFNULL(".$dbColumnsArray[1].",0)) ) AS   ".$aliasKey;
                                    }
                                } else {
                                    $measureSelect[] = "SUM( ROUND( IFNULL(".$dbColumnsArray[0].",0)*IFNULL(".$dbColumnsArray[1].",0),0) ) AS ".$measure['ALIASE'];
                                }
                            }
                            break;
                        default:
                            $measureSelect[] = $measure['VAL'] . " AS " . $measure['ALIASE'];
                            break;
                    }
                }
            }
        }

        return $measureSelect;
    }

    public function fetchAllTimeSelectionData($settingVars, &$jsonOutput, $extraParams) {
        $timeSelectionDataCollectors = new datahelper\Time_Selection_DataCollectors($settingVars);

        //COLLECT TIME SELECTION DATA
        if ($settingVars->includeFutureDates) {
            switch ($settingVars->timeSelectionUnit) {
                case 'weekYear':
					filters\timeFilter::getYTD($settingVars); //ALTERNATIVE OF getSlice WHEN THE PROJECT IS LOADING FOR FIRST TIME
                    $timeSelectionDataCollectors->getAllWeek_with_future_dates($jsonOutput);
                    break;
                case 'weekMonth':
					filters\timeFilter::getYTDMonth($settingVars); //ALTERNATIVE OF getSlice WHEN THE PROJECT IS LOADING FOR FIRST TIME
                    $timeSelectionDataCollectors->getAllMonth_with_future_dates($jsonOutput);
                    break;
                case 'period':
                    filters\timeFilter::getYTD($settingVars); //ALTERNATIVE OF getSlice WHEN THE PROJECT IS LOADING FOR FIRST TIME
                    $timeSelectionDataCollectors->getAllPeriod_with_future_dates($jsonOutput);
                    break;
            }
        } else {
            switch ($settingVars->timeSelectionUnit) {
                case 'weekYear':
                    $includeDate = (isset($settingVars->includeDateInTimeFilter)) ? $settingVars->includeDateInTimeFilter : true;
                    $timeSelectionDataCollectors->getAllWeek($jsonOutput, $includeDate);
                    filters\timeFilter::getYTD($settingVars); //ALTERNATIVE OF getSlice WHEN THE PROJECT IS LOADING FOR FIRST TIME
                    break;
                case 'weekMonth':
					filters\timeFilter::getYTDMonth($settingVars); //ALTERNATIVE OF getSlice WHEN THE PROJECT IS LOADING FOR FIRST TIME
                    $timeSelectionDataCollectors->getAllMonth($jsonOutput);
                    break;
                case 'date':
                    // TODO: NEED TO IMPLEMENT DEFAULT TY & LY CREATE FUNCTIONS AS PER REQUIREMENT
                    $timeSelectionDataCollectors->getAllMydate($jsonOutput);
                    break;
                case 'week':
					filters\timeFilter::getYTD($settingVars); //ALTERNATIVE OF getSlice WHEN THE PROJECT IS LOADING FOR FIRST TIME
                    $timeSelectionDataCollectors->getOnlyWeek($jsonOutput);
                    break;
                case 'days':
                    filters\timeFilter::getDays($settingVars); //ALTERNATIVE OF getSlice WHEN THE PROJECT IS LOADING FOR FIRST TIME
                    $timeSelectionDataCollectors->getOnlyDays($jsonOutput);
                    break;
                case 'period':
                    // TODO: NEED TO IMPLEMENT DEFAULT TY & LY CREATE FUNCTIONS AS PER REQUIREMENT
                    $timeSelectionDataCollectors->getAllPeriod($jsonOutput);
                    break;
                case 'seasonal':
                    // TODO: NEED TO IMPLEMENT DEFAULT TY & LY CREATE FUNCTIONS AS PER REQUIREMENT
                    $timeSelectionDataCollectors->getAllSeasonal($jsonOutput);
                    break;
                case 'gnielsenFormat1':
                    filters\timeFilter::gnielsenFormat1($settingVars);
                    break;
                case 'none':
                    filters\timeFilter::prepareTyLy($settingVars);
                    break;
            }
        }

        $timeSelectionDataCollectors->getAllDates($jsonOutput);
    }

    public function prepareTimeFilter($settingVars, $queryVars, $extraParams) {
    	if ($_REQUEST['DataHelper'] == "true")
    		return;

    	$setDefaultTimeFilter = false;
    	switch ($settingVars->timeSelectionUnit) {
            case 'weekYear':
				if (!isset($_REQUEST["FromWeek"]) || empty($_REQUEST["FromWeek"]) || $_REQUEST["FromWeek"] == 'undefined' ||
					!isset($_REQUEST["ToWeek"]) || empty($_REQUEST["ToWeek"]) || $_REQUEST["ToWeek"] == 'undefined') {
					$setDefaultTimeFilter = true;
					break;
				}
				filters\timeFilter::getSlice($settingVars);
                break;
            case 'weekMonth':
            	if (!isset($_REQUEST["FromWeek"]) || empty($_REQUEST["FromWeek"]) || $_REQUEST["FromWeek"] == 'undefined' ||
					!isset($_REQUEST["ToWeek"]) || empty($_REQUEST["ToWeek"]) || $_REQUEST["ToWeek"] == 'undefined') {
					$setDefaultTimeFilter = true;
					break;
				}
				filters\timeFilter::getSliceMonth($settingVars); //ALTERNATIVE OF getSlice WHEN THE PROJECT IS LOADING FOR FIRST TIME
                break;
            case 'date':
                // TODO: NEED TO IMPLEMENT DEFAULT TY & LY CREATE FUNCTIONS AS PER REQUIREMENT
                filters\timeFilter::getDateSlice($settingVars);
                break;
            case 'week':
            	if (!isset($_REQUEST["FromWeek"]) || empty($_REQUEST["FromWeek"]) || $_REQUEST["FromWeek"] == 'undefined' || 
					!isset($_REQUEST["ToWeek"]) || empty($_REQUEST["ToWeek"]) || $_REQUEST["ToWeek"] == 'undefined') {
					$setDefaultTimeFilter = true;
					break;
				}
				filters\timeFilter::getYTD($settingVars); //ALTERNATIVE OF getSlice WHEN THE PROJECT IS LOADING FOR FIRST TIME
				filters\timeFilter::getSlice($settingVars);
                break;
            case 'days':
            	if (!isset($_REQUEST["FromDate"]) || empty($_REQUEST["FromDate"]) || $_REQUEST["FromDate"] == 'undefined') {
					$setDefaultTimeFilter = true;
					break;
				}
                filters\timeFilter::getDaysSlice($settingVars); //ALTERNATIVE OF getSlice WHEN THE PROJECT IS LOADING FOR FIRST TIME
                break;
            case 'period':
                // TODO: NEED TO IMPLEMENT DEFAULT TY & LY CREATE FUNCTIONS AS PER REQUIREMENT
                filters\timeFilter::getSlice($settingVars);
                break;
            case 'seasonal':
                // TODO: NEED TO IMPLEMENT DEFAULT TY & LY CREATE FUNCTIONS AS PER REQUIREMENT
                filters\timeFilter::getSliceSeasonal($settingVars);
                break;
            case 'gnielsenFormat1':
                filters\timeFilter::gnielsenFormat1($settingVars);
                break;
			case 'none':
                filters\timeFilter::prepareTyLy($settingVars);
                break;
        }

        if ($setDefaultTimeFilter) {
        	$configureProject = new config\ConfigureProject($settingVars, $queryVars);
        	$configureProject->fetch_all_timeSelection_data();
        }
    }
}