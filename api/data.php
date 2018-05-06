<?php

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

require_once '../php_classes/vendor/autoload.php';
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

function echoError(Response $response, $message) {
	$responseObject = new stdClass;
	$responseObject->error = '1';
	$responseObject->message = $message;

	return $response->withJson($responseObject);
}

function echoSuccess(Response $response, $message) {
	$responseObject = new stdClass;
	$responseObject->error = '0';
	$responseObject->message = $message;

	return $response->withJson($responseObject);
}

function echo400(Response $response, $message) {
	$response = echoError($response, $message);
	return $response->withStatus(400);
}

function echo401(Response $response) {
	$response = echoError($response, 'Authentication required');
	return $response->withStatus(401);
}

function echo403(Response $response) {
	$response = echoError($response, 'Insufficient permissions');
	return $response->withStatus(403);
}

$app = new \Slim\App;

$app->add(function ($request, $response, $next) {
	$request->registerMediaTypeParser('application/json', function($input) {
		return json_decode($input);
	});

	return $next($request, $response);
});

$app->get('/user', function($request, $response) {
	global $database;

	$user = $database->getUser();
	if (!$user) {
		echo401($response);
		return;
	}

	return $response->withJson($user);
});

$app->put('/registrations/{id}/discordId', function($request, $response, $args) {
	global $database;

	$user = $database->getUser();
	if (!$user) {
		return echo401($response);
	}

	if ($database->getScope() != SCOPE::ADMIN) {
		return echo403($response);
	}

	$body = $request->getParsedBody();

	if (empty($body)) {
		return echo400($response, 'Value for new Discord ID missing');
	}

	$database->putPlayerDiscordId($args['id'], $body->discordId);
	return echoSuccess($response, 'Discord ID changed');
});

$app->get('/registrations', function($request, $response) {
	global $database;

	$user = $database->getUser();
	if (!$user) {
		return echo401($response);
	}

	if ($database->getScope() != SCOPE::ADMIN) {
		return echo403($response);
	}

	return $response->withJson($database->getRegistrations());
});

$app->post('/registrations', function($request, $response) {
	global $database;

	$user = $database->getUser();
	if (!$user) {
		return echo401($response);
	}

	if ($database->getScope() != SCOPE::REGISTRATION) {
		return echo403($response);
	}

	$body = $request->getParsedBody();

	if (empty($body)) {
		return echo400('Value for osu ID missing');
	}

	$database->postRegistration($body->osuId);
	return echoSuccess($response, 'Registration successfull');
});

$app->delete('/registrations/{id}', function($request, $response, $args) {
	global $database;

	$user = $database->getUser();
	if (!$user) {
		return echo401($response);
	}

	if ($database->getScope() != SCOPE::ADMIN) {
		return echo403($response);
	}

	$database->deleteRegistration($args['id']);
	return echoSuccess($response, 'Registration deleted');
});

$app->delete('/registrations', function($request, $response) {
	global $database;

	$user = $database->getUser();
	if (!$user) {
		return echo401($response);
	}

	if ($database->getScope() != SCOPE::REGISTRATION) {
		return echo403($response);
	}

	$database->deleteRegistration($user->discord->id);
	return echoSuccess($response, 'Registration deleted');
});

$app->put('/players/{id}/discordId', function($request, $response, $args) {
	global $database;

	$user = $database->getUser();
	if (!$user) {
		return echo401($response);
	}

	if ($database->getScope() != SCOPE::ADMIN) {
		return echo403($response);
	}

	$body = $request->getParsedBody();

	if (empty($body)) {
		return echo400($response, 'Value for new Discord ID missing');
	}

	$database->putPlayerDiscordId($args['id'], $body);
	return echoSuccess($response, 'Discord ID changed');
});

$app->put('/players/{id}/trivia', function($request, $response, $args) {
	global $database;

	$user = $database->getUser();
	if (!$user) {
		return echo401($response);
	}

	if ($database->getScope() != SCOPE::PLAYER) {
		return echo403($response);
	}

	$body = $request->getParsedBody();

	$database->putPlayerTrivia($user->userId, $body);
	return echoSuccess('Trivia saved');
});

