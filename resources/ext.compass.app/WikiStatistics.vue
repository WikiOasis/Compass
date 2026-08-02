<template>
	<ul class="ext-compass-statistics">
		<li
			v-for="item in items"
			:key="item.key"
			class="ext-compass-statistics__item"
		>
			<cdx-icon :icon="item.icon" size="small"></cdx-icon>
			<span>{{ item.text }}</span>
		</li>
	</ul>
</template>

<script>
const { defineComponent } = require( 'vue' );
const { CdxIcon } = require( './codex.js' );
const icons = require( './icons.json' );

// What the wiki has been through, what came out of it, and who is still there.
const STATISTICS = [
	{ key: 'edits', icon: icons.cdxIconEdit, message: 'compass-statistics-edits' },
	{ key: 'articles', icon: icons.cdxIconArticles, message: 'compass-statistics-articles' },
	{ key: 'activeusers', icon: icons.cdxIconUserActive, message: 'compass-statistics-users' }
];

module.exports = exports = defineComponent( {
	name: 'WikiStatistics',
	components: { CdxIcon },

	props: {
		statistics: {
			type: Object,
			required: true
		}
	},

	computed: {
		items() {
			return STATISTICS.map( ( statistic ) => Object.assign( {}, statistic, {
				text: this.$i18n(
					statistic.message, this.statistics[ statistic.key ] || 0
				).text()
			} ) );
		}
	}
} );
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.ext-compass-statistics {
	display: flex;
	flex-wrap: wrap;
	gap: @spacing-50 @spacing-100;
	margin: @spacing-100 0 0;
	padding: @spacing-75 0 0;
	border-top: 1px solid @border-color-subtle;
	color: @color-subtle;
	font-size: @font-size-small;
	list-style: none;

	// Nothing to rule off when the counts are all there is.
	&:first-child {
		margin-top: 0;
		padding-top: 0;
		border-top: 0;
	}

	&__item {
		display: flex;
		align-items: center;
		gap: @spacing-50;
		margin: 0;
	}

	// The rule that separates one count from the next, not a leading edge.
	&__item + &__item {
		padding-left: @spacing-100;
		border-left: 1px solid @border-color-subtle;
	}
}
</style>
