<?php
error_reporting(E_ALL);
ini_set("display_errors",true);
/* @var $youtube Google_Service_YouTube */
//define('AMQP_DEBUG', true);
require __DIR__ . '/config.php';
require_once __DIR__ . '/vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Youtubedl\Youtubedl;

$client = new Google_Client();
$client->setDeveloperKey(YOUTUBE_KEY);
// Define an object that will be used to make all API requests.
$youtube = new Google_Service_YouTube($client);
// create a log channel
$log = new Logger('download');
$log->pushHandler(new StreamHandler(LOG_PATH, Logger::DEBUG));
$log->info('Consumer started');
// add records to the log

try {
    $log->info('Connecting to queue manager');
    $connection = new AMQPConnection(RABBIT_HOST, RABBIT_PORT, RABBIT_USER, RABBIT_PASS, RABBIT_VHOST);
    $log->info('Successfully connected to queue manager');
} catch (Exception $e) {
    $log->error('Problem connecting to queue', [$e->getMessage()]);
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


$channel->basic_consume(RABBIT_QUEUE, RABBIT_TAG, false, false, false, false, 'process_message');

/**
 * @param \PhpAmqpLib\Channel\AMQPChannel $channel
 * @param \PhpAmqpLib\Connection\AbstractConnection $connection
 */
function shutdown($channel, $connection) {
    $channel->close();
    $connection->close();
}

register_shutdown_function('shutdown', $channel, $connection);
// Loop as long as the channel has callbacks registered
while (count($channel->callbacks)) {
    $channel->wait();
}



/**
 * 
 * @global Logger $log
 * @global Google_Service_YouTube $youtube
 * @param type $message
 */
function process_message($message) {
    global $log, $youtube;
    $msg = explode('|', $message->body);
    $vid = $msg[0];
    $cid = $msg[1];

    if (is_dir(!DOWNLOAD_DIR . $cid))
        mkdir(DOWNLOAD_DIR . $cid);

    $log->info('New message recived', [$message->body]);
    $log->info('Getting info from youtube', [$message->body]);
    $collection = (new MongoDB\Client(MONGO_URI))->youtube->$cid;
    $channelCollection = (new MongoDB\Client(MONGO_URI))->youtube->channels;
    $searchResponse = $youtube->videos->listVideos('snippet,contentDetails', array(
        'id' => $vid
    ));
    $details = [
        'title' => $searchResponse['items'][0]['snippet']['title'],
        'duration' => $searchResponse['items'][0]['contentDetails']['duration'],
        'thumbnail' => [
            "url" => $searchResponse['items'][0]['snippet']['thumbnails']['default']['url'],
            "width" => $searchResponse['items'][0]['snippet']['thumbnails']['default']['width'],
            "height" => $searchResponse['items'][0]['snippet']['thumbnails']['default']['height'],
        ]
    ];
    $collection->updateOne(
            ['_id' => $vid], ['$set' => ['status' => 'downloading', 'detail' => $details]], ['upsert' => true]
    );
    $log->info('Download begins', [$message->body]);
//    sleep(10);

    $youtubedl=new Youtubedl();
    $youtubedl->getFileSystemOption()
              ->setOutput("'".DOWNLOAD_DIR.$channel."/".$vid.".%(ext)s'");
    $youtubedl->download($vid);

    $message->delivery_info['channel']->basic_ack($message->delivery_info['delivery_tag']);
    $collection->updateOne(
            ['_id' => $vid], ['$set' => ['status' => 'finished']], ['upsert' => true]
    );
    $channelCollection->updateOne(
            ['_id' => $cid], ['$inc' => ['processed' => 1]]
    );
    $log->info('Job is done', [$message->body]);
}