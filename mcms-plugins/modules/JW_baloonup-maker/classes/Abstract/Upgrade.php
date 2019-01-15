<?php
/*******************************************************************************
 * Copyright (c) 2018, MCMS BaloonUp Maker
 ******************************************************************************/

if ( ! defined( 'BASED_TREE_URI' ) ) {
	exit;
}

/**
 * Implements a basic upgrade process.
 *
 * Handles marking complete and resume management.
 *
 * @since 1.7.0
 */
abstract class PUM_Abstract_Upgrade extends PUM_Abstract_Batch_Process {

	/**
	 * Store the current upgrade args in case we need to redo somehting
	 *
	 * @param int $step
	 */
	public function __construct( $step = 1 ) {
		update_option( 'pum_doing_upgrade', array(
			'upgrade_id' => $this->batch_id,
			'step'       => $step,
		) );

		parent::__construct( $step );
	}


	/**
	 * Defines logic to execute once batch processing is complete.
	 */
	public function finish() {
		/**
		 * Clear the doing upgrade flag to prevent issues later.
		 */
		delete_option( 'pum_doing_upgrade' );

		parent::finish();
	}


}