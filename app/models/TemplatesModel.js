define(['app', 'backbone'], function (app, Backbone) {
  return Backbone.Model.extend({
    urlRoot: '/api/extensions/static_generator/templates'
  });
});
