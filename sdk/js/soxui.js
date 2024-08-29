module.exports = {
	d: [
		"\r\n"
		, '#'
		, function() {
			var obj = {};

			for (var i = 0;i < arguments.length; i++) {
				if (typeof arguments[i] != 'object') {
					obj[arguments[i]] = arguments[++i] || '';
				} else {
					for (var j in arguments[i]) {
						obj[j] = arguments[i][j];
					}
				}
			}

			return obj;
		}
		, '\''
		, '"'
		, '\\'
	]
	, data: function(d) {
		if (typeof(d) == 'string') {
			var data = {};

			if (d != '') {
				if (d.indexOf(':') == -1) {
					data = JSON.parse(Buffer.from(d, 'base64').toString('utf8'));
				} else {
					d = d.split(';');

					for (var k in d) {
						if (d[k] === '') continue;

						var j = d[k].indexOf(':');

						if (j != -1) {
							data[d[k].substr(0,j)] = d[k].substr(j + 1);
						} else {
							data[d[k]] = '';
						}
					}
				}
			}

			return data;
		} else {
			if (d === null || d === undefined) return {};

			return Buffer.from(JSON.stringify(d)).toString('base64');
		}
	}
	, tpl: (function() {
		this.version = '1.0.0';
		this.modules = [];

		var config = {
			tag_open: '{{',
			tag_shut: '}}'
		};

		var tool = {
			exp: function(str){
				return new RegExp(str, 'g');
			},
			query: function(type, _, __) {
				var types = ['#([\\s\\S])+?', '([^{#}])*?'][type || 0];

				return this.exp((_ || '') + config.tag_open + types + config.tag_shut + (__ || ''));
			},
			escape: function(html) {
				return String(html || '')
				.replace(/&(?!#?[a-zA-Z0-9]+;)/g, '&amp;')
				.replace(/</g, '&lt;')
				.replace(/>/g, '&gt;')
				.replace(/'/g, '&#39;')
				.replace(/"/g, '&quot;');
			},
			error: function(e, log) {
				var error = 'soxtpl Error：';
				typeof(console) == 'object' && console.error(error+e+'\n'+(log || ''));
				return error + e;
			}
		};

		this.create = function(tpl) {
			var js_s = tool.exp('^' + config.tag_open + '#', '');
			var js_e = tool.exp(config.tag_shut + '$', '');

			tpl = tpl.replace(/\r|\n/g, ' ') // /\s+|\r|\t|\n/g
			.replace(tool.exp(config.tag_open + '#'), config.tag_open + '# ')
			.replace(tool.exp(config.tag_shut + '}'), '} ' + config.tag_shut)
			.replace(/\\/g, '\\\\').replace(/(?="|')/g, '\\')
			.replace(tool.query(), function(str) {
				str = str.replace(js_s, '').replace(js_e, '');
				return '";' + str.replace(/\\/g, '') + ';view+="';
			})
			.replace(tool.query(1), function(str){
				var start = '"+(';

				if (str.replace(/\s/g, '') === config.tag_open + config.tag_shut) {
					return '';
				}

				str = str.replace(tool.exp(config.tag_open + '|' + config.tag_shut), '');

				if (/^=/.test(str)) {
					str = str.replace(/^=/, '');
					start = '"+_escape_(';
				}

				return start + str.replace(/\\/g, '') + ')+"';
			});

			tpl = '"use strict";var view = "' + tpl + '";return view;';

			try {
				return new Function('d, _escape_', tpl);
			} catch(e) {
				return tool.error(e, tpl);
			}
		};

		this.render = function(tpl, data, callback) {
			if (!data) data = {};

			tpl = tpl(data, tool.escape);

			if (!callback) return tpl;

			callback(tpl);
		};

		this.config = function(options) {
			options = options || {};

			for (var i in options) {
				config[i] = options[i];
			}
		};

		return this;
	})()
	, tel_mask: function(str) {
		var pos = str.lastIndexOf('-');

		if (pos === -1) return str;

		var cnt = str.slice(0, pos);
		var tel = str.slice(pos + 1);

		switch (tel.length) {
			case 8:
				return cnt + '-' + tel.slice(0, 2) + '****' + tel.slice(-2);
				break;
			case 9:
				return cnt + '-' + tel.slice(0, 2) + '****' + tel.slice(-3);
				break
			case 11:
				return cnt + '-' + tel.slice(0, 3) + '****' + tel.slice(-4);
				break;
			default:
				return str;
				break
		}
	}
};