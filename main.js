define([ 'app', 'backbone', 'core/extensions', './app/models/TemplatesModel',
		'./app/views/MainView', './app/collections/SavedTemplatesCollection',
		'./app/collections/LoadedTemplatesCollection' ],

function(app, Backbone, Extension, TemplatesModel, MainView,
		SavedTemplatesCollection, LoadedTemplatesCollection) {

	var PageView = Extension.BasePageView.extend({
		headerOptions : {
			route : {
				title : 'Static Generator'
			}
		},
		initialize : function() {
			this.setView('#page-content', new MainView({
				model : new TemplatesModel(),
				collection : {
					savedTemplates : new SavedTemplatesCollection(),
					loadedTemplates : new LoadedTemplatesCollection()
				}
			}));
		}
	});

	var Router = Extension.Router.extend({
		routes : {
			'(/)' : function() {
				app.router.v.main.setView('#content', new PageView());
				app.router.v.main.render();
			}
		}
	});

	return {
		id : 'static_generator',
		title : 'Static Generator',
		Router : Router
	}
});
