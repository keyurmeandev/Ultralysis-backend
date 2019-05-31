<?php

namespace classes\ub;

use filters;
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
    
    public function createSkuFile() {

        $query  = "SELECT db_column, csv_column, db_table FROM ".$this->settingVars->clientconfigtable." WHERE cid=".$this->settingVars->aid." AND db_table IN ('".$this->settingVars->skutable."','".$this->settingVars->skulisttable."') AND show_on_ui='Y' ORDER BY rank ASC";
        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT); 
        $columnConfig = $skuList = array();

        if (is_array($result) && !empty($result)) {
            foreach ($result as $column) {
                $columnConfig[$column['db_column']] = $column['csv_column'];
                $columnTblConfig[$column['db_column']] = $column['db_table'];
            }        

            $this->jsonOutput['GRID_SETUP'] = $columnConfig;

            $sqlPart = array();
            foreach ($columnConfig as $key => $aliase) {
                $sqlPart[] = $columnTblConfig[$key].".".$key . " AS '$key'";
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