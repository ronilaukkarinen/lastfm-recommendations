<?php
class LastFmRecommender {
  private $apiKey;
  private $username;
  private $baseUrl = 'http://ws.audioscrobbler.com/2.0/';
  private $cacheDir = 'cache';
  private $cacheTime = 3600;
  private $knownArtistRatio = 0.3; // 30% known, 70% new artists
  private $maxTopArtists = 15;
  private $maxSimilarArtists = 5;
  private $number_of_recommendations = 24;
  private $excludeListFile = 'excludelist.json';
  private $streamContext = null;

  public function __construct( $apiKey, $username ) { // phpcs:ignore
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

  private function getExcludeList() {
    $excludePath = $this->cacheDir . '/' . $this->excludeListFile;
    if ( ! file_exists( $excludePath ) ) {
      file_put_contents( $excludePath, json_encode( [] ) );
      return [];
    }

    return json_decode( file_get_contents( $excludePath ), true ) ?? [];
  }

  public function addToExcludeList( $artistName ) {
    try {
      $excludePath = $this->cacheDir . '/' . $this->excludeListFile;
      $excludeList = $this->getExcludeList();

      if ( ! in_array( $artistName, $excludeList ) ) {
        $excludeList[] = $artistName;
        if ( file_put_contents( $excludePath, json_encode( $excludeList ) ) === false ) {
          throw new Exception( 'Failed to write to exclude list file' );
        }
      }

      return true;
    } catch ( Exception $e ) {
      $this->log( 'Error adding artist to exclude list', [
        'artist' => $artistName,
        'error' => $e->getMessage(),
      ] );
      throw $e;
    }
  }

  private function isExcluded( $artistName ) {
    $excludeList = $this->getExcludeList();
    return in_array( $artistName, $excludeList );
  }

  public function setStreamContext( $context ) {
    $this->streamContext = $context;
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
		$response = @file_get_contents( $url, false, $this->streamContext );

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
		$response = @file_get_contents( $url, false, $this->streamContext );
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
		$response = @file_get_contents( $url, false, $this->streamContext );
		$data = json_decode( $response, true );

		if ( isset( $data['artisttracks']['track'][0]['date']['uts'] ) ) {
		  return $data['artisttracks']['track'][0]['date']['uts'];
    }

		return null;
  }

  public function getRecommendations( $isReplacement = false ) {
    if ( $isReplacement ) {
      return $this->getOneNewRecommendation();
    }

    $cacheKey = $this->getCacheKey( 'recommendations', [ $this->username ] );
    $cached = $this->getFromCache( $cacheKey );

    if ( $cached !== null && count( $cached ) === $this->number_of_recommendations ) {
      return $cached;
    }

    // Initialize arrays
    $knownArtists = [];
    $newArtists = [];
    $processedArtists = [];

    $topArtists = $this->getTopArtists();
    usleep( 100000 );

    $targetKnown = ceil( $this->number_of_recommendations * $this->knownArtistRatio );
    $targetNew = $this->number_of_recommendations - $targetKnown;

    // Process all top artists until we have enough recommendations
    foreach ( $topArtists['topartists']['artist'] as $artist ) {
      if ( count( $knownArtists ) >= $targetKnown && count( $newArtists ) >= $targetNew ) {
        break;
      }

      usleep( 100000 );
      $similar = $this->getSimilarArtists( $artist['name'] );

      if ( ! isset( $similar['similarartists']['artist'] ) ) {
        continue;
      }

      foreach ( $similar['similarartists']['artist'] as $similarArtist ) {
        if ( isset( $processedArtists[$similarArtist['name']] ) ) {
          continue;
        }

        $processedArtists[$similarArtist['name']] = true;

        // Skip if explicitly excluded
        if ( $this->isExcluded( $similarArtist['name'] ) ) {
          continue;
        }

        usleep( 100000 );
        $artistInfo = $this->getArtistInfo( $similarArtist['name'] );

        if ( ! isset( $artistInfo['artist'] ) ) {
          continue;
        }

        $isKnown = false;
        $userplaycount = 0;

        // Check if this is a known artist and get play count
        foreach ( $topArtists['topartists']['artist'] as $topArtist ) {
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
        if ( count( $knownArtists ) >= $targetKnown && count( $newArtists ) >= $targetNew ) {
          break 2;
        }
      }
    }

    // Ensure we have exactly 24 recommendations
    $recommendations = array_merge(
      array_slice( $knownArtists, 0, $targetKnown ),
      array_slice( $newArtists, 0, $targetNew )
    );

    shuffle( $recommendations );
    $this->saveToCache( $cacheKey, $recommendations );

    return $recommendations;
  }

  private function getOneNewRecommendation() {
    $topArtists = $this->getTopArtists();
    $processedArtists = [];
    foreach ( array_slice( $topArtists['topartists']['artist'], 0, $this->maxTopArtists ) as $artist ) {
      $similar = $this->getSimilarArtists( $artist['name'] );

      if ( ! isset( $similar['similarartists']['artist'] ) ) {
        continue;
      }

      foreach ( $similar['similarartists']['artist'] as $similarArtist ) {
        if ( isset( $processedArtists[$similarArtist['name']] ) ) {
          continue;
        }

        $processedArtists[$similarArtist['name']] = true;
        $artistInfo = $this->getArtistInfo( $similarArtist['name'] );

        if ( ! isset( $artistInfo['artist'] ) ) {
          continue;
        }

        return [
          'name' => $similarArtist['name'],
          'match' => $similarArtist['match'],
          'url' => $similarArtist['url'],
          'image' => $this->getArtistImageFromPage( $similarArtist['name'] ),
          'listeners' => $artistInfo['artist']['stats']['listeners'] ?? '0',
          'playcount' => $artistInfo['artist']['stats']['playcount'] ?? '0',
          'summary' => strip_tags( $artistInfo['artist']['bio']['summary'] ?? '' ),
          'tags' => array_slice( array_column( $artistInfo['artist']['tags']['tag'] ?? [], 'name' ), 0, 5 ),
          'isKnown' => false,
          'userplaycount' => $artistInfo['artist']['stats']['userplaycount'] ?? 0,
          'lastplayed' => $this->getLastPlayedTime( $similarArtist['name'] ),
        ];
      }
    }

    return null;
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
