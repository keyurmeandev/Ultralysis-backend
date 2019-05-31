<?php

namespace projectsettings;

class JohnsonvillieLcl extends BaseLcl {

    public function __construct($aid, $projectID) {
        $this->maintable = "johnsonville_mults";

        if($this->hasTerritory($aid, $projectID))
            $this->territorytable = "territory";
            
        $this->clientID = "JV";
        $this->aid = $aid; // 52
        $this->projectID = $projectID;

        parent::__construct();	
		$this->clustertable = "";
        $this->configureClassVars();

        if(!$this->hasMeasureFilter){
        
    		// measure selection list
            $this->pageArray["MEASURE_SELECTION_LIST"] = array(
                array('measureID' => 1, 'jsonKey'=>'VALUE', 'measureName' => 'Value ('.$this->currencySign.')'),
                array('measureID' => 2, 'jsonKey'=>'VOLUME', 'measureName' => 'Volume'),
                array('measureID' => 3, 'jsonKey'=>'KG', 'measureName' => 'KG')
            );
            
            if($this->projectTypeID == 6) // DDB
            {
                $this->pageArray["MEASURE_SELECTION_LIST"] = array(
                    array('measureID' => 1, 'jsonKey'=>'VALUE', 'measureName' => 'VALUE ('.$this->currencySign.')', 'selected' => true ),
                    array('measureID' => 2, 'jsonKey'=>'VOLUME', 'measureName' => 'VOLUME', 'selected' => true )
                );
            }
        
            $this->measureArray['M3']['VAL']    = "ROUND(WEIGHT*converting,0)";
            $this->measureArray['M3']['ALIASE'] = "KG";
            $this->measureArray['M3']['attr']   = "SUM";
            $this->measureArray['M3']['usedFields']   = array('product.converting');
            unset($this->measureArray['M3']['dataDecimalPlaces']);
            
            unset($this->measureArray['M4']); // due to 'attr' element
            $this->measureArray['M4']['VAL'] = "(SUM(IFNULL($this->ProjectValue,0))/SUM(IFNULL($this->ProjectVolume,1)))";
            $this->measureArray['M4']['ALIASE'] = "PRICE";
            $this->measureArray['M4']['dataDecimalPlaces'] = 2; 
            
            $this->measureArray['M5']['VAL'] = "DISTINCT((CASE WHEN ".$this->ProjectValue." > 0 THEN ".$this->maintable.".SNO END))";
            $this->measureArray['M5']['ALIASE'] = "DISTRIBUTION";
            $this->measureArray['M5']['attr'] = "COUNT";    
            $this->measureArray['M5']['dataDecimalPlaces'] = 0;         
        }
        
        $this->performanceTabMappings = array(
            "drillDown" => 'M1',
            "overTime" => 'M1',
            "priceOverTime" => 'M4',
            "distributionOverTime" => 'M5'
        );

    }

}

?>