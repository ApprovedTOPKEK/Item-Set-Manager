<?php
/**
 * SOME CLASSES CONTAIN NO ERROR-HANDLING BECAUSE IT IS ASSUMED THAT THE USER DIDNT MESS WITH THE CODE CLIENT-SIDE.
 * + Code is still secure, but the rebellish user won't see any message. Why should he.
 */


include_once "GlobalUtils.php";

$database = new DBWrapper();

//No point in downloading something if there is nothing to download
if(! isset($_GET["id"])){
	die();
}

/**
 * Fetch the itemset the user wants to download.
 */
$stmt3 = $database->mysqli->prepare("SELECT m.ItemSetID, m.ItemID, m.ItemCount, s.OwnerID, s.Title, s.Type, s.Map, s.Mode, s.Sortrank, s.Champion, IFNULL(v.Rating, 0) AS Rating, s.Date, b.Name, b.recMath, b.minSumLvl, b.maxSumLvl, b.showIfSumSpell, b.hideIfSumSpell, i.Gold, i.Name AS ItemName, i.Description, ibi.BuildsIntoID, ibf.BuildsFromID FROM `ItemSetMap` m JOIN `ItemSets` s ON m.ItemSetID = s.ID JOIN `ItemBlocks` b ON m.BlockID = b.ID JOIN `Items` i ON m.ItemID = i.ID LEFT JOIN `ItemBuildsFrom` ibf ON m.ItemID = ibf.ItemID LEFT JOIN `ItemBuildsInto` ibi ON m.ItemID = ibi.ItemID LEFT JOIN (SELECT ItemsetID, SUM(Rating) AS Rating FROM `Votes` GROUP BY ItemsetID) v ON v.ItemsetID = m.ItemSetID WHERE m.ItemSetID = ?;");
$stmt3->bind_param("i", $_GET["id"]);
$stmt3->execute();
$res = $stmt3->get_result();
$ret = array();
while ($row = $res->fetch_assoc()) {
	array_push($ret, $row);
}

$stmt3->free_result();
$stmt3->close();
$itemset = ItemSet::newSet(collapseResultArray($ret)[$_GET["id"]]);

/**
 * JSON-ify the ItemSet, Rito-style, ready for download
 */
$preJSON = array();
$preJSON["title"] = $itemset->title;
$preJSON["type"] = $itemset->type;
$preJSON["map"] = $itemset->map;
$preJSON["mode"] = $itemset->mode;
$preJSON["priority"] = false;
//$preJSON["sortrank"] = 0;
$preJSON["blocks"] = array();

foreach($itemset->blocks as $block){
	$blockArr = array();
	$blockArr["type"] = $block->Name;
	$blockArr["recMath"] = $block->recMath?true:false;
	$blockArr["minSummonerLevel"] = $block->minSumLvl;
	$blockArr["maxSummonerLevel"] = $block->maxSumLvl;
	$blockArr["showIfSummonerSpell"] = $block->showIfSumSpell;
	$blockArr["hideIfSummonerSpell"] = $block->hideIfSumSpell;

	$blockArr["items"] = array();

	foreach($block->Items as $item){
		$itemArr = array();
		$itemArr["id"] = (string)$item->ItemID;
		$itemArr["count"] = $item->ItemCount;

		array_push($blockArr["items"], $itemArr);
	}

	array_push($preJSON["blocks"], $blockArr);
}
$json = json_encode($preJSON);

/**
 * DOWNLOAD!
 */
if(! isset($_GET['showonly'])){

	header('Pragma: public');
	header('Expires: 0');
	header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
	header('Cache-Control: private', false);
	header('Content-Type: application/json');
	header('Content-Disposition: attachment; filename="'.uniqid().'.json"');
	header('Content-Transfer-Encoding: binary');
	header('Content-Length: ' . strlen($json));
	echo $json;
	exit();

}

/**
 * Didnt want to download? Dont worry, here you go:
 */
echo $json;


?>