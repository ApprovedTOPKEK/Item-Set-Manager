<?php

/**
 * Bunch of utility functions I don't want to define somewhere else.
 */


/**
 * @param $link - URL whose content shall be retrieved
 * @return mixed - Site-Content
 * Function to get contents of some URL, for example API-Calls.
 */
function cURL($link)
{
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $link);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
	$data = curl_exec($ch);
	curl_close($ch);
	return $data;
}

/**
 * @param $json - Riot-API-JSON
 * @param array $retArr - If the array shall already contain some default values, specify one here.
 * @param bool $onlySprites - Unused
 * @return ArrayObject
 */
function parseChampJSON($json, $retArr = array(), $onlySprites = false)
{
	$arr = json_decode($json, true);
	foreach ($arr["data"] as $value) {
		if($onlySprites){
			$retArr[$value["key"]] = "http://ddragon.leagueoflegends.com/cdn/5.16.1/img/champion/" . $value["key"].".png";
			continue;
		}
		$retArr[$value["key"]] = new ArrayObject(array("img" => "http://ddragon.leagueoflegends.com/cdn/5.16.1/img/champion/" . $value["key"].".png", "name" => $value["name"], "key" => $value["key"]), ArrayObject::ARRAY_AS_PROPS);
	}
	$retObj = new ArrayObject($retArr);
	$retObj->setFlags(ArrayObject::ARRAY_AS_PROPS);
	return $retObj;
}

/**
 * UNUSED
 * @param $json - Riot-API-JSON
 * @param array $retArr - If the array shall already contain some default values, specify one here.
 * @param bool $onlySprites - Unused
 * @return ArrayObject
 */
function parseSpellJSON($json, $onlySprites = false)
{
	$retArr = array();
	$arr = json_decode($json, true);
	foreach ($arr["data"] as $value) {
		if ($onlySprites) {
			$retArr[$value["key"]] = "http://ddragon.leagueoflegends.com/cdn/5.16.1/img/spell/" . $value["key"].".png";
			continue;
		}
		$retArr[$value["key"]] = new ArrayObject(array("img" => "http://ddragon.leagueoflegends.com/cdn/5.16.1/img/spell/" . $value["key"].".png", "name" => $value["name"], "key" => $value["key"]), ArrayObject::ARRAY_AS_PROPS);
	}
	$retObj = new ArrayObject($retArr);
	$retObj->setFlags(ArrayObject::ARRAY_AS_PROPS);
	return $retObj;
}

/**
 * Retrieve all Items and store them in own Database (Reason: Riot-API is so slow, unfortunately...)
 * @param $db - Database
 */
function saveItems($db){
	global $apiKey;
	$arr = json_decode(cURL("https://global.api.pvp.net/api/lol/static-data/euw/v1.2/item?itemListData=gold&api_key=".$apiKey), true);
	foreach($arr["data"] as $val){
		$stmt = $db->mysqli->prepare("INSERT INTO `Items` (`ID`, `Gold`, `Image`, `Name`, `Description`) VALUES(?, ?, ?, ?, ?);");
		$url = "http://lkimg.zamimg.com/images/v2/items/icons/size32x32/" . $val["id"] . ".png";
		$stmt->bind_param("iisss", $val["id"], $val["gold"]["total"], $url, $val["name"], $val["description"]);
		$stmt->execute();
		$stmt->close();
	}
}

/**
 * Retrieve even more specific Item-Data and store them. See above, "saveItems"
 * @param $db - Database
 */
function saveItemData($db){
	global $apiKey;
	$arr = json_decode(cURL("https://global.api.pvp.net/api/lol/static-data/euw/v1.2/item?itemListData=from,into,tags&api_key=" . $apiKey), true);
	foreach($arr["data"] as $val){
		if(! empty($val["tags"])){
			prettyPrint($val["tags"]);
			foreach($val["tags"] as $tag) {
				$stmt = $db->mysqli->prepare("INSERT INTO `ItemTags` (`ItemID`, `Tag`) VALUES (?, ?)");
				$stmt->bind_param("is", $val["id"], $tag);
				$stmt->execute();
				$stmt->close();
			}
		}
		if(! empty($val["from"])){
			foreach($val["from"] as $from){
				$stmt = $db->mysqli->prepare("INSERT INTO `ItemBuildsFrom` (`ItemID`, `BuildsFromID`) VALUES (?, ?)");
				$stmt->bind_param("is", $val["id"], $from);
				$stmt->execute();
				$stmt->close();
			}
		}
		if (!empty($val["into"])) {
			foreach ($val["into"] as $into) {
				$stmt = $db->mysqli->prepare("INSERT INTO `ItemBuildsInto` (`ItemID`, `BuildsIntoID`) VALUES (?, ?)");
				$stmt->bind_param("is", $val["id"], $into);
				$stmt->execute();
				$stmt->close();
			}
		}
	}
}

