/* global $ */
define(['app', 'backbone', 'core/Modal', 'core/notification'],

function (app, Backbone, ModalView, Notification) {
  return ModalView.extend({
    prefix: 'customs/extensions/',
    template: 'static_generator/app/templates/editTemplatePathModalView',
    afterRender: function () {
    	$('input[name=filePath]').focus();
    },
    events: {
      'click .save': 'save',
      'click .cancel': 'cancel'
    },
    serialize: function () {
      return {
        tpl: this.model.attributes
      };
    },
    cancel: function () {
      this.container.close();
    },
    save: function () {
      this.model.save({
        filePath: this.$('input[name=filePath]').val(),
        contents: this.model.attributes.contents
      }, {
        success: function (model, response) {
          Notification.success(response.message);
          Backbone.history.loadUrl(Backbone.history.fragment);
          $('#modal_container').hide();
        }
      });
    }
  });
});
