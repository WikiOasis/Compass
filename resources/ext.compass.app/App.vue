<template>
	<div class="ext-compass-app">
		<p class="ext-compass-app__intro">
			{{ $i18n( 'compass-header-info' ).text() }}
		</p>

		<cdx-message v-if="error" type="error">
			{{ error }}
		</cdx-message>

		<section
			v-if="showHighlights && highlights.length"
			class="ext-compass-app__highlights"
		>
			<h2 class="ext-compass-app__heading">
				{{ $i18n( 'compass-highlights-heading' ).text() }}
			</h2>
			<div class="ext-compass-app__cards">
				<highlight-card
					v-for="wiki in highlights"
					:key="wiki.dbname"
					:wiki="wiki"
					:language-name="languageName( wiki.language )"
					:category-label="categoryLabel( wiki.category )"
					:can-curate="config.canCurate"
					@unpin="curate( $event, 'unpin' )"
					@delete="confirmDelete"
				></highlight-card>
			</div>
		</section>

		<section class="ext-compass-app__results">
			<h2 class="ext-compass-app__heading">
				{{ $i18n( 'compass-results-heading' ).text() }}
			</h2>

			<div
				class="ext-compass-app__toolbar"
				role="search"
				:aria-label="$i18n( 'compass-header' ).text()"
			>
				<cdx-search-input
					v-model="searchInput"
					class="ext-compass-app__search"
					:placeholder="$i18n( 'compass-filter-search-placeholder' ).text()"
					:aria-label="$i18n( 'compass-filter-search' ).text()"
					@update:model-value="onSearchInput"
				></cdx-search-input>

				<div class="ext-compass-app__filters">
					<cdx-field
						v-for="filter in filterControls"
						:key="filter.name"
						class="ext-compass-app__filter"
					>
						<template #label>
							{{ filter.label }}
						</template>
						<cdx-select
							v-model:selected="filters[ filter.name ]"
							:menu-items="filter.items"
							@update:selected="applyFilters"
						></cdx-select>
					</cdx-field>

					<cdx-button
						v-if="isFiltered"
						class="ext-compass-app__reset"
						@click="reset"
					>
						{{ $i18n( 'compass-button-reset' ).text() }}
					</cdx-button>
				</div>
			</div>

			<cdx-progress-bar
				v-if="loading"
				class="ext-compass-app__progress"
				:aria-label="$i18n( 'compass-loading' ).text()"
			></cdx-progress-bar>

			<cdx-table
				class="ext-compass-app__table"
				:caption="$i18n( 'compass-results-heading' ).text()"
				:hide-caption="true"
				:columns="columns"
				:data="rows"
				:use-row-headers="true"
			>
				<template #item-sitename="{ row }">
					<div class="ext-compass-app__wiki">
						<cdx-thumbnail
							v-if="row.wiki.thumbnail"
							class="ext-compass-app__thumbnail"
							:thumbnail="{ url: row.wiki.thumbnail }"
						></cdx-thumbnail>
						<div>
							<a class="ext-compass-app__name" :href="row.wiki.url">
								{{ row.wiki.sitename }}
							</a>
							<p v-if="row.wiki.description" class="ext-compass-app__description">
								{{ row.wiki.description }}
							</p>
							<cdx-accordion
								v-if="row.wiki.extendeddescription"
								class="ext-compass-app__more"
							>
								<template #title>
									{{ $i18n( 'compass-card-more' ).text() }}
								</template>
								<p>{{ row.wiki.extendeddescription }}</p>
							</cdx-accordion>
						</div>
					</div>
				</template>

				<template #item-language="{ row }">
					{{ languageName( row.wiki.language ) }}
				</template>

				<template #item-category="{ row }">
					{{ categoryLabel( row.wiki.category ) }}
				</template>

				<template #item-state="{ row }">
					<cdx-info-chip :status="stateStatus( row.wiki.state )">
						{{ stateLabel( row.wiki.state ) }}
					</cdx-info-chip>
					<cdx-info-chip v-if="row.wiki.private">
						{{ $i18n( 'compass-label-private' ).text() }}
					</cdx-info-chip>
				</template>

				<template #item-actions="{ row }">
					<div class="ext-compass-app__actions">
						<cdx-button
							weight="quiet"
							:aria-label="pinLabel( row.wiki )"
							:title="pinLabel( row.wiki )"
							@click="curate( row.wiki, row.wiki.highlighted ? 'unpin' : 'pin' )"
						>
							<cdx-icon :icon="icons.cdxIconPushPin"></cdx-icon>
						</cdx-button>
						<cdx-button
							weight="quiet"
							action="destructive"
							:aria-label="$i18n( 'compass-action-delete' ).text()"
							:title="$i18n( 'compass-action-delete' ).text()"
							@click="confirmDelete( row.wiki )"
						>
							<cdx-icon :icon="icons.cdxIconTrash"></cdx-icon>
						</cdx-button>
					</div>
				</template>

				<template #empty-state>
					{{ $i18n( 'compass-results-empty' ).text() }}
				</template>
			</cdx-table>

			<div v-if="total" class="ext-compass-app__pagination">
				<span class="ext-compass-app__count">{{ countText }}</span>
				<cdx-button :disabled="offset === 0" @click="page( -1 )">
					{{ $i18n( 'compass-pagination-previous' ).text() }}
				</cdx-button>
				<cdx-button :disabled="!hasNext" @click="page( 1 )">
					{{ $i18n( 'compass-pagination-next' ).text() }}
				</cdx-button>
			</div>
		</section>

		<cdx-dialog
			v-model:open="deleteOpen"
			:title="$i18n( 'compass-delete-title' ).text()"
			:use-close-button="true"
			:primary-action="primaryAction"
			:default-action="defaultAction"
			@primary="runDelete"
			@default="deleteOpen = false"
		>
			{{ deleteBody }}
		</cdx-dialog>
	</div>
