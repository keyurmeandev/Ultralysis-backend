<?php

namespace classes\totalmults;

use projectsettings;
use datahelper;
use filters;
use db;
use config;

class RangeEfficiency extends \classes\RangeEfficiency {

    public function go($settingVars) {
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES       
		filters\timeFilter::calculateTotalWeek($settingVars); //Total Weeks
        
        $this->settingVars->pageName = (empty($this->settingVars->pageName)) ? 
            $this->settingVars->pageID.'_Efficiency' : $this->settingVars->pageName;

		if ($this->settingVars->isDynamicPage) {
          $this->getAccountField();
        } else {
            if (!isset($this->settingVars->pageArray[$this->settingVars->pageName]) || 
                empty($this->settingVars->pageArray[$this->settingVars->pageName]))
                $this->configurationFailureMessage();
        }

        $this->barAccountName = $this->settingVars->pageArray[$this->settingVars->pageName]["BAR_ACCOUNT_TITLE"];;
        $account = $this->settingVars->pageArray[$this->settingVars->pageName]["ACCOUNT"];
        
        $this->queryPart = $this->getAll();  //PREPARE TABLE JOIN STRING USING PARENT getAll
        
        $id = !key_exists('ID', $this->settingVars->dataArray[$account]) ? $this->settingVars->dataArray[$account]["NAME"] : $this->settingVars->dataArray[$account]["ID"];
        $name = $this->settingVars->dataArray[$account]["NAME"];

		$this->fetchConfig(); // Fetching filter configuration for page
        $this->customSelectPart();
        $this->GridSKU($id, $name);

		$this->jsonOutput['accountTitle'] = $this->settingVars->pageArray[$this->settingVars->pageName]["BAR_ACCOUNT_TITLE"];

        return $this->jsonOutput;
    }

    public function customSelectPart()
    {
        $countAccount = $this->settingVars->pageArray[$this->settingVars->pageName]["COUNT_ACCOUNT"];
        $countcolumn = !key_exists('ID', $this->settingVars->dataArray[$countAccount]) ? 
			$this->settingVars->dataArray[$countAccount]["NAME"] : $this->settingVars->dataArray[$countAccount]["ID"];

        if($this->settingVars->getStoreCountType == "AVG")
            $this->customSelectPart = "SUM($countcolumn)/".filters\timeFilter::$totalWeek." AS STORES ";
        else
            $this->customSelectPart = $this->settingVars->getStoreCountType."($countcolumn) AS STORES ";
    }
}

?>