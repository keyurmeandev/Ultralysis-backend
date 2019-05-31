<?php
namespace classes;

use datahelper;
use projectsettings;
use filters;
use utils;
use db;
use config;

class ZeroSalesLclPage extends config\UlConfig {

    public $dbColumnsArray;
    public $displayCsvNameArray;
    private $timeFrame;

    public function go($settingVars) {
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES

        $this->settingVars->pageName = (empty($this->settingVars->pageName)) ? $this->settingVars->pageID.'_ZeroSalesLclPage' : $settingVars->pageName;

        $this->redisCache = new utils\RedisCache($this->queryVars);
        
        if ($this->settingVars->isDynamicPage) {
            $this->storeField = $this->getPageConfiguration('store_field', $this->settingVars->pageID)[0];
            $this->pinField = $this->getPageConfiguration('pin_field', $this->settingVars->pageID)[0];
            $this->extraColumns = $this->getPageConfiguration('extra_columns', $this->settingVars->pageID);    

            $tempBuildFieldsArray = array($this->storeField, $this->pinField);
            if (!empty($this->extraColumns) && is_array($this->extraColumns)){
                foreach ($this->extraColumns as $key => $val) {
                    $extra_fields[] = $val;
                }
                $tempBuildFieldsArray = array_merge($tempBuildFieldsArray, $extra_fields);
            }

            $buildDataFields = array();
            foreach($tempBuildFieldsArray as $value)
                if (!in_array($value, $buildDataFields))
                    $buildDataFields[] = $value;

            $this->buildDataArray($buildDataFields);
            $this->buildPageArray();
        }

        /* To set Time Frame */
        if (isset($_REQUEST["timeFrame"]) && !empty($_REQUEST["timeFrame"]))
            $this->timeFrame = $_REQUEST["timeFrame"];
        else
            $this->timeFrame = 12; //$this->settingVars->pageArray["DISTRIBUTION_QUALITY"]["DEFAULT_TIME_FRAME"];

        filters\timeFilter::calculate_Ty_And_Ly_WeekRange_From_TimeFrame($this->timeFrame, $this->settingVars);
        $this->queryPart = $this->getAll();
        /* To set Time Frame */

        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true'){
            $this->fetchConfig();
        }else{
            $this->prepareGridData();
        }

