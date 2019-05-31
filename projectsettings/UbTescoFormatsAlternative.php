<?php

namespace projectsettings;
use projectstructure;

class UbTescoFormatsAlternative extends BaseLcl {

    public function __construct($aid, $projectID, $gid) {
        $this->maintable = "ub_tesco_format_alternative";

        if($this->hasTerritory($aid, $projectID))
            $this->territorytable = "territory";
            
        $this->clustertable = "";
        $this->accounttable = "fgroup";
        $this->clientID     = "UB";
        $this->aid          = $aid;
        $this->projectID    = $projectID;
        parent::__construct($gid);

        $this->projectStructureType = projectstructure\ProjectStructureType::$MEASURE_WITH_TYLY_AS_DIFF_COLUMN;
        $this->dateperiod = "period";
        $this->periodField = "period";

        $this->accountLink = " AND $this->accounttable.GID IN ($this->GID) AND $this->maintable.GID=$this->accounttable.gid ";
        $this->accountHelperLink = " WHERE ".$this->accounttable.".GID IN (".$this->GID.") AND $this->maintable.accountID = ".$aid . " ";

        $this->ProjectValue  = "value";
        $this->ProjectVolume = "volume";

        $this->configureClassVars();

        $this->dataTable['store']['tables']     = '';
        $this->dataTable['store']['link']       = '';

        if(!$this->hasMeasureFilter){
            $this->measureArray['M3']['VAL']    = "profit";
            $this->measureArray['M3']['ALIASE'] = "PROFIT";
            $this->measureArray['M3']['attr']   = "SUM";
            $this->measureArray['M3']['usedFields'] = array('profit_ty');

            $this->measureArray['M4']['VAL']    = "value_ex_vat";
            $this->measureArray['M4']['ALIASE'] = "VALUE_EX_VAT";
            $this->measureArray['M4']['attr']   = "SUM";
            $this->measureArray['M4']['usedFields'] = array('value_ex_vat_ty');

        }

        $this->pageArray["MEASURE_SELECTION_LIST"] = array(
            array('measureID' => 1, 'jsonKey'=>'VALUE', 'measureName' => 'Value ('.$this->currencySign.')'),
            array('measureID' => 2, 'jsonKey'=>'VOLUME', 'measureName' => 'Volume'),
            array('measureID' => 3, 'jsonKey'=>'PROFIT', 'measureName' => 'profit'),
            array('measureID' => 4, 'jsonKey'=>'VALUE_EX_VAT', 'measureName' => 'value_ex_vat')
        );
    }
}

?>