<?php
namespace classes;

use filters;
use db;
use config;

class WohTracker extends config\UlConfig{
	private $skuID,$skuName,$storeID,$storeName;

	public function go($settingVars){
		$this->initiate($settingVars); //INITIATE COMMON VARS
		
		$this->skuID 	    = key_exists('ID' , $this->settingVars->dataArray[$_REQUEST['SKU_FIELD']]) ? $this->settingVars->dataArray[$_REQUEST['SKU_FIELD']]['ID'] : $this->settingVars->dataArray[$_REQUEST['SKU_FIELD']]['NAME'];
		$this->skuName 	    = $this->settingVars->dataArray[$_REQUEST['SKU_FIELD']]['NAME'];
		$this->storeID 	    = key_exists('ID' , $this->settingVars->dataArray[$_REQUEST['STORE_FIELD']]) ? $this->settingVars->dataArray[$_REQUEST['STORE_FIELD']]['ID'] : $this->settingVars->dataArray[$_REQUEST['STORE_FIELD']]['NAME'];
		$this->storeName    = $this->settingVars->dataArray[$_REQUEST['STORE_FIELD']]['NAME'];

	
		$action = $_REQUEST["action"];
		switch ($action) 
		{
			case "changegrid":	return $this->ChangeGrid();	break;
			case "changesin":	return $this->ChangeSIN();	break;
			case "reload":		return $this->Reload();		break;
		}			
	}



	
	private function ChangeGrid(){
		$this->queryPart = $this->getAll(); //USES OWN getAll FUNCTION
		$this->valueFunc();
		$this->SkuGrid();
	
		return $this->xmlOutput;	
	}
	
	
	private function ChangeSIN(){
		$this->queryPart = $this->getAll(); //USES OWN getAll FUNCTION
		$this->valueFunc();
		$this->TextGrid();
	
		return $this->xmlOutput;	
	}
	
	
	private function Reload(){
		$this->queryPart = $this->getAll(); //USES OWN getAll FUNCTION
		$this->sinvalueFunc();
		$this->SinGrid();
	
		return $this->xmlOutput;
	}	
	
	private function SkuGrid(){
		global $qtyArr;
	
		$TotalWeek	 = $_REQUEST["TimeFrame"];
		$qpart		 = $this->queryPart." AND ".$this->settingVars->maintable.".".$this->settingVars->period." IN (".implode("," , filters\timeFilter::getPeriodWithinRange(0 , 1 , $this->settingVars)).") AND VSI = 1"; 
		$query		 = "SELECT ".$this->skuID." AS SKUID".
							",".$this->storeID." AS SNO".
							",".$this->skuName." AS SKU".
							",".$this->storeName." AS SNAME, ".
							"SUM(Store_Hand) AS STORE_HAND, ".
							"SUM(MSQ) AS MSQ ".
							"FROM ".$this->settingVars->tablename.$this->queryPart." ".
							"GROUP BY SKUID,SNO,SKU,SNAME";
		$result 	 = $this->queryVars->queryHandler->runQuery($query,$this->queryVars->linkid,db\ResultTypes::$TYPE_OBJECT);
		foreach($result as $key=>$data) 
		{
			$advSales 	= number_format($avgQty / 7, 2, '.', '');

			if ($advSales > 0) {
				$wc 	= number_format(($data['STORE_HAND'] / $advSales), 1, '.', '');
				$wcio 	= number_format((($data['STORE_HAND'] + $data['MSQ'] + $data[6] + $data[7]) / $advSales), 2);
			} else {
				$wc 	= 0;
				$wcio 	= 0;
			}

			$sin 		= $data['SKUID'];
			$sno 		= $data['SNO'];

			$qty 		= $qtyArr[$sin][$sno];
			$avgQty 	= ($qty/ $TotalWeek);
			
			$weekshand	= ($avgQty>0)? number_format($data['STORE_HAND']/$avgQty, 1, '.', '') : 0;     
			
			$check = false;      
			/*** ZERO UNITS SOLD IN SELECTED TIME FRAME ***/
			switch($_REQUEST['ID'])
			{
				case 1:
					$check = $qty<1;
					break;
				case 2:
					$check = $weekshand<2 && $qty>0;
					break;
				case 3:
					$check = $weekshand>=2 && $weekshand<4;
					break;
				case 4:
					$check = $weekshand>=4 && $weekshand<8;
					break;
				case 5:
					$check = $weekshand>=8;
					break;
			}
			if ($check)
			{
				$value = $this->xmlOutput->addChild('Skugrid');
				$value->addChild('SID',			$data['SKUID']);
				$value->addChild('SNO',			$data['SNO']);
				$value->addChild('SKU',			$data['SKU']);
				$value->addChild('SNAME',		$data['SNAME']);
				$value->addChild('Hand',		$data['STORE_HAND']);
				$value->addChild('Qty',			$qty);
				$value->addChild('AvgQty',		$avgQty);
				$value->addChild('WEEKSHAND',	$weekshand);
				$value->addChild('MSQ',			$data['MSQ']);
			}	
		}
		
		return $value;
	}
	
	
	
