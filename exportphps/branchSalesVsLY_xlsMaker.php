<?php
namespace exportphps;

class branchSalesVsLY_xlsMaker{
    
    public static $objPHPExcel;
    public static $skuMode;

    public static function createXlsFile($gainArray,$maintainArray,$lostArray){
	global $gainArr,$maintainArr,$lostArr;
	$gainArr 	= $gainArray;
	$maintainArr 	= $maintainArray;
	$lostArr 	= $lostArray;
	
	//-- Error reporting
	error_reporting(E_ALL);
	ini_set('display_errors', TRUE);
	ini_set('display_startup_errors', TRUE);
		
	//-- Create new PHPExcel object
	include "../ppt/Classes/PHPExcel.php";
	self::$objPHPExcel = new \PHPExcel();
	
	//-- Set document properties
	self::$objPHPExcel->getProperties()->setCreator("Ultralysis")
				    ->setTitle("Store Sales vs LY")
				    ->setSubject("Product KBI Report");
	$newWorkSheet = new \PHPExcel_Worksheet(self::$objPHPExcel, "VS LY");
	self::$objPHPExcel->addSheet($newWorkSheet,0);
	self::$objPHPExcel->setActiveSheetIndex(0);
	
	//set skuDetailMode :[P or M]
	self::$skuMode = $_REQUEST['skuMode'];
	/*********************************************************************/
	
	
	
	
	
	global $gain_start_letter,$gain_end_letter,$maintain_start_letter,$maintain_end_letter,$lost_start_letter,$lost_end_letter;
	//------------------------- default text staff -----------------------
	$gain_start_letter 	= "A";
	$gain_end_letter 	= self::$skuMode=="M" 	? "G" : "F";
	$maintain_start_letter 	= self::$skuMode=="M" 	? "I" : "H";
	$maintain_end_letter 	= self::$skuMode=="M" 	? "S" : "P";
	$lost_start_letter 	= self::$skuMode=="M" 	? "U" : "R";
	$lost_end_letter	= self::$skuMode=="M" 	? "AB": "X";
	
	self::$objPHPExcel->getActiveSheet()
			->setCellValue("A1", $_REQUEST['TPNB'].":".$_REQUEST['SKU'])
			->setCellValue($gain_start_letter."2", "BRANCHES GAINED")
			->setCellValue($maintain_start_letter."2", "BRANCHES MAINTAINED")
			->setCellValue($lost_start_letter."2", "BRANCHES LOST");
	self::$objPHPExcel->getActiveSheet()->getStyle("A1:AC5")->getFont()->setBold(true);
	/*********************************************************************/
	
	
	
	
	
	
	//--------------------------- FUNCTION INTERFACE -------------------
	self::createStoresGained();
	self::createStoresMaintained();
	self::createStoresLost();
	self::saveAndDownload();
	/*******************************************************************/
    
    }
   
