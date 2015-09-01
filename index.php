<?php

//Give access to some utility functions/variables.
include_once "GlobalUtils.php";

//Database wrapper for MySQL Calls. Not that much simpler than just using MySQLi, but maybe a bit.
$database = new DBWrapper();

//The user authentification class handles registration and log-in. It has access to the database, and should get its data from $_POST. Constructor handles registration requests.
$userAuth = new UserAuthentification($database);

//Try to log-in with the current $_POST. Sets cookies if needed (Supports sessions)
$userAuth->tryLogin();

//HTML Template for the user-specific part on the website, depending on wether the user is logged in or not.
$userTemplate = new Template($userAuth->userGroup != UserAuthentification::$GUEST ? "www/templates/loggedin_template.html" : "www/templates/register_template.html");

//Give the template the possibility to use the UserAuthentification class.
$userTemplate->setVar("UserAuthentification", $userAuth);

//If there is an AJAX Request for the user-part-only, print the user template and exit. UNUSED
if(isset($_POST['userAjax'])){
	$userTemplate->prepare();
	$userTemplate->printTemplate();
	exit();
}

//Want to delete an itemset?
if(isset($_POST["delete"])){
	delSet($database, $userAuth, $_POST["delete"]);
	exit();
}

//Array mapping a champion name to its icon and other data.
$champJson = cURL("https://global.api.pvp.net/api/lol/static-data/euw/v1.2/champion?champData=image&api_key=" . $apiKey);
$championData = parseChampJSON($champJson, array("Champion"=> new ArrayObject(array("img" => "www/any.png", "name" => "Any Champion", "key" => "Champion"), ArrayObject::ARRAY_AS_PROPS)));

//UNUSED
//$spellData = parseSpellJSON(cURL("https://global.api.pvp.net/api/lol/static-data/euw/v1.2/summoner-spell?spellData=image,key&api_key=" . $apiKey));

/**
 * HANDLE RATING. Secure, of course.
 */
if (isset($_POST["voteid"])) {
	$stmt = $database->mysqli->prepare("SELECT `Rating` FROM `Votes` WHERE `UserID`=? AND `ItemsetID` = ?;");
	$stmt->bind_param("ii", $userAuth->id, $_POST["voteid"]);
	$stmt->execute();
	$stmt->store_result();
	if ($stmt->num_rows == 0 && (isset($_GET["upvote"]) || isset($_GET["downvote"]))) {
		$stmt2 = $database->mysqli->prepare("INSERT INTO `Votes` (`UserID`, `ItemsetID`, `Rating`) VALUES(?, ?, ?);");
		$num;
		if (isset($_GET["upvote"])) {
			$num = 1;
		} elseif (isset($_GET["downvote"])) {
			$num = -1;
		}
		$stmt2->bind_param("iii", $userAuth->id, $_POST["voteid"], $num);
		$stmt2->execute();

		$stmt2->close();

		$stmt3 = $database->mysqli->prepare("SELECT m.ItemSetID, m.ItemID, m.ItemCount, s.OwnerID, s.Title, s.Type, s.Map, s.Mode, s.Sortrank, s.Champion, IFNULL(v.Rating, 0) AS Rating, s.Date, b.Name, b.recMath, b.minSumLvl, b.maxSumLvl, b.showIfSumSpell, b.hideIfSumSpell, i.Gold, i.Name AS ItemName, i.Description, ibi.BuildsIntoID, ibf.BuildsFromID FROM `ItemSetMap` m JOIN `ItemSets` s ON m.ItemSetID = s.ID JOIN `ItemBlocks` b ON m.BlockID = b.ID JOIN `Items` i ON m.ItemID = i.ID LEFT JOIN `ItemBuildsFrom` ibf ON m.ItemID = ibf.ItemID LEFT JOIN `ItemBuildsInto` ibi ON m.ItemID = ibi.ItemID LEFT JOIN (SELECT ItemsetID, SUM(Rating) AS Rating FROM `Votes` GROUP BY ItemsetID) v ON v.ItemsetID = m.ItemSetID WHERE m.ItemSetID = ?;");
		$stmt3->bind_param("i", $_POST["voteid"]);
		$stmt3->execute();
		$res = $stmt3->get_result();
		$ret = array();
		while ($row = $res->fetch_assoc()) {
			array_push($ret, $row);
		}


		$stmt3->free_result();
		$stmt3->close();
		$itemset = newItemSet($database, $userAuth, collapseResultArray($ret)[$_POST["voteid"]]);
		$itemsetTemplate = new Template("www/templates/itemset_template.html");
		$itemsetTemplate->setVar("ChampionData", $championData);
		$itemsetTemplate->setVar("SpellData", $spellData);
		$itemsetTemplate->setVar("currentItemset", $itemset);
		$itemsetTemplate->prepare();
		echo $itemsetTemplate->printTemplate();
		exit();
	}
	$stmt->free_result();
	$stmt->close();
}