$app->get('/rounds/{round}/tiers/{tier}/players', function($request, $response, $args) {
	global $database;

	$players = $database->getPlayers($args['tier'], $args['round']);

	if ($database->getScope() == SCOPE::ADMIN && isset($args['round'])) {
		foreach ($players as &$player) {
			$player->availabilities = $database->getAvailability($player->userId, $args['round']);
		}
	}

	return $response->withJson($players);
});

$app->get('/tiers/{tier}/players', function($request, $response, $args) {
	global $database;

	$players = $database->getPlayers($args['tier']);

	return $response->withJson($players);
});

$app->get('/players', function($request, $response) {
	global $database;

	$players = $database->getPlayers();

	return $response->withJson($players);
});

$app->get('/rounds', function($request, $response) {
	global $database;
	return $response->withJson($database->getRounds());
});

$app->post('/rounds', function($request, $response) {
	global $database;

	$user = $database->getUser();
	if (!$user) {
		return echo401($response);
	}

	if ($database->getScope() != SCOPE::ADMIN) {
		return echo403($response);
	}

	$body = $request->getParsedBody();

	$roundId = $database->postRound($body->name, $body->lobbySize, $body->bestOf, $body->isFirstRound, $body->playerAmount, $body->isStartRound, $body->hasContinue, $body->continueAmount, $body->continueRoundId, $body->hasDropDown, $body->dropDownAmount, $body->dropDownRoundId, $body->hasElimination, $body->eliminatedAmount, $body->hasBracketReset, $body->mappoolsReleased, $body->lobbiesReleased, $body->copyMappool, $body->copyMappoolFrom);

	$database->putRoundTimes($roundId, $body->times);

	return echoSuccess($response, 'Round saved');
});

$app->put('/rounds/{id}', function($request, $response, $args) {
	global $database;

	$user = $database->getUser();
	if (!$user) {
		return echo401($response);
	}

	if ($database->getScope() != SCOPE::ADMIN) {
		return echo403($response);
	}

	$body = $request->getParsedBody();

	$database->putRound($args['id'], $body->name, $body->lobbySize, $body->bestOf, $body->isFirstRound, $body->playerAmount, $body->isStartRound, $body->hasContinue, $body->continueAmount, $body->continueRoundId, $body->hasDropDown, $body->dropDownAmount, $body->dropDownRoundId, $body->hasElimination, $body->eliminatedAmount, $body->hasBracketReset, $body->mappoolsReleased, $body->lobbiesReleased, $body->copyMappool, $body->copyMappoolFrom);

	$database->putRoundTimes($args['id'], $body->times);

	return echoSuccess($response, 'Round saved');
});

$app->delete('/rounds/{id}', function($request, $response, $args) {
	global $database;

	$user = $database->getUser();
	if (!$user) {
		return echo401($response);
	}

	if ($database->getScope() != SCOPE::ADMIN) {
		return echo403($response);
	}

	$database->deleteRound($args['id']);

	return echoSuccess($response, 'Round deleted');
});

$app->get('/tiers', function($request, $response) {
	global $database;

	return $response->withJson($database->getTiers());
});

$app->post('/tiers', function($request, $response) {
	global $database;

	$user = $database->getUser();
	if (!$user) {
		return echo401($response);
	}

	if ($database->getScope() != SCOPE::ADMIN) {
		return echo403($response);
	}

	$body = $request->getParsedBody();

	$tiers = $database->getTiers();

	foreach ($tiers as $tier) {
		if (($body->lowerEndpoint >= $tier->lowerEndpoint && $body->lowerEndpoint <= $tier->upperEndpoint) || ($body->upperEndpoint >= $tier->lowerEndpoint && $body->upperEndpoint <= $tier->upperEndpoint)) {
			return echoError($response, 'Ranks are overlapping with existing tiers');
		}
	}

	$database->postTier($body->name, $body->lowerEndpoint, $body->upperEndpoint, $body->startingRound, $body->selectedSeeding, $body->subBonus);

	return echoSuccess($response, 'Tier saved');
});

