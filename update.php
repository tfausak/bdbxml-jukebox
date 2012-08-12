#!/usr/local/bin/php
<?php

# ------------------------------------------------------------------------------
# Define constants
# ------------------------------------------------------------------------------

# http://www.w3.org/TR/REC-xml/#NT-Char
define('XML_CHAR', '\x{9}\x{a}\x{d}\x{20}-\x{d7ff}\x{e000}-\x{fffd}\x{10000}-\x{10ffff}');

# http://www.w3.org/TR/REC-xml/#NT-NameStartChar
define('XML_NAME_START_CHAR', ':A-Z_a-z\x{c0}-\x{d6}\x{d8}-\x{f6}\x{f8}-\x{2ff}\x{370}-\x{37d}\x{37f}-\x{1fff}\x{200c}-\x{200d}\x{2070}-\x{218f}\x{2c00}-\x{2fef}\x{3001}-\x{d7ff}\x{f900}-\x{fdcf}\x{fdf0}-\x{fffd}\x{10000}-\x{effff}');

# http://www.w3.org/TR/REC-xml/#NT-NameChar
define('XML_NAME_CHAR', XML_NAME_START_CHAR . '-.0-9\x{b7}\x{300}-\x{36f}\x{203f}-\x{2040}');

# ------------------------------------------------------------------------------
# Set up program environment and load dependencies
# ------------------------------------------------------------------------------

# Report all errors (for debugging)
error_reporting(-1);

# Allow owner and group full permissions on files generate by this script
umask(002);

# Avoid PHP notices about timezones (actual timezone here is not important)
date_default_timezone_set('UTC');

# Load getID3() for parsing tags
require_once('./getid3/getid3/getid3.php');
require_once('./getid3/getid3/getid3.lib.php');

# ------------------------------------------------------------------------------
# Parse command-line options
# ------------------------------------------------------------------------------

$defaults = array(
    'd' => './library/',
    'e' => './db/',
    'f' => 'library.dbxml',
    'g' => 'music',
    'i' => null,
    'x' => null,
    'v' => false
);

$options = 'hd:e:f:g:i:x:v';
$options = getopt($options);

if ($options === false || isset($options['h'])) {
    echo <<<HELP
-d  Directory to scan for music files
    default: {$defaults['d']}
-e  Base directory for database environment
    default: {$defaults['e']}
-f  Database file to update or create
    default: {$defaults['f']}
-g  Group to allow access to database files
    default: {$defaults['g']}
-h  Show this help message and exit
-i  File extensions to include (ex: mp3,M4A,.WMa)
    default: all
-x  File extensions to exclude (ex: flac,M4P,.AaC)
    default: none
-v  Verbose mode

HELP;

    exit(0);
}

foreach ($defaults as $k => $v) {
    $$k = $v;

    if (isset($options[$k])) {
        $$k = $options[$k];
    }
}

if (substr($d, -1) !== '/') {
    $d .= '/';
}
if (substr($e, -1) !== '/') {
    $e .= '/';
}
$i = format_exts($i);
$x = format_exts($x);
$v = isset($options['v']);

define('DIRECTORY', $d);
define('ENV_HOME', $e);
define('DATABASE', $f);
define('GROUP', $g);
define('EXT_INCLUDE', serialize($i));
define('EXT_EXCLUDE', serialize($x));
define('VERBOSE', $v);

# ------------------------------------------------------------------------------
# Set up database environment
# ------------------------------------------------------------------------------

vecho('Initializing database environment...' . "\n");

if (!is_dir(ENV_HOME)) {
    mkdir(ENV_HOME);
    chgrp(ENV_HOME, GROUP);
    chmod(ENV_HOME, 02775);
    vecho('Created directory \'' . ENV_HOME . '\'.' . "\n");
}

$env = new Db4Env();
$env->open(ENV_HOME, DB_CREATE |
                     DB_INIT_LOCK |
                     DB_INIT_LOG |
                     DB_INIT_MPOOL |
                     DB_INIT_TXN);

$mgr = new XmlManager($env, 0);
if ($mgr->existsContainer(DATABASE)) {
    $con = $mgr->openContainer(DATABASE);
}
else {
    $con = $mgr->createContainer(DATABASE);
    $con->addIndex('', 'path', 'unique-node-element-equality-string');
    $con->addIndex('', 'title', 'node-element-substring-string');
    $con->addIndex('', 'artist', 'node-element-substring-string');
    $con->addIndex('', 'album', 'node-element-substring-string');
}

$qc = $mgr->createQueryContext(XmlQueryContext_LiveValues,
                               XmlQueryContext_Eager);
$qc->setDefaultCollection(DATABASE);
$uc = $mgr->createUpdateContext();

