<?php
namespace classes;

use projectsettings;
use SimpleXMLElement;
use datahelper;
use filters;
use db;
use config;

class SkuStoreDistribution extends config\UlConfig{    
    private $skuID,$skuName,$storeField,$areaField;
    
    /*****
    * Default gateway function, should be similar in all DATA CLASS
    * arguments:
    *  $settingVars [project settingsGateway variables]
    * @return $xmlOutput with all data that should be sent to client app
    *****/
    public function go($settingVars){
	$this->initiate($settingVars); //INITIATE COMMON VARIABLES
        
        $this->skuID                    = $this->settingVars->dataArray[$_REQUEST['PRODUCT_FIELD']]['ID'];
        $this->skuName                  = $this->settingVars->dataArray[$_REQUEST['PRODUCT_FIELD']]['NAME'];
        $this->storeField               = $this->settingVars->dataArray[$_REQUEST['STORE_FIELD']]['NAME'];
        $this->areaField                = $this->settingVars->dataArray[$_REQUEST['AREA_FIELD']]['NAME'];

	$this->queryPart    	        = $this->getAll();
    
	$action = $_REQUEST["action"];
	switch($action)
	{
	    case "reload":		    return $this->reload();	    break;
	    case "getSellingNotSelling":    return $this->changeGrid();	    break;    
	}
	
    }

    private function reload(){
        $this->gridValue();
        return $this->xmlOutput;
    }


    private function changeGrid(){
        $this->getSellingStores();
        $this->getNotSellingStores();
        
        return $this->xmlOutput;
    }

    
    private function gridValue(){
        //COLLECT TOTAL BRANCH
        $query              = "SELECT ". $this->areaField ." AS REGION".
                                ",COUNT(DISTINCT(". $this->storeField .")) AS TOTAL_BRANCH ".
                                "FROM ". $this->settingVars->tablename . $this->queryPart ." ".
                                "GROUP BY REGION";
        $result             = $this->queryVars->queryHandler->runQuery($query,$this->queryVars->linkid,db\ResultTypes::$TYPE_OBJECT);		      
        $totalStoresArray   = array();
        foreach($result as $key=>$data)
        {
            $totalStoresArray[$data['REGION']] = $data['TOTAL_BRANCH'];    
        }
        
        
        //COLLECT SELLING BRANCH FOR ROW SKUs
        $query              = "SELECT ". $this->skuID ." AS SKUID".
                                ",". $this->areaField ." AS REGION".
                                ",COUNT(DISTINCT(CASE WHEN ". $this->ValueVolume .">0 THEN ". $this->storeField ." END)) AS BRANCH ".
                                "FROM ". $this->settingVars->tablename . $this->queryPart." ".
                                "GROUP BY SKUID,REGION";              
        $result             = $this->queryVars->queryHandler->runQuery($query,$this->queryVars->linkid,db\ResultTypes::$TYPE_OBJECT);		   
        $sellingStoresArray = array();
        foreach($result as $key=>$data)
        {
            $index                      = $data['SKUID'].$data['REGION'];
            $sellingStoresArray[$index] = $data['BRANCH'];
        }
        
        
        
        $query          = "SELECT ". $this->skuID ." AS SKUID".
                                ",MAX(". $this->skuName .") AS SKU".
                                ",SUM(". $this->ValueVolume .") AS SALES".
                                ",". $this->areaField ." AS REGION ".
                                "FROM ".$this->settingVars->tablename . $this->queryPart." ".
                                "GROUP BY SKUID, REGION ".
                                "ORDER BY SALES DESC ";           
        $result         = $this->queryVars->queryHandler->runQuery($query,$this->queryVars->linkid,db\ResultTypes::$TYPE_OBJECT);	
        foreach($result as $key=>$data)
        {
            $index          = $data['SKUID'].$data['REGION'];
            $totalStores    = $totalStoresArray[$data['REGION']];
            $sellingStores  = $sellingStoresArray[$index];
            $distPct        = $sellingStores>0?($sellingStores/$totalStores)*100:0;
            
            $value          = $this->xmlOutput->addChild('gridData');
                                    $value->addChild('SKUID',           $data['SKUID']);
                                    $value->addChild('SKU',             htmlspecialchars_decode($data['SKU']));
                                    $value->addChild('REGION',          htmlspecialchars_decode($data['REGION']));
                                    $value->addChild('SALES',           $data['SALES']);
                                    $value->addChild('TTL_BRANCH',      $totalStores);
                                    $value->addChild('SLNG_BRANCH',     $sellingStores);
                                    $value->addChild('DIST_PCT',        number_format($distPct,1,'.',''));                       
        } 
    }



