<?php

/**
 * Class UserAuthentification
 *
 * Logs users in depending on sessions, cookies, and requests.
 * + 3 User groups: GUEST, USER and ADMIN.
 */
class UserAuthentification {

	public static $GUEST = 0, $USER = 1, $ADMIN = 2;

	/**
	 * String representation of UserGroups. Should be in a settings-class or something, but it's fine here aswell.
	 * @return string
	 */
	public function group(){
		switch($this->userGroup){
			case UserAuthentification::$ADMIN: return "Administrator";
			case UserAuthentification::$USER: return "User";
		}
		return "Guest";
	}

	/**
	 * All those beautiful variables.
	 */
	public $showRegisteredMessage = false;
	public $showMustLoginMessage = false;
	public $showWrongCredentialsMessage = false;

	public $loggedIn, $id = 0, $name = "", $mail = "", $userGroup = 0;

	private $database;

	public function __construct(&$database){
		$this->database = $database;
	}

	/**
	 * This is where all the magic happens:
	 * + If there is a logout-request, log-out.
	 * + If an user tried to visit a page without permission, show message.
	 * + Check for Session-Cookies to log the user in when he switches page.
	 * + Check for Login-Requests & set cookies
	 * + Check for Registration-Requests.
	 *
	 * + EVERYTHING IS SECURE
	 */
	public function tryLogin(){
		if(isset($_POST["logout"])){
			setcookie("F_RAPIC2_SID", null, 1);
			return;
		}
		if(isset($_COOKIE["mustlogin"])){
			setcookie("mustlogin", null, 1);
			$this->showMustLoginMessage = true;
			return;
		}

		//Login Requests: Check Cookies, and then $_POST
		if(isset($_COOKIE["F_RAPIC2_SID"])){
			$stmt = $this->database->mysqli->prepare("SELECT `Created`, `Lifetime`, `UserID` FROM `Sessions` WHERE `ID`=?;");
			echo $this->database->mysqli->error;
			$stmt->bind_param("i", $_COOKIE["F_RAPIC2_SID"]);
			$stmt->execute();
			$stmt->store_result();
			$stmt->bind_result($c1, $c2, $c3);
			$stmt->fetch();

			//No result; Session ID does not exist.
			if($stmt->num_rows == 0 || time() - $c1 > $c2){
				setcookie("F_RAPIC2_SID", null, 1);
			}else{
				$this->id = $c3;
				$this->loggedIn = true;

				$mysqlStmt = $this->database->mysqli->prepare("SELECT `Name`, `Mail`, `Group` FROM `Users` WHERE `ID` = ?;");
				$mysqlStmt->bind_param("i", $this->id);
				$mysqlStmt->execute();
				$mysqlStmt->store_result();
				$mysqlStmt->bind_result($col1, $col2, $col3);
				$mysqlStmt->fetch();
				$this->name = $col1;
				$this->mail = $col2;
				$this->userGroup = $col3;
				$mysqlStmt->free_result();
				$mysqlStmt->close();
			}

			$stmt->free_result();
			$stmt->close();

		}else if(isset($_POST["Username"]) && isset($_POST["Password"])){
			$mysqlStmt = $this->database->mysqli->prepare("SELECT `ID`, `Name`, `Mail`, `Group` FROM `Users` WHERE `Name` = ? AND `Password` = ?;");
			$mysqlStmt->bind_param("ss", $_POST["Username"], md5($_POST["Password"].md5($_POST["Username"])));
			$mysqlStmt->execute();
			$mysqlStmt->store_result();
			if($mysqlStmt->num_rows == 1) {
				$mysqlStmt->bind_result($col1, $col2, $col3, $col4);
				$mysqlStmt->fetch();
				$this->id = $col1;
				$this->name = $col2;
				$this->mail = $col3;
				$this->userGroup = $col4;
				$this->loggedIn = true;
				$this->database->mysqli->query("INSERT INTO `Sessions` (`Created`, `Lifetime`, `UserID`) VALUES (".time().", ".(60*60*24*5).", ".$this->id.")");
				$q = $this->database->query("SELECT `ID` FROM `Sessions` WHERE `UserID`=" . $this->id . " ORDER BY `ID` DESC LIMIT 1;");
				setcookie("F_RAPIC2_SID", $q[0]["ID"], time() + 60*60*24*365*10);
				return;
			}
			$this->showWrongCredentialsMessage = true;
			return;
		}

		//Registration requests
		if(isset($_POST["RegisterMail"]) && isset($_POST["RegisterName"]) && isset($_POST["RegisterPassword"]) && preg_match("#^[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,4}$#i", $_POST["RegisterMail"]) && preg_match("#^[a-z0-9_-]{4,25}$#i", $_POST["RegisterName"]) && preg_match("#^.{5,25}$#i", $_POST["RegisterPassword"])){ //If something with the data is wrong, no need to show a message, since that would mean that the user has sent the $_POST data himself and avoided the javascript check.
			//todo ajax test mail &name
			$stmt = $this->database->mysqli->prepare("SELECT `ID` FROM `Users` WHERE `Name`=? OR `Mail`=?;");
			$stmt->bind_param("ss", $_POST["RegisterName"], $_POST["RegisterMail"]);
			$stmt->execute();
			$stmt->store_result();
			if($stmt->num_rows == 0){
				$stmt2 = $this->database->mysqli->prepare("INSERT INTO `Users` (`Name`, `Password`, `Mail`) VALUES (?, ?, ?)");
				$stmt2->bind_param("sss", $_POST["RegisterName"], md5($_POST["RegisterPassword"].md5($_POST["RegisterName"])), $_POST["RegisterMail"]);
				if($stmt2->execute()){
					$this->showRegisteredMessage = true;
					$_POST["Username"] = $_POST["RegisterName"];
					$_POST["Password"] = $_POST["RegisterPassword"];
					unset($_POST["RegisterName"]);
					unset($_POST["RegisterMail"]);
					unset($_POST["RegisterPassword"]);
					$this->tryLogin();
					$stmt2->close();
				}
			}
			$stmt->free_result();
			$stmt->close();
		}
	}
}