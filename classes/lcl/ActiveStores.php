<?php
namespace classes\lcl;

use datahelper;
use projectsettings;
use filters;
use utils;
use db;
use config;

class ActiveStores extends config\UlConfig {

    public function go($settingVars) {
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES
        // $this->queryPart = $this->getAll(); //USES OWN getAll FUNCTION
        $this->pageName = $_REQUEST["pageName"];
        //$this->getFieldSettings();
        //$this->jsonOutput['GRID_SETUP'] = $this->settingVars->pageArray[$this->pageName]['GRID_SETUP'];
        $this->prepareGridData(); //ADDING TO OUTPUT

        return $this->jsonOutput;
    }

    private function getFieldSettings(){
        $configurationCheck = new config\ConfigurationCheck($this->settingVars, $this->queryVars);
        $this->dbColumns = array();

        // Query to fetch settings 
        $result = (isset($configurationCheck->settings['has_market_filter']) && !empty($configurationCheck->settings['has_market_filter'])) ? $configurationCheck->settings['has_market_filter'] : '';
        if (!empty($result)) {
            $filterSettingStr = (isset($configurationCheck->settings['market_settings']) && !empty($configurationCheck->settings['market_settings'])) ? $configurationCheck->settings['market_settings'] : '';
            if (!empty($filterSettingStr)) {
                $settings = explode("|", $filterSettingStr);
                // Explode with # because we are getting some value with # ie (PNAME#PIN) And such column name combination not match with db_column. 
                foreach($settings as $key => $data)
                {
                    $originalCol = explode("#", $data);
                    if(is_array($originalCol) && !empty($originalCol))
                        $settings[$key] = $originalCol[0];
                }
                $this->dbColumns = $settings;
                
            }
            else{
                $this->configErrors[] = "Market filter configuration not found.";
            }
                
        }else{
            $this->configErrors[] = "Market filter configuration not found.";
        }

        if (!empty($this->configErrors)) {
            $response = array("configuration" => array("status" => "fail", "messages" => $this->configErrors));
            echo json_encode($response);
            exit();
        }

    }

    private function prepareGridData() {

        $this->getFieldSettings();

        $result = array();

        if(is_array($this->dbColumns) && !empty($this->dbColumns)){
            foreach ($this->dbColumns as $field) {
                $searchKeyDB  = array_search($field, array_column($this->settingVars->clientConfiguration, 'db_column'));
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
            $this->settingVars->tableUsedForQuery = $this->measureFields = array();

            //$sqlPart[] = $this->settingVars->storetable.".SNO AS SNO_DEF";
            foreach ($columnConfig as $key => $aliase) {
                $sqlPart[] = $this->settingVars->storetable.".".$key . " AS '$key'";
                $this->measureFields[] = $this->settingVars->storetable.".".$key;
            }
            $sqlPart = implode(",", $sqlPart);

            $this->settingVars->useRequiredTablesOnly = true;
            if (is_array($this->measureFields) && !empty($this->measureFields)) {
                $this->prepareTablesUsedForQuery($this->measureFields);
            }

            $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll

            $this->queryPart .= " AND CONCAT(" . $this->settingVars->yearperiod . "," . $this->settingVars->weekperiod . ") ".
            " IN (" . implode(",", filters\timeFilter::getYearWeekWithinRange(0, 1, $this->settingVars)) . ") ";

            $query = "SELECT DISTINCT $sqlPart " .
                    "FROM " . $this->settingVars->tablename . "," . $this->settingVars->activetable . $this->queryPart . " " .
                    "AND " . $this->settingVars->storetable . ".SNO = " . $this->settingVars->activetable . ".SNO ";
                    //"GROUP BY SNO";
            //echo $query;exit;     
            $skuList = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);

        }

        $this->jsonOutput['SkuList'] = $skuList;
        
    }
}

?> 