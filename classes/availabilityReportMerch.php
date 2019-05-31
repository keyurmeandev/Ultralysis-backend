<?php

namespace classes;

use datahelper;
use projectsettings;
use filters;
use utils;
use db;
use config;
use lib;

class availabilityReportMerch extends config\UlConfig {

    private $TY_total;      //Share_By_Name Function uses this to create 'TOTAL' tag
    private $LY_total;      //Share_By_Name Function uses this to create 'TOTAL' tag
    public $pageName;
    public $podsSettings;
	public $wherePart;

    /*     * ***
     * Default gateway function, should be similar in all DATA CLASS
     * arguments:
     *  $settingVars [project settingsGateway variables]
     * @return $jsonOutput with all data that should be sent to client app
     * *** */

    public function go($settingVars) {
        $this->pageName = $settingVars->pageName = (empty($settingVars->pageName)) ? 'SummaryPage' : $settingVars->pageName; // set pageName in settingVars
		
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES

        if ($this->settingVars->isDynamicPage)
            $this->podsSettings = $this->getPageConfiguration('pods_settings', $this->settingVars->pageID);

        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll
        //$this->fetch_all_pod_data(); //collecting summary page POD data
        $this->fetchConfig(); // Fetching filter configuration for page

		if(isset($_REQUEST['FromDate']) && $_REQUEST['FromDate'] != "")
			$latestMydate = $_REQUEST['FromDate'];
		else if(isset($this->jsonOutput['yearWeekList']) && $this->jsonOutput['yearWeekList'][0]['data'] != "" && $_REQUEST['VeryFirstRequest'])
			$latestMydate = $this->jsonOutput['yearWeekList'][0]['data'];
	
		$this->wherePart = "";
		if(isset($_REQUEST['market_group']) && $_REQUEST['market_group'] != "")
		{
			$this->wherePart .= " AND MERCH_DEPT = '".rawurldecode($_REQUEST['market_group'])."' ";
			if(isset($_REQUEST['band']) && $_REQUEST['band'] != "")
				$this->wherePart .= "AND Band = '".rawurldecode($_REQUEST['band'])."' ";
		}
			
		$this->wherePart .=	" AND MYDATE = '".$latestMydate."'";
		
		$action = $_REQUEST['action'];
		
		switch($action)
		{
			case "getUpdatedChart":
				$this->getPieChartData();
				$this->getStackChartData();
				$this->getBottomGridData();
				break;
			case "changeGroup":
				$this->gridData();
				$this->bottomChartData();
				$this->getPieChartData();
				$this->getStackChartData();
				$this->getBottomGridData();
				break;
			case "updateBottomChart":
				$this->bottomChartData();
				break;
			default:
				$this->gridData();
				$this->bottomChartData();
				$this->getPieChartData();
				$this->getStackChartData();
				$this->getBottomGridData();
		}
		
        return $this->jsonOutput;
    }

