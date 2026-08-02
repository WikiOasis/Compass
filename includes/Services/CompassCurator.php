<?php

namespace WikiOasis\Compass\Services;

use MediaWiki\Config\ServiceOptions;
use Miraheze\ManageWiki\Helpers\Factories\ModuleFactory;
use StatusValue;
use WikiOasis\Compass\CompassListing;

/**
 * The curation actions behind both the inline controls on Special:Compass and
 * the compasscurate API module.
 */
class CompassCurator {

	public const CONSTRUCTOR_OPTIONS = [
		'CompassDefaultVisibility',
	];

	public function __construct(
		private readonly ServiceOptions $options,
		private readonly CompassStore $store,
		private readonly ModuleFactory $moduleFactory
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
	}

	public function setHighlighted( string $dbname, bool $highlighted ): StatusValue {
		if ( !$this->store->wikiExists( $dbname ) ) {
			return StatusValue::newFatal( 'compass-error-nowiki', $dbname );
		}

		if ( $highlighted && !$this->store->isHighlighted( $dbname ) ) {
			$max = $this->store->getMaxHighlightedWikis();
			if ( $this->store->countHighlighted() >= $max ) {
				return StatusValue::newFatal( 'compass-error-full', $max );
			}
		}

		$this->store->setHighlighted( $dbname, $highlighted );
		return StatusValue::newGood();
	}

	/**
	 * Clears the descriptions through ManageWiki, so the change is tracked
	 * there, and then drops the directory entry.
	 */
	public function removeListing( string $dbname ): StatusValue {
		if ( !$this->store->wikiExists( $dbname ) ) {
			return StatusValue::newFatal( 'compass-error-nowiki', $dbname );
		}

		$mwCore = $this->moduleFactory->core( $dbname );
		$mwCore->setExtraFieldData(
			'compass-visible', false,
			default: $this->options->get( 'CompassDefaultVisibility' )
		);

		foreach ( CompassListing::EXTRA_FIELDS as $field ) {
			$mwCore->setExtraFieldData( $field, '', default: '' );
		}

		$mwCore->commit();
		$this->store->deleteWiki( $dbname );

		return StatusValue::newGood();
	}
}
