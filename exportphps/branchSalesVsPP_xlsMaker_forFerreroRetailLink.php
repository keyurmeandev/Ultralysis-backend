<?php
namespace exportphps;
ini_set("memory_limit", "150M");

 class branchSalesVsPP_xlsMaker_forFerreroRetailLink{
    
    public static $objPHPExcel;
	
    public static function createXlsFile($gainArray,$maintainArray,$lostArray){
		global $gainArr,$maintainArr,$lostArr;
		$gainArr 		= $gainArray;
		$maintainArr 	= $maintainArray;
		$lostArr 		= $lostArray;
		
		
		/**************************** INITIATE EXCEL FILE ***********************************************/	
		//-- Error reporting
		error_reporting(E_ALL);
		ini_set('display_errors', TRUE);
		ini_set('display_startup_errors', TRUE);
		
		//-- Create new PHPExcel object
		include "../ppt/Classes/PHPExcel.php";
		self::$objPHPExcel = new \PHPExcel();
		
		//-- Set document properties
		self::$objPHPExcel->getProperties()->setCreator("Ultralysis")
						->setTitle("Download Store Sales vs PP")
						->setSubject("Product KBI Report")
						->setLastModifiedBy('Anas Bin Numan'); //DEVELOPER WHO RECENTLY WORKED ON THIS FILE;
		$newWorkSheet = new \PHPExcel_Worksheet(self::$objPHPExcel, "VS PP");
		self::$objPHPExcel->addSheet($newWorkSheet,0);
		self::$objPHPExcel->setActiveSheetIndex(0);
		/*************************************************************************************************/
		
		
		/**************************** DEFAULT TEXT STUFF *************************************************/
		global $gain_start_letter,$gain_end_letter,$maintain_start_letter,$maintain_end_letter,$lost_start_letter,$lost_end_letter;
		$gain_start_letter 		= "A";
		$gain_end_letter 		= "G";
		$maintain_start_letter 	= "I";
		$maintain_end_letter 	= "R";
		$lost_start_letter 		= "T";
		$lost_end_letter		= "AA";
			
		self::$objPHPExcel->getActiveSheet()
				->setCellValue("A1", 						$_REQUEST['TPNB'].":".$_REQUEST['SKU'])
				->setCellValue($gain_start_letter."2", 		"STORES GAINED")
				->setCellValue($maintain_start_letter."2", 	"STORES MAINTAINED")
				->setCellValue($lost_start_letter."2", 		"STORES LOST");
		self::$objPHPExcel->getActiveSheet()->getStyle("A1:Z5")->getFont()->setBold(true);
		/*********************************************************************/

		self::createStoresGained();
		self::createStoresMaintained();
		self::createStoresLost();
		self::saveAndDownload();
    }


    private static function createStoresGained(){
		global $gainArr,$gain_start_letter,$gain_end_letter;
		
		/************** STYLING ****************************************************************/
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
						'bottom'	=> array('style' => \PHPExcel_Style_Border::BORDER_THIN),
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
		/******* STYLING DONE ********************************************************************/
		
		
		/******* ADDING TEXTS AND CELL VALUES ****************************************************/
		//-- HEADER TEXTS
		self::$objPHPExcel->getActiveSheet()
			->setCellValue("A5", "SNO")
			->setCellValue("B5", "STORE")
			->setCellValue("C5", "BARB")
			->setCellValue("D5", "CITY")
			->setCellValue("E5", "VOLUME TP")
			->setCellValue("F5", "VALUE TP")
			->setCellValue("G5", "PRICE TP");		
		
		$rowIndex = 6;
		foreach($gainArr as $key=>$data)
		{
			$priceTp = $data['TP_VOL']>0?$data['TP_VAL']/$data['TP_VOL']:0;
			$i = $gain_start_letter;
			self::$objPHPExcel->getActiveSheet()
			->setCellValue($i++.$rowIndex, $data['SNO'])
			->setCellValue($i++.$rowIndex, $data['STORE'])
			->setCellValue($i++.$rowIndex, $data['BARB'])
			->setCellValue($i++.$rowIndex, $data['CITY'])
			->setCellValue($i++.$rowIndex, $data['TP_VOL'])
			->setCellValue($i++.$rowIndex, $data['TP_VAL'])
			->setCellValue($i++.$rowIndex, number_format($priceTp , 2 , '.' , ''));			
			$rowIndex++;
		}
		
		//-- SET COLUMN AUTO SIZE PROPERTIES
		for($i=$gain_start_letter;$i<=$gain_end_letter;$i++)
		{
			self::$objPHPExcel->getActiveSheet()
			->getColumnDimension($i)
			->setAutoSize(true);
		}
		/*****************************************************************************************/
    }

	
    private static function createStoresMaintained(){
		global $maintainArr,$maintain_start_letter,$maintain_end_letter;
		
		/*********** STYLING *********************************************************************/
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
						'bottom'	=> array('style' => \PHPExcel_Style_Border::BORDER_THIN),
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
		/************ STYLING DONE *************************************************************/
		
		
		
		
		/********* ADDING TEXTS AND CELL VALUE *************************************************/
		//-- HEADER TEXTS
		$i = $maintain_start_letter;
		self::$objPHPExcel->getActiveSheet()
				->setCellValue($i++."5", "SNO")
				->setCellValue($i++."5", "STORE")
				->setCellValue($i++."5", "BARB")
				->setCellValue($i++."5", "CITY")
				->setCellValue($i++."5", "VOLUME TP")
				->setCellValue($i++."5", "VOLUME var PP")
				->setCellValue($i++."5", "VALUE TP")
				->setCellValue($i++."5", "VALUE var PP")
				->setCellValue($i++."5", "PRICE TP")
				->setCellValue($i++."5", "PRICE PP");
			
		$rowIndex = 6;
		foreach($maintainArr as $key=>$data)
		{
			$price_tp 	= $data['TP_VOL'] > 0 ? $data['TP_VAL'] / $data['TP_VOL']:0;
			$price_pp 	= $data['PP_VOL'] > 0 ? $data['PP_VAL'] / $data['PP_VOL']:0;
		
			$vol_var_pp = $data['TP_VOL'] - $data['PP_VOL'];
			$val_var_pp = $data['TP_VAL'] - $data['PP_VAL'];
			
			$i = $maintain_start_letter;
			self::$objPHPExcel->getActiveSheet()
				->setCellValue($i++.$rowIndex, $data['SNO'])
				->setCellValue($i++.$rowIndex, $data['STORE'])
				->setCellValue($i++.$rowIndex, $data['BARB'])
				->setCellValue($i++.$rowIndex, $data['CITY'])
				->setCellValue($i++.$rowIndex, $data['TP_VOL'])
				->setCellValue($i++.$rowIndex, number_format($vol_var_pp , 0 , '.' , '') )
				->setCellValue($i++.$rowIndex, $data['TP_VAL'])
				->setCellValue($i++.$rowIndex, number_format($val_var_pp , 0 , '.' , '') )
				->setCellValue($i++.$rowIndex, number_format($price_tp , 2 , '.' , '') )
				->setCellValue($i++.$rowIndex, number_format($price_pp , 2 , '.' , '') );			
			$rowIndex++;
		}
		
		//-- SET COLUMN AUTO SIZE PROPERTIES
		for($i=$maintain_start_letter;$i<=$maintain_end_letter;$i++)
		{
			self::$objPHPExcel->getActiveSheet()
			->getColumnDimension($i)
			->setAutoSize(true);
		}
		

		/****************************************************************************************/
    }
    
    
    private static function createStoresLost(){
		global $lostArr,$lost_start_letter,$lost_end_letter;
		
		/******* STYLING ******************************************************/
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
		/****** STYLING DONE **********************************************************************/
		
		/****** ADDING TEXTS AND CELL VALUES ******************************************************/
		//-- HEADER TEXTS
		$i = $lost_start_letter;
		self::$objPHPExcel->getActiveSheet()
				->setCellValue($i++."5", "SNO")
				->setCellValue($i++."5", "STORE")
				->setCellValue($i++."5", "BARB")
				->setCellValue($i++."5", "CITY")
				->setCellValue($i++."5", "VOLUME SALES PP")
				->setCellValue($i++."5", "VALUE SALES PP")
				->setCellValue($i++."5", "PRICE PP");		
			
		$rowIndex = 6;
		foreach($lostArr as $key=>$data)
		{
			$price_pp = $data['PP_VOL']>0?$data['PP_VAL']/$data['PP_VOL']:0;
			$i = $lost_start_letter;
			self::$objPHPExcel->getActiveSheet()
			->setCellValue($i++.$rowIndex, $data['SNO'])
			->setCellValue($i++.$rowIndex, $data['STORE'])
			->setCellValue($i++.$rowIndex, $data['BARB'])
			->setCellValue($i++.$rowIndex, $data['CITY'])
			->setCellValue($i++.$rowIndex, $data['PP_VOL'])
			->setCellValue($i++.$rowIndex, $data['PP_VAL'])
			->setCellValue($i++.$rowIndex, number_format($price_pp , 2 ,'.' , '') );
			$rowIndex++;
		}
		
		//-- SET COLUMN AUTO SIZE PROPERTIES
		$i = $lost_start_letter;
		while($i!=$lost_end_letter)
		{
			self::$objPHPExcel->getActiveSheet()
			->getColumnDimension($i)
			->setAutoSize(true);
			$i++;
		}
		/************************************************************************************/
    }
    
    
    //---------------------------------------- SAVING AND DOWNLOADING FUNCTIONS --------------------------------------
    private static function saveAndDownload(){
		self::$objPHPExcel->setActiveSheetIndex(0);
		$filepath = self::saveXlsxFileToServer();
		self::downloadFile($filepath);
    }
    
    private static function saveXlsxFileToServer(){
		chdir("../zip/");
		$objWriter = \PHPExcel_IOFactory::createWriter(self::$objPHPExcel, 'Excel2007');
		$objWriter->save(getcwd().DIRECTORY_SEPARATOR."VS_PP.xlsx");
		$filePath = getcwd().DIRECTORY_SEPARATOR."VS_PP.xlsx";  
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
				header("Content-disposition: attachment; filename="."VS_PP.xlsx");
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
	//-----------------------------------------------------------------------------------------------------------
}
?>