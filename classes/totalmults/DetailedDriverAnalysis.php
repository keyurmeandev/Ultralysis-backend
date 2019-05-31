<?php

namespace classes\totalmults;

use db;
use filters;
use config;

class DetailedDriverAnalysis extends \classes\lcl\DetailedDriverAnalysis {

    public function go($settingVars) {
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES
		filters\timeFilter::calculateTotalWeek($settingVars); //Total Weeks
        
        $this->pageName = $_REQUEST["pageName"];
        $this->settingVars->pageName = $this->pageName; // set pageName in settingVars
        
        $this->queryPart = $this->getAll(); //USES OWN getAll FUNCTION

        $this->prepare_DDA_Data(); //ADDING TO OUTPUT [DDA => DETAILED DRIVER ANALYSIS]

        return $this->jsonOutput;
    }

    public function customSelectPart($year, $week, $flag = true)
    {
    	$storesRanged = $this->settingVars->dataArray['F8']["NAME"];
        if($this->settingVars->getStoreCountType == "AVG") {
        	if ($flag)
            	return "SUM((CASE WHEN " . $this->settingVars->yearperiod . "=$year AND " . $this->settingVars->weekperiod . "=$week AND " . $this->settingVars->ProjectVolume . ">0 THEN 1 END)* ".$storesRanged." )/".filters\timeFilter::$totalWeek." TYEAR_DIST_$year$week, ";
            else
            	return "SUM((CASE WHEN " . $this->settingVars->yearperiod . "=$year AND " . $this->settingVars->weekperiod . "=$week AND " . $this->settingVars->ProjectVolume . ">0 THEN 1 END)* ".$storesRanged." )/".filters\timeFilter::$totalWeek." LYEAR_DIST_$year$week, ";
        } else {
        	if ($flag)
        		return $this->settingVars->getStoreCountType."((CASE WHEN " . $this->settingVars->yearperiod . "=$year AND " . $this->settingVars->weekperiod . "=$week AND " . $this->settingVars->ProjectVolume . ">0 THEN 1 END)* ".$storesRanged." ) TYEAR_DIST_$year$week, ";
        	else
        		return $this->settingVars->getStoreCountType."((CASE WHEN " . $this->settingVars->yearperiod . "=$year AND " . $this->settingVars->weekperiod . "=$week AND " . $this->settingVars->ProjectVolume . ">0 THEN 1 END)* ".$storesRanged." ) LYEAR_DIST_$year$week, ";
        }
    }
}

?>