<?php

namespace classes\relayplus;

use datahelper;
use projectsettings;
use filters;
use db;
use config;

class OrderedVsDelivered extends config\UlConfig {
    /*     * ***
     * go()
     * Default gateway function, should be similar in all DATA CLASS
     * arguments:
     * $settingVars [project settingsGateway variables]
     * @return $this->jsonOutput with all data that should be sent to client app
     * *** */

    public function go($settingVars) {
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES
        $this->redisCache = new \utils\RedisCache($this->queryVars);
        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll
        $action = $_REQUEST['action'];

        $this->pinField = $this->settingVars->skutable.".PIN";
		$this->pnameField = $this->settingVars->skutable.".PNAME";
        $this->maintable = $this->settingVars->maintable;
        $this->timetable = $this->settingVars->multsSummaryAsdaOnlinePeriodTable;
        $this->ferreroAsdaOnlineTable = $this->settingVars->ferreroAsdaOnlineTable;
        $this->skutable = $this->settingVars->skutable;
        
        switch ($action) {
            case 'orderedVsDeliveredGrid':
                $this->orderedVsDeliveredGrid();
                break;
            case 'getPinData':
                $this->getPinData();
                break;
        }

        return $this->jsonOutput;
    }
    
    public function getMultsSummaryAsdaOnlinePeriodTableData()
    {
        $query = "SELECT * FROM ".$this->settingVars->multsSummaryAsdaOnlinePeriodTable;

        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $periodListResult = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($periodListResult);
        } else {
            $periodListResult = $redisOutput;
        }
        
        $periodList = array();
        if (is_array($periodListResult) && !empty($periodListResult)) 
        {
            foreach($periodListResult as $data)
                $periodList[$data['asda_online_period']] = $data['mults_summary_period'];
        }

