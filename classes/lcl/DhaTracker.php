<?php

namespace classes\lcl;

use datahelper;
use projectsettings;
use filters;
use utils;
use db;
use config;

class DhaTracker extends config\UlConfig {
	
	private $requestDays,$getLatestDaysDate,$getLastDaysDate;
    /** ***
    * Default gateway function, should be similar in all DATA CLASS
    * arguments:
    * $settingVars [project settingsGateway variables]
    * @return $xmlOutput with all data that should be sent to client app
    * *** */
    public function go($settingVars) {
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES

        $this->settingVars->pageName = (empty($this->settingVars->pageName) && !empty($this->settingVars->pageID)) ? $this->settingVars->pageID.'_DhaTrackerPage' : $this->settingVars->pageName;

        if ($this->settingVars->isDynamicPage) {

            if (isset($_REQUEST['Field']) && !empty($_REQUEST['Field'])) {
                $this->accountField = $_REQUEST['Field'];
            }else{
                $this->accountField = $this->getPageConfiguration('account_field', $this->settingVars->pageID)[0];
            }
			
            $fieldArray = array($this->accountField);
                    
			$this->buildDataArray($fieldArray,true);
			$this->buildPageArray();
		} else {
			$this->configurationFailureMessage();
	    }
		
		if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
            $this->getFieldSelection();
            $accountFieldPart = explode("#", $this->accountField);
            $this->jsonOutput['selectedField'] = $accountFieldPart[0];
		}

