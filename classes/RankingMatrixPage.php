<?php

namespace classes;

use projectsettings;
use datahelper;
use filters;
use config;
use utils;
use db;

class RankingMatrixPage extends config\UlConfig {

    public $dbColumnsArray;
    public $displayCsvNameArray;
    public $redisCache;

    public function __construct() {
        $this->jsonOutput = array();
    }

    /*****
     * Default gateway function, should be similar in all DATA CLASS
     * arguments:
     *  $settingVars [project settingsGateway variables]
     * @return $jsonOutput with all data that should be sent to client app
    *****/
    public function go($settingVars) {
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES
        $this->redisCache = new utils\RedisCache($this->queryVars);

        if ($this->settingVars->isDynamicPage) 
        {
            // read template config and prepare data array
            $customerField = $this->getPageConfiguration('customer_field', $this->settingVars->pageID);
            $account = $this->getPageConfiguration('account_field', $this->settingVars->pageID);
            $fieldPart = explode("#", $account[0]);
            $this->accountField = $fieldPart[0];
            $this->customerField = $customerField[0];
            $fields[] = $account[0];
            $fields[] = $customerField[0];


            $this->buildDataArray($fields);
            $this->buildPageArray();
            
            $accountField = strtoupper($this->dbColumnsArray[$this->accountField]);
            $accountField = (count($fieldPart) > 1) ? strtoupper($accountField."_".$this->dbColumnsArray[$fieldPart[1]]) : $accountField;

            $customerField = strtoupper($this->dbColumnsArray[$this->customerField]);
            
            if(isset($this->settingVars->dataArray[$accountField]) && isset($this->settingVars->dataArray[$accountField]['ID'])) {
                $this->accountID = $this->settingVars->dataArray[$accountField]['ID'];    
                $this->jsonOutput['accountIDColHeader'] = $this->settingVars->dataArray[$accountField]['ID_CSV'];
            }

            $this->accountName = $this->settingVars->dataArray[$accountField]['NAME'];
            $this->jsonOutput['accountColHeader'] = $this->settingVars->dataArray[$accountField]['NAME_CSV'];

            $this->customerField = $this->settingVars->dataArray[$customerField]['NAME'];

            //$this->jsonOutput['accountColHeader'] = "MASTER SKU";
        } else {
            $message = "Page isn't configured properly.";
            $response = array("configuration" => array("status" => "fail", "messages" => array($message)));
            echo json_encode($response);
            exit();
        }
        
        if (isset($_REQUEST['action']) && $_REQUEST['action']=='fetchGrid')
            $this->gridData();
        else if(isset($_REQUEST['action']) && $_REQUEST['action']=='fetchInlineMarketAndProductFilter') {
            $this->settingVars->pageName = '';
            $this->fetchInlineMarketAndProductFilterData();
            $this->fetchHardStopDate();
        }
        return $this->jsonOutput;
    }


    public function fetchInlineMarketAndProductFilterData() {
        /*[START] PREPARING THE PRODUCT INLINE FILTER DATA */
        if(isset($this->queryVars->projectConfiguration) && isset($this->queryVars->projectConfiguration['has_product_market_filter_disp_type']) && $this->queryVars->projectConfiguration['has_product_market_filter_disp_type']==1) {
            $configureProject = new config\ConfigureProject($this->settingVars, $this->queryVars);
            $configureProject->fetch_all_product_and_marketSelection_data(true);
            $extraWhere = filters\timeFilter::$seasonalTimeframeExtraWhere." AND (" .filters\timeFilter::$tyWeekRange. " OR ".filters\timeFilter::$lyWeekRange.") ";
            $this->getAllProductAndMarketInlineFilterData($this->queryVars, $this->settingVars, $extraWhere);
        }
        /*[END] PREPARING THE PRODUCT INLINE FILTER DATA */
    }

    public function buildPageArray() 
    {
        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {

            // get filter configuration
            $this->jsonOutput['gridConfig'] = array(
                'enabledFilters' => $this->getPageConfiguration('filter_settings', $this->settingVars->pageID),
            );

            /*[START] CODE FOR INLINE FILTER STYLE*/
            $filtersDisplayType = $this->getPageConfiguration('filter_disp_type', $this->settingVars->pageID);
            if(!empty($filtersDisplayType) && isset($filtersDisplayType[0])) {
                $this->jsonOutput['gridConfig']['enabledFiltersDispType'] = $filtersDisplayType[0];
            }
            /*[END] CODE FOR INLINE FILTER STYLE*/
        }
        return;
    }    
    