/**
 * Is an integer in the range of two others?
 * @param $integer
 * @param $min
 * @param $max
 * @return bool
 */
function intInRange($integer, $min, $max){
	return $integer >= $min && $integer <= $max;
}

/**
 * Simplified call in order to let the Templating System transform ArrayObjects to JSON.
 * @param $arrobj - PHP ArrayObject
 * @return string - JSON
 */
function arrobjtojson($arrobj){
	$arr = $arrobj->getArrayCopy();
	return json_encode($arr);
}

/**
 * Also just a simple call wrapper for the templating system.
 * @param $string
 * @return mixed
 */
function escapeQuotes($string){
	return str_replace("\"", "&#34;", $string);
}

/**
 * Sorts an array of items first by cost and then alphabetically
 * @param $items
 * @return ArrayObject
 */
function sortItems($items){
	$sort = array();
	$arr = $items->getArrayCopy();
	foreach ($arr as $k => $v) {
		$sort['Gold'][$k] = $v['Gold'];
		$sort['Name'][$k] = $v['Name'];
	}
	array_multisort($sort['Gold'], SORT_ASC, $sort['Name'], SORT_ASC, $arr);
	return recursiveArrayObject($arr, ArrayObject::ARRAY_AS_PROPS);
}

/**
 * Array-Debugging made easy
 * @param $a
 */
function prettyPrint($a)
{
	echo '<pre>' . print_r($a, 1) . '</pre>';
}

/**
 * Recursively searches for Arrays in an array, in order to convert them to PHP ArrayObjects
 * @param $array
 * @param int $flags
 * @return ArrayObject
 */
function recursiveArrayObject($array, $flags = 0){
	foreach($array as $k => $val){
		if(is_array($val)) $array[$k] = recursiveArrayObject($val, $flags);
	}
	return new ArrayObject($array, $flags);
}

/**
 * ItemSet-MySQL-Queries give back an array of rows containing some data multiple times. This function "collapses" those arrays, to avoid repetition of data and allow easier "data-extraction".
 * @param $mysqlRes
 */
