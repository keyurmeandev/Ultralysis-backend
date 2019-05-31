<?php
namespace utils;

use projectsettings;
use db;

class RedisCache {
    public $queryVars;     //containes query related variables [queryHandler,linkid]
    public $requestHash;     //containes query related variables [queryHandler,linkid]
    public $excludeDestinations = array(
        'PerformancePage' => array('hashFunction' => "prepareCommonHashForPerformance", 'logicFunction' => "performancePageCacheLogic"),
        'SvgMap' => array('hashFunction' => "prepareCommonHashForPerformance", 'logicFunction' => "performancePageCacheLogic"),
        'ddb\DynamicDataBuilder' => array('logicFunction' => "skipRequestCaching"),
        'ddb\LoadSavedFilter' => array('logicFunction' => "skipRequestCaching"),
    );     //containes query related variables [queryHandler,linkid]
    public $isRedisCachingEnabled;
    public $skipCommonCacheHash;

  //   function __construct($settingVars, $queryVars) {
        // $this->settingVars   = $settingVars;
  //    $this->queryVars    = $queryVars;
  //   }

    function __construct($queryVars) {
        $this->queryVars    = $queryVars;

        $this->queryVars->redisLink->select($this->queryVars->projectID);
		$this->isRedisCachingEnabled = $this->hasRedisCaching();
    }

    public function hasRedisCaching() {
        if(isset($this->queryVars->projectConfiguration['has_redis_caching']) && $this->queryVars->projectConfiguration['has_redis_caching'] == 1 )
            return true;
        else
            return false;
    }

