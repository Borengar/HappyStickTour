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

header('Content-Type: application/json; charset=UTF-8');

switch ($_GET['query']) {
	case 'user':
		switch ($_SERVER['REQUEST_METHOD']) {
			case 'GET': getUser(); break; // get user data
		}
		break;
	case 'playerDiscordId':
		switch ($_SERVER['REQUEST_METHOD']) {
			case 'PUT': putPlayerDiscordId(); break; // change player discord account
		}
		break;
	case 'playerTrivia':
		switch ($_SERVER['REQUEST_METHOD']) {
			case 'PUT': putPlayerTrivia(); break; // update player trivia
		}
		break;
	case 'registrations':
		switch ($_SERVER['REQUEST_METHOD']) {
			case 'GET': getRegistrations(); break; // get a list of all registrations
			case 'POST': postRegistration(); break; // create new registration
			case 'DELETE': deleteRegistration(); break; // delete a registration
		}
		break;
	case 'registrationDiscordId':
		switch ($_SERVER['REQUEST_METHOD']) {
			case 'PUT': putRegistrationDiscordId(); break; // change registration discord id
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
			case 'GET': getLobby(); break; // get lobby by id
		}
		break;
	case 'bans':
		switch ($_SERVER['REQUEST_METHOD']) {
			case 'PUT': putBans(); break; // update lobby bans
		}
		break;
	case 'matchId':
		switch ($_SERVER['REQUEST_METHOD']) {
			case 'PUT': putMatchId(); break; // update lobby match id
		}
		break;
	case 'comment':
		switch ($_SERVER['REQUEST_METHOD']) {
			case 'PUT': putComment(); break; // update lobby comment
		}
		break;
	case 'result':
		switch ($_SERVER['REQUEST_METHOD']) {
			case 'PUT': putResult(); break; // update lobby result
		}
		break;
	case 'mappool':
		switch ($_SERVER['REQUEST_METHOD']) {
			case 'GET': getMappool(); break; // get mappool
		}
		break;
	case 'mappoolSlots':
		switch ($_SERVER['REQUEST_METHOD']) {
			case 'PUT': putMappoolSlots(); break; // update mappool slots
		}
		break;
	case 'mappack':
		switch ($_SERVER['REQUEST_METHOD']) {
			case 'PUT': putMappack(); break; // update mappack uri
		}
		break;
	case 'feedback':
		switch ($_SERVER['REQUEST_METHOD']) {
			case 'PUT': putFeedback(); break; // update mappool feedback
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
			case 'DELETE': deleteOsuGame(); break; // delete a bracket reset
		}
		break;
	case 'counts':
		switch ($_SERVER['REQUEST_METHOD']) {
			case 'PUT': putCounts(); break; // update an osu game
		}
		break;
	case 'pickedBy':
		switch ($_SERVER['REQUEST_METHOD']) {
			case 'PUT': putPickedBy(); break; // update an osu game
		}
		break;
	case 'availability':
		switch ($_SERVER['REQUEST_METHOD']) {
			case 'GET': getAvailability(); break; // returns a list of availabilites for a round
			case 'PUT': putAvailability(); break; // save availability for a round
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

function echoError($message) {
	$response = new stdClass;
	$response->error = '1';
	$response->message = $message;
	echo json_encode($response);
}

function echoSuccess($message) {
	$response = new stdClass;
	$response->error = '0';
	$response->message = $message;
	echo json_encode($response);
}

function echo400($message) {
	http_response_code(400);
	echoError($message);
	exit;
}

function echo401() {
	http_response_code(401);
	echoError('Authentication required');
	exit;
}

function echo403() {
	http_response_code(403);
	echoError('Insufficient permissions');
	exit;
}

function getUser() {
	global $database;

	$user = $database->getUser();
	if (!$user) {
		echo401();
		return;
	}

	echo json_encode($user);
}

function putPlayerDiscordId() {
	global $database;

	$user = $database->getUser();
	if (!$user) {
		echo401();
	}

	if ($database->getScope() != SCOPE::ADMIN) {
		echo403();
	}

	$body = json_decode(file_get_contents('php://input'));

	if (empty($body)) {
		echo400('Value for new Discord ID missing');
	}

	$database->putPlayerDiscordId($_GET['player'], $body->discordId);
	echoSuccess('Discord ID changed');
}

function putPlayerTrivia() {
	global $database;

	$user = $database->getUser();
	if (!$user) {
		echo401();
	}

	if ($database->getScope() != SCOPE::PLAYER) {
		echo403();
	}

	$body = json_decode(file_get_contents('php://input'));

	$database->putPlayerTrivia($user->userId, $body->trivia);
	echoSuccess('Trivia saved');
}

function getRegistrations() {
	global $database;

	$user = $database->getUser();
	if (!$user) {
		echo401();
	}

	if ($database->getScope() != SCOPE::ADMIN) {
		echo403();
	}

	echo json_encode($database->getRegistrations());
}

function putRegistrationDiscordId() {
	global $database;

	$user = $database->getUser();
	if (!$user) {
		echo401();
	}

	if ($database->getScope() != SCOPE::ADMIN) {
		echo403();
	}

	$body = json_decode(file_get_contents('php://input'));

	if (empty($body)) {
		echo400('Value for new Discord ID missing');
	}

	$database->putRegistrationDiscordId($_GET['registration'], $body->idNew);
	echoSuccess('Discord ID changed');
}

function postRegistration() {
	global $database;

	$user = $database->getUser();
	if (!$user) {
		echo401();
	}

	if ($database->getScope() != SCOPE::REGISTRATION) {
		echo403();
	}

	$body = json_decode(file_get_contents('php://input'));

	if (empty($body)) {
		echo400('Value for osu ID missing');
	}

	$database->postRegistration($body->osuId);
	echoSuccess('Registration successfull');
}

function deleteRegistration() {
	global $database;

	$user = $database->getUser();
	if (!$user) {
		echo401();
	}

	if ($database->getScope() == SCOPE::REGISTRATION) {
		$database->deleteRegistration($user->discord->id);
		echoSuccess('Registration deleted');
		return;
	}

	if ($database->getScope() == SCOPE::ADMIN) {
		$database->deleteRegistration($_GET['registration']);
		echoSuccess('Registration deleted');
		return;
	}

	echo403();
}

function getPlayers() {
	global $database;

	if (!isset($_GET['round'])) {
		echo json_encode($database->getPlayers($_GET['tier']));
		return;
	}

	echo json_encode($database->getPlayers($_GET['tier'], $_GET['round']));
}

function getRounds() {
	global $database;
	echo json_encode($database->getRounds());
}

function postRound() {
	global $database;

	$user = $database->getUser();
	if (!$user) {
		echo401();
	}

	if ($database->getScope() != SCOPE::ADMIN) {
		echo403();
	}

	$body = json_decode(file_get_contents('php://input'));

	$roundId = $database->postRound($body->name, $body->lobbySize, $body->bestOf, $body->isFirstRound, $body->playerAmount, $body->isStartRound, $body->hasContinue, $body->continueAmount, $body->continueRoundId, $body->hasDropDown, $body->dropDownAmount, $body->dropDownRoundId, $body->hasElimination, $body->eliminatedAmount, $body->hasBracketReset, $body->mappoolsReleased, $body->lobbiesReleased, $body->copyMappool, $body->copyMappoolFrom);

	$database->putRoundTimes($roundId, $body->times);

	echoSuccess('Round saved');
}

function putRound() {
	global $database;

	$user = $database->getUser();
	if (!$user) {
		echo401();
	}

	if ($database->getScope() != SCOPE::ADMIN) {
		echo403();
	}

	$body = json_decode(file_get_contents('php://input'));

	$database->putRound($_GET['round'], $body->name, $body->lobbySize, $body->bestOf, $body->isFirstRound, $body->playerAmount, $body->isStartRound, $body->hasContinue, $body->continueAmount, $body->continueRoundId, $body->hasDropDown, $body->dropDownAmount, $body->dropDownRoundId, $body->hasElimination, $body->eliminatedAmount, $body->hasBracketReset, $body->mappoolsReleased, $body->lobbiesReleased, $body->copyMappool, $body->copyMappoolFrom);

	$database->putRoundTimes($_GET['round'], $body->times);

	echoSuccess('Round saved');
}

function deleteRound() {
	global $database;

	$user = $database->getUser();
	if (!$user) {
		echo401();
	}

	if ($database->getScope() != SCOPE::ADMIN) {
		echo403();
	}

	$database->deleteRound($_GET['round']);

	echoSuccess('Round deleted');
}

function getTiers() {
	global $database;

	echo json_encode($database->getTiers());
}

function postTier() {
	global $database;

	$user = $database->getUser();
	if (!$user) {
		echo401();
	}

	if ($database->getScope() != SCOPE::ADMIN) {
		echo403();
	}

	$body = json_decode(file_get_contents('php://input'));

	$tiers = $database->getTiers();

	foreach ($tiers as $tier) {
		if (($body->lowerEndpoint >= $tier->lowerEndpoint && $body->lowerEndpoint <= $tier->upperEndpoint) || ($body->upperEndpoint >= $tier->lowerEndpoint && $body->upperEndpoint <= $tier->upperEndpoint)) {
			echoError('Ranks are overlapping with existing tiers');
			return;
		}
	}

	$database->postTier($body->name, $body->lowerEndpoint, $body->upperEndpoint, $body->startingRound, $body->selectedSeeding, $body->subBonus);

	echoSuccess('Tier saved');
}

function putTier() {
	global $database;

	$user = $database->getUser();
	if (!$user) {
		echo401();
	}

	if ($database->getScope() != SCOPE::ADMIN) {
		echo403();
	}

	$tiers = $database->getTiers();

	foreach ($tiers as $tier) {
		if ($tier->id != $_GET['tier'] && (($body->lowerEndpoint >= $tier->lowerEndpoint && $body->lowerEndpoint <= $tier->upperEndpoint) || ($body->upperEndpoint >= $tier->lowerEndpoint && $body->upperEndpoint <= $tier->upperEndpoint))) {
			echoError('Ranks are overlapping with existing tiers');
			return;
		}
	}

	$body = json_decode(file_get_contents('php://input'));

	$database->putTier($_GET['tier'], $body->name, $body->lowerEndpoint, $body->upperEndpoint, $body->startingRound, $body->selectedSeeding, $body->subBonus);

	echoSuccess('Tier saved');
}

function deleteTier() {
	global $database;

	$user = $database->getUser();
	if (!$user) {
		echo401();
	}

	if ($database->getScope() != SCOPE::ADMIN) {
		echo403();
	}

	$database->deleteTier($_GET['tier']);

	echoSuccess('Tier deleted');
}

function getLobbies() {
	global $database;

	$scope = $database->getScope();
	if ($scope != SCOPE::ADMIN) {
		$rounds = $database->getRounds();
		$accessGranted = false;
		foreach ($rounds as $round) {
			if ($round->id == $_GET['round'] && $round->lobbiesReleased == '1') {
				$accessGranted = true;
				break;
			}
		}
		if (!$accessGranted) {
			echo403();
		}
	}

	$lobbies = $database->getLobbies($_GET['tier'], $_GET['round']);

	if ($scope != SCOPE::ADMIN) {
		foreach ($lobbies as &$lobby) {
			foreach ($lobby->slots as &$slot) {
				unset($slot->availabilities);
			}
		}
	}

	echo json_encode($lobbies);
}

function putLobbies() {
	global $database;

	$user = $database->getUser();
	if (!$user) {
		echo401();
	}

	if ($database->getScope() != SCOPE::ADMIN) {
		echo403();
	}

	$body = json_decode(file_get_contents('php://input'));

	foreach ($body->lobbies as $lobby) {
		$database->putLobbyTime($lobby->id, $lobby->matchTime);
		foreach ($lobby->slots as $slot) {
			$database->putLobbySlot($slot->id, $slot->userId);
			if (!empty($slot->userId)) {
				$database->putPlayerLobby($slot->userId, $lobby->id);
			}
		}
	}

	echoSuccess('Lobbies saved');
}

function postLobbies() {
	global $database;

	$user = $database->getUser();
	if (!$user) {
		echo401();
	}

	if ($database->getScope() != SCOPE::ADMIN) {
		echo403();
	}

	$lobbies = $database->getLobbies($_GET['tier'], $_GET['round']);
	if (count($lobbies) > 0) {
		echo400('There are already existing lobbies');
	}

	$round = $database->getRound($_GET['round']);
	if (!$round) {
		echo400('Round ID not found');
	}

	for ($i = 0; $i < ((int)$round->playerAmount / (int)$round->lobbySize); $i++) {
		$lobbyId = $database->postLobby($_GET['tier'], $_GET['round']);
		for ($j = 0; $j < (int)$round->lobbySize; $j++) {
			$database->postLobbySlot($lobbyId);
		}
	}

	echoSuccess('Lobbies created');
}

function deleteLobbies() {
	global $database;

	$user = $database->getUser();
	if (!$user) {
		echo401();
	}

	if ($database->getScope() != SCOPE::ADMIN) {
		echo403();
	}

	$database->deleteLobbies($_GET['tier'], $_GET['round']);

	echoSuccess('Lobbies deleted');
}

function getLobby() {
	global $database;

	$lobby = $database->getLobby($_GET['lobby']);

	$scope = $database->getScope();
	if ($scope != SCOPE::ADMIN) {
		$round = $database->getRound($lobby->round);
		if ($round->lobbiesReleased != '1') {
			echo403();
		}
		foreach ($lobby->slots as &$slot) {
			unset($slot->availabilities);
		}
	}

	echo json_encode($lobby);
}

function putBans() {
	global $database;

	$user = $database->getUser();
	if (!$user) {
		echo401();
	}

	if ($database->getScope() != SCOPE::ADMIN) {
		echo403();
	}

	$body = json_decode(file_get_contents('php://input'));

	$database->putBans($_GET['lobby'], $body);

	echoSuccess('Bans saved');
}

function putMatchId() {
	global $database;

	$user = $database->getUser();
	if (!$user) {
		echo401();
	}

	if ($database->getScope() != SCOPE::REFEREE) {
		echo403();
	}

	$body = json_decode(file_get_contents('php://input'));

	$database->putLobby($_GET['lobby'], '', $body->matchId);

	echoSuccess('Match ID saved');
}

function putComment() {
	global $database;

	$user = $database->getUser();
	if (!$user) {
		echo401();
	}

	if ($database->getScope() != SCOPE::REFEREE) {
		echo403();
	}

	$body = file_get_contents('php://input');

	$database->putLobby($_GET['lobby'], '', '', $body->comment);

	echoSuccess('Comment saved');
}

function putResult() {
	global $database;

	$user = $database->getUser();
	if (!$user) {
		echo401();
	}

	if ($database->getScope() != SCOPE::REFEREE) {
		echo403();
	}

	$lobby = $database->getLobby($_GET['lobby']);
	$players = $database->getPlayers($lobby->tier);
	foreach ($lobby->slots as $slot) {
		foreach ($players as $player) {
			if ($player->userId == $slot->userId && $player->currentLobby != $_GET['lobby']) {
				echo400('Some players already moved to another round');
			}
		}
	}

	$body = json_decode(file_get_contents('php://input'));

	$database->putResult($_GET['lobby'], $body);

	echoSuccess('Results saved');
}

function getMappool() {
	global $database;

	$user = $database->getUser();
	$scope = $database->getScope();

	if (!isset($_GET['mappool'])) {
		$mappoolId = $database->getMappoolId($_GET['tier'], $_GET['round']);
	} else {
		$mappoolId = $_GET['mappool'];
	}

	$mappool = $database->getMappool($mappoolId);

	if ($scope != SCOPE::ADMIN && $scope != SCOPE::HEADPOOLER && $scope != SCOPE::MAPPOOLER) {
		$round = $database->getRound($mappool->round);
		if ($round->mappoolsReleased == 0) {
			echo403();
		}
	}

	if ($scope == SCOPE::MAPPOOLER) {
		if ($user->tier != $mappool->tier) {
			echo403();
		}
	}
	
	echo json_encode($mappool);
}

function putMappoolSlots() {
	global $database;

	$user = $database->getUser();
	$scope = $database->getScope();

	if (!$user) {
		echo401();
	}

	if ($scope != SCOPE::HEADPOOLER && $scope != SCOPE::MAPPOOLER) {
		echo403();
	}

	if (!isset($_GET['mappool'])) {
		$mappoolId = $database->getMappoolId($_GET['tier'], $_GET['round']);
	} else {
		$mappoolId = $_GET['mappool'];
	}

	$mappool = $database->getMappool($mappoolId);

	$body = json_decode(file_get_contents('php://input'));

	if ($scope == SCOPE::MAPPOOLER) {
		if ($user->tier != $mappool->tier) {
			echo403();
		}
	}

	$database->putMappoolSlots($mappool->id, $body);

	echoSuccess('Mappool saved');
}

function putMappack() {
	global $database;

	$user = $database->getUser();
	$scope = $database->getScope();

	if (!$user) {
		echo401();
	}

	if ($scope != SCOPE::HEADPOOLER) {
		echo403();
	}

	if (!isset($_GET['mappool'])) {
		$mappoolId = $database->getMappoolId($_GET['tier'], $_GET['round']);
	} else {
		$mappoolId = $_GET['mappool'];
	}

	$body = json_decode(file_get_contents('php://input'));

	$database->putMappoolMappack($mappoolId, $body->mappack);

	echoSuccess('Mappack URL saved');
}

function putFeedback() {
	global $database;

	$user = $database->getUser();
	if (!$user) {
		echo401();
	}

	if ($database->getScope() != SCOPE::PLAYER) {
		echo403();
	}

	if (!isset($_GET['mappool'])) {
		$mappoolId = $database->getMappoolId($_GET['tier'], $_GET['round']);
	} else {
		$mappoolId = $_GET['mappool'];
	}

	$body = file_get_contents('php://input');

	$database->putMappoolFeedback($mappoolId, $user->userId, $body);

	echoSuccess('Feedback saved');
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

function putCounts() {
	global $database;

	$user = $database->getUser();
	if (!$user) {
		echo401();
	}

	if ($database->getScope() != SCOPE::REFEREE) {
		echo403();
	}

	$body = json_decode(file_get_contents('php://input'));

	$database->putOsuGameCounts($_GET['game'], $body->counts);

	echoSuccess('Game saved');
}

function putPickedBy() {
	global $database;

	$user = $database->getUser();
	if (!$user) {
		echo401();
	}

	if ($database->getScope() != SCOPE::REFEREE) {
		echo403();
	}

	$body = json_decode(file_get_contents('php://input'));

	$database->putOsuGamePickedBy($_GET['game'], $body->pickedBy);

	echoSuccess('Game saved');
}

function postOsuGame() {
	global $database;

	$user = $database->getUser();
	if (!$user) {
		echo401();
	}

	if ($database->getScope() != SCOPE::REFEREE) {
		echo403();
	}

	$body = json_decode(file_get_contents('php://input'));

	$database->postBracketReset($_GET['match'], $body->time);

	echoSuccess('Bracket reset created');
}

function deleteOsuGame() {
	global $database;

	$user = $database->getUser();
	if (!$user) {
		echo401();
	}

	if ($database->getScope() != SCOPE::REFEREE) {
		echo403();
	}

	$body = json_decode(file_get_contents('php://input'));

	$database->deleteBracketReset($_GET['id']);

	echoSuccess('Bracket reset removed');
}

function getAvailability() {
	global $database;

	$user = $database->getUser();
	if (!$user) {
		echo401();
	}

	if ($database->getScope() != SCOPE::PLAYER) {
		echo403();
	}

	echo json_encode($database->getAvailability($user->userId, $_GET['round']));
}

function putAvailability() {
	global $database;

	$user = $database->getUser();
	if (!$user) {
		echo401();
	}

	if ($database->getScope() != SCOPE::PLAYER) {
		echo403();
	}

	$body = json_decode(file_get_contents('php://input'));

	$database->putAvailability($user->userId, $_GET['round'], $body->availabilities);

	echoSuccess('Availability saved');
}

function getSettings() {
	global $database;

	echo json_encode($database->getSettings());
}

function putSettings() {
	global $database;

	$user = $database->getUser();
	if (!$user) {
		echo401();
	}

	if ($database->getScope() != SCOPE::ADMIN) {
		echo403();
	}

	$body = json_decode(file_get_contents('php://input'));

	if (isset($body->registrationsOpen)) {
		$database->putRegistrationSettings($body->registrationsOpen, $body->registrationsFrom, $body->registrationsTo);
	}

	if (isset($body->roleAdmin)) {
		$database->putRoles($body->roleAdmin, $body->roleHeadpooler, $body->roleMappooler, $body->roleReferee, $body->rolePlayer);
	}

	echoSuccess('Settings saved');
}

function getDiscordLogin() {
	global $discordApi;
	echo json_encode(array('uri' => $discordApi->getLoginUri()));
}

function postDiscordLogin() {
	global $database;
	global $discordApi;

	$body = json_decode(file_get_contents('php://input'));
	$user = $discordApi->getUser($body->accessToken);
	$member = $discordApi->getGuildMember($user->id);

	if (!$member) {
		$response = new stdClass;
		$response->error = '1';
		$response->message = 'Unknown Member';
		echo json_encode($response);
		return;
	}

	$roles = $database->getRoles();

	$settings = $database->getSettings();

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

	$registrations = $database->getRegistrations();
	foreach ($registrations as $registration) {
		if ($registration->discordId == $user->id) {
			$possibleRoles[] = 'REGISTRATION';
		}
	}

	$possibleRoles = array_values(array_unique($possibleRoles));

	if (count($possibleRoles) == 1) {
		$token = $database->loginUser($user->id, $possibleRoles[0]);

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
		$token = $database->loginUser($user->id, $body->scope);

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

	echoError('Error when trying to login');
}

function getDiscordRoles() {
	global $database;

	echo json_encode($database->getRoles());
}

function postDiscordRoles() {
	global $database;

	$user = $database->getUser();
	if (!$user) {
		echo401();
	}

	if ($database->getScope() != SCOPE::ADMIN) {
		echo403();
	}

	$database->postRoles();

	echoSuccess('Roles refreshed');
}

function getTwitchLogin() {
	global $twitchApi;
	echo json_encode(array('uri' => $twitchApi->getLoginUri()));
}

function postTwitchLogin() {
	global $database;

	$user = $database->getUser();
	if (!$user) {
		echo401();
	}

	if ($database->getScope() != SCOPE::REGISTRATION) {
		echo403();
	}

	$body = json_decode(file_get_contents('php://input'));

	$twitchId = $database->cacheNewTwitchAccount($body->token);

	$database->putRegistrationTwitchId($user->discord->id, $twitchId);
	echoSuccess('Twitch account linked');
}

function getMappoolers() {
	global $database;

	$user = $database->getUser();
	if (!$user) {
		echo401();
	}

	if ($database->getScope() != SCOPE::ADMIN) {
		echo403();
	}

	echo json_encode($database->getMappoolers());
}

function postMappoolers() {
	global $database;

	$user = $database->getUser();
	if (!$user) {
		echo401();
	}

	if ($database->getScope() != SCOPE::ADMIN) {
		echo403();
	}

	$database->postMappoolers();

	echoSuccess('Mappoolers refreshed');
}

function putMappooler() {
	global $database;

	$user = $database->getUser();
	if (!$user) {
		echo401();
	}

	if ($database->getScope() != SCOPE::ADMIN) {
		echo403();
	}

	$body = json_decode(file_get_contents('php://input'));

	$database->putMappooler($_GET['id'], $body->tier);

	echoSuccess('Mappoolers updated');
}

?>