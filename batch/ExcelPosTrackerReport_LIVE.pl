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
use Encode;

my $data_source = 'DBI:mysql:database=canadalcl:mysql_read_default_file=/usr/local/Calpont/mysql/my.cnf';
my $username = 'ultralysis';
my $auth = 'retaillink';
my %attr = ();

# Perl script argument details 
# ARGV[0] => New Excel File Path
# ARGV[1] => Query
# ARGV[2] => Time Key Array 
# ARGV[3] => Time Value Array 
# ARGV[4] => Measure Filter Array
# ARGV[5] => Header Columns Name Array
# ARGV[6] => currencySign
# ARGV[7] => All Applyed Filters 
# ARGV[8] => Measure Color Code
# ARGV[9] => Logo

my @timekey = split /##/, $ARGV[2];
my @timeval = split /##/, $ARGV[3];
my @measureFilterSettings = split /##/, $ARGV[4];
my @headerColumns = split /##/, $ARGV[5];
my $currencySign = decode('utf8',$ARGV[6]);
my @measureFilterColorCode = split /$$/, decode('utf8',$ARGV[8]);
my $imagePath = $ARGV[9];

my @allAppliedFiltersList = split /$$/, $ARGV[7];

my $dbh = DBI->connect($data_source, $username, $auth, \%attr);
my $sql = $ARGV[1];
my $sth = $dbh->prepare($sql);
$sth->execute();

my %catData;
my %totalCatData;
my %subCatList;
my %catList;
my %measureColorCodeSetting;

for my $measureColorCode (@measureFilterColorCode) {
    my @actColorData = split /@@/, $measureColorCode;
    my @actColors = split /##/, $actColorData[1];
    $measureColorCodeSetting{$actColorData[0]}[0] = $actColors[0];
    $measureColorCodeSetting{$actColorData[0]}[1] = $actColors[1];
    $measureColorCodeSetting{$actColorData[0]}[2] = $actColors[2];
}

