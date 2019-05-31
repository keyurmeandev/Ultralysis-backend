<?php
namespace classes;

ini_set("memory_limit","128M");

use projectsettings;
use SimpleXMLElement;
use ZipArchive;
use datahelper;
use filters;
use db;
use config;

class DynamicDataBuilder extends config\UlConfig{			
	public function go($settingVars){
		$this->initiate($settingVars); //INITIATE COMMON VARIABLES
		$this->queryPart    = $this->getAll();
		
		$action = $_REQUEST["action"];
		switch ($action)
		{
			case 'queryStatus' : 	return $this->queryStatus(); 	break;
			default:				$this->download();				break;
		}

    }

	
	private function queryStatus(){
		$this->queryPart    = $this->getAll();
		filters\timeFilter::getSlice($this->settingVars);

		return $this->gridData();
	}
	
	private function download(){
		$csvFile = $_REQUEST['csvFile'];
		$zipFile = $_REQUEST['zipFile'];
		
		$this->downloadFile();
	}
	
	private function getSqlPart(){		
		$sqlPart 		= "";
		$groupByPart 	= "";
				
		$items 			= explode("-",$_REQUEST['items']);
		for($i=0;$i<count($items);$i++)
		{
			$id  		 = key_exists('ID',$this->settingVars->dataArray[$items[$i]]) ? $this->settingVars->dataArray[$items[$i]]['ID'] : "";
			$name 	  	 = $this->settingVars->dataArray[$items[$i]]['NAME'];
			if('CLUSTER'==$name){
				$this->queryPart		.= " AND ".$this->settingVars->maintable.".PIN=".$this->settingVars->clustertable.".PIN ";
				$this->settingVars->tablename	 = $this->settingVars->tablename.",".$this->settingVars->clustertable;
			}

			if($id!='')
			{
				$sqlPart		.= $id. " AS '". $this->settingVars->dataArray[$items[$i]]['ID_ALIASE'] . "',";
				$groupByPart	.= "'".$this->settingVars->dataArray[$items[$i]]['ID_ALIASE'] . "',";
			}
			
			$sqlPart		.= $name. " AS '". $this->settingVars->dataArray[$items[$i]]['NAME_ALIASE'] . "',";
			$groupByPart	.= "'".$this->settingVars->dataArray[$items[$i]]['NAME_ALIASE'] . "',";	
		}
			
		$sqlPart 	= substr($sqlPart,0,strlen($sqlPart)-1);
		$groupByPart 	= substr($groupByPart,0,strlen($groupByPart)-1);
				
		return array(
						 'selectPart'=>$sqlPart
						,'groupByPart'=>$groupByPart
					 );
	}
	
	
	
	private function getCsvPart($data){
		$csvPart 			= "";
		$totalItems 		= explode("-",$_REQUEST['items']);
		$totalMeasures 		= explode("-",$_REQUEST['measures']);
		
		//ADDING ITEMS TO CSV	
		for($i=0;$i<count($totalItems);$i++)
		{
			$id  		 = key_exists('ID',$this->settingVars->dataArray[$totalItems[$i]]) ? $this->settingVars->dataArray[$totalItems[$i]]['ID'] : "";
			$name 	  	 = $this->settingVars->dataArray[$totalItems[$i]]['NAME'];
			if($id!='')
			{
				//CHECKING IF DATA FROM QUERY HAS ANY COMMA WITHIN IT,IF YES, ADD QUOATED DATA, ELSE LEAVE IT AS IT IS
				$domain = strstr($data[$this->settingVars->dataArray[$totalItems[$i]]['ID_ALIASE']],',');
				if(strlen($domain)==0)
					$csvPart.= $data[$this->settingVars->dataArray[$totalItems[$i]]['ID_ALIASE']].","; //LEAVING AS IT IS
				else
					$csvPart.= "\"".$data[$this->settingVars->dataArray[$totalItems[$i]]['ID_ALIASE']]."\","; //ADDING QUOTED DATA
			}
		
			//CHECKING IF DATA FROM QUERY HAS ANY COMMA WITHIN IT,IF YES, ADD QUOATED DATA, ELSE LEAVE IT AS IT IS
			$domain = strstr($data[$this->settingVars->dataArray[$totalItems[$i]]['NAME_ALIASE']],',');
			if(strlen($domain)==0)
				$csvPart.= $data[$this->settingVars->dataArray[$totalItems[$i]]['NAME_ALIASE']].","; //LEAVING AS IT IS
			else
				$csvPart.= "\"".$data[$this->settingVars->dataArray[$totalItems[$i]]['NAME_ALIASE']]."\","; //ADDING QUOTED DATA
		}

		//ADDING MEASURES TO CSV
		for($i=0;$i<count($totalMeasures);$i++)
		{
			if(key_exists($totalMeasures[$i],$this->settingVars->measureArray))
			{
				$csvPart	.= $data[$this->settingVars->measureArray[$totalMeasures[$i]]['ALIASE']] . ",";
			}
			if(in_array($totalMeasures[$i],array("AVEspacePRICE")))
			{
				$avePrice = 0.00;
				if($data['VOLUME']>0) $avePrice = ($data['VALUE']/$data['VOLUME']);  
				$csvPart      .=  number_format($avePrice,2,".",",") . ",";
			}
		}
		
		$csvPart	 = str_replace("\r" , "" , substr($csvPart,0,strlen($csvPart)-1));
		$csvPart	 = str_replace("\n" , "" , $csvPart);
		$csvPart	.= "\r\n";
		
		return $csvPart;
		
	}
	
