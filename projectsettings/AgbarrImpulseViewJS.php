<?php
namespace projectsettings;

use db;

class AgbarrImpulseViewJS extends BaseTsdDailyReport {

    public function __construct($accountID,$projectID) {

        $this->maintable            = "agbarr_sku_store_daily_14";        
        $this->tesco_depot_daily    = "agbarr_tesco_depot_daily";

        $this->sku_supplier_daily   = "agbarr_sku_supplier_daily_14";
        
        $this->rangedtable          = "";
        $this->clientID             = "AGBARR";        
        $this->footerCompanyName    = "Agbarr";
        
        $this->GID = 6;
        
		parent::__construct($accountID,$projectID);	
        
        $this->getClientAndRetailerLogo();
        
        // measure selection list
        $this->pageArray["MEASURE_SELECTION_LIST"] = array(
            array('measureID' => 1, 'jsonKey'=>'VALUE', 'measureName' => 'Value ('.$this->currencySign.')', 'selected' => false),
            array('measureID' => 2, 'jsonKey'=>'VOLUME', 'measureName' => 'Units', 'selected' => true)
        );

        $this->fetchGroupDetail();
        $this->depotLink = " WHERE ".$this->tesco_depot_daily.".accountID=" . $this->aid . 
                " AND " . $this->tesco_depot_daily . ".skuID=" . $this->skutable . ".PIN " .
                " AND " . $this->tesco_depot_daily . ".GID=" . $this->skutable . ".GID " .
                " AND " . $this->skutable . ".GID=". $this->GID.
                " AND " . $this->skutable . ".clientID='" . $this->clientID . "' ";     //$periodFilterDepot

         $this->geoHelperLink = $this->storeHelperLink = " WHERE ".$this->maintable.".SNO = ".$this->storetable.".sno ".
                                " AND " . $this->storetable . ".gid=".$this->GID . 
                                " AND " . $this->maintable . ".accountID=" . $this->aid . 
                                " AND " . $this->storetable . ". gid = ". $this->GID . " ";

        $this->productHelperLink     = " WHERE ".$this->maintable.".skuID = ".$this->skutable.".PIN".
                                    " AND ". $this->maintable . ".accountID=" . $this->aid .                                    
                                    " AND ". $this->skutable . ".gid=" . $this->GID.
                                    " AND ". $this->maintable . ".GID=" . $this->skutable . ".gid ".
                                    " AND " . $this->skutable . ".clientID='" . $this->clientID . "' ";

        $this->configureClassVars();
        
        $this->dateperiod = $this->DatePeriod;
        /*[START] EXTARA PPC COLUMNS ONLY FOR THE SIF PAGE */
        $this->ppcColumnField = $this->skutable.".PPC";
        /*[END] EXTARA PPC COLUMNS ONLY FOR THE SIF PAGE */
    }
}
?>