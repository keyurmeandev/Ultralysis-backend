<?php

namespace projectsettings;

class MjnUsaNielsenLcl extends BaseLcl {

    public function __construct($aid, $projectID) {
        $this->maintable = "mjn_usa_nielsen";
        $this->mjnbrandsorttable = "mjn_brand_sort";

        if($this->hasTerritory($aid, $projectID))
            $this->territorytable = "territory";
            
        $this->clientID = "MJNUSA";
        $this->aid = $aid;
        $this->projectID = $projectID;

        parent::__construct(array(11));

        $this->configureClassVars();
        $commonlink     = " WHERE $this->maintable.accountID=$this->aid " .
                        "AND $this->maintable.".$this->dateperiod."=$this->timetable.".$this->dateperiod." " .
                        "AND $this->maintable.GID=$this->timetable.gid " .
                        "AND $this->timetable.gid IN (".$this->GID.") AND ".$this->maintable.".is_processed = 1 ";
                        
        $this->timeHelperLink = " WHERE " . $this->timetable.".GID IN (".$this->GID.") ".
                                " AND ". $this->timetable . "." . $this->dateperiod . 
                                " IN ( SELECT DISTINCT " . $this->maintable . "." . $this->dateperiod . 
                                    " FROM " . $this->maintable . " WHERE ".$this->maintable.".GID IN (".$this->GID.") AND ".$this->maintable.".is_processed = 1 ) ";
                        
        $this->dataTable['default']['link']        = $commonlink;

        $this->retailerReportTotalField = $this->storetable.".customAgg3";
        
        if(!$this->hasMeasureFilter){
        
    		// measure selection list
            $this->pageArray["MEASURE_SELECTION_LIST"] = array(
                array('measureID' => 1, 'jsonKey'=>'VALUE', 'measureName' => 'Value ('.$this->currencySign.')', 'selected' => true ),
                array('measureID' => 2, 'jsonKey'=>'VOLUME', 'measureName' => 'Volume', 'selected' => false ),
                array('measureID' => 3, 'jsonKey'=>'EQVOL', 'measureName' => 'EQ Vol', 'selected' => false )
            );
        
            $this->measureArray['M3']['VAL'] 	= "eqvol";
            $this->measureArray['M3']['ALIASE'] = "EQVOL";
            $this->measureArray['M3']['attr'] 	= "SUM";
            unset($this->measureArray['M3']['dataDecimalPlaces']);
            
            $this->measureArray['M5']['VAL'] = "(SUM(IFNULL($this->ProjectValue,0))/SUM(IFNULL($this->ProjectVolume,1)))";
            $this->measureArray['M5']['ALIASE'] = "PRICE";
            $this->measureArray['M5']['attr']   = "";
            $this->measureArray['M5']['dataDecimalPlaces'] = 2;

            $this->measureArray['M6']['VAL'] = "DISTINCT((CASE WHEN ".$this->ProjectValue." > 0 THEN ".$this->maintable.".SNO END))";
            $this->measureArray['M6']['ALIASE'] = "DISTRIBUTION";
            $this->measureArray['M6']['attr'] = "COUNT";
            $this->measureArray['M6']['dataDecimalPlaces'] = 0;

            $this->performanceTabMappings["priceOverTime"] = 'M5';
            $this->performanceTabMappings["distributionOverTime"] = 'M6';
            
            if($this->projectTypeID == 6) // DDB
            {
                $this->measureArray['M3']['VAL'] 	= "eqvol";
                $this->measureArray['M3']['ALIASE'] = "EQVOL";
                $this->measureArray['M3']['attr'] 	= "SUM";
                unset($this->measureArray['M3']['dataDecimalPlaces']);
                
                $this->pageArray["MEASURE_SELECTION_LIST"] = array(
                    array('measureID' => 1, 'jsonKey'=>'VALUE', 'measureName' => 'DOLLARS', 'selected' => true ),
                    array('measureID' => 2, 'jsonKey'=>'VOLUME', 'measureName' => 'UNITS', 'selected' => true ),
                    array('measureID' => 3, 'jsonKey'=>'EQVOL', 'measureName' => 'EQ Vol', 'selected' => false )
                );            
            }
        }
       
    }
}
?>