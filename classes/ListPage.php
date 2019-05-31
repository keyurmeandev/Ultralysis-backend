<?php

namespace classes;

use datahelper;
use projectsettings;
use filters;
use utils;
use db;
use config;

class ListPage extends config\UlConfig {
    public $timeFrame;
    public $allCsvFields;

    /*****
     * Default gateway function, should be similar in all DATA CLASS
     * arguments:
     *  $settingVars [project settingsGateway variables]
     * @return jsonOutput with all data that should be sent to client app
    *****/
    public function go($settingVars) {
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES
        
        /*[START] ON THIS PAGE WE DONT REQUIRED THE TIMEFILTES*/
        filters\timeFilter::$tyWeekRange = NULL;
        filters\timeFilter::$lyWeekRange = NULL;
        /*[END] ON THIS PAGE WE DONT REQUIRED THE TIMEFILTES*/

        if ($this->settingVars->isDynamicPage){
            $this->table = $this->getPageConfiguration('table_field', $this->settingVars->pageID)[0];
            $this->allCsvFields = $this->getPageConfiguration('list_page_table_fields_list', $this->settingVars->pageID);

            if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
                $this->jsonOutput['pageConfig']['enabledFilters'] = $this->getPageConfiguration('filter_settings', $this->settingVars->pageID);
                if($this->table == "product")
                    $this->jsonOutput['tableName'] = "SKU";
                else
                    $this->jsonOutput['tableName'] = ucfirst($this->table);
            }
        }

        if (isset($_REQUEST["timeFrame"]) && !empty($_REQUEST["timeFrame"])) {
            $this->timeFrame = $_REQUEST["timeFrame"];
        }

