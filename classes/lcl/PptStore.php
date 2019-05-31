<?php

namespace classes\lcl;

use datahelper;
use projectsettings;
use filters;
use utils;
use db;
use config;

use PhpOffice;

class PptStore extends config\UlConfig {

    private $territoryLevel;

    public function go($settingVars) {
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES
        //filters\timeFilter::getYTD($settingVars); 


        if ($_REQUEST['TerritoryName'] != "" && $_REQUEST['TerritoryName'] != "undefined")
            $this->territoryLevel = $this->settingVars->territorytable . "." . $_REQUEST['TerritoryName'];
        else
            $this->territoryLevel = $this->settingVars->territorytable . ".level" . $this->settingVars->territoryLevel;

        $this->queryPart = $this->getAll(); //USES OWN getAll FUNCTION

        $action = $_REQUEST["action"];
        switch ($action) {
            case "createppt":
                $this->createPPT();
                break;
            case "changeTerritoryList":
                $this->changeTerritoryList();
                $this->storegridData();
                break;
            case "fetchConfig":
                $this->changeTerritoryList();
                break;
            case "reload":
                $this->storegridData();
                break;
        }

        return $this->jsonOutput;
    }

    private function createPPT() {
        global $value;
        $this->territory = $this->getTerritoryList();
        $value = array();
        $value['storeData'] = $this->storeData();
        $value['storeData'] = $this->storeData();
        $value['storeCatData'] = $this->storeCatData();
        $value['topFiveSku'] = $this->topFiveSku();
        $value['bottomFiveSku'] = $this->bottomFiveSku();
        $value['returnPath'] = true;

        //include('createPptx.php');

        // Get from and to date
        $dateperiod = $this->settingVars->getMydateSelect($this->settingVars->dateperiod, false);
        $query = "SELECT Max(".$dateperiod.") as ToDate, Min(".$dateperiod.")  as FromDate FROM ".$this->settingVars->maintable.", ".$this->settingVars->timetable." WHERE ".$this->settingVars->maintable.".accountID = ".$this->settingVars->aid." AND ".$this->settingVars->maintable.".".$this->settingVars->dateperiod." = ".$dateperiod." AND ".$this->settingVars->maintable.".GID = ".$this->settingVars->timetable.".gid AND ".$this->settingVars->timetable.".gid IN (".$this->settingVars->GID.") AND ".filters\timeFilter::$tyWeekRange;
        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
        
        if(is_array($result) && !empty($result))
        {
            $value['fromDate'] = $result[0]['FromDate'];
            $value['toDate'] = $result[0]['ToDate'];
        }
        
        $objPpt = new CreatePptx($value);
        $filePath = $objPpt->init();

        $this->jsonOutput['downloadLink'] = $this->settingVars->get_full_url() . '/zip/' . basename($filePath);
    }

    private function changeTerritoryList() {
        $query = "SELECT DISTINCT " . $this->territoryLevel . " AS TERRITORY from " . $this->settingVars->territoryHelperTables . $this->settingVars->territoryHelperLink;
        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
        if (is_array($result) && !empty($result)) {
            foreach ($result as $key => $data) {
                $territoryList[$key]['data'] = $data['TERRITORY'];
            }
        }
        $this->jsonOutput['TNAME'] = $territoryList;
    }

    private function getTerritoryList() {
        $territoryList = array();

        $query = "SELECT DISTINCT " . $this->settingVars->territorytable . ".SNO AS SNO," . $this->territoryLevel . " AS TERRITORY from " . $this->settingVars->territoryHelperTables . $this->settingVars->territoryHelperLink;
        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
        if (is_array($result) && !empty($result)) {
            foreach ($result as $key => $data) {
                $territoryList[$data['SNO']] = $data['TERRITORY'];
            }
        }
        return $territoryList;
    }

    private function storegridData() {
        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $this->measureFields[] = $this->settingVars->storetable . ".SNAME";
        $this->measureFields[] = $this->territoryLevel;
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }

        $this->queryPart = $this->getAll();

