<?php

namespace projectsettings;

class fcanadaLD extends BaseLcl {

    public function __construct($aid, $projectID) {
        $this->maintable = "ferrero_canada_mults";

        if($this->hasTerritory($aid, $projectID))
            $this->territorytable = "territory";
            
        $this->clientID = "FCANADA";
        $this->aid = $aid;
        $this->projectID = $projectID;

        parent::__construct(array(13));		
		
        $this->accounttable = "fgroup";
        $this->accountLink  = " AND ".$this->accounttable.".gid = ".$this->maintable.".GID AND ".$this->accounttable.".GID IN (".$this->GID.")";
		
        $this->configureClassVars();

        if(!$this->hasMeasureFilter){
            // measure selection list
            $this->pageArray["MEASURE_SELECTION_LIST"] = array(
                array('measureID' => 1, 'jsonKey'=>'VALUE', 'measureName' => 'Value ('.$this->currencySign.')'),
                array('measureID' => 2, 'jsonKey'=>'VOLUME', 'measureName' => 'Volume')
            );
            
            $this->measureArray['M4']['VAL'] = "(SUM(IFNULL($this->ProjectValue,0))/SUM(IFNULL($this->ProjectVolume,1)))";
            $this->measureArray['M4']['ALIASE'] = "PRICE";
            $this->measureArray['M4']['dataDecimalPlaces'] = 2;            
            
            if($this->projectTypeID == 6) // DDB
            {
                $this->pageArray["MEASURE_SELECTION_LIST"] = array(
                    array('measureID' => 1, 'jsonKey'=>'VALUE', 'measureName' => 'VALUE', 'selected' => true),
                    array('measureID' => 2, 'jsonKey'=>'VOLUME', 'measureName' => 'VOLUME', 'selected' => true)
                );
            }            
        }
    }

}

?>