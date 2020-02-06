<?php

/**
 * Zume_REST_API
 *
 * @class Zume_REST_API
 * @version 0.1
 * @since 0.1
 * @package Disciple_Tools
 */

if ( ! defined( 'ABSPATH' ) ) { exit; } // Exit if accessed directly


class Zume_REST_API {

    /**
     * Zume_REST_API The single instance of Zume_REST_API.
     * @var     object
     * @access  private
     * @since   0.1
     */
    private static $_instance = null;

    /**
     * Main Zume_REST_API instance
     *
     * Ensures only one instance of Zume_REST_API is loaded or can be loaded.
     *
     * @since 0.1
     * @static
     * @return Zume_REST_API instance
     */
    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    } // End instance()

    /**
     * Constructor function.
     * @access  public
     * @since   0.1
     */
    public function __construct() {
        add_action( 'rest_api_init', array( $this,  'add_api_routes' ) );

    } // End __construct()

    public function add_api_routes() {
        $version = '4';
        $namespace = 'zume/v' . $version;

        register_rest_route( $namespace, '/coaching_request', array(
            array(
                'methods'         => WP_REST_Server::CREATABLE,
                'callback'        => array( $this, 'coaching_request' ),
                "permission_callback" => function () {
                    return current_user_can( 'zume' );
                }
            ),
        ) );
        register_rest_route( $namespace, '/update_profile', array(
            array(
                'methods'         => WP_REST_Server::CREATABLE,
                'callback'        => array( $this, 'update_profile' ),
                "permission_callback" => function () {
                    return current_user_can( 'zume' );
                }
            ),
        ) );
        register_rest_route( $namespace, '/unlink_profile', array(
            array(
                'methods'         => WP_REST_Server::CREATABLE,
                'callback'        => array( $this, 'unlink_profile' ),
                "permission_callback" => function () {
                    return current_user_can( 'zume' );
                }
            ),
        ) );
    }

    public function unlink_profile( WP_REST_Request $request){
        $params = $request->get_json_params();
        if ( isset( $params['type'] ) ) {
            switch ( $params['type'] ) {
                case 'facebook':
                    delete_user_meta( get_current_user_id(), 'facebook_sso_email' );
                    delete_user_meta( get_current_user_id(), 'facebook_sso_id' );
                    delete_user_meta( get_current_user_id(), 'facebook_session_token' );
                    break;
                case 'google':
                    delete_user_meta( get_current_user_id(), 'google_sso_email' );
                    delete_user_meta( get_current_user_id(), 'google_sso_id' );
                    delete_user_meta( get_current_user_id(), 'google_session_token' );
                    break;
                default:
                    return new WP_Error( "tract_param_error", "Unable to unlink profile", array( 'status' => 400 ) );
                    break;
            }
        } else {
            return new WP_Error( "tract_param_error", "Unable to unlink profile", array( 'status' => 400 ) );
        }
        return true;
    }

    public function update_profile( WP_REST_Request $request){
        $params = $request->get_json_params();
        $user_info = get_userdata( get_current_user_id() );

        // update name
        $name = sanitize_text_field( wp_unslash( $params['name'] ) );
        if ( empty( $name ) ) {
            delete_user_meta( $user_info->ID, 'zume_full_name' );
        } else {
            update_user_meta( $user_info->ID, 'zume_full_name', $name );
        }

        // update phone
        $phone = sanitize_text_field( wp_unslash( $params['phone'] ) );
        if ( empty( $phone ) ) {
            delete_user_meta( $user_info->ID, 'zume_phone_number' );
        } else {
            update_user_meta( $user_info->ID, 'zume_phone_number', $phone );
        }

        // update email
        $email = sanitize_email( wp_unslash( $params['email'] ) );
        if ( $email !== $user_info->ID && ! empty( $email ) ) {
            $args = array();
            $args['ID'] = $user_info->ID;
            $args['user_email'] = $email;

            $result = wp_update_user( $args );
            if ( is_wp_error( $result ) ) {
                return new WP_Error( 'fail_update_user_data', 'Error while updating user data in user table.' );
            }
        }

        // update affiliation key
        $affiliation_key = sanitize_text_field( wp_unslash( trim( $params['affiliation_key'] ) ) );
        if ( empty( $affiliation_key ) ) {
            delete_user_meta( $user_info->ID, 'zume_affiliation_key' );
        } else {
            update_user_meta( $user_info->ID, 'zume_affiliation_key', $affiliation_key );
        }

        // update location_grid_meta
        if ( empty( $params['location_grid_meta'] ) ) {
            delete_user_meta( $user_info->ID, 'location_grid_meta' );
        } else {
            $location_grid_meta = array_map( 'sanitize_text_field', wp_unslash( $params['location_grid_meta'] ) );
            $lng = empty( $location_grid_meta['lng'] ) ? false : $location_grid_meta['lng'];
            $lat = empty( $location_grid_meta['lat'] ) ? false : $location_grid_meta['lat'];
            if ( $lng && $lat ) {
                $geocoder = new Location_Grid_Geocoder();
                $grid = $geocoder->get_grid_id_by_lnglat( $lng, $lat );
                if ( isset( $grid['grid_id'] ) ) {
                    $location_grid_meta['grid_id'] = $grid['grid_id'];
                }
            }

            update_user_meta( $user_info->ID, 'location_grid_meta', $location_grid_meta );
        }

        $zume_user = wp_get_current_user();
        $zume_user_meta = zume_get_user_meta( $zume_user->ID );

        return [
            'id' => $zume_user->data->ID,
            'name' => $zume_user_meta['zume_full_name'] ?? '',
            'email' => $zume_user->data->user_email,
            'phone' => $zume_user_meta['zume_phone_number'] ?? '',
            'location_grid_meta' => maybe_unserialize( $zume_user_meta['location_grid_meta'] ) ?? '',
            'affiliation_key' => $zume_user_meta['zume_affiliation_key'] ?? '',
            'facebook_sso_email' => $zume_user_meta['facebook_sso_email'] ?? false,
            'google_sso_email' => $zume_user_meta['google_sso_email'] ?? false,
        ];
    }

    /**
     * @param WP_REST_Request $request
     * @return array|WP_Error
     */
    public function coaching_request( WP_REST_Request $request ) {
        $user_id = get_current_user_id();
        $params = $request->get_params();
        if ( ! isset( $params['name'] ) ) {
            return new WP_Error( "log_param_error", "Missing parameters", array( 'status' => 400 ) );
        }

        $args = array(
            'name' => sanitize_text_field( wp_unslash( $params['name'] ) ),
            'phone' => sanitize_text_field( wp_unslash( $params['phone'] ) ),
            'email' => sanitize_text_field( wp_unslash( $params['email'] ) ),
            'preference' => sanitize_text_field( wp_unslash( $params['preference'] ) ),
            'affiliation_key' => sanitize_text_field( wp_unslash( $params['affiliation_key'] ) ),
        );
        $notes = [
            'preference' => 'Requested contact method is: ' .$args['preference'],
            'affiliation' => 'Requested affiliation is: ' . $args['affiliation_key']
        ];
        $zume_foreign_key = Zume_Integration::get_foreign_key( $user_id );

        // build fields for transfer
        $fields = [
            "title" => $args['name'],
            "sources" => [
                "values" => [
                    [ "value" => "zume_training" ],  //add new, or make sure it exists
                ],
            ],
            "contact_phone" => [
                [ "value" => $args['phone'] ],
            ],
            "contact_email" => [
                [ "value" => $args['email'] ],
            ],
            'zume_training_id' => $user_id,
            'zume_foreign_key' => $zume_foreign_key,
            "notes" => $notes,
        ];

        // Additional fields that may or may not be present

        // Build location_grid_meta
        $geocoder = new Location_Grid_Geocoder();
        if ( empty( $params['location_grid_meta'] ) ) {
            // if no provided location, get ip address location
            $ip_result = DT_Ipstack_API::geocode_current_visitor();
            $args['location_grid_meta'] = $geocoder->convert_ip_result_to_location_grid_meta( $ip_result );
        } else if ( empty( $params['location_grid_meta']['grid_id'] ) ) {
            $grid = $geocoder->get_grid_id_by_lnglat( $params['location_grid_meta']['lng'], $params['location_grid_meta']['lat'] );
            $params['location_grid_meta']['grid_id'] = (int) $grid['grid_id'];
            $args['location_grid_meta'] = $params['location_grid_meta'];
        } else if ( ! empty( $params['location_grid_meta'] ) ) {
            $args['location_grid_meta'] = $params['location_grid_meta'];
        } else {
            $args['location_grid_meta'] = false;
        }

        if ( $args['location_grid_meta'] ) {
            $fields['location_grid'] = [ "values" => [ [ "value" => $args['location_grid_meta']['grid_id'] ] ] ];
            $coordinates = [];
            $coordinates['values'][] = [
                'value' => $args['location_grid_meta']
            ];
            // load location_grid_meta field
            $fields['location_grid_meta'] = $coordinates;
            // load address field
            $fields['contact_address'] = [
                [ "value" => $args['location_grid_meta']['label'] ],
            ];
        }


        $site = Site_Link_System::get_site_connection_vars( 20125 ); // @todo remove hardcoded
        if ( ! $site ) {
            return new WP_Error( __METHOD__, 'Missing site to site data' );
        }

        $args = [
            'method' => 'POST',
            'body' => $fields,
            'headers' => [
                'Authorization' => 'Bearer ' . $site['transfer_token'],
            ],
        ];


        $result = wp_remote_post( 'https://' . trailingslashit( $site['url'] ) . 'wp-json/dt/v1/contact/create', $args );
        if ( is_wp_error( $result ) ) {
            return new WP_Error( 'failed_remote_post', $result->get_error_message() );
        }

        $body = json_decode( $result['body'], true );

        update_user_meta( $user_id, 'zume_global_network', [
            "contact_id" => $body['post_id'],
            "date_transferred" => time()
        ] );

        return $result;

    }
}
Zume_REST_API::instance();