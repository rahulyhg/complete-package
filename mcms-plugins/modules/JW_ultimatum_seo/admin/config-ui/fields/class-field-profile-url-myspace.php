<?php
/**
 * @package MCMSSEO\Admin\ConfigurationUI
 */

/**
 * Class MCMSSEO_Config_Field_Profile_URL_MySpace
 */
class MCMSSEO_Config_Field_Profile_URL_MySpace extends MCMSSEO_Config_Field {

	/**
	 * MCMSSEO_Config_Field_Profile_URL_MySpace constructor.
	 */
	public function __construct() {
		parent::__construct( 'profileUrlMySpace', 'Input' );

		$this->set_property( 'label', __( 'MySpace URL', 'mandarincms-seo' ) );
		$this->set_property( 'pattern', '^https:\/\/myspace\.com\/([^/]+)\/$' );
	}

	/**
	 * Set adapter
	 *
	 * @param MCMSSEO_Configuration_Options_Adapter $adapter Adapter to register lookup on.
	 */
	public function set_adapter( MCMSSEO_Configuration_Options_Adapter $adapter ) {
		$adapter->add_ultimatum_lookup( $this->get_identifier(), 'mcmsseo_social', 'myspace_url' );
	}
}