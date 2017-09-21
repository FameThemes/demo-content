var ft_import_running = false;
window.onbeforeunload = function() {
    if ( ft_import_running ) {
        return FT_IMPORT_DEMO.confirm_leave;
    }
};


function loading_icon(){
    var frame = $( '<iframe style="display: none;"></iframe>' );
    frame.appendTo('body');
    // Thanks http://jsfiddle.net/KSXkS/1/
    try { // simply checking may throw in ie8 under ssl or mismatched protocol
        doc = frame[0].contentDocument ? frame[0].contentDocument : frame[0].document;
    } catch(err) {
        doc = frame[0].document;
    }
    doc.open();
    doc.close();
}


// -------------------------------------------------------------------------------

(function ( $ ) {

    var demo_contents_params = demo_contents_params || window.demo_contents_params;

    if( typeof demo_contents_params.plugins.activate !== "object" ) {
        demo_contents_params.plugins.activate = {};
    }
    var $document = $( document );
    var is_importing = false;

    /**
     * Function that loads the Mustache template
     */
    var repeaterTemplate = _.memoize(function () {
        var compiled,
            /*
             * Underscore's default ERB-style templates are incompatible with PHP
             * when asp_tags is enabled, so WordPress uses Mustache-inspired templating syntax.
             *
             * @see track ticket #22344.
             */
            options = {
                evaluate: /<#([\s\S]+?)#>/g,
                interpolate: /\{\{\{([\s\S]+?)\}\}\}/g,
                escape: /\{\{([^\}]+?)\}\}(?!\})/g,
                variable: 'data'
            };

        return function (data, tplId ) {
            if ( typeof tplId === "undefined" ) {
                tplId = '#tmpl-demo-contents--preview';
            }
            compiled = _.template(jQuery( tplId ).html(), null, options);
            return compiled(data);
        };
    });

    var template = repeaterTemplate();

    var ftDemoContents  = {
        loading_step: function( $element ){
            $element.removeClass( 'demo-contents--waiting demo-contents--running' );
            $element.addClass( 'demo-contents--running' );
        },
        completed_step: function( $element, event_trigger ){
            $element.removeClass( 'demo-contents--running demo-contents--waiting' ).addClass( 'demo-contents--completed' );
            if ( typeof event_trigger !== "undefined" ) {
                $document.trigger( event_trigger );
            }
        },
        preparing_plugins: function() {
            var $list_install_plugins = $('.demo-contents-install-plugins');
            var n = _.size(demo_contents_params.plugins.install);
            if (n > 0) {
                var $child_steps = $list_install_plugins.find('.demo-contents--child-steps');
                $.each(demo_contents_params.plugins.install, function ($slug, plugin) {
                    var $item = $('<div class="demo-contents-child-item demo-contents-plugin-' + $slug + '">Installing ' + plugin.name + '</div>');
                    $child_steps.append($item);
                    $item.attr('data-plugin', $slug);
                });
            }

            var $list_active_plugins = $( '.demo-contents-active-plugins' );
            var $activate_child_steps = $list_active_plugins.find(  '.demo-contents--child-steps' );
            $.each(demo_contents_params.plugins.all, function ($slug, plugin) {
                var $item = $(  '<div class="demo-contents-child-item demo-contents-plugin-'+$slug+'">Activating '+plugin.name+'</div>' );
                $activate_child_steps.append( $item );
                $item.attr( 'data-plugin', $slug );
            });

        },
        installPlugins: function() {
            var that = this;
            // Install Plugins
            var $list_install_plugins = $( '.demo-contents-install-plugins' );
            that.loading_step( $list_install_plugins );
            console.log( 'Being installing plugins....' );
            var $child_steps = $list_install_plugins.find(  '.demo-contents--child-steps' );
            var n = _.size( demo_contents_params.plugins.install );
            if ( n > 0 ) {
                var current = $child_steps.find( '.demo-contents-child-item' ).eq( 0 );
                var callback = function( current ){
                    if ( current.length ) {
                        var slug = current.attr( 'data-plugin' );
                        var plugin =  demo_contents_params.plugins.install[ slug ];
                        $.post( plugin.page_url, plugin.args, function (res) {
                            //console.log(plugin.name + ' Install Completed');
                            plugin.action = demo_contents_params.action_active_plugin;
                            demo_contents_params.plugins.activate[ slug ] = plugin;
                            console.log( plugin.name + ' installed' );
                            current.html( plugin.name + ' installed'  );
                            var next = current.next();
                            callback( next );
                        });
                    } else {
                        console.log( 'Plugin invalid switch to install completed' );
                        that.completed_step( $list_install_plugins, 'demo_contents_plugins_install_completed' );
                    }
                };
                callback( current );
            } else {
                console.log( 'Plugins install completed' );
                that.completed_step( $list_install_plugins, 'demo_contents_plugins_install_completed' );
            }

        },
        activePlugins: function(){
            var that = this;
            var $list_active_plugins = $( '.demo-contents-active-plugins' );
            that.loading_step( $list_active_plugins );
            var $child_steps = $list_active_plugins.find(  '.demo-contents--child-steps' );
            var n = _.size( demo_contents_params.plugins.activate );
            console.log( 'Being activate plugins....' );
            if (  n > 0 ) {

                $.each( demo_contents_params.plugins.activate, function ($slug, plugin) {
                    var $item = $('<div class="demo-contents-child-item demo-contents-plugin-' + $slug + '">Activating ' + plugin.name + '</div>');
                    $child_steps.append($item);
                    $item.attr('data-plugin', $slug);
                });

                var callback = function (current) {
                    if (current.length) {
                        var slug = current.attr('data-plugin');
                        var plugin = demo_contents_params.plugins.activate[slug];
                        if (typeof  plugin !== "undefined") {
                            $.post(plugin.page_url, plugin.args, function (res) {
                                console.log( plugin.name + ' activated' );
                                current.html(plugin.name + ' activated');
                                var next = current.next();
                                callback(next);
                            });
                        } else {
                            console.log( 'Plugin invalid switch to activate completed' );
                            that.completed_step( $list_active_plugins, 'demo_contents_plugins_active_completed' );
                        }
                    } else {
                        console.log(' Activated all plugins');
                        that.completed_step( $list_active_plugins, 'demo_contents_plugins_active_completed' );
                    }
                };

                var current = $child_steps.find( '.demo-contents-child-item' ).eq( 0 );
                callback( current );

            } else {
                $list_active_plugins.removeClass('demo-contents--running demo-contents--waiting').addClass('demo-contents--completed');
                $document.trigger('demo_contents_plugins_active_completed');
            }


        },
        ajax: function( doing, complete_cb ){
            console.log( 'Being....', doing );
            $.ajax( {
                url: demo_contents_params.ajaxurl,
                data: {
                    action: 'demo_contents__import',
                    doing: doing,
                    theme: '', // Import demo for theme ?
                    version: '' // Current demo version ?
                },
                type: 'GET',
                dataType: 'html',
                success: function( res ){

                    console.log( res );
                    if ( typeof complete_cb === 'function' ) {
                        complete_cb();
                    }
                    console.log( 'Completed: ', doing );
                    $document.trigger( 'demo_contents_'+doing+'_completed' );
                },
                fail: function(){
                    console.log( 'Failed: ', doing );
                    $document.trigger( 'demo_contents_'+doing+'_failed' );
                    $document.trigger( 'demo_contents_ajax_failed', [ doing ] );
                }

            } )
        },
        import_users: function(){
            var step =  $( '.demo-contents-import-users' );
            var that = this;
            that.loading_step( step );
            this.ajax( 'import_users', function(){
                that.completed_step( step );
            } );
        },
        import_categories: function(){
            var step =  $( '.demo-contents-import-categories' );
            var that = this;
            that.loading_step( step );
            this.ajax(  'import_categories', function(){
                that.completed_step( step );
            } );
        },
        import_tags: function(){
            var step =  $( '.demo-contents-import-tags' );
            var that = this;
            that.loading_step( step );
            this.ajax(  'import_tags', function(){
                that.completed_step( step );
            } );
        },
        import_taxs: function(){
            var step =  $( '.demo-contents-import-taxs' );
            var that = this;
            that.loading_step( step );
            this.ajax(  'import_taxs', function(){
                that.completed_step( step );
            } );
        },
        import_posts: function(){
            var step =  $( '.demo-contents-import-posts' );
            var that = this;
            that.loading_step( step );
            this.ajax( 'import_posts', function(){
                that.completed_step( step );
            } );
        },

        import_theme_options: function(){
            var step =  $( '.demo-contents-import-theme-options' );
            var that = this;
            that.loading_step( step );
            this.ajax( 'import_theme_options', function(){
                that.completed_step( step );
            } );
        },

        import_widgets: function(){
            var step =  $( '.demo-contents-import-widgets' );
            var that = this;
            that.loading_step( step );
            this.ajax( 'import_widgets', function(){
                that.completed_step( step );
            } );
        },

        import_customize: function(){
            var step =  $( '.demo-contents-import-customize' );
            var that = this;
            that.loading_step( step );
            this.ajax( 'import_customize', function (){
                that.completed_step( step );
            } );
        },

        toggle_collapse: function(){
            $document .on( 'click', '.demo-contents-collapse-sidebar', function( e ){
                $( '#demo-contents--preview' ).toggleClass('ft-preview-collapse');
            } );
        },

        done: function(){
            console.log( 'All done' );
            $( '.demo-contents--import-now' ).replaceWith( '<a href="'+demo_contents_params.home+'" class="button button-primary">'+demo_contents_params.btn_done_label+'</a>' );
        },

        failed: function(){
            console.log( 'Import failed' );
            $( '.demo-contents--import-now' ).replaceWith( '<span class="button button-secondary">'+demo_contents_params.failed_msg+'</span>' );
        },

        preview: function(){
            var that = this;
            $document .on( 'click', '.demo-contents--preview-theme-btn', function( e ){
                e.preventDefault();
                var btn = $( this );
                var theme = btn.closest('.theme');
                var demoURL         = btn.attr( 'data-demo-url' ) || '';
                var slug            = btn.attr( 'data-theme-slug' ) || '';
                var name            = btn.attr( 'data-name' ) || '';
                var demo_version    = btn.attr( 'data-demo-version' ) || '';
                var demo_name       = btn.attr( 'data-demo-version-name' ) || '';
                var img             = $( '.theme-screenshot' ).html();
                if ( demoURL.indexOf( 'http' ) !== 0 ) {
                    demoURL = 'https://demos.famethemes.com/'+slug+'/';
                }
                $( '#demo-contents--preview' ).remove();
                var previewHtml = template( {
                    name: name,
                    slug: slug,
                    demo_version: demo_version,
                    demo_name:  demo_name,
                    demoURL: demoURL,
                    img: img
                } );
                $( 'body' ).append( previewHtml );
                $( 'body' ).addClass( 'demo-contents-body-viewing' );

                that.preparing_plugins();

                $document.trigger( 'demo_contents_preview_opened' );

            } );

            $document.on( 'click', '.demo-contents-close', function( e ) {
                e.preventDefault();
                $( this ).closest('#demo-contents--preview').remove();
                $( 'body' ).removeClass( 'demo-contents-body-viewing' );
            } );

        },

        init: function(){
            var that = this;

            that.preview();
            that.toggle_collapse();


            $document.on( 'demo_contents_ready', function(){
                that.installPlugins();
            } );

            $document.on( 'demo_contents_plugins_install_completed', function(){
                that.activePlugins();
            } );

            $document.on( 'demo_contents_plugins_active_completed', function(){
                that.import_users();
            } );

            $document.on( 'demo_contents_import_users_completed', function(){
                that.import_categories();
            } );

            $document.on( 'demo_contents_import_categories_completed', function(){
                that.import_tags();
            } );

            $document.on( 'demo_contents_import_tags_completed', function(){
                that.import_taxs();
            } );

            $document.on( 'demo_contents_import_taxs_completed', function(){
                that.import_posts();
            } );

            $document.on( 'demo_contents_import_posts_completed', function(){
                that.import_theme_options();
            } );

            $document.on( 'demo_contents_import_theme_options_completed', function(){
                that.import_widgets();
            } );

            $document.on( 'demo_contents_import_widgets_completed', function(){
                that.import_customize();
            } );

            $document.on( 'demo_contents_import_customize_completed', function(){
                that.done();
            } );

            $document.on( 'demo_contents_ajax_failed', function(){
                that.failed();
            } );


            if ( demo_contents_params.run == 'run' ) {
                $document.trigger( 'demo_contents_ready' );
            }

            // Toggle Heading
            $document.on( 'click', '.demo-contents--step', function( e ){
                e.preventDefault();
                $( '.demo-contents--child-steps', $( this ) ).toggleClass( 'demo-contents--show' );

            } );


            $document.on( 'click', '.demo-contents--import-now', function( e ) {
                e.preventDefault();
                if ( $( this ).hasClass( 'updating-message' ) ) {
                    $( this ).addClass( 'updating-message' );
                    $document.trigger( 'demo_contents_ready' );
                }

            } );

            $document.on( 'demo_contents_preview_opened', function(){
               // $document.trigger( 'demo_contents_import_posts_completed' );
            } );

            //$( '.demo-contents--preview-theme-btn' ).eq( 0 ).click();


        }
    };

    $.fn.ftDemoContent = function() {
        ftDemoContents.init();
    };




}( jQuery ));

jQuery( document ).ready( function( $ ){

    $( document ).ftDemoContent();
    // Active Plugins








});



