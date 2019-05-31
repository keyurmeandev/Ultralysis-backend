<?php

namespace projectsettings;

class ViledaCanadaMults extends BaseLcl {

    public function __construct($aid, $projectID, $gid) {
        $this->maintable = "vileda_canada_mults";

        if($this->hasTerritory($aid, $projectID))
            $this->territorytable = "territory";
            
        $this->clientID = "VILEDA";
        $this->aid = $aid;
        $this->projectID = $projectID;

        parent::__construct($gid);

        $this->configureClassVars();
    }

}

?>