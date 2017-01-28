<?php
/**
 * Library Requirements
 *
 * 1. Install composer (https://getcomposer.org)
 * 2. On the command line, change to this directory (api-samples/php)
 * 3. Require the google/apiclient library
 *    $ composer require google/apiclient:~2.0
 */
require __DIR__ . '/config.php';

if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
    throw new \Exception('please run "composer require google/apiclient:~2.0" in "' . __DIR__ . '"');
}

require_once __DIR__ . '/vendor/autoload.php';

session_start();

use phpFastCache\CacheManager;

// Setup File Path on your config files
CacheManager::setDefaultConfig(array(
    "path" => __DIR__ . '/tmp', // or in windows "C:/tmp/"
));

// In your class, function, you can call the Cache
$InstanceCache = CacheManager::getInstance('files');
//$resultsItem = $InstanceCache->getItem($_GET['q'].'--page10')->get();

$htmlBody = <<<END
<form method="GET">
  <div>
    Channel: <input type="search" id="q" name="q" placeholder="">
  </div>
  <input type="submit" value="Fetch">
</form>
END;

// This code will execute if the user entered a search query in the form
// and submitted the form. Otherwise, the page displays the form above.
if (isset($_GET['q'])) {
    $exp_time = 3600 * 24;
    /*
     * Set $DEVELOPER_KEY to the "API key" value from the "Access" tab of the
     * {{ Google Cloud Console }} <{{ https://cloud.google.com/console }}>
     * Please ensure that you have enabled the YouTube Data API for your project.
     */

    $client = new Google_Client();
    $client->setDeveloperKey(YOUTUBE_KEY);

    // Define an object that will be used to make all API requests.
    $youtube = new Google_Service_YouTube($client);
    $_GET['page'] = isset($_GET['page']) ? $_GET['page'] : 1;
    $htmlBody = '';
    try {

        // Call the search.list method to retrieve results matching the specified
        // query term.
        $token = isset($_GET['pageToken']) ? $_GET['pageToken'] : '';
        $resultsItem = $InstanceCache->getItem($_GET['q'] . '--page' . $_GET['page']);


        if (!$resultsItem->isHit()) {
            $searchResponse = $youtube->search->listSearch('id,snippet', array(
                'channelId' => $_GET['q'],
                'type' => 'video',
                'maxResults' => 50,
                'order' => 'date',
                'pageToken' => $token
            ));

            $all_rows = $searchResponse['pageInfo']['totalResults'];
            $resultsItem->set($searchResponse)->expiresAfter($exp_time);
            $InstanceCache->save($resultsItem);
            $sr = $searchResponse;
            $class = $_GET['page'] == 1 ? 'active' : '';
            $page = "<li class='$class'><a href='?q=$_GET[q]&page=1'>1</a></li>";

            for ($i = 50, $j = 2; $i <= $all_rows; $i+=50, $j++) {
                $tk = $sr['nextPageToken'];
                $sr = $youtube->search->listSearch('id,snippet', array(
                    'channelId' => $_GET['q'],
                    'type' => 'video',
                    'maxResults' => 50,
                    'order' => 'date',
                    'pageToken' => $tk
                ));
                $class = @$_GET['page'] == $j ? 'active' : '';
                $page .= "<li class='$class'><a href='?q=$_GET[q]&page=$j'>$j</a></li>";
                $item = $InstanceCache->getItem($_GET['q'] . '--page' . $j);
                $item->set($sr)->expiresAfter($exp_time);
                $InstanceCache->save($item);
            }
        } else {
            $searchResponse = $resultsItem->get();
            $all_rows = $searchResponse['pageInfo']['totalResults'];
            $class = $_GET['page'] == 1 ? 'active' : '';
            $page = "<li class='$class'><a href='?q=$_GET[q]&page=1'>1</a></li>";
            for ($i = 50, $j = 2; $i <= $all_rows; $i+=50, $j++) {
                $class = $_GET['page'] == $j ? 'active' : '';
                $page .= "<li class='$class'><a href='?q=$_GET[q]&page=$j'>$j</a></li>";
            }
        }

        $videos = '';
        $channels = '';
        $playlists = '';
        $videosRow = '';

        // Add each result to the appropriate list, and then display the lists of
        // matching videos, channels, and playlists.
        $nextPage = $searchResponse['nextPageToken'];
        $prevPage = $searchResponse['prevPageToken'];
        foreach ($searchResponse['items'] as $searchResult) {
            switch ($searchResult['id']['kind']) {
                case 'youtube#video':
                    $button = isset($_SESSION['videos'][$_GET['q']][$searchResult['id']['videoId']]) ? '<button class="btn btn-danger glyphicon glyphicon-remove remove" data-vid="' . $searchResult['id']['videoId'] . '" data-cid="' . $_GET['q'] . '">&nbsp;Remove</button>' : '<button class="btn btn-success glyphicon glyphicon-ok add"  data-vid="' . $searchResult['id']['videoId'] . '" data-cid="' . $_GET['q'] . '">&nbsp;Add</button>';
                    $videosRow .= '<tr>';
                    $videosRow .= '<td><img src="' . $searchResult['snippet']['thumbnails']['default']['url'] . '" width="' . $searchResult['snippet']['thumbnails']['default']['width'] . '" height="' . $searchResult['snippet']['thumbnails']['default']['height'] . '" /></td>';
                    $videosRow .= '<td>' . $searchResult['snippet']['title'] . '</td>';
                    $videosRow .= '<td>' . $button . '</td>';
                    $videosRow .= '</tr>';
                    break;
            }
        }
        if ($nextPage) {
            $query = "?q=$_GET[q]&page=$nextPage";
        }

        $htmlBody .= <<<END
    <ul class="pagination">
      $page
    </ul>
    <div class="pull-right"><button class="btn btn-primary run" data-cid="$_GET[q]">Add to download queue</button></div>
    <table class="table table-striped">
    <tr><td>Picture</td><td>Title</td><td>Operation</td></tr>
    $videosRow
    </table>

END;
    } catch (Google_Service_Exception $e) {
        $htmlBody .= sprintf('<p>A service error occurred: <code>%s</code></p>', htmlspecialchars($e->getMessage()));
    } catch (Google_Exception $e) {
        $htmlBody .= sprintf('<p>An client error occurred: <code>%s</code></p>', htmlspecialchars($e->getMessage()));
    }
}
?>

