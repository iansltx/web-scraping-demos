<?php

use Symfony\Component\BrowserKit\HttpBrowser;
use Symfony\Component\DomCrawler\Crawler;

require __DIR__ . '/vendor/autoload.php';

$browser = new HttpBrowser();

$city = readline('Please enter the city you want to pull prefixes for: ');
$state = readline('Please enter the state you want to pull prefixes for: ');

$searchPage = $browser->request('GET', 'https://www.localcallingguide.com/lca_listexch.php');
$searchForm = $searchPage->filter('#form form')->first()->form();

$rateCenterListPage = $browser->submit($searchForm, array_merge(
    $searchForm->getValues(),
    ['ename' => $city, 'region' => $state])
);
$rateCenterLink = $rateCenterListPage->filter('td[data-label="Rate centre"] a')->first();
if (!$rateCenterLink) {
    throw new \RuntimeException('Could not find rate center');
}

// A naive approach to get to the prefix detail page navigates through an intermediate page and iterates over links
// until it finds the one for prefix detail, then navigates to that link...
$loadPrefixDetailPage = function (Crawler $rateCenterLink) use ($browser): Crawler {
    $rateCenterPage = $browser->request('GET', $rateCenterLink->attr('href'));
    foreach ($rateCenterPage->filter('p > a')->getIterator() as $link) {
        if ($link->textContent === '[Prefix detail]') {
            return $browser->request('GET', $link->attributes->getNamedItem('href')->nodeValue);
        }
    }

    throw new \InvalidArgumentException('Could not find prefix detail link');
};

// ...but we know that every rate center has a prefix detail page, keyed by the same ID as the rate center itself,
// so we can skip the intermediate page entirely and speed things up, at the expense of things breaking if the page
// moves around but remains linked.
$loadPrefixDetailPage = function (Crawler $rateCenterLink) use ($browser): Crawler {
    return $browser->request('GET', str_replace('lca_exch', 'lca_prefix', $rateCenterLink->attr('href')));
};

$prefixDetailPage = $loadPrefixDetailPage($rateCenterLink);
$prefixes = [];

for ($pageNumber = 1;;) {
    $prefixes = array_merge($prefixes, $prefixDetailPage->filter('tbody > tr')->each(fn (Crawler $row) => [
        'npa-nxx' => $row->filter('td[data-label="NPA-NXX"]')->text(),
        'block' => $row->filter('td[data-label="Block"]')->text(),
        'ocn' => $row->filter('td[data-label="OCN"]')->text()
    ]));

    // The element immediately after the table is a <p> with pagination links inside, if pagination exists.
    // Pages other than the last one will have a ">>" link to the next page, so we'll traverse through that rather
    // than trying to grab all pages simultaneously. Note that empty node lists (e.g. a prefix detail result where
    // everything fits on one page) are still objects, so you have to use count() rather than a truthiness check
    // prior to calling text() to see if we did indeed grab the next-page link.
    $lastPaginationLink = $prefixDetailPage->filter('table')->first()->nextAll()->first()->filter('a')->last();
    if (!$lastPaginationLink->count() || $lastPaginationLink->text() !== '>>') {
        break; // no more pages
    }

    echo 'Getting page ' . ++$pageNumber . "\n";

    $prefixDetailPage = $browser->request('GET', $lastPaginationLink->attr('href'));
}

echo json_encode($prefixes, JSON_PRETTY_PRINT);
