<?php

namespace classes\retailer;

use datahelper;
use projectsettings;
use filters;
use db;
use config;
use utils;

class ZeroSalesOld extends config\UlConfig {
    /*     * ***
     * Default gateway function, should be similar in all DATA CLASS
     * arguments:
     *  $settingVars [project settingsGateway variables]
     * @return $xmlOutput with all data that should be sent to client app
     * *** */

    public function go($settingVars) {
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES
        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll
		
        $action = $_REQUEST["action"];
		
        if ($_GET['DataHelper'] == "true") {

            $this->jsonOutput['projectID']          	= utils\Encryption::encode($this->settingVars->projectID);
            
            if(isset($this->settingVars->clientLogo) && $this->settingVars->clientLogo != '')
                $this->jsonOutput['clientLogo']         = $this->settingVars->clientLogo;
            else
                $this->jsonOutput['clientLogo']         = 'no-logo.jpg';
            
            if(isset($this->settingVars->retailerLogo) && $this->settingVars->retailerLogo != '')
                $this->jsonOutput['retailerLogo']       = $this->settingVars->retailerLogo;
            else
                $this->jsonOutput['retailerLogo']       = 'no-logo.jpg';    

            if(isset($this->settingVars->default_load_pageID) && !empty($this->settingVars->default_load_pageID))
                $this->jsonOutput['default_load_pageID'] = $this->settingVars->default_load_pageID;
        }
		
        switch ($action) {
            case "skuChange":
                $this->skuSelect();
                break;
			case "getAllData":
				$this->zeroSalesGrid();
				break;
        }		
		
        return $this->jsonOutput;
    }

