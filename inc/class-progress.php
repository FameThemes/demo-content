<?php
/**
 * Created by PhpStorm.
 * User: truongsa
 * Date: 9/16/17
 * Time: 9:10 AM
 */


class  Demo_Contents_Progress {


    private $config_data= array();

    function __construct()
    {
        add_action( 'admin_enqueue_scripts', array( $this, 'scripts' ) );
        add_action( 'wp_ajax_demo_contents__import', array( $this, 'ajax_import' ) );
    }

    /**
     * @see https://github.com/devinsays/edd-theme-updater/blob/master/updater/theme-updater.php
     */
    function ajax_import(){

        wp_send_json_success(); // just for test

        // Test Import theme Option only

        $demo_config_file = DEMO_CONTENT_PATH.'demos/onepress/config.json';
        $demo_xml_file = DEMO_CONTENT_PATH.'demos/onepress/dummy-data.xml';

        if ( ! class_exists( 'Merlin_WXR_Parser' ) ) {
            require DEMO_CONTENT_PATH. 'inc/merlin-wp/includes/class-merlin-xml-parser.php' ;
        }

        if ( ! class_exists( 'Merlin_Importer' ) ) {
            require DEMO_CONTENT_PATH .'inc/merlin-wp/includes/class-merlin-importer.php';
        }

        if ( ! current_user_can( 'import' ) ) {
            wp_send_json_error( __( "You have not permissions to import.", 'demo-contents' ) );
        }
        $importer = new Merlin_Importer();
        //$importer->import( $demo_xml_file );
        $doing = isset( $_REQUEST['doing'] ) ? sanitize_text_field( $_REQUEST['doing'] ) : '';
        if ( ! $doing ) {
            wp_send_json_error( __( "No actions to do", 'demo-contents' ) );
        }

        $theme      =  isset( $_REQUEST['theme'] ) ? sanitize_text_field( $_REQUEST['theme'] ) : ''; // Theme to import
        $version    =  isset( $_REQUEST['version'] ) ? sanitize_text_field( $_REQUEST['version'] ) : ''; // demo version

        //$transient_key = 'ft_demo_xml_data'.$theme.$version;
        //$content = get_transient( $transient_key );

        $content = false;

        if ( ! $content ) {
            $parser = new Merlin_WXR_Parser();
            $content = $parser->parse( $demo_xml_file );
           // set_transient( $transient_key, $content, DAY_IN_SECONDS );
        }
        if ( is_wp_error( $content ) ) {
            wp_send_json_success( 'no_demo_import' );
        }

        //$importer->importStart();

        switch ( $doing ) {
            case 'import_users':
                if ( ! empty( $content['users'] ) ) {
                    $importer->import_users( $content['users'] );
                }
                break;

            case 'import_categories':
                if ( ! empty( $content['categories'] ) ) {
                    $importer->importTerms( $content['categories'] );
                }
                break;
            case 'import_tags':
                if ( ! empty( $content['tags'] ) ) {
                    $importer->importTerms( $content['tags'] );
                }
                break;
            case 'import_taxs':
                if ( ! empty( $content['terms'] ) ) {
                    $importer->importTerms( $content['terms'] );
                }
                break;
            case 'import_posts':
                if ( ! empty( $content['posts'] ) ) {
                    $importer->importPosts( $content['posts'] );
                }
                $importer->remapImportedData();
                //$importer->importEnd();

                break;

            case 'import_theme_options':
                global $wp_filesystem;
                WP_Filesystem();
                $file_contents = $wp_filesystem->get_contents( $demo_config_file );
                $option_config = json_decode( $file_contents, true );
                $this->config_data = $option_config;
                if ( isset( $option_config['options'] ) ){
                    $this->importOptions( $option_config['options'] );
                }
                print_r( $option_config['pages'] );
                // Setup Pages
                $processed_posts = get_transient('_wxr_imported_posts') ? : array();
                if ( isset( $option_config['pages'] ) ){
                    foreach ( $option_config['pages']  as $key => $id ) {
                        $val = isset( $processed_posts[ $id ] )  ? $processed_posts[ $id ] : null ;
                        update_option( $key, $val );
                    }
                }


                break;

            case 'import_widgets':
                global $wp_filesystem;
                WP_Filesystem();
                $file_contents = $wp_filesystem->get_contents( $demo_config_file );
                $option_config = json_decode( $file_contents, true );
                $this->config_data = $option_config;
                if ( isset( $option_config['widgets'] ) ){
                   // print_r( $option_config['widgets'] );
                    $importer->importWidgets( $option_config['widgets'] );
                }
                break;

            case 'import_customize':
                global $wp_filesystem;
                WP_Filesystem();
                $file_contents = $wp_filesystem->get_contents( $demo_config_file );
                $option_config = json_decode( $file_contents, true );
                $this->config_data = $option_config;
                print_r( $option_config['theme_mods'] );
                if ( isset( $option_config['theme_mods'] ) ){

                    $importer->importThemeOptions( $option_config['theme_mods'] );
                    if ( isset( $option_config['customizer_keys'] ) ) {
                        foreach ( ( array ) $option_config['customizer_keys'] as $k=> $list_key ) {
                            $this->resetup_repeater_page_ids( $k, $list_key );
                        }
                    }

                }

                $importer->importEnd();
                break;

        }
    }


