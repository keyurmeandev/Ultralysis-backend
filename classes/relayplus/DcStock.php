<?php
namespace classes\relayplus;

use datahelper;
use projectsettings;
use filters;
use db;
use config;
use utils;

class DcStock extends config\UlConfig {
    /*     * ***
     * go()
     * Default gateway function, should be similar in all DATA CLASS
     * arguments:
     * $settingVars [project settingsGateway variables]
     * @return $this->jsonOutput with all data that should be sent to client app
     * *** */

    public function go($settingVars) {
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES
        $this->redisCache = new \utils\RedisCache($this->queryVars);
        $this->checkConfiguration();
        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll                     

        $action = $_REQUEST['action'];
        //COLLECTING TOTAL SALES  
        switch ($action) {
            case 'filterBySku':
                $this->storeDCStockGrid();
                break;
            case 'productDCStockGrid';
                $this->productDCStockGrid();
                break;
            case 'storeDCStockGrid';
                $this->storeDCStockGrid();
                break;
        }
        
        return $this->jsonOutput;
    }
    
    /**
     * productStockGrid()
     * This Function is used to retrieve list based on set parameters     
     * @access private  
     * @return $this->jsonOutput with all data that should be sent to client app     
     */
    private function productDCStockGrid() {
        $gridData = array();
        $this->queryPart = $this->getAll();
        $query = "SELECT ".$this->settingVars->retaillinkdctable.".PIN AS SKUID ,".$this->settingVars->skutable.".PNAME AS SKU , ".
                 " ".$this->settingVars->retaillinkdctable.".wm_week AS time_range, ".
                 " SUM(".$this->settingVars->retaillinkdctable.".Depot_OH_Qty) AS depot_stock, ".
                 " SUM(".$this->settingVars->retaillinkdctable.".Depot_Ord_Qty) AS depot_orders , ".
                 " SUM(".$this->settingVars->retaillinkdctable.".Store_OHQ) AS store_stock, ".
                 " SUM(".$this->settingVars->retaillinkdctable.".Store_Trans_Qty) AS store_trans, ".
                 " SUM(".$this->settingVars->retaillinkdctable.".Store_Depot_Qty) AS store_depot, ".
                 " SUM(".$this->settingVars->retaillinkdctable.".Store_Order_Qty) AS store_order, ".
                 " SUM(".$this->settingVars->retaillinkdctable.".qty) AS sales_qty ".
                 " FROM ".$this->settingVars->retaillinkdctable.", ".$this->settingVars->skutable.
                 " WHERE ".$this->settingVars->retaillinkdctable.".accountID= ".$this->settingVars->aid.
                 " AND ".$this->settingVars->retaillinkdctable.".gid=".$this->settingVars->GID.
                 " AND ".$this->settingVars->retaillinkdctable.".PIN = ".$this->settingVars->skutable.".PIN ".
                 " AND ".$this->settingVars->skutable.".gid=".$this->settingVars->GID.
                 " AND ".$this->settingVars->retaillinkdctable.".gid=".$this->settingVars->skutable.".gid ".
                 " AND ".$this->settingVars->skutable.".clientID='".$this->settingVars->clientID."' ".
                 " AND ".$this->settingVars->skutable.".hide<>1 ".$this->queryPart.
                 " GROUP BY SKUID, SKU, time_range ".
                 " ORDER BY depot_stock DESC";

        
        //MAIN TABLE QUERY
        /*$query = "SELECT ".$this->settingVars->maintable.".SIN AS SKUID " .
                ",".$this->skuName." AS SKU " .
                ",SUM((CASE WHEN ".$this->settingVars->DatePeriod." IN('" . filters\timeFilter::$tyDaysRange[0] . "') THEN ".$this->ohq." ELSE 0 END)) AS OHQ" .
                ",SUM((CASE WHEN ". $this->settingVars->DatePeriod." IN('" . implode("','", filters\timeFilter::$ty14DaysRange) . "') THEN ".$this->settingVars->ProjectValue." ELSE 0 END)) AS SALES" .
                ",".$this->itemStatus." AS ITEMSTATUS " .
                " FROM " . $this->settingVars->tablename . " " . $this->queryPart .                 
                "GROUP BY SKUID, SKU, ITEMSTATUS ORDER BY OHQ DESC";*/
        //echo $query;//exit;

        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }
        
