<?php

namespace classes;

use projectsettings;
use datahelper;
use filters;
use config;
use utils;
use db;
use lib;

class StoreBySkuPage extends config\UlConfig {

    public $gridNameArray;
    public $pageName;
    public $gridFields;
    public $countOfGrid;
    public $lineChart;
    public $dbColumnsArray;
    public $displayCsvNameArray;
	public $lyHavingField;
	public $tyHavingField;

    public $lineChartAllFunction;
    public $lineChartAllPpFunction;

    public function __construct() {
		$this->lyHavingField = "LYVALUE";
		$this->tyHavingField = "TYVALUE";

        $this->lineChartAllFunction = 'LineChartAllData';
        $this->lineChartAllPpFunction = 'LineChartAllData_for_PP';
        
        $this->gridNameArray = array();
        $this->jsonTagArray = array("gridStore", "gridGroup", "gridCategory", "gridBrand", "gridSKU");
        $this->jsonOutput = array();
    }

    /*     * ***
     * Default gateway function, should be similar in all DATA CLASS
     * arguments:
     *  $settingVars [project settingsGateway variables]
     * @return $jsonOutput with all data that should be sent to client app
     * *** */

    public function go($settingVars) {
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES

		if ($this->settingVars->isDynamicPage) {
			$this->gridFields = $this->getPageConfiguration('grid_fields', $this->settingVars->pageID);
            
            if(isset($_REQUEST['customAccount']) && $_REQUEST['customAccount'] != "")
                $this->gridFields[0] = $_REQUEST['customAccount'];

            if (is_array($this->settingVars->performanceTabMappings) && !empty($this->settingVars->performanceTabMappings)) {
                foreach ($this->settingVars->performanceTabMappings as $mapKey => $mapping) {
                    if($mapKey != 'drillDown') {
                        $this->settingVars->tabsXaxis[$mapping] = $this->getPageConfiguration($mapKey.'_xaxis', $this->settingVars->pageID)[0];
                        $this->settingVars->tabsXaxis[$mapping] = (!empty($this->settingVars->tabsXaxis[$mapping])) ? $this->settingVars->tabsXaxis[$mapping] : 'yearweek';
                    }
                }
            }

			$this->settingVars->pageName = (empty($this->settingVars->pageName)) ? implode("_", $this->gridFields)."_PERFORMANCE" : $settingVars->pageName;
			$this->countOfGrid = count($this->gridFields);

			$this->buildDataArray($this->gridFields);
			$this->buildPageArray($this->gridFields);
		} else {
            if (!isset($this->settingVars->pageArray[$this->settingVars->pageName]) || !isset($this->settingVars->pageArray[$this->settingVars->pageName]["GRID_FIELD"]) || empty($this->settingVars->pageArray[$this->settingVars->pageName]["GRID_FIELD"]))
                    $this->configurationFailureMessage();
			
            if (isset($this->settingVars->pageArray[$this->settingVars->pageName]['configuration']))
                $this->jsonOutput['configuration'] = $this->settingVars->pageArray[$this->settingVars->pageName]['configuration'];
            
            $this->countOfGrid = count($this->settingVars->pageArray[$this->settingVars->pageName]["GRID_FIELD"]);
		}

        //SET PERFORMANCE PAGE'S GRID AND CHART PROPERTIES
        $this->setPageDetails();

        if ( (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') && $this->settingVars->projectType == 2 || $this->settingVars->projectType == 15)
        {
            reset($this->jsonOutput["GRID_FIRST_COLUMN_NAMES"]);
            $first_key = key($this->jsonOutput["GRID_FIRST_COLUMN_NAMES"]);
            
            $fieldArr = array_column($this->jsonOutput['fieldSelection'], 'data');
            
            if(in_array($this->gridFields[0], $fieldArr))
            {
                $key = array_search($this->gridFields[0], $fieldArr);
                if($key >= 0)
                    $this->jsonOutput["GRID_FIRST_COLUMN_NAMES"][$first_key] = $this->jsonOutput['fieldSelection'][$key]['label'];
                else
                    $this->jsonOutput["GRID_FIRST_COLUMN_NAMES"][$first_key] = $this->displayCsvNameArray[$this->gridFields[0]];
            }
            else
                $this->jsonOutput["GRID_FIRST_COLUMN_NAMES"][$first_key] = $this->displayCsvNameArray[$this->gridFields[0]];
                
            //$this->jsonOutput["GRID_FIRST_COLUMN_NAMES"][$first_key] = $this->jsonOutput['fieldSelection'][0]['label'];
        }

        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
            $gridFieldArray = $this->settingVars->pageArray[$this->settingVars->pageName]["GRID_FIELD"];
            if (is_array($gridFieldArray) && !empty($gridFieldArray)) {
                foreach ($gridFieldArray as $gridFieldKey => $gridFieldArrayValue) {
                    $this->jsonOutput["GRID_FIELD"][$gridFieldKey] = array_values($gridFieldArrayValue)[0];
                }
            }
        }
        
        $redisCache = new utils\RedisCache($this->queryVars);

        if (!isset($_REQUEST["fetchConfig"]) || empty($_REQUEST["fetchConfig"]) || $_REQUEST["fetchConfig"] != 'true')
            $redisCache->requestHash = $redisCache->prepareCommonHashForPerformance();
        else
            $redisCache->requestHash = $redisCache->prepareCommonHash();

        //filters\timeFilter::calculateTotalWeek($settingVars); //Total Weeks

        // $this->queryPart = $this->getAll(); //USING OWN getAll function

		if (!isset($_REQUEST["fetchConfig"]) || empty($_REQUEST["fetchConfig"]) || $_REQUEST["fetchConfig"] != 'true') {
			datahelper\Common_Data_Fetching_Functions::$settingVars = $this->settingVars;
			datahelper\Common_Data_Fetching_Functions::$queryVars = $this->queryVars;
			$this->requestedGridName = $_REQUEST['gridFetchName'];

			//START PREPARING XML FORMATED DATA 
			$this->prepareGridData();
			$this->prepareChartData();
		}

        $redisJSON = $this->jsonOutput;
        if (isset($_REQUEST['requestFieldName']) && !empty($_REQUEST['requestFieldName'])) {
            $redisJSON[$_REQUEST['requestFieldName']] = $redisJSON[$this->requestedGridName];
            $redisJSON['measureArray'] = $this->settingVars->measureArray;
            unset($redisJSON[$this->requestedGridName]);
        }

        if($this->lineChart == true){
            $redisJSON['measureArray'] = $this->settingVars->measureArray;
        }

        $redisCache->setDataForHash($redisJSON);

        
        if(isset($this->jsonOutput[$_REQUEST['gridFetchName']]) && !empty($this->jsonOutput[$_REQUEST['gridFetchName']]) ){
            $options = array();
            // $options['ACCOUNT_FIELDS'] = $requiredGridFields = array("ACCOUNT", "ID");
            $requiredGridFields = array("ACCOUNT", "ID");
            $indexInDataArray = $this->gridNameArray[$_REQUEST['gridFetchName']];
            $requiredGridFields = $redisCache->getRequiredFieldsArray($requiredGridFields, false, $this->settingVars->measureArray);
            $requiredGridFields[] = 'PERFORMANCE_VAR';
            $requiredGridFields[] = 'PERFORMANCE_VAR_PER';

            $orderBy = $tyField = $lyField = "";

            if(isset($_REQUEST['ValueVolume']) && !empty($_REQUEST['ValueVolume'])) {
                $measureKey = 'M' . $_REQUEST['ValueVolume'];
                $measure = $this->settingVars->measureArray[$measureKey];
                $tyField = $orderBy = "TY" . $measure['ALIASE'];
                $lyField = "LY" . $measure['ALIASE'];
            }
            // $options['limit'] = 1000;
            // $options['includeAllOther'] = 'YES';
            // $options['allOtherLabel'] = 'ALL OTHER';
            $this->jsonOutput[$_REQUEST['gridFetchName']] = $redisCache->getRequiredData($this->jsonOutput[$_REQUEST['gridFetchName']],$requiredGridFields,$orderBy,$tyField,$lyField, $options);
        }

        if($this->lineChart == true){
            $requiredChartFields = array('ACCOUNT','TYACCOUNT','LYACCOUNT','TYMYDATE','LYMYDATE');
            $requiredChartFields = $redisCache->getRequiredFieldsArray($requiredChartFields,true, $this->settingVars->measureArray);
            $this->jsonOutput['LineChart'] = $redisCache->getRequiredData($this->jsonOutput['LineChart'],$requiredChartFields);
        }

        return $this->jsonOutput;
    }

    public function setPageDetails() {
        // $this->countOfGrid = $_REQUEST["gridCount"];
        if (isset($_REQUEST["LINECHART"]) && strtolower($_REQUEST["LINECHART"]) == "true")
            $this->lineChart = true;

        // CONFIGURING ACTIVE GRIDS AND GETTING UP FIRST COLUMN NAME OF BOTTOM GRID INDIVIDUALLY
        $temp = array();
        $tempID = array();
        if (!empty($this->settingVars->pageArray[$this->settingVars->pageName]["GRID_FIELD"])) {
            foreach ($this->settingVars->pageArray[$this->settingVars->pageName]["GRID_FIELD"] as $gridName => $columnAndFieldName) {
                foreach ($columnAndFieldName as $columnName => $fieldName) {
                    $this->gridNameArray[$gridName] = $fieldName;
                    $temp[$gridName] = $this->displayCsvNameArray[$columnName];
                    $idColumn = key_exists('ID_CSV', $this->settingVars->dataArray[$fieldName]) ? $this->settingVars->dataArray[$fieldName]['ID_CSV'] : "";
                    $tempID[$gridName] = $idColumn;
                }
            }
        }

        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
            $this->jsonOutput["GRID_FIRST_COLUMN_NAMES"] = $temp;
            $this->jsonOutput["GRID_FIRST_ID_NAMES"] = $tempID;
        }
    }

