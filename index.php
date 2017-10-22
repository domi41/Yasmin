<?php
/**
 * Yasmin
 * Copyright 2017 Charlotte Dunois, All Rights Reserved
 *
 * Website: https://charuru.moe
 * License: MIT
*/

$token = file_get_contents("Z:\\Eigene Dokumente\\Discord Bots\\Charuru Commando\\storage\\CharuruAlpha.token");

define('IN_DIR', str_replace('\\', '/', __DIR__));

spl_autoload_register(function ($name) {
    if(strpos($name, 'CharlotteDunois\\Yasmin') === 0) {
        $name = str_replace('CharlotteDunois\\Yasmin\\', '', $name);
        $name = str_replace('\\', '/', $name);
        
        if(file_exists(IN_DIR.'/'.$name.'.php')) {
            include_once(IN_DIR.'/'.$name.'.php');
            return true;
        }
    }
});
require_once(IN_DIR.'/vendor/autoload.php');

$client = new \CharlotteDunois\Yasmin\Client();

echo 'WS status is: '.$client->getWSstatus().PHP_EOL;

$client->on('debug', function ($debug) {
    echo $debug.PHP_EOL;
});
$client->on('error', function ($error) {
    echo $error.PHP_EOL;
});

$client->on('ready', function () use($client) {
    echo 'WS status is: '.$client->getWSstatus().PHP_EOL;
    
    $user = $client->getClientUser();
    echo 'Logged in as '.$user->tag.' created on '.$user->createdAt->format('d.m.Y H:i:s').PHP_EOL;
    
    $client->addPeriodicTimer(30, function ($client) {
        $client->getClientUser()->setGame('with Yasmin | '.\bin2hex(\random_bytes(3)));
    });
});
$client->on('disconnect', function ($code, $reason) {
    echo 'WS status is: '.$client->getWSstatus().PHP_EOL;
    echo 'Disconnected! (Code: '.$code.' | Reason: '.$reason.')'.PHP_EOL;
});
$client->on('reconnect', function () {
    echo 'WS status is: '.$client->getWSstatus().PHP_EOL;
    echo 'Reconnect happening!'.PHP_EOL;
});

$client->on('message', function ($message) use ($client) {
    echo 'Received Message from '.$message->author->tag.' in channel '.$message->channel->name.' with '.$message->attachments->count().' attachment(s) and '.\count($message->embeds).' embed(s)'.PHP_EOL;
});

$client->login($token)->done(function () use ($client) {
    $client->addPeriodicTimer(60, function ($client) {
        echo 'Avg. Ping is '.$client->getPing().'ms'.PHP_EOL;
    });
    
    /*$client->addTimer(10, function () use ($client) {
        //var_dump($client->channels);
        //var_dump($client->guilds);
        //var_dump($client->presences);
        //var_dump($client->users);
        
        echo 'Making API request...'.PHP_EOL;
        $client->apimanager()->endpoints->createMessage('323433852590751754', array('content' => 'Hello, my name is Onee-sama!'), array(
            array(
                'name' => 'file',
                'path' => 'C:\\Users\\Charlotte\\Downloads\\[HorribleSubs] Ousama Game - 01 [720p].mkv_snapshot_03.13_[2017.10.06_19.01.07].png',
                'filename' => 'uwa.png'
            )
        ))->then(function ($response) {
            var_dump($response);
        }, function ($error) {
            var_dump($error);
        });
    });*/
    
    $client->addTimer(500, function ($client) {
        echo 'Ending session'.PHP_EOL;
        $client->destroy()->then(function () use ($client) {
            echo 'WS status is: '.$client->getWSstatus().PHP_EOL;
        });
    });
});

$client->getLoop()->run();
