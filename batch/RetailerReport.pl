#!/usr/bin/perl -w

use strict;
use warnings;

# use DBI;
# use DBD::mysql;
use Excel::Writer::XLSX;
use Data::Dumper;
use Text::CSV;
use Redis;
use JSON;

# print "Started at ".(localtime),"\n"; # scalar context

# my $data_source = 'DBI:mysql:database=canadalcl:mysql_read_default_file=/usr/local/Calpont/mysql/my.cnf';
# my $username = 'ultralysis';
# my $auth = 'retaillink';
# my %attr = ();

# my $dbh = DBI->connect($data_source, $username, $auth, \%attr);
# my $sql = $ARGV[0];
# my $sth = $dbh->prepare($sql);

# $sth->execute();

# print "Completed at ".(localtime),"\n"; # scalar context
# Perl script argument details 
# ARGV[0] => New Excel File Path
# ARGV[1] => Raw data CSV File Path
# ARGV[2] => Pass Max MYDATE to show in file 
# ARGV[3] => ## separated BRAND LIST 
# ARGV[4] => ## separated SUPPLIER LIST
# ARGV[5] => ## separated DROPDOWN 1 LIST
# ARGV[6] => ## separated DROPDOWN 2 LIST
# ARGV[7] => Skip Rank count
# ARGV[8] => project ID
# ARGV[9] => Redis connection server
# ARGV[10] => Redis connection password
# $ARGV[11] => supplier Field Name
# $ARGV[12] => Brand Field Name

# Create a new Excel workbook
my $excelFilePath  = $ARGV[0];
my $csvFilePath    = $ARGV[1];
my $projectID      = $ARGV[8];
my $redisServer    = $ARGV[9];
my $redisPassword  = $ARGV[10];
my $supplierFieldName  = $ARGV[11];
my $brandFieldName     = $ARGV[12];


my $redis = Redis->new( server => $redisServer, debug => 0, password => $redisPassword );
$redis->select($projectID);
my $pmst = $redis->get($csvFilePath);
my $csvRawData = JSON->new->utf8->decode($pmst);

my $workbook = Excel::Writer::XLSX->new($excelFilePath);

# my $csv = Text::CSV->new({ sep_char => ',' });
# my $csvFilePath = $ARGV[1] or die "Need to get Raw Data CSV file on the command line\n";
# open(my $csvRawData, '<', $csvFilePath) or die "Could not open '$csvFilePath' $!\n";

# Add a worksheet
my $worksheet = $workbook->add_worksheet("RetailersData");
my $row = 0;
my $rowFileStartNo = 1;

for my $array (@{$csvRawData}) {
    if($array->{'TY_VALUE'} > 0 || $array->{'LY_VALUE'} > 0){
        my @fields;
        push(@fields,$array->{'TIME_FILTER'});
        push(@fields,$array->{'TOP_FILTER'});
        push(@fields,$array->{'BRAND'});
        push(@fields,$array->{'SUPPLIER'});
        push(@fields,$array->{'TY_VALUE'});
        push(@fields,$array->{'LY_VALUE'});
        my $field_ref = \@fields;
        $worksheet->write_row( $row++, 0, $field_ref );
    }
}

# while (my $line = <$csvRawData>) {
#     chomp $line;

#     if ($csv->parse($line)) {
#         my @fields = $csv->fields();
#         my $field_ref = \@fields;
#         $worksheet->write_row( $row++, 0, $field_ref );
#     }
# }
my $rowFileEndNo = $row;
$worksheet->hide();

my @colors = ( "#C0C0C0","#9999FF","#FFFFCC","#CCFFFF","#FF8080","#CCCCFF","#FFFF00","#00FFFF","#CCFFFF",
        "#CCFFCC","#FFFF99","#99CCFF","#FF99CC","#CC99FF","#FFCC99","#33CCCC","#99CC00","#FFCC00","#FF9900","#FF6600" );

my @excelBrandHeader = ( '$ Rank', "Current Dollars", "YAGO Dollars", "Dollar Change", "% Change to LY", $brandFieldName." Share", $brandFieldName." Share Indx",
        $brandFieldName." Share Chg", $supplierFieldName." Share", $supplierFieldName." Share Chg" );
my $excelBrandHeader_ref = \@excelBrandHeader;

my @brandList = split /##/, $ARGV[3];
my @supplierList = split /##/, $ARGV[4];

