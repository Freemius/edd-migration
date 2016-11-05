<?php
	/**
	 * @package     Freemius for EDD Add-On
	 * @copyright   Copyright (c) 2015, Freemius, Inc.
	 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
	 * @since       1.0.0
	 */

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	/**
	 * @var FS_Migration_Endpoint_Abstract $endpoint
	 */
	$endpoint = $VARS['endpoint'];

	$developer    = $endpoint->get_developer();
	$is_connected = $endpoint->is_connected();

	wp_enqueue_style( WP_FSM__SLUG . '/settings',
		plugins_url( plugin_basename( WP_FSM__DIR_CSS . '/' . trim( 'admin/settings.css', '/' ) ) ) );

?>
<div class="wrap">
	<h2><?php printf( __fs( 'freemius-x-settings' ), WP_FS__NAMESPACE_EDD ) ?></h2>

	<?php if ( $is_connected ) : ?>
		<table class="form-table">
			<tbody>
			<tr>
				<th><h3><?php _efs( 'all-products' ) ?></h3></th>
				<td>
					<hr>
				</td>
			</tr>
			</tbody>
		</table>
		<table id="fs_modules" class="widefat">
			<thead>
			<tr>
				<th style="width: 1px"></th>
				<th><?php _efs( 'Name' ) ?></th>
				<th><?php _efs( 'Slug' ) ?></th>
				<th><?php _efs( 'Local ID' ) ?></th>
				<th><?php _efs( 'FS ID' ) ?></th>
				<th><?php _efs( 'FS Plan ID' ) ?></th>
				<th></th>
			</tr>
			</thead>
			<tbody>
			<?php
				$local_modules            = $endpoint->get_all_local_modules_for_settings();
				$synced_local_modules     = array();
				$not_synced_local_modules = array();

				foreach ( $local_modules as $local_module ) {
					$module_id = $endpoint->get_remote_module_id( $local_module->id );

					if ( false !== $module_id ) {
						$synced_local_modules[] = $local_module;
					} else {
						$not_synced_local_modules[] = $local_module;
					}
				}

				$local_modules = array_merge($synced_local_modules, $not_synced_local_modules);
			?>

			<?php foreach ( $local_modules as $local_module ) : ?>
				<?php $module_id = $endpoint->get_remote_module_id( $local_module->id ) ?>
				<?php $is_synced = is_numeric( $module_id ) ?>
				<tr data-local-module-id="<?php echo $local_module->id ?>"
				    class="<?php echo $is_synced ? 'fs--synced' : '' ?>">
					<td><i class="dashicons dashicons-yes"></i></td>
					<td><?php echo $local_module->title ?></td>
					<td><?php echo $local_module->slug ?></td>
					<td><?php echo $local_module->id ?></td>
					<?php if ( $is_synced ) : ?>
						<td class="fs--module-id"><?php echo $module_id ?></td>
						<td class="fs--paid-plan-id"><?php
								$remote_plan_id = $endpoint->get_remote_paid_plan_id( $local_module->id );
								echo ( false !== $remote_plan_id ) ? $remote_plan_id : '';
							?></td>
						<td>
							<button class="button"><?php _efs( 'Resync' ) ?></button>
						</td>
					<?php else : ?>
						<td class="fs--module-id"></td>
						<td class="fs--paid-plan-id"></td>
						<td style="text-align: right">
							<button class="button button-primary"><?php _efs( 'Sync to Freemius' ) ?></button>
						</td>
					<?php endif ?>
				</tr>
			<?php endforeach ?>
			</tbody>
		</table>

		<br>

		<button id="clear_mapping" class="button"><?php _efs( 'Clear Mapping Data' ) ?></button>
	<?php endif ?>
	<?php if ( ! $is_connected ) : ?>
		<p><?php printf(
				__fs( 'api-instructions' ),
				sprintf( '<a target="_blank" href="%s">%s</a>',
					'https://dashboard.freemius.com',
					__fs( 'login-to-fs' )
				)
			) ?></p>
	<?php endif ?>
	<form method="post" action="">
		<input type="hidden" name="fs_action" value="save_settings">
		<?php wp_nonce_field( 'save_settings' ) ?>
		<table id="fs_api_settings" class="form-table">
			<tbody>
			<tr>
				<th><h3><?php _efs( 'api-settings' ) ?></h3></th>
				<td>
					<hr>
				</td>
			</tr>
			<tr>
				<th><?php _efs( 'id' ) ?></th>
				<td><input id="fs_id" name="fs_id" type="number"
				           value="<?php echo $developer->id ?>"<?php if ( $is_connected ) {
						echo ' readonly';
					} ?>/></td>
			</tr>
			<tr>
				<th><?php _efs( 'public-key' ) ?></th>
				<td><input name="fs_public_key" type="text" value="<?php echo $developer->public_key ?>"
				           placeholder="pk_<?php echo str_pad( '', 29 * 6, '&bull;' ) ?>" maxlength="32"
				           style="width: 320px"<?php if ( $is_connected ) {
						echo ' readonly';
					} ?>/></td>
			</tr>
			<tr>
				<th><?php _efs( 'secret-key' ) ?></th>
				<td><input name="fs_secret_key" type="text" value="<?php echo $developer->secret_key ?>"
				           placeholder="sk_<?php echo str_pad( '', 29 * 6, '&bull;' ) ?>" maxlength="32"
				           style="width: 320px"<?php if ( $is_connected ) {
						echo ' readonly';
					} ?>/></td>
			</tr>
			</tbody>
		</table>
		<p class="submit"><input type="submit" name="submit" id="fs_submit" class="button<?php if ( ! $is_connected ) {
				echo ' button-primary';
			} ?>" value="<?php _efs( $is_connected ? 'edit-settings' : 'save-settings' ) ?>"/></p>
	</form>
</div>
<script>
	(function ($) {
		var inputs = $('#fs_api_settings input');

		inputs.on('keyup keypress', function () {
			var has_empty = false;
			for (var i = 0, len = inputs.length; i < len; i++) {
				if ('' === $(inputs[i]).val()) {
					has_empty = true;
					break;
				}
			}

			if (has_empty)
				$('#fs_submit').attr('disabled', 'disabled');
			else
				$('#fs_submit').removeAttr('disabled');
		});

		$(inputs[0]).keyup();

		$('#fs_submit').click(function () {
			if (!$(this).hasClass('button-primary')) {
				inputs.removeAttr('readonly');
				$(inputs[0]).focus().select();

				$(this)
					.addClass('button-primary')
					.val('<?php _efs('save-changes') ?>');

				return false;
			}

			return true;
		});

		$('#fs_modules button').click(function () {
			var $this = $(this),
				$container = $this.parents('tr'),
				$moduleID = $container.find('.fs--module-id'),
				$paidPlanID = $container.find('.fs--paid-plan-id');

			// Set button to loading mode.
			$this.html('<?php printf('%s...', __fs( 'Syncing' )) ?>');
			$this.attr('disabled', 'disabled');
			$this.removeClass('button-primary');

			// Remove synced class till synced again.
			$container.removeClass('fs--synced');
			$container.addClass('fs--syncing');

			// Clear remote IDs.
			$moduleID.html('');
			$paidPlanID.html('');

			$.post(ajaxurl, {
				action: 'fs_sync_module',
				local_module_id: $container.attr('data-local-module-id')
			}, function (result) {
				if (result.success) {
					$container.addClass('fs--synced');
					$container.removeClass('fs--syncing');
					$moduleID.html(result.data.module_id);
					$paidPlanID.html(result.data.plan_id);

					alert('<?php _e('W00t W00t! Module was successfully synced to Freemius. Refresh your Freemius Dashboard and you should be able to see all the data.', 'freemius') ?>');
				} else {
					alert('<?php _e('Oops... Something went wrong during the data sync, please try again in few min.', 'freemius' ) ?>');
				}

				// Recover button's label.
				$this.html('<?php _efs( 'Re-sync' ) ?>');
				$this.prop('disabled', false);
			});

			return false;
		});

		$('#clear_mapping').click(function () {
			if (confirm("<?php _e('Are you sure you\'d like to clear all mapping data?', 'freemius') ?>")) {
				$.post(ajaxurl, {
					action: 'fs_clear_mapping'
				}, function (result) {
					if (result.success) {
						$('.fs--synced').each(function () {
							var $this = $(this);
							$this.removeClass('fs--syncing fs--synced');
							$this.find('.fs--module-id').html('');
							$this.find('.fs--paid-plan-id').html('');
							$this.find('.button').removeClass('button-primary').html('<?php __fs( 'Sync to Freemius', 'freemius' ) ?>')
						});

						alert('<?php _e('All mapping data was successfully deleted.', 'freemius') ?>');
					} else {
						alert('<?php _e('Oops... Something went wrong, please try again in few min.', 'freemius' ) ?>');
					}
				});
			}
		});
	})
	(jQuery);
</script>
