<?php
namespace projectsettings;

use projectsettings;
use db;
use utils;

class MjnPosMasterSystem extends BaseLcl {

    public function __construct($accountID,$projectID,$gids) {
	
        $this->aid                  = $accountID;
        $this->projectID            = $projectID;        
    
        $this->maintable            = "sales";
        $this->accounttable         = "fgroup";
        $this->groupType            = "POS";
        $this->clientID             = "MJN";
        
        if ($projectID == 559)
            $this->includeDateInTimeFilter  = true;
        else
            $this->includeDateInTimeFilter  = false;
        
        parent::__construct($gids);

        $this->dateperiod           = "mydate";
        $this->yearField            = "year";
        $this->weekField            = "week";
        
        $this->timetable            = "period_list";
        $this->weekperiod           = "$this->timetable.week";
        $this->yearperiod           = "$this->timetable.year";
        $this->ProjectVolume        = "volume";
        
        $this->accountLink = " AND $this->accounttable.GID IN ($this->GID) AND $this->maintable.GID = $this->accounttable.gid ";
        $this->accountHelperLink = " WHERE ".$this->accounttable.".GID IN (".$this->GID.") ";
        
        $this->productHelperLink = " WHERE GID IN (".$this->GID.") AND hide=0 ". $this->extraProductHelperQueryPart;
        
        $this->tableArray['product']['link'] 	= " WHERE GID IN (".$this->GID.") AND hide=0 ". $this->extraProductHelperQueryPart . $commonFilterQueryPart;      
        
		$this->configureClassVars();
        
        if(!$this->hasMeasureFilter){
            $this->pageArray["MEASURE_SELECTION_LIST"] = array(
                array('measureID' => 1, 'jsonKey'=>'VALUE', 'measureName' => 'Value'),
                array('measureID' => 2, 'jsonKey'=>'VOLUME', 'measureName' => 'Volume'),
                array('measureID' => 3, 'jsonKey'=>'EQUIV_OZ', 'measureName' => 'Equiv 8oz')
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
        
        $commontables   = $this->maintable . ", " . $this->timetable. ", ".$this->grouptable;
        $commonlink     = " WHERE $this->maintable.accountID=$this->aid " .
                        "AND $this->maintable.".$this->yearField."=".$this->yearperiod ." ".
                        "AND $this->maintable.".$this->weekField."=".$this->weekperiod ." ".
                        "AND $this->maintable.GID=$this->timetable.gid " .
                        "AND $this->maintable.GID=$this->grouptable.gid " .
                        "AND $this->grouptable.groupType = '$this->groupType' " .
                        "AND $this->timetable.gid IN (".$this->GID.") AND $this->maintable.project_type = 'sales' ".
                        "AND $this->maintable.GID IN (".$this->GID.") ";

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

        $this->copy_link     = $this->link     = $commonlink.$storelink.$territorylink.$skulink.$accountlink;

        $this->timeHelperTables = " " . $this->timetable . "," . $this->maintable . " ";
        $this->timeHelperLink = " WHERE " . $this->maintable . "." . $this->weekField . "=" . $this->weekperiod .
                                " AND " . $this->maintable . "." . $this->yearField . "=" . $this->yearperiod . 
                                " AND ".$this->maintable.".GID=".$this->timetable.".gid " .
                                " AND ".$this->timetable.".GID IN (".$this->GID.") ";

        if($this->hasHiddenSku()) {
            $commonlink   .= " AND ".$this->maintable.".skuID NOT IN ( ".$this->hiddenSkusQueryString.") ";
        }
                                
        $this->dataTable['default']['tables']      = $commontables;
        $this->dataTable['default']['link']        = $commonlink;

        $this->dataTable['product']['tables']      = $this->skutable;
        $this->dataTable['product']['link']        = $skulink;

        $this->dataTable['store']['tables']        = $this->storetable;
        $this->dataTable['store']['link']          = $storelink;

        $this->dataTable['territory']['tables']    = $this->territorytable;
        $this->dataTable['territory']['link']      = $territorylink;   
        
        $this->dataTable[$this->accounttable]['tables']        = "";
        $this->dataTable[$this->accounttable]['link']          = "";

        if($this->projectTypeID == 6) // DDB
        {
            $this->timeSelectionUnit  	= "week";
            
            $this->dataArray['WEEK']['NAME'] = $this->weekperiod;
            $this->dataArray['WEEK']['NAME_ALIASE'] = 'WEEK';

            $this->dataArray['YEAR']['NAME'] = $this->yearperiod;
            $this->dataArray['YEAR']['NAME_ALIASE'] = 'YEAR';
            
            $this->filterPages[5]['config'] = array(
                        "table_name" => $this->accounttable, 
                        "helper_table" => $this->accountHelperTables, 
                        "setting_name" => "account_settings", 
                        "helper_link" => $this->accountHelperLink,
                        "type" => "A", 
                        "enable_setting_name" => "has_account"
                    );
                    
            $this->outputDateOptions = array(			
                array("id" => "YEAR", "value" => "Year", "selected" => false),
                array("id" => "WEEK", "value" => "Week", "selected" => false)
            );
            
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
		
        if ($this->projectID == 559) {
            switch ($dateField) {
                case "period":
                    $selectField = ($withAggregate) ? "MAX(".$this->timetable.".".$this->dateperiod.") " : $this->timetable.".".$this->dateperiod;
                    break;
                case "mydate":
                    $selectField = ($withAggregate) ? "MAX(".$this->timetable.".".$this->dateperiod.") " : $this->timetable.".".$this->dateperiod;
                    break;
            }
        } else {
    		switch ($dateField) {
    			case "period":
    				$selectField = ($withAggregate) ? "MAX(".$this->maintable.".".$this->dateperiod.") " : $this->maintable.".".$this->dateperiod;
    				break;
    			case "mydate":
    				$selectField = ($withAggregate) ? "MAX(".$this->maintable.".".$this->dateperiod.") " : $this->maintable.".".$this->dateperiod;
    				break;
    		}
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

    public function prepareMeasureForFactoryShipmentOverview() {

        /*$this->pageArray["MEASURE_SELECTION_LIST"] = array(
            array('measureID' => 1, 'jsonKey'=>'NET', 'measureName' => 'Net', 'selected'=>false),
            array('measureID' => 2, 'jsonKey'=>'GROSS', 'measureName' => 'Gross', 'selected'=>true),
            array('measureID' => 3, 'jsonKey'=>'RETURN', 'measureName' => 'Return', 'selected'=>false),
            array('measureID' => 4, 'jsonKey'=>'OUTDATED', 'measureName' => 'Outdated', 'selected'=>false),
        );

        $this->measureArray                     = array();
        $this->measureArray['M1']['VAL']        = "value";
        $this->measureArray['M1']['ALIASE']     = "NET_SALES";
        $this->measureArray['M1']['attr']       = "SUM";
        $this->measureArray['M1']['NAME']       = "NET SALES";
                
        $this->measureArray['M2']['VAL']        = "volume";
        $this->measureArray['M2']['ALIASE']     = "NET_VOLUME";
        $this->measureArray['M2']['attr']       = "SUM";    
        $this->measureArray['M2']['NAME']       = "NET CASES";

        $this->measureArray['M3']['VAL']        = "(volume*".$this->skutable.".equiv_8)";
        $this->measureArray['M3']['ALIASE']     = "NET_EQUIV_8OZ"; 
        $this->measureArray['M3']['attr']       = "SUM"; 
        $this->measureArray['M3']['usedFields'] = array($this->skutable.'.equiv_8'); 
        $this->measureArray['M3']['NAME']       = "EQUIVALENT 80Z SERVINGS";

        $this->measureArray['M4']['VAL']        = "grossvalue";
        $this->measureArray['M4']['ALIASE']     = "GROSS_SALES";
        $this->measureArray['M4']['attr']       = "SUM";
        $this->measureArray['M4']['NAME']       = "GROSS SALES";

        $this->measureArray['M5']['VAL']        = "grossvolume";
        $this->measureArray['M5']['ALIASE']     = "GROSS_CASES";
        $this->measureArray['M5']['attr']       = "SUM";
        $this->measureArray['M5']['NAME']       = "GROSS CASES";

        $this->measureArray['M6']['VAL']        = "(grossvolume*".$this->skutable.".equiv_8)";
        $this->measureArray['M6']['ALIASE']     = "GROSS_EQUIV_8OZ";
        $this->measureArray['M6']['attr']       = "SUM";
        $this->measureArray['M6']['usedFields'] = array($this->skutable.'.equiv_8'); 
        $this->measureArray['M6']['NAME']       = "EQUIVALENT 80Z SERVINGS";

        $this->measureArray['M7']['VAL']        = "returnDollars";
        $this->measureArray['M7']['ALIASE']     = "RETURN_SALES";
        $this->measureArray['M7']['attr']       = "SUM";
        $this->measureArray['M7']['NAME']       = "RETURN SALES";

        $this->measureArray['M8']['VAL']        = "returnCases";
        $this->measureArray['M8']['ALIASE']     = "RETURN_CASES";
        $this->measureArray['M8']['attr']       = "SUM";
        $this->measureArray['M8']['NAME']       = "RETURN CASES";

        $this->measureArray['M9']['VAL']        = "(returnCases*".$this->skutable.".equiv_8)";
        $this->measureArray['M9']['ALIASE']     = "RETURN_EQUIV_8OZ";
        $this->measureArray['M9']['attr']       = "SUM";
        $this->measureArray['M9']['usedFields'] = array($this->skutable.'.equiv_8'); 
        $this->measureArray['M9']['NAME']       = "EQUIVALENT 80Z SERVINGS";

        $this->measureArray['M10']['VAL']        = "outDatedDollars";
        $this->measureArray['M10']['ALIASE']     = "OUTDATED_SALES";
        $this->measureArray['M10']['attr']       = "SUM";
        $this->measureArray['M10']['NAME']       = "OUTDATED SALES";

        $this->measureArray['M11']['VAL']        = "outdatedCases";
        $this->measureArray['M11']['ALIASE']     = "OUTDATED_CASES";
        $this->measureArray['M11']['attr']       = "SUM";
        $this->measureArray['M11']['NAME']       = "OUTDATED CASES";

        $this->measureArray['M12']['VAL']        = "(outdatedCases*".$this->skutable.".equiv_8)";
        $this->measureArray['M12']['ALIASE']     = "OUTDATED_EQUIV_8OZ";
        $this->measureArray['M12']['attr']       = "SUM";
        $this->measureArray['M12']['usedFields'] = array($this->skutable.'.equiv_8');
        $this->measureArray['M12']['NAME']       = "EQUIVALENT 80Z SERVINGS";*/

        /*$this->measureArray                     = array();
        $this->measureArray['M98']['VAL']        = "value";
        $this->measureArray['M98']['ALIASE']     = "VALUE";
        $this->measureArray['M98']['attr']       = "SUM";
        $this->measureArray['M98']['NAME']       = "VALUE";
                
        $this->measureArray['M99']['VAL']        = "volume";
        $this->measureArray['M99']['ALIASE']     = "VOLUME";
        $this->measureArray['M99']['attr']       = "SUM";  
        $this->measureArray['M99']['NAME']       = "VOLUME";

        $this->measureArray['M102']['VAL']        = "(volume*".$this->skutable.".equiv_8)";
        $this->measureArray['M102']['ALIASE']     = "EQUIVOZ"; 
        $this->measureArray['M102']['attr']       = "SUM"; 
        $this->measureArray['M102']['usedFields'] = array($this->skutable.'.equiv_8'); 
        $this->measureArray['M102']['NAME']       = "EQUIVALENT 80Z SERVINGS";*/

        $this->measureArrayMapping[$_REQUEST['ValueVolume']] = array_column($this->pageArray["MEASURE_SELECTION_LIST"], 'measureID');

        /*$this->measureArrayMapping = [
                                        1=>[1,3,2],
                                        2=>[4,6,5],
                                        3=>[7,9,8],
                                        4=>[10,12,11]
                                    ];*/
    }
}
?>