        $query = "SELECT " . $this->settingVars->maintable . ".SNO AS SNO, " .
                "MAX(SNAME) AS STORE, " .
                "MAX(" . $this->territoryLevel . ") AS TERRITORY " .
                "FROM " . $this->settingVars->tablename . " " . $this->queryPart .
                " GROUP BY SNO " .
                "ORDER BY STORE ASC";

        //echo $query;exit;
        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);

        $this->jsonOutput['storeList'] = $result;
    }

    private function storeData() {
        global $area, $munic;
        $query = "SELECT DISTINCT banner_alt,customAgg2 FROM " . $this->settingVars->geoHelperTables . $this->settingVars->geoHelperLink .
                " AND " . $this->settingVars->storetable . ".SNO = " . $_REQUEST['SNO'];
        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
        $area = (is_array($result) && !empty($result)) ? $result[0]['banner_alt'] : '';
        $munic = (is_array($result) && !empty($result)) ? $result[0]['customAgg2'] : '';


        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $this->measureFields[] = $this->settingVars->storetable . ".banner_alt";
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }

        $this->queryPart = $this->getAll(false);

        $querypart = $this->queryPart;
        $querypart.= " AND " . $this->settingVars->storetable . ".banner_alt='" . $area . "'";
        $areaData = array();
        $areaData = $this->getSalesForParameter($querypart, 'banner_alt');

        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $this->measureFields[] = $this->settingVars->storetable . ".customAgg2";
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }

        $this->queryPart = $this->getAll();

        $querypart = $this->queryPart;
        $querypart.= " AND " . $this->settingVars->storetable . ".customAgg2='" . $munic . "'";
        $municData = array();
        $municData = $this->getSalesForParameter($querypart, 'customAgg2');

        //$querypart = $this->queryPart;
        $sno = $_REQUEST['SNO'];
        $sname = $_REQUEST['STORE'];

        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }

        $this->queryPart = $this->getAll();

        if (filters\timeFilter::$ToYear == filters\timeFilter::$FromYear)
            $query = "  SELECT SUM( (CASE WHEN  " . filters\timeFilter::$tyWeekRange . " THEN 1 ELSE 0 END) * value ) AS TYEAR, " .
                    "SUM((CASE WHEN " . filters\timeFilter::$lyWeekRange . "  THEN 1 ELSE 0 END) * value) AS LYEAR " .
                    "FROM  " . $this->settingVars->tablename . " " . $this->queryPart . " " .
                    "ORDER BY TYEAR DESC ";
        else
            $query = "  SELECT SUM( (CASE WHEN  (" . filters\timeFilter::$tyWeekRange . ")  THEN 1 ELSE 0 END) * value ) as TYEAR, " .
                    "SUM( (CASE WHEN  ( " . filters\timeFilter::$lyWeekRange . ")  THEN 1 ELSE 0 END ) * value ) AS LYEAR " .
                    "FROM " . $this->settingVars->tablename . " " . $this->queryPart . " " .
                    "ORDER BY TYEAR DESC";
        //echo $query;exit;
        $value = array();
        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
        //if(!$result) {print $query; exit;}
        if (is_array($result) && !empty($result)) {
            foreach ($result as $key => $data) {
                $varp = 0.00;
                if ($data['LYEAR'] > 0)
                    $varp = ( ($data['TYEAR'] - $data['LYEAR']) / $data['LYEAR'] ) * 100;

                $value['SNO'] = $sno;
                $value['STORE'] = $sname;
                $value['AREA'] = $area;
                $value['MUNIC'] = $munic;
                $value['TERRITORY'] = $this->territory[$sno];
                $value['TYEAR'] = number_format($data['TYEAR'], 0, ".", ",");
                $value['LYEAR'] = number_format($data['LYEAR'], 0, ".", ",");
                $value['VARP'] = number_format($varp, 2, ".", ",");
                $value['AREA_VAR_PCT'] = number_format($areaData[$area], 2, ".", ",");
                $value['MUNIC_VAR_PCT'] = number_format($municData[$munic], 2, ".", ",");
            }
        }
        return $value;
    }

    private function storeCatData() {
        global $area, $munic;

        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $this->measureFields[] = $this->settingVars->storetable . ".banner_alt";
        $this->measureFields[] = $this->settingVars->skutable . ".agg2";
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }

        $this->queryPart = $this->getAll(false);

        // collecting area sales for each category // 
        $querypart = $this->queryPart;
        $querypart.= " AND " . $this->settingVars->storetable . ".banner_alt='" . $area . "'"; //filtering query, so that only selected area's data comes up
        $areaData = array();
        $areaData = $this->getSalesForParameter($querypart, 'agg2');

        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $this->measureFields[] = $this->settingVars->storetable . ".customAgg2";
        $this->measureFields[] = $this->settingVars->skutable . ".agg2";
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }

        $this->queryPart = $this->getAll();

        // collecting munic sales for each category //
        $querypart = $this->queryPart;
        $querypart.= " AND " . $this->settingVars->storetable . ".customAgg2='" . $munic . "'";  //filtering query, so that only selected municipalty's data comes up
        $municData = array();
        $municData = $this->getSalesForParameter($querypart, 'agg2');

        //$querypart = $this->queryPart;

        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $this->measureFields[] = $this->settingVars->skutable . ".agg2";
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }

        $this->queryPart = $this->getAll();

        $query = "  SELECT agg2 AS CATEGORY, " .
                "SUM( (CASE WHEN  " . filters\timeFilter::$tyWeekRange . " THEN 1 ELSE 0 END) * value ) AS TYEAR, " .
                "SUM((CASE WHEN " . filters\timeFilter::$lyWeekRange . "  THEN 1 ELSE 0 END) * value) AS LYEAR " .
                "FROM  " . $this->settingVars->tablename . " " . $this->queryPart . " AND agg2 NOT IN ('CHEESE', 'DIP', 'UNKNOWN') " .
                "GROUP BY CATEGORY " .
                "ORDER BY TYEAR DESC LIMIT 0,5";

        //echo $query;exit;
        $value = array();
        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
        //if(!$result) {print $query; exit;}
        if (is_array($result) && !empty($result)) {
            foreach ($result as $key => $data) {
                $varp = 0.00;
                if ($data['LYEAR'] > 0)
                    $varp = ( ($data['TYEAR'] - $data['LYEAR']) / $data['LYEAR'] ) * 100;

                $item = array();
                $item['CATEGORY'] = $data['CATEGORY'];
                $item['TYEAR'] = number_format($data['TYEAR'], 0, ".", ",");
                $item['LYEAR'] = number_format($data['LYEAR'], 0, ".", ",");
                $item['VARPCT'] = number_format($varp, 2, ".", ",");
                $item['AREA_VAR_PCT'] = number_format($areaData[$data['CATEGORY']], 2, ".", ",");
                $item['MUNIC_VAR_PCT'] = number_format($municData[$data['CATEGORY']], 2, ".", ",");

                array_push($value, $item);
            }
        }

        return $value;
    }

    private function topFiveSku() {
        global $area;
        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $this->measureFields[] = $this->settingVars->skutable . ".agg2";
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }

        $this->queryPart = $this->getAll();

        $query = "  SELECT " . $this->settingVars->maintable . ".PIN AS PIN, " .
                "PNAME AS PNAME, " .
                "SUM( (CASE WHEN  " . filters\timeFilter::$tyWeekRange . " THEN 1 ELSE 0 END) * value ) AS TYEAR, " .
                "SUM((CASE WHEN " . filters\timeFilter::$lyWeekRange . "  THEN 1 ELSE 0 END) * value) AS LYEAR " .
                "FROM  " . $this->settingVars->tablename . " " . $this->queryPart . 
                "GROUP BY PIN, PNAME " .
                "ORDER BY TYEAR DESC LIMIT 0,5";
                // AND agg2 NOT IN ('CHEESE', 'DIP', 'UNKNOWN')

        //echo $query;exit;
        $storage = array();
        $skuList = array();
        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
        //if(!$result) {print $query; exit;}
        if (is_array($result) && !empty($result)) {
            foreach ($result as $key => $data) {
                array_push($storage, $data);
                array_push($skuList, $data['PIN']);
            }
        }

        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $this->measureFields[] = $this->settingVars->storetable . ".banner_alt";
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }

        $this->queryPart = $this->getAll(false);

        $querypart = $this->queryPart;
        if (is_array($skuList) && !empty($skuList))
            $querypart.= " AND " . $this->settingVars->storetable . ".banner_alt='" . $area . "' AND " . $this->settingVars->maintable . ".PIN IN (" . implode(",", $skuList) . ") ";
        $areaData = array();
        $areaData = $this->getSalesForParameter($querypart, $this->settingVars->maintable . ".PIN");
        
        $value = array();
        if (is_array($storage) && !empty($storage)) {
            for ($i = 0; $i < count($storage); $i++) {
                $varp = 0.00;
                if ($storage[$i]['LYEAR'] > 0)
                    $varp = ( ($storage[$i]['TYEAR'] - $storage[$i]['LYEAR']) / $storage[$i]['LYEAR'] ) * 100;

                $item = array();
                $item['PIN'] = $storage[$i]['PIN'];
                $item['PNAME'] = $storage[$i]['PNAME'];
                $item['SALES'] = number_format($storage[$i]['TYEAR'], 0, ".", ",");
                $item['VARPCT'] = number_format($varp, 2, ".", ",");
                $item['AREA_VAR_PCT'] = number_format($areaData[$storage[$i]['PIN']], 2, ".", ",");
                $item['OVER_UNDER'] = number_format($varp - $areaData[$storage[$i]['PIN']], 2, ".", ",");

                array_push($value, $item);
            }
        }
        return $value;
    }

    private function bottomFiveSku() {
        global $area;

        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $this->measureFields[] = $this->settingVars->skutable . ".agg2";
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }

        $this->queryPart = $this->getAll();

        $query = "  SELECT " . $this->settingVars->maintable . ".PIN AS PIN, " .
                "MAX(PNAME) AS PNAME, " .
                "SUM( (CASE WHEN  " . filters\timeFilter::$tyWeekRange . " THEN 1 ELSE 0 END) * value ) AS TYEAR, " .
                "SUM((CASE WHEN " . filters\timeFilter::$lyWeekRange . "  THEN 1 ELSE 0 END) * value) AS LYEAR " .
                "FROM  " . $this->settingVars->tablename . " " . $this->queryPart . 
                "GROUP BY PIN " .
                "HAVING TYEAR>500 " .
                "ORDER BY TYEAR ASC LIMIT 0,5";
                // AND agg2 NOT IN ('CHEESE', 'DIP', 'UNKNOWN') " .

        //echo $query;exit;
        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
        $storage = array();
        $skuList = array();
        if (is_array($result) && !empty($result)) {
            foreach ($result as $key => $data) {
                array_push($storage, $data);
                array_push($skuList, $data['PIN']);
            }
        }

        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $this->measureFields[] = $this->settingVars->storetable . ".banner_alt";
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }

        $this->queryPart = $this->getAll(false);

        $querypart = $this->queryPart;
        if (is_array($skuList) && !empty($skuList))
            $querypart.= " AND " . $this->settingVars->storetable . ".banner_alt='" . $area . "' AND " . $this->settingVars->maintable . ".PIN IN (" . implode(",", $skuList) . ") ";

        $areaData = array();

        $areaData = $this->getSalesForParameter($querypart, $this->settingVars->maintable . ".PIN");

        $value = array();

        if (is_array($result) && !empty($result)) {
            for ($i = 0; $i < count($storage); $i++) {
                $varp = 0.00;
                if ($storage[$i]['LYEAR'] > 0)
                    $varp = ( ($storage[$i]['TYEAR'] - $storage[$i]['LYEAR']) / $storage[$i]['LYEAR'] ) * 100;

                $item = array();
                $item['PIN'] = $storage[$i]['PIN'];
                $item['PNAME'] = $storage[$i]['PNAME'];
                $item['SALES'] = number_format($storage[$i]['TYEAR'], 0, ".", ",");
                $item['VARPCT'] = number_format($varp, 2, ".", ",");
                $item['AREA_VAR_PCT'] = number_format($areaData[$storage[$i]['PIN']], 2, ".", ",");
                $item['OVER_UNDER'] = number_format($varp - $areaData[$storage[$i]['PIN']], 2, ".", ",");

                array_push($value, $item);
            }
        }
        return array_reverse($value);
    }

    private function getSalesForParameter($querypart, $parameter) {

        $query = "  SELECT $parameter AS ACCOUNT, " .
                "SUM( (CASE WHEN  " . filters\timeFilter::$tyWeekRange . " THEN 1 ELSE 0 END) * value ) AS TYEAR, " .
                "SUM((CASE WHEN " . filters\timeFilter::$lyWeekRange . "  THEN 1 ELSE 0 END) * value) AS LYEAR " .
                "FROM  " . $this->settingVars->tablename . " $querypart " .
                "GROUP BY ACCOUNT " .
                "ORDER BY ACCOUNT ASC ";

        //echo $query;exit;
        $value = array();
        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
        //if(!$result) {print $query; exit;}
        if (is_array($result) && !empty($result)) {
            foreach ($result as $key => $data) {
                $varp = 0.00;
                if ($data['LYEAR'] > 0)
                    $varp = ( ($data['TYEAR'] - $data['LYEAR']) / $data['LYEAR'] ) * 100;
                $value[$data['ACCOUNT']] = number_format($varp, 2, ".", ",");
            }
        }
        return $value;
    }

    /*     * ***
     * COLLECTS TABLE JOINING STRING FROM PROJECT SETTINGS
     * ALSO APPLYS FILTER CONDITIONS TO THE STRING
     * AND RETURNS $tablejoins_and_filters
     * *** */

    public function getAll($includeSno = true) {
        $extraFields = array();

        $tablejoins_and_filters = "";


        if ($_REQUEST['Territory'] != "") {
            $tablejoins_and_filters.= " AND " . $this->territoryLevel . " = '" . $_REQUEST['Territory'] . "'";
            $extraFields[] = $this->territoryLevel;
        }

        if($includeSno)
        {
            if ($_REQUEST['SNO'] != "") {
                $tablejoins_and_filters.= " AND " . $this->settingVars->maintable . ".SNO = " . $_REQUEST['SNO'];
            }
        }

        $tablejoins_and_filters.= " AND (" . filters\timeFilter::$tyWeekRange . " OR " . filters\timeFilter::$lyWeekRange . " ) ";

        /* if(filters\timeFilter::$ToYear==filters\timeFilter::$FromYear)
          {
          $tablejoins_and_filters.= " AND (".$this->settingVars->weekperiod.">=".filters\timeFilter::$FromWeek." OR ".$this->settingVars->weekperiod."<=".filters\timeFilter::$ToWeek.")  AND ".$this->settingVars->yearperiod." IN (" . filters\timeFilter::$ToYear . "," . (filters\timeFilter::$FromYear - 1) . ")";
          }
          else
          {
          $tablejoins_and_filters.= " AND (".$this->settingVars->weekperiod.">=".filters\timeFilter::$FromWeek." AND ".$this->settingVars->weekperiod."<=".filters\timeFilter::$ToWeek.")  AND ".$this->settingVars->yearperiod." IN(" . filters\timeFilter::$ToYear . "," . (filters\timeFilter::$FromYear - 1) . ")";
          } */


        $this->prepareTablesUsedForQuery($extraFields);
        $tablejoins_and_filters1 = parent::getAll();
        $tablejoins_and_filters1 .= $tablejoins_and_filters;

        return $tablejoins_and_filters1;
    }

}

?>