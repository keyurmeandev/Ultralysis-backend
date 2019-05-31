<?php
namespace filters;
class productAndMarketFilter
{
    public static function include_product_and_market_filters($queryVars, $settingVars, $filterType="", $requestKeyName = "FS"){
        $filterPart     = "";
        $tablename 	= explode("," , $settingVars->tablename);
        foreach($_REQUEST[$requestKeyName] as $key=>$data)
        {	
            if(!empty($data) && isset($settingVars->dataArray[$key]))
            {
                $data 			= str_replace('+', '%2B', $data);
				$data = htmlspecialchars_decode(urldecode($data));
				if(($filterType != "" && $filterType == $settingVars->dataArray[$key]['TYPE']) || $filterType == "" )
				{
					if($settingVars->dataArray[$key]['special']==1 && !in_array($settingVars->dataArray[$key]['tablename'] , $tablename) ){
						$settingVars->tablename 	.= ",".$settingVars->dataArray[$key]['tablename'];
						$filterPart			.= " AND ".$settingVars->dataArray[$key]['connectingField'];
						$tablename 			 = explode("," , $settingVars->tablename);
					}

					$arr            = array();
					$data 			= stripslashes($data);
					$data           = mysqli_real_escape_string($queryVars->linkid,$data);
					$arr            = explode(",", $data);
					$str            = implode("','", array_map('trim',$arr));
					$str            = "'" . $str . "'";
					$filterKey      = !key_exists('ID',$settingVars->dataArray[$key]) ? $settingVars->dataArray[$key]['NAME'] : $settingVars->dataArray[$key]['ID'];
					$filterPart    .= " AND trim($filterKey) IN (" .$str . ")";

					$fieldPart = explode(".", $filterKey);
					if (count($fieldPart) > 1) {
						if (!in_array($fieldPart[0], $settingVars->tableUsedForQuery))
	                        $settingVars->tableUsedForQuery[] = $fieldPart[0];
	                }
				}
            }
        }
        
        return $filterPart;
    }
}
?>