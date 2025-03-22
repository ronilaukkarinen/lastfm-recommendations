<?php
class LastFmRecommender {
  private $apiKey;
  private $username;
  private $baseUrl = 'http://ws.audioscrobbler.com/2.0/';
  private $cacheDir = 'cache';
  private $cacheTime = 300; // 5 minutes
  private $knownArtistRatio = 0.2; // 20% known, 80% new artists
  private $maxTopArtists = 20; // Limit top artists
  private $maxSimilarArtists = 5; // Limit similar artists per artist

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
		$response = file_get_contents( $url );
		return json_decode( $response, true );
  }

  public function getArtistInfo( $artist ) {
		$url = 'https://ws.audioscrobbler.com/2.0/?method=artist.getinfo&artist=' . urlencode( $artist ) . '&api_key=' . $this->apiKey . '&format=json';
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

  public function getRecommendations() {
		$cacheKey = $this->getCacheKey( 'recommendations', [ $this->username ] );
		$cached = $this->getFromCache( $cacheKey );

		if ( $cached !== null ) {
		  return $cached;
			}

		$topArtists = $this->getTopArtists();
		$knownArtists = [];
		$newArtists = [];
		$processedArtists = [];

		// Limit the number of top artists to process
		$topArtistsToProcess = array_slice( $topArtists['topartists']['artist'], 0, $this->maxTopArtists );

		foreach ( $topArtistsToProcess as $artist ) {
		  $similar = $this->getSimilarArtists( $artist['name'] );

		  if ( ! isset( $similar['similarartists']['artist'] )) continue;

		  // Process limited number of similar artists
		  $similarArtistsToProcess = array_slice( $similar['similarartists']['artist'], 0, $this->maxSimilarArtists );

		  foreach ( $similarArtistsToProcess as $similarArtist ) {
				if (isset( $processedArtists[$similarArtist['name']] )) continue;
				$processedArtists[$similarArtist['name']] = true;

				$artistInfo = $this->getArtistInfo( $similarArtist['name'] );
				if ( ! isset( $artistInfo['artist'] )) continue;

				$isKnown = false;
				foreach ( $topArtistsToProcess as $topArtist ) {
					if ( strtolower( $topArtist['name'] ) === strtolower( $similarArtist['name'] ) ) {
					$isKnown = true;
					break;
					  }
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
          'userplaycount' => $isKnown ? ( $topArtists['topartists']['artist'][array_search( $similarArtist['name'], array_column( $topArtists['topartists']['artist'], 'name' ) )]['playcount'] ?? 0 ) : 0,
				];

				if ( $isKnown ) {
				  $knownArtists[] = $recommendation;
					} else {
				  $newArtists[] = $recommendation;
        }

				// Break if we have enough recommendations
				if (count( $knownArtists ) >= 5 && count( $newArtists ) >= 15) break 2;
		    }
			}

		shuffle( $knownArtists );
		shuffle( $newArtists );

		$knownCount = min( ceil( 10 * $this->knownArtistRatio ), count( $knownArtists ) );
		$newCount = min( 10 - $knownCount, count( $newArtists ) );

		$recommendations = array_merge(
		array_slice( $knownArtists, 0, $knownCount ),
		array_slice( $newArtists, 0, $newCount )
		);

		shuffle( $recommendations );
		$this->saveToCache( $cacheKey, $recommendations );
		return array_slice( $recommendations, 0, 10 );
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
