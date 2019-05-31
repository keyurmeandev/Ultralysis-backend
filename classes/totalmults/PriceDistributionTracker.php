<?php

namespace classes\totalmults;

use filters;
use db;
use config;

class PriceDistributionTracker extends \classes\PriceDistributionTracker {

    /** ***
     * Default gateway function, should be similar in all DATA CLASS
     * arguments:
     *  $settingVars [project settingsGateway variables]
     * @return $jsonOutput with all data that should be sent to client app
     * *** */

    public function go($settingVars) {
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES 
		filters\timeFilter::calculateTotalWeek($settingVars); //Total Weeks
        
        /* $this->pageName = $_REQUEST["pageName"];
        $this->settingVars->pageName = $this->pageName; // set pageName in settingVars */
		
		if ($this->settingVars->isDynamicPage) {
			$this->accountField = $this->getPageConfiguration('account_field', $this->settingVars->pageID)[0];
			$this->storeField = $this->getPageConfiguration('store_field', $this->settingVars->pageID)[0];
			$this->buildDataArray(array($this->accountField, $this->storeField));
			$this->buildPageArray();
		} else {
            if (!isset($this->settingVars->pageArray[$this->settingVars->pageName]) || empty($this->settingVars->pageArray[$this->settingVars->pageName]))
                $this->configurationFailureMessage();

			/*$this->accountID = (isset($this->settingVars->dataArray['F2']) && isset($this->settingVars->dataArray['F2']['ID'])) ? $this->settingVars->dataArray['F2']['ID'] : $this->settingVars->dataArray['F2']['NAME'];
	        $this->accountName = $this->settingVars->dataArray['F2']['NAME'];
	        $this->storeID = (isset($this->settingVars->dataArray['F22']) && isset($this->settingVars->dataArray['F22']['ID'])) ? $this->settingVars->dataArray['F22']['ID'] : $this->settingVars->dataArray['F22']['NAME'];
	        $this->storeName = $this->settingVars->dataArray['F22']['NAME'];*/

            $this->accountID = (isset($this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['ACCOUNT']]) && isset($this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['ACCOUNT']]['ID'])) ? $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['ACCOUNT']]['ID'] : $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['ACCOUNT']]['NAME'];
            $this->accountName = $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['ACCOUNT']]['NAME'];
            $this->storeID = (isset($this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['STORE_FIELD']]) && isset($this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['STORE_FIELD']]['ID'])) ? $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['STORE_FIELD']]['ID'] : $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['STORE_FIELD']]['NAME'];
            $this->storeName = $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['STORE_FIELD']]['NAME'];
	    }
        
        /* $this->accountField = $this->settingVars->pageArray[$this->pageName]["ACCOUNT"];
        $this->storeField = $this->settingVars->pageArray[$this->pageName]["STORE_FIELD"]; */

        $this->customSelectPart();
        
        $this->queryPart = $this->getAll();

        $action = $_REQUEST["action"];
        switch ($action) {
            case "reload": 
                $this->gridColumnName();
                return $this->reload();
                break;
            case "skuchange": return $this->changeSku();
                break;
        }
    }

    public function customSelectPart()
    {
        /* $storeField = !key_exists('ID', $this->settingVars->dataArray[$this->storeField]) ? $this->settingVars->dataArray[$this->storeField]["NAME"] : $this->settingVars->dataArray[$this->storeField]["ID"]; */
        if($this->settingVars->getStoreCountType == "AVG")
            $this->customSelectPart = "SUM(CASE WHEN " . $this->settingVars->ProjectValue . ">0 THEN ".$this->storeID." END)/".filters\timeFilter::$totalWeek." AS DIST ";
        else
            $this->customSelectPart = $this->settingVars->getStoreCountType."(CASE WHEN " . $this->settingVars->ProjectValue . ">0 THEN ".$this->storeID." END) AS DIST ";
    }
}
?> 