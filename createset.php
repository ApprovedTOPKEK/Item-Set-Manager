<?php

//Give access to some utility functions/variables.
include_once "GlobalUtils.php";

//Database wrapper for MySQL Calls. Not really simpler than using MySQLi directly, but who cares.
$database = new DBWrapper();

//The user authentification class handles registration and log-in. It has access to the database, and should get its data from $_POST. Constructor handles registration requests.
$userAuth = new UserAuthentification($database);

//Try to log-in with the current $_POST. Sets cookies if needed (Supports sessions)
$userAuth->tryLogin();

//Only viewable by members.
if ($userAuth->userGroup == UserAuthentification::$GUEST) {
	setcookie("mustlogin");
	header('Location: index.php');
	exit();
}

//Itemset displayed in the creator. Empty by default.
$itemset = new ItemSet();

//How will the ItemSet be saved? On a new ID, or based on an old one? create = new, some id = old one
$type = "create";

if(isset($_GET["edit"])){
	$type=$_GET["edit"];
	$stmt3 = $database->mysqli->prepare("SELECT m.ItemSetID, m.ItemID, m.ItemCount, s.OwnerID, s.Title, s.Type, s.Map, s.Mode, s.Sortrank, s.Champion, IFNULL(v.Rating, 0) AS Rating, s.Date, b.Name, b.recMath, b.minSumLvl, b.maxSumLvl, b.showIfSumSpell, b.hideIfSumSpell, i.Gold, i.Name AS ItemName, i.Description, ibi.BuildsIntoID, ibf.BuildsFromID FROM `ItemSetMap` m JOIN `ItemSets` s ON m.ItemSetID = s.ID JOIN `ItemBlocks` b ON m.BlockID = b.ID JOIN `Items` i ON m.ItemID = i.ID LEFT JOIN `ItemBuildsFrom` ibf ON m.ItemID = ibf.ItemID LEFT JOIN `ItemBuildsInto` ibi ON m.ItemID = ibi.ItemID LEFT JOIN (SELECT ItemsetID, SUM(Rating) AS Rating FROM `Votes` GROUP BY ItemsetID) v ON v.ItemsetID = m.ItemSetID WHERE m.ItemSetID = ?;");
	$stmt3->bind_param("i", $_GET["edit"]);
	$stmt3->execute();
	$res = $stmt3->get_result();
	$ret = array();
	while ($row = $res->fetch_assoc()) {
		array_push($ret, $row);
	}

	$stmt3->free_result();
	$stmt3->close();
	$itemset = ItemSet::newSet(collapseResultArray($ret)[$_GET["edit"]]);
}elseif(isset($_GET["upload"])){

}elseif(isset($_GET["copy"])){
	$stmt3 = $database->mysqli->prepare("SELECT m.ItemSetID, m.ItemID, m.ItemCount, s.OwnerID, s.Title, s.Type, s.Map, s.Mode, s.Sortrank, s.Champion, IFNULL(v.Rating, 0) AS Rating, s.Date, b.Name, b.recMath, b.minSumLvl, b.maxSumLvl, b.showIfSumSpell, b.hideIfSumSpell, i.Gold, i.Name AS ItemName, i.Description, ibi.BuildsIntoID, ibf.BuildsFromID FROM `ItemSetMap` m JOIN `ItemSets` s ON m.ItemSetID = s.ID JOIN `ItemBlocks` b ON m.BlockID = b.ID JOIN `Items` i ON m.ItemID = i.ID LEFT JOIN `ItemBuildsFrom` ibf ON m.ItemID = ibf.ItemID LEFT JOIN `ItemBuildsInto` ibi ON m.ItemID = ibi.ItemID LEFT JOIN (SELECT ItemsetID, SUM(Rating) AS Rating FROM `Votes` GROUP BY ItemsetID) v ON v.ItemsetID = m.ItemSetID WHERE m.ItemSetID = ?;");
	$stmt3->bind_param("i", $_GET["copy"]);
	$stmt3->execute();
	$res = $stmt3->get_result();
	$ret = array();
	while ($row = $res->fetch_assoc()) {
		array_push($ret, $row);
	}

	$stmt3->free_result();
	$stmt3->close();
	$itemset = ItemSet::newSet(collapseResultArray($ret)[$_GET["copy"]]);
}

//HTML Template for the user-specific part on the website
$userTemplate = new Template("www/templates/loggedin_template.html");

//Give the template the possibility to use the UserAuthentification class.
$userTemplate->setVar("UserAuthentification", $userAuth);

//Array mapping a champion name to its icon. Array ( ["ChampionName"] => "Icon", ["ChampionName2"] => "Icon2" )
$champJson = cURL("https://global.api.pvp.net/api/lol/static-data/euw/v1.2/champion?champData=image&api_key=" . $apiKey);
$championData = parseChampJSON($champJson, array("Champion" => new ArrayObject(array("img" => "www/any.png", "name" => "Any Champion", "key" => "Champion"), ArrayObject::ARRAY_AS_PROPS)));
//$spellData = parseSpellJSON(cURL("https://global.api.pvp.net/api/lol/static-data/euw/v1.2/summoner-spell?spellData=image,key&api_key=" . $apiKey));

//Get all those items...
$itemdata = recursiveArrayObject(collapseResultArray2($database->query("SELECT i.ID AS ItemID, i.Gold, i.Name AS ItemName, i.Description, ibi.BuildsIntoID, ibf.BuildsFromID, it.Tag FROM `Items` i LEFT JOIN `ItemBuildsFrom` ibf ON i.ID = ibf.ItemID LEFT JOIN `ItemBuildsInto` ibi ON i.ID = ibi.ItemID LEFT JOIN `ItemTags` it ON i.ID = it.ItemID"), true, true, true), ArrayObject::ARRAY_AS_PROPS);

$headerTemplate = new Template("www/templates/header_template.html");
$headerTemplate->setVar("UserTemplate", $userTemplate);
$headerTemplate->setVar("buttons", array(new ArrayObject(array("name" => "Home", "href" => "index.php"), ArrayObject::ARRAY_AS_PROPS), new ArrayObject(array("name" => "My Sets", "href" => "mysets.php"), ArrayObject::ARRAY_AS_PROPS)));

//And then, printerino!
$csTemplate = new Template("www/templates/createset_template.html");
$csTemplate->setVar("ChampionData", $championData);
$csTemplate->setVar("ItemSet", $itemset);
//$csTemplate->setVar("SpellData", $spellData);
$csTemplate->setVar("Items", $itemdata);
$csTemplate->setVar("i", 1);
$csTemplate->setVar("TypusMaximusWaddafakius", $type);
$csTemplate->setVar("ChampionData", $championData);

$footerTemplate = new Template("www/templates/footer_template.html");

$userTemplate->prepare();
$headerTemplate->prepare();

$csTemplate->prepare();

$footerTemplate->prepare();

echo $headerTemplate->printTemplate();
echo $csTemplate->printTemplate();
echo $footerTemplate->printTemplate();