/***
The MIT License (MIT)

Copyright (c) luyilin <luyilin12@gmail.com> (https://github.com/luyilin)

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:
***/

const defaultColors = {
  keyColor: '#9CCC65',
  numberColor: 'lightskyblue',
  stringColor: 'lightcoral',
  trueColor: 'lightseagreen',
  falseColor: '#f66578',
  nullColor: 'cornflowerblue'
}

const entityMap = {
  '&': '&amp;',
  '<': '&lt;',
  '>': '&gt;',
  '"': '&quot;',
  "'": '&#39;',
  '`': '&#x60;',
  '=': '&#x3D;'
};

function escapeHtml (html){
  return String(html).replace(/[&<>"'`=]/g, function (s) {
      return entityMap[s];
  });
}

function highlightJson (json, colorOptions = {}) {
  const valueType = typeof json
  if (valueType !== 'string') {
    json = JSON.stringify(json, null, 2) || valueType
  }
  let colors = Object.assign({}, defaultColors, colorOptions)
  json = json.replace(/&/g, '&').replace(/</g, '<').replace(/>/g, '>')
  return json.replace(/("(\\u[a-zA-Z0-9]{4}|\\[^u]|[^\\"])*"(\s*:)?|\b(true|false|null)\b|-?\d+(?:\.\d*)?(?:[eE][+]?\d+)?)/g, (match) => {
    let color = colors.numberColor
    let style = ''
    if (/^"/.test(match)) {
      if (/:$/.test(match)) {
        color = colors.keyColor
      } else {
        color = colors.stringColor;
        match = '"' + escapeHtml(match.substr(1, match.length - 2)) + '"';
        style = 'word-wrap:break-word;white-space:pre-wrap;';
      }
    } else {
      color = /true/.test(match)
        ? colors.trueColor
        : /false/.test(match)
          ? colors.falseColor
          : /null/.test(match)
            ? colors.nullColor
            : color
    }
    return `<span style="${style}color:${color}">${match}</span>`
  })
}

document.addEventListener("DOMContentLoaded", () => {
	let jsonElement = document.getElementById("siteseo_highlighter");
	if(jsonElement && jsonElement.textContent){
		try{
			jsonElement.innerHTML = highlightJson(JSON.parse(jsonElement.textContent));
		} catch(e){
			console.log("Error parsing JSON for highlighting:", e);
		}
	}
});
