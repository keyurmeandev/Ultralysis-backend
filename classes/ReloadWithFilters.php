<?php

namespace classes;

use filters;
use db;
use config;

class ReloadWithFilters extends SummaryPage {

    /** ***
     * Default gateway function, should be similar in all DATA CLASS
     * arguments:
     *  $settingVars [project settingsGateway variables]
     * @return $jsonOutput with all data that should be sent to client app
     * *** */

    public function go($settingVars) {
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES

        $this->pageName = $_REQUEST["pageName"]; // Identify the page name
        $this->settingVars->pageName = $this->pageName; // set pageName in settingVars
        
        //IF NEEDED COLLECT & PROVIDE DATA HELPERS
        /* if ($_REQUEST['DataHelper'] == "true") {
            $this->fetch_all_product_and_marketSelection_data(); //collecting product and market filter data 
			$this->fetchProductAndMarketTabsSettings();			
        } */
	
		$action = $_REQUEST["action"];
		switch ($action) {
			case "saveFilter":
				$this->saveFilter();
				$this->fetchSavedFilterList();
			case "loadFilter":
				$this->fetchFilter();
				$this->fetchSavedFilterList();
			case "editFilter":
				$this->editFilter();
				$this->fetchSavedFilterList();
				break;
			case "deleteFilter":
				$this->deleteFilter();
				$this->fetchSavedFilterList();
				break;
			case "applyFilter":
				$this->applyFilter();
				break;
			default: 
				$this->fetchSavedFilterList();
                break;
		}
	
        return $this->jsonOutput;
    }
	
