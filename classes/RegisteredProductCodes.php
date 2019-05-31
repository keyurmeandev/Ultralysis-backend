<?php

namespace classes;

use projectsettings;
use db;
use config;

class RegisteredProductCodes extends config\UlConfig {
    /*     * ***
     * Default gateway function, should be similar in all DATA CLASS
     * arguments:
     *  $settingVars [project settingsGateway variables]
     * @return jsonOutput with all data that should be sent to client app
     * *** */

    public function go($settingVars) {
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES
        
        if (isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true')
        {
            $columnConfig['CUSTOMER'] = "Customer";
            $columnConfig['PRODUCT_CODE'] = "Product Code";
            $this->jsonOutput['GRID_SETUP'] = $columnConfig;
            $this->getGroupNames();
        }
        else
            $this->createSkuList();
        
        return $this->jsonOutput;
    }

    public function getGroupNames()
    {
        $iterator = scandir($this->settingVars->registeredProductCodesFilePath);
        
        $groups = array();
        
        foreach($iterator as $fileInfo)
        {
            $tmp = explode('.', $fileInfo);
            if(!empty($tmp[0]) && !is_dir($this->settingVars->registeredProductCodesFilePath."/".$fileInfo))
                $groups[] = $tmp[0];
        }
        
        $query = "SELECT GID, GNAME FROM ".$this->settingVars->grouptable." WHERE GID IN (".implode(',', $groups).")";
        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
        if(is_array($result) && !empty($result))
        {
            foreach($result as $data)
                $groupList[] = array('data' => $data['GID'], 'label' => $data['GNAME']);
        }
        
        $_REQUEST['selectedGid'] = $groupList[0]['data'];
        $_REQUEST['selectedGName'] = $groupList[0]['label'];
        
        $this->jsonOutput['groupList'] = $groupList;
        $this->createSkuList();
    }
    
    public function createSkuList()
    {
        $skuList = array();
        $row = 1;
        
        if($_REQUEST['selectedGid'] != "")
        {
            if (($handle = fopen($this->settingVars->registeredProductCodesFilePath."/".$_REQUEST['selectedGid'].".csv", "r")) !== FALSE) 
            {
                while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) 
                {
                    if($row > 1)
                        $skuList[] = array("CUSTOMER" => $_REQUEST['selectedGName'], "PRODUCT_CODE" => $data[0]);
                    
                    $row++;
                }
                fclose($handle);
            }
        }
        $this->jsonOutput['sku'] = $skuList;
    }

}

?>