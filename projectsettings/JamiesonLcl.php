<?php

namespace projectsettings;

class JamiesonLcl extends BaseLcl {

    public function __construct($aid, $projectID) {
        $this->maintable = "jamieson_mults";

        if($this->hasTerritory($aid, $projectID))
            $this->territorytable = "territory";
            
        $this->clientID = "JAMIESON";
        $this->aid = $aid;
        $this->projectID = $projectID;

        parent::__construct(array(10));
		
        if(!$this->hasMeasureFilter){
    		// measure selection list
            $this->pageArray["MEASURE_SELECTION_LIST"] = array(
                array('measureID' => 1, 'jsonKey'=>'VALUE', 'measureName' => 'Value ('.$this->currencySign.')', 'selected' => true ),
                array('measureID' => 2, 'jsonKey'=>'VOLUME', 'measureName' => 'Volume', 'selected' => false )
            );
            
            if($this->projectTypeID == 6) // DDB
            {
                $this->pageArray["MEASURE_SELECTION_LIST"] = array(
                    array('measureID' => 1, 'jsonKey'=>'VALUE', 'measureName' => 'VALUE', 'selected' => true),
                    array('measureID' => 2, 'jsonKey'=>'VOLUME', 'measureName' => 'VOLUME', 'selected' => true),
                    array('measureID' => 3, 'jsonKey'=>'PRICE', 'measureName' => 'AVE PRICE', 'selected' => false),
                    array('measureID' => 4, 'jsonKey'=>'DISTRIBUTION', 'measureName' => 'STORES SELLING', 'selected' => false)
                );            
            }
        }
		
        $this->configureClassVars();
       
    }

}

?>