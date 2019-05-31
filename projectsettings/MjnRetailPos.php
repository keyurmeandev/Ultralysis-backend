<?php

namespace projectsettings;

class MjnRetailPos extends BaseLcl {

    public function __construct($aid, $projectID) {
        $this->maintable = "sales";
        
        $this->clientID = "";
        $this->aid = $aid; // 52
        $this->projectID = $projectID;

        parent::__construct();		
        $this->clustertable = "";
        $this->timetable  = "pos_period";
        $this->weekperiod = "$this->timetable.calweek";
        $this->yearperiod = "$this->timetable.calyear";
        $this->dateperiod = "period";
		
        if(!$this->hasMeasureFilter){
    		// measure selection list
            $this->pageArray["MEASURE_SELECTION_LIST"] = array(
                array('measureID' => 1, 'jsonKey'=>'VALUE', 'measureName' => 'Value ('.$this->currencySign.')', 'selected' => true ),
                array('measureID' => 2, 'jsonKey'=>'VOLUME', 'measureName' => 'Volume', 'selected' => false ),
                array('measureID' => 3, 'jsonKey'=>'EQUIV', 'measureName' => 'Equiv 8oz', 'selected' => false )
            );
        }
		
        $this->configureClassVars();

        $this->copy_tablename = $this->tablename = " " . $this->maintable . "," . $this->timetable . "," . $this->skutable . "," . $this->storetable . "," . $this->grouptable;

        $commonlink     = " WHERE $this->maintable.accountID=$this->aid " .
                        "AND $this->maintable.year = $this->timetable.year ".
                        "AND $this->maintable.week = $this->timetable.week ".
                        "AND $this->maintable.GID = $this->timetable.GID ".
                        "AND $this->maintable.GID = $this->grouptable.GID " .
                        "AND groupType='POS' ";

        $storelink      = " AND $this->maintable.SNO=$this->storetable.SNO " .
                        "AND $this->maintable.GID=$this->storetable.GID ";

        $skulink        = "AND $this->maintable.skuID=$this->skutable.skuID " .
                        "AND $this->maintable.GID=$this->skutable.GID " .
                        "AND $this->skutable.hide<>1 ";
      
        $this->copy_link     = $this->link     = $commonlink.$storelink.$skulink;

        $this->dataTable['default']['tables']      = $this->maintable . "," . $this->timetable . "," . $this->grouptable;
        $this->dataTable['default']['link']        = $commonlink;

        $this->dataTable['product']['tables']      = $this->skutable;
        $this->dataTable['product']['link']        = $skulink;

        $this->dataTable['store']['tables']        = $this->storetable;
        $this->dataTable['store']['link']          = $storelink;

        if(!$this->hasMeasureFilter){
            $this->measureArray['M3']['VAL']    = "ROUND(IFNULL($this->ProjectVolume,1)*equiv_8,0)";
            $this->measureArray['M3']['ALIASE'] = "EQUIV";
            $this->measureArray['M3']['attr']   = "SUM";
            $this->measureArray['M3']['usedFields'] = array('product.equiv_8');

            $this->measureArray['M4']['VAL'] = "(SUM(IFNULL($this->ProjectValue,0))/SUM(IFNULL($this->ProjectVolume,1)))";
            $this->measureArray['M4']['ALIASE'] = "PRICE";      
            $this->measureArray['M4']['attr']   = "";
            
            $this->measureArray['M5']['VAL'] = "DISTINCT((CASE WHEN ".$this->ProjectValue." > 0 THEN ".$this->maintable.".SNO END))";
            $this->measureArray['M5']['ALIASE'] = "DISTRIBUTION";
            $this->measureArray['M5']['attr'] = "COUNT";

            $this->performanceTabMappings["priceOverTime"] = 'M4';
            $this->performanceTabMappings["distributionOverTime"] = 'M5';
        }

        /*$this->measureArray['M3']['VAL'] = "weight";
        $this->measureArray['M3']['ALIASE'] = "Weight";
        $this->measureArray['M3']['attr'] = "SUM";  

        $this->measureArray['M4']['VAL'] = "(SUM(IFNULL($this->ProjectValue,0))/SUM(IFNULL($this->ProjectVolume,1)))";
        $this->measureArray['M4']['ALIASE'] = "PRICE";

        $this->measureArray['M5']['VAL'] = "DISTINCT((CASE WHEN ".$this->ProjectValue." > 0 THEN ".$this->maintable.".SNO END))";
        $this->measureArray['M5']['ALIASE'] = "DISTRIBUTION";
        $this->measureArray['M5']['attr'] = "COUNT";*/
    }

}

?>