    public function buildDataArray($fields) {
        if (empty($fields))
            return false;

        $configurationCheck = new config\ConfigurationCheck($this->settingVars, $this->queryVars);
        $configurationCheck->buildDataArray($fields);
        $this->dbColumnsArray = $configurationCheck->dbColumnsArray;
        $this->displayCsvNameArray = $configurationCheck->displayCsvNameArray;
        return;
    }

    public function fetchHardStopDate()
    {
        if($this->settingVars->hasSeasonalTimeframe){
            $requestedYear = '';
        }else{
            $requestedCombination = explode("-", $_REQUEST['timeFrame']);
            $requestedYear        = $requestedCombination[1];
        }

        // get hardstopdate bases on request
        $objDatahelper = new datahelper\Time_Selection_DataCollectors($this->settingVars);
        $showAsOutput = (isset($_REQUEST['fetchHardStopDate']) && $_REQUEST['fetchHardStopDate'] == 'YES') ? true : false;
        $objDatahelper->getAllSeasonalHardStopDates($this->jsonOutput, $requestedYear, $showAsOutput);
    }
    
    public function gridData() 
    {
        $rankMeasureType = (isset($_REQUEST['rankMeasureSelection']) && $_REQUEST['rankMeasureSelection']=='M') ? 'M' : 'R';
        $finalData = $totalRank = $this->settingVars->tableUsedForQuery = $this->measureFields = $fgroupList = $seasonalDataArray = array();
        
        $this->fetchHardStopDate();
    
        $this->measureFields[] = $this->accountName;
        $this->measureFields[] = $this->customerField;
        
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->queryPart = $this->getAll();

        /* Make dynamic measuer bases on request params -- START */
        $measureKey = 'M' . $_REQUEST['ValueVolume'];
        $measure = $this->settingVars->measureArray[$measureKey];
        
        if (!empty(filters\timeFilter::$tyWeekRange)) 
        {
            $measureTYValue = "TY" . $measure['ALIASE'];
            $options['tyLyRange'][$measureTYValue] = trim(filters\timeFilter::$tyWeekRange);
        }
        
        $this->currentMeasure = config\MeasureConfigure::prepareSelect($this->settingVars, $this->queryVars, array($measureKey), $options)[0];
        $this->currentMeasure = explode(" AS ", $this->currentMeasure)[0];
        /* Make dynamic measuer bases on request params -- END */

        if($rankMeasureType == 'R'){
            // get TOTAL RANK for skus
            $query = "SELECT ".$this->accountName." AS ACCOUNT, ".
                (($this->accountID) ? " ".$this->accountID." AS  ACCOUNT_ID , " : " ") .
                " RANK() OVER (ORDER BY " . $this->currentMeasure . " DESC) TOTAL_RANK ".
                " FROM " . $this->settingVars->tablename .' '. trim($this->queryPart) . 
                " AND (" . filters\timeFilter::$tyWeekRange . ")". 
                " GROUP BY ACCOUNT ".
                (($this->accountID) ? ", ACCOUNT_ID" : " ") .
                " ORDER BY TOTAL_RANK ASC";
            
            $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
            if ($redisOutput === false) {
                $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
                $this->redisCache->setDataForHash($result);
            } else {
                $result = $redisOutput;
            }

            
            if (is_array($result) && !empty($result)) {
                foreach($result as $data)
                    if(isset($this->accountID)) {
                        $totalRank[$data['ACCOUNT'].$data['ACCOUNT_ID']] = $data['TOTAL_RANK'];
                    }else{
                        $totalRank[$data['ACCOUNT']] = $data['TOTAL_RANK'];
                    }
            }
        
            $subQue = " RANK() OVER (PARTITION BY ".$this->customerField." ORDER BY " . $this->currentMeasure . " DESC) RANK, "."RANK() OVER (ORDER BY " . $this->currentMeasure . " DESC) TOTAL_RANK ";
            $subQueOrderBy = 'TOTAL_RANK ASC';
        }else{
            $subQue = $this->currentMeasure . " RANK";
            $subQueOrderBy = 'RANK DESC';
        }
        
        // main grid data query
        $query = "SELECT ".$this->accountName." AS ACCOUNT, ".
            (($this->accountID) ? " ".$this->accountID." AS  ACCOUNT_ID , " : " ") .
            $this->customerField." as CUSTOMER, " .
            $subQue.
            " FROM " . $this->settingVars->tablename .' '. trim($this->queryPart) . 
            " AND (" . filters\timeFilter::$tyWeekRange . ")". 
            " GROUP BY ACCOUNT ".
            ((isset($this->accountID)) ? ", ACCOUNT_ID" : " ") .
            " , CUSTOMER  ORDER BY $subQueOrderBy";

        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }

