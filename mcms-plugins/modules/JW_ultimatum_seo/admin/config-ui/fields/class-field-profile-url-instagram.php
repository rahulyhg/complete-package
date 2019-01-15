<?php
/**
 * @package MCMSSEO\Admin\ConfigurationUI
 */

/**
 * Class MCMSSEO_Config_Field_Profile_URL_Instagram
 */
class MCMSSEO_Config_Field_Profile_URL_Instagram extends MCMSSEO_Config_Field {

	/**
	 * MCMSSEO_Config_Field_Profile_URL_Instagram constructor.
	 */
	public function __construct() {
		parent::__construct( 'profileUrlInstagram', 'Input' );

		$this->set_property( 'label', __( 'Instagram URL', 'mandarincms-seo' ) );
		$this->set_property( 'pattern', '^https:\/\/www\.instagram\.com\/([^/]+)\/$' );
	}

	/**
	 * Set adapter
	 *
	 * @param MCMSSEO_Configuration_Options_Adapter $adapter Adapter to register lookup on.
	 */
	public function set_adapter( MCMSSEO_Configuration_Options_Adapter $adapter ) {
		$adapter->add_ultimatum_lookup( $this->get_identifier(), 'mcmsseo_social', 'instagram_url' );
	}
}