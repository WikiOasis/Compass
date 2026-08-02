<?php

namespace WikiOasis\Compass\HookHandlers;

use Miraheze\CreateWiki\Hooks\CreateWikiDeletionHook;
use Miraheze\CreateWiki\Hooks\CreateWikiRenameHook;
use Wikimedia\Rdbms\DBConnRef;
use WikiOasis\Compass\Services\CompassStore;

/**
 * Keeps the compass_wikis index in step with the wikis that exist. Newly
 * created wikis need no entry: without one they are simply not listed until
 * someone opts in through ManageWiki.
 */
class CreateWiki implements
	CreateWikiDeletionHook,
	CreateWikiRenameHook
{

	public function __construct(
		private readonly CompassStore $store
	) {
	}

	/**
	 * @inheritDoc
	 * @param DBConnRef $cwdb @phan-unused-param
	 */
	public function onCreateWikiDeletion( DBConnRef $cwdb, string $dbname ): void {
		$this->store->deleteWiki( $dbname );
	}

	/**
	 * @inheritDoc
	 * @param DBConnRef $cwdb @phan-unused-param
	 */
	public function onCreateWikiRename(
		DBConnRef $cwdb,
		string $oldDbName,
		string $newDbName
	): void {
		$this->store->renameWiki( $oldDbName, $newDbName );
	}
}
