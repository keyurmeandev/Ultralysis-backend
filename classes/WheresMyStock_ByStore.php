<?php

namespace classes;

use db;
use filters;
use config;

class WheresMyStock_ByStore extends config\UlConfig {

    private $skuID, $skuName, $storeID, $storeName, $pageName;

    /*     * ***
     * Default gateway function, should be similar in all DATA CLASS
     * arguments:
     *  $settingVars [project settingsGateway variables]
     * @return $jsonOutput with all data that should be sent to client app
     * *** */

    public function go($settingVars) {
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES

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
        $this->valueLatestPeriodSku();
        $this->valueFunc2();
        $this->gridValue2(); //ADDING TO OUTPUT

        return $this->jsonOutput;
    }

    private function Reload() {
        $this->queryPart = $this->getAll();
        $this->valueLatestPeriodStore();
        $this->valueFunc();
        $this->gridValue(); //ADDING TO OUTPUT

        return $this->jsonOutput;
    }

    private function valueLatestPeriodSku() {
        //PROVIDING GLOBALS
        global $LastWeek_Sales_Array, $SeondLastWeek_Sales_Array;

        $LastWeek_Sales_Array = array();
        $SeondLastWeek_Sales_Array = array();

        $qpart = $this->queryPart . " AND " .  "." .$this->settingVars->maintable.".". $this->settingVars->period . " IN (" . implode(",", filters\timeFilter::getPeriodWithinRange(1, 1, $this->settingVars)) . ") ";
        $query = "SELECT " . $this->skuID . " AS SIN" .
                ",SUM(" . $this->settingVars->ProjectVolume . ") AS QTY " .
                "FROM " . $this->settingVars->tablename . $qpart . " " .
                "GROUP BY SIN";
        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
        foreach ($result as $key => $data) {
            $LastWeek_Sales_Array[$data['SIN']] = $data['QTY'];
        }


        $qpart = $this->queryPart . " AND " .$this->settingVars->maintable. "." . $this->settingVars->period . " IN (" . implode(",", filters\timeFilter::getPeriodWithinRange(2, 1, $this->settingVars)) . ") ";
        $query = "SELECT " . $this->skuID . " AS SIN" .
                ",SUM(" . $this->settingVars->ProjectVolume . ") AS QTY " .
                "FROM " . $this->settingVars->tablename . $qpart . " " .
                "GROUP BY SIN";
        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
        foreach ($result as $key => $data) {
            $SeondLastWeek_Sales_Array[$data['SIN']] = $data['QTY'];
        }
    }

    private function valueLatestPeriodStore() {
        //PROVIDING GLOBALS
        global $LastWeek_Sales_Array, $SeondLastWeek_Sales_Array;

        $LastWeek_Sales_Array = array();
        $SeondLastWeek_Sales_Array = array();


        $qpart = $this->queryPart . " AND " . $this->settingVars->maintable."." . $this->settingVars->period . " IN (" . implode(",", filters\timeFilter::getPeriodWithinRange(1, 1, $this->settingVars)) . ") ";
        $query = "SELECT " . $this->storeID . "  AS SNO" .
                ",SUM(" . $this->settingVars->ProjectVolume . ") AS QTY " .
                "FROM " . $this->settingVars->tablename . $qpart . " " .
                "GROUP BY SNO";
        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
        foreach ($result as $key => $data) {
            $LastWeek_Sales_Array[$data['SNO']] = $data['QTY'];
        }



        $qpart = $this->queryPart . " AND " .  $this->settingVars->maintable."." . $this->settingVars->period . " IN (" . implode(",", filters\timeFilter::getPeriodWithinRange(2, 1, $this->settingVars)) . ") ";
        $query = "SELECT " . $this->storeID . "  AS SNO" .
                ",SUM(" . $this->settingVars->ProjectVolume . ") AS QTY " .
                "FROM " . $this->settingVars->tablename . $qpart . " " .
                "GROUP BY SNO";
        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
        foreach ($result as $key => $data) {
            $SNO = $data[0];
            $SeondLastWeek_Sales_Array[$data['SNO']] = $data['QTY'];
        }
    }

    private function valueFunc2() {
        //PROVIDING GLOBALS
        global $ddArr1, $atsArr1, $avsArr1;

        $ddArr1 = array();

        $qpart = $this->queryPart . " AND " .  $this->settingVars->maintable."." . $this->settingVars->period . " IN (" . implode(",", filters\timeFilter::getPeriodWithinRange(1, 1, $this->settingVars)) . ") ";
        $query = "SELECT " . $this->skuID . " AS SIN" .
                "," . $this->storeID . "  AS SNO" .
                ",ATS AS ATS" .
                ",AVS AS AVS" .
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
        global $ddArr1;

        $ddArr1 = array();

        $qpart = $this->queryPart . " AND " .  $this->settingVars->maintable."." . $this->settingVars->period . " IN (" . implode(",", filters\timeFilter::getPeriodWithinRange(1, 1, $this->settingVars)) . ") ";
        $query = "SELECT " . $this->storeID . "  AS SNO" .
                ",SUM(dd_cases* WHPK_Qty) AS DD " .
                "FROM " . $this->settingVars->tablename . $qpart . " " .
                "GROUP BY SNO";
        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
        foreach ($result as $key => $data) {
            $ddArr1[$data['SNO']] = $data['DD'];
        }
    }

