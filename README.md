<h1>Advanced cache dropin</h1>

A simple and straightforward caching file. Just put the "advanced-cache.php" in your "wp-content" folder and enable caching in your "wp-config.php" file and that's it. 

Download "advanced-cache.php" and move to:
/wp-content/advanced-cache.php

Go to your config file and add define "WP_CACHE" with "true":
/wp-config.php
define( 'WP_CACHE', true );

Changelog
----------------
1.0.0
- initial setup

1.1.0
- timeout fix and reduce code

1.2.0
- added define check for cache timeout
