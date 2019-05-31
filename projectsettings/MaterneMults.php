<?php

namespace projectsettings;

class MaterneMults extends BaseLcl {

    public function __construct($aid, $projectID, $gid) {
        $this->maintable = "materne_mults";

        if($this->hasTerritory($aid, $projectID))
            $this->territorytable = "territory";
            
        $this->clientID = "MATERNE";
        $this->aid = $aid;
        $this->projectID = $projectID;

        parent::__construct($gid);

        $this->configureClassVars();
        
        if(!$this->hasMeasureFilter){
        
            //measure selection list
            $this->pageArray["MEASURE_SELECTION_LIST"] = array(
                array('measureID' => 1, 'jsonKey'=>'VALUE', 'measureName' => 'Value ('.$this->currencySign.')', 'selected' => true ),
                array('measureID' => 2, 'jsonKey'=>'VOLUME', 'measureName' => 'Volume', 'selected' => false ),
                /*array('measureID' => 3, 'jsonKey'=>'KG', 'measureName' => 'KG', 'selected' => false ),
                array('measureID' => 4, 'jsonKey'=>'Weight', 'measureName' => 'Weight', 'selected' => false )*/
            );
            
            if($this->projectTypeID == 6) // DDB
            {
                $this->pageArray["MEASURE_SELECTION_LIST"] = array(
                    array('measureID' => 1, 'jsonKey'=>'VALUE', 'measureName' => 'VALUE ('.$this->currencySign.')', 'selected' => true ),
                    array('measureID' => 2, 'jsonKey'=>'VOLUME', 'measureName' => 'VOLUME', 'selected' => true ),
                    /*array('measureID' => 5, 'jsonKey'=>'PRICE', 'measureName' => 'AVE PRICE', 'selected' => false),
                    array('measureID' => 6, 'jsonKey'=>'DISTRIBUTION', 'measureName' => 'STORES SELLING', 'selected' => false),
                    array('measureID' => 3, 'jsonKey'=>'KG', 'measureName' => 'KG', 'selected' => false )*/
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