<template>
	<cdx-card class="ext-compass-highlight" :url="wiki.url">
		<template #title>
			{{ wiki.sitename }}
		</template>
		<template #description>
			{{ wiki.description }}
		</template>
		<template #supporting-text>
			{{ supportingText }}
		</template>
	</cdx-card>
</template>

<script>
const { defineComponent } = require( 'vue' );
const { CdxCard } = require( '@wikimedia/codex' );

module.exports = exports = defineComponent( {
	name: 'HighlightCard',
	components: { CdxCard },

	props: {
		wiki: {
			type: Object,
			required: true
		},
		languageName: {
			type: String,
			default: ''
		},
		categoryLabel: {
			type: String,
			default: ''
		}
	},

	computed: {
		supportingText() {
			return [ this.categoryLabel, this.languageName ]
				.filter( Boolean )
				.join( this.$i18n( 'comma-separator' ).text() );
		}
	}
} );
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.ext-compass-highlight {
	height: 100%;
	border-color: @border-color-progressive;
	background-color: @background-color-progressive-subtle;
}
</style>
