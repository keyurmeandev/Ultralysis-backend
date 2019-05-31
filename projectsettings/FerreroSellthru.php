<?php

namespace projectsettings;

use db;

class FerreroSellthru extends BaseLcl {

    // public function __construct($accountID,$uid,$projectID) {
    public function __construct($aid, $projectID, $gid) {
		
        $this->maintable                    = "ferrero_daily_tracker"; // "volume_sellthru_agreed";
        $this->volumeSellthruTable          = "volume_sellthru";
        $this->volumeSellthruActualsTable   = "volume_sellthru_actuals";

        if($this->hasTerritory($aid, $projectID))
            $this->territorytable = "territory";

        $this->clientID         = "FERRERO";
        $this->accounttable     = "fgroup";
        $this->aid              = $aid;
        $this->projectID        = $projectID;
		
		parent::__construct($gid);

        // $this->getClientAndRetailerLogo();

        /*$this->volSellthruActualsTblPinField = "PIN";
        $this->volSellthruActualsTblDateField = "mydate";
        $this->volSellthruActualsTblQtyField = "qty";*/
        $this->mainTblPinField = "PIN";
        
        // $this->isSifPageRequired = false;
        
        $this->productHelperTables = " " . $this->maintable . "," . $this->skutable . " ";
        $this->productHelperLink = " WHERE " . $this->maintable . ".PIN=" . $this->skutable . ".PIN AND ".$this->skutable.".clientID='" . $this->clientID . "' AND ".$this->skutable.".GID IN ($this->GID) AND ".$this->maintable.".GID = ".$this->skutable.".gid AND hide=0 ";

        $this->accountLink = " AND $this->accounttable.GID IN ($this->GID) AND $this->maintable.GID=$this->accounttable.gid ";
        $this->accountHelperLink = " WHERE ".$this->accounttable.".GID IN (".$this->GID.") ";        
        
		$this->dateperiod     = $this->maintable . ".mydate";
        $this->ProjectValue   = $this->maintable . ".value";
        $this->ProjectVolume  = $this->maintable . ".qty";

        $this->timeSelectionStyle = 'DROPDOWN';
        $this->timetable = $this->maintable;
        $this->weekperiod = "";
        $this->yearperiod = "";
		
		$this->configureClassVars();
            /*
                $this->timetable = $this->volumeSellthruTable;
                $this->timeHelperTables = $this->maintable . "," . $this->volumeSellthruTable;
            */
        $this->timetable = $this->maintable;
        $this->timeHelperTables = $this->maintable;
        $this->dateField = $this->dateperiod;

        $this->copy_tablename = $this->tablename = $this->maintable . "," . $this->skutable;
        
        //$commontables   = $this->maintable . "," . $this->volumeSellthruTable;
        $commontables   = $this->maintable;
        $commonlink     = " WHERE $this->maintable.accountID = '".$this->aid."' " .
                            //" WHERE $this->maintable.clientid = '".$this->clientID."' " .
                            //"AND " . $this->maintable . ".category = " . $this->volumeSellthruTable.".CATEGORY ".
                        "AND ".$this->maintable.".gid IN (".$this->GID.")";

        $skulink        = "AND $this->maintable.PIN=$this->skutable.PIN " .
                        "AND $this->maintable.GID=$this->skutable.GID " .
                        "AND $this->skutable.hide<>1 " .
                        "AND $this->skutable.gid IN (".$this->GID.") ".
                        "AND $this->skutable.clientID='$this->clientID' ";
        
        $this->copy_link     = $this->link     = $commonlink.$skulink;
        
        //$this->timeHelperLink = " WHERE ".$this->maintable.".CLIENTID = '".$this->clientID."' AND " . $this->maintable . ".category = " . $this->volumeSellthruTable.".CATEGORY AND ".$this->maintable.".gid IN (".$this->GID.")";
        $this->timeHelperLink = " WHERE 1=1 ";
        
        if($this->hasHiddenSku()) {
            $commontables .= ", ".$this->skutable;
            $commonlink   .= $skulink;
            $skulink       = '';
        }

        $this->dataTable['default']['tables'] = $commontables;
        $this->dataTable['default']['link']   = $commonlink;
        $this->dataTable['product']['tables'] = $this->skutable;
        $this->dataTable['product']['link']   = $skulink;

        $this->timeSelectionUnit = "seasonalTimeframe";
        $this->getAllSeasonalTimeframe(); // function implemented on the base class to get the project-manager side seasonal timeframe table data

        $this->weekField = "";
        $this->yearField = "";
        $this->weekperiod = "";
        $this->yearperiod = "";
        $this->prepareFromToDateRange();
        
        $this->gridFilterDDMapping[] = ['data'=>0, 'label'=>'ALL'];
        $this->gridFilterDDMapping[] = ['data'=>1, 'label'=>'STC Forecast'];
        $this->gridFilterDDMapping[] = ['data'=>2, 'label'=>'Actual Sales'];
        $this->gridFilterDDMapping[] = ['data'=>3, 'label'=>'Actual vs Forecast'];
        $this->gridFilterDDMapping[] = ['data'=>4, 'label'=>'TAS vs Forecast'];
    }
    
    public function prepareFromToDateRange() {
        $timeframe = (isset($_REQUEST['timeFrame']) && !empty($_REQUEST['timeFrame'])) ? $_REQUEST['timeFrame'] : '';
        if (!empty($timeframe) && isset($this->seasonalTimeframeConfiguration) && count($this->seasonalTimeframeConfiguration) > 0) {
            $sKey = array_search($timeframe, array_column($this->seasonalTimeframeConfiguration,'id'));
            $data = isset($this->seasonalTimeframeConfiguration[$sKey]) ? $this->seasonalTimeframeConfiguration[$sKey] : $this->seasonalTimeframeConfiguration[0];
        } else {
            $data = $this->seasonalTimeframeConfiguration[0];
        }

        if(!empty($data)){
            $tyFromDate = explode(' ',$data['ty_start_date']);
            $tyToDate   = explode(' ',$data['ty_end_date']);
            /*$lyFromDate = $data['ly_start_date'];
            $lyToDate   = $data['ly_end_date'];*/
            $tyFDate =  explode('-', $tyFromDate[0]);
            $tyTDate =  explode('-', $tyToDate[0]);

            $this->fromToDateRange = ['fromDay'   => $tyFDate[2],
                                      'fromMonth' => $tyFDate[1],
                                      'fromYear'  => $tyFDate[0],
                                      'toDay'     => $tyTDate[2],
                                      'toMonth'   => $tyTDate[1],
                                      'toYear'    => $tyTDate[0],
                                      'maxDate'   => $tyToDate[0]
                                    ];
        }
    }
}
?>