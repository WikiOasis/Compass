<?php

namespace WikiOasis\Compass\HookHandlers;

use MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook;

class Installer implements LoadExtensionSchemaUpdatesHook {

	/**
	 * @inheritDoc
	 * @codeCoverageIgnore Tested by updating or installing MediaWiki.
	 */
	public function onLoadExtensionSchemaUpdates( $updater ) {
		$dir = __DIR__ . '/../../sql';

		$updater->addExtensionUpdateOnVirtualDomain( [
			'virtual-createwiki',
			'addTable',
			'compass_wikis',
			"$dir/compass_wikis.sql",
			true,
		] );

		$updater->addExtensionUpdateOnVirtualDomain( [
			'virtual-createwiki',
			'addField',
			'compass_wikis',
			'cpw_thumbnail',
			"$dir/patches/patch-compass_wikis-add-cpw_thumbnail.sql",
			true,
		] );

		// One patch adds all three statistics columns, so the remaining two are
		// already there by the time their own check runs.
		$updater->addExtensionUpdateOnVirtualDomain( [
			'virtual-createwiki',
			'addField',
			'compass_wikis',
			'cpw_edits',
			"$dir/patches/patch-compass_wikis-add-statistics.sql",
			true,
		] );
	}
}
