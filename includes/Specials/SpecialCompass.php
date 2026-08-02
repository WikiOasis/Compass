<?php

namespace WikiOasis\Compass\Specials;

use MediaWiki\Html\Html;
use MediaWiki\Languages\LanguageNameUtils;
use MediaWiki\SpecialPage\SpecialPage;
use WikiOasis\Compass\Services\CompassStore;

/**
 * The wiki directory. The page itself only carries the configuration the Codex
 * app in ext.compass.app needs and a placeholder of the same shape, which that
 * app replaces once it has loaded.
 */
class SpecialCompass extends SpecialPage {

	public function __construct(
		private readonly CompassStore $store,
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

		$limit = max( 1, (int)$this->getConfig()->get( 'CompassWikisPerPage' ) );
		$highlights = $this->countPlaceholderHighlights();

		$out->addJsConfigVars( 'wgCompassConfig', [
			'limit' => $limit,
			'highlightCount' => $highlights,
			'usePrivateFilter' => $this->usePrivateFilter(),
			'states' => array_values( array_diff( $this->getStateOptions(), [ '*' ] ) ),
			'languages' => $this->getLanguageOptions(),
			'categories' => $this->getCategoryOptions(),
			'canCurate' => $this->getAuthority()->isAllowed( 'compass-curate' ),
			'maxHighlighted' => $this->store->getMaxHighlightedWikis(),
		] );

		$out->addHTML(
			Html::rawElement( 'div', [ 'id' => 'ext-compass-app' ],
				$this->buildPlaceholder( $limit, $highlights )
			) .
			Html::rawElement( 'noscript', [],
				Html::element( 'p', [ 'class' => 'ext-compass-nojs' ],
					$this->msg( 'compass-nojs' )->text()
				)
			)
		);
	}

	/**
	 * A skeleton of the directory, in the shape the app renders it in. The app
	 * holds it up until its own first response, so the page is laid out once
	 * instead of moving twice: without it the directory drops a full page of
	 * results into an empty document and pushes everything below it down.
	 *
	 * LoadingSkeleton.vue is the same markup, and both share its styles.
	 */
	private function buildPlaceholder( int $limit, int $highlights ): string {
		$blocks = static fn ( string $class, int $count ): string => str_repeat(
			Html::element( 'div', [ 'class' => "ext-compass-skeleton__$class" ] ), $count
		);

		$content = Html::element( 'div', [ 'class' => 'ext-compass-skeleton__intro' ] );

		if ( $highlights > 0 ) {
			$content .= Html::element( 'div', [ 'class' => 'ext-compass-skeleton__heading' ] ) .
				Html::rawElement( 'div', [ 'class' => 'ext-compass-skeleton__cards' ],
					$blocks( 'card', $highlights )
				);
		}

		$content .= Html::element( 'div', [ 'class' => 'ext-compass-skeleton__heading' ] ) .
			Html::element( 'div', [ 'class' => 'ext-compass-skeleton__toolbar' ] ) .
			Html::rawElement( 'div', [ 'class' => 'ext-compass-skeleton__table' ],
				Html::element( 'div', [ 'class' => 'ext-compass-skeleton__head' ] ) .
				$blocks( 'row', $limit )
			) .
			Html::element( 'div', [ 'class' => 'ext-compass-skeleton__pagination' ] );

		return Html::rawElement( 'div', [
			'class' => 'ext-compass-skeleton',
			'role' => 'status',
		],
			Html::element( 'span', [ 'class' => 'ext-compass-skeleton__label' ],
				$this->msg( 'compass-loading' )->text()
			) . $content
		);
	}

	/**
	 * @return int How many highlight cards the app is going to draw, so the
	 *   placeholder can leave room for exactly those
	 */
	private function countPlaceholderHighlights(): int {
		$request = $this->getRequest();
		if ( $request->getInt( 'offset' ) > 0 ) {
			return 0;
		}

		// The app hides the highlights as soon as a filter is set, and it reads
		// its filters from the same query string.
		foreach ( [ 'search', 'language', 'category', 'state', 'visibility' ] as $filter ) {
			$value = $request->getText( $filter );
			if ( $value !== '' && $value !== '*' ) {
				return 0;
			}
		}

		return min( $this->store->countHighlighted(), $this->store->getMaxHighlightedWikis() );
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

	/** @inheritDoc */
	protected function getGroupName(): string {
		return 'wiki';
	}
}
