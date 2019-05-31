<?php
namespace classes;

use datahelper;
use projectsettings;
use filters;
use db;
use config;

class PurchaseOrderAnalysis extends config\UlConfig {

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
        $this->commonQueryPart = " file_type = 'PO DETAILS'";
        
        if ($this->settingVars->isDynamicPage) {
        	$this->accountField = $this->getPageConfiguration('account_field', $this->settingVars->pageID)[0];

        	$tempBuildFieldsArray = array($this->accountField);

        	$this->buildDataArray($tempBuildFieldsArray);
            $this->buildPageArray();
            
            if(count($this->gridColumnHeader) > 0)
                $this->jsonOutput['gridColumnNames'] = $this->gridColumnHeader;

        } else {
            $this->configurationFailureMessage();
        }

        $action = $_REQUEST['action'];
        switch ($action) {
            case 'topGridData';
                $this->topGridData();
				break;
			case 'bottomGridData';
                $this->bottomGridData();
                break;
        }
        
        return $this->jsonOutput;
    }
	
    private function getLatestPeriod() 
    {
        
        $query = "SELECT period FROM ".$this->settingVars->tesco_po_details." WHERE gid = ".$this->settingVars->GID." AND accountID = 143 AND ".$this->commonQueryPart." GROUP BY period ORDER BY period DESC LIMIT 4"; // accountID = 143 to run in dev // $this->settingVars->aid
        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }
        
        return $result;
    }
    
	/**
	 * topGridData()
     * This Function is used to retrieve list based on set parameters     
	 * @access private	
     * @return $this->jsonOutput with all data that should be sent to client app     
     */
    private function topGridData() {
		
        $result = $this->getLatestPeriod();
        
        if (is_array($result) && !empty($result))
        {
            $periodResult = array_column($result, "period");
            $query = "SELECT ".$this->accountIdField." AS SKUID " .
                    ",".$this->accountNameField." AS ACCOUNT " .
                    ",".$this->settingVars->tesco_po_details.".period AS PERIOD " .
                    ",SUM((CASE WHEN period IN('" . implode("','", $periodResult) . "') THEN total_placed_orders_Cases ELSE 0 END)) AS ORDERED " .
                    ",SUM((CASE WHEN period IN('" . implode("','", $periodResult) . "') THEN delivered_on_time ELSE 0 END)) AS DELIVERED " .
                    " FROM ".$this->settingVars->tesco_po_details.", ".$this->settingVars->skutable." WHERE ".$this->settingVars->tesco_po_details.".GID = ".$this->settingVars->skutable.".GID AND ".$this->settingVars->tesco_po_details.".skuID = ".$this->settingVars->skutable.".PIN AND ".$this->settingVars->skutable.".clientID = '".$this->settingVars->clientID."' AND ".$this->settingVars->skutable.".GID = ".$this->settingVars->GID." AND ".$this->commonQueryPart." GROUP BY SKUID, ACCOUNT, PERIOD ORDER BY PERIOD DESC";
            //echo $query; exit;
            $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
            if ($redisOutput === false) {
                $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
                $this->redisCache->setDataForHash($result);
            } else {
                $result = $redisOutput;
            }
            
            $tmpResult = array();
            if (is_array($result) && !empty($result))
            {
                foreach($result as $data)
                    $tmpResult[$data['SKUID']."##".$data['ACCOUNT']][$data['PERIOD']] = $data;
                
                foreach($tmpResult as $tmpKey => $tmpData)
                {
                    $tmp = array();
                    $extra = explode("##", $tmpKey);
                    $tmp["0_0SKUID"] = $extra[0];
                    $tmp["0_01ACCOUNT"] = $extra[1];
                    
                    foreach($periodResult as $key => $period)
                    {
                        $tmp[$key."_1ORDERED_".$period] = (double)$tmpData[$period]["ORDERED"];
                        $tmp[$key."_2DOT_".$period] = ($tmpData[$period]["ORDERED"] != 0) ? ($tmpData[$period]["DELIVERED"]/$tmpData[$period]["ORDERED"])*100 : 0;
                        $tmp[$key."_2DOT_".$period] = (double)number_format($tmp[$key."_2DOT_".$period], 1, '.', '');
                    }
                    $finalData[] = $tmp;
                }
            }
        }

        $this->jsonOutput['topGridData'] = $finalData;
    }
	
	/**
	 * bottomGridData()
     * This Function is used to retrieve list based on set parameters     
	 * @access private	
     * @return $this->jsonOutput with all data that should be sent to client app     
     */
    private function bottomGridData() {
		
        $result = $this->getLatestPeriod();
        
        if (is_array($result) && !empty($result))
        {
            $periodResult = array_column($result, "period");
            $query = "SELECT ".$this->accountIdField." AS SKUID " .
                    ",MAX(".$this->accountNameField.") AS ACCOUNT " .
                    ",".$this->settingVars->tesco_po_details.".purchase_order_number AS PON " .
                    ",MAX(".$this->settingVars->tesco_po_details.".depot_number) AS DEPOT_NO " .
                    ",".$this->settingVars->tesco_po_details.".period AS PERIOD " .
                    ",MAX(".$this->settingVars->tesco_po_details.".delivered_on_time) AS DOT " .
                    ",SUM((CASE WHEN period IN('" . implode("','", $periodResult) . "') THEN total_placed_orders_Cases ELSE 0 END)) AS ORDERED " .
                    ",SUM((CASE WHEN period IN('" . implode("','", $periodResult) . "') THEN delivered_on_time ELSE 0 END)) AS DELIVERED " .
                    " FROM ".$this->settingVars->tesco_po_details.", ".$this->settingVars->skutable." WHERE ".$this->settingVars->tesco_po_details.".GID = ".$this->settingVars->skutable.".GID AND ".$this->settingVars->tesco_po_details.".skuID = ".$this->settingVars->skutable.".PIN AND ".$this->settingVars->skutable.".clientID = '".$this->settingVars->clientID."' AND ".$this->settingVars->skutable.".GID = ".$this->settingVars->GID." AND ".$this->settingVars->tesco_po_details.".skuID = '".$_REQUEST['selectedSKUID']."' AND ".$this->settingVars->tesco_po_details.".period IN ('".implode("','", $periodResult)."') AND ".$this->commonQueryPart." GROUP BY SKUID, PERIOD, PON ORDER BY PERIOD DESC";
            //echo $query; exit;
            $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
            if ($redisOutput === false) {
                $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
                $this->redisCache->setDataForHash($result);
            } else {
                $result = $redisOutput;
            }
            
            if (is_array($result) && !empty($result))
            {
                foreach($result as $data)
                {
                    $tmp = array();
                    $tmp["SKUID"] = $data["SKUID"];
                    $tmp["ACCOUNT"] = $data["ACCOUNT"];
                    $tmp["PO"] = (int)$data["PON"];
                    $tmp["DEPOT"] = (int)$data["DEPOT_NO"];
                    $tmp["PERIOD"] = (int)$data["PERIOD"];
                    $tmp["ORDERED"] = (int)$data["ORDERED"];
                    $tmp["DOT"] = (int)$data["DOT"];
                    $tmp["DOT_PER"] = ($data["ORDERED"] != 0) ? ($data["DELIVERED"]/$data["ORDERED"])*100 : 0;
                    $tmp["DOT_PER"] = (double)number_format($tmp["DOT_PER"], 1, '.', '');
                    $tmp["SHORT_CASES"] = $data["ORDERED"]-$data["DOT"];
                    $finalData[] = $tmp;
                }
            }
            $this->jsonOutput['bottomGridData'] = $finalData;
        }
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

            $bottomGridColumns['SKUID'] = $topGridColumns['0_0SKUID'] =  (count($gridFieldPart) > 1) ? $this->displayCsvNameArray[$gridFieldPart[1]] : $this->displayCsvNameArray[$gridFieldPart[0]];
            $bottomGridColumns['ACCOUNT'] = $topGridColumns['0_01ACCOUNT'] =  $this->displayCsvNameArray[$gridFieldPart[0]];
            
            $result = $this->getLatestPeriod();
            if (is_array($result) && !empty($result))
            {
                foreach($result as $key => $period)
                {
                    $topGridColumns[$key.'_1ORDERED_'.$period['period']] = $period['period']." ORDERED";
                    $topGridColumns[$key.'_2DOT_'.$period['period']] = $period['period']." DoT %";
                }
            }
            
            $this->jsonOutput['topGridColumns'] = $topGridColumns;
            $this->jsonOutput['bottomGridColumns'] = $bottomGridColumns;
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