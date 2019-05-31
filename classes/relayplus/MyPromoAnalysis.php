<?php
namespace classes\relayplus;
ini_set("memory_limit", "1024M");

use datahelper;
use projectsettings;
use filters;
use db;
use config;
use utils;

class MyPromoAnalysis extends config\UlConfig{

    private $prePromoTyWeekRange;
    private $promoTyWeekRange;
    private $postPromoTyWeekRange;
    
    private $totalPrePromoWeek;
    private $totalPromoWeek;
    private $totalPostPromoWeek;
    
    private $aggregateSku;


    /**
     * Default gateway function, should be similar in all DATA CLASS
     * 
     * @param array $settingVars project settingsGateway variables
     *
     * @return Array
     */
	public function go($settingVars)
	{
		$this->initiate($settingVars); //INITIATE COMMON VARIABLES

		$this->settingVars->pageName = (empty($this->settingVars->pageName) && !empty($this->settingVars->pageID)) ? $this->settingVars->pageID.'_PromoAnalysisPage' : $this->settingVars->pageName;

        if ($this->settingVars->isDynamicPage) {
			$this->accountField = $this->getPageConfiguration('account_field', $this->settingVars->pageID)[0];
			$this->buildDataArray(array($this->accountField));
			$this->buildPageArray();
		} else {
			$this->accountName = $this->settingVars->dataArray[$_REQUEST["ACCOUNT"]]["NAME"];
			$this->accountID = key_exists('ID',$this->settingVars->dataArray[$_REQUEST["ACCOUNT"]]) ? $this->settingVars->dataArray[$_REQUEST["ACCOUNT"]]['ID'] : $this->settingVars->dataArray[$_REQUEST["ACCOUNT"]]['NAME'];	
		}
		
		$this->queryPart = $this->getAll();  //PREPARE TABLE JOIN STRING USING this class getAll
		$this->aggregateSku = $_REQUEST['aggregateSku'];

		$action = $_REQUEST['action'];
		switch ($action) {
			case "getConfig":
				$this->getSkuSelectionTab();
				break;
			case "filterChange":
				//PREPARING PROMO,PRE-PROMO,POST-PROMO WEEK RANGE
				$this->prepareWeekRanges();	    
				//PREPARING CHART DATA
				$this->promoProductLineChart();
				//PREPARING GRID DATA
				$this->promoTopGrid();			
				break;
		}
		
		return $this->jsonOutput;
	}

	public function getSkuSelectionTab()
	{
		$fieldPart = explode("#", $this->accountField);
        $field = strtoupper($this->dbColumnsArray[$fieldPart[0]]);
        $field = (count($fieldPart) > 1) ? strtoupper($field . "_" . $this->dbColumnsArray[$fieldPart[1]]) : $field;

		if(isset($this->settingVars->dataArray[$field]))
		{
			$id 		= $this->settingVars->dataArray[$field]['ID'];
			$idAlias 	= $this->settingVars->dataArray[$field]['ID_ALIASE'];
			$name		= $this->settingVars->dataArray[$field]['NAME'];
			$nameAlias	= $this->settingVars->dataArray[$field]['NAME_ALIASE'];
			$tableName  = $this->settingVars->dataArray[$field]['tablename'];
			$link  		= $this->settingVars->dataArray[$field]['link'];
			$csvName  	= $this->settingVars->dataArray[$field]['NAME_CSV'];
			
			$query = "SELECT DISTINCT $id as $idAlias, $name as $nameAlias FROM $tableName $link";
			$result = $this->queryVars->queryHandler->runQuery($query,$this->queryVars->linkid,db\ResultTypes::$TYPE_OBJECT);
			
			$productSelectionTabs = array();
			if(is_array($result) && !empty($result))
			{
				$name = explode(".", $name);
				$productSelectionTabs['data'] = $name[1];
				
				$id = explode(".", $id);
				
				$datalist = array();
			
				foreach($result as $key => $data)
				{
					$datalist[$key] = array('data' => $data[$nameAlias], 'label' => $data[$nameAlias].($id[1] != "" ? " (".$data[$idAlias].")" : ""), 'label_hash_secondary' => $data[$nameAlias]." #");
				}
				$productSelectionTabs['dataList'] 				= $datalist;
				$productSelectionTabs['indexName'] 				= "FS[".$name[1]."]";
				$productSelectionTabs['label'] 					= $csvName;
				$productSelectionTabs['selected'] 				= true;
				$productSelectionTabs['selectedDataList'] 		= array();
				$productSelectionTabs['selectedItemLeft'] 		= "selected".$name[1]."Left";
				$productSelectionTabs['selectedItemRight'] 		= "selected".$name[1]."Left";
				
				$this->jsonOutput[$name[1]] = $datalist;
			}
			
			$this->jsonOutput['skuSelectionTabs'] = array($productSelectionTabs);
		}
	}
	
