<?php

namespace WikiOasis\Compass\Specials;

use MediaWiki\Html\Html;
use MediaWiki\Languages\LanguageNameUtils;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Status\Status;
use Miraheze\CreateWiki\Services\CreateWikiValidator;
use stdClass;
use WikiOasis\Compass\CompassHtml;
use WikiOasis\Compass\CompassListing;
use WikiOasis\Compass\Services\CompassCurator;
use WikiOasis\Compass\Services\CompassStore;

/**
 * The wiki directory. Everything below the mount point is a no-JavaScript
 * fallback; the Codex app in ext.compass.app replaces it when it loads.
 */
class SpecialCompass extends SpecialPage {

	private const SEARCH_MAX_LENGTH = 128;

	public function __construct(
		private readonly CompassStore $store,
		private readonly CompassCurator $curator,
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
		$out->addModules( [ 'ext.compass.app' ] );

		$filters = $this->getFilters();
		$limit = max( 1, (int)$this->getConfig()->get( 'CompassWikisPerPage' ) );
		$offset = max( 0, $this->getRequest()->getInt( 'offset' ) );

		if ( $this->getRequest()->wasPosted() && $this->handleCuration( $filters, $offset ) ) {
			return;
		}

		$out->addJsConfigVars( 'wgCompassConfig', [
			'limit' => $limit,
			'usePrivateFilter' => $this->usePrivateFilter(),
			'states' => array_values( array_diff( $this->getStateOptions(), [ '*' ] ) ),
			'languages' => $this->getLanguageOptions(),
			'categories' => $this->getCategoryOptions(),
			'canCurate' => $this->getAuthority()->isAllowed( 'compass-curate' ),
			'maxHighlighted' => $this->store->getMaxHighlightedWikis(),
		] );

		$pinned = !$this->hasFilters( $filters );
		$fallback = Html::element( 'p', [ 'class' => 'ext-compass-intro' ],
			$this->msg( 'compass-header-info' )->text()
		) . $this->buildCurationNotice( $filters, $offset );

		if ( $pinned && $offset === 0 ) {
			$fallback .= $this->buildHighlights( $filters, $offset );
		}

		$fallback .= $this->buildResults( $filters, $limit, $offset, $pinned );

		$out->addHTML(
			Html::element( 'div', [ 'id' => 'ext-compass-app' ] ) .
			Html::rawElement( 'div',
				[ 'class' => 'ext-compass ext-compass-fallback' ], $fallback
			)
		);
	}

	private function canCurate(): bool {
		return $this->getAuthority()->isAllowed( 'compass-curate' );
	}

	/**
	 * @return bool Whether the request has been answered with a redirect
	 */
	private function handleCuration( array $filters, int $offset ): bool {
		$request = $this->getRequest();
		$action = $request->getVal( 'wpCurateAction' );
		$dbname = trim( $request->getText( 'wpDbname' ) );

		if ( !$this->canCurate() || !$action || $dbname === '' ) {
			return false;
		}

		if ( !$this->getContext()->getCsrfTokenSet()->matchTokenField( 'wpEditToken' ) ) {
			$this->getOutput()->addHTML( CompassHtml::message( 'error',
				$this->msg( 'sessionfailure' )->escaped()
			) );
			return false;
		}

		$status = match ( $action ) {
			'pin' => $this->curator->setHighlighted( $dbname, true ),
			'unpin' => $this->curator->setHighlighted( $dbname, false ),
			'remove' => $this->curator->removeListing( $dbname ),
			default => null,
		};

		if ( $status === null ) {
			return false;
		}

		if ( !$status->isGood() ) {
			$this->getOutput()->addHTML( CompassHtml::message( 'error',
				Status::wrap( $status )->getMessage()->escaped()
			) );
			return false;
		}

		$this->getOutput()->redirect( $this->getPageUrl( $filters, $offset ) );
		return true;
	}

	private function buildCurationNotice( array $filters, int $offset ): string {
		$dbname = trim( $this->getRequest()->getText( 'remove' ) );
		if ( !$this->canCurate() || $dbname === '' || !$this->store->wikiExists( $dbname ) ) {
			return '';
		}

		$return = $this->getPageUrl( $filters, $offset );
		return CompassHtml::message( 'warning',
			Html::element( 'strong', [], $this->msg( 'compass-delete-title' )->text() ) .
			Html::element( 'p', [], $this->msg( 'compass-delete-body', $dbname )->text() ) .
			Html::rawElement( 'form', [
				'class' => 'ext-compass-actions',
				'method' => 'post',
				'action' => $return,
			],
				Html::hidden( 'wpDbname', $dbname ) .
				Html::hidden( 'wpCurateAction', 'remove' ) .
				Html::hidden( 'wpEditToken', $this->getCsrfToken() ) .
				CompassHtml::submitButton( $this->msg( 'compass-delete-confirm' )->text() ) .
				CompassHtml::linkButton( $return, $this->msg( 'cancel' )->text() )
			)
		);
	}

