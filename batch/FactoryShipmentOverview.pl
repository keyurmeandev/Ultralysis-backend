#!/usr/bin/perl -w

use strict;
use warnings;

# use DBI;
# use DBD::mysql;
use Excel::Writer::XLSX;
use Text::CSV;
use Data::Dumper;
use Time::Piece;
use POSIX 'floor';
use utf8;
use Encode;
use Redis;
use JSON;

# Perl script argument details 
# ARGV[0] => New Excel File Path
# ARGV[1] => Data Hash
# ARGV[2] => Logo
# ARGV[3] => project ID
# ARGV[4] => Redis connection server
# ARGV[5] => Redis connection password
# ARGV[6] => All Applied Filters Txt

my $dataHash       = $ARGV[1];
my $imagePath      = $ARGV[2];
my $projectID      = $ARGV[3];
my $redisServer    = $ARGV[4];
my $redisPassword  = $ARGV[5];

my @allAppliedFiltersList = split /$$/, $ARGV[6];

my $redis = Redis->new( server => $redisServer, debug => 0, password => $redisPassword );
$redis->select($projectID);
my $pmst = $redis->get($dataHash);
my $dataResult = JSON->new->utf8->decode($pmst);

#print Dumper $dataResult;
#exit;

my $excelFilePath = $ARGV[0];
my $workbook = Excel::Writer::XLSX->new($excelFilePath);
our $worksheet = $workbook->add_worksheet('Factory-Shipments-Overview');
our $row = 0;
our $colStart = 0;


# [START] Adding LOGO IMAGE
	# $worksheet->insert_image('A1', $imagePath, 1, 0);
	# $worksheet->set_row($row, 35);
	# $row = 1;
	# my $formatLogoBg = $workbook->add_format();
	# $formatLogoBg->set_bg_color('#0e0e0e');
	# $worksheet->merge_range($row, 0, $row, 50, '', $formatLogoBg);
	# $worksheet->set_row($row, 5);
# [END] Adding LOGO IMAGE

# [START] Adding Main Title
	my $formatTitle = $workbook->add_format();
	$formatTitle->set_align('center');
	$formatTitle->set_bold();
	$formatTitle->set_text_wrap();
	$formatTitle->set_size(15);
	$worksheet->merge_range($row, 0, $row, 20, 'MJN Factory Shipments Overview Report', $formatTitle);
	$worksheet->set_row($row, 35);
# [END] Adding Main Title

$row = $row + 1;
$colStart = 0;

#[START] Adding Filters details
    my $formatFilters = $workbook->add_format();
    $formatFilters->set_align('center');
    $formatFilters->set_bold();
    $formatFilters->set_text_wrap();
    $formatFilters->set_size(10);

    for my $appliedFlts (@allAppliedFiltersList) {
        my @actFlt = split /##/, $appliedFlts;
        $worksheet->merge_range($row, 0, $row, 20, $actFlt[0].' : '.decode('utf8',$actFlt[1]), $formatFilters);
        $worksheet->set_row($row, 20);
    $row++;
    }
#[END] Adding Filters details

$row = $row + 2;
$colStart = 0;

our $fmatHeader = $workbook->add_format();
	$fmatHeader->set_align('center');
	$fmatHeader->set_bold();
	$fmatHeader->set_bg_color('#edc9af');
	$fmatHeader->set_size(10);
	$fmatHeader->set_border(1);

our $fmat = $workbook->add_format();
	$fmat->set_align('center');
	$fmat->set_size(10);
	$fmat->set_border(1);

our $fmatNumber = $workbook->add_format();
	$fmatNumber->set_align('center');
	$fmatNumber->set_size(10);
	$fmatNumber->set_border(1);
	$fmatNumber->set_num_format('#,##0');

our $fmatFloat = $workbook->add_format();
	$fmatFloat->set_align('center');
	$fmatFloat->set_size(10);
	$fmatFloat->set_border(1);
	$fmatFloat->set_num_format('0.0');

