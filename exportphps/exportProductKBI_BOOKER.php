<?php
set_time_limit(100000000);
/** Error reporting */
error_reporting(E_ALL);
ini_set('display_errors', TRUE);
ini_set('display_startup_errors', TRUE);
define('EOL',(PHP_SAPI == 'cli') ? PHP_EOL : '<br />');
date_default_timezone_set('Europe/London');



/** PHPExcel_IOFactory */
global $objPHPExcel; 
require_once '../../ppt/Classes/PHPExcel/IOFactory.php';
$objReader = PHPExcel_IOFactory::createReader('Excel2007');

$skuMode = $_REQUEST['skuMode'];
$objPHPExcel =  $skuMode=="P" ? $objReader->load("templates/product_kbi_export_template.xlsx") : $objReader->load("templates/product_kbi_export_template_with_customers.xlsx");

//$dataView  = (array)json_decode($_REQUEST['listData']);


$dataArr = (array)json_decode($_REQUEST['dataArr']);
//echo "<pre>";print_r($dataArr);exit;

addData($dataArr);
saveAndDownload();

function addData($data){
	global $objPHPExcel,$skuMode;
	$baseRow = 5;
	foreach($data as $r => $dataRow) {
		$row = $baseRow + (int)$r;
		$objPHPExcel->getActiveSheet()->insertNewRowBefore($row,1);
	
		$objPHPExcel->getActiveSheet()->setCellValue("A".$row, $dataRow->TPNB)//TPNB
					      ->setCellValue("B".$row, $dataRow->SKU)//SKU
					      ->setCellValue("C".$row, $dataRow->VOL_TP)//VOL_TP
					      ->setCellValue("D".$row, $dataRow->VOL_PP_VAR)//VOL_VAR_PP
					      ->setCellValue("E".$row, $dataRow->VOL_PP_VAR_PCT)//VOL_VAR_PCT_PP
					      ->setCellValue("F".$row, $dataRow->VOL_LY_VAR)//VOL_VAR_LY
					      ->setCellValue("G".$row, $dataRow->VOL_LY_VAR_PCT)//VOL_VAR_PCT_LY
					      ->setCellValue("H".$row, $dataRow->VOL_TP_SHARE)//VOL_SHARE_TP
					      ->setCellValue("I".$row, $dataRow->VOL_PP_SHARE)//VOL_SHARE_PP
					      ->setCellValue("J".$row, $dataRow->VOL_LY_SHARE)//VOL_SHARE_LY
					      ->setCellValue("K".$row, $dataRow->VAL_TP)//VAL_TP
					      ->setCellValue("L".$row, $dataRow->VAL_PP_VAR)//VAL_VAR_PP
					      ->setCellValue("M".$row, $dataRow->VAL_PP_VAR_PCT)//VAL_VAR_PCT_PP
					      ->setCellValue("N".$row, $dataRow->VAL_LY_VAR)//VAL_VAR_LY
					      ->setCellValue("O".$row, $dataRow->VAL_LY_VAR_PCT)//VAL_VAR_PCT_LY
					      ->setCellValue("P".$row, $dataRow->VAL_TP_SHARE)//VAL_SHARE_TP
					      ->setCellValue("Q".$row, $dataRow->VAL_PP_SHARE)//VAL_SHARE_PP
					      ->setCellValue("R".$row, $dataRow->VAL_LY_SHARE)//VAL_SHARE_LY
					      ->setCellValue("S".$row, $dataRow->PRICE_TP)//PRICE_TP
					      ->setCellValue("T".$row, $dataRow->PRICE_PP)//PRICE_PP
					      ->setCellValue("U".$row, $dataRow->PRICE_LY)//PRICE_LY
					      ->setCellValue("V".$row, $dataRow->DIST_TP)//DIST_TP
					      ->setCellValue("W".$row, $dataRow->DIST_PP)//DIST_PP
					      ->setCellValue("X".$row, $dataRow->DIST_LY);//DIST_LY
					      
		if($skuMode == "M")
		{
			$objPHPExcel->getActiveSheet()->setCellValue("Y".$row, $dataRow->CUST_TP)//CUST_TP
						      ->setCellValue("Z".$row, $dataRow->CUST_PP)//CUST_PP
						      ->setCellValue("AA".$row, $dataRow->CUST_LY);//CUST_LY				      
		}
	}
	$objPHPExcel->getActiveSheet()->removeRow($baseRow-1,1);
}







//---------------------------------------- SAVES AND DOWNLOADS EXCEL FILE --------------------------------------
function saveAndDownload(){
	global $objPHPExcel;
	$objPHPExcel->setActiveSheetIndex(0);
	$filepath = saveXlsxFileToServer();
	downloadFile($filepath);
}



function saveXlsxFileToServer(){
	global $objPHPExcel;
	chdir("../../zip/");
	$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
	$objWriter->save(getcwd().DIRECTORY_SEPARATOR."productKBI.xlsx");
	$filePath = getcwd().DIRECTORY_SEPARATOR."productKBI.xlsx";  
	return $filePath;
}

function downloadFile($fullPath){
	if ($fd = fopen ($fullPath, "r")){
	$fsize = filesize($fullPath);
	$path_parts = pathinfo($fullPath);
	$ext = strtolower($path_parts["extension"]);
	switch ($ext) {
	    case "xlsx":
		{
		    header("Content-type: application/force-download");
		    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		    header("Content-disposition: attachment; filename="."productKBI.xlsx");
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
    

?>