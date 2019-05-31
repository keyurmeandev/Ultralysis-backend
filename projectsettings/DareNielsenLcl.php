<?php

namespace projectsettings;

class DareNielsenLcl extends BaseLcl {

    public function __construct($aid, $projectID) {
        $this->maintable = "dare_nielsen";

        if($this->hasTerritory($aid, $projectID))
            $this->territorytable = "territory";
            
        $this->clientID = "DARE";
        $this->aid = $aid;
        $this->projectID = $projectID;

        parent::__construct(array(11));
		
        $this->includeFutureDates = true;
        
        if(!$this->hasMeasureFilter){
    		// measure selection list
            $this->pageArray["MEASURE_SELECTION_LIST"] = array(
                array('measureID' => 1, 'jsonKey'=>'VALUE', 'measureName' => 'Value ('.$this->currencySign.')', 'selected' => true ),
                array('measureID' => 2, 'jsonKey'=>'VOLUME', 'measureName' => 'Volume', 'selected' => false ),
                array('measureID' => 3, 'jsonKey'=>'EQVOL', 'measureName' => 'EQ Vol', 'selected' => false )
            );
        }
		
        $this->configureClassVars();
        
        if(!$this->hasMeasureFilter){

            $this->measureArray['M3']['VAL'] 	= "eqvol";
            $this->measureArray['M3']['ALIASE'] = "EQVOL";
            $this->measureArray['M3']['attr'] 	= "SUM";
            unset($this->measureArray['M3']['dataDecimalPlaces']);

            unset($this->measureArray['M4']); // due to 'attr' element
            $this->measureArray['M4']['VAL'] = "(SUM(IFNULL($this->ProjectValue,0))/SUM(IFNULL($this->ProjectVolume,1)))";
            $this->measureArray['M4']['ALIASE'] = "PRICE";   
            $this->measureArray['M4']['dataDecimalPlaces'] = 2;
            
            $this->measureArray['M5']['VAL'] = "DISTINCT((CASE WHEN ".$this->ProjectValue." > 0 THEN ".$this->maintable.".SNO END))";
            $this->measureArray['M5']['ALIASE'] = "DISTRIBUTION";
            $this->measureArray['M5']['attr'] = "COUNT";
            $this->measureArray['M5']['dataDecimalPlaces']   = 0;
            
            $this->performanceTabMappings = array(
                "drillDown" => 'M1',
                "overTime" => 'M1',
                "priceOverTime" => 'M4',
                "distributionOverTime" => 'M5'
            );
            
        }
    }

}

?>