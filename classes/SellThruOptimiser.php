<?php
namespace classes;

use db;
use filters;
use config;

class SellThruOptimiser extends config\UlConfig{
    /*****
    * Default gateway function, should be similar in all DATA CLASS
    * arguments:
    *  $settingVars [project settingsGateway variables]
    * @return $xmlOutput with all data that should be sent to client app
    *****/
    public function go($settingVars){
        $this->initiate($settingVars);//INITIATE COMMON VARIABLES       
		
		//SET REQUIRED FIELD FOR QUERY SENT FORM CLIENT APPLICATION
        $this->pageName = $_REQUEST["pageName"];
		
		$this->skuID = $this->settingVars->dataArray[$this->settingVars->pageArray[$this->pageName]['SKU_ACCOUNT']]['ID'];
        $this->skuName = $this->settingVars->dataArray[$this->settingVars->pageArray[$this->pageName]['SKU_ACCOUNT']]['NAME'];
        $this->storeID = $this->settingVars->dataArray[$this->settingVars->pageArray[$this->pageName]['STORE_ACCOUNT']]['ID'];
        $this->storeName = $this->settingVars->dataArray[$this->settingVars->pageArray[$this->pageName]['STORE_ACCOUNT']]['NAME'];
		
        $this->queryPart    = $this->getAll();
        
        $action     = $_REQUEST["action"];
        switch ($action)
        {
            case "reload":      $this->Reload();     break;
            case "skuchange":   $this->changesku();  break;
        }
		return $this->jsonOutput;
    }
	
    private function Reload(){
        $this->valueLatestPeriod();
        $this->parepareGridData();        
    }

    private function changesku(){
        $this->chartValue();
        $this->idleStHealthChk();        
    }

    private function chartValue(){
        $arr = array();
        $qty = 0;
        $gsq = 0;
        $qpart.= $this->queryPart." AND ".$this->settingVars->maintable.".".$this->settingVars->period." IN (" . implode("," , filters\timeFilter::getPeriodWithinRange(0 , 8 , $this->settingVars)) . ") ";
    
        $query = "SELECT ".$this->settingVars->maintable.".".$this->settingVars->period.
                    ",SUM(".$this->settingVars->ProjectVolume.")".
                    ",SUM(OHQ)".
                    ",SUM(MSQ)".
                    ",SUM(GSQ) " .
                    "FROM ".$this->settingVars->tablename.$qpart." ".
                    "GROUP BY 1 ".
                    "ORDER BY 1 ASC";
        $result = $this->queryVars->queryHandler->runQuery($query,$this->queryVars->linkid,db\ResultTypes::$TYPE_ARRAY);
        
		if(is_array($result) && !empty($result))
		{
			foreach($result as $key=>$data)
			{
				$temp		= array();
				
				$qty        = $qty + $data[1];
				$gsq        = $gsq + $data[4];
				$sellthru   = $gsq > 0 ? number_format((($qty / $gsq) * 100), 1) : 0;
				
				$temp["ACCOUNT"]	= $data[0];
				$temp["QTY"]		= $data[1];
				$temp["OHQ"]		= $data[2];
				$temp["MSQ"]		= $data[3];
				$temp["GSQ"]		= $data[4];
				$temp["SELLTHRU"]	= $sellthru;
				$arr[]				= $temp;
			}
		}
		$this->jsonOutput['chartValue']	= $arr;
    }

    private function idleStHealthChk(){
		$arr   = array();
        $qpart = $this->queryPart." AND ".$this->settingVars->maintable.".".$this->settingVars->period." IN (" . implode("," , filters\timeFilter::getPeriodWithinRange(0 , 1 , $this->settingVars)) . ") ";

        $query = "SELECT SUM(StoreHand)".
                    ",SUM(StoreTrans)".
                    ",SUM(StoreWhs)".
                    ",SUM(StoreOrder)  " .
                    " FROM ".$this->settingVars->tablename.$qpart;
        $result = $this->queryVars->queryHandler->runQuery($query,$this->queryVars->linkid,db\ResultTypes::$TYPE_ARRAY);
        if(is_array($result) && !empty($result))
		{
			foreach($result as $key=>$data)
			{
				$temp				= array();
				$temp["onHand"]		= $data[0];
				$temp["InTrans"]	= $data[1];
				$temp["InDepot"]	= $data[2];
				$temp["OnOrder"]	= $data[3];
				$arr[]				= $temp;
			}
		}
		$this->jsonOutput['statusValue']	= $arr;
    }