    public function prepareGridData() {
        $totalGrid = count($this->jsonTagArray);
		$this->countOfGrid = (isset($_REQUEST["gridCount"])) ? $_REQUEST["gridCount"] : $this->countOfGrid;
        $measuresFields = datahelper\Common_Data_Fetching_Functions::getMeasuresExtraTable();
        for ($i = ($totalGrid - $this->countOfGrid); $i < $totalGrid; $i++) {
            $selectPart = array();
            $this->settingVars->tableUsedForQuery = $groupByPart = array();
            $fieldsList = $measuresFields;

			if ($this->jsonTagArray[$i] == $this->requestedGridName) {
				$indexInDataArray = $this->gridNameArray[$this->jsonTagArray[$i]];
				
				$tempId = key_exists('ID', $this->settingVars->dataArray[$indexInDataArray]) ? $this->settingVars->dataArray[$indexInDataArray]['ID'] : "";
				
				if ($tempId != "") {
					$fieldsList[] = $tempId;

					$selectPart[] = $tempId . " AS ID";
					$groupByPart[] = 'ID';
				}
				
				$nameList = explode("-", $this->settingVars->dataArray[$indexInDataArray]['NAME']);
				
				foreach ($nameList as $key => $name) {
					$fieldsList[] = $name;
					if ($key == 0) {
						$selectPart[] = $name . " AS ACCOUNT";
						$groupByPart[] = "ACCOUNT";
					} else {
						$selectPart[] = $name . " AS " . strtoupper($name);
						$groupByPart[] = strtoupper($name);
					}
				}

				$this->prepareTablesUsedForQuery($fieldsList);
				$this->settingVars->useRequiredTablesOnly = true;
				$this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll

				$dataJsonTag = $this->jsonTagArray[$i];
				datahelper\Common_Data_Fetching_Functions::gridFunctionAllData($this->queryPart, $selectPart, $groupByPart, $dataJsonTag, $this->jsonOutput, $indexInDataArray, $this->tyHavingField, $this->lyHavingField);
			}
		}
    }

