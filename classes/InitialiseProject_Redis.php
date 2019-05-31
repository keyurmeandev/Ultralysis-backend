<?php

namespace classes;

use datahelper;
use projectsettings;
use filters;
use utils;
use db;
use config;

class InitialiseProject extends config\UlConfig {
    /*     * ***
     * Default gateway function, should be similar in all DATA CLASS
     * arguments:
     *  $settingVars [project settingsGateway variables]
     * @return jsonOutput with all data that should be sent to client app
     * *** */

    public $projectType;     
     
    public function go($settingVars) {
        $redis = new \Redis();
        $redis->connect('127.0.0.1', 6379);
        $redis->auth('ultralysislondon');
        $redis->select($settingVars->projectID); //set Database for redis data
        $requestUrl = md5('http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . "{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}");

        /*if($redis->exists($settingVars->projectID)){
            $this->jsonOutput = json_decode($redis->get($settingVars->projectID));
            return $this->jsonOutput;
        }*/

        if($redis->exists($requestUrl)){
            $this->jsonOutput = json_decode($redis->get($requestUrl));
            return $this->jsonOutput;
        }

        $this->initiate($settingVars); //INITIATE COMMON VARIABLES

        $this->projectType = $_REQUEST['projectType'];
        
        switch ($this->projectType)
        {
            case 'tesco-store-daily':
                if($_REQUEST['SIF'] == "YES")
                {
                    $this->checkConfiguration();
                    $this->getAllSkus();
                    $this->getCluster();
                    $this->getLatestDateOfMainProject();
                    $this->fetch_all_timeSelection_data();
                    unset($this->jsonOutput['selectedIndexFrom'], $this->jsonOutput['selectedIndexTo']);
                }
                else
                {
                    if(isset($this->settingVars->clientLogo) && $this->settingVars->clientLogo != '')
                        $this->jsonOutput['clientLogo']         = $this->settingVars->clientLogo;
                    else
                        $this->jsonOutput['clientLogo']         = 'no-logo.jpg';                
                
                    $this->getSkuSelectionList();
                
                    $this->getLatestDateOfMultsProject();
                    $this->fetch_all_timeSelection_data();
                }
            break;
            case 'relayplus':
                if($_REQUEST['SIF'] == "YES")
                {
                    $this->getRegions();
                    $this->getAllSkus();
                    $this->getLastMyDate();
                    $this->getCluster();
                    $this->fetch_all_timeSelection_data();
                    unset($this->jsonOutput['selectedIndexFrom'], $this->jsonOutput['selectedIndexTo']);

                    return $this->jsonOutput;
                }
                else
                {
                    $this->jsonOutput['weekRangeList']  = $this->settingVars->weekRangeList;
                    $this->jsonOutput['dayList']        = $this->settingVars->dayList;
                    $this->jsonOutput['daysList']       = $this->settingVars->daysList;

                    $this->jsonOutput['footerCompanyName']  = (isset($this->settingVars->footerCompanyName)) ? $this->settingVars->footerCompanyName : '';       
                    $this->jsonOutput['projectID']          = utils\Encryption::encode($this->settingVars->projectID);
                    $this->jsonOutput['sifProjectID']          = utils\Encryption::encode($this->settingVars->sifProjectID);
                    
                    if(isset($this->settingVars->clientLogo) && $this->settingVars->clientLogo != '')
                        $this->jsonOutput['clientLogo']         = $this->settingVars->clientLogo;
                    else
                        $this->jsonOutput['clientLogo']         = 'no-logo.jpg';
                    
                    if(isset($this->settingVars->retailerLogo) && $this->settingVars->retailerLogo != '')
                        $this->jsonOutput['retailerLogo']       = $this->settingVars->retailerLogo;
                    else
                        $this->jsonOutput['retailerLogo']       = 'no-logo.jpg';    

                    if(isset($this->settingVars->default_load_pageID) && !empty($this->settingVars->default_load_pageID))
                        $this->jsonOutput['default_load_pageID'] = $this->settingVars->default_load_pageID;

                    $this->getSkuSelectionList();
                   
                    $this->getLastMyDate();
                    $this->fetch_all_timeSelection_data();
                }
            break;

            case 'lcl':
                $this->fetch_all_timeSelection_data();
                break;
        }

        //$redis->set($settingVars->projectID,json_encode($this->jsonOutput));
        $redis->set($requestUrl,json_encode($this->jsonOutput));
        return $this->jsonOutput;
    }

    public function checkConfiguration(){

        $configurationCheck = new config\ConfigurationCheck($this->settingVars, $this->queryVars);
        $configurationCheck->checkClusterConfiguration();

        return;
    }    
    
    /** ***
     * COLLECTS ALL TIME SELECTION DATA HELPERS AND CONCATE THEM WITH GLOBAL $jsonOutput 
     * *** 
     */
    public function fetch_all_timeSelection_data() {
        $timeSelectionDataCollectors = new datahelper\Time_Selection_DataCollectors($this->settingVars);
        //COLLECT TIME SELECTION DATA
        switch ($this->settingVars->timeSelectionUnit) {
            case 'days':
                $timeSelectionDataCollectors->getOnlyDays($this->jsonOutput, 'days_');
                break;
			case 'weekYear':
				filters\timeFilter::getYTD($this->settingVars); //ALTERNATIVE OF getSlice WHEN THE PROJECT IS LOADING FOR FIRST TIME
				$timeSelectionDataCollectors->getAllWeek_with_future_dates($this->jsonOutput, 'with_future_');
				break;
        }
    }    
    
    /**
     * getAllSkus()
     * It will list all skus
     * 
     * @return void
     */
    public function getAllSkus()
    {
        $skuid = $this->settingVars->skuDataSetting['ID'];
        $sku = $this->settingVars->skuDataSetting['NAME'];
        $skuTable = $this->settingVars->skuDataSetting['tablename'];
        $skuLink = $this->settingVars->skuDataSetting['link'];
        
        $query = "SELECT DISTINCT ". $skuid . " AS SKUID" .
            ", TRIM(" . $sku . ") AS SKU".
            " FROM ". $skuTable . $skuLink .
            " GROUP BY SKUID, SKU ORDER BY SKU ASC";        
        //echo $query;exit;
        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
        
        $allSkus    = array();
        
        if($this->projectType == "relayplus")
        {
            foreach($result as $key => $data)
            {
                $temp           = array();
                $temp['data']   = $data['SKUID'];
                $temp['label']  = $data['SKU']." #".$data['SKUID'];
                $allSkus[]  = $temp;
            }
            $this->jsonOutput['skuListRetailLink'] = $allSkus;
        }
        
        if($this->projectType == "tesco-store-daily")
        {
            $allSkus[] = array("data" => "all", "label" => "ALL");
            foreach($result as $key => $data)
            {
                $tmp = array();
                $tmp['data'] = $data['SKUID'];
                $tmp['label'] = trim($data['SKU'])." {".$data['SKUID']."}";
                $allSkus[] = $tmp;
            }
            $this->jsonOutput['skuList'] = $allSkus;
        }
    }
    
    /**
     * getCluster()
     * It will list all clusters
     * 
     * @return void
     */
    public function getCluster()
    {
        $clusterList = array();
        $query = "SELECT * FROM ".$this->settingVars->configTable." WHERE accountID=".$this->settingVars->aid." AND projectID = ".$this->settingVars->projectID." AND setting_name = 'has_cluster' AND setting_value = 1";
        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->projectManagerLinkid, db\ResultTypes::$TYPE_OBJECT);
        if(!empty($result) && is_array($result))
        {
			$query = "SELECT * FROM ".$this->settingVars->clustertable." WHERE projectType=".$this->settingVars->projectType;
			$clusters = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->projectManagerLinkid, db\ResultTypes::$TYPE_OBJECT);

            if(!empty($clusters) && is_array($clusters))
            {
                $clusterData = array();
                foreach($clusters as $data)
                    $clusterData[$data['cl']] = $data['cl_name'];
            }
            
            $query = "SELECT * FROM ".$this->settingVars->configTable." WHERE accountID=".$this->settingVars->aid." AND projectID = ".$this->settingVars->projectID." AND setting_name = 'cluster_default_load'";
            $defaultLoad = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->projectManagerLinkid, db\ResultTypes::$TYPE_OBJECT);
            if(!empty($defaultLoad) && is_array($defaultLoad))
                $defaultLoad = $defaultLoad[0]['setting_value'];
            
