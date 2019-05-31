<?php
namespace projectsettings;

class commonTescoStoreDaily extends BaseTsdDailyReport {
	/*
		Allowed settingData Keys...
			$settingData['accountID'];
			$settingData['projectID'];
			$settingData['maintable'];
			$settingData['tesco_depot_daily'];
			$settingData['rangedtable'];
			$settingData['clientID'];
	*/
    public function __construct($settingData)
    {
    	if(empty($settingData['accountID']) || empty($settingData['projectID']) || empty($settingData['clientID']))
            exit(json_encode(array('access' => 'unothorized')));

        $this->clientID			    		= $settingData['clientID'];
		$this->maintable            = (!isset($settingData['maintable']) ? strtolower($settingData['clientID']).'_tesco_store_daily_14' : $settingData['maintable'] );
        $this->tesco_depot_daily    = (!isset($settingData['tesco_depot_daily']) ? strtolower($settingData['clientID']).'_tesco_depot_daily' : $settingData['tesco_depot_daily'] );
        $this->tesco_po_details    = (!isset($settingData['tesco_po_details']) ? strtolower($settingData['clientID']).'_tesco_po_details' : $settingData['tesco_po_details'] );
        
        if(isset($settingData['isRangedtable']) && $settingData['isRangedtable'] == true)
            $this->rangedtable      = (!isset($settingData['rangedtable']) ? strtolower($settingData['clientID']).'_tesco_ranged' : $settingData['rangedtable'] );

        parent::__construct($settingData['accountID'],$settingData['projectID']);
		$this->dateperiod			= $this->DatePeriod;
		$this->configureClassVars();
        
        // //[START] LOAD DDB SETTING
        // if($this->projectTypeID == 6) // DDB
        //     $this->setDynamicDataBuilderSetting();
        
    }
}
?>