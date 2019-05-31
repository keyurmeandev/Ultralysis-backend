<?php
namespace classes\totaldaily;

use filters;

class MyPromoAnalysis extends \classes\MyPromoAnalysis{
	
	private $allDates;
	
    /**
     * Default gateway function, should be similar in all DATA CLASS
     * 
     * @param array $settingVars project settingsGateway variables
     *
     * @return Array
     */
	public function go($settingVars)
	{
		$this->initiate($settingVars); //INITIATE COMMON VARIABLES
		$this->customSelectPart();
		$this->queryPart = $this->getAll();  //PREPARE TABLE JOIN STRING USING this class getAll
		$this->aggregateSku = $_REQUEST['aggregateSku'];
		$name = $this->settingVars->dataArray[$_REQUEST["ACCOUNT"]]["NAME"];	
		
		$this->allDates = filters\timeFilter::getPeriodWithinRange(0, 0, $settingVars, false);
		if($_REQUEST['action']=='filterChange'){
		    //PREPARING PROMO,PRE-PROMO,POST-PROMO WEEK RANGE
		    $this->prepareWeekRanges();	    
		    //PREPARING CHART DATA
		    $this->promoProductLineChart($name);
		    //PREPARING GRID DATA
		    $this->promoTopGrid($name);
		}
		else
		{
			foreach($this->allDates as $key => $data)
			{
				$tmpArr['date'] = $data;
				$tmpArr['numdata'] = $key;
				$allDate[] = $tmpArr;
			}
			$this->jsonOutput["all_date"] = $allDate;
		}
		
		return $this->jsonOutput;
	}
	
	/**
     * customSelectPart()
     * It will prepare select and group by part for query
     *
     * @return void
     */
	public function customSelectPart()
	{
		$this->customSelectPart 	= $this->settingVars->dateperiod." AS DATE";
		$this->customGroupByPart	= "GROUP BY DATE ORDER BY DATE DESC";
		$this->getVar				= "DATE";
	}
	    
    /**
     * prepareWeekRanges()
     * It will prepare pre,post and current promo days ranges
     *
     * @return void
     */
	public function prepareWeekRanges() {
		$countWeek = array();
	
		$prePromoFrom   		= $_REQUEST['prePromoFrom'];
		$prePromoTo 			= $_REQUEST['prePromoTo'];
		$prePromoFrom = array_search($prePromoFrom, $this->allDates);
		$prePromoTo = array_search($prePromoTo, $this->allDates);
		$this->totalPrePromoWeek = $countWeek['PRE_PROMO'] = $prePromoFrom - $prePromoTo;
		filters\timeFilter::fetchPeriodWithinRange($prePromoFrom, (int)$countWeek['PRE_PROMO'], $this->settingVars);
		$this->prePromoTyWeekRange = filters\timeFilter::$mydateRange;
		
		$promoFrom 				= $_REQUEST['promoFrom'];
		$promoTo 				= $_REQUEST['promoTo'];
		$promoFrom = array_search($promoFrom, $this->allDates);
		$promoTo = array_search($promoTo, $this->allDates);
		$this->totalPromoWeek = $countWeek['PROMO'] = $promoFrom - $promoTo;
		filters\timeFilter::fetchPeriodWithinRange($promoFrom, $countWeek['PROMO'], $this->settingVars);
		$this->promoTyWeekRange = filters\timeFilter::$mydateRange;
		
		$postPromoFrom 			= $_REQUEST['postPromoFrom'];
		$postPromoTo 			= $_REQUEST['postPromoTo'];
		$postPromoFrom = array_search($postPromoFrom, $this->allDates);
		$postPromoTo = array_search($postPromoTo, $this->allDates);
		$this->totalPostPromoWeek = $countWeek['POST_PROMO'] = $postPromoFrom - $postPromoTo;			
		filters\timeFilter::fetchPeriodWithinRange($postPromoFrom, $countWeek['POST_PROMO'], $this->settingVars);	
		$this->postPromoTyWeekRange = filters\timeFilter::$mydateRange;
		
		$this->jsonOutput['CountWeek'] 	= $countWeek;
	}
	
}
?>