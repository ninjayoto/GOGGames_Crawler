<?php
/**
 * Coded by fionera.
 */
require_once 'vendor/autoload.php';
require_once 'php-selector/selector.inc';

$loop = React\EventLoop\Factory::create();
$client = new React\HttpClient\Client($loop);

const goggames = 'https://goggames.goodolddownloads.com';
$sites = 79;
$requestsDone = 0;
$gameIDs = [];

// function to decrypt the text given
function dec($pswd, $text)
{
    $pswd = strtolower($pswd);

    // intialize variables
    $code = "";
    $ki = 0;
    $kl = strlen($pswd);
    $length = strlen($text);

    // iterate over each line in text
    for ($i = 0; $i < $length; $i++) {
        // if the letter is alpha, decrypt it
        if (ctype_alpha($text[$i])) {
            // uppercase
            if (ctype_upper($text[$i])) {
                $x = (ord($text[$i]) - ord("A")) - (ord($pswd[$ki]) - ord("a"));

                if ($x < 0) {
                    $x += 26;
                }

                $x = $x + ord("A");

                $text[$i] = chr($x);
            } // lowercase
            else {
                $x = (ord($text[$i]) - ord("a")) - (ord($pswd[$ki]) - ord("a"));

                if ($x < 0) {
                    $x += 26;
                }

                $x = $x + ord("a");

                $text[$i] = chr($x);
            }

            // update the index of key
            $ki++;
            if ($ki >= $kl) {
                $ki = 0;
            }
        }
    }

    // return the decrypted text
    return $text;
}

for ($i = 1; $i <= $sites; $i++) {
    $request = $client->request('GET', goggames . '/search/all/' . $i . '/title/asc/any');

    $request->on('response', function ($response) use (&$gameIDs, &$requestsDone) {

        $data = '';
        $response->on('data', function ($chunk) use (&$data) {
            $data .= $chunk;
        });
        $response->on('end', function () use (&$data, &$gameIDs, &$requestsDone) {
            $listAllDom = new SelectorDOM($data);

            $games = $listAllDom->select('div.game-blocks.grid-view > a');

            foreach ($games as $game) {
                $gameIDs[] = $game['attributes']['data-id'];
            }

            $requestsDone++;
        });
    });

    $request->on('error', function (\Exception $e) {
        echo $e;
    });

    $request->end();
}

$games = [];
$timer = $loop->addPeriodicTimer(0.1, function () use (&$requestsDone, &$timer, &$sites, $loop, &$gameIDs, $client, &$games) {
    echo 'Requests done: ' . $requestsDone . "\n";

    if ($requestsDone === $sites) {
        $loop->cancelTimer($timer);

        $newTimer = $loop->addPeriodicTimer(0.1, function () use (&$requestsDone, &$newTimer, $loop, &$gameIDs, &$games) {
            echo 'Remaining Games: ' . count($gameIDs) . "\n";
            echo 'Games done: ' . count($games) . "\n\n";

            if (count($gameIDs) === 0) {
                $loop->cancelTimer($newTimer);

                file_put_contents('all_games.php', "<?php\nreturn " . var_export($games, true) . ";\n");
            }
        });

        wait();
    }
});

$currentReqs = 0;
$currentKey = 0;
function wait()
{
    global $currentReqs;
    global $currentKey;
    global $loop;
    global $gameIDs;

    if ($currentReqs < 20) {
        if (isset($gameIDs[$currentKey])) {
            $currentReqs++;
            requestGame($gameIDs[$currentKey], $currentKey);
            $currentKey++;
        } else {
            return;
        }
    }

    $loop->addTimer(0.1, 'wait');
}


function requestGame($gameID, $key)
{
    global $client;
    global $gameIDs;
    global $games;
    global $currentReqs;

    $request = $client->request('GET', goggames . '/api/public/game?id=' . $gameID);

    $request->on('response', function ($response) use (&$gameIDs, $key, &$games, &$currentReqs) {

        $data = '';
        $response->on('data', function ($chunk) use (&$data) {
            $data .= $chunk;
        });

        $response->on('end', function () use (&$data, &$gameIDs, $key, &$games, &$currentReqs) {
            $data = json_decode($data, true);


            $urls = array_merge(...array_values($data['links']));

            $downloadLinks = [];
            foreach ($urls as $url) {
                foreach ($url['links'] as $link) {
                    for ($i = 0; $i < $data['memes']; $i++) {
                        $link['link'] = dec($data['dank'], $link['link']);
                    }

                    $downloadLinks[$url['slug']][] = $link;
                }

                $games[$data['slug']] = $downloadLinks;
            }

            unset($gameIDs[$key]);
            $currentReqs--;
        });
    });

    $request->on('error', function (\Exception $e) {
        echo $e;
    });

    $request->end();
}

$loop->run();
