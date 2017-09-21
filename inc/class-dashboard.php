<?php
class Demo_Content_Dashboard {
    private $api_url = 'https://www.famethemes.com/wp-json/wp/v2/download/?download_type=15&per_page=100&orderby=title&order=asc';
    private $errors = array();
    private $cache_time = 3*HOUR_IN_SECONDS;
    //private $cache_time = 0;
    private $page_slug = 'demo-contents';
    private $config_slugs = array(
        'coupon-wp' => 'wp-coupon'
    );
    private $items = array();
    private $current_theme = null;
    function __construct()
    {
        add_action( 'admin_menu', array( $this, 'add_menu' ) );
        add_action( 'admin_footer', array( $this, 'preview_template' ) );
    }


    function add_menu() {
        add_management_page( __( 'Demo Content', 'demo-contents' ), __( 'Demo Content', 'demo-contents' ), 'manage_options', $this->page_slug, array( $this, 'dashboard' ) );
    }

    function get_items(){
        if ( ! empty( $this->items ) ) {
            return $this->items;
        }
        $cache_key = 'Demo_Content_Dashboard_get_theme';

        if ( ! $this->cache_time ) {
            delete_transient( $cache_key );
        }
        $items = get_transient( $cache_key );

        if ( $items ) {
            return $items;
        }
        $r = wp_remote_get( $this->api_url );
        if ( wp_remote_retrieve_response_code( $r ) != 200 ) {
            $this->errors['COULD_NOT_CONNECT'] = __( 'Could not connect to FameThemes server.', 'demo-contents' );
            return array();
        }

        $items = wp_remote_retrieve_body( $r );
        $items = json_decode( $items, true );
        if ( ! is_array( $items )  || empty( $items ) ) {
            $this->errors['COULD_NOT_LOAD_ITEMS'] = __( 'Could not load themes.', 'demo-contents' );
            return array();
        }

        set_transient( $cache_key , $items, $this->cache_time );

        return $items;
    }

    function is_installed( $theme_slug ){
        $check = wp_get_theme( $theme_slug );
        return $check->exists();
    }

    function setup_themes(){
        $this->current_theme = wp_get_theme();
        $current_child_slug = $this->current_theme->get_stylesheet();
        $current_parent_slug = $this->current_theme->get_template();


        $items = $this->get_items();
        $current_slug = $current_parent_slug;
        if ( isset( $this->items[ $current_child_slug ] ) ) {
            $current_slug = $this->items[ $current_child_slug ];
        }

        $installed_items = array();
        $not_installed_items = array();

        foreach ( $items as $item ) {
            $slug = $item['slug'];
            if ( isset( $this->config_slugs[ $slug  ] ) ) {
                $slug = $this->config_slugs[ $slug  ];
            }
            if ( $current_slug == $slug ) {
                $item['__is_current'] = true;
            } else {
                $item['__is_current'] = false;
            }
            $item['__is_installed'] = $this->is_installed( $slug );
            if ( $item['__is_installed'] ) {
                $installed_items[ $slug ] = $item;
            } else {
                $not_installed_items[ $slug ] = $item;
            }
        }

        $new_items =  array_merge( $installed_items, $not_installed_items );
        $this->items = $new_items;
    }

    function count(){
        return count( $this->items );
    }


