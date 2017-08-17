<?php

require_once 'php_classes/Database.php';
require_once 'php_classes/DiscordApi.php';

$database = new Database();
$db = $database->getConnection();
$discordApi = new DiscordApi();


$stmt = $db->prepare('SELECT id
	FROM players
	WHERE next_round IS NULL AND role_given IS NULL
	ORDER BY id DESC');
$stmt->execute();
$players = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($players as $player) {
	$response = $discordApi->removeUserFromPlayers($player['id']);
	if(!empty($response) && strpos($response->message, 'rate limit') !== false) {
		sleep(20);
		$discordApi->removeUserFromPlayers($player['id']);
	}
	$stmt = $db->prepare('UPDATE players
		SET role_given = 1
		WHERE id = :id');
	$stmt->bindValue(':id', $player['id'], PDO::PARAM_INT);
	$stmt->execute();
	sleep(2);
}

echoFeedback(false, 'Player role given');

/*
$stmt = $db->prepare('SELECT next_round
	FROM players
	WHERE id = :id');
$stmt->bindValue(':id', $_GET['user'], PDO::PARAM_STR);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (empty($rows[0]['next_round'])) {
	$discordApi->removeUserFromPlayers($_GET['user']);
	echo 'user removed';
}
*/

?>