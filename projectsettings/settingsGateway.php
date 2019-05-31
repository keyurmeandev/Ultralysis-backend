<?php

namespace projectsettings;

use utils;
use db;
use lib;

class settingsGateway {

    public $queryHandler;
    public $linkid;
    public $redisLink;
    public $isInitialisePage;
    public $projectConfiguration;

    public $devServer;
    public $aid;
    public $uid;
    public $projectID;
    public $connectionManager;

    public static function getInstance() {
        static $instance = null;
        if (null === $instance) {
            $instance = new settingsGateway();
        }
        return $instance;
    }

    protected function __construct() {
        
    }

    private function __clone() {
        
    }

    private function __wakeup() {
        
    }

    public function initiateQueryVars() {
        switch ($_REQUEST['projectType']) {
            case 'lcl':
                $configPath = "../global/project/config.xml";
                break;
            case 'relayplus':
                $configPath = "../global/project/config.xml";
                break;
            case 'tesco-store-daily':
                $configPath = "../global/project/config.xml";
                break;
            case 'sales-builder':
                $configPath = "../sales-builder/project/config.xml";
                break;
            case 'ddb':
                $configPath = "../dynamic-data-builder/config.xml";
                break;
            /*Not moved to the comphp */
            case 'Retailer':
                $configPath = "../retailer/project/config.xml";
                break;
            case 'totalmults':
                $configPath = "../total-mults/weekly/project/config.xml";
                break;
            case 'totaldaily':
                $configPath = "../total-mults/daily/project/config.xml";
                break;
            case 'ub':
                $configPath = "../ub/tesco-format/html-project/config.xml";
                break;
            case 'ubtesco':
                $configPath = "../ub/tesco-format/html-tesco-project/config.xml";
                break;              
            case 'gaylea_gt':
                $configPath = "../gaylea/gaint/project/config.xml";
                break;
            case 'super-drug':
                $configPath = "../super-drug/project/config.xml";
                break;
            default :
                $configPath = "../retail-link/project/config.xml"; 
                break;
        }

        $xml = simplexml_load_file($configPath); // for lcl project
        $this->devServer = $xml->project->development;

        // if ($this->devServer == "false") {
            session_start();
            session_write_close();

            // will work when server on live
            if (!isset($_REQUEST["projectID"]) || empty($_REQUEST["projectID"])) {
                exit(json_encode(array('access' => 'unauthorized')));
            } else {
                $this->aid = $_SESSION["accountID"];
                $this->uid = $_SESSION["account"];
            }
        /*} else {
            // will work when server on development
            $this->projectID = $xml->project->projectID;
            $this->aid = $xml->project->accountID;
            $this->uid = $xml->project->account;
        }*/

        /**
         * if $_REQUEST['projectID'] is not empty then the projectID will be set from $_REQUEST['projectID']
         * if it is live server then the projectID will be set from accountID of session 
         * otherwise the projectID will be set from config.xml 
         */
        if (isset($_REQUEST['projectID']) && !empty($_REQUEST['projectID']))
            $this->projectID = utils\Encryption::decode($_REQUEST['projectID']);
// echo $this->projectID;die;
         // if ($this->projectID == 353)
        $_REQUEST['log_query'] = true; 

        $this->isInitialisePage = false;
        if (isset($_REQUEST['destination']) && !empty($_REQUEST['destination']) && $_REQUEST['destination'] == 'InitialiseProject' &&
            isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true') {
            $this->isInitialisePage = true;
        }

        if (isset($_REQUEST['projectType']) && !empty($_REQUEST['projectType']) && $_REQUEST['projectType'] == 'sales-builder' &&
            isset($_REQUEST["VeryFirstRequest"]) && !empty($_REQUEST["VeryFirstRequest"]) && $_REQUEST["VeryFirstRequest"] == 'true') {
            $this->isInitialisePage = true;
        }

        if (isset($_REQUEST['destination']) && !empty($_REQUEST['destination']) && $_REQUEST['destination'] == 'InitialiseProject' &&
            isset($_REQUEST["fetchConfig"]) && !empty($_REQUEST["fetchConfig"]) && $_REQUEST["fetchConfig"] == 'true' && 
            isset($_REQUEST["SIF"]) && !empty($_REQUEST["SIF"]) && $_REQUEST["SIF"] == 'YES') {
            $this->isInitialisePage = false;
        }   

        if (isset($_REQUEST['projectType']) && !empty($_REQUEST['projectType']) && $_REQUEST['projectType'] == 'ddb' &&
            isset($_REQUEST['destination']) && !empty($_REQUEST['destination']) && $_REQUEST['destination'] == 'ddb\ProjectLoader') {
            $this->isInitialisePage = true;
        }

        
        $this->connectionManagerLIVE = new db\I3_Connection_LIVE();
        $this->connectionManager = new db\I3_Connection();

        $this->queryHandler = new db\QueryHandler();
        if( $this->projectID == 733){    
            $this->projectID = 781;
            $this->projectID = 920; // v1
            $this->aid = 126;
            $this->projectManagerLinkid = $this->connectionManagerLIVE->Connect(19); // project_manager_new 
            //$this->projectManagerLinkid = $this->connectionManager->Connect(19); // project_manager_new
        }
        /*else if($this->projectID == 738) {
            $this->projectID = 568;
            $this->aid = 80;
            $this->projectManagerLinkid = $this->connectionManagerLIVE->Connect(19); // project_manager_new    
        }*/
        else
            $this->projectManagerLinkid = $this->connectionManager->Connect(19); // project_manager_new
        $this->linkid = $this->connectionManager->Connect(9); // canadalcl

        $redisConnectionManager = new db\REDIS_Connection();
        $this->RedisHost     = $redisConnectionManager->getHost();
        $this->RedisPort     = $redisConnectionManager->getPort();
        $this->RedisPassword = $redisConnectionManager->getPassword();

        $this->redisLink = $redisConnectionManager->Connect();

        $this->redisCacheVersion = 1; // DO NOT PUT Freaction point value(.) (Like 1.0 or 1.2.1), KEEP IT INT VALUE
        
        $redisCache = new utils\RedisCache($this);
        $redisCacheVersionOutput = $redisCache->checkAndReadByStaticHashFromCache('redis_cache_version');
        
        if(empty($redisCacheVersionOutput))
            $redisCache->setDataForStaticHash($this->redisCacheVersion);
        else if($this->redisCacheVersion > $redisCacheVersionOutput) {
            $this->redisLink->select($this->projectID);
            $this->redisLink->flushDb();
            $redisCache->setDataForStaticHash($this->redisCacheVersion);
        }

        $this->checkForMaintainanceMode();        
        
        $this->getAllConfiguration();

        $this->removeCachedDataKeys();
    }

    public function checkForMaintainanceMode() {
        $query = "SELECT setting_name,setting_value FROM pm_config WHERE accountID=".$this->aid." AND projectID = ".$this->projectID." AND setting_name='is_under_maintainance'";
        $isUnderMaintainance = $this->queryHandler->runQuery($query, $this->projectManagerLinkid, db\ResultTypes::$TYPE_OBJECT);

        if (is_array($isUnderMaintainance) && !empty($isUnderMaintainance) && $isUnderMaintainance[0]['setting_value'] == 1) {
            if ($_REQUEST['action'] == 'checkMaintainance')
                exit(json_encode(array('access' => 'maintainance', 'reload' => 'NO')));
            else
                exit(json_encode(array('access' => 'maintainance')));
        } else {
            if ($_REQUEST['action'] == 'checkMaintainance')
                exit(json_encode(array('access' => 'maintainance', 'reload' => 'YES')));
        }

        return true;
    }

    public function getAllConfiguration() {
        $redisCache = new utils\RedisCache($this);
        $redisOutput = $redisCache->checkAndReadByStaticHashFromCache('pm_config_settings');
        
        if ($this->isInitialisePage || $redisOutput === false) {
            $query = "SELECT setting_name,setting_value FROM pm_config WHERE accountID=".$this->aid." AND projectID = ".$this->projectID;

            $settings = $this->queryHandler->runQuery($query, $this->projectManagerLinkid, db\ResultTypes::$TYPE_OBJECT);
            if (is_array($settings) && !empty($settings)) {
                foreach ($settings as $key => $setting) {
                    $this->projectConfiguration[$setting['setting_name']] = $setting['setting_value'];
                }

                $redisCache->setDataForStaticHash($this->projectConfiguration);
            }
        } else {
            $this->projectConfiguration = $redisOutput;
        }
    }

    public function removeCachedDataKeys() {
        $removeKeys = array('time_filter_data', 'lcl_measure_configuration_sif');
        $redisCache = new utils\RedisCache($this);
        
        if ($this->isInitialisePage && is_array($removeKeys) && !empty($removeKeys)) {
            $redisCache->removeDataKeys($removeKeys);
        }
    }