function collapseResultArray($mysqlRes, $buildsInto = true, $buildsFrom = true, $tags = false)
{
	$returnArray = array();
	foreach ($mysqlRes as $row) {
		if (!isset($returnArray[$row['ItemSetID']])) {
			$returnArray[$row['ItemSetID']] = array();
			$returnArray[$row["ItemSetID"]]["Blocks"] = array();
		}
		$returnArray[$row['ItemSetID']]["ID"] = $row['ItemSetID'];
		$returnArray[$row['ItemSetID']]["OwnerID"] = $row["OwnerID"];
		$returnArray[$row['ItemSetID']]["Title"] = $row["Title"];
		$returnArray[$row['ItemSetID']]["Type"] = $row["Type"];
		$returnArray[$row['ItemSetID']]["Map"] = $row["Map"];
		$returnArray[$row['ItemSetID']]["Mode"] = $row["Mode"];
		$returnArray[$row['ItemSetID']]["Sortrank"] = $row["Sortrank"];
		$returnArray[$row['ItemSetID']]["Champion"] = $row["Champion"];
		$returnArray[$row['ItemSetID']]["Rating"] = $row["Rating"];

		if (!isset($returnArray[$row["ItemSetID"]]["Blocks"][$row["Name"]])) {
			$returnArray[$row["ItemSetID"]]["Blocks"][$row["Name"]] = array();
			$returnArray[$row["ItemSetID"]]["Blocks"][$row["Name"]]["Items"] = array();
		}
		$returnArray[$row["ItemSetID"]]["Blocks"][$row["Name"]]["Name"] = $row["Name"];
		$returnArray[$row["ItemSetID"]]["Blocks"][$row["Name"]]["recMath"] = $row["recMath"];
		$returnArray[$row["ItemSetID"]]["Blocks"][$row["Name"]]["minSumLvl"] = $row["minSumLvl"];
		$returnArray[$row["ItemSetID"]]["Blocks"][$row["Name"]]["maxSumLvl"] = $row["maxSumLvl"];
		$returnArray[$row["ItemSetID"]]["Blocks"][$row["Name"]]["showIfSumSpell"] = $row["showIfSumSpell"];
		$returnArray[$row["ItemSetID"]]["Blocks"][$row["Name"]]["hideIfSumSpell"] = $row["hideIfSumSpell"];

		if (!isset($returnArray[$row["ItemSetID"]]["Blocks"][$row["Name"]]["Items"][$row["ItemID"]])) {
			$returnArray[$row["ItemSetID"]]["Blocks"][$row["Name"]]["Items"][$row["ItemID"]] = array();
			if ($buildsInto) $returnArray[$row["ItemSetID"]]["Blocks"][$row["Name"]]["Items"][$row["ItemID"]]["BuildsInto"] = array();
			if ($buildsFrom) $returnArray[$row["ItemSetID"]]["Blocks"][$row["Name"]]["Items"][$row["ItemID"]]["BuildsFrom"] = array();
			if ($tags) $returnArray[$row["ItemSetID"]]["Blocks"][$row["Name"]]["Items"][$row["ItemID"]]["Tags"] = array();
		}
		$returnArray[$row["ItemSetID"]]["Blocks"][$row["Name"]]["Items"][$row["ItemID"]]["ItemID"] = $row["ItemID"];
		$returnArray[$row["ItemSetID"]]["Blocks"][$row["Name"]]["Items"][$row["ItemID"]]["ItemCount"] = $row["ItemCount"];
		$returnArray[$row["ItemSetID"]]["Blocks"][$row["Name"]]["Items"][$row["ItemID"]]["Gold"] = str_pad($row["Gold"], 4, " ", STR_PAD_LEFT);
		$returnArray[$row["ItemSetID"]]["Blocks"][$row["Name"]]["Items"][$row["ItemID"]]["Name"] = $row["ItemName"];
		//$returnArray[$row["ItemSetID"]]["Blocks"][$row["Name"]]["Items"][$row["ItemID"]]["Image"] = $row["Image"];
		$returnArray[$row["ItemSetID"]]["Blocks"][$row["Name"]]["Items"][$row["ItemID"]]["Description"] = $row["Description"];

		if ($buildsFrom && !empty($row["BuildsFromID"]) && !in_array($row["BuildsFromID"], $returnArray[$row["ItemSetID"]]["Blocks"][$row["Name"]]["Items"][$row["ItemID"]]["BuildsFrom"]))
			array_push($returnArray[$row["ItemSetID"]]["Blocks"][$row["Name"]]["Items"][$row["ItemID"]]["BuildsFrom"], $row["BuildsFromID"]);
		if ($buildsInto && !empty($row["BuildsIntoID"]) && !in_array($row["BuildsIntoID"], $returnArray[$row["ItemSetID"]]["Blocks"][$row["Name"]]["Items"][$row["ItemID"]]["BuildsInto"]))
			array_push($returnArray[$row["ItemSetID"]]["Blocks"][$row["Name"]]["Items"][$row["ItemID"]]["BuildsInto"], $row["BuildsIntoID"]);
		if ($tags && !empty($row["Tag"]) && !in_array($row["Tag"], $returnArray[$row["ItemSetID"]]["Blocks"][$row["Name"]]["Items"][$row["ItemID"]]["Tags"]))
			array_push($returnArray[$row["ItemSetID"]]["Blocks"][$row["Name"]]["Items"][$row["ItemID"]]["Tags"], $row["Tag"]);


	}
	return $returnArray;
}

/**
 * Creates an ItemSet and adjusts it depending on the user.
 * @param $database
 * @param $userAuth
 * @param $val
 * @return ItemSet
 */
function newItemSet($database, $userAuth, $val){
	$set = ItemSet::newSet($val);
	$set->setEditable($set->ownerID == $userAuth->id || $userAuth->userGroup == UserAuthentification::$ADMIN);
	$res = $database->query("SELECT `Rating` FROM `Votes` WHERE `UserID`=" . $userAuth->id . " AND `ItemsetID` = " . $set->ID . ";");
	$set->canRate($userAuth->userGroup != UserAuthentification::$GUEST && $set->ownerID != $userAuth->id && empty($res));
	$set->canCopy($userAuth->id != UserAuthentification::$GUEST);
	return $set;
}

