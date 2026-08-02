<?php

use MediaWiki\Config\ServiceOptions;
use MediaWiki\MediaWikiServices;
use WikiOasis\Compass\Services\CompassCurator;
use WikiOasis\Compass\Services\CompassStore;

return [
	'CompassCurator' => static function ( MediaWikiServices $services ): CompassCurator {
		return new CompassCurator(
			new ServiceOptions(
				CompassCurator::CONSTRUCTOR_OPTIONS,
				$services->getMainConfig()
			),
			$services->get( 'CompassStore' ),
			$services->get( 'ManageWikiModuleFactory' )
		);
	},
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
