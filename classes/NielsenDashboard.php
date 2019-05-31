<?php
namespace classes;

use filters;
use db;
use config;

class NielsenDashboard extends config\UlConfig {

    public $pageName;
    public $storeField;
    public $displayCsvNameArray;
    public $dbColumnsArray;

    /** ***
     * Default gateway function, should be similar in all DATA CLASS
     * arguments:
     *  $settingVars [project settingsGateway variables]
     * @return $jsonOutput with all data that should be sent to client app
     * *** */
    public function go($settingVars) {
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES        
        $this->redisCache = new \utils\RedisCache($this->queryVars);
		$this->settingVars->pageName = (empty($this->settingVars->pageName)) ? $this->settingVars->pageID.'_NielsenDashboardPage' : $this->settingVars->pageName;
		$this->ValueVolume = getValueVolume($this->settingVars);
        
		if ($this->settingVars->isDynamicPage) {
			$this->storeField = $this->getPageConfiguration('store_field', $this->settingVars->pageID)[0];
            $this->buildDataArray(array($this->storeField));
			$this->buildPageArray();
		} else {
            $message = "Page isn't configured properly.";
            $response = array("configuration" => array("status" => "fail", "messages" => array($message)));
            echo json_encode($response);
            exit();
	    }
		
        $this->queryPart = $this->getAll();
        $action = $_REQUEST["action"];
        switch ($action) {
            case "reload": 
    			if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true'){
                    /////
                }else{
                    $this->reload();
                }
    			break;
        }
		return $this->jsonOutput;
    }
    
    public function reload() {
        $this->gridData();
    }

    public function gridData() {

		$this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $measureSelectRes = $this->prepareMeasureSelectPart();
        $this->measureFields = $measureSelectRes['measureSelectionArr'];
		$this->measureFields[] = $this->storeID;
        $this->measureFields[] = $this->storeName;
        
		$this->settingVars->useRequiredTablesOnly = true;
		if (is_array($this->measureFields) && !empty($this->measureFields)) {
			$this->prepareTablesUsedForQuery($this->measureFields);
		}
		$this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll
        $measuresFldsAll = implode(',',$measureSelectRes['measureSelectionArr']);
        $totalWeek = filters\timeFilter::getTotalWeek(filters\timeFilter::$FromYear,filters\timeFilter::$FromWeek,filters\timeFilter::$ToYear,filters\timeFilter::$ToWeek);

        $query = "SELECT ".$this->storeID." AS ID, " .
            $this->storeName ." AS ACCOUNT, " .
            $measuresFldsAll.
            ",SUM((CASE WHEN ".trim(filters\timeFilter::$tyWeekRange)." THEN 1 ELSE 0 END) * ".$this->settingVars->ave_ac_dist.") / ".$totalWeek." AS TY_AVE_AC_DIST, " .
            " SUM((CASE WHEN ".trim(filters\timeFilter::$lyWeekRange)." THEN 1 ELSE 0 END) * ".$this->settingVars->ave_ac_dist.") / ".$totalWeek." AS LY_AVE_AC_DIST " .
            " FROM " . $this->settingVars->tablename . $this->queryPart . " AND (" . filters\timeFilter::$tyWeekRange ." OR ".filters\timeFilter::$lyWeekRange ." ) " .
            "GROUP BY ID,ACCOUNT ORDER BY TYVALUE DESC";

        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }

        $gridData = array();
        if(isset($result) && !empty($result)) {
            foreach ($result as $key => $data) {
                $temp = array();
                $temp['ID'] = $data['ID'];
                $temp['ACCOUNT'] = htmlspecialchars_decode($data['ACCOUNT']);

                $temp['VALUE']     = $data['TYVALUE'];
                $temp['VALUE_LY']  = $data['LYVALUE'];
                $temp['VALUE_VAR'] = $data['TYVALUE'] - $data['LYVALUE'];
                $temp['VALUE_VAR_PER'] = ($data['LYVALUE'] > 0) ? ($temp['VALUE_VAR'] / $data['LYVALUE'])*100 : 0;
                
                $temp['VOLUME']     = $data['TYVOLUME'];
                $temp['VOLUME_LY']  = $data['LYVOLUME'];
                $temp['VOLUME_VAR'] = $data['TYVOLUME'] - $data['LYVOLUME'];
                $temp['VOLUME_VAR_PER'] = ($data['LYVOLUME'] > 0) ? ($temp['VOLUME_VAR'] / $data['LYVOLUME'])*100 : 0;
                
                $temp['TY_AVE_AC_DIST'] = $data['TY_AVE_AC_DIST'];
                $temp['LY_AVE_AC_DIST'] = $data['LY_AVE_AC_DIST'];
                
                $gridData[] = $temp;
            }
        }
        $this->jsonOutput['gridData'] = $gridData;
    }

	public function buildPageArray() {
        $fetchConfig = false;

        $storeFieldPart = explode("#", $this->storeField);
        $storeField = strtoupper($this->dbColumnsArray[$storeFieldPart[0]]);
        $storeField = (count($storeFieldPart) > 1) ? strtoupper($storeField . "_" . $this->dbColumnsArray[$storeFieldPart[1]]) : $storeField;

        $this->storeID = (isset($this->settingVars->dataArray[$storeField]) && isset($this->settingVars->dataArray[$storeField]['ID'])) ? $this->settingVars->dataArray[$storeField]['ID'] : $this->settingVars->dataArray[$storeField]['NAME'];
        $this->storeName = $this->settingVars->dataArray[$storeField]['NAME'];

		if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
		    $fetchConfig = true;
            
            $productfld = str_replace("#", "_", $this->getPageConfiguration('product_setting_field', $this->settingVars->pageID));
            if(count($productfld) > 0) 
                $productfld = strtoupper($productfld[0]);

            $this->jsonOutput['pageConfig']['enabledFilters'] = $this->getPageConfiguration('filter_settings', $this->settingVars->pageID);
			$this->jsonOutput['pageConfig']['productFilters'] = $productfld;

            $this->jsonOutput['pageConfig']['gridFldName']    = (isset($this->settingVars->dataArray[$storeField]) && isset($this->settingVars->dataArray[$storeField]['NAME_CSV'])) ? $this->settingVars->dataArray[$storeField]['NAME_CSV'] : 'STORE';

            if($this->storeID != $this->storeName){
                $this->jsonOutput['pageConfig']['gridFldId']  = (isset($this->settingVars->dataArray[$storeField]) && isset($this->settingVars->dataArray[$storeField]['ID_CSV'])) ? $this->settingVars->dataArray[$storeField]['ID_CSV'] : 'ID';
            }
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