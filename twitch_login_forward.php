<?php

require_once 'php_classes/TwitchApi.php';

$twitchApi = new TwitchApi();
$uri = $twitchApi->getLoginUri();
header('Location: ' . $uri);
die();

?>