    private function fetchFilter()
    {
        $filterId = $_REQUEST['filterId'];
		$params = "";
        if ($filterId != '') 
		{
            $filterDetailQuery = "SELECT * FROM ".$this->settingVars->filterSelection.' WHERE '.$this->settingVars->filterSelection.'.filter_master_id = '.$filterId;
            $filterDetail = $this->queryVars->queryHandler->runQuery($filterDetailQuery, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
			
            if (is_array($filterDetail) && !empty($filterDetail)) 
			{
                foreach($filterDetail as $data)
				{
					$params .= "&FS[".$data['filter_code']."]=".$data['user_selection'];
				}
            }
        }
		$this->jsonOutput['loadFilter'] = array('params' => $params, "selectedFilter" => $filterId);
    }	
	
	public function saveFilter()
	{
        $savedFilterId = $_REQUEST['saved_filter_id'];
        $savedFilterName = (isset($_REQUEST['saved_filter_name']) && !empty($_REQUEST['saved_filter_name'])) ? $_REQUEST['saved_filter_name'] : 'My Report';

        if (is_numeric($savedFilterId) && !empty($savedFilterId)) {
            $checkConfigExistQuery = "SELECT * FROM ".$this->settingVars->filterMaster.' WHERE '.$this->settingVars->filterMaster.'.id = '.$savedFilterId;
            $result = $this->queryVars->queryHandler->runQuery($checkConfigExistQuery, $this->queryVars->linkid, db\ResultTypes::$NUM_OF_ROWS);

            if ($result > 0)
                $isNew = false;
            else {
                $reportConfig = $this->insertReportConfig($savedFilterName);
                $savedFilterId = $reportConfig['id'];
                $isNew = $reportConfig['isNew'];
            }
        }
        else {
            $reportConfig = $this->insertReportConfig($savedFilterName);
            if ($reportConfig['isNew']) {
                $savedFilterId = $reportConfig['id'];
                $isNew = $reportConfig['isNew'];
            }
            else {
                $this->jsonOutput['saveFilter'] = array('status' => 'fail', 'message' => 'Filter with same name exists. Please try other name.');
                return;
            }
        }
		
		$this->insertUpdateProductFilter($savedFilterId, $isNew);
	}

    private function insertReportConfig($reportName)
    {
        $checkRecordExist = "SELECT * FROM ".$this->settingVars->filterMaster.' WHERE '.$this->settingVars->filterMaster.'.name = "'.$reportName.'"'.
            ' AND '.$this->settingVars->filterMaster.'.cid = '.$this->settingVars->aid;
        $checkRecordExistData = $this->queryVars->queryHandler->runQuery($checkRecordExist, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);

        if (is_array($checkRecordExistData) && !empty($checkRecordExistData))
            return array('id' => $checkRecordExistData[0]['id'], 'isNew' => false);
        else {
            $insertConfigQuery = 'INSERT INTO '.$this->settingVars->filterMaster.' values (0, '.$this->settingVars->aid.', "'.$reportName.'", "'.date('Y-m-d H:i:s').'", "'.date('Y-m-d H:i:s').'")';
            $result = $this->queryVars->queryHandler->runQuery($insertConfigQuery, $this->queryVars->linkid, db\ResultTypes::$DML);

            $insertedRecordData = "SELECT * FROM ".$this->settingVars->filterMaster.' WHERE '.$this->settingVars->filterMaster.'.name = "'.$reportName.'"'.
                ' AND '.$this->settingVars->filterMaster.'.cid = '.$this->settingVars->aid;
            $insertedRecord = $this->queryVars->queryHandler->runQuery($insertedRecordData, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);

            if (is_array($insertedRecord) && !empty($insertedRecord))
                return array('id' => $insertedRecord[0]['id'], 'isNew' => true);
        }

        return false;
    }	
	
	private function insertUpdateProductFilter($configId, $isNew)
	{
        $postFilters = $_REQUEST['FS'];
		
        if (is_array($postFilters) && !empty($postFilters)) 
		{
            foreach ($postFilters as $key => $productSubFilter) 
			{
                if ($productSubFilter != '') 
				{
                    $productSelFilterData[] = array(
                        'filter_master_id' => $configId,
                        'filter_type' => $this->settingVars->dataArray[$key]['NAME'],
                        'filter_code' => $key,
						'filter_on' => $this->settingVars->dataArray[$key]['TYPE'],
                        'user_selection' => mysqli_real_escape_string($this->queryVars->linkid,$productSubFilter)
                    );
                }
            }
        }		
		
        if (!$isNew) {
            $where = ' WHERE '.$this->settingVars->filterSelection.'.filter_master_id = '.$configId . " ";
            $this->deleteFilterProperties($this->settingVars->filterSelection, $where);
        }
        
        if (is_array($productSelFilterData) && !empty($productSelFilterData)) {
            $fields = array_keys($productSelFilterData[0]);

            $this->insertFilter($this->settingVars->filterSelection, $fields, $productSelFilterData, true);
			$this->jsonOutput['saveFilter'] = array('status' => 'success', 'message' => 'Filter has been saved successfully.');
        }
	}
	
    private function deleteFilterProperties($table, $condition)
    {
        if (empty($table) || empty($condition))
            return false;

        $query = 'DELETE FROM '.$table.' '.$condition;
        return $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$DML);
    }	
	
    private function insertFilter($table, $fields, $values, $isMultiple = false)
    {
        if (empty($table) || !is_array($fields) || empty($fields) || !is_array($values) || empty($values))
            return false;

        if (!$isMultiple)
            $insertQuery = 'INSERT INTO '.$table.' ('.implode(',', $fields).') Values ("'.implode('","', $values).'")';
        else {
            $insertQuery = 'INSERT INTO '.$table.' ('.implode(',', $fields).') Values';
            foreach ($values as $key => $value) {
                $separator = ($key < count($values)-1) ? ',' : '';
                $insertQuery .= ' ("'.implode('","', $value).'")'.$separator;
            }
        }
        return $this->queryVars->queryHandler->runQuery($insertQuery, $this->queryVars->linkid, db\ResultTypes::$DML);
    }
	
