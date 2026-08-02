<?php

namespace WikiOasis\Compass\Maintenance;

use MediaWiki\Maintenance\Maintenance;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}

require_once "$IP/maintenance/Maintenance.php";

/**
 * Reads site_stats from every wiki on the farm into the Compass index, which is
 * where Special:Compass takes the edit, article and active editor counts from.
 * Run this on a schedule; the numbers are only as fresh as the last run.
 */
class UpdateCompassStatistics extends Maintenance {

	public function __construct() {
		parent::__construct();

		$this->addDescription( 'Refreshes the wiki statistics shown on Special:Compass.' );
		$this->addOption( 'dbname', 'Only refresh this wiki.', false, true );
		$this->requireExtension( 'Compass' );
		$this->setBatchSize( 50 );
	}

	public function execute(): void {
		$services = $this->getServiceContainer();
		$databaseUtils = $services->get( 'CreateWikiDatabaseUtils' );
		$statistics = $services->get( 'CompassStatistics' );

		$dbname = $this->getOption( 'dbname' );
		$dbnames = $dbname !== null ? [ $dbname ] : $databaseUtils->getGlobalReplicaDB()
			->newSelectQueryBuilder()
			->select( 'wiki_dbname' )
			->from( 'cw_wikis' )
			->where( [ 'wiki_deleted' => 0 ] )
			->caller( __METHOD__ )
			->fetchFieldValues();

		$updated = 0;
		$skipped = 0;

		foreach ( $dbnames as $wiki ) {
			if ( !$statistics->refresh( $wiki ) ) {
				$this->output( "Could not read the statistics of $wiki.\n" );
				$skipped++;
				continue;
			}

			if ( ++$updated % $this->getBatchSize() === 0 ) {
				$this->waitForReplication();
				$this->output( "Updated $updated wikis.\n" );
			}
		}

		$this->output( "Done. Updated $updated wikis, skipped $skipped.\n" );
	}
}

// @codeCoverageIgnoreStart
$maintClass = UpdateCompassStatistics::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
