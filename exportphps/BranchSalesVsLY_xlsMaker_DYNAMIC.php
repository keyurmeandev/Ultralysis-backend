<?php
namespace exportphps;
ini_set("memory_limit", "350M");

use filters;
use db;
use config;

class BranchSalesVsLY_xlsMaker_DYNAMIC extends config\UlConfig{    

    private $objPHPExcel;
    private $skuMode;
    private $gain_start_letter,$gain_end_letter,$maintain_start_letter,$maintain_end_letter,$lost_start_letter,$lost_end_letter;
    private $accountArr; //STORES ACCOUNT LIST SENT FROM FRONT-END
    private $accountHeaders; //STORES ACCOUNT ALIASE LIST DERIVED FROM $accountArr
    private $distribution_ly_totalData,$distribution_ty_totalData;
    private $gainArr,$maintainArr,$lostArr;
    private $queryPartsArr;
    
    public function go($settingVars){
	$this->initiate($settingVars); //INITIATE COMMON VARIABLES
	$this->queryPart 	= $this->getAll();
	filters\timeFilter::getSlice($this->settingVars);
	filters\timeFilter::getExtraSlice_ByQuery($this->settingVars);
	$this->queryPartsArr 	= $this->getSqlPart();
	
	$this->initiateDocumentProperties();
	$this->setCellDimensions();
	$this->setOutOfGridTitleTexts();
	
	$this->getData();
	$this->createStoresGained();
	$this->createStoresMaintained();
	$this->createStoresLost();
	$this->saveAndDownload();
    }


    /**** OVERRIDE PARENT CLASS'S getAll FUNCTION ****/
    public function getAll(){
	$tablejoins_and_filters       	 = $this->settingVars->link;
	 
	$skuID	= $this->settingVars->dataArray[$_GET['SKU_FIELD']]['ID'];
	if($_GET['TPNB']!=""){
		$tablejoins_and_filters	.= " AND $skuID = '".$_GET['TPNB']."' ";
	}
	
	return $tablejoins_and_filters;
    }
    
    private function initiateDocumentProperties(){
	//ERROR REPORTING
	error_reporting(E_ALL);
	ini_set('display_errors', TRUE);
	ini_set('display_startup_errors', TRUE);
	
	//CREATE NEW PHPExcel OBJECT
	include "../ppt/Classes/PHPExcel.php";
	$this->objPHPExcel = new \PHPExcel();
	
	//SET DOCUMENT PROPERTIES
	$this->objPHPExcel->getProperties()->setCreator("Ultralysis")
						->setTitle($_GET['HEADER']." Sales vs PP")
						->setSubject("Product KBI ".$_GET['HEADER']." Sales Report")
						->setLastModifiedBy('Anas Bin Numan'); //DEVELOPER WHO RECENTLY WORKED ON THIS FILE
						
	//ADD A NEW WORKSHEET AND SET IT AS THE FIRST WORKSHEET OF THE DOCUMENT
	$newWorkSheet = new \PHPExcel_Worksheet($this->objPHPExcel, "VS PP");
	$this->objPHPExcel->addSheet($newWorkSheet,0);
	$this->objPHPExcel->setActiveSheetIndex(0);
    }
    
    private function setCellDimensions(){	
	$this->gain_start_letter 	= "A";
	$gain_columns_count 	    	= count($this->accountHeaders) + count($this->settingVars->measureArray_for_productKBI_only) + 1; //+1 for price
	$this->gain_end_letter		= $this->calculateEndingLetter($this->gain_start_letter,$gain_columns_count-1);
	
	$temp = $this->gain_end_letter;
	$temp++;$temp++;
	$this->maintain_start_letter 	= $temp;
	$maintain_columns_count 	= count($this->accountHeaders) + (count($this->settingVars->measureArray_for_productKBI_only)+1)*2; //+1 for price
	$this->maintain_end_letter 	= $this->calculateEndingLetter($this->maintain_start_letter,$maintain_columns_count-1);
	
	$temp = $this->maintain_end_letter;
	$temp++;$temp++;
	$this->lost_start_letter 	= $temp;
	$lost_columns_count 		= count($this->accountHeaders) + count($this->settingVars->measureArray_for_productKBI_only)+1; //+1 for price
	$this->lost_end_letter		= $this->calculateEndingLetter($this->lost_start_letter,$lost_columns_count-1);
    }
    
