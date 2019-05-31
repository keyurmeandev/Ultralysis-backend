<?php

namespace classes\relayplus;

use datahelper;
use projectsettings;
use filters;
use db;
use config;
use lib;

class WhsFillPerformance extends config\UlConfig {

    /** ***
	 * go()
     * Default gateway function, should be similar in all DATA CLASS
     * arguments:
     * $settingVars [project settingsGateway variables]
     * @return $this->jsonOutput with all data that should be sent to client app
     */
    public function go($settingVars) {
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES

        $this->settingVars->pageName = (empty($this->settingVars->pageName) && !empty($this->settingVars->pageID)) ? $this->settingVars->pageID.'_WhsFillPerformancePage' : $this->settingVars->pageName;

        if ($this->settingVars->isDynamicPage) {
			$this->accountField = $this->getPageConfiguration('account_field', $this->settingVars->pageID)[0];
			$this->buildDataArray(array($this->accountField));
			$this->buildPageArray();
		} else {
			$this->settingVars->pageName = (empty($this->settingVars->pageName)) ? 'WHS_FILL_PERFORMANCE_PAGE' : $this->settingVars->pageName;
            if (!isset($this->settingVars->pageArray[$this->settingVars->pageName]) || empty($this->settingVars->pageArray[$this->settingVars->pageName]))
                $this->configurationFailureMessage();

            $this->accountID = (isset($this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['ACCOUNT']]) && isset($this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['ACCOUNT']]['ID'])) ? $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['ACCOUNT']]['ID'] : $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['ACCOUNT']]['NAME'];
            $this->accountName = $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['ACCOUNT']]['NAME'];
	    }

		$this->fetchConfig(); // Fetching filter configuration for page	
        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll
		
		$action = $_REQUEST['action'];
        //COLLECTING TOTAL SALES  
        switch ($action) {
            case 'filterBySku':
                $this->whsFillPerformancePOGrid();				
                break;
			case 'filterBySkuPO':                
				$this->whsFillComments();
                break;
			case 'newcomment':
                $this->whsNewComment();				
				$this->whsFillComments();
                break;
			case 'updatePrivacyFlag':
				$this->whsFillComments();
                break;
            case 'whsFillPerformanceGrid':
                $this->whsFillPerformanceGrid();                
                break;
        }
		
        return $this->jsonOutput;
    }

    public function fetchConfig()
    {
        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
            $this->jsonOutput['gridConfig'] = array(
                'enabledFilters' => $this->getPageConfiguration('filter_settings', $this->settingVars->pageID)
            );

            if (!$this->settingVars->isDynamicPage) {
            	$this->jsonOutput['gridColumns']['SKUID'] = (isset($this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['ACCOUNT']])
            												 && isset($this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['ACCOUNT']]['ID_ALIASE'])
            												 ) ? $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['ACCOUNT']]['ID_ALIASE'] : $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['ACCOUNT']]['NAME_ALIASE'];
            	$this->jsonOutput['gridColumns']['SKU'] = $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['ACCOUNT']]['NAME_ALIASE'];
        	}
        }
    }	
	
	/**
	 * whsFillPerformanceGrid()
     * This Function is used to retrieve list based on set parameters     
	 * @access private	
     * @return $this->jsonOutput with all data that should be sent to client app     
     */
    private function whsFillPerformanceGrid() {

		$latestWeek   = filters\timeFilter::getLatest_n_dates(0, 52, $this->settingVars);
		$latest4Week  = implode(',', array_slice($latestWeek, 0, 4));
		$latest13Week = implode(',', array_slice($latestWeek, 0, 13));
		$latest52Week = implode(',', array_slice($latestWeek, 0, 52));
		$latestWeek   = array_slice($latestWeek, 0, 1);
		
		filters\timeFilter::getYTD($this->settingVars);
		$ytdTyWeekRange = filters\timeFilter::$tyWeekRange;

        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $this->measureFields[] = $this->accountID;
        $this->measureFields[] = $this->accountName;
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->queryPart = $this->getAll();		
		
        //MAIN TABLE QUERY
    	$query = "SELECT ".$this->accountID." AS SKUID " .
                ",".$this->accountName." AS SKU " .
                ",SUM(".$this->settingVars->ProjectValue.") AS SALES" .
                ",SUM(".$this->settingVars->ProjectVolume.") AS QTY" .
                ",SUM((CASE WHEN CONCAT(" . $this->settingVars->yearperiod . "," . $this->settingVars->weekperiod . ") IN (" . implode(",", $latestWeek) . ") AND PO_Cancel < insertdate THEN 1 ELSE 0 END)*WhsReceived) AS LW_Fill_WhsReceived" .
                ",SUM((CASE WHEN CONCAT(" . $this->settingVars->yearperiod . "," . $this->settingVars->weekperiod . ") IN (" . implode(",", $latestWeek) . ") AND PO_Cancel < insertdate THEN 1 ELSE 0 END)*WhsOrdered) AS LW_Fill_WhsOrdered" .
				",SUM((CASE WHEN CONCAT(" . $this->settingVars->yearperiod . "," . $this->settingVars->weekperiod . ") IN (" . $latest4Week. ") AND PO_Cancel < insertdate THEN 1 ELSE 0 END)*WhsReceived) AS L4_Fill_WhsReceived" .
                ",SUM((CASE WHEN CONCAT(" . $this->settingVars->yearperiod . "," . $this->settingVars->weekperiod . ") IN (" . $latest4Week . ") AND PO_Cancel < insertdate THEN 1 ELSE 0 END)*WhsOrdered) AS L4_Fill_WhsOrdered" .
				",SUM((CASE WHEN CONCAT(" . $this->settingVars->yearperiod . "," . $this->settingVars->weekperiod . ") IN (" . $latest13Week . ") AND PO_Cancel < insertdate THEN 1 ELSE 0 END)*WhsReceived) AS L13_Fill_WhsReceived" .
                ",SUM((CASE WHEN CONCAT(" . $this->settingVars->yearperiod . "," . $this->settingVars->weekperiod . ") IN (" . $latest13Week . ") AND PO_Cancel < insertdate THEN 1 ELSE 0 END)*WhsOrdered) AS L13_Fill_WhsOrdered" .
				",SUM((CASE WHEN CONCAT(" . $this->settingVars->yearperiod . "," . $this->settingVars->weekperiod . ") IN (" . $latest52Week . ") AND PO_Cancel < insertdate THEN 1 ELSE 0 END)*WhsReceived) AS L52_Fill_WhsReceived" .
                ",SUM((CASE WHEN CONCAT(" . $this->settingVars->yearperiod . "," . $this->settingVars->weekperiod . ") IN (" . $latest52Week . ") AND PO_Cancel < insertdate THEN 1 ELSE 0 END)*WhsOrdered) AS L52_Fill_WhsOrdered" .
				",SUM((CASE WHEN " . $ytdTyWeekRange . " THEN 1 ELSE 0 END)*WhsReceived) AS YTD_Fill_WhsReceived" .
				",SUM((CASE WHEN " . $ytdTyWeekRange . " THEN 1 ELSE 0 END)*WhsOrdered) AS YTD_Fill_WhsOrdered" .
                " FROM " . $this->settingVars->tablename . $this->queryPart .
                " AND CONCAT(" . $this->settingVars->yearperiod . "," . $this->settingVars->weekperiod . ") IN  (" . $latest52Week . ") GROUP BY SKUID, SKU ORDER BY SALES DESC";

		//echo $query;exit;
        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
		$arr = array();
		if (is_array($result) && !empty($result)) {			
			
			foreach ($result as $key => $value) {
				$temp 				= array();
				$temp['SKUID'] 		= $value['SKUID'];
				$temp['SKU'] 		= $value['SKU'];
				$temp['SALES'] 		= number_format($value['SALES'], 1, '.', '');
				$temp['QTY'] 		= number_format($value['QTY'], 1, '.', '');
				$temp['LW_Fill'] 	= ($value['LW_Fill_WhsOrdered'] > 0) ? number_format(($value['LW_Fill_WhsReceived']/$value['LW_Fill_WhsOrdered'])*100, 1, '.', '') : 0;
				$temp['L4_Fill'] 	= ($value['L4_Fill_WhsOrdered'] > 0) ? number_format(($value['L4_Fill_WhsReceived']/$value['L4_Fill_WhsOrdered'])*100, 1, '.', '') : 0;
				$temp['L13_Fill'] 	= ($value['L13_Fill_WhsOrdered'] > 0) ? number_format(($value['L13_Fill_WhsReceived']/$value['L13_Fill_WhsOrdered'])*100, 1, '.', '') : 0;
				$temp['L52_Fill'] 	= ($value['L52_Fill_WhsOrdered'] > 0) ? number_format(($value['L52_Fill_WhsReceived']/$value['L52_Fill_WhsOrdered'])*100, 1, '.', '') : 0;
				$temp['YTD_Fill'] 	= ($value['YTD_Fill_WhsOrdered'] > 0) ? number_format(($value['YTD_Fill_WhsReceived']/$value['YTD_Fill_WhsOrdered'])*100, 1, '.', '') : 0;
				$arr[] 				= $temp;
			}
			
		} // end if
        $this->jsonOutput['whsFillPerformanceGrid'] = $arr;
    }

	/**
	 * whsFillPerformancePOGrid()
     * This Function is used to retrieve list based on set parameters     
	 * @access private	
     * @return $this->jsonOutput with all data that should be sent to client app     
     */
    private function whsFillPerformancePOGrid() {
		$items   = filters\timeFilter::getLatest_n_dates(0, 52, $this->settingVars);
        $latest52Week = implode(',', $items);

        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $this->measureFields[] = $this->accountID;
        $this->measureFields[] = $this->accountName;
		
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->queryPart = $this->getAll();		
		
		//MAIN TABLE QUERY
        $query = "SELECT ".$this->accountID." AS SKUID " .
                ",".$this->accountName." AS SKU " .
                ",PO_Number AS PO" .
                ",PO_Cancel AS PO_Cancel" .
                ",PO_Create AS Create_Date" .
                ",SUM(WhsOrdered) AS Order_Qty" .
                ",SUM(WhsReceived) AS Recd_Qty" .               
                " FROM " . $this->settingVars->tablename . $this->queryPart .
                " AND CONCAT(" . $this->settingVars->yearperiod . "," . $this->settingVars->weekperiod . ") IN  (" . $latest52Week . ")" .
                " AND PO_Cancel < insertdate GROUP BY SKUID, SKU, PO,PO_Cancel, Create_Date ORDER BY Create_Date DESC";

		//echo $query;exit;
        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
        
		$arr = array();
				
		if (is_array($result) && !empty($result)) {			
			
			foreach ($result as $key => $value) {
				
				$shortFills = ($value['Order_Qty'] - $value['Recd_Qty']);				
				if($shortFills > 0)
				{												
					$temp 					= array();
					$temp['SKUID'] 			= $value['SKUID'];
					$temp['SKU'] 			= $value['SKU'];
					$temp['PO'] 			= $value['PO'];
					$temp['PO_Cancel'] 		= $value['PO_Cancel'];
					$temp['Create_Date']	= $value['Create_Date'];
					$temp['Order_Qty'] 		= number_format($value['Order_Qty'], 1, '.', '');
					$temp['Recd_Qty'] 		= number_format($value['Recd_Qty'], 1, '.', '');
					$temp['Short_Fills'] 	= number_format($shortFills, 1, '.', '');
					$temp['Fill_Per'] 		= ($value['Order_Qty'] > 0) ? number_format(($value['Recd_Qty']/$value['Order_Qty'])*100, 1, '.', '') : 0;
					$arr[] 					= $temp;	
				}
			}
		} // end if
        $this->jsonOutput['whsFillPerformancePOGrid'] = $arr;
	}

	/**
	 * whsFillComments()
     * This Function is used to retrieve comments and username based on set parameters     
	 * @access private	
     * @return array
     */
    private function whsFillComments() {		
		$queryPart = '';		
		if ($_REQUEST['SKUID'] != "") {
            $queryPart = " AND " . $this->accountID . "='" . $_REQUEST['SKUID'] . "'";
        }
		if ($_REQUEST['PO'] != "" && $_REQUEST['PO'] != 'undefined') {
            $queryPart .= " AND " . $this->settingVars->relayCommenttable . ".po_number='" . $_REQUEST['PO'] . "'";
        }
		
		$privacyFlag = $_REQUEST['privacyFlag'];
		
		$query = "SELECT ".$this->accountID." as SKUID,".$this->settingVars->relayCommenttable.".po_number as PO,comment_detail AS COMMENT, comment_time as COMMENTDATETIME, uid FROM ".$this->settingVars->commentHelperTables.$this->settingVars->commentHelperLink.$queryPart." AND privacy_flag=".$privacyFlag." ORDER BY sequence DESC";
		
		//echo $query;exit;
        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
		
		$arr = array();
		
		if (is_array($result) && !empty($result)) {						
			$uids = array();
			foreach ($result as $key => $value) {
				array_push($uids, $value['uid']);
			}
			
			$username = $this->getUsers($uids);		
			
			foreach ($result as $key => $value) {
				$expdatetime = explode(" ",$value['COMMENTDATETIME']);
				$date = date("jS F Y",strtotime($expdatetime[0]));
				$time = date("h:i A",strtotime($expdatetime[1]));
				$arr[$value['PO']][]			= array('COMMENT'=>$value['COMMENT'],'COMMENTDATE'=>$date,'COMMENTTIME'=>$time,'USERNAME'=>$username[$value['uid']]);								
			}
				
		}
		if ($_REQUEST['PO'] != "" && $_REQUEST['PO'] != 'undefined') {
			$this->jsonOutput['whsFillComments'] = $arr[$_REQUEST['PO']];
		}
		else
		{
			$this->jsonOutput['whsFillComments'] = $arr;
		}
	}

	private function whsNewComment()
	{
		$queryPart = '';		
		if ($_REQUEST['SKUID'] != "") {
            $queryPart = " AND " . $this->accountID . "='" . $_REQUEST['SKUID'] . "'";
        }
		if ($_REQUEST['PO'] != "" && $_REQUEST['PO'] != 'undefined') {
            $queryPart .= " AND " . $this->settingVars->relayCommenttable . ".po_number='" . $_REQUEST['PO'] . "'";
        }
		
		$comment = trim($_REQUEST['newcomment']);
		
		$query = "SELECT MAX(sequence) as sequence FROM ".$this->settingVars->commentHelperTables.$this->settingVars->commentHelperLink.$queryPart;
		//echo $query;exit;
        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
		$lastSequence = $result[0]['sequence'];
		if($comment != ''){
			$newSequence = $lastSequence+1;	
			$currentdatetime = date('Y-m-d h:i:s');
			$query = "INSERT INTO ".$this->settingVars->relayCommenttable." (clientID, SIN, po_number, uid, privacy_flag, mood, comment_bucket, comment_detail, comment_time, sequence) values ('".$this->settingVars->clientID."','".$_REQUEST['SKUID']."','".$_REQUEST['PO']."',".$this->settingVars->uid.",0,'','','".$comment."','".$currentdatetime."',".$newSequence.")";
			$result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$DML);
			$this->jsonOutput['success'] = "Comment has been done successfully.";
		}else{
			$this->jsonOutput['message'] = "Comment failed.";
		}
	}

	/**
	 * getUsers()
     * This Function is used to retrieve usernames based on user id parameters     
	 * @access private	
     * @return array
     */
	private function getUsers($uids) {		
        $row = array();
		
		$ultraUtility    = lib\UltraUtility::getInstance();
		$result = $ultraUtility->getUsers($uids, true);
		
		if (is_array($result) && !empty($result))
		{
			foreach ($result as $data) {
				$row[$data['aid']] = $data['uname'];
			}
		}
        return $row;  
    }

	/* ****
     * COLLECTS TABLE JOINING STRING FROM PROJECT SETTINGS
     * ALSO APPLYS FILTER CONDITIONS TO THE STRING
     * AND RETURNS $tablejoins_and_filters
     * ****/
	public function getAll() {
        $tablejoins_and_filters = parent::getAll();

        if ($_REQUEST['SKUID'] != "") {
        	$tablejoins_and_filters .= " AND " . $this->accountID . "='" . $_REQUEST['SKUID'] . "'";
        }
		
        return $tablejoins_and_filters;
    }

    public function buildPageArray() {

		$accountFieldPart = explode("#", $this->accountField);

		$fetchConfig = false;
		if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
		    $fetchConfig = true;
		    $this->jsonOutput['gridColumns']['SKUID'] = (count($accountFieldPart) > 1) ? $this->displayCsvNameArray[$accountFieldPart[1]] : $this->displayCsvNameArray[$accountFieldPart[0]];
		    
		    if(count($accountFieldPart) > 1)
		    	$this->jsonOutput['gridColumns']['SKU'] = $this->displayCsvNameArray[$accountFieldPart[0]];
		}

        $accountField = strtoupper($this->dbColumnsArray[$accountFieldPart[0]]);
        $accountField = (count($accountFieldPart) > 1) ? strtoupper($accountField."_".$this->dbColumnsArray[$accountFieldPart[1]]) : $accountField;

        $this->accountID = (isset($this->settingVars->dataArray[$accountField]) && isset($this->settingVars->dataArray[$accountField]['ID'])) ? $this->settingVars->dataArray[$accountField]['ID'] : $this->settingVars->dataArray[$accountField]['NAME'];
        $this->accountName = $this->settingVars->dataArray[$accountField]['NAME'];

        return;
    }

	public function buildDataArray($fields) {
        if (empty($fields))
            return false;

        $configurationCheck = new config\ConfigurationCheck($this->settingVars, $this->queryVars);
        $configurationCheck->buildDataArray($fields);
        $this->dbColumnsArray = $configurationCheck->dbColumnsArray;
		$this->displayCsvNameArray = $configurationCheck->displayCsvNameArray;
        return;
    }
}
?>