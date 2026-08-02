<?php

namespace WikiOasis\Compass\Specials;

use MediaWiki\Html\Html;
use MediaWiki\Languages\LanguageNameUtils;
use MediaWiki\SpecialPage\SpecialPage;
use Miraheze\CreateWiki\Services\CreateWikiValidator;
use stdClass;
use WikiOasis\Compass\CompassHtml;
use WikiOasis\Compass\Services\CompassStore;

class SpecialCompass extends SpecialPage {

	private const SEARCH_MAX_LENGTH = 128;

	public function __construct(
		private readonly CompassStore $store,
		private readonly CreateWikiValidator $validator,
		private readonly LanguageNameUtils $languageNameUtils
	) {
		parent::__construct( 'Compass' );
	}

	/**
	 * @param ?string $par @phan-unused-param
	 */
	public function execute( $par ): void {
		$this->setHeaders();
		$this->outputHeader();

		$out = $this->getOutput();
		$out->addModuleStyles( [ 'ext.compass.styles' ] );

		$filters = $this->getFilters();
		$limit = max( 1, (int)$this->getConfig()->get( 'CompassWikisPerPage' ) );
		$offset = max( 0, $this->getRequest()->getInt( 'offset' ) );

		$html = CompassHtml::message( 'notice',
			$this->msg( 'compass-header-info' )->parse()
		);

		$html .= $this->buildFilterForm( $filters );

		// Pinned wikis are listed on their own, so they are kept out of the
		// paginated list whenever that section applies to the current view.
		$pinned = !$this->hasFilters( $filters );
		if ( $pinned && $offset === 0 ) {
			$html .= $this->buildHighlights();
		}

		$html .= $this->buildResults( $filters, $limit, $offset, $pinned );

		$out->addHTML( Html::rawElement( 'div', [ 'class' => 'ext-compass' ], $html ) );
	}

	private function getFilters(): array {
		$request = $this->getRequest();

		$language = $request->getText( 'language', '*' );
		if ( !$this->languageNameUtils->isSupportedLanguage( $language ) ) {
			$language = '*';
		}

		$category = $request->getText( 'category', '*' );
		if ( !in_array( $category, $this->getConfig()->get( 'CreateWikiCategories' ), true ) ) {
			$category = '*';
		}

		$state = $request->getText( 'state', '*' );
		if ( !in_array( $state, $this->getStateOptions(), true ) ) {
			$state = '*';
		}

		$visibility = $request->getText( 'visibility', '*' );
		if ( !in_array( $visibility, [ '*', 'public', 'private' ], true ) ) {
			$visibility = '*';
		}

		return [
			'search' => mb_substr(
				trim( $request->getText( 'search' ) ), 0, self::SEARCH_MAX_LENGTH
			),
			'language' => $language,
			'category' => $category,
			'state' => $state,
			'visibility' => $visibility,
		];
	}

	private function hasFilters( array $filters ): bool {
		foreach ( $filters as $value ) {
			if ( $value !== '' && $value !== '*' ) {
				return true;
			}
		}

		return false;
	}

	private function usePrivateFilter(): bool {
		return $this->getConfig()->get( 'CreateWikiUsePrivateWikis' ) &&
			$this->getConfig()->get( 'CompassListPrivateWikis' );
	}

	/**
	 * @return string[] The values, not the labels, of the state filter
	 */
	private function getStateOptions(): array {
		$states = [ '*', 'active', 'locked', 'deleted' ];

		if ( $this->getConfig()->get( 'CreateWikiUseClosedWikis' ) ) {
			$states[] = 'closed';
		}

		if ( $this->getConfig()->get( 'CreateWikiUseInactiveWikis' ) ) {
			$states[] = 'inactive';
		}

		return $states;
	}