        $this->queryPart = $this->getAll();  //USES OWN getAll FUNCTION  
        $this->getListData();
        return $this->jsonOutput;
    }

    private function getFieldSettings($table){
        $this->dbColumns = array();

        if($table == 'store')
            $table = 'market';

        $hasSettingName = 'has_'.$table.'_filter';
        $filterSettingName = $table.'_settings';

        // Query to fetch settings 
        $result = (isset($this->queryVars->projectConfiguration[$hasSettingName]) && 
            !empty($this->queryVars->projectConfiguration[$hasSettingName])) ? $this->queryVars->projectConfiguration[$hasSettingName] : '';
        if (!empty($result)) {
            $filterSettingStr = (isset($this->queryVars->projectConfiguration[$filterSettingName]) && 
                !empty($this->queryVars->projectConfiguration[$filterSettingName])) ? $this->queryVars->projectConfiguration[$filterSettingName] : '';
            if (!empty($filterSettingStr)) {
                $settings = explode("|", $filterSettingStr);
                // Explode with # because we are getting some value with # ie (PNAME#PIN) And such column name combination not match with db_column. 
                foreach($settings as $key => $data)
                {
                    $originalCol = explode("#", $data);
                    if(is_array($originalCol) && !empty($originalCol))
                        $this->dbColumns[] = $originalCol[0];
                    if(is_array($originalCol) && count($originalCol) > 1)
                        $this->dbColumns[] = $originalCol[1];                        
                }
            }
            else{
                $this->configErrors[] = ucfirst($table)." filter configuration not found.";
            }
        }else{
            $this->configErrors[] = ucfirst($table)." filter configuration not found.";
        }

        if (!empty($this->configErrors)) {
            $response = array("configuration" => array("status" => "fail", "messages" => $this->configErrors));
            echo json_encode($response);
            exit();
        }
    }
    
    public function getListData() {

        if(isset($this->allCsvFields) && is_array($this->allCsvFields) && count($this->allCsvFields) > 0){
            $this->buildDataArray($this->allCsvFields);
            $this->dbColumns = $this->dbColumnsArray;
        }else{
            $this->getFieldSettings($this->table);
        }

        $result = array();
        if(is_array($this->dbColumns) && !empty($this->dbColumns)){
            foreach ($this->dbColumns as $field) {
                if(isset($this->allCsvFields) && is_array($this->allCsvFields) && count($this->allCsvFields) > 0){
                    $searchKeyDB  = array_search($field, array_column($this->settingVars->clientConfiguration, 'db_table_db'));
                }else{
                    $searchKeyDB  = array_search($field, array_column($this->settingVars->clientConfiguration, 'db_column'));
                }
                if ($searchKeyDB !== false) {
                    $result[] = $this->settingVars->clientConfiguration[$searchKeyDB];
                }
            }
            $result = utils\SortUtility::sort2DArray($result, 'rank', utils\SortTypes::$SORT_ASCENDING);
        }

        $columnConfig = $skuList = array();
        if (is_array($result) && !empty($result)) {
            foreach ($result as $column) {
                $columnConfig[$column['db_column']] = $column['csv_column'];
            }
        
            if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true'){
                $this->jsonOutput['GRID_SETUP'] = $columnConfig;
                return;
            }

            $sqlPart = array();
            foreach ($columnConfig as $key => $aliase) {
                $sqlPart[] = $this->table.".".$key . " AS '$key'";
            }
            $sqlPart = implode(",", $sqlPart);

            $query = "SELECT DISTINCT $sqlPart " .
                    "FROM " . $this->settingVars->tableArray[$this->table]['tables'] . 
                    ((is_array($this->preparedLinkQuery) && !empty($this->preparedLinkQuery) && isset($this->preparedLinkQuery['tables']) && !empty($this->preparedLinkQuery['tables'])) ? $this->preparedLinkQuery['tables'] : "") .
                    $this->settingVars->tableArray[$this->table]['link'] . 
                    ((is_array($this->preparedLinkQuery) && !empty($this->preparedLinkQuery) && isset($this->preparedLinkQuery['link']) && !empty($this->preparedLinkQuery['link'])) ? $this->preparedLinkQuery['link'] : "") .
                    $this->queryPart;
            $skuList = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
        }

        $this->jsonOutput['sku'] = $skuList;
    }

    //overriding parent class's getAll function
    public function getAll() {
        $tablejoins_and_filters = "";
        if (isset($_REQUEST["FS"]) && $_REQUEST["FS"] != ''){
            $tablejoins_and_filters   .= filters\productAndMarketFilter::include_product_and_market_filters($this->queryVars,$this->settingVars);
        }

        /*[START] time filter date rang will apply only for the STORE table */
        if ($this->table == 'store') {
            $this->preparedLinkQuery = $this->prepareLinkForQuery([$this->table]);
            $this->settingVars->tableArray[$this->table]['link'] = " WHERE ".$this->settingVars->storetable.".gid IN (".$this->settingVars->GID.") ";
            if (!empty($this->timeFrame)) {

                /*$allDateArr = filters\timeFilter::getYearWeekWithinRange(0, $this->timeFrame, $this->settingVars,false,true);
                if (is_array($allDateArr) && count($allDateArr)>0) {
                    $tablejoins_and_filters .= "AND ".$this->settingVars->dateLastSold." BETWEEN '" .end($allDateArr). "' AND '" .$allDateArr[0]. "' ";
                }*/
                
                $query = 'SELECT MAX('.$this->settingVars->dateLastSold.') AS DATELASTSOLD FROM '.$this->settingVars->tableArray['store']['tables'].$this->settingVars->tableArray['store']['link'];
                $getMaxDateLastSold = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
                if(!empty($getMaxDateLastSold) && isset($getMaxDateLastSold[0]['DATELASTSOLD'])) {
                    $dateLastSoldEnd = $getMaxDateLastSold[0]['DATELASTSOLD'];
                    $dateLastSoldStart = date('Y-m-d',strtotime('-'.$this->timeFrame.' week', strtotime($dateLastSoldEnd)));
                    $tablejoins_and_filters .= " AND ".$this->settingVars->dateLastSold." BETWEEN '" .$dateLastSoldStart. "' AND '" .$dateLastSoldEnd. "' ";
                }
            }
        }
        /*[END] time filter date rang will apply only for the STORE table */
        return $tablejoins_and_filters;
    }

    public function prepareLinkForQuery($ignoreTables = array())
    {
        foreach ($this->settingVars->tableUsedForQuery as $key => $dataTable) {
            if(!empty($ignoreTables) && in_array($dataTable, $ignoreTables)) {
                continue;
            }
            if (isset($this->settingVars->dataTable[$dataTable])) {
                if (array_key_exists('tables', $this->settingVars->dataTable[$dataTable]) && !empty($this->settingVars->dataTable[$dataTable]['tables']) &&
                    array_key_exists('link', $this->settingVars->dataTable[$dataTable]) && !empty($this->settingVars->dataTable[$dataTable]['link'])) {
                    if(!in_array($dataTable, array_map('trim',explode(",", $tables))))
                    {
                        $tables .= ", ".$this->settingVars->dataTable[$dataTable]['tables'];
                        $link   .= $this->settingVars->dataTable[$dataTable]['link'];
                    }
                }
            }
        }
        return ['tables' => $tables, 'link' => $link];

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