	private function getCsrfToken(): string {
		return $this->getContext()->getCsrfTokenSet()->getToken()->toString();
	}

	private function buildActions( stdClass $row, array $filters, int $offset ): string {
		$dbname = $row->wiki_dbname;
		$highlighted = (bool)( $row->cpw_highlighted ?? false );
		$label = $highlighted ? 'compass-action-unpin' : 'compass-action-pin';

		$pin = Html::rawElement( 'form', [
			'method' => 'post',
			'action' => $this->getPageUrl( $filters, $offset ),
		],
			Html::hidden( 'wpDbname', $dbname ) .
			Html::hidden( 'wpCurateAction', $highlighted ? 'unpin' : 'pin' ) .
			Html::hidden( 'wpEditToken', $this->getCsrfToken() ) .
			CompassHtml::submitButton( $this->msg( $label )->text(), progressive: false )
		);

		$remove = CompassHtml::linkButton(
			$this->getPageUrl( $filters, $offset, [ 'remove' => $dbname ] ),
			$this->msg( 'compass-action-delete' )->text(),
			[ 'class' => [
				'cdx-button',
				'cdx-button--fake-button',
				'cdx-button--fake-button--enabled',
				'cdx-button--action-destructive',
			] ]
		);

		return Html::rawElement( 'div', [ 'class' => 'ext-compass-actions' ], $pin . $remove );
	}

	/**
	 * @return array[] Language codes paired with their name in the user's language
	 */
	private function getLanguageOptions(): array {
		$languages = [];
		foreach ( $this->store->getAvailableLanguages() as $code ) {
			$languages[] = [
				'code' => $code,
				'name' => $this->languageNameUtils->getLanguageName(
					$code, $this->getLanguage()->getCode()
				) ?: $code,
			];
		}

		usort( $languages,
			static fn ( array $a, array $b ): int => strcmp( $a['name'], $b['name'] )
		);

		return $languages;
	}

	/**
	 * @return array[] Category values paired with their configured label
	 */
	private function getCategoryOptions(): array {
		$labels = array_flip( $this->getConfig()->get( 'CreateWikiCategories' ) );
		$categories = [];

		foreach ( $this->store->getAvailableCategories() as $value ) {
			$categories[] = [
				'value' => $value,
				'label' => (string)( $labels[$value] ?? $value ),
			];
		}

		return $categories;
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

		$sort = $request->getText( 'sort', 'random' );
		if ( !in_array( $sort, [ 'name', 'newest', 'oldest', 'random' ], true ) ) {
			$sort = 'random';
		}

		return [
			'search' => mb_substr(
				trim( $request->getText( 'search' ) ), 0, self::SEARCH_MAX_LENGTH
			),
			'language' => $language,
			'category' => $category,
			'state' => $state,
			'visibility' => $visibility,
			'sort' => $sort,
		];
	}

	private function hasFilters( array $filters ): bool {
		foreach ( $filters as $key => $value ) {
			if ( $key !== 'sort' && $value !== '' && $value !== '*' ) {
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
				'type' => 'search',
				'value' => $filters['search'],
				'maxlength' => self::SEARCH_MAX_LENGTH,
				'placeholder' => $this->msg( 'compass-filter-search-placeholder' )->text(),
			] )
		);

		$languages = [ $this->msg( 'compass-label-any' )->text() => '*' ];
		foreach ( $this->getLanguageOptions() as $language ) {
			$languages[$language['name']] = $language['code'];
		}

		$fields .= CompassHtml::field( 'compass-language',
			$this->msg( 'compass-table-language' )->text(),
			CompassHtml::select( 'language', 'compass-language', $languages, $filters['language'] )
		);

		$categories = [ $this->msg( 'compass-label-any' )->text() => '*' ];
		foreach ( $this->getCategoryOptions() as $category ) {
			$categories[$category['label']] = $category['value'];
		}

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

