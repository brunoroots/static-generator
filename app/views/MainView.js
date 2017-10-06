/* global $ */
define(['app', 'backbone', 'core/t', 'core/extensions', 'core/notification', './CreateTemplateModalView', './EditTemplatePathModalView', 'ace/ace'],

function (app, Backbone, __t, Extension, Notification, CreateTemplateModalView, EditTemplatePathModalView, ace) {
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
        loadedFiles: this.collection.loadedTemplates.filesAsJSON(),
        directoryStructuresAsUL: this.collection.savedTemplates.directoryStructuresAsUL(),
        config: this.collection.savedTemplates.config()
      };
    },
    afterRender: function () {
      var tpl = this.collection.loadedTemplates.findWhere({selected: true});

      if (tpl) {
        this.editor = ace.edit('editor-' + tpl.get('id'));
        this.editor.session.setMode('ace/mode/twig');
      }
    },
    events: {
      'click .create-new-template': 'createTemplate',
      'click .page-route.save i': 'saveTemplate',
      'click i.delete-file': 'deleteTemplate',
      'click i.edit-file': 'editTemplatePath',
      'click .close-tab': 'unloadTemplate',
      'click .tab, .file': 'loadTemplate',
      'click #generate': 'generateSite'
    },
    generateSite: function () {
      var self = this;
      var outputDirectory = $('#output-dir').val();
      var generationMethod = $('#generation').val();

      this.model.save({
        generationMethod: generationMethod,
        outputDirectory: outputDirectory
      }, {
        success: function (model, response) {
          Notification.success(response.message);
          self.model.unset('generate');
        }
      });
    },
    initSaveBtn: function (saveBtn) {
      this.saveBtn = saveBtn;
    },
    loadTemplate: function (e) {
      var fileId = $(e.target).attr('data-id');
      var tpl = this.collection.loadedTemplates.findWhere({id: fileId});
      var selected = this.collection.loadedTemplates.findWhere({selected: true});
      
      if (selected) {
        selected.set({selected: false});
      }

      if (!tpl) {
        tpl = this.collection.savedTemplates.findWhere({id: fileId});
        this.collection.loadedTemplates.push(tpl);
      }

      this.saveBtn.setEnabled(true);
      
      tpl.set({selected: true});
      this.render();
      
      $('#file-'+fileId).addClass('active');
    },
    unloadTemplate: function (e) {
      var tpl = this.collection.loadedTemplates.findWhere({id: $(e.target).attr('data-id')});

      if (tpl) {
        this.collection.loadedTemplates.remove(tpl);
        tpl = this.collection.loadedTemplates.first();
        var selected = this.collection.loadedTemplates.findWhere({selected: true});

        if (!selected && tpl) {
          tpl.set({selected: true});
        }

        if (this.collection.loadedTemplates.length === 0) {
          this.saveBtn.setEnabled(false);
        }

        this.render();
      }
    },
    createTemplate: function () {
      app.router.openViewInModal(new CreateTemplateModalView({
        model: this.model
      }));
    },
    editTemplatePath: function (e) {
      var tpl = this.collection.savedTemplates.findWhere({id: $(e.target).attr('data-id')});

      app.router.openViewInModal(new EditTemplatePathModalView({
        model: tpl
      }));
    },
    saveTemplate: function () {
      var tpl = this.collection.loadedTemplates.findWhere({selected: true});

      this.model.save({
        id: tpl.get('id'),
        contents: this.editor.getValue(),
        filePath: tpl.get('file')
      }, {
        success: function (model, response) {
          Notification.success(response.message);
          Backbone.history.loadUrl(Backbone.history.fragment);
            // TODO: need to update models,
            // but don't really want to refresh
            // unsaved content in other tabs
        }
      });
    },
    deleteTemplate: function (e) {
      var self = this;
      app.router.openModal({type: 'confirm', text: __t('Are you sure you want to delete this file?'), callback: function () {
        var tpl = self.collection.savedTemplates.findWhere({id: $(e.target).attr('data-id')});

        tpl.destroy({
          success: function (model, response) {
            Notification.success(response.message);
            Backbone.history.loadUrl(Backbone.history.fragment);
              // TODO: need to update models,
              // but don't really want to refresh
              // unsaved content in other tabs
          }
        });
      }});
    }
  });
});
