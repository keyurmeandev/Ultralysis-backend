<?php
namespace classes\lcl;

use projectsettings;
use SimpleXMLElement;
use datahelper;
use filters;
use db;
use config;

class PlanogramDistributionGaps extends config\UlConfig {
    
    /*****
    * Default gateway function, should be similar in all DATA CLASS
    * arguments:
    * $settingVars [project settingsGateway variables]
    * @return $xmlOutput with all data that should be sent to client app
    * *** */
    public function go($settingVars) {
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES
        $this->pageName = $_REQUEST["pageName"];

        $action = $_REQUEST["action"];

        switch ($action) {
            case "fetchConfig":
                $this->prepareGridData();
                break;
            case "skuSelect":
                $this->prepareNotSellingGrid();
                break;
        }

        // if (isset($_REQUEST["action"]) && !empty($_REQUEST["action"]) && $_REQUEST["action"] == 'fetchConfig') {
        //     $this->prepareGridData();
        // }

        return $this->jsonOutput;
    }
    
    private function getFieldSettings(){
        $configurationCheck = new config\ConfigurationCheck($this->settingVars, $this->queryVars);
        $this->dbColumns = array();
        if (!empty($this->configErrors)) {
            $response = array("configuration" => array("status" => "fail", "messages" => $this->configErrors));
            echo json_encode($response);
            exit();
        }
    }

    private function prepareGridData() {
        $this->getFieldSettings();
        $lclPlanoList = array();
        $query="SELECT 
                    lclsfd.PIN, 
                    lclsfd.PNAME, 
                    lclspt.planogramme, 
                    COUNT(DISTINCT lclspt.SNO) as STORES_ON_PLANO 
                FROM 
                    ".$this->settingVars->lclStorePlanoTable." AS lclspt, 
                    ".$this->settingVars->lclShelfDetailsTable." AS lclsfd, 
                    ".$this->settingVars->storetable." AS st, 
                    ".$this->settingVars->skutable." 
                WHERE 
                    lclspt.planogramme = lclsfd.planogramme AND 
                    lclspt.SNO = st.SNO AND 
                    st.GID IN (".$this->settingVars->GID.") AND 
                    lclsfd.PIN = ".$this->settingVars->skutable.".PIN AND ".
                    $this->settingVars->skutable.".GID IN (".$this->settingVars->GID.") AND ".
                    $this->settingVars->skutable.".clientID IN ('".$this->settingVars->clientID."') 
                GROUP BY lclsfd.PIN, 
                         lclsfd.PNAME, 
                         lclspt.planogramme
                ORDER BY lclsfd.PIN ASC ";

        $lclPlanoList = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
        if(!empty($lclPlanoList)){
            filters\timeFilter::getYTD($this->settingVars);
            $LWeek = $this->settingVars->maxYearWeekCombination[2];
            
            $this->settingVars->tableUsedForQuery = $this->measureFields = array();
            $this->measureFields[] = $this->settingVars->maintable.'.SNO';
            $this->measureFields[] = $this->settingVars->skutable.'.PIN';
            $this->settingVars->useRequiredTablesOnly = true;
            if (is_array($this->measureFields) && !empty($this->measureFields)) {
                $this->prepareTablesUsedForQuery($this->measureFields);
            }
            $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll
            $this->queryPart .= " AND ".$this->settingVars->maintable.".QTY > 0 ";

            $query = "SELECT ".$this->settingVars->maintable.".PIN AS PIN, COUNT(DISTINCT ".$this->settingVars->maintable.".SNO) AS STORES_SELLING_LW FROM ".$this->settingVars->tablename." ".$this->queryPart." AND 
                        CONCAT(".$this->settingVars->maintable.".PIN,".$this->settingVars->maintable.".SNO) IN (
                                        SELECT DISTINCT
                                            CONCAT(lclsfd.PIN,lclspt.SNO) 
                                        FROM 
                                            ".$this->settingVars->lclStorePlanoTable." AS lclspt, 
                                            ".$this->settingVars->lclShelfDetailsTable." AS lclsfd, 
                                            ".$this->settingVars->storetable." AS st 
                                        WHERE 
                                            lclspt.planogramme = lclsfd.planogramme AND 
                                            lclspt.SNO = st.SNO AND 
                                            st.GID IN (".$this->settingVars->GID.") 
                        ) AND ".$this->settingVars->maintable.".mydate = '".$LWeek."'
                     GROUP BY PIN";
            $StoreSellingList = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $stLW = [];
            if(!empty($StoreSellingList)){
                $stLW = array_column($StoreSellingList, 'STORES_SELLING_LW', 'PIN');
            }

            foreach ($lclPlanoList as $key => $value) {
                $lclPlanoList[$key]['STORES_SELLING_LW'] = isset($stLW[$value['PIN']]) ? $stLW[$value['PIN']] : 0;
                $lclPlanoList[$key]['STORES_NOT_SELLING'] = $value['STORES_ON_PLANO'] - $lclPlanoList[$key]['STORES_SELLING_LW'];
            }
        }
        $this->jsonOutput['lclPlanoList'] = $lclPlanoList;
    }

