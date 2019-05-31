<?php

namespace classes\totalmults;

use projectsettings;
use datahelper;
use filters;
use db;
use config;
use utils;

class CategoryPerformance extends config\UlConfig {
       
    
    /*     * ***
     * Default gateway function, should be similar in all DATA CLASS
     * arguments:
     *  $settingVars [project settingsGateway variables]
     * @return $jsonOutput with all data that should be sent to client app
     * *** */

    public function go($settingVars) {
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES
        $this->queryPart = $this->getAll();
		        
        $this->prepareMainGridData(); //ADDING TO OUTPUT

        return $this->jsonOutput;
    }
    
    private function prepareMainGridData() {
				
		$weeks 				= $_REQUEST['timeFrame'];
		$act 				= $_REQUEST['ACCOUNT'];
		$filterBy			= $_REQUEST['filterBy'];
		$account 			= $this->settingVars->dataArray[$act]["NAME"];
		$filterByAccount	= $this->settingVars->dataArray[$filterBy]["NAME"];
		$matchText			= 'SUB-CAT TOTAL';
		
		$items = array();
        $items = explode("#", filters\timeFilter::getLatest_n_dates_ly(0, $weeks, $this->settingVars));
        $latest4Week = ($items[0]) ? $items[0] : 0;
        $previous4Week = ($items[1]) ? $items[1] : 0;
		
		$items = array();
        $items = explode("#", filters\timeFilter::getLatest_n_dates_ly($weeks, $weeks, $this->settingVars));
        $latestPP4Week = ($items[0]) ? $items[0] : 0;
        //$previous4Week = $items[1];
		
		$items = explode("#", filters\timeFilter::getLatest_n_dates_ly(0, 52, $this->settingVars));
        $latest52Week = $items[0];
        //$previous52Week = $items[1];
		
		$query = "SELECT $account AS ACCOUNT " .
                ",SUM((CASE WHEN CONCAT(" . $this->settingVars->yearperiod . "," . $this->settingVars->weekperiod . ") IN (" . $previous4Week . ") AND $filterByAccount = '".$matchText."' THEN 1 ELSE 0 END)*".$this->settingVars->ProjectValue.") AS SC_L4_Fill" .
				",SUM((CASE WHEN CONCAT(" . $this->settingVars->yearperiod . "," . $this->settingVars->weekperiod . ") IN (" . $latest4Week. ") AND $filterByAccount = '".$matchText."' THEN 1 ELSE 0 END)*".$this->settingVars->ProjectValue.") AS SC_T4_Fill" .
                ",SUM((CASE WHEN CONCAT(" . $this->settingVars->yearperiod . "," . $this->settingVars->weekperiod . ") IN (" . $latestPP4Week . ") AND $filterByAccount = '".$matchText."' THEN 1 ELSE 0 END)*".$this->settingVars->ProjectValue.") AS SC_P4_Fill" .
				",SUM((CASE WHEN CONCAT(" . $this->settingVars->yearperiod . "," . $this->settingVars->weekperiod . ") IN (" . $previous4Week . ") AND $filterByAccount != '".$matchText."' THEN 1 ELSE 0 END)*".$this->settingVars->ProjectValue.") AS F_L4_Fill" .
				",SUM((CASE WHEN CONCAT(" . $this->settingVars->yearperiod . "," . $this->settingVars->weekperiod . ") IN (" . $latest4Week. ") AND $filterByAccount != '".$matchText."' THEN 1 ELSE 0 END)*".$this->settingVars->ProjectValue.") AS F_T4_Fill" .
                ",SUM((CASE WHEN CONCAT(" . $this->settingVars->yearperiod . "," . $this->settingVars->weekperiod . ") IN (" . $latestPP4Week . ") AND $filterByAccount != '".$matchText."' THEN 1 ELSE 0 END)*".$this->settingVars->ProjectValue.") AS F_P4_Fill" .
                " FROM " . $this->settingVars->tablename . $this->queryPart .
                " AND CONCAT(" . $this->settingVars->yearperiod . "," . $this->settingVars->weekperiod . ") IN  (" . $latest52Week . ",".$previous4Week.") GROUP BY ACCOUNT HAVING (SC_L4_Fill > 0 AND SC_T4_Fill > 0 AND SC_P4_Fill > 0 AND F_L4_Fill > 0 AND F_T4_Fill > 0 AND F_P4_Fill > 0) ORDER BY SC_T4_Fill DESC";
		//echo $query;exit;
        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
		$arr 		= array();
		$arrChart 	= array();
		if(is_array($result) && !empty($result))
		{
			foreach ($result as $key => $data) {
				$temp 	= array();
				$chart 	= array();
							
				$temp['ACCOUNT']		= $data['ACCOUNT'];		
				$temp['SC_T4_Fill'] 	= (int)$data['SC_T4_Fill'];
				$temp['F_T4_Fill'] 		= (int)$data['F_T4_Fill'];
				
				$temp['sc_pp_var_per'] 	= ($data['SC_P4_Fill'] > 0) ? number_format(((($data['SC_T4_Fill']-$data['SC_P4_Fill'])/$data['SC_P4_Fill'])*100),1) : 0;
				$temp['sc_ly_var_per'] 	= ($data['SC_L4_Fill'] > 0) ? number_format(((($data['SC_T4_Fill']-$data['SC_L4_Fill'])/$data['SC_L4_Fill'])*100),1) : 0;
				$temp['f_pp_var_per'] 	= ($data['F_P4_Fill'] > 0) ? number_format(((($data['F_T4_Fill']-$data['F_P4_Fill'])/$data['F_P4_Fill'])*100),1) : 0;
				$temp['f_ly_var_per'] 	= ($data['F_L4_Fill'] > 0) ? number_format(((($data['F_T4_Fill']-$data['F_L4_Fill'])/$data['F_L4_Fill'])*100),1) : 0;
				
				$temp['pp'] = number_format(($temp['f_pp_var_per'] - $temp['sc_pp_var_per']),1);
				$temp['ly'] = number_format(($temp['f_ly_var_per'] - $temp['sc_ly_var_per']),1);
				
				$chart['ACCOUNT'] = $temp['ACCOUNT'];
				$chart['sc_pp_var_per'] = $temp['sc_pp_var_per'];
				$chart['sc_ly_var_per'] = $temp['sc_ly_var_per'];
				$chart['f_pp_var_per'] 	= $temp['f_pp_var_per'];
				$chart['f_ly_var_per'] 	= $temp['f_ly_var_per'];
				
				$arr[] 		= $temp;
				$arrChart[] = $chart;
			}
		}
		//print("<pre>");print_r($arr);exit;
		$this->jsonOutput['gridData'] 	= $arr;
		$this->jsonOutput['chartData'] 	= $arrChart;
       
    }

}

?> 