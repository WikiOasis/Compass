const Vue = require( 'vue' );
const App = require( './App.vue' );

/**
 * Replace the placeholder Special:Compass renders with the Codex app.
 *
 * ResourceLoader executes a module as soon as it arrives, so whether the mount
 * point has been parsed yet depends on how much the skin puts in the head.
 * Citizen ships enough of it that this module regularly wins the race and finds
 * nothing to mount on, leaving the placeholder on screen for good.
 */
function mount() {
	const container = document.getElementById( 'ext-compass-app' );
	if ( !container ) {
		return;
	}

	try {
		Vue.createMwApp( App ).mount( container );
	} catch ( error ) {
		// Vue empties the mount point before it renders, so a failure here
		// would leave the page waiting for a directory that is never coming.
		container.className = 'ext-compass-error';
		container.textContent = mw.msg( 'compass-error-load' );
		mw.log.error( 'ext.compass.app failed to mount', error );
	}
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', mount, { once: true } );
} else {
	mount();
}