our $fmatTotal = $workbook->add_format();
	$fmatTotal->set_align('center');
	$fmatTotal->set_size(10);
	$fmatTotal->set_bold();
	$fmatTotal->set_border(1);

our $fmatNumberTotal = $workbook->add_format();
	$fmatNumberTotal->set_align('center');
	$fmatNumberTotal->set_size(10);
	$fmatNumberTotal->set_border(1);
	$fmatNumberTotal->set_bold();
	$fmatNumberTotal->set_num_format('#,##0');

our $fmatFloatTotal = $workbook->add_format();
	$fmatFloatTotal->set_align('center');
	$fmatFloatTotal->set_size(10);
	$fmatFloatTotal->set_border(1);
	$fmatFloatTotal->set_bold();
	$fmatFloatTotal->set_num_format('0.0');

our $formatHead = $workbook->add_format();
	$formatHead->set_align('center');
	$formatHead->set_bold();
	$formatHead->set_bg_color('#a5a5a5');
	$formatHead->set_text_wrap();
	$formatHead->set_size(12);
	$formatHead->set_border(1);

our $fmatEmpty = $workbook->add_format();
	$fmatEmpty->set_align('center');
	
# [START] PRINTING THE TOTAL GRID DATA
	my %dataHash = ('columns' => \@{$dataResult->{salesColumns}}, 'data' => \@{$dataResult->{totalSales}}, 'columnsName' => \%{$dataResult->{measureColumnsNamesMapping}}, 'accountTitle'=>'', 'calculateTotalCols' => 'no');
	excelPrepareColumns(%dataHash);
# [END] PRINTING THE TOTAL GRID DATA

# [START] PRINTING THE INFRONT CATEGORY GRID DATA
	my $formatCategoryTitle = $workbook->add_format();
	$formatCategoryTitle->set_align('center');
	$formatCategoryTitle->set_bold();
	$formatCategoryTitle->set_text_wrap();
	$formatCategoryTitle->set_bg_color('#ffd700');
	$formatCategoryTitle->set_size(12);
	$formatCategoryTitle->set_border(1);

	# BLANK
	$worksheet->write($row, $colStart, '', $fmatEmpty);

	$row = $row + 1;
	$colStart = 0;

	$worksheet->merge_range($row, 0, $row, 20, $dataResult->{salesAccountColumns}{ACCOUNT}, $formatCategoryTitle);

	$row = $row + 1;
	$colStart = 0;

	%dataHash = ('columns' => \@{$dataResult->{salesColumns}}, 'data' => \@{$dataResult->{salesData}}, 'columnsName' => \%{$dataResult->{measureColumnsNamesMapping}}, 'accountTitle' =>$dataResult->{salesAccountColumns}{ACCOUNT}, 'calculateTotalCols' => 'yes');
	excelPrepareColumns(%dataHash);
# [END] PRINTING THE INFRONT CATEGORY GRID DATA

# [START] PRINTING THE MAX INFRONT CATEGORY GRID DATA
	
	# BLANK
	$worksheet->write($row, $colStart, '', $fmatEmpty);
	$row = $row + 1;
	$colStart = 0;

	$worksheet->merge_range($row, 0, $row, 20, $dataResult->{maxSalesProduct}, $formatCategoryTitle);

	$row = $row + 1;
	$colStart = 0;

	my @maxSalesProductKeys = keys %{$dataResult->{maxSalesProductData}};
	for my $accountId (@maxSalesProductKeys) {
		
		%dataHash = ('columns' => \@{$dataResult->{salesColumns}}, 'data' => \@{$dataResult->{maxSalesProductData}{$accountId}}, 'columnsName' => \%{$dataResult->{measureColumnsNamesMapping}}, 'accountTitle' =>$dataResult->{maxSalesProductAccountColumns}{$accountId}, 'calculateTotalCols' => 'yes');
		excelPrepareColumns(%dataHash);

		$row = $row + 1;
		$colStart = 0;
	}
# [END] PRINTING THE MAX INFRONT CATEGORY GRID DATA

