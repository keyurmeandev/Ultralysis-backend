<?php

namespace classes;

use projectsettings;
use datahelper;
use filters;
use config;
use utils;
use db;

class PerfectStoreScoreCardPage extends config\UlConfig {

    public $dbColumnsArray;
    public $displayCsvNameArray;
    public $redisCache;
    public $isExport;
    public $aggregateSelection;
    public $brandField;
    public $categoryField;


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
        $this->isExport = false;

        if ($this->settingVars->isDynamicPage) 
        {
            $brand = $this->getPageConfiguration('brand_field', $this->settingVars->pageID);
            if(!empty($brand)){
                $brandFieldPart = explode("#", $brand[0]);
                $fields[] = $this->brandField = $brandFieldPart[0];
            }

            $category = $this->getPageConfiguration('category_field', $this->settingVars->pageID);
            if(!empty($category)){
                $categoryFieldPart = explode("#", $category[0]);
                $fields[] = $this->categoryField = $categoryFieldPart[0];
            }

            $this->settingVars->pageName = (empty($this->settingVars->pageName)) ? implode("_", $this->gridFields)."_PERFECT_STORE_SCORE_CARD" : $settingVars->pageName;

            $this->buildDataArray($fields);
            $this->buildPageArray();

            if(!empty($brand)){
                $brandField = strtoupper($this->dbColumnsArray[$this->brandField]);
                $brandField = (count($brandFieldPart) > 1) ? strtoupper($brandFieldPart."_".$this->dbColumnsArray[$brandFieldPart[1]]) : $brandField;

                $this->brandID = (isset($this->settingVars->dataArray[$brandField]) && isset($this->settingVars->dataArray[$brandField]['ID'])) ? $this->settingVars->dataArray[$brandField]['ID'] : $this->settingVars->dataArray[$brandField]['NAME'];  
                $this->brandName = $this->settingVars->dataArray[$brandField]['NAME'];
                $this->brandCsvName = $this->settingVars->dataArray[$brandField]['NAME_CSV'];
            }    

            if(!empty($category)){
                $categoryField = strtoupper($this->dbColumnsArray[$this->categoryField]);
                $categoryField = (count($categoryFieldPart) > 1) ? strtoupper($categoryFieldPart."_".$this->dbColumnsArray[$categoryFieldPart[1]]) : $categoryField;

                $this->categoryID = (isset($this->settingVars->dataArray[$categoryField]) && isset($this->settingVars->dataArray[$categoryField]['ID'])) ? $this->settingVars->dataArray[$categoryField]['ID'] : $this->settingVars->dataArray[$categoryField]['NAME'];  
                $this->categoryName = $this->settingVars->dataArray[$categoryField]['NAME'];
                $this->categoryCsvName = $this->settingVars->dataArray[$categoryField]['NAME_CSV'];
            }
        } else {
            $message = "Page isn't configured properly.";
            $response = array("configuration" => array("status" => "fail", "messages" => array($message)));
            echo json_encode($response);
            exit();
        }
        
        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') 
        {
            $this->jsonOutput["displayColumn"][] = array("fieldName" => 'CATEGORY', "title" => $this->categoryCsvName);
            $this->jsonOutput["displayColumn"][] = array("fieldName" => 'BRAND', "title" => $this->brandCsvName);
        }
        
        if (isset($_REQUEST['action']) && ($_REQUEST['action'] == 'fetchGrid' || $_REQUEST['action'] == 'export')){
            if($_REQUEST['action'] == 'export')
                $this->isExport = true;

            $this->gridData();
        } else if (isset($_REQUEST['action']) && ($_REQUEST['action'] == 'fetchInlineMarketAndProductFilter')) {
            $this->settingVars->pageName = '';
            $this->fetchInlineMarketAndProductFilterData();
        }

