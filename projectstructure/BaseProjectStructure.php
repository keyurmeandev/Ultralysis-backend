<?php

namespace projectstructure;

interface BaseProjectStructure {
	
	public function prepareMeasureSelectPart($settingVars, $queryVars);
	public function prepareMeasureSelect($settingVars, $queryVars, $measureIDs, $options);
	public function fetchAllTimeSelectionData($settingVars, &$jsonOutput, $extraParams);
	public function prepareTimeFilter($settingVars, $queryVars, $extraParams);
}