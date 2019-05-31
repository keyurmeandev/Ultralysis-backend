<?php

namespace classes;

use datahelper;
use projectsettings;
use filters;
use utils;
use db;
use config;

class CategorySubcategoryGridPage extends config\UlConfig {

    public $catList;

    /**
     * Default gateway function, should be similar in all DATA CLASS
     * 
     * @param array $settingVars project settingsGateway variables
     *
     * @return Array
     */

    public function go($settingVars) {
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES
        $this->queryPart = $this->getAll();

        $this->catList = array();

        $this->settingVars->pageName = (empty($this->settingVars->pageName) && !empty($this->settingVars->pageID)) ? $this->settingVars->pageID.'_RankMonitorPage' : $this->settingVars->pageName;

        if ($this->settingVars->isDynamicPage) {
            $this->categoryField = $this->getPageConfiguration('category_field', $this->settingVars->pageID)[0];
            $this->subCategoryField = $this->getPageConfiguration('sub_category_field', $this->settingVars->pageID)[0];
            $this->categorySubCategoryCountField = $this->getPageConfiguration('categorySubCategoryCountField', $this->settingVars->pageID)[0];

            $fieldArray = array($this->categoryField,$this->subCategoryField);

            $this->buildDataArray($fieldArray);
            $this->buildPageArray();
        } else {
            $this->configurationFailureMessage();
        }

        if (!isset($_REQUEST["fetchConfig"]) || empty($_REQUEST["fetchConfig"]) || $_REQUEST["fetchConfig"] != 'true') {
            if(!isset($_REQUEST['ValueVolume']) || empty($_REQUEST['ValueVolume'])) {
                $this->configurationFailureMessage('Measures configuration missing.');
            }
        }

        $this->timeArray  = array( 
            '1' => array('jsonKey' => 'LW', 'label' => 'Latest Week'),  
            '4' => array('jsonKey' => 'LW4', 'label' => 'Latest 4W'), 
            '12' => array('jsonKey' => 'LW12', 'label' => 'Latest 12W'),
            '26' => array('jsonKey' => 'LW26', 'label' => 'Latest 26W'),
            '52' => array('jsonKey' => 'LW52', 'label' => 'Latest 52W'),
            'YTD' => array('jsonKey' => 'YTD', 'label' => 'YTD')
        );

        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
            $this->getGridColumnsConfig();
        }

