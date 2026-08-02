<?php

namespace WikiOasis\Compass\HookHandlers;

use MediaWiki\Config\Config;
use MediaWiki\Context\IContextSource;
use Miraheze\ManageWiki\Helpers\Factories\ModuleFactory;
use Miraheze\ManageWiki\Hooks\ManageWikiCoreAddFormFieldsHook;
use Miraheze\ManageWiki\Hooks\ManageWikiCoreFormSubmissionHook;
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
			'section' => 'main',
		];
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

		if ( $this->config->get( 'CompassUseDescriptions' ) ) {
			$description = (string)( $formData['compass-description'] ?? '' );
			$extendedDescription = (string)( $formData['compass-extended-description'] ?? '' );

			$mwCore->setExtraFieldData( 'compass-description', $description, default: '' );
			$mwCore->setExtraFieldData(
				'compass-extended-description', $extendedDescription, default: ''
			);
		}

		$this->store->saveSettings( $dbname, $visible, $description, $extendedDescription );
	}
}
