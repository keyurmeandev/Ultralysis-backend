<?php
namespace projectsettings;

class commonMultsSummary extends baseMultsSummary {
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

        $this->maintable            = (!isset($settingData['maintable']) ? strtolower($settingData['clientID']).'_mults_summary' : $settingData['maintable'] );
        $this->tesco_po_details    = (!isset($settingData['tesco_po_details']) ? strtolower($settingData['clientID']).'_tesco_po_details' : $settingData['tesco_po_details'] );
		$this->clientID             = $settingData['clientID'];
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
}

?>