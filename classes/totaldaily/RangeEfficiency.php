<?php

namespace classes\totaldaily;

use filters;

class RangeEfficiency extends \classes\RangeEfficiency {

    public function customSelectPart()
    {
        $countAccount = $this->settingVars->pageArray[$this->settingVars->pageName]["COUNT_ACCOUNT"];
        $countcolumn = !key_exists('ID', $this->settingVars->dataArray[$countAccount]) ? 
            $this->settingVars->dataArray[$countAccount]["NAME"] : $this->settingVars->dataArray[$countAccount]["ID"];

        // $this->customSelectPart = "SUM($countcolumn)/".$_REQUEST['timeFrame']." AS STORES ";
        if ($this->settingVars->getStoreCountType == "AVG")
            $this->customSelectPart = "SUM($countcolumn)/".(filters\timeFilter::$daysTimeframe)." AS STORES ";
        else
            $this->customSelectPart = $this->settingVars->getStoreCountType."($countcolumn) AS STORES ";
    }
}

?>