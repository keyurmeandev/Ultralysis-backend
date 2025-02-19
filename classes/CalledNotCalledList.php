<?php

namespace classes;

use projectsettings;
use SimpleXMLElement;
use datahelper;
use filters;
use db;
use config;

class CalledNotCalledList extends config\UlConfig {

    private $requiredAccountsList;
    private $groupByList;
    private $accountFields;
    private $accountNames;
    public $dbColumnsArray;
    public $displayCsvNameArray;
    public $displayDbColumnArray;

    /*     * ***
     * Default gateway function, should be similar in all DATA CLASS
     * arguments:
     *  $settingVars [project settingsGateway variables]
     * @return $xmlOutput with all data that should be sent to client app
     * *** */

    public function go($settingVars) {
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES
        $this->queryPart = $this->getAll();

        $this->redisCache = new \utils\RedisCache($this->queryVars);

        $this->requiredAccountsList = array();
        $this->groupByList = array();

        $this->settingVars->pageName = (empty($this->settingVars->pageName)) ? $this->settingVars->pageID . '_CalledNotCalledListPage' : $this->settingVars->pageName;

        if ($this->settingVars->isDynamicPage) {
            $this->accountFields = $this->getPageConfiguration('account_list_field', $this->settingVars->pageID);
            $this->buildDataArray($this->accountFields);
            $this->buildPageArray();
        } else {
            if (!isset($this->settingVars->pageArray[$this->settingVars->pageName]) || empty($this->settingVars->pageArray[$this->settingVars->pageName]))
                $this->configurationFailureMessage();

            $this->accountNames = explode("-", $this->settingVars->pageArray[$this->settingVars->pageName]['ACCOUNTS']);
        }

        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
            $this->setGridColumns();
        }else{
            $this->createCalledNotCalled();
        }

