<?php

namespace classes\retailer;

use datahelper;
use projectsettings;
use filters;
use db;
use config;
use utils;

class RangeReviewAnalysis extends config\UlConfig {
    /*     * ***
     * Default gateway function, should be similar in all DATA CLASS
     * arguments:
     *  $settingVars [project settingsGateway variables]
     * @return $xmlOutput with all data that should be sent to client app
     * *** */

    public $mydate;
    public $category;
    private $fixedQueryPart;

    public function go($settingVars) {
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES
        //$this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll

        $this->settingVars->pageName = (empty($this->settingVars->pageName)) ? $this->settingVars->pageID . '_RangeReviewAnalysis' : $this->settingVars->pageName;
        
        $this->categoryField = $this->settingVars->dataArray["F32"]['tablename'].".".$this->settingVars->dataArray["F32"]['NAME'];
        
        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
            $this->fetchCategoryList();
            $this->fetchMydateList();
            $this->jsonOutput['mydateList'] = $this->mydateList;
            // $this->populateChartData();
        }

        if (isset($_REQUEST["mydate"]))
            $this->mydate = $_REQUEST["mydate"];
        if (isset($_REQUEST["category"]))
            $this->category = $_REQUEST["category"];
        if (isset($_REQUEST["selectedField"]))
            $this->selectedField = $_REQUEST["selectedField"];
        
        
        //$this->accountID = key_exists("ID", $this->settingVars->dataArray[$this->selectedField]) ? $this->settingVars->dataArray[$this->selectedField]['ID'] : $this->settingVars->dataArray[$this->selectedField]['NAME'];
        $this->accountField = $this->settingVars->dataArray[$this->selectedField]['NAME'];

        $this->fixedQueryPart = " AND ".$this->settingVars->maintable.".insertdate='2016-07-27' and ".$this->settingVars->maintable.".SNO<7258 ";

        $action = $_REQUEST["action"];

        switch ($action) {
            case "getSeasonalPromo":
                $this->getSeasonalPromo();
                break;
            case "getAllData":
                $this->zeroSalesGrid();
                break;
            case "getPerformanceChartData":
                $this->getPerformanceChartData();
                break;
            case "getBottomGridData":
                $this->getBottomGridData();
                break;
            case "salseComparisonBySku":
                $this->salseComparisonBySku();
                break;
        }

