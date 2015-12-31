/* global Backbone, _, _nextRecentPostsWidgetExports, JSON */
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
			var view = this, data, posts, watchAuthorChanges;

			data = JSON.parse( $( view.el ).find( '> script[type="application/json"]' ).text() );
			view.model = new self.WidgetModel( data.instance );
			view.args = data.args;
			posts = _.map(
				data.posts,
				function( post ) {
					/*
					 * Note that map is needed as otherwise an error occurs:
					 * Uncaught TypeError: post.date.toLocaleDateString is not a function
					 */
					return wp.api.models.Post.prototype.parse( post );
				}
			);
			view.collection = new wp.api.collections.Posts( posts );

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

			watchAuthorChanges = function( post ) {
				var author = post.get( 'author' );
				if ( author instanceof wp.api.models.User ) {
					author.on( 'change', function() {
						view.render();
					} );
				}
			};

			view.collection.on( 'change', function() {
				view.render();
			} );
			view.model.on( 'change', function() {
				view.render();
			} );
			view.collection.on( 'add', watchAuthorChanges );
			view.collection.each( watchAuthorChanges );
			view.collection.on( 'sync', function( collection, response, options ) {
				view.model.set( 'has_more', view.model.get( 'number' ) < options.xhr.getResponseHeader( 'X-WP-Total' ) );
				view.render();
			} );

			view.model.on( 'change:number', function() {
				view.collection.fetch();
			} );

			// @todo If we're in the Customizer preview, make sure that this.model gets updated whenever the widget setting gets updated.

			view.render();
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



