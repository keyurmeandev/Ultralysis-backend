<?php
namespace projectsettings;

class FerreroTsdMultsSummary extends baseMultsSummary {

    public function __construct($accountID,$uid,$projectID) {
		
        $this->maintable            = "ferrero_mults_summary";
        $this->clientID             = "FERRERO";     
		$this->GID					= 1;
        $this->footerCompanyName    = "Ferrero UK";
		
		parent::__construct($accountID,$uid,$projectID);

		$this->getClientAndRetailerLogo();

		$this->dateperiod 			= "period";
		
		$this->configureClassVars();
    } 
}
?>