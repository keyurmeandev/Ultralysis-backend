<?php

namespace classes\totalmults;

use filters;
use db;
use config;

class StockDistributionBySku extends config\UlConfig {

    public $customSelectPart;
    public $latestDate;

    public function go($settingVars) {

        if ($_REQUEST['intialRequest'] == "YES")
            $_REQUEST['FromWeek'] = $_REQUEST['ToWeek'];

        $this->initiate($settingVars); //INITIATE COMMON VARIABLES

        $this->latestDate = filters\timeFilter::getLatestMydate($this->settingVars);

        $this->customSelectPart();

        $this->queryPart = $this->getAll(); //USES OWN getAll FUNCTION

        $weeks = filters\timeFilter::$daysTimeframe;

        $this->prepareGridData($weeks); //ADDING TO OUTPUT

        return $this->jsonOutput;
    }

    public function customSelectPart() {
        $storesRanged = $this->settingVars->dataArray['F8']["NAME"];
        $mainStockAvailable = $this->settingVars->dataArray['F19']["NAME"];
        $mainStockHeld = $this->settingVars->dataArray['F20']["NAME"];

        $this->customSelectPart = ",MAX((CASE WHEN " . $this->settingVars->dateField . "='" . $this->latestDate . "' THEN 1 ELSE 0 END)*$storesRanged) AS STORERANGED, " .
                "MAX((CASE WHEN " . $this->settingVars->dateField . "='" . $this->latestDate . "' THEN 1 ELSE 0 END)*$mainStockAvailable) AS MAINSTOCKAVAIL, " .
                "MAX((CASE WHEN " . $this->settingVars->dateField . "='" . $this->latestDate . "' THEN 1 ELSE 0 END)*$mainStockHeld) AS MAINSTOCKHELD ";
    }

    function prepareGridData($weeks) {
        $skuId = $this->settingVars->dataArray['F1']["ID"];
        $sku = $this->settingVars->dataArray['F1']["NAME"];
        $storeStock = $this->settingVars->dataArray['F18']["NAME"];

        $stockDistrubtionBySku = array();

        $query = "SELECT " . $skuId . " AS ID, " .
                $sku . " AS NAME, " .
                "SUM((CASE WHEN  " . filters\timeFilter::$tyWeekRange . " THEN 1 ELSE 0 END) *" . $this->ValueVolume . ") AS VALUEVOLUME, " .
                "SUM((CASE WHEN  " . filters\timeFilter::$tyWeekRange . " THEN 1 ELSE 0 END) *" . $this->settingVars->ProjectValue . ") AS VALUE, " .
                "SUM((CASE WHEN  " . filters\timeFilter::$tyWeekRange . " THEN 1 ELSE 0 END) *" . $this->settingVars->ProjectVolume . ") AS VOLUME, " .
                "MAX((CASE WHEN " . $this->settingVars->dateField . "='" . $this->latestDate . "' THEN 1 ELSE 0 END)*$storeStock) AS STORESTOCK " .
                $this->customSelectPart .
                "FROM  " . $this->settingVars->tablename . $this->queryPart .
                "GROUP BY ID,NAME " .
                "ORDER BY ValueVolume DESC ";
        //echo $query;exit();
        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
        if (isset($result) && !empty($result)) {
            foreach ($result as $key => $data) {
                $checkVol = $data['VOLUME'] / $weeks;
                $unitWeeks = ($weeks > 0) ? $data['VOLUME'] / $weeks : 0;
                $totalStock = ($data['STORESTOCK'] && $data['MAINSTOCKAVAIL'] && $data['MAINSTOCKHELD']) ? $data['STORESTOCK'] + $data['MAINSTOCKAVAIL'] + $data['MAINSTOCKHELD'] : 0;
                $temp = array(
                    'SKUID' => $data['ID'],
                    'SKU' => htmlspecialchars_decode($data['NAME']),
                    'VALUEVOLUME' => (int) number_format($data['VALUEVOLUME'], 0, '.', ''),
                    'VALUE' => (int) number_format($data['VALUE'], 0, '.', ''),
                    'VOLUME' => (int) number_format($data['VOLUME'], 0, '.', ''),
                    'STORESTOCK' => (int) number_format($data['STORESTOCK'], 0, '.', ''),
                    'STORERANGED' => ($data['STORERANGED']) ? (int) number_format($data['STORERANGED'], 0, '.', '') : 0,
                    'MAINSTOCKAVAIL' => ($data['MAINSTOCKAVAIL']) ? (int) number_format($data['MAINSTOCKAVAIL'], 0, '.', '') : 0,
                    'MAINSTOCKHELD' => ($data['MAINSTOCKHELD']) ? (int) number_format($data['MAINSTOCKHELD'], 0, '.', '') : 0,
                    'TOTALSTOCK' => (int) $totalStock,
                    'STRWKSCOVER' => (int) ($checkVol != 0) ? ($data['STORESTOCK'] / $checkVol) : 0,
                    'TOTALWKSCOVER' => (int) ($unitWeeks != 0) ? number_format($totalStock / $unitWeeks, 2, '.', '') : 0,
                    'STOCK_STORE_WK' => (isset($data['STORERANGED']) && $data['STORERANGED'] != 0 && $data['VALUEVOLUME'] != 0) ? number_format(($data['VALUEVOLUME'] / $data['STORERANGED']) / $weeks, 2, '.', '') : 0,
                    'AVGWKVOLUME' => (int) ($checkVol != 0) ? number_format($checkVol, 1, '.', '') : 0,
                    'STOCK_STORE_DAILY_WK' => ($checkVol != 0) ? number_format($data['STORESTOCK'] / ($checkVol), 1, '.', '') : 0
                        //'STOCK_STORE_DAILY_WK' => ($data['VALUEVOLUME'] != 0) ? number_format(($data['STORESTOCK']/$data['VOLUME'])/$weeks, 2, '.', '') : 0
                );
                $stockDistrubtionBySku[] = $temp;
            }
        }
        $this->jsonOutput["gridValue"] = $stockDistrubtionBySku;
    }

}

?>