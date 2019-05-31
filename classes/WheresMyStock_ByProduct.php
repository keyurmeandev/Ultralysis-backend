<?php

namespace classes;

use db;
use filters;
use config;

class WheresMyStock_ByProduct extends config\UlConfig {

    private $skuID, $skuName, $storeID, $storeName, $pageName;

    /*     * ***
     * Default gateway function, should be similar in all DATA CLASS
     * arguments:
     *  $settingVars [project settingsGateway variables]
     * @return $jsonOutput with all data that should be sent to client app
     * *** */

    public function go($settingVars) {
        $this->initiate($settingVars);

        $this->pageName = $_REQUEST["pageName"];

        $this->settingVars->pageName = $this->pageName; // set pageName in settingVars
        //SET REQUIRED FIELD FOR QUERY SENT FORM CLIENT APPLICATION
        $this->skuID = $this->settingVars->dataArray[$this->settingVars->pageArray[$this->pageName]["SKU_FIELD"]]['ID'];
        $this->skuName = $this->settingVars->dataArray[$this->settingVars->pageArray[$this->pageName]["SKU_FIELD"]]['NAME'];
        $this->storeID = $this->settingVars->dataArray[$this->settingVars->pageArray[$this->pageName]["STORE_FIELD"]]['ID'];
        $this->storeName = $this->settingVars->dataArray[$this->settingVars->pageArray[$this->pageName]["STORE_FIELD"]]['NAME'];


        $action = $_REQUEST["action"];
        switch ($action) {
            case "skuchange": return $this->changesku();
                break;
            case "reload": return $this->Reload();
                break;
        }
    }

    private function changesku() {
        $this->queryPart = $this->getAll();
        $this->valueLatestPeriodStore();
        $this->valueFunc2();

        $this->gridValue2();  //adding to output

        return $this->jsonOutput;
    }

    private function Reload() {
        $this->queryPart = $this->getAll(); //USES CUSTOM getAll FUNCTION
        $this->valueLatestPeriodSku();
        $this->valueFunc();
        $this->gridValue(); //adding to output

        return $this->jsonOutput;
    }

    private function valueLatestPeriodStore() {
        //PROVIDING GLOBALS
        global $LastWeek_Sales_Array, $Last_2_Weeks_Sales_Array;

        $LastWeek_Sales_Array = array();
        $Last_2_Weeks_Sales_Array = array();


        //CALCULATING LAST WEEKS SALE
        $qpart = $this->queryPart . " AND " .$this->settingVars->maintable. '.'.$this->settingVars->period . " IN (" . implode(",", filters\timeFilter::getPeriodWithinRange(1, 1, $this->settingVars)) . ") ";
        $query = "SELECT " . $this->storeID . " AS SNO" .
                ",SUM(" . $this->settingVars->ProjectVolume . ") AS QTY " .
                "FROM " . $this->settingVars->tablename . $qpart . " " .
                "GROUP BY SNO";
        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
        foreach ($result as $key => $data) {
            $sno = $data['SNO'];
            $LastWeek_Sales_Array[$sno] = $data['QTY'];
        }



        //CALCULATING LAST 2 WEEKS SALE
        $qpart = $this->queryPart . " AND " .$this->settingVars->maintable. '.'.$this->settingVars->period . " IN (" . implode(",", filters\timeFilter::getPeriodWithinRange(2, 1, $this->settingVars)) . ") ";
        $query = "SELECT " . $this->storeID . "  AS SNO" .
                ",SUM(" . $this->settingVars->ProjectVolume . ") AS QTY " .
                "FROM " . $this->settingVars->tablename . $qpart . " " .
                "GROUP BY SNO";
        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
        foreach ($result as $key => $data) {
            $sno = $data['SNO'];
            $Last_2_Weeks_Sales_Array[$sno] = $data['QTY'];
        }
    }

