<?php
namespace classes\lcl;

use filters;
use utils;
use db;
use config;


class StorePlanoAnalysis extends config\UlConfig{
	//private $weekperiod,$yearperiod;
    public $pageName;
    /*****
    * Default gateway function, should be similar in all DATA CLASS
    * arguments:
    *  $settingVars [project settingsGateway variables]
    * @return $jsonOutput with all data that should be sent to client app
    *****/
    public function go($settingVars){
		$this->initiate($settingVars); //INITIATE COMMON VARIABLES
        
        $this->pageName = $_REQUEST["pageName"];
        $this->settingVars->pageName = $this->pageName; // set pageName in settingVars

		$this->queryPart = $this->getAll(); //USING OWN getAll function
		
		$this->gridValue();

		return $this->jsonOutput;
    }
	
	public function customSelectPart()
    {
		global $storeData;
		$storeData = array();
		$queryp = " AND CONCAT(".$this->settingVars->yearperiod.",".$this->settingVars->weekperiod.") IN (".implode("," , filters\timeFilter::getYearWeekWithinRange(0, $_REQUEST['pageID'], $this->settingVars)).") ";

        $query = "SELECT ". $this->settingVars->storetable . ".SNO AS SNO" .                
                ",SUM(" . $this->settingVars->ProjectVolume . ") AS VOLUME" .
                ",SUM(" . $this->settingVars->ProjectValue . ") AS VALUE " .
                "FROM " . $this->settingVars->tablename . $this->queryPart . $queryp . " " .
                "GROUP BY SNO";
        //echo $query; exit;
        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
        if (is_array($result) && !empty($result)) {
            foreach ($result as $key => $data) {
                $storeData[$data['SNO']]['VOLUME'] = $data['VOLUME'];
                $storeData[$data['SNO']]['VALUE'] = $data['VALUE'];
            }
        }		
        unset($result, $query);
    }
    
    public function gridValue() 
    {
		global $storeData;
		
		$this->customSelectPart();
		$skuStoreData = array();

		$queryp = " AND CONCAT(".$this->settingVars->yearperiod.",".$this->settingVars->weekperiod.") IN (".implode("," , filters\timeFilter::getYearWeekWithinRange(0, $_REQUEST['pageID'], $this->settingVars)).") ";

		$query 	= "SELECT ".$this->settingVars->skutable.".PIN AS PIN".
				",".$this->settingVars->storetable.".SNO AS SNO".
				",MAX(".$this->settingVars->skutable.".UPC) AS UPC".
				",MAX(".$this->settingVars->skutable.".PNAME) AS PNAME".
				",MAX(".$this->settingVars->storetable.".SNAME) AS SNAME".
				",MAX(".$this->settingVars->storetable.".banner_alt_grp1) AS BANNER".
				",MAX(".$this->settingVars->storetable.".REGION) AS REGION".
				",SUM(".$this->settingVars->ProjectVolume.") AS VOLUME".
				",SUM(".$this->settingVars->ProjectValue.") AS VALUE ".
				"FROM ".$this->settingVars->tablename.$this->queryPart.$queryp." ".
				"GROUP BY PIN,SNO";
        //echo $query;exit;				
		$result	= $this->queryVars->queryHandler->runQuery($query,$this->queryVars->linkid,db\ResultTypes::$TYPE_OBJECT);
		if (is_array($result) && !empty($result)) {
			foreach($result as $key=>$data) {
				$skuStoreData[$data['PIN']]['UPC'] = $data['UPC'];
				$skuStoreData[$data['PIN']]['PNAME'] = $data['PNAME'];
				$skuStoreData[$data['PIN']]['store'][$data['SNO']]['VOLUME'] = $data['VOLUME'];
				$skuStoreData[$data['PIN']]['store'][$data['SNO']]['VALUE'] = $data['VALUE'];
				$skuStoreData[$data['PIN']]['store'][$data['SNO']]['STORE'] = $data['SNAME'];
				$skuStoreData[$data['PIN']]['store'][$data['SNO']]['BANNER'] = $data['BANNER'];
				$skuStoreData[$data['PIN']]['store'][$data['SNO']]['REGION'] = $data['REGION'];
			}
		}		
		unset($result, $query);

		$query	= "SELECT  ".$this->settingVars->storeplanotable.".SNO AS SNO".
			",".$this->settingVars->productplanotable.".PIN AS PIN".
			",MAX(".$this->settingVars->productplanotable.".plano) AS PLANO ".
			"FROM ".$this->settingVars->productplanotable.", ".$this->settingVars->storeplanotable.",".$this->settingVars->skutable.",".$this->settingVars->storetable." ".
			"WHERE ".$this->settingVars->productplanotable.".plano = ".$this->settingVars->storeplanotable.".planogram ".
			"AND ".$this->settingVars->productplanotable.".PIN=".$this->settingVars->skutable.".PIN ".
			"AND ".$this->settingVars->storeplanotable.".SNO=".$this->settingVars->storetable.".SNO ".
			"AND ".$this->settingVars->skutable.".hide<>1 ".
			"AND ".$this->settingVars->skutable.".clientID='".$this->settingVars->clientID."' ".
			"GROUP BY SNO,PIN";
		//echo $query;exit;
		$result	= $this->queryVars->queryHandler->runQuery($query,$this->queryVars->linkid,db\ResultTypes::$TYPE_OBJECT);

	    $gridDataArray = array();
	    if (is_array($result) && !empty($result)) {
		    foreach($result as $key => $data)
			{
			    $PIN 	= $data['PIN'];
			    $sno 	= $data['SNO'];
			    if (is_array($skuStoreData[$PIN]['store']) && in_array($sno, array_keys($skuStoreData[$PIN]['store'])) && 
			    	$skuStoreData[$PIN]['store'][$sno]['VALUE'] < 1) {
			        
			        $tempArr = array();
				    $tempArr["PIN"] 	= $PIN;
				    $tempArr["UPC"] 	= $skuStoreData[$PIN]['UPC'];
				    $tempArr["PNAME"] 	= htmlspecialchars_decode($skuStoreData[$PIN]['PNAME']);
				    $tempArr["SNO"] 	= $sno;
				    $tempArr["STORE"] 	= htmlspecialchars_decode($skuStoreData[$PIN]['store'][$sno]['STORE']);
				    $tempArr["BANNER"] 	= htmlspecialchars_decode($skuStoreData[$PIN]['store'][$sno]['BANNER']);
				    $tempArr["REGION"] 	= htmlspecialchars_decode($skuStoreData[$PIN]['store'][$sno]['REGION']);
				    $tempArr["SALES"] 	= htmlspecialchars($storeData[$sno]['VALUE']);
		            $gridDataArray[] 	= $tempArr;					
		            unset($tempArr);
	            }
			}			
        }		
        unset($result);

        $this->jsonOutput['storePlano'] = $gridDataArray;
    }

}
?>