#!/usr/bin/perl -w

use strict;
use warnings;

use DBI;
use DBD::mysql;
use Excel::Writer::XLSX;
use Text::CSV;
use Data::Dumper;
use Time::Piece;
use POSIX 'floor';
use utf8;
use Redis;
use Encode;
use JSON;

my $data_source = 'DBI:mysql:database=canadalcl:mysql_read_default_file=/usr/local/Calpont/mysql/my.cnf';
my $username = 'ultralysis';
my $auth = 'retaillink';
my %attr = ();

# Perl script argument details 
# ARGV[0] => New Excel File Path
# ARGV[1] => Query
# ARGV[2] => thisYear
# ARGV[3] => lastYear
# ARGV[4] => Active Worksheet Field Name
# ARGV[5] => Active Worksheet Name
# ARGV[6] => Selected TY Measure Filter
# ARGV[7] => Selected LY Measure Filter
# ARGV[8] => All Applyed Filters 
# ARGV[9] => Logo
# ARGV[10] => TY START DATE
# ARGV[11] => TY END DATE
# ARGV[12] => HASH FOR THE Exclude Non Current Customer Data
# ARGV[13] => project ID
# ARGV[14] => Redis connection server
# ARGV[15] => Redis connection password
# ARGV[16] => Aggregate selection
# ARGV[17] => HASH FOR THE All Seasonal Hard Stop Dates Hash Key

my $excludeNonCurrentCustomerDataHash = $ARGV[12];
my $projectID      = $ARGV[13];
my $redisServer    = $ARGV[14];
my $redisPassword  = $ARGV[15];
my $redis = Redis->new( server => $redisServer, debug => 0, password => $redisPassword );
$redis->select($projectID);

my $aggregateSelection      = $ARGV[16];
my $aggregateDateFormat     = "%a-%d-%b";
my $aggregateDateHeadFormat = "%Y-%m-%d";
if(defined $aggregateSelection and $aggregateSelection ne ''){
    if($aggregateSelection eq 'weeks'){
        #$aggregateDateFormat = '%W-%b';
        #$aggregateDateHeadFormat = "%Y-%m-%W";
        $aggregateDateFormat = '%W';
        $aggregateDateHeadFormat = "%Y-%W";
    }
    elsif($aggregateSelection eq 'months'){
        $aggregateDateFormat = '%B';
        $aggregateDateHeadFormat = "%Y-%m";
    }
}else{
    $aggregateSelection = 'days';
}

my $havingTYValue  = $ARGV[6];
my $havingLYValue  = $ARGV[7];
my $dbh = DBI->connect($data_source, $username, $auth, \%attr);

my $tyFromDate = $ARGV[10];
my $tyToDate   = $ARGV[11];

# my $queryDateRange = 'SELECT ADDDATE("'.$tyFromDate.'", INTERVAL @i:=@i+1 DAY) AS DAY
#             FROM (
#                 SELECT a.a
#                     FROM (SELECT 0 AS a UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) AS a
#                 CROSS JOIN (SELECT 0 AS a UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) AS b
#                 CROSS JOIN (SELECT 0 AS a UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) AS c
#             ) a
#             JOIN (SELECT @i := -1) r1 
#         WHERE @i < DATEDIFF("'.$tyToDate.'", "'.$tyFromDate.'") ORDER BY DAY DESC';
# my $dateRangeResult = $dbh->prepare($queryDateRange);
# $dateRangeResult->execute();

my @allSeasonalHardStopDatesHash = split /##/, $ARGV[17];
my $allSeasonalHardStopDatesHashTy = $allSeasonalHardStopDatesHash[0];
my $allSeasonalHardStopDatesHashLy = $allSeasonalHardStopDatesHash[1];
my $ashsdh = $redis->get('time_filter_data');
my $allTimeFilterData = JSON->new->utf8->decode($ashsdh);
my $allSeasonalHardStopDatesTy = JSON->new->utf8->decode($allTimeFilterData->{$allSeasonalHardStopDatesHashTy});
my $allSeasonalHardStopDatesLy = JSON->new->utf8->decode($allTimeFilterData->{$allSeasonalHardStopDatesHashLy});

