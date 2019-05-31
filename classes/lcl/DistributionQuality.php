<?php

namespace classes\lcl;

use db;
use filters;
use config;

class DistributionQuality extends config\UlConfig {

    private $timeFrame;
    private $accountField;
    public $dbColumnsArray;
    public $displayCsvNameArray;

    /*     * ***
     * Default gateway function, should be similar in all DATA CLASS
     * arguments:
     *  $settingVars [project settingsGateway variables]
     * @return $jsonOutput with all data that should be sent to client app
     * *** */

    public function go($settingVars) {
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES
		$this->redisCache = new \utils\RedisCache($this->queryVars);
        $this->settingVars->pageName = (empty($this->settingVars->pageName)) ? $this->settingVars->pageID . '_DistributionQualityPage' : $this->settingVars->pageName;
        $this->ValueVolume = getValueVolume($this->settingVars);

        if ($this->settingVars->isDynamicPage) {
            $this->accountField = $this->getPageConfiguration('account_field', $this->settingVars->pageID)[0];
            $this->buildDataArray(array($this->accountField));
            $this->buildPageArray();
        } else {
            if (!isset($this->settingVars->pageArray[$this->settingVars->pageName]) || empty($this->settingVars->pageArray[$this->settingVars->pageName]))
                $this->configurationFailureMessage();

            $this->accountID = (isset($this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['ACCOUNT']]) && isset($this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['ACCOUNT']]['ID'])) ? $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['ACCOUNT']]['ID'] : $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['ACCOUNT']]['NAME'];
            $this->accountName = $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['ACCOUNT']]['NAME'];
            $this->jsonOutput["GRID_COLUMN_NAME"] = ($this->settingVars->pageArray[$this->settingVars->pageName]["GRID_COLUMN_NAME"] == null) ? array() : $this->settingVars->pageArray[$this->settingVars->pageName]["GRID_COLUMN_NAME"];
        }

        if (isset($_REQUEST["timeFrame"]) && !empty($_REQUEST["timeFrame"]))
            $this->timeFrame = $_REQUEST["timeFrame"];
        else
            $this->timeFrame = 12; //$this->settingVars->pageArray["DISTRIBUTION_QUALITY"]["DEFAULT_TIME_FRAME"];

        filters\timeFilter::calculate_Ty_And_Ly_WeekRange_From_TimeFrame($this->timeFrame, $this->settingVars);
        $this->queryPart = $this->getAll();
        
        if (!isset($_REQUEST["fetchConfig"]) && empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] != 'true') {
            $this->prepareGridData(); //ADDING TO OUTPUT
        }

        return $this->jsonOutput;
    }

    private function prepareGridData() {

        $this->getGridData($this->accountID, $this->accountName, "gridData"); //ADDING TO OUTPUT   

        return $this->jsonOutput;
    }

    private function getGridData($id, $name, $xmlTag) {
        global $ohqArr;
        
        //$this->settingVars->tableUsedForQuery = $this->measureFields = array();
        /*$this->measureFields = $this->prepareTablesUsedForMeasure($_REQUEST['ValueVolume']);
        $this->measureFields[] = $this->settingVars->maintable . ".SNO";
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->queryPart = $this->getAll();
        
        $query = "SELECT COUNT(DISTINCT (CASE WHEN " . $this->settingVars->ProjectVolume . ">0 THEN 1 END) * " . $this->settingVars->maintable . ".SNO) AS STORES " .
                "FROM " . $this->settingVars->tablename . $this->queryPart . " " .
                "AND " . filters\timeFilter::$tyWeekRange;
        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);

        //print_r($result); exit;
        $data = $result[0];
        $totalStores = $data['STORES'];*/

        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $measureSelectRes    = $this->prepareMeasureSelectPart();
        $this->measureFields = $measureSelectRes['measureFields'];
        $measureSelectionArr = $measureSelectRes['measureSelectionArr'];
        $havingTYValue       = $measureSelectRes['havingTYValue'];
        $havingLYValue       = $measureSelectRes['havingLYValue'];

        //$this->settingVars->tableUsedForQuery = $this->measureFields = array();
        //$this->measureFields = $this->prepareTablesUsedForMeasure($_REQUEST['ValueVolume']);
        $this->measureFields[] = $id;
        $this->measureFields[] = $name;
        $this->measureFields[] = $this->settingVars->storetable . ".SNO";
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->queryPart = $this->getAll();

        $options = array();
        /*if (!empty(filters\timeFilter::$tyWeekRange))
            $options['tyLyRange']['SALES'] = filters\timeFilter::$tyWeekRange;
        $measureSelect = config\MeasureConfigure::prepareSelect($this->settingVars, $this->queryVars, array('M'.$_REQUEST['ValueVolume']), $options);
        $measureSelect = implode(", ", $measureSelect);*/

        $measureSelect = implode(", ", $measureSelectionArr);
        $query = "SELECT $id AS ID" .
                ",$name AS ACCOUNT" .
                ",(CASE WHEN " . $this->settingVars->ProjectVolume . "> 0 THEN 1 ELSE 0 END) * " . $this->settingVars->storetable . ".SNO AS STORES" .
                " , ".$measureSelect." ".
                "FROM  " . $this->settingVars->tablename . $this->queryPart . " AND " . filters\timeFilter::$tyWeekRange . " " .
                "GROUP BY ID,ACCOUNT,STORES ";
                /*"ORDER BY SALES DESC";*/

		$redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }

		$requiredGridFields = ['ID','ACCOUNT','STORES',$havingTYValue];
        $result = $this->redisCache->getRequiredData($result, $requiredGridFields, $havingTYValue, $havingTYValue, $havingTYValue);

        $fnGpArr = $allStores = $tempResult = array();
        foreach ($result as $key => $data) {
        		$tmpky = $data['ID'].str_replace("'",'_',str_replace('"','_',str_replace(' ', '', $data['ACCOUNT'])));

	        	if ($id != "")
	        		$fnGpArr[$tmpky]["ID"] 		= $data['ID'];
	        	
	        	$fnGpArr[$tmpky]["ACCOUNT"] = htmlspecialchars_decode($data['ACCOUNT']);
	        	$fnGpArr[$tmpky]["salesLw"] += $data[$havingTYValue];

                if($data['STORES']!=0)
				    $fnGpArr[$tmpky]['STORES'][$data['STORES']] = $data['STORES'];

        		$fnGpArr[$tmpky]['noOfStores'] = isset($fnGpArr[$tmpky]['STORES']) ? count($fnGpArr[$tmpky]['STORES']) : 0;
				$fnGpArr[$tmpky]['aveSales']   = $fnGpArr[$tmpky]['noOfStores'] > 0 ? (($fnGpArr[$tmpky]["salesLw"] / $fnGpArr[$tmpky]['noOfStores']) / $this->timeFrame) : 0;

        	if(!empty($data['STORES']))
        		$allStores[$data['STORES']] = $data['STORES'];
        }

        $totalStores = count($allStores);
        $fnGpArr = \utils\SortUtility::sort2DArray($fnGpArr, 'salesLw', \utils\SortTypes::$SORT_DESCENDING);
		$this->jsonOutput[$xmlTag] = array_values($fnGpArr);
        $tempData = array(
            'total_stores' => $totalStores
        );
        $this->jsonOutput['summary'] = $tempData;
    }

    public function buildPageArray() {

        $fetchConfig = false;
        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
            $fetchConfig = true;
            $this->jsonOutput['pageConfig'] = array(
                'enabledFilters' => $this->getPageConfiguration('filter_settings', $this->settingVars->pageID)
            );
        }

        $accountFieldPart = explode("#", $this->accountField);
        $accountLabel = $accountFieldPart[0];
        $idLabel = (count($accountFieldPart) > 1) ? $accountFieldPart[1] : 'ID';

        $this->jsonOutput["GRID_COLUMN_NAME"] = array("ID" => array("title" => $this->displayCsvNameArray[$idLabel]), "ACCOUNT" => array("title" => $this->displayCsvNameArray[$accountLabel]));

        $accountField = strtoupper($this->dbColumnsArray[$accountFieldPart[0]]);
        $accountField = (count($accountFieldPart) > 1) ? strtoupper($accountField . "_" . $this->dbColumnsArray[$accountFieldPart[1]]) : $accountField;

        $this->accountID = (isset($this->settingVars->dataArray[$accountField]) && isset($this->settingVars->dataArray[$accountField]['ID'])) ? $this->settingVars->dataArray[$accountField]['ID'] : $this->settingVars->dataArray[$accountField]['NAME'];
        $this->accountName = $this->settingVars->dataArray[$accountField]['NAME'];

        return;
    }

    public function buildDataArray($fields) {
        if (empty($fields))
            return false;

        $configurationCheck = new config\ConfigurationCheck($this->settingVars, $this->queryVars);
        $configurationCheck->buildDataArray($fields);
        $this->dbColumnsArray = $configurationCheck->dbColumnsArray;
        $this->displayCsvNameArray = $configurationCheck->displayCsvNameArray;
        return;
    }
}
?>