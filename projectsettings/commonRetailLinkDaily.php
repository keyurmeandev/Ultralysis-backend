<?php
namespace projectsettings;

class commonRetailLinkDaily extends baseRetailLinkDaily {
	/*
		Allowed settingData Keys...
			$settingData['accountID'];
			$settingData['projectID'];
			$settingData['maintable'];
			$settingData['clientID'];
			$settingData['gId'] = '';
			$settingData['forceUseOriginalLink'] = '';
			$settingData['retaillinkdctable'] = '';
	*/
    public function __construct($settingData)
    {
    	if(empty($settingData['accountID']) || empty($settingData['projectID']) || empty($settingData['clientID']))
            exit(json_encode(array('access' => 'unothorized')));

        $this->clientID             = $settingData['clientID'];
		$this->maintable            = (!isset($settingData['maintable']) ? strtolower($settingData['clientID']).'_retail_link_daily_14' : $settingData['maintable'] );

		if(isset($settingData['gId']) && !empty($settingData['gId']))
        	$this->GID = $settingData['gId'];

        if(isset($settingData['isretaillinkdctable']) && $settingData['isretaillinkdctable'] == true)
            $this->retaillinkdctable = (!isset($settingData['retaillinkdctable']) ? strtolower($settingData['clientID']).'_retail_link_dc' : $settingData['retaillinkdctable'] );

        if(isset($settingData['forceUseOriginalLink']) && !empty($settingData['forceUseOriginalLink']))
            $this->forceUseOriginalLink = $settingData['forceUseOriginalLink'];

        if(isset($settingData['accountTable']) && !empty($settingData['accountTable']))
        	$this->accounttable = $settingData['accountTable'];

        parent::__construct($settingData['accountID'],$settingData['projectID']);

        if (isset($this->accounttable) && !empty($this->accounttable)) {
	        $this->accountLink = " AND ".$this->accounttable.".GID IN (".$this->GID.") AND ".$this->maintable.".GID = ".$this->accounttable.".gid ";
	        $this->accountHelperLink = " WHERE ".$this->accounttable.".GID IN (".$this->GID.") ";
        }
        
        $this->InstockSummaryStoreListDLCol = true; //Instock summary page the Store list dl coloum always set to TRUE
        if(isset($settingData['InstockSummaryStoreListDLCol']))
            $this->InstockSummaryStoreListDLCol = $settingData['InstockSummaryStoreListDLCol'];     

		if(isset($settingData['myStockByProductStockedStoresCol']))
			$this->myStockByProductStockedStoresCol = $settingData['myStockByProductStockedStoresCol'];

		$this->fetchProductAndMarketFilterOnTabClick = true;

		$this->dateperiod = $this->DatePeriod;
		$this->configureClassVars();
    }
}
?>