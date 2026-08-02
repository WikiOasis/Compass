<?php

namespace WikiOasis\Compass;

class CompassListing {

	public const EXTRA_FIELDS = [
		'compass-description',
		'compass-extended-description',
		'compass-thumbnail',
	];

	/**
	 * Thumbnails are rendered straight into an image, so only absolute http(s)
	 * URLs are accepted.
	 */
	public static function isValidThumbnail( string $url ): bool {
		if ( $url === '' ) {
			return true;
		}

		if ( strlen( $url ) > 512 ) {
			return false;
		}

		$scheme = strtolower( (string)parse_url( $url, PHP_URL_SCHEME ) );
		return in_array( $scheme, [ 'http', 'https' ], true ) &&
			(bool)parse_url( $url, PHP_URL_HOST );
	}
}
