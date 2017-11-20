require.config({
  paths: {
    'ace/mode/twig': '../customs/extensions/static-generator/node_modules/ace-builds/src-min/mode-twig',
    'ace/mode/twig_highlight_rules': '../customs/extensions/static-generator/node_modules/ace-builds/src-min/mode-twig'
  }
});

define('ace/mode/directus', function (require, exports, module) {
  var oop = require('ace/lib/oop');
  var TwigMode = require('ace/mode/twig').Mode;
  var DirectusHighlightRules = require('ace/mode/directus_highlight_rules').DirectusHighlightRules;

  var Mode = function () {
    this.HighlightRules = DirectusHighlightRules;
  };
  oop.inherits(Mode, TwigMode);
  exports.Mode = Mode;
});

define('ace/mode/directus_highlight_rules', function (require, exports, module) {
  var oop = require('ace/lib/oop');

  var TwigHighlightRules = require('ace/mode/twig_highlight_rules').TwigHighlightRules;

  var DirectusHighlightRules = function () {
    this.$rules = new TwigHighlightRules().getRules();
    console.log(this.$rules);

    /**
     * Add Directus intro comment regex into start rules section
     */
    this.$rules.start.unshift({
      token: 'directus',
      regex: '<!---(?:[^\\\\]|\\\\.)*?--->'
    });
  };

  oop.inherits(DirectusHighlightRules, TwigHighlightRules);

  exports.DirectusHighlightRules = DirectusHighlightRules;
});
