<?php
namespace classes;

use filters;
use db;
use config;

class DdMonitor extends config\UlConfig{
    private $skuID,$skuName,$storeID,$storeName;
    
    public function go($settingVars){
	
	$this->initiate($settingVars); //INITIATE COMMON VARIABLES
	
	$this->skuID 	    = key_exists('ID' , $this->settingVars->dataArray[$_REQUEST['SKU_FIELD']]) ? $this->settingVars->dataArray[$_REQUEST['SKU_FIELD']]['ID'] : $this->settingVars->dataArray[$_REQUEST['SKU_FIELD']]['NAME'];
	$this->skuName 	    = $this->settingVars->dataArray[$_REQUEST['SKU_FIELD']]['NAME'];
	$this->storeID 	    = key_exists('ID' , $this->settingVars->dataArray[$_REQUEST['STORE_FIELD']]) ? $this->settingVars->dataArray[$_REQUEST['STORE_FIELD']]['ID'] : $this->settingVars->dataArray[$_REQUEST['STORE_FIELD']]['NAME'];
	$this->storeName    = $this->settingVars->dataArray[$_REQUEST['STORE_FIELD']]['NAME'];
	
	$action = $_GET["action"];
	
	switch ($action) {
	    case "reload":		
			$this->Reload();		
			break;
	    case "ChangeCategory":	
			$this->ChangeCategory();	
			break;
	    case "ChangeFilter":	
			$this->Reload();
			break;			
		case "changeDDFilter":
			$this->ChangeCategory();
			break;
		default:
			$this->Reload();
			break;			
	}	
	
	return $this->jsonOutput;
    }
    
    private function Reload(){
		$this->queryPart = $this->getAll(); //USING OWN getAll FUNCTION
		$this->filterTable(); //ADDING TO OUTPUT
    }
    
    private function ChangeCategory() {
		$this->queryPart = $this->getAll(); //USING OWN getAll FUNCTION
		$this->categoryTable(); //ADDING TO OUTPUT
    }
    
    private function categoryTable(){
		$mystore 	= array();
		$totalQty 	= 0;
		$totalddunit 	= 0;
		
		$query 	    = "SELECT ".$this->skuID." AS SKUID".
					",".$this->storeID." AS SNO".
					",MAX(".$this->storeName.") AS STORE".
					",SUM(dd_cases) AS DDCASE".
					",SUM(dd_cases*WHPK_Qty) AS DDUNITS".
					",SUM(qty) AS QTY".
					",ATS AS ATS ".
					"FROM ".$this->settingVars->tablename.$this->queryPart." ".
					"AND ".$this->settingVars->maintable.".".$this->settingVars->period." IN(" . filters\timeFilter::getPeriodWithinRangeTwin($this->settingVars , 1 , 1) . ") ".
					"GROUP BY SKUID,SNO,ATS ";	
		//exit($query);
		$result     = $this->queryVars->queryHandler->runQuery($query,$this->queryVars->linkid,db\ResultTypes::$TYPE_OBJECT);
		foreach($result as $key=>$data)
		{
			$totalQty 		= $totalQty + $data['QTY'];
			$totalddunit 	= $totalddunit + $data['DDUNITS'];
		}
		
		foreach($result as $key=>$data)
		{
			$qtyShare 		= ($totalQty>0) ? number_format(($data['QTY'] / $totalQty) * 100, 1, '.', '') : 0;
			$ddunitShare 	= ($totalddunit>0) ? number_format(($data['DD_WHQ'] / $totalddunit) * 100, 1, '.', '') : 0;
			
			/*$value 		= $this->xmlOutput->addChild("gridCategory");
			$value->addChild("SKUID",		$data['SKUID']);
			$value->addChild("SNO",		$data['SNO']);
			$value->addChild("STORE",		htmlspecialchars_decode($data['STORE']));
			$value->addChild("DDCASE",		$data['DDCASE']);
			$value->addChild("DDUNITS",		$data['DDUNITS']);
			$value->addChild("DDUNITSHARE",	$ddunitShare);
			$value->addChild("QTY",		$data['QTY']);
			$value->addChild("QTYSHARE",	$qtyShare);
			$value->addChild("ATS",		$data['ATS']);*/
			
			$result[$key]['SKUID'] = $data['SKUID'];
			$result[$key]['SNO'] = $data['SNO'];
			$result[$key]['STORE'] = htmlspecialchars_decode($data['STORE']);
			$result[$key]['DDCASE'] = $data['DDCASE'];
			$result[$key]['DDUNITS'] = $data['DDUNITS'];
			$result[$key]['DDUNITSHARE'] = $ddunitShare;
			$result[$key]['QTY'] = $data['QTY'];
			$result[$key]['QTYSHARE'] = $qtyShare;
			$result[$key]['ATS'] = $data['ATS'];
		}
		
		$this->jsonOutput["gridStore"] = $result;
    }
    
