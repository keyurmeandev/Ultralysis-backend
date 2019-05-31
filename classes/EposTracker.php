<?php
namespace classes;
	
use filters;
use db;
use config;
	
class EposTracker extends config\UlConfig{
	 
	public function go($settingVars){
		$this->initiate($settingVars); //INITIATE COMMON VARIABLES
		$this->prepareData();
		
		return $this->xmlOutput;
	}
	
	private function prepareData(){
		$this->LatestWeek();
		$this->queryPart = $this->getAll();
		
		$this->PieChartValue(); 	//ADDING TO OUTPUT  
		$this->GridLW();   			//ADDING TO OUTPUT
		$this->LatestWeekFunc();   	//ADDING TO OUTPUT
		$this->PieChart(); 			//ADDING TO OUTPUT
		
		filters\timeFilter::collectLatestYearSSCover($this->settingVars);
		$this->GridTable1(); 		//ADDING TO OUTPUT
		$this->GridTable2(); 		//ADDING TO OUTPUT	
	}
	
	
	private function PieChartValue(){		
		$latestArr	= array();
		$brand		= array();	
		$total		= 0;
		
		$query		=	"SELECT brandrange AS ACCOUNT,".
							"SUM( (CASE WHEN ".filters\timeFilter::$tyWeekRange." THEN 1 ELSE 0 END) * ".$this->settingVars->ProjectValue.") AS TYEAR ". 
							 "FROM ".$this->settingVars->tablename.$this->queryPart." ".
							 "AND brandrange<>'OTHER' ".
							 "GROUP BY ACCOUNT ".
							 "ORDER BY TYEAR DESC";  	
		$result		= $this->queryVars->queryHandler->runQuery($query,$this->queryVars->linkid,db\ResultTypes::$TYPE_OBJECT);
		foreach($result as $key=>$data)
		{	  
			$total	= $total+$data['TYEAR']; 	 
			array_push($brand,$data['ACCOUNT']);
			array_push($latestArr,$data['TYEAR']);
		}
			
			
		$i=1;
		$xml = $this->xmlOutput->addChild("gridXML");
		$xml->addChild("ACCOUNT",	"Latest Wk");
		for($j=0;$j<count($brand);$j++)
		{	 	
			$per	= ($total>0) ? ($latestArr[$j]/$total)*100 : 0;
			$xml->addChild("brand$i", 		$brand[$j]);
			$xml->addChild("brandcost$i", 	$per);
			$i++;
		}			  
	}
	   
