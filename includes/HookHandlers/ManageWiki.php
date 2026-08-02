<?php

namespace WikiOasis\Compass\HookHandlers;

use MediaWiki\Config\Config;
use MediaWiki\Context\IContextSource;
use Miraheze\ManageWiki\Helpers\Factories\ModuleFactory;
use Miraheze\ManageWiki\Hooks\ManageWikiCoreAddFormFieldsHook;
use Miraheze\ManageWiki\Hooks\ManageWikiCoreFormSubmissionHook;
use WikiOasis\Compass\CompassListing;
use WikiOasis\Compass\Services\CompassStore;

class ManageWiki implements
	ManageWikiCoreAddFormFieldsHook,
	ManageWikiCoreFormSubmissionHook
{

	public function __construct(
		private readonly CompassStore $store,
		private readonly Config $config
	) {
	}

	/**
	 * @inheritDoc
	 * @param IContextSource $context @phan-unused-param
	 */
	public function onManageWikiCoreAddFormFields(
		IContextSource $context,
		ModuleFactory $moduleFactory,
		string $dbname,
		bool $ceMW,
		array &$formDescriptor
	): void {
		$mwCore = $moduleFactory->core( $dbname );

		$formDescriptor['compass-visible'] = [
			'label-message' => 'compass-label-visible',
			'help-message' => 'compass-label-visible-help',
			'type' => 'check',
			'default' => (bool)$mwCore->getExtraFieldData(
				'compass-visible',
				default: $this->config->get( 'CompassDefaultVisibility' )
			),
			'disabled' => !$ceMW,
			'section' => 'main',
		];

		if ( !$this->config->get( 'CompassUseDescriptions' ) ) {
			return;
		}

		// The remaining fields only matter for a wiki that is actually listed.
		$hideIfUnlisted = [ '!==', 'compass-visible', '1' ];

		$formDescriptor['compass-description'] = [
			'label-message' => 'compass-label-description',
			'help-message' => 'compass-label-description-help',
			'type' => 'text',
			'default' => $mwCore->getExtraFieldData(
				'compass-description',
				default: $mwCore->getExtraFieldData( 'description', default: '' )
			),
			'maxlength' => $this->config->get( 'CompassDescriptionsMaxLength' ),
			'disabled' => !$ceMW,
			'hide-if' => $hideIfUnlisted,
			'section' => 'main',
		];

		$formDescriptor['compass-extended-description'] = [
			'label-message' => 'compass-label-extended-description',
			'help-message' => 'compass-label-extended-description-help',
			'type' => 'textarea',
			'rows' => 6,
			'default' => $mwCore->getExtraFieldData( 'compass-extended-description', default: '' ),
			'maxlength' => $this->config->get( 'CompassExtendedDescriptionsMaxLength' ),
			'disabled' => !$ceMW,
			'hide-if' => $hideIfUnlisted,
			'section' => 'main',
		];

		$formDescriptor['compass-thumbnail'] = [
			'label-message' => 'compass-label-thumbnail',
			'help-message' => 'compass-label-thumbnail-help',
			'type' => 'url',
			'default' => $mwCore->getExtraFieldData( 'compass-thumbnail', default: '' ),
			'maxlength' => 512,
			'disabled' => !$ceMW,
			'hide-if' => $hideIfUnlisted,
			'section' => 'main',
			'validation-callback' => [ $this, 'validateThumbnail' ],
		];
	}

	/**
	 * @param ?string $value
	 * @param array $alldata @phan-unused-param
	 * @return bool|string|Message
	 */
	public function validateThumbnail( $value, array $alldata ) {
		if ( !$value || CompassListing::isValidThumbnail( (string)$value ) ) {
			return true;
		}

		return wfMessage( 'compass-error-thumbnail' );
	}

	/**
	 * @inheritDoc
	 * @param IContextSource $context @phan-unused-param
	 */
	public function onManageWikiCoreFormSubmission(
		IContextSource $context,
		ModuleFactory $moduleFactory,
		string $dbname,
		array $formData
	): void {
		if ( !isset( $formData['compass-visible'] ) ) {
			return;
		}

		$mwCore = $moduleFactory->core( $dbname );
		$visible = (bool)$formData['compass-visible'];
		$mwCore->setExtraFieldData(
			'compass-visible', $visible,
			default: $this->config->get( 'CompassDefaultVisibility' )
		);

		$description = null;
		$extendedDescription = null;
		$thumbnail = null;

		if ( $this->config->get( 'CompassUseDescriptions' ) ) {
			$description = (string)( $formData['compass-description'] ?? '' );
			$extendedDescription = (string)( $formData['compass-extended-description'] ?? '' );
			$thumbnail = (string)( $formData['compass-thumbnail'] ?? '' );

			if ( !CompassListing::isValidThumbnail( $thumbnail ) ) {
				$thumbnail = '';
			}

			$mwCore->setExtraFieldData( 'compass-description', $description, default: '' );
			$mwCore->setExtraFieldData(
				'compass-extended-description', $extendedDescription, default: ''
			);
			$mwCore->setExtraFieldData( 'compass-thumbnail', $thumbnail, default: '' );
		}

		$this->store->saveSettings(
			$dbname, $visible, $description, $extendedDescription, $thumbnail
		);
	}
}