    function  preview_template(){
        ?>
        <script id="tmpl-demo-contents--preview" type="text/html">
            <div id="demo-contents--preview">

                  <span type="button" class="demo-contents-collapse-sidebar button" aria-expanded="true">
                        <span class="collapse-sidebar-arrow"></span>
                        <span class="collapse-sidebar-label"><?php _e( 'Collapse', 'demo-contents' ); ?></span>
                    </span>

                <div id="demo-contents-sidebar">
                    <span class="demo-contents-close"><span class="screen-reader-text"><?php _e( 'Close', 'fdi' ); ?></span></span>

                    <div id="demo-contents-sidebar-topbar">
                        <span class="ft-theme-name">{{ data.name }}</span>
                    </div>

                    <div id="demo-contents-sidebar-content">
                        <# if ( data.demo_version ) { #>
                        <div id="demo-contents-sidebar-heading">
                            <span><?php _e( "Your're viewing demo", 'demo-contents' ); ?></span>
                            <strong class="panel-title site-title">{{ data.demo_name }}</strong>
                        </div>
                        <# } #>
                        <# if ( data.img ) { #>
                            <div class="demo-contents--theme-thumbnail">{{{ data.img }}}</div>
                        <# } #>
                        <div class="demo-contents-import-progress">

                            <div class="demo-contents--step demo-contents-install-plugins demo-contents--waiting">
                                <div class="demo-contents--step-heading"><?php _e( 'Install Plugins', 'demo-contents' ); ?></div>
                                <div class="demo-contents--status demo-contents--loading"></div>
                                <div class="demo-contents--child-steps"></div>
                            </div>

                            <div class="demo-contents--step demo-contents-active-plugins demo-contents--waiting">
                                <div class="demo-contents--step-heading"><?php _e( 'Active Plugins', 'demo-contents' ); ?></div>
                                <div class="demo-contents--status demo-contents--waiting"></div>
                                <div class="demo-contents--child-steps"></div>
                            </div>

                            <div class="demo-contents--step demo-contents-import-users demo-contents--waiting">
                                <div class="demo-contents--step-heading"><?php _e( 'Import Users', 'demo-contents' ); ?></div>
                                <div class="demo-contents--status demo-contents--waiting"></div>
                                <div class="demo-contents--child-steps"></div>
                            </div>

                            <div class="demo-contents--step demo-contents-import-categories demo-contents--waiting">
                                <div class="demo-contents--step-heading"><?php _e( 'Import Categories', 'demo-contents' ); ?></div>
                                <div class="demo-contents--status demo-contents--completed"></div>
                                <div class="demo-contents--child-steps"></div>
                            </div>

                            <div class="demo-contents--step demo-contents-import-tags demo-contents--waiting">
                                <div class="demo-contents--step-heading"><?php _e( 'Import Tags', 'demo-contents' ); ?></div>
                                <div class="demo-contents--status demo-contents--completed"></div>
                                <div class="demo-contents--child-steps"></div>
                            </div>

                            <div class="demo-contents--step demo-contents-import-taxs demo-contents--waiting">
                                <div class="demo-contents--step-heading"><?php _e( 'Import Taxonomies', 'demo-contents' ); ?></div>
                                <div class="demo-contents--status demo-contents--waiting"></div>
                                <div class="demo-contents--child-steps"></div>
                            </div>

                            <div class="demo-contents--step  demo-contents-import-posts demo-contents--waiting">
                                <div class="demo-contents--step-heading"><?php _e( 'Import Posts', 'demo-contents' ); ?></div>
                                <div class="demo-contents--status demo-contents--waiting"></div>
                                <div class="demo-contents--child-steps"></div>
                            </div>

                            <div class="demo-contents--step demo-contents-import-theme-options demo-contents--waiting">
                                <div class="demo-contents--step-heading"><?php _e( 'Import theme Options', 'demo-contents' ); ?></div>
                                <div class="demo-contents--status demo-contents--waiting"></div>
                                <div class="demo-contents--child-steps"></div>
                            </div>

                            <div class="demo-contents--step demo-contents-import-widgets demo-contents--waiting">
                                <div class="demo-contents--step-heading"><?php _e( 'Import Widgets', 'demo-contents' ); ?></div>
                                <div class="demo-contents--status demo-contents--waiting"></div>
                                <div class="demo-contents--child-steps"></div>
                            </div>

                            <div class="demo-contents--step  demo-contents-import-customize demo-contents--waiting">
                                <div class="demo-contents--step-heading"><?php _e( 'Import Customize', 'demo-contents' ) ?></div>
                                <div class="demo-contents--status demo-contents--waiting"></div>
                                <div class="demo-contents--child-steps"></div>
                            </div>
                        </div>

                    </div><!-- /.demo-contents-sidebar-content -->

                    <div id="demo-contents-sidebar-footer">
                        <input type="button" class="demo-contents--import-now button button-primary save" value="<?php esc_attr_e( 'Import Now', 'demo-contents' ); ?>">
                    </div>

                </div>
                <div id="demo-contents-viewing">
                    <iframe src="{{ data.demoURL }}"></iframe>
                </div>
            </div>
        </script>
        <?php
    }

