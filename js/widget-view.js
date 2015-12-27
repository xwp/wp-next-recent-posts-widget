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
		defaults: self.defaultInstanceData
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
					'filter[posts_per_page]': view.model.get( 'number' )
				} );
				return wp.api.collections.Posts.prototype.fetch.call( this, options );
			};

			view.hasMore = null;

			view.collection.on( 'sync', function( collection, response, options ) {
				view.hasMore = ( view.model.get( 'number' ) < options.xhr.getResponseHeader( 'X-WP-Total' ) );
				view.render();
			} );

			view.collection.fetch();
			view.model.on( 'change:number', function() {
				view.collection.fetch();
			} );
			view.model.on( 'change', function() {
				view.render();
			} );

			// @todo If we're in the Customizer preview, make sure that this.model gets updated whenever the widget setting gets updated.
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
			var view = this, data = {};
			_.extend( data, view.args );
			_.extend( data, view.model.attributes );
			data.posts = view.collection.map( function( model ) {
				return model.attributes;
			} );
			data.has_more = view.hasMore;

			view.$el.html( view.template( data ) );
			view.trigger( 'rendered' );
		}

	});

	$(function() {
		self.boot();
	});

	return self;
}( jQuery ));