while(my $array = $sth->fetchrow_hashref()) {
    
    my %tmpData;
    $tmpData{'ACCOUNT'} = $array->{'ACCOUNT'};
    $tmpData{'isTotalRow'} = 0;

    # if(defined $subCatList{$array->{'CATCOLUMN'}}) {
    # }else{
    #     $subCatList{$array->{'CATCOLUMN'}} = [];
    # }

    for(my $index=0;$index<=$#timekey;$index++){
        my $tKey = $timekey[$index];
        my $tVal = $timeval[$index];
        for my $mFS (@measureFilterSettings) {
            # [START] PREPARING SUBCATLIST 
                $tmpData{$tVal.'_TY_'.$mFS}  = $array->{$tVal.'_TY_'.$mFS};
                $tmpData{$tVal.'_LY_'.$mFS}  = $array->{$tVal.'_LY_'.$mFS};
                $tmpData{$tVal.'_VAR_'.$mFS} = ($array->{$tVal.'_TY_'.$mFS} - $array->{$tVal.'_LY_'.$mFS});

                if($array->{$tVal.'_LY_'.$mFS} > 0){
                    $tmpData{$tVal.'_VARP_'.$mFS} = (($array->{$tVal.'_TY_'.$mFS} - $array->{$tVal.'_LY_'.$mFS}) / $array->{$tVal.'_LY_'.$mFS}) * 100;
                }
                else {
                    $tmpData{$tVal.'_VARP_'.$mFS} = 0;
                }
            # [END] PREPARING SUBCATLIST 

            # [START] PREPARING catData
                $catData{$array->{CATCOLUMN}}{'ACCOUNT'} = $array->{CATCOLUMN};
                $catData{$array->{CATCOLUMN}}{'isTotalRow'} = 0;
                $catData{$array->{CATCOLUMN}}{'isCategory'} = 1;
                $catData{$array->{CATCOLUMN}}{'colorCode'} = '#CEAF96';

                if(defined $catData{$array->{CATCOLUMN}}{$tVal.'_TY_'.$mFS}){
                    $catData{$array->{CATCOLUMN}}{$tVal.'_TY_'.$mFS} = str_replace(',', '', $catData{$array->{CATCOLUMN}}{$tVal.'_TY_'.$mFS});
                } else {
                    $catData{$array->{CATCOLUMN}}{$tVal.'_TY_'.$mFS} = 0;
                }
                $catData{$array->{CATCOLUMN}}{$tVal.'_TY_'.$mFS} += $array->{$tVal.'_TY_'.$mFS};    
                

                if(defined $catData{$array->{CATCOLUMN}}{$tVal.'_LY_'.$mFS}) {
                    $catData{$array->{CATCOLUMN}}{$tVal.'_LY_'.$mFS} = str_replace(',', '', $catData{$array->{CATCOLUMN}}{$tVal.'_LY_'.$mFS});
                } else {
                    $catData{$array->{CATCOLUMN}}{$tVal.'_LY_'.$mFS} = 0;
                }
                $catData{$array->{CATCOLUMN}}{$tVal.'_LY_'.$mFS} += $array->{$tVal.'_LY_'.$mFS};

                $catData{$array->{CATCOLUMN}}{$tVal.'_VAR_'.$mFS} = ($catData{$array->{CATCOLUMN}}{$tVal.'_TY_'.$mFS}-$catData{$array->{CATCOLUMN}}{$tVal.'_LY_'.$mFS});
                
                if($catData{$array->{CATCOLUMN}}{$tVal.'_LY_'.$mFS} > 0){
                    $catData{$array->{CATCOLUMN}}{$tVal.'_VARP_'.$mFS} = ((($catData{$array->{CATCOLUMN}}{$tVal.'_TY_'.$mFS} - $catData{$array->{CATCOLUMN}}{$tVal.'_LY_'.$mFS}) / $catData{$array->{CATCOLUMN}}{$tVal.'_LY_'.$mFS}) * 100);
                }
                else {
                    $catData{$array->{CATCOLUMN}}{$tVal.'_VARP_'.$mFS} = 0;
                }
                
                $catData{$array->{CATCOLUMN}}{$tVal.'_TY_'.$mFS}   = $catData{$array->{CATCOLUMN}}{$tVal.'_TY_'.$mFS};
                $catData{$array->{CATCOLUMN}}{$tVal.'_LY_'.$mFS}   = $catData{$array->{CATCOLUMN}}{$tVal.'_LY_'.$mFS};
                $catData{$array->{CATCOLUMN}}{$tVal.'_VAR_'.$mFS}  = $catData{$array->{CATCOLUMN}}{$tVal.'_VAR_'.$mFS};
                $catData{$array->{CATCOLUMN}}{$tVal.'_VARP_'.$mFS} = $catData{$array->{CATCOLUMN}}{$tVal.'_VARP_'.$mFS};
            # [END] PREPARING catData

            # [START] PREPARING totalCatData
                $totalCatData{'ACCOUNT'} = "Total";
                $totalCatData{'isTotalRow'} = 1;

                if(defined $totalCatData{$tVal.'_TY_'.$mFS}){
                    $totalCatData{$tVal.'_TY_'.$mFS} = str_replace(',', '', $totalCatData{$tVal.'_TY_'.$mFS});
                } else {
                    $totalCatData{$tVal.'_TY_'.$mFS} = 0;
                }
                $totalCatData{$tVal.'_TY_'.$mFS} += $array->{$tVal.'_TY_'.$mFS};

                if(defined $totalCatData{$tVal.'_LY_'.$mFS}){
                    $totalCatData{$tVal.'_LY_'.$mFS} = str_replace(',', '', $totalCatData{$tVal.'_LY_'.$mFS});    
                } else {
                    $totalCatData{$tVal.'_LY_'.$mFS} = 0;
                }
                $totalCatData{$tVal.'_LY_'.$mFS} += $array->{$tVal.'_LY_'.$mFS};
                $totalCatData{$tVal.'_VAR_'.$mFS} = ($totalCatData{$tVal.'_TY_'.$mFS}-$totalCatData{$tVal.'_LY_'.$mFS});
                
                if($totalCatData{$tVal.'_LY_'.$mFS} > 0){
                    $totalCatData{$tVal.'_VARP_'.$mFS} = ((($totalCatData{$tVal.'_TY_'.$mFS}-$totalCatData{$tVal.'_LY_'.$mFS})/$totalCatData{$tVal.'_LY_'.$mFS})*100);
                } else {
                    $totalCatData{$tVal.'_VARP_'.$mFS} = 0;
                }
                
                $totalCatData{$tVal.'_TY_'.$mFS}    = $totalCatData{$tVal.'_TY_'.$mFS};
                $totalCatData{$tVal.'_LY_'.$mFS}    = $totalCatData{$tVal.'_LY_'.$mFS};
                $totalCatData{$tVal.'_VAR_'.$mFS}   = $totalCatData{$tVal.'_VAR_'.$mFS};
                $totalCatData{$tVal.'_VARP_'.$mFS}  = $totalCatData{$tVal.'_VARP_'.$mFS};
            # [END] PREPARING totalCatData
        }
    }
    push(@{$subCatList{$array->{'CATCOLUMN'}}},\%tmpData);
}

