<?php

namespace classes;

use datahelper;
use projectsettings;
use filters;
use utils;
use db;
use config;

class NewConceptTestPage extends config\UlConfig {
    public $timeFrame;
    public $allCsvFields;

    /*****
     * Default gateway function, should be similar in all DATA CLASS
     * arguments:
     *  $settingVars [project settingsGateway variables]
     * @return jsonOutput with all data that should be sent to client app
    *****/
    public function go($settingVars) {
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES
	$this->queryPart    = $this->getAll();
	
	$this->gridData();
	
	return $this->jsonOutput;
    }
    
    private function gridData(){
        
	//Product Details
	$query 		= "SELECT ". $this->settingVars->dateField ." AS MYDATE ".
				",".$this->settingVars->skutable.".PIN AS PRODUCTID".
                                ",".$this->settingVars->skutable.".PNAME AS PRODUCTNAME ".
                                ",COUNT(". $this->settingVars->storetable .".SNO) AS STORECOUNT ".
                                ",SUM(". $this->settingVars->ProjectValue .") AS SALES ".
				"FROM ". $this->settingVars->tablename . $this->queryPart." ".
				"GROUP BY MYDATE,PRODUCTID,PRODUCTNAME ".
				"ORDER BY MYDATE,PRODUCTID,PRODUCTNAME ";
        
        
        $product_result = $this->queryVars->queryHandler->runQuery($query,$this->queryVars->linkid,db\ResultTypes::$TYPE_OBJECT);
        $this->jsonOutput['productlist'] = $product_result;  
        
        //Sales Details
	$query 		= "SELECT ". $this->settingVars->dateField ." AS MYDATE ".
				",".$this->settingVars->storetable.".SNO AS STOREID".
                                ",".$this->settingVars->storetable.".SNAME AS STORENAME ".
                                ",SUM(". $this->settingVars->ProjectValue .") AS SALES ".
				"FROM ". $this->settingVars->tablename . $this->queryPart." ".
				"GROUP BY MYDATE,STOREID,STORENAME ".
				"ORDER BY MYDATE,STOREID,STORENAME ";
        
        
	$store_result = $this->queryVars->queryHandler->runQuery($query,$this->queryVars->linkid,db\ResultTypes::$TYPE_OBJECT);
        $this->jsonOutput['storelist'] = $store_result; 
    }
}
?>