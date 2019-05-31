<?php
namespace config;

use lib;
use db;
use config;
use utils;

class ConfigurationCheck extends UlConfig {

    public $settings;
    public $configErrors = array();
    public $settingVars;   //containes all setting variables
    public $queryVars;     //containes query related variables [queryHandler,linkid]
    public $dbColumnsArray = array();

    public function __construct($settingVars, $queryVars) {
        $this->settingVars  = $settingVars;
        $this->queryVars    = $queryVars;
        $this->fetchSettings();
    }

    public function checkConfiguration()
    {
        if ($_REQUEST['DataHelper'] == "true") {
            $this->verifyMenuAndPageConfiguration();
            
			$projectTypes = array('relayplus', 'relayplus-sif', 'super-drug', 'sales-builder', 'Retailer', 'tesco-store-daily', 'nielsen', 'impulseViewJS');
			
            //if ($_REQUEST['projectType'] == 'relayplus' || $_REQUEST['projectType'] == 'relayplus-sif' || $_REQUEST['projectType'] == 'super-drug' || $_REQUEST['projectType'] == 'sales-builder')
			if(in_array($_REQUEST['projectType'], $projectTypes))
                $this->settings['has_pods'] = false;
            elseif ($_REQUEST['projectType'] != 'lcl')
                $this->verifyPodsConfiguration();
            else
                $this->verifyTemplatePodsConfiguration();

            // $this->verifyCurrencyConfiguration();
            $this->verifyFilterConfiguration();
        } else {
            // $this->verifyCurrencyConfiguration();
            $this->verifyFilterConfiguration();
        }

        if (!empty($this->configErrors)) {
            $response = array("configuration" => array("status" => "fail", "messages" => $this->configErrors));
            echo json_encode($response);
            exit();
        }

        return true;
    }    

    public function fetchSettings()
    {
        $this->settings = $this->queryVars->projectConfiguration;
    }

    public function fetchFieldConfig($fields, $tableName = "")
    {
        $dbColumns = array();
        if(is_array($this->settingVars->clientConfiguration) && !empty($this->settingVars->clientConfiguration)){
            foreach ($fields as $field) 
            {
                if($tableName != "")
                    $searchKeyDB  = array_search($tableName.".".$field, array_column($this->settingVars->clientConfiguration, 'db_table_db'));
                else
                    $searchKeyDB  = array_search($field, array_column($this->settingVars->clientConfiguration, 'db_column'));
                    
                if ($searchKeyDB !== false && !empty($tableName) && $this->settingVars->clientConfiguration[$searchKeyDB]['db_table'] == $tableName ) {
                    $dbColumns[] = $this->settingVars->clientConfiguration[$searchKeyDB];
                }
                if ($searchKeyDB !== false && empty($tableName) ) {
                    $dbColumns[] = $this->settingVars->clientConfiguration[$searchKeyDB];
                }
            }                       
        }

        return $dbColumns;
    }

    public function verifyMenuAndPageConfiguration()
    {
        $redisCache = new utils\RedisCache($this->queryVars);
        $redisOutput = $redisCache->checkAndReadByStaticHashFromCache('menu_configuration');

        if ($this->queryVars->isInitialisePage || $redisOutput === false) {
            $menuBuilt = new lib\MenuBuilt($this->settingVars->projectID, $this->settingVars->aid, $this->queryVars);
            $menus     = $menuBuilt->getMenus(true);
            $redisCache->setDataForStaticHash($menus);
        }else {
            $menus = $redisOutput;
        }

        if (empty($menus))
            $this->configErrors[] = "Menu configuration not found.";
    }

    public function verifyPodsConfiguration()
    {
        $hasPods = (!isset($this->settings['has_pods'])) ? true : ((isset($this->settings['has_pods']) && $this->settings['has_pods'] == '1') ? true : false);

        if (!$hasPods)
            return;

        if (!$this->configurePODs('pod_one_key'))
            $this->configErrors[] = "Pods two/three configuration not found.";

        if (!$this->configurePODs('pod_four_five_key'))
            $this->configErrors[] = "Pods four/five configuration not found.";
    }

