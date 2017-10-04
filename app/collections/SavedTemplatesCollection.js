define([ 'app', 'backbone', '../models/TemplatesModel' ],

function(app, Backbone, TemplatesModel) {

	return Backbone.Collection.extend({
		url : '/api/extensions/static_generator/templates',
		model : TemplatesModel,
		pagesAsJSON : function() {
			return new Backbone.Collection(this.where({
				type : 'page'
			})).toJSON();
		},
		includesAsJSON : function() {
			return new Backbone.Collection(this.where({
				type : 'include'
			})).toJSON();
		},
		directoryStructuresAsUL : function() {
			var res = new Backbone.Collection(this.where({
				hasDirectoryTree: true
			})).toJSON();
			return res;
		}
	});

});