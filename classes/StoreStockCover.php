<?php

namespace classes;

use config;
use filters;
use db;

class StoreStockCover extends config\UlConfig {

    private $skuID;
    private $skuName;
    private $storeID;
    private $salesArrayBy_SkuAndStore;
    private $qtyArr;
    private $totalWeek;
    private $storeName;
    private $pageName;

    /*     * ***
     * Default gateway function, should be similar in all DATA CLASS
     * arguments:
     *  $settingVars [project settingsGateway variables]
     * @return $jsonOutput with all data that should be sent to client app
     * *** */

    public function go($settingVars) {
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES
        $this->pageName = $_REQUEST["pageName"]; // Identify the page name
        $this->settingVars->pageName = $this->pageName; // set pageName in settingVars
        $this->queryPart = $this->getAll(); //THIS CLASS USES IT'S OWN getAll Function
        //SET REQUIRED FIELD FOR QUERY SENT FORM CLIENT APPLICATION
        $this->skuID = $this->settingVars->dataArray[$this->settingVars->pageArray[$this->pageName]["SKU_ACCOUNT"]]["ID"];
        $this->skuName = $this->settingVars->dataArray[$this->settingVars->pageArray[$this->pageName]["SKU_ACCOUNT"]]["NAME"];
        $this->storeID = $this->settingVars->dataArray[$this->settingVars->pageArray[$this->pageName]["STORE_ACCOUNT"]]["ID"];
        $this->storeName = $this->settingVars->dataArray[$this->settingVars->pageArray[$this->pageName]["STORE_ACCOUNT"]]["NAME"];

        //STORE STOCK COVER PAGE USES A CUSTOM TIME SELECTION RANGE
        filters\timeFilter::collectLatestYearSSCover($settingVars);

        // SET SALES , UNITS AND DD STORATE ARRAY $salesArrayBy_SkuAndStore GROUP BY EACH SKU AND STORE
        $this->valueFunc();

        //CALCUALTE TOTAL WEEK OF CURRENT TIME SELECTION
        $this->FindTotalWeek();

        $this->prepareLineChartData();
        $this->QtySum();
        $this->prepareGridData();

        return $this->jsonOutput;
    }

    private function valueFunc() {
        $this->salesArrayBy_SkuAndStore = array();
        $qpart = " AND " . $this->settingVars->maintable . ".period IN (" . implode(",", filters\timeFilter::getPeriodWithinRange(1, 1, $this->settingVars)) . ") ";
        $query = "SELECT " . $this->skuID . " AS TPNB " .
                "," . $this->storeID . " AS SNO " .
                "," . $this->settingVars->ProjectVolume . " AS UNITS " .
                "," . $this->settingVars->ProjectValue . " AS VALUE " .
                ",SUM(dd_cases* WHPK_Qty) AS DD " .
                "FROM " . $this->settingVars->tablename . $this->queryPart . $qpart . " " .
                "GROUP BY 1,2,3,4 LIMIT 1000 "; //OFFSET 33554432
		//echo $query;exit;
        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
        foreach ($result as $key => $data) {
            $sin = $data['TPNB'];
            $sno = $data['SNO'];
            $this->salesArrayBy_SkuAndStore[$sin][$sno]['UNITS'] = $data['UNITS'];
            $this->salesArrayBy_SkuAndStore[$sin][$sno]['VALUE'] = $data['VALUE'];
            $this->salesArrayBy_SkuAndStore[$sin][$sno]['DD'] = $data['DD'];
        }
    }

    private function prepareLineChartData() {
        $query = "SELECT MAX(" . $this->settingVars->maintable . "." . $this->settingVars->period . ") AS PERIOD" .
                ",SUM( (CASE WHEN " . $this->settingVars->yearperiod . "=" . filters\timeFilter::$ToYear . " THEN 1 ELSE 0 END) * " . $this->settingVars->ProjectVolume . " ) AS UNITS" .
                ",SUM( (CASE WHEN " . $this->settingVars->yearperiod . "=" . filters\timeFilter::$ToYear . " THEN 1 ELSE 0 END) * GSQ) AS SHIP_UNITS " .
                "FROM " . $this->settingVars->tablename . $this->queryPart . " " .
                "GROUP BY " . $this->settingVars->weekperiod . " " .
                "ORDER BY " . $this->settingVars->weekperiod . " ASC";
        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
        foreach ($result as $key => $data) {
            if ($data['UNITS'] <> 0 || $data['SHIP_UNITS'] <> 0) {
                $temp = array(
                    'WEEK' => htmlspecialchars($data['PERIOD'])
                    , 'QTY' => number_format($data['UNITS'], 0, '.', '')
                    , 'SHIPQTY' => number_format($data['SHIP_UNITS'], 0, '.', '')
                );
                //$this->jsonOutput[$tagName]['LineChart'] = $temp;
                $this->jsonOutput['LineChart'] = $temp;
            }
        }
    }