    private function valueLatestPeriod(){    
        //PROVIDING GLOBALS
        global $ohqList;
    
        $qpart  =$this->queryPart." AND ".$this->settingVars->maintable.".".$this->settingVars->period." IN (" . implode(",",filters\timeFilter::getPeriodWithinRange(0 , 1 , $this->settingVars)) . ") ";
        $query = "SELECT ".$this->skuID.
                    ",".$this->storeID.
                    ",SUM(OHQ) ".
                    "FROM ".$this->settingVars->tablename.$qpart." ".
                    "GROUP BY 1,2";
        $result = $this->queryVars->queryHandler->runQuery($query,$this->queryVars->linkid,db\ResultTypes::$TYPE_ARRAY);
        foreach($result as $key=>$data)
        {
            $sin                    = $data[0];
            $sno                    = $data[1];
            $ohqList[$sin][$sno]    = $data[2];
        }
    }

    private function parepareGridData(){
		$arr   = array();	
        $qpart = $this->queryPart . " AND ".$this->settingVars->maintable.".".$this->settingVars->period." IN (" . implode("," , filters\timeFilter::getPeriodWithinRange(0 , 8 , $this->settingVars)) . ") ";
        $instockType = $_REQUEST["instockType"];
    
        $query = "SELECT ".$this->skuID.
                        ",MAX(".$this->skuName.")skuname".
                        ",".$this->storeID.
                        ",MAX(".$this->storeName.")sname".
                        ",SUM(".$this->settingVars->ProjectVolume.")qty".
                        ",SUM(GSQ)gqs".
                        ",(SUM(".$this->settingVars->ProjectVolume.")/SUM(GSQ))sellthru".
                        ",MAX(WHPK_Qty)" .
                        ",AVG(OHQ)aohq".
                        ",AVG(MSQ)amsq".
                        ",SUM(OHQ) ".
                        "FROM ".$this->settingVars->tablename.$qpart." AND AVS=1 ".
                        "GROUP BY 1,3 LIMIT 1000";
        $result = $this->queryVars->queryHandler->runQuery($query,$this->queryVars->linkid,db\ResultTypes::$TYPE_ARRAY);

		if(is_array($result) && !empty($result))
		{
			foreach($result as $key=>$data)
			{			
				$temp		= array();
				$sin        = $data[0];
				$sno        = $data[2];
				$sellthru   = number_format($data[6] * 100, 1);
				$shelffill  = $data[9] > 0 ? number_format(($data[8] / $data[9]) * 100, 1) : 0;
				$woh        = $data[4]>0 ? number_format(($ohqList[$sin][$sno]/($data[4]/8)),2) : 0;
				
				$addData    = false;        
				if ($instockType == 1){
					if ($sellthru > 95) $addData = true;
				}elseif ($instockType == 2){
					if ($sellthru >= 80 && $sellthru <= 95) $addData = true;
				}elseif ($instockType == 3){
					if ($sellthru < 80)  $addData = true;
				}
				
				if($addData==true)
				{					
					$temp["SKU"]		= $data[0];
					$temp["SKUNAME"]	= htmlspecialchars($data[1]);
					$temp["STORE"]		= $data[2];
					$temp["STORENAME"]	= htmlspecialchars($data[3]);
					$temp["QTY"]		= $data[4];
					$temp["GSQ"]		= $data[5];
					$temp["SELLTHRU"]	= $sellthru;
					$temp["SUPP"]		= $data[7];
					$temp["OHQ"]		= $data[10];
					$temp["SHELFFILL"]	= $shelffill;
					$temp["WOH"]		= $woh;
					$arr[]				= $temp;					
				}				
			}			
		}
		$this->jsonOutput['gridValue']	= $arr;
    }
    
    
    /*** OVERRIDING PARENT CLASS'S getAll FUNCTION ***/
    public function getAll(){
		$tablejoins_and_filters = parent::getAll();
			    
        if ($_REQUEST["STORE"] != "")
            $tablejoins_and_filters .= " AND ".$this->storeID."=".$_REQUEST['STORE'];
    
        if ($_REQUEST["SKU"] != "")
            $tablejoins_and_filters .= " AND ".$this->skuID."=".$_REQUEST['SKU'];
        
        return $tablejoins_and_filters;
    }
}   
?> 