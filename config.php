<?php
require_once 'env.php';

// Check for production environment
$prodEnvPath = '/var/www/lastfm-recommendations.rolle.wtf/.env';
$localEnvPath = '.env';

if ( file_exists( $prodEnvPath ) ) {
  loadEnv( $prodEnvPath );
} else {
  loadEnv( $localEnvPath );
}

define( 'LASTFM_API_KEY', getenv( 'LASTFM_API_KEY' ) );
define( 'LASTFM_USERNAME', getenv( 'LASTFM_USERNAME' ) );
