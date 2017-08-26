<?php

require_once '../php_classes/Database.php';
require_once '../php_classes/OsuApi.php';
require_once '../php_classes/TwitchApi.php';
require_once '../php_classes/DiscordApi.php';
$database = new Database();
$db = $database->getConnection();
$osuApi = new OsuApi();
$twitchApi = new TwitchApi();
$discordApi = new DiscordApi();

date_default_timezone_set('UTC');

switch ($_GET['query']) {
	case 'user':
		switch ($_SERVER['REQUEST_METHOD']) {
			case 'GET': getUser(); break; // get user data
			case 'PUT': putUser(); break; // update user data
		}
		break;
	case 'registrations':
		switch ($_SERVER['REQUEST_METHOD']) {
			case 'GET': getRegistrations(); break; // get a list of all registrations
			case 'PUT': putRegistration(); break; // update registration
			case 'POST': postRegistration(); break; // create new registration
			case 'DELETE': deleteRegistration(); break; // delete registration
		}
		break;
	case 'players':
		switch ($_SERVER['REQUEST_METHOD']) {
			case 'GET': getPlayers(); break; // get a list of all players
		}
		break;
	case 'rounds':
		switch ($_SERVER['REQUEST_METHOD']) {
			case 'GET': getRounds(); break; // get a list of rounds in a tier
			case 'POST': postRound(); break; // create new round
		}
		break;
	case 'round':
		switch ($_SERVER['REQUEST_METHOD']) {
			case 'GET': getRound(); break; // get round
			case 'PUT': putRound(); break; // update a round
			case 'DELETE': deleteRound(); break; // delete round
		}
		break;
	case 'tiers':
		switch ($_SERVER['REQUEST_METHOD']) {
			case 'GET': getTiers(); break; // get a list of all tiers
			case 'POST': postTier(); break; // create a new tier
		}
		break;
	case 'tier':
		switch ($_SERVER['REQUEST_METHOD']) {
			case 'GET': getTier(); break; // get tier
			case 'PUT': putTier(); break; // update a tier
			case 'DELETE': deleteTier(); break; // delete a tier
		}
		break;
	case 'lobbies':
		switch ($_SERVER['REQUEST_METHOD']) {
			case 'GET': getLobbies(); break; // get a list of lobbies in a round
			case 'POST': postLobbies(); break; // create lobbies for a round
			case 'DELETE': deleteLobbies(); break; // delete all lobbies of a round
		}
		break;
	case 'lobby':
		switch ($_SERVER['REQUEST_METHOD']) {
			case 'GET': getLobby(); break; // get a lobby
			case 'PUT': putLobby(); break; // update a lobby
		}
		break;
	case 'mappools':
		switch ($_SERVER['REQUEST_METHOD']) {
			case 'GET': getMappools(); break; // get all mappools
			case 'POST': postMappool(); break; // create new mappool
		}
		break;
	case 'mappool':
		switch ($_SERVER['REQUEST_METHOD']) {
			case 'GET': getMappool(); break; // get mappool
			case 'PUT': putMappool(); break; // update mappool
			case 'DELETE': deleteMappool(); break; // delete mappool
		}
		break;
	case 'osuprofile':
		switch ($_SERVER['REQUEST_METHOD']) {
			case 'GET': getOsuProfile(); break; // get an osu account over the osu api
		}
		break;
	case 'osubeatmap':
		switch ($_SERVER['REQUEST_METHOD']) {
			case 'GET': getOsuBeatmap(); break; // get an osu beatmap over the osu api
		}
		break;
	case 'osumatch':
		switch ($_SERVER['REQUEST_METHOD']) {
			case 'GET': getOsuMatch(); break; // get an osu match over the osu api
		}
		break;
	case 'osugame':
		switch ($_SERVER['REQUEST_METHOD']) {
			case 'PUT': putOsuGame(); break; // update an osu game
		}
		break;
	case 'availability':
		switch ($_SERVER['REQUEST_METHOD']) {
			case 'GET': getAvailability(); break; // returns a list of availabilites for a round
			case 'POST': postAvailability(); break; // creates a new availability for a round
		}
		break;
	case 'settings':
		switch ($_SERVER['REQUEST_METHOD']) {
			case 'GET': getSettings(); break; // get general settings
			case 'PUT': putSettings(); break; // update general settings
		}
		break;
	case 'discordlogin':
		switch ($_SERVER['REQUEST_METHOD']) {
			case 'GET': getDiscordLogin(); break; // get discord login uri
			case 'POST': postDiscordLogin(); break; // try to login with access token
		}
		break;
	case 'discordroles':
		switch ($_SERVER['REQUEST_METHOD']) {
			case 'GET': getDiscordRoles(); break; // get discord roles
			case 'POST': postDiscordRoles(); break; // refresh discord role list
		}
		break;
	case 'twitchlogin':
		switch ($_SERVER['REQUEST_METHOD']) {
			case 'GET': getTwitchLogin(); break; // get twitch login uri
			case 'POST': postTwitchLogin(); break; // try to login with code
		}
		break;
}

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

