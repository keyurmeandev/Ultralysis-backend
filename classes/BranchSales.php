<?php
namespace classes;

use projectsettings;
use SimpleXMLElement;
use datahelper;
use filters;
use db;
use exportphps;
use config;

class BranchSales extends config\UlConfig{
    function go($settingVars){
	$this->initiate($settingVars);
	$this->queryPart    = $this->getAll();

	filters\timeFilter::getExtraSlice_ByQuery($this->settingVars);

	$this->getData();
    }
    
    private function getData(){
	
	$selectableItems = explode("-" , $_REQUEST['ITEMS']);
	$selectParts 	= array();
	$groupByParts 	= array();
	foreach($selectableItems as $currentSelectable)
	{
	    $id		 	= key_exists('ID' , $this->settingVars->dataArray[$currentSelectable]) ? $this->settingVars->dataArray[$currentSelectable]['ID'] : "";
	    $idAliase	 	= key_exists('ID' , $this->settingVars->dataArray[$currentSelectable]) ? $this->settingVars->dataArray[$currentSelectable]['ID_ALIASE'] : "";
	    if($id != "" && $_REQUEST['INCLUDE_IDS']=="true"){$selectParts[] = "$id AS $idAliase"; $groupByParts[] = $idAliase;}
	    $account 		= $this->settingVars->dataArray[$currentSelectable]['NAME'];
	    $accountAliase 	= $this->settingVars->dataArray[$currentSelectable]['NAME_ALIASE'];
	    $selectParts[] 	= "$account AS $accountAliase";
	    $groupByParts[] 	= $accountAliase;   
	}
	$query 		= "SELECT ".implode("," , $selectParts).
			    ",SUM((CASE WHEN ". filters\timeFilter::$tyWeekRange ." THEN 1 ELSE 0 END)* ". $this->settingVars->ProjectValue. " ) AS TP_VAL".
			    ",SUM((CASE WHEN ". filters\timeFilter::$ppWeekRange ." THEN 1 ELSE 0 END)* ". $this->settingVars->ProjectValue. " ) AS PP_VAL".
			    ",SUM((CASE WHEN ". filters\timeFilter::$lyWeekRange ." THEN 1 ELSE 0 END)* ". $this->settingVars->ProjectValue. " ) AS LY_VAL".
			    ",SUM((CASE WHEN ". filters\timeFilter::$tyWeekRange ." THEN 1 ELSE 0 END)* ". $this->settingVars->ProjectVolume. " ) AS TP_VOL".
			    ",SUM((CASE WHEN ". filters\timeFilter::$ppWeekRange ." THEN 1 ELSE 0 END)* ". $this->settingVars->ProjectVolume. " ) AS PP_VOL".
			    ",SUM((CASE WHEN ". filters\timeFilter::$lyWeekRange ." THEN 1 ELSE 0 END)* ". $this->settingVars->ProjectVolume. " ) AS LY_VOL ".
			    "FROM ".$this->settingVars->tablename.$this->queryPart." ".
			    "GROUP BY ".implode("," , $groupByParts);
	$result 	= $this->queryVars->queryHandler->runQuery($query,$this->queryVars->linkid,db\ResultTypes::$TYPE_OBJECT);
	$gainArr 	= array();
	$maintainArr 	= array();
	$lostArr 	= array();
	foreach($result as $key=>$data)
	{
	    $lyOrPp = $_REQUEST['timeMode'] == 'pp' ? 'PP_VOL' : 'LY_VOL';
	    if($data[$lyOrPp]==0) //-- GAINED
	    {
		if($data['TP_VAL']>0 && $data['TP_VOL']>0)
		    $gainArr[] = $data; 
	    }
	    else if($data['TP_VOL']>0 && $data[$lyOrPp]>0) //-- MAINTAINED
	    {
		$maintainArr[] = $data;
	    }
	    else if($data['TP_VOL']==0 && $data[$lyOrPp]>0) //-- LOST
	    {
		$lostArr[] = $data;
	    }
	}
		
	//-- Include PHPExcel
	if($_REQUEST['timeMode']=="pp") exportphps\branchSalesVsPP_xlsMaker_forFerreroRetailLink::createXlsFile($gainArr , $maintainArr , $lostArr);
	else exportphps\branchSalesVsLY_xlsMaker_forFerreroRetailLink::createXlsFile($gainArr,$maintainArr,$lostArr);	    
    }



    /*****
    * OVERRIDING PARENT CLASS'S getAll
    * COLLECTS TABLE JOINING STRING FROM PROJECT SETTINGS
    * ALSO APPLYS FILTER CONDITIONS TO THE STRING
    * AND RETURNS $tablejoins_and_filters
    *****/
    public function getAll(){
	$tablejoins_and_filters       = $this->settingVars->link;
	
	$skuID = $this->settingVars->dataArray[$_REQUEST['ACCOUNT']]['ID'];
	if($_REQUEST['TPNB']!="")
	{
	    $tablejoins_and_filters.=" AND $skuID = '".$_REQUEST['TPNB']."' ";
	}
	return $tablejoins_and_filters;
    }
}
?>