<?php
namespace projectsettings;

use projectsettings;
use db;
use utils;

class MjnDistMasterSystem extends BaseLcl {

    public function __construct($accountID,$projectID,$gids) {
	
        $this->aid                  = $accountID;
        $this->projectID            = $projectID;    
    
        $this->maintable            = "sales_dist";
        $this->accounttable         = "fgroup";
        $this->groupType            = "DIST";
        $this->clientID             = "MJN";
        
        $this->includeDateInTimeFilter  = false;
        
        parent::__construct($gids);

        $this->includeFutureDates   = true;
        $this->storetable           = "store_dist";
        $this->timetable            = "period_list";
       
        $this->timeSelectionUnit    = "weekMonth";
        $this->weekperiod           = $this->monthperiod = "$this->maintable.month";
        $this->yearperiod           = "$this->maintable.year";
        $this->ProjectVolume        = "volume";
        
        $this->accountLink = " AND $this->accounttable.GID IN ($this->GID) AND $this->maintable.GID = $this->accounttable.gid ";
        $this->accountHelperLink = " WHERE ".$this->accounttable.".GID IN (".$this->GID.") ";
        
        $this->productHelperLink = " WHERE GID IN (".$this->GID.") AND hide=0 ". $this->extraProductHelperQueryPart;
        
        $this->geoHelperTables = $this->storetable;
        $this->geoHelperLink = " WHERE gid IN (".$this->GID.") ";
      
        $this->tableArray['product']['link'] 	= " WHERE GID IN (".$this->GID.") AND hide=0 ". $this->extraProductHelperQueryPart . $commonFilterQueryPart;      
      
		$this->configureClassVars();

        if(!$this->hasMeasureFilter){
            $this->pageArray["MEASURE_SELECTION_LIST"] = array(
                array('measureID' => 1, 'jsonKey'=>'VALUE', 'measureName' => 'Value'),
                array('measureID' => 2, 'jsonKey'=>'VOLUME', 'measureName' => 'Volume'),
                array('measureID' => 3, 'jsonKey'=>'EQUIV_OZ', 'measureName' => 'Equiv 8oz'),
            );        
        
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
        
        $commontables   = $this->maintable . ", ".$this->grouptable;
        $commonlink     = " WHERE $this->maintable.accountID=$this->aid " .
                        //"AND $this->maintable.".$this->dateperiod." = $this->timetable.".$this->dateperiod ." ".
                        //"AND $this->maintable.GID=$this->timetable.gid " .
                        "AND $this->maintable.GID=$this->grouptable.gid " .
                        "AND $this->grouptable.groupType = '$this->groupType' " .
                        //"AND $this->timetable.gid IN (".$this->GID.") ".
                        "AND $this->accounttable.gid IN (".$this->GID.") ".
                        "AND $this->maintable.GID IN ($this->GID) ";

        $storelink      = " AND $this->maintable.SNO=$this->storetable.SNO " .
                        "AND $this->maintable.GID=$this->storetable.GID " .
                        "AND $this->storetable.gid IN (".$this->GID.") ";
                        
        $territorylink  = ((!empty($this->territorytable)) ? " AND $this->storetable.SNO=$this->territorytable.SNO 
                        AND $this->storetable.GID=$this->territorytable.GID
                        AND $this->territorytable.GID IN ($this->GID) AND $this->territorytable.accountID = $this->aid " : "");
                        
        $accountlink  = ((!empty($this->accounttable)) ? $this->accountLink : "");
                        
        $skulink        = "AND $this->maintable.skuID=$this->skutable.skuID " .
                        "AND $this->maintable.GID=$this->skutable.GID " .
                        "AND $this->skutable.hide=0 " .
                        "AND $this->skutable.gid IN (".$this->GID.") ";

        $this->copy_link = $this->link = $commonlink.$storelink.$territorylink.$skulink.$accountlink;

        $this->timeHelperTables = " " . $this->maintable . " ";
        /* $this->timeHelperLink = " WHERE " . $this->maintable . "." . $this->dateperiod . "=" . $this->timetable .".".$this->dateperiod .
                                " AND ".$this->maintable.".GID=".$this->timetable.".gid " .
                                " AND ".$this->timetable.".GID IN (".$this->GID.") "; */
        $this->timeHelperLink = " WHERE ".$this->maintable.".GID IN (".$this->GID.") ";

        if($this->hasHiddenSku()) {
            $commonlink   .= " AND ".$this->maintable.".skuID NOT IN ( ".$this->hiddenSkusQueryString.") ";
        }
                                
        $this->dataTable['default']['tables']      = $commontables;
        $this->dataTable['default']['link']        = $commonlink;

        $this->dataTable['product']['tables']      = $this->skutable;
        $this->dataTable['product']['link']        = $skulink;

        $this->dataTable[$this->storetable]['tables']   = $this->storetable;
        $this->dataTable[$this->storetable]['link'] = $storelink;

        $this->dataTable['territory']['tables']    = $this->territorytable;
        $this->dataTable['territory']['link']      = $territorylink;   
        
        $this->dataTable[$this->accounttable]['tables']        = "";
        $this->dataTable[$this->accounttable]['link']          = "";        
        
        unset($this->dataTable['store']);
        
        if($this->projectTypeID == 6) // DDB
        {
            $this->includeFutureDates   = false;
            $this->timeSelectionUnit    = "month";
            
            $this->filterPages[3]['config'] = array(
                        "table_name" => $this->storetable, 
                        "helper_table" => $this->geoHelperTables, 
                        "setting_name" => "market_settings", 
                        "helper_link" => $this->geoHelperLink,
                        "type" => "M", 
                        "enable_setting_name" => "has_market_filter"
                    );
        
            $this->accountHelperTables = " ".$this->maintable. ",". $this->accounttable." ";
            $this->accountHelperLink = " WHERE ".$this->accounttable.".GID IN (".$this->GID.") AND ".$this->maintable.".GID = ".$this->accounttable.".GID ";
            
            $this->filterPages[5]['config'] = array(
                        "table_name" => $this->accounttable, 
                        "helper_table" => $this->accountHelperTables, 
                        "setting_name" => "account_settings", 
                        "helper_link" => $this->accountHelperLink,
                        "type" => "A", 
                        "enable_setting_name" => "has_account"
                    );
                    
            /* $this->pageArray["MEASURE_SELECTION_LIST"] = array(
                array('measureID' => 1, 'jsonKey'=>'VALUE', 'measureName' => 'VALUE', 'selected'=>true),
                array('measureID' => 2, 'jsonKey'=>'VOLUME', 'measureName' => 'VOLUME', 'selected'=>true),
                array('measureID' => 3, 'jsonKey'=>'PRICE', 'measureName' => 'AVE PRICE', 'selected'=>false)
            ); */
            
            if(!$this->hasMeasureFilter){
                $this->pageArray["MEASURE_SELECTION_LIST"] = array(
                    array('measureID' => 1, 'jsonKey'=>'VALUE', 'measureName' => 'VALUE ('.$this->currencySign.')', 'selected' => true ),
                    array('measureID' => 2, 'jsonKey'=>'VOLUME', 'measureName' => 'VOLUME', 'selected' => true ),
                    array('measureID' => 3, 'jsonKey'=>'EQUIV_OZ', 'measureName' => 'EQUIV OZ', 'selected' => true )
                );
                
                $this->measureArray['M3']['VAL'] 	= "volume*equiv_8";
                $this->measureArray['M3']['ALIASE'] = "EQUIV_OZ";
                $this->measureArray['M3']['attr'] 	= "SUM";
                unset($this->measureArray['M3']['dataDecimalPlaces']);
            }            
            
            $this->dataArray['MONTH']['NAME'] = $this->monthperiod;
            $this->dataArray['MONTH']['NAME_ALIASE'] = 'MONTH';
            $this->dataArray['MONTH']['TYPE'] = "T";

            $this->dataArray['YEAR']['NAME'] = $this->yearperiod;
            $this->dataArray['YEAR']['NAME_ALIASE'] = 'YEAR';
            $this->dataArray['YEAR']['TYPE'] = "T";
            
            $this->dataArray['YEARWEEK']['NAME'] = "CONCAT(".$this->yearperiod.",'-',"."LPAD(".$this->monthperiod.",2,'0')".")";
            $this->dataArray['YEARWEEK']['NAME_ALIASE'] = 'YEARWEEK';
            $this->dataArray['YEARWEEK']['TYPE'] = "T";
            $this->dataArray['YEARWEEK']['csv_header'] = "YEAR-MONTH";
            
            $this->outputDateOptions = array(			
                array("id" => "YEAR", "value" => "Year", "selected" => false),
                array("id" => "MONTH", "value" => "Month", "selected" => false)
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
				$selectField = ($withAggregate) ? "MAX(".$this->maintable.".".$this->dateperiod.") " : $this->maintable.".".$this->dateperiod;
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