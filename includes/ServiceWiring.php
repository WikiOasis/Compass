<?php

use MediaWiki\Config\ServiceOptions;
use MediaWiki\MediaWikiServices;
use WikiOasis\Compass\Services\CompassStore;

return [
	'CompassStore' => static function ( MediaWikiServices $services ): CompassStore {
		return new CompassStore(
			new ServiceOptions(
				CompassStore::CONSTRUCTOR_OPTIONS,
				$services->getMainConfig()
			),
			$services->get( 'CreateWikiDatabaseUtils' )
		);
	},
];
