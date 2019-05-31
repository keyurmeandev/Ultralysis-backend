<?php

namespace projectsettings;

class MjnUsaPosBru extends BaseLcl {
    public function __construct($aid, $projectID, $gids) {
        $this->maintable = "mjn_usa_mults";

        if($this->hasTerritory($aid, $projectID))
            $this->territorytable = "territory";
            
        $this->clientID = "MJNUSA";
        $this->aid = $aid;
        $this->GID = implode(",",$gids);
        $this->projectID = $projectID;

        parent::__construct($gids);

        $this->configureClassVars();
    }
}
?>