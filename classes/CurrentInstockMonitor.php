<?php
namespace classes;

use config;
use filters;
use db;

class CurrentInstockMonitor extends config\UlConfig{

    private $skuID,$skuName,$storeID,$storeName;
    private $salesArrayBy_SkuAndStore;

    /*****
    * Default gateway function, should be similar in all DATA CLASS
    * arguments:
    *  $settingVars [project settingsGateway variables]
    * @return $xmlOutput with all data that should be sent to client app
    *****/
    public function go($settingVars){
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES
        
        $this->skuID 	= $this->settingVars->dataArray[$_REQUEST['SKU_ACCOUNT']]['ID'];
        $this->skuName 	= $this->settingVars->dataArray[$_REQUEST['SKU_ACCOUNT']]['NAME'];
    
        $this->storeID	= $this->settingVars->dataArray[$_REQUEST['STORE_ACCOUNT']]['ID'];
        $this->storeName 	= $this->settingVars->dataArray[$_REQUEST['STORE_ACCOUNT']]['NAME'];
        
        $this->onHandField  = isset($_REQUEST['OnHandField'])   ? $_REQUEST['OnHandField']  : "OHQ";
        $this->inTransField = isset($_REQUEST['InTransField'])  ? $_REQUEST['InTransField'] : "Store_trans";
        $this->inDepotField = isset($_REQUEST['InDepotField'])  ? $_REQUEST['InDepotField'] : "Store_Whs";
        $this->inOrderField  = isset($_REQUEST['InOrderField']) ? $_REQUEST['InOrderField'] : "store_order";
            
        $action = $_REQUEST["action"];
        switch ($action)
        {
            case "reload":      
				$this->Reload();     
				break;
            case "gridchange":  
				$this->changeGrid(); 
				break;
        }
		return $this->jsonOutput;
    }

    private function Reload(){    
        $_REQUEST["TSI"]    = 2;
        $_REQUEST["ONHAND"] = 1;
        
        $_GET['ignoreLatest_N_Weeks'] = 0; //old projects ignored this parameter for instock page
        
        filters\timeFilter::getYTD($this->settingVars);
        $this->queryPart      = $this->getAll(); //USES OWN getAll FUNCTION
        
        $this->CountSNO();
        $this->valueLatestPeriod();
        $this->instockGrid(); //adding to output
    }

    private function changeGrid(){        
        filters\timeFilter::collectLatestYearSSCover($this->settingVars);
        
        $this->queryPart  = $this->getAll();
        $this->valueFunc();
        
        $this->myLatestPeriod();
    
        $this->queryPart  = $this->getAll2();
        $this->SkuGrid(); //adding to output
    }

    private function valueLatestPeriod(){
        $this->LOList = array();
        $query      = "SELECT ".$this->skuID." ".
                            "FROM ".$this->settingVars->tablename . $this->queryPart." ".
                            "AND ".$this->settingVars->maintable.".".$this->settingVars->period." IN (" . filters\timeFilter::getPeriodWithinRangeTwin($this->settingVars) . ") ".
                            "AND ".$this->settingVars->maintable.".VSI=1 AND ".$this->settingVars->maintable.".OHQ<1 ".
                            "GROUP BY 1";
        //print $query;exit;
        $result     = $this->queryVars->queryHandler->runQuery($query,$this->queryVars->linkid,db\ResultTypes::$TYPE_ARRAY);
        foreach($result as $key=>$data){
            $this->LOList[$data[0]]   = $data[1];
        }
    }

