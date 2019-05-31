<?php
namespace classes\ddb;

ini_set('max_execution_time', 0);

use projectsettings;
use SimpleXMLElement;
use ZipArchive;
use datahelper;
use filters;
use db;
use config;
use lib;

class DynamicDataBuilder extends ProjectLoader {

    private $allowedToSendMessage;
    public $isTYActive = true;
    public $isLYActive = false;
    public $isLYAsRowActive = false;
    public $timeFilterExportOption = '';
    public function __construct() {
        $this->allowedToSendMessage = false;
    }

    public function go($settingVars) {
        $settingVars->pageName = 'DDBDynamicDataBuilder';
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES
        $this->queryPart = $this->getAll();

        $this->getAllUserList();

        /*echo "<pre>";
        print_r($this->queryVars->projectConfiguration);exit();*/

        if($_REQUEST['weekOption'] == 1 && isset($_REQUEST['timeFilterExportOption']) && !empty($_REQUEST['timeFilterExportOption'])){
            $this->timeFilterExportOption = $_REQUEST['timeFilterExportOption'];
            switch ($this->timeFilterExportOption) {
                case 'showThisYearOnly' : 
                    $this->isTYActive = true;
                    $this->isLYActive = false;
                    break;
                case 'showThisYearAndLastYear' : 
                    $this->isTYActive = true;
                    $this->isLYActive = true;
                    break;
                case 'showThisYearAndLastYearRow' : 
                    $this->isTYActive = true;
                    $this->isLYActive = false;
                    $this->isLYAsRowActive = true;
                    break;
                case 'showLastYearOnly' : 
                    $this->isTYActive = false;
                    $this->isLYActive = true;
                    break;
                default: 
                    $this->isTYActive = true;
                    $this->isLYActive = false;
                    break;
            }
        }

        $_REQUEST = $this->escapeRequestedString($_REQUEST);
        
        $action = $_REQUEST["action"];
        switch ($action) {
            case 'saveFilters' : 
                $this->saveFilters();
                return $this->jsonOutput;
                break;
            case 'editFilter' : 
                $this->editFilter();
                return $this->jsonOutput;
                break;
            case 'deleteFilter' : 
                $this->deleteFilter();
                return $this->jsonOutput;
                break;
            case 'queryStatus' : return $this->queryStatus();
                break;
            case 'delete' : $this->deleteFiles();
                break;
			case 'fetchSavedFilter':
                $this->fetchSavedFilterList();
                return $this->jsonOutput;
                break;
            default: $this->download();
                break;
        }
    }

    public function makeEscapeRequestedString($value)
    {
        if(is_array($value) && !empty($value))
            $mres = $this->escapeRequestedString($value);
        else
            $mres = mysqli_real_escape_string($this->queryVars->ddbLinkid, $value);
            
        return $mres;
    }
    
    public function escapeRequestedString($param)
    {
        $param = array_map(array($this, 'makeEscapeRequestedString'), $param);
        
        return $param;
    }
    
    public function getAllUserList(){
        /*[START] Get all users and projects details */
            $ultraUtility = \lib\UltraUtility::getInstance();
            $allUsers = $ultraUtility->getUsersByClientAndProject($this->settingVars->aid,$this->settingVars->projectID);
            if(!empty($allUsers)){
                $this->userList = array_column($allUsers,'uname','aid');
            }
        /*[END] Get all users and projects details */
    }

    public function fetchSavedFilterList(){

        $filterList = "SELECT * FROM ".$this->settingVars->ddbconfigTable.' WHERE '.$this->settingVars->ddbconfigTable.'.cid = '.$this->settingVars->aid.' AND projectID = '.$this->settingVars->projectID." AND ((".$this->settingVars->ddbconfigTable.".userID = ".$this->queryVars->uid." AND ".$this->settingVars->ddbconfigTable.".ddbType=1) OR (".$this->settingVars->ddbconfigTable.".ddbType=0))";

        $redisCache = new \utils\RedisCache($this->queryVars);
        $redisOutput = $redisCache->checkAndReadByQueryHashFromCache($filterList);
        if ($redisOutput === false || isset($_REQUEST['randomId'])) {
            $result = $this->queryVars->queryHandler->runQuery($filterList, $this->queryVars->ddbLinkid, db\ResultTypes::$TYPE_OBJECT);
            $redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }

        $filters = $filterList = array();
        if (is_array($result) && !empty($result)) {
            foreach ($result as $filterData) {
                if($filterData['userID'] == $this->queryVars->uid)
                    $filterList[$filterData['id']] = $filterData['name'];
                
                $filters[] = array(
                    'filterId'   => $filterData['id'],
                    'label'      => $filterData['name'],
                    'ddbType'    => $filterData['ddbType'],
                    'isEditable' => ($filterData['userID'] == $this->queryVars->uid) ? 0 : 1,
                    'type'       => $filterData['ddbType'] == 1 ? 'Private' : 'Public',
                    'name'       => (!empty($filterData['userID']) && isset($this->userList[$filterData['userID']])) ? $this->userList[$filterData['userID']] : ''
                );
            }
        }

        $this->jsonOutput['filter_list']  = $filterList;
        $this->jsonOutput['savedFilters'] = $filters;
    }

