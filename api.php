<?php
error_reporting( E_ALL );
ini_set( 'display_errors', 1 );

header( 'Content-Type: application/json' );

require_once 'class-lastfm-recommender.php';
require_once 'config.php';

try {
  $recommender = new LastFmRecommender( LASTFM_API_KEY, LASTFM_USERNAME );

  if ( isset( $_GET['cache_expiry'] ) ) {
		echo json_encode( [ 'expiry' => $recommender->getCacheExpiry() ] );
		exit;
  }

  $recommendations = $recommender->getRecommendations();
  echo json_encode([
    'recommendations' => $recommendations,
    'cache_expiry' => $recommender->getCacheExpiry(),
  ]);
} catch ( Throwable $e ) {
  http_response_code( 500 );
  echo json_encode( [ 'error' => $e->getMessage() ] );
}
