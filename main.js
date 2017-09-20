define([ 'app', 'backbone', 'core/extensions', './app/models/TemplatesModel',
		'./app/views/MainView', './app/collections/SavedTemplatesCollection',
		'./app/collections/LoadedTemplatesCollection', 'core/widgets/widgets',
		'core/t' ],

function(app, Backbone, Extension, TemplatesModel, MainView,
		SavedTemplatesCollection, LoadedTemplatesCollection, Widgets, __t) {

	var PageView = Extension.BasePageView.extend({
		headerOptions : {
			route : {
				title : 'Static Generator'
			}
		},

		leftToolbar : function() {

			return this.widgets;
		},
		initialize : function() {
			var self = this;

			this.mainView = new MainView({
				model : new TemplatesModel(),
				collection : {
					savedTemplates : new SavedTemplatesCollection(),
					loadedTemplates : new LoadedTemplatesCollection()
				}
			});
						
			this.addNewBtn = new Widgets.ButtonWidget({
					widgetOptions : {
						buttonId : 'addBtn',
						iconClass : 'add',
						buttonClass : 'primary',
						buttonText : __t('new_file')
					},
			        onClick: function(e){ 
			        	self.mainView.createTemplate(e); 
			        }
			});
			this.saveBtn = new Widgets.SaveWidget({
					widgetOptions : {
						basicSave : this.headerOptions.basicSave
					},
					enabled : false,
			        onClick: function(e){ 
			        	self.mainView.saveTemplate(e); 
			        }
			});
			
			this.mainView.initSaveBtn(this.saveBtn);
				
			this.widgets = [];
			this.widgets.push(this.addNewBtn);
			this.widgets.push(this.saveBtn);
			
			this.setView('#page-content', this.mainView);
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
