#!/usr/local/bin/php
<?php

# ------------------------------------------------------------------------------
# Set up program environment
# ------------------------------------------------------------------------------

# For debugging
ini_set('error_reporting', E_ALL | E_STRICT);

# Allow owner and group full permissions on files generate by this script
umask(002);

# Avoid PHP notices about timezones (actual timezone here is not important)
date_default_timezone_set('UTC');

# Load GetID3 library for parsing ID3v1 and ID3v2 tags
require_once('getid3/getid3.php');

# ------------------------------------------------------------------------------
# Parse command-line options
# ------------------------------------------------------------------------------

# Default values for command-line options
$defaults = array(
    'd' => 'library/',
    'e' => 'db/',
    'f' => 'library.dbxml',
    'i' => null,
    'x' => null,
    'v' => false
);

# Get command-line options
$options = 'h'  # Help
         . 'd:' # Directory
         . 'e:' # Environment
         . 'f:' # File
         . 'i:' # Include
         . 'x:' # Exclude
         . 'v'  # Verbose
         ;
$options = getopt($options);

# Show help if getopt() failed or -h was passed
if ($options === false || isset($options['h'])) {
    echo <<<HELP
-d  Directory to scan for music files
    default: {$defaults['d']}
-e  Base directory for database environment
    default: {$defaults['e']}
-f  Database file to update or create
    default: {$defaults['f']}
-i  File extensions to include (ex: mp3,M4A,.WMa)
    default: all
-x  File extensions to exclude (ex: flac,M4P,.AaC)
    default: none
-v  Verbose mode
-h  Show this help message

HELP;

    exit(0);
}

# Set options as either defaults or passed values
foreach ($defaults as $k => $v) {
    $$k = $v;

    if (isset($options[$k])) {
        $$k = $options[$k];
    }
}

# Re-format some options
if (substr($d, -1) !== '/') {
    $d .= '/';
}
if (substr($e, -1) !== '/') {
    $e .= '/';
}
$i = format_exts($i);
$x = format_exts($x);
$v = isset($options['v']);

# Write options to constants
define('DIRECTORY', $d);
define('ENV_HOME', $e);
define('DATABASE', $f);
define('EXT_INCLUDE', serialize($i));
define('EXT_EXCLUDE', serialize($x));
define('VERBOSE', $v);

# ------------------------------------------------------------------------------
# Set up database environment
# ------------------------------------------------------------------------------

vecho("Initializing database environment...\n");

# Create home directory if it does not exist
if (!is_dir(ENV_HOME)) {
    die('Home directory \'' . ENV_HOME . '\' does not exist.' . "\n");
}

# Initialize environment
$env = new Db4Env();
$env->open(ENV_HOME, DB_CREATE |
                     DB_INIT_LOCK |
                     DB_INIT_LOG |
                     DB_INIT_MPOOL |
                     DB_INIT_TXN);

# Create XML manager and container
$mgr = new XmlManager($env, 0);
if ($mgr->existsContainer(DATABASE)) {
    $con = $mgr->openContainer(DATABASE);
}
else {
    $con = $mgr->createContainer(DATABASE);
    $con->addIndex('', 'filemtime', 'node-metadata-equality-dateTime');
    $con->addIndex('', 'path', 'unique-node-element-equality-string');
    $con->addIndex('', 'title', 'node-element-substring-string');
    $con->addIndex('', 'artist', 'node-element-substring-string');
    $con->addIndex('', 'album', 'node-element-substring-string');
    $con->addIndex('', 'album_artist', 'node-element-substring-string');
}

# Create query and update contexts
$qc = $mgr->createQueryContext(XmlQueryContext_LiveValues,
                               XmlQueryContext_Eager);
$qc->setDefaultCollection(DATABASE);
$uc = $mgr->createUpdateContext();

# ------------------------------------------------------------------------------
# Remove dead entries
# ------------------------------------------------------------------------------

vecho("Removing dead entries...\n");

# Create a transaction to use for deleting entries
$txn = $mgr->createTransaction();

# Build and execute XQuery
$xquery = 'for $i in collection()//song return data($i/path)';
$result = $mgr->query($xquery, $qc);

