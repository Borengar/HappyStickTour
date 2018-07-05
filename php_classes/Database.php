<?php

include_once 'OsuApi.php';
include_once 'DiscordApi.php';
include_once 'TwitchApi.php';

abstract class SCOPE {
	const NONE = 0;
	const REGISTRATION = 1;
	const PLAYER = 2;
	const REFEREE = 3;
	const MAPPOOLER = 4;
	const HEADPOOLER = 5;
	const ADMIN = 6;
}

class Database {
	private $db;
	private $scope;
	private $discordId;

	function __construct() {
		$config = parse_ini_file('config.ini');
		$this->db = new PDO('mysql:host=' . $config['databaseHost'] . ';dbname=' . $config['databaseDbname'] . ';charset=utf8', $config['databaseUsername'], $config['databasePassword']);
		$this->db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);

		if (!isset($_SERVER['HTTP_AUTHORIZATION']) || empty($_SERVER['HTTP_AUTHORIZATION'])) {
			$this->scope = SCOPE::NONE;
			return;
		}

		$stmt = $this->db->prepare('SELECT user_id as userId, scope
			FROM bearer_tokens
			WHERE token = :token');
		$stmt->bindValue(':token', $_SERVER['HTTP_AUTHORIZATION'], PDO::PARAM_STR);
		$stmt->execute();
		$row = $stmt->fetch();
		if (!$row) {
			$this->scope = SCOPE::NONE;
			return;
		}
		$this->discordId = $row->userId;
		switch ($row->scope) {
			case 'REGISTRATION': $this->scope = SCOPE::REGISTRATION; break;
			case 'PLAYER': $this->scope = SCOPE::PLAYER; break;
			case 'REFEREE': $this->scope = SCOPE::REFEREE; break;
			case 'MAPPOOLER': $this->scope = SCOPE::MAPPOOLER; break;
			case 'HEADPOOLER': $this->scope = SCOPE::HEADPOOLER; break;
			case 'ADMIN': $this->scope = SCOPE::ADMIN; break;
		}
	}

