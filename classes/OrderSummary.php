<?php

namespace classes;

use projectsettings;
use db;
use config;
use utils;
use filters;

class OrderSummary extends config\UlConfig {
    
    private $latestDates = [];
    private $previousDates = [];

    /**
     * Default gateway function, should be similar in all DATA CLASS
     * arguments:
     *  $settingVars [project settingsGateway variables]
     * @return jsonOutput with all data that should be sent to client app
     */

    public function go($settingVars) {
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES
        $this->redisCache = new utils\RedisCache($this->queryVars);

        if (isset($_REQUEST["timeFrame"]) && !empty($_REQUEST["timeFrame"])) {
            $this->timeFrame = $_REQUEST["timeFrame"];
        } else {
            $this->timeFrame = 12;
        }

        $this->setDateRange();

        $this->action = $_REQUEST["action"];
        
        $this->createList();    
        
        return $this->jsonOutput;
    }
    
    public function createList() 
    {
        $priceList = $dateArray = array();

        // $query = "SELECT territory as TERRITORY, COUNT(DISTINCT orderID) as ORDER_ID, COUNT(DISTINCT accountReference) as ACCOUNT_REFERENCES,COUNT(DISTINCT itemID) as ITEM_ID, SUM(quantity) as QUANTITY FROM ".$this->settingVars->ferreroIrelandOrdersTable  . " GROUP BY TERRITORY ORDER BY QUANTITY DESC";
        // echo $query;exit;

        $query = "SELECT territory AS TERRITORY, 
            COUNT(DISTINCT(CASE WHEN orderDate IN ('".implode("','", $this->latestDates)."') THEN orderID END)) as ORDER_ID, 
            COUNT(DISTINCT(CASE WHEN orderDate IN ('".implode("','", $this->previousDates)."') THEN orderID END)) as ORDER_ID_PREVIOUS, 
            
            COUNT(DISTINCT(CASE WHEN orderDate IN ('".implode("','", $this->latestDates)."') THEN accountReference END)) as ACCOUNT_REFERENCES, 
            COUNT(DISTINCT(CASE WHEN orderDate IN ('".implode("','", $this->previousDates)."') THEN accountReference END)) as ACCOUNT_REFERENCES_PREVIOUS, 

            COUNT(DISTINCT(CASE WHEN orderDate IN ('".implode("','", $this->latestDates)."') THEN itemID END)) as ITEM_ID, 
            COUNT(DISTINCT(CASE WHEN orderDate IN ('".implode("','", $this->previousDates)."') THEN itemID END)) as ITEM_ID_PREVIOUS,

            SUM(CASE WHEN orderDate IN ('".implode("','", $this->latestDates)."') THEN quantity ELSE 0 END) as QUANTITY,
            SUM(CASE WHEN orderDate IN ('".implode("','", $this->previousDates)."') THEN quantity ELSE 0 END) as QUANTITY_PREVIOUS

            FROM ".$this->settingVars->ferreroIrelandOrdersTable  . " GROUP BY TERRITORY ORDER BY QUANTITY DESC";
        
        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }

        foreach($result as &$value){
            $value['ORDER_ID_VAR_PER'] = $value['ORDER_ID_PREVIOUS'] > 0 ? number_format((($value['ORDER_ID'] - $value['ORDER_ID_PREVIOUS']) / $value['ORDER_ID_PREVIOUS']) * 100, 2) : 0;

            $value['ACCOUNT_REFERENCES_VAR_PER'] = $value['ACCOUNT_REFERENCES_PREVIOUS'] > 0 ? number_format((($value['ACCOUNT_REFERENCES'] - $value['ACCOUNT_REFERENCES_PREVIOUS']) / $value['ACCOUNT_REFERENCES_PREVIOUS']) * 100, 2) : 0;

            $value['ITEM_ID_VAR_PER'] = $value['ITEM_ID_PREVIOUS'] > 0 ? number_format((($value['ITEM_ID'] - $value['ITEM_ID_PREVIOUS']) / $value['ITEM_ID_PREVIOUS']) * 100, 2) : 0;

            $value['QUANTITY_VAR_PER'] = $value['QUANTITY_PREVIOUS'] > 0 ? number_format((($value['QUANTITY'] - $value['QUANTITY_PREVIOUS']) / $value['QUANTITY_PREVIOUS']) * 100, 2) : 0;
        }
        
        $this->jsonOutput['orderSummaryList'] = $result;
    }

    private function setDateRange(){

        $query = "select distinct orderDate from {$this->settingVars->ferreroIrelandOrdersTable} order by orderDate desc limit 0, $this->timeFrame";
        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
        $this->latestDates = array_column($result, 'orderDate');

        $query = "select distinct orderDate from {$this->settingVars->ferreroIrelandOrdersTable} order by orderDate desc limit $this->timeFrame, $this->timeFrame";
        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
        $this->previousDates = array_column($result, 'orderDate');

    }

}

?>