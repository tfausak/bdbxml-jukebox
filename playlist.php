<?php

require_once('config.php');

# ------------------------------------------------------------------------------
# GET actions
# ------------------------------------------------------------------------------

if (isset($_GET['add'])) {
    $song = trim($_GET['add']);
    
    if (empty($song)) {
        exit(1);
    }
    
    $_SESSION['playlist'][] = $song;

    exit('Added ' . $song . ' to your playlist.');
}

if (isset($_GET['remove'])) {
    $key = trim($_GET['remove']);
    
    if (!isset($_SESSION['playlist'][$key])) {
        exit(1);
    }
    
    $song = $_SESSION['playlist'][$key];
    unset($_SESSION['playlist'][$key]);

    exit('Removed ' . $song . ' from your playlist.');
}

if (isset($_GET['up'])) {
    $key = trim($_GET['up']);
    $keys = array_keys($_SESSION['playlist']);
    $key = array_search($key, $keys);

    if ($key === false) {
        exit(1);
    }

    $a = $_SESSION['playlist'][$keys[$key - 1]];
    $b = $_SESSION['playlist'][$keys[$key]];

    echo $a . "\n" . $b;

    exit();
}

if (isset($_GET['down'])) {
    $key = trim($_GET['down']);
    
    if (!isset($_SESSION['playlist'][$key])) {
        exit(1);
    }

    $song = $_SESSION['playlist'][$key];
    $tmp = $_SESSION['playlist'][$key + 1];
    $_SESSION['playlist'][$key + 1] = $song;
    $_SESSION['playlist'][$key] = $tmp;

    exit('Moved ' . $song . ' down.');
}

if (isset($_GET['clear'])) {
    unset($_SESSION['playlist']);
    
    exit('Cleared your playlist.');
}

# ------------------------------------------------------------------------------
# Set up database environment
# ------------------------------------------------------------------------------

$env = new Db4Env();
$env->open(ENV_HOME, DB_INIT_LOCK |
                     DB_INIT_LOG |
                     DB_INIT_MPOOL |
                     DB_INIT_TXN);

$mgr = new XmlManager($env, 0);
$con = $mgr->openContainer(DATABASE);

$qc = $mgr->createQueryContext(XmlQueryContext_LiveValues,
                               XmlQueryContext_Eager);
$qc->setDefaultCollection(DATABASE);

# ------------------------------------------------------------------------------
# Build query
# ------------------------------------------------------------------------------

$xquery = 'for $i in collection()//song[path=$path]
return
<li class="song" id="song-{$key}">
    <a class="title"
       href="{data($i/url)}"
       title="Listen to this song">
        {data($i/title)}
    </a>

    <span class="hidden"> by </span>

    <a class="artist"
       href="index.php?artist={data($i/artist)}"
       title="Find more songs by this artist">
        {data($i/artist)}
    </a>

    <span class="hidden"> from </span>

    <a class="album"
       href="index.php?album={data($i/album)}"
       title="Find more songs from this album">
        {data($i/album)}
    </a>

    <ul class="utility">
        <li>
        {if ($key != $first)
            then <a class="up" href="playlist.php?up={$key}" title="Move this song up">&#x2191;</a>
            else <a class="invisible">&#x2191;</a>}
        </li>

        <li>
            <a class="remove" href="playlist.php?remove={$key}" title="Remove this song from your playlist">&#xd7;</a>
        </li>

        <li>
        {if ($key != $last)
            then <a class="down" href="playlist.php?down={$key}" title="Move this song down">&#x2193;</a>
            else <a class="invisible">&#x2193;</a>}
        </li>
    </ul>
</li>';

# ------------------------------------------------------------------------------
# Execute query
# ------------------------------------------------------------------------------

$songs = array();
$keys = array_keys($_SESSION['playlist']);
$first = reset($keys);
$last = end($keys);

foreach ($_SESSION['playlist'] as $key => $song) {
    $qc->setVariableValue('path', $song);
    $qc->setVariableValue('key', $key);
    $qc->setVariableValue('first', $first);
    $qc->setVariableValue('last', $last);
    $exp = $mgr->prepare($xquery, $qc);
    $result = $exp->execute($qc);
    
    while ($result->hasNext()) {
        $songs[] = $result->next()->asString();
    }
}

# ------------------------------------------------------------------------------
# Create playlist
# ------------------------------------------------------------------------------

$qtnext = array();
$i = 0;

foreach ($_SESSION['playlist'] as $song) {
    if ($i !== 0) {
        $qtnext[] = '<param name="qtnext' . $i . '" value="<' . $song . '>T<myself>"/>';
    }

    $i++;
}

$qtnext = implode("\n\t", $qtnext);

$html_object = <<<HTML
<object id="playlist">
    <param name="src" value="{$_SESSION['playlist'][0]}"/>
    <param name="target" value="myself"/>
    {$qtnext}
</object>
HTML;

# ------------------------------------------------------------------------------
# Format output
# ------------------------------------------------------------------------------

$songs = implode("\n\t", $songs);

$clear = '';
if (count($_SESSION['playlist']) > 0) {
    $clear = <<<HTML
<p id="nav-backward">
    <a class="clear"
       href="playlist.php?clear"
       id="clear"
       title="Clear your playlist">
        Clear
    </a>
</p>
HTML;
}

$content = <<<HTML
{$clear}

<p id="nav-forward">
    <a href="./"
       title="Find more songs for your playlist">
        Search
    </a>
</p>

{$html_object}

<ul id="songs">
    {$songs}
</ul>
HTML;

$page = new Page();
$page->output($content);

?>
