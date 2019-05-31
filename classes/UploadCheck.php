<?php
namespace classes;

use datahelper;
use projectsettings;
use filters;
use utils;
use db;
use config;

class UploadCheck extends config\UlConfig{
    /*****
    * Default gateway function, should be similar in all DATA CLASS
    * arguments:
    *  $settingVars [project settingsGateway variables]
    * @return $xmlOutput with all data that should be sent to client app
    *****/
    public function go($settingVars){
       $this->initiate($settingVars); //INITIATE COMMON VARIABLES

       $this->settingVars->pageName = (empty($this->settingVars->pageName)) ? $this->settingVars->pageID.'_UploadCheckPage' : $this->settingVars->pageName;

       $this->groupsData = array();

        if ($this->settingVars->isDynamicPage) {
            $this->groups = $this->getPageConfiguration('group_settings', $this->settingVars->pageID);
			$this->type   = $this->getPageConfiguration('page_type', $this->settingVars->pageID)[0];
       		$this->buildPageArray();
        }

       	if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true'){
       		$this->fetchConfig();
   		}else{
	   		$this->prepareGridData();
	   	}
    
		return $this->jsonOutput;
    }

    public function fetchConfig()
    {
        if($this->settingVars->monthperiod != '')
            $ColumnList = array("YEAR" => "YEAR", "WEEK" => "MONTH");
        else
            $ColumnList = array("YEAR" => "YEAR", "WEEK" => "WEEK");

    	foreach($this->groupsData as $group)
		{
			$ColumnList[str_replace(" ","_",$group['gname'])] = str_replace(" ","_",$group['gname']);
		}
		$this->jsonOutput['ColumnList'] = $ColumnList;
    	$this->jsonOutput['type'] = $this->type;
    }

    public function prepareGridData()
	{
        if($this->settingVars->monthperiod != '')
            $selectPart = $this->settingVars->yearperiod." AS YEAR, ".$this->settingVars->monthperiod." AS WEEK ";
        else
            $selectPart = $this->settingVars->yearperiod." AS YEAR, ".$this->settingVars->weekperiod." AS WEEK ";

		foreach($this->groupsData as $group){
			$selectPart .= ", MAX(CASE WHEN ".$this->settingVars->maintable.".gid = ".$group['gid']." THEN ". (($this->type != "1") ? $this->settingVars->timetable.".".$this->type : $this->type) ." END) AS ".str_replace(" ","_",$group['gname'])." ";
		}
        $this->settingVars->tableUsedForQuery = array();
        $this->settingVars->useRequiredTablesOnly = true;
        $this->queryPart = $this->getAll();
        $query = "SELECT $selectPart ".
                    "FROM ". $this->settingVars->tablename." ". $this->queryPart.
                    " GROUP BY YEAR,WEEK HAVING YEAR>0 AND WEEK>0 ".
                    " ORDER BY YEAR DESC,WEEK DESC";

        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
		$this->jsonOutput['periodData'] = $result;
    }

    public function buildPageArray() {
    	$gid = implode(",", $this->groups);
		$query = "SELECT gid, gname FROM ".$this->settingVars->grouptable." WHERE gid IN (".$gid.")";
		$this->groupsData = $this->queryVars->queryHandler->runQuery($query,$this->queryVars->linkid,db\ResultTypes::$TYPE_OBJECT);

    	return;
    }
}
?>