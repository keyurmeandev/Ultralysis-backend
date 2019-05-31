<?php

namespace classes\totalmults;

use filters;
use db;
use config;

class WastageLostBySku extends config\UlConfig {
	
	public $customSelectPart;
	
    public function go($settingVars) {
        $this->initiate($settingVars); //INITIATE COMMON VARIABLES
		
		$this->customSelectPart();
		
        $this->queryPart = $this->getAll(); //USES OWN getAll FUNCTION

        $this->prepareGridData(); //ADDING TO OUTPUT

        return $this->jsonOutput;
    }
	
	public function customSelectPart()
    {
        $this->customSelectPart = "SUM(" . $this->ValueVolume . ") AS SALES, SUM(lostOpp) AS LOSTOPP ";
    }
	
    function prepareGridData() {
    	$skuId = $this->settingVars->dataArray['F1']["ID"];
    	$sku = $this->settingVars->dataArray['F1']["NAME"];

		$query = "SELECT " . $skuId . " AS ID, " .
			$sku." AS NAME, " .			
			"SUM(wastageTotalVal) AS WASTAGETOTALVAL, " .	
			$this->customSelectPart.
			"FROM  " . $this->settingVars->tablename . $this->queryPart . "  AND (" . filters\timeFilter::$tyWeekRange . ") " .
			"GROUP BY ID,NAME " .
			"ORDER BY SALES DESC ";

		//echo $query;exit;
		$result = $this->queryVars->queryHandler->runQuery($query, $this->queryVars->linkid, db\ResultTypes::$TYPE_OBJECT);
		if(isset($result) && !empty($result))
		{
			foreach ($result as $key => $data) {
				$temp = array(
					'SKUID' => $data['ID'],
					'SKU' => htmlspecialchars_decode($data['NAME']),
					'SALES' => number_format($data['SALES'], 0, '.', ''),
					'WASTAGETOTALVAL' => number_format($data['WASTAGETOTALVAL'], 0, '.', ''),
					'LOSTOPP' => number_format($data['LOSTOPP'], 0, '.', ''),
					'WASTAGETOTALVAL_PER' => ($data['SALES'] > 0) ? number_format(($data['WASTAGETOTALVAL']/$data['SALES'])*100, 2, '.', '') : 0,
					'LOSTOPP_PER' => ($data['SALES'] > 0) ? number_format(($data['LOSTOPP']/$data['SALES'])*100, 2, '.', '') : 0
				);
				$this->jsonOutput["gridValue"][] = $temp;
			}
		}
	}
}

?>