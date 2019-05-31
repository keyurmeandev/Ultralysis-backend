<?php
namespace datahelper;

use projectsettings;
use db;
use utils;

class Product_And_Market_Filter_DataCollector{
    
    /*****
    * Static function so that we call it whenever we want , wherever we want , without thinking about getting an instance of the parent class
    * This function creates 'Helper Data' according to received args
    * arguments:
    * 	$id: id field of the account, which function should create helper data of
    * 	$name: account field, which function should create helper data of
    * 	$tablename: tables should be used in data fetching query
    * 	$querypart: table joing strings and other where clauses
    * 	$jsonOutput: calling class's output data storage variable.all data should add to this variable
    *****/
    public static function collect_Filter_Data($selectPart, $groupByPart, $jsonTag, $tablename, $querypart, &$jsonOutput, $includeIdInLabel = false, $account="", $orderByPart = "" ){
		$queryVars 	= projectsettings\settingsGateway::getInstance();
		
        $orderBySelectPart = "";
        if($orderByPart != "")
        {
            $orderBySelectPart = ", ".$orderByPart;
            $orderByPart = "ORDER_BY_FIELD ASC, PRIMARY_LABEL ASC";
        }
        else
            $orderByPart = "PRIMARY_LABEL ASC";
        
		$query = "SELECT ".implode("," , $selectPart)." ".$orderBySelectPart." ".
				"FROM $tablename $querypart ".
				"GROUP BY ".implode("," , $groupByPart)." ".
				"HAVING PRIMARY_LABEL <>'' ".
				"ORDER BY $orderByPart";
		
        $redisCache = new utils\RedisCache($queryVars);
        $redisOutput = $redisCache->checkAndReadByQueryHashFromCache($query);

        if ($redisOutput === false) {
            $result = $queryVars->queryHandler->runQuery($query,$queryVars->linkid,db\ResultTypes::$TYPE_OBJECT);
            $redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }
        
		$tmpParams = (isset($_REQUEST['FS'][$account])) ? explode(",",htmlspecialchars_decode(urldecode($_REQUEST['FS'][$account]))) : array();
		if((isset($_REQUEST['commonFilterPage']) && $_REQUEST['commonFilterPage'] == true) || (isset($_REQUEST['VeryFirstRequest']) && $_REQUEST['VeryFirstRequest'] == true))
		{
			foreach($result as $key=>$data)
			{
				$dataVal = in_array('PRIMARY_ID',$groupByPart) ? $data['PRIMARY_ID'] : $data['PRIMARY_LABEL'];
				$temp = array(
						//'data' => htmlspecialchars($dataVal)
						'data' => htmlspecialchars_decode(urldecode($dataVal))
						, 'label' => ($includeIdInLabel) ? htmlspecialchars_decode($data['PRIMARY_LABEL']." ( ".$data['PRIMARY_ID']." ) ") : htmlspecialchars_decode($data['PRIMARY_LABEL'])
						, 'label_hash_secondary' => htmlspecialchars_decode($data['PRIMARY_LABEL']." #".$data['SECONDARY_LABEL'])
					);
				$jsonOutput[$jsonTag][] = $temp;

				if(in_array($dataVal,$tmpParams))
				{
					$jsonOutput['commonFilter'][$jsonTag]['selectedDataList'][] = $temp;
				}
				else
					$jsonOutput['commonFilter'][$jsonTag]['dataList'][] = $temp;
			}
		}
		/*elseif(isset($_REQUEST['commonFilterApplied']) && $_REQUEST['commonFilterApplied'] == true)
		{
			foreach($result as $key=>$data)
			{
				$dataVal = in_array('PRIMARY_ID',$groupByPart) ? $data['PRIMARY_ID'] : $data['PRIMARY_LABEL'];
				if((isset($_REQUEST['FS'][$account]) && in_array($dataVal,$tmpParams)) || $_REQUEST['FS'][$account] == '')
				{
					$temp = array(
							//'data' => htmlspecialchars($dataVal)
							'data' => htmlspecialchars_decode(urldecode($dataVal))
							, 'label' => ($includeIdInLabel) ? htmlspecialchars_decode($data['PRIMARY_LABEL']." ( ".$data['PRIMARY_ID']." ) ") : htmlspecialchars_decode(urldecode($data['PRIMARY_LABEL']))
							, 'label_hash_secondary' => htmlspecialchars_decode($data['PRIMARY_LABEL']." #".$data['SECONDARY_LABEL'])
						);
						$jsonOutput[$jsonTag][] = $temp;
						if(in_array($dataVal,$tmpParams))
						{
							$jsonOutput['commonFilter'][$jsonTag]['selectedDataList'][] = $temp;
						}
						else
							$jsonOutput['commonFilter'][$jsonTag]['dataList'][] = $temp;
				}
			}
		}*/
		else
		{
			foreach($result as $key=>$data)
			{
				$dataVal = in_array('PRIMARY_ID',$groupByPart) ? $data['PRIMARY_ID'] : $data['PRIMARY_LABEL'];
					$temp = array(
							'data' => htmlspecialchars($dataVal)
							, 'label' => ($includeIdInLabel) ? htmlspecialchars_decode($data['PRIMARY_LABEL']." ( ".$data['PRIMARY_ID']." ) ") : htmlspecialchars_decode($data['PRIMARY_LABEL'])
							, 'label_hash_secondary' => htmlspecialchars_decode($data['PRIMARY_LABEL']." #".$data['SECONDARY_LABEL'])
						);
				$jsonOutput[$jsonTag][] = $temp;
			}
		}
    }