my @dropdown1Data = split /##/, $ARGV[5];
my $dropdown1Data_ref = \@dropdown1Data;
my @dropdown2Data = split /##/, $ARGV[6];
my $dropdown2Data_ref = \@dropdown2Data;

my $skipRankCnt = $ARGV[7];

my $worksheet1 = $workbook->add_worksheet("Retailers");
$worksheet1->activate();

#  Add and define a format

my $isTopFilter = scalar(@dropdown1Data);

my $format = $workbook->add_format();

$format->set_bg_color('#CCFFCC');
$format->set_align('left');
$worksheet1->set_column(1, 1, 35);
$worksheet1->write('B1', 'Select Options From Dropdowns Below', $format);

if($isTopFilter != 0)
{
    $worksheet1->data_validation('B2', {
        validate => 'list',
        source    => $dropdown1Data_ref,
        input_title => 'Pick from list',
        input_message => 'Please pick a value from the drop-down list.',
    });
    $worksheet1->write('B2', $dropdown1Data[0]);
}

#  Add and define a format
$format = $workbook->add_format();
$format->set_bg_color('#CCFFCC');
$format->set_align('center');
$worksheet1->set_column(1, 1, 35);
$worksheet1->write('B3', 'Select Options From Dropdowns Below', $format);
$worksheet1->data_validation('B3', {
    validate => 'list',
    source    => $dropdown2Data_ref,
    input_title => 'Pick from list',
    input_message => 'Please pick a value from the drop-down list.',
});
$worksheet1->write('B3', $dropdown2Data[0]);

$worksheet1->write('B4', $ARGV[2]);

$format = $workbook->add_format();
$format->set_bg_color($colors[0]);
$format->set_align('center');
$worksheet1->write('B6', '', $format);
$format->set_align('left');
$worksheet1->write('B7', $supplierFieldName, $format);

my $i = 1;
my $colorCnt = 0;
my $totalColorCnt = scalar(@colors);
for my $brand (@brandList) {
    $format = $workbook->add_format();
    if ($colorCnt > ($totalColorCnt - 1)) {
        $colorCnt = 0;
    }

    $format->set_bg_color($colors[$colorCnt]);
    $format->set_align('center');
    $worksheet1->merge_range(5, $i+1, 5, $i+10, $brand, $format);
    $format->set_text_wrap();
    # $format->set_align('vjustify');
    $worksheet1->set_row(6, 60);
    $worksheet1->write_row(6, $i+1, $excelBrandHeader_ref, $format);
    $colorCnt++;
    $i = $i+10;
}

my $maxColNum = $i;
my $rowStart = 7;
my $colStart = 1;
$row = 7;
my $col = 1;
my $totalRow = scalar(@supplierList) + $rowStart;

my $numFormat = $workbook->add_format();
$numFormat->set_num_format('#,##0');

my $perFormat = $workbook->add_format();
$perFormat->set_num_format('0.0%');

my $oneDecFormat = $workbook->add_format();
$oneDecFormat->set_num_format('#,##0.0');

my $twoDecFormat = $workbook->add_format();
$twoDecFormat->set_num_format('#,##0.00');

my $formatColorRed = $workbook->add_format();
$formatColorRed->set_bg_color('#FFCCCC');

my $formatColorGreen = $workbook->add_format();
$formatColorGreen->set_bg_color('#CCFFCC');

my $autoFilterAdded = 'false';
my $addExtraCnt = 0;

