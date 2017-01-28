<?php
/* @var $col MongoDB\Collection */
/* @var $channel AMQPChannel */
session_start();
require __DIR__.'/config.php';


require_once __DIR__ . '/vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPConnection;
use PhpAmqpLib\Message\AMQPMessage;

$cid = $_GET['cid'];
$col = (new MongoDB\Client(MONGO_URI))->youtube->$cid;


//var_dump($_SESSION['videos'][$cid]);die;
try {
    $connection = new AMQPConnection(RABBIT_HOST, RABBIT_PORT, RABBIT_USER,
            RABBIT_PASS,RABBIT_VHOST);    
} catch(Exception $e) {
    echo $e->getMessage();
}

$channel = $connection->channel();
$channel->queue_declare(
          RABBIT_QUEUE, //queue name
          false,  //passive -  check whether an exchange exists without modifying server state
          true,   //durable - RabbitMQ will never lose the queue if a crash occurs
          false,  //exclusive - if queue only will be used by one connection
          false   //autodelete - queue is deleted when last consumer unsubscribes
);


$channel->exchange_declare(RABBIT_EXCHANGE, 'direct', false, true, false);
$channel->queue_bind(RABBIT_QUEUE, RABBIT_EXCHANGE);

$mongo_put = [];
foreach($_SESSION['videos'][$cid] as $k=>$video) {
    if(strlen($k) != 11) {
        continue;
    }
    $mongo_put[] = ['_id'=>$k, 'status'=>'in-queue'];
    $msg_body = $k.'|'.$cid;
    $msg = new AMQPMessage($msg_body, array('content_type' => 'text/plain', 'delivery_mode' => 2));
    $channel->basic_publish($msg,RABBIT_EXCHANGE);
}
$col->insertMany($mongo_put);


$client = new Google_Client();
$client->setDeveloperKey(YOUTUBE_KEY);

// Define an object that will be used to make all API requests.
$youtube = new Google_Service_YouTube($client);
$searchResponse = $youtube->channels->listchannels('snippet', array(
        'id' => $cid
    ));
//print_r( $searchResponse['items'][0]);
//die;
$put = [
        '_id'   =>$cid,
        'title' => $searchResponse['items'][0]['snippet']['title'],
        'thumbnail' => $searchResponse['items'][0]['snippet']['thumbnails']['high']['url'],
        'processed' => 0,
        'all'   =>  count($mongo_put)
        ];
    
/* @var $collection MongoDB\Collection */
$collection = (new MongoDB\Client(MONGO_URI))->youtube->channels;
//var_dump($put);die;
$collection->insertOne($put);
    
unset($_SESSION['videos'][$cid]);

$channel->close();
$connection->close();