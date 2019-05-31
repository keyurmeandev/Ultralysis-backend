<?php
namespace classes;

use db;
use filters;
use config;

class TraitedValidTracker extends config\UlConfig{
/*****
    * Default gateway function, should be similar in all DATA CLASS
    * arguments:
    *  $settingVars [project settingsGateway variables]
    * @return $xmlOutput with all data that should be sent to client app
    *****/
	public function go($settingVars){
		$this->initiate($settingVars);
		$this->queryPart = $this->getAll(); //USES PARENT CLASS'S 'getAll' FUNCTION
	
		$this->pageName = $_REQUEST["pageName"];

        $this->settingVars->pageName = $this->pageName; // set pageName in settingVars
        $this->skuID = $this->settingVars->dataArray[$this->settingVars->pageArray[$this->pageName]["SKU_ACCOUNT"]]['ID'];
        $this->skuName = $this->settingVars->dataArray[$this->settingVars->pageArray[$this->pageName]["SKU_ACCOUNT"]]['NAME'];
        $this->storeID = $this->settingVars->dataArray[$this->settingVars->pageArray[$this->pageName]["STORE_ACCOUNT"]]['ID'];
        $this->storeName = $this->settingVars->dataArray[$this->settingVars->pageArray[$this->pageName]["STORE_ACCOUNT"]]['NAME'];
        $this->inDepotField = $this->settingVars->dataArray[$this->settingVars->pageArray[$this->pageName]["STORE_WHS"]]['NAME'];
        $this->onHandField = $this->settingVars->dataArray[$this->settingVars->pageArray[$this->pageName]["STORE_HAND"]]['NAME'];
        $this->onOrderField = $this->settingVars->dataArray[$this->settingVars->pageArray[$this->pageName]["STORE_ORDER"]]['NAME'];
        $this->inTransField = $this->settingVars->dataArray[$this->settingVars->pageArray[$this->pageName]["STORE_TRANS"]]['NAME'];

		$this->valueFunc();
		$this->gridValue(); //ADDING TO OUTPUT
		
		return $this->jsonOutput;
	}	
	

	
	private function valueFunc(){
		//COLLECTING SALES AND ATS/AVS DATA FOR LAST WEEK
		$this->utilDataForWeek_1	=	array();
		$getPeriods = (is_array(filters\timeFilter::getPeriodWithinRange(1,1 , $this->settingVars)) && !empty(filters\timeFilter::getPeriodWithinRange(1,1 , $this->settingVars))) ? implode("," , filters\timeFilter::getPeriodWithinRange(1,1 , $this->settingVars)) : 0;
		$qpart	= $this->queryPart." AND ".$this->settingVars->maintable.".".$this->settingVars->period." IN (".$getPeriods.") ";
		$query	= "SELECT ".$this->skuID." AS SKUID".
						",".$this->storeID." AS SNO".
						",ATS AS ATS".
						",AVS AS AVS".
						",qty AS QTY".
						",sales AS SALES".
						",SUM(dd_cases*WHPK_Qty) DD ".
						"FROM ".$this->settingVars->tablename.$qpart." ".
						"GROUP BY SKUID,SNO,ATS,AVS,QTY,SALES";	
		//echo $query;exit;
		$result = $this->queryVars->queryHandler->runQuery($query , $this->queryVars->linkid , db\ResultTypes::$TYPE_OBJECT);		
		foreach($result as $key=>$data)
		{
			$sin	= $data['SKUID'];
			$sno	= $data['SNO'];
			$this->utilDataForWeek_1[$sin][$sno]['ATS']		= $data['ATS'];
			$this->utilDataForWeek_1[$sin][$sno]['AVS']		= $data['AVS'];
			$this->utilDataForWeek_1[$sin][$sno]['QTY']		= $data['QTY'];
			$this->utilDataForWeek_1[$sin][$sno]['SALES']	= $data['SALES'];
			$this->utilDataForWeek_1[$sin][$sno]['DD']		= $data['DD'];
		}
		
		//COLLECTING ATS/AVS DATA FOR SECOND LAST WEEK
		$this->utilDataForWeek_2	=	array();
		$getPeriods = (is_array(filters\timeFilter::getPeriodWithinRange(2,1 , $this->settingVars)) && !empty(filters\timeFilter::getPeriodWithinRange(2,1 , $this->settingVars))) ? implode("," , filters\timeFilter::getPeriodWithinRange(2,1 , $this->settingVars)) : 0;
		$qpart	= $this->queryPart." AND ".$this->settingVars->maintable.".".$this->settingVars->period." IN (".$getPeriods.") ";		
		$query	= "SELECT ".$this->skuID." AS SKUID".
						",".$this->storeID." AS SNO".
						",ATS AS ATS".
						",AVS AS AVS ".
						"FROM ".$this->settingVars->tablename.$qpart." ".
						"GROUP BY SKUID,SNO,ATS,AVS LIMIT 1000";	
		//echo $query;exit;
		$result	= $this->queryVars->queryHandler->runQuery($query , $this->queryVars->linkid , db\ResultTypes::$TYPE_OBJECT);		
		foreach($result as $key=>$data)
		{
			$sin	= $data['SKUID'];
			$sno	= $data['SNO'];
			$this->utilDataForWeek_2[$sin][$sno]['ATS']	= $data['ATS'];
			$this->utilDataForWeek_2[$sin][$sno]['AVS']	= $data['AVS'];
		}

		//COLLECTING ATS/AVS DATA FOR THIRD LAST WEEK		
		$this->utilDataForWeek_3	=	array();
		$getPeriods = (is_array(filters\timeFilter::getPeriodWithinRange(3,1 , $this->settingVars)) && !empty(filters\timeFilter::getPeriodWithinRange(3,1 , $this->settingVars))) ? implode("," , filters\timeFilter::getPeriodWithinRange(3,1 , $this->settingVars)) : 0;
		$qpart	= $this->queryPart." AND ".$this->settingVars->maintable.".".$this->settingVars->period." IN (".$getPeriods.") ";		
		$query	= "SELECT ".$this->skuID." AS SKUID".
						",".$this->storeID." AS SNO".
						",ATS AS ATS".
						",AVS AS AVS ".
						"FROM ".$this->settingVars->tablename.$qpart." ".
						"GROUP BY SKUID,SNO,ATS,AVS LIMIT 1000";	
		//echo $query;exit;
		$result	= $this->queryVars->queryHandler->runQuery($query , $this->queryVars->linkid , db\ResultTypes::$TYPE_OBJECT);		
		foreach($result as $key=>$data)
		{
			$sin	= $data['SKUID'];
			$sno	= $data['SNO'];
			$this->utilDataForWeek_3[$sin][$sno]['ATS']	= $data['ATS'];
			$this->utilDataForWeek_3[$sin][$sno]['AVS']	= $data['AVS'];
		}

		//COLLECTING ATS/AVS DATA FOR FOURTH LAST WEEK
		$this->utilDataForWeek_4	=	array();
		$getPeriods = (is_array(filters\timeFilter::getPeriodWithinRange(4,1 , $this->settingVars)) && !empty(filters\timeFilter::getPeriodWithinRange(4,1 , $this->settingVars))) ? implode("," , filters\timeFilter::getPeriodWithinRange(4,1 , $this->settingVars)) : 0;
		$qpart	= $this->queryPart." AND ".$this->settingVars->maintable.".".$this->settingVars->period." IN (".$getPeriods.") ";						
		$query	= "SELECT ".$this->skuID." AS SKUID".
						",".$this->storeID." AS SNO".
						",ATS AS ATS".
						",AVS AS AVS ".
						"FROM ".$this->settingVars->tablename.$qpart." ".
						"GROUP BY SKUID,SNO,ATS,AVS";	
		//echo $query;exit;
		$result	= $this->queryVars->queryHandler->runQuery($query , $this->queryVars->linkid , db\ResultTypes::$TYPE_OBJECT);		
		foreach($result as $key=>$data)
		{
			$sin	= $data[0];
			$sno	= $data[1];
			$this->utilDataForWeek_4[$sin][$sno]['ATS']	= $data['ATS'];
			$this->utilDataForWeek_4[$sin][$sno]['AVS']	= $data['AVS'];
		}

		//COLLECTING ATS/AVS DATA FOR FIFTH LAST WEEK
		$this->utilDataForWeek_5	=	array();
		$getPeriods = (is_array(filters\timeFilter::getPeriodWithinRange(5,1 , $this->settingVars)) && !empty(filters\timeFilter::getPeriodWithinRange(5,1 , $this->settingVars))) ? implode("," , filters\timeFilter::getPeriodWithinRange(5,1 , $this->settingVars)) : 0;
		$qpart	= $this->queryPart." AND ".$this->settingVars->maintable.".".$this->settingVars->period." IN (".$getPeriods.") ";						
		$query	= "SELECT ".$this->skuID." AS SKUID".
						",".$this->storeID." AS SNO".
						",ATS AS ATS".
						",AVS AS AVS ".
						"FROM ".$this->settingVars->tablename.$qpart." ".
						"GROUP BY SKUID,SNO,ATS,AVS";	
		$result	= $this->queryVars->queryHandler->runQuery($query , $this->queryVars->linkid , db\ResultTypes::$TYPE_OBJECT);		
		foreach($result as $key=>$data)
		{
			$sin	= $data['SKUID'];
			$sno	= $data['SNO'];
			$this->utilDataForWeek_5[$sin][$sno]['ATS']	= $data['ATS'];
			$this->utilDataForWeek_5[$sin][$sno]['AVS']	= $data['AVS'];
		}

		//COLLECTING ATS/AVS DATA FOR FIFTH LAST WEEK
		$this->utilDataForWeek_6	=	array();
		$getPeriods = (is_array(filters\timeFilter::getPeriodWithinRange(6,1 , $this->settingVars)) && !empty(filters\timeFilter::getPeriodWithinRange(6,1 , $this->settingVars))) ? implode("," , filters\timeFilter::getPeriodWithinRange(6,1 , $this->settingVars)) : 0;
		$qpart	= $this->queryPart." AND ".$this->settingVars->maintable.".".$this->settingVars->period." IN (".$getPeriods.") ";						
		$query	= "SELECT ".$this->skuID." AS SKUID".
						",".$this->storeID." AS SNO".
						",ATS AS ATS".
						",AVS AS AVS ".
						"FROM ".$this->settingVars->tablename.$qpart." ".
						"GROUP BY SKUID,SNO,ATS,AVS";	
		$result	= $this->queryVars->queryHandler->runQuery($query , $this->queryVars->linkid , db\ResultTypes::$TYPE_OBJECT);		
		foreach($result as $key=>$data)
		{
			$sin	= $data['SKUID'];
			$sno	= $data['SNO'];
			$this->utilDataForWeek_6[$sin][$sno]['ATS']	= $data['ATS'];
			$this->utilDataForWeek_6[$sin][$sno]['AVS']	= $data['AVS'];
		}

		return $value;		
	}   	
	
