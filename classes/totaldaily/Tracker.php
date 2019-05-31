<?php

namespace classes\totaldaily;

use filters;
use db;
use config;

class Tracker extends config\UlConfig {
    /*     * ***
	 * go()
     * Default gateway function, should be similar in all DATA CLASS
     * arguments:
     * $settingVars [project settingsGateway variables]
     * @return $this->jsonOutput with all data that should be sent to client app
     * *** */

    public function go($settingVars) {
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES
        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll
		$this->trackerGrid();
		
		if(!isset($_REQUEST['SKULIST']) && isset($_REQUEST['PROMOTION']) && $_REQUEST['PROMOTION'] == 'YES')
			$this->getSkuList();
				
        return $this->jsonOutput;
    }
	
	public function getSkuList()
	{
		$query = "SELECT ".$this->settingVars->maintable.".skuID AS data,sku AS label FROM ".$this->settingVars->tablename." ".$this->queryPart." GROUP BY data,label HAVING label <>'' ORDER BY label ASC ";
		$result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
		$this->jsonOutput['getSkuList'] = $result;
	}
	
	/**
	 * trackerGrid()
     * This Function is used to retrieve list based on set parameters     
	 * @access private	
     * @return $this->jsonOutput with all data that should be sent to client app     
     */
    private function trackerGrid() {
				
		$latestDays = filters\timeFilter::getPeriodWithinRange(0, filters\timeFilter::$daysTimeframe, $this->settingVars);
				
		$arr 		= array();
		$arrChart 	= array();
		$cols 		= array();
		
		if(isset($_REQUEST['PROMOTION']))
		{
			if($_REQUEST['PROMOTION'] == 'YES')
			{
				// filters\timeFilter::prepareTyLyMydateRange($this->settingVars); //Total Weeks
				
				$qPart = '';
				if (isset($_REQUEST["FS"]) && $_REQUEST["FS"] != ''){
					$storeStock 	= $this->settingVars->dataArray['F18']['NAME'];
					$availInst 		= $this->settingVars->dataArray['F15']['NAME'];
					$depotService	= $this->settingVars->dataArray['F14']['NAME'];
											
					$latestDay = filters\timeFilter::getPeriodWithinRange(0, 1, $this->settingVars);
					
					//MAIN TABLE QUERY			
					$query = "SELECT ".$this->settingVars->maintable.".skuID AS SKUID " .
								",sku AS SKU " .
								",".$this->settingVars->dateField." AS MYDATES " .
								",SUM(".$this->settingVars->ProjectValue.") AS SALES" .
								",SUM(".$this->settingVars->ProjectVolume.") AS QTY" .
								",SUM($storeStock) AS STORESTOCK".
								",AVG($availInst) AS STOREINSTOCK".
								",AVG($depotService) AS DEPOTSERVICE".
								" FROM " . $this->settingVars->tablename . $this->queryPart .
								" AND (" . filters\timeFilter::$tyWeekRange . " OR " . filters\timeFilter::$lyWeekRange . ") GROUP BY SKUID, SKU, MYDATES ORDER BY MYDATES ASC, SALES DESC";
					//echo $query;exit;
					$result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
					
					$temp1	= array();
					$temp2	= array();
					$temp3	= array();
					$temp4	= array();
					$temp5	= array();
					$latestDays = array_reverse($latestDays);
					foreach($latestDays as $lw)
					{															
						if($key == 0)
							$cols[] 	= array('data'=>'L_'.str_replace("-","",$lw),'label'=>date("jS M Y",strtotime($lw)));					
											
						if (is_array($result) && !empty($result)) {
							$searchKey = array_search($lw,array_column($result,"MYDATES"));
						}
						
						if($searchKey)
						{
							$value = $result[$searchKey];
							$temp1["L_".str_replace("-","",$lw)]	= (int)$value['SALES'];
							$temp2["L_".str_replace("-","",$lw)]	= (int)$value['QTY'];
							$temp3["L_".str_replace("-","",$lw)]	= (int)$value['STORESTOCK'];
							$account = $lw;
							if (in_array($account, filters\timeFilter::$tyDaysRange)) { //$numberFrom AND $numberTo COMES HANDY HERE
								$temp4["L_".str_replace("-","",$lw)] 	= number_format($value['STOREINSTOCK'],1, '.', '');
								$temp5["L_".str_replace("-","",$lw)]	= number_format($value['DEPOTSERVICE'],1, '.', '');							
							}

							$temp = array();
							$temp['MYDATE'] = $lw;
							$temp['QTY'] = (int)$value['QTY'];
							$temp['STORESTOCK'] = (int)$value['STORESTOCK'];
							$temp['STOREINSTOCK'] = number_format($value['STOREINSTOCK'],1, '.', '');
							$temp['DEPOTSERVICE'] = number_format($value['DEPOTSERVICE'],1, '.', '');
							$arrChart[] = $temp;
							
						}
						else
						{
							$temp1["L_".str_replace("-","",$lw)] = $temp2["L_".str_replace("-","",$lw)] = $temp3["L_".str_replace("-","",$lw)] = $temp4["L_".str_replace("-","",$lw)] = $temp5["L_".str_replace("-","",$lw)] = 0;
							
							$temp = array();
							$temp['MYDATE'] = $lw;
							$temp['QTY'] = $temp['STORESTOCK'] = $temp['STOREINSTOCK'] = $temp['DEPOTSERVICE'] = 0;
							$arrChart[] = $temp;
						}											
					}
					$temp1['label'] = "SALES ".$this->settingVars->currencySign;
					$temp2['label'] = "QTY";
					$temp3['label'] = "STORE STOCK";
					$temp4['label'] = "STORE INSTOCK %";
					$temp5['label'] = "DEPOT SERVICE %";
					
					$arr[0]	= $temp1;				
					$arr[1]	= $temp2;				
					$arr[2]	= $temp3;				
					$arr[3]	= $temp4;				
					$arr[4]	= $temp5;
				}
			}
			else
			{
				filters\timeFilter::fetchPeriodWithinRange(0, filters\timeFilter::$daysTimeframe, $this->settingVars); //To get $mydateRange
				
				$act = $_REQUEST['ACCOUNT'];
				$account = $this->settingVars->dataArray[$act]['NAME'];
				
				//asort($latestDays);
				
				$getLdates = array();
				foreach($latestDays as $date)
				{			
					$getLdates[] = "MAX((CASE WHEN " . $this->settingVars->period . "='".$date."' THEN 1 ELSE 0 END)*$account) AS DATE".str_replace("-","",$date);
				}
				
				$qPart = implode(",",$getLdates);
				
				//MAIN TABLE QUERY
				$query = "SELECT ".$this->settingVars->maintable.".skuID AS SKUID " .
					",sku AS SKU " .
					",SUM(".$this->settingVars->ProjectValue.") AS SALES" .
					",SUM(".$this->settingVars->ProjectVolume.") AS QTY," .
					$qPart.
					" FROM " . $this->settingVars->tablename . $this->queryPart .
					" AND " . filters\timeFilter::$mydateRange . " GROUP BY SKUID, SKU ORDER BY SALES DESC";
				$result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
				
				if (is_array($result) && !empty($result)) {
					foreach ($result as $key => $value) {
						
						$temp 				= array();
						$temp['SKUID'] 		= $value['SKUID'];
						$temp['SKU'] 		= $value['SKU'];
						$temp['SALES'] 		= number_format($value['SALES'], 1, '.', '');
						$temp['QTY'] 		= number_format($value['QTY'], 1, '.', '');
						foreach($latestDays as $lw)
						{															
							if($key == 0)
								$cols[] 	= array('data'=>'Tracker_L'.str_replace("-","",$lw),'label'=>date("jS M Y",strtotime($lw)));
							
							$temp['Tracker_L'.str_replace("-","",$lw)] 	= number_format($value['DATE'.str_replace("-","",$lw)], 1, '.', '');
						}
						$arr[] 				= $temp;
						
					}
				}
			}		
		}
        $this->jsonOutput['TrackerGrid'] 	= $arr;
        $this->jsonOutput['TrackerChart'] 	= $arrChart;
        $this->jsonOutput['Tracker_L'] 		= $cols;
    }
		
}

?>