    private function valueLatestPeriodSku() {
        //PROVIDING GLOBALS
        global $LastWeek_Sales_Array, $Last_2_Weeks_Sales_Array;

        $LastWeek_Sales_Array = array();
        $Last_2_Weeks_Sales_Array = array();


        //CALCULATING LAST WEEK SALES
        $qpart = $this->queryPart . " AND " .$this->settingVars->maintable. '.'.$this->settingVars->period . " IN (" . implode(",", filters\timeFilter::getPeriodWithinRange(1, 1, $this->settingVars)) . ") ";
        $query = "SELECT " . $this->skuID . " AS SIN" .
                ",SUM(" . $this->settingVars->ProjectVolume . ") AS QTY " .
                "FROM " . $this->settingVars->tablename . $qpart . " " .
                "GROUP BY SIN";
        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
        foreach ($result as $key => $data) {
            $SIN = $data['SIN'];
            $LastWeek_Sales_Array[$SIN] = $data['QTY'];
        }




        //CALCULATING LAST 2 WEEKS SALES
        $qpart = $this->queryPart . " AND " .$this->settingVars->maintable. '.'.$this->settingVars->period . " IN (" . implode(",", filters\timeFilter::getPeriodWithinRange(2, 1, $this->settingVars)) . ") ";
        $query = "SELECT " . $this->skuID . " AS SIN" .
                ",SUM(" . $this->settingVars->ProjectVolume . ") AS QTY " .
                "FROM " . $this->settingVars->tablename . $qpart . " " .
                "GROUP BY SIN";
        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
        foreach ($result as $key => $data) {
            $SIN = $data['SIN'];
            $Last_2_Weeks_Sales_Array[$SIN] = $data['QTY'];
        }
    }

    private function valueFunc2() {
        //PROVIDING GLOBALS
        global $ddArr1, $atsArr1, $avsArr1;

        $ddArr1 = array();
        $atsArr1 = array();
        $avsArr1 = array();

        $qpart = $this->queryPart . " AND " .$this->settingVars->maintable. '.'.$this->settingVars->period . " IN (" . implode(",", filters\timeFilter::getPeriodWithinRange(1, 1, $this->settingVars)) . ") ";
        $query = "SELECT " . $this->skuID . " AS SIN" .
                "," . $this->storeID . "  AS SNO" .
                ",ATS" .
                ",AVS" .
                ",SUM(dd_cases* WHPK_Qty) AS DD " .
                "FROM " . $this->settingVars->tablename . $qpart . " " .
                "GROUP BY SIN,SNO,ATS,AVS";
        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
        foreach ($result as $key => $data) {
            $sin = $data['SIN'];
            $sno = $data['SNO'];
            $atsArr1[$sin][$sno] = $data['ATS'];
            $avsArr1[$sin][$sno] = $data['AVS'];
            $ddArr1[$sin][$sno] = $data['DD'];
        }
    }

    private function valueFunc() {
        //PROVIDING GLOBALS
        global $ddArr1, $atsArr1, $avsArr1;

        $ddArr1 = array();
        $atsArr1 = array();
        $avsArr1 = array();

        $qpart = $this->queryPart . " AND " .$this->settingVars->maintable.'.'.$this->settingVars->period . " IN (" . implode(",", filters\timeFilter::getPeriodWithinRange(1, 1, $this->settingVars)) . ") ";
        $query = "SELECT " . $this->skuID . " AS SIN" .
                ",SUM(dd_cases* WHPK_Qty) AS DD " .
                "FROM " . $this->settingVars->tablename . $qpart . " " .
                "GROUP BY SIN";
        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
        foreach ($result as $key => $data) {
            $sin = $data['SIN'];
            $ddArr1[$sin] = $data['DD'];
        }
    }