    private function gridValue() {
        global $SeondLastWeek_Sales_Array, $LastWeek_Sales_Array;
        $qpart = $this->queryPart . " AND " .  $this->settingVars->maintable."." . $this->settingVars->period . " IN (" . implode(",", filters\timeFilter::getPeriodWithinRange(0, 1, $this->settingVars)) . ") ";
        $query = "SELECT " . $this->storeID . "  AS SNO" .
                ",MAX(" . $this->storeName . ") AS SNAME" .
                ",SUM(OHQ) AS OHQ" .
                ",SUM(" . $this->settingVars->ProjectVolume . ") AS QTY" .
                ",SUM(WHPK_Qty) WHQ " .
                " FROM " . $this->settingVars->tablename . $qpart . " " .
                "GROUP BY SNO";
        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
        foreach ($result as $key => $data) {
            $SNO = $data['SNO'];
            $slw = $LastWeek_Sales_Array[$SNO];
            $sllw = $SeondLastWeek_Sales_Array[$SNO];
            $dd = $ddArr1[$SNO];

//            $value = $this->jsonOutput->addChild('gridValue');
//            $value->addChild("SNO", $data['SNO']);
//            $value->addChild("STORENAME", $data['SNAME']);
//            $value->addChild("OHQ", $data['OHQ']);
//            $value->addChild("QTY", $data['QTY']);
//            $value->addChild("CQTY", $data['WHQ']);
//            $value->addChild("SLW", $slw);
//            $value->addChild("SLLW", $sllw);
//            $value->addChild("DD", $dd);


            $temp = array();
            $temp['SNO'] = $data['SNO'];
            $temp['STORENAME'] = $data['SNAME'];
            $temp['OHQ'] = $data['OHQ'];
            $temp['QTY'] = $data['QTY'];
            $temp['CQTY'] = $data['WHQ'];
            $temp['SLW'] = $slw;
            $temp['SLLW'] = $sllw;
            $temp['DD'] = $dd;
            $this->jsonOutput['gridTop'][] = $temp;
        }
    }

    private function gridValue2() {
        global $LastWeek_Sales_Array, $SeondLastWeek_Sales_Array, $ddArr1, $atsArr1, $avsArr1;

        $sno = $_REQUEST['SNO'];

        $qpart = $this->queryPart . " AND " .  $this->settingVars->maintable."." . $this->settingVars->period . " IN (" . implode(",", filters\timeFilter::getPeriodWithinRange(0, 1, $this->settingVars)) . ") ";
        $query = "SELECT " . $this->skuID . " AS SIN" .
                ",MAX(" . $this->skuName . ") AS SKU" .
                ",SUM(OHQ) AS OHQ" .
                ",SUM(" . $this->settingVars->ProjectVolume . ") AS QTY" .
                ",SUM(MSQ) AS MSQ" .
                ",WHPK_Qty AS WHQ " .
                "FROM " . $this->settingVars->tablename . $qpart . " " .
                "GROUP BY SIN,WHQ";
        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
        foreach ($result as $key => $data) {
            $sin = $data['SIN'];
            $slw = $LastWeek_Sales_Array[$sin];
            $sllw = $SeondLastWeek_Sales_Array[$sin];
            $dd = $ddArr1[$sin][$sno];

            $ats1 = $atsArr1[$sin][$sno];
            $avs1 = $avsArr1[$sin][$sno];

            if ($data['OHQ'] != 0) {
//                $value = $this->jsonOutput->addChild("gridValue2");
//                $value->addChild("SKU", $data['SIN']);
//                $value->addChild("SKUNAME", htmlspecialchars_decode($data['SKU']));
//                $value->addChild("OHQ", $data['OHQ']);
//                $value->addChild("QTY", $data['QTY']);
//                $value->addChild("MSQ", $data['MSQ']);
//                $value->addChild("CQTY", $data['WHQ']);
//                $value->addChild("SLW", $slw);
//                $value->addChild("SLLW", $sllw);
//                $value->addChild("ATS1", $ats1);
//                $value->addChild("AVS1", $avs1);
//                $value->addChild("DD", $dd);


                $temp = array();
                $temp['SKU'] = $data['SIN'];
                $temp['SKUNAME'] = htmlspecialchars_decode($data['SKU']);
                $temp['OHQ'] = $data['OHQ'];
                $temp['QTY'] = $data['QTY'];
                $temp['MSQ'] = $data['MSQ'];
                $temp['CQTY'] = $data['WHQ'];
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

        if ($_REQUEST['SNO'] != "")
            $tablejoins_and_filters .=" AND " . $this->storeID . " =" . $_REQUEST['SNO'];

        return $tablejoins_and_filters;
    }

}

?> 