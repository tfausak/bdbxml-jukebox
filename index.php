<?php

# ------------------------------------------------------------------------------
# Define constants
# ------------------------------------------------------------------------------

define('ENV_HOME', 'db/');
define('DATABASE', 'library.dbxml');

# ------------------------------------------------------------------------------
# Set up database environment
# ------------------------------------------------------------------------------

# Initialize environment
$env = new Db4Env();
$env->open(ENV_HOME, DB_INIT_LOCK |
                     DB_INIT_LOG |
                     DB_INIT_MPOOL |
                     DB_INIT_TXN);

# Create XML manager and container
$mgr = new XmlManager($env, 0);
$con = $mgr->openContainer(DATABASE);

# Create query context
$qc = $mgr->createQueryContext(XmlQueryContext_LiveValues,
                               XmlQueryContext_Eager);
$qc->setDefaultCollection(DATABASE);

# ------------------------------------------------------------------------------
# Parse GET variables
# ------------------------------------------------------------------------------

$g = array('artist', 'album', 'search');
foreach ($g as $k => $i) {
    unset($g[$k]);

    $g[$i][] = isset($_GET[$i]) && $_GET[$i] !== '';

    if ($g[$i][0]) {
        $g[$i][] = $_GET[$i];
        $g[$i][] = htmlspecialchars($_GET[$i]);
    }
    else {
        $g[$i][1] = $g[$i][2] = '';
    }
}

# ------------------------------------------------------------------------------
# Get every artist in the database
# ------------------------------------------------------------------------------

$xquery = 'for $i in collection()/artists return $i';
$result = $mgr->query($xquery, $qc);
$artists = $result->next()->asString();

# ------------------------------------------------------------------------------
# Get every album in the database, or just albums by the selected artist
# ------------------------------------------------------------------------------

if ($g['artist'][0]) {
    $albums = array();
    $xquery = 'for $i in collection()/song
               where $i/artist eq $artist
               return $i/album';
    $qc->setVariableValue('artist', $g['artist'][1]);
    $result = $mgr->query($xquery, $qc);

    while ($result->hasNext()) {
        $albums[] = $result->next()->asString() . "\n";
    }

    $albums = array_unique($albums);
    $albums = '<albums>' . implode('', $albums) . '</albums>';
}
else {
    $xquery = 'for $i in collection()/albums return $i';
    $result = $mgr->query($xquery, $qc);
    $albums = $result->next()->asString();
}

# ------------------------------------------------------------------------------
# Build and execute XQuery
# ------------------------------------------------------------------------------

$xquery = 'for $i in collection()/song';

if ($g['artist'][0]) {
    $xquery .= ' where $i/artist eq $artist';
    $qc->setVariableValue('artist', $g['artist'][1]);
}

if ($g['album'][0]) {
    $xquery .= ($g['artist'][0]) ? ' and' : ' where';
    $xquery .= ' $i/album eq $album';
    $qc->setVariableValue('album', $g['album'][1]);
}

if ($g['search'][0]) {
    $xquery .= ($g['artist'][0] || $g['album'][0]) ? ' and' : ' where';
    $xquery .= ' (dbxml:contains($i/title, $search) or
                  dbxml:contains($i/album, $search) or
              dbxml:contains($i/artist, $search) or
              dbxml:contains($i/album_artist, $search))';
    $qc->setVariableValue('search', $g['search'][1]);
}

$xquery .= ' return $i';

$result = $mgr->query($xquery, $qc);
while ($result->hasNext()) {
    $songs[] = $result->next()->asString();
}

# ------------------------------------------------------------------------------
# Choose (up to) 10 random songs if no selection or search was done
# ------------------------------------------------------------------------------

if (!$g['artist'][0] && !$g['album'][0] && !$g['search'][0]) {
    $total = $con->getNumDocuments();
    $limit = min($total, 10);
    $rand = range(1, $total);
    shuffle($rand);
    $rand = array_slice($rand, 0, $limit);
    $tmp = array();

    foreach ($rand as $i) {
        $tmp[] = $songs[$i];
    }

    $songs = $tmp;
}

# ------------------------------------------------------------------------------
# Format XML
# ------------------------------------------------------------------------------

$songs = '<songs>' . implode("\n", $songs) . '</songs>';

$xml = <<<XML
<?xml version="1.0" encoding="utf-8"?>

<library>
    <get_artist>{$g['artist'][2]}</get_artist>
    <get_album>{$g['album'][2]}</get_album>
    <get_search>{$g['search'][2]}</get_search>
    <xquery>{$xquery}</xquery>

    {$artists}
    {$albums}
    {$songs}
</library>
XML;

# ------------------------------------------------------------------------------
# Transform and output XML
# ------------------------------------------------------------------------------

$cmd = 'xsltproc transform.xsl -';
$descriptorspec = array(
    '0' => array('pipe', 'r'),
    '1' => array('pipe', 'w')
);
$pipes = array();

$process = proc_open($cmd, $descriptorspec, $pipes);

if (!is_resource($process)) {
    exit(1);
}

fwrite($pipes[0], $xml);
fclose($pipes[0]);

echo stream_get_contents($pipes[1]);
fclose($pipes[1]);

proc_close($process);

?>
