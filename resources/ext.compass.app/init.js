const Vue = require( 'vue' );
const App = require( './App.vue' );

const container = document.getElementById( 'ext-compass-app' );
if ( container ) {
	Vue.createMwApp( App ).mount( container );
}