$app->put('/tiers/{id}', function($request, $response, $args) {
	global $database;

	$user = $database->getUser();
	if (!$user) {
		return echo401($response);
	}

	if ($database->getScope() != SCOPE::ADMIN) {
		return echo403($response);
	}

	$tiers = $database->getTiers();

	foreach ($tiers as $tier) {
		if ($tier->id != $args['id'] && (($body->lowerEndpoint >= $tier->lowerEndpoint && $body->lowerEndpoint <= $tier->upperEndpoint) || ($body->upperEndpoint >= $tier->lowerEndpoint && $body->upperEndpoint <= $tier->upperEndpoint))) {
			return echoError($response, 'Ranks are overlapping with existing tiers');
		}
	}

	$body = $request->getParsedBody();

	$database->putTier($args['id'], $body->name, $body->lowerEndpoint, $body->upperEndpoint, $body->startingRound, $body->selectedSeeding, $body->subBonus);

	return echoSuccess($response, 'Tier saved');
});

$app->delete('/tiers/{id}', function($request, $response, $args) {
	global $database;

	$user = $database->getUser();
	if (!$user) {
		return echo401($response);
	}

	if ($database->getScope() != SCOPE::ADMIN) {
		return echo403($response);
	}

	$database->deleteTier($args['id']);

	return echoSuccess($response, 'Tier deleted');
});

$app->get('/rounds/{round}/tiers/{tier}/lobbies', function($request, $response, $args) {
	global $database;

	$scope = $database->getScope();
	if ($scope != SCOPE::ADMIN) {
		$rounds = $database->getRounds();
		$accessGranted = false;
		foreach ($rounds as $round) {
			if ($round->id == $args['round'] && $round->lobbiesReleased == '1') {
				$accessGranted = true;
				break;
			}
		}
		if (!$accessGranted) {
			return echo403($response);
		}
	}

	$lobbies = $database->getLobbies($args['round'], $args['tier']);

	if ($scope != SCOPE::ADMIN) {
		foreach ($lobbies as &$lobby) {
			foreach ($lobby->slots as &$slot) {
				unset($slot->availabilities);
			}
		}
	}

	return $response->withJson($lobbies);
});

$app->get('/rounds/{round}/lobbies', function($request, $response, $args) {
	global $database;

	$scope = $database->getScope();
	if ($scope != SCOPE::ADMIN) {
		$rounds = $database->getRounds();
		$accessGranted = false;
		foreach ($rounds as $round) {
			if ($round->id == $args['round'] && $round->lobbiesReleased == '1') {
				$accessGranted = true;
				break;
			}
		}
		if (!$accessGranted) {
			echo403();
		}
	}

	$lobbies = $database->getLobbies($args['round']);

	if ($scope != SCOPE::ADMIN) {
		foreach ($lobbies as &$lobby) {
			foreach ($lobby->slots as &$slot) {
				unset($slot->availabilities);
			}
		}
	}

	return $response->withJson($lobbies);
});

$app->put('/rounds/{round}/tiers/{tier}/lobbies', function($request, $response, $args) {
	global $database;

	$user = $database->getUser();
	if (!$user) {
		return echo401($response);
	}

	if ($database->getScope() != SCOPE::ADMIN) {
		return echo403($response);
	}

	$body = $request->getParsedBody();

	foreach ($body->lobbies as $lobby) {
		$database->putLobbyTime($lobby->id, $lobby->matchTime);
		foreach ($lobby->slots as $slot) {
			$database->putLobbySlot($slot->id, $slot->userId);
			if (!empty($slot->userId)) {
				$database->putPlayerLobby($slot->userId, $lobby->id);
			}
		}
	}

	return echoSuccess($response, 'Lobbies saved');
});