# Iterate over results
while ($result->hasNext()) {
    $path = $result->next()->asString();
    $doc = $con->getDocument($path, DBXML_LAZY_DOCS);

    # Ensure file exists and is readable
    if (!is_readable($path)) {
        $con->deleteDocument($txn, $doc, $uc);

        vecho("Removed dead entry '{$path}'.\n");
        continue;
    }

    # Ensure file has a desired extension
    $ext = pathinfo($path, PATHINFO_EXTENSION);
    if ((EXT_INCLUDE !== null && stripos(EXT_INCLUDE, $ext) === false) ||
        (EXT_EXCLUDE !== null && stripos(EXT_EXCLUDE, $ext) !== false)) {
        $con->deleteDocument($txn, $doc, $uc);

        vecho("Removed unwanted entry '{$path}'.\n");
        continue;
    }

    # Ensure database entry is current
    $time = strtotime($doc->getMetaData('', 'filemtime')->asString());
    if ($time < filemtime($path)) {
        $con->deleteDocument($txn, $doc, $uc);

        vecho("Removed out of date entry '{$path}'.\n");
        continue;
    }
}

$txn->commit();

# ------------------------------------------------------------------------------
# Scan for music files
# ------------------------------------------------------------------------------

vecho("Scanning for files...\n");

$files = scan(DIRECTORY);
$files = filter($files, unserialize(EXT_INCLUDE), unserialize(EXT_EXCLUDE));

if (!$files) {
    $files = array();
}

# ------------------------------------------------------------------------------
# Parse all music files found
# ------------------------------------------------------------------------------

vecho("Parsing all files found...\n");

# Create a transaction to use for inserting entries
$txn = $mgr->createTransaction();

# Iterate over files
foreach ($files as $path) {
    # Skip documents that already exist
    if (document_exists($path, $con)) {
        continue;
    }

    # Parse the ID3 tag on the file
    $tag = read_tag($path);
    if (!$tag) {
        vecho("Failed to parse '{$path}'.\n");
        continue;
    }

    # Transform tag information into XML and put the document
    $song = array_to_xml($tag, 'song');
    $con->putDocument($txn, $path, $song, $uc);

    # Add metadata to the document
    $doc = $con->getDocument($path, DBXML_LAZY_DOCS);
    $time = new XmlValue(XmlValue_DATE_TIME, date('c', filemtime($path)));
    $doc->setMetaData('', 'filemtime', $time);
    $con->updateDocument($doc);

    vecho("Added new entry '{$path}'.\n");
}

$txn->commit();

# ------------------------------------------------------------------------------
# Get every artist in the database
# ------------------------------------------------------------------------------

vecho("Creating a list of artists...\n");

# Initialize an array to hold artists
$artists = array();

# Build and execute XQuery
$xquery = 'for $i in collection()/song order by $i/artist return $i/artist';
$result = $mgr->query($xquery, $qc);

# Iterate over artists
while ($result->hasNext()) {
    $artists[] = $result->next()->asString();
}

# Keep unique artists (i.e., only one of each)
$artists = array_unique($artists);
$artists = '<artists count="' . count($artists) . '">'
         . implode('', $artists)
         . '</artists>';

# Add XML to database
if (document_exists('artists', $con)) {
    $con->deleteDocument('artists', $uc);
}
$con->putDocument('artists', $artists, $uc);

# ------------------------------------------------------------------------------
# Get every album in the database
# ------------------------------------------------------------------------------

vecho("Creating a list of albums...\n");

# Initialize an array to hold albums
$albums = array();

# Build and execute XQuery
$xquery = 'for $i in collection()/song order by $i/album return $i/album';
$result = $mgr->query($xquery, $qc);

# Iterate over albums
while ($result->hasNext()) {
    $albums[] = $result->next()->asString() . "\n";
}

# Keep unique albums (i.e., only one of each)
$albums = array_unique($albums);
$albums = '<albums count="' . count($albums) . '">'
        . implode('', $albums)
        . '</albums>';

# Add XML to database
if (document_exists('albums', $con)) {
    $con->deleteDocument('albums', $uc);
}
$con->putDocument('albums', $albums, $uc);

# ------------------------------------------------------------------------------
# Functions
# ------------------------------------------------------------------------------

# Format an array into XML (i.e., 'key' => 'value' becomes <key>value</key>)
# $array: array to format into XML
# $tag: parent tag to wrap XML in
# Returns XML as a string
function array_to_xml($array, $tag) {
    # Replace whitespce in tag with underscores
    $tag = preg_replace('/\s/', '_', $tag);

    if (!is_array($array) || count($array) === 0) {
        return '<' . $tag . '/>';
    }

    $result = '<' . $tag . '>';
    foreach ($array as $k => $v) {
        # Replace whitespace in keys with underscores
        $k = preg_replace('/\s/', '_', $k);

        # Escape entities in values
        $v = htmlspecialchars($v);

        $result .= '<' . $k . '>' . $v . '</' . $k . '>';
    }
    $result .= '</' . $tag . '>';

    return $result;
}

