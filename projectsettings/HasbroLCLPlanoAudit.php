<?php

namespace projectsettings;

class HasbroLCLPlanoAudit extends BaseLcl {

    public function __construct($aid, $projectID) {

        $this->lclStorePlanoTable   = "lcl_store_plano";
        $this->lclShelfDetailsTable = "lcl_shelf_details";
        $this->lclPlanoImageTable   = "lcl_plano_image";
        $this->lclPlanoClientTable  = "lcl_plano_client";

        $this->clientID = "HASBRO";
        $this->aid = $aid;
        $this->projectID = $projectID;

        parent::__construct();		
        
        $this->timeSelectionUnit = "";
        $this->configureClassVars();
        $this->dateField = "";
    }

}

?>