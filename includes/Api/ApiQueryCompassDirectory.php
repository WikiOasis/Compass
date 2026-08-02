<?php

namespace WikiOasis\Compass\Api;

use MediaWiki\Api\ApiQuery;
use MediaWiki\Api\ApiQueryBase;
use Miraheze\CreateWiki\Services\CreateWikiValidator;
use stdClass;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\ParamValidator\TypeDef\IntegerDef;
use WikiOasis\Compass\Services\CompassStore;

/**
 * Backs the Special:Compass interface: the highlighted wikis and one page of
 * the filtered directory.
 */
class ApiQueryCompassDirectory extends ApiQueryBase {

	public function __construct(
		ApiQuery $query,
		string $moduleName,
		private readonly CompassStore $store,
		private readonly CreateWikiValidator $validator
	) {
		parent::__construct( $query, $moduleName, 'cd' );
	}

	public function execute(): void {
		$params = $this->extractRequestParams();
		$result = $this->getResult();
		$path = [ 'query', $this->getModuleName() ];

		if ( $params['highlighted'] ) {
			$wikis = [];
			foreach ( $this->store->getHighlightedWikis() as $row ) {
				$wikis[] = $this->formatRow( $row );
			}

			$result->addValue( $path, 'highlighted', $wikis );
			$result->addIndexedTagName( array_merge( $path, [ 'highlighted' ] ), 'wiki' );
			return;
		}

		$filters = [
			'search' => $params['search'],
			'language' => $params['language'],
			'category' => $params['category'],
			'state' => $params['state'],
			'visibility' => $params['visibility'],
			'sort' => $params['sort'],
		];

		$exclude = (bool)$params['excludehighlighted'];
		$wikis = [];
		foreach ( $this->store->getWikis( $filters, $params['limit'], $params['offset'], $exclude ) as $row ) {
			$wikis[] = $this->formatRow( $row );
		}

		$result->addValue( $path, 'total', $this->store->countWikis( $filters, $exclude ) );
		$result->addValue( $path, 'offset', $params['offset'] );
		$result->addValue( $path, 'wikis', $wikis );
		$result->addIndexedTagName( array_merge( $path, [ 'wikis' ] ), 'wiki' );
	}

	private function formatRow( stdClass $row ): array {
		return [
			'dbname' => $row->wiki_dbname,
			'sitename' => $row->wiki_sitename,
			'url' => $row->wiki_url ?: $this->validator->getValidUrl( $row->wiki_dbname ),
			'language' => $row->wiki_language,
			'category' => $row->wiki_category,
			'state' => $this->getState( $row ),
			'private' => (bool)$row->wiki_private,
			'highlighted' => (bool)( $row->cpw_highlighted ?? false ),
			'created' => wfTimestamp( TS_ISO_8601, $row->wiki_creation ),
			'createdformatted' => $this->getLanguage()->userDate(
				$row->wiki_creation, $this->getUser()
			),
			'description' => (string)( $row->cpw_description ?? '' ),
			'extendeddescription' => (string)( $row->cpw_extended_description ?? '' ),
		];
	}

	private function getState( stdClass $row ): string {
		return match ( true ) {
			(bool)$row->wiki_deleted => 'deleted',
			(bool)$row->wiki_locked => 'locked',
			(bool)$row->wiki_closed => 'closed',
			(bool)$row->wiki_inactive => 'inactive',
			default => 'open',
		};
	}

	/**
	 * @inheritDoc
	 * @param array $params @phan-unused-param
	 */
	public function getCacheMode( $params ): string {
		return 'public';
	}

	/** @inheritDoc */
	protected function getAllowedParams(): array {
		return [
			'category' => [
				ParamValidator::PARAM_DEFAULT => '*',
				ParamValidator::PARAM_TYPE => 'string',
			],
			'excludehighlighted' => [
				ParamValidator::PARAM_DEFAULT => false,
				ParamValidator::PARAM_TYPE => 'boolean',
			],
			'highlighted' => [
				ParamValidator::PARAM_DEFAULT => false,
				ParamValidator::PARAM_TYPE => 'boolean',
			],
			'language' => [
				ParamValidator::PARAM_DEFAULT => '*',
				ParamValidator::PARAM_TYPE => 'string',
			],
			'limit' => [
				IntegerDef::PARAM_MAX => 100,
				IntegerDef::PARAM_MAX2 => 200,
				IntegerDef::PARAM_MIN => 1,
				ParamValidator::PARAM_DEFAULT => 20,
				ParamValidator::PARAM_TYPE => 'limit',
			],
			'offset' => [
				IntegerDef::PARAM_MIN => 0,
				ParamValidator::PARAM_DEFAULT => 0,
				ParamValidator::PARAM_TYPE => 'integer',
			],
			'search' => [
				ParamValidator::PARAM_DEFAULT => '',
				ParamValidator::PARAM_TYPE => 'string',
			],
			'sort' => [
				ParamValidator::PARAM_DEFAULT => 'name',
				ParamValidator::PARAM_TYPE => [ 'name', 'newest', 'oldest' ],
			],
			'state' => [
				ParamValidator::PARAM_DEFAULT => '*',
				ParamValidator::PARAM_TYPE => [
					'*',
					'active',
					'closed',
					'deleted',
					'inactive',
					'locked',
				],
			],
			'visibility' => [
				ParamValidator::PARAM_DEFAULT => '*',
				ParamValidator::PARAM_TYPE => [ '*', 'private', 'public' ],
			],
		];
	}

	/** @inheritDoc */
	protected function getExamplesMessages(): array {
		return [
			'action=query&list=compassdirectory' => 'apihelp-query+compassdirectory-example',
		];
	}
}