for my $supplier (@supplierList) {
    my $rowNum = $row+1;
    if ($skipRankCnt > 0 && $rowNum == ($skipRankCnt+$rowStart+1)) {
        my $formatBg = $workbook->add_format();
        $formatBg->set_bg_color('#000000');
        my $startColName = mlx_xls2col($col);
        my $lastColName = mlx_xls2col($maxColNum);
        $worksheet1->autofilter($startColName.$rowNum.':'.$lastColName.$rowNum);
        $worksheet1->set_row($rowNum-1, undef, $formatBg);
        $autoFilterAdded = 'true';
        $addExtraCnt = 1;
        $rowNum++;
        $row++;
    }
    if ($skipRankCnt == 0) {
        $autoFilterAdded = 'true';
    }
    $worksheet1->write($row, $colStart, $supplier);
    for my $brand (@brandList) {
        my $rankColName = mlx_xls2col($col+1);
        my $tyColName = mlx_xls2col($col+2);
        my $lyColName = mlx_xls2col($col+3);
        
        # Rank Calculation
        if ($autoFilterAdded eq 'true') {
            $worksheet1->write_formula($rankColName.$rowNum, '=RANK('.$tyColName.$rowNum.','.'$'.$tyColName.'$'.($skipRankCnt+$rowStart+$addExtraCnt+1).':'.'$'.$tyColName.'$'.($totalRow+$addExtraCnt).',0)');
        } else {
            $worksheet1->write_blank($rankColName.$rowNum);
        }

        # Current Dollars Calculation
        my $bName = '"'.$brand.'"';
        my $cellFormula = '=SUMIFS(RetailersData!'.'$'.'E'.$rowFileStartNo.':'.'$'.'E'.$rowFileEndNo.',RetailersData!'.'$'.'A'.$rowFileStartNo.':'.'$'.'A'.$rowFileEndNo.',Retailers!'.'$'.'B'.'$'.'3,';
        
        if($isTopFilter != 0)
        {
            $cellFormula = $cellFormula . 'RetailersData!'.'$'.'B'.$rowFileStartNo.':'.'$'.'B'.$rowFileEndNo.',Retailers!'.'$'.'B'.'$'.'2,';
        }
        
        $cellFormula = $cellFormula.'RetailersData!'.'$'.'C'.$rowFileStartNo.':'.'$'.'C'.$rowFileEndNo.','.$bName.',RetailersData!'.'$'.'D'.$rowFileStartNo.':'.'$'.'D'.$rowFileEndNo.','.'$'.'B'.($row+1).')';
        
        $worksheet1->write_formula($tyColName.$rowNum, $cellFormula, $numFormat);
        
        # YAGO Calculation
        $cellFormula = '=SUMIFS(RetailersData!'.'$'.'F'.$rowFileStartNo.':'.'$'.'F'.$rowFileEndNo.',RetailersData!'.'$'.'A'.$rowFileStartNo.':'.'$'.'A'.$rowFileEndNo.',Retailers!'.'$'.'B'.'$'.'3,';
        
        if($isTopFilter != 0)
        {
            $cellFormula = $cellFormula.'RetailersData!'.'$'.'B'.$rowFileStartNo.':'.'$'.'B'.$rowFileEndNo.',Retailers!'.'$'.'B'.'$'.'2,';
        }
        
        $cellFormula = $cellFormula.'RetailersData!'.'$'.'C'.$rowFileStartNo.':'.'$'.'C'.$rowFileEndNo.','.$bName.',RetailersData!'.'$'.'D'.$rowFileStartNo.':'.'$'.'D'.$rowFileEndNo.','.'$'.'B'.($row+1).')';
        
        $worksheet1->write_formula($lyColName.$rowNum, $cellFormula, $numFormat);
        
        # Dollar Change Calculation
        my $dollarChangeColName = mlx_xls2col($col+4);
        $worksheet1->write_formula($dollarChangeColName.$rowNum, '='.$tyColName.$rowNum.'-'.$lyColName.$rowNum, $numFormat);
        $worksheet1->conditional_formatting($dollarChangeColName.$rowNum,
            {
                type     => 'cell',
                criteria => '<',
                value    => 0,
                format   => $formatColorRed,
            }
        );
        $worksheet1->conditional_formatting($dollarChangeColName.$rowNum,
            {
                type     => 'cell',
                criteria => '>',
                value    => 0,
                format   => $formatColorGreen,
            }
        );

        # % Change to LY Calculation
        my $percentageChangeColName = mlx_xls2col($col+5);
        $worksheet1->write_formula($percentageChangeColName.$rowNum, '=IFERROR('.$tyColName.$rowNum.'/'.$lyColName.$rowNum.'-1,0)', $perFormat);
        $worksheet1->conditional_formatting($percentageChangeColName.$rowNum,
            {
                type     => 'cell',
                criteria => '<',
                value    => 0,
                format   => $formatColorRed,
            }
        );
        $worksheet1->conditional_formatting($percentageChangeColName.$rowNum,
            {
                type     => 'cell',
                criteria => '>',
                value    => 0,
                format   => $formatColorGreen,
            }
        );

        # Category Share Calculation
        my $catShareColName = mlx_xls2col($col+6);
        #if ($autoFilterAdded eq 'true') {
            $worksheet1->write_formula($catShareColName.$rowNum, '=IFERROR('.$tyColName.$rowNum.'/$'.$tyColName.'$'.($totalRow+1).',0)', $perFormat);
        #}else{
        #    $worksheet1->write_formula($catShareColName.$rowNum, '=IFERROR('.$tyColName.$rowNum.'/$'.'D'.'$'.($rowStart+1).',0)', $perFormat);
        #}
        
        # Category Share Indx Calculation
        my $catShareIndxColName = mlx_xls2col($col+7);
        $worksheet1->write_formula($catShareIndxColName.$rowNum, '=IFERROR(('.$catShareColName.$rowNum.'/$'.'H'.'$'.$rowNum.')*100,0)', $twoDecFormat);

        $worksheet1->conditional_formatting($catShareIndxColName.$rowNum,
            {
                type     => 'cell',
                criteria => 'between',
                minimum  => 85,
                maximum  => 115,
                format   => $formatColorGreen,
            }
        );

        $worksheet1->conditional_formatting($catShareIndxColName.$rowNum,
            {
                type     => 'cell',
                criteria => 'not between',
                minimum  => 85,
                maximum  => 115,
                format   => $formatColorRed,
            }
        );

        # Category Share Chg Calculation
        #if ($autoFilterAdded eq 'true') {
            $worksheet1->write_formula(mlx_xls2col($col+8).$rowNum, '=IFERROR(('.$catShareColName.$rowNum.'-('.$lyColName.$rowNum.'/'.$lyColName.'$'.($totalRow+1).'))*100,0)', $oneDecFormat);
        #}else{
        #    $worksheet1->write_formula(mlx_xls2col($col+7).$rowNum, '=IFERROR(('.$catShareColName.$rowNum.'-('.$lyColName.$rowNum.'/$'.'E'.$rowNum.'))*100,0)', $oneDecFormat);
        #}

        # Market Share Calculation
        my $marketShareColName = mlx_xls2col($col+9);
        #if ($autoFilterAdded eq 'true') {
            $worksheet1->write_formula($marketShareColName.$rowNum, '=IFERROR(('.$tyColName.$rowNum.'/$'.'D'.'$'.$rowNum.'),0)', $perFormat);
        #}else{
        #    $worksheet1->write_formula($marketShareColName.$rowNum, '=IFERROR(('.$tyColName.$rowNum.'/$'.$tyColName.($rowStart+1).'),0)', $perFormat);            
        #}

        
        # Market Share Chg Calculation
        #if ($autoFilterAdded eq 'true') {
            $worksheet1->write_formula(mlx_xls2col($col+10).$rowNum, '=IFERROR(('.$marketShareColName.$rowNum.'-('.$lyColName.$rowNum.'/'.'$'.'E'.'$'.($rowNum).'))*100,0)', $oneDecFormat);
        #}else{
        #    $worksheet1->write_formula(mlx_xls2col($col+9).$rowNum, '=IFERROR(('.$marketShareColName.$rowNum.'-('.$lyColName.$rowNum.'/'.$lyColName.'$'.($rowStart+1).'))*100,0)', $oneDecFormat);
        #}

        $col = $col+10;
    }
    $row++;
    $col = $colStart;
}

