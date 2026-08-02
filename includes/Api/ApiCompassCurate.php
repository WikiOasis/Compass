<?php

namespace WikiOasis\Compass\Api;

use MediaWiki\Api\ApiBase;
use MediaWiki\Api\ApiMain;
use Wikimedia\ParamValidator\ParamValidator;
use WikiOasis\Compass\Services\CompassCurator;

/**
 * The curation actions offered inline on Special:Compass.
 */
class ApiCompassCurate extends ApiBase {

	public function __construct(
		ApiMain $main,
		string $moduleName,
		private readonly CompassCurator $curator
	) {
		parent::__construct( $main, $moduleName );
	}

	public function execute(): void {
		$this->checkUserRightsAny( 'compass-curate' );

		$params = $this->extractRequestParams();
		$dbname = $params['dbname'];

		$status = match ( $params['curateaction'] ) {
			'pin' => $this->curator->setHighlighted( $dbname, true ),
			'unpin' => $this->curator->setHighlighted( $dbname, false ),
			default => $this->curator->removeListing( $dbname ),
		};

		if ( !$status->isGood() ) {
			$this->dieStatus( $status );
		}

		$this->getResult()->addValue( null, $this->getModuleName(), [
			'result' => 'success',
			'dbname' => $dbname,
			'curateaction' => $params['curateaction'],
		] );
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
