<?php
namespace projectsettings;

class UbFirstHitWeekly extends baseMultsSummary {
	/*
		Allowed settingData Keys...
			$settingData['accountID'];
			$settingData['uid'];
			$settingData['projectID'];
			$settingData['maintable'];
			$settingData['clientID'];
			$settingData['footerCompanyName'];
			$settingData['clientLogo'];
			$settingData['retailerLogo'];
			$settingData['gId'] = '';
			$settingData['forceUseOriginalLink'] = '';
			$settingData['retaillinkdctable'] = '';
	*/
    public function __construct($settingData)
    {
        if(empty($settingData['accountID']) || empty($settingData['uid']) || empty($settingData['projectID']) || empty($settingData['clientID']))
            exit(json_encode(array('access' => 'unothorized')));

        $this->maintable            = 'ub_tesco_po_details';
        $this->tesco_po_details    = 'ub_tesco_po_details';
		$this->clientID             = 'UB';
        $this->footerCompanyName    = isset($settingData['footerCompanyName']) ? $settingData['footerCompanyName'] : '';

        if(isset($settingData['gId']) && !empty($settingData['gId']))
            $this->GID = $settingData['gId'];

        $this->isShowCustomerLogo = true;
        if (isset($settingData['isShowCustomerLogo']))
            $this->isShowCustomerLogo = $settingData['isShowCustomerLogo'];

        if(isset($settingData['extraVars']) && !empty($settingData['extraVars'])){
            foreach($settingData['extraVars'] as $key => $data)
                $this->$key = $data;
        }        

        // For TSD Mults summary
        if(isset($settingData['forceUseOriginalLink']) && !empty($settingData['forceUseOriginalLink']))
            $this->forceUseOriginalLink = $settingData['forceUseOriginalLink'];            
            
		parent::__construct($settingData['accountID'], $settingData['uid'], $settingData['projectID']);

        $this->timeSelectionUnit = "period";

        $this->productHelperLink = " WHERE " . $this->maintable . ".skuID=" . $this->skutable . ".PIN AND clientID='" . $this->clientID . "' AND ".$this->skutable.".GID = ".$this->GID." AND ".$this->maintable.".GID = ".$this->skutable.".gid AND hide=0 ";

        $this->getClientAndRetailerLogo($settingData);
        
        if(isset($settingData['isSifPageRequired']))
            $this->isSifPageRequired = $settingData['isSifPageRequired'];
        
		$this->configureClassVars();

        /*** 
         * To enable new flow for pages 
         * >> Product and Market filter on tab click
         * >> Added Sticky Filter for Product and Market filter
         * >> Added logic for clean dom object and rebuild page
         * >> All above features controlled by this single flag
        ***/
        if($this->projectTypeID == 2)
            $this->fetchProductAndMarketFilterOnTabClick = true;
    }

    public function configureClassVars()
    {
        parent::configureClassVars();
    
        $commontables   = $this->maintable . "," . $this->timetable;
        $commonlink     = " WHERE $this->maintable.accountID=$this->aid " .
                        "AND " . $this->maintable . ".".$this->dateperiod."=" . $this->timetable . ".".$this->dateperiod." " .
                        "AND $this->maintable.GID=$this->timetable.gid " .
                        "AND $this->timetable.gid IN (".$this->GID.") ";

        $skulink        = "AND $this->maintable.skuID=$this->skutable.PIN " .
                        "AND $this->maintable.GID=$this->skutable.GID " .
                        "AND $this->skutable.hide<>1 " .
                        "AND $this->skutable.gid IN (".$this->GID.") ".
                        "AND $this->skutable.clientID='$this->clientID' ";

        $storelink      = ((!empty($this->storetable)) ? " AND $this->maintable.SNO=$this->storetable.SNO " .
                        "AND $this->maintable.GID=$this->storetable.GID " .
                        "AND $this->storetable.gid IN (".$this->GID.") " : "" );
                        
        $this->copy_link     = $this->link     = $commonlink.$storelink.$skulink;

        $this->timeHelperTables = $this->timetable;
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

        $this->dataTable['product']['tables']      = $this->skutable;
        $this->dataTable['product']['link']        = $skulink;
    }
}

?>