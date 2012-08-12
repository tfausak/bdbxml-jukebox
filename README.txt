Table of contents
1. Requirements
2. Installation
3. Running updates

#
# Requirements
#

Apache 1.3.41
    http://httpd.apache.org/
PHP 5.2.8
    http://www.php.net/downloads.php
Berkely DB XML 2.4.16
    http://www.oracle.com/technology/software/products/berkeley-db/xml/index.html
getID3 2.0.0-b4
    http://getid3.sourceforge.net/

#
# Installation
#

-   Set up Apache, PHP, and Berkeley DB XML to work nicely together.
-   Make a web-accessible directory for all this stuff.
    ex: mkdir /usr/local/apache/htdocs/music
-   Unpack getID3 to that directory.
-   Make a symbolic link to your media files.
    ex: ln -s /path/to/music/files library
-   Ensure all your media files can be read by PHP.
    ex: chmod -R o+r library
-   Make update.php executeable.
    ex: chmod u+x update.php
-   Make a directory to store the database environment in.
    ex: mkdir /usr/local/music/db
-   Make a group to access the database.
    ex: groupadd music
-   Add both the web server and the updater to the group.
    ex: usermod -a music www-data
        usermod -a music updater
-   Fix permissions on the database environment directory.
    ex: chmod 2775 /usr/local/music/db
        chgrp music /usr/local/music/db
-   Consider using a .htaccess to keep everyone out of your stuff.

#
# Running updates
#

The first time you run update.php, you'll probably want to do it like this:

    ./update.php -v -d library

The -v flag runs it in verbose mode, which will tell you how much time is left.
The -d flag specifies what directory to search for music files. If you followed
the installation directions above, library is a symbolic link to wherever you
keep your media.

If all goes well, your databaes directory will be populated with a few __db.*
and log.* files, along with library.dbxml and DB_CONFIG. Only one more step!

Ensure that your database directory and the database itself are both named how
you want them (run update.php with -h for help). Now open index.php and fill in
those values.

You're done! Load up index.php in your favorite browser (it's meant for iPhone)
and search away!

#
# Reporting problems
#

This script is incredibly experimental. Use at your own risk.

If you come across a bug, problem, or missing feature, send me an email at
[ tfausak@gmail.com ] and I'll try to fix it as soon as possible.

#
# Known issues
#

-   text encoding is wonky at best
-   security is controlled with apache
