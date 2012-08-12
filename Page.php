<?php

class Page {
    const MIME = 'application/xhtml+xml';
    
    public function __construct() {
        # Serve the correct MIME type to browsers that support it
        $mime = 'text/html';
        if (isset($_SERVER['HTTP_ACCEPT']) &&
            !empty($_SERVER['HTTP_ACCEPT']) &&
            stripos($_SERVER['HTTP_ACCEPT'], self::MIME) !== false) {
            $mime = self::MIME;
        }
    }
}

?>
