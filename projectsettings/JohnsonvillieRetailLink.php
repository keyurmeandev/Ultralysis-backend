<?php

namespace projectsettings;

class JohnsonvillieRetailLink extends BaseLcl {

    public function __construct($aid, $projectID) {
        $this->maintable = "johnsonville_retail_link";

        if($this->hasTerritory($aid, $projectID))
            $this->territorytable = "territory";

        
        $this->masterplanotable = "master_plano";
        $this->clientID = "JV";
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
    }

}

?>