	public function loginUser($userId, $scope) {
		while (true) {
			$token = random_int(PHP_INT_MIN, PHP_INT_MAX);
			$stmt = $this->db->prepare('SELECT COUNT(*) as rowcount
				FROM bearer_tokens
				WHERE token = :token');
			$stmt->bindValue(':token', $token, PDO::PARAM_STR);
			$stmt->execute();
			$rows = $stmt->fetch();
			if ($rows->rowcount == '0') {
				break;
			}
		}

		$stmt = $this->db->prepare('DELETE FROM bearer_tokens
			WHERE user_id = :user_id');
		$stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
		$stmt->execute();

		$stmt = $this->db->prepare('INSERT INTO bearer_tokens (token, user_id, scope)
			VALUES (:token, :user_id, :scope)');
		$stmt->bindValue(':token', $token, PDO::PARAM_STR);
		$stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
		$stmt->bindValue(':scope', $scope, PDO::PARAM_STR);
		$stmt->execute();

		$this->cacheNewDiscordAccount($userId);

		return $token;
	}

	public function getConnection() {
		return $this->db;
	}

	public function getScope() {
		return $this->scope;
	}

	public function getUser() {
		if ($this->scope == SCOPE::NONE) {
			return FALSE;
		}

		$user = new stdClass;

		// discord profile
		$user->discord = new stdClass;
		$stmt = $this->db->prepare('SELECT id, username, discriminator, avatar
			FROM discord_users
			WHERE id = :id');
		$stmt->bindValue(':id', $this->discordId, PDO::PARAM_INT);
		$stmt->execute();
		$row = $stmt->fetch();
		$user->discord->id = $row->id;
		$user->discord->username = $row->username;
		$user->discord->discriminator = $row->discriminator;
		$user->discord->avatar = $row->avatar;

		// registration data
		if ($this->scope == SCOPE::REGISTRATION) {
			$osuApi = new OsuApi();
			$stmt = $this->db->prepare('SELECT osu_id as osuId, registration_time as registrationTime, twitch_id as twitchId
				FROM registrations
				WHERE id = :id');
			$stmt->bindValue(':id', $this->discordId, PDO::PARAM_INT);
			$stmt->execute();
			$row = $stmt->fetch();
			if ($row->osuId) {
				$user->osu = $osuApi->getUser($row->osuId);
			} else {
				$user->osu = null;
			}
			$user->registrationTime = $row->registrationTime;
			if ($row->twitchId) {
				$stmt = $this->db->prepare('SELECT id, username, display_name as displayName, avatar, sub_since as subSince, sub_plan as subPlan
					FROM twitch_users
					WHERE id = :id');
				$stmt->bindValue(':id', $row->twitchId, PDO::PARAM_INT);
				$stmt->execute();
				$user->twitch = $stmt->fetch();
			} else {
				$user->twitch = null;
			}
			$stmt = $this->db->prepare('SELECT time_slots.id, time_slots.day, time_slots.time
				FROM time_slots INNER JOIN availabilities ON time_slots.id = availabilities.time_slot
				WHERE availabilities.user_id = :user_id');
			$stmt->bindValue(':user_id', $this->discordId, PDO::PARAM_INT);
			$stmt->execute();
			$user->timeslots = $stmt->fetchAll();
		}

		// player data
		if ($this->scope == SCOPE::PLAYER) {
			$osuApi = new OsuApi();
			$stmt = $this->db->prepare('SELECT id as userId, osu_id as osuId, tier, current_lobby as currentLobby, next_round as nextRound, trivia
				FROM players
				WHERE discord_id = :discord_id');
			$stmt->bindValue(':discord_id', $this->discordId, PDO::PARAM_INT);
			$stmt->execute();
			$row = $stmt->fetch();
			$user->userId = $row->userId;
			$user->osu = $osuApi->getUser($row->osuId);
			$user->tier = new stdClass;
			$user->tier->id = $row->tier;
			$user->lobby = new stdClass;
			$user->lobby->id = $row->currentLobby;
			$user->round = new stdClass;
			$user->round->id = $row->nextRound;
			$user->trivia = $row->trivia;

			$stmt = $this->db->prepare('SELECT time_slots.id, time_slots.day, time_slots.time
				FROM time_slots INNER JOIN availabilities ON time_slots.id = availabilities.time_slot
				WHERE availabilities.user_id = :user_id');
			$stmt->bindValue(':user_id', $this->discordId, PDO::PARAM_INT);
			$stmt->execute();
			$user->timeslots = $stmt->fetchAll();
		}

		// mappooler data
		if ($this->scope == SCOPE::MAPPOOLER) {
			$stmt = $this->db->prepare('SELECT tiers.id, tiers.name, tiers.lower_endpoint as lowerEndpoint, tiers.upper_endpoint as upperEndpoint
				FROM mappooler_tiers INNER JOIN tiers ON mappooler_tiers.tier = tiers.id
				WHERE mappooler_tiers.discord_id = :discord_id');
			$stmt->bindValue(':discord_id', $this->discordId, PDO::PARAM_INT);
			$stmt->execute();
			$user->tiers = $stmt->fetchAll();
		}

		return $user;
	}

	public function getPlayers($tierId = 0, $roundId = 0) {
		$osuApi = new OsuApi();
		$players = [];

		if ($roundId != 0) {
			$stmt = $this->db->prepare('SELECT players.id as userId, players.osu_id as osuId, players.current_lobby as currentLobby, discord_users.id as discordId, discord_users.username as discordUsername, discord_users.discriminator as discordDiscriminator, discord_users.avatar as discordAvatar, tiers.id as tierId, tiers.name as tierName, players.role_set as roleSet
				FROM players INNER JOIN discord_users ON players.discord_id = discord_users.id INNER JOIN tiers ON players.tier = tiers.id
				WHERE tiers.id = :tier AND next_round = :next_round');
			$stmt->bindValue(':tier', $tierId, PDO::PARAM_INT);
			$stmt->bindValue(':next_round', $roundId, PDO::PARAM_INT);
		} elseif ($tierId != 0) {
			$stmt = $this->db->prepare('SELECT players.id as userId, players.osu_id as osuId, players.current_lobby as currentLobby, discord_users.id as discordId, discord_users.username as discordUsername, discord_users.discriminator as discordDiscriminator, discord_users.avatar as discordAvatar, tiers.id as tierId, tiers.name as tierName, players.role_set as roleSet
				FROM players INNER JOIN discord_users ON players.discord_id = discord_users.id INNER JOIN tiers ON players.tier = tiers.id
				WHERE tiers.id = :tier');
			$stmt->bindValue(':tier', $tierId, PDO::PARAM_INT);
		} else {
			$stmt = $this->db->prepare('SELECT players.id as userId, players.osu_id as osuId, players.current_lobby as currentLobby, discord_users.id as discordId, discord_users.username as discordUsername, discord_users.discriminator as discordDiscriminator, discord_users.avatar as discordAvatar, tiers.id as tierId, tiers.name as tierName, players.role_set as roleSet
				FROM players INNER JOIN discord_users ON players.discord_id = discord_users.id INNER JOIN tiers ON players.tier = tiers.id');
		}
		$stmt->execute();
		$rows = $stmt->fetchAll();
		foreach ($rows as $row) {
			$player = new stdClass;
			$player->userId = $row->userId;
			$player->currentLobby = $row->currentLobby;

			$player->discord = new stdClass;
			$player->discord->id = $row->discordId;
			$player->discord->username = $row->discordUsername;
			$player->discord->discriminator = $row->discordDiscriminator;
			$player->discord->avatar = $row->discordAvatar;

			$player->osu = $osuApi->getUser($row->osuId);

			$player->tier = new stdClass;
			$player->tier->id = $row->tierId;
			$player->tier->name = $row->tierName;

			$player->roleSet = $row->roleSet;
			$players[] = $player;
		}

		return $players;
	}

	public function putPlayerDiscordId($userId, $discordId) {
		$stmt = $this->db->prepare('UPDATE players
			SET discord_id = :discord_id
			WHERE id = :id');
		$stmt->bindValue(':discord_id', $discordId, PDO::PARAM_INT);
		$stmt->bindValue(':id', $userId, PDO::PARAM_INT);
		$stmt->execute();
		$this->cacheNewDiscordAccount($discordId);
	}

	public function putPlayerTrivia($userId, $trivia) {
		$stmt = $this->db->prepare('UPDATE players
			SET trivia = :trivia
			WHERE id = :id');
		$stmt->bindValue(':trivia', $trivia, PDO::PARAM_STR);
		$stmt->bindValue(':id', $userId, PDO::PARAM_INT);
		$stmt->execute();
	}

	public function putPlayerLobby($userId, $lobbyId) {
		$stmt = $this->db->prepare('UPDATE players
			SET current_lobby = :current_lobby
			WHERE id = :id');
		$stmt->bindValue(':current_lobby', $lobbyId, PDO::PARAM_INT);
		$stmt->bindValue(':id', $userId, PDO::PARAM_INT);
		$stmt->execute();
	}

	public function putPlayerRoleSet($userId, $roleSet) {
		$stmt = $this->db->prepare('UPDATE players
			SET role_set = :role_set
			WHERE id = :id');
		$stmt->bindValue(':role_set', $roleSet, PDO::PARAM_BOOL);
		$stmt->bindValue(':id', $userId, PDO::PARAM_INT);
		$stmt->execute();
	}

	public function getRegistrations() {
		$osuApi = new OsuApi();
		$registrations = [];

		$stmt = $this->db->prepare('SELECT registrations.osu_id as osuId, registrations.registration_time as registrationTime, registrations.donator as donator, osu_users.username as osuUsername, osu_users.avatar_url as osuAvatarUrl, osu_users.hit_accuracy as osuHitAccuracy, osu_users.level as osuLevel, osu_users.play_count as osuPlayCount, osu_users.pp as osuPp, osu_users.rank as osuRank, osu_users.rank_history as osuRankHistory, osu_users.best_score as osuBestScore, osu_users.playstyle as osuPlaystyle, osu_users.join_date as osuJoinDate, osu_users.country as osuCountry, registrations.twitch_id as twitchId, twitch_users.username as twitchUsername, twitch_users.display_name as twitchDisplayName, twitch_users.avatar as twitchAvatar, twitch_users.sub_since as twitchSubSince, twitch_users.sub_plan as twitchSubPlan, discord_users.id as discordId, discord_users.username as discordUsername, discord_users.discriminator as discordDiscriminator, discord_users.avatar as discordAvatar
			FROM registrations INNER JOIN osu_users ON registrations.osu_id = osu_users.id INNER JOIN discord_users ON registrations.id = discord_users.id LEFT JOIN twitch_users ON registrations.twitch_id = twitch_users.id
			ORDER BY registrations.registration_time ASC');
		$stmt->execute();
		$rows = $stmt->fetchAll();
		foreach ($rows as $row) {
			$registration = new stdClass;
			$registration->time = $row->registrationTime;
			$registration->donator = (bool) $row->donator;

			$registration->discord = new stdClass;
			$registration->discord->id = $row->discordId;
			$registration->discord->username = $row->discordUsername;
			$registration->discord->discriminator = $row->discordDiscriminator;
			$registration->discord->avatar = $row->discordAvatar;

			$registration->osu = new stdClass;
			$registration->osu->id = $row->osuId;
			$registration->osu->username = $row->osuUsername;
			$registration->osu->avatarUrl = $row->osuAvatarUrl;
			$registration->osu->hitAccuracy = $row->osuHitAccuracy;
			$registration->osu->level = $row->osuLevel;
			$registration->osu->playCount = $row->osuPlayCount;
			$registration->osu->pp = $row->osuPp;
			$registration->osu->rank = $row->osuRank;
			$registration->osu->rankHistory = $row->osuRankHistory;
			$registration->osu->bestScore = $row->osuBestScore;
			$registration->osu->playstyle = $row->osuPlaystyle;
			$registration->osu->joinDate = $row->osuJoinDate;
			$registration->osu->country = $row->osuCountry;

			if (!empty($row->twitchId)) {
				$registration->twitch = new stdClass;
				$registration->twitch->id = $row->twitchId;
				$registration->twitch->username = $row->twitchUsername;
				$registration->twitch->displayName = $row->twitchDisplayName;
				$registration->twitch->avatar = $row->twitchAvatar;
				$registration->twitch->subSince = $row->twitchSubSince;
				$registration->twitch->subPlan = $row->twitchSubPlan;
			}

			$registrations[] = $registration;
		}

		return $registrations;
	}

	public function putRegistrationDiscordId($discordIdOld, $discordIdNew) {
		$stmt = $this->db->prepare('UPDATE registrations
			SET id = :id_new
			WHERE id = :id_old');
		$stmt->bindValue(':id_new', $discordIdNew, PDO::PARAM_INT);
		$stmt->bindValue(':id_old', $discordIdOld, PDO::PARAM_INT);
		$stmt->execute();
		$this->cacheNewDiscordAccount($discordIdNew);
	}

	public function putRegistrationDonator($discordId, $donator) {
		$stmt = $this->db->prepare('UPDATE registrations
			SET donator = :donator
			WHERE id = :id');
		$stmt->bindValue(':donator', $donator, PDO::PARAM_INT);
		$stmt->bindValue(':id', $discordId, PDO::PARAM_INT);
		$stmt->execute();
	}

	public function putRegistrationTwitchId($discordId, $twitchId) {
		$stmt = $this->db->prepare('UPDATE registrations
			SET twitch_id = :twitch_id
			WHERE id = :id');
		$stmt->bindValue(':twitch_id', $twitchId, PDO::PARAM_INT);
		$stmt->bindValue(':id', $discordId, PDO::PARAM_INT);
		$stmt->execute();
	}

	public function postRegistration($osuId, $availabilities) {
		$stmt = $this->db->prepare('INSERT INTO registrations (id, osu_id, registration_time)
			VALUES (:id, :osu_id, :registration_time)');
		$stmt->bindValue(':id', $this->discordId, PDO::PARAM_INT);
		$stmt->bindValue(':osu_id', $osuId, PDO::PARAM_INT);
		$stmt->bindValue(':registration_time', gmdate('Y-m-d H:i:s'));
		$stmt->execute();

		$stmt = $this->db->prepare('DELETE FROM availabilities
			WHERE user_id = :user_id');
		$stmt->bindValue(':user_id', $this->discordId, PDO::PARAM_INT);
		$stmt->execute();

		foreach ($availabilities as $availability) {
			$stmt = $this->db->prepare('INSERT INTO availabilities (user_id, time_slot)
				VALUES (:user_id, :time_slot)');
			$stmt->bindValue(':user_id', $this->discordId, PDO::PARAM_INT);
			$stmt->bindValue(':time_slot', $availability->id, PDO::PARAM_INT);
			$stmt->execute();
		}
	}

	public function putRegistration($availabilities) {
		$stmt = $this->db->prepare('DELETE FROM availabilities
			WHERE user_id = :user_id');
		$stmt->bindValue(':user_id', $this->discordId, PDO::PARAM_INT);
		$stmt->execute();

		foreach ($availabilities as $availability) {
			$stmt = $this->db->prepare('INSERT INTO availabilities (user_id, time_slot)
				VALUES (:user_id, :time_slot)');
			$stmt->bindValue(':user_id', $this->discordId, PDO::PARAM_INT);
			$stmt->bindValue(':time_slot', $availability->id, PDO::PARAM_INT);
			$stmt->execute();
		}
	}

	public function deleteRegistration($discordId) {
		$stmt = $this->db->prepare('DELETE FROM registrations
			WHERE id = :id');
		$stmt->bindValue(':id', $discordId, PDO::PARAM_INT);
		$stmt->execute();
	}

	public function getRounds() {
		$stmt = $this->db->prepare('SELECT id, name, lobby_size as lobbySize, best_of as bestOf, is_first_round as isFirstRound, player_amount as playerAmount, is_start_round as isStartRound, has_continue as hasContinue, continue_amount as continueAmount, continue_round as continueRound, has_drop_down as hasDropDown, drop_down_amount as dropDownAmount, drop_down_round as dropDownRound, has_elimination as hasElimination, eliminated_amount as eliminatedAmount, has_bracket_reset as hasBracketReset, mappools_released as mappoolsReleased, lobbies_released as lobbiesReleased, copy_mappool as copyMappool, copy_mappool_from as copyMappoolFrom
			FROM rounds
			ORDER BY id ASC');
		$stmt->execute();
		$rounds = $stmt->fetchAll();
		foreach ($rounds as $round) {
			$stmt = $this->db->prepare('SELECT time_from as `from`, time_to as `to`
				FROM round_times
				WHERE round = :round');
			$stmt->bindValue(':round', $round->id, PDO::PARAM_INT);
			$stmt->execute();
			$round->times = $stmt->fetchAll();
		}
		return $rounds;
	}

	public function getRound($roundId) {
		$stmt = $this->db->prepare('SELECT id, name, lobby_size as lobbySize, best_of as bestOf, is_first_round as isFirstRound, player_amount as playerAmount, is_start_round as isStartRound, has_continue as hasContinue, continue_amount as continueAmount, continue_round as continueRound, has_drop_down as hasDropDown, drop_down_amount as dropDownAmount, drop_down_round as dropDownRound, has_elimination as hasElimination, eliminated_amount as eliminatedAmount, has_bracket_reset as hasBracketReset, mappools_released as mappoolsReleased, lobbies_released as lobbiesReleased, copy_mappool as copyMappool, copy_mappool_from as copyMappoolFrom
			FROM rounds
			WHERE id = :id');
		$stmt->bindValue(':id', $roundId, PDO::PARAM_INT);
		$stmt->execute();
		$round = $stmt->fetch();

		$stmt = $this->db->prepare('SELECT time_from as `from`, time_to as `to`
			FROM round_times
			WHERE round = :round');
		$stmt->bindValue(':round', $round->id, PDO::PARAM_INT);
		$stmt->execute();
		$round->times = $stmt->fetchAll();

		return $round;
	}

	public function postRound($name, $lobbySize, $bestOf, $isFirstRound, $playerAmount, $isStartRound, $hasContinue, $continueAmount, $continueRoundId, $hasDropDown, $dropDownAmount, $dropDownRoundId, $hasElimination, $eliminatedAmount, $hasBracketReset, $mappoolsReleased, $lobbiesReleased, $copyMappool, $copyMappoolFrom) {
		$stmt = $this->db->prepare('INSERT INTO rounds (name, lobby_size, best_of, is_first_round, player_amount, is_start_round, has_continue, continue_amount, continue_round, has_drop_down, drop_down_amount, drop_down_round, has_elimination, eliminated_amount, has_bracket_reset, mappools_released, lobbies_released, copy_mappool, copy_mappool_from)
			VALUES (:name, :lobby_size, :best_of, :is_first_round, :player_amount, :is_start_round, :has_continue, :continue_amount, :continue_round, :has_drop_down, :drop_down_amount, :drop_down_round, :has_elimination, :eliminated_amount, :has_bracket_reset, :mappools_released, :lobbies_released, :copy_mappool, :copy_mappool_from)');
		$stmt->bindValue(':name', $name, PDO::PARAM_STR);
		$stmt->bindValue(':lobby_size', $lobbySize, PDO::PARAM_INT);
		$stmt->bindValue(':best_of', $bestOf, PDO::PARAM_INT);
		$stmt->bindValue(':is_first_round', $isFirstRound, PDO::PARAM_BOOL);
		$stmt->bindValue(':player_amount', $playerAmount, PDO::PARAM_INT);
		$stmt->bindValue(':is_start_round', $isStartRound, PDO::PARAM_BOOL);
		$stmt->bindValue(':has_continue', $hasContinue, PDO::PARAM_BOOL);
		$stmt->bindValue(':continue_amount', $continueAmount, PDO::PARAM_INT);
		$stmt->bindValue(':continue_round', $continueRoundId, PDO::PARAM_INT);
		$stmt->bindValue(':has_drop_down', $hasDropDown, PDO::PARAM_BOOL);
		$stmt->bindValue(':drop_down_amount', $dropDownAmount, PDO::PARAM_INT);
		$stmt->bindValue(':drop_down_round', $dropDownRoundId, PDO::PARAM_INT);
		$stmt->bindValue(':has_elimination', $hasElimination, PDO::PARAM_BOOL);
		$stmt->bindValue(':eliminated_amount', $eliminatedAmount, PDO::PARAM_INT);
		$stmt->bindValue(':has_bracket_reset', $hasBracketReset, PDO::PARAM_BOOL);
		$stmt->bindValue(':mappools_released', $mappoolsReleased, PDO::PARAM_BOOL);
		$stmt->bindValue(':lobbies_released', $lobbiesReleased, PDO::PARAM_BOOL);
		$stmt->bindValue(':copy_mappool', $copyMappool, PDO::PARAM_BOOL);
		$stmt->bindValue(':copy_mappool_from', $copyMappoolFrom, PDO::PARAM_INT);
		$stmt->execute();

		$this->recalculateRound();

		return $this->db->lastInsertId();
	}

	public function putRound($roundId, $name, $lobbySize, $bestOf, $isFirstRound, $playerAmount, $isStartRound, $hasContinue, $continueAmount, $continueRoundId, $hasDropDown, $dropDownAmount, $dropDownRoundId, $hasElimination, $eliminatedAmount, $hasBracketReset, $mappoolsReleased, $lobbiesReleased, $copyMappool, $copyMappoolFrom) {
		$stmt = $this->db->prepare('UPDATE rounds
			SET name = :name, lobby_size = :lobby_size, best_of = :best_of, is_first_round = :is_first_round, player_amount = :player_amount, is_start_round = :is_start_round, has_continue = :has_continue, continue_amount = :continue_amount, continue_round = :continue_round, has_drop_down = :has_drop_down, drop_down_amount = :drop_down_amount, drop_down_round = :drop_down_round, has_elimination = :has_elimination, eliminated_amount = :eliminated_amount, has_bracket_reset = :has_bracket_reset, mappools_released = :mappools_released, lobbies_released = :lobbies_released, copy_mappool = :copy_mappool, copy_mappool_from = :copy_mappool_from
			WHERE id = :id');
		$stmt->bindValue(':name', $name, PDO::PARAM_STR);
		$stmt->bindValue(':lobby_size', $lobbySize, PDO::PARAM_INT);
		$stmt->bindValue(':best_of', $bestOf, PDO::PARAM_INT);
		$stmt->bindValue(':is_first_round', $isFirstRound, PDO::PARAM_BOOL);
		$stmt->bindValue(':player_amount', $playerAmount, PDO::PARAM_INT);
		$stmt->bindValue(':is_start_round', $isStartRound, PDO::PARAM_BOOL);
		$stmt->bindValue(':has_continue', $hasContinue, PDO::PARAM_BOOL);
		$stmt->bindValue(':continue_amount', $continueAmount, PDO::PARAM_INT);
		$stmt->bindValue(':continue_round', $continueRoundId, PDO::PARAM_INT);
		$stmt->bindValue(':has_drop_down', $hasDropDown, PDO::PARAM_BOOL);
		$stmt->bindValue(':drop_down_amount', $dropDownAmount, PDO::PARAM_INT);
		$stmt->bindValue(':drop_down_round', $dropDownRoundId, PDO::PARAM_INT);
		$stmt->bindValue(':has_elimination', $hasElimination, PDO::PARAM_BOOL);
		$stmt->bindValue(':eliminated_amount', $eliminatedAmount, PDO::PARAM_INT);
		$stmt->bindValue(':has_bracket_reset', $hasBracketReset, PDO::PARAM_BOOL);
		$stmt->bindValue(':mappools_released', $mappoolsReleased, PDO::PARAM_BOOL);
		$stmt->bindValue(':lobbies_released', $lobbiesReleased, PDO::PARAM_BOOL);
		$stmt->bindValue(':copy_mappool', $copyMappool, PDO::PARAM_BOOL);
		$stmt->bindValue(':copy_mappool_from', $copyMappoolFrom, PDO::PARAM_INT);
		$stmt->bindValue(':id', $roundId, PDO::PARAM_INT);
		$stmt->execute();

		$this->recalculateRound();
	}

	public function deleteRound($roundId) {
		$stmt = $this->db->prepare('UPDATE rounds
			SET has_continue = 0, continue_amount = 0, continue_round = NULL
			WHERE continue_round = :continue_round');
		$stmt->bindValue(':continue_round', $roundId, PDO::PARAM_INT);
		$stmt->execute();
		$stmt = $this->db->prepare('UPDATE rounds
			SET has_drop_down = 0, drop_down_amount = 0, drop_down_round = NULL
			WHERE drop_down_round = :drop_down_round');
		$stmt->bindValue(':drop_down_round', $roundId, PDO::PARAM_INT);
		$stmt->execute();

		$stmt = $this->db->prepare('DELETE FROM round_times
			WHERE round = :round');
		$stmt->bindValue(':round', $roundId, PDO::PARAM_INT);
		$stmt->execute();

		$stmt = $this->db->prepare('DELETE FROM rounds
			WHERE id = :id');
		$stmt->bindValue(':id', $roundId, PDO::PARAM_INT);
		$stmt->execute();

		$this->recalculateRound();
	}

	public function putRoundTimes($roundId, $times) {
		$stmt = $this->db->prepare('DELETE FROM round_times
			WHERE round = :round');
		$stmt->bindValue(':round', $roundId, PDO::PARAM_INT);
		$stmt->execute();

		foreach ($times as $time) {
			$stmt = $this->db->prepare('INSERT INTO round_times (round, time_from, time_to)
				VALUES (:round, :time_from, :time_to)');
			$stmt->bindValue(':round', $roundId, PDO::PARAM_INT);
			$stmt->bindValue(':time_from', $time->from, PDO::PARAM_STR);
			$stmt->bindValue(':time_to', $time->to, PDO::PARAM_STR);
			$stmt->execute();
		}
	}

	public function getTiers() {
		$stmt = $this->db->prepare('SELECT id, name, lower_endpoint as lowerEndpoint, upper_endpoint as upperEndpoint, starting_round as startingRound, seed_by_rank as seedByRank, seed_by_time as seedByTime, seed_by_random as seedByRandom, sub_bonus as subBonus
			FROM tiers
			ORDER BY id ASC');
		$stmt->execute();
		return $stmt->fetchAll();
	}

	public function postTier($name, $lowerEndpoint, $upperEndpoint, $startingRound, $seeding, $subBonus) {
		$stmt = $this->db->prepare('INSERT INTO tiers (name, lower_endpoint, upper_endpoint, starting_round, seed_by_rank, seed_by_time, seed_by_random, sub_bonus)
			VALUES (:name, :lower_endpoint, :upper_endpoint, :starting_round, :seed_by_rank, :seed_by_time, :seed_by_random, :sub_bonus)');
		$stmt->bindValue(':name', $name, PDO::PARAM_STR);
		$stmt->bindValue(':lower_endpoint', $lowerEndpoint, PDO::PARAM_INT);
		$stmt->bindValue(':upper_endpoint', $upperEndpoint, PDO::PARAM_INT);
		$stmt->bindValue(':starting_round', $startingRound, PDO::PARAM_INT);
		$stmt->bindValue(':seed_by_rank', $seeding == 'rank', PDO::PARAM_BOOL);
		$stmt->bindValue(':seed_by_time', $seeding == 'time', PDO::PARAM_BOOL);
		$stmt->bindValue(':seed_by_random', $seeding == 'random', PDO::PARAM_BOOL);
		$stmt->bindValue(':sub_bonus', $subBonus, PDO::PARAM_BOOL);
		$stmt->execute();

		return $this->db->lastInsertId();
	}

	public function putTier($tierId, $name, $lowerEndpoint, $upperEndpoint, $startingRound, $seeding, $subBonus) {
		$stmt = $this->db->prepare('UPDATE tiers
			SET name = :name, lower_endpoint = :lower_endpoint, upper_endpoint = :upper_endpoint, starting_round = :starting_round, seed_by_rank = :seed_by_rank, seed_by_time = :seed_by_time, seed_by_random = :seed_by_random, sub_bonus = :sub_bonus
			WHERE id = :id');
		$stmt->bindValue(':name', $name, PDO::PARAM_STR);
		$stmt->bindValue(':lower_endpoint', $lowerEndpoint, PDO::PARAM_INT);
		$stmt->bindValue(':upper_endpoint', $upperEndpoint, PDO::PARAM_INT);
		$stmt->bindValue(':starting_round', $startingRound, PDO::PARAM_INT);
		$stmt->bindValue(':seed_by_rank', $seeding == 'rank', PDO::PARAM_BOOL);
		$stmt->bindValue(':seed_by_time', $seeding == 'time', PDO::PARAM_BOOL);
		$stmt->bindValue(':seed_by_random', $seeding == 'random', PDO::PARAM_BOOL);
		$stmt->bindValue(':sub_bonus', $subBonus, PDO::PARAM_BOOL);
		$stmt->bindValue(':id', $tierId, PDO::PARAM_INT);
		$stmt->execute();
	}

	public function deleteTier($tierId) {
		$stmt = $this->db->prepare('DELETE FROM tiers
			WHERE id = :id');
		$stmt->bindValue(':id', $tierId, PDO::PARAM_INT);
		$stmt->execute();
	}

	public function getLobbies($roundId, $tierId = 0) {
		if ($tierId != 0) {
			$stmt = $this->db->prepare('SELECT lobbies.id, lobbies.round, lobbies.tier, lobbies.match_id as matchId, lobbies.match_time as matchTime, lobbies.comment
				FROM lobbies INNER JOIN rounds ON lobbies.round = rounds.id
				WHERE lobbies.round = :round AND lobbies.tier = :tier');
			$stmt->bindValue(':round', $roundId, PDO::PARAM_INT);
			$stmt->bindValue(':tier', $tierId, PDO::PARAM_INT);
		} else {
			$stmt = $this->db->prepare('SELECT lobbies.id, lobbies.round, lobbies.tier, lobbies.match_id as matchId, lobbies.match_time as matchTime, lobbies.comment
				FROM lobbies INNER JOIN rounds ON lobbies.round = rounds.id
				WHERE lobbies.round = :round');
			$stmt->bindValue(':round', $roundId, PDO::PARAM_INT);
		}
		$stmt->execute();
		$lobbies = $stmt->fetchAll();
		foreach ($lobbies as &$lobby) {
			$lobby = $this->getLobby($lobby->id);
		}
		return $lobbies;
	}

	public function getLobby($lobbyId) {
		$osuApi = new OsuApi();

		$stmt = $this->db->prepare('SELECT lobbies.id, lobbies.round, lobbies.tier, lobbies.match_id as matchId, lobbies.match_time as matchTime, lobbies.comment, lobbies.result_sent as resultSent
			FROM lobbies INNER JOIN rounds ON lobbies.round = rounds.id
			WHERE lobbies.id = :id');
		$stmt->bindValue(':id', $lobbyId, PDO::PARAM_INT);
		$stmt->execute();
		$lobby = $stmt->fetch();

		$stmt = $this->db->prepare('SELECT lobby_slots.id, lobby_slots.user_id as userId, lobby_slots.continue_to_upper as continueToUpper, lobby_slots.drop_down as dropDown, lobby_slots.eliminated, lobby_slots.forfeit, lobby_slots.noshow, players.osu_id as osuId, discord_users.id as discordId, discord_users.username as discordUsername, discord_users.discriminator as discordDiscriminator, discord_users.avatar as discordAvatar
			FROM lobby_slots LEFT JOIN players ON lobby_slots.user_id = players.id LEFT JOIN discord_users ON players.discord_id = discord_users.id
			WHERE lobby_slots.lobby = :id');
		$stmt->bindValue(':id', $lobby->id, PDO::PARAM_INT);
		$stmt->execute();
		$rows = $stmt->fetchAll();
		$lobby->slots = [];
		foreach ($rows as $row) {
			$slot = new stdClass;
			$slot->id = $row->id;
			if ($row->userId) {
				$slot->userId = $row->userId;
				if ($row->continueToUpper) {
					$slot->continue = 'Continue';
				} elseif ($row->dropDown) {
					$slot->continue = 'Drop down';
				} elseif ($row->eliminated) {
					$slot->continue = 'Eliminated';
				} elseif ($row->forfeit) {
					$slot->continue = 'Forfeit';
				} elseif ($row->noshow) {
					$slot->continue = 'Noshow';
				} else {
					$slot->continue = null;
				}
				$slot->osu = $osuApi->getUser($row->osuId);
				$slot->discord = new stdClass;
				$slot->discord->id = $row->discordId;
				$slot->discord->username = $row->discordUsername;
				$slot->discord->discriminator = $row->discordDiscriminator;
				$slot->discord->avatar = $row->discordAvatar;
			}
			$lobby->slots[] = $slot;
		}

		foreach ($lobby->slots as &$slot) {
			if (!empty($slot->userId)) {
				$stmt = $this->db->prepare('SELECT time_slots.id, time_slots.day, time_slots.time
					FROM availabilities INNER JOIN time_slots ON availabilities.time_slot = time_slots.id
					WHERE availabilities.user_id = :user_id
					ORDER BY time_slots.id ASC');
				$stmt->bindValue(':user_id', $slot->discord->id, PDO::PARAM_INT);
				$stmt->execute();
				$slot->availabilities = $stmt->fetchAll();
			} else {
				$slot->availabilities = [];
			}
		}

		$stmt = $this->db->prepare('SELECT lobby_bans.beatmap_id as beatmapId, lobby_bans.banned_by as bannedBy, lobby_bans.after_bracket_reset as afterBracketReset, players.osu_id as osuId, discord_users.id as discordId, discord_users.username as discordUsername, discord_users.discriminator as discordDiscriminator, discord_users.avatar as discordAvatar
			FROM lobby_bans INNER JOIN players ON lobby_bans.banned_by = players.id INNER JOIN discord_users ON players.discord_id = discord_users.id
			WHERE lobby = :lobby');
		$stmt->bindValue(':lobby', $lobby->id, PDO::PARAM_INT);
		$stmt->execute();
		$rows = $stmt->fetchAll(PDO::FETCH_OBJ);
		$lobby->bans = [];
		foreach ($rows as $row) {
			$ban = new stdClass;
			$ban->beatmapId = $row->beatmapId;
			$ban->afterBracketReset = $row->afterBracketReset;
			$ban->bannedBy = new stdClass;
			$ban->bannedBy->userId = $row->bannedBy;
			$ban->bannedBy->osu = $osuApi->getUser($row->osuId);
			$ban->bannedBy->discord = new stdClass;
			$ban->bannedBy->discord->id = $row->discordId;
			$ban->bannedBy->discord->username = $row->discordUsername;
			$ban->bannedBy->discord->discriminator = $row->discordDiscriminator;
			$ban->bannedBy->discord->avatar = $row->discordAvatar;
			$lobby->bans[] = $ban;
		}

		return $lobby;
	}

	public function putLobbyTime($lobbyId, $matchTime) {
		if (!empty($matchTime)) {
			$stmt = $this->db->prepare('UPDATE lobbies
				SET match_time = :match_time
				WHERE id = :id');
			$stmt->bindValue(':match_time', $matchTime, PDO::PARAM_STR);
			$stmt->bindValue(':id', $lobbyId, PDO::PARAM_INT);
			$stmt->execute();
		}
	}

	public function putLobbyMatchId($lobbyId, $matchId) {
		$stmt = $this->db->prepare('UPDATE lobbies
			SET match_id = :match_id
			WHERE id = :id');
		$stmt->bindValue(':match_id', $matchId, PDO::PARAM_INT);
		$stmt->bindValue(':id', $lobbyId, PDO::PARAM_INT);
		$stmt->execute();
	}

	public function putLobbyComment($lobbyId, $comment) {
		$stmt = $this->db->prepare('UPDATE lobbies
			SET comment = :comment
			WHERE id = :id');
		$stmt->bindValue(':comment', $comment, PDO::PARAM_STR);
		$stmt->bindValue(':id', $lobbyId, PDO::PARAM_INT);
		$stmt->execute();
	}

	public function resetResultSent($lobbyId) {
		$stmt = $this->db->prepare('UPDATE lobbies
			SET result_sent = 0
			WHERE id = :id');
		$stmt->bindValue(':id', $lobbyId, PDO::PARAM_INT);
		$stmt->execute();
	}

	public function putLobbySlot($slotId, $userId) {
		$stmt = $this->db->prepare('UPDATE lobby_slots
			SET user_id = :user_id
			WHERE id = :id');
		$stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
		$stmt->bindValue(':id', $slotId, PDO::PARAM_INT);
		$stmt->execute();
	}

	public function postLobby($tierId, $roundId) {
		$stmt = $this->db->prepare('INSERT INTO lobbies (round, tier)
			VALUES (:round, :tier)');
		$stmt->bindValue(':round', $roundId, PDO::PARAM_INT);
		$stmt->bindValue(':tier', $tierId, PDO::PARAM_INT);
		$stmt->execute();
		return $this->db->lastInsertId();
	}

	public function postLobbySlot($lobbyId) {
		$stmt = $this->db->prepare('INSERT INTO lobby_slots (lobby)
			VALUES (:lobby)');
		$stmt->bindValue(':lobby', $lobbyId, PDO::PARAM_INT);
		$stmt->execute();
		return $this->db->lastInsertId();
	}

	public function deleteLobbies($tierId, $roundId) {
		$stmt = $this->db->prepare('DELETE l, ls
			FROM lobbies l JOIN lobby_slots ls ON l.id = ls.lobby
			WHERE l.tier = :tier AND l.round = :round');
		$stmt->bindValue(':tier', $tierId, PDO::PARAM_INT);
		$stmt->bindValue(':round', $roundId, PDO::PARAM_INT);
		$stmt->execute();
	}

	public function putBans($lobbyId, $bans) {
		$stmt = $this->db->prepare('DELETE FROM lobby_bans
		WHERE lobby = :lobby');
		$stmt->bindValue(':lobby', $lobbyId, PDO::PARAM_INT);
		$stmt->execute();

		foreach ($bans as $ban) {
			$stmt = $this->db->prepare('INSERT INTO lobby_bans (lobby, beatmap_id, banned_by, after_bracket_reset)
				VALUES (:lobby, :beatmap_id, :banned_by, :after_bracket_reset)');
			$stmt->bindValue(':lobby', $lobbyId, PDO::PARAM_INT);
			$stmt->bindValue(':beatmap_id', $ban->beatmapId, PDO::PARAM_INT);
			$stmt->bindValue(':banned_by', $ban->userId, PDO::PARAM_INT);
			$stmt->bindValue(':after_bracket_reset', $ban->afterBracketReset, PDO::PARAM_BOOL);
			$stmt->execute();
		}
	}

	public function putResult($lobbyId, $result) {
		$discordApi = new DiscordApi();

		$stmt = $this->db->prepare('SELECT rounds.id as roundId, rounds.name as roundName, tiers.id as tierId, tiers.name as tierName, rounds.continue_round as continueRound, rounds.drop_down_round as dropDownRound, lobbies.match_id as matchId, lobbies.result_sent as resultSent
			FROM rounds INNER JOIN lobbies ON rounds.id = lobbies.round INNER JOIN tiers ON lobbies.tier = tiers.id
			WHERE lobbies.id = :id');
		$stmt->bindValue(':id', $lobbyId, PDO::PARAM_INT);
		$stmt->execute();
		$round = $stmt->fetch();
		$roundId = $round->roundId;
		$roundName = $round->roundName;
		$tierId = $round->tierId;
		$tierName = $round->tierName;
		$matchId = $round->matchId;
		$resultSent = $round->resultSent;
		foreach ($result as &$player) {
			$stmt = $this->db->prepare('UPDATE lobby_slots
				SET continue_to_upper = :continue_to_upper, drop_down = :drop_down, eliminated = :eliminated, forfeit = :forfeit, noshow = :noshow
				WHERE lobby = :lobby and user_id = :user_id');
			$stmt->bindValue(':continue_to_upper', $player->continue == 'Continue', PDO::PARAM_BOOL);
			$stmt->bindValue(':drop_down', $player->continue == 'Drop down', PDO::PARAM_BOOL);
			$stmt->bindValue(':eliminated', $player->continue == 'Eliminated', PDO::PARAM_BOOL);
			$stmt->bindValue(':forfeit', $player->continue == 'Forfeit', PDO::PARAM_BOOL);
			$stmt->bindValue(':noshow', $player->continue == 'Noshow', PDO::PARAM_BOOL);
			$stmt->bindValue(':lobby', $lobbyId, PDO::PARAM_INT);
			$stmt->bindValue(':user_id', $player->userId, PDO::PARAM_INT);
			$stmt->execute();
			$nextRound = null;
			switch ($player->continue) {
				case 'Continue': $nextRound = $round->continueRound; break;
				case 'Drop down': $nextRound = $round->dropDownRound; break;
				default: $nextRound = null;
			}
			$stmt = $this->db->prepare('UPDATE players
				SET next_round = :next_round
				WHERE id = :id');
			$stmt->bindValue(':next_round', $nextRound, PDO::PARAM_INT);
			$stmt->bindValue(':id', $player->userId, PDO::PARAM_INT);
			$stmt->execute();

			//$player->score = 0;
		}

		$message = "**" . $roundName . " | " . $tierName . "**\r\n";
		$stmt = $this->db->prepare('SELECT osu_match_events.id, osu_match_events.type, osu_match_events.user_id as userId, osu_match_games.counts
			FROM osu_match_events INNER JOIN lobbies ON osu_match_events.match_id = lobbies.match_id LEFT JOIN osu_match_games ON osu_match_events.id = osu_match_games.match_event
			WHERE lobbies.id = :id
			ORDER BY osu_match_events.timestamp ASC');
		$stmt->bindValue(':id', $_GET['lobby'], PDO::PARAM_INT);
		$stmt->execute();
		$events = $stmt->fetchAll(PDO::FETCH_OBJ);
		$hasBracketReset = false;
		/*
		foreach ($events as $event) {
			if ($event->type == 'bracket-reset') {
				$hasBracketReset = true;
				foreach ($result as &$player) {
					$player->score = 0;
				}
			}
			if ($event->type == 'other' && $event->counts) {
				$scoreAmount = count($result) == 2 ? 1 : 6;

				$stmt = $this->db->prepare('SELECT user_id as userId, score, pass
					FROM osu_match_scores
					WHERE match_event = :match_event
					ORDER BY pass DESC, score DESC');
				$stmt->bindValue(':match_event', $event->id, PDO::PARAM_INT);
				$stmt->execute();
				$scores = $stmt->fetchAll(PDO::FETCH_OBJ);
				foreach ($scores as $score) {
					foreach ($result as &$player) {
						if ($score->userId == $player->osu->id) {
							$player->score += $scoreAmount;
							switch ($scoreAmount) {
								case 6: $scoreAmount = 4; break;
								case 4: $scoreAmount = 3; break;
								case 3: $scoreAmount = 2; break;
								default: $scoreAmount = 0; break;
							}
							break;
						}
					}
					if ($scoreAmount == 0) {
						break;
					}
				}
			}
		}
		*/

		usort($result, function($a, $b) {
			return $b->score - $a->score;
		});

		if (count($result) == 2) {
			$message .= "Final Score: **" . $result[0]->osu->username . " (<@" . $result[0]->discord->id . ">) " . $result[0]->score . "** | " . $result[1]->score . " " . $result[1]->osu->username . " (<@" . $result[1]->discord->id . ">)\r\n";
			$message .= "MP LINK: https://osu.ppy.sh/community/matches/" . $matchId . "\r\n\r\n";
			$message .= "**Bans**\r\n";
			$message .= "__" . $result[0]->osu->username . "__\r\n";
			$stmt = $this->db->prepare('SELECT mappool_slots.mod, osu_beatmaps.beatmap_id as beatmapId, osu_beatmaps.artist, osu_beatmaps.title, osu_beatmaps.version
				FROM mappools INNER JOIN mappool_slots ON mappools.id = mappool_slots.mappool INNER JOIN osu_beatmaps ON mappool_slots.beatmap_id = osu_beatmaps.beatmap_id
				WHERE mappools.tier = :tier AND mappools.round = :round');
			$stmt->bindValue(':tier', $tierId, PDO::PARAM_INT);
			$stmt->bindValue(':round', $roundId, PDO::PARAM_INT);
			$stmt->execute();
			$mappool = $stmt->fetchAll(PDO::FETCH_OBJ);
			$stmt = $this->db->prepare('SELECT beatmap_id as beatmapId, banned_by as bannedBy
				FROM lobby_bans
				WHERE lobby = :lobby AND after_bracket_reset = :after_bracket_reset');
			$stmt->bindValue(':lobby', $_GET['lobby'], PDO::PARAM_INT);
			$stmt->bindValue(':after_bracket_reset', $hasBracketReset, PDO::PARAM_BOOL);
			$stmt->execute();
			$bans = $stmt->fetchAll(PDO::FETCH_OBJ);
			foreach ($bans as $ban) {
				if ($ban->bannedBy == $result[0]->userId) {
					foreach ($mappool as $beatmap) {
						if ($beatmap->beatmapId == $ban->beatmapId) {
							$message .= $beatmap->mod . " | " . $beatmap->artist . " - " . $beatmap->title . " [" . $beatmap->version . "]\r\n";
						}
					}
				}
			}
			$message .= "\r\n__" . $result[1]->osu->username . "__\r\n";
			foreach ($bans as $ban) {
				if ($ban->bannedBy == $result[1]->userId) {
					foreach ($mappool as $beatmap) {
						if ($beatmap->beatmapId == $ban->beatmapId) {
							$message .= $beatmap->mod . " | " . $beatmap->artist . " - " . $beatmap->title . " [" . $beatmap->version . "]\r\n";
						}
					}
				}
			}
		} else {
			$message .= "MP LINK: https://osu.ppy.sh/community/matches/" . $matchId . "\r\n\r\n";
			foreach ($result as $score) {
				$message .= $score->score . " | " . $score->osu->username . " (<@" . $score->discord->id . ">)\r\n";
			}
		}
		/*
		$discordApi->sendMessage($message);
		*/
		if ($resultSent != "1") {
			$discordApi->sendMatchResult($lobbyId, $matchId, $result, $roundName, $tierName);
			$stmt = $this->db->prepare('UPDATE lobbies
				SET result_sent = 1
				WHERE id = :id');
			$stmt->bindValue(':id', $lobbyId, PDO::PARAM_INT);
			$stmt->execute();
		}
	}

	public function getMappoolId($tierId, $roundId) {
		$stmt = $this->db->prepare('SELECT copy_mappool as copyMappool, copy_mappool_from as copyMappoolFrom
			FROM rounds
			WHERE id = :id');
		$stmt->bindValue(':id', $roundId, PDO::PARAM_INT);
		$stmt->execute();
		$round = $stmt->fetch();
		if ($round->copyMappool) {
			$roundId = $round->copyMappoolFrom;
		}

		$stmt = $this->db->prepare('SELECT id
			FROM mappools
			WHERE round = :round AND tier = :tier');
		$stmt->bindValue(':round', $roundId, PDO::PARAM_INT);
		$stmt->bindValue(':tier', $tierId, PDO::PARAM_INT);
		$stmt->execute();
		$row = $stmt->fetch();
		if (!$row) {
			$stmt = $this->db->prepare('INSERT INTO mappools (tier, round)
				VALUES (:tier, :round)');
			$stmt->bindValue(':tier', $tierId, PDO::PARAM_INT);
			$stmt->bindValue(':round', $roundId, PDO::PARAM_INT);
			$stmt->execute();
			$mappoolId = $this->db->lastInsertId();
		} else {
			$mappoolId = $row->id;
		}

		return $mappoolId;
	}

	public function getMappool($mappoolId) {
		$osuApi = new OsuApi();

		$stmt = $this->db->prepare('SELECT id, tier, round, mappack
			FROM mappools
			WHERE id = :id');
		$stmt->bindValue(':id', $mappoolId, PDO::PARAM_INT);
		$stmt->execute();
		$mappool = $stmt->fetch();

		$stmt = $this->db->prepare('SELECT mappool_slots.id, mappool_slots.beatmap_id as beatmapId, mappool_slots.mod, osu_beatmaps.beatmapset_id as beatmapsetId, osu_beatmaps.title, osu_beatmaps.artist, osu_beatmaps.version, osu_beatmaps.cover, osu_beatmaps.preview_url as previewUrl, osu_beatmaps.total_length as totalLength, osu_beatmaps.bpm, osu_beatmaps.count_circles as countCircles, osu_beatmaps.count_sliders as countSliders, osu_beatmaps.cs, osu_beatmaps.drain, osu_beatmaps.accuracy, osu_beatmaps.ar, osu_beatmaps.difficulty_rating as difficultyRating
			FROM mappool_slots INNER JOIN osu_beatmaps ON mappool_slots.beatmap_id = osu_beatmaps.beatmap_id
			WHERE mappool_slots.mappool = :mappool');
		$stmt->bindValue(':mappool', $mappoolId, PDO::PARAM_INT);
		$stmt->execute();
		$mappool->slots = $stmt->fetchAll();

		$stmt = $this->db->prepare('SELECT mappool_feedback.feedback, players.osu_id as osuId, discord_users.id as discordId, discord_users.username as discordUsername, discord_users.discriminator as discordDiscriminator, discord_users.avatar as discordAvatar
			FROM mappool_feedback INNER JOIN players ON mappool_feedback.user_id = players.id INNER JOIN discord_users ON players.discord_id = discord_users.id
			WHERE mappool_feedback.mappool = :mappool');
		$stmt->bindValue(':mappool', $mappoolId, PDO::PARAM_INT);
		$stmt->execute();
		$rows = $stmt->fetchAll();
		$mappool->feedback = [];
		foreach ($rows as $row) {
			$feedback = new stdClass;
			$feedback->feedback = $row->feedback;
			$feedback->osu = $osuApi->getUser($row->osuId);
			$feedback->discord = new stdClass;
			$feedback->discord->id = $row->discordId;
			$feedback->discord->username = $row->discordUsername;
			$feedback->discord->discriminator = $row->discordDiscriminator;
			$feedback->discord->avatar = $row->discordAvatar;
			$mappool->feedback[] = $feedback;
		}

		return $mappool;
	}

	public function putMappoolSlots($mappoolId, $slots) {
		$stmt = $this->db->prepare('DELETE FROM mappool_slots
			WHERE mappool = :mappool');
		$stmt->bindValue(':mappool', $mappoolId, PDO::PARAM_INT);
		$stmt->execute();

		foreach ($slots as $slot) {
			$stmt = $this->db->prepare('INSERT INTO mappool_slots (mappool, beatmap_id, `mod`)
				VALUES (:mappool, :beatmap_id, :mod)');
			$stmt->bindValue(':mappool', $mappoolId, PDO::PARAM_INT);
			$stmt->bindValue(':beatmap_id', $slot->beatmapId, PDO::PARAM_INT);
			$stmt->bindValue(':mod', $slot->mod, PDO::PARAM_STR);
			$stmt->execute();
		}
	}

	public function putMappoolMappack($mappoolId, $mappack) {
		$stmt = $this->db->prepare('UPDATE mappools
			SET mappack = :mappack
			WHERE id = :id');
		$stmt->bindValue(':mappack', $mappack, PDO::PARAM_STR);
		$stmt->bindValue(':id', $mappoolId, PDO::PARAM_INT);
		$stmt->execute();
	}

	public function putMappoolFeedback($mappoolId, $userId, $feedback) {
		$stmt = $this->db->prepare('DELETE FROM mappool_feedback
			WHERE mappool = :mappool AND user_id = :user_id');
		$stmt->bindValue(':mappool', $mappoolId, PDO::PARAM_INT);
		$stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
		$stmt->execute();
		$stmt = $this->db->prepare('INSERT INTO mappool_feedback (mappool, user_id, feedback)
			VALUES (:mappool, :user_id, :feedback)');
		$stmt->bindValue(':mappool', $mappoolId, PDO::PARAM_INT);
		$stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
		$stmt->bindValue(':feedback', $feedback, PDO::PARAM_STR);
		$stmt->execute();
	}

	public function putOsuGameCounts($matchEventId, $counts) {
		$stmt = $this->db->prepare('UPDATE osu_match_games
			SET counts = :counts
			WHERE match_event = :match_event');
		$stmt->bindValue(':counts', $counts, PDO::PARAM_BOOL);
		$stmt->bindValue(':match_event', $matchEventId, PDO::PARAM_INT);
		$stmt->execute();
	}

	public function putOsuGamePickedBy($matchEventId, $userId) {
		$stmt = $this->db->prepare('UPDATE osu_match_games
			SET picked_by = :picked_by
			WHERE match_event = :match_event');
		$stmt->bindValue(':picked_by', $userId, PDO::PARAM_INT);
		$stmt->bindValue(':match_event', $matchEventId, PDO::PARAM_INT);
		$stmt->execute();
	}

	public function postBracketReset($matchId, $time) {
		$stmt = $this->db->prepare('SELECT MIN(t1.ID + 1) AS nextID
			FROM osu_match_events t1 LEFT JOIN osu_match_events t2 ON t1.ID + 1 = t2.ID
			WHERE t2.ID IS NULL');
		$stmt->execute();
		$freeId = $stmt->fetch(PDO::FETCH_OBJ)->nextID;
		$stmt = $this->db->prepare('INSERT INTO osu_match_events (id, match_id, type, timestamp)
			VALUES (:id, :match_id, :type, :timestamp)');
		$stmt->bindValue(':id', $freeId, PDO::PARAM_INT);
		$stmt->bindValue(':match_id', $matchId, PDO::PARAM_INT);
		$stmt->bindValue(':type', 'bracket-reset', PDO::PARAM_STR);
		$stmt->bindValue(':timestamp', $time, PDO::PARAM_STR);
		$stmt->execute();
	}

	public function deleteBracketReset($matchEventId) {
		$stmt = $this->db->prepare('DELETE FROM osu_match_events
			WHERE id = :id AND type = \'bracket-reset\'');
		$stmt->bindValue(':id', $_GET['id'], PDO::PARAM_INT);
		$stmt->execute();
	}

	public function getAvailability($userId) {
		$stmt = $this->db->prepare('SELECT time_slots.id, time_slots.day, time_slots.time
			FROM availabilities INNER JOIN time_slots ON availabilities.time_slot = time_slots.id
			WHERE availabilities.user_id = :user_id');
		$stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
		$stmt->execute();
		$availability = $stmt->fetchAll();

		usort($availability, function($a, $b) {
			if ($a->day == $b->day) {
				return substr($a->time, 0, 2) - substr($b->time, 0, 2);
			}
			$days = [ 'Friday', 'Saturday', 'Sunday', 'Monday' ];
			return intval(array_search($a->day, $days)) - intval(array_search($b->day, $days));
		});

		return $availability;
	}

	public function putAvailability($availabilities) {
		$stmt = $this->db->prepare('DELETE FROM availabilities
			WHERE user_id = :user_id');
		$stmt->bindValue(':user_id', $this->discordId, PDO::PARAM_INT);
		$stmt->execute();

		foreach ($availabilities as $availability) {
			$stmt = $this->db->prepare('INSERT INTO availabilities (user_id, time_slot)
				VALUES (:user_id, :time_slot)');
			$stmt->bindValue(':user_id', $this->discordId, PDO::PARAM_INT);
			$stmt->bindValue(':time_slot', $availability->id, PDO::PARAM_INT);
			$stmt->execute();
		}
	}

	public function getSettings() {
		$stmt = $this->db->prepare('SELECT registrations_open as registrationsOpen, registrations_from as registrationsFrom, registrations_to as registrationsTo, role_admin as roleAdmin, role_headpooler as roleHeadpooler, role_mappooler as roleMappooler, role_referee as roleReferee, role_player as rolePlayer
		FROM settings');
		$stmt->execute();
		return $stmt->fetch();
	}

	public function putRegistrationSettings($registrationsOpen, $registrationsFrom, $registrationsTo) {
		$stmt = $this->db->prepare('UPDATE settings
			SET registrations_open = :registrations_open');
		$stmt->bindValue(':registrations_open', $registrationsOpen, PDO::PARAM_BOOL);
		$stmt->execute();
	
		$stmt = $this->db->prepare('UPDATE settings
			SET registrations_from = :registrations_from');
		$stmt->bindValue(':registrations_from', $registrationsFrom, PDO::PARAM_STR);
		$stmt->execute();
	
		$stmt = $this->db->prepare('UPDATE settings
			SET registrations_to = :registrations_to');
		$stmt->bindValue(':registrations_to', $registrationsTo, PDO::PARAM_STR);
		$stmt->execute();
	}

	public function getRoles() {
		$stmt = $this->db->prepare('SELECT id, name, color, position
			FROM discord_roles
			ORDER BY position DESC');
		$stmt->execute();
		return $stmt->fetchAll();
	}

	public function putRoles($roleAdmin, $roleHeadpooler, $roleMappooler, $roleReferee, $rolePlayer) {
		$stmt = $this->db->prepare('UPDATE settings
			SET role_admin = :role_admin');
		$stmt->bindValue(':role_admin', $roleAdmin, PDO::PARAM_INT);
		$stmt->execute();
	
		$stmt = $this->db->prepare('UPDATE settings
			SET role_headpooler = :role_headpooler');
		$stmt->bindValue(':role_headpooler', $roleHeadpooler, PDO::PARAM_INT);
		$stmt->execute();
	
		$stmt = $this->db->prepare('UPDATE settings
			SET role_mappooler = :role_mappooler');
		$stmt->bindValue(':role_mappooler', $roleMappooler, PDO::PARAM_INT);
		$stmt->execute();
	
		$stmt = $this->db->prepare('UPDATE settings
			SET role_referee = :role_referee');
		$stmt->bindValue(':role_referee', $roleReferee, PDO::PARAM_INT);
		$stmt->execute();

		$stmt = $this->db->prepare('UPDATE settings
			SET role_player = :role_player');
		$stmt->bindValue(':role_player', $rolePlayer, PDO::PARAM_INT);
		$stmt->execute();
	}

	public function postRoles() {
		$discordApi = new DiscordApi();

		$stmt = $this->db->prepare('TRUNCATE discord_roles');
		$stmt->execute();

		$roles = $discordApi->getGuildRoles();
		foreach ($roles as $role) {
			$stmt = $this->db->prepare('INSERT INTO discord_roles (id, name, color, position)
				VALUES (:id, :name, :color, :position)');
			$stmt->bindValue(':id', $role->id, PDO::PARAM_INT);
			$stmt->bindValue(':name', $role->name, PDO::PARAM_STR);
			$stmt->bindValue(':color', $role->color, PDO::PARAM_INT);
			$stmt->bindValue(':position', $role->position, PDO::PARAM_INT);
			$stmt->execute();
		}
	}

	public function getMappoolers() {
		$stmt = $this->db->prepare('SELECT discord_users.id as discordId, discord_users.username as discordUsername, discord_users.discriminator as discordDiscriminator, discord_users.avatar as discordAvatar
			FROM mappoolers INNER JOIN discord_users ON mappoolers.discord_id = discord_users.id');
		$stmt->execute();
		$rows = $stmt->fetchAll();
		$mappoolers = [];
		foreach ($rows as $row) {
			$mappooler = new stdClass;
			$mappooler->discord = new stdClass;
			$mappooler->discord->id = $row->discordId;
			$mappooler->discord->username = $row->discordUsername;
			$mappooler->discord->discriminator = $row->discordDiscriminator;
			$mappooler->discord->avatar = $row->discordAvatar;

			$stmt = $this->db->prepare('SELECT mappooler_tiers.tier as id, tiers.name as name, tiers.lower_endpoint as lowerEndpoint, tiers.upper_endpoint as upperEndpoint
				FROM mappooler_tiers INNER JOIN tiers ON mappooler_tiers.tier = tiers.id
				WHERE mappooler_tiers.discord_id = :discord_id');
			$stmt->bindValue(':discord_id', $mappooler->discord->id, PDO::PARAM_INT);
			$stmt->execute();
			$mappooler->tiers = $stmt->fetchAll();

			$mappoolers[] = $mappooler;
		}

		return $mappoolers;
	}

	public function postMappoolers() {
		$discordApi = new DiscordApi();

		$settings = $this->getSettings();
		$roleId = $settings->roleMappooler;

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

		$stmt = $this->db->prepare('SELECT id, discord_id as discordId
			FROM mappoolers');
		$stmt->execute();
		$existingMappoolers = $stmt->fetchAll();

		// remove mappoolers from the website that got the role removed
		foreach ($existingMappoolers as $existingMappooler) {
			$found = false;
			foreach ($mappoolers as $mappooler) {
				if ($existingMappooler->discordId == $mappooler->user->id) {
					$found = true;
					break;
				}
			}
			if (!$found) {
				$stmt = $this->db->prepare('DELETE FROM mappoolers
					WHERE discord_id = :discord_id');
				$stmt->bindValue(':discord_id', $existingMappooler->discordId, PDO::PARAM_INT);
				$stmt->execute();
				$stmt = $this->db->prepare('DELETE FROM mappooler_tiers
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
				$stmt = $this->db->prepare('INSERT INTO mappoolers (discord_id)
					VALUES (:discord_id)');
				$stmt->bindValue(':discord_id', $mappooler->user->id, PDO::PARAM_INT);
				$stmt->execute();

				$this->cacheNewDiscordAccount($mappooler->user->id);
			}
		}
	}

	public function putMappooler($userId, $tiers) {
		$stmt = $this->db->prepare('DELETE FROM mappooler_tiers
			WHERE discord_id = :discord_id');
		$stmt->bindValue(':discord_id', $userId, PDO::PARAM_INT);
		$stmt->execute();

		foreach ($tiers as $tier) {
			$stmt = $this->db->prepare('INSERT INTO mappooler_tiers (discord_id, tier)
				VALUES (:discord_id, :tier)');
			$stmt->bindValue(':discord_id', $userId, PDO::PARAM_INT);
			$stmt->bindValue(':tier', $tier, PDO::PARAM_INT);
			$stmt->execute();
		}
	}

	public function cacheNewTwitchAccount($accessToken) {
		$twitchApi = new TwitchApi();

		$twitchUser = $twitchApi->getUser($accessToken);

		$sub = $twitchApi->getUserSubscription($accessToken, $twitchUser->_id);

		$stmt = $this->db->prepare('INSERT INTO twitch_users (id, username, display_name, avatar, sub_since, sub_plan)
			VALUES (:id, :username, :display_name, :avatar, :sub_since, :sub_plan)
			ON DUPLICATE KEY UPDATE username = :username2, display_name = :display_name2, avatar = :avatar2, sub_since = :sub_since2, sub_plan = :sub_plan2');
		$stmt->bindValue(':id', $twitchUser->_id, PDO::PARAM_INT);
		$stmt->bindValue(':username', $twitchUser->name, PDO::PARAM_STR);
		$stmt->bindValue(':display_name', $twitchUser->display_name, PDO::PARAM_STR);
		$stmt->bindValue(':avatar', $twitchUser->logo, PDO::PARAM_STR);
		$stmt->bindValue(':sub_since', isset($sub->created_at) ? $sub->created_at : null, PDO::PARAM_STR);
		$stmt->bindValue(':sub_plan', isset($sub->sub_plan) ? $sub->sub_plan : null, PDO::PARAM_STR);
		$stmt->bindValue(':username2', $twitchUser->name, PDO::PARAM_STR);
		$stmt->bindValue(':display_name2', $twitchUser->display_name, PDO::PARAM_STR);
		$stmt->bindValue(':avatar2', $twitchUser->logo, PDO::PARAM_STR);
		$stmt->bindValue(':sub_since2', isset($sub->created_at) ? $sub->created_at : null, PDO::PARAM_STR);
		$stmt->bindValue(':sub_plan2', isset($sub->sub_plan) ? $sub->sub_plan : null, PDO::PARAM_STR);
		$stmt->execute();

		return $twitchUser->_id;
	}

	public function getTimeslots() {
		$stmt = $this->db->prepare('SELECT id, day, `time`
			FROM time_slots');
		$stmt->execute();
		$timeslots = $stmt->fetchAll();

		usort($timeslots, function($a, $b) {
			if ($a->day == $b->day) {
				return substr($a->time, 0, 2) - substr($b->time, 0, 2);
			}
			$days = [ 'Friday', 'Saturday', 'Sunday', 'Monday' ];
			return intval(array_search($a->day, $days)) - intval(array_search($b->day, $days));
		});

		return $timeslots;
	}

	public function postTimeslots($timeslots) {
		$stmt = $this->db->prepare('DELETE FROM time_slots');
		$stmt->execute();

		foreach ($timeslots as $timeslot) {
			$stmt = $this->db->prepare('INSERT INTO time_slots (day, `time`)
				VALUES (:day, :time)');
			$stmt->bindValue(':day', $timeslot->day, PDO::PARAM_STR);
			$stmt->bindValue(':time', $timeslot->time, PDO::PARAM_STR);
			$stmt->execute();
		}
	}

	private function recalculateRound($roundId = 0) {
		if (empty($roundId)) {
			$stmt = $this->db->prepare('SELECT has_continue as hasContinue, continue_round as continueRound, has_drop_down as hasDropDown, drop_down_round as dropDownRound
				FROM rounds
				WHERE is_first_round = 1');
			$stmt->execute();
			$row = $stmt->fetch();
			if (!empty($row) && !empty($row->hasContinue)) {
				$this->recalculateRound($row->continueRound);
			}
			if (!empty($row) && !empty($row->hasDropDown)) {
				$this->recalculateRound($row->dropDownRound);
			}
		} else {
			$playerAmount = 0;

			$stmt = $this->db->prepare('SELECT player_amount as playerAmount, lobby_size as lobbySize, continue_amount as continueAmount
				FROM rounds
				WHERE has_continue = 1 AND continue_round = :continue_round');
			$stmt->bindValue(':continue_round', $roundId, PDO::PARAM_INT);
			$stmt->execute();
			$rows = $stmt->fetchAll();
			foreach ($rows as $row) {
				$playerAmount += $row->playerAmount / $row->lobbySize * $row->continueAmount;
			}
			$stmt = $this->db->prepare('SELECT player_amount as playerAmount, lobby_size as lobbySize, drop_down_amount as dropDownAmount
				FROM rounds
				WHERE has_drop_down = 1 AND drop_down_round = :drop_down_round');
			$stmt->bindValue(':drop_down_round', $roundId, PDO::PARAM_INT);
			$stmt->execute();
			$rows = $stmt->fetchAll();
			foreach ($rows as $row) {
				$playerAmount += $row->playerAmount / $row->lobbySize * $row->dropDownAmount;
			}

			$stmt = $this->db->prepare('UPDATE rounds
				SET player_amount = :player_amount
				WHERE id = :id');
			$stmt->bindValue(':player_amount', $playerAmount, PDO::PARAM_INT);
			$stmt->bindValue(':id', $roundId, PDO::PARAM_INT);
			$stmt->execute();

			$stmt = $this->db->prepare('SELECT has_continue as hasContinue, continue_round as continueRound, has_drop_down as hasDropDown, drop_down_round as dropDownRound
				FROM rounds
				WHERE id = :id');
			$stmt->bindValue(':id', $roundId, PDO::PARAM_INT);
			$stmt->execute();
			$row = $stmt->fetch();
			if (!empty($row->hasContinue)) {
				$this->recalculateRound($row->continueRound);
			}
			if (!empty($row->hasDropDown)) {
				$this->recalculateRound($row->dropDownRound);
			}
		}
	}

	private function cacheNewDiscordAccount($discordId) {
		$discordApi = new DiscordApi();
		$user = $discordApi->getGuildMember($discordId);

		$stmt = $this->db->prepare('INSERT INTO discord_users (id, username, discriminator, avatar)
			VALUES (:id, :username, :discriminator, :avatar)
			ON DUPLICATE KEY UPDATE username = :username2, discriminator = :discriminator2, avatar = :avatar2');
		$stmt->bindValue(':id', $discordId, PDO::PARAM_INT);
		$stmt->bindValue(':username', $user->user->username, PDO::PARAM_STR);
		$stmt->bindValue(':discriminator', $user->user->discriminator, PDO::PARAM_STR);
		$stmt->bindValue(':avatar', $user->user->avatar, PDO::PARAM_STR);
		$stmt->bindValue(':username2', $user->user->username, PDO::PARAM_STR);
		$stmt->bindValue(':discriminator2', $user->user->discriminator, PDO::PARAM_STR);
		$stmt->bindValue(':avatar2', $user->user->avatar, PDO::PARAM_STR);
		$stmt->execute();
	}

	private function generateToken() {
		while (true) {
			$token = str_replace('.', '', uniqid('', true));
			$stmt = $this->db->prepare('SELECT COUNT(*) as rowcount
				FROM bearer_tokens
				WHERE token = :token');
			$stmt->bindValue(':token', $token, PDO::PARAM_STR);
			$stmt->execute();
			$rows = $stmt->fetch();
			if ($rows->rowcount == '0') {
				break;
			}
		}

		return $token;
	}
}

?>