	private function SinGrid(){
		//PROVIDING GLOBALS
		global $qtyArrList;
	
		$TotalWeek	= $_REQUEST["TimeFrame"];
		filters\timeFilter::grabLatestPeriod($_REQUEST["TimeFrame"] , $this->settingVars);
				
		$query		= "SELECT ".$this->skuID." AS SKUID".
							",".$this->skuName." AS SKU ".
							"FROM ".$this->settingVars->tablename.$this->queryPart." AND ".filters\timeFilter::$tyWeekRange." ".
							"GROUP BY SKUID,SKU ";
		$result 	= $this->queryVars->queryHandler->runQuery($query,$this->queryVars->linkid,db\ResultTypes::$TYPE_ARRAY);
		foreach($result as $key=>$data)
		{
			$sin 	= $data[0];
			$qty 	= $qtyArrList[$sin];
			$value 	= $this->xmlOutput->addChild("singrid");
			$value->addChild("SID", $data[0]);
			$value->addChild("SKU", htmlspecialchars_decode($data[1]));
			$value->addChild("Qty", $qty);
		}   
	}
	
	
	private function TextGrid() {
		global $qtyArr;	
		$TotalWeek	= $_REQUEST["TimeFrame"];;
		$count1=0;$count2=0;$count3=0;$count4=0;$count5=0;
		
		
		$qpart		 = $this->queryPart." AND ".$this->settingVars->maintable.".".$this->settingVars->period." IN (".implode("," , filters\timeFilter::getPeriodWithinRange(0 , 1 , $this->settingVars)).") AND VSI = 1"; 
		$query		 = "SELECT ".$this->skuID." AS SKUID".
							",".$this->storeID." AS SNO".
							",".$this->skuName." AS SKU".
							",".$this->storeName." AS SNAME".
							",SUM(Store_Hand) AS STORE_HAND ".
							"FROM ".$this->settingVars->tablename.$qpart." ".
							"GROUP BY SKUID,SNO,SKU,SNAME";
		$result 	 = $this->queryVars->queryHandler->runQuery($query,$this->queryVars->linkid,db\ResultTypes::$TYPE_OBJECT);
		foreach($result as $key=>$data)
		{
			$advSales 		= number_format($avgQty / 7, 2, '.', '');
	
			if ($advSales > 0) {
				$wc 		= number_format(($data['STORE_HAND'] / $advSales), 1, '.', '');
				$wcio 		= number_format((($data['STORE_HAND'] + $data[5] + $data[6] + $data[7]) / $advSales), 2);
			} else {
				$wc 		= 0;
				$wcio 		= 0;
			}
	
			$sin 			= $data['SKUID'];
			$sno 			= $data['SNO'];
	
			$qty 			= $qtyArr[$sin][$sno];
			$avgQty 		= ($qty/ $TotalWeek);
			
			if($qty<1) $count1++;
			$weekshand		= ($avgQty>0) ? number_format($data['STORE_HAND']/$avgQty, 1, '.', '') : 0;     
				   
			if($weekshand<2 && $qty>0) $count2++;
			else if($weekshand>=2 && $weekshand<4) $count3++;
			else if($weekshand>=4 && $weekshand<8) $count4++;
			else if($weekshand>=8) $count5++;
			
		}
		
			$total			= $count1+$count2+$count3+$count4+$count5;
			$value 			= $this->xmlOutput->addChild('Textgrid');
								$value->addChild('id',	1);
								$value->addChild('comment',	"Zero Units Sold in ".$_REQUEST["TimeFrame"]." Week(s)");
								$value->addChild('mycase',	$count1);
								$value->addChild('percent',	$total != 0 ? ($count1/$total)*100 : 0);
								$value->addChild('color',	"0xF50A0A");
			
			$value 			= $this->xmlOutput->addChild('Textgrid');
								$value->addChild('id',	2);
								$value->addChild('comment',	"Less than 2 Weeks OH");
								$value->addChild('mycase',	$count2);
								$value->addChild('percent',	$total != 0 ? ($count2/$total)*100 : 0);
								$value->addChild('color',	"0xF5740A");
		
			$value 			= $this->xmlOutput->addChild('Textgrid');
								$value->addChild('id',	3);
								$value->addChild('comment',	"Between 2 and 4 Weeks OH");
								$value->addChild('mycase',	$count3);
								$value->addChild('percent',	$total != 0 ? ($count3/$total)*100 : 0);
								$value->addChild('color',	"0xF5E10A");
				
			$value 			= $this->xmlOutput->addChild('Textgrid');
								$value->addChild('id',	4);
								$value->addChild('comment',	"Between 4 and 8 Weeks OH");
								$value->addChild('mycase',	$count4);
								$value->addChild('percent',	$total != 0 ? ($count4/$total)*100 : 0);
								$value->addChild('color',	"0xD2F50A");
				
			$value 			= $this->xmlOutput->addChild('Textgrid');
								$value->addChild('id',	5);
								$value->addChild('comment',	"More than 8 Weeks OH");
								$value->addChild('mycase',	$count5);
								$value->addChild('percent',	$total != 0 ? ($count5/$total)*100 : 0);
								$value->addChild('color',	"0x1EF50A");
	}
	
	
	private function sinvalueFunc() {		
		//PROVIDING GLOBALS
		global $qtyArrList;
		$qtyArrList = array();
		
		filters\timeFilter::grabLatestPeriod($_REQUEST["TimeFrame"] , $this->settingVars);
		$query 		= "SELECT ".$this->skuID." AS SKUID".
							",SUM(".$this->settingVars->ProjectVolume.") ".
							"FROM ".$this->settingVars->tablename.$this->queryPart." AND ".filters\timeFilter::$tyWeekRange." ".
							"GROUP BY SKUID";
		$result 	= $this->queryVars->queryHandler->runQuery($query,$this->queryVars->linkid,db\ResultTypes::$TYPE_ARRAY);
		foreach($result as $key=>$data)
		{
			$sin 				= $data[0];
			$qtyArrList[$sin] 	= $data[1];
		}
	}
	
	
	private function valueFunc(){
		//PROVIDING GLOBALS
		global $qtyArr;
		$qtyArr = array();
		
		filters\timeFilter::grabLatestPeriod($_REQUEST["TimeFrame"] , $this->settingVars);
		$query 	= "SELECT ".$this->skuID." AS SKUID".
							",".$this->storeID." AS SNO".
							",SUM(".$this->settingVars->ProjectVolume.") AS QTY ".
							"FROM ".$this->settingVars->tablename.$this->queryPart." AND ".filters\timeFilter::$tyWeekRange." ".
							"GROUP BY SKUID,SNO";
		$result = $this->queryVars->queryHandler->runQuery($query,$this->queryVars->linkid,db\ResultTypes::$TYPE_OBJECT);
		foreach($result as $key=>$data)
		{
			$qtyArr[$data['SKUID']][$data['SNO']] = $data['QTY'];
		}	
	}
	
	
	
	/**** OVERRIDE PARENT CLASS'S getAll FUNCTION ****/
	public function getAll() {	
		$tablejoins_and_filters       	 = $this->settingVars->link;	
	
		if (isset($_REQUEST["FS"]) && $_REQUEST["FS"] != ''){
           $tablejoins_and_filters   	.= filters\productAndMarketFilter::include_product_and_market_filters($this->queryVars,$this->settingVars);
        }
	
		if(!empty($_REQUEST["SIN"])){
			$tablejoins_and_filters		.=" AND skuID_ROLLUP2='".$_REQUEST["SIN"]."'";
		}
	
		 	
		return $tablejoins_and_filters;
	}
}
?> 