        $action = $_REQUEST['action'];
        switch ($action) {
            case "getGridData":
                $this->getCategoryData();
                break;
        }
        return $this->jsonOutput;
    }

    public function getGridColumnsConfig(){
        $timeSelectionSettings = array();

        if(is_array($this->timeArray) && !empty($this->timeArray)){
            foreach ($this->timeArray as $timekey => $timeval) {
                $timeSelectionSettings[$timekey] = $timeval;
            }
        }

        $this->jsonOutput['timeSelectionSettings'] = $timeSelectionSettings;
    }

    /**
     * getCategoryData()
     * It will list all category 
     * 
     * @return void
     */
    private function getCategoryData() {
        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $this->measureFields[] = $this->categoryName;
        $this->measureFields[] = $this->subCategoryName;
        
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->queryPart = $this->getAll();  

        $measureArr = $measureSelectionArr = array();
        $timeWhereCluase = '';
        if(is_array($this->timeArray) && !empty($this->timeArray)){
            foreach ($this->timeArray as $timekey => $timeData) {
                if(is_array($this->settingVars->pageArray["MEASURE_SELECTION_LIST"]) && !empty($this->settingVars->pageArray["MEASURE_SELECTION_LIST"])){
                    filters\timeFilter::getTimeFrame($timekey, $this->settingVars);
                    foreach ($this->settingVars->pageArray["MEASURE_SELECTION_LIST"] as $mkey => $measureVal) {
                        $options = array();
                        $measureKey = 'M' . $measureVal['measureID'];
                        $measure = $this->settingVars->measureArray[$measureKey];
                        $timeval = $timeData['jsonKey'];

                        if (!empty(filters\timeFilter::$tyWeekRange)){
                            if($timeval == 'YTD' && $mkey == 0)
                                $orderBy = $timeval.'_TY_'.$measure['ALIASE'];
                            $options['tyLyRange'][$timeval.'_TY_'.$measure['ALIASE']] = filters\timeFilter::$tyWeekRange;
                        }

                        if (!empty(filters\timeFilter::$lyWeekRange))
                            $options['tyLyRange'][$timeval.'_LY_'.$measure['ALIASE']] = filters\timeFilter::$lyWeekRange;
                        
                        $measureArr[$measureKey] = config\MeasureConfigure::prepareSelect($this->settingVars, $this->queryVars, array($measureKey), $options);           
                        $measureSelectionArr = array_merge($measureSelectionArr,$measureArr[$measureKey]);
                    }
                    if ($timekey == '52')
                        $timeWhereCluase = " AND (" . filters\timeFilter::$tyWeekRange . " OR " . filters\timeFilter::$lyWeekRange . ") ";
                }
            }
        }

        $measureSelect = implode(", ", $measureSelectionArr);
        /* Removed the DISTINCT and added the GROUP BY */
        $query = "SELECT " .$this->subCategoryName . " AS ACCOUNT" .
                " , ".$this->categoryName." AS CATCOLUMN ".
                " , MAX(pl) AS PL ".
                " , ".$measureSelect." ".
                " FROM " . $this->settingVars->tablename . $this->queryPart. $timeWhereCluase.
                " GROUP BY ".$this->categoryName.",ACCOUNT ".
                ((!empty($orderBy)) ? " ORDER BY PL DESC,".$orderBy." DESC, CATCOLUMN ASC" : " ");
        $subcatResult = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);

        $this->totalCatData = array();
        if(is_array($subcatResult) && !empty($subcatResult)) {
            foreach ($subcatResult as $key => $catdata) {
                $this->prepareTempArray($catdata);
            }

            // $this->catList[] = $this->totalCatData;
            $allOtherData = [];
            $subCatCntr = 0;
			$measureKey = 'M' . $_REQUEST['ValueVolume'];
			$measure = $this->settingVars->measureArray[$measureKey];

            foreach ($this->catData as $category => $catData) {
    			if(($subCatCntr < $this->categorySubCategoryCountField) || ($this->categorySubCategoryCountField == 0)){
					$this->catList[] = $catData;
	                $subCatList = $this->subCatList[$category];
	                foreach ($subCatList as $subCatData) {
	                    $this->catList[] = $subCatData;
	                }
                    $subCatCntr++;
    			}else{
    				foreach ($this->timeArray as $timekey => $timeData){
						$timeval = $timeData['jsonKey'];
						$allOtherData[$timeval.'_TY_'.$measure['ALIASE']] += str_replace(',', '', $catData[$timeval.'_TY_'.$measure['ALIASE']]);
						$allOtherData[$timeval.'_LY_'.$measure['ALIASE']] += str_replace(',', '', $catData[$timeval.'_LY_'.$measure['ALIASE']]);
					}
    			}
            }
        }

        if(count($allOtherData) > 0){
			$allOtherData['ACCOUNT'] = 'ALL OTHERS';
			$allOtherData['isTotalRow'] = 0;
			$allOtherData['isCategory'] = 1;
			$allOtherData['colorCode'] = '#ceaf96';
			
        	foreach ($this->timeArray as $timekey => $timeData){
        		$timeval = $timeData['jsonKey'];
				$allOtherData[$timeval.'_VAR_'.$measure['ALIASE']] = number_format(($allOtherData[$timeval.'_TY_'.$measure['ALIASE']]-$allOtherData[$timeval.'_LY_'.$measure['ALIASE']]),0);
				$allOtherData[$timeval.'_VARP_'.$measure['ALIASE']] = $allOtherData[$timeval.'_LY_'.$measure['ALIASE']] > 0 ? number_format((($allOtherData[$timeval.'_TY_'.$measure['ALIASE']]-$allOtherData[$timeval.'_LY_'.$measure['ALIASE']])/$allOtherData[$timeval.'_LY_'.$measure['ALIASE']])*100,1,'.',','):0;

				$allOtherData[$timeval.'_TY_'.$measure['ALIASE']] = number_format($allOtherData[$timeval.'_TY_'.$measure['ALIASE']],0);
				$allOtherData[$timeval.'_LY_'.$measure['ALIASE']] = number_format($allOtherData[$timeval.'_LY_'.$measure['ALIASE']],0);
        	}
            $this->catList[] = $allOtherData;
        }

        $this->jsonOutput['gridList'] = $this->catList;
    }

    private function prepareTempArray($result, $isCategory = false)
    {
        $temp = array();
        $temp['ACCOUNT'] = $result['ACCOUNT'];
        $temp['isTotalRow'] = 0;
        if (!isset($this->subCatList[$result['CATCOLUMN']]))
            $this->subCatList[$result['CATCOLUMN']] = array();

        $measureKey = 'M' . $_REQUEST['ValueVolume'];
        $measure = $this->settingVars->measureArray[$measureKey];

        foreach ($this->timeArray as $timekey => $timeData) 
        {
            $timeval = $timeData['jsonKey'];
            
            $temp[$timeval.'_TY_'.$measure['ALIASE']] = number_format($result[$timeval.'_TY_'.$measure['ALIASE']],0);
            $temp[$timeval.'_LY_'.$measure['ALIASE']] = number_format($result[$timeval.'_LY_'.$measure['ALIASE']],0);
            $temp[$timeval.'_VAR_'.$measure['ALIASE']] = number_format(($result[$timeval.'_TY_'.$measure['ALIASE']]-$result[$timeval.'_LY_'.$measure['ALIASE']]),0);
            $temp[$timeval.'_VARP_'.$measure['ALIASE']] = $result[$timeval.'_LY_'.$measure['ALIASE']] > 0 ? number_format((($result[$timeval.'_TY_'.$measure['ALIASE']]-$result[$timeval.'_LY_'.$measure['ALIASE']])/$result[$timeval.'_LY_'.$measure['ALIASE']])*100,1,'.',','):0;

            if ($result['PL'] > 0) {
                $this->catData[$result['CATCOLUMN']]['ACCOUNT'] = $result['ACCOUNT'];
                $this->catData[$result['CATCOLUMN']]['isTotalRow'] = (count($this->catData) == 1) ? 1 : 0;
                $this->catData[$result['CATCOLUMN']]['isCategory'] = (count($this->catData) != 1) ? 1 : 0;
                $this->catData[$result['CATCOLUMN']]['colorCode'] = '#ceaf96';

                $this->catData[$result['CATCOLUMN']][$timeval.'_TY_'.$measure['ALIASE']] = str_replace(',', '', $this->catData[$result['CATCOLUMN']][$timeval.'_TY_'.$measure['ALIASE']]);
                $this->catData[$result['CATCOLUMN']][$timeval.'_TY_'.$measure['ALIASE']] += $result[$timeval.'_TY_'.$measure['ALIASE']];

                $this->catData[$result['CATCOLUMN']][$timeval.'_LY_'.$measure['ALIASE']] = str_replace(',', '', $this->catData[$result['CATCOLUMN']][$timeval.'_LY_'.$measure['ALIASE']]);
                $this->catData[$result['CATCOLUMN']][$timeval.'_LY_'.$measure['ALIASE']] += $result[$timeval.'_LY_'.$measure['ALIASE']];
                
                $this->catData[$result['CATCOLUMN']][$timeval.'_VAR_'.$measure['ALIASE']] = ($this->catData[$result['CATCOLUMN']][$timeval.'_TY_'.$measure['ALIASE']]-$this->catData[$result['CATCOLUMN']][$timeval.'_LY_'.$measure['ALIASE']]);
                $this->catData[$result['CATCOLUMN']][$timeval.'_VARP_'.$measure['ALIASE']] = (($this->catData[$result['CATCOLUMN']][$timeval.'_LY_'.$measure['ALIASE']] > 0) ? 
                    ((($this->catData[$result['CATCOLUMN']][$timeval.'_TY_'.$measure['ALIASE']]-$this->catData[$result['CATCOLUMN']][$timeval.'_LY_'.$measure['ALIASE']])/$this->catData[$result['CATCOLUMN']][$timeval.'_LY_'.$measure['ALIASE']])*100) : 0 );

                $this->catData[$result['CATCOLUMN']][$timeval.'_TY_'.$measure['ALIASE']] = number_format($this->catData[$result['CATCOLUMN']][$timeval.'_TY_'.$measure['ALIASE']], 0);
                $this->catData[$result['CATCOLUMN']][$timeval.'_LY_'.$measure['ALIASE']] = number_format($this->catData[$result['CATCOLUMN']][$timeval.'_LY_'.$measure['ALIASE']], 0);
                $this->catData[$result['CATCOLUMN']][$timeval.'_VAR_'.$measure['ALIASE']] = number_format($this->catData[$result['CATCOLUMN']][$timeval.'_VAR_'.$measure['ALIASE']], 0);
                $this->catData[$result['CATCOLUMN']][$timeval.'_VARP_'.$measure['ALIASE']] = number_format($this->catData[$result['CATCOLUMN']][$timeval.'_VARP_'.$measure['ALIASE']],1,'.',',');
            }

            /*$this->totalCatData['ACCOUNT'] = "Total";
            $this->totalCatData['isTotalRow'] = 1;

            $this->totalCatData[$timeval.'_TY_'.$measure['ALIASE']] = str_replace(',', '', $this->totalCatData[$timeval.'_TY_'.$measure['ALIASE']]);
            $this->totalCatData[$timeval.'_TY_'.$measure['ALIASE']] += $result[$timeval.'_TY_'.$measure['ALIASE']];

            $this->totalCatData[$timeval.'_LY_'.$measure['ALIASE']] = str_replace(',', '', $this->totalCatData[$timeval.'_LY_'.$measure['ALIASE']]);
            $this->totalCatData[$timeval.'_LY_'.$measure['ALIASE']] += $result[$timeval.'_LY_'.$measure['ALIASE']];
            
            $this->totalCatData[$timeval.'_VAR_'.$measure['ALIASE']] = ($this->totalCatData[$timeval.'_TY_'.$measure['ALIASE']]-$this->totalCatData[$timeval.'_LY_'.$measure['ALIASE']]);
            $this->totalCatData[$timeval.'_VARP_'.$measure['ALIASE']] = (($this->totalCatData[$timeval.'_LY_'.$measure['ALIASE']] > 0) ? 
                ((($this->totalCatData[$timeval.'_TY_'.$measure['ALIASE']]-$this->totalCatData[$timeval.'_LY_'.$measure['ALIASE']])/$this->totalCatData[$timeval.'_LY_'.$measure['ALIASE']])*100) : 0 );

            $this->totalCatData[$timeval.'_TY_'.$measure['ALIASE']] = number_format($this->totalCatData[$timeval.'_TY_'.$measure['ALIASE']],0);
            $this->totalCatData[$timeval.'_LY_'.$measure['ALIASE']] = number_format($this->totalCatData[$timeval.'_LY_'.$measure['ALIASE']],0);
            $this->totalCatData[$timeval.'_VAR_'.$measure['ALIASE']] = number_format($this->totalCatData[$timeval.'_VAR_'.$measure['ALIASE']],0);
            $this->totalCatData[$timeval.'_VARP_'.$measure['ALIASE']] = number_format($this->totalCatData[$timeval.'_VARP_'.$measure['ALIASE']],1,'.',',');*/
        }
        if ($result['PL'] == 0) {
            $this->subCatList[$result['CATCOLUMN']][] = $temp;
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
        
        $tablejoins_and_filters = parent::getAll();

        return $tablejoins_and_filters;
    }

    public function buildPageArray() {

        $fetchConfig = false;
        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
            $fetchConfig = true;
            
            $mktfld = str_replace("#", "_", $this->getPageConfiguration('store_setting_field', $this->settingVars->pageID));
            if(count($mktfld) > 0) 
                $mktfld = strtoupper($mktfld[0]);

            $this->jsonOutput['pageConfig'] = array(
                'enabledFilters' => $this->getPageConfiguration('filter_settings', $this->settingVars->pageID), 
                'marketFilters' => $mktfld
            );
        }
        
        $categoryField = strtoupper($this->dbColumnsArray[$this->categoryField]);
        $subCategoryField = strtoupper($this->dbColumnsArray[$this->subCategoryField]);
        $this->categoryName = $this->settingVars->dataArray[$categoryField]['NAME'];
        $this->subCategoryName = $this->settingVars->dataArray[$subCategoryField]['NAME'];
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