<?php
namespace config;

use projectstructure;

class MeasureConfigure {

    public static function prepareSelect($settingVars, $queryVars, $measureIDs, $options) {
        $projectStructureType = "projectstructure\\".$settingVars->projectStructureType;
        $structureClass = new $projectStructureType();
        $measureSelect = $structureClass->prepareMeasureSelect($settingVars, $queryVars, $measureIDs, $options);
        return $measureSelect;
    }
}

?>