<?php

namespace projectsettings;

class CatelliLcl extends BaseLcl {

    public function __construct($aid, $projectID) {
        $this->maintable = "catelli_mults";

        if($this->hasTerritory($aid, $projectID))
            $this->territorytable = "territory";

        
        $this->masterplanotable = "master_plano";
        $this->clientID = "CATELLI";
        $this->aid = $aid;
        $this->projectID = $projectID;

        parent::__construct();
        // $this->clustertable = "rubicon_custom_product_group";
        $this->dataArray['F17']['NAME'] = 'level'.$this->territoryLevel;
        $this->dataArray['F17']['NAME_ALIASE'] = 'TERRITORY';
        $this->dataArray['F17']['tablename'] = $this->territorytable;
        $this->dataArray['F17']['link'] = "WHERE 1";
        $this->dataArray['F17']['use_alias_as_tag'] = true;

        // $this->dataArray['F18']['NAME'] = 'CLUSTER';
        // $this->dataArray['F18']['NAME_ALIASE'] = 'CLUSTER';
        // $this->dataArray['F18']['tablename'] = $this->clustertable;
        // $this->dataArray['F18']['link'] = "WHERE 1";

        if(!$this->hasMeasureFilter){
            if($this->projectTypeID == 6) // DDB
            {
                $this->pageArray["MEASURE_SELECTION_LIST"] = array(
                    array('measureID' => 1, 'jsonKey'=>'VALUE', 'measureName' => 'VALUE ('.$this->currencySign.')', 'selected' => true ),
                    array('measureID' => 2, 'jsonKey'=>'VOLUME', 'measureName' => 'VOLUME', 'selected' => true )
                );
            }
        }
        
        $this->configureClassVars();
    }

}

?>