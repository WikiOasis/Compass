const Vue = require( 'vue' );
const App = require( './App.vue' );

const container = document.getElementById( 'ext-compass-app' );
if ( container ) {
	Vue.createMwApp( App ).mount( container );

	// Drop the server-rendered directory only once the app is really running,
	// so that a module which fails to load leaves a working page behind.
	const fallback = document.querySelector( '.ext-compass-fallback' );
	if ( fallback ) {
		fallback.remove();
	}
}