    public function prepareCommonHash() {
    	return md5('http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . "{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}");
    }

    public function prepareCommonHashForPerformance() {
        $requestURI = $_SERVER['REQUEST_URI'];
        
        if ( (isset($_REQUEST['requestFieldName']) && !empty($_REQUEST['requestFieldName'])) || (isset($_REQUEST["LINECHART"]) && strtolower($_REQUEST["LINECHART"]) == "true") ) {
            $requestPart = explode("&", $requestURI);
            
            $searchword = "pageID=";
            $matches = array_filter($requestPart, function($var) use ($searchword) { return preg_match("/\b$searchword\b/i", $var); });
            $removeKey = (is_array($matches) && !empty($matches)) ? array_keys($matches)[0] : '';
            if (is_numeric($removeKey))
                unset($requestPart[$removeKey]);

            $searchword = "gridFetchName=";
            $matches = array_filter($requestPart, function($var) use ($searchword) { return preg_match("/\b$searchword\b/i", $var); });
            $removeKey = (is_array($matches) && !empty($matches)) ? array_keys($matches)[0] : '';
            if (is_numeric($removeKey))
                unset($requestPart[$removeKey]);

            $searchword = "ValueVolume=";
            $matches = array_filter($requestPart, function($var) use ($searchword) { return preg_match("/\b$searchword\b/i", $var); });
            $removeKey = (is_array($matches) && !empty($matches)) ? array_keys($matches)[0] : '';
            if (is_numeric($removeKey))
                unset($requestPart[$removeKey]);

            $searchword = "pageTitle=";
            $matches = array_filter($requestPart, function($var) use ($searchword) { return preg_match("/\b$searchword\b/i", $var); });
            $removeKey = (is_array($matches) && !empty($matches)) ? array_keys($matches)[0] : '';
            if (is_numeric($removeKey))
                unset($requestPart[$removeKey]);

            $requestURI = implode("&", $requestPart);
            $requestURI .= "&requestFieldName=".$_REQUEST['requestFieldName'];
        }

    	return md5('http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . "{$_SERVER['HTTP_HOST']}{$requestURI}");
    }

    public function checkAndReadFromCache($commonHashFunc = 'prepareCommonHash', $skipDestinationCheck = false) {

        if (!isset($this->isRedisCachingEnabled) || $this->isRedisCachingEnabled != true)
            return false;

        if (!$skipDestinationCheck && in_array($_REQUEST['destination'], array_keys($this->excludeDestinations))) {
            $this->skipCommonCacheHash = true;
            $functionName = $this->excludeDestinations[$_REQUEST['destination']]['logicFunction'];
            return $this->$functionName();
        }

		$this->queryVars->redisLink->select($this->queryVars->projectID); //set Database for redis data
		$this->requestHash = $this->$commonHashFunc();

        if ($this->queryVars->redisLink->exists($this->requestHash) ){
            //return json_decode($this->queryVars->redisLink->get($this->requestHash), true);
        	$jsonOutput = json_decode($this->queryVars->redisLink->get($this->requestHash), true);
            $jsonOutput['requestHash'] = $this->requestHash;
            return $jsonOutput;
        }

        return false;
	}

	public function setDataForHash($data) {
		if(isset($this->isRedisCachingEnabled) && $this->isRedisCachingEnabled == true )
			$this->queryVars->redisLink->set($this->requestHash, json_encode($data));

		return true;
	}

    public function setDataForSubKeyHash($data, $subHashKey = '') {
        $this->queryVars->redisLink->select($this->queryVars->projectID);
        $storedData = json_decode($this->queryVars->redisLink->get($this->requestHash), true);
        $storedData[$subHashKey] = json_encode($data);
        $this->queryVars->redisLink->set($this->requestHash, json_encode($storedData));

        return true;
    }

    public function checkAndReadByStaticHashFromCache($requestHash = '', $subHashKey = '') {

        $this->queryVars->redisLink->select($this->queryVars->projectID); //set Database for redis data
        $this->requestHash = $requestHash;

        if (!empty(trim($subHashKey)) && $this->queryVars->redisLink->exists($this->requestHash)) {
            $data = json_decode($this->queryVars->redisLink->get($this->requestHash), true);
            
            if (isset($data[$subHashKey]) && !empty($data[$subHashKey]))
                return json_decode($data[$subHashKey], true);
        }
        elseif ($this->queryVars->redisLink->exists($this->requestHash))
            return json_decode($this->queryVars->redisLink->get($this->requestHash), true);

        return false;
    }

    public function setDataForStaticHash($data) {
        $this->queryVars->redisLink->set($this->requestHash, json_encode($data));
        return true;
    }

    public function performancePageCacheLogic() {

        if (isset($_REQUEST["LINECHART"]) && strtolower($_REQUEST["LINECHART"]) == "true")
            $lineChart = true;

        if (!isset($_REQUEST["fetchConfig"]) || empty($_REQUEST["fetchConfig"]) || $_REQUEST["fetchConfig"] != 'true')
            $jsonOutput = $this->checkAndReadFromCache('prepareCommonHashForPerformance', true);
        else
            $jsonOutput = $this->checkAndReadFromCache('prepareCommonHash', true);

        if ($jsonOutput !== false) {
            if (isset($_REQUEST['requestFieldName']) && !empty($_REQUEST['requestFieldName'])) {
                $jsonOutput[$_REQUEST['gridFetchName']] = $jsonOutput[$_REQUEST['requestFieldName']];
                unset($jsonOutput[$_REQUEST['requestFieldName']]);
            }

            $measureArray = $jsonOutput['measureArray'];

            if(isset($jsonOutput[$_REQUEST['gridFetchName']]) && !empty($jsonOutput[$_REQUEST['gridFetchName']]) ){
                $requiredGridFields = array("ACCOUNT", "ID");

                $requiredGridFields = $this->getRequiredFieldsArray($requiredGridFields, false, $measureArray);
                $requiredGridFields[] = 'PERFORMANCE_VAR';
                $requiredGridFields[] = 'PERFORMANCE_VAR_PER';

                $orderBy = $tyField = $lyField = "";
                if(isset($_REQUEST['ValueVolume']) && !empty($_REQUEST['ValueVolume'])){
                    $measureKey = 'M' . $_REQUEST['ValueVolume'];
                    $measure    = $measureArray[$measureKey];
                    $tyField    = $orderBy = "TY" . $measure['ALIASE'];
                    $lyField    = "LY" . $measure['ALIASE'];
                }
                $jsonOutput[$_REQUEST['gridFetchName']] = $this->getRequiredData($jsonOutput[$_REQUEST['gridFetchName']],$requiredGridFields,$orderBy,$tyField,$lyField);
            }

            if($lineChart == true) {
                $requiredChartFields = array('ACCOUNT','TYACCOUNT','LYACCOUNT','TYMYDATE','LYMYDATE');
                $requiredChartFields = $this->getRequiredFieldsArray($requiredChartFields,true, $measureArray);
                $jsonOutput['LineChart'] = $this->getRequiredData($jsonOutput['LineChart'],$requiredChartFields);
            }

            unset($jsonOutput['measureArray']);
            
            return $jsonOutput;
        }
        return false;
    }

    public function getRequiredFieldsArray($fields = array() , $includeChartMeasure, $measureArray) {
        if(isset($_REQUEST['ValueVolume']) && !empty($_REQUEST['ValueVolume'])) {
            $measureKey = 'M' . $_REQUEST['ValueVolume'];
            $measure    = $measureArray[$measureKey];
            $fields[]   = "TY" . $measure['ALIASE'];
            $fields[]   = "LY" . $measure['ALIASE'];
        }

        if(isset($_REQUEST['requestedChartMeasure']) && !empty($_REQUEST['requestedChartMeasure']) && $includeChartMeasure ) {
            $measureKey = $_REQUEST['requestedChartMeasure'];
            $measure    = $measureArray[$measureKey];
            $fields[]   = "TY" . $measure['ALIASE'];            
            $fields[]   = "LY" . $measure['ALIASE'];            
        }
        return $fields;
    }

    public function getRequiredData($gridArr = array(), $requiredFields = array(), $orderBy = "", $tyField = "" ,$lyField = "", $options = array()){
        $gridData = array();
        $skipZeroVal = false;
        
        if(!empty($tyField) && !empty($lyField))
            $skipZeroVal = true;
        elseif(!empty($tyField))
            $skipTyZeroVal = true;
        elseif(!empty($lyField))
            $skipLyZeroVal = true;


        $allOther = array();
        if(is_array($gridArr) && !empty($gridArr)){
            foreach ($gridArr as $key => $value) {
                
                if($skipZeroVal && $value[$tyField] == 0 && $value[$lyField] == 0 )
                    continue;

                if ($skipTyZeroVal && $value[$tyField] == 0)
                    continue;

                if ($skipLyZeroVal && $value[$lyField] == 0)
                    continue;

                if (is_array($options) && !empty($options) && isset($options['limit']) && !empty($options['limit'])) {
                    if (count($gridData) >= $options['limit']) {
                        $addToAllOther = (isset($options['includeAllOther']) && $options['includeAllOther'] == 'YES') ? true : false;

                        if (!$addToAllOther)
                            continue;
                    }
                }

                if ($addToAllOther) {
                    if (is_array($requiredFields) && !empty($requiredFields)){
                        foreach ($requiredFields as $field) {
                            if(isset($options['ACCOUNT_FIELDS']) && in_array($field, $options['ACCOUNT_FIELDS']))
                                $allOther[$field] = isset($options['allOtherLabel']) ? $options['allOtherLabel'] : "ALL OTHER";
                            else if($field == 'PERFORMANCE_VAR')
                                $allOther['VAR'] = (isset($allOther[$tyField]) && isset($allOther[$lyField])) ? $allOther[$tyField]-$allOther[$lyField] : 0;
                            else if($field == 'PERFORMANCE_VAR_PER')
                                $allOther['VARPER'] = (isset($allOther[$tyField]) && isset($allOther[$lyField]) && $allOther[$lyField] > 0) ? (($allOther[$tyField] - $allOther[$lyField]) * 100) / $allOther[$lyField] : 0;
                            else if(isset($value[$field]) && is_numeric($value[$field]))
                                $allOther[$field] += $value[$field]*1;
                            else if(isset($value[$field]))
                                $allOther[$field] = $value[$field];
                        }
                    }
                } else {
                    if (is_array($requiredFields) && !empty($requiredFields)){
                        $temp = array();
                        foreach ($requiredFields as $field) {
                            if($field == 'PERFORMANCE_VAR')
                                $temp['VAR'] = $value[$tyField]-$value[$lyField];
                            else if($field == 'PERFORMANCE_VAR_PER')
                                $temp['VARPER'] = ($value[$lyField] > 0) ? (($value[$tyField] - $value[$lyField]) * 100) / $value[$lyField] : 0;
                            else if(isset($value[$field]) && is_numeric($value[$field]))
                                $temp[$field] = $value[$field]*1;
                            else if(isset($value[$field]))
                                $temp[$field] = $value[$field];
                        }

                        $gridData[] = $temp;
                    } else {
                        $gridData[] = $value;
                    }
                }
            }
        }

        if(!empty($orderBy) ){
            $gridData = SortUtility::sort2DArray($gridData, $orderBy, SortTypes::$SORT_DESCENDING);
        }

        if ($addToAllOther) {
            $gridData[] = $allOther;
        }

        return $gridData;
    }

    // This function is used when having part is dynamic
    public function getRequiredDataWithHaving($gridArr = array(), $requiredFields = array(), $orderBy = "", $tyField = "" ,$lyField = ""){
        $gridData = array();
        $skipZeroVal = false;
        
        if(!empty($tyField) && !empty($lyField))
            $skipZeroVal = true;
        elseif(!empty($tyField))
            $skipTyZeroVal = true;
        elseif(!empty($lyField))
            $skipLyZeroVal = true;


        if(is_array($gridArr) && !empty($gridArr)){
            foreach ($gridArr as $key => $value) {
                
                if($skipZeroVal && $value[$tyField] == 0 && $value[$lyField] == 0 )
                    continue;

                // <= Means we are chking data > 0
                if ($skipTyZeroVal && $value[$tyField] <= 0)
                    continue;

                if ($skipLyZeroVal && $value[$lyField] == 0)
                    continue;

                if(is_array($requiredFields) && !empty($requiredFields)){
                    $temp = array();
                    foreach ($requiredFields as $field) {
                        if(isset($value[$field]))
                            $temp[$field] = $value[$field];
                    }

                    $gridData[] = $temp;
                } else {
                    $gridData[] = $value;
                }
            }
        }

        if(!empty($orderBy) ){
            $gridData = SortUtility::sort2DArray($gridData, $orderBy, SortTypes::$SORT_DESCENDING);
        }

        return $gridData;
    }    
    
    public function removeDataKeys($removeKeys) {
        if (is_array($removeKeys) && !empty($removeKeys)) {
            $this->queryVars->redisLink->select($this->queryVars->projectID); //set Database for redis data            
            $this->queryVars->redisLink->delete($removeKeys);
        }

        return true;
    }
    
    public function checkAndReadByQueryHashFromCache($query = '', $commonHashFunc = 'prepareQueryHash'  ) {

        if (!isset($this->isRedisCachingEnabled) || $this->isRedisCachingEnabled != true || empty($query))
            return false;

        $this->queryVars->redisLink->select($this->queryVars->projectID); //set Database for redis data
        $this->requestHash = $this->$commonHashFunc($query);

        if ($this->queryVars->redisLink->exists($this->requestHash))
            return json_decode($this->queryVars->redisLink->get($this->requestHash), true);

        return false;
    }    

    public function prepareQueryHash($query) {
        return md5($query);
    }

    public function skipRequestCaching(){
        return false;
    }
    
}