    public static function collect_Filter_Query_Data($selectPart, $groupByPart, $tablename, $querypart){
		$queryVars 	= projectsettings\settingsGateway::getInstance();
		
		$query = "SELECT ".implode("," , $selectPart)." ".
				"FROM $tablename $querypart ".
				"GROUP BY ".implode("," , $groupByPart)." ";
				//"HAVING PRIMARY_LABEL <>'' ".
				//"ORDER BY PRIMARY_LABEL ASC ";
		
        $redisCache = new utils\RedisCache($queryVars);
        $redisOutput = $redisCache->checkAndReadByQueryHashFromCache($query);

        if ($redisOutput === false) {
            $result = $queryVars->queryHandler->runQuery($query,$queryVars->linkid,db\ResultTypes::$TYPE_OBJECT);
            $redisCache->setDataForHash($result);
        } else {
            $result = $redisOutput;
        }
        
		return $result;
    }

    public static function getFilterData($nameAliase, $idAliase, $tempId, $resultData, $jsonTag, &$jsonOutput, $includeIdInLabel = false, $account=""){
		
    	$tmpParams = (isset($_REQUEST['FS'][$account])) ? explode(",",htmlspecialchars_decode(urldecode($_REQUEST['FS'][$account]))) : array();

        $dataArr = array();
        if(is_array($resultData) && !empty($resultData) ){
            foreach ($resultData as $key => $data) {
                $groupBy = ($tempId != "") ? $data[$nameAliase]."_".$data[$idAliase] : $data[$nameAliase]; 
                if($data[$nameAliase] != '' && !in_array($groupBy, $dataArr)){
                    $dataVal = ($tempId != "") ? $data[$idAliase] : $data[$nameAliase];
                        $temp = array(
                                'data' => htmlspecialchars($dataVal)
                                //'data' => htmlspecialchars_decode(urldecode($dataVal))
                                , 'label' => ($includeIdInLabel) ? htmlspecialchars_decode($data[$nameAliase]." ( ".$data[$idAliase]." ) ") : htmlspecialchars_decode($data[$nameAliase])
                                , 'label_hash_secondary' => htmlspecialchars_decode($data[$nameAliase]." #".$data['SECONDARY_LABEL'])
                            );
                    $dataArr[] = $groupBy;
                    $jsonOutput['filters'][$jsonTag][] = $temp;

                    if((isset($_REQUEST['commonFilterPage']) && $_REQUEST['commonFilterPage'] == true) || (isset($_REQUEST['VeryFirstRequest']) && $_REQUEST['VeryFirstRequest'] == true))
                    {
                        if(in_array($dataVal,$tmpParams))
                        {
                            $jsonOutput['filters']['commonFilter'][$jsonTag]['selectedDataList'][] = $temp;
                        }
                        else
                            $jsonOutput['filters']['commonFilter'][$jsonTag]['dataList'][] = $temp;
                    }
                }
            }
            $jsonOutput['filters'][$jsonTag] = utils\SortUtility::sort2DArray($jsonOutput['filters'][$jsonTag], 'label', utils\SortTypes::$SORT_ASCENDING);
        }

    }
}
?>