    private static function createStoresGained(){
	global $gainArr,$gain_start_letter,$gain_end_letter;
	
	//-- BLUE HEADERS
	for($i=$gain_start_letter;$i<=$gain_end_letter;$i++)
	{
	    self::$objPHPExcel->getActiveSheet()->getStyle($i."5")->applyFromArray(
	    array(	'fill' 	=> array(
					    'type'		=> \PHPExcel_Style_Fill::FILL_SOLID,
					    'color'		=> array('argb' => 'FFDEEBF6')
					),
			'borders' => array(
					    'top'		=> array('style' => \PHPExcel_Style_Border::BORDER_THIN),
					    'bottom'		=> array('style' => \PHPExcel_Style_Border::BORDER_THIN),
					    'left'		=> array('style' => \PHPExcel_Style_Border::BORDER_THIN),
					    'right'		=> array('style' => \PHPExcel_Style_Border::BORDER_THIN)
					)
		  )
	    );
	}
	
	//-- GENERAL COLUMNS
	$rowIndex = 5;
	while($rowIndex++<count($gainArr)+5)
	{
	    for($i=$gain_start_letter;$i<=$gain_end_letter;$i++)
	    {
		
		    self::$objPHPExcel->getActiveSheet()->getStyle($i.$rowIndex)->applyFromArray
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
	    }
    
	}
    
	
	
	
	
	//-- HEADER TEXTS
	$i = $gain_start_letter;
	self::$objPHPExcel->getActiveSheet()
		->setCellValue($i++."5", "BRANCH")
		->setCellValue($i++."5", "REGION")
		->setCellValue($i++."5", "COUNTRY")
		->setCellValue($i++."5", "VOLUME TP")
		->setCellValue($i++."5", "VALUE TP")
		->setCellValue($i++."5", "PRICE TP");
	if(self::$skuMode=="M")
		self::$objPHPExcel->getActiveSheet()
			    ->setCellValue($i++."5", "CUSTOMERS TP");
			    
	//-- SET COLUMN AUTO SIZE PROPERTIES
	for($i=$gain_start_letter;$i<=$gain_end_letter;$i++)
	{
	    self::$objPHPExcel->getActiveSheet()
		->getColumnDimension($i)
		->setAutoSize(true);
    
	}
	
	$rowIndex = 6;
	foreach($gainArr as $key=>$data)
	{
	    $price = $data['TP_VOL']>0?$data['TP_VAL']/$data['TP_VOL']:0;
	    $i = $gain_start_letter;
	    self::$objPHPExcel->getActiveSheet()
		->setCellValue($i++.$rowIndex, $data['BRANCH'])
		->setCellValue($i++.$rowIndex, $data['REGION'])
		->setCellValue($i++.$rowIndex, $data['COUNTRY'])
		->setCellValue($i++.$rowIndex, $data['TP_VOL'])
		->setCellValue($i++.$rowIndex, $data['TP_VAL'])
		->setCellValue($i++.$rowIndex, number_format($price,2,'.',''));
	    if(self::$skuMode=="M")
		self::$objPHPExcel->getActiveSheet()
			    ->setCellValue($i++.$rowIndex, $data['TP_CUST']);
		
	    $rowIndex++;
	}    
    }
    
    
    private static function createStoresMaintained(){
	
	global $maintainArr,$maintain_start_letter,$maintain_end_letter;
	
	//-- BLUE HEADERS
	for($i=$maintain_start_letter;$i<=$maintain_end_letter;$i++)
	{
	    self::$objPHPExcel->getActiveSheet()->getStyle($i."5")->applyFromArray(
	    array(	'fill' 	=> array(
					    'type'		=> \PHPExcel_Style_Fill::FILL_SOLID,
					    'color'		=> array('argb' => 'FFDEEBF6')
					),
		    'borders' => array(
					    'top'		=> array('style' => \PHPExcel_Style_Border::BORDER_THIN),
					    'bottom'		=> array('style' => \PHPExcel_Style_Border::BORDER_THIN),
					    'left'		=> array('style' => \PHPExcel_Style_Border::BORDER_THIN),
					    'right'		=> array('style' => \PHPExcel_Style_Border::BORDER_THIN)
					)
		  )
	    );
    
	}
	
	
	//-- GENERAL COLUMNS
	$rowIndex = 5;
	while($rowIndex++<count($maintainArr)+5)
	{
	    for($i=$maintain_start_letter;$i<=$maintain_end_letter;$i++)
	    {
		
		    self::$objPHPExcel->getActiveSheet()->getStyle($i.$rowIndex)->applyFromArray
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
	    }
    
	}
	
	//-- HEADER TEXTS
	$i = $maintain_start_letter;
	self::$objPHPExcel->getActiveSheet()
		    ->setCellValue($i++."5", "BRANCH")
		    ->setCellValue($i++."5", "REGION")
		    ->setCellValue($i++."5", "COUNTRY")
		    ->setCellValue($i++."5", "VOLUME TP")
		    ->setCellValue($i++."5", "VOLUME var LY")
		    ->setCellValue($i++."5", "VALUE TP")
		    ->setCellValue($i++."5", "VALUE var LY")
		    ->setCellValue($i++."5", "PRICE TP")
		    ->setCellValue($i++."5", "PRICE LY");
	if(self::$skuMode=="M")
		self::$objPHPExcel->getActiveSheet()
			    ->setCellValue($i++."5", "CUSTOMERS TP")
			    ->setCellValue($i++."5", "CUSTOMERS LY");
			    
	//-- SET COLUMN AUTO SIZE PROPERTIES
	for($i=$maintain_start_letter;$i<=$maintain_end_letter;$i++)
	{
	    self::$objPHPExcel->getActiveSheet()
		->getColumnDimension($i)
		->setAutoSize(true);
	}
	
	$rowIndex = 6;
	foreach($maintainArr as $key=>$data)
	{
	    $price_tp = $data['TP_VOL']>0?$data['TP_VAL']/$data['TP_VOL']:0;
	    $price_ly = $data['LY_VOL']>0?$data['LY_VAL']/$data['LY_VOL']:0;
    
	    $vol_var_ly = $data['TP_VOL'] - $data['LY_VOL'];
	    $val_var_ly = $data['TP_VAL'] - $data['LY_VAL'];
	    
	    $i = $maintain_start_letter;
	    self::$objPHPExcel->getActiveSheet()
		->setCellValue($i++.$rowIndex, $data['BRANCH'])
		->setCellValue($i++.$rowIndex, $data['REGION'])
		->setCellValue($i++.$rowIndex, $data['COUNTRY'])
		->setCellValue($i++.$rowIndex, $data['TP_VOL'])
		->setCellValue($i++.$rowIndex, number_format($vol_var_ly,0,'.',''))
		->setCellValue($i++.$rowIndex, $data['TP_VAL'])
		->setCellValue($i++.$rowIndex, number_format($val_var_ly,0,'.',''))
		->setCellValue($i++.$rowIndex, number_format($price_tp,2,'.',''))
		->setCellValue($i++.$rowIndex, number_format($price_ly,2,'.',''));
	    if(self::$skuMode=="M")
		self::$objPHPExcel->getActiveSheet()
			    ->setCellValue($i++.$rowIndex, $data['TP_CUST'])
			    ->setCellValue($i++.$rowIndex, $data['LY_CUST']);
		
	    $rowIndex++;
	}
    }
    
    
    private static function createStoresLost(){
	global $lostArr,$lost_start_letter,$lost_end_letter;
	
	//-- BLUE HEADERS
	$i=$lost_start_letter;
	while($i!=$lost_end_letter)
	{
	    self::$objPHPExcel->getActiveSheet()->getStyle($i."5")->applyFromArray(
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
	
	//-- GENERAL COLUMNS
	$rowIndex = 5;
	while($rowIndex++<count($lostArr)+5)
	{
	    $i = $lost_start_letter;
	    while($i!=$lost_end_letter)
	    {
		    self::$objPHPExcel->getActiveSheet()->getStyle($i.$rowIndex)->applyFromArray
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
    
	 
	//-- HEADER TEXTS
	$i = $lost_start_letter; 
	self::$objPHPExcel->getActiveSheet()
		    ->setCellValue($i++."5", "BRANCH")
		    ->setCellValue($i++."5", "REGION")
		    ->setCellValue($i++."5", "COUNTRY")
		    ->setCellValue($i++."5", "VOLUME SALES LY")
		    ->setCellValue($i++."5", "VALUE SALES LY")
		    ->setCellValue($i++."5", "PRICE LY");
	if(self::$skuMode=="M")
		self::$objPHPExcel->getActiveSheet()
			    ->setCellValue($i++."5", "CUSTOMERS LY");
	
	
	
	//-- SET COLUMN AUTO SIZE PROPERTIES
	$i = $lost_start_letter;
	while($i!=$lost_end_letter)
	{
	    self::$objPHPExcel->getActiveSheet()
		->getColumnDimension($i)
		->setAutoSize(true);
	    $i++;
    
	}
	
	$rowIndex = 6;
	foreach($lostArr as $key=>$data)
	{
	       $price_ly = $data['LY_VOL']>0?$data['LY_VAL']/$data['LY_VOL']:0;
		$i = $lost_start_letter;
		self::$objPHPExcel->getActiveSheet()
		    ->setCellValue($i++.$rowIndex, $data['BRANCH'])
		    ->setCellValue($i++.$rowIndex, $data['REGION'])
		    ->setCellValue($i++.$rowIndex, $data['COUNTRY'])
		    ->setCellValue($i++.$rowIndex, $data['LY_VOL'])
		    ->setCellValue($i++.$rowIndex, $data['LY_VAL'])
		    ->setCellValue($i++.$rowIndex, number_format($price_ly,2,'.',''));
	    if(self::$skuMode=="M")
		self::$objPHPExcel->getActiveSheet()
			    ->setCellValue($i++.$rowIndex, $data['LY_CUST']);
	    
		$rowIndex++;
	}
    }
    
    
    
    //---------------------------------------- SAVING ND DOWNLOAING FUNCTIONS --------------------------------------
    private static function saveAndDownload(){
	self::$objPHPExcel->setActiveSheetIndex(0);
	$filepath = self::saveXlsxFileToServer();
	self::downloadFile($filepath);
    }
    
    private static function saveXlsxFileToServer(){
	chdir("../zip/");
	$objWriter = \PHPExcel_IOFactory::createWriter(self::$objPHPExcel, 'Excel2007');
	$objWriter->save(getcwd().DIRECTORY_SEPARATOR."VS_LY.xlsx");
	$filePath = getcwd().DIRECTORY_SEPARATOR."VS_LY.xlsx";  
	return $filePath;
    }
    
    private static function downloadFile($fullPath){
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