    public function fetchConfig()
    {
        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
            $this->jsonOutput['gridConfig'] = array(
                'enabledFilters' => $this->getPageConfiguration('filter_settings', $this->settingVars->pageID)
            );
        }
    }
    
	public function getBottomGridData()
	{
		$query = "SELECT DISTINCT MYDATE, MERCH_DEPT, Band, ".$this->settingVars->maintable.".PIN, PNAME, Cost_Price as COST_PRICE, SUM(RTK_INSTOCK_STORES) as RTK_INSTOCK_STORES, SUM(RTK_Stores_Ranged) as RTK_Stores_Ranged, SUM(Total_RDC_SOH) as Total_RDC_SOH, (Total_RDC_SOH + SUM(Pont_On_Order) + SUM(Duns_On_Order)) as TOTAL_RDC_SOH_OO, ((SUM(Sales_WK1)+SUM(Sales_WK2)+SUM(Sales_WK3))/3) as AVG_Weeklys_Sales, ((Total_RDC_SOH + SUM(Pont_On_Order) + SUM(Duns_On_Order)) / ((SUM(Sales_WK1)+SUM(Sales_WK2)+SUM(Sales_WK3))/3)) as TOTAL_RDC_WIOH, supplier as supplier, agg5 as replenishmentManager, PNAME_ROLLUP  
		FROM ".$this->settingVars->maintable.", ".$this->settingVars->productHelperTables." ".$this->settingVars->productHelperLink." AND ".$this->settingVars->maintable.".PIN = ".$this->settingVars->productHelperTables.".PIN ".$this->wherePart;
		
		$result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
		
		if(is_array($result) && !empty($result))
		{
			foreach($result as $key => $data)
			{
				$result[$key]['MYDATE'] = date("jS M Y", strtotime($data['MYDATE']));
				$result[$key]['OOS'] = ($data['RTK_Stores_Ranged'] > 0) ? ($data['RTK_INSTOCK_STORES'] / $data['RTK_Stores_Ranged']) * 100 : 0;
				$result[$key]['OOS_STORE'] = $data['RTK_Stores_Ranged'] - $data['RTK_INSTOCK_STORES'];
			}
		}
		
		$this->jsonOutput['bottomGridData'] = $result;
	}
	
	public function getStackChartData()
	{
		$query = "SELECT 
			SUM(Pont_SOH) AS Pont_SOH,SUM(Pont_On_Order) AS Pont_On_Order, SUM(Duns_SOH) AS Duns_SOH,SUM(Duns_On_Order) AS Duns_On_Order 
			FROM ".$this->settingVars->maintable.", ".$this->settingVars->productHelperTables." ".$this->settingVars->productHelperLink." AND ".$this->settingVars->maintable.".PIN = ".$this->settingVars->productHelperTables.".PIN ".$this->wherePart;
		
		$result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
		
		$output = array();
		if(is_array($result) && !empty($result))
		{
			$output[] = array(
				"col_one" => (double)$result[0]['Pont_SOH'], 
				"col_two" => (double)$result[0]['Pont_On_Order'],
				"SOH" => number_format($result[0]['Pont_SOH']),
				"On_Order" => number_format($result[0]['Pont_On_Order']),
				"TOTAL_PONT" => number_format($result[0]['Pont_SOH'] + $result[0]['Pont_On_Order']),
				"ACCOUNT" => "PONT"
				);
			$output[] = array(
				"col_one" => (double)$result[0]['Duns_SOH'], 
				"col_two" => (double)$result[0]['Duns_On_Order'],
				"SOH" => number_format($result[0]['Duns_SOH']),
				"On_Order" => number_format($result[0]['Duns_On_Order']),
				"TOTAL_DUNS" => number_format($result[0]['Duns_SOH'] + $result[0]['Duns_On_Order']),
				"ACCOUNT" => "DUNS"
				);				
		}
		
		$this->jsonOutput['stackChartData'] = $output;
	}
	
	public function getPieChartData()
	{
		$query = "SELECT 
			SUM((CASE WHEN RTK_Store_Availability = 100 THEN 1 ELSE 0 END)) AS SEGMENTS_ONE,
			SUM((CASE WHEN RTK_Store_Availability > 99.5 AND RTK_Store_Availability < 100 THEN 1 ELSE 0 END)) AS SEGMENTS_TWO, 
			SUM((CASE WHEN RTK_Store_Availability > 99 AND RTK_Store_Availability <= 99.5 THEN 1 ELSE 0 END)) AS SEGMENTS_THREE, 
			SUM((CASE WHEN RTK_Store_Availability > 95 AND RTK_Store_Availability < 99 THEN 1 ELSE 0 END)) AS SEGMENTS_FOUR, 
			SUM((CASE WHEN RTK_Store_Availability < 95 THEN 1 ELSE 0 END)) AS SEGMENTS_FIVE 
			FROM ".$this->settingVars->maintable.", ".$this->settingVars->productHelperTables." ".$this->settingVars->productHelperLink." AND ".$this->settingVars->maintable.".PIN = ".$this->settingVars->productHelperTables.".PIN ".$this->wherePart;
		
		$result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
		
		$output = array();
		$accountArray = array("100% Instock", "Over 99.5%", "Over 99%", "Over 95%", "Less than 95%");
		
		if(is_array($result) && !empty($result))
		{
			$i = 0;
			foreach($result[0] as $key => $data)
			{
				$tmp = array("ACCOUNT" => $accountArray[$i], "TYEAR" => (int)$data, "LYEAR" => 0);
				$output[] = $tmp;
				$i++;
			}
		}
		
		$this->jsonOutput['pieChartData'] = $output;
	}
	
	public function gridData()
	{
 		$query = "SELECT DISTINCT MYDATE, MERCH_DEPT, Band, SUM(RTK_INSTOCK_STORES) as RTK_INSTOCK_STORES, SUM(RTK_Stores_Ranged) as RTK_Stores_Ranged, 
		SUM((CASE WHEN RTK_Store_Availability < 100 THEN 1 ELSE 0 END)) AS OOS_SKUS, SUM((CASE WHEN RTK_Store_Availability = 100 THEN 1 ELSE 0 END)) AS IS_SKUS FROM ".$this->settingVars->maintable.", ".$this->settingVars->productHelperTables." ".$this->settingVars->productHelperLink." AND ".$this->settingVars->maintable.".PIN = ".$this->settingVars->productHelperTables.".PIN ".$this->wherePart;
		
		$result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
		
		if(is_array($result) && !empty($result))
		{
			foreach($result as $key => $data)
			{
				$result[$key]['MYDATE'] = date("jS M Y", strtotime($data['MYDATE']));
				$result[$key]['OOS'] = ($data['RTK_Stores_Ranged'] > 0) ? ($data['RTK_INSTOCK_STORES'] / $data['RTK_Stores_Ranged']) * 100 : 0;
				$result[$key]['OOS'] = (float)number_format($result[$key]['OOS'], 2, '.', '');
				$result[$key]['OOS_STORE'] = $data['RTK_Stores_Ranged'] - $data['RTK_INSTOCK_STORES'];
			}
		}
		$this->jsonOutput['gridData'] = $result;
	}
	
	public function bottomChartData()
	{
		$wherePart = "";
		if(isset($_REQUEST['action']) && $_REQUEST['action'] == "getUpdatedChart")
			$wherePart = " AND MERCH_DEPT = '".rawurldecode($_REQUEST['market_group'])."' AND Band = '".rawurldecode($_REQUEST['band'])."' ";
		else if(isset($_REQUEST['market_group']) && $_REQUEST['market_group'] != "")
			$wherePart = " AND MERCH_DEPT = '".rawurldecode($_REQUEST['market_group'])."' ";
		
		if(isset($_REQUEST['action']) && $_REQUEST['action'] == "updateBottomChart")
		{
			$wherePart = " AND PNAME = '".rawurldecode($_REQUEST['item'])."' AND Band = '".rawurldecode($_REQUEST['band'])."' ";
		}
		
		$query = "SELECT DISTINCT MYDATE, SUM(RTK_INSTOCK_STORES) as RTK_INSTOCK_STORES, SUM(RTK_Stores_Ranged) as RTK_Stores_Ranged FROM ".$this->settingVars->maintable.", ".$this->settingVars->productHelperTables." ".$this->settingVars->productHelperLink." AND ".$this->settingVars->maintable.".PIN = ".$this->settingVars->productHelperTables.".PIN ".$wherePart." ORDER BY MYDATE";
		
		$result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
		
		if(is_array($result) && !empty($result))
		{
			foreach($result as $key => $data)
			{
				$result[$key]['MYDATE'] = $data['MYDATE'];
				$result[$key]['OOS'] = ($data['RTK_Stores_Ranged'] > 0) ? ($data['RTK_INSTOCK_STORES'] / $data['RTK_Stores_Ranged']) * 100 : 0;
				$result[$key]['OOS'] = (float)number_format($result[$key]['OOS'], 2, '.', '');
				$result[$key]['DIFF_OOS'] = 100 - $result[$key]['OOS'];
				$result[$key]['DIFF_OOS'] = (float)number_format($result[$key]['DIFF_OOS'], 2, '.', '');
			}
		}
		$this->jsonOutput['chartData'] = $result;		
	}
	
    /*     * ***
     * COLLECTS ALL POD DATA AND CONCATE THEM WITH GLOBAL $jsonOutput 
     * *** */

    public function fetch_all_pod_data() {
        $this->ValueVolume = getValueVolume($this->settingVars);

        //ADVICED TO EXECUTE THE FOLLOWING FUNCTION ON TOP AS IT SETS TY_total and LY_total
        $this->totalSales();

        if ($this->settingVars->isDynamicPage) {
            $this->configurePODsDynamic();
        } else {
            if($this->pageName == 'CAT_SUMMARY_PAGE') {
                if(isset($this->settingVars->catSummaryPageDataTwoField) && $this->settingVars->catSummaryPageDataTwoField != '')
                {
                    $query  = "SELECT db_column FROM ".$this->settingVars->clientconfigtable." WHERE cid=".$this->settingVars->aid." AND db_table='".$this->settingVars->skutable."' AND csv_column = '".$this->settingVars->catSummaryPageDataTwoField."'";
                    $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT); 
                    
                    if(is_array($result) && !empty($result))
                        $this->settingVars->pageArray[$this->pageName]["PODS"]["DATA_TWO"] = $result[0]['db_column'];
                }           
            }
                    
            //COLLECT REQUIRED POD VARIABLES
            if($this->pageName == 'EXE_SUMMARY_PAGE') {
                $this->configurePODsStatic('pod_one_key');
                $this->configurePODsStatic('pod_four_five_key');
            }

            foreach($this->settingVars->pageArray[$this->pageName]["TITLE"] as $key => $name){
                $this->jsonOutput[$key."_TITLE"] = $name;
            }
        }


		if (!empty($this->settingVars->pageArray[$this->pageName]["PODS"])) {
			$pods = $this->settingVars->pageArray[$this->pageName]["PODS"];
			foreach ($pods as $key => $account) {
				$this->Share_By_Name($account, $key);
			}
		}
    }

    public function configurePODsStatic($settingName)
    {
        $query  = "SELECT setting_value from ".$this->settingVars->configTable." as b WHERE b.accountID=".$this->settingVars->aid . 
            $this->settingVars->projectHelperLink." AND b.setting_name='".$settingName."'";
        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->projectManagerLinkid, db\ResultTypes::$TYPE_OBJECT);
        
        if(is_array($result) && !empty($result)) {
            $query  = "SELECT csv_column,db_table from ".$this->settingVars->clientconfigtable." as a WHERE a.cid=".$this->settingVars->aid.
                " AND a.db_column='".$result[0]['setting_value']."'";
            //a.db_table='".$this->settingVars->skutable."' AND 
            $subResult = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            //echo $query;exit;
            if(is_array($subResult) && !empty($subResult)) {
                if($settingName == 'pod_one_key') {
                    
                    $this->settingVars->pageArray[$this->pageName]["PODS"]["DATA_ONE"] = $subResult[0]['db_table'].".".$result[0]['setting_value'];
                    $this->settingVars->pageArray[$this->pageName]["TITLE"]["POD_TWO"] = "Share by ".$subResult[0]['csv_column'];
                    $this->settingVars->pageArray[$this->pageName]["TITLE"]["POD_THREE"] = $subResult[0]['csv_column']." Performance";
                } else {
                    $this->settingVars->pageArray[$this->pageName]["PODS"]["DATA_TWO"] = $subResult[0]['db_table'].".".$result[0]['setting_value'];
                    $this->settingVars->pageArray[$this->pageName]["TITLE"]["POD_FOUR"] = "Share by ".$subResult[0]['csv_column'];
                    $this->settingVars->pageArray[$this->pageName]["TITLE"]["POD_FIVE"] = $subResult[0]['csv_column']." Performance";
                }                   
            }
        }
    }

    public function configurePODsDynamic()
    {
        if(is_array($this->podsSettings) && !empty($this->podsSettings)) {
            $query  = "SELECT csv_column,db_table,db_column from ".$this->settingVars->clientconfigtable.
                " as a WHERE a.cid=".$this->settingVars->aid. " AND a.csv_column IN ('".implode("','", $this->podsSettings)."') AND show_in_pm = 'Y' ".
                " AND database_name = '".$this->settingVars->databaseName."' ";
            $subResult = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->projectManagerLinkid, db\ResultTypes::$TYPE_OBJECT);

            foreach ($this->podsSettings as $key => $podSetting) 
			{
                $searchPodKey = (is_array($subResult) && !empty($subResult)) ? array_search($podSetting, array_column($subResult, 'csv_column')) : '';
				if($searchPodKey !== false)
				{
					$this->settingVars->pageArray[$this->pageName]["PODS"][] = $subResult[$searchPodKey]['db_table'].".".$subResult[$searchPodKey]['db_column'];
					$this->jsonOutput["TITLE_".$key] = $podSetting;
				}
				else
				{
					$response = array("configuration" => array("status" => "fail", "messages" => array("Seems PODs configuration is missing.")));
					echo json_encode($response);
					exit();
				}
            }
        }
    }

    /*     * ***
     * COLLECTS TOTAL SALES POD DATA
     * SETS TWO STATIC VARIABLES [TY_total,LY_total] 
     * *** */

    public function totalSales() {

    	$options = array();
        if (!empty(filters\timeFilter::$tyWeekRange))
            $options['tyLyRange']['TYEAR'] = filters\timeFilter::$tyWeekRange;

        if (!empty(filters\timeFilter::$lyWeekRange))
            $options['tyLyRange']['LYEAR'] = filters\timeFilter::$lyWeekRange;

        $measureSelect = config\MeasureConfigure::prepareSelect($this->settingVars, $this->queryVars, array('M'.$_REQUEST['ValueVolume']), $options);
        $measureSelect = implode(", ", $measureSelect);

        $query = "SELECT .".
        		" ".$measureSelect." ".
        		//" SUM((CASE WHEN " . filters\timeFilter::$tyWeekRange . " THEN 1 ELSE 0 END) * " . $this->ValueVolume . " ) AS TYEAR " .
                //",SUM((CASE WHEN " . filters\timeFilter::$lyWeekRange . " THEN 1 ELSE 0 END) * " . $this->ValueVolume . " ) AS LYEAR " .
                "FROM " . $this->settingVars->tablename . $this->queryPart . " " .
                "AND (" . filters\timeFilter::$tyWeekRange . " OR " . filters\timeFilter::$lyWeekRange . ") " .
                "ORDER BY TYEAR DESC ";
        //print $query;exit;
        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
        $data = $result[0];

        //SET TY AND LY TOTAL SALES AS GLOBAL TO BE USED BY LATER FUNCTIONS
        $this->TY_total = $data['TYEAR'];
        $this->LY_total = $data['LYEAR'];


        $arr = array();
        $temp = array();
        //$temp['ACCOUNT'] = 'THIS PERIOD';
        $temp['ACCOUNT'] = (string) ($_REQUEST['TSM'] == 2 ? "THIS PERIOD" : "THIS YEAR");
        $temp['SALES'] = $data['TYEAR'];
        $arr[] = $temp;

        $temp = array();
        $temp['ACCOUNT'] = (string) ($_REQUEST['TSM'] == 2 ? "PREVIOUS PERIOD" : "LAST YEAR");
        $temp['SALES'] = $data['LYEAR'];
        $arr[] = $temp;

        $this->jsonOutput['totalSales'] = $arr;

        $val = $data['LYEAR'] > 0 ? (($data['TYEAR'] - $data['LYEAR']) / $data['LYEAR']) * 100 : 0;
        $this->jsonOutput['share'] = array("value" => $val);
    }

    /*     * ***
     * Prepares 'Share By NAME' & 'NAME Performance' POD data
     * arguments:
     *   $account:   Field name to use as 'Name'
     *   $tagName:    This tag will be the wrapper of corresponding xml pod data
     * *** */

    public function Share_By_Name($account, $tagName) {
    	$options = array();
        if (!empty(filters\timeFilter::$tyWeekRange))
            $options['tyLyRange']['TYEAR'] = filters\timeFilter::$tyWeekRange;

        if (!empty(filters\timeFilter::$lyWeekRange))
            $options['tyLyRange']['LYEAR'] = filters\timeFilter::$lyWeekRange;

        $measureSelect = config\MeasureConfigure::prepareSelect($this->settingVars, $this->queryVars, array('M'.$_REQUEST['ValueVolume']), $options);
        $measureSelect = implode(", ", $measureSelect);

        $query = "SELECT $account AS ACCOUNT" .
        		", ".$measureSelect." ".
                //",SUM((CASE WHEN " . filters\timeFilter::$tyWeekRange . " THEN 1 ELSE 0 END) * " . $this->ValueVolume . " ) AS TYEAR " .
                //",SUM((CASE WHEN " . filters\timeFilter::$lyWeekRange . " THEN 1 ELSE 0 END) * " . $this->ValueVolume . " ) AS LYEAR " .
                "FROM " . $this->settingVars->tablename . $this->queryPart . " AND (" . filters\timeFilter::$tyWeekRange . " OR " . filters\timeFilter::$lyWeekRange . ") " .
                "GROUP BY ACCOUNT " .
                "HAVING TYEAR<>0 OR LYEAR<>0 " .
                "ORDER BY TYEAR DESC";
        //print $query;exit;
        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
        $otherTY = 0;
        $otherLY = 0;

        $temp = array();
        $arr = array();
        foreach ($result as $key => $data) {
            if ($key < 9) {
                $temp["ACCOUNT"] = htmlspecialchars_decode($data['ACCOUNT']);
                $temp["TYEAR"] = $data['TYEAR'];
                $temp["LYEAR"] = $data['LYEAR'];
                $arr[] = $temp;
            } else {
                $otherTY += $data['TYEAR'];
                $otherLY += $data['LYEAR'];
            }
        }

        if ($key >= 9) {
            $temp["ACCOUNT"] = "All Other";
            $temp["TYEAR"] = number_format($otherTY, 0, '', '');
            $temp["LYEAR"] = number_format($otherLY, 0, '', '');
            $arr[] = $temp;
        }

        $temp["ACCOUNT"] = "TOTAL";
        $temp["TYEAR"] = number_format($this->TY_total, 0, '', '');
        $temp["LYEAR"] = number_format($this->LY_total, 0, '', '');
        $arr[] = $temp;

        $this->jsonOutput["POD_$tagName"] = $arr;
    }
}

?>