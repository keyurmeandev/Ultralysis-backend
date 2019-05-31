<?php

namespace classes\lcl;

//ini_set("memory_limit", "350M");

/** PHPExcel_IOFactory */
//require_once '../ppt/Classes/PHPExcel/IOFactory.php';

use datahelper;
use projectsettings;
use filters;
use db;
use config;
use utils;

class MapByAccount extends config\UlConfig {

    public function go($settingVars) {
    	unset($_REQUEST["FSG"]);
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES
        $this->queryPart = $this->getAll(); //" AND CONTROL_FLAG IN (2,0) ";  //PREPARE TABLE JOIN STRING USING this class getAll
        $this->redisCache = new utils\RedisCache($this->queryVars);
        
        $this->settingVars->pageName = (empty($this->settingVars->pageName) && !empty($this->settingVars->pageID)) ? $this->settingVars->pageID.'_MarketComparisonPage' : $this->settingVars->pageName;

        $this->timeArray  = array( '4' => 'LW4', '13' => 'LW13', '52' => 'LW52' );
        /* if ($this->settingVars->isDynamicPage) {
            $this->gridField = $this->getPageConfiguration('grid_field', $this->settingVars->pageID)[0];
            $fieldArray = array($this->gridField);

            $this->buildDataArray($fieldArray);
            $this->buildPageArray();
        }else{
        	$this->configurationFailureMessage();
        } */

        if(isset($_REQUEST['fetchConfig']) && $_REQUEST['fetchConfig'] == true)
            $this->buildPageArray();
        
        $action = $_REQUEST["action"];
        switch ($action) {
            case "getData" : {
                $this->getChartData();
                break;
            }
        }

        return $this->jsonOutput;
    }

    public function getChartData()
    {
        $this->provinceField = $this->settingVars->storetable.".PROVINCE"; // CSV NAME
        $getField = $_REQUEST['Field'];
        
        $this->buildDataArray(array($this->provinceField, $getField), true, false);
        
        $gridFieldPart = explode("#", $getField);
        $accountField = strtoupper($this->dbColumnsArray[$gridFieldPart[0]]);
        $accountField = (count($gridFieldPart) > 1) ? strtoupper($accountField."_".$this->dbColumnsArray[$gridFieldPart[1]]) : $accountField;        
        
        $this->accountID = (isset($this->settingVars->dataArray[$accountField]) && isset($this->settingVars->dataArray[$accountField]['ID'])) ? $this->settingVars->dataArray[$accountField]['ID'] : $this->settingVars->dataArray[$accountField]['NAME'];
        $this->accountName = $this->settingVars->dataArray[$accountField]['NAME'];
        
        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $measureSelectRes = $this->prepareMeasureSelectPart();
        $this->measureFields = $measureSelectRes['measureFields'];
        
        $this->measureFields[] = $this->accountID;
        $this->measureFields[] = $this->accountName;
        $this->measureFields[] = $this->provinceField;
        
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->queryPart = $this->getAll();
        
        $measureSelectionArr = $measureSelectRes['measureSelectionArr'];
        $havingTYValue = $measureSelectRes['havingTYValue'];
        $havingLYValue = $measureSelectRes['havingLYValue'];
        $measureSelect = implode(",", $measureSelectionArr);
        
        $query = "SELECT ".$this->provinceField." AS PROVINCE, ".$this->accountName." AS ACCOUNT, ".$measureSelect." ".
                "FROM ".$this->settingVars->tablename ." ". trim($this->queryPart)." AND (".filters\timeFilter::$tyWeekRange." OR ".filters\timeFilter::$lyWeekRange.") GROUP BY PROVINCE, ACCOUNT";
                
        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($resultCached);
        } else {
            $result = $redisOutput;
        }
        
        $requiredGridFields = array("PROVINCE", "ACCOUNT", $havingTYValue, $havingLYValue);
        $result = $this->redisCache->getRequiredData($result, $requiredGridFields, $havingTYValue, $havingTYValue, $havingLYValue);
        
        // We are having sort name of province in db, as per sort name make it proper name as in provinceMap.html id tag
        $provinceList = array("USA" => "", "ON" => "Ontario", "PQ" => "PQ", "NT" => "Northwest Terr", "YT" => "Yukon", "PE" => "Prince Edward Island", "SK" => "Saskatchewan", "MB" => "Manitoba", "QC" => "Quebec", "NS" => "Nova Scotia", "NL" => "Newfoundland", "UN" => "UN", "NB" => "New Brunswick", "BC" => "British Columbia", "AB" => "Alberta");
        