            $_REQUEST['clusterID'] = $defaultLoad;
            $this->settingVars->setCluster();
            
            $query = "SELECT setting_value FROM ".$this->settingVars->configTable." WHERE accountID = ".$this->settingVars->aid." AND projectID = ".$this->settingVars->projectID." AND setting_name = 'cluster_settings' ";
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->projectManagerLinkid, db\ResultTypes::$TYPE_OBJECT);
            
            if(!empty($result) && is_array($result) && $result[0]['setting_value'] != "")
            {
                $settings = explode("|", $result[0]['setting_value']);
                foreach($settings as $data)
                {
                    $tmp = array();
                    $tmp['cl'] = $data;
                    $tmp['CLUSTER'] = $clusterData[$data];
                    
                    if($defaultLoad == $data)
                        $tmp['defaultLoad'] = 1;
                    else
                        $tmp['defaultLoad'] = 0;
                    
                    $clusterList[] = $tmp;
                }
            }
        }

        $this->jsonOutput['CLUSTER_LIST'] = $clusterList;
    }
    
    function getLatestDateOfMainProject() {
        $query = "SELECT MAX(mydate) AS LATESTDATE " .
                "FROM " . $this->settingVars->maintable;

        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);

        $this->jsonOutput['LatestMainDate'] = date("d M Y", strtotime($result[0]['LATESTDATE']));
    }

    function getLatestDateOfMultsProject() {
        if (isset($this->settingVars->latestDateHelperTables) && !empty($this->settingVars->latestDateHelperTables) &&
            isset($this->settingVars->latestDateHelperLink) && !empty($this->settingVars->latestDateHelperLink) ) {

            $query = "SELECT MAX(" . $this->settingVars->timetable . ".mydate) AS LATESTDATE, MAX(" . $this->settingVars->timetable . ".period) as PERIOD " .
                    "FROM " . $this->settingVars->latestDateHelperTables . $this->settingVars->latestDateHelperLink;

            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);

            $this->jsonOutput['LatestMultsDate'] = $result[0]['PERIOD'] . " (w/e " . date("D d M Y", strtotime($result[0]['LATESTDATE'])) . ")";
        }
    }

    public function getSkuSelectionList()
    {
        $skuList = $this->jsonOutput['settings']['sku_settings'];
        $skuDefaultLoad = $this->jsonOutput['settings']['sku_default_load'];
        
        if($this->jsonOutput['settings']['has_sku_filter'])
        {
            if($skuList != "" && $skuDefaultLoad != "")
            {
                $settings = explode("|", $skuList);
                foreach($settings as $key => $field)
                    $fields[] = $field;
                
                $this->buildDataArray($fields, false);
                $tmpArr = array();
                
                foreach($settings as $key => $field)
                {
                    $fieldPart = explode("#", $field);
                    $fieldName = (count(explode(".", $fieldPart[0])) > 1 ) ? strtoupper($fieldPart[0]) : strtoupper("product.".$fieldPart[0]);
                    $fieldName2 = (count($fieldPart) > 1 && count(explode(".", $fieldPart[1])) > 1) ? strtoupper($fieldPart[1]) : ((count($fieldPart) > 1) ? strtoupper("product.".$fieldPart[1]) : "" );
                    $fieldName = (!empty($fieldName2)) ? $fieldName . "_" . $fieldName2 : $fieldName;
                
                    if($fieldPart[0] == $skuDefaultLoad)
                        $selected = true;
                    else
                        $selected = false;
                    
                    $data = "product.".$this->settingVars->dataArray[$fieldName]['NAME_CSV'];
                    $data = (array_key_exists("ID",$this->settingVars->dataArray[$fieldName])) ? $data."#product.".$this->settingVars->dataArray[$fieldName]['ID_CSV'] : $data;
                    
                    $tmpArr[] = array('label' => $this->settingVars->dataArray[$fieldName]['NAME_CSV'], 'data' => $data, 'selected' => $selected);
                }
            }
        }
        else if(in_array("sku-selection", $this->jsonOutput['gridConfig']['enabledFilters']) && !$this->jsonOutput['settings']['has_sku_filter'])
        {
            $response = array("configuration" => array("status" => "fail", "messages" => array("Page isn't configured properly.")));
            echo json_encode($response);
            exit();
        }
        else
        {
            $bottomGridField = $this->getPageConfiguration('bottom_grid_field', $this->settingVars->pageID)[0];
			$this->buildDataArray(array($bottomGridField),true);

            $bottomGridFieldPart = explode("#", $bottomGridField);
			
            $tmpArr[] = array('label' => $this->displayCsvNameArray[$bottomGridFieldPart[0]], 'data' => $bottomGridField, 'selected' => true);
        }
        $this->jsonOutput['skuSelectionList'] = $tmpArr;
    }    
  
    private function getLastMyDate() {
        $selectPart = "";
        if(isset($_REQUEST['SIF']) && $_REQUEST['SIF'] == "YES")
            $selectPart = $this->settingVars->DatePeriod;
        else
            $selectPart = $this->settingVars->timetable . ".mydate";
        
        $query = "SELECT MAX(" . $selectPart . ") AS mydate FROM " . $this->settingVars->timeHelperTables . $this->settingVars->timeHelperLink;
        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
        
        if(!isset($_REQUEST['SIF']))
            $this->jsonOutput['myProductBasedate'] = $result[0]["mydate"];
        else
            $this->jsonOutput['myStoreBasedate'] = $result[0]["mydate"];
    }
  
    public function buildDataArray($fields, $isCsvColumn) {
        if (empty($fields))
            return false;

        $configurationCheck = new config\ConfigurationCheck($this->settingVars, $this->queryVars);
        $configurationCheck->buildDataArray($fields, $isCsvColumn);
        $this->dbColumnsArray = $configurationCheck->dbColumnsArray;
		$this->displayCsvNameArray = $configurationCheck->displayCsvNameArray;
        return;
    }  
    
    /**
     * getRegions()
     * It will list all regions
     * 
     * @return void
     */
    public function getRegions()
    {
        $regionAccount = $this->settingVars->regionDataSetting['NAME'];
        $regionTable = $this->settingVars->regionDataSetting['tablename'];
        $regionLink = $this->settingVars->regionDataSetting['link'];
        
        $query = "SELECT ".$regionAccount." AS ACCOUNT ".
            "FROM ".$regionTable." ".$regionLink." ".
            "AND ".$regionAccount." IS NOT NULL ".
            "GROUP BY ACCOUNT ".
            "ORDER BY ACCOUNT ASC ";
        //echo $query;exit;
        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);

        if (!empty($result)) {
            foreach($result as $key=>$data) {
                $dataVal    = ($id==""?$data['ACCOUNT']:$data['ID']);
                $temp   = array(
                     'data'=>$dataVal,
                     'label'=>$data['ACCOUNT']
                );
                $this->jsonOutput['REGION'][] = $temp;
            }
        }
    }  
  
}

?>