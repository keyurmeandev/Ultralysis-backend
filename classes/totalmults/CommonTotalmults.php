<?php
namespace classes\totalmults;

use filters;
use db;

class CommonTotalmults
{
    public static function performanceDistributionCalculator($settingVars){		
		$storeField = !key_exists('ID', $settingVars->dataArray['F8']) ? $settingVars->dataArray['F8']["NAME"] : $settingVars->dataArray['F8']["ID"];
		$subQueryPart = "SUM(CASE WHEN ".$settingVars->ProjectValue." > 0 THEN $storeField END)/".filters\timeFilter::$totalWeek;
        return $subQueryPart;
    }
	
	/*     * *
     * ONLY USED IN OVER/UNDER PAGES TO PREPARE GRID DATA
     * * */

    public static function gridFunction_for_overUnder($settingVars, $queryVars, $querypart, $id, $name, $storeField, $skuField, $jsonTag, $ValueVolume, &$jsonOutput) {
				
		if(isset($settingVars->getStoreCountType) && $settingVars->getStoreCountType != '')
		{
			if($settingVars->getStoreCountType == "AVG")
			{
				$query = "SELECT " .
						"SUM( (CASE WHEN " . filters\timeFilter::$tyWeekRange . " THEN 1 ELSE 0 END) * " . $ValueVolume . " ) AS SALES " .
						",SUM((CASE WHEN (" . filters\timeFilter::$tyWeekRange . " AND " . $settingVars->ProjectValue . ">0)  THEN 1 END) * " . $storeField . " )/".filters\timeFilter::$totalWeek." AS STORES " .
						",COUNT( DISTINCT (CASE WHEN (" . filters\timeFilter::$tyWeekRange . " AND " . $settingVars->ProjectValue . ">0)  THEN 1 END) * " . $skuField . " ) AS SKUS " .
						"FROM " . $settingVars->tablename . $querypart;
			}
			else
			{
				$query = "SELECT " .
						"SUM( (CASE WHEN " . filters\timeFilter::$tyWeekRange . " THEN 1 ELSE 0 END) * " . $ValueVolume . " ) AS SALES " .
						",".$settingVars->getStoreCountType."((CASE WHEN (" . filters\timeFilter::$tyWeekRange . " AND " . $settingVars->ProjectValue . ">0)  THEN 1 END) * " . $storeField . " ) AS STORES " .
						",COUNT( DISTINCT (CASE WHEN (" . filters\timeFilter::$tyWeekRange . " AND " . $settingVars->ProjectValue . ">0)  THEN 1 END) * " . $skuField . " ) AS SKUS " .
						"FROM " . $settingVars->tablename . $querypart;
			}
			//echo $query;exit;
			$result = $queryVars->queryHandler->runQuery($query, $queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
			$data = $result[0];
			$totalSkus = $data['SKUS'];
			$totalStores = $data['STORES'];
			$totalSales = $data['SALES'];

			//CHECK IF THERE SHOULD BE ID IN THE QUERY, IF YES, ADD ID IN GROUP BY CLAUSE TOO
			if (empty($id) || $id == "") {
				$query = "SELECT $name AS ACCOUNT,";
				$groupBy = "GROUP BY ACCOUNT";
			} else {
				$query = "SELECT $id AS ID,$name AS ACCOUNT,";
				$groupBy = "GROUP BY ID,ACCOUNT";
			}
			
			if($settingVars->getStoreCountType == "AVG")
			{
				$query .= "SUM( (CASE WHEN " . filters\timeFilter::$tyWeekRange . " THEN 1 ELSE 0 END) * $ValueVolume ) AS SALES" .
						",SUM((CASE WHEN (" . filters\timeFilter::$tyWeekRange . " AND " . $settingVars->ProjectValue . ">0)  THEN 1 END) * " . $storeField . ")/".filters\timeFilter::$totalWeek." AS STORES" .
						",COUNT( DISTINCT (CASE WHEN (" . filters\timeFilter::$tyWeekRange . " AND " . $settingVars->ProjectValue . ">0)  THEN 1 END) * " . $skuField . ") AS SKUS " .
						"FROM " . $settingVars->tablename . $querypart . " " .
						"$groupBy ORDER BY ACCOUNT ASC";
			}
			else
			{
				$query .= "SUM( (CASE WHEN " . filters\timeFilter::$tyWeekRange . " THEN 1 ELSE 0 END) * $ValueVolume ) AS SALES" .
						",".$settingVars->getStoreCountType."((CASE WHEN (" . filters\timeFilter::$tyWeekRange . " AND " . $settingVars->ProjectValue . ">0)  THEN 1 END) * " . $storeField . ") AS STORES" .
						",COUNT( DISTINCT (CASE WHEN (" . filters\timeFilter::$tyWeekRange . " AND " . $settingVars->ProjectValue . ">0)  THEN 1 END) * " . $skuField . ") AS SKUS " .
						"FROM " . $settingVars->tablename . $querypart . " " .
						"$groupBy ORDER BY ACCOUNT ASC";
			}
			$result = $queryVars->queryHandler->runQuery($query, $queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
			$value = array();
			foreach ($result as $key => $data) {
				$avg = ($data['STORES'] > 0 && $data['SKUS'] > 0) ? ($data['SALES'] / $data['STORES']) / filters\timeFilter::$totalWeek / $data['SKUS'] : 0;
				if ($avg > 0) {
					$idVal = $id == "" ? $data['ACCOUNT'] : $data['ID'];
					$value = array(
						'ID' => htmlspecialchars_decode($idVal)
						, 'ACCOUNT' => htmlspecialchars_decode($data['ACCOUNT'])
						, 'SALES' => $data['SALES']
						, 'STORES' => $data['STORES']
						, 'SKUS' => $data['SKUS']
						, 'TOTALWEEK' => filters\timeFilter::$totalWeek
						, 'COST_CUR' => number_format($avg, 2, '.', '')
					);
					$jsonOutput[$jsonTag][] = $value;
				}
			}

			if ($totalStores * filters\timeFilter::$totalWeek * $totalSkus > 0)
				$summaryAvg = $totalSales / $totalStores / filters\timeFilter::$totalWeek / $totalSkus;
			$value = array();
			$value = array(
				'avg' => number_format($summaryAvg, 2, '.', ',')
				, 'stores' => $totalStores
			);
			$jsonOutput[$jsonTag . "_summary"] = $value;
		}
    }
}
