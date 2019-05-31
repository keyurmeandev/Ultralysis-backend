<?php

namespace classes\relayplus;

use projectsettings;
use datahelper;
use filters;
use config;
use utils;
use db;

class SellThru extends config\UlConfig {

    public $dbColumnsArray;
    public $displayCsvNameArray;
    public $tyHavingField;
    public $redisCache;
    public $isExport;

    public function __construct() {
        $this->tyHavingField = "VALUE";

        $this->jsonOutput = array();
    }

    /*****
     * Default gateway function, should be similar in all DATA CLASS
     * arguments:
     *  $settingVars [project settingsGateway variables]
     * @return $jsonOutput with all data that should be sent to client app
    *****/
    public function go($settingVars) {
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES
        $this->redisCache = new utils\RedisCache($this->queryVars);
        $this->isExport = false;

        if (isset($_REQUEST['action']) && ($_REQUEST['action'] == 'fetchGrid' || $_REQUEST['action'] == 'export'))
        {
            if($_REQUEST['action'] == 'export')
                $this->isExport = true;
                
            $this->gridData();
        }

        return $this->jsonOutput;
    }

    public function gridData() 
    {
        $requestedCombination = explode("-", $_REQUEST['timeFrame']);
        $requestedYear        = $requestedCombination[1];
        
        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $measureSelectRes = $this->prepareMeasureSelectPart();
        
        $this->measureFields[] = $_REQUEST['ACCOUNT'] = $this->settingVars->skutable.'.pname_rollup';
        
        $accountField = $this->settingVars->maintable.".PNAME";
        if(isset($_REQUEST['ACCOUNT']) && !empty($_REQUEST['ACCOUNT'])){
            $this->measureFields[] = $_REQUEST['ACCOUNT'];
            $accountField = $_REQUEST['ACCOUNT'];
        }else if(isset($this->jsonOutput["defaultSelectedField"]) && !empty($this->jsonOutput["defaultSelectedField"])){
            $this->measureFields[] = $this->jsonOutput["defaultSelectedField"];
            $accountField = $this->jsonOutput["defaultSelectedField"];
        }
        
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll

        $timeFilters = '';
        $maintable = $this->settingVars->maintable;

        $objDatahelper = new datahelper\Time_Selection_DataCollectors($this->settingVars);
        $objDatahelper->getAllSeasonalHardStopDates($this->jsonOutput,$requestedYear, $showAsOutput);
        
        if (is_array($this->settingVars->tyDates) && !empty($this->settingVars->tyDates)) {
            foreach ($this->settingVars->tyDates as $tyDate) {
                $tyMydatePart = explode('-', $tyDate);
                $tyMydatePart[0] = $tyMydatePart[0]-1;
                $lyDate = implode('-', $tyMydatePart);

                $colsHeader['TY'][] = array("FORMATED_DATE" => date('d/m/Y', strtotime($tyDate)), "MYDATE" => $tyDate, "DAY" => date('D', strtotime($tyDate)));
                $colsHeader['LY'][] = array("FORMATED_DATE" => date('D', strtotime($tyDate)), "MYDATE" => $tyDate);
                $dateArray[$tyDate] = date('j-n', strtotime($tyDate));
            }
        }
        
        $measuresFldsAll = implode(',',$measureSelectRes['measureSelectionArr']);

        $query = "SELECT PIN, DATE, MAX(QTY) as QTY FROM ".$this->settingVars->volumeSellthruActualsTable." WHERE PIN IN (SELECT DISTINCT PIN FROM ".$this->settingVars->maintable." WHERE gid = ".$this->settingVars->GID." AND clientid = '".$this->settingVars->clientID."') GROUP BY PIN, DATE";
        
        $actualSalesQuery = $query;
        
        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }
        
        /* $this->redisCache->requestHash = $this->redisCache->prepareQueryHash('actualSales');
        $this->redisCache->setDataForStaticHash($result);
        $actualSalesHash = $this->redisCache->requestHash; */
        
        $actualSalesArray = array();
        if(is_array($result) && !empty($result))
        {
            foreach($result as $data)
                $actualSalesArray[$data['PIN'].$data['DATE']] = $data['QTY'];
        }
        
        $query = "SELECT ".$accountField." AS ACCOUNT, ".
            "MAX(".$maintable.".pin) as PIN, " . 
            "MAX(".$this->settingVars->volumeSellthruTable.".mydate) as MYDATE, ".
            "MAX(".$maintable.".category) as CATEGORY, " . 
            "DATE_FORMAT(".$this->settingVars->volumeSellthruTable.".mydate, '%e-%c') as FORMATED_DATE, ".
            "MAX(".$maintable.".seasonal_year) as YEAR, ".
            "MAX(agreed_buy) as AGREED_BUY, ".
            "MAX(STC_FORECAST) as STC_FORECAST " . 
            " FROM " . $this->settingVars->tablename .' '. trim($this->queryPart) . 
            " AND (" . filters\timeFilter::$tyWeekRange . ")". // . " OR " . filters\timeFilter::$lyWeekRange 
            " GROUP BY ACCOUNT, FORMATED_DATE ORDER BY MYDATE ASC";
        
