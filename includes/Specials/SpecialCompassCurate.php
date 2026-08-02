<?php

namespace WikiOasis\Compass\Specials;

use MediaWiki\Html\Html;
use MediaWiki\SpecialPage\SpecialPage;
use WikiOasis\Compass\CompassHtml;
use WikiOasis\Compass\Services\CompassStore;

class SpecialCompassCurate extends SpecialPage {

	public function __construct(
		private readonly CompassStore $store
	) {
		parent::__construct( 'CompassCurate', 'compass-curate' );
	}

	/**
	 * @param ?string $par @phan-unused-param
	 */
	public function execute( $par ): void {
		$this->setHeaders();
		$this->outputHeader();
		$this->checkPermissions();
		$this->checkReadOnly();

		$out = $this->getOutput();
		$out->addModuleStyles( [ 'ext.compass.styles' ] );

		$status = $this->getRequest()->wasPosted() ? $this->handleSubmission() : '';

		$out->addHTML( Html::rawElement( 'div', [ 'class' => 'ext-compass' ],
			CompassHtml::message( 'notice', $this->msg( 'compasscurate-intro' )->parse() ) .
			$status .
			$this->buildAddForm() .
			$this->buildHighlightForm()
		) );
	}

	private function handleSubmission(): string {
		$request = $this->getRequest();
		if ( !$this->getContext()->getCsrfTokenSet()->matchTokenField( 'wpEditToken' ) ) {
			return CompassHtml::message( 'error', $this->msg( 'sessionfailure' )->escaped() );
		}

		if ( $request->getCheck( 'wpAdd' ) ) {
			return $this->addHighlight( trim( $request->getText( 'wpDbname' ) ) );
		}

		if ( !$request->getCheck( 'wpSave' ) ) {
			return '';
		}

		$order = [];
		foreach ( (array)$request->getArray( 'wpOrder', [] ) as $dbname => $position ) {
			$order[(string)$dbname] = (int)$position;
		}

		$remove = array_map( 'strval',
			array_keys( (array)$request->getArray( 'wpRemove', [] ) )
		);

		$this->store->updateHighlights( $order, $remove );

		return CompassHtml::message( 'success',
			$this->msg( 'compasscurate-success-saved' )->escaped()
		);
	}

	private function addHighlight( string $dbname ): string {
		if ( $dbname === '' ) {
			return '';
		}

		if ( !$this->store->wikiExists( $dbname ) ) {
			return CompassHtml::message( 'error',
				$this->msg( 'compasscurate-error-nowiki', $dbname )->escaped()
			);
		}

		if ( $this->store->isHighlighted( $dbname ) ) {
			return CompassHtml::message( 'warning',
				$this->msg( 'compasscurate-error-already', $dbname )->escaped()
			);
		}

		$max = $this->store->getMaxHighlightedWikis();
		if ( $this->store->countHighlighted() >= $max ) {
			return CompassHtml::message( 'error',
				$this->msg( 'compasscurate-error-full' )->numParams( $max )->escaped()
			);
		}

		$this->store->addHighlight( $dbname );

		return CompassHtml::message( 'success',
			$this->msg( 'compasscurate-success-added', $dbname )->escaped()
		);
	}

	private function buildAddForm(): string {
		$field = CompassHtml::field( 'compass-dbname',
			$this->msg( 'compasscurate-add-label' )->text(),
			CompassHtml::textInput( [
				'id' => 'compass-dbname',
				'name' => 'wpDbname',
				'maxlength' => 64,
				'required' => true,
			] )
		);

		return Html::rawElement( 'form', [
			'class' => 'ext-compass-curate__add',
			'method' => 'post',
			'action' => $this->getPageTitle()->getLocalURL(),
		],
			Html::element( 'h2', [ 'class' => 'ext-compass-heading' ],
				$this->msg( 'compasscurate-add-heading' )->text()
			) .
			$field .
			Html::rawElement( 'p', [ 'class' => 'ext-compass-curate__help' ],
				$this->msg( 'compasscurate-add-help' )->parse()
			) .
			Html::rawElement( 'div', [ 'class' => 'ext-compass-curate__actions' ],
				CompassHtml::submitButton( $this->msg( 'compasscurate-add-button' )->text() )
			) .
			Html::hidden( 'wpAdd', '1' ) .
			Html::hidden( 'wpEditToken', $this->getContext()->getCsrfTokenSet()->getToken()->toString() )
		);
	}