# ------------------------------------------------------------------------------
# Remove dead entries
# ------------------------------------------------------------------------------

vecho('Removing dead entries...' . "\n");

$txn = $mgr->createTransaction();
$include = unserialize(EXT_INCLUDE);
$exclude = unserialize(EXT_EXCLUDE);

$xquery = 'for $i in collection()/song/path return data($i)';
$result = $mgr->query($xquery, $qc);

while ($result->hasNext()) {
    $path = $result->next()->asString();
    $doc = $con->getDocument($path, DBXML_LAZY_DOCS);

    if (!is_readable($path)) {
        $con->deleteDocument($txn, $doc, $uc);
        vecho("Removed '{$path}' (unreadable).\n");
        continue;
    }

    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if ((!empty($include) && in_array($ext, $include) === false) ||
        (!empty($exclude) && in_array($ext, $exclude) !== false)) {
        $con->deleteDocument($txn, $doc, $uc);
        vecho("Removed '{$path}' (invalid extension).\n");
        continue;
    }

    $time = strtotime($doc->getMetaData('', 'filemtime')->asString());
    if ($time < filemtime($path)) {
        $con->deleteDocument($txn, $doc, $uc);
        vecho("Removed '{$path}' (stale).\n");
        continue;
    }
}

$txn->commit();

# ------------------------------------------------------------------------------
# Scan for music files
# ------------------------------------------------------------------------------

vecho('Scanning for files...' . "\n");

$files = scan(DIRECTORY);
$files = filter($files, unserialize(EXT_INCLUDE), unserialize(EXT_EXCLUDE));

if (!$files) {
    $files = array();
}

# ------------------------------------------------------------------------------
# Parse all music files found
# ------------------------------------------------------------------------------

vecho('Parsing files...' . "\n");

$txn = $mgr->createTransaction();

foreach ($files as $path) {
    if (document_exists($path, $con)) {
        continue;
    }

    $info = read_tag($path);
    if (!$info) {
        vecho("Failed to parse '{$path}'.\n");
        continue;
    }

    $song = array_to_xml($info, 'song');
    $con->putDocument($txn, $path, $song, $uc);

    $doc = $con->getDocument($path, DBXML_LAZY_DOCS);
    $time = new XmlValue(XmlValue_DATE_TIME, date('c', filemtime($path)));
    $doc->setMetaData('', 'filemtime', $time);
    $con->updateDocument($doc);

    vecho("Added '{$path}'.\n");
}

$txn->commit();

# ------------------------------------------------------------------------------
# Get every artist in the database
# ------------------------------------------------------------------------------

vecho('Generating a list of artists...' . "\n");

$artists = array();
$xquery = 'for $i in collection()/song order by $i/artist return $i/artist';
$result = $mgr->query($xquery, $qc);

while ($result->hasNext()) {
    $artists[] = $result->next()->asString();
}

$artists = array_unique($artists);
$artists = '<artists>' . implode('', $artists) . '</artists>';

if (document_exists('artists', $con)) {
    $con->deleteDocument('artists', $uc);
}
$con->putDocument('artists', $artists, $uc);

# ------------------------------------------------------------------------------
# Get every album in the database
# ------------------------------------------------------------------------------

vecho('Generating a list of albums...' . "\n");

$albums = array();
$xquery = 'for $i in collection()/song order by $i/album return '
        . '<album artist="{data($i/artist)}">{data($i/album)}</album>';
$result = $mgr->query($xquery, $qc);

while ($result->hasNext()) {
    $albums[] = $result->next()->asString() . "\n";
}

$albums = array_unique($albums);
$albums = '<albums>' . implode('', $albums) . '</albums>';

if (document_exists('albums', $con)) {
    $con->deleteDocument('albums', $uc);
}
$con->putDocument('albums', $albums, $uc);

# ------------------------------------------------------------------------------
# Functions
# ------------------------------------------------------------------------------

# Recursively converts an array into XML
function array_to_xml($array, $tag = 'root') {
    $tag = xml_validate_tag($tag);

    if (!is_array($array) || empty($array)) {
        return "<{$tag}/>";
    }

    $result = "<{$tag}>";

    foreach ($array as $key => $value) {
        $key = xml_validate_tag($key);

        if (empty($value) || !is_scalar($value) && !is_array($value)) {
            $result .= "<{$key}/>";
            continue;
        }

        if (is_array($value)) {
            $result .= array_to_xml($value, $key);
            continue;
        }
        
        $value = xml_validate_text($value);
        $value = htmlspecialchars($value);
        
        $result .= "<{$key}>{$value}</{$key}>";
    }

    $result .= "</{$tag}>";

    return $result;
}

