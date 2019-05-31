<?php

namespace classes;

use datahelper;
use projectsettings;
use filters;
use utils;
use db;
use config;
use lib;

class SummaryPage extends config\UlConfig {

    private $TY_total;      //Share_By_Name Function uses this to create 'TOTAL' tag
    private $LY_total;      //Share_By_Name Function uses this to create 'TOTAL' tag
    public $pageName;
    public $podsSettings;
    public $podNum = 0;
    public $measureFields = array();

    /*     * ***
     * Default gateway function, should be similar in all DATA CLASS
     * arguments:
     *  $settingVars [project settingsGateway variables]
     * @return $jsonOutput with all data that should be sent to client app
     * *** */

    public function go($settingVars) {
        $this->pageName = $settingVars->pageName = (empty($settingVars->pageName)) ? 'SummaryPage' : $settingVars->pageName; // set pageName in settingVars
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES

		$this->settingVars->useRequiredTablesOnly = false;
        // $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll
        $this->fetchConfig(); // Fetching filter configuration for page

        $this->redisCache = new utils\RedisCache($this->queryVars);

        if ($_REQUEST['DataHelper'] != "true") {
            if ($_REQUEST['action'] == 'totalSales')
                $this->fetch_totalSales(); //collecting summary page POD data
            elseif ($_REQUEST['action'] == 'sharePerformance') {
                if ($this->settingVars->isDynamicPage) {
                    $this->podsSettings = $this->getPageConfiguration('pods_settings', $this->settingVars->pageID);
                    $this->podNum = (isset($_REQUEST['podNum'])) ? ($_REQUEST['podNum']-1) : 0;
                    $this->podsSettings = array($this->podsSettings[$this->podNum]);
                }

                $this->fetch_all_pod_data(); //collecting summary page POD data
            }
        }

        return $this->jsonOutput;
    }