    private function setOutOfGridTitleTexts(){
	$this->objPHPExcel->getActiveSheet()
			->setCellValue("A1" , 			    		$_GET['TPNB'].":".$_GET['SKU'])
			->setCellValue($this->gain_start_letter."2",	    	$_GET['HEADER']." GAINED")
			->setCellValue($this->maintain_start_letter."2",      	$_GET['HEADER']." MAINTAINED")
			->setCellValue($this->lost_start_letter."2", 	    	$_GET['HEADER']." LOST");
	$this->objPHPExcel->getActiveSheet()->getStyle("A1:AC5")->getFont()->setBold(true);
    }


    private function calculateEndingLetter($startingLetter,$stepsAhead){
	$i=0;
	while($i<$stepsAhead)
	{
	    $startingLetter++;
	    $i++;
	}
	
	return $startingLetter;
    }


    private function getSqlPart(){
	$returnArray 	= array();
	$this->accountHeaders = array();
	$selectPart	= array();
	$groupByPart	= array();
	 
	$accountArr = explode("-" , $_REQUEST['ACCOUNTS']);
	foreach($accountArr as $i=>$account)
	{
	    $id			= key_exists("ID" , $this->settingVars->dataArray[$account]) ? $this->settingVars->dataArray[$account]['ID']:"";
	    $name 	  	= $this->settingVars->dataArray[$account]['NAME'];
	    
	    if($id!=""){
		    $selectPart[]	    	= $id . " AS '". $this->settingVars->dataArray[$account]['ID_ALIASE'] . "'";
		    $groupByPart[]	    	= "'".$this->settingVars->dataArray[$account]['ID_ALIASE'] . "'";
		    $this->accountHeaders[]     = $this->settingVars->dataArray[$account]['ID_ALIASE'];
	    }
 
	    $selectPart[]		    = $name . " AS '". $this->settingVars->dataArray[$account]['NAME_ALIASE'] . "'";
	    $groupByPart[]		    = "'".$this->settingVars->dataArray[$account]['NAME_ALIASE'] . "'";
	    $this->accountHeaders[]	    = $this->settingVars->dataArray[$account]['NAME_ALIASE'];
	}
	
	//PREPARE MEASURE ITEMS
	foreach($this->settingVars->measureArray_for_productKBI_only as $key=>$measure)
	{
	    if($measure['attr']=='SUM')
	    {
		$measureSelectionArr_TY[] = "SUM( (CASE WHEN ".filters\timeFilter::$tyWeekRange." THEN 1 ELSE 0 END) * " . $measure['VAL'] . ") AS TY" . $measure['ALIASE'];
		$measureSelectionArr_LY[] = "SUM( (CASE WHEN ".filters\timeFilter::$lyWeekRange." THEN 1 ELSE 0 END) * " . $measure['VAL'] . ") AS LY" . $measure['ALIASE'];
	    }   
	}
		
	$returnArray = array(
				'SELECT_PART'=>$selectPart
				,'GROUPBY_PART'=>$groupByPart
				,'MEASURE_TY'=>$measureSelectionArr_TY
				,'MEASURE_LY'=>$measureSelectionArr_LY
				 );
	
	return $returnArray;
    
    }



