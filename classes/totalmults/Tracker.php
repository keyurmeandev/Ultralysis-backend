<?php

namespace classes\totalmults;

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

		$arr 		= array();
		$arrChart 	= array();
		$cols 		= array();
		
    	
			if($_REQUEST['PROMOTION'] == 'YES')
			{
				$latestWeek = filters\timeFilter::getYearWeekWithinRange(0, 12, $this->settingVars);	

				//filters\timeFilter::prepareTyLyMydateRange($this->settingVars); //Total Weeks
				
				$qPart = '';
				if (isset($_REQUEST["FS"]) && $_REQUEST["FS"] != ''){
					$storeStock 	= $this->settingVars->dataArray['F18']['NAME'];
					$availInst 		= $this->settingVars->dataArray['F15']['NAME'];
					$depotService	= $this->settingVars->dataArray['F14']['NAME'];

					$getLdates = array();
					foreach($latestWeek as $lw)
					{			
						$getLdates[] = "MAX((CASE WHEN CONCAT(" . $this->settingVars->yearperiod .",". $this->settingVars->weekperiod . ")=".$lw." THEN 1 ELSE 0 END)*$account) AS Tracker_L".$lw."";			
					}
					
					$query = "SELECT ".$this->settingVars->timetable.".mydate AS MYDATE " .
							",CONCAT(" . $this->settingVars->yearperiod . "," . $this->settingVars->weekperiod . ") as YEARDATE".
			                " FROM " . $this->settingVars->timetable .
			                " WHERE CONCAT(" . $this->settingVars->yearperiod . "," . $this->settingVars->weekperiod . ") IN  (" . implode(",", $latestWeek) . ") GROUP BY  MYDATE, YEARDATE ORDER BY YEARDATE DESC";
					//echo $query;exit;
			        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
					if (is_array($result) && !empty($result)) {	
						$getmydates = array();
						foreach ($result as $key => $value) {
							$getmydates["Tracker_L".$value['YEARDATE']] = $value['MYDATE'];
						}
					}

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
								//" AND (" . filters\timeFilter::$tyWeekRange . " OR " . filters\timeFilter::$lyWeekRange . ") GROUP BY SKUID, SKU, MYDATES ORDER BY MYDATES ASC, SALES DESC";
								" AND  CONCAT(" . $this->settingVars->yearperiod . "," . $this->settingVars->weekperiod . ") IN (" . implode(",", $latestWeek) . ") GROUP BY SKUID, SKU, MYDATES ORDER BY MYDATES ASC, SALES DESC";
			
					
					$result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);

					
					$temp1	= array();
					$temp2	= array();
					$temp3	= array();
					$temp4	= array();
					$temp5	= array();
					$latestWeek = array_reverse($latestWeek);
					$count = 0;

					foreach($latestWeek as $lw)
					{												
							$cols[] 	= array('data'=>'L_'.$lw,'label'=>date("jS M Y",strtotime($getmydates['Tracker_L'.$lw])));
						
							$value = $result[$count];
							$temp1["L_".str_replace("-","",$lw)]	= (int)$value['SALES'];
							$temp2["L_".str_replace("-","",$lw)]	= (int)$value['QTY'];
							$temp3["L_".str_replace("-","",$lw)]	= (int)$value['STORESTOCK'];
							$temp4["L_".str_replace("-","",$lw)] 	= number_format($value['STOREINSTOCK'],1, '.', '');
             			    $temp5["L_".str_replace("-","",$lw)]	= number_format($value['DEPOTSERVICE'],1, '.', '');							
							$account = $lw;							
							
							$temp = array();
							$temp['Tracker_L'.$lw] 	= number_format($value['Tracker_L'.$lw], 1, '.', '');
							$temp['MYDATE'] = $lw;
							$temp['QTY'] = (int)$value['QTY'];
							$temp['STORESTOCK'] = (int)$value['STORESTOCK'];
							$temp['STOREINSTOCK'] = number_format($value['STOREINSTOCK'],1, '.', '');
							$temp['DEPOTSERVICE'] = number_format($value['DEPOTSERVICE'],1, '.', '');
							$arrChart[] = $temp;
							$count++;							
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
					
					$this->jsonOutput['TrackerGrid'] 	= $arr;
			        $this->jsonOutput['TrackerChart'] 	= $arrChart;
			        $this->jsonOutput['Tracker_L'] 		= $cols;
				}
			}
			else
			{
						$act = $_REQUEST['ACCOUNT'];
						$account = $this->settingVars->dataArray[$act]['NAME'];
						
				        $latestWeek = filters\timeFilter::getYearWeekWithinRange(0, 12, $this->settingVars);
				        asort($latestWeek);    
						        
						$getLdates = array();
						foreach($latestWeek as $lw)
						{			
							$getLdates[] = "MAX((CASE WHEN CONCAT(" . $this->settingVars->yearperiod .",". $this->settingVars->weekperiod . ")=".$lw." THEN 1 ELSE 0 END)*$account) AS Tracker_L".$lw."";			
						}
						
						$query = "SELECT ".$this->settingVars->timetable.".mydate AS MYDATE " .
								",CONCAT(" . $this->settingVars->yearperiod . "," . $this->settingVars->weekperiod . ") as YEARDATE".
				                " FROM " . $this->settingVars->timetable .
				                " WHERE CONCAT(" . $this->settingVars->yearperiod . "," . $this->settingVars->weekperiod . ") IN  (" . implode(",", $latestWeek) . ") GROUP BY  MYDATE, YEARDATE ORDER BY YEARDATE DESC";
						//echo $query;exit;
				        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
						if (is_array($result) && !empty($result)) {	
							$getmydates = array();
							foreach ($result as $key => $value) {
								$getmydates["Tracker_L".$value['YEARDATE']] = $value['MYDATE'];
							}
						}
						
						//print("<pre>");print_r($getLdates);exit;
				        //MAIN TABLE QUERY
				        $query = "SELECT ".$this->settingVars->maintable.".skuID AS SKUID " .
				                ",sku AS SKU " .
				                ",MAX(".$this->settingVars->ProjectValue.") AS SALES" .
				                ",MAX(".$this->settingVars->ProjectVolume.") AS QTY," .
								implode(",",$getLdates).
				                " FROM " . $this->settingVars->tablename . $this->queryPart .
				                " AND CONCAT(" . $this->settingVars->yearperiod . "," . $this->settingVars->weekperiod . ") IN  (" . implode(",", $latestWeek) . ") GROUP BY SKUID, SKU ORDER BY SALES DESC";
						//echo $query;exit;
				        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
				        
						$arr = array();
						$cols = array();
								
						if (is_array($result) && !empty($result)) {	
										
							foreach ($result as $key => $value) {
								
								$temp 				= array();
								$temp['SKUID'] 		= $value['SKUID'];
								$temp['SKU'] 		= $value['SKU'];
								$temp['SALES'] 		= number_format($value['SALES'], 1, '.', '');
								$temp['QTY'] 		= number_format($value['QTY'], 1, '.', '');
								foreach($latestWeek as $lw)
								{															
									if($key == 0)
										$cols[] 	= array('data'=>'Tracker_L'.$lw,'label'=>date("jS M Y",strtotime($getmydates['Tracker_L'.$lw])));
									
									$temp['Tracker_L'.$lw] 	= number_format($value['Tracker_L'.$lw], 1, '.', '');
								}
								$arr[] 				= $temp;
								
							}
							
						} // end if
						//print("<pre>");print_r($arr);
				        $this->jsonOutput['TrackerGrid'] = $arr;
				        $this->jsonOutput['Tracker_L'] = $cols; //(count($arr) > 0) ? array_keys($arr) : [];
    		}
	    }
		
}

?>