# Determines if a document exists
function document_exists($name, &$con) {
    $result = false;
    
    try {
        $doc = $con->getDocument($name, DBXML_LAZY_DOCS);
        $result = true;
    }
    catch (XmlException $e) {
        if ($e->getExceptionCode() !== 11) {
            throw $e;
        }
    }
    
    return $result;
}

# Selects files with particular extensions
function filter($files, $include = array(), $exclude = array()) {
    $result = array();

    foreach ($files as $file) {
        $ext = pathinfo($file, PATHINFO_EXTENSION);

        if (!empty($include) && !in_array($ext, $include)) {
            continue;
        }

        if (in_array($ext, $exclude)) {
            continue;
        }

        $result[] = $file;
    }

    return $result;
}

# Formats file extensions
function format_exts($exts) {
    $result = array();

    $exts = explode(',', $exts);

    foreach ($exts as $ext) {
        if (empty($ext)) {
            continue;
        }

        if ($ext[0] === '.') {
            $ext = substr($ext, 1);
        }

        $result[] = strtolower($ext);
    }

    return $result;
}

# Gets song information from tag
function read_tag($file) {
    static $parser;

    if (!isset($parser)) {
        $parser = new GetID3;
        $parser->setOption(array(
            'encoding' => 'UTF-8',
            'option_md5_data' => false
        ));
    }

    $level = error_reporting(E_ALL);
    $info = $parser->analyze($file);
    getid3_lib::CopyTagsToComments($info);
    error_reporting($level);

    if (!isset($info['audio']) || isset($info['error'])) {
        return false;
    }

    $url = str_replace('%2F', '/', rawurlencode($file));

    $info = array(
        'path' => $file,
        'url' => $url,
        'track' => @end($info['comments']['track']),
        'title' => @end($info['comments']['title']),
        'time' => $info['playtime_string'],
        'artist' => @end($info['comments']['artist']),
        'album' => @end($info['comments']['album']),
        'year' => @end($info['comments']['year'])
    );
    
    if (empty($info['title'])) {
        $info['title'] = 'Unknown title';
    }
    if (empty($info['artist'])) {
        $info['artist'] = 'Unknown artist';
    }
    if (empty($info['album'])) {
        $info['album'] = 'Unknown album';
    }

    return $info;
}

# Scans a directory for files
function scan($dir, $recurse = true, &$result = array(), &$visited = array()) {
    if (!is_readable($dir) || !is_dir($dir)) {
        return false;
    }

    if (substr($dir, -1) !== '/') {
        $dir .= '/';
    }

    $dh = opendir($dir);
    if (!$dh) {
        return false;
    }

    rewinddir($dh);
    $files = array();

    while (($file = readdir($dh)) !== false) {
        if ($file === '.' || $file === '..') {
            continue;
        }

        $files[] = $file;
    }

    closedir($dh);
    natcasesort($files);
    $files = array_values($files);

    foreach ($files as $file) {
        $file = $dir . $file;
        $realpath = realpath($file);

        if (isset($visited[$realpath]) && $visited[$realpath]) {
            continue;
        }
        $visited[$realpath] = true;

        if (is_readable($file) && is_dir($file)) {
            if ($recurse) {
                scan($file, $recurse, $result, $visited);
            }

            continue;
        }

        $result[] = $file;
    }

    return $result;
}

# Echos only if verbose mode is on
function vecho($string, $stream = STDOUT) {
    if (!defined('VERBOSE') || !VERBOSE) {
        return;
    }
    
    fwrite($stream, $string);
}

# Validates an XML tag name, replacing bad characters with underscores ('_')
function xml_validate_tag($tag) {
    if (is_numeric($tag)) {
        return '_' . $tag;
    }
    
    if (empty($tag) || !is_string($tag)) {
        return 'unknown';
    }

    $search = '/^[' . XML_NAME_START_CHAR . '][' . XML_NAME_CHAR . ']*$/u';
    
    if (preg_match($search, $tag) === 1) {
        return $tag;
    }
    
    $regex = '/[^' . XML_NAME_START_CHAR . ']/u';
    $result = preg_replace($regex, '_', $tag[0]);
    
    $regex = '/[^' . XML_NAME_CHAR . ']/u';
    $result .= preg_replace($regex, '_', substr($tag, 1));

    # http://www.w3.org/TR/REC-xml/#dt-name
    if (preg_match('/^[Xx][Mm][Ll]/', $result) === 1) {
        $result = '_' . $result;
    }

    return $result;
}

# Replaces bad characters with underscores ('_')
function xml_validate_text($text) {
    $search = '/[^' . XML_CHAR . ']/u';
    $text = preg_replace($search, '_', $text);
    
    return $text;
}

?>
