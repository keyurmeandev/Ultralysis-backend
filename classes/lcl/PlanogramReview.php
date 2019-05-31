<?php
namespace classes\lcl;
use db;
use filters;
use config;

class PlanogramReview extends config\UlConfig{
    
    private $pageName;
    private $qtyArray;
    private $valArray;
    /*****
    * Default gateway function, should be similar in all DATA CLASS
    * arguments:
    *  $settingVars [project settingsGateway variables]
    * @return $jsonOutput with all data that should be sent to client app
    *****/
    public function go($settingVars){
		$this->initiate($settingVars);//INITIATE COMMON VARIABLES
        $this->pageName = $_REQUEST["pageName"];
        $this->settingVars->pageName = $this->pageName; // set pageName in settingVars
    	$this->redisCache = new \utils\RedisCache($this->queryVars);
    
		//$this->queryPart = $this->getAll();
		$this->queryCustomPart = $this->getCustomFilter();
		$this->skuID = $this->settingVars->skutable.".PIN";
		$this->storeID = $this->settingVars->storetable.".SNO";
		$this->timeRange = "AND CONCAT(" . $this->settingVars->yearperiod . "," . $this->settingVars->weekperiod . ") IN (" . implode(",", filters\timeFilter::getYearWeekWithinRange(0, $_REQUEST['timeFrame'], $this->settingVars)) . ") ";

		$action = $_REQUEST["action"];
		switch ($action)
		{
		    case "reload": $this->Reload(); break;
		    case "fetchConfig" : $this->fetchConfig(); break;
		}
		return $this->jsonOutput;
    }

    public function fetchConfig() {
    	$this->jsonOutput['pageConfig'] = array();
    	$this->Reload();
    }

