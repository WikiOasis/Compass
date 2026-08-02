const Vue = require( 'vue' );
const App = require( './App.vue' );

/**
 * Swap the server-rendered directory for the Codex app.
 *
 * ResourceLoader executes a module as soon as it arrives, so whether the mount
 * point has been parsed yet depends on how much the skin puts in the head.
 * Citizen ships enough of it that this module regularly wins the race and finds
 * nothing to mount on, leaving the no-JavaScript fallback on screen for good.
 */
function mount() {
	const container = document.getElementById( 'ext-compass-app' );
	if ( !container ) {
		return;
	}

	try {
		Vue.createMwApp( App ).mount( container );
	} catch ( error ) {
		// Drop whatever the failed mount left behind and keep the
		// server-rendered directory: a broken app should degrade to a working
		// page rather than an empty one.
		container.remove();
		mw.log.error( 'ext.compass.app failed to mount', error );
		return;
	}

	const fallback = document.querySelector( '.ext-compass-fallback' );
	if ( fallback ) {
		fallback.remove();
	}
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', mount, { once: true } );
} else {
	mount();
}