        return $this->jsonOutput;
    }
    
    private function prepareSelectAndGroupPart(){
        foreach ($this->accountNames as $key => $account) {

            $tempId = key_exists('ID', $this->settingVars->dataArray[$account]) ? $this->settingVars->dataArray[$account]['ID'] : "";
            if ($tempId != "") {
                $this->requiredAccountsList[] = $tempId . " AS '" . $this->settingVars->dataArray[$account]['ID_ALIASE'] . "'";
                $this->groupByList[] = "'" . $this->settingVars->dataArray[$account]['ID_ALIASE'] . "'";
                $this->measureFields[] = $tempId;
            }
            $this->requiredAccountsList[] = $this->settingVars->dataArray[$account]['NAME'] . " AS '" . $this->settingVars->dataArray[$account]['NAME_ALIASE'] . "'";
            $this->groupByList[] = "'" . $this->settingVars->dataArray[$account]['NAME_ALIASE'] . "'";
            $this->measureFields[] = $this->settingVars->dataArray[$account]['NAME'];
        }
    }

    public function createCalledNotCalled() {
        $this->gridData();
    }

    public function gridData() {

        //$this->settingVars->territoryLevel =  'NOT CALLED';

        /*$this->measureFields = $this->prepareTablesUsedForMeasure($_REQUEST['ValueVolume']);
        $this->prepareSelectAndGroupPart();
        $this->measureFields[] = $this->settingVars->territorytable.".level".$this->settingVars->territoryLevel;

        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->queryPart = $this->getAll();
        
        $options = array();
        if (!empty(filters\timeFilter::$tyWeekRange))
            $options['tyLyRange']['TYEAR'] = filters\timeFilter::$tyWeekRange;

        if (!empty(filters\timeFilter::$lyWeekRange))
            $options['tyLyRange']['LYEAR'] = filters\timeFilter::$lyWeekRange;

        $measureSelect = config\MeasureConfigure::prepareSelect($this->settingVars, $this->queryVars, array('M'.$_REQUEST['ValueVolume']), $options);
        $measureSelect = implode(", ", $measureSelect);

        $query = " SELECT " . implode(",", $this->requiredAccountsList) .
                " , ".$measureSelect." ".
                //",SUM( (CASE WHEN " . filters\timeFilter::$tyWeekRange . " THEN 1 ELSE 0 END) * " . $this->ValueVolume . ") AS TYEAR " .
                //",SUM( (CASE WHEN " . filters\timeFilter::$lyWeekRange . " THEN 1 ELSE 0 END) * " . $this->ValueVolume . ") AS LYEAR " .
                "FROM " . $this->settingVars->tablename . $this->queryPart . " AND (" . filters\timeFilter::$tyWeekRange . " OR " . filters\timeFilter::$lyWeekRange . ") " . $extraWhere .
                "GROUP BY " . implode(",", $this->groupByList) . " " .
                "HAVING TYEAR<>0 OR LYEAR<>0 " .
                "ORDER BY TYEAR DESC limit 200";*/


        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $measureSelectRes = $this->prepareMeasureSelectPart();
        $this->measureFields = $measureSelectRes['measureFields'];
        $this->prepareSelectAndGroupPart();

        if($this->settingVars->territorytable!='')
            $this->measureFields[] = $this->settingVars->territorytable.".level".$this->settingVars->territoryLevel;

        $this->settingVars->useRequiredTablesOnly = true;
        $this->prepareTablesUsedForQuery($this->measureFields);
        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll

        $measureSelectionArr = $measureSelectRes['measureSelectionArr'];
        $havingTYValue = $measureSelectRes['havingTYValue'];
        $havingLYValue = $measureSelectRes['havingLYValue'];


        /*STATIC ADD THE DECISIONFIELD WHIC WILL CHECK THE CALLED/NOT CALLED VALUE*/
        if($this->settingVars->territorytable!=''){
            $this->requiredAccountsList[] = $this->settingVars->territorytable.".level".$this->settingVars->territoryLevel." AS DECISIONFIELD";
            $this->groupByList[] = "'DECISIONFIELD'";
        }

        $query = "SELECT ". implode(",", $this->requiredAccountsList).','. implode(",", $measureSelectionArr).
            " FROM " . $this->settingVars->tablename .' '. trim($this->queryPart) .
            " AND (" . filters\timeFilter::$tyWeekRange . " OR " . filters\timeFilter::$lyWeekRange . ") " .
            "GROUP BY ".implode(",", $this->groupByList);

        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }
        
        $dataFieldsArr = array();
        foreach ($this->groupByList as $gKey => $groupAccount) {
            $dataFieldsArr[] = htmlspecialchars_decode(str_replace("'", "", $groupAccount));
        }
        $requiredGridFields = array_merge($dataFieldsArr,[$havingTYValue, $havingLYValue]);
        $result = $this->redisCache->getRequiredData($result, $requiredGridFields, $havingTYValue, $havingTYValue, $havingLYValue);

        $dataArray = array();
        foreach ($result as $key => $data) {
            $keyFg = '';
            if(isset($data['DECISIONFIELD']) && $data['DECISIONFIELD'] == 'NOT CALLED'){
                $keyFg = 'NOT_CALL_LIST';
            }else{
                $keyFg = 'CALL_LIST';
            }

            if(count($dataArray['NOT_CALL_LIST']) >= 200 && count($dataArray['CALL_LIST']) >= 200){
                break;
            }else if($keyFg == 'NOT_CALL_LIST' && count($dataArray['NOT_CALL_LIST']) >= 200){
                continue;
            }else if($keyFg == 'CALL_LIST' && count($dataArray['CALL_LIST']) >= 200){
                continue;
            }

            $percent = $data[$havingLYValue] > 0 ? ((($data[$havingTYValue] / $data[$havingLYValue]) - 1) * 100) : 0;
            foreach ($this->groupByList as $gKey => $groupAccount) {
                $dataArray[$keyFg][$key][str_replace("'", "", $groupAccount)] = htmlspecialchars_decode($data[str_replace("'", "", $groupAccount)]);
            }
            $dataArray[$keyFg][$key]['TYEAR'] = $data[$havingTYValue];
            $dataArray[$keyFg][$key]['LYEAR'] = $data[$havingLYValue];
            $dataArray[$keyFg][$key]['DIFF']  = $data[$havingTYValue] - $data[$havingLYValue];
            $dataArray[$keyFg][$key]['PERCENT'] = $percent;
        }

        $this->jsonOutput['NOT_CALL_LIST'] = isset($dataArray['NOT_CALL_LIST']) && is_array($dataArray['NOT_CALL_LIST']) ? array_values($dataArray['NOT_CALL_LIST']) : [];
        $this->jsonOutput['CALL_LIST'] = isset($dataArray['CALL_LIST']) && is_array($dataArray['CALL_LIST']) ? array_values($dataArray['CALL_LIST']) : [];
    }

    private function setGridColumns() {
        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
            $tempCol = array();
            foreach ($this->accountNames as $key => $value) {
                //$tempCol[strtoupper($this->displayDbColumnArray[$value])] = $this->displayCsvNameArray[$this->accountFields[$key]];
                $tempCol[$this->settingVars->dataArray[strtoupper($this->accountNames[$key])]['NAME_ALIASE']] = $this->displayCsvNameArray[$this->accountFields[$key]];
            }
            $this->jsonOutput["GRID_COLUMN_NAMES"] = $tempCol;
        }
    }

    private function makeFieldsToAccounts($srcArray) {
        $tempArr = array();
        foreach ($srcArray as $value) {
            $tempArr[] = strtoupper($this->dbColumnsArray[$value]);
        }
        return $tempArr;
    }

    public function buildPageArray() {
        $fetchConfig = false;
        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
            $fetchConfig = true;
            $this->jsonOutput['pageConfig'] = array(
                'enabledFilters' => $this->getPageConfiguration('filter_settings', $this->settingVars->pageID)
            );
        }

        $this->accountNames = $this->makeFieldsToAccounts($this->accountFields);

        return;
    }

    public function buildDataArray($fields) {
        if (empty($fields))
            return false;

        $configurationCheck = new config\ConfigurationCheck($this->settingVars, $this->queryVars);
        $configurationCheck->buildDataArray($fields);
        $this->dbColumnsArray = $configurationCheck->dbColumnsArray;
        $this->displayCsvNameArray = $configurationCheck->displayCsvNameArray;
        $this->displayDbColumnArray = $configurationCheck->displayDbColumnArray;
        return;
    }

}

?>