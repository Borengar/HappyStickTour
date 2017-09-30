<?php

class TwitchApi {

	// get api keys from here: https://twitch.tv/settings/connections
	private $twitchClientKey;
	private $twitchClientSecret;

	// the page the user gets redirected to after authorization
	private $redirect_uri;

	function __construct() {
		$config = parse_ini_file('config.ini');
		$this->twitchClientKey = $config['twitchClientKey'];
		$this->twitchClientSecret = $config['twitchClientSecret'];
		$this->redirectUri = $config['twitchRedirectUri'];
	}

	public function getLoginUri() {
		$state = random_int(1, 1000000000);

		return 'https://api.twitch.tv/kraken/oauth2/authorize?response_type=token&client_id=' . $this->twitchClientKey . '&redirect_uri=' . $this->redirectUri . '&scope=user_read+user_subscriptions&state=' . $state;
	}

	public function getAccessToken($code, $state) {
		$curl = curl_init();
		curl_setopt_array($curl, array(
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_FOLLOWLOCATION => false,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_URL => 'https://api.twitch.tv/kraken/oauth2/token',
			CURLOPT_POSTFIELDS => array(
				'client_id' => $this->twitchClientKey,
				'client_secret' => $this->twitchClientSecret,
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
				'Client-ID: ' . $this->twitchClientKey,
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
				'Client-ID: ' . $this->twitchClientKey,
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