<?php

namespace classes\totaldaily;

use filters;
use db;

class SummaryPage extends \classes\SummaryPage {

    /** ***
     * Default gateway function, should be similar in all DATA CLASS
     * arguments:
     *  $settingVars [project settingsGateway variables]
     * @return $jsonOutput with all data that should be sent to client app
     * *** */

    public function go($settingVars) {
        $this->pageName = $settingVars->pageName = (empty($settingVars->pageName)) ? 'SummaryPage' : $settingVars->pageName; // set pageName in settingVars
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES
        
        $this->queryPart = $this->getAll(); //PREPARE TABLE JOINING STRINGS USING PARENT getAll

        $this->fetchConfig(); // Fetching filter configuration for page
        $this->getFeedNews();

        //ADVICED TO EXECUTE THE FOLLOWING FUNCTION ON TOP AS IT SETS TY_total and LY_total
        $this->totalSales();
        $this->top5Product();
		$this->jsonOutput['PerformanceInABox'] = $this->getTopPodsData(" AND agg3 <> 'SUB-CAT TOTAL' ");
		$this->jsonOutput['CategoryPerformanceInABox'] = $this->getTopPodsData(" AND agg3 = 'SUB-CAT TOTAL' ");

        return $this->jsonOutput;
    }

    private function getFeedNews() {

        // THIS FUNCTION IS USED TO REMOVE THE <![CDATA FROM TITLE & DESCRIPTION
        $newsFeeds = simplexml_load_file("http://www.j-sainsbury.co.uk/extras/rss/latest-press-releases/", "SimpleXMLElement", LIBXML_NOCDATA);
        foreach ($newsFeeds->channel->item as $item) 
		{
			$tmpItem['description'] = (string)$item->description;
			$tmpItem['guid'] = (string)$item->guid;
			$tmpItem['link'] = (string)$item->link;
			$tmpItem['pubDate'] = date("D, d M Y",strtotime((string)$item->pubDate));
			$tmpItem['title'] = (string)$item->title;
			$itemRes[] = $tmpItem;
        }
		$this->jsonOutput["NEWS_FEED"] = $itemRes;
    }


    /*     * ***
     * COLLECTS TOTAL SALES POD DATA
     * SETS TWO STATIC VARIABLES [TY_total,LY_total] 
     * *** */

    public function totalSales() {
        $this->jsonOutput['TYEAR_SALES'] = $this->getTotalSales(" AND agg3 <> 'SUB-CAT TOTAL' ");
        $this->jsonOutput['CATEGORY_TYEAR_SALES'] = $this->getTotalSales(" AND agg3 = 'SUB-CAT TOTAL' ");
        
        $this->jsonOutput['PERFORMANCE_LIST'] = $this->getPerformanceList(" AND agg3 <> 'SUB-CAT TOTAL' ");
        $this->jsonOutput['CATEGORY_PERFORMANCE_LIST'] = $this->getPerformanceList(" AND agg3 = 'SUB-CAT TOTAL' ");
    }

    private function top5Product() {
        
        $this->jsonOutput['PERFORMANCE_TOP5_PRODUCT_LIST'] = $this->getProductGrid(" AND agg3 <> 'SUB-CAT TOTAL' ", "F1");
        $this->jsonOutput['CATEGORY_PERFORMANCE_TOP5_PRODUCT_LIST'] = $this->getProductGrid(" AND agg3 = 'SUB-CAT TOTAL' ", "F17");
    }
	
    private function getTotalSales($queryPart){
        $query = "SELECT SUM(" . $this->ValueVolume . ") AS TYEAR" .
                " FROM " . $this->settingVars->tablename . " " . $this->queryPart .
                " AND (" . filters\timeFilter::$tyWeekRange . ") " .
                " $queryPart " .
                "ORDER BY TYEAR DESC ";
        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
        return $result[0]["TYEAR"];
    }

    private function getPerformanceList($queryPart) {
        $query = "SELECT " . $this->settingVars->period . " AS MYDATE, SUM(" . $this->ValueVolume . " ) AS TYEAR" .
                " FROM " . $this->settingVars->tablename . " " . $this->queryPart .
                " AND (" . filters\timeFilter::$tyWeekRange . ") " .
                " $queryPart " .
                " GROUP BY MYDATE " .
                "ORDER BY MYDATE ASC ";
        //print $query; exit;
        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
        $tempArr = array();
        if (!empty($result))
		{
            foreach ($result as $data) 
			{
                $tempArr['TYEAR'] = $data['TYEAR'];
				$tempArr['MYDATE'] = date("dS M Y",strtotime($data['MYDATE']));
				$resultList[] = $tempArr;
            }
		}	
        return $resultList;
    }

