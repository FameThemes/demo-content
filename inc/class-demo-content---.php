<?php
class Demo_Contents {

    public $dir;
    public $url;

    public $processed_posts;


    /**
     * XML file to import
     * Export by WP Exporter
     * @var string file path
     */
    public $xml_file;


    public $config_file;

    /**
     * Option key to store data in $option_file
     *
     * Data type: JSON string
     *
     * @see update_option
     * @var string option_key
     */
    public $option_key;

    public $config_data;

    function __construct( $options = array() ){

        $options = wp_parse_args( $options, array(
            'option_key'    => '',
            'dummy-data'    => '', // file ext
            'config'        => ''
        ));

        $this->xml_file         =  $options['dummy-data'];
        $this->config_file      =  $options['config'];
        $this->option_key       =  $options['option_key'];

    }



    /**
     * Import options
     *
     * Export by function get_options
     *
     * @see get_option
     * @param $option_key
     * @param $file
     * @return bool
     */
    function import_options( $option_key, $file ) {
        if ( ! file_exists( $file ) ) {
            return false;
        }
        if ( ! $option_key ) {
            return  false;
        }
        $data = file_get_contents( $file );
        $data = json_decode( $data , true );

        update_option( $option_key, $data );
    }



    /**
     * Available widgets
     *
     * Gather site's widgets into array with ID base, name, etc.
     * Used by export and import functions.
     *
     * @since 0.4
     * @global array $wp_registered_widget_updates
     * @return array Widget information
     */
    static function get_available_widgets() {

        global $wp_registered_widget_controls;

        $widget_controls = $wp_registered_widget_controls;

        $available_widgets = array();

        foreach ( $widget_controls as $widget ) {

            if ( ! empty( $widget['id_base'] ) && ! isset( $available_widgets[$widget['id_base']] ) ) { // no dupes

                $available_widgets[$widget['id_base']]['id_base'] = $widget['id_base'];
                $available_widgets[$widget['id_base']]['name'] = $widget['name'];

            }

        }

        return $available_widgets;

    }


    /**
     * Generate Widgets export data
     *
     * @since 0.1
     * @return string Export file contents
     */
    static function generate_widgets_export_data() {

        // Get all available widgets site supports
        $available_widgets = self::get_available_widgets();

        // Get all widget instances for each widget
        $widget_instances = array();
        foreach ( $available_widgets as $widget_data ) {

            // Get all instances for this ID base
            $instances = get_option( 'widget_' . $widget_data['id_base'] );

            // Have instances
            if ( ! empty( $instances ) ) {

                // Loop instances
                foreach ( $instances as $instance_id => $instance_data ) {

                    // Key is ID (not _multiwidget)
                    if ( is_numeric( $instance_id ) ) {
                        $unique_instance_id = $widget_data['id_base'] . '-' . $instance_id;
                        $widget_instances[$unique_instance_id] = $instance_data;
                    }

                }

            }

        }

        // Gather sidebars with their widget instances
        $sidebars_widgets = get_option( 'sidebars_widgets' ); // get sidebars and their unique widgets IDs
        $sidebars_widget_instances = array();
        foreach ( $sidebars_widgets as $sidebar_id => $widget_ids ) {

            // Skip inactive widgets
            if ( 'wp_inactive_widgets' == $sidebar_id ) {
                continue;
            }

            // Skip if no data or not an array (array_version)
            if ( ! is_array( $widget_ids ) || empty( $widget_ids ) ) {
                continue;
            }

            // Loop widget IDs for this sidebar
            foreach ( $widget_ids as $widget_id ) {

                // Is there an instance for this widget ID?
                if ( isset( $widget_instances[$widget_id] ) ) {

                    // Add to array
                    $sidebars_widget_instances[$sidebar_id][$widget_id] = $widget_instances[$widget_id];

                }

            }

        }

        // Filter pre-encoded data
        $data = apply_filters( 'ft_demo_export_widgets_data', $sidebars_widget_instances );

        // Encode the data for file contents
        return $data;

    }

