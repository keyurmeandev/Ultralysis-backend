<?php

namespace projectsettings;

class MultySalesTracker extends BaseLcl {

    public function __construct($aid, $projectID, $GID) {
        $this->maintable = "multy_sales_tracker";

        if($this->hasTerritory($aid, $projectID))
            $this->territorytable = "territory";

        $this->clientID = "MULTY";
        $this->aid = $aid; 
        $this->projectID = $projectID;

        parent::__construct($GID);

        $this->timetable  = $this->maintable;
        $this->monthperiod = $this->weekperiod = "$this->maintable.month";
        $this->yearperiod = "$this->maintable.year";
        
        $this->timeSelectionUnit = "weekMonth";

        $this->dataArray['F17']['NAME'] = 'level'.$this->territoryLevel;
        $this->dataArray['F17']['NAME_ALIASE'] = 'TERRITORY';
        $this->dataArray['F17']['tablename'] = $this->territorytable;
        $this->dataArray['F17']['link'] = "WHERE 1";
        $this->dataArray['F17']['use_alias_as_tag'] = true;
		
		$this->territoryHelperTables 	= $this->maintable.",".$this->storetable.",".$this->skutable.",".$this->territorytable;
        $this->territoryHelperLink 		= " WHERE ".$this->maintable.".accountID=".$this->aid." AND ".$this->maintable.".SNO=".$this->storetable.".SNO".
										" AND ".$this->maintable.".PIN=".$this->skutable.".PIN".
										" AND ".$this->storetable.".SNO=".$this->territorytable.".SNO".
										" AND ".$this->skutable.".hide<>1".
                                        " AND ".$this->skutable.".gid IN (".$this->GID.")".
                                        " AND ".$this->storetable.".gid IN (".$this->GID.")".
                                        " AND ".$this->territorytable.".GID IN (".$this->GID.") AND ".$this->territorytable.".accountID=".$this->aid.
										" AND ".$this->skutable.".clientID='".$this->clientID."' ";

        if(!$this->hasMeasureFilter){                                        
            if($this->projectTypeID == 6) // DDB
            {
                $this->pageArray["MEASURE_SELECTION_LIST"] = array(
                    array('measureID' => 1, 'jsonKey'=>'VALUE', 'measureName' => 'VALUE ('.$this->currencySign.')', 'selected' => true ),
                    array('measureID' => 2, 'jsonKey'=>'VOLUME', 'measureName' => 'VOLUME', 'selected' => true ),
                    array('measureID' => 3, 'jsonKey'=>'PRICE', 'measureName' => 'AVE PRICE', 'selected' => false ),
                    array('measureID' => 4, 'jsonKey'=>'DISTRIBUTION', 'measureName' => 'STORES SELLING', 'selected' => false )
                );
            }
        }

        $this->configureClassVars();

        $this->dateperiod = "";

        $this->pageArray["PerformanceBoxSettings"] = array(
            'LW_PW_1' => array(
                    'title' => 'LATEST MONTH vs LY',
                    'timeFrame' => 1,
                    'TY' => 'LW1',
                    'LY' => 'PW1',
                    'rank' => 0,
                ),
            'LW_PW' => array(
                    'title' => 'LATEST MONTH vs PREV. MONTH',
                    'timeFrame' => 0,
                    'TY' => 'LW',
                    'LY' => 'PW',
                    'rank' => 1,
                ),
            'LW_PW_4' => array(
                    'title' => 'LAST 3 MONTHS vs LY',
                    'timeFrame' => 3,
                    'TY' => 'LW3',
                    'LY' => 'PW3',
                    'rank' => 2,
                ),
            'LW_PW_13' => array(
                    'title' => 'LAST 6 MONTHS vs LY',
                    'timeFrame' => 6,
                    'TY' => 'LW6',
                    'LY' => 'PW6',
                    'rank' => 3,
                ),
            'LW_PW_52' => array(
                    'title' => 'LAST 12 MONTHS vs LY',
                    'timeFrame' => 12,
                    'TY' => 'LW12',
                    'LY' => 'PW12',
                    'rank' => 4,
                ),
            'YTD' => array(
                    'title' => 'YTD vs LY',
                    'timeFrame' => 'YTD',
                    'TY' => 'YTD_TY',
                    'LY' => 'YTD_LY',
                    'rank' => 5,
                ),
        );
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

        $accountlink  = ((!empty($this->accounttable) && $this->accounttable != $this->maintable) ? $this->accountLink : "");
                        
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

        $this->dataTable['default']['tables']      = $commontables;
        $this->dataTable['default']['link']        = $commonlink;
    }

    /*public function getMydateSelect($dateField, $withAggregate = true) {
        $dateFieldPart = explode('.', $dateField);
        $dateField = (count($dateFieldPart) > 1) ? $dateFieldPart[1] : $dateFieldPart[0];
        
        switch ($dateField) {
            case "period":
                $selectField = ($withAggregate) ? "MAX(".$this->maintable.".month) " : $this->maintable.".month ";
                break;
            case "mydate":
                $selectField = ($withAggregate) ? "MAX(".$this->maintable.".month) " : $this->maintable.".month ";
                break;
        }
        
        return $selectField;
    }*/

}

?>