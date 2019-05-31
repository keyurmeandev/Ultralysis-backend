<?php

namespace classes;

use datahelper;
use projectsettings;
use filters;
use utils;
use db;
use config;
use lib;

class FullYearViewer extends config\UlConfig {

    public function go($settingVars) {

        $this->initiate($settingVars); //INITIATE COMMON VARIABLES
        $this->redisCache = new utils\RedisCache($this->queryVars);

        if ($this->settingVars->isDynamicPage) {
            $this->podsSettings = $this->getPageConfiguration('all_pod_settings', $this->settingVars->pageID);
        }
        
        $this->fetchConfig(); // Fetching filter configuration for page
        
        return $this->jsonOutput;
    }
    
    public function fetchConfig()
    {
        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
            $this->jsonOutput['gridConfig'] = array(
                'enabledFilters' => $this->getPageConfiguration('filter_settings', $this->settingVars->pageID)
            );
        }
        else
            $this->getPodsData();
    }
    
    public function getPodsData()
    {
        //datahelper\Common_Data_Fetching_Functions::$settingVars = $this->settingVars;
        //datahelper\Common_Data_Fetching_Functions::$queryVars   = $this->queryVars;        
        //$measuresFields = datahelper\Common_Data_Fetching_Functions::getAllMeasuresExtraTable();
        
        $this->settingVars->tableUsedForQuery = $measureIDs = $measureTitleArr = array();
        $array_colmns_key = array_column($this->settingVars->pageArray["MEASURE_SELECTION_LIST"],'measureID');

        if(is_array($this->settingVars->measureArray) && !empty($this->settingVars->measureArray)){
            foreach ($this->settingVars->measureArray as $key => $measureVal) {
                if(!in_array($key, $measureIDs) && in_array(str_replace("M", "", $key), $this->podsSettings) ){

                    $measureIDs[] = $key;
                    if (array_key_exists('usedFields', $measureVal) && is_array($measureVal['usedFields']) && !empty($measureVal['usedFields'])) {
                        foreach ($measureVal['usedFields'] as $usedField) {
                            $measureFields[] = $usedField;
                        }
                    }
                    if(in_array(str_replace("M", "", $key), $array_colmns_key)) {
                        $measureNmk = array_search(str_replace("M", "", $key), $array_colmns_key);
                        if(isset($this->settingVars->pageArray["MEASURE_SELECTION_LIST"][$measureNmk]) && isset($this->settingVars->pageArray["MEASURE_SELECTION_LIST"][$measureNmk]['measureName'])){
                            
                            $measureTitleArr[$key] = [
                                'measureName'       => $this->settingVars->pageArray["MEASURE_SELECTION_LIST"][$measureNmk]['measureName'],
                                'dataDecimalPlaces' => $this->settingVars->pageArray["MEASURE_SELECTION_LIST"][$measureNmk]['dataDecimalPlaces']
                            ];

                        }else{
                            $measureTitleArr[$key] = [
                                'measureName'=> $measureVal['ALIASE'],
                                'dataDecimalPlaces'=> 0
                            ];
                        }
                    }
                }
                $keyAlias[$key] = $measureVal['ALIASE'];
            }
        }
        
        $this->prepareTablesUsedForQuery($measureFields);
        $this->settingVars->useRequiredTablesOnly = true;
        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll        

        $options = array();
        $measureSelectionArr = config\MeasureConfigure::prepareSelect($this->settingVars, $this->queryVars, $measureIDs, $options);

        $query = " SELECT " . $this->settingVars->yearperiod . " AS YEAR," . $this->settingVars->weekperiod . " AS WEEK,";
            //$mydateSelect = $this->settingVars->getMydateSelect($this->settingVars->dateField);
            //$query .= ($this->settingVars->dateField !='') ? " ".$mydateSelect . " AS MYDATE, " : "";      
        $query .= implode(",", $measureSelectionArr) . " ";
        $query .= "FROM " . $this->settingVars->tablename . $this->queryPart . " AND 
        (". $this->settingVars->yearperiod ."=" . filters\timeFilter::$FromYear . " OR " .$this->settingVars->yearperiod . "=". (filters\timeFilter::$FromYear-1) . ") " .
                "GROUP BY YEAR,WEEK " .
                "ORDER BY YEAR ASC,WEEK ASC";

        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);

        if(is_array($result) && !empty($result))
        {
            foreach($result as $key => $data)
                $yearResult[$data['YEAR']][] = $data;
                
            $years = array_column($result, "YEAR");
            $lyYear = $years[0];
            $tyYear = $years[count($years)-1];
        }

        $lyData = $yearResult[$lyYear];
        $tyData = $yearResult[$tyYear];

        if(count($lyData) > count($tyData)){
            $allData = $lyData;
        }else{
            $allData = $tyData;
        }

        $finalResult = array(); $pod0_cumTY = $pod0_cumLY = $pod1_cumTY = $pod1_cumLY = 0;
        foreach($allData as $key => $data)
        {
            $tmp = array();
            $tmp['ACCOUNT'] = $this->getAccountMonthYr($data);

            $tylyKey = $keyAlias["M".$this->podsSettings[0]];
                $tmp['TY'.$tylyKey] = isset($tyData[$key]) && isset($tyData[$key][$tylyKey]) ? $tyData[$key][$tylyKey] : 0;
                $tmp['LY'.$tylyKey] = isset($lyData[$key]) && isset($lyData[$key][$tylyKey]) ? $lyData[$key][$tylyKey] : 0;
                $pod0_cumTY += $tmp['TY'.$tylyKey];
                $pod0_cumLY += $tmp['LY'.$tylyKey];
                $tmp['TYLINE'.$tylyKey] = $pod0_cumTY;
                $tmp['LYLINE'.$tylyKey] = $pod0_cumLY;

            $tylyKey = $keyAlias["M".$this->podsSettings[1]];
                $tmp['TY'.$tylyKey] = isset($tyData[$key]) && isset($tyData[$key][$tylyKey]) ? $tyData[$key][$tylyKey] : 0;
                $tmp['LY'.$tylyKey] = isset($lyData[$key]) && isset($lyData[$key][$tylyKey]) ? $lyData[$key][$tylyKey] : 0;
                $pod1_cumTY += $tmp['TY'.$tylyKey];
                $pod1_cumLY += $tmp['LY'.$tylyKey];
                $tmp['TYLINE'.$tylyKey] = $pod1_cumTY;
                $tmp['LYLINE'.$tylyKey] = $pod1_cumLY;

            $finalResult[] = $tmp;
        }
      

        if($this->settingVars->timeSelectionUnit == 'weekMonth'){
            $measuereUnit = "By Month";
        }else if($this->settingVars->timeSelectionUnit == 'weekYear'){
            $measuereUnit = "By Week";
        }

        $this->jsonOutput["chartDataAlise"] = [
                                'POD0'=>[
                                         'alias'=>$keyAlias["M".$this->podsSettings[0]],
                                         'title'=>$measureTitleArr["M".$this->podsSettings[0]]['measureName'].' '.$measuereUnit,
                                         'decimal'=>$measureTitleArr["M".$this->podsSettings[0]]['dataDecimalPlaces']
                                        ],
                                'POD1'=>[
                                         'alias'=>$keyAlias["M".$this->podsSettings[1]],
                                         'title'=>$measureTitleArr["M".$this->podsSettings[1]]['measureName'].' '.$measuereUnit,
                                         'decimal'=>$measureTitleArr["M".$this->podsSettings[1]]['dataDecimalPlaces']
                                        ]
                            ];
        $this->jsonOutput["chartData"] = $finalResult;
    }

    private function getAccountMonthYr($data){
        if($this->settingVars->timeSelectionUnit == 'weekMonth'){
            return date("M", mktime(0, 0, 0, $data['WEEK'], 1, $data['YEAR']));
        }else if($this->settingVars->timeSelectionUnit == 'weekYear'){
            return $data['WEEK'].'-'.$data['YEAR'];
        }
    }
    
}