    public function prepareChartData() {
        $lineChartAllFunction = $this->lineChartAllFunction;
        $lineChartAllPpFunction = $this->lineChartAllPpFunction;

        if ($this->lineChart == true) {
			
            //IF CHART OF THE PERFORMANCE PAGE NEEDS TO SHOW DATA USING OTHER ACCOUNT, RATHER THAN YEAR-WEEK
            if (isset($_REQUEST["lineChartType"])) {
                $dataId = key_exists('ID', $this->settingVars->dataArray[$_REQUEST["lineChartType"]]) ? $this->settingVars->dataArray[$_REQUEST["lineChartType"]]["ID"] : "";
                $dataName = $this->settingVars->dataArray[$_REQUEST["lineChartType"]]["NAME"];
                $dataJsonTag = $_REQUEST["lineChartType"];

                $measuresFields = datahelper\Common_Data_Fetching_Functions::getMeasuresExtraTable();
                $measuresFields[] = $dataId;
                $measuresFields[] = $dataName;
                
                $this->prepareTablesUsedForQuery($measuresFields);
                $this->settingVars->useRequiredTablesOnly = true;
                $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll

                datahelper\Common_Data_Fetching_Functions::gridFunctionAllData($this->queryPart, $dataId, $dataName, $dataJsonTag, $this->jsonOutput);
            } else {
                $measuresFields = datahelper\Common_Data_Fetching_Functions::getMeasuresExtraTable();
                // $measuresFields = datahelper\Common_Data_Fetching_Functions::getAllMeasuresExtraTable();
                $this->prepareTablesUsedForQuery($measuresFields);
                $this->settingVars->useRequiredTablesOnly = true;
                $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll
            }

            //CONVENTIONAL YEAR-WEEK BASED DATA FOR USUAL LINE CHART
			if ($_REQUEST['TSM'] == 1)
				datahelper\Common_Data_Fetching_Functions::$lineChartAllFunction($this->queryPart, $this->jsonOutput);
			else
				datahelper\Common_Data_Fetching_Functions::$lineChartAllPpFunction($this->queryPart, $this->jsonOutput);
        }
    }

