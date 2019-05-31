<?php

namespace classes;

use projectsettings;
use SimpleXMLElement;
use datahelper;
use filters;
use db;
use config;

class NewPage extends config\UlConfig{
    /*****
    * Default gateway function, should be similar in all DATA CLASS
    * arguments:
    *  $settingVars [project settingsGateway variables]
    * @return $xmlOutput with all data that should be sent to client app
    *****/
    public function go($settingVars){
	$this->initiate($settingVars); //INITIATE COMMON VARIABLES
	$this->queryPart    = $this->getAll();
	
	$this->gridData();
	
	return $this->xmlOutput;
    }


    private function gridData(){    
	/* ************************************************************************************************
	 * COLLECT -12 TO +11 DATES
	 * IF DATE '1ST JAN' IS SELECTED THEN IT WILL COLLECT 12 DATES BEFORE '1ST JAN' , CALLING LY DATES,
	 * AND 11 DATES AFTER '1ST JAN' , CALLING TY DATES
	 **************************************************************************************************/
	global $datesAfter , $datesBefore , $selection , $allDates;
	global $datesAfterLY , $datesBeforeLY , $selectionLY , $allDatesLY;
	
	$this->getTY_and_LY_usingPromoStart();
	
	//COLLECT LY SALES
	$querypart_ly 	= $this->queryPart ." AND CONCAT(". $this->settingVars->weekperiod .",". $this->settingVars->yearperiod .") IN (". implode(",",$allDatesLY) .") ";
	$query 		= "SELECT ". $this->settingVars->dateField ." AS MYDATE ".
				",MAX(". $this->settingVars->weekperiod .") AS WEEK".
				",MAX(". $this->settingVars->yearperiod .") AS YEAR".
				",SUM(". $this->settingVars->ProjectValue .") AS SALES ".
				"FROM ". $this->settingVars->tablename . $querypart_ly." ".
				"GROUP BY MYDATE ".
				"ORDER BY MYDATE";
	$result 	= $this->queryVars->queryHandler->runQuery($query,$this->queryVars->linkid,db\ResultTypes::$TYPE_OBJECT);
	$lySales 	= array();
	foreach($result as $key=>$data)
	{
	    $index 		= $data['WEEK'].($data['YEAR']+1);
	    $lySales[$index] 	= $data['SALES'];
	}
	
	
	//COLLECT TY SALES
	$querypart_ty 	= $this->queryPart." AND CONCAT(" . $this->settingVars->weekperiod .",". $this->settingVars->yearperiod.") IN (".implode(",",$allDates).")";
	$query 		= "SELECT ". $this->settingVars->dateField ." AS MYDATE ".
				",MAX(". $this->settingVars->weekperiod .") AS WEEK".
				",MAX(". $this->settingVars->yearperiod .") AS YEAR".
				",SUM(". $this->settingVars->ProjectValue .") AS SALES ".
				"FROM ". $this->settingVars->tablename . $querypart_ty." ".
				"GROUP BY MYDATE ".
				"ORDER BY MYDATE";
	$result_ty 	= $this->queryVars->queryHandler->runQuery($query,$this->queryVars->linkid,db\ResultTypes::$TYPE_OBJECT);
	$tySales 	= array();
	foreach($result_ty as $key=>$data)
	{
	    $index 		= $data['WEEK'].$data['YEAR'];
	    $tySales[$index] 	= $data['SALES'];
	}
	
	
	//COLLECT LY SALES FOR DATES BEFORE SELECTED DATE
	$querypart_temp = $this->queryPart ." AND CONCAT(". $this->settingVars->weekperiod .",". $this->settingVars->yearperiod .") IN (".implode(",",$datesBeforeLY).")";
	$query 		= "SELECT SUM(". $this->settingVars->ProjectValue .") AS SALES ".
				"FROM ".$this->settingVars->tablename . $querypart_temp." ";
	$result 	= $this->queryVars->queryHandler->runQuery($query,$this->queryVars->linkid,db\ResultTypes::$TYPE_OBJECT);
	$data 		= $result[0];
	$prePromo_LY 	= $data['SALES'];
    
	
	
	//COLLECT TY SALES FOR DATES AFTER SELECTED DATE
	$querypart_temp = $this->queryPart ." AND CONCAT(". $this->settingVars->weekperiod .",". $this->settingVars->yearperiod .") IN (".implode(",",$datesBefore).")";
	$query 		= "SELECT SUM(". $this->settingVars->ProjectValue .") AS SALES ".
				"FROM ". $this->settingVars->tablename . $querypart_temp ." ";
	$result 	= $this->queryVars->queryHandler->runQuery($query,$this->queryVars->linkid,db\ResultTypes::$TYPE_OBJECT);
	$data 		= $result[0];
	$prePromo_TY 	= $data['SALES']; 
	
	
	
	foreach($result_ty as $key=>$data)
	{
	    $index = $data['WEEK'].$data['YEAR'];
	    
	    $postPromo_var_pct = $prePromo_LY>0?(($prePromo_TY/$prePromo_LY)-1)*100:0;
	    if(in_array($index,array_merge($datesBefore)))
	    {
		$bsln_ty = $tySales[$index];
	    }
	    else
	    {
		$bsln_ty = $lySales[$index]*(1+($postPromo_var_pct/100));
	    }
	    
	    $value = $this->xmlOutput->addChild('gridData');
			$value->addChild('MYDATE',	$data['MYDATE']);
			$value->addChild('TY',		$tySales[$index]);
			$value->addChild('LY',		$lySales[$index]);
			$value->addChild('BASELINE_TY',	number_format($bsln_ty,2,'.',''));
	}
	
	$additionalInfo = $this->xmlOutput->addChild('additional');
			    $additionalInfo->addChild('prePromo_TY',	number_format($prePromo_TY,0,'.',''));
			    $additionalInfo->addChild('prePromo_LY',	number_format($prePromo_LY,0,'.',''));
			    $additionalInfo->addChild('postPromo_PCT',	number_format($postPromo_var_pct,2,'.',''));
    }



