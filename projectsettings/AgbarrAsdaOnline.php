<?php

namespace projectsettings;

class AgbarrAsdaOnline extends baseMultsSummary {

    public function __construct($accountID,$uid,$projectID) {
		
        $this->maintable            		= "agbarr_asda_online";
        $this->summarytable                 = "agbarr_mults_summary";
        $this->clientID             		= "AGBARR";     
		$this->GID							= 2;
        $this->footerCompanyName    		= "AG Barr";
		
		parent::__construct($accountID,$uid,$projectID);

        $this->getClientAndRetailerLogo();

		$this->dateperiod = "period";
        $this->ProjectValue   = $this->maintable . ".value";
        $this->ProjectVolume  = "";
        $this->measureTypeField  = $this->maintable . ".measure_type";
		$this->pinField = $this->skutable.".PIN";
		$this->pnameField = $this->skutable.".PNAME";		
        
		$this->configureClassVars();
        
        if(!$this->hasMeasureFilter) {
            // measure selection list
            $this->pageArray["MEASURE_SELECTION_LIST"] = array(
                array('measureID' => 1, 'jsonKey'=>'ONLINEVALUE', 'measureName' => 'Online Value', 'selected' => false),
                array('measureID' => 2, 'jsonKey'=>'ONLINESUBSTITUTEDVOLUME', 'measureName' => 'Online Substituted Volume', 'selected' => false),
                array('measureID' => 3, 'jsonKey'=>'ONLINEORDEREDVOLUME', 'measureName' => 'Online Ordered Volume', 'selected' => false),
                array('measureID' => 4, 'jsonKey'=>'ONLINENILPICKEDVOLUME', 'measureName' => 'Online Nil Picked Volume', 'selected' => false),
                array('measureID' => 5, 'jsonKey'=>'ONLINEDELIVEREDVOLUME', 'measureName' => 'Online Delivered Volume', 'selected' => true)
            );

            $this->measureArray = array();
            
            $this->measureArray['M1']['VAL']    = $this->ProjectValue;
            $this->measureArray['M1']['ALIASE'] = "ONLINEVALUE";
            $this->measureArray['M1']['attr']   = "SUM";
            $this->measureArray['M1']['CASE_WHEN']   = " AND ".$this->measureTypeField." = 'ONLINE_VALUE' ";
            
            $this->measureArray['M2']['VAL']    = $this->ProjectValue;
            $this->measureArray['M2']['ALIASE'] = "ONLINESUBSTITUTEDVOLUME";
            $this->measureArray['M2']['attr']   = "SUM";
            $this->measureArray['M2']['CASE_WHEN']   = " AND ".$this->measureTypeField." = 'ONLINE_SUBSTITUTED_VOLUME' ";
            
            $this->measureArray['M3']['VAL']    = $this->ProjectValue;
            $this->measureArray['M3']['ALIASE'] = "ONLINEORDEREDVOLUME";
            $this->measureArray['M3']['attr']   = "SUM";
            $this->measureArray['M3']['CASE_WHEN']   = " AND ".$this->measureTypeField." = 'ONLINE_ORDERED_VOLUME' ";
            
            $this->measureArray['M4']['VAL']    = $this->ProjectValue;
            $this->measureArray['M4']['ALIASE'] = "ONLINENILPICKEDVOLUME";
            $this->measureArray['M4']['attr']   = "SUM";
            $this->measureArray['M4']['CASE_WHEN']   = " AND ".$this->measureTypeField." = 'ONLINE_NIL_PICKED_VOLUME' ";

            $this->measureArray['M5']['VAL']    = $this->ProjectValue;
            $this->measureArray['M5']['ALIASE'] = "ONLINEDELIVEREDVOLUME";
            $this->measureArray['M5']['attr']   = "SUM";
            $this->measureArray['M5']['CASE_WHEN']   = " AND ".$this->measureTypeField." = 'ONLINE_DELIVERED_VOLUME' ";

            
            $this->performanceTabMappings = array(
                "drillDown" => 'M1',
                "overTime" => 'M1'/*,
                "priceOverTime" => 'M3',
                "distributionOverTime" => 'M4',
                "stockOverTime" => 'M5',
                "availabilityOverTime" => 'M6'*/
            );
        }
		
    } 
}

?>