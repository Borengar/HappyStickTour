<?php

require_once '../php_classes/Database.php';
require_once '../php_classes/OsuApi.php';
require_once '../php_classes/TwitchApi.php';
require_once '../php_classes/DiscordApi.php';
$database = new Database();
$db = $database->getConnection();

// show errors
$db->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING );

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
			case 'PUT': putLobbies(); break; // update lobbies
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
		}
		break;
	case 'mappool':
		switch ($_SERVER['REQUEST_METHOD']) {
			case 'GET': getMappool(); break; // get mappool
			case 'PUT': putMappool(); break; // update mappool
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
	case 'osugames':
		switch ($_SERVER['REQUEST_METHOD']) {
			case 'POST': postOsuGame(); break; // insert a bracket reset
		}
		break;
	case 'osugame':
		switch ($_SERVER['REQUEST_METHOD']) {
			case 'PUT': putOsuGame(); break; // update an osu game
			case 'DELETE': deleteOsuGame(); break; // delete a bracket reset
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
	case 'mappoolers':
		switch ($_SERVER['REQUEST_METHOD']) {
			case 'GET': getMappoolers(); break; // get mappoolers
			case 'POST': postMappoolers(); break; // refresh list of mappoolers
		}
		break;
	case 'mappooler':
		switch ($_SERVER['REQUEST_METHOD']) {
			case 'PUT': putMappooler(); break; // update mappooler
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
		$stmt = $db->prepare('SELECT registrations.osu_id as osuId, registrations.registration_time as registrationTime, osu_users.username as osuUsername, osu_users.avatar_url as osuAvatarUrl, osu_users.hit_accuracy as osuHitAccuracy, osu_users.level as osuLevel, osu_users.play_count as osuPlayCount, osu_users.pp as osuPp, osu_users.rank as osuRank, osu_users.rank_history as osuRankHistory, osu_users.best_score as osuBestScore, osu_users.playstyle as osuPlaystyle, osu_users.join_date as osuJoinDate, osu_users.country as osuCountry, registrations.twitch_id as twitchId, twitch_users.username as twitchUsername, twitch_users.display_name as twitchDisplayName, twitch_users.avatar as twitchAvatar, twitch_users.sub_since as twitchSubSince, twitch_users.sub_plan as twitchSubPlan, discord_users.id as discordId, discord_users.username as discordUsername, discord_users.discriminator as discordDiscriminator, discord_users.avatar as discordAvatar
			FROM registrations INNER JOIN osu_users ON registrations.osu_id = osu_users.id INNER JOIN discord_users ON registrations.id = discord_users.id LEFT JOIN twitch_users ON registrations.twitch_id = twitch_users.id
			ORDER BY registrations.registration_time ASC');
		$stmt->execute();
		echo json_encode($stmt->fetchAll(PDO::FETCH_OBJ));
		return;
	}
}

function putRegistration() {
	global $db;
	global $discordApi;

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
			$member = $discordApi->getGuildMember($body->idNew);
			$stmt = $db->prepare('INSERT INTO discord_users (id, username, discriminator, avatar)
				VALUES (:id, :username, :discriminator, :avatar)');
			$stmt->bindValue(':id', $body->idNew, PDO::PARAM_INT);
			$stmt->bindValue(':username', $member->user->username, PDO::PARAM_STR);
			$stmt->bindValue(':discriminator', $member->user->discriminator, PDO::PARAM_STR);
			$stmt->bindValue(':avatar', $member->user->avatar, PDO::PARAM_STR);
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

	$user = checkToken();

	if (isset($user) && $user->scope == 'ADMIN') {
		if (isset($_GET['round']) && isset($_GET['tier'])) {
			$stmt = $db->prepare('SELECT players.id as userId, osu_users.id as osuId, osu_users.username as osuUsername, osu_users.avatar_url as osuAvatarUrl, osu_users.hit_accuracy as osuHitAccuracy, osu_users.level as osuLevel, osu_users.play_count as osuPlayCount, osu_users.pp as osuPp, osu_users.rank as osuRank, osu_users.rank_history as osuRankHistory, osu_users.best_score as osuBestScore, osu_users.playstyle as osuPlaystyle, osu_users.join_date as osuJoinDate, osu_users.country as osuCountry
				FROM players INNER JOIN osu_users ON players.osu_id = osu_users.id
				WHERE players.tier = :tier AND next_round = :next_round');
			$stmt->bindValue(':tier', $_GET['tier'], PDO::PARAM_INT);
			$stmt->bindValue(':next_round', $_GET['round'], PDO::PARAM_INT);
			$stmt->execute();
			$players = $stmt->fetchAll(PDO::FETCH_OBJ);
			foreach ($players as &$player) {
				$stmt = $db->prepare('SELECT time_from as timeFrom, time_to as timeTo
					FROM availabilities
					WHERE round = :round AND user_id = :user_id
					ORDER BY time_from ASC');
				$stmt->bindValue(':round', $_GET['round'], PDO::PARAM_INT);
				$stmt->bindValue(':user_id', $player->userId, PDO::PARAM_INT);
				$stmt->execute();
				$player->availabilities = $stmt->fetchAll(PDO::FETCH_OBJ);
			}
			echo json_encode($players);
			return;
		}
	}

	$stmt = $db->prepare('SELECT osu_users.id as osuId, osu_users.username as osuUsername, osu_users.avatar_url as osuAvatarUrl, osu_users.hit_accuracy as osuHitAccuracy, osu_users.level as osuLevel, osu_users.play_count as osuPlayCount, osu_users.pp as osuPp, osu_users.rank as osuRank, osu_users.rank_history as osuRankHistory, osu_users.best_score as osuBestScore, osu_users.playstyle as osuPlaystyle, osu_users.join_date as osuJoinDate, osu_users.country as osuCountry, tiers.id as tierId, tiers.name as tierName
		FROM players INNER JOIN osu_users ON players.osu_id = osu_users.id INNER JOIN tier ON players.tier = tiers.id');
	$stmt->execute();
	echo json_encode($stmt->fetchAll(PDO::FETCH_OBJ));
}

function getRounds() {
	global $database;
	$db = $database->getConnection();

	$stmt = $db->prepare('SELECT id, name, lobby_size as lobbySize, best_of as bestOf, is_first_round as isFirstRound, player_amount as playerAmount, is_start_round as isStartRound, has_continue as hasContinue, continue_amount as continueAmount, continue_round as continueRound, has_drop_down as hasDropDown, drop_down_amount as dropDownAmount, drop_down_round as dropDownRound, has_elimination as hasElimination, eliminated_amount as eliminatedAmount, has_bracket_reset as hasBracketReset, mappools_released as mappoolsReleased, lobbies_released as lobbiesReleased, copy_mappool as copyMappool, copy_mappool_from as copyMappoolFrom
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

	$stmt = $db->prepare('INSERT INTO rounds (name, lobby_size, best_of, is_first_round, player_amount, is_start_round, has_continue, continue_amount, continue_round, has_drop_down, drop_down_amount, drop_down_round, has_elimination, eliminated_amount, has_bracket_reset, mappools_released, lobbies_released, copy_mappool, copy_mappool_from)
		VALUES (:name, :lobby_size, :best_of, :is_first_round, :player_amount, :is_start_round, :has_continue, :continue_amount, :continue_round, :has_drop_down, :drop_down_amount, :drop_down_round, :has_elimination, :eliminated_amount, :has_bracket_reset, :mappools_released, :lobbies_released, :copy_mappool, :copy_mappool_from)');
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
	$stmt->bindValue(':copy_mappool', $body->copyMappool, PDO::PARAM_BOOL);
	$stmt->bindValue(':copy_mappool_from', $body->copyMappoolFrom, PDO::PARAM_INT);
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

	$stmt = $db->prepare('SELECT id, name, lobby_size as lobbySize, best_of as bestOf, is_first_round as isFirstRound, player_amount as playerAmount, is_start_round as isStartRound, has_continue as hasContinue, continue_amount as continueAmount, continue_round as continueRound, has_drop_down as hasDropDown, drop_down_amount as dropDownAmount, drop_down_round as dropDownRound, has_elimination as hasElimination, eliminated_amount as eliminatedAmount, has_bracket_reset as hasBracketReset, mappools_released as mappoolsReleased, lobbies_released as lobbiesReleased, copy_mappool as copyMappool, copy_mappool_from as copyMappoolFrom
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
		SET name = :name, lobby_size = :lobby_size, best_of = :best_of, is_first_round = :is_first_round, player_amount = :player_amount, is_start_round = :is_start_round, has_continue = :has_continue, continue_amount = :continue_amount, continue_round = :continue_round, has_drop_down = :has_drop_down, drop_down_amount = :drop_down_amount, drop_down_round = :drop_down_round, has_elimination = :has_elimination, eliminated_amount = :eliminated_amount, has_bracket_reset = :has_bracket_reset, mappools_released = :mappools_released, lobbies_released = :lobbies_released, copy_mappool = :copy_mappool, copy_mappool_from = :copy_mappool_from
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
	$stmt->bindValue(':copy_mappool', $body->copyMappool, PDO::PARAM_BOOL);
	$stmt->bindValue(':copy_mappool_from', $body->copyMappoolFrom, PDO::PARAM_INT);
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

	$user = checkToken();
	if (!isset($user) || $user->scope == 'PLAYER' || $user->scope == 'REFEREE') {
		$stmt = $db->prepare('SELECT lobbies.id, lobbies.round, lobbies.tier, lobbies.match_id as matchId, lobbies.match_time as matchTime
			FROM lobbies INNER JOIN rounds ON lobbies.round = rounds.id
			WHERE lobbies.round LIKE :round AND lobbies.tier LIKE :tier AND rounds.lobbies_released = 1');
		$stmt->bindValue(':round', isset($_GET['round']) ? $_GET['round'] : '%', PDO::PARAM_STR);
		$stmt->bindValue(':tier', isset($_GET['tier']) ? $_GET['tier'] : '%', PDO::PARAM_STR);
		$stmt->execute();
		$lobbies = $stmt->fetchAll(PDO::FETCH_OBJ);
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
		$stmt->bindValue(':round', isset($_GET['round']) ? $_GET['round'] : '%', PDO::PARAM_STR);
		$stmt->bindValue(':tier', isset($_GET['tier']) ? $_GET['tier'] : '%', PDO::PARAM_STR);
		$stmt->execute();
		$lobbies = $stmt->fetchAll(PDO::FETCH_OBJ);
		foreach ($lobbies as &$lobby) {
			$stmt = $db->prepare('SELECT lobby_slots.id, lobby_slots.user_id as userId, lobby_slots.continue_to_upper as continueToUpper, lobby_slots.drop_down as dropDown, osu_users.id as osuId, osu_users.username as osuUsername, osu_users.avatar_url as osuAvatarUrl, osu_users.hit_accuracy as osuHitAccuracy, osu_users.level as osuLevel, osu_users.play_count as osuPlayCount, osu_users.pp as osuPp, osu_users.rank as osuRank, osu_users.rank_history as osuRankHistory, osu_users.best_score as osuBestScore, osu_users.playstyle as osuPlaystyle, osu_users.join_date as osuJoinDate, osu_users.country as osuCountry
				FROM lobby_slots LEFT JOIN players ON lobby_slots.user_id = players.id LEFT JOIN osu_users ON players.osu_id = osu_users.id
				WHERE lobby_slots.lobby = :id');
			$stmt->bindValue(':id', $lobby->id, PDO::PARAM_INT);
			$stmt->execute();
			$lobby->slots = $stmt->fetchAll(PDO::FETCH_OBJ);
			foreach ($lobby->slots as &$slot) {
				if (!empty($slot->userId)) {
					$stmt = $db->prepare('SELECT time_from as timeFrom, time_to as timeTo
						FROM availabilities
						WHERE round = :round AND user_id = :user_id
						ORDER BY time_from ASC');
					$stmt->bindValue(':round', $lobby->round, PDO::PARAM_INT);
					$stmt->bindValue(':user_id', $slot->userId, PDO::PARAM_INT);
					$stmt->execute();
					$slot->availabilities = $stmt->fetchAll(PDO::FETCH_OBJ);
				} else {
					$slot->availabilities = [];
				}
			}
		}
		echo json_encode($lobbies);
		return;
	}
}

function putLobbies() {
	global $db;

	$user = checkToken();
	if (!isset($user)) {
		return;
	}

	$body = json_decode(file_get_contents('php://input'));

	if ($user->scope == 'ADMIN') {
		foreach ($body->lobbies as $lobby) {
			$stmt = $db->prepare('UPDATE lobbies
				SET match_time = :match_time
				WHERE id = :id');
			$stmt->bindValue(':match_time', $lobby->matchTime, PDO::PARAM_STR);
			$stmt->bindValue(':id', $lobby->id, PDO::PARAM_INT);
			$stmt->execute();
			foreach ($lobby->slots as $slot) {
				$stmt = $db->prepare('UPDATE lobby_slots
					SET user_id = :user_id
					WHERE id = :id');
				$stmt->bindValue(':user_id', $slot->userId, PDO::PARAM_INT);
				$stmt->bindValue(':id', $slot->id, PDO::PARAM_INT);
				$stmt->execute();
				if (!empty($slot->userId)) {
					$stmt = $db->prepare('UPDATE players
						SET current_lobby = :current_lobby
						WHERE id = :id');
					$stmt->bindValue(':current_lobby', $lobby->id, PDO::PARAM_INT);
					$stmt->bindValue(':id', $slot->userId, PDO::PARAM_INT);
					$stmt->execute();
				}
			}
		}
		echoError(0, 'Lobbies updated');
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
	global $db;

	$user = checkToken();
	if (!isset($user)) {
		return;
	}

	$body = json_decode(file_get_contents('php://input'));

	if ($user->scope == 'REFEREE') {
		if (isset($body->matchId)) {
			$stmt = $db->prepare('UPDATE lobbies
				SET match_id = :match_id
				WHERE id = :id');
			$stmt->bindValue(':match_id', $body->matchId, PDO::PARAM_INT);
			$stmt->bindValue(':id', $_GET['lobby'], PDO::PARAM_INT);
			$stmt->execute();
		}
		if (isset($body->comment)) {
			$stmt = $db->prepare('UPDATE lobbies
				SET comment = :comment
				WHERE id = :id');
			$stmt->bindValue(':comment', $body->comment, PDO::PARAM_STR);
			$stmt->bindValue(':id', $_GET['lobby'], PDO::PARAM_INT);
			$stmt->execute();
		}
		if (isset($body->continues)) {
			$stmt = $db->prepare('SELECT COUNT(*) as rowcount
				FROM lobby_slots INNER JOIN players ON lobby_slots.user_id = players.id
				WHERE lobby_slots.lobby = :lobby AND players.current_lobby <> :current_lobby');
			$stmt->bindValue(':lobby', $_GET['lobby'], PDO::PARAM_INT);
			$stmt->bindValue(':current_lobby', $_GET['lobby'], PDO::PARAM_INT);
			$stmt->execute();
			$rowcount = $stmt->fetch(PDO::FETCH_OBJ);
			if ($rowcount->rowcount == 0) {
				$stmt = $db->prepare('SELECT rounds.continue_round as continueRound, rounds.drop_down_round as dropDownRound
					FROM rounds INNER JOIN lobbies ON rounds.id = lobbies.round
					WHERE lobbies.id = :id');
				$stmt->bindValue(':id', $_GET['lobby'], PDO::PARAM_INT);
				$stmt->execute();
				$round = $stmt->fetch(PDO::FETCH_OBJ);
				foreach ($body->continues as $player) {
					$stmt = $db->prepare('UPDATE lobby_slots
						SET continue_to_upper = :continue_to_upper, drop_down = :drop_down, eliminated = :eliminated, forfeit = :forfeit, noshow = :noshow
						WHERE lobby = :lobby and user_id = :user_id');
					$stmt->bindValue(':continue_to_upper', $player->continue == 'continue', PDO::PARAM_BOOL);
					$stmt->bindValue(':drop_down', $player->continue == 'dropdown', PDO::PARAM_BOOL);
					$stmt->bindValue(':eliminated', $player->continue == 'eliminated', PDO::PARAM_BOOL);
					$stmt->bindValue(':forfeit', $player->continue == 'forfeit', PDO::PARAM_BOOL);
					$stmt->bindValue(':noshow', $player->continue == 'noshow', PDO::PARAM_BOOL);
					$stmt->bindValue(':lobby', $_GET['lobby'], PDO::PARAM_INT);
					$stmt->bindValue(':user_id', $player->id, PDO::PARAM_INT);
					$stmt->execute();
					$nextRound = null;
					switch ($player->continues) {
						case 'continue': $nextRound = $round->continueRound; break;
						case 'dropdown': $nextRound = $round->dropDownRound; break;
						default: $nextRound = null;
					}
					$stmt = $db->prepare('UPDATE players
						SET next_round = :next_round
						WHERE id = :id');
					$stmt->bindValue(':next_round', $nextRound, PDO::PARAM_INT);
					$stmt->bindValue(':id', $player->id, PDO::PARAM_INT);
					$stmt->execute();
				}
			}
		}
		echoError(0, 'Lobby updated');
		return;
	}
}

function getMappools() {
	global $db;

	$user = checkToken();

	if (!isset($user)) {
		$stmt = $db->prepare('SELECT mappools.id, mappools.tier, mappools.round, mappools.mappack
			FROM mappools INNER JOIN rounds ON mappools.round = rounds.id
			WHERE rounds.mappools_released = 1');
		$stmt->execute();
		echo json_encode($stmt->fetchAll(PDO::FETCH_OBJ));
		return;
	}

	if ($user->scope == 'PLAYER') {
		$stmt = $db->prepare('SELECT tier
			FROM players
			WHERE discord_id = :discord_id');
		$stmt->bindValue(':discord_id', $user->id, PDO::PARAM_INT);
		$stmt->execute();
		$tier = $stmt->fetch(PDO::FETCH_OBJ)->tier;
		$stmt = $db->prepare('SELECT mappools.id, mappools.tier, mappools.round, mappools.mappack
			FROM mappools INNER JOIN rounds ON mappools.round = rounds.id
			WHERE rounds.mappools_released = 1 AND mappools.tier = :tier');
		$stmt->bindValue(':tier', $tier, PDO::PARAM_INT);
		$stmt->execute();
		echo json_encode($stmt->fetchAll(PDO::FETCH_OBJ));
		return;
	}

	if ($user->scope == 'MAPPOOLER') {
		$stmt = $db->prepare('SELECT tier
			FROM mappoolers
			WHERE discord_id = :discord_id');
		$stmt->bindValue(':discord_id', $user->id, PDO::PARAM_INT);
		$stmt->execute();
		$tier = $stmt->fetch(PDO::FETCH_OBJ)->tier;
		$stmt = $db->prepare('SELECT mappools.id, mappools.tier, mappools.round, mappools.mappack
			FROM mappools INNER JOIN rounds ON mappools.round = rounds.id
			WHERE rounds.mappools_released = 1 AND mappools.tier = :tier');
		$stmt->bindValue(':tier', $tier, PDO::PARAM_INT);
		$stmt->execute();
		echo json_encode($stmt->fetchAll(PDO::FETCH_OBJ));
		return;
	}

	if ($user->scope == 'HEADPOOLER') {
		$stmt = $db->prepare('SELECT mappools.id, mappools.tier, mappools.round, mappools.mappack
			FROM mappools INNER JOIN rounds ON mappools.round = rounds.id');
		$stmt->execute();
		echo json_encode($stmt->fetchAll(PDO::FETCH_OBJ));
		return;
	}
}

function getMappool() {
	global $db;

	$user = checkToken();

	if (!isset($_GET['mappool'])) {
		$stmt = $db->prepare('SELECT id
			FROM mappools
			WHERE round = :round AND tier = :tier');
		$stmt->bindValue(':round', $_GET['round'], PDO::PARAM_INT);
		$stmt->bindValue(':tier', $_GET['tier'], PDO::PARAM_INT);
		$stmt->execute();
		$row = $stmt->fetch(PDO::FETCH_OBJ);
		$id = $row->id;
	} else {
		$id = $_GET['mappool'];
	}

	if (!isset($user) || $user->scope == 'PLAYER' || $user->scope == 'REFEREE') {
		$stmt = $db->prepare('SELECT rounds.mappools_released as mappoolsReleased, mappools.mappack
			FROM rounds INNER JOIN mappools ON mappools.round = rounds.id
			WHERE mappools.id = :id');
		$stmt->bindValue(':id', $id, PDO::PARAM_INT);
		$stmt->execute();
		$row = $stmt->fetch(PDO::FETCH_OBJ);
		if ($row->mappoolsReleased == 0) {
			return;
		}
	}

	if ($user->scope == 'MAPPOOLER') {
		$stmt = $db->prepare('SELECT tier
			FROM mappoolers
			WHERE discord_id = :discord_id');
		$stmt->bindValue(':discord_id', $user->id, PDO::PARAM_INT);
		$stmt->execute();
		$mappooler = $stmt->fetch(PDO::FETCH_OBJ);
		$stmt = $db->prepare('SELECT tier
			FROM mappools
			WHERE id = :id');
		$stmt->bindValue(':id', $id, PDO::PARAM_INT);
		$stmt->execute();
		$mappool = $stmt->fetch(PDD::FETCH_OBJ);
		if ($mappooler->tier != $mappool->tier) {
			return;
		}
	}

	$mappool = new stdClass;
	$mappool->id = $id;
	$mappool->mappack = $row->mappack;
	$stmt = $db->prepare('SELECT mappool_slots.id, mappool_slots.beatmap_id as beatmapId, mappool_slots.tiebreaker, mappool_slots.freemod, mappool_slots.hardrock, mappool_slots.doubletime, mappool_slots.hidden, osu_beatmaps.beatmapset_id as beatmapsetId, osu_beatmaps.title, osu_beatmaps.artist, osu_beatmaps.version, osu_beatmaps.cover, osu_beatmaps.preview_url as previewUrl, osu_beatmaps.total_length as totalLength, osu_beatmaps.bpm, osu_beatmaps.count_circles as countCircles, osu_beatmaps.count_sliders as countSliders, osu_beatmaps.cs, osu_beatmaps.drain, osu_beatmaps.accuracy, osu_beatmaps.ar, osu_beatmaps.difficulty_rating as difficultyRating
		FROM mappool_slots INNER JOIN osu_beatmaps ON mappool_slots.beatmap_id = osu_beatmaps.beatmap_id
		WHERE mappool_slots.mappool = :mappool');
	$stmt->bindValue(':mappool', $id, PDO::PARAM_INT);
	$stmt->execute();
	$rows = $stmt->fetchAll(PDO::FETCH_OBJ);
	$mappool->slots = [];
	foreach ($rows as $beatmap) {
		$slot = new stdClass;
		$slot->id = $beatmap->id;
		$slot->tiebreaker = $beatmap->tiebreaker;
		$slot->freemod = $beatmap->freemod;
		$slot->hardrock = $beatmap->hardrock;
		$slot->doubletime = $beatmap->doubletime;
		$slot->hidden = $beatmap->hidden;
		$slot->beatmap = new stdClass;
		$slot->beatmap->id = $beatmap->beatmapId;
		$slot->beatmap->beatmapsetId = $beatmap->beatmapsetId;
		$slot->beatmap->title = $beatmap->title;
		$slot->beatmap->artist = $beatmap->artist;
		$slot->beatmap->version = $beatmap->version;
		$slot->beatmap->cover = $beatmap->cover;
		$slot->beatmap->previewUrl = $beatmap->previewUrl;
		$slot->beatmap->totalLength = $beatmap->totalLength;
		$slot->beatmap->bpm = $beatmap->bpm;
		$slot->beatmap->countCircles = $beatmap->countCircles;
		$slot->beatmap->countSliders = $beatmap->countSliders;
		$slot->beatmap->cs = $beatmap->cs;
		$slot->beatmap->drain = $beatmap->drain;
		$slot->beatmap->accuracy = $beatmap->accuracy;
		$slot->beatmap->ar = $beatmap->ar;
		$slot->beatmap->difficultyRating = $beatmap->difficultyRating;
		$mappools->slots[] = $slot;
	}

	echo json_encode($mappool);
}

function putMappool() {
	global $db;

	$user = checkToken();

	if (!isset($_GET['mappool'])) {
		$stmt = $db->prepare('SELECT id
			FROM mappools
			WHERE round = :round AND tier = :tier');
		$stmt->bindValue(':round', $_GET['round'], PDO::PARAM_INT);
		$stmt->bindValue(':tier', $_GET['tier'], PDO::PARAM_INT);
		$stmt->execute();
		$row = $stmt->fetch(PDO::FETCH_OBJ);
		if (!isset($row->id)) {
			$stmt = $db->prepare('INSERT INTO mappools (round, tier)
				VALUES (:round, :tier)');
			$stmt->bindValue(':round', $_GET['round'], PDO::PARAM_INT);
			$stmt->bindValue(':tier', $_GET['tier'], PDO::PARAM_INT);
			$stmt->execute();
			$id = $db->lastInsertId();
		} else {
			$id = $row->id;
		}
	} else {
		$id = $_GET['mappool'];
	}

	if (!isset($user) || $user->scope == 'PLAYER' || $user->scope == 'REFEREE' || $user->scope == 'ADMIN') {
		return;
	}

	$body = json_decode(file_get_contents('php://input'));

	if ($user->scope == 'MAPPOOLER') {
		$stmt = $db->prepare('SELECT rounds.mappools_released as mappoolsReleased, mappools.tier
			FROM rounds INNER JOIN mappools ON rounds.id = mappools.round
			WHERE mappools.id = :id');
		$stmt->bindValue(':id', $id, PDO::PARAM_INT);
		$stmt->execute();
		$row = $stmt->fetch(PDO::FETCH_OBJ);
		if ($row->mappoolsReleased == 1) {
			return;
		}
		$stmt = $db->prepare('SELECT tier
			FROM mappoolers
			WHERE discord_id = :discord_id');
		$stmt->bindValue(':discord_id', $user->id, PDO::PARAM_INT);
		$stmt->execute();
		$mappooler = $stmt->fetch(PDO::FETCH_OBJ);
		if ($row->tier != $mappooler->tier) {
			return;
		}

		if (isset($body->slots)) {
			$stmt = $db->prepare('DELETE FROM mappool_slots
				WHERE mappool = :mappool');
			$stmt->bindValue(':mappool', $id, PDO::PARAM_INT);
			$stmt->execute();
			foreach ($body->slots as $slot) {
				$stmt = $db->prepare('INSERT INTO mappool_slots (mappool, beatmap_id, tiebreaker, freemod, hardrock, doubletime, hidden)
					VALUES (:mappool, :beatmap_id, :tiebreaker, :freemod, :hardrock, :doubletime, :hidden)');
				$stmt->bindValue(':mappool', $id, PDO::PARAM_INT);
				$stmt->bindValue(':beatmap_id', $body->beatmapId, PDO::PARAM_INT);
				$stmt->bindValue(':tiebreaker', $body->tiebreaker, PDO::PARAM_BOOL);
				$stmt->bindValue(':freemod', $body->freemod, PDO::PARAM_BOOL);
				$stmt->bindValue(':hardrock', $body->hardrock, PDO::PARAM_BOOL);
				$stmt->bindValue(':doubletime', $body->doubletime, PDO::PARAM_BOOL);
				$stmt->bindValue(':hidden', $body->hidden, PDO::PARAM_BOOL);
				$stmt->execute();
			}
		}

		echoError(0, 'Mappool updated');
		return;
	}

	if ($user->scope == 'HEADPOOLER') {
		if (isset($body->slots)) {
			$stmt = $db->prepare('DELETE FROM mappool_slots
				WHERE mappool = :mappool');
			$stmt->bindValue(':mappool', $id, PDO::PARAM_INT);
			$stmt->execute();
			foreach ($body->slots as $slot) {
				$stmt = $db->prepare('INSERT INTO mappool_slots (mappool, beatmap_id, tiebreaker, freemod, hardrock, doubletime, hidden)
					VALUES (:mappool, :beatmap_id, :tiebreaker, :freemod, :hardrock, :doubletime, :hidden)');
				$stmt->bindValue(':mappool', $id, PDO::PARAM_INT);
				$stmt->bindValue(':beatmap_id', $body->beatmapId, PDO::PARAM_INT);
				$stmt->bindValue(':tiebreaker', $body->tiebreaker, PDO::PARAM_BOOL);
				$stmt->bindValue(':freemod', $body->freemod, PDO::PARAM_BOOL);
				$stmt->bindValue(':hardrock', $body->hardrock, PDO::PARAM_BOOL);
				$stmt->bindValue(':doubletime', $body->doubletime, PDO::PARAM_BOOL);
				$stmt->bindValue(':hidden', $body->hidden, PDO::PARAM_BOOL);
				$stmt->execute();
			}
		}
		if (isset($body->mappack)) {
			$stmt = $db->prepare('UPDATE mappools
				SET mappack = :mappack
				WHERE id = :id');
			$stmt->bindValue(':mappack', $body->mappack, PDO::PARAM_STR);
			$stmt->bindValue(':id', $id, PDO::PARAM_INT);
			$stmt->execute();
		}

		echoError(0, 'Mappool updated');
		return;
	}
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
	global $db;

	$user = checkToken();

	if (!isset($user) || $user->scope != 'REFEREE') {
		return;
	}

	$body = json_decode(file_get_contents('php://input'));

	if (isset($body->counts)) {
		$stmt = $db->prepare('UPDATE osu_match_games
			SET counts = :counts
			WHERE match_event = :match_event');
		$stmt->bindValue(':counts', $body->counts, PDO::PARAM_BOOL);
		$stmt->bindValue(':match_event', $_GET['id'], PDO::PARAM_INT);
		$stmt->execute();
	}
	if (isset($body->pickedBy)) {
		$stmt = $db->prepare('UPDATE osu_match_games
			SET picked_by = :picked_by
			WHERE match_event = :match_event');
		$stmt->bindValue(':picked_by', $body->pickedBy, PDO::PARAM_INT);
		$stmt->bindValue(':match_event', $_GET['id'], PDO::PARAM_INT);
	}

	echoError(0, 'Game updated');
	return;
}

function postOsuGame() {
	global $db;

	$user = checkToken();

	if (!isset($user) || $user->scope != 'REFEREE') {
		return;
	}

	$body = json_decode(file_get_contents('php://input'));

	$stmt = $db->prepare('SELECT MIN(t1.ID + 1) AS nextID
		FROM tablename t1 LEFT JOIN tablename t2 ON t1.ID + 1 = t2.ID
		WHERE t2.ID IS NULL');
	$stmt->execute();
	$freeId = $stmt->fetch(PDO::FETCH_OBJ)->nextID;
	$stmt = $db->prepare('INSERT INTO osu_match_events (id, match_id, type, timestamp)
		VALUES (:id, :match_id, :type, :timestamp)');
	$stmt->bindValue(':id', $freeId, PDO::PARAM_INT);
	$stmt->bindValue(':match_id', $_GET['match'], PDO::PARAM_INT);
	$stmt->bindValue(':type', 'bracket-reset', PDO::PARAM_STR);
	$stmt->bindValue(':timestamp', $body->time, PDO::PARAM_STR);
	$stmt->execute();

	echoError(0, 'Bracket reset created');
	return;
}

function deleteOsuGame() {
	global $db;

	$user = checkToken();

	if (!isset($user) || $user->scope != 'REFEREE') {
		return;
	}

	$body = json_decode(file_get_contents('php://input'));

	$stmt = $db->prepare('DELETE FROM osu_match_events
		WHERE id = :id AND type = \'bracket-reset\'');
	$stmt->bindValue(':id', $_GET['id'], PDO::PARAM_INT);
	$stmt->execute();

	echoError(0, 'Bracket reset removed');
	return;
}

function getAvailability() {
	global $db;

	$user = checkToken();

	if (!isset($user)) {
		return;
	}

	if ($user->scope == 'PLAYER') {
		$stmt = $db->prepare('SELECT availabilities.id, availabilities.time_from as timeFrom, availabilities.time_to as timeTo
			FROM availabilites INNER JOIN players ON availabilites.user_id = players.id
			WHERE availabilities.round = :round AND players.discord_id = :discord_id
			ORDER BY availabilities.time_from ASC');
		$stmt->bindValue(':round', $_GET['round'], PDO::PARAM_INT);
		$stmt->bindValue(':discord_id', $user->id, PDO::PARAM_INT);
		$stmt->execute();
		echo json_encode($stmt->fetchAll(PDO::FETCH_OBJ));
		return;
	}

	if ($user->scope == 'ADMIN') {
		$stmt = $db->prepare('SELECT availabilities.id, availabilities.time_from as timeFrom, availabilities.time_to as timeTo, players.discord_id as discordId, osu_users.id as osuId, osu_users.username as osuUsername, osu_users.avatar_url as osuAvatarUrl, osu_users.hit_accuracy as osuHitAccuracy, osu_users.level as osuLevel, osu_users.play_count as osuPlayCount, osu_users.pp as osuPp, osu_users.rank as osuRank, osu_users.rank_history as osuRankHistory, osu_users.best_score as osuBestScore, osu_users.playstyle as osuPlaystyle, osu_users.join_date as osuJoinDate, osu_users.country as osuCountry, tiers.id as tierId, tiers.name as tierName
			FROM availabilites INNER JOIN players ON availabilities.user_id = players.id INNER JOIN osu_users ON players.osu_id = osu_users.id INNER JOIN tiers ON players.tier = tiers.id
			WHERE availabilities.round = :round
			ORDER BY availabilities.time_from ASC');
		$stmt->bindValue(':round', $_GET['round'], PDO::PARAM_INT);
		$stmt->execute();
		echo json_encode($stmt->fetchAll(PDO::FETCH_OBJ));
		return;
	}
}

function putAvailability() {
	global $db;

	$user = checkToken();

	if (!isset($user)) {
		return;
	}

	$body = json_decode(file_get_contents('php://input'));

	if ($user->scope == 'ADMIN') {
		$stmt = $db->prepare('SELECT id
			FROM players
			WHERE discord_id = :discord_id');
		$stmt->bindValue(':discord_id', $user->id, PDO::PARAM_INT);
		$stmt->execute();
		$userId = $stmt->fetch(PDO::FETCH_OBJ)->id;
		$stmt = $db->prepare('DELETE FROM availabilities
			WHERE round = :round AND user_id = :user_id');
		$stmt->bindValue(':round', $_GET['round'], PDO::PARAM_INT);
		$stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
		$stmt->execute();

		foreach ($body->availabilities as $availability) {
			$stmt = $db->prepare('INSERT INTO availabilites (round, user_id, time_from, time_to)
				VALUES (:round, :user_id, :time_from, :time_to)');
			$stmt->bindValue(':round', $_GET['round'], PDO::PARAM_INT);
			$stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
			$stmt->bindValue(':time_from', $availability->timeFrom, PDO::PARAM_STR);
			$stmt->bindValue(':time_to', $availability->timeTo, PDO::PARAM_STR);
			$stmt->execute();
		}

		echoError(0, 'Availability saved');
		return;
	}
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

function getMappoolers() {
	global $db;

	$user = checkToken();
	if (!isset($user) || $user->scope != 'ADMIN') {
		return;
	}

	$stmt = $db->prepare('SELECT discord_users.id as discordId, discord_users.username as discordUsername, discord_users.discriminator as discordDiscriminator, discord_users.avatar as discordAvatar, mappoolers.tier as tierId, tiers.name as tierName
		FROM mappoolers INNER JOIN discord_users ON mappoolers.discord_id = discord_users.id LEFT JOIN tiers ON mappoolers.tier = tiers.id
		ORDER BY tiers.lower_endpoint ASC, discord_users.username');
	$stmt->execute();
	echo json_encode($stmt->fetchAll(PDO::FETCH_OBJ));
}

function postMappoolers() {
	global $db;
	global $discordApi;

	$user = checkToken();
	if (!isset($user) || $user->scope != 'ADMIN') {
		return;
	}

	$stmt = $db->prepare('SELECT role_mappooler as roleMappooler
		FROM settings');
	$stmt->execute();
	$roleId = $stmt->fetch(PDO::FETCH_OBJ)->roleMappooler;

	$allMembersDone = false;
	$highestId = null;
	$mappoolers = [];
	while (!$allMembersDone) {
		$members = $discordApi->getGuildMembers($highestId);
		foreach ($members as $member) {
			$highestId = $member->user->id;
			if (in_array($roleId, $member->roles)) {
				$mappoolers[] = $member;
			}
		}
		if (count($members) == 0) {
			$allMembersDone = true;
		}
	}

	$stmt = $db->prepare('SELECT id, discord_id as discordId, tiers
		FROM mappoolers');
	$stmt->execute();
	$existingMappoolers = $stmt->fetchAll(PDO::FETCH_OBJ);

	// remove mappoolers from the website that got the role removed
	foreach ($existingMappoolers as $existingMappooler) {
		$found = false;
		foreach ($mappoolers as $mappooler) {
			if ($existingMappoolers->discordId == $mappooler->user->id) {
				$found = true;
				break;
			}
		}
		if (!$found) {
			$stmt = $db->prepare('DELETE FROM mappoolers
				WHERE discord_id = :discord_id');
			$stmt->bindValue(':discord_id', $existingMappooler->discordId, PDO::PARAM_INT);
			$stmt->execute();
		}
	}

	// insert new mappoolers into the website that got the role
	foreach ($mappoolers as $mappooler) {
		$found = false;
		foreach ($existingMappoolers as $existingMappooler) {
			if ($mappooler->user->id == $existingMappooler->discordId) {
				$found = true;
				break;
			}
		}
		if (!$found) {
			$stmt = $db->prepare('INSERT INTO mappoolers (discord_id)
				VALUES (:discord_id)');
			$stmt->bindValue(':discord_id', $mappooler->user->id, PDO::PARAM_INT);
			$stmt->execute();
			$stmt = $db->prepare('INSERT INTO discord_users (id, username, discriminator, avatar)
				VALUES (:id, :username, :discriminator, :avatar)');
			$stmt->bindValue(':id', $mappooler->user->id, PDO::PARAM_INT);
			$stmt->bindValue(':username', $mappooler->user->username, PDO::PARAM_STR);
			$stmt->bindValue(':discriminator', $mappooler->user->discriminator, PDO::PARAM_STR);
			$stmt->bindValue(':avatar', $mappooler->user->avatar, PDO::PARAM_STR);
			$stmt->execute();
		}
	}

	echoError(0, 'Mappoolers refreshed');
}

function putMappooler() {
	global $db;

	$user = checkToken();
	if (!isset($user) || $user->scope != 'ADMIN') {
		return;
	}

	$body = json_decode(file_get_contents('php://input'));

	$stmt = $db->prepare('UPDATE mappoolers
		SET tier = :tier
		WHERE discord_id = :discord_id');
	$stmt->bindValue(':tier', $body->tier, PDO::PARAM_INT);
	$stmt->bindValue(':discord_id', $_GET['id'], PDO::PARAM_INT);
	$stmt->execute();

	echoError(0, 'Mappoolers updated');
}

?>