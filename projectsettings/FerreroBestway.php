<?php

namespace projectsettings;

class FerreroBestway extends BaseLcl {

    public function __construct($aid, $projectID) {
        $this->maintable = "ferrero_bestway";

        if($this->hasTerritory($aid, $projectID))
            $this->territorytable = "territory";

        
        $this->clientID = "FERRERO";
        $this->aid = $aid; // 52
        $this->projectID = $projectID;

        parent::__construct(array(12));		
        
        $this->clustertable = "";
        if(!$this->hasMeasureFilter){
    		// measure selection list
            $this->pageArray["MEASURE_SELECTION_LIST"] = array(
                array('measureID' => 1, 'jsonKey'=>'VALUE', 'measureName' => 'Value ('.$this->currencySign.')'),
                array('measureID' => 2, 'jsonKey'=>'VOLUME', 'measureName' => 'Volume (Units)'),
                array('measureID' => 6, 'jsonKey'=>'CASES', 'measureName' => 'Volume (Cases)')
            );
            
            if($this->projectTypeID == 6) // DDB
            {
                $this->pageArray["MEASURE_SELECTION_LIST"] = array(
                    array('measureID' => 1, 'jsonKey'=>'VALUE', 'measureName' => 'VALUE', 'selected' => true),
                    array('measureID' => 2, 'jsonKey'=>'VOLUME', 'measureName' => 'VOLUME (Units)', 'selected' => true),
                    array('measureID' => 6, 'jsonKey'=>'CASES', 'measureName' => 'VOLUME (Cases)', 'selected' => true )
                );
            }
        }

        $this->configureClassVars();

        if(!$this->hasMeasureFilter){
            $this->measureArray['M6']['VAL'] = "CASES";
            $this->measureArray['M6']['ALIASE'] = "CASES";
            $this->measureArray['M6']['attr'] = "SUM";
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