$app->post('/rounds/{round}/tiers/{tier}/lobbies', function($request, $response, $args) {
	global $database;

	$user = $database->getUser();
	if (!$user) {
		return echo401($response);
	}

	if ($database->getScope() != SCOPE::ADMIN) {
		return echo403($response);
	}

	$lobbies = $database->getLobbies($args['tier'], $args['round']);
	if (count($lobbies) > 0) {
		return echo400($response, 'There are already existing lobbies');
	}

	$round = $database->getRound($args['round']);
	if (!$round) {
		return echo400($response, 'Round ID not found');
	}

	for ($i = 0; $i < ((int)$round->playerAmount / (int)$round->lobbySize); $i++) {
		$lobbyId = $database->postLobby($args['tier'], $args['round']);
		for ($j = 0; $j < (int)$round->lobbySize; $j++) {
			$database->postLobbySlot($lobbyId);
		}
	}

	return echoSuccess($response, 'Lobbies created');
});

$app->delete('/rounds/{round}/tiers/{tier}/lobbies', function($request, $response, $args) {
	global $database;

	$user = $database->getUser();
	if (!$user) {
		return echo401($response);
	}

	if ($database->getScope() != SCOPE::ADMIN) {
		return echo403($response);
	}

	$database->deleteLobbies($args['tier'], $args['round']);

	return echoSuccess($response, 'Lobbies deleted');
});

$app->get('/lobbies/{id}', function($request, $response, $args) {
	global $database;

	$lobby = $database->getLobby($args['id']);

	$scope = $database->getScope();
	if ($scope != SCOPE::ADMIN) {
		$round = $database->getRound($lobby->round);
		if ($round->lobbiesReleased != '1') {
			return echo403($response);
		}
		foreach ($lobby->slots as &$slot) {
			unset($slot->availabilities);
		}
	}

	return $response->withJson($lobby);
});

$app->put('/lobbies/{id}/bans', function($request, $response, $args) {
	global $database;

	$user = $database->getUser();
	if (!$user) {
		return echo401($response);
	}

	if ($database->getScope() != SCOPE::REFEREE) {
		return echo403($response);
	}

	$body = $request->getParsedBody();

	$database->putBans($args['id'], $body);

	return echoSuccess($response, 'Bans saved');
});

$app->put('/lobbies/{id}/matchId', function($request, $response, $args) {
	global $database;

	$user = $database->getUser();
	if (!$user) {
		return echo401($response);
	}

	if ($database->getScope() != SCOPE::REFEREE) {
		return echo403($response);
	}

	$body = $request->getParsedBody();

	$database->putLobbyMatchId($args['id'], $body);

	return echoSuccess($response, 'Match ID saved');
});

$app->put('/lobbies/{id}/comment', function($request, $response, $args) {
	global $database;

	$user = $database->getUser();
	if (!$user) {
		return echo401($response);
	}

	if ($database->getScope() != SCOPE::REFEREE) {
		return echo403($response);
	}

	$body = $request->getParsedBody();

	$database->putLobbyComment($args['id'], $body);

	return echoSuccess($response, 'Comment saved');
});

$app->put('/lobbies/{id}/result', function($request, $response, $args) {
	global $database;

	$user = $database->getUser();
	if (!$user) {
		return echo401($response);
	}

	if ($database->getScope() != SCOPE::REFEREE) {
		return echo403($response);
	}

	$lobby = $database->getLobby($args['id']);
	$players = $database->getPlayers($lobby->tier);
	foreach ($lobby->slots as $slot) {
		foreach ($players as $player) {
			if ($player->userId == $slot->userId && $player->currentLobby != $args['id']) {
				echo400('Some players already moved to another round');
			}
		}
	}

	$body = $request->getParsedBody();

	$database->putResult($_GET['id'], $body);

	return echoSuccess($response, 'Results saved');
});

$app->get('/rounds/{round}/tiers/{tier}/mappool', function($request, $response, $args) {
	global $database;

	$user = $database->getUser();
	$scope = $database->getScope();

	$mappoolId = $database->getMappoolId($args['tier'], $args['round']);

	$mappool = $database->getMappool($mappoolId);

	if ($scope != SCOPE::ADMIN && $scope != SCOPE::HEADPOOLER && $scope != SCOPE::MAPPOOLER) {
		$round = $database->getRound($mappool->round);
		if ($round->mappoolsReleased == 0) {
			return echo403($response);
		}
		$feedback = new stdClass;
		if ($scope == SCOPE::PLAYER) {
			foreach ($mappool->feedback as $feedbackItem) {
				if ($feedbackItem->discord->id == $user->discord->id) {
					$feedback = $feedbackItem->feedback;
				}
			}
		}
		$mappool->feedback = $feedback;
	}

	if ($scope == SCOPE::MAPPOOLER) {
		if ($user->tier != $mappool->tier) {
			return echo403($response);
		}
	}
	
	return $response->withJson($mappool);
});

