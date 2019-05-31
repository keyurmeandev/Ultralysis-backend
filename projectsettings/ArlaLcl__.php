<?php

namespace projectsettings;

class ArlaLcl extends BaseLcl {

    public function __construct($aid, $projectID) {
        $this->maintable = "arla_lcl";

        if($this->hasTerritory($aid, $projectID))
            $this->territorytable = "territory";
            
        $this->clustertable = "arla_custom_product_group";
        $this->accounttable = "fgroup";
        $this->clientID = "ARLA";
        $this->aid = $aid; // 52
        $this->projectID = $projectID;

        $this->lclStorePlanoTable   = "lcl_store_plano";
        $this->lclShelfDetailsTable = "lcl_shelf_details";
        $this->lclPlanoImageTable   = "lcl_plano_image";
        $this->lclPlanoClientTable  = "lcl_plano_client";

        parent::__construct();
        
        // $this->timeSelectionStyle = 'DROPDOWN';
        //** controlFlagField only used on the Item Ranking Report page
        $this->controlFlagField = 'product.pl';

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
            unset($this->measureArray['M3']['dataDecimalPlaces']);

            $this->measureArray['M4']['VAL'] = "weight";
            $this->measureArray['M4']['ALIASE'] = "Weight";
            $this->measureArray['M4']['attr'] = "SUM";
            unset($this->measureArray['M3']['dataDecimalPlaces']);

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
            
            $this->performanceTabMappings = array(
                "drillDown" => 'M1',
                "overTime" => 'M1',
                "priceOverTime" => 'M5',
                "distributionOverTime" => 'M6'
            );            
            
            $this->detailedDriverAnalysisChartTabMappings = array(
                "valueChart" => 'M1',
                "unitChart" => 'M2',
                "priceChart" => 'M5',
                "sellingStoreChart" => 'M6'
            );
            
            $this->pageArray["BIC_SALES_TRACKER"]['M1'] = array( 
                "BASELINE" => array("ALIAS" => "BASELINE_SALES", "FIELD" => $this->maintable.".VAT"),
                "SALES" => array("ALIAS" => "SALES", "FIELD" => $this->maintable.".".$this->ProjectValue),
                "CANNIBALIZATION" => array("ALIAS" => "CANNIBALIZATION", "FIELD" => $this->maintable.".CUSTOMWEIGHT") );
                
            $this->pageArray["BIC_SALES_TRACKER"]['M2'] = array( 
                "BASELINE" => array("ALIAS" => "BASELINE_QTY", "FIELD" => $this->maintable.".WEIGHT"),
                "SALES" => array("ALIAS" => "QTY", "FIELD" => $this->maintable.".".$this->ProjectVolume),
                "CANNIBALIZATION" => array("ALIAS" => "CANNIBALIZATION", "FIELD" => "0") );
            
        }
    }

}

?>