<?php

namespace classes\totalmults;

use filters;
use db;
use config;

class PromotionTracker extends config\UlConfig {
    /*     * ***
	 * go()
     * Default gateway function, should be similar in all DATA CLASS
     * arguments:
     * $settingVars [project settingsGateway variables]
     * @return $this->jsonOutput with all data that should be sent to client app
     * *** */

    public function go($settingVars) {
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES
        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll
		$this->trackerGrid();
				
        return $this->jsonOutput;
    }
	
	/**
	 * trackerGrid()
     * This Function is used to retrieve list based on set parameters     
	 * @access private	
     * @return $this->jsonOutput with all data that should be sent to client app     
     */
    private function trackerGrid() {
		
		$storeStock 	= $this->settingVars->dataArray['F18']['NAME'];
		$availInst 		= $this->settingVars->dataArray['F15']['NAME'];
		$depotService	= $this->settingVars->dataArray['F14']['NAME'];
		
		$latestDays = filters\timeFilter::getPeriodWithinRange(0, 1, $this->settingVars);
		
		//asort($latestDays);
		
        //MAIN TABLE QUERY
        $query = "SELECT ".$this->settingVars->maintable.".skuID AS SKUID " .
                ",sku AS SKU " .
                ",SUM(".$this->settingVars->ProjectValue.") AS SALES" .
                ",SUM(".$this->settingVars->ProjectVolume.") AS QTY" .
				",SUM((CASE WHEN " . $this->settingVars->period . "='".$latestDays[0]."' THEN 1 ELSE 0 END)*$storeStock) AS STORESTOCK".
				",SUM((CASE WHEN " . $this->settingVars->period . "='".$latestDays[0]."' THEN 1 ELSE 0 END)*$availInst) AS STOREINSTOCK".
				",SUM($availInst) AS TOTAL_STOREINSTOCK".
				",SUM((CASE WHEN " . $this->settingVars->period . "='".$latestDays[0]."' THEN 1 ELSE 0 END)*$depotService) AS DEPOTSERVICE".
				",SUM($depotService) AS TOTAL_DEPOTSERVICE".
                " FROM " . $this->settingVars->tablename . $this->queryPart .
                " GROUP BY SKUID, SKU HAVING SALES > 0 ORDER BY SALES DESC";
		//echo $query;exit;
        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
        
		$arr = array();
		$cols = array();
				
		if (is_array($result) && !empty($result)) {	

			foreach ($result as $key => $value) {
				
				$temp 					= array();
				$temp['SKUID'] 			= $value['SKUID'];
				$temp['SKU'] 			= $value['SKU'];
				$temp['SALES'] 			= number_format($value['SALES'], 1, '.', '');
				$temp['QTY'] 			= number_format($value['QTY'], 1, '.', '');
				$temp['STORESTOCK']		= $value['STORESTOCK'];
				$temp['STOREINSTOCK']	= ($value['TOTAL_STOREINSTOCK'] > 0) ? number_format(($value['STOREINSTOCK']/$value['TOTAL_STOREINSTOCK'])*100, 1, '.', '') : 0;
				$temp['DEPOTSERVICE']	= ($value['TOTAL_DEPOTSERVICE'] > 0) ? number_format(($value['DEPOTSERVICE']/$value['TOTAL_DEPOTSERVICE'])*100, 1, '.', '') : 0;
				$arr[] 					= $temp;				
			}
			
		}
        $this->jsonOutput['TrackerGrid'] = $arr;
    }
		
}

?>