    private function instockGrid(){
        $qpart .= " AND Order_Book_Flag <> 'N' AND ITEM_STATUS='A' AND ".$this->settingVars->effectiveDate." < ".$this->settingVars->insertDate." AND ".$this->settingVars->openDate." < ".$this->settingVars->insertDate." ";
    
        $query = "SELECT ".$this->skuName.
                        ",".$this->skuID.
                        ",SUM( (CASE WHEN (".$this->settingVars->yearperiod."=" . filters\timeFilter::$ToYear . " AND ".$this->settingVars->weekperiod.">=".filters\timeFilter::$FromWeek." AND ".$this->settingVars->weekperiod."<=52) OR (".$this->settingVars->yearperiod."=" . filters\timeFilter::$ToYear . " AND ".$this->settingVars->weekperiod."<=".filters\timeFilter::$ToWeek." AND ".$this->settingVars->weekperiod.">=1) THEN 1 ELSE 0 END)*".$this->settingVars->ProjectVolume.") AS qtyval".
                        ",COUNT(DISTINCT CASE WHEN ".$this->settingVars->yearperiod."=" . filters\timeFilter::$ToYear . " AND ".$this->settingVars->weekperiod."=".filters\timeFilter::$ToWeek." AND OHQ> 0 AND VSI=1 AND ".$this->settingVars->openDate." < ".$this->settingVars->insertDate." THEN 1 END * ".$this->storeID.") AS SNO2 ".
                        "FROM ".$this->settingVars->tablename.$this->queryPart.$qpart." ".
                        "GROUP BY 1,2";
        //print $query;exit;
        $result = $this->queryVars->queryHandler->runQuery($query,$this->queryVars->linkid,db\ResultTypes::$TYPE_ARRAY);
        foreach($result as $key=>$data)
        {
			$tmpArray = array();
            $sin    = $data[1];
            $trait  = $this->snoArr[$sin];
            $val    = $trait > 0 ? (($data[3] / $trait) * 100) : 0;
            $val    = number_format($val , 1 , '.' , '');
    
            if ($_REQUEST["instockType"] == 1 && $trait > 0){
                if ($val < 98.5)
                {
					$tmpArray['SKU'] =  htmlspecialchars($data[0]);
					$tmpArray['PRIMENO'] =  $data[1];
					$tmpArray['TYQTY'] =  $data[2];
					$tmpArray['TRAITED'] =  $trait;
					$tmpArray['INSTOCK'] =  $data[3];
					$tmpArray['INSTOCKP'] =  $val;
					$tmpArray['LOSTTW'] =  $this->LOList[$sin];
					
                    /* $value = $this->xmlOutput->addChild("instockGrid");
                        $value->addChild("SKU",         htmlspecialchars($data[0]));
                        $value->addChild("PRIMENO",     $data[1]);
                        $value->addChild("TYQTY",       $data[2]);
                        $value->addChild("TRAITED",     $trait);
                        $value->addChild("INSTOCK",     $data[3]);
                        $value->addChild("INSTOCKP",    $val);
                        $value->addChild("LOSTTW",      $this->LOList[$sin]); */
                }
            }elseif ($_REQUEST["instockType"] == 2 && $trait > 0){
                if ($val > 98.5 && $val < 100)
                {
					$tmpArray['SKU'] =  htmlspecialchars($data[0]);
					$tmpArray['PRIMENO'] =  $data[1];
					$tmpArray['TYQTY'] =  $data[2];
					$tmpArray['TRAITED'] =  $trait;
					$tmpArray['INSTOCK'] =  $data[3];
					$tmpArray['INSTOCKP'] =  $val;
					$tmpArray['LOSTTW'] =  $this->LOList[$sin];
                    /*$value = $this->xmlOutput->addChild("instockGrid");
                        $value->addChild("SKU",         htmlspecialchars($data[0]));
                        $value->addChild("PRIMENO",     $data[1]);
                        $value->addChild("TYQTY",       $data[2]);
                        $value->addChild("TRAITED",     $trait);
                        $value->addChild("INSTOCK",     $data[3]);
                        $value->addChild("INSTOCKP",    $val);
                        $value->addChild("LOSTTW",      $this->LOList[$sin]);*/
                }
            }elseif ($_REQUEST["instockType"] == 3 && $trait > 0){
                if ($val == 100)
                {
					$tmpArray['SKU'] =  htmlspecialchars($data[0]);
					$tmpArray['PRIMENO'] =  $data[1];
					$tmpArray['TYQTY'] =  $data[2];
					$tmpArray['TRAITED'] =  $trait;
					$tmpArray['INSTOCK'] =  $data[3];
					$tmpArray['INSTOCKP'] =  $val;
					$tmpArray['LOSTTW'] =  $this->LOList[$sin];				
                    /*$value = $this->xmlOutput->addChild("instockGrid");
                        $value->addChild("SKU",         htmlspecialchars($data[0]));
                        $value->addChild("PRIMENO",     $data[1]);
                        $value->addChild("TYQTY",       $data[2]);
                        $value->addChild("TRAITED",     $trait);
                        $value->addChild("INSTOCK",     $data[3]);
                        $value->addChild("INSTOCKP",    $val);
                        $value->addChild("LOSTTW",      $this->LOList[$sin]);*/
                }
            }
			if(!empty($tmpArray))
				$skuGrid[] = $tmpArray;
        }
		$this->jsonOutput['skuGrid'] = $skuGrid;
    }

