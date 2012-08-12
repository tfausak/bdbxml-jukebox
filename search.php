<?php

require_once('config.php');

$q = trim(@$_GET['q']);
$value = htmlspecialchars($q);

if ($q != '') {
    $title = 'Search results for &#x201c;' . $value . '&#x201d; &#x00b7; ';

    $query = '
for $i in collection()//song
where dbxml:contains($i/title, $needle) or
      dbxml:contains($i/album, $needle) or
      dbxml:contains($i/artist, $needle) or
      dbxml:contains($i/album_artist, $needle)
order by $i/title
return <li>
    <a class="direct" href="{data($i/url)}">
        <strong>{data($i/title)}</strong>
        <span>{string-join((data($i/album), data($i/artist)), " &#x00b7; ")}</span>
    </a>
    <a class="utility add" href="playlist?add={data($i/url)}">+</a>
</li>';

    $qc->setVariableValue('needle', $q);
    $result = $mgr->query($query, $qc);
    $result->reset();
    $count = 0;

    while ($result->hasNext()) {
        $results[] = $result->next()->asString();
        $count++;
    }

    if ($count > 0) {
        $results = '<ul id="songs">' . implode("\n", $results) . '</ul>';
        $s1 = ($count === 1) ? '' : 's';
        $elapsed = round(microtime(true) - $start, 2);
        $s2 = ($elapsed === 1) ? '' : 's';
        $status = 'Found ' . $count . ' song' . $s1 . ' in ' . $elapsed . ' second' . $s2;
        $back = '<a class="back" href="search">Start over</a>';
    } else {
        $results = '<h2>Hmm...</h2><p>Nothing matched <strong>' . $value . '</strong>. Try something else.</p>';
        $status = 'No matches';
    }
} else {
    $results = '';
}

$content = <<<HTML
<form action="search" id="search" method="get">
    <fieldset>
        <legend>Search</legend>

        <input id="query" name="q" type="text" value="{$value}" />
        <input id="reset" type="reset" value="&#x00d7;" />
        <input id="submit" type="submit" value="Go" />
    </fieldset>
</form>{$results}
HTML;

print_page();

?>
