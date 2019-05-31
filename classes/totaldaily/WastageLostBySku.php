<?php

namespace classes\totaldaily;

use filters;

class WastageLostBySku extends \classes\totalmults\WastageLostBySku {

	public function customSelectPart() {
        $this->customSelectPart = "SUM((CASE WHEN  " . filters\timeFilter::$tyWeekRange . " THEN 1 ELSE 0 END) *" . 
        	$this->ValueVolume . ") AS SALES, SUM(lostSalesVal) AS LOSTOPP ";
    }
}

?>