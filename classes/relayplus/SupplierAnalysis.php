<?php

namespace classes\relayplus;

use datahelper;
use projectsettings;
use filters;
use db;
use config;

class SupplierAnalysis extends config\UlConfig {
    /*     * ***
	 * go()
     * Default gateway function, should be similar in all DATA CLASS
     * arguments:
     * $settingVars [project settingsGateway variables]
     * @return $this->jsonOutput with all data that should be sent to client app
     * *** */

    public function go($settingVars) {

        $this->initiate($settingVars); //INITIATE COMMON VARIABLES

        $this->settingVars->pageName = (empty($this->settingVars->pageName) && !empty($this->settingVars->pageID)) ? $this->settingVars->pageID.'_AnalysisPage' : $this->settingVars->pageName;

        if ($this->settingVars->isDynamicPage) {
			$this->accountField = $this->getPageConfiguration('account_field', $this->settingVars->pageID)[0];
			$this->skuField = $this->getPageConfiguration('sku_field', $this->settingVars->pageID)[0];
			$this->buildDataArray(array($this->accountField, $this->skuField));
			$this->buildPageArray();
		} else {
			$this->settingVars->pageName = (empty($this->settingVars->pageName)) ? 'SUPPLIER_ANALYSIS_PAGE' : $this->settingVars->pageName;
            if (!isset($this->settingVars->pageArray[$this->settingVars->pageName]) || empty($this->settingVars->pageArray[$this->settingVars->pageName]))
                $this->configurationFailureMessage();

            $this->accountID = (isset($this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['ACCOUNT']]) && isset($this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['ACCOUNT']]['ID'])) ? $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['ACCOUNT']]['ID'] : $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['ACCOUNT']]['NAME'];
            $this->accountName = $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['ACCOUNT']]['NAME'];
            $this->skuID = (isset($this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['SKU_FIELD']]) && isset($this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['SKU_FIELD']]['ID'])) ? $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['SKU_FIELD']]['ID'] : $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['SKU_FIELD']]['NAME'];
            $this->skuName = $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['SKU_FIELD']]['NAME'];
	    }

        $this->queryPart = $this->getAll();  //PREPARE TABLE JOIN STRING USING this class getAll
		
		$action = $_REQUEST['action'];
		
		switch ($action) {
			case 'GridAll':
				$this->GridAll();
				break;
		}		
        
        return $this->jsonOutput;
    }
	
	private function GridAll()
	{
		$arr = array();

        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $this->measureFields[] = $this->accountName;
		
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->queryPart = $this->getAll();		

        $options = array();
        if (!empty(filters\timeFilter::$tyWeekRange))
            $options['tyLyRange']['SALESTY'] = filters\timeFilter::$tyWeekRange;

        if (!empty(filters\timeFilter::$lyWeekRange))
            $options['tyLyRange']['SALESLY'] = filters\timeFilter::$lyWeekRange;

        $measureSelect = config\MeasureConfigure::prepareSelect($this->settingVars, $this->queryVars, array('M'.$_REQUEST['ValueVolume']), $options);
        $measureSelect = implode(", ", $measureSelect);		
		
        $query = "SELECT ".$this->accountName." AS ACCOUNT" .
        		", ".$measureSelect." ".
				//",SUM((CASE WHEN " . filters\timeFilter::$tyWeekRange . " THEN 1 ELSE 0 END)* " . $this->ValueVolume . ") AS SALESTY " .
                //",SUM((CASE WHEN " . filters\timeFilter::$lyWeekRange . " THEN 1 ELSE 0 END)* " . $this->ValueVolume . ") AS SALESLY " .
				",COUNT( DISTINCT (CASE WHEN  ".$this->settingVars->ProjectVolume." > 0 AND " . filters\timeFilter::$tyWeekRange . " THEN ".$this->skuName." END)) AS SKUTYCOUNT " .
				",COUNT( DISTINCT (CASE WHEN  ".$this->settingVars->ProjectVolume." > 0 AND " . filters\timeFilter::$lyWeekRange . " THEN ".$this->skuName." END)) AS SKULYCOUNT " .
                "FROM " . $this->settingVars->tablename . $this->queryPart . "  " .
                "GROUP BY ACCOUNT HAVING SALESTY > 0 AND SALESLY > 0 " .
                "ORDER BY SALESTY DESC";
		// echo $query;exit;
        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);

        if (is_array($result) && !empty($result)) {
			foreach ($result as $data) {
				$temp				= array();
				$temp['ACCOUNT']	= $data['ACCOUNT'];
				$temp['SALESTY']	= $data['SALESTY'];
				$temp['SALESLY']	= $data['SALESLY'];
				$temp['SALESVAR']	= ($data['SALESLY'] > 0) ? number_format((($data['SALESTY'] - $data['SALESLY']) / $data['SALESLY']) * 100, 1, '.', '') : 0;
				$temp['SKUTYCOUNT']	= $data['SKUTYCOUNT'];
				$temp['SKULYCOUNT']	= $data['SKULYCOUNT'];
				$temp['SKUSALESTY']	= ($data['SKUTYCOUNT'] > 0 ? number_format(($data['SALESTY']/$data['SKUTYCOUNT']), 2, '.', '') : 0);
				$temp['SKUSALESLY']	= ($data['SKULYCOUNT'] > 0 ? number_format(($data['SALESLY']/$data['SKULYCOUNT']), 2, '.', '') : 0);
				$arr[]				= $temp;
			}
		}
		
		$this->jsonOutput['gridData'] = $arr;
	}

	public function buildPageArray() {

		$accountFieldPart = explode("#", $this->accountField);

		$fetchConfig = false;
		if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
		    $fetchConfig = true;
			$this->jsonOutput['pageConfig'] = array(
				'enabledFilters' => $this->getPageConfiguration('filter_settings', $this->settingVars->pageID)
			);
			$this->jsonOutput['ACCOUNT']['TITLE'] = $accountFieldPart[0];
        }

		$accountField = strtoupper($this->dbColumnsArray[$accountFieldPart[0]]);
        $accountField = (count($accountFieldPart) > 1) ? strtoupper($accountField."_".$this->dbColumnsArray[$accountFieldPart[1]]) : $accountField;

        $skuFieldPart = explode("#", $this->skuField);
        $skuField = strtoupper($this->dbColumnsArray[$skuFieldPart[0]]);
        $skuField = (count($skuFieldPart) > 1) ? strtoupper($skuField."_".$this->dbColumnsArray[$skuFieldPart[1]]) : $skuField;

        $this->accountID = (isset($this->settingVars->dataArray[$accountField]) && isset($this->settingVars->dataArray[$accountField]['ID'])) ? $this->settingVars->dataArray[$accountField]['ID'] : $this->settingVars->dataArray[$accountField]['NAME'];
        $this->accountName = $this->settingVars->dataArray[$accountField]['NAME'];
        $this->skuID = (isset($this->settingVars->dataArray[$skuField]) && isset($this->settingVars->dataArray[$skuField]['ID'])) ? $this->settingVars->dataArray[$skuField]['ID'] : $this->settingVars->dataArray[$skuField]['NAME'];
        $this->skuName = $this->settingVars->dataArray[$skuField]['NAME']; 

        return;
    }
	
	public function buildDataArray($fields) {
        if (empty($fields))
            return false;

        $configurationCheck = new config\ConfigurationCheck($this->settingVars, $this->queryVars);
        $configurationCheck->buildDataArray($fields);
        $this->dbColumnsArray = $configurationCheck->dbColumnsArray;
        return;
    }
}

?>