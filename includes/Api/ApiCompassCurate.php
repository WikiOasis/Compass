<?php

namespace WikiOasis\Compass\Api;

use MediaWiki\Api\ApiBase;
use MediaWiki\Api\ApiMain;
use MediaWiki\Config\Config;
use Miraheze\ManageWiki\Helpers\Factories\ModuleFactory;
use Wikimedia\ParamValidator\ParamValidator;
use WikiOasis\Compass\CompassListing;
use WikiOasis\Compass\Services\CompassStore;

/**
 * The curation actions offered inline on Special:Compass.
 */
class ApiCompassCurate extends ApiBase {

	public function __construct(
		ApiMain $main,
		string $moduleName,
		private readonly CompassStore $store,
		private readonly ModuleFactory $moduleFactory,
		private readonly Config $config
	) {
		parent::__construct( $main, $moduleName );
	}

	public function execute(): void {
		$this->checkUserRightsAny( 'compass-curate' );

		$params = $this->extractRequestParams();
		$dbname = $params['dbname'];

		if ( !$this->store->wikiExists( $dbname ) ) {
			$this->dieWithError( [ 'compass-error-nowiki', wfEscapeWikiText( $dbname ) ] );
		}

		switch ( $params['curateaction'] ) {
			case 'pin':
				$this->pin( $dbname );
				break;
			case 'unpin':
				$this->store->setHighlighted( $dbname, false );
				break;
			case 'delete':
				$this->deleteListing( $dbname );
				break;
		}

		$this->getResult()->addValue( null, $this->getModuleName(), [
			'result' => 'success',
			'dbname' => $dbname,
			'curateaction' => $params['curateaction'],
		] );
	}

	private function pin( string $dbname ): void {
		$max = $this->store->getMaxHighlightedWikis();
		if ( !$this->store->isHighlighted( $dbname ) && $this->store->countHighlighted() >= $max ) {
			$this->dieWithError( [ 'compass-error-full', $max ] );
		}

		$this->store->setHighlighted( $dbname, true );
	}

	/**
	 * Removing a listing clears the descriptions through ManageWiki, so the
	 * change is tracked there, and then drops the directory entry.
	 */
	private function deleteListing( string $dbname ): void {
		$mwCore = $this->moduleFactory->core( $dbname );
		$mwCore->setExtraFieldData(
			'compass-visible', false,
			default: $this->config->get( 'CompassDefaultVisibility' )
		);

		foreach ( CompassListing::EXTRA_FIELDS as $field ) {
			$mwCore->setExtraFieldData( $field, '', default: '' );
		}

		$mwCore->commit();
		$this->store->deleteWiki( $dbname );
	}

	/** @inheritDoc */
	public function isWriteMode(): bool {
		return true;
	}

	/** @inheritDoc */
	public function mustBePosted(): bool {
		return true;
	}

	/** @inheritDoc */
	public function needsToken(): string {
		return 'csrf';
	}

	/** @inheritDoc */
	protected function getAllowedParams(): array {
		return [
			'curateaction' => [
				ParamValidator::PARAM_REQUIRED => true,
				ParamValidator::PARAM_TYPE => [ 'delete', 'pin', 'unpin' ],
			],
			'dbname' => [
				ParamValidator::PARAM_REQUIRED => true,
				ParamValidator::PARAM_TYPE => 'string',
			],
		];
	}

	/** @inheritDoc */
	protected function getExamplesMessages(): array {
		return [
			'action=compasscurate&curateaction=pin&dbname=examplewiki&token=123ABC' =>
				'apihelp-compasscurate-example',
		];
	}
}
