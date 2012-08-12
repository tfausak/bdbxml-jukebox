<?php

require_once('config.php');

if (isset($_GET['add'])) {
    $_SESSION['playlist'][] = $_GET['add'];
    echo 'Added ' . $_GET['add'] . ' to your playlist.';

    exit();
}

if (isset($_GET['remove'])) {
    $key = array_search($_GET['remove'], $_SESSION['playlist']);
    unset($_SESSION['playlist'][$key]);
    echo 'Removed ' . $_GET['remove'] . ' from your playlist.';

    exit();
}

if (isset($_GET['clear'])) {
    unset($_SESSION['playlist']);
    echo 'Cleared your playlist.';

    exit();
}

$query = '
for $i in collection()//song[path=$path]
return <li>
    <a class="direct" href="{data($i/url)}" title="Listen to this song">
        <strong>{data($i/title)}</strong>
        <span>{string-join((data($i/album), data($i/artist)), " &#x00b7; ")}</span>
    </a>
    <a class="utility remove" href="playlist?remove={data($i/url)}">&#x00d7;</a>
</li>';

if (isset($_SESSION['playlist']) && count($_SESSION['playlist']) !== 0) {
    $count = 0;
    $content = '<ul id="songs">';

    foreach ($_SESSION['playlist'] as $song) {
        $qc->setVariableValue('path', $song);
        $exp = $mgr->prepare($query, $qc);
        $result = $exp->execute($qc);
        $result->reset();
        while ($result->hasNext()) {
            $content .= $result->next()->asString();
            $count++;
        }
    }

    $s = ($count === 1) ? '' : 's';
    $status = $count . ' song' . $s . ' in your playlist';
    $back = '<a class="back clear" href="playlist?clear=true">Clear</a>';
    $forward = '<a class="forward" href="play">Play</a>';

    $content .= '</ul>';
} else {
    $content = '
<h2>Whoa!</h2>

<p>There&#x2019;s nothing in your playlist yet! Why don&#x2019;t you <a href="search">search for something</a> to listen to?</p>
';
}

$title = 'Playlist &#x00b7; ';
print_page();

?>
