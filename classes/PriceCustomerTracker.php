<?php
namespace classes;

use projectsettings;
use SimpleXMLElement;
use datahelper;
use filters;
use db;
use config;

class PriceCustomerTracker extends config\UlConfig{
    /*****
    * Default gateway function, should be similar in all DATA CLASS
    * arguments:
    *  $settingVars [project settingsGateway variables]
    * @return $xmlOutput with all data that should be sent to client app
    *****/
    public function go($settingVars){
	$this->initiate($settingVars); //INITIATE COMMON VARIABLES
	$this->queryPart    = $this->getAll();
	
	$action = $_REQUEST["action"];
	switch ($action)
	{
	    case "reload":	return $this->reload();		break;
	    case "skuchange": 	return $this->changeSku();	break;
	}
    }
    
    private function reload(){
	$secondAccount = !key_exists('ID',$this->settingVars->dataArray[$_REQUEST["SECOND_ACCOUNT"]]) ? $this->settingVars->dataArray[$_REQUEST["SECOND_ACCOUNT"]]["NAME"]:$this->settingVars->dataArray[$_REQUEST["SECOND_ACCOUNT"]]["ID"];
    
	$this->chartData($secondAccount);
	$this->gridData($secondAccount);
	
	return $this->xmlOutput;
    }
    
    private function changeSku(){
	$secondAccount = !key_exists('ID',$this->settingVars->dataArray[$_REQUEST["SECOND_ACCOUNT"]]) ? $this->settingVars->dataArray[$_REQUEST["SECOND_ACCOUNT"]]["NAME"]:$this->settingVars->dataArray[$_REQUEST["SECOND_ACCOUNT"]]["ID"];
    
	$this->chartData($secondAccount);
	
	return $this->xmlOutput;
    }
    
    private function chartData($secondAccount){
	$value = "";
	$query = "SELECT CONCAT(". $this->settingVars->weekperiod .",'-',". $this->settingVars->yearperiod .") AS PERIOD, ".
			"SUM(". $this->settingVars->ProjectValue .") AS SALES, ".
			"SUM(". $this->settingVars->ProjectVolume .") AS UNITS, ".
			"SUM(". $secondAccount .") AS SA_SUM ". // SECOND ACCOUNT SUM
			"FROM ".$this->settingVars->tablename.$this->queryPart." AND ". filters\timeFilter::$tyWeekRange ." ".
			"GROUP BY PERIOD,".$this->settingVars->weekperiod.",".$this->settingVars->yearperiod." ".
			"ORDER BY ".$this->settingVars->yearperiod." ASC,".$this->settingVars->weekperiod." ASC";
	$result = $this->queryVars->queryHandler->runQuery($query,$this->queryVars->linkid,db\ResultTypes::$TYPE_OBJECT);
	foreach($result as $key=>$data)
	{
	    $avePriceP = 0.00;
	    if($data['UNITS']>0) $avePriceP = ($data['SALES']/$data['UNITS'])*100;
	    $value = $this->xmlOutput->addChild('chartData');
			$value->addChild('ACCOUNT',		$data['PERIOD']);
			$value->addChild('SALES',		$data['SALES']);
			$value->addChild('UNITS',		$data['UNITS']);
			$value->addChild('AVEPP',		number_format($avePriceP,0,".",""));
			$value->addChild('SECOND_ACCOUNT',	$data['SA_SUM']);
	}  
    }
    
    private function gridData($secondAccount){
	$skuID 		= !key_exists('ID',$this->settingVars->dataArray[$_REQUEST['ACCOUNT']]) ? $this->settingVars->dataArray[$_REQUEST['ACCOUNT']]['NAME'] : $this->settingVars->dataArray[$_REQUEST['ACCOUNT']]['ID'];
	$skuName 	= $this->settingVars->dataArray[$_REQUEST['ACCOUNT']]['NAME'];
	
	$query = "SELECT $skuID AS ID, ".
			"$skuName AS ACCOUNT, ".
			"SUM(".$this->settingVars->ProjectValue.") AS SALES, ".
			"SUM(".$this->settingVars->ProjectVolume.") AS UNITS, ".
			"COUNT( DISTINCT (CASE WHEN ".$this->settingVars->ProjectValue.">0 THEN ".$secondAccount." END)) AS DIST ".
			"FROM ".$this->settingVars->tablename.$this->queryPart." AND ".filters\timeFilter::$tyWeekRange." ".
			"GROUP BY ID,ACCOUNT ".
			"ORDER BY SALES DESC";
	$result = $this->queryVars->queryHandler->runQuery($query,$this->queryVars->linkid,db\ResultTypes::$TYPE_OBJECT);
	foreach($result as $key=>$data)
	{
	    $avePriceP = 0.00;
	    if($data['SALES']>0) $avePriceP = ($data['SALES']/$data['UNITS'])*100;
	    $value = $this->xmlOutput->addChild('gridData');
			$value->addChild('ID',		$data['ID']);
			$value->addChild('ACCOUNT',	htmlspecialchars_decode($data['ACCOUNT']));
			$value->addChild('SALES',	$data['SALES']);
			$value->addChild('UNITS',	$data['UNITS']);
			$value->addChild('AVEPP',	number_format($avePriceP,0,".",","));
	} 
    }
    
    /*****
    * COLLECTS TABLE JOINING STRING FROM PROJECT SETTINGS
    * ALSO APPLYS FILTER CONDITIONS TO THE STRING
    * AND RETURNS $tablejoins_and_filters
    *****/   
    public function getAll(){    
	$tablejoins_and_filters       	 = $this->settingVars->link; 
	
	if($_REQUEST['SKU']!="")
	{
	    $skuID 			 = !key_exists('ID',$this->settingVars->dataArray[$_REQUEST['ACCOUNT']]) ? $this->settingVars->dataArray[$_REQUEST['ACCOUNT']]['NAME'] : $this->settingVars->dataArray[$_REQUEST['ACCOUNT']]['ID'];
	    $tablejoins_and_filters	.=" AND $skuID='".$_REQUEST['SKU']."' ";
	} 
		      
	if (isset($_REQUEST["FS"]) && $_REQUEST["FS"] != ''){
           $tablejoins_and_filters   	.= filters\productAndMarketFilter::include_product_and_market_filters($this->queryVars,$this->settingVars);
        }
    
	return $tablejoins_and_filters;
    }
}

?> 