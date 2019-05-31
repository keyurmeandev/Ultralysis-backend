<?php
namespace projectsettings;

class ferreroRelayPlus extends baseMultsSummary {

    public function __construct($settingData) {
    	if(empty($settingData['accountID']) || empty($settingData['uid']) || empty($settingData['projectID']) || empty($settingData['clientID']))
            exit(json_encode(array('access' => 'unothorized')));

        $this->maintable            = (!isset($settingData['maintable']) ? strtolower($settingData['clientID']).'_mults_summary' : $settingData['maintable'] );
		$this->clientID             = $settingData['clientID'];
        $this->footerCompanyName    = isset($settingData['footerCompanyName']) ? $settingData['footerCompanyName'] : '';

        if(isset($settingData['gId']) && !empty($settingData['gId']))
            $this->GID = $settingData['gId'];

        $this->isShowCustomerLogo = true;
        if (isset($settingData['isShowCustomerLogo']) && !empty($settingData['isShowCustomerLogo']))
            $this->isShowCustomerLogo = $settingData['isShowCustomerLogo'];

        if (isset($settingData['extraVars']) && !empty($settingData['extraVars'])) {
            foreach($settingData['extraVars'] as $key => $data)
                $this->$key = $data;
        }

        // For TSD Mults summary
        if (isset($settingData['forceUseOriginalLink']) && !empty($settingData['forceUseOriginalLink']))
            $this->forceUseOriginalLink = $settingData['forceUseOriginalLink'];            
            
		parent::__construct($settingData['accountID'], $settingData['uid'], $settingData['projectID']);

        $this->getClientAndRetailerLogo($settingData);

        if (isset($settingData['isSifPageRequired']))
            $this->isSifPageRequired = $settingData['isSifPageRequired'];

        /*** 
         * To enable new flow for pages 
         * >> Product and Market filter on tab click
         * >> Added Sticky Filter for Product and Market filter
         * >> Added logic for clean dom object and rebuild page
         * >> All above features controlled by this single flag
        ***/
        $this->fetchProductAndMarketFilterOnTabClick = true;
        
        $this->fetchAllMeasureForSummaryPerformanceInBox = false;
		$this->configureClassVars();
    }

    public function changeOnlineMeasure() {

        $this->maintable         = "ferrero_asda_online";
        $this->summarytable      = "ferrero_mults_summary";
        $this->multsSummaryAsdaOnlinePeriodTable = "mults_summary_asda_online_period_list";
        $this->clientID          = "FERRERO";     
        $this->GID               = 2;
        $this->dateperiod        = "period";
        $this->ProjectValue      = $this->maintable . ".value";
        $this->ProjectVolume     = "";
        $this->measureTypeField  = $this->maintable . ".measure_type";
        $this->pinField          = $this->skutable.".PIN";
        $this->pnameField        = $this->skutable.".PNAME";

        $commontables   = $this->maintable . "," . $this->timetable;
        $commonlink     = " WHERE $this->maintable.accountID=$this->aid " .
                        "AND " . $this->maintable . ".".$this->dateperiod."=" . $this->timetable . ".".$this->dateperiod." " .
                        "AND $this->maintable.GID=$this->timetable.gid " .
                        "AND $this->timetable.gid IN (".$this->GID.") ";

        $skulink        = "AND $this->maintable.PIN=$this->skutable.PIN " .
                        "AND $this->maintable.GID=$this->skutable.GID " .
                        "AND $this->skutable.hide<>1 " .
                        "AND $this->skutable.gid IN (".$this->GID.") ".
                        "AND $this->skutable.clientID='$this->clientID' ";

        $this->timeHelperLink = " WHERE " . $this->timetable.".GID IN (".$this->GID.") ".
                                " AND ". $this->timetable . "." . $this->dateperiod . 
                                " IN ( SELECT DISTINCT " . $this->maintable . "." . $this->dateperiod . 
                                    " FROM " . $this->maintable . " WHERE ".$this->maintable.".GID IN (".$this->GID.") ) ";

        if($this->hasHiddenSku()) {
            $commontables .= ", ".$this->skutable;
            $commonlink   .= $skulink;
            $skulink       = '';
        }

        $this->dataTable['default']['tables']      = $commontables;
        $this->dataTable['default']['link']        = $commonlink;

        $this->dataTable['product']['link']        = $skulink;

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
        
    }
}
?>