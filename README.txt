# ------------------------------------------------------------------------------
# Requirements
# ------------------------------------------------------------------------------

Apache 1.3.41
PHP 5.2.8
Berkely DB XML 2.4.16
getID3 2.0.0-b4
xsltproc

# ------------------------------------------------------------------------------
# Installation
# ------------------------------------------------------------------------------

1       Set up Apache, PHP, and BDBXML to work nicely together. This can be
        difficult and changes depending on your system, so I won't cover it.

2       Create a web-accessible directory for everything.
        ex:     mkdir /usr/local/apache/htdocs/music

3       Extract getID3 into that directory.
        ex:     unzip getid3-1.7.9.zip

4       Create a symbolic link to your music directory.
        ex:     ln -s /path/to/music/files library

5       Make sure all your files are readable by Apache and PHP.
        ex:     chmod -R o+r library

6       Make update.php executeable.
        ex:     chmod u+x update.php

7       Create a directory for the database environment/
        ex:     mkdir db

8       Create a group to access the database.
        ex:     groupadd music

9       Add the web server and the updater (typically you) to the group.
        ex:     usermod -a music www-data
                usermod -a music updater

10      Set permissions on the database environment's directory.
        ex:     chgrp music db
                chmod 2775 db
