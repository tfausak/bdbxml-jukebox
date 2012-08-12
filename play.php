<?php

require_once('config.php');

$meta = '<script type="text/javascript">';
$count = 1;

foreach ($_SESSION['playlist'] as $song) {
    # Escape single quotes
    $song = str_replace("'", "\'", $song);
    $delay = $count++ * 1000;
    $meta .= <<<JS
setTimeout('window.location="{$song}"', $delay);
JS;
}

$delay += 1000;
$meta .= <<<JS
setTimeout('window.location="http://music.thursday.com:8080/playlist"', $delay);
JS;
$meta .= '</script>';

$content = '<h2>Now playing</h2><p>The next song should start in a second.</p>';

print_page();

?>