	/**
     * promoTopGrid()
     * It will 
     * 
     * @param String $name To set SKU_name or SKURollup_name
     *
     * @return void
     */
	private function promoTopGrid()
	{
	    $query = "SELECT $this->accountName as SKU_NAME ".
		    ",SUM((CASE WHEN ".$this->prePromoTyWeekRange." THEN 1 ELSE 0 END)* ".$this->ValueVolume." ) AS PRE_PROMO_SALES ".
		    ",SUM((CASE WHEN ".$this->promoTyWeekRange." THEN 1 ELSE 0 END)* ".$this->ValueVolume." ) AS PROMO_SALES ".
		    ",SUM((CASE WHEN ".$this->postPromoTyWeekRange." THEN 1 ELSE 0 END)* ".$this->ValueVolume." ) AS POST_PROMO_SALES ".
		    "FROM ".$this->settingVars->tablename.$this->queryPart.
		    " GROUP BY SKU_NAME";
		//echo $query;exit;
	    $result = $this->queryVars->queryHandler->runQuery($query,$this->queryVars->linkid,db\ResultTypes::$TYPE_OBJECT);
	    $totalPromoWros = $totalPrePromoWros = $totalPostPromoWros = 0;
	    
	    $tempGridArr = array();

	    if (is_array($result) && !empty($result)) {

		    foreach($result as $key=>$row) {
				$temp=array();
				$temp['SKU_NAME']     		= $row['SKU_NAME'];
				$temp['PRE_WROS']   		= $this->totalPrePromoWeek>0?number_format($row['PRE_PROMO_SALES']/$this->totalPrePromoWeek,0):0;
				$temp['PROMO_WROS'] 		= $this->totalPromoWeek>0?number_format($row['PROMO_SALES']/$this->totalPromoWeek,0):0;			 
				$temp['POST_WROS']  		= $this->totalPostPromoWeek>0?number_format($row['POST_PROMO_SALES']/$this->totalPostPromoWeek,0):0;
				
				$preWros = $this->totalPrePromoWeek>0?round($row['PRE_PROMO_SALES']/$this->totalPrePromoWeek):0;
				$promoWros = $this->totalPromoWeek>0?round($row['PROMO_SALES']/$this->totalPromoWeek):0;
				$postWros = $this->totalPostPromoWeek>0?round($row['POST_PROMO_SALES']/$this->totalPostPromoWeek):0;
				
				$totalPrePromoWros += $preWros;
				$totalPromoWros += $promoWros;
				$totalPostPromoWros += $postWros;

				$temp['UPLIFT']			= $preWros>0?number_format((($promoWros/$preWros)-1)*100,1,'.',''):0;
				$temp['POST_PRE_UPLIFT']	= $preWros>0?number_format((($postWros/$preWros)-1)*100,1,'.',''):0;

				$tempGridArr[] 			= $temp;
		    }

			$tempGridArr = utils\SortUtility::sort2DArray($tempGridArr,'PRE_WROS',utils\SortTypes::$SORT_DESCENDING);
		    $totalRow = array(
		    	'SKU_NAME' => 'TOTAL',
		    	'PRE_WROS' => number_format($totalPrePromoWros, 0),
		    	'POST_WROS' => number_format($totalPostPromoWros, 0),
		    	'PROMO_WROS' => number_format($totalPromoWros, 0),
		    	'UPLIFT' => ($totalPrePromoWros > 0) ? number_format((($totalPromoWros/$totalPrePromoWros)-1)*100,1,'.','') : 0,
		    	'POST_PRE_UPLIFT' => ($totalPrePromoWros > 0) ? number_format((($totalPostPromoWros/$totalPrePromoWros)-1)*100,1,'.','') : 0
			);
			$tempGridArr[] 			= $totalRow;
		}
	    
	    $this->jsonOutput['promoTopGrid'] = $tempGridArr;
	}
    
