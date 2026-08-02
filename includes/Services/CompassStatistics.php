<?php

namespace WikiOasis\Compass\Services;

use Miraheze\CreateWiki\Services\CreateWikiDatabaseUtils;
use Wikimedia\Rdbms\DBError;

/**
 * Copies the site statistics of the individual wikis into the directory index.
 *
 * Special:Compass lists wikis from other databases than its own, so the numbers
 * have to be collected ahead of time: reading site_stats while the directory is
 * rendered would mean one connection per listed wiki on every page view.
 */
class CompassStatistics {

	public function __construct(
		private readonly CreateWikiDatabaseUtils $databaseUtils,
		private readonly CompassStore $store
	) {
	}

	/**
	 * @return ?array{edits:int,articles:int,activeusers:int} Null when the wiki
	 *   cannot be reached or has not counted itself yet
	 */
	public function fetch( string $dbname ): ?array {
		try {
			$row = $this->databaseUtils->getRemoteWikiReplicaDB( $dbname )
				->newSelectQueryBuilder()
				->select( [ 'ss_total_edits', 'ss_good_articles', 'ss_active_users' ] )
				->from( 'site_stats' )
				->caller( __METHOD__ )
				->fetchRow();
		} catch ( DBError ) {
			// A wiki that is being created, renamed or dropped is missing from
			// the directory for a while rather than holding up the whole run.
			return null;
		}

		if ( !$row ) {
			return null;
		}

		// site_stats holds -1 for a count that has never been initialised.
		return [
			'edits' => max( 0, (int)$row->ss_total_edits ),
			'articles' => max( 0, (int)$row->ss_good_articles ),
			'activeusers' => max( 0, (int)$row->ss_active_users ),
		];
	}

	/**
	 * @return bool Whether the statistics could be read and stored
	 */
	public function refresh( string $dbname ): bool {
		$statistics = $this->fetch( $dbname );
		if ( $statistics === null ) {
			return false;
		}

		$this->store->updateStatistics( $dbname, $statistics );
		return true;
	}
}
