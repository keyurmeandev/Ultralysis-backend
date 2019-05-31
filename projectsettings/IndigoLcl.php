<?php

namespace projectsettings;

class IndigoLcl extends BaseLcl {

    public function __construct($aid, $projectID) {
        $this->maintable = "arla_mults";

        if($this->hasTerritory($aid, $projectID))
            $this->territorytable = "territory";

        $this->clientID = "INDIGO";
        $this->aid = $aid;
        $this->projectID = $projectID;

        parent::__construct();
		
        $this->clustertable = "";
        $this->dataArray['F17']['NAME'] = 'level'.$this->territoryLevel;
        $this->dataArray['F17']['NAME_ALIASE'] = 'TERRITORY';
        $this->dataArray['F17']['tablename'] = $this->territorytable;
        $this->dataArray['F17']['link'] = "WHERE 1";
        $this->dataArray['F17']['use_alias_as_tag'] = true;

        $this->dataArray['F18']['NAME'] = 'CLUSTER';
        $this->dataArray['F18']['NAME_ALIASE'] = 'CLUSTER';
        $this->dataArray['F18']['tablename'] = $this->clustertable;
        $this->dataArray['F18']['link'] = "WHERE 1";
        
        $this->territoryHelperTables    = $this->maintable.",".$this->storetable.",".$this->skutable.",".$this->territorytable;
        $this->territoryHelperLink      = " WHERE ".$this->maintable.".accountID=".$this->aid." AND ".$this->maintable.".SNO=".$this->storetable.".SNO".
                                        " AND ".$this->maintable.".PIN=".$this->skutable.".PIN".
                                        " AND ".$this->storetable.".SNO=".$this->territorytable.".SNO".
                                        " AND ".$this->skutable.".hide<>1".
                                        " AND ".$this->skutable.".gid IN (".$this->GID.")".
                                        " AND ".$this->storetable.".gid IN (".$this->GID.")".
                                        " AND ".$this->territorytable.".GID IN (".$this->GID.") AND ".$this->territorytable.".accountID=".$this->aid.
                                        " AND ".$this->skutable.".clientID='".$this->clientID."' ";
        
        $this->configureClassVars();

        $commonlink     = " WHERE $this->maintable.accountID=$this->aid " .
                        "AND $this->maintable.mydate=$this->timetable.mydate " .
                        "AND $this->maintable.GID=$this->timetable.gid " .
                        "AND $this->timetable.gid IN (".$this->GID.") ";

        $commonlink     .= "AND $this->maintable.PIN=$this->skutable.PIN " .
                        "AND $this->maintable.GID=$this->skutable.GID " .
                        "AND $this->skutable.hide<>1 " .
                        "AND $this->skutable.gid IN (".$this->GID.") ".
                        "AND $this->skutable.clientID='$this->clientID' ";

        $storelink      = " AND $this->maintable.SNO=$this->storetable.SNO " .
                        "AND $this->maintable.GID=$this->storetable.GID " .
                        "AND $this->storetable.gid IN (".$this->GID.") ";
                        
        $territorylink  = ((!empty($this->territorytable)) ? " AND $this->storetable.SNO=$this->territorytable.SNO 
                        AND $this->storetable.GID=$this->territorytable.GID
                        AND $this->territorytable.GID IN ($this->GID) AND $this->territorytable.accountID = $this->aid " : "");
                        
        if($this->hasHiddenSku()) {
            $commonlink   .= " AND ".$this->maintable.".PIN NOT IN ( ".$this->hiddenSkusQueryString.") ";
        }
                        
        $this->copy_link     = $this->link     = $commonlink.$storelink.$territorylink;

        $this->dataTable['default']['tables']      = $this->maintable . "," . $this->timetable . "," . $this->skutable;
        $this->dataTable['default']['link']        = $commonlink;

        $this->dataTable['product']['tables']      = "";
        $this->dataTable['product']['link']        = "";

        $this->dataTable['store']['tables']        = $this->storetable;
        $this->dataTable['store']['link']          = $storelink;

        $this->dataTable['territory']['tables']    = $this->territorytable;
        $this->dataTable['territory']['link']      = $territorylink;

        if(!$this->hasMeasureFilter){
            //measure selection list
            $this->pageArray["MEASURE_SELECTION_LIST"] = array(
                array('measureID' => 1, 'jsonKey'=>'VALUE', 'measureName' => 'Value ('.$this->currencySign.')', 'selected' => true ),
                array('measureID' => 2, 'jsonKey'=>'VOLUME', 'measureName' => 'Volume', 'selected' => false ),
                array('measureID' => 3, 'jsonKey'=>'KG', 'measureName' => 'KG', 'selected' => false ),
                array('measureID' => 4, 'jsonKey'=>'Weight', 'measureName' => 'Weight', 'selected' => false )
            );

            if($this->projectTypeID == 6) // DDB
            {
                $this->pageArray["MEASURE_SELECTION_LIST"] = array(
                    array('measureID' => 1, 'jsonKey'=>'VALUE', 'measureName' => 'VALUE ('.$this->currencySign.')', 'selected' => true ),
                    array('measureID' => 2, 'jsonKey'=>'VOLUME', 'measureName' => 'VOLUME', 'selected' => true ),
                    array('measureID' => 5, 'jsonKey'=>'PRICE', 'measureName' => 'AVE PRICE', 'selected' => false),
                    array('measureID' => 6, 'jsonKey'=>'DISTRIBUTION', 'measureName' => 'STORES SELLING', 'selected' => false),
                    array('measureID' => 3, 'jsonKey'=>'KG', 'measureName' => 'KG', 'selected' => false )
                );
            }

            $this->measureArray['M3']['VAL']    = "ROUND(QTY*converting,0)";
            $this->measureArray['M3']['ALIASE'] = "KG";
            $this->measureArray['M3']['attr']   = "SUM";
            $this->measureArray['M3']['usedFields'] = array('product.converting');
            unset($this->measureArray['M3']['dataDecimalPlaces']);

            $this->measureArray['M4']['VAL'] = "weight";
            $this->measureArray['M4']['ALIASE'] = "Weight";
            $this->measureArray['M4']['attr'] = "SUM";  
            unset($this->measureArray['M4']['dataDecimalPlaces']);

            $this->measureArray['M5']['VAL'] = "(SUM(IFNULL($this->ProjectValue,0))/SUM(IFNULL($this->ProjectVolume,1)))";
            $this->measureArray['M5']['ALIASE'] = "PRICE";
            $this->measureArray['M5']['attr'] = "";
            $this->measureArray['M5']['dataDecimalPlaces'] = 2;

            $this->measureArray['M6']['VAL'] = "DISTINCT((CASE WHEN ".$this->ProjectValue." > 0 THEN ".$this->maintable.".SNO END))";
            $this->measureArray['M6']['ALIASE'] = "DISTRIBUTION";
            $this->measureArray['M6']['attr'] = "COUNT";
            $this->measureArray['M6']['dataDecimalPlaces'] = 0;

            $this->performanceTabMappings["priceOverTime"] = 'M5';
            $this->performanceTabMappings["distributionOverTime"] = 'M6';
        }

    }

}

?>