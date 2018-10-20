<?php

$array = include 'all_games.php';

foreach ($array as $game) {
    if (!isset($game['gdrive_folder'])) {
        continue;
    }

    foreach ($game as $downloadSource => $links) {

        file_put_contents('urls/' . $downloadSource . '.txt', implode('', array_map(function ($link) {
            return $link['link'] . "\n";
        }, $links) ), FILE_APPEND | LOCK_EX);
    }

}