	private function gridValue(){
		$latestPeriod 	= filters\timeFilter::getPeriodWithinRange(0 , 1 , $this->settingVars)[0];
		$qpart			= $this->queryPart." AND ".$this->settingVars->maintable.".".$this->settingVars->period." = $latestPeriod ";						
		$query	= "SELECT ".$this->skuID." AS TPNB".
						",MAX(".$this->skuName.") AS SKU".
						",".$this->storeID." AS SNO".
						",MAX(".$this->storeName.") AS STORE".
						",SUM(".$this->onHandField.") AS ON_HAND".
						",SUM(".$this->inTransField.") AS IN_TRANS".
						",SUM(".$this->inDepotField.") AS IN_DEPOT".
						",SUM(".$this->onOrderField.") AS IN_ORDER ".
						"FROM ".$this->settingVars->tablename.$qpart." ".
						"AND TSI=1 AND VSI=0  ".
						"GROUP BY TPNB,SNO ".
						"ORDER BY ON_HAND DESC";
		//print $query;exit;
		$result	= $this->queryVars->queryHandler->runQuery($query , $this->queryVars->linkid , db\ResultTypes::$TYPE_OBJECT);		
		$total	= count($result);
		$arr 	= array(); 
		foreach($result as $key=>$data)
		{			
			//print("<pre>");print_r($this->utilDataForWeek_1);exit;
			$temp 	= array();
			$sin	= $data['TPNB'];
			$sno	= $data['SNO'];
			$ats1	= $this->utilDataForWeek_1[$sin][$sno]['ATS'];
			$avs1	= $this->utilDataForWeek_1[$sin][$sno]['AVS'];

			$ats2	= $this->utilDataForWeek_1[$sin][$sno]['ATS'];
			$avs2	= $this->utilDataForWeek_1[$sin][$sno]['AVS'];
			
			$ats3	= $this->utilDataForWeek_1[$sin][$sno]['ATS'];
			$avs3	= $this->utilDataForWeek_1[$sin][$sno]['AVS'];	

			$ats4	= $this->utilDataForWeek_1[$sin][$sno]['ATS'];
			$avs4	= $this->utilDataForWeek_1[$sin][$sno]['AVS'];
			
			$ats5	= $this->utilDataForWeek_1[$sin][$sno]['ATS'];
			$avs5	= $this->utilDataForWeek_1[$sin][$sno]['AVS'];
			
			$ats6	= $this->utilDataForWeek_1[$sin][$sno]['ATS'];
			$avs6	= $this->utilDataForWeek_1[$sin][$sno]['AVS'];
			
			$qty	= $this->utilDataForWeek_1[$sin][$sno][$sin][$sno]['QTY'];
			$sales	= $this->utilDataForWeek_1[$sin][$sno][$sin][$sno]['SALES'];
			$dd		= $this->utilDataForWeek_1[$sin][$sno][$sin][$sno]['DD'];
						
			$temp["TPNB"]			= $data['TPNB'];
			$temp["SKU"] 			= htmlspecialchars($data['SKU']);
			$temp["SNO"] 			= $data['SNO'];
			$temp["STORE"] 			= $data['STORE'];
			$temp["ON_HAND"]		= $data['ON_HAND'];
			$temp["IN_TRANS"]		= $data['IN_TRANS'];
			$temp["IN_DEPOT"] 		= $data['IN_DEPOT'];
			$temp["IN_ORDER"]	 	= $data['IN_ORDER'];
			$temp["ATS1"]			= $this->utilDataForWeek_1[$sin][$sno]['ATS'];
			$temp["AVS1"]			= $this->utilDataForWeek_1[$sin][$sno]['AVS'];
			$temp["ATS2"]			= $this->utilDataForWeek_2[$sin][$sno]['ATS'];
			$temp["AVS2"]			= $this->utilDataForWeek_2[$sin][$sno]['AVS'];
			$temp["ATS3"]			= $this->utilDataForWeek_3[$sin][$sno]['ATS'];
			$temp["AVS3"]			= $this->utilDataForWeek_3[$sin][$sno]['AVS'];
			$temp["ATS4"]			= $this->utilDataForWeek_4[$sin][$sno]['ATS'];
			$temp["AVS4"]			= $this->utilDataForWeek_4[$sin][$sno]['AVS'];
			$temp["ATS5"]			= $this->utilDataForWeek_5[$sin][$sno]['ATS'];
			$temp["AVS5"]			= $this->utilDataForWeek_5[$sin][$sno]['AVS'];
			$temp["ATS6"]			= $this->utilDataForWeek_6[$sin][$sno]['ATS'];
			$temp["AVS6"]			= $this->utilDataForWeek_6[$sin][$sno]['AVS'];
			$temp["QTY"]			= number_format($qty,0);
			$temp["SALES"]			= number_format($sales,2,'.','');
			$temp["DD"]				= number_format($dd,1,'.','');
			$arr[]					= $temp;
		}
		
		$this->jsonOutput['traitedValidTracker']	= $arr;
		$this->jsonOutput['latestPeriod']			= $latestPeriod;
		$this->jsonOutput['totalRow']				= $total;
		
	}
}
?> 