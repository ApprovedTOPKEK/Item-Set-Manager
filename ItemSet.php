<?php

/**
 * Class ItemSet
 *
 * Data-Representation of ItemSets.
 */
class ItemSet {

	public $ID = 0, $ownerID = 0, $title = "", $type = "", $map = "", $mode = "", $sortrank = 0, $champion = "", $canEdit = false, $canRate = false, $canCopy = false, $rating = 0;
	public $blocks = array();
	public $complete = false;


	public static function newSet($val){
		$itemset = new self();
		$itemset->ID = $val['ID'];
		$itemset->ownerID = $val['OwnerID'];
		$itemset->title = $val['Title'];
		$itemset->type = $val['Type'];
		$itemset->map = $val['Map'];
		$itemset->mode = $val['Mode'];
		$itemset->sortrank = $val['Sortrank'];
		$itemset->champion = $val['Champion'];
		$itemset->rating = $val["Rating"];
		$itemset->blocks = recursiveArrayObject($val["Blocks"], ArrayObject::ARRAY_AS_PROPS);
		//prettyPrint($itemset->blocks);
		$itemset->complete = true;

		return $itemset;
	}

	public function __construct(){}

	public function setEditable($bool){
		$this->canEdit = $bool;
	}

	public function canRate($bool){
		$this->canRate = $bool;
	}

	public function canCopy($bool){
		$this->canCopy = $bool;
	}

	//UNUSED... Wait why? This would have saved so much messy code... meh.. too late
	public function save($database){

	}

	//UNUSED
	public function reload($database){
		if(! isset($this->ID)) return;


	}
}

?>