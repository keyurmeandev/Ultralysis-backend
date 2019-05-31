<?php

namespace classes\tsd;

use datahelper;
use projectsettings;
use filters;
use utils;
use db;
use config;

class ExecutiveSummary extends \classes\relayplus\SummaryPage {
    /*     * ***
     * Default gateway function, should be similar in all DATA CLASS
     * arguments:
     *  $settingVars [project settingsGateway variables]
     * @return $xmlOutput with all data that should be sent to client app
     * *** */

    public $pageName;

    public function go($settingVars) {
        $this->pageName = $settingVars->pageName = (empty($settingVars->pageName)) ? 'SummaryPage' : $settingVars->pageName; // set pageName in settingVars
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES
        $this->redisCache = new \utils\RedisCache($this->queryVars);
        $this->checkConfiguration();

        $this->dcViewState = ((!isset($this->settingVars->tesco_depot_daily) || $this->settingVars->tesco_depot_daily == '') ||
                (isset($_REQUEST["dcViewState"]) && $_REQUEST["dcViewState"] == '0')) ? false : true;

        if ($this->settingVars->isDynamicPage) {
            $this->skuField     = $this->getPageConfiguration('sku_field', $this->settingVars->pageID)[0];
            $this->storeField   = $this->getPageConfiguration('store_field', $this->settingVars->pageID)[0];

            $this->buildDataArray(array($this->skuField, $this->storeField));
            $this->buildPageArray();
        } else {
            //$this->bannerAccount = (isset($this->settingVars->dataArray['F11']) && isset($this->settingVars->dataArray['F11']['ID'])) ? $this->settingVars->dataArray['F11']['ID'] : $this->settingVars->dataArray['F11']['NAME'];
            //$this->provinceAccount = (isset($this->settingVars->dataArray['F15']) && isset($this->settingVars->dataArray['F15']['ID'])) ? $this->settingVars->dataArray['F15']['ID'] : $this->settingVars->dataArray['F15']['NAME'];
        }

        $action = $_REQUEST["action"];

        switch ($action) {
            case "gridData":
                $this->gridData();
                break;
            case "dailySales":
                $this->dailySales();
                break;
            case "dailySalesVsLw":
                $this->dailySalesVsLw();
                break;
            case "changeDailySales":
                $this->salesVsStockFilter();
                break;
        }
        return $this->jsonOutput;
    }

    function gridData() {
        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $this->measureFields[] = $this->pinIdField;
        $this->measureFields[] = $this->pinNameField;

        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->queryPart = $this->getAll();

        $depotStockByTpnb = array();

        if ($this->dcViewState) {
            $getLastDepotDate = filters\timeFilter::getLastNDaysDateFromDepotDaily($this->settingVars);
            $query = "SELECT " . $this->pinIdField . " AS TPNB" .
                    ",SUM((CASE WHEN " . $this->settingVars->DatePeriod . "=" . $getLastDepotDate . " THEN 1 ELSE 0 END)*stock) AS STOCK " .
                    " FROM " . $this->settingVars->depotTableName . $this->settingVars->depotLink . " GROUP BY TPNB";
            //echo $query;
            $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
            if ($redisOutput === false) {
                $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
                $this->redisCache->setDataForHash($result);
            } else {
                $result = $redisOutput;
            }

            if (is_array($result) && !empty($result)) {
                foreach ($result as $key => $value) {
                    $depotStockByTpnb[$value['TPNB']] = $value['STOCK'];
                }
            }
        }

        $query = "SELECT " . $this->pinIdField . " AS TPNB, "
                . $this->pinNameField . " AS SKU, "
                //. "COUNT( DISTINCT (CASE WHEN " . $this->settingVars->DatePeriod . "='" . filters\timeFilter::$tyDaysRange[0] . "' AND stock > 0 THEN ".$this->settingVars->maintable.".SNO END)) AS STOCKED_STORES "
                . "COUNT( DISTINCT (CASE WHEN stock > 0 THEN ".$this->settingVars->maintable.".SNO END)) AS STOCKED_STORES "
                . ", COUNT( DISTINCT (CASE WHEN " . $this->settingVars->ProjectVolume . " > 0 THEN ".$this->settingVars->maintable.".SNO END)) AS SELLING_STORES "
                . ",SUM(" . $this->settingVars->ProjectValue . ") AS SALES "
                . ",SUM(" . $this->settingVars->ProjectVolume . ") AS QTY "
                . ",SUM((CASE WHEN " . $this->settingVars->DatePeriod . "='" . filters\timeFilter::$tyDaysRange[0] . "' THEN 1 ELSE 0 END)*stock) AS STOCK "
                . ",SUM((CASE WHEN " . $this->settingVars->DatePeriod . "='" . filters\timeFilter::$tyDaysRange[0] . "' THEN 1 ELSE 0 END)*stockTra) AS TRANSIT "
                . " FROM " . $this->settingVars->tablename
                . $this->queryPart
                . " AND " . $this->settingVars->dateField . " IN ('" . implode("','", filters\timeFilter::$tyDaysRange) . "') "
                . " GROUP BY TPNB, SKU"
                . " ORDER BY SALES DESC";

        //echo $query;exit;
        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }

