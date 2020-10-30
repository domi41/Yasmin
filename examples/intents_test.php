<?php

require_once __DIR__.'/../vendor/autoload.php';

use CharlotteDunois\Yasmin\WebSocket\Intents;

var_dump(Intents::all());
var_dump(Intents::default());
var_dump(array_sum(Intents::default()));
var_dump(Intents::only(['GUILD_MESSAGES']));
var_dump(Intents::except(['GUILD_PRESENCES']));
