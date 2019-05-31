<?php

namespace classes\retailer;

use datahelper;
//use projectsettings;
use filters;
//use utils;
use db;
use config;

class StockVsSales extends \classes\relayplus\StockVsSales {
    

	function getCustomSelectPart(){

        $customSelectPart = " ,SUM((CASE WHEN ".$this->settingVars->ProjectVolume.">0 AND ".$this->settingVars->recordType."='SAL' THEN 1 ELSE 0 END)*".$this->settingVars->ProjectVolume.") AS SALES " .
                            " ,SUM((CASE WHEN " . $this->settingVars->dateField . "='" . filters\timeFilter::$tyDaysRange[0] . "' AND ".$this->settingVars->recordType."='STK' THEN 1 ELSE 0 END)*".$this->settingVars->ProjectVolume.") AS STOCK ";

        return $customSelectPart;
    }
	
    
	
}

?>