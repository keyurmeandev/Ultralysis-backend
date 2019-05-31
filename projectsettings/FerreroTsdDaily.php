<?php
namespace projectsettings;
    
class FerreroTsdDaily extends BaseTsdDailyReport {

    public function __construct($accountID,$projectID) {

        $this->maintable            = "ferrero_tesco_store_daily_14";        
        $this->tesco_depot_daily    = "ferrero_tesco_depot_daily";
        $this->rangedtable          = "ferrero_tesco_ranged";
        $this->clientID             = "FERRERO";        
        $this->footerCompanyName    = "Ferrero";

		parent::__construct($accountID,$projectID);	

        $this->getClientAndRetailerLogo();

        // measure selection list
        $this->pageArray["MEASURE_SELECTION_LIST"] = array(
            array('measureID' => 1, 'jsonKey'=>'VALUE', 'measureName' => 'Value ('.$this->currencySign.')', 'selected' => false),
            array('measureID' => 2, 'jsonKey'=>'VOLUME', 'measureName' => 'Units', 'selected' => true)
        ); 

        $this->configureClassVars();

        if(!$this->hasMeasureFilter) {
            $this->measureArray['M6']['VAL']    = "MAX(".$this->maintable.".stock)";
            $this->measureArray['M6']['measureName'] = "STOCK OVER TIME";
            $this->measureArray['M6']['ALIASE'] = "STOCKOVERTIME";
            $this->measureArray['M6']['dataDecimalPlaces']   = 0;

            $this->performanceTabMappings['stockOverTime'] = 'M6';
        }

        $this->dataTable[$this->grouptable]['tables'] = $this->grouptable;
        $this->dataTable[$this->grouptable]['link']   = " AND ".$this->maintable.".GID=".$this->grouptable.".gid AND ".$this->grouptable.".GID IN (".$this->GID.") ";
        
        $this->dateperiod = $this->DatePeriod;
    }
}    
?>