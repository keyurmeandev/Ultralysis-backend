<?php
namespace classes\relayplus;
ini_set("memory_limit", "1024M");

use datahelper;
use projectsettings;
use filters;
use utils;
use db;
use config;
use lib;

ini_set ('allow_url_fopen', '1');

class StoreSellThruOptimizer extends config\UlConfig {

    public $vsiStatusData;

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
        $this->buildDataArray();
        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll

        /*if (empty($_REQUEST["requestDays"])) {
            $_REQUEST["requestDays"] = 14;
        }*/


        $action = $_REQUEST["action"];
        switch ($action) {
            case "skuChange":
                $this->skuSelect();
                break;
            case "storeSellThruGrid": //storeChange
                $this->storeSellThruGrid();
				break;
			case "storeRangeEfficiencyGrid":
				$this->storeRangeEfficiencyGrid();
				break;
            case "crossStoreAnalysisGrid":
                $this->crossStoreAnalysisGrid();
				break;
			case "getSaleStockComparisonChart":	
				$this->getSaleStockComparisonChart();
                break;
/*             case "filterChange":
                $this->storeSellThruGrid();
                $this->storeRangeEfficiencyGrid();
                $this->crossStoreAnalysisGrid();
				$this->getSaleStockComparisonChart();
                break; */
/*             case "crossStoreAnalysisGrid": // comparedStoreChange
                $this->crossStoreAnalysisGrid();
				break;
			case "getSaleStockComparisonChart":
				$this->getSaleStockComparisonChart();
                break; */
			case "createImage":
                $this->getStoreImage();
                break;
            case "comparisonChartSkuSelect":
                $this->getSaleStockComparisonChart();
                break;
            case "storeList":
                $this->storeList();
                break;
        }