    /**
     * promoTopGrid()
     * It will prepare pre,post and current promo week ranges
     *
     * @return void
     */
	private function prepareWeekRanges() {
		$prePromoFrom   		= $_REQUEST['prePromoFrom'];
		$prePromoTo 			= $_REQUEST['prePromoTo'];
		$_REQUEST['FromWeek'] 	= $prePromoFrom;
		$_REQUEST['ToWeek'] 	= $prePromoTo;
		
		filters\timeFilter::getSlice($this->settingVars);
		$this->prePromoTyWeekRange 	= filters\timeFilter::$tyWeekRange;

		$promoFrom 				= $_REQUEST['promoFrom'];
		$promoTo 				= $_REQUEST['promoTo'];
		$_REQUEST['FromWeek'] 	= $promoFrom;
		$_REQUEST['ToWeek']   	= $promoTo;
		
		filters\timeFilter::getSlice($this->settingVars);
		$this->promoTyWeekRange = filters\timeFilter::$tyWeekRange;

		$postPromoFrom 			= $_REQUEST['postPromoFrom'];
		$postPromoTo 			= $_REQUEST['postPromoTo'];
		$_REQUEST['FromWeek'] 	= $postPromoFrom;
		$_REQUEST['ToWeek'] 	= $postPromoTo;
		
		filters\timeFilter::getSlice($this->settingVars);
		$this->postPromoTyWeekRange = filters\timeFilter::$tyWeekRange;
		
		
		// SET WEEK COUNT
		$countWeek = array();
		$this->totalPrePromoWeek 		= $this->getTotalWeekByWeekRange($this->prePromoTyWeekRange);
		$this->totalPromoWeek 			= $this->getTotalWeekByWeekRange($this->promoTyWeekRange);
		$this->totalPostPromoWeek 		= $this->getTotalWeekByWeekRange($this->postPromoTyWeekRange);
		
		$countWeek['PRE_PROMO'] 		= $this->totalPrePromoWeek;
		$countWeek['PROMO'] 			= $this->totalPromoWeek;
		$countWeek['POST_PROMO'] 		= $this->totalPostPromoWeek;
		$this->jsonOutput['CountWeek'] 	= $countWeek;
	}

	/**
     * getTotalWeekByWeekRange()
     * It will prepare total weeks as per week range
     *
     * @return numeric
     */
	private function getTotalWeekByWeekRange($weekRange)
	{
	    $query = "select count(distinct ".$this->settingVars->weekperiod.") as TOTAL_WEEK from ".$this->settingVars->timetable." where ".$weekRange;
	    $result = $this->queryVars->queryHandler->runQuery($query,$this->queryVars->linkid,db\ResultTypes::$TYPE_OBJECT);
	    return $result[0]['TOTAL_WEEK'];
	}

