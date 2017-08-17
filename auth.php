<?php

require_once 'php_classes/DiscordApi.php';
$discordApi = new DiscordApi();
require_once 'php_classes/Database.php';
$database = new Database();
$db = $database->getConnection();

date_default_timezone_set('UTC');

// grant if PUBLIC access is requested
if (!empty($_GET['scope']) && $_GET['scope'] == 'PUBLIC') {
	$token = generateToken();
	$stmt = $db->prepare('INSERT INTO bearer_tokens (token, scope)
		VALUES (:token, :scope)');
	$stmt->bindValue(':token', $token, PDO::PARAM_STR);
	$stmt->bindValue(':scope', 'PUBLIC', PDO::PARAM_STR);
	$stmt->execute();

	$response = new stdClass;
	$response->response = 'granted';
	$response->scope = 'PUBLIC';
	$response->token = $token;
	echo json_encode($response);
	return;
}

// test if access token is given for every scope except PUBLIC
if (empty($_GET['access_token'])) {
	http_response_code(400);
	echo 'No access token given';
	return;
}

$user = $discordApi->getUser($_GET['access_token']);

// reject if access token is invalid
if ($user->message == '401: Unauthorized') {
	http_response_code(400);
	$response = new stdClass;
	$response->response = 'rejected';
	$response->message = 'Invalid access token given';
	echo json_encode($response);
	return;
}

$userRoles = $discordApi->getUserRoles($user->id);
$guildRoles = $discordApi->getGuildRoles();

if ($userRoles === false) {
	$response = new stdClass;
	$response->response = 'rejected';
	$response->message = 'User not in guild';
	echo json_encode($response);
	return;
}

$scopes = [];

foreach ($userRoles as $userRole) {
	foreach ($guildRoles as $guildRole) {
		if ($userRole == $guildRole->id) {
			switch ($guildRole->name) {
				case 'Player': $scopes[] = 'PLAYER'; break;
				case 'Referees': $scopes[] = 'REFEREE'; break;
				case 'Head Mappoolers': $scopes[] = 'MAPPOOLER'; break;
				case 'Core Staff': $scopes[] = 'ADMIN'; $scopes[] = 'MAPPOOLER'; $scopes[] = 'REFEREE'; break;
			}
		}
	}
}

$registration = false;

/*
// add registration to roles if registration is open
$stmt = $db->prepare('SELECT registration_open, registration_close
	FROM settings');
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
if ($rows[0] && $rows[0]['registration_open'] && $rows[0]['registration_close']) {
	$registration_open = strtotime($rows[0]['registration_open']);
	$registration_close = strtotime($rows[0]['registration_close']);
	$now = strtotime(gmdate('Y-m-d H:i:s'));
	if ($now > $registration_open && $now < $registration_close) {
		$registration = true;
	}
}

// add registration to roles if user has an active registration
if (!$registration) {
	$stmt = $db->prepare('SELECT COUNT(*) as rowcount
		FROM registrations
		WHERE id = :id AND osu_id IS NOT NULL');
	$stmt->bindValue(':id', $user->id, PDO::PARAM_STR);
	$stmt->execute();
	$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
	if ($rows[0]['rowcount'] == '1') {
		$registration = true;
	}
}

if ($registration) {
	$scopes[] = 'REGISTRATION';
}
*/

$scopes = array_values(array_unique($scopes));

// return possible scopes if no scope is requested
if (empty($_GET['scope'])) {
	$response = new stdClass;
	$response->response = 'rejected';
	$response->message = 'Scope is missing';
	$response->scopes = $scopes;
	echo json_encode($response);
	return;
}

// return possible scopes if user has no authorization for the requested scope
if (!in_array($_GET['scope'], $scopes)) {
	$response = new stdClass;
	$response->response = 'rejected';
	$response->message = 'You have no authorization for this scope';
	$response->scopes = $scopes;
	echo json_encode($response);
	return;
}

// generate and insert token into database
$token = generateToken();
$stmt = $db->prepare('INSERT INTO bearer_tokens (user_id, token, scope)
	VALUES (:user_id, :token, :scope)');
$stmt->bindValue(':user_id', $user->id, PDO::PARAM_STR);
$stmt->bindValue(':token', $token, PDO::PARAM_STR);
$stmt->bindValue(':scope', $_GET['scope'], PDO::PARAM_STR);
$stmt->execute();

// insert or update discord profile
$stmt = $db->prepare('INSERT INTO discord_users (id, username, discriminator, avatar)
	VALUES (:id, :username, :discriminator, :avatar)
	ON DUPLICATE KEY UPDATE username = :username2, discriminator = :discriminator2, avatar = :avatar2');
$stmt->bindValue(':id', $user->id, PDO::PARAM_STR);
$stmt->bindValue(':username', $user->username, PDO::PARAM_STR);
$stmt->bindValue(':discriminator', $user->discriminator, PDO::PARAM_STR);
$stmt->bindValue(':avatar', $user->avatar, PDO::PARAM_STR);
$stmt->bindValue(':username2', $user->username, PDO::PARAM_STR);
$stmt->bindValue(':discriminator2', $user->discriminator, PDO::PARAM_STR);
$stmt->bindValue(':avatar2', $user->avatar, PDO::PARAM_STR);
$stmt->execute();

if ($_GET['scope'] == 'REGISTRATION') {
	$stmt = $db->prepare('INSERT INTO registrations (id)
		VALUES (:id)');
	$stmt->bindValue(':id', $user->id, PDO::PARAM_STR);
	$stmt->execute();
}

if ($_GET['scope'] == 'MAPPOOLER') {
	$stmt = $db->prepare('INSERT INTO mappoolers (id)
		VALUES (:id)');
	$stmt->bindValue(':id', $user->id, PDO::PARAM_STR);
	$stmt->execute();
}

if ($_GET['scope'] == 'ADMIN') {
	$stmt = $db->prepare('INSERT INTO admins (id)
		VALUES (:id)');
	$stmt->bindValue(':id', $user->id, PDO::PARAM_STR);
	$stmt->execute();
}

if ($_GET['scope'] == 'REFEREE') {
	$stmt = $db->prepare('INSERT INTO referees (id)
		VALUES (:id)');
	$stmt->bindValue(':id', $user->id, PDO::PARAM_STR);
	$stmt->execute();
}

$response = new stdClass;
$response->response = 'granted';
$response->message = 'Your authorization request has been granted';
$response->scope = $_GET['scope'];
$response->token = $token;
echo json_encode($response);

/**
 * Generates unique unused tokens
 */
function generateToken() {
	global $db;

	while (true) {
		$token = str_replace('.', '', uniqid('', true));
		$stmt = $db->prepare('SELECT COUNT(*) as rowcount
			FROM bearer_tokens
			WHERE token = :token');
		$stmt->bindValue(':token', $token, PDO::PARAM_STR);
		$stmt->execute();
		$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
		if ($rows[0]['rowcount'] == '0') {
			break;
		}
	}

	return $token;
}

?>