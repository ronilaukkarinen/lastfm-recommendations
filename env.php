<?php
function loadEnv( $path = '.env' ) {
  if ( ! file_exists( $path ) ) {
		throw new Exception( '.env file not found' );
  }

  $lines = file( $path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
  foreach ( $lines as $line ) {
		if ( strpos( $line, '=' ) !== false && strpos( trim( $line ), '#' ) !== 0 ) {
		  list($key, $value) = explode( '=', $line, 2 );
		  $key = trim( $key );
		  $value = trim( $value );

		  if ( ! array_key_exists( $key, $_ENV ) ) {
				putenv( sprintf( '%s=%s', $key, $value ) );
				$_ENV[ $key ] = $value;
		  }
    }
  }
}
