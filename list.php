<?php

//define('AMQP_DEBUG', true);
require __DIR__.'/config.php';
require_once __DIR__ . '/vendor/autoload.php';
/* @var $collection MongoDB\Collection */
$cid = $_GET['id'];
$collection = (new MongoDB\Client(MONGO_URI))->youtube->$cid;

$videos = $collection->find();

//foreach ($cursor as $collection) {
//    if($collection['name'] == 'system.indexes')
//        continue;
//    echo $collection['name'], "\n";
//}
?>
<!DOCTYPE html>

<html>
    <head>
        <title>Video downloader</title>
        <link rel="stylesheet" href="css/bootstrap.css">
        <link rel="stylesheet" href="css/newcss.css">
        <script type="text/javascript" src="https://code.jquery.com/jquery-1.12.4.min.js"></script>
        
    </head>
    <body>
        <div class="container">
            <div class="well well-sm">
                <strong>Category : </strong> <?php echo $cid ?>
                
            </div>
            <div id="products" class="row list-group">
                <?php
//                var_dump($channels);die;
                foreach ($videos as $video) {
                    if($video['status'] == 'in-queue') {
                        $video['detail']['title'] = '-';
                        $video['detail']['duration'] = '-';
                        $video['detail']['thumbnail'] = [
                            "url" => 'http://placehold.it/120x90/000/fff',
                            "width" => 120,
                            "height" => 90,
                        ];
                    }
                    
//                    echo $channel;die;
                    echo '<div class="item  col-xs-4 col-lg-4 list-group-item">'
                    . '<div class="thumbnail">'
                            . '<img class="group list-group-image" src="'. $video['detail']['thumbnail']['url'] .'" height="90" />'
                            . '<div class="caption">'
                            . '<h4 class="group inner list-group-item-heading">'
                            .  $video['detail']['title'].'</h4>'
                            . '<p class="group inner list-group-item-text">'
                            . 'Video id : '.$video['_id'].'<br />'
                            . 'Duration : ' . $video['detail']['duration'] .'<br />'
                            . 'Status : '. $video['status'] .'<br />'
                            . '</div>'
                            . '</div>'
                            . '</div>'
                            ;
                    
                    
                    
                    
//                        echo ''
//                        . '<div class="panel panel-default">'
//                        . '<div class="panel-body" style="background-image:url('.$channel['thumbnail'].');background-size:100% 100%;">'
//                        . '<h3><span class="label label-default">' . $channel['title']. '</span></h3>'
//                        . '<p class="bg-primary">'
//                        . 'All Videos : ' . $channel['all'] .'<br />'
//                        . 'Processed Videos : ' . $channel['processed']
//                        . '</p>'
//                        . '</div>'
//                        . '</div>'
//                        . '</div>';
                }
                ?>
            </div>
        </div>


    </body>

</html>