<?php

namespace projectsettings;

class FerreroTescoIreland extends BaseLcl {

    public function __construct($aid, $projectID) {
        $this->maintable            = "ferrero_ireland_mults";
        
        if($this->hasTerritory($aid, $projectID))
            $this->territorytable = "territory";
            
        $this->clientID = "FERRERO";
        $this->aid = $aid;
        $this->projectID = $projectID;

        parent::__construct(array(26));

        $this->configureClassVars();

        if(!$this->hasMeasureFilter){
            //measure selection list
            if($this->projectTypeID == 6) // DDB
            {
                $this->pageArray["MEASURE_SELECTION_LIST"] = array(
                    array('measureID' => 1, 'jsonKey'=>'VALUE', 'measureName' => 'Value ('.$this->currencySign.')', 'selected' => true),
                    array('measureID' => 2, 'jsonKey'=>'VOLUME', 'measureName' => 'Volume', 'selected' => true)
                );
            }
        }
    }

    /*public function fetchGroupDetail()
    {
        $this->currencySign = "€";
        $this->groupName = "Tesco Ireland";
    }*/
    
}

?>