        return $this->jsonOutput;
    }

    public function fetchInlineMarketAndProductFilterData()
    {
        /*[START] PREPARING THE PRODUCT INLINE FILTER DATA */
        if(isset($this->queryVars->projectConfiguration) && isset($this->queryVars->projectConfiguration['has_product_market_filter_disp_type']) && $this->queryVars->projectConfiguration['has_product_market_filter_disp_type']==1){
            $configureProject = new config\ConfigureProject($this->settingVars, $this->queryVars);
            $configureProject->fetch_all_product_and_marketSelection_data(true); //collecting time selection data

            $extraWhere = "";

            $this->getAllProductAndMarketInlineFilterData($this->queryVars, $this->settingVars, $extraWhere);
        }
        /*[END] PREPARING THE PRODUCT INLINE FILTER DATA */
    }

    public function buildPageArray() 
    {
        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {

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
    
    public function gridData() 
    {
        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $measureSelectRes = $this->prepareMeasureSelectPart();
        
        $this->measureFields[] = $this->brandName;
        
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
           $this->prepareTablesUsedForQuery($this->measureFields);
        }
        
        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll

        $sumOfCategoryValue = $countOfCategoryValue = 0;


        $query  = "SELECT ".$this->categoryName." AS category".
            ",SUM((CASE WHEN fileType='NIELSEN' THEN 1 ELSE 0 END) * ".$this->settingVars->ProjectValue.") AS categorysales " .
            ",SUM((CASE WHEN ".$this->settingVars->ProjectDistribution." > 120 AND fileType='NIELSEN' THEN 1 ELSE 0 END) * ".$this->settingVars->ProjectDistribution.") AS categoryshare " .

            ",SUM((CASE WHEN posGroup = 'KIOSK' AND fileType='BEMYEYE' THEN 1 ELSE 0 END) * ".$this->settingVars->ProjectValue.") AS KIOSK_SALES " .
            ",SUM((CASE WHEN posGroup = 'TILL Q' AND fileType='BEMYEYE' THEN 1 ELSE 0 END) * ".$this->settingVars->ProjectValue.") AS TILL_Q_SALES " .
            ",SUM((CASE WHEN posGroup = 'CHECKOUT' AND fileType='BEMYEYE' THEN 1 ELSE 0 END) * ".$this->settingVars->ProjectValue.") AS CHECKOUT_SALES " .
            ",SUM((CASE WHEN posGroup = 'SELF-SCAN' AND fileType='BEMYEYE' THEN 1 ELSE 0 END) * ".$this->settingVars->ProjectValue.") AS SELF_SCAN_SALES " .
            ",SUM((CASE WHEN posGroup = 'IN AISLE OFF SHELF' AND fileType='BEMYEYE' THEN 1 ELSE 0 END) * ".$this->settingVars->ProjectValue.") AS IN_AISLE_OFF_SHELF_SALES " .
            ",SUM((CASE WHEN posGroup = 'MMU' AND fileType='BEMYEYE' THEN 1 ELSE 0 END) * ".$this->settingVars->ProjectValue.") AS MMU_SALES " .
            ",SUM((CASE WHEN fileType='BEMYEYE' THEN 1 ELSE 0 END) * ".$this->settingVars->ProjectValue.") AS OFF_SHELF_SALES " .
            " FROM " . $this->settingVars->tablename . $this->queryPart . " AND fileType IN ('NIELSEN', 'BEMYEYE') " .
            " GROUP BY category";
        //echo $query;exit;
        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }

        
        $sumOfCategoryValue = array_column($result,'categorysales', 'category');
        $sumOfCategoryShare = array_column($result,'categoryshare', 'category');

        $categorySales['KIOSK_SALES'] = array_column($result,'KIOSK_SALES', 'category');
        $categorySales['TILL_Q_SALES'] = array_column($result,'TILL_Q_SALES', 'category');
        $categorySales['CHECKOUT_SALES'] = array_column($result,'CHECKOUT_SALES', 'category');
        $categorySales['SELF_SCAN_SALES'] = array_column($result,'SELF_SCAN_SALES', 'category');
        $categorySales['IN_AISLE_OFF_SHELF_SALES'] = array_column($result,'IN_AISLE_OFF_SHELF_SALES', 'category');
        $categorySales['MMU_SALES'] = array_column($result,'MMU_SALES', 'category');
        $categorySales['OFF_SHELF_SALES'] = array_column($result,'OFF_SHELF_SALES', 'category');

        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $measureSelectRes = $this->prepareMeasureSelectPart();
        
        $this->measureFields[] = $this->categoryName;
        $this->measureFields[] = $this->brandName;
        
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
           $this->prepareTablesUsedForQuery($this->measureFields);
        }
        
        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll
        

        $query  = "SELECT ".$this->brandName." as brand".
                ",".$this->categoryName." AS category".
                ",AVG(CASE WHEN fileType='CHECKOUT' THEN ".$this->settingVars->ProjectValue." END) AS Checkout_Multiplier " .
                ",SUM((CASE WHEN fileType='NIELSEN' THEN 1 ELSE 0 END) * ".$this->settingVars->ProjectValue.") AS sales " .
                ",COUNT((CASE WHEN ".$this->settingVars->ProjectDistribution." > 120 AND fileType='NIELSEN' THEN ".$this->settingVars->ProjectDistribution." END)) AS salesCnt " .
                ",SUM((CASE WHEN ".$this->settingVars->ProjectDistribution." > 120 AND fileType='NIELSEN' THEN 1 ELSE 0 END) * ".$this->settingVars->ProjectDistribution.") AS distShare " .
                ",SUM((CASE WHEN posGroup = 'KIOSK' AND fileType='BEMYEYE' THEN 1 ELSE 0 END) * ".$this->settingVars->ProjectValue.") AS KIOSK_SALES " .
                ",SUM((CASE WHEN posGroup = 'TILL Q' AND fileType='BEMYEYE' THEN 1 ELSE 0 END) * ".$this->settingVars->ProjectValue.") AS TILL_Q_SALES " .
                ",SUM((CASE WHEN posGroup = 'CHECKOUT' AND fileType='BEMYEYE' THEN 1 ELSE 0 END) * ".$this->settingVars->ProjectValue.") AS CHECKOUT_SALES " .
                ",SUM((CASE WHEN posGroup = 'SELF-SCAN' AND fileType='BEMYEYE' THEN 1 ELSE 0 END) * ".$this->settingVars->ProjectValue.") AS SELF_SCAN_SALES " .
                ",SUM((CASE WHEN posGroup = 'IN AISLE OFF SHELF' AND fileType='BEMYEYE' THEN 1 ELSE 0 END) * ".$this->settingVars->ProjectValue.") AS IN_AISLE_OFF_SHELF_SALES " .
                ",SUM((CASE WHEN posGroup = 'MMU' AND fileType='BEMYEYE' THEN 1 ELSE 0 END) * ".$this->settingVars->ProjectValue.") AS MMU_SALES " .
                ",SUM((CASE WHEN fileType='BEMYEYE' THEN 1 ELSE 0 END) * ".$this->settingVars->ProjectValue.") AS OFF_SHELF_SALES " .
                // ",SUM((CASE WHEN ".$this->settingVars->ProjectValue." > 120 THEN 1 ELSE 0 END) * ".$this->settingVars->ProjectValue.") AS sales2 " .                
                " FROM " . $this->settingVars->tablename . $this->queryPart . " AND fileType IN ('NIELSEN', 'BEMYEYE', 'CHECKOUT') " .
                " GROUP BY brand,category ORDER BY sales DESC";

        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }

        $gridData = [];
        $data=[];
        $sumOfValue=$countOfValue=0;
        if (is_array($result) && !empty($result)) {
            $sumOfValue=array_sum(array_column($result,'sales'));
            $countOfValue=count($result);
            
            foreach ($result as $key => $value) {
                $categorySum = (isset($sumOfCategoryValue[$value['category']])) ? $sumOfCategoryValue[$value['category']] : 0;
                $categoryShare = (isset($sumOfCategoryShare[$value['category']])) ? $sumOfCategoryShare[$value['category']] : 0;

                $data['CATEGORY'] = $value['category'];
                $data['BRAND'] = $value['brand'];
                $data['CHECKOUT_MULTIPLIER']= $value['Checkout_Multiplier'];
                $data['sales'] = $value['sales'];
                $data['note1'] = ($categoryShare > 0) ? (($value['distShare'] / $categoryShare) * 100) : 0;
                $data['note2'] = ($categoryShare > 0) ? ((($value['salesCnt'] * 1200) / $categoryShare) * 100) : 0;

                $data['shareOfSale'] = ($categorySum > 0) ? (($value['sales'] / $categorySum) * 100) : 0;

                $data['note3'] = ($data['shareOfSale']>0) ? (($data['note1'] /$data['shareOfSale'])*100) : 0;

                $data['real_score']='';
                $data['share_dist']='';

                $data['maxDistScore']=($data['shareOfSale']>0) ? (($data['note2']/$data['shareOfSale'])*100) : 0;

                $data['distScore']=($data['note3']<100) ? (($data['note3']/$data['maxDistScore'])*100) : $data['note3'];


                $data['KIOSK_SALES'] = (isset($categorySales['KIOSK_SALES'][$value['category']]) && $categorySales['KIOSK_SALES'][$value['category']] > 0) ? ($value['KIOSK_SALES'] / $categorySales['KIOSK_SALES'][$value['category']])*100 : 0;
                $data['TILL_Q_SALES'] = (isset($categorySales['TILL_Q_SALES'][$value['category']]) && $categorySales['TILL_Q_SALES'][$value['category']] > 0) ? ($value['TILL_Q_SALES'] / $categorySales['TILL_Q_SALES'][$value['category']])*100 : 0;
                $data['CHECKOUT_SALES'] = (isset($categorySales['CHECKOUT_SALES'][$value['category']]) && $categorySales['CHECKOUT_SALES'][$value['category']] > 0) ? (($value['CHECKOUT_SALES']*$data['CHECKOUT_MULTIPLIER']) / $categorySales['CHECKOUT_SALES'][$value['category']])*100 : 0;
                $data['SELF_SCAN_SALES'] = (isset($categorySales['SELF_SCAN_SALES'][$value['category']]) && $categorySales['SELF_SCAN_SALES'][$value['category']] > 0) ? ($value['SELF_SCAN_SALES'] / $categorySales['SELF_SCAN_SALES'][$value['category']])*100 : 0;
                $data['IN_AISLE_OFF_SHELF_SALES'] = (isset($categorySales['IN_AISLE_OFF_SHELF_SALES'][$value['category']]) && $categorySales['IN_AISLE_OFF_SHELF_SALES'][$value['category']] > 0) ? ($value['IN_AISLE_OFF_SHELF_SALES'] / $categorySales['IN_AISLE_OFF_SHELF_SALES'][$value['category']])*100 : 0;
                $data['MMU_SALES'] = (isset($categorySales['MMU_SALES'][$value['category']]) && $categorySales['MMU_SALES'][$value['category']] > 0) ? ($value['MMU_SALES'] / $categorySales['MMU_SALES'][$value['category']])*100 : 0;
                $data['OFF_SHELF_SALES'] = (isset($categorySales['OFF_SHELF_SALES'][$value['category']]) && $categorySales['OFF_SHELF_SALES'][$value['category']] > 0) ? ($value['OFF_SHELF_SALES'] / $categorySales['OFF_SHELF_SALES'][$value['category']])*100 : 0;

                $data['OFF_SHELF_SCORE'] = (isset($data['shareOfSale']) && $data['shareOfSale'] > 0) ? ($data['OFF_SHELF_SALES'] / $data['shareOfSale'] )*100 : 0;

                $gridData[] = $data;
            }
        }

        $this->jsonOutput['gridData'] = $gridData;
    }
}
?>