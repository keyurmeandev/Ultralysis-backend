<?php
namespace classes;

use db;
use filters;
use config;

class IdleStockAlert extends config\UlConfig{

	private $skuID,$skuName,$storeID,$storeName;
	
	/*****
    * Default gateway function, should be similar in all DATA CLASS
    * arguments:
    *  $settingVars [project settingsGateway variables]
    * @return $xmlOutput with all data that should be sent to client app
    *****/
	public function go($settingVars){
		$this->initiate($settingVars);
					
		//SET REQUIRED FIELD FOR QUERY SENT FORM CLIENT APPLICATION
        $this->pageName = $_REQUEST["pageName"];
		
		$this->skuID = $this->settingVars->dataArray[$this->settingVars->pageArray[$this->pageName]['SKU_FIELD']]['ID'];
        $this->skuName = $this->settingVars->dataArray[$this->settingVars->pageArray[$this->pageName]['SKU_FIELD']]['NAME'];
        $this->storeID = $this->settingVars->dataArray[$this->settingVars->pageArray[$this->pageName]['STORE_FIELD']]['ID'];
        $this->storeName = $this->settingVars->dataArray[$this->settingVars->pageArray[$this->pageName]['STORE_FIELD']]['NAME'];
		
		$this->queryPart	= $this->getAll();
		
		$action		= $_REQUEST["action"];
		switch($action){
			case "reload": 		$this->Reload();break; 
			case "skuchange": 	$this->changesku();break;
		}
		return $this->jsonOutput;
	}


	//PAREPARING FRESH DATA WITHOUT ANY FILTER FOR DATAGRID
	private function Reload(){				
	    $this->valueLatestPeriod();	    
	    $this->prepareGridData();   //ADDING TO OUTPUT		
	}
	
	//PREPARING CHART DATA FOR SELECTED SKU ON FRONT-END
	private function changesku(){	    
	    $this->chartValue(); //adding to output
	    $this->idleStHealthChk();  //adding to output
	}	
	
	private function chartValue(){	
		$arr	= array();
	    $qpart 	= $this->queryPart." AND ".$this->settingVars->maintable.".".$this->settingVars->period." IN (".implode(",",filters\timeFilter::getPeriodWithinRange(0,5,$this->settingVars)).") ";				
		
	    $query	= "SELECT ".$this->settingVars->maintable.".".$this->settingVars->period." AS PERIOD".
						",SUM(".$this->settingVars->ProjectVolume.") AS UNITS".
						",SUM(OHQ) AS OHQ".
						",SUM(MSQ) AS MSQ".
						",SUM(GSQ) AS GSQ ".
						",SUM(MUMD_QTY) AS MUM_QTY ".
						"FROM ".$this->settingVars->tablename . $qpart." ".
						"GROUP BY 1 ".
						"ORDER BY 1 ASC";  		
	    $result 	= $this->queryVars->queryHandler->runQuery($query,$this->queryVars->linkid,db\ResultTypes::$TYPE_OBJECT);		
	    if(is_array($result) && !empty($result))
		{
			foreach($result as $key=>$data)
			{
				$temp				= array();
				$temp["ACCOUNT"]	= $data['PERIOD'];
				$temp["QTY"]		= $data['UNITS'];
				$temp["OHQ"]		= $data['OHQ'];
				$temp["MSQ"]		= $data['MSQ'];
				$temp["GSQ"]		= $data['GSQ'];
				$temp["MUM_QTY"]	= $data['MUM_QTY'];
				$arr[]				= $temp;
			}
		}
		$this->jsonOutput['chartValue']	= $arr;
	}   
	
	function idleStHealthChk(){
		
		$arr	= array();
		
		//CALCULATION FOR LATEST 1 PERIOD
		$qpart  = $this->queryPart." AND ".$this->settingVars->maintable.".".$this->settingVars->period." IN (".implode(",",filters\timeFilter::getPeriodWithinRange(0 , 1 , $this->settingVars)).") ";
		
		$query	= "SELECT ITEM_STATUS".
			    ",CWO_FLAG".
			    ",TSI".
			    ",VSI".
			    ",Order_Book_Flag ".
			    "FROM ".$this->settingVars->tablename.$qpart;  	
		$result = $this->queryVars->queryHandler->runQuery($query,$this->queryVars->linkid,db\ResultTypes::$TYPE_ARRAY);
		
		if(is_array($result) && !empty($result))
		{
			foreach($result as $key=>$data)
			{				
				$arr["itemStatus"]	= $data[0];
				$arr["cancelwout"]	= $data[1];
				$arr["traited"]	= $data[2];
				$arr["valid"]		= $data[3];
				$arr["orderbFlag"]	= $data[4];
			}
		}
		//$this->jsonOutput['statusValue']	= $arr;
		
		//CALCULATION FOR LATEST 4 PERIOD
		$qpart  = $this->queryPart." AND ".$this->settingVars->maintable.".".$this->settingVars->period." IN (".implode(",",filters\timeFilter::getPeriodWithinRange(1 , 4 , $this->settingVars)).") ";		
		$query	= "SELECT SUM(".$this->settingVars->ProjectVolume.")".
			    ",SUM(MSQ)  ".
			    "FROM ".$this->settingVars->tablename.$qpart;  	
		$result = $this->queryVars->queryHandler->runQuery($query,$this->queryVars->linkid,db\ResultTypes::$TYPE_ARRAY);
		
		if(is_array($result) && !empty($result))
		{
			foreach($result as $key=>$data)
			{	
				$val				= $data[1]>0 ? number_format(($data[0]/$data[1])*100,1,'.',',') : 0;
				$arr["shelfFrate"]	= $val;
			}	
		}
		$this->jsonOutput['statusValue']	= $arr;
}

	
	private function valueLatestPeriod(){
		//PROVIDING GLOBALS
		global $resList;
		$resList=array();
		
		$qpart = $this->queryPart." AND ".$this->settingVars->maintable.".".$this->settingVars->period." IN (".implode("," , filters\timeFilter::getPeriodWithinRange(0 , 1 , $this->settingVars)).") ";   
		
		$query="SELECT ".$this->skuID.
				",".$this->storeID.
				",SUM(OHQ)".
				",SUM(".$this->settingVars->maintable.".UNIT_RETAIL) ".
				"FROM ".$this->settingVars->tablename . $qpart." ".
				"GROUP BY 1,2";
		$result = $this->queryVars->queryHandler->runQuery($query,$this->queryVars->linkid,db\ResultTypes::$TYPE_ARRAY);		
		if(is_array($result) && !empty($result))
		{
			foreach($result as $key=>$data){		
			  $SIN					= $data[0];
			  $sno					= $data[1];
			  $resList[$SIN][$sno]	= ($data[2]*$data[3]);
			}
		}
	}
		