    private function getProductGrid($queryPart, $accountName) {
		$name = $this->settingVars->dataArray[$accountName]['NAME'];

        $query = "SELECT ".$name." AS NAME " . 
                ",SUM((CASE WHEN  " . filters\timeFilter::$tyWeekRange . " THEN 1 ELSE 0 END) *" . $this->settingVars->ProjectValue . ") AS L28D_SALES " .
				((!empty(filters\timeFilter::$lyWeekRange)) ? ",SUM((CASE WHEN  " . filters\timeFilter::$lyWeekRange . " THEN 1 ELSE 0 END) *" . $this->settingVars->ProjectValue . ") AS P28D_SALES" : "") .
                " FROM  " . $this->settingVars->tablename . $this->queryPart . $queryPart . 
				" AND (" . filters\timeFilter::$tyWeekRange . " OR " . filters\timeFilter::$lyWeekRange . ") " . 
                " GROUP BY NAME ORDER BY L28D_SALES DESC";

		$result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
		
		$tmpArr = array();
		$L7D_SALES_SUM = $P7D_SALES_SUM = $VAR_SUM = 0;
        
        if(!empty($result))
        {
    		foreach($result as $key => $data)
    		{
    			if($key <= 4)
    			{
    				$tmpArr['NAME'] = $data['NAME'];
    				$tmpArr['L7D_SALES'] = $data['L28D_SALES'];
    				$tmpArr['P7D_SALES'] = $data['P28D_SALES'];
    				if($data['P28D_SALES'] > 0)
    					$tmpArr['VAR'] = number_format((($data['L28D_SALES']-$data['P28D_SALES'])/$data['P28D_SALES'])*100,1,'.','');
    				else
    					$tmpArr['VAR'] = 0;
    				$prodList[] = $tmpArr;
    			}
    			else
    			{
    				$L7D_SALES_SUM = $L7D_SALES_SUM + $data['L28D_SALES'];
    				$P7D_SALES_SUM = $P7D_SALES_SUM + $data['P28D_SALES'];
    			}			
    		}
    		
            if(count($prodList) > 4)
            {
                $tmpArr['NAME'] = "All Other";
                $tmpArr['L7D_SALES'] = $L7D_SALES_SUM;
                $tmpArr['P7D_SALES'] = $P7D_SALES_SUM;
                if($P7D_SALES_SUM > 0)
                    $VAR_SUM = (($L7D_SALES_SUM-$P7D_SALES_SUM)/$P7D_SALES_SUM)*100;        
                $tmpArr['VAR'] = number_format($VAR_SUM,1,'.','');
                $prodList[] = $tmpArr;
            }
		}
		
		return $prodList;
    }
	
