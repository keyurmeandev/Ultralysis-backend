<?php

namespace projectsettings;

class FcanadaLCLPlanoAudit extends BaseLcl {

    public function __construct($aid, $projectID) {

        $this->maintable = "ferrero_canada_mults";        
        $this->lclStorePlanoTable   = "lcl_store_plano";
        $this->lclShelfDetailsTable = "lcl_shelf_details";
        $this->lclPlanoImageTable   = "lcl_plano_image";
        $this->lclPlanoClientTable  = "lcl_plano_client";

        $this->clientID = "FCANADA";
        $this->aid = $aid;
        $this->projectID = $projectID;

        parent::__construct();		

        // $this->timeSelectionUnit = "";
        
        if(!$this->hasMeasureFilter){
    		
        }
		
        $this->configureClassVars();
        // $this->dateField = "";
    }

}

?>