# my $tyKeys = keys %allSeasonalHardStopDatesTy;
my $tyCnt = scalar @{$allSeasonalHardStopDatesTy};
my $lyCnt = scalar @{$allSeasonalHardStopDatesLy};

my %dateThisYear;
my %dateLastYear;
my $tydate;
my $lydate;

my $allSeasonalHardStopDates;
my $dataKey;

if ($tyCnt > $lyCnt) {
    $allSeasonalHardStopDates = $allSeasonalHardStopDatesTy;
    $tyToDate = $tyToDate;
    $dataKey = "TY";
} else {
    $allSeasonalHardStopDates = $allSeasonalHardStopDatesLy;
    my @tmpTyToDateSplit = split /-/, $tyToDate;
    my $lyToDate = ($tmpTyToDateSplit[0]-1)."-".$tmpTyToDateSplit[1]."-".$tmpTyToDateSplit[2];
    # my $lyToDate = Time::Piece->strptime($lyToDateTmp, "%Y-%m-%d");
    $tyToDate = $lyToDate;
    $dataKey = "LY";
}

# while(my $dateRangeArray = $dateRangeResult->fetchrow_hashref()) {
my $toDateStrptime = Time::Piece->strptime($tyToDate, "%Y-%m-%d");
for my $dateRangeArray (@{$allSeasonalHardStopDates}) {
    my $dtTyFormatedTmp = Time::Piece->strptime($dateRangeArray->{DAY}, "%Y-%m-%d");
    if($dtTyFormatedTmp <= $toDateStrptime){

        if ($aggregateSelection eq 'days') {
            my $tyDateHasError = 0; 
            my $lyDateHasError = 0;
            if ($dataKey eq "TY") {
                $tydate = $dateRangeArray->{DAY};
                my @tmpTyDateSplit = split /-/, $tydate;
                $lydate = ($tmpTyDateSplit[0]-1)."-".$tmpTyDateSplit[1]."-".$tmpTyDateSplit[2];
                
                my $t5 = Time::Piece->strptime($lydate, "%Y-%m-%d");
                my $lyMonth = $t5->strftime("%m");
                if ($lyMonth != $tmpTyDateSplit[1]) {
                    $lyDateHasError = 1;
                }
            } else {
                $lydate = $dateRangeArray->{DAY};
                my @tmplyDateSplit = split /-/, $lydate;
                $tydate = ($tmplyDateSplit[0]+1)."-".$tmplyDateSplit[1]."-".$tmplyDateSplit[2];

                my $t5 = Time::Piece->strptime($tydate, "%Y-%m-%d");
                my $tyMonth = $t5->strftime("%m");
                if ($tyMonth != $tmplyDateSplit[1]) {
                    $tyDateHasError = 1;
                }
            }

            if ($tyDateHasError) {
                $dateThisYear{$tydate} = "NULL";
            } else {
                my $dtTyFormatedTmp = Time::Piece->strptime($tydate, "%Y-%m-%d");
                my $dtTyFormated = $dtTyFormatedTmp->strftime($aggregateDateHeadFormat);
                if(defined $dateThisYear{$dtTyFormated}){
                    # already defined no change
                }else{
                    my $t1 = Time::Piece->strptime($tydate, "%Y-%m-%d");
                    $dateThisYear{$dtTyFormated} = $t1->strftime($aggregateDateFormat);
                }
            }

            if ($lyDateHasError) {
                $dateLastYear{$lydate} = "NULL";
            } else {
                my $dtLyFormatedTmp = Time::Piece->strptime($lydate, "%Y-%m-%d");
                my $dtLyFormated = $dtLyFormatedTmp->strftime($aggregateDateHeadFormat);
                if(defined $dateLastYear{$dtLyFormated}){
                    # already defined no change
                }else{
                    my $t2 = Time::Piece->strptime($lydate, "%Y-%m-%d");
                    $dateLastYear{$dtLyFormated} = $t2->strftime($aggregateDateFormat);
                }
            }

            # exit;
        } else {
            my $dtTyFormated = $dtTyFormatedTmp->strftime($aggregateDateHeadFormat);
            if(defined $dateThisYear{$dtTyFormated}){
                # already defined no change
            }else{
                my $t1 = Time::Piece->strptime($dateRangeArray->{DAY}, "%Y-%m-%d");
                $dateThisYear{$dtTyFormated} = $t1->strftime($aggregateDateFormat);
            }

            my @tmpDateSplit = split /-/, $dateRangeArray->{DAY};
            my $lyMyDate = ($tmpDateSplit[0]-1)."-".$tmpDateSplit[1]."-".$tmpDateSplit[2];
            my $dtLyFormatedTmp = Time::Piece->strptime($lyMyDate, "%Y-%m-%d");
            my $dtLyFormated = $dtLyFormatedTmp->strftime($aggregateDateHeadFormat);
            if(defined $dateLastYear{$dtLyFormated}){
                # already defined no change
            }else{
                my $t2 = Time::Piece->strptime($lyMyDate, "%Y-%m-%d");
                $dateLastYear{$dtLyFormated} = $t2->strftime($aggregateDateFormat);
            }
        }
    }
}

