<?php

namespace classes;

use db;
use config;
use filters;
use datahelper;
use utils;
use lib;

class ExternalLinkPage extends config\UlConfig {
    /*     * ***
     * Default gateway function, should be similar in all DATA CLASS
     * arguments:
     *  $settingVars [project settingsGateway variables]
     * @return $xmlOutput with all data that should be sent to client app
     * *** */

    public function go($settingVars) {
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES
        $action = $_REQUEST["action"];

        switch ($action) {
            case "ExternalLinkForDDB":
                $this->checkPoint();
                break;
        }

        return $this->jsonOutput;
    }
    
    private function checkPoint()
	{
		if(isset($_REQUEST['projectID']) && $_REQUEST['projectID'] != "" && $_SESSION["accountID"] != "" && $_SESSION["account"] != "")
		{
            $ddbProject = $this->getPageConfiguration('ddb_project', $this->settingVars->pageID)[0];
            $ExternalLink = new lib\ExternalLink($ddbProject);
            $ExternalLink = $ExternalLink->verifyExternalLink();
            $this->jsonOutput['ExternalLink'] = $ExternalLink;
		}
		else
		{
            exit(json_encode(array('access' => 'unothorized')));
		}
	}
    
}

?>