    //------------------ FETCH STORE SALES FOR SELECTED TPNB -------------
    private function getData(){		
	$selectPart 	 	= $this->queryPartsArr['SELECT_PART'];
	$groupByPart 	 	= $this->queryPartsArr['GROUPBY_PART'];
	
	$measurePart_TY		= $this->queryPartsArr['MEASURE_TY'];
	$measurePart_LY		= $this->queryPartsArr['MEASURE_LY'];
	
	$this->distribution_ty_totalData 	= array();	
	//FIND OUT TY SELLING CUSTOMERS/BRANCHES/STORES
	$query 	    = "SELECT ".implode("," , $selectPart).",".implode("," , $measurePart_TY)." ".	   
			    "FROM ".$this->settingVars->tablename.$this->queryPart." ".
			    "GROUP BY ".implode("," , $groupByPart)." ".
			    "HAVING TYVOL<>0 ".
			    "ORDER BY 1";
	$result     = $this->queryVars->queryHandler->runQuery($query,$this->queryVars->linkid,db\ResultTypes::$TYPE_OBJECT);
	foreach($result as $key=>$data)
	{
	    $this->distribution_ty_totalData[$data[$this->accountHeaders[0]]] = $data;
	}
	
	$this->distribution_ly_totalData 	= array();
	//FIND OUT LY/PP SELLING CUSTOMERS/BRANCHES/STORES
	$query 	    = "SELECT ".implode("," , $selectPart).",".implode("," , $measurePart_LY)." ".  
			    "FROM ".$this->settingVars->tablename.$this->queryPart." ".
			    "GROUP BY ".implode("," , $groupByPart)." ".
			    "HAVING LYVOL<>0 ".
			    "ORDER BY 1";
	$result     = $this->queryVars->queryHandler->runQuery($query,$this->queryVars->linkid,db\ResultTypes::$TYPE_OBJECT);
	foreach($result as $key=>$data)
	{
	    $this->distribution_ly_totalData[$data[$this->accountHeaders[0]]] = $data;
	}
    
    
	$this->gainArr 			= array_diff_key($this->distribution_ty_totalData,$this->distribution_ly_totalData);
	$this->maintainArr 		= array_intersect_key($this->distribution_ty_totalData,$this->distribution_ly_totalData);
	$this->lostArr 			= array_diff_key($this->distribution_ly_totalData,$this->distribution_ty_totalData);
	/*USEFUL WHEN DEBUGGING , DON'T DELETE !!
	    header('Content-type:application/json');
	    print ("Maintain Array: ");
	    print_r($this->maintainArr);exit;
	*/
    }


