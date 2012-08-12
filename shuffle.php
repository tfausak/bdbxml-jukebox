<?php

require_once('config.php');

$total = $con->getNumDocuments();
$limit = min($total, 10);
$count = 0;

$return = '<li>
    <a class="direct" href="{data($i/url)}">
        <strong>{data($i/title)}</strong>
        <span>{string-join((data($i/album), data($i/artist)), " &#x00b7; ")}</span>
    </a>
    <a class="utility add" href="playlist?add={data($i/url)}">+</a>
</li>';

$rand = range(1, $total);
shuffle($rand);
$rand = array_slice($rand, 0, $limit);
$i = 1;

$txn = $mgr->createTransaction();
$result = $con->getAllDocuments($txn, DBXML_LAZY_DOCS);
$result->reset();

while ($result->hasNext()) {
    $doc = $result->next();
    if (in_array($i++, $rand)) {
        $results[] = $doc->asString();
    }
}

unset($docs);
$txn->commit();

$query = '
for $i in <songs>' . implode('', $results) . '</songs>/song
order by $i/title
return ' . $return;

$title = 'Shuffle &#x00b7; ';

$result = $mgr->query($query, $qc);
$result->reset();
$results = array();

while ($result->hasNext()) {
    $results[] = $result->next()->asString();
    $count++;
}
$content = '<ul id="songs">' . implode("\n", $results) . '</ul>';
$status = $count . ' of ' . $total;
$s1 = ($count === 1) ? '' : 's';
$elapsed = round(microtime(true) - $start, 2);
$s2 = ($elapsed === 1) ? '' : 's';
$status = 'Chose ' . $count . ' song' . $s1 . ' in ' . $elapsed . ' second' . $s2;

print_page();

?>