</template>

<script>
const { defineComponent } = require( 'vue' );
const {
	CdxAccordion,
	CdxButton,
	CdxDialog,
	CdxField,
	CdxIcon,
	CdxInfoChip,
	CdxMessage,
	CdxProgressBar,
	CdxSearchInput,
	CdxSelect,
	CdxTable,
	CdxThumbnail
} = require( '@wikimedia/codex' );
const icons = require( './icons.json' );
const HighlightCard = require( './HighlightCard.vue' );

const ANY = '*';
const SEARCH_DELAY = 300;

module.exports = exports = defineComponent( {
	name: 'CompassApp',
	components: {
		CdxAccordion,
		CdxButton,
		CdxDialog,
		CdxField,
		CdxIcon,
		CdxInfoChip,
		CdxMessage,
		CdxProgressBar,
		CdxSearchInput,
		CdxSelect,
		CdxTable,
		CdxThumbnail,
		HighlightCard
	},

	data() {
		const config = mw.config.get( 'wgCompassConfig' );
		const params = new URLSearchParams( window.location.search );

		return {
			config: config,
			icons: icons,
			api: new mw.Api(),
			searchInput: params.get( 'search' ) || '',
			searchTimeout: null,
			filters: {
				search: params.get( 'search' ) || '',
				language: params.get( 'language' ) || ANY,
				category: params.get( 'category' ) || ANY,
				state: params.get( 'state' ) || ANY,
				visibility: params.get( 'visibility' ) || ANY,
				sort: params.get( 'sort' ) || 'name'
			},
			offset: Math.max( 0, parseInt( params.get( 'offset' ), 10 ) || 0 ),
			limit: config.limit,
			total: 0,
			wikis: [],
			highlights: [],
			loading: true,
			error: '',
			deleteOpen: false,
			deleteTarget: null
		};
	},

	computed: {
		columns() {
			const columns = [
				{ id: 'sitename', label: this.$i18n( 'compass-table-wiki' ).text(), minWidth: '20em' },
				{ id: 'language', label: this.$i18n( 'compass-table-language' ).text() },
				{ id: 'category', label: this.$i18n( 'compass-table-category' ).text() },
				{ id: 'state', label: this.$i18n( 'compass-table-state' ).text() },
				{ id: 'created', label: this.$i18n( 'compass-table-established' ).text() }
			];

			if ( this.config.canCurate ) {
				columns.push( {
					id: 'actions',
					label: this.$i18n( 'compass-table-actions' ).text(),
					textAlign: 'end'
				} );
			}

			return columns;
		},

		rows() {
			return this.wikis.map( ( wiki ) => ( {
				sitename: wiki.sitename,
				language: wiki.language,
				category: wiki.category,
				state: wiki.state,
				created: wiki.createdformatted,
				actions: '',
				wiki: wiki
			} ) );
		},

		filterControls() {
			const anyLabel = this.$i18n( 'compass-label-any' ).text();
			const controls = [];

			if ( this.config.languages.length > 1 ) {
				controls.push( {
					name: 'language',
					label: this.$i18n( 'compass-table-language' ).text(),
					items: [ { label: anyLabel, value: ANY } ].concat(
						this.config.languages.map( ( language ) => ( {
							label: language.name,
							value: language.code
						} ) )
					)
				} );
			}

			if ( this.config.categories.length > 1 ) {
				controls.push( {
					name: 'category',
					label: this.$i18n( 'compass-table-category' ).text(),
					items: [ { label: anyLabel, value: ANY } ].concat(
						this.config.categories.map( ( category ) => ( {
							label: category.label,
							value: category.value
						} ) )
					)
				} );
			}

			controls.push( {
				name: 'state',
				label: this.$i18n( 'compass-table-state' ).text(),
				items: [ { label: anyLabel, value: ANY } ].concat(
					this.config.states.map( ( state ) => ( {
						label: this.$i18n( 'compass-label-' + state ).text(),
						value: state
					} ) )
				)
			} );

			if ( this.config.usePrivateFilter ) {
				controls.push( {
					name: 'visibility',
					label: this.$i18n( 'compass-table-visibility' ).text(),
					items: [
						{ label: anyLabel, value: ANY },
						{ label: this.$i18n( 'compass-label-public' ).text(), value: 'public' },
						{ label: this.$i18n( 'compass-label-private' ).text(), value: 'private' }
					]
				} );
			}

			controls.push( {
				name: 'sort',
				label: this.$i18n( 'compass-sort-label' ).text(),
				items: [ 'name', 'newest', 'oldest' ].map( ( sort ) => ( {
					label: this.$i18n( 'compass-sort-' + sort ).text(),
					value: sort
				} ) )
			} );

			return controls;
		},

		isFiltered() {
			return this.filters.search !== '' ||
				this.filters.language !== ANY ||
				this.filters.category !== ANY ||
				this.filters.state !== ANY ||
				this.filters.visibility !== ANY;
		},

		showHighlights() {
			return !this.isFiltered && this.offset === 0;
		},

		hasNext() {
			return this.offset + this.limit < this.total;
		},

		countText() {
			return this.$i18n(
				'compass-results-count',
				this.offset + 1,
				this.offset + this.wikis.length,
				this.total
			).text();
		},

		primaryAction() {
			return {
				label: this.$i18n( 'compass-delete-confirm' ).text(),
				actionType: 'destructive'
			};
		},

		defaultAction() {
			return { label: this.$i18n( 'cancel' ).text() };
		},

		deleteBody() {
			return this.deleteTarget ?
				this.$i18n( 'compass-delete-body', this.deleteTarget.sitename ).text() :
				'';
		}
	},

	methods: {
		languageName( code ) {
			const language = this.config.languages.find( ( item ) => item.code === code );
			return language ? language.name : code;
		},

		categoryLabel( value ) {
			const category = this.config.categories.find( ( item ) => item.value === value );
			return category ? category.label : value;
		},

		stateLabel( state ) {
			return this.$i18n( 'compass-label-' + state ).text();
		},

		stateStatus( state ) {
			switch ( state ) {
				case 'deleted':
				case 'locked':
					return 'error';
				case 'closed':
				case 'inactive':
					return 'warning';
				default:
					return 'success';
			}
		},

		pinLabel( wiki ) {
			return wiki.highlighted ?
				this.$i18n( 'compass-action-unpin' ).text() :
				this.$i18n( 'compass-action-pin' ).text();
		},

		onSearchInput( value ) {
			clearTimeout( this.searchTimeout );
			this.searchTimeout = setTimeout( () => {
				this.filters.search = value.trim();
				this.applyFilters();
			}, SEARCH_DELAY );
		},

		applyFilters() {
			this.offset = 0;
			this.load();
		},

		page( direction ) {
			this.offset = Math.max( 0, this.offset + ( direction * this.limit ) );
			this.load();
			window.scrollTo( { top: 0, behavior: 'smooth' } );
		},

		reset() {
			clearTimeout( this.searchTimeout );
			this.searchInput = '';
			this.filters = {
				search: '',
				language: ANY,
				category: ANY,
				state: ANY,
				visibility: ANY,
				sort: this.filters.sort
			};
			this.applyFilters();
		},

		confirmDelete( wiki ) {
			this.deleteTarget = wiki;
			this.deleteOpen = true;
		},

		runDelete() {
			const wiki = this.deleteTarget;
			this.deleteOpen = false;
			if ( wiki ) {
				this.curate( wiki, 'delete' );
			}
		},

		curate( wiki, curateAction ) {
			this.error = '';
			return this.api.postWithToken( 'csrf', {
				action: 'compasscurate',
				format: 'json',
				formatversion: 2,
				uselang: mw.config.get( 'wgUserLanguage' ),
				dbname: wiki.dbname,
				curateaction: curateAction
			} ).then( () => {
				this.highlights = [];
				return this.load();
			} ).catch( ( code, result ) => {
				this.error = ( result && result.error && result.error.info ) ||
					this.$i18n( 'compass-error-curate' ).text();
			} );
		},

		updateUrl() {
			// Rewrite only the parameters this page owns, so that index.php
			// style URLs keep their title.
			const url = new URL( window.location.href );
			Object.keys( this.filters ).concat( [ 'offset' ] ).forEach( ( key ) => {
				url.searchParams.delete( key );
			} );

			Object.keys( this.filters ).forEach( ( key ) => {
				const value = this.filters[ key ];
				if ( value && value !== ANY && !( key === 'sort' && value === 'name' ) ) {
					url.searchParams.set( key, value );
				}
			} );

			if ( this.offset > 0 ) {
				url.searchParams.set( 'offset', String( this.offset ) );
			}

			window.history.replaceState( null, '', url.toString() );
		},

		load() {
			this.loading = true;
			this.updateUrl();

			const params = {
				action: 'query',
				list: 'compassdirectory',
				formatversion: 2,
				uselang: mw.config.get( 'wgUserLanguage' ),
				cdlimit: this.limit,
				cdoffset: this.offset,
				cdsearch: this.filters.search,
				cdlanguage: this.filters.language,
				cdcategory: this.filters.category,
				cdstate: this.filters.state,
				cdvisibility: this.filters.visibility,
				cdsort: this.filters.sort
			};

			if ( !this.isFiltered ) {
				params.cdexcludehighlighted = 1;
			}

			const requests = [ this.api.get( params ) ];

			if ( this.showHighlights && !this.highlights.length ) {
				requests.push( this.api.get( {
					action: 'query',
					list: 'compassdirectory',
					formatversion: 2,
					uselang: mw.config.get( 'wgUserLanguage' ),
					cdhighlighted: 1
				} ) );
			}

			return Promise.all( requests ).then( ( responses ) => {
				const directory = responses[ 0 ].query.compassdirectory;
				this.wikis = directory.wikis;
				this.total = directory.total;

				if ( responses[ 1 ] ) {
					this.highlights = responses[ 1 ].query.compassdirectory.highlighted;
				}
			} ).catch( () => {
				this.error = this.$i18n( 'compass-error-load' ).text();
				this.wikis = [];
				this.total = 0;
			} ).finally( () => {
				this.loading = false;
			} );
		}
	},

	mounted() {
		this.load();
	}
} );
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.ext-compass-app {
	display: flex;
	flex-direction: column;
	gap: @spacing-200;

	&__heading {
		margin: 0 0 @spacing-100;
		padding: 0;
		border: 0;
		font-size: @font-size-large;
		font-weight: @font-weight-bold;
	}

	&__intro {
		margin: 0;
		color: @color-subtle;
	}

	&__cards {
		display: grid;
		grid-template-columns: repeat( auto-fill, minmax( 22em, 1fr ) );
		gap: @spacing-100;
		align-items: stretch;
	}

	&__toolbar {
		display: flex;
		flex-direction: column;
		gap: @spacing-75;
		margin-bottom: @spacing-100;
	}

	&__search {
		max-width: 30em;
	}

	&__filters {
		display: flex;
		flex-wrap: wrap;
		align-items: flex-end;
		gap: @spacing-50;
	}

	&__filter {
		/* Keep the filter row compact: a very small label above a small select. */
		margin: 0;
		min-width: 9em;
		font-size: @font-size-small;

		.cdx-label {
			margin: 0;
			padding: 0;

			&__label__text {
				font-size: @font-size-x-small;
				font-weight: @font-weight-normal;
				color: @color-subtle;
			}
		}
	}

	&__reset {
		margin-left: @spacing-25;
	}

	&__progress {
		margin-bottom: @spacing-50;
	}

	&__wiki {
		display: flex;
		align-items: flex-start;
		gap: @spacing-75;
	}

	&__thumbnail .cdx-thumbnail__image,
	&__thumbnail .cdx-thumbnail__placeholder {
		border-radius: @border-radius-base;
	}

	&__name {
		font-weight: @font-weight-bold;
	}

	&__description {
		margin: @spacing-25 0 0;
		color: @color-subtle;
		font-weight: @font-weight-normal;
	}

	&__more {
		margin-top: @spacing-25;
	}

	&__actions {
		display: flex;
		justify-content: flex-end;
		gap: @spacing-25;
	}

	&__pagination {
		display: flex;
		flex-wrap: wrap;
		align-items: center;
		justify-content: flex-end;
		gap: @spacing-50;
		margin-top: @spacing-100;
	}

	&__count {
		margin-right: auto;
		color: @color-subtle;
	}

	.cdx-info-chip + .cdx-info-chip {
		margin-top: @spacing-25;
	}
}

@media screen and ( max-width: @max-width-breakpoint-mobile ) {
	.ext-compass-app {
		&__search {
			max-width: none;
		}

		&__filter {
			flex-grow: 1;
		}

		&__cards {
			grid-template-columns: 1fr;
		}
	}
}
</style>