        return $this->jsonOutput;
    }

    public function fetchConfig(){
    	/* Filter Settings Start */
        $this->jsonOutput['pageConfig']['enabledFilters'] = $this->getPageConfiguration('filter_settings', $this->settingVars->pageID);
        /* Filter Settings End */

        /* Pagination Settings Starts */
        $pagination_settings_arr = $this->getPageConfiguration('pagination_settings', $this->settingVars->pageID);
        if(count($pagination_settings_arr) > 0){
            $this->jsonOutput['is_pagination'] = $pagination_settings_arr[0];
            $this->jsonOutput['per_page_row_count'] = (int) $pagination_settings_arr[1];
        }
        /* Pagination Settings End */
    }

    public function prepareGridData($name) {

    	$rows = $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $querypart = "";

        $this->measureFields[] = $this->storeID;
        $this->measureFields[] = $this->storeName;
        $this->measureFields[] = $this->skuID;
        $this->measureFields[] = $this->skuName;

        foreach ($this->extra_field_arr as $key => $value) {
        	$this->measureFields[] = $value;
        }
        //echo "<pre>";print_r($this->measureFields);exit;
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }

        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll

        //$latestDate = filters\timeFilter::getLastN14DaysDate($this->settingVars, 0 , 1);
        $latestDate = filters\timeFilter::getLatest_n_dates(0, 1, $this->settingVars, false, '', true);

        $query = "SELECT DISTINCT PIN, MAX(PNAME) PNAME, SNO, MAX(SNAME) SNAME ".
            (!empty($this->extra_field_groupby) ? ", ".implode(', ', $this->extra_field_groupby) : "").",SUM(SALES) SALES, ".
            "SUM((CASE WHEN MYDATE= '" . $latestDate[0] . "' THEN 1 ELSE 0 END) * STOCK) AS STOCK " .
            " FROM (
            SELECT ".
            $this->skuID. " AS PIN, ".
            "MAX(".$this->skuName. ") AS PNAME, ".
            $this->storeID. " AS SNO, ".
            "MAX(".$this->storeName. ") AS SNAME, ".
            (!empty($this->extra_field_select) ? implode(',', $this->extra_field_select).", " : "").
            "SUM(" . $this->settingVars->ProjectVolume . ") AS SALES, " .
            // "SUM((CASE WHEN " . $this->settingVars->dateField . "= '" . $latestDate[0] . "' THEN 1 ELSE 0 END) * VAT) AS STOCK " .
            "SUM(VAT) AS STOCK, " .
            $this->settingVars->dateField." AS MYDATE, " .
            "COUNT(".$this->settingVars->dateField.") over (partition by ".$this->storeID.",".$this->skuID.")number_week ".
            " FROM " . $this->settingVars->tablename .' '. trim($this->queryPart) .
            " AND (" . filters\timeFilter::$tyWeekRange . ") ".
            " GROUP BY SNO, PIN, MYDATE".(!empty($this->extra_field_groupby) ? ", ".implode(', ', $this->extra_field_groupby) : "").
            " HAVING STOCK > 1 AND SALES = 0 ".
            " ORDER BY STOCK DESC ) as tt WHERE number_week=".$this->timeFrame." ORDER BY STOCK DESC ";
 
        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);

        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }



        $this->jsonOutput["gridData"] = array_values($result);

    }

    public function buildPageArray() {
    	
    	/* Pin Field Start */
        $pinFieldPart = explode("#", $this->pinField);
    	$pinField = strtoupper($this->dbColumnsArray[$pinFieldPart[0]]);
        $pinField = (count($pinFieldPart) > 1) ? strtoupper($pinField . "_" . $this->dbColumnsArray[$pinFieldPart[1]]) : $pinField;
		//print_r($this->settingVars->dataArray);exit;
		$this->skuID = (isset($this->settingVars->dataArray[$pinField]) && isset($this->settingVars->dataArray[$pinField]['ID'])) ? $this->settingVars->dataArray[$pinField]['ID'] : $this->settingVars->dataArray[$pinField]['NAME'];
        $this->skuName = $this->settingVars->dataArray[$pinField]['NAME'];
        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true'){
        	
        	if(isset($this->settingVars->dataArray[$pinField]) && isset($this->settingVars->dataArray[$pinField]['ID_CSV']))
        	   $gridColumns['PIN'] =  $this->settingVars->dataArray[$pinField]['ID_CSV'];

        	$gridColumns['PNAME'] = $this->settingVars->dataArray[$pinField]['NAME_CSV'];
        }
        /* Pin Field End */

    	/* Store Field Start */
    	$storeFieldPart = explode("#", $this->storeField);
    	$storeField = strtoupper($this->dbColumnsArray[$storeFieldPart[0]]);
        $storeField = (count($storeFieldPart) > 1) ? strtoupper($storeField . "_" . $this->dbColumnsArray[$storeFieldPart[1]]) : $storeField;
		$this->storeID = (isset($this->settingVars->dataArray[$storeField]) && isset($this->settingVars->dataArray[$storeField]['ID'])) ? $this->settingVars->dataArray[$storeField]['ID'] : $this->settingVars->dataArray[$storeField]['NAME'];
        $this->storeName = $this->settingVars->dataArray[$storeField]['NAME'];
        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true'){
        	

            if(isset($this->settingVars->dataArray[$storeField]) && isset($this->settingVars->dataArray[$storeField]['ID_CSV']))
               $gridColumns['SNO'] =  $this->settingVars->dataArray[$storeField]['ID_CSV'];

        	$gridColumns['SNAME'] = $this->settingVars->dataArray[$storeField]['NAME_CSV'];
        }
        /* Store Field End */

        

        //echo "<pre>";print_r($this->settingVars->dataArray);exit;
        foreach ($this->extraColumns as $key => $value) {
        	$extraField = strtoupper($this->dbColumnsArray[$value]);
        	$this->extra_field_arr[] = $this->settingVars->dataArray[$extraField]['NAME'];
        	$this->extra_field_select[] = $this->settingVars->dataArray[$extraField]['NAME']." AS ".$this->settingVars->dataArray[$extraField]['NAME_ALIASE'];
        	$this->extra_field_groupby[] = $this->settingVars->dataArray[$extraField]['NAME_ALIASE'];
        	if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true'){
        		$gridColumns[$this->settingVars->dataArray[$extraField]['NAME_ALIASE']] = $this->settingVars->dataArray[$extraField]['NAME_CSV'];
        	}
        }

        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true'){
        	//echo "<pre>";print_r($gridColumns);exit;
        	$this->jsonOutput['gridColumns'] = $gridColumns;
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
}
?>