	private function prepareGridData(){
		global $resList;
		
		$arr			= array();		
		$instockType	= $_REQUEST["instockType"];
		$qpart 			= $this->queryPart." AND ".$this->settingVars->maintable.".".$this->settingVars->period." IN (".implode(",",filters\timeFilter::getPeriodWithinRange(1,4,$this->settingVars)).") ";

		$query	= "SELECT ".$this->skuID.
						",MAX(".$this->skuName.")skuname".
						",".$this->storeID.
						",MAX(".$this->storeName.")sname".
						",AVG(".$this->settingVars->ProjectVolume.")qty".
						",AVG(OHQ)myohq".
						",AVG(UNIT_RETAIL)unitretail".
						",AVG(".$this->settingVars->ProjectValue.")sales ".
						"FROM ".$this->settingVars->tablename.$qpart." AND AVS = 1 AND qty>0 AND OHQ>0 ".
						"GROUP BY 1,3 LIMIT 1000";
		//echo $query;exit;
		$result = $this->queryVars->queryHandler->runQuery($query,$this->queryVars->linkid,db\ResultTypes::$TYPE_ARRAY);
		
		if(is_array($result) && !empty($result))
		{
			foreach($result as $key=>$data)
			{			
				$QTYOHQ		= $data[5]>0 ? (($data[4]/$data[5])*100) : 0;
				$SIN		= $data[0];
				$sno		= $data[2];			
				$sv		= $resList[$SIN][$sno];			
				$STOCKVAL 	= number_format($sv,2,'.',',');
				//echo $QTYOHQ;exit;
				$addData = false;
				if($instockType==1)
				{
					if($QTYOHQ<5){
						$addData = true;
					}
				}elseif($instockType==2){
					if($QTYOHQ>=5 && $QTYOHQ<=20){
						$addData = true;
					}
				}elseif($instockType==3){
					if($QTYOHQ>20){
						$addData = true;
					}
				}	

				//Test
				$addData = true;
				if($addData==true){
					$temp	= array();
					$temp["SKU"]		= htmlspecialchars_decode($data[0]);
					$temp["SKUNAME"]	= htmlspecialchars_decode($data[1]);
					$temp["STORE"]		= $data[2];
					$temp["STORENAME"]	= htmlspecialchars_decode($data[3]);
					$temp["QTY"]		= $data[4];
					$temp["OHQ"]		= $data[5];
					$temp["SALES"]		= number_format($data[7] , 2 , '.' , '');
					$temp["UNITRETAIL"]	= $data[6];
					$temp["QTYOHQ"]		= number_format($QTYOHQ , 2 , '.' , ',');
					$temp["STOCKVAL"]	= $STOCKVAL;
					$arr[]				= $temp;
				}			
			}	
		}		
		$this->jsonOutput['gridValue']	= $arr;
	}   
		
	/** OVERRIDING PARENT CLASS'S getAll FUNCTION **/
	public function getAll(){
		$tablejoins_and_filters = parent::getAll();
		
		if($_REQUEST["STORE"]!="") 
			$tablejoins_and_filters	 .=" AND ".$this->storeID."=".$_REQUEST['STORE'];	      
			
		if($_REQUEST["SKU"]!="") 
			$tablejoins_and_filters	 .=" AND ".$this->skuID."=".$_REQUEST['SKU'];	      
			
		/* if($_REQUEST["depot"]!="All" && $_REQUEST["depot"]!="") 
			$tablejoins_and_filters	 .=" AND ".$this->settingVars->maintable.".depotID=".$_REQUEST['depot'];	 */
								
		return  $tablejoins_and_filters;
	}
}
?> 