/* global Backbone, _, _nextRecentPostsWidgetExports */
/* exported nextRecentPostsWidget */

var nextRecentPostsWidget = (function( $ ) {

	var self = {
		widgets: [],
		postsPerPage: 5,
		containerSelector: '',
		defaultInstanceData: {}
	};

	if ( 'undefined' !== typeof _nextRecentPostsWidgetExports ) {
		_.extend( self, _nextRecentPostsWidgetExports );
	}

	self.boot = function () {
		$( self.containerSelector ).each( function() {
			var widgetContainer, widget;
			widgetContainer = $( this );
			widget = new self.WidgetView( { el: widgetContainer.get() } );
			self.widgets.push( widget );
		} );
	};

	self.WidgetModel = Backbone.Model.extend({
		defaults: _.extend(
			{},
			self.defaultInstanceData,
			{
				has_more: false
			}
		)
	});

	self.WidgetView = Backbone.View.extend({

		// @todo Try http://stackoverflow.com/a/20419831

		initialize: function() {
			var view = this;
			view.model = new self.WidgetModel( $( view.el ).data( 'instance' ) );
			view.args = $( view.el ).data( 'args' );
			view.collection = new wp.api.collections.Posts();
			view.template = wp.template( 'next-recent-posts-widget' );

			view.collection.fetch = function( options ) {
				options = options || {};
				options.data = options.data || {};
				_.extend( options.data, {
					'filter[posts_per_page]': view.model.get( 'number' ),
					'_embed': true
				} );
				return wp.api.collections.Posts.prototype.fetch.call( this, options );
			};

			view.authors = new wp.api.collections.Users();
			view.authors.on( 'change', function() {
				view.render();
			} );
			view.collection.on( 'change', function() {
				view.render();
			} );
			view.model.on( 'change', function() {
				view.render();
			} );
			view.collection.on( 'sync', function( collection, response, options ) {
				view.model.set( 'has_more', view.model.get( 'number' ) < options.xhr.getResponseHeader( 'X-WP-Total' ) );
				collection.each( function( post ) {
					if ( post.get( 'author' ) instanceof wp.api.models.User ) {
						view.authors.add( post.get( 'author' ) );
					}
				} );
				view.render();
			} );

			view.collection.fetch();
			view.model.on( 'change:number', function() {
				view.collection.fetch();
			} );

			// @todo If we're in the Customizer preview, make sure that this.model gets updated whenever the widget setting gets updated.

			view.render = _.debounce( view.render );
		},

		events: {
			'click .load-more': 'loadMore'
		},

		loadMore: function () {
			var view = this;

			// Restore focus on the load-more button. (This wouldn't be necessary in React.)
			view.once( 'rendered', function() {
				view.$el.find( '.load-more' ).focus();
			} );
			view.model.set( 'number', view.model.get( 'number' ) + self.postsPerPage );
		},

		/**
		 * Render view.
		 */
		render: function() {
			var view = this, data;
			data = _.extend( {}, view.args, view.model.attributes );
			data.posts = view.collection.map( function( model ) {
				return model.attributes;
			} );
			view.$el.html( view.template( data ) );
			view.trigger( 'rendered' );
		}

	});

	$(function() {
		self.boot();
	});

	return self;
}( jQuery ));