	private function buildHighlightForm(): string {
		$rows = '';
		foreach ( $this->store->getCurationRows() as $row ) {
			$rows .= $this->buildRow( $row->cpw_dbname,
				$row->wiki_sitename ?? $row->cpw_dbname,
				(int)$row->cpw_highlight_order,
				!( $row->cpw_visible ?? 1 )
			);
		}

		$heading = Html::element( 'h2', [ 'class' => 'ext-compass-heading' ],
			$this->msg( 'compasscurate-current-heading' )->text()
		);

		if ( $rows === '' ) {
			return Html::rawElement( 'section', [ 'class' => 'ext-compass-curate' ],
				$heading .
				CompassHtml::message( 'notice', $this->msg( 'compasscurate-empty' )->escaped() )
			);
		}

		$head = Html::rawElement( 'tr', [],
			Html::element( 'th', [], $this->msg( 'compasscurate-header-wiki' )->text() ) .
			Html::element( 'th', [], $this->msg( 'compasscurate-header-order' )->text() ) .
			Html::element( 'th', [], $this->msg( 'compasscurate-header-remove' )->text() )
		);

		$table = Html::rawElement( 'div', [ 'class' => 'cdx-table' ],
			Html::rawElement( 'div', [ 'class' => 'cdx-table__table-wrapper' ],
				Html::rawElement( 'table', [ 'class' => 'cdx-table__table' ],
					Html::rawElement( 'thead', [], $head ) .
					Html::rawElement( 'tbody', [], $rows )
				)
			)
		);

		return Html::rawElement( 'form', [
			'class' => 'ext-compass-curate',
			'method' => 'post',
			'action' => $this->getPageTitle()->getLocalURL(),
		],
			$heading .
			$table .
			Html::rawElement( 'div', [ 'class' => 'ext-compass-curate__actions' ],
				CompassHtml::submitButton( $this->msg( 'compasscurate-save' )->text() )
			) .
			Html::hidden( 'wpSave', '1' ) .
			Html::hidden( 'wpEditToken', $this->getContext()->getCsrfTokenSet()->getToken()->toString() )
		);
	}

	private function buildRow(
		string $dbname,
		string $sitename,
		int $order,
		bool $hidden
	): string {
		$name = Html::element( 'span', [ 'class' => 'ext-compass-curate__name' ], $sitename ) .
			Html::element( 'span', [ 'class' => 'ext-compass-curate__dbname' ], $dbname );

		if ( $hidden ) {
			$name .= CompassHtml::chip( $this->msg( 'compasscurate-hidden' )->text(), 'warning' );
		}

		$position = CompassHtml::textInput( [
			'id' => "compass-order-$dbname",
			'name' => "wpOrder[$dbname]",
			'type' => 'number',
			'value' => (string)$order,
			'min' => 0,
			'class' => 'cdx-text-input__input ext-compass-curate__order',
		] );

		$remove = CompassHtml::checkbox(
			"compass-remove-$dbname", "wpRemove[$dbname]", false,
			$this->msg( 'compasscurate-header-remove' )->text() . ": $sitename"
		);

		return Html::rawElement( 'tr', [],
			Html::rawElement( 'td', [], $name ) .
			Html::rawElement( 'td', [], $position ) .
			Html::rawElement( 'td', [], $remove )
		);
	}

	/** @inheritDoc */
	protected function getGroupName(): string {
		return 'wiki';
	}
}
