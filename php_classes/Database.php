<?php

require_once 'OsuApi.php';

class Database {
	
	private $database_host;
	private $database_dbname;
	private $database_username;
	private $database_password;

	function __construct() {
		$config = parse_ini_file('config.ini');
		$this->database_host = $config['databaseHost'];
		$this->database_dbname = $config['databaseDbname'];
		$this->database_username = $config['databaseUsername'];
		$this->database_password = $config['databasePassword'];
	}

	public function getConnection() {
		$connection = new PDO('mysql:host=' . $this->database_host . ';dbname=' . $this->database_dbname . ';charset=utf8', $this->database_username, $this->database_password);
		return $connection;
	}

	public function tiers() {
		$db = $this->getConnection();
		$stmt = $db->prepare('SELECT id, lower_endpoint, upper_endpoint, slots, name
			FROM tiers
			ORDER BY lower_endpoint');
		$stmt->execute();
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	public function user($user_id) {
		$db = $this->getConnection();
		$osuApi = new OsuApi();

		$user = new stdClass;

		$stmt = $db->prepare('SELECT username, discriminator, avatar
			FROM discord_users
			WHERE id = :id');
		$stmt->bindValue(':id', $user_id, PDO::PARAM_STR);
		$stmt->execute();
		$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

		$user->discord_profile = new stdClass;
		$user->discord_profile->id = $user_id;
		$user->discord_profile->username = $rows[0]['username'];
		$user->discord_profile->discriminator = $rows[0]['discriminator'];
		$user->discord_profile->avatar = $rows[0]['avatar'];

		$stmt = $db->prepare('SELECT osu_id, twitch_id, tier, trivia, current_lobby, next_round
			FROM players
			WHERE id = :id');
		$stmt->bindValue(':id', $user_id, PDO::PARAM_STR);
		$stmt->execute();
		$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

		if (!empty($rows[0]['osu_id'])) {
			$user->osu_profile = $osuApi->getUser($rows[0]['osu_id']);
			foreach ($this->tiers() as $tier) {
				if ($tier['id'] == $rows[0]['tier']) {
					$user->tier = new stdClass;
					$user->tier->id = $tier['id'];
					$user->tier->name = $tier['name'];
					$user->tier->slots = $tier['slots'];
					$user->tier->lower_endpoint = $tier['lower_endpoint'];
					$user->tier->upper_endpoint = $tier['upper_endpoint'];
				}
			}
			$user->trivia = $rows[0]['trivia'];
			$user->current_lobby = $rows[0]['current_lobby'];
			$user->next_round = $rows[0]['next_round'];
		}
		if (!empty($rows[0]['twitch_id'])) {
			$stmt = $db->prepare('SELECT id, username, display_name, avatar, sub_since, sub_plan
				FROM twitch_users
				WHERE id = :id');
			$stmt->bindValue(':id', $rows[0]['twitch_id'], PDO::PARAM_INT);
			$stmt->execute();
			$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

			$user->twitch_profile = new stdClass;
			$user->twitch_profile->id = $rows[0]['id'];
			$user->twitch_profile->username = $rows[0]['username'];
			$user->twitch_profile->display_name = $rows[0]['display_name'];
			$user->twitch_profile->avatar = $rows[0]['avatar'];
			$user->twitch_profile->sub_since = $rows[0]['sub_since'];
			$user->twitch_profile->sub_plan = $rows[0]['sub_plan'];
		}

		return $user;
	}
}

?>