<?php

namespace classes;

use datahelper;
use projectsettings;
use filters;
use utils;
use db;
use config;

class SkuFile extends config\UlConfig {
    /*     * ***
     * Default gateway function, should be similar in all DATA CLASS
     * arguments:
     *  $settingVars [project settingsGateway variables]
     * @return jsonOutput with all data that should be sent to client app
     * *** */

    public function go($settingVars) {
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES
        $this->queryPart = $this->getAll();  //USES OWN getAll FUNCTION  
        $this->pageName = $_REQUEST["pageName"];
        $this->createSkuFile();
        return $this->jsonOutput;
    }

    private function getFieldSettings(){
        $configurationCheck = new config\ConfigurationCheck($this->settingVars, $this->queryVars);
        $this->dbColumns = array();

        // Query to fetch settings 
        $result = (isset($configurationCheck->settings['has_product_filter']) && !empty($configurationCheck->settings['has_product_filter'])) ? $configurationCheck->settings['has_product_filter'] : '';
        if (!empty($result)) {
            $filterSettingStr = (isset($configurationCheck->settings['product_settings']) && !empty($configurationCheck->settings['product_settings'])) ? $configurationCheck->settings['product_settings'] : '';
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
                $this->configErrors[] = "Product filter configuration not found.";
            }
                
        }else{
            $this->configErrors[] = "Product filter configuration not found.";
        }

        if (!empty($this->configErrors)) {
            $response = array("configuration" => array("status" => "fail", "messages" => $this->configErrors));
            echo json_encode($response);
            exit();
        }

    }
    
    public function createSkuFile() {

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
            foreach ($columnConfig as $key => $aliase) {
                $sqlPart[] = $this->settingVars->skutable.".".$key . " AS '$key'";
            }
            $sqlPart = implode(",", $sqlPart);

            $query = "SELECT DISTINCT $sqlPart " .
                    "FROM " . $this->settingVars->productHelperTables . $this->queryPart;
            //echo $query;exit;		
            $skuList = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
        }
        $this->jsonOutput['sku'] = $skuList;
    }

    //overriding parent class's getAll function
    public function getAll() {
        $tablejoins_and_filters = $this->settingVars->productHelperLink;
		//$tablejoins_and_filters = $this->settingVars->clientID == "" ? "" : " WHERE clientID='" . $this->settingVars->clientID . "'";		
        return $tablejoins_and_filters;
    }

}

?>