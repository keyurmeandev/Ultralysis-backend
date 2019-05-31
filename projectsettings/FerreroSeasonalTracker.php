<?php

namespace projectsettings;
use db;

class FerreroSeasonalTracker extends BaseLcl {

    public function __construct($aid, $projectID, $gid) {
        $this->maintable = "seasonal_tracker_ferrero";
        $this->seasonalmorrisonspricetable = "seasonal_morrisons_price";
        $this->morrisonsGid = 3;

        if($this->hasTerritory($aid, $projectID))
            $this->territorytable = "territory";
            
        $this->clientID = "FERRERO";
        $this->accounttable = "fgroup";
        $this->aid = $aid;
        $this->projectID = $projectID;

        $this->includeDateInTimeFilter  = false;
        parent::__construct($gid);

        $this->skutable = "seasonal_product";
        $this->privateLabelFilterField = $this->skutable.".pl";

        $this->tableArray['product']['tables']  = $this->skutable;
        $this->productHelperTables = $this->skutable;
        $this->registeredProductCodesFilePath = $_SERVER['DOCUMENT_ROOT']."/data-uploader/project/uploads";

        $this->timeSelectionStyle = 'DROPDOWN';
        $this->accountLink = " AND $this->accounttable.GID IN ($this->GID) AND $this->maintable.GID=$this->accounttable.gid ";
        $this->accountHelperLink = " WHERE ".$this->accounttable.".GID IN (".$this->GID.") ";

        $this->timetable  = "seasonal_tracker_ferrero";
        $this->weekperiod = "";
        $this->yearperiod = "";

        $this->configureClassVars();
        $commontables   = $this->maintable;

        $commonlink     = " WHERE $this->maintable.GID IN (".$this->GID.") AND hide=0 ";
        $skulink        = " AND $this->maintable.PNAME=$this->skutable.pname ";

        $this->productHelperLink = " WHERE $this->skutable.pname IN (SELECT DISTINCT $this->maintable.PNAME FROM $this->maintable WHERE $this->maintable.GID IN (".$this->GID.")) ";

        $this->copy_tablename = $this->tablename = " " . $this->maintable . "," . $this->skutable;
        $this->copy_link = $this->link = $commonlink.$skulink;

        $this->dataTable['default']['tables']      = $commontables;
        $this->dataTable['default']['link']        = $commonlink;
        $this->dataTable[$this->skutable]['tables'] = $this->skutable;
        $this->dataTable[$this->skutable]['link']  = $skulink;
        
        $this->timeSelectionUnit = "seasonal";
        $this->weekField = "seasonal_description";
        $this->yearField = "seasonal_year";
        $this->timeHelperLink = $commonlink." AND ".$this->weekField." IS NOT NULL";

        $this->pnameField = $this->maintable.".PNAME";
        $this->pnameMorrisonsPriceTableField = $this->seasonalmorrisonspricetable.".PNAME";
        $this->showContributionAnalysisPopup = false;
        
        $this->fetchProductAndMarketFilterOnTabClick = true;

        /*[START] SEASONAL TIMEFRAME SETTING */
        $this->hasSeasonalTimeframe = $this->hasSeasonalTimeframe();
        if ($this->hasSeasonalTimeframe) {
            $this->timeSelectionUnit = "seasonalTimeframe";
            $this->getAllSeasonalTimeframe(); // function implemented on the base class to get the project-manager side seasonal timeframe table data

            $this->prepareFromToDateRangeWithSeasonalTimeframe();
        }else{
            $this->prepareFromToDateRange(); // this is for the previous logic to develop the hardstop dates.
        }
        /*[END] SEASONAL TIMEFRAME SETTING */
    }

    public function prepareFromToDateRange() {
        $queryVars = settingsGateway::getInstance();
        $timeframe = (isset($_REQUEST['timeFrame']) && !empty($_REQUEST['timeFrame'])) ? explode('-', $_REQUEST['timeFrame']) : array();

        if ($timeframe[0] == 'EASTER') {
            $facings = [
                '2018' => 0,
                '2017' => 0,
                '2016' => 0,
            ];
        }

        if (is_array($timeframe) && !empty($timeframe)) {
            $query = "SELECT MIN(".$this->dateField.") AS MIN_DATE, MAX(".$this->dateField.") AS MAX_DATE FROM ".
                    $this->timetable.$this->timeHelperLink." AND ".$this->weekField." = '".$timeframe[0]."' AND ".$this->yearField."=".$timeframe[1];
            $result = $queryVars->queryHandler->runQuery($query, $queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            if (is_array($result) && !empty($result)) {
                $minDate = $result[0]['MIN_DATE'];
                if (isset($facings) && isset($facings[$timeframe[1]]) && !empty($facings[$timeframe[1]])) {
                    $minDate = date('Y-m-d', strtotime($minDate . ' + '.$facings[$timeframe[1]] . ' days'));
                }
                $minDate = explode('-', $minDate);
                // $maxDate = explode('-', $result[0]['MAX_DATE']);
                $maxDate = $result[0]['MAX_DATE'];
                $this->fromToDateRange = ['fromDay' => $minDate[2], 'fromMonth' => $minDate[1], 'toDay' => '31', 'toMonth' => '12', 'maxDate' => $maxDate];
            }
        } else {
            $this->fromToDateRange = ['fromDay'=>'01', 'fromMonth'=>'10', 'toDay'=>'03', 'toMonth'=>'01'];
        }
    }

    public function hasHiddenSku(){
        return false;
    }

    public function prepareFromToDateRangeWithSeasonalTimeframe() {
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
            $tyFDate    = explode('-', $tyFromDate[0]);
            $tyTDate    = explode('-', $tyToDate[0]);

            $lyFromDate = explode(' ',$data['ly_start_date']);
            $lyToDate   = explode(' ',$data['ly_end_date']);
            $lyFDate    = explode('-', $lyFromDate[0]);
            $lyTDate    = explode('-', $lyToDate[0]);

            $this->fromToDateRange = ['fromDay'    => $tyFDate[2],
                                      'fromMonth'  => $tyFDate[1],
                                      'fromYear'   => $tyFDate[0],
                                      'toDay'      => $tyTDate[2],
                                      'toMonth'    => $tyTDate[1],
                                      'toYear'     => $tyTDate[0],
                                      'maxDate'    => $tyToDate[0],
                                      'lyFromDay'  => $lyFDate[2],
                                      'lyFromMonth'=> $lyFDate[1],
                                      'lyFromYear' => $lyFDate[0],
                                      'lyToDay'    => $lyTDate[2],
                                      'lyToMonth'  => $lyTDate[1],
                                      'lyToYear'   => $lyTDate[0],
                                      'lyMaxDate'  => $lyToDate[0]
                                    ];
        }
    }

}
?>