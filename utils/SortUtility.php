<?php
namespace utils;

class SortUtility
{
	public static $sortBy;
	public static function sort2DArray($arrayToSort,$sortBy,$sortType){
		if($arrayToSort!==null)
		{
			self::$sortBy = $sortBy;
			usort($arrayToSort, array(self,$sortType));
			return $arrayToSort;
		}
		return array();
	}
	
	public static function sort2DArrayAssoc($arrayToSort,$sortBy,$sortType){
		if($arrayToSort!==null)
		{
			self::$sortBy = $sortBy;
			uasort($arrayToSort, array(self,$sortType));
			return $arrayToSort;
		}
		return array();
	}


	public static function sortAscendingCallBackFunc($a,$b) {
		return ( (array)$a[self::$sortBy] > (array)$b[self::$sortBy]) ? 1 : -1;
	}

	public static function sortDescendingCallBackFunc($a,$b) {
		return ( (array)$a[self::$sortBy] < (array)$b[self::$sortBy]) ? 1 : -1;
	}
}
