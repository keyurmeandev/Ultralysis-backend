<?php
namespace classes;

use datahelper;
use projectsettings;
use filters;
use db;
use config;
use utils;

class DCAndStoreFirstHitDashboard extends config\UlConfig {

    /** ***
	 * go()
     * Default gateway function, should be similar in all DATA CLASS
     * arguments:
     * $settingVars [project settingsGateway variables]
     * @return $this->jsonOutput with all data that should be sent to client app
     * *** */
     
    public function go($settingVars) {
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES
        $this->redisCache = new \utils\RedisCache($this->queryVars);
        
        if ($this->settingVars->isDynamicPage) {
        	$this->accountField = $this->getPageConfiguration('account_field', $this->settingVars->pageID)[0];
            
            
            $this->fullLayout = true;

        	$tempBuildFieldsArray = array($this->accountField);

            if(isset($_REQUEST["fetchConfig"]) &&  $_REQUEST["fetchConfig"] == true) {
                $pagination_settings_arr = $this->getPageConfiguration('pagination_settings', $this->settingVars->pageID);
                if(count($pagination_settings_arr) > 0){
                    $this->jsonOutput['is_pagination'] = $pagination_settings_arr[0];
                    $this->jsonOutput['per_page_row_count'] = (int) $pagination_settings_arr[1];
                }
            }

        	$this->buildDataArray($tempBuildFieldsArray);
            $this->buildPageArray();
        } else {
            $this->configurationFailureMessage();
        }

        $action = $_REQUEST['action'];
        switch ($action) {
            case 'topGridData';
                $this->getLatestDateOfMultsProject();
                $this->topGridData();
				break;
        }
        
        return $this->jsonOutput;
    }

    /**
     * For overright header text
    */
    function getLatestDateOfMultsProject() {
        
        $commontables   = $this->settingVars->dataTable['default']['tables'];
        $commonlink     = $this->settingVars->dataTable['default']['link'];
        $skutable        = $this->settingVars->dataTable['product']['tables'];
        $skulink        = $this->settingVars->dataTable['product']['link'];

        $query = " SELECT MAX(distinct order_due_date) AS MYDATE
                    FROM ".$commontables.", ".$skutable. 
                    $commonlink. $skulink . 
                    " ORDER BY MYDATE";

        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }
        
