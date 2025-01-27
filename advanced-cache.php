<?php
/**
 * Webathletes
 *
 * @package      	  WA
 * @author        	  Webatleten
 * Description:       One file to cache all of WordPress
 * Version:           1.1
 * Author:            Webatleten
 * Author URI:        https://webatleten.nl/
*/

// check if cache is enabled
if( !defined( 'WP_CACHE' ) || !WP_CACHE ) {
    return;
}

$wa_cache = true;

// these are empty on bash (CLI)
if( empty( $_SERVER[ 'SERVER_NAME' ] ) || empty( $_SERVER[ 'REQUEST_URI' ] ) ) {
    $wa_cache = false;
}

// create and check for a session
$session = array();

if( !empty( $_SESSION ) ) {
    $session = $_SESSION;
}

//easyflex session fix
if( isset( $session[ 'easyflex' ] ) && !$session[ 'easyflex' ] ) {
    unset( $session[ 'easyflex' ] );
}

// check on basic WP and user actions
if( is_admin() || wp_doing_ajax() || !empty( $session ) || !empty( $_POST ) || !empty( $_FILES ) ) {
    $wa_cache = false;
}

// check cookies
if( $wa_cache && !empty( $_COOKIE ) ) {
    $regex = '/wordpress_logged_in|woocommerce_session|comment_author|wp-postpass/';

    foreach( $_COOKIE as $name => $val ) {
	    if( preg_match( $regex, $name ) ) {
            $wa_cache = false;
            break;
        }
    }
}

// check request uri
if ( $wa_cache && !empty( $_GET ) ) {

    $regex = '/^(?!(fbclid|ref|mc_(cid|eid)|utm_(source|medium|campaign|term|content|expid)|gclid|fb_(action_ids|action_types|source)|age-verified|usqp|cn-reloaded|_ga|_ke)).+$/';

    if( preg_match( $regex, parse_url( $_SERVER['REQUEST_URI'], PHP_URL_QUERY ) ) ) {
        $wa_cache = false;
    }
}

// check request for a file request
if( $wa_cache && $_SERVER[ 'REQUEST_URI' ] != '/' && preg_match( '|\.|', $_SERVER[ 'REQUEST_URI' ] ) ) {
    $wa_cache = false;
}


// check if cache is a 'go'
if( !$wa_cache ) {
    return;
}

// create cache dir if not exists
global $wa_cache_dir;

$wa_cache_dir = ABSPATH.'wp-content/cache/html/';

if( !file_exists( $wa_cache_dir ) ) {
    mkdir( $wa_cache_dir );
}

// protect cache files
$wa_cache_dir_htaccess = $wa_cache_dir.'.htaccess';

if( !file_exists( $wa_cache_dir_htaccess ) ) {
    file_put_contents( $wa_cache_dir_htaccess, trim( "
<Files \"*\">
    Order Allow,Deny
    Deny from all
</Files>" ) );
}

// create cache file based on URL
global $wa_cache_file;

$wa_cache_file = $wa_cache_dir.md5( $_SERVER[ 'SERVER_NAME' ].$_SERVER[ 'REQUEST_URI' ] ).'.html';

$wa_cache_timeout = 43200;  //default 12 hours

// get custom timemout
if( file_exists( ABSPATH.'wp-content/settings/wa/timeout.txt' ) ) {
    $wa_cache_timeout = (float) file_get_contents( ABSPATH.'wp-content/settings/wa/timeout.txt' );
}

// remove existing cache file after timeout
if( $wa_cache_timeout > 0 && file_exists( $wa_cache_file ) && ( time() - filemtime( $wa_cache_file ) ) > $wa_cache_timeout ) {
    unlink( $wa_cache_file );
}

// load cache file
if( file_exists( $wa_cache_file ) ) {
	if( $contents = file_get_contents( $wa_cache_file ) ) {
        // check if cache file has all basic HTML elements
		if( preg_match( '/html|head|body/', $contents ) ) {
			echo $contents; exit;
		}
	}
}

// capture output
ob_start();

// save cache file (after some checks)
function wa_cache_save()
{
    // recheck session
	$session = array();

    if( !empty( $_SESSION ) ) {
        $session = $_SESSION;
    }

    //easyflex session fix
    if( isset( $session[ 'easyflex' ] ) && !$session[ 'easyflex' ] ) {
        unset( $session[ 'easyflex' ] );
    }

    //if session is not empty or its a 404 page, don't cache
    if( is_404() || !empty( $session ) ) {
        return;
    }

    global $wa_cache_done;

    if( !isset( $wa_cache_done ) ) {
        $wa_cache_done = false;
    }
    // stop if the page is already cached
    else if( $wa_cache_done ) {
        return;
    }

    if( $cache_data = ob_get_clean() ) {
	    global $wa_cache_file;

        // do stuff with the cache_data
        $cache_data = apply_filters( 'wa_cache_data', $cache_data );

        //check if result if cache file is available and there are basic HTML tags
        if( !empty( $wa_cache_file ) && preg_match( '/html|head|body/', $cache_data ) ) {
            file_put_contents( $wa_cache_file, $cache_data );
            chmod( $wa_cache_file, 0777 );

            $wa_cache_done = true;
	    }

	    echo $cache_data;
    }
}

// hook on shutdown and footer
add_action( 'wp_footer', 'wa_cache_save', 999999999 );
add_action( 'shutdown', 'wa_cache_save', 999999999 );
