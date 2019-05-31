<?php
namespace classes\relayplus;

use datahelper;
use projectsettings;
use filters;
use db;
use config;

class UpliftStockTracker extends config\UlConfig {
    /*     * ***
     * Default gateway function, should be similar in all DATA CLASS
     * arguments:
     *  $settingVars [project settingsGateway variables]
     * @return $xmlOutput with all data that should be sent to client app
     * *** */
	public $allMydate;
	public $lastDays;
	public $previousDays;
	 
    public function go($settingVars) {
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES
        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll

        $this->settingVars->pageName = (empty($this->settingVars->pageName) && !empty($this->settingVars->pageID)) ? $this->settingVars->pageID.'_UpliftStockTrackerPage' : $this->settingVars->pageName;

        if ($this->settingVars->isDynamicPage) {
			$this->accountFields = $this->getPageConfiguration('account_list_field', $this->settingVars->pageID);
            $this->skuField = $this->getPageConfiguration('sku_field', $this->settingVars->pageID)[0];
            $this->storeField = $this->getPageConfiguration('store_field', $this->settingVars->pageID)[0];
            $tempBuildFieldsArray = array($this->skuField,$this->storeField);
            $tempBuildFieldsArray = array_merge($tempBuildFieldsArray, $this->accountFields);

            $buildDataFields = array();
            foreach ($tempBuildFieldsArray as $value)
                if (!in_array($value, $buildDataFields))
                    $buildDataFields[] = $value;

            $this->buildDataArray($buildDataFields);
			$this->buildPageArray();
		} else {
			$this->configurationFailureMessage();
		}

		$this->allMydate = $this->getAllMydate();
		
		if(isset($_REQUEST['getmydatelist']) && $_REQUEST['getmydatelist'] == true)
		{
			$this->jsonOutput['mydateList'] = $this->allMydate;
			$_REQUEST['selectedMydate'] = $this->jsonOutput['mydateList'][0]['value'];
		}
        
		$this->generateLastAndPreviousDays(3);
		
		$this->upliftStockTrackerGrid();
		
        return $this->jsonOutput;
    }

	function getAllMydate()
	{
		$query = "select distinct mydate as value from ".$this->settingVars->maintable." ORDER BY value DESC";
		return $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
	}
	
	function generateLastAndPreviousDays($days)
	{
		foreach ($this->allMydate as $key => $date) {
	
			if($date['value'] == $_REQUEST['selectedMydate'])
			{
				$lUpto = $key+$days;
				$lDaysStart = true;
			}
		
			if ($lDaysStart && $key < $lUpto) {
				$this->lastDays[] = $date['value'];
			}

			if ($key >= $lUpto && $key < $lUpto+$days)
				$this->previousDays[] = $date['value'];
		}
	}
	
	function upliftStockTrackerGrid()
	{
        $this->settingVars->setCluster();
		$this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $this->measureFields[] = $this->skuID;
        $this->measureFields[] = $this->skuName;
        $this->measureFields[] = $this->storeID;
        $this->measureFields[] = $this->storeName;
        $this->measureFields[] = $this->ohq;
        $this->measureFields[] = $this->settingVars->clusterID;
        
        foreach ($this->accountsName as $key => $data) {
            $this->measureFields[] = $this->settingVars->dataArray[$data]['NAME'];
            $selectPart[] = "MAX(" . $this->settingVars->dataArray[$data]['NAME'] . ") AS '" . $this->settingVars->dataArray[$data]['NAME_ALIASE'] . "'";
        }

        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll

        $query = "SELECT " . $this->skuID . " AS PIN_1 ," .
                " TRIM(MAX(" . $this->skuName . ")) AS SKU_1 ," .
                $this->storeID . " AS SNO_1 ," .
                " TRIM(MAX(" . $this->storeName . ")) AS SNAME_1 , " .
                " TRIM(MAX(" . $this->settingVars->clusterID . ")) AS CLUSTER , " .
                implode(",", $selectPart) .
		        ((is_array($this->lastDays) && !empty($this->lastDays)) ? ",SUM((CASE WHEN " . $this->settingVars->DatePeriod . " IN ('".implode("','", $this->lastDays)."') THEN 1 ELSE 0 END)* ".$this->settingVars->ProjectVolume.") AS L3D_SALES " : " 0 AS L3D_SALES ").
				((is_array($this->previousDays) && !empty($this->previousDays)) ? ",SUM((CASE WHEN " . $this->settingVars->DatePeriod . " IN ('".implode("','", $this->previousDays)."') THEN 1 ELSE 0 END)*".$this->settingVars->ProjectVolume.") AS P3D_SALES " : " 0 AS P3D_SALES ").
                ",SUM((CASE WHEN " . $this->settingVars->DatePeriod . "='" . $_REQUEST['selectedMydate'] . "' THEN 1 ELSE 0 END)*".$this->ohq.") AS STOCK " .
                        "FROM " . $this->settingVars->tablename . " " . $this->queryPart .
                " AND " . $this->settingVars->DatePeriod . " IN('" . implode("','", array_merge($this->lastDays, $this->previousDays) ). "') " .
                "GROUP BY PIN_1,SNO_1 ORDER BY L3D_SALES DESC";
		$result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
		
		$output = array();
		if(is_array($result) && !empty($result))
		{
			foreach($result as $data)
			{
				$uplift = ($data['P3D_SALES'] > 0 ) ? (($data['L3D_SALES']/$data['P3D_SALES']) - 1) * 100 : 0;
				$data['UPLIFT'] = number_format($uplift, 2, '.', '');
				$daysCover = ($data['L3D_SALES'] > 0 ) ? ($data['STOCK']/($data['L3D_SALES']/3)) : 0;
				$data['DAYS_COVER'] = number_format($daysCover, 2, '.', '');
				$output[] = $data;
			}
		}
		$this->jsonOutput['gridData'] = $output;
	}