    /*     * ***
     * COLLECTS TABLE JOINING STRING FROM PROJECT SETTINGS
     * ALSO APPLYS FILTER CONDITIONS TO THE STRING
     * AND RETURNS $tablejoins_and_filters
     * *** */

    public function getAll() {
        $extraFields = array();
        
        foreach ($this->gridNameArray as $gridName => $indexInDataArray) {
            $keyName = strtoupper(str_replace('grid', '', $gridName));
            if ($_REQUEST[$keyName] != "") {
                if (!key_exists('ID', $this->settingVars->dataArray[$indexInDataArray])) {
                    $extraFields[] = $this->settingVars->dataArray[$indexInDataArray]["NAME"];
                    $tablejoins_and_filters .= " AND trim(" . $this->settingVars->dataArray[$indexInDataArray]["NAME"] . ")='" . $_REQUEST[$keyName] . "'";
                } else {
                    $extraFields[] = $this->settingVars->dataArray[$indexInDataArray]["ID"];
                    $tablejoins_and_filters .= " AND " . $this->settingVars->dataArray[$indexInDataArray]["ID"] . "='" . $_REQUEST[$keyName] . "'";
                }
            }
        }

        $this->prepareTablesUsedForQuery($extraFields);
        $tablejoins_and_filters1 = parent::getAll();
        $tablejoins_and_filters1 .= $tablejoins_and_filters;

        return $tablejoins_and_filters1;
    }

    public function buildDataArray($gridFields, $isCsvColumn = true) {
        if (empty($gridFields))
            return false;

        $configurationCheck = new config\ConfigurationCheck($this->settingVars, $this->queryVars);
        $configurationCheck->buildDataArray($gridFields, $isCsvColumn);
        $this->dbColumnsArray = $configurationCheck->dbColumnsArray;
        $this->displayCsvNameArray = $configurationCheck->displayCsvNameArray;
        return;
    }

    public function buildPageArray($gridFields) {
        if (empty($gridFields))
            return false;

        $startIndex = count($this->jsonTagArray) - $this->countOfGrid;
		$fetchConfig = false;

		if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
		    $fetchConfig = true;
            $tabsSettings = $this->getPageConfiguration('tabs_settings', $this->settingVars->pageID);
            $firstTabMeasure = (is_array($tabsSettings) && !empty($tabsSettings)) ? $this->settingVars->performanceTabMappings[$tabsSettings[0]] : "";

            if (is_array($this->settingVars->measureArray) && !empty($this->settingVars->measureArray)) {
                foreach ($this->settingVars->measureArray as $mKey => $measure) {
                    $measureAliase[$mKey] = array(
                        "ALIASE" => $measure['ALIASE'], 
                        "dataDecimalPlaces" => (isset($measure['dataDecimalPlaces']) && !empty($measure['dataDecimalPlaces'])) ? $measure['dataDecimalPlaces'] : 0, 
                        'NAME' => (isset($measure['measureName']) && !empty($measure['measureName'])) ? $measure['measureName'] : $measure['ALIASE']
                    );
                }
            }

			$this->jsonOutput['gridConfig'] = array(
				'gridCount' => count($gridFields),
				'leftFirstGridCol' => strtoupper(str_replace('grid', '', $this->jsonTagArray[$startIndex])),
                'leftFirstGridName' => $this->jsonTagArray[$startIndex],
                'enabledGrids' => array(),
                'enabledTabs' => $tabsSettings,
                'firstTabMeasure' => $firstTabMeasure,
                'tabMeasures' => $this->settingVars->performanceTabMappings,
				'measuresAliases' => $measureAliase,
				'enabledFilters' => $this->getPageConfiguration('filter_settings', $this->settingVars->pageID),
                'selectedField' => $gridFields[0]
			);
        }
        
