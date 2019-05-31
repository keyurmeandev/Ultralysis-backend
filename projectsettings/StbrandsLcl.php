<?php

namespace projectsettings;

class StbrandsLcl extends BaseLcl {

    public function __construct($aid, $projectID) {
        $this->maintable = "stbrands_mults";

        if($this->hasTerritory($aid, $projectID))
            $this->territorytable = "territory";

        $this->clientID = "STBRANDS";
        $this->aid = $aid;
        $this->projectID = $projectID;

        parent::__construct();
		
        $this->configureClassVars();
    }

}

?>