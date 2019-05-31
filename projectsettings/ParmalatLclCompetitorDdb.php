<?php
namespace projectsettings;

class ParmalatLclCompetitorDdb extends baseMultsSummary {

    public function __construct($aid, $uid, $projectID) {
        $this->maintable        = "parmalat_lcl_competitor";
        $this->clientID         = "PARMALAT";
        $this->GID              = 10;

        parent::__construct($aid, $uid, $projectID);
        
        // measure selection list
        $this->pageArray["MEASURE_SELECTION_LIST"] = array(
            array('measureID' => 1, 'jsonKey'=>'VALUE', 'measureName' => 'Value ('.$this->currencySign.')', 'selected'=>true),
            array('measureID' => 2, 'jsonKey'=>'VOLUME', 'measureName' => 'Units', 'selected'=>true),
            array('measureID' => 3, 'jsonKey'=>'Baseline', 'measureName' => 'Baseline', 'selected'=>true),
            array('measureID' => 4, 'jsonKey'=>'Cannibalisation', 'measureName' => 'Cannibalisation', 'selected'=>true),
        );
        
        $this->configureClassVars();

		$this->measureArray['M3']['VAL']        = $this->maintable.".BASELINE";
		$this->measureArray['M3']['ALIASE']     = "Baseline";
		$this->measureArray['M3']['attr']       = "SUM";
        unset($this->measureArray['M3']['dataDecimalPlaces']);
		
		$this->measureArray['M4']['VAL']        = $this->maintable.".CANNIBALIZATION";
		$this->measureArray['M4']['ALIASE']     = "Cannibalisation";
		$this->measureArray['M4']['attr']	    = "SUM";
        unset($this->measureArray['M4']['dataDecimalPlaces']);
    }

}
?>