	private function GridLW(){
		global $ToYear,$FromYear,$ToWeek,$FromWeek,$FromWeek4,$FromWeek13,$FromWeek52,$LastYear4,$LastYear13,$LastYear52,$latestweek4Val,$latestweek13Val,$latestweek52Val;//time vars
		
		$latestweek4Val		= 0;
		$latestweek13Val	= 0;
		$latestweek52Val	= 0;
		
		$total				= 0;
		$total2				= 0;
		$total3				= 0;
		
		$latestArr			= array();
		$latestArr2			= array();
		$latestArr3			= array();
		$brand				= array();
		
		$query				= "SELECT brandrange, ";
		if($ToYear==$LastYear4){
			$query	.= "SUM( (CASE WHEN ".$this->settingVars->yearperiod." = '".$ToYear."' AND ".$this->settingVars->weekperiod."<=".$ToWeek." AND ".$this->settingVars->weekperiod.">=".$FromWeek4." THEN 1 ELSE 0 END) * ".$this->settingVars->ProjectValue.") AS MCOST,";
			$query	.= "SUM( (CASE WHEN ".$this->settingVars->yearperiod." = '".($ToYear-1)."' AND ".$this->settingVars->weekperiod."<=".$ToWeek." AND ".$this->settingVars->weekperiod.">=".$FromWeek4." THEN 1 ELSE 0 END) * ".$this->settingVars->ProjectValue." ) AS PCOST, ";
			
			$query	.= "SUM( (CASE WHEN ".$this->settingVars->yearperiod." = '".$ToYear."' AND ".$this->settingVars->weekperiod."<=".$ToWeek." AND ".$this->settingVars->weekperiod.">=".$FromWeek4." THEN 1 ELSE 0 END) * ".$this->settingVars->ProjectVolume.") AS MCOST2,"; 
			$query	.= "SUM( (CASE WHEN ".$this->settingVars->yearperiod." = '".($ToYear-1)."' AND ".$this->settingVars->weekperiod."<=".$ToWeek." AND ".$this->settingVars->weekperiod.">=".$FromWeek4." THEN 1 ELSE 0 END) * ".$this->settingVars->ProjectVolume." ) AS PCOST2, ";
		}else
		{
			$query	.= "SUM( (CASE WHEN ".$this->settingVars->yearperiod." = '".$ToYear."' AND ".$this->settingVars->weekperiod."<=".$ToWeek." OR ".$this->settingVars->yearperiod." = '".$LastYear4."' AND ".$this->settingVars->weekperiod.">=".$FromWeek4." THEN 1 ELSE 0 END) * ".$this->settingVars->ProjectValue.") AS MCOST,";
			$query	.= "SUM( (CASE WHEN ".$this->settingVars->yearperiod." = '".($ToYear-1)."' AND ".$this->settingVars->weekperiod."<=".$ToWeek." OR ".$this->settingVars->yearperiod." = '".($LastYear4-1)."' AND ".$this->settingVars->weekperiod.">=".$FromWeek4." THEN 1 ELSE 0 END) * ".$this->settingVars->ProjectValue." ) AS PCOST, ";
			$query	.= "SUM( (CASE WHEN ".$this->settingVars->yearperiod." = '".$ToYear."' AND ".$this->settingVars->weekperiod."<=".$ToWeek." OR ".$this->settingVars->yearperiod." = '".$LastYear4."' AND ".$this->settingVars->weekperiod.">=".$FromWeek4." THEN 1 ELSE 0 END) * ".$this->settingVars->ProjectVolume.") AS MCOST2,";
			$query	.= "SUM( (CASE WHEN ".$this->settingVars->yearperiod." = '".($ToYear-1)."' AND ".$this->settingVars->weekperiod."<=".$ToWeek." OR ".$this->settingVars->yearperiod = '".($LastYear4-1)."' AND $this->settingVars->weekperiod.">=".$FromWeek4." THEN 1 ELSE 0 END) * ".$this->settingVars->ProjectVolume." ) AS PCOST2, ";
		}
		
		 
		if($ToYear==$LastYear13)  	  
		{
			$query	.= "SUM( (CASE WHEN ".$this->settingVars->yearperiod." = '".$ToYear."' AND ".$this->settingVars->weekperiod."<=".$ToWeek." AND ".$this->settingVars->weekperiod.">=".$FromWeek13." THEN 1 ELSE 0 END) * ".$this->settingVars->ProjectValue.") AS MCOST3,"; 
			$query	.= "SUM( (CASE WHEN ".$this->settingVars->yearperiod." = '".($ToYear-1)."' AND ".$this->settingVars->weekperiod."<=".$ToWeek." AND ".$this->settingVars->weekperiod.">=".$FromWeek13." THEN 1 ELSE 0 END) * ".$this->settingVars->ProjectValue." ) AS PCOST3,";
			
			$query	.= "SUM( (CASE WHEN ".$this->settingVars->yearperiod." = '".$ToYear."' AND ".$this->settingVars->weekperiod."<=".$ToWeek." AND ".$this->settingVars->weekperiod.">=".$FromWeek13." THEN 1 ELSE 0 END) * ".$this->settingVars->ProjectVolume.") AS MCOST4,";
			$query	.= "SUM( (CASE WHEN ".$this->settingVars->yearperiod." = '".($ToYear-1)."' AND ".$this->settingVars->weekperiod."<=".$ToWeek." AND ".$this->settingVars->weekperiod.">=".$FromWeek13." THEN 1 ELSE 0 END) * ".$this->settingVars->ProjectVolume." ) AS PCOST4, ";
		}
		else
		{
			$query	.= "SUM( (CASE WHEN ".$this->settingVars->yearperiod." = '".$ToYear."' AND ".$this->settingVars->weekperiod."<=".$ToWeek." OR ".$this->settingVars->yearperiod." = '".$LastYear13."' AND ".$this->settingVars->weekperiod.">=".$FromWeek13." THEN 1 ELSE 0 END) * ".$this->settingVars->ProjectValue.") AS MCOST3,";
			$query	.= "SUM( (CASE WHEN ".$this->settingVars->yearperiod." = '".($ToYear-1)."' AND ".$this->settingVars->weekperiod."<=".$ToWeek." OR ".$this->settingVars->yearperiod." = '".($LastYear13-1)."' AND ".$this->settingVars->weekperiod.">=".$FromWeek13." THEN 1 ELSE 0 END) * ".$this->settingVars->ProjectValue." ) AS PCOST3,";
			
			$query	.= "SUM( (CASE WHEN ".$this->settingVars->yearperiod." = '".$ToYear."' AND ".$this->settingVars->weekperiod."<=".$ToWeek." OR ".$this->settingVars->yearperiod." = '".$LastYear13."' AND ".$this->settingVars->weekperiod.">=".$FromWeek13." THEN 1 ELSE 0 END) * ".$this->settingVars->ProjectVolume.") AS MCOST4,"; 
			$query	.= "SUM( (CASE WHEN ".$this->settingVars->yearperiod." = '".($ToYear-1)."' AND ".$this->settingVars->weekperiod."<=".$ToWeek." OR ".$this->settingVars->yearperiod." = '".($LastYear13-1)."' AND ".$this->settingVars->weekperiod.">=".$FromWeek13." THEN 1 ELSE 0 END) * ".$this->settingVars->ProjectVolume." ) AS PCOST4, ";
		}			
		
		if($ToYear==$LastYear52)  	  
		{
			$query	.= "SUM( (CASE WHEN ".$this->settingVars->yearperiod." = '".$ToYear."' AND ".$this->settingVars->weekperiod."<=".$ToWeek." AND ".$this->settingVars->weekperiod.">=".$FromWeek52." THEN 1 ELSE 0 END) * ".$this->settingVars->ProjectValue.") AS MCOST5,"; 
			$query	.= "SUM( (CASE WHEN ".$this->settingVars->yearperiod." = '".($ToYear-1)."' AND ".$this->settingVars->weekperiod."<=".$ToWeek." AND ".$this->settingVars->weekperiod.">=".$FromWeek52." THEN 1 ELSE 0 END) * ".$this->settingVars->ProjectValue." ) AS PCOST5,";
		  
			$query	.= "SUM( (CASE WHEN ".$this->settingVars->yearperiod." = '".$ToYear."' AND ".$this->settingVars->weekperiod."<=".$ToWeek." AND ".$this->settingVars->weekperiod.">=".$FromWeek52." THEN 1 ELSE 0 END) * ".$this->settingVars->ProjectVolume.") AS MCOST6,";
			$query	.= "SUM( (CASE WHEN ".$this->settingVars->yearperiod." = '".($ToYear-1)."' AND ".$this->settingVars->weekperiod."<=".$ToWeek." AND ".$this->settingVars->weekperiod.">=".$FromWeek52." THEN 1 ELSE 0 END) * ".$this->settingVars->ProjectVolume." ) AS PCOST6 ";
		}	
		else
		{
			$query	.= "SUM( (CASE WHEN ".$this->settingVars->yearperiod." = '".$ToYear."' AND ".$this->settingVars->weekperiod."<=".$ToWeek." OR ".$this->settingVars->yearperiod." = '".$LastYear52."' AND ".$this->settingVars->weekperiod.">=".$FromWeek52." THEN 1 ELSE 0 END) * ".$this->settingVars->ProjectValue.") AS MCOST5,";
			$query	.= "SUM( (CASE WHEN ".$this->settingVars->yearperiod." = '".($ToYear-1)."' AND ".$this->settingVars->weekperiod."<=".$ToWeek." OR ".$this->settingVars->yearperiod." = '".($LastYear52-1)."' AND ".$this->settingVars->weekperiod.">=".$FromWeek52." THEN 1 ELSE 0 END) * ".$this->settingVars->ProjectValue." ) AS PCOST5,";
		  
			$query	.= "SUM( (CASE WHEN (".$this->settingVars->yearperiod." = '".$ToYear."' AND ".$this->settingVars->weekperiod."<=".$ToWeek.") OR (".$this->settingVars->yearperiod." = '".$LastYear52."' AND ".$this->settingVars->weekperiod.">=".$FromWeek52.") THEN 1 ELSE 0 END) * ".$this->settingVars->ProjectVolume.") AS MCOST6,";
			$query	.= "SUM( (CASE WHEN ".$this->settingVars->yearperiod." = '".($ToYear-1)."' AND ".$this->settingVars->weekperiod."<=".$ToWeek." OR ".$this->settingVars->yearperiod." = '".($LastYear52-1)."' AND ".$this->settingVars->weekperiod.">=".$FromWeek52." THEN 1 ELSE 0 END) * ".$this->settingVars->ProjectVolume." ) AS PCOST6 ";
		}		
			
		$query	.= " FROM ".$this->settingVars->tablename.$this->queryPart." AND brandrange<>'OTHER' GROUP BY brandrange ORDER BY MCOST DESC";  
		$result	 = $this->queryVars->queryHandler->runQuery($query,$this->queryVars->linkid,db\ResultTypes::$TYPE_ARRAY);
		
		
		$VS4Sum			= 0;
		$YOY1Sum4		= 0;
		$USSum4			= 0;
		$YOY2Sum4		= 0;
		$VS13Sum		= 0;
		$YOY1Sum13		= 0;
		$USSum13		= 0;
		$YOY2Sum13		= 0;
		$VS52Sum		= 0;
		$YOY1Sum52		= 0;
		$USSum52		= 0;
		$YOY2Sum52		= 0;
		
		
		$data2Sum		= 0;
		$data4Sum		= 0;
		$data6Sum		= 0;
		$data8Sum		= 0;
		$data10Sum		= 0;
		$data12Sum		= 0;
		
		foreach($result as $key=>$data)
		{
			if($data[1]==0 && $data[2]==0){}
			else
			{			
				$per1				 = ($data[2]>0) ? (($data[1]-$data[2])/$data[2])*100 : 0;   
				$data2Sum			+= $data[2];	
				$per2 				 = ($data[4]>0) ? (($data[3]-$data[4])/$data[4])*100 : 0;
				$data4Sum			+= $data[4];		   
				$per3				 = ($data[6]>0) ?(($data[5]-$data[6])/$data[6])*100 : 0;
				$data6Sum			+= $data[6];	
				$per4				 = ($data[8]>0) ? (($data[7]-$data[8])/$data[8])*100 : 0;
				$data8Sum			+= $data[8];	   
				$per5				 = ($data[10]>0) ? (($data[9]-$data[10])/$data[10])*100 : 0;
				$data10Sum			+= $data[10];	
				$per6		 		 = ($data[12]>0) ? (($data[11]-$data[12])/$data[12])*100 : 0;
				$data12Sum			+= $data[12];		   
			 
				$latestweek4Val		 = $latestweek4Val+$data[1];
				$latestweek13Val	 = $latestweek13Val+$data[5];
				$latestweek52Val	 = $latestweek52Val+$data[9];
			
				$total				 = $total+$data[1]; 	 
				$total2				 = $total2+$data[5]; 	 
				$total3				 = $total3+$data[9]; 	 
			
				array_push($brand,$data[0]);
				array_push($latestArr,$data[1]);
				array_push($latestArr2,$data[5]);
				array_push($latestArr3,$data[9]);
			
			
				$VS4Sum					+= $data[1];
				$USSum4					+= $data[3];
				$VS13Sum				+= $data[5];
				$USSum13				+= $data[7];
				$VS52Sum				+= $data[9];
				$USSum52				+= $data[11];
			
				$value = $this->xmlOutput->addChild("gridLW");
						$value->addChild("BRAND", 	htmlspecialchars_decode($data[0]));
						$value->addChild("VS4",		$data[1]);
						$value->addChild("4YOY1",	$per1);
						$value->addChild("4US",		$data[3]);
						$value->addChild("4YOY2",	$per2);
						$value->addChild("VS13",	$data[5]);
						$value->addChild("13YOY1",	$per3);
						$value->addChild("13US",	$data[7]);
						$value->addChild("13YOY2",	$per4);
						$value->addChild("VS52",	$data[9]);
						$value->addChild("52YOY1",	$per5);
						$value->addChild("52US",	$data[11]);
						$value->addChild("52YOY2",	$per6);		
			}	 	 
			
		} 
		
		$tper1					 = (($VS4Sum-$data2Sum)/$data2Sum)*100;
		$tper2					 = (($USSum4-$data4Sum)/$data4Sum)*100;
		$tper3					 = (($VS13Sum-$data6Sum)/$data6Sum)*100;
		$tper4					 = (($USSum13-$data8Sum)/$data8Sum)*100;
		$tper5					 = (($VS52Sum-$data10Sum)/$data10Sum)*100;
		$tper6					 = (($USSum52-$data12Sum)/$data12Sum)*100;
		
		$value = $this->xmlOutput->addChild("gridLW");
			$value->addChild("BRAND", 	"Total");
			$value->addChild("VS4",		$VS4Sum);
			$value->addChild("4YOY1",	$tper1);
			$value->addChild("4US",		$USSum4);
			$value->addChild("4YOY2",	$tper2);
			$value->addChild("VS13",	$VS13Sum);
			$value->addChild("13YOY1",	$tper3);
			$value->addChild("13US",	$USSum13);
			$value->addChild("13YOY2",	$tper4);
			$value->addChild("VS52",	$VS52Sum);
			$value->addChild("52YOY1",	$tper5);
			$value->addChild("52US",	$USSum52);
			$value->addChild("52YOY2",	$tper6);		
			
		
		$i	= 1;
		for($j=0;$j<count($brand);$j++)
		{
		   
		   if($brand[$j]<>'OTHER')	
			{
				if($total>0)
				{
					$per		 = ($latestArr[$j]/$total)*100;
					$per2		 = ($latestArr2[$j]/$total2)*100;
					$per3		 = ($latestArr3[$j]/$total3)*100;
				}
				else
				{
					$per		 = 0;
					$per2		 = 0;
					$per3		 = 0;	  
				}
				
				$value = $this->xmlOutput->addChild("gridXML");
					$value->addChild("brand$i",$brand[$j]);
					$value->addChild("brandcost$i",$per);
					
				$value = $this->xmlOutput->addChild("gridXML");
					$value->addChild("brand$i",$brand[$j]);
					$value->addChild("brandcost$i",$per2);
					
				$value = $this->xmlOutput->addChild("gridXML");
					$value->addChild("brand$i",$brand[$j]);
					$value->addChild("brandcost$i",$per3);
			}
			$i++;
		}	
	}
	
	
	private function LatestWeekFunc(){
		global $latestweekVal;
		global $ToYear,$FromYear;
		
		$qpart	= $this->qpart($this->queryPart);
		$query		= "SELECT SUM((CASE WHEN ".$this->settingVars->yearperiod."='".$ToYear."' THEN 1 ELSE 0 END) * ".$this->settingVars->ProjectValue." ) AS TYEAR".
						",SUM((CASE WHEN ".$this->settingVars->yearperiod."='".($FromYear-1)."' THEN 1 ELSE 0 END) * ".$this->settingVars->ProjectValue." ) AS TYEAR2".
						",SUM((CASE WHEN ".$this->settingVars->yearperiod."='".$ToYear."' THEN 1 ELSE 0 END) * ".$this->settingVars->ProjectVolume." ) AS TYEAR3".
						",SUM((CASE WHEN ".$this->settingVars->yearperiod."='".($FromYear-1)."' THEN 1 ELSE 0 END) * ".$this->settingVars->ProjectVolume." ) AS TYEAR4 ".
						"FROM ".$this->settingVars->tablename.$this->queryPart;  
		$result		= $this->queryVars->queryHandler->runQuery($query,$this->queryVars->linkid,db\ResultTypes::$TYPE_ARRAY);
		$data		= $result[0];
		
		$per1= ($data[1]>0) ? (($data[0]-$data[1])/$data[1])*100 : 0;
		$per2= ($data[3]>0) ? (($data[2]-$data[3])/$data[3])*100 : 0;
		
		$value = $this->xmlOutput->addChild("Chart1");
					$value->addChild("account","LY");
					$value->addChild("TYEAR",$data[1]);
		$value = $this->xmlOutput->addChild("Chart1");
					$value->addChild("account","TY");
					$value->addChild("TYEAR",$data[0]);
		$value = $this->xmlOutput->addChild("ChartTitle1");
					$value->addChild("PER",$per1);
					
		
		$value = $this->xmlOutput->addChild("Chart2");
					$value->addChild("account","LY");
					$value->addChild("TYEAR",$data[3]);
		$value = $this->xmlOutput->addChild("Chart2");
					$value->addChild("account","TY");
					$value->addChild("TYEAR",$data[2]);
		$value = $this->xmlOutput->addChild("ChartTitle2");
					$value->addChild("PER",$per2);
			
		$latestweekVal	= $data[0];
	}
	   