    public function verifyTemplatePodsConfiguration()
    {
/*         $cid = $this->settingVars->aid;
        $pid = $this->settingVars->projectID;

        $query = "SELECT setting_value  FROM ". $this->settingVars->pageConfigTable . " WHERE accountID = ".$cid.
            " AND projectID = ".$pid." AND setting_name = 'pods_settings' ";
        $pageConfig = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->projectManagerLinkid, db\ResultTypes::$TYPE_OBJECT);

        if (!is_array($pageConfig) || empty($pageConfig))
            $this->configErrors[] = "Pods configuration not found."; */
    }

    public function configurePODs($settingName)
    {
        $result = (isset($this->settings[$settingName]) && !empty($this->settings[$settingName])) ? $this->settings[$settingName] : '';
       
        if(!empty($result)) {
            if(empty($this->fetchFieldConfig(array($result))))
                return false;
        }
        else
            return false;

        return true;
    }

    /*public function verifyCurrencyConfiguration()
    {
        $result = (isset($this->settings['has_currency']) && !empty($this->settings['has_currency'])) ? $this->settings['has_currency'] : '';
        if(empty($result))
            $this->configErrors[] = "Currency configuration not found.";
		else
			return $result;
    }*/

    public function verifyFilterConfiguration()
    {
        if (!$this->findFilterConfiguration('has_product_filter', 'product_settings', $this->settingVars->skutable))
            $this->configErrors[] = "Product filter configuration not found.";

        if (!$this->findFilterConfiguration('has_kdr', 'kdr_settings'))
            $this->configErrors[] = "KDR filter configuration not found.";
        
        if (!$this->findFilterConfiguration('has_market_filter', 'market_settings', $this->settingVars->storetable))
            $this->configErrors[] = "Market filter configuration not found.";
        
        if($_REQUEST['projectType'] == "tesco-store-daily" || $_REQUEST['projectType'] == "impulseViewJS")
        {
            $result = (isset($this->settings['has_territory']) && !empty($this->settings['has_territory'])) ? $this->settings['has_territory'] : '';
            if ($result) 
            {
                $filterSettingStr = (isset($this->settings['has_territory_level']) && !empty($this->settings['has_territory_level'])) ? $this->settings['has_territory_level'] : '';
                if (empty($filterSettingStr))
                    $this->configErrors[] = "Terretory Level configuration not found.";
            }
        }
        else
        {
            if (!$this->findFilterConfiguration('has_territory', 'territory_settings'))
                $this->configErrors[] = "Terretory filter configuration not found.";        
        }

        if (!$this->findFilterConfiguration('has_account', 'account_settings', $this->settingVars->accounttable))
            $this->configErrors[] = "Account filter configuration not found.";
            
        if (!$this->findFilterConfiguration('has_sku_filter', 'sku_settings'))
            $this->configErrors[] = "SKU filter configuration not found.";
            
    }

    public function findFilterConfiguration($hasSettingName, $filterSettingName, $tableName = '')
    {
        // Query to fetch settings 
        $result = (isset($this->settings[$hasSettingName]) && !empty($this->settings[$hasSettingName])) ? $this->settings[$hasSettingName] : '';
        if ($result) {
            $filterSettingStr = (isset($this->settings[$filterSettingName]) && !empty($this->settings[$filterSettingName])) ? $this->settings[$filterSettingName] : '';
            if (!empty($filterSettingStr)) {
                $settings = explode("|", $filterSettingStr);
                // Explode with # because we are getting some value with # ie (PNAME#PIN) And such column name combination not match with db_column. 
                foreach($settings as $key => $data)
                {
                    $originalCol = explode("#", $data);
                    if(is_array($originalCol) && !empty($originalCol))
                        $settings[$key] = $originalCol[0];
                }
                $dbFields = $this->fetchFieldConfig($settings, $tableName);
                if (count($dbFields) != count($settings))
                    return false;
            }
            else
                return false;
        }

        return true;
    }

