<?php

require_once __DIR__.'/../vendor/autoload.php';

use CharlotteDunois\Yasmin\WebSocket\Intents;

var_dump(Intents::all());
var_dump(Intents::default());
var_dump(decbin(Intents::bit(Intents::default())));
var_dump(Intents::only(['GUILD_MESSAGES']));
var_dump(decbin(Intents::bit(Intents::only(['DIRECT_MESSAGE_TYPING']))));
var_dump(Intents::except(['GUILD_PRESENCES']));
var_dump(decbin(array_sum(Intents::all())));