    public function fetchConfig()
    {
        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
            $this->jsonOutput['gridConfig'] = array(
                'enabledFilters' => $this->getPageConfiguration('filter_settings', $this->settingVars->pageID)
            );

            /*[START] CODE FOR INLINE FILTER STYLE*/
            $filtersDisplayType = $this->getPageConfiguration('filter_disp_type', $this->settingVars->pageID);
            if(!empty($filtersDisplayType) && isset($filtersDisplayType[0])) {
                $this->jsonOutput['gridConfig']['enabledFiltersDispType'] = $filtersDisplayType[0];
            }
            /*[END] CODE FOR INLINE FILTER STYLE*/
        }
    }

    public function fetch_totalSales($value='')
    {
        $this->ValueVolume = getValueVolume($this->settingVars);

        $this->measureFields = $this->prepareTablesUsedForMeasure($_REQUEST['ValueVolume']);

        //ADVICED TO EXECUTE THE FOLLOWING FUNCTION ON TOP AS IT SETS TY_total and LY_total
        $this->totalSales();
    }
    
    /*     * ***
     * COLLECTS ALL POD DATA AND CONCATE THEM WITH GLOBAL $jsonOutput 
     * *** */

    public function fetch_all_pod_data() {
        $this->ValueVolume = getValueVolume($this->settingVars);
        
        // $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $this->measureFields = $this->prepareTablesUsedForMeasure($_REQUEST['ValueVolume']);
        //ADVICED TO EXECUTE THE FOLLOWING FUNCTION ON TOP AS IT SETS TY_total and LY_total
        // $this->totalSales();

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
            $subResult = array();
            foreach ($this->podsSettings as $field) {
                if(is_array($this->settingVars->clientConfiguration) && !empty($this->settingVars->clientConfiguration)){
                    $searchKeyWithTable  = array_search($field, array_column($this->settingVars->clientConfiguration, 'db_table_csv'));

                    if($searchKeyWithTable !== false && $this->settingVars->clientConfiguration[$searchKeyWithTable]['show_in_pm'] == 'Y' )
                            $subResult[] = $this->settingVars->clientConfiguration[$searchKeyWithTable];
                }    
            }
	
            if(count($this->podsSettings) != count($subResult))
            {
                $response = array("configuration" => array("status" => "fail", "messages" => array("Seems PODs configuration is missing.")));
                echo json_encode($response);
                exit();                
            }
    
            foreach ($this->podsSettings as $key => $podSetting) 
			{
                $searchPodKey = (is_array($subResult) && !empty($subResult)) ? array_search($podSetting, array_column($subResult, 'db_table_csv')) : '';
				if($searchPodKey !== false)
				{
					$this->settingVars->pageArray[$this->pageName]["PODS"][$this->podNum] = $subResult[$searchPodKey]['db_table'].".".$subResult[$searchPodKey]['db_column'];
					$this->jsonOutput["TITLE_".$this->podNum] = $subResult[$searchPodKey]['csv_column'];
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

        $measureSelectRes = $this->prepareMeasureSelectPart();
        $this->measureFields = $measureSelectRes['measureFields'];

        $this->measureFields[] = $account;
        $this->prepareTablesUsedForQuery($this->measureFields);
        $this->settingVars->useRequiredTablesOnly = true;
        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll

        $measureSelectionArr = $measureSelectRes['measureSelectionArr'];
        $havingTYValue = $measureSelectRes['havingTYValue'];
        $havingLYValue = $measureSelectRes['havingLYValue'];

        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }

        /*$options = array();
        if (!empty(filters\timeFilter::$tyWeekRange))
            $options['tyLyRange']['TYEAR'] = filters\timeFilter::$tyWeekRange;

        if (!empty(filters\timeFilter::$lyWeekRange))
            $options['tyLyRange']['LYEAR'] = filters\timeFilter::$lyWeekRange;

        $measureSelect = config\MeasureConfigure::prepareSelect($this->settingVars, $this->queryVars, array('M'.$_REQUEST['ValueVolume']), $options);
        $measureSelect = implode(", ", $measureSelect);

        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll
        $query = "SELECT " . implode(",", $measureSelectionArr) . " ".
                "FROM " . $this->settingVars->tablename . $this->queryPart . " " .
                "AND (" . filters\timeFilter::$tyWeekRange . " OR " . filters\timeFilter::$lyWeekRange . ") " .
                "ORDER BY TYEAR DESC ";*/

        $query = "SELECT " .implode(",", $measureSelectionArr).
            " FROM " . $this->settingVars->tablename .' '. trim($this->queryPart) .
            " AND (" . filters\timeFilter::$tyWeekRange . " OR " . filters\timeFilter::$lyWeekRange . ") ";

        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }

        $data = $result[0];

        //SET TY AND LY TOTAL SALES AS GLOBAL TO BE USED BY LATER FUNCTIONS
        // $this->TY_total = $data['TYEAR'];
        // $this->LY_total = $data['LYEAR'];


        $arr = array();
        $temp = array();
        //$temp['ACCOUNT'] = 'THIS PERIOD';
        $temp['ACCOUNT'] = (string) ($_REQUEST['TSM'] == 2 ? "THIS PERIOD" : "THIS YEAR");
        $temp['SALES'] = $data[$havingTYValue];
        $arr[] = $temp;

        $temp = array();
        $temp['ACCOUNT'] = (string) ($_REQUEST['TSM'] == 2 ? "PREVIOUS PERIOD" : "LAST YEAR");
        $temp['SALES'] = $data[$havingLYValue];
        $arr[] = $temp;

        $this->jsonOutput['totalSales'] = $arr;

        $val = $data[$havingLYValue] > 0 ? (($data[$havingTYValue] - $data[$havingLYValue]) / $data[$havingLYValue]) * 100 : 0;
        $this->jsonOutput['share'] = array("value" => $val);
    }

    /*     * ***
     * Prepares 'Share By NAME' & 'NAME Performance' POD data
     * arguments:
     *   $account:   Field name to use as 'Name'
     *   $tagName:    This tag will be the wrapper of corresponding xml pod data
     * *** */

    public function Share_By_Name($account, $tagName) 
    {
        $measureSelectRes = $this->prepareMeasureSelectPart();
        $this->measureFields = $measureSelectRes['measureFields'];

        $this->measureFields[] = $account;
        $this->prepareTablesUsedForQuery($this->measureFields);
        $this->settingVars->useRequiredTablesOnly = true;
        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll

        $measureSelectionArr = $measureSelectRes['measureSelectionArr'];
        $havingTYValue = $measureSelectRes['havingTYValue'];
        $havingLYValue = $measureSelectRes['havingLYValue'];

        /*$measureSelect = count($measureSelectionArr) > 0 ? implode(",", $measureSelectionArr) : '';
        $query = "SELECT $account AS ACCOUNT" .
                ", ". $measureSelect." ".
                "FROM " . $this->settingVars->tablename . $this->queryPart . " AND (" . filters\timeFilter::$tyWeekRange . " OR " . filters\timeFilter::$lyWeekRange . ") " .
                "GROUP BY ACCOUNT ";*/
              /*"HAVING TYEAR<>0 OR LYEAR<>0 " .
                "ORDER BY TYEAR DESC";*/
        
       $query = "SELECT $account AS ACCOUNT, " .implode(",", $measureSelectionArr).
            " FROM " . $this->settingVars->tablename .' '. trim($this->queryPart) .
            " AND (" . filters\timeFilter::$tyWeekRange . " OR " . filters\timeFilter::$lyWeekRange . ") " .
            "GROUP BY ACCOUNT";

        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }

        $selMeasureNm = $this->settingVars->measureArray['M'.$_REQUEST['ValueVolume']]['ALIASE'];
        $requiredGridFields = array("ACCOUNT", $havingTYValue, $havingLYValue);
        $result = $this->redisCache->getRequiredData($result, $requiredGridFields, $havingTYValue, $havingTYValue, $havingLYValue);

        $temp = array();
        $arr = array();
        foreach ($result as $key => $data) {
            $temp["ACCOUNT"] = htmlspecialchars_decode($data['ACCOUNT']);
            $temp["TYEAR"] = $data[$havingTYValue];
            $temp["LYEAR"] = $data[$havingLYValue];
            $arr[] = $temp;
        }
        $this->jsonOutput["POD_$tagName"] = $arr;
    }
}

?>