    private function fetchSavedFilterList()
    {
        $filterList = "SELECT * FROM ".$this->settingVars->filterMaster.' WHERE '.$this->settingVars->filterMaster.'.cid = '.$this->settingVars->aid;
        $result = $this->queryVars->queryHandler->runQuery($filterList, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
        $filters = $filterList = array();

        if (is_array($result) && !empty($result)) {
            foreach ($result as $filterData) {
                $filterList[$filterData['id']] = $filterData['name'];
                $filters[] = array(
                    'filterId' => $filterData['id'],
                    'label' => $filterData['name']
                );
            }
        }

        $this->jsonOutput['filter_list'] = $filterList;
        $this->jsonOutput['filters'] = $filters;
    }
	
    private function editFilter() 
	{
        $filterId = trim($_REQUEST['edit_filter_id']);
        $filterName = trim($_REQUEST['edit_filter_name']);
		
		if(isset($_REQUEST['edit_filter_id']))
		{
			if(!$this->checkFilterNameExist($filterName, $filterId)) 
			{
				$query  = "UPDATE ".$this->settingVars->filterMaster." SET name = '".$filterName."' WHERE id = ".$filterId;
				$result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$DML);
				if ($result) {
					$this->jsonOutput['editFilter'] = array('status' => 'success');
					$this->fetchSavedFilterList();
				}
				else 
					$this->jsonOutput['editFilter'] = array('status' => 'fail', 'errMsg' => 'Database error.');
			}
			else 
				$this->jsonOutput['editFilter'] = array('status' => 'fail', 'errMsg' => 'Filter with same name exists. Please try other name.');
		}	
    }

    private function checkFilterNameExist($filterName, $escapeId = '') {

        $query = "SELECT * FROM ".$this->settingVars->filterMaster." WHERE name='".$filterName."'";
        
        if ($escapeId != '')
            $query .= " AND id != ".$escapeId;

        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$NUM_OF_ROWS);
        
        if($result > 0)
            return true;
        else 
            return false;
    }	

    private function deleteFilter() {

        try {
            $id = $_GET["delete_filter_id"];
            $query  = "DELETE FROM ".$this->settingVars->filterMaster." WHERE id=".$id;   
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$DML);

            $query  = "DELETE FROM ".$this->settingVars->filterSelection." WHERE filter_master_id=".$id;   
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$DML);

            $this->jsonOutput['delete_status'] = array('status' => 'success');
            $this->fetchSavedFilterList();
        } catch (Exception $e) {
            $this->jsonOutput['delete_status'] = array('status' => 'fail', 'errMsg' => $e->getMessage());
        }
    }	
	
	private function applyFilter()
	{
		$filterId = $_REQUEST['filterId'];
		$_REQUEST['FS'] = array();
        if ($filterId != '') 
		{
            $filterDetailQuery = "SELECT * FROM ".$this->settingVars->filterSelection.' WHERE '.$this->settingVars->filterSelection.'.filter_master_id = '.$filterId;
            $filterDetail = $this->queryVars->queryHandler->runQuery($filterDetailQuery, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
			
            // print_r($filterDetail);exit();
            if (is_array($filterDetail) && !empty($filterDetail)) 
			{
                foreach($filterDetail as $data)
				{
					$_REQUEST["FS"][$data['filter_code']] = $data['user_selection'];
				}
				if($_REQUEST["FS"])
				{
					$_REQUEST['DataHelper'] = true;
					// $_REQUEST['pageName'] = "EXE_SUMMARY_PAGE";
					$_REQUEST['commonFilterPage'] = true;
					$_REQUEST["commonFilterApplied"] = true;
					
					//$this->fetch_all_product_and_marketSelection_data(); //collecting product and market filter data 
					//$this->fetchProductAndMarketTabsSettings();
                    $configureProject = new config\ConfigureProject($this->settingVars, $this->queryVars);
                    $configureProject->fetch_all_product_and_marketSelection_data();
                    $configureProject->fetchProductAndMarketTabsSettings();
            
                    $this->jsonOutput['marketSelectionTabs'] = $configureProject->jsonOutput['marketSelectionTabs'];
                    $this->jsonOutput['productSelectionTabs'] = $configureProject->jsonOutput['productSelectionTabs'];
				}
            }
        }
	}
	
}
?>