# [START] PRINTING THE PRODUCT BASE ACCOUNT OTHER DATA
	my @productBaseAccountOtherKeys = keys %{$dataResult->{productBaseAccountOtherData}};
	for my $accountId (@productBaseAccountOtherKeys) {
		$worksheet->merge_range($row, 0, $row, 20, $dataResult->{productBaseAccountOtherAccountColumns}{$accountId}, $formatCategoryTitle);

		$row = $row + 1;
		$colStart = 0;

		%dataHash = ('columns' => \@{$dataResult->{salesColumns}}, 'data' => \@{$dataResult->{productBaseAccountOtherData}{$accountId}}, 'columnsName' => \%{$dataResult->{measureColumnsNamesMapping}}, 'accountTitle' =>$dataResult->{productBaseAccountOtherAccountName}, 'calculateTotalCols' => 'yes');
		excelPrepareColumns(%dataHash);

		$row = $row + 1;
		$colStart = 0;
	}
# [END] PRINTING THE PRODUCT BASE ACCOUNT OTHER DATA

# [START] PRINTING THE PRODUCT SPECIAL GRID ACCOUNT NAME
	$worksheet->merge_range($row, 0, $row, 20, $dataResult->{productSpecialGridAccountName}, $formatCategoryTitle);

	$row = $row + 1;
	$colStart = 0;

	%dataHash = ('columns' => \@{$dataResult->{salesColumns}}, 'data' => \@{$dataResult->{productSpecialGridValueData}}, 'columnsName' => \%{$dataResult->{measureColumnsNamesMapping}}, 'accountTitle' =>$dataResult->{productSpecialGridAccountColumns}{ACCOUNT}, 'calculateTotalCols' => 'yes');
	excelPrepareColumns(%dataHash);

	$row = $row + 1;
	$colStart = 0;
# [END] PRINTING THE PRODUCT SPECIAL GRID ACCOUNT NAME

$worksheet->set_column('A:A',16);
$worksheet->set_column('B:U',12);
$worksheet->set_column('H:H',3);
$worksheet->set_column('O:O',3);

