<?php

namespace classes\relayplus;

use datahelper;
use projectsettings;
use filters;
use db;
use config;

class OnPromoNow extends config\UlConfig {
    /*     * ***
     * Default gateway function, should be similar in all DATA CLASS
     * arguments:
     *  $settingVars [project settingsGateway variables]
     * @return $xmlOutput with all data that should be sent to client app
     * *** */

    public function go($settingVars) {
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES
        
        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true')
        {
            $this->jsonOutput['gridConfig']['enabledFiltersDispType'] = $this->settingVars->filtersDispType;
            $this->getDateList();
        }
        else
            $this->getPromoGridData();
                
        return $this->jsonOutput;
    }
    
    private function getDateList()
    {
        $query = "SELECT MIN(saleInFrom) as minDate, MAX(saleInTo) as maxDate FROM ".$this->settingVars->maintable." WHERE accountID = ".$this->settingVars->aid;
        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
        
        $query = "SELECT ADDDATE('".$result[0]['minDate']."', INTERVAL @i:=@i+1 DAY) AS DAY
                    FROM (
                        SELECT a.a
                            FROM (SELECT 0 AS a UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) AS a
                        CROSS JOIN (SELECT 0 AS a UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) AS b
                        CROSS JOIN (SELECT 0 AS a UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) AS c
                    ) a
                    JOIN (SELECT @i := -1) r1 
                WHERE @i < DATEDIFF('".$result[0]['maxDate']."', '".$result[0]['minDate']."') ORDER BY DAY ASC";        
        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
        
        $dateList = array();
        if(is_array($result) && !empty($result))
        {
            foreach($result as $data)
            {
                $dateList[] = array("data" => $data['DAY'], "label" => date("d-M-Y",strtotime($data['DAY'])));
            }
        }
        $this->jsonOutput['dateList'] = $dateList;
        $this->jsonOutput['selectedDate'] = array("data" => date('Y-m-d'), "label" => date('d-M-Y'));
    }
    
    private function getPromoGridData() 
    {
        $this->queryPart = $this->getAll();

        $dateQueryPart = "curdate()";
        if(isset($_REQUEST['selectedTodaysDate']) && $_REQUEST['selectedTodaysDate'] != "")
            $dateQueryPart = "'".$_REQUEST['selectedTodaysDate']."'";
        
        $query = "SELECT DISTINCT tmpStatus as TMP_STATUS, customer as CUSTOMER, promoID as PROMO_ID, promoDesc as PROMO_DESC, ".
                " saleInFrom as SALES_IN_FROM, saleInTo as SALE_IN_TO, localBrand as LOCAL_BRAND, product as PRODUCT ".
                " FROM " . $this->settingVars->tablename . $this->queryPart . " AND accountID = ".$this->settingVars->aid." AND ".$dateQueryPart." BETWEEN saleInFrom AND saleInTo";
        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
       
        $this->jsonOutput['promoGridList'] = $result;
    }

    public function getAll() {
        $tablejoins_and_filters = $this->settingVars->link;

        if (isset($_REQUEST["FS"]) && $_REQUEST["FS"] != '') {
            $tablejoins_and_filters .= filters\productAndMarketFilter::include_product_and_market_filters($this->queryVars, $this->settingVars);
        }

        return $tablejoins_and_filters;
    }

}

?>