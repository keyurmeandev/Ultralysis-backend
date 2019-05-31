<?php

namespace classes;

use projectsettings;
use datahelper;
use filters;
use db;
use config;
use utils;

class ProductKBI extends config\UlConfig {

    public $totalSales;
    public $skuID, $skuName, $countField;
    public $pageName;
    public $displayCsvNameArray;
    public $dbColumnsArray;
    public $customSelectPart;
    public $disp_column_options;
    public $kbi_rank;
    private $objPHPExcel;
    private $skuMode;
    private $gain_start_letter, $gain_end_letter, $maintain_start_letter, $maintain_end_letter, $lost_start_letter, $lost_end_letter;
    private $accountArr; //STORES ACCOUNT LIST SENT FROM FRONT-END
    private $accountHeaders; //STORES ACCOUNT ALIASE LIST DERIVED FROM $accountArr
    private $distribution_pp_totalData, $distribution_ty_totalData;
    private $gainArr, $maintainArr, $lostArr;
    private $queryPartsArr;


    /*     * ***
     * Default gateway function, should be similar in all DATA CLASS
     * arguments:
     *  $settingVars [project settingsGateway variables]
     * @return $jsonOutput with all data that should be sent to client app
     * *** */

    public function go($settingVars) {
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES		

        $this->settingVars->pageName = (empty($this->settingVars->pageName)) ? $this->settingVars->pageID . '_KBIPage' : $this->settingVars->pageName;

        $this->redisCache = new utils\RedisCache($this->queryVars);

        if ($this->settingVars->isDynamicPage) {
            $this->gridField = $this->getPageConfiguration('grid_field', $this->settingVars->pageID)[0];
            $this->countField = $this->getPageConfiguration('count_field', $this->settingVars->pageID)[0];
            $this->accountFields = $this->getPageConfiguration('account_list_field', $this->settingVars->pageID);
            $this->columnGroups = $this->getPageConfiguration('column_groups', $this->settingVars->pageID);

            $this->disp_column_options = $this->getPageConfiguration('disp_column_options', $this->settingVars->pageID);
            $this->kbi_rank = $this->getPageConfiguration('kbi_rank', $this->settingVars->pageID);
            if(!empty($this->kbi_rank) && isset($this->kbi_rank[0]))
                $this->kbi_rank = $this->kbi_rank[0];

            $tempBuildFieldsArray = array($this->gridField, $this->countField);
            if(is_array($this->accountFields) && !empty($this->accountFields))
                $tempBuildFieldsArray = array_merge($tempBuildFieldsArray, $this->accountFields);
            
            $buildDataFields = array();
            foreach ($tempBuildFieldsArray as $value)
                if (!empty($value) && !in_array($value, $buildDataFields))
                    $buildDataFields[] = $value;
            
            if(isset($_REQUEST["fetchConfig"]) &&  $_REQUEST["fetchConfig"] == true) {
                $pagination_settings_arr = $this->getPageConfiguration('pagination_settings', $this->settingVars->pageID);
                if(count($pagination_settings_arr) > 0){
                    $this->jsonOutput['is_pagination'] = $pagination_settings_arr[0];
                    $this->jsonOutput['per_page_row_count'] = (int) $pagination_settings_arr[1];
                }
            }



            $this->buildDataArray($buildDataFields);
            $this->buildPageArray();
        } else {
            if (!isset($this->settingVars->pageArray[$this->settingVars->pageName]) || empty($this->settingVars->pageArray[$this->settingVars->pageName]))
                $this->configurationFailureMessage();

            $this->accountID = (isset($this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['ACCOUNT']]) && isset($this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['ACCOUNT']]['ID'])) ? $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['ACCOUNT']]['ID'] : $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['ACCOUNT']]['NAME'];

            $this->accountName = $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['ACCOUNT']]['NAME'];
            $this->countField = $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['COUNT_FIELD']]['NAME'];
        }

        if (!isset($_REQUEST["fetchConfig"]) || empty($_REQUEST["fetchConfig"]) || $_REQUEST["fetchConfig"] != 'true') {
            //$this->queryPart = $this->getAll();
            filters\timeFilter::getSlice($this->settingVars);
            filters\timeFilter::getExtraSlice_ByQuery($this->settingVars);
            
            // filters\timeFilter::calculateTotalWeek($this->settingVars);
            $this->totalWeeks = filters\timeFilter::$totalWeek;
        }
        
        $action = $_REQUEST['action'];

        $this->isexport = ($action == 'exportXls') ? true : false;

        $this->timeMode = $_REQUEST['timeMode'];

        $action = $_REQUEST["action"];
        switch ($action) {
            case "exportXls":
                if (empty($this->timeMode)) {
                    $this->customSelectPart();
                    $this->prepareSummaryData(); //ADDING TO OUTPUT
                    $this->prepareMainGridData(); //ADDING TO OUTPUT
                } else {
                    $this->exportPPLYXls();
                }
                break;
            default:
                $this->defaultLoad();
                break;
        }
        return $this->jsonOutput;
    }

    public function defaultLoad() {
        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
        
            $this->jsonOutput['pageConfig'] = array(
                'enabledFilters' => $this->getPageConfiguration('filter_settings', $this->settingVars->pageID)
            );
            $this->jsonOutput['gridColumnConfig'] = array(
                'enabledColumnGroup' => $this->columnGroups,
                'dispColumnOptions'  => $this->disp_column_options,
                'kbiRank'            => $this->kbi_rank
            );
            $this->jsonOutput['gridColumns'] = $this->gridColumns;
        }else{
            $this->customSelectPart();
            $this->prepareSummaryData(); //ADDING TO OUTPUT
            $this->prepareMainGridData(); //ADDING TO OUTPUT
        }
    }

    public function prepareSummaryData() {

        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $this->measureFields[] = $this->countField;
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->queryPart = $this->getAll();

        /*         * **************** CALCULATING DIST TOTALS FOR [TY,LY,PP] ************************************** */
        $query = "SELECT " . $this->customSelectPart .
                "FROM " . $this->settingVars->tablename . " " . $this->queryPart . " AND " . $this->settingVars->ProjectVolume . ">0 " .
                "AND (" . filters\timeFilter::$tyWeekRange . " OR " . filters\timeFilter::$ppWeekRange . " OR " . filters\timeFilter::$lyWeekRange . ") ";
        //echo $query; exit;
        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
        $this->totalDist = $result[0];
    }

    public function getMeasuresPart($setOnlyPP = '') {
        $measureSelectionArr = array();
        foreach ($this->settingVars->measureArray_for_productKBI_only as $key => $measure) {
            $measureFields = (isset($measure['usedFields']) && !empty($measure['usedFields'])) ? $measure['usedFields'] : array();
            if (is_array($measureFields) && !empty($measureFields)) {
                foreach ($measureFields as $key => $value) {
                    $this->measureFields[] = $value;
                }
            }
            if ($measure['attr'] == 'SUM') {
                if($setOnlyPP == 'Y') {
                    $measureSelectionArr[] = "SUM( (CASE WHEN " . filters\timeFilter::$ppWeekRange . " THEN 1 ELSE 0 END) * " . $measure['VAL'] . ") AS PP" . $measure['ALIASE'];
                } else {
                    $measureSelectionArr[] = "SUM( (CASE WHEN " . filters\timeFilter::$tyWeekRange . " THEN 1 ELSE 0 END) * " . $measure['VAL'] . ") AS TY" . $measure['ALIASE'];
                    $measureSelectionArr[] = "SUM( (CASE WHEN " . filters\timeFilter::$lyWeekRange . " THEN 1 ELSE 0 END) * " . $measure['VAL'] . ") AS LY" . $measure['ALIASE'];
                }
            }
        }

        /*
         * Usefull when debuggin, DON'T DELETE
          header('Content-Type: application/json');
          print_r($measureSelectionArr);exit;
         */

        return implode(",", $measureSelectionArr);
    }

    public function customSelectPart() {
        $this->customSelectPart = "COUNT(DISTINCT(CASE WHEN " . filters\timeFilter::$tyWeekRange . " AND " . $this->settingVars->ProjectVolume . " > 0 THEN " . $this->countField . " END)) AS TP_DIST" .
                ",COUNT(DISTINCT (CASE WHEN " . filters\timeFilter::$lyWeekRange . " AND " . $this->settingVars->ProjectVolume . " > 0 THEN " . $this->countField . " END)) AS LY_DIST" .
                ",COUNT(DISTINCT (CASE WHEN " . filters\timeFilter::$ppWeekRange . " AND " . $this->settingVars->ProjectVolume . " > 0 THEN " . $this->countField . " END)) AS PP_DIST ";
    }

    public function prepareMainGridData() {
        /*         * **************** CALCULATING DISTRIBUTIONS FOR ACCOUNT [TY,LY,PP] ************************************** */
        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $this->measureFields[] = $this->countField;
        $this->measureFields[] = $this->accountID;
        $this->measureFields[] = $this->accountName;

        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $measureSelect = $this->getMeasuresPart('Y');
        $this->queryPart = $this->getAll();

        if($this->accountName == $this->accountID){
            $fieldsName = $this->accountName . " AS ACCOUNT, ";
            $fieldsGroupBy = 'ACCOUNT';
        }else{
            $fieldsName = $this->accountID." AS ID".",".$this->accountName." AS ACCOUNT, ";
            $fieldsGroupBy = 'ID,ACCOUNT';
        }

        $query = "SELECT " . $fieldsName . $measureSelect . ", ".
                $this->customSelectPart .
                "FROM " . $this->settingVars->tablename . " " . $this->queryPart . 
                " AND (" . filters\timeFilter::$tyWeekRange . " OR " . filters\timeFilter::$ppWeekRange . 
                    " OR " . filters\timeFilter::$lyWeekRange . ") " .
                "GROUP BY $fieldsGroupBy ";
        
        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }

        
        
        $arrResPP = $TP_DIST = $PP_DIST = $LY_DIST = array();
        if (is_array($result) && !empty($result)) {
            foreach ($result as $key => $data) {
                $arrayKey = ($this->accountName == $this->accountID) ? $data['ACCOUNT'] : $data['ID'];
                $arrayKey = trim($arrayKey).trim($data['ACCOUNT']);
                $TP_DIST[$arrayKey] = $data['TP_DIST'];
                $PP_DIST[$arrayKey] = $data['PP_DIST'];
                $LY_DIST[$arrayKey] = $data['LY_DIST'];
                unset($data['TP_DIST'], $data['PP_DIST'], $data['LY_DIST']);
                $arrResPP[$arrayKey] = $data;
            }
        }
        
        /** *********************************************************************************************** */
        
        /*[START] PRODUCT MEASURE QUERY */
        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        // $measureSelectRes = $this->prepareMeasureSelectPart();
        // $this->measureFields = $measureSelectRes['measureFields'];

        $this->measureFields[] = $this->accountID;
        $this->measureFields[] = $this->accountName;
        $this->settingVars->useRequiredTablesOnly = true;
        $this->prepareTablesUsedForQuery($this->measureFields);
        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll

        // $measureSelectionArr = $measureSelectRes['measureSelectionArr'];
        $measureSelect = $this->getMeasuresPart();

        if($this->accountName == $this->accountID){
            $fieldsName = $this->accountName . " AS ACCOUNT, ";
            $fieldsGroupBy = 'ACCOUNT';
        }else{
            $fieldsName = $this->accountID." AS ID".",".$this->accountName." AS ACCOUNT, ";
            $fieldsGroupBy = 'ID,ACCOUNT';
        }

        // $query = "SELECT ". $fieldsName . implode(",", $measureSelectionArr).
        $query = "SELECT ". $fieldsName . $measureSelect .
            " FROM " . $this->settingVars->tablename .' '. trim($this->queryPart) .
            " AND (" . filters\timeFilter::$tyWeekRange . " OR " . filters\timeFilter::$lyWeekRange . ") " .
            "GROUP BY $fieldsGroupBy";
        // echo $query;exit();
        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }

        $requiredGridFields = array("ACCOUNT","TYVAL","LYVAL","TYVOL","LYVOL");
        if($this->accountName != $this->accountID){ array_push($requiredGridFields, "ID"); }
        $resultMain = $this->redisCache->getRequiredData($result, $requiredGridFields, 'TYVOL');

        $result = $getAliase = [];

        foreach($this->settingVars->measureArray_for_productKBI_only as $k => $arrVal){
            if($arrVal['VAL'] == 'QTY'){
                $getAliase['TYVOLUME'] = 'TY'.$arrVal['ALIASE'];
                $getAliase['LYVOLUME'] = 'LY'.$arrVal['ALIASE'];
                $getAliase['PPVOL']    = 'PP'.$arrVal['ALIASE'];
            }
            if($arrVal['VAL'] == 'value'){
                $getAliase['TYValue'] = 'TY'.$arrVal['ALIASE'];
                $getAliase['LYValue'] = 'LY'.$arrVal['ALIASE'];
                $getAliase['PPVAL']   = 'PP'.$arrVal['ALIASE'];
            }
        }

        if(is_array($resultMain) && !empty($resultMain)){
            foreach ($resultMain as $k2 => $val) {
                $skuID = ($this->accountName == $this->accountID) ? $val['ACCOUNT'] : $val['ID'];
                $arrayKey = trim($skuID).trim($val['ACCOUNT']);
                $result[] = [
                                'SKU'  => $val['ACCOUNT'],
                                'SKUID'=> $skuID,
                                $getAliase['TYValue']=>$val[$getAliase['TYValue']],
                                $getAliase['LYValue']=>$val[$getAliase['LYValue']],
                                $getAliase['TYVOLUME']=>$val[$getAliase['TYVOLUME']],
                                $getAliase['LYVOLUME']=>$val[$getAliase['LYVOLUME']],
                                $getAliase['PPVOL']=>isset($arrResPP[$arrayKey]) ? $arrResPP[$arrayKey]['PPVOL'] : 0,
                                $getAliase['PPVAL']=>isset($arrResPP[$arrayKey]) ? $arrResPP[$arrayKey]['PPVAL'] : 0,
                            ];
                if(isset($arrResPP[$arrayKey])){
                    unset($arrResPP[$arrayKey]);
                }
            }
        }
        if(isset($arrResPP) && is_array($arrResPP) && count($arrResPP)>0){
            foreach ($arrResPP as $k2 => $resppval) {
                $skuID = ($this->accountName == $this->accountID) ? $resppval['ACCOUNT'] : $resppval['ID'];
                array_push($result, [
                                    'SKU'  =>$resppval['ACCOUNT'],
                                    'SKUID'=>$skuID,
                                    $getAliase['TYValue']  => 0,
                                    $getAliase['LYValue']  => 0,
                                    $getAliase['TYVOLUME'] => 0,
                                    $getAliase['LYVOLUME'] => 0,
                                    $getAliase['PPVOL'] => $resppval[$getAliase['PPVOL']],
                                    $getAliase['PPVAL'] => $resppval[$getAliase['PPVAL']],
                                ]);
            }
        }

        if(!empty($this->kbi_rank) && $this->kbi_rank == 'VALUE'){
            $rankOrderingClm = $getAliase['TYValue'];
            $isRankAct = true;
        }else if(!empty($this->kbi_rank) && $this->kbi_rank == 'VOLUME'){
            $rankOrderingClm = $getAliase['TYVOLUME'];
            $isRankAct = true;
        }else{
            $rankOrderingClm = $getAliase['TYVOLUME'];
            $isRankAct = false;
        }
        $result = utils\SortUtility::sort2DArray($result, $rankOrderingClm, utils\SortTypes::$SORT_DESCENDING);
        $this->gridData = array();
        
        $volArray = $distArray =  array();
        if(is_array($this->disp_column_options) && !empty($this->disp_column_options))
        {
            foreach($this->disp_column_options as $opt)
            {
                if($opt == "TP")
                    $volArray[] = "TYVOL";
                else
                    $volArray[] = $opt."VOL";
                
                $distArray[] = strtolower($opt)."Dist";
            }
        }
        else {
            $volArray = array("TYVOL", "LYVOL", "PPVOL");
            $distArray = array("tpDist", "ppDist", "lyDist");
        }
        
        $rankCounter = 1;
        foreach ($result as $key => $data) {
            
            $checkData = $volArray;
            foreach($volArray as $volkey => $volData)
            {
                if($data[$volData] != 0)
                    unset($checkData[$volkey]);
            }
            
            if(count($checkData) == count($volArray))
                continue;
            
            $temp = array();
            $temp['TPNB'] = $data['SKUID'];
            $temp['SKU'] = htmlspecialchars_decode($data['SKU']);

            $accountSales = array();
            foreach ($this->settingVars->measureArray_for_productKBI_only as $subKey => $measure) {
                if ($measure['attr'] == 'SUM') {
                    if ($key == 0) {
                        $totalSum[$measure['ALIASE']]['TY'] = array_sum(array_column($result, 'TY'.$measure['ALIASE']));
                        $totalSum[$measure['ALIASE']]['LY'] = array_sum(array_column($result, 'LY'.$measure['ALIASE']));
                        $totalSum[$measure['ALIASE']]['PP'] = array_sum(array_column($result, 'PP'.$measure['ALIASE']));
                    }

                    $tempObj = new utils\KBI_MeasureBlock();
                    $tempObj->createMeasureBlock($measure['ALIASE'], $data);

                    $accountSales[$measure['ALIASE']] = $tempObj;
                    $measureName = $tempObj->measureName;

                    $temp[$measureName . "_TP"] = number_format($tempObj->ty, 0, '.', '');
                    $temp[$measureName . "_PP"] = number_format($tempObj->pp, 0, '.', '');
                    $temp[$measureName . "_LY"] = number_format($tempObj->ly, 0, '.', '');
                    $temp[$measureName . "_PP_VAR"] = number_format($tempObj->pp_var_varPct->var, 0, '.', '');
                    $temp[$measureName . "_PP_VAR_PCT"] = number_format($tempObj->pp_var_varPct->varPct, 1, '.', '');
                    $temp[$measureName . "_LY_VAR"] = $tempObj->ly_var_varPct->var;
                    $temp[$measureName . "_LY_VAR_PCT"] = number_format($tempObj->ly_var_varPct->varPct, 1, '.', '');

                    $temp[$measureName . "_TP_SHARE"] = number_format( ($totalSum[$measure['ALIASE']]['TY'] != 0 ? (($tempObj->ty / $totalSum[$measure['ALIASE']]['TY']) * 100) : 0), 1, '.', '');
                    $temp[$measureName . "_PP_SHARE"] = number_format( ($totalSum[$measure['ALIASE']]['PP'] != 0 ? (($tempObj->pp / $totalSum[$measure['ALIASE']]['PP']) * 100) : 0), 1, '.', '');
                    $temp[$measureName . "_LY_SHARE"] = number_format( ($totalSum[$measure['ALIASE']]['LY'] != 0 ? (($tempObj->ly / $totalSum[$measure['ALIASE']]['LY']) * 100) : 0), 1, '.', '');
                }
            }

            $accountValues = new utils\KBI_MeasureBlock();
            $accountValues = $accountSales['VAL'];

            $accountVolumes = new utils\KBI_MeasureBlock();
            $accountVolumes = $accountSales['VOL'];

            $tpPrice = $accountVolumes->ty != 0 ? $accountValues->ty / $accountVolumes->ty : 0;
            $lyPrice = $accountVolumes->ly != 0 ? $accountValues->ly / $accountVolumes->ly : 0;
            $ppPrice = $accountVolumes->pp != 0 ? $accountValues->pp / $accountVolumes->pp : 0;

            $arrayKey = trim($data['SKUID']).trim($data['SKU']);
            $tpDist = $TP_DIST[$arrayKey];
            $ppDist = $PP_DIST[$arrayKey];
            $lyDist = $LY_DIST[$arrayKey];

            $checkDistData = $distArray;
            foreach($distArray as $distkey => $distData)
            {
                if($$distData != 0)
                    unset($checkDistData[$distkey]);
            }
            
            if(count($checkDistData) == count($distArray))
                continue;
                
            if($isRankAct)
                $temp['RANK'] = $rankCounter++;    
            
            //DISTRIBUTION
            $temp["DIST_TP"] = number_format($tpDist, 0, '.', '');
            $temp["DIST_PP"] = number_format($ppDist, 0, '.', '');
            $temp["DIST_LY"] = number_format($lyDist, 0, '.', '');

            //PRICE SECTION
            $temp["PRICE_TP"] = number_format($tpPrice, 2, '.', '');
            $temp["PRICE_PP"] = number_format($ppPrice, 2, '.', '');
            $temp["PRICE_LY"] = number_format($lyPrice, 2, '.', '');

            //VALUE SPPD
            $calc = ($temp["DIST_TP"] > 0) ? $accountValues->ty/$temp["DIST_TP"] : 0;
            $calc = ($calc > 0) ? $calc/$this->totalWeeks : 0;
            $temp["VALUE_SPPD_TP"] = number_format($calc, 1, '.', '');

            $calc = ($temp["DIST_PP"] > 0) ? $accountValues->pp/$temp["DIST_PP"] : 0;
            $calc = ($calc > 0) ? $calc/$this->totalWeeks : 0;
            $temp["VALUE_SPPD_PP"] = number_format($calc, 1, '.', '');

            $calc = ($temp["DIST_LY"] > 0) ? $accountValues->ly/$temp["DIST_LY"] : 0;
            $calc = ($calc > 0) ? $calc/$this->totalWeeks : 0;            
            $temp["VALUE_SPPD_LY"] = number_format($calc, 1, '.', '');
            
            //VOLUME SPPD
            $calc = ($temp["DIST_TP"] > 0) ? $accountVolumes->ty/$temp["DIST_TP"] : 0;
            $calc = ($calc > 0) ? $calc/$this->totalWeeks : 0;
            $temp["VOLUME_SPPD_TP"] = number_format($calc, 1, '.', '');

            $calc = ($temp["DIST_PP"] > 0) ? $accountVolumes->pp/$temp["DIST_PP"] : 0;
            $calc = ($calc > 0) ? $calc/$this->totalWeeks : 0;
            $temp["VOLUME_SPPD_PP"] = number_format($calc, 1, '.', '');

            $calc = ($temp["DIST_LY"] > 0) ? $accountVolumes->ly/$temp["DIST_LY"] : 0;
            $calc = ($calc > 0) ? $calc/$this->totalWeeks : 0;            
            $temp["VOLUME_SPPD_LY"] = number_format($calc, 1, '.', '');
                        
            $this->gridData[] = $temp;
        }

        $totalRow = array();
        if (is_array($this->gridData) && !empty($this->gridData)) {
            $totalRow['TPNB'] = "Total";
            foreach ($this->settingVars->measureArray_for_productKBI_only as $key => $measure) {
                if ($measure['attr'] == 'SUM') {
                    $measureName = $measure['ALIASE'];
                    $totalRow[$measureName . "_TP"] = number_format($totalSum[$measureName]['TY'], 0, '.', '');
                    $totalRow[$measureName . "_LY"] = number_format($totalSum[$measureName]['LY'], 0, '.', '');
                    $totalRow[$measureName . "_PP"] = number_format($totalSum[$measureName]['PP'], 0, '.', '');
                    $totalRow[$measureName . "_PP_VAR"] = number_format(($totalSum[$measureName]['TY'] - $totalSum[$measureName]['PP']), 0, '.', '');
                    $totalRow[$measureName . "_PP_VAR_PCT"] = ($totalSum[$measureName]['PP'] != 0) ? number_format((($totalSum[$measureName]['TY']-$totalSum[$measureName]['PP'])/$totalSum[$measureName]['PP'])*100, 1, '.', '') : 0;
                    
                    $totalRow[$measureName . "_LY_VAR"] = number_format(($totalSum[$measureName]['TY'] - $totalSum[$measureName]['LY']), 0, '.', '');;
                    $totalRow[$measureName . "_LY_VAR_PCT"] = ($totalSum[$measureName]['LY'] != 0) ? number_format((($totalSum[$measureName]['TY']-$totalSum[$measureName]['LY'])/$totalSum[$measureName]['LY'])*100, 1, '.', '') : 0;

                    $totalRow[$measureName . "_TP_SHARE"] = "100";
                    $totalRow[$measureName . "_PP_SHARE"] = "100";
                    $totalRow[$measureName . "_LY_SHARE"] = "100";
                }
            }

            $total_tpPrice = $totalSum['VOL']['TY'] != 0 ? $totalSum['VAL']['TY'] / $totalSum['VOL']['TY'] : 0;
            $total_ppPrice = $totalSum['VOL']['PP'] != 0 ? $totalSum['VAL']['PP'] / $totalSum['VOL']['PP'] : 0;
            $total_lyPrice = $totalSum['VOL']['LY'] != 0 ? $totalSum['VAL']['LY'] / $totalSum['VOL']['LY'] : 0;

            //PRICE SECTION
            $totalRow["PRICE_TP"] = number_format($total_tpPrice, 2, '.', '');
            $totalRow["PRICE_PP"] = number_format($total_ppPrice, 2, '.', '');
            $totalRow["PRICE_LY"] = number_format($total_lyPrice, 2, '.', '');

            //DISTRIBUTION SECTION
            $totalTpDist = $this->totalDist['TP_DIST'];
            $totalPpDist = $this->totalDist['PP_DIST'];
            $totalLpDist = $this->totalDist['LY_DIST'];

            $totalRow["DIST_TP"] = number_format($totalTpDist, 0, '.', '');
            $totalRow["DIST_PP"] = number_format($totalPpDist, 0, '.', '');
            $totalRow["DIST_LY"] = number_format($totalLpDist, 0, '.', '');

            //VALUE SPPD
            $calc = ($totalTpDist > 0) ? $totalSum['VAL']['TY']/$totalTpDist : 0;
            $calc = ($calc > 0) ? $calc/$this->totalWeeks : 0;
            $totalRow["VALUE_SPPD_TP"] = number_format($calc, 1, '.', '');
            
            $calc = ($totalPpDist > 0) ? $totalSum['VAL']['PP']/$totalPpDist : 0;
            $calc = ($calc > 0) ? $calc/$this->totalWeeks : 0;
            $totalRow["VALUE_SPPD_PP"] = number_format($calc, 1, '.', '');

            $calc = ($totalLpDist > 0) ? $totalSum['VAL']['LY']/$totalLpDist : 0;
            $calc = ($calc > 0) ? $calc/$this->totalWeeks : 0;        
            $totalRow["VALUE_SPPD_LY"] = number_format($calc, 1, '.', '');
            
            //VOLUME SPPD
            $calc = ($totalTpDist > 0) ? $totalSum['VOL']['TY']/$totalTpDist : 0;
            $calc = ($calc > 0) ? $calc/$this->totalWeeks : 0;
            $totalRow["VOLUME_SPPD_TP"] = number_format($calc, 1, '.', '');
            
            $calc = ($totalPpDist > 0) ? $totalSum['VOL']['PP']/$totalPpDist : 0;
            $calc = ($calc > 0) ? $calc/$this->totalWeeks : 0;
            $totalRow["VOLUME_SPPD_PP"] = number_format($calc, 1, '.', '');

            $calc = ($totalLpDist > 0) ? $totalSum['VOL']['LY']/$totalLpDist : 0;
            $calc = ($calc > 0) ? $calc/$this->totalWeeks : 0;        
            $totalRow["VOLUME_SPPD_LY"] = number_format($calc, 1, '.', '');
        }

        if (!$this->isexport) {
            $this->jsonOutput['totalData'] = $totalRow;
            $this->jsonOutput['gridData'] = $this->gridData;
        }
        else
            $this->exportXls();
        /*         * *************************************************************************************************************** */
    }

    /*     * ** OVERRIDE PARENT CLASS'S getAll FUNCTION *** */

    public function getAll() {
        //$tablejoins_and_filters          = $this->settingVars->link;
        // $tablejoins_and_filters = parent::getAll();
        $tablejoins_and_filters = "";

        //$skuID    = $this->settingVars->dataArray[$_GET['SKU_FIELD']]['ID'];
        if ($_GET['TPNB'] != "") {
            $extraFields[] = $this->accountID;
            $tablejoins_and_filters .= " AND $this->accountID = '" . urldecode($_GET['TPNB']) . "' ";
            $this->prepareTablesUsedForQuery($extraFields);
        }

        if ($_GET['SKU'] != "") {
            $extraFields[] = $this->accountName;
            $tablejoins_and_filters .= " AND $this->accountName = '" . urldecode($_GET['SKU']) . "' ";
            $this->prepareTablesUsedForQuery($extraFields);
        }

        $tablejoins_and_filters1 = parent::getAll();
        $tablejoins_and_filters1 .= $tablejoins_and_filters;

        return $tablejoins_and_filters1;
    }

    private function initiateDocumentProperties() {
        //CREATE NEW PHPExcel OBJECT
        include "../ppt/Classes/PHPExcel.php";
        $this->objPHPExcel = new \PHPExcel();

        //SET DOCUMENT PROPERTIES
        $this->objPHPExcel->getProperties()->setCreator("Ultralysis")
                ->setTitle("STORES Sales vs " . $this->timeMode)
                ->setSubject("Product KBI STORES Sales Report")
                ->setLastModifiedBy('Ultralysis'); //DEVELOPER WHO RECENTLY WORKED ON THIS FILE
        //ADD A NEW WORKSHEET AND SET IT AS THE FIRST WORKSHEET OF THE DOCUMENT
        $newWorkSheet = new \PHPExcel_Worksheet($this->objPHPExcel, "VS " . $this->timeMode);
        $this->objPHPExcel->addSheet($newWorkSheet, 0);
        $this->objPHPExcel->setActiveSheetIndex(0);
    }

    private function setCellDimensions() {
        $this->gain_start_letter = "A";
        $gain_columns_count = count($this->accountHeaders) + count($this->settingVars->measureArray_for_productKBI_only) + 1; //+1 for price
        $this->gain_end_letter = $this->calculateEndingLetter($this->gain_start_letter, $gain_columns_count - 1);

        $temp = $this->gain_end_letter;
        $temp++;
        $temp++;
        $this->maintain_start_letter = $temp;
        $maintain_columns_count = count($this->accountHeaders) + (count($this->settingVars->measureArray_for_productKBI_only) + 1) * 2; //+1 for price
        $this->maintain_end_letter = $this->calculateEndingLetter($this->maintain_start_letter, $maintain_columns_count - 1);

        $temp = $this->maintain_end_letter;
        $temp++;
        $temp++;
        $this->lost_start_letter = $temp;
        $lost_columns_count = count($this->accountHeaders) + count($this->settingVars->measureArray_for_productKBI_only) + 1; //+1 for price
        $this->lost_end_letter = $this->calculateEndingLetter($this->lost_start_letter, $lost_columns_count - 1);
    }

    private function setOutOfGridTitleTexts() {
        $this->objPHPExcel->getActiveSheet()
                ->setCellValue("A1", urldecode($_GET['TPNB']) . ":" . urldecode($_GET['SKU']))
                ->setCellValue($this->gain_start_letter . "2", "STORES GAINED")
                ->setCellValue($this->maintain_start_letter . "2", "STORES MAINTAINED")
                ->setCellValue($this->lost_start_letter . "2", "STORES LOST");
        $this->objPHPExcel->getActiveSheet()->getStyle("A1:AC5")->getFont()->setBold(true);
    }

    private static function calculateEndingLetter($startingLetter, $stepsAhead) {
        $i = 0;
        while ($i < $stepsAhead) {
            $startingLetter++;
            $i++;
        }

        return $startingLetter;
    }

    private function getSqlPart() {
        $returnArray = array();
        $accountHeaders = array();
        $selectPart = array();
        $groupByPart = array();

        //PREPARE ITEMS SELECTED ON FRONT END TO WORK IN QUERY
        //$accountArr           = explode("-" , $_REQUEST['ACCOUNTS']);
        if(is_array($this->accountsName) && !empty($this->accountsName) ){
            foreach ($this->accountsName as $i => $account) {
                $id = key_exists("ID", $this->settingVars->dataArray[$account]) ? $this->settingVars->dataArray[$account]['ID'] : "";
                $name = $this->settingVars->dataArray[$account]['NAME'];

                if ($id != "") {
                    $selectPart[] = $id . " AS '" . $this->settingVars->dataArray[$account]['ID_ALIASE'] . "'";
                    $groupByPart[] = "'" . $this->settingVars->dataArray[$account]['ID_ALIASE'] . "'";
                    $this->accountHeaders[] = $this->settingVars->dataArray[$account]['ID_ALIASE'];
                    $this->measureFields[] = $id;
                }

                if($name != ""){
                    $selectPart[] = $name . " AS '" . $this->settingVars->dataArray[$account]['NAME_ALIASE'] . "'";
                    $groupByPart[] = "'" . $this->settingVars->dataArray[$account]['NAME_ALIASE'] . "'";
                    $this->accountHeaders[] = $this->settingVars->dataArray[$account]['NAME_ALIASE'];
                    $this->measureFields[] = $name;
                }
            }
        }

        //PREPARE MEASURE ITEMS
        foreach ($this->settingVars->measureArray_for_productKBI_only as $key => $measure) {
            //$num = str_replace('M', '', $key);
            //$measureFields = $this->prepareTablesUsedForMeasure($num+1);
            $measureFields = (isset($measure['usedFields']) && !empty($measure['usedFields'])) ? $measure['usedFields'] : array();
            if (is_array($measureFields) && !empty($measureFields)) {
                foreach ($measureFields as $key => $value) {
                    $this->measureFields[] = $value;
                }
            }
            if ($measure['attr'] == 'SUM') {
                $measureSelectionArr_TY[] = "SUM( (CASE WHEN " . filters\timeFilter::$tyWeekRange . " THEN 1 ELSE 0 END) * " . $measure['VAL'] . ") AS TY" . $measure['ALIASE'];
                $measureSelectionArr_PP[] = "SUM( (CASE WHEN " . filters\timeFilter::${strtolower($this->timeMode) . 'WeekRange'} . " THEN 1 ELSE 0 END) * " . $measure['VAL'] . ") AS " . $this->timeMode . $measure['ALIASE'];
            }
        }

        $returnArray = array(
            'SELECT_PART' => $selectPart
            , 'GROUPBY_PART' => $groupByPart
            , 'MEASURE_TY' => $measureSelectionArr_TY
            , 'MEASURE_PP' => $measureSelectionArr_PP
        );

        return $returnArray;
    }

    private function getData() {
        $selectPart = $this->queryPartsArr['SELECT_PART'];
        $groupByPart = $this->queryPartsArr['GROUPBY_PART'];

        $measurePart_TY = $this->queryPartsArr['MEASURE_TY'];
        $measurePart_PP = $this->queryPartsArr['MEASURE_PP'];

        $this->distribution_ty_totalData = array();
        $this->distribution_pp_totalData = array();

        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->queryPart = $this->getAll();

        //FIND OUT TY SELLING CUSTOMERS/BRANCHES/STORES
        $query = "SELECT " . implode(",", $selectPart) . "," . implode(",", $measurePart_TY) . " " .
                "FROM " . $this->settingVars->tablename . $this->queryPart . " " .
                "GROUP BY " . implode(",", $groupByPart) . " " .
                "HAVING TYVOL<>0 " .
                "ORDER BY 1";
        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
        foreach ($result as $key => $data) {
            $this->distribution_ty_totalData[$data[$this->accountHeaders[0]]] = $data;
        }


        //FIND OUT LY/PP SELLING CUSTOMERS/BRANCHES/STORES
        $query = "SELECT " . implode(",", $selectPart) . "," . implode(",", $measurePart_PP) . " " .
                "FROM " . $this->settingVars->tablename . $this->queryPart . " " .
                "GROUP BY " . implode(",", $groupByPart) . " " .
                "HAVING " . $this->timeMode . "VOL<>0 " .
                "ORDER BY 1";
        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
        foreach ($result as $key => $data) {
            $this->distribution_pp_totalData[$data[$this->accountHeaders[0]]] = $data;
        }

        $this->gainArr = array_diff_key($this->distribution_ty_totalData, $this->distribution_pp_totalData);
        $this->maintainArr = array_intersect_key($this->distribution_ty_totalData, $this->distribution_pp_totalData);
        $this->lostArr = array_diff_key($this->distribution_pp_totalData, $this->distribution_ty_totalData);
        /* USEFUL WHEN DEBUGGING , DON'T DELETE !! */
        //echo "<pre>";
        //print ("lostArr Array: ");
        //print_r($this->lostArr);exit;   
    }

    private function createStoresGained() {
        //-- COLOR HEADER ROWS WITH BLUE
        for ($i = $this->gain_start_letter; $i <= $this->gain_end_letter; $i++) {
            $this->objPHPExcel->getActiveSheet()->getStyle($i . "5")->applyFromArray(
                    array('fill' => array(
                            'type' => \PHPExcel_Style_Fill::FILL_SOLID,
                            'color' => array('argb' => 'FFDEEBF6')
                        ),
                        'borders' => array(
                            'top' => array('style' => \PHPExcel_Style_Border::BORDER_THIN),
                            'bottom' => array('style' => \PHPExcel_Style_Border::BORDER_THIN),
                            'left' => array('style' => \PHPExcel_Style_Border::BORDER_THIN),
                            'right' => array('style' => \PHPExcel_Style_Border::BORDER_THIN)
                        )
                    )
            );
        }

        //-- ADD BORDERS TO GENERAL COLUMNS
        $rowIndex = 5;
        while ($rowIndex++ < count($this->gainArr) + 5) {
            for ($i = $this->gain_start_letter; $i <= $this->gain_end_letter; $i++) {

                $this->objPHPExcel->getActiveSheet()->getStyle($i . $rowIndex)->applyFromArray
                        (
                        array(
                            'borders' => array
                                (
                                'top' => array('style' => \PHPExcel_Style_Border::BORDER_THIN),
                                'bottom' => array('style' => \PHPExcel_Style_Border::BORDER_THIN),
                                'left' => array('style' => \PHPExcel_Style_Border::BORDER_THIN),
                                'right' => array('style' => \PHPExcel_Style_Border::BORDER_THIN)
                            )
                        )
                );
            }
        }




        //-- ADD GRID HEADER TEXTS
        $i = $this->gain_start_letter;
        foreach ($this->accountHeaders as $key => $value) {
            $this->objPHPExcel->getActiveSheet()->setCellValue($i++ . "5", $value);
        }
        foreach ($this->settingVars->measureArray_for_productKBI_only as $key => $measure) {
            if ($measure['attr'] == "SUM")
                $this->objPHPExcel->getActiveSheet()->setCellValue($i++ . "5", $measure['ALIASE'] . ' TP');
        }
        $this->objPHPExcel->getActiveSheet()->setCellValue($i++ . "5", "PRICE TP");



        //-- SET COLUMN AUTO SIZE PROPERTIES
        for ($i = $this->gain_start_letter; $i <= $this->gain_end_letter; $i++) {
            $this->objPHPExcel->getActiveSheet()
                    ->getColumnDimension($i)
                    ->setAutoSize(true);
        }

        //--ADD GRID VALUES
        $rowIndex = 6;
        $activeSheet = $this->objPHPExcel->getActiveSheet();
        foreach ($this->gainArr as $key => $data) {
            $price = $data['TYVOL'] > 0 ? $data['TYVAL'] / $data['TYVOL'] : 0;

            $i = $this->gain_start_letter;
            foreach ($this->accountHeaders as $key => $value) {
                $activeSheet->setCellValue($i++ . $rowIndex, $data[$value]);
            }
            foreach ($this->settingVars->measureArray_for_productKBI_only as $key => $measure) {
                if ($measure['attr'] == "SUM")
                    $activeSheet->setCellValue($i++ . $rowIndex, $data['TY' . $measure['ALIASE']]);
            }
            $activeSheet->setCellValue($i++ . $rowIndex, number_format($price, 2, '.', ''));

            $rowIndex++;
        }
    }

    private function createStoresMaintained() {
        //-- COLOR HEADER ROW WITH BLUE
        for ($i = $this->maintain_start_letter; $i <= $this->maintain_end_letter; $i++) {
            $this->objPHPExcel->getActiveSheet()->getStyle($i . "5")->applyFromArray(
                    array('fill' => array(
                            'type' => \PHPExcel_Style_Fill::FILL_SOLID,
                            'color' => array('argb' => 'FFDEEBF6')
                        ),
                        'borders' => array(
                            'top' => array('style' => \PHPExcel_Style_Border::BORDER_THIN),
                            'bottom' => array('style' => \PHPExcel_Style_Border::BORDER_THIN),
                            'left' => array('style' => \PHPExcel_Style_Border::BORDER_THIN),
                            'right' => array('style' => \PHPExcel_Style_Border::BORDER_THIN)
                        )
                    )
            );
        }


        //-- ADD BORDERS TO GENERAL COLUMNS
        $rowIndex = 5;
        while ($rowIndex++ < count($this->maintainArr) + 5) {
            for ($i = $this->maintain_start_letter; $i <= $this->maintain_end_letter; $i++) {
                $this->objPHPExcel->getActiveSheet()->getStyle($i . $rowIndex)->applyFromArray
                        (
                        array(
                            'borders' => array
                                (
                                'top' => array('style' => \PHPExcel_Style_Border::BORDER_THIN),
                                'bottom' => array('style' => \PHPExcel_Style_Border::BORDER_THIN),
                                'left' => array('style' => \PHPExcel_Style_Border::BORDER_THIN),
                                'right' => array('style' => \PHPExcel_Style_Border::BORDER_THIN)
                            )
                        )
                );
            }
        }

        //-- ADD GRID HEADER TEXTS
        $i = $this->maintain_start_letter;
        foreach ($this->accountHeaders as $key => $value) {
            $this->objPHPExcel->getActiveSheet()->setCellValue($i++ . "5", $value);
        }
        foreach ($this->settingVars->measureArray_for_productKBI_only as $key => $measure) {
            if ($measure['attr'] == "SUM")
                $this->objPHPExcel->getActiveSheet()->setCellValue($i++ . "5", $measure['ALIASE'] . ' TP')
                        ->setCellValue($i++ . "5", $measure['ALIASE'] . ' var ' . $this->timeMode);
        }

        $this->objPHPExcel->getActiveSheet()->setCellValue($i++ . "5", "PRICE TP")
                ->setCellValue($i++ . "5", "PRICE " . $this->timeMode);


        //-- SET COLUMN AUTO SIZE PROPERTIES
        for ($i = $this->maintain_start_letter; $i <= $this->maintain_end_letter; $i++) {
            $this->objPHPExcel->getActiveSheet()
                    ->getColumnDimension($i)
                    ->setAutoSize(true);
        }


        //--ADD GRID VALUES
        $rowIndex = 6;
        foreach ($this->maintainArr as $key => $data) {
            $price_tp = $data['TYVOL'] > 0 ? $data['TYVAL'] / $data['TYVOL'] : 0;
            $price_ly = $this->distribution_pp_totalData[$key][$this->timeMode . 'VOL'] > 0 ? $this->distribution_pp_totalData[$key][$this->timeMode . 'VAL'] / $this->distribution_pp_totalData[$key][$this->timeMode . 'VOL'] : 0;

            $var_ly_arr = array();
            foreach ($this->settingVars->measureArray_for_productKBI_only as $mKey => $measure) {
                if ($measure['attr'] == "SUM") {
                    $temp_ty = $data['TY' . $measure['ALIASE']];
                    $temp_ly = $this->distribution_pp_totalData[$key][$this->timeMode . $measure['ALIASE']];
                    $var_ly_arr[$measure['ALIASE']] = $temp_ty - $temp_ly;
                }
            }

            $i = $this->maintain_start_letter;
            foreach ($this->accountHeaders as $key => $value) {
                $this->objPHPExcel->getActiveSheet()->setCellValue($i++ . $rowIndex, $data[$value]);
            }
            $activeSheet = $this->objPHPExcel->getActiveSheet();
            foreach ($this->settingVars->measureArray_for_productKBI_only as $key => $measure) {
                if ($measure['attr'] == "SUM") {
                    $activeSheet->setCellValue($i++ . $rowIndex, $data['TY' . $measure['ALIASE']]);
                    $activeSheet->setCellValue($i++ . $rowIndex, number_format($var_ly_arr[$measure['ALIASE']], 0, '.', ''));
                }
            }

            $activeSheet->setCellValue($i++ . $rowIndex, number_format($price_tp, 2, '.', ''));
            $activeSheet->setCellValue($i++ . $rowIndex, number_format($price_ly, 2, '.', ''));

            $rowIndex++;
        }
    }

    private function createStoresLost() {
        //-- COLOR HEADER ROW WITH BLUE
        $i = $this->lost_start_letter;
        $endRange = $this->lost_end_letter;
        $endRange++;
        while ($i != $endRange) {
            $this->objPHPExcel->getActiveSheet()->getStyle($i . "5")->applyFromArray(
                    array('fill' => array(
                            'type' => \PHPExcel_Style_Fill::FILL_SOLID,
                            'color' => array('argb' => 'FFDEEBF6')
                        ),
                        'borders' => array(
                            'top' => array('style' => \PHPExcel_Style_Border::BORDER_THIN),
                            'bottom' => array('style' => \PHPExcel_Style_Border::BORDER_THIN),
                            'left' => array('style' => \PHPExcel_Style_Border::BORDER_THIN),
                            'right' => array('style' => \PHPExcel_Style_Border::BORDER_THIN)
                        )
                    )
            );
            $i++;
        }


        //-- ADD BORDERS TO GENERAL COLUMNS
        $rowIndex = 5;
        while ($rowIndex++ < count($this->lostArr) + 5) {
            $i = $this->lost_start_letter;
            while ($i != $endRange) {
                $this->objPHPExcel->getActiveSheet()->getStyle($i . $rowIndex)->applyFromArray
                        (
                        array(
                            'borders' => array
                                (
                                'top' => array('style' => \PHPExcel_Style_Border::BORDER_THIN),
                                'bottom' => array('style' => \PHPExcel_Style_Border::BORDER_THIN),
                                'left' => array('style' => \PHPExcel_Style_Border::BORDER_THIN),
                                'right' => array('style' => \PHPExcel_Style_Border::BORDER_THIN)
                            )
                        )
                );
                $i++;
            }
        }



        //-- ADD GRID HEADER TEXTS
        $i = $this->lost_start_letter;
        foreach ($this->accountHeaders as $key => $value) {
            $this->objPHPExcel->getActiveSheet()->setCellValue($i++ . "5", $value);
        }
        foreach ($this->settingVars->measureArray_for_productKBI_only as $key => $measure) {
            if ($measure['attr'] == "SUM")
                $this->objPHPExcel->getActiveSheet()->setCellValue($i++ . "5", $measure['ALIASE'] . ' ' . $this->timeMode);
        }
        $this->objPHPExcel->getActiveSheet()->setCellValue($i++ . "5", "PRICE " . $this->timeMode);



        //-- SET COLUMN AUTO SIZE PROPERTIES
        for ($i = $this->lost_start_letter; $i != $endRange; $i++) {
            $this->objPHPExcel->getActiveSheet()
                    ->getColumnDimension($i)
                    ->setAutoSize(true);
        }



        //--ADD GRID VALUES
        $rowIndex = 6;
        foreach ($this->lostArr as $key => $data) {
            $price = $data[$this->timeMode . 'VOL'] > 0 ? $data[$this->timeMode . 'VAL'] / $data[$this->timeMode . 'VOL'] : 0;

            $i = $this->lost_start_letter;
            foreach ($this->accountHeaders as $key => $value) {
                $this->objPHPExcel->getActiveSheet()->setCellValue($i++ . $rowIndex, $data[$value]);
            }
            $activeSheet = $this->objPHPExcel->getActiveSheet();
            foreach ($this->settingVars->measureArray_for_productKBI_only as $key => $measure) {
                if ($measure['attr'] == "SUM") {
                    $activeSheet->setCellValue($i++ . $rowIndex, $data[$this->timeMode . $measure['ALIASE']]);
                }
            }
            $activeSheet->setCellValue($i++ . $rowIndex, number_format($price, 2, '.', ''));

            $rowIndex++;
        }
    }

    public function exportXls() {

        include "../ppt/Classes/PHPExcel.php";
        $objReader = \PHPExcel_IOFactory::createReader('Excel2007');
        $this->objPHPExcel = $objReader->load("exportphps/templates/product_kbi_export_template.xlsx");
        $this->addData($this->gridData);
        $this->fileName = "productKBI.xlsx";
        $this->saveAndDownload();
    }

    public function exportPPLYXls() {
        $this->settingVars->tableUsedForQuery = array();
        $this->measureFields = array();

        $this->queryPartsArr = $this->getSqlPart();
        $this->initiateDocumentProperties();

        
        $this->setCellDimensions();
        $this->setOutOfGridTitleTexts();

        $this->getData();
        $this->createStoresGained();
        
        $this->createStoresMaintained();
        $this->createStoresLost();
        $this->fileName = "VS_" . $this->timeMode . ".xlsx";
        $this->saveAndDownload();
    }

    public function addData($data) {
        $baseRow = 4;

        $columnsArray = array(
            'A' => 'TPNB',
            'B' => 'SKU',
            'C' => 'VAL_TP',
            'D' => 'VAL_PP_VAR',
            'E' => 'VAL_PP_VAR_PCT',
            'F' => 'VAL_LY_VAR',
            'G' => 'VAL_LY_VAR_PCT',
            'H' => 'VAL_TP_SHARE',
            'I' => 'VAL_PP_SHARE',
            'J' => 'VAL_LY_SHARE',            
            'K' => 'VOL_TP',
            'L' => 'VOL_PP_VAR',
            'M' => 'VOL_PP_VAR_PCT',
            'N' => 'VOL_LY_VAR',
            'O' => 'VOL_LY_VAR_PCT',
            'P' => 'VOL_TP_SHARE',
            'Q' => 'VOL_PP_SHARE',
            'R' => 'VOL_LY_SHARE',
            'S' => 'PRICE_TP',
            'T' => 'PRICE_PP',
            'U' => 'PRICE_LY',
            'V' => 'DIST_TP',
            'W' => 'DIST_PP',
            'X' => 'DIST_LY',
            'Y' => 'VALUE_SPPD_TP',
            'Z' => 'VALUE_SPPD_PP',
            'AA' => 'VALUE_SPPD_LY',
            'AB' => 'VOLUME_SPPD_TP',
            'AC' => 'VOLUME_SPPD_PP',
            'AD' => 'VOLUME_SPPD_LY',
        );

        /*If number format and thousand format is required then add value on this array*/
        $columnsFormatArray = array(
            'VOL_TP'          => ['format'=>'#,##0'],
            'VOL_PP_VAR'      => ['format'=>'#,##0'],
            'VOL_PP_VAR_PCT'  => ['format'=>'#,##0.0'],
            'VOL_LY_VAR'      => ['format'=>'#,##0'],
            'VOL_LY_VAR_PCT'  => ['format'=>'#,##0.0'],
            'VOL_TP_SHARE'    => ['format'=>'#,##0.0'],
            'VOL_PP_SHARE'    => ['format'=>'#,##0.0'],
            'VOL_LY_SHARE'    => ['format'=>'#,##0.0'],
            'VAL_TP'          => ['format'=>'#,##0'],
            'VAL_PP_VAR'      => ['format'=>'#,##0'],
            'VAL_PP_VAR_PCT'  => ['format'=>'#,##0.0'],
            'VAL_LY_VAR'      => ['format'=>'#,##0'],
            'VAL_LY_VAR_PCT'  => ['format'=>'#,##0.0'],
            'VAL_TP_SHARE'    => ['format'=>'#,##0.0'],
            'VAL_PP_SHARE'    => ['format'=>'#,##0.0'],
            'VAL_LY_SHARE'    => ['format'=>'#,##0.0'],
            'PRICE_TP'        => ['format'=>'#,##0.00'],
            'PRICE_PP'        => ['format'=>'#,##0.00'],
            'PRICE_LY'        => ['format'=>'#,##0.00'],
            'DIST_TP'         => ['format'=>'#,##0'],
            'DIST_PP'         => ['format'=>'#,##0'],
            'DIST_LY'         => ['format'=>'#,##0'],
            'VALUE_SPPD_TP'   => ['format'=>'#,##0.0'],
            'VALUE_SPPD_PP'   => ['format'=>'#,##0.0'],
            'VALUE_SPPD_LY'   => ['format'=>'#,##0.0'],
            'VOLUME_SPPD_TP'  => ['format'=>'#,##0.0'],
            'VOLUME_SPPD_PP'  => ['format'=>'#,##0.0'],
            'VOLUME_SPPD_LY'  => ['format'=>'#,##0.0'],
        );

        $removeColumns = array();
        if (!in_array('value_sales', $this->columnGroups)) {
            $removeColumns = array_merge($removeColumns, array('C', 'D', 'E', 'F', 'G'));
        }

        if (!in_array('value_share', $this->columnGroups)) {
            $removeColumns = array_merge($removeColumns, array('H', 'I', 'J'));
        }

        if (!in_array('volume_sales', $this->columnGroups)) {
            $removeColumns = array_merge($removeColumns, array('K', 'L', 'M', 'N', 'O'));
        }

        if (!in_array('volume_share', $this->columnGroups)) {
            $removeColumns = array_merge($removeColumns, array('P', 'Q', 'R'));
        }

        if (!in_array('price', $this->columnGroups)) {
            $removeColumns = array_merge($removeColumns, array('S', 'T', 'U'));
        }

        if (!in_array('distribution', $this->columnGroups)) {
            $removeColumns = array_merge($removeColumns, array('V', 'W', 'X'));
        }

        if (!in_array('value_sppd', $this->columnGroups)) {
            $removeColumns = array_merge($removeColumns, array('Y', 'Z', 'AA'));
        }        

        if (!in_array('volume_sppd', $this->columnGroups)) {
            $removeColumns = array_merge($removeColumns, array('AB', 'AC', 'AD'));
        }        
        
        $removeColumns = array_reverse($removeColumns);

        $toremove_num = count($removeColumns);
        for ($i = 0; $i < $toremove_num; $i++) {
            $this->objPHPExcel->getActiveSheet()->removeColumn($removeColumns[$i], 1);
            unset($columnsArray[$removeColumns[$i]]);
        }

        $this->objPHPExcel->getActiveSheet()->setCellValue('A1', $this->gridColumns['TPNB']);
        $this->objPHPExcel->getActiveSheet()->setCellValue('B1', $this->gridColumns['SKU']);

        $cellAlignmentStyle = array(
            'horizontal' => \PHPExcel_Style_Alignment::HORIZONTAL_LEFT,
            'vertical'   => \PHPExcel_Style_Alignment::VERTICAL_CENTER
        );

        $cellAlignmentStyleRight = array(
            'horizontal' => \PHPExcel_Style_Alignment::HORIZONTAL_RIGHT,
            'vertical'   => \PHPExcel_Style_Alignment::VERTICAL_CENTER
        );

        foreach ($data as $r => $dataRow) {
            $row = $baseRow + (int) $r;
            $col = 0;

            foreach ($columnsArray as $value) {
                $cell = $this->objPHPExcel->getActiveSheet()->getCellByColumnAndRow($col,$row)->getColumn().$row;
                if(isset($columnsFormatArray[$value])) {
                    //$dataRow[$value] = number_format((float) $dataRow[$value], $columnsFormatArray[$value]['dec'], '.', $columnsFormatArray[$value]['thsep']);
                    if(isset($columnsFormatArray[$value]['format']) && !empty($columnsFormatArray[$value]['format']))
                        $this->objPHPExcel->getActiveSheet()->getStyle($cell)->getNumberFormat()->setFormatCode($columnsFormatArray[$value]['format']);

                    $this->objPHPExcel->getActiveSheet()->getStyle($cell)->getAlignment()->applyFromArray($cellAlignmentStyleRight);
                }else{
                    $this->objPHPExcel->getActiveSheet()->getStyle($cell)->getAlignment()->applyFromArray($cellAlignmentStyle);
                }
                $this->objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow($col, $row, $dataRow[$value]);
                $col++;
            }
        }

        $this->objPHPExcel->getActiveSheet()->getColumnDimension('A')->setAutoSize(true);
        $this->objPHPExcel->getActiveSheet()->getColumnDimension('B')->setAutoSize(true);
    }

    public function saveAndDownload() {
        $this->objPHPExcel->setActiveSheetIndex(0);
        $filepath = $this->saveXlsxFileToServer();
        $this->jsonOutput['fileName'] = $filepath;
    }

    public function saveXlsxFileToServer() {
        global $objPHPExcel;
        $objWriter = \PHPExcel_IOFactory::createWriter($this->objPHPExcel, 'Excel2007');
        $objWriter->save(getcwd() . DIRECTORY_SEPARATOR . "../zip/" . $this->fileName);
        $filePath = "/zip/" . $this->fileName;
        return $filePath;
    }

    private function makeFieldsToAccounts($srcArray) {
        $tempArr = array();
        foreach ($srcArray as $value) {
            $value = explode("#", $value);
            if (count($value) > 1)
                $tempArr[] = strtoupper($this->dbColumnsArray[$value[0]] . "_" . $this->dbColumnsArray[$value[1]]);
            else
                $tempArr[] = strtoupper($this->dbColumnsArray[$value[0]]);
        }
        return $tempArr;
    }

    public function buildPageArray() {

        $accountFieldPart = explode("#", $this->gridField);

        $this->gridColumns = array(
            'TPNB' => (count($accountFieldPart) > 1) ? $this->displayCsvNameArray[$accountFieldPart[1]] : $this->displayCsvNameArray[$accountFieldPart[0]],
            'SKU' => $this->displayCsvNameArray[$accountFieldPart[0]]
        );

        $accountField = strtoupper($this->dbColumnsArray[$accountFieldPart[0]]);
        $accountField = (count($accountFieldPart) > 1) ? strtoupper($accountField . "_" . $this->dbColumnsArray[$accountFieldPart[1]]) : $accountField;

        $countFieldPart = explode("#", $this->countField);
        $countField = strtoupper($this->dbColumnsArray[$countFieldPart[0]]);
        $countField = (count($countFieldPart) > 1) ? strtoupper($countField . "_" . $this->dbColumnsArray[$countFieldPart[1]]) : $countField;

        $this->accountID = (isset($this->settingVars->dataArray[$accountField]) &&
                isset($this->settingVars->dataArray[$accountField]['ID'])) ? $this->settingVars->dataArray[$accountField]['ID'] :
                $this->settingVars->dataArray[$accountField]['NAME'];

        $this->accountName = $this->settingVars->dataArray[$accountField]['NAME'];
        $this->countField = (isset($this->settingVars->dataArray[$countField]) &&
                isset($this->settingVars->dataArray[$countField]['ID'])) ? $this->settingVars->dataArray[$countField]['ID'] :
                $this->settingVars->dataArray[$countField]['NAME'];

        $tempAccounts = array($this->gridField);
        if(is_array($this->accountFields) && !empty($this->accountFields)){
            foreach ($this->accountFields as $key => $value) {
                if(!empty($value) && !in_array($value, $tempAccounts))
                    $tempAccounts[] = $value;
            }
        }

        $this->accountsName = $this->makeFieldsToAccounts($tempAccounts);

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