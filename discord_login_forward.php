<?php

require_once 'php_classes/DiscordApi.php';

$discordApi = new DiscordApi();
$uri = $discordApi->getLoginUri();
header('Location: ' . $uri);
die();

?>