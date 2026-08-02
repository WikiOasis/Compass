<?php

namespace WikiOasis\Compass\Maintenance;

use MediaWiki\Maintenance\Maintenance;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}

require_once "$IP/maintenance/Maintenance.php";

/**
 * Rebuilds the compass_wikis index from the ManageWiki extra field data of
 * every wiki. Run once after installing Compass, and whenever the index and
 * ManageWiki are believed to have drifted apart.
 */
class PopulateCompassIndex extends Maintenance {

	public function __construct() {
		parent::__construct();

		$this->addDescription( 'Rebuilds the Compass index from ManageWiki extra field data.' );
		$this->requireExtension( 'Compass' );
		$this->setBatchSize( 50 );
	}

	public function execute(): void {
		$services = $this->getServiceContainer();
		$databaseUtils = $services->get( 'CreateWikiDatabaseUtils' );
		$remoteWikiFactory = $services->get( 'RemoteWikiFactory' );
		$store = $services->get( 'CompassStore' );

		$default = (bool)$this->getConfig()->get( 'CompassDefaultVisibility' );
		$dbnames = $databaseUtils->getGlobalReplicaDB()
			->newSelectQueryBuilder()
			->select( 'wiki_dbname' )
			->from( 'cw_wikis' )
			->caller( __METHOD__ )
			->fetchFieldValues();

		$indexed = 0;
		foreach ( $dbnames as $dbname ) {
			$remoteWiki = $remoteWikiFactory->newInstance( $dbname );
			$store->saveSettings(
				$dbname,
				(bool)$remoteWiki->getExtraFieldData( 'compass-visible', default: $default ),
				(string)$remoteWiki->getExtraFieldData( 'compass-description',
					default: $remoteWiki->getExtraFieldData( 'description', default: '' )
				),
				(string)$remoteWiki->getExtraFieldData( 'compass-extended-description', default: '' )
			);

			if ( ++$indexed % $this->getBatchSize() === 0 ) {
				$this->waitForReplication();
				$this->output( "Indexed $indexed wikis.\n" );
			}
		}

		$this->output( "Done. Indexed $indexed wikis.\n" );
	}
}

// @codeCoverageIgnoreStart
$maintClass = PopulateCompassIndex::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