        $groupArray = array();
        if(is_array($result) && !empty($result))
        {
            // Prepare response
            foreach($result as $data)
            {
                $data['data'] = ($data[$havingLYValue] != 0 ) ? (($data[$havingTYValue] - $data[$havingLYValue]) / $data[$havingLYValue]) * 100 : 0;
                $data['color'] = '#808080';//datahelper\Common_Data_Fetching_Functions::getColor($data['data']);
                $data['name'] = (isset($provinceList[$data['PROVINCE']])) ? $provinceList[$data['PROVINCE']] : $data['PROVINCE'];
                
                $tmp = array(
                        'name' => $data['name'],
                        'value' => $data[$havingTYValue],
                        'data' => $data['data'],
                        'varpct' => $data['data'],
                        'color' => $data['color'],
                        'varpct-color' => $data['color'],
                    );
                    
                unset($innerData[$havingTYValue], $innerData[$havingLYValue]);
                $key = "PROV_".htmlspecialchars_decode(strtoupper(str_replace(" ", "_", $data['name'])));
                $groupArray[$data['ACCOUNT']][$key] = $tmp;
            }
            
            // Finding share %
            foreach($groupArray as $key => $data)
            {
                $dataSum = array_sum(array_column($data, 'value'));
                
                foreach($data as $innerKey => $innerData)
                {
                    $groupArray[$key][$innerKey]['sharepct'] = ($dataSum != 0) ? ($innerData['value'] / $dataSum) * 100 : 0;
                    unset($groupArray[$key][$innerKey]['value']);
                }
                
                $groupArray[$key] = utils\SortUtility::sort2DArrayAssoc($groupArray[$key], 'sharepct', utils\SortTypes::$SORT_DESCENDING);
            }
        }
        $this->jsonOutput['mapData'] = $groupArray;
    }

    public function buildPageArray() {

        /* $fetchConfig = false;
        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
            $fetchConfig = true;
            $this->jsonOutput['pageConfig'] = array(
                'enabledFilters' => $this->getPageConfiguration('filter_settings', $this->settingVars->pageID)
            );

            $this->jsonOutput["gridColumns"]['ACCOUNT'] = $this->displayCsvNameArray[$this->gridField];
            $this->jsonOutput["timeSelection"] = $this->timeArray;
        }

        if ($this->settingVars->hasGlobalFilter) {
            $globalFilterField = $this->settingVars->globalFilterFieldDataArrayKey;

            $this->storeIDField = (isset($this->settingVars->dataArray[$globalFilterField]) && isset($this->settingVars->dataArray[$globalFilterField]['ID'])) ? $this->settingVars->dataArray[$globalFilterField]['ID'] : $this->settingVars->dataArray[$globalFilterField]['NAME'];
            $globalFilterFieldIDAlias = (isset($this->settingVars->dataArray[$globalFilterField]) && isset($this->settingVars->dataArray[$globalFilterField]['ID'])) ? $this->settingVars->dataArray[$globalFilterField]['ID_ALIASE'] : $this->settingVars->dataArray[$globalFilterField]['NAME_ALIASE'];
            $this->storeNameField = $this->settingVars->dataArray[$globalFilterField]['NAME'];
            $globalFilterFieldNameAlias = $this->settingVars->dataArray[$globalFilterField]['NAME_ALIASE'];
        }else{
            $this->configurationFailureMessage("Global filter configuration not found");
        }

        $gridField = strtoupper($this->dbColumnsArray[$this->gridField]);
        $this->gridFieldName = $this->settingVars->dataArray[$gridField]['NAME']; */

        /* FIELD SELECTION */
        
        $tables = array();
        if ($this->settingVars->isDynamicPage){
            $tables = $this->getPageConfiguration('table_settings', $this->settingVars->pageID);
            $tablesSelectedField = $this->getPageConfiguration('table_settings_default_field', $this->settingVars->pageID);
        }else {
            if (isset($this->getPageAnalysisField) && $this->getPageAnalysisField == 'Product')
                $tables = array($this->settingVars->skutable);
            else if (isset($this->getPageAnalysisField) && $this->getPageAnalysisField == 'Store')
                $tables = array("market");
        }

        $tables = array($this->settingVars->skutable);
        
        if (is_array($tables) && !empty($tables)) {
            $fields = $tmpArr = array();
            
            foreach ($tables as $table) {
                if(is_array($this->queryVars->projectConfiguration) && isset($this->queryVars->projectConfiguration[$table . "_settings"]) ){
                    $settings = explode("|", $this->queryVars->projectConfiguration[$table . "_settings"]);
                    foreach ($settings as $key => $field) {
                        $val = explode("#", $field);
                        
                        if($table == 'market')
                        {
                            $tbl = ($table == 'market') ? $this->settingVars->storetable : $table;
                            $fields[] = (count($val) > 1) ? $tbl.".".$val[0]."#".$tbl.".".$val[1] : $tbl.".".$val[0];
                            //$fields[] = (($table == 'market') ? $this->settingVars->storetable : $table).".".$val[0];
                        }
                        elseif($table == 'account')
                        {
                            $tbl = ($table == 'account') ? $this->settingVars->accounttable : $table;
                            $fields[] = (count($val) > 1) ? $tbl.".".$val[0]."#".$tbl.".".$val[1] : $tbl.".".$val[0];
                            //$fields[] = (($table == 'account') ? $this->settingVars->accounttable : $table).".".$val[0];
                        }
                        elseif($table == 'product')
                        {
                            $tbl = (isset($this->settingVars->skutable) && !empty($this->settingVars->skutable)) ? $this->settingVars->skutable : $table;
                            $fields[] = (count($val) > 1) ? $tbl.".".$val[0]."#".$tbl.".".$val[1] : $tbl.".".$val[0];
                            //$fields[] = ((isset($this->settingVars->skutable) && !empty($this->settingVars->skutable)) ? $this->settingVars->skutable : $table).".".$val[0];
                        }
                        else
                        {
                            $fields[] = (count($val) > 1) ? $table.".".$val[0]."#".$table.".".$val[1] : $table.".".$val[0];
                            //$fields[] = $table.".".$val[0];
                        }
                        
                        if ($key == 0) {
                            if($table == 'market')
                                $appendTable = ($table == 'market') ? $this->settingVars->storetable : $table;
                            elseif($table == 'account')
                                $appendTable = ($table == 'account') ? $this->settingVars->accounttable : $table;
                            elseif($table == 'product')
                                $appendTable = (isset($this->settingVars->skutable) && !empty($this->settingVars->skutable)) ? $this->settingVars->skutable : $table;
                            else
                                $appendTable = $table;
                            
                            //$this->settingVars->pageArray[$this->settingVars->pageName]["ACCOUNT"] = $appendTable . "." . $val[0];
                            $this->settingVars->pageArray[$this->settingVars->pageName]["ACCOUNT"] = (count($val) > 1) ? $appendTable.".".$val[0]."#".$appendTable.".".$val[1] : $appendTable.".".$val[0];
                        }
                    }
                }
            }

            $this->buildDataArray($fields, false, true);
            
            if(isset($tablesSelectedField) && is_array($tablesSelectedField) && !empty($tablesSelectedField)){
                $this->settingVars->pageArray[$this->settingVars->pageName]["ACCOUNT"] = $tablesSelectedField[0];
            }
            else
            {
                $account = explode("#", $this->settingVars->pageArray[$this->settingVars->pageName]["ACCOUNT"]);
                $search = array_search($account[0],$this->dbColumnsArray);
                
                if($search !== false)
                {
                    if(count($account) > 1)
                    {
                        $search1 = array_search($account[1],$this->dbColumnsArray);
                        $this->settingVars->pageArray[$this->settingVars->pageName]["ACCOUNT"] = $search."#".$search1;
                    }
                    else
                        $this->settingVars->pageArray[$this->settingVars->pageName]["ACCOUNT"] = $search;
                }
            }
            
            foreach ($fields as $field)
            {
                $val = explode("#", $field);
                $search = array_search($val[0],$this->dbColumnsArray);
                
                if($search !== false)
                {
                    if ($this->settingVars->pageArray[$this->settingVars->pageName]["ACCOUNT"] == $search && $search !== false)
                    {
                        if(count($val) > 1)
                        {
                            $search1 = array_search($val[1],$this->dbColumnsArray);
                            $this->settingVars->pageArray[$this->settingVars->pageName]["ACCOUNT"] = $search."#".$search1;
                        }
                        else
                            $this->settingVars->pageArray[$this->settingVars->pageName]["ACCOUNT"] = $search;
                    }

                    if(count($val) > 1)
                    {
                        $search1 = array_search($val[1],$this->dbColumnsArray);
                        $tmpArr[] = array('label' => $this->displayCsvNameArray[$search], 'data' => $search."#".$search1);
                    }
                    else
                        $tmpArr[] = array('label' => $this->displayCsvNameArray[$search], 'data' => $search);
                }
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
        
        /* FIELD SELECTION */
        
        
        return;
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

}
?>