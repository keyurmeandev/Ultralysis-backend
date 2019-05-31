<?php

namespace classes;

use projectsettings;
use db;
use config;
use utils;

class SkuListSeasonalTracker extends config\UlConfig {
    /*     * ***
     * Default gateway function, should be similar in all DATA CLASS
     * arguments:
     *  $settingVars [project settingsGateway variables]
     * @return jsonOutput with all data that should be sent to client app
     * *** */

    public function go($settingVars) {
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES
        $this->redisCache = new utils\RedisCache($this->queryVars);
        
        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true')
        {
            $columnConfig['PNAME'] = "BASE SKU";
            $columnConfig['PNAME2'] = "MASTER SKU";
            $columnConfig['BRAND'] = "BRAND";
            $columnConfig['BRAND_RANGE'] = "BRAND RANGE";
            $this->jsonOutput['GRID_SETUP'] = $columnConfig;
            $this->getSeasonalDesc();
            $this->jsonOutput['sku'] = array();
        }
        else
            $this->createSkuList();
        
        return $this->jsonOutput;
    }

    public function getSeasonalDesc()
    {
        $query = "SELECT DISTINCT seasonal_description as SEAS_DESC FROM ".$this->settingVars->maintable;
        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }
        
        $seasonaldescList = array();
        if(is_array($result) && !empty($result))
        {
            foreach($result as $data)
                $seasonaldescList[] = array('data' => $data['SEAS_DESC'], 'label' => $data['SEAS_DESC']);
        }
        $this->jsonOutput['seasonaldescList'] = $seasonaldescList;
    }
    
    public function createSkuList() 
    {
        $skuList = array();
        
        if($_REQUEST['seasonalDescription'] != "")
        {
            $query = "SELECT DISTINCT ".$this->settingVars->skutable.".pname as PNAME, ".$this->settingVars->skutable.".pname2 as PNAME2, ".$this->settingVars->skutable.".agg1 as BRAND, ".$this->settingVars->skutable.".agg2 as BRAND_RANGE FROM ".$this->settingVars->maintable.", ".$this->settingVars->skutable." WHERE ".$this->settingVars->maintable.".PNAME = ".$this->settingVars->skutable.".pname AND seasonal_description = '".$_REQUEST['seasonalDescription']."' ";
            
            $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
            if ($redisOutput === false) {
                $skuList = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
                $this->redisCache->setDataForHash($result);
            } else {
                $skuList = $redisOutput;
            }
        }
        $this->jsonOutput['sku'] = $skuList;
    }

}

?>