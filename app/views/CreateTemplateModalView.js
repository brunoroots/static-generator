/* global $ */
define(['app', 'backbone', 'core/Modal', 'core/notification'], function (app, Backbone, ModalView, Notification) {
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
        filePath: this.$('input[name=filePath]').val()
      }, {
        success: function (model, response) {
          Notification.success(response.message);
          Backbone.history.loadUrl(Backbone.history.fragment);
          $('#modal_container').hide();
          setTimeout(function(){
        	  $('#file-'+response.id).click();
          }, 1000);
        }
      });
    }
  });
});
