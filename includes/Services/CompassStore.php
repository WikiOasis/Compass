<?php

namespace WikiOasis\Compass\Services;

use MediaWiki\Config\ServiceOptions;
use Miraheze\CreateWiki\Services\CreateWikiDatabaseUtils;
use Wikimedia\Rdbms\IExpression;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\Rdbms\IResultWrapper;
use Wikimedia\Rdbms\LikeValue;
use Wikimedia\Rdbms\SelectQueryBuilder;

class CompassStore {

	public const CONSTRUCTOR_OPTIONS = [
		'CompassDefaultVisibility',
		'CompassListPrivateWikis',
		'CompassMaxHighlightedWikis',
		'CreateWikiUseClosedWikis',
		'CreateWikiUseInactiveWikis',
		'CreateWikiUsePrivateWikis',
	];

	private const TABLE = 'compass_wikis';

	private const FIELDS = [
		'wiki_dbname',
		'wiki_sitename',
		'wiki_url',
		'wiki_language',
		'wiki_category',
		'wiki_creation',
		'wiki_closed',
		'wiki_deleted',
		'wiki_inactive',
		'wiki_locked',
		'wiki_private',
		'cpw_description',
		'cpw_extended_description',
		'cpw_thumbnail',
		'cpw_highlighted',
		'cpw_highlight_order',
		'cpw_edits',
		'cpw_articles',
		'cpw_active_users',
	];

	public function __construct(
		private readonly ServiceOptions $options,
		private readonly CreateWikiDatabaseUtils $databaseUtils
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
	}

	public function getMaxHighlightedWikis(): int {
		return (int)$this->options->get( 'CompassMaxHighlightedWikis' );
	}

	public function getWikis(
		array $filters,
		int $limit,
		int $offset,
		bool $excludeHighlighted = false
	): IResultWrapper {
		$dbr = $this->databaseUtils->getGlobalReplicaDB();
		[ $sortField, $sortDirection ] = match ( $filters['sort'] ?? 'random' ) {
			'name' => [ 'wiki_sitename', SelectQueryBuilder::SORT_ASC ],
			'newest' => [ 'wiki_creation', SelectQueryBuilder::SORT_DESC ],
			'oldest' => [ 'wiki_creation', SelectQueryBuilder::SORT_ASC ],
			default => [ $this->getShuffleOrder( $dbr ), null ],
		};

		return $this->newListQuery( $dbr, $filters, $excludeHighlighted )
			->select( self::FIELDS )
			->orderBy( $sortField, $sortDirection )
			->limit( $limit )
			->offset( $offset )
			->caller( __METHOD__ )
			->fetchResultSet();
	}

	/**
	 * The language codes that listed wikis actually use, so that the filter
	 * only offers languages that can return a result.
	 *
	 * @return string[]
	 */
	public function getAvailableLanguages(): array {
		$dbr = $this->databaseUtils->getGlobalReplicaDB();
		return $this->newListQuery( $dbr, [] )
			->select( 'wiki_language' )
			->distinct()
			->orderBy( 'wiki_language', SelectQueryBuilder::SORT_ASC )
			->caller( __METHOD__ )
			->fetchFieldValues();
	}

	/**
	 * @return string[]
	 */
	public function getAvailableCategories(): array {
		$dbr = $this->databaseUtils->getGlobalReplicaDB();
		return $this->newListQuery( $dbr, [] )
			->select( 'wiki_category' )
			->distinct()
			->orderBy( 'wiki_category', SelectQueryBuilder::SORT_ASC )
			->caller( __METHOD__ )
			->fetchFieldValues();
	}

	public function countWikis( array $filters, bool $excludeHighlighted = false ): int {
		$dbr = $this->databaseUtils->getGlobalReplicaDB();
		return $this->newListQuery( $dbr, $filters, $excludeHighlighted )
			->select( '*' )
			->caller( __METHOD__ )
			->fetchRowCount();
	}

	/**
	 * Highlighted wikis as shown to readers. Wikis that opted out of being
	 * listed, or that are filtered out by the farm configuration, never appear.
	 */
	public function getHighlightedWikis(): IResultWrapper {
		$dbr = $this->databaseUtils->getGlobalReplicaDB();
		return $this->newListQuery( $dbr, [] )
			->select( self::FIELDS )
			->andWhere( [ 'cpw_highlighted' => 1 ] )
			->orderBy(
				[ 'cpw_highlight_order', 'wiki_sitename' ],
				SelectQueryBuilder::SORT_ASC
			)
			->limit( $this->getMaxHighlightedWikis() )
			->caller( __METHOD__ )
			->fetchResultSet();
	}

