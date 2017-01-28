<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

define('RABBIT_HOST',"172.16.39.59");       
define('RABBIT_USER',"guest");
define('RABBIT_PASS',"guest");
define('RABBIT_PORT',5672);
define('RABBIT_VHOST','/');
define('RABBIT_QUEUE','download_queue');
define('RABBIT_EXCHANGE','download_exchange');
define('RABBIT_TAG','Masood');

define('MONGO_URI','mongodb://127.0.0.1/');

define('LOG_PATH',__DIR__.'/data.log');
define('DOWNLOAD_DIR', __DIR__.'/dl/');
define('YOUTUBE_KEY','XXXXXXXXXXXXXXXXXXXXXXXXXXXXXX');