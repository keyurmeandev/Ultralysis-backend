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
            case 'nielsen':
                $configPath = "../global/project/config.xml";
                break;
            case 'relayplus':
                $configPath = "../global/project/config.xml";
                break;
            case 'tesco-store-daily':
                $configPath = "../global/project/config.xml";
                break;                
            case 'impulseViewJS':
                $configPath = "../global/project/config.xml";
                break;                
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
            case 'sales-builder':
                $configPath = "../sales-builder/project/config.xml";
                break;
            case 'super-drug':
                $configPath = "../super-drug/project/config.xml";
                break;
            case 'ddb':
                $configPath = "../dynamic-data-builder/config.xml";
                break;                
            default :
                exit(json_encode(array('Error' => 'Config.xml file is missing!' )));
                break;
        }

        $xml = simplexml_load_file($configPath); // for lcl project
        $this->devServer = $xml->project->development;

        if ($this->devServer == "false") {
            session_start();
            session_write_close();
            // will work when server on live
            if (!isset($_REQUEST["projectID"]) || empty($_REQUEST["projectID"])) {
                exit(json_encode(array('access' => 'unauthorized')));
            } else {
                $this->aid = $_SESSION["accountID"];
                $this->uid = $_SESSION["account"];
            }
        } else {
            // will work when server on development
            $this->projectID = $xml->project->projectID;
            $this->aid = $xml->project->accountID;
            $this->uid = $xml->project->account;
        }


        /**
         * if $_REQUEST['projectID'] is not empty then the projectID will be set from $_REQUEST['projectID']
         * if it is live server then the projectID will be set from accountID of session 
         * otherwise the projectID will be set from config.xml 
         */
        if (isset($_REQUEST['projectID']) && !empty($_REQUEST['projectID']))
            $this->projectID = utils\Encryption::decode($_REQUEST['projectID']);

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
        
        $this->connectionManager = new db\I3_Connection();
        $this->queryHandler = new db\QueryHandler();

        $this->projectManagerLinkid = $this->connectionManager->Connect(19); // project_manager_new
        
        if($_REQUEST['projectType'] == 'ddb')
            $this->ddbLinkid = $this->connectionManager->Connect(13); // DDB
        
        $this->linkid = $this->connectionManager->Connect(9); // canadalcl
        
        $redisConnectionManager = new db\REDIS_Connection();
        $this->redisLink = $redisConnectionManager->Connect();

        $this->redisCacheVersion = 5; // DO NOT PUT Freaction point value(.) (Like 1.0 or 1.2.1), KEEP IT INT VALUE
        
        $redisCache = new utils\RedisCache($this);
        $redisCacheVersionOutput = $redisCache->checkAndReadByStaticHashFromCache('redis_cache_version');
        
        if(empty($redisCacheVersionOutput))
            $redisCache->setDataForStaticHash($this->redisCacheVersion);
        else if($this->redisCacheVersion > $redisCacheVersionOutput) {
            $this->redisLink->select($this->projectID);
            $this->redisLink->flushDb();
            $redisCache->setDataForStaticHash($this->redisCacheVersion);
        }        
        
        $this->getAllConfiguration();

        $this->removeCachedDataKeys();
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
        $removeKeys = array('time_filter_data');
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

            case 568: // MJN FACTORY SALES - MONTHLY (Html)
                    $this->linkid = $this->connectionManager->Connect(8); // mjn
                    $varsClass = new MjnFactorySalesMonthly($this->aid,$this->projectID,array(1));
                    break;

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

            case 679: // LCL Plano Audit
                $varsClass = new FcanadaLCLPlanoAudit($this->aid,$this->projectID);
                break;
                
            case 689: // IDFOODS LCL Plano Audit
                $varsClass = new IDFoodsLCLPlanoAudit($this->aid,$this->projectID);
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

            case 684: // Jamieson LCL Competitor
                $varsClass = new JamiesonLclCompetitor($this->aid,$this->projectID);
                break;
                
            case 702: // Hasbro LCL Competitor
                $varsClass = new HasbroLclCompetitor($this->aid,$this->projectID);
                break;

            case 703: // HASBRO LCL Plano Audit
                $varsClass = new HasbroLCLPlanoAudit($this->aid,$this->projectID);
                break;

            case 709: // Ferrero Tesco Ireland
                $varsClass = new FerreroTescoIreland($this->aid,$this->projectID);
                break;

            case 705: // MJN Nielsen NEW V2
                $this->linkid = $this->connectionManager->Connect(22); // canadalcl
                $varsClass = new MjnNielsen($this->aid,$this->projectID);
                break;
                
            case 715: // Ferrero Blakemore
                $varsClass = new FerreroBlakemore($this->aid,$this->projectID,array(27));
                break;   

            case 723: // Ferrero Seasonal Tracker
                $varsClass = new FerreroSeasonalTracker($this->aid,$this->projectID, array(1));
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
                    $varsClass = new commonRetailLinkDaily(array_merge($commonParams, array('clientID' => "FERRERO")));
                    $varsClass->setCluster();
                } else {
                   $varsClass = new commonMultsSummary(array_merge($commonParams, array('clientID' => "FERRERO", 'footerCompanyName' => "Ferrero UK")));
                }
                break;                
                
            case 403: // Shana Relay Plus
                if ($_REQUEST['SIF'] == "YES") {
                    $varsClass = new commonRetailLinkDaily(array_merge($commonParams, array('clientID' => "SHANA")));
                    $varsClass->setCluster();
                } else {
                   $varsClass = new commonMultsSummary(array_merge($commonParams, array('clientID' => "SHANA", 'footerCompanyName' => "Shana Foods")));
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
                    $varsClass = new commonMultsSummary(array_merge($commonParams, array('clientID' => "FCANADA", 'footerCompanyName' => "Ferrero Canada", 'clientLogo' => "ferrero.png", 'gId' => 8)));
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

            case 676: // Britvic Relay Plus
                if ($_REQUEST['SIF'] == "YES") {
                    $varsClass = new commonRetailLinkDaily(array_merge($commonParams, array('clientID' => "BRITVIC")));
                    $varsClass->setCluster();
                } else {
                   $varsClass = new commonMultsSummary(array_merge($commonParams, array('clientID' => "BRITVIC", 'footerCompanyName' => "BRITVIC")));
                }
                break;
                
            case 613: // Johnsonville Relay Plus
                if ($_REQUEST['SIF'] == "YES") {
                    $varsClass = new commonRetailLinkDaily(array_merge($commonParams, array('clientID'  => "JV", 'maintable' => "johnsonville_retail_link_daily_14", 'gId' => 8)));
                    $varsClass->setCluster();
                } else {
                    $varsClass = new commonMultsSummary(array_merge($commonParams, array('clientID' => "JV", 'maintable' => "johnsonville_mults_summary", 'footerCompanyName' => "Johnsonville", 'clientLogo' => "johnsonville.png", 'gId' => 8)));
                }
                break;

            case 596: // PANDG Relay Plus
                if ($_REQUEST['SIF'] == "YES") {
                    $varsClass = new commonRetailLinkDaily(array_merge($commonParams,array('clientID' => "PANDG")));
                    $varsClass->setCluster();
                } else {
                    $varsClass = new commonMultsSummary(array_merge($commonParams, array('clientID' => "PANDG", 'footerCompanyName' => "P&G", 'clientLogo' => "PG-logo.png")));
                }
                break;

            case 574: // Dare Relay Plus
                if ($_REQUEST['SIF'] == "YES") {
                    $varsClass = new commonRetailLinkDaily(array_merge($commonParams, array('clientID' => "DARE", 'maintable'=> "Dare_retail_link_daily_14", 'gId' => 8)));
                    $varsClass->setCluster();
                } else {
                   $varsClass = new commonMultsSummary(array_merge($commonParams, array('clientID' => "DARE", 'footerCompanyName' => "Dare",'clientLogo' => "dare.jpg", 'gId' => 8)));
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
                    $varsClass = new commonMultsSummary(array_merge($commonParams, array('clientID' => "MP", 'footerCompanyName' => "Mother  Parker", 'clientLogo' => "motherparkers.png", 'gId' => 8)));
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
                   $varsClass = new commonMultsSummary(array_merge($commonParams, array('clientID' => "UB",'footerCompanyName' => "United Biscuits UK", 'clientLogo' => "unitedbiscuits.png")));
                }
                break;
                
            case 681: // Shana Item View
                $varsClass = new commonMultsSummary(array_merge($commonParams, array('clientID' => "SHANA", 'footerCompanyName' => "Shana Foods",  'gId' => 6, 'retailerLogo' => "sainsburys.png")));
                break;
                
            case 682: // Quorn Item View
                $varsClass = new commonMultsSummary(array_merge($commonParams, array('clientID' => "QUORN", 'footerCompanyName' => "QUORN",  'gId' => 6, 'retailerLogo' => "sainsburys.png")));
                break;

            case 713: // Ferrero Morissons Summary
                $varsClass = new AgbarrAsdaOnline($this->aid, $this->uid, $this->projectID);
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

            case 597: // PANDG Sales Builder
                $varsClass = new PandgSalesBuilder($this->aid,$this->projectID,array(1,2,3));
                break;

            case 649: //    Ferrero Bestway Sales Builder
                $varsClass = new FerreroBestwaySalesBuilder($this->aid,$this->projectID,array(12));
                break;            
                
            case 650: //    Ferrero Booker Sales Builder
                $varsClass = new FerreroBookerSalesBuilder($this->aid,$this->projectID,array(14));
                break;

            case 688: // Jamieson DDB
                $varsClass = new JamiesonLcl($this->aid,$this->projectID);
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
                    $varsClass = new commonMultsSummary(array_merge($commonParams, array('footerCompanyName' => "Asr Group", "gId" => 1, "maintable" => "asr_mults_summary", "clientID" => "ASRGROUP", "clientLogo" => "asrgroup.png")));
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

            case 722: // Ferrero ImpulseView JS
                if ($_REQUEST['SIF'] == "YES") {
                    $varsClass = new FerreroImpulseViewJS($this->aid,$this->projectID);
                    $varsClass->setCluster();
                } else {
                    $varsClass = new commonMultsSummary(array_merge($commonParams, array('clientID' => "FERRERO", 'footerCompanyName' => "Ferrero", 'clientLogo' => "ferrero.png", 'gId' => 6)));
                }
                break;
                
            /* ---------------- TSD Client Class Configuration End ---------------- */
            
            /* ---------------- Dynamic Data Builder Start ---------------- */
            
            case 543: // Arla LCL DDB
                $varsClass = new ArlaMults($this->aid,$this->projectID, array(10));
                break;

            case 618: // Rubicon LCL DDB
                $varsClass = new RubiconLcl($this->aid,$this->projectID);
                break;

            case 585: // Parmalat LCL DDB
                $varsClass = new ParmalatLcl($this->aid,$this->projectID);
                break;

            case 552: // Materne LCL DDB
                $varsClass = new MaterneLcl($this->aid,$this->projectID);
                break;
                
            case 536: // Gaylea LCL DDB
                $varsClass = new GayleaLcl($this->aid,$this->projectID);
                break;

            case 433: // IDFoods LCL DDB
                $varsClass = new IdFoodsLcl($this->aid,$this->projectID);
                break;

            case 430: // Pataks LCL DDB
                $varsClass = new PataksLcl($this->aid,$this->projectID);
                break;

            case 429: // Blue Dragon LCL DDB
                $varsClass = new BlueDragonLcl($this->aid, $this->projectID);
                break;

            case 411: // Danone LCL DDB
                $varsClass = new DanoneLcl($this->aid,$this->projectID);
                break;

            case 402: // Johnsonville LCL DDB
                $varsClass = new JohnsonvillieLcl($this->aid,$this->projectID);
                break;
                
            case 625: // Factory Sales Weekly DDB I3
                $this->linkid = $this->connectionManager->Connect(8); // mjn
                $varsClass = new MjnFactorySalesWeekly($this->aid,$this->projectID,array(1));
                break;

            case 653: // Factory Sales Weekly DDB I3 V2
                $this->linkid = $this->connectionManager->Connect(8); // mjn
                $varsClass = new MjnFactorySalesWeekly($this->aid,$this->projectID,array(1));
                break;
                
            case 624: // All Distributor DDB I3
                $this->linkid = $this->connectionManager->Connect(8); // mjn
                $varsClass = new MjnDistMasterSystem($this->aid,$this->projectID,array(6,7,8));
                break;
                
            case 621: // Factory Sales Monthly DDB I3
                $this->linkid = $this->connectionManager->Connect(8); // mjn
                $varsClass = new MjnFactorySalesMonthly($this->aid,$this->projectID,array(1));
                break;

            case 652: // Factory Sales Monthly DDB I3 V2
                $this->linkid = $this->connectionManager->Connect(8); // mjn
                $varsClass = new MjnFactorySalesMonthly($this->aid,$this->projectID,array(1));
                break;

            case 623: // MJN Dynamic Data Builder I3
                $this->linkid = $this->connectionManager->Connect(8); // mjn
                $varsClass = new MjnPosMasterSystem($this->aid,$this->projectID,array(2,3,4,5,12,13,14)); 
                break;
                
            case 622: // RETAIL POS - DYNAMIC DATA BUILDER I3
                $this->linkid = $this->connectionManager->Connect(8); // mjn
                $varsClass = new MjnPosMasterSystem($this->aid,$this->projectID,array(2,3,4,5,12,13,14));
                break;                

            case 706: // RETAIL POS - EXECUTIVE SUMMARY DDB NEW
                $this->linkid = $this->connectionManager->Connect(8); // mjn
                $varsClass = new MjnPosMasterSystem($this->aid,$this->projectID,array(2,3,4,5,12,13,14));
                break;

            case 707: // RETAIL POST - POSTAL CODE DDB NEW
                $this->linkid = $this->connectionManager->Connect(8); // mjn
                $varsClass = new MjnPosMasterSystem($this->aid,$this->projectID,array(2,3,4,5,12,13,14));
                break;
                
            case 662: // Lindt Relay Plus DDB
                $varsClass = new commonMultsSummary(array_merge($commonParams, array('clientID' => "LINDT")));
                break;

            case 678: // Britvic Relay Plus DDB
                $varsClass = new commonMultsSummary(array_merge($commonParams, array('clientID' => "BRITVIC")));
                break;

            case 549: // Quorn Relay Plus DDB
                $varsClass = new commonMultsSummary(array_merge($commonParams, array('clientID' => "QUORN")));
                break;

            case 555: // Red Bull Relay Plus DDB
                $varsClass = new commonMultsSummary(array_merge($commonParams, array('clientID' => "REDBULL")));
                break;

            case 475: // Shana Relay Plus DDB
                $varsClass = new commonMultsSummary(array_merge($commonParams, array('clientID' => "SHANA")));
                break;

            case 474: // Hartz Relay Plus DDB
                $varsClass = new commonMultsSummary(array_merge($commonParams, array('clientID' => "HARTZ", 'gId' => 8)));
                break;

            case 473: // Agbarr Relay Plus DDB
                $varsClass = new commonMultsSummary(array_merge($commonParams, array('clientID' => "AGBARR")));
                break;

            case 472: // Rubicon Relay Plus DDB
                $varsClass = new commonMultsSummary(array_merge($commonParams, array('clientID' => "RUBICON", 'gId' => 8)));
                break;

            case 471: // Arla Relay Plus DDB
                $varsClass = new commonMultsSummary(array_merge($commonParams, array('clientID' => "ARLA", 'gId' => 8)));
                break;

            case 470: // MP Relay Plus DDB
                $varsClass = new commonMultsSummary(array_merge($commonParams, array('clientID' => "MP", 'gId' => 8)));
                break;

            case 469: // Ferrero Relay Plus DDB
                $varsClass = new commonMultsSummary(array_merge($commonParams, array('clientID' => "FERRERO")));
                break;

            case 432: // Blue Dragon Relay Plus DDB
                $varsClass = new commonMultsSummary(array_merge($commonParams, array('clientID' => "BLUEDRAGON", 'gId' => 8)));
                break;

            case 431: // Pataks Relay Plus DDB
                $varsClass = new commonMultsSummary(array_merge($commonParams, array('clientID' => "PATAKS", 'gId' => 8)));
                break;

            case 418: // UB Relay Plus DDB
                $varsClass = new commonMultsSummary(array_merge($commonParams, array('clientID' => "UB")));
                break;

            case 672: // UB Relay Plus DDB
                $varsClass = new ParmalatLclCompetitorDdb($this->aid,$this->uid,$this->projectID);
                break;
                
            case 659: // Red Bull TSD DDB
                $varsClass = new commonTescoStoreDaily(array_merge($commonParams,array('clientID' => "REDBULL")));
                break;

            case 655: // RB TSD DDB
                $varsClass = new RbTsdMultsSummary($this->aid,$this->uid,$this->projectID);
                break;

            case 628: // ASR TSD DDB
                $varsClass = new commonMultsSummary(array_merge($commonParams, array('footerCompanyName' => "Asr Group", "gId" => 1, "maintable" => "asr_mults_summary", "clientID" => "ASRGROUP", "clientLogo" => "asrgroup.png")));
                break;

            case 590: // Baxters TSD DDB
                $varsClass = new commonMultsSummary(array_merge($commonParams, array('clientID' => "BAXTERS", 'footerCompanyName' => "Baxters", "gId" => 1)));
                break;

            case 637: // UB Tesco Formats DDB
                $varsClass = new UbTescoFormat($this->aid,$this->projectID);
                break;

            case 633: // MJN USA Shipments Monthly DDB
                $varsClass = new MjnUsaShipmentLclMonthly($this->aid,$this->projectID);
                break;

            case 632: // MJN USA Shipments Weekly DDB
                $varsClass = new MjnUsaShipmentLcl($this->aid,$this->projectID);
                break;

            case 617: // Rubicon Walmart DDB
                $varsClass = new RubiconRetailLink($this->aid,$this->projectID);
                break;

            case 616: // Shana Tesco DDB
                $varsClass = new ShanaMults($this->aid,$this->projectID,array(1));
                break;
                
            case 615: // Shana Asda DDB
                $varsClass = new ShanaMults($this->aid,$this->projectID,array(2));
                break;

            case 586: // Parmalat Nielsen DDB
                $varsClass = new ParmalatNielsenLcl($this->aid,$this->projectID);
                break;

            case 537: // Gaylea Retail Link DDB
                $varsClass = new GayleaRetailLink($this->aid,$this->projectID);
                break;

            case 534: // MJN USA Nielsen DDB
                $varsClass = new MjnUsaNielsenLcl($this->aid,$this->projectID);
                break;

            case 529: // Agbarr Master DDB
                $varsClass = new AgbarrMults($this->aid,$this->projectID,array(1,2,3));
                break;

            case 496: // Agbarr Morrison DDB
                $varsClass = new AgbarrMults($this->aid,$this->projectID,array(3));
                break;

            case 495: // Agbarr Asda DDB
                $varsClass = new AgbarrMults($this->aid,$this->projectID,array(2));
                break;

            case 494: // Agbarr Tesco DDB
                $varsClass = new AgbarrMults($this->aid,$this->projectID,array(1));
                break;

			case 484: // London Drugs DDB
                $varsClass = new fcanadaLD($this->aid,$this->projectID);
                break;

            case 486: // Ferrero Master DDB
                $varsClass = new FerreroMasterSystem($this->aid,$this->projectID,array(1,2,3,5,6));
                break;

            case 680: // Ferrero Master Customers DDB
                $varsClass = new FerreroMasterSystem($this->aid,$this->projectID,array(1,2,3,5,6));
                break;                
                
            case 424: // Ferrero/Thorntons Sainsburys DDB
                $varsClass = new FerreroMasterSystem($this->aid,$this->projectID,array(6));
                break;

            case 423: // Ferrero/Thorntons Morrisons DDB
                $varsClass = new FerreroMasterSystem($this->aid,$this->projectID,array(3));
                break;

            case 422: // Ferrero/Thorntons Tesco DDB
                $varsClass = new FerreroMasterSystem($this->aid,$this->projectID,array(1));
                break;

            case 421: // Ferrero/Thorntons Asda DDB
                $varsClass = new FerreroMasterSystem($this->aid,$this->projectID,array(2));
                break;

            case 420: // Ferrero/Thorntons Coop DDB
                $varsClass = new FerreroMasterSystem($this->aid,$this->projectID,array(5));
                break;

            case 387: // Ferrero Bestway DDB
                $varsClass = new FerreroBestway($this->aid,$this->projectID);
                break;

            case 386: // Ferrero Booker DDB
                $varsClass = new FerreroBooker($this->aid,$this->projectID);
                break;

            case 357: // Ferrero Tesco Store Daily DDB
                $varsClass = new commonTescoStoreDaily(array_merge($commonParams, array('clientID' => "FERRERO", 'footerCompanyName' => "Ferrero UK", "gId" => 1)));
                break;

            case 352: // Dynamic Data Builder - MP Brand Only
                $varsClass = new MotherParkersBrandLcl($this->aid,$this->projectID);
                break;

            case 686: // RETAIL POS - LOBLAW COMPANIES DDB I3
                $this->linkid = $this->connectionManager->Connect(8); // mjn
                $varsClass = new MjnPosMasterSystem($this->aid,$this->projectID,array(2));
                break;
                
            case 690: // RETAIL POS - WALMART CANADA DDB I3
                $this->linkid = $this->connectionManager->Connect(8); // mjn
                $varsClass = new MjnPosMasterSystem($this->aid,$this->projectID,array(3));
                break;

            case 691: // RETAIL POS - COSTCO CANADA DDB I3
                $this->linkid = $this->connectionManager->Connect(8); // mjn
                $varsClass = new MjnPosMasterSystem($this->aid,$this->projectID,array(12));
                break;

            case 692: // RETAIL POS - JEAN COUTU DDB I3
                $this->linkid = $this->connectionManager->Connect(8); // mjn
                $varsClass = new MjnPosMasterSystem($this->aid,$this->projectID,array(5));
                break;

            case 693: // RETAIL POS - TOYS R US DDB I3
                $this->linkid = $this->connectionManager->Connect(8); // mjn
                $varsClass = new MjnPosMasterSystem($this->aid,$this->projectID,array(13));
                break;

            case 694: // RETAIL POS - SHOPPERS DRUG MART DDB I3
                $this->linkid = $this->connectionManager->Connect(8); // mjn
                $varsClass = new MjnPosMasterSystem($this->aid,$this->projectID,array(4));
                break;
                
            case 695: // RETAIL POS - OFG DDB I3
                $this->linkid = $this->connectionManager->Connect(8); // mjn
                $varsClass = new MjnPosMasterSystem($this->aid,$this->projectID,array(14));
                break;
                
            case 696: // DISTRIBUTOR - MCKESSON DDB I3
                $this->linkid = $this->connectionManager->Connect(8); // mjn
                $varsClass = new MjnDistMasterSystem($this->aid,$this->projectID,array(8));
                break;

            case 697: // DISTRIBUTOR - MCMAHON DDB I3
                $this->linkid = $this->connectionManager->Connect(8); // mjn
                $varsClass = new MjnDistMasterSystem($this->aid,$this->projectID,array(9));
                break;

            case 698: // DISTRIBUTOR - KOHL & FRISCH DDB I3
                $this->linkid = $this->connectionManager->Connect(8); // mjn
                $varsClass = new MjnDistMasterSystem($this->aid,$this->projectID,array(7));
                break;

            case 699: // DISTRIBUTOR - JEAN COUTU DDB I3
                $this->linkid = $this->connectionManager->Connect(8); // mjn
                $varsClass = new MjnDistMasterSystem($this->aid,$this->projectID,array(6));
                break;
                
            /* ---------------- Dynamic Data Builder End ---------------- */

            default :
                exit(json_encode(array('access' => 'unothorized')));
                break;
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
        $params = array();
        $params['pageTitle']    = trim($_REQUEST['pageTitle']);
        $params['pid']          = $this->projectID;
        $params['cid']          = $_SESSION['accountID'];
        $params['aid']          = $_SESSION['account'];
        
        $ultraUtility = lib\UltraUtility::getInstance();
        $ultraUtility->addVisitingPages($params);
    }
}

?>