	private function makeFieldsToAccounts($srcArray) {
        $tempArr = array();
        foreach ($srcArray as $value) {
            $value = explode("#", $value);
            if (count($value) > 1)
                $tempArr[] = strtoupper($this->dbColumnsArray[$value[0]] . "_" . $this->dbColumnsArray[$value[1]]);
            else
                $tempArr[] = strtoupper($this->dbColumnsArray[$value[0]]);
        }
        return $tempArr;
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
        $skuField = strtoupper($this->dbColumnsArray[$skuFieldPart[0]]);
        $skuField = (count($skuFieldPart) > 1) ? strtoupper($skuField . "_" . $this->dbColumnsArray[$skuFieldPart[1]]) : $skuField;
        
        $storeFieldPart = explode("#", $this->storeField);
        $storeField = strtoupper($this->dbColumnsArray[$storeFieldPart[0]]);
        $storeField = (count($storeFieldPart) > 1) ? strtoupper($storeField . "_" . $this->dbColumnsArray[$storeFieldPart[1]]) : $storeField;

        $this->accountsName = $this->makeFieldsToAccounts($this->accountFields);

        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
            $fetchConfig = true;
            $this->jsonOutput['pageConfig'] = array(
                'enabledFilters' => $this->getPageConfiguration('filter_settings', $this->settingVars->pageID)
            );

            $tempCol = array();

            $tempCol['PIN_1'] = (isset($this->settingVars->dataArray[$skuField]['ID_CSV'])) ? $this->settingVars->dataArray[$skuField]['ID_CSV'] : $this->settingVars->dataArray[$skuField]['NAME_CSV'];
            $tempCol['SKU_1'] = $this->settingVars->dataArray[$skuField]['NAME_CSV'];
                
            $tempCol['SNO_1'] = (isset($this->settingVars->dataArray[$storeField]['ID_CSV'])) ? $this->settingVars->dataArray[$storeField]['ID_CSV'] : $this->settingVars->dataArray[$storeField]['NAME_CSV'];
            $tempCol['SNAME_1'] = $this->settingVars->dataArray[$storeField]['NAME_CSV'];
            
            foreach ($this->accountsName as $key => $value) {
                $tempCol[$this->settingVars->dataArray[$value]['NAME_ALIASE']] = $this->settingVars->dataArray[$value]['NAME_CSV'];
            }
            
            $this->jsonOutput["GRID_COLUMN_NAMES"] = $tempCol;
            
        }

        $this->skuID = (isset($this->settingVars->dataArray[$skuField]) && isset($this->settingVars->dataArray[$skuField]['ID'])) ? $this->settingVars->dataArray[$skuField]['ID'] : $this->settingVars->dataArray[$skuField]['NAME'];
        $this->skuName = $this->settingVars->dataArray[$skuField]['NAME'];

        $this->storeID = (isset($this->settingVars->dataArray[$storeField]) && isset($this->settingVars->dataArray[$storeField]['ID'])) ? $this->settingVars->dataArray[$storeField]['ID'] : $this->settingVars->dataArray[$storeField]['NAME'];
        $this->storeName = $this->settingVars->dataArray[$storeField]['NAME'];

        $this->ohq 		= $this->settingVars->dataArray['F12']['NAME'];

        return;
    }
}

?>