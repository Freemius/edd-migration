<?php
	/**
	 * @package     Freemius Migration
	 * @copyright   Copyright (c) 2016, Freemius, Inc.
	 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
	 * @since       1.0.0
	 */

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	global $fs_text;

	if ( ! isset( $fs_text ) ) {
		$fs_text = array();
	}

	global $fsm_text;

	$fsm_text = array(
		'freemius-x-settings'  => __( 'Freemius for %s Settings', 'freemius' ),
		'api-settings'         => __( 'API Settings', 'freemius' ),
		'id'                   => __( 'ID', 'freemius' ),
		'all-products'         => __( 'All Products', 'freemius' ),
		'secret-key'           => __( 'Secret Key', 'freemius' ),
		'public-key'           => __( 'Public Key', 'freemius' ),
		'secure-webhook'       => __( 'Secure Webhook', 'freemius' ),
		'endpoint'             => __( 'Endpoint', 'freemius' ),
		'regenerate'           => __( 'Re-Generate', 'freemius' ),
		'fetching-token'       => __( 'Fetching new token...', 'freemius' ),
		'save-changes'         => __( 'Save Changes', 'freemius' ),
		'edit-settings'        => __( 'Edit Settings', 'freemius' ),
		'congrats'             => _x( 'Congrats', 'as congratulations', 'freemius' ),
		'oops'                 => _x( 'Oops', 'exclamation', 'freemius' ),
		'congrats-x'           => _x( 'Congrats %s', 'as congratulations', 'freemius' ),
		'woot'                 => _x( 'W00t',
			'(especially in electronic communication) used to express elation, enthusiasm, or triumph.', 'freemius' ),
		'api-instructions'     => __( 'To obtain your developer\'s ID and keys, please %s, click on the right side menu and open "My Profile".',
			'freemius' ),
		'login-to-fs'          => __( 'login to
			Freemius', 'freemius' ),
		#region API Credentials

		'bad-credentials'      => __( 'Seems like one of the credentials is wrong. Please make sure you don\'t omit any characters from the keys.',
			'freemius' ),
		'credentials-validate' => __( 'Your store was successfully connected to Freemius.', 'freemius' ),

		#endregion API Credentials
	);