    private function Reload()
    {
        $pPlanoTable    = $this->settingVars->productplanotable;
        $sPlanoTable    = $this->settingVars->storeplanotable;
        $maintable      = $this->settingVars->maintable;
        $timetable      = $this->settingVars->timetable;
        $skutable       = $this->settingVars->skutable;
        $storetable     = $this->settingVars->storetable;

        $query = "SELECT ".$pPlanoTable.".planogram AS PLANO_NAME, ".
					//"COUNT(".$pPlanoTable.".article) AS ALL_ITEM_COUNT, ".
					"COUNT(DISTINCT ".$sPlanoTable.".SNO) AS ALL_STORE_COUNT, ".
					"COUNT(DISTINCT (CASE WHEN ".$skutable.".clientID = '".$this->settingVars->clientID."' THEN ".$pPlanoTable.".article END)) AS COMPANY_ITEM_COUNT ".
					"FROM ".$pPlanoTable.",".$sPlanoTable.",".$skutable.",".$storetable." ".
					"WHERE ".$pPlanoTable.".gid IN (".$this->settingVars->GID.") ".
					"AND ".$skutable.".PIN = ".$pPlanoTable.".article ".
					"AND ".$skutable.".GID = ".$pPlanoTable.".GID ".
					"AND ".$sPlanoTable.".planogramme=".$pPlanoTable.".planogram ".
					"AND ".$sPlanoTable.".category=".$pPlanoTable.".cat ".                    
					"AND ".$sPlanoTable.".SNO=".$storetable.".SNO ".
					"AND ".$skutable.".hide <> 1 ".
					"AND ".$skutable.".gid IN (".$this->settingVars->GID.") ".
					"AND ".$skutable.".clientID = '".$this->settingVars->clientID."' ".
					" GROUP BY PLANO_NAME,".$pPlanoTable.".article HAVING COMPANY_ITEM_COUNT > 0";

		$redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $mainPlanoGridResult = $this->queryVars->queryHandler->runQuery($query,$this->queryVars->linkid,db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($mainPlanoGridResult);
        } else {
            $mainPlanoGridResult = $redisOutput;
        }

        $allDistPlanoList = array_column($mainPlanoGridResult, 'PLANO_NAME');
        $queryForAllItemCount = "SELECT ".$pPlanoTable.".planogram AS PLANO_NAME, 
									COUNT(DISTINCT ".$pPlanoTable.".article) AS ITEM_COUNT 
								FROM 
									".$pPlanoTable.", ".$sPlanoTable."
								WHERE 
									".$sPlanoTable.".planogramme = ".$pPlanoTable.".planogram and 
									".$pPlanoTable.".gid IN (10) AND 
									".$sPlanoTable.".category = ".$pPlanoTable.".cat AND 
									".$pPlanoTable.".planogram IN ('".implode("','",$allDistPlanoList)."') 
								GROUP BY ".$pPlanoTable.".planogram";

		$redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($queryForAllItemCount);
        if ($redisOutput === false) {
            $planoAllItemCountResult = $this->queryVars->queryHandler->runQuery($queryForAllItemCount,$this->queryVars->linkid,db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($planoAllItemCountResult);
        } else {
            $planoAllItemCountResult = $redisOutput;
        }
		$planoAllItemCount = array_column($planoAllItemCountResult, 'ITEM_COUNT','PLANO_NAME');
        /*[START] GETTING THE STORE SELLING RESULT*/
        	$queryp = " AND CONCAT(".$this->settingVars->yearperiod.",".$this->settingVars->weekperiod.") IN (".implode("," , filters\timeFilter::getYearWeekWithinRange(0, $_REQUEST['timeFrame'], $this->settingVars)).") ";

	        $queryForStoreSelling = "SELECT ".$sPlanoTable.".planogramme AS PLANO_NAME, ".
	        						"COUNT(DISTINCT ".$this->settingVars->maintable.".SNO) AS SNO, ".
							        "IFNULL(SUM(".$this->settingVars->ProjectValue."),0) AS SALES ".
							        "FROM ".$maintable.",".$sPlanoTable.",".$skutable.",".$timetable." ".
							        "WHERE ".$timetable.".gid IN (".$this->settingVars->GID.") ".
									"AND ".$timetable.".mydate = ".$maintable.".mydate ".
									"AND ".$timetable.".gid = ".$maintable.".GID ".
									"AND ".$skutable.".PIN = ".$maintable.".PIN ".
									"AND ".$skutable.".hide <> 1 ".
									"AND ".$skutable.".gid IN (".$this->settingVars->GID.") ".
									"AND ".$skutable.".clientID = '".$this->settingVars->clientID."' ".
									"AND ".$sPlanoTable.".SNO=".$maintable.".SNO ".
									"AND ".$maintable.".gid IN (".$this->settingVars->GID.") ".
									"AND ".$this->settingVars->ProjectValue." > 0 ".
									"AND ".$maintable.".accountID = ".$this->settingVars->aid." ".
									"AND ".$sPlanoTable.".planogramme IN ('".implode("','",$allDistPlanoList)."') ".
									$queryp.
									"GROUP BY PLANO_NAME ORDER BY SALES DESC";

			$redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($queryForStoreSelling);
	        if ($redisOutput === false) {
	            $planoStoreSellingResult = $this->queryVars->queryHandler->runQuery($queryForStoreSelling,$this->queryVars->linkid,db\ResultTypes::$TYPE_OBJECT);
	            $this->redisCache->setDataForHash($planoStoreSellingResult);
	        } else {
	            $planoStoreSellingResult = $redisOutput;
	        }

	        if(!empty($planoStoreSellingResult)){
				$allSellingStores = array_column($planoStoreSellingResult, 'SNO', 'PLANO_NAME');
				$allSellingStoresSales = array_column($planoStoreSellingResult, 'SALES', 'PLANO_NAME');

	        	// $allSellingStores = []; $allSellingStoresMappings = [];
	        	// foreach ($planoStoreSellingResult as $k => $val) {
	        	// 	$allSellingStores[$val['PLANO_NAME']][$val['SNO']] = $val['SALES'];
	        	// 	$allSellingStoresMappings[] = $val['PLANO_NAME']."_".$val['SNO'];
	        	// }
	        }
        /*[END] GETTING THE STORE SELLING RESULT*/
        /*[START] GETTING THE STORE NOT SELLING RESULT*/
	        /*$queryForStoreNotSelling = "SELECT ".$pPlanoTable.".planogram AS PLANO_NAME, ".
						"COUNT(".$sPlanoTable.".SNO) AS STORENOTSELLING ".
				        "FROM ".$pPlanoTable.",".$sPlanoTable." ".
						"WHERE ".$sPlanoTable.".planogramme=".$pPlanoTable.".planogram ".
						"AND ".$sPlanoTable.".category=".$pPlanoTable.".cat ".
	                    "AND CONCAT(".$pPlanoTable.".planogram,'_',".$sPlanoTable.".SNO) NOT IN ('".implode("','",array_unique($allSellingStoresMappings))."') ".
						"GROUP BY PLANO_NAME";

			$redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($queryForStoreNotSelling);
	        if ($redisOutput === false) {
	            $planoStoreNotSellingResult = $this->queryVars->queryHandler->runQuery($queryForStoreNotSelling,$this->queryVars->linkid,db\ResultTypes::$TYPE_OBJECT);
	            $this->redisCache->setDataForHash($planoStoreNotSellingResult);
	        } else {
	            $planoStoreNotSellingResult = $redisOutput;
	        }

	        if(!empty($planoStoreNotSellingResult)){
	        	$planoStoreNotSelling = array_column($planoStoreNotSellingResult, 'STORENOTSELLING', 'PLANO_NAME');
	        }*/
		/*[END] GETTING THE STORE NOT SELLING RESULT*/
		$allGridData = [];
		if(!empty($mainPlanoGridResult)){
			foreach ($mainPlanoGridResult as $key => $value) {

				$companySales = $planoStoresSelling = $salesPerStoreSelling = $planoStoreNotSelling = $gap = 0;
				$companySales = isset($allSellingStoresSales[$value['PLANO_NAME']]) ? $allSellingStoresSales[$value['PLANO_NAME']] : 0;
				$planoStoresSelling = isset($allSellingStores[$value['PLANO_NAME']]) ? $allSellingStores[$value['PLANO_NAME']] : 0;
				$salesPerStoreSelling = !empty($planoStoresSelling) ? ($companySales / $planoStoresSelling) : 0;
				$planoStoreNotSelling = $value['ALL_STORE_COUNT'] - $planoStoresSelling;
				$gap = ($planoStoreNotSelling * $salesPerStoreSelling);
				$AllItemCount = $planoAllItemCount[$value['PLANO_NAME']];

				$allGridData[] = [
									'PLANO_NAME'			 => $value['PLANO_NAME'],
									'ALL_ITEM_COUNT'		 => $AllItemCount,
									'COMPANY_ITEM_COUNT'	 => $value['COMPANY_ITEM_COUNT'],
									'COMPANY_SALES'		  	 => $companySales,
									'PLANO_STORES_SELLING'	 => $planoStoresSelling,
									'SALES_PER_STORE_SELLING'=> $salesPerStoreSelling,
									'PLANO_NOT_SELLING'		 => $planoStoreNotSelling,
									'GAP'					 => $gap,
								 ];
			}

			$this->jsonOutput['allGridData'] = $allGridData;
		}
    }

	/*     * ***
     * COLLECTS TABLE JOINING STRING FROM PROJECT SETTINGS
     * ALSO APPLYS FILTER CONDITIONS TO THE STRING
     * AND RETURNS $tablejoins_and_filters
     * *** */
    public function getCustomFilter() {
        $tablejoins_and_filters = '';
		if (isset($_REQUEST["FS"]) && $_REQUEST["FS"] != '') {
            $tablejoins_and_filters .= filters\productAndMarketFilter::include_product_and_market_filters($this->queryVars,$this->settingVars);
    	    foreach($_REQUEST['FS'] as $key=>$data){
        		if(!empty($data)) {
        		    $filterKey = !key_exists('ID',$this->settingVars->dataArray[$key]) ? $this->settingVars->dataArray[$key]['NAME'] : $settingVars->dataArray[$key]['ID'];
        		    if($filterKey=="CLUSTER") {
						$this->settingVars->tablename = $this->settingVars->tablename.",".$this->settingVars->clustertable;
						$tablejoins_and_filters .= " AND ".$this->settingVars->maintable.".PIN=".$this->settingVars->clustertable.".PIN ";
        		    }
        		}
    	    }
        }
        return $tablejoins_and_filters;
    }
}

?>