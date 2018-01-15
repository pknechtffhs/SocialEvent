<?php
if(!defined('IncludeCheck')) {
   die('Direct access not permitted');
}

use Slim\Http\Request;
use Slim\Http\Response;

$app->post('/users', function (Request $request, Response $response) {
	$this->logger->debug("POST USER called from ".$_SERVER['REMOTE_ADDR']);
	$role = sanitizeUserInput($request->getParams()['role']);
	if (validateUserInput($output,$response,$role,"Rolle","role")){
		if ($role == 1){
			$name = sanitizeUserInput($request->getParams()['name']);
			$forename = sanitizeUserInput($request->getParams()['forename']);
			$mail = sanitizeUserInput(strtolower($request->getParams()['mail']));
			$pw = sanitizeUserInput($request->getParams()['pw1']);
			if (validateUserInput($output,$response,$name,"Nachname","name") &&
					validateUserInput($output,$response,$forename,"Vorname","name") &&
					validateUserInput($output,$response,$mail,"E-Mail-Adresse","email") &&
					validateUserInput($output,$response,$pw,"Passwort zu kurz (<8) oder ","password")){
				$profilePicutre = sanitizeUserInput($request->getParams()['picture']);
				$pdo = $this->db->prepare("SELECT mail FROM users WHERE mail = :mail");
				$pdo->bindParam(':mail', $mail, PDO::PARAM_STR);
				$pdo->execute();
				$result = $pdo->fetch(PDO::FETCH_ASSOC);
				if ($pdo->rowCount() == 0){
					$pdo = $this->db->prepare("INSERT INTO users (name, forename, mail, password, role, profilepicture) VALUES (:name, :forename, :mail, :password, :role, :profilepicture)");
					$pdo->bindParam(':name', $name, PDO::PARAM_STR);
					$pdo->bindParam(':forename', $forename, PDO::PARAM_STR);
					$pdo->bindParam(':mail', $mail, PDO::PARAM_STR);
					$pdo->bindParam(':password', password_hash($pw, PASSWORD_DEFAULT), PDO::PARAM_STR);
					$pdo->bindParam(':role', $role, PDO::PARAM_STR);
					$pdo->bindParam(':profilepicture', $profilePicutre, PDO::PARAM_STR);
					$pdo->execute();
					$response = $response->withStatus(201);
					$response = $response->withHeader('Location', '/user/'.$mail);
					$output['result'] = "success";
				} else {
					$output['result'] = "failed";
					$response = $response->withStatus(409);
					$output['errormessage'] = "Benutzer ist bereits registiert.";
				}
			}
		} else {
			$name = sanitizeUserInput($request->getParams()['name']);
			$mail = sanitizeUserInput($request->getParams()['mail']);
			$phone = sanitizeUserInput($request->getParams()['phone']);
			$street = sanitizeUserInput($request->getParams()['street']);
			$place = sanitizeUserInput($request->getParams()['place']);
			$pw = sanitizeUserInput($request->getParams()['pw1']);
			if (validateUserInput($output,$response,$name,"Firma/Name","name") &&
					validateUserInput($output,$response,$mail,"E-Mail-Adresse","email") &&
					validateUserInput($output,$response,$phone,"Telefonnummer","phone") &&
					validateUserInput($output,$response,$street,"Strasse","streetplace") &&
					validateUserInput($output,$response,$place,"Ort/PLZ","streetplace") &&
					validateUserInput($output,$response,$pw,"Passwort zu kurz (<8) oder ","password")){
				$pdo = $this->db->prepare("SELECT mail FROM users WHERE mail = :mail");
				$pdo->bindParam(':mail', $mail, PDO::PARAM_STR);
				$pdo->execute();
				$result = $pdo->fetch(PDO::FETCH_ASSOC);
				if ($pdo->rowCount() == 0){
					$pdo = $this->db->prepare("INSERT INTO users (name, mail, password, role, phone, street, place) VALUES (:name, :mail, :password, :role, :phone, :street, :place)");
					$pdo->bindParam(':name', $name, PDO::PARAM_STR);
					$pdo->bindParam(':mail', $mail, PDO::PARAM_STR);
					$pdo->bindParam(':password', password_hash($pw, PASSWORD_DEFAULT), PDO::PARAM_STR);
					$pdo->bindParam(':role', $role, PDO::PARAM_STR);
					$pdo->bindParam(':phone', $phone, PDO::PARAM_STR);
					$pdo->bindParam(':street', $street, PDO::PARAM_STR);
					$pdo->bindParam(':place', $place, PDO::PARAM_STR);
					$pdo->execute();
					$response = $response->withStatus(201);
					$response = $response->withHeader('Location', '/user/'.$mail);
					$output['result'] = "success";
				} else {
					$output['result'] = "failed";
					$response = $response->withStatus(409);
					$output['errormessage'] = "Benutzer ist bereits registiert.";
				}
			}
		}
	}	
	return $response ->withJson($output);
});
$app->get('/users/{userid}', function (Request $request, Response $response,$args) {
	$this->logger->debug("GET USER called from ".$_SERVER['REMOTE_ADDR']);
	$sessionkey = sanitizeUserInput($request->getParams()['sessionkey']);
	if (validateUserInput($output,$response,$sessionkey,"Sessionkey","sessionkey") ){
		$userInfo = getUserInfo($this->db,$sessionkey);
		if (getUserInfo($this->db,$sessionkey)){
			$profile = sanitizeUserInput(strtolower($args['userid']));
			if (validateUserInput($output,$response,$profile,"Benutzername","email",true)){
				$pdo =  $this->db->prepare("SELECT mail, name, forename, role, phone, street, place, profilepicture, companyinfo, companypictures FROM users WHERE mail = :mail");
				$pdo->bindParam(':mail', $profile, PDO::PARAM_STR);
				$pdo->execute();
				$result = $pdo->fetch(PDO::FETCH_ASSOC);
				if ($pdo->rowCount()>0){
					$output['result'] = "success";
					$output['profile'] = $result;
				} else {
					$output['result'] = "failed";
					$output['errormessage'] = "Profil wurde nicht gefunden.";
					$response = $response->withStatus(404);
					$this->logger->warn("Failed getting profile from ".$_SERVER['REMOTE_ADDR']);
				}
			}
		} else {
			return $response->withStatus(401);
		}
	}
	return $response ->withJson($output);
});
$app->patch('/users/{userid}', function (Request $request, Response $response,$args) {
	$this->logger->debug("PATCH USER called from ".$_SERVER['REMOTE_ADDR']);
	$sessionkey = sanitizeUserInput($request->getParams()['sessionkey']);
	if (validateUserInput($output,$response,$sessionkey,"Sessionkey","sessionkey") ){
		$userInfo = getUserInfo($this->db,$sessionkey);
		if ($userInfo['mail']!=$args['userid']){
			//Änderungen nur für eigenes Konto erlaubt
			return $response->withStatus(401);
		}
		if ($userInfo){
			$this->db->beginTransaction();
			$output['result'] = "success";
			$change = false;
			if (isset($request->getParams()['oldpw']) && isset($request->getParams()['newpw'])){
				$oldpw = sanitizeUserInput($request->getParams()['oldpw']);
				$pw = sanitizeUserInput($request->getParams()['newpw']);
				if (validateUserInput($output,$response,$oldpw,"Altes Passwort","password") &&
						validateUserInput($output,$response,$pw,"Passwort zu kurz (<8) oder ","password")){
						$pdo =  $this->db->prepare("SELECT password FROM users INNER JOIN sessions on sessions.mail=users.mail WHERE sessionkey = :sessionkey");
						$pdo->bindParam(':sessionkey', $sessionkey, PDO::PARAM_STR);
						$pdo->execute();
						$result = $pdo->fetch(PDO::FETCH_ASSOC);
						if (password_verify($oldpw, $result['password'])){
							$pdo = $this->db->prepare("UPDATE users SET password = :password WHERE mail = :mail");
							$pdo->bindParam(':password', password_hash($pw, PASSWORD_DEFAULT), PDO::PARAM_STR);
							$pdo->bindParam(':mail', $userInfo['mail'], PDO::PARAM_STR);			
							$pdo->execute();
							if ($pdo->rowCount()>0){
								$output['result'] = "success";
								$this->logger->info("Successful password change from ".$_SERVER['REMOTE_ADDR']." (Sessionkey: ".$sessionkey.")");
								$change = true;
							} else {
								$output['result'] = "failed";
								$response = $response->withStatus(500);
								$output['errormessage'] = "Passwort konnte nicht geändert werden.";
								$this->logger->warn("Password change failed from ".$_SERVER['REMOTE_ADDR'] ." (Sessionkey: ".$sessionkey.")");
							}
						} else {
							$output['result'] = "failed";
							$response = $response->withStatus(403);
							$output['errormessage'] = "Bestehendes Passwort ist falsch.";
							$this->logger->warn("Failed password change from ".$_SERVER['REMOTE_ADDR'].". Reason: Wrong password");
						}
					}
			}
			if ($userInfo['role']==1) {
				if ($output['result'] == "success" && isset($request->getParams()['profilepicture'])){
					$pdo = $this->db->prepare("UPDATE users SET profilepicture = :profilepicture WHERE mail = :mail");
					$pdo->bindParam(':profilepicture', sanitizeUserInput($request->getParams()['profilepicture']), PDO::PARAM_STR);
					$pdo->bindParam(':mail', $userInfo['mail'], PDO::PARAM_STR);			
					$pdo->execute();
					if ($pdo->rowCount()>0){
						$output['result'] = "success";
						$change = true;
						$this->logger->info("Successful profile picture change from ".$_SERVER['REMOTE_ADDR']." (Sessionkey: ".$sessionkey.")");
					} else {
						$output['result'] = "failed";
						$response = $response->withStatus(500);
						$output['errormessage'] = "Profilbild konnte nicht geändert werden.";
						$this->logger->warn("Profilepicture change failed from ".$_SERVER['REMOTE_ADDR'] ." (Sessionkey: ".$sessionkey.")");
					}
				}
			} else if ($userInfo['role']==2) {
				if ($output['result'] == "success" && isset($request->getParams()['companypictures'])){
					$pdo = $this->db->prepare("UPDATE users SET companypictures = :companypictures WHERE mail = :mail");
					$pdo->bindParam(':companypictures', sanitizeUserInput($request->getParams()['companypictures']), PDO::PARAM_STR);
					$pdo->bindParam(':mail', $userInfo['mail'], PDO::PARAM_STR);			
					$pdo->execute();
					if ($pdo->rowCount()>0){
						$output['result'] = "success";
						$change = true;
						$this->logger->info("Successful company pictures change from ".$_SERVER['REMOTE_ADDR']." (Sessionkey: ".$sessionkey.")");
					} else {
						$output['result'] = "failed";
						$response = $response->withStatus(500);
						$output['errormessage'] = "Firmenbilder konnte nicht geändert werden.";
						$this->logger->warn("Company picture change failed from ".$_SERVER['REMOTE_ADDR'] ." (Sessionkey: ".$sessionkey.")");
					}
				}
				if ($output['result'] == "success" && isset($request->getParams()['companyinfo'])){
					$companyinfo = sanitizeUserInput($request->getParams()['companyinfo']);
					if (validateUserInput($output,$response,$companyinfo,"Werbetext","text", true)){
						$pdo = $this->db->prepare("UPDATE users SET companyinfo = :companyinfo WHERE mail = :mail");
						$pdo->bindParam(':companyinfo', $companyinfo, PDO::PARAM_STR);
						$pdo->bindParam(':mail', $userInfo['mail'], PDO::PARAM_STR);			
						$pdo->execute();
						if ($pdo->rowCount()>0){
							$output['result'] = "success";
							$change = true;
							$this->logger->info("Successful companyinfo change from ".$_SERVER['REMOTE_ADDR']." (Sessionkey: ".$sessionkey.")");
						} else {
							$output['result'] = "failed";
							$response = $response->withStatus(500);
							$output['errormessage'] = "Werbetext konnte nicht geändert werden.";
							$this->logger->warn("Companyinfo change failed from ".$_SERVER['REMOTE_ADDR'] ." (Sessionkey: ".$sessionkey.")");
						}
					}
				}
			}
			if ($output['result'] != "success"){
				$this->db->rollBack();
			} else if (!$change){
				$response = $response->withStatus(202);
				$output['result'] = "failed";
				$output['errormessage'] = "Keine Änderungen übermittelt.";
			} else {
				$this->db->commit();				
			}
		} else {
			return $response->withStatus(401);
		}
	}
	return $response ->withJson($output);
});
$app->delete('/users/{userid}', function (Request $request, Response $response,$args) {
	$this->logger->debug("DELETE USER called from ".$_SERVER['REMOTE_ADDR']);
	$sessionkey = sanitizeUserInput($request->getParams()['sessionkey']);
	if (validateUserInput($output,$response,$sessionkey,"Sessionkey","sessionkey")){
		$userInfo = getUserInfo($this->db,$sessionkey);
		if ($userInfo['mail']!=$args['userid']){
			//Änderungen nur für eigenes Konto erlaubt
			return $response->withStatus(401);
		}
		$pdo = $this->db->prepare("DELETE FROM users WHERE mail = :mail");
		$pdo->bindParam(':mail', $userInfo['mail'], PDO::PARAM_STR);
		$pdo->execute();
		if ($pdo->rowCount()>0){
			$output['result'] = "success";
			$this->logger->info("Successful deleted user from ".$_SERVER['REMOTE_ADDR']." (Sessionkey: ".$sessionkey.")");
		} else {
			$output['result'] = "failed";
			$response = $response->withStatus(404);	
			$output['errormessage'] = "Es ist ein Fehler aufgetreten.";
		}
	} else {
		return $response->withStatus(401);
	}
	
	return $response ->withJson($output);
});
$app->post('/sessions', function (Request $request, Response $response) {
	$this->logger->debug("POST SESSION called from ".$_SERVER['REMOTE_ADDR']);
	$username = sanitizeUserInput(strtolower($request->getParams()['username']));
	$password = sanitizeUserInput($request->getParams()['password']);
	if (validateUserInput($output,$response,$username,"Benutzername","email") && 
			validateUserInput($output,$response,$password,"Passwort","password")){		
		$pdo = $this->db->prepare("SELECT password,role FROM users WHERE mail = :mail");
		$pdo->bindParam(':mail', $username, PDO::PARAM_STR);
		$pdo->execute();
		$result = $pdo->fetch(PDO::FETCH_ASSOC);
		if (isset($result) && password_verify($password, $result['password'])){
			//Rückgabearray vorbereiten
			$output['result'] = "success";
			$output['role'] = $result['role'];
			$output['sessionkey'] = getSessionSecret($username);
			//Session in Datenbank ablegen
			$pdo = $this->db->prepare("INSERT INTO sessions (mail, sessionkey) VALUES (:mail, :sessionkey)");
			$pdo->bindParam(':mail', $username, PDO::PARAM_STR);
			$pdo->bindParam(':sessionkey', $output['sessionkey'], PDO::PARAM_STR);
			$pdo->execute();
			$response = $response->withStatus(201);
			$response = $response->withHeader('Location', '/session/'.$output['sessionkey']);
			$this->logger->info("Successful login attempt from ".$_SERVER['REMOTE_ADDR']." (".$username.")");
		} else {
			$output['result'] = "failed";
			$output['errormessage'] = "Anmeldedaten ungültig.";
			$response = $response->withStatus(403);
			$this->logger->warn("Failed login attempt from ".$_SERVER['REMOTE_ADDR']." (".$username.")");
		}
	}
	return $response ->withJson($output);
});
$app->get('/sessions/{sessionkey}', function (Request $request, Response $response,$args) {
	$this->logger->debug("GET SESSION called from ".$_SERVER['REMOTE_ADDR']);
	$sessionkey = sanitizeUserInput($args['sessionkey']);
	if (validateUserInput($output,$response,$sessionkey,"Sessionkey","sessionkey")){
		$userinfo = getUserInfo($this->db,$sessionkey);
		if ($userinfo){
			$output = $userinfo;
			$output['result'] = "success";
			$this->logger->info("Successful session validation from ".$_SERVER['REMOTE_ADDR']." (".$output['mail'].")");
		} else {
			$output['result'] = "failed";
			$output['errormessage'] = "Sessionkey ungültig.";
			$response = $response->withStatus(404);
			$this->logger->warn("Failed session validation from ".$_SERVER['REMOTE_ADDR']);
		}
	}
	
	return $response ->withJson($output);
});
$app->delete('/sessions/{sessionkey}', function (Request $request, Response $response,$args) {
	$this->logger->debug("DELETE SESSION called from ".$_SERVER['REMOTE_ADDR']);
	$sessionkey = sanitizeUserInput($args['sessionkey']);
	if (validateUserInput($output,$response,$sessionkey,"Sessionkey","sessionkey")){		
		$pdo = $this->db->prepare("DELETE FROM sessions WHERE sessionkey = :sessionkey");
		$pdo->bindParam(':sessionkey', $sessionkey, PDO::PARAM_STR);
		$pdo->execute();
		if ($pdo->rowCount()>0){
			$output['result'] = "success";
			$this->logger->info("Successful logout from ".$_SERVER['REMOTE_ADDR']." (Sessionkey: ".$sessionkey.")");
		} else {
			$output['result'] = "failed";
			$response = $response->withStatus(404);
			$output['errormessage'] = "Sessionkey ungültig.";
			$this->logger->warn("Failed logout attempt from ".$_SERVER['REMOTE_ADDR']);
		}
	}
	return $response ->withJson($output);
});
$app->post('/offers', function (Request $request, Response $response) {
	$this->logger->debug("POST OFFER called from ".$_SERVER['REMOTE_ADDR']);
	$sessionkey = sanitizeUserInput($request->getParams()['sessionkey']);
	$title = sanitizeUserInput($request->getParams()['title']);
	$description = sanitizeUserInput($request->getParams()['description']);
	$openinghours = sanitizeUserInput($request->getParams()['openinghours']);
	if (validateUserInput($output,$response,$sessionkey,"Sessionkey","sessionkey") &&
			validateUserInput($output,$response,$title,"Angebotstitel","text") &&
			validateUserInput($output,$response,$description,"Angebotsbeschreibung","text") &&
			validateUserInput($output,$response,$openinghours,"Öffnungszeiten","text")){
		$userInfo = getUserInfo($this->db,$sessionkey);
		if ($userInfo['role']==2){
			$pdo = $this->db->prepare("INSERT INTO offers (title, description, provider, openinghours, pictures) VALUES (:title, :description, :provider, :openinghours, :pictures)");
			$pdo->bindParam(':title', $title, PDO::PARAM_STR);		
			$pdo->bindParam(':description', $description, PDO::PARAM_STR);		
			$pdo->bindParam(':provider', $userInfo['mail'], PDO::PARAM_STR);
			$pdo->bindParam(':openinghours', $openinghours, PDO::PARAM_STR);						
			$pdo->bindParam(':pictures', sanitizeUserInput($request->getParams()['offerpictures']), PDO::PARAM_STR);
			$pdo->execute();
			if ($pdo->rowCount()>0){
				$pdo = $this->db->prepare("SELECT LAST_INSERT_ID();");
				$pdo->execute();
				$output['result'] = "success";
				$response = $response->withStatus(201);
				$response = $response->withHeader('Location', '/offer/'.$pdo->fetch(PDO::FETCH_ASSOC)['LAST_INSERT_ID()']);
				$this->logger->info("Successful created offer from ".$_SERVER['REMOTE_ADDR']." (Sessionkey: ".$sessionkey.")");
			} else {
				$output['result'] = "failed";
				$response = $response->withStatus(500);
				$output['errormessage'] = "Aktivität konnte nicht erstellt werden.";
			}
		} else {
			return $response->withStatus(401);
		}
	}
	return $response ->withJson($output);
});
$app->get('/offers', function (Request $request, Response $response) {
	$this->logger->debug("GET OFFER called from ".$_SERVER['REMOTE_ADDR']);
	$sessionkey = sanitizeUserInput($request->getParams()['sessionkey']);
	if (validateUserInput($output,$response,$sessionkey,"Sessionkey","sessionkey")){
		$userInfo = getUserInfo($this->db,$sessionkey);
		if ($userInfo){
			if (isset($request->getParams()['profile'])){
				$pdo = $this->db->prepare("SELECT offerid, title, description, pictures, openinghours, name, place, provider, street, phone FROM offers INNER JOIN users on users.mail=offers.provider WHERE provider=:provider");
				$pdo->bindParam(':provider', sanitizeUserInput($request->getParams()['profile']), PDO::PARAM_STR);
			} else {
				$pdo = $this->db->prepare("SELECT offerid, title, description, pictures, openinghours, name, place, provider, street, phone FROM offers INNER JOIN users on users.mail=offers.provider");
			}
			$pdo->execute();
			$output['result'] = "success";
			while( $row = $pdo->fetch(PDO::FETCH_ASSOC)){
				$pdo_tmp = $this->db->prepare("SELECT count(activityid) FROM activities WHERE offerid=:offerid");
				$pdo_tmp->bindParam(':offerid', $row['offerid'], PDO::PARAM_STR);
				$pdo_tmp->execute();
				$row['activityCount'] = $pdo_tmp->fetch(PDO::FETCH_ASSOC)['count(activityid)'];
				
				$pdo_tmp = $this->db->prepare("SELECT count(participant) FROM activities INNER JOIN participations ON activities.activityid = participations.activityid WHERE offerid=:offerid");
				$pdo_tmp->bindParam(':offerid', $row['offerid'], PDO::PARAM_STR);
				$pdo_tmp->execute();
				$row['participantCount'] = $pdo_tmp->fetch(PDO::FETCH_ASSOC)['count(participant)'] + $row['activityCount'];
				$output['offers'][] = $row;
			}
		} else {
			return $response->withStatus(401);
		}
	}
	return $response ->withJson($output);
});
$app->get('/offers/{offerid}', function (Request $request, Response $response,$args) {
	$this->logger->debug("GET OFFER called from ".$_SERVER['REMOTE_ADDR']);
	$sessionkey = sanitizeUserInput($request->getParams()['sessionkey']);
	if (validateUserInput($output,$response,$sessionkey,"Sessionkey","sessionkey")){
		$userInfo = getUserInfo($this->db,$sessionkey);
		if ($userInfo){
			$pdo = $this->db->prepare("SELECT offerid, title, description, pictures, openinghours, name, place, provider, street, phone FROM offers INNER JOIN users on users.mail=offers.provider WHERE offerid=:offerid");
			$pdo->bindParam(':offerid', sanitizeUserInput($args['offerid']), PDO::PARAM_STR);
			$pdo->execute();
			$output['result'] = "success";
			while( $row = $pdo->fetch(PDO::FETCH_ASSOC)){
				$pdo_tmp = $this->db->prepare("SELECT count(activityid) FROM activities WHERE offerid=:offerid");
				$pdo_tmp->bindParam(':offerid', $row['offerid'], PDO::PARAM_STR);
				$pdo_tmp->execute();
				$row['activityCount'] = $pdo_tmp->fetch(PDO::FETCH_ASSOC)['count(activityid)'];
				
				$pdo_tmp = $this->db->prepare("SELECT count(participant) FROM activities INNER JOIN participations ON activities.activityid = participations.activityid WHERE offerid=:offerid");
				$pdo_tmp->bindParam(':offerid', $row['offerid'], PDO::PARAM_STR);
				$pdo_tmp->execute();
				$row['participantCount'] = $pdo_tmp->fetch(PDO::FETCH_ASSOC)['count(participant)'] + $row['activityCount'];
				$output['offers'][] = $row;
			}
			if (count($output['offers'])==0){
				$output['result'] = "failed";
				$output['errormessage'] = "Offer-ID ungültig.";
				$response = $response->withStatus(404);
				$this->logger->warn("Failed getting offer by id from ".$_SERVER['REMOTE_ADDR']);
			}
		} else {
			return $response->withStatus(401);
		}
	}
	return $response ->withJson($output);
});
$app->put('/offers/{offerid}', function (Request $request, Response $response,$args) {
	$this->logger->debug("PUT OFFER called from ".$_SERVER['REMOTE_ADDR']);
	$sessionkey = sanitizeUserInput($request->getParams()['sessionkey']);
	$offerid = sanitizeUserInput($args['offerid']);
	$title = sanitizeUserInput($request->getParams()['title']);
	$description = sanitizeUserInput($request->getParams()['description']);
	$openinghours = sanitizeUserInput($request->getParams()['openinghours']);
	if (validateUserInput($output,$response,$sessionkey,"Sessionkey","sessionkey") &&
			validateUserInput($output,$response,$offerid,"Angebotsid","id") &&
			validateUserInput($output,$response,$title,"Angebotstitel","text") &&
			validateUserInput($output,$response,$description,"Angebotsbeschreibung","text") &&
			validateUserInput($output,$response,$openinghours,"Öffnungszeiten","text")){
		$userInfo = getUserInfo($this->db,$sessionkey);
		if ($userInfo['role']==2){
			$pdo = $this->db->prepare("UPDATE offers SET title = :title, description=:description, openinghours=:openinghours, pictures=:pictures WHERE provider=:provider AND offerid=:offerid");
			$pdo->bindParam(':title', $title, PDO::PARAM_STR);		
			$pdo->bindParam(':description', $description, PDO::PARAM_STR);		
			$pdo->bindParam(':provider', $userInfo['mail'], PDO::PARAM_STR);
			$pdo->bindParam(':openinghours', $openinghours, PDO::PARAM_STR);						
			$pdo->bindParam(':pictures', sanitizeUserInput($request->getParams()['offerpictures']), PDO::PARAM_STR);
			$pdo->bindParam(':offerid', $offerid, PDO::PARAM_STR);
			$pdo->execute();
			if ($pdo->rowCount()>0){
				$output['result'] = "success";
				$this->logger->info("Successful changed offer from ".$_SERVER['REMOTE_ADDR']." (Sessionkey: ".$sessionkey.")");
			} else {
				$output['result'] = "failed";
				$response = $response->withStatus(404);
				$output['errormessage'] = "Angebot nicht gefunden oder nicht zur Änderung berechtigt.";
			}
		} else {
			return $response->withStatus(401);
		}
	}
	return $response ->withJson($output);
});
$app->delete('/offers/{offerid}', function (Request $request, Response $response,$args) {
	$this->logger->debug("DELETE OFFER called from ".$_SERVER['REMOTE_ADDR']);
	$sessionkey = sanitizeUserInput($request->getParams()['sessionkey']);
	$offerid = sanitizeUserInput($args['offerid']);
	if (validateUserInput($output,$response,$sessionkey,"Sessionkey","sessionkey") &&
			validateUserInput($output,$response,$offerid,"Angebotsid","id")){
		$userInfo = getUserInfo($this->db,$sessionkey);
		if ($userInfo['role'] == 2){
			$pdo = $this->db->prepare("DELETE FROM offers WHERE provider=:provider AND offerid=:offerid");
			$pdo->bindParam(':provider', $userInfo['mail'], PDO::PARAM_STR);
			$pdo->bindParam(':offerid', $offerid, PDO::PARAM_STR);
			$pdo->execute();
			if ($pdo->rowCount()>0){
				$output['result'] = "success";
				$this->logger->info("Successful deleted offer ".$offerid." from ".$_SERVER['REMOTE_ADDR']." (Sessionkey: ".$sessionkey.")");
			} else {
				$output['result'] = "failed";
				$response = $response->withStatus(404);
				$output['errormessage'] = "Angebot nicht gefunden oder nicht zur Entfernung berechtigt.";
			}
		} else {
			return $response->withStatus(401);
		}
	}
	return $response ->withJson($output);
});
$app->post('/events', function (Request $request, Response $response) {
	$this->logger->debug("POST EVENT called from ".$_SERVER['REMOTE_ADDR']);
	$sessionkey = sanitizeUserInput($request->getParams()['sessionkey']);
	$title = sanitizeUserInput($request->getParams()['title']);
	$description = sanitizeUserInput($request->getParams()['description']);
	$start = sanitizeUserInput($request->getParams()['start']);
	$end = sanitizeUserInput($request->getParams()['end']);
	if (validateUserInput($output,$response,$sessionkey,"Sessionkey","sessionkey") &&
			validateUserInput($output,$response,$title,"Event-Titel","text") &&
			validateUserInput($output,$response,$description,"Event-Beschreibung","text") &&
			validateUserInput($output,$response,$start,"Start-Zeitpunkt","datetime") &&
			validateUserInput($output,$response,$end,"End-Zeitpunkt","datetime")){
		$userInfo = getUserInfo($this->db,$sessionkey);
		if ($userInfo['role']==2){
			$pdo = $this->db->prepare("INSERT INTO events (title, description, provider, start, end, pictures) VALUES (:title, :description, :provider, :start, :end, :pictures)");
			$pdo->bindParam(':title', $title, PDO::PARAM_STR);		
			$pdo->bindParam(':description', $description, PDO::PARAM_STR);		
			$pdo->bindParam(':provider', $userInfo['mail'], PDO::PARAM_STR);
			$pdo->bindParam(':start', text2unixtime($start), PDO::PARAM_STR);		
			$pdo->bindParam(':end', text2unixtime($end), PDO::PARAM_STR);						
			$pdo->bindParam(':pictures', sanitizeUserInput($request->getParams()['eventPictures']), PDO::PARAM_STR);
			$pdo->execute();
			if ($pdo->rowCount()>0){
				$pdo = $this->db->prepare("SELECT LAST_INSERT_ID();");
				$pdo->execute();
				$output['result'] = "success";
				$response = $response->withStatus(201);
				$response = $response->withHeader('Location', '/event/'.$pdo->fetch(PDO::FETCH_ASSOC)['LAST_INSERT_ID()']);
				$this->logger->info("Successful created event from ".$_SERVER['REMOTE_ADDR']." (Sessionkey: ".$sessionkey.")");
			} else {
				$output['result'] = "failed";
				$response = $response->withStatus(500);
				$output['errormessage'] = "Aktivität konnte nicht erstellt werden.";
			}
		} else {
			return $response->withStatus(401);
		}
	}
	return $response ->withJson($output);
});
$app->get('/events', function (Request $request, Response $response) {
	$this->logger->debug("GET EVENT called from ".$_SERVER['REMOTE_ADDR']);
	$sessionkey = sanitizeUserInput($request->getParams()['sessionkey']);
	if (validateUserInput($output,$response,$sessionkey,"Sessionkey","sessionkey")){
		$userInfo = getUserInfo($this->db,$sessionkey);
		if ($userInfo){
			if (isset($request->getParams()['profile'])){
				$pdo = $this->db->prepare("SELECT eventid, title, description, pictures, start, end, name, place, provider, street, phone FROM events INNER JOIN users on users.mail=events.provider WHERE provider=:provider AND start > UNIX_TIMESTAMP() ORDER BY start");
				$pdo->bindParam(':provider', sanitizeUserInput($request->getParams()['profile']), PDO::PARAM_STR);
			} else {
				$pdo = $this->db->prepare("SELECT eventid, title, description, pictures, start, end, name, place, provider, street, phone FROM events INNER JOIN users on users.mail=events.provider WHERE start > UNIX_TIMESTAMP() ORDER BY start");
			}
			$pdo->execute();
			$output['result'] = "success";
			while( $row = $pdo->fetch(PDO::FETCH_ASSOC)){
				$pdo_tmp = $this->db->prepare("SELECT count(activityid) FROM activities WHERE eventid=:eventid");
				$pdo_tmp->bindParam(':eventid', $row['eventid'], PDO::PARAM_STR);
				$pdo_tmp->execute();
				$row['activityCount'] = $pdo_tmp->fetch(PDO::FETCH_ASSOC)['count(activityid)'];
				
				$pdo_tmp = $this->db->prepare("SELECT count(participant) FROM activities INNER JOIN participations ON activities.activityid = participations.activityid WHERE eventid=:eventid");
				$pdo_tmp->bindParam(':eventid', $row['eventid'], PDO::PARAM_STR);
				$pdo_tmp->execute();
				$row['participantCount'] = $pdo_tmp->fetch(PDO::FETCH_ASSOC)['count(participant)'] + $row['activityCount'];
				$row['start'] = unixtime2text($row['start']);
				$row['end'] = unixtime2text($row['end']);				
				$output['events'][] = $row;
			}
		} else {
			return $response->withStatus(401);
		}
	}
	return $response ->withJson($output);
});
$app->get('/events/{eventid}', function (Request $request, Response $response,$args) {
	$this->logger->debug("GET EVENT called from ".$_SERVER['REMOTE_ADDR']);
	$sessionkey = sanitizeUserInput($request->getParams()['sessionkey']);
	if (validateUserInput($output,$response,$sessionkey,"Sessionkey","sessionkey")){
		$userInfo = getUserInfo($this->db,$sessionkey);
		if ($userInfo){
			$pdo = $this->db->prepare("SELECT eventid, title, description, pictures, start, end, name, place, provider, street, phone FROM events INNER JOIN users on users.mail=events.provider WHERE eventid=:eventid AND start > UNIX_TIMESTAMP() ORDER BY start");
			$pdo->bindParam(':eventid', sanitizeUserInput($args['eventid']), PDO::PARAM_STR);
			$pdo->execute();
			$output['result'] = "success";
			while( $row = $pdo->fetch(PDO::FETCH_ASSOC)){
				$pdo_tmp = $this->db->prepare("SELECT count(activityid) FROM activities WHERE eventid=:eventid");
				$pdo_tmp->bindParam(':eventid', $row['eventid'], PDO::PARAM_STR);
				$pdo_tmp->execute();
				$row['activityCount'] = $pdo_tmp->fetch(PDO::FETCH_ASSOC)['count(activityid)'];
				
				$pdo_tmp = $this->db->prepare("SELECT count(participant) FROM activities INNER JOIN participations ON activities.activityid = participations.activityid WHERE eventid=:eventid");
				$pdo_tmp->bindParam(':eventid', $row['eventid'], PDO::PARAM_STR);
				$pdo_tmp->execute();
				$row['participantCount'] = $pdo_tmp->fetch(PDO::FETCH_ASSOC)['count(participant)'] + $row['activityCount'];
				$row['start'] = unixtime2text($row['start']);
				$row['end'] = unixtime2text($row['end']);				
				$output['events'][] = $row;
			}
			if (count($output['events'])==0){
				$output['result'] = "failed";
				$output['errormessage'] = "Event-ID ungültig.";
				$response = $response->withStatus(404);
				$this->logger->warn("Failed getting event by id from ".$_SERVER['REMOTE_ADDR']);
			}
		} else {
			return $response->withStatus(401);
		}
	}
	return $response ->withJson($output);
});
$app->put('/events/{eventid}', function (Request $request, Response $response,$args) {
	$this->logger->debug("PUT EVENT called from ".$_SERVER['REMOTE_ADDR']);
	$sessionkey = sanitizeUserInput($request->getParams()['sessionkey']);
	$eventid = sanitizeUserInput($args['eventid']);
	$title = sanitizeUserInput($request->getParams()['title']);
	$description = sanitizeUserInput($request->getParams()['description']);
	$start = sanitizeUserInput($request->getParams()['start']);
	$end = sanitizeUserInput($request->getParams()['end']);
	if (validateUserInput($output,$response,$sessionkey,"Sessionkey","sessionkey") &&
			validateUserInput($output,$response,$eventid,"Event-ID","id") &&
			validateUserInput($output,$response,$title,"Event-Titel","text") &&
			validateUserInput($output,$response,$description,"Event-Beschreibung","text") &&
			validateUserInput($output,$response,$start,"Start-Zeitpunkt","datetime") &&
			validateUserInput($output,$response,$end,"End-Zeitpunkt","datetime")){
		$userInfo = getUserInfo($this->db,$sessionkey);
		if ($userInfo['role']==2){	
			$pdo = $this->db->prepare("UPDATE events SET title = :title, description=:description, start=:start, end=:end, pictures=:pictures WHERE provider=:provider AND eventid=:eventid");
			$pdo->bindParam(':title', $title, PDO::PARAM_STR);		
			$pdo->bindParam(':description', $description, PDO::PARAM_STR);		
			$pdo->bindParam(':provider', $userInfo['mail'], PDO::PARAM_STR);
			$pdo->bindParam(':start', text2unixtime($start), PDO::PARAM_STR);			
			$pdo->bindParam(':end', text2unixtime($end), PDO::PARAM_STR);				
			$pdo->bindParam(':pictures', sanitizeUserInput($request->getParams()['eventPictures']), PDO::PARAM_STR);
			$pdo->bindParam(':eventid', $eventid, PDO::PARAM_STR);
			$pdo->execute();
			if ($pdo->rowCount()>0){
				$output['result'] = "success";
				$this->logger->info("Successful changed event from ".$_SERVER['REMOTE_ADDR']." (Sessionkey: ".$sessionkey.")");
			} else {
				$output['result'] = "failed";
				$response = $response->withStatus(404);
				$output['errormessage'] = "Event nicht gefunden oder nicht zur Änderung berechtigt.";
			}
		} else {
			return $response->withStatus(401);
		}
	}
	return $response ->withJson($output);
});
$app->delete('/events/{eventid}', function (Request $request, Response $response,$args) {
	$this->logger->debug("DELETE EVENT called from ".$_SERVER['REMOTE_ADDR']);
	$sessionkey = sanitizeUserInput($request->getParams()['sessionkey']);
	$eventid = sanitizeUserInput($args['eventid']);
	if (validateUserInput($output,$response,$sessionkey,"Sessionkey","sessionkey") &&
			validateUserInput($output,$response,$eventid,"Event-ID","id")){
		$userInfo = getUserInfo($this->db,$sessionkey);
		if ($userInfo['role']==2){		
			$pdo = $this->db->prepare("DELETE FROM events WHERE provider=:provider AND eventid=:eventid");
			$pdo->bindParam(':provider', $userInfo['mail'], PDO::PARAM_STR);
			$pdo->bindParam(':eventid', $eventid, PDO::PARAM_STR);
			$pdo->execute();
			if ($pdo->rowCount()>0){
				$output['result'] = "success";
				$this->logger->info("Successful deleted event ".$eventid." from ".$_SERVER['REMOTE_ADDR']." (Sessionkey: ".$sessionkey.")");
			} else {
				$output['result'] = "failed";
				$response = $response->withStatus(404);
				$output['errormessage'] = "Event nicht gefunden oder nicht zur Entfernung berechtigt.";
			}
		} else {
			return $response->withStatus(401);
		}
	}
	return $response ->withJson($output);
});
$app->post('/activities', function (Request $request, Response $response) {
	$this->logger->debug("POST ACTIVITY called from ".$_SERVER['REMOTE_ADDR']);
	$sessionkey = sanitizeUserInput($request->getParams()['sessionkey']);
	$title = sanitizeUserInput($request->getParams()['title']);
	$description = sanitizeUserInput($request->getParams()['description']);
	$start = sanitizeUserInput($request->getParams()['start']);
	$offerid = sanitizeUserInput($request->getParams()['offerid']);
	$eventid = sanitizeUserInput($request->getParams()['eventid']);
	if (validateUserInput($output,$response,$sessionkey,"Sessionkey","sessionkey") &&
			validateUserInput($output,$response,$title,"Aktivitäten-Titel","text") &&
			validateUserInput($output,$response,$description,"Aktivitäten-Beschreibung","text") &&
			validateUserInput($output,$response,$start,"Zeitpunkt","datetime") &&
			validateUserInput($output,$response,$offerid,"Offer-ID","id",true) &&
			validateUserInput($output,$response,$eventid,"Event-ID","id",true)){
		$userInfo = getUserInfo($this->db,$sessionkey);
		if ($userInfo['role']==1){
			if (!$offerid && !$eventid){
				$street = sanitizeUserInput($request->getParams()['street']);
				$place = sanitizeUserInput($request->getParams()['place']);
				if (validateUserInput($output,$response,$street,"Strasse","streetplace") &&
					validateUserInput($output,$response,$place,"PLZ/Ort","streetplace")){
						$pdo = $this->db->prepare("INSERT INTO activities (title, description, provider, start, street, place, pictures) VALUES (:title, :description, :provider, :start, :street, :place, :pictures)");
						$pdo->bindParam(':title', $title, PDO::PARAM_STR);		
						$pdo->bindParam(':description', $description, PDO::PARAM_STR);		
						$pdo->bindParam(':provider', $userInfo['mail'], PDO::PARAM_STR);
						$pdo->bindParam(':start', text2unixtime($start), PDO::PARAM_STR);		
						$pdo->bindParam(':street', $street, PDO::PARAM_STR);	
						$pdo->bindParam(':place', $place, PDO::PARAM_STR);						
						$pdo->bindParam(':pictures', sanitizeUserInput($request->getParams()['activityPictures']), PDO::PARAM_STR);
						$pdo->execute();
						if ($pdo->rowCount()>0){
							$pdo = $this->db->prepare("SELECT LAST_INSERT_ID();");
							$pdo->execute();
							$output['result'] = "success";
							$response = $response->withHeader('Location', '/activity/'.$pdo->fetch(PDO::FETCH_ASSOC)['LAST_INSERT_ID()']);
							$response = $response->withStatus(201);
							$this->logger->info("Successful created unlinked activity from ".$_SERVER['REMOTE_ADDR']." (Sessionkey: ".$sessionkey.")");
						} else {
							$output['result'] = "failed";
							$response = $response->withStatus(500);
							$output['errormessage'] = "Aktivität konnte nicht erstellt werden.";
						}
					}
			} else {
				if ($offerid){
					$pdo = $this->db->prepare("INSERT INTO activities (offerid, title, description, provider, start, venue, pictures) VALUES (:offerid, :title, :description, :provider, :start, :venue, :pictures)");
					$pdo->bindParam(':offerid', $offerid, PDO::PARAM_STR);
				} else {
					$pdo = $this->db->prepare("INSERT INTO activities (eventid, title, description, provider, start, venue, pictures) VALUES (:eventid, :title, :description, :provider, :start, :venue, :pictures)");
					$pdo->bindParam(':eventid', $eventid, PDO::PARAM_STR);
				}
				$venue = sanitizeUserInput($request->getParams()['venue']);
				if (validateUserInput($output,$response,$venue,"Treffpunkt","streetplace")){
					$pdo->bindParam(':title', $title, PDO::PARAM_STR);		
					$pdo->bindParam(':description', $description, PDO::PARAM_STR);		
					$pdo->bindParam(':provider', $userInfo['mail'], PDO::PARAM_STR);
					$pdo->bindParam(':start', text2unixtime($start), PDO::PARAM_STR);		
					$pdo->bindParam(':venue', $venue, PDO::PARAM_STR);					
					$pdo->bindParam(':pictures', sanitizeUserInput($request->getParams()['activityPictures']), PDO::PARAM_STR);
					$pdo->execute();
					if ($pdo->rowCount()>0){
						$pdo = $this->db->prepare("SELECT LAST_INSERT_ID();");
						$pdo->execute();
						$output['result'] = "success";
						$response = $response->withHeader('Location', '/activity/'.$pdo->fetch(PDO::FETCH_ASSOC)['LAST_INSERT_ID()']);
						$response = $response->withStatus(201);
						$this->logger->info("Successful created linked activity from ".$_SERVER['REMOTE_ADDR']." (Sessionkey: ".$sessionkey.")");
					} else {
							$output['result'] = "failed";
							$response = $response->withStatus(500);
							$output['errormessage'] = "Aktivität konnte nicht erstellt werden.";
					}
				}				
			}
		} else {
			return $response->withStatus(401);
		}
	}
	return $response ->withJson($output);
});
$app->get('/activities', function (Request $request, Response $response) {
	$this->logger->debug("GET ACTIVITY called from ".$_SERVER['REMOTE_ADDR']);
	$sessionkey = sanitizeUserInput($request->getParams()['sessionkey']);
	if (validateUserInput($output,$response,$sessionkey,"Sessionkey","sessionkey")){
		$userInfo = getUserInfo($this->db,$sessionkey);
		if ($userInfo){
			if (isset($request->getParams()['offerid'])){
				$pdo = $this->db->prepare("SELECT activities.*, users.name, users.forename, users.profilepicture FROM activities INNER JOIN users on users.mail=activities.provider WHERE offerid=:offerid AND start > UNIX_TIMESTAMP() ORDER BY start");
				$pdo->bindParam(':offerid', sanitizeUserInput($request->getParams()['offerid']), PDO::PARAM_STR);
			} else if (isset($request->getParams()['eventid'])){
				$pdo = $this->db->prepare("SELECT activities.*, users.name, users.forename, users.profilepicture FROM activities INNER JOIN users on users.mail=activities.provider WHERE eventid=:eventid AND start > UNIX_TIMESTAMP() ORDER BY start");
				$pdo->bindParam(':eventid', sanitizeUserInput($request->getParams()['eventid']), PDO::PARAM_STR);
			} else if (isset($request->getParams()['profile'])){
				$pdo = $this->db->prepare("SELECT activities.*, users.name, users.forename, users.profilepicture FROM activities INNER JOIN users on users.mail=activities.provider WHERE provider=:provider AND start > UNIX_TIMESTAMP() ORDER BY start");
				$pdo->bindParam(':provider', sanitizeUserInput($request->getParams()['profile']), PDO::PARAM_STR);
			} else {
				$pdo = $this->db->prepare("SELECT activities.*, users.name, users.forename, users.profilepicture FROM activities INNER JOIN users on users.mail=activities.provider WHERE start > UNIX_TIMESTAMP() ORDER BY start");
			}
			$pdo->execute();
			$output['result'] = "success";
			while( $row = $pdo->fetch(PDO::FETCH_ASSOC)){
				$pdo_tmp = $this->db->prepare("SELECT forename,profilepicture FROM participations INNER JOIN users on users.mail=participations.participant WHERE activityid=:activityid");
				$pdo_tmp->bindParam(':activityid', $row['activityid'], PDO::PARAM_STR);
				$pdo_tmp->execute();
				$row['participantCount'] = $pdo_tmp->rowCount() + 1;
				$row['participants'] = $pdo_tmp->fetchAll();;
				$pdo_tmp = $this->db->prepare("SELECT forename,profilepicture FROM participations INNER JOIN users on users.mail=participations.participant WHERE activityid=:activityid AND participant=:participant");
				$pdo_tmp->bindParam(':activityid', $row['activityid'], PDO::PARAM_STR);
				$pdo_tmp->bindParam(':participant', $userInfo['mail'], PDO::PARAM_STR);
				$pdo_tmp->execute();
				$row['participated'] = $pdo_tmp->rowCount() > 0;
				$row['start'] = unixtime2text($row['start']);
				$output['activities'][] = $row;
			}
		} else {
			return $response->withStatus(401);
		}
	}
	return $response ->withJson($output);
});
$app->get('/activities/{activityid}', function (Request $request, Response $response, $args) {
	$this->logger->debug("GET ACTIVITY called from ".$_SERVER['REMOTE_ADDR']);
	$sessionkey = sanitizeUserInput($request->getParams()['sessionkey']);
	if (validateUserInput($output,$response,$sessionkey,"Sessionkey","sessionkey")){
		$userInfo = getUserInfo($this->db,$sessionkey);
		if ($userInfo){
			$pdo = $this->db->prepare("SELECT activities.*, users.name, users.forename, users.profilepicture FROM activities INNER JOIN users on users.mail=activities.provider WHERE activityid=:activityid AND start > UNIX_TIMESTAMP() ORDER BY start");
			$pdo->bindParam(':activityid', sanitizeUserInput($args['activityid']), PDO::PARAM_STR);
			$pdo->execute();
			$output['result'] = "success";
			while( $row = $pdo->fetch(PDO::FETCH_ASSOC)){
				$pdo_tmp = $this->db->prepare("SELECT forename,profilepicture FROM participations INNER JOIN users on users.mail=participations.participant WHERE activityid=:activityid");
				$pdo_tmp->bindParam(':activityid', $row['activityid'], PDO::PARAM_STR);
				$pdo_tmp->execute();
				$row['participantCount'] = $pdo_tmp->rowCount() + 1;
				$row['participants'] = $pdo_tmp->fetchAll();;
				$pdo_tmp = $this->db->prepare("SELECT forename FROM participations INNER JOIN users on users.mail=participations.participant WHERE activityid=:activityid AND participant=:participant");
				$pdo_tmp->bindParam(':activityid', $row['activityid'], PDO::PARAM_STR);
				$pdo_tmp->bindParam(':participant', $userInfo['mail'], PDO::PARAM_STR);
				$pdo_tmp->execute();
				$row['participated'] = $pdo_tmp->rowCount() > 0;
				$row['start'] = unixtime2text($row['start']);
				$output['activities'][] = $row;
			}
			if (count($output['activities'])==0){
				$output['result'] = "failed";
				$output['errormessage'] = "Aktivitäten-ID ungültig.";
				$response = $response->withStatus(404);
				$this->logger->warn("Failed getting activity by id from ".$_SERVER['REMOTE_ADDR']);
			}
		} else {
			return $response->withStatus(401);
		}
	}
	return $response ->withJson($output);
});
$app->put('/activities/{activityid}', function (Request $request, Response $response, $args) {
	$this->logger->debug("PUT ACTIVITY called from ".$_SERVER['REMOTE_ADDR']);
	$sessionkey = sanitizeUserInput($request->getParams()['sessionkey']);
	$title = sanitizeUserInput($request->getParams()['title']);
	$description = sanitizeUserInput($request->getParams()['description']);
	$start = sanitizeUserInput($request->getParams()['start']);
	$activityid = sanitizeUserInput($args['activityid']);
	if (validateUserInput($output,$response,$sessionkey,"Sessionkey","sessionkey") &&
			validateUserInput($output,$response,$title,"Aktivitäten-Titel","text") &&
			validateUserInput($output,$response,$description,"Aktivitäten-Beschreibung","text") &&
			validateUserInput($output,$response,$start,"Zeitpunkt","datetime") &&
			validateUserInput($output,$response,$activityid,"Aktivitäten-ID","id",true)){
		$userInfo = getUserInfo($this->db,$sessionkey);
		if ($userInfo['role']==1){
			$pdo =  $this->db->prepare("SELECT offerid, eventid FROM activities WHERE activityid = :activityid AND provider = :provider");
			$pdo->bindParam(':activityid', $activityid, PDO::PARAM_STR);
			$pdo->bindParam(':provider', $userInfo['mail'], PDO::PARAM_STR);
			$pdo->execute();
			if ($pdo->rowCount()>0){
				$result = $pdo->fetch(PDO::FETCH_ASSOC);
				if ($result['offerid']!="" || $result['eventid']!=""){
					$venue = sanitizeUserInput($request->getParams()['venue']);
					if (validateUserInput($output,$response,$venue,"Treffpunkt","streetplace")){
							$pdo = $this->db->prepare("UPDATE activities SET title = :title, description = :description, start = :start, venue = :venue, pictures =:pictures WHERE activityid = :activityid");
							$pdo->bindParam(':title', $title, PDO::PARAM_STR);		
							$pdo->bindParam(':description', $description, PDO::PARAM_STR);	
							$pdo->bindParam(':start', text2unixtime($start), PDO::PARAM_STR);		
							$pdo->bindParam(':venue', $venue, PDO::PARAM_STR);							
							$pdo->bindParam(':pictures', sanitizeUserInput($request->getParams()['activityPictures']), PDO::PARAM_STR);
							$pdo->bindParam(':activityid', $activityid, PDO::PARAM_STR);
							$pdo->execute();
							if ($pdo->rowCount()>0){
								$output['result'] = "success";
								$this->logger->info("Successful changed linked activity from ".$_SERVER['REMOTE_ADDR']." (Sessionkey: ".$sessionkey.")");
							} else {
								$output['result'] = "failed";
								$response = $response->withStatus(404);
								$output['errormessage'] = "Aktivität nicht gefunden oder nicht zur Änderung berechtigt.";
							}
					}
				} else {
					$street = sanitizeUserInput($request->getParams()['street']);
					$place = sanitizeUserInput($request->getParams()['place']);
					if (validateUserInput($output,$response,$street,"Strasse","streetplace") &&
						validateUserInput($output,$response,$place,"PLZ/Ort","streetplace")){
							$pdo = $this->db->prepare("UPDATE activities SET title = :title, description = :description, start = :start, street = :street, place = :place, pictures =:pictures WHERE activityid = :activityid");
							$pdo->bindParam(':title', $title, PDO::PARAM_STR);		
							$pdo->bindParam(':description', $description, PDO::PARAM_STR);	
							$pdo->bindParam(':start', text2unixtime($start), PDO::PARAM_STR);		
							$pdo->bindParam(':street', $street, PDO::PARAM_STR);	
							$pdo->bindParam(':place', $place, PDO::PARAM_STR);						
							$pdo->bindParam(':pictures', sanitizeUserInput($request->getParams()['activityPictures']), PDO::PARAM_STR);
							$pdo->bindParam(':activityid', $activityid, PDO::PARAM_STR);
							$pdo->execute();
							if ($pdo->rowCount()>0){
								$output['result'] = "success";
								$this->logger->info("Successful changed unlinked activity from ".$_SERVER['REMOTE_ADDR']." (Sessionkey: ".$sessionkey.")");
							} else {
								$output['result'] = "failed";
								$response = $response->withStatus(404);
								$output['errormessage'] = "Aktivität nicht gefunden oder nicht zur Änderung berechtigt.";
							}
					}
				}
			} else {
				$output['result'] = "failed";
				$response = $response->withStatus(404);
				$output['errormessage'] = "Aktivität nicht gefunden oder nicht zur Änderung berechtigt.";
			}
		} else {
			return $response->withStatus(401);
		}
	}
	return $response ->withJson($output);
});
$app->delete('/activities/{activityid}', function (Request $request, Response $response, $args) {
	$this->logger->debug("DELETE ACTIVITY called from ".$_SERVER['REMOTE_ADDR']);
	$sessionkey = sanitizeUserInput($request->getParams()['sessionkey']);
	$activityid = sanitizeUserInput($args['activityid']);
	if (validateUserInput($output,$response,$sessionkey,"Sessionkey","sessionkey") &&
			validateUserInput($output,$response,$activityid,"Aktivitäten-ID","id")){
		$userInfo = getUserInfo($this->db,$sessionkey);
		if ($userInfo['role']==1){
			$pdo = $this->db->prepare("DELETE FROM activities WHERE provider=:provider AND activityid=:activityid");
			$pdo->bindParam(':provider', $userInfo['mail'], PDO::PARAM_STR);
			$pdo->bindParam(':activityid', $activityid, PDO::PARAM_STR);
			$pdo->execute();
			if ($pdo->rowCount()>0){
				$output['result'] = "success";
				$this->logger->info("Successful deleted activity ".$activityid." from ".$_SERVER['REMOTE_ADDR']." (Sessionkey: ".$sessionkey.")");
			} else {
				$output['result'] = "failed";
				$response = $response->withStatus(404);
				$output['errormessage'] = "Aktivität nicht gefunden oder nicht zur Entfernung berechtigt.";
			}
		} else {
			return $response->withStatus(401);
		}
	}
	return $response ->withJson($output);
});
$app->post('/participations', function (Request $request, Response $response) {
	$this->logger->debug("POST PARTICIPATION called from ".$_SERVER['REMOTE_ADDR']);
	$sessionkey = sanitizeUserInput($request->getParams()['sessionkey']);
	$activityid = sanitizeUserInput($request->getParams()['activityid']);
	if (validateUserInput($output,$response,$sessionkey,"Sessionkey","sessionkey") &&
			validateUserInput($output,$response,$offerid,"Aktivitäten-ID","id",true)){
		$userInfo = getUserInfo($this->db,$sessionkey);
		if ($userInfo['role']==1){
				$pdo = $this->db->prepare("INSERT INTO participations (activityid, participant) VALUES (:activityid, :participant)");
				$pdo->bindParam(':activityid', $activityid, PDO::PARAM_STR);		
				$pdo->bindParam(':participant', $userInfo['mail'], PDO::PARAM_STR);
				$pdo->execute();
				if ($pdo->rowCount()>0){
					$output['result'] = "success";
					$this->logger->info("Successful created participation from ".$_SERVER['REMOTE_ADDR']." (Sessionkey: ".$sessionkey.")");
				} else {
					$output['result'] = "failed";	
					$response = $response->withStatus(500);
					$output['errormessage'] = "Es ist ein Fehler aufgetreten.";
				}
			}
		} else {
			return $response->withStatus(401);
		}
	return $response ->withJson($output);
});
$app->get('/participations', function (Request $request, Response $response) {
	$this->logger->debug("GET PARTICIPATION called from ".$_SERVER['REMOTE_ADDR']);
	$sessionkey = sanitizeUserInput($request->getParams()['sessionkey']);
	if (validateUserInput($output,$response,$sessionkey,"Sessionkey","sessionkey")){
		$userInfo = getUserInfo($this->db,$sessionkey);
		if ($userInfo['role']==1){
			$pdo = $this->db->prepare("SELECT title, start, activities.activityid FROM activities INNER JOIN participations on activities.activityid=participations.activityid WHERE participant=:participant AND start > UNIX_TIMESTAMP() ORDER BY start");
			$pdo->bindParam(':participant', $userInfo['mail'], PDO::PARAM_STR);
			$pdo->execute();
			$output['result'] = "success";
			while( $row = $pdo->fetch(PDO::FETCH_ASSOC)){
				$row['start'] = unixtime2text($row['start']);
				$output['participations'][] = $row;
			}
		} else {
			return $response->withStatus(401);
		}
	}
	return $response ->withJson($output);
});
$app->delete('/participations', function (Request $request, Response $response) {
	$this->logger->debug("DELETE PARTICIPATION called from ".$_SERVER['REMOTE_ADDR']);
	$sessionkey = sanitizeUserInput($request->getParams()['sessionkey']);
	$activityid = sanitizeUserInput($request->getParams()['activityid']);
	if (validateUserInput($output,$response,$sessionkey,"Sessionkey","sessionkey") &&
			validateUserInput($output,$response,$offerid,"Aktivitäten-ID","id",true)){
		$userInfo = getUserInfo($this->db,$sessionkey);
		if ($userInfo['role']==1){
				$pdo = $this->db->prepare("DELETE FROM participations WHERE activityid = :activityid AND participant = :participant");
				$pdo->bindParam(':activityid', $activityid, PDO::PARAM_STR);		
				$pdo->bindParam(':participant', $userInfo['mail'], PDO::PARAM_STR);
				$pdo->execute();
				if ($pdo->rowCount()>0){
					$output['result'] = "success";
					$this->logger->info("Successful deleted participation from ".$_SERVER['REMOTE_ADDR']." (Sessionkey: ".$sessionkey.")");
				} else {
					$output['result'] = "failed";	
					$response = $response->withStatus(404);
					$output['errormessage'] = "Teilnahme nicht gefunden.";
				}
			}
		} else {
			return $response->withStatus(401);
		}
	return $response ->withJson($output);
});
?>