        return $this->jsonOutput;
    }

    /**
     * storeList()
     * This Function is used to retrieve list of stores
     * @access private  
     * @return $this->jsonOutput with all data that should be sent to client app     
     */
    private function storeList() {

        $query ="SELECT DISTINCT ". $this->storeID . " AS DATA" .
                ", TRIM(" . $this->storeName . ") AS ACCOUNT".
                ", SUM(" . $this->settingVars->ProjectVolume . ") AS QTY" .
                " FROM ". $this->settingVars->storeHelperTables . $this->settingVars->storeHelperLink .
                " AND ". $this->settingVars->maintable . ".accountID=" . $this->settingVars->aid . 
                " AND " . $this->settingVars->storetable . ".gid=".$this->settingVars->GID . " GROUP BY DATA, ACCOUNT having QTY>0 ORDER BY ACCOUNT ASC";

        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }

        $stores = array();
        if (is_array($result) && !empty($result)) {
            foreach ($result as $storeDetail) {
                $stores[] = array(
                    'DATA' => $storeDetail['DATA'],
                    'ACCOUNT' => $storeDetail['ACCOUNT']." (".$storeDetail['DATA'].")"
                );
            }
        }
        $this->jsonOutput['storeList'] = $stores;
    }
	
	/**
	 * storeSellThruGrid()
     * This Function is used to retrieve list based on set parameters     
	 * @access private	
     * @return $this->jsonOutput with all data that should be sent to client app     
     */
    private function storeSellThruGrid() {
		
		$storeSellThruGridDataBinding = array();

        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $this->measureFields[] = $this->skuID;
        $this->measureFields[] = $this->storeID;
        
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->queryPart = $this->getAll();

        //$lastSalesDays        = datahelper\Common_Data_Fetching_Functions::findLastSalesDays($this->settingVars);
        $lastSalesDays      = datahelper\Common_Data_Fetching_Functions::getLastSalesDays( $this->skuID, $this->storeID, $this->settingVars, $this->queryPart);

        // get VSI according to all mydate        
		$query = "SELECT distinct skuID AS TPNB" .
                ",SNO " .
                ",VSI " .
                ",TSI " .
                ",StoreTrans " .
                "FROM " . $this->settingVars->ranged_items .
                " WHERE clientID='" . $this->settingVars->clientID . "' AND GID = ".$this->settingVars->GID;


        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }

        $tsiStatusData = $this->vsiStatusData = $storeTransData = array();
        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
		
		if (is_array($result) && !empty($result)) {
			
			foreach ($result as $key => $row) {
				$this->vsiStatusData[$row['TPNB'] . '_' . $row['SNO']] = $row['VSI'];
				$tsiStatusData[$row['TPNB'] . '_' . $row['SNO']] = $row['TSI'];
                $storeTransData[$row['TPNB'] . '_' . $row['SNO']] = $row['StoreTrans'];
			}
			
			// For Territory
			if (isset($_REQUEST["territoryLevel"]) && $_REQUEST["territoryLevel"] != "") {
				$addTerritoryColumn = ",".$this->settingVars->territoryTable . ".Level".$_REQUEST["territoryLevel"]." AS TERRITORY";
				$addTerritoryGroup = ",TERRITORY";

                $this->measureFields[] = $this->settingVars->territoryTable . ".Level".$_REQUEST["territoryLevel"];      
			}
			else
			{
				$addTerritoryColumn = '';
				$addTerritoryGroup = '';
			}

            $this->settingVars->setCluster();

            $this->measureFields[] = $this->skuID;
            $this->measureFields[] = $this->storeID;
            $this->measureFields[] = $this->storeName;
            $this->measureFields[] = $this->skuName;
            $this->measureFields[] = $this->ohq;
            $this->measureFields[] = $this->msq;
            $this->measureFields[] = $this->planogram;
            $this->measureFields[] = $this->catName;
            $this->measureFields[] = $this->gsq;
            $this->measureFields[] = $this->settingVars->clusterID;
            
            $this->settingVars->useRequiredTablesOnly = true;
            if (is_array($this->measureFields) && !empty($this->measureFields)) {
                $this->prepareTablesUsedForQuery($this->measureFields);
            }


            // [STATIC FIX TO BYPASS THE TERRITORY LEVEL FROM THE QUERY WHERE PART]
            if (isset($_REQUEST["Level"]) && $_REQUEST["Level"] != "" && isset($_REQUEST["territoryLevel"]) && $_REQUEST["territoryLevel"] != "") {
                $reqLevel = $_REQUEST["Level"]; $_REQUEST["Level"] = '';
                    $this->queryPart = $this->getAll();
                $_REQUEST["Level"] = $reqLevel;
            }else{
                $this->queryPart = $this->getAll();
            }

			$query = "SELECT "
					. $this->skuID . " AS TPNB " .
					"," . $this->storeID . " AS SNO " .
					",TRIM(MAX(" . $this->storeName . ")) AS STORE " .
					$addTerritoryColumn.
					",TRIM(MAX(" . $this->settingVars->clusterID . ")) AS CLUSTER " .
					",TRIM(MAX(" . $this->skuName . ")) AS SKU" .
					",SUM(" . $this->settingVars->ProjectVolume . ") AS SALES " .
					",SUM((CASE WHEN " . $this->settingVars->DatePeriod . "= '" . filters\timeFilter::$tyDaysRange[0] . "' THEN 1 ELSE 0 END)*" . $this->ohq . ") AS STOCK " .
					",SUM((CASE WHEN " . $this->settingVars->DatePeriod . "= '" . filters\timeFilter::$tyDaysRange[0] . "' THEN 1 ELSE 0 END)*" . $this->msq . ") AS SHELF " .
					",TRIM(MAX(" . $this->planogram . ")) AS PLANOGRAM " .
                    ",TRIM(MAX(" . $this->catName . ")) AS CATEGORY" .
					",SUM((CASE WHEN " . $this->settingVars->ProjectVolume . " > 0 THEN 1 ELSE 0 END)*" . $this->settingVars->ProjectVolume . ") AS qty " .
					",SUM((CASE WHEN " . $this->gsq . ">0 THEN 1 ELSE 0 END)*" . $this->gsq . ") AS GSQ " .
					"FROM " . $this->settingVars->tablename . " " . $this->queryPart .
					"AND " . filters\timeFilter::$tyWeekRange .
					"GROUP BY TPNB,SNO ".$addTerritoryGroup;

			$redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
            if ($redisOutput === false) {
                $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
                $this->redisCache->setDataForHash($result);
            } else {
                $result = $redisOutput;
            }
			
			if (is_array($result) && !empty($result)) {
				foreach ($result as $value) {
					$index 	= $value['TPNB'] .'_'. $value['SNO'];
					$status = array_key_exists($index, $this->vsiStatusData) ? 1 : 0;
					
					if (($_REQUEST['VSI'] == 1 && $status == 1) || ($_REQUEST['VSI'] == 2 && $status == 0) || ($_REQUEST['VSI'] == 3)) {

                        if (isset($_REQUEST["Level"]) && $_REQUEST["Level"] != "" && isset($_REQUEST["territoryLevel"]) && $_REQUEST["territoryLevel"] != "") {
                            if($_REQUEST["Level"] == '1' && $value['TERRITORY'] == 'NOT CALLED'){
                                continue;
                            }
                            else if($_REQUEST["Level"] == '2' && $value['TERRITORY'] != 'NOT CALLED'){
                                continue;
                            }
                        }

						if ($tsiStatusData[$index] == 1 && $this->vsiStatusData[$index] == 1)
							$tsiVsi = "Y/Y";
						else if ($tsiStatusData[$index] == 1 && $this->vsiStatusData[$index] == 0)
							$tsiVsi = "Y/N";
						else if ($tsiStatusData[$index] == 0 && $this->vsiStatusData[$index] == 1)
							$tsiVsi = "N/Y";
						else
							$tsiVsi = "N/N";

						$aveDailySales 	= $value['SALES'] / filters\timeFilter::$daysTimeframe;
						$sell_thru 		= 0;
						if ($value['GSQ'] > 0) {
							$sell_thru 	= number_format(($value['qty'] / $value['GSQ']) * 100, 1);
						}
						
						$row 					= array();
						$dc 					= ($value['SALES'] > 0) ? (($value['STOCK'] + $value['TRANSIT']) / $aveDailySales) : 0;
						$row['SNO'] 			= $value['SNO'];
						$row['STORE'] 			= utf8_encode($value['STORE']);
						$row['CLUSTER'] 		= utf8_encode($value['CLUSTER']);
						$row['SKUID'] 			= $value['TPNB'];
						$row['SKU'] 			= utf8_encode($value['SKU']);
                        $row['CATEGORY']        = utf8_encode($value['CATEGORY']);
						$row['STOCK'] 			= (int)$value['STOCK'];
                        $row['TRANSIT']         = $storeTransData[$index];
						$row['GSQ'] 			= $value['GSQ'];
						$row['qty'] 			= (int)$value['qty'];
						$row['sell_thru'] 		= $sell_thru;
						$row['SHELF'] 			= $value['SHELF'];
						$row['AVE_DAILY_SALES'] = number_format($aveDailySales, 2, '.', '');
						$row['DAYS_COVER'] 		= number_format($dc, 2, '.', '');
						$row['LAST_SALE'] 		= $lastSalesDays[$value['TPNB'] . "_" . $value['SNO']];
						$row['PLANOGRAM'] 		= ($value['PLANOGRAM'] == null) ? '' : $value['PLANOGRAM'];
						$row['TSIVSI'] 			= $tsiVsi;
                        $row['ACTION']          = "";
						
						if($value['TERRITORY'])
							$row['TERRITORY'] = $value['TERRITORY'];

						array_push($storeSellThruGridDataBinding, $row);
					}
				}
				
				if(!empty($storeSellThruGridDataBinding))
				{
					foreach ($storeSellThruGridDataBinding as $key => $value) {
						//still going to sort by firstname
						$emp[$key] = $value['AVE_DAILY_SALES'];
					}
					array_multisort($emp, SORT_DESC, $storeSellThruGridDataBinding);
				}
			} // end if		
		
		} // end if

        $this->jsonOutput['storeSellThruGrid'] = $storeSellThruGridDataBinding;
    }
	
	/**
	 * skuSelect()
     * This Function is used to retrieve sku data based on set parameters for graph     
	 * @access private	
     * @return $this->jsonOutput with all data that should be sent to client app     
     */
    private function skuSelect() {

        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $this->measureFields[] = $this->ohq;
        $this->measureFields[] = $this->ohaq;
        $this->measureFields[] = $this->baq;
        $this->measureFields[] = $this->gsq;
        
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->queryPart = $this->getAll();

        $query = "SELECT ". $this->settingVars->DatePeriod .",  DATE_FORMAT(". $this->settingVars->DatePeriod .",'%a %e %b') AS DAY" .
                ",SUM(" . $this->settingVars->ProjectVolume . ") AS SALES " .
                ",SUM((CASE WHEN " . $this->ohq . ">0 THEN 1 ELSE 0 END)*" . $this->ohq . ") AS STOCK " .
                ",SUM(" . $this->ohaq .") AS OHAQ " .
                ",SUM(" . $this->baq .") AS BAQ " .                
                ",SUM((CASE WHEN " . $this->gsq . ">0 THEN 1 ELSE 0 END)*" . $this->gsq . ") AS GSQ " .
                "FROM " . $this->settingVars->tablename . " " . $this->queryPart .
                //" AND " . filters\timeFilter::$tyWeekRange .
                " GROUP BY DAY, ". $this->settingVars->DatePeriod ." " .
                "ORDER BY ". $this->settingVars->DatePeriod ." ASC";

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
				$value['SALES'][] 	= $data['SALES'];
				$value['STOCK'][] 	= $data['STOCK'];
                $value['TRANSIT'][] = 0;
				$value['ADJ'][] 	= $data['OHAQ']+$data['BAQ'];
				$value['DAY'][] 	= $data['DAY'];
				$value['GSQ'][] 	= $data['GSQ'];
			}
		} // end if
        $this->jsonOutput['skuSelect'] = $value;
    }

    /**
     * GridSKU()
     * It will prepare data for SKU trails and driving in selected range grid
     * 
     * @param String $id To Store field name value
     *
     * @return Void
     */
    private function storeRangeEfficiencyGrid()
    {
		$query = "SELECT distinct skuID AS TPNB" .
				",SNO " .
				",VSI " .
				",TSI " .
				",StoreTrans " .
				"FROM " . $this->settingVars->ranged_items .
				" WHERE clientID='" . $this->settingVars->clientID . "' AND GID = ".$this->settingVars->GID;
		$redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $resultRPT = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($resultRPT);
        } else {
            $resultRPT = $redisOutput;
        }

		$this->vsiStatusData = array();
		if (is_array($resultRPT) && !empty($resultRPT)) {
			foreach ($resultRPT as $key => $row)
				$this->vsiStatusData[$row['TPNB'] . '_' . $row['SNO']] = $row['VSI'];
		}	
	
        $tempArr    = array();
        $currency   = ($_REQUEST['ValueVolume'] == 1) ? $this->settingVars->currencySign : '';
        //$params     = $_REQUEST["FS"];
        //$storeID    = $params['F3'];
        $storeID    = $_REQUEST['primaryStore'];
        $totalStoreSales = $totalStoreSku = 0;

        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $this->measureFields[] = $this->skuID;
        $this->measureFields[] = $this->storeID;
        
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->queryPart = $this->getAll();
        //$lastSalesDays        = datahelper\Common_Data_Fetching_Functions::findLastSalesDays($this->settingVars);
        $lastSalesDays      = datahelper\Common_Data_Fetching_Functions::getLastSalesDays( $this->skuID, $this->storeID, $this->settingVars, $this->queryPart);
        
        $this->settingVars->tableUsedForQuery = array();
        $this->measureFields = $this->prepareTablesUsedForMeasure($_REQUEST['ValueVolume']);
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->queryPart = $this->getAll();

        $options = array();
        if (!empty(filters\timeFilter::$tyWeekRange))
            $options['tyLyRange']['SALES'] = filters\timeFilter::$tyWeekRange;

        $measureSelect = config\MeasureConfigure::prepareSelect($this->settingVars, $this->queryVars, array('M'.$_REQUEST['ValueVolume']), $options);
        $measureSelect = implode(", ", $measureSelect);

        $query  ="SELECT " . $this->settingVars->maintable . ".SNO AS SNO".
            ", " . $this->settingVars->maintable . ".SIN AS skuID".
            ", " . $measureSelect . " ".
            " FROM ".$this->settingVars->tablename.$this->queryPart.
            " AND " . filters\timeFilter::$tyWeekRange .
            " GROUP BY SNO, skuID";

        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query,$this->queryVars->linkid,db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }

        if (is_array($result) && !empty($result)) {
            foreach($result as $data) {
                $searchIndex  = $data['skuID'] .'_'. $storeID;
                $status = array_key_exists($searchIndex, $this->vsiStatusData) ? 1 : 0;
                
                if (($_REQUEST['VSI'] == 1 && $status == 1) || ($_REQUEST['VSI'] == 2 && $status == 0) || ($_REQUEST['VSI'] == 3)) {
                    $totalStoreSales += $data['SALES'];
                    $totalStoreSku++;
                }
            }
        }

        $this->settingVars->tableUsedForQuery = array();
        $this->measureFields = $this->prepareTablesUsedForMeasure($_REQUEST['ValueVolume']);
        $this->measureFields[] = $this->skuID;
        $this->measureFields[] = $this->storeID;
        $this->measureFields[] = $this->skuName;
        $this->measureFields[] = $this->storeName;
        $this->measureFields[] = $this->msq;
        $this->measureFields[] = $this->ohq;
        
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->queryPart = $this->getAll();

        $options = array();
        if (!empty(filters\timeFilter::$tyWeekRange))
            $options['tyLyRange']['SALES'] = filters\timeFilter::$tyWeekRange;

        $measureSelect = config\MeasureConfigure::prepareSelect($this->settingVars, $this->queryVars, array('M'.$_REQUEST['ValueVolume']), $options);
        $measureSelect = implode(", ", $measureSelect);

        $query  ="SELECT  ".$this->skuID." AS ID".
            ", ".$this->skuName." AS ACCOUNT".
            ",TRIM(MAX(" . $this->storeName . ")) AS STORE " .
            ", ".$measureSelect." ".
            //", SUM(" . $this->ValueVolume . " ) AS SALES".
            ", MAX((CASE WHEN " . $this->settingVars->DatePeriod . "= '" . filters\timeFilter::$tyDaysRange[0] . "' THEN 1 ELSE 0 END)*" . $this->msq . ") AS SHELF " .
            ", SUM((CASE WHEN " . $this->settingVars->DatePeriod . "= '" . filters\timeFilter::$tyDaysRange[0] . "' THEN 1 ELSE 0 END)*" . $this->ohq . ") AS STOCK " .
            "FROM ".$this->settingVars->tablename.$this->queryPart.
            " AND " . filters\timeFilter::$tyWeekRange .
            "GROUP BY ID, ACCOUNT ".
            "ORDER BY SALES DESC, ACCOUNT ASC";

        $table = array();
        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query,$this->queryVars->linkid,db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }

        $share = $topshare = $tailshare = $cumShare = $topSku = $topSkuSum = $totalSku = 0;
        if (is_array($result) && !empty($result)) {

            foreach($result as $key=>$data) {
                $index  = $data['ID'] .'_'. $storeID;
                $status = array_key_exists($index, $this->vsiStatusData) ? 1 : 0;
                
                if (($_REQUEST['VSI'] == 1 && $status == 1) || ($_REQUEST['VSI'] == 2 && $status == 0) || ($_REQUEST['VSI'] == 3)) {
                    $share      = $totalStoreSales <> 0 ? ($data['SALES'] / $totalStoreSales) * 100 : 0;					
                    if ($cumShare < $_REQUEST["skuPercent"]) {
                        $topSkuSum  = $topSkuSum + $data['SALES'];
                        $topshare   += $share;
                        $gridTag    = "SKUtop";
                    } else {
                       $gridTag     = "SKUtail";
                       $tailshare   += $share; 
                    }
                    
                    $aveDailySales  = $data['SALES'] / filters\timeFilter::$daysTimeframe;
                    $cumShare       = $cumShare + $share;
                    $dc             = ($data['SALES'] > 0) ? (($data['STOCK'] + $data['TRANSIT']) / $aveDailySales) : 0;
                  
                    $tempArr['ID'] = $data['ID'];
                    $tempArr['ACCOUNT'] = $data['ACCOUNT'];
                    $tempArr['STORE'] = $data['STORE'];
                    $tempArr['SALES'] = $data['SALES'];
                    $tempArr['SHELF'] = $data['SHELF'];
                    $tempArr['STOCK'] = $data['STOCK'];
                    $tempArr['LAST_SALE'] = $lastSalesDays[$data['ID'] . "_" . $storeID];
                    $tempArr['DAYS_COVER']      = number_format($dc, 2, '.', '');

                    $this->jsonOutput[$gridTag][] = $tempArr;
                }
            }
            
            $topSku = count($this->jsonOutput['SKUtop']);
            $tailSku = count($this->jsonOutput['SKUtail']);

            $topSkuValueShare   = $totalStoreSales <> 0 ? number_format(($topSkuSum / $totalStoreSales)*100,0)   : 0;
            $topSkuItemsShare   = $totalStoreSku <> 0    ? number_format(($topSku / $totalStoreSku)*100, 0)       : 0;
            
            $table['valueShare'] = [(int)$topSkuValueShare, (int)$topSkuItemsShare];
            $table['itemShare'] = [(int)(100-(int)$topSkuValueShare), (int)(100-(int)$topSkuItemsShare)];
        }

        $this->jsonOutput['totalSku'] = number_format($totalStoreSku, 0, '.', ',');
        $this->jsonOutput['totalSkuSum'] =  $currency.' '.number_format($totalStoreSales, 0, '.', ',');        
        
        $this->jsonOutput['topSku'] = number_format($topSku, 0, '.', ',');
        $this->jsonOutput['topSkuSum'] = $currency.' '.number_format($topSkuSum, 0, '.', ',');
        $this->jsonOutput['topshare'] = $topshare;
        $this->jsonOutput['tailSku'] = number_format($tailSku, 0, '.', '');
        $this->jsonOutput['tailSkuSum'] = $currency.' '.number_format(($totalStoreSales - $topSkuSum), 0, '.', ',');
        $this->jsonOutput['tailshare'] = $tailshare;        
        
        $this->jsonOutput['hasEfficiencyData'] = true;
        $this->jsonOutput['barchart'] = $table;
        
        if(!isset($this->jsonOutput['SKUtop']) || empty($this->jsonOutput['SKUtop']))
            $this->jsonOutput['SKUtop'] = array();

        if(!isset($this->jsonOutput['SKUtail']) || empty($this->jsonOutput['SKUtail']))
            $this->jsonOutput['SKUtail'] = array();
    }

    /**
     * GridSKU()
     * It will prepare data for SKU trails and driving in selected range grid
     * 
     * @param String $id To Store field name value
     *
     * @return Void
     */
    private function crossStoreAnalysisGrid()
    {
        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $this->measureFields[] = $this->skuID;
        $this->measureFields[] = $this->storeID;
        
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->queryPart = $this->getAll();

        //$lastSalesDays        = datahelper\Common_Data_Fetching_Functions::findLastSalesDays($this->settingVars);
        $lastSalesDays      = datahelper\Common_Data_Fetching_Functions::getLastSalesDays( $this->skuID, $this->storeID, $this->settingVars, $this->queryPart);

        //$getLastDaysDate        = filters\timeFilter::getLastNDaysDate($this->settingVars);
        $primaryStoreID         = $_REQUEST['primaryStore'];
        $secondaryStoreID       = $_REQUEST['compareStore'];
        $crossStoreAnalysisGrid = array();
		//$getLast14Days 			= filters\timeFilter::getLastN14DaysDate($this->settingVars);
		
		$totalSku 				= 0;
		$totalStoreSalesSum		= 0;
		$totalStoreSalesQtySum	= 0;
		
        if (!empty($primaryStoreID) && !empty($secondaryStoreID)) {

            // get VSI according to all mydate        
            $query = "SELECT distinct skuID AS TPNB" .
                    ",SNO " .
                    ",VSI " .
                    "FROM " . $this->settingVars->ranged_items .
                    " WHERE clientID='" . $this->settingVars->clientID . "' AND GID = ".$this->settingVars->GID;

            $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
            if ($redisOutput === false) {
                $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
                $this->redisCache->setDataForHash($result);
            } else {
                $result = $redisOutput;
            }
            $vsiStatusData = array();
            if (is_array($result) && !empty($result)) {
                foreach ($result as $key => $row)
                    $vsiStatusData[$row['TPNB'] . '_' . $row['SNO']] = $row['VSI'];

                $this->settingVars->tableUsedForQuery = $this->measureFields = array();
                $this->measureFields[] = $this->skuID;
                $this->measureFields[] = $this->storeID;
                $this->measureFields[] = $this->skuName;
                $this->measureFields[] = $this->ohq;
                $this->measureFields[] = $this->msq;
                
                $this->settingVars->useRequiredTablesOnly = true;
                if (is_array($this->measureFields) && !empty($this->measureFields)) {
                    $this->prepareTablesUsedForQuery($this->measureFields);
                }
                $this->queryPart = $this->getAllForCompare();

                $options = array();
                if (!empty($primaryStoreID))
                    $options['tyLyRange']['SALES_0'] = $this->storeID . "='" . $primaryStoreID."' ";

                if (!empty($secondaryStoreID))
                    $options['tyLyRange']['SALES_1'] = $this->storeID . "='" . $secondaryStoreID."' ";

                $measureSelect = config\MeasureConfigure::prepareSelect($this->settingVars, $this->queryVars, array('M'.$_REQUEST['ValueVolume']), $options);
                $measureSelect = implode(", ", $measureSelect);

                $query = "SELECT "
                        . $this->skuID . " AS TPNB " .
                        ",TRIM(" . $this->skuName . ") AS SKU" .	
                        ", ".$measureSelect." ".					
                        //",SUM((CASE WHEN " . $this->storeID . "=" . $primaryStoreID . " THEN 1 ELSE 0 END)*" . $this->ValueVolume . ") AS SALES_0 " .
                        ",SUM((CASE WHEN " . $this->settingVars->DatePeriod . "= '" . filters\timeFilter::$tyDaysRange[0] . "' AND " . $this->storeID . "=" . $primaryStoreID . " THEN 1 ELSE 0 END)*" . $this->ohq . ") AS STOCK_0 " .
                        ",MAX((CASE WHEN " . $this->settingVars->DatePeriod . "= '" . filters\timeFilter::$tyDaysRange[0] . "' AND " . $this->storeID . "=" . $primaryStoreID . " THEN 1 ELSE 0 END)*" . $this->msq . ") AS SHELF_0 " .
                        //",SUM((CASE WHEN " . $this->storeID . "=" . $secondaryStoreID . " THEN 1 ELSE 0 END)*" . $this->ValueVolume . ") AS SALES_1 " .
                        ",SUM((CASE WHEN " . $this->settingVars->DatePeriod . "= '" . filters\timeFilter::$tyDaysRange[0] . "' AND " . $this->storeID . "=" . $secondaryStoreID . " THEN 1 ELSE 0 END)*" . $this->ohq . ") AS STOCK_1 " .
                        ",MAX((CASE WHEN " . $this->settingVars->DatePeriod . "= '" . filters\timeFilter::$tyDaysRange[0] . "' AND " . $this->storeID . "=" . $secondaryStoreID . " THEN 1 ELSE 0 END)*" . $this->msq . ") AS SHELF_1 " .
                        "FROM " . $this->settingVars->tablename . " " . $this->queryPart .
                        "AND " . filters\timeFilter::$tyWeekRange .
                        "GROUP BY TPNB,SKU HAVING SALES_0 > 0 OR SALES_1 > 0 ORDER BY SALES_0 DESC";

                $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
                if ($redisOutput === false) {
                    $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
                    $this->redisCache->setDataForHash($result);
                } else {
                    $result = $redisOutput;
                }

                $crossStoreAnalysisGrid = array();
                if (is_array($result) && !empty($result)) {
                    $compareStores = array(
                        $primaryStoreID,
                        $secondaryStoreID
                    );
                    foreach ($compareStores as $storeIndex => $storeID) {						
                        $result = utils\SortUtility::sort2DArray($result, 'SALES_' . $storeIndex, utils\SortTypes::$SORT_DESCENDING);
                        foreach ($result as $key => $value) {							
                            $index  = $value['TPNB'] .'_'. $storeID;

                            $crossStoreAnalysisGrid[$value['TPNB']]['SKUID']               = $value['TPNB'];
                            $crossStoreAnalysisGrid[$value['TPNB']]['SKU']                 = utf8_encode($value['SKU']);
                            $crossStoreAnalysisGrid[$value['TPNB']]['RANK_'.$storeIndex]   = $key+1;
                            $crossStoreAnalysisGrid[$value['TPNB']]['SALES_'.$storeIndex]  = $value['SALES_'.$storeIndex];
                            $crossStoreAnalysisGrid[$value['TPNB']]['STOCK_'.$storeIndex]   = $value['STOCK_'.$storeIndex];
                            $crossStoreAnalysisGrid[$value['TPNB']]['SHELF_'.$storeIndex]   = $value['SHELF_'.$storeIndex];
                            $crossStoreAnalysisGrid[$value['TPNB']]['VALID_'.$storeIndex]   = ($vsiStatusData[$index]==1)?'Y':'N';							
                        }
                    }
                } // end if								
				
            } // end if
        }
		
        $this->jsonOutput['crossStoreAnalysisGrid'] = array_values($crossStoreAnalysisGrid);
		//$this->getStoreImage();		
    }    
	
	private function getSaleStockComparisonChart()
	{
		$primaryStoreID     = $_REQUEST['primaryStore'];
        $secondaryStoreID	= $_REQUEST['compareStore'];
        $filterSkuId        = (isset($_REQUEST['chartSkuId']) && !empty($_REQUEST['chartSkuId'])) ? $_REQUEST['chartSkuId'] : '';
		
		if(isset($primaryStoreID) && $primaryStoreID != '' && isset($secondaryStoreID) && $secondaryStoreID != '')
		{
            $this->settingVars->tableUsedForQuery = $this->measureFields = array();
            $this->measureFields[] = $this->skuID;
            $this->measureFields[] = $this->storeID;
            $this->measureFields[] = $this->storeName;
            $this->measureFields[] = $this->ohq;
            
            $this->settingVars->useRequiredTablesOnly = true;
            if (is_array($this->measureFields) && !empty($this->measureFields)) {
                $this->prepareTablesUsedForQuery($this->measureFields);
            }
            $this->queryPart = $this->getAllForCompare();

			$query = "SELECT ".	
					"mydate".
					",MAX(CASE WHEN " . $this->storeID . "=" . $primaryStoreID . " THEN ".$this->storeName." END) AS STORE_P " .
					",MAX(CASE WHEN " . $this->storeID . "=" . $secondaryStoreID . " THEN ".$this->storeName." END) AS STORE_S " .
					",SUM((CASE WHEN " . $this->storeID . "=" . $primaryStoreID . " THEN 1 ELSE 0 END)*".$this->ohq.") AS STOCK_P " .
					",SUM((CASE WHEN " . $this->storeID . "=" . $secondaryStoreID . " THEN 1 ELSE 0 END)*".$this->ohq.") AS STOCK_S " .
					",SUM((CASE WHEN ". $this->storeID . "=" . $primaryStoreID . " THEN 1 ELSE 0 END)*" . $this->settingVars->ProjectValue . ") AS SALES_P " .
					",SUM((CASE WHEN ". $this->storeID . "=" . $secondaryStoreID . " THEN 1 ELSE 0 END)*" . $this->settingVars->ProjectValue . ") AS SALES_S " .
					"FROM " . $this->settingVars->tablename . " " . $this->queryPart .
					(($filterSkuId) ? ' AND ' . $this->skuID . " = " . $filterSkuId : '') .
					" GROUP BY mydate ORDER BY mydate ASC";
			//echo $query;exit;
            $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
            if ($redisOutput === false) {
                $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
                $this->redisCache->setDataForHash($result);
            } else {
                $result = $redisOutput;
            }

			$arr	= array();
			if (is_array($result) && !empty($result)) {
				$arr['STORE_P'][] = $result[0]['STORE_P'];
				$arr['STORE_S'][] = $result[0]['STORE_S'];
				foreach($result as $key => $row){					
					$arr['MYDATE'][]		= date("d M Y",strtotime($row['mydate']));
					$arr['STOCK_P'][]	= $row['STOCK_P'];					
					$arr['STOCK_S'][]	= $row['STOCK_S'];					
					$arr['SALES_P'][]	= $row['SALES_P'];					
					$arr['SALES_S'][]	= $row['SALES_S'];				
				}
				
				$chartData = array();
				foreach($arr as $k =>$v){
					foreach($v as $data){
						$chartData[$k][] = $data ;	
					}
				}
			}
			$this->jsonOutput['getSaleStockComparisonChart'] = $chartData;
		}
		else
			$this->jsonOutput['getSaleStockComparisonChart'] = array();
	}
	
	private function getStoreImage()
	{		
        $primaryStoreID         = $_REQUEST['primaryStore'];
        $secondaryStoreID       = $_REQUEST['compareStore'];
        $crossStoreAnalysisGrid = array();
        //$getLast14Days          = filters\timeFilter::getLastN14DaysDate($this->settingVars);
		$getLast14Days 			= filters\timeFilter::$ty14DaysRange;
        $getFirst7              = array_slice($getLast14Days, 0 ,7);
        $getPrev7               = array_slice($getLast14Days, 7 ,14);

        if (!empty($primaryStoreID) && !empty($secondaryStoreID)) {
			
            // get valid sku based on store        
            $query = "SELECT count(DISTINCT skuID) as SKU,".$this->settingVars->ranged_items.".SNO as SNUM " .                   
                    "FROM " . $this->settingVars->ranged_items .
                    " WHERE clientID='" . $this->settingVars->clientID . "' AND GID = ".$this->settingVars->GID." AND VSI = 1 AND ".$this->settingVars->ranged_items.".SNO in ($primaryStoreID,$secondaryStoreID) GROUP BY SNUM";
			//echo $query;exit;
            $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
            if ($redisOutput === false) {
                $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
                $this->redisCache->setDataForHash($result);
            } else {
                $result = $redisOutput;
            }
            if (is_array($result) && !empty($result)) {
                                
				$storesku 	= array();
						
				foreach ($result as $key => $row)
					$storesku[$row['SNUM']] 		= $row['SKU'];

                $this->settingVars->tableUsedForQuery = $this->measureFields = array();
                $this->measureFields[] = $this->storeID;
                $this->measureFields[] = $this->storeName;
                $this->measureFields[] = $this->ohq;
                
                $this->settingVars->useRequiredTablesOnly = true;
                if (is_array($this->measureFields) && !empty($this->measureFields)) {
                    $this->prepareTablesUsedForQuery($this->measureFields);
                }
                $this->queryPart = $this->getAllForCompare();
				
                $query = "SELECT "
                        . $this->storeID . " AS SNO " .											
                        ",MAX(CASE WHEN " . $this->storeID . "=" . $primaryStoreID . " THEN ".$this->storeName." END) AS STORE_0 " .
                        ",MAX(CASE WHEN " . $this->storeID . "=" . $secondaryStoreID . " THEN ".$this->storeName." END) AS STORE_1 " .
                        ",SUM((CASE WHEN " . $this->storeID . "=" . $primaryStoreID . " THEN 1 ELSE 0 END)*" . $this->settingVars->ProjectVolume . ") AS QTY_0 " .
						",SUM((CASE WHEN " . $this->storeID . "=" . $secondaryStoreID . " THEN 1 ELSE 0 END)*" . $this->settingVars->ProjectVolume . ") AS QTY_1 " .
                        ",SUM((CASE WHEN " . $this->settingVars->DatePeriod . " = '" . $getLast14Days[0] . "' AND " . $this->storeID . "=" . $primaryStoreID . " THEN 1 ELSE 0 END)*".$this->ohq.") AS STOCK_0 " .
						",SUM((CASE WHEN " . $this->settingVars->DatePeriod . " = '" . $getLast14Days[0] . "' AND " . $this->storeID . "=" . $secondaryStoreID . " THEN 1 ELSE 0 END)*".$this->ohq.") AS STOCK_1 " .
						",SUM((CASE WHEN " . $this->settingVars->DatePeriod . " in ('" . implode("','", $getFirst7) . "') AND " . $this->storeID . "=" . $primaryStoreID . " THEN 1 ELSE 0 END)*" . $this->settingVars->ProjectValue . ") AS SALES_LW_0 " .
						",SUM((CASE WHEN " . $this->settingVars->DatePeriod . " in ('" . implode("','", $getPrev7) . "') AND " . $this->storeID . "=" . $primaryStoreID . " THEN 1 ELSE 0 END)*" . $this->settingVars->ProjectValue . ") AS SALES_CW_0 " .
						",SUM((CASE WHEN " . $this->settingVars->DatePeriod . " in ('" . implode("','", $getFirst7) . "') AND " . $this->storeID . "=" . $secondaryStoreID . " THEN 1 ELSE 0 END)*" . $this->settingVars->ProjectValue . ") AS SALES_LW_1 " .
						",SUM((CASE WHEN " . $this->settingVars->DatePeriod . " in ('" . implode("','", $getPrev7) . "') AND " . $this->storeID . "=" . $secondaryStoreID . " THEN 1 ELSE 0 END)*" . $this->settingVars->ProjectValue . ") AS SALES_CW_1 " .
                        "FROM " . $this->settingVars->tablename . " " . $this->queryPart .
                        "GROUP BY SNO HAVING (SALES_LW_0 > 0 OR SALES_CW_0 > 0 OR SALES_LW_1 > 0 OR SALES_CW_1 > 0) ORDER BY SALES_LW_0 DESC, SALES_LW_1 DESC";

                $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
                if ($redisOutput === false) {
                    $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
                    $this->redisCache->setDataForHash($result);
                } else {
                    $result = $redisOutput;
                }
                $crossStoreAnalysisGrid = array();
                if (is_array($result) && !empty($result)) {
                    $compareStores = array(
                        $primaryStoreID,
                        $secondaryStoreID
                    );
					$storeData	= array();
                    foreach ($compareStores as $storeIndex => $storeID) {
						foreach ($result as $key => $value) {
                            $storeData[$value['SNO']]['STORE_NAME']     = $value['STORE_'.$key];
                            $storeData[$value['SNO']]['SALES_QTY']      = $value['QTY_'.$key];
                            $storeData[$value['SNO']]['STOCK']          = $value['STOCK_'.$key];
                            $storeData[$value['SNO']]['SALES_LW']       = $value['SALES_LW_'.$key];
                            $storeData[$value['SNO']]['SALES_CW']       = $value['SALES_CW_'.$key];
                            $storeData[$value['SNO']]['WK_ON_WK_PER']   = ($storeData[$value['SNO']]['SALES_LW'] > 0) ? number_format((($storeData[$value['SNO']]['SALES_CW']-$storeData[$value['SNO']]['SALES_LW'])/$storeData[$value['SNO']]['SALES_LW']) * 100,2,'.','') : 0;
                            $storeData[$value['SNO']]['TOTAL_SALES']    = $storeData[$value['SNO']]['SALES_LW']+$storeData[$value['SNO']]['SALES_CW'];
						}
                    }
                    $storeData[$primaryStoreID]['VALID'] = $storesku[$primaryStoreID];
                    $storeData[$secondaryStoreID]['VALID'] = $storesku[$secondaryStoreID];
                } // end if				
				
                $createdimg = $this->createStoreAnalysisImg($storeData, $primaryStoreID, $secondaryStoreID);
            } // end if
        }
		$this->jsonOutput['crossStoreAnalysisImg'] = $createdimg;
	}
	
	private function createStoreAnalysisImg($storeData, $primaryStoreID, $secondaryStoreID)
	{

		$storesDetails = array(
            array(
                'primary'   => $this->settingVars->currencySign." ".number_format($storeData[$primaryStoreID]['TOTAL_SALES'],0,'',','),
                'secondary' => $this->settingVars->currencySign." ".number_format($storeData[$secondaryStoreID]['TOTAL_SALES'],0,'',','),
                'label'     => " Sales ".$this->settingVars->currencySign." "
            ),
            array(
                'primary'   => number_format($storeData[$primaryStoreID]['SALES_QTY'],0,'',','),
                'secondary' => number_format($storeData[$secondaryStoreID]['SALES_QTY'],0,'',','),
                'label'     => " Sales Qty "
            ),
            array(
                'primary'   => number_format($storeData[$primaryStoreID]['VALID'],0,'',','),
                'secondary' => number_format($storeData[$secondaryStoreID]['VALID'],0,'',','),
                'label'     => " Valid Skus "
            ),
            array(
                'primary'   => number_format($storeData[$primaryStoreID]['STOCK'],0,'',','),
                'secondary' => number_format($storeData[$secondaryStoreID]['STOCK'],0,'',','),
                'label'     => " Total Stock "
            ),
            array(
                'primary'   => $storeData[$primaryStoreID]['WK_ON_WK_PER']." %",
                'secondary' => $storeData[$secondaryStoreID]['WK_ON_WK_PER']." %",
                'label'     => " Wk on Wk % "
            )
        );
        
        $primaryStoreName   = $storeData[$primaryStoreID]['STORE_NAME'];
        $secondaryStoreName = $storeData[$secondaryStoreID]['STORE_NAME'];

		// convert in PDF		
		try
		{			
			ob_start();
			$pathAlert = dirname(__FILE__).'/StoreCompare.php';
			include($pathAlert);
			$content = ob_get_contents();
			ob_end_clean();
			
			$html2pdf = new lib\HTML2PDF('L', 'A4', 'en', true, 'UTF-8', array(16, 16, 16, 16));		
			$html2pdf->writeHTML($content, isset($_GET['vuehtml']));
			$html2pdf->Output($this->settingVars->uploadPath.'storecompare.pdf','F');

			$im = new \imagick($this->settingVars->uploadPath.'storecompare.pdf');
			$im->setImageFormat( "jpg" );
            $fileName = time().".jpg";			
			$img_name = $this->settingVars->uploadURL.$fileName;
			$img_path = $this->settingVars->uploadPath.$fileName;
			$im->setSize(1000,650);
			$im->writeImage($img_path);			
			$im->clear();
			$im->destroy();	

			if(file_exists($this->settingVars->uploadPath.'storecompare.pdf'))
				unlink($this->settingVars->uploadPath.'storecompare.pdf');
		}
		catch(HTML2PDF_exception $e) {
			echo $e;
			exit;
		}
		
		return $img_name;
	}
	
	/* ****
     * COLLECTS TABLE JOINING STRING FROM PROJECT SETTINGS
     * ALSO APPLYS FILTER CONDITIONS TO THE STRING
     * AND RETURNS $tablejoins_and_filters
     * ****/
    public function getAll() {

        $tablejoins_and_filters = "";
        $extraFields = array();

        if (isset($_REQUEST['SUPPLIER']) && $_REQUEST['SUPPLIER']=="YES"){
            $tablejoins_and_filters .= " ".$this->settingVars->supplierHelperLink." ";
        }

        if($_REQUEST['primaryStore'] != ''){
            $extraFields[] = $this->storeID;
            $tablejoins_and_filters .= " AND ".$this->storeID." IN ('".$_REQUEST["primaryStore"]."') "; 
        }
        

        if ($_REQUEST["region"] != '') {
            $extraFields[] = $this->regionField;
            $tablejoins_and_filters .= ' AND '.$this->regionField.' = "'.$_REQUEST["region"].'" ';
        }

        $this->prepareTablesUsedForQuery($extraFields);
        $tablejoins_and_filters1    = parent::getAll();
        $tablejoins_and_filters1    .= $tablejoins_and_filters;

        return $tablejoins_and_filters1;

    }

    /* ****
     * COLLECTS TABLE JOINING STRING FROM PROJECT SETTINGS
     * ALSO APPLYS FILTER CONDITIONS TO THE STRING
     * AND RETURNS $tablejoins_and_filters
     * ****/
    public function getAllForCompare() {

        $primaryStoreID         = $_REQUEST['primaryStore'];
        $secondaryStoreID       = $_REQUEST['compareStore'];

        $tablejoins_and_filters = "";
        $extraFields = array();

        if (isset($_REQUEST['SUPPLIER']) && $_REQUEST['SUPPLIER']=="YES"){
            $tablejoins_and_filters .= " ".$this->settingVars->supplierHelperLink." ";
        }

        if($primaryStoreID != '' && $secondaryStoreID != '' ){
            $extraFields[] = $this->storeID;
            $tablejoins_and_filters .= " AND ".$this->storeID." IN('".$primaryStoreID."','".$secondaryStoreID."') "; 
        }

        if ($_REQUEST["region"] != '') {
            $extraFields[] = $this->regionField;
            $tablejoins_and_filters .= ' AND '.$this->regionField.' = "'.$_REQUEST["region"].'" ';
        }

        $this->prepareTablesUsedForQuery($extraFields);
        $tablejoins_and_filters1    = parent::getAll();
        $tablejoins_and_filters1    .= $tablejoins_and_filters;

        return $tablejoins_and_filters1;
        
    }

    public function checkConfiguration(){

        if(!isset($this->settingVars->ranged_items) || empty($this->settingVars->ranged_items))
            $this->configurationFailureMessage("Relay Plus TV configuration not found.");

        $configurationCheck = new config\ConfigurationCheck($this->settingVars, $this->queryVars);
        $configurationCheck->checkClusterConfiguration();

        return ;
    }

    public function buildDataArray() {

        $this->skuID    = key_exists('ID', $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['SKU_FIELD']]) ? $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['SKU_FIELD']]['ID'] : $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['SKU_FIELD']]['NAME'];
        $this->storeID  = key_exists('ID', $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['STORE_FIELD']]) ? $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['STORE_FIELD']]['ID'] : $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['STORE_FIELD']]['NAME'];
        $this->storeName= $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['STORE_FIELD']]['NAME'];
        $this->skuName  = $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['SKU_FIELD']]['NAME'];
        $this->catName  = $this->settingVars->dataArray['F23']['NAME'];  
        $this->ohq      = $this->settingVars->dataArray['F12']['NAME'];
        $this->storeTrans = $this->settingVars->dataArray['F13']['NAME'];
        $this->msq      = $this->settingVars->dataArray['F14']['NAME'];
        $this->planogram= $this->settingVars->dataArray['F6']['NAME'];
        $this->tsi      = $this->settingVars->dataArray['F7']['NAME'];
        $this->vsi      = $this->settingVars->dataArray['F8']['NAME'];
        $this->gsq      = $this->settingVars->dataArray['F9']['NAME'];
        $this->ohaq     = $this->settingVars->dataArray['F10']['NAME'];
        $this->baq      = $this->settingVars->dataArray['F11']['NAME'];
        $this->regionField = $this->settingVars->dataArray['F19']['NAME'];

    }

}
?>