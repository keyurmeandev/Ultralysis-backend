<?php

namespace classes;

use projectsettings;
use db;
use config;
use utils;
use filters;

class SellThruForecast extends config\UlConfig {
    /*     * ***
     * Default gateway function, should be similar in all DATA CLASS
     * arguments:
     *  $settingVars [project settingsGateway variables]
     * @return jsonOutput with all data that should be sent to client app
     * *** */

    public function go($settingVars) {
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES
        $this->redisCache = new utils\RedisCache($this->queryVars);

        
        $action = $_REQUEST['action'];
        switch ($action) {
            case 'getCustomer':
                $this->getCustomer();
                break;
            case 'getForecastList':
                $this->getForecastList();
                break;
            case 'fetchInlineMarketAndProductFilter':
                $this->fetchInlineMarketAndProductFilterData();
                break;
            default;
                $this->getCustomer();
                $this->fetchInlineMarketAndProductFilterData();
                break;
        }

        return $this->jsonOutput;
    }


    public function fetchInlineMarketAndProductFilterData()
    {
        /*[START] PREPARING THE PRODUCT INLINE FILTER DATA */
        if(isset($this->queryVars->projectConfiguration) && isset($this->queryVars->projectConfiguration['has_product_market_filter_disp_type']) && $this->queryVars->projectConfiguration['has_product_market_filter_disp_type']==1){
            $configureProject = new config\ConfigureProject($this->settingVars, $this->queryVars);
            $configureProject->fetch_all_product_and_marketSelection_data(true); //collecting time selection data
            $extraWhere = filters\timeFilter::$seasonalTimeframeExtraWhere." AND (" .filters\timeFilter::$tyWeekRange. " OR ".filters\timeFilter::$lyWeekRange.") ";
            $this->getAllProductAndMarketInlineFilterData($this->queryVars, $this->settingVars, $extraWhere);
        }
        /*[END] PREPARING THE PRODUCT INLINE FILTER DATA */
    }
    
    public function getCustomer() {
        
        $mainTable  = $this->settingVars->maintable;
        $groupTable =  $this->settingVars->grouptable;

        $query = "SELECT gid as GID, gname as GNAME FROM ".$groupTable." WHERE GID IN(SELECT DISTINCT gid FROM ".$mainTable.") ORDER BY GID";

        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            
        $this->jsonOutput['customerList'] = $result;
    }

    public function getForecastList() 
    {
        $forecastList = $dateArray = $dateList = array();

        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $this->measureFields[] = $this->settingVars->skutable . ".PNAME_ROLLUP2";

        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll

        /*$extraWhere = "";
        if(isset($_REQUEST['GID']) && $_REQUEST['GID'] != "")
        {
            $extraWhere = " AND " . $this->settingVars->maintable . ".GID = ".$_REQUEST['GID'];
        }
        $extraWhere .= " AND ".filters\timeFilter::$tyWeekRange;*/

        $query = "SELECT " . 
            $this->settingVars->maintable . ".PIN as PIN, MAX(" . $this->settingVars->skutable . ".PNAME_ROLLUP2) as PNAME, " . 
            $this->settingVars->maintable . ".mydate as MYDATE,".
            " MAX(" . $this->settingVars->maintable . ".forecast1) as FORECAST, " . $this->settingVars->maintable . ".gid as GID ".
            /*" FROM " . $this->settingVars->maintable . ", " . $this->settingVars->skutable . 
            " WHERE " . $this->settingVars->maintable . ".accountID = '".$this->settingVars->aid."'".
            " AND " . $this->settingVars->maintable . ".gid IN (".$_REQUEST['GID'].")".
            " AND " . $this->settingVars->maintable . ".PIN=" . $this->settingVars->skutable . ".PIN".
            " AND " . $this->settingVars->maintable . ".GID=" . $this->settingVars->skutable . ".GID".
            " AND " . $this->settingVars->skutable . ".hide<>1 AND " . $this->settingVars->skutable . ".gid IN (".$_REQUEST['GID'].")".
            " AND " . $this->settingVars->skutable . ".clientID='".$this->settingVars->clientID."'".
            " GROUP BY PIN, MYDATE, GID ORDER BY MYDATE";*/
            " FROM " . $this->settingVars->tablename .' '. trim($this->queryPart) . 
            filters\timeFilter::$seasonalTimeframeExtraWhere . 
            " AND (" .filters\timeFilter::$tyWeekRange.") ". 
            // " FROM " . $this->settingVars->maintable." WHERE 1=1 ".$extraWhere.
            " GROUP BY PIN, MYDATE, GID ORDER BY MYDATE";

        // echo $query;exit();

        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }
        
        $accountArray = array();
        if(is_array($result) && !empty($result))
        {
            foreach($result as $key => $data) {
                $accountArray[$data['PIN']]['TOTAL'] = $accountArray[$data['PIN']]['TOTAL'] + $data['FORECAST'];
                $accountArray[$data['PIN']][$data['MYDATE']] = $data['FORECAST'];
                $accountArray[$data['PIN']]['PNAME'] = $data['PNAME'];
            }
            
            $dateArray = array_unique(array_column($result, 'MYDATE'));
            if(!empty($accountArray))
            {
                foreach(array_keys($accountArray) as $key => $accountData)
                {
                    $tmp = array();
                    foreach($dateArray as $innerkey => $date)
                    {
                        $mainKey = 'dt'.date('Ymd', strtotime($date));
                        $tmp['PIN'] = $accountData;
                        $tmp[$mainKey] = (isset($accountArray[$accountData][$date])) ? (double)$accountArray[$accountData][$date] : 0;
                        if($key == 0)
                            $dateList[] = array("MYDATE" => $date,"FORMATED_DATE" => date('j M Y', strtotime($date)));
                    }
                    $tmp['TOTAL'] = $accountArray[$accountData]['TOTAL'];
                    $tmp['PNAME'] = $accountArray[$accountData]['PNAME'];
                    $forecastList[] = $tmp;
                }
            }
        }
        $this->jsonOutput['dateList'] = $dateList;
        $this->jsonOutput['forecastList'] = $forecastList;
    }

}

?>