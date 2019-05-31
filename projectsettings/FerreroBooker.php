<?php

namespace projectsettings;

class FerreroBooker extends BaseLcl {

    public function __construct($aid, $projectID) {
        $this->maintable = "ferrero_booker";

        if($this->hasTerritory($aid, $projectID))
            $this->territorytable = "territory";

        $this->clientID = "FERRERO";
        $this->aid = $aid; // 52
        $this->projectID = $projectID;

        parent::__construct(array(14));

        $this->configureClassVars();
        $this->clustertable = "";

        if(!$this->hasMeasureFilter){
        
    		// measure selection list
            $this->pageArray["MEASURE_SELECTION_LIST"] = array(
                array('measureID' => 1, 'jsonKey'=>'VALUE', 'measureName' => 'Value ('.$this->currencySign.')'),
    			array('measureID' => 2, 'jsonKey'=>'VOLUME', 'measureName' => 'Volume (Units)'),
    			array('measureID' => 3, 'jsonKey'=>'CASES', 'measureName' => 'Volume (Cases)'),
            );
            
            if($this->projectTypeID == 6) // DDB
            {
                $this->pageArray["MEASURE_SELECTION_LIST"] = array(
                    array('measureID' => 1, 'jsonKey'=>'VALUE', 'measureName' => 'VALUE', 'selected' => true),
                    array('measureID' => 2, 'jsonKey'=>'VOLUME', 'measureName' => 'VOLUME (Units)', 'selected' => true),
                    array('measureID' => 3, 'jsonKey'=>'CASES', 'measureName' => 'VOLUME (Cases)', 'selected' => true )
                );
            }
        
            $this->measureArray['M3']['VAL'] = "CASES";
            $this->measureArray['M3']['ALIASE'] = "CASES";
            $this->measureArray['M3']['attr'] = "SUM";
            unset($this->measureArray['M3']['dataDecimalPlaces']);

            $this->measureArray['M4']['VAL'] = "(SUM(IFNULL($this->ProjectValue,0))/SUM(IFNULL($this->ProjectVolume,1)))";
            $this->measureArray['M4']['ALIASE'] = "PRICE";
            $this->measureArray['M4']['attr'] = "";
            $this->measureArray['M4']['dataDecimalPlaces'] = 2;

            $this->measureArray['M5']['VAL'] = "DISTINCT((CASE WHEN ".$this->ProjectValue." > 0 THEN ".$this->maintable.".SNO END))";
            $this->measureArray['M5']['ALIASE'] = "DISTRIBUTION";
            $this->measureArray['M5']['attr'] = "COUNT";
            $this->measureArray['M5']['dataDecimalPlaces'] = 0;

            $this->performanceTabMappings["priceOverTime"] = 'M4';
            $this->performanceTabMappings["distributionOverTime"] = 'M5';
        }
		
        $this->weekperiod = "$this->timetable.week";
        $this->yearperiod = "$this->timetable.year";
		
		if (!$this->isDynamicPage)
		{
			$this->dataArray['WEEK']['NAME'] = $this->weekperiod;
			$this->dataArray['WEEK']['NAME_ALIASE'] = 'WEEK';

			$this->dataArray['YEAR']['NAME'] = $this->yearperiod;
			$this->dataArray['YEAR']['NAME_ALIASE'] = 'YEAR';
		}
       
    }
}
?>