	public function countHighlighted(): int {
		$dbr = $this->databaseUtils->getGlobalReplicaDB();
		return $dbr->newSelectQueryBuilder()
			->select( '*' )
			->from( self::TABLE )
			->where( [ 'cpw_highlighted' => 1 ] )
			->caller( __METHOD__ )
			->fetchRowCount();
	}

	public function isHighlighted( string $dbname ): bool {
		$dbr = $this->databaseUtils->getGlobalReplicaDB();
		return (bool)$dbr->newSelectQueryBuilder()
			->select( 'cpw_dbname' )
			->from( self::TABLE )
			->where( [
				'cpw_dbname' => $dbname,
				'cpw_highlighted' => 1,
			] )
			->caller( __METHOD__ )
			->fetchField();
	}

	public function wikiExists( string $dbname ): bool {
		$dbr = $this->databaseUtils->getGlobalReplicaDB();
		return (bool)$dbr->newSelectQueryBuilder()
			->select( 'wiki_dbname' )
			->from( 'cw_wikis' )
			->where( [ 'wiki_dbname' => $dbname ] )
			->caller( __METHOD__ )
			->fetchField();
	}

	/**
	 * A null value leaves the stored field untouched.
	 */
	public function saveSettings(
		string $dbname,
		bool $visible,
		?string $description,
		?string $extendedDescription,
		?string $thumbnail
	): void {
		$dbw = $this->databaseUtils->getGlobalPrimaryDB();
		$set = [
			'cpw_visible' => (int)$visible,
			'cpw_touched' => $dbw->timestamp(),
		];

		if ( $description !== null ) {
			$set['cpw_description'] = $description;
		}

		if ( $extendedDescription !== null ) {
			$set['cpw_extended_description'] = $extendedDescription;
		}

		if ( $thumbnail !== null ) {
			$set['cpw_thumbnail'] = $thumbnail;
		}

		$dbw->newInsertQueryBuilder()
			->insertInto( self::TABLE )
			->row( [ 'cpw_dbname' => $dbname ] + $set )
			->onDuplicateKeyUpdate()
			->uniqueIndexFields( [ 'cpw_dbname' ] )
			->set( $set )
			->caller( __METHOD__ )
			->execute();
	}

	/**
	 * @param array{edits:int,articles:int,activeusers:int} $statistics
	 */
	public function updateStatistics( string $dbname, array $statistics ): void {
		$dbw = $this->databaseUtils->getGlobalPrimaryDB();
		$set = [
			'cpw_edits' => $statistics['edits'],
			'cpw_articles' => $statistics['articles'],
			'cpw_active_users' => $statistics['activeusers'],
			'cpw_touched' => $dbw->timestamp(),
		];

		$dbw->newInsertQueryBuilder()
			->insertInto( self::TABLE )
			->row( [
				'cpw_dbname' => $dbname,
				'cpw_visible' => (int)$this->options->get( 'CompassDefaultVisibility' ),
			] + $set )
			->onDuplicateKeyUpdate()
			->uniqueIndexFields( [ 'cpw_dbname' ] )
			->set( $set )
			->caller( __METHOD__ )
			->execute();
	}

	public function deleteWiki( string $dbname ): void {
		$this->databaseUtils->getGlobalPrimaryDB()
			->newDeleteQueryBuilder()
			->deleteFrom( self::TABLE )
			->where( [ 'cpw_dbname' => $dbname ] )
			->caller( __METHOD__ )
			->execute();
	}

	public function renameWiki( string $oldDbName, string $newDbName ): void {
		$dbw = $this->databaseUtils->getGlobalPrimaryDB();
		$dbw->startAtomic( __METHOD__ );

		$dbw->newDeleteQueryBuilder()
			->deleteFrom( self::TABLE )
			->where( [ 'cpw_dbname' => $newDbName ] )
			->caller( __METHOD__ )
			->execute();

		$dbw->newUpdateQueryBuilder()
			->update( self::TABLE )
			->set( [ 'cpw_dbname' => $newDbName ] )
			->where( [ 'cpw_dbname' => $oldDbName ] )
			->caller( __METHOD__ )
			->execute();

		$dbw->endAtomic( __METHOD__ );
	}

