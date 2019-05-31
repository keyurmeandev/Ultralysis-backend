<?php
namespace classes;

use datahelper;
use projectsettings;
use filters;
use utils;
use db;
use config;

class RevealStats extends SummaryPage {

    private $TY_total;      //Share_By_Name Function uses this to create 'TOTAL' tag
    private $LY_total;      //Share_By_Name Function uses this to create 'TOTAL' tag

    /**
     * Default gateway function, should be similar in all DATA CLASS
     * 
     * @param array $settingVars project settingsGateway variables
     *
     * @return Array
     */
    public function go($settingVars) {
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES
        $this->queryPart = $this->getAll();  //PREPARE TABLE JOIN STRING USING PARENT getAll

        $this->settingVars->useRequiredTablesOnly = false;
        
        $this->redisCache = new utils\RedisCache($this->queryVars);
 
		$this->getYTDPeriod();
		$this->fetch_totalSales();
		$this->getTopBrands();
		
		$this->maps = $this->settingVars->pageArray['REVEAL_TREEMAP']["MAPS"];
		        
        //PREPAREING MAPS DATA        
        foreach ($this->maps as $name=>$field){			
			$tabList = array();
			$tabList["data"] = $field;
			$tabList["name"] = $name;			
			$this->jsonOutput["TREE_TAB_LIST"][] = $tabList;			
			$this->Tree($this->settingVars->dataArray[$field]['NAME'], $field, $field);
		}				
		
        return $this->jsonOutput;
    }

	
    /**
     * getYTDData()
     * It fetch data to show in YTD date range
     * 
     * @return void
     */
    private function getYTDPeriod() {

        //COLLECTING YTD TIME RANGE
        filters\timeFilter::getYTD($this->settingVars);
        $fromWeek = filters\timeFilter::$FromWeek;
        $toWeek = filters\timeFilter::$ToWeek;	
		$fromYear = filters\timeFilter::$FromYear;
        $toYear = filters\timeFilter::$ToYear;
		
        $this->jsonOutput['YTD_PERIOD'] = $fromWeek.'-'.$fromYear.' To '.$toWeek.'-'.$toYear;
    }
	
	/**
     * getTotalSales()
     * It fetch data to show in YTD sales
     * 
     * @return void
     */
    private function getTotalSales() {
        // $this->ValueVolume = getValueVolume($this->settingVars);
		$VAR_total = 0;
        $items = explode("#", filters\timeFilter::getLatest_n_dates_ly(0, 52, $this->settingVars));
        $latest52Week = $items[0];
        $previous52Week = $items[1];

        //COLLECTING YTD TIME RANGE
        filters\timeFilter::getYTD($this->settingVars);
        $ytdTyWeekRange = filters\timeFilter::$tyWeekRange;
        $ytdLyWeekRange = filters\timeFilter::$lyWeekRange;


        $query = "SELECT " .
                "SUM((CASE WHEN " . $ytdTyWeekRange . " THEN 1 ELSE 0 END)* " . $this->ValueVolume . ") AS YTD_TY" .
                ",SUM((CASE WHEN " . $ytdLyWeekRange . " THEN 1 ELSE 0 END)* " . $this->ValueVolume . ") AS YTD_LY " .
                "FROM " . $this->settingVars->tablename . $this->queryPart .
                " AND CONCAT(" . $this->settingVars->yearperiod . "," . $this->settingVars->weekperiod . ") IN  (" . $latest52Week . "," . $previous52Week . ") ";
		//echo $query;exit;
        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);

        if (is_array($result) && !empty($result)) {

            $row = array();
            $data = $result[0];

            $row['YTD_TY'] = number_format($data['YTD_TY'], 0);
            $row['YTD_VAR'] = number_format(($data['YTD_TY'] - $data['YTD_LY']), 0);
            $row['YTD_PRFMNC'] = ($data['YTD_LY'] > 0) ? number_format((($data['YTD_TY'] / $data['YTD_LY']) - 1) * 100, 1, '.', '') : "0";
            $row['YTD_LY'] = number_format($data['YTD_LY'], 0);


            $row['YTD_VAR'] = ($row['YTD_VAR'] > 0) ? '+' . $row['YTD_VAR'] . '' : $row['YTD_VAR'];
            $row['YTD_PRFMNC'] = ($row['YTD_PRFMNC'] > 0) ? '+' . $row['YTD_PRFMNC'] . '' : $row['YTD_PRFMNC'];


			$this->LY_total = number_format($data['YTD_LY'], 0, '', '');
			$this->TY_total = number_format($data['YTD_TY'], 0, '', '');
			$VAR_total = $row['YTD_PRFMNC'];
			$totalLabelLy = 'LY';
			$totalLabelTy = 'YTD';



            $this->jsonOutput['totalSales'] = array(
                array(
                    'ACCOUNT' => $totalLabelTy,
                    'SALES' => $this->TY_total
                ),
                array(
                    'ACCOUNT' => $totalLabelLy,
                    'SALES' => $this->LY_total
                )
            );
		}
		
