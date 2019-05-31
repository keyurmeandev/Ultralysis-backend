<?php

namespace classes\lcl;

use datahelper;
use projectsettings;
use filters;
use utils;
use db;
use config;

class ProductView extends config\UlConfig {

    public $customSelectPart;

    public function go($settingVars) {
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES
        $this->queryPart = $this->getAll(); //USES OWN getAll FUNCTION

        $this->settingVars->pageName = (empty($this->settingVars->pageName)) ? $this->settingVars->pageID.'_ViewPage' : $settingVars->pageName;

        $this->redisCache = new utils\RedisCache($this->queryVars);
        
        if ($this->settingVars->isDynamicPage) {
            $this->accountField = $this->getPageConfiguration('account_field', $this->settingVars->pageID)[0];
            $this->distributionField = $this->getPageConfiguration('distribution_field', $this->settingVars->pageID)[0];
            $this->viewpageColumnSettings = $this->getPageConfiguration('viewpage_column_settings', $this->settingVars->pageID);
            $this->measureFilterSettings = $this->getPageConfiguration('measure_filter_settings', $this->settingVars->pageID);

            if (!is_array($this->viewpageColumnSettings) || empty($this->viewpageColumnSettings)) {
                $this->viewpageColumnSettings = array('measure-columns', 'share-columns', 'dist-columns', 'cros-columns', 'uros-columns');
            }

            $this->buildDataArray(array($this->accountField, $this->distributionField));
            $this->buildPageArray();
        }else{
            if (!isset($this->settingVars->pageArray[$this->settingVars->pageName]) || empty($this->settingVars->pageArray[$this->settingVars->pageName]))
                $this->configurationFailureMessage();

            $this->skuID = (isset($this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['ACCOUNT']]) && isset($this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['ACCOUNT']]['ID'])) ? $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['ACCOUNT']]['ID'] : $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['ACCOUNT']]['NAME'];
            $this->skuName = $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['ACCOUNT']]['NAME'];
            $this->distributionID = (isset($this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['DISTRIBUTION']]) && isset($this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['DISTRIBUTION']]['ID'])) ? $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['DISTRIBUTION']]['ID'] : $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['DISTRIBUTION']]['NAME'];
        }

        $this->customSelectPart = "COUNT( DISTINCT(CASE WHEN " . $this->settingVars->ProjectVolume . ">0 AND " . filters\timeFilter::$tyWeekRange . " THEN 1 END )* " . $this->distributionID . " ) AS DIS" .
                                  ",COUNT(DISTINCT(CASE WHEN " . $this->settingVars->ProjectVolume . ">0 AND " . filters\timeFilter::$lyWeekRange . " THEN 1 END )* " . $this->distributionID . " ) AS DISLY";
                                  
        if (!isset($_REQUEST["fetchConfig"]) )
            $this->prepareGridData(); //ADDING TO OUTPUT

        return $this->jsonOutput;
    }

    function prepareGridData() {
        $arr = array();
        $temp = array();

        if (filters\timeFilter::$ToYear == filters\timeFilter::$FromYear)
            $week = (filters\timeFilter::$ToWeek - filters\timeFilter::$FromWeek) + 1;
        else
            $week = ( filters\timeFilter::$ToWeek) + (52 - filters\timeFilter::$FromWeek) + 1;

        /* Custome Part */
        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $this->measureFields[] = $this->skuID;
        $this->measureFields[] = $this->skuName;
        $this->measureFields[] = $this->distributionID;
        
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }

        $this->queryPart = $this->getAll();

        $query = "SELECT ". ($this->skuID!='' ? $this->skuID." AS ID, " : "" ) .$this->skuName." AS ACCOUNT " .
            (!empty($this->customSelectPart) ? ", " . $this->customSelectPart : "") .
            ",CONCAT(".$this->settingVars->yearperiod.", LPAD(".$this->settingVars->weekperiod.", 2, '0')) AS YEARWEEK".
            ",MAX(CASE WHEN  " . filters\timeFilter::$tyWeekRange . " THEN 'TY' ELSE 'LY' END) AS TYLY" .
            ",SUM((CASE WHEN  " . filters\timeFilter::$tyWeekRange . " THEN 1 ELSE 0 END) *" . $this->settingVars->ProjectVolume . ") AS VOLUMETY" .
            ",SUM((CASE WHEN  " . filters\timeFilter::$lyWeekRange . " THEN 1 ELSE 0 END) *" . $this->settingVars->ProjectVolume . ") AS VOLUMELY" .
            ",SUM((CASE WHEN  " . filters\timeFilter::$tyWeekRange . " THEN 1 ELSE 0 END) *" . $this->settingVars->ProjectValue . ") AS VALUE_TY" .
            ",SUM((CASE WHEN  " . filters\timeFilter::$lyWeekRange . " THEN 1 ELSE 0 END) *" . $this->settingVars->ProjectValue . ") AS VALUE_LY" .
            " FROM " . $this->settingVars->tablename . $this->queryPart .
            " AND (" . filters\timeFilter::$tyWeekRange . " OR " . filters\timeFilter::$lyWeekRange . ") " .
            "GROUP BY ".($this->skuID!='' ? " ID, " : "" )." ACCOUNT, YEARWEEK";

        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);

        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }        
        
        $cros = $uros = $disList = array();
        if(is_array($result) && !empty($result))
        {
            foreach($result as $data) {
                if (isset($data['ID']))
                    $accountId = $data['ID'].$data['ACCOUNT'];
                else
                    $accountId = $data['ACCOUNT'];

                $disList[$accountId]["DIS"] += $data['DIS'];
                $disList[$accountId]["DISLY"] += $data['DISLY'];

                if ($data['DIS'] > 0 && $data['VALUE_TY'] > 0 && $data['TYLY'] == "TY") {
                    $cros[$accountId]["TY"] +=  $data['VALUE_TY'] / $data['DIS'];
                }

                if ($data['DISLY'] > 0 && $data['VALUE_LY'] > 0 && $data['TYLY'] == "LY") {
                    $cros[$accountId]["LY"] +=  $data['VALUE_LY'] / $data['DISLY'];
                }

                if ($data['DIS'] > 0 && $data['VOLUMETY'] > 0 && $data['TYLY'] == "TY") {
                    $uros[$accountId]["TY"] +=  $data['VOLUMETY'] / $data['DIS'];
                }

                if ($data['DISLY'] > 0 && $data['VOLUMELY'] > 0 && $data['TYLY'] == "LY") {
                    $uros[$accountId]["LY"] +=  $data['VOLUMELY'] / $data['DISLY'];
                }
                
            }
        }
        /* Custome Part */
        
        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $this->measureFields[] = $this->skuID;
        $this->measureFields[] = $this->skuName;
        
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }

        $this->queryPart = $this->getAll();
        
        $projectStructureTypePage = "projectstructure\\".$this->settingVars->projectStructureType."_PAGES";
        $structurePageClass = new $projectStructureTypePage();

        $measureSelectionArr = $orderBy = array();

        if (is_array($this->userSelectedMeasure) && !empty($this->userSelectedMeasure)) {
            foreach ($this->userSelectedMeasure as  $measure) {
                $measureArrTmp = $orderByTmp = [];
                list($measureArrTmp, $orderByTmp, $measureKey) = $structurePageClass->tyLyMeasurePageLogic($this->settingVars, $this->queryVars, $measure);

                $measureSelectionArr = array_merge($measureSelectionArr,$measureArrTmp[$measureKey]);
                
                if(!empty($orderByTmp))
                    $orderBy[] = $orderByTmp." DESC";
            }
        }

        $result = $this->prepareMeasureSelectPart();
        
        $query = "SELECT " . ($this->skuID!='' ? $this->skuID." AS ID, " : "" ) . $this->skuName." AS ACCOUNT ". 
            (count($measureSelectionArr) > 0 ? ", ". implode(",", $measureSelectionArr)." " : "").
            " FROM " . $this->settingVars->tablename . $this->queryPart .
            " AND (" . filters\timeFilter::$tyWeekRange . " OR " . filters\timeFilter::$lyWeekRange . ") " .
            "GROUP BY ".($this->skuID!='' ? " ID, " : "" )." ACCOUNT";

        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);

        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }
        
        $tempResult = $sumArray = array();

        if(isset($result) && !empty($result))
        {
            foreach($this->userSelectedMeasure as $measure)
            {
                $sumArray["TY_".$measure['measureAlias']."_SUM"] = array_sum(array_column($result,"TY_".$measure['measureAlias']));
                $sumArray["LY_".$measure['measureAlias']."_SUM"] = array_sum(array_column($result,"LY_".$measure['measureAlias']));
            }

            foreach ($result as $key => $data) 
            {
                if (isset($data['ID']))
                    $accountId = $data['ID'].$data['ACCOUNT'];
                else
                    $accountId = $data['ACCOUNT'];

                $idValue = ($this->skuID!='') ? $data['ID'] : htmlspecialchars_decode($data['ACCOUNT']);
                
                $tempMeasureData = array();
                if(isset($tempResult[$idValue]))
                    $isNew = true;
                else
                    $isNew = false;

                foreach($this->userSelectedMeasure as $mKey => $measure)
                {
                    $tempResult[$idValue]['TY_'.$measure['measureAlias']] = (double)$data["TY_".$measure['measureAlias']];
                    $tempResult[$idValue]['LY_'.$measure['measureAlias']] = (double)$data["LY_".$measure['measureAlias']];

                    if($isNew)
                    {
                       $tempMeasureData[] = $tempResult[$idValue]['TY_'.$measure['measureAlias']];
                       $tempMeasureData[] = $tempResult[$idValue]['LY_'.$measure['measureAlias']];                            
                    }

                    $var = ($tempResult[$idValue]['LY_'.$measure['measureAlias']] != 0) ? ((($tempResult[$idValue]['TY_'.$measure['measureAlias']] - $tempResult[$idValue]['LY_'.$measure['measureAlias']]) / $tempResult[$idValue]['LY_'.$measure['measureAlias']]) * 100) : 0;
                
                    $tempResult[$idValue]['VAR_'.$measure['measureAlias']] = (double)number_format($var, 1, '.', '');

                    if (in_array("share-columns", $this->viewpageColumnSettings)) 
                    {
                        $total = $sumArray["TY_".$measure['measureAlias']."_SUM"];
                        $totalSALESLY = $sumArray["LY_".$measure['measureAlias']."_SUM"];

                        $share = $total != 0 ? (($tempResult[$idValue]['TY_'.$measure['measureAlias']] / $total) * 100) : 0;
                        $shareLY = $totalSALESLY != 0 ? (($tempResult[$idValue]['LY_'.$measure['measureAlias']] / $totalSALESLY) * 100) : 0;

                        $tempResult[$idValue]['SHARE_TY_'.$measure['measureAlias']] = (double)number_format($share, 1, '.', '');
                        $tempResult[$idValue]['SHARE_LY_'.$measure['measureAlias']] = (double)number_format($shareLY, 1, '.', '');
                    }
                }

                if($isNew && array_sum($tempMeasureData) == 0)
                {
                    unset($tempResult[$idValue]);
                    continue;
                }

                $tempResult[$idValue]['SKUID'] = $idValue;
                $tempResult[$idValue]['SKU'] = htmlspecialchars_decode($data['ACCOUNT']);

                if (in_array("cros-columns", $this->viewpageColumnSettings)) {
                    $crosTyVal = (isset($cros[$accountId]) && isset($cros[$accountId]['TY']) && $cros[$accountId]['TY'] != 0) ? ($cros[$accountId]['TY']/$week) : 0;
                    $crosLyVal = (isset($cros[$accountId]) && isset($cros[$accountId]['LY']) && $cros[$accountId]['LY'] != 0) ? ($cros[$accountId]['LY']/$week) : 0;

                    // $crosLyVal = ($disList[$accountId]['DISLY'] != 0) ? ($data['VALUE_LY'] / $disList[$accountId]['DISLY'])/$week : 0;
                    $tempResult[$idValue]['CROS'] = (double)number_format($crosTyVal, 2, '.', '');
                    $tempResult[$idValue]['CROSLY'] = (double)number_format($crosLyVal, 2, '.', '');
                }

                if (in_array("uros-columns", $this->viewpageColumnSettings)) {
                    // $urosTyVal = ($disList[$accountId]['DIS'] > 0) ? ($data['VOLUMETY'] / $disList[$accountId]['DIS'])/$week : 0;
                    // $urosLyVal = ($disList[$accountId]['DISLY'] > 0) ? ($data['VOLUMELY'] / $disList[$accountId]['DISLY'])/$week : 0;

                    $urosTyVal = (isset($uros[$accountId]) && isset($uros[$accountId]['TY']) && $uros[$accountId]['TY'] != 0) ? ($uros[$accountId]['TY']/$week) : 0;
                    $urosLyVal = (isset($uros[$accountId]) && isset($uros[$accountId]['LY']) && $uros[$accountId]['LY'] != 0) ? ($uros[$accountId]['LY']/$week) : 0;

                    $tempResult[$idValue]['UROS'] = (double)number_format($urosTyVal, 2, '.', '');
                    $tempResult[$idValue]['UROSLY'] = (double)number_format($urosLyVal, 2, '.', '');
                }

                if (in_array("dist-columns", $this->viewpageColumnSettings)) {
                    $tempResult[$idValue]['DIS'] = (double)number_format(($disList[$accountId]['DIS']/$week), 1, '.', '');
                    $tempResult[$idValue]['DISLY'] = (double)number_format(($disList[$accountId]['DISLY']/$week), 1, '.', '');
                }
            }
        }
        $this->jsonOutput["gridValue"] = array_values($tempResult);
    }

    public function buildPageArray() {

        $accountFieldPart = explode("#", $this->accountField);
        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true'){
            $this->jsonOutput['pageConfig']['enabledFilters'] = $this->getPageConfiguration('filter_settings', $this->settingVars->pageID);
            $this->jsonOutput["gridColumns"]['SKUID'] = (count($accountFieldPart) > 1) ? $this->displayCsvNameArray[$accountFieldPart[1]] : $this->displayCsvNameArray[$accountFieldPart[0]];
            $this->jsonOutput["gridColumns"]['SKU'] = $this->displayCsvNameArray[$accountFieldPart[0]];
            $this->jsonOutput['pageConfig']['enabledColumns'] = $this->viewpageColumnSettings;

            /* Pagination Settings Starts */
            $pagination_settings_arr = $this->getPageConfiguration('pagination_settings', $this->settingVars->pageID);
            if(count($pagination_settings_arr) > 0){
                $this->jsonOutput['is_pagination'] = $pagination_settings_arr[0];
                $this->jsonOutput['per_page_row_count'] = (int) $pagination_settings_arr[1];
            }
            /* Pagination Settings End */
        }        

        if(in_array("measure-columns", $this->viewpageColumnSettings))
        {
            $this->userSelectedMeasure = array();

            // Dynamic
            if(is_array($this->measureFilterSettings) && !empty($this->measureFilterSettings))
            {
                if(is_array($this->settingVars->measureArray) && !empty($this->settingVars->measureArray)) {
                    foreach ($this->settingVars->measureArray as  $mkey => $measure) {
                        if (in_array(str_replace("M", "", $mkey), $this->measureFilterSettings)) {
                            $this->userSelectedMeasure[] = array(
                                'measureID' => $mkey,
                                'measureName' => $measure['measureName'],
                                'measureAlias' => $measure['ALIASE'],
                                'decimalPlaces' => $measure['dataDecimalPlaces']
                            );
                        }
                    }
                }
            }
            else
            {
                if(is_array($this->settingVars->measureArray) && !empty($this->settingVars->measureArray)) {
                    foreach ($this->settingVars->pageArray["MEASURE_SELECTION_LIST"] as  $mkey => $measure) {
                        $this->userSelectedMeasure[] = array(
                            'measureID' => "M".$measure['measureID'],
                            'measureName' => $measure['measureName'],
                            'measureAlias' => $this->settingVars->measureArray['M'.$measure['measureID']]['ALIASE'],
                            'decimalPlaces' => (isset($this->settingVars->measureArray['M'.$measure['measureID']]['dataDecimalPlaces']) ? $this->settingVars->measureArray['M'.$measure['measureID']]['dataDecimalPlaces'] : 0)
                        );
                        $ALIASE = $this->settingVars->measureArray['M'.$measure['measureID']]['ALIASE'];
                    }
                }
            }

            if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true')
                $this->jsonOutput["userSelectedMeasure"] = $this->userSelectedMeasure;
        }        

        $accountField = strtoupper($this->dbColumnsArray[$accountFieldPart[0]]);
        $accountField = (count($accountFieldPart) > 1) ? strtoupper($accountField."_".$this->dbColumnsArray[$accountFieldPart[1]]) : $accountField;
        
        $this->skuID = (isset($this->settingVars->dataArray[$accountField]) && isset($this->settingVars->dataArray[$accountField]['ID'])) ? $this->settingVars->dataArray[$accountField]['ID'] : $this->settingVars->dataArray[$accountField]['NAME'];
        $this->skuName = $this->settingVars->dataArray[$accountField]['NAME'];
        
        $distributionFieldPart = explode("#", $this->distributionField);
        $distributionField = strtoupper($this->dbColumnsArray[$distributionFieldPart[0]]);
        $distributionField = (count($distributionFieldPart) > 1) ? strtoupper($distributionField."_".$this->dbColumnsArray[$distributionFieldPart[1]]) : $distributionField;


        $this->distributionID = (isset($this->settingVars->dataArray[$distributionField]) && isset($this->settingVars->dataArray[$distributionField]['ID'])) ? $this->settingVars->dataArray[$distributionField]['ID'] : $this->settingVars->dataArray[$distributionField]['NAME'];

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