my $excelFilePath = $ARGV[0];
my $workbook = Excel::Writer::XLSX->new($excelFilePath);
my $worksheet = $workbook->add_worksheet('Excel Pos Tracker');

my $row = 0;
my $colStart = 0;

$worksheet->insert_image('A1', $imagePath, 1, 0);
$worksheet->set_row($row, 35);

$row = 1;
my $formatLogoBg = $workbook->add_format();
$formatLogoBg->set_bg_color('#0e0e0e');
$worksheet->merge_range($row, 0, $row, 50, '', $formatLogoBg);
$worksheet->set_row($row, 5);

$row = $row + 1;
$colStart = 0;

#[START] Adding Filters details to the worksheet
    $row = $row+1;
    my $formatFiltersTitle = $workbook->add_format();
    $formatFiltersTitle->set_align('vcenter');
    $formatFiltersTitle->set_bold();
    $formatFiltersTitle->set_text_wrap();
    $formatFiltersTitle->set_size(10);
    $formatFiltersTitle->set_bg_color('#CEAF96');

    my $formatFilters = $workbook->add_format();
    $formatFilters->set_align('vcenter');
    $formatFilters->set_bold();
    $formatFilters->set_text_wrap();
    $formatFilters->set_size(10);

    for my $appliedFlts (@allAppliedFiltersList) {
        my @actFlt = split /##/, $appliedFlts;
        $worksheet->write($row, $colStart, $actFlt[0], $formatFiltersTitle);
        $worksheet->merge_range($row, $colStart+1, $row, 12,  decode('utf8',$actFlt[1]), $formatFilters);
        $worksheet->set_row($row, 30);
    $row++;
    }
#[END] Adding Filters details to the worksheet
$worksheet->set_column(0, 0, 35);

$colStart = 1;

