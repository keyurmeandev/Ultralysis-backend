<?php
namespace classes;

use filters;
use db;
use config;

class SalesVStockScatterStore extends config\UlConfig{
    public function go($settingVars){
	$this->initiate($settingVars);//INITIATE COMMON VARIABLES	
	$action = $_REQUEST["action"];
	switch ($action){
	    case "reload":	return $this->Reload();		break;
	    case "changesku": 	return $this->skuChange();	break;
	}
    }
   
    private function Reload(){	
	filters\timeFilter::grabLatestPeriod($_REQUEST["TimeFrame"] , $this->settingVars);
	    
	$this->queryPart = $this->getAll(); //USES OWN getAll FUNCTION
	
	//PREPARE GRID DATA
	$grid_ID 	= key_exists('ID' , $this->settingVars->dataArray[$_REQUEST['GRID_ACCOUNT']]) ? $this->settingVars->dataArray[$_REQUEST['GRID_ACCOUNT']]['ID'] : $this->settingVars->dataArray[$_REQUEST['GRID_ACCOUNT']]['NAME'];
	$grid_ACCOUNT 	= $this->settingVars->dataArray[$_REQUEST['GRID_ACCOUNT']]['NAME']; 
	$this->valueFunc($grid_ID); 
	$this->getGridData($grid_ID , $grid_ACCOUNT , "gridData"); //ADDING TO OUTPUT
	
	//PREPARE CHART DATA
	$chart_ID 	= key_exists('ID' , $this->settingVars->dataArray[$_REQUEST['CHART_ACCOUNT']]) ? $this->settingVars->dataArray[$_REQUEST['CHART_ACCOUNT']]['ID'] : $this->settingVars->dataArray[$_REQUEST['CHART_ACCOUNT']]['NAME'];
	$chart_ACCOUNT 	= $this->settingVars->dataArray[$_REQUEST['CHART_ACCOUNT']]['NAME']; 
	$this->valueFunc($chart_ID); 
	$this->getGridData($chart_ID , $chart_ACCOUNT , "chartData"); //ADDING TO OUTPUT
	
	return $this->xmlOutput;
    }

    private function skuChange(){
	filters\timeFilter::grabLatestPeriod($_REQUEST["TimeFrame"] , $this->settingVars);
	$this->queryPart = $this->getAll(); //USES OWN getAll FUNCTION
	
	//ON GRID-CHANGE, PREPARE ONLY CHART DATA
	$chart_ID 		= key_exists('ID' , $this->settingVars->dataArray[$_REQUEST['CHART_ACCOUNT']]) ? $this->settingVars->dataArray[$_REQUEST['CHART_ACCOUNT']]['ID'] : $this->settingVars->dataArray[$_REQUEST['CHART_ACCOUNT']]['NAME'];
	$chart_ACCOUNT 	= $this->settingVars->dataArray[$_REQUEST['CHART_ACCOUNT']]['NAME']; 
	$this->valueFunc($chart_ID); 
	$this->getGridData($chart_ID , $chart_ACCOUNT , "chartData"); //ADDING TO OUTPUT   
    
	return $this->xmlOutput;
    }

    private function getGridData($id , $name , $xmlTag){
	global $ohqArr;		
	$query 		= "SELECT COUNT(DISTINCT (CASE WHEN ".$this->settingVars->ProjectVolume.">0 THEN 1 END) * ".$this->settingVars->maintable.".SNO) AS STORES ".
				    "FROM ".$this->settingVars->tablename.$this->queryPart." AND ".filters\timeFilter::$tyWeekRange;
	$result 	= $this->queryVars->queryHandler->runQuery($query,$this->queryVars->linkid,db\ResultTypes::$TYPE_OBJECT);
	$data 		= $result[0];
	$totalStores 	= $data['STORES'];
		    
	$query 		= "SELECT $id AS ID, ".
				"MAX($name) AS ACCOUNT, ".
				"COUNT(DISTINCT (CASE WHEN ".$this->settingVars->yearperiod." = " . filters\timeFilter::$ToYear . " AND ".$this->settingVars->ProjectValue.">0 THEN 1 END) * ".$this->settingVars->storetable.".SNO) AS STORES, ".
				"SUM(".$this->settingVars->ProjectVolume.") AS UNITS ".
				"FROM ".$this->settingVars->tablename.$this->queryPart." AND ".filters\timeFilter::$tyWeekRange." ".
				"GROUP BY ID ".
				"ORDER BY UNITS DESC";	    
	$result 	= $this->queryVars->queryHandler->runQuery($query,$this->queryVars->linkid,db\ResultTypes::$TYPE_OBJECT);
	foreach($result as $key=>$data)
	{
	    if($data['UNITS']>0)
	    {
		$ohq		= $ohqArr[$data['ID']];
		$aveUnits	= ($data['STORES']>0) ? (($data['UNITS']/$data['STORES'])/$_REQUEST["TimeFrame"]) : 0;	
		$aveStock	= ($data['STORES']>0) ? ($ohq/$data['STORES']) : 0;	
		$stockCover	= ($aveUnits>0) ? ($aveStock/$aveUnits) : 0;
		
		$value = $this->xmlOutput->addChild($xmlTag);
		$value->addChild("ID",			htmlspecialchars_decode($data['ID']));
		$value->addChild("ACCOUNT",		htmlspecialchars_decode($data['ACCOUNT']));
		$value->addChild("noOfStores",		$data['STORES']);
		$value->addChild("salesUnitsLw",	$data['UNITS']);
		$value->addChild("avgStock",		$aveStock);
		$value->addChild("stock",		$ohq);
		$value->addChild("stockCover",		$stockCover);
		$value->addChild("aveUnits",		$aveUnits);
	    }
	}
	
	$value = $this->xmlOutput->addChild($xmlTag."_summary");
	$value->addAttribute("total_stores",$totalStores);
    }

    private function valueFunc($account){
	//PROVIDING GLOBALS
	global $ohqArr; 
	$ohqArr = array();
    
	$qpart      = $this->queryPart." AND ".$this->settingVars->maintable.".".$this->settingVars->period." IN (" . implode("," , filters\timeFilter::getPeriodWithinRange(0 , 1 , $this->settingVars)) . ") ";
	$query 	    = "SELECT $account AS ACCOUNT".
			",SUM(OHQ) AS OHQ ".
			"FROM ".$this->settingVars->tablename.$qpart." ".
			"GROUP BY ACCOUNT";
	$result     = $this->queryVars->queryHandler->runQuery($query,$this->queryVars->linkid,db\ResultTypes::$TYPE_OBJECT);
	foreach($result as $key=>$data)
	{
	    $ohqArr[$data['ACCOUNT']] 	= $data['OHQ'];
	}
    }


    /**** OVERRIDING PARENT CLASS'S getAll FUNCTION ****/
    public function getAll(){
	$tablejoins_and_filters       	 = $this->settingVars->link;
	
	if (isset($_REQUEST["FS"]) && $_REQUEST["FS"] != ''){
           $tablejoins_and_filters   .= filters\productAndMarketFilter::include_product_and_market_filters($this->queryVars,$this->settingVars);
        }

	if($_REQUEST['SKU']!='')
	{
	    $tablejoins_and_filters	.= " AND skuID_ROLLUP2 = ".$_REQUEST['SKU'];
	}
	    
	return $tablejoins_and_filters;
    }
}
?>