    /**
     * generate theme customize data
     *
     * @return mixed|string|void
     */
    static function generate_theme_mods_export_data(){
        $data = get_theme_mods();
        if ( ! $data ) {
            return '';
        }
        return json_encode( $data ) ;
    }

    /**
     * generate option data
     *
     * @param $option_key
     * @return mixed|string|void
     */
    static function generate_options_export_data( $option_key  ){
        $options = get_option( $option_key , array() );
        if ( ! $options ) {
            return '';
        }
        $options = stripslashes_deep( $options );
        return json_encode( $options ) ;
    }

    static function get_update_keys(){

        $key = 'ft_demo_customizer_keys';
        $theme_slug = get_option( 'stylesheet' );
        $data = get_option( $key );
        if ( ! is_array( $data ) ) {
            $data = array();
        }
        if ( isset( $data[ $theme_slug ] ) ){
            return $data[ $theme_slug ];
        }

        $r = wp_remote_post( admin_url( 'customize.php' ), array(
                'method' => 'POST',
                'timeout' => 45,
                'redirection' => 5,
                'httpversion' => '1.0',
                'blocking' => true,
                'headers' => array(),
                'cookies' => array(
                    SECURE_AUTH_COOKIE => $_COOKIE[ SECURE_AUTH_COOKIE ],
                    AUTH_COOKIE => $_COOKIE[ AUTH_COOKIE ],
                    LOGGED_IN_COOKIE => $_COOKIE[ LOGGED_IN_COOKIE ],
                )
            )
        );

        if ( is_wp_error( $r ) ) {
            return false;
        } else {
            global $wpdb;

            $row = $wpdb->get_row( $wpdb->prepare( "SELECT option_value FROM $wpdb->options WHERE option_name = %s LIMIT 1", $key ) );
            $notoptions = wp_cache_get( 'notoptions', 'options' );
            // Has to be get_row instead of get_var because of funkiness with 0, false, null values
            if ( is_object( $row ) ) {
                $value = $row->option_value;
                $data = apply_filters( 'option_' . $key, maybe_unserialize( $value ), $key );
                wp_cache_add( $key, $value, 'options' );
            } else { // option does not exist, so we must cache its non-existence
                if ( ! is_array( $notoptions ) ) {
                    $notoptions = array();
                }
                $notoptions[$key] = true;
                wp_cache_set( 'notoptions', $notoptions, 'options' );

                /** This filter is documented in wp-includes/option.php */
                $data = apply_filters( 'default_option_' . $key, '', $key );
            }

            if ( ! is_array( $data ) ) {
                $data = array();
            }

            if ( isset( $data[ $theme_slug ] ) ) {
                return $data[ $theme_slug ];
            }
        }

        return false;
    }


    static function generate_config(){
        $nav_menu_locations = get_theme_mod( 'nav_menu_locations' );
        // Just update the customizer keys

        $regen_keys = self::get_update_keys();

        $config = array(
            'home_url' => home_url('/'),
            'menus' => $nav_menu_locations,
            'pages' => array(
                'page_on_front'  => get_option( 'page_on_front' ),
                'page_for_posts' => get_option( 'page_for_posts' ),
            ),
            'options' => array(
                'show_on_front' => get_option( 'show_on_front' )
            ),
            'theme_mods' => get_theme_mods(),
            'widgets' => self::generate_widgets_export_data(),
            'customizer_keys' => $regen_keys
        );

        $config = apply_filters( 'ft_demo_generate_config', $config );

        return json_encode( $config );
    }

