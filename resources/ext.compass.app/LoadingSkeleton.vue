<template>
	<div class="ext-compass-skeleton" role="status">
		<span class="ext-compass-skeleton__label">
			{{ $i18n( 'compass-loading' ).text() }}
		</span>
		<div class="ext-compass-skeleton__intro"></div>

		<template v-if="cards">
			<div class="ext-compass-skeleton__heading"></div>
			<div class="ext-compass-skeleton__cards">
				<div
					v-for="card in cards"
					:key="card"
					class="ext-compass-skeleton__card"
				></div>
			</div>
		</template>

		<div class="ext-compass-skeleton__heading"></div>
		<div class="ext-compass-skeleton__toolbar"></div>
		<div class="ext-compass-skeleton__table">
			<div class="ext-compass-skeleton__head"></div>
			<div
				v-for="row in rows"
				:key="row"
				class="ext-compass-skeleton__row"
			></div>
		</div>
		<div class="ext-compass-skeleton__pagination"></div>
	</div>
</template>

<script>
const { defineComponent } = require( 'vue' );

/**
 * The same placeholder Special:Compass renders, so that mounting the app does
 * not move the page: the server-rendered one is handed over to this one, and
 * the directory only replaces it once there is something to show.
 *
 * Its styles live in ext.compass.styles, which both versions share.
 */
module.exports = exports = defineComponent( {
	name: 'LoadingSkeleton',

	props: {
		cards: {
			type: Number,
			default: 0
		},
		rows: {
			type: Number,
			required: true
		}
	}
} );
</script>
