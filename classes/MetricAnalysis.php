<?php

namespace classes;

use datahelper;
use projectsettings;
use filters;
use utils;
use db;
use config;

class MetricAnalysis extends config\UlConfig {
    /*     * ***
     * Default gateway function, should be similar in all DATA CLASS
     * arguments:
     *  $settingVars [project settingsGateway variables]
     * @return jsonOutput with all data that should be sent to client app
     * *** */

    public function go($settingVars) {
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES
        $this->queryPart = $this->getAll();  //USES OWN getAll FUNCTION
        $this->pageName = $_REQUEST["pageName"];
        $action = $_REQUEST["action"];
        $this->clusterID = $this->settingVars->storetable.'.cl29';

        if ($action == 'fetchGrid')
            $this->prepareGrid();
        else
            $this->fetchConfig();

        return $this->jsonOutput;
    }

    public function fetchConfig()
    {
        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true'){
            $this->jsonOutput['GRID_SETUP'] = array();
        }
        return;
    }
    
    public function prepareGrid() {
        
        $query = "SELECT DISTINCT pname_rollup2,".$this->settingVars->maintable.".gid, ".$this->settingVars->dateField." as MYDATE, ".
            " COUNT((case when ".$this->settingVars->ProjectVolume." > 0 then 1 end) * ".$this->settingVars->maintable.".SNO) as NUM_DIST, ".
            " COUNT((case when ".$this->settingVars->ProjectVolume." > 0 AND ".$this->clusterID."='A' then 1 end) * ".$this->settingVars->maintable.".SNO) as NUM_DIST_A, ".
            " COUNT((case when ".$this->settingVars->ProjectVolume." > 0 AND ".$this->clusterID."='B' then 1 end) * ".$this->settingVars->maintable.".SNO) as NUM_DIST_B, ".
            " COUNT((case when ".$this->settingVars->ProjectVolume." > 0 AND ".$this->clusterID."='C' then 1 end) * ".$this->settingVars->maintable.".SNO) as NUM_DIST_C, ".
            " COUNT((case when ".$this->settingVars->ProjectVolume." > 0 AND ".$this->clusterID."='D' then 1 end) * ".$this->settingVars->maintable.".SNO) as NUM_DIST_D, ".
            " COUNT((case when ".$this->settingVars->ProjectVolume." > 0 AND ".$this->clusterID."='E' then 1 end) * ".$this->settingVars->maintable.".SNO) as NUM_DIST_E, ".
            " SUM(".$this->settingVars->ProjectVolume.") as SALES".
            " FROM ".$this->settingVars->tablename.$this->settingVars->link." LIMIT 10";
        $mainResult = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT); 
        
        $subQuery = "SELECT ".$this->settingVars->maintable.".gid,".$this->settingVars->dateField." as MYDATE,".
            " COUNT(DISTINCT(case when ".$this->settingVars->ProjectVolume." > 0 then 1 end) * ".$this->settingVars->maintable.".SNO) as MAX_DIST, ".
            " COUNT(DISTINCT(case when ".$this->settingVars->ProjectVolume." > 0 AND ".$this->clusterID."='A' then 1 end) * ".$this->settingVars->maintable.".SNO) as MAX_DIST_A, ".
            " COUNT(DISTINCT(case when ".$this->settingVars->ProjectVolume." > 0 AND ".$this->clusterID."='B' then 1 end) * ".$this->settingVars->maintable.".SNO) as MAX_DIST_B, ".
            " COUNT(DISTINCT(case when ".$this->settingVars->ProjectVolume." > 0 AND ".$this->clusterID."='C' then 1 end) * ".$this->settingVars->maintable.".SNO) as MAX_DIST_C, ".
            " COUNT(DISTINCT(case when ".$this->settingVars->ProjectVolume." > 0 AND ".$this->clusterID."='D' then 1 end) * ".$this->settingVars->maintable.".SNO) as MAX_DIST_D, ".
            " COUNT(DISTINCT(case when ".$this->settingVars->ProjectVolume." > 0 AND ".$this->clusterID."='E' then 1 end) * ".$this->settingVars->maintable.".SNO) as MAX_DIST_E ".
            " FROM ".$this->settingVars->tablename.$this->settingVars->link." GROUP BY ".$this->settingVars->maintable.".gid, MYDATE";
        $subResult = $this->queryVars->queryHandler->runQuery($subQuery, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT); 

        $subResultDetail = array();
        if (is_array($subResult) && !empty($subResult)) {
            foreach ($subResult as $subResultData) {
                $subResultDetail[$subResultData['gid']][$subResultData['MYDATE']] = $subResultData;
            }
        }

        if (is_array($mainResult) && !empty($mainResult)) {
            foreach ($mainResult as $mainResultData) {
                $mainResultData['MAX_DIST'] = (isset($subResultDetail[$mainResultData['gid']][$mainResultData['MYDATE']])) ? $subResultDetail[$mainResultData['gid']][$mainResultData['MYDATE']]['MAX_DIST'] : 0;
                $mainResultData['MAX_DIST_A'] = (isset($subResultDetail[$mainResultData['gid']][$mainResultData['MYDATE']])) ? $subResultDetail[$mainResultData['gid']][$mainResultData['MYDATE']]['MAX_DIST_A'] : 0;
                $mainResultData['MAX_DIST_B'] = (isset($subResultDetail[$mainResultData['gid']][$mainResultData['MYDATE']])) ? $subResultDetail[$mainResultData['gid']][$mainResultData['MYDATE']]['MAX_DIST_B'] : 0;
                $mainResultData['MAX_DIST_C'] = (isset($subResultDetail[$mainResultData['gid']][$mainResultData['MYDATE']])) ? $subResultDetail[$mainResultData['gid']][$mainResultData['MYDATE']]['MAX_DIST_C'] : 0;
                $mainResultData['MAX_DIST_D'] = (isset($subResultDetail[$mainResultData['gid']][$mainResultData['MYDATE']])) ? $subResultDetail[$mainResultData['gid']][$mainResultData['MYDATE']]['MAX_DIST_D'] : 0;
                $mainResultData['MAX_DIST_E'] = (isset($subResultDetail[$mainResultData['gid']][$mainResultData['MYDATE']])) ? $subResultDetail[$mainResultData['gid']][$mainResultData['MYDATE']]['MAX_DIST_E'] : 0;
                $mainResultDetail[] = $mainResultData;
            }
        }

        echo "<pre>";
        print_r($mainResultDetail);
        exit();

        $this->jsonOutput['metricAnalysis'] = $mainResultDetail;
    }

    //overriding parent class's getAll function
    /*public function getAll() {
        $tablejoins_and_filters = $this->settingVars->productHelperLink;
		//$tablejoins_and_filters = $this->settingVars->clientID == "" ? "" : " WHERE clientID='" . $this->settingVars->clientID . "'";		
        return $tablejoins_and_filters;
    }*/

}

?>