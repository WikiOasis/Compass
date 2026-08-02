<template>
	<div class="ext-compass-app">
		<p class="ext-compass-app__intro">
			{{ $i18n( 'compass-header-info' ).text() }}
		</p>

		<section
			class="ext-compass-app__toolbar"
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
				<cdx-field v-if="languageItems.length > 1">
					<template #label>
						{{ $i18n( 'compass-table-language' ).text() }}
					</template>
					<cdx-select
						v-model:selected="filters.language"
						:menu-items="languageItems"
						@update:selected="applyFilters"
					></cdx-select>
				</cdx-field>

				<cdx-field v-if="categoryItems.length > 1">
					<template #label>
						{{ $i18n( 'compass-table-category' ).text() }}
					</template>
					<cdx-select
						v-model:selected="filters.category"
						:menu-items="categoryItems"
						@update:selected="applyFilters"
					></cdx-select>
				</cdx-field>

				<cdx-field>
					<template #label>
						{{ $i18n( 'compass-table-state' ).text() }}
					</template>
					<cdx-select
						v-model:selected="filters.state"
						:menu-items="stateItems"
						@update:selected="applyFilters"
					></cdx-select>
				</cdx-field>

				<cdx-field v-if="config.usePrivateFilter">
					<template #label>
						{{ $i18n( 'compass-table-visibility' ).text() }}
					</template>
					<cdx-select
						v-model:selected="filters.visibility"
						:menu-items="visibilityItems"
						@update:selected="applyFilters"
					></cdx-select>
				</cdx-field>

				<cdx-field>
					<template #label>
						{{ $i18n( 'compass-sort-label' ).text() }}
					</template>
					<cdx-select
						v-model:selected="filters.sort"
						:menu-items="sortItems"
						@update:selected="applyFilters"
					></cdx-select>
				</cdx-field>
			</div>

			<cdx-button
				v-if="isFiltered"
				class="ext-compass-app__reset"
				@click="reset"
			>
				{{ $i18n( 'compass-button-reset' ).text() }}
			</cdx-button>
		</section>

		<cdx-message v-if="error" type="error">
			{{ $i18n( 'compass-error-load' ).text() }}
		</cdx-message>

		<section v-if="showHighlights && highlights.length" class="ext-compass-app__highlights">
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
				></highlight-card>
			</div>
		</section>

		<section class="ext-compass-app__results">
			<h2 class="ext-compass-app__heading">
				{{ $i18n( 'compass-results-heading' ).text() }}
			</h2>

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
					<a class="ext-compass-app__name" :href="row.wiki.url">{{ row.wiki.sitename }}</a>
					<p v-if="row.wiki.description" class="ext-compass-app__description">
						{{ row.wiki.description }}
					</p>
					<cdx-accordion
						v-if="row.wiki.extendeddescription"
						class="ext-compass-app__more"
						separation="none"
						heading-level="h3"
					>
						<template #title>
							{{ $i18n( 'compass-card-more' ).text() }}
						</template>
						<p>{{ row.wiki.extendeddescription }}</p>
					</cdx-accordion>
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
					<cdx-info-chip v-if="row.wiki.private" status="subtle">
						{{ $i18n( 'compass-label-private' ).text() }}
					</cdx-info-chip>
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
	</div>
</template>

<script>
const { defineComponent } = require( 'vue' );
const {
	CdxAccordion,
	CdxButton,
	CdxField,
	CdxInfoChip,
	CdxMessage,
	CdxProgressBar,
	CdxSearchInput,
	CdxSelect,
	CdxTable
} = require( '@wikimedia/codex' );
const HighlightCard = require( './HighlightCard.vue' );

const ANY = '*';
const SEARCH_DELAY = 300;

module.exports = exports = defineComponent( {
	name: 'CompassApp',
	components: {
		CdxAccordion,
		CdxButton,
		CdxField,
		CdxInfoChip,
		CdxMessage,
		CdxProgressBar,
		CdxSearchInput,
		CdxSelect,
		CdxTable,
		HighlightCard
	},

	data() {
		const config = mw.config.get( 'wgCompassConfig' );
		const params = new URLSearchParams( window.location.search );

		return {
			config: config,
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
			error: false
		};
	},

	computed: {
		columns() {
			return [
				{ id: 'sitename', label: this.$i18n( 'compass-table-wiki' ).text(), minWidth: '20em' },
				{ id: 'language', label: this.$i18n( 'compass-table-language' ).text() },
				{ id: 'category', label: this.$i18n( 'compass-table-category' ).text() },
				{ id: 'state', label: this.$i18n( 'compass-table-state' ).text() },
				{ id: 'created', label: this.$i18n( 'compass-table-established' ).text() }
			];
		},

		rows() {
			return this.wikis.map( ( wiki ) => ( {
				sitename: wiki.sitename,
				language: wiki.language,
				category: wiki.category,
				state: wiki.state,
				created: wiki.createdformatted,
				wiki: wiki
			} ) );
		},

		languageItems() {
			return [ { label: this.$i18n( 'compass-label-any' ).text(), value: ANY } ].concat(
				this.config.languages.map( ( language ) => ( {
					label: language.name,
					value: language.code
				} ) )
			);
		},

		categoryItems() {
			return [ { label: this.$i18n( 'compass-label-any' ).text(), value: ANY } ].concat(
				this.config.categories.map( ( category ) => ( {
					label: category.label,
					value: category.value
				} ) )
			);
		},

		stateItems() {
			return [ { label: this.$i18n( 'compass-label-any' ).text(), value: ANY } ].concat(
				this.config.states.map( ( state ) => ( {
					label: this.$i18n( 'compass-label-' + state ).text(),
					value: state
				} ) )
			);
		},

		visibilityItems() {
			return [
				{ label: this.$i18n( 'compass-label-any' ).text(), value: ANY },
				{ label: this.$i18n( 'compass-label-public' ).text(), value: 'public' },
				{ label: this.$i18n( 'compass-label-private' ).text(), value: 'private' }
			];
		},

		sortItems() {
			return [ 'name', 'newest', 'oldest' ].map( ( sort ) => ( {
				label: this.$i18n( 'compass-sort-' + sort ).text(),
				value: sort
			} ) );
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

		updateUrl() {
			const params = new URLSearchParams();
			Object.keys( this.filters ).forEach( ( key ) => {
				const value = this.filters[ key ];
				if ( value && value !== ANY && !( key === 'sort' && value === 'name' ) ) {
					params.set( key, value );
				}
			} );

			if ( this.offset > 0 ) {
				params.set( 'offset', String( this.offset ) );
			}

			const query = params.toString();
			window.history.replaceState( null, '',
				window.location.pathname + ( query ? '?' + query : '' )
			);
		},

		load() {
			this.loading = true;
			this.error = false;
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
				this.error = true;
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

	&__toolbar {
		display: flex;
		flex-direction: column;
		gap: @spacing-100;
	}

	&__search {
		max-width: 32em;
	}

	&__filters {
		display: grid;
		grid-template-columns: repeat( auto-fit, minmax( 12em, 1fr ) );
		gap: @spacing-100;
		align-items: end;
	}

	&__reset {
		align-self: flex-start;
	}

	&__cards {
		display: grid;
		grid-template-columns: repeat( auto-fill, minmax( 18em, 1fr ) );
		gap: @spacing-100;
		align-items: stretch;
	}

	&__progress {
		margin-bottom: @spacing-50;
	}

	&__name {
		font-weight: @font-weight-bold;
	}

	&__description {
		margin: @spacing-25 0 0;
		color: @color-subtle;
	}

	&__more {
		margin-top: @spacing-25;
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
</style>