        $customerList = array();

        //echo "<pre>";print_r($result);exit;
        // prepare array based in pname
        if (is_array($result) && !empty($result)) {
            foreach ($result as $key => $seasonalData) {

                $accountKey = $seasonalData['ACCOUNT'];
                if(isset($this->accountID)) {
                    $accountKey .=$seasonalData['ACCOUNT_ID'];
                } 

                if ($rankMeasureType == 'M')
                    $totalRank[$accountKey] += (isset($seasonalData['ACCOUNT'])) ? $seasonalData['RANK'] : 0;
                
                $seasonalDataArray[$accountKey][$seasonalData['CUSTOMER']] = $seasonalData;
                if(!in_array($seasonalData['CUSTOMER'], array_column($customerList, 'CUSTOMER_LABEL'))) {
                	$randomNum = rand();
                	$randomNumMap[$seasonalData['CUSTOMER']] = 'CUST_'.$randomNum;
					array_push($customerList, ['CUSTOMER_FIELD' => 'CUST_'.$randomNum, 'CUSTOMER_LABEL' => $seasonalData['CUSTOMER']]);
                }
                //if(!in_array($seasonalData['GID'], $gidList))  array_push($gidList, $seasonalData['GID']);
            }
        }

        
        /*// get group name
        if(count($gidList) > 0)
        {
            $query = "SELECT gid, gname FROM ".$this->settingVars->grouptable." WHERE gid IN (".implode(",", $gidList).")";
            $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
            if ($redisOutput === false) {
                $fgroupList = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
                $this->redisCache->setDataForHash($fgroupList);
            } else {
                $fgroupList = $redisOutput;
            }
        }*/

        //echo "<pre>";print_r($seasonalDataArray);exit;
        // prepare final output array
        foreach (array_keys($seasonalDataArray) as $account) 
        {
            $tmp = array();
            foreach ($customerList as $key => $customerData) 
            {

                $tmp['TOTAL_RANK'] = ($rankMeasureType == 'R') ? (int) $totalRank[$account] : $totalRank[$account];
                if (isset($seasonalDataArray[$account][$customerData['CUSTOMER_LABEL']])) 
                {
                    $data = $seasonalDataArray[$account][$customerData['CUSTOMER_LABEL']];

                    $tmp['ACCOUNT'] = $data['ACCOUNT'];
                    if(isset($this->accountID)) {
                        $tmp['ACCOUNT_ID'] = $data['ACCOUNT_ID'];
                    }
                    // $dtKey = 'gid_'.$gData['gid'];
                    $dtKey = $randomNumMap[$customerData['CUSTOMER_LABEL']];
                    $tmp[$dtKey] = ($rankMeasureType == 'R') ? (int) $data['RANK'] : (float) $data['RANK'];
                }
                else
                {
                    // $dtKey = 'gid_'.$gData['gid'];
                    $dtKey = $randomNumMap[$customerData['CUSTOMER_LABEL']];
                    $tmp[$dtKey] = ($rankMeasureType == 'R') ? 999999 : 0;
                }
            }
            $finalData[] = $tmp;
        }
        
        $this->jsonOutput['gridAllColumnsHeader']= $customerList;
        $this->jsonOutput['gridData']            = $finalData;
        $this->jsonOutput['gridRankMeasureType'] = $rankMeasureType;
        $this->jsonOutput['dataDecimalPlaces']   = $measure['dataDecimalPlaces'];
    }
}
?>