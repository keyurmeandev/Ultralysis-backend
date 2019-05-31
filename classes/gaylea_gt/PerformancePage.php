<?php

namespace classes\gaylea_gt;

class PerformancePage extends \classes\PerformancePage {

    public function __construct() {
		$this->lyHavingField = "LYVOLUME";
		$this->tyHavingField = "TYVOLUME";
        $this->gridNameArray = array();
        $this->jsonTagArray = array("gridStore", "gridGroup", "gridCategory", "gridBrand", "gridSKU");
        $this->jsonOutput = array();
    }
}
?> 