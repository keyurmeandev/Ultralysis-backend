<?php

namespace classes;

use datahelper;
use projectsettings;
use filters;
use utils;
use db;
use config;

class RangedItemsbyStore extends config\UlConfig {
    /*     * ***
     * Default gateway function, should be similar in all DATA CLASS
     * arguments:
     *  $settingVars [project settingsGateway variables]
     * @return jsonOutput with all data that should be sent to client app
     * *** */

    public function go($settingVars) {
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES

        $this->storeID = (isset($this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['STORE_FIELD']]) && isset($this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['STORE_FIELD']]['ID'])) ? $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['STORE_FIELD']]['ID'] : $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['STORE_FIELD']]['NAME'];
        $this->storeName = $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['STORE_FIELD']]['NAME'];

        if($_REQUEST['firstRequest'])
            $this->getAllStore();

        if(isset($_REQUEST["action"]) && $_REQUEST["action"] == "filterChange" )
            $this->getRangedItemsByStore();
        
        return $this->jsonOutput;
    }

    public function getAllStore(){
        $query = "SELECT Distinct $this->storeID as SNO, $this->storeName as Store".
            " FROM ".$this->settingVars->storetable.
            " Where gid IN (".$this->settingVars->GID.") Order By Store";
            
        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);

        $row = array();
        foreach ($result as $data) {
            $data['label'] = $data['Store'] .' ( '.$data['SNO'].' ) ';
            $row[] = $data;
        }
        
        $this->jsonOutput['storeList'] = $row;
    }
    
    
    public function getRangedItemsByStore() {

        $this->skuID = (isset($this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['SKU_FIELD']]) && isset($this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['SKU_FIELD']]['ID'])) ? $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['SKU_FIELD']]['ID'] : $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['SKU_FIELD']]['NAME'];
        $this->skuName = $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['SKU_FIELD']]['NAME'];

        $rangedItemList = array();

        if($_REQUEST['selectedStore'] != ''){
            $qpart = " AND ".$this->settingVars->masterplanotable.".SNO =".$_REQUEST['selectedStore'];

            $query = "SELECT $this->storeID as SNO, $this->storeName as Store, $this->skuID as skuID, $this->skuName as Sku , plano as PLANO, facings as FACINGS ".
            " FROM ".$this->settingVars->masterplanotable.','.$this->settingVars->storetable.','.$this->settingVars->skutable.
            " Where 1 ".
            " AND ".$this->settingVars->storetable.".SNO = ".$this->settingVars->masterplanotable.".SNO " .
            " AND ".$this->settingVars->storetable.".gid IN (".$this->settingVars->GID.") " .
            " AND ".$this->settingVars->skutable.".PIN = ".$this->settingVars->masterplanotable.".PIN ".
            " AND ".$this->settingVars->skutable.".clientID = '".$this->settingVars->clientID."'".
            " AND ".$this->settingVars->skutable.".GID IN (".$this->settingVars->GID.") ".
            " AND ".$this->settingVars->skutable.".hide = 0".
            " AND ".$this->settingVars->masterplanotable.".accountID=".$this->settingVars->aid.
            " AND ".$this->settingVars->masterplanotable.".projectID=".$this->settingVars->projectID.$qpart;
            //echo $query;exit; 
            $rangedItemList = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
        }        
        
        $this->jsonOutput['rangedItemList'] = $rangedItemList;
    }

}

?>