	/**
     * promoProductLineChart()
     * It will prepare data for line chart as per filter requested
     *
     * @return void
     */
	private function promoProductLineChart()
	{
		$skulistArr =  '';//explode(',',$_REQUEST['SKU']);
/* 		if(!empty($_REQUEST['FS']['F3']))
			$skulistArr =  $_REQUEST['FS']['F3'];
		elseif(!empty($_REQUEST['FS']['F4']))
			$skulistArr =  $_REQUEST['FS']['F4']; */
			
		$fieldPart = explode("#", $this->accountField);
        $field = strtoupper($this->dbColumnsArray[$fieldPart[0]]);
        $field = (count($fieldPart) > 1) ? strtoupper($field . "_" . $this->dbColumnsArray[$fieldPart[1]]) : $field;

		$name = $this->settingVars->dataArray[$field]['NAME'];
		$name = explode(".", $name);
		
	    $selectedSkus['ORG_IDS'] = explode(",",rawurldecode($_REQUEST['FS'][$name[1]]));

		$table 			= array();
		$rows 			= array();
		$cols 			= array();
		$cols			= array( array('label' => 'YEAR', 	'type' => 'string') );

		//COLLECTING SKUNAMES FOR SELECTED SKUS
		$query = "SELECT ".$this->accountID." AS TPNB".
				", $this->accountName AS SKUNAME ".
				"FROM ".$this->settingVars->skutable." ".
				"WHERE $this->accountName IN('".implode("','", $selectedSkus['ORG_IDS'])."') AND GID = ".$this->settingVars->GID.
				" GROUP BY 1,2 ";

		//echo $query; exit;		
		$result = $this->queryVars->queryHandler->runQuery($query,$this->queryVars->linkid,db\ResultTypes::$TYPE_OBJECT);
		
		if (is_array($result) && !empty($result)) {
			foreach($result as $data) {
				$selectedSkus['IDS'][] = $data['TPNB'];
				if($this->aggregateSku=='false')
					$colChild[$data['TPNB']]=$data['SKUNAME'];
			}
		}

		//print_r($selectedSkus); exit;
		
		//PREPARE QUERY PART FOR SELECTED SKUS
		if(is_array($selectedSkus['IDS']) && !empty($selectedSkus['IDS']))
		{
			if($this->aggregateSku=='true')
				$selectPart	= 	",SUM((CASE WHEN ".$this->accountID." in (".join(",",$selectedSkus['IDS']).") THEN 1 ELSE 0 END)* ".$this->ValueVolume.") AS  'aggregateSku'";
			else
			{
				$selectPart = "";
				foreach($selectedSkus['IDS'] as $skuID)
					$selectPart	.= 	",SUM((CASE WHEN ".$this->accountID."=".$skuID." THEN 1 ELSE 0 END)* ".$this->ValueVolume.") AS '$skuID'";
			}
		
			//COLLECTING PRE-PROMO SALES
			$query = "SELECT 'PRE-PROMO' AS PROMO_TEXT, ".$this->settingVars->weekperiod." AS WEEK,".
					$this->settingVars->yearperiod." AS YEAR".
					$selectPart." ".
					"FROM ".$this->settingVars->tablename.$this->queryPart." ".
					"AND ".$this->prePromoTyWeekRange." ".
					"GROUP BY WEEK,YEAR ".
					"ORDER BY YEAR DESC,WEEK DESC";

			$prePromoSales = $this->queryVars->queryHandler->runQuery($query,$this->queryVars->linkid,db\ResultTypes::$TYPE_OBJECT);

			//COLLECTING PROMO SALES
			$query = "SELECT 'PROMO' AS PROMO_TEXT, ".$this->settingVars->weekperiod." AS WEEK,".
					$this->settingVars->yearperiod." AS YEAR".
					$selectPart." ".
					"FROM ".$this->settingVars->tablename.$this->queryPart." ".
					"AND ".$this->promoTyWeekRange." ".
					"GROUP BY WEEK,YEAR ".
					"ORDER BY YEAR DESC,WEEK DESC";

			$promoSales 		= $this->queryVars->queryHandler->runQuery($query,$this->queryVars->linkid,db\ResultTypes::$TYPE_OBJECT);

			//COLLECTING POST-PROMO SALES
			$query = "SELECT 'POST-PROMO' AS PROMO_TEXT, ".$this->settingVars->weekperiod." AS WEEK,".
									$this->settingVars->yearperiod." AS YEAR".
									$selectPart." ".
									"FROM ".$this->settingVars->tablename.$this->queryPart." ".
									"AND ".$this->postPromoTyWeekRange." ".
									"GROUP BY WEEK,YEAR ".
									"ORDER BY YEAR DESC,WEEK DESC";
			$postPromoSales 		= $this->queryVars->queryHandler->runQuery($query,$this->queryVars->linkid,db\ResultTypes::$TYPE_OBJECT);

			$mergedArray = array_merge($postPromoSales,$promoSales,$prePromoSales);
		}
		
		if (is_array($mergedArray) && !empty($mergedArray)) {
			//PREPARE CHART DATA
			foreach($mergedArray as $key=>$data) {
				$weekVal 	= $data['WEEK'];
				$identity 	= $data['PROMO_TEXT'];
				$temp		= array();
				$i=0;
				if($this->aggregateSku=='true')
				    $rows[$i++][] 	= array('value' => (int)$data['aggregateSku'],'skuname'=>'Aggregate SKUs','promotext' => $weekVal."-".$data['YEAR']." ($identity)");
				else
				{
				    foreach($selectedSkus['IDS'] as $skuID)
					    $rows[$i++][] 	= array('value' => (int)$data[$skuID],'skuname'=>$colChild[$skuID],'promotext' => $weekVal."-".$data['YEAR']." ($identity)");
				}
			}
			
			for($j=0;$j<count($rows);$j++)
				$rows[$j] = array_reverse($rows[$j]);

			$table['rows'] = $rows;
			$this->jsonOutput['Promo_LineChart'] = $table;
		}
	}

	public function buildPageArray() {

		$accountFieldPart = explode("#", $this->accountField);

		$fetchConfig = false;
		if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
		    $fetchConfig = true;
		    $this->jsonOutput['pageConfig'] = array(
				'enabledFilters' => $this->getPageConfiguration('filter_settings', $this->settingVars->pageID)
			);
		}

        $accountField = strtoupper($this->dbColumnsArray[$accountFieldPart[0]]);
        $accountField = (count($accountFieldPart) > 1) ? strtoupper($accountField."_".$this->dbColumnsArray[$accountFieldPart[1]]) : $accountField;

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