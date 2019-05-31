<?php

namespace projectsettings;

class ArlaMults extends BaseLcl {

    public function __construct($aid, $projectID, $gid) 
    {   
        $this->maintable = "arla_lcl";
    
        if($projectID == 518)
            $this->maintable = "arla_mults";

        if($this->hasTerritory($aid, $projectID))
            $this->territorytable = "territory";

        //$this->clustertable = "arla_custom_product_group";
        //$this->clustertable = "cluster";
        $this->accounttable = "fgroup";
        $this->clientID = "ARLA";
        $this->aid = $aid; // 52
        $this->projectID = $projectID;
        $this->pageTitlePrependText = "ARLA - ";

        /*[START] REMOVE IT WHEN MAKING THIS LIVE IS JUST FOR THE MAKING THE "DISTRIBUTION GAP-STORE DETAILS PAGE"*/
            $this->productplanotable = "lcl_product_plano";
            $this->storeplanotable = "lcl_store_plano";

            $this->lclStorePlanoTable   = "lcl_store_plano";
            $this->lclShelfDetailsTable = "lcl_shelf_details";
            $this->lclPlanoImageTable   = "lcl_plano_image";
            $this->lclPlanoClientTable  = "lcl_plano_client";
        /*[END]*/

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
            $this->performanceTabMappings["volumeOverTime"] = 'M6';
        }

        $this->pageArray["BIC_SALES_TRACKER"]['M1'] = array(
            "BASELINE" => array("ALIAS" => "BASELINE_SALES", "FIELD" => $this->maintable.".VAT"),
            "SALES" => array("ALIAS" => "SALES", "FIELD" => $this->maintable.".".$this->ProjectValue),
            "CANNIBALIZATION" => array("ALIAS" => "CANNIBALIZATION", "FIELD" => $this->maintable.".CUSTOMWEIGHT"));
                
        $this->pageArray["BIC_SALES_TRACKER"]['M2'] = array(
            "BASELINE" => array("ALIAS" => "BASELINE_QTY", "FIELD" => $this->maintable.".WEIGHT"),
            "SALES" => array("ALIAS" => "QTY", "FIELD" => $this->maintable.".".$this->ProjectVolume),
            "CANNIBALIZATION" => array("ALIAS" => "CANNIBALIZATION", "FIELD" => "0"));

        $this->fetchProductAndMarketFilterOnTabClick = false;
        if($this->projectTypeID != 6){ // DDB
            $this->fetchProductAndMarketFilterOnTabClick = true;
            $this->productAndMarketFilterTabDataLoadLimit = 100;
        }


        //$this->stock_qty = $this->maintable.".VAT";
    }
}
?>