my $sql = $ARGV[1];
my $sth = $dbh->prepare($sql);
$sth->execute();

my @actXlsFldNmList = split /##/, $ARGV[4];
my @actXlsValNmList = split /##/, $ARGV[5];

my @allAppliedFiltersList = split /$$/, $ARGV[8];
my %matrix;

# Create a new Excel workbook
my $excelFilePath = $ARGV[0];
my $workbook = Excel::Writer::XLSX->new($excelFilePath);
my %dataPnameSum;

my @dateThisYearData = keys %dateThisYear;
my @dateLastYearData = keys %dateLastYear;

if($aggregateSelection eq 'days'){
    @dateThisYearData = array_date_sort(@dateThisYearData);
    @dateLastYearData = array_date_sort(@dateLastYearData);
}else{
    @dateThisYearData = array_date_sort_months(@dateThisYearData);
    @dateLastYearData = array_date_sort_months(@dateLastYearData);
}

while(my $array = $sth->fetchrow_hashref()) {
    #Adding LY records
    for my $flds (@actXlsFldNmList) {
        #Adding LY records
        if(defined $matrix{$flds}{$array->{$flds}}{$ARGV[3]}{$array->{FORMATED_DATE}}){
            $matrix{$flds}{$array->{$flds}}{$ARGV[3]}{$array->{FORMATED_DATE}} = $matrix{$flds}{$array->{$flds}}{$ARGV[3]}{$array->{FORMATED_DATE}} + $array->{$havingLYValue};
        }else{
            $matrix{$flds}{$array->{$flds}}{$ARGV[3]}{$array->{FORMATED_DATE}} = $array->{$havingLYValue};
        }

        #Adding TY records
        if(defined $matrix{$flds}{$array->{$flds}}{$ARGV[2]}{$array->{FORMATED_DATE}}){
            $matrix{$flds}{$array->{$flds}}{$ARGV[2]}{$array->{FORMATED_DATE}} = $matrix{$flds}{$array->{$flds}}{$ARGV[2]}{$array->{FORMATED_DATE}} + $array->{$havingTYValue};
        }
        else{
            $matrix{$flds}{$array->{$flds}}{$ARGV[2]}{$array->{FORMATED_DATE}} = $array->{$havingTYValue};
        }

        $dataPnameSum{$flds}{$array->{$flds}} += floor($array->{$havingTYValue});
    }
}

my %newMatrix; 
my %newDataPnameSum;
if(defined $excludeNonCurrentCustomerDataHash and $excludeNonCurrentCustomerDataHash ne ''){
    my $pmst = $redis->get($excludeNonCurrentCustomerDataHash);
    my $excludeNonCurrentCustomerData = JSON->new->utf8->decode($pmst);
    my %excNonCurCustData = %$excludeNonCurrentCustomerData;

    for my $flds (@actXlsFldNmList) {
        if(defined $excNonCurCustData{$flds}){
            my @excludeNonCurrDataList = @{$excNonCurCustData{$flds}};
            for my $exldNCudKey (@excludeNonCurrDataList) {
                if(defined $matrix{$flds}{$exldNCudKey}){
                    $newMatrix{$flds}{$exldNCudKey} = \%{$matrix{$flds}{$exldNCudKey}};
                }
                if(defined $dataPnameSum{$flds}{$exldNCudKey}){
                    $newDataPnameSum{$flds}{$exldNCudKey} = $dataPnameSum{$flds}{$exldNCudKey};
                }
            }
        }
    }
undef %matrix;
undef %dataPnameSum;

%matrix = %newMatrix;
%dataPnameSum = %newDataPnameSum;
}