    private function editFilter() {
        $filterId 	= $_REQUEST['edit_filter_id'];
        $filterName = rawurldecode($_REQUEST['edit_filter_name']);

        //$filterType = isset($_REQUEST['edit_filter_type']) ? $_REQUEST['edit_filter_type'] : 0;
   
        if(!$this->checkFilterNameExist($filterName, $filterId)) {
            $query  = "UPDATE ".$this->settingVars->ddbconfigTable." SET name = '".$filterName."' WHERE id = ".$filterId;
            //$query  = "UPDATE ".$this->settingVars->ddbconfigTable." SET name = '".$filterName."',ddbType = ".$filterType." WHERE id = ".$filterId;
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->ddbLinkid, db\ResultTypes::$DML);
            if ($result) {
                $this->jsonOutput['edit_status'] = array('status' => 'success');
                $this->fetchSavedFilterList();
            }
            else 
                $this->jsonOutput['edit_status'] = array('status' => 'fail', 'errMsg' => 'Database error.');
        }
        else 
            $this->jsonOutput['edit_status'] = array('status' => 'fail', 'errMsg' => 'Filter with same name exists. Please try other name.');
    }

    private function checkFilterNameExist($filterName, $escapeId = '') {

        $query = "SELECT * FROM ".$this->settingVars->ddbconfigTable." WHERE name='".$filterName."'";
        if ($escapeId != '')
            $query .= " AND id != ".$escapeId;
        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->ddbLinkid, db\ResultTypes::$NUM_OF_ROWS);
        if($result > 0)
            return true;
        else 
            return false;
    }

    private function deleteFilter() {
        try {
            $id = $_REQUEST["delete_filter_id"];
            $query  = "DELETE FROM ".$this->settingVars->ddbconfigTable." WHERE id=".$id;   
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->ddbLinkid, db\ResultTypes::$DML);

            $where = ' WHERE '.$this->settingVars->timeSelectionTable.'.config_id = '.$id;
            $this->deleteFilterProperties($this->settingVars->timeSelectionTable, $where);

            $where = ' WHERE '.$this->settingVars->filterSettingTable.'.config_id = '.$id;
            $this->deleteFilterProperties($this->settingVars->filterSettingTable, $where);

            $where = ' WHERE '.$this->settingVars->measuresSelectionTable.'.config_id = '.$id;
            $this->deleteFilterProperties($this->settingVars->measuresSelectionTable, $where);

            $where = ' WHERE '.$this->settingVars->outputColumnsTable.'.config_id = '.$id;
            $this->deleteFilterProperties($this->settingVars->outputColumnsTable, $where);

            $this->jsonOutput['delete_status'] = array('status' => 'success');
            $this->fetchSavedFilterList();
        } catch (Exception $e) {
            $this->jsonOutput['delete_status'] = array('status' => 'fail', 'errMsg' => $e->getMessage());
        }
    }

    private function saveFilters() {
		$savedFilterId = $_REQUEST['saved_filter_id'];
        $savedFilterName = (isset($_REQUEST['saved_filter_name']) && !empty($_REQUEST['saved_filter_name'])) ? $_REQUEST['saved_filter_name'] : 'My Report';
        $savedFilterType = (isset($_REQUEST['saved_filter_type']) && !empty($_REQUEST['saved_filter_type'])) ? $_REQUEST['saved_filter_type'] : 0;

        $savedFilterName = rawurldecode($savedFilterName);
        
        if (is_numeric($savedFilterId) && !empty($savedFilterId)) {
            $checkConfigExistQuery = "SELECT * FROM ".$this->settingVars->ddbconfigTable.' WHERE '.$this->settingVars->ddbconfigTable.'.id = '.$savedFilterId .' AND projectID='.$this->settingVars->projectID;
            $result = $this->queryVars->queryHandler->runQuery($checkConfigExistQuery, $this->queryVars->ddbLinkid, db\ResultTypes::$NUM_OF_ROWS);

            if ($result > 0){
				$isNew = false;
				/*Update the filter type and userid*/
				$query  = "UPDATE ".$this->settingVars->ddbconfigTable." SET ddbType = ".$savedFilterType.", userID=".$this->queryVars->uid." WHERE id = ".$savedFilterId;
				$rsupd = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->ddbLinkid, db\ResultTypes::$DML);
			}else {
                $reportConfig = $this->insertReportConfig($savedFilterName,$savedFilterType);
                $savedFilterId = $reportConfig['id'];
                $isNew = $reportConfig['isNew'];
            }
        }
        else {
            $reportConfig = $this->insertReportConfig($savedFilterName,$savedFilterType);
            if ($reportConfig['isNew']) {
                $savedFilterId = $reportConfig['id'];
                $isNew = $reportConfig['isNew'];
            }
            else {
                $this->jsonOutput['save_error'] = array('status' => 'fail', 'errMsg' => 'Filter with same name exists. Please try other name.');
                return;
            }
        }

		$this->insertUpdateDynamicFilter($savedFilterId, $isNew);
        $this->insertUpdateTimeFilter($savedFilterId, $isNew);
        $this->insertUpdateMeasuresFilter($savedFilterId, $isNew);
        $this->insertUpdateOutputColumns($savedFilterId, $isNew);

        $this->jsonOutput['filter_save'] = array('status' => 'success');
        $this->fetchSavedFilterList();
    }
    
	private function insertUpdateDynamicFilter($configId, $isNew){
		$postFilters = $_REQUEST['FS'];
       
		foreach($postFilters as $key => $filter)
		{
			if($filter != "")
			{
				$productSelFilterData[] = array(
					'config_id' => $configId,
					'filter_type' => $this->settingVars->dataArray[$key]['TYPE'],
					'filter_code' => $key,
					'user_selection' => rawurldecode($filter)
				);
			}
		}
		
        if (!$isNew) {
            $where = ' WHERE '.$this->settingVars->filterSettingTable.'.config_id = '.$configId;
            $this->deleteFilterProperties($this->settingVars->filterSettingTable, $where);
        }
        
        if (is_array($productSelFilterData) && !empty($productSelFilterData)) {
            $fields = array_keys($productSelFilterData[0]);
            $this->insertFilter($this->settingVars->filterSettingTable, $fields, $productSelFilterData, true);
        }
	}
	
    private function insertReportConfig($reportName,$savedFilterType = 0){
        $checkRecordExist = "SELECT * FROM ".$this->settingVars->ddbconfigTable.' WHERE '.$this->settingVars->ddbconfigTable.'.name = "'.$reportName.'"'.
            ' AND '.$this->settingVars->ddbconfigTable.'.cid = '.$this->settingVars->aid.' AND projectID='.$this->settingVars->projectID;
        $checkRecordExistData = $this->queryVars->queryHandler->runQuery($checkRecordExist, $this->queryVars->ddbLinkid, db\ResultTypes::$TYPE_OBJECT);

        if (is_array($checkRecordExistData) && !empty($checkRecordExistData))
            return array('id' => $checkRecordExistData[0]['id'], 'isNew' => false);
        else {

            $query = "SELECT MAX(id) as maxID FROM ".$this->settingVars->ddbconfigTable;
            $maxIDResult = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->ddbLinkid, db\ResultTypes::$TYPE_OBJECT);
        
            if(is_array($maxIDResult) && !empty($maxIDResult) && isset($maxIDResult[0]['maxID']) && $maxIDResult[0]['maxID'] != "")
                $maxID = $maxIDResult[0]['maxID'] + 1;
            else
                $maxID = 1;        
        
            $insertConfigQuery = 'INSERT INTO '.$this->settingVars->ddbconfigTable.' values ('.$maxID.', '.$this->settingVars->aid.', '.$this->settingVars->projectID.', "'.$reportName.'", "'.date('Y-m-d H:i:s').'", "'.date('Y-m-d H:i:s').'",'.$this->queryVars->uid.','.$savedFilterType.')';
            $result = $this->queryVars->queryHandler->runQuery($insertConfigQuery, $this->queryVars->ddbLinkid, db\ResultTypes::$DML);

            $insertedRecordData = "SELECT id FROM ".$this->settingVars->ddbconfigTable.' WHERE '.$this->settingVars->ddbconfigTable.'.name = "'.$reportName.'"'.
                ' AND '.$this->settingVars->ddbconfigTable.'.cid = '.$this->settingVars->aid.' AND projectID='.$this->settingVars->projectID;
            $insertedRecord = $this->queryVars->queryHandler->runQuery($insertedRecordData, $this->queryVars->ddbLinkid, db\ResultTypes::$TYPE_OBJECT);

            if (is_array($insertedRecord) && !empty($insertedRecord))
                return array('id' => $insertedRecord[0]['id'], 'isNew' => true);
        }

        return false;
    }

    private function insertUpdateTimeFilter($configId, $isNew){
        $timeSelFilterData = array(
            'config_id' => $configId,
            'from_week_year' => $_REQUEST['FromWeek'],
            'to_week_year' => $_REQUEST['ToWeek'],
            'week_separated_report' => ($_REQUEST['week_separated_report'] == 'true') ? 1 : 0,
            'week_range' => $_REQUEST['week_range']
        );

        if(isset($_REQUEST['weekOption']) && $_REQUEST['weekOption']==1 && isset($_REQUEST['timeFilterExportOption']) && !empty($_REQUEST['timeFilterExportOption'])) {
            $timeSelFilterData['ty_ly_option'] = $_REQUEST['timeFilterExportOption'];
        }

        if ($isNew) {
            $fields = array_keys($timeSelFilterData);
            $values = array_values($timeSelFilterData);

            $this->insertFilter($this->settingVars->timeSelectionTable, $fields, $values);
        }
        else {
            $fields = array_keys($timeSelFilterData);
            $where = ' WHERE '.$this->settingVars->timeSelectionTable.'.config_id = '.$configId;

            $this->updateFilter($this->settingVars->timeSelectionTable, $fields, $timeSelFilterData, $where);
        }
    }

    private function insertUpdateMeasuresFilter($configId, $isNew){
        $measuresFilterData = array(
            'config_id' => $configId,
            'selected_measures_code' => $_REQUEST['measures']
        );

        if ($isNew) {
            $fields = array_keys($measuresFilterData);
            $values = array_values($measuresFilterData);

            $this->insertFilter($this->settingVars->measuresSelectionTable, $fields, $values);
        }
        else {
            $fields = array_keys($measuresFilterData);
            $where = ' WHERE '.$this->settingVars->measuresSelectionTable.'.config_id = '.$configId;

            $this->updateFilter($this->settingVars->measuresSelectionTable, $fields, $measuresFilterData, $where);
        }
    }

    private function insertUpdateOutputColumns($configId, $isNew){
        $outputColumnData = array(
            'config_id' => $configId,
            'selected_column_code' => rawurldecode($_REQUEST['items'])
        );

        if ($isNew) {
            $fields = array_keys($outputColumnData);
            $values = array_values($outputColumnData);

            $this->insertFilter($this->settingVars->outputColumnsTable, $fields, $values);
        }
        else {
            $fields = array_keys($outputColumnData);
            $where = ' WHERE '.$this->settingVars->outputColumnsTable.'.config_id = '.$configId;

            $this->updateFilter($this->settingVars->outputColumnsTable, $fields, $outputColumnData, $where);
        }
    }

    private function insertFilter($table, $fields, $values, $isMultiple = false){
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

        return $this->queryVars->queryHandler->runQuery($insertQuery, $this->queryVars->ddbLinkid, db\ResultTypes::$DML);
    }

    private function updateFilter($table, $fields, $values, $condition, $leftjoin = ''){
        if (empty($table) || !is_array($fields) || empty($fields) || !is_array($values) || empty($values) || empty($condition))
            return false;

        $query="update ".$table.$leftjoin." set ";
      
        for ($i=0; $i < count($fields); $i++) {
            if($i==0)
                $query = $query." ".$table.".".$fields[$i]."='".$values[$fields[$i]]."'";
            else
                $query = $query.",".$table.".".$fields[$i]."='".$values[$fields[$i]]."'";
        }
      
        $query= $query.$condition;
        return $this->queryVars->queryHandler->runQuery($query, $this->queryVars->ddbLinkid, db\ResultTypes::$DML);
    }

    private function deleteFilterProperties($table, $condition){
        if (empty($table) || empty($condition))
            return false;

        $query = 'DELETE FROM '.$table.' '.$condition;
        return $this->queryVars->queryHandler->runQuery($query, $this->queryVars->ddbLinkid, db\ResultTypes::$DML);
    }

    private function queryStatus() {
        $this->queryPart = $this->getAll();
        
        if($this->settingVars->timeSelectionUnit == "days")
            filters\timeFilter::getDaysSlice($this->settingVars);
        elseif($this->settingVars->timeSelectionUnit == "date")
            filters\timeFilter::getDateSlice($this->settingVars);
        else
            filters\timeFilter::getSlice($this->settingVars);
        
        return $this->gridData();
    }

    private function download() {
        $csvFile = $_REQUEST['csvFile'];
        $zipFile = $_REQUEST['zipFile'];

        $this->downloadFile();
    }

    private function getSqlPart() {
        $sqlPart = "";
        $groupByPart = "";
        
        $items = explode("-", rawurldecode($_REQUEST['items']));
        if($_REQUEST['weekOption'] == 2)
            $items[] = $this->settingVars->dataArray['YEARWEEK']['NAME_ALIASE'];

        for ($i = 0; $i < count($items); $i++) {
            if (isset($this->settingVars->dataArray[$items[$i]])) {
                $id = key_exists('ID', $this->settingVars->dataArray[$items[$i]]) ? $this->settingVars->dataArray[$items[$i]]['ID'] : "";
                $name = $this->settingVars->dataArray[$items[$i]]['NAME'];
                if ($id != '') {
                    $sqlPart .= $id . " AS '" . $this->settingVars->dataArray[$items[$i]]['ID_ALIASE'] . "',";
                    $groupByPart .= "'" . $this->settingVars->dataArray[$items[$i]]['ID_ALIASE'] . "',";
                }

                $sqlPart .= $name . " AS '" . $this->settingVars->dataArray[$items[$i]]['NAME_ALIASE'] . "',";
                $groupByPart .= "'" . $this->settingVars->dataArray[$items[$i]]['NAME_ALIASE'] . "',";
            }
        }

        $sqlPart = substr($sqlPart, 0, strlen($sqlPart) - 1);
        $groupByPart = substr($groupByPart, 0, strlen($groupByPart) - 1);

        return array(
            'selectPart' => $sqlPart
            , 'groupByPart' => $groupByPart
        );
    }

    private function getCsvPart($data) {
        $csvPart = "";
        $totalItems = explode("-", rawurldecode($_REQUEST['items']));
        if($_REQUEST['weekOption'] == 2)
            $totalItems[] = $this->settingVars->dataArray['YEARWEEK']['NAME_ALIASE'];
        $totalMeasures = explode("-", $_REQUEST['measures']);

        //ADDING ITEMS TO CSV	
        for ($i = 0; $i < count($totalItems); $i++) {
            if (isset($this->settingVars->dataArray[$totalItems[$i]])) {
                $id = key_exists('ID', $this->settingVars->dataArray[$totalItems[$i]]) ? $this->settingVars->dataArray[$totalItems[$i]]['ID'] : "";
                $name = $this->settingVars->dataArray[$totalItems[$i]]['NAME'];

                if ($id != '') {
                    //CHECKING IF DATA FROM QUERY HAS ANY COMMA WITHIN IT,IF YES, ADD QUOATED DATA, ELSE LEAVE IT AS IT IS
                    $domain = strstr($data[$this->settingVars->dataArray[$totalItems[$i]]['ID_ALIASE']], ',');
                    if (strlen($domain) == 0)
                        $csvPart.= $data[$this->settingVars->dataArray[$totalItems[$i]]['ID_ALIASE']] . ","; //LEAVING AS IT IS
                    else
                        $csvPart.= "\"" . $data[$this->settingVars->dataArray[$totalItems[$i]]['ID_ALIASE']] . "\","; //ADDING QUOTED DATA
                }

                //CHECKING IF DATA FROM QUERY HAS ANY COMMA WITHIN IT,IF YES, ADD QUOATED DATA, ELSE LEAVE IT AS IT IS
                $domain = strstr($data[$this->settingVars->dataArray[$totalItems[$i]]['NAME_ALIASE']], ',');
                if (strlen($domain) == 0)
                    $csvPart.= $data[$this->settingVars->dataArray[$totalItems[$i]]['NAME_ALIASE']] . ","; //LEAVING AS IT IS
                else
                    $csvPart.= "\"" . $data[$this->settingVars->dataArray[$totalItems[$i]]['NAME_ALIASE']] . "\","; //ADDING QUOTED DATA
            }
        }

        if($this->timeFilterExportOption == 'showThisYearAndLastYearRow'){
            $csvPartLY = $csvPart;
            $csvPart .= "This Year,";
            $csvPartLY .= "Last Year,";
        }

        //ADDING MEASURES TO CSV
        for ($i = 0; $i < count($totalMeasures); $i++) {
            if (key_exists('M'.$totalMeasures[$i], $this->settingVars->measureArray)) {
                if($this->isTYActive == true){
                    if(isset($data['TY'.$this->settingVars->measureArray['M'.$totalMeasures[$i]]['ALIASE']])) {
                        $csvPart .= $data['TY'.$this->settingVars->measureArray['M'.$totalMeasures[$i]]['ALIASE']] . ",";
                    }else{
                        $csvPart .= $data[$this->settingVars->measureArray['M'.$totalMeasures[$i]]['ALIASE']] . ",";
                    }
                }

                if($this->isLYActive == true && isset($data['LY'.$this->settingVars->measureArray['M'.$totalMeasures[$i]]['ALIASE']])) {
                    $csvPart .= $data['LY'.$this->settingVars->measureArray['M'.$totalMeasures[$i]]['ALIASE']] . ",";
                }

                if($this->isLYAsRowActive == true && isset($data['LY'.$this->settingVars->measureArray['M'.$totalMeasures[$i]]['ALIASE']])) {
                    $csvPartLY .= $data['LY'.$this->settingVars->measureArray['M'.$totalMeasures[$i]]['ALIASE']] . ",";
                }
            }
            /*if (in_array($totalMeasures[$i], array("AVEspacePRICE"))) {
                $avePrice = 0.00;
                if ($data['VOLUME'] > 0)
                    $avePrice = ($data['VALUE'] / $data['VOLUME']);
                $csvPart .= number_format($avePrice, 2, ".", ",") . ",";
            }*/
        }

        $csvPart = str_replace("\r", "", substr($csvPart, 0, strlen($csvPart) - 1));
        $csvPart = str_replace("\n", "", $csvPart);
        $csvPart .= "\r\n";

        if($this->timeFilterExportOption == 'showThisYearAndLastYearRow'){
            $csvPartLY = str_replace("\r", "", substr($csvPartLY, 0, strlen($csvPartLY) - 1));
            $csvPartLY = str_replace("\n", "", $csvPartLY);
            $csvPart .= $csvPartLY."\r\n";
        }

        return $csvPart;
    }

    public function buildDataArray($fields, $isCsvColumn, $appendTableNameWithDbColumn = false ) {
        if (empty($fields))
            return false;

        $configurationCheck = new config\ConfigurationCheck($this->settingVars, $this->queryVars);
        $configurationCheck->buildDataArray($fields, $isCsvColumn, $appendTableNameWithDbColumn);
        $this->dbColumnsArray = $configurationCheck->dbColumnsArray;
        $this->displayCsvNameArray = $configurationCheck->displayCsvNameArray;
        return;
    }

    private function gridData() {
        /** MESSAGING RESPONSE */
        if ($this->allowedToSendMessage)
            $this->send_message("Query Preparing", "10");

        datahelper\Common_Data_Fetching_Functions::$settingVars = $this->settingVars;
        datahelper\Common_Data_Fetching_Functions::$queryVars = $this->queryVars;

        /*$measureArr = datahelper\Common_Data_Fetching_Functions::getMeasuresForDDB();
        $measureSelectionPart = implode(",", (array) $measureArr['selectPart']);
        $havingPart = "";
        if (count((array) $measureArr['havingPart']) > 0)
            $havingPart = " HAVING (" . implode(" OR ", (array) $measureArr['havingPart']) . ")";
        */

        $filterSql = '';

        if(($this->isTYActive == true && $this->isLYActive == true) || ($this->isTYActive == true && $this->isLYAsRowActive == true)) {
            $filterSql = "(".filters\timeFilter::$tyWeekRange." OR ".filters\timeFilter::$lyWeekRange.")";
        } else if($this->isTYActive == true && $this->isLYActive == false) {
            filters\timeFilter::$lyWeekRange = NULL;
            $filterSql = "(".filters\timeFilter::$tyWeekRange.")";
        }else if($this->isTYActive == false && $this->isLYActive == true) {
            filters\timeFilter::$tyWeekRange = NULL;
            $filterSql = "(".filters\timeFilter::$lyWeekRange.")";
        }

        $measureSelectRes = $this->prepareMeasureSelectPart();
        $this->measureFields = $measureSelectRes['measureFields'];
        $mainSelectionArr = $this->getSqlPart();

        $havingPart = "";
        if (count((array) $measureSelectRes['ddbMeasureHavingPart']) > 0)
            $havingPart = " HAVING (" . implode(" OR ", (array) $measureSelectRes['ddbMeasureHavingPart']) . ")";

        $selectPart = explode(',', $mainSelectionArr['selectPart']);
        if(!empty($selectPart))
            $this->measureFields = array_merge($this->measureFields,$selectPart);
        
        $groupByPart = $mainSelectionArr['groupByPart'];
        
        $ddbFilterPartSql = "";
        if(isset($this->queryVars->projectConfiguration['ddb_extra_filter_part_selection']) && $this->queryVars->projectConfiguration['ddb_extra_filter_part_selection'] != '') {
            $fieldArr = explode("##", $this->queryVars->projectConfiguration['ddb_extra_filter_part_selection']);
            $ddbFilterSql = '';
            if (is_array($fieldArr) && !empty($fieldArr)) {
                foreach ($fieldArr as $fieldConfig) {
                    $fieldData = explode("|", $fieldConfig);
                    list($fieldName, $ddbFilterPartSql) = $this->setFilterQuery($fieldData[0], $fieldData[1], $fieldData[2]);
                    $ddbFilterSql .= $ddbFilterPartSql;
                    $this->measureFields[] = $fieldName;
                }
            }
        }

        $this->prepareTablesUsedForQuery($this->measureFields);
        $this->settingVars->useRequiredTablesOnly = true;
        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll

        /** MESSAGING RESPONSE */
        if ($this->allowedToSendMessage)
            $this->send_message("Query Preparing", "30");

        $measureSelectionArr = $measureSelectRes['measureSelectionArr'];
        $slpart = isset($mainSelectionArr['selectPart']) ? $mainSelectionArr['selectPart'] : '';

        $query = "SELECT ".$slpart.",".implode(",", $measureSelectionArr)." ".
                "FROM " . $this->settingVars->tablename . $this->queryPart . " AND ".$filterSql. $ddbFilterSql. 
                " GROUP BY $groupByPart $havingPart";

        $redisCache = new \utils\RedisCache($this->queryVars);
        $redisOutput = $redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }

        /** MESSAGING RESPONSE */
        if ($this->allowedToSendMessage)
            $this->send_message("Query Preparing", "40");

        if (!$result) {
            if ($this->allowedToSendMessage)
                $this->send_message("No data found for this selection", "100", '', '', 0);
            exit;
        } elseif (count($result) > 1048575) {
            $this->send_message("Your request will return more than 1.04 million rows, which is more than Excel can handle! Please reduce this by adding filters or changing your Output Colums and try again.", "100", '', '', 0);
            exit();
        } else {

            $csvOutput = "";
            $csvHeaderForItems = "";

            $totalMeasures = explode("-", $_REQUEST['measures']);
            $totalItems = explode("-", rawurldecode($_REQUEST['items']));
            if($_REQUEST['weekOption'] == 2)
                $totalItems[] = $this->settingVars->dataArray['YEARWEEK']['NAME_ALIASE'];

            //ADDING  HEADERS FOR ACCOUNT COLUMNS [PRODUCT OPTIONS,STORE OPTIONS]
            for ($i = 0; $i < count($totalItems); $i++) 
			{
                if (isset($this->settingVars->dataArray[$totalItems[$i]])) 
				{
                    if (key_exists('ID_ALIASE', (array) $this->settingVars->dataArray[$totalItems[$i]]))
					{
						if(isset($this->settingVars->dataArray[$totalItems[$i]]['id_csv_header']))
							$csvHeaderForItems .= $this->settingVars->dataArray[$totalItems[$i]]['id_csv_header'] . ",";
						else
							$csvHeaderForItems .= $this->settingVars->dataArray[$totalItems[$i]]['ID_ALIASE'] . ",";
					}

					if(isset($this->settingVars->dataArray[$totalItems[$i]]['csv_header']))
						$csvHeaderForItems .= $this->settingVars->dataArray[$totalItems[$i]]['csv_header'] . ",";
					else	
						$csvHeaderForItems .= isset($this->settingVars->dataArray[$totalItems[$i]]['NAME_CSV']) ? $this->settingVars->dataArray[$totalItems[$i]]['NAME_CSV']. "," : $this->settingVars->dataArray[$totalItems[$i]]['NAME_ALIASE'] . ",";
                }
            }

            /** MESSAGING RESPONSE */
            if ($this->allowedToSendMessage)
                $this->send_message("Query Preparing", "60");

            //ADDING HEADERS FOR MEASURE COLUMNS [VALU,VOLUME....]
            $meausreCols = array_column($this->settingVars->pageArray['MEASURE_SELECTION_LIST'],'measureName','measureID');

            if($_REQUEST['weekOption'] == 1 && $this->timeFilterExportOption == 'showThisYearAndLastYearRow'){
                $csvHeaderForItems .= "Time Range,";
            }

            for ($i = 0; $i < count($totalMeasures); $i++) {
                if (key_exists('M'.$totalMeasures[$i], $this->settingVars->measureArray)) {
                    $name = $this->settingVars->measureArray['M'.$totalMeasures[$i]]['ALIASE'];
                } else {
                    $name = str_replace("space", " ", $totalMeasures[$i]);
                }
                if(isset($meausreCols[$totalMeasures[$i]]))
                    $name = $meausreCols[$totalMeasures[$i]];

                if($_REQUEST['weekOption'] == 2 || $this->isLYAsRowActive){
                    $csvHeaderForItems .= $name . ",";
                }else{
                    if($this->isTYActive == true) {
                        $csvHeaderForItems .= $name . " (This Year),";
                    }

                    if($this->isLYActive == true) {
                        $csvHeaderForItems .= $name . " (Last Year),";
                    }
                }
            }
            $csvOutput .= substr($csvHeaderForItems, 0, strlen($csvHeaderForItems) - 1) . "\n";

            /** MESSAGING RESPONSE */
            if ($this->allowedToSendMessage)
                $this->send_message("Query Preparing", "70");

            //ADDING ROW DATA
            /*while ($data = mysqli_fetch_assoc($result)) {
                $csvOutput .= $this->getCsvPart($data);
            }*/
            //mysqli_free_result($result);

            $cnt = count($result);
            for($i=0;$i<$cnt;$i++){
               $csvOutput .= $this->getCsvPart($result[$i]); 
            }
            unset($result);
            return $this->exportCSVAsZip($csvOutput);
        }
    }

    private function exportCSVAsZip($csvData) {

        /** MESSAGING RESPONSE */
        if ($this->allowedToSendMessage)
            $this->send_message("Query Preparing", "90");

        $date = date('Y_m_j_H\hi\ms\s') . "_" . $this->settingVars->aid;
        $fileName = $date . ".zip";
        chdir($_SERVER['DOCUMENT_ROOT'] . DIRECTORY_SEPARATOR . "zip");
        $zip = new ZipArchive;
        $result_zip = $zip->open($fileName, ZIPARCHIVE::CREATE);
        $zip->addFromString($date . ".csv", $csvData);
        $zip->close();

        $csvFile = getcwd() . DIRECTORY_SEPARATOR . $date . ".csv";
        $zipFile = getcwd() . DIRECTORY_SEPARATOR . $date . ".zip";
        /** MESSAGING RESPONSE */
        //if ($this->allowedToSendMessage)
            $this->send_message("Query Preparing", "100", $csvFile, $zipFile, 1);
        exit;
    }

    private function downloadFile() {
        $zipFile = $_REQUEST['zipFile'];
        if ($fd = fopen($zipFile, "r")) {
            $fsize = filesize($zipFile);
            $path_parts = pathinfo($zipFile);
            $ext = strtolower($path_parts["extension"]);
            switch ($ext) {
                case "zip": {
                        header("Content-type: application/force-download");
                        header("Content-Transfer-Encoding: application/zip;");
                        header("Content-disposition: attachment; filename=" . "reportData.zip");
                        break;
                    }
                default: print "Unknown file format";
                    exit;
            }
            header("Content-length: $fsize");
            header("Cache-Control: must-revalidate, post-check=0, pre-check=0, public");
            header("Expires: 0");
            @readfile($zipFile);
        }
        fclose($fd);
        //$this->deleteFiles();
    }

    /*     * ***
     * DELETES THE CSV FILES, THOSE WERE CREATED DURING REPORT GENERATION PROCESS
     * **** */
    private function deleteFiles() {
        $csvFile = $_REQUEST['csvFile'];
        $zipFile = $_REQUEST['zipFile'];

        if (file_exists($zipFile))
            unlink($zipFile);
        if (file_exists($csvFile))
            unlink($csvFile);
    }

    private function send_message($message, $progress, $csvfile = '', $zipfile = '', $status = 0) {
        $d = array('message' => $message, 'progress' => $progress, 'csvFile' => $csvfile, 'zipFile' => $zipfile, 'status' => $status);
        if ($progress > 10)
            echo json_encode($d);
        else
            echo json_encode($d);
			
        //PUSH THE data out by all FORCE POSSIBLE
        ob_flush();
        flush();
        sleep(1);
    }

    public function setFilterQuery($fieldName, $fieldOperator, $fieldValue) {
        $this->buildDataArray(array($fieldName), true);
        $gridFieldPart = explode("#", $fieldName);
        $accountField = strtoupper($this->dbColumnsArray[$gridFieldPart[0]]);
        $accountField = (count($gridFieldPart) > 1) ? strtoupper($accountField."_".$this->dbColumnsArray[$gridFieldPart[1]]) : $accountField;
        $fieldName = $this->settingVars->dataArray[$accountField]['NAME'];

        switch ($fieldOperator) {
            case 'EQUALS_TO':
                $filterFieldExtraWhere = ' AND '.$fieldName.' = "'.$fieldValue.'"';
            break;
            case 'NOT_EQUALS_TO':
                $filterFieldExtraWhere = ' AND '.$fieldName.' != "'.$fieldValue.'"';
            break;
            case 'CONTAINS':
                $filterFieldExtraWhere = ' AND '.$fieldName.' LIKE "%'.$fieldValue.'%"';
            break;
            case 'NOT_CONTAINS':
                $filterFieldExtraWhere = ' AND '.$fieldName.' NOT LIKE "%'.$fieldValue.'%"';
            break;
            default:
                $filterFieldExtraWhere = ' AND '.$fieldName.' LIKE "%'.$fieldValue.'%"';
            break;
        }
        return [$fieldName, $filterFieldExtraWhere];
    }
}
?>