	private function buildFilterForm( array $filters ): string {
		$fields = CompassHtml::field( 'compass-search',
			$this->msg( 'compass-filter-search' )->text(),
			CompassHtml::textInput( [
				'id' => 'compass-search',
				'name' => 'search',
				'value' => $filters['search'],
				'maxlength' => self::SEARCH_MAX_LENGTH,
				'placeholder' => $this->msg( 'compass-filter-search-placeholder' )->text(),
			] )
		);

		$languages = [ $this->msg( 'compass-label-any' )->text() => '*' ];
		$languageNames = $this->languageNameUtils->getLanguageNames(
			$this->getLanguage()->getCode()
		);

		foreach ( $languageNames as $code => $name ) {
			$languages["$name ($code)"] = $code;
		}

		$fields .= CompassHtml::field( 'compass-language',
			$this->msg( 'compass-table-language' )->text(),
			CompassHtml::select( 'language', 'compass-language', $languages, $filters['language'] )
		);

		$categories = [ $this->msg( 'compass-label-any' )->text() => '*' ] +
			$this->getConfig()->get( 'CreateWikiCategories' );

		$fields .= CompassHtml::field( 'compass-category',
			$this->msg( 'compass-table-category' )->text(),
			CompassHtml::select( 'category', 'compass-category', $categories, $filters['category'] )
		);

		$states = [];
		foreach ( $this->getStateOptions() as $state ) {
			$key = $state === '*' ? 'compass-label-any' : "compass-label-$state";
			$states[$this->msg( $key )->text()] = $state;
		}

		$fields .= CompassHtml::field( 'compass-state',
			$this->msg( 'compass-table-state' )->text(),
			CompassHtml::select( 'state', 'compass-state', $states, $filters['state'] )
		);

		if ( $this->usePrivateFilter() ) {
			$visibilities = [
				$this->msg( 'compass-label-any' )->text() => '*',
				$this->msg( 'compass-label-public' )->text() => 'public',
				$this->msg( 'compass-label-private' )->text() => 'private',
			];

			$fields .= CompassHtml::field( 'compass-visibility',
				$this->msg( 'compass-table-visibility' )->text(),
				CompassHtml::select(
					'visibility', 'compass-visibility', $visibilities, $filters['visibility']
				)
			);
		}

		$actions = CompassHtml::submitButton( $this->msg( 'search' )->text() );
		if ( $this->hasFilters( $filters ) ) {
			$actions .= CompassHtml::linkButton(
				$this->getPageTitle()->getLocalURL(),
				$this->msg( 'compass-button-reset' )->text()
			);
		}

		return Html::rawElement( 'form', [
			'class' => 'ext-compass-filters',
			'method' => 'get',
			'action' => $this->getPageTitle()->getLocalURL(),
		],
			Html::element( 'h2', [ 'class' => 'ext-compass-heading' ],
				$this->msg( 'compass-header' )->text()
			) .
			Html::rawElement( 'div', [ 'class' => 'ext-compass-filters__grid' ], $fields ) .
			Html::rawElement( 'div', [ 'class' => 'ext-compass-filters__actions' ], $actions )
		);
	}

	private function buildHighlights(): string {
		$cards = '';
		foreach ( $this->store->getHighlightedWikis() as $row ) {
			$cards .= $this->buildCard( $row, true );
		}

		if ( $cards === '' ) {
			return '';
		}

		return Html::rawElement( 'section', [ 'class' => 'ext-compass-highlights' ],
			Html::element( 'h2', [ 'class' => 'ext-compass-heading' ],
				$this->msg( 'compass-highlights-heading' )->text()
			) .
			Html::rawElement( 'div', [ 'class' => 'ext-compass-grid' ], $cards )
		);
	}

	private function buildResults(
		array $filters,
		int $limit,
		int $offset,
		bool $excludeHighlighted
	): string {
		$total = $this->store->countWikis( $filters, $excludeHighlighted );
		if ( $total === 0 ) {
			return Html::rawElement( 'section', [ 'class' => 'ext-compass-results' ],
				$this->buildResultsHeading() .
				CompassHtml::message( 'notice', $this->msg( 'compass-results-empty' )->parse() )
			);
		}

		if ( $offset >= $total ) {
			$offset = intdiv( $total - 1, $limit ) * $limit;
		}

		$cards = '';
		$shown = 0;
		foreach ( $this->store->getWikis( $filters, $limit, $offset, $excludeHighlighted ) as $row ) {
			$cards .= $this->buildCard( $row, false );
			$shown++;
		}

		$count = $this->msg( 'compass-results-count' )
			->numParams( $offset + 1, $offset + $shown, $total )
			->parse();

		return Html::rawElement( 'section', [ 'class' => 'ext-compass-results' ],
			$this->buildResultsHeading() .
			Html::rawElement( 'p', [ 'class' => 'ext-compass-results__count' ], $count ) .
			Html::rawElement( 'div', [ 'class' => 'ext-compass-grid' ], $cards ) .
			$this->buildPagination( $filters, $limit, $offset, $total )
		);
	}

	private function buildResultsHeading(): string {
		return Html::element( 'h2', [ 'class' => 'ext-compass-heading' ],
			$this->msg( 'compass-results-heading' )->text()
		);
	}