my $cnti = 0;
for my $flds (@actXlsFldNmList) {
    my $totalColStPos = 0; 

    my $worksheet = $workbook->add_worksheet($actXlsValNmList[$cnti]);

    my $totalColCnt = scalar @dateLastYearData;
    $totalColCnt = $totalColCnt+1;

    my $row = 0;
    my $colStart = 0;

    $worksheet->insert_image('A1', $ARGV[9], 1, 0);
    $worksheet->set_row($row, 35);

    $row = 1;
    my $formatLogoBg = $workbook->add_format();
    $formatLogoBg->set_bg_color('#0e0e0e');
    $worksheet->merge_range($row, 0, $row, $totalColCnt, '', $formatLogoBg);
    $worksheet->set_row($row, 5);

    #Start Adding Filters details to the worksheet
    $row = $row+1;
    my $formatFilters = $workbook->add_format();
    $formatFilters->set_align('vcenter');
    $formatFilters->set_bold();
    $formatFilters->set_text_wrap();
    $formatFilters->set_size(10);
    
    for my $appliedFlts (@allAppliedFiltersList) {
        my @actFlt = split /##/, $appliedFlts;
        $worksheet->write($row, $colStart, $actFlt[0], $formatFilters);
        $worksheet->merge_range($row, $colStart+2, $row, 12,  decode('utf8',$actFlt[1]), $formatFilters);
        $worksheet->set_row($row, 30);
    $row++;
    }
    #End Adding Filters details to the worksheet
    
    # Add a worksheet
    $row = $row+1;
    $colStart = 2;
    my $format = $workbook->add_format();
    $format->set_bg_color('#5590DC');
    $format->set_align('vcenter');
    $format->set_bold();
    $format->set_color('white');

    $worksheet->merge_range($row, 0, $row+1, 0, $actXlsValNmList[$cnti].' SUMMARY', $format);
    $worksheet->set_column(0, 0, 35);

    $worksheet->freeze_panes($row+2,2);

    $format = $workbook->add_format();
    $format->set_align('vcenter');
    $format->set_bold();
    $format->set_size(11);
    $worksheet->write($row, 1, $ARGV[3], $format);

    my $fmat = $workbook->add_format();
    $fmat->set_align('vcenter');
    $fmat->set_bold();
    $fmat->set_size(9);
    for my $lyDate (@dateLastYearData) {
        $worksheet->write($row, $colStart, $dateLastYear{$lyDate}, $fmat);
        $colStart++;
    }

    $row = $row+1;
    $colStart = 2;
    $worksheet->write($row, 1, $ARGV[2], $format);
    for my $tyDate (@dateThisYearData) {
        $worksheet->write($row, $colStart, $dateThisYear{$tyDate}, $fmat);
        $colStart++;
    }

    $row = $row+2;
    # [START] GETTING THE VARS FOR THE CALCULATING THT TOTAL COLUMNS
    $totalColStPos = $row;
    my %tyColmnsN;
    my %lyColmnsN;
    $row = $row+8;
    # [END] GETTING THE VARS FOR THE CALCULATING THT TOTAL COLUMNS

    $colStart = 0;
    my @dataKeys;

    my $numFormat = $workbook->add_format();
    $numFormat->set_num_format('#,##0');
    $numFormat->set_size(10);

    my $numFormatCum = $workbook->add_format();
    $numFormatCum->set_num_format('#,##0');
    $numFormatCum->set_bg_color('#F7F7F7');
    $numFormatCum->set_size(10);

    my $perFormat = $workbook->add_format();
    $perFormat->set_num_format('0.0%');
    $perFormat->set_color('#42659C');

    #%newMatrix = %{$matrix{$flds}};
    my $hdprint = 0;

    my @sorted = sort { $dataPnameSum{$flds}{$b} <=> $dataPnameSum{$flds}{$a} } keys %{$dataPnameSum{$flds}};
    foreach my $key (@sorted) {
        my $keypname = $key;
        my $valdatayear = \%{$matrix{$flds}{$key}};

        #}
        #while ((my $keypname, my $valdatayear) = each (%newMatrix)) {
        my $formatH = $workbook->add_format();
        $formatH->set_bg_color('#DAEEF3');
        $formatH->set_align('left');
        $formatH->set_size(12);
        $formatH->set_bold();
        my $tmpcol = $row++;
        $worksheet->merge_range($tmpcol, $colStart, $tmpcol, $totalColCnt, $keypname, $formatH);

        $colStart = $colStart + 1;
        my %dataCumulativeArr;
        my @dataYOYPer;
        my @thisYear;
        my @lastYear;
        my @thisYearCum;
        my @lastYearCum;

            if($hdprint==0){
                my $formatHd = $workbook->add_format();
                $formatHd->set_align('vcenter');
                $formatHd->set_bold();
                $formatHd->set_size(10);
                $worksheet->merge_range($row, 0, $row+2, 0, 'Daily Sales',$formatHd);
                $hdprint = 1;
            }

            #[START] Print records based on the Last Year
                my $keydatayear = $ARGV[3];
                my $valdate = $valdatayear->{$ARGV[3]};
                my $nF = $workbook->add_format();
                $nF->set_size(10);
                    $worksheet->write($row, $colStart, $keydatayear,$nF);
                    #@dataKeys = keys %$valdate;
                    #@dataKeys = array_date_sort(@dataKeys);
                    @dataKeys = @dateLastYearData;
                    my @dataCumu;
                    my $cumulD = 0;

                    for my $cDate (@dataKeys) {

                        my $lyformateddt;
                        if($aggregateSelection eq 'days'){
                            my @tmpcDateSplit = split /-/, $cDate;
                            my $cMonth = $tmpcDateSplit[1];
                            $cMonth =~ s/^0//;
                            my $cDay = $tmpcDateSplit[2];
                            $cDay =~ s/^0//;

                            my $cfdt = Time::Piece->strptime($cDate, "%Y-%m-%d");
                            if ($cMonth != $cfdt->strftime("%-m")) {
                                $lyformateddt = $cDay."-".$cMonth;
                            } else {
                                $lyformateddt = $cfdt->strftime("%-e-%-m");
                            }
                        }else{
                            $lyformateddt = $dateLastYear{$cDate};
                        }

                        $colStart = $colStart+1;
                        my $cdtVal = 0;
                        if(defined $matrix{$flds}{$keypname}{$keydatayear}{$lyformateddt}){
                            $cdtVal = $matrix{$flds}{$keypname}{$keydatayear}{$lyformateddt};
                        }
                        $worksheet->write($row, $colStart, $cdtVal, $numFormat);
                        $cumulD = $cumulD + $cdtVal;
                        push(@dataCumu, $cumulD);
                        push(@lastYear, $cdtVal);

                        if(defined $lyColmnsN{$cDate}){
                            $lyColmnsN{$cDate} = $lyColmnsN{$cDate} + $cdtVal;
                        }else{
                            $lyColmnsN{$cDate} = $cdtVal;
                        }
                    }
                    $dataCumulativeArr{$keydatayear} = \@dataCumu;
                    if($keydatayear == $ARGV[3]){
                        @lastYearCum = @dataCumu;
                    }
                    $row = $row+1;
                    $colStart = 1;
            #[END] Print records based on the Last Year

            #[START] Print records based on the This Year
                $keydatayear = $ARGV[2];
                $valdate = $valdatayear->{$ARGV[2]};
                    $worksheet->write($row, $colStart, $keydatayear, $nF);
                    #@dataKeys = keys %$valdate;
                    #@dataKeys = array_date_sort(@dataKeys);
                    @dataKeys = @dateThisYearData;
                    my @dataCumuTy;
                    $cumulD = 0;
                    for my $cDate (@dataKeys) {

                        my $tyformateddt;
                        if($aggregateSelection eq 'days'){
                            my @tmpcDateSplit = split /-/, $cDate;
                            my $cMonth = $tmpcDateSplit[1];
                            $cMonth =~ s/^0//;
                            my $cDay = $tmpcDateSplit[2];
                            $cDay =~ s/^0//;

                            my $cfdt = Time::Piece->strptime($cDate, "%Y-%m-%d");
                            if ($cMonth != $cfdt->strftime("%-m")) {
                                $tyformateddt = $cDay."-".$cMonth;
                            } else {
                                $tyformateddt = $cfdt->strftime("%-e-%-m");
                            }

                            # my $cfdt = Time::Piece->strptime($cDate, "%Y-%m-%d");
                            # $tyformateddt = $cfdt->strftime("%-e-%-m");
                        }else{
                            $tyformateddt = $dateThisYear{$cDate};
                        }
                        
                        $colStart = $colStart+1;
                        my $cdtVal = 0;
                        if(defined $matrix{$flds}{$keypname}{$keydatayear}{$tyformateddt}){
                            $cdtVal = $matrix{$flds}{$keypname}{$keydatayear}{$tyformateddt};
                        }
                        $worksheet->write($row, $colStart, $cdtVal, $numFormat);
                        $cumulD = $cumulD + $cdtVal;
                        push(@dataCumuTy, $cumulD);
                        if($keydatayear == $ARGV[2]){
                            push(@thisYear, $cdtVal);   
                        }

                        if(defined $tyColmnsN{$cDate}){
                            $tyColmnsN{$cDate} = $tyColmnsN{$cDate} + $cdtVal;
                        }else{
                            $tyColmnsN{$cDate} = $cdtVal;
                        }
                    }
                    $dataCumulativeArr{$keydatayear} = \@dataCumuTy;
                    if($keydatayear == $ARGV[2]){
                        @thisYearCum = @dataCumuTy;
                    }
                    $row = $row+1;
                    $colStart = 1;
            #[END] Print records based on the This Year
            
            for(my $index=0;$index<=$#thisYear;$index++){
                my $ty = $thisYear[$index];
                my $ly = $lastYear[$index];
                my $lytyVar = $ty - $ly;
                $lytyVar = ($ly != 0) ? (($lytyVar / $ly) ) : 0;
                push(@dataYOYPer, $lytyVar);
            }

            my $fmt = $workbook->add_format();
            $fmt->set_color('#42659C');
            $fmt->set_bold();
            $fmt->set_size(10);
            $worksheet->write($row, 1, 'YOY% daily', $fmt);

            my $tmpColSt = 2;
            for my $yoyData (@dataYOYPer) {
                my $perFormatTmp = $workbook->add_format();
                   $perFormatTmp->set_num_format('0.0%');
                   $perFormatTmp->set_color('#42659C');
                   $perFormatTmp->set_bold();
                   $perFormatTmp->set_size(10);
                   if($yoyData == 0){
                   }elsif($yoyData < 0){
                        $perFormatTmp->set_bg_color('#FFCCCC');
                   }else{
                        $perFormatTmp->set_bg_color('#B2F4B3');
                   }
                $worksheet->write($row, $tmpColSt, $yoyData, $perFormatTmp);
                $tmpColSt++;
            }
            #$worksheet->write_row($row, 2, \@dataYOYPer, $perFormat);
            
            $row = $row+1;
            my $formatCumHd = $workbook->add_format();
            $formatCumHd->set_align('vcenter');
            $formatCumHd->set_bold();
            $formatCumHd->set_size(10);
            $worksheet->merge_range($row, 0, $row+2, 0, 'Cumulative Sales',$formatCumHd);
            
            #[START] Cumulative Print records based on the Last Year 
                $worksheet->write($row, 1, $ARGV[3], $nF);
                $worksheet->write_row($row, 2, $dataCumulativeArr{$ARGV[3]}, $numFormatCum);
                $row = $row+1;
            #[END] Cumulative Print records based on the Last Year
            #[START] Cumulative Print records based on the Last Year
                $worksheet->write($row, 1, $ARGV[2], $nF);
                $worksheet->write_row($row, 2, $dataCumulativeArr{$ARGV[2]}, $numFormatCum);
                $row = $row+1;
            #[END] Cumulative Print records based on the Last Year 

            my @dataYOYPerCum;
            for(my $index=0;$index<=$#thisYearCum;$index++){
                my $ty = $thisYearCum[$index];
                my $ly = $lastYearCum[$index];
                my $lytyVar = $ty - $ly;
                $lytyVar = ($ly != 0) ? (($lytyVar / $ly) ) : 0;
                push(@dataYOYPerCum, $lytyVar);
            }

            $worksheet->write($row, 1, 'YOY% cum.',$fmt);
            $tmpColSt = 2;
            for my $yoyDataCum (@dataYOYPerCum) {
                my $perFormatTmp = $workbook->add_format();
                   $perFormatTmp->set_num_format('0.0%');
                   $perFormatTmp->set_color('#42659C');
                   $perFormatTmp->set_bold();
                   $perFormatTmp->set_size(10);

                   if($yoyDataCum == 0){
                   }elsif($yoyDataCum < 0){
                        $perFormatTmp->set_bg_color('#FFCCCC');
                   }else{
                        $perFormatTmp->set_bg_color('#B2F4B3');
                   }
                $worksheet->write($row, $tmpColSt, $yoyDataCum, $perFormatTmp);
                $tmpColSt++;
            }
            #$worksheet->write_row($row, 2, \@dataYOYPerCum, $perFormat);
            $row = $row+1;
        $row = $row+1;
        $colStart = 0;
        $hdprint = 0;
    }

    # [START] PRINTING THE TOTAL COLUMNS
    $row = $totalColStPos;
    $colStart = 0;
    
    my $formatH = $workbook->add_format();
    $formatH->set_bg_color('#DAEEF3');
    $formatH->set_align('left');
    $formatH->set_size(12);
    $formatH->set_bold();
    $worksheet->merge_range($row, $colStart, $row, $totalColCnt, 'TOTAL', $formatH);

    $row = $row+1;
    my $formatHd = $workbook->add_format();
    $formatHd->set_align('vcenter');
    $formatHd->set_bold();
    $formatHd->set_size(10);
    $worksheet->merge_range($row, 0, $row+2, 0, 'Daily Sales',$formatHd);

    my @thisYear;
    my @lastYear;
    my @dataYOYPer;
    my @thisYearCum;
    my @lastYearCum;

    $colStart = 1;
    my $nF = $workbook->add_format();
    $nF->set_size(10);

    my @dataLyKeys = keys %lyColmnsN;
    if($aggregateSelection eq 'days'){
        @dataLyKeys = array_date_sort(@dataLyKeys);
    }else{
        @dataLyKeys = array_date_sort_months(@dataLyKeys);
    }
    $colStart = 1;
    $worksheet->write($row, $colStart, $ARGV[3],$nF);
    my @dataCumu;
    my $cumulD = 0;
    for my $cDate (@dataLyKeys) {
        $colStart = $colStart+1;
        $worksheet->write($row, $colStart, $lyColmnsN{$cDate}, $numFormat);
        push(@lastYear, $lyColmnsN{$cDate});
        $cumulD = $cumulD + $lyColmnsN{$cDate};
        push(@dataCumu, $cumulD);
    }
    
    my @dataTyKeys = keys %tyColmnsN;
    if($aggregateSelection eq 'days'){
        @dataTyKeys = array_date_sort(@dataTyKeys);
    }else{
        @dataTyKeys = array_date_sort_months(@dataTyKeys);
    }
    $colStart = 1;
    $row = $row+1;
    $worksheet->write($row, $colStart, $ARGV[2], $nF);
    my @dataCumuTy;
    $cumulD = 0;
    for my $cDate (@dataTyKeys) {
        $colStart = $colStart+1;
        $worksheet->write($row, $colStart, $tyColmnsN{$cDate}, $numFormat);
        
        push(@thisYear, $tyColmnsN{$cDate});

        $cumulD = $cumulD + $tyColmnsN{$cDate};
        push(@dataCumuTy, $cumulD);
    }

    for(my $index=0;$index<=$#thisYear;$index++){
        my $ty = $thisYear[$index];
        my $ly = $lastYear[$index];
        my $lytyVar = $ty - $ly;
        $lytyVar = ($ly != 0) ? (($lytyVar / $ly) ) : 0;
        push(@dataYOYPer, $lytyVar);
    }

    $row = $row+1;
    my $fmt = $workbook->add_format();
    $fmt->set_color('#42659C');
    $fmt->set_bold();
    $fmt->set_size(10);
    $worksheet->write($row, 1, 'YOY% daily', $fmt);
    $colStart = 2;
    for my $yoyData (@dataYOYPer) {
        my $perFormatTmp = $workbook->add_format();
           $perFormatTmp->set_num_format('0.0%');
           $perFormatTmp->set_color('#42659C');
           $perFormatTmp->set_bold();
           $perFormatTmp->set_size(10);

           if($yoyData == 0){
           }elsif($yoyData < 0){
                $perFormatTmp->set_bg_color('#FFCCCC');
           }else{
                $perFormatTmp->set_bg_color('#B2F4B3');
           }

        $worksheet->write($row, $colStart, $yoyData, $perFormatTmp);
        $colStart++;
    }

    $row = $row+1;
    my $formatCumHd = $workbook->add_format();
    $formatCumHd->set_align('vcenter');
    $formatCumHd->set_bold();
    $formatCumHd->set_size(10);
    $worksheet->merge_range($row, 0, $row+2, 0, 'Cumulative Sales',$formatCumHd);

    $worksheet->write($row, 1, $ARGV[3], $nF);
    $worksheet->write_row($row, 2, \@dataCumu, $numFormatCum);
    $row = $row+1;

    $worksheet->write($row, 1, $ARGV[2], $nF);
    $worksheet->write_row($row, 2, \@dataCumuTy, $numFormatCum);
    $row = $row+1;
    
    my @dataYOYPerCum;
    for(my $index=0;$index<=$#dataCumu;$index++){
        my $ly = $dataCumu[$index];
        my $ty = $dataCumuTy[$index];
        my $lytyVar = $ty - $ly;
        $lytyVar = ($ly != 0) ? (($lytyVar / $ly) ) : 0;
        push(@dataYOYPerCum, $lytyVar);
    }

    $worksheet->write($row, 1, 'YOY% cum.',$fmt);
    $colStart = 2;
    for my $yoyDataCum (@dataYOYPerCum) {
        my $perFormatTmp = $workbook->add_format();
           $perFormatTmp->set_num_format('0.0%');
           $perFormatTmp->set_color('#42659C');
           $perFormatTmp->set_bold();
           $perFormatTmp->set_size(10);

           if($yoyDataCum == 0){
           }elsif($yoyDataCum < 0){
                $perFormatTmp->set_bg_color('#FFCCCC');
           }else{
                $perFormatTmp->set_bg_color('#B2F4B3');
           }
        $worksheet->write($row, $colStart, $yoyDataCum, $perFormatTmp);
        $colStart++;
    }
    # [END] PRINTING THE TOTAL COLUMNS

$cnti = $cnti+1;
}

$dbh->disconnect;
sub array_date_sort {
    my (@arr) = @_;
    @arr =
         map  { $_->[0] }
         sort { $a->[1] <=> $b->[1] }
         map  {
           my($y,$m,$d) = /^(\d+)-(\d+)-(\d+)/;
           [ $_, sprintf "20%d%02d%02d", $y, $m, $d ]
         } (@arr);

    return @arr;
}

sub array_date_sort_months {
    my (@arr) = @_;
    @arr =
         map  { $_->[0] }
         sort { $a->[1] <=> $b->[1] }
         map  {
           my($y,$m) = /^(\d+)-(\d+)/;
           [ $_, sprintf "20%d%02d", $y, $m ]
         } (@arr);

    return @arr;
}