		$this->jsonOutput['TotalSaleVar'] = $VAR_total;
    }
	
	/**
     * getTopBrands()
     * It fetch data to show in top 5 sales
     * 
     * @return void
     */
    private function getTopBrands() {
		$VAR_total = 0;
		$dataArray = array();
        /*$items = explode("#", filters\timeFilter::getLatest_n_dates_ly(0, 52, $this->settingVars));
        $latest52Week = $items[0];
        $previous52Week = $items[1];

        //COLLECTING YTD TIME RANGE
        filters\timeFilter::getYTD($this->settingVars);
        $ytdTyWeekRange = filters\timeFilter::$tyWeekRange;
        $ytdLyWeekRange = filters\timeFilter::$lyWeekRange;*/

        $brandField = $this->settingVars->dataArray['F2']['NAME'];

        $this->measureFields[] = $brandField;
        $this->prepareTablesUsedForQuery($this->measureFields);
        $this->settingVars->useRequiredTablesOnly = true;
        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll

        /* Make dynamic measuer bases on request params -- START */
        $measureKey = 'M' . $_REQUEST['ValueVolume'];
        $measure = $this->settingVars->measureArray[$measureKey];
        
        if (!empty(filters\timeFilter::$tyWeekRange)) 
        {
            $measureTYValue = "YTD_TY";
            $options['tyLyRange'][$measureTYValue] = trim(filters\timeFilter::$tyWeekRange);
            $measureTYValue = "YTD_LY";
            $options['tyLyRange'][$measureTYValue] = trim(filters\timeFilter::$lyWeekRange);
        }
        
        $measureSelectionArr = config\MeasureConfigure::prepareSelect($this->settingVars, $this->queryVars, array($measureKey), $options);

        $query = "SELECT DISTINCT($brandField) as BRAND, " . implode(",", $measureSelectionArr) . " " .
                "FROM " . $this->settingVars->tablename . $this->queryPart . " " .
                "AND (" . filters\timeFilter::$tyWeekRange . " OR " . filters\timeFilter::$lyWeekRange . ") ".
                "ORDER BY YTD_TY DESC LIMIT 0,5";
		//echo $query;exit;
        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);

        if (is_array($result) && !empty($result)) {
			foreach($result as $data){
				$row = array();
				$row['ACCOUNT']	= $data['BRAND'];			
				$row['YTD_TY'] = number_format($data['YTD_TY'], 0);
				$row['YTD_VAR'] = number_format(($data['YTD_TY'] - $data['YTD_LY']), 0);
				$row['YTD_PRFMNC'] = ($data['YTD_LY'] > 0) ? number_format((($data['YTD_TY'] / $data['YTD_LY']) - 1) * 100, 1, '.', '') : "0";
				$row['YTD_LY'] = number_format($data['YTD_LY'], 0);

				$row['YTD_VAR'] = ($row['YTD_VAR'] > 0) ? '+' . $row['YTD_VAR'] . '' : $row['YTD_VAR'];
				$row['YTD_PRFMNC'] = ($row['YTD_PRFMNC'] > 0) ? '+' . $row['YTD_PRFMNC'] . '' : $row['YTD_PRFMNC'];

				$dataArray[] = $row;
			}
			
		}
		
		$this->jsonOutput['TOP_BRANDS'] = $dataArray;
    }
	

	public function Tree($name, $tagName, $indexInDataArray) {
        $negcolor = array('EE0202', 'D20202', 'B50202', 'A00202', '8C0101', '760101', '640101', '510101', '400101', '2E0101');
        $color = array('002D00', '014301', '015901', '016B01', '018001', '019701', '01AC01', '02C502', '02DB02', '02FB02');
        //$negcolor = array('0xEE0202', '0xD20202', '0xB50202', '0xA00202', '0x8C0101', '0x760101', '0x640101', '0x510101', '0x400101', '0x2E0101');
        //$color = array('0x002D00', '0x014301', '0x015901', '0x016B01', '0x018001', '0x019701', '0x01AC01', '0x02C502', '0x02DB02', '0x02FB02');

        $dataStore = array();
        $max = 0;
        $min = 0;

        $this->measureFields[] = $name;
        $this->prepareTablesUsedForQuery($this->measureFields);
        $this->settingVars->useRequiredTablesOnly = true;
        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll

        /* Make dynamic measuer bases on request params -- START */
        $measureKey = 'M' . $_REQUEST['ValueVolume'];
        $measure = $this->settingVars->measureArray[$measureKey];
        
        if (!empty(filters\timeFilter::$tyWeekRange)) 
        {
            $measureTYValue = "TYEAR";
            $options['tyLyRange'][$measureTYValue] = trim(filters\timeFilter::$tyWeekRange);
            $measureTYValue = "LYEAR";
            $options['tyLyRange'][$measureTYValue] = trim(filters\timeFilter::$lyWeekRange);
        }
        
        $measureSelectionArr = config\MeasureConfigure::prepareSelect($this->settingVars, $this->queryVars, array($measureKey), $options);


        $query = "SELECT $name AS ACCOUNT, " . implode(",", $measureSelectionArr) . " " .
                "FROM " . $this->settingVars->tablename . $this->queryPart . " " .
                "AND (" . filters\timeFilter::$tyWeekRange . " OR " . filters\timeFilter::$lyWeekRange . ") ".
                "GROUP BY $name " .
                "ORDER BY TYEAR DESC LIMIT 250 ";
		//echo $query;exit;
        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);

        foreach ($result as $key => $row) {
            $row['ACCOUNT'] = str_replace('\'', ' ', $row['ACCOUNT']);
            $thisyearval = $row['TYEAR'];
            $lastyearval = $row['LYEAR'];

            if ($lastyearval > 0) {
                $var = (($thisyearval - $lastyearval) / $lastyearval) * 100;
                if ($var > $max)
                    $max = $var;
                if ($var < $min)
                    $min = $var;
                array_push($dataStore, $row['ACCOUNT'] . "#" . $thisyearval . "#" . $var);
            }else {
                $var = 0;
                array_push($dataStore, $row['ACCOUNT'] . "#" . $thisyearval . "#" . $var);
            }
        }

        for ($i = 0; $i < count($dataStore); $i++) {
            $d = explode('#', $dataStore[$i]);

            if ($this->TOTAL_TY_SALES == 0 || $this->TOTAL_TY_SALES == NULL) {
                $percent = number_format(0);
            } else {
                $percent = number_format(($d[1] / $this->TOTAL_TY_SALES) * 100, 1);
                $chartval2 = number_format((($this->TOTAL_TY_SALES - $d[1]) / $this->TOTAL_TY_SALES) * 100, 1);
            }


            if ($d[2] >= 0) {
                $c = 0;
                $range = 10;
                for ($j = 0; $j <= $max; $j+=$range) {
                    if ($d[1] > 0) {
                        if (number_format($d[2], 2, '.', '') >= 100) {
                            $temp = array(
                                //'@attributes' => array(
                                    'name' => htmlspecialchars_decode(strtoupper($d[0]))
                                    , 'value' => $d[1]
                                    , 'color' => $color[9]
                                    , 'alpha' => 1
                                    , 'varp' => $d[2]
                                    , 'chartval1' => $percent
                                    , 'chartval2' => $chartval2
                               // )
                            );
                            $this->jsonOutput[$tagName][] = $temp;
                            break;
                        } else {
                            if ((number_format($d[2], 5, '.', '') >= number_format($j, 5, '.', '')) && (number_format($d[2], 5, '.', '') < number_format(($j + $range), 5, '.', ''))) {
                                $temp = array(
                                    //'@attributes' => array(
                                        'name' => htmlspecialchars_decode(strtoupper($d[0]))
                                        , 'value' => $d[1]
                                        , 'color' => $color[$c]
                                        , 'alpha' => 1
                                        , 'varp' => $d[2]
                                        , 'chartval1' => $percent
                                        , 'chartval2' => $chartval2
                                    //)
                                );
                                $this->jsonOutput[$tagName][] = $temp;
                                break;
                            }
                            $c++;
                        }
                    }
                }
            } else {
                $c = 0;
                $range = abs($min / 10);
                for ($j = $min; $j <= 0; $j+=$range) {
                    if ((number_format($d[2], 5, '.', '') >= number_format($j, 5, '.', '')) && (number_format($d[2], 5, '.', '') < number_format(($j + $range), 5, '.', ''))) {
                        $temp = array(
                            //'@attributes' => array(
                                'name' => htmlspecialchars_decode(strtoupper($d[0]))
                                , 'value' => $d[1]
                                , 'color' => $negcolor[$c]
                                , 'alpha' => 1
                                , 'varp' => $d[2]
                                , 'chartval1' => $percent
                                , 'chartval2' => $chartval2
                            //)
                        );
                        $this->jsonOutput[$tagName][] = $temp;
                        break;
                    }
                    $c++;
                }
            }
        }
    }
	
    /**
     * getAll()
     * COLLECTS TABLE JOINING STRING FROM PROJECT SETTINGS
     * ALSO APPLYS FILTER CONDITIONS TO THE STRING
     * AND RETURNS $tablejoins_and_filters
     *
     * @return String
     */
    public function getAll() {
        $tablejoins_and_filters = $this->settingVars->link;

        return $tablejoins_and_filters;
    }

}

?>