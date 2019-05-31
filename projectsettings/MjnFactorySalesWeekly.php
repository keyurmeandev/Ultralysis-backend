<?php
namespace projectsettings;

use projectsettings;
use db;
use utils;

class MjnFactorySalesWeekly extends BaseLcl {

    public function __construct($accountID,$projectID,$gids) {
	
        $this->aid                  = $accountID;
        $this->projectID            = $projectID;        
        $this->clientID             = "MJN";
    
        $this->maintable            = "sales";
        $this->accounttable         = "shipments_ship_store";
        $this->groupType            = "SHIPMENTS";
        
        $this->includeDateInTimeFilter  = false;
        
        parent::__construct($gids);

        $this->dateperiod           = "mydate";
        $this->yearField            = "year";
        $this->weekField            = "week";
        
        $this->storetable           = "shipments_sold_store";
        $this->timetable            = "period_list";
        $this->weekperiod           = "$this->maintable.week";
        $this->yearperiod           = "$this->maintable.year";
        $this->ProjectVolume        = "volume";
        
        $this->productHelperLink = " WHERE GID IN (".$this->GID.") AND hide=0 ". $this->extraProductHelperQueryPart;
        
        $this->tableArray['product']['link'] 	= " WHERE GID IN (".$this->GID.") AND hide=0 ". $this->extraProductHelperQueryPart . $commonFilterQueryPart;
        
		$this->configureClassVars();
        
        $this->dateField = "";
        
        if(!$this->hasMeasureFilter){
            
            $this->pageArray["MEASURE_SELECTION_LIST"][] = array('measureID' => 3, 'jsonKey'=>'EQUIV_OZ', 'measureName' => 'Equiv 8oz');
            
            $this->measureArray['M3']['VAL'] 	= "volume*equiv_8";
            $this->measureArray['M3']['ALIASE'] = "EQUIV_OZ";
            $this->measureArray['M3']['attr'] 	= "SUM";
            unset($this->measureArray['M3']['dataDecimalPlaces']);

            $this->measureArray['M4']['VAL'] = "(SUM(IFNULL($this->ProjectValue,0))/SUM(IFNULL($this->ProjectVolume,1)))";
            $this->measureArray['M4']['ALIASE'] = "PRICE";      
            $this->measureArray['M4']['attr']   = "";
            $this->measureArray['M4']['dataDecimalPlaces'] = 2;
            
            $this->measureArray['M5']['VAL'] = "DISTINCT((CASE WHEN ".$this->ProjectValue." > 0 THEN ".$this->maintable.".SNO END))";
            $this->measureArray['M5']['ALIASE'] = "DISTRIBUTION";
            $this->measureArray['M5']['attr'] = "COUNT";
            $this->measureArray['M5']['dataDecimalPlaces'] = 0;

            $this->performanceTabMappings["priceOverTime"] = 'M4';
            $this->performanceTabMappings["distributionOverTime"] = 'M5';
        }    
        
        $commontables   = $this->maintable . ", " . " ".$this->grouptable;
        $commonlink     = " WHERE $this->maintable.accountID=$this->aid " .
                        "AND $this->maintable.".$this->yearField."=".$this->yearperiod ." ".
                        "AND $this->maintable.".$this->weekField."=".$this->weekperiod ." ".
                        // "AND $this->maintable.GID=$this->timetable.gid " .
                        "AND $this->maintable.GID=$this->grouptable.gid " .
                        "AND $this->grouptable.groupType = '$this->groupType' " .
                        // "AND $this->timetable.gid IN (".$this->GID.") AND ".
                        "AND $this->maintable.project_type = 'sales_ship_weekly' ". 
                        "AND $this->maintable.GID IN (".$this->GID.") ";

        $storelink      = " AND $this->maintable.SNO=$this->storetable.SNO " .
                        "AND $this->maintable.GID=$this->storetable.GID " .
                        "AND $this->storetable.gid IN (".$this->GID.") ";
                        
        $territorylink  = ((!empty($this->territorytable)) ? " AND $this->storetable.SNO=$this->territorytable.SNO 
                        AND $this->storetable.GID=$this->territorytable.GID
                        AND $this->territorytable.GID IN ($this->GID) AND $this->territorytable.accountID = $this->aid " : "");
                        
        $accountlink  = " AND $this->maintable.SNO_SHIP=$this->accounttable.SNO " .
                        "AND $this->maintable.GID=$this->accounttable.GID " .
                        "AND $this->accounttable.gid IN (".$this->GID.") ";                        
                        
        $skulink        = "AND $this->maintable.skuID=$this->skutable.skuID " .
                        "AND $this->maintable.GID=$this->skutable.GID " .
                        "AND $this->skutable.hide=0 " .
                        "AND $this->skutable.gid IN (".$this->GID.") ";

        $this->copy_link     = $this->link     = $commonlink.$storelink.$territorylink.$skulink.$accountlink;

        $this->timeHelperTables = " " . $this->maintable . " ";
        $this->timeHelperLink = // " WHERE " . $this->maintable . "." . $this->weekField . "=" . $this->weekperiod .
                                // " AND " . $this->maintable . "." . $this->yearField . "=" . $this->yearperiod . 
                                //" AND ".$this->maintable.".GID=".$this->timetable.".gid " .
                                //" AND ".$this->timetable.".GID IN (".$this->GID.") ".
                                " WHERE ".$this->maintable.".GID IN (".$this->GID.") AND ".
                                " ".$this->maintable.".project_type = 'sales_ship_weekly'";

        if($this->hasHiddenSku()) {
            $commonlink   .= " AND ".$this->maintable.".skuID NOT IN ( ".$this->hiddenSkusQueryString.") ";
        }
                                
        $this->dataTable['default']['tables']               = $commontables;
        $this->dataTable['default']['link']                 = $commonlink;

        $this->dataTable['product']['tables']               = $this->skutable;
        $this->dataTable['product']['link']                 = $skulink;

        $this->dataTable['shipments_sold_store']['tables']  = $this->storetable;
        $this->dataTable['shipments_sold_store']['link']    = $storelink;

        $this->dataTable['territory']['tables']             = $this->territorytable;
        $this->dataTable['territory']['link']               = $territorylink;   
        
        $this->dataTable['shipments_ship_store']['tables']  = $this->accounttable;
        $this->dataTable['shipments_ship_store']['link']    = $accountlink;
        
 		$this->tableArray['shipments_sold_store']['tables'] 	= $this->storetable.", ".$this->maintable;
		$this->tableArray['shipments_sold_store']['link'] 		= " WHERE ".$this->storetable.".gid IN (".$this->GID.") AND ".$this->maintable.".GID = ".$this->storetable.".GID AND ".$this->maintable.".SNO = ".$this->storetable.".SNO ";
		$this->tableArray['shipments_sold_store']['type'] 		= 'M';        
        
        $this->geoHelperTables = $this->tableArray['shipments_sold_store']['tables'];
        $this->geoHelperLink = $this->tableArray['shipments_sold_store']['link'];
        
        if($this->projectTypeID == 6) // DDB
        {
            $this->weekperiod   = "$this->maintable.week";
            $this->yearperiod   = "$this->maintable.year";
            
            $this->dataArray['WEEK']['NAME'] = $this->weekperiod;
            $this->dataArray['WEEK']['NAME_ALIASE'] = 'WEEK';            
            
            $this->dataArray['YEAR']['NAME'] = $this->yearperiod;
            $this->dataArray['YEAR']['NAME_ALIASE'] = 'YEAR';
        
            $this->accountHelperTables = $this->accounttable.", ".$this->maintable;
            $this->accountHelperLink = " WHERE $this->maintable.SNO_SHIP=$this->accounttable.SNO " .
                        "AND $this->maintable.GID=$this->accounttable.GID " .
                        "AND $this->accounttable.gid IN (".$this->GID.") AND $this->maintable.project_type = 'sales_ship_weekly'";
            
            $this->timeSelectionUnit  	= "week";
            
            $this->filterPages[3]['filterHeader'] = "Sold Store Selection";
            $this->filterPages[3]['config'] = array(
                        "table_name" => $this->storetable, 
                        "helper_table" => $this->geoHelperTables , 
                        "setting_name" => "market_settings", 
                        "helper_link" => $this->geoHelperLink,
                        "type" => "M", 
                        "enable_setting_name" => "has_market_filter"
                    );
            
            $this->filterPages[5]['filterHeader'] = "Shipped Store Selection";
            $this->filterPages[5]['config'] = array(
                        "table_name" => $this->accounttable, 
                        "helper_table" => $this->accountHelperTables, 
                        "setting_name" => "account_settings", 
                        "helper_link" => $this->accountHelperLink,
                        "type" => "A", 
                        "enable_setting_name" => "has_account"
                    );
            
            if(!$this->hasMeasureFilter){
                $this->measureArray       		      	= array();
                
                $this->measureArray['M0']['VAL']        = "value";
                $this->measureArray['M0']['ALIASE']     = "NET_SALES";
                $this->measureArray['M0']['attr']       = "SUM";
                        
                $this->measureArray['M1']['VAL']        = "volume";
                $this->measureArray['M1']['ALIASE']     = "NET_VOLUME";
                $this->measureArray['M1']['attr']	    = "SUM";		

                $this->measureArray['M2']['VAL']        = "grossvalue";
                $this->measureArray['M2']['ALIASE']     = "GROSS_SALES";
                $this->measureArray['M2']['attr']	    = "SUM";

                $this->measureArray['M3']['VAL']        = "grossvolume";
                $this->measureArray['M3']['ALIASE']     = "GROSS_CASES";
                $this->measureArray['M3']['attr']	    = "SUM";

                $this->measureArray['M4']['VAL']        = "returnDollars";
                $this->measureArray['M4']['ALIASE']     = "RETURN_SALES";
                $this->measureArray['M4']['attr']	    = "SUM";

                $this->measureArray['M5']['VAL']        = "returnCases";
                $this->measureArray['M5']['ALIASE']     = "RETURN_CASES";
                $this->measureArray['M5']['attr']	    = "SUM";

                $this->measureArray['M6']['VAL']        = "outDatedDollars";
                $this->measureArray['M6']['ALIASE']     = "OUTDATED_SALES";
                $this->measureArray['M6']['attr']	    = "SUM";

                $this->measureArray['M7']['VAL']        = "outdatedCases";
                $this->measureArray['M7']['ALIASE']     = "OUTDATED_CASES";
                $this->measureArray['M7']['attr']	    = "SUM";

                $this->measureArray['M8']['VAL']        = "(volume*equiv_8)";
                $this->measureArray['M8']['ALIASE']     = "NETE_QIV_8OZ"; 
                $this->measureArray['M8']['attr']	    = "SUM";

                $this->measureArray['M9']['VAL']        = "(grossvolume*equiv_8)";
                $this->measureArray['M9']['ALIASE']     = "GROSS_EQUIV_8OZ";
                $this->measureArray['M9']['attr']	    = "SUM";
                
                $this->dataArray['WEEK']['NAME'] = $this->weekperiod;
                $this->dataArray['WEEK']['NAME_ALIASE'] = 'WEEK';

                $this->dataArray['YEAR']['NAME'] = $this->yearperiod;
                $this->dataArray['YEAR']['NAME_ALIASE'] = 'YEAR';
                
                $this->pageArray["MEASURE_SELECTION_LIST"] = array(
                    array('measureID' => 0, 'jsonKey'=>'NET_SALES', 'measureName' => 'Net Sales', 'selected'=>true),
                    array('measureID' => 1, 'jsonKey'=>'NET_VOLUME', 'measureName' => 'Net Cases', 'selected'=>true),
                    array('measureID' => 2, 'jsonKey'=>'GROSS_SALES', 'measureName' => 'Gross Sales', 'selected'=>false),
                    array('measureID' => 3, 'jsonKey'=>'GROSS_CASES', 'measureName' => 'Gross Cases', 'selected'=>false),
                    array('measureID' => 4, 'jsonKey'=>'RETURN_SALES', 'measureName' => 'Return Sales', 'selected'=>false),
                    array('measureID' => 5, 'jsonKey'=>'RETURN_CASES', 'measureName' => 'Return Cases', 'selected'=>false),
                    array('measureID' => 6, 'jsonKey'=>'OUTDATED_SALES', 'measureName' => 'Outdated Sales', 'selected'=>false),
                    array('measureID' => 7, 'jsonKey'=>'OUTDATED_CASES', 'measureName' => 'Outdated Cases', 'selected'=>false),
                    array('measureID' => 8, 'jsonKey'=>'NETE_QIV_8OZ', 'measureName' => 'Net Equiv 8oz', 'selected'=>false),
                    array('measureID' => 9, 'jsonKey'=>'GROSS_EQUIV_8OZ', 'measureName' => 'Gross Equiv 8oz', 'selected'=>false),
                );
            }
            
            $this->outputDateOptions = array(			
                array("id" => "YEAR", "value" => "Year", "selected" => false),
                array("id" => "WEEK", "value" => "Week", "selected" => false)
            );
            
        }
    }   
    
    public function hasHiddenSku()
    {
        $queryVars = projectsettings\settingsGateway::getInstance();

        $redisCache = new utils\RedisCache($queryVars);
        $redisOutput = $redisCache->checkAndReadByStaticHashFromCache('lcl_has_hidden_sku');

        if ($queryVars->isInitialisePage || $redisOutput === false) {
            $query = "SELECT skuID FROM ".$this->skutable." WHERE GID IN (".$this->GID.") AND hide=1 ";
            $result = $queryVars->queryHandler->runQuery($query, $queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);

            if(is_array($result) && !empty($result))
            {
                $result = array_column($result, "skuID");
                $this->hiddenSkusQueryString = implode($result, ",");
            }
            $redisCache->setDataForStaticHash($this->hiddenSkusQueryString);
        } else {
            $this->hiddenSkusQueryString = $redisOutput;
        }

        if($this->hiddenSkusQueryString != "")
            return true;
        else
            return false;
    }    
    
	public function getMydateSelect($dateField, $withAggregate = true) {
		$dateFieldPart = explode('.', $dateField);
		$dateField = (count($dateFieldPart) > 1) ? $dateFieldPart[1] : $dateFieldPart[0];
		
		switch ($dateField) {
			case "period":
				$selectField = ($withAggregate) ? "MAX(".$this->maintable.".".$this->dateperiod.") " : $this->maintable.".".$this->dateperiod;
				break;
			case "mydate":
				$selectField = ($withAggregate) ? "MAX(".$this->timetable.".period) " : $this->timetable.".period ";
				break;
		}
		
		return $selectField;
	}    
    
    public function fetchGroupDetail()
    {
        $queryVars = projectsettings\settingsGateway::getInstance();

        $redisCache = new utils\RedisCache($queryVars);
        $redisOutput = $redisCache->checkAndReadByStaticHashFromCache('lcl_fgroup_detail');

        if ($queryVars->isInitialisePage || $redisOutput === false) {
            $query = "SELECT groupType,gname FROM ".$this->grouptable." WHERE gid IN (".$this->GID.") ";
            $result = $queryVars->queryHandler->runQuery($query, $queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);

            $redisCache->setDataForStaticHash($result);
        } else {
            $result = $redisOutput;
        }

        if(is_array($result) && !empty($result) && isset($result[0]['currency']) && !empty($result[0]['currency']))
            $this->currencySign = html_entity_decode($result[0]['currency']);
        else
            $this->currencySign = '$';

        if(is_array($result) && !empty($result) && isset($result[0]['gname']) && !empty($result[0]['gname']))
            $this->groupName = $result[0]['gname'];

        if(is_array($result) && !empty($result) && isset($result[0]['groupType']) && !empty($result[0]['groupType']))
            $this->groupType = $result[0]['groupType'];   

        return true;
    }           
    
}
?>