<?php

namespace projectsettings;

class ShanaLclBannerView extends BaseLcl {
    public function __construct($aid, $projectID) {
        $this->maintable = "shana_lcl_bannerview";

        if($this->hasTerritory($aid, $projectID))
            $this->territorytable = "territory";
            
        
        $this->accounttable = "fgroup";
        $this->clientID = "SHANA";
        $this->aid = $aid;
        $this->projectID = $projectID;

        parent::__construct();
        $this->clustertable = "arla_custom_product_group";
        $this->accountLink = " AND $this->accounttable.GID IN ($this->GID) AND $this->maintable.GID=$this->accounttable.gid ";
        $this->accountHelperLink = " WHERE ".$this->accounttable.".GID IN (".$this->GID.") ";

        if(!$this->hasMeasureFilter){        
    		//measure selection list
            $this->pageArray["MEASURE_SELECTION_LIST"] = array(
                array('measureID' => 1, 'jsonKey'=>'VALUE', 'measureName' => 'Value ('.$this->currencySign.')', 'selected' => true ),
                array('measureID' => 2, 'jsonKey'=>'VOLUME', 'measureName' => 'Volume', 'selected' => false )
            );
        }

        $this->configureClassVars();
    }
}
?>