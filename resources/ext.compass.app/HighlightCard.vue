<template>
	<div class="ext-compass-highlight">
		<cdx-card
			class="ext-compass-highlight__card"
			:thumbnail="thumbnail"
			:force-thumbnail="true"
		>
			<template #title>
				<a :href="wiki.url">{{ wiki.sitename }}</a>
			</template>
			<template #description>
				{{ wiki.description }}
			</template>
			<template #supporting-text>
				{{ supportingText }}
			</template>
		</cdx-card>

		<cdx-accordion v-if="wiki.extendeddescription" class="ext-compass-highlight__more">
			<template #title>
				{{ $i18n( 'compass-card-more' ).text() }}
			</template>
			<p>{{ wiki.extendeddescription }}</p>
		</cdx-accordion>

		<div v-if="canCurate" class="ext-compass-highlight__actions">
			<cdx-button
				weight="quiet"
				:aria-label="$i18n( 'compass-action-unpin' ).text()"
				:title="$i18n( 'compass-action-unpin' ).text()"
				@click="$emit( 'unpin', wiki )"
			>
				<cdx-icon :icon="icons.cdxIconPushPin"></cdx-icon>
			</cdx-button>
			<cdx-button
				weight="quiet"
				action="destructive"
				:aria-label="$i18n( 'compass-action-delete' ).text()"
				:title="$i18n( 'compass-action-delete' ).text()"
				@click="$emit( 'delete', wiki )"
			>
				<cdx-icon :icon="icons.cdxIconTrash"></cdx-icon>
			</cdx-button>
		</div>
	</div>
</template>

<script>
const { defineComponent } = require( 'vue' );
const { CdxAccordion, CdxButton, CdxCard, CdxIcon } = require( './codex.js' );
const icons = require( './icons.json' );

module.exports = exports = defineComponent( {
	name: 'HighlightCard',
	components: { CdxAccordion, CdxButton, CdxCard, CdxIcon },

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
		},
		canCurate: {
			type: Boolean,
			default: false
		}
	},

	emits: [ 'unpin', 'delete' ],

	data() {
		return { icons: icons };
	},

	computed: {
		thumbnail() {
			return this.wiki.thumbnail ? { url: this.wiki.thumbnail } : null;
		},

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
	display: flex;
	flex-direction: column;
	height: 100%;
	border: 1px solid @border-color-progressive;
	border-radius: @border-radius-base;
	background-color: @background-color-progressive-subtle;
	overflow: hidden;

	&__card.cdx-card {
		flex-grow: 1;
		align-items: flex-start;
		gap: @spacing-100;
		padding: @spacing-150;
		border: 0;
		border-radius: 0;
		background-color: transparent;

		.cdx-card__thumbnail.cdx-thumbnail {
			margin-right: 0;

			.cdx-thumbnail__image,
			.cdx-thumbnail__placeholder {
				width: 5rem;
				height: 5rem;
				border-radius: @border-radius-base;
			}
		}

		.cdx-card__text__title {
			font-size: @font-size-large;
		}
	}

	&__more {
		padding: 0 @spacing-150;
	}

	&__actions {
		display: flex;
		justify-content: flex-end;
		gap: @spacing-25;
		padding: @spacing-50 @spacing-100;
		border-top: 1px solid @border-color-subtle;
	}
}

@media screen and ( max-width: @max-width-breakpoint-mobile ) {
	.ext-compass-highlight__card.cdx-card {
		flex-direction: column;
	}
}
</style>
