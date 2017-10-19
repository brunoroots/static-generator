/* global $ */
define(['app', 'backbone', 'core/Modal', 'core/notification', 'ace/ace'], function (app, Backbone, ModalView, Notification, ace) {
  return ModalView.extend({
    prefix: 'customs/extensions/',
    template: 'static_generator/app/templates/createTemplateModalView',
    events: {
      'click .save': 'save',
      'click .cancel': 'cancel'
    },
    cancel: function () {
      this.container.close();
    },
    save: function () {
      this.model.save({
          id: null,
          contents: null,
          filePath: this.$('input[name=filePath]').val()
      }, {
        success: function (model, response) {
        	
          Notification.success(response.message);
          Backbone.history.loadUrl(Backbone.history.fragment);
          $('#modal_container').hide();
          setTimeout(function(){
        	  $('#file-'+response.id).click();
              var editor = ace.edit('editor-'+response.id);
              editor.focus(); 
              var n = editor.getSession().getValue().split("\n").length;  
              editor.gotoLine(n);
          }, 2000);
        }
      });
    }
  });
});
