<?php
namespace classes;
use db;
use filters;
use config;

class SalesVStockScatterSku extends config\UlConfig{
    /*****
    * Default gateway function, should be similar in all DATA CLASS
    * arguments:
    *  $settingVars [project settingsGateway variables]
    * @return $this->xmlOutput with all data that should be sent to client app
    *****/
    public function go($settingVars){
	$this->initiate($settingVars);//INITIATE COMMON VARIABLES
	$this->queryPart = $this->getAll();
	filters\timeFilter::grabLatestPeriod($_REQUEST["TimeFrame"] , $this->settingVars);
	$this->prepareData();
	
	return $this->xmlOutput;
    }
    
    
    private function prepareData(){
	$dataID 	= key_exists('ID' , $this->settingVars->dataArray[$_REQUEST['ACCOUNT']]) ? $this->settingVars->dataArray[$_REQUEST['ACCOUNT']]['ID'] : $this->settingVars->dataArray[$_REQUEST['ACCOUNT']]['NAME'];
	$dataACCOUNT 	= $this->settingVars->dataArray[$_REQUEST['ACCOUNT']]['NAME'];

	$this->valueFunc($dataID); 
	$this->getGridData($dataID , $dataACCOUNT , "gridData");  //ADDING TO OUTPUT  
    }
    
    
    private function getGridData($id , $name , $xmlTag){
	global $ohqArr;
	    
	$query 		= "SELECT COUNT(DISTINCT (CASE WHEN ".$this->settingVars->ProjectVolume.">0 THEN 1 END) * ".$this->settingVars->maintable.".SNO) AS STORES ".
				"FROM ".$this->settingVars->tablename.$this->queryPart." ".
				"AND ".filters\timeFilter::$tyWeekRange." ";
	$result 	= $this->queryVars->queryHandler->runQuery($query,$this->queryVars->linkid,db\ResultTypes::$TYPE_OBJECT);
	$data 		= $result[0];
	$totalStores 	= $data['STORES'];
	
	$query = "SELECT $id AS ID".
			",MAX($name) AS ACCOUNT".
			",COUNT(DISTINCT (CASE WHEN ".$this->settingVars->yearperiod." = " . filters\timeFilter::$ToYear . " AND ".$this->settingVars->ProjectValue.">0 THEN 1 END) * ".$this->settingVars->storetable.".SNO) AS STORES".
			",SUM(".$this->settingVars->ProjectVolume.") AS UNITS ".
			"FROM ".$this->settingVars->tablename.$this->queryPart." AND ".filters\timeFilter::$tyWeekRange." ".
			"GROUP BY ID ".
			"ORDER BY UNITS DESC";
	$result = $this->queryVars->queryHandler->runQuery($query,$this->queryVars->linkid,db\ResultTypes::$TYPE_OBJECT);
	foreach($result as $key=>$data)
	{
	    if($data['UNITS']>0)
		{
		    $tempId		= $data['ID'];
		    $ohq		= $ohqArr[$tempId];
		     
		    $aveUnits	= ($data['STORES']>0) ? (($data['UNITS']/$data['STORES'])/$_REQUEST["TimeFrame"]) : 0;	
		    $aveStock	= ($data['STORES']>0) ? ($ohq/$data['STORES']) : 0;	
		    $stockCover	= ($aveUnits>0) ? ($aveStock/$aveUnits) : 0;
		    
		    
		    $value = $this->xmlOutput->addChild($xmlTag);
		    $value->addChild("ID",		htmlspecialchars_decode($data['ID']));
		    $value->addChild("ACCOUNT",		htmlspecialchars_decode($data['ACCOUNT']));
		    $value->addChild("noOfStores",	$data['STORES']);
		    $value->addChild("salesUnitsLw",	$data['UNITS']);
		    $value->addChild("avgStock",	$aveStock);
		    $value->addChild("stock",		$ohq);
		    $value->addChild("stockCover",	$stockCover);
		    $value->addChild("aveUnits",	$aveUnits);
		}
	}
	$value = $this->xmlOutput->addChild("summary");
	$value->addAttribute ("total_stores",	$totalStores);
    }
    
    
    private function valueFunc($account) {
	//PROVIDING GLOBALS
	global $ohqArr;
	$ohqArr = array();
    
	$qpart  = $this->queryPart." AND ".$this->settingVars->maintable.".".$this->settingVars->period." IN (" . implode("," , filters\timeFilter::getPeriodWithinRange(0 , 1 , $this->settingVars)) . ") ";
	$query 	= "SELECT $account AS ACCOUNT".
			",SUM(OHQ) AS OHQ ".
			"FROM ".$this->settingVars->tablename.$qpart." ".
			"GROUP BY ACCOUNT";
	$result = $this->queryVars->queryHandler->runQuery($query,$this->queryVars->linkid,db\ResultTypes::$TYPE_OBJECT);
	foreach($result as $key=>$data) 
	{
	    $id 		= $data['ACCOUNT'];
	    $ohqArr[$id] 	= $data['OHQ'];
	}    
    }
}
?>