    /**
     * Returns the name of the currently set context.
     *
     * @return string Name of the current context
     */
    public function initiateSettingVars() {
        $commonParams = array('accountID' => $this->aid, 'uid' => $this->uid, 'projectID' => $this->projectID);
        switch ($this->projectID) {
        
            case 583: // Parmalat Nielsen
                $varsClass = new ParmalatNielsenLcl($this->aid,$this->projectID);
                break;        

            case 579: // Dare Nielsen
                $varsClass = new DareNielsenLcl($this->aid,$this->projectID);
                break;
                
            case 576: // MJN USA Shipments
                $this->linkid = $this->connectionManager->Connect(9);
                $varsClass = new MjnUsaShipmentLcl($this->aid,$this->projectID);
                break;

    	    case 569: // MJN FACTORY SALES - WEEKLY (Html)
                    $this->linkid = $this->connectionManager->Connect(8); // mjn
                    $varsClass = new MjnFactorySalesWeekly($this->aid,$this->projectID,array(1));
                    break;

    	    /* case 568: // MJN FACTORY SALES - MONTHLY (Html)
                    $this->linkid = $this->connectionManager->Connect(8); // mjn
                    $varsClass = new MjnFactorySalesMonthly($this->aid,$this->projectID,array(1));
                    break; */

    	    case 567: // RETAIL POS - EXECUTIVE SUMMARY (Html)
                    $this->linkid = $this->connectionManager->Connect(8); // mjn
                    $varsClass = new MjnPosMasterSystem($this->aid,$this->projectID,array(2,3,4,5,12,13,14)); 
                    break;

    	    case 566: // RETAIL POS - SHOPPERS DRUG MART (Html)
                    $this->linkid = $this->connectionManager->Connect(8); // mjn
                    $varsClass = new MjnPosMasterSystem($this->aid,$this->projectID,array(4)); // POS SDM
                    break;

    	    case 604: // RETAIL POS - OFG (Html)
                    $this->linkid = $this->connectionManager->Connect(8); // mjn
                    $varsClass = new MjnPosMasterSystem($this->aid,$this->projectID,array(14)); // POS OFG
                    break;
                    
    	    case 565: // DISTRIBUTOR - MCMAHON (Html)
                    $this->linkid = $this->connectionManager->Connect(8); // mjn
                    $varsClass = new MjnDistMasterSystem($this->aid,$this->projectID,array(9)); // DIST McMahon
                    break;

    	    case 564: // DISTRIBUTOR - MCKESSON (Html)
                    $this->linkid = $this->connectionManager->Connect(8); // mjn
                    $varsClass = new MjnDistMasterSystem($this->aid,$this->projectID,array(8)); // DIST MCKESSON
                    break;

    	    case 563: // DISTRIBUTOR - KOHL & FRISCH (Html)
                    $this->linkid = $this->connectionManager->Connect(8); // mjn
                    $varsClass = new MjnDistMasterSystem($this->aid,$this->projectID,array(7)); // DIST KOHL & FRISCH
                    break;

    	    case 562: // DISTRIBUTOR - JEAN COUTU (Html)
                    $this->linkid = $this->connectionManager->Connect(8); // mjn
                    $varsClass = new MjnDistMasterSystem($this->aid,$this->projectID,array(6)); // DIST JEAN COUTU
                    break;

    	    case 561: // RETAIL POS - WALMART CANADA (Html)
                    $this->linkid = $this->connectionManager->Connect(8); // mjn
                    $varsClass = new MjnPosMasterSystem($this->aid,$this->projectID,array(3)); // POS WALMART CANADA
                    break;

    	    case 560: // RETAIL POS - JEAN COUTU (Html)
                    $this->linkid = $this->connectionManager->Connect(8); // mjn
                    $varsClass = new MjnPosMasterSystem($this->aid,$this->projectID,array(5)); // POS JEAN COUTU
                    break;

    	    case 559: // RETAIL POS - COSTCO CANADA (Html)
                    $this->linkid = $this->connectionManager->Connect(8); // mjn
                    $varsClass = new MjnPosMasterSystem($this->aid,$this->projectID,array(12)); // POS COSTCO CANADA
                    break;

    	    case 558: // RETAIL POS - TOYS R US (Html)
                    $this->linkid = $this->connectionManager->Connect(8); // mjn
                    $varsClass = new MjnPosMasterSystem($this->aid,$this->projectID,array(13)); // POS TOYS R US
                    break;

    	    case 557: // RETAIL POS - POSTAL CODE (Html)
                    $this->linkid = $this->connectionManager->Connect(8); // mjn
                    $varsClass = new MjnPosMasterSystem($this->aid,$this->projectID,array(2,3,4,5,12,13,14));
                    break;

    	    case 556: // RETAIL POS - LOBLAW COMPANIES (Html)
                    $this->linkid = $this->connectionManager->Connect(8); // mjn
                    $varsClass = new MjnPosMasterSystem($this->aid,$this->projectID,array(2)); // POS LOBLAW COMPANIES
                    break;

    	    case 528: // MJN DIST Master System
                    $this->linkid = $this->connectionManager->Connect(8); // mjn
                    $varsClass = new MjnDistMasterSystem($this->aid,$this->projectID,array(6,7,8,9));
                    break;                

    	    case 525: // MJN POS Master System
                    $this->linkid = $this->connectionManager->Connect(8); // mjn
                    $varsClass = new MjnPosMasterSystem($this->aid,$this->projectID,array(2,3,4,5,12,13,14));
                    break;
                
            case 541: // Materne LCL Html
                $varsClass = new MaterneLcl($this->aid,$this->projectID);
                break;
                
            case 540: // Parmalat LCL
                $varsClass = new ParmalatLcl($this->aid,$this->projectID);
                break;

	       case 504: // UB Tesco Formats V2
                $varsClass = new UbTescoFormat($this->aid,$this->projectID);
                break;
                
            case 501: // MJN USA Nielsen
                $varsClass = new MjnUsaNielsenLcl($this->aid,$this->projectID);
                break;

            case 490: // AG Barr Morrison
                $varsClass = new AgbarrMults($this->aid,$this->projectID,array(3));
                break;

            case 489: // AG Barr Asda
                $varsClass = new AgbarrMults($this->aid,$this->projectID,array(2));
                break;

            case 487: // AG Barr Tesco
                $varsClass = new AgbarrMults($this->aid,$this->projectID,array(1));
                break;

            case 468: // Agbarr Mults I3 Html
                $varsClass = new AgbarrMults($this->aid,$this->projectID,array(1,2,3));
                break;

            case 372: // Agbarr Retail Link Html
                $varsClass = new AgbarrRetailLink($this->aid,$this->projectID);
                break;

            case 32: // AG Barr Master
                $varsClass = new AgbarrMults($this->aid,$this->projectID,array(1,2,3));
                break;
                
            case 353: // Ferrero Master Html
                $varsClass = new FerreroMasterSystem($this->aid,$this->projectID,array(1,2,3,5,6));
                break;

            case 644: // Ferrero Master Html New
                $varsClass = new FerreroMasterSystem($this->aid,$this->projectID,array(1,2,3,5,6));
                break;
                
            case 591: // Baxters Tesco Format
                $varsClass = new BaxtersTescoFormat($this->aid,$this->projectID);
                break;                

            case 595: // Baxters Tesco Format V2
                $varsClass = new BaxtersTescoFormatv2($this->aid,$this->projectID);
                break;

            case 320: // Blue Dragon LCL
                $varsClass = new BlueDragonLcl($this->aid, $this->projectID);
                break;

            case 599: // ASR Tesco Formats
                $varsClass = new AsrTescoFormat($this->aid,$this->projectID);
                break;

            case 605: // Shana Asda
                $varsClass = new ShanaMults($this->aid,$this->projectID,array(2));
                break;

            case 606: // Shana Tesco
                $varsClass = new ShanaMults($this->aid,$this->projectID,array(1));
                break;

            case 611: // Johnsonville Walmart
                $varsClass = new JohnsonvillieRetailLink($this->aid,$this->projectID);
                break;
                
            case 620: // Shana LCL Bannerview
                $varsClass = new ShanaLclBannerView($this->aid,$this->projectID);
                break;
                
            case 626: // Ferrero Canada SDM Html
                $varsClass = new FerreroCanadaMults($this->aid,$this->projectID, array(18));
                break;

            case 629: // Ferrero Canada London Drugs
                $varsClass = new FerreroCanadaMults($this->aid,$this->projectID, array(13));
                break;                

            case 630: // Ferrero Canada Walmart
                $varsClass = new FerreroCanadaMults($this->aid,$this->projectID, array(8));
                break;
                
            case 627: // Ferrero Canada LCL
                $varsClass = new FerreroCanadaMults($this->aid,$this->projectID, array(10));
                break;

            case 727: // Fcanada Nielsen
                $this->linkid = $this->connectionManager->Connect(22); // GNIELSEN
                $varsClass = new FcanadaNielsen($this->aid,$this->projectID);
                break;
            case 635: // Arla LCL Html
                $varsClass = new ArlaMults($this->aid,$this->projectID, array(10));
                break;

            case 636: // Arla Walmart Html
                $varsClass = new ArlaMults($this->aid,$this->projectID, array(8));
                break;

            case 631: // MJN USA Shipments Monthly
                $varsClass = new MjnUsaShipmentLclMonthly($this->aid,$this->projectID);
                break;

            case 532: // Jamieson LCL Html I3
                $varsClass = new JamiesonLcl($this->aid,$this->projectID);
                break;

            case 417: // Pataks LCL
                $varsClass = new PataksLcl($this->aid,$this->projectID);
                break;
            
            case 409: // Danone LCL
                $varsClass = new DanoneLcl($this->aid,$this->projectID);
                break;

            case 503: // Gaylea Walmart
                $varsClass = new GayleaRetailLink($this->aid,$this->projectID);
                break;

            case 391: // Ferrero Coop NEW
                $varsClass = new FerreroMults($this->aid,$this->projectID,array(5));
                break;
                
            case 390: // Ferrero/Thorntons Sainsburys NEW
                $varsClass = new FerreroMults($this->aid,$this->projectID,array(6));
                break;
                
            case 389: // Ferrero Morrisons NEW
                $varsClass = new FerreroMults($this->aid,$this->projectID,array(3));
                break;                
                
            case 388: // Ferrero Asda NEW
                $varsClass = new FerreroMults($this->aid,$this->projectID,array(2));
                break;                

            case 361: // Ferrero Bestway Html
                $varsClass = new FerreroBestway($this->aid,$this->projectID);
                break;
                
            case 349: // Ferrero Tesco NEW
                $varsClass = new FerreroMults($this->aid,$this->projectID,array(1));
                break;                

            case 373: // Ferrero Booker Html
                $varsClass = new FerreroBooker($this->aid,$this->projectID);
                break;

            case 499: // Standard Brands LCL Html
                $varsClass = new StbrandsLcl($this->aid,$this->projectID);
                break;

            case 378: // Pinnacle LCL
                $varsClass = new PinnacleLcl($this->aid,$this->projectID);
                break;

            case 375: // Johnsonville LCL New 
                $varsClass = new JohnsonvillieLcl($this->aid,$this->projectID);
                break;
                
            case 480: // Shana Retail Link Html
                $varsClass = new ShanaRetailLink($this->aid,$this->projectID);
                break;                
                
            case 348: // Rubicon LCL I3
                $varsClass = new RubiconLcl($this->aid,$this->projectID);
                break;                

            case 478: // Rubicon Retail Link Html
                $varsClass = new RubiconRetailLink($this->aid,$this->projectID);
                break;

            case 345: // Mother Parkers LCL I3 Html
                $varsClass = new MotherParkersLcl($this->aid,$this->projectID);
                break;

			case 459: // IDFoods LCL Html
				$varsClass = new IdFoodsLcl($this->aid,$this->projectID);
				break;

            case 320: // Blue Dragon LCL
                $varsClass = new BlueDragonLcl($this->aid, $this->projectID);
                break;

            case 316: // Mother Parkers LCL Brand Only I3
                $varsClass = new MotherParkersBrandLcl($this->aid,$this->projectID);
                break;

            case 306: // Gaylea LCL
                $varsClass = new GayleaLcl($this->aid,$this->projectID);
                break;
                
			case 385: // London Drugs I3
                $varsClass = new fcanadaLD($this->aid,$this->projectID);
                break;

            case 668: //Ferrero Nielsen Dashboard
                $varsClass = new FerreroNielsenLcl($this->aid,$this->projectID,array(11));
                break;
                
            case 671: // Parmalat LCL Competitor
                $varsClass = new ParmalatLclCompetitor($this->aid,$this->projectID);
                break;

            case 674: // MJN USA POS - BRU
                $varsClass = new MjnUsaPosBru($this->aid,$this->projectID, array(25));
                break;

            
               
            /* ---------------- Relay Plus Client Class Configuration Start ---------------- */

            case 439: // Ferrero Relay Plus
                if ($_REQUEST['SIF'] == "YES") {
                    $varsClass = new commonRetailLinkDaily(array_merge($commonParams, array('clientID' => "FERRERO")));
                    $varsClass->setCluster();
                } else {
                   $varsClass = new commonMultsSummary(array_merge($commonParams, array('clientID' => "FERRERO", 'footerCompanyName' => "Ferrero UK")));
                }
                break;

            case 645: // Ferrero Relay Plus New
                if ($_REQUEST['SIF'] == "YES") {
                    //$varsClass = new FerreroRetailLinkDaily($this->aid, $this->projectID);
                    $varsClass = new commonRetailLinkDaily(array_merge($commonParams, array('clientID' => "FERRERO")));
                    $varsClass->setCluster();
                } else {
                   //$varsClass = new FerreroMultsSummary($this->aid, $this->uid, $this->projectID);
                   $varsClass = new commonMultsSummary(array_merge($commonParams, array('clientID' => "FERRERO", 'footerCompanyName' => "Ferrero UK")));
                }
                break;                
                
            case 403: // Shana Relay Plus
                if ($_REQUEST['SIF'] == "YES") {
                    $varsClass = new commonRetailLinkDaily(array_merge($commonParams, array('clientID' => "SHANA", "InstockSummaryStoreListDLCol" => true, 'myStockByProductStockedStoresCol' => true)));
                    $varsClass->setCluster();
                } else {
                   $varsClass = new commonMultsSummary(array_merge($commonParams, array('clientID' => "SHANA", 'footerCompanyName' => "Shana Foods",'isSifPageRequired' => false)));
                }
                break;

            case 657: // Ferrero Morissons Summary
                if ($this->aid == 3) { // FERRERO
                    $varsClass = new FerreroMorissonsSummary($this->aid, $this->uid, $this->projectID);
                }                
                break;

            case 511: // Ferrero Canada Relay Plus
                if ($_REQUEST['SIF'] == "YES") {
                    $varsClass = new commonRetailLinkDaily(array_merge($commonParams, array('clientID' => "FCANADA", 'gId' => 8)));
                    $varsClass->setCluster();
                } else {
                    $varsClass = new commonMultsSummary(array_merge($commonParams, array('clientID' => "FCANADA", 'footerCompanyName' => "Ferrero Canada", 'clientLogo' => $this->aid.".png", 'gId' => 8)));
                }
                break;

            case 661: // Lindt Relay Plus
                if ($_REQUEST['SIF'] == "YES") {
                    $varsClass = new commonRetailLinkDaily(array_merge($commonParams,array('clientID' => "LINDT")));
                    $varsClass->setCluster();
                } else {
                   $varsClass = new commonMultsSummary(array_merge($commonParams, array('clientID' => "LINDT", 'footerCompanyName' => "LINDT")));
                }
                break;

            case 613: // Johnsonville Relay Plus
                if ($_REQUEST['SIF'] == "YES") {
                    $varsClass = new commonRetailLinkDaily(array_merge($commonParams, array('clientID'  => "JV", 'maintable' => "johnsonville_retail_link_daily_14", 'gId' => 8)));
                    $varsClass->setCluster();
                } else {
                    $varsClass = new commonMultsSummary(array_merge($commonParams, array('clientID' => "JV", 'maintable' => "johnsonville_mults_summary", 'footerCompanyName' => "Johnsonville", 'clientLogo' => $this->aid.".png", 'gId' => 8)));
                }
                break;

            case 596: // PANDG Relay Plus
                if ($_REQUEST['SIF'] == "YES") {
                    $varsClass = new commonRetailLinkDaily(array_merge($commonParams,array('clientID' => "PANDG")));
                    $varsClass->setCluster();
                } else {
                    $varsClass = new commonMultsSummary(array_merge($commonParams, array('clientID' => "PANDG", 'footerCompanyName' => "P&G", 'clientLogo' => $this->aid.".png")));
                }
                break;

            case 574: // Dare Relay Plus
                if ($_REQUEST['SIF'] == "YES") {
                    $varsClass = new commonRetailLinkDaily(array_merge($commonParams, array('clientID' => "DARE", 'maintable'=> "Dare_retail_link_daily_14", 'gId' => 8)));
                    $varsClass->setCluster();
                } else {
                   $varsClass = new commonMultsSummary(array_merge($commonParams, array('clientID' => "DARE", 'footerCompanyName' => "Dare",'clientLogo' => $this->aid.".jpg", 'gId' => 8)));
                }
                break;

            case 554: // Red Bull Relay Plus
                if ($_REQUEST['SIF'] == "YES") {
                    $varsClass = new commonRetailLinkDaily(array_merge($commonParams, array('clientID' => "REDBULL", 'isretaillinkdctable'=> true)));
                    $varsClass->setCluster();
                } else {
                    $varsClass = new commonMultsSummary(array_merge($commonParams, array('clientID' => "REDBULL", 'footerCompanyName' => "RED BULL")));
                }
                break;

            case 550: // Quorn Relay Plus
                if ($_REQUEST['SIF'] == "YES") {
                    $varsClass = new commonRetailLinkDaily(array_merge($commonParams, array('clientID' => "QUORN")));
                    $varsClass->setCluster();
                } else {
                    $varsClass = new commonMultsSummary(array_merge($commonParams, array('clientID' => "QUORN", 'footerCompanyName' => "QUORN")));
                }
                break;

            case 507: // Gaylea Relay Plus
                if ($_REQUEST['SIF'] == "YES") {
                    $varsClass = new commonRetailLinkDaily(array_merge($commonParams,array('clientID' => "GAYLEA", 'gId' => 8)));
                    $varsClass->setCluster();
                } else {
                    $varsClass = new commonMultsSummary(array_merge($commonParams, array('clientID' => "GAYLEA", 'footerCompanyName' => "Gaylea", 'gId' => 8)));
                }
                break;

            case 444: // Indigo Yin Relay Plus
                if ($_REQUEST['SIF'] == "YES") {
                    $varsClass = new commonRetailLinkDaily(array_merge($commonParams, array('clientID' => "INDIGO", 'gId' => 8, 'maintable' => "indigoyin_retail_link_daily_14")));
                    $varsClass->setCluster();
                } else {
                    $varsClass = new commonMultsSummary(array_merge($commonParams, array('clientID' => "INDIGO", 'maintable' => "indigoyin_mults_summary", 'footerCompanyName' => "Indigo Yin", 'gId' => 8)));
                }
                break;

            case 443: // MP Relay Plus
                if ($_REQUEST['SIF'] == "YES") {
                    $varsClass = new commonRetailLinkDaily(array_merge($commonParams,array('clientID' => "MP", 'gId' => 8)));
                    $varsClass->setCluster();
                } else {
                    $varsClass = new commonMultsSummary(array_merge($commonParams, array('clientID' => "MP", 'footerCompanyName' => "Mother  Parker", 'clientLogo' => $this->aid.".png", 'gId' => 8)));
                }
                break;

            case 442: // Arla Relay Plus
                if ($_REQUEST['SIF'] == "YES") {
                    $varsClass = new commonRetailLinkDaily(array_merge($commonParams,array('clientID' => "ARLA", 'gId' => 8)));
                    $varsClass->setCluster();
                } else {
                   $varsClass = new commonMultsSummary(array_merge($commonParams, array('clientID' => "ARLA", 'footerCompanyName' => "Arla", 'gId' => 8)));
                }
                break;

            case 440: // Rubicon Relay Plus
                if ($_REQUEST['SIF'] == "YES") {
                    $varsClass = new commonRetailLinkDaily(array_merge($commonParams,array('clientID' => "RUBICON", 'gId' => 8)));
                    $varsClass->setCluster();
                } else {
                   $varsClass = new commonMultsSummary(array_merge($commonParams, array('clientID' => "RUBICON", 'footerCompanyName' => "Rubicon",  'gId' => 8)));
                }
                break;

            case 438: // Agbarr Relay Plus
                if ($_REQUEST['SIF'] == "YES") {
                    $varsClass = new commonRetailLinkDaily(array_merge($commonParams,array('clientID' => "AGBARR")));
                    $varsClass->setCluster();
                } else {
                   $varsClass = new commonMultsSummary(array_merge($commonParams, array('clientID' => "AGBARR", 'footerCompanyName' => "AG Barr")));
                }
                break;

            case 437: // Hartz Relay Plus
                if ($_REQUEST['SIF'] == "YES") {
                    $varsClass = new commonRetailLinkDaily(array_merge($commonParams,array('clientID' => "HARTZ", 'gId' => 8, 'forceUseOriginalLink' => true)));
                    $varsClass->setCluster();
                } else {
                   $varsClass = new commonMultsSummary(array_merge($commonParams, array('clientID' => "HARTZ", 'footerCompanyName' => "Hartz", 'gId' => 8, 'forceUseOriginalLink' => true)));
                }
                break;

            case 426: // Blue Dragon Relay Plus => AB World Foods 
                if ($_REQUEST['SIF'] == "YES") {
                    $varsClass = new commonRetailLinkDaily(array_merge($commonParams,array('clientID' => "BLUEDRAGON", 'gId' => 8, 'forceUseOriginalLink' => true)));
                    $varsClass->setCluster();
                } else {
                   $varsClass = new commonMultsSummary(array_merge($commonParams, array('clientID' => "BLUEDRAGON", 'footerCompanyName' => "Blue Dragon", 'gId' => 8, 'forceUseOriginalLink' => true)));
                }
                break;

            case 425: // Pataks Relay Plus => AB World Foods
                if ($_REQUEST['SIF'] == "YES") {
                    $varsClass = new commonRetailLinkDaily(array_merge($commonParams,array('clientID' => "PATAKS", 'gId' => 8, 'forceUseOriginalLink' => true)));
                    $varsClass->setCluster();
                } else {
                   $varsClass = new commonMultsSummary(array_merge($commonParams, array('clientID' => "PATAKS", 'footerCompanyName' => "Pataks",  'gId' => 8, 'forceUseOriginalLink' => true)));
                }
                break;

            case 366: // UB Relay Plus
                if ($_REQUEST['SIF'] == "YES") {
                    $varsClass = new commonRetailLinkDaily(array_merge($commonParams,array('clientID' => "UB")));
                    $varsClass->setCluster();
                } else {
                   $varsClass = new commonMultsSummary(array_merge($commonParams, array('clientID' => "UB",'footerCompanyName' => "United Biscuits UK", 'clientLogo' => $this->aid.".png")));
                }
                break;

            /* ---------------- Relay Plus Client Class Configuration End ---------------- */

            /* ---------------- Sales Builder Client Class Configuration Start ---------------- */

            case 381: // Ferrero Sales Builder OLD
                $varsClass = new FerreroSalesBuilder($this->aid,$this->projectID,array(1,2,3,4,5));
                break;

            case 462: // Agbarr Sales Builder
                $varsClass = new AgbarrSalesBuilder($this->aid,$this->projectID,array(1,2,3));
                break;

            case 497: // Ferrero Sales Builder
                $varsClass = new FerreroSalesBuilder($this->aid,$this->projectID,array(1,2,3,4,5));
                break;

            case 580: // Gaylea Sales Builder
                $varsClass = new GayleaLclSalesBuilder($this->aid,$this->projectID,array(10));
                break;

            case 597: // PANDG Sales Builders
                $varsClass = new PandgSalesBuilder($this->aid,$this->projectID,array(1,2,3));
                break;

            case 649: //    Ferrero Bestway Sales Builder
                $varsClass = new FerreroBestwaySalesBuilder($this->aid,$this->projectID,array(12));
                break;            
                
            case 650: //    Ferrero Booker Sales Builder
                $varsClass = new FerreroBookerSalesBuilder($this->aid,$this->projectID,array(14));
                break;
            
            /* ---------------- Sales Builder Client Class Configuration End ---------------- */

            /* ---------------- TSD Client Class Configuration Start ---------------- */

            case 482: // Ferrero Tesco Store Daily Project
                if ($_REQUEST['SIF'] == "YES") {
                    $varsClass = new FerreroTsdDaily($this->aid,$this->projectID);
                    $varsClass->setCluster();
                } else {
                    $varsClass = new commonMultsSummary(array_merge($commonParams, array('clientID' => "FERRERO", 'footerCompanyName' => "Ferrero UK", "gId" => 1)));
                }
                break;

            case 646: // Ferrero Tesco Store Daily Project New
                if ($_REQUEST['SIF'] == "YES") {
                    $varsClass = new FerreroTsdDaily($this->aid,$this->projectID);
                    $varsClass->setCluster();
                } else {
                    $varsClass = new commonMultsSummary(array_merge($commonParams, array('clientID' => "FERRERO", 'footerCompanyName' => "Ferrero UK", "gId" => 1)));
                }
                break;

            case 658: // Red Bull Tesco Store Daily
                if ($_REQUEST['SIF'] == "YES") {
                    $varsClass = new commonTescoStoreDaily(array_merge($commonParams,array('clientID' => "REDBULL")));
                    $varsClass->setCluster();
                } else {
                    $varsClass = new commonMultsSummary(array_merge($commonParams, array('clientID' => "REDBULL", 'footerCompanyName' => "Red Bull", "gId" => 1)));
                }
                break;

            case 609: // RB Tesco Store Daily
                if ($_REQUEST['SIF'] == "YES") {
                    $varsClass = new commonTescoStoreDaily(array_merge($commonParams,array('clientID' => "RB")));
                    $varsClass->setCluster();
                } else {
                    $varsClass = new RbTsdMultsSummary($this->aid,$this->uid,$this->projectID);
                }
                break;

            case 594: // ASR Tesco Store Daily
                if ($_REQUEST['SIF'] == "YES") {
                    $varsClass = new commonTescoStoreDaily(array_merge($commonParams,array('clientID' => "ASRGROUP", "maintable" => "asr_tesco_store_daily_14", "tesco_depot_daily" => "asr_tesco_depot_daily", "rangedtable" => "asr_tesco_ranged", "isRangedtable" => true)));
                    $varsClass->setCluster();
                } else {
                    $varsClass = new commonMultsSummary(array_merge($commonParams, array('footerCompanyName' => "Asr Group", "gId" => 1, "maintable" => "asr_mults_summary", "clientID" => "ASRGROUP", "clientLogo" => $this->aid.".png")));
                }
                break;

            case 588: // Baxters TSD
                if ($_REQUEST['SIF'] == "YES") {
                    $varsClass = new commonTescoStoreDaily(array_merge($commonParams,array('clientID' => "BAXTERS", "isRangedtable" => true)));
                    $varsClass->setCluster();
                } else {
                    $varsClass = new commonMultsSummary(array_merge($commonParams, array('clientID' => "BAXTERS", 'footerCompanyName' => "Baxters", "gId" => 1)));
                }
                break;

            case 546: // Quorn Tesco Store Daily
                if ($_REQUEST['SIF'] == "YES") {
                    $varsClass = new commonTescoStoreDaily(array_merge($commonParams,array('clientID' => "QUORN", "isRangedtable" => true)));
                    $varsClass->setCluster();
                } else {
                    $varsClass = new commonMultsSummary(array_merge($commonParams, array('clientID' => "QUORN", 'footerCompanyName' => "QUORN", "gId" => 1)));
                }
                break;

            case 493: // Agbarr Tesco Store Daily V2
                if ($_REQUEST['SIF'] == "YES") {
                    $varsClass = new commonTescoStoreDaily(array_merge($commonParams,array('clientID' => "AGBARR", "isRangedtable" => true)));
                    $varsClass->setCluster();
                } else {
                    $varsClass = new commonMultsSummary(array_merge($commonParams, array('clientID' => "AGBARR", 'footerCompanyName' => "Agbarr", "gId" => 1)));
                }
                break;

            case 407: // Shana TSD
                if ($_REQUEST['SIF'] == "YES") {
                    $varsClass = new commonTescoStoreDaily(array_merge($commonParams,array('clientID' => "SHANA", "isRangedtable" => true)));
                    $varsClass->setCluster();
                } else {
                    $varsClass = new commonMultsSummary(array_merge($commonParams, array('clientID' => "SHANA", 'footerCompanyName' => "Shana Foods", "gId" => 1)));
                }
                break;

            case 518: // DDB
                    //Dynamic data builder Link
                    $this->ddbLinkid = $this->connectionManager->Connect(13);
                    $this->linkid = $this->connectionManager->Connect(9); // canadalcl
                    $varsClass = new ArlaMults($this->aid,$this->projectID, array(10));
                    //$varsClass = new ArlaLcl($this->aid,$this->projectID);
                    break;
            case 764: // LCL Dynamic Data Builder Test
                    $varsClass = new ArlaMultsTest($this->aid,$this->projectID);
                    break;

            /* ---------------- TSD Client Class Configuration End ---------------- */

            /* ------------------------------------------------------------------------------------------------------------ */
                
            /* case 220:
                $this->linkid = $this->connectionManager->Connect(1);
                $varsClass = new FerreroRetailLink($this->aid,$this->projectID);
                break; */

            /*case 293:
                $this->linkid = $this->connectionManager->Connect(1);
                $varsClass = new AgbarrRetailLink($this->aid,$this->projectID);
                break;*/

            /* case 348:
                $this->linkid = $this->connectionManager->Connect(9);
                $varsClass = new RubiconLcl($this->aid,$this->projectID);
                break;

            case 499:
                $this->linkid = $this->connectionManager->Connect(9);
                $varsClass = new StbrandsLcl($this->aid,$this->projectID);
                break;

            case 341:
                $this->linkid = $this->connectionManager->Connect(9);
                $varsClass = new ArlaLcl($this->aid,$this->projectID);
                break;
            
            case 361:
                $this->linkid = $this->connectionManager->Connect(9);
                $varsClass = new FerreroBestway($this->aid,$this->projectID);
                break;

            case 306:
                $this->linkid = $this->connectionManager->Connect(9);
                $varsClass = new GayleaLcl($this->aid,$this->projectID);
                break;

            case 16:
                $this->linkid = $this->connectionManager->Connect(9);
                $varsClass = new IdfoodsLcl($this->aid,$this->projectID);
                break;

            case 324:
                $this->linkid = $this->connectionManager->Connect(9);
                $varsClass = new EarthsownLcl($this->aid,$this->projectID);
                break;
            
            case 329:
                $this->linkid = $this->connectionManager->Connect(9);
                $varsClass = new EarthsownEOLcl($this->aid,$this->projectID);
                break;
            
            case 330:
                $this->linkid = $this->connectionManager->Connect(9);
                $varsClass = new EarthsownChilledEOLcl($this->aid,$this->projectID);
                break;

            case 284:
                $this->linkid = $this->connectionManager->Connect(9);
                $varsClass = new JamiesonLcl($this->aid,$this->projectID);
                break;

            case 305:
                $this->linkid = $this->connectionManager->Connect(9);
                $varsClass = new FerreroCanadaLcl($this->aid,$this->projectID);
                break;

            case 285:
                $this->linkid = $this->connectionManager->Connect(9);
                $varsClass = new JohnsonvillieLcl($this->aid,$this->projectID);
                break;

            case 345:
                $this->linkid = $this->connectionManager->Connect(9);
                $varsClass = new MotherParkersLcl($this->aid,$this->projectID);
                break;

            case 310:
                $this->linkid = $this->connectionManager->Connect(1);
                $varsClass = new FerreroTotalMults($this->aid,$this->projectID);
                break;
                
            case 312:
                $this->linkid = $this->connectionManager->Connect(1);
                $varsClass = new FerreroSainsburysDaily($this->aid,$this->projectID);
                break;

            case 313:
                $this->linkid = $this->connectionManager->Connect(1);
                $varsClass = new ShanaTotalMults($this->aid,$this->projectID);
                break;
                
            case 314:
                $this->linkid = $this->connectionManager->Connect(1);
                $varsClass = new ShanaSainsburysDaily($this->aid,$this->projectID);
                break;

            case 316:
                $this->linkid = $this->connectionManager->Connect(9);
                $varsClass = new MotherParkersBrandLcl($this->aid,$this->projectID);
                break;

            case 320:
                $this->linkid = $this->connectionManager->Connect(9);
                $varsClass = new BlueDragonLcl($this->aid, $this->projectID);
                break;

            case 334:
                $this->linkid = $this->connectionManager->Connect(1);
                $varsClass = new UbCakes($this->aid,$this->projectID);
                break;

			case 343:
                $this->linkid = $this->connectionManager->Connect(1);
                $varsClass = new UbTescoFormat($this->aid,$this->projectID);
                break;				
				
            case 336:
                $this->linkid = $this->connectionManager->Connect(1);
                $varsClass = new GayleaGt($this->aid,$this->projectID);
                break;

            case 349: //70
                $this->linkid = $this->connectionManager->Connect(9);
                $varsClass = new FerreroMults($this->aid,$this->projectID,array(1));
                break;

            case 388: //70
                $this->linkid = $this->connectionManager->Connect(9);
                $varsClass = new FerreroMults($this->aid,$this->projectID,array(2));
                break;

            case 389: //70
                $this->linkid = $this->connectionManager->Connect(9);
                $varsClass = new FerreroMults($this->aid,$this->projectID,array(3));
                break;

            case 390: //70
                $this->linkid = $this->connectionManager->Connect(9);
                $varsClass = new FerreroMults($this->aid,$this->projectID,array(6));
                break; 

            case 391: //70
                $this->linkid = $this->connectionManager->Connect(9);
                $varsClass = new FerreroMults($this->aid,$this->projectID,array(5));
                break;
				
			case 353:
                $this->linkid = $this->connectionManager->Connect(9);
                $varsClass = new FerreroMasterSystem($this->aid,$this->projectID,array(1,2,3,5,6));
                break;

            case 368:
                $this->linkid = $this->connectionManager->Connect(9);
                $varsClass = new FerreroNielsen($this->aid, $this->projectID, array(11));
                break;

            case 370:
                $this->linkid = $this->connectionManager->Connect(9);
                $varsClass = new MjnUsaNielsen($this->aid, $this->projectID, array(11));
                break;
			
            case 364: // THIS IS OLD ONE. Please refer new one PID: 627
                $this->linkid = $this->connectionManager->Connect(9);
                $varsClass = new FcanadaLcl($this->aid,$this->projectID);
                break;			

            case 40:
                $this->linkid = $this->connectionManager->Connect(9);
                $varsClass = new ferreroRetailLink($this->aid,$this->projectID);
                break;

            case 373:
                $this->linkid = $this->connectionManager->Connect(9);
                $varsClass = new FerreroBooker($this->aid,$this->projectID);
                break;
            case 375:
                $this->linkid = $this->connectionManager->Connect(9);
                $varsClass = new JohnsonvillieLcl($this->aid,$this->projectID);
                break;
            case 378:
                $this->linkid = $this->connectionManager->Connect(9);
                $varsClass = new PinnacleLcl($this->aid,$this->projectID);
                break;
            case 381:
                $this->linkid = $this->connectionManager->Connect(9);
                $varsClass = new FerreroSalesBuilder($this->aid,$this->projectID,array(1,2,3,4,5));
                break;
			case 385:
                $this->linkid = $this->connectionManager->Connect(9);
                $varsClass = new fcanadaLD($this->aid,$this->projectID);
                break;
            case 379:
                $this->linkid = $this->connectionManager->Connect(9);
                $varsClass = new SuperDrug($this->aid,$this->projectID);
                break;
			case 399:
				$this->linkid = $this->connectionManager->Connect(9);
				$varsClass = new tescoRetailer($this->aid, $this->projectID);
				break;
            case 400:
                $this->linkid = $this->connectionManager->Connect(9);
                $varsClass = new coopRetailer($this->aid, $this->projectID);
                break;
            case 403:
                $this->linkid = $this->connectionManager->Connect(9);
                if ($this->aid == 31){ // SHANA
                    if ($_REQUEST['SIF'] == "YES") {
                        $varsClass = new ShanaRetailLinkDaily($this->aid, $this->projectID);
                        $varsClass->setCluster();
                    } else {
                       $varsClass = new ShanaMultsSummary($this->aid, $this->uid, $this->projectID);
                    }
                }
                break;			 */
            /*case 403:
				$this->linkid = $this->connectionManager->Connect(9);
				if ($this->aid == 31) // SHANA
					$varsClass = new shanaMultsSummary($this->aid, $this->uid, $this->projectID);
				break;*/
			/* case 366:
				$this->linkid = $this->connectionManager->Connect(9);
                if ($this->aid == 62){ // SHANA
                    if ($_REQUEST['SIF'] == "YES") {
                        $varsClass = new UbRetailLinkDaily($this->aid, $this->projectID);
                        $varsClass->setCluster();
                    } else {
                       $varsClass = new UbMultsSummary($this->aid, $this->uid, $this->projectID);
                    }
                }
				break;
			case 425:
				$this->linkid = $this->connectionManager->Connect(9);
                if ($this->aid == 118){ // AB World Foods
                    if ($_REQUEST['SIF'] == "YES") {
                        $varsClass = new PataksRetailLinkDaily($this->aid, $this->projectID);
                        $varsClass->setCluster();
                    } else {
                       $varsClass = new PataksMultsSummary($this->aid, $this->uid, $this->projectID);
                    }
                }
				break;	
            case 426:
                $this->linkid = $this->connectionManager->Connect(9);
                if ($this->aid == 118){ // AB World Foods
                    if ($_REQUEST['SIF'] == "YES") {
                        $varsClass = new blueDragonRetailLinkDaily($this->aid, $this->projectID);
                        $varsClass->setCluster();
                    } else {
                       $varsClass = new blueDragonMultsSummary($this->aid, $this->uid, $this->projectID);
                    }
                }
                break;                			                
            case 437:
                $this->linkid = $this->connectionManager->Connect(9);
                if ($this->aid == 117){ // HARTZ
                    if ($_REQUEST['SIF'] == "YES") {
                        $varsClass = new HartzRetailLinkDaily($this->aid, $this->projectID);
                        $varsClass->setCluster();
                    } else {
                       $varsClass = new HartzMultsSummary($this->aid, $this->uid, $this->projectID);
                    }
                }
                break;                                                          
			case 407:
				$this->linkid = $this->connectionManager->Connect(9);
				if ($this->aid == 31) // SHANA
					$varsClass = new shanaTSD($this->aid, $this->uid, $this->projectID);
				break;				
            case 438:
                $this->linkid = $this->connectionManager->Connect(9);
                if ($this->aid == 27){ // AGBARR
                    if ($_REQUEST['SIF'] == "YES") {
                        $varsClass = new AgbarrRetailLinkDaily($this->aid, $this->projectID);
                        $varsClass->setCluster();
                    } else {
                       $varsClass = new AgbarrMultsSummary($this->aid, $this->uid, $this->projectID);
                    }
                }
                break;
            
            case 440:    
                $this->linkid = $this->connectionManager->Connect(9);
                if ($this->aid == 31){ // RUBICON
                    if ($_REQUEST['SIF'] == "YES") {
                        $varsClass = new RubiconRetailLinkDaily($this->aid, $this->projectID);
                        $varsClass->setCluster();
                    } else {
                       $varsClass = new RubiconMultsSummary($this->aid, $this->uid, $this->projectID);
                    }
                }
                break;    */
            /*case 441:    
                $this->linkid = $this->connectionManager->Connect(9);
                if ($this->aid == 31){ // ASDA
                    if ($_REQUEST['SIF'] == "YES") {
                        $varsClass = new AsdaRetailLinkDaily($this->aid, $this->projectID);
                        $varsClass->setCluster();
                    } else {
                       $varsClass = new AsdaMultsSummary($this->aid, $this->uid, $this->projectID);
                    }
                }
                break;                   */
            /* case 442:    
                $this->linkid = $this->connectionManager->Connect(9);
                if ($this->aid == 52){ // ARLA
                    if ($_REQUEST['SIF'] == "YES") {
                        $varsClass = new ArlaRetailLinkDaily($this->aid, $this->projectID);
                        $varsClass->setCluster();
                    } else {
                       $varsClass = new ArlaMultsSummary($this->aid, $this->uid, $this->projectID);
                    }
                }
                break;
            case 443:    
                $this->linkid = $this->connectionManager->Connect(9);
                if ($this->aid == 97){ // MP
                    if ($_REQUEST['SIF'] == "YES") {
                        $varsClass = new MpRetailLinkDaily($this->aid, $this->projectID);
                        $varsClass->setCluster();
                    } else {
                       $varsClass = new MpMultsSummary($this->aid, $this->uid, $this->projectID);
                    }
                }
                break;                
            case 444:    
                $this->linkid = $this->connectionManager->Connect(9);
                if ($this->aid == 102){ // Indigo Yin
                    if ($_REQUEST['SIF'] == "YES") {
                        $varsClass = new IndigoRetailLinkDaily($this->aid, $this->projectID);
                        $varsClass->setCluster();
                    } else {
                       $varsClass = new IndigoMultsSummary($this->aid, $this->uid, $this->projectID);
                    }
                }
                break;
            case 409:
                $this->linkid = $this->connectionManager->Connect(9);
                $varsClass = new DanoneLcl($this->aid,$this->projectID);
                break;
            
            case 396:
                $this->linkid = $this->connectionManager->Connect(9);
                $varsClass = new SunrypeLcl($this->aid,$this->projectID);
                break;
            case 393:
                $this->linkid = $this->connectionManager->Connect(9);
                $varsClass = new mjnUsaLcl($this->aid,$this->projectID);
                break;
            
            case 417:
                $this->linkid = $this->connectionManager->Connect(9);
                $varsClass = new PataksLcl($this->aid,$this->projectID);
                break;
			case 459:
				$this->linkid = $this->connectionManager->Connect(9);
				$varsClass = new IdFoodsLcl($this->aid,$this->projectID);
				break;
			case 463:
				$this->linkid = $this->connectionManager->Connect(9);
				$varsClass = new UbTsdDaily($this->aid,$this->projectID);
                $varsClass->setCluster();
				break;    
			 case 147:
				$this->linkid = $this->connectionManager->Connect(9);
				if ($_REQUEST['SIF'] == "YES") {
                    $varsClass = new FerreroTsdDaily($this->aid,$this->projectID);
                    $varsClass->setCluster();
                } else {
                    $varsClass = new FerreroTsdMultsSummary($this->aid,$this->uid,$this->projectID);
                }
				break;                    
            case 462:
                $this->linkid = $this->connectionManager->Connect(9);
                $varsClass = new AgbarrSalesBuilder($this->aid,$this->projectID,array(1,2,3));
                break;

            case 478:
                $this->linkid = $this->connectionManager->Connect(9);
                $varsClass = new RubiconRetailLink($this->aid,$this->projectID);
                break;                
            case 480:
                $this->linkid = $this->connectionManager->Connect(9);
                $varsClass = new ShanaRetailLink($this->aid,$this->projectID);
                break;                                

			case 493:
				$this->linkid = $this->connectionManager->Connect(9);
				if ($_REQUEST['SIF'] == "YES") {
                    $varsClass = new AgbarrTsdDaily($this->aid,$this->projectID);
                    $varsClass->setCluster();
                } else {
                    $varsClass = new AgbarrTsdMultsSummary($this->aid,$this->uid,$this->projectID);
                }
				break;  

            case 503:
                $this->linkid = $this->connectionManager->Connect(9);
                $varsClass = new GayleaRetailLink($this->aid,$this->projectID);
                break;

            case 507:    
                $this->linkid = $this->connectionManager->Connect(9);
                if ($this->aid == 69){
                    if ($_REQUEST['SIF'] == "YES") {
                        $varsClass = new GayleaRetailLinkDaily($this->aid, $this->projectID);
                        $varsClass->setCluster();
                    } else {
                       $varsClass = new GayleaMultsSummary($this->aid, $this->uid, $this->projectID);
                    }
                }
                break;                
            case 511:    
                $this->linkid = $this->connectionManager->Connect(9);
                if ($this->aid == 28){
                    if ($_REQUEST['SIF'] == "YES") {
                        $varsClass = new FcanadaRetailLinkDaily($this->aid, $this->projectID);
                        $varsClass->setCluster();
                    } else {
                       $varsClass = new FcanadaMultsSummary($this->aid, $this->uid, $this->projectID);
                    }
                }
                break;                
            case 509:
                $this->linkid = $this->connectionManager->Connect(9);
                $varsClass = new FcanadaRetailLink($this->aid,$this->projectID);
                break;                
                
            case 574:
                $this->linkid = $this->connectionManager->Connect(9);
                if ($this->aid == 123) { // DARE
                    if ($_REQUEST['SIF'] == "YES") {
                        $varsClass = new DareRetailLinkDaily($this->aid, $this->projectID);
                        $varsClass->setCluster();
                    } else {
                       $varsClass = new DareMultsSummary($this->aid, $this->uid, $this->projectID);
                    }
                }
                break; 
            default :
                exit(json_encode(array('access' => 'unothorized')));
                break;*/
        }

        if ($this->devServer == "true") {
            switch ($this->projectID) {
                case 341:
                     //$varsClass = new ArlaLcl($this->aid,$this->projectID);
                     $varsClass = new ArlaMults($this->aid,$this->projectID, array(10));
                    break;
                case 760:
                    $varsClass = new ArlaMultsTest($this->aid,$this->projectID);
                    break;
                case 718:
                     //$varsClass = new ArlaLcl($this->aid,$this->projectID);
                     $varsClass = new ArlaMults($this->aid,$this->projectID, array(10));
                    break;
                case 529:
                    $varsClass = new LCLPlanoAudit($this->aid,$this->projectID);
                    break;                    
                case 524: //Ferrero Nielsen Dashboard
                    $varsClass = new FerreroNielsenLcl($this->aid,$this->projectID,array(11));
                    break;
                case 403:
                    if ($this->aid == 31){ // SHANA
                        $this->linkid = $this->connectionManagerLIVE->Connect(9); // CANADALCL
                        if ($_REQUEST['SIF'] == "YES") {
                            $varsClass = new commonRetailLinkDaily(array_merge($commonParams,array('clientID' => "SHANA", "InstockSummaryStoreListDLCol" => true, 'myStockByProductStockedStoresCol' => true)));
                            $varsClass->setCluster();
                        } else {
                            $varsClass = new commonMultsSummary(array_merge($commonParams, array('clientID' => "SHANA", 'footerCompanyName' => "Shana Foods",'isSifPageRequired' => true)));
                        }
                    }
                    break;
                case 376: // Rubicon Relay Plus
                    if ($_REQUEST['SIF'] == "YES") {
                        $varsClass = new commonRetailLinkDaily(array_merge($commonParams,array('clientID' => "RUBICON", 'gId' => 8)));
                        $varsClass->setCluster();
                    } else {
                       $varsClass = new commonMultsSummary(array_merge($commonParams, array('clientID' => "RUBICON", 'footerCompanyName' => "Rubicon",  'gId' => 8)));
                    }
                    break;
                case 147: // Ferrero Tesco Daily Report
                    $this->linkid = $this->connectionManagerLIVE->Connect(9); // CANADALCL
                    if ($_REQUEST['SIF'] == "YES") {
                        $varsClass = new commonTescoStoreDaily(array_merge($commonParams,array('clientID' => "FERRERO")));
                        $varsClass->setCluster();
                    } else {
                        // $varsClass = new FerreroTsdMultsSummary($this->aid,$this->uid,$this->projectID);
                        $varsClass = new commonMultsSummary(array_merge($commonParams, array('clientID' => "FERRERO", 'footerCompanyName' => "Ferrero UK", "gId" => 1,'isSifPageRequired' => true)));
                    }
                    break;
                case 515:
                    $varsClass = new MjnUsaShipmentLclMonthly($this->aid,$this->projectID);
                    break;
                case 381: // ferrero sales builder
                    //$varsClass = new FerreroSalesBuilder($this->aid,$this->projectID,array(1,2,3,4,5));
                    $varsClass = new FerreroBestwaySalesBuilder($this->aid,$this->projectID,array(12));
                    break;
                case 518: // DDB
                    //Dynamic data builder Link
                    $this->ddbLinkid = $this->connectionManager->Connect(13);
                    $this->linkid = $this->connectionManagerLIVE->Connect(9); // canadalcl
                    $varsClass = new ArlaMults($this->aid,$this->projectID, array(10));
                    // $varsClass = new ArlaLcl($this->aid,$this->projectID);
                    break;
                case 535: // Agbarr Asda Online Relay Plus
                       $varsClass = new AgbarrAsdaOnline($this->aid, $this->uid, $this->projectID);
                    break;
                /*case 724: // // Ferrero Relay Plus 
                    if ($_REQUEST['SIF'] == "YES") {
                            $varsClass = new commonRetailLinkDaily(array_merge($commonParams, array('clientID' => "FERRERO"))); 
                            $varsClass->setCluster();
                        } else {
                            $varsClass = new commonMultsSummary(array_merge($commonParams, array('clientID' => "FERRERO", 'footerCompanyName' => "Ferrero UK", "extraVars" => array("ferreroAsdaOnlineTable" => "ferrero_asda_online", "multsSummaryAsdaOnlinePeriodTable" => "mults_summary_asda_online_period_list", "measureTypeField" => "ferrero_asda_online.measure_type")))); 
                        }
                    break;*/
                case 734: // Ferrero Relay Plus
                    $this->linkid = $this->connectionManagerLIVE->Connect(9); // CANADALCL
                    if ($_REQUEST['SIF'] == "YES") {
                        $varsClass = new commonRetailLinkDaily(array_merge($commonParams, array('clientID' => "FERRERO")));
                        $varsClass->setCluster();
                    } else {
                        $varsClass = new ferreroRelayPlus(array_merge($commonParams, array('clientID' => "FERRERO", 'footerCompanyName' => "Ferrero UK", "extraVars" => array("ferreroAsdaOnlineTable" => "ferrero_asda_online", "multsSummaryAsdaOnlinePeriodTable" => "mults_summary_asda_online_period_list", "measureTypeField" => "ferrero_asda_online.measure_type","isShowCustomerLogo"=>true))));
                    }
                    break;
                case 724: // Online Sales Analysis
                    $this->linkid = $this->connectionManagerLIVE->Connect(9); // CANADALCL
                    $varsClass = new OnlineSalesAnalysis($this->aid, $this->uid, $this->projectID);
                    break;
                case 343:
                        //$this->linkid = $this->connectionManager->Connect(1);
                        $varsClass = new UbTescoFormatsAlternative($this->aid,$this->projectID, array(1));
                    break;    
                case 541: // Ferrero Seasonal Tracker
                        $this->linkid = $this->connectionManagerLIVE->Connect(9); // CANADALCL
                        $varsClass = new FerreroSeasonalTracker($this->aid,$this->projectID, array(1,2,3,4,5,6,26,28));
                    break;
                case 719: // Shana Relay Plus Daily 14 DDB
                        $this->ddbLinkid = $this->connectionManager->Connect(13);
                        $varsClass = new commonRetailLinkDaily(array_merge($commonParams,array('clientID' => "SHANA")));
                    break;
                case 720: // Ferrero Sellthru
                    $this->linkid = $this->connectionManagerLIVE->Connect(9); // CANADALCL
                    $varsClass = new FerreroSellthru($this->aid, $this->uid, $this->projectID);
                    break;
                case 722: // Ferrero Trade Calender New
                        $this->linkid = $this->connectionManagerLIVE->Connect(7);
                        //$this->linkid = $this->connectionManager->Connect(7);
                        $varsClass = new FerreroTradeCalender($this->aid, $this->uid, $this->projectID);
                    break;
                case 723: // Cluster Analysis
                    $varsClass = new FerreroClusterAnalysis($this->aid, $this->projectID, array(1,2,3,5,6));
                    break;  
                case 727: // Fcanada Nielsen
                    //$this->linkid = $this->connectionManager->Connect(22); // GNIELSEN
                    $this->linkid = $this->connectionManagerLIVE->Connect(22); // GNIELSEN
                    $varsClass = new FcanadaNielsen($this->aid,$this->projectID);
                    break;
                case 728: // MJN USA Nielsen
                    $this->linkid = $this->connectionManagerLIVE->Connect(9); // CANADALCL
                    $varsClass = new MjnUsaNielsenLcl($this->aid,$this->projectID);
                    break;                    
                case 729: // Oleiva LCL
                    //$this->aid = 141;
                    $this->linkid = $this->connectionManagerLIVE->Connect(9); // CANADALCL
                    $varsClass = new OleivaLcl($this->aid,$this->projectID);
                    break;            
                case 920: // PANDG Mults Daily Sales Builder
                    $this->aid = 126;
                    $this->linkid = $this->connectionManagerLIVE->Connect(9); // CANADALCL
                    //$this->projectID = 733;
                    $varsClass = new PandgMultsDailySalesBuilder($this->aid,$this->projectID,array(1,2,3,29));
                    break;
                /*case 733: // PANDG Mults Daily Sales Builder
                    $this->linkid = $this->connectionManager->Connect(9); // CANADALCL
                    $varsClass = new PandgMultsDailySalesBuilder($this->aid,$this->projectID,array(29));
                    break;*/
                case 735:
                    $varsClass = new ArlaNewView($this->aid,$this->projectID, array(10));
                    break;                                 
                case 738: //IFCN FACTORY SLS - MONTHLY 
                    $this->linkid = $this->connectionManagerLIVE->Connect(8); // mjn
                    $varsClass = new MjnFactorySalesMonthly($this->aid,$this->projectID,array(1));
                    break;
                case 568: //IFCN FACTORY SLS - MONTHLY 
                    $this->linkid = $this->connectionManagerLIVE->Connect(8); // mjn
                    $varsClass = new MjnFactorySalesMonthly($this->aid,$this->projectID,array(1));
                    break;
                case 97: // RETAIL POS - EXECUTIVE SUMMARY
                    $this->linkid = $this->connectionManagerLIVE->Connect(8); // mjn
                    $varsClass = new MjnPosMasterSystem($this->aid,$this->projectID,array(2,3,4,5,12,13,14));
                    break;                    
                case 739: // Arla Seasonal Tracker
                    $this->linkid = $this->connectionManagerLIVE->Connect(9); // CANADALCL
                    $varsClass = new ArlaSeasonalTracker($this->aid,$this->projectID, array(8));
                    break;
                case 741: // Sainsburys Depot Daily DDB
                    $this->ddbLinkid = $this->connectionManager->Connect(13);
                    $this->linkid = $this->connectionManager->Connect(9); // canadalcl
                    $varsClass = new FerreroSainsburysDepotDaily($this->aid,$this->projectID);
                    break;
                case 742: // Sainsburys Depot Daily
                    $this->linkid = $this->connectionManagerLIVE->Connect(9); // CANADALCL
                    $varsClass = new FerreroSainsburysDepotDaily($this->aid,$this->projectID);
                    break;                    
                case 743: // Ultralysis Coop AOD
                    $this->linkid = $this->connectionManagerLIVE->Connect(9); // CANADALCL
                    $varsClass = new FerreroMultsSummaryMasterSystem($this->aid,$this->projectID, array(7));
                    break;
                case 745: // Red Bull Relay Plus
                    if ($_REQUEST['SIF'] == "YES") {
                        $varsClass = new commonRetailLinkDaily(array_merge($commonParams, array('clientID' => "REDBULL", 'isretaillinkdctable'=> true)));
                        $varsClass->setCluster();
                    } else {
                        $varsClass = new commonMultsSummary(array_merge($commonParams, array('clientID' => "REDBULL", 'footerCompanyName' => "RED BULL")));
                    }
                    break;
                case 751: // RB UK Tesco ImpulseView    
                    $this->linkid = $this->connectionManagerLIVE->Connect(9); // CANADALCL
                    if ($_REQUEST['SIF'] == "YES") {
                        $varsClass = new commonTescoStoreDaily(array_merge($commonParams,array('clientID' => "RBUK")));
                        $varsClass->setCluster();
                    } else {
                        $varsClass = new commonMultsSummary(array_merge($commonParams, array('clientID' => "RBUK", 'footerCompanyName' => "RB UK", "gId" => 1)));
                    }
                    break;
                case 32: // AG Barr Master
                    $this->linkid = $this->connectionManagerLIVE->Connect(9); // CANADALCL
                    $varsClass = new AgbarrMults($this->aid,$this->projectID,array(1,2,3));
                    break;
                case 753: // Multy Sales Tracker
                    $this->linkid = $this->connectionManagerLIVE->Connect(9); // CANADALCL
                    $varsClass = new MultySalesTracker($this->aid,$this->projectID, array(30));
                    break;
                case 756: // Shana Corp Sales
                    $varsClass = new ShanaCorpSales($this->aid,$this->projectID,array(32));
                    break;      
                case 762: // Arla LCL New View Test
                    $varsClass = new ArlaNewViewTest($this->aid,$this->projectID,array(10));
                    break;
                case 768: // Arla LCL Base/Incremental Tracker
                    $this->linkid = $this->connectionManagerLIVE->Connect(9); // CANADALCL
                    $varsClass = new ArlaBump($this->aid,$this->projectID, array(10));
                    break;
                case 353: // Ferrero Master Html
                    $this->linkid = $this->connectionManagerLIVE->Connect(9); // CANADALCL
                    $varsClass = new FerreroMasterSystem($this->aid,$this->projectID,array(1,2,3,5,6));
                    break;
                case 775:
                     $varsClass = new ArlaMults($this->aid,$this->projectID, array(10));
                    break;
                case 780:
                    $this->linkid = $this->connectionManagerLIVE->Connect(9); // CANADALCL
                    $varsClass = new FerreroSellthru($this->aid,$this->projectID, array(2));
                    break;
                case 781:
                    $this->linkid = $this->connectionManagerLIVE->Connect(9); // CANADALCL
                    $this->aid = 142;
                    $varsClass = new GrenadeMults($this->aid,$this->projectID,array(1));
                    break;
                default :
                    exit(json_encode(array('access' => 'unothorized')));
                    break;
            }
        }else{
            if(empty($varsClass)){
                exit(json_encode(array('access' => 'unothorized')));
            }
        }
		$varsClass->databaseName = $this->connectionManager->getDefaultDatabaseName();
        $varsClass->projectID   = $this->projectID;
        $varsClass->userID      = $this->uid;

        // will work only when we are on live server        
        if ($this->devServer == "false" && isset($_REQUEST['trackRequest']) && $_REQUEST['trackRequest'] == 'true')
            $this->addVisitingPages();

        return $varsClass;
    }

    public function addVisitingPages()
    {
       /* $params = array();
        $params['pageTitle']    = trim($_REQUEST['pageTitle']);
        $params['pid']          = $this->projectID;
        $params['cid']          = $_SESSION['accountID'];
        $params['aid']          = $_SESSION['account'];
        
        $ultraUtility = lib\UltraUtility::getInstance();
        $ultraUtility->addVisitingPages($params);*/
    }
}

?>
