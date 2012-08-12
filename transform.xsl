<?xml version="1.0" encoding="UTF-8"?>

<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns="http://www.w3.org/1999/xhtml">
    <xsl:output method="xml" indent="yes" encoding="UTF-8"/>

    <xsl:template match="/library">
<html>
    <head>
        <meta content="text/html;charset=utf-8" http-equiv="content-type"/>
        <meta content="initial-scale=1.0,maximum-scale=1.0,user-scalable=no" name="viewport"/>
        
        <link href="style.css" media="screen" rel="stylesheet" type="text/css"/>

        <script src="listeners.js" type="text/javascript">/**/</script>

        <title>Music</title>
    </head>

    <body>
        <div id="header">
            <h1>Music</h1>

            <xsl:if test="/library/get_artist != '' or /library/get_album != '' or /library/get_search != ''">
                <a href="./" id="restart">Start over</a>
            </xsl:if>
        </div>

        <form action="index.php" id="query" method="get">
            <fieldset id="artist">
                <legend>Artist</legend>

                <xsl:apply-templates select="artists"/>
            </fieldset>

            <fieldset id="album">
                <legend>Album</legend>

                <xsl:apply-templates select="albums"/>
            </fieldset>

            <fieldset id="search">
                <legend>Search</legend>

                <input type="text" name="search">
                    <xsl:attribute name="value">
                        <xsl:value-of select="get_search"/>
                    </xsl:attribute>
                </input>
            </fieldset>

            <input id="submit" type="submit" value="Go"/>
        </form>

        <div id="content">
            <xsl:apply-templates select="songs"/>
        </div>

        <div id="footer">
            <p id="copyright">Copyright &#x00a9; 2009 <a href="http://taylor.thursday.com/">Taylor Fausak</a>.</p>
        </div>
    </body>
</html>
    </xsl:template>

    <xsl:template match="artists">
<select name="artist">
    <option value="">
        All artists (<xsl:value-of select="@count"/>)
    </option>

    <xsl:apply-templates select="artist">
        <xsl:sort select="."/>
    </xsl:apply-templates>
</select>
    </xsl:template>

    <xsl:template match="artist">
<option>
    <xsl:if test=". = /library/get_artist">
        <xsl:attribute name="selected">selected</xsl:attribute>
    </xsl:if>

    <xsl:attribute name="value">
        <xsl:value-of select="."/>
    </xsl:attribute>

    <xsl:value-of select="."/>
</option>
    </xsl:template>

    <xsl:template match="albums">
<select name="album">
    <option value="">
        All albums (<xsl:value-of select="@count"/>)
    </option>

    <xsl:apply-templates select="album">
        <xsl:sort select="."/>
    </xsl:apply-templates>
</select>
    </xsl:template>

    <xsl:template match="album">
<option>
    <xsl:if test=". = /library/get_album">
        <xsl:attribute name="selected">selected</xsl:attribute>
    </xsl:if>

    <xsl:attribute name="value">
        <xsl:value-of select="."/>
    </xsl:attribute>

    <xsl:value-of select="."/>
</option>
    </xsl:template>

    <xsl:template match="songs">
<ul id="songs">
    <xsl:apply-templates select="song"/>
</ul>
    </xsl:template>

    <xsl:template match="song">
<li>
    <a>
        <xsl:attribute name="href">
            <xsl:value-of select="url"/>
        </xsl:attribute>

        <span class="line">
            <span class="hidden">Title: </span>
            <span class="title"><xsl:value-of select="title"/></span>
        </span>

        <span class="line">
            <span class="hidden">Artist: </span>
            <span class="artist"><xsl:value-of select="artist"/></span>

            <span class="hidden">Album: </span>
            <span class="album"><xsl:value-of select="album"/></span>
        </span>
    </a>
</li>
    </xsl:template>
</xsl:stylesheet>