//Prepare $_GET Variables to fit the database query
$orderByRating = false;
$orderByDate = false;
if (isset($_GET['itemsetRating']) && ($_GET['itemsetRating'] == "asc" || $_GET['itemsetRating'] == "desc")) $orderByRating = true; //Maybe I should just let the DBWrapper itself check such things...
if (isset($_GET['itemsetDate']) && ($_GET['itemsetDate'] == "asc" || $_GET['itemsetDate'] == "desc")) $orderByDate = true;
if(!isset($_GET['itemsetChampion']) || $_GET['itemsetChampion'] == "any" || $_GET["itemsetChampion"] == "Champion" || ! in_array($_GET['itemsetChampion'], array_keys($championData->getArrayCopy()))) $_GET['itemsetChampion'] = "Champion"; //Nasty MySQL Hack
if(!isset($_GET['itemsetResultAmount']) || !is_int($_GET['itemsetResultAmount']) || !intInRange($_GET['itemsetResultAmount'], 5, 100)) $_GET['itemsetResultAmount'] = 20;
if(!isset($_GET['itemsetStartIndex']) || !is_int($_GET['itemsetStartIndex'])) $_GET['itemsetStartIndex'] = 0;
//Start building query
$query = "SELECT m.ItemSetID, m.ItemID, m.ItemCount, s.OwnerID, s.Title, s.Type, s.Map, s.Mode, s.Sortrank, s.Champion, IFNULL(v.Rating, 0) AS Rating, s.Date, b.Name, b.recMath, b.minSumLvl, b.maxSumLvl, b.showIfSumSpell, b.hideIfSumSpell, i.Gold, i.Name AS ItemName, i.Description, ibi.BuildsIntoID, ibf.BuildsFromID FROM `ItemSetMap` m JOIN `ItemSets` s ON m.ItemSetID = s.ID JOIN `ItemBlocks` b ON m.BlockID = b.ID JOIN `Items` i ON m.ItemID = i.ID LEFT JOIN `ItemBuildsFrom` ibf ON m.ItemID = ibf.ItemID LEFT JOIN `ItemBuildsInto` ibi ON m.ItemID = ibi.ItemID LEFT JOIN (SELECT ItemsetID, SUM(Rating) AS Rating FROM `Votes` GROUP BY ItemsetID) v ON v.ItemsetID = m.ItemSetID";
if($_GET['itemsetChampion'] != "Champion") $query .= " WHERE `Champion`='".$_GET['itemsetChampion']."'"; /*SELECT m.ItemSetID, m.ItemID, m.ItemCount, s.OwnerID, s.Title, s.Type, s.Map, s.Mode, s.Sortrank, s.Champion, s.Rating, s.Date, b.Name, b.recMath, b.minSumLvl, b.maxSumLvl, b.showIfSumSpell, b.hideIfSumSpell, i.Gold, i.Image, i.Name AS ItemName, i.Description FROM `ItemSetMap` m JOIN `ItemSets` s ON m.ItemSetID = s.ID JOIN `ItemBlocks` b ON m.BlockID = b.ID JOIN `Items` i ON m.ItemID = i.ID WHERE `Champion`='".$_GET['itemsetChampion']."'*/
if($orderByDate && $orderByRating) $query .= " ORDER BY `Date` ".$_GET['itemsetDate'].", `Rating` ".$_GET['itemsetRating'];
elseif($orderByDate && !$orderByRating) $query .= " ORDER BY `Date` " . $_GET['itemsetDate'];
elseif($orderByRating && !$orderByDate) $query .= " ORDER BY `Rating` " . $_GET['itemsetRating'];
//$query .= " LIMIT ".$_GET["itemsetStartIndex"].", ".$_GET['itemsetResultAmount']; TODO NEEDS SOME FIXING UP. DISTINCT SETS ONLY...

//Request the itemset with the prepared arguments
$rawItemSets = collapseResultArray($database->query($query));//mysql query sorting by PHP Post Parameters: Range (Page #), Rating, champ, date, etc. Make the mysql wrapper in a way that this returns an array, not some weird MySQL stuff

//Create an array of actual ItemSet Objects.
$twentyItemSets = array();
foreach($rawItemSets as $val){
	array_push($twentyItemSets, newItemSet($database, $userAuth, $val));
}

$setListTemplate = new Template("www/templates/home_setList_template.html");
$setListTemplate->setVar("ChampionData", $championData);
//$setListTemplate->setVar("SpellData", $spellData);
$setListTemplate->setVar("CurrentChampion", $_GET["itemsetChampion"]);
$setListTemplate->setVar("ItemSets", $twentyItemSets);

//Itemset-Ajax for example when updating search parameters.
if(isset($_POST['itemsetAjax'])){
	$setListTemplate->prepare();
	echo $setListTemplate->printTemplate();
	exit();
}

//Print all that stuff

$headerTemplate = new Template("www/templates/header_template.html");
$headerTemplate->setVar("UserTemplate", $userTemplate);
$headerTemplate->setVar("buttons", array(new ArrayObject(array("name" => "My Sets", "href" => "mysets.php"), ArrayObject::ARRAY_AS_PROPS), new ArrayObject(array("name" => "Create Set", "href" => "createset.php"), ArrayObject::ARRAY_AS_PROPS)));

$homeTemplate = new Template("www/templates/homepage_template.html");
$homeTemplate->setVar("ChampionData", $championData);
$homeTemplate->setVar("ItemSetTemplate", $setListTemplate);

$footerTemplate = new Template("www/templates/footer_template.html");

$userTemplate->prepare();
$headerTemplate->prepare();

$setListTemplate->prepare();
$homeTemplate->prepare();

$footerTemplate->prepare();

echo $headerTemplate->printTemplate();
echo $homeTemplate->printTemplate();
echo $footerTemplate->printTemplate();


?>