/* global CoursePress, _ */

(function() {
    'use strict';

    CoursePress.Define( 'ExtensionsSettings', function( $, doc, win ) {
        var Extension, Post;

        Extension = CoursePress.View.extend({
            type: false,
            initialize: function(model, options) {
                this.model = model;
                _.extend( this, options );
                this.render();
            },
            render: function() {
                CoursePress.View.prototype.render.apply( this );

                this.$el.appendTo( '#extension-' + this.type );

                return this;
            },
            updateModel: function() {

            }
        });

        Post = new CoursePress.Request();

        return CoursePress.View.extend({
            template_id: 'coursepress-extensions-setting-tpl',
            el: $( '#coursepress-setting-extensions' ),
            extensions: {},
            setting: false,
            initialize: function( extensions, settingObject ) {
                this.model = {extensions: extensions};
                this.setting = settingObject;

                this.render();
            },
            render: function() {
                CoursePress.View.prototype.render.apply( this );

                _.each( this.model.extensions, function( ext ) {
                    this.showExtension(ext);
                }, this );
            },
            updateModel: function(ev) {
                var target = this.$(ev.currentTarget),
                    value = target.val(),
                    is_checked = target.is(':checked');

                this.model.extensions = _.without( this.model.extensions, value );

                if ( is_checked ) {
                    this.model.extensions.push( value );
                    this.showExtension(value);
                } else {
                    this.hideExtension(value);
                }
            },
            showExtension: function( value ) {
                if ( ! this.extensions[value] ) {
                    var tpl = $('#coursepress-' + value + '-tpl' );

                    if ( ! tpl.length ) {
                        return;
                    }

                    // Initialize extension settings
                    this.extensions[value] = new Extension({}, {
                        template_id: 'coursepress-' + value + '-tpl',
                        type: value,
                        controller: this
                    });
                }
            },
            hideExtension: function( value ) {
                if ( this.extensions[value] ) {
                    this.extensions[value].remove();
                    delete this.extensions[value];
                }
            },
            getModel: function() {
                var extensions = this.model.extensions;

                // MP and woo should not be activated at the same time
                if ( _.contains( extensions, 'marketpress') &&
                    _.contains( extensions, 'woocommerce' ) ) {
                    var popup = new CoursePress.PopUp({
                        type: 'error',
                        message: win._coursepress.messages.no_mp_woo
                    });
                    return false;

                } else if ( _.contains( extensions, 'marketpress' ) ) {
                    // Extract and activate MP
                    Post.set( 'action', 'activate_marketpress' );
                    Post.off( 'coursepress:success_activate_marketpress' );
                    Post.on( 'coursepress:success_activate_marketpress', this.MPActivated, this );
                    Post.save();
                } else if ( _.contains( extensions, 'woocommerce' ) ) {
                    // Check WooCommerce and activae woo
                }

                return extensions;
            },
            MPActivated: function() {}
        });
    });

})();