$app->get('/mappools/{id}', function($request, $response, $args) {
	global $database;

	$user = $database->getUser();
	$scope = $database->getScope();

	$mappool = $database->getMappool($args['id']);

	if ($scope != SCOPE::ADMIN && $scope != SCOPE::HEADPOOLER && $scope != SCOPE::MAPPOOLER) {
		$round = $database->getRound($mappool->round);
		if ($round->mappoolsReleased == 0) {
			return echo403($response);
		}
		$feedback = new stdClass;
		if ($scope == SCOPE::PLAYER) {
			foreach ($mappool->feedback as $feedbackItem) {
				if ($feedbackItem->discord->id == $user->discord->id) {
					$feedback = $feedbackItem->feedback;
				}
			}
		}
		$mappool->feedback = $feedback;
	}

	if ($scope == SCOPE::MAPPOOLER) {
		if ($user->tier != $mappool->tier) {
			return echo403($response);
		}
	}
	
	return $response->withJson($mappool);
});

$app->put('/rounds/{round}/tiers/{tier}/mappool/slots', function($request, $response, $args) {
	global $database;

	$user = $database->getUser();
	$scope = $database->getScope();

	if (!$user) {
		return echo401($response);
	}

	if ($scope != SCOPE::HEADPOOLER && $scope != SCOPE::MAPPOOLER) {
		return echo403($response);
	}

	$mappoolId = $database->getMappoolId($args['tier'], $args['round']);

	$mappool = $database->getMappool($mappoolId);

	$body = $request->getParsedBody();

	if ($scope == SCOPE::MAPPOOLER) {
		if ($user->tier != $mappool->tier) {
			return echo403($response);
		}
	}

	$database->putMappoolSlots($mappool->id, $body);

	return echoSuccess($response, 'Mappool saved');
});

$app->put('/mappools/{id}/slots', function($request, $response, $args) {
	global $database;

	$user = $database->getUser();
	$scope = $database->getScope();

	if (!$user) {
		return echo401($response);
	}

	if ($scope != SCOPE::HEADPOOLER && $scope != SCOPE::MAPPOOLER) {
		return echo403($response);
	}

	$mappool = $database->getMappool($args['id']);

	$body = $request->getParsedBody();

	if ($scope == SCOPE::MAPPOOLER) {
		if ($user->tier != $mappool->tier) {
			return echo403($response);
		}
	}

	$database->putMappoolSlots($mappool->id, $body);

	return echoSuccess($response, 'Mappool saved');
});

$app->put('/rounds/{round}/tiers/{tier}/mappool/mappack', function($request, $response, $args) {
	global $database;

	$user = $database->getUser();
	$scope = $database->getScope();

	if (!$user) {
		return echo401($response);
	}

	if ($scope != SCOPE::HEADPOOLER) {
		return echo403($response);
	}

	$mappoolId = $database->getMappoolId($args['tier'], $args['round']);

	$body = $request->getParsedBody();

	$database->putMappoolMappack($mappoolId, $body->mappack);

	return echoSuccess($response, 'Mappack URL saved');
});

$app->put('/mappools/{id}/mappack', function($request, $response, $args) {
	global $database;

	$user = $database->getUser();
	$scope = $database->getScope();

	if (!$user) {
		return echo401($response);
	}

	if ($scope != SCOPE::HEADPOOLER) {
		return echo403($response);
	}

	$body = $request->getParsedBody();

	$database->putMappoolMappack($args['id'], $body->mappack);

	return echoSuccess($response, 'Mappack URL saved');
});