$col = $colStart;
$worksheet1->write($row, $colStart, 'Total');
for my $brand (@brandList) {
    
    my $rankColName = mlx_xls2col($col+1);
    my $tyColName = mlx_xls2col($col+2);
    my $lyColName = mlx_xls2col($col+3);
    
    my $rowNum = $row+1;

    # Rank Calculation
    $worksheet1->write_blank($rankColName.$rowNum);

    # Current Dollars Calculation
    $worksheet1->write_formula($tyColName.$rowNum, '=SUM('.'$'.$tyColName.'$'.($rowStart+1).':'.'$'.$tyColName.'$'.($totalRow+$addExtraCnt).')', $numFormat);
    
    # YAGO Calculation
    $worksheet1->write_formula($lyColName.$rowNum, '=SUM('.'$'.$lyColName.'$'.($rowStart+1).':'.'$'.$lyColName.'$'.($totalRow+$addExtraCnt).')', $numFormat);
    
    # Dollar Change Calculation
    $worksheet1->write_formula(mlx_xls2col($col+4).$rowNum, '='.$tyColName.$rowNum.'-'.$lyColName.$rowNum, $numFormat);
    $worksheet1->conditional_formatting(mlx_xls2col($col+4).$rowNum,
            {
                type     => 'cell',
                criteria => '<',
                value    => 0,
                format   => $formatColorRed,
            }
        );
    $worksheet1->conditional_formatting(mlx_xls2col($col+4).$rowNum,
        {
            type     => 'cell',
            criteria => '>',
            value    => 0,
            format   => $formatColorGreen,
        }
    );    

    # % Change to LY Calculation
    $worksheet1->write_formula(mlx_xls2col($col+5).$rowNum, '=IFERROR('.$tyColName.$rowNum.'/'.$lyColName.$rowNum.'-1,0)', $perFormat);
    $worksheet1->conditional_formatting(mlx_xls2col($col+5).$rowNum,
            {
                type     => 'cell',
                criteria => '<',
                value    => 0,
                format   => $formatColorRed,
            }
        );
    $worksheet1->conditional_formatting(mlx_xls2col($col+5).$rowNum,
        {
            type     => 'cell',
            criteria => '>',
            value    => 0,
            format   => $formatColorGreen,
        }
    );        
    
    # Category Share Calculation
    my $catShareColName = mlx_xls2col($col+6);
    #if ($autoFilterAdded eq 'true') {
        $worksheet1->write_formula($catShareColName.$rowNum, '=IFERROR('.$tyColName.$rowNum.'/$'.$tyColName.'$'.($totalRow+1).',0)', $perFormat);
    #}else{
    #    $worksheet1->write_formula($catShareColName.$rowNum, '=IFERROR('.$tyColName.$rowNum.'/$'.'D'.'$'.($rowStart+1).',0)', $perFormat);
    #}
    
    # Category Share Indx Calculation
    my $catShareIndxColName = mlx_xls2col($col+7);
    # $worksheet1->write_formula($catShareIndxColName.$rowNum, '=IFERROR(('.$catShareColName.$rowNum.'/$'.'H'.'$'.$rowNum.')*100,0)', $twoDecFormat);
    # $worksheet1->conditional_formatting($catShareIndxColName.$rowNum,
    #     {
    #         type     => 'cell',
    #         criteria => '<',
    #         value    => 100,
    #         format   => $formatColor,
    #     }
    # );
    $worksheet1->write_blank($catShareIndxColName.$rowNum);
   
    # Category Share Chg Calculation
    #if ($autoFilterAdded eq 'true') {
        $worksheet1->write_formula(mlx_xls2col($col+8).$rowNum, '=IFERROR(('.$catShareColName.$rowNum.'-('.$lyColName.$rowNum.'/'.$lyColName.'$'.($totalRow+1).'))*100,0)', $oneDecFormat);
    #}else{
    #    $worksheet1->write_formula(mlx_xls2col($col+7).$rowNum, '=IFERROR(('.$catShareColName.$rowNum.'-('.$lyColName.$rowNum.'/$'.'E'.$rowNum.'))*100,0)', $oneDecFormat);
    #}
    
    # Market Share Calculation
    my $marketShareColName = mlx_xls2col($col+9);
    #if ($autoFilterAdded eq 'true') {
        $worksheet1->write_formula($marketShareColName.$rowNum, '=IFERROR(('.$tyColName.$rowNum.'/$'.'D'.'$'.$rowNum.'),0)', $perFormat);
    #}else{
    #    $worksheet1->write_formula($marketShareColName.$rowNum, '=IFERROR(('.$tyColName.$rowNum.'/$'.$tyColName.($rowStart+1).'),0)', $perFormat);
    #}

    # Market Share Chg Calculation
    #if ($autoFilterAdded eq 'true') {
        $worksheet1->write_formula(mlx_xls2col($col+10).$rowNum, '=IFERROR(('.$marketShareColName.$rowNum.'-('.$lyColName.$rowNum.'/'.'$'.'E'.'$'.($rowNum).'))*100,0)', $oneDecFormat);
    #}else{
    #    $worksheet1->write_formula(mlx_xls2col($col+9).$rowNum, '=IFERROR(('.$marketShareColName.$rowNum.'-('.$lyColName.$rowNum.'/'.$lyColName.'$'.($rowStart+1).'))*100,0)', $oneDecFormat);
    #}

    $col = $col+10;
}

# my $format = $workbook->add_format();
# $format->set_num_format('#,##0');
# $worksheet1->set_column('A:A', undef, $format1);
$worksheet1->freeze_panes('C8');
print $excelFilePath;

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