    private function createStoresGained(){	
	//-- COLOR HEADER ROWS WITH BLUE
	for($i=$this->gain_start_letter;$i<=$this->gain_end_letter;$i++)
	{
	    $this->objPHPExcel->getActiveSheet()->getStyle($i."5")->applyFromArray(
	    array(	'fill' 	=> array(
					'type'		=> \PHPExcel_Style_Fill::FILL_SOLID,
					'color'		=> array('argb' => 'FFDEEBF6')
				    ),
			'borders' => array(
					'top'		=> array('style' => \PHPExcel_Style_Border::BORDER_THIN),
					'bottom'	=> array('style' => \PHPExcel_Style_Border::BORDER_THIN),
					'left'		=> array('style' => \PHPExcel_Style_Border::BORDER_THIN),
					'right'		=> array('style' => \PHPExcel_Style_Border::BORDER_THIN)
					)
		  )
	    );
	}
	
	//-- ADD BORDERS TO GENERAL COLUMNS
	$rowIndex = 5;
	while($rowIndex++<count($this->gainArr)+5)
	{
	    for($i=$this->gain_start_letter;$i<=$this->gain_end_letter;$i++)
	    {
		
		$this->objPHPExcel->getActiveSheet()->getStyle($i.$rowIndex)->applyFromArray
		(
		    array   (
				'borders' => array
				(
				    'top'	=> array('style' => \PHPExcel_Style_Border::BORDER_THIN),
				    'bottom'	=> array('style' => \PHPExcel_Style_Border::BORDER_THIN),
				    'left'	=> array('style' => \PHPExcel_Style_Border::BORDER_THIN),
				    'right'	=> array('style' => \PHPExcel_Style_Border::BORDER_THIN)
				)
			    )
		);
	    }
    
	}
	
	//-- ADD GRID HEADER TEXTS
	$i = $this->gain_start_letter;
	foreach($this->accountHeaders as $key=>$value)
	{
	    $this->objPHPExcel->getActiveSheet()->setCellValue($i++."5",$value);
	}	
	foreach($this->settingVars->measureArray_for_productKBI_only as $key=>$measure)
	{
	    if($measure['attr']=="SUM")
		$this->objPHPExcel->getActiveSheet()->setCellValue($i++."5", $measure['ALIASE'].' TP');
						 
	}
	$this->objPHPExcel->getActiveSheet()->setCellValue($i++."5", "PRICE TP");
	    
	    
			    
	//-- SET COLUMN AUTO SIZE PROPERTIES
	for($i=$this->gain_start_letter;$i<=$this->gain_end_letter;$i++)
	{
	    $this->objPHPExcel->getActiveSheet()
		->getColumnDimension($i)
		->setAutoSize(true);
	}
	
	//--ADD VALUES TO GRID COLUMNS
	$rowIndex = 6;
	$activeSheet = $this->objPHPExcel->getActiveSheet();
	foreach($this->gainArr as $key=>$data)
	{
	    $price = $data['TYVOL']>0?$data['TYVAL']/$data['TYVOL']:0;
	    $i = $this->gain_start_letter;
	    
	    
	    foreach($this->accountHeaders as $key=>$value)
	    {
		$activeSheet->setCellValue($i++.$rowIndex, $data[$value]);
	    }
	    foreach($this->settingVars->measureArray_for_productKBI_only as $key=>$measure)
	    {
		if($measure['attr']=="SUM")
		   $activeSheet->setCellValue($i++.$rowIndex, $data['TY'.$measure['ALIASE']]); 				     
	    }
	    $activeSheet->setCellValue($i++.$rowIndex, number_format($price,2,'.',''));
	
	    $rowIndex++;
	}
    }


