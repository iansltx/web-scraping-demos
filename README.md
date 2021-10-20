Web Scraping Demos
==================

This repo contains companion code to my ["Levelling Up Your Web Scraping Game" talk](https://ian.im/scrape0721).
To use the examples, run `composer install` on a system with the requisite extensions and PHP 8.0+ installed (extension
and PHP version requirements are listed in `composer.json`), then run either `hippo.php` or `localcallingguide.php`
directly.

LocalCallingGuide
-----------------

This is an example of "traditional" web scraping, where you're navigating forms and links with server-rendered HTML.
The script takes rate center information (city and state), then grabs information on all prefixes in that rate center,
traversing pagination as needed.

Hippo
-----

Hippo is an insurance company, and pulling insurance company data into a consistent format is currently my day job.
This script logs into Hippo via their passwordless auth system, prompting as user-supplied information is required,
and pulls the JSON payload for policy data. This is an example of how to efficiently scrape a single page app,
which is an increasingly common source of data these days.
