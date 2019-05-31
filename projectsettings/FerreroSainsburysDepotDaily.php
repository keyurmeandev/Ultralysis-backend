<?php
namespace projectsettings;

use projectsettings;
use db;
use utils;

class FerreroSainsburysDepotDaily extends BaseLcl {

    public function __construct($accountID,$projectID) {
	
        $this->aid                  = $accountID;
        $this->projectID            = $projectID;        
    
        $this->maintable            = "ferrero_sainsburys_depot_daily";
        $this->accounttable         = "depot";
        $this->clientID             = "FERRERO";
        $gids                       = array(6);
        
        $this->includeDateInTimeFilter  = false;
        
        parent::__construct($gids);

        $this->timeSelectionUnit = "date";
        $this->measureTypeField = $this->maintable . ".metric";
        $this->timetable        = $this->maintable;
        $this->yearperiod       = "";
        $this->weekperiod       = "";
        
		$this->configureClassVars();
        
        if(!$this->hasMeasureFilter)
        {
            $this->pageArray["MEASURE_SELECTION_LIST"] = array(
                array('measureID' => 1, 'jsonKey'=>'TOTALCASE', 'measureName' => 'Total Cases', 'selected' => true),
                array('measureID' => 2, 'jsonKey'=>'CASESAVAILABLE', 'measureName' => 'Cases Available', 'selected' => true),
            );

            $this->measureArray = array();
            
            $this->measureArray['M1']['VAL']    = $this->ProjectValue;
            $this->measureArray['M1']['ALIASE'] = "TOTALCASE";
            $this->measureArray['M1']['attr']   = "SUM";
            $this->measureArray['M1']['CASE_WHEN']   = " AND ".$this->measureTypeField." = 'Total Cases' ";
            
            $this->measureArray['M2']['VAL']    = $this->ProjectValue;
            $this->measureArray['M2']['ALIASE'] = "CASESAVAILABLE";
            $this->measureArray['M2']['attr']   = "SUM";
            $this->measureArray['M2']['CASE_WHEN']   = " AND ".$this->measureTypeField." = 'Cases Available' ";
        }
        
        $commontables   = $this->maintable . ", ".$this->grouptable;
        $commonlink     = " WHERE $this->maintable.accountID=$this->aid " .
                        "AND $this->maintable.GID=$this->grouptable.gid " .
                        "AND $this->maintable.GID IN (".$this->GID.") ";

        $accountlink  = " AND $this->maintable.depot_id=$this->accounttable.depot_no " .
                        "AND $this->maintable.GID=$this->accounttable.GID " .
                        "AND $this->accounttable.gid IN (".$this->GID.") ";
                        
        $skulink        = "AND $this->maintable.pin=$this->skutable.PIN " .
                        "AND $this->maintable.GID=$this->skutable.GID " .
                        "AND $this->skutable.hide<>1 " .
                        "AND $this->skutable.clientID = '".$this->clientID."' " .
                        "AND $this->skutable.gid IN (".$this->GID.") ";

        $this->copy_link = $this->link = $commonlink.$territorylink.$skulink.$accountlink;

        $this->timeHelperTables = " " . $this->maintable . " ";
        $this->timeHelperLink = " WHERE ".$this->maintable.".GID IN (".$this->GID.") ";

        unset($this->dataTable['store']);
        
        $this->dataTable['default']['tables']               = $commontables;
        $this->dataTable['default']['link']                 = $commonlink;

        $this->dataTable['product']['tables']               = $this->skutable;
        $this->dataTable['product']['link']                 = $skulink;

        $this->dataTable[$this->accounttable]['tables']     = $this->accounttable;
        $this->dataTable[$this->accounttable]['link']       = $accountlink;
        
        if($this->projectTypeID == 6) // DDB
        {
            $this->outputDateOptions = array(
                array("id" => "MYDATE", "value" => "My Date", "selected" => true)
            );
            
            $this->accountHelperTables = $this->accounttable.", ".$this->maintable;
            $this->accountHelperLink = " WHERE $this->maintable.depot_id=$this->accounttable.depot_no " .
                        "AND $this->maintable.GID=$this->accounttable.GID " .
                        "AND $this->accounttable.gid IN (".$this->GID.") ";
            
            $this->filterPages[5]['config'] = array(
                        "table_name" => $this->accounttable, 
                        "helper_table" => $this->accountHelperTables, 
                        "setting_name" => "account_settings", 
                        "helper_link" => $this->accountHelperLink,
                        "type" => "A", 
                        "enable_setting_name" => "has_account"
                    );
        }
    }

}
?>