    private function gridValue() {
        global $LastWeek_Sales_Array, $Last_2_Weeks_Sales_Array, $ddArr1;
        

        $qpart = $this->queryPart . " AND " .$this->settingVars->maintable.'.'.$this->settingVars->period . " IN (" . implode(",", filters\timeFilter::getPeriodWithinRange(0, 1, $this->settingVars)) . ") ";
        $query = "SELECT " . $this->skuID . " AS SIN" .
                ",MAX(" . $this->skuName . ") AS SKU" .
                ",SUM(OHQ) AS OHQ" .
                ",SUM(" . $this->settingVars->ProjectVolume . ") AS QTY" .
                ",WHPK_Qty AS WHPK " .
                "FROM " . $this->settingVars->tablename . $qpart . " " .
                "GROUP BY SIN,WHPK";
        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
        foreach ($result as $key => $data) {
            $SIN = $data['SIN'];
            $slw = $LastWeek_Sales_Array[$SIN];
            $sllw = $Last_2_Weeks_Sales_Array[$SIN];
            $dd = $ddArr1[$SIN];

            $temp = array();
            $temp['SKU'] = $data['SIN'];
            $temp['SKUNAME'] = htmlspecialchars_decode($data['SKU']);
            $temp['OHQ'] = $data['OHQ'];
            $temp['QTY'] = $data['QTY'];
            $temp['CQTY'] = $data['WHPK'];
            $temp['SLW'] = $slw;
            $temp['SLLW'] = $sllw;
            $temp['DD'] = $dd;
            $this->jsonOutput['gridTop'][] = $temp;
        }
    }

    function gridValue2() {
        global $LastWeek_Sales_Array, $Last_2_Weeks_Sales_Array, $ddArr1, $atsArr1, $avsArr1;
        $sin = $_REQUEST['SKU'];
       // $sin = $_REQUEST['ACCOUNT'];

        $qpart = $this->queryPart . " AND " .$this->settingVars->maintable. '.'.$this->settingVars->period . " IN (" . implode(",", filters\timeFilter::getPeriodWithinRange(0, 1, $this->settingVars)) . ") ";
        $query = "SELECT " . $this->storeID . "  AS SNO" .
                ",MAX(" . $this->storeName . ") AS SNAME" .
                ",SUM(OHQ) AS OHQ" .
                ",SUM(" . $this->settingVars->ProjectVolume . ") AS QTY" .
                ",SUM(MSQ) AS MSQ " .
                "FROM " . $this->settingVars->tablename . $qpart . " " .
                "GROUP BY SNO";
        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
        foreach ($result as $key => $data) {
            $sno = $data['SNO'];
            $slw = $LastWeek_Sales_Array[$sno];
            $sllw = $Last_2_Weeks_Sales_Array[$sno];
            $dd = $ddArr1[$sin][$sno];

            $ats1 = $atsArr1[$sin][$sno];
            $avs1 = $avsArr1[$sin][$sno];

            if ($data['OHQ'] != 0) {

                $temp = array();
                $temp['STORE'] = $data['SNO'];
                $temp['STORENAME'] = htmlspecialchars_decode($data['SNAME']);
                $temp['OHQ'] = $data['OHQ'];
                $temp['QTY'] = $data['QTY'];
                $temp['MSQ'] = $data['MSQ'];
                $temp['SLW'] = $slw;
                $temp['SLLW'] = $sllw;
                $temp['ATS1'] = $ats1;
                $temp['AVS1'] = $avs1;
                $temp['DD'] = $dd;
                $this->jsonOutput['gridBottom'][] = $temp;
            }
        }
    }

    /*     * * OVERRIDING PARENT CLASS'S getAll FUNCTION ** */

    public function getAll() {
        $tablejoins_and_filters = parent::getAll();

        /* if (isset($_REQUEST["FS"]) && $_REQUEST["FS"] != '') {
            $tablejoins_and_filters .= filters\productAndMarketFilter::include_product_and_market_filters($this->queryVars, $this->settingVars);
        } */

        if ($_REQUEST['SKU'] != "")
            $tablejoins_and_filters .=" AND " . $this->skuID . "=" . $_REQUEST['SKU'];

        return $tablejoins_and_filters;
    }

}

?>