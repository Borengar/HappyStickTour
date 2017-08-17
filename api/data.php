<?php

require_once '../php_classes/Database.php';
require_once '../php_classes/OsuApi.php';
require_once '../php_classes/TwitchApi.php';
require_once '../php_classes/DiscordApi.php';
$database = new Database();
$osuApi = new OsuApi();
$discordApi = new DiscordApi();

date_default_timezone_set('UTC');

switch ($_GET['query']) {
	case 'user':
		switch ($_SERVER['REQUEST_METHOD']) {
			case 'GET': getCurrentUser(); break; // get user data
			case 'PUT': putCurrentUser(); break; // update user data
		}
		break;
	case 'registrations':
		switch ($_SERVER['REQUEST_METHOD']) {
			case 'GET': getRegistrations(); break; // get a list of all registrations
		}
		break;
	case 'players':
		switch ($_SERVER['REQUEST_METHOD']) {
			case 'GET': getPlayers(); break; // get a list of all players
			case 'PUT': putPlayers(); break; // gives all players the player role
			case 'POST': postPlayers(); break; // Seeds all registrations
		}
		break;
	case 'rounds':
		switch ($_SERVER['REQUEST_METHOD']) {
			case 'GET': getRounds(); break; // get a list of rounds in a tier
			case 'PUT': putRounds(); break; // update the rounds in a tier
		}
		break;
	case 'round':
		switch ($_SERVER['REQUEST_METHOD']) {
			case 'PUT': putRound(); break; // update a round
		}
		break;
	case 'tier':
		switch ($_SERVER['REQUEST_METHOD']) {
			case 'PUT': putTier(); break; // update a tier
			case 'DELETE': deleteTier(); break; // delete a tier
		}
		break;
	case 'tiers':
		switch ($_SERVER['REQUEST_METHOD']) {
			case 'GET': getTiers(); break; // get a list of all tiers
			case 'POST': postTier(); break; // create a new tier
		}
		break;
	case 'lobbies':
		switch ($_SERVER['REQUEST_METHOD']) {
			case 'GET': getLobbies(); break; // get a list of lobbies in a round
			case 'PUT': putLobbies(); break; // finalizes the lobbies for a round
			case 'POST': postLobbies(); break; // create lobbies for a round
			case 'DELETE': deleteLobbies(); break; // delete all lobbies of a round
		}
		break;
	case 'mappool':
		switch ($_SERVER['REQUEST_METHOD']) {
			case 'GET': getMappool(); break; // get the mappool for a round
			case 'POST': postMappool(); break; // insert a new map into the mappool of a round
		}
		break;
	case 'mappack':
		switch ($_SERVER['REQUEST_METHOD']) {
			case 'GET': getMappack(); break; // creates the mappack for a round
			case 'PUT': putMappack(); break; // update the mappack url for a round
		}
		break;
	case 'lobby':
		switch ($_SERVER['REQUEST_METHOD']) {
			case 'GET': getLobby(); break; // get a lobby
			case 'PUT': putLobby(); break; // update a lobby
		}
		break;
	case 'lobby_slot':
		switch ($_SERVER['REQUEST_METHOD']) {
			case 'PUT': putLobbySlot(); break; // update a lobby slot
		}
		break;
	case 'mappool_slot':
		switch ($_SERVER['REQUEST_METHOD']) {
			case 'PUT': putMappoolSlot(); break; // update a mappool slot
			case 'DELETE': deleteMappoolSlot(); break; // delete a mappool slot
		}
		break;
	case 'osu_profile':
		switch ($_SERVER['REQUEST_METHOD']) {
			case 'GET': getOsuProfile(); break; // get an osu account over the osu api
		}
		break;
	case 'osu_beatmap':
		switch ($_SERVER['REQUEST_METHOD']) {
			case 'GET': getOsuBeatmap(); break; // get an osu beatmap over the osu api
		}
		break;
	case 'match':
		switch ($_SERVER['REQUEST_METHOD']) {
			case 'GET': getMatch(); break; // get an osu match over the osu api
		}
		break;
	case 'game':
		switch ($_SERVER['REQUEST_METHOD']) {
			case 'PUT': putGame(); break; // update an osu match
		}
		break;
	case 'ticket':
		switch ($_SERVER['REQUEST_METHOD']) {
			case 'GET': getTicket(); break; // get a ticket
			case 'PUT': putTicket(); break; // add a message to a ticket
			case 'DELETE': deleteTicket(); break; // close a ticket
		}
		break;
	case 'tickets':
		switch ($_SERVER['REQUEST_METHOD']) {
			case 'GET': getTickets(); break; // get a list of all tickets
			case 'POST': postTicket(); break; // create a new ticket
		}
		break;
	case 'blacklist':
		switch ($_SERVER['REQUEST_METHOD']) {
			case 'GET': getBlacklist(); break; // get a list of all blacklisted osu accounts
			case 'POST': postBlacklist(); break; // add an osu account to the blacklist
			case 'DELETE': deleteBlacklist(); break; // remove an osu account from the blacklist
		}
		break;
	case 'free_players':
		switch ($_SERVER['REQUEST_METHOD']) {
			case 'GET': getFreePlayers(); break; // get a list of unscheduled players for a round
		}
		break;
	case 'availability':
		switch ($_SERVER['REQUEST_METHOD']) {
			case 'GET': getAvailability(); break; // returns a list of availabilites for a round
			case 'POST': postAvailability(); break; // creates a new availability for a round
			case 'DELETE': deleteAvailability(); break; // Deletes a availability
		}
		break;
	case 'settings':
		switch ($_SERVER['REQUEST_METHOD']) {
			case 'GET': getSettings(); break; // get general settings
			case 'PUT': putSettings(); break; // update general settings
		}
		break;
	case 'feedback':
		switch ($_SERVER['REQUEST_METHOD']) {
			case 'GET': getFeedback(); break; // get feedback for the mappool of a round
			case 'PUT': putFeedback(); break; // update feedback for the mappool of a round
		}
		break;
	case 'bans':
		switch ($_SERVER['REQUEST_METHOD']) {
			case 'GET': getBans(); break; // returns the bans of a match
			case 'POST': postBan(); break; // Saves a new ban in a match
			case 'DELETE': deleteBan(); break; // Removes ban from a match
		}
}

/**
 * Returns the discord id and scope of the authorized user or outputs an error message if token is not valid
 */