# Determines if a document exists
# $name: name of document to check for
# $con: container to look in
# Returns true or false
function document_exists($name, &$con) {
    $result = false;
    
    try {
        $doc = $con->getDocument($name, DBXML_LAZY_DOCS);
        $result = true;
    }
    catch (XmlException $e) {
        if ($e->getExceptionCode() !== 11) {
            throw new XmlException($e);
        }
    }
    
    return $result;
}

# Selects files from $files that have particular extensions
# $files: array of files to check
# $include: array of extensions to include
# $exclude: array of extensions to exclude
# Returns an array of files
function filter($files, $include = array(), $exclude = array()) {
    $result = array();

    foreach ($files as $file) {
        $ext = pathinfo($file, PATHINFO_EXTENSION);

        # Skip files without extensions in $include
        if (!empty($include) && !in_array($ext, $include)) {
            continue;
        }

        # Skip files with extensions in $exclude
        if (in_array($ext, $exclude)) {
            continue;
        }

        $result[] = $file;
    }

    return $result;
}

# Formats a list of file extensions
# $exts: comma-delimited list of extensions
# Returns an array of lowercase extensions without periods
function format_exts($exts) {
    $result = array();

    $exts = explode(',', $exts);

    foreach ($exts as $ext) {
        if (empty($ext)) {
            continue;
        }

        # Strip periods
        if ($ext{0} === '.') {
            $ext = substr($ext, 1);
        }

        $result[] = strtolower($ext);
    }

    return $result;
}

# Reads ID3 (v1 or v2) from a file
# $file: path to file to read and parse
# Returns an array of ID3 info or false on failure
function read_tag($file) {
    static $parser;

    if (!isset($parser)) {
        $parser = new GetID3;
    }

    # Skip songs with invalid tags
    try {
        $song = $parser->Analyze($file);
    }
    catch (getid3_exception $e) {
        return false;
    }

    # Escape URLs but keep forward slashes
    $url = str_replace('%2F', '/', rawurlencode($file));

    $result = array
    (
        'path' => $file,
        'url' => $url,
        'title' => @end($song['comments']['title']),
        'artist' => @end($song['comments']['artist']),
        'album' => @end($song['comments']['album']),
        'album artist' => @end($song['comments']['text'])
    );

    # Fill in album artist with artist if it doesn't exist
    #if (trim($result['album artist']) === '') {
        #$result['album artist'] = $result['artist'];
    #}

    # Trim and escape all values
    foreach ($result as $k => &$v) {
        if ($k === 'path' || $k === 'url') {
            continue;
        }

        $v = trim($v);

        if ($v === '') {
            $v = 'Unknown';
            continue;
        }

        $v = iconv($parser->encoding, 'UTF-8', $v);
    }

    return $result;
}

# Recursively scans a directory for readable files
# $dir: directory to search for files
# $result: list of files found
# $visited: directories that have been scanned already
# Returns an array of files or false on failure
function scan($dir, &$result = array(), &$visited = array()) {
    # Fail if $dir is not readable or not a directory
    if (!is_readable($dir) || !is_dir($dir)) {
        return false;
    }

    # Append a trailing slash to the directory
    if (substr($dir, -1) !== '/') {
        $dir .= '/';
    }

    # Get a list of files in $dir
    $files = scandir($dir);

    foreach ($files as $file) {
        # Skip the current and parent directories
        if ($file === '.' || $file === '..') {
            continue;
        }

        $file = $dir . $file;
        $realpath = realpath($file);

        # Skip files that have already been visited
        if (isset($visited[$realpath]) && $visited[$realpath]) {
            continue;
        }
        $visited[$realpath] = true;

        # Skip unreadable files
        if (!is_readable($file)) {
            continue;
        }

        # Recursively call scan() if $file is a directory
        if (is_dir($file)) {
            scan($file, $result, $visited);
            continue;
        }

        $result[] = $file;
    }

    return $result;
}

# Echo only if verbose mode is enabled
# $string: text to output
# $stream: stream to output to
# Returns nothing
function vecho($string, $stream = STDOUT) {
    if (!defined('VERBOSE') || !VERBOSE) {
        return;
    }
    
    fwrite($stream, $string);
}

?>