        if (is_array($result) && !empty($result)) {
            foreach ($result as $key => $value) {
                $dataSet = array();
                $dataSet['SKUID']                   = $value['SKUID'];
                $dataSet['SKU']                     = $value['SKU'];
                $dataSet['time_range']              = $value['time_range'];
                $dataSet['depot_stock']             = number_format($value['depot_stock'], 0, '.', ',');
                $dataSet['depot_orders']            = number_format($value['depot_orders'], 0, '.', ',');
                $dataSet['store_stock']             = number_format($value['store_stock'], 0, '.', ',');
                $dataSet['OH_IT_OO_WH_STOCK_QTY']   = number_format($value['store_stock']+$value['store_trans']+$value['store_depot']+$value['store_order'], 0, '.', ',');
                /*$dataSet['TOTAL_STOCK_excl_Orders'] = number_format($value['depot_stock']+$value['store_stock']+$value['store_trans']+$value['store_depot']+$value['store_order'], 0, '.', ',');*/
                $dataSet['TOTAL_STOCK_excl_Orders'] = number_format($value['depot_stock']+$value['store_stock'], 0, '.', ',');
                /*$dataSet['TOTAL_STOCK_incl_Orders'] = number_format($value['depot_stock']+ $value['store_stock']+$value['store_trans']+$value['store_depot']+$value['store_order']+$value['depot_orders'], 0, '.', ',');*/
                $dataSet['TOTAL_STOCK_incl_Orders'] = number_format($value['depot_stock']+ $value['store_stock']+$value['depot_orders'], 0, '.', ',');
                $dataSet['sales_qty']               = number_format($value['sales_qty'], 0, '.', ',');
                $gridData[] = $dataSet;
            }
        } // end if
        $this->jsonOutput['productDCStockGrid'] = $gridData;
    }
    
    /**
     * storeStockGrid()
     * This Function is used to retrieve list based on set parameters     
     * @access private  
     * @return $this->jsonOutput with all data that should be sent to client app     
     */
    private function storeDCStockGrid() {
        $gridData = $chartData = $seriesData = array();
        $this->queryPart = $this->getAll();
        $query = "SELECT ".$this->settingVars->retaillinkdctable.".PIN AS SKUID ,".$this->settingVars->skutable.".PNAME AS SKU , ".
                 " MAX(".$this->settingVars->storetable.".SNAME) AS STORE, ".
                 " ".$this->settingVars->retaillinkdctable.".wm_week AS time_range, ".
                 " ".$this->settingVars->retaillinkdctable.".SNO AS SNO, ".
                 " SUM(".$this->settingVars->retaillinkdctable.".Depot_OH_Qty) AS depot_stock, ".
                 " SUM(".$this->settingVars->retaillinkdctable.".Depot_Ord_Qty) AS depot_orders , ".
                 " SUM(".$this->settingVars->retaillinkdctable.".Store_OHQ) AS store_stock, ".
                 " SUM(".$this->settingVars->retaillinkdctable.".Store_Trans_Qty) AS store_trans, ".
                 " SUM(".$this->settingVars->retaillinkdctable.".Store_Depot_Qty) AS store_depot, ".
                 " SUM(".$this->settingVars->retaillinkdctable.".Store_Order_Qty) AS store_order, ".
                 " SUM(".$this->settingVars->retaillinkdctable.".qty) AS sales_qty ".
                 " FROM ".$this->settingVars->retaillinkdctable.", ".$this->settingVars->skutable.", ".$this->settingVars->storetable.
                 " WHERE ".$this->settingVars->retaillinkdctable.".accountID= ".$this->settingVars->aid.
                 " AND ".$this->settingVars->retaillinkdctable.".gid=".$this->settingVars->GID.
                 " AND ".$this->settingVars->retaillinkdctable.".PIN = ".$this->settingVars->skutable.".PIN ".
                 " AND ".$this->settingVars->skutable.".gid=".$this->settingVars->GID.
                 " AND ".$this->settingVars->retaillinkdctable.".gid=".$this->settingVars->skutable.".gid ".
                 " AND ".$this->settingVars->skutable.".clientID='".$this->settingVars->clientID."' ".
                 " AND ".$this->settingVars->skutable.".hide<>1 ".
                 " AND ".$this->settingVars->retaillinkdctable.".SNO = ".$this->settingVars->storetable.".sno ".
                 " AND ".$this->settingVars->retaillinkdctable.".gid = ".$this->settingVars->storetable.".gid ".
                 " AND " . $this->settingVars->storetable . ".gid=".$this->settingVars->GID." ".$this->queryPart.
                 " GROUP BY SKUID, SKU, time_range, SNO ".
                 " ORDER BY depot_stock DESC";
        //echo $query;
        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }

        $series1 = array(); //TOTAL STORE STOCK
        $series2 = array(); //DC ON HAND
        $series3 = array(); //DEPOT ORDERS
        $categories = array(); //SNO
        if (is_array($result) && !empty($result)) {
            
            foreach ($result as $key => $value) {
                $dataSet = array();
                $dataSet['SKUID']                   = $value['SKUID'];
                $dataSet['SKU']                     = $value['SKU'];
                $dataSet['time_range']              = $value['time_range'];
                $dataSet['SNO']                     = $value['SNO'];
                $dataSet['STORE']                   = $value['STORE'];
                $dataSet['depot_stock']             = number_format($value['depot_stock'], 0, '.', ',');
                $dataSet['depot_orders']            = number_format($value['depot_orders'], 0, '.', ',');
                $dataSet['store_stock']             = number_format($value['store_stock'], 0, '.', ',');
                $dataSet['OH_IT_OO_WH_STOCK_QTY']   = number_format($value['store_stock']+$value['store_trans']+$value['store_depot']+$value['store_order'], 0, '.', ',');
                /*$dataSet['TOTAL_STOCK_excl_Orders'] = number_format($value['depot_stock']+$value['store_stock']+$value['store_trans']+$value['store_depot']+$value['store_order'], 0, '.', ',');*/
                $dataSet['TOTAL_STOCK_excl_Orders'] = number_format($value['depot_stock']+$value['store_stock'], 0, '.', ',');
                /*$dataSet['TOTAL_STOCK_incl_Orders'] = number_format($value['depot_stock']+ $value['store_stock']+$value['store_trans']+$value['store_depot']+$value['store_order']+$value['depot_orders'], 0, '.', ',');*/
                $dataSet['TOTAL_STOCK_incl_Orders'] = number_format($value['depot_stock']+ $value['store_stock']+$value['depot_orders'], 0, '.', ',');
                $dataSet['sales_qty']               = number_format($value['sales_qty'], 0, '.', ',');
                $gridData[] = $dataSet;

                if (!empty($_REQUEST['SKU'])) {
                    $temp = array();
                    $temp['SNO'] = $value['SNO'];
                    $temp['OH_IT_OO_WH_STOCK_QTY'] = $value['store_stock']+$value['store_trans']+$value['store_depot']+$value['store_order'];
                    $temp['STORE_STOCK'] = (int)$value['store_stock'];
                    $temp['depot_stock'] = (int)$value['depot_stock'];
                    $temp['depot_orders'] = (int)$value['depot_orders'];
                    $chartTitleSKU = $value['SKU'];
                    $chartTitleTimeRange = $value['time_range'];
                    $seriesData[] = $temp;
                }
            }
        } // end if

        $this->jsonOutput['storeDCStockGrid'] = $gridData;
        if (!empty($_REQUEST['SKU'])) {
            if(is_array($seriesData) && !empty($seriesData)){
                $seriesData = utils\SortUtility::sort2DArray($seriesData, 'STORE_STOCK', utils\SortTypes::$SORT_DESCENDING);
                foreach ($seriesData as $value) {
                    //$series1[] = (int)$value['OH_IT_OO_WH_STOCK_QTY'];
                    $series1[] = (int)$value['STORE_STOCK'];
                    $series2[] = (int)$value['depot_stock'];
                    $series3[] = (int)$value['depot_orders'];
                    $categories[] = $value['SNO'];
                }
            }
            $chartData['chartTitle'] = 'Stock by DC for '.$chartTitleSKU.' (Itm Nbr: '.$_REQUEST['SKU'].') at WM Week '.$chartTitleTimeRange;
            $chartData['categories'] = $categories;
            $chartData['series'][] = array(
                'name' => 'STORE STOCK',
                'data' => $series1,
                'color' => "#f3ac32",
            );
            $chartData['series'][] = array(
                'name' => 'DEPOT STOCK',
                'data' => $series2,
                'color' => "#b8b8b8",
            );
            $chartData['series'][] = array(
                'name' => 'DEPOT ORDERS',
                'data' => $series3,
                'color' => "#bb6e36",
            );
            $this->jsonOutput['storeDCStockChart'] = $chartData;
        }
    }
    
    /* ****
     * COLLECTS TABLE JOINING STRING FROM PROJECT SETTINGS
     * ALSO APPLYS FILTER CONDITIONS TO THE STRING
     * AND RETURNS $tablejoins_and_filters
     * ****/
    public function getAll() {
        $tablejoins_and_filter = "";
        //$tablejoins_and_filters = parent::getAll();

        if ($_REQUEST['SKU'] != "") {
            $tablejoins_and_filters .= " AND " . $this->settingVars->retaillinkdctable . ".PIN='" . $_REQUEST['SKU'] . "'";
        }

        // will work when calling with sendRequest services
        if (isset($_REQUEST["HidePrivate"]) && $_REQUEST["HidePrivate"] == 'true' && !empty($this->settingVars->privateLabelFilterField)) {
            $tablejoins_and_filters  .=" AND ".$this->settingVars->privateLabelFilterField." = 0 ";
        }

        // will work when calling with default services
        if (!isset($_REQUEST["HidePrivate"])){            
            if($this->settingVars->pageArray[$this->settingVars->pageName]["PRIVATE_LABEL"] != null)
            if($this->settingVars->pageArray[$this->settingVars->pageName]["PRIVATE_LABEL"]==true && !empty($this->settingVars->privateLabelFilterField)){
                $tablejoins_and_filters  .=" AND ".$this->settingVars->privateLabelFilterField." = 0 ";
            }
        }
        return $tablejoins_and_filters;
    }

    public function checkConfiguration(){
        if(!isset($this->settingVars->retaillinkdctable) || empty($this->settingVars->retaillinkdctable))
            $this->configurationFailureMessage("Retail link DC table not found.");
        /*$configurationCheck = new config\ConfigurationCheck($this->settingVars, $this->queryVars);
        $configurationCheck->checkClusterConfiguration();*/
        return ;
    }
}

?>