<!doctype html>
<html>
    <head>
        <title>YouTube Search</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/3.3.7/css/bootstrap.min.css">
        <script type="text/javascript" src="https://code.jquery.com/jquery-1.12.4.min.js"></script>
        <script type="text/javascript">
            $(function () {
                $(document).on('click', '.add', function (e) {
                    var button = $(this);
                    $.ajax({
                        'url': 'session.php',
                        'type': 'post',
                        'data': {
                            'cid': $(this).attr('data-cid'),
                            'vid': $(this).attr('data-vid')
                        },
                        success: function (data) {
                            button
                                    .removeClass()
                                    .addClass('btn btn-danger glyphicon glyphicon-remove remove')
                                    .html('&nbsp;Remove');
                        }
                    })
                });

                $(document).on('click', '.remove', function (e) {
                    var button = $(this);
                    $.ajax({
                        'url': 'session.php',
                        'type': 'post',
                        'data': {
                            'cid': $(this).attr('data-cid'),
                            'vid': $(this).attr('data-vid'),
                            'delete': true
                        },
                        success: function (data) {
                            button
                                    .removeClass()
                                    .addClass('btn btn-success glyphicon glyphicon-ok add')
                                    .html('&nbsp;Add');
                        }
                    })
                });

                $(document).on('click', '.run', function (e) {
                    var button = $(this);
                    $.ajax({
                        'url': 'queue_pusher.php',
                        'type': 'get',
                        'data': {
                            'cid': $(this).attr('data-cid'),
                        },
                        success: function (data) {
                            location.href="index.php"
                        }
                    })
                });
            })
        </script>
    </head>
    <body>

<?php
$collection = (new MongoDB\Client(MONGO_URI))->youtube->channels;

$channels = $collection->find();
?>
        <div class="container">
            <div class="well well-sm">
                <strong>Fetch a new channel : </strong>

            <?= $htmlBody ?>
            </div>
            <div class="well well-sm">
                <strong>Categories : </strong>

            </div>
            <div id="products" class="row grid-group-item">
                
<?php

foreach ($channels as $channel) {
        echo '<div class="item  col-xs-4 col-lg-4">
            <div class="thumbnail">
                <img class="group list-group-image" src="'.$channel['thumbnail'].'" height="219" alt="'.$channel['_id'].' image is not loaded" />
                <div class="caption">
                    <h4 class="group inner list-group-item-heading">
                        '.$channel['title'].'</h4>
                    <p class="group inner list-group-item-text">
                        All Videos : ' . $channel['all'] . '<br />
                        Processed Videos : ' . $channel['processed'].'</p>
                    <div class="row">
                        <div class="col-xs-12 col-md-6" style="margin-top:5px">
                            <a class="btn btn-success" href="list.php?id='.$channel['_id'].'">View</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>';
}
?>
            </div>
        </div>
    </body>
</html>


<?php
