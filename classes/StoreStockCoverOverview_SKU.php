<?php
namespace classes;
use db;
use filters;
use config;

class StoreStockCoverOverview_SKU extends config\UlConfig{
    /*****
    * Default gateway function, should be similar in all DATA CLASS
    * arguments:
    *  $settingVars [project settingsGateway variables]
    * @return $xmlOutput with all data that should be sent to client app
    *****/
    public function go($settingVars){
	$this->initiate($settingVars);//INITIATE COMMON VARIABLES
	filters\timeFilter::grabLatestPeriod($_REQUEST["TimeFrame"] , $this->settingVars);   
	$this->queryPart = $this->getAll();

	$this->prepareGridData(); //ADDING TO OUTPUT
	
	return $this->xmlOutput;
    }
    
    
    private function prepareGridData(){	
	$dataViewOption 	= $_REQUEST['dataViewOption'];
	$dataView_ID 		= key_exists('ID' , $this->settingVars->dataArray[$dataViewOption]) ? $this->settingVars->dataArray[$dataViewOption]['ID'] : $this->settingVars->dataArray[$dataViewOption]['NAME'];
	$dataView_ACCOUNT 	= $this->settingVars->dataArray[$dataViewOption]['NAME'];

	$this->valueFunc($dataView_ID); 
	$this->getGridData($dataView_ID , $dataView_ACCOUNT , "gridData"); //ADDING TO OUTPUT   
	
	return $this->xmlOutput;
    }
    
    
    private function getGridData($id , $name , $xmlTag){
	global $ohqArr;	
	$query 		= "SELECT COUNT(DISTINCT (CASE WHEN ".$this->settingVars->ProjectVolume.">0 THEN 1 END) * ".$this->settingVars->maintable.".SNO) AS STORES ".
				"FROM ".$this->settingVars->tablename.$this->queryPart." ".
				"AND ".filters\timeFilter::$tyWeekRange;
	$result 	= $this->queryVars->queryHandler->runQuery($query,$this->queryVars->linkid,db\ResultTypes::$TYPE_OBJECT);
	$data 		= $result[0];
	$totalStores 	= $data['STORES'];
    
		
	$query 	    	= "SELECT $id AS ID".
			    ",MAX($name) AS ACCOUNT".
			    ",COUNT(DISTINCT (CASE WHEN ".$this->settingVars->ProjectVolume.">0 THEN 1 END) * ".$this->settingVars->storetable.".SNO) AS STORES".
			    ",SUM(".$this->settingVars->ProjectVolume.") AS UNITS ".
			    "FROM  ".$this->settingVars->tablename.$this->queryPart." AND ".filters\timeFilter::$tyWeekRange." ".
			    "GROUP BY ID ".
			    "ORDER BY UNITS DESC";    
	$result     	= $this->queryVars->queryHandler->runQuery($query,$this->queryVars->linkid,db\ResultTypes::$TYPE_OBJECT);
	foreach($result as $key=>$data)
	{
	    if($data['UNITS']>0){
		    $idVal	= ($id!="") ? $data['ID'] : $data['ACCOUNT'];
		    $ohq	= $ohqArr[$idVal];
		    $aveUnits	= $data['STORES']>0 ? (($data['UNITS']/$data['STORES'])/$_REQUEST["TimeFrame"]) : 0;	
		    $aveStock	= $data['STORES']>0 ? ($ohq/$data['STORES']) : 0;	
		    $stockCover	= $aveUnits>0 ? ($aveStock/$aveUnits) : 0;
			  
		    $value = $this->xmlOutput->addChild($xmlTag);
		    if($id!="") $value->addChild("ID", 		$data['ID']);
		    $value->addChild("ACCOUNT", 		htmlspecialchars_decode($data['ACCOUNT']));
		    $value->addChild("noOfStores", 		$data['STORES']);
		    $value->addChild("salesUnitsLw", 		$data['UNITS']);
		    $value->addChild("avgStock", 		$aveStock);
		    $value->addChild("stock", 			$ohq);
		    $value->addChild("stockCover", 		$stockCover);
		    $value->addChild("aveUnits", 		$aveUnits);
	    }
	}
	
	
	$value = $this->xmlOutput->addChild("summary");
	$value->addAttribute ("total_stores",	$totalStores);
    }
    
    
    private function valueFunc($account) {
	//PROVIDING GLOBALS
	global $ohqArr;
	$ohqArr = array();
	
	$qpart 	 = $this->queryPart." AND ".$this->settingVars->maintable.".".$this->settingVars->period." IN (" . implode("," , filters\timeFilter::getPeriodWithinRange(0 , 1 , $this->settingVars)) . ") ";
	$query 	 = "SELECT $account AS ACCOUNT".
			",SUM(OHQ) AS OHQ ".
			"FROM ".$this->settingVars->tablename.$qpart." ".
			"GROUP BY ACCOUNT";
	$result  = $this->queryVars->queryHandler->runQuery($query,$this->queryVars->linkid,db\ResultTypes::$TYPE_OBJECT);
	foreach($result as $key=>$data) 
	{
	    $ohqArr[$data['ACCOUNT']] 	= $data['OHQ'];
	}   
    }    
}
?>