    private function getTY_and_LY_usingPromoStart(){
	//PROVIDING GLOBALS
	global $datesAfter,$datesBefore,$selection,$allDates;
	global $datesAfterLY,$datesBeforeLY,$selectionLY,$allDatesLY;
	$allDates 	= array();
	$allDatesLY 	= array();
	
	
	// COLLECTING TY DATES [ TY = 0 TO +11 -- 0 is $promoStart ]
	$query 		= "SELECT ". $this->settingVars->dateField ." AS MYDATE ".
				",MAX(". $this->settingVars->weekperiod .") AS WEEK".
				",MAX(". $this->settingVars->yearperiod .") AS YEAR ".
				"FROM ". $this->settingVars->tablename . $this->queryPart." ".
				"AND ".  $this->settingVars->dateField .">='". $_REQUEST['promoStart'] ."' ".
				"GROUP BY MYDATE ".
				"ORDER BY MYDATE ASC ".
				"LIMIT 11";
	$result 	= $this->queryVars->queryHandler->runQuery($query,$this->queryVars->linkid,db\ResultTypes::$TYPE_OBJECT);
	$datesAfter 	= array();
	$datesAfterLY 	= array();
	foreach($result as $key=>$data)
	{
	    array_push($datesAfter,$data['WEEK'].$data['YEAR']);
	    $lyDate 	= $data['WEEK'].($data['YEAR']-1);
	    array_push($datesAfterLY,$lyDate);
	}
	
	
	// COLLECTING LY DATES [ LY = -12 TO -1 -- 0 is $promoStart ]
	$query 	    	= "SELECT ". $this->settingVars->dateField ." AS MYDATE ".
				",MAX(". $this->settingVars->weekperiod .") AS WEEK".
				",MAX(". $this->settingVars->yearperiod .") AS YEAR ".
				"FROM ". $this->settingVars->tablename . $this->queryPart." ".
				"AND ". $this->settingVars->dateField. "<'". $_REQUEST['promoStart'] ."' ".
				"GROUP BY MYDATE ".
				"ORDER BY MYDATE DESC ".
				"LIMIT 12";
	$result     	= $this->queryVars->queryHandler->runQuery($query,$this->queryVars->linkid,db\ResultTypes::$TYPE_OBJECT);
	$datesBefore 	= array();
	$datesBeforeLY 	= array();
	foreach($result as $key=>$data)
	{
	    array_push($datesBefore,$data['WEEK'].$data['YEAR']);
	    $lyDate 	= $data['WEEK'].($data['YEAR']-1);
	    array_push($datesBeforeLY,$lyDate);
	}
	
	/*** USE THE FOLLOWING CODE SNIPPET IF SELECTION AND SELECTION_LY IS NEEDED ***/
	// COLLECTING SELECTION YEAR-WEEKS [0-- selected date]
	/*$query 	= "SELECT ". $this->settingVars->dateField ." AS MYDATE ".
				",MAX(". $this->settingVars->weekperiod) AS WEEK".
				",MAX(". $this->settingVars->yearperiod) AS YEAR ".
				"FROM ". $this->settingVars->tablename . $this->queryPart." ".
				"AND ". $this->settingVars->dateField ."='". $_REQUEST['promoStart'] ."' ".
				"GROUP BY MYDATE ".
				"ORDER BY MYDATE ASC ".
				"LIMIT 11";
	$result 	= $this->queryVars->queryHandler->runQuery($query,$this->queryVars->linkid,db\ResultTypes::$TYPE_OBJECT);
	$selection 	= array();
	$selectionLY 	= array();
	foreach($result as $key=>$data)
	{
	    array_push($selection,$data['WEEK'].$data['YEAR']);
	    $lyDate 	= $data['WEEK'].($data['YEAR']-1);
	    array_push($selectionLY,$lyDate);
	}
	*/
	
	
	//CONCATE TWO ARRAYS TO GET ALL DATES
	$allDates 	= array_merge($datesBefore,$datesAfter);
	$allDatesLY 	= array_merge($datesBeforeLY,$datesAfterLY);
	
	/** USEFULL WHEN DEBUGGING , PLEASE DON'T DELETE **/
	//print implode("##",$allDates);exit;
	//print implode("##",$allDatesLY);exit;
    }
}
?> 