        return $periodList;
    }
    
    public function getAsdaOnlinePeriodData()
    {
        $periodList = $this->getMultsSummaryAsdaOnlinePeriodTableData();
        
        $query = "SELECT DISTINCT period as PERIOD FROM ".$this->ferreroAsdaOnlineTable." WHERE ".$this->ferreroAsdaOnlineTable.".gid = ".$this->settingVars->GID." AND ".$this->ferreroAsdaOnlineTable.".accountID = ".$this->settingVars->aid." ORDER BY PERIOD DESC";
        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        
        if ($redisOutput === false) {
            $asdaPeriodList = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($asdaPeriodList);
        } else {
            $asdaPeriodList = $redisOutput;
        }
        
        if (is_array($asdaPeriodList) && !empty($asdaPeriodList)) {
            $this->ytdAsdaPeriodList = array_column($asdaPeriodList, "PERIOD");
            $this->l13AsdaPeriodList = array_slice($this->ytdAsdaPeriodList, 0, 13, true);
            $this->l4AsdaPeriodList  = array_slice($this->ytdAsdaPeriodList, 0, 4, true);
            
            $multsSummaryPeriodList  = array();
            foreach($this->ytdAsdaPeriodList as $data)
                $multsSummaryPeriodList[] = $periodList[$data];
            
            $this->ytdSummaryPeriodList = $multsSummaryPeriodList;
            $this->l13SummaryPeriodList = array_slice($multsSummaryPeriodList, 0, 13, true);
            $this->l4SummaryPeriodList  = array_slice($multsSummaryPeriodList, 0, 4, true);
        }
    }
    
    private function getPinData()
    {
        filters\timeFilter::getLatestWeek($this->settingVars);
        $this->getAsdaOnlinePeriodData();

        /*$query = "SELECT ".$this->timetable.".asda_online_period as PERIOD, " . 
        "SUM((CASE WHEN ".$this->maintable.".period IN (". implode(",",$this->ytdSummaryPeriodList).") THEN 1 ELSE 0 END) * ".$this->maintable.".qty) as VOLUME_ALL_YTD ".
        "FROM ".$this->maintable.", ".$this->timetable.
        " WHERE ".$this->maintable.".accountID = ".$this->settingVars->aid." AND ".
        $this->maintable.".period = ". $this->timetable.".mults_summary_period AND ".
        $this->maintable.".gid = ".$this->settingVars->GID." AND ".
        $this->maintable.".period IN (". implode(",",$this->ytdSummaryPeriodList).") AND ".$this->maintable.".project_type = 'mults_summary' AND ".$this->maintable.".PIN = '".$_REQUEST['clickedPin']."' GROUP BY PERIOD ORDER BY PERIOD ";
        
        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }
        
        $totalSalesData = $summaryPinQtySum = array();
        if (is_array($result) && !empty($result)) {
            foreach($result as $data)
            {
                $totalSalesData[] = (double)$data['VOLUME_ALL_YTD'];
                $summaryPinQtySum[$data['PERIOD']] = array("VOLUME_ALL_YTD" => $data['VOLUME_ALL_YTD']);
            }
        }
        
        $this->jsonOutput['totalSalesData'] = $totalSalesData;*/
        
        $query = "SELECT ".$this->ferreroAsdaOnlineTable.".period as PERIOD, ".
        "SUM((CASE WHEN ".$this->ferreroAsdaOnlineTable.".period IN (". implode(",",$this->ytdAsdaPeriodList).") AND measure_type = 'ONLINE_ORDERED_VOLUME' THEN 1 ELSE 0 END) * ".$this->ferreroAsdaOnlineTable.".value) as ONLINE_ORDERED_YTD, ".
        "SUM((CASE WHEN ".$this->ferreroAsdaOnlineTable.".period IN (". implode(",",$this->ytdAsdaPeriodList).") AND measure_type = 'ONLINE_DELIVERED_VOLUME' THEN 1 ELSE 0 END) * ".$this->ferreroAsdaOnlineTable.".value) as ONLINE_DELIVERED_YTD ".
        "FROM ".$this->ferreroAsdaOnlineTable.", ".$this->skutable." WHERE ".$this->ferreroAsdaOnlineTable.".pin = ".$this->pinField." AND ".$this->ferreroAsdaOnlineTable.".gid = ".$this->skutable.".gid AND ".$this->ferreroAsdaOnlineTable.".gid = ".$this->settingVars->GID." AND ".$this->skutable.".gid = ".$this->settingVars->GID." AND ".$this->skutable.".clientID = '".$this->settingVars->clientID."' AND ".
        $this->settingVars->measureTypeField." IN ('ONLINE_DELIVERED_VOLUME','ONLINE_ORDERED_VOLUME') AND ".$this->ferreroAsdaOnlineTable.".period IN (". implode(",",$this->ytdAsdaPeriodList).") AND ".$this->ferreroAsdaOnlineTable.".pin = ".$_REQUEST['clickedPin']." GROUP BY PERIOD ORDER BY PERIOD";
        
        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }
        
        $totalSalesData = $onlineOrderedData = $percentageColumnChartData = array();
        if (is_array($result) && !empty($result)) {
            foreach($result as $key => $data) {
                //$result[$key]['VOLUME_ALL_YTD'] = (double)$summaryPinQtySum[$data['PERIOD']]['VOLUME_ALL_YTD'];
                $totalSalesData[] = (double)$data['ONLINE_ORDERED_YTD'];
                $onlineOrderedData[] = (double)$data['ONLINE_DELIVERED_YTD'];
                $percentageColumnChartData[] = ($data['ONLINE_ORDERED_YTD'] > 0) ? ( ($data['ONLINE_DELIVERED_YTD']/$data['ONLINE_ORDERED_YTD'] )*100) : 0;
            }
        }
        $this->jsonOutput['totalSalesData'] = $totalSalesData;
        $this->jsonOutput['onlineOrderedData'] = $onlineOrderedData;
        $this->jsonOutput['percentageColumnChartData'] = $percentageColumnChartData;
    }
    
    /**
     * orderedVsDeliveredGrid()
     * This Function is used to retrieve list based on set parameters     
     * @access private  
     * @return $this->jsonOutput with all data that should be sent to client app     
     */
    private function orderedVsDeliveredGrid() {
        
        filters\timeFilter::getLatestWeek($this->settingVars);
        
        $this->getAsdaOnlinePeriodData();
        
       /*$query = "SELECT DISTINCT ".$this->maintable.".PIN as PIN, ".
        "SUM((CASE WHEN ".$this->maintable.".period IN (". implode(",",$this->ytdSummaryPeriodList).") THEN 1 ELSE 0 END) * ".$this->maintable.".qty) as VOLUME_ALL_YTD, ".
        "SUM((CASE WHEN ".$this->maintable.".period IN (". implode(",",$this->l13SummaryPeriodList).") THEN 1 ELSE 0 END) * ".$this->maintable.".qty) as VOLUME_ALL_L13, ".
        "SUM((CASE WHEN ".$this->maintable.".period IN (". implode(",",$this->l4SummaryPeriodList).") THEN 1 ELSE 0 END) * ".$this->maintable.".qty) as VOLUME_ALL_L4 ".
        "FROM ".$this->maintable." ".
        "WHERE ".$this->maintable.".accountID = ".$this->settingVars->aid." AND ".
        $this->maintable.".gid = ".$this->settingVars->GID." AND ".
        $this->maintable.".period IN (". implode(",",$this->ytdSummaryPeriodList).") AND ".$this->maintable.".project_type = 'mults_summary' ";
        
        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }
        
        $summaryPinQtySum = array();
        if (is_array($result) && !empty($result)) {
            foreach($result as $data)
            {
                $summaryPinQtySum[$data['PIN']] = array("VOLUME_ALL_YTD" => $data['VOLUME_ALL_YTD'], "VOLUME_ALL_L13" => $data['VOLUME_ALL_L13'], "VOLUME_ALL_L4" => $data['VOLUME_ALL_L4']);
            }
        }*/
        
        $query = "SELECT DISTINCT ".$this->pinField." as PIN, 
                 ".$this->pnameField." as PNAME, 
                 ".$this->ferreroAsdaOnlineTable.".dept_name as DEPARTMENT, ".
        "SUM((CASE WHEN ".$this->ferreroAsdaOnlineTable.".period IN (". implode(",",$this->ytdAsdaPeriodList).") AND measure_type = 'ONLINE_ORDERED_VOLUME' THEN 1 ELSE 0 END) * ".$this->ferreroAsdaOnlineTable.".value) as ONLINE_ORDERED_YTD, ".
        "SUM((CASE WHEN ".$this->ferreroAsdaOnlineTable.".period IN (". implode(",",$this->l13AsdaPeriodList).") AND measure_type = 'ONLINE_ORDERED_VOLUME' THEN 1 ELSE 0 END) * ".$this->ferreroAsdaOnlineTable.".value) as ONLINE_ORDERED_L13, ".
        "SUM((CASE WHEN ".$this->ferreroAsdaOnlineTable.".period IN (". implode(",",$this->l4AsdaPeriodList).") AND measure_type = 'ONLINE_ORDERED_VOLUME' THEN 1 ELSE 0 END) * ".$this->ferreroAsdaOnlineTable.".value) as ONLINE_ORDERED_L4, ".
        "SUM((CASE WHEN ".$this->ferreroAsdaOnlineTable.".period IN (". implode(",",$this->ytdAsdaPeriodList).") AND measure_type = 'ONLINE_DELIVERED_VOLUME' THEN 1 ELSE 0 END) * ".$this->ferreroAsdaOnlineTable.".value) as ONLINE_DELIVERED_YTD, ".
        "SUM((CASE WHEN ".$this->ferreroAsdaOnlineTable.".period IN (". implode(",",$this->l13AsdaPeriodList).") AND measure_type = 'ONLINE_DELIVERED_VOLUME' THEN 1 ELSE 0 END) * ".$this->ferreroAsdaOnlineTable.".value) as ONLINE_DELIVERED_L13, ".
        "SUM((CASE WHEN ".$this->ferreroAsdaOnlineTable.".period IN (". implode(",",$this->l4AsdaPeriodList).") AND measure_type = 'ONLINE_DELIVERED_VOLUME' THEN 1 ELSE 0 END) * ".$this->ferreroAsdaOnlineTable.".value) as ONLINE_DELIVERED_L4 ".
        "FROM ".$this->ferreroAsdaOnlineTable.", ".$this->skutable." WHERE ".$this->ferreroAsdaOnlineTable.".pin = ".$this->pinField." AND ".$this->ferreroAsdaOnlineTable.".gid = ".$this->skutable.".gid AND ".$this->ferreroAsdaOnlineTable.".gid = ".$this->settingVars->GID." AND ".$this->skutable.".gid = ".$this->settingVars->GID." AND ".$this->skutable.".clientID = '".$this->settingVars->clientID."' AND ".
        $this->settingVars->measureTypeField." IN ('ONLINE_DELIVERED_VOLUME','ONLINE_ORDERED_VOLUME') AND ".$this->ferreroAsdaOnlineTable.".period IN (". implode(",",$this->ytdAsdaPeriodList).")";
        
        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }
        
        if (is_array($result) && !empty($result)) {
        
            foreach($result as $key => $data)
            {
                /*$result[$key]['ONLINE_ORDERED_YTD'] = (double)$summaryPinQtySum[$data['PIN']]['VOLUME_ALL_YTD'];
                $result[$key]['ONLINE_ORDERED_L13'] = (double)$summaryPinQtySum[$data['PIN']]['VOLUME_ALL_L13'];
                $result[$key]['ONLINE_ORDERED_L4'] = (double)$summaryPinQtySum[$data['PIN']]['VOLUME_ALL_L4'];*/
                
                $result[$key]['SHARE_ONLINE_YTD'] = ($result[$key]['ONLINE_ORDERED_YTD'] > 0) ? (($data['ONLINE_DELIVERED_YTD']*100)/$result[$key]['ONLINE_ORDERED_YTD']) : 0;
                $result[$key]['SHARE_ONLINE_L13'] = ($result[$key]['ONLINE_ORDERED_L13'] > 0) ? (($data['ONLINE_DELIVERED_L13']*100)/$result[$key]['ONLINE_ORDERED_L13']) : 0;
                $result[$key]['SHARE_ONLINE_L4'] = ($result[$key]['ONLINE_ORDERED_L4'] > 0) ? (($data['ONLINE_DELIVERED_L4']*100)/$result[$key]['ONLINE_ORDERED_L4']) : 0;
            }
        }
        
        $this->jsonOutput['gridData'] = $result;
    }

}

?>