	private function getTopPodsData($queryPart) {

        $queryParts = array();
		filters\timeFilter::fetchPeriodWithinRange(0, 7, $this->settingVars);
		$latest7Days = filters\timeFilter::$mydateRange;
        if (!empty($latest7Days))
            $queryParts[] = "SUM((CASE WHEN " . $latest7Days . " THEN 1 ELSE 0 END)* " . $this->ValueVolume . ") AS L7D_VALUE ";

		filters\timeFilter::fetchPeriodWithinRange(7, 7, $this->settingVars);
		$prev7Days = filters\timeFilter::$mydateRange;
        if (!empty($prev7Days))
            $queryParts[] = "SUM((CASE WHEN " . $prev7Days . " THEN 1 ELSE 0 END)* " . $this->ValueVolume . ") AS P7D_VALUE ";
		
		filters\timeFilter::fetchPeriodWithinRange(0, 14, $this->settingVars);
		$latest14Days = filters\timeFilter::$mydateRange;
        if (!empty($latest14Days))
            $queryParts[] = "SUM((CASE WHEN " . $latest14Days . " THEN 1 ELSE 0 END)* " . $this->ValueVolume . ") AS L14D_VALUE ";

		filters\timeFilter::fetchPeriodWithinRange(14, 14, $this->settingVars);
		$prev4Days = filters\timeFilter::$mydateRange;
        if (!empty($prev4Days))
            $queryParts[] = "SUM((CASE WHEN " . $prev4Days . " THEN 1 ELSE 0 END)* " . $this->ValueVolume . ") AS P14D_VALUE ";
		
		filters\timeFilter::fetchPeriodWithinRange(0, 21, $this->settingVars);
		$latest21Days = filters\timeFilter::$mydateRange;
        if (!empty($latest21Days))
            $queryParts[] = "SUM((CASE WHEN " . $latest21Days . " THEN 1 ELSE 0 END)* " . $this->ValueVolume . ") AS L21D_VALUE ";

		filters\timeFilter::fetchPeriodWithinRange(21, 21, $this->settingVars);
		$prev21Days = filters\timeFilter::$mydateRange;
        if (!empty($prev21Days))
            $queryParts[] = "SUM((CASE WHEN " . $prev21Days . " THEN 1 ELSE 0 END)* " . $this->ValueVolume . ") AS P21D_VALUE ";
		
		filters\timeFilter::fetchPeriodWithinRange(0, 28, $this->settingVars);
		$latest28Days = filters\timeFilter::$mydateRange;
        if (!empty($latest28Days))
            $queryParts[] = "SUM((CASE WHEN " . $latest28Days . " THEN 1 ELSE 0 END)* " . $this->ValueVolume . ") AS L28D_VALUE ";

		filters\timeFilter::fetchPeriodWithinRange(28, 28, $this->settingVars);
		$prev28Days = filters\timeFilter::$mydateRange;
        if (!empty($prev28Days))
            $queryParts[] = "SUM((CASE WHEN " . $prev28Days . " THEN 1 ELSE 0 END)* " . $this->ValueVolume . ") AS P28D_VALUE ";

		$days28latest = filters\timeFilter::getPeriodWithinRange(0, 28, $this->settingVars);
		$days28past = filters\timeFilter::getPeriodWithinRange(28, 28, $this->settingVars);
		
		$query = "SELECT " . implode(",", $queryParts) .
                "FROM " . $this->settingVars->tablename . $this->queryPart .
				" AND mydate IN ('" . implode("','", array_merge($days28latest,$days28past)) . "') ". 
                $queryPart;
		//echo $query;exit;
        $result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
		
		if (is_array($result) && !empty($result)) 
		{
			$row = array();
            $data = $result[0];
			
            $row['L7D_VALUE'] = number_format($data['L7D_VALUE'], 0);
            $row['L7D_P7D_VALUE'] = number_format(($data['L7D_VALUE'] - $data['P7D_VALUE']), 0);
			$row['L7D_P7D_VAR'] = ($data['P7D_VALUE'] > 0) ? number_format((($data['L7D_VALUE'] - $data['P7D_VALUE']) / $data['P7D_VALUE']) * 100, 1, '.', '') : "0";
            $row['P7D_VALUE'] = number_format($data['P7D_VALUE'], 0);
			
            $row['L14D_VALUE'] = number_format($data['L14D_VALUE'], 0);
            $row['LD_PD_14_VALUE'] = number_format(($data['L14D_VALUE'] - $data['P14D_VALUE']), 0);
            $row['LD_PD_14_VAR'] = ($data['P14D_VALUE'] > 0) ? number_format((($data['L14D_VALUE'] - $data['P14D_VALUE']) / $data['P14D_VALUE']) * 100, 1, '.', '') : "0";
            $row['P14D_VALUE'] = number_format($data['P14D_VALUE'], 0);
			
            $row['L21D_VALUE'] = number_format($data['L21D_VALUE'], 0);
            $row['LD_PD_21_VALUE'] = number_format(($data['L21D_VALUE'] - $data['P21D_VALUE']), 0);
            $row['LD_PD_21_VAR'] = ($data['P21D_VALUE'] > 0) ? number_format((($data['L21D_VALUE'] - $data['P21D_VALUE']) / $data['P21D_VALUE']) * 100, 1, '.', '') : "0";
            $row['P21D_VALUE'] = number_format($data['P21D_VALUE'], 0);			

            $row['L28D_VALUE'] = number_format($data['L28D_VALUE'], 0);
            $row['LD_PD_28_VALUE'] = number_format(($data['L28D_VALUE'] - $data['P28D_VALUE']), 0);
            $row['LD_PD_28_VAR'] = ($data['P28D_VALUE'] > 0) ? number_format((($data['L28D_VALUE'] - $data['P28D_VALUE']) / $data['P28D_VALUE']) * 100, 1, '.', '') : "0";
            $row['P28D_VALUE'] = number_format($data['P28D_VALUE'], 0);			
			
			$row['L7D_P7D_VALUE'] = ($row['L7D_P7D_VALUE'] > 0) ? '+' . $row['L7D_P7D_VALUE'] . '' : $row['L7D_P7D_VALUE'];
			$row['L7D_P7D_VAR'] = ($row['L7D_P7D_VAR'] > 0) ? '+' . $row['L7D_P7D_VAR'] . '' : $row['L7D_P7D_VAR'];
			
			$row['LD_PD_14_VALUE'] = ($row['LD_PD_14_VALUE'] > 0) ? '+' . $row['LD_PD_14_VALUE'] . '' : $row['LD_PD_14_VALUE'];
			$row['LD_PD_14_VAR'] = ($row['LD_PD_14_VAR'] > 0) ? '+' . $row['LD_PD_14_VAR'] . '' : $row['LD_PD_14_VAR'];

			$row['LD_PD_21_VALUE'] = ($row['LD_PD_21_VALUE'] > 0) ? '+' . $row['LD_PD_21_VALUE'] . '' : $row['LD_PD_21_VALUE'];
			$row['LD_PD_21_VAR'] = ($row['LD_PD_21_VAR'] > 0) ? '+' . $row['LD_PD_21_VAR'] . '' : $row['LD_PD_21_VAR'];

			$row['LD_PD_28_VALUE'] = ($row['LD_PD_28_VALUE'] > 0) ? '+' . $row['LD_PD_28_VALUE'] . '' : $row['LD_PD_28_VALUE'];
			$row['LD_PD_28_VAR'] = ($row['LD_PD_28_VAR'] > 0) ? '+' . $row['LD_PD_28_VAR'] . '' : $row['LD_PD_28_VAR'];
			
			$arr[] = $row;		
			//$this->jsonOutput['PerformanceInABox'] = $arr;
			return $arr;
		}
	}
}
?>