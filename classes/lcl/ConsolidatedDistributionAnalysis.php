<?php

namespace classes\lcl;

use projectsettings;
use datahelper;
use filters;
use db;
use config;

class ConsolidatedDistributionAnalysis extends config\UlConfig {

    private $timeRange;
    private $pageName;
    private $planoStores;

    /*     * ***
     * Default gateway function, should be similar in all DATA CLASS
     * arguments:
     *  $settingVars [project settingsGateway variables]
     * @return $jsonOutput with all data that should be sent to client app
     * *** */

    public function go($settingVars) {
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES
        
        $this->redisCache = new \utils\RedisCache($this->queryVars);
        
        $this->pageName = $_REQUEST["pageName"];
        $this->settingVars->pageName = $this->pageName; // set pageName in settingVars

        $this->queryPart = $this->getAll();
        $this->timeRange = "AND CONCAT(" . $this->settingVars->yearperiod . "," . $this->settingVars->weekperiod . ") IN (" . implode(",", filters\timeFilter::getYearWeekWithinRange(0, $_REQUEST['timeFrame'], $this->settingVars)) . ") ";

        $action = $_REQUEST["action"];
		
		if (empty($_REQUEST["timeFrame"]))
            $_REQUEST["timeFrame"] = 1;
		
        switch ($action) {
            case "fetchConfig": $this->fetchConfig();
                break;
            case "reload": $this->reload();
                break;
            case "getSellingNotSelling": $this->changeGrid();
                break;
        }
		return $this->jsonOutput;
    }

    public function fetchConfig(){

        if(count(explode('.', $this->settingVars->dataArray[$this->settingVars->pageArray[$this->pageName]["totalStoresAccounts"]]["NAME"])) != 2)
            $this->configurationFailureMessage();
        if(count(explode('.', $this->settingVars->dataArray[$this->settingVars->pageArray[$this->pageName]["regionAccount"]]["NAME"])) != 2)
            $this->configurationFailureMessage();

        $this->jsonOutput["BANNER_FIELD_NAME"] = $this->settingVars->dataArray[$this->settingVars->pageArray[$this->pageName]["totalStoresAccounts"]]["NAME_ALIASE"];
        $this->jsonOutput["PIN_FIELD_NAME"] = $this->settingVars->dataArray[$this->settingVars->pageArray[$this->pageName]["pinAccount"]]["ID_ALIASE"];
        $this->jsonOutput["REGION_FIELD_NAME"] = $this->settingVars->dataArray[$this->settingVars->pageArray[$this->pageName]["regionAccount"]]["NAME_ALIASE"];
    
        $this->jsonOutput["TOP_GRID_COLUMN_NAME"] = $this->settingVars->pageArray[$this->pageName]["TOP_GRID_COLUMN_NAME"];
        $this->jsonOutput["SELLING"] = $this->settingVars->pageArray[$this->pageName]["SELLING_GRID_COLUMN_NAME"];
        $this->jsonOutput["NOTSELLING"] = $this->settingVars->pageArray[$this->pageName]["NOT_SELLING_GRID_COLUMN_NAME"];
    }

    private function reload() {
        $this->gridValue(); //ADDING TO OUTPUT        
    }

    private function changeGrid() {        
        $this->getSellingStores(); //ADDING TO OUTPUT
        $this->getNotSellingStores(); //ADDING TO OUTPUT
    }

    private function gridValue() {
        $gridData = array();
        $planoOnly = $this->settingVars->isPlanoOnly;
        //COLLECT SELLING STORES
        $this->collectSellingStores();
		
		//COLLECT LIVE STORES
		$this->collectLiveStores();		
		
		//COLLECT PLANO STORES	
		$this->collectPlanoStoresSales();
		
        $gridAccounts = explode("-", $this->settingVars->pageArray[$this->pageName]['gridAccounts']);
        $selectPart = array();
        $groupByPart = array();
        $gridAccountListToFetchQueryDataLater = array();

        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        
        $tableNameinField = true;

        foreach ($gridAccounts as $key => $data) {
            if (key_exists('ID', $this->settingVars->dataArray[$data])) {
                $selectPart[] = $this->settingVars->dataArray[$data]['ID'] . " AS '" . $this->settingVars->dataArray[$data]['ID_ALIASE'] . "'";
                $groupByPart[] = $gridAccountListToFetchQueryDataLater[] = "'" . $this->settingVars->dataArray[$data]['ID_ALIASE'] . "'";
                $this->measureFields[] = $this->settingVars->dataArray[$data]['ID'];
                if(count(explode('.', $this->settingVars->dataArray[$data]['ID'])) != 2)
                    $tableNameinField = false;

                $selectPart[] = "MAX(" . $this->settingVars->dataArray[$data]['NAME'] . ") AS '" . $this->settingVars->dataArray[$data]['NAME_ALIASE'] . "'";
                $gridAccountListToFetchQueryDataLater[] = $this->settingVars->dataArray[$data]['NAME_ALIASE'];
                $this->measureFields[] = $this->settingVars->dataArray[$data]['NAME'];
                if(count(explode('.', $this->settingVars->dataArray[$data]['NAME'])) != 2)
                    $tableNameinField = false;
            } else {
                $selectPart[] = $this->settingVars->dataArray[$data]['NAME'] . " AS '" . $this->settingVars->dataArray[$data]['NAME_ALIASE'] . "'";
                $groupByPart[] = $gridAccountListToFetchQueryDataLater[] = "'" . $this->settingVars->dataArray[$data]['NAME_ALIASE'] . "'";
                $this->measureFields[] = $this->settingVars->dataArray[$data]['NAME'];
                if(count(explode('.', $this->settingVars->dataArray[$data]['NAME'])) != 2)
                    $tableNameinField = false;
            }
        }

        if(!$tableNameinField){
            $this->configurationFailureMessage();
        }

        $this->settingVars->useRequiredTablesOnly = true;
            if (is_array($this->measureFields) && !empty($this->measureFields)) {
                $this->prepareTablesUsedForQuery($this->measureFields);
            }

        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll

        $query = "SELECT " . implode(",", $selectPart) .
                ",SUM(".$this->settingVars->ProjectValue.") AS SALES " .
                " FROM " . $this->settingVars->tablename . $this->queryPart . $this->timeRange . " " .
                " GROUP BY " . implode(",", $groupByPart) .
                " ORDER BY SALES DESC ";

        
        //print $query; exit;
		
        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }
        