    //CALCULATE EACH PRODUCT'S COUNT OF STORES
    private function CountSNO(){    
        $this->snoArr = array();
        $query = "SELECT ".$this->skuID." AS SKUID".
                        ",COUNT(DISTINCT ".$this->storeID.") ".
                        "FROM ".$this->settingVars->tablename . $this->queryPart." ".
                        "AND ".$this->settingVars->yearperiod."=".filters\timeFilter::$ToYear." ".
                        "AND ".$this->settingVars->weekperiod."=".filters\timeFilter::$ToWeek." AND VSI=1 ".
                        "AND ".$this->settingVars->openDate."<".$this->settingVars->insertDate." ".
                        "GROUP BY SKUID";
        //print $query;exit;
        $result = $this->queryVars->queryHandler->runQuery($query,$this->queryVars->linkid,db\ResultTypes::$TYPE_ARRAY);
        foreach($result as $key=>$data)
        {
            $this->snoArr[$data[0]] = $data[1];
        }
    }

    private function valueFunc(){		
        $this->salesArrayBy_SkuAndStore = array();    
        $qpart = " AND ".$this->settingVars->maintable.".period IN (" . filters\timeFilter::getPeriodWithinRangeTwin($this->settingVars , 0 , 1) . ") ";
        $query = "SELECT ".$this->skuID." AS TPNB ".
                        ",".$this->storeID." AS SNO ".
                        ",".$this->settingVars->ProjectVolume." AS UNITS ".
                        ",".$this->settingVars->ProjectValue." AS VALUE ".
                        ",SUM(dd_cases* WHPK_Qty) AS DD ".
                        "FROM ".$this->settingVars->tablename . $this->queryPart . $qpart." ".
                        "GROUP BY 1,2,3,4";
        $result = $this->queryVars->queryHandler->runQuery($query,$this->queryVars->linkid,db\ResultTypes::$TYPE_OBJECT);
        foreach($result as $key=>$data)
        {
                $sin 	= $data['TPNB'];
                $sno 	= $data['SNO'];
                $this->salesArrayBy_SkuAndStore[$sin][$sno]['UNITS'] 	= $data['UNITS'];
                $this->salesArrayBy_SkuAndStore[$sin][$sno]['VALUE'] 	= $data['VALUE'];
                $this->salesArrayBy_SkuAndStore[$sin][$sno]['DD'] 		= $data['DD'];
        }
    }

