<?php

namespace projectsettings;

class OleivaLcl extends BaseLcl {

    public function __construct($aid, $projectID) {
        $this->maintable = "oleiva_mults";
        
        if($this->hasTerritory($aid, $projectID))
            $this->territorytable = "territory";
            
        
        $this->clientID = "OLEIVA";
        $this->aid = $aid; 
        $this->projectID = $projectID;
        $this->forceUseOriginalLink = true;

        parent::__construct();
        $this->clustertable = "";
        
        if ((isset($_REQUEST['YtdOn']) && $_REQUEST['YtdOn'] == 'CALENDAR_YEAR')) {
            $this->weekperiod = "$this->timetable.week";
            $this->yearperiod = "$this->timetable.year";
        }
		
        $this->configureClassVars();
        
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
                    array('measureID' => 2, 'jsonKey'=>'VOLUME', 'measureName' => 'VOLUME', 'selected' => true ),
                    array('measureID' => 3, 'jsonKey'=>'STORESELLING', 'measureName' => 'STORES SELLING', 'selected' => true)
                );
                
                $this->measureArray['M3']['VAL'] = "DISTINCT((CASE WHEN ".$this->ProjectVolume." > 0 THEN ".$this->maintable.".SNO END))";
                $this->measureArray['M3']['ALIASE'] = "STORE_SELLING";
                $this->measureArray['M3']['attr'] = "COUNT";
            }
        }
    }

}

?>