        return $this->jsonOutput;
    }

    public function fetchMydateList() {
        $mydateField = $this->settingVars->getMydateSelect($this->settingVars->dateField, false);
        $query = "SELECT " . $mydateField . " AS MYDATE FROM " . $this->settingVars->timeHelperTables .
                " GROUP BY MYDATE ORDER BY MYDATE DESC";
        //echo $query;exit;                
        $this->mydateList = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
    }

    public function fetchCategoryList() {
        
        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $this->measureFields[] = $this->categoryField;
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }

        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll

        $query = "SELECT DISTINCT " . $this->categoryField . " as DATA FROM " . $this->settingVars->tablename . $this->queryPart .
                " AND " . $this->categoryField . " IS NOT NULL";

        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
        $this->jsonOutput['categoryList'] = $result;
    }

    public function getPerformanceChartData() {
        $this->settingVars->tableUsedForQuery = array();
        $this->settingVars->useRequiredTablesOnly = true;
        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll
        $mydateField = $this->settingVars->getMydateSelect($this->settingVars->dateField, false);

        $query = "SELECT " . $mydateField . " AS MYDATE " .
                ",SUM(" . $this->ValueVolume . ") AS SALES " .
                " FROM " . $this->settingVars->tablename . " " . $this->queryPart . $this->fixedQueryPart . " AND " . $mydateField . " < '2016-06-20' GROUP BY MYDATE ORDER BY MYDATE DESC LIMIT 20 ";
        //echo $query;
        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);

        $allDataArray = array();
        foreach ($result as $key => $row) {
            $day = date("D", strtotime($row["MYDATE"]));
            $allDataArray[$day][] = $row["SALES"];
        }

        $dataArray = array();
        foreach ($allDataArray as $day => $dataArr) {
            $totalData = 0;
            foreach ($dataArr as $data)
                $totalData += $data;

            $avgData = $totalData / count($dataArr);

            $dataArray[$day]["PRE"] = array(
                "VALUE" => $avgData
            );
        }


        $query = "SELECT " . $mydateField . " AS MYDATE " .
                ",SUM(" . $this->ValueVolume . ") AS SALES " .
                " FROM " . $this->settingVars->tablename . " " . $this->queryPart . $this->fixedQueryPart . " AND " . $mydateField . " >= '2016-06-20' GROUP BY MYDATE ORDER BY MYDATE ASC LIMIT 20 ";
        //echo $query;
        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);

        $allDataArray = array();
        foreach ($result as $key => $row) {
            $day = date("D", strtotime($row["MYDATE"]));
            $allDataArray[$day][] = $row["SALES"];
        }

        foreach ($allDataArray as $day => $dataArr) {
            $totalData = 0;
            foreach ($dataArr as $data)
                $totalData += $data;

            $avgData = $totalData / count($dataArr);

            $dataArray[$day]["POST"] = array(
                "VALUE" => $avgData
            );
        }


        $chartData = array();
        $dayArr = array("Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun");

        foreach ($dayArr as $day) {
            $chartData[] = array(
                "day" => $day,
                "pre_value" => $dataArray[$day]["PRE"]["VALUE"],
                "post_value" => $dataArray[$day]["POST"]["VALUE"],
            );
        }

        $this->jsonOutput["chartData"] = $chartData;
    }

    function getBottomGridData() {
        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $this->settingVars->useRequiredTablesOnly = true;
        $this->measureFields[] = $this->categoryField;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll
        $this->mydateField = $this->settingVars->getMydateSelect($this->settingVars->dateField, false);

        $query = "SELECT " .
                "COUNT(DISTINCT(CASE WHEN " . $this->mydateField . " < '" . $this->mydate . "' AND " . $this->settingVars->ProjectValue . ">0 THEN " . $this->settingVars->maintable . ".SNO END)) AS PRE_STORE_TOTAL " .
                ",COUNT(DISTINCT(CASE WHEN " . $this->mydateField . " >= '" . $this->mydate . "' AND " . $this->settingVars->ProjectValue . ">0 THEN " . $this->settingVars->maintable . ".SNO END)) AS POST_STORE_TOTAL " .
                ",COUNT(DISTINCT(CASE WHEN " . $this->mydateField . " < '" . $this->mydate . "' AND " . $this->settingVars->ProjectValue . ">0 THEN " . $this->settingVars->maintable . ".PIN END)) AS PRE_SKU_TOTAL " .
                ",COUNT(DISTINCT(CASE WHEN " . $this->mydateField . " >= '" . $this->mydate . "' AND " . $this->settingVars->ProjectValue . ">0 THEN " . $this->settingVars->maintable . ".PIN END)) AS POST_SKU_TOTAL " .
                ",COUNT(DISTINCT(CASE WHEN " . $this->mydateField . " < '" . $this->mydate . "' AND " . $this->settingVars->ProjectValue . ">0 THEN " . $this->settingVars->skutable . ".supplier END)) AS PRE_SUPPLIER_TOTAL " .
                ",COUNT(DISTINCT(CASE WHEN " . $this->mydateField . " >= '" . $this->mydate . "' AND " . $this->settingVars->ProjectValue . ">0 THEN " . $this->settingVars->skutable . ".supplier END)) AS POST_SUPPLIER_TOTAL " .
                ",SUM((CASE WHEN " . $this->mydateField . " < '" . $this->mydate . "' THEN 1 ELSE 0 END)*" . $this->settingVars->ProjectValue . ") AS PRE_SALES_TOTAL " .
                ",SUM((CASE WHEN " . $this->mydateField . " >= '" . $this->mydate . "' THEN 1 ELSE 0 END)*" . $this->settingVars->ProjectValue . ") AS POST_SALES_TOTAL " .
                ",SUM((CASE WHEN " . $this->mydateField . " < '" . $this->mydate . "' THEN 1 ELSE 0 END)*" . $this->settingVars->ProjectVolume . ") AS PRE_UNIT_TOTAL " .
                ",SUM((CASE WHEN " . $this->mydateField . " >= '" . $this->mydate . "' THEN 1 ELSE 0 END)*" . $this->settingVars->ProjectVolume . ") AS POST_UNIT_TOTAL " .
                ",COUNT(DISTINCT(CASE WHEN " . $this->mydateField . " < '" . $this->mydate . "' THEN " . $this->mydateField . " END)) AS PRE_MYDATE_TOTAL " .
                ",COUNT(DISTINCT(CASE WHEN " . $this->mydateField . " >= '" . $this->mydate . "' THEN " . $this->mydateField . " END)) AS POST_MYDATE_TOTAL " .
                " FROM " . $this->settingVars->tablename . " " . $this->queryPart . $this->fixedQueryPart . " AND " . $this->categoryField . "='" . $this->category . "'";
        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
        //echo $query; exit;
        if (!empty($result)) {
            $pre_store_scan = $result[0]["PRE_STORE_TOTAL"];
            $post_store_scan = $result[0]["POST_STORE_TOTAL"];
            $store_scan_var = ($post_store_scan - $pre_store_scan);
            $store_scan_varper = $pre_store_scan > 0 ? ((($post_store_scan - $pre_store_scan) / $pre_store_scan) * 100) : 0;

            $pre_sku_count = $result[0]["PRE_SKU_TOTAL"];
            $post_sku_count = $result[0]["POST_SKU_TOTAL"];
            $sku_count_var = ($post_sku_count - $pre_sku_count);
            $sku_count_varper = $pre_sku_count > 0 ? ((($post_sku_count - $pre_sku_count) / $pre_sku_count) * 100) : 0;

            $pre_suplier_count = $result[0]["PRE_SUPPLIER_TOTAL"];
            $post_suplier_count = $result[0]["POST_SUPPLIER_TOTAL"];
            $suplier_count_var = ($post_suplier_count - $pre_suplier_count);
            $suplier_count_varper = $pre_suplier_count > 0 ? ((($post_suplier_count - $pre_suplier_count) / $pre_suplier_count) * 100) : 0;

            $pre_total_sales = $result[0]["PRE_SALES_TOTAL"];
            $post_total_sales = $result[0]["POST_SALES_TOTAL"];
            $total_sales_var = ($post_total_sales - $pre_total_sales);
            $total_sales_varper = $pre_total_sales > 0 ? ((($post_total_sales - $pre_total_sales) / $pre_total_sales) * 100) : 0;

            $pre_total_sales_qty = $result[0]["PRE_UNIT_TOTAL"];
            $post_total_sales_qty = $result[0]["POST_UNIT_TOTAL"];
            $total_sales_qty_var = ($post_total_sales_qty - $pre_total_sales_qty);
            $total_sales_qty_varper = $pre_total_sales_qty > 0 ? ((($post_total_sales_qty - $pre_total_sales_qty) / $pre_total_sales_qty) * 100) : 0;

            $pre_mydate_count = $result[0]["PRE_MYDATE_TOTAL"];
            $post_mydate_count = $result[0]["POST_MYDATE_TOTAL"];
            $mydate_count_var = ($post_mydate_count - $pre_mydate_count);
            $mydate_count_varper = $pre_mydate_count > 0 ? ((($post_mydate_count - $pre_mydate_count) / $pre_mydate_count) * 100) : 0;
        }

        $pre_sales_per_sku = ($pre_sku_count > 0) ? $pre_total_sales / $pre_sku_count : 0;
        $post_sales_per_sku = ($post_sku_count > 0) ? $post_total_sales / $post_sku_count : 0;
        $sales_per_sku_var = ($post_sales_per_sku - $pre_sales_per_sku);
        $sales_per_sku_varper = $pre_sales_per_sku > 0 ? ((($post_sales_per_sku - $pre_sales_per_sku) / $pre_sales_per_sku) * 100) : 0;


        $pre_avg_sales = ($pre_mydate_count > 0) ? $pre_total_sales / $pre_mydate_count : 0;
        $post_avg_sales = ($post_mydate_count > 0) ? $post_total_sales / $post_mydate_count : 0;
        $avg_sales_var = ($post_avg_sales - $pre_avg_sales);
        $avg_sales_varper = $pre_avg_sales > 0 ? ((($post_avg_sales - $pre_avg_sales) / $pre_avg_sales) * 100) : 0;

        $pre_avg_sales_qty = ($pre_mydate_count > 0) ? $pre_total_sales_qty / $pre_mydate_count : 0;
        $post_avg_sales_qty = ($post_mydate_count > 0) ? $post_total_sales_qty / $post_mydate_count : 0;
        $avg_sales_qty_var = ($post_avg_sales_qty - $pre_avg_sales_qty);
        $avg_sales_qty_varper = $pre_avg_sales_qty > 0 ? ((($post_avg_sales_qty - $pre_avg_sales_qty) / $pre_avg_sales_qty) * 100) : 0;

        $gridData = array(
            array(
                "name" => "STORE SCANNED",
                "pre" => $pre_store_scan,
                "post" => $post_store_scan,
                "variance" => $store_scan_var,
                "varper" => $store_scan_varper,
            ),
            array(
                "name" => "SKU COUNT",
                "pre" => $pre_sku_count,
                "post" => $post_sku_count,
                "variance" => $sku_count_var,
                "varper" => $sku_count_varper,
            ),
            array(
                "name" => "SALES TOTAL",
                "pre" => $pre_total_sales,
                "post" => $post_total_sales,
                "variance" => $total_sales_var,
                "varper" => $total_sales_varper,
            ),
            array(
                "name" => "SALES PER SKU",
                "pre" => $pre_sales_per_sku,
                "post" => $post_sales_per_sku,
                "variance" => $sales_per_sku_var,
                "varper" => $sales_per_sku_varper,
            ),
            array(
                "name" => "AVG SALES",
                "pre" => $pre_avg_sales,
                "post" => $post_avg_sales,
                "variance" => $avg_sales_var,
                "varper" => $avg_sales_varper,
            ),
            array(
                "name" => "AVG UNIT SALES",
                "pre" => $pre_avg_sales_qty,
                "post" => $post_avg_sales_qty,
                "variance" => $avg_sales_qty_var,
                "varper" => $avg_sales_qty_varper,
            ),
            array(
                "name" => "SUPPLIER",
                "pre" => $pre_suplier_count,
                "post" => $post_suplier_count,
                "variance" => $suplier_count_var,
                "varper" => $suplier_count_varper,
            ),
        );

        $this->jsonOutput['bottomGridData'] = $gridData;
    }

    function salseComparisonBySku() {
        
        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $this->settingVars->useRequiredTablesOnly = true;
        $this->measureFields[] = $this->categoryField;
        $this->measureFields[] = $this->accountField;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll
        $this->mydateField = $this->settingVars->getMydateSelect($this->settingVars->dateField, false);
        
        
        

        $query = "SELECT " . $this->accountField . " as SKU " .
//                "," . $this->settingVars->maintable . ".PIN as SKUID " .
                ",COUNT(DISTINCT(CASE WHEN " . $this->mydateField . " < '" . $this->mydate . "' AND " . $this->settingVars->ProjectVolume . ">0 THEN " . $this->settingVars->maintable . ".SNO END)) AS PRE_DIST " .
                ",COUNT(DISTINCT(CASE WHEN " . $this->mydateField . " >= '" . $this->mydate . "' AND " . $this->settingVars->ProjectVolume . ">0 THEN " . $this->settingVars->maintable . ".SNO END)) AS POST_DIST " .
                ",SUM((CASE WHEN " . $this->mydateField . " < '" . $this->mydate . "' THEN 1 ELSE 0 END)*" . $this->settingVars->ProjectValue . ") AS PRE_SALES " .
                ",SUM((CASE WHEN " . $this->mydateField . " >= '" . $this->mydate . "' THEN 1 ELSE 0 END)*" . $this->settingVars->ProjectValue . ") AS POST_SALES " .
                ",SUM((CASE WHEN " . $this->mydateField . " < '" . $this->mydate . "' THEN 1 ELSE 0 END)*" . $this->settingVars->ProjectVolume . ") AS PRE_QTY " .
                ",SUM((CASE WHEN " . $this->mydateField . " >= '" . $this->mydate . "' THEN 1 ELSE 0 END)*" . $this->settingVars->ProjectVolume . ") AS POST_QTY " .
                " FROM " . $this->settingVars->tablename . " " . $this->queryPart . $this->fixedQueryPart . " AND ".$this->settingVars->recordType."='SAL' AND " . 
                $this->categoryField . "='" . $this->category . "' GROUP BY SKU";
        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
        // echo $query; exit;
        $gridData = array();
        if (!empty($result)) {
            foreach ($result as $key => $row) {
                $gridData[$key] = $row;
                $gridData[$key]["PRE_SPPD"] = $row["PRE_DIST"] > 0 ? $row["PRE_SALES"] / $row["PRE_DIST"] : 0;
                $gridData[$key]["POST_SPPD"] = $row["POST_DIST"] > 0 ? $row["POST_SALES"] / $row["POST_DIST"] : 0;
                $gridData[$key]["PRE_PRICE"] = $row["PRE_QTY"] > 0 ? $row["PRE_SALES"] / $row["PRE_QTY"] : 0;
                $gridData[$key]["POST_PRICE"] = $row["POST_QTY"] > 0 ? $row["POST_SALES"] / $row["POST_QTY"] : 0;
            }
        }


        if (!empty($gridData))
            foreach ($gridData as $key => $data) {

                $var = $data["POST_SALES"] - $data["PRE_SALES"];
                $varper = $data["PRE_SALES"] > 0 ? ((( $data["POST_SALES"] - $data["PRE_SALES"] ) / $data["PRE_SALES"] ) * 100 ) : 0;
                $gridData[$key]["SALES_VARIANCE"] = $var;
                $gridData[$key]["SALES_VARPER"] = $varper;

                $var = $data["POST_DIST"] - $data["PRE_DIST"];
                $varper = $data["PRE_DIST"] > 0 ? ((( $data["POST_DIST"] - $data["PRE_DIST"] ) / $data["PRE_DIST"] ) * 100 ) : 0;
                $gridData[$key]["DIST_VARIANCE"] = $var;
                $gridData[$key]["DIST_VARPER"] = $varper;

                $var = $data["POST_SPPD"] - $data["PRE_SPPD"];
                $varper = $data["PRE_SPPD"] > 0 ? ((( $data["POST_SPPD"] - $data["PRE_SPPD"] ) / $data["PRE_SPPD"] ) * 100 ) : 0;
                $gridData[$key]["SPPD_VARIANCE"] = $var;
                $gridData[$key]["SPPD_VARPER"] = $varper;

                $var = $data["POST_PRICE"] - $data["PRE_PRICE"];
                $varper = $data["PRE_PRICE"] > 0 ? ((( $data["POST_PRICE"] - $data["PRE_PRICE"] ) / $data["PRE_PRICE"] ) * 100 ) : 0;
                $gridData[$key]["PRICE_VARIANCE"] = $var;
                $gridData[$key]["PRICE_VARPER"] = $varper;
            }

        $this->jsonOutput["salseComparisonBySku"] = $gridData;
    }

    function getSeasonalPromo() {
        $this->settingVars->tableUsedForQuery = array();
        $this->settingVars->useRequiredTablesOnly = true;
        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll

        $query = "SELECT " .
                "SUM((CASE WHEN " . $this->settingVars->recordType . " = 'DCS' THEN 1 ELSE 0 END)*" . $this->settingVars->ProjectVolume . ") AS SUM_DCS " .
                ",SUM((CASE WHEN " . $this->settingVars->recordType . " = 'STK' THEN 1 ELSE 0 END)*" . $this->settingVars->ProjectVolume . ") AS SUM_STK " .
                "FROM " . $this->settingVars->tablename . " " . $this->queryPart .
                "AND " . $this->settingVars->dateperiod . " = '" . filters\timeFilter::getLatestMydate($this->settingVars) . "'";
        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);

        $sum = 0;
        if (!empty($result) && is_array($result)) {
            $sum = $result[0]['SUM_DCS'] + $result[0]['SUM_STK'];
        }

        $this->jsonOutput['seasonalPromo'] = array("DAYS_TO_LAUNCH" => "10th Jun 2016", "DAYS_TO_EVENT" => "19th Jun 2016", "DAYS_TO_STORE_LOADING" => "7th Jun 2016", "TOTAL_STOCK_HOLDING" => $sum);
    }

}

?>