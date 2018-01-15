<?php
if(!defined('IncludeCheck')) {
   die('Direct access not permitted');
}

function sanitizeUserInput($input){
  $input = trim($input);
  $input = htmlspecialchars($input);
  $input = stripslashes($input);
  return $input;
}

function validateUserInput(&$output, &$response, $input, $fieldname, $type, $optional=false){
	switch ($type) {
		case "role":
			$regex = "/^(1|2)$/";
			break;
		case "id":
			$regex = "/^[0-9]+$/";
			break;
		case "name":
			$regex = "/^[A-Za-z- .]+$/";
			break;
		case "streetplace":
			$regex = "/^[A-Za-z- .0-9]+$/";
			break;
		case "text":
			$regex = "/^[A-Za-z- .0-9(),!?@:;\näÄüÜöÖ]+$/m";
			break;
		case "phone":
			$regex = "/^[0-9+\' \/]+$/";
			break;
		case "email":
			$regex = "/^[a-z0-9._-]+@[a-z0-9._-]+\.[a-z]{2,}$/";
			break;
		case "password":
			$regex = "/^[a-zA-Z\d-+_!@#$%^&*\.,?]{8,}$/";
			break;
		case "datetime":
			$regex = "/^[0-9]{2}.[0-9]{2}.[0-9]{2,4} [0-2][0-9]:[0-5][0-9]$/";
			break;
		case "sessionkey": //SHA1
			$regex = "/^[0-9a-f]{40}$/";
			break;
	}
	if (strlen($input) == 0 && !$optional){
		$output['result'] = "failed";
		$output['errormessage'] = "$fieldname muss gesetzt und gültig sein.";
		$response = $response->withStatus(400);
		return false;
	} else if (strlen($input) == 0 && $optional){
		return true;
	} else if (!preg_match($regex, $input)){
		$output['result'] = "failed";
		$output['errormessage'] = "$fieldname ungültig.";
		$response = $response->withStatus(400);
		return false;
	}
	return true;
}

function getSessionSecret($username){
  return sha1(rand()."+".$username."+".time()."+".rand());
}

define("DTFORMAT", "d.m.Y H:i");
function text2unixtime($date){
  return date_create_from_format(DTFORMAT,$date)->getTimestamp();
}
function unixtime2text($timestamp){
  return date(DTFORMAT, $timestamp);
}

function getUserInfo($db, $sessionkey){
	$pdo = $db->prepare("SELECT role, users.mail FROM users INNER JOIN sessions on sessions.mail=users.mail WHERE sessionkey = :sessionkey");
	$pdo->bindParam(':sessionkey', $sessionkey, PDO::PARAM_STR);
	$pdo->execute();
	$result = $pdo->fetch(PDO::FETCH_ASSOC);
	if ($pdo->rowCount()>0){
		$pdo = $db->prepare("UPDATE sessions SET lastused=NOW() WHERE sessionkey = :sessionkey");
		$pdo->bindParam(':sessionkey', $sessionkey, PDO::PARAM_STR);
		$pdo->execute();
		return $result;
	}
	return false;
}
?>