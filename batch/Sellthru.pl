#!/usr/bin/perl -w

use strict;
use warnings;

use Excel::Writer::XLSX;
use Text::CSV;
use Data::Dumper;
use Time::Piece;
use POSIX 'floor';
use utf8;
use Redis;
use Encode;
use JSON;

# Perl script argument details 
# ARGV[0] => New Excel File Path
# ARGV[1] => project ID
# ARGV[2] => Redis connection server
# ARGV[3] => Redis connection password
# ARGV[4] => REDIS HASH FOR THE SELLTHRU_GRID_COLUMNS_HEADER
# ARGV[5] => REDIS HASH FOR THE SELLTHRU_GRID_DATA
# ARGV[6] => ULTRALYSIS LOGO PATH

my $projectID      = $ARGV[1];
my $redisServer    = $ARGV[2];
my $redisPassword  = $ARGV[3];
my $redis = Redis->new( server => $redisServer, debug => 0, password => $redisPassword );
$redis->select($projectID);

my $sellthruGridColumnsHeader = $redis->get($ARGV[4]);
my $sellthruGridColumnsHeaderData = JSON->new->utf8->decode($sellthruGridColumnsHeader);
my $sellthruGrid = $redis->get($ARGV[5]);
my $sellthruGridData = JSON->new->utf8->decode($sellthruGrid);

# Create a new Excel workbook
my $excelFilePath = $ARGV[0];
my $workbook = Excel::Writer::XLSX->new($excelFilePath);

my $worksheet = $workbook->add_worksheet('SELLTHRU');

my $row = 0;
my $colStart = 0;

my $totalColCnt = scalar @{$sellthruGridColumnsHeaderData->{'TY'}};
$totalColCnt = $totalColCnt+3;

$worksheet->insert_image('A1', $ARGV[6], 1, 0);
$worksheet->set_row($row, 35);

$worksheet->freeze_panes(5,4);

$row = 1;
my $formatLogoBg = $workbook->add_format();
$formatLogoBg->set_bg_color('#0e0e0e');
$worksheet->merge_range($row, 0, $row, $totalColCnt, '', $formatLogoBg);


$row = 3;
my $fmat = $workbook->add_format();
$fmat->set_align('center');
$fmat->set_align('vcenter');
$fmat->set_bold();
$fmat->set_size(10);
$fmat->set_text_wrap();

$worksheet->merge_range($row, 0, $row+1, 0, 'WIN',$fmat);
$worksheet->merge_range($row, 1, $row+1, 1, 'BUY Volumes',$fmat);
$worksheet->merge_range($row, 2, $row+1, 2, 'Category',$fmat);
$worksheet->merge_range($row, 3, $row+1, 3, 'Row Title', $fmat);
$colStart = 4;
for my $tyDate (@{$sellthruGridColumnsHeaderData->{'TY'}}) {
    $worksheet->set_column($colStart, $colStart, 10);
    $worksheet->write($row, $colStart, $tyDate->{'DAY'}, $fmat);
    $worksheet->write($row+1, $colStart, $tyDate->{'FORMATED_DATE'}, $fmat);
    $colStart++;
}

$row = 5;
my $formatH = $workbook->add_format();
$formatH->set_bg_color('#DAEEF3');
$formatH->set_align('left');
$formatH->set_size(12);
$formatH->set_bold();

my $fmatData = $workbook->add_format();
$fmatData->set_align('vcenter');
$fmatData->set_align('center');
$fmatData->set_bold();
$fmatData->set_size(9);

my $fmatDataWithBg = $workbook->add_format();
$fmatDataWithBg->set_align('vcenter');
$fmatDataWithBg->set_align('center');
$fmatDataWithBg->set_bold();
$fmatDataWithBg->set_bg_color('#F2C0AE');
$fmatDataWithBg->set_size(9);

my $fmatDigitData = $workbook->add_format();
$fmatDigitData->set_align('right');
# $fmatDigitData->set_bold();
$fmatDigitData->set_num_format('#,##0');
$fmatDigitData->set_size(9);

my $fmatDigitDataPer = $workbook->add_format();
$fmatDigitDataPer->set_align('right');
# $fmatDigitDataPer->set_bold();
$fmatDigitDataPer->set_num_format('#,##0.0');
$fmatDigitDataPer->set_size(9);

my $fmatDigitDataRed = $workbook->add_format();
$fmatDigitDataRed->set_align('right');
# $fmatDigitDataRed->set_bold();
$fmatDigitDataRed->set_num_format('#,##0');
$fmatDigitDataRed->set_bg_color('#FFCCCC');
$fmatDigitDataRed->set_size(9);

$worksheet->set_column('C:C', 14);
$worksheet->set_column('D:D', 18);

while ((my $keyPname, my $gridData) = each (%$sellthruGridData)) {
    $colStart = 0;
    $worksheet->merge_range($row, $colStart, $row, $totalColCnt, $keyPname, $formatH);
    $row++;

    my $cnt = 0;
    foreach my $gridDetail (@$gridData) {
        if ($cnt == 0) {
            $worksheet->merge_range($row, 0, $row+5, 0, $gridDetail->{'PIN'}, $fmatData);
            $worksheet->merge_range($row, 1, $row+5, 1, $gridDetail->{'AGREED_BUY'}, $fmatDataWithBg);
            $worksheet->merge_range($row, 2, $row+5, 2, $gridDetail->{'CATEGORY'}, $fmatDataWithBg);
        }
        $worksheet->write($row, 3, $gridDetail->{'ROWDESC'}, $fmatDataWithBg);

        $colStart = 4;
        for my $tyDate (@{$sellthruGridColumnsHeaderData->{'TY'}}) {
            my $cfdt = Time::Piece->strptime($tyDate->{'MYDATE'}, "%Y-%m-%d");
            $cfdt = $cfdt->strftime("%Y%m%d");
            $cfdt = 'dt'.$cfdt;
            
            my $cdtVal = 0;
            if(defined $gridDetail->{$cfdt}){
                $cdtVal = $gridDetail->{$cfdt};
            }

            if ($cnt == 4) {
                $worksheet->write($row, $colStart, $cdtVal, $fmatDigitDataPer);
            } elsif ($cnt == 2 && $cdtVal == 0) {
                $worksheet->write($row, $colStart, $cdtVal, $fmatDigitDataRed);
            } else {
                $worksheet->write($row, $colStart, $cdtVal, $fmatDigitData);
            }

            $colStart++;
        }
        $row++;
        $cnt++;
    }
    $row++;
}

# print Dumper $gridDetail