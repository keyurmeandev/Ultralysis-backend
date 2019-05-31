<?php
namespace classes;

use filters;
use db;
use config;

class PerformanceBySupplier extends config\UlConfig {

    public $pageName;
    public $skuField;
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
		$this->settingVars->pageName = (empty($this->settingVars->pageName)) ? $this->settingVars->pageID.'_PerformanceBySupplierPage' : $this->settingVars->pageName;
		$this->ValueVolume = getValueVolume($this->settingVars);
        
		if ($this->settingVars->isDynamicPage) {
			$this->skuField = $this->getPageConfiguration('sku_field', $this->settingVars->pageID)[0];
            $this->buildDataArray(array($this->skuField));
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
                    //////////////
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
		$this->measureFields[] = $this->skuID;
        $this->measureFields[] = $this->skuName;
        
		$this->settingVars->useRequiredTablesOnly = true;
		if (is_array($this->measureFields) && !empty($this->measureFields)) {
			$this->prepareTablesUsedForQuery($this->measureFields);
		}
		$this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll
        $measuresFldsAll = implode(',',$measureSelectRes['measureSelectionArr']);
        $totalWeek = filters\timeFilter::getTotalWeek(filters\timeFilter::$FromYear,filters\timeFilter::$FromWeek,filters\timeFilter::$ToYear,filters\timeFilter::$ToWeek);

        $query = "SELECT ".$this->skuID." AS ID, " .
            $this->skuName ." AS ACCOUNT, " .
            $measuresFldsAll.
            ",SUM((CASE WHEN ".trim(filters\timeFilter::$tyWeekRange)." THEN 1 ELSE 0 END) * ".$this->settingVars->ave_ac_dist.") / ".$totalWeek." AS TY_AVE_AC_DIST, " .
            " SUM((CASE WHEN ".trim(filters\timeFilter::$lyWeekRange)." THEN 1 ELSE 0 END) * ".$this->settingVars->ave_ac_dist.") / ".$totalWeek." AS LY_AVE_AC_DIST, " .
            " ".$this->settingVars->skutable.".pl AS PL ".
            " FROM " . $this->settingVars->tablename . $this->queryPart . " AND (" . filters\timeFilter::$tyWeekRange ." OR ".filters\timeFilter::$lyWeekRange ." ) ".
            // " AND ".$this->settingVars->skutable.".pl <> 0 ".
            "GROUP BY ID,ACCOUNT,PL ORDER BY TYVALUE DESC";

        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }

        $gridData = $gridData2 = array();
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
                
                if($data['PL'] <> 0)
                    $gridData[] = $temp;
                else
                    $gridData2[] = $temp;
            }
        }
        $this->jsonOutput['gridData'] = $gridData;
        $this->jsonOutput['gridData2'] = $gridData2;
    }

	public function buildPageArray() {
        $fetchConfig = false;
        
        $skuFieldPart = explode("#", $this->skuField);
        $skuField = strtoupper($this->dbColumnsArray[$skuFieldPart[0]]);
        $skuField = (count($skuFieldPart) > 1) ? strtoupper($skuField . "_" . $this->dbColumnsArray[$skuFieldPart[1]]) : $skuField;
        $this->skuID = (isset($this->settingVars->dataArray[$skuField]) && isset($this->settingVars->dataArray[$skuField]['ID'])) ? $this->settingVars->dataArray[$skuField]['ID'] : $this->settingVars->dataArray[$skuField]['NAME'];
        $this->skuName = $this->settingVars->dataArray[$skuField]['NAME'];

		if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
		    $fetchConfig = true;
            
            $mktfld = str_replace("#", "_", $this->getPageConfiguration('store_setting_field', $this->settingVars->pageID));
            if(count($mktfld) > 0) 
                $mktfld = strtoupper($mktfld[0]);

            $this->jsonOutput['pageConfig']['enabledFilters'] = $this->getPageConfiguration('filter_settings', $this->settingVars->pageID);
            $this->jsonOutput['pageConfig']['marketFilters']  = $mktfld;
            $this->jsonOutput['pageConfig']['gridFldName']    = (isset($this->settingVars->dataArray[$skuField]) && isset($this->settingVars->dataArray[$skuField]['ID'])) ? $this->settingVars->dataArray[$skuField]['NAME_CSV'] : 'SKU';

            if($this->skuID != $this->skuName){
                $this->jsonOutput['pageConfig']['gridFldId']    = (isset($this->settingVars->dataArray[$skuField]) && isset($this->settingVars->dataArray[$skuField]['ID'])) ? $this->settingVars->dataArray[$skuField]['ID_CSV'] : 'ID';
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