function checkToken() {
	$token = $_SERVER['HTTP_AUTHORIZATION'];
	global $database;
	$db = $database->getConnection();

	$stmt = $db->prepare('SELECT user_id, scope
		FROM bearer_tokens
		WHERE token = :token');
	$stmt->bindValue(':token', $token, PDO::PARAM_STR);
	$stmt->execute();
	$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
	if (count($rows) == 0) {
		$response = new stdClass;
		$response->message = 'Token is not valid';
		echo json_encode($response);
		die();
	}

	$user = new stdClass;
	$user->scope = $rows[0]['scope'];
	if ($user->scope != 'PUBLIC') {
		$user->id = $rows[0]['user_id'];
	}
	return $user;
}

function echoFeedback($error, $message) {
	$response = new stdClass;
	$response->error = $error ? '1' : '0';
	$response->message = $message;
	echo json_encode($response);
}

/**
 * Outputs user info of the requester
 */
function getCurrentUser() {
	global $database;
	$db = $database->getConnection();
	global $osuApi;
	$user = checkToken();

	if ($user->scope == 'PUBLIC') {
		http_response_code(401);
		echoFeedback(true, 'The scope PUBLIC has no user');
		return;
	}

	$profile = new stdClass;

	$stmt = $db->prepare('SELECT username, discriminator, avatar
		FROM discord_users
		WHERE id = :id');
	$stmt->bindValue(':id', $user->id, PDO::PARAM_STR);
	$stmt->execute();
	$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
	$discord_profile = new stdClass;
	$discord_profile->id = $user->id;
	$discord_profile->username = $rows[0]['username'];
	$discord_profile->discriminator = $rows[0]['discriminator'];
	$discord_profile->avatar = $rows[0]['avatar'];
	$profile->discord_profile = $discord_profile;

	if ($user->scope == 'REGISTRATION') {
		$stmt = $db->prepare('SELECT osu_id, twitch_id, time
			FROM registrations
			WHERE id = :id');
		$stmt->bindValue(':id', $user->id, PDO::PARAM_STR);
		$stmt->execute();
		$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
		$registration = new stdClass;
		if (empty($rows[0]['osu_id'])) {
			$registration->osu_profile = null;
			$registration->tier = null;
			$registration->time = null;
			$profile->twitch_profile = null;
		} else {
			$registration->osu_profile = $osuApi->getUser($rows[0]['osu_id']);
			foreach ($database->tiers() as $tier) {
				if ($tier['lower_endpoint'] <= $registration->osu_profile->pp_rank && $tier['upper_endpoint'] >= $registration->osu_profile->pp_rank) {
					$registration->tier = new stdClass;
					$registration->tier->id = $tier['id'];
					$registration->tier->lower_endpoint = $tier['lower_endpoint'];
					$registration->tier->upper_endpoint = $tier['upper_endpoint'];
					$registration->tier->name = $tier['name'];
				}
			}
			$registration->time = $rows[0]['time'];
			if (empty($rows[0]['twitch_id'])) {
				$profile->twitch_profile = null;
			} else {
				$stmt = $db->prepare('SELECT username, display_name, avatar, sub_since, sub_plan
					FROM twitch_users
					WHERE id = :id');
				$stmt->bindValue(':id', $rows[0]['twitch_id'], PDO::PARAM_INT);
				$stmt->execute();
				$twitch = $stmt->fetchAll(PDO::FETCH_ASSOC);
				$profile->twitch_profile = new stdClass;
				$profile->twitch_profile->username = $twitch[0]['username'];
				$profile->twitch_profile->display_name = $twitch[0]['display_name'];
				$profile->twitch_profile->avatar = $twitch[0]['avatar'];
				$profile->twitch_profile->sub_since = $twitch[0]['sub_since'];
				$profile->twitch_profile->sub_plan = $twitch[0]['sub_plan'];
			}
		}
		$profile->registration = $registration;
	}

	if ($user->scope == 'PLAYER') {
		$stmt = $db->prepare('SELECT osu_id, twitch_id, tier, trivia, current_lobby, next_round
			FROM players
			WHERE id = :id');
		$stmt->bindValue(':id', $user->id, PDO::PARAM_STR);
		$stmt->execute();
		$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
		$profile->osu_profile = $osuApi->getUser($rows[0]['osu_id']);
		$profile->trivia = $rows[0]['trivia'];
		$profile->current_lobby = $rows[0]['current_lobby'];
		foreach ($database->tiers() as $tier) {
			if ($tier['id'] == $rows[0]['tier']) {
				$profile->tier = new stdClass;
				$profile->tier->id = $tier['id'];
				$profile->tier->lower_endpoint = $tier['lower_endpoint'];
				$profile->tier->upper_endpoint = $tier['upper_endpoint'];
				$profile->tier->name = $tier['name'];
			}
		}
		if (empty($rows[0]['twitch_id'])) {
			$profile->twitch_profile = null;
		} else {
			$stmt = $db->prepare('SELECT username, display_name, avatar, sub_since, sub_plan
				FROM twitch_users
				WHERE id = :id');
			$stmt->bindValue(':id', $rows[0]['twitch_id'], PDO::PARAM_INT);
			$stmt->execute();
			$twitch = $stmt->fetchAll(PDO::FETCH_ASSOC);
			$profile->twitch_profile = new stdClass;
			$profile->twitch_profile->username = $twitch[0]['username'];
			$profile->twitch_profile->display_name = $twitch[0]['display_name'];
			$profile->twitch_profile->avatar = $twitch[0]['avatar'];
			$profile->twitch_profile->sub_since = $twitch[0]['sub_since'];
			$profile->twitch_profile->sub_plan = $twitch[0]['sub_plan'];
		}

		if (empty($rows[0]['next_round'])) {
			$profile->next_round = null;
		} else {
			$stmt = $db->prepare('SELECT name, time_from, time_to, week, level, time_from_2, time_to_2
				FROM rounds
				WHERE id = :id');
			$stmt->bindValue(':id', $rows[0]['next_round'], PDO::PARAM_INT);
			$stmt->execute();
			$round = $stmt->fetchAll(PDO::FETCH_ASSOC);
			$profile->next_round = new stdClass;
			$profile->next_round->id = $rows[0]['next_round'];
			$profile->next_round->name = $round[0]['name'];
			$profile->next_round->time_from = $round[0]['time_from'];
			$profile->next_round->time_to = $round[0]['time_to'];
			$profile->next_round->time_from_2 = $round[0]['time_from_2'];
			$profile->next_round->time_to_2 = $round[0]['time_to_2'];

			$next_upper_week = (int) $round[0]['week'] + 1;
			$next_upper_level = (int) $round[0]['level'];
			$next_lower_week = (int) $round[0]['week'];
			$next_lower_level = (int) $round[0]['level'] + 1;

			while (true) {
				$stmt = $db->prepare('SELECT id, input_amount, total_continue_to_upper, total_drop_down, name, time_from, time_to, time_from_2, time_to_2
					FROM rounds
					WHERE tier = :tier AND week = :week AND level = :level');
				$stmt->bindValue(':tier', $profile->tier->id, PDO::PARAM_INT);
				$stmt->bindValue(':week', $next_upper_week, PDO::PARAM_INT);
				$stmt->bindValue(':level', $next_upper_level, PDO::PARAM_INT);
				$stmt->execute();
				$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
				if (empty($rows[0])) {
					break;
				}
				if ($rows[0]['input_amount'] == $rows[0]['total_continue_to_upper']) {
					$next_upper_week++;
				} elseif ($rows[0]['input_amount'] == $rows[0]['total_drop_down']) {
					$next_upper_level++;
				} else {
					$profile->after_win = new stdClass;
					$profile->after_win->id = $rows[0]['id'];
					$profile->after_win->name = $rows[0]['name'];
					$profile->after_win->time_from = $rows[0]['time_from'];
					$profile->after_win->time_to = $rows[0]['time_to'];
					$profile->after_win->time_from_2 = $rows[0]['time_from_2'];
					$profile->after_win->time_to_2 = $rows[0]['time_to_2'];
					break;
				}
			}

			while (true) {
				$stmt = $db->prepare('SELECT id, input_amount, total_continue_to_upper, total_drop_down, name, time_from, time_to, time_from_2, time_to_2
					FROM rounds
					WHERE tier = :tier AND week = :week AND level = :level');
				$stmt->bindValue(':tier', $profile->tier->id, PDO::PARAM_INT);
				$stmt->bindValue(':week', $next_lower_week, PDO::PARAM_INT);
				$stmt->bindValue(':level', $next_lower_level, PDO::PARAM_INT);
				$stmt->execute();
				$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
				if (empty($rows[0])) {
					break;
				}
				if ($rows[0]['input_amount'] == $rows[0]['total_continue_to_upper']) {
					$next_lower_week++;
				} elseif ($rows[0]['input_amount'] == $rows[0]['total_drop_down']) {
					$next_lower_level++;
				} else {
					$profile->after_lose = new stdClass;
					$profile->after_lose->id = $rows[0]['id'];
					$profile->after_lose->name = $rows[0]['name'];
					$profile->after_lose->time_from = $rows[0]['time_from'];
					$profile->after_lose->time_to = $rows[0]['time_to'];
					$profile->after_lose->time_from_2 = $rows[0]['time_from_2'];
					$profile->after_lose->time_to_2 = $rows[0]['time_to_2'];
					break;
				}
			}
		}
	}

	if ($user->scope == 'REFEREE') {
		// nothing
	}

	if ($user->scope == 'MAPPOOLER') {

	}

	if ($user->scope == 'ADMIN') {
		// nothing
	}

	echo json_encode($profile);
}

/**
 * Updates user info of the requester
 */
function putCurrentUser() {
	global $database;
	$db = $database->getConnection();
	global $osuApi;

	$user = checkToken();

	if ($user->scope == 'PUBLIC') {
		http_response_code(401);
		echoFeedback(true, 'The scope PUBLIC has no user');
		return;
	}

	if ($user->scope == 'REGISTRATION') {
		$body = json_decode(file_get_contents('php://input'));
		if (empty($body)) {
			http_response_code(400);
			echoFeedback(true, 'No data sent');
			return;
		}
		if ($body->action == 'register') {
			$profile = $osuApi->getUser($body->osu_id);
			if (empty($profile)) {
				echoFeedback(true, 'Cannot find osu! profile');
				return;
			}
			$tiers = $database->tiers();
			$userTier = null;
			foreach ($tiers as $tier) {
				if ($profile->pp_rank >= $tier['lower_endpoint'] && $profile->pp_rank <= $tier['upper_endpoint']) {
					$userTier = $tier['id'];
				}
			}
			if (empty($userTier)) {
				http_response_code(401);
				echoFeedback(true, 'Cannot find a tier for this osu! profile');
				return;
			}
			$stmt = $db->prepare('SELECT COUNT(*) as rowcount
				FROM blacklist
				WHERE osu_id = :osu_id');
			$stmt->bindValue(':osu_id', $body->osu_id, PDO::PARAM_INT);
			$stmt->execute();
			$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
			if ($rows[0]['rowcount'] != '0') {
				echoFeedback(true, 'This osu profile is blacklisted');
				return;
			}
			$stmt = $db->prepare('UPDATE registrations
				SET osu_id = :osu_id, tier = :tier, time = :time
				WHERE id = :id');
			$stmt->bindValue(':osu_id', $body->osu_id, PDO::PARAM_INT);
			$stmt->bindValue(':tier', $userTier, PDO::PARAM_INT);
			$stmt->bindValue(':time', gmdate('Y-m-d H:i:s'), PDO::PARAM_STR);
			$stmt->bindValue(':id', $user->id, PDO::PARAM_STR);
			$stmt->execute();
			echoFeedback(false, 'Registration successfull');
			return;
		}
		if ($body->action == 'twitch') {
			$twitchApi = new TwitchApi();
			$twitchToken = $twitchApi->getAccessToken($body->code, $body->state);
			$twitchProfile = $twitchApi->getUser($twitchToken);
			if (empty($twitchProfile->_id)) {
				echoFeedback(true, 'Error while trying to access twitch profile');
				return;
			}
			$stmt = $db->prepare('INSERT INTO twitch_users (id, username, display_name, avatar)
				VALUES (:id, :username, :display_name, :avatar)');
			$stmt->bindValue(':id', $twitchProfile->_id, PDO::PARAM_INT);
			$stmt->bindValue(':username', $twitchProfile->name, PDO::PARAM_STR);
			$stmt->bindValue(':display_name', $twitchProfile->display_name, PDO::PARAM_STR);
			$stmt->bindValue(':avatar', $twitchProfile->logo, PDO::PARAM_STR);
			$stmt->execute();
			$stmt = $db->prepare('UPDATE registrations
				SET twitch_id = :twitch_id
				WHERE id = :id');
			$stmt->bindValue(':twitch_id', $twitchProfile->_id, PDO::PARAM_INT);
			$stmt->bindValue(':id', $user->id, PDO::PARAM_STR);
			$stmt->execute();
			$twitchSub = $twitchApi->getUserSubscription($twitchToken, $twitchProfile->_id);
			if (!empty($twitchSub->_id)) {
				$stmt = $db->prepare('UPDATE twitch_users
					SET sub_since = :sub_since, sub_plan = :sub_plan
					WHERE id = :id');
				$stmt->bindValue(':sub_since', $twitchSub->created_at, PDO::PARAM_STR);
				$stmt->bindValue(':sub_plan', $twitchSub->sub_plan, PDO::PARAM_STR);
				$stmt->bindValue(':id', $twitchProfile->_id, PDO::PARAM_INT);
				$stmt->execute();
			}
			echoFeedback(false, 'Twitch account linked');
			return;
		}
		if ($body->action == 'unregister') {
			$stmt = $db->prepare('UPDATE registrations
				SET osu_id = NULL, twitch_id = NULL, tier = NULL, time = NULL
				WHERE id = :id');
			$stmt->bindValue(':id', $user->id, PDO::PARAM_STR);
			$stmt->execute();
			echoFeedback(false, 'Registration deleted');
			return;
		}
	}

	if ($user->scope == 'PLAYER') {
		$body = json_decode(file_get_contents('php://input'));

		if (!empty($body->trivia) || $body->trivia == '') {
			$stmt = $db->prepare('UPDATE players
				SET trivia = :trivia
				WHERE id = :id');
			$stmt->bindValue(':trivia', $body->trivia, PDO::PARAM_STR);
			$stmt->bindValue(':id', $user->id, PDO::PARAM_STR);
			$stmt->execute();

			echoFeedback(false, 'Trivia saved');
			return;
		}
	}
}

/**
 * Outputs a list of all registered users
 */
function getRegistrations() {
	global $database;
	$db = $database->getConnection();
	global $osuApi;

	$user = checkToken();

	if ($user->scope != 'ADMIN') {
		http_response_code(401);
		echoFeedback(true, 'You need the scope ADMIN to view registrations');
		return;
	}

	$registrations = [];
	$stmt = $db->prepare('SELECT registrations.id, registrations.osu_id, registrations.twitch_id, registrations.tier, registrations.time, discord_users.username as discord_username, discord_users.discriminator, discord_users.avatar as discord_avatar, twitch_users.username as twitch_username, twitch_users.display_name, twitch_users.avatar as twitch_avatar, twitch_users.sub_since, twitch_users.sub_plan
		FROM registrations INNER JOIN discord_users ON registrations.id = discord_users.id LEFT JOIN twitch_users ON registrations.twitch_id = twitch_users.id
		WHERE registrations.osu_id IS NOT NULL
		ORDER BY registrations.time');
	$stmt->execute();
	$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
	foreach ($rows as $row) {
		$registration = new stdClass;
		$registration->discord_profile = new stdClass;
		$registration->discord_profile->id = $row['id'];
		$registration->discord_profile->username = $row['discord_username'];
		$registration->discord_profile->discriminator = $row['discriminator'];
		$registration->discord_profile->avatar = $row['discord_avatar'];
		$registration->osu_profile = $osuApi->getUser($row['osu_id']);
		if (!empty($row['twitch_id'])) {
			$registration->twitch_profile = new stdClass;
			$registration->twitch_profile->id = $row['twitch_id'];
			$registration->twitch_profile->username = $row['twitch_username'];
			$registration->twitch_profile->display_name = $row['display_name'];
			$registration->twitch_profile->avatar = $row['twitch_avatar'];
			$registration->twitch_profile->sub_since = $row['sub_since'];
			$registration->twitch_profile->sub_plan = $row['sub_plan'];
		} else {
			$registration->twitch_profile = null;
		}
		$registration->tier = $row['tier'];
		$registration->time = $row['time'];
		$registrations[] = $registration;
	}

	echo json_encode($registrations);
}

/**
 * Outputs a list off all players
 */
function getPlayers() {
	global $database;
	$db = $database->getConnection();
	global $osuApi;

	$user = checkToken();

	$stmt = $db->prepare('SELECT id
		FROM players');
	$stmt->execute();
	$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$players = [];
	foreach ($rows as $row) {
		$players[] = $database->user($row['id']);
	}

	echo json_encode($players);
}

/**
 * Gives all players the player role
 */
function putPlayers() {
	global $database;
	$db = $database->getConnection();
	global $discordApi;

	$user = checkToken();

	if ($user->scope != 'ADMIN') {
		http_response_code(401);
		echoFeedback(true, 'You need the scope ADMIN');
		return;
	}

	$stmt = $db->prepare('SELECT id
		FROM players
		WHERE role_given IS NULL AND id <> \'97808692346368000\' AND id <> \'186110237244129280\'
		ORDER BY id DESC');
	$stmt->execute();
	$players = $stmt->fetchAll(PDO::FETCH_ASSOC);
	foreach ($players as $player) {
		while (!empty($discordApi->addUserToPlayers($player['id']))) {
			sleep(20);
		}
		$stmt = $db->prepare('UPDATE players
			SET role_given = 1
			WHERE id = :id');
		$stmt->bindValue(':id', $player['id'], PDO::PARAM_INT);
		$stmt->execute();
	}

	echoFeedback(false, 'Player role given');
}

/**
 * Seeds all registrations
 */
function postPlayers() {
	global $database;
	$db = $database->getConnection();
	global $discordApi;

	$user = checkToken();

	if ($user->scope != 'ADMIN') {
		http_response_code(401);
		echoFeedback(true, 'You need the scope ADMIN for seeding');
		return;
	}

	$stmt = $db->prepare('UPDATE registrations
		SET tier = NULL');
	$stmt->execute();

	$stmt = $db->prepare('SELECT id, slots, seed_by_rank, lower_endpoint, upper_endpoint
		FROM tiers');
	$stmt->execute();
	$tiers = $stmt->fetchAll(PDO::FETCH_ASSOC);

	foreach ($tiers as $tier) {
		$stmt = $db->prepare('SELECT id
			FROM rounds
			WHERE tier = :tier AND week = 0 AND level = 0');
		$stmt->bindValue(':tier', $tier['id'], PDO::PARAM_INT);
		$stmt->execute();
		$first_round = $stmt->fetchAll(PDO::FETCH_ASSOC);

		if ($tier['seed_by_rank'] == '1') {
			$stmt = $db->prepare('SELECT registrations.id, registrations.osu_id, registrations.twitch_id
				FROM registrations INNER JOIN osu_users ON registrations.osu_id = osu_users.user_id
				WHERE osu_users.pp_rank >= :lower_endpoint AND osu_users.pp_rank <= :upper_endpoint
				ORDER BY osu_users.pp_rank
				LIMIT :count');
			$stmt->bindValue(':lower_endpoint', $tier['lower_endpoint'], PDO::PARAM_INT);
			$stmt->bindValue(':upper_endpoint', $tier['upper_endpoint'], PDO::PARAM_INT);
			$stmt->bindValue(':count', (int) $tier['slots'], PDO::PARAM_INT);
			$stmt->execute();
			$registrations = $stmt->fetchAll(PDO::FETCH_ASSOC);

			foreach ($registrations as $registration) {
				$stmt = $db->prepare('INSERT INTO players (id, osu_id, twitch_id, next_round, tier)
					VALUES (:id, :osu_id, :twitch_id, :next_round, :tier)');
				$stmt->bindValue(':id', $registration['id'], PDO::PARAM_STR);
				$stmt->bindValue(':osu_id', $registration['osu_id'], PDO::PARAM_INT);
				$stmt->bindValue(':twitch_id', $registration['twitch_id'], PDO::PARAM_INT);
				$stmt->bindValue(':next_round', $first_round[0]['id'], PDO::PARAM_INT);
				$stmt->bindValue(':tier', $tier['id'], PDO::PARAM_INT);
				$stmt->execute();

				//$discordApi->addUserToPlayers($registration['id']);
			}
		} else {
			$stmt = $db->prepare('SELECT registrations.id, registrations.osu_id, registrations.twitch_id
				FROM registrations INNER JOIN osu_users ON registrations.osu_id = osu_users.user_id INNER JOIN twitch_users ON registrations.twitch_id = twitch_users.id
				WHERE osu_users.pp_rank >= :lower_endpoint AND osu_users.pp_rank <= :upper_endpoint AND twitch_users.sub_since IS NOT NULL
				ORDER BY registrations.time ASC
				LIMIT :rowcount');
			$stmt->bindValue(':lower_endpoint', $tier['lower_endpoint'], PDO::PARAM_INT);
			$stmt->bindValue(':upper_endpoint', $tier['upper_endpoint'], PDO::PARAM_INT);
			$stmt->bindValue(':rowcount', (int) $tier['slots'], PDO::PARAM_INT);
			$stmt->execute();
			$registrations = $stmt->fetchAll(PDO::FETCH_ASSOC);
			var_dump($registrations);

			foreach ($registrations as $registration) {
				$stmt = $db->prepare('INSERT INTO players (id, osu_id, twitch_id, next_round, tier)
					VALUES (:id, :osu_id, :twitch_id, :next_round, :tier)');
				$stmt->bindValue(':id', $registration['id'], PDO::PARAM_STR);
				$stmt->bindValue(':osu_id', $registration['osu_id'], PDO::PARAM_INT);
				$stmt->bindValue(':twitch_id', $registration['twitch_id'], PDO::PARAM_INT);
				$stmt->bindValue(':next_round', $first_round[0]['id'], PDO::PARAM_INT);
				$stmt->bindValue(':tier', $tier['id'], PDO::PARAM_INT);
				$stmt->execute();

				//$discordApi->addUserToPlayers($registration['id']);
			}

			if (count($registrations) < $tier['slots']) {
				$stmt = $db->prepare('SELECT registrations.id, registrations.osu_id, registrations.twitch_id
					FROM registrations INNER JOIN osu_users ON registrations.osu_id = osu_users.user_id LEFT JOIN twitch_users ON registrations.twitch_id = twitch_users.id
					WHERE osu_users.pp_rank >= :lower_endpoint AND osu_users.pp_rank <= :upper_endpoint AND (twitch_users.sub_since IS NULL OR registrations.twitch_id IS NULL)
					ORDER BY registrations.time ASC
					LIMIT :count');
				$stmt->bindValue(':lower_endpoint', $tier['lower_endpoint'], PDO::PARAM_INT);
				$stmt->bindValue(':upper_endpoint', $tier['upper_endpoint'], PDO::PARAM_INT);
				$stmt->bindValue(':count', $tier['slots'] - count($registrations), PDO::PARAM_INT);
				$stmt->execute();
				$registrations = $stmt->fetchAll(PDO::FETCH_ASSOC);

				foreach ($registrations as $registration) {
					$stmt = $db->prepare('INSERT INTO players (id, osu_id, twitch_id, next_round, tier)
						VALUES (:id, :osu_id, :twitch_id, :next_round, :tier)');
					$stmt->bindValue(':id', $registration['id'], PDO::PARAM_STR);
					$stmt->bindValue(':osu_id', $registration['osu_id'], PDO::PARAM_INT);
					$stmt->bindValue(':twitch_id', $registration['twitch_id'], PDO::PARAM_INT);
					$stmt->bindValue(':next_round', $first_round[0]['id'], PDO::PARAM_INT);
					$stmt->bindValue(':tier', $tier['id'], PDO::PARAM_INT);
					$stmt->execute();

					$discordApi->addUserToPlayers($registration['id']);
				}
			}
		}
	}

	echoFeedback(false, 'Players seeded');
}

/**
 * Outputs a list of all tiers
 */
function getTiers() {
	global $database;
	$db = $database->getConnection();

	$user = checkToken();

	$tiers = [];
	if ($user->scope == 'REFEREE') {
		$stmt = $db->prepare('SELECT tiers.id, tiers.lower_endpoint, tiers.upper_endpoint, tiers.slots, tiers.seed_by_rank, tiers.name
			FROM tiers
			WHERE tiers.id NOT IN (
				SELECT players.tier
				FROM players
				WHERE players.id = :id AND players.next_round IS NOT NULL
			)
			ORDER BY tiers.lower_endpoint');
		$stmt->bindValue(':id', $user->id, PDO::PARAM_STR);
		$stmt->execute();
		$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
	} else {
		$stmt = $db->prepare('SELECT tiers.id, tiers.lower_endpoint, tiers.upper_endpoint, tiers.slots, tiers.seed_by_rank, tiers.name
			FROM tiers
			ORDER BY tiers.lower_endpoint');
		$stmt->execute();
		$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	foreach ($rows as $row) {
		$tier = new stdClass;
		$tier->id = $row['id'];
		$tier->lower_endpoint = $row['lower_endpoint'];
		$tier->upper_endpoint = $row['upper_endpoint'];
		$tier->slots = $row['slots'];
		$tier->seed_by_rank = $row['seed_by_rank'];
		$tier->name = $row['name'];

		$tiers[] = $tier;
	}

	echo json_encode($tiers);
}

/**
 * Creates a new tier
 */
function postTier() {
	global $database;
	$db = $database->getConnection();

	$user = checkToken();

	if ($user->scope != 'ADMIN') {
		http_response_code(401);
		echoFeedback(true, 'You need the scope ADMIN to create new tiers');
		return;
	}

	$body = json_decode(file_get_contents('php://input'));

	$tiers = $database->tiers();
	foreach ($tiers as $tier) {
		if (($body->lower_endpoint >= $tier['lower_endpoint'] && $body->lower_endpoint <= $tier['upper_endpoint']) || ($body->upper_endpoint >= $tier['lower_endpoint'] && $body->upper_endpoint <= $tier['upper_endpoint'])) {
			http_response_code(409);
			echoFeedback(true, 'Tier is conflicting with an existing tier');
			return;
		}
	}

	$stmt = $db->prepare('INSERT INTO tiers (lower_endpoint, upper_endpoint, slots, seed_by_rank, name)
		VALUES (:lower_endpoint, :upper_endpoint, :slots, :seed_by_rank, :name)');
	$stmt->bindValue(':lower_endpoint', $body->lower_endpoint, PDO::PARAM_INT);
	$stmt->bindValue(':upper_endpoint', $body->upper_endpoint, PDO::PARAM_INT);
	$stmt->bindValue(':slots', $body->slots, PDO::PARAM_INT);
	$stmt->bindValue(':seed_by_rank', $body->seed_by_rank == '1', PDO::PARAM_BOOL);
	$stmt->bindValue(':name', $body->name, PDO::PARAM_STR);
	$stmt->execute();

	$stmt = $db->prepare('SELECT id
		FROM tiers
		WHERE lower_endpoint = :lower_endpoint');
	$stmt->bindValue(':lower_endpoint', $body->lower_endpoint, PDO::PARAM_INT);
	$stmt->execute();
	$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
	$tier_id = $rows[0]['id'];
	$stmt = $db->prepare('INSERT INTO rounds (tier, week, name, level, lobby_size, amount_continue_to_upper, amount_drop_down, input_amount, total_continue_to_upper, total_drop_down, amount_lobbies)
		VALUES (:tier, :week, :name, :level, :lobby_size, :amount_continue_to_upper, :amount_drop_down, :input_amount, :total_continue_to_upper, :total_drop_down, :amount_lobbies)');
	$stmt->bindValue(':tier', $tier_id, PDO::PARAM_INT);
	$stmt->bindValue(':week', 0, PDO::PARAM_INT);
	$stmt->bindValue(':name', '', PDO::PARAM_STR);
	$stmt->bindValue(':level', 0, PDO::PARAM_INT);
	$stmt->bindValue(':lobby_size', 1, PDO::PARAM_INT);
	$stmt->bindValue(':amount_continue_to_upper', 0, PDO::PARAM_INT);
	$stmt->bindValue(':amount_drop_down', 0, PDO::PARAM_INT);
	$stmt->bindValue(':input_amount', $body->slots, PDO::PARAM_INT);
	$stmt->bindValue(':total_continue_to_upper', 0, PDO::PARAM_INT);
	$stmt->bindValue(':total_drop_down', 0, PDO::PARAM_INT);
	$stmt->bindValue(':amount_lobbies', $body->slots, PDO::PARAM_INT);
	$stmt->execute();

	echoFeedback(false, 'Tier created');
}

/**
 * Updates an existing tier identified by id
 *
 * @param integer  $_GET['tier']  Id of the tier
 */
function putTier() {
	global $database;
	$db = $database->getConnection();

	$user = checkToken();

	if ($user->scope != 'ADMIN') {
		http_response_code(401);
		echoFeedback(true, 'You need the scope ADMIN to edit tiers');
		return;
	}

	$body = json_decode(file_get_contents('php://input'));

	$tiers = $database->tiers();
	foreach ($tiers as $tier) {
		if ($_GET['tier'] != $tier['id']) {
			if (($body->lower_endpoint >= $tier['lower_endpoint'] && $body->lower_endpoint <= $tier['upper_endpoint']) || ($body->upper_endpoint >= $tier['lower_endpoint'] && $body->upper_endpoint <= $tier['upper_endpoint'])) {
				http_response_code(409);
				echoFeedback(true, 'Tier is conflicting with an existing tier');
				return;
			}
		}
	}

	$stmt = $db->prepare('SELECT COUNT(*) as rowcount
		FROM players
		WHERE tier = :tier');
	$stmt->bindValue(':tier', $_GET['tier'], PDO::PARAM_INT);
	$stmt->execute();
	$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
	if ($rows[0]['rowcount'] != '0') {
		echoFeedback(true, 'There are existing players for this tier');
		return;
	}

	$stmt = $db->prepare('UPDATE tiers
		SET lower_endpoint = :lower_endpoint, upper_endpoint = :upper_endpoint, slots = :slots, seed_by_rank = :seed_by_rank, name = :name
		WHERE id = :id');
	$stmt->bindValue(':lower_endpoint', $body->lower_endpoint, PDO::PARAM_INT);
	$stmt->bindValue(':upper_endpoint', $body->upper_endpoint, PDO::PARAM_INT);
	$stmt->bindValue(':slots', $body->slots, PDO::PARAM_INT);
	$stmt->bindValue(':seed_by_rank', $body->seed_by_rank == '1', PDO::PARAM_BOOL);
	$stmt->bindValue(':name', $body->name, PDO::PARAM_STR);
	$stmt->bindValue(':id', $_GET['tier'], PDO::PARAM_INT);
	$stmt->execute();

	$stmt = $db->prepare('DELETE lobby_slots, lobbies, mappool_slot_history, mappool_slots, mappools, round
		FROM tiers LEFT JOIN rounds ON tiers.id = rounds.tier LEFT JOIN lobbies ON rounds.id = lobbies.round LEFT JOIN lobby_slots ON lobby_slots.lobby = lobbies.id LEFT JOIN mappools ON mappools.round = rounds.id LEFT JOIN mappool_slots ON mappool_slots.mappool = mappools.id LEFT JOIN mappool_slot_history ON mappool_slot_history.mappool_slot = mappool_slots.id
		WHERE tiers.id = :id');
	$stmt->bindValue(':id', $_GET['tier'], PDO::PARAM_INT);
	$stmt->execute();

	$stmt = $db->prepare('SELECT id
		FROM tiers
		WHERE lower_endpoint = :lower_endpoint');
	$stmt->bindValue(':lower_endpoint', $_GET['lowerEndpoint'], PDO::PARAM_INT);
	$stmt->execute();
	$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
	$tier_id = $rows[0]['id'];
	$stmt = $db->prepare('INSERT INTO rounds (tier, week, name, level, lobby_size, amount_continue_to_upper, amount_drop_down, input_amount, total_continue_to_upper, total_drop_down, amount_lobbies)
		VALUES (:tier, :week, :name, :level, :lobby_size, :amount_continue_to_upper, :amount_drop_down, :input_amount, :total_continue_to_upper, :total_drop_down, :amount_lobbies)');
	$stmt->bindValue(':tier', $_GET['tier'], PDO::PARAM_INT);
	$stmt->bindValue(':week', 0, PDO::PARAM_INT);
	$stmt->bindValue(':name', '', PDO::PARAM_STR);
	$stmt->bindValue(':level', 0, PDO::PARAM_INT);
	$stmt->bindValue(':lobby_size', 1, PDO::PARAM_INT);
	$stmt->bindValue(':amount_continue_to_upper', 0, PDO::PARAM_INT);
	$stmt->bindValue(':amount_drop_down', 0, PDO::PARAM_INT);
	$stmt->bindValue(':input_amount', $body->slots, PDO::PARAM_INT);
	$stmt->bindValue(':total_continue_to_upper', 0, PDO::PARAM_INT);
	$stmt->bindValue(':total_drop_down', 0, PDO::PARAM_INT);
	$stmt->bindValue(':amount_lobbies', $body->slots, PDO::PARAM_INT);
	$stmt->execute();

	echoFeedback(false, 'Tier changed');
}

/**
 * Deletes an existing tier identified by id
 *
 * @param integer  $_GET['tier']  Id of the tier
 */
function deleteTier() {
	global $database;
	$db = $database->getConnection();

	$user = checkToken();

	if ($user->scope != 'ADMIN') {
		http_response_code(401);
		echoFeedback(true, 'You need the scope ADMIN to edit tiers');
		return;
	}

	$stmt = $db->prepare('SELECT COUNT(*) as rowcount
		FROM players
		WHERE tier = :tier');
	$stmt->bindValue(':tier', $_GET['tier'], PDO::PARAM_INT);
	$stmt->execute();
	$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
	if ($rows[0]['rowcount'] != '0') {
		echoFeedback(true, 'There are existing players for this tier');
		return;
	}

	$stmt = $db->prepare('DELETE FROM tiers
		WHERE id = :id');
	$stmt->bindValue(':id', $_GET['tier'], PDO::PARAM_INT);
	$stmt->execute();

	echoFeedback(false, 'Tier deleted');
}

/**
 * Outputs all rounds of a tier identified by id
 *
 * @param integer  $_GET['tier']  Id of the tier
 */
function getRounds() {
	global $database;
	$db = $database->getConnection();

	$user = checkToken();

	if ($user->scope == 'ADMIN') {
		$stmt = $db->prepare('SELECT rounds.id, week, name, level, lobby_size, amount_continue_to_upper, amount_drop_down, input_amount, total_continue_to_upper, total_drop_down, amount_lobbies, time_from, time_to, time_from_2, time_to_2, public_release_time, closed, copy_mappool_from_round, COUNT(lobbies.id) as amount_active_lobbies
			FROM rounds LEFT JOIN lobbies ON rounds.id = lobbies.round
			WHERE tier = :tier
			GROUP BY rounds.id, week, name, level, lobby_size, amount_continue_to_upper, amount_drop_down, input_amount, total_continue_to_upper, total_drop_down, amount_lobbies, closed, copy_mappool_from_round
			ORDER BY week, level');
		$stmt->bindValue(':tier', $_GET['tier'], PDO::PARAM_INT);
		$stmt->execute();
		echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
		return;
	}

	if ($user->scope == 'MAPPOOLER' || $user->scope == 'REFEREE' || $user->scope == 'PUBLIC' || $user->scope == 'PLAYER') {
		$stmt = $db->prepare('SELECT rounds.id, week, name, level, lobby_size, amount_continue_to_upper, amount_drop_down, input_amount, total_continue_to_upper, total_drop_down, amount_lobbies, time_from, time_to, time_from_2, time_to_2, public_release_time, closed, copy_mappool_from_round, COUNT(lobbies.id) as amount_active_lobbies
			FROM rounds LEFT JOIN lobbies ON rounds.id = lobbies.round
			WHERE tier = :tier AND input_amount <> 0 AND input_amount <> total_continue_to_upper AND input_amount <> total_drop_down
			GROUP BY rounds.id, week, name, level, lobby_size, amount_continue_to_upper, amount_drop_down, input_amount, total_continue_to_upper, total_drop_down, amount_lobbies, closed, copy_mappool_from_round
			ORDER BY week, level');
		$stmt->bindValue(':tier', $_GET['tier'], PDO::PARAM_INT);
		$stmt->execute();
		echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
		return;
	}

	http_response_code(401);
	echoFeedback(true, 'You need the scope ADMIN to view rounds');
}

/**
 * Updates the rounds of a tier identified by id
 *
 * @param integer  $_GET['tier']  Id of the tier
 */
function putRounds() {
	global $database;
	$db = $database->getConnection();

	$user = checkToken();

	if ($user->scope != 'ADMIN') {
		http_response_code(401);
		echoFeedback(true, 'You need the scope ADMIN to edit rounds');
		return;
	}

	$body = json_decode(file_get_contents('php://input'));

	$stmt = $db->prepare('SELECT id, week, level, name
		FROM rounds
		WHERE tier = :tier');
	$stmt->bindValue(':tier', $_GET['tier'], PDO::PARAM_INT);
	$stmt->execute();
	$rounds = $stmt->fetchAll(PDO::FETCH_ASSOC);
	foreach ($rounds as $round) {
		$found = false;
		for ($column = 0; $column < count($body); $column++) {
			for ($row = 0; $row < count($body[$column]->levels); $row++) {
				if ($round['week'] == $column && $round['level'] == $row) {
					$found = true;
				}
			}
		}
		if (!$found) {
			$stmt = $db->prepare('SELECT COUNT(*) as rowcount
				FROM lobbies
				WHERE round = :round');
			$stmt->bindValue(':round', $round['id'], PDO::PARAM_INT);
			$stmt->execute();
			$count = $stmt->fetchAll(PDO::FETCH_ASSOC);
			if ($count[0]['rowcount'] != 0) {
				echoFeedback(true, 'Cannot delete round ' . $round['name'] . ' because it has active lobbies');
				return;
			}
		}
	}

	for ($column = 0; $column < count($body); $column++) {
		for ($row = 0; $row < count($body[$column]->levels); $row++) {
			$stmt = $db->prepare('SELECT COUNT(*) as rowcount
				FROM rounds
				WHERE tier = :tier AND week = :week AND level = :level');
			$stmt->bindValue(':tier', $_GET['tier'], PDO::PARAM_INT);
			$stmt->bindValue(':week', $column, PDO::PARAM_INT);
			$stmt->bindValue(':level', $row, PDO::PARAM_INT);
			$stmt->execute();
			$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
			if ($rows[0]['rowcount'] != 0) {
				$stmt = $db->prepare('SELECT COUNT(*) as rowcount
					FROM lobbies INNER JOIN rounds ON lobbies.round = rounds.id
					WHERE rounds.tier = :tier AND rounds.week = :week AND rounds.level = :level');
				$stmt->bindValue(':tier', $_GET['tier'], PDO::PARAM_INT);
				$stmt->bindValue(':week', $column, PDO::PARAM_INT);
				$stmt->bindValue(':level', $row, PDO::PARAM_INT);
				$stmt->execute();
				$count = $stmt->fetchAll(PDO::FETCH_ASSOC);
				if ($count[0]['rowcount'] != 0) {
					$stmt = $db->prepare('SELECT lobby_size
						FROM rounds
						WHERE tier = :tier AND week = :week AND level = :level');
					$stmt->bindValue(':tier', $_GET['tier'], PDO::PARAM_INT);
					$stmt->bindValue(':week', $column, PDO::PARAM_INT);
					$stmt->bindValue(':level', $row, PDO::PARAM_INT);
					$stmt->execute();
					$round = $stmt->fetchAll(PDO::FETCH_ASSOC);
					if ($round[0]['lobby_size'] != $body[$column]->levels[$row]->lobby_size) {
						echoFeedback(true, 'Cannot change lobby size of round ' . $body[$column]->levels[$row]->name . ' because it has active lobbies');
						return;
					}
				}
			}
		}
	}

	$stmt = $db->prepare('SELECT id, week, level
		FROM rounds
		WHERE tier = :tier');
	$stmt->bindValue(':tier', $_GET['tier'], PDO::PARAM_INT);
	$stmt->execute();
	$rounds = $stmt->fetchAll(PDO::FETCH_ASSOC);
	foreach ($rounds as $round) {
		$found = false;
		for ($column = 0; $column < count($body); $column++) {
			for ($row = 0; $row < count($body[$column]->levels); $row++) {
				if ($round['week'] == $column && $round['level'] == $row) {
					$found = true;
				}
			}
		}
		if (!$found) {
			$stmt = $db->prepare('DELETE FROM rounds
				WHERE id = :id');
			$stmt->bindValue(':id', $round['id'], PDO::PARAM_INT);
			$stmt->execute();
		}
	}

	for ($column = 0; $column < count($body); $column++) {
		for ($row = 0; $row < count($body[$column]->levels); $row++) {
			$stmt = $db->prepare('SELECT COUNT(*) as rowcount
				FROM rounds
				WHERE tier = :tier AND week = :week AND level = :level');
			$stmt->bindValue(':tier', $_GET['tier'], PDO::PARAM_INT);
			$stmt->bindValue(':week', $column, PDO::PARAM_INT);
			$stmt->bindValue(':level', $row, PDO::PARAM_INT);
			$stmt->execute();
			$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
			if ($rows[0]['rowcount'] == 0) {
				$stmt = $db->prepare('INSERT INTO rounds (tier, week, name, level, lobby_size, amount_continue_to_upper, amount_drop_down, input_amount, total_continue_to_upper, total_drop_down, amount_lobbies, time_from, time_to, time_from_2, time_to_2, public_release_time)
					VALUES (:tier, :week, :name, :level, :lobby_size, :amount_continue_to_upper, :amount_drop_down, :input_amount, :total_continue_to_upper, :total_drop_down, :amount_lobbies, :time_from, :time_to, :time_from_2, :time_to_2, :public_release_time)');
				$stmt->bindValue(':tier', $_GET['tier'], PDO::PARAM_INT);
				$stmt->bindValue(':week', $column, PDO::PARAM_INT);
				$stmt->bindValue(':name', $body[$column]->levels[$row]->name, PDO::PARAM_STR);
				$stmt->bindValue(':level', $row, PDO::PARAM_INT);
				$stmt->bindValue(':lobby_size', $body[$column]->levels[$row]->lobby_size, PDO::PARAM_INT);
				$stmt->bindValue(':amount_continue_to_upper', $body[$column]->levels[$row]->amount_continue_to_upper, PDO::PARAM_INT);
				$stmt->bindValue(':amount_drop_down', $body[$column]->levels[$row]->amount_drop_down, PDO::PARAM_INT);
				$stmt->bindValue(':input_amount', $body[$column]->levels[$row]->input_amount, PDO::PARAM_INT);
				$stmt->bindValue(':total_continue_to_upper', $body[$column]->levels[$row]->total_continue_to_upper, PDO::PARAM_INT);
				$stmt->bindValue(':total_drop_down', $body[$column]->levels[$row]->total_drop_down, PDO::PARAM_INT);
				$stmt->bindValue(':amount_lobbies', $body[$column]->levels[$row]->amount_lobbies, PDO::PARAM_INT);
				$stmt->bindValue(':time_from', $body[$column]->levels[$row]->time_from, PDO::PARAM_STR);
				$stmt->bindValue(':time_to', $body[$column]->levels[$row]->time_to, PDO::PARAM_STR);
				$stmt->bindValue(':time_from_2', $body[$column]->levels[$row]->time_from_2, PDO::PARAM_STR);
				$stmt->bindValue(':time_to_2', $body[$column]->levels[$row]->time_to_2, PDO::PARAM_STR);
				$stmt->bindValue(':public_release_time', $body[$column]->levels[$row]->public_release_time, PDO::PARAM_STR);
				$stmt->execute();
			} else {
				$stmt = $db->prepare('UPDATE rounds
					SET name = :name, lobby_size = :lobby_size, amount_continue_to_upper = :amount_continue_to_upper, amount_drop_down = :amount_drop_down, input_amount = :input_amount, total_continue_to_upper = :total_continue_to_upper, total_drop_down = :total_drop_down, amount_lobbies = :amount_lobbies, time_from = :time_from, time_to = :time_to, time_from_2 = :time_from_2, time_to_2 = :time_to_2, public_release_time = :public_release_time
					WHERE tier = :tier AND week = :week AND level = :level');
				$stmt->bindValue(':name', $body[$column]->levels[$row]->name, PDO::PARAM_STR);
				$stmt->bindValue(':lobby_size', $body[$column]->levels[$row]->lobby_size, PDO::PARAM_INT);
				$stmt->bindValue(':amount_continue_to_upper', $body[$column]->levels[$row]->amount_continue_to_upper, PDO::PARAM_INT);
				$stmt->bindValue(':amount_drop_down', $body[$column]->levels[$row]->amount_drop_down, PDO::PARAM_INT);
				$stmt->bindValue(':input_amount', $body[$column]->levels[$row]->input_amount, PDO::PARAM_INT);
				$stmt->bindValue(':total_continue_to_upper', $body[$column]->levels[$row]->total_continue_to_upper, PDO::PARAM_INT);
				$stmt->bindValue(':total_drop_down', $body[$column]->levels[$row]->total_drop_down, PDO::PARAM_INT);
				$stmt->bindValue(':amount_lobbies', $body[$column]->levels[$row]->amount_lobbies, PDO::PARAM_INT);
				$stmt->bindValue(':time_from', $body[$column]->levels[$row]->time_from, PDO::PARAM_STR);
				$stmt->bindValue(':time_to', $body[$column]->levels[$row]->time_to, PDO::PARAM_STR);
				$stmt->bindValue(':time_from_2', $body[$column]->levels[$row]->time_from_2, PDO::PARAM_STR);
				$stmt->bindValue(':time_to_2', $body[$column]->levels[$row]->time_to_2, PDO::PARAM_STR);
				$stmt->bindValue(':public_release_time', $body[$column]->levels[$row]->public_release_time, PDO::PARAM_STR);
				$stmt->bindValue(':tier', $_GET['tier'], PDO::PARAM_INT);
				$stmt->bindValue(':week', $column, PDO::PARAM_INT);
				$stmt->bindValue(':level', $row, PDO::PARAM_INT);
				$stmt->execute();
			}
		}
	}

	echoFeedback(false, 'Bracket setup saved');
}

function putRound() {
	global $database;
	$db = $database->getConnection();

	$user = checkToken();

	if ($user->scope != 'MAPPOOLER') {
		echoFeedback(true, 'You need the role MAPPOOLER');
		return;
	}

	$body = json_decode(file_get_contents('php://input'));

	$stmt = $db->prepare('UPDATE rounds
		SET copy_mappool_from_round = :copy_mappool_from_round
		WHERE id = :id');
	$stmt->bindValue(':copy_mappool_from_round', $body->copy_mappool_from_round, PDO::PARAM_INT);
	$stmt->bindValue(':id', $_GET['round'], PDO::PARAM_INT);
	$stmt->execute();

	echoFeedback(false, 'Round saved');
}

/**
 * Outputs all lobbies of a round identified by id
 *
 * @param integer  $_GET['round']  Id of the round
 */
function getLobbies() {
	global $database;
	$db = $database->getConnection();
	global $osuApi;

	$user = checkToken();

	if ($user->scope == 'PUBLIC') {
		// select lobbies
		$stmt = $db->prepare('SELECT id, round, match_id, match_time
			FROM lobbies
			WHERE round = :round AND match_id IS NOT NULL');
		$stmt->bindValue(':round', $_GET['round'], PDO::PARAM_INT);
		$stmt->execute();
		$lobbies = $stmt->fetchAll(PDO::FETCH_ASSOC);

		// get lobby slots for each lobby
		foreach ($lobbies as &$lobby) {
			$stmt = $db->prepare('SELECT id, slot_number, user_id
				FROM lobby_slots
				WHERe lobby = :lobby
				ORDER BY slot_number');
			$stmt->bindValue(':lobby', $lobby['id'], PDO::PARAM_INT);
			$stmt->execute();
			$lobby['slots'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

			// get user data for each filled slot
			foreach ($lobby['slots'] as &$slot) {
				if ($slot['user_id']) {
					$slot['user'] = $database->user($slot['user_id']);
				}
			}
		}
	} else {
		// select lobbies
		$stmt = $db->prepare('SELECT id, round, match_id, match_time
			FROM lobbies
			WHERE round = :round');
		$stmt->bindValue(':round', $_GET['round'], PDO::PARAM_INT);
		$stmt->execute();
		$lobbies = $stmt->fetchAll(PDO::FETCH_ASSOC);

		// get lobby slots for each lobby
		foreach ($lobbies as &$lobby) {
			$stmt = $db->prepare('SELECT id, slot_number, user_id
				FROM lobby_slots
				WHERe lobby = :lobby
				ORDER BY slot_number');
			$stmt->bindValue(':lobby', $lobby['id'], PDO::PARAM_INT);
			$stmt->execute();
			$lobby['slots'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

			// get user data for each filled slot
			foreach ($lobby['slots'] as &$slot) {
				if ($slot['user_id']) {
					$slot['user'] = $database->user($slot['user_id']);
				}
			}
		}
	}

	echo json_encode($lobbies);
}

/**
 * Finalizes the lobbies of a round identified by id
 *
 * @param integer  $_GET['round']  Id of the round
 */
function putLobbies() {
	global $database;
	$db = $database->getConnection();

	$user = checkToken();

	if ($user->scope != 'ADMIN') {
		http_response_code(401);
		echoFeedback(true, 'You need the scope ADMIN to finalize lobbies');
		return;
	}

	$stmt = $db->prepare('SELECT closed
		FROM rounds
		WHERE id = :id');
	$stmt->bindValue(':id', $_GET['round'], PDO::PARAM_INT);
	$stmt->execute();
	$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

	if ($rows[0]['closed'] == '1') {
		echoFeedback(true, 'This round is already finalized');
		return;
	}

	$stmt = $db->prepare('SELECT COUNT(*) as rowcount
		FROM lobby_slots INNER JOIN lobbies ON lobby_slots.lobby = lobbies.id
		WHERE lobbies.round = :round AND user_id IS NOT NULL AND lobby_slots.continue_to_upper IS NULL');
	$stmt->bindValue(':round', $_GET['round'], PDO::PARAM_INT);
	$stmt->execute();
	$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

	if ($rows[0]['rowcount'] != '0') {
		echoFeedback(true, 'Not all lobbies in this round are finished');
		return;
	}

	$stmt = $db->prepare('SELECT tier, week, level
		FROM rounds
		WHERE id = :id');
	$stmt->bindValue(':id', $_GET['round'], PDO::PARAM_INT);
	$stmt->execute();
	$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
	$tier = $rows[0]['tier'];
	$next_upper_week = (int) $rows[0]['week'] + 1;
	$next_upper_level = (int) $rows[0]['level'];
	$next_lower_week = (int) $rows[0]['week'];
	$next_lower_level = (int) $rows[0]['level'] + 1;

	while (true) {
		$stmt = $db->prepare('SELECT id, input_amount, total_continue_to_upper, total_drop_down
			FROM rounds
			WHERE tier = :tier AND week = :week AND level = :level');
		$stmt->bindValue(':tier', $tier, PDO::PARAM_INT);
		$stmt->bindValue(':week', $next_upper_week, PDO::PARAM_INT);
		$stmt->bindValue(':level', $next_upper_level, PDO::PARAM_INT);
		$stmt->execute();
		$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
		if (empty($rows[0])) {
			break;
		}
		if ($rows[0]['input_amount'] == $rows[0]['total_continue_to_upper']) {
			$next_upper_week++;
		} elseif ($rows[0]['input_amount'] == $rows[0]['total_drop_down']) {
			$next_upper_level++;
		} else {
			$next_upper_id = $rows[0]['id'];
			break;
		}
	}

	while (true) {
		$stmt = $db->prepare('SELECT id, input_amount, total_continue_to_upper, total_drop_down
			FROM rounds
			WHERE tier = :tier AND week = :week AND level = :level');
		$stmt->bindValue(':tier', $tier, PDO::PARAM_INT);
		$stmt->bindValue(':week', $next_lower_week, PDO::PARAM_INT);
		$stmt->bindValue(':level', $next_lower_level, PDO::PARAM_INT);
		$stmt->execute();
		$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
		if (empty($rows[0])) {
			break;
		}
		if ($rows[0]['input_amount'] == $rows[0]['total_continue_to_upper']) {
			$next_lower_week++;
		} elseif ($rows[0]['input_amount'] == $rows[0]['total_drop_down']) {
			$next_lower_level++;
		} else {
			$next_lower_id = $rows[0]['id'];
			break;
		}
	}

	$stmt = $db->prepare('SELECT user_id, continue_to_upper, drop_down
		FROM lobby_slots INNER JOIN lobbies ON lobby_slots.lobby = lobbies.id
		WHERE lobbies.round = :round AND user_id IS NOT NULL');
	$stmt->bindValue(':round', $_GET['round'], PDO::PARAM_INT);
	$stmt->execute();
	$players = $stmt->fetchAll(PDO::FETCH_ASSOC);

	foreach ($players as $player) {
		if ($player['continue_to_upper'] == '1') {
			$stmt = $db->prepare('UPDATE players
				SET next_round = :next_round
				WHERE id = :id');
			$stmt->bindValue(':next_round', $next_upper_id, PDO::PARAM_INT);
			$stmt->bindValue(':id', $player['user_id'], PDO::PARAM_STR);
			$stmt->execute();
		} elseif ($player['drop_down'] == '1') {
			$stmt = $db->prepare('UPDATE players
				SET next_round = :next_round
				WHERE id = :id');
			$stmt->bindValue(':next_round', $next_lower_id, PDO::PARAM_INT);
			$stmt->bindValue(':id', $player['user_id'], PDO::PARAM_STR);
			$stmt->execute();
		} else {
			$stmt = $db->prepare('UPDATE players
				SET next_round = NULL
				WHERE id = :id');
			$stmt->bindValue(':id', $player['user_id'], PDO::PARAM_STR);
			$stmt->execute();
		}
	}

	$stmt = $db->prepare('UPDATE rounds
		SET closed = 1
		WHERE id = :id');
	$stmt->bindValue(':id', $_GET['round'], PDO::PARAM_INT);
	$stmt->execute();

	echoFeedback(false, 'Lobbies finalized');
}

/**
 * Creates lobbies for a round identified by id
 *
 * @param integer  $_GET['round']  Id of the round
 */
function postLobbies() {
	global $database;
	$db = $database->getConnection();

	$user = checkToken();

	if ($user->scope != 'ADMIN') {
		http_response_code(401);
		echoFeedback(true, 'You need the scope ADMIN to create lobbies');
		return;
	}

	// get week parameters
	$stmt = $db->prepare('SELECT amount_lobbies, lobby_size
		FROM rounds
		WHERE id = :id');
	$stmt->bindValue(':id', $_GET['round'], PDO::PARAM_INT);
	$stmt->execute();
	$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
	$amountLobbies = $rows[0]['amount_lobbies'];
	$lobbySize = $rows[0]['lobby_size'];

	// check for existing lobbies
	$stmt = $db->prepare('SELECT COUNT(*) as rowcount
		FROM lobbies
		WHERE round = :round');
	$stmt->bindValue(':round', $_GET['round'], PDO::PARAM_INT);
	$stmt->execute();
	$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
	if ($rows[0]['rowcount'] != '0') {
		echoFeedback(true, 'There are already existing lobbies for this round');
		return;
	}

	// create lobbies
	for ($i = 0; $i < $amountLobbies; $i++) {
		$stmt = $db->prepare('INSERT INTO lobbies (round)
			VALUES (:round)');
		$stmt->bindValue(':round', $_GET['round'], PDO::PARAM_INT);
		$stmt->execute();
	}

	// create lobby slots for each lobby
	$stmt = $db->prepare('SELECT id
		FROM lobbies
		WHERE round = :round');
	$stmt->bindValue(':round', $_GET['round'], PDO::PARAM_INT);
	$stmt->execute();
	$lobbies = $stmt->fetchAll(PDO::FETCH_ASSOC);
	foreach ($lobbies as $lobby) {
		for ($i = 0; $i < $lobbySize; $i++) {
			$stmt = $db->prepare('INSERT INTO lobby_slots (lobby, slot_number)
				VALUES (:lobby, :slot_number)');
			$stmt->bindValue(':lobby', $lobby['id'], PDO::PARAM_INT);
			$stmt->bindValue(':slot_number', $i, PDO::PARAM_INT);
			$stmt->execute();
		}
	}

	echoFeedback(false, 'Lobbies created');
}

/**
 * Delete all lobbies of a round identified by id
 *
 * @param integer  $_GET['round']  Id of the round
 */
function deleteLobbies() {
	global $database;
	$db = $database->getConnection();

	$user = checkToken();

	if ($user->scope != 'ADMIN') {
		http_response_code(401);
		echoFeedback(true, 'You need the scope ADMIN to delete lobbies');
		return;
	}

	$stmt = $db->prepare('DELETE lobby_slots, lobbies
		FROM lobbies INNER JOIN lobby_slots ON lobbies.id = lobby_slots.lobby
		WHERE lobbies.round = :round');
	$stmt->bindValue(':round', $_GET['round'], PDO::PARAM_INT);
	$stmt->execute();

	echoFeedback(false, 'Lobbies deleted');
}

/**
 * Outputs a lobby identified by id
 *
 * @param integer  $_GET['lobby']  Id of the lobby
 */
function getLobby() {
	global $database;
	$db = $database->getConnection();
	global $osuApi;

	$user = checkToken();

	if ($user->scope == 'REFEREE') {
		$stmt = $db->prepare('SELECT lobbies.match_id, lobbies.match_time, lobbies.comment, rounds.amount_continue_to_upper, rounds.amount_drop_down, rounds.lobby_size, rounds.closed
			FROM lobbies INNER JOIN rounds ON lobbies.round = rounds.id
			WHERE lobbies.id = :id');
		$stmt->bindValue(':id', $_GET['lobby'], PDO::PARAM_INT);
		$stmt->execute();
		$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
		$lobby = new stdClass;
		$lobby->id = $_GET['lobby'];
		$lobby->match_id = $rows[0]['match_id'];
		$lobby->closed = $rows[0]['closed'];
		$lobby->match_time = $rows[0]['match_time'];
		$lobby->lobby_size = $rows[0]['lobby_size'];
		$lobby->amount_continue_to_upper = $rows[0]['amount_continue_to_upper'];
		$lobby->amount_drop_down = $rows[0]['amount_drop_down'];
		$lobby->amount_eliminated = $lobby->lobby_size - $lobby->amount_continue_to_upper - $lobby->amount_drop_down;
		$lobby->comment = $rows[0]['comment'];
		$lobby->slots = [];
		$stmt = $db->prepare('SELECT id, slot_number, user_id, continue_to_upper, drop_down
			FROM lobby_slots
			WHERE lobby = :lobby
			ORDER BY slot_number ASC');
		$stmt->bindValue(':lobby', $_GET['lobby'], PDO::PARAM_INT);
		$stmt->execute();
		$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
		foreach ($rows as $row) {
			$slot = new stdClass;
			$slot->id = $row['id'];
			$slot->slot_number = $row['slot_number'];
			$slot->continue_to_upper = $row['continue_to_upper'];
			$slot->drop_down = $row['drop_down'];
			$slot->user = $database->user($row['user_id']);
			$lobby->slots[] = $slot;
		}

		echo json_encode($lobby);
		return;
	}

	if ($user->scope == 'PLAYER' || $user->scope == 'PUBLIC') {
		$stmt = $db->prepare('SELECT rounds.public_release_time
			FROM rounds INNER JOIN lobbies ON rounds.id = lobbies.round
			WHERE lobbies.id = :id');
		$stmt->bindValue(':id', $_GET['lobby'], PDO::PARAM_INT);
		$stmt->execute();
		$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
		$release_time = new DateTime($rows[0]['public_release_time']);
		$now = new DateTime();
		if ($release_time > $now) {
			echoFeedback(true, 'This lobby is not released for public');
			return;
		}

		$stmt = $db->prepare('SELECT lobbies.match_id, lobbies.match_time, lobbies.comment, rounds.amount_continue_to_upper, rounds.amount_drop_down, rounds.lobby_size, rounds.id
			FROM lobbies INNER JOIN rounds ON lobbies.round = rounds.id
			WHERE lobbies.id = :id');
		$stmt->bindValue(':id', $_GET['lobby'], PDO::PARAM_INT);
		$stmt->execute();
		$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
		$lobby = new stdClass;
		$lobby->id = $_GET['lobby'];
		$lobby->round = $rows[0]['id'];
		$lobby->match_id = $rows[0]['match_id'];
		$lobby->closed = $rows[0]['closed'];
		$lobby->match_time = $rows[0]['match_time'];
		$lobby->lobby_size = $rows[0]['lobby_size'];
		$lobby->amount_continue_to_upper = $rows[0]['amount_continue_to_upper'];
		$lobby->amount_drop_down = $rows[0]['amount_drop_down'];
		$lobby->amount_eliminated = $lobby->lobby_size - $lobby->amount_continue_to_upper - $lobby->amount_drop_down;
		$lobby->comment = $rows[0]['comment'];
		$lobby->slots = [];
		$stmt = $db->prepare('SELECT id, slot_number, user_id, continue_to_upper, drop_down
			FROM lobby_slots
			WHERE lobby = :lobby
			ORDER BY slot_number ASC');
		$stmt->bindValue(':lobby', $_GET['lobby'], PDO::PARAM_INT);
		$stmt->execute();
		$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
		foreach ($rows as $row) {
			$slot = new stdClass;
			$slot->id = $row['id'];
			$slot->slot_number = $row['slot_number'];
			$slot->continue_to_upper = $row['continue_to_upper'];
			$slot->drop_down = $row['drop_down'];
			$slot->user = $database->user($row['user_id']);
			$lobby->slots[] = $slot;
		}

		echo json_encode($lobby);
		return;
	}

	echoFeedback(true, 'You need the scope REFEREE, PLAYER or PUBLIC to view a lobby');
}

/**
 * Updates the lobby identified by id
 *
 * @param integer  $_GET['lobby']  Id of the lobby
 */
function putLobby() {
	global $database;
	$db = $database->getConnection();

	$user = checkToken();

	$body = json_decode(file_get_contents('php://input'));

	if ($user->scope == 'ADMIN') {
		if ($body->action == 'play_time') {
			$stmt = $db->prepare('UPDATE lobbies
				SET match_time = :match_time
				WHERE id = :id');
			$stmt->bindValue(':match_time', $body->time, PDO::PARAM_STR);
			$stmt->bindValue(':id', $_GET['lobby'], PDO::PARAM_INT);
			$stmt->execute();

			echoFeedback(false, 'Lobby updated');
			return;
		}
	}

	if ($user->scope == 'REFEREE') {
		if ($body->action == 'match_id') {
			$stmt = $db->prepare('UPDATE lobbies
				SET match_id = :match_id
				WHERE id = :id');
			$stmt->bindValue(':match_id', $body->id, PDO::PARAM_INT);
			$stmt->bindValue(':id', $_GET['lobby'], PDO::PARAM_INT);
			$stmt->execute();

			echoFeedback(false, 'Lobby updated');
			return;
		}
		if ($body->action == 'continues') {
			$stmt = $db->prepare('SELECT rounds.tier, rounds.week, rounds.level
				FROM lobbies INNER JOIN rounds ON lobbies.round = rounds.id
				WHERE lobbies.id = :id');
			$stmt->bindValue(':id', $_GET['lobby'], PDO::PARAM_INT);
			$stmt->execute();
			$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
			$tier = $rows[0]['tier'];
			$next_upper_week = (int) $rows[0]['week'] + 1;
			$next_upper_level = (int) $rows[0]['level'];
			$next_lower_week = (int) $rows[0]['week'];
			$next_lower_level = (int) $rows[0]['level'] + 1;

			while (true) {
				$stmt = $db->prepare('SELECT id, input_amount, total_continue_to_upper, total_drop_down
					FROM rounds
					WHERE tier = :tier AND week = :week AND level = :level');
				$stmt->bindValue(':tier', $tier, PDO::PARAM_INT);
				$stmt->bindValue(':week', $next_upper_week, PDO::PARAM_INT);
				$stmt->bindValue(':level', $next_upper_level, PDO::PARAM_INT);
				$stmt->execute();
				$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
				if (empty($rows[0])) {
					break;
				}
				if ($rows[0]['input_amount'] == $rows[0]['total_continue_to_upper']) {
					$next_upper_week++;
				} elseif ($rows[0]['input_amount'] == $rows[0]['total_drop_down']) {
					$next_upper_level++;
				} else {
					$next_upper_id = $rows[0]['id'];
					break;
				}
			}

			while (true) {
				$stmt = $db->prepare('SELECT id, input_amount, total_continue_to_upper, total_drop_down
					FROM rounds
					WHERE tier = :tier AND week = :week AND level = :level');
				$stmt->bindValue(':tier', $tier, PDO::PARAM_INT);
				$stmt->bindValue(':week', $next_lower_week, PDO::PARAM_INT);
				$stmt->bindValue(':level', $next_lower_level, PDO::PARAM_INT);
				$stmt->execute();
				$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
				if (empty($rows[0])) {
					break;
				}
				if ($rows[0]['input_amount'] == $rows[0]['total_continue_to_upper']) {
					$next_lower_week++;
				} elseif ($rows[0]['input_amount'] == $rows[0]['total_drop_down']) {
					$next_lower_level++;
				} else {
					$next_lower_id = $rows[0]['id'];
					break;
				}
			}

			foreach ($body->continues as $player) {
				switch ($player->continues) {
					case 'Continues': $continues = 1; $drops_down = 0; break;
					case 'Drops down': $continues = 0; $drops_down = 1; break;
					case 'Eliminated': $continues = 0; $drops_down = 0; break;
				}

				$stmt = $db->prepare('SELECT current_lobby
					FROM players
					WHERE id = :id');
				$stmt->bindValue(':id', $player->user, PDO::PARAM_STR);
				$stmt->execute();
				$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

				if (!empty($rows[0]['current_lobby']) && $rows[0]['current_lobby'] == $_GET['lobby']) {
					if ($continues == 1) {
						$stmt = $db->prepare('UPDATE players
							SET next_round = :next_round
							WHERE id = :id');
						$stmt->bindValue(':next_round', $next_upper_id, PDO::PARAM_INT);
						$stmt->bindValue(':id', $player->user, PDO::PARAM_STR);
						$stmt->execute();
					} elseif ($drops_down == 1) {
						$stmt = $db->prepare('UPDATE players
							SET next_round = :next_round
							WHERE id = :id');
						$stmt->bindValue(':next_round', $next_lower_id, PDO::PARAM_INT);
						$stmt->bindValue(':id', $player->user, PDO::PARAM_STR);
						$stmt->execute();
					} else {
						$stmt = $db->prepare('UPDATE players
							SET next_round = NULL
							WHERE id = :id');
						$stmt->bindValue(':id', $player->user, PDO::PARAM_STR);
						$stmt->execute();
					}

					$stmt = $db->prepare('UPDATE lobby_slots
						SET continue_to_upper = :continue_to_upper, drop_down = :drop_down
						WHERE lobby = :lobby AND user_id = :user_id');
					$stmt->bindValue(':continue_to_upper', $continues, PDO::PARAM_BOOL);
					$stmt->bindValue(':drop_down', $drops_down, PDO::PARAM_BOOL);
					$stmt->bindValue(':lobby', $_GET['lobby'], PDO::PARAM_INT);
					$stmt->bindValue(':user_id', $player->user, PDO::PARAM_STR);
					$stmt->execute();
				}
			}

			echoFeedback(false, 'Lobby updated');
			return;
		}
		if ($body->action == 'comment') {
			$stmt = $db->prepare('UPDATE lobbies
				SET comment = :comment
				WHERE id = :id');
			$stmt->bindValue(':comment', $body->comment, PDO::PARAM_STR);
			$stmt->bindValue(':id', $_GET['lobby'], PDO::PARAM_INT);
			$stmt->execute();

			echoFeedback(false, 'Lobby updated');
			return;
		}
	}

	http_response_code(401);
	echoFeedback(true, 'You need the scope ADMIN or REFEREE to edit lobbies');
}

/**
 * Updates the lobby slot identified by id
 *
 * @param integer  $_GET['slot']  Id of the lobby slot
 */
function putLobbySlot() {
	global $database;
	$db = $database->getConnection();

	$user = checkToken();

	if ($user->scope != 'ADMIN') {
		http_response_code(401);
		echoFeedback(true, 'You need the scope ADMIN to edit lobby slots');
		return;
	}

	$body = json_decode(file_get_contents('php://input'));

	if ($body->action == 'remove_player') {
		$stmt = $db->prepare('SELECT user_id
			FROM lobby_slots
			WHERE id = :id');
		$stmt->bindValue(':id', $_GET['slot'], PDO::PARAM_INT);
		$stmt->execute();
		$player = $stmt->fetchAll(PDO::FETCH_ASSOC);
		$stmt = $db->prepare('UPDATE lobby_slots
			SET user_id = NULL
			WHERE id = :id');
		$stmt->bindValue(':id', $_GET['slot'], PDO::PARAM_INT);
		$stmt->execute();
		$stmt = $db->prepare('UPDATE players
			SET current_lobby = NULL
			WHERE id = :id');
		$stmt->bindValue(':id', $player[0]['user_id'], PDO::PARAM_STR);
		$stmt->execute();
	}

	if ($body->action == 'choose_player') {
		$stmt = $db->prepare('SELECT lobby, user_id
			FROM lobby_slots
			WHERE id = :id');
		$stmt->bindValue(':id', $_GET['slot'], PDO::PARAM_INT);
		$stmt->execute();
		$lobby = $stmt->fetchAll(PDO::FETCH_ASSOC);
		$stmt = $db->prepare('UPDATE lobby_slots
			SET user_id = :user_id
			WHERE id = :id');
		$stmt->bindValue(':user_id', $body->id, PDO::PARAM_STR);
		$stmt->bindValue(':id', $_GET['slot'], PDO::PARAM_INT);
		$stmt->execute();
		$stmt = $db->prepare('UPDATE players
			SET current_lobby = :current_lobby
			WHERE id = :id');
		$stmt->bindValue(':current_lobby', $lobby[0]['lobby'], PDO::PARAM_INT);
		$stmt->bindValue(':id', $body->id, PDO::PARAM_STR);
		$stmt->execute();
		if (!empty($lobby[0]['user_id'])) {
			$stmt = $db->prepare('UPDATE players
			SET current_lobby = NULL
			WHERE id = :id');
		$stmt->bindValue(':id', $lobby[0]['user_id'], PDO::PARAM_STR);
		$stmt->execute();
		}
	}

	echoFeedback(false, 'Slot updated');
}

/**
 * Outputs the mappool of a round identified by id
 *
 * @param integer  $_GET['round']  Id of the round
 */
function getMappool() {
	global $database;
	$db = $database->getConnection();
	global $osuApi;

	$user = checkToken();

	if ($user->scope == 'MAPPOOLER' || $user->scope == 'REFEREE') {
		$mappool = new stdClass;
		$mappool->slots = [];
		$stmt = $db->prepare('SELECT mappack_url, copy_mappool_from_round
			FROM rounds
			WHERE id = :id');
		$stmt->bindValue(':id', $_GET['round'], PDO::PARAM_INT);
		$stmt->execute();
		$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
		$mappool->mappack = $rows[0]['mappack_url'];
		if ($rows[0]['copy_mappool_from_round'] != -1) {
			$round = $rows[0]['copy_mappool_from_round'];
		} else {
			$round = $_GET['round'];
		}

		$stmt = $db->prepare('SELECT mappool_slots.id, mappool_slots.beatmap_id, mappool_slots.mods, mappool_slots.freemod, mappool_slots.tiebreaker, mappool_slots.beatmap_info
			FROM mappool_slots
			WHERE mappool_slots.round = :round');
		$stmt->bindValue(':round', $round, PDO::PARAM_INT);
		$stmt->execute();
		$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

		foreach ($rows as $row) {
			$slot = new stdClass;
			$slot->id = $row['id'];
			$slot->beatmap = $osuApi->getBeatmap($row['beatmap_id']);
			$slot->mods = $row['mods'];
			$slot->freemod = $row['freemod'];
			$slot->tiebreaker = $row['tiebreaker'];
			$slot->beatmap_info = $row['beatmap_info'];

			$mappool->slots[] = $slot;
		}

		usort($mappool->slots, function($a, $b) {
			if ($a->tiebreaker == '1') {
				return 1;
			}
			if ($b->tiebreaker == '1') {
				return -1;
			}
			if ($a->freemod == '1') {
				if ($b->freemod == '0') {
					return 1;
				} else {
					return 0;
				}
			} elseif ($b->freemod == '1') {
				return -1;
			}
			return $a->mods - $b->mods;
		});

		echo json_encode($mappool);
		return;
	}

	if ($user->scope == 'PLAYER') {
		$stmt = $db->prepare('SELECT public_release_time
			FROM rounds
			WHERE id = :id');
		$stmt->bindValue(':id', $_GET['round'], PDO::PARAM_INT);
		$stmt->execute();
		$release_time = $stmt->fetchAll(PDO::FETCH_ASSOC);
		$now = new DateTime();
		if (empty($release_time[0]['public_release_time']) || new DateTime($release_time[0]['public_release_time']) > $now) {
			echo '{}';
			return;
		}

		$mappool = new stdClass;
		$mappool->slots = [];
		$stmt = $db->prepare('SELECT mappack_url, copy_mappool_from_round
			FROM rounds
			WHERE id = :id');
		$stmt->bindValue(':id', $_GET['round'], PDO::PARAM_INT);
		$stmt->execute();
		$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
		if ($rows[0]['copy_mappool_from_round'] != -1) {
			$round = $rows[0]['copy_mappool_from_round'];
			$stmt = $db->prepare('SELECT mappack_url
				FROM rounds
				WHERE id = :id');
			$stmt->bindValue(':id', $round, PDO::PARAM_INT);
			$stmt->execute();
			$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
			$mappool->mappack = $rows[0]['mappack_url'];
		} else {
			$round = $_GET['round'];
			$mappool->mappack = $rows[0]['mappack_url'];
		}

		$stmt = $db->prepare('SELECT mappool_slots.id, mappool_slots.beatmap_id, mappool_slots.mods, mappool_slots.freemod, mappool_slots.tiebreaker, mappool_slots.beatmap_info
			FROM mappool_slots
			WHERE mappool_slots.round = :round');
		$stmt->bindValue(':round', $round, PDO::PARAM_INT);
		$stmt->execute();
		$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

		foreach ($rows as $row) {
			$slot = new stdClass;
			$slot->id = $row['id'];
			$slot->beatmap = $osuApi->getBeatmap($row['beatmap_id']);
			$slot->mods = $row['mods'];
			$slot->freemod = $row['freemod'];
			$slot->tiebreaker = $row['tiebreaker'];
			$slot->beatmap_info = $row['beatmap_info'];

			$mappool->slots[] = $slot;
		}

		usort($mappool->slots, function($a, $b) {
			if ($a->tiebreaker == '1') {
				return 1;
			}
			if ($b->tiebreaker == '1') {
				return -1;
			}
			if ($a->freemod == '1') {
				if ($b->freemod == '0') {
					return 1;
				} else {
					return 0;
				}
			} elseif ($b->freemod == '1') {
				return -1;
			}
			return $a->mods - $b->mods;
		});

		echo json_encode($mappool);
		return;
	}

	if ($user->scope == 'PUBLIC') {
		$stmt = $db->prepare('SELECT public_release_time
			FROM rounds
			WHERE id = :id');
		$stmt->bindValue(':id', $_GET['round'], PDO::PARAM_INT);
		$stmt->execute();
		$release_time = $stmt->fetchAll(PDO::FETCH_ASSOC);
		$now = new DateTime();
		if (empty($release_time[0]['public_release_time']) || new DateTime($release_time[0]['public_release_time']) > $now) {
			echo '{}';
			return;
		}
		
		$matches = [];
		$stmt = $db->prepare('SELECT match_id
			FROM lobbies
			WHERE match_id IS NOT NULL AND round = :round');
		$stmt->bindValue(':round', $_GET['round'], PDO::PARAM_INT);
		$stmt->execute();
		$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
		foreach ($rows as $row) {
			$match = $osuApi->getMatch($row['match_id']);
			$match->bans = [];
			$stmt = $db->prepare('SELECT beatmap_id
				FROM osu_match_bans
				WHERE match_id = :match_id');
			$stmt->bindValue(':match_id', $row['match_id'], PDO::PARAM_INT);
			$stmt->execute();
			$bans = $stmt->fetchAll(PDO::FETCH_ASSOC);
			foreach ($bans as $ban) {
				$match->bans[] = $ban['beatmap_id'];
			}
			$matches[] = $match;
		}

		$amount_matches = count($matches);

		$mappool = new stdClass;
		$mappool->slots = [];
		$stmt = $db->prepare('SELECT mappack_url, copy_mappool_from_round
			FROM rounds
			WHERE id = :id');
		$stmt->bindValue(':id', $_GET['round'], PDO::PARAM_INT);
		$stmt->execute();
		$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
		$mappool->mappack = $rows[0]['mappack_url'];
		if ($rows[0]['copy_mappool_from_round'] != -1) {
			$round = $rows[0]['copy_mappool_from_round'];
		} else {
			$round = $_GET['round'];
		}

		$stmt = $db->prepare('SELECT mappool_slots.id, mappool_slots.beatmap_id, mappool_slots.mods, mappool_slots.freemod, mappool_slots.tiebreaker, mappool_slots.beatmap_info
			FROM mappool_slots
			WHERE mappool_slots.round = :round');
		$stmt->bindValue(':round', $round, PDO::PARAM_INT);
		$stmt->execute();
		$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

		foreach ($rows as $row) {
			$slot = new stdClass;
			$slot->id = $row['id'];
			$slot->beatmap = $osuApi->getBeatmap($row['beatmap_id']);
			$slot->mods = $row['mods'];
			$slot->freemod = $row['freemod'];
			$slot->tiebreaker = $row['tiebreaker'];
			$slot->beatmap_info = $row['beatmap_info'];

			$slot->pick_amount = 0;
			$slot->ban_amount = 0;
			$slot->total_amount = 0;
			$total_acc = 0;
			$score_count = 0;
			$slot->pass_amount = 0;
			$slot->fail_amount = 0;
			foreach ($matches as $match) {
				foreach ($match->games as $game) {
					if ($game->beatmap_id == $slot->beatmap->beatmap_id && $game->counts == '1') {
						$slot->pick_amount++;
						$slot->total_amount++;
						foreach ($game->scores as $score) {
							$points_of_hits = $score->count50 * 50 + $score->count100 * 100 + $score->count300 * 300;
							$number_of_hits = (int) $score->countmiss + (int) $score->count50 + (int) $score->count100 + (int) $score->count300;
							if ($number_of_hits == 0) {
								$tota_acc = 0;
							} else {
								$total_acc += round($points_of_hits / ($number_of_hits * 300), 2);
							}
							$score_count++;
							if ($score->pass == '1') {
								$slot->pass_amount++;
							} else {
								$slot->fail_amount++;
							}
						}
					}
				}
				foreach ($match->bans as $ban) {
					if ($ban == $slot->beatmap->beatmap_id) {
						$slot->ban_amount++;
						$slot->total_amount++;
					}
				}
			}
			if ($amount_matches > 0) {
				$slot->pick_percentage = round($slot->pick_amount / $amount_matches * 100, 2);
				$slot->ban_percentage = round($slot->ban_amount / $amount_matches * 100, 2);
				$slot->total_percentage = round($slot->total_amount / $amount_matches * 100, 2);
				if ($score_count > 0) {
					$slot->average_accuracy = round($total_acc / $score_count * 100, 2);
					$slot->pass_percentage = round($slot->pass_amount / $score_count * 100, 2);
					$slot->fail_percentage = round($slot->fail_amount / $score_count * 100, 2);
				}
			}

			$mappool->slots[] = $slot;
		}

		usort($mappool->slots, function($a, $b) {
			if ($a->tiebreaker == '1') {
				return 1;
			}
			if ($b->tiebreaker == '1') {
				return -1;
			}
			if ($a->freemod == '1') {
				if ($b->freemod == '0') {
					return 1;
				} else {
					return 0;
				}
			} elseif ($b->freemod == '1') {
				return -1;
			}
			return $a->mods - $b->mods;
		});

		echo json_encode($mappool);
		return;
	}

	http_response_code(401);
	echoFeedback(true, 'You have no access to mappools');
}

/**
 * Updates the mappool slot identified by id
 *
 * @param integer  $_GET['slot']  Id of the mappool slot
 */
function putMappoolSlot() {
	global $database;
	$db = $database->getConnection();
	global $osuApi;

	$user = checkToken();

	$body = json_decode(file_get_contents('php://input'));

	if ($user->scope == 'MAPPOOLER') {
		$beatmap = $osuApi->getBeatmap($body->beatmap_id);

		if (!empty($beatmap)) {
			$stmt = $db->prepare('UPDATE mappool_slots
				SET beatmap_id = :beatmap_id, mods = :mods, freemod = :freemod, tiebreaker = :tiebreaker, beatmap_info = :beatmap_info
				WHERE id = :id');
			$stmt->bindValue(':beatmap_id', $body->beatmap_id, PDO::PARAM_INT);
			$stmt->bindValue(':mods', $body->mods, PDO::PARAM_INT);
			$stmt->bindValue(':freemod', $body->freemod == '1', PDO::PARAM_BOOL);
			$stmt->bindValue(':tiebreaker', $body->tiebreaker == '1', PDO::PARAM_BOOL);
			$stmt->bindValue(':beatmap_info', $body->beatmap_info, PDO::PARAM_STR);
			$stmt->bindValue(':id', $_GET['slot'], PDO::PARAM_INT);
			$stmt->execute();

			echoFeedback(false, 'Slot saved');
			return;
		} else {
			echoFeedback(true, 'This is not a valid beatmap id');
			return;
		}
	}

	http_response_code(401);
	echoFeedback(true, 'You have no access to mappools');
}

/**
 * Creates a new mappool slot in the mappool of a round identified by id
 *
 * @param integer  $_GET['round']  Id of the round
 */
function postMappool() {
	global $database;
	$db = $database->getConnection();
	global $osuApi;

	$user = checkToken();

	$body = json_decode(file_get_contents('php://input'));

	if ($user->scope == 'MAPPOOLER') {
		$beatmap = $osuApi->getBeatmap($body->beatmap_id);

		if (!empty($beatmap)) {
			$stmt = $db->prepare('INSERT INTO mappool_slots (round, beatmap_id, mods, freemod, tiebreaker, beatmap_info)
				VALUES (:round, :beatmap_id, :mods, :freemod, :tiebreaker, :beatmap_info)');
			$stmt->bindValue(':round', $_GET['round'], PDO::PARAM_INT);
			$stmt->bindValue(':beatmap_id', $body->beatmap_id, PDO::PARAM_INT);
			$stmt->bindValue(':mods', $body->mods, PDO::PARAM_INT);
			$stmt->bindValue(':freemod', $body->freemod == '1', PDO::PARAM_BOOL);
			$stmt->bindValue(':tiebreaker', $body->tiebreaker == '1', PDO::PARAM_BOOL);
			$stmt->bindValue(':beatmap_info', $body->beatmap_info, PDO::PARAM_STR);
			$stmt->execute();

			echoFeedback(false, 'Slot saved');
			return;
		} else {
			echoFeedback(true, 'This is not a valid beatmap id');
			return;
		}
	}

	http_response_code(401);
	echoFeedback(true, 'You have no access to mappools');
}

/**
 * Creates the mappack for a round identified by id
 *
 * @param integer  $_GET['round']  Id of the round
 */
function getMappack() {
	global $database;
	$db = $database->getConnection();
	global $osuApi;

	$user = checkToken();

	if ($user->scope != 'MAPPOOLER') {
		http_response_code(401);
		echoFeedback(true, 'You need the scope MAPPOOLER to create mappacks');
		return;
	}

	$stmt = $db->prepare('SELECT mappool_slots.beatmap_id
		FROM mappool_slots
		WHERE mappool_slots.round = :round');
	$stmt->bindValue(':round', $_GET['round'], PDO::PARAM_INT);
	$stmt->execute();
	$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

	if (count($rows) == 0) {
		echoFeedback(true, 'No beatmaps in this mappool');
		return;
	}
	
	$beatmaps = [];
	foreach ($rows as $row) {
		$beatmaps[] = $row['beatmap_id'];
	}

	$stmt = $db->prepare('SELECT tiers.lower_endpoint, tiers.upper_endpoint, rounds.name
		FROM rounds INNER JOIN tiers ON rounds.tier = tiers.id
		WHERE rounds.id = :id');
	$stmt->bindValue(':id', $_GET['round'], PDO::PARAM_INT);
	$stmt->execute();
	$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
	$tier_name = $rows[0]['lower_endpoint'] . '-' . $rows[0]['upper_endpoint'];
	$week_name = $rows[0]['name'];

	$curl = curl_init();
	curl_setopt($curl, CURLOPT_URL, 'https://osu.ppy.sh/forum/ucp.php?mode=login');
	curl_setopt($curl, CURLOPT_POST, true);
	curl_setopt($curl, CURLOPT_POSTFIELDS, 'sid=&username=Borengar&password=nHmsKKa6QKN3k0JbmHaj&autologin=on&login=login');
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($curl, CURLOPT_VERBOSE, true);
	curl_setopt($curl, CURLOPT_HEADER, true);
	$response = curl_exec($curl);

	$header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
	$header = substr($response, 0, $header_size);
	$body = substr($response, $header_size);

	curl_close($curl);

	$cookie_headers = explode('Set-Cookie: ', $header);

	$cookies = [];

	for ($i = 1; $i < count($cookie_headers) - 2; $i++) {
		$cookies[] = $cookie_headers[$i];
	}
	$cookie_string = '';
	foreach ($cookies as $cookie) {
		$cookie_string .= explode(';', $cookie)[0] . '; ';
	}

	$zip = new ZipArchive();
	$filename = '../mappacks/Mappack ' . $tier_name . ' ' . $week_name . '.zip';
	$zipError = $zip->open($filename, ZipArchive::OVERWRITE|ZipArchive::CREATE);
	if ($zipError !== TRUE) {
	   echoFeedback(true, 'Error when creating zip file');
	   return;
	}

	foreach ($beatmaps as $beatmap) {
		$beatmap_data = $osuApi->getBeatmap($beatmap);

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, 'https://osu.ppy.sh/d/' . $beatmap_data->beatmapset_id);
		curl_setopt($ch, CURLOPT_HEADER, true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_COOKIE, $cookieString);

		$a = curl_exec($ch);

		$url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);

		curl_close($ch);

		$options = array(
			CURLOPT_FILE => fopen('../mappacks/' . str_replace('*', '', $beatmap_data->artist) . ' - ' . str_replace('*', '', $beatmap_data->title) . '.osz', 'w'),
			CURLOPT_TIMEOUT => 28800,
			CURLOPT_URL => $url
		);

		$ch = curl_init();
		curl_setopt_array($ch, $options);
		curl_exec($ch);
		curl_close($ch);
		
		$zip->addFile('../mappacks/' . str_replace('*', '', $beatmap_data->artist) . ' - ' . str_replace('*', '', $beatmap_data->title) . '.osz', str_replace('*', '', $beatmap_data->artist) . ' - ' . str_replace('*', '', $beatmap_data->title) . '.osz');
	}

	$zip->close();

	$response = new stdClass;
	$response->error = '0';
	$response->message = 'Zip created';
	$response->filename = 'http://happysticktour.com/mappacks/Mappack ' . $tier_name . ' ' . $week_name . '.zip';
	echo json_encode($response);
}

/**
 * Updates the mappack url of a round identified by id
 *
 * @param integer  $_GET['round']  Id of the round
 */
function putMappack() {
	global $database;
	$db = $database->getConnection();

	$user = checkToken();

	if ($user->scope != 'MAPPOOLER') {
		http_response_code(401);
		echoFeedback(true, 'You need the scope MAPPOOLER to edit the mappack url');
		return;
	}

	$body = json_decode(file_get_contents('php://input'));

	$stmt = $db->prepare('UPDATE rounds
		SET mappack_url = :mappack_url
		WHERE id = :id');
	$stmt->bindValue(':mappack_url', $body->mappack, PDO::PARAM_STR);
	$stmt->bindValue('id', $_GET['round'], PDO::PARAM_INT);
	$stmt->execute();

	echoFeedback(false, 'Mappack URL updated');
}

/**
 * Deletes the mappool slot identified by id
 *
 * @param integer  $_GET['slot']  Id of the mappool slot
 */
function deleteMappoolSlot() {
	global $database;
	$db = $database->getConnection();

	$user = checkToken();

	if ($user->scope == 'MAPPOOLER') {
		$stmt = $db->prepare('DELETE FROM mappool_slots
			WHERE id = :id');
		$stmt->bindValue(':id', $_GET['slot'], PDO::PARAM_INT);
		$stmt->execute();

		echoFeedback('Slot deleted');
		return;
	}

	http_response_code(401);
	echoFeedback(true, 'You have no access to mappools');
}

/**
 * Outputs the osu profile identified by id
 *
 * @param integer  $_GET['id']  Id of the osu profile
 */
function getOsuProfile() {
	global $osuApi;

	$user = checkToken();

	if (empty($_GET['id'])) {
		echo '{}';
		return;
	}

	$profile = $osuApi->getUser($_GET['id']);

	if (empty($profile)) {
		echo '{}';
		return;
	}

	echo json_encode($profile);
}

/**
 * Outputs the osu beatmap identified by id
 *
 * @param integer  $_GET['id']  Beatmap-Id of the osu beatmap
 */
function getOsuBeatmap() {
	global $osuApi;

	$user = checkToken();

	if (empty($_GET['id'])) {
		echo '{}';
		return;
	}

	$beatmap = $osuApi->getBeatmap($_GET['id']);

	if (empty($beatmap)) {
		echo '{}';
		return;
	}

	echo json_encode($beatmap);
}

/**
 * Outputs the match identified by id
 *
 * @param integer  $_GET['match']  Id of the osu match
 */
function getMatch() {
	global $osuApi;

	$user = checkToken();

	if ($user->scope == 'REFEREE' || $user->scope == 'PLAYER' || $user->scope == 'PUBLIC') {
		echo json_encode($osuApi->getMatch($_GET['match']));
		return;
	}

	http_response_code(401);
	echoFeedback(true, 'You cannot access matches');
}

/**
 * Updates the game identified by id
 *
 * @param integer  $_GET['game']  Id of the osu game
 */
function putGame() {
	global $database;
	$db = $database->getConnection();

	$user = checkToken();

	if ($user->scope != 'REFEREE') {
		http_response_code(401);
		echoFeedback(true, 'You need the scope REFEREE to update games');
		return;
	}

	$body = json_decode(file_get_contents('php://input'));

	if ($body->action == 'counts') {
		$stmt = $db->prepare('UPDATE osu_games
			SET counts = :counts
			WHERE game_id = :game_id');
		$stmt->bindValue(':counts', $body->counts, PDO::PARAM_BOOL);
		$stmt->bindValue(':game_id', $_GET['game'], PDO::PARAM_INT);
		$stmt->execute();
		echoFeedback(false, 'Game updated');
		return;
	}

	if ($body->action == 'picked_by') {
		$stmt = $db->prepare('UPDATE osu_games
			SET picked_by = :picked_by
			WHERE game_id = :game_id');
		$stmt->bindValue(':picked_by', $body->picked_by, PDO::PARAM_STR);
		$stmt->bindValue(':game_id', $_GET['game'], PDO::PARAM_INT);
		$stmt->execute();
		echoFeedback(false, 'Game updated');
		return;
	}
}

/**
 * Outputs a list of all tickets
 */
function getTickets() {
	global $database;
	$db = $database->getConnection();

	$user = checkToken();

	if ($user->scope == 'ADMIN') {
		$stmt = $db->prepare('SELECT tickets.id, tickets.creator, tickets.topic, tickets.title, tickets.closed
			FROM tickets');
		$stmt->execute();
		$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
	} elseif ($user->scope == 'PLAYER') {
		$stmt = $db->prepare('SELECT tickets.id, tickets.creator, tickets.topic, tickets.title, tickets.closed
			FROM tickets
			WHERE tickets.creator = :creator');
		$stmt->bindValue(':creator', $user->id, PDO::PARAM_STR);
		$stmt->execute();
		$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
	} else {
		echoFeedback(true, 'Only users with the scope ADMIN or PLAYER can view tickets');
		return;
	}

	$tickets = [];
	foreach ($rows as $row) {
		$ticket = new stdClass;
		$ticket->id = $row['id'];
		$ticket->topic = $row['topic'];
		$ticket->title = $row['title'];
		$ticket->closed = $row['closed'];

		$stmt = $db->prepare('SELECT user_id, timestamp
			FROM ticket_messages
			WHERE ticket = :id
			ORDER BY timestamp DESC');
		$stmt->bindValue(':id', $ticket->id, PDO::PARAM_INT);
		$stmt->execute();
		$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

		$ticket->last_message = new stdClass;
		$ticket->last_message->time = $messages[0]['timestamp'];
		$ticket->last_message->user = $database->user($messages[0]['user_id']);

		$tickets[] = $ticket;
	}

	usort($tickets, function($a, $b) {
		return $a->last_message->time < $b->last_message->time;
	});

	echo json_encode($tickets);
}

/**
 * Creates a new ticket
 */
function postTicket() {
	global $database;
	$db = $database->getConnection();

	$user = checkToken();

	if ($user->scope != 'PLAYER') {
		http_response_code(401);
		echoFeedback(true, 'Only users with scope PLAYER can create tickets');
		return;
	}

	$body = json_decode(file_get_contents('php://input'));

	$stmt = $db->prepare('INSERT INTO tickets (creator, topic, title, closed)
		VALUES (:creator, :topic, :title, 0)');
	$stmt->bindValue(':creator', $user->id, PDO::PARAM_STR);
	$stmt->bindValue(':topic', $body->topic, PDO::PARAM_STR);
	$stmt->bindValue(':title', $body->title, PDO::PARAM_STR);
	$stmt->execute();

	$stmt = $db->prepare('SELECT MAX(id) as last_id
		FROM tickets
		WHERE creator = :creator');
	$stmt->bindValue(':creator', $user->id, PDO::PARAM_STR);
	$stmt->execute();
	$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
	$last_id = $rows[0]['last_id'];

	$stmt = $db->prepare('INSERT INTO ticket_messages (ticket, user_id, message, timestamp)
		VALUES (:ticket, :user_id, :message, :timestamp)');
	$stmt->bindValue(':ticket', $last_id, PDO::PARAM_INT);
	$stmt->bindValue(':user_id', $user->id, PDO::PARAM_INT);
	$stmt->bindValue(':message', $body->message, PDO::PARAM_STR);
	$stmt->bindValue(':timestamp', gmdate('Y-m-d H:i:s'), PDO::PARAM_STR);
	$stmt->execute();

	echoFeedback(false, 'Ticket created');
}

/**
 * Outputs a ticket identified by id
 *
 * @param integer  $_GET['ticket']  Id of the ticket
 */
function getTicket() {
	global $database;
	$db = $database->getConnection();

	$user = checkToken();

	if ($user->scope != 'ADMIN' && $user->scope != 'PLAYER') {
		echoFeedback(true, 'Only users with the scope ADMIN or PLAYER can view tickets');
		return;
	}

	$stmt = $db->prepare('SELECT id, creator, topic, title, closed
		FROM tickets
		WHERE id = :id');
	$stmt->bindValue(':id', $_GET['ticket'], PDO::PARAM_INT);
	$stmt->execute();
	$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

	if ($user->scope == 'PLAYER' && $rows[0]['creator'] != $user->id) {
		echoFeedback(true, 'You have no access to this ticket');
		return;
	}

	$ticket = new stdClass;
	$ticket->id = $rows[0]['id'];
	$ticket->topic = $rows[0]['topic'];
	$ticket->title = $rows[0]['title'];
	$ticket->closed = $rows[0]['closed'];
	$ticket->creator = $database->user($rows[0]['creator']);

	$stmt = $db->prepare('SELECT id, message, timestamp, user_id
		FROM ticket_messages
		WHERE ticket = :ticket
		ORDER BY timestamp ASC');
	$stmt->bindValue(':ticket', $ticket->id, PDO::PARAM_INT);
	$stmt->execute();
	$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$ticket->messages = [];

	foreach ($rows as $row) {
		$message = new stdClass;
		$message->id = $row['id'];
		$message->message = $row['message'];
		$message->timestamp = $row['timestamp'];
		$message->creator = $database->user($row['user_id']);

		$ticket->messages[] = $message;
	}

	echo json_encode($ticket);
}

/**
 * Adds a message to a ticket identified by id
 *
 * @param integer  $_GET['ticket']  Id of the ticket
 */
function putTicket() {
	global $database;
	$db = $database->getConnection();

	$user = checkToken();

	if ($user->scope != 'ADMIN' && $user->scope != 'PLAYER') {
		echoFeedback(true, 'Only users with the scope ADMIN or PLAYER can post in tickets');
		return;
	}

	$stmt = $db->prepare('SELECT creator, closed
		FROM tickets
		WHERE id = :id');
	$stmt->bindValue(':id', $_GET['ticket'], PDO::PARAM_INT);
	$stmt->execute();
	$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

	if ($rows[0]['closed'] == '1') {
		echoFeedback(true, 'Cannot post in a closed ticket');
		return;
	}

	if ($user->scope == 'PLAYER' && $rows[0]['creator'] != $user->id) {
		echoFeedback(true, 'This is not your own ticket');
		return;
	}

	$body = json_decode(file_get_contents('php://input'));

	$stmt = $db->prepare('INSERT INTO ticket_messages (ticket, user_id, message, timestamp)
		VALUES (:ticket, :user_id, :message, :timestamp)');
	$stmt->bindValue(':ticket', $_GET['ticket'], PDO::PARAM_INT);
	$stmt->bindValue(':user_id', $user->id, PDO::PARAM_STR);
	$stmt->bindValue(':message', $body->message, PDO::PARAM_STR);
	$stmt->bindValue(':timestamp', gmdate('Y-m-d H:i:s'), PDO::PARAM_STR);
	$stmt->execute();

	echoFeedback(false, 'Comment saved');
}

/**
 * Closes a ticket identified by id
 *
 * @param integer  $_GET['ticket']  Id of the ticket
 */
function deleteTicket() {
	global $database;
	$db = $database->getConnection();

	$user = checkToken();

	if ($user->scope != 'ADMIN' && $user->scope != 'PLAYER') {
		echoFeedback(true, 'Only users with the scope ADMIN or PLAYER can close tickets');
		return;
	}

	$stmt = $db->prepare('SELECT creator, closed
		FROM tickets
		WHERE id = :id');
	$stmt->bindValue(':id', $_GET['ticket'], PDO::PARAM_INT);
	$stmt->execute();
	$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

	if ($rows[0]['closed'] == '1') {
		echoFeedback(true, 'Cannot close a already closed ticket');
		return;
	}

	if ($user->scope == 'PLAYER' && $rows[0]['creator'] != $user->id) {
		echoFeedback(true, 'This is not your own ticket');
		return;
	}

	$body = json_decode(file_get_contents('php://input'));

	$stmt = $db->prepare('UPDATE tickets
		SET closed = 1
		WHERE id = :id');
	$stmt->bindValue(':id', $_GET['ticket'], PDO::PARAM_INT);
	$stmt->execute();

	echoFeedback(false, 'Ticket closed');
}


/**
 * Outputs a list of all blacklisted osu profiles
 */
function getBlacklist() {
	global $database;
	$db = $database->getConnection();
	global $osuApi;

	$user = checkToken();

	if ($user->scope == 'ADMIN') {
		$blacklist = [];
		$stmt = $db->prepare('SELECT osu_id
			FROM blacklist');
		$stmt->execute();
		$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

		foreach ($rows as $row) {
			$profile = $osuApi->getUser($row['osu_id']);
			if (!empty($profile)) {
				$blacklist[] = $profile;
			}
		}

		usort($blacklist, function($a, $b) {
			return strtolower($b->username) < strtolower($a->username);
		});

		echo json_encode($blacklist);
		return;
	}

	http_response_code(401);
	echoFeedback(true, 'Only users with scope ADMIN have access to the blacklist');
}

/**
 * Adds an osu profile to the blacklist
 */
function postBlacklist() {
	global $database;
	$db = $database->getConnection();

	$user = checkToken();

	if ($user->scope != 'ADMIN') {
		http_response_code(401);
		echoFeedback(true, 'Only users with scope ADMIN have access to the blacklist');
		return;
	}

	$body = json_decode(file_get_contents('php://input'));

	$stmt = $db->prepare('INSERT INTO blacklist (osu_id)
		VALUES (:osu_id)');
	$stmt->bindValue(':osu_id', $body->id, PDO::PARAM_INT);
	$stmt->execute();

	echoFeedback(false, 'Profile added to blacklist');
}

/**
 * Deletes an osu profile from the blacklist
 *
 * @param integer  $_GET['id']  Id of the osu profile
 */
function deleteBlacklist() {
	global $database;
	$db = $database->getConnection();

	$user = checkToken();

	if ($user->scope != 'ADMIN') {
		http_response_code(401);
		echoFeedback(true, 'Only users with scope ADMIN have access to the blacklist');
		return;
	}

	$stmt = $db->prepare('DELETE FROM blacklist
		WHERE osu_id = :osu_id');
	$stmt->bindValue(':osu_id', $_GET['id'], PDO::PARAM_INT);
	$stmt->execute();

	echoFeedback(false, 'Profile removed from blacklist');
}

/**
 * Outputs a list of unscheduled players in a round identified by id
 *
 * @param integer  $_GET['round']  Id of the round
 */
function getFreePlayers() {
	global $database;
	$db = $database->getConnection();
	global $osuApi;

	$user = checkToken();

	if ($user->scope != 'ADMIN') {
		http_response_code(401);
		echoFeedback(true, 'You need the scope ADMIN to view unscheduled players');
		return;
	}

	$stmt = $db->prepare('SELECT players.id, players.osu_id, players.twitch_id, players.trivia, players.current_lobby, players.next_round, twitch_users.sub_since, twitch_users.sub_plan
		FROM players LEFT JOIN twitch_users ON players.twitch_id = twitch_users.id
		WHERE players.next_round = :next_round AND players.id NOT IN (
			SELECT lobby_slots.user_id
			FROM lobby_slots INNER JOIN lobbies ON lobby_slots.lobby = lobbies.id
			WHERE lobbies.round = :round AND lobby_slots.user_id IS NOT NULL
		)');
	$stmt->bindValue(':next_round', $_GET['round'], PDO::PARAM_INT);
	$stmt->bindValue(':round', $_GET['round'], PDO::PARAM_INT);
	$stmt->execute();
	$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$players = [];
	foreach ($rows as $row) {
		$player = new stdClass;
		$player->id = $row['id'];
		$player->osu_profile = $osuApi->getUser($row['osu_id']);
		$player->trivia = $row['trivia'];
		$player->current_lobby = $row['current_lobby'];
		$player->next_round = $row['next_round'];

		$player->availabilities = [];
		$stmt = $db->prepare('SELECT time_from, time_to
			FROM availability
			WHERE round = :round AND user_id = :user_id
			ORDER BY time_from ASC');
		$stmt->bindValue(':round', $_GET['round'], PDO::PARAM_INT);
		$stmt->bindValue(':user_id', $player->id, PDO::PARAM_STR);
		$stmt->execute();
		$availabilities = $stmt->fetchAll(PDO::FETCH_ASSOC);
		foreach ($availabilities as $availability) {
			$availabilityObject = new stdClass;
			$availabilityObject->time_from = $availability['time_from'];
			$availabilityObject->time_to = $availability['time_to'];
			$player->availabilities[] = $availabilityObject;
		}

		$players[] = $player;
	}

	echo json_encode($players);
}

/**
 * Outputs a list of availabilities for a round identified by id
 *
 * @param integer  $_GET['round']  Id of the round
 */
function getAvailability() {
	global $database;
	$db = $database->getConnection();

	$user = checkToken();

	if ($user->scope == 'PLAYER') {
		$stmt = $db->prepare('SELECT id, time_from, time_to
			FROM availability
			WHERE round = :round AND user_id = :user_id');
		$stmt->bindValue(':round', $_GET['round'], PDO::PARAM_INT);
		$stmt->bindValue(':user_id', $user->id, PDO::PARAM_STR);
		$stmt->execute();
		$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

		$availabilities = [];
		foreach ($rows as $row) {
			$availability = new stdClass;
			$availability->id = $row['id'];
			$availability->time_from = $row['time_from'];
			$availability->time_to = $row['time_to'];
			$availabilities[] = $availability;
		}

		echo json_encode($availabilities);
		return;
	} elseif ($user->scope == 'ADMIN') {
		$stmt = $db->prepare('SELECT players.id, discord_users.username, discord_users.discriminator, discord_users.avatar
			FROM players INNER JOIN discord_users ON players.id = discord_users.id
			WHERE players.next_round = :next_round');
		$stmt->bindValue(':next_round', $_GET['round'], PDO::PARAM_INT);
		$stmt->execute();
		$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

		$players = [];
		foreach ($rows as $row) {
			$player = new stdClass;
			$player->discord_profile = new stdClass;
			$player->discord_profile->id = $row['id'];
			$player->discord_profile->username = $row['username'];
			$player->discord_profile->discriminator = $row['discriminator'];
			$player->discord_profile->avatar = $row['avatar'];

			$stmt = $db->prepare('SELECT availability.id, availability.time_from, availability.time_to
				FROM availability
				WHERE availability.round = :round AND availability.user_id = :user_id');
			$stmt->bindValue(':round', $_GET['round'], PDO::PARAM_INT);
			$stmt->bindValue(':user_id', $row['id'], PDO::PARAM_STR);
			$stmt->execute();
			$availabilities = $stmt->fetchAll(PDO::FETCH_ASSOC);

			$player->availability = [];
			foreach ($availabilities as $availability) {
				$availabilityObject = new stdClass;
				$availabilityObject->id = $availability['id'];
				$availabilityObject->time_from = $availability['time_from'];
				$availabilityObject->time_to = $availability['time_to'];
				$player->availability[] = $availabilityObject;
			}

			$players[] = $player;
		}

		echo json_encode($players);
		return;
	}

	http_response_code(401);
	echoFeedback(true, 'You need the scope ADMIN or PLAYER to view availabilities');
}

/**
 * Creates a new availability for a round identified by id
 *
 * @param integer  $_GET['round']  Id of the round
 */
function postAvailability() {
	global $database;
	$db = $database->getConnection();

	$user = checkToken();

	if ($user->scope != 'PLAYER') {
		http_response_code(401);
		echoFeedback(true, 'You need the scope PLAYER to create availabilities');
		return;
	}

	$body = json_decode(file_get_contents('php://input'));

	$stmt = $db->prepare('SELECT time_from, time_to, time_from_2, time_to_2
		FROM rounds
		WHERE id = :id');
	$stmt->bindValue(':id', $_GET['round'], PDO::PARAM_INT);
	$stmt->execute();
	$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
	if (new DateTime($body->time_from) < new DateTime($rows[0]['time_from']) || new DateTime($body->time_from) > new DateTime($rows[0]['time_to']) || new DateTime($body->time_to) < new DateTime($rows[0]['time_from']) || new DateTime($body->time_to) > new DateTime($rows[0]['time_to'])) {
		if ((!empty($rows[0]['time_from_2']) && (new DateTime($body->time_from) < new DateTime($rows[0]['time_from_2']) || new DateTime($body->time_from) > new DateTime($rows[0]['time_to_2']) || new DateTime($body->time_to) < new DateTime($rows[0]['time_from_2']) || new DateTime($body->time_to) > new DateTime($rows[0]['time_to_2']))) || empty($rows[0]['time_from_2'])) {
			echoFeedback(true, 'Your availability is not in the given round times');
			return;
		}
	}

	$stmt = $db->prepare('SELECT id, time_from, time_to
		FROM availability
		WHERE round = :round AND user_id = :user_id');
	$stmt->bindValue(':round', $_GET['round'], PDO::PARAM_INT);
	$stmt->bindValue(':user_id', $user->id, PDO::PARAM_STR);
	$stmt->execute();
	$availabilities = $stmt->fetchAll(PDO::FETCH_ASSOC);

	foreach ($availabilities as $availability) {
		if (new DateTime($availability['time_from']) <= new DateTime($body->time_from) && new DateTime($availability['time_to']) >= new DateTime($body->time_from)) {
			$body->time_from = $availability['time_from'];
			$stmt = $db->prepare('DELETE FROM availability
				WHERE id = :id');
			$stmt->bindValue(':id', $availability['id'], PDO::PARAM_INT);
			$stmt->execute();
		}
		if (new DateTime($availability['time_from']) <= new DateTime($body->time_to) && new DateTime($availability['time_to']) >= new DateTime($body->time_to)) {
			$body->time_to = $availability['time_to'];
			$stmt = $db->prepare('DELETE FROM availability
				WHERE id = :id');
			$stmt->bindValue(':id', $availability['id'], PDO::PARAM_INT);
			$stmt->execute();
		}
	}

	$stmt = $db->prepare('DELETE FROM availability
		WHERE time_from > :time_from AND time_to < :time_to AND round = :round AND user_id = :user_id');
	$stmt->bindValue(':time_from', $body->time_from, PDO::PARAM_STR);
	$stmt->bindValue(':time_to', $body->time_to, PDO::PARAM_STR);
	$stmt->bindValue(':round', $_GET['round'], PDO::PARAM_INT);
	$stmt->bindValue(':user_id', $user->id, PDO::PARAM_STR);
	$stmt->execute();

	$stmt = $db->prepare('INSERT INTO availability (round, user_id, time_from, time_to)
		VALUES (:round, :user_id, :time_from, :time_to)');
	$stmt->bindValue(':round', $_GET['round'], PDO::PARAM_INT);
	$stmt->bindValue(':user_id', $user->id, PDO::PARAM_STR);
	$stmt->bindValue(':time_from', $body->time_from, PDO::PARAM_STR);
	$stmt->bindValue(':time_to', $body->time_to, PDO::PARAM_STR);
	$stmt->execute();

	echoFeedback(false, 'Availability saved');
}

/**
 * Deletes a availability identified by id
 *
 * @param integer  $_GET['id']  Id of the availability
 */
function deleteAvailability() {
	global $database;
	$db = $database->getConnection();

	$user = checkToken();

	if ($user->scope != 'PLAYER') {
		http_response_code(401);
		echoFeedback(true, 'You need the scope PLAYER to delete availabilities');
		return;
	}

	$stmt = $db->prepare('DELETE FROM availability
		WHERE id = :id AND user_id = :user_id');
	$stmt->bindValue(':id', $_GET['id'], PDO::PARAM_INT);
	$stmt->bindValue(':user_id', $user->id, PDO::PARAM_STR);
	$stmt->execute();

	echoFeedback(false, 'Availability deleted');
}

/**
 * Returns all general settings
 */
function getSettings() {
	global $database;
	$db = $database->getConnection();

	$stmt = $db->prepare('SELECT registration_open, registration_close
		FROM settings');
	$stmt->execute();
	$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$settings = new stdClass;
	$settings->registration_open = $rows[0]['registration_open'];
	$settings->registration_close = $rows[0]['registration_close'];

	echo json_encode($settings);
}

/**
 * Updates settings
 */
function putSettings() {
	global $database;
	$db = $database->getConnection();

	$user = checkToken();

	if ($user->scope != 'ADMIN') {
		http_response_code(401);
		echoFeedback(true, 'You need the scope ADMIN to edit settings');
		return;
	}

	$body = json_decode(file_get_contents('php://input'));

	if (!empty($body->registration_open)) {
		$stmt = $db->prepare('UPDATE settings
			SET registration_open = :registration_open');
		$stmt->bindValue(':registration_open', $body->registration_open, PDO::PARAM_STR);
		$stmt->execute();
	}

	if (!empty($body->registration_close)) {
		$stmt = $db->prepare('UPDATE settings
			SET registration_close = :registration_close');
		$stmt->bindValue(':registration_close', $body->registration_close, PDO::PARAM_STR);
		$stmt->execute();
	}

	echoFeedback(false, 'Settings saved');
}

/**
 * Returns mappool feedback for a round identified by id
 *
 * @param integer  $_GET['round']  Id of the round
 */
function getFeedback() {
	global $database;
	$db = $database->getConnection();
	global $osuApi;

	$user = checkToken();

	if ($user->scope == 'PLAYER') {
		$stmt = $db->prepare('SELECT copy_mappool_from_round
			FROM rounds
			WHERE id = :id');
		$stmt->bindValue(':id', $_GET['round'], PDO::PARAM_INT);
		$stmt->execute();
		$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
		if ($rows[0]['copy_mappool_from_round'] != -1) {
			$round = $rows[0]['copy_mappool_from_round'];
		} else {
			$round = $_GET['round'];
		}

		$stmt = $db->prepare('SELECT feedback
			FROM mappool_feedback
			WHERE round = :round AND user_id = :user_id');
		$stmt->bindValue(':round', $round, PDO::PARAM_INT);
		$stmt->bindValue(':user_id', $user->id, PDO::PARAM_STR);
		$stmt->execute();
		$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
		if (!empty($rows[0])) {
			echoFeedback(false, $rows[0]['feedback']);
		} else {
			echoFeedback(false, '');
		}
		return;
	}

	if ($user->scope == 'MAPPOOLER') {
		$stmt = $db->prepare('SELECT copy_mappool_from_round
			FROM rounds
			WHERE id = :id');
		$stmt->bindValue(':id', $_GET['round'], PDO::PARAM_INT);
		$stmt->execute();
		$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
		if ($rows[0]['copy_mappool_from_round'] != -1) {
			$round = $rows[0]['copy_mappool_from_round'];
		} else {
			$round = $_GET['round'];
		}

		$stmt = $db->prepare('SELECT feedback, user_id
			FROM mappool_feedback
			WHERE round = :round');
		$stmt->bindValue(':round', $round, PDO::PARAM_INT);
		$stmt->execute();
		$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

		$feedback = [];
		foreach ($rows as $row) {
			$feedbackObject = new stdClass;
			$feedbackObject->feedback = $row['feedback'];
			$feedbackObject->user = $database->user($row['user_id']);
			$feedback[] = $feedbackObject;
		}

		echo json_encode($feedback);
		return;
	}

	http_response_code(401);
	echoFeedback(true, 'You need the scope PLAYER or MAPPOOLER to see feedback');
}

/**
 * Update the mappool feedback for a round identified by id
 *
 * @param integer  $_GET['round']  Id of the round
 */
function putFeedback() {
	global $database;
	$db = $database->getConnection();

	$user = checkToken();

	if ($user->scope != 'PLAYER') {
		echoFeedback(true, 'You need the scope PLAYER to save feedback');
		return;
	}

	$stmt = $db->prepare('SELECT copy_mappool_from_round
		FROM rounds
		WHERE id = :id');
	$stmt->bindValue(':id', $_GET['round'], PDO::PARAM_INT);
	$stmt->execute();
	$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
	if ($rows[0]['copy_mappool_from_round'] != -1) {
		$round = $rows[0]['copy_mappool_from_round'];
	} else {
		$round = $_GET['round'];
	}

	$body = json_decode(file_get_contents('php://input'));

	$stmt = $db->prepare('SELECT COUNT(*) as rowcount
		FROM mappool_feedback
		WHERE round = :round AND user_id = :user_id');
	$stmt->bindValue(':round', $round, PDO::PARAM_INT);
	$stmt->bindValue(':user_id', $user->id, PDO::PARAM_STR);
	$stmt->execute();
	$count = $stmt->fetchAll(PDO::FETCH_ASSOC);
	if ($count[0]['rowcount'] == '0') {
		$stmt = $db->prepare('INSERT INTO mappool_feedback (round, user_id, feedback)
			VALUES (:round, :user_id, :feedback)');
		$stmt->bindValue(':round', $round, PDO::PARAM_INT);
		$stmt->bindValue(':user_id', $user->id, PDO::PARAM_STR);
		$stmt->bindValue(':feedback', $body->feedback, PDO::PARAM_STR);
		$stmt->execute();
	} else {
		$stmt = $db->prepare('UPDATE mappool_feedback
			SET feedback = :feedback
			WHERE user_id = :user_id AND round = :round');
		$stmt->bindValue(':feedback', $body->feedback, PDO::PARAM_STR);
		$stmt->bindValue(':user_id', $user->id, PDO::PARAM_STR);
		$stmt->bindValue(':round', $round, PDO::PARAM_INT);
		$stmt->execute();
	}

	echoFeedback(false, 'Feedback saved');
}

/**
 * Outputs all bans for a match identified by id
 *
 * @param integer  $_GET['match']  Id of the match
 */
function getBans() {
	global $database;
	$db = $database->getConnection();

	$user = checkToken();

	$stmt = $db->prepare('SELECT beatmap_id, user_id
		FROM osu_match_bans
		WHERE match_id = :match_id');
	$stmt->bindValue(':match_id', $_GET['match'], PDO::PARAM_INT);
	$stmt->execute();
	$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$bans = [];

	foreach ($rows as $row) {
		$ban = new stdClass;
		$ban->beatmap_id = $row['beatmap_id'];
		$ban->user = $database->user($row['user_id']);
		$bans[] = $ban;
	}

	echo json_encode($bans);
}

function postBan() {
	global $database;
	$db = $database->getConnection();

	$user = checkToken();

	if ($user->scope != 'REFEREE') {
		http_response_code(401);
		echoFeedback(true, 'You need the scope REFEREE to change bans');
		return;
	}

	$body = json_decode(file_get_contents('php://input'));

	$stmt = $db->prepare('DELETE FROM osu_match_bans
		WHERE match_id = :match_id AND beatmap_id = :beatmap_id');
	$stmt->bindValue(':match_id', $_GET['match'], PDO::PARAM_INT);
	$stmt->bindValue(':beatmap_id', $body->beatmap_id, PDO::PARAM_INT);
	$stmt->execute();

	$stmt = $db->prepare('INSERT INTO osu_match_bans (match_id, beatmap_id, user_id)
		VALUES (:match_id, :beatmap_id, :user_id)');
	$stmt->bindValue(':match_id', $_GET['match'], PDO::PARAM_INT);
	$stmt->bindValue(':beatmap_id', $body->beatmap_id, PDO::PARAM_INT);
	$stmt->bindValue(':user_id', $body->user_id, PDO::PARAM_STR);
	$stmt->execute();

	echoFeedback(false, 'Ban saved');
}

function deleteBan() {
	global $database;
	$db = $database->getConnection();

	$user = checkToken();

	if ($user->scope != 'REFEREE') {
		http_response_code(401);
		echoFeedback(true, 'You need the scope REFEREE to change bans');
		return;
	}

	$body = json_decode(file_get_contents('php://input'));

	$stmt = $db->prepare('DELETE FROM osu_match_bans
		WHERE match_id = :match_id AND beatmap_id = :beatmap_id');
	$stmt->bindValue(':match_id', $_GET['match'], PDO::PARAM_INT);
	$stmt->bindValue(':beatmap_id', $body->beatmap_id, PDO::PARAM_INT);
	$stmt->execute();

	echoFeedback(false, 'Ban deleted');
}

?>