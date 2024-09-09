<?php
/**
 * Webathletes
 *
 * @package      	  WA
 * @author        	  Webathletes
 * Description:       Simple WordPress page caching
 * Version:           1.0
 * Author:            Webathletes
 * Author URI:        https://webathletes.eu/
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

// check if the user is doing something
if( is_admin() || wp_doing_ajax() || !empty( $_SESSION ) || !empty( $_POST ) || !empty( $_FILES ) ) {
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

// check request for file types
if( $wa_cache && $_SERVER[ 'REQUEST_URI' ] != '/' ) {

    $wa_cache_ignore_exts = array(
        //Codes
        '.html',
        '.json',
        '.css',
        '.php', 
        'wp-json',

        // AFbeeldingen
        '.jpg',
        '.jpeg',
        '.png',
        '.gif',
        '.bmp',
        '.tiff',
        '.ico',
        '.webp',
    
        // Documenten
        '.pdf',
        '.doc',
        '.docx',
        '.xls',
        '.xlsx',
        '.ppt',
        '.pptx',
        '.odt',
        '.ods',
        '.odp',
        '.txt',
        '.rtf',
    
        // Audio
        '.mp3',
        '.m4a',
        '.ogg',
        '.wav',
        '.wma',
    
        // Video
        '.mp4',
        '.m4v',
        '.mov',
        '.wmv',
        '.avi',
        '.mpg',
        '.ogv',
        '.3gp',
        '.3g2',
    
        // Andere Bestandsformaten
        '.zip',
        '.rar',
        '.7z',
        '.gz',
        '.tar',
        '.svg',
        '.eot',
        '.woff',
        '.woff2',
        '.ttf'
    );

    $regex = '';
    foreach( $wa_cache_ignore_exts as $ext ) {
        $regex .= str_replace( ['.', '-'], ['\.', '\-'], $ext ).'|';
    }

    $regex = '/'.rtrim( $regex, '|' ).'/';

    if( preg_match( $regex, $_SERVER[ 'REQUEST_URI' ] ) ) {
        $wa_cache = false;
    }
}

// check if cache is a 'go'
if( !$wa_cache ) {
    return;
}

// create cache dir if not exists
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
$wa_cache_settings = (object) array(
    'timeout'  => 43200 //12 hours
);

if( file_exists( ABSPATH.'wp-content/settings/wa/optimize.json' ) ) {
    $wa_cache_saved_settings = json_decode( file_get_contents( ABSPATH.'wp-content/settings/wa/optimize.json' ) );

    if( !empty( $wa_cache_saved_settings->timeout ) && $wa_cache_saved_settings->timeout === 'true' ) {
        $wa_cache_settings->timeout = 0;
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
    if( is_404() || !empty( $_SESSION ) ) {
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
	    global $wa_cache_file;

        if( !empty( $wa_cache_file ) && preg_match( '/html|head|body/', $cache_data ) ) {
            $cache_data = str_replace( '"width=device-width, user-scalable=no, minimum-scale=1, maximum-scale=1, initial-scale=1.0"', '"width=device-width, initial-scale=1.0"', $cache_data );

            file_put_contents( $wa_cache_file, $cache_data );
        }

        echo $cache_data;

        $wa_cache_done = true;
    }
}

// hook on shutdown and footer
add_action( 'wp_footer', 'wa_cache_save', 999999999 );
add_action( 'shutdown', 'wa_cache_save', 999999999 );
