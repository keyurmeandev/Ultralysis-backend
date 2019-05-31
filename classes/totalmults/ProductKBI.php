<?php

namespace classes\totalmults;

use filters;

class ProductKBI extends \classes\ProductKBI {

    /*     * ***
     * Default gateway function, should be similar in all DATA CLASS
     * arguments:
     *  $settingVars [project settingsGateway variables]
     * @return $jsonOutput with all data that should be sent to client app
     * *** */

    public function go($settingVars) {
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES
		filters\timeFilter::calculateTotalWeek($settingVars); //Total Weeks
		
        $this->queryPart = $this->getAll();
        filters\timeFilter::getExtraSlice_ByQuery($this->settingVars);
        
		$this->settingVars->pageName = (empty($this->settingVars->pageName)) ? $this->settingVars->pageID.'_KBIPage' : $this->settingVars->pageName;
		
		if ($this->settingVars->isDynamicPage) {
			$this->gridField = $this->getPageConfiguration('grid_field', $this->settingVars->pageID)[0];
			$this->buildDataArray(array($this->gridField));
			$this->buildPageArray();
		} else {
            if (!isset($this->settingVars->pageArray[$this->settingVars->pageName]) || empty($this->settingVars->pageArray[$this->settingVars->pageName]))
                $this->configurationFailureMessage();

			/*$this->accountID = (isset($this->settingVars->dataArray['F2']) && isset($this->settingVars->dataArray['F2']['ID'])) ? $this->settingVars->dataArray['F2']['ID'] : $this->settingVars->dataArray['F2']['NAME'];
	        $this->accountName = $this->settingVars->dataArray['F2']['NAME'];
	        $this->countField = $this->settingVars->dataArray['F22']['NAME'];*/

            $this->accountID = (isset($this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['ACCOUNT']]) && isset($this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['ACCOUNT']]['ID'])) ? $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['ACCOUNT']]['ID'] : $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['ACCOUNT']]['NAME'];
            $this->accountName = $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['ACCOUNT']]['NAME'];
            $this->countField = $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['COUNT_FIELD']]['NAME'];
	    }

        /* $this->pageName = $_REQUEST["pageName"]; 
        $this->skuID = $this->settingVars->dataArray[$this->settingVars->pageArray[$this->pageName]['ACCOUNT']]['ID'];
        $this->skuName = $this->settingVars->dataArray[$this->settingVars->pageArray[$this->pageName]['ACCOUNT']]['NAME'];
        $this->countField = !key_exists('ID', $this->settingVars->dataArray[$this->settingVars->pageArray[$this->pageName]['COUNT_FIELD']]) ? $this->settingVars->dataArray[$this->settingVars->pageArray[$this->pageName]['COUNT_FIELD']]['NAME'] : $this->settingVars->dataArray[$this->settingVars->pageArray[$this->pageName]['COUNT_FIELD']]['ID'];*/
		
		$this->customSelectPart();
        $this->settingDownloadLink(); // GETTING DOWNLOAD LINK DATA
        
        $this->prepareSummaryData(); //ADDING TO OUTPUT
        $this->prepareMainGridData(); //ADDING TO OUTPUT

        return $this->jsonOutput;
    }
	
	public function customSelectPart()
    {
		if(isset($this->settingVars->getStoreCountType) && $this->settingVars->getStoreCountType != '')
		{
			if($this->settingVars->getStoreCountType == "AVG")
			{
				$this->customSelectPart = "SUM(CASE WHEN " . filters\timeFilter::$tyWeekRange . " THEN " . $this->countField . " END)/".filters\timeFilter::$totalWeek." AS TP_DIST" .
												 ",SUM(CASE WHEN " . filters\timeFilter::$lyWeekRange . " THEN " . $this->countField . " END)/".filters\timeFilter::$totalWeek." AS LY_DIST" .
												 ",SUM(CASE WHEN " . filters\timeFilter::$ppWeekRange . " THEN " . $this->countField . " END)/".filters\timeFilter::$totalWeek." AS PP_DIST ";
			}
			else
			{
				$this->customSelectPart = $this->settingVars->getStoreCountType."(CASE WHEN " . filters\timeFilter::$tyWeekRange . " THEN " . $this->countField . " END) AS TP_DIST" .
												 ",".$this->settingVars->getStoreCountType."(CASE WHEN " . filters\timeFilter::$lyWeekRange . " THEN " . $this->countField . " END) AS LY_DIST" .
												 ",".$this->settingVars->getStoreCountType."(CASE WHEN " . filters\timeFilter::$ppWeekRange . " THEN " . $this->countField . " END) AS PP_DIST ";
			}
		}
    }
	    
}
?> 