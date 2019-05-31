<?php

namespace projectsettings;

class MultyShipments extends BaseLcl {

    public function __construct($aid, $projectID, $gid) {
        $this->maintable = "multy_shipments";

        if($this->hasTerritory($aid, $projectID))
            $this->territorytable = "territory";
            
        $this->clientID = "MULTY";
        $this->aid = $aid;
        $this->projectID = $projectID;

        parent::__construct($gid);

        $this->timetable  = $this->maintable;
        $this->yearField            = "year";
        $this->weekField            = "month";
        $this->monthperiod = $this->weekperiod = "$this->maintable.month";
        $this->yearperiod           = "$this->maintable.year";
        $this->timeSelectionUnit    = "weekMonth";

        $this->configureClassVars();
    }

    public function configureClassVars()
    {
        parent::configureClassVars();

        $this->dateField = "";

        $commontables   = $this->maintable;
        $commonlink     = " WHERE $this->maintable.accountID=$this->aid " .
                        "AND $this->maintable.gid IN (".$this->GID.") ";

        $storelink      = " AND $this->maintable.SNO=$this->storetable.SNO " .
                        "AND $this->maintable.GID=$this->storetable.GID " .
                        "AND $this->storetable.gid IN (".$this->GID.") ";
                        
        $territorylink  = ((!empty($this->territorytable)) ? " AND $this->storetable.SNO=$this->territorytable.SNO 
                        AND $this->storetable.GID=$this->territorytable.GID
                        AND $this->territorytable.GID IN ($this->GID) AND $this->territorytable.accountID = $this->aid " : "");

        $accountlink  = ((!empty($this->accounttable) && $this->accounttable != $this->maintable) ? $this->accountHelperLink : "");
                        
        $skulink        = "AND $this->maintable.PIN=$this->skutable.PIN " .
                        "AND $this->maintable.GID=$this->skutable.GID " .
                        "AND $this->skutable.hide<>1 " .
                        "AND $this->skutable.gid IN (".$this->GID.") ".
                        "AND $this->skutable.clientID='$this->clientID' ";

        $this->copy_link     = $this->link     = $commonlink.$storelink.$territorylink.$skulink.$accountlink;

        $this->timeHelperTables = $this->maintable;
        $this->timeHelperLink = " WHERE $this->maintable.accountID=$this->aid AND $this->maintable.gid IN (".$this->GID.") ";

        if($this->hasHiddenSku()) {
            $commonlink   .= " AND ".$this->maintable.".PIN NOT IN ( ".$this->hiddenSkusQueryString.") ";
        }

        $this->dataTable[$this->accounttable]['tables']      = $this->accounttable;
        $this->dataTable[$this->accounttable]['link']        = $accountlink;

        $this->dataTable['default']['tables']      = $commontables;
        $this->dataTable['default']['link']        = $commonlink;
    }

}

?>