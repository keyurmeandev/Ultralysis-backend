<?php
namespace classes;
use db;
use filters;
use config;

class DistributionGaps extends config\UlConfig{
    
    private $slList;
    private $pageName;


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
        
        		
		$this->queryPart = $this->getAll();
		$this->queryCustomPart = $this->getCustomFilter();

		$action = $_REQUEST["action"];
		switch ($action)
		{
			case "fetchConfig":			
				$this->fetchConfig();		
				break;
			case "reload":			
				$this->Reload();		
				break;
			case "planoload":			
				$this->LoadPlano();	
				break;			
		}
		return  $this->jsonOutput;
    }

    public function fetchConfig(){
    	// GRID COLUMNS CONFIGURATION
        $this->jsonOutput["GRID_COLUMN_CONFIGURATION"] = $this->settingVars->pageArray[$this->pageName]["GRID_COLUMN_CONFIGURATION"];
    }

    private function Reload(){
		$this->getPeriodData();
		$this->gridValue(false);	//ADDING TO OUTPUT	
    }
    
    private function LoadPlano(){		
		$this->getPeriodData();
		$this->gridValue(true);	//ADDING TO OUTPUT
    }
        
    private function getPeriodData(){		
							
		$querypart = $this->queryCustomPart;
		$querypart 	.= " AND CONCAT(".$this->settingVars->yearperiod.",".$this->settingVars->weekperiod.") IN (".implode("," , filters\timeFilter::getYearWeekWithinRange(0 , $_REQUEST['timeFrame'] , $this->settingVars)).") ";
		$query 		= "SELECT plano AS PLANO".
					",".$this->settingVars->masterplanotable.".PIN AS ID".
					",COUNT(DISTINCT((CASE WHEN ".$this->settingVars->ProjectVolume.">0 THEN 1 END)*".$this->settingVars->masterplanotable.".SNO)) AS sellingStores ".
					"FROM ".$this->settingVars->maintable.",".$this->settingVars->timetable.", ".$this->settingVars->masterplanotable." ".
					"WHERE ".$this->settingVars->maintable.".mydate=".$this->settingVars->timetable.".mydate ".
					"AND ".$this->settingVars->maintable.".SNO=".$this->settingVars->masterplanotable.".SNO ".
					"AND ".$this->settingVars->masterplanotable.".accountID=".$this->settingVars->aid." ".
					"AND ".$this->settingVars->masterplanotable.".projectID=".$this->settingVars->projectID." ".$querypart." ".
					"GROUP BY PLANO,ID";
		//echo $query;exit;
		$result		= $this->queryVars->queryHandler->runQuery($query,$this->queryVars->linkid,db\ResultTypes::$TYPE_OBJECT);
		$this->slList 	= array();
		if(is_array($result) && !empty($result))
		{
			foreach($result as $key=>$data)
			{
				$this->slList[$data['PLANO']][$data['ID']]	= $data['sellingStores'];
			}
		}
    }
	    
    private function gridValue($flag){

    	$this->settingVars->tableUsedForQuery = $this->measureFields = array();
		$this->measureFields[] = $this->settingVars->skutable.".PIN";
			$this->settingVars->useRequiredTablesOnly = true;
			if (is_array($this->measureFields) && !empty($this->measureFields)) {
				$this->prepareTablesUsedForQuery($this->measureFields);
			}

		$this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll	
		
		$productInfo 	= array();
		$query 	= "SELECT ".$this->settingVars->skutable.".PIN AS ID ".
				",MAX(PNAME)AS NAME".
				",MAX(UPC) AS UPC".
				",MAX(agg3) AS FORMAT".
				",MAX(agg4) AS CATEGORY ".
				"FROM ".$this->settingVars->tablename.$this->queryPart." ".
				"GROUP BY ID";
		//echo $query;exit;
		$result	= $this->queryVars->queryHandler->runQuery($query,$this->queryVars->linkid,db\ResultTypes::$TYPE_OBJECT);
		if(is_array($result) && !empty($result))
		{
			foreach($result as $key=>$data)
			{
				$PIN 			     = $data['ID'];
				$productInfo[$PIN]['NAME'] 	     = $data['NAME'];
				$productInfo[$PIN]['UPC'] 	     = $data['UPC'];
				$productInfo[$PIN]['FORMAT']     = $data['FORMAT'];
				$productInfo[$PIN]['CATEGORY']   = $data['CATEGORY'];
			}
		}
		
		$qPart = ($flag == true) ? " " : $this->queryCustomPart;
   
		$query	="SELECT  ".$this->settingVars->masterplanotable.".PIN AS ID".
					",".$this->settingVars->masterplanotable.".plano AS PLANO ".
					",COUNT(DISTINCT ".$this->settingVars->masterplanotable.".SNO) AS DIST ".
					"FROM ".$this->settingVars->masterplanotable." ".
					"WHERE 1=1 ".$qPart." ".
					"AND ".$this->settingVars->masterplanotable.".accountID=".$this->settingVars->aid." ".
					"AND ".$this->settingVars->masterplanotable.".projectID=".$this->settingVars->projectID." ".
					"AND ".$this->settingVars->masterplanotable.".PIN IN ".
					" ( SELECT ".$this->settingVars->skutable.".PIN AS ID2 "."
						FROM ".$this->settingVars->tablename.$this->queryPart.
					" GROUP BY ID2) ".
					"GROUP BY ID,PLANO ".
					"ORDER BY PLANO";
		//echo $query;exit;
		$result	= $this->queryVars->queryHandler->runQuery($query,$this->queryVars->linkid,db\ResultTypes::$TYPE_OBJECT);
		
		$tempPlano['All'] = array("data"=>"All","label"=>"All");
		$this->jsonOutput["PlanoList"] = $tempPlano;
		
		if(is_array($result) && !empty($result))
		{
			for($i=0;$i<count($result);$i++)
			{
				$data 	= $result[$i];
				$ID 	= $data['PLANO']; 
				$PIN 	= $data['ID'];			
				$pctSelling =  number_format(($this->slList[$ID][$PIN]/$data['DIST'])*100,1,".",",");      
					
					if($pctSelling>=100){ $gridTag 	= "noissues";}
					else {$gridTag 			= "issues";}
				
				$temp 		= array();
				$temp["plano"]=htmlspecialchars_decode(trim($data['PLANO']));
				$temp["pin"]=$PIN;
				$temp["pname"]=$productInfo[$PIN]['NAME'];
				$temp["format"]=$productInfo[$PIN]['FORMAT'];
				$temp["category"]=$productInfo[$PIN]['CATEGORY'];
				$temp["upc"]=$productInfo[$PIN]['UPC'];
				$temp["maxDist"]=$data['DIST'];
				$temp["sellingStores"]=$this->slList[$ID][$PIN];
				$temp["pctSelling"]=$pctSelling;
				$this->jsonOutput[$gridTag][] = $temp;
				
				$tempPlano[$data['PLANO']] = array("data"=>htmlspecialchars_decode($data['PLANO']),"label"=>htmlspecialchars_decode($data['PLANO']));			
			}
		}
		$this->jsonOutput["PlanoList"] = array_values($tempPlano);		
    }
	
	/*     * ***
     * COLLECTS TABLE JOINING STRING FROM PROJECT SETTINGS
     * ALSO APPLYS FILTER CONDITIONS TO THE STRING
     * AND RETURNS $tablejoins_and_filters
     * *** */

    public function getCustomFilter() {
		
        $tablejoins_and_filters = '';	
		
		if (isset($_REQUEST["FS"]) && ($_REQUEST["FS[F2]"] != '' || $_REQUEST["FS[F13]"] != '')){
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
					
        if(trim($_REQUEST['PLANOID'])!="" && trim($_REQUEST['PLANOID'])!="All")
        {
            $tablejoins_and_filters.= " AND plano = '".trim($_REQUEST['PLANOID'])."'" ;
        }

        return $tablejoins_and_filters;
    }
}
?>