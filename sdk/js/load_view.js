var fs = require('fs');

var soxui = require('./soxui.js');

var args = process.argv.slice(2);

var html = fs.readFileSync('./style/index' + (args[0].substr(0, 1) === '/' ? '' : '/template/') + args[0] + '.html', 'utf8');

var d = args[1] ? soxui.data(args[1]) : {};

var tpl = soxui.tpl.create(html.replace(/\{\{\{\{[\s\S]*?\:/g, '').replace(/\}\}\}\}/g, ''));

global.soxui = soxui;

console.log(soxui.tpl.render(tpl, d));