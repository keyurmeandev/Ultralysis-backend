<?php

namespace projectsettings;

class GayleaRetailLink extends BaseLcl {

    public function __construct($aid, $projectID) {
        $this->maintable = "gaylea_retail_link";

        if($this->hasTerritory($aid, $projectID))
            $this->territorytable = "territory";

        
        $this->masterplanotable = "master_plano";
        $this->clientID = "GAYLEA";
        $this->aid = $aid;
        $this->projectID = $projectID;
        $this->calculateVsiOhq = true;

        parent::__construct(array(8));

        $this->clustertable = "";
        $this->dateperiod = "period";
        
        $this->dataArray['F17']['NAME'] = 'level'.$this->territoryLevel;
        $this->dataArray['F17']['NAME_ALIASE'] = 'TERRITORY';
        $this->dataArray['F17']['tablename'] = $this->territorytable;
        $this->dataArray['F17']['link'] = "WHERE 1";
        $this->dataArray['F17']['use_alias_as_tag'] = true;

        $this->dataArray['F18']['NAME'] = 'CLUSTER';
        $this->dataArray['F18']['NAME_ALIASE'] = 'CLUSTER';
        $this->dataArray['F18']['tablename'] = $this->clustertable;
        $this->dataArray['F18']['link'] = "WHERE 1";

        $this->configureClassVars();
        
        if($this->projectTypeID == 6) // DDB
        {
            if(!$this->hasMeasureFilter){
                $this->pageArray["MEASURE_SELECTION_LIST"] = array(
                    array('measureID' => 1, 'jsonKey'=>'VALUE', 'measureName' => 'VALUE', 'selected'=>true),
                    array('measureID' => 2, 'jsonKey'=>'VOLUME', 'measureName' => 'VOLUME', 'selected'=>true)
                );
            }
            
            $this->dateperiod = "mydate";
            $this->dataArray['MYDATE']['NAME'] = $this->timetable . "." . $this->dateperiod;
            $this->dataArray['MYDATE']['NAME_ALIASE'] = 'MYDATE';
            $this->dataArray['MYDATE']['TYPE'] = "T";
        }
    }

}

?>