    function dashboard() {
        if ( ! current_user_can( 'manage_options' ) )  {
            wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
        }

        $this->setup_themes();
        $n = $this->count();
        $link_all = '?page='.$this->page_slug;
        $link_current_theme = '?page='.$this->page_slug.'&tab=current_theme';
        $link_export= '?page='.$this->page_slug.'&tab=export';
        $tab = isset( $_GET['tab'] )  ? $_GET['tab'] : '';

        echo '<div class="wrap demo-contents">';
            ?>
            <h1 class="wp-heading-inline"><?php _e( 'Demo Contents', 'demo-contents' ); ?><span class="title-count theme-count"><?php echo $n; ?></span></h1>
            <div class="wp-filter hide-if-no-js">
                <div class="filter-count">
                    <span class="count theme-count"><?php echo $n; ?></span>
                </div>
                <ul class="filter-links">
                    <li><a href="<?php echo $link_all; ?>" class="<?php echo ( ! $tab ) ? 'current' : ''; ?>"><?php _e( 'All Demos', 'demo-contents' ); ?></a></li>
                    <li><a href="<?php echo $link_export; ?>"  class="<?php echo ( $tab == 'export' ) ? 'current' : ''; ?>"><?php _e( 'Export', 'demo-contents' ); ?></a></li>
                </ul>
                <form class="search-form"><label class="screen-reader-text" for="wp-filter-search-input"><?php _e( 'Search Demos', 'demo-contents' ); ?></label><input placeholder="Search themes..." aria-describedby="live-search-desc" id="wp-filter-search-input" class="wp-filter-search" type="search"></form>
            </div>

            <?php

            echo '<div class="theme-browser rendered">';
                echo '<div class="themes wp-clearfix">';

                    ?>
                    <div class="demo-contents--current-theme theme" tabindex="0" data-slug="<?php echo esc_attr($this->current_theme->get_template()); ?>">
                        <div class="theme-screenshot">
                            <img src="<?php echo esc_url( $this->current_theme->get_screenshot() ); ?>" alt="">
                        </div>
                        <span class="more-details"><?php _e( 'Current Theme', 'demo-contents' ); ?></span>
                        <div class="theme-author">By FameThemes</div>
                        <h2 class="theme-name" id="<?php echo esc_attr($this->current_theme->get_template()); ?>-name"><?php echo esc_html( $this->current_theme->get( 'Name' ) ); ?></h2>
                        <div class="theme-actions">
                            <a href="#"
                               data-theme-slug="<?php echo esc_attr( $this->current_theme->get_template() ); ?>"
                               data-demo-version=""
                               data-name="<?php echo esc_attr( $this->current_theme->get( 'Name' ) ); ?>"
                               data-demo-url=""
                               class="demo-contents--preview-theme-btn button button-primary"><?php _e( 'Start Import Demo', 'demo-contents' ); ?></a>
                        </div>
                    </div>
                <?php

                foreach (  $this->items as $theme => $item ) {
                    if ( ! $item['__is_current'] ) {
                        ?>
                        <div class="theme" tabindex="0" aria-describedby="<?php echo esc_attr($theme); ?>-action <?php echo esc_attr($theme); ?>-name" data-slug="<?php echo esc_attr($theme); ?>">
                            <div class="theme-screenshot">
                                <img src="<?php echo esc_url($item['_image']) ?>" alt="">
                            </div>
                            <a href="<?php echo esc_url( $item['link'] ); ?>" target="_blank" class="more-details" id="<?php echo esc_attr($theme); ?>-action"><?php _e( 'Theme Details', 'demo-contents' ); ?></a>
                            <div class="theme-author"><?php _e( 'By FameThemes', 'demo-content' ); ?></div>
                            <h2 class="theme-name" id="<?php echo esc_attr($theme); ?>-name"><?php echo esc_html($item['title']['rendered']); ?></h2>
                            <div class="theme-actions">
                                <?php
                                if ( $item['__is_installed'] ) {
                                    ?>
                                    <a
                                        data-theme-slug="<?php echo esc_attr($theme); ?>"
                                        data-demo-version=""
                                        data-name="<?php echo esc_html($item['title']['rendered']); ?>"
                                        data-demo-url=""
                                        class="demo-contents--preview-theme-btn button button-primary customize"
                                        href="#"
                                    ><?php _e( 'View', 'demo-contents' ); ?></a>
                                    <?php
                                } else {
                                    ?>
                                    <a class="button button-secondary customize" target="_blank" href="<?php echo esc_url( $item['link'] ); ?>"><?php _e( 'Download', 'demo-contents' ); ?></a>
                                    <?php
                                }
                                ?>
                            </div>
                        </div>
                        <?php
                    }
                } // end loop items

                echo '</div>';
            echo '</div>';
        echo '</div>';
        ?>
        <?php
    }
}

new Demo_Content_Dashboard();




//wp_remote_get( 'https://www.famethemes.com//wp-json/wp/v2/posts?filter[posts_per_page]=5' );
//wp_remote_get( 'https://www.famethemes.com/wp-json/wp/v2/download/?per_page=100' );
