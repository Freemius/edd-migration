<?php
    /**
     * @package     Freemius
     * @copyright   Copyright (c) 2015, Freemius, Inc.
     * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
     * @since       1.0.3
     */

    if ( ! defined( 'ABSPATH' ) ) {
        exit;
    }

    global $fs_core_logger;

    $fs_core_logger = FS_Logger::get_logger( WP_FS__SLUG . '_core', WP_FS__DEBUG_SDK, WP_FS__ECHO_DEBUG_SDK );

    if ( ! function_exists( 'fs_dummy' ) ) {
        function fs_dummy() {
        }
    }

    if ( ! function_exists( 'starts_with' ) ) {
        function starts_with( $haystack, $needle ) {
            $length = strlen( $needle );

            return ( substr( $haystack, 0, $length ) === $needle );
        }
    }

    /* Url.
    --------------------------------------------------------------------------------------------*/
    if ( ! function_exists( 'fs_get_url_daily_cache_killer' ) ) {
        function fs_get_url_daily_cache_killer() {
            return date( '\YY\Mm\Dd' );
        }
    }

    /* Templates / Views.
    --------------------------------------------------------------------------------------------*/
    if ( ! function_exists( 'fs_get_template_path' ) ) {
        function fs_get_template_path( $path ) {
            return WP_FS__DIR_TEMPLATES . '/' . trim( $path, '/' );
        }

        function fs_include_template( $path, &$params = null ) {
            $VARS = &$params;
            include( fs_get_template_path( $path ) );
        }

        function fs_include_once_template( $path, &$params = null ) {
            $VARS = &$params;
            include_once( fs_get_template_path( $path ) );
        }

        function fs_require_template( $path, &$params = null ) {
            $VARS = &$params;
            require( fs_get_template_path( $path ) );
        }

        function fs_require_once_template( $path, &$params = null ) {
            $VARS = &$params;
            require_once( fs_get_template_path( $path ) );
        }

        function fs_get_template( $path, &$params = null ) {
            ob_start();

            $VARS = &$params;
            require_once( fs_get_template_path( $path ) );

            return ob_get_clean();
        }
    }

    /* Scripts and styles including.
    --------------------------------------------------------------------------------------------*/
    if ( ! function_exists( 'fs_enqueue_local_style' ) ) {
        function fs_enqueue_local_style( $handle, $path, $deps = array(), $ver = false, $media = 'all' ) {
            global $fs_core_logger;
            if ( $fs_core_logger->is_on() ) {
                $fs_core_logger->info( 'handle = ' . $handle . '; path = ' . $path . ';' );
                $fs_core_logger->info( 'plugin_basename = ' . plugins_url( WP_FS__DIR_CSS . trim( $path, '/' ) ) );
                $fs_core_logger->info( 'plugins_url = ' . plugins_url( plugin_basename( WP_FS__DIR_CSS . '/' . trim( $path, '/' ) ) ) );
            }

            wp_enqueue_style( $handle, plugins_url( plugin_basename( WP_FS__DIR_CSS . '/' . trim( $path, '/' ) ) ), $deps, $ver, $media );
        }
    }

    if ( ! function_exists( 'fs_enqueue_local_script' ) ) {
        function fs_enqueue_local_script( $handle, $path, $deps = array(), $ver = false, $in_footer = 'all' ) {
            global $fs_core_logger;
            if ( $fs_core_logger->is_on() ) {
                $fs_core_logger->info( 'handle = ' . $handle . '; path = ' . $path . ';' );
                $fs_core_logger->info( 'plugin_basename = ' . plugins_url( WP_FS__DIR_JS . trim( $path, '/' ) ) );
                $fs_core_logger->info( 'plugins_url = ' . plugins_url( plugin_basename( WP_FS__DIR_JS . '/' . trim( $path, '/' ) ) ) );
            }

            wp_enqueue_script( $handle, plugins_url( plugin_basename( WP_FS__DIR_JS . '/' . trim( $path, '/' ) ) ), $deps, $ver, $in_footer );
        }
    }

    if ( ! function_exists( 'fs_img_url' ) ) {
        function fs_img_url( $path, $img_dir = WP_FS__DIR_IMG ) {
            return plugins_url( plugin_basename( $img_dir . '/' . trim( $path, '/' ) ) );
        }
    }

    #--------------------------------------------------------------------------------
    #region Request handlers.
    #--------------------------------------------------------------------------------

    if ( ! function_exists( 'fs_request_get' ) ) {
        /**
         * @param string      $key
         * @param mixed       $def
         * @param string|bool $type Since 1.2.1.7 - when set to 'get' will look for the value passed via querystring, when
         *                          set to 'post' will look for the value passed via the POST request's body, otherwise,
         *                          will check if the parameter was passed in any of the two.
         *
         * @return mixed
         */
        function fs_request_get( $key, $def = false, $type = false ) {
            if ( is_string( $type ) ) {
                $type = strtolower( $type );
            }

            switch ( $type ) {
                case 'post':
                    $value = isset( $_POST[ $key ] ) ? $_POST[ $key ] : $def;
                    break;
                case 'get':
                    $value = isset( $_GET[ $key ] ) ? $_GET[ $key ] : $def;
                    break;
                default:
                    $value = isset( $_REQUEST[ $key ] ) ? $_REQUEST[ $key ] : $def;
                    break;
            }

            return $value;
        }
    }

    if ( ! function_exists( 'fs_request_has' ) ) {
        function fs_request_has( $key ) {
            return isset( $_REQUEST[ $key ] );
        }
    }

    if ( ! function_exists( 'fs_request_get_bool' ) ) {
        function fs_request_get_bool( $key, $def = false ) {
            if ( ! isset( $_REQUEST[ $key ] ) ) {
                return $def;
            }

            if ( 1 == $_REQUEST[ $key ] || 'true' === strtolower( $_REQUEST[ $key ] ) ) {
                return true;
            }

            if ( 0 == $_REQUEST[ $key ] || 'false' === strtolower( $_REQUEST[ $key ] ) ) {
                return false;
            }

            return $def;
        }
    }

    if ( ! function_exists( 'fs_request_is_post' ) ) {
        function fs_request_is_post() {
            return ( 'post' === strtolower( $_SERVER['REQUEST_METHOD'] ) );
        }
    }

    if ( ! function_exists( 'fs_request_is_get' ) ) {
        function fs_request_is_get() {
            return ( 'get' === strtolower( $_SERVER['REQUEST_METHOD'] ) );
        }
    }

    if ( ! function_exists( 'fs_get_action' ) ) {
        function fs_get_action( $action_key = 'action' ) {
            if ( ! empty( $_REQUEST[ $action_key ] ) ) {
                return strtolower( $_REQUEST[ $action_key ] );
            }

            if ( 'action' == $action_key ) {
                $action_key = 'fs_action';

                if ( ! empty( $_REQUEST[ $action_key ] ) ) {
                    return strtolower( $_REQUEST[ $action_key ] );
                }
            }

            return false;
        }
    }

    if ( ! function_exists( 'fs_request_is_action' ) ) {
        function fs_request_is_action( $action, $action_key = 'action' ) {
            return ( strtolower( $action ) === fs_get_action( $action_key ) );
        }
    }

    if ( ! function_exists( 'fs_request_is_action_secure' ) ) {
        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.0.0
         *
         * @since  1.2.1.5 Allow nonce verification.
         *
         * @param string $action
         * @param string $action_key
         * @param string $nonce_key
         *
         * @return bool
         */
        function fs_request_is_action_secure(
            $action,
            $action_key = 'action',
            $nonce_key = 'nonce'
        ) {
            if ( strtolower( $action ) !== fs_get_action( $action_key ) ) {
                return false;
            }

            $nonce = ! empty( $_REQUEST[ $nonce_key ] ) ?
                $_REQUEST[ $nonce_key ] :
                '';

            if ( empty( $nonce ) ||
                 ( false === wp_verify_nonce( $nonce, $action ) )
            ) {
                return false;
            }

            return true;
        }
    }

    #endregion

    /* Core UI.
    --------------------------------------------------------------------------------------------*/
    if ( ! function_exists( 'fs_ui_action_button' ) ) {
        /**
         * @param string      $slug
         * @param string      $page
         * @param string      $action
         * @param string      $title
         * @param array       $params
         * @param bool        $is_primary
         * @param string|bool $icon_class   Optional class for an icon (since 1.1.7).
         * @param string|bool $confirmation Optional confirmation message before submit (since 1.1.7).
         * @param string      $method       Since 1.1.7
         *
         * @uses fs_ui_get_action_button()
         */
        function fs_ui_action_button(
            $slug,
            $page,
            $action,
            $title,
            $params = array(),
            $is_primary = true,
            $icon_class = false,
            $confirmation = false,
            $method = 'GET'
        ) {
            echo fs_ui_get_action_button(
                $slug,
                $page,
                $action,
                $title,
                $params,
                $is_primary,
                $icon_class,
                $confirmation,
                $method
            );
        }
    }

    if ( ! function_exists( 'fs_ui_get_action_button' ) ) {
        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.1.7
         *
         * @param string      $slug
         * @param string      $page
         * @param string      $action
         * @param string      $title
         * @param array       $params
         * @param bool        $is_primary
         * @param string|bool $icon_class   Optional class for an icon.
         * @param string|bool $confirmation Optional confirmation message before submit.
         * @param string      $method
         *
         * @return string
         */
        function fs_ui_get_action_button(
            $slug,
            $page,
            $action,
            $title,
            $params = array(),
            $is_primary = true,
            $icon_class = false,
            $confirmation = false,
            $method = 'GET'
        ) {
            // Prepend icon (if set).
            $title = ( is_string( $icon_class ) ? '<i class="' . $icon_class . '"></i> ' : '' ) . $title;

            if ( is_string( $confirmation ) ) {
                return sprintf( '<form action="%s" method="%s"><input type="hidden" name="fs_action" value="%s">%s<a href="#" class="%s" onclick="if (confirm(\'%s\')) this.parentNode.submit(); return false;">%s</a></form>',
                    freemius( $slug )->_get_admin_page_url( $page, $params ),
                    $method,
                    $action,
                    wp_nonce_field( $action, '_wpnonce', true, false ),
                    'button' . ( $is_primary ? ' button-primary' : '' ),
                    $confirmation,
                    $title
                );
            } else if ( 'GET' !== strtoupper( $method ) ) {
                return sprintf( '<form action="%s" method="%s"><input type="hidden" name="fs_action" value="%s">%s<a href="#" class="%s" onclick="this.parentNode.submit(); return false;">%s</a></form>',
                    freemius( $slug )->_get_admin_page_url( $page, $params ),
                    $method,
                    $action,
                    wp_nonce_field( $action, '_wpnonce', true, false ),
                    'button' . ( $is_primary ? ' button-primary' : '' ),
                    $title
                );
            } else {
                return sprintf( '<a href="%s" class="%s">%s</a></form>',
                    wp_nonce_url( freemius( $slug )->_get_admin_page_url( $page, array_merge( $params, array( 'fs_action' => $action ) ) ), $action ),
                    'button' . ( $is_primary ? ' button-primary' : '' ),
                    $title
                );
            }
        }

        function fs_ui_action_link( $slug, $page, $action, $title, $params = array() ) {
            ?><a class=""
                 href="<?php echo wp_nonce_url( freemius( $slug )->_get_admin_page_url( $page, array_merge( $params, array( 'fs_action' => $action ) ) ), $action ) ?>"><?php echo $title ?></a><?php
        }
    }

    /*function fs_error_handler($errno, $errstr, $errfile, $errline)
    {
        if (false === strpos($errfile, 'freemius/'))
        {
            // @todo Dump Freemius errors to local log.
        }

//		switch ($errno) {
//			case E_USER_ERROR:
//				break;
//			case E_WARNING:
//			case E_USER_WARNING:
//				break;
//			case E_NOTICE:
//			case E_USER_NOTICE:
//				break;
//			default:
//				break;
//		}
    }

    set_error_handler('fs_error_handler');*/

    if ( ! function_exists( 'fs_nonce_url' ) ) {
        function fs_nonce_url( $actionurl, $action = - 1, $name = '_wpnonce' ) {
//		$actionurl = str_replace( '&amp;', '&', $actionurl );
            return add_query_arg( $name, wp_create_nonce( $action ), $actionurl );
        }
    }

    if ( ! function_exists( 'fs_starts_with' ) ) {
        /**
         * Check if string starts with.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.1.3
         *
         * @param string $haystack
         * @param string $needle
         *
         * @return bool
         */
        function fs_starts_with( $haystack, $needle ) {
            $length = strlen( $needle );

            return ( substr( $haystack, 0, $length ) === $needle );
        }
    }

    #region Url Canonization ------------------------------------------------------------------

    if ( ! function_exists( 'fs_canonize_url' ) ) {
        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.1.3
         *
         * @param string $url
         * @param bool   $omit_host
         * @param array  $ignore_params
         *
         * @return string
         */
        function fs_canonize_url( $url, $omit_host = false, $ignore_params = array() ) {
            $parsed_url = parse_url( strtolower( $url ) );

//		if ( ! isset( $parsed_url['host'] ) ) {
//			return $url;
//		}

            $canonical = ( ( $omit_host || ! isset( $parsed_url['host'] ) ) ? '' : $parsed_url['host'] ) . $parsed_url['path'];

            if ( isset( $parsed_url['query'] ) ) {
                parse_str( $parsed_url['query'], $queryString );
                $canonical .= '?' . fs_canonize_query_string( $queryString, $ignore_params );
            }

            return $canonical;
        }
    }

    if ( ! function_exists( 'fs_canonize_query_string' ) ) {
        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.1.3
         *
         * @param array $params
         * @param array $ignore_params
         * @param bool  $params_prefix
         *
         * @return string
         */
        function fs_canonize_query_string( array $params, array &$ignore_params, $params_prefix = false ) {
            if ( ! is_array( $params ) || 0 === count( $params ) ) {
                return '';
            }

            // Url encode both keys and values
            $keys   = fs_urlencode_rfc3986( array_keys( $params ) );
            $values = fs_urlencode_rfc3986( array_values( $params ) );
            $params = array_combine( $keys, $values );

            // Parameters are sorted by name, using lexicographical byte value ordering.
            // Ref: Spec: 9.1.1 (1)
            uksort( $params, 'strcmp' );

            $pairs = array();
            foreach ( $params as $parameter => $value ) {
                $lower_param = strtolower( $parameter );

                // Skip ignore params.
                if ( in_array( $lower_param, $ignore_params ) ||
                     ( false !== $params_prefix && starts_with( $lower_param, $params_prefix ) )
                ) {
                    continue;
                }

                if ( is_array( $value ) ) {
                    // If two or more parameters share the same name, they are sorted by their value
                    // Ref: Spec: 9.1.1 (1)
                    natsort( $value );
                    foreach ( $value as $duplicate_value ) {
                        $pairs[] = $lower_param . '=' . $duplicate_value;
                    }
                } else {
                    $pairs[] = $lower_param . '=' . $value;
                }
            }

            if ( 0 === count( $pairs ) ) {
                return '';
            }

            return implode( "&", $pairs );
        }
    }

    if ( ! function_exists( 'fs_urlencode_rfc3986' ) ) {
        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.1.3
         *
         * @param string|string[] $input
         *
         * @return array|mixed|string
         */
        function fs_urlencode_rfc3986( $input ) {
            if ( is_array( $input ) ) {
                return array_map( 'fs_urlencode_rfc3986', $input );
            } else if ( is_scalar( $input ) ) {
                return str_replace( '+', ' ', str_replace( '%7E', '~', rawurlencode( $input ) ) );
            }

            return '';
        }
    }

    #endregion Url Canonization ------------------------------------------------------------------

    if ( ! function_exists( 'fs_download_image' ) ) {
        function fs_download_image( $from, $to ) {
            $ch = curl_init( $from );
            $fp = fopen( fs_normalize_path( $to ), 'wb' );
            curl_setopt( $ch, CURLOPT_FILE, $fp );
            curl_setopt( $ch, CURLOPT_HEADER, 0 );
            curl_exec( $ch );
            curl_close( $ch );
            fclose( $fp );
        }
    }

    /* General Utilities
    --------------------------------------------------------------------------------------------*/

    if ( ! function_exists( 'fs_sort_by_priority' ) ) {
        /**
         * Sorts an array by the value of the priority key.
         *
         * @author Daniel Iser (@danieliser)
         * @since  1.1.7
         *
         * @param $a
         * @param $b
         *
         * @return int
         */
        function fs_sort_by_priority( $a, $b ) {

            // If b has a priority and a does not, b wins.
            if ( ! isset( $a['priority'] ) && isset( $b['priority'] ) ) {
                return 1;
            } // If b has a priority and a does not, b wins.
            elseif ( isset( $a['priority'] ) && ! isset( $b['priority'] ) ) {
                return - 1;
            } // If neither has a priority or both priorities are equal its a tie.
            elseif ( ( ! isset( $a['priority'] ) && ! isset( $b['priority'] ) ) || $a['priority'] === $b['priority'] ) {
                return 0;
            }

            // If both have priority return the winner.
            return ( $a['priority'] < $b['priority'] ) ? - 1 : 1;
        }
    }

    #--------------------------------------------------------------------------------
    #region Localization
    #--------------------------------------------------------------------------------

    if ( ! function_exists( 'fs_text' ) ) {
        /**
         * Retrieve a translated text by key.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.2.1.7
         *
         * @param string $key
         * @param string $slug
         *
         * @return string
         *
         * @global       $fs_text , $fs_text_overrides
         */
        function fs_text( $key, $slug = 'freemius' ) {
            return __fs( $key, $slug );
        }

        /**
         * Get a translatable text override if exists, or `false`.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.2.1.7
         *
         * @param string $text Translatable string.
         * @param string $key  String key for overrides.
         * @param string $slug Module slug for overrides.
         *
         * @return string|false
         */
        function fs_text_override( $text, $key, $slug ) {
            global $fs_text_overrides;

            /**
             * Check if string is overridden.
             */
            if ( ! isset( $fs_text_overrides[ $slug ] ) ) {
                return false;
            }

            if ( empty( $key ) ) {
                $key = strtolower( str_replace( ' ', '-', $text ) );
            }

            if ( isset( $fs_text_overrides[ $slug ][ $key ] ) ) {
                return $fs_text_overrides[ $slug ][ $key ];
            }

            $lower_key = strtolower( $key );
            if ( isset( $fs_text_overrides[ $slug ][ $lower_key ] ) ) {
                return $fs_text_overrides[ $slug ][ $lower_key ];
            }

            return false;
        }

        /**
         * Get a translatable text and its text domain.
         *
         * When the text is overridden by the module, returns the overridden text and the text domain of the module. Otherwise, returns the original text and 'freemius' as the text domain.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.2.1.7
         *
         * @param string $text Translatable string.
         * @param string $key  String key for overrides.
         * @param string $slug Module slug for overrides.
         *
         * @return string[]
         */
        function fs_text_and_domain( $text, $key, $slug ) {
            $override = fs_text_override( $text, $key, $slug );

            if ( false === $override ) {
                // No override, use FS text domain.
                $text_domain = 'freemius';
            } else {
                // Found an override.
                $text = $override;
                // Use the module's text domain.
                $text_domain = $slug;
            }

            return array( $text, $text_domain );
        }

        #region Private

        /**
         * Retrieve an inline translated text by key.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.2.3
         *
         * @param string $text Translatable string.
         * @param string $key  String key for overrides.
         * @param string $slug Module slug for overrides.
         *
         * @return string
         *
         * @global       $fs_text_overrides
         */
        function _fs_text_inline( $text, $key = '', $slug = 'freemius' ) {
            list( $text, $text_domain ) = fs_text_and_domain( $text, $key, $slug );

            // Avoid misleading Theme Check warning.
            $fn = 'translate';

            return $fn( $text, $text_domain );
        }

        /**
         * Retrieve an inline translated text by key with a context.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.2.3
         *
         * @param string $text    Translatable string.
         * @param string $context Context information for the translators.
         * @param string $key     String key for overrides.
         * @param string $slug    Module slug for overrides.
         *
         * @return string
         *
         * @global       $fs_text_overrides
         */
        function _fs_text_x_inline( $text, $context, $key = '', $slug = 'freemius' ) {
            list( $text, $text_domain ) = fs_text_and_domain( $text, $key, $slug );

            // Avoid misleading Theme Check warning.
            $fn = 'translate_with_gettext_context';

            return $fn( $text, $context, $text_domain );
        }

        #endregion

        /**
         * Retrieve an inline translated text by key.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.2.3
         *
         * @param string $text Translatable string.
         * @param string $key  String key for overrides.
         * @param string $slug Module slug for overrides.
         *
         * @return string
         *
         * @global       $fs_text_overrides
         */
        function fs_text_inline( $text, $key = '', $slug = 'freemius' ) {
            return _fs_text_inline( $text, $key, $slug );
        }

        /**
         * Retrieve an inline translated text by key with a context.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.2.3
         *
         * @param string $text    Translatable string.
         * @param string $context Context information for the translators.
         * @param string $key     String key for overrides.
         * @param string $slug    Module slug for overrides.
         *
         * @return string
         *
         * @global       $fs_text_overrides
         */
        function fs_text_x_inline( $text, $context, $key = '', $slug = 'freemius' ) {
            return _fs_text_x_inline( $text, $context, $key, $slug );
        }

        /**
         * Output a translated text by key.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.2.1.7
         *
         * @param string $key
         * @param string $slug
         */
        function fs_echo( $key, $slug = 'freemius' ) {
            echo fs_text( $key, $slug );
        }

        /**
         * Output an inline translated text.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.2.3
         *
         * @param string $text Translatable string.
         * @param string $key  String key for overrides.
         * @param string $slug Module slug for overrides.
         */
        function fs_echo_inline( $text, $key = '', $slug = 'freemius' ) {
            echo _fs_text_inline( $text, $key, $slug );
        }

        /**
         * Output an inline translated text with a context.
         *
         * @author Vova Feldman (@svovaf)
         * @since  1.2.3
         *
         * @param string $text    Translatable string.
         * @param string $context Context information for the translators.
         * @param string $key     String key for overrides.
         * @param string $slug    Module slug for overrides.
         */
        function fs_echo_x_inline( $text, $context, $key = '', $slug = 'freemius' ) {
            echo _fs_text_x_inline( $text, $context, $key, $slug );
        }
    }

    if ( ! function_exists( 'fs_esc_attr' ) ) {
        /**
         * @author Vova Feldman
         * @since  1.2.1.6
         *
         * @param string $key
         * @param string $slug
         *
         * @return string
         */
        function fs_esc_attr( $key, $slug ) {
            return esc_attr( fs_text( $key, $slug ) );
        }
    }

    if ( ! function_exists( 'fs_esc_attr_inline' ) ) {
        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.2.3
         *
         * @param string $text Translatable string.
         * @param string $key  String key for overrides.
         * @param string $slug Module slug for overrides.
         *
         * @return string
         */
        function fs_esc_attr_inline( $text, $key = '', $slug = 'freemius' ) {
            return esc_attr( _fs_text_inline( $text, $key, $slug ) );
        }
    }

    if ( ! function_exists( 'fs_esc_attr_x_inline' ) ) {
        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.2.3
         *
         * @param string $text    Translatable string.
         * @param string $context Context information for the translators.
         * @param string $key     String key for overrides.
         * @param string $slug    Module slug for overrides.
         *
         * @return string
         */
        function fs_esc_attr_x_inline( $text, $context, $key = '', $slug = 'freemius' ) {
            return esc_attr( _fs_text_x_inline( $text, $context, $key, $slug ) );
        }
    }

    if ( ! function_exists( 'fs_esc_attr_echo' ) ) {
        /**
         * @author Vova Feldman
         * @since  1.2.1.6
         *
         * @param string $key
         * @param string $slug
         */
        function fs_esc_attr_echo( $key, $slug ) {
            echo esc_attr( fs_text( $key, $slug ) );
        }
    }

    if ( ! function_exists( 'fs_esc_attr_echo_inline' ) ) {
        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.2.3
         *
         * @param string $text Translatable string.
         * @param string $key  String key for overrides.
         * @param string $slug Module slug for overrides.
         */
        function fs_esc_attr_echo_inline( $text, $key = '', $slug = 'freemius' ) {
            echo esc_attr( _fs_text_inline( $text, $key, $slug ) );
        }
    }

    if ( ! function_exists( 'fs_esc_js' ) ) {
        /**
         * @author Vova Feldman
         * @since  1.2.1.6
         *
         * @param string $key
         * @param string $slug
         *
         * @return string
         */
        function fs_esc_js( $key, $slug ) {
            return esc_js( fs_text( $key, $slug ) );
        }
    }

    if ( ! function_exists( 'fs_esc_js_inline' ) ) {
        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.2.3
         *
         * @param string $text Translatable string.
         * @param string $key  String key for overrides.
         * @param string $slug Module slug for overrides.
         *
         * @return string
         */
        function fs_esc_js_inline( $text, $key = '', $slug = 'freemius' ) {
            return esc_js( _fs_text_inline( $text, $key, $slug ) );
        }
    }

    if ( ! function_exists( 'fs_esc_js_x_inline' ) ) {
        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.2.3
         *
         * @param string $text    Translatable string.
         * @param string $context Context information for the translators.
         * @param string $key     String key for overrides.
         * @param string $slug    Module slug for overrides.
         *
         * @return string
         */
        function fs_esc_js_x_inline( $text, $context, $key = '', $slug = 'freemius' ) {
            return esc_js( _fs_text_x_inline( $text, $context, $key, $slug ) );
        }
    }

    if ( ! function_exists( 'fs_esc_js_echo_x_inline' ) ) {
        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.2.3
         *
         * @param string $text    Translatable string.
         * @param string $context Context information for the translators.
         * @param string $key     String key for overrides.
         * @param string $slug    Module slug for overrides.
         *
         * @return string
         */
        function fs_esc_js_echo_x_inline( $text, $context, $key = '', $slug = 'freemius' ) {
            echo esc_js( _fs_text_x_inline( $text, $context, $key, $slug ) );
        }
    }

    if ( ! function_exists( 'fs_esc_js_echo' ) ) {
        /**
         * @author Vova Feldman
         * @since  1.2.1.6
         *
         * @param string $key
         * @param string $slug
         */
        function fs_esc_js_echo( $key, $slug ) {
            echo esc_js( fs_text( $key, $slug ) );
        }
    }

    if ( ! function_exists( 'fs_esc_js_echo_inline' ) ) {
        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.2.3
         *
         * @param string $text Translatable string.
         * @param string $key  String key for overrides.
         * @param string $slug Module slug for overrides.
         */
        function fs_esc_js_echo_inline( $text, $key = '', $slug = 'freemius' ) {
            echo esc_js( _fs_text_inline( $text, $key, $slug ) );
        }
    }

    if ( ! function_exists( 'fs_json_encode_echo' ) ) {
        /**
         * @author Vova Feldman
         * @since  1.2.1.6
         *
         * @param string $key
         * @param string $slug
         */
        function fs_json_encode_echo( $key, $slug ) {
            echo json_encode( fs_text( $key, $slug ) );
        }
    }

    if ( ! function_exists( 'fs_json_encode_echo_inline' ) ) {
        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.2.3
         *
         * @param string $text Translatable string.
         * @param string $key  String key for overrides.
         * @param string $slug Module slug for overrides.
         */
        function fs_json_encode_echo_inline( $text, $key = '', $slug = 'freemius' ) {
            echo json_encode( _fs_text_inline( $text, $key, $slug ) );
        }
    }

    if ( ! function_exists( 'fs_esc_html' ) ) {
        /**
         * @author Vova Feldman
         * @since  1.2.1.6
         *
         * @param string $key
         * @param string $slug
         *
         * @return string
         */
        function fs_esc_html( $key, $slug ) {
            return esc_html( fs_text( $key, $slug ) );
        }
    }

    if ( ! function_exists( 'fs_esc_html_inline' ) ) {
        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.2.3
         *
         * @param string $text Translatable string.
         * @param string $key  String key for overrides.
         * @param string $slug Module slug for overrides.
         *
         * @return string
         */
        function fs_esc_html_inline( $text, $key = '', $slug = 'freemius' ) {
            return esc_html( _fs_text_inline( $text, $key, $slug ) );
        }
    }

    if ( ! function_exists( 'fs_esc_html_x_inline' ) ) {
        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.2.3
         *
         * @param string $text    Translatable string.
         * @param string $context Context information for the translators.
         * @param string $key     String key for overrides.
         * @param string $slug    Module slug for overrides.
         *
         * @return string
         */
        function fs_esc_html_x_inline( $text, $context, $key = '', $slug = 'freemius' ) {
            return esc_html( _fs_text_x_inline( $text, $context, $key, $slug ) );
        }
    }

    if ( ! function_exists( 'fs_esc_html_echo_x_inline' ) ) {
        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.2.3
         *
         * @param string $text    Translatable string.
         * @param string $context Context information for the translators.
         * @param string $key     String key for overrides.
         * @param string $slug    Module slug for overrides.
         */
        function fs_esc_html_echo_x_inline( $text, $context, $key = '', $slug = 'freemius' ) {
            echo esc_html( _fs_text_x_inline( $text, $context, $key, $slug ) );
        }
    }

    if ( ! function_exists( 'fs_esc_html_echo' ) ) {
        /**
         * @author Vova Feldman
         * @since  1.2.1.6
         *
         * @param string $key
         * @param string $slug
         */
        function fs_esc_html_echo( $key, $slug ) {
            echo esc_html( fs_text( $key, $slug ) );
        }
    }

    if ( ! function_exists( 'fs_esc_html_echo_inline' ) ) {
        /**
         * @author Vova Feldman (@svovaf)
         * @since  1.2.3
         *
         * @param string $text Translatable string.
         * @param string $key  String key for overrides.
         * @param string $slug Module slug for overrides.
         */
        function fs_esc_html_echo_inline( $text, $key = '', $slug = 'freemius' ) {
            echo esc_html( _fs_text_inline( $text, $key, $slug ) );
        }
    }

#endregion