		foreach ($gridFields as $gridField) {
            $gridFieldPart = explode("#", $gridField);
            $tempGridField = strtoupper($this->dbColumnsArray[$gridFieldPart[0]]);
            $tempGridField = (count($gridFieldPart) > 1) ? strtoupper($tempGridField."_".$this->dbColumnsArray[$gridFieldPart[1]]) : $tempGridField;

            $this->settingVars->pageArray[$this->settingVars->pageName]["GRID_FIELD"][$this->jsonTagArray[$startIndex]] = array($gridFieldPart[0] => $tempGridField);
            if ($fetchConfig)
				$this->jsonOutput['gridConfig']['enabledGrids'][] = $this->jsonTagArray[$startIndex];
            
			$startIndex++;
        }
        
        // FOR RELAY-PLUS AND TSD ONLY
        if( ($this->settingVars->projectType == 2 || $this->settingVars->projectType == 15) && isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true' && in_array("field-selection",$this->jsonOutput['gridConfig']['enabledFilters']))
        {
            $tables = array();
            if ($this->settingVars->isDynamicPage)
                $tables = $this->getPageConfiguration('table_field', $this->settingVars->pageID);
            else {
                if (isset($this->getPageAnalysisField) && $this->getPageAnalysisField == 'Product')
                    $tables = array($this->settingVars->skutable);
                else if (isset($this->getPageAnalysisField) && $this->getPageAnalysisField == 'Store')
                    $tables = array("market");
            }

            if (is_array($tables) && !empty($tables)) {
                $fields = $tmpArr = array();
                foreach ($tables as $table) {
                    if(isset($this->queryVars->projectConfiguration[$table."_settings"]) && !empty($this->queryVars->projectConfiguration[$table."_settings"])) {
                        $settings = explode("|", $this->queryVars->projectConfiguration[$table."_settings"]);    
                        foreach ($settings as $key => $field) {
                            $val = explode("#", $field);
                            $fields[] = $val[0];

                            if ($key == 0) {
                                $appendTable = ($table == 'market') ? 'store' : $table;
                                $this->settingVars->pageArray[$this->settingVars->pageName]["ACCOUNT"] = $appendTable . "." . $val[0];
                            }
                        }
                    }
                }

                $oldCsvCol = $this->displayCsvNameArray;
                
                $this->buildDataArray($fields, false);
                
                $newCsvCol = $this->displayCsvNameArray;
                
                $this->displayCsvNameArray = array_merge($oldCsvCol, $newCsvCol);

                foreach ($this->dbColumnsArray as $csvCol => $dbCol) {
                    if ($this->settingVars->pageArray[$this->settingVars->pageName]["ACCOUNT"] == $dbCol) {
                        $this->settingVars->pageArray[$this->settingVars->pageName]["ACCOUNT"] = $csvCol;
                    }

                    $tmpArr[] = array('label' => $this->displayCsvNameArray[$csvCol], 'data' => $csvCol);
                }

                $this->jsonOutput['fieldSelection'] = $tmpArr;
                $getField = $this->settingVars->pageArray[$this->settingVars->pageName]["ACCOUNT"]; // DEFINED ACCOUNT NAME FROM PAGE SETTING
                $this->jsonOutput['gridConfig']['selectedField'] = $this->gridFields[0]; //$getField;
                
            } elseif (isset($this->settingVars->pageArray[$this->settingVars->pageName]["ACCOUNT"]) &&
                    !empty($this->settingVars->pageArray[$this->settingVars->pageName]["ACCOUNT"])) {
                $this->skipDbcolumnArray = true;
            } else {
                $response = array("configuration" => array("status" => "fail", "messages" => array("Page isn't configured properly.")));
                echo json_encode($response);
                exit();
            }        
        }
        
        return;
    }
}
?> 