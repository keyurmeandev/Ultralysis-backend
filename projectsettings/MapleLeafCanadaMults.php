<?php

namespace projectsettings;

class MapleLeafCanadaMults extends BaseLcl {

    public function __construct($aid, $projectID, $gid) {
        $this->maintable = "mapleleaf_canada_mults";

        if($this->hasTerritory($aid, $projectID))
            $this->territorytable = "territory";
            
        $this->clientID = "MAPLELEAF";
        $this->aid = $aid;
        $this->projectID = $projectID;

        parent::__construct($gid);

        $this->configureClassVars();
    }

}

?>