    private function SkuGrid(){
        // FOR NOT INSTOCK 
        $query = "SELECT ".$this->skuID.
                        ",".$this->skuName.
                        ",".$this->storeID.
                        ",".$this->storeName.
                        ",SUM((CASE WHEN ".$this->settingVars->maintable.".".$this->settingVars->period."='".$this->latestPeriod."' THEN 1 END)*".$this->onHandField."  ) AS ohq".
                        ",SUM(".$this->inTransField.")".
                        ",SUM(".$this->inDepotField.")".
                        ",SUM(".$this->inOrderField.")".
                        ",SUM(".$this->settingVars->ProjectVolume.")".
                        ",VSI".
                        ",SUM(MSQ) ".
                        "FROM ".$this->settingVars->tablename . $this->queryPart." AND VSI =1 AND ".$this->settingVars->openDate."<curdate() ".
                        "GROUP BY 1,2,3,4,VSI ".
                        "HAVING ohq<1";
        $result = $this->queryVars->queryHandler->runQuery($query,$this->queryVars->linkid,db\ResultTypes::$TYPE_ARRAY);
		$instockgrid = array();
        foreach($result as $key=>$data)
        {
			$instockgridTmpArr = array();
            $sin                = $data[0];
            $sno                = $data[2];
    
            $lwUnits            = $this->salesArrayBy_SkuAndStore[$sin][$sno]['UNITS'];
            $sales              = $this->salesArrayBy_SkuAndStore[$sin][$sno]['VALUE'];
            $dd                 = $this->salesArrayBy_SkuAndStore[$sin][$sno]['DD'];
            $dd                 = number_format($dd, 1, '.', '');
            $pipeline           = $data[5] + $data[6] + $data[7];
			
			$instockgridTmpArr['SNO'] = $data[2];
			$instockgridTmpArr['SNAME'] = htmlspecialchars($data[3]);
			$instockgridTmpArr['PIN'] = $data[0];
			$instockgridTmpArr['PNAME'] = htmlspecialchars($data[1]);
			$instockgridTmpArr['Hand'] = $data[4];
			$instockgridTmpArr['Trans'] = $data[5];
			$instockgridTmpArr['Whs'] = $data[6];
			$instockgridTmpArr['Order'] = $data[7];
			$instockgridTmpArr['TSI'] = $data[8];
			$instockgridTmpArr['ONHAND'] = $data[9];
			$instockgridTmpArr['FCAST'] = $WLList[$sin][$sno];
			$instockgridTmpArr['LOSTTW'] = $NIList[$sin][$sno];
			$instockgridTmpArr['depot'] = $data[11];
			$instockgridTmpArr['LWUNITS'] = $lwUnits;
			$instockgridTmpArr['LWVAL'] = $sales;
			$instockgridTmpArr['DD'] = $dd;
			$instockgridTmpArr['MSQ'] = $data[10];
			$instockgridTmpArr['PLINE'] = $pipeline;
			
			$instockgrid[] = $instockgridTmpArr;
			
            /*$value = $this->xmlOutput->addChild("instockgrid");
                        $value->addChild("SNO",         $data[2]);
                        $value->addChild("SNAME",       htmlspecialchars($data[3]));
                        $value->addChild("PIN",         $data[0]);
                        $value->addChild("PNAME",       htmlspecialchars($data[1]));
                        $value->addChild("Hand",        $data[4]);
                        $value->addChild("Trans",       $data[5]);
                        $value->addChild("Whs",         $data[6]);
                        $value->addChild("Order",       $data[7]);
                        $value->addChild("TSI",         $data[8]);
                        $value->addChild("ONHAND",      $data[9]);
                        $value->addChild("FCAST",       $WLList[$sin][$sno]);
                        $value->addChild("LOSTTW",      $NIList[$sin][$sno]);
                        $value->addChild("depot",       $data[11]);
                        $value->addChild("LWUNITS",     $lwUnits);
                        $value->addChild("LWVAL",       $sales);
                        $value->addChild("DD",          $dd);
                        $value->addChild("MSQ",         $data[10]);
                        $value->addChild("PLINE",       $pipeline);*/
        }
		//print_r($instockGrid); exit;
		$this->jsonOutput['instockGrid'] = $instockgrid;
    }

 
    public function getAll(){    
        $tablejoins_and_filters       = $this->settingVars->link;    
    
        if (isset($_REQUEST["FS"]) && $_REQUEST["FS"] != ''){
           $tablejoins_and_filters   .= filters\productAndMarketFilter::include_product_and_market_filters($this->queryVars,$this->settingVars);
        }
        
        if ($_REQUEST["PIN"] != "")
            $tablejoins_and_filters.=" AND ".$this->skuID."=".$_REQUEST['PIN'];
    
        if ($_REQUEST["date"] != "")
            $tablejoins_and_filters.=" AND ".$this->maintable.".".$this->settingVars->period."=".$_REQUEST['date'];
    
        if ($_REQUEST["SNO"] != "")
            $tablejoins_and_filters.=" AND ".$this->storeID."=".$_REQUEST['SNO'];
        
        return $tablejoins_and_filters;
    }

    private function getAll2(){
        $tablejoins_and_filters       = $this->settingVars->link. " AND ".$this->settingVars->openDate."<insertdate ";
    
        if (isset($_REQUEST["FS"]) && $_REQUEST["FS"] != ''){
           $tablejoins_and_filters   .= filters\productAndMarketFilter::include_product_and_market_filters($this->queryVars,$this->settingVars);
        }

        if ($_REQUEST["PIN"] != "")
            $tablejoins_and_filters.=" AND ".$this->skuID."=$_REQUEST[PIN]";
    
        if ($_REQUEST["SNO"] != "")
            $tablejoins_and_filters.=" AND ".$this->storeID."=$_REQUEST[SNO]";
        
        return $tablejoins_and_filters;
    }


    private function myLatestPeriod(){
        if (!empty(filters\timeFilter::$ToYear))
            $query = "SELECT ".$this->settingVars->maintable.".".$this->settingVars->period." ".
                        "FROM ".$this->settingVars->timeHelperTables . $this->settingVars->timeHelperLink." ".
                        "AND ".$this->settingVars->yearperiod."=".filters\timeFilter::$ToYear." ".
                        "GROUP BY 1 ".
                        "ORDER BY 1 DESC ".
                        "LIMIT 0,1";
        else
            $query="SELECT MAX(".$this->settingVars->period.") FROM ".$this->settingVars->maintable;
            
        $result = $this->queryVars->queryHandler->runQuery($query,$this->queryVars->linkid,db\ResultTypes::$TYPE_ARRAY);
        $data   = $result[0];
        $this->latestPeriod = $data[0];
    }
}
?>