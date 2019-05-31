<?php

namespace classes\lcl;

use db;
use filters;
use config;

class ProvinceSalesByStore extends config\UlConfig {

    public function go($settingVars) {
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES
        $this->queryPart = $this->getAll(); //USES OWN getAll FUNCTION

        $this->redisCache = new \utils\RedisCache($this->queryVars);

        $this->settingVars->pageName = (empty($this->settingVars->pageName)) ? $this->settingVars->pageID . '_SalesByStorePage' : $this->settingVars->pageName;
        $this->ValueVolume = getValueVolume($this->settingVars);

        if ($this->settingVars->isDynamicPage) {
            $this->provinceField = $this->getPageConfiguration('province_field', $this->settingVars->pageID)[0];
            $this->storeField = $this->getPageConfiguration('store_field', $this->settingVars->pageID)[0];
            $this->buildDataArray(array($this->provinceField, $this->storeField));
            $this->buildPageArray();
        } else {
            if (!isset($this->settingVars->pageArray[$this->settingVars->pageName]) ||
                    empty($this->settingVars->pageArray[$this->settingVars->pageName]))
                $this->configurationFailureMessage();

            $this->provinceAccount = (isset($this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['PROVINCE']]) &&
                    isset($this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['PROVINCE']]['ID'])) ?
                    $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['PROVINCE']]['ID'] :
                    $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['PROVINCE']]['NAME'];

            $this->storeAccount = (isset($this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['STORE_FIELD']]) &&
                    isset($this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['STORE_FIELD']]['ID'])) ?
                    $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['STORE_FIELD']]['ID'] :
                    $this->settingVars->dataArray[$this->settingVars->pageArray[$this->settingVars->pageName]['STORE_FIELD']]['NAME'];
        }

        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
            $this->jsonOutput['pageConfig'] = array(
                'enabledFilters' => $this->getPageConfiguration('filter_settings', $this->settingVars->pageID)
            );
        }else{
            $this->gridValue(); //ADDING TO OUTPUT [DDA => DETAILED DRIVER ANALYSIS]
        }
        return $this->jsonOutput;
    }
    

    private function gridValue() {

        /*[START] Getting common cached MEASURES query from the Redis*/
        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $measureSelectRes = $this->prepareMeasureSelectPart();
        $this->measureFields = $measureSelectRes['measureFields'];

        $this->measureFields[] = $this->provinceAccount;
        $this->prepareTablesUsedForQuery($this->measureFields);
        $this->settingVars->useRequiredTablesOnly = true;
        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll

        $measureSelectionArr = $measureSelectRes['measureSelectionArr'];
        $havingTYValue = $measureSelectRes['havingTYValue'];
        $havingLYValue = $measureSelectRes['havingLYValue'];

        $query = "SELECT ".$this->provinceAccount ." AS ACCOUNT, " .implode(",", $measureSelectionArr).
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

        $requiredGridFields = array("ACCOUNT", $havingTYValue, $havingLYValue);
        $resultMain = $this->redisCache->getRequiredData($result, $requiredGridFields, $havingTYValue, $havingTYValue, $havingLYValue);
        /*[END] Getting common cached MEASURES query from the Redis*/

        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $this->measureFields = $this->prepareTablesUsedForMeasure($_REQUEST['ValueVolume']);
        $this->measureFields[] = $this->provinceAccount;
        $this->measureFields[] = $this->storeAccount;
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->queryPart = $this->getAll();
        
        if (filters\timeFilter::$ToYear == filters\timeFilter::$FromYear)
            $week = (filters\timeFilter::$ToWeek - filters\timeFilter::$FromWeek) + 1;
        else
            $week = ( filters\timeFilter::$ToWeek) + (52 - filters\timeFilter::$FromWeek) + 1;

        $query = "SELECT " . $this->provinceAccount . " AS ACCOUNT" .
                            ",COUNT( DISTINCT(CASE WHEN " . filters\timeFilter::$tyWeekRange . " AND " . $this->settingVars->ProjectVolume . ">0 THEN 1 END )* " . $this->storeAccount . " ) AS DIST" .", ".
                            "SUM((CASE WHEN  " . filters\timeFilter::$tyWeekRange . " THEN 1 ELSE 0 END) * " . $this->settingVars->ProjectVolume . ") AS SALES_UNIT " .
                            "FROM " . $this->settingVars->tablename . $this->queryPart . " " .
                            "AND (" . filters\timeFilter::$tyWeekRange . " OR " . filters\timeFilter::$lyWeekRange . ") " .
                            "GROUP BY " . $this->provinceAccount;
                            /*"HAVING VALUETY>0 " .
                            "ORDER BY VALUETY DESC";*/
        
        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $resultSub = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($resultSub);
        } else {
            $resultSub = $redisOutput;
        }

        /*1 MAke the new array which contain KEY as commonKey for 2nd array*/
        $resultSubTmp = $result = [];
        if(isset($resultSub) && is_array($resultSub) && count($resultSub) > 0){
            foreach ($resultSub as $k1 => $value) {
                $resultSubTmp[$value['ACCOUNT']] = $value;
            }
        }
        /*2 loop through the main array and mearge it with tmp 2nd array*/
        if(isset($resultMain) && is_array($resultMain) && count($resultMain) > 0){
            foreach ($resultMain as $k2 => $val) {
                $result[] = [
                    'ACCOUNT'=>$val['ACCOUNT'],
                    'VALUETY'=>$val[$havingTYValue],
                    'DIST'=>isset($resultSubTmp[$val['ACCOUNT']]) && isset($resultSubTmp[$val['ACCOUNT']]['DIST']) ? $resultSubTmp[$val['ACCOUNT']]['DIST'] : 0,
                    'SALES_UNIT'=>isset($resultSubTmp[$val['ACCOUNT']]) && isset($resultSubTmp[$val['ACCOUNT']]['SALES_UNIT']) ? $resultSubTmp[$val['ACCOUNT']]['SALES_UNIT'] : 0,
                ];
                if(isset($resultSubTmp[$val['ACCOUNT']])){
                    unset($resultSubTmp[$val['ACCOUNT']]);
                }
            }
        }
        /*3 Now at least add the remaining 2nd array values into the main array*/
        if(isset($resultSubTmp) && is_array($resultSubTmp) && count($resultSubTmp)>0){
            foreach ($resultSubTmp as $k2 => $resVal) {
                array_push($result, [
                                        'ACCOUNT'=>$resVal['ACCOUNT'],
                                        'VALUETY'=>0,
                                        'DIST'=>$resVal['DIST'],
                                        'SALES_UNIT'=>$resVal['SALES_UNIT'],
                                    ]);
            }
        }
        unset($resultSubTmp); unset($resultMain);
        $result = \utils\SortUtility::sort2DArray($result, 'VALUETY', \utils\SortTypes::$SORT_DESCENDING);

        foreach ($result as $key => $data) {
            $cros = ($data['DIST'] > 0) ? (($data['VALUETY'] / $data['DIST']) / $week) : 0;
            $uros = ($data['DIST'] > 0) ? (($data['SALES_UNIT'] / $data['DIST']) / $week) : 0;
            $temp = array();
            $temp['PRO'] = htmlspecialchars_decode($data['ACCOUNT']);
            $temp['DIS'] = $data['DIST'];
            $temp['SALES'] = number_format($data['VALUETY'], 0, '.', '');
            $temp['SALES_UNIT'] = number_format($data['SALES_UNIT'], 0, '.', '');
            $temp['CROS'] = number_format($cros, 2, '.', '');
            $temp['UROS'] = number_format($uros, 1, '.', '');
            $this->jsonOutput['gridValue'][] = $temp;
        }
    }

    public function buildPageArray() {

        //        $fetchConfig = false;
        //        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
        //            $fetchConfig = true;
        //            $this->jsonOutput['pageConfig'] = array(
        //                'enabledFilters' => $this->getPageConfiguration('filter_settings', $this->settingVars->pageID)
        //            );
        //        }

        $provinceField = strtoupper($this->dbColumnsArray[$this->provinceField]);
        $storeField = strtoupper($this->dbColumnsArray[$this->storeField]);

        $this->provinceAccount = (isset($this->settingVars->dataArray[$provinceField]) &&
                isset($this->settingVars->dataArray[$provinceField]['ID'])) ? $this->settingVars->dataArray[$provinceField]['ID'] :
                $this->settingVars->dataArray[$provinceField]['NAME'];

        $this->provinceAccountName = (isset($this->settingVars->dataArray[$provinceField]) && isset($this->settingVars->dataArray[$provinceField]['NAME_CSV'])) ? $this->settingVars->dataArray[$provinceField]['NAME_CSV'] : 'PROVINCE';
        $this->jsonOutput['provinceFieldName'] = $this->provinceAccountName;
        
        $this->storeAccount = (isset($this->settingVars->dataArray[$storeField]) &&
                isset($this->settingVars->dataArray[$storeField]['ID'])) ? $this->settingVars->dataArray[$storeField]['ID'] :
                $this->settingVars->dataArray[$storeField]['NAME'];

        return;
    }

    public function buildDataArray($fields) {
        if (empty($fields))
            return false;

        $configurationCheck = new config\ConfigurationCheck($this->settingVars, $this->queryVars);
        $configurationCheck->buildDataArray($fields);
        $this->dbColumnsArray = $configurationCheck->dbColumnsArray;
        return;
    }
}

?> 