        $redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
        if ($redisOutput === false) {
            $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }
        
        /* $this->redisCache->requestHash = $this->redisCache->prepareQueryHash('mainResult');
        $this->redisCache->setDataForStaticHash($result);
        $mainResultHash = $this->redisCache->requestHash; */
        
        $dataPnameSum = [];
        $dataPnameSumIndexed = [];
        $havingTYValue = "DAILY_FCAST";
        
        if (is_array($result) && !empty($result)) {
            foreach ($result as $key => $seasonalData) {
                $seasonalData[$havingTYValue] = $seasonalData['AGREED_BUY']*($seasonalData['STC_FORECAST']/100);
                $seasonalDataArray[$seasonalData['ACCOUNT']][$seasonalData['FORMATED_DATE']] = $seasonalData;
                $dataPnameSum[$seasonalData['ACCOUNT']] += $seasonalData[$havingTYValue];
            }
            arsort($dataPnameSum);
            $rankCnt = 2;
            foreach ($dataPnameSum as $pnm=>$pdt) {
                $dataPnameSum[$pnm] = $rankCnt;
                $rankCnt++;
            }
        }
        
        $cnt = 0; $cmpTyTotal = $cmpLyTotal = [];
        foreach (array_keys($seasonalDataArray) as $account) {
            $tmp = $tmp1 = $tmp2 = $cumTmp = $cumTmp1 = $tmp3 = array();
            
            $tmp['ACCOUNT'] = $account;
            $tmp['TOTAL'] = (isset($dataPnameSum[$account])) ? $dataPnameSum[$account] : 0;

            $tmp1['ACCOUNT'] = $account;
            $tmp1['TOTAL'] = (isset($dataPnameSum[$account])) ? $dataPnameSum[$account] : 0;            

            $tmp2['ACCOUNT'] = $account;
            $tmp2['TOTAL'] = (isset($dataPnameSum[$account])) ? $dataPnameSum[$account] : 0;

            $tmp3['ACCOUNT'] = $account;
            $tmp3['TOTAL'] = (isset($dataPnameSum[$account])) ? $dataPnameSum[$account] : 0;
            
            $cumTmp['ACCOUNT'] = $account;
            $cumTmp['TOTAL'] = (isset($dataPnameSum[$account])) ? $dataPnameSum[$account] : 0;

            $cumTmp1['ACCOUNT'] = $account;
            $cumTmp1['TOTAL'] = (isset($dataPnameSum[$account])) ? $dataPnameSum[$account] : 0;            
            
            $cumTyValue = $cumActualValue = 0;
            foreach ($dateArray as $dayMydate => $dayMonth) {
                $tyMydate = $dayMydate;
                
                if (isset($seasonalDataArray[$account][$dayMonth])) {
                    $data = $seasonalDataArray[$account][$dayMonth];
                    $dtKey = 'dt'.str_replace('-','',$tyMydate);
                    $dtTotalKey = 'dt'.str_replace('-','',$tyMydate);

                    $tmp[$dtKey] = $data[$havingTYValue]*1;
                    $tmp['PIN'] = $data['PIN'];
                    $tmp['CATEGORY'] = $data['CATEGORY'];
                    $tmp['AGREED_BUY'] = (double)$data['AGREED_BUY'];
                    $tmp['RANK'] = (int)'1'.$data['YEAR'];
                    $tmp['ROWDESC'] = 'Daily Fcast';
                    $tmp['highlightRow'] = 1;

                    $tmp1[$dtKey] = $actualSalesArray[$data['PIN'].$data['MYDATE']]*1;
                    $tmp1['PIN'] = $data['PIN'];
                    $tmp1['CATEGORY'] = $data['CATEGORY'];
                    $tmp1['AGREED_BUY'] = (double)$data['AGREED_BUY'];                    
                    $tmp1['RANK'] = (int)'3'.$data['YEAR'];
                    $tmp1['ROWDESC'] = 'Actual Sales';
                    $tmp1['highlightRow'] = 2;
                    
                    $cumTyValue += $data[$havingTYValue];
                    $cumTmp[$dtKey] = $cumTyValue;
                    $cumTmp['YEAR'] = $ty;
                    $cumTmp['PIN'] = $data['PIN'];
                    $cumTmp['CATEGORY'] = $data['CATEGORY'];
                    $cumTmp['AGREED_BUY'] = (double)$data['AGREED_BUY'];                    
                    $cumTmp['RANK'] = (int)'2'.$data['YEAR'];
                    $cumTmp['ROWDESC'] = 'Cumulative Fcast';
                    $cumTmp['highlightRow'] = 1;
                    
                    $cumActualValue += $actualSalesArray[$data['PIN'].$data['MYDATE']];
                    $cumTmp1[$dtKey] = $cumActualValue;
                    $cumTmp1['PIN'] = $data['PIN'];
                    $cumTmp1['CATEGORY'] = $data['CATEGORY'];
                    $cumTmp1['AGREED_BUY'] = (double)$data['AGREED_BUY'];                    
                    $cumTmp1['RANK'] = (int)'4'.$data['YEAR'];
                    $cumTmp1['ROWDESC'] = 'Cumulative Sales';
                    $cumTmp1['highlightRow'] = 1;
                    
                    $tmp2[$dtKey] = ($cumTyValue > 0) ? (($cumActualValue/$cumTyValue)*100) : 0;
                    $tmp2['PIN'] = $data['PIN'];
                    $tmp2['CATEGORY'] = $data['CATEGORY'];
                    $tmp2['AGREED_BUY'] = (double)$data['AGREED_BUY'];                    
                    $tmp2['RANK'] = (int)'5'.$data['YEAR'];
                    $tmp2['ROWDESC'] = 'Actual vs Fcast (Cum)';
                    $tmp2['highlightRow'] = 3;
                    
                    $tmp3[$dtKey] = ($data['AGREED_BUY'] - $cumActualValue);
                    $tmp3['PIN'] = $data['PIN'];
                    $tmp3['CATEGORY'] = $data['CATEGORY'];
                    $tmp3['AGREED_BUY'] = (double)$data['AGREED_BUY'];
                    $tmp3['RANK'] = (int)'6'.$data['YEAR'];
                    $tmp3['ROWDESC'] = 'Units To Go';
                    $tmp3['highlightRow'] = 1;
                }
            }
            
            $finalData[] = $tmp;
            $finalData[] = $cumTmp;
            $finalData[] = $tmp1;
            $finalData[] = $cumTmp1;
            $finalData[] = $tmp2;
            $finalData[] = $tmp3;
            
            $finalExeclData[$account][] = $tmp;
            $finalExeclData[$account][] = $cumTmp;
            $finalExeclData[$account][] = $tmp1;
            $finalExeclData[$account][] = $cumTmp1;
            $finalExeclData[$account][] = $tmp2;
            $finalExeclData[$account][] = $tmp3;
            $cnt++;
        }
        
