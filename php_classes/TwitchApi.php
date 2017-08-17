<?php

class TwitchApi {

	// get api keys from here: https://twitch.tv/settings/connections
	private $twitch_client_key;
	private $twitch_client_secret;

	// the page the user gets redirected to after authorization
	private $redirect_uri = 'http://happysticktour.com/twitch_login.html';

	// permission scopes
	private $scope = 'user_read+user_subscriptions';

	function __construct() {
		$config = parse_ini_file('config.ini');
		$this->twitch_client_key = $config['twitchClientKey'];
		$this->twitch_client_secret = $config['twitchClientSecret'];
	}

	public function getLoginUri() {
		$state = random_int(1, 1000000000);

		return 'https://api.twitch.tv/kraken/oauth2/authorize?response_type=code&client_id=' . $this->twitch_client_key . '&redirect_uri=' . $this->redirect_uri . '&scope=' . $this->scope . '&state=' . $state;
	}

	public function getAccessToken($code, $state) {
		$curl = curl_init();
		curl_setopt_array($curl, array(
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_FOLLOWLOCATION => false,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_URL => 'https://api.twitch.tv/kraken/oauth2/token',
			CURLOPT_POSTFIELDS => array(
				'client_id' => $this->twitch_client_key,
				'client_secret' => $this->twitch_client_secret,
				'grant_type' => 'authorization_code',
				'redirect_uri' => $this->redirect_uri,
				'code' => $code,
				'state' => $state
				)
			)
		);
		$response = json_decode(curl_exec($curl));
		curl_close($curl);
		return $response->access_token;
	}

	public function getUser($accessToken) {
		$curl = curl_init();
		curl_setopt_array($curl, array(
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_FOLLOWLOCATION => false,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_URL => 'https://api.twitch.tv/kraken/user',
			CURLOPT_HTTPHEADER => array(
				'Accept: application/vnd.twitchtv.v5+json',
				'Client-ID: ' . $this->twitch_client_key,
				'Authorization: OAuth ' . $accessToken
				)
			)
		);
		$response = json_decode(curl_exec($curl));
		curl_close($curl);
		return $response;
	}

	public function getUserSubscription($accessToken, $userId) {
		$curl = curl_init();
		curl_setopt_array($curl, array(
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_FOLLOWLOCATION => false,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_URL => 'https://api.twitch.tv/kraken/users/' . $userId . '/subscriptions/33002242',
			CURLOPT_HTTPHEADER => array(
				'Accept: application/vnd.twitchtv.v5+json',
				'Client-ID: ' . $this->twitch_client_key,
				'Authorization: OAuth ' . $accessToken
				)
			)
		);
		$response = json_decode(curl_exec($curl));
		curl_close($curl);
		return $response;
	}
}

?>