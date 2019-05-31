<?php

namespace projectsettings;

class ShanaCorpSales extends BaseLcl {

    public function __construct($aid, $projectID, $gids) {
        $this->maintable = "shana_corpsales";

		if($this->hasTerritory($aid, $projectID))
			$this->territorytable = "territory";

		$this->masterplanotable = "";
        $this->accounttable     = "fgroup";
        $this->clientID         = "SHANA";
        $this->aid              = $aid;
        $this->projectID        = $projectID;
		$this->GID              = implode(",",$gids);

        parent::__construct($gids);

        $this->timetable  = $this->maintable;
        $this->yearField            = "year";
        $this->weekField            = "month";
        $this->monthperiod = $this->weekperiod = "$this->maintable.month";
        $this->yearperiod           = "$this->maintable.year";
        $this->timeSelectionUnit    = "weekMonth";

        $this->currencySign = "£";
        $this->ProjectVolume = "actual_volume";
        $this->ProjectValue = "actual_sales";

        $this->accountHelperTables      = $this->accounttable;
        $this->accountHelperLink        = " AND ".$this->accounttable.".gid IN (SELECT DISTINCT " . 
                                            $this->maintable . ".GID FROM " . $this->maintable . 
                                            " WHERE ".$this->maintable.".GID IN (".$this->GID.")) ";

        $this->configureClassVars();


        $this->dateperiod           = "";
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

    public function prepareMeasureForActualBudgetComparisonAnalysis()
    {
        $this->hasMeasureFilter = false;

        $this->pageArray["MEASURE_SELECTION_LIST"] = array(
            array('measureID' => 1, 'jsonKey'=>'VALUE', 'measureName' => 'Value ('.$this->currencySign.')', 'selected' => true),
            array('measureID' => 2, 'jsonKey'=>'VOLUME', 'measureName' => 'Volume', 'selected' => false)
        );

        $this->measureArray = array();
        $this->measureArray['M1']['VAL'] = "actual_sales";
        $this->measureArray['M1']['ALIASE'] = "actual_sales";
        $this->measureArray['M1']['attr'] = "SUM";
        $this->measureArray['M1']['NAME'] = "Actual Sales";

        $this->measureArray['M2']['VAL'] = "actual_volume";
        $this->measureArray['M2']['ALIASE'] = "actual_volume";
        $this->measureArray['M2']['attr'] = "SUM";
        $this->measureArray['M2']['NAME'] = "Actual Volume";

        $this->measureArray['M3']['VAL'] = "budget_sales";
        $this->measureArray['M3']['ALIASE'] = "budget_sales";
        $this->measureArray['M3']['attr'] = "SUM";
        $this->measureArray['M3']['NAME'] = "Budget Sales";

        $this->measureArray['M4']['VAL'] = "budget_volume";
        $this->measureArray['M4']['ALIASE'] = "budget_volume";
        $this->measureArray['M4']['attr'] = "SUM";
        $this->measureArray['M4']['NAME'] = "Budget Volume";

        $this->measureArrayMapping = [ 1=>[1,3], 2=>[2,4] ];
    }
}
?>