    private function QtySum() {
        $this->qtyArr = array();
        if ($_REQUEST["splitSt"] == 'true') {
            $query = "SELECT " . $this->skuID . " AS TPNB" .
                    "," . $this->storeID . " AS SNO" .
                    ",SUM( (CASE WHEN " . $this->settingVars->yearperiod . "=" . filters\timeFilter::$ToYear . " THEN 1 ELSE 0 END) * " . $this->settingVars->ProjectVolume . ") AS MCOST " .
                    "FROM " . $this->settingVars->tablename . $this->queryPart . " " .
                    "GROUP BY 1,2";
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            foreach ($result as $key => $data) {
                $this->qtyArr[$data['TPNB']][$data['SNO']] = $data['MCOST'];
            }
        } else {
            $query = "SELECT " . $this->skuID . " AS TPNB" .
                    ", SUM( (CASE WHEN " . $this->settingVars->yearperiod . "=" . filters\timeFilter::$ToYear . "' THEN 1 ELSE 0 END) * " . $this->settingVars->ProjectVolume . " ) AS MCOST " .
                    "FROM " . $this->settingVars->tablename . $this->queryPart . " " .
                    "GROUP BY 1";
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, ResultTypes::$TYPE_OBJECT);
            foreach ($result as $key => $data) {
                $this->qtyArr[$data['TPNB']] = $data['MCOST'];
            }
        }
    }

    private function prepareGridData() {
        if ($_REQUEST["TSI"] == 2)
            $querypart = $this->queryPart . " AND VSI=1 ";
        else if ($_REQUEST["TSI"] == 0)
            $querypart = $this->queryPart . " AND VSI=0 ";

        if ($_REQUEST["ONHAND"] == 2)
            $havingPart = " OHQ>0 ";
        else if ($_REQUEST["ONHAND"] == 0)
            $havingPart = " OHQ<=0 ";
		
		$gridSetup = array();
		
        if ($_REQUEST["splitSt"] == 'true') {
            $query = "SELECT " . $this->skuID . " AS TPNB" .
                    "," . $this->storeID . " AS SNO" .
                    "," . $this->skuName . " AS SKU " .
                    "," . $this->storeName . " AS STORE" .
                    ",SUM(OHQ) AS OHQ" .
                    ",SUM(StoreTrans) AS STORE_TRANS" .
                    ",SUM(StoreWhs) AS STORE_WHS" .
                    ",SUM(StoreOrder) AS STORE_ORD" .
                    ",SUM(" . $this->settingVars->ProjectVolume . ")" .
                    ",max(TSI) AS TSI" .
                    ",max(OHQ) AS OHQ_MAX," .
                    "SUM(StoreTrans+StoreWhs+StoreOrder) AS TOTAL " .
                    "FROM " . $this->settingVars->tablename . $this->queryPart . $querypart . " " .
                    "GROUP BY 1,2,3,4 " .
                    "HAVING $havingPart";
			//echo $query;exit;
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_ARRAY);
            foreach ($result as $key => $data) {
                $sin = $data[0];
                $sno = $data[1];

                $lwUnits = $this->salesArrayBy_SkuAndStore[$sin][$sno]['UNITS'];
                $sales = $this->salesArrayBy_SkuAndStore[$sin][$sno]['VALUE'];
                $dd = $this->salesArrayBy_SkuAndStore[$sin][$sno]['DD'];
                $dd = number_format($dd, 1, '.', '');

                $addToOutput = "false";
                if ($sales >= $_REQUEST["LWSALES"]) {
                    if ($_REQUEST["ONORDER"] == 0 && $data[11] < 1)
                        $addToOutput = "true";
                    elseif ($_REQUEST["ONORDER"] == 2 && $data[11] > 1)
                        $addToOutput = "true";
                    elseif ($_REQUEST["ONORDER"] == 1)
                        $addToOutput = "true";

                    if ($addToOutput == "true") {
                        $temp = array(
                            'SID' => htmlspecialchars($data['PERIOD'])
                            , 'SNO' => htmlspecialchars_decode($data[1])
                            , 'TPNB' => htmlspecialchars_decode($data[0])
                            , 'SKU' => htmlspecialchars_decode($data[2])
                            , 'SNAME' => htmlspecialchars_decode($data[3])
                            , 'Hand' => $data[4]
                            , 'Trans' => $data[5]
                            , 'Whs' => $data[6]
                            , 'Order' => $data[7]
                            , 'LWUNITS' => number_format($lwUnits, 0)
                            , 'LWVAL' => number_format($sales, 2, '.', '')
                            , 'DD' => $dd
                        );
                        $this->jsonOutput['Skugrid'][] = $temp;
                        $this->jsonOutput['gridSetup'] = array_keys($temp);
                    }
                }
            }
        } else {
            $query = "SELECT " . $this->skuID . " AS TPNB" .
                    "," . $this->skuName . " AS SKU" .
                    ",SUM(OHQ) AS OHQ" .
                    ",SUM(StoreTrans) AS STORE_TRANS" .
                    ",SUM(StoreWhs) AS STORE_WHS" .
                    ",SUM(store_order) AS STORE_ORD" .
                    ",SUM(" . $this->settingVars->ProjectVolume . ")" .
                    ",MAX(TSI) AS TSI" .
                    ",MAX(OHQ) AS OHQ_MAX" .
                    "FROM " . $this->settingVars->tablename . $querypart . " " .
                    "GROUP BY 1,2";
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_ARRAY);
            foreach ($result as $key => $data) {
                $avgQty = ($this->qtyArr[$data[0]] / $this->totalWeek);
                $advSales = number_format($avgQty / 7, 2, '.', '');

                if ($advSales > 0) {
                    $wc = number_format(($data[2] / $advSales), 1, '.', '');
                    $wcio = number_format((($data[2] + $data[3] + $data[4] + $data[5]) / $advSales), 2);
                } else {
                    $wc = 0;
                    $wcio = 0;
                }

                $temp = array(
                    'SID' => htmlspecialchars_decode($data[0])
                    , 'SKU' => htmlspecialchars_decode($data[1])
                    , 'Hand' => $data[2]
                    , 'Trans' => $data[3]
                    , 'Whs' => $data[4]
                    , 'Order' => $data[5]
                    , 'AvgQty' =>  number_format($avgQty, 2, '.', '')
                    , 'AVDSALES' =>$advSales
                    , 'WC' => $wc
                    , 'WCIO' => $wcio
                    , 'TSI' => $data[7]
                    , 'ONHAND' => $data[8]
                );
                $this->jsonOutput['Skugrid'][] = $temp;
                $this->jsonOutput['gridSetup'] = array_keys($temp);
            }
        }
    }

    private function FindTotalWeek() {
        if (filters\timeFilter::$ToYear != filters\timeFilter::$FromYear)
            $this->totalWeek = ((52 - filters\timeFilter::$FromWeek) + filters\timeFilter::$ToWeek);
        else
            $this->totalWeek = ( filters\timeFilter::$ToWeek - filters\timeFilter::$FromWeek) + 1;

        $this->totalWeek;
    }

    /*     * *** ORERRIDING PARENT CLASS'S getAll Function
     * COLLECTS TABLE JOINING STRING FROM PROJECT SETTINGS
     * ALSO APPLYS FILTER CONDITIONS TO THE STRING
     * AND RETURNS $tablejoins_and_filters
     * *** */

    public function getAll() {        
		$tablejoins_and_filters = parent::getAll();
        $tablejoins_and_filters .= " AND openDate < insertDate";

        /* if (isset($_REQUEST["FS"]) && $_REQUEST["FS"] != '') {
            $tablejoins_and_filters .= filters\productAndMarketFilter::include_product_and_market_filters($this->queryVars, $this->settingVars);
        } */

        if ($_REQUEST["STORE"] != "")
            $tablejoins_and_filters .= " AND " . $storeID . "=" . $_REQUEST['STORE'];
        if ($_REQUEST["SKU"] != "")
            $tablejoins_and_filters .= " AND " . $skuID . " IN(" . $_REQUEST['SKU'] . ") ";
        if ($_REQUEST["PRODUCT"] != "")
            $tablejoins_and_filters .= " AND " . $skuName . " IN(" . $_REQUEST['PRODUCT'] . ") ";


        return $tablejoins_and_filters;
    }

}

