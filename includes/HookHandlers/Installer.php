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
	}
}