    public function prepareNotSellingGrid() {
        $pin = $_REQUEST['PIN'];
        $notSellingStores = array();
        
        filters\timeFilter::getYTD($this->settingVars);
        $LWeek = $this->settingVars->maxYearWeekCombination[2];

        $this->settingVars->tableUsedForQuery = $this->measureFields = array();
        $this->measureFields[] = $this->settingVars->maintable.'.SNO';
        $this->measureFields[] = $this->settingVars->skutable.'.PIN';
        $this->settingVars->useRequiredTablesOnly = true;
        if (is_array($this->measureFields) && !empty($this->measureFields)) {
            $this->prepareTablesUsedForQuery($this->measureFields);
        }
        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll
        $this->queryPart .= " AND ".$this->settingVars->maintable.".QTY > 0 ";

        $q1PinCond = (!empty($pin)) ? "AND ".$this->settingVars->maintable.".PIN=".$pin : '';
        $query = "SELECT DISTINCT CONCAT(".$this->settingVars->maintable.".PIN, '_', ".$this->settingVars->maintable.".SNO) AS COMB FROM ".$this->settingVars->tablename." ".$this->queryPart.
                " AND 
                CONCAT(".$this->settingVars->maintable.".PIN,".$this->settingVars->maintable.".SNO) IN (
                                SELECT DISTINCT
                                    CONCAT(lclsfd.PIN,lclspt.SNO) 
                                FROM 
                                    ".$this->settingVars->lclStorePlanoTable." AS lclspt, 
                                    ".$this->settingVars->lclShelfDetailsTable." AS lclsfd, 
                                    ".$this->settingVars->storetable." AS st 
                                WHERE 
                                    lclspt.planogramme = lclsfd.planogramme AND 
                                    lclspt.SNO = st.SNO AND 
                                    st.GID IN (".$this->settingVars->GID.") 
                ) AND ".$this->settingVars->maintable.".mydate = '".$LWeek."' ".$q1PinCond." ORDER BY COMB ASC";

        $q2PinCond = (!empty($pin)) ? "AND lclsfd.PIN=".$pin : '';
        $lwSellingStore = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
        
        $query2 = "SELECT DISTINCT lclsfd.PIN, lclsfd.PNAME, lclspt.SNO, st.SNAME, CONCAT(lclsfd.PIN, '_', lclspt.SNO) as COMB_ALL FROM ".
                $this->settingVars->lclStorePlanoTable." AS lclspt, ".
                $this->settingVars->lclShelfDetailsTable." AS lclsfd, ".
                $this->settingVars->storetable." AS st, ".
                $this->settingVars->skutable." 
            WHERE 
                lclspt.planogramme = lclsfd.planogramme AND ".
                "lclspt.SNO = st.SNO ".$q2PinCond." AND 
                st.GID IN (".$this->settingVars->GID.") AND 
                lclsfd.PIN = ".$this->settingVars->skutable.".PIN AND ".
                $this->settingVars->skutable.".GID IN (".$this->settingVars->GID.") AND ".
                $this->settingVars->skutable.".clientID IN ('".$this->settingVars->clientID."') 
            ORDER BY COMB_ALL ASC";

        $totalStore = $this->queryVars->queryHandler->runQuery($query2, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
        if (is_array($lwSellingStore) && !empty($lwSellingStore))
            $notSellingStoresList = array_diff(array_column($totalStore, 'COMB_ALL'), array_column($lwSellingStore, 'COMB'));
        else
            $notSellingStoresList = array_column($totalStore, 'COMB_ALL');

        if (is_array($notSellingStoresList) && !empty($notSellingStoresList)) {
            foreach ($notSellingStoresList as $key => $notSellingStore) {
                $tmp = array();
                $tmp['SNO']   = $totalStore[$key]['SNO'];
                $tmp['PIN']   = $totalStore[$key]['PIN'];
                $tmp['SNAME'] = $totalStore[$key]['SNAME'];
                $tmp['PNAME'] = $totalStore[$key]['PNAME'];
                $notSellingStores[] = $tmp;
            }

            /*[START] Getting TOTAL ALL QTY LW */
            if(!empty($pin)){
                $allSno = array_column($notSellingStores, 'SNO');

                /*[START] FUNCTION GETTING THE LAST SOLD DATE*/
                $this->queryPart .= " AND ".$this->settingVars->maintable.".SNO IN (".implode(',',$allSno).") ";

                $lastSoldDateQue = "AND ".$this->settingVars->skutable.".PIN = ".$pin."";
                $this->settingVars->dateperiod = $this->settingVars->dateField;
                $lastSalesDates = datahelper\Common_Data_Fetching_Functions::getLastSalesDays( $this->settingVars->skutable.'.PIN', $this->settingVars->maintable.'.SNO', $this->settingVars, $this->queryPart.$lastSoldDateQue,'Y');
                /*[END] FUNCTION GETTING THE LAST SOLD DATE*/

                $query = "SELECT ".$this->settingVars->maintable.".SNO as SNO, SUM(".$this->settingVars->maintable.".QTY) AS TOTAL_ALL_QTY_LW FROM ".$this->settingVars->tablename." ".$this->queryPart." AND ".$this->settingVars->maintable.".mydate = '".$LWeek."' GROUP BY SNO ORDER BY SNO ASC";

                $totalAllQtyLw = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
                if (is_array($totalAllQtyLw) && !empty($totalAllQtyLw)) {
                    $allSno   = array_column($totalAllQtyLw, 'TOTAL_ALL_QTY_LW', 'SNO');
                    foreach ($notSellingStores as $key => $nts) {
                        $notSellingStores[$key]['TOTAL_ALL_QTY_LW'] = 0;
                        $notSellingStores[$key]['DATE_LAST_SOLD'] = '';

                        if(isset($allSno[$nts['SNO']]) && !empty($allSno[$nts['SNO']])){
                            $notSellingStores[$key]['TOTAL_ALL_QTY_LW'] = $allSno[$nts['SNO']];
                        }

                        if(isset($lastSalesDates[$pin.'_'.$nts['SNO']]) && !empty($lastSalesDates[$pin.'_'.$nts['SNO']])){
                            $notSellingStores[$key]['DATE_LAST_SOLD'] = date("d/m/Y", strtotime($lastSalesDates[$pin.'_'.$nts['SNO']]));
                        }
                    }
                }
            }
            /*[END] Getting TOTAL ALL QTY LW */
        }

        $this->jsonOutput['notSellingStores'] = $notSellingStores;
    }
}
?>