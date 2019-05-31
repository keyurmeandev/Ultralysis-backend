<?php

namespace projectsettings;

class MjnNielsen extends BaseGnielsen {

    public function __construct($aid, $projectID) {
        $this->maintable = "mjn_nielsen";

        $this->territorytable = "";
            
        $this->clientID = "MJN";
        $this->aid = $aid;
        $this->projectID = $projectID;

        parent::__construct(array(1));

        $this->getClientAndRetailerLogo();
		
        $this->showContributionAnalysisPopup = false;
        $this->headerFooterSourceText = "AC Nielsen (INFANT & STAGE 3)";
        
        $this->configureClassVars();
        
        // measure selection list
        $this->pageArray["MEASURE_SELECTION_LIST"] = array(
            array('measureID' => 1, 'jsonKey'=>'VALUE', 'measureName' => 'SALES ('.$this->currencySign.')'),
            array('measureID' => 2, 'jsonKey'=>'VOLUME', 'measureName' => 'SALES (YIELD)')
        );
        
    }
}
?>