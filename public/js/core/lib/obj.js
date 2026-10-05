/* Object.prototype.getConstructorName = function() { 
 var funcNameRegex = /function (.{1,})\(/;
 var results = (funcNameRegex).exec((this).constructor.toString());
 return (results && results.length > 1) ? results[1] : "";
 }; */

var obj = {
	className: function (obj) {
		var funcNameRegex = /function (.{1,})\(/;
		var results = (funcNameRegex).exec((obj).constructor.toString());

		return (results && results.length > 1) ? results[1] : "native";
	},
	extend: function (source, extentions) {
		for (var key in extentions) {
			if (source[key]) {
				source[key] = obj.extendValue(source[key], extentions[key]);
			} else {
				source[key] = extentions[key];
			}
		}
		return source;
	},
	extendValue: function (val1, val2) {
		if (typeof val1 === 'object' && typeof val2 === 'object') {
			return obj.extend(val1, val2); // recursion
		} else {
			return val2;
		}

	},
	extendIfNotEmpty: function (source, extentions) {
		if (!extentions || typeof extentions !== 'object') {
			return source;
		}
		for (var key in extentions) {
			source[key] = obj.extendValueIfNotEmpty(source[key], extentions[key]);
		}
		return source;
	},
	// pathItem: every node clones defaultData (method/html/files: null).
	// null/undefined = unset → keep ancestor. '', 0, false overwrite.
	extendValueIfNotEmpty: function (val1, val2) {
		if (val2 === null || val2 === undefined) {
			return val1 !== undefined ? val1 : val2;
		}
		if (obj.isMergeObject(val1) && obj.isMergeObject(val2)) {
			return obj.extendIfNotEmpty(val1, val2);
		}
		return val2;
	},
	isMergeObject: function (v) {
		return v !== null && typeof v === 'object' && Object.getPrototypeOf(v) === Object.prototype;
	},
	empty: function (objOrArray) {
		for (var prop in objOrArray) {
			if (objOrArray.hasOwnProperty(prop)) {
				return false;
			}
		}
		return JSON.stringify(objOrArray) === '{}' || JSON.stringify(objOrArray) === '[]';
	},
	serialize: function (obj, prefix) {
		var str = [], p;
		for (p in obj) {
			if (obj.hasOwnProperty(p)) {
				var k = prefix ? prefix + "[" + p + "]" : p,
						v = obj[p];
				str.push((v !== null && typeof v === "object") ?
						serialize(v, k) :
						encodeURIComponent(k) + "=" + encodeURIComponent(v));
			}
		}
		return str.join("&");
	},
	clone: function (obj) {
		return JSON.parse(JSON.stringify(obj));
	},

	getQueryString: function (params, prefix) {
		if (!params) {
			return '';
		}
		if (obj.empty(params)) {
			return '';
		}
		if (!prefix) {
			prefix = '';
		}
		return prefix + Object.keys(params).map(function (key) {
			return key + '=' + encodeURIComponent(params[key]);
		}).join('&');
	},
	valueByPath: path => { // e.g. returns function 
		var value = window;
		path.split('.').forEach(key => value = value[key]);
		return value;
	}
};