<?php

namespace WikiOasis\Compass;

use MediaWiki\Html\Html;

/**
 * Markup helpers for the CSS-only Codex components used by Compass.
 */
class CompassHtml {

	public static function field( string $id, string $label, string $control ): string {
		return Html::rawElement( 'div', [ 'class' => 'cdx-field' ],
			Html::rawElement( 'div', [ 'class' => 'cdx-label' ],
				Html::rawElement( 'label', [ 'class' => 'cdx-label__label', 'for' => $id ],
					Html::element( 'span', [ 'class' => 'cdx-label__label__text' ], $label )
				)
			) .
			Html::rawElement( 'div', [ 'class' => 'cdx-field__control' ], $control )
		);
	}

	/**
	 * @param array $options Map of option label to option value
	 */
	public static function select(
		string $name,
		string $id,
		array $options,
		string $selected
	): string {
		$html = '';
		foreach ( $options as $label => $value ) {
			$html .= Html::element( 'option', [
				'value' => $value,
				'selected' => (string)$value === $selected,
			], (string)$label );
		}

		return Html::rawElement( 'select', [
			'class' => 'cdx-select',
			'id' => $id,
			'name' => $name,
		], $html );
	}

	public static function textInput( array $attribs ): string {
		return Html::rawElement( 'div', [ 'class' => 'cdx-text-input' ],
			Html::element( 'input', $attribs + [
				'class' => 'cdx-text-input__input',
				'type' => 'text',
			] )
		);
	}

	public static function submitButton( string $label, bool $progressive = true ): string {
		$classes = [ 'cdx-button' ];
		if ( $progressive ) {
			$classes[] = 'cdx-button--action-progressive';
			$classes[] = 'cdx-button--weight-primary';
		}

		return Html::element( 'button', [
			'class' => $classes,
			'type' => 'submit',
		], $label );
	}

	public static function linkButton( string $href, string $label, array $attribs = [] ): string {
		return Html::element( 'a', $attribs + [
			'class' => [
				'cdx-button',
				'cdx-button--fake-button',
				'cdx-button--fake-button--enabled',
			],
			'href' => $href,
		], $label );
	}

	public static function message( string $type, string $html ): string {
		return Html::rawElement( 'div', [
			'class' => [ 'cdx-message', 'cdx-message--block', "cdx-message--$type" ],
		],
			Html::element( 'span', [ 'class' => 'cdx-message__icon' ] ) .
			Html::rawElement( 'div', [ 'class' => 'cdx-message__content' ], $html )
		);
	}

	public static function chip( string $text, string $modifier = '' ): string {
		$classes = [ 'cdx-info-chip', 'ext-compass-chip' ];
		if ( $modifier !== '' ) {
			$classes[] = "ext-compass-chip--$modifier";
		}

		return Html::rawElement( 'span', [ 'class' => $classes ],
			Html::element( 'span', [ 'class' => 'cdx-info-chip__text' ], $text )
		);
	}

	public static function accordion( string $summary, string $content ): string {
		return Html::rawElement( 'details', [ 'class' => 'cdx-accordion' ],
			Html::rawElement( 'summary', [],
				Html::element( 'span', [ 'class' => 'cdx-accordion__header' ], $summary )
			) .
			Html::rawElement( 'div', [ 'class' => 'cdx-accordion__content' ], $content )
		);
	}
}
