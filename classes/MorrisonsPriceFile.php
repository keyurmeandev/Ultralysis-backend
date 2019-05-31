<?php

namespace classes;

use projectsettings;
use db;
use config;
use utils;

class MorrisonsPriceFile extends config\UlConfig {
    /*     * ***
     * Default gateway function, should be similar in all DATA CLASS
     * arguments:
     *  $settingVars [project settingsGateway variables]
     * @return jsonOutput with all data that should be sent to client app
     * *** */

    public function go($settingVars) {
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES
        $this->redisCache = new utils\RedisCache($this->queryVars);
        
        $this->createList();
        
        return $this->jsonOutput;
    }
    
    public function createList() 
    {
        $priceList = $dateArray = $dateList = array();
        
        $fromToDateRange = $this->settingVars->fromToDateRange;
        
        if(isset($_REQUEST['timeFrame']) && $_REQUEST['timeFrame'] != "")
        {
            $seasonalYear = explode("-", $_REQUEST['timeFrame']);
            $fromRange = $seasonalYear[1]."-".$fromToDateRange['fromMonth']."-".$fromToDateRange['fromDay'];
            $toRange = $seasonalYear[1]."-".$fromToDateRange['toMonth']."-".$fromToDateRange['toDay'];
        }
        
        $query = "SELECT ".$this->settingVars->seasonalmorrisonspricetable.".PNAME as PNAME, MAX(".$this->settingVars->skutable.".pname2) as PNAME_ROLLUP, ".$this->settingVars->seasonalmorrisonspricetable.".MYDATE as MYDATE, MAX(PRICE) as PRICE, ".$this->settingVars->seasonalmorrisonspricetable.".GID as GID FROM ".$this->settingVars->seasonalmorrisonspricetable.", ".$this->settingVars->maintable.", ".$this->settingVars->skutable." WHERE ".$this->settingVars->maintable.".PNAME=".$this->settingVars->seasonalmorrisonspricetable.".PNAME AND ".$this->settingVars->maintable.".PNAME=".$this->settingVars->skutable.".pname AND ".$this->settingVars->seasonalmorrisonspricetable.".GID=".$this->settingVars->maintable.".GID and ".$this->settingVars->seasonalmorrisonspricetable.".GID = ".$this->settingVars->morrisonsGid." AND ".$this->settingVars->skutable.".clientID = '".$this->settingVars->clientID."' AND ".$this->settingVars->seasonalmorrisonspricetable.".MYDATE BETWEEN '".$fromRange."' AND '".$toRange."' AND seasonal_year=".$seasonalYear[1]." AND seasonal_description='".$seasonalYear[0]."' GROUP BY PNAME, MYDATE, GID ORDER BY MYDATE";
        // echo $query;exit();
        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }
        
        $accountArray = $accountPname2Array = array();
        if(is_array($result) && !empty($result))
        {
            foreach($result as $key => $data) {
                $accountArray[$data['PNAME']][$data['MYDATE']] = $data['PRICE'];
                $accountPname2Array[$data['PNAME']] = $data['PNAME_ROLLUP'];
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
                        $tmp['PNAME'] = $accountData;
                        $tmp['PNAME_ROLLUP'] = $accountPname2Array[$accountData];
                        $tmp[$mainKey] = (isset($accountArray[$accountData][$date])) ? (double)$accountArray[$accountData][$date] : 0;
                        if($key == 0)
                            $dateList[] = array("MYDATE" => $date,"FORMATED_DATE" => date('j M Y', strtotime($date)));
                    }
                    $priceList[] = $tmp;
                }
            }
        }
        
        $this->jsonOutput['dateList'] = $dateList;
        $this->jsonOutput['priceList'] = $priceList;
    }

}

?>