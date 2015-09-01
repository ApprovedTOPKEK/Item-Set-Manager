<?php

//Give access to some utility functions/variables.
include_once "GlobalUtils.php";

//Database wrapper for MySQL Calls. Not really simpler than using MySQLi directly, but who cares.
$database = new DBWrapper();

//The user authentification class handles registration and log-in. It has access to the database, and should get its data from $_POST. Constructor handles registration requests.
$userAuth = new UserAuthentification($database);

//Try to log-in with the current $_POST. Sets cookies if needed (Supports sessions)
$userAuth->tryLogin();

//Only for members.
if($userAuth->userGroup == UserAuthentification::$GUEST) {
	setcookie("mustlogin");
	header('Location: index.php');
	exit();
}

//Save set if there is a request. This code is in mysets.php because after creating a set you will be redirected to your sets.
if(isset($_POST["saveset"])){
	$set = json_decode($_POST["setdata"], true);
	if($_POST["saveset"] != "create") delSet($database, $userAuth, $_POST["saveset"]);
	$time = time();
	$stmt = $database->mysqli->prepare("INSERT INTO `ItemSets` (`OwnerID`, `Title`, `Map`, `Mode`, `Champion`, `Date`) VALUES(?, ?, ?, ?, ?, ?);");
	$stmt->bind_param("issssi", $userAuth->id, $set['title'], $set['map'], $set['mode'], $set['champion'], $time);
	$stmt->execute();
	$stmt->close();
	$stmt = $database->mysqli->prepare("SELECT `ID` FROM `ItemSets` WHERE `Date` = ? AND `OwnerID` = ?");
	$stmt->bind_param("ii", $time, $userAuth->id);
	$stmt->execute();
	$sid = $stmt->get_result()->fetch_assoc()["ID"];
	$stmt->close();
	foreach($set['blocks'] as $block) {
		$stmt = $database->mysqli->prepare("INSERT INTO `ItemBlocks` (`Name`, `recMath`) VALUES(?, ?);");
		$stmt->bind_param("si", $block["type"], $block["recMath"]);
		$stmt->execute();
		$bid = $database->mysqli->insert_id;
		$stmt->close();

		foreach($block['items'] as $item){
			$stmt = $database->mysqli->prepare("INSERT INTO `ItemSetMap` (`ItemSetID`, `BlockID`, `ItemID`, `ItemCount`) VALUES (?, ?, ?, ?)");
			$stmt->bind_param("iiii", $sid, $bid, $item['id'], $item['count']);
			$stmt->execute();
			$stmt->close();
		}
	}
}

//HTML Template for the user-specific part on the website
$userTemplate = new Template("www/templates/loggedin_template.html");

//Give the template the possibility to use the UserAuthentification class.
$userTemplate->setVar("UserAuthentification", $userAuth);

//Array mapping a champion name to its icon. Array ( ["ChampionName"] => "Icon", ["ChampionName2"] => "Icon2" )
$champJson = cURL("https://global.api.pvp.net/api/lol/static-data/euw/v1.2/champion?champData=image&api_key=" . $apiKey);
$championData = parseChampJSON($champJson, array("Champion" => new ArrayObject(array("img" => "www/any.png", "name" => "Any Champion", "key" => "Champion"), ArrayObject::ARRAY_AS_PROPS)));
//$spellData = parseSpellJSON(cURL("https://global.api.pvp.net/api/lol/static-data/euw/v1.2/summoner-spell?spellData=image,key&api_key=" . $apiKey));

$res = $database->query("SELECT m.ItemSetID, m.ItemID, m.ItemCount, s.OwnerID, s.Title, s.Type, s.Map, s.Mode, s.Sortrank, s.Champion, IFNULL(v.Rating, 0) AS Rating, s.Date, b.Name, b.recMath, b.minSumLvl, b.maxSumLvl, b.showIfSumSpell, b.hideIfSumSpell, i.Gold, i.Name AS ItemName, i.Description, ibi.BuildsIntoID, ibf.BuildsFromID FROM `ItemSetMap` m JOIN `ItemSets` s ON m.ItemSetID = s.ID JOIN `ItemBlocks` b ON m.BlockID = b.ID JOIN `Items` i ON m.ItemID = i.ID LEFT JOIN `ItemBuildsFrom` ibf ON m.ItemID = ibf.ItemID LEFT JOIN `ItemBuildsInto` ibi ON m.ItemID = ibi.ItemID LEFT JOIN (SELECT ItemsetID, SUM(Rating) AS Rating FROM `Votes` GROUP BY ItemsetID) v ON v.ItemsetID = m.ItemSetID WHERE `OwnerID` = " . $userAuth->id . " ORDER BY `Sortrank` DESC;", false);
$rawSets = collapseResultArray($res);
$sets = array();
foreach($rawSets as $rawSet){
	$set = ItemSet::newSet($rawSet);
	$set->setEditable(true);
	$set->canRate(false);
	$set->canCopy(true);
	array_push($sets, $set);
}

$setListTemplate = new Template("www/templates/home_setList_template.html");
$setListTemplate->setVar("ChampionData", $championData);
//$setListTemplate->setVar("SpellData", $spellData);
$setListTemplate->setVar("ItemSets", $sets);

$headerTemplate = new Template("www/templates/header_template.html");
$headerTemplate->setVar("UserTemplate", $userTemplate);
$headerTemplate->setVar("buttons", array(new ArrayObject(array("name" => "Home", "href" => "index.php"), ArrayObject::ARRAY_AS_PROPS), new ArrayObject(array("name" => "Create Set", "href" => "createset.php"), ArrayObject::ARRAY_AS_PROPS)));


$mysets_template = new Template("www/templates/mysets_template.html");
$mysets_template->setVar("ItemSetTemplate", $setListTemplate);

$footerTemplate = new Template("www/templates/footer_template.html");

$userTemplate->prepare();
$headerTemplate->prepare();

$setListTemplate->prepare();
$mysets_template->prepare();

$footerTemplate->prepare();

echo $headerTemplate->printTemplate();
echo $mysets_template->printTemplate();
echo $footerTemplate->printTemplate();
?>