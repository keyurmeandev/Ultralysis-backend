<?php
namespace classes\lcl;

use projectsettings;
use SimpleXMLElement;
use datahelper;
use filters;
use db;
use config;

class LclSelfDetails extends config\UlConfig {
    
    /*****
    * Default gateway function, should be similar in all DATA CLASS
    * arguments:
    * $settingVars [project settingsGateway variables]
    * @return $xmlOutput with all data that should be sent to client app
    * *** */
    public function go($settingVars) {
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES
        $this->pageName = $_REQUEST["pageName"];

        if (isset($_REQUEST["action"]) && !empty($_REQUEST["action"]) && $_REQUEST["action"] == 'fetchConfig') {
            $this->prepareGridData();
        }else if (isset($_REQUEST["action"]) && !empty($_REQUEST["action"]) && $_REQUEST["action"] == 'loadPlanograms') {
            $this->preparePlanogramsGridData();
        }else if (isset($_REQUEST["action"]) && !empty($_REQUEST["action"]) && $_REQUEST["action"] == 'loadPlanogrammeDetails') {
            $this->preparePlanogramsDetailsGridData();
        }

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
        $query = "SELECT DISTINCT st.SNO, st.SNAME, st.BANNER, st.STATE AS PROVINCE " . 
        "FROM " . $this->settingVars->storetable . " AS st," . $this->settingVars->lclStorePlanoTable ." " . "WHERE st.SNO = " . $this->settingVars->lclStorePlanoTable . ".SNO AND st.GID IN (".$this->settingVars->GID.") ORDER BY st.SNO ASC";
        $lclPlanoList = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);

