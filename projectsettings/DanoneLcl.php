<?php

namespace projectsettings;

class DanoneLcl extends BaseLcl {

    public function __construct($aid, $projectID) {
        $this->maintable = "danone_lcl";

        if($this->hasTerritory($aid, $projectID))
            $this->territorytable = "territory";
            
        
        $this->clientID = "DANONE";
        $this->aid = $aid; // 52
        $this->projectID = $projectID;

        parent::__construct();		
        $this->clustertable = "";
        if(!$this->hasMeasureFilter){
    		// measure selection list
            $this->pageArray["MEASURE_SELECTION_LIST"] = array(
                array('measureID' => 1, 'jsonKey'=>'VALUE', 'measureName' => 'Value ('.$this->currencySign.')'),
                array('measureID' => 2, 'jsonKey'=>'VOLUME', 'measureName' => 'Volume')
            );
            
            if($this->projectTypeID == 6) // DDB
            {
                $this->pageArray["MEASURE_SELECTION_LIST"] = array(
                    array('measureID' => 1, 'jsonKey'=>'VALUE', 'measureName' => 'VALUE ('.$this->currencySign.')', 'selected' => true ),
                    array('measureID' => 2, 'jsonKey'=>'VOLUME', 'measureName' => 'VOLUME', 'selected' => true )
                );
            }
        }
		
        $this->configureClassVars();
        
    }

}

?>