        foreach ($result as $key => $data) {

            $data["PIN"] = $data["PIN_A"];
        
			$sellingStoresIndexStr = "";
            foreach ($GLOBALS['sellingStoresGroupByPart'] as $account) {
                $sellingStoresIndexStr .='[$data[' . $account . ']]';
            }

            $sellingStoresCode = '$sellingStores = $GLOBALS[sellingStoresArray]' . $sellingStoresIndexStr . ';';
            //print $sellingStoresCode;exit;
            eval($sellingStoresCode);
			
			$liveStoresIndexStr = "";
            foreach ($GLOBALS['liveStoresGroupByPart'] as $account) {
                $liveStoresIndexStr .='[$data[' . $account . ']]';
            }
            $liveStoresCode = '$liveStores = $GLOBALS[liveStoresArray]' . $liveStoresIndexStr . ';';
            //print $sellingStoresCode;exit;
            eval($liveStoresCode);
						
			$planoStoresIndexStr = "";
            foreach ($GLOBALS['planoStoresAccounts'] as $account) {
                $planoStoresIndexStr .='[$data[' . $account . ']]';
            }
            $planoStoresCode = '$planoStore = $GLOBALS[planoStoresArray]' . $planoStoresIndexStr . ';';
            eval($planoStoresCode);

			$planoSellingIndexStr = "";
            foreach ($GLOBALS['planoSellingAccounts'] as $account) {
                $planoSellingIndexStr .='[$data[' . $account . ']]';
            }
            $planoSellingCode = '$planoSellingStores = $GLOBALS[planoSellingArray]' . $planoSellingIndexStr . ';';
            eval($planoSellingCode);
						
			$wcrossSelling		= $sellingStores != 0 ? $data['SALES']/$sellingStores/$_REQUEST['timeFrame'] : 0;
			//$distPctPlano       = $planoStores >0 ? ($sellingStores/$planoStores)*100 : 0;
			// $distPctPlano       = $this->planoStores[$data['PIN_A']]['STORES'] >0 ? ($sellingStores/$this->planoStores[$data['PIN_A']]['STORES'])*100 : 0;
			$distPctLive        = $liveStores >0 ? ($sellingStores/$liveStores)*100 : 0;

            /* $planoStore = array_key_exists($data['PIN_A'], $this->planoStores) ? (int)$this->planoStores[$data['PIN_A']][$data['PROVINCE']][$data['BANNER']]['STORES'] : 0; */
            
            if (($planoOnly && $planoStore > 0) || (!$planoOnly)) {
                $temp = array();
                foreach ($gridAccountListToFetchQueryDataLater as $gridAccount) {
                    $xmlTag = str_replace("'", "", str_replace(" ", "", $gridAccount));
                    $temp[$xmlTag] = htmlspecialchars_decode($data[str_replace("'", "", $gridAccount)]);
                }
                $temp["SALES"] = $data['SALES'];
                $temp["LIVE_STORES"] = $liveStores;
                $temp["PLANO_STORES"] = (isset($planoStore)) ? $planoStore : 0;
                $temp["SLNG_STORES"] = $sellingStores;
                $temp["WCROS_SELLING"] = number_format($wcrossSelling, 2, '.', '');
                $temp["DIST_PCT_LIVE"] = number_format($distPctLive, 1, '.', '');
                /* $temp["PLANO_SELLING"] = array_key_exists($data['PIN_A'], $this->planoStores) ? (int)$this->planoStores[$data['PIN_A']][$data['PROVINCE']][$data['BANNER']]['STORESSALES'] : 0; */
				$temp["PLANO_SELLING"] = (isset($planoSellingStores)) ? $planoSellingStores : 0;
				
                $temp["DIST_PCT_PLANO"] = ($temp["PLANO_STORES"] > 0) ? (number_format((($temp["PLANO_SELLING"]/$temp["PLANO_STORES"])*100), 1, '.', '')) : 0;
    			/* $temp["PLANO_N_SELLING"] = array_key_exists($data['PIN_A'], $this->planoStores) ? (int)$this->planoStores[$data['PIN_A']][$data['PROVINCE']][$data['BANNER']]['STORES'] - $this->planoStores[$data['PIN_A']][$data['PROVINCE']][$data['BANNER']]['STORESSALES'] : 0; */
				$temp["PLANO_N_SELLING"] = (int)$temp["PLANO_STORES"] - (int)$temp["PLANO_SELLING"];

                $gridData[] = $temp;
                
            }
        }