        $this->jsonOutput['gridAllColumnsHeader'] = $colsHeader;
        $this->redisCache->requestHash = $this->redisCache->prepareQueryHash('SELLTHRU_GRID_COLUMNS_HEADER');
        $this->redisCache->setDataForStaticHash($colsHeader);
        $gridAllColumnsHeaderHash = $this->redisCache->requestHash;
        
        $this->jsonOutput['gridData'] = $finalData;
        $this->redisCache->requestHash = $this->redisCache->prepareQueryHash('SELLTHRU_GRID_DATA');
        $this->redisCache->setDataForStaticHash($finalExeclData);
        $gridDataHash = $this->redisCache->requestHash;
        
        if(isset($dataPnameSum) && count($dataPnameSum)>0){
            foreach ($dataPnameSum as $k1 => $v1) {
               $dataPnameSumIndexed['val'.$v1]=$k1;
            }
        }
        $this->jsonOutput['gridDataTotal'] = $dataPnameSumIndexed;
        
        if($this->isExport)
        {
            unset($this->jsonOutput['gridAllColumnsHeader']);
            unset($this->jsonOutput['gridData']);
            unset($this->jsonOutput['gridDataTotal']);
            unset($this->jsonOutput['allSeasonalHardStopDatesHashKey']);
            
            $fileName      = "Sell-Thru-" . date("Y-m-d-h-i-s") . ".xlsx";
            $savePath      = dirname(__FILE__)."/../../uploads/Sell-Thru/";
            $imgLogoPath   = dirname(__FILE__)."/../../../global/project/assets/img/ultralysis_logo.png";
            $filePath      = $savePath.$fileName;
            $projectID     = $this->settingVars->projectID;
            $RedisServer   = $this->queryVars->RedisHost.":".$this->queryVars->RedisPort;
            $RedisPassword = $this->queryVars->RedisPassword;

            $result = shell_exec('/usr/bin/perl '.dirname(__FILE__).'/../../batch/Sellthru.pl "'.$filePath.'" "'.$projectID.'" "'.$RedisServer.'" "'.$RedisPassword.'" "'.$gridAllColumnsHeaderHash.'" "'.$gridDataHash.'" "'.$imgLogoPath.'"');
            
            $this->jsonOutput['downloadLink'] = $this->settingVars->get_full_url()."/".basename(dirname(dirname(dirname(__FILE__))))."/uploads/Sell-Thru/".$fileName;
        }
    }

}
?> 