<?php

namespace projectsettings;

use db;
use utils;

class FerreroTradeCalender extends baseMultsSummary {

    //tables
    public $maintable;
    public $brandtable;
    public $channeltable;
    public $tablename;
    public $link;
    //core setting vars
    public $aid;
    //time vars
    public $yearperiod;
    public $weekperiod;
    public $ProjectValue;
    public $ProjectVolume;
    public $PROJECTDEV;

    public function __construct($aid, $uid, $projectID) {

        $this->maintable = "ferrero_gantt";
        $this->brandtable = "powerbrand";
        $this->skulisttable = $this->channeltable = "subchannel";
        $this->producttable = "product";
        $this->aid = $aid;
        $this->projectID = $projectID;
        
        parent::__construct($aid, $uid, $projectID);

        $this->getClientAndRetailerLogo();
        
        $this->isSifPageRequired = false;
        $this->timetable = $this->maintable;
        $this->footerCompanyName = "Ferrero UK";
        $this->latestMydate = $this->getLastMyDate();
        $this->weekEndingText = "Data last updated";
        $this->filtersDispType = "inline";
        
        $this->configureClassVars();
        
        $this->copy_tablename = $this->tablename = " " . $this->maintable . ", ".$this->skulisttable;
        // . ",".$this->brandtable.",".$this->channeltable.",".$this->producttable

        /* $this->link = " WHERE accountID = " . $this->aid . " AND ".$this->maintable.".localBrand=".$this->brandtable.".brand ".
                    " AND ".$this->maintable.".customer=".$this->channeltable.".channel ".
                    " AND ".$this->maintable.".product=".$this->producttable.".product_raw "; */

        $this->link = " WHERE accountID = " . $this->aid . " AND ".$this->maintable.".customer = ".$this->channeltable.".channel ";
        $this->skuListHelperLink = "";
                    
        $this->dataTable['default']['tables']      = $this->tablename;
        $this->dataTable['default']['link']        = $this->link;
        
        $this->dataTable['subchannel']['tables']    = $this->skulisttable;
        $this->dataTable['subchannel']['link']      = $this->skuListHelperLink;        
        
        $this->yearperiod = "saleInFromYear";
        $this->weekperiod = "session";
        $this->monthperiod = "saleInFromMonth";

        $this->ProjectValue = $this->maintable.".QLSales";
        $this->ProjectVolume = $this->maintable.".grossRevenue";

        $this->timeHelperTables = " " . $this->maintable . " ";
        $this->timeHelperLink = " WHERE $this->yearperiod<>0 ";
        $this->monthHelperLink = " WHERE $this->monthperiod<>0 ";

        $this->productHelperTables = " " . $this->maintable . ",".$this->brandtable;
        $this->productHelperLink = " WHERE ".$this->maintable.".localBrand=".$this->brandtable.".brand ";

        /* $this->masterProductHelperTables = " " . $this->maintable . ",".$this->producttable;
        $this->masterProductHelperLink = " WHERE ".$this->maintable.".product=".$this->producttable.".product_raw " ; */

        /* $this->marketHelperTables = " " . $this->maintable . ",".$this->channeltable;
        $this->marketHelperLink = " WHERE ".$this->maintable.".customer=".$this->channeltable.".channel " ; */

        $this->accounttable = $this->maintable;
        $this->accountHelperTables = $this->maintable;
        $this->accountHelperLink = " WHERE accountID = ".$this->aid;
        
        /* $this->measureArray = array();
        $this->measureArray['M0']['VAL'] = $this->ProjectValue;
        $this->measureArray['M0']['ALIASE'] = "VALUE";
        $this->measureArray['M0']['attr'] = "SUM";

        $this->measureArray['M1']['VAL'] = $this->ProjectVolume;
        $this->measureArray['M1']['ALIASE'] = "VOLUME";
        $this->measureArray['M1']['attr'] = "SUM"; */

        /* $this->dataArray = array();

        $this->dataArray['F1']['NAME'] = 'localBrand';
        $this->dataArray['F1']['NAME_ALIASE'] = 'Brand';
        $this->dataArray['F1']['tablename'] = $this->productHelperTables;
        $this->dataArray['F1']['link'] = $this->productHelperLink;

        $this->dataArray['F2']['NAME'] = $this->maintable.'.channel';
        $this->dataArray['F2']['NAME_ALIASE'] = "CHANNEL";
        $this->dataArray['F2']['tablename'] = $this->productHelperTables;
        $this->dataArray['F2']['link'] = $this->productHelperLink;
        
        $this->dataArray['F3']['NAME'] = 'product';
        $this->dataArray['F3']['NAME_ALIASE'] = "SKU_LIST";
        $this->dataArray['F3']['tablename'] = $this->productHelperTables;
        $this->dataArray['F3']['link'] = $this->productHelperLink;
        
        $this->dataArray['F4']['NAME'] = 'customer';
        $this->dataArray['F4']['NAME_ALIASE'] = "Customer_LIST";
        $this->dataArray['F4']['tablename'] = $this->productHelperTables;
        $this->dataArray['F4']['link'] = $this->productHelperLink;

        $this->dataArray['F5']['NAME'] = 'tmpStatus';
        $this->dataArray['F5']['NAME_ALIASE'] = "Status_LIST";
        $this->dataArray['F5']['tablename'] = $this->productHelperTables;
        $this->dataArray['F5']['link'] = $this->productHelperLink;

        $this->dataArray['F6']['NAME'] = 'powerbrand';
        $this->dataArray['F6']['NAME_ALIASE'] = "Powerbrand_LIST";
        $this->dataArray['F6']['tablename'] = $this->productHelperTables;
        $this->dataArray['F6']['link'] = $this->productHelperLink;

        $this->dataArray['F7']['NAME'] = 'subchannel';
        $this->dataArray['F7']['NAME_ALIASE'] = "Subchannel_LIST";
        $this->dataArray['F7']['tablename'] = $this->marketHelperTables;
        $this->dataArray['F7']['link'] = $this->marketHelperLink;

        $this->dataArray['F8']['NAME'] = $this->maintable.'.insertdate';
        $this->dataArray['F8']['NAME_ALIASE'] = "MYDATE";

        $this->dataArray['F9']['NAME'] = 'saleInFromYear';
        $this->dataArray['F9']['NAME_ALIASE'] = "Year_LIST";
        $this->dataArray['F9']['tablename'] = $this->timeHelperTables;
        $this->dataArray['F9']['link'] = $this->timeHelperLink;

        $this->dataArray['F10']['NAME'] = 'saleInFromSession';
        $this->dataArray['F10']['NAME_ALIASE'] = "Session_LIST";
        $this->dataArray['F10']['tablename'] = $this->timeHelperTables;
        $this->dataArray['F10']['link'] = $this->timeHelperLink;

        $this->dataArray['F11']['NAME'] = 'product_master';
        $this->dataArray['F11']['NAME_ALIASE'] = "Master_Product_List";
        $this->dataArray['F11']['tablename'] = $this->masterProductHelperTables;
        $this->dataArray['F11']['link'] = $this->masterProductHelperLink; */
    }

    public function fetchGroupDetail(){
        return true;
    }
    
    public function hasHiddenSku(){
        return false;
    }
        
    public function getMydateSelect($dateField, $withAggregate = true) {
        return "MAX(".$this->timetable.".insertdate)";
    }
    
    public function getLastMyDate()
    {
        $queryVars = settingsGateway::getInstance();
        $query = "SELECT MAX(insertdate) as MaxMydate FROM ".$this->maintable." WHERE accountID = ".$this->aid;
        $result = $queryVars->queryHandler->runQuery($query, $queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
        if(is_array($result) && !empty($result))
            return $result[0]['MaxMydate'];
        else
            return "";
    }
}

?>