    private function getSellingStores(){
        //PROVIDING GLOBALS
        global $sellingStores;
        
        $localQueryPart = $this->queryPart;
        
        /*************** APPLY SKU AND BANER FILTER ********************/
        if ($_REQUEST["SKU"] != "")
        $localQueryPart     .= " AND ". $this->skuID ." = '".$_REQUEST["SKU"]."' ";
        
        if ($_REQUEST["REGION"] != "")
        $localQueryPart     .= " AND ". $this->areaField ." = '".$_REQUEST["REGION"]."' ";    
        /****************************************************************/
    
        $sellingStores = array();
    
        $query = "SELECT ". $this->storeField." AS BRANCH".
                    ",SUM(". $this->ValueVolume .") AS SALES ".
                    "FROM ". $this->settingVars->tablename . $localQueryPart ." AND ". $this->ValueVolume .">0 ".
                    "GROUP BY BRANCH ".
                    "ORDER BY SALES DESC";
        $result = $this->queryVars->queryHandler->runQuery($query,$this->queryVars->linkid,db\ResultTypes::$TYPE_OBJECT);	
        foreach($result as $key=>$data)  
        {
            array_push($sellingStores,addslashes($data['BRANCH']));
            $value = $this->xmlOutput->addChild('sellingStores');
                        $value->addChild('BRANCH',  htmlspecialchars_decode($data['BRANCH']));
                        $value->addChild('SALES',   $data['SALES']);
        }  
    }

    private function getNotSellingStores(){
        global $sellingStores; //INITIATED ND POPULATED  @getSellingStores
         
        
        $localQueryPart = $this->queryPart;
        
        /*************** APPLY SKU AND BANER FILTER ********************/
        if ($_REQUEST["REGION"] != "")
        $localQueryPart  .= " AND ". $this->areaField ." = '".$_REQUEST["REGION"]."' "; 
        /****************************************************************/
        
        $query =  "SELECT ". $this->storeField ." AS BRANCH".
                    ",SUM( (CASE WHEN ".$this->ValueVolume."<=0  THEN 1 ELSE 0 END) * ". $this->ValueVolume ." ) AS SALES ".   //in ferrero booker insight3 it should be sales with less equal to zero 
                    "FROM ". $this->settingVars->tablename . $localQueryPart." AND ". $this->storeField ." NOT IN ('".implode("','",$sellingStores)."') ".
                    "GROUP BY BRANCH ".
                    "ORDER BY SALES DESC ";
        $result = $this->queryVars->queryHandler->runQuery($query,$this->queryVars->linkid,db\ResultTypes::$TYPE_OBJECT);	
        foreach($result as $key=>$data)   
        {
            $value = $this->xmlOutput->addChild('notSellingStores');
                            $value->addChild('BRANCH',  htmlspecialchars_decode($data['BRANCH']));
                            $value->addChild('SALES',   $data['SALES']);
        } 
    }



    /*****
    * COLLECTS TABLE JOINING STRING FROM PROJECT SETTINGS
    * ALSO APPLYS FILTER CONDITIONS TO THE STRING
    * AND RETURNS $tablejoins_and_filters
    *****/
    public function getAll(){    
        $tablejoins_and_filters       = $this->settingVars->link;    
    
	if (isset($_REQUEST["FS"]) && $_REQUEST["FS"] != ''){
           $tablejoins_and_filters   .= filters\productAndMarketFilter::include_product_and_market_filters($this->queryVars,$this->settingVars);
        }

        $weekArray = filters\timeFilter::getYearWeekWithinRange(0,$_REQUEST["pageID"],$this->settingVars);
        $tablejoins_and_filters .= " AND CONCAT(". $this->settingVars->yearperiod.",".$this->settingVars->weekperiod.") IN (".implode(",",$weekArray).") ";

        return $tablejoins_and_filters;
    }
}

?> 