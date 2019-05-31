<?php

namespace projectsettings;

class FerreroNielsenLcl extends BaseLcl {

    public function __construct($aid, $projectID, $gids) {

        $this->maintable = "ferrero_uk_nielsen";
        $this->clientID = "FERRERO";
        $this->aid = $aid;
        $this->GID = implode(",",$gids);
        $this->projectID = $projectID;
		$this->ave_ac_dist = 'AVEACDIST';
        
        parent::__construct($gids);
        $this->configureClassVars();
    }
}
?>