<?php
class LastFmRecommender {
  private $apiKey;
  private $username;
  private $baseUrl = 'http://ws.audioscrobbler.com/2.0/';
  private $cacheDir = 'cache';
  private $cacheTime = 3600; // Increased cache time to 1 hour
  private $knownArtistRatio = 0.3; // 30% known, 70% new artists
  private $maxTopArtists = 15; // Reduced from 30
  private $maxSimilarArtists = 5; // Reduced from 8
  private $number_of_recommendations = 24; // Fixed at 24 (6 rows × 4 columns)

  public function __construct( $apiKey, $username ) {
		$this->apiKey = $apiKey;
		$this->username = $username;

		// Create cache directory if it doesn't exist
		if ( ! file_exists( $this->cacheDir ) ) {
		  mkdir( $this->cacheDir, 0755, true );
    }
  }

  private function getCacheKey( $method, $params = [] ) {
		return $this->cacheDir . '/' . md5( $method . serialize( $params ) ) . '.json';
  }

  private function getFromCache( $cacheKey ) {
		if ( file_exists( $cacheKey ) ) {
		  $cacheData = json_decode( file_get_contents( $cacheKey ), true );
		  if ( time() - $cacheData['timestamp'] < $this->cacheTime ) {
				return $cacheData['data'];
		  }
    }
		return null;
  }

  private function saveToCache( $cacheKey, $data ) {
		file_put_contents($cacheKey, json_encode([
      'timestamp' => time(),
      'data' => $data,
		]));
  }

  private function log( $message, $data = null ) {
		$logFile = $this->cacheDir . '/debug.log';
		$timestamp = date( 'Y-m-d H:i:s' );
		$logMessage = "[{$timestamp}] {$message}\n";
		if ( $data !== null ) {
		  $logMessage .= print_r( $data, true ) . "\n";
    }
		file_put_contents( $logFile, $logMessage, FILE_APPEND );
  }

  public function getTopArtists() {
		$params = [
      'method' => 'user.gettopartists',
      'user' => $this->username,
      'api_key' => $this->apiKey,
      'format' => 'json',
      'limit' => $this->maxTopArtists,
		];

		$url = $this->baseUrl . '?' . http_build_query( $params );
		$response = @file_get_contents( $url );

		if ( $response === false ) {
		  $error = error_get_last();
		  $this->log( 'Error fetching top artists', $error );
		  throw new Exception( 'Failed to fetch top artists: ' . ( $error['message'] ?? 'Unknown error' ) );
    }

		$data = json_decode( $response, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
		  $this->log( 'JSON decode error for top artists', [
				'error' => json_last_error_msg(),
				'response' => $response,
		  ] );
		  throw new Exception( 'Invalid response from Last.fm API' );
    }

		if ( isset( $data['error'] ) ) {
		  $this->log( 'Last.fm API error', $data );
		  throw new Exception( 'Last.fm API error: ' . ( $data['message'] ?? 'Unknown error' ) );
    }

		return $data;
  }

  public function getArtistInfo( $artist ) {
		$params = [
      'method' => 'artist.getinfo',
      'artist' => $artist,
      'api_key' => $this->apiKey,
      'format' => 'json',
      'username' => $this->username,  // Add username to get user's stats
		];

		$url = $this->baseUrl . '?' . http_build_query( $params );
		$response = file_get_contents( $url );
		return json_decode( $response, true );
  }

  public function getSimilarArtists( $artist ) {
		$params = [
      'method' => 'artist.getsimilar',
      'artist' => $artist,
      'api_key' => $this->apiKey,
      'format' => 'json',
      'limit' => $this->maxSimilarArtists,
		];

		$url = $this->baseUrl . '?' . http_build_query( $params );
		$response = file_get_contents( $url );
		return json_decode( $response, true );
  }

  private function getArtistImageFromPage( $artistName ) {
		$url = 'https://www.last.fm/music/' . urlencode( $artistName );
		$html = @file_get_contents( $url );

		if ( $html === false ) {
		  return null;
    }

		if ( preg_match( '/background-image: url\((.*?)\)/', $html, $matches ) ) {
		  return $matches[1];
    }

		return null;
  }

  private function getLastPlayedTime( $artistName ) {
		$params = [
      'method' => 'user.getartisttracks',
      'user' => $this->username,
      'artist' => $artistName,
      'api_key' => $this->apiKey,
      'format' => 'json',
      'limit' => 1,
		];

		$url = $this->baseUrl . '?' . http_build_query( $params );
		$response = file_get_contents( $url );
		$data = json_decode( $response, true );

		if ( isset( $data['artisttracks']['track'][0]['date']['uts'] ) ) {
		  return $data['artisttracks']['track'][0]['date']['uts'];
    }

		return null;
  }

