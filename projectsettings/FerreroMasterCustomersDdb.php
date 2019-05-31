<?php

namespace projectsettings;

class FerreroMasterCustomersDdb extends FerreroMasterSystem {

    public function __construct($aid, $projectID, $gids) {
        
        parent::__construct($aid, $projectID, $gids);

        if($this->projectTypeID == 6) // DDB
        {
            if(!$this->hasMeasureFilter)
            {
                $this->pageArray["MEASURE_SELECTION_LIST"] = array(
                    array('measureID' => 1, 'jsonKey'=>'VALUE', 'measureName' => 'VALUE', 'selected' => true),
                    array('measureID' => 2, 'jsonKey'=>'VOLUME', 'measureName' => 'VOLUME', 'selected' => true),
                    array('measureID' => 3, 'jsonKey'=>'STORECOUNT', 'measureName' => 'STORE COUNT', 'selected' => true),
                );
                
                $this->measureArray['M3']['VAL'] = "DISTINCT((CASE WHEN ".$this->ProjectVolume." > 0 THEN ".$this->maintable.".SNO END))";
                $this->measureArray['M3']['ALIASE'] = "STORE_COUNT";
                $this->measureArray['M3']['attr'] = "COUNT";
            }
        }
    }
    
}

?>