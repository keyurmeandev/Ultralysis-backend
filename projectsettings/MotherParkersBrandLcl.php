<?php

namespace projectsettings;

class MotherParkersBrandLcl extends BaseLcl {

    public function __construct($aid, $projectID) {
        $this->maintable = "mp_mults";
        $this->territorytable = "";
        
        $this->masterplanotable = "master_plano";
        $this->clientID = "MP_BRAND";
        $this->aid = $aid; 
        $this->projectID = $projectID;

        parent::__construct();
        $this->clustertable = "";
        $this->dataArray['F17']['NAME'] = 'BANNER';
        $this->dataArray['F17']['NAME_ALIASE'] = 'BANNER (RAW)';
        $this->dataArray['F17']['tablename'] = $this->geoHelperTables;
        $this->dataArray['F17']['link'] = $this->geoHelperLink;

        $this->dataArray['F18']['NAME'] = 'CLUSTER';
        $this->dataArray['F18']['NAME_ALIASE'] = 'CLUSTER';
        $this->dataArray['F18']['tablename'] = $this->clustertable;
        $this->dataArray['F18']['link'] = "WHERE 1";

        $this->dataArray['F32']['ID'] = $this->masterplanotable .'.PIN';
        $this->dataArray['F32']['ID_ALIASE'] = 'PIN_A';
        $this->dataArray['F32']['NAME'] = 'PNAME';
        $this->dataArray['F32']['NAME_ALIASE'] = 'PNAME_A';

        $this->dataArray['F33']['ID'] = $this->maintable .'.PIN';
        $this->dataArray['F33']['ID_ALIASE'] = 'PIN_A';
        $this->dataArray['F33']['NAME'] = 'PNAME';
        $this->dataArray['F33']['NAME_ALIASE'] = 'PNAME_A'; 

        $this->pageArray["CONSOLIDATED_DISTRIBUTION"]["planoStoresAccounts"] = "F32-F11-F15";
        $this->pageArray["CONSOLIDATED_DISTRIBUTION"]["planoSellingAccounts"] = "F33-F11-F15";  

        $this->territoryHelperTables    = $this->maintable.",".$this->storetable.",".$this->skutable.",".$this->territorytable;
        $this->territoryHelperLink      = " WHERE ".$this->maintable.".accountID=".$this->aid." AND ".$this->maintable.".SNO=".$this->storetable.".SNO".
                                        " AND ".$this->maintable.".PIN=".$this->skutable.".PIN".
                                        " AND ".$this->storetable.".SNO=".$this->territorytable.".SNO".
                                        " AND ".$this->skutable.".hide<>1".
                                        " AND ".$this->skutable.".gid IN (".$this->GID.")".
                                        " AND ".$this->storetable.".gid IN (".$this->GID.")".
                                        " AND ".$this->territorytable.".GID IN (".$this->GID.") AND ".$this->territorytable.".accountID=".$this->aid.
                                        " AND ".$this->skutable.".clientID='".$this->clientID."' ";

        $this->configureClassVars();

        
        $commonlink     = " WHERE $this->maintable.accountID=$this->aid " .
                        "AND $this->maintable.mydate=$this->timetable.mydate " .
                        "AND $this->maintable.GID=$this->timetable.gid " .
                        "AND $this->timetable.gid IN (".$this->GID.") ";

        $commonlink     .= "AND $this->maintable.PIN=$this->skutable.PIN " .
                        "AND $this->maintable.GID=$this->skutable.GID " .
                        "AND $this->skutable.hide<>1 " .
                        "AND $this->skutable.gid IN (".$this->GID.") ".
                        "AND $this->skutable.clientID='$this->clientID' ";

        $storelink      = " AND $this->maintable.SNO=$this->storetable.SNO " .
                        "AND $this->maintable.GID=$this->storetable.GID " .
                        "AND $this->storetable.gid IN (".$this->GID.") ";
                        
        $territorylink  = ((!empty($this->territorytable)) ? " AND $this->storetable.SNO=$this->territorytable.SNO 
                        AND $this->storetable.GID=$this->territorytable.GID
                        AND $this->territorytable.GID IN ($this->GID) AND $this->territorytable.accountID = $this->aid " : "");

        if($this->hasHiddenSku()) {
            $commonlink   .= " AND ".$this->maintable.".PIN NOT IN ( ".$this->hiddenSkusQueryString.") ";
        }

        $this->copy_link     = $this->link     = $commonlink.$storelink.$territorylink;

        $this->dataTable['default']['tables']      = $this->maintable . "," . $this->timetable . "," . $this->skutable;
        $this->dataTable['default']['link']        = $commonlink;

        $this->dataTable['product']['tables']      = "";
        $this->dataTable['product']['link']        = "";

        $this->dataTable['store']['tables']        = $this->storetable;
        $this->dataTable['store']['link']          = $storelink;

        $this->dataTable['territory']['tables']    = $this->territorytable;
        $this->dataTable['territory']['link']      = $territorylink;
        
        if($this->projectTypeID == 6) // DDB
        {
            if(!$this->hasMeasureFilter){
                $this->pageArray["MEASURE_SELECTION_LIST"] = array(
                    array('measureID' => 1, 'jsonKey'=>'VALUE', 'measureName' => 'VALUE', 'selected' => true),
                    array('measureID' => 2, 'jsonKey'=>'VOLUME', 'measureName' => 'VOLUME', 'selected' => true),
                    array('measureID' => 3, 'jsonKey'=>'PRICE', 'measureName' => 'AVE PRICE', 'selected' => false),
                    array('measureID' => 4, 'jsonKey'=>'DISTRIBUTION', 'measureName' => 'STORES SELLING', 'selected' => false)
                );
            }
        }
        
    }

}

?>