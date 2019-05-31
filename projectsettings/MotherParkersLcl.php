<?php

namespace projectsettings;

class MotherParkersLcl extends BaseLcl {

    public function __construct($aid, $projectID) {
        $this->maintable = "mp_mults";
        $this->territorytable = "";
        $this->clustertable = "";
        $this->clientID = "MP";
        $this->aid = $aid; 
        $this->projectID = $projectID;

        parent::__construct();

        $this->dataArray['F17']['NAME'] = 'BANNER';
        $this->dataArray['F17']['NAME_ALIASE'] = 'BANNER (RAW)';
        $this->dataArray['F17']['tablename'] = $this->geoHelperTables;
        $this->dataArray['F17']['link'] = $this->geoHelperLink;

        $this->dataArray['F18']['NAME'] = 'CLUSTER';
        $this->dataArray['F18']['NAME_ALIASE'] = 'CLUSTER';
        $this->dataArray['F18']['tablename'] = $this->clustertable;
        $this->dataArray['F18']['link'] = "WHERE 1";

        $this->configureClassVars();
    }

}

?>