        $gridData = array();
        if (is_array($result) && !empty($result)) {
            foreach ($result as $value) {
                $tempGridData = array();
                $dcStock = $daysCover = $daysCoverDepo = 0;
                $averageDailyQty = (filters\timeFilter::$daysTimeframe > 0) ? $value['QTY'] / filters\timeFilter::$daysTimeframe : 0;
                $daysCover = ($value['QTY'] > 0 || $averageDailyQty > 0) ? (($value['STOCK'] + $value['TRANSIT']) / $averageDailyQty) : 0;
                
                if ($this->dcViewState) {
                    $dcStock = $depotStockByTpnb[$value['TPNB']];
                    $reqQty = (filters\timeFilter::$daysTimeframe > 0) ? $value['QTY'] / filters\timeFilter::$daysTimeframe : 0;
                    $daysCoverDepo = ($reqQty > 0) ? (($value['STOCK'] + $depotStockByTpnb[$value['TPNB']]) / ($reqQty)) : 0;
                }

                $tempGridData['SKUID'] = $value['TPNB'];
                $tempGridData['SKU'] = $value['SKU'] . " { " . $value['TPNB'] . " }";
                $tempGridData['SALES'] = number_format($value['SALES'], 0, '.', ',');
                $tempGridData['QTY'] = number_format($value['QTY'], 0, '.', ',');
                $tempGridData['STOCK'] = number_format($value['STOCK'], 0, '.', ',');
                $tempGridData['DC_STOCK'] = $dcStock;
                $tempGridData['DC'] = number_format($daysCover, 1, '.', '');
                $tempGridData['DCI'] = number_format($daysCoverDepo, 1, '.', '');
                $tempGridData['STOCKED_STORES'] = number_format($value['STOCKED_STORES'], 0, '.', '');
                $tempGridData['SELLING_STORES'] = number_format($value['SELLING_STORES'], 0, '.', '');
                $tempGridData['SELLING_PER'] = number_format(($value['STOCKED_STORES'] > 0 ? ($value['SELLING_STORES']/$value['STOCKED_STORES'])*100 : 0 ), 1, '.', '');
                $tempGridData['TOTAL'] = $value['STOCK'] + $dcStock;

                $gridData[] = $tempGridData;
            }
        }
        $this->jsonOutput['gridData'] = $gridData;
    }

    function salesVsStockFilter() {

        $sno = $this->settingVars->dataArray['F4']['ID'];
        $sname = $this->settingVars->dataArray['F4']['NAME'];

        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $this->measureFields[] = $this->storeIdField;
        $this->measureFields[] = $this->storeNameField;

        $this->settingVars->useRequiredTablesOnly = true;
        
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->queryPart = $this->getAll();

        $query = "SELECT ".$this->storeIdField." AS SNO, ".
                $this->storeNameField." AS SNAME, " .
                "SUM(" . $this->settingVars->ProjectVolume . ") AS SALES" .
                ",SUM((CASE WHEN " . $this->settingVars->DatePeriod . "='" . filters\timeFilter::$tyDaysRange[0] . "' THEN 1 ELSE 0 END)*stock) AS STOCK" .
                " FROM " . $this->settingVars->tablename . " " . $this->queryPart .
                " GROUP BY SNO, SNAME";
        //echo $query;exit;
        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }

        $bindingData = array();

        $i = 0;
        if (is_array($result) && !empty($result)) {
            foreach ($result as $key => $value) {
                if (strlen($key) <= 2) {
                    $bindingData[$i]['SNO'] = $value['SNO'];
                    $bindingData[$i]['SNAME'] = $value['SNAME'];
                    $bindingData[$i]['SALES'] = (int) $value['SALES'];
                    $bindingData[$i]['STOCK'] = (int) $value['STOCK'];
                    $i++;
                }
            }
        }
        $this->jsonOutput['salesVsStockDataFilter'] = $bindingData;
    }

    function dailySales() {

        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $this->measureFields[] = $this->settingVars->clusterID;

        $this->settingVars->useRequiredTablesOnly = true;
        
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->queryPart = $this->getAll();

        $query = "SELECT  ".$this->settingVars->clusterID." AS CLUSTER "
                . ",COUNT(DISTINCT(CASE WHEN " . $this->settingVars->ProjectVolume . ">0 THEN " . $this->settingVars->storetable . '.SNO' . " END)) AS STORES_COUNT "
                . ",SUM(" . $this->settingVars->ProjectVolume . ") AS TOTAL_UNITS "
                . ",SUM((CASE WHEN " . $this->settingVars->DatePeriod . "='" . filters\timeFilter::$tyDaysRange[0] . "' THEN 1 ELSE 0 END)*" . $this->settingVars->maintable . ".stock) AS STOCK "
                . " FROM " . $this->settingVars->tablename
                . $this->queryPart
                . " AND " . $this->settingVars->dateField . " IN('" . implode("','", filters\timeFilter::$tyDaysRange) . "') "
                . " GROUP BY CLUSTER"
                . " ORDER BY CLUSTER ASC";

        //echo $query; exit;    
        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }
        $gridData = array();
        $chartData = array();
        if (is_array($result) && !empty($result)) {
            foreach ($result as $value) {
                $tempGridData = array();
                $tempChartData = array();

                $ups = ($value['TOTAL_UNITS'] == 0 || $value['STORES_COUNT'] == 0) ? 0 : (($value['TOTAL_UNITS'] / $value['STORES_COUNT']) / 7);
                $getTU = $value['TOTAL_UNITS'] / 14;

                $tempGridData['CLUSTER'] = $value['CLUSTER'];
                $tempGridData['STORES'] = $value['STORES_COUNT'];
                $tempGridData['UPS'] = number_format($ups, 1, '.', '');
                $tempGridData['STOCK'] = number_format($value['STOCK'], 0, '.', ',');
                $tempGridData['DC'] = number_format($value['STOCK'] == 0 || $getTU == 0 ? 0 : $value['STOCK'] / $getTU, 1, '.', '');

                $tempChartData['name'] = $value['CLUSTER'];
                $tempChartData['SALES'] = (int)$value['TOTAL_UNITS'];
                $tempChartData['STOCK'] = (int)$value['STOCK'];

                $gridData[] = $tempGridData;
                $chartData[] = $tempChartData;
            }
        }
        $this->jsonOutput['dailySales'] = $gridData;
        $this->jsonOutput['salesVsStocData'] = $chartData;
    }

    function dailySalesVsLw() {
        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $this->settingVars->useRequiredTablesOnly = true;
        $this->queryPart = $this->getAll();
        $query = "SELECT  ".$this->settingVars->dateField." AS MYDATE "
                . ",SUM(" . $this->settingVars->ProjectVolume . ") AS UNIT_SALES "
                . " FROM " . $this->settingVars->tablename
                . $this->queryPart
                . " AND " . $this->settingVars->dateField . " IN('" . implode("','", filters\timeFilter::$ty14DaysRange) . "') "
                . " GROUP BY MYDATE"
                . " ORDER BY MYDATE DESC";

        //echo $query; exit;
        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }
        $tyResult = array_slice($result, 0, 7);
        $lyResult = array_slice($result, 7);
        
        if (is_array($tyResult) && !empty($tyResult)) {
            foreach ($tyResult as $tyKey => $tyData) {
                $tempChartData = array();
                $tempChartData['TYACCOUNT'] = date('D', strtotime($tyData['MYDATE']));
                $tempChartData['DAYNUM'] = $tyKey;
                $tempChartData['LYACCOUNT'] = (isset($lyResult[$tyKey])) ? date('D', strtotime($lyResult[$tyKey]['MYDATE'])) : '';
                $tempChartData['SALES'] = (int)$tyData['UNIT_SALES'];
                $tempChartData['LYSALES'] = (isset($lyResult[$tyKey])) ? (int)$lyResult[$tyKey]['UNIT_SALES'] : 0;

                $chartData[] = $tempChartData;
            }
        }
        $this->jsonOutput['dailySalesVsLw'] = $chartData;
    }

    public function buildDataArray($fields, $isCsvColumn = true) {
        if (empty($fields))
            return false;

        $configurationCheck = new config\ConfigurationCheck($this->settingVars, $this->queryVars);
        $configurationCheck->buildDataArray($fields);
        $this->dbColumnsArray = $configurationCheck->dbColumnsArray;
        $this->displayCsvNameArray = $configurationCheck->displayCsvNameArray;
        $this->displayDbColumnArray = $configurationCheck->displayDbColumnArray;
        return;
    }

    public function buildPageArray() {

        $fetchConfig = false;
        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
            $fetchConfig = true;
            $this->jsonOutput['pageConfig'] = array(
                'enabledFilters' => $this->getPageConfiguration('filter_settings', $this->settingVars->pageID)
            );
        }

        $skuFieldPart = explode("#", $this->skuField);
        $skuField = strtoupper($this->dbColumnsArray[$skuFieldPart[0]]);
        $skuField = (count($skuFieldPart) > 1) ? strtoupper($skuField . "_" . $this->dbColumnsArray[$skuFieldPart[1]]) : $skuField;

        $this->pinIdField = (isset($this->settingVars->dataArray[$skuField]) && isset($this->settingVars->dataArray[$skuField]['ID'])) ? 
            $this->settingVars->dataArray[$skuField]['ID'] : $this->settingVars->dataArray[$skuField]['NAME'];
        $this->pinNameField = $this->settingVars->dataArray[$skuField]['NAME'];

        $storeFieldPart = explode("#", $this->storeField);
        $storeField = strtoupper($this->dbColumnsArray[$storeFieldPart[0]]);
        $storeField = (count($storeFieldPart) > 1) ? strtoupper($storeField . "_" . $this->dbColumnsArray[$storeFieldPart[1]]) : $storeField;

        $this->storeIdField = (isset($this->settingVars->dataArray[$storeField]) && isset($this->settingVars->dataArray[$storeField]['ID'])) ? 
            $this->settingVars->dataArray[$storeField]['ID'] : $this->settingVars->dataArray[$storeField]['NAME'];
        $this->storeNameField = $this->settingVars->dataArray[$storeField]['NAME'];
        return;
    }

    /*     * ***
     * COLLECTS TABLE JOINING STRING FROM PROJECT SETTINGS
     * ALSO APPLYS FILTER CONDITIONS TO THE STRING
     * AND RETURNS $tablejoins_and_filters
     * *** */

    public function getAll() {
        $extraFields = array();

        if (isset($_REQUEST["cl"]) && $_REQUEST["cl"] != "") {
            $extraFields[] = $this->settingVars->clusterID;
            $tablejoins_and_filters .= " AND " . $this->settingVars->clusterID . " ='" . $_REQUEST["cl"] . "' ";
        }
        
        if (isset($_REQUEST["selectedSkuID"]) && $_REQUEST["selectedSkuID"] != "" && $_REQUEST["selectedSkuID"] != "all") {
            $tablejoins_and_filters .= " AND " . $this->settingVars->maintable . ".skuID ='" . $_REQUEST["selectedSkuID"] . "' ";
        }
                
        $this->prepareTablesUsedForQuery($extraFields);
        $tablejoins_and_filters1 = parent::getAll();
        $tablejoins_and_filters1 .= $tablejoins_and_filters;

        return $tablejoins_and_filters1;
    }

    public function checkConfiguration(){
        $configurationCheck = new config\ConfigurationCheck($this->settingVars, $this->queryVars);
        $configurationCheck->checkClusterConfiguration();
        return ;
    }
}
?>