function checkToken() {
	global $db;
	$token = $_SERVER['HTTP_AUTHORIZATION'];

	$stmt = $db->prepare('SELECT user_id as id, scope
		FROM bearer_tokens
		WHERE token = :token');
	$stmt->bindValue(':token', $token, PDO::PARAM_STR);
	$stmt->execute();
	$rows = $stmt->fetchAll(PDO::FETCH_OBJ);
	if (count($rows) > 0) {
		return $rows[0];
	}
	return null;
}

function echoError($error, $message) {
	$response = new stdClass;
	$response->error = $error ? '1' : '0';
	$response->message = $message;
	echo json_encode($response);
}

function recalculateRound($round) {
	global $db;

	if (empty($round)) {
		$stmt = $db->prepare('SELECT has_continue, continue_round, has_drop_down, drop_down_round
			FROM rounds
			WHERE is_first_round = 1');
		$stmt->execute();
		$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
		if (!empty($rows[0]) && !empty($rows[0]['has_continue'])) {
			recalculateRound($rows[0]['continue_round']);
		}
		if (!empty($rows[0]) && !empty($rows[0]['has_drop_down'])) {
			recalculateRound($rows[0]['drop_down_round']);
		}
	} else {
		$playerAmount = 0;

		$stmt = $db->prepare('SELECT player_amount, lobby_size, continue_amount
			FROM rounds
			WHERE has_continue = 1 AND continue_round = :continue_round');
		$stmt->bindValue(':continue_round', $round, PDO::PARAM_INT);
		$stmt->execute();
		$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
		foreach ($rows as $row) {
			$playerAmount += $row['player_amount'] / $row['lobby_size'] * $row['continue_amount'];
		}
		$stmt = $db->prepare('SELECT player_amount, lobby_size, drop_down_amount
			FROM rounds
			WHERE has_drop_down = 1 AND drop_down_round = :drop_down_round');
		$stmt->bindValue(':drop_down_round', $round, PDO::PARAM_INT);
		$stmt->execute();
		$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
		foreach ($rows as $row) {
			$playerAmount += $row['player_amount'] / $row['lobby_size'] * $row['drop_down_amount'];
		}

		$stmt = $db->prepare('UPDATE rounds
			SET player_amount = :player_amount
			WHERE id = :id');
		$stmt->bindValue(':player_amount', $playerAmount, PDO::PARAM_INT);
		$stmt->bindValue(':id', $round, PDO::PARAM_INT);
		$stmt->execute();

		$stmt = $db->prepare('SELECT has_continue, continue_round, has_drop_down, drop_down_round
			FROM rounds
			WHERE id = :id');
		$stmt->bindValue(':id', $round, PDO::PARAM_INT);
		$stmt->execute();
		$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
		if (!empty($rows[0]['has_continue'])) {
			recalculateRound($rows[0]['continue_round']);
		}
		if (!empty($rows[0]['has_drop_down'])) {
			recalculateRound($rows[0]['drop_down_round']);
		}
	}
}

function getUser() {
	global $db;

	if ($_GET['user'] == '@me') {
		$user = checkToken();
		if (!isset($user)) {
			return;
		}
		$stmt = $db->prepare('SELECT id, username, discriminator, avatar
			FROM discord_users
			WHERE id = :id');
		$stmt->bindValue(':id', $user->id, PDO::PARAM_INT);
		$stmt->execute();
		echo json_encode($stmt->fetch(PDO::FETCH_OBJ));
		return;
	} else {
		$stmt = $db->prepare('SELECT id, username, discriminator, avatar
			FROM discord_users
			WHERE id = :id');
		$stmt->bindValue(':id', $_GET['user'], PDO::PARAM_INT);
		$stmt->execute();
		echo json_encode($stmt->fetch(PDO::FETCH_OBJ));
		return;
	}
}

function putUser() {
	global $db;

	$user = checkToken();
	if (!isset($user)) {
		return;
	}

	$body = json_decode(file_get_contents('php://input'));

	if ($user->scope == 'PLAYER') {
		if (isset($body->discordId)) {
			$stmt = $db->prepare('UPDATE players
				SET discord_id = :discord_id
				WHERE id = :id');
			$stmt->bindValue(':discord_id', $body->discordId, PDO::PARAM_INT);
			$stmt->bindValue(':id', $body->id, PDO::PARAM_INT);
			$stmt->execute();
		}
	}
}

function getRegistrations() {
	global $db;
	global $osuApi;
	$user = checkToken();
	if (!isset($user)) {
		return;
	}
	if ($user->scope == 'REGISTRATION') {
		$stmt = $db->prepare('SELECT registrations.osu_id as osuId, registrations.registration_time as registrationTime, osu_users.username as osuUsername, osu_users.avatar_url as osuAvatarUrl, osu_users.hit_accuracy as osuHitAccuracy, osu_users.level as osuLevel, osu_users.play_count as osuPlayCount, osu_users.pp as osuPp, osu_users.rank as osuRank, osu_users.rank_history as osuRankHistory, osu_users.best_score as osuBestScore, osu_users.playstyle as osuPlaystyle, osu_users.join_date as osuJoinDate, osu_users.country as osuCountry, registrations.twitch_id as twitchId, twitch_users.username as twitchUsername, twitch_users.display_name as twitchDisplayName, twitch_users.avatar as twitchAvatar, twitch_users.sub_since as twitchSubSince, twitch_users.sub_plan as twitchSubPlan
			FROM registrations INNER JOIN osu_users ON registrations.osu_id = osu_users.id LEFT JOIN twitch_users ON registrations.twitch_id = twitch_users.id
			WHERE registrations.id = :id');
		$stmt->bindValue(':id', $user->id, PDO::PARAM_INT);
		$stmt->execute();
		echo json_encode($stmt->fetch(PDO::FETCH_OBJ));
		return;
	}
	if ($user->scope == 'ADMIN') {
		$stmt = $db->prepare('SELECT registrations.osu_id as osuId, registrations.registration_time as registrationTime, osu_users.username as osuUsername, osu_users.avatar_url as osuAvatarUrl, osu_users.hit_accuracy as osuHitAccuracy, osu_users.level as osuLevel, osu_users.play_count as osuPlayCount, osu_users.pp as osuPp, osu_users.rank as osuRank, osu_users.rank_history as osuRankHistory, osu_users.best_score as osuBestScore, osu_users.playstyle as osuPlaystyle, osu_users.join_date as osuJoinDate, osu_users.country as osuCountry, registrations.twitch_id as twitchId, twitch_users.username as twitchUsername, twitch_users.display_name as twitchDisplayName, twitch_users.avatar as twitchAvatar, twitch_users.sub_since as twitchSubSince, twitch_users.sub_plan as twitchSubPlan
			FROM registrations INNER JOIN osu_users ON registrations.osu_id = osu_users.id LEFT JOIN twitch_users ON registrations.twitch_id = twitch_users.id
			ORDER BY registrations.registration_time ASC');
		$stmt->bindValue(':id', $user->id, PDO::PARAM_INT);
		$stmt->execute();
		echo json_encode($stmt->fetchAll(PDO::FETCH_OBJ));
		return;
	}
}

function putRegistration() {
	global $db;

	$user = checkToken();
	if (!isset($user)) {
		return;
	}

	$body = json_decode(file_get_contents('php://input'));

	if ($user->scope == 'ADMIN') {
		if (isset($body->idNew)) {
			$stmt = $db->prepare('UPDATE registrations
				SET id = :id_new
				WHERE id = :id_old');
			$stmt->bindValue(':id_new', $body->idNew, PDO::PARAM_INT);
			$stmt->bindValue(':id_old', $body->idOld, PDO::PARAM_INT);
			$stmt->execute();
			echoError(0, 'Discord account changed');
			return;
		}
	}
}

function postRegistration() {
	global $db;

	$user = checkToken();
	if (!isset($user)) {
		return;
	}

	$body = json_decode(file_get_contents('php://input'));

	if ($user->scope == 'REGISTRATION') {
		$stmt = $db->prepare('INSERT INTO registrations (id, osu_id, registration_time)
			VALUES (:id, :osu_id, :registration_time)');
		$stmt->bindValue(':id', $user->id, PDO::PARAM_INT);
		$stmt->bindValue(':osu_id', $body->osuId, PDO::PARAM_INT);
		$stmt->bindValue(':registration_time', gmdate('Y-m-d H:i:s'));
		$stmt->execute();
		echoError(0, 'Registration successfull');
		return;
	}
}

function deleteRegistration() {
	global $db;

	$user = checkToken();
	if (!isset($user)) {
		return;
	}

	$body = json_decode(file_get_contents('php://input'));

	if ($user->scope == 'REGISTRATION') {
		$stmt = $db->prepare('DELETE FROM registrations
			WHERE id = :id');
		$stmt->bindValue(':id', $user->id, PDO::PARAM_INT);
		$stmt->execute();
		echoError(0, 'Registration deleted');
		return;
	}
	if ($user->scope == 'ADMIN') {
		$stmt = $db->prepare('DELETE FROM registrations
			WHERE id = :id');
		$stmt->bindValue(':id', $body->id, PDO::PARAM_INT);
		$stmt->execute();
		echoError(0, 'Registration deleted');
		return;
	}
}

function getPlayers() {
	global $db;

	$stmt = $db->prepare('SELECT osu_users.id as osuId, osu_users.username as osuUsername, osu_users.avatar_url as osuAvatarUrl, osu_users.hit_accuracy as osuHitAccuracy, osu_users.level as osuLevel, osu_users.play_count as osuPlayCount, osu_users.pp as osuPp, osu_users.rank as osuRank, osu_users.rank_history as osuRankHistory, osu_users.best_score as osuBestScore, osu_users.playstyle as osuPlaystyle, osu_users.join_date as osuJoinDate, osu_users.country as osuCountry, tiers.id as tierId, tiers.name as tierName
		FROM players INNER JOIN osu_users ON players.osu_id = osu_users.id INNER JOIN tier ON players.tier = tiers.id');
	$stmt->execute();
	echo json_encode($stmt->fetchAll(PDO::FETCH_OBJ));
}

function getRounds() {
	global $database;
	$db = $database->getConnection();

	$stmt = $db->prepare('SELECT id, name, lobby_size as lobbySize, best_of as bestOf, is_first_round as isFirstRound, player_amount as playerAmount, is_start_round as isStartRound, has_continue as hasContinue, continue_amount as continueAmount, continue_round as continueRound, has_drop_down as hasDropDown, drop_down_amount as dropDownAmount, drop_down_round as dropDownRound, has_elimination as hasElimination, eliminated_amount as eliminatedAmount, has_bracket_reset as hasBracketReset, mappools_released as mappoolsReleased, lobbies_released as lobbiesReleased
		FROM rounds
		ORDER BY id ASC');
	$stmt->execute();
	$rounds = $stmt->fetchAll(PDO::FETCH_ASSOC);
	foreach ($rounds as &$round) {
		$stmt = $db->prepare('SELECT time_from as `from`, time_to as `to`
			FROM round_times
			WHERE round = :round');
		$stmt->bindValue(':round', $round['id'], PDO::PARAM_INT);
		$stmt->execute();
		$round['times'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
	}
	echo json_encode($rounds);
}

function postRound() {
	global $db;

	$user = checkToken();
	if (!isset($user) || $user->scope != 'ADMIN') {
		return;
	}

	$body = json_decode(file_get_contents('php://input'));

	$stmt = $db->prepare('INSERT INTO rounds (name, lobby_size, best_of, is_first_round, player_amount, is_start_round, has_continue, continue_amount, continue_round, has_drop_down, drop_down_amount, drop_down_round, has_elimination, eliminated_amount, has_bracket_reset, mappools_released, lobbies_released)
		VALUES (:name, :lobby_size, :best_of, :is_first_round, :player_amount, :is_start_round, :has_continue, :continue_amount, :continue_round, :has_drop_down, :drop_down_amount, :drop_down_round, :has_elimination, :eliminated_amount, :has_bracket_reset, :mappools_released, :lobbies_released)');
	$stmt->bindValue(':name', $body->name, PDO::PARAM_STR);
	$stmt->bindValue(':lobby_size', $body->lobbySize, PDO::PARAM_INT);
	$stmt->bindValue(':best_of', $body->bestOf, PDO::PARAM_INT);
	$stmt->bindValue(':is_first_round', $body->isFirstRound, PDO::PARAM_BOOL);
	$stmt->bindValue(':player_amount', $body->playerAmount, PDO::PARAM_INT);
	$stmt->bindValue(':is_start_round', $body->isStartRound, PDO::PARAM_BOOL);
	$stmt->bindValue(':has_continue', $body->hasContinue, PDO::PARAM_BOOL);
	$stmt->bindValue(':continue_amount', $body->continueAmount, PDO::PARAM_INT);
	$stmt->bindValue(':continue_round', $body->continueRoundId, PDO::PARAM_INT);
	$stmt->bindValue(':has_drop_down', $body->hasDropDown, PDO::PARAM_BOOL);
	$stmt->bindValue(':drop_down_amount', $body->dropDownAmount, PDO::PARAM_INT);
	$stmt->bindValue(':drop_down_round', $body->dropDownRoundId, PDO::PARAM_INT);
	$stmt->bindValue(':has_elimination', $body->hasElimination, PDO::PARAM_BOOL);
	$stmt->bindValue(':eliminated_amount', $body->eliminatedAmount, PDO::PARAM_INT);
	$stmt->bindValue(':has_bracket_reset', $body->hasBracketReset, PDO::PARAM_BOOL);
	$stmt->bindValue(':mappools_released', $body->mappoolsReleased, PDO::PARAM_BOOL);
	$stmt->bindValue(':lobbies_released', $body->lobbiesReleased, PDO::PARAM_BOOL);
	$stmt->execute();

	$round = $db->lastInsertId();

	foreach ($body->times as $time) {
		$stmt = $db->prepare('INSERT INTO round_times (round, time_from, time_to)
			VALUES (:round, :time_from, :time_to)');
		$stmt->bindValue(':round', $round, PDO::PARAM_INT);
		$stmt->bindValue(':time_from', $time->from, PDO::PARAM_STR);
		$stmt->bindValue(':time_to', $time->to, PDO::PARAM_STR);
		$stmt->execute();
	}

	recalculateRound(0);

	echoError(0, 'Round saved');
}

function getRound() {
	global $db;

	$stmt = $db->prepare('SELECT id, name, lobby_size as lobbySize, best_of as bestOf, is_first_round as isFirstRound, player_amount as playerAmount, is_start_round as isStartRound, has_continue as hasContinue, continue_amount as continueAmount, continue_round as continueRound, has_drop_down as hasDropDown, drop_down_amount as dropDownAmount, drop_down_round as dropDownRound, has_elimination as hasElimination, eliminated_amount as eliminatedAmount, has_bracket_reset as hasBracketReset, mappools_released as mappoolsReleased, lobbies_released as lobbiesReleased
		FROM rounds
		WHERE id = :id');
	$stmt->bindValue(':id', $_GET['round'], PDO::PARAM_INT);
	$stmt->execute();
	$round = $stmt->fetch(PDO::FETCH_ASSOC);
	$stmt = $db->prepare('SELECT id, time_from as timeFrom, time_to as timeTo
		FROM round_times
		WHERE round = :round');
	$stmt->bindValue(':round', $_GET['round'], PDO::PARAM_INT);
	$stmt->execute();
	$round['times'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
	echo json_encode($round);
}

function putRound() {
	global $db;

	$user = checkToken();
	if (!isset($user) || $user->scope != 'ADMIN') {
		return;
	}

	$body = json_decode(file_get_contents('php://input'));

	$stmt = $db->prepare('UPDATE rounds
		SET name = :name, lobby_size = :lobby_size, best_of = :best_of, is_first_round = :is_first_round, player_amount = :player_amount, is_start_round = :is_start_round, has_continue = :has_continue, continue_amount = :continue_amount, continue_round = :continue_round, has_drop_down = :has_drop_down, drop_down_amount = :drop_down_amount, drop_down_round = :drop_down_round, has_elimination = :has_elimination, eliminated_amount = :eliminated_amount, has_bracket_reset = :has_bracket_reset, mappools_released = :mappools_released, lobbies_released = :lobbies_released
		WHERE id = :id');
	$stmt->bindValue(':name', $body->name, PDO::PARAM_STR);
	$stmt->bindValue(':lobby_size', $body->lobbySize, PDO::PARAM_INT);
	$stmt->bindValue(':best_of', $body->bestOf, PDO::PARAM_INT);
	$stmt->bindValue(':is_first_round', $body->isFirstRound, PDO::PARAM_BOOL);
	$stmt->bindValue(':player_amount', $body->playerAmount, PDO::PARAM_INT);
	$stmt->bindValue(':is_start_round', $body->isStartRound, PDO::PARAM_BOOL);
	$stmt->bindValue(':has_continue', $body->hasContinue, PDO::PARAM_BOOL);
	$stmt->bindValue(':continue_amount', $body->continueAmount, PDO::PARAM_INT);
	$stmt->bindValue(':continue_round', $body->continueRoundId, PDO::PARAM_INT);
	$stmt->bindValue(':has_drop_down', $body->hasDropDown, PDO::PARAM_BOOL);
	$stmt->bindValue(':drop_down_amount', $body->dropDownAmount, PDO::PARAM_INT);
	$stmt->bindValue(':drop_down_round', $body->dropDownRoundId, PDO::PARAM_INT);
	$stmt->bindValue(':has_elimination', $body->hasElimination, PDO::PARAM_BOOL);
	$stmt->bindValue(':eliminated_amount', $body->eliminatedAmount, PDO::PARAM_INT);
	$stmt->bindValue(':has_bracket_reset', $body->hasBracketReset, PDO::PARAM_BOOL);
	$stmt->bindValue(':mappools_released', $body->mappoolsReleased, PDO::PARAM_BOOL);
	$stmt->bindValue(':lobbies_released', $body->lobbiesReleased, PDO::PARAM_BOOL);
	$stmt->bindValue(':id', $_GET['round'], PDO::PARAM_INT);
	$stmt->execute();

	$stmt = $db->prepare('DELETE FROM round_times
		WHERE round = :round');
	$stmt->bindValue(':round', $_GET['round'], PDO::PARAM_INT);
	$stmt->execute();
	foreach ($body->times as $time) {
		$stmt = $db->prepare('INSERT INTO round_times (round, time_from, time_to)
			VALUES (:round, :time_from, :time_to)');
		$stmt->bindValue(':round', $_GET['round'], PDO::PARAM_INT);
		$stmt->bindValue(':time_from', $time->from, PDO::PARAM_STR);
		$stmt->bindValue(':time_to', $time->to, PDO::PARAM_STR);
		$stmt->execute();
	}

	recalculateRound(0);

	echoError(0, 'Round saved');
}

function deleteRound() {
	global $db;

	$user = checkToken();
	if (!isset($user) || $user->scope != 'ADMIN') {
		return;
	}

	$stmt = $db->prepare('UPDATE rounds
		SET has_continue = 0, continue_amount = 0, continue_round = NULL
		WHERE continue_round = :continue_round');
	$stmt->bindValue(':continue_round', $_GET['round'], PDO::PARAM_INT);
	$stmt->execute();
	$stmt = $db->prepare('UPDATE rounds
		SET has_drop_down = 0, drop_down_amount = 0, drop_down_round = NULL
		WHERE drop_down_round = :drop_down_round');
	$stmt->bindValue(':drop_down_round', $_GET['round'], PDO::PARAM_INT);
	$stmt->execute();

	$stmt = $db->prepare('DELETE FROM round_times
		WHERE round = :round');
	$stmt->bindValue(':round', $_GET['round'], PDO::PARAM_INT);
	$stmt->execute();

	$stmt = $db->prepare('DELETE FROM rounds
		WHERE id = :id');
	$stmt->bindValue(':id', $_GET['round'], PDO::PARAM_INT);
	$stmt->execute();

	recalculateRound(0);

	echoError(0, 'Round deleted');
}

function getTiers() {
	global $db;

	$stmt = $db->prepare('SELECT id, name, lower_endpoint as lowerEndpoint, upper_endpoint as upperEndpoint, starting_round as startingRound, seed_by_rank as seedByRank, seed_by_time as seedByTime, seed_by_random as seedByRandom, sub_bonus as subBonus
		FROM tiers
		ORDER BY id ASC');
	$stmt->execute();
	echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
}

function postTier() {
	global $database;
	$db = $database->getConnection();

	$user = checkToken();
	if (!isset($user) || $user->scope != 'ADMIN') {
		return;
	}

	$body = json_decode(file_get_contents('php://input'));

	$stmt = $db->prepare('INSERT INTO tiers (name, lower_endpoint, upper_endpoint, starting_round, seed_by_rank, seed_by_time, seed_by_random, sub_bonus)
		VALUES (:name, :lower_endpoint, :upper_endpoint, :starting_round, :seed_by_rank, :seed_by_time, :seed_by_random, :sub_bonus)');
	$stmt->bindValue(':name', $body->name, PDO::PARAM_STR);
	$stmt->bindValue(':lower_endpoint', $body->lowerEndpoint, PDO::PARAM_INT);
	$stmt->bindValue(':upper_endpoint', $body->upperEndpoint, PDO::PARAM_INT);
	$stmt->bindValue(':starting_round', $body->startingRound, PDO::PARAM_INT);
	$stmt->bindValue(':seed_by_rank', $body->selectedSeeding == 'rank', PDO::PARAM_BOOL);
	$stmt->bindValue(':seed_by_time', $body->selectedSeeding == 'time', PDO::PARAM_BOOL);
	$stmt->bindValue(':seed_by_random', $body->selectedSeeding == 'random', PDO::PARAM_BOOL);
	$stmt->bindValue(':sub_bonus', $body->subBonus, PDO::PARAM_BOOL);
	$stmt->execute();

	echoError(0, 'Tier saved');
}

function getTier() {
	global $db;

	$stmt = $db->prepare('SELECT id, name, lower_endpoint as lowerEndpoint, upper_endpoint as upperEndpoint, starting_round as startingRound, seed_by_rank as seedByRank, seed_by_time as seedByTime, seed_by_random as seedByRandom, sub_bonus as subBonus
		FROM tiers
		WHERE id = :id');
	$stmt->bindValue(':id', $_GET['tier'], PDO::PARAM_INT);
	$stmt->execute();
	echo json_encode($stmt->fetch(PDO::FETCH_ASSOC));
}

function putTier() {
	global $db;

	$user = checkToken();
	if (!isset($user) || $user->scope != 'ADMIN') {
		return;
	}

	$body = json_decode(file_get_contents('php://input'));

	$stmt = $db->prepare('UPDATE tiers
		SET name = :name, lower_endpoint = :lower_endpoint, upper_endpoint = :upper_endpoint, starting_round = :starting_round, seed_by_rank = :seed_by_rank, seed_by_time = :seed_by_time, seed_by_random = :seed_by_random, sub_bonus = :sub_bonus
		WHERE id = :id');
	$stmt->bindValue(':name', $body->name, PDO::PARAM_STR);
	$stmt->bindValue(':lower_endpoint', $body->lowerEndpoint, PDO::PARAM_INT);
	$stmt->bindValue(':upper_endpoint', $body->upperEndpoint, PDO::PARAM_INT);
	$stmt->bindValue(':starting_round', $body->startingRound, PDO::PARAM_INT);
	$stmt->bindValue(':seed_by_rank', $body->selectedSeeding == 'rank', PDO::PARAM_BOOL);
	$stmt->bindValue(':seed_by_time', $body->selectedSeeding == 'time', PDO::PARAM_BOOL);
	$stmt->bindValue(':seed_by_random', $body->selectedSeeding == 'random', PDO::PARAM_BOOL);
	$stmt->bindValue(':sub_bonus', $body->subBonus, PDO::PARAM_BOOL);
	$stmt->bindValue(':id', $_GET['tier'], PDO::PARAM_INT);
	$stmt->execute();

	echoError(0, 'Tier saved');
}

function deleteTier() {
	global $db;

	$user = checkToken();
	if (!isset($user) || $user->scope != 'ADMIN') {
		return;
	}

	$stmt = $db->prepare('DELETE FROM tiers
		WHERE id = :id');
	$stmt->bindValue(':id', $_GET['tier'], PDO::PARAM_INT);
	$stmt->execute();

	echoError(0, 'Tier deleted');
}

function getLobbies() {
	global $db;

	$body = json_decode(file_get_contents('php://input'));

	$user = checkToken();
	if (!isset($user) || $user->scope == 'PLAYER' || $user->scope == 'REFEREE') {
		$stmt = $db->prepare('SELECT lobbies.id, lobbies.round, lobbies.tier, lobbies.match_id as matchId, lobbies.match_time as matchTime
			FROM lobbies INNER JOIN rounds ON lobbies.round = rounds.id
			WHERE lobbies.round LIKE :round AND lobbies.tier LIKE :tier AND rounds.lobbies_released = 1');
		$stmt->bindValue(':round', isset($body->round) ? $body->round : '%', PDO::PARAM_STR);
		$stmt->bindValue(':tier', isset($body->tier) ? $body->tier : '%', PDO::PARAM_STR);
		$stmt->execute();
		$lobbies = $stmt->fetchAll(PDO::FETCH_OB);
		foreach ($lobbies as &$lobby) {
			$stmt = $db->prepare('SELECT lobby_slots.id, lobby_slots.continue_to_upper as continueToUpper, lobby_slots.drop_down as dropDown, osu_users.id as osuId, osu_users.username as osuUsername, osu_users.avatar_url as osuAvatarUrl, osu_users.hit_accuracy as osuHitAccuracy, osu_users.level as osuLevel, osu_users.play_count as osuPlayCount, osu_users.pp as osuPp, osu_users.rank as osuRank, osu_users.rank_history as osuRankHistory, osu_users.best_score as osuBestScore, osu_users.playstyle as osuPlaystyle, osu_users.join_date as osuJoinDate, osu_users.country as osuCountry
				FROM lobby_slots LEFT JOIN players ON lobby_slots.user_id = players.id LEFT JOIN osu_users ON players.osu_id = osu_users.id
				WHERE lobby_slots.id = :id');
			$stmt->bindValue(':id', $lobby->id, PDO::PARAM_INT);
			$stmt->execute();
			$lobby->slots = $stmt->fetchAll(PDO::FETCH_OBJ);
		}
		echo json_encode($lobbies);
		return;
	}
	if ($user->scope == 'ADMIN') {
		$stmt = $db->prepare('SELECT lobbies.id, lobbies.round, lobbies.tier, lobbies.match_id as matchId, lobbies.match_time as matchTime
			FROM lobbies INNER JOIN rounds ON lobbies.round = rounds.id
			WHERE lobbies.round LIKE :round AND lobbies.tier LIKE :tier');
		$stmt->bindValue(':round', isset($body->round) ? $body->round : '%', PDO::PARAM_STR);
		$stmt->bindValue(':tier', isset($body->tier) ? $body->tier : '%', PDO::PARAM_STR);
		$stmt->execute();
		$lobbies = $stmt->fetchAll(PDO::FETCH_OB);
		foreach ($lobbies as &$lobby) {
			$stmt = $db->prepare('SELECT lobby_slots.id, lobby_slots.continue_to_upper as continueToUpper, lobby_slots.drop_down as dropDown, osu_users.id as osuId, osu_users.username as osuUsername, osu_users.avatar_url as osuAvatarUrl, osu_users.hit_accuracy as osuHitAccuracy, osu_users.level as osuLevel, osu_users.play_count as osuPlayCount, osu_users.pp as osuPp, osu_users.rank as osuRank, osu_users.rank_history as osuRankHistory, osu_users.best_score as osuBestScore, osu_users.playstyle as osuPlaystyle, osu_users.join_date as osuJoinDate, osu_users.country as osuCountry
				FROM lobby_slots LEFT JOIN players ON lobby_slots.user_id = players.id LEFT JOIN osu_users ON players.osu_id = osu_users.id
				WHERE lobby_slots.id = :id');
			$stmt->bindValue(':id', $lobby->id, PDO::PARAM_INT);
			$stmt->execute();
			$lobby->slots = $stmt->fetchAll(PDO::FETCH_OBJ);
		}
		echo json_encode($lobbies);
		return;
	}
}

function postLobbies() {
	global $db;

	$user = checkToken();
	if (!isset($user)) {
		return;
	}

	$body = json_decode(file_get_contents('php://input'));

	if ($user->scope == 'ADMIN') {
		if (!isset($body->round) || !isset($body->tier)) {
			echoError(1, 'Parameters missing');
			return;
		}
		$stmt = $db->prepare('SELECT COUNT(*) as rowcount
			FROM lobbies
			WHERE round = :round AND tier = :tier');
		$stmt->bindValue(':round', $body->round, PDO::PARAM_INT);
		$stmt->bindValue(':tier', $body->tier, PDO::PARAM_INT);
		$stmt->execute();
		if ($stmt->fetch(PDO::FETCH_OBJ)->rowcount != 0) {
			echoError(1, 'There are already existing lobbies');
			return;
		}
		$stmt = $db->prepare('SELECT lobby_size as lobbySize, player_amount as playerAmount
			FROM rounds
			WHERE id = :id');
		$stmt->bindValue(':id', $body->round, PDO::PARAM_INT);
		$stmt->execute();
		$round = $stmt->fetch(PDO::FETCH_OBJ);
		for ($i = 0; $i < ((int)$round->playerAmount / (int)$round->lobbySize); $i++) {
			$stmt = $db->prepare('INSERT INTO lobbies (round, tier)
				VALUES (:round, :tier)');
			$stmt->bindValue(':round', $body->round, PDO::PARAM_INT);
			$stmt->bindValue(':tier', $body->tier, PDO::PARAM_INT);
			$stmt->execute();
			$id = $db->lastInsertId();
			for ($j = 0; $j < (int)$round->lobbySize; $j++) {
				$stmt = $db->prepare('INSERT INTO lobby_slots (lobby)
					VALUES (:lobby)');
				$stmt->bindValue(':lobby', $id, PDO::PARAM_INT);
				$stmt->execute();
			}
		}
		echoError(0, 'Lobbies created');
		return;
	}
}

function deleteLobbies() {
	global $db;

	$user = checkToken();
	if (!isset($user) || $user->scope != 'ADMIN') {
		return;
	}

	$body = json_decode(file_get_contents('php://input'));

	if (!isset($body->round) || !isset($body->tier)) {
		echoError(1, 'Parameters missing');
		return;
	}
	$stmt = $db->prepare('SELECT id
		FROM lobbies
		WHERE round = :round AND tier = :tier');
	$stmt->bindValue(':round', $body->round, PDO::PARAM_INT);
	$stmt->bindValue(':tier', $body->tier, PDO::PARAM_INT);
	$stmt->execute();
	$lobbies = $stmt->fetchAll(PDO::FETCH_OBJ);
	foreach ($lobbies as $lobby) {
		$stmt = $db->prepare('DELETE FROM lobby_slots
			WHERE lobby = :lobby');
		$stmt->bindValue(':lobby', $lobby->id, PDO::PARAM_INT);
		$stmt->execute();
	}
	$stmt = $db->prepare('DELETE FROM lobbies
		WHERE round = :round AND tier = :tier');
	$stmt->bindValue(':round', $body->round, PDO::PARAM_INT);
	$stmt->bindValue(':tier', $body->tier, PDO::PARAM_INT);
	$stmt->execute();
	echoError(0, 'Lobbies deleted');
}

function getLobby() {
	global $db;
	global $osuApi;

	$stmt = $db->prepare('SELECT id, round, tier, match_id as matchId, match_time as matchTime, comment
		FROM lobbies
		WHERE id = :id');
	$stmt->bindValue(':id', $_GET['lobby'], PDO::PARAM_INT);
	$stmt->execute();
	$lobby = $stmt->fetch(PDO::FETCH_OBJ);
	if (isset($lobby->matchId)) {
		$lobby->events = $osuApi->getMatch($lobby->matchId);
	}
}

function putLobby() {

}

function getMappools() {

}

function postMappool() {

}

function getMappool() {

}

function putMappool() {

}

function deleteMappool() {

}

function getOsuProfile() {
	global $osuApi;
	echo json_encode($osuApi->getUser($_GET['id']));
}

function getOsuBeatmap() {
	global $osuApi;
	echo json_encode($osuApi->getBeatmap($_GET['id']));
}

function getOsuMatch() {
	global $osuApi;
	echo json_encode($osuApi->getMatch($_GET['id']));
}

function putOsuGame() {

}

function getAvailability() {

}

function putAvailability() {

}

function getSettings() {
	global $db;

	$stmt = $db->prepare('SELECT registrations_open as registrationsOpen, registrations_from as registrationsFrom, registrations_to as registrationsTo, role_admin as roleAdmin, role_headpooler as roleHeadpooler, role_mappooler as roleMappooler, role_referee as roleReferee, role_player as rolePlayer
		FROM settings');
	$stmt->execute();
	echo json_encode($stmt->fetch(PDO::FETCH_ASSOC));
}

function putSettings() {
	global $db;

	$user = checkToken();
	if (!isset($user) || $user->scope != 'ADMIN') {
		return;
	}

	$body = json_decode(file_get_contents('php://input'));

	if (isset($body->registrationsOpen)) {
		$stmt = $db->prepare('UPDATE settings
			SET registrations_open = :registrations_open');
		$stmt->bindValue(':registrations_open', $body->registrationsOpen, PDO::PARAM_BOOL);
		$stmt->execute();
	}
	if (isset($body->registrationsFrom)) {
		$stmt = $db->prepare('UPDATE settings
			SET registrations_from = :registrations_from');
		$stmt->bindValue(':registrations_from', $body->registrationsFrom, PDO::PARAM_STR);
		$stmt->execute();
	}
	if (isset($body->registrationsTo)) {
		$stmt = $db->prepare('UPDATE settings
			SET registrations_to = :registrations_to');
		$stmt->bindValue(':registrations_to', $body->registrationsTo, PDO::PARAM_STR);
		$stmt->execute();
	}
	if (isset($body->roleAdmin)) {
		$stmt = $db->prepare('UPDATE settings
			SET role_admin = :role_admin');
		$stmt->bindValue(':role_admin', $body->roleAdmin, PDO::PARAM_INT);
		$stmt->execute();
	}
	if (isset($body->roleHeadpooler)) {
		$stmt = $db->prepare('UPDATE settings
			SET role_headpooler = :role_headpooler');
		$stmt->bindValue(':role_headpooler', $body->roleHeadpooler, PDO::PARAM_INT);
		$stmt->execute();
	}
	if (isset($body->roleMappooler)) {
		$stmt = $db->prepare('UPDATE settings
			SET role_mappooler = :role_mappooler');
		$stmt->bindValue(':role_mappooler', $body->roleMappooler, PDO::PARAM_INT);
		$stmt->execute();
	}
	if (isset($body->roleReferee)) {
		$stmt = $db->prepare('UPDATE settings
			SET role_referee = :role_referee');
		$stmt->bindValue(':role_referee', $body->roleReferee, PDO::PARAM_INT);
		$stmt->execute();
	}
	if (isset($body->rolePlayer)) {
		$stmt = $db->prepare('UPDATE settings
			SET role_player = :role_player');
		$stmt->bindValue(':role_player', $body->rolePlayer, PDO::PARAM_INT);
		$stmt->execute();
	}

	echoError(0, 'Settings saved');
}

function getDiscordLogin() {
	global $discordApi;
	echo json_encode(array('uri' => $discordApi->getLoginUri()));
}

function postDiscordLogin() {
	global $db;
	global $discordApi;

	$body = json_decode(file_get_contents('php://input'));
	$user = $discordApi->getUser($body->accessToken);
	$member = $discordApi->getGuildMember($user->id);
	$stmt = $db->prepare('SELECT id, name, color, position
		FROM discord_roles
		ORDER BY position DESC');
	$stmt->execute();
	$roles = $stmt->fetchAll(PDO::FETCH_OBJ);
	$stmt = $db->prepare('SELECT registrations_open as registrationsOpen, registrations_from as registrationsFrom, registrations_to as registrationsTo, role_admin as roleAdmin, role_headpooler as roleHeadpooler, role_mappooler as roleMappooler, role_referee as roleReferee, role_player as rolePlayer
		FROM settings');
	$stmt->execute();
	$settings = $stmt->fetch(PDO::FETCH_OBJ);

	$possibleRoles = [];
	foreach ($member->roles as $role) {
		if ($role == $settings->roleAdmin) {
			$possibleRoles[] = 'ADMIN';
			$possibleRoles[] = 'HEADPOOLER';
			$possibleRoles[] = 'REFEREE';
		} elseif ($role == $settings->roleHeadpooler) {
			$possibleRoles[] = 'HEADPOOLER';
		} elseif ($role == $settings->roleMappooler) {
			$possibleRoles[] = 'MAPPOOLER';
		} elseif ($role == $settings->roleReferee) {
			$possibleRoles[] = 'REFEREE';
		} elseif ($role == $settings->rolePlayer) {
			$possibleRoles[] = 'PLAYER';
		}
	}
	$now = strtotime(gmdate('Y-m-d H:i:s'));
	if ($settings->registrationsOpen && $now > strtotime($settings->registrationsFrom) && $now < strtotime($settings->registrationsTo)) {
		$possibleRoles[] = 'REGISTRATION';
	}
	$stmt = $db->prepare('SELECT COUNT(*) as rowcount
		FROM registrations
		WHERE id = :id');
	$stmt->bindValue(':id', $user->id, PDO::PARAM_INT);
	$stmt->execute();
	$rows = $stmt->fetchAll(PDO::FETCH_OBJ);
	if ($rows[0]->rowcount != '0') {
		$possibleRoles[] = 'REGISTRATION';
	}
	$possibleRoles = array_values(array_unique($possibleRoles));

	if (count($possibleRoles) == 1) {
		$token = generateToken();
		$stmt = $db->prepare('INSERT INTO bearer_tokens (token, user_id, scope)
			VALUES (:token, :user_id, :scope)');
		$stmt->bindValue(':token', $token, PDO::PARAM_STR);
		$stmt->bindValue(':user_id', $user->id, PDO::PARAM_INT);
		$stmt->bindValue(':scope', $possibleRoles[0], PDO::PARAM_STR);
		$stmt->execute();

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

		$response = new stdClass;
		$response->error = '0';
		$response->message = 'Login successfull';
		$response->token = $token;
		$response->scope = $possibleRoles[0];
		echo json_encode($response);
		return;
	}

	$body = json_decode(file_get_contents('php://input'));

	if (isset($body->scope) && in_array($body->scope, $possibleRoles)) {
		$token = generateToken();
		$stmt = $db->prepare('INSERT INTO bearer_tokens (token, user_id, scope)
			VALUES (:token, :user_id, :scope)');
		$stmt->bindValue(':token', $token, PDO::PARAM_STR);
		$stmt->bindValue(':user_id', $user->id, PDO::PARAM_INT);
		$stmt->bindValue(':scope', $body->scope, PDO::PARAM_STR);
		$stmt->execute();

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

		$response = new stdClass;
		$response->error = '0';
		$response->message = 'Login successfull';
		$response->token = $token;
		$response->scope = $body->scope;
		echo json_encode($response);
		return;
	}

	if (count($possibleRoles) > 1) {
		$response = new stdClass;
		$response->error = '0';
		$response->message = 'Multiple roles possible';
		$response->scopes = $possibleRoles;
		echo json_encode($response);
		return;
	}

	echoError(1, 'Error when trying to login');
}

function getDiscordRoles() {
	global $db;

	$stmt = $db->prepare('SELECT id, name, color, position
		FROM discord_roles
		ORDER BY position DESC');
	$stmt->execute();
	echo json_encode($stmt->fetchAll(PDO::FETCH_OBJ));
}

function postDiscordRoles() {
	global $db;
	global $discordApi;

	$user = checkToken();
	if (!isset($user) || $user->scope != 'ADMIN') {
		return;
	}

	$roles = $discordApi->getGuildRoles();
	$stmt = $db->prepare('TRUNCATE discord_roles');
	$stmt->execute();
	foreach ($roles as $role) {
		$stmt = $db->prepare('INSERT INTO discord_roles (id, name, color, position)
			VALUES (:id, :name, :color, :position)');
		$stmt->bindValue(':id', $role->id, PDO::PARAM_INT);
		$stmt->bindValue(':name', $role->name, PDO::PARAM_STR);
		$stmt->bindValue(':color', $role->color, PDO::PARAM_INT);
		$stmt->bindValue(':position', $role->position, PDO::PARAM_INT);
		$stmt->execute();
	}

	echoError(0, 'Roles refreshed');
}

function getTwitchLogin() {
	global $twitchApi;
	echo json_encode(array('uri' => $twitchApi->getLoginUri()));
}

function postTwitchLogin() {
	global $db;
	global $twitchApi;
	$user = checkToken();
	if (!isset($user)) {
		return;
	}
	$body = json_decode(file_get_contents('php://input'));
	if ($user->scope == 'REGISTRATION') {
		$accessToken = $twitchApi->getAccessToken($body->code, $body->state);
		if ($accessToken) {
			$twitchUser = $twitchApi->getUser($accessToken);
			$sub = $twitchApi->getUserSubscription($accessToken, $twitchUser->_id);
			$stmt = $db->prepare('UPDATE registrations
				SET twitch_id = :twitch_id
				WHERE id = :id');
			$stmt->bindValue(':twitch_id', $twitchUser->_id, PDO::PARAM_INT);
			$stmt->bindValue(':id', $user->id, PDO::PARAM_INT);
			$stmt->execute();
			$stmt = $db->prepare('INSERT INTO twitch_users (id, username, display_name, avatar, sub_since, sub_plan)
				VALUES (:id, :username, :display_name, :avatar, :sub_since, :sub_plan)');
			$stmt->bindValue(':id', $twitchUser->_id, PDO::PARAM_INT);
			$stmt->bindValue(':username', $twitchUser->name, PDO::PARAM_STR);
			$stmt->bindValue(':display_name', $twitchUser->display_name, PDO::PARAM_STR);
			$stmt->bindValue(':avatar', $twitchUser->logo, PDO::PARAM_STR);
			$stmt->bindValue(':sub_since', isset($sub->created_at) ? $sub->created_at : null, PDO::PARAM_STR);
			$stmt->bindValue(':sub_plan', isset($sub->sub_plan) ? $sub->sub_plan : null, PDO::PARAM_STR);
			$stmt->execute();
			echoError(0, 'Twitch account linked');
			return;
		} else {
			echoError(1, 'Access code is not valid');
			return;
		}
	}
}

?>