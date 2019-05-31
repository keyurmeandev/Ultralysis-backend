<?php

namespace projectsettings;
use projectstructure;

class FcanadaNielsen extends BaseGnielsen {

    public function __construct($aid, $projectID) {
        $this->maintable = "fcanada_nielsen";

        $this->territorytable = "";
            
        $this->clientID = "FCANADA";
        $this->aid = $aid;
        $this->projectID = $projectID;
        
        parent::__construct(array(1));

        $this->getClientAndRetailerLogo();
        
        $this->dateField = $this->maintable.".period";
        $this->timeSelectionUnit = "gnielsenFormat1";
        $this->projectStructureType = \projectstructure\ProjectStructureType::$MEASURE_AS_DIFF_COLUMN_AND_TYLY_AS_ROW;

        $this->timeSelectionStyleDDArray = array(
            ['data'=>"YTD", 'label'=>"YTD",             'jsonKey' => 'YTD', 'selected' => false],
            ['data'=>"4",   'label'=>"Latest 4 Weeks",  'jsonKey' => 'L4',  'selected' => false],
            ['data'=>"12",  'label'=>"Latest 12 Weeks", 'jsonKey' => 'L12', 'selected' => false],
            ['data'=>"52",  'label'=>"Latest 52 Weeks", 'jsonKey' => 'L52', 'selected' => true]
        );

        $this->filterMaster = '';
        $this->showContributionAnalysisPopup = false;
        $this->headerFooterSourceText = "AC Nielsen (INFANT & STAGE 3)";
        $this->configureClassVars();
        $this->dateField = $this->maintable.".period";

        // measure selection list
        $this->pageArray["MEASURE_SELECTION_LIST"] = array(
            array('measureID' => 1, 'jsonKey'=>'VALUE', 'measureName' => 'SALES ('.$this->currencySign.')', 'selected' => true),
        );

        //Item Ranking Report Page level condition 
        $this->pageArray["ItemsRankingReports"] = array(
            'itemsRankingReportsWithoutFlag' => [ 'Where' => $this->skutable.".UOM = 'C'", 
                                        'OrderBy'=>true, 
                                        'OrderByField'=>'',
                                        'OrderByType'=>'DESC'
                                      ],
            'segmentRankingReportGridData' => [ 'Where' => $this->controlFlagField." <> 0 AND ".$this->skutable.".UOM = 'L'", 
                                        'OrderBy'=>true, 
                                        'OrderByField'=>'CONTROLFLAG',
                                        'OrderByType'=>'ASC'
                                      ]
        );

        $this->pageArray["DrillDown"] = array(
            'ItemDrillDown' =>         [ 'Where' => ' AND '.$this->skutable.".UOM = 'C'" ],
            'ExtraColumns' =>          ['AVE_AC_DIST' => [
                                                //'FIELDS'=>'MAX('.$this->maintable.'.avg_ac_dist) AS AVE_AC_DIST', 
                                                'FIELDS'=>$this->maintable.'.avg_ac_dist',
                                                'TITLE'=>'AVE AC DIST',
                                                'TIME_RANGE' => 'TY',
                                                'DATATYPE'=>'number'
                                                ],
                                        'AVE_AC_DIST_LY' => [
                                                'FIELDS'=>$this->maintable.'.avg_ac_dist',
                                                'TITLE'=>'AVE AC DIST LY',
                                                'TIME_RANGE' => 'LY',
                                                'DATATYPE'=>'number'
                                                ],
                                        'AVE_AC_DIST_CHG' => [
                                                'TITLE'=>'AVE AC DIST CHG',
                                                'DATATYPE'=>'number'
                                            ],
                                        'SPPD' => [ 
                                                'FIELDS'=>'MAX('.$this->maintable.'.sppd) AS SPPD', 
                                                'TITLE'=>'SPPD',
                                                'DATATYPE'=>'number'
                                                ]
                                       ],
            'isShareChartActive' => false
        );
        
        $this->pageArray["RangeEfficiency"] = array(
            'ExtraColumns' =>          ['AVE_AC_DIST' => [
                                                'FIELDS'=>'MAX('.$this->maintable.'.avg_ac_dist) AS AVE_AC_DIST', 
                                                'TITLE'=>'AVE AC DIST',
                                                'DATATYPE'=>'number'
                                                ],
                                        ]
        );
        
        $this->pageArray["PerformanceFlashPage"] = array('Where' => ' AND '.$this->skutable.".UOM = 'C'", 
                                                         'fieldName' => $this->skutable.".UOM");
    }
    
	public function getMydateSelect($dateField, $withAggregate = true) {
		$dateFieldPart = explode('.', $dateField);
		$dateField = (count($dateFieldPart) > 1) ? $dateFieldPart[1] : $dateFieldPart[0];
		
		switch ($dateField) {
			case "period":
				$selectField = ($withAggregate) ? "MAX(".$this->maintable.".mydate) " : $this->maintable.".mydate ";
				break;
			case "mydate":
				$selectField = ($withAggregate) ? "MAX(".$this->maintable.".mydate) " : $this->maintable.".mydate ";
				break;
		}
		
		return $selectField;
	}
}
?>