        //$this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll
		$action = $_REQUEST["action"];
        $this->ValueVolume = getValueVolume($this->settingVars);
        switch ($action) {
            case "getData":
                $this->getData();
				break;
        }
		return $this->jsonOutput;
    }

    public function getFieldSelection(){
        //$tables = array();
        /*if ($this->settingVars->isDynamicPage)
            $tables = $this->getPageConfiguration('table_settings', $this->settingVars->pageID);
        else {
            if (isset($this->getPageAnalysisField) && $this->getPageAnalysisField == 'Product')
                $tables = array($this->settingVars->skutable);
            else if (isset($this->getPageAnalysisField) && $this->getPageAnalysisField == 'Store')
                $tables = array("market");
        }*/
        $tables = array($this->settingVars->skutable);

        if (is_array($tables) && !empty($tables)) {
            $fields = $tmpArr = array();
            foreach ($tables as $table) {
                $query = "SELECT setting_value FROM ".$this->settingVars->configTable." WHERE accountID=" . $this->settingVars->aid . " AND projectID = " . $this->settingVars->projectID . " AND setting_name = '" . $table . "_settings' ";
                $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->projectManagerLinkid, db\ResultTypes::$TYPE_OBJECT);
                if (is_array($result) && !empty($result)) {
                    $settings = explode("|", $result[0]['setting_value']);
                    foreach ($settings as $key => $field) {
                        $val = explode("#", $field);
                        $fields[] = $val[0];

                        if ($key == 0) {
                            $appendTable = ($table == 'market') ? 'store' : $table;
                            $this->settingVars->pageArray[$this->settingVars->pageName]["ACCOUNT"] = $appendTable . "." . $val[0];
                        }
                    }
                }
            }

            $this->buildDataArray($fields, false);

            foreach ($this->dbColumnsArray as $csvCol => $dbCol) {
                if ($this->settingVars->pageArray[$this->settingVars->pageName]["ACCOUNT"] == $dbCol) {
                    $this->settingVars->pageArray[$this->settingVars->pageName]["ACCOUNT"] = $csvCol;
                }

                $tmpArr[] = array('label' => $this->displayCsvNameArray[$csvCol], 'data' => $csvCol);
            }

            $this->jsonOutput['fieldSelection'] = $tmpArr;
        } elseif (isset($this->settingVars->pageArray[$this->settingVars->pageName]["ACCOUNT"]) &&
                !empty($this->settingVars->pageArray[$this->settingVars->pageName]["ACCOUNT"])) {
            $this->skipDbcolumnArray = true;
        } else {
            $response = array("configuration" => array("status" => "fail", "messages" => array("Page isn't configured properly.")));
            echo json_encode($response);
            exit();
        }
    }

    public function getData()
    {
        $productTypeField = $this->settingVars->skutable.".productType";
        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $this->measureFields[] = $this->accountID;
        $this->measureFields[] = $this->accountName;
        $this->measureFields[] = $productTypeField;
    
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }    
    
        $this->queryPart = $this->getAll();

        $options = array();
        if (!empty(filters\timeFilter::$tyWeekRange)){
            $options['tyLyRange']['MCOST'] = filters\timeFilter::$tyWeekRange." AND ".$productTypeField." IN('DHA') ";
            $options['tyLyRange']['DTY'] = filters\timeFilter::$tyWeekRange." AND ".$productTypeField." IN('DHA','NON DHA') ";
        }

        if (!empty(filters\timeFilter::$lyWeekRange)){
            $options['tyLyRange']['PCOST'] = filters\timeFilter::$lyWeekRange." AND ".$productTypeField." IN('DHA') ";
            $options['tyLyRange']['DLY'] = filters\timeFilter::$lyWeekRange." AND ".$productTypeField." IN('DHA','NON DHA') ";
        }

        $measureSelect = config\MeasureConfigure::prepareSelect($this->settingVars, $this->queryVars, array('M'.$_REQUEST['ValueVolume']), $options);
        $measureSelect = implode(", ", $measureSelect);
    
        $query = "SELECT ".$this->accountName." AS ACCOUNT, ".
            " ".$measureSelect." ".
            //"SUM((CASE WHEN ".filters\timeFilter::$tyWeekRange." AND ".$productTypeField." IN('DHA') THEN 1 ELSE 0 END) * " . $this->ValueVolume . " ) AS MCOST, ".
            //"SUM((CASE WHEN ".filters\timeFilter::$lyWeekRange." AND ".$productTypeField." IN('DHA') THEN 1 ELSE 0 END) * " . $this->ValueVolume . " ) AS PCOST, ".
            //"SUM((CASE WHEN ".filters\timeFilter::$tyWeekRange." AND ".$productTypeField." IN('DHA','NON DHA') THEN 1 ELSE 0 END) * " . $this->ValueVolume . " ) AS DTY, ".
            //"SUM((CASE WHEN ".filters\timeFilter::$lyWeekRange." AND ".$productTypeField." IN('DHA','NON DHA') THEN 1 ELSE 0 END) * " . $this->ValueVolume . " ) AS DLY ".					
            "FROM ".$this->settingVars->tablename . " " . $this->queryPart." AND (".filters\timeFilter::$tyWeekRange ." OR ". filters\timeFilter::$lyWeekRange .") ".
            "GROUP BY ACCOUNT "."HAVING (MCOST>0 OR PCOST>0) "."ORDER BY MCOST DESC";
        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);

        $totalsArray = array();
        if(is_array($result) && !empty($result))
        {
            $tmp = $output = array();
            foreach($result as $key=>$data)
            {
                $dhaTY	= $data['DTY']>0 ? ($data['MCOST']/$data['DTY'])*100 : 0;
                $dhaLY	= $data['DLY']>0 ? ($data['PCOST']/$data['DLY'])*100 : 0;
                
                $totalsArray['MCOST']	+= $data['MCOST'];
                $totalsArray['PCOST']	+= $data['PCOST'];
                $totalsArray['DTY']		+= $data['DTY'];
                $totalsArray['DLY']		+= $data['DLY'];                
                
                $tmp['ACCOUNT'] = htmlspecialchars($data['ACCOUNT']);
                $tmp['TYSALES'] = (double)$data['MCOST'];
                $tmp['LYSALES'] = (double)$data['PCOST'];
                $tmp['DHA_TY']  = $dhaTY;
                $tmp['DHA_LY']  = $dhaLY;
                $tmp['DIFF']    = $dhaTY-$dhaLY;
                
                $output[] = $tmp;
            }
            $total_DHA_ty = $totalsArray['DTY']>0?number_format(($totalsArray['MCOST']/$totalsArray['DTY'])*100, 2, '.', ''):0;
            $total_DHA_ly = $totalsArray['DLY']>0?number_format(($totalsArray['PCOST']/$totalsArray['DLY'])*100, 2, '.', ''):0;            
            
            $this->jsonOutput['gridData'] = $output;
        }
    }
    
	/*****
    * COLLECTS TABLE JOINING STRING FROM PROJECT SETTINGS
    * ALSO APPLYS FILTER CONDITIONS TO THE STRING
    * AND RETURNS $tablejoins_and_filters
    *****/   
    public function getAll(){
		
		$tablejoins_and_filters = "";
		$extraFields = array();
		
		$this->prepareTablesUsedForQuery($extraFields);
		$tablejoins_and_filters1	= parent::getAll();
		$tablejoins_and_filters1 	.= $tablejoins_and_filters;

		return $tablejoins_and_filters1;
	}

	public function buildPageArray() {

		$accountFieldPart = explode("#", $this->accountField);

		if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
		    $fetchConfig = true;
			$this->jsonOutput['pageConfig'] = array(
				'enabledFilters' => $this->getPageConfiguration('filter_settings', $this->settingVars->pageID)
			);

            $this->jsonOutput['gridColumns']['ACCOUNT'] = (count($accountFieldPart) > 1) ? $this->displayCsvNameArray[$accountFieldPart[1]] : $this->displayCsvNameArray[$accountFieldPart[0]];
        }
        
        $accountField = strtoupper($this->dbColumnsArray[$accountFieldPart[0]]);
        $accountField = (count($accountFieldPart) > 1) ? strtoupper($accountField."_".$this->dbColumnsArray[$accountFieldPart[1]]) : $accountField;
        
        $this->accountID = (isset($this->settingVars->dataArray[$accountField]) && isset($this->settingVars->dataArray[$accountField]['ID'])) ? $this->settingVars->dataArray[$accountField]['ID'] : $this->settingVars->dataArray[$accountField]['NAME'];
        $this->accountName = $this->settingVars->dataArray[$accountField]['NAME'];

        return;
    }
	
	public function buildDataArray($fields, $isCsvColumn) {
        if (empty($fields))
            return false;

        $configurationCheck = new config\ConfigurationCheck($this->settingVars, $this->queryVars);
        $configurationCheck->buildDataArray($fields, $isCsvColumn);
        $this->dbColumnsArray = $configurationCheck->dbColumnsArray;
        $this->displayCsvNameArray = $configurationCheck->displayCsvNameArray;
        return;
    }

}

?>