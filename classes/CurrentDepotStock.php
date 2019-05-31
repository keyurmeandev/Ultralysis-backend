<?php
namespace classes;
use filters;
use db;
use config;

class CurrentDepotStock extends config\UlConfig{

	public function go($settingVars){
		$this->initiate($settingVars); //INITIATE COMMON VARIABLES
		$action = $_REQUEST["action"];
		
		switch($action) 
		{
			//case "ChangeCategory": 	return $this->ChangeCategory();	break;
			//case "reload":			return $this->Reload();			break;
			
			case "ChangeCategory":
				$this->ChangeCategory();
				break;
			case "reload":
				$this->Reload();			
				break;
			default:
				$this->depotList(); 
				$this->Reload();
				break;
		}
		
		return $this->jsonOutput;
	}
  
	private function Reload(){    
		filters\timeFilter::getYTD($this->settingVars);
		$this->queryPart = $this->getAll();
		
		$this->gridFunction($this->settingVars->pageArray[$_REQUEST['pageName']]['GRID_FIELD']['gridCategory'] , 'gridCategory');		//ADDING TO OUTPUT
		$this->gridFunction($this->settingVars->pageArray[$_REQUEST['pageName']]['GRID_FIELD']['gridBrand'] , 'gridBrand');		//ADDING TO OUTPUT
		//$this->RadarChart();								//ADDING TO OUTPUT
	}
  
	private function ChangeCategory(){    
		filters\timeFilter::getYTD($this->settingVars);
		$this->queryPart = $this->getAll();
		
		$this->gridFunction($this->settingVars->pageArray[$_REQUEST['pageName']]['GRID_FIELD']['gridBrand'],"gridBrand");		//ADDING TO OUTPUT
		//$this->RadarChart();								//ADDING TO OUTPUT
	}
  
	private function depotList(){		
		$query	= "SELECT depotID FROM ".$this->settingVars->maintable." ".
						"GROUP BY depotID ".
						"ORDER BY depotID ASC";  			
		$result	= $this->queryVars->queryHandler->runQuery($query,$this->queryVars->linkid,db\ResultTypes::$TYPE_ARRAY);
      
		//$value 	= $this->xmlOutput->addChild("depot");
		//$value->addChild("account", 	"All");
	
		foreach($result as $key=>$data)
		{
			//$value 	= $this->xmlOutput->addChild("depot");
		    //$value->addChild("account", 	htmlspecialchars_decode($data[0]));
			$account[]['account'] = htmlspecialchars_decode($data[0]);
		}
		$this->jsonOutput["depotList"] = $account;
		//return $value;
	} 
       
	private function gridFunction($gridField , $xmlTag){
		$id 		= key_exists('ID' , $this->settingVars->dataArray[$gridField]) ? $this->settingVars->dataArray[$gridField]['ID'] : $this->settingVars->dataArray[$gridField]['NAME'];
		$account 	= $this->settingVars->dataArray[$gridField]['NAME'];
		
		$query	= "SELECT $id AS ID".
						",$account AS ACCOUNT".
						",SUM((CASE WHEN ".$this->settingVars->maintable.".".$this->settingVars->period." IN (". filters\timeFilter::getPeriodWithinRangeTwin($this->settingVars , 1 , 1).") THEN 1 ELSE 0 END)* ".$this->settingVars->ProjectVolume.") AS QTY".
						",SUM(DHQ) AS DHQ".
						",SUM(DOQ) AS DOQ".
						",SUM(StoreTrans) AS STORE_HAND".
						",SUM(StoreHand) AS STORE_TRANS ".
						" FROM ".$this->settingVars->tablename.$this->queryPart.
						"GROUP BY ID,ACCOUNT ".
						"ORDER BY QTY DESC";
		
		$result	= $this->queryVars->queryHandler->runQuery($query,$this->queryVars->linkid,db\ResultTypes::$TYPE_OBJECT);
		foreach($result as $key=>$data)
		{
			if($data['QTY']>0 && $data['DHQ']>0 && $data['DOQ']>0 && $data['STORE_HAND']>0 && $data['STORE_TRANS']>0)
			{
				$total	= ($data['DHQ']+$data['DOQ']);
				$cover	= ($data['QTY']>0) ? ($total/$data['QTY']) : 0;
				$total2	= ($data['STORE_HAND']+$data['STORE_TRANS']);
				  
				/*$value	= $this->xmlOutput->addChild($xmlTag);
				$value->addChild("ID",				htmlspecialchars_decode($data['ID']));
				$value->addChild("ACCOUNT",			htmlspecialchars_decode($data['ACCOUNT']));
				$value->addChild("COST_QTY",		$data['QTY']);
				$value->addChild("COST_DHQ",		$data['DHQ']);
				$value->addChild("COST_DOQ",		$data['DOQ']);
				$value->addChild("TOTAL",			$total);
				$value->addChild("COVER",			$cover);
				$value->addChild("TOTAL2",			$total2);*/
				
				$tmpArr['ID'] = htmlspecialchars_decode($data['ID']);
				$tmpArr["ACCOUNT"] = htmlspecialchars_decode($data['ACCOUNT']);
				$tmpArr['COST_QTY'] = $data['QTY'];
				$tmpArr['COST_DHQ'] = $data['DHQ'];
				$tmpArr['COST_DOQ'] = $data['DOQ'];
				$tmpArr['TOTAL'] = $total;
				$tmpArr['COVER'] = $cover;
				$tmpArr['TOTAL2'] = $total2;
				$tmpArr['DSO_PER'] = ($data['DOQ']/$total)*100;
				$tmpArr['DOH_PER'] = ($data['DHQ']/$total)*100;
				$gridData[] = $tmpArr;
			}
		}
		$this->jsonOutput[$xmlTag] = $gridData;
	}
    
    /**** OVERRIDE PARENT CLASS'S getAll FUNCTION ****/  
	public function getAll(){
		$tablejoins_and_filters = $this->settingVars->link;
		
		if (isset($_REQUEST["FS"]) && $_REQUEST["FS"] != ''){
           $tablejoins_and_filters   .= filters\productAndMarketFilter::include_product_and_market_filters($this->queryVars,$this->settingVars);
        }
		  
		if($_REQUEST["depot"]!="All" && $_REQUEST["depot"]!="") 
			$tablejoins_and_filters.=" AND ".$this->settingVars->maintable.".depotID=".$_REQUEST['depot'];	 
		    
		return  $tablejoins_and_filters;
	}
}
?>