    function zeroSalesGrid() {

        $lastSalesDays = datahelper\Common_Data_Fetching_Functions::findLastSalesDays($this->settingVars);

        $id = key_exists('ID', $this->settingVars->dataArray['F2']) ? $this->settingVars->dataArray['F2']['ID'] : $this->settingVars->dataArray['F2']['NAME'];

        $storeid = key_exists('ID', $this->settingVars->dataArray['F13']) ? $this->settingVars->dataArray['F13']['ID'] : $this->settingVars->dataArray['F13']['NAME'];
        $storename = $this->settingVars->dataArray['F13']['NAME'];
        $skuname = $this->settingVars->dataArray['F2']['NAME'];
        $catname = $this->settingVars->dataArray['F7']['NAME'];
        $bannername = $this->settingVars->dataArray['F11']['NAME'];
        $storeDistrict = $this->settingVars->dataArray['F6']['NAME'];

        $query = "SELECT " . 
                $id . " AS TPNB " .
                "," . $catname . " AS CLUSTER " .
                "," . $bannername . " AS BANNER " .
                ",SUM((CASE WHEN ".$this->settingVars->ProjectValue.">0 THEN 1 END)*value)/COUNT(DISTINCT(case when (value) >1 then " . $this->settingVars->maintable . ".SNO end)) AS LOST_VALUE " .
                "FROM " . $this->settingVars->tablename . " " . $this->queryPart .
                "AND " . $this->settingVars->dateField . " IN('" . implode("','", filters\timeFilter::$tyDaysRange) . "') " .
                "GROUP BY TPNB,BANNER,CLUSTER  ORDER BY LOST_VALUE DESC";
        //echo $query.'<BR>';exit;
        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
		
        $lostValueArray 		  = array();
		$topLost 				  = array();
		$summaryPod 			  = array();
		$zeroSalesGridDataBinding = array();
		$summaryPod['TOTAL_LOST'] = 0;
		
        $selectPart = array();
        $groupByPart1 = array();
		
		if (is_array($result) && !empty($result))
		{
			foreach ($result as $key => $value) {
				$index = $value['TPNB'] . $value['BANNER'] . $value['CLUSTER'];
				$lostValueArray[$index] = $value['LOST_VALUE'];
			}
			
			print_r($lostValueArray); exit;
			
 			$query = "SELECT " . $id . " AS TPNB" .
					",TRIM(MAX(" . $skuname . ")) AS SKU" .
					"," . $storeid . " AS SNO " .
					",TRIM(MAX(" . $storename . ")) AS STORE " .
					",TRIM(MAX(" . $catname . ")) AS CLUSTER " .
					",TRIM(MAX(" . $bannername . ")) AS BANNER " .
					",SUM((CASE WHEN ".$this->settingVars->ProjectVolume.">0 AND recordType='SAL' THEN 1 ELSE 0 END)*".$this->settingVars->ProjectVolume.") AS SALES " .
					",SUM((CASE WHEN " . $this->settingVars->dateField . "='" . filters\timeFilter::$tyDaysRange[0] . "' AND recordType='STK' THEN 1 ELSE 0 END)*".$this->settingVars->ProjectVolume.") AS STOCK " .
					"FROM " . $this->settingVars->tablename . " " . $this->queryPart .
					" AND " . $this->settingVars->dateField . " IN('" . implode("','", filters\timeFilter::$tyDaysRange) . "') " .
					"GROUP BY TPNB,SNO ".$addTerritoryGroup." HAVING (STOCK>0 AND SALES=0)";

			//echo $query.'<BR>';exit;
			$result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
			
			$uniqueTpnb = array();
			if (is_array($result) && !empty($result))
			{
				foreach ($result as $key => $value) {

					//$index = $value['TPNB'] . $value['SNO'];
					
					$indexLostValue = $value['TPNB'] . $value['BANNER'] . $value['CLUSTER'];
					
					$row = array();
					
					$row['SNO'] = $value['SNO'];
					$row['STORE'] = utf8_encode($value['STORE']);
					$row['SKU'] = utf8_encode($value['SKU']);
					$row['SKUID'] = $value['TPNB'];
					$row['BANNER'] = utf8_encode($value['BANNER']);
					$row['CLUSTER'] = utf8_encode($value['CLUSTER']);
					
					//$row['STOREDISTRICT'] = utf8_encode($value['STOREDISTRICT']);
					$row['TPNB'] = $value['TPNB'];
					$row['STOCK'] = $value['STOCK'];
					$row['LOST'] = number_format($lostValueArray[$indexLostValue], 2, '.', '');
					$row['LAST_SALE'] = $lastSalesDays[$row['TPNB'] . "_" . $row['SNO']];
					$summaryPod['TOTAL_LOST'] += $row['LOST'];
					$summaryPod['TOTAL_LOST'] += $result[$key]['LOST'];
					
 					if (!isset($uniqueTpnb[$row['TPNB']])) {
						$uniqueTpnb[$row['TPNB']] = $row['TPNB'];
					}
					
					array_push($zeroSalesGridDataBinding, $row);
				}
			}
			// This is new update use  
		
			$sumOfShare = 0;
			if (is_array($zeroSalesGridDataBinding) && !empty($zeroSalesGridDataBinding))
			{
				foreach ($zeroSalesGridDataBinding as $value) {
					if (!isset($topLost[$value[$this->storeField]])) 
					{
						$topLost[$value['SNO']]['STORE'] = $value['STORE'];
						$topLost[$value['SNO']]['CLUSTER'] = $value['CLUSTER'];
						$topLost[$value['SNO']]['BANNER'] = $value['BANNER'];
						$topLost[$value[$this->storeField]]['ZERO_SKU'] = 1;
						$topLost[$value[$this->storeField]]['LOST'] = $value['LOST'];
					
					} else {
						$topLost[$value[$this->storeField]]['ZERO_SKU'] += 1;
						$topLost[$value[$this->storeField]]['LOST'] += $value['LOST'];
					}
				}

				foreach ($topLost as $key => $value) {
					$tmp[$key] = $value['LOST'];
				}
				array_multisort($tmp, SORT_DESC, $topLost);

				$cumShare = 0;
				$till80PerStoreCount = 0;
				foreach ($topLost as $key => $value) {
					$val = number_format(($value['LOST'] == 0 ? 0 : $value['LOST'] / $summaryPod['TOTAL_LOST']) * 100, '2', '.', ',');
					$cumShare += $val;
					$topLost[$key]['CUM_SHARE'] = $cumShare;
					if ($cumShare <= 80) {
						$till80PerStoreCount++;
					}
				}				

				$summaryPod['TOTAL_LOST'] = number_format($summaryPod['TOTAL_LOST'], '2', '.', ',');
				$summaryPod['UNIQUE_STORE'] = number_format(count($topLost), '0', '.', ',');
				$summaryPod['SKUS_INCLUDED'] = number_format(count($uniqueTpnb), '0', '.', ',');
				$summaryPod['80_PCT_LOST'] = number_format($till80PerStoreCount, '0', '.', ',');
				
			}
		}
        $this->jsonOutput['summaryPod'] = $summaryPod;
        $this->jsonOutput['topZeroSalesGrid'] = array_values($topLost);

        // end 
		
		if (is_array($zeroSalesGridDataBinding) && !empty($zeroSalesGridDataBinding))
		{
			foreach ($zeroSalesGridDataBinding as $key => $value) {
				$emp[$key] = $value['LOST'];
			}
			array_multisort($emp, SORT_DESC, $zeroSalesGridDataBinding);
		}

        $this->jsonOutput['zeroSalesGrid'] = $zeroSalesGridDataBinding;
    }

    function skuSelect() {

        $query = "SELECT " . $this->settingVars->dateperiod . " AS DAY" .
				",SUM((CASE WHEN recordType = 'SAL' THEN 1 ELSE 0 END)*".$this->settingVars->ProjectVolume.") AS SALES " .
                ",SUM((CASE WHEN ".$this->settingVars->ProjectVolume.">0 AND recordType = 'STK' THEN 1 ELSE 0 END)*".$this->settingVars->ProjectVolume.") AS STOCK " .
                "FROM " . $this->settingVars->tablename . " " . $this->queryPart .
                "AND " . $this->settingVars->dateperiod . " IN('" . implode("','", filters\timeFilter::$tyDaysRange) . "') " .
                "GROUP BY DAY " .
                "ORDER BY DAY ASC";
		//echo $query;exit;
        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);


        $value = array();
		
		if (is_array($result) && !empty($result))
		{
			foreach ($result as $data) {

				$value['SALES'][] = $data['SALES'];
				$value['STOCK'][] = $data['STOCK'];
				//$value['TRANSIT'][] = $data['TRANSIT'];
				$value['DAY'][] = $data['DAY'];
			}
		}
		
        $this->jsonOutput['skuSelect'] = $value;
    }
}
?>