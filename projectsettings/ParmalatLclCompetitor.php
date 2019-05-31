<?php

namespace projectsettings;

class ParmalatLclCompetitor extends BaseLcl {

    public function __construct($aid, $projectID) {
        $this->maintable = "parmalat_lcl_competitor";
        $this->accounttable = "parmalat_lcl_competitor";

        if($this->hasTerritory($aid, $projectID))
            $this->territorytable = "territory";
            
        
        $this->clientID = "PARMALAT";
        $this->aid = $aid; // 121
        $this->projectID = $projectID;

        parent::__construct();
        $this->clustertable = "";
        $this->dateperiod = 'period';
        $this->ProjectValue = "SALES";
		
        $this->configureClassVars();
        
        if(!$this->hasMeasureFilter){
            $this->pageArray["BIC_SALES_TRACKER"]['M1'] = array( 
                "BASELINE" => array("ALIAS" => "BASELINE_SALES", "FIELD" => $this->maintable.".BASELINE"),
                "SALES" => array("ALIAS" => "SALES", "FIELD" => $this->maintable.".".$this->ProjectValue),
                "CANNIBALIZATION" => array("ALIAS" => "CANNIBALIZATION", "FIELD" => $this->maintable.".CANNIBALIZATION") );
                
            $this->pageArray["BIC_SALES_TRACKER"]['M2'] = array( 
                "BASELINE" => array("ALIAS" => "BASELINE_QTY", "FIELD" => $this->maintable.".BASELINE_QTY"),
                "SALES" => array("ALIAS" => "QTY", "FIELD" => $this->maintable.".".$this->ProjectVolume),
                "CANNIBALIZATION" => array("ALIAS" => "CANNIBALIZATION", "FIELD" => "0") );
        }

    }

}

?>