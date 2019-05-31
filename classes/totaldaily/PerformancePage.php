<?php

namespace classes\totaldaily;

class PerformancePage extends \classes\PerformancePage {

    public function __construct() {
        $this->lyHavingField = "LYVALUE";
        $this->tyHavingField = "TYVALUE";

        $this->lineChartAllFunction     = 'LineChartDaysAllData';
        $this->lineChartAllPpFunction   = 'LineChartDaysAllData';
        
        $this->gridNameArray = array();
        $this->jsonTagArray = array("gridStore", "gridGroup", "gridCategory", "gridBrand", "gridSKU");
        $this->jsonOutput = array();
    }
}
?> 