    function importOptions( $options ){
        if ( empty( $options ) ) {
            return ;
        }
        foreach ( $options as $option_name => $ops ) {
            update_option( $option_name, $ops );
        }
    }

    function scripts(){
        wp_enqueue_style( 'demo-contents', DEMO_CONTENT_URL . 'style.css', false );

        wp_enqueue_script( 'underscore');
        wp_enqueue_script( 'demo-contents', DEMO_CONTENT_URL.'assets/js/importer.js', array( 'jquery', 'underscore' ) );

        $run = isset( $_REQUEST['import_now'] ) && $_REQUEST['import_now'] == 1 ? 'run' : 'no';

        $themes = array();
        $install_themes = wp_get_themes();
        foreach (  $install_themes as $slug => $theme ) {
            $themes[ $slug ] = $theme->get( "Name" );
        }

        $tgm_url = '';
        // Localize the javascript.
        $plugins = array();
        if ( class_exists( 'TGM_Plugin_Activation' ) ) {
            $this->tgmpa = isset($GLOBALS['tgmpa']) ? $GLOBALS['tgmpa'] : TGM_Plugin_Activation::get_instance();
            $plugins = $this->get_tgmpa_plugins();
            $tgm_url = $this->tgmpa->get_tgmpa_url();
        }

        $template_slug  = get_option( 'template' );
        $theme_slug     = get_option( 'stylesheet' );

        wp_localize_script( 'demo-contents', 'demo_contents_params', array(
            'tgm_plugin_nonce' 	=> array(
                'update'  	=> wp_create_nonce( 'tgmpa-update' ),
                'install' 	=> wp_create_nonce( 'tgmpa-install' ),
            ),
            'tgm_bulk_url' 		    => $tgm_url,
            'ajaxurl'      		    => admin_url( 'admin-ajax.php' ),
            'wpnonce'      		    => wp_create_nonce( 'merlin_nonce' ),
            'action_install_plugin' => 'tgmpa-bulk-activate',
            'action_active_plugin'  => 'tgmpa-bulk-activate',
            'action_update_plugin'  => 'tgmpa-bulk-update',
            'plugins'               => $plugins,
            'home'                  => home_url('/'),
            'btn_done_label'        => __( 'All Done! View Site', 'demo-contents' ),
            'failed_msg'            => __( 'Import Failed!', 'demo-contents' ),
            'installed_themes'      => $themes
        ) );

    }

    /**
     * Get registered TGMPA plugins
     *
     * @return    array
     */
    protected function get_tgmpa_plugins() {
        $plugins  = array(
            'all'      => array(), // Meaning: all plugins which still have open actions.
            'install'  => array(),
            'update'   => array(),
            'activate' => array(),
        );

        $tgmpa_url = $this->tgmpa->get_tgmpa_url();

        foreach ( $this->tgmpa->plugins as $slug => $plugin ) {
            if ( $this->tgmpa->is_plugin_active( $slug ) && false === $this->tgmpa->does_plugin_have_update( $slug ) ) {
                continue;
            } else {
                $plugins['all'][ $slug ] = $plugin;

                $args =   array(
                    'plugin' => $slug,
                    'tgmpa-page' => $this->tgmpa->menu,
                    'plugin_status' => 'all',
                    '_wpnonce' => wp_create_nonce('bulk-plugins'),
                    'action' => '',
                    'action2' => -1,
                    //'message' => esc_html__('Installing', '@@textdomain'),
                );

                $plugin['page_url'] = $tgmpa_url;

                if ( ! $this->tgmpa->is_plugin_installed( $slug ) ) {
                    $plugins['install'][ $slug ] = $plugin;
                    $action = 'tgmpa-bulk-install';
                    $args['action'] = $action;
                    $plugins['install'][ $slug ][ 'args' ] = $args;
                } else {
                    if ( false !== $this->tgmpa->does_plugin_have_update( $slug ) ) {
                        $plugins['update'][ $slug ] = $plugin;
                        $action = 'tgmpa-bulk-update';
                        $args['action'] = $action;
                        $plugins['update'][ $slug ][ 'args' ] = $args;
                    }
                    if ( $this->tgmpa->can_plugin_activate( $slug ) ) {
                        $plugins['activate'][ $slug ] = $plugin;
                        $action = 'tgmpa-bulk-activate';
                        $args['action'] = $action;
                        $plugins['activate'][ $slug ][ 'args' ] = $args;
                    }
                }


            }
        }

        return $plugins;
    }


    function resetup_repeater_page_ids( $theme_mod_name = null, $list_keys, $processed_posts = array(), $url ='', $option_type = 'theme_mod' ){

        $processed_posts = get_transient('_wxr_imported_posts') ? : array();
        if ( ! is_array( $processed_posts ) ) {
            $processed_posts = array();
        }

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

new Demo_Contents_Progress();