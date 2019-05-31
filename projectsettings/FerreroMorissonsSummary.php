<?php

namespace projectsettings;

class FerreroMorissonsSummary extends baseMultsSummary {

    public function __construct($accountID,$uid,$projectID) {
		
        $this->maintable            		= "ferrero_mults_summary";
        $this->clientID             		= "FERRERO";     
		$this->GID							= 3;
        $this->footerCompanyName    		= "Ferrero UK";
		
		parent::__construct($accountID,$uid,$projectID);

        $this->getClientAndRetailerLogo();

		$this->dateperiod = "period";
		
		$this->configureClassVars();
        
        // measure selection list
        $this->pageArray["MEASURE_SELECTION_LIST"] = array(
            array('measureID' => 1, 'jsonKey'=>'VOLUME', 'measureName' => 'Units')
        );        

        // $this->measureArray = array();
        $this->measureArray['M1']['VAL']    = $this->ProjectVolume;
        $this->measureArray['M1']['ALIASE'] = "VOLUME";
        $this->measureArray['M1']['attr']   = "SUM";
        
        $this->performanceTabMappings = array(
            "drillDown" => 'M1',
            "overTime" => 'M1',
            // "priceOverTime" => 'M2',
            "distributionOverTime" => 'M4',
            "stockOverTime" => 'M5'
        );
		
    } 
}

?>