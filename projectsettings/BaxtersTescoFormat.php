<?php

namespace projectsettings;

class BaxtersTescoFormat extends BaseLcl {

    public function __construct($aid, $projectID) {
        $this->maintable            = "baxters_tesco_format";
        $this->skulisttable			= "baxters_tesco_format_skulist_weekly";
        $this->accounttable         = "baxters_tesco_format";

        if($this->hasTerritory($aid, $projectID))
            $this->territorytable = "territory";

        
        $this->masterplanotable = "master_plano";
        $this->clientID = "BAXTERS";
        $this->aid = $aid;
        $this->projectID = $projectID;
        $this->calculateVsiOhq = true;

        $this->accountLink = " AND $this->accounttable.GID IN ($this->GID) AND $this->maintable.GID = $this->accounttable.gid AND $this->maintable.accountID = ".$aid . " ";
        
        parent::__construct(array(1));
        $this->clustertable = "";
        $this->accountHelperLink = " WHERE ".$this->accounttable.".GID IN (".$this->GID.") AND $this->maintable.accountID = ".$aid . " ";
        
        $this->dateperiod = "mydate";
        $this->ProjectVolume = "units";
        
        if(!$this->hasMeasureFilter){
            $this->pageArray["MEASURE_SELECTION_LIST"] = array(
                array('measureID' => 1, 'jsonKey'=>'VAL_EXC_VAT', 'measureName' => 'Value Exc. VAT'),
                array('measureID' => 2, 'jsonKey'=>'VALUE', 'measureName' => 'Value ('.$this->currencySign.')'),
                array('measureID' => 3, 'jsonKey'=>'VOLUME', 'measureName' => 'Volume'),
                array('measureID' => 4, 'jsonKey'=>'PROFIT', 'measureName' => 'Profit'),
            );
        }
        
        $this->configureClassVars();
        
        $commonlink     = " WHERE $this->maintable.accountID=$this->aid " .
                        "AND $this->maintable.".$this->dateperiod."=$this->timetable.".$this->dateperiod." " .
                        "AND $this->maintable.GID=$this->timetable.gid " .
                        "AND $this->maintable.skuID = $this->skulisttable.skuID " .
                        "AND $this->maintable.accountYear=$this->skulisttable.listAccountYear " .
                        "AND $this->maintable.accountWeek=$this->skulisttable.listAccountWeek " .
                        "AND $this->timetable.gid IN (".$this->GID.") ";

        $storelink      = " AND $this->maintable.SNO=$this->storetable.SNO " .
                        "AND $this->maintable.GID=$this->storetable.GID " .
                        "AND $this->storetable.gid IN (".$this->GID.") ";
                        
        $territorylink  = ((!empty($this->territorytable)) ? " AND $this->storetable.SNO=$this->territorytable.SNO 
                        AND $this->storetable.GID=$this->territorytable.GID
                        AND $this->territorytable.GID IN ($this->GID) AND $this->territorytable.accountID = $this->aid " : "");
                        
        $accountlink  = ((!empty($this->accounttable)) ? $this->accountLink : "");
                        
        $skulink        = "AND $this->maintable.skuID=$this->skutable.PIN " .
                        "AND $this->maintable.GID=$this->skutable.GID " .
                        "AND $this->skutable.hide<>1 " .
                        //"AND $this->skulisttable.skuID=$this->skutable.PIN " .
                        "AND $this->skutable.gid IN (".$this->GID.") ".
                        "AND $this->skutable.clientID='$this->clientID' ";

        $this->copy_link     = $this->link     = $commonlink.$storelink.$territorylink.$skulink.$accountlink;
        
        $this->dataTable['default']['tables']      = $this->maintable . "," . $this->timetable . "," .$this->skulisttable;
        $this->dataTable['default']['link']        = $commonlink;

        $this->dataTable['product']['tables']      = $this->skutable;
        $this->dataTable['product']['link']        = $skulink;

        $this->dataTable['store']['tables']        = $this->storetable;
        $this->dataTable['store']['link']          = $storelink;

        $this->dataTable['territory']['tables']    = $this->territorytable;
        $this->dataTable['territory']['link']      = $territorylink;
        
        $this->dataTable[$this->accounttable]['tables']        = "";
        $this->dataTable[$this->accounttable]['link']          = "";
        
        if(!$this->hasMeasureFilter){
            $this->measureArray = array();
    		$this->measureArray['M1']['VAL']	= "value/vat";
    		$this->measureArray['M1']['ALIASE']	= "VAL_EXC_VAT";
    		$this->measureArray['M1']['attr']	= "SUM";
    		
            $this->measureArray['M2']['VAL'] 	= $this->ProjectValue;
            $this->measureArray['M2']['ALIASE'] = "VALUE";
            $this->measureArray['M2']['attr'] 	= "SUM";

            $this->measureArray['M3']['VAL'] 	= $this->ProjectVolume;
            $this->measureArray['M3']['ALIASE'] = "VOLUME";
            $this->measureArray['M3']['attr'] 	= "SUM";
            unset($this->measureArray['M3']['dataDecimalPlaces']);

            $this->measureArray['M4']['VAL'] 	= "profit";
            $this->measureArray['M4']['ALIASE'] = "PROFIT";
            $this->measureArray['M4']['attr'] 	= "SUM";
            unset($this->measureArray['M4']['dataDecimalPlaces']);

            $this->measureArray['M5']['VAL'] = "(SUM(IFNULL($this->ProjectValue,0))/SUM(IFNULL($this->ProjectVolume,1)))";
            $this->measureArray['M5']['ALIASE'] = "PRICE";
            $this->measureArray['M5']['attr'] = "";
            $this->measureArray['M5']['dataDecimalPlaces'] = 2;

            $this->measureArray['M6']['VAL'] = "DISTINCT((CASE WHEN ".$this->ProjectValue." > 0 THEN ".$this->maintable.".SNO END))";
            $this->measureArray['M6']['ALIASE'] = "DISTRIBUTION";
            $this->measureArray['M6']['attr'] = "COUNT";
            $this->measureArray['M6']['dataDecimalPlaces'] = 0;
            
            $this->performanceTabMappings = array(
                "drillDown" => 'M2',
                "overTime" => 'M2',
                "priceOverTime" => 'M5',
                "distributionOverTime" => 'M6'
            );            
            
        }
    }

}

?>