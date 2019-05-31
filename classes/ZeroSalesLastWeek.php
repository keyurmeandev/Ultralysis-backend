<?php
namespace classes;
use db;
use filters;
use config;

class ZeroSalesLastWeek extends config\UlConfig{

    private $skuID,$skuName,$storeID,$storeName,$pageID;
    
    public function go($settingVars){
		$this->initiate($settingVars);//INITIATE COMMON VARIABLES

		$this->pageName = $_REQUEST["pageName"];
		$this->settingVars->pageName = $this->pageName; // set pageName in settingVars
		$this->skuID = $this->settingVars->dataArray[$this->settingVars->pageArray[$this->pageName]["SKU_ACCOUNT"]]['ID'];
		$this->skuName = $this->settingVars->dataArray[$this->settingVars->pageArray[$this->pageName]["SKU_ACCOUNT"]]['NAME'];
		$this->storeID = $this->settingVars->dataArray[$this->settingVars->pageArray[$this->pageName]["STORE_ACCOUNT"]]['ID'];
		$this->storeName = $this->settingVars->dataArray[$this->settingVars->pageArray[$this->pageName]["STORE_ACCOUNT"]]['NAME'];
		$this->inDepotField = $this->settingVars->dataArray[$this->settingVars->pageArray[$this->pageName]["STORE_WHS"]]['NAME'];
		$this->onOrderField = $this->settingVars->dataArray[$this->settingVars->pageArray[$this->pageName]["STORE_ORDER"]]['NAME'];
		$this->inTransField = $this->settingVars->dataArray[$this->settingVars->pageArray[$this->pageName]["STORE_TRANS"]]['NAME'];
	
		if(empty($_REQUEST["pageID"])) $this->pageID = 1;
		else
			$this->pageID = $_REQUEST["pageID"];
		
		$this->queryPart = $this->getAll();
		
		$action	= $_REQUEST["action"];
		switch($action) 
		{
			case "reload": $this->Reload();	break; 
			case "skuchange": $this->changesku();	break;
		}
		return $this->jsonOutput;
    }
	
	
    private function Reload(){	
		$this->valueLatestPeriod();
		$this->prepareGridData();  //adding to output 	
    }
    
    private function changesku(){
		//$this->valueLatestPeriod();
		//$this->prepareGridData();
		$this->prepareChartData(); //adding to ouput
		$this->idleStHealthChk(); //adding to output
    }
		
    private function prepareChartData(){				
		$arr	= array();
		$qpart	    = $this->queryPart." AND ".$this->settingVars->maintable.".".$this->settingVars->period." IN (".implode("," , filters\timeFilter::getPeriodWithinRange(0 , $this->pageID , $this->settingVars)).") ";
		$query	= "SELECT ".$this->settingVars->maintable.".".$this->settingVars->period." AS PERIOD".
					",SUM(".$this->settingVars->ProjectVolume.") AS QTY".
					",SUM(OHQ) AS OHQ".
					",SUM(MSQ) MSQ".
					",SUM(GSQ) AS GSQ ".
					"FROM ".$this->settingVars->tablename.$qpart." ".
					"GROUP BY PERIOD ".
					"ORDER BY PERIOD ASC";  	
		$result	= $this->queryVars->queryHandler->runQuery($query,$this->queryVars->linkid,db\ResultTypes::$TYPE_OBJECT);		
		if(is_array($result) && !empty($result))
		{
			foreach($result as $key=>$data)
			{
				$temp				= array();
				$temp["ACCOUNT"]	= $data['PERIOD'];
				$temp["QTY"]		= (int)$data['QTY'];
				$temp["OHQ"]		= (int)$data['OHQ'];
				$temp["MSQ"]		= (int)$data['MSQ'];
				$temp["GSQ"]		= (int)$data['GSQ'];
				$arr[]				= $temp;
			}	
		}
		$this->jsonOutput['chartValue'] = $arr;
    }	
	
