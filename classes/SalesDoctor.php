<?php

namespace classes;

use datahelper;
use projectsettings;
use filters;
use utils;
use db;
use config;

class SalesDoctor extends config\UlConfig {
    /*     * ***
     * Default gateway function, should be similar in all DATA CLASS
     * arguments:
     *  $settingVars [project settingsGateway variables]
     * @return jsonOutput with all data that should be sent to client app
     * *** */

    public function go($settingVars) {
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES   
        $this->redisCache = new \utils\RedisCache($this->queryVars);
        $this->buildDataArray();

        $this->problemDescription = [
            '1_1' => 'NO PROBLEM',
            '1_2' => 'WILL KEEP TRACKING',
            '1_3' => 'OVERSTOCK HIGH',
            '1_4' => 'OVERSTOCK HIGH',
            '2_1' => 'WILL KEEP TRACKING',
            '2_2' => 'NO PROBLEM',
            '2_3' => 'WILL KEEP TRACKING',
            '2_4' => 'OVERSTOCK MEDIUM',
            '3_1' => 'UNDERSTOCK MEDIUM',
            '3_2' => 'WILL KEEP TRACKING',
            '3_3' => 'NO PROBLEM',
            '3_4' => 'WILL KEEP TRACKING',
            '4_1' => 'UNDERSTOCK HIGH',
            '4_2' => 'UNDERSTOCK HIGH',
            '4_3' => 'WILL KEEP TRACKING',
            '4_4' => 'NO PROBLEM'
        ];

        $action = $_REQUEST["action"];
        switch ($action) {
            case "getGridData":
                $this->getGridData();
                break;
            case "skuChange":
                $this->skuSelect();
                break;
            case "getCordinate":
                $_REQUEST['cordinate'] = "ALL";
                $this->getCordinateValues();
                $this->getGridData();
                break;
        }


        return $this->jsonOutput; 
    }

    public function stockCoverFetchData($SKU)
    {
        $compareBetween = $_REQUEST['compareBetween'];
        $compareField = $this->ohq;
        if (isset($compareBetween) && !empty($compareBetween) && $compareBetween == 'Q_VS_M') {
            $compareField = $this->msq;
        }

        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $this->measureFields[] = $this->skuID;
        $this->measureFields[] = $this->skuName;
        $this->measureFields[] = $this->storeID;
        $this->measureFields[] = $this->storeName;
        $this->measureFields[] = $this->cluster;
        
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->queryPart = $this->getAll();

        if(isset($_REQUEST['territoryLevel'])) {
            $addTerritoryColumn = $this->settingVars->territoryTable . ".Level".$_REQUEST["territoryLevel"]." AS TERRITORY, ";
            $addTerritoryGroup = ",TERRITORY";
            $this->measureFields[] = $this->settingVars->territoryTable . ".Level".$_REQUEST["territoryLevel"];
        }
        else
        {
            $addTerritoryColumn = '';
            $addTerritoryGroup = '';
        }

        $query = "SELECT ". 
            $this->skuID . " AS TPNB, " .
            $this->storeID . " AS SNO, " .
            $addTerritoryColumn.
            // "SUM(ROUND((CASE WHEN " . $this->settingVars->ProjectVolume . ">0 THEN 1 ELSE 0 END)*" . $this->settingVars->ProjectVolume . ")) AS SALES_QTY, " .
            "SUM((CASE WHEN " . $this->settingVars->DatePeriod . "= '" . filters\timeFilter::$tyDaysRange[0] . "' THEN 1 ELSE 0 END)*" . $compareField . ") AS STOCK, " .
            "NTILE(100) OVER (ORDER BY SUM((CASE WHEN " . $this->settingVars->DatePeriod . "= '" . filters\timeFilter::$tyDaysRange[0] . "' THEN 1 ELSE 0 END)*" . $compareField . ")) STOCK_NTILE ".
            "FROM " . $this->settingVars->tablename . " " . $this->queryPart .
                    " AND " . filters\timeFilter::$tyWeekRange ." ".
            "GROUP BY TPNB,SNO ".$addTerritoryGroup." ".
            "ORDER BY STOCK DESC";

        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);

