<?php

require_once('config.php');

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
# Parse GET variables
# ------------------------------------------------------------------------------

$g = array('artist', 'album', 'search');

# [0]: true if variable is set and not empty
# [1]: value of variable or an empty string if not [0]
# [2]: [1] with HTML entities escaped
foreach ($g as $k => $v) {
    unset($g[$k]);

    $_GET[$v] = isset($_GET[$v]) ? trim($_GET[$v]) : '';
    
    $g[$v][0] = isset($_GET[$v]) && !empty($_GET[$v]);
    $g[$v][1] = ($g[$v][0]) ? $_GET[$v] : '';
    $g[$v][2] = htmlspecialchars($g[$v][1]);
}

# ------------------------------------------------------------------------------
# Create a list of artists
# ------------------------------------------------------------------------------
/*
$artists = array();
$xquery = 'for $i in distinct-values(collection()/artists/artist) '
        . 'order by $i '
        . 'return data($i)';
$result = $mgr->query($xquery, $qc);

while ($result->hasNext()) {
    $artists[] = $result->next()->asString();
}

natcasesort($artists);
*/
# ------------------------------------------------------------------------------
# Create a list of albums
# ------------------------------------------------------------------------------
/*
$albums = array();
$xquery = 'for $i in distinct-values(collection()/albums/album) '
        . 'order by $i '
        . 'return data($i)';

if ($g['artist'][0]) {
    $xquery = 'for $i in collection()/albums/album '
            . 'where $i/@artist eq $artist '
            . 'order by $i/album '
            . 'return data($i)';
    $qc->setVariableValue('artist', $g['artist'][1]);
}

$result = $mgr->query($xquery, $qc);

while ($result->hasNext()) {
    $albums[] = $result->next()->asString();
}

natcasesort($albums);
*/
# ------------------------------------------------------------------------------
# Build query
# ------------------------------------------------------------------------------

if ($g['artist'][0] || $g['album'][0] || $g['search'][0]) {
    $xquery = 'for $i in collection()/song ';
}
else {
    $total = $con->getNumDocuments();
    $limit = min($total, RAND_LIMIT);
    $rand = range(1, $total);
    shuffle($rand);
    $rand = array_slice($rand, 0, $limit);
    $rand = '(' . implode(', ', $rand) . ')';

    $xquery = 'for $i at $j in collection()/song where $j = ' . $rand . ' ';
}

if ($g['artist'][0]) {
    $xquery .= 'where $i/artist eq $artist ';
    $qc->setVariableValue('artist', $g['artist'][1]);
}
if ($g['album'][0]) {
    $xquery .= ($g['artist'][0]) ? 'and ' : 'where ';
    $xquery .= '$i/album eq $album ';
    $qc->setVariableValue('album', $g['album'][1]);
}
if ($g['search'][0]) {
    $xquery .= ($g['artist'][0] || $g['album'][0]) ? 'and ' : 'where ';
    $xquery .= '(dbxml:contains($i/title, $search) or '
             . 'dbxml:contains($i/artist, $search) or '
             . 'dbxml:contains($i/album, $search)) ';
    $qc->setVariableValue('search', $g['search'][1]);
}

$xquery .= 'return
<li class="song">
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
            <a class="add"
               href="playlist.php?add={data($i/url)}"
               title="Add this song to your playlist">
                {string("+")}
            </a>
        </li>
    </ul>
</li>';

# ------------------------------------------------------------------------------
# Execute query
# ------------------------------------------------------------------------------

$songs = array();
$result = $mgr->query($xquery, $qc);

while ($result->hasNext()) {
    $songs[] = $result->next()->asString();
}

if (!$g['artist'][0] && !$g['album'][0] && !$g['search'][0]) {
    shuffle($songs);
}

# ------------------------------------------------------------------------------
# Format artist list
# ------------------------------------------------------------------------------
/*
foreach ($artists as &$artist) {
    $artist = htmlspecialchars($artist);
    $tmp = '<option';
    
    if ($artist === $g['artist'][2]) {
        $tmp .= ' selected="selected"';
    }
    
    $artist = $tmp . '>' . $artist . '</option>';
}

$count = count($artists);
$artists = implode('', $artists);
$artists = <<<HTML
<select id="artist" name="artist">
    <option value="">All artists ({$count})</option>
    {$artists}
</select>
HTML;
*/
# ------------------------------------------------------------------------------
# Format album list
# ------------------------------------------------------------------------------
/*
foreach ($albums as &$album) {
    $album = htmlspecialchars($album);
    $tmp = '<option';
    
    if ($album === $g['album'][2]) {
        $tmp .= ' selected="selected"';
    }
    
    $album = $tmp . '>' . $album . '</option>';
}

$count = count($albums);
$albums = implode('', $albums);
$albums = <<<HTML
<select id="album" name="album">
    <option value="">All albums ({$count})</option>
    {$albums}
</select>
HTML;
*/
# ------------------------------------------------------------------------------
# Format some stray variables
# ------------------------------------------------------------------------------

$songs = implode("\n\t", $songs);

$start_over = '';
if ($g['artist'][0] || $g['album'][0] || $g['search'][0]) {
    $start_over = <<<HTML
<p id="nav-backward">
    <a href="./"
       title="Return to the home page">
        Start over
    </a>
</p>
HTML;
}

$playlist = <<<HTML
<p id="nav-forward">
    <a href="playlist.php"
       title="Go to your playlist">
        Playlist
    </a>
</p>
HTML;

# ------------------------------------------------------------------------------
# Output XHTML
# ------------------------------------------------------------------------------

$content = <<<HTML
{$start_over}
{$playlist}

<form action="index.php" id="query" method="get">
    <fieldset>
        <legend>Find something to listen to</legend>

        <ul>
            <!--
            <li>
                <label for="artist">Artist</label>

{$artists}
            </li>
        
            <li>
                <label for="album">Album</label>

{$albums}
            </li>
            -->
        
            <li>
                <label for="search">Search</label>

                <input id="search" name="search" type="text" value="{$g[$v][2]}"/>
            </li>
        
            <li class="no-label">
                <input type="submit" value="Go"/>
            </li>
        </ul>
    </fieldset>
</form>

<ul id="songs">
    {$songs}
</ul>
HTML;

$page = new Page();
$page->output($content);

?>
