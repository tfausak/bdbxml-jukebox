<?php

# ------------------------------------------------------------------------------
# Define constants
# ------------------------------------------------------------------------------

define('START', microtime(true));

# Database environment variables
define('ENV_HOME', './db/');
define('DATABASE', 'library.dbxml');

# Number of random songs to show when no query is done
define('RAND_LIMIT', 10);

# ------------------------------------------------------------------------------
# Start sessions and initialize session variables
# ------------------------------------------------------------------------------

session_start();

if (!isset($_SESSION['playlist']) || !is_array($_SESSION['playlist'])) {
    $_SESSION['playlist'] = array();
}

# ------------------------------------------------------------------------------
# Page class
# ------------------------------------------------------------------------------

class Page {
    public $self, $mime, $iphone, $year;
    
    public function __construct() {
        # Determine the currently executing script
        $this->self = basename($_SERVER['SCRIPT_NAME'], '.php');
        
        # Serve the correct MIME type to browsers that support it
        $this->mime = 'text/html';
        if (isset($_SERVER['HTTP_ACCEPT']) &&
            !empty($_SERVER['HTTP_ACCEPT']) &&
            stripos($_SERVER['HTTP_ACCEPT'], 'application/xhtml+xml') !== false) {
            #$this->mime = 'application/xhtml+xml';
        }
        
        # Sniff for iPhone browsers
        $this->iphone = false;
        if (isset($_SERVER['HTTP_USER_AGENT']) &&
            !empty($_SERVER['HTTP_USER_AGENT']) &&
            stripos($_SERVER['HTTP_USER_AGENT'], 'iphone') !== false) {
            $this->iphone = true;
        }
        
        # Determine the current year (for copyright notice)
        $this->year = date('Y');
    }
    
    public function output($content) {
        header('Content-Type: ' . $this->mime);
        
        echo <<<EOT
<?xml version="1.0" encoding="UTF-8"?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">

<html lang="en-US" xml:lang="en-US" xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <meta content="{$this->mime};charset=utf-8" http-equiv="content-type"/>
        <link href="styles/screen.css" media="screen" rel="stylesheet" type="text/css"/>

EOT;

        if ($this->iphone) {
            echo <<<EOT
        <meta content="initial-scale=1.0,maximum-scale=1.0,user-scalable=no" name="viewport"/>
        <link href="styles/iphone.css" media="screen" rel="stylesheet" type="text/css"/>

EOT;
        }
        
        echo <<<EOT
        <title>Music</title>
    </head>
    
    <body id="{$this->self}">
        <div id="header">
            <h1>Music</h1>
        </div>

        <div id="content">
{$content}
        </div>

        <div id="footer">
            <p>Copyright &#xa9; {$this->year} <a href="http://taylor.thursday.com/" title="Visit Taylor Fausak's website">Taylor Fausak</a></p>

            <ul>
                <li>Valid <a href="http://validator.w3.org/check?uri=referer" title="Check this document's validity"><abbr title="Extensible Hypertext Markup Language">XHTML</abbr> 1.0 Strict</a></li>
                <li>Valid <a href="http://jigsaw.w3.org/css-validator/check/referer" title="Check this document's validity"><abbr title="Cascading Style Sheets">CSS</abbr> 2.1</a></li>
            </ul>
        </div>
        
        <script src="scripts/listeners.js" type="text/javascript"></script>
    </body>
</html>

EOT;
    }
}

?>
