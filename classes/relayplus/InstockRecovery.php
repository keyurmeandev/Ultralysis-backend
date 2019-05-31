<?php
namespace classes\relayplus;

use filters;
use utils;
use db;
use config;

class InstockRecovery extends config\UlConfig {
		
    /*     * ***
     * Default gateway function, should be similar in all DATA CLASS
     * arguments:
     *  $settingVars [project settingsGateway variables]
     * @return $xmlOutput with all data that should be sent to client app
     * *** */

    public function go($settingVars) {
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES		
        $this->redisCache = new \utils\RedisCache($this->queryVars);

        $action = $_REQUEST['action'];
        switch ($action) {
			case 'prepareGrid';
                $this->prepareGrid();
                break;
        }        
        
		return $this->jsonOutput;
    }
	
	/**
     * getInstockRecoveryLevels()
     * It will list all InstockRecoveryLevels data
     * 
     * @return array
     */
	private function getInstockRecoveryLevels()
    {        
		$query = "SELECT DD_LOOKUP " .
            ", PUSH ".
            " FROM ". $this->settingVars->instockRecoveryLevelTable .
			" WHERE ACCOUNTID = ".$this->settingVars->aid." AND GID = ".$this->settingVars->GID." ".
			" GROUP BY DD_LOOKUP, PUSH ORDER BY DD_LOOKUP ASC, PUSH ASC";		
		
		$redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
		if ($redisOutput === false) {
		    $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
		    $this->redisCache->setDataForHash($result);
		} else {
		    $result = $redisOutput;
		}
		return $result;
    }
		
    private function prepareGrid() {

		$instockRecoveryLevels = $this->getInstockRecoveryLevels();
        $id 		= key_exists('ID', $this->settingVars->dataArray['F2']) ? $this->settingVars->dataArray['F2']['ID'] : $this->settingVars->dataArray['F2']['NAME'];
        $storeid 	= key_exists('ID', $this->settingVars->dataArray['F3']) ? $this->settingVars->dataArray['F3']['ID'] : $this->settingVars->dataArray['F3']['NAME'];
        $storename 	= $this->settingVars->dataArray['F3']['NAME'];
        $skuname 	= $this->settingVars->dataArray['F2']['NAME'];
		$arr		= array();
        $query = "SELECT skuID as SKUID" .					
				",MAX(sku_flags) as SKUFLAG " .
				",MAX(sku) as SKU " .
				",MAX(modular_items_flag) as ITEMFLAG " .
				",sno as SNO " .
				",MAX(sname) as STORE " .
				",MAX(state) as STATE " .
				",MAX(VNPK_qty) as VNPKQTY " .
				",MAX(VNPK_cost) as VNPKCOST " .
				",MAX(WHPK_cost) as WHPKCOST " .
				",MAX(avg_str_dd) as AVGSTRDD " .
				",MAX(OHQ) as OHQ " .
				",MAX(StoreTrans) as STORETRANS " .
				",MAX(StoreWhs) as STOREWHS " .
				",MAX(StoreOrder) as STOREORDER " .
				",MAX(TSI) as TSI " .
				"FROM ".$this->settingVars->instockRecoveryTable." ".
				"WHERE accountID = ".$this->settingVars->aid." ".
				"GROUP BY SKUID, SNO ".			
				"ORDER BY SKUID DESC, SNO DESC";
		
		$redisOutput = $this->redisCache->checkAndReadByQueryHashFromCache($query);
		if ($redisOutput === false) {
		    $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
		    $this->redisCache->setDataForHash($result);
		} else {
		    $result = $redisOutput;
		}

		if (is_array($result) && !empty($result)){
			foreach($result as $data){
				foreach($instockRecoveryLevels as $key=>$val){
					if($data['AVGSTRDD'] >= $val['DD_LOOKUP']){
						$getLevel = $val['PUSH'];
						continue;
					}
				}
				
				if(isset($_REQUEST['WOS']) && $_REQUEST['WOS'] != ''){
					$temp					= array();
					$temp['SKUID']			= $data['SKUID'];
					$temp['SKUFLAG']		= $data['SKUFLAG'];
					$temp['SKU']			= $data['SKU'];
					$temp['ITEMFLAG']		= $data['ITEMFLAG'];
					$temp['SNO']			= $data['SNO'];
					$temp['STORE']			= $data['STORE'];
					$temp['STATE']			= $data['STATE'];
					$temp['VNPKQTY']		= $data['VNPKQTY'];
					$temp['VNPKCOST']		= $data['VNPKCOST'];
					$temp['WHPKCOST']		= $data['WHPKCOST'];
					$temp['PIPELINE']		= $data['OHQ'] + $data['STORETRANS'] + $data['STOREWHS'];
					$temp['WOS']			= ($data['AVGSTRDD'] > 0) ? ceil($temp['PIPELINE']/$data['AVGSTRDD']) : 0;					
					$temp['SCRIPTDCUNITS']	= $getLevel;
					$temp['DCPUSHCASES']	= ($data['VNPKQTY'] > 0) ? ceil($temp['SCRIPTDCUNITS']/$data['VNPKQTY']) : 0;					
					$temp['AVGSTRDD']		= $data['AVGSTRDD'];
					$temp['NEWWOS']			= ($temp['AVGSTRDD'] > 0) ? ceil(($temp['PIPELINE']+($temp['DCPUSHCASES']*$temp['VNPKQTY']))/$temp['AVGSTRDD']) : 0;
					$temp['OHQ']			= $data['OHQ'];
					$temp['STORETRANS']		= $data['STORETRANS'];
					$temp['STOREWHS']		= $data['STOREWHS'];
					$temp['STOREORDER']		= $data['STOREORDER'];
					$temp['TSI']			= $data['TSI'];
					
					if($temp['WOS'] < $_REQUEST['WOS'])
						$arr[]	= $temp;
				}
			}
		}
		$this->jsonOutput['prepareGrid'] = $arr;        
    }
}
?>