    private function idleStHealthChk(){
		$arr	= array();
		$qpart	= $this->queryPart." AND ".$this->settingVars->maintable.".".$this->settingVars->period." IN (".implode("," , filters\timeFilter::getPeriodWithinRange(0 , $this->pageID , $this->settingVars)).") ";
		$query	    = "SELECT ITEM_STATUS AS ISTATUS".
					",CWO_FLAG AS CWFLAG".
					",TSI AS TSI".
					",VSI AS VSI".
					",Order_Book_Flag AS OBFLAG ".
					"FROM ".$this->settingVars->tablename.$qpart;  
		//echo $query;exit;
		$result	= $this->queryVars->queryHandler->runQuery($query,$this->queryVars->linkid,db\ResultTypes::$TYPE_OBJECT);		
		if(is_array($result) && !empty($result))
		{
			foreach($result as $key=>$data)
			{
				$temp	= array();
				$temp["itemStatus"]	= $data['ISTATUS'];
				$temp["cancelwout"]	= $data['CWFLAG'];
				$temp["traited"]	= $data['TSI'];
				$temp["valid"]		= $data['VSI'];
				$temp["orderbFlag"]	= $data['OBFLAG'];
				$arr[]				= $temp;
			}
		}
		$this->jsonOutput['statusValue']	= $arr;
		
		$qpart 	 	= $this->queryPart." AND ".$this->settingVars->maintable.".".$this->settingVars->period." IN (".implode("," , filters\timeFilter::getPeriodWithinRange(0 , $this->pageID , $this->settingVars)).") ";
		$query		= "SELECT SUM(".$this->settingVars->ProjectVolume.")".
					",SUM(MSQ) ".
					"FROM ".$this->settingVars->tablename.$qpart;  	
		$result 	= $this->queryVars->queryHandler->runQuery($query,$this->queryVars->linkid,db\ResultTypes::$TYPE_ARRAY);		
		if(is_array($result) && !empty($result))
		{
			foreach($result as $key=>$data)
			{	
				$val 	= $data[1]>0 ? number_format(($data[0]/$data[1])*100,1,'.',',') : 0;
				$temp	= array();
				$temp["shelfFrate"]	= $val;
				$arr[]	= $temp;
			}
		}
		$this->jsonOutput['statusValue']	= $arr;
    }
	
	
    private function valueLatestPeriod(){	
		//PROVIDING GLOBALS
		global $OHQ_LW_List,$STList,$SWList,$SOList,$LOList,$OHQList,$qtyList;
		
		$OHQ_LW_List	= array();
		$STList		= array();
		$SWList		= array();
		$SOList		= array();
		$LOList		= array();
		$OHQList	= array();
		$qtyList	= array();
		
		$qpart = $this->queryPart." AND ".$this->settingVars->maintable.".".$this->settingVars->period." IN (".implode("," , filters\timeFilter::getPeriodWithinRange(0 , $this->pageID , $this->settingVars)).") ";   
		
		$query= "SELECT ".$this->skuID.
			   ",".$this->storeID.
			   ",SUM(OHQ)".
			   ",SUM(".$this->inTransField.")StoreTrans".
			   ",SUM(".$this->inDepotField.")StoreWhs".
			   ",SUM(".$this->onOrderField.")StoreOrder".
			   ",SUM(OHQ * ".$this->settingVars->maintable.".UNIT_RETAIL)".
			   ",SUM(QTY)myqty ".
			   "FROM ".$this->settingVars->tablename.$qpart." ".
			   "GROUP BY 1,2 LIMIT 1000";
		//echo $query;exit;
		$result = $this->queryVars->queryHandler->runQuery($query,$this->queryVars->linkid,db\ResultTypes::$TYPE_ARRAY);		
		if(is_array($result) && !empty($result))
		{
			foreach($result as $key=>$data)
			{
				$SIN			= $data[0];
				$sno			= $data[1];
				$OHQ_LW_List[$SIN][$sno]	= $data[2];
				$STList[$SIN][$sno]		= $data[3];
				$SWList[$SIN][$sno]		= $data[4];
				$SOList[$SIN][$sno]		= $data[5];
				$LOList[$SIN][$sno]		= $data[6];
				$OHQList[$SIN][$sno]	= $data[6];
				$qtyList[$SIN][$sno]	= $data[7];
			}
		}
    }
    
    
	private function prepareGridData(){	
		global $OHQ_LW_List,$STList,$SWList,$SOList,$LOList,$OHQList,$qtyList;
		
		$qpart 		= $this->queryPart." AND ".$this->settingVars->maintable.".".$this->settingVars->period." IN (".implode("," , filters\timeFilter::getPeriodWithinRange(1 , $this->pageID , $this->settingVars)).") ";   
		$query		= "SELECT ".$this->skuID.
					",MAX(".$this->skuName.")skuname".
					",".$this->storeID.
					",MAX(".$this->storeName.")sname".
					",SUM(".$this->settingVars->ProjectVolume.")myqty".
					",SUM((CASE WHEN ".$this->settingVars->maintable.".".$this->settingVars->period." IN (".implode("," , filters\timeFilter::getPeriodWithinRange(1 , $this->pageID , $this->settingVars)).") AND AVS = 1 AND ".$this->settingVars->ProjectVolume."<1 THEN 1 ELSE 0 END)*OHQ)ohq".
					",MAX(avs) ".
					"FROM ".$this->settingVars->tablename.$qpart." ".
					"GROUP BY 1,3 ".
					"HAVING myqty<1 AND ohq>1";  
		//echo $query;exit;
		$result		= $this->queryVars->queryHandler->runQuery($query,$this->queryVars->linkid,db\ResultTypes::$TYPE_ARRAY);		
		if(is_array($result) && !empty($result))
		{
			foreach($result as $key=>$data)
			{	
				$SIN	    = $data[0];
				$sno	    = $data[2];		
				$QTYOHQ	    = ($data[5]>0) ? ($data[4]/$data[5])*100 : 0;
				
				if($_REQUEST["SALESVIEW"]=="false")
				{
					// do not include any combination where sales for the LATEST/Max week > 0
					if($qtyList[$SIN][$sno]<=0)
					{
						$temp				= array();
						$temp["SKU"]		= $data[0];
						$temp["SKUNAME"]	= htmlspecialchars($data[1]);
						$temp["STORE"]		= $data[2];
						$temp["STORENAME"]	= htmlspecialchars($data[3]);
						$temp["QTY"]		= (int)$data[4];
						$temp["OHQLW"]		= (int)$data[5];
						$temp["LOST"]		= (int)$LOList[$SIN][$sno];
						$temp["OHQTW"]		= (int)$OHQ_LW_List[$SIN][$sno];
						$temp["STRTRANS"]	= (int)$STList[$SIN][$sno];
						$temp["STRWHS"]		= (int)$SWList[$SIN][$sno];
						$temp["STRORD"]		= (int)$SOList[$SIN][$sno];
						$temp["OHQTWP"]		= (int)$OHQList[$SIN][$sno];
						$temp["GFE"]		= (int)$GFE;
						$arr[]				= $temp;
						/* if($this->pageID<4)
						{
							if($data[4]==0 && $data[6]==1)
							{
								$temp				= array();
								$temp["SKU"]		= $data[0];
								$temp["SKUNAME"]	= htmlspecialchars($data[1]);
								$temp["STORE"]		= $data[2];
								$temp["STORENAME"]	= htmlspecialchars($data[3]);
								$temp["QTY"]		= $data[4];
								$temp["OHQLW"]		= $data[5];
								$temp["LOST"]		= $LOList[$SIN][$sno];
								$temp["OHQTW"]		= $OHQ_LW_List[$SIN][$sno];
								$temp["STRTRANS"]	= $STList[$SIN][$sno];
								$temp["STRWHS"]		= $SWList[$SIN][$sno];
								$temp["STRORD"]		= $SOList[$SIN][$sno];
								$temp["OHQTWP"]		= $OHQList[$SIN][$sno];
								$temp["GFE"]		= $GFE;
								$arr[]				= $temp;
							}
						}
						else if($this->pageID==4)
						{
							if($data[4]==0)
							{
								$temp				= array();
								$temp["SKU"]		= $data[0];
								$temp["SKUNAME"]	= htmlspecialchars($data[1]);
								$temp["STORE"]		= $data[2];
								$temp["STORENAME"]	= htmlspecialchars($data[3]);
								$temp["QTY"]		= $data[4];
								$temp["OHQLW"]		= $data[5];
								$temp["LOST"]		= $LOList[$SIN][$sno];
								$temp["OHQTW"]		= $OHQ_LW_List[$SIN][$sno];
								$temp["STRTRANS"]	= $STList[$SIN][$sno];
								$temp["STRWHS"]		= $SWList[$SIN][$sno];
								$temp["STRORD"]		= $SOList[$SIN][$sno];
								$temp["OHQTWP"]		= $OHQList[$SIN][$sno];
								$temp["GFE"]		= $GFE;
								$arr[]				= $temp;
							}
						} */
					}
				}
				/* else if($_REQUEST["SALESVIEW"]=="true")
				{
					if($this->pageID<4)
					{
						if($data[4]==0 && $data[6]==1)
						{
							$temp				= array();
							$temp["SKU"]		= $data[0];
							$temp["SKUNAME"]	= htmlspecialchars($data[1]);
							$temp["STORE"]		= $data[2];
							$temp["STORENAME"]	= htmlspecialchars($data[3]);
							$temp["QTY"]		= $data[4];
							$temp["OHQLW"]		= $data[5];
							$temp["LOST"]		= $LOList[$SIN][$sno];
							$temp["OHQTW"]		= $OHQ_LW_List[$SIN][$sno];
							$temp["STRTRANS"]	= $STList[$SIN][$sno];
							$temp["STRWHS"]		= $SWList[$SIN][$sno];
							$temp["STRORD"]		= $SOList[$SIN][$sno];
							$temp["OHQTWP"]		= $OHQList[$SIN][$sno];
							$temp["GFE"]		= $GFE;
							$arr[]				= $temp;
						}
					}
					else if($this->pageID==4)
					{
						if($data[4]==0)
						{
							$temp				= array();
							$temp["SKU"]		= $data[0];
							$temp["SKUNAME"]	= htmlspecialchars($data[1]);
							$temp["STORE"]		= $data[2];
							$temp["STORENAME"]	= htmlspecialchars($data[3]);
							$temp["QTY"]		= $data[4];
							$temp["OHQLW"]		= $data[5];
							$temp["LOST"]		= $LOList[$SIN][$sno];
							$temp["OHQTW"]		= $OHQ_LW_List[$SIN][$sno];
							$temp["STRTRANS"]	= $STList[$SIN][$sno];
							$temp["STRWHS"]		= $SWList[$SIN][$sno];
							$temp["STRORD"]		= $SOList[$SIN][$sno];
							$temp["OHQTWP"]		= $OHQList[$SIN][$sno];
							$temp["GFE"]		= $GFE;
							$arr[]				= $temp;
						}
					}				
				} */
			}
		}
		
		$this->jsonOutput['gridValue']	= $arr;
    }   
	

    /*** OVERRIDING PARENT CLASS'S getAll FUNCTION ***/	
    public function getAll(){
		$tablejoins_and_filters       = parent::getAll();    
						
		if($_REQUEST["STORE"]!="") 
			$tablejoins_and_filters.=" AND ".$this->storeID."=".$_REQUEST['STORE'];	      
			
		if($_REQUEST["SKU"]!="") 
			$tablejoins_and_filters.=" AND ".$this->skuID."=".$_REQUEST['SKU'];	      
			
		if($_REQUEST["depot"]!="All" && $_REQUEST["depot"]!="") 
			$tablejoins_and_filters.=" AND ".$this->settingVars->maintable.whseID."=".$_REQUEST['depot'];	
				
		if($_REQUEST["FF"]=="1") 
			$tablejoins_and_filters.=" AND (FER_GFE!=0 OR FER_GFE!=null) ";	
		else if($_REQUEST["FF"]=="2") 
			$tablejoins_and_filters.=" AND (FER_GFE=0 OR FER_GFE=null) ";	

		return  $tablejoins_and_filters;
    }
}
?> 