<?php

namespace classes;

use db;
use filters;
use config;

class ItemFile extends config\UlConfig {

    public function go($settingVars) {
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES
        $this->queryPart = $this->getAll();

        $this->valueLatestPeriod(); //ADDING TO OUTPUT
        $this->loadItemType();
        $this->gridValue($querypart);   //ADDING TO OUTPUT

        return $this->jsonOutput;
    }

    private function loadItemType() {
        $query = "SELECT DISTINCT ".$this->settingVars->itemType." " .
                "FROM " . $this->settingVars->maintable . " " .
                "GROUP BY ".$this->settingVars->itemType." " .
                "ORDER BY ".$this->settingVars->itemType." DESC";
        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_ARRAY);

        $temp = array(
            'id' => 'All'
        );
        $this->jsonOutput['ITList'][] = $temp;

        foreach ($result as $key => $data) {
            $temp = array(
                'id' => htmlspecialchars_decode($data[0])
            );
            $this->jsonOutput['ITList'][] = $temp;
        }
    }

    private function valueLatestPeriod() {
        //PROVIDING GLOBALS
        global $LWList;
        $LWList = array();

        $qpart = $this->queryPart . " AND " . $this->settingVars->maintable . "." . $this->settingVars->period . " IN (" . implode(",", filters\timeFilter::getPeriodWithinRange(0, 1, $this->settingVars)) . ") ";
        $query = "SELECT skuID_ROLLUP2" .
                ",WHPK_Qty" .
                ",MSQ" .
                ",COUNT(DISTINCT " . $this->settingVars->storetable . ".SNO) " .
                "FROM " . $this->settingVars->tablename . $qpart . " " .
                "GROUP BY skuID_ROLLUP2,WHPK_Qty,MSQ";
        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_ARRAY);
        foreach ($result as $key => $data) {
            $sin = $data[0];
            $whpk = $data[1];
            $msq = $data[2];
            $LWList[$sin][$whpk][$msq] = $data[3];
        }
    }

    private function gridValue() {
        global $LWList; //SET BY valueLatestPeriod
        $qpart = $this->queryPart . " AND " . $this->settingVars->maintable . "." . $this->settingVars->period . " IN (" . implode(",", filters\timeFilter::getPeriodWithinRange(0, 1, $this->settingVars)) . ") ";
        $query = "SELECT skuID_ROLLUP2 AS SIN" .
                ",MAX(SKU_ROLLUP2) AS SKU" .
                ",MAX(".$this->settingVars->itemType.") AS ITEM_TYPE" .
                ",MAX(".$this->settingVars->effectiveDate.") AS EFFECTIVE_DATE" .
                ",MAX(".$this->settingVars->obsoleteDate.") AS OBSLT_DATE" .
                ",WHPK_Qty AS WQ" .
                ",MSQ AS MSQ" .
                ",MAX(ITEM_STATUS) AS STATUS" .
                ",MAX(Order_Book_Flag) AS OBF " .
                "FROM " . $this->settingVars->tablename . $qpart . " " .
                "GROUP BY skuID_ROLLUP2,WHPK_Qty,MSQ";
        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
        foreach ($result as $key => $data) {
            $sin = $data['SIN'];
            $whpk = $data['WQ'];
            $msq = $data['MSQ'];

            $temp = array(
                'SIN' => $data['SIN'],
                'SKU' => htmlspecialchars_decode($data['SKU']),
                'ITEMTYPE' => $data['ITEM_TYPE'],
                'EDATE' => $data['EFFECTIVE_DATE'],
                'ODATE' => $data['OBSLT_DATE'],
                'WQ' => $data['WQ'],
                'MSQ' => $data['MSQ'],
                'IS' => $data['STATUS'],
                'OBF' => $data['OBF'],
                'CI' => $LWList[$sin][$whpk][$msq]
            );
            $this->jsonOutput['gridValue'][] = $temp;
        }
    }

    /*     * * OVERRIDING PARENT CLASS'S getAll FUNCTION ** */

    public function getAll() {
        $tablejoins_and_filters = parent::getAll();

        /* if (isset($_REQUEST["FS"]) && $_REQUEST["FS"] != '') {
            $tablejoins_and_filters .= filters\productAndMarketFilter::include_product_and_market_filters($this->queryVars, $this->settingVars);
        } */

        if ($_REQUEST['IT'] != "All" && $_REQUEST['IT'] != "")
            $tablejoins_and_filters .=" and ItemType=" . $_REQUEST['IT'];
        if ($_REQUEST['IS'] != "")
            $tablejoins_and_filters .=" and ITEM_STATUS='" . $_REQUEST['IS'] . "'";
        if ($_REQUEST['OBF'] != "")
            $tablejoins_and_filters .=" and Order_Book_Flag='" . $_REQUEST['OBF'] . "'";

        return $tablejoins_and_filters;
    }

}

?> 