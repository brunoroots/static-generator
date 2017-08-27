define([ 'app', 'backbone', 'core/extensions', 'core/notification', './CreateEditModalView' ],

function(app, Backbone, Extension, Notification, CreateEditModalView) {

	return Extension.View.extend({
	    template: 'static_generator/app/templates/main',
	    initialize: function () {
	      this.listenTo(this.collection.savedTemplates, 'sync', this.render);
	      this.collection.savedTemplates.fetch();
	    }, 
	    serialize: function () {
	      return {
	        savedPages: this.collection.savedTemplates.pagesAsJSON(),
	        savedIncludes: this.collection.savedTemplates.includesAsJSON(),
	        loadedFiles: this.collection.loadedTemplates.filesAsJSON()
	      };
	    },
	    events: {
	        "click .create-new-template": "createTemplate",
	        "click .page-route.save": "saveTemplate",
	        "click .page-route.delete": "deleteTemplate",
	        "click .close-tab": "unloadTemplate",
	        "click .tab, .file": "loadTemplate",
	        "click #generate": "generateSite"
	    },    
	    generateSite: function(e) {
	    	var self = this;
	    	
			this.model.save({
				'generate': 'manually'
			}, {
				success : function(model, response, options) {
					Notification.success(response.message);
					self.model.unset('generate');
				}
			});
	    },
	    loadTemplate: function(e){
	    	var tpl = this.collection.loadedTemplates.findWhere({id: $(e.target).attr('data-id')}),
				selected = this.collection.loadedTemplates.findWhere({selected: true});

			if(selected) selected.set({selected:false});
	    	
	    	if( ! tpl) {
	    		tpl = this.collection.savedTemplates.findWhere({id: $(e.target).attr('data-id')});
	    		this.collection.loadedTemplates.push(tpl);
	    	}
	    	
	    	tpl.set({selected:true});  
	    	this.render();
	    },    
	    unloadTemplate: function(e) {
	    	var tpl = this.collection.loadedTemplates.findWhere({id: $(e.target).attr('data-id')});

	    	if(tpl) {
	    		this.collection.loadedTemplates.remove(tpl);
	    		tpl = this.collection.loadedTemplates.first();
	    		var selected = this.collection.loadedTemplates.findWhere({selected: true});
	    		
	    		if(!selected && tpl) tpl.set({selected:true}); 
	    		
	    		this.render();
	    	}    	
	    },
	    createTemplate: function(e) {
	    	app.router.openViewInModal(new CreateEditModalView({
	    		model: this.model,
	    		id: $(e.target).attr('data-file-type')	
	    	}) );	        
	    },
	    saveTemplate: function(e) {
	    	var tpl = this.collection.loadedTemplates.findWhere({id: $(e.target).attr('data-id')}),
	    		self = this;

			this.model.save({
				'id': tpl.get('id'),
				'original_route': tpl.get('route'),
				'route' : this.$('input[type=text]').val(),
				'type': tpl.get('type'),
				'contents': this.$('.editor').val()
			}, {
				success : function(model, response, options) {
					Notification.success(response.message);
					Backbone.history.loadUrl(Backbone.history.fragment); // TODO: need to update models, 
																		 // but don't really want to refresh 
																		 // unsaved content in other tabs
				}
			});	        
	    },
	    deleteTemplate: function(e) {
	    	// TODO: need to confirm prior to deletion
	    	var tpl = this.collection.loadedTemplates.findWhere({id: $(e.target).attr('data-id')});

	    	tpl.destroy({
				success : function(model, response, options) {
					Notification.success(response.message);
					Backbone.history.loadUrl(Backbone.history.fragment); // TODO: need to update models, 
																		 // but don't really want to refresh 
																		 // unsaved content in other tabs
				}
			});
//	        app.router.openModal({type: 'confirm', text: '<div>test</div>', callback: function () {
//            window.location.href = app.API_URL + 'auth/logout';
//          }});
	    }
	  });
});