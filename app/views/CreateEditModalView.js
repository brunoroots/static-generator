define([ 'app', 'backbone', 'core/Modal', 'core/notification' ],

function(app, Backbone, ModalView, Notification) {

	return ModalView.extend({
		prefix : 'customs/extensions/',
		template : 'static_generator/app/templates/createEditModalView',
		events : {
			"click .save" : "save"
		},
		initialize : function() {
		},
		save : function(e) {
			this.model.save({
				'route' : this.$('input[name=route]').val(),
				'type' : this.id
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