    /**
     * Run import
     */
    function import(){
        $wp_import = $this->import_xml( $this->xml_file );

        $this->config_data = file_get_contents( $this->config_file );
        $this->config_data  = json_decode( $this->config_data , true );
        $this->config_data = wp_parse_args( $this->config_data, array(
            'home_url' => '',
            'menus' => array(),
            'pages' => array(),
            'options' => array(),
            'theme_mods' => array(),
            'widgets' => array(),
            'customizer_keys' => array()
        ) );
        $this->import_customize( $this->config_data['theme_mods'] );
        $this->import_widgets( $this->config_data['widgets'] );

        if ( $wp_import ) {

            // Update options
            foreach ( ( array ) $this->config_data['options'] as $k => $v ) {
                update_option( $k, $v );
            }

            // Update general pages.
            if ( is_array( $this->config_data['pages'] ) ) {
                foreach ( $this->config_data['pages'] as $k => $post_id ) {
                    if ( $post_id ) {
                        $id = isset( $wp_import->processed_posts[ $post_id ] ) ? $wp_import->processed_posts[ $post_id ] : $post_id;
                        update_option( $k, $id );
                    }
                }
            }

            // Update menus
            $nav_menu_locations = get_theme_mod( 'nav_menu_locations' );
            foreach ( ( array ) $this->config_data['menus'] as $k => $menu_id ) {
                if ( $menu_id ) {
                    $id = isset( $wp_import->processed_terms[ $menu_id ] ) ? $wp_import->processed_terms[ $menu_id ] : $menu_id;
                    $nav_menu_locations[ $k ] = $id;
                }
            }
            set_theme_mod( 'nav_menu_locations', $nav_menu_locations );

            // Update menu links
            if ( $this->config_data['home_url'] ) {

                $demo_url = trailingslashit( $this->config_data['home_url'] );
                $home_url = site_url('/');

                global $wpdb;

                $sql = $wpdb->prepare(
                    "UPDATE {$wpdb->postmeta} SET `meta_value` = REPLACE (`meta_value`, '%s', '%s')",
                    $demo_url,
                    $home_url
                );

                $wpdb->query($sql);

            }

            // Re-setup meta keys
            foreach ( ( array ) $this->config_data['customizer_keys'] as $k => $replace_keys ) {
                $this->resetup_repeater_page_ids( $k , $replace_keys, $wp_import->processed_posts, $this->config_data['home_url'] );
            }

            echo 'demo_imported';
        } else {
            echo 'demo_import_failed';
        }

        do_action( 'ft_import_after_imported', $wp_import );
    }


    function resetup_repeater_page_ids( $theme_mod_name, $list_keys, $processed_posts = array(), $url ='', $option_type = 'theme_mod' ){
        // Setup service
        $data = get_theme_mod( $theme_mod_name );
        if (  is_string( $list_keys ) ) {
            switch( $list_keys ) {
                case 'media':
                    $new_data = $processed_posts[ $data ];
                    if ( $option_type == 'option' ) {
                        update_option( $theme_mod_name , $new_data );
                    } else {
                        set_theme_mod( $theme_mod_name , $new_data );
                    }
                    break;
            }
            return;
        }

        if ( is_string( $data ) ) {
            $data = json_decode( $data, true );
        }
        if ( ! is_array( $data ) ) {
            return false;
        }
        if ( ! is_array( $processed_posts ) ) {
            return false;
        }

        if ( $url ) {
            $url = trailingslashit( $this->config_data['home_url'] );
        }

        $home = home_url('/');


        foreach ($list_keys as $key_info) {
            if ($key_info['type'] == 'post' || $key_info['type'] == 'page') {
                foreach ($data as $k => $item) {
                    if (isset($item[$key_info['key']]) && isset ($processed_posts[$item[$key_info['key']]])) {
                        $data[$k][$key_info['key']] = $processed_posts[$item[$key_info['key']]];
                    }
                }
            } elseif ($key_info['type'] == 'media') {

                $main_key = $key_info['key'];
                $sub_key_id = 'id';
                $sub_key_url = 'url';
                if ($main_key) {

                    foreach ($data as $k => $item) {
                        if (isset($item[$sub_key_id]) && is_array($item[$sub_key_id])) {
                            if (isset ($item[$main_key][$sub_key_id])) {
                                $data[$item][$main_key][$sub_key_id] = $processed_posts[$item[$main_key][$sub_key_id]];
                            }
                            if (isset ($item[$main_key][$sub_key_url])) {
                                $data[$item][$main_key][$sub_key_url] = str_replace($url, $home, $item[$main_key][$sub_key_url]);
                            }
                        }
                    }

                }


            }
        }


        if ( $option_type == 'option' ) {
            update_option( $theme_mod_name , $data );
        } else {
            set_theme_mod( $theme_mod_name , $data );
        }


    }

}
