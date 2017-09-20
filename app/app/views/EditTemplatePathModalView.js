define([ 'app', 'backbone', 'core/Modal', 'core/notification' ],

function(app, Backbone, ModalView, Notification) {

	return ModalView.extend({
		prefix : 'customs/extensions/',
		template : 'static_generator/app/templates/editTemplatePathModalView',
		events : {
			"click .save" : "save",
			"click .cancel": "cancel"
		},
		initialize : function() {
			console.log(this.model.attributes.file);
		},
		serialize : function() {
			return {
				tpl : this.model.attributes
			};
		},
		cancel: function(e) {
		      this.container.close();
		},
		save : function(e) {
			this.model.save({
				'filePath' : this.$('input[name=filePath]').val(),
				'type' : this.model.attributes.type,
				'contents': this.model.attributes.contents
			}, {
				success : function(model, response, options) {
					Notification.success(response.message);
					Backbone.history.loadUrl(Backbone.history.fragment);
					$('#modal_container').hide(); // TODO: need an api call
					// for this; this.close()
					// only closes the content;
					// this.container undefined
				}
			});
		}
	});
});