        if(!empty($lclPlanoList)){
            foreach ($lclPlanoList as $key => $lclpln) {
                $lclPlanoList[$key]['SNO']  = (int) $lclpln['SNO'];
            }
        }
        $this->jsonOutput['lclPlanoList'] = $lclPlanoList;
    }

    private function preparePlanogramsGridData(){
        if(isset($_REQUEST['SNO']) && !empty($_REQUEST['SNO']))
            $wherePart = $this->settingVars->lclStorePlanoTable.".SNO = ".$_REQUEST['SNO'];

        $lclPlanogramsList = array();
        /*$query = "SELECT DISTINCT planogramme, MAX(category) as category, CASE planogramme WHEN 'FE_CONFEC_LANE_01_02X50_NOFRILLS' THEN 'planogramme_image.png' ELSE '' END as planoImg FROM " . $this->settingVars->lclStorePlanoTable . " WHERE ".$wherePart." ORDER BY planogramme ASC";*/

        $query = "SELECT ".$this->settingVars->lclStorePlanoTable.".planogramme, COUNT(".$this->settingVars->lclPlanoImageTable.".plano_image) AS planoImg 
                  FROM ".$this->settingVars->lclStorePlanoTable." LEFT JOIN ".$this->settingVars->lclPlanoImageTable." ON ".$this->settingVars->lclStorePlanoTable.".planogramme = ".$this->settingVars->lclPlanoImageTable.".planogramme,".$this->settingVars->lclPlanoClientTable." WHERE ".$wherePart." AND 
                  ".$this->settingVars->lclStorePlanoTable.".planogramme = ".$this->settingVars->lclPlanoClientTable.".planogramme AND ".
                  $this->settingVars->lclPlanoClientTable.".clientID = ('".$this->settingVars->clientID."') 
        GROUP BY ".$this->settingVars->lclStorePlanoTable.".planogramme ORDER BY ".$this->settingVars->lclStorePlanoTable.".planogramme ASC";

        $lclPlanogramsList = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
        $this->jsonOutput['lclPlanogramsList'] = $lclPlanogramsList;
    }

    private function preparePlanogramsDetailsGridData(){
        $wherePart = '';
        if(isset($_REQUEST['SNO']) && !empty($_REQUEST['SNO']))
            $wherePart .= ' AND '.$this->settingVars->lclStorePlanoTable.".SNO = ".$_REQUEST['SNO'];

        if(isset($_REQUEST['PLANOGRAMME']) && !empty($_REQUEST['PLANOGRAMME']))
            $wherePart .= ' AND '.$this->settingVars->lclShelfDetailsTable.".planogramme = '".urldecode($_REQUEST['PLANOGRAMME'])."'";

        $lclPlanogrammeDetailsList = array();
        $query = "SELECT ".
                    $this->settingVars->lclShelfDetailsTable.".PIN, ".
                    $this->settingVars->lclShelfDetailsTable.".PNAME, ". 
                    $this->settingVars->lclShelfDetailsTable.".segment, ". 
                    "MAX(".$this->settingVars->lclShelfDetailsTable.".SHELF) AS SHELF,". 
                    "MAX(".$this->settingVars->lclShelfDetailsTable.".ID) AS ID, ". 
                    $this->settingVars->lclShelfDetailsTable.".FCN 
                FROM 
                    ".$this->settingVars->lclShelfDetailsTable.",
                    ".$this->settingVars->lclStorePlanoTable . ",
                    ".$this->settingVars->skutable . "
                WHERE 
                    ".$this->settingVars->lclShelfDetailsTable.".planogramme = ".$this->settingVars->lclStorePlanoTable.".planogramme ".
              " AND ".$this->settingVars->lclShelfDetailsTable.".PIN = ".$this->settingVars->skutable.".PIN ".
              " AND ".$this->settingVars->skutable.".GID IN (".$this->settingVars->GID.") ".
              " AND ".$this->settingVars->skutable.".clientID IN ('".$this->settingVars->clientID."') ".
              //" AND ".$this->settingVars->skutable.".clientID IN ('FCANADA') ".
              //" AND ".$this->settingVars->skutable.".clientID IN ('IDFOODS') ".
                $wherePart." GROUP BY ".$this->settingVars->lclShelfDetailsTable.".PIN, ".
                                        $this->settingVars->lclShelfDetailsTable.".PNAME, ". 
                                        $this->settingVars->lclShelfDetailsTable.".FCN, ".
                                        $this->settingVars->lclShelfDetailsTable.".segment ORDER BY PNAME ASC";

        $lclPlanogrammeDetailsList = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
        $this->jsonOutput['lclPlanogrammeDetailsList'] = $lclPlanogrammeDetailsList;

        if(isset($_REQUEST['PLANOGRAMME']) && !empty($_REQUEST['PLANOGRAMME'])){
            /*[START] GETTING THE SEGMENT IMAGES*/
            if(isset($lclPlanogrammeDetailsList) && count($lclPlanogrammeDetailsList) > 0){
                $allSegments = array_column($lclPlanogrammeDetailsList, 'segment');
                $allSegments = array_unique($allSegments);
                    $qPlImg = "SELECT segment,plano_image FROM ".$this->settingVars->lclPlanoImageTable." WHERE ".$this->settingVars->lclPlanoImageTable.".planogramme = '".urldecode($_REQUEST['PLANOGRAMME'])."' AND ".$this->settingVars->lclPlanoImageTable.".segment IN (".implode(',',$allSegments).") ORDER BY ".$this->settingVars->lclPlanoImageTable.".segment ASC";

                    $lclPlanoSegImg = $this->queryVars->queryHandler->runQuery($qPlImg, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
                    if(!empty($lclPlanoSegImg)){
                        $this->jsonOutput['lclPlanogrammeSegmentImagesDefault'] = $lclPlanoSegImg[0]['segment'];
                        $this->jsonOutput['lclPlanogrammeSegmentImages'] = array_column($lclPlanoSegImg, 'plano_image','segment');
                    }
            }
            /*[END] GETTING THE SEGMENT IMAGES*/

            /*[START] Getting the SHELF AND ID by the Planogramme table using the WINDOW QUERY*/
            $query = "SELECT DISTINCT shelf, segment, max(id) OVER(partition by shelf,segment) facing FROM ".$this->settingVars->lclShelfDetailsTable." WHERE planogramme='".urldecode($_REQUEST['PLANOGRAMME'])."'";
            $lclPlanogrammeImagesDetailsList = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
            $this->jsonOutput['lclPlanogrammeImagesDetailsList'] = $lclPlanogrammeImagesDetailsList;
            /*[END] Getting the SHELF AND ID by the Planogramme table using the WINDOW QUERY*/

            if(isset($lclPlanogrammeImagesDetailsList) && count($lclPlanogrammeImagesDetailsList) > 0){
                foreach ($lclPlanogrammeImagesDetailsList as $key => $value) {
                    $allSegmentsSel[$value['segment']][] = ['facing'=>$value['facing'],'shelf'=>$value['shelf']];
                }
            }

            $tmpSegArr = [];
            foreach ($lclPlanogrammeDetailsList as $key => $value) {
                if(!isset($tmpSegArr[$value['segment']])) {
                    $tmpSegArr[$value['segment']] = array_column($allSegmentsSel[$value['segment']], 'facing', 'shelf');
                }
                
                $selVal = $tmpSegArr[$value['segment']];
                $selValTmpID = isset($selVal[$value['SHELF']]) ? $selVal[$value['SHELF']] : '';
                // $tmpArray[$value['segment']]['shelf_'.$value['SHELF'].'_'.$selValTmpID] = $value['ID'];
                $tmpArray[$value['segment']]['shelf_'.$value['SHELF'].'_'.$selValTmpID.'_'.$value['ID']] = $value['ID'];
                $tmpArray[$value['segment']]['tooltip_'.$value['SHELF'].'_'.$selValTmpID.'_'.$value['ID']] = 'PNAME: '.$value['PNAME'].'<br/>'.'FACINGS: '.$value['FCN'];
            }
            $this->jsonOutput['lclPlanogrammeImagesSelectedFIList'] = $tmpArray;
        }
    }
}
?>