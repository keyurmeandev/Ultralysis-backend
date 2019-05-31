<?php

namespace classes;

use datahelper;
use projectsettings;
use filters;
use utils;
use db;
use config;
use lib;

class BICSalesTracker extends config\UlConfig {

    public function go($settingVars) {

        $this->initiate($settingVars); //INITIATE COMMON VARIABLES
        $this->redisCache = new utils\RedisCache($this->queryVars);
        $this->fetchConfig(); // Fetching filter configuration for page
        
        return $this->jsonOutput;
    }
    
    public function fetchConfig()
    {
        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
        
            if(!isset($this->settingVars->pageArray["BIC_SALES_TRACKER"]) || empty($this->settingVars->pageArray["BIC_SALES_TRACKER"]))
            {
                $response = array("configuration" => array("status" => "fail", "messages" => array("Page isn't configured properly.")));
				echo json_encode($response);
				exit();                
            }
        
            $this->jsonOutput['gridConfig'] = array(
                'enabledFilters' => $this->getPageConfiguration('filter_settings', $this->settingVars->pageID)
            );
        }
        else
            $this->getChartData();
    }
    
    public function getChartData()
    {
        $this->settingVars->tableUsedForQuery = array();
        $measure = $this->settingVars->pageArray["BIC_SALES_TRACKER"]['M'.$_REQUEST['ValueVolume']];
        
        $queryString = array();
        foreach($measure as $data)
        {
            $queryString[] = "SUM((CASE WHEN " . filters\timeFilter::$tyWeekRange . " THEN 1 ELSE 0 END) * " . $data['FIELD'] . ") AS ".$data['ALIAS']."_TY";
            
            $queryString[] = "SUM((CASE WHEN " . filters\timeFilter::$lyWeekRange . " THEN 1 ELSE 0 END) * " . $data['FIELD'] . ") AS ".$data['ALIAS']."_LY";
            
            $measureFields[] = $data['FIELD'];
        }

        $this->prepareTablesUsedForQuery($measureFields);
        $this->settingVars->useRequiredTablesOnly = true;
        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll
        
        // $query = " SELECT " . $this->settingVars->yearperiod . " AS YEAR," . $this->settingVars->weekperiod . " AS WEEK, ".
        // implode(", ",$queryString); 
        $query = " SELECT " . $this->settingVars->yearperiod . " AS YEAR," . $this->settingVars->weekperiod . " AS WEEK ";
        if( !empty($queryString) )
        $query .= ', '.implode(", ",$queryString); 

        $query .= " FROM " . $this->settingVars->tablename . $this->queryPart . " AND (". filters\timeFilter::$tyWeekRange . " OR " . filters\timeFilter::$lyWeekRange . ") GROUP BY YEAR, WEEK ORDER BY YEAR ASC,WEEK ASC";

        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }        
        
        $finalResult = array();
        if(is_array($result) && !empty($result))
        {
            foreach($result as $key => $data)
            {
                $tmp = array();
                $finalResult[$data['WEEK']]['ACCOUNT'] = $data['WEEK'];
                if($data['YEAR'] == filters\timeFilter::$FromYear)
                {
                    $finalResult[$data['WEEK']]['ACCOUNT_TY'] = $data['YEAR']."-".$data['WEEK'];
                    $finalResult[$data['WEEK']]['INCREMENTAL_TY'] = ($data[$measure["SALES"]["ALIAS"]."_TY"] - $data[$measure["BASELINE"]["ALIAS"]."_TY"]);
                    $finalResult[$data['WEEK']]['BASELINE_TY'] = (double)$data[$measure["BASELINE"]["ALIAS"]."_TY"];
                    $finalResult[$data['WEEK']]['CANNIBALIZATION_TY'] = (double)$data[$measure["CANNIBALIZATION"]["ALIAS"]."_TY"];
                }
                else
                {
                    $finalResult[$data['WEEK']]['ACCOUNT_LY'] = $data['YEAR']."-".$data['WEEK'];
                    $finalResult[$data['WEEK']]['INCREMENTAL_LY'] = ($data[$measure["SALES"]["ALIAS"]."_LY"] - $data[$measure["BASELINE"]["ALIAS"]."_LY"]);
                    $finalResult[$data['WEEK']]['BASELINE_LY'] = (double)$data[$measure["BASELINE"]["ALIAS"]."_LY"];
                    $finalResult[$data['WEEK']]['CANNIBALIZATION_LY'] = (double)$data[$measure["CANNIBALIZATION"]["ALIAS"]."_LY"];
                }
            }
        }
        
        $this->jsonOutput["chartData"] = array_values($finalResult);
    }

    private function getAccountMonthYr($data){
        if($this->settingVars->timeSelectionUnit == 'weekMonth'){
            return date("M", mktime(0, 0, 0, $data['WEEK'], 1, $data['YEAR']));
        }else if($this->settingVars->timeSelectionUnit == 'weekYear'){
            return $data['WEEK'].'-'.$data['YEAR'];
        }
    }
    
}