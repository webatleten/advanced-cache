<?php
/**
 * Webathletes
 *
 * @package      	  WA
 * @author        	  Webatleten
 * Description:       Simple WordPress page caching
 * Version:           1.1
 * Author:            Webatleten
 * Author URI:        https://Webatleten.nl/
*/

// check if cache is enabled
if( !defined( 'WP_CACHE' ) || !WP_CACHE ) {
    return;
}

$wa_cache = true;

// these are empty on bash
if( empty( $_SERVER[ 'SERVER_NAME' ] ) || empty( $_SERVER[ 'REQUEST_URI' ] ) ) {
    $wa_cache = false;
}

$session = array();

if( !empty( $_SESSION ) ) {
    $session = $_SESSION;
}

//easyflex session fix
if( isset( $session[ 'easyflex' ] ) && !$session[ 'easyflex' ] ) {
    unset( $session[ 'easyflex' ] );
}

// check if the user is doing something
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

// check request for a file
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
    file_put_contents( $wa_cache_dir_htaccess, '<Files "*"> Require all denied </Files>' );
}

// create cache file based on URL
global $wa_cache_file;

$wa_cache_file = $wa_cache_dir.md5( $_SERVER[ 'SERVER_NAME' ].$_SERVER[ 'REQUEST_URI' ] ).'.html';

// get cache settings
global $wa_cache_settings;

$wa_cache_settings = (object) array(
    'timeout'  => 43200, //12 hours
    'minify'   => 1 //minify html
);

if( file_exists( ABSPATH.'wp-content/settings/wa/optimize.json' ) ) {
    $wa_cache_saved_settings = json_decode( file_get_contents( ABSPATH.'wp-content/settings/wa/optimize.json' ) );

    if( !empty( $wa_cache_saved_settings->timeout ) && $wa_cache_saved_settings->timeout === 'true' ) {
        $wa_cache_settings->timeout = 0;
    }

    if( !empty( $wa_cache_saved_settings->minify ) && $wa_cache_saved_settings->minify === 'false' ) {
        $wa_cache_settings->minify = 0;
    }
}

// remove existing cache file after timeout
if( $wa_cache_settings->timeout > 0 && file_exists( $wa_cache_file ) && ( time() - filemtime( $wa_cache_file ) ) > $wa_cache_settings->timeout ) {
    unlink( $wa_cache_file );
}

// load cache file
if( file_exists( $wa_cache_file ) ) {
	if( $contents = file_get_contents( $wa_cache_file ) ) {
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
	$session = array();

    if( !empty( $_SESSION ) ) {
        $session = $_SESSION;
    }

    //easyflex session fix
    if( isset( $session[ 'easyflex' ] ) && !$session[ 'easyflex' ] ) {
        unset( $session[ 'easyflex' ] );
    }

    if( is_404() || !empty( $session ) ) {
        return;
    }

    global $wa_cache_done;

    if( !isset( $wa_cache_done ) ) {
        $wa_cache_done = false;
    }
    else if( $wa_cache_done ) {
        return;
    }

    if( $cache_data = ob_get_clean() ) {
	    global $wa_cache_file, $wa_cache_settings;

        if( $wa_cache_settings->minify ) {
            // Verwijder HTML opmerkingen (behalve IE voorwaarden)
            $cache_data = preg_replace('/<!--(?!\[if.*?\]).*?-->/', '', $cache_data);

            // Verwijder overtollige witruimtes tussen HTML tags
            $cache_data = preg_replace('/>\s+</', '><', $cache_data);

            // Verwijder tabs, nieuwe regels, en overtollige spaties
            $cache_data = preg_replace('/\s{2,}/', ' ', $cache_data); 
            
            // Meerdere spaties vervangen door één spatie
            //$cache_data = str_replace(["\n", "\r", "\t"], '', $cache_data); 
            
            // Nieuwe regels, tabs verwijderen
            $cache_data = trim ($cache_data );
        }

        if( !empty( $wa_cache_file ) && preg_match( '/html|head|body/', $cache_data ) ) {
            $cache_data = str_replace( '"width=device-width, user-scalable=no, minimum-scale=1, maximum-scale=1, initial-scale=1.0"', '"width=device-width, initial-scale=1.0"', $cache_data );

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