	public function setHighlighted( string $dbname, bool $highlighted ): void {
		$dbw = $this->databaseUtils->getGlobalPrimaryDB();
		$order = 0;

		if ( $highlighted ) {
			$order = 1 + (int)$dbw->newSelectQueryBuilder()
				->select( 'MAX(cpw_highlight_order)' )
				->from( self::TABLE )
				->where( [ 'cpw_highlighted' => 1 ] )
				->caller( __METHOD__ )
				->fetchField();
		}

		$set = [
			'cpw_highlighted' => (int)$highlighted,
			'cpw_highlight_order' => $order,
			'cpw_touched' => $dbw->timestamp(),
		];

		$dbw->newInsertQueryBuilder()
			->insertInto( self::TABLE )
			->row( [
				'cpw_dbname' => $dbname,
				'cpw_visible' => (int)$this->options->get( 'CompassDefaultVisibility' ),
			] + $set )
			->onDuplicateKeyUpdate()
			->uniqueIndexFields( [ 'cpw_dbname' ] )
			->set( $set )
			->caller( __METHOD__ )
			->execute();
	}

	/**
	 * Shuffles the directory so that browsing it surfaces different wikis. The
	 * seed only changes each hour, which keeps paging through one visit stable.
	 */
	private function getShuffleOrder( IReadableDatabase $dbr ): string {
		if ( $dbr->getType() !== 'mysql' ) {
			return 'RANDOM()';
		}

		$seed = ( (int)gmdate( 'z' ) * 24 ) + (int)gmdate( 'G' );
		return "RAND( $seed )";
	}

	private function newListQuery(
		IReadableDatabase $dbr,
		array $filters,
		bool $excludeHighlighted = false
	): SelectQueryBuilder {
		return $dbr->newSelectQueryBuilder()
			->from( 'cw_wikis' )
			->leftJoin( self::TABLE, null, 'cpw_dbname = wiki_dbname' )
			->where( $this->buildConds( $dbr, $filters, $excludeHighlighted ) );
	}

	private function buildConds(
		IReadableDatabase $dbr,
		array $filters,
		bool $excludeHighlighted = false
	): array {
		$conds = [];

		if ( $excludeHighlighted ) {
			$conds[] = $dbr->expr( 'cpw_highlighted', '=', 0 )
				->or( 'cpw_highlighted', '=', null );
		}

		if ( $this->options->get( 'CompassDefaultVisibility' ) ) {
			$conds[] = $dbr->expr( 'cpw_visible', '=', 1 )
				->or( 'cpw_visible', '=', null );
		} else {
			$conds['cpw_visible'] = 1;
		}

		$search = trim( $filters['search'] ?? '' );
		if ( $search !== '' ) {
			$conds[] = $dbr->expr( 'wiki_sitename', IExpression::LIKE,
				new LikeValue( $dbr->anyString(), $search, $dbr->anyString() )
			)->or( 'wiki_dbname', IExpression::LIKE,
				new LikeValue( $dbr->anyString(), $search, $dbr->anyString() )
			);
		}

		$language = $filters['language'] ?? '';
		if ( $language && $language !== '*' ) {
			$conds['wiki_language'] = $language;
		}

		$category = $filters['category'] ?? '';
		if ( $category && $category !== '*' ) {
			$conds['wiki_category'] = $category;
		}

		return $conds + $this->buildStateConds( $filters );
	}

	private function buildStateConds( array $filters ): array {
		$conds = [];
		$state = $filters['state'] ?? '';

		switch ( true ) {
			case $state === 'deleted':
				$conds['wiki_deleted'] = 1;
				break;
			case $state === 'locked':
				$conds['wiki_deleted'] = 0;
				$conds['wiki_locked'] = 1;
				break;
			case $state === 'closed' && $this->options->get( 'CreateWikiUseClosedWikis' ):
				$conds['wiki_closed'] = 1;
				$conds['wiki_deleted'] = 0;
				break;
			case $state === 'inactive' && $this->options->get( 'CreateWikiUseInactiveWikis' ):
				$conds['wiki_deleted'] = 0;
				$conds['wiki_inactive'] = 1;
				break;
			case $state === 'active':
				$conds['wiki_deleted'] = 0;
				if ( $this->options->get( 'CreateWikiUseClosedWikis' ) ) {
					$conds['wiki_closed'] = 0;
				}

				if ( $this->options->get( 'CreateWikiUseInactiveWikis' ) ) {
					$conds['wiki_inactive'] = 0;
				}
				break;
			default:
				$conds['wiki_deleted'] = 0;
		}

		if ( !$this->options->get( 'CreateWikiUsePrivateWikis' ) ) {
			return $conds;
		}

		$visibility = $filters['visibility'] ?? '';
		if ( !$this->options->get( 'CompassListPrivateWikis' ) || $visibility === 'public' ) {
			$conds['wiki_private'] = 0;
		} elseif ( $visibility === 'private' ) {
			$conds['wiki_private'] = 1;
		}

		return $conds;
	}
}