	private function GridTable1(){	   
		$i		= 1;
		$query	= "SELECT SKU_ROLLUP2 AS ACCOUNT".
						",SUM( (CASE WHEN ".filters\timeFilter::$tyWeekRange." THEN 1 ELSE 0 END) * ".$this->settingVars->ProjectValue." ) AS TYEAR".
						",SUM( (CASE WHEN ".filters\timeFilter::$lyWeekRange." THEN 1 ELSE 0 END) * ".$this->settingVars->ProjectValue." ) AS LYEAR ".
						"FROM ".$this->settingVars->tablename.$this->queryPart." AND (".filters\timeFilter::$tyWeekRange." OR ".filters\timeFilter::$lyWeekRange.") ".
						"GROUP BY skuID_ROLLUP2,ACCOUNT ".
						"ORDER BY TYEAR DESC ".
						"LIMIT 0,10";
		$result	= $this->queryVars->queryHandler->runQuery($query,$this->queryVars->linkid,db\ResultTypes::$TYPE_OBJECT);
		foreach($result as $key=>$data)
		{
			$per = ($data['LYEAR']>0) ? number_format( ((($data['TYEAR']-$data['LYEAR'])/$data['LYEAR'])*100) ,1,'.',',') : 0;
			
			$value = $this->xmlOutput->addChild("gridTable1");
			$value->addChild("RANK",	$i);
			$value->addChild("SKU",		htmlspecialchars_decode($data['ACCOUNT']));
			$value->addChild("VAL",		$data['TYEAR']);
			$value->addChild("VAR",		$data['LYEAR']);
			$i++;
		} 
	}
	
	
	private function GridTable2(){ 
		$i			= 1;
		$j			= 1;
		 
		 
		$query		= "SELECT SNAME AS SNAME".
						 ",SUM((CASE WHEN ".filters\timeFilter::$tyWeekRange." THEN 1 ELSE 0 END) * ".$this->settingVars->ProjectValue." ) AS TYEAR".
						 ",SUM((CASE WHEN ".filters\timeFilter::$lyWeekRange." THEN 1 ELSE 0 END) * ".$this->settingVars->ProjectValue." ) AS LYEAR ".
						 "FROM ".$this->settingVars->tablename.$this->queryPart." AND (".filters\timeFilter::$tyWeekRange." OR ".filters\timeFilter::$lyWeekRange.") AND country = 'SC' ".
						 "GROUP BY SNAME ".
						 "ORDER BY TYEAR DESC ".
						 "LIMIT 0,10";
		 
		$result		= $this->queryVars->queryHandler->runQuery($query,$this->queryVars->linkid,db\ResultTypes::$TYPE_OBJECT);
		foreach($result as $key=>$data)
		{
			$per = ($data['LYEAR']>0) ? number_format( ((($data['TYEAR']-$data['LYEAR'])/$data['LYEAR'])*100) ,1,'.',',') : 0;
			$value = $this->xmlOutput->addChild("gridTable2");
			$value->addChild("RANK",	$i);
			$value->addChild("SKU",		htmlspecialchars_decode($data['SNAME']));
			$value->addChild("VAL",		$data['TYEAR']);
			$value->addChild("VAR",		$per);
			$i++;	
		}
		 
		 
		 
		$query	= "SELECT SNAME AS SNAME".
						 ",SUM( (CASE WHEN ".filters\timeFilter::$tyWeekRange." THEN 1 ELSE 0 END) * ".$this->settingVars->ProjectValue." ) AS TYEAR".
						 ",SUM( (CASE WHEN ".filters\timeFilter::$lyWeekRange." THEN 1 ELSE 0 END) * ".$this->settingVars->ProjectValue." ) AS LYEAR ".
						 "FROM ".$this->settingVars->tablename.$this->queryPart." AND (".filters\timeFilter::$tyWeekRange." OR ".filters\timeFilter::$lyWeekRange.") AND country <> 'SC' ".
						 "GROUP BY SNAME ".
						 "ORDER BY TYEAR DESC ".
						 "LIMIT 0,10";  	
		$result	= $this->queryVars->queryHandler->runQuery($query,$this->queryVars->linkid,db\ResultTypes::$TYPE_OBJECT);
		foreach($result as $key=>$data)
		{
			$per 	= ($data['LYEAR']>0) ? number_format( ((($data['TYEAR']-$data['LYEAR'])/$data['LYEAR'])*100) ,1,'.',',') : 0;
			$value	= $this->xmlOutput->addChild("gridTable3");
			$value->addChild("RANK",	$j);
			$value->addChild("SKU",		htmlspecialchars_decode($data['SNAME']));
			$value->addChild("VAL",		$data['TYEAR']);
			$value->addChild("VAR",		$per);
			$j++;	
		}
	}
	
	
	private function PieChart(){
		global $latestweekVal,$latestweek4Val,$latestweek13Val;		
		$value = $this->xmlOutput->addChild("piechart");
		$value->addChild("latest",		$latestweekVal);
		$value->addChild("latest4",		$latestweek4Val);
		$value->addChild("latest13",	$latestweek13Val);
	}
	   
	   
	private function LatestWeek(){
		global $ToYear,$FromYear,$FromWeek,$ToWeek,$FromWeek4,$FromWeek13,$FromWeek52,$LastYear4,$LastYear13,$LastYear52;//time vars
	
		//COLLECTING FIRST YEAR-WEEK
		$data = $this->getYearWeekRange_groupByPeriod(1 , 1);
		filters\timeFilter::$ToWeek		= $ToWeek 		= $data[1];
		filters\timeFilter::$ToYear		= $ToYear 		= $data[0];
		filters\timeFilter::$FromWeek	= $FromWeek 	= $ToWeek;
		filters\timeFilter::$FromYear	= $FromYear  	= $ToYear;   
	 
		//COLLECTING 4TH YEAR-WEEK
		$data = $this->getYearWeekRange_groupByPeriod(4 , 1);
		$FromWeek4	= $data[1];
		$LastYear4	= $data[0];
	
		//COLLECTING 13TH YEAR-WEEK
		$data = $this->getYearWeekRange_groupByPeriod(13 , 1);
		$FromWeek13	= $data[1];
		$LastYear13	= $data[0];
	
		//COLLECTING 52SN YEAR-WEEK
		$data = $this->getYearWeekRange_groupByPeriod(52 , 1);
		$FromWeek52	= $data[1];
		$LastYear52	= $data[0];
		
		filters\timeFilter::getExtraSlice($this->settingVars);
	}
	
	
	private function getYearWeekRange_groupByPeriod($from,$to){  
		$query		= "SELECT ".$this->settingVars->yearperiod." AS YEAR".
						",".$this->settingVars->weekperiod." AS WEEK ".
						"FROM ".$this->settingVars->timeHelperTables.$this->settingVars->timeHelperLink." ".
						"GROUP BY ".$this->settingVars->timetable.".".$this->settingVars->period.",YEAR,WEEK ".
						"ORDER BY ".$this->settingVars->timetable.".".$this->settingVars->period." DESC ".
						"LIMIT $from,$to";
		$result		= $this->queryVars->queryHandler->runQuery($query,$this->queryVars->linkid,db\ResultTypes::$TYPE_ARRAY);  
		$data		= $result[0];
		
		return $data;
	}
	
	
	private function qpart($querypart){
		global $ToYear,$FromYear,$FromWeek,$ToWeek;
		if($ToYear==$FromYear)  	   
			$querypart	.=" AND ".$this->settingVars->weekperiod." BETWEEN ".$FromWeek." AND ".$ToWeek;
			
		return $querypart;
	}	 
	
	/**** OVERRIDE PARENT CLASS'S getAll FUNCTION ****/  
	public function getAll(){	
		$tablejoins_and_filters       = $this->settingVars->link; 
		 
		if($_REQUEST["CATEGORY"]!="") 
		  $tablejoins_and_filters	 .= " AND ".$storetable.".SNO=".$_REQUEST['CATEGORY'];	      
		  
		if($_REQUEST["BRAND"]!="") 
		   $tablejoins_and_filters	 .= " AND ".$dept.".deptNo=".$_REQUEST['BRAND'];	      
	
		if($_REQUEST["SKU"]!="") 
		   $tablejoins_and_filters	 .= " AND ".$skutable.".PIN_ROLLUP=".$_REQUEST['SKU'];	      
		   
		return  $tablejoins_and_filters;
	}
	
}
?>