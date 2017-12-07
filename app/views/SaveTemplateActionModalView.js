/* global $ */
define(['app', 'backbone', 'core/Modal', 'core/notification', 'ace/ace'], function (app, Backbone, ModalView, Notification, ace) {
  return ModalView.extend({
	msgTimeout: 3000,
    prefix: 'customs/extensions/',
    template: 'static-generator/app/templates/saveTemplateActionModalView',
    constructor: function() {
    	this.mainView = arguments[0].mainView;
    	this.tpl = arguments[0].tpl;
		ModalView.prototype.constructor.apply(this, arguments);
    },
    events: {
      'click .save': 'save',
      'click .no': 'noSave',
      'click .cancel': 'cancel'
    },
    cancel: function () {
        this.container.close();
    },
    save: function () {
    	this.mainView.saveTemplate();
        this.container.close();
  	    this.mainView.collection.loadedTemplates.remove(this.tpl);
        tpl = this.mainView.collection.loadedTemplates.first();
        var selected = this.mainView.collection.loadedTemplates.findWhere({selected: true});

        if (!selected && tpl) {
          tpl.set({selected: true});
        }
    },
    noSave: function () {    	
        this.tpl.set('modified', false);
        this.mainView.saveBtn.setEnabled(false);
        
        this.mainView.collection.loadedTemplates.remove(this.tpl);
        tpl = this.mainView.collection.loadedTemplates.first();
        var selected = this.mainView.collection.loadedTemplates.findWhere({selected: true});

        if (!selected && tpl) {
          tpl.set({selected: true});
        }
        
        this.mainView.render();
        this.container.close();
    }
  });
});
