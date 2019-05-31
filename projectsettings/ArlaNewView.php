<?php

namespace projectsettings;

class ArlaNewView extends BaseLcl {

    public function __construct($aid, $projectID, $gid) {
        $this->maintable = "arla_lcl";

        if($this->hasTerritory($aid, $projectID))
            $this->territorytable = "territory";
            
        $this->clustertable = "arla_custom_product_group";
        $this->accounttable = "fgroup";
        $this->clientID = "ARLA";
        $this->aid = $aid; // 52
        $this->projectID = $projectID;
        $this->pageTitlePrependText = "ARLA - ";

        parent::__construct($gid);

        if ((isset($_REQUEST['YtdOn']) && $_REQUEST['YtdOn'] == 'ACCOUNT_YEAR')) {
            //We dont need to overwrite this conditions because it already loaded form the BASELCL CLASS
        }else{
            $this->weekperiod = "$this->timetable.week";
            $this->yearperiod = "$this->timetable.year";
        }

        $this->accountLink = " AND $this->accounttable.GID IN ($this->GID) AND $this->maintable.GID=$this->accounttable.gid ";
        $this->accountHelperLink = " WHERE ".$this->accounttable.".GID IN (".$this->GID.") ";

        if(!$this->hasMeasureFilter){        
    		//measure selection list
            $this->pageArray["MEASURE_SELECTION_LIST"] = array(
                array('measureID' => 1, 'jsonKey'=>'VALUE', 'measureName' => 'Value ('.$this->currencySign.')', 'selected' => true ),
                array('measureID' => 2, 'jsonKey'=>'VOLUME', 'measureName' => 'Volume', 'selected' => false ),
                array('measureID' => 3, 'jsonKey'=>'KG', 'measureName' => 'KG', 'selected' => false ),
                array('measureID' => 4, 'jsonKey'=>'Weight', 'measureName' => 'Weight', 'selected' => false )
            );
        }
		
        $this->configureClassVars();

        if(!$this->hasMeasureFilter){
            $this->measureArray['M3']['VAL']    = "ROUND(CUSTOMWEIGHT*converting,0)";
            $this->measureArray['M3']['ALIASE'] = "KG";
            $this->measureArray['M3']['attr']   = "SUM";
            $this->measureArray['M3']['usedFields'] = array('product.converting');

            $this->measureArray['M4']['VAL'] = "weight";
            $this->measureArray['M4']['ALIASE'] = "Weight";
            $this->measureArray['M4']['attr'] = "SUM";  

            $this->measureArray['M5']['VAL'] = "(SUM(IFNULL($this->ProjectValue,0))/SUM(IFNULL($this->ProjectVolume,1)))";
            $this->measureArray['M5']['ALIASE'] = "PRICE";
            $this->measureArray['M5']['attr'] = "";

            $this->measureArray['M6']['VAL'] = "DISTINCT((CASE WHEN ".$this->ProjectValue." > 0 THEN ".$this->maintable.".SNO END))";
            $this->measureArray['M6']['ALIASE'] = "DISTRIBUTION";
            $this->measureArray['M6']['attr'] = "COUNT";

            $this->performanceTabMappings["priceOverTime"] = 'M5';
            $this->performanceTabMappings["distributionOverTime"] = 'M6';
        }

        $this->fetchProductAndMarketFilterOnTabClick = true;
        $this->productAndMarketFilterTabDataLoadLimit = 100;
    }

}

?>