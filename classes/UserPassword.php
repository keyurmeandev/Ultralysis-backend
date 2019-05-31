<?php

namespace classes;

use projectsettings;
use datahelper;
use filters;
use db;
use config;
use utils;
use lib;

class UserPassword extends config\UlConfig {

    private $maps;
    private $querypart;
    private $TOTAL_TY_SALES;
    private $pageName;
    private $dbColumnsArray;
    private $displayCsvNameArray;

    /*     * ***
     * Default gateway function, should be similar in all DATA CLASS
     * arguments:
     *  $settingVars [project settingsGateway variables]
     * @return $jsonOutput with all data that should be sent to client app
     * *** */

    public function go($settingVars) {
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES

        /*$ultraUtility   = lib\UltraUtility::getInstance();
        $result = $ultraUtility->getAllUsers();*/
        
        $encryption   = new lib\Encryption();
        $encryptionNew   = new lib\Encryption_NEW();
        /*$insertStr = array();
        foreach ($result as $k => $detail) {
            $decodePass = $encryption->decode($detail['pass']);
            $newEncodePass = $encryptionNew->encode($decodePass);
            // $newDecodePass = $encryptionNew->decode($newEncodePass);
            // $result[$k]['decodePass'] = $decodePass;
            $result[$k]['pass'] = $newEncodePass;
            // $result[$k]['newDecodePass'] = $newDecodePass;
            $insertStr[] = '("'.implode('","', array_values($result[$k])).'")';
        }*/
        
        $decodePass = $encryption->decode('BkNLghmEZH-QJz60BrWnB1zEmnb56U48XtcbIf5SR6s');
        $newEncodePass = $encryptionNew->decode('MkFnUVNvVVhaRWk1MS9qUVZQWTUwdz09Ojp9na4e7LUShG7Yw5ezh1sC');

        var_dump($decodePass);
        var_dump($newEncodePass);
        /*$result = $ultraUtility->insertIntoUserNewPass(implode(",", $insertStr));
        echo "<pre>";
        print_r($result);*/
        exit();
        
    }

}

?>