        if (is_array($result) && !empty($result)) {
            foreach ($result as $ntileQtyData) {
                $arrayKey = $ntileQtyData['TPNB']."_".$ntileQtyData['SNO'];
                if (!empty($this->territorytable))
                    $arrayKey .= "_".$ntileQtyData['TPNB'];
                
                $ntileQty[$arrayKey] = array(
                    'STOCK' => $ntileQtyData['STOCK'],
                    'STOCK_NTILE' => $ntileQtyData['STOCK_NTILE']
                );
            }
        }

        $query = "SELECT ". 
            $this->skuID . " AS TPNB, " .
            "TRIM(MAX(" . $this->skuName . ")) AS SKU, " .
            $this->storeID . " AS SNO, " .
            "TRIM(MAX(" . $this->storeName . ")) AS STORE, " .
            $addTerritoryColumn.
            "TRIM(MAX(" . $this->cluster . ")) AS CLUSTER, " .
            "SUM((CASE WHEN " . $this->settingVars->ProjectVolume . ">0 THEN 1 ELSE 0 END)*" . $this->settingVars->ProjectVolume . ") AS SALES_VALUE, " .
            "NTILE(100) OVER (ORDER BY SUM((CASE WHEN " . $this->settingVars->ProjectVolume . ">0 THEN 1 ELSE 0 END)*" . $this->settingVars->ProjectVolume . ")) SALES_VALUE_NTILE ".
            "FROM " . $this->settingVars->tablename . " " . $this->queryPart .
                    " AND " . filters\timeFilter::$tyWeekRange ." ".
            "GROUP BY TPNB,SNO ".$addTerritoryGroup." ".
            "ORDER BY SALES_VALUE DESC";
        //echo $query;exit;
        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);

        $this->redisCache->requestHash = $this->redisCache->prepareQueryHash('salesDoctorData');
        $this->redisCache->setDataForStaticHash($result);

        $this->redisCache->requestHash = $this->redisCache->prepareQueryHash('salesDoctorNtileQty');
        $this->redisCache->setDataForStaticHash($ntileQty);

        return array($result, $ntileQty);
    }

    public function getCordinateValues() {
         /*
            SALES QUARTILE / STOCK QUARTILE
                1 => 1-25
                2 => 26-50
                3 => 51-75
                4 => 76-100
        */
        $cordinateValues = [
            'cordinate_4_1' => 0, 'cordinate_4_2' => 0, 'cordinate_4_3' => 0, 'cordinate_4_4' => 0, 
            'cordinate_3_1' => 0, 'cordinate_3_2' => 0, 'cordinate_3_3' => 0, 'cordinate_3_4' => 0, 
            'cordinate_2_1' => 0, 'cordinate_2_2' => 0, 'cordinate_2_3' => 0, 'cordinate_2_4' => 0, 
            'cordinate_1_1' => 0, 'cordinate_1_2' => 0, 'cordinate_1_3' => 0, 'cordinate_1_4' => 0,
        ];
        $SKU = $_REQUEST['SKU'];

        if (!empty($SKU)) {
            list($result, $ntileQty) = $this->stockCoverFetchData($SKU);

            if (is_array($result) && !empty($result)) {
                foreach ($result as $ntileData) {
                    $arrayKey = $ntileData['TPNB']."_".$ntileData['SNO'];
                    if (!empty($this->territorytable))
                        $arrayKey .= "_".$ntileData['TPNB'];

                    $ntileData['STOCK_NTILE'] = (isset($ntileQty[$arrayKey]) && !empty($ntileQty[$arrayKey])) ? $ntileQty[$arrayKey]['STOCK_NTILE'] : 0;

                    if (($ntileData['SALES_VALUE_NTILE'] > 0 && $ntileData['SALES_VALUE_NTILE'] <= 25) && ($ntileData['STOCK_NTILE'] > 0 && $ntileData['STOCK_NTILE'] <= 25))
                        $cordinateValues['cordinate_1_1']++;

                    if (($ntileData['SALES_VALUE_NTILE'] > 0 && $ntileData['SALES_VALUE_NTILE'] <= 25) && ($ntileData['STOCK_NTILE'] > 25 && $ntileData['STOCK_NTILE'] <= 50))
                        $cordinateValues['cordinate_1_2']++;

                    if (($ntileData['SALES_VALUE_NTILE'] > 0 && $ntileData['SALES_VALUE_NTILE'] <= 25) && ($ntileData['STOCK_NTILE'] > 50 && $ntileData['STOCK_NTILE'] <= 75))
                        $cordinateValues['cordinate_1_3']++;

                    if (($ntileData['SALES_VALUE_NTILE'] > 0 && $ntileData['SALES_VALUE_NTILE'] <= 25) && ($ntileData['STOCK_NTILE'] > 75 && $ntileData['STOCK_NTILE'] <= 100))
                        $cordinateValues['cordinate_1_4']++;

                    if (($ntileData['SALES_VALUE_NTILE'] > 25 && $ntileData['SALES_VALUE_NTILE'] <= 50)  && ($ntileData['STOCK_NTILE'] > 0 && $ntileData['STOCK_NTILE'] <= 25))
                        $cordinateValues['cordinate_2_1']++;

                    if (($ntileData['SALES_VALUE_NTILE'] > 25 && $ntileData['SALES_VALUE_NTILE'] <= 50)  && ($ntileData['STOCK_NTILE'] > 25 && $ntileData['STOCK_NTILE'] <= 50))
                        $cordinateValues['cordinate_2_2']++;

                    if (($ntileData['SALES_VALUE_NTILE'] > 25 && $ntileData['SALES_VALUE_NTILE'] <= 50)  && ($ntileData['STOCK_NTILE'] > 50 && $ntileData['STOCK_NTILE'] <= 75))
                        $cordinateValues['cordinate_2_3']++;

                    if (($ntileData['SALES_VALUE_NTILE'] > 25 && $ntileData['SALES_VALUE_NTILE'] <= 50)  && ($ntileData['STOCK_NTILE'] > 75 && $ntileData['STOCK_NTILE'] <= 100))
                        $cordinateValues['cordinate_2_4']++;

                    if (($ntileData['SALES_VALUE_NTILE'] > 50 && $ntileData['SALES_VALUE_NTILE'] <= 75)  && ($ntileData['STOCK_NTILE'] > 0 && $ntileData['STOCK_NTILE'] <= 25))
                        $cordinateValues['cordinate_3_1']++;

                    if (($ntileData['SALES_VALUE_NTILE'] > 50 && $ntileData['SALES_VALUE_NTILE'] <= 75)  && ($ntileData['STOCK_NTILE'] > 25 && $ntileData['STOCK_NTILE'] <= 50))
                        $cordinateValues['cordinate_3_2']++;

                    if (($ntileData['SALES_VALUE_NTILE'] > 50 && $ntileData['SALES_VALUE_NTILE'] <= 75)  && ($ntileData['STOCK_NTILE'] > 50 && $ntileData['STOCK_NTILE'] <= 75))
                        $cordinateValues['cordinate_3_3']++;

                    if (($ntileData['SALES_VALUE_NTILE'] > 50 && $ntileData['SALES_VALUE_NTILE'] <= 75)  && ($ntileData['STOCK_NTILE'] > 75 && $ntileData['STOCK_NTILE'] <= 100))
                        $cordinateValues['cordinate_3_4']++;

                    if (($ntileData['SALES_VALUE_NTILE'] > 75 && $ntileData['SALES_VALUE_NTILE'] <= 100)  && ($ntileData['STOCK_NTILE'] > 0 && $ntileData['STOCK_NTILE'] <= 25))
                        $cordinateValues['cordinate_4_1']++;

                    if (($ntileData['SALES_VALUE_NTILE'] > 75 && $ntileData['SALES_VALUE_NTILE'] <= 100)  && ($ntileData['STOCK_NTILE'] > 25 && $ntileData['STOCK_NTILE'] <= 50))
                        $cordinateValues['cordinate_4_2']++;

                    if (($ntileData['SALES_VALUE_NTILE'] > 75 && $ntileData['SALES_VALUE_NTILE'] <= 100)  && ($ntileData['STOCK_NTILE'] > 50 && $ntileData['STOCK_NTILE'] <= 75))
                        $cordinateValues['cordinate_4_3']++;

                    if (($ntileData['SALES_VALUE_NTILE'] > 75 && $ntileData['SALES_VALUE_NTILE'] <= 100)  && ($ntileData['STOCK_NTILE'] > 75 && $ntileData['STOCK_NTILE'] <= 100))
                        $cordinateValues['cordinate_4_4']++;
                }
            }
        }

        $this->jsonOutput['cordinateValues'] = $cordinateValues;
    }
    
    public function getGridData() {
        $cordinate = $_REQUEST['cordinate'];
        $SKU = $_REQUEST['SKU'];
        $gridData = $ntileDataList = array();

        if (!empty($cordinate) && !empty($SKU)) {
            $result = $this->redisCache->checkAndReadByStaticHashFromCache($this->redisCache->prepareQueryHash('salesDoctorData'));
            $ntileQty = $this->redisCache->checkAndReadByStaticHashFromCache($this->redisCache->prepareQueryHash('salesDoctorNtileQty'));
            /*
                SALES QUARTILE / STOCK QUARTILE
                    1 => 0-25
                    2 => 26-50
                    3 => 51-75
                    4 => 76-100
            */
            if ($cordinate != 'ALL') {
                $quartilePart = ["1-25", "26-50", "51-75", "76-100"];
                $cordinatePart = explode("_", $cordinate);
                $cordinateQuartileY = $quartilePart[$cordinatePart[0]-1];
                $cordinateQuartileX = $quartilePart[$cordinatePart[1]-1];

                $cordinateQuartileYPart = explode("-", $cordinateQuartileY);
                $cordinateQuartileXPart = explode("-", $cordinateQuartileX);
            }

            if (is_array($result) && !empty($result)) {
                foreach ($result as $ntileData) {
                    $arrayKey = $ntileData['TPNB']."_".$ntileData['SNO'];
                    if (!empty($this->territorytable))
                        $arrayKey .= "_".$ntileData['TPNB'];

                    $ntileData['STOCK_NTILE'] = (isset($ntileQty[$arrayKey]) && !empty($ntileQty[$arrayKey])) ? $ntileQty[$arrayKey]['STOCK_NTILE'] : 0;

                    $ntileData['PROBLEM'] = $this->fetchProblemDesc($ntileData);
                    $ntileData['PROBLEM_DESCRIPTION'] = $this->problemDescription[$ntileData['PROBLEM']];

                    if ($cordinate != 'ALL' && ($ntileData['SALES_VALUE_NTILE'] >= (int)$cordinateQuartileYPart[0] && $ntileData['SALES_VALUE_NTILE'] <= (int)$cordinateQuartileYPart[1])  && ($ntileData['STOCK_NTILE'] >= (int)$cordinateQuartileXPart[0] && $ntileData['STOCK_NTILE'] <= (int)$cordinateQuartileXPart[1])) {
                        $ntileDataList[$ntileData['SNO']] = $ntileData;
                    } elseif($cordinate == 'ALL') {
                        $ntileDataList[$ntileData['SNO']] = $ntileData;
                    }
                }

                if (is_array($ntileDataList) && !empty($ntileDataList)) {
                    $snoList = array_keys($ntileDataList);
                    $this->settingVars->tableUsedForQuery = $this->measureFields = array();
                    $this->measureFields[] = $this->skuID;
                    $this->measureFields[] = $this->storeID;
                    $this->measureFields[] = $this->storeName;
                    $this->measureFields[] = $this->ohq;
                    if(isset($this->msq))
                        $this->measureFields[] = $this->msq;

                    $this->settingVars->useRequiredTablesOnly = true;
                    if (is_array($this->measureFields) && !empty($this->measureFields)) {
                        $this->prepareTablesUsedForQuery($this->measureFields);
                    }
                    $this->queryPart = $this->getAll();

                    if (!empty($this->settingVars->territoryTable)) {
                        $addTerritoryColumn = ", ".$this->settingVars->territoryTable . ".Level1 AS TERRITORY";
                        $addTerritoryGroup = ",TERRITORY";
                        $this->measureFields[] = $this->settingVars->territoryTable . ".Level1";
                    }
                    else
                    {
                        $addTerritoryColumn = '';
                        $addTerritoryGroup = '';
                    }

                    $query = "SELECT ".
                        $this->storeID . " AS SNO " .
                        ",TRIM(MAX(" . $this->storeName . ")) AS STORE " .
                        $addTerritoryColumn.             
                        ",SUM((CASE WHEN " . $this->settingVars->ProjectVolume . ">0 THEN 1 ELSE 0 END)*" . $this->settingVars->ProjectVolume . ") AS SALES " .
                        ",SUM((CASE WHEN " . $this->settingVars->DatePeriod . "= '" . filters\timeFilter::$tyDaysRange[0] . "' THEN 1 ELSE 0 END)*" . $this->ohq . ") AS STOCK ";
                        
                        if(isset($this->msq))
                            $query .= ",SUM((CASE WHEN " . $this->settingVars->DatePeriod . "= '" . filters\timeFilter::$tyDaysRange[0] . "' THEN 1 ELSE 0 END)*" . $this->msq . ") AS SHELF ";
                        else
                            $query .= ",0 AS SHELF ";
                        
                        $query .= "FROM " . $this->settingVars->tablename . " " . $this->queryPart ." ".
                        "AND " . filters\timeFilter::$tyWeekRange." AND ".$this->storeID." IN (".implode(",", $snoList).") ".
                        "GROUP BY SNO ".$addTerritoryGroup;
                    $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);

                    if (is_array($result) && !empty($result)) {
                        foreach ($result as $storeData) {
                            $aveDailySales  = $storeData['SALES'] / filters\timeFilter::$daysTimeframe;
                            $dc = ($storeData['SALES'] > 0) ? (($storeData['STOCK'] + $storeData['TRANSIT']) / $aveDailySales) : 0;
                            $absVal = abs($ntileDataList[$storeData['SNO']]['SALES_VALUE_NTILE'] - $ntileDataList[$storeData['SNO']]['STOCK_NTILE'])/100;

                            $row                    = array();
                            $row['SNO']             = $storeData['SNO'];
                            $row['STORE']           = utf8_encode($storeData['STORE']);
                            $row['SALES']           = $storeData['SALES'];
                            $row['STOCK']           = $storeData['STOCK'];
                            $row['SHELF']           = $storeData['SHELF'];
                            $row['SALES_NTILE']     = $ntileDataList[$storeData['SNO']]['SALES_VALUE_NTILE'];
                            $row['STOCK_NTILE']     = $ntileDataList[$storeData['SNO']]['STOCK_NTILE'];
                            $row['DAYS_COVER']      = number_format($dc, 2, '.', '');
                            $row['CORDINATE']       = $ntileDataList[$storeData['SNO']]['PROBLEM'];
                            // $row['PROBLEM']         = ($absVal > 0.6) ? "YES" : "NO";
                            $row['PROBLEM']         = $ntileDataList[$storeData['SNO']]['PROBLEM_DESCRIPTION'];

                            if(isset($storeData['TERRITORY']))
                                $row['TERRITORY'] = $storeData['TERRITORY'];

                            array_push($gridData, $row);
                        }
                    }
                }
            }
        }

        $this->jsonOutput['gridData'] = $gridData;
    }

    public function fetchProblemDesc($ntileData)
    {
        if (($ntileData['SALES_VALUE_NTILE'] > 0 && $ntileData['SALES_VALUE_NTILE'] <= 25) && ($ntileData['STOCK_NTILE'] > 0 && $ntileData['STOCK_NTILE'] <= 25))
            return '1_1';

        if (($ntileData['SALES_VALUE_NTILE'] > 0 && $ntileData['SALES_VALUE_NTILE'] <= 25) && ($ntileData['STOCK_NTILE'] > 25 && $ntileData['STOCK_NTILE'] <= 50))
            return '1_2';

        if (($ntileData['SALES_VALUE_NTILE'] > 0 && $ntileData['SALES_VALUE_NTILE'] <= 25) && ($ntileData['STOCK_NTILE'] > 50 && $ntileData['STOCK_NTILE'] <= 75))
            return '1_3';

        if (($ntileData['SALES_VALUE_NTILE'] > 0 && $ntileData['SALES_VALUE_NTILE'] <= 25) && ($ntileData['STOCK_NTILE'] > 75 && $ntileData['STOCK_NTILE'] <= 100))
            return '1_4';

        if (($ntileData['SALES_VALUE_NTILE'] > 25 && $ntileData['SALES_VALUE_NTILE'] <= 50)  && ($ntileData['STOCK_NTILE'] > 0 && $ntileData['STOCK_NTILE'] <= 25))
            return '2_1';

        if (($ntileData['SALES_VALUE_NTILE'] > 25 && $ntileData['SALES_VALUE_NTILE'] <= 50)  && ($ntileData['STOCK_NTILE'] > 25 && $ntileData['STOCK_NTILE'] <= 50))
            return '2_2';

        if (($ntileData['SALES_VALUE_NTILE'] > 25 && $ntileData['SALES_VALUE_NTILE'] <= 50)  && ($ntileData['STOCK_NTILE'] > 50 && $ntileData['STOCK_NTILE'] <= 75))
            return '2_3';

        if (($ntileData['SALES_VALUE_NTILE'] > 25 && $ntileData['SALES_VALUE_NTILE'] <= 50)  && ($ntileData['STOCK_NTILE'] > 75 && $ntileData['STOCK_NTILE'] <= 100))
            return '2_4';

        if (($ntileData['SALES_VALUE_NTILE'] > 50 && $ntileData['SALES_VALUE_NTILE'] <= 75)  && ($ntileData['STOCK_NTILE'] > 0 && $ntileData['STOCK_NTILE'] <= 25))
            return '3_1';

        if (($ntileData['SALES_VALUE_NTILE'] > 50 && $ntileData['SALES_VALUE_NTILE'] <= 75)  && ($ntileData['STOCK_NTILE'] > 25 && $ntileData['STOCK_NTILE'] <= 50))
            return '3_2';

        if (($ntileData['SALES_VALUE_NTILE'] > 50 && $ntileData['SALES_VALUE_NTILE'] <= 75)  && ($ntileData['STOCK_NTILE'] > 50 && $ntileData['STOCK_NTILE'] <= 75))
            return '3_3';

        if (($ntileData['SALES_VALUE_NTILE'] > 50 && $ntileData['SALES_VALUE_NTILE'] <= 75)  && ($ntileData['STOCK_NTILE'] > 75 && $ntileData['STOCK_NTILE'] <= 100))
            return '3_4';

        if (($ntileData['SALES_VALUE_NTILE'] > 75 && $ntileData['SALES_VALUE_NTILE'] <= 100)  && ($ntileData['STOCK_NTILE'] > 0 && $ntileData['STOCK_NTILE'] <= 25))
            return '4_1';

        if (($ntileData['SALES_VALUE_NTILE'] > 75 && $ntileData['SALES_VALUE_NTILE'] <= 100)  && ($ntileData['STOCK_NTILE'] > 25 && $ntileData['STOCK_NTILE'] <= 50))
            return '4_2';

        if (($ntileData['SALES_VALUE_NTILE'] > 75 && $ntileData['SALES_VALUE_NTILE'] <= 100)  && ($ntileData['STOCK_NTILE'] > 50 && $ntileData['STOCK_NTILE'] <= 75))
            return '4_3';

        if (($ntileData['SALES_VALUE_NTILE'] > 75 && $ntileData['SALES_VALUE_NTILE'] <= 100)  && ($ntileData['STOCK_NTILE'] > 75 && $ntileData['STOCK_NTILE'] <= 100))
            return '4_4';
    }

    /**
     * skuSelect()
     * This Function is used to retrieve sku data based on set parameters for graph     
     * @access private  
     * @return $this->jsonOutput with all data that should be sent to client app     
     */
    private function skuSelect() {

        $this->settingVars->useRequiredTablesOnly = true;
        $this->queryPart = $this->getAll();     

        $query = "SELECT " . $this->settingVars->DatePeriod . ",  DATE_FORMAT(" . $this->settingVars->DatePeriod . ",'%a %e %b') AS DAY" .
                ",SUM(" . $this->settingVars->ProjectVolume . ") AS SALES " .
                ",SUM((CASE WHEN " . $this->ohq . ">0 THEN 1 ELSE 0 END)*" . $this->ohq . ") AS STOCK ";
                
                if($this->settingVars->projectTypeID == 2)
                {
                    $query .= ",SUM(".$this->ohaq.") AS OHAQ" .
                    ",SUM(".$this->baq.") AS BAQ" .
                    ",SUM((CASE WHEN " . $this->gsq . ">0 THEN 1 ELSE 0 END)*" . $this->gsq . ") AS GSQ ";
                }
                
                $query .= " FROM " . $this->settingVars->tablename . " " . $this->queryPart;
                if($this->settingVars->projectTypeID == 2)
                    $query .= " AND ". $this->settingVars->maintable .".OpenDate < ". $this->settingVars->maintable .".insertdate ";
                else
                    $query .= " AND ". $this->settingVars->maintable .".".$this->settingVars->DatePeriod." < ". $this->settingVars->maintable .".insertdate ";
                    
                //$query .= " AND " . filters\timeFilter::$tyWeekRange .
                $query .= "GROUP BY DAY, " . $this->settingVars->DatePeriod . " " .
                "ORDER BY " . $this->settingVars->DatePeriod . " ASC";

        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }

        $value = array();
        
        if (is_array($result) && !empty($result)) {
            foreach ($result as $data) {

                $value['SALES'][]   = $data['SALES'];
                $value['STOCK'][]   = $data['STOCK'];
                $value['DAY'][]     = $data['DAY'];
                
                if($this->settingVars->projectTypeID == 2)
                {
                    $value['ADJ'][]     = $data['OHAQ'] + $data['BAQ'];
                    $value['GSQ'][]     = $data['GSQ'];                
                }
            }
        } // end if
        
        $this->jsonOutput['skuSelect'] = $value;
    }

    public function getAll() {
        $tablejoins_and_filters = "";
        $extraFields = array();
        
        if (isset($_REQUEST["SKU"]) && $_REQUEST["SKU"] != '')
        {
            $extraFields[] = $this->filterSkuID;
            $tablejoins_and_filters .= " AND " . $this->filterSkuID . " = '" . $_REQUEST["SKU"]."' ";
        }
            
        
        if (isset($_REQUEST["SNO"]) && $_REQUEST["SNO"] != '')
        {
            $extraFields[] = $this->storeID;
            $tablejoins_and_filters .= " AND " . $this->storeID." = '".$_REQUEST['SNO']."' ";
        }
        
        $this->prepareTablesUsedForQuery($extraFields);
        $tablejoins_and_filters1 = parent::getAll();
        $tablejoins_and_filters1 .= $tablejoins_and_filters;
        return $tablejoins_and_filters1;
    }    
    
    public function buildDataArray() {
        /* $this->skuID    = "product.PIN";
        $this->skuName  = "product.PNAME";
        $this->storeID  = "store.SNO";
        $this->storeName= "store.sname"; */
    	$this->skuID    = key_exists('ID', $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['SKU_FIELD']]) ? $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['SKU_FIELD']]['ID'] : $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['SKU_FIELD']]['NAME'];
        $this->storeID 	= key_exists('ID', $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['STORE_FIELD']]) ? $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['STORE_FIELD']]['ID'] : $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['STORE_FIELD']]['NAME'];
        $this->storeName= $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['STORE_FIELD']]['NAME'];
        $this->skuName 	= $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['SKU_FIELD']]['NAME'];
        
        $this->cluster  = "store.cl1";
        $this->ohq      = $this->settingVars->maintable . '.'.$this->settingVars->stockFieldName;
        if(isset($this->settingVars->dataArray['F14']) && isset($this->settingVars->dataArray['F14']['NAME']) && $this->settingVars->dataArray['F14']['NAME'] != "")
            $this->msq  = $this->settingVars->dataArray['F14']['NAME'];
        
        $this->gsq        = $this->settingVars->dataArray['F9']['NAME'];
        $this->ohaq       = $this->settingVars->dataArray['F10']['NAME'];
        $this->baq        = $this->settingVars->dataArray['F11']['NAME'];

        $this->filterSkuID = key_exists('ID', $this->settingVars->skuDataSetting) ? $this->settingVars->skuDataSetting['ID'] : $this->settingVars->skuDataSetting['NAME'];
    }
}
?>