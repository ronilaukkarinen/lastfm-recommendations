<?php
error_reporting( E_ALL );
ini_set( 'display_errors', 0 );
ini_set( 'max_execution_time', 1000 );
set_time_limit( 1000 );

header( 'Content-Type: application/json' );
header( 'Cache-Control: no-cache, must-revalidate' );
header( 'Pragma: no-cache' );
header( 'Expires: 0' );

require_once 'class-lastfm-recommender.php';
require_once 'config.php';

try {
  // Start output buffering early
  ob_start();

  // Add debug timestamp
  $startTime = microtime( true );

  // Clear entire cache directory if refresh is requested
  if ( isset( $_GET['refresh'] ) ) {
    $cacheDir = 'cache';
    if ( is_dir( $cacheDir ) ) {
      $files = glob( $cacheDir . '/*' );
      foreach ( $files as $file ) {
        if ( is_file( $file ) ) {
          unlink( $file );
        }
      }
    }
  }

  $recommender = new LastFmRecommender( LASTFM_API_KEY, LASTFM_USERNAME );

  // Update the stream context creation
  $ctx = stream_context_create([
    'http' => [
      'timeout' => 10,
      'ignore_errors' => true,
      'user_agent' => 'PHP/LastFM-Recommendations',
      'follow_location' => 1,
      'max_redirects' => 2,
      'protocol_version' => '1.1',
      'header' => [
        'Connection: close',
        'Accept: application/json',
      ],
    ],
    'ssl' => [
      'verify_peer' => false,
      'verify_peer_name' => false,
    ],
  ]);

  // Store the context in the recommender
  $recommender->setStreamContext( $ctx );

  // Handle cache expiry check
  if ( isset( $_GET['cache_expiry'] ) ) {
		echo json_encode( [ 'expiry' => $recommender->getCacheExpiry() ] );
		exit;
  }

  // Clear cache if requested
  if ( isset( $_GET['refresh'] ) ) {
    $cacheKey = $recommender->getCacheKey( 'recommendations', [ $recommender->username ] );
    if ( file_exists( $cacheKey ) ) {
      unlink( $cacheKey );
    }
  }

  // Handle replacement requests
  if ( isset( $_GET['replace'] ) ) {
    $recommendation = $recommender->getRecommendations( true );
    echo json_encode( [
      'recommendation' => $recommendation,
    ] );
    exit;
  }

  // Add new endpoint for exclude list management
  if ( isset( $_POST['action'] ) && $_POST['action'] === 'exclude' ) {
    try {
      if ( ! isset( $_POST['artist'] ) || empty( $_POST['artist'] ) ) {
        throw new Exception( 'Artist name is required' );
      }

      // Validate artist name
      $artistName = trim( $_POST['artist'] );
      if ( empty( $artistName ) ) {
        throw new Exception( 'Invalid artist name' );
      }

      // Check if cache directory is writable
      if ( ! is_writable( $recommender->cacheDir ) ) {
        throw new Exception( 'Cache directory is not writable' );
      }

      // Try to exclude the artist
      $success = $recommender->addToExcludeList( $artistName );

      if ( ! $success ) {
        throw new Exception( 'Failed to add artist to exclude list' );
      }

      // Clear the recommendations cache to force a refresh
      $cacheKey = $recommender->getCacheKey( 'recommendations', [ $recommender->username ] );
      if ( file_exists( $cacheKey ) ) {
        unlink( $cacheKey );
      }

      echo json_encode( [
        'success' => true,
        'message' => 'Artist excluded successfully',
      ] );
      exit;

    } catch ( Exception $e ) {
      error_log( 'Error excluding artist: ' . $e->getMessage() );
      http_response_code( 400 );
      echo json_encode( [
        'success' => false,
        'error' => $e->getMessage(),
      ] );
      exit;
    }
  }

  // Get recommendations
  $recommendations = $recommender->getRecommendations();

  // Validate recommendations
  if ( ! is_array( $recommendations ) ) {
    throw new Exception( 'Invalid recommendations data returned: ' . var_export( $recommendations, true ) );
  }

  if ( empty( $recommendations ) ) {
    throw new Exception( 'No recommendations found - please try refreshing' );
  }

  // Calculate execution time
  $executionTime = microtime( true ) - $startTime;

  // Clean any buffered output
  ob_clean();

  // Send response
  echo json_encode( [
    'recommendations' => $recommendations,
    'cache_expiry' => $recommender->getCacheExpiry(),
    'debug' => [
      'count' => count( $recommendations ),
      'expected' => 24,
      'execution_time' => round( $executionTime, 2 ),
      'timestamp' => time(),
      'memory_usage' => memory_get_peak_usage( true ),
      'php_version' => PHP_VERSION,
    ],
  ], JSON_THROW_ON_ERROR );

} catch ( Throwable $e ) {
  // Clean any output that might have been sent
  ob_clean();

  // Log the full error
  error_log( 'Last.fm Recommendations Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine() );
  error_log( 'Stack trace: ' . $e->getTraceAsString() );

  // Send detailed error response
  http_response_code( 500 );
  echo json_encode( [
    'error' => 'Failed to fetch recommendations. Please check your internet connection and Last.fm API status.',
    'debug' => [
      'message' => $e->getMessage(),
      'file' => basename( $e->getFile() ),
      'line' => $e->getLine(),
      'trace' => explode( "\n", $e->getTraceAsString() ),
      'execution_time' => isset( $startTime ) ? round( microtime( true ) - $startTime, 2 ) : null,
      'last_error' => error_get_last(),
    ],
  ], JSON_THROW_ON_ERROR );
}
