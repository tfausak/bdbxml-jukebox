<?php

# Start the page timer
$start = microtime(true);

# Turn on all error reporting
error_reporting(E_ALL);
ini_set('display_errors', 'stderr');
ini_set('html_errors', false);

define('ENV_HOME', 'db/');
define('DATABASE', 'library.dbxml');

$links = array(
    'search.php' => 'Search',
    'shuffle.php' => 'Shuffle',
    'playlist.php' => 'Playlist'
);

session_start();

# Determine which script is calling this
$self = basename($_SERVER['SCRIPT_FILENAME']);

# Serve the correct MIME type to UAs that can handle it
$mime = 'text/html';
if (stripos(@$_SERVER['HTTP_ACCEPT'], 'application/xhtml+xml') !== false) {
    $mime = 'application/xhtml+xml';
}

# Find out if the UA is an iPhone
$iphone = false;
if (stripos(@$_SERVER['HTTP_USER_AGENT'], 'iphone') !== false) {
    $iphone = true;
}

# Initialize database connection
$env = new Db4Env();
$env->open(ENV_HOME, DB_INIT_TXN | DB_INIT_LOG | DB_INIT_MPOOL | DB_INIT_LOCK | DB_CREATE | DB_INIT_TXN);
$mgr = new XmlManager($env, 0);
$con = $mgr->openContainer(DATABASE);
$qc = $mgr->createQueryContext(XmlQueryContext_LiveValues, XmlQueryContext_Eager);
$qc->setDefaultCollection(DATABASE);

# Prevent out of date copyright dates
$year = date('Y');

# Make navigation
$nav = '<ul id="nav">';
foreach ($links as $script => $name) {
    # Find the active page
    $current = '';
    if ($script === $self) {
        $current = ' id="current"';
    }

    $nav .= '<li><a href="' . basename($script, '.php') . '"' . $current . '>' . $name . '</a></li>';
}
$nav .= '</ul>';

function print_page() {
    global $iphone, $mime, $meta, $title, $back, $forward, $nav, $content, $status, $year;

    header('Content-type: ' . $mime);

    if ($iphone) {
        $meta .= '<meta content="initial-scale=1.0; maximum-scale=1.0; user-scalable=no; width=device-width;" name="viewport" />' . "\n"
               . '<link href="styles/iphone.css" media="screen" rel="stylesheet" type="text/css" />'
               ;
    }

    echo <<<HTML
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">

<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <meta content="{$mime}; charset=utf-8;" http-equiv="content-type" />
        <link href="styles/screen.css" media="screen" rel="stylesheet" type="text/css" />
        <link href="images/favicon.png" rel="icon" type="image/png" />
        <link href="images/apple-touch-icon.png" rel="apple-touch-icon" type="image/png" />
        <script src="scripts/listeners.js" type="text/javascript"></script>
        <!--[if IE]><style type="text/css">* { text-decoration: none; font-weight: normal; }</style><![endif]-->
{$meta}

        <title>{$title}Music</title>
    </head>

    <body>
        <div id="header">
            <h1>Music</h1>

            <ul>
                <li>{$back}</li>
                <li>{$forward}</li>
            </ul>
        </div>

{$nav}

        <div id="content">
{$content}
        </div>

        <div id="footer">
            <p>{$status}</p>

            <p>&#x00a9; {$year} <a href="mailto:tfausak@gmail.com">Taylor Fausak</a></p>

            <ul>
                <li>Valid <a href="http://validator.w3.org/check?uri=referer"><abbr title="Extensible Hypertext Markup Language">XHTML</abbr> 1.1</a></li>
                <li>Valid <a href="http://jigsaw.w3.org/css-validator/check/referer"><abbr title="Cascading Style Sheets">CSS</abbr> 2.1</a></li>
            </ul>
        </div>
    </body>
</html>
HTML;
}

?>
