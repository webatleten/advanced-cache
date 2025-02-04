<?php
/**
 * Webatleten
 *
 * @package      	  WA
 * @author        	  Webatleten
 * Description:       One file to cache all of WordPress
 * Version:           1.2.2
 * Author:            Webatleten
 * Author URI:        https://webatleten.nl/
*/

// check if cache is enabled
if( !defined( 'WP_CACHE' ) || !WP_CACHE ) {
    return;
}

// define and create cache dir
if( !defined( 'WA_AC_CACHE_DIR'  ) ) {
    define( 'WA_AC_CACHE_DIR', ABSPATH.'wp-content/cache/html/' );
}

function wa_ac_cache_clear( $args = false )
{
    if( ( defined( 'WA_AC_CACHE_CLEAR_DISABLE' ) && WA_AC_CACHE_CLEAR_DISABLE ) || !file_exists( WA_AC_CACHE_DIR ) ) {
        return;
    }

    $files = scandir( WA_AC_CACHE_DIR );

    if( empty( $files ) ) {
        return;
    }

    foreach( $files as $file ) {
        if( $file === '.' || $file === '..' ) {
            continue;
        }

        $file_path = WA_AC_CACHE_DIR.$file;

        if( file_exists( $file_path ) && is_file( $file_path ) ) {
            unlink( $file_path );
        }
    }
}

if( !defined( 'WA_AC_CACHE_CLEAR_DISABLE' ) || !WA_AC_CACHE_CLEAR_DISABLE ) {
    add_action( 'wp_update_nav_menu', 'wa_ac_cache_clear', 99, 1 );
    add_action( 'edit_term', 'wa_ac_cache_clear', 99, 1 );
    add_action( 'save_post', 'wa_ac_cache_clear', 99, 1 );
    add_action( 'wa_metabox_save_option', 'wa_ac_cache_clear', 99, 1 );
    add_action( 'comment_post', 'wa_ac_cache_clear', 99, 1 );
    add_action( 'acf/save_post', 'wa_ac_cache_clear', 99, 1 );
    add_action( 'woocommerce_product_set_stock', 'wa_ac_cache_clear', 99, 1 );
    add_action( 'woocommerce_variation_set_stock', 'wa_ac_cache_clear', 99, 1 );
    add_action( 'woocommerce_product_set_stock_status', 'wa_ac_cache_clear', 99, 1 );
    add_action( 'woocommerce_variation_set_stock_status', 'wa_ac_cache_clear', 99, 1 );
}

$wa_ac_cache = true;

// these are empty on bash (CLI)
if( empty( $_SERVER[ 'SERVER_NAME' ] ) || empty( $_SERVER[ 'REQUEST_URI' ] ) ) {
    $wa_ac_cache = false;
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
    $wa_ac_cache = false;
}

// check cookies
if( $wa_ac_cache && !empty( $_COOKIE ) ) {
    $regex = '/wordpress_logged_in|woocommerce_session|comment_author|wp-postpass/';

    foreach( $_COOKIE as $name => $val ) {
	    if( preg_match( $regex, $name ) ) {
            $wa_ac_cache = false;
            break;
        }
    }
}

// check request uri
if ( $wa_ac_cache && !empty( $_GET ) ) {
    $regex = '/^(?!(fbclid|ref|mc_(cid|eid)|utm_(source|medium|campaign|term|content|expid)|gclid|fb_(action_ids|action_types|source)|age-verified|usqp|cn-reloaded|_ga|_ke)).+$/';

    if( preg_match( $regex, parse_url( $_SERVER['REQUEST_URI'], PHP_URL_QUERY ) ) ) {
        $wa_ac_cache = false;
    }
}

// check request for a file request
if( $wa_ac_cache && $_SERVER[ 'REQUEST_URI' ] != '/' && preg_match( '|\.|', $_SERVER[ 'REQUEST_URI' ] ) ) {
    $wa_ac_cache = false;
}

// check if cache is a 'go'
if( !$wa_ac_cache ) {
    return;
}

if( !file_exists( WA_AC_CACHE_DIR ) ) {
    mkdir( WA_AC_CACHE_DIR );
}

// protect cache files with htaccess
if( !file_exists( WA_AC_CACHE_DIR.'.htaccess' ) ) {
    file_put_contents( WA_AC_CACHE_DIR.'.htaccess', trim( "
<Files \"*\">
    Order Allow,Deny
    Deny from all
</Files>" ) );
}

// create cache file based on URL
if( !defined( 'WA_AC_CACHE_FILE' ) ) {
    define( 'WA_AC_CACHE_FILE', WA_AC_CACHE_DIR.md5( $_SERVER[ 'SERVER_NAME' ].$_SERVER[ 'REQUEST_URI' ] ).'.html' );
}

$wa_ac_cache_timeout = 43200;  //in seconds (default 12 (hours) * 3600)

// get custom timemout
if( defined( 'WA_AC_CACHE_TIMEOUT'  ) ) {
    $wa_ac_cache_timeout = (float) WA_AC_CACHE_TIMEOUT;
}
else if( file_exists( ABSPATH.'wp-content/settings/wa/timeout.txt' ) ) {
    $wa_ac_cache_timeout = (float) file_get_contents( ABSPATH.'wp-content/settings/wa/timeout.txt' );
}

// remove existing cache file after timeout
if( $wa_ac_cache_timeout > 0 && file_exists( WA_AC_CACHE_FILE ) && ( time() - filemtime( WA_AC_CACHE_FILE ) ) > $wa_ac_cache_timeout ) {
    unlink( WA_AC_CACHE_FILE );
}

// load cache file
if( file_exists( WA_AC_CACHE_FILE ) ) {
	if( $contents = file_get_contents( WA_AC_CACHE_FILE ) ) {
        // check if cache file has all basic HTML elements
		if( preg_match( '/html|head|body/', $contents ) ) {
			echo $contents; exit;
		}
	}
}

// capture output
ob_start();

// save cache file (after some checks)
function wa_ac_cache_save()
{
    // recheck session
	$session = array();

    if( !empty( $_SESSION ) ) {
        $session = (array) $_SESSION;
    }

    //easyflex session fix
    if( isset( $session[ 'easyflex' ] ) && !$session[ 'easyflex' ] ) {
        unset( $session[ 'easyflex' ] );
    }

    //if session is not empty or its a 404 page, don't cache
    if( is_404() || !empty( $session ) ) {
        return;
    }

    global $wa_ac_cache_done;

    if( !isset( $wa_ac_cache_done ) ) {
        $wa_ac_cache_done = false;
    }
    // stop if the page is already cached
    else if( $wa_ac_cache_done ) {
        return;
    }

    if( $cache_data = ob_get_clean() ) {
        // do stuff with the cache_data
        $cache_data = apply_filters( 'wa_ac_cache_data', $cache_data );

        //check if result if cache file is available and there are basic HTML tags
        if( !empty( WA_AC_CACHE_FILE ) && preg_match( '/html|head|body/', $cache_data ) ) {
            file_put_contents( WA_AC_CACHE_FILE, $cache_data );
	     chmod( WA_AC_CACHE_FILE, 0755 );

            $wa_ac_cache_done = true;
	    }

	    echo $cache_data;
    }
}

// hook on shutdown and footer
add_action( 'wp_footer', 'wa_ac_cache_save', 999999999 );
add_action( 'shutdown', 'wa_ac_cache_save', 999999999 );
