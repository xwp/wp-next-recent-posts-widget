/* global Backbone, _ */
/* exported nextRecentPostsWidget */

var nextRecentPostsWidget = (function( $ ) {

	var component = {
		widgets: {},
		idBase: '',
		postsPerPage: 5,
		containerSelector: '',
		renderTemplateId: '',
		defaultInstanceData: {},
		isCustomizePreview: false
	};

	component.init = function init( data ) {
		if ( data ) {
			_.extend( component, data );
		}

		// @todo Extend to disable self.WidgetPartial

		if ( component.isCustomizePreview ) {
			component.extendWidgetPartial();
		}

		$( function() {
			wp.api.loadPromise.done( function() {
				component.createModels();
				component.setUpWidgets( document.body );

				// @todo The widget instance should be fetched from the server. Selective refresh can return just the rendered data, or we can override to fetch the instance from the rest api.
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
				settingValue = _.clone( wp.customize( partial.params.settings[0] ).get() );
				component.widgets[ partial.widgetId ].model.set( settingValue );

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
			var widgetContainer, widget, widgetId;
			widgetContainer = $( this );
			widgetId = widgetContainer.data( 'embedded' ).args.widget_id;
			if ( ! component.widgets[ widgetId ] ) {
				widget = new component.WidgetView( { el: widgetContainer.get() } );
				component.widgets[ widgetId ] = widget;
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
				component.defaultInstanceData,
				{
					has_more: false
				}
			)
		});

		if ( component.isCustomizePreview ) {
			component.previewPostModel();
		}

		component.PostsCollection = wp.api.collections.Posts.extend({

			// @todo Why is this necessary? If not supplied, then the Model is not instantiated for each.
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

			initialize: function() {
				var view = this, data, watchAuthorChanges;

				data = $( view.el ).data( 'embedded' ) || {};
				view.model = new component.WidgetModel( data.instance );
				view.args = data.args;
				view.collection = new component.PostsCollection( data.posts, { parse: true } );
				view.template = wp.template( component.renderTemplateId );
				view.userPromises = {};

				watchAuthorChanges = function( post ) {
					var author = post.get( 'author' );
					if ( author && post.getAuthorUser ) { // @todo Why wouldn't it be defined?
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
							'per_page': number
						}
					} );
				} );

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
				view.model.set( 'number', view.model.get( 'number' ) + component.postsPerPage );
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
					view.$el.find( '> :not(.customize-partial-edit-shortcut)' ).remove();
					view.$el.append( $( view.template( data ) ) );
					view.trigger( 'rendered' );
				} );
			}

		});
	};

	/**
	 * Hook up post model in Backbone with post setting in customizer.
	 *
	 * @todo Prevent selective refresh requests or piggy-back on selective refresh requests to obtain newly-rendered values.
	 *
	 * @returns {void}
	 */
	component.previewPostModel = function previewPostModel() {
		var originalInitialize = wp.api.models.Post.prototype.initialize;

		wp.api.models.Post.prototype.initialize = function( attributes, options ) {
			var model = this, settingId;
			originalInitialize.call( model, attributes, options );

			settingId = 'post[' + model.get( 'type' ) + '][' + String( model.id ) + ']';
			wp.customize( settingId, function( postSetting ) {
				var updateModel = function( postData ) {
					model.set( {
						title: {
							raw: postData.post_title,
							rendered: postData.post_title // Raw value used temporarily until new value fetched from server.
						}
					} );
				};
				updateModel( postSetting.get() );
				postSetting.bind( updateModel );
			} );
		};
	};

	return component;
}( jQuery ) );