/**
 * Riot API Item-Descriptions contain unclosed tags - Fix it. Simple but works.
 * @param $html
 * @return mixed
 */
function closetag($html)
{
	$html_new = $html;
	preg_match_all("#<([a-z]+)( .*)?(?!/)>#iU", $html, $result1);
	preg_match_all("#</([a-z]+)>#iU", $html, $result2);
	$results_start = $result1[1];
	$results_end = $result2[1];
	foreach ($results_start AS $startag) {
		if (!in_array($startag, $results_end) && $startag != "br") {
			$html_new = str_replace('<' . $startag . '>', '', $html_new);
		}
	}
	foreach ($results_end AS $endtag) {
		if (!in_array($endtag, $results_start) && $endtag != "br") {
			$html_new = str_replace('</' . $endtag . '>', '', $html_new);
		}
	}
	return $html_new;
}

/**
 * For the smaller purpose. (No blocks, no itemsets: Only Items.)
 * @param $mysqlRes
 * @param bool $buildsInto
 * @param bool $buildsFrom
 * @param bool $tags
 * @return array
 */
function collapseResultArray2($mysqlRes, $buildsInto = true, $buildsFrom = true, $tags = false)
{
	$returnArray = array();
	foreach ($mysqlRes as $row) {

		if (!isset($returnArray[$row["ItemID"]])) {
			$returnArray[$row["ItemID"]] = array();
			if ($buildsInto) $returnArray[$row["ItemID"]]["BuildsInto"] = array();
			if ($buildsFrom) $returnArray[$row["ItemID"]]["BuildsFrom"] = array();
			if ($tags) $returnArray[$row["ItemID"]]["Tags"] = array();
		}
		$returnArray[$row["ItemID"]]["ItemID"] = $row["ItemID"];
		$returnArray[$row["ItemID"]]["Gold"] = str_pad($row["Gold"], 4, " ", STR_PAD_LEFT);
		$returnArray[$row["ItemID"]]["Name"] = $row["ItemName"];
		$returnArray[$row["ItemID"]]["Description"] = $row["Description"];

		if ($buildsFrom && !empty($row["BuildsFromID"]) && !in_array($row["BuildsFromID"], $returnArray[$row["ItemID"]]["BuildsFrom"]))
			array_push($returnArray[$row["ItemID"]]["BuildsFrom"], $row["BuildsFromID"]);
		if ($buildsInto && !empty($row["BuildsIntoID"]) && !in_array($row["BuildsIntoID"], $returnArray[$row["ItemID"]]["BuildsInto"]))
			array_push($returnArray[$row["ItemID"]]["BuildsInto"], $row["BuildsIntoID"]);
		if ($tags && !empty($row["Tag"]) && !in_array($row["Tag"], $returnArray[$row["ItemID"]]["Tags"]))
			array_push($returnArray[$row["ItemID"]]["Tags"], $row["Tag"]);


	}
	return $returnArray;
}

/**
 * Debug used by templating
 * @param $i
 * @return mixed
 */
function pp($i){
	return $i+1;
}

/**
 * Debug used by templating
 * @param $i
 * @return mixed
 */
function mm($i){
	return $i--;
}

/**
 * Delete an ItemSet with ID $delete.
 * @param $database
 * @param $userAuth
 * @param $delete
 */
function delSet($database, $userAuth, $delete){
	$stmt0 = $database->mysqli->prepare("SELECT i.Sortrank FROM `ItemSets` i JOIN `Users` u ON u.ID=? WHERE i.ID=? AND (i.OwnerID=? OR u.Group = 2);");
	$stmt0->bind_param("iii", $userAuth->id, $delete, $userAuth->id);
	$stmt0->execute();
	$stmt0->store_result();
	if ($stmt0->num_rows < 1) die();
	$stmt1 = $database->mysqli->prepare("DELETE FROM `ItemSets` WHERE `ID`=?;");
	$stmt1->bind_param("i", $delete);
	$stmt1->execute();
	$stmt1->close();
	$stmt2 = $database->mysqli->prepare("DELETE FROM `ItemSetMap` WHERE `ItemSetID`=?");
	$stmt2->bind_param("i", $delete);
	$stmt2->execute();
	$stmt2->close();
}

//Include classes
include_once "DBWrapper.php";
include_once "UserAuthentification.php";
include_once "Template.php";
include_once "ItemSet.php";
include "credentials.php";
?>