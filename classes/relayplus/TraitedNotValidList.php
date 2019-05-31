<?php
namespace classes\relayplus;

use datahelper;
use projectsettings;
use filters;
use db;
use config;

class TraitedNotValidList extends config\UlConfig {
    /*     * ***
	 * go()
     * Default gateway function, should be similar in all DATA CLASS
     * arguments:
     * $settingVars [project settingsGateway variables]
     * @return $this->jsonOutput with all data that should be sent to client app
     * *** */

    public function go($settingVars) {
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES
        $this->redisCache = new \utils\RedisCache($this->queryVars);
        $this->checkConfiguration();
        $this->buildDataArray();
        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll

        $action = $_REQUEST['action'];
        //COLLECTING TOTAL SALES  
        switch ($action) {
            case 'traitedNotValidList':
                $this->traitedNotValidList();
                break;
        }

        return $this->jsonOutput;
    }
	
	/**
	 * traitedNotValidList()
     * This Function is used to retrieve list based on set parameters     
	 * @access private	
     * @return $this->jsonOutput with all data that should be sent to client app     
     */
    private function traitedNotValidList() {
        
		$this->settingVars->tableUsedForQuery = $this->measureFields = array();
		$this->measureFields[] = $this->skuName;
		$this->measureFields[] = $this->storeID;
		
		$this->settingVars->useRequiredTablesOnly = true;
		if (is_array($this->measureFields) && !empty($this->measureFields)) {
			$this->prepareTablesUsedForQuery($this->measureFields);
		}
		$this->queryPart = $this->getAll();
		
        $query = "SELECT " . $this->skuID . " AS TPNB" .
                ",TRIM(MAX(" . $this->skuName . ")) AS SKU" .
                ",TRIM(MAX(" . $this->storeName . ")) AS SNAME" .
                "," . $this->storeID . " AS SNO " .
                "," . $this->tsi . " AS TSI " .
                "," . $this->vsi . " AS VSI " .
                ",TRIM(MAX(" . $this->settingVars->clusterID . ")) AS CLUSTER " .
                "," . $this->settingVars->maintable . ".ItemStatus AS STATUS " .
                ",SUM((CASE WHEN ".$this->settingVars->DatePeriod." IN('" . filters\timeFilter::$tyDaysRange[0] . "') THEN ".$this->ohq." ELSE 0 END)) AS OHQ" .
                ",SUM((CASE WHEN " . $this->settingVars->DatePeriod . "= '" . filters\timeFilter::$tyDaysRange[0] . "' THEN 1 ELSE 0 END)*" . $this->msq . ") AS SHELF " .
                "FROM " . $this->settingVars->tablename . " " . $this->queryPart .
                "AND " . filters\timeFilter::$tyWeekRange .
                "AND ". $this->odate ." < ". $this->idate .
                " GROUP BY TPNB, SNO, TSI, VSI, STATUS HAVING (TSI = 1 AND VSI = 0)";

		//echo $query; exit;
        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }

        $dataSet = array();
		if (is_array($result) && !empty($result)) 
        {
			foreach ($result as $key => $data) 
            {
                $tmp = array();
				$tmp['SKUID']   = $data['TPNB'];
				$tmp['SKU']     = $data['SKU'];
				$tmp['SNO']     = $data['SNO'];
				$tmp['SNAME']   = $data['SNAME'];
				$tmp['CLUSTER'] = $data['CLUSTER'];
				$tmp['STATUS']  = $data['STATUS'];
				$tmp['SHELF']   = $data['SHELF'];
				$tmp['OHQ']     = (int)$value['OHQ'];
                $dataSet[]      = $tmp;
			}
		}

        $this->jsonOutput['traitedNotValidList'] = $dataSet;
    }

    public function checkConfiguration(){

        if(!isset($this->settingVars->ranged_items) || empty($this->settingVars->ranged_items))
            $this->configurationFailureMessage("Relay Plus TV configuration not found.");

        $configurationCheck = new config\ConfigurationCheck($this->settingVars, $this->queryVars);
        $configurationCheck->checkClusterConfiguration();

        return ;
    }

    public function buildDataArray() {
        $this->skuID    = key_exists('ID', $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['SKU_FIELD']]) ? $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['SKU_FIELD']]['ID'] : $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['SKU_FIELD']]['NAME'];
        $this->storeID  = key_exists('ID', $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['STORE_FIELD']]) ? $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['STORE_FIELD']]['ID'] : $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['STORE_FIELD']]['NAME'];
        $this->storeName= $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['STORE_FIELD']]['NAME'];
        $this->skuName  = $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['SKU_FIELD']]['NAME'];
        $this->catName  = $this->settingVars->dataArray['F1']['NAME'];  
        $this->ohq      = $this->settingVars->dataArray['F12']['NAME'];
        $this->storeTrans = $this->settingVars->dataArray['F13']['NAME'];
        $this->msq      = $this->settingVars->dataArray['F14']['NAME'];
        $this->planogram= $this->settingVars->dataArray['F6']['NAME'];
        $this->tsi      = $this->settingVars->dataArray['F7']['NAME'];
        $this->vsi      = $this->settingVars->dataArray['F8']['NAME'];
        $this->ana        = $this->settingVars->dataArray['F5']['NAME'];
        $this->storeOrder = $this->settingVars->dataArray['F16']['NAME'];
        $this->storeWhs   = $this->settingVars->dataArray['F17']['NAME'];
        $this->odate      = $this->settingVars->dataArray['F21']['NAME'];
        $this->idate      = $this->settingVars->dataArray['F22']['NAME'];
        $this->gsq      = $this->settingVars->dataArray['F9']['NAME'];
        $this->ohaq     = $this->settingVars->dataArray['F10']['NAME'];
        $this->baq      = $this->settingVars->dataArray['F11']['NAME'];

    }

}

?>