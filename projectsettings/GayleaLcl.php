<?php

namespace projectsettings;

class GayleaLcl extends BaseLcl {

    public function __construct($aid, $projectID) {
        $this->maintable = "gaylea_mults";

        if($this->hasTerritory($aid, $projectID))
            $this->territorytable = "territory";

        $this->masterplanotable = "master_plano";
        $this->clientID = "GAYLEA";
        $this->aid = $aid; // 69
        $this->projectID = $projectID;

        parent::__construct();
        $this->clustertable = "gaylea_custom_product_group";
        
        $this->dataArray['F17']['NAME'] = 'level'.$this->territoryLevel;
        $this->dataArray['F17']['NAME_ALIASE'] = 'TERRITORY';
        $this->dataArray['F17']['tablename'] = $this->territorytable;
        $this->dataArray['F17']['link'] = "WHERE 1";
        $this->dataArray['F17']['use_alias_as_tag'] = true;

        $this->dataArray['F18']['NAME'] = 'CLUSTER';
        $this->dataArray['F18']['NAME_ALIASE'] = 'CLUSTER';
        $this->dataArray['F18']['tablename'] = $this->clustertable;
        $this->dataArray['F18']['link'] = "WHERE 1";

		// required in CONSOLIDATED_DISTRIBUTION page Only F32 AND F33
        $this->dataArray['F32']['ID'] = $this->masterplanotable .'.PIN';
        $this->dataArray['F32']['ID_ALIASE'] = 'PIN_A';
        $this->dataArray['F32']['NAME'] = 'PNAME';
        $this->dataArray['F32']['NAME_ALIASE'] = 'PNAME_A';

        $this->dataArray['F33']['ID'] = $this->maintable .'.PIN';
        $this->dataArray['F33']['ID_ALIASE'] = 'PIN_A';
        $this->dataArray['F33']['NAME'] = 'PNAME';
        $this->dataArray['F33']['NAME_ALIASE'] = 'PNAME_A';		
		
		// overwrite to make dynamic select part in CONSOLIDATED_DISTRIBUTION page
        $this->pageArray["CONSOLIDATED_DISTRIBUTION"]["planoStoresAccounts"] = "F32-F11-F15";
		$this->pageArray["CONSOLIDATED_DISTRIBUTION"]["planoSellingAccounts"] = "F33-F11-F15";		
		
		$this->territoryHelperTables 	= $this->maintable.",".$this->storetable.",".$this->skutable.",".$this->territorytable;
        $this->territoryHelperLink 		= " WHERE ".$this->maintable.".accountID=".$this->aid." AND ".$this->maintable.".SNO=".$this->storetable.".SNO".
										" AND ".$this->maintable.".PIN=".$this->skutable.".PIN".
										" AND ".$this->storetable.".SNO=".$this->territorytable.".SNO".
										" AND ".$this->skutable.".hide<>1".
                                        " AND ".$this->skutable.".gid IN (".$this->GID.")".
                                        " AND ".$this->storetable.".gid IN (".$this->GID.")".
                                        " AND ".$this->territorytable.".GID IN (".$this->GID.") AND ".$this->territorytable.".accountID=".$this->aid.
										" AND ".$this->skutable.".clientID='".$this->clientID."' ";

        if(!$this->hasMeasureFilter){                                        
            if($this->projectTypeID == 6) // DDB
            {
                $this->pageArray["MEASURE_SELECTION_LIST"] = array(
                    array('measureID' => 1, 'jsonKey'=>'VALUE', 'measureName' => 'VALUE ('.$this->currencySign.')', 'selected' => true ),
                    array('measureID' => 2, 'jsonKey'=>'VOLUME', 'measureName' => 'VOLUME', 'selected' => true ),
                    array('measureID' => 3, 'jsonKey'=>'PRICE', 'measureName' => 'AVE PRICE', 'selected' => false ),
                    array('measureID' => 4, 'jsonKey'=>'DISTRIBUTION', 'measureName' => 'STORES SELLING', 'selected' => false )
                );
            }
        }

        $this->configureClassVars();
    }

}

?>