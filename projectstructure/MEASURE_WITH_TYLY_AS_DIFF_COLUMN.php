<?php
namespace projectstructure;

use config;
use filters;
use datahelper;

class MEASURE_WITH_TYLY_AS_DIFF_COLUMN implements BaseProjectStructure
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
                $options['tyLyFieldData'][$measureTYValue] = '_ty';
            } else {
                if(($measureVal['measureID'] == $_REQUEST['ValueVolume']))
                    $havingTYValue = $measure['ALIASE'];
            }

            if (!empty(filters\timeFilter::$lyWeekRange)) {
                if(($measureVal['measureID'] == $_REQUEST['ValueVolume']))
                    $havingLYValue = "LY" . $measure['ALIASE'];
                $measureLYValue = "LY" . $measure['ALIASE'];
                $options['tyLyRange'][$measureLYValue] = trim(filters\timeFilter::$lyWeekRange);
                $options['tyLyFieldData'][$measureLYValue] = '_ly';
            } else {
                if(($measureVal['measureID'] == $_REQUEST['ValueVolume']))
                    $havingLYValue = $measure['ALIASE'];
            }
            
            $measureArr[$measureKey] = config\MeasureConfigure::prepareSelect($settingVars, $queryVars, array($measureKey), $options);
            $measureSelectionArr = array_merge($measureSelectionArr,$measureArr[$measureKey]);
            // This array will use for ddb project only
            if($measure['attr'] == "SUM")
                $ddbMeasureHavingPart[] = "TY".$measure['ALIASE']." <>0 ";
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

                $tyLyFieldPart = (isset($options['tyLyFieldPart']) && !empty($options['tyLyFieldPart'])) ? $options['tyLyFieldPart'] : '_ty';
                if(!$settingVars->hasMeasureFilter) {
                    switch ($measure['attr'])
                    {
                        case 'SUM':
                            if (is_array($tyLyRange) && !empty($tyLyRange)) {
                                foreach ($tyLyRange as $aliasKey => $range) {

                                    $tyLyFieldData = (isset($options['tyLyFieldData']) && isset($options['tyLyFieldData'][$aliasKey])) ? $options['tyLyFieldData'][$aliasKey] : '';

                                    $measureSelect[] = "SUM((CASE WHEN " . $range . $extraCaseWhen . " THEN 1 ELSE 0 END) * IFNULL(" . $measure['VAL']. $tyLyFieldData . ",0) ) AS ".$aliasKey;
                                }
                            } else {
                                if (!empty($extraCaseWhen))
                                    $measureSelect[] = "SUM((CASE WHEN 1=1 " . $extraCaseWhen . " THEN 1 ELSE 0 END) * IFNULL(" . $measure['VAL'].$tyLyFieldPart . ",0) ) AS  ".$measure['ALIASE'].$tyLyFieldPart;
                                else 
                                    $measureSelect[] = "SUM(IFNULL(" . $measure['VAL'].$tyLyFieldPart . ",0)) AS " . $measure['ALIASE'].$tyLyFieldPart;
                            }
                            break;
                        case 'COUNT':
                                $measureSelect[] = "COUNT(".$measure['VAL'].$tyLyFieldPart.") AS " . $measure['ALIASE'].$tyLyFieldPart;
                            break;
                        default:
                            $measureSelect[] = $measure['VAL'].$tyLyFieldPart . " AS " . $measure['ALIASE'].$tyLyFieldPart;
                            break;
                    }
                } else {

                    /*[NOTE] WHEN YOU ENABLE THE MEASURE DYNAMIC FROM THE PROJECT MANAGEMENT THEN YOU NEED TO SETUP THIS PART*/

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
                filters\timeFilter::$lyWeekRange = filters\timeFilter::$tyWeekRange;
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