# [START] Function definitation
sub excelPrepareColumns {
	my (%array) = @_;
	my $columns     = $array{columns};
	my $data 	    = $array{data};
	my $columnsName = $array{columnsName};
	my $accountTitle = $array{accountTitle};
	my $isCalculateTotalCols = $array{calculateTotalCols};
	
	$worksheet->write($row, $colStart, '', $formatHead);
	$colStart++;

	for my $cols (@{$columns}) {
		$worksheet->merge_range($row, $colStart, $row, $colStart+5, $columnsName->{$cols}, $formatHead);
		$colStart = $colStart+6;
		
		$worksheet->write($row, $colStart+1, '', $fmatEmpty);
	    $colStart++;
	}

	$row = $row + 1;
	$colStart = 0;

	$worksheet->write($row, $colStart, $accountTitle, $fmatHeader);
	$colStart++;

	for my $cols (@{$columns}) {
		$worksheet->write($row, $colStart, 'LAST YEAR', $fmatHeader);
		$colStart++;

		$worksheet->write($row, $colStart, 'THIS YEAR', $fmatHeader);
		$colStart++;

		$worksheet->write($row, $colStart, 'VAR', $fmatHeader);
		$colStart++;

		$worksheet->write($row, $colStart, 'VAR%', $fmatHeader);
		$colStart++;

		$worksheet->write($row, $colStart, 'SHARE', $fmatHeader);
		$colStart++;

		$worksheet->write($row, $colStart, 'SHARE CHANGE', $fmatHeader);
		$colStart++;

		# BLANK
		$worksheet->write($row, $colStart, '', $fmatEmpty);
		$colStart++;
	}

	$row = $row + 1;
	$colStart = 0;

	my $startRow = $row+1;
    for my $objData (@{$data}) {

		$worksheet->write($row, $colStart, $objData->{ACCOUNT}, $fmat);
	    $colStart++;

	    for my $cols (@{$columns}) {

	    	my $ty 			= $objData->{'TY_'.$cols};
			my $ly 			= $objData->{'LY_'.$cols};
			my $var 		= $objData->{'VAR_'.$cols};
			my $varper 		= $objData->{'VAR_PER_'.$cols};
			my $share 		= $objData->{'SHARE_'.$cols};
			my $shareChange = $objData->{'SHARE_CHANGE_'.$cols};

	        $worksheet->write($row, $colStart, $ly, $fmatNumber);
	        $colStart++;

	        $worksheet->write($row, $colStart, $ty, $fmatNumber);
	        $colStart++;

	        $worksheet->write($row, $colStart, $var, $fmatNumber);
	        $colStart++;

	        $worksheet->write($row, $colStart, $varper, $fmatFloat);
	        $colStart++;

	        $worksheet->write($row, $colStart, $share, $fmatFloat);
	        $colStart++;

	        $worksheet->write($row, $colStart, $shareChange, $fmatFloat);
	        $colStart++;

	        # BLANK
	        $worksheet->write($row, $colStart, '', $fmatEmpty);
	        $colStart++;

			# my $tylyVar = $ty - $ly;
			# $worksheet->write($row, $colStart, $tylyVar, $fmat);
			# $colStart++;

			# my $tylyVarPer = ($ly != 0) ? ((($tylyVar * 100) / $ly) ) : 0;
			# $worksheet->write($row, $colStart, $tylyVarPer, $fmat);
			# $colStart++;
	    }

	    $row = $row + 1;
		$colStart = 0;
	}

	my $endRow = $row;
	
	if($isCalculateTotalCols eq 'yes') {
		$worksheet->write($row, $colStart, 'TOTAL', $fmatTotal);
		$colStart++;

		for my $cols (@{$columns}) {

			my $lyColName  = mlx_xls2col($colStart);
			my $tyColName  = mlx_xls2col($colStart+1);
			my $varColName = mlx_xls2col($colStart+2);

			$worksheet->write_formula($row, $colStart,'=SUM('.$lyColName.$startRow.':'.$lyColName.$endRow.')', $fmatNumberTotal);
			$colStart++;

			$worksheet->write_formula($row, $colStart,'=SUM('.$tyColName.$startRow.':'.$tyColName.$endRow.')', $fmatNumberTotal);
			$colStart++;

			$worksheet->write_formula($row, $colStart,'=SUM('.$varColName.$startRow.':'.$varColName.$endRow.')', $fmatNumberTotal);
			$colStart++;

			$worksheet->write_formula($row, $colStart,'=IFERROR((((SUM('.$tyColName.$startRow.':'.$tyColName.$endRow.')-SUM('.$lyColName.$startRow.':'.$lyColName.$endRow.'))/SUM('.$lyColName.$startRow.':'.$lyColName.$endRow.'))*100),0)', $fmatFloatTotal);
			$colStart++;
			
			$worksheet->write($row, $colStart, '', $fmatTotal);
			$colStart++;

			$worksheet->write($row, $colStart, '', $fmatTotal);
			$colStart++;

			# BLANK
			$worksheet->write($row, $colStart, '', $fmatEmpty);
			$colStart++;
		}

		$row = $row + 1;
		$colStart = 0;
	}
}
# [END] Function definitation

# [START] GETTING COLUMN NUMBERS
sub mlx_xls2col {
    my $col      = shift;
    my $alphabet = shift || [ 'A' .. 'Z' ];

    my $alphabet_size = scalar( @$alphabet );

    my $result    = '';
    my $remainder = $col;
    while ( $remainder ) {
        my $letter = $remainder % $alphabet_size;
        my $adj = length( $result ) ? -1 : 0;    # Not quite a base-26
        # + number
        $result = $alphabet->[$letter + $adj] . $result;
        $remainder = int( $remainder / $alphabet_size );
    }
    $result ||= $alphabet->[0];
	$result;
}
# [END]