# [START] PRINTING THE HEADER COLUMNS
for my $mFS (@measureFilterSettings) {
    my $colH1Cnt = scalar @timekey;
    my $formatH1 = $workbook->add_format();
       if(defined $measureColorCodeSetting{$mFS}[0]){
            $formatH1->set_bg_color('#'.$measureColorCodeSetting{$mFS}[0]);
       }
       $formatH1->set_align('center');
       $formatH1->set_bold();

    $worksheet->merge_range($row, $colStart, $row, ($colStart+($colH1Cnt*4))-1, $mFS, $formatH1);
    my $cntr = 1;
    for(my $index=0;$index<=$#timekey;$index++){
        my $tKey = $timekey[$index];
        my $tVal = $timeval[$index];

        my $formatH2 = $workbook->add_format();
        $formatH2->set_align('center');
        if(defined $measureColorCodeSetting{$mFS}[1]){
            my $colorCd = $measureColorCodeSetting{$mFS}[1];
            if($cntr % 2 == 0){
               $colorCd = $measureColorCodeSetting{$mFS}[2]; 
            }
            $formatH2->set_bg_color('#'.$colorCd);
        }
        $worksheet->merge_range($row+1, $colStart, $row+1, $colStart+3, $headerColumns[$index], $formatH2);

        $worksheet->write($row+2, $colStart, 'TY', $formatH2);
        $colStart++;

        $worksheet->write($row+2, $colStart, 'LY', $formatH2);
        $colStart++;

        $worksheet->write($row+2, $colStart, '%', $formatH2);
        $colStart++;

        $worksheet->write($row+2, $colStart, $currencySign.' - DIFF', $formatH2);
        $colStart++;

    $cntr++;
    }
}

$row = $row + 4;
$colStart = 0;
# [END] PRINTING THE HEADER COLUMNS

