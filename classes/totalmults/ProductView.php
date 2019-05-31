<?php

namespace classes\totalmults;

use filters;

class ProductView extends \classes\lcl\ProductView {

    public function go($settingVars) {
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES
		filters\timeFilter::calculateTotalWeek($settingVars); //Total Weeks
		
        $this->queryPart = $this->getAll(); //USES OWN getAll FUNCTION

        $this->customSelectPart();
        $this->prepareGridData(); //ADDING TO OUTPUT

        return $this->jsonOutput;
    }

    public function customSelectPart()
    {
    	$storesRanged = $this->settingVars->dataArray['F8']["NAME"];
    	if($this->settingVars->getStoreCountType == "AVG") {
    		$this->customSelectPart = "SUM((CASE WHEN " . $this->settingVars->ProjectVolume . ">0 AND " . filters\timeFilter::$tyWeekRange . " THEN 1 END )* $storesRanged)/".filters\timeFilter::$totalWeek." AS DIS" .
				",MAX(agg1) AS SUBBRAND, MAX(agg2) AS COLOR, MAX(agg3) AS SIZE ";
		} else {
			$this->customSelectPart = $this->settingVars->getStoreCountType."( (CASE WHEN " . $this->settingVars->ProjectVolume . ">0 AND " . filters\timeFilter::$tyWeekRange . " THEN 1 END )* $storesRanged) AS DIS" .
					",MAX(agg1) AS SUBBRAND, MAX(agg2) AS COLOR, MAX(agg3) AS SIZE ";
		}
    }
}

?>