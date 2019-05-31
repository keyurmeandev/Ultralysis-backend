<?php

namespace classes;

use db;
use config;
use filters;
use datahelper;
use utils;

class CannibalisationTracker extends config\UlConfig {
    /*     * ***
     * Default gateway function, should be similar in all DATA CLASS
     * arguments:
     *  $settingVars [project settingsGateway variables]
     * @return $xmlOutput with all data that should be sent to client app
     * *** */

    public function go($settingVars) {
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES
        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll

        $action = $_REQUEST["action"];
        $this->redisCache = new utils\RedisCache($this->queryVars);

		if ($this->settingVars->isDynamicPage) {
			$this->skuField = $this->getPageConfiguration('sku_field', $this->settingVars->pageID)[0];
			
			$buildDataFields = array($this->skuField);
			
            $this->buildDataArray($buildDataFields);
            $this->buildPageArray();
		
            if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
                $configurationCheck = new config\ConfigurationCheck($this->settingVars, $this->queryVars);
                if(in_array("sku-selection", $this->jsonOutput['pageConfig']['enabledFilters']) && !$configurationCheck->settings['has_sku_filter'])
                {
                    $response = array("configuration" => array("status" => "fail", "messages" => array("Page isn't configured properly.")));
                    echo json_encode($response);
                    exit();
                }
            }
        
		}

        switch ($action) {
            case "getChartData":
                $this->getChartData();
                break;
        }

        return $this->jsonOutput;
    }

    function getChartData() 
    {
        datahelper\Common_Data_Fetching_Functions::$settingVars = $this->settingVars;
        datahelper\Common_Data_Fetching_Functions::$queryVars = $this->queryVars;

        $measuresFields = datahelper\Common_Data_Fetching_Functions::getMeasuresExtraTable();
        $measureSelectRes = $this->prepareMeasureSelectPart();
        $this->measureFields = $measureSelectRes['measureFields'];
        
        if(isset($_REQUEST['skuField']) && !empty($_REQUEST['skuField']) )
        {
            $skuIdField  = $_REQUEST['skuField'];
            $this->buildDataArray(array($skuIdField),true);
            $gridFieldPart = explode("#", $skuIdField);
            $accountField = strtoupper($this->dbColumnsArray[$gridFieldPart[0]]);
            $accountField = (count($gridFieldPart) > 1) ? strtoupper($accountField."_".$this->dbColumnsArray[$gridFieldPart[1]]) : $accountField;
            
            $accountID = (isset($this->settingVars->dataArray[$accountField]) && isset($this->settingVars->dataArray[$accountField]['ID'])) ? $this->settingVars->dataArray[$accountField]['ID'] : $this->settingVars->dataArray[$accountField]['NAME'];
            
            $this->skuIdField = $accountID;
            $this->skuNameField = $this->settingVars->dataArray[$accountField]['NAME'];
        }
            
        $this->measureFields[] = $this->skuIdField;
        $this->measureFields[] = $this->skuNameField;
        $this->prepareTablesUsedForQuery($this->measureFields);
        $this->settingVars->useRequiredTablesOnly = true;
        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll

        $selectPart[] = $this->skuIdField ." AS SKU_ID ";
        $selectPart[] = $this->skuNameField." as SKU_NAME ";
        $groupByPart[] = "SKU_ID";
        $groupByPart[] = "SKU_NAME";
        
        $isIncludedID = ($this->skuIdField != $this->skuNameField) ? true : false;
        
        $measureSelectionArr = $measureSelectRes['measureSelectionArr'];
        $havingTYValue = $measureSelectRes['havingTYValue'];
        
        $query = " SELECT " . $this->settingVars->yearperiod . " AS YEAR," . $this->settingVars->weekperiod . " AS WEEK,";
        $mydateSelect = $this->settingVars->getMydateSelect($this->settingVars->dateField);
        $query .= ($this->settingVars->dateField !='') ? " ".$mydateSelect . " AS MYDATE, " : "";
        
        $query .= (!empty($selectPart)) ? implode(",", $selectPart). ", " : " ";
        $query .= implode(",", $measureSelectionArr) . " ";
        $query .= "FROM " . $this->settingVars->tablename . $this->queryPart . " AND (" . filters\timeFilter::$tyWeekRange . ") " .
            "GROUP BY YEAR,WEEK " .((!empty($groupByPart)) ? ",".implode(",", $groupByPart)." ": " ").
            "ORDER BY YEAR ASC,WEEK ASC";
        
        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }
        
        $requiredGridFields = array("MYDATE", "SKU_ID", "SKU_NAME", "WEEK", "YEAR", $havingTYValue);
        $result = $this->redisCache->getRequiredData($result, $requiredGridFields);
        
        $selectedSkus = $lineChartData = array();
        if(is_array($result) && !empty($result))
        {
            foreach($result as $key => $data)
            {
                $data['MYDATE'] = date('j M y', strtotime($data['MYDATE']));
                $data['SKU_NAME'] = ($isIncludedID) ? $data['SKU_NAME']." (".$data['SKU_ID'].")" : $data['SKU_NAME'];
                
                if(!in_array($data['SKU_NAME'], $selectedSkus))
                    $selectedSkus[$data['SKU_ID'].$data['SKU_NAME']] = $data['SKU_NAME'];
                    
                $lineChartData[$data['SKU_ID'].$data['SKU_NAME']][] = $data;
            }
        }
        
        $maxLength = array();
        
        foreach($lineChartData as $key => $data)
            $maxLength[$key] = count($data);
        
        $value = max($maxLength);
        $maxLength = array_search($value, $maxLength);
        
        $this->jsonOutput['LineChart'] = $lineChartData;
        $this->jsonOutput['selectedSkus'] = $selectedSkus;
        $this->jsonOutput['maxLength'] = $maxLength;
    }

    public function buildDataArray($fields) {
        if (empty($fields))
            return false;

        $configurationCheck = new config\ConfigurationCheck($this->settingVars, $this->queryVars);
        $configurationCheck->buildDataArray($fields);
        $this->dbColumnsArray = $configurationCheck->dbColumnsArray;
        $this->displayCsvNameArray = $configurationCheck->displayCsvNameArray;
        $this->displayDbColumnArray = $configurationCheck->displayDbColumnArray;
        return;
    }	
	
	public function buildPageArray() {

        $fetchConfig = false;
        $skuFieldPart = explode("#", $this->skuField);
        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
            $fetchConfig = true;
            $this->jsonOutput['pageConfig'] = array(
                'enabledFilters' => $this->getPageConfiguration('filter_settings', $this->settingVars->pageID)
            );

            $this->jsonOutput['gridColumns']['SKU_ID'] =  (count($skuFieldPart) > 1) ? $this->displayCsvNameArray[$skuFieldPart[1]] : $this->displayCsvNameArray[$skuFieldPart[0]];
            $this->jsonOutput['gridColumns']['SKU_NAME'] =  $this->displayCsvNameArray[$skuFieldPart[0]];
            
        }
       
        $skuField = strtoupper($this->dbColumnsArray[$skuFieldPart[0]]);
        $skuField = (count($skuFieldPart) > 1) ? strtoupper($skuField . "_" . $this->dbColumnsArray[$skuFieldPart[1]]) : $skuField;

        $this->skuIdField = (isset($this->settingVars->dataArray[$skuField]) && isset($this->settingVars->dataArray[$skuField]['ID'])) ? 
            $this->settingVars->dataArray[$skuField]['ID'] : $this->settingVars->dataArray[$skuField]['NAME'];
        $this->skuNameField = $this->settingVars->dataArray[$skuField]['NAME'];
        return;
    }
	    
    
}

?>