foreach my $category (keys %catData) {
    foreach my $subCatData (@{$subCatList{$category}}) {
        $worksheet->write($row, $colStart, $subCatData->{'ACCOUNT'});
        $colStart++;
            for my $mFS (@measureFilterSettings) {
                for(my $index=0;$index<=$#timekey;$index++){
                    my $tKey = $timekey[$index];
                    my $tVal = $timeval[$index];

                    # TY FORMAT
                    my $formatTY = $workbook->add_format();
                    $formatTY->set_align('center');
                    $formatTY->set_num_format('#,##0');
                    if($subCatData->{$tVal.'_TY_'.$mFS} < 0){
                        $formatTY->set_color('#FF0000');
                    }
                    $worksheet->write($row, $colStart, $subCatData->{$tVal.'_TY_'.$mFS}, $formatTY);
                    $colStart++;

                    # LY FORMAT
                    my $formatLY = $workbook->add_format();
                    $formatLY->set_align('center');
                    $formatLY->set_num_format('#,##0');
                    if($subCatData->{$tVal.'_LY_'.$mFS} < 0){
                        $formatLY->set_color('#FF0000');
                    }
                    $worksheet->write($row, $colStart, $subCatData->{$tVal.'_LY_'.$mFS}, $formatLY);
                    $colStart++;

                    # VARP FORMAT
                    my $formatVARP = $workbook->add_format();
                    $formatVARP->set_align('center');
                    $formatVARP->set_num_format('#,##0.0');
                    if($subCatData->{$tVal.'_VARP_'.$mFS} < 0){
                        $formatVARP->set_color('#FF0000');
                    }
                    $worksheet->write($row, $colStart, $subCatData->{$tVal.'_VARP_'.$mFS}, $formatVARP);
                    $colStart++;

                    # VAR FORMAT
                    my $formatVAR = $workbook->add_format();
                    $formatVAR->set_align('center');
                    $formatVAR->set_num_format('#,##0');
                    if($subCatData->{$tVal.'_VAR_'.$mFS} < 0){
                        $formatVAR->set_color('#FF0000');
                    }
                    $worksheet->write($row, $colStart, $subCatData->{$tVal.'_VAR_'.$mFS}, $formatVAR);
                    $colStart++;
                }
            }
        $row++;
        $colStart = 0;
    }
    
    # [START] PRINTING THE MAIN CATEGORY DATA 
            my $formatCategory = $workbook->add_format();
            $formatCategory->set_bg_color($catData{$category}->{'colorCode'});
            $formatCategory->set_bold();
            #$formatCategory->set_align('vcenter');
            #$formatCategory->set_color('black');

            $worksheet->write($row, $colStart, $catData{$category}->{'ACCOUNT'}, $formatCategory);
            $colStart++;
            for my $mFS (@measureFilterSettings) {
                for(my $index=0;$index<=$#timekey;$index++){
                    my $tKey = $timekey[$index];
                    my $tVal = $timeval[$index];

                    # TY FORMAT
                    my $formatCatTY = $workbook->add_format();
                    $formatCatTY->set_align('center');
                    $formatCatTY->set_num_format('#,##0');
                    $formatCatTY->set_bg_color($catData{$category}->{'colorCode'});
                    $formatCatTY->set_bold();
                    if($catData{$category}->{$tVal.'_TY_'.$mFS} < 0){
                        $formatCatTY->set_color('#FF0000');
                    }
                    $worksheet->write($row, $colStart, $catData{$category}->{$tVal.'_TY_'.$mFS}, $formatCatTY);
                    $colStart++;

                    # LY FORMAT
                    my $formatCatLY = $workbook->add_format();
                    $formatCatLY->set_align('center');
                    $formatCatLY->set_num_format('#,##0');
                    $formatCatLY->set_bg_color($catData{$category}->{'colorCode'});
                    $formatCatLY->set_bold();
                    if($catData{$category}->{$tVal.'_LY_'.$mFS} < 0){
                        $formatCatLY->set_color('#FF0000');
                    }
                    $worksheet->write($row, $colStart, $catData{$category}->{$tVal.'_LY_'.$mFS}, $formatCatLY);
                    $colStart++;

                    # VARP FORMAT
                    my $formatCatVARP = $workbook->add_format();
                    $formatCatVARP->set_align('center');
                    $formatCatVARP->set_num_format('#,##0.0');
                    $formatCatVARP->set_bg_color($catData{$category}->{'colorCode'});
                    $formatCatVARP->set_bold();
                    if($catData{$category}->{$tVal.'_VARP_'.$mFS} < 0){
                        $formatCatVARP->set_color('#FF0000');
                    }
                    $worksheet->write($row, $colStart, $catData{$category}->{$tVal.'_VARP_'.$mFS}, $formatCatVARP);
                    $colStart++;

                    # VAR FORMAT
                    my $formatCatVAR = $workbook->add_format();
                    $formatCatVAR->set_align('center');
                    $formatCatVAR->set_num_format('#,##0');
                    $formatCatVAR->set_bg_color($catData{$category}->{'colorCode'});
                    $formatCatVAR->set_bold();
                    if($catData{$category}->{$tVal.'_VAR_'.$mFS} < 0){
                        $formatCatVAR->set_color('#FF0000');
                    }
                    $worksheet->write($row, $colStart, $catData{$category}->{$tVal.'_VAR_'.$mFS}, $formatCatVAR);
                    $colStart++;
                }
            }
        $row++;
        $colStart = 0;
    # [END] PRINTING THE MAIN CATEGORY DATA
}

