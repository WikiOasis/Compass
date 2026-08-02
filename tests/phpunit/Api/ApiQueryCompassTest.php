<?php

namespace WikiOasis\Compass\Tests\Api;

use MediaWiki\Tests\Api\ApiTestCase;
use MediaWiki\WikiMap\WikiMap;

/**
 * @group Compass
 * @group Database
 * @group medium
 * @coversDefaultClass \WikiOasis\Compass\Api\ApiQueryCompass
 */
class ApiQueryCompassTest extends ApiTestCase {

	/**
	 * @covers ::__construct
	 * @covers ::execute
	 */
	public function testQueryCompass(): void {
		$this->insertWiki();
		[ $response ] = $this->doApiRequest( [
			'action' => 'query',
			'list' => 'compass',
		] );

		$this->assertArrayHasKey( 'count', $response['query']['compass'] );
		$this->assertNotCount(
			0,
			$response['query']['compass']['wikis'],
			'compass API response should not be empty'
		);

		foreach ( $response['query']['compass']['wikis'] as $wiki => $data ) {
			$this->assertArrayHasKey( 'dbname', $data );
			$this->assertArrayHasKey( 'sitename', $data );
		}
	}

	private function insertWiki(): void {
		$databaseUtils = $this->getServiceContainer()->get( 'CreateWikiDatabaseUtils' );
		$dbw = $databaseUtils->getGlobalPrimaryDB();
		$dbw->newInsertQueryBuilder()
			->insertInto( 'cw_wikis' )
			->row( [
				'wiki_dbname' => WikiMap::getCurrentWikiId(),
				'wiki_dbcluster' => 'c1',
				'wiki_sitename' => 'Central Wiki',
				'wiki_language' => 'en',
				'wiki_private' => 0,
				'wiki_creation' => $dbw->timestamp(),
				'wiki_category' => 'test',
			] )
			->caller( __METHOD__ )
			->execute();
	}
}