    public function checkClusterConfiguration()
    {
        // Query to fetch settings 
        $result = (isset($this->settings["has_cluster"]) && !empty($this->settings["has_cluster"])) ? $this->settings["has_cluster"] : '';
        if ($result) {
            $filterSettingStr = (isset($this->settings["cluster_settings"]) && !empty($this->settings["cluster_settings"])) ? $this->settings["cluster_settings"] : '';
            if (empty($filterSettingStr)) {
                $this->configErrors[] = "Cluster configuration not found.";
            }
            $filterSettingStr = (isset($this->settings["cluster_default_load"]) && !empty($this->settings["cluster_default_load"])) ? $this->settings["cluster_default_load"] : '';
            if (empty($filterSettingStr)) {
                $this->configErrors[] = "Cluster Default Load configuration not found.";
            }
                
        }else{
            $this->configErrors[] = "Cluster configuration not found.";
        }

        if (!empty($this->configErrors)) {
            $response = array("configuration" => array("status" => "fail", "messages" => $this->configErrors));
            echo json_encode($response);
            exit();
        }

        return true;
    }
	
	public function buildDataArray($pageFields = array(), $isCsvColumn = true, $appendTableNameWithDbColumn = false)
	{
        if (is_array($pageFields) && !empty($pageFields)) {
			$pageFieldArray = array();
			
			foreach ($pageFields as $pageField) {
				$pageFieldPart = explode("#", $pageField);
				$pageFieldArray[] = $pageFieldPart[0];
				
				if (count($pageFieldPart) > 1)
					$pageFieldArray[] = $pageFieldPart[1];
			}

            $pageFieldArray = array_unique($pageFieldArray);

            $dbColumns = array();

            if(is_array($this->settingVars->clientConfiguration) && !empty($this->settingVars->clientConfiguration)){
                foreach ($pageFieldArray as $field) {
                    if($isCsvColumn){
                        $searchKeyWithTable  = array_search($field, array_column($this->settingVars->clientConfiguration, 'db_table_csv'));
                        $searchKeyCSV  = array_search($field, array_column($this->settingVars->clientConfiguration, 'csv_column'));
                        if($searchKeyWithTable !== false){
                            $dbColumns[] = $this->settingVars->clientConfiguration[$searchKeyWithTable];
                        }elseif ($searchKeyCSV !== false) {
                            $dbColumns[] = $this->settingVars->clientConfiguration[$searchKeyCSV];
                        }
                    }elseif ($appendTableNameWithDbColumn) {
                        $searchKeyWithTable  = array_search($field, array_column($this->settingVars->clientConfiguration, 'db_table_db'));
                        if($searchKeyWithTable !== false){
                            $dbColumns[] = $this->settingVars->clientConfiguration[$searchKeyWithTable];
                        }
                    }else{
                        $searchKeyDB  = array_search($field, array_column($this->settingVars->clientConfiguration, 'db_column'));
                        if ($searchKeyDB !== false) {
                            $dbColumns[] = $this->settingVars->clientConfiguration[$searchKeyDB];
                        }
                    }
                }
            }

			// Creating dataArray for remaining configured fields of data-manager
			if (is_array($dbColumns) && !empty($dbColumns) && count($dbColumns) == count($pageFieldArray)) {
				foreach ($pageFields as $key => $pageField) {
					$pageFieldPart = explode("#", $pageField);
					$nameField = $pageFieldPart[0];
					
					if ($isCsvColumn && count(explode(".", $nameField))>1 )
						$searchKey = (is_array($dbColumns) && !empty($dbColumns)) ? array_search($nameField, array_column($dbColumns, 'db_table_csv')) : '';
                    elseif($appendTableNameWithDbColumn)
                    {
                        $nameField = explode(".", $nameField)[1];
                        $searchKey = (is_array($dbColumns) && !empty($dbColumns)) ? array_search($nameField, array_column($dbColumns, 'db_column')) : '';
                    }
					elseif ($isCsvColumn)
						$searchKey = (is_array($dbColumns) && !empty($dbColumns)) ? array_search($nameField, array_column($dbColumns, 'csv_column')) : '';
					else
						$searchKey = (is_array($dbColumns) && !empty($dbColumns)) ? array_search($nameField, array_column($dbColumns, 'db_column')) : '';
                    
					$dbColumn = $dbColumns[$searchKey]['db_column'];
					$csvColumn = $dbColumns[$searchKey]['csv_column'];
					$dbTable = $dbColumns[$searchKey]['db_table'];
					$nameAliase = strtoupper($dbColumn);
					$dbColumnUpper = strtoupper($dbTable.'.'.$dbColumn);
                    
                    $this->dbColumnsArray[$dbTable.'.'.$csvColumn] = $dbTable.'.'.$dbColumn;
					
                    $this->displayCsvNameArray[$dbTable.'.'.$csvColumn] = $csvColumn;
                    
                    $requestVar = str_replace(array(' ', '.', '[', '-'), '_', $csvColumn);
                    $this->requestCsvNameArray[$dbTable.'.'.$csvColumn] = $requestVar;
					
                    $this->displayDbColumnArray[strtoupper($dbTable.'.'.$dbColumn)] = $dbColumn;

                    if(count($pageFieldPart) > 1) {
                        $idField = $pageFieldPart[1];
                        if ($isCsvColumn && count(explode(".", $nameField))>1 )
                            $searchKey = (is_array($dbColumns) && !empty($dbColumns)) ? array_search($idField, array_column($dbColumns, 'db_table_csv')) : '';
                        elseif($appendTableNameWithDbColumn)
                        {
                            $idField = explode(".", $idField)[1];
                            $searchKey = (is_array($dbColumns) && !empty($dbColumns)) ? array_search($idField, array_column($dbColumns, 'db_column')) : '';
                        }
                        elseif ($isCsvColumn)
                            $searchKey = (is_array($dbColumns) && !empty($dbColumns)) ? array_search($idField, array_column($dbColumns, 'db_table_csv')) : '';
                        else
                            $searchKey = (is_array($dbColumns) && !empty($dbColumns)) ? array_search($idField, array_column($dbColumns, 'db_column')) : '';

                        $idDbColumn = $dbColumns[$searchKey]['db_column'];
                        $idCsvColumn = $dbColumns[$searchKey]['csv_column'];
                        $idDbTable = $dbColumns[$searchKey]['db_table'];

                        $this->dbColumnsArray[$idDbTable.'.'.$idCsvColumn] = $idDbTable.'.'.$idDbColumn;
                        
                        $this->displayCsvNameArray[$idDbTable.'.'.$idCsvColumn] = $idCsvColumn;
                        
                        $requestVar = str_replace(array(' ', '.', '[', '-'), '_', $idCsvColumn);
                        $this->requestCsvNameArray[$idDbTable.'.'.$idCsvColumn] = $requestVar;
                        
                        $this->displayDbColumnArray[strtoupper($idDbTable.'.'.$idDbColumn)] = $idDbColumn;

                        $dbColumnUpper = strtoupper($dbColumnUpper."_".$idDbTable.'.'.$idDbColumn);
                        
                        // Setting dataArray based on configuration to fetch DataHelper
                        $ID = $idDbTable.".".$idDbColumn;
                    }

                    
					
                    if (!isset($this->settingVars->dataArray[$dbColumnUpper])) {

                        if(count($pageFieldPart) > 1) {
							$this->settingVars->dataArray[$dbColumnUpper]['ID'] = $ID;
							$this->settingVars->dataArray[$dbColumnUpper]['ID_ALIASE'] = strtoupper($idDbColumn);
							$this->settingVars->dataArray[$dbColumnUpper]['ID_CSV'] = $idCsvColumn;
                            $this->settingVars->dataArray[$dbColumnUpper]['include_id_in_label'] = true;
						}
						// Setting dataArray based on configuration to fetch DataHelper
						$NAME = (!empty($dbTable)) ? $dbTable.".".$dbColumn : $dbColumn;
						
						$this->settingVars->dataArray[$dbColumnUpper]['NAME'] = $NAME;
						$this->settingVars->dataArray[$dbColumnUpper]['NAME_ALIASE'] = $nameAliase;
                        $this->settingVars->dataArray[$dbColumnUpper]['NAME_CSV'] = $csvColumn;
						$this->settingVars->dataArray[$dbColumnUpper]['tablename'] = $this->settingVars->tableArray[$dbTable]['tables'];
						$this->settingVars->dataArray[$dbColumnUpper]['link'] = $this->settingVars->tableArray[$dbTable]['link'];
						$this->settingVars->dataArray[$dbColumnUpper]['TYPE'] = $this->settingVars->tableArray[$dbTable]['type'];
						$this->settingVars->dataArray[$dbColumnUpper]['use_alias_as_tag'] = true;
					}
				}
			} else {
				$response = array("configuration" => array("status" => "fail", "messages" => array("Page isn't configured properly.")));
				echo json_encode($response);
				exit();
			}
		}
	}
}

?>