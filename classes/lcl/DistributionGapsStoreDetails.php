<?php
namespace classes\lcl;
use db;
use filters;
use config;

class DistributionGapsStoreDetails extends config\UlConfig{
    
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
		    case "planoload": $this->LoadPlano(); break;
		    case "fetchConfig" : $this->fetchConfig(); break;
		}
		return $this->jsonOutput;
    }

    public function fetchConfig(){
    	$this->jsonOutput['pageConfig'] = array();
    }

    private function Reload(){	
		$this->latestActiveStores();
		$this->getWeeksSalesQtyData();
		$this->getWeeksData($this->skuID,"PIN");
		$this->getWeeksData($this->storeID,"SNO");
		$this->gridValue(false);	//ADDING TO OUTPUT		
    }    
    
    function LoadPlano(){
		$this->latestActiveStores();
		$this->getWeeksSalesQtyData();
		$this->getWeeksData($this->skuID,"PIN");
		$this->getWeeksData($this->storeID,"SNO");
		$this->gridValue(true);	//ADDING TO OUTPUT
	}

	private function getWeeksSalesQtyData(){
		//PROVIDING GLOBALS
		$this->settingVars->tableUsedForQuery = $this->measureFields = array();
		$this->measureFields[] = $this->skuID;
		$this->measureFields[] = $this->storeID;
		$this->settingVars->useRequiredTablesOnly = true;
		if (is_array($this->measureFields) && !empty($this->measureFields)) {
			$this->prepareTablesUsedForQuery($this->measureFields);
		}

		$this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll
		
		$queryp = " AND CONCAT(".$this->settingVars->yearperiod.",".$this->settingVars->weekperiod.") IN (".implode("," , filters\timeFilter::getYearWeekWithinRange(0, $_REQUEST['timeFrame'], $this->settingVars)).") ";
		$query 	= "SELECT ".$this->settingVars->skutable.".PIN AS PIN".
				",".$this->settingVars->storetable.".SNO AS SNO".
				",SUM(".$this->settingVars->ProjectVolume.") AS VOLUME ".
				",SUM(".$this->settingVars->ProjectValue.") AS VALUE ".
				"FROM ".$this->settingVars->tablename.$this->queryPart.$queryp.
                " AND ".$this->settingVars->ProjectVolume." > 0 AND ".$this->settingVars->ProjectValue." > 0 ".
				"GROUP BY PIN,SNO";
		//echo $query;exit;
        
        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result	= $this->queryVars->queryHandler->runQuery($query,$this->queryVars->linkid,db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }
        
		if(is_array($result) && !empty($result))
		{
			foreach($result as $key=>$data)
			{
				$this->qtyArray[$data['PIN']][$data['SNO']] = $data['VOLUME'];
				$this->valArray[$data['PIN']][$data['SNO']] = $data['VALUE'];
			}
		}
    }

    
    private function getWeeksData($account,$title){
		//PROVIDING GLOBALS
		global $storeSales;
		global $productSales;

		$this->settingVars->tableUsedForQuery = $this->measureFields = array();
		$this->measureFields[] = $account;
		$this->settingVars->useRequiredTablesOnly = true;
		if (is_array($this->measureFields) && !empty($this->measureFields)) {
			$this->prepareTablesUsedForQuery($this->measureFields);
		}

		$this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll
		
		$queryp = " AND CONCAT(".$this->settingVars->yearperiod.",".$this->settingVars->weekperiod.") IN (".implode("," , filters\timeFilter::getYearWeekWithinRange(0, $_REQUEST['timeFrame'], $this->settingVars)).") ";
        
		$query 	= "SELECT $account AS $title ".
				",SUM(".$this->settingVars->ProjectVolume.") AS VOLUME".
				",SUM(".$this->settingVars->ProjectValue.") AS VALUE ".
				"FROM ".$this->settingVars->tablename.$this->queryPart.$queryp." ".
				"GROUP BY $account";
		//echo $query;exit;
		
        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result	= $this->queryVars->queryHandler->runQuery($query,$this->queryVars->linkid,db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }
        
		if(is_array($result) && !empty($result))
		{
			foreach($result as $key=>$data)
			{
				if ($title=="SNO") $storeSales[$data['SNO']] = $data['VALUE'];
				else if($title=="PIN")	$productSales[$data['PIN']] = $data['VALUE'];
			}
		}
    }

    private function latestActiveStores(){
		global $snoList;
		$snoList=array();
			
		$query 	= "SELECT SNO AS ACCOUNT ".
				"FROM ".$this->settingVars->activetable.",".$this->settingVars->timetable." ".
				"WHERE ".$this->settingVars->activetable.".mydate=".$this->settingVars->timetable.".mydate ".
				"GROUP BY SNO";	
		//echo $query;exit;
        
        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result	= $this->queryVars->queryHandler->runQuery($query,$this->queryVars->linkid,db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }
        
		if(is_array($result) && !empty($result))
		{
			foreach($result as $key=>$data)
			{
				array_push($snoList , $data['ACCOUNT']);
			}
		}
    }

    private function gridValue($flag){
		global $snoList, $storeSales, $productSales;
				
		$planoVal 	= trim($_GET['PLANOID']);
		$pinArr 	= array();
		$snoArr 	= array();
		$upcArr 	= array();
		$aggArr 	= array();
		$bannerArr 	= array();
		$proArr 	= array();
		
        $plQpart = "";
    	if (isset($_REQUEST["HidePrivate"]) && $_REQUEST["HidePrivate"] == 'true') {
            $plQpart = " AND ".$this->settingVars->privateLabelFilterField." = 0 ";
    	}                
                
		$query 	= "SELECT PIN AS ID".
					//",MAX(PNAME) AS ACCOUNT".
					",MAX(UPC) AS UPC".
					",MAX(NG) AS NG ".
					"FROM ".$this->settingVars->skutable.",".$this->settingVars->productplanotable." ".
					"WHERE ".$this->settingVars->skutable.".hide<>1 AND ".$this->settingVars->skutable.".GID IN (".$this->settingVars->GID.") ".
					"AND ".$this->settingVars->skutable.".clientID='".$this->settingVars->clientID."' ".
                    "AND ".$this->settingVars->productplanotable.".GID = ".$this->settingVars->skutable.".GID ".
                    "AND ".$this->settingVars->productplanotable.".article = ".$this->settingVars->skutable.".PIN $plQpart ".
					"GROUP BY ID ";
		//echo $query;exit;
		
        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result	= $this->queryVars->queryHandler->runQuery($query,$this->queryVars->linkid,db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }
        
		if(is_array($result) && !empty($result))
		{
			foreach($result as $key=>$data)
			{
				$PIN 		= $data['ID'];
				//$pinArr[$PIN]	= $data['ACCOUNT'];
				$upcArr[$PIN]	= $data['UPC'];
				$aggArr[$PIN]	= $data['NG'];
			}
		}
		
		$query 	= "SELECT ".$this->settingVars->storetable.".SNO AS ID".
					",max(SNAME) AS ACCOUNT".
					",MAX(banner_alt) AS BANNER".
					",MAX(STATE) AS STATE ".
					"FROM ".$this->settingVars->storetable.",".$this->settingVars->storeplanotable." ".
					"WHERE ".$this->settingVars->storetable.".SNO<>0 AND ".$this->settingVars->storetable.".GID IN (".$this->settingVars->GID.") ".
                    "AND ".$this->settingVars->storeplanotable.".SNO = ".$this->settingVars->storetable.".SNO ".
					"GROUP BY ID";
		
        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result	= $this->queryVars->queryHandler->runQuery($query,$this->queryVars->linkid,db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }
        
		foreach($result as $key=>$data)
		{
			$sno 				= $data['ID'];
			$snoArr[$sno]		= $data['ACCOUNT'];
			$bannerArr[$sno]	= $data['BANNER'];
			$proArr[$sno]		= $data['STATE'];
		}
		
		$tempPlano['All'] = array("data"=>"All","label"=>"All");
		$this->jsonOutput["PlanoList"] = $tempPlano;
		
		$qpart = "";
		if ($planoVal!="" && $planoVal!="All")		
			$qpart=" AND planogram='$planoVal'";
		
		$qPart2 = ($flag == true) ? " " : $this->queryCustomPart;

        $pPlanoTable    = $this->settingVars->productplanotable;
        $sPlanoTable    = $this->settingVars->storeplanotable;
        $maintable       = $this->settingVars->maintable;
        $timetable       = $this->settingVars->timetable;
        $skutable       = $this->settingVars->skutable;
        $storetable       = $this->settingVars->storetable;
        
        $query	= "SELECT  ".$sPlanoTable.".SNO AS SNO".
					",".$pPlanoTable.".article AS PIN".
					",".$pPlanoTable.".article_name AS PNAME".
					",MAX(".$pPlanoTable.".planogram) AS PLANO ".
                    ",MAX(UPC) AS UPC".
					",MAX(NG) AS NG ".                    
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
					$qPart2." ".$plQpart." ".$qpart.
					" GROUP BY SNO,PIN,PNAME ORDER BY PLANO ASC";
		//echo $query; exit;
		
        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result	= $this->queryVars->queryHandler->runQuery($query,$this->queryVars->linkid,db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }
        
        $storeList = array();
        
        $gridDataArray = $pinSno =array();
		if(is_array($result) && !empty($result))
		{
			foreach($result as $key=>$data)
			{
				$PIN 	= $data['PIN'];
				$storeList[] = $sno 	= $data['SNO'];
			
                $pinSno[] = $PIN.$sno;
            
				if($this->qtyArray[$PIN][$sno] <= 0) 
                    $gridTag = "notselling";
				else 
                    $gridTag = "selling";
					
				$tempArr = array();
				$tempArr["plano"] = htmlspecialchars_decode(trim($data['PLANO']));
				$tempArr["PIN"] = $PIN;
                $tempArr["PNAME"] = htmlspecialchars_decode($data['PNAME']);
				$tempArr["SNO"] = $sno;
				$tempArr["SNAME"] = htmlspecialchars_decode($snoArr[$sno]);
                $tempArr["upc"] = $data['UPC'];
				$tempArr["agg_int"] = $data['NG'];
				$tempArr["banner"] = htmlspecialchars_decode($bannerArr[$sno]);
				$tempArr["province"] = htmlspecialchars_decode($proArr[$sno]);
			   				
				if($gridTag=="notselling")
				{
					if(in_array($sno,$snoList)) {$active='Y';}
					else {$active='N';}
						$tempArr["active"] = $active;

					$tempArr["salesL5W"] = $storeSales[$sno]==NULL?0:$storeSales[$sno];
					$tempArr["productSales"] = $productSales[$PIN]==NULL?0:$productSales[$PIN];
				}
				else
					$tempArr["salesL5W"] = $this->valArray[$PIN][$sno]==NULL?0:$this->valArray[$PIN][$sno];
					
				$gridDataArray[$gridTag][] = json_encode($tempArr);

				$tempPlano[$data['PLANO']] = array(
	                "data"=>htmlspecialchars_decode($data['PLANO']),
	                "label"=>htmlspecialchars_decode($data['PLANO'])
	            );
			}
		}
        
        $sellingOffPlano = array();
        if(is_array($storeList) && !empty($storeList))
        {
            $this->settingVars->tableUsedForQuery = $this->measureFields = array();
            $this->measureFields[] = $this->skuID;
            $this->measureFields[] = $this->storeID;
            $this->settingVars->useRequiredTablesOnly = true;
            if (is_array($this->measureFields) && !empty($this->measureFields)) {
                $this->prepareTablesUsedForQuery($this->measureFields);
            }

            $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll
            
            $queryp = " AND CONCAT(".$this->settingVars->yearperiod.",".$this->settingVars->weekperiod.") IN (".implode("," , filters\timeFilter::getYearWeekWithinRange(0, $_REQUEST['timeFrame'], $this->settingVars)).") ";
            
            $query 	= "SELECT ".$this->settingVars->skutable.".PIN AS PIN".
                    ",".$this->settingVars->storetable.".SNO AS SNO".
                    ",".$this->settingVars->skutable.".PNAME as PNAME".
                    ",MAX(".$this->settingVars->storetable.".SNAME) as SNAME".
                    ",MAX(UPC) AS UPC".
                    ",MAX(NG) AS NG ".
                    ",MAX(banner_alt) AS BANNER".
                    ",MAX(STATE) AS STATE ".
                    "FROM ".$this->settingVars->tablename.$this->queryPart.$queryp.
                    " AND ".$this->settingVars->ProjectVolume." > 0 AND ".$this->settingVars->ProjectValue." > 0 AND ".$this->settingVars->storetable.".SNO NOT IN (".implode(",", $storeList).") ".
                    "GROUP BY PIN, SNO, PNAME";
            //echo $query;exit;
            
            $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
            if ($redisOutput === false) {
                $result	= $this->queryVars->queryHandler->runQuery($query,$this->queryVars->linkid,db\ResultTypes::$TYPE_OBJECT);
                $this->redisCache->setDataForHash($result);
            } else {
                $result = $redisOutput;
            }
            
            if(is_array($result) && !empty($result))
            {
                foreach($result as $key => $data)
                {
                    $tempArr = array();
                    $tempArr["plano"] = "";
                    $tempArr["PIN"] = $data['PIN'];
                    $tempArr["PNAME"] = htmlspecialchars_decode($data['PNAME']);
                    $tempArr["SNO"] = $data['SNO'];
                    $tempArr["SNAME"] = htmlspecialchars_decode($data['SNAME']);
                    $tempArr["upc"] = $data['UPC'];
                    $tempArr["agg_int"] = $data['NG'];
                    $tempArr["banner"] = htmlspecialchars_decode($data['BANNER']);
                    $tempArr["province"] = htmlspecialchars_decode($data['STATE']);
                    $tempArr["salesL5W"] = $this->valArray[$data['PIN']][$data['SNO']]==NULL?0:$this->valArray[$data['PIN']][$data['SNO']];
                    $sellingOffPlano[] = json_encode($tempArr);
                    
                }
            }
        }
        
        $this->jsonOutput['selling'] = $gridDataArray['selling'];
        $this->jsonOutput['notselling'] = $gridDataArray['notselling']; 
        $this->jsonOutput['sellingOffPlano'] = $sellingOffPlano; 
		$this->jsonOutput['PlanoList'] = array_values($tempPlano);
        
    }
	
	/*     * ***
     * COLLECTS TABLE JOINING STRING FROM PROJECT SETTINGS
     * ALSO APPLYS FILTER CONDITIONS TO THE STRING
     * AND RETURNS $tablejoins_and_filters
     * *** */

    public function getCustomFilter() {
		
        $tablejoins_and_filters = '';	
		
		if (isset($_REQUEST["FS"]) && $_REQUEST["FS"] != ''){
            $tablejoins_and_filters   .= filters\productAndMarketFilter::include_product_and_market_filters($this->queryVars,$this->settingVars);
    	    foreach($_REQUEST['FS'] as $key=>$data)
    	    {
        		if(!empty($data))
        		{
        		    $filterKey      = !key_exists('ID',$this->settingVars->dataArray[$key]) ? $this->settingVars->dataArray[$key]['NAME'] : $settingVars->dataArray[$key]['ID'];
        		    if($filterKey=="CLUSTER")
        		    {
						$this->settingVars->tablename 	 = $this->settingVars->tablename.",".$this->settingVars->clustertable;
						$tablejoins_and_filters		.= " AND ".$this->settingVars->maintable.".PIN=".$this->settingVars->clustertable.".PIN ";
        		    }
        		}
    	    }
        }
        
        return $tablejoins_and_filters;
    }        
}

?> 