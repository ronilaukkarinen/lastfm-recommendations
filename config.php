<?php
require_once 'env.php';
loadEnv();

define( 'LASTFM_API_KEY', getenv( 'LASTFM_API_KEY' ) );
define( 'LASTFM_USERNAME', getenv( 'LASTFM_USERNAME' ) );
