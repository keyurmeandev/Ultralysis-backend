<?php
session_start();
session_write_close();
set_time_limit(1000000);
ini_set("memory_limit", "4096M");
global $controller;

$controller = new controller(substr($_REQUEST['destination'],0,1)!="\\" ? "classes\\".$_REQUEST['destination'] : $_REQUEST['destination'] , $_REQUEST['outputType']);

class controller
{
    public $projectSettings;
    public $settingVars;
    public $businessLogic;
    private $preventInjection;
    
    function __construct($class_name,$outputType='x'){  

        //DEFINE PROJECT ROOT VAR [USE FULL WHEN YOU NEED TO LOAD ASSETS]
        if (!defined('PROJECT_ROOT')) {
            define('PROJECT_ROOT', dirname(__FILE__) . '/');
        }
        
        //REGISTER AUTOLOADERS [BOTH LOCAL AND GLOBAL]
        spl_autoload_register('classLoader',false,false); //REGISTER LOCAL CLASS LOADER
        include $_SERVER['DOCUMENT_ROOT'].DIRECTORY_SEPARATOR."AutoLoader.php"; //REGISTER GLOBAL CLASS LOADER

        // REGISTER CUSTOM ERROR HANDLER
        $ultErrorHandler = new lib\UltErrorHandler();
        $ultErrorHandler->registerCustomErrorHandler("../global/project/config.xml");

        //VALIDATE ACCESS PERMISSION
        lib\AccessebilityChecker::advanceSecureCheck("../global/project/config.xml");

        //INITIATE PROJECT SETTINGS
        $this->projectSettings      = projectsettings\settingsGateway::getInstance(); //get settingsGateway class instance  or create a new one if not exists
        $this->projectSettings->initiateQueryVars();
        
        // $redisCache = new utils\RedisCache($this->settingVars, $this->projectSettings);
        $redisCache = new utils\RedisCache($this->projectSettings);
        $jsonOutput = $redisCache->checkAndReadFromCache();

        if ($jsonOutput === false) {
            //LOAD DESTINATION CLASS AND PREPARE DATA
            if(class_exists($class_name))
            {
                $this->settingVars      = $this->projectSettings->initiateSettingVars(); //set settingsGateway variables [..tablename , queryHandler....etc]
                
                //SECURE GET/POST VARS FROM SQL INJECTION
                $this->preventInjection     = new config\PreventInjection();
                $this->bringSafety();

                $this->businessLogic    = new $class_name();
                $jsonOutput             = $this->businessLogic->go($this->settingVars);

                if (!$redisCache->skipCommonCacheHash) {
                    $redisCache->setDataForHash($jsonOutput);
                }
            }
            else die('Class not found');
        }
        else
            $this->projectSettings->addVisitingPages();

        //RETURN OUTPUT DATA IN JSON FORMAT
        print json_encode($jsonOutput);    

    }
    
    private function bringSafety(){
        foreach($_REQUEST as $key=>$data)
        {
            if(key_exists($key,$_GET) || key_exists($key,$_POST)){
                if(is_array($data))
                {
                    foreach($data as $secondKey=>$secondData)
                    {
                        if($key!="projectID")
                       $_REQUEST[$key][$secondKey] = $this->preventInjection->user_injection_check($key."->".$secondKey , mysqli_real_escape_string($this->projectSettings->linkid,$secondData)); 
                    }
                }
                else {
                    if($key!="projectID")
                    $_REQUEST[$key] = $this->preventInjection->user_injection_check($key , mysqli_real_escape_string($this->projectSettings->linkid,$data));
                }
            }
        }
        
        /*** USEFUL WHEN DEBUGGING, PLZ DON'T DELETE ***/
        //echo "<pre>";print_r($_REQUEST);exit;
    }
    
}


function classLoader($class_name){
    $class_name = PROJECT_ROOT .
                      str_replace('\\',DIRECTORY_SEPARATOR,$class_name) .
                      '.php';
    
    //IF CLASS ALREADY LOADED
    if ((class_exists($class_name,FALSE))) {
        return FALSE;
    }
    
    
    if ((file_exists($class_name) === FALSE) || (is_readable($class_name) === FALSE)) {
        return FALSE;
    }
    
    require($class_name);
}
    
function getValueVolume($settingVars){
    if($_REQUEST['ValueVolume'] == 'undefined'){
        $searchKey = (is_array($settingVars->pageArray["MEASURE_SELECTION_LIST"]) && !empty($settingVars->pageArray["MEASURE_SELECTION_LIST"])) 
        ? array_search(true, array_column($settingVars->pageArray["MEASURE_SELECTION_LIST"], 'selected')) : '';

        if(is_numeric($searchKey)){
            $_REQUEST['ValueVolume'] = $settingVars->pageArray["MEASURE_SELECTION_LIST"][$searchKey]['measureID'];
        }elseif((is_array($settingVars->pageArray["MEASURE_SELECTION_LIST"]) && !empty($settingVars->pageArray["MEASURE_SELECTION_LIST"]))){
            $_REQUEST['ValueVolume'] = $settingVars->pageArray["MEASURE_SELECTION_LIST"][0]['measureID'];
        }else{
            $_REQUEST['ValueVolume'] = 1;
        }

    }

    return $settingVars->measureArray['M'.($_REQUEST['ValueVolume'])]['VAL'];
}
    
function getValueVolumeForShipAnalysis($settingVars){
    if($_REQUEST['ValueVolume'] == 'undefined'){
        $searchKey = (is_array($settingVars->pageArray["MEASURE_SELECTION_LIST"]) && !empty($settingVars->pageArray["MEASURE_SELECTION_LIST"])) 
        ? array_search(true, array_column($settingVars->pageArray["MEASURE_SELECTION_LIST"], 'selected')) : '';

        if(is_numeric($searchKey)){
            $_REQUEST['ValueVolume'] = $settingVars->pageArray["MEASURE_SELECTION_LIST"][$searchKey]['measureID'];
        }elseif((is_array($settingVars->pageArray["MEASURE_SELECTION_LIST"]) && !empty($settingVars->pageArray["MEASURE_SELECTION_LIST"]))){
            $_REQUEST['ValueVolume'] = $settingVars->pageArray["MEASURE_SELECTION_LIST"][0]['measureID'];
        }else{
            $_REQUEST['ValueVolume'] = 1;
        }

    }
    
    $accountMeasures =  array(
                              'SALE_FIELD'  =>  $settingVars->measureArrayForShipAnalysis[($_REQUEST['ValueVolume']-1)]['SALE_FIELD']
                              ,'SHIP_FIELD' =>  $settingVars->measureArrayForShipAnalysis[($_REQUEST['ValueVolume']-1)]['SHIP_FIELD']
                            );
    return $accountMeasures;
}
    
function getAdjectiveForIndex($index){
    $adjectiveArr = array("PRIMARY","SECONDARY","TERTIARY");
    return $adjectiveArr[$index];
}
?>