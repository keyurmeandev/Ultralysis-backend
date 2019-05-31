<?php

namespace projectsettings;

use filters;

class FerreroBookerSalesBuilder extends BaseMults {

    public function __construct($aid, $projectID, $gids) {
        $this->maintable = "ferrero_booker";

        $this->territorytable = "territory";

        $this->masterplanotable = "";
        $this->rdetrackertable = "rde_tracker";
        $this->clientID = "FERRERO";
        $this->aid = $aid;
        $this->projectID = $projectID;
        $gids = (isset($_REQUEST['GID']) && !empty($_REQUEST['GID'])) ? array($_REQUEST['GID']) : $gids;
		$this->GID = implode(",",$gids);
        
        parent::__construct($gids);

        $this->fetchProductAndMarketFilterOnTabClick = false;

        /*$this->productLevelToChart = ['product.PNAME#product.PIN'];*/
        
        $this->weekperiod = "$this->timetable.week";
        $this->yearperiod = "$this->timetable.year";
        $this->ProjectCases="CASES";
        
        $this->includeFutureDates = false;

        $this->currencySign = "£";
		$this->territoryField = $this->territorytable.".Level1";

		$this->productHelperTables = " " . $this->maintable . "," . $this->skutable . "," . $this->storetable;
        $this->productHelperLink = " WHERE ".$this->maintable.".accountID=".$this->aid .
        " AND ".$this->maintable.".SNO=".$this->storetable.".SNO " .
        " AND ".$this->maintable.".GID=".$this->storetable.".GID " .
        " AND ".$this->maintable.".PIN=".$this->skutable.".PIN " .
        " AND ".$this->maintable.".GID=".$this->skutable.".GID " .
        " AND ".$this->skutable.".hide<>1 " .
        " AND ".$this->skutable.".gid IN (".$this->GID.") ".
        " AND ".$this->maintable.".gid IN (".$this->GID.") ".
        " AND ".$this->storetable.".gid IN (".$this->GID.") ".
        " AND ".$this->skutable.".clientID='".$this->clientID."' ";
        

		if(isset($_REQUEST['filtered_territory']) && !empty($_REQUEST['filtered_territory']))
		{
            $territoryWhere = ' AND '.$this->territoryField. ' IN ("'.implode('", "', explode(",", $_REQUEST['filtered_territory'])).'") ';
			$this->tableArray['store']['tables'] 	= $this->storetable.",".$this->territorytable;
			$this->tableArray['store']['link'] 		= " WHERE ".$this->storetable.".gid IN (".$this->GID.") ". 
														" AND ".$this->storetable.".SNO=".$this->territorytable.".SNO".
														" AND ".$this->storetable.".GID=".$this->territorytable.".GID".
														" AND ".$this->territorytable.".GID IN (".$this->GID.") AND ".$this->territorytable.".accountID=".$this->aid.
														$territoryWhere.$commonFilterQueryPart;
			$this->tableArray['store']['type'] 		= 'M';

			$this->geoHelperTables = $this->storetable.",".$this->territorytable;
        	$this->geoHelperLink = " WHERE ".$this->storetable.".gid IN (".$this->GID.") ". 
									" AND ".$this->storetable.".SNO=".$this->territorytable.".SNO".
									" AND ".$this->storetable.".GID=".$this->territorytable.".GID".
									" AND ".$this->territorytable.".GID IN (".$this->GID.") AND ".$this->territorytable.".accountID=".$this->aid.
									$territoryWhere.$commonFilterQueryPart;

			$this->productHelperTables .= ",".$this->territorytable;	
			$this->productHelperLink .= " AND ".$this->storetable.".SNO=".$this->territorytable.".SNO".
										" AND ".$this->storetable.".GID=".$this->territorytable.".GID".
										" AND ".$this->territorytable.".GID IN (".$this->GID.") AND ".$this->territorytable.".accountID=".$this->aid.$territoryWhere;	
		}

        $this->tableArray['product']['tables']  = $this->skutable.",".$this->maintable;
        $this->tableArray['product']['link']    = " WHERE ".$this->skutable.".gid IN (".$this->GID.") ". 
                                                    " AND ".$this->maintable.".PIN=".$this->skutable.".PIN".
                                                    " AND ".$this->maintable.".GID=".$this->skutable.".GID".
                                                    " AND ".$this->skutable.".hide<>1 " .
                                                    " AND ".$this->skutable.".gid IN (".$this->GID.") ".
                                                    " AND ".$this->skutable.".clientID='".$this->clientID."' ";
        $this->tableArray['product']['type']    = 'P';


        // measure selection list
        $this->pageArray["MEASURE_SELECTION_LIST"] = array(
            array('measureID' => 1, 'jsonKey'=>'VALUE', 'measureName' => 'Value (£)', 'selected' => true ),
            array('measureID' => 2, 'jsonKey'=>'VOLUME', 'measureName' => 'Volume (Units)', 'selected' => true),
            array('measureID' => 5, 'jsonKey'=>'CASES', 'measureName' => 'Volume (Cases)', 'selected' => false )
        );        

        if(isset($_REQUEST['GID']) && $_REQUEST['GID'] == 3 ){
			// measure selection list
	        $this->pageArray["MEASURE_SELECTION_LIST"] = array(
	            array('measureID' => 1, 'jsonKey'=>'VALUE', 'measureName' => 'Value (£)', 'selected' => false ),
                array('measureID' => 2, 'jsonKey'=>'VOLUME', 'measureName' => 'Volume (Units)', 'selected' => true),
                array('measureID' => 5, 'jsonKey'=>'CASES', 'measureName' => 'Volume (Cases)', 'selected' => false )
	        );        	
        }


        $this->dataArray['F17']['NAME'] = 'level'.$this->territoryLevel;
        $this->dataArray['F17']['NAME_ALIASE'] = 'TERRITORY';
        $this->dataArray['F17']['tablename'] = $this->territorytable;
        $this->dataArray['F17']['link'] = "WHERE GID IN (".$this->GID.")";
		$this->dataArray['F17']['use_alias_as_tag'] = true;
		
		$this->dataArray['F35']['NAME'] 		= "gname";
        $this->dataArray['F35']['NAME_ALIASE'] 	= 'CUSTOMER';
		
		$this->dataArray['F36']['NAME'] 		= "Level1";
        $this->dataArray['F36']['NAME_ALIASE'] 	= 'AREA MANAGER';
		
		$this->dataArray['F37']['NAME'] 		= "Level2";
        $this->dataArray['F37']['NAME_ALIASE'] 	= 'FE';

        $this->dataArray['WEEK']['NAME'] = $this->weekperiod;
        $this->dataArray['WEEK']['NAME_ALIASE'] = 'WEEK';

        $this->dataArray['YEAR']['NAME'] = $this->yearperiod;
        $this->dataArray['YEAR']['NAME_ALIASE'] = 'YEAR';
		
		/**
         *  Executive Summary Page
         */
        //$this->pageArray["EXE_SUMMARY_PAGE"]["DH"] = "F1-F3-F4-F5-F2#F19-F6-F7-F8-F9-F10-F11-F12-F13-F14-F15-F16-F26-F30-F25";
	$this->pageArray["EXE_SUMMARY_PAGE"]["DH"] = "";
        $this->pageArray["EXE_SUMMARY_PAGE"]["PODS"] = array("DATA_ONE" => "agg2", "DATA_TWO" => "banner_alt");
        $this->pageArray["EXE_SUMMARY_PAGE"]["TITLE"]["POD_ONE"] = "Total Sales";
        $this->pageArray["EXE_SUMMARY_PAGE"]["TITLE"]["POD_TWO"] = "Share by Brand Range";
        $this->pageArray["EXE_SUMMARY_PAGE"]["TITLE"]["POD_THREE"] = "Brand Range Performance";
        $this->pageArray["EXE_SUMMARY_PAGE"]["TITLE"]["POD_FOUR"] = "Share by Format";
        $this->pageArray["EXE_SUMMARY_PAGE"]["TITLE"]["POD_FIVE"] = "Format Performance";
	
					
		
		$this->territoryHelperTables 	= $this->maintable.",".$this->storetable.",".$this->skutable.",".$this->territorytable;
        $this->territoryHelperLink 		= " WHERE ".$this->maintable.".accountID=".$this->aid." AND ".$this->maintable.".SNO=".$this->storetable.".SNO".
										" AND ".$this->maintable.".PIN=".$this->skutable.".PIN".
										" AND ".$this->maintable.".GID=".$this->skutable.".GID".
										" AND ".$this->maintable.".GID=".$this->storetable.".GID".
										" AND ".$this->storetable.".SNO=".$this->territorytable.".SNO".
										" AND ".$this->storetable.".GID=".$this->territorytable.".GID".
										" AND ".$this->skutable.".hide<>1".
                                        " AND ".$this->skutable.".gid IN (".$this->GID.")".
                                        " AND ".$this->storetable.".gid IN (".$this->GID.")".
                                        " AND ".$this->territorytable.".GID IN (".$this->GID.") AND ".$this->territorytable.".accountID=".$this->aid.
										" AND ".$this->skutable.".clientID='".$this->clientID."' ";		
		
        // set project name and footer text
		$this->pageArray["PROJECT_NAME"] = "FERRERO SALES BUILDER";
					
        $this->pageArray["PROJECT_FOOTER_TEXT"] = "All data contained is owned by ".$this->pageArray["PROJECT_NAME"]." and made available under strict terms. Activity is monitored, and violation of terms will be reported to ".$this->pageArray["PROJECT_NAME"].".";
										
        $this->configureClassVars();

        $this->uploadDir = __DIR__ . "/../../".$_REQUEST['projectDIR']."/templates/FERRERO/";
        $this->uploadUrl = $this->get_full_url()."/".$_REQUEST['projectDIR']."/templates/FERRERO/";

    }
	public function configureClassVars()
    {
        $this->dateField = $this->maintable . "." . $this->dateperiod;
        $this->tablename = " " . $this->maintable . "," . $this->timetable . "," . $this->skutable . "," . $this->storetable. "," . $this->grouptable;
        
        if (!empty($this->territorytable))
            $this->tablename .= "," . $this->territorytable . " ";
        
        $commontables   = $this->maintable . ", " . $this->timetable;
        $commonlink     = " WHERE $this->maintable.accountID=$this->aid " .
                        "AND $this->maintable.".$this->dateperiod."=$this->timetable.".$this->dateperiod." " .
                        "AND $this->maintable.GID=$this->timetable.gid " .
                        "AND $this->timetable.gid IN (".$this->GID.") ";

        $storelink      = " AND $this->maintable.SNO=$this->storetable.SNO " .
                        "AND $this->maintable.GID=$this->storetable.GID " .
                        "AND $this->storetable.gid IN (".$this->GID.") ";
                        
        $territorylink  = ((!empty($this->territorytable)) ? " AND $this->storetable.SNO=$this->territorytable.SNO 
                        AND $this->storetable.GID=$this->territorytable.GID
                        AND $this->territorytable.GID IN ($this->GID) AND $this->territorytable.accountID = $this->aid " : "");

        $accountlink  = ((!empty($this->accounttable)) ? $this->accountLink : "");
                        
        $skulink        = "AND $this->maintable.PIN=$this->skutable.PIN " .
                        "AND $this->maintable.GID=$this->skutable.GID " .
                        "AND $this->skutable.hide<>1 " .
                        "AND $this->skutable.gid IN (".$this->GID.") ".
                        "AND $this->skutable.clientID='$this->clientID' ";

        $this->copy_link     = $this->link     = $commonlink.$storelink.$territorylink.$skulink.$accountlink;

        $this->timeHelperTables = $this->timetable;
        $this->timeHelperLink = " WHERE " . $this->timetable.".GID IN (".$this->GID.") ".
                                " AND ". $this->timetable . "." . $this->dateperiod . 
                                " IN ( SELECT DISTINCT " . $this->maintable . "." . $this->dateperiod . 
                                    " FROM " . $this->maintable . " WHERE ".$this->maintable.".GID IN (".$this->GID.") ) ";            

        $this->dataTable['default']['tables']      = $commontables;
        $this->dataTable['default']['link']        = $commonlink;

        $this->dataTable['product']['tables']      = $this->skutable;
        $this->dataTable['product']['link']        = $skulink;

        $this->dataTable['store']['tables']        = $this->storetable;
        $this->dataTable['store']['link']          = $storelink;

        $this->dataTable['territory']['tables']    = $this->territorytable;
        $this->dataTable['territory']['link']      = $territorylink;

        $this->dataTable[$this->accounttable]['tables']        = $this->accounttable;
        $this->dataTable[$this->accounttable]['link']          = $accountlink;

        $this->measureArray = array();
        $this->measureArray['M1']['VAL'] = $this->ProjectValue;
        $this->measureArray['M1']['ALIASE'] = "VALUE";
        $this->measureArray['M1']['attr'] = "SUM";

        $this->measureArray['M2']['VAL'] = $this->ProjectVolume;
        $this->measureArray['M2']['ALIASE'] = "VOLUME";
        $this->measureArray['M2']['attr'] = "SUM";

        $this->measureArray['M3']['VAL'] = "(SUM(IFNULL($this->ProjectValue,0))/SUM(IFNULL($this->ProjectVolume,1)))";
        $this->measureArray['M3']['ALIASE'] = "PRICE";      
        $this->measureArray['M3']['dataDecimalPlaces'] = 2;  
        
        $this->measureArray['M4']['VAL'] = "DISTINCT((CASE WHEN ".$this->ProjectValue." > 0 THEN ".$this->maintable.".SNO END))";
        $this->measureArray['M4']['ALIASE'] = "DISTRIBUTION";
        $this->measureArray['M4']['attr'] = "COUNT";
        $this->measureArray['M4']['dataDecimalPlaces'] = 0;  

        $this->measureArray['M5']['VAL'] = $this->ProjectCases;
        $this->measureArray['M5']['ALIASE'] = "CASES";
        $this->measureArray['M5']['attr'] = "SUM";             

        $this->measureArray_for_productKBI_only = array();
        $this->measureArray_for_productKBI_only['M0']['VAL'] = $this->ProjectVolume;
        $this->measureArray_for_productKBI_only['M0']['ALIASE'] = "VOL";
        $this->measureArray_for_productKBI_only['M0']['attr'] = "SUM";

        $this->measureArray_for_productKBI_only['M1']['VAL'] = $this->ProjectValue;
        $this->measureArray_for_productKBI_only['M1']['ALIASE'] = "VAL";
        $this->measureArray_for_productKBI_only['M1']['attr'] = "SUM";

        $this->getClientProjectName();
    }

    public function get_full_url() {
        $https = !empty($_SERVER['HTTPS']) && strcasecmp($_SERVER['HTTPS'], 'on') === 0 ||
            !empty($_SERVER['HTTP_X_FORWARDED_PROTO']) &&
                strcasecmp($_SERVER['HTTP_X_FORWARDED_PROTO'], 'https') === 0;
                
        return ($https ? 'https://' : 'http://').
            (!empty($_SERVER['REMOTE_USER']) ? $_SERVER['REMOTE_USER'].'@' : '').
            (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : ($_SERVER['SERVER_NAME'].
            ($https && $_SERVER['SERVER_PORT'] === 443 ||
            $_SERVER['SERVER_PORT'] === 80 ? '' : ':'.$_SERVER['SERVER_PORT']))).
            substr($_SERVER['SCRIPT_NAME'],0, -19);
    }
}

?>