$app->put('/rounds/{round}/tiers/{tier}/mappool/feedback', function($request, $response, $args) {
	global $database;

	$user = $database->getUser();
	if (!$user) {
		return echo401($response);
	}

	if ($database->getScope() != SCOPE::PLAYER) {
		return echo403($response);
	}

	$mappoolId = $database->getMappoolId($args['tier'], $args['round']);

	$body = $request->getParsedBody();

	$database->putMappoolFeedback($mappoolId, $user->userId, $body);

	return echoSuccess($response, 'Feedback saved');
});

$app->put('/mappools/{id}/feedback', function($request, $response, $args) {
	global $database;

	$user = $database->getUser();
	if (!$user) {
		return echo401($response);
	}

	if ($database->getScope() != SCOPE::PLAYER) {
		return echo403($response);
	}

	$body = $request->getParsedBody();

	$database->putMappoolFeedback($args['id'], $user->userId, $body);

	return echoSuccess($response, 'Feedback saved');
});

$app->get('/osuprofile/{id}', function($request, $response, $args) {
	global $osuApi;

	return $response->withJson($osuApi->getUser($args['id']));
});

$app->get('/osubeatmap/{id}', function($request, $response, $args) {
	global $osuApi;

	return $response->withJson($osuApi->getBeatmap($args['id']));
});

$app->get('/osumatch/{id}', function($request, $response, $args) {
	global $osuApi;

	return $response->withJson($osuApi->getMatch($args['id']));
});

$app->post('/osumatch/{id}/games', function($request, $response, $args) {
	global $database;

	$user = $database->getUser();
	if (!$user) {
		return echo401($response);
	}

	if ($database->getScope() != SCOPE::REFEREE) {
		return echo403($response);
	}

	$body = $request->getParsedBody();

	$database->postBracketReset($args['id'], $body->time);

	echoSuccess('Bracket reset created');
});

$app->put('/osugame/{id}/counts', function($request, $response, $args) {
	global $database;

	$user = $database->getUser();
	if (!$user) {
		return echo401($response);
	}

	if ($database->getScope() != SCOPE::REFEREE) {
		return echo403($response);
	}

	$body = $request->getParsedBody();

	$database->putOsuGameCounts($args['id'], $body->counts);

	return echoSuccess($response, 'Game saved');
});

$app->put('/osugame/{id}/pickedby', function($request, $response, $args) {
	global $database;

	$user = $database->getUser();
	if (!$user) {
		return echo401($response);
	}

	if ($database->getScope() != SCOPE::REFEREE) {
		return echo403($response);
	}

	$body = $request->getParsedBody();

	$database->putOsuGamePickedBy($args['id'], $body->pickedBy);

	return echoSuccess($response, 'Game saved');
});

$app->delete('/osugame/{id}', function($request, $response, $args) {
	global $database;

	$user = $database->getUser();
	if (!$user) {
		return echo401($response);
	}

	if ($database->getScope() != SCOPE::REFEREE) {
		return echo403($response);
	}

	$body = $request->getParsedBody();

	$database->deleteBracketReset($args['id']);

	return echoSuccess($response, 'Bracket reset removed');
});

$app->get('/rounds/{id}/availability', function($request, $response, $args) {
	global $database;

	$user = $database->getUser();
	if (!$user) {
		return echo401($response);
	}

	if ($database->getScope() != SCOPE::PLAYER) {
		return echo403($response);
	}

	return $response->withJson($database->getAvailability($user->userId, $args['id']));
});

$app->put('/rounds/{id}/availability', function($request, $response, $args) {
	global $database;

	$user = $database->getUser();
	if (!$user) {
		return echo401($response);
	}

	if ($database->getScope() != SCOPE::PLAYER) {
		return echo403($response);
	}

	$body = $request->getParsedBody();

	$database->putAvailability($user->userId, $args['id'], $body->availabilities);

	return echoSuccess($response, 'Availability saved');
});

$app->get('/settings', function($request, $response) {
	global $database;

	return $response->withJson($database->getSettings());
});

