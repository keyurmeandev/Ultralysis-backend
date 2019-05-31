<?php

namespace projectsettings;

use db;

class UbSeasonalTracker extends BaseLcl {

    public function __construct($aid, $projectID, $gid) {
        $this->maintable = "seasonal_tracker_ub";

        if($this->hasTerritory($aid, $projectID))
            $this->territorytable = "territory";
            
        $this->clientID = "UB";
        $this->accounttable = "fgroup";
        $this->aid = $aid;
        $this->projectID = $projectID;

        $this->includeDateInTimeFilter  = false;
        parent::__construct($gid);

        $this->skutable = "seasonal_product";
        $this->privateLabelFilterField = $this->skutable.".pl";

        $this->tableArray['product']['tables']  = $this->skutable;
        $this->productHelperTables = $this->skutable;
        $this->registeredProductCodesFilePath = $_SERVER['DOCUMENT_ROOT']."/data-uploader/project/uploads/ub";

        $this->timeSelectionStyle = 'DROPDOWN';
        $this->accountLink = " AND $this->accounttable.GID IN ($this->GID) AND $this->maintable.GID=$this->accounttable.gid ";
        $this->accountHelperLink = " WHERE ".$this->accounttable.".GID IN (".$this->GID.") ";

        $this->timetable  = "seasonal_tracker_ub";
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

        $this->showContributionAnalysisPopup = false;
        $this->prepareFromToDateRange();
    }

    public function prepareFromToDateRange() {
        $queryVars = settingsGateway::getInstance();
        $timeframe = (isset($_REQUEST['timeFrame']) && !empty($_REQUEST['timeFrame'])) ? explode('-', $_REQUEST['timeFrame']) : array();

        if (is_array($timeframe) && !empty($timeframe)) {
            $query = "SELECT MIN(".$this->dateField.") AS MIN_DATE, MAX(".$this->dateField.") AS MAX_DATE FROM ".
                    $this->timetable.$this->timeHelperLink." AND ".$this->yearField."=".$timeframe[1];
            $result = $queryVars->queryHandler->runQuery($query, $queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            if (is_array($result) && !empty($result)) {
                $minDate = explode('-', $result[0]['MIN_DATE']);
                // $maxDate = $result[0]['MAX_DATE'];
                $maxDate = explode('-', $result[0]['MAX_DATE']);
                $this->fromToDateRange = ['fromDay' => $minDate[2], 'fromMonth' => $minDate[1], 'toDay' => $maxDate[2], 'toMonth' => $maxDate[1], 'maxDate' => $result[0]['MAX_DATE']];
            }
        } else {
            $this->fromToDateRange = ['fromDay'=>'01', 'fromMonth'=>'01', 'toDay'=>'31', 'toMonth'=>'12'];
        }
    }

    public function hasHiddenSku(){
        return false;
    }
}
?>