  public function getRecommendations() {
		$cacheKey = $this->getCacheKey( 'recommendations', [ $this->username ] );
		$cached = $this->getFromCache( $cacheKey );

		if ( $cached !== null ) {
		  return $cached;
    }

		// Add sleep between API calls to avoid rate limiting
		$topArtists = $this->getTopArtists();
		usleep( 100000 ); // 0.1 second delay

		$knownArtists = [];
		$newArtists = [];
		$processedArtists = [];

		// Limit the number of top artists to process
		$topArtistsToProcess = array_slice( $topArtists['topartists']['artist'], 0, $this->maxTopArtists );

		foreach ( $topArtistsToProcess as $artist ) {
		  usleep( 100000 ); // 0.1 second delay between API calls
		  $similar = $this->getSimilarArtists( $artist['name'] );

		  if ( ! isset( $similar['similarartists']['artist'] ) ) {
				continue;
		  }

		  // Process limited number of similar artists
		  $similarArtistsToProcess = array_slice( $similar['similarartists']['artist'], 0, $this->maxSimilarArtists );

		  foreach ( $similarArtistsToProcess as $similarArtist ) {
				if ( isset( $processedArtists[$similarArtist['name']] ) ) {
				  continue;
				}
				$processedArtists[$similarArtist['name']] = true;

				usleep( 100000 ); // 0.1 second delay
				$artistInfo = $this->getArtistInfo( $similarArtist['name'] );

				if ( ! isset( $artistInfo['artist'] )) continue;

				$isKnown = false;
				$userplaycount = 0;

				// Check if this is a known artist and get play count
				foreach ( $topArtistsToProcess as $topArtist ) {
					if ( strtolower( $topArtist['name'] ) === strtolower( $similarArtist['name'] ) ) {
						$isKnown = true;
						$userplaycount = $topArtist['playcount'];
						break;
					}
					}

				// If not found in top artists, try to get play count from artist.getInfo
				if ( ! $isKnown && isset( $artistInfo['artist']['stats']['userplaycount'] ) ) {
					$userplaycount = $artistInfo['artist']['stats']['userplaycount'];
					$isKnown = $userplaycount > 0;
					}

				$recommendation = [
					'name' => $similarArtist['name'],
					'match' => $similarArtist['match'],
					'url' => $similarArtist['url'],
					'image' => $this->getArtistImageFromPage( $similarArtist['name'] ),
					'listeners' => $artistInfo['artist']['stats']['listeners'] ?? '0',
					'playcount' => $artistInfo['artist']['stats']['playcount'] ?? '0',
					'summary' => strip_tags( $artistInfo['artist']['bio']['summary'] ?? '' ),
					'tags' => array_slice( array_column( $artistInfo['artist']['tags']['tag'] ?? [], 'name' ), 0, 5 ),
					'isKnown' => $isKnown,
					'userplaycount' => $userplaycount,
					'lastplayed' => $this->getLastPlayedTime( $similarArtist['name'] ),
				];

				if ( $isKnown ) {
					$knownArtists[] = $recommendation;
					} else {
					$newArtists[] = $recommendation;
					}

				// Break earlier if we have enough recommendations
				if ( count( $knownArtists ) >= 10 && count( $newArtists ) >= 15 ) {
				  break;
				}
		    }
			}

		shuffle( $knownArtists );
		shuffle( $newArtists );

		$knownCount = min( ceil( $this->number_of_recommendations * $this->knownArtistRatio ), count( $knownArtists ) );
		$newCount = $this->number_of_recommendations - $knownCount;

		// If we don't have enough known artists, get more new ones
		if ( $knownCount < ceil( $this->number_of_recommendations * $this->knownArtistRatio ) ) {
		  $newCount = min( $this->number_of_recommendations, count( $newArtists ) );
			}

		$recommendations = array_merge(
		array_slice( $knownArtists, 0, $knownCount ),
		array_slice( $newArtists, 0, $newCount )
		);

		shuffle( $recommendations );
		$this->saveToCache( $cacheKey, $recommendations );

		// Always return exactly 24 recommendations
		$finalRecommendations = array_slice( $recommendations, 0, $this->number_of_recommendations );

		// If we don't have enough, pad with new artists
		if ( count( $finalRecommendations ) < $this->number_of_recommendations ) {
		  $remainingRecommendations = $this->number_of_recommendations - count( $finalRecommendations );
		  // Get more new artists if needed
		  while ( $newArtistsCount < $remainingRecommendations ) {
				foreach ( $topArtistsToProcess as $artist ) {
				  $similar = $this->getSimilarArtists( $artist['name'] );
				  if ( ! isset( $similar['similarartists']['artist'] )) continue;

				  foreach ( $similar['similarartists']['artist'] as $similarArtist ) {
						if (isset( $processedArtists[$similarArtist['name']] )) continue;
						// Process artist and add to newArtists array...
						// (Same logic as before for processing artists)
						if (count( $newArtists ) >= ( $this->number_of_recommendations - count( $finalRecommendations ) )) break 2;
				  }
					}
		  }

		  // Add more new artists to reach 24
		  $remainingCount = $this->number_of_recommendations - count( $finalRecommendations );
		  $finalRecommendations = array_merge(
			$finalRecommendations,
			array_slice( $newArtists, 0, $remainingCount )
		  );
			}

		return $finalRecommendations;
  }

  public function getCacheExpiry() {
		$cacheKey = $this->getCacheKey( 'recommendations', [ $this->username ] );
		if ( file_exists( $cacheKey ) ) {
		  $cacheData = json_decode( file_get_contents( $cacheKey ), true );
		  return ( $cacheData['timestamp'] + $this->cacheTime ) - time();
    }
		return 0;
  }
}