    private function createStoresMaintained(){
	//-- COLOR HEADER ROW WITH BLUE
	for($i=$this->maintain_start_letter;$i<=$this->maintain_end_letter;$i++)
	{
	    $this->objPHPExcel->getActiveSheet()->getStyle($i."5")->applyFromArray(
	    array(	'fill' 	=> array(
					'type'		=> \PHPExcel_Style_Fill::FILL_SOLID,
					'color'		=> array('argb' => 'FFDEEBF6')
				    ),
			'borders' => array(
					'top'		=> array('style' => \PHPExcel_Style_Border::BORDER_THIN),
					'bottom'	=> array('style' => \PHPExcel_Style_Border::BORDER_THIN),
					'left'		=> array('style' => \PHPExcel_Style_Border::BORDER_THIN),
					'right'		=> array('style' => \PHPExcel_Style_Border::BORDER_THIN)
					)
		  )
	    );
    
	}
       
	
	//-- ADD BORDERS TO GENERAL COLUMNS
	$rowIndex = 5;
	while($rowIndex++<count($this->maintainArr)+5)
	{
	    for($i=$this->maintain_start_letter;$i<=$this->maintain_end_letter;$i++)
	    {
		$this->objPHPExcel->getActiveSheet()->getStyle($i.$rowIndex)->applyFromArray
		(
		    array   (
				'borders' => array
				(
				    'top'	=> array('style' => \PHPExcel_Style_Border::BORDER_THIN),
				    'bottom'	=> array('style' => \PHPExcel_Style_Border::BORDER_THIN),
				    'left'	=> array('style' => \PHPExcel_Style_Border::BORDER_THIN),
				    'right'	=> array('style' => \PHPExcel_Style_Border::BORDER_THIN)
				)
			    )
		);
	    }
	}
    
	
	
	
	
	 //-- ADD GRID HEADER TEXTS
	$i = $this->maintain_start_letter;
	foreach($this->accountHeaders as $key=>$value)
	{
	    $this->objPHPExcel->getActiveSheet()->setCellValue($i++."5", $value);
	}		
	foreach($this->settingVars->measureArray_for_productKBI_only as $key=>$measure)
	{
	    if($measure['attr']=="SUM")
	     $this->objPHPExcel->getActiveSheet()->setCellValue($i++."5", $measure['ALIASE'].' TP')
					   ->setCellValue($i++."5", $measure['ALIASE'].' var LY');
	}
	$this->objPHPExcel->getActiveSheet()->setCellValue($i++."5", "PRICE TP")
				      ->setCellValue($i++."5", "PRICE LY");
				     
	
	//-- SET COLUMN AUTO SIZE PROPERTIES
	for($i=$this->maintain_start_letter;$i<=$this->maintain_end_letter;$i++)
	{
	    $this->objPHPExcel->getActiveSheet()
		->getColumnDimension($i)
		->setAutoSize(true);
	}
	
	
	//--ADD GRID VALUES
	$rowIndex = 6;
	foreach($this->maintainArr as $key=>$data)
	{
	    $price_tp 	= $data['TYVOL']>0?$data['TYVAL']/$data['TYVOL']:0;
	    $price_ly 	= $this->distribution_ly_totalData[$key]['LYVOL']>0?$this->distribution_ly_totalData[$key]['LYVAL']/$this->distribution_ly_totalData[$key]['LYVOL']:0;
    
	    $var_ly_arr = array();
	    foreach($this->settingVars->measureArray_for_productKBI_only as $mKey=>$measure)
	    {
		if($measure['attr']=="SUM"){
				  $temp_ty 				=  $data['TY'.$measure['ALIASE']];
				  $temp_ly 				=  $this->distribution_ly_totalData[$key]['LY'.$measure['ALIASE']];
				  $var_ly_arr[$measure['ALIASE']] 	=  $temp_ty - $temp_ly;
		}
	    }
	    
	    $i = $this->maintain_start_letter;
	    foreach($this->accountHeaders as $value)
	    {
		$this->objPHPExcel->getActiveSheet()->setCellValue($i++.$rowIndex, $data[$value]);
	    }
	    $activeSheet = $this->objPHPExcel->getActiveSheet();
	    foreach($this->settingVars->measureArray_for_productKBI_only as $key=>$measure)
	    {
		if($measure['attr']=="SUM"){
			$activeSheet->setCellValue($i++.$rowIndex, $data['TY'.$measure['ALIASE']]);
			$activeSheet->setCellValue($i++.$rowIndex, number_format($var_ly_arr[$measure['ALIASE']],0,'.',''));
		}
	    }
	    $activeSheet->setCellValue($i++.$rowIndex, number_format($price_tp,2,'.',''));
	    $activeSheet->setCellValue($i++.$rowIndex, number_format($price_ly,2,'.',''));
			
	    $rowIndex++;
	}
    }