$app->put('/settings', function($request, $response) {
	global $database;

	$user = $database->getUser();
	if (!$user) {
		return echo401($response);
	}

	if ($database->getScope() != SCOPE::ADMIN) {
		return echo403($response);
	}

	$body = $request->getParsedBody();

	if (isset($body->registrationsOpen)) {
		$database->putRegistrationSettings($body->registrationsOpen, $body->registrationsFrom, $body->registrationsTo);
	}

	if (isset($body->roleAdmin)) {
		$database->putRoles($body->roleAdmin, $body->roleHeadpooler, $body->roleMappooler, $body->roleReferee, $body->rolePlayer);
	}

	return echoSuccess($response, 'Settings saved');
});

$app->get('/discordlogin', function($request, $response) {
	global $discordApi;

	return $response->withJson(array('uri' => $discordApi->getLoginUri()));
});

$app->post('/discordlogin', function($request, $response) {
	global $database;
	global $discordApi;

	$body = $request->getParsedBody();
	$user = $discordApi->getUser($body->accessToken);
	$member = $discordApi->getGuildMember($user->id);

	if (!$member) {
		return echoError($response, 'Unknown Member');
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

		$responseObject = new stdClass;
		$responseObject->error = '0';
		$responseObject->message = 'Login successfull';
		$responseObject->token = (string) $token;
		$responseObject->scope = $possibleRoles[0];
		return $response->withJson($responseObject);
	}

	if (isset($body->scope) && in_array($body->scope, $possibleRoles)) {
		$token = $database->loginUser($user->id, $body->scope);

		$responseObject = new stdClass;
		$responseObject->error = '0';
		$responseObject->message = 'Login successfull';
		$responseObject->token = (string) $token;
		$responseObject->scope = $body->scope;
		return $response->withJson($responseObject);
	}

	if (count($possibleRoles) > 1) {
		$responseObject = new stdClass;
		$responseObject->error = '0';
		$responseObject->message = 'Multiple roles possible';
		$responseObject->scopes = $possibleRoles;
		return $response->withJson($responseObject);
	}

	return echoError($response, 'Error when trying to login');
});

$app->get('/discordroles', function($request, $response) {
	global $database;

	return $response->withJson($database->getRoles());
});

$app->post('/discordroles', function($request, $response) {
	global $database;

	$user = $database->getUser();
	if (!$user) {
		return echo401($response);
	}

	if ($database->getScope() != SCOPE::ADMIN) {
		return echo403($response);
	}

	$database->postRoles();

	return echoSuccess($response, 'Roles refreshed');
});

$app->get('/twitchlogin', function($request, $response) {
	global $twitchApi;

	return $response->withJson(array('uri' => $twitchApi->getLoginUri()));
});

$app->post('/twitchlogin', function($request, $response) {
	global $database;

	$user = $database->getUser();
	if (!$user) {
		return echo401($response);
	}

	if ($database->getScope() != SCOPE::REGISTRATION) {
		return echo403($response);
	}

	$body = $request->getParsedBody();

	$twitchId = $database->cacheNewTwitchAccount($body->token);

	$database->putRegistrationTwitchId($user->discord->id, $twitchId);
	return echoSuccess($response, 'Twitch account linked');
});

$app->get('/mappoolers', function($request, $response) {
	global $database;

	$user = $database->getUser();
	if (!$user) {
		return echo401($response);
	}

	if ($database->getScope() != SCOPE::ADMIN) {
		return echo403($response);
	}

	return $response->withJson($database->getMappoolers());
});

$app->post('/mappoolers', function($request, $response) {
	global $database;

	$user = $database->getUser();
	if (!$user) {
		return echo401($response);
	}

	if ($database->getScope() != SCOPE::ADMIN) {
		return echo403($response);
	}

	$database->postMappoolers();

	return echoSuccess($response, 'Mappoolers refreshed');
});

$app->put('/mappoolers/{id}', function($request, $response, $args) {
	global $database;

	$user = $database->getUser();
	if (!$user) {
		return echo401($response);
	}

	if ($database->getScope() != SCOPE::ADMIN) {
		return echo403($response);
	}

	$body = $request->getParsedBody();

	$database->putMappooler($args['id'], $body->tier);

	return echoSuccess($response, 'Mappoolers updated');
});

$app->run();

?>