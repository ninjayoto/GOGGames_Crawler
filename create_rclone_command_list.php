<?php

$array = include 'all_games.php';

function createRcloneConfig(string $name, string $id, string $fileName)
{
    return <<<EOF
[$name]
type = drive
client_id =
client_secret =
scope = drive
root_folder_id = $id
service_account_file = $fileName


EOF;
}

$rcloneConfigString = '';


foreach ($array as $name => $game) {
    if (!isset($game['gdrive_folder'])) {
        continue;
    }

    foreach ($game as $downloadSource => $sources) {
        if ($downloadSource !== 'gdrive_folder') {
            continue;
        }
        foreach ($sources as $id => $source) {
            $rclone_endpoint_name = $name . '_' . $id;
            $rcloneConfigString .= createRcloneConfig($rclone_endpoint_name, explode('https://drive.google.com/open?id=', $source['link'])[1], '');
            $rclone_destination = 'GoodOldDownloads';
            $command = 'rclone copy ' . $rclone_endpoint_name . ': ' . $rclone_destination . ':' . $name . ' -vvv --config rclone.conf' . "\n";

            file_put_contents('rclone_commands.txt', $command, FILE_APPEND | LOCK_EX);
        }
    }
}

file_put_contents('rclone.conf', $rcloneConfigString);
