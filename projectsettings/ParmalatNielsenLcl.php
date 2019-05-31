<?php

namespace projectsettings;

class ParmalatNielsenLcl extends BaseLcl {

    public function __construct($aid, $projectID) {
        $this->maintable = "parmalat_nielsen";

        if($this->hasTerritory($aid, $projectID))
            $this->territorytable = "territory";
            
        $this->clientID = "PARMALAT";
        $this->aid = $aid;
        $this->projectID = $projectID;

        parent::__construct(array(11));
        
        $this->weekField = "$this->timetable.week";
        $this->yearField = "$this->timetable.year";
        $this->periodField = "$this->timetable.period";
        $this->timeSelectionUnit = "period";        
        
        //$this->includeFutureDates = true;
        
        if(!$this->hasMeasureFilter){
            // measure selection list
            $this->pageArray["MEASURE_SELECTION_LIST"] = array(
                array('measureID' => 1, 'jsonKey'=>'VALUE', 'measureName' => 'Value ('.$this->currencySign.')', 'selected' => true ),
                array('measureID' => 2, 'jsonKey'=>'VOLUME', 'measureName' => 'Volume', 'selected' => false )
            );
        }
        
        if($this->projectTypeID == 6) // DDB
        {
            if(!$this->hasMeasureFilter){
                $this->pageArray["MEASURE_SELECTION_LIST"] = array(
                    array('measureID' => 1, 'jsonKey'=>'VALUE', 'measureName' => 'VALUE', 'selected' => true ),
                    array('measureID' => 2, 'jsonKey'=>'VOLUME', 'measureName' => 'VOLUME', 'selected' => true )
                );
            }
            
            $this->dataArray['PERIOD']['NAME'] = $this->periodField;
            $this->dataArray['PERIOD']['NAME_ALIASE'] = 'PERIOD';
            $this->dataArray['PERIOD']['TYPE'] = "T";
            
            $this->outputDateOptions = array(
                array("id" => "PERIOD", "value" => "Period", "selected" => false),
                array("id" => "YEAR", "value" => "Year", "selected" => false),
                array("id" => "WEEK", "value" => "Week", "selected" => false)
            );
        }
        
        $this->configureClassVars();
    }

}

?>