	private function buildPagination(
		array $filters,
		int $limit,
		int $offset,
		int $total
	): string {
		$links = '';

		if ( $offset > 0 ) {
			$links .= CompassHtml::linkButton(
				$this->getPageUrl( $filters, max( 0, $offset - $limit ) ),
				$this->msg( 'compass-pagination-previous' )->text(),
				[ 'rel' => 'prev' ]
			);
		}

		if ( $offset + $limit < $total ) {
			$links .= CompassHtml::linkButton(
				$this->getPageUrl( $filters, $offset + $limit ),
				$this->msg( 'compass-pagination-next' )->text(),
				[ 'rel' => 'next' ]
			);
		}

		if ( $links === '' ) {
			return '';
		}

		return Html::rawElement( 'nav', [ 'class' => 'ext-compass-pagination' ], $links );
	}

	private function getPageUrl( array $filters, int $offset ): string {
		$query = array_filter( $filters,
			static fn ( string $value ): bool => $value !== '' && $value !== '*'
		);

		if ( $offset > 0 ) {
			$query['offset'] = (string)$offset;
		}

		return $this->getPageTitle()->getLocalURL( $query );
	}

	private function buildCard( stdClass $row, bool $highlighted ): string {
		$url = $row->wiki_url ?: $this->validator->getValidUrl( $row->wiki_dbname );
		$useDescriptions = $this->getConfig()->get( 'CompassUseDescriptions' );

		$text = Html::rawElement( 'span', [ 'class' => 'cdx-card__text__title' ],
			Html::element( 'a', [ 'href' => $url ], $row->wiki_sitename )
		);

		$description = trim( (string)( $row->cpw_description ?? '' ) );
		if ( $useDescriptions && $description !== '' ) {
			$text .= Html::element( 'span',
				[ 'class' => 'cdx-card__text__description' ], $description
			);
		}

		$text .= Html::rawElement( 'span', [ 'class' => 'cdx-card__text__supporting-text' ],
			$this->buildChips( $row, $highlighted )
		);

		$card = Html::rawElement( 'span', [ 'class' => 'cdx-card__text' ], $text );

		$extended = trim( (string)( $row->cpw_extended_description ?? '' ) );
		if ( $useDescriptions && $extended !== '' ) {
			$card .= CompassHtml::accordion(
				$this->msg( 'compass-card-more' )->text(),
				Html::element( 'p', [], $extended )
			);
		}

		$classes = [ 'cdx-card', 'ext-compass-card' ];
		if ( $highlighted ) {
			$classes[] = 'ext-compass-card--highlighted';
		}

		return Html::rawElement( 'div', [ 'class' => $classes ], $card );
	}

	private function buildChips( stdClass $row, bool $highlighted ): string {
		$chips = '';
		if ( $highlighted ) {
			$chips .= CompassHtml::chip(
				$this->msg( 'compass-label-highlighted' )->text(), 'highlighted'
			);
		}

		[ $state, $modifier ] = $this->getState( $row );
		$chips .= CompassHtml::chip( $this->msg( $state )->text(), $modifier );

		$categories = array_flip( $this->getConfig()->get( 'CreateWikiCategories' ) );
		$chips .= CompassHtml::chip(
			(string)( $categories[$row->wiki_category] ?? $row->wiki_category )
		);

		$language = $this->languageNameUtils->getLanguageName(
			$row->wiki_language, $this->getLanguage()->getCode()
		);

		$chips .= CompassHtml::chip( $language ?: $row->wiki_language );

		if ( $this->getConfig()->get( 'CreateWikiUsePrivateWikis' ) && (bool)$row->wiki_private ) {
			$chips .= CompassHtml::chip( $this->msg( 'compass-label-private' )->text() );
		}

		$established = $this->msg( 'compass-card-established' )
			->params( $this->getLanguage()->userDate( $row->wiki_creation, $this->getUser() ) )
			->text();

		return $chips . Html::element( 'span',
			[ 'class' => 'ext-compass-card__established' ], $established
		);
	}

	/**
	 * @return array{0:string,1:string} The state message key and the chip modifier
	 */
	private function getState( stdClass $row ): array {
		return match ( true ) {
			(bool)$row->wiki_deleted => [ 'compass-label-deleted', 'error' ],
			(bool)$row->wiki_locked => [ 'compass-label-locked', 'error' ],
			(bool)$row->wiki_closed => [ 'compass-label-closed', 'warning' ],
			(bool)$row->wiki_inactive => [ 'compass-label-inactive', 'warning' ],
			default => [ 'compass-label-open', 'success' ],
		};
	}

	/** @inheritDoc */
	protected function getGroupName(): string {
		return 'wiki';
	}
}
