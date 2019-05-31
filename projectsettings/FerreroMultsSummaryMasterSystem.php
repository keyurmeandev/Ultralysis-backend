<?php

namespace projectsettings;
// year week
class FerreroMultsSummaryMasterSystem extends BaseMults {

    public function __construct($aid, $projectID, $gids) {
        $this->maintable = "ferrero_mults_summary";

		$this->accounttable = ($projectID == 787) ? "ferrero_mults_summary" : "fgroup";
        $this->clientID = "FERRERO";
        $this->aid = $aid;
        $this->projectID = $projectID;
		$this->GID = implode(",",$gids);
        
        parent::__construct($gids);

        $this->currencySign = "£";
        $this->ProjectValue = "sales";
        $this->ProjectVolume = "qty";

        if(!$this->hasMeasureFilter){
            // measure selection list
            $this->pageArray["MEASURE_SELECTION_LIST"] = array(
                array('measureID' => 1, 'jsonKey'=>'VALUE', 'measureName' => 'Value (£)'),
                array('measureID' => 2, 'jsonKey'=>'VOLUME', 'measureName' => 'Volume')
            );
        }
		
        $this->dateperiod = "period";
        
        $this->configureClassVars();
    }
	public function configureClassVars() {

		$this->dateField = $this->maintable . "." . $this->dateperiod;
		$this->copy_tablename = $this->tablename = " " . $this->maintable . "," . $this->timetable . "," . $this->skutable . "," . $this->grouptable;
		
        $commontables   = $this->maintable . "," . $this->timetable. "," . $this->grouptable;
		$commonlink     = " WHERE $this->maintable.accountID=$this->aid " .
                        "AND $this->maintable.period=$this->timetable.period " .
                        "AND $this->maintable.GID=$this->timetable.GID " .
                        "AND $this->maintable.GID=$this->grouptable.gid " .
                        "AND $this->timetable.gid IN (".$this->GID.") ".
                        "AND $this->maintable.gid IN (".$this->GID.") ";

        $skulink        = "AND $this->maintable.PIN=$this->skutable.PIN " .
        				"AND $this->maintable.GID=$this->skutable.GID " .
                        "AND $this->skutable.hide<>1 " .
                        "AND $this->skutable.gid IN (".$this->GID.") ".
                        "AND $this->skutable.clientID='$this->clientID' ";

        $this->copy_link     = $this->link     = $commonlink.$storelink.$skulink;
		

        $this->timeHelperTables = $this->timetable;
        $this->timeHelperLink = " WHERE " . $this->timetable.".GID IN (".$this->GID.") ".
                                " AND ". $this->timetable . "." . $this->dateperiod . 
                                " IN ( SELECT DISTINCT " . $this->maintable . "." . $this->dateperiod . 
                                    " FROM " . $this->maintable . " WHERE ".$this->maintable.".GID IN (".$this->GID.") ) ";

		if($this->hasHiddenSku()) {
            $commonlink   .= " AND ".$this->maintable.".PIN NOT IN ( ".$this->hiddenSkusQueryString.") ";
        }

		$this->dataTable['default']['tables']      = $commontables;
        $this->dataTable['default']['link']        = $commonlink;

        $this->dataTable['product']['tables']      = $this->skutable;
        $this->dataTable['product']['link']        = $skulink;

		/** ******************************************** USED ONLY FOR CALENDAR PAGE *********************** */
		$this->calendarItems = array();
		array_push($this->calendarItems, array(
			'DATA' => $this->maintable . "." . $this->dateperiod,
			'ALIASE' => "MYDATE"
				)
		);
		array_push($this->calendarItems, array(
			'DATA' => $this->timetable.'.week',
			'ALIASE' => "WEEK"
				)
		);
		array_push($this->calendarItems, array(
			'DATA' => $this->timetable.'.year',
			'ALIASE' => "YEAR"
				)
		);
		array_push($this->calendarItems, array(
			'DATA' => "accountweek",
			'ALIASE' => "ACCOUNTWEEK"
				)
		);
		array_push($this->calendarItems, array(
			'DATA' => "accountyear",
			'ALIASE' => "ACCOUNTYEAR"
				)
		);

        if($this->hasMeasureFilter){
            $this->getMeasureSettings();
        }else{
    		$this->measureArray = array();
    		$this->measureArray['M1']['VAL'] = $this->ProjectValue;
    		$this->measureArray['M1']['ALIASE'] = "VALUE";
    		$this->measureArray['M1']['attr'] = "SUM";

    		$this->measureArray['M2']['VAL'] = $this->ProjectVolume;
    		$this->measureArray['M2']['ALIASE'] = "VOLUME";
    		$this->measureArray['M2']['attr'] = "SUM";

    		$this->measureArray['M3']['VAL'] = "(SUM(IFNULL($this->ProjectValue,0))/SUM(IFNULL($this->ProjectVolume,1)))";
    		$this->measureArray['M3']['ALIASE'] = "PRICE";      
            $this->measureArray['M3']['attr'] = "";
            $this->measureArray['M3']['dataDecimalPlaces'] = 2;
    		
    		/*$this->measureArray['M4']['VAL'] = "DISTINCT((CASE WHEN ".$this->ProjectValue." > 0 THEN ".$this->maintable.".SNO END))";
    		$this->measureArray['M4']['ALIASE'] = "DISTRIBUTION";
    		$this->measureArray['M4']['attr'] = "COUNT";*/

            $this->measureArray['M4']['VAL'] = "MAX(store_count)";
            $this->measureArray['M4']['ALIASE'] = "DISTRIBUTION";
            $this->measureArray['M4']['attr']   = "DISTRIBUTION";
            $this->measureArray['M4']['dataDecimalPlaces']   = 0;
            
            $this->performanceTabMappings = array(
                "drillDown" => 'M1',
                "overTime" => 'M1',
                "priceOverTime" => 'M3',
                "distributionOverTime" => 'M4'
            );
        }
        
        $this->getClientProjectName();
	}

}

?>