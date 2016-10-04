<?php
	/**
	 * @package     Freemius for EDD Add-On
	 * @copyright   Copyright (c) 2015, Freemius, Inc.
	 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
	 * @since       1.0.0
	 */
?>
<div class="<?php
	switch ( $VARS['type'] ) {
		case 'error':
			echo 'error form-invalid';
			break;
		case 'promotion':
			echo 'updated promotion';
			break;
		case 'update':
//			echo 'update-nag update';
//			break;
		case 'success':
		default:
			echo 'updated success';
			break;
	}
?> fs-notice<?php if ( ! empty( $VARS['title'] ) ) {
	echo ' fs-has-title';
} ?>"><p>
		<?php if ( ! empty( $VARS['title'] ) ) : ?><b><?php echo $VARS['title'] ?></b> <?php endif ?>
		<?php echo $VARS['message'] ?>
	</p>
</div>
