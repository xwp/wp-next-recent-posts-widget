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

	self.boot = function() {
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

	self.PostsCollection = wp.api.collections.Posts.extend({

		// @todo The following shouldn't be needed.
		model: wp.api.models.Post,

		defaultQueryParamsData: {
			_embed: true,
			order: 'desc',
			orderby: 'date'
		},

		/**
		 * Compare two posts.
		 *
		 * @param {Backbone.Model} a
		 * @param {Backbone.Model} b
		 * @returns {number}
		 */
		comparator: function( a, b ) {
			if ( a.get( 'date' ) === b.get( 'date' )  ) {
				return 0;
			}
			return a.get( 'date' ) < b.get( 'date' ) ? 1 : -1;
		},

		/**
		 * Fetch.
		 *
		 * @param {object} [options]
		 * @param {object} [options.data]
		 * @returns {*}
		 */
		fetch: function( options ) {
			options = options || {};
			options.data = options.data || {};
			_.extend( options.data, this.defaultQueryParamsData );
			return wp.api.collections.Posts.prototype.fetch.call( this, options );
		}
	});

	self.WidgetView = Backbone.View.extend({

		// @todo Try http://stackoverflow.com/a/20419831

		initialize: function() {
			var view = this, data, watchAuthorChanges;

			data = JSON.parse( $( view.el ).find( '> script[type="application/json"]' ).text() );
			view.model = new self.WidgetModel( data.instance );
			view.args = data.args;
			view.collection = new self.PostsCollection( data.posts, { parse: true } );
			view.template = wp.template( 'next-recent-posts-widget' );
			view.userPromises = {};

			watchAuthorChanges = function( post ) {
				var author = post.get( 'author' );
				if ( author ) {
					post.getAuthorUser().done( function( user ) {
						user.on( 'change', function() {
							view.render();
						} );
					} );
				}
			};

			view.collection.on( 'change', function() {
				var collection = this;
				collection.sort();
				view.render();
			} );
			view.model.on( 'change', function() {
				view.render();
			} );
			view.collection.on( 'add', watchAuthorChanges );
			view.collection.each( watchAuthorChanges );
			view.collection.on( 'sync', function( collection ) {
				view.model.set( 'has_more', collection.hasMore() );
				view.render();
			} );

			view.model.on( 'change:number', function( model, number ) {
				view.collection.fetch( {
					data: {
						'filter[posts_per_page]': number
					}
				} );
			} );

			// @todo If we're in the Customizer preview, make sure that this.model gets updated whenever the widget setting gets updated.

			if ( ! data.posts ) {
				view.collection.fetch();
			}

			view.render();
			view.render = _.debounce( view.render );
		},

		events: {
			'click .load-more': 'loadMore'
		},

		loadMore: function() {
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
				var authorPromise;
				var postData = _.clone( model.attributes );
				if ( ! ( postData.date instanceof Date ) ) {
					postData.date = new Date( postData.date );
				}
				if ( model.get( 'author' ) && model.getAuthorUser ) {
					authorPromise = view.userPromises[ model.get( 'author' ) ];
					if ( ! authorPromise ) {
						authorPromise = model.getAuthorUser();
						view.userPromises[ model.get( 'author' ) ] = authorPromise;
					}
					authorPromise.done( function( user ) {
						postData.author = user;
					} );
				}
				return postData;
			} );

			$.when.apply( null, _.values( view.userPromises ) ).then( function() {
				view.$el.html( view.template( data ) );
				view.trigger( 'rendered' );
			} );
		}

	});

	$(function() {
		self.boot();
	});

	return self;
}( jQuery ) );
