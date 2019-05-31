<?php
namespace projectsettings;

class ShanaRelayPlusDailyDDB extends baseRetailLinkDaily {

    public function __construct($accountID,$projectID) {
	
        $this->maintable            = "shana_retail_link_daily_14";		
        $this->clientID             = "SHANA";   
        $this->footerCompanyName    = "Shana";
        $this->GID                  = 2;
		
        parent::__construct($accountID,$projectID);        
		
		$this->dateperiod			= $this->DatePeriod;
		
		$this->configureClassVars();
		
    }    
}
?>