    private function createStoresLost(){
	//-- COLOR HEADER ROW WITH BLUE
	$i	    = $this->lost_start_letter;
	$endRange   = $this->lost_end_letter;
	$endRange++;
	while($i!=$endRange)
	{
	    $this->objPHPExcel->getActiveSheet()->getStyle($i."5")->applyFromArray(
	    array(	'fill' 	=> array(
					'type'		=> \PHPExcel_Style_Fill::FILL_SOLID,
					'color'		=> array('argb' => 'FFDEEBF6')
				    ),
			'borders' => array(
					'top'		=> array('style' => \PHPExcel_Style_Border::BORDER_THIN),
					'bottom'	=> array('style' => \PHPExcel_Style_Border::BORDER_THIN),
					'left'		=> array('style' => \PHPExcel_Style_Border::BORDER_THIN),
					'right'		=> array('style' => \PHPExcel_Style_Border::BORDER_THIN)
					)
		  )
	    );
	    $i++;
    
	}
	
	//-- ADD BORDERS TO GENERAL COLUMNS
	$rowIndex = 5;
	while($rowIndex++<count($this->lostArr)+5)
	{
	    $i = $this->lost_start_letter;
	    while($i!=$endRange)
	    {
		    $this->objPHPExcel->getActiveSheet()->getStyle($i.$rowIndex)->applyFromArray
		    (
			array   (
				    'borders' => array
				    (
					'top'		=> array('style' => \PHPExcel_Style_Border::BORDER_THIN),
					'bottom'	=> array('style' => \PHPExcel_Style_Border::BORDER_THIN),
					'left'		=> array('style' => \PHPExcel_Style_Border::BORDER_THIN),
					'right'		=> array('style' => \PHPExcel_Style_Border::BORDER_THIN)
				    )
				)
		    );
		    $i++;
	    }
	}
	
	//-- ADD GRID HEADER TEXTS
	$i = $this->lost_start_letter;
	foreach($this->accountHeaders as $key=>$value)
	{
	    $this->objPHPExcel->getActiveSheet()->setCellValue($i++."5",$value);
	}	
	foreach($this->settingVars->measureArray_for_productKBI_only as $key=>$measure)
	{
	    if($measure['attr']=="SUM")
	    $this->objPHPExcel->getActiveSheet()->setCellValue($i++."5", $measure['ALIASE'].' LY');
						 
	}
	$this->objPHPExcel->getActiveSheet()->setCellValue($i++."5", "PRICE LY");
	    
	    		    
	//-- SET COLUMN AUTO SIZE PROPERTIES
	for($i=$this->lost_start_letter;$i!=$endRange;$i++)
	{
	    $this->objPHPExcel->getActiveSheet()
		->getColumnDimension($i)
		->setAutoSize(true);
	}
	
	
	//--ADD GRID VALUES
	$rowIndex = 6;
	foreach($this->lostArr as $key=>$data)
	{
	    $price = $data['LYVOL']>0?$data['LYVAL']/$data['LYVOL']:0;
	    
	    $i = $this->lost_start_letter;
	    foreach($this->accountHeaders as $value)
	    {
		$this->objPHPExcel->getActiveSheet()->setCellValue($i++.$rowIndex, $data[$value]);
	    }
	    $activeSheet = $this->objPHPExcel->getActiveSheet();
	    foreach($this->settingVars->measureArray_for_productKBI_only as $key=>$measure)
	    {
		    if($measure['attr']=="SUM"){
			    $activeSheet->setCellValue($i++.$rowIndex, $data['LY'.$measure['ALIASE']]);
		    }
	    }
	    $activeSheet->setCellValue($i++.$rowIndex, number_format($price,2,'.',''));
	
	    $rowIndex++;
	}
    }



    //---------------------------------------- SAVING ND DOWNLOAING FUNCTIONS --------------------------------------
    private function saveAndDownload(){
	$this->objPHPExcel->setActiveSheetIndex(0);
	$this->downloadFile($this->saveXlsxFileToServer());
    }
    
    private function saveXlsxFileToServer(){
	chdir($_SERVER['DOCUMENT_ROOT'].DIRECTORY_SEPARATOR."zip");
	$objWriter = \PHPExcel_IOFactory::createWriter($this->objPHPExcel, 'Excel2007');
	$objWriter->save(getcwd().DIRECTORY_SEPARATOR."VS_LY.xlsx");
	$filePath = getcwd().DIRECTORY_SEPARATOR."VS_LY.xlsx";  
	return $filePath;
    }
    
    private function downloadFile($fullPath){
	if ($fd = fopen ($fullPath, "r")){
	$fsize = filesize($fullPath);
	$path_parts = pathinfo($fullPath);
	$ext = strtolower($path_parts["extension"]);
	switch ($ext) {
	    case "xlsx":
		{
		    header("Content-type: application/force-download");
		    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		    header("Content-disposition: attachment; filename="."VS_LY.xlsx");
		    break;
		}
		default: print "Unknown file format";exit; 
	}
	header("Content-length: $fsize");
	header("Cache-Control: must-revalidate, post-check=0, pre-check=0, public");
	header("Expires: 0");
	@readfile($fullPath);
	}
	fclose ($fd);
	unlink($fullPath);
    }
}
?>