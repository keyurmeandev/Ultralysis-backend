<?php

namespace classes\totalmults;

use datahelper;
use projectsettings;
use filters;
use utils;
use db;
use config;

class WeeklyShareTracker extends config\UlConfig {

    public function go($settingVars) {
	
		/* if($_REQUEST['intialRequest'] == "YES")
			$_REQUEST['FromWeek'] = $_REQUEST['ToWeek']; */
	
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES
		
        $this->queryPart = $this->getAll(); //USES OWN getAll FUNCTION

        $this->prepareGridData(); //ADDING TO OUTPUT

        return $this->jsonOutput;
    }

    function prepareGridData() {

		$ferreroSales = 0;
		$act 		= $_REQUEST['ACCOUNT'];
    	$account 	= $this->settingVars->dataArray[$act]["NAME"];
		$act2		= $_REQUEST['SUBCAT'];
    	$subcate 	= $this->settingVars->dataArray[$act2]["NAME"];
    	//$year 		= $this->settingVars->dataArray['YEAR']['NAME'];
    	//$week 		= $this->settingVars->dataArray['WEEK']['NAME'];
		
		$weeklyShareTracker = array();
        $weeks = $_REQUEST['timeFrame'];
        
        $latestWeeks = filters\timeFilter::getYearWeekWithinRange(0, $weeks, $this->settingVars);
				
		$query = "SELECT $account AS ACCOUNT, " .
            "SUM((CASE WHEN $subcate = 'SUB-CAT TOTAL' THEN 1 ELSE 0 END)*" . $this->settingVars->ProjectValue . ") AS SALES, " .
            $this->settingVars->maintable.".mydate AS MYDATE, " .
			"SUM((CASE WHEN ($subcate != 'SUB-CAT TOTAL') THEN 1 ELSE 0 END)*".$this->settingVars->ProjectValue.") AS FERREROSALES " .
			"FROM  " . $this->settingVars->tablename . $this->queryPart .
			" AND CONCAT(" . $this->settingVars->yearperiod . "," . $this->settingVars->weekperiod . ") IN  (" . implode(",", $latestWeeks) . ") " .
			" AND $account <> '' " .
			"GROUP BY MYDATE, ACCOUNT " .
			"ORDER BY ACCOUNT ASC,MYDATE ASC";			
		//echo $query;exit();
		$result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
		//$ferreroSales = array_sum(array_column($result,'FERREROSALES'));
		if(isset($result) && !empty($result))
		{			
			$allAccounts = array_unique(str_replace("\r","",array_column($result,'ACCOUNT')));
			//$allAccounts = array_unique(array_column($result,'ACCOUNT'));
			$allMyDates = array_unique(array_column($result,'MYDATE'));
			
			$getAccounts = array();
			$weeklyShareTracker = array();
			
			foreach($allAccounts as $key => $subcat)
				if(!in_array($subcat, $getAccounts))
					$getAccounts[] = array("data"=>$subcat,"label"=>"ACCOUNT_".$key,"key"=>$key);
			
			// foreach($allAccounts as $key => $subcat)
				// $getAccounts[] = array("data"=>$subcat,"label"=>"ACCOUNT_".$key,"key"=>$key);
				
			foreach($allMyDates as $key => $mydate)
				$getMydates[] = array("data"=>$mydate);
				
			foreach($getAccounts as $k=>$cat){
				foreach($getMydates as $key=>$mdate){
					$searchKey = array_search(str_replace("\r","",$result[$cat['key']]['ACCOUNT']), array_column($getAccounts, 'data'));					
					$searchMydateKey = array_search($result[$cat['key']]['MYDATE'], array_column($getMydates,'data'));
					if(is_numeric($searchMydateKey))
						$getMyDate = $result[$cat['key']]['MYDATE'];
					else
						$getMyDate = $mdate['data'];;
					
					$total = $result[$cat['key']]['SALES'] + $result[$cat['key']]['FERREROSALES'];
					$weeklyShareTracker[$getAccounts[$searchKey]["label"]][] = array(
						'ACCOUNT' => $result[$cat['key']]['ACCOUNT'],
						'SALES' => (int)$result[$cat['key']]['SALES'],
						'MYDATE' => $getMyDate,
						'FERREROSALES' => (int)$result[$cat['key']]['FERREROSALES'],
						'SC_PERCENT' => (($total) > 0)?number_format(($result[$cat['key']]['SALES']/($total))*100,1,".","") : 0,
						'F_PERCENT' => (($total) > 0)?number_format(($result[$cat['key']]['FERREROSALES']/($total))*100,1,".","") : 0					
					);
					$cat['key']++;
				}
			}
		}
		//print("<pre>");print_r($weeklyShareTracker);exit;
		$this->jsonOutput["gridValue"] = $weeklyShareTracker;
		$this->jsonOutput["allAccounts"] = $getAccounts ;		
	}
}

?>