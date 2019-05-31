<?php

namespace classes\lcl;

use db;
use filters;
use config;

class SingleSalesCheckWeekly extends \classes\SingleSalesCheckWeekly {
    
    public function go($settingVars) {
        $this->initiate($settingVars);
        
        $this->pageName = $_REQUEST["pageName"];

        $this->settingVars->pageName = $this->pageName; // set pageName in settingVars
        $this->skuID = $this->settingVars->dataArray[$this->settingVars->pageArray[$this->pageName]["SKU_ACCOUNT"]]['ID'];
        $this->skuName = $this->settingVars->dataArray[$this->settingVars->pageArray[$this->pageName]["SKU_ACCOUNT"]]['NAME'];
        $this->storeID = $this->settingVars->dataArray[$this->settingVars->pageArray[$this->pageName]["STORE_ACCOUNT"]]['ID'];
        $this->storeName = $this->settingVars->dataArray[$this->settingVars->pageArray[$this->pageName]["STORE_ACCOUNT"]]['NAME'];
        
        $this->queryPart = $this->getAll();
        $this->queryCustomPart = $this->getAllCustom();
		
		$this->customSelectPart();
        
        $action = $_REQUEST["action"];
        switch ($action) {
            case "changesku": $this->changesku();
                break;
            case "reload": $this->reload();
                break;
            default:
				$this->form();
				$this->reload();
        }
        
        return $this->jsonOutput;
    }
	
	public function customSelectPart()
    {		
		$this->customSelectPart 	= " AND ".$this->settingVars->period . " IN (" . implode(",", filters\timeFilter::periodWithinRange(0, 12, $this->settingVars)) . ") ";
		$this->customSelectPart2	= '';
		$this->customTablePart 		= $this->settingVars->timeHelperTables." ".$this->settingVars->timeHelperLink;		
		$this->customTablePart2		= $this->settingVars->period;
		$this->customSelectPart3	= '';
	}

}
