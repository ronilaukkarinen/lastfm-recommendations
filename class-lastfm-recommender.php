<?php
// phpcs:ignore PHPCompatibility.Variables.ForbiddenThisUseContexts.OutsideObjectContext
class LastFmRecommender {
  private $apiKey;
  private $username;
  private $baseUrl = 'http://ws.audioscrobbler.com/2.0/';
  private $cacheDir = 'cache';
  private $cacheTime = 7200;
  private $knownArtistRatio = 0.5; // 0.0-1.0 (0 = all new, 1 = all known)
  private $maxTopArtists = 6;
  private $maxSimilarArtists = 8;
  private $number_of_recommendations = 24;
  private $excludeListFile = 'excludelist.json';
  private $streamContext = null;
  private $maxConcurrentRequests = 5;
  private $requestDelay = 50000; // 50ms delay between requests in microseconds

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

				// Clear the recommendations cache when adding to exclude list
				$cacheKey = $this->getCacheKey( 'recommendations', [ $this->username ] );
				if ( file_exists( $cacheKey ) ) {
				  unlink( $cacheKey );
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

  private function makeApiRequest( $url, $method = '', $maxRetries = 2 ) {
		$attempts = 0;
		while ( $attempts <= $maxRetries ) {
		  try {
				$response = @file_get_contents( $url, false, $this->streamContext );

				if ( $response === false ) {
				  $attempts++;
				  if ( $attempts > $maxRetries ) {
						$error = error_get_last();
						$this->log( "API request failed for {$method} after {$attempts} attempts", [
						'url' => $url,
						'error' => $error['message'] ?? 'Unknown error',
						] );
						  return null;
				  }
				  usleep( $this->requestDelay * 2 );
				  continue;
					}

				$data = json_decode( $response, true );

				if ( json_last_error() !== JSON_ERROR_NONE || isset( $data['error'] ) ) {
				  $attempts++;
				  if ( $attempts > $maxRetries ) {
						return null;
				  }
				  usleep( $this->requestDelay * 2 );
				  continue;
					}

				return $data;
		  } catch ( Exception $e ) {
				$attempts++;
				if ( $attempts > $maxRetries ) {
					return null;
					}
				usleep( $this->requestDelay * 2 );
		  }
			}
		return null;
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
		$data = $this->makeApiRequest( $url, 'getTopArtists' );

		if ( ! $data || ! isset( $data['topartists']['artist'] ) ) {
		  throw new Exception( 'Failed to fetch top artists or invalid response' );
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
		$response = @file_get_contents( $url, false, $this->streamContext );
		return json_decode( $response, true );
  }

  private function getArtistImageFromPage( $artistName ) {
		try {
		  $url = 'https://www.last.fm/music/' . urlencode( $artistName );
		  $html = @file_get_contents( $url, false, $this->streamContext );

		  if ( $html === false ) {
				$this->log( 'Failed to fetch artist page', [
				'artist' => $artistName,
				'error' => error_get_last(),
				] );
				  return null;
		  }

		  if ( preg_match( '/background-image: url\((.*?)\)/', $html, $matches ) ) {
				return $matches[1];
		  }

		  // Fallback to searching for any artist image
		  if ( preg_match( '/<img[^>]*class="[^"]*avatar[^"]*"[^>]*src="([^"]*)"/', $html, $matches ) ) {
				return $matches[1];
		  }

		  return null;
			} catch ( Exception $e ) {
		  $this->log( 'Error fetching artist image', [
			'artist' => $artistName,
			'error' => $e->getMessage(),
		  ] );
		  return null;
			}
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

  private function getRandomTag() {
		$tags = [
		'rock', 'electronic', 'jazz', 'hip-hop', 'classical', 'metal',
		'indie', 'folk', 'ambient', 'punk', 'blues', 'experimental',
		'pop', 'alternative', 'soul', 'reggae', 'world', 'latin',
		];
		return $tags[array_rand( $tags )];
  }

  private function getArtistsByTag( $tag ) {
		$params = [
      'method' => 'tag.gettopartists',
      'tag' => $tag,
      'api_key' => $this->apiKey,
      'format' => 'json',
      'limit' => 50, // Get a good number of artists per tag
		];

		$url = $this->baseUrl . '?' . http_build_query( $params );
		$response = @file_get_contents( $url, false, $this->streamContext );
		$data = json_decode( $response, true );

		return $data['topartists']['artist'] ?? [];
  }

  private function createRecommendation( $artist, $isKnown = false ) {
		try {
		  $artistInfo = $this->getArtistInfo( $artist['name'] );
		  usleep( $this->requestDelay );

		  if ( ! isset( $artistInfo['artist'] ) ) {
				return null;
		  }

		  // Calculate match score if not provided
		  $match = $artist['match'] ?? ( $isKnown ? 1.0 : 0.5 );

		  // Only get lastplayed for known artists
		  $lastplayed = $isKnown ? $this->getLastPlayedTime( $artist['name'] ) : null;
		  $image = $this->getArtistImageFromPage( $artist['name'] );

		  return [
        'name' => $artist['name'],
        'match' => $match,
        'url' => $artist['url'] ?? '',
        'image' => $image,
        'listeners' => $artistInfo['artist']['stats']['listeners'] ?? '0',
        'playcount' => $artistInfo['artist']['stats']['playcount'] ?? '0',
        'summary' => strip_tags( $artistInfo['artist']['bio']['summary'] ?? '' ),
        'tags' => array_slice( array_column( $artistInfo['artist']['tags']['tag'] ?? [], 'name' ), 0, 5 ),
        'isKnown' => $isKnown,
        'userplaycount' => $artistInfo['artist']['stats']['userplaycount'] ?? 0,
        'lastplayed' => $lastplayed,
        'isNewArtist' => ! $isKnown,
		  ];
			} catch ( Exception $e ) {
		  $this->log( 'Error creating recommendation', [
        'artist' => $artist['name'],
        'error' => $e->getMessage(),
		  ] );
		  return null;
			}
  }

  private function getRandomTags( $count = 3 ) {
		try {
		  $params = [
        'method' => 'chart.gettoptags',
        'api_key' => $this->apiKey,
        'format' => 'json',
        'limit' => 50, // Get more tags to ensure variety
		  ];

		  $url = $this->baseUrl . '?' . http_build_query( $params );
		  $data = $this->makeApiRequest( $url, 'getTopTags' );

		  if ( ! $data || ! isset( $data['tags']['tag'] ) ) {
				$this->log( 'Failed to fetch tags', [ 'response' => $data ] );
				return [];
		  }

		  $tags = $data['tags']['tag'];
		  shuffle( $tags ); // Randomize the order
		  return array_slice( array_column( $tags, 'name' ), 0, $count );
			} catch ( Exception $e ) {
		  $this->log('Error getting random tags', [
			'error' => $e->getMessage(),
		  ]);
		  return [];
    }
  }

  public function getRecommendations( $isReplacement = false ) {
		$cacheKey = $this->getCacheKey( 'recommendations', [ $this->username ] );
		$excludeList = $this->getExcludeList();

		// Try to get from cache first, but only if not a replacement request
		if ( ! $isReplacement ) {
		  $cached = $this->getFromCache( $cacheKey );
		  if ( $cached !== null ) {
				// Filter out excluded artists from cached results
				$filtered = array_filter( $cached, function ( $artist ) use ( $excludeList ) {
				  return ! in_array( $artist['name'], $excludeList );
					} );

				  // Only use cache if we have enough non-excluded recommendations
				  if ( count( $filtered ) >= $this->number_of_recommendations ) {
					  return array_slice( $filtered, 0, $this->number_of_recommendations );
          }
		    }
			}

		try {
		  $knownArtists = [];
		  $newArtists = [];
		  $processedArtists = [];
		  $attempts = 0;
		  $maxAttempts = 3;

		  $targetKnown = ceil( $this->number_of_recommendations * $this->knownArtistRatio );
		  $targetNew = $this->number_of_recommendations - $targetKnown;

		  // Get top artists with retry
		  $retries = 2;
		  $topArtists = null;
		  while ( $retries > 0 && ! $topArtists ) {
				try {
					$topArtists = $this->getTopArtists();
					break;
					} catch ( Exception $e ) {
				  $retries--;
				  if ( $retries === 0 ) {
						throw $e;
				  }
				  usleep( $this->requestDelay * 2 );
        }
		  }

		  // Process known artists first with more careful error handling
		  foreach ( array_slice( $topArtists['topartists']['artist'], 0, $this->maxTopArtists ) as $artist ) {
				if ( count( $knownArtists ) >= $targetKnown ) {
				  break;
        }

				try {
				  if ( ! isset( $processedArtists[$artist['name']] ) && ! $this->isExcluded( $artist['name'] ) ) {
						$artistInfo = $this->getArtistInfo( $artist['name'] );
						usleep( $this->requestDelay );

						if ( $artistInfo && isset( $artistInfo['artist'] ) ) {
							  $recommendation = $this->createRecommendation( $artist, true );
							  if ( $recommendation ) {
                  $knownArtists[] = $recommendation;
                  $processedArtists[$artist['name']] = true;
								}
							}
				    }
					} catch ( Exception $e ) {
				  $this->log('Error processing known artist', [
					'artist' => $artist['name'],
					'error' => $e->getMessage(),
				  ]);
				  continue;
        }
		  }

		  // Get random artists from different tags for new recommendations
		  $randomArtists = [];
		  $tags = $this->getRandomTags( 3 ); // Get 3 random tags from Last.fm

		  if ( empty( $tags ) ) {
				$this->log( 'Warning: No tags fetched, using user top artists for recommendations' );
				// Fallback to using more top artists if we can't get tags
				$randomArtists = array_slice( $topArtists['topartists']['artist'], $this->maxTopArtists );
		  } else {
				foreach ( $tags as $tag ) {
				  $tagArtists = $this->getArtistsByTag( $tag );
				  if ( ! empty( $tagArtists ) ) {
						shuffle( $tagArtists );
						$randomArtists = array_merge(
						  $randomArtists,
						  array_slice( $tagArtists, 0, ceil( 50 / count( $tags ) ) )
						  );
						  usleep( $this->requestDelay );
				  }
        }
		  }

		  // Add logging for transparency
		  $this->log('Using tags for recommendations', [
        'tags' => $tags,
        'artists_per_tag' => ceil( 50 / count( $tags ) ),
        'total_random_artists' => count( $randomArtists ),
		  ]);

		  // Process new artists
		  shuffle( $randomArtists );
		  foreach ( $randomArtists as $artist ) {
				if ( $newCount >= $targetNew ) {
				  break;
        }

				if ( ! isset( $processedArtists[$artist['name']] ) && ! $this->isExcluded( $artist['name'] ) ) {
			  $recommendation = $this->createRecommendation( $artist, false );
			  if ( $recommendation &&
				  ( ! isset( $recommendation['userplaycount'] ) || $recommendation['userplaycount'] == 0 ) ) {
						$newArtists[] = $recommendation;
						$processedArtists[$artist['name']] = true;
			  }
					}
		  }

		  // Combine and shuffle final recommendations
		  $recommendations = array_merge( $knownArtists, $newArtists );
		  shuffle( $recommendations );

		  $this->log('Recommendation counts', [
        'known' => count( $knownArtists ),
        'new' => count( $newArtists ),
        'total' => count( $recommendations ),
        'target_known' => $targetKnown,
        'target_new' => $targetNew,
		  ]);

		  // Save to cache if we have enough recommendations
		  if ( count( $recommendations ) >= $this->number_of_recommendations ) {
				$recommendations = array_slice( $recommendations, 0, $this->number_of_recommendations );
				$this->saveToCache( $cacheKey, $recommendations );
		  }

		  return $recommendations;

			} catch ( Exception $e ) {
		  $this->log('Error in getRecommendations', [
			'error' => $e->getMessage(),
			'trace' => $e->getTraceAsString(),
		  ]);
		  throw $e;
    }
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
				if ( isset( $processedArtists[$similarArtist['name']] ) || $this->isExcluded( $similarArtist['name'] ) ) {
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
          'isNewArtist' => true,
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
