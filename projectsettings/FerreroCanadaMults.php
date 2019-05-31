<?php

namespace projectsettings;

class FerreroCanadaMults extends BaseLcl {

    public function __construct($aid, $projectID, $gid) {
        $this->maintable = "ferrero_canada_mults";

        if($this->hasTerritory($aid, $projectID))
            $this->territorytable = "territory";
            
        $this->clientID = "FCANADA";
        $this->aid = $aid;
        $this->projectID = $projectID;
        $this->GID = implode(",",$gids);

        parent::__construct($gid);

        if ((isset($_REQUEST['YtdOn']) && $_REQUEST['YtdOn'] == 'ACCOUNT_YEAR')) {
            //We don't need to overwrite this conditions because it already loaded form the BASELCL CLASS
        }else{
            $this->weekperiod = "$this->timetable.week";
            $this->yearperiod = "$this->timetable.year";
        }

        $this->configureClassVars();
    }


    public function configureClassVars() {

        $this->dateField = $this->maintable . "." . $this->dateperiod;
        $this->copy_tablename = $this->tablename = " " . $this->maintable . "," . $this->timetable . "," . $this->skutable . "," . $this->storetable. "," . $this->grouptable;
        
        if (!empty($this->territorytable)) {
            $this->copy_tablename .= "," . $this->territorytable . " ";
            $this->tablename .= "," . $this->territorytable . " ";
        }

        $commontables   = $this->maintable . "," . $this->timetable. "," . $this->grouptable;
        $commonlink     = " WHERE $this->maintable.accountID=$this->aid " .
                        "AND $this->maintable.mydate=$this->timetable.mydate " .
                        "AND $this->maintable.GID=$this->timetable.GID " .
                        "AND $this->maintable.GID=$this->grouptable.gid " .
                        "AND $this->timetable.gid IN (".$this->GID.") ".
                        "AND $this->maintable.gid IN (".$this->GID.") ";

        $storelink      = " AND $this->maintable.SNO=$this->storetable.SNO " .
                        "AND $this->maintable.GID=$this->storetable.GID " .
                        "AND $this->storetable.gid IN (".$this->GID.") ";
                        
        $territorylink  = ((!empty($this->territorytable)) ? " AND $this->storetable.SNO=$this->territorytable.SNO 
                        AND $this->storetable.GID=$this->territorytable.GID 
                        AND $this->territorytable.GID IN ($this->GID) AND $this->territorytable.accountID = $this->aid " : "");
                        
        $skulink        = "AND $this->maintable.PIN=$this->skutable.PIN " .
                        "AND $this->maintable.GID=$this->skutable.GID " .
                        "AND $this->skutable.hide<>1 " .
                        "AND $this->skutable.gid IN (".$this->GID.") ".
                        "AND $this->skutable.clientID='$this->clientID' ";

        $this->copy_link     = $this->link     = $commonlink.$storelink.$territorylink.$skulink;
        

        /*$this->timeHelperTables = " " . $this->timetable . "," . $this->maintable . " ";
        $this->timeHelperLink = " WHERE " . $this->maintable . "." . $this->dateperiod . "=" . 
                                $this->timetable . "." . $this->dateperiod . " AND ".
                                $this->timetable.".GID IN (".$this->GID.") ";*/

        $this->timeHelperTables = $this->timetable;
        $this->timeHelperLink = " WHERE " . $this->timetable.".GID IN (".$this->GID.") ".
                                " AND ". $this->timetable . "." . $this->dateperiod . 
                                " IN ( SELECT DISTINCT " . $this->maintable . "." . $this->dateperiod . 
                                    " FROM " . $this->maintable . " WHERE ".$this->maintable.".GID IN (".$this->GID.") ) ";

        if($this->hasHiddenSku()) {
            //$commontables .= ", ".$this->skutable;
            //$commonlink   .= $skulink;
            //$skulink       = '';
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


        /** ******************************************** USED ONLY FOR CALENDAR PAGE *********************** */
        $this->calendarItems = array();
        array_push($this->calendarItems, array(
            'DATA' => $this->maintable . "." . $this->dateperiod,
            'ALIASE' => "MYDATE"
                )
        );
        array_push($this->calendarItems, array(
            'DATA' => $this->timetable.'.week',
            'ALIASE' => "WEEK"
                )
        );
        array_push($this->calendarItems, array(
            'DATA' => $this->timetable.'.year',
            'ALIASE' => "YEAR"
                )
        );
        array_push($this->calendarItems, array(
            'DATA' => "accountweek",
            'ALIASE' => "ACCOUNTWEEK"
                )
        );
        array_push($this->calendarItems, array(
            'DATA' => "accountyear",
            'ALIASE' => "ACCOUNTYEAR"
                )
        );

        $this->tablename_for_calendar = $this->maintable . "," . $this->timetable;
        $this->where_clause_for_calendar = $this->maintable . "." . $this->dateperiod . "=" . $this->timetable . "." . $this->dateperiod . " ";
        /** ****************************************************************************************************************** */

        if($this->hasMeasureFilter){
            $this->getMeasureSettings();
        }else{
            $this->measureArray = array();
            $this->measureArray['M1']['VAL'] = $this->ProjectValue;
            $this->measureArray['M1']['ALIASE'] = "VALUE";
            $this->measureArray['M1']['attr'] = "SUM";

            $this->measureArray['M2']['VAL'] = $this->ProjectVolume;
            $this->measureArray['M2']['ALIASE'] = "VOLUME";
            $this->measureArray['M2']['attr'] = "SUM";

            $this->measureArray['M3']['VAL'] = "(SUM(IFNULL($this->ProjectValue,0))/SUM(IFNULL($this->ProjectVolume,1)))";
            $this->measureArray['M3']['ALIASE'] = "PRICE";      
            $this->measureArray['M3']['attr'] = "";
            
            $this->measureArray['M4']['VAL'] = "DISTINCT((CASE WHEN ".$this->ProjectValue." > 0 THEN ".$this->maintable.".SNO END))";
            $this->measureArray['M4']['ALIASE'] = "DISTRIBUTION";
            $this->measureArray['M4']['attr'] = "COUNT";
            
            $this->performanceTabMappings = array(
                "drillDown" => 'M1',
                "overTime" => 'M1',
                "priceOverTime" => 'M3',
                "distributionOverTime" => 'M4'
            );
        }
        
        $this->measureArray_for_productKBI_only = array();
        $this->measureArray_for_productKBI_only['M0']['VAL'] = $this->ProjectVolume;
        $this->measureArray_for_productKBI_only['M0']['ALIASE'] = "VOL";
        $this->measureArray_for_productKBI_only['M0']['attr'] = "SUM";


        $this->measureArray_for_productKBI_only['M1']['VAL'] = $this->ProjectValue;
        $this->measureArray_for_productKBI_only['M1']['ALIASE'] = "VAL";
        $this->measureArray_for_productKBI_only['M1']['attr'] = "SUM";
        
        $this->getClientProjectName();

        //[START] LOAD DDB SETTING
        if($this->projectTypeID == 6) // DDB
            $this->setDynamicDataBuilderSetting();
    }

}

?>