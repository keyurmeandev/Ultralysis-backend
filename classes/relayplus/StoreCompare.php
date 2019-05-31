<style type="text/css">
<!--		
table{width:1000px;}
.storeData tr{display: block; margin:15mm 0px;}
.storeData tr td{padding:10px;}
table tr td{color:#FFFFFF;font-weight:bold;}
.store1{			
		font-size: 26px;
		text-align:right;
		color: #000;
		width:120mm;
		height:10px;		
		background-color: #FFD700;
}
.store2{		
		font-size: 26px;
		text-align:left;
		color: #000;
		width:120mm;
		height:10px;		
		background-color: #FFD700;
}
.greytd{
	background:#A9A9A9;
	width:32mm;
	float:left;
}
.datatd{
	background:#E6E6FA;
	color:#000;
	text-align:center;
	width:45mm;
	font-size:23px;
}
.labeltd{
	background:#018634;
	text-align:center;
	width:50mm;
	font-size:24px;
}
-->
</style>
		
<?php $bg = 'background-image: url('.$_SERVER['DOCUMENT_ROOT'].'/relay-plus/project/assets/img/bg.png); background-position: left top; background-repeat: no-repeat;'; ?>
<div style="<?php echo $bg; ?>;width:1000px;height:650px;">
	<br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br>
	<table cellspacing="0">
		<tr>
			<td class="store1" style="padding-top:10px;padding-bottom:10px;"><?php echo trim($primaryStoreName); ?></td>
			<td style="width:20mm;">&nbsp;</td>
			<td class="store2" style="padding-top:10px;padding-bottom:10px;"><?php echo trim($secondaryStoreName); ?></td>
		</tr>
	</table>
	<br><br>
	<table width="100%" class="storeData" cellspacing="5">
		<?php foreach($storesDetails as $data){?>
		<tr>
			<td class="greytd">&nbsp;</td>
			<td class="datatd"><?php echo ($data['primary']) ? $data['primary'] : 0; ?></td>
			<td class="labeltd"><?php echo $data['label']; ?></td>
			<td class="datatd"><?php echo ($data['secondary']) ? $data['secondary'] : 0; ?></td>
			<td class="greytd">&nbsp;</td>
		</tr>
			<?php } ?>
	</table>
</div>