    private function filterTable() {
		$totalQty 		= 0;
		$totalddunit 	= 0;
		$query 		= "SELECT ".$this->skuID." AS PIN".
						",".$this->skuName." AS SKU".
						",MAX(Order_Book_Flag) AS OBFLAG".
						",SUM(dd_cases) AS DDCASE".
						",SUM(dd_cases*WHPK_Qty) AS DDUNITS".
						",SUM(".$this->settingVars->ProjectVolume.") AS QTY".
						",MAX(ITEM_STATUS) AS ITEMSTATUS ".
						"FROM ".$this->settingVars->tablename.$this->queryPart." ".
						"AND ".$this->settingVars->maintable.".".$this->settingVars->period." IN(" . filters\timeFilter::getPeriodWithinRangeTwin($this->settingVars , 1 , 1) . ") ".
						"GROUP BY PIN,SKU";
		//exit($query);
		$result 	= $this->queryVars->queryHandler->runQuery($query,$this->queryVars->linkid,db\ResultTypes::$TYPE_OBJECT);
		foreach($result as $key=>$data)
		{
			$totalQty 		= $totalQty + $data['QTY'];
			$totalddunit 	= $totalddunit + $data['DDUNITS'];
		}
		
		foreach($result as $key=>$data)
		{
			$qtyShare 		= ($totalQty>0) ? number_format(($data['QTY'] / $totalQty) * 100, 1, '.', '') : 0;
			$ddunitShare 	= ($totalddunit>0) ? number_format(($data['DDUNITS'] / $totalddunit) * 100, 1, '.', '') : 0;
		
			/*$value 		= $this->xmlOutput->addChild("gridFilter");
			$value->addChild("PIN",			$data['PIN']);
			$value->addChild("SKU",			htmlspecialchars_decode($data['SKU']));
			$value->addChild("OBFLAG",			$data['OBFLAG']);
			$value->addChild("DDCASE",			$data['DDCASE']);
			$value->addChild("DDUNITS",			$data['DDUNITS']);
			$value->addChild("DDUNITSHARE",		$ddunitShare);
			$value->addChild("QTY",			$data['QTY']);
			$value->addChild("QTYSHARE",		$qtyShare);
			$value->addChild("ITEMSTATUS",		$data['ITEMSTATUS']);*/
			
			$result[$key]['PIN'] = $data['PIN'];
			$result[$key]['SKU'] = htmlspecialchars_decode($data['SKU']);
			$result[$key]['OBFLAG'] = $data['OBFLAG'];
			$result[$key]['DDCASE'] = $data['DDCASE'];
			$result[$key]['DDUNITS'] = $data['DDUNITS'];
			$result[$key]['DDUNITSHARE'] = $ddunitShare;
			$result[$key]['QTY'] = $data['QTY'];
			$result[$key]['QTYSHARE'] = $qtyShare;
			$result[$key]['ITEMSTATUS'] = $data['ITEMSTATUS'];
		}
		
		$this->jsonOutput["gridCategory"] = $result;
    }
    
    /**** OVERRIDE PARENT CLASS'S getAll FUNCTION ****/
    public function getAll() {
		$tablejoins_and_filters       	 = $this->settingVars->link;    
		
		if ($_REQUEST["PIN"] != "")
		   $tablejoins_and_filters .=" AND skuID_ROLLUP2='".$_REQUEST[PIN]."'";
		
		if ($_REQUEST["dd_cases"] == "N")
			$tablejoins_and_filters	.=" AND dd_cases = 0";

		if ($_REQUEST["dd_cases"] == "Y")
			$tablejoins_and_filters	.=" AND dd_cases > 0";
			
		if ($_REQUEST["ITEM_STATUS"] != "" && $_REQUEST["ITEM_STATUS"] != "ALL")
			$tablejoins_and_filters .= " AND ITEM_STATUS='".$_REQUEST["ITEM_STATUS"]."'";
		if($_REQUEST["Order_Book_Flag"] != "" && $_REQUEST["Order_Book_Flag"] != "ALL")	
			$tablejoins_and_filters .= " AND Order_Book_Flag='".$_REQUEST['Order_Book_Flag']."'";
		
		return $tablejoins_and_filters;
    }
}
?>