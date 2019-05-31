<?php

namespace classes;


use projectsettings;
use config;
use lib;

class ZeroSalesCompare extends config\UlConfig {
    /*     * ***
     * go()
     * Default gateway function, should be similar in all DATA CLASS
     * arguments:
     * $settingVars [project settingsGateway variables]
     * @return $this->jsonOutput with all data that should be sent to client app
     * *** */

    public function go($settingVars) {
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES

        $action = $_REQUEST['action'];
        switch ($action) {
            case "uploadAndCompare":
                $this->uploadAndCompare();
                break;
        }

        return $this->jsonOutput;
    }
    
    public function verifyExt($file)
    {
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        if($ext != "csv")
            return false;
        else
            return true;
    }
    
    public function verifySize($file)
    {
        if($file['size'] <= 0)
            return false;
        else
            return true;
    }
    
    public function verifyMimeTypes($file)
    {
        $allowedMimeTypes = array('text/csv', 'application/vnd.ms-excel', 'application/octet-stream', 'text/comma-separated-values', 'application/csv', 'application/excel', 'application/vnd.msexcel');
        if(!in_array($file['type'], $allowedMimeTypes))
            return false;
        else
            return true;
    }
    
    public function getEndDate($file, $getHeader = false)
    {
        $handle = fopen($file['tmp_name'], "r");
        $row = 1;
        $date = "";
        while($data = fgetcsv($handle, 0, ","))
        {
            if($row == 1 && $getHeader)
            {
                $this->header = $data;
            }
            
            if($row == 2)
            {
                $date = $data[count($data)-1];
                break;
            }
            $row++;
        }
        return $date;
    }
    
    private function uploadAndCompare()
    {
        $file1 = $_FILES['file1'];
        $file2 = $_FILES['file2'];

        if(!$this->verifyExt($file1) || !$this->verifyExt($file2))
            $this->jsonOutput['error'] = "Only .csv file allowed!";
        elseif(!$this->verifySize($file1) || !$this->verifySize($file2))
            $this->jsonOutput['error'] = "Uploaded File should not be empty!";
        elseif (!$this->verifyMimeTypes($file1) || !$this->verifyMimeTypes($file2))
            $this->jsonOutput['error'] = 'Invalid file format. Please re-download the current content, and ensure that no columns are added or removed in your revised file. Please also ensure that no column headers have been renamed.';
        else
        {
            $date1 = str_replace('/', '-', $this->getEndDate($file1, true));
            $date2 = str_replace('/', '-', $this->getEndDate($file2));


            if($date1 != "" && $date2 != "")
            {
                if($date1 == $date2)
                    $this->jsonOutput['error'] = "Both file has same date!, Please correct the file and upload again.";
                else
                {
                    // decide file1 and file2 base on end date col, small date will be file1
                    $path = getcwd()."/uploads/zeroSalesCompare";
                    $fileName1 = "file1_uploaded_".date('dmy_his').".csv";
                    $fileName2 = "file2_uploaded_".date('dmy_his').".csv";

                    if(strtotime($date1) < strtotime($date2))
                    {
                        move_uploaded_file($file1['tmp_name'], "$path/$fileName1");
                        move_uploaded_file($file2['tmp_name'], "$path/$fileName2");                        
                    }
                    else
                    {
                        move_uploaded_file($file2['tmp_name'], "$path/$fileName1");
                        move_uploaded_file($file1['tmp_name'], "$path/$fileName2");
                    }
                    
                    $excelLibPath = $_SERVER['DOCUMENT_ROOT']."/ppt/Classes/PHPExcel.php";
                    include_once $excelLibPath;
                    $objPHPExcel = new \PHPExcel();
                    $objPHPExcel->getProperties()->setCreator("Ultralysis")->setTitle("Zero Sales Compare")->setSubject("Zero Sales Compare");

                    $zeroSalesSheet = new \PHPExcel_Worksheet($objPHPExcel, "Zero-Sales-Compare-Status");
                    $objPHPExcel->addSheet($zeroSalesSheet, 0);
                    $objPHPExcel->setActiveSheetIndex(0);
                    $dataSheet = $objPHPExcel->getActiveSheet();
                    
                    $col = 0;
                    $this->header[] = "STATUS";
                    foreach($this->header as $data)
                    {
                        $dataSheet->setCellValueByColumnAndRow($col, 1, $data);
                        $col++;
                    }
                    $this->rowNbr = 2;

                    $projectTypeID = $this->settingVars->projectTypeID;
                    
                    // Fixed Zero Sales (in A but not in B)
                    $logicFile = getcwd() . '/batch/zeroSalesCompare/in-A-but-not-in-B_'.$projectTypeID.'.sh';
                    $result = shell_exec("/bin/sh ".$logicFile." ".$path."/".$fileName2." ".$path."/".$fileName1);
                    $this->createDataSheet($objPHPExcel, "Fixed", $result, $dataSheet);
                    
                    // New Zero Sales (In B but not in A)
                    $logicFile = getcwd() . '/batch/zeroSalesCompare/in-B-but-not-in-A_'.$projectTypeID.'.sh';
                    $result = shell_exec("/bin/sh ".$logicFile." ".$path."/".$fileName1." ".$path."/".$fileName2);
                    $this->createDataSheet($objPHPExcel, "New", $result, $dataSheet);

                    // Continued Zero Sales (in A and B)
                    $logicFile = getcwd() . '/batch/zeroSalesCompare/in-A-and-in-B_'.$projectTypeID.'.sh';
                    $result = shell_exec("/bin/sh ".$logicFile." ".$path."/".$fileName1." ".$path."/".$fileName2);
                    $this->createDataSheet($objPHPExcel, "Continued", $result, $dataSheet);
                    
                    $objPHPExcel->setActiveSheetIndex(0);
                    $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
                    $objWriter->setPreCalculateFormulas(true);
                    $fileName = "Zero-Sales-Compare-" . date("Y-m-d-h-i-s") . ".xlsx";
                    chdir($path);
                    $objWriter->save($fileName);
                    $this->jsonOutput['downloadLink'] = $this->settingVars->get_full_url()."/".basename(dirname(dirname(__FILE__)))."/uploads/zeroSalesCompare/".$fileName;
                    
                    unlink($path."/".$fileName1);
                    unlink($path."/".$fileName2);
                }
            }
        }
    }
    
    public function createDataSheet(&$objPHPExcel, $status, $result, &$dataSheet)
    {
        $result = explode("\r\n", $result);
        if($status == "Continued")
            unset($result[0]);
        
        foreach($result as $row)
        {
            if($row == "")
                continue;
            
            $rowData = explode(",", $row);
            $col = 0;
            foreach($rowData as $data)
            {
                $dataSheet->setCellValueByColumnAndRow($col, $this->rowNbr, ($data != "" ? $data : ""));
                $col++;
            }
            $dataSheet->setCellValueByColumnAndRow($col, $this->rowNbr, $status);
            $this->rowNbr++;
        }
    }
    
}

?>