<?php
namespace projectsettings;

class RbTsdMultsSummary extends baseMultsSummary {

    public function __construct($accountID,$uid,$projectID) {
		
        $this->maintable            = "rb_mults_summary";
        $this->clientID             = "RB";     
		$this->GID					= 1;
        $this->footerCompanyName    = "RB";
		
		parent::__construct($accountID,$uid,$projectID);

        $this->getClientAndRetailerLogo();

		$this->dateperiod           = "period";
		
		$this->configureClassVars();

        if(!$this->hasMeasureFilter) {
            // measure selection list
            $this->pageArray["MEASURE_SELECTION_LIST"] = array(
                array('measureID' => 1, 'jsonKey'=>'VALUE', 'measureName' => 'Value ('.$this->currencySign.')'),
                array('measureID' => 2, 'jsonKey'=>'VOLUME', 'measureName' => 'Units'),
                array('measureID' => 3, 'jsonKey'=>'TABLETS', 'measureName' => 'Tablets'),
            );
            
            if($this->projectTypeID == 6) { // DDB
                $this->pageArray["MEASURE_SELECTION_LIST"] = array(
                    array('measureID' => 1, 'jsonKey'=>'VALUE', 'measureName' => 'Value ('.$this->currencySign.')', 'selected' => true),
                    array('measureID' => 2, 'jsonKey'=>'VOLUME', 'measureName' => 'Units', 'selected' => true)
                );
                
                $this->outputDateOptions = array(
                    array("id" => "MYDATE", "value" => "My Date", "selected" => false),
                    array("id" => "YEAR", "value" => "Year", "selected" => false),
                    array("id" => "WEEK", "value" => "Week", "selected" => false),
                    array("id" => "PERIOD", "value" => "PERIOD", "selected" => false)
                );
                
            } 
    		
            $this->measureArray['M3']['VAL'] = "(IFNULL(".$this->ProjectVolume.",0)*IFNULL(".$this->skutable.".agg_int,1))";
            $this->measureArray['M3']['ALIASE'] = "TABLETS";
            $this->measureArray['M3']['attr'] = "SUM";
            
            $this->measureArray['M4']['VAL'] = "(SUM(IFNULL(".$this->ProjectValue.",0))/SUM(IFNULL(".$this->ProjectVolume.",1)))";
            $this->measureArray['M4']['ALIASE'] = "PRICE";
            $this->measureArray['M4']['attr']   = "PRICE";
            $this->measureArray['M4']['dataDecimalPlaces'] = 2;
                                    
            $this->measureArray['M5']['VAL'] = "MAX(store_count)";
            $this->measureArray['M5']['ALIASE'] = "DISTRIBUTION";
            $this->measureArray['M5']['attr']   = "DISTRIBUTION";
            $this->measureArray['M5']['dataDecimalPlaces'] = 0;
    		
            $this->measureArray['M6']['VAL'] = "AveStoreStock";
            $this->measureArray['M6']['ALIASE'] = "STOCK";
            $this->measureArray['M6']['attr'] = "SUM";

            $this->performanceTabMappings = array(
                "drillDown" => 'M1',
                "overTime" => 'M1',
                "priceOverTime" => 'M4',
                "distributionOverTime" => 'M5',
                "stockOverTime" => 'M6'
            );
        }
    } 
}
?>