<?php

namespace projectsettings;

class agbarrRetailLink extends BaseLcl {

    public function __construct($aid, $projectID) {
        $this->maintable = "agbarr_retail_link";

        if($this->hasTerritory($aid, $projectID))
            $this->territorytable = "territory";
            
        $this->clientID = "AGBARR";
        $this->aid = $aid; // 3
        $this->projectID = $projectID;
		
		$this->GID = 2;
		
        parent::__construct(array($this->GID));

		$this->clustertable = "arla_custom_product_group";
		$this->periodField = "period";
		$this->ProjectValue = "qty";
		
        if(!$this->hasMeasureFilter){
    		// measure selection list
            $this->pageArray["MEASURE_SELECTION_LIST"] = array(
                array('measureID' => 1, 'jsonKey'=>'VALUE', 'measureName' => 'Value ('.$this->currencySign.')'),
                array('measureID' => 2, 'jsonKey'=>'VOLUME', 'measureName' => 'Volume')
            );
        }

        $this->configureClassVars();

        $commontables   = $this->maintable . "," . $this->timetable;
        $commonlink     = " WHERE $this->maintable.accountID=$this->aid " .
                        "AND $this->maintable.".$this->periodField."=$this->timetable.".$this->periodField." " .
                        "AND $this->timetable.gid IN (".$this->GID.") ";

        $storelink      = " AND $this->maintable.SNO=$this->storetable.SNO " .
                        "AND $this->storetable.gid IN (".$this->GID.") ";
                        
        $territorylink  = ((!empty($this->territorytable)) ? " AND $this->storetable.SNO=$this->territorytable.SNO 
                        AND $this->storetable.GID=$this->territorytable.GID
                        AND $this->territorytable.GID IN ($this->GID) AND $this->territorytable.accountID = $this->aid " : "");
                        
        $skulink        = "AND $this->maintable.PIN=$this->skutable.PIN " .
                        "AND $this->skutable.hide<>1 " .
                        "AND $this->skutable.gid IN (".$this->GID.") ".
                        "AND $this->skutable.clientID='$this->clientID' ";

        $this->copy_link     = $this->link     = $commonlink.$storelink.$territorylink.$skulink;

        if($this->hasHiddenSku()) {
            $commonlink   .= " AND ".$this->maintable.".PIN NOT IN ( ".$this->hiddenSkusQueryString.") ";
        }

        $this->dataTable['default']['tables']      = $commontables;
        $this->dataTable['default']['link']        = $commonlink;

        $this->dataTable['product']['tables']      = $this->skutable;
        $this->dataTable['product']['link']        = $skulink;

        $this->dataTable['store']['tables']        = $this->storetable;
        $this->dataTable['store']['link']          = $storelink;

        $this->dataTable['territory']['tables']    = $this->territorytable;
        $this->dataTable['territory']['link']      = $territorylink;
		
		$this->timeHelperLink = " WHERE " . $this->maintable . "." . $this->periodField . "=" . $this->timetable . "." . $this->periodField . 
                                " AND ".$this->timetable.".GID IN (".$this->GID.") ";
		
		$this->dateField = $this->timetable . "." . $this->dateperiod;
    }

}

?>