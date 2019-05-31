<?php
namespace projectstructure;

use config;
use filters;
use datahelper;

class MEASURE_AND_TYLY_AS_COLUMN implements BaseProjectStructure
{
    public function prepareMeasureSelectPart($settingVars, $queryVars)
    {
        $measureArr = $measureSelectionArr = $ddbMeasureHavingPart = array();
        
        foreach ($settingVars->pageArray["MEASURE_SELECTION_LIST"] as $key => $measureVal) {
            $options = array();
            $measureKey = 'M' . $measureVal['measureID'];
            $measure = $settingVars->measureArray[$measureKey];
            
            if (!empty(filters\timeFilter::$tyTimeframeRange)) {
                if(($measureVal['measureID'] == $_REQUEST['ValueVolume']))
                    $havingTYValue = "TY" . $measure['ALIASE'];
                $measureTYValue = "TY" . $measure['ALIASE'];
                $options['tyLyRange'][$measureTYValue] = "1=1";
                $options['tyLyFieldPart'][$measureTYValue] = trim(filters\timeFilter::$tyTimeframeRange);
            }else{
                $options['tyLyFieldPart'][$measure['ALIASE']] = trim(filters\timeFilter::$tyTimeframeRange);
                if(($measureVal['measureID'] == $_REQUEST['ValueVolume']))
                    $havingTYValue = $measure['ALIASE'];
            }

            if (!empty(filters\timeFilter::$lyTimeframeRange)) {
                if(($measureVal['measureID'] == $_REQUEST['ValueVolume']))
                    $havingLYValue = "LY" . $measure['ALIASE'];
                $measureLYValue = "LY" . $measure['ALIASE'];
                $options['tyLyRange'][$measureLYValue] = "1=1";
                $options['tyLyFieldPart'][$measureLYValue] = trim(filters\timeFilter::$lyTimeframeRange);
            }else{
                $options['tyLyFieldPart'][$measure['ALIASE']] = trim(filters\timeFilter::$lyTimeframeRange);
                if(($measureVal['measureID'] == $_REQUEST['ValueVolume']))
                    $havingLYValue = $measure['ALIASE'];
            }
            $measureArr[$measureKey] = config\MeasureConfigure::prepareSelect($settingVars, $queryVars, array($measureKey), $options);           
            $measureSelectionArr = array_merge($measureSelectionArr,$measureArr[$measureKey]);   

            // This array will use for ddb project only
            if($measure['attr'] == "SUM"){
                if (!empty(filters\timeFilter::$tyTimeframeRange)) {
                    $ddbMeasureHavingPart[] = "TY".$measure['ALIASE']." <>0 ";
                }
                
                if (!empty(filters\timeFilter::$lyTimeframeRange)) {
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
                $tyLyFieldPart  = (isset($options['tyLyFieldPart']) && !empty($options['tyLyFieldPart'])) ? $options['tyLyFieldPart'] : array();
                $extraCaseWhen = (isset($measure['CASE_WHEN']) && !empty($measure['CASE_WHEN'])) ? $measure['CASE_WHEN'] : '';

                if(!$settingVars->hasMeasureFilter) {
                    switch ($measure['attr'])
                    {
                        case 'SUM':
                            if (is_array($tyLyRange) && !empty($tyLyRange)) {
                                foreach ($tyLyRange as $aliasKey => $range) {
                                    $fieldPart = (isset($tyLyFieldPart[$aliasKey]) && !empty($tyLyFieldPart[$aliasKey])) ? '_'.$tyLyFieldPart[$aliasKey] : '';
                                    $measureSelect[] = "SUM((CASE WHEN " . $range . $extraCaseWhen . " THEN 1 ELSE 0 END) * IFNULL(" . $measure['VAL'].$fieldPart . ",0) ) AS  ".$aliasKey;
                                }
                            } else {
                                $fieldPart = (isset($tyLyFieldPart[$measure['ALIASE']]) && !empty($tyLyFieldPart[$measure['ALIASE']])) ? '_'.$tyLyFieldPart[$measure['ALIASE']] : '';
                                if (!empty($extraCaseWhen))
                                    $measureSelect[] = "SUM((CASE WHEN 1=1 " . $extraCaseWhen . " THEN 1 ELSE 0 END) * IFNULL(" . $measure['VAL'].$fieldPart . ",0) ) AS  ".$measure['ALIASE'];
                                else 
                                    $measureSelect[] = "SUM(IFNULL(" . $measure['VAL'].$fieldPart . ",0)) AS " . $measure['ALIASE'];
                            }
                            break;
                    }
                } else {

                }
            }
        }

        return $measureSelect;
	}

	public function fetchAllTimeSelectionData($settingVars, &$jsonOutput, $extraParams) {
		switch ($settingVars->timeSelectionUnit) {
            case 'none':
                filters\timeFilter::$daysTimeframe = 'YTD';
                filters\timeFilter::prepareTyLyTimeframeRange($settingVars);
                break;
        }
	}

	public function prepareTimeFilter($settingVars, $queryVars, $extraParams) {
		if ($_REQUEST['DataHelper'] == "true")
    		return;

    	$setDefaultTimeFilter = false;
    	switch ($settingVars->timeSelectionUnit) {
    		case 'none':
				if (!isset($_REQUEST["timeFrame"]) || empty($_REQUEST["timeFrame"]) || $_REQUEST["timeFrame"] == 'undefined') {
					$setDefaultTimeFilter = true;
					break;
				}
				filters\timeFilter::getSliceTimeframe($settingVars);
                break;
        }

        if ($setDefaultTimeFilter) {
        	$configureProject = new config\ConfigureProject($settingVars, $queryVars);
        	$configureProject->fetch_all_timeSelection_data();
        }
    }
}