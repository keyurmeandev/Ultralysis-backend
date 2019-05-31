<?php

namespace classes;

use db;
use filters;
use config;

class SingleSalesCheckWeekly extends config\UlConfig {

    private $skuID, $skuName, $storeID, $storeName, $pageName;

    public function go($settingVars) {
        $this->initiate($settingVars);
        
        $this->pageName = $_REQUEST["pageName"];

        $this->settingVars->pageName = $this->pageName; // set pageName in settingVars
        $this->skuID = $this->settingVars->dataArray[$this->settingVars->pageArray[$this->pageName]["SKU_ACCOUNT"]]['ID'];
        $this->skuName = $this->settingVars->dataArray[$this->settingVars->pageArray[$this->pageName]["SKU_ACCOUNT"]]['NAME'];
        $this->storeID = $this->settingVars->dataArray[$this->settingVars->pageArray[$this->pageName]["STORE_ACCOUNT"]]['ID'];
        $this->storeName = $this->settingVars->dataArray[$this->settingVars->pageArray[$this->pageName]["STORE_ACCOUNT"]]['NAME'];
        
        $this->queryPart = $this->getAll();
        $this->queryCustomPart = $this->getAllCustom();
        
        $action = $_REQUEST["action"];
        switch ($action) {
            case "changesku": $this->changesku();
                break;
            case "reload": $this->reload();
                break;
            default:
				$this->form();
				$this->reload();
        }
        
        return $this->jsonOutput;
    }

    private function changesku() {        
        $this->prepareChartData();  //ADDING TO OUTPUT 
        
    }

    private function reload() {
        $this->prepareGridData();    //ADDING TO OUTPUT
        $this->prepareChartData();   //ADDING TO OUTPUT 

    }

    private function form() {
        $this->getSkuList();      //ADDING TO OUTPUT  
        $this->getStoreList();     //ADDINT TO OUTPUT

    }

    private function getSkuList() {
		$querypart = $this->queryPart;
		$querypart .= " AND " .  $this->settingVars->maintable.".".$this->settingVars->period . " IN (" . implode(",", filters\timeFilter::getPeriodWithinRange(0, 12, $this->settingVars)) . ") ";
        $query = "SELECT " . $this->skuID .
                ",MAX(" . $this->skuName . ") skuname " .
                "FROM " . $this->settingVars->tablename . $querypart . " " .
                "GROUP BY 1 " .
                "ORDER BY 2";
		//echo $query;exit;
        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_ARRAY);

        $temp = array();
        $temp['data'] = '';
        $temp['label'] = 'NONE';
        $this->jsonOutput['skulist'][] = $temp;

        foreach ($result as $key => $data) { 

            $temp = array();
            $temp['data'] = $data[0];
            $temp['label'] = htmlspecialchars_decode($data[1] . "  [" . $data[0] . "]");
            $this->jsonOutput['skulist'][] = $temp;
        }
        
    }

    private function getStoreList() {
		
		$querypart = $this->queryPart;
		$querypart .= " AND " .  $this->settingVars->maintable.".".$this->settingVars->period . " IN (" . implode(",", filters\timeFilter::getPeriodWithinRange(0, 12, $this->settingVars)) . ") ";
		
        $query = "SELECT " . $this->storeID .
                ",MAX(" . $this->storeName . ")sname " .
                "FROM " . $this->settingVars->tablename . $querypart . " " .
                "AND " . $this->storeID . "<>0 " .
                "GROUP BY 1 " .
                "ORDER BY 2";
        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_ARRAY);

        $temp = array();
        $temp['data'] = '';
        $temp['label'] = 'NONE';
        $this->jsonOutput['storelist'][] = $temp;
        foreach ($result as $key => $data) {
            $temp = array();
            $temp['data'] = $data[0];
            $temp['label'] = $data[1];
            $this->jsonOutput['storelist'][] = $temp;
        }        
    }

    private function prepareChartData() {
        $query = "SELECT " . $this->settingVars->maintable.".".$this->settingVars->period .
                ",SUM(GSQ)" .
                ",SUM(" . $this->settingVars->ProjectVolume . ")" .
                ",SUM(OHQ)" .
                ",SUM(MSQ)" .
                ",SUM(On_Hand_Adj_Qty)" .
                ",SUM(Backroom_Adj_Qty) " .
                "FROM " . $this->settingVars->tablename . $this->queryCustomPart . " " .
                "GROUP BY 1 " .
                "ORDER BY 1 ASC ";
		//echo $query;exit;
        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_ARRAY);
        foreach ($result as $key => $data) {
            $temp = array();
            $temp['account'] = $data[0];
            $temp['GSQ'] = $data[1];
            $temp['QTY'] = $data[2];
            $temp['HOQ'] = $data[3];
            $temp['MSQ'] = $data[4];
            $temp['HQ'] = $data[5];
            $temp['BQ'] = $data[6];
            $this->jsonOutput['chartValue'][] = $temp;
        }
    }

    private function prepareGridData() {
        $query = "SELECT MAX(" . $this->settingVars->period . ") FROM " . $this->settingVars->maintable;
        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_ARRAY);
        $data = $result[0];
        $lastestperiod = $data[0];


        $query="SELECT ".$this->skuID.
		    ",MAX(".$this->skuName.")skuname".
		    ",".$this->storeID.
		    ",MAX(".$this->storeName.")".
		    ",SUM((CASE WHEN ".$this->settingVars->maintable.".".$this->settingVars->period."='".$lastestperiod."' THEN 1 END)*OHQ  ) ASDF".
		    ",SUM((CASE WHEN ".$this->settingVars->maintable.".".$this->settingVars->period."='".$lastestperiod."' THEN 1 ELSE 0 END)*MSQ) fdsa".
		    ",SUM(".$this->settingVars->ProjectVolume.") AS qty ".
		    "FROM ".$this->settingVars->tablename.$this->queryCustomPart." ".
		    "GROUP BY 1,3 ".
		    "ORDER BY qty DESC ".
		    "LIMIT 0,5"; 
		//echo $query;exit;
        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_ARRAY);
        foreach ($result as $key => $data) {
            $temp = array();
            $temp['SKU'] = $data[0];
            $temp['SKUNAME'] = htmlspecialchars_decode($data[1]);
            $temp['STORE'] = $data[2];
            $temp['STORENAME'] = htmlspecialchars_decode($data[3]);
            $temp['CURONHAND'] = $data[4];
            $temp['MAXSHELF'] = $data[5];
            $temp['QTY'] = $data[6];
            $this->jsonOutput['gridValue'][] = $temp;
        }
    }

    /*     * * OVERRIDING PARENT CLASS'S getAll FUNCTION ** */

    public function getAllCustom() {

        $tablejoins_and_filters = parent::getAll();

        if ($_REQUEST["STORE"] != "")
            $tablejoins_and_filters .= " AND " . $this->storeID . "=" . $_REQUEST['STORE'];

        if ($_REQUEST["SKU"] != "")
            $tablejoins_and_filters .= " AND " . $this->skuID . "=" . $_REQUEST['SKU'];

        $tablejoins_and_filters .= " AND " .  $this->settingVars->maintable.".".$this->settingVars->period . " IN (" . implode(",", filters\timeFilter::getPeriodWithinRange(0, 12, $this->settingVars)) . ") ";

        return $tablejoins_and_filters;
    }

}