	private function gridData(){
		datahelper\Common_Data_Fetching_Functions::$settingVars = $this->settingVars;
		datahelper\Common_Data_Fetching_Functions::$queryVars 	= $this->queryVars;

		$measureArr 		 		= datahelper\Common_Data_Fetching_Functions::getMeasuresForDDB();
		$measureSelectionPart		 	= implode(",",(array)$measureArr['selectPart']);
		
		$havingPart="";
		if(count((array)$measureArr['havingPart'])>0) $havingPart= " HAVING (".implode(" OR ",(array)$measureArr['havingPart']).")" ;

		$mainSelectionArr 		 	= $this->getSqlPart();
		$selectPart 	 			= $mainSelectionArr['selectPart'];
		$groupByPart 	 			= $mainSelectionArr['groupByPart'];
		

		
		$query 		= "SELECT $selectPart,$measureSelectionPart ".
							"FROM ".$this->settingVars->tablename . $this->queryPart." AND ".filters\timeFilter::$tyWeekRange." ".
							"GROUP BY $groupByPart $havingPart";
		$result 	= mysqli_query($this->queryVars->linkid,$query);
		if(!$result)
		{
			$this->xmlOutput->addChild("message",0);
			$this->xmlOutput->addChild("query",$query);
			$this->xmlOutput->addChild("error",mysqli_error($linkid));
			return $this->xmlOutput;
		}
		else
		{
		 
			
			$csvOutput 			= "";
			$csvHeaderForItems 	= "";
			
			$totalMeasures 		= explode("-",$_REQUEST['measures']);
			$totalItems 		= explode("-",$_REQUEST['items']);
			
			//ADDING  HEADERS FOR ACCOUNT COLUMNS [PRODUCT OPTIONS,STORE OPTIONS]
			for($i=0;$i<count($totalItems);$i++)
			{
				if(key_exists('ID_ALIASE',(array)$this->settingVars->dataArray[$totalItems[$i]]))
				$csvHeaderForItems	.= $this->settingVars->dataArray[$totalItems[$i]]['ID_ALIASE'].",";
				$csvHeaderForItems	.= $this->settingVars->dataArray[$totalItems[$i]]['NAME_ALIASE'].",";
			}
			
			//ADDING HEADERS FOR MEASURE COLUMNS [VALU,VOLUME....]
			for($i=0;$i<count($totalMeasures);$i++)
			{
				if(key_exists($totalMeasures[$i],$this->settingVars->measureArray))
				{
					$name 	  	 = $this->settingVars->measureArray[$totalMeasures[$i]]['ALIASE'];
				}
				else
				{
					$name 	  	 = str_replace("space" , " " , $totalMeasures[$i]);
				}
				$csvHeaderForItems	.= $name.",";
			}
			$csvHeaderForItems = str_replace("_" , " " , $csvHeaderForItems); //REPLACING ANY "_" SIGN WITH BLANK-SPACE

			
			$csvOutput			.= substr($csvHeaderForItems,0,strlen($csvHeaderForItems)-1). "\n";			
			//ADDING ROW DATA
			while($data = mysqli_fetch_assoc($result))
			{
				$csvOutput		.= $this->getCsvPart($data);
			}
			
			return $this->exportCSVAsZip($csvOutput);
		}
	}
	
	
	private function exportCSVAsZip($csvData){
		$date 		= date('Y_m_j_H\hi\ms\s')."_".$this->settingVars->aid;
		$fileName 	= $date.".zip";
		chdir("../zip");
		$zip 		= new ZipArchive;
		$result_zip = $zip->open($fileName,ZIPARCHIVE::CREATE);
		$zip->addFromString($date.".csv", $csvData);
		$zip->close();

		
		$csvFile 	= getcwd().DIRECTORY_SEPARATOR.$date.".csv";
		$zipFile 	= getcwd().DIRECTORY_SEPARATOR.$date.".zip";

		$this->xmlOutput->addChild("message",1);
		$this->xmlOutput->addChild("csvFile",$csvFile);
		$this->xmlOutput->addChild("zipFile",$zipFile);
		
		return $this->xmlOutput;
	}
	
	
	private function downloadFile(){
		$zipFile 	= $_REQUEST['zipFile'];
		if ($fd = fopen($zipFile, "r"))
		{
			$fsize 		= filesize($zipFile);
			$path_parts = pathinfo($zipFile);
			$ext 		= strtolower($path_parts["extension"]);
			switch ($ext){
				case "zip":
				{
					header("Content-type: application/force-download");
					header("Content-Transfer-Encoding: application/zip;");
					header("Content-disposition: attachment; filename="."reportData.zip");
					break;
				}
				default: print "Unknown file format";exit; 
			}
			header("Content-length: $fsize");
			header("Cache-Control: must-revalidate, post-check=0, pre-check=0, public");
			header("Expires: 0");
			@readfile($zipFile);
		}
		fclose ($fd);
		$this->deleteFiles();	
	}
	
	
    /*****
    * DELETES THE CSV FILES, THOSE WERE CREATED DURING REPORT GENERATION PROCESS
    ******/	
	private function deleteFiles(){
		$csvFile = $_REQUEST['csvFile'];
		$zipFile = $_REQUEST['zipFile'];
		
		if(file_exists($zipFile)) unlink($zipFile);
		if(file_exists($csvFile)) unlink($csvFile);
	
	}
}
?>