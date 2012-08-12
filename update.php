#!/usr/local/bin/php
<?php

require_once('getid3/getid3.php');

# # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # 
# Parse command-line options
# # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # 

# Default values for command-line options
$defaults = array
(
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

$d = (isset($options['d'])) ? $options['d'] : $defaults['d'];
    if (substr($d, -1) !== '/') $d .= '/'; # Force a trailing slash
$e = (isset($options['e'])) ? $options['e'] : $defaults['e'];
    if (substr($e, -1) !== '/') $e .= '/';
$f = (isset($options['f'])) ? $options['f'] : $defaults['f'];
$i = (isset($options['i'])) ? format_exts($options['i']) : $defaults['i'];
$x = (isset($options['x'])) ? format_exts($options['x']) : $defaults['x'];
$v = isset($options['v']);

define('DIRECTORY', $d);
define('ENV_HOME', $e);
define('DATABASE', $f);
define('EXT_INCLUDE', $i);
define('EXT_EXCLUDE', $x);
define('VERBOSE', $v);

unset($defaults, $options, $d, $e, $f, $i, $x, $v);

# # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # 
# Main program
# # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # 

umask(002);

#
# Set up database environment
#
vecho("Initializing database environment...\n");

$env = new Db4Env();
$env->open(ENV_HOME, DB_INIT_TXN | DB_INIT_LOG | DB_INIT_MPOOL | DB_INIT_LOCK | DB_CREATE | DB_INIT_TXN);

$mgr = new XmlManager($env, 0);
if ($mgr->existsContainer(DATABASE)) {
    $con = $mgr->openContainer(DATABASE);
} else {
    $con = $mgr->createContainer(DATABASE);
    $con->addIndex('', 'filemtime', 'node-metadata-equality-dateTime');
    $con->addIndex('', 'path', 'unique-node-element-equality-string');
    $con->addIndex('', 'title', 'node-element-substring-string');
    $con->addIndex('', 'artist', 'node-element-substring-string');
    $con->addIndex('', 'album', 'node-element-substring-string');
    $con->addIndex('', 'album_artist', 'node-element-substring-string');
}

$qc = $mgr->createQueryContext(XmlQueryContext_LiveValues, XmlQueryContext_Eager);
$qc->setDefaultCollection(DATABASE);
$uc = $mgr->createUpdateContext();

#
# Remove dead entires
#
vecho("Checking for dead entires...\n");
$start = microtime(true);

$txn = $mgr->createTransaction();
$docs = $con->getAllDocuments($txn, DBXML_LAZY_DOCS);
$count = 0;

$docs->reset();
while ($docs->hasNext()) {
    $doc = $docs->next()->asDocument();
    $path = $doc->getName();

    if (!is_readable($path)) {
        $con->deleteDocument($doc, $uc);
        $count++;

        vecho("Removed dead entry {$path}\n");
    } else {
        $time = $doc->getMetaData('', 'filemtime')->asString();
        $time = strtotime($time);
        if ($time < filemtime($path)) {
            # File exists, is readable, and is newer than the database
            $con->deleteDocument($doc, $uc);
            $count++;

            vecho("Removed out of date entry {$path}\n");
        }
    }

    unset($time, $path, $doc);
}

unset($docs);
$txn->commit();

$elapsed = round(microtime(true) - $start, 3);
$s1 = ($count === 1) ? 'y' : 'ies';
$s2 = ($elapsed == 1) ? '' : 's';
vecho("Removed {$count} dead entr{$s1} in {$elapsed} second{$s2}.\n");

unset($s2, $s1, $elapsed, $txn, $count, $start);

#
# Scan for music files
#
vecho("Scanning for music files...\n");
$start = microtime(true);

$files = scan(DIRECTORY);
if (!$files) {
    $files = array();
}

$elapsed = round(microtime(true) - $start, 3);
$count = count($files);
$s1 = ($count === 1) ? '' : 's';
$s2 = ($elapsed == 1) ? '' : 's';
vecho("Found {$count} music file{$s1} in {$elapsed} second{$s2}.\n");

unset($s2, $s1, $count, $elapsed, $start);

#
# Parse all media files found
#
vecho("Parsing all media files found...\n");
$start = microtime(true);

$txn = $mgr->createTransaction();
$count = 0;
$i = 1;
$count_files = count($files);

foreach ($files as $path) {
    vecho(make_progress_bar($start, (double) $i++ / $count_files));

    try {
        $doc = $con->getDocument($path, DBXML_LAZY_DOCS);

        # Skip documents that already exist
        continue;
    } catch (XmlException $e) {
        # Only catch 'document does not exist' exceptions
        if ($e->getExceptionCode() !== 11) {
            throw new XmlException($e);
        }
    }

    $tag = read_tag($path);
    if (!$tag) {
        vecho("\nFailed to parse {$path}\n");
    }

    $song = array_to_xml($tag, 'song');

    $con->putDocument($txn, $path, $song, $uc);
    $count++;

    # Add metadata
    $doc = $con->getDocument($path, DBXML_LAZY_DOCS);
    $time = new XmlValue(XmlValue_DATE_TIME, date('c', filemtime($path)));
    $doc->setMetaData('', 'filemtime', $time);
    $con->updateDocument($doc);

    unset($time, $doc, $song, $tag, $path);
}

$txn->commit();

$elapsed = round(microtime(true) - $start, 3);
$s1 = ($count === 1) ? 'y' : 'ies';
$s2 = ($elapsed == 1) ? '' : 's';
vecho("\n{$count} database entr{$s1} added in {$elapsed} second{$s2}.\n");

unset($count_files, $i, $s2, $s1, $elapsed, $txn, $count, $start, $files);

#
# Close the environment
#

unset($uc, $qc, $con, $mgr, $env);
exit(0);

# # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # 
# Functions
# # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # # 

# Recursive scan a directory for readable files with proper extensions
# $dir - directory to scan
# Returns an array of filenames on success or false on failure
function scan($dir, &$result = null) {
    if (!is_dir($dir)) {
        return false;
    }

    if (substr($dir, -1) !== '/') {
        $dir .= '/';
    }

    $files = scandir($dir);

    if (!$files) {
        return false;
    }

    foreach ($files as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }

        $file = $dir . $file;

        if (!is_readable($file)) {
            continue;
        }

        if (is_dir($file)) {
            scan($file, $result);
            continue;
        }

        # Only include files with proper extensions
        $ext = pathinfo($file, PATHINFO_EXTENSION);
        if (EXT_INCLUDE !== null && stripos(EXT_INCLUDE, $ext) === false) {
            continue;
        }
        if (stripos(EXT_EXCLUDE, $ext) !== false) {
            continue;
        }

        $result[] = $file;
    }

    if (count($result) === 0) {
        return false;
    }

    return $result;
}

