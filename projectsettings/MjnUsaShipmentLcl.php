<?php

namespace projectsettings;

class MjnUsaShipmentLcl extends BaseLcl {

    public function __construct($aid, $projectID) {
        
        $this->maintable = "mjn_usa_sap_ship";

        if($this->hasTerritory($aid, $projectID))
            $this->territorytable = "territory";
            
        $this->accounttable = "shipments_ship_store";
        $this->clientID = "MJNUSA";
        $this->aid = $aid; // 52
        $this->projectID = $projectID;

        parent::__construct(array(23)); // GID

        $this->includeFutureDates = true;
        
        $this->configureClassVars();

        $commontables   = $this->maintable . ", " . $this->timetable;
        $commonlink     = " WHERE $this->maintable.accountID=$this->aid " .
                        "AND $this->maintable.".$this->dateperiod."=$this->timetable.".$this->dateperiod." " .
                        "AND $this->maintable.GID=$this->timetable.gid " .
                        "AND $this->timetable.gid IN (".$this->GID.") AND $this->maintable.project_type = 'Weekly' ";

        $storelink      = " AND $this->maintable.SNO=$this->storetable.SNO " .
                        "AND $this->maintable.GID=$this->storetable.GID " .
                        "AND $this->storetable.gid IN (".$this->GID.") ";
                        
        $territorylink  = ((!empty($this->territorytable)) ? " AND $this->storetable.SNO=$this->territorytable.SNO 
                        AND $this->storetable.GID=$this->territorytable.GID
                        AND $this->territorytable.GID IN ($this->GID) AND $this->territorytable.accountID = $this->aid " : "");

        $this->accountLink = $accountlink  = " AND $this->maintable.SHIP_STORE=$this->accounttable.SNO " .
                        "AND $this->maintable.GID=$this->accounttable.GID " .
                        "AND $this->accounttable.gid IN (".$this->GID.") ";        
                        
        $skulink        = "AND $this->maintable.PIN=$this->skutable.PIN " .
                        "AND $this->maintable.GID=$this->skutable.GID " .
                        "AND $this->skutable.hide<>1 " .
                        "AND $this->skutable.gid IN (".$this->GID.") ".
                        "AND $this->skutable.clientID='$this->clientID' ";

        $this->copy_link     = $this->link     = $commonlink.$storelink.$territorylink.$skulink.$accountlink;

        $this->timeHelperTables = " " . $this->timetable . "," . $this->maintable . " ";
        $this->timeHelperLink = " WHERE " . $this->maintable . "." . $this->dateperiod . "=" . 
                                $this->timetable . "." . $this->dateperiod . 
                                " AND ".$this->maintable.".GID=".$this->timetable.".gid " .
                                " AND ".$this->timetable.".GID IN (".$this->GID.") AND $this->maintable.project_type = 'Weekly' ";

        if($this->hasHiddenSku()) {
            $commonlink   .= " AND ".$this->maintable.".PIN NOT IN ( ".$this->hiddenSkusQueryString.") ";
        }

        $this->dataTable['default']['tables']      = $commontables;
        $this->dataTable['default']['link']        = $commonlink;

        $this->dataTable['product']['tables']      = $this->skutable;
        $this->dataTable['product']['link']        = $skulink;

        $this->dataTable['store']['tables']        = $this->storetable;
        $this->dataTable['store']['link']          = $storelink;

        $this->dataTable['territory']['tables']    = $this->territorytable;
        $this->dataTable['territory']['link']      = $territorylink;

        $this->dataTable['shipments_ship_store']['tables']  = $this->accounttable;
        $this->dataTable['shipments_ship_store']['link']    = $accountlink;
        
        if($this->projectTypeID == 6) // DDB
        {
            $this->accountHelperTables = $this->accounttable.", ".$this->maintable;
            $this->accountHelperLink = " WHERE $this->maintable.SHIP_STORE=$this->accounttable.SNO " .
                        "AND $this->maintable.GID=$this->accounttable.GID " .
                        "AND $this->accounttable.gid IN (".$this->GID.") AND $this->maintable.project_type = 'Monthly' ";
            
            $this->filterPages[5]['config'] = array( 
                    "table_name" => $this->accounttable, 
                    "helper_table" => $this->accountHelperTables,
                    "setting_name" => "account_settings", 
                    "helper_link" => $this->accountHelperLink,
                    "type" => "A",
                    "enable_setting_name" => "has_account"
                );
                
            if(!$this->hasMeasureFilter){    
                $this->pageArray["MEASURE_SELECTION_LIST"] = array(
                    array('measureID' => 1, 'jsonKey'=>'VALUE', 'measureName' => 'DOLLARS', 'selected'=>true),
                    array('measureID' => 2, 'jsonKey'=>'VOLUME', 'measureName' => 'UNITS', 'selected'=>true)
                );
            }
        }
        
    }

}

?>