        $this->jsonOutput['LatestMultsDate'] = date("D d M Y", strtotime($result[0]['MYDATE']));
    }
	
	/**
	 * topGridData()
     * This Function is used to retrieve list based on set parameters     
	 * @access private	
     * @return $this->jsonOutput with all data that should be sent to client app     
     */
    private function topGridData($action = false) {
		
        $this->queryPart = filters\productAndMarketFilter::include_product_and_market_filters($this->queryVars,$this->settingVars);
        
        $poTable = $this->settingVars->tesco_po_details;
        $timetable = $this->settingVars->timetable;
        $skutable = $this->settingVars->skutable;

        $query_group = $query_order = '';
        if($action){
            $query_fields_data .= ", order_due_date AS ORDER_DUE_DATE ";
            $query_group = "ORDER_DUE_DATE, ";
            $query_order = "ORDER_DUE_DATE DESC, ";
        }
        
        $query = "SELECT ".$this->accountIdField." AS SKUID " .
                ",".$this->accountNameField." AS ACCOUNT " .
                ",MAX(central_PTMM_YN) AS STAR_LINE " .
                ",SUM(IF(file_type='FIRST HIT WEEKLY',total_orders_cases,0)) AS SUPPLIER_ORDERS ".
                ",SUM(IF(file_type='DC TO STORE WEEKLY',total_orders_cases,0)) AS STORE_DEMAND ".
                ",SUM(IF(file_type='FIRST HIT WEEKLY',delivered_on_time,0)) AS FIRST_HIT_DELIV_ON_TIME ".
                ",SUM(IF(file_type='DC TO STORE WEEKLY',total_delivered_cases,0)) AS DC_TO_STORE_DELIV_ON_TIME ".
                $query_fields_data .
                " FROM ".$poTable.", ".$skutable.", ".$timetable. 
                " WHERE ".$poTable.".GID = ".$skutable.".GID AND ". 
                $poTable.".skuID = ".$skutable.".PIN AND ".
                $poTable.".GID = ".$timetable.".GID AND ".
                $poTable.".".$this->settingVars->dateperiod." = ".$timetable.".".$this->settingVars->dateperiod." AND ".
                $skutable.".clientID = '".$this->settingVars->clientID."' AND ".
                $skutable.".hide <> 1 AND ".
                $skutable.".GID = ".$this->settingVars->GID." AND ".
                $timetable.".GID = ".$this->settingVars->GID." ".
                (!empty($this->queryPart) ? $this->queryPart : "") ." AND (" . 
                filters\timeFilter::$tyWeekRange . ") GROUP BY SKUID,  ".$query_group." ACCOUNT ORDER BY ".$query_order." SUPPLIER_ORDERS DESC,STORE_DEMAND  DESC";
        //echo $query;exit;
        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }
        
        if (is_array($result) && !empty($result))
        {
            foreach($result as $key => $data)
            {
                $result[$key]['STAR_LINE'] = $data['STAR_LINE'];
                
                if($action){
                    $result[$key]['ORDER_DUE_DATE'] = $data['ORDER_DUE_DATE'];
                }
                $result[$key]['SUPPLIER_ORDERS'] =(int) $data['SUPPLIER_ORDERS'];
                $result[$key]['STORE_DEMAND'] =(int) $data['STORE_DEMAND'];

                $result[$key]['FIRST_HIT_DELIV_ON_TIME'] = (int) $data['FIRST_HIT_DELIV_ON_TIME'];
                $result[$key]['DC_TO_STORE_DELIV_ON_TIME'] = (int) $data['DC_TO_STORE_DELIV_ON_TIME'];

                $result[$key]['S2DCSL_PER'] = ($data['SUPPLIER_ORDERS'] != 0 && $data['FIRST_HIT_DELIV_ON_TIME'] != 0) ? ($result[$key]['FIRST_HIT_DELIV_ON_TIME'] / $result[$key]['SUPPLIER_ORDERS']) * 100 : 0;

                $result[$key]['DC2STORE_PER'] = ($data['STORE_DEMAND'] != 0 && $data['DC_TO_STORE_DELIV_ON_TIME'] != 0) ? ($result[$key]['DC_TO_STORE_DELIV_ON_TIME'] / $result[$key]['STORE_DEMAND']) * 100 : 0;

                $result[$key]['S2DCSL_PER'] = (double) number_format($result[$key]['S2DCSL_PER'], 1, '.', '');
                $result[$key]['DC2STORE_PER'] = (double) number_format($result[$key]['DC2STORE_PER'], 1, '.', '');
            }

            $AllRowTotal['SKUID'] = '';
            $AllRowTotal['ACCOUNT'] = 'TOTAL';
            
            $AllRowTotal['SUPPLIER_ORDERS'] = array_sum(array_column($result,'SUPPLIER_ORDERS'));
            $AllRowTotal['STORE_DEMAND'] = array_sum(array_column($result,'STORE_DEMAND'));

            $AllRowTotal['FIRST_HIT_DELIV_ON_TIME'] = array_sum(array_column($result,'FIRST_HIT_DELIV_ON_TIME'));
            $AllRowTotal['DC_TO_STORE_DELIV_ON_TIME'] = array_sum(array_column($result,'DC_TO_STORE_DELIV_ON_TIME'));

            $AllRowTotal['S2DCSL_PER'] = ($AllRowTotal['SUPPLIER_ORDERS'] != 0 && $AllRowTotal['FIRST_HIT_DELIV_ON_TIME'] != 0) ? ($AllRowTotal['FIRST_HIT_DELIV_ON_TIME'] / $AllRowTotal['SUPPLIER_ORDERS']) * 100 : 0;

            $AllRowTotal['DC2STORE_PER'] = ($AllRowTotal['STORE_DEMAND'] != 0 && $AllRowTotal['DC_TO_STORE_DELIV_ON_TIME'] != 0) ? ($AllRowTotal['DC_TO_STORE_DELIV_ON_TIME'] / $AllRowTotal['STORE_DEMAND']) * 100 : 0;

            $AllRowTotal['S2DCSL_PER'] = (double) number_format($AllRowTotal['S2DCSL_PER'], 1, '.', '');
            $AllRowTotal['DC2STORE_PER'] = (double) number_format($AllRowTotal['DC2STORE_PER'], 1, '.', '');

            array_unshift($result,$AllRowTotal);
        }
        $this->jsonOutput['topGridData'] = $result;
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
        $gridFieldPart = explode("#", $this->accountField);

        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
            $fetchConfig = true;
            $this->jsonOutput['pageConfig'] = array(
                'enabledFilters' => $this->getPageConfiguration('filter_settings', $this->settingVars->pageID)
            );
            
            $SKUID =  (count($gridFieldPart) > 1) ? $this->displayCsvNameArray[$gridFieldPart[1]] : $this->displayCsvNameArray[$gridFieldPart[0]];
            $topGridColumns['SKUID']                = ['name'=>$SKUID, 'type'=>'string','size'=>170, 'filter' => 'agTextColumnFilter'];
            $topGridColumns['ACCOUNT']              = ['name'=>$this->displayCsvNameArray[$gridFieldPart[0]],'type'=>'string','size'=>170, 'filter' => 'agTextColumnFilter'];

            $topGridColumns['STAR_LINE']             = ['name'=>"STAR LINE?",   'type'=>'string', 'size'=>100, 'filter' => 'agTextColumnFilter'];
            $topGridColumns['SUPPLIER_ORDERS']      = ['name'=>"SUPPLIER ORDERS", 'type'=>'number', 'size'=>100, 'decimalPoints'=>0, 'filter' => 'agNumberColumnFilter'];
            $topGridColumns['STORE_DEMAND']         = ['name'=>"STORE DEMAND", 'type'=>'number', 'size'=>100, 'decimalPoints'=>0, 'filter' => 'agNumberColumnFilter'];

            $topGridColumns['S2DCSL_PER']           = ['name'=>"S2DC SL%", 'type'=>'number', 'size'=>100, 'decimalPoints'=>1, 'filter' => 'agNumberColumnFilter'];
            $topGridColumns['DC2STORE_PER']         = ['name'=>"DC2STORE SL%",'type'=>'number', 'size'=>100, 'decimalPoints'=>1, 'filter' => 'agNumberColumnFilter'];

            $this->jsonOutput['topGridColumns']     = $topGridColumns;
        }

        $gridField = strtoupper($this->dbColumnsArray[$gridFieldPart[0]]);
        $gridField = (count($gridFieldPart) > 1) ? strtoupper($gridField . "_" . $this->dbColumnsArray[$gridFieldPart[1]]) : $gridField;

        $this->accountIdField = (isset($this->settingVars->dataArray[$gridField]) && isset($this->settingVars->dataArray[$gridField]['ID'])) ? 
            $this->settingVars->dataArray[$gridField]['ID'] : $this->settingVars->dataArray[$gridField]['NAME'];
        $this->accountNameField = $this->settingVars->dataArray[$gridField]['NAME'];

        return;
    }
	
}
?>