# Reads ID3 info from a file
# $file - path to file to read and parse
# Returns an array of ID3 info or false if the tags can't be read
function read_tag($file) {
    static $parser;

    if (!isset($parser)) {
        $parser = new GetID3;
    }

    # Skip songs with invalid tags
    try {
        $song = $parser->Analyze($file);
    } catch (getid3_exception $e) {
        return false;
    }

    # Escape URLs but keep forward slashes
    $url = str_replace('%2F', '/', rawurlencode($file));

    if ($song['mime_type'] === 'audio/mpeg' || $song['mime_type'] === 'audio/mp4' || $song['mime_type'] === 'video/quicktime') {
        $result = array(
            'path' => $file,
            'url' => $url,
            'title' => @end($song['comments']['title']),
            'artist' => @end($song['comments']['artist']),
            'album' => @end($song['comments']['album']),
            'album artist' => @end($song['comments']['text'])
        );
    } else {
        return false;
    }

    # Trim and escape every value
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

# Format an array into XML, making key values tags
# ie 'key' => 'value' becomes <key>value</key>
# $array - array to format into XML
# $tag - parent tag to wrap XML in
# returns XML as a string
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

# Formats a list of extensions
# $extensions - comma-delimited list of extensions
# Returns a space-delimited list of lowercase extensions
function format_exts($exts) {
    $exts = explode(',', $exts);

    foreach ($exts as &$ext) {
        if ($ext[0] === '.')
            $ext = substr($ext, 1);

        $ext = strtolower($ext);
    }

    return implode(' ', $exts);
}

# Makes a progress bar showing percent complete and time left
# $start - epoch, as reported by microtime(true)
# $percent - percentage of job complete, as a decimal (ex: 0.14)
# Returns a string 80 characters wide
function make_progress_bar($start, $percent) {
    static $format = '  %3d%%  [ %-54s ]  ETA %5.5s';

    $progress_bar = str_repeat('=', 54 * $percent);
    $progress_bar .= '>';

    # Calculate time remaining
    $elapsed = microtime(true) - $start;
    $eta = $elapsed * (1 / $percent - 1);
    $eta = floor($eta);
    $minutes = floor($eta / 60);
    $seconds = $eta % 60;
    $eta = $minutes . ':' . sprintf('%02d', $seconds);

    $percent = floor(100 * $percent);
    $progress = sprintf($format, $percent, $progress_bar, $eta);

    # Back the cursor up
    $len = strlen($progress);
    for ($j = 0; $j < $len; $j++) {
        $progress .= chr(8);
    }

    return $progress;
}

# Output only if verbose mode is on
# $string - string to output
# $stream - stream to output the string on
# Returns nothing
function vecho($string, $stream = STDOUT) {
    if (defined('VERBOSE') && VERBOSE) {
        fwrite($stream, $string);
    }
}

?>
