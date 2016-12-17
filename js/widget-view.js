/* global Backbone, _, wp, jQuery */
/* exported nextRecentPostsWidget */

var nextRecentPostsWidget = (function( $ ) {
	'use strict';

	var component = {
		widgets: {},
		idBase: '',
		posts: [],
		perPage: 5,
		containerSelector: '',
		renderTemplateId: '',
		defaultInstanceData: {},
		isCustomizePreview: false
	};

	/**
	 * Initialize.
	 *
	 * @param {object} [data] Component data.
	 * @returns {void}
	 */
	component.init = function init( data ) {
		if ( data ) {
			_.extend( component, data );
		}

		if ( component.isCustomizePreview ) {
			component.extendWidgetPartial();
		}

		$( function() {
			wp.api.loadPromise.done( function() {
				component.createModels();
				component.setUpWidgets( document.body );

				// Set up any new widgets appearing in rendered partials.
				if ( 'undefined' !== typeof wp && 'undefined' !== typeof wp.customize && typeof 'undefined' !== wp.customize.selectiveRefresh ) {
					wp.customize.selectiveRefresh.bind( 'partial-content-rendered', function( placement ) {
						component.setUpWidgets( placement.container );
					} );
				}
			} );
		} );
	};

	/**
	 * Extend widget partial.
	 *
	 * @returns {void}
	 */
	component.extendWidgetPartial = function extendWidgetPartial() {
		var WidgetPartial = wp.customize.selectiveRefresh.partialConstructor.widget;

		WidgetPartial.prototype.refresh = (function( originalRefresh ) {
			return function() {
				var partial = this, settingValue;
				if ( component.idBase !== partial.widgetIdParts.idBase || ! component.widgets[ partial.widgetId ] ) {
					return originalRefresh.call( partial );
				}

				// @todo Still need to fetch the rendered data from the server, but we don't need to use the normal renderContent.
				// @todo We need to override the render_callback for these widgets specifically.
				settingValue = _.clone( wp.customize( partial.params.settings[0] ).get() );

				// @todo The mapping of the instance data into the REST item representation is not DRY here.
				settingValue.title = {
					raw: settingValue.title,
					rendered: settingValue.title // Actual rendered value will come with the selective refresh request.
				};
				component.widgets[ partial.widgetId ].model.set( settingValue ); // This is optional.

				return $.Deferred().resolve().promise();
			};
		})( WidgetPartial.prototype.refresh );
	};

	/**
	 * Set up widgets.
	 *
	 * @param {jQuery|Element} [root] Root element to search for widget containers.
	 * @returns {jQuery} Containers found.
	 */
	component.setUpWidgets = function setUpWidgets( root ) {
		var rootContainer = $( root || document.body ), containers;
		containers = rootContainer.find( component.containerSelector );
		if ( rootContainer.is( component.containerSelector ) ) {
			containers = containers.add( component.containerSelector );
		}
		containers.each( function() {
			var widgetContainer, widget, args;
			widgetContainer = $( this );
			args = widgetContainer.data( 'args' );
			if ( ! component.widgets[ args.widget_id ] ) {
				widget = new component.WidgetView( {
					el: widgetContainer.get(),
					args: args,
					item: widgetContainer.data( 'item' )
				} );
				component.widgets[ args.widget_id ] = widget;
			}
		} );
		return containers;
	};

	/**
	 * Create models.
	 *
	 * @returns {void}
	 */
	component.createModels = function createModels() {

		component.WidgetModel = Backbone.Model.extend({
			defaults: _.extend(
				{},
				component.defaultInstanceData
			)
		});

		component.PostsCollection = wp.api.collections.Posts.extend({

			// @todo This can be removed as of WP 4.7.1; see WP Core Trac #39070.
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

		component.WidgetView = Backbone.View.extend({

			// @todo Try http://stackoverflow.com/a/20419831

			initialize: function( options ) {
				var view = this, watchRelatedResourceChanges, item, posts;

				view.args = options.args;
				item = _.clone( options.item );
				posts = item._embedded['wp:post'] || [];
				delete item._links;
				delete item._embedded;
				view.model = new component.WidgetModel( item );
				view.collection = new component.PostsCollection( posts, { parse: true } );
				view.template = wp.template( component.renderTemplateId );
				view.userPromises = {};
				view.mediaPromises = {};

				watchRelatedResourceChanges = function( post ) {
					var userPromise, mediaPromise;
					if ( post.get( 'author' ) && post.getAuthorUser ) {
						userPromise = view.userPromises[ post.get( 'author' ) ];
						if ( ! userPromise ) {
							userPromise = post.getAuthorUser();
							view.userPromises[ post.get( 'author' ) ] = userPromise;
						}
						userPromise.done( function( user ) {
							user.on( 'change', function() {
								if ( view.collection.findWhere( { author: user.id } ) ) {
									view.render();
								}
							} );
						} );
					}
					if ( post.get( 'featured_media' ) && post.getFeaturedMedia ) {
						mediaPromise = view.mediaPromises[ post.get( 'featured_media' ) ];
						if ( ! mediaPromise ) {
							mediaPromise = post.getFeaturedMedia();
							view.mediaPromises[ post.get( 'featured_media' ) ] = mediaPromise;
						}
						mediaPromise.done( function( media ) {
							media.on( 'change', function() {
								if ( view.collection.findWhere( { featured_media: media.id } ) ) {
									view.render();
								}
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
				view.collection.on( 'add', watchRelatedResourceChanges );
				view.collection.each( watchRelatedResourceChanges );
				view.collection.on( 'sync', function() {
					view.render();
				} );

				view.model.on( 'change:number', function( model, number ) {
					view.collection.fetch( {
						data: {
							'per_page': number
						}
					} );
				} );

				view.render();
				view.render = _.debounce( view.render );
			},

			/**
			 * Render view.
			 */
			render: function() {
				var view = this, data;
				view.$el.addClass( 'customize-partial-refreshing' );
				data = _.extend( {}, view.args, view.model.attributes );
				data.posts = view.collection.map( function( model ) {
					var userPromise, mediaPromise, postData;
					postData = _.clone( model.attributes );
					if ( ! ( postData.date instanceof Date ) ) {
						postData.date = new Date( postData.date ); // @todo Timezone naïve.
					}
					if ( model.get( 'author' ) ) {
						userPromise = view.userPromises[ model.get( 'author' ) ];
						if ( userPromise ) {
							userPromise.done( function( user ) {
								postData.author = user;
							} );
						}
					}
					if ( model.get( 'featured_media' ) ) {
						mediaPromise = view.mediaPromises[ model.get( 'featured_media' ) ];
						if ( mediaPromise ) {
							mediaPromise.done( function( media ) {
								postData.featured_media = media;
							} );
						}
					}
					return postData;
				} );

				$.when.apply( null, _.values( view.userPromises ).concat( _.values( view.mediaPromises ) ) ).then( function() {
					view.$el.find( '> :not(.customize-partial-edit-shortcut)' ).remove();
					view.$el.append( $( view.template( data ) ) );
					view.$el.removeClass( 'customize-partial-refreshing' );
					view.trigger( 'rendered' );
				} );
			}
		});
	};

	return component;
}( jQuery ) );