# [START] PRINTING THE TOTAL COLUMNS
        my $formatTotalHead = $workbook->add_format();
        $formatTotalHead->set_bg_color('#FFA500');
        $formatTotalHead->set_bold();
        $worksheet->write($row, $colStart, $totalCatData{'ACCOUNT'}, $formatTotalHead);
        $colStart++;
        for my $mFS (@measureFilterSettings) {
            my $cntrTot = 1;
            for(my $index=0;$index<=$#timekey;$index++){
                my $tKey = $timekey[$index];
                my $tVal = $timeval[$index];

                # TY FORMAT
                my $formatTotalTY = $workbook->add_format();
                $formatTotalTY->set_align('center');
                if(defined $measureColorCodeSetting{$mFS}[1]){
                    my $colorCd = $measureColorCodeSetting{$mFS}[1];
                    if($cntrTot % 2 == 0){
                        $colorCd = $measureColorCodeSetting{$mFS}[2]; 
                    }
                    $formatTotalTY->set_bg_color('#'.$colorCd);
                }
                $formatTotalTY->set_num_format('#,##0');
                if($totalCatData{$tVal.'_TY_'.$mFS} < 0){
                    $formatTotalTY->set_color('#FF0000');
                }
                $worksheet->write($row, $colStart, $totalCatData{$tVal.'_TY_'.$mFS}, $formatTotalTY);
                $colStart++;

                # LY FORMAT
                my $formatTotalLY = $workbook->add_format();
                $formatTotalLY->set_align('center');
                if(defined $measureColorCodeSetting{$mFS}[1]){
                    my $colorCd = $measureColorCodeSetting{$mFS}[1];
                    if($cntrTot % 2 == 0){
                        $colorCd = $measureColorCodeSetting{$mFS}[2]; 
                    }
                    $formatTotalLY->set_bg_color('#'.$colorCd);
                }
                $formatTotalLY->set_num_format('#,##0');
                if($totalCatData{$tVal.'_LY_'.$mFS} < 0){
                    $formatTotalLY->set_color('#FF0000');
                }
                $worksheet->write($row, $colStart, $totalCatData{$tVal.'_LY_'.$mFS}, $formatTotalLY);
                $colStart++;

                # VARP FORMAT
                my $formatTotalVARP = $workbook->add_format();
                $formatTotalVARP->set_align('center');
                if(defined $measureColorCodeSetting{$mFS}[1]){
                    my $colorCd = $measureColorCodeSetting{$mFS}[1];
                    if($cntrTot % 2 == 0){
                        $colorCd = $measureColorCodeSetting{$mFS}[2]; 
                    }
                    $formatTotalVARP->set_bg_color('#'.$colorCd);
                }
                $formatTotalVARP->set_num_format('#,##0.0');
                if($totalCatData{$tVal.'_VARP_'.$mFS} < 0){
                    $formatTotalVARP->set_color('#FF0000');
                }
                $worksheet->write($row, $colStart, $totalCatData{$tVal.'_VARP_'.$mFS}, $formatTotalVARP);
                $colStart++;


                # VAR FORMAT
                my $formatTotalVAR = $workbook->add_format();
                $formatTotalVAR->set_align('center');
                if(defined $measureColorCodeSetting{$mFS}[1]){
                    my $colorCd = $measureColorCodeSetting{$mFS}[1];
                    if($cntrTot % 2 == 0){
                        $colorCd = $measureColorCodeSetting{$mFS}[2]; 
                    }
                    $formatTotalVAR->set_bg_color('#'.$colorCd);
                }
                $formatTotalVAR->set_num_format('#,##0');
                if($totalCatData{$tVal.'_VAR_'.$mFS} < 0){
                    $formatTotalVAR->set_color('#FF0000');
                }
                $worksheet->write($row, $colStart, $totalCatData{$tVal.'_VAR_'.$mFS}, $formatTotalVAR);
                $colStart++;

            $cntrTot++;
            }
        }
    $row++;
    $colStart = 0;
# [START] PRINTING THE TOTAL COLUMNS

$dbh->disconnect;

sub str_replace {
    my $replace_this = shift;
    my $with_this  = shift; 
    my $string   = shift;
    
    my $length = length($string);
    my $target = length($replace_this);
    
    for(my $i=0; $i<$length - $target + 1; $i++) {
        if(substr($string,$i,$target) eq $replace_this) {
            $string = substr($string,0,$i) . $with_this . substr($string,$i+$target);
            return $string; #Comment this if you what a global replace
        }
    }
    return $string;
}
# sub array_date_sort {
#     my (@arr) = @_;
#     @arr =
#          map  { $_->[0] }
#          sort { $a->[1] <=> $b->[1] }
#          map  {
#            my($y,$m,$d) = /^(\d+)-(\d+)-(\d+)/;
#            [ $_, sprintf "20%d%02d%02d", $y, $m, $d ]
#          } (@arr);

#     return @arr;
# }