		$sorts = [];
		foreach ( [ 'random', 'name', 'newest', 'oldest' ] as $sort ) {
			$sorts[$this->msg( "compass-sort-$sort" )->text()] = $sort;
		}

		$fields .= CompassHtml::field( 'compass-sort',
			$this->msg( 'compass-sort-label' )->text(),
			CompassHtml::select( 'sort', 'compass-sort', $sorts, $filters['sort'] )
		);

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
			'aria-label' => $this->msg( 'compass-header' )->text(),
		],
			Html::rawElement( 'div', [ 'class' => 'ext-compass-filters__grid' ], $fields ) .
			Html::rawElement( 'div', [ 'class' => 'ext-compass-filters__actions' ], $actions )
		);
	}

	private function buildHighlights( array $filters, int $offset ): string {
		$cards = '';
		foreach ( $this->store->getHighlightedWikis() as $row ) {
			$cards .= $this->buildCard( $row, $filters, $offset );
		}

		if ( $cards === '' ) {
			return '';
		}

		return Html::rawElement( 'section', [ 'class' => 'ext-compass-highlights' ],
			Html::element( 'h2', [ 'class' => 'ext-compass-heading' ],
				$this->msg( 'compass-highlights-heading' )->text()
			) .
			Html::rawElement( 'div', [ 'class' => 'ext-compass-cards' ], $cards )
		);
	}

	private function buildCard( stdClass $row, array $filters, int $offset ): string {
		$url = $row->wiki_url ?: $this->validator->getValidUrl( $row->wiki_dbname );
		$text = Html::rawElement( 'span', [ 'class' => 'cdx-card__text__title' ],
			Html::element( 'a', [ 'href' => $url ], $row->wiki_sitename )
		);

		$description = trim( (string)( $row->cpw_description ?? '' ) );
		if ( $description !== '' ) {
			$text .= Html::element( 'span',
				[ 'class' => 'cdx-card__text__description' ], $description
			);
		}

		$text .= Html::element( 'span', [ 'class' => 'cdx-card__text__supporting-text' ],
			$this->getLanguage()->commaList( array_filter( [
				$this->getCategoryLabel( $row->wiki_category ),
				$this->getLanguageName( $row->wiki_language ),
			] ) )
		);

		$card = Html::rawElement( 'span',
			[ 'class' => 'cdx-card ext-compass-highlight__card' ],
			$this->buildThumbnail( $row ) .
			Html::rawElement( 'span', [ 'class' => 'cdx-card__text' ], $text )
		);

		$extended = trim( (string)( $row->cpw_extended_description ?? '' ) );
		if ( $extended !== '' ) {
			$card .= Html::rawElement( 'div',
				[ 'class' => 'ext-compass-highlight__more' ],
				CompassHtml::accordion(
					$this->msg( 'compass-card-more' )->text(),
					Html::element( 'p', [], $extended )
				)
			);
		}

		if ( $this->canCurate() ) {
			$card .= $this->buildActions( $row, $filters, $offset );
		}

		return Html::rawElement( 'div', [ 'class' => 'ext-compass-highlight' ], $card );
	}

	private function buildThumbnail( stdClass $row ): string {
		$url = trim( (string)( $row->cpw_thumbnail ?? '' ) );
		if ( $url === '' || !CompassListing::isValidThumbnail( $url ) ) {
			return '';
		}

		return Html::rawElement( 'span',
			[ 'class' => 'cdx-thumbnail cdx-card__thumbnail' ],
			Html::element( 'img', [
				'class' => 'cdx-thumbnail__image ext-compass-thumbnail',
				'src' => $url,
				'alt' => '',
				'loading' => 'lazy',
			] )
		);
	}

	private function buildResults(
		array $filters,
		int $limit,
		int $offset,
		bool $excludeHighlighted
	): string {
		$total = $this->store->countWikis( $filters, $excludeHighlighted );
		$heading = Html::element( 'h2', [ 'class' => 'ext-compass-heading' ],
			$this->msg( 'compass-results-heading' )->text()
		);

		if ( $total === 0 ) {
			return Html::rawElement( 'section', [ 'class' => 'ext-compass-results' ],
				$heading .
				$this->buildFilterForm( $filters ) .
				CompassHtml::message( 'notice', $this->msg( 'compass-results-empty' )->escaped() )
			);
		}

		if ( $offset >= $total ) {
			$offset = intdiv( $total - 1, $limit ) * $limit;
		}

		$rows = '';
		$shown = 0;
		foreach ( $this->store->getWikis( $filters, $limit, $offset, $excludeHighlighted ) as $row ) {
			$rows .= $this->buildRow( $row, $filters, $offset );
			$shown++;
		}

		$count = $this->msg( 'compass-results-count' )
			->numParams( $offset + 1, $offset + $shown, $total )
			->escaped();

		return Html::rawElement( 'section', [ 'class' => 'ext-compass-results' ],
			$heading .
			$this->buildFilterForm( $filters ) .
			$this->buildTable( $rows ) .
			Html::rawElement( 'div', [ 'class' => 'ext-compass-pagination' ],
				Html::rawElement( 'span', [ 'class' => 'ext-compass-pagination__count' ], $count ) .
				$this->buildPagination( $filters, $limit, $offset, $total )
			)
		);
	}

	private function buildTable( string $rows ): string {
		$headers = '';
		$columns = [
			'compass-table-wiki',
			'compass-table-language',
			'compass-table-category',
			'compass-table-state',
			'compass-table-established',
		];

		if ( $this->canCurate() ) {
			$columns[] = 'compass-table-actions';
		}

		foreach ( $columns as $column ) {
			$headers .= Html::element( 'th', [ 'scope' => 'col' ], $this->msg( $column )->text() );
		}

		return Html::rawElement( 'div', [ 'class' => 'cdx-table' ],
			Html::rawElement( 'div', [ 'class' => 'cdx-table__table-wrapper' ],
				Html::rawElement( 'table', [ 'class' => 'cdx-table__table' ],
					Html::rawElement( 'thead', [], Html::rawElement( 'tr', [], $headers ) ) .
					Html::rawElement( 'tbody', [], $rows )
				)
			)
		);
	}

	private function buildRow( stdClass $row, array $filters, int $offset ): string {
		$name = $this->buildThumbnail( $row ) . Html::element( 'a', [
			'class' => 'ext-compass-table__name',
			'href' => $row->wiki_url ?: $this->validator->getValidUrl( $row->wiki_dbname ),
		], $row->wiki_sitename );

		$description = trim( (string)( $row->cpw_description ?? '' ) );
		if ( $description !== '' ) {
			$name .= Html::element( 'p',
				[ 'class' => 'ext-compass-table__description' ], $description
			);
		}

		$extended = trim( (string)( $row->cpw_extended_description ?? '' ) );
		if ( $extended !== '' ) {
			$name .= CompassHtml::accordion(
				$this->msg( 'compass-card-more' )->text(),
				Html::element( 'p', [], $extended )
			);
		}

		[ $state, $modifier ] = $this->getState( $row );
		$stateCell = CompassHtml::chip( $this->msg( $state )->text(), $modifier );

		if ( $this->getConfig()->get( 'CreateWikiUsePrivateWikis' ) && (bool)$row->wiki_private ) {
			$stateCell .= CompassHtml::chip( $this->msg( 'compass-label-private' )->text() );
		}

		$actions = $this->canCurate() ?
			Html::rawElement( 'td', [], $this->buildActions( $row, $filters, $offset ) ) : '';

		return Html::rawElement( 'tr', [],
			Html::rawElement( 'th', [ 'scope' => 'row' ], $name ) .
			Html::element( 'td', [], $this->getLanguageName( $row->wiki_language ) ) .
			Html::element( 'td', [], $this->getCategoryLabel( $row->wiki_category ) ) .
			Html::rawElement( 'td', [], $stateCell ) .
			Html::element( 'td', [],
				$this->getLanguage()->userDate( $row->wiki_creation, $this->getUser() )
			) .
			$actions
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

		return $links;
	}

	private function getPageUrl( array $filters, int $offset, array $extra = [] ): string {
		$query = array_filter( $filters,
			static fn ( string $value ): bool => $value !== '' && $value !== '*'
		);

		unset( $query['sort'] );
		if ( ( $filters['sort'] ?? 'random' ) !== 'random' ) {
			$query['sort'] = $filters['sort'];
		}

		if ( $offset > 0 ) {
			$query['offset'] = (string)$offset;
		}

		return $this->getPageTitle()->getLocalURL( $query + $extra );
	}

	private function getLanguageName( string $code ): string {
		return $this->languageNameUtils->getLanguageName(
			$code, $this->getLanguage()->getCode()
		) ?: $code;
	}

	private function getCategoryLabel( string $value ): string {
		$labels = array_flip( $this->getConfig()->get( 'CreateWikiCategories' ) );
		return (string)( $labels[$value] ?? $value );
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
