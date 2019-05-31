<?php
namespace projectsettings;

class ArlaBump extends BaseLcl {
    public function __construct($aid, $projectID, $gid) {
        $this->maintable = "arla_lcl_bump";
        if ($this->hasTerritory($aid, $projectID))
            $this->territorytable = "territory";

        $this->clientID = "ARLA";
        $this->aid = $aid;
        $this->projectID = $projectID;

        parent::__construct($gid);

        $this->ProjectValue = "SALES";
        $this->ProjectVolume = "QTY";
        $this->dateperiod = "period";

        $this->configureClassVars();

        $this->accounttable = $this->maintable;
        $this->accountHelperTables = $this->accounttable;
        $this->accountHelperLink = " WHERE $this->accounttable.GID IN ($this->GID) ";

        /*[START] WE MAKE THIS STATIC OVERWRITE OF MEASURE BECAUSE OF THE BASE INCREMENTAL TRACKER REPORT PAGE (BaseIncrementalTrackerPage.php) LOGIC */
        $this->pageArray["MEASURE_SELECTION_LIST"] = array(
            array('measureID' => 1, 'jsonKey'=>'VALUE', 'measureName' => 'Value ('.$this->currencySign.')'),
            array('measureID' => 2, 'jsonKey'=>'VOLUME', 'measureName' => 'Volume')
        );
    }
}
?>