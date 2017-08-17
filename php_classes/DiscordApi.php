<?php

class DiscordApi {

	private $client_id;
	private $client_secret;

	private $bot_token;

	private $scopes = 'identify';

	//private $guild_id = '279958943890145280'; // BorengarTest Guild
	private $guild_id = '110691455600689152'; // HappyStick Guild
	private $role_id = '333708355854139412'; // Player role

	function __construct() {
		$config = parse_ini_file('config.ini');
		$this->client_id = $config['discordClientId'];
		$this->client_secret = $config['discordClientSecret'];
		$this->bot_token = $config['discordBotToken'];
	}

	public function getLoginUri() {
		return 'https://discordapp.com/oauth2/authorize?client_id=' . $this->client_id . '&scope=' . $this->scopes . '&response_type=token';
	}

	public function getUser($token) {
		$curl = curl_init();
		curl_setopt_array($curl, array(
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_FOLLOWLOCATION => false,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_URL => 'https://discordapp.com/api/users/@me',
			CURLOPT_HTTPHEADER => array(
				'Authorization: Bearer ' . $token
				)
			)
		);
		$response = json_decode(curl_exec($curl));
		curl_close($curl);
		return $response;
	}

	public function addUserToPlayers($player_id) {
		$curl = curl_init();
		curl_setopt_array($curl, array(
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_FOLLOWLOCATION => false,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_CUSTOMREQUEST => 'PUT',
			CURLOPT_URL => 'https://discordapp.com/api/guilds/' . $this->guild_id . '/members/' . $player_id . '/roles/' . $this->role_id,
			CURLOPT_HTTPHEADER => array(
				'Authorization: Bot ' . $this->bot_token
				),
			CURLOPT_POSTFIELDS => ''
			)
		);
		$response = json_decode(curl_exec($curl));
		curl_close($curl);
		return $response;
	}

	public function removeUserFromPlayers($player_id) {
		$curl = curl_init();
		curl_setopt_array($curl, array(
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_FOLLOWLOCATION => false,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_CUSTOMREQUEST => 'DELETE',
			CURLOPT_URL => 'https://discordapp.com/api/guilds/' . $this->guild_id . '/members/' . $player_id . '/roles/' . $this->role_id,
			CURLOPT_HTTPHEADER => array(
				'Authorization: Bot ' . $this->bot_token
				),
			CURLOPT_POSTFIELDS => ''
			)
		);
		$response = json_decode(curl_exec($curl));
		curl_close($curl);
		return $response;
	}

	public function addUserRole($player_id, $role_id) {
		$curl = curl_init();
		curl_setopt_array($curl, array(
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_FOLLOWLOCATION => false,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_CUSTOMREQUEST => 'PUT',
			CURLOPT_URL => 'https://discordapp.com/api/guilds/' . $this->guild_id . '/members/' . $player_id . '/roles/' . $role_id,
			CURLOPT_HTTPHEADER => array(
				'Authorization: Bot ' . $this->bot_token
				),
			CURLOPT_POSTFIELDS => ''
			)
		);
		$response = json_decode(curl_exec($curl));
		curl_close($curl);
		return $response;
	}

	public function getUserRoles($player_id) {
		$response = $this->getGuildMember($player_id);
		if ($response === false) {
			return false;
		}
		return $response->roles;
	}

	public function getGuildRoles() {
		$curl = curl_init();
		curl_setopt_array($curl, array(
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_FOLLOWLOCATION => false,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_URL => 'https://discordapp.com/api/guilds/' . $this->guild_id . '/roles',
			CURLOPT_HTTPHEADER => array(
				'Authorization: Bot ' . $this->bot_token
				)
			)
		);
		$response = json_decode(curl_exec($curl));
		curl_close($curl);
		return $response;
	}

	public function getGuildMember($player_id) {
		$curl = curl_init();
		curl_setopt_array($curl, array(
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_FOLLOWLOCATION => false,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_URL => 'https://discordapp.com/api/guilds/' . $this->guild_id . '/members/' . $player_id,
			CURLOPT_HTTPHEADER => array(
				'Authorization: Bot ' . $this->bot_token
				)
			)
		);
		$response = json_decode(curl_exec($curl));
		if (!empty($response->code) && $response->code == 10007) {
			return false;
		}
		curl_close($curl);
		return $response;
	}

}

?>