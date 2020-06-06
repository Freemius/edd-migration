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

    wp_enqueue_script( 'vuejs', plugins_url( plugin_basename( WP_FSM__DIR_JS . '/vendor/vue.min.js' ) ) );
    wp_enqueue_script( 'vue-resource', 'https://cdn.jsdelivr.net/npm/vue-resource@1.3.4' );
    wp_enqueue_script( 'vue-async-computed', 'https://unpkg.com/vue-async-computed@3.3.1' );
?>
<div class="wrap">
    <h2><?php printf( __fs( 'freemius-x-settings' ), WP_FS__NAMESPACE_EDD ) ?></h2>

    <?php if ( $is_connected ) : ?>
        <div id="fs_settings">
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
                    <th><?php _efs( 'FS Plan IDs' ) ?></th>
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

                    $local_modules = array_merge( $synced_local_modules, $not_synced_local_modules );
                ?>

                <?php foreach ( $local_modules as $local_module ) : ?>
                    <?php $module_id = $endpoint->get_remote_module_id( $local_module->id ) ?>
                    <?php $is_synced = is_numeric( $module_id ) ?>
                    <tr class="fs-module<?php echo $is_synced ? ' fs--synced' : '' ?>" data-local-module-id="<?php echo $local_module->id ?>">
                        <td><i class="dashicons dashicons-yes"></i></td>
                        <td><?php echo $local_module->title ?></td>
                        <td><?php echo $local_module->slug ?></td>
                        <td><?php echo $local_module->id ?></td>
                        <?php if ( $is_synced ) : ?>
                            <td class="fs--module-id"><?php echo $module_id ?></td>
                            <td class="fs--paid-plan-id"><?php
                                    $remote_plan_ids = $endpoint->get_remote_paid_plan_ids( $local_module->id );
                                    echo ( false !== $remote_plan_ids ) ? implode( ', ', $remote_plan_ids ) : '';
                                ?></td>
                        <?php else : ?>
                            <td class="fs--module-id"></td>
                            <td class="fs--paid-plan-id"></td>
                        <?php endif ?>
                        <td style="text-align: right">
                            <button class="button fs-auto-sync" v-on:click="autoSyncProduct(<?php echo $local_module->id ?>, $event)"><?php $is_synced ? fs_esc_html_echo_inline( 'Resync' ) : fs_esc_html_echo_inline( 'Auto Sync to Freemius' ) ?></button>
                            <button class="button button-primary" v-on:click="selectModule(<?php echo $local_module->id ?>, $event)"><?php fs_esc_html_echo_inline( 'Manual Mapping' ) ?></button>
                        </td>
                    </tr>
                <?php endforeach ?>
                <tr id="fs_manual_mapping" v-show="localModuleID" v-cloak>
                    <td></td>
                    <td colspan="5">
                        <div>
                            <!-- Product Selection -->
                            <select v-model="module" :disabled="loading.modules">
                                <option disabled selected value="">{{ loading.modules ?
                                    '<?php fs_esc_html_echo_inline( 'Loading products' ) ?>' :
                                    '<?php fs_esc_html_echo_inline( 'Select product' ) ?>' }}...
                                </option>
                                <option v-for="m in modules" v-bind:value="m">{{ m.title }} ({{ m.id }} - {{ m.slug }})
                                </option>
                            </select>
                            <!--/ Product Selection -->

                            <span v-show="loading.pricing">Loading pricing collection...</span>
                            <!-- Plan Selection -->
                            <!--/ Plan Selection -->
                        </div>

                        <!-- Pricing Mapping -->
                        <div v-show="module">
                            <table v-if="pricing && ! loading.pricing" style="width: 100%">
                                <tbody>
                                <tr v-for="p in pricing.local">
                                    <td style="text-align: right"><label style="white-space:nowrap;">{{ p.name }} - ${{ p.price }} ({{ p.licenses }}
                                            licenses):</label></td>
                                    <td style="text-align: left">
                                        <select v-model="p.remote" style="width: 100%">
                                            <option disabled selected value="">{{ loading.pricing ?
                                                '<?php fs_esc_html_echo_inline( 'Loading pricing' ) ?>' :
                                                '<?php fs_esc_html_echo_inline( 'Select pricing' ) ?>' }}...
                                            </option>
                                            <option v-for="rp in pricing.remote" v-bind:value="rp.id">
                                                {{ rp.plan_title + ' (' + rp.plan_id + ' - ' + rp.plan_name + ')' }}
                                                {{ rp.licenses == null ? 'Unlimited' : rp.licenses }}-Site plan for
                                                ${{
                                                    rp.annual_price ?
                                                        rp.annual_price + ' / year' :
                                                        rp.lifetime_price ?
                                                            rp.lifetime_price + ' one-time' :
                                                            rp.monthly_price + ' / month'
                                                }} (ID = {{ rp.id }})
                                            </option>
                                        </select>
                                    </td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                        <!--/ Pricing Mapping -->

                        <div>
                            <button class="button"
                                    v-on:click="unselectModule"><?php fs_esc_html_echo_inline( 'Cancel' ) ?></button>
                            <button class="button button-primary"
                                    v-on:click="saveMapping"><?php fs_esc_html_echo_inline( 'Save Mapping' ) ?></button>
                        </div>
                    </td>
                    <td></td>
                </tr>
                </tbody>
            </table>

            <br>

            <button v-on:click="clearAllMapping($event)" class="button"><?php _efs( 'Clear Mapping Data' ) ?></button>
        </div>
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
                    .val('<?php _efs( 'Save Changes' ) ?>');

                return false;
            }

            return true;
        });

        $(document).ready(function () {
            new Vue({
                el  : '#fs_settings',
                data: {
                    loading        : {
                        modules: false,
                        plans  : false,
                        pricing: false
                    },
                    localModuleID  : null,
                    module         : '',
//					modules: [],
                    plan           : '',
//				plans: [],
//					pricing: []
                    selectedPricing: []
                },

                mounted: function () {
                    var self = this,
                        dom  = jQuery(self.$el);
                },

                methods: {
                    selectModule   : function (localModuleID, event) {
                        this.localModuleID = localModuleID;

                        var $container = $(event.target).parents('tr');

                        $container.after($('#fs_manual_mapping'));
                    },
                    unselectModule : function () {
                        this.localModuleID = null;
                    },
                    autoSyncProduct: function (localModuleID, event) {
                        var $this       = $(event.target),
                            $container  = $this.parents('tr'),
                            $moduleID   = $container.find('.fs--module-id'),
                            $paidPlanID = $container.find('.fs--paid-plan-id');

                        // Set button to loading mode.
                        $this.html('<?php printf( '%s...', __fs( 'Syncing' ) ) ?>');
                        $this.attr('disabled', 'disabled');
                        $this.removeClass('button-primary');

                        // Remove synced class till synced again.
                        $container.removeClass('fs--synced');
                        $container.addClass('fs--syncing');

                        // Clear remote IDs.
                        $moduleID.html('');
                        $paidPlanID.html('');

                        $.post(ajaxurl, {
                            action  : '<?php echo $endpoint->get_ajax_action( 'sync_module' ) ?>',
                            security: '<?php echo $endpoint->get_ajax_security( 'sync_module' ) ?>',
                            local_module_id: $container.attr('data-local-module-id')
                        }, function (result) {
                            if (result.success) {
                                $container.addClass('fs--synced');
                                $moduleID.html(result.data.module_id);
                                $paidPlanID.html(result.data.plan_ids.join(', '));

                                alert('<?php _e( 'W00t W00t! Module was successfully synced to Freemius. Refresh your Freemius Dashboard and you should be able to see all the data.', 'freemius' ) ?>');
                            } else {
                                alert('<?php _e( 'Oops... Something went wrong during the data sync, please try again in few min.', 'freemius' ) ?>');
                            }

                            // Recover button's label.
                            $this.html('<?php _efs( 'Re-sync' ) ?>');
                            $this.prop('disabled', false);
                            $container.removeClass('fs--syncing');
                        });

                        return false;
                    },
                    saveMapping    : function () {
                        var self    = this,
                            pricing = self.pricing.local;

                        var map = {
                            module : {
                                local : self.localModuleID,
                                remote: self.module.id
                            },
                            pricing: []
                        };

                        for (var i = 0; i < pricing.length; i++) {
                            map.pricing.push({
                                local      : pricing[i].id,
                                remote     : pricing[i].remote.id,
                                remote_plan: pricing[i].remote.plan_id
                            });
                        }

                        wp.ajax.send({
                            data   : {
                                action  : '<?php echo $endpoint->get_ajax_action( 'store_mapping' ) ?>',
                                security: '<?php echo $endpoint->get_ajax_security( 'store_mapping' ) ?>',
                                map     : map
                            },
                            success: function (result) {
                                var $container  = $('#fs_modules tr[data-local-module-id=' + self.localModuleID + ']'),
                                    $moduleID   = $container.find('.fs--module-id'),
                                    $paidPlanID = $container.find('.fs--paid-plan-id');

                                $container.addClass('fs--synced');
                                $moduleID.html(result.module_id);
                                $paidPlanID.html(result.plan_ids.join(', '));

                                self.unselectModule();
                            }
                        });
                    },
                    clearAllMapping: function (event) {
                        if (confirm("<?php _e( 'Are you sure you\'d like to clear all mapping data?', 'freemius' ) ?>")) {
                            wp.ajax.send({
                                data   : {
                                    action  : '<?php echo $endpoint->get_ajax_action( 'clear_mapping' ) ?>',
                                    security: '<?php echo $endpoint->get_ajax_security( 'clear_mapping' ) ?>'
                                },
                                success: function () {
                                    $('.fs--synced').each(function () {
                                        var $this = $(this);
                                        $this.removeClass('fs--syncing fs--synced');
                                        $this.find('.fs--module-id').html('');
                                        $this.find('.fs--paid-plan-id').html('');
                                        $this.find('.button').removeClass('button-primary').html('<?php __fs( 'Sync to Freemius', 'freemius' ) ?>')
                                    });

                                    alert('<?php _e( 'All mapping data was successfully deleted.', 'freemius' ) ?>');
                                },
                                error  : function () {
                                    alert('<?php _e( 'Oops... Something went wrong, please try again in few min.', 'freemius' ) ?>');
                                }
                            });
                        }
                    }
                },

                asyncComputed: {
                    modules: {
                        lazy: true,
                        get : function () {
                            var self = this;

                            self.loading.modules = true;

                            return Vue.http.get(ajaxurl, {
                                params: {
                                    action: 'fs_fetch_modules'
                                    // _wpnonce: weDocs.nonce
                                }
                            }).then(function (result) {
                                self.loading.modules = false;

                                return result.body.data;
                            }, function (error) {
                                // handle error
                            });
                        }
                    },
                    pricing  : function () {
                        var self = this;

                        if (self.loading.modules||
                            !$.isPlainObject(self.module) ||
                            !$.isNumeric(self.module.id)
                        ) {
                            return [];
                        }

                        self.loading.pricing = true;
                        self.pricing         = '';

                        return Vue.http.get(ajaxurl, {
                            params: {
                                action         : 'fs_fetch_pricing',
                                local_module_id: self.localModuleID,
                                module_id      : self.module.id
                            }
                        }).then(function (result) {
                            self.loading.pricing = false;

                            return result.body.data;
                        }, function (error) {
                            // handle error
                        });
                    }
                }
            })
        });
    })(jQuery);
</script>