        $this->jsonOutput["gridData"] = $gridData;
    }

    private function getSellingStores() {
        //PROVIDING GLOBALS
        global $sellingStores;
		
		$GLOBALS['planodStoresForSelectedSku'] = array();
		//$GLOBALS['planodStoresForSelectedSku']  = $this->utils_collectPlanodStoresForSpecificSku();
		
		$GLOBALS['sellingStoresForSelectedSku'] = array();

        $this->settingVars->tableUsedForQuery = $this->measureFields = array();

        $qpart = "";
        /*         * ************* APPLY SKU AND BANER FILTER ******************* */
        if ($_REQUEST["SKU"] != ""){
            $qpart.= " AND " . $this->settingVars->maintable . ".PIN = " . $_REQUEST["SKU"] . " ";
            $this->measureFields[] = $this->settingVars->maintable . ".PIN";
        }
        
        if ($_REQUEST["BANNER"] != ""){
            $qpart.= " AND ". $this->settingVars->dataArray[$this->settingVars->pageArray[$this->pageName]["totalStoresAccounts"]]["NAME"] ." = '" . $_REQUEST["BANNER"] . "' ";
            $this->measureFields[] = $this->settingVars->dataArray[$this->settingVars->pageArray[$this->pageName]["totalStoresAccounts"]]["NAME"];
        }
        if ($_REQUEST["REGION"] != ""){
            $qpart.= " AND ". $this->settingVars->dataArray[$this->settingVars->pageArray[$this->pageName]["regionAccount"]]["NAME"] ." = '" . $_REQUEST["REGION"] . "' ";
            $this->measureFields[] = $this->settingVars->dataArray[$this->settingVars->pageArray[$this->pageName]["regionAccount"]]["NAME"];
        }

        /*         * ************************************************************* */
        
        $this->measureFields[] = $this->settingVars->storetable . ".SNAME";

        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }

        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll

        $query = "SELECT " . $this->settingVars->maintable . ".SNO AS SNO" .
                ",SNAME AS NAME " .
				",SUM(".$this->settingVars->ProjectValue.") AS SALES ".
                "FROM " . $this->settingVars->tablename . $this->queryPart . $this->timeRange . $qpart . " " .
                "GROUP BY SNO,SNAME ".
				"HAVING SALES>0 ".
				"ORDER BY SALES DESC";
        
        //print $query;exit;
        
        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }
        
        $query = "SELECT DISTINCT planogramme, SNO FROM ".$this->settingVars->storeplanotable; 
        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $storePlanoResult = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $storePlanoResult = $redisOutput;
        }
        
        $storePlano = array();
        
        if(is_array($storePlanoResult) && !empty($storePlanoResult))
        {
            foreach($storePlanoResult as $data)
                $storePlano[$data['SNO']] = $data['planogramme'];
        }
        
        $sellingStores = array();
        for ($i = 0; $i < count($result); $i++) {			
            $data = $result[$i];
			$GLOBALS['sellingStoresForSelectedSku'][] = $data['SNO'];
            array_push($sellingStores, $data['SNO']);
            $temp = array();
            $temp["SNO"] = $data['SNO'];
            $temp["SNAME"] = htmlspecialchars_decode($data['NAME']);
            $temp["SALES"] = $data['SALES'];
            $temp["PLANO_NAME"] = $storePlano[$data['SNO']];
            //$temp["PLANO_D"] = (in_array($data['SNO'],$GLOBALS['planodStoresForSelectedSku'])) ? 'YES' : 'NO';
            $this->jsonOutput["sellingStores"][] = $temp;
        }
    }

    private function getNotSellingStores() {
        //INITIATED ND POPULATED  @getSellingStores
        global $sellingStores;
		
        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
		
        $qpart = "";
        /*         * ************* APPLY SKU AND BANER FILTER ******************* */
        
        if ($_REQUEST["BANNER"] != ""){
            $qpart.= " AND ". $this->settingVars->dataArray[$this->settingVars->pageArray[$this->pageName]["totalStoresAccounts"]]["NAME"] ." = '" . $_REQUEST["BANNER"] . "' ";
		    $this->measureFields[] = $this->settingVars->dataArray[$this->settingVars->pageArray[$this->pageName]["totalStoresAccounts"]]["NAME"];
        }
		if ($_REQUEST["REGION"] != ""){
            $qpart.= " AND ". $this->settingVars->dataArray[$this->settingVars->pageArray[$this->pageName]["regionAccount"]]["NAME"] ." = '" . $_REQUEST["REGION"] . "' ";
            $this->measureFields[] = $this->settingVars->dataArray[$this->settingVars->pageArray[$this->pageName]["regionAccount"]]["NAME"];
        }
        /*         * ************************************************************* */
        
        $notSellingStores = array();

        $this->measureFields[] = $this->settingVars->storetable . ".SNAME";

        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }

        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll
		
        //COLLECTING NOT SELLING STORES		
        /* $query = "SELECT " . $this->settingVars->maintable . ".SNO AS SNO" .
                ",SNAME AS NAME " .                
                ",if (SUM(" . $this->settingVars->ProjectVolume . ")>0,'YES','NO') AS ISactive " .
                "FROM " . $this->settingVars->tablename . $this->queryPart . $qpart . $this->timeRange ;
        
        if (is_array($sellingStores) && !empty($sellingStores))
            $query .= " AND " . $this->settingVars->maintable . ".SNO NOT IN (" . implode(",", $sellingStores) . ") ";
        
        $query .= "GROUP BY SNO,NAME ORDER BY SNO DESC"; */
        //print $query;exit;
        
        $pPlanoTable    = $this->settingVars->productplanotable;
        $sPlanoTable    = $this->settingVars->storeplanotable;
        $skutable       = $this->settingVars->skutable;        
        $storetable     = $this->settingVars->storetable;        
        
        $query	= "SELECT  ".$sPlanoTable.".SNO AS SNO ".
                    ",MAX(".$storetable.".SNAME) as NAME ".
                    ",MAX(".$sPlanoTable.".planogramme) as PLANO_NAME ".
					"FROM ".$pPlanoTable.",".$sPlanoTable.",".$skutable.",".$storetable." ".
					"WHERE ".$skutable.".PIN=".$pPlanoTable.".article ".
					"AND ".$skutable.".GID=".$pPlanoTable.".GID ".
					"AND ".$sPlanoTable.".planogramme=".$pPlanoTable.".planogram ".
					"AND ".$sPlanoTable.".SNO=".$storetable.".SNO ".
					"AND ".$sPlanoTable.".category=".$pPlanoTable.".cat ".
                    "AND ".$skutable.".hide <> 1 ".
                    "AND ".$skutable.".gid IN (".$this->settingVars->GID.") ".
                    "AND ".$storetable.".gid IN (".$this->settingVars->GID.") ".
                    "AND ".$skutable.".clientID = '".$this->settingVars->clientID."' ".
                    "AND ".$sPlanoTable.".SNO NOT IN (".implode(",",$sellingStores).") ".$qpart.
                    "AND ".$pPlanoTable.".article = ".$_REQUEST["SKU"]." ".
					"GROUP BY SNO";
        

        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $notSellingData = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $notSellingData = $redisOutput;
        }
        
        /* foreach ($notSellingData as $key => $data) {
            $notSellingStores[] = $data['SNO'];
        } */
		
        $notSellingStores = array_column($notSellingData, "SNO");
        
        $companySalesArrayForNotSellingStores = $this->utils_collectCompanySalesForNotSellingStores($notSellingStores);
        list($activeStoreStatusList,$activeStoreDateLastStoreList) = $this->utils_collectActiveStoreStatus($notSellingStores);
        
        $dateLastSold = array();
        if (count($notSellingStores) > 0) {
            //COLLECTING DATES_LAST_SOLD : the latest mydate where sumValue>0 for that pin/SNO
            if ($_REQUEST["SKU"] != "")
                $qpart.= " AND " . $this->settingVars->maintable . ".PIN = " . $_REQUEST["SKU"] . " ";
            $query = "SELECT " . $this->settingVars->maintable . ".SNO AS SNO" .
                    ",DATE_FORMAT(MAX(" . $this->settingVars->dateField . ") , '%d %b %Y') AS DATE_LAST_SOLD " .
                    "FROM " . $this->settingVars->tablename . $this->queryPart . $qpart . " " .
                    "AND " . $this->settingVars->maintable . ".SNO IN (" . implode(",", $notSellingStores) . ") " .
                    "AND " . $this->settingVars->ProjectValue . ">0 " .
                    "GROUP BY SNO ";
             
            //print $query;exit;
            
            $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
            if ($redisOutput === false) {
                $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
                $this->redisCache->setDataForHash($result);
            } else {
                $result = $redisOutput;
            }
            
            foreach ($result as $key => $data) {
                $dateLastSold[$data['SNO']] = $data['DATE_LAST_SOLD'];
            }
        }

        foreach ($notSellingData as $key => $data) {
            $temp = array();
            $temp["SNO"] = $data['SNO'];
            $temp["PLANO_NAME"] = $data['PLANO_NAME'];
            $temp["SNAME"] = htmlspecialchars_decode($data['NAME']);
            if(isset($this->settingVars->isActiveStoreFromDateLastSoldStoreTbl) && $this->settingVars->isActiveStoreFromDateLastSoldStoreTbl == true){
                $isActive = isset($activeStoreStatusList[$data['SNO']]) ? $activeStoreStatusList[$data['SNO']] : 'No';
                /*$dateLastSold = isset($activeStoreDateLastStoreList[$data['SNO']]) ? $activeStoreDateLastStoreList[$data['SNO']] : '';*/
            } else {
                $isActive = (isset($companySalesArrayForNotSellingStores[$data['SNO']])) ? "Yes" : "No";
            }
            $temp["ACTIVE"] = $isActive;

            $temp["COMPANY_SALES"] = (isset($companySalesArrayForNotSellingStores[$data['SNO']])) ? $companySalesArrayForNotSellingStores[$data['SNO']] : 0;
            //$temp["PLANO_D"] = (in_array($data['SNO'],$GLOBALS['planodStoresForSelectedSku'])) ? 'YES' : 'NO';
			$temp["DATE_LAST_SOLD"] = $dateLastSold[$data['SNO']];
            $this->jsonOutput["notSellingStores"][] = $temp;
        }
    }

    /*     * ****************** HELPING FUNCTIONS ********************************* */

    private function collectSellingStores() {
        $sellingStoresAccounts = explode("-", $this->settingVars->pageArray[$this->pageName]['sellingStoresAccounts']);
        $selectPart = array();
        $GLOBALS['sellingStoresGroupByPart'] = array();
        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $tableNameinField = true;
        foreach ($sellingStoresAccounts as $key => $data) {
            if (key_exists('ID', $this->settingVars->dataArray[$data])) {
                $selectPart[] = $this->settingVars->dataArray[$data]['ID'] . " AS '" . $this->settingVars->dataArray[$data]['ID_ALIASE'] . "'";
                $GLOBALS['sellingStoresGroupByPart'][] = "'" . $this->settingVars->dataArray[$data]['ID_ALIASE'] . "'";
                $this->measureFields[] = $this->settingVars->dataArray[$data]['ID'];
                if(count(explode('.', $this->settingVars->dataArray[$data]['ID'])) != 2)
                    $tableNameinField = false;

            } else {
                $selectPart[] = $this->settingVars->dataArray[$data]['NAME'] . " AS '" . $this->settingVars->dataArray[$data]['NAME_ALIASE'] . "'";
                $GLOBALS['sellingStoresGroupByPart'][] = "'" . $this->settingVars->dataArray[$data]['NAME_ALIASE'] . "'";
                $this->measureFields[] = $this->settingVars->dataArray[$data]['NAME'];
                if(count(explode('.', $this->settingVars->dataArray[$data]['NAME'])) != 2)
                    $tableNameinField = false;
            }
        }

        if(!$tableNameinField){
            $this->configurationFailureMessage();
        }

        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }

        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll

        $query = "SELECT " . implode(",", $selectPart) .
                ",COUNT(DISTINCT((CASE WHEN ".$this->settingVars->ProjectValue.">0 THEN 1 END)*" . $this->settingVars->maintable . ".SNO)) AS STORES " .
                "FROM " . $this->settingVars->tablename . $this->queryPart . $this->timeRange . " " .
                "GROUP BY " . implode(",", $GLOBALS['sellingStoresGroupByPart']);
        
        

        

        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }
        
        $GLOBALS['sellingStoresArray'] = array();
        foreach ($result as $key => $data) {
            $codeStr = "";
            $indexStr = "";
            foreach ($GLOBALS['sellingStoresGroupByPart'] as $account) {
                $indexStr .='[$data[' . $account . ']]';
            }
            $codeStr = '$GLOBALS["sellingStoresArray"]' . $indexStr . ' = $data["STORES"];';
            eval($codeStr);
        }

        /*         * * USEFULL WHEN DEBUGGING , PLZ DON'T DELETE ** */
        /*echo '<pre>';
        print_r($GLOBALS["sellingStoresArray"]);exit;*/
    }
		
	private function collectLiveStores() {
        $sellingStoresAccounts = explode("-", $this->settingVars->pageArray[$this->pageName]['liveStoresAccounts']);
        $selectPart = array();
        $GLOBALS['liveStoresGroupByPart'] = array();
        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $tableNameinField = true;
        foreach ($sellingStoresAccounts as $key => $data) {
            if (key_exists('ID', $this->settingVars->dataArray[$data])) {
                $selectPart[] = $this->settingVars->dataArray[$data]['ID'] . " AS '" . $this->settingVars->dataArray[$data]['ID_ALIASE'] . "'";
                $GLOBALS['liveStoresGroupByPart'][] = "'" . $this->settingVars->dataArray[$data]['ID_ALIASE'] . "'";
                $this->measureFields[] = $this->settingVars->dataArray[$data]['ID'];
                if(count(explode('.', $this->settingVars->dataArray[$data]['ID'])) != 2)
                    $tableNameinField = false;
            } else {
                $selectPart[] = $this->settingVars->dataArray[$data]['NAME'] . " AS '" . $this->settingVars->dataArray[$data]['NAME_ALIASE'] . "'";
                $GLOBALS['liveStoresGroupByPart'][] = "'" . $this->settingVars->dataArray[$data]['NAME_ALIASE'] . "'";
                $this->measureFields[] = $this->settingVars->dataArray[$data]['NAME'];
                if(count(explode('.', $this->settingVars->dataArray[$data]['NAME'])) != 2)
                    $tableNameinField = false;
            }
        }

        if(!$tableNameinField){
            $this->configurationFailureMessage();
        }

        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }

        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll

        $query = "SELECT " . implode(",", $selectPart) .
                ",COUNT(DISTINCT((CASE WHEN ".$this->settingVars->ProjectValue.">0 THEN 1 END)*".$this->settingVars->maintable.".SNO)) AS TOTAL_STORES ".
                "FROM " . $this->settingVars->tablename . $this->queryPart . $this->timeRange . " " .
                "GROUP BY " . implode(",", $GLOBALS['liveStoresGroupByPart']);
        
        //print $query;exit;
        
        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }
        
        $GLOBALS['liveStoresArray'] = array();
        foreach ($result as $key => $data) {
            $codeStr = "";
            $indexStr = "";
            foreach ($GLOBALS['liveStoresGroupByPart'] as $account) {
                $indexStr .='[$data[' . $account . ']]';
            }
            $codeStr = '$GLOBALS["liveStoresArray"]' . $indexStr . ' = $data["TOTAL_STORES"];';
            eval($codeStr);
        }

        /** * USEFULL WHEN DEBUGGING , PLZ DON'T DELETE ** */
        //echo '<pre>';
        //print_r($GLOBALS["liveStoresArray"]);exit;
    }
		
    /*************************************************************************************************************/
		
	//UTILITY FUNCTIONS
    private function utils_collectPlanodStoresForSpecificSku(){
		/* $query = "SELECT sno AS SNO ".
				"FROM ".$this->settingVars->masterplanotable." a ".
				"WHERE a.pin = ".$_REQUEST['SKU']." ".
                "AND a.accountID=".$this->settingVars->aid." ".
                "AND a.projectID=".$this->settingVars->projectID." ".
				"GROUP BY SNO"; */
                
        $query = "SELECT ".$this->settingVars->storeplanotable.".SNO AS SNO ".
				"FROM ".$this->settingVars->storeplanotable.",".$this->settingVars->maintable." ".
				"WHERE ".$this->settingVars->maintable.".PIN = ".$_REQUEST['SKU']." ".
                "AND ".$this->settingVars->maintable.".SNO=".$this->settingVars->storeplanotable.".SNO ".
				"GROUP BY SNO";
                
		//echo $query;exit;

        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }
        
		$planodStoresForSelectedSku = array();
		foreach($result as $data)
		{
			$planodStoresForSelectedSku[] = $data['SNO'];
		}
		
		return $planodStoresForSelectedSku;
    }
	
	//UTILITY FUNCTIONS
    private function utils_collectCompanySalesForNotSellingStores($getsno) {

        if(empty($getsno))
            return array();

		//$getsno = (count($GLOBALS['sellingStoresForSelectedSku'])>0) ? implode("," , $GLOBALS['sellingStoresForSelectedSku']) : 0;
        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $this->measureFields[] = $this->settingVars->storetable.".SNO";
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }

        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll
		$query = "SELECT ".$this->settingVars->maintable.".SNO AS SNO ".
				",SUM(".$this->settingVars->ProjectValue.") AS SALES ".
				"FROM ".$this->settingVars->tablename.$this->queryPart . $this->timeRange." ".
				"AND ".$this->settingVars->storetable.".SNO IN (".implode(",",$getsno).") ".
				"GROUP BY SNO";
        
		//print $query;exit;

        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }
        
		$companySalesArrayForNotSellingStores = array();
		foreach ($result as $data) {
			$companySalesArrayForNotSellingStores[$data['SNO']] = $data['SALES'];
		}
		return $companySalesArrayForNotSellingStores;
    }

    private function utils_collectActiveStoreStatus($getsno) {

        if (empty($getsno))
            return array();

        $activeStoreList = $activeStoreDateLastStoreList = [];
        if(isset($this->settingVars->isActiveStoreFromDateLastSoldStoreTbl) && $this->settingVars->isActiveStoreFromDateLastSoldStoreTbl == true){

            $maxDateLastSold = '';
            $query = "SELECT MAX(".$this->settingVars->storetable.".dateLastSold) AS dateLastSold FROM ".$this->settingVars->storetable." WHERE ".$this->settingVars->storetable.".gid IN (".$this->settingVars->GID.") ";
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);

            if(!empty($result))
                $maxDateLastSold = $result[0]['dateLastSold'];

            if (!empty($maxDateLastSold)) {
                //", CASE WHEN ".$this->settingVars->storetable.".dateLastSold >= CURRENT_DATE - INTERVAL ".$this->settingVars->activeStoreDateLastSoldDate." DAY THEN 'Yes' ELSE 'No' END AS activeStore ".
                $query = "SELECT DISTINCT ".$this->settingVars->storetable.".SNO AS SNO".
                    //", DATE_FORMAT(".$this->settingVars->storetable.".dateLastSold, '%d %b %Y') AS DATE_LAST_SOLD " .
                    ", CASE WHEN ".$this->settingVars->storetable.".dateLastSold = '".$maxDateLastSold."' THEN 'Yes' ELSE 'No' END AS activeStore ".
                    "FROM ".$this->settingVars->storetable." WHERE ".$this->settingVars->storetable.".gid = ".$this->settingVars->GID." AND ".$this->settingVars->storetable.".SNO IN (".implode(",",$getsno).") ";

                $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
                if (!empty($result)) {
                    $activeStoreList = array_column($result, 'activeStore','SNO');
                    //$activeStoreDateLastStoreList = array_column($result, 'DATE_LAST_SOLD','SNO');
                }
            }
        }
        return array($activeStoreList,$activeStoreDateLastStoreList);
    }

	private function collectPlanoStoresSales(){
	
		$planoStoresAccounts = explode("-", $this->settingVars->pageArray[$this->pageName]['planoStoresAccounts']);
	
        $selectPart = array();
        $GLOBALS['planoStoresAccounts'] = array();
        foreach ($planoStoresAccounts as $key => $data) {
            if (key_exists('ID', $this->settingVars->dataArray[$data])) {
                $selectPart[] = $this->settingVars->dataArray[$data]['ID'] . " AS '" . $this->settingVars->dataArray[$data]['ID_ALIASE'] . "'";
                $GLOBALS['planoStoresAccounts'][] = "'" . $this->settingVars->dataArray[$data]['ID_ALIASE'] . "'";
            } else {
                $selectPart[] = $this->settingVars->dataArray[$data]['NAME'] . " AS '" . $this->settingVars->dataArray[$data]['NAME_ALIASE'] . "'";
                $GLOBALS['planoStoresAccounts'][] = "'" . $this->settingVars->dataArray[$data]['NAME_ALIASE'] . "'";
            }
        }
		
		/* $query = "SELECT " . implode(",", $selectPart) .
			", COUNT(DISTINCT(".$this->settingVars->masterplanotable.".SNO)) AS STORES 
			FROM (".$this->settingVars->storetable.
			" INNER JOIN ".$this->settingVars->masterplanotable." ON ".$this->settingVars->storetable.".SNO = ".$this->settingVars->masterplanotable.".SNO) ".
			" INNER JOIN ".$this->settingVars->activetable." ON ".$this->settingVars->storetable.".SNO = ".$this->settingVars->activetable.".SNO ".
			" INNER JOIN ".$this->settingVars->timetable." ON ".$this->settingVars->activetable.".mydate = ".$this->settingVars->timetable.".mydate WHERE ".$this->settingVars->masterplanotable.".accountID=".$this->settingVars->aid." AND ".$this->settingVars->masterplanotable.".projectID=".$this->settingVars->projectID." ".$this->timeRange ." GROUP BY " . implode(",", $GLOBALS['planoStoresAccounts']); */
            
           /* $query = "SELECT " . implode(",", $selectPart) .
			", COUNT(DISTINCT(".$this->settingVars->storeplanotable.".SNO)) AS STORES 
			FROM (".$this->settingVars->storetable.
			" INNER JOIN ".$this->settingVars->storeplanotable." ON ".$this->settingVars->storetable.".SNO = ".$this->settingVars->storeplanotable.".SNO) ".
			" INNER JOIN ".$this->settingVars->activetable." ON ".$this->settingVars->storetable.".SNO = ".$this->settingVars->activetable.".SNO ".
			" INNER JOIN ".$this->settingVars->timetable." ON ".$this->settingVars->activetable.".mydate = ".$this->settingVars->timetable.".mydate WHERE 1 = 1 ".$this->timeRange ." GROUP BY " . implode(",", $GLOBALS['planoStoresAccounts']);*/
            
        $query = "SELECT " . implode(",", $selectPart) .
			", COUNT(DISTINCT(".$this->settingVars->storeplanotable.".SNO)) AS STORES ". 
			" FROM ".$this->settingVars->storetable.
			" INNER JOIN (".$this->settingVars->productplanotable." INNER JOIN ".$this->settingVars->storeplanotable." ON ".$this->settingVars->productplanotable.".planogram = ".$this->settingVars->storeplanotable.".planogramme) ON ".$this->settingVars->storetable.".SNO = ".$this->settingVars->storeplanotable.".SNO".
			" WHERE ".$this->settingVars->storetable.".GID = ".$this->settingVars->GID." AND ".$this->settingVars->productplanotable.".GID = ".$this->settingVars->GID." GROUP BY " . implode(",", $GLOBALS['planoStoresAccounts']);
            
		//echo $query;exit;
		
        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }
        
        $GLOBALS['planoStoresArray'] = array();
        foreach ($result as $key => $data) {
            $codeStr = "";
            $indexStr = "";
            foreach ($GLOBALS['planoStoresAccounts'] as $account) {
                $indexStr .='[$data[' . $account . ']]';
            }
            $codeStr = '$GLOBALS["planoStoresArray"]' . $indexStr . ' = $data["STORES"];';
            eval($codeStr);
        }
        
		$planoSellingAccounts = explode("-", $this->settingVars->pageArray[$this->pageName]['planoSellingAccounts']);
	
        $selectPart = array();
        $GLOBALS['planoSellingAccounts'] = array();
        foreach ($planoSellingAccounts as $key => $data) {
            if (key_exists('ID', $this->settingVars->dataArray[$data])) {
                $selectPart[] = $this->settingVars->dataArray[$data]['ID'] . " AS '" . $this->settingVars->dataArray[$data]['ID_ALIASE'] . "'";
                $GLOBALS['planoSellingAccounts'][] = "'" . $this->settingVars->dataArray[$data]['ID_ALIASE'] . "'";
            } else {
                $selectPart[] = $this->settingVars->dataArray[$data]['NAME'] . " AS '" . $this->settingVars->dataArray[$data]['NAME_ALIASE'] . "'";
                $GLOBALS['planoSellingAccounts'][] = "'" . $this->settingVars->dataArray[$data]['NAME_ALIASE'] . "'";
            }
        }
			
		/* $query = "SELECT " . implode(",", $selectPart) . ", COUNT(DISTINCT(".$this->settingVars->maintable.".SNO)) AS STORESSALES, ".
            "SUM(".$this->settingVars->ProjectValue.") AS SALES FROM ".$this->settingVars->masterplanotable.
            " INNER JOIN (".$this->settingVars->maintable." INNER JOIN ".$this->settingVars->storetable.
            " ON ".$this->settingVars->maintable.".SNO = ".$this->settingVars->storetable.".SNO) ON (".$this->settingVars->masterplanotable.".SNO = ".$this->settingVars->maintable.".SNO) ".
            " AND (".$this->settingVars->masterplanotable.".PIN = ".$this->settingVars->maintable.".PIN) ".
            " INNER JOIN ".$this->settingVars->timetable." ON ".$this->settingVars->maintable.".".$this->settingVars->dateperiod." = ".$this->settingVars->timetable.".".$this->settingVars->dateperiod." WHERE ".
            $this->settingVars->masterplanotable.".accountID=".$this->settingVars->aid." AND ".$this->settingVars->masterplanotable.".projectID=".$this->settingVars->projectID." ".
            $this->timeRange ." GROUP BY ".implode(",", $GLOBALS['planoSellingAccounts'])." HAVING SALES > 0"; */
            
        $query = "SELECT " . implode(",", $selectPart) . ", COUNT(DISTINCT(".$this->settingVars->maintable.".SNO)) AS STORESSALES ".
            "FROM ".$this->settingVars->storeplanotable.",".$this->settingVars->productplanotable.",".$this->settingVars->maintable.",".$this->settingVars->timetable.",".$this->settingVars->storetable." ".
            "WHERE ".
            /*$this->settingVars->maintable.".PIN=".$this->settingVars->productplanotable.".article ".
            "AND ".$this->settingVars->maintable.".gid=".$this->settingVars->productplanotable.".gid ".
            "AND ".*/
            $this->settingVars->maintable.".SNO=".$this->settingVars->storeplanotable.".SNO ".
            "AND ".$this->settingVars->maintable.".SNO=".$this->settingVars->storetable.".SNO ".
            "AND ".$this->settingVars->maintable.".gid=".$this->settingVars->storetable.".GID ".
            "AND ".$this->settingVars->maintable.".mydate=".$this->settingVars->timetable.".mydate ".
            "AND ".$this->settingVars->maintable.".gid=".$this->settingVars->timetable.".gid ".
            "AND ".$this->settingVars->productplanotable.".planogram=".$this->settingVars->storeplanotable.".planogramme ".
            "AND ".$this->settingVars->productplanotable.".gid IN (".$this->settingVars->GID.") ".
            "AND ".$this->settingVars->timetable.".gid IN (".$this->settingVars->GID.") ".
            "AND ".$this->settingVars->maintable.".gid IN (".$this->settingVars->GID.") ".
            "AND ".$this->settingVars->ProjectValue." > 0 ".
            $this->timeRange ." GROUP BY ".implode(",", $GLOBALS['planoSellingAccounts'])."";
            
		//echo $query;exit;	
        
        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }        
        
        $GLOBALS['planoSellingArray'] = array();
        foreach ($result as $key => $data) {
            $codeStr = "";
            $indexStr = "";
            foreach ($GLOBALS['planoSellingAccounts'] as $account) {
                $indexStr .='[$data[' . $account . ']]';
            }
            $codeStr = '$GLOBALS["planoSellingArray"]' . $indexStr . ' = $data["STORESSALES"];';
            eval($codeStr);
        }
    }
}

?> 