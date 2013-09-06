(function (global) {
	var Jsonary = global.Jsonary;

	function copyValue(value) {
		return (typeof value == "object") ? JSON.parse(JSON.stringify(value)) : value;
	}
	var randomChars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
	function randomId(length) {
		length = length || 10;
		var result = "";
		while (result.length < length) {
			result += randomChars.charAt(Math.floor(Math.random()*randomChars.length));
		}
		return result;
	}

	function htmlEscapeSingleQuote (str) {
		return str.replace("&", "&amp;").replace("<", "&lt;").replace(">", "&gt;").replace("'", "&#39;");
	}

	var prefixPrefix = "Jsonary";
	var prefixCounter = 0;

	var componentNames = {
		ADD_REMOVE: "ADD_REMOVE",
		TYPE_SELECTOR: "TYPE_SELECTOR",
		RENDERER: "DATA_RENDERER",
		add: function (newName, beforeName) {
			if (this[newName] != undefined) {
				if (beforeName !== undefined) {
					if (componentList.indexOf(newName) != -1) {
						componentList.splice(componentList.indexOf(newName), 1);
					}
				} else {
					return;
				}
			}
			this[newName] = newName;
			if (typeof beforeName == 'number') {
				var index = Math.max(0, Math.min(componentList.length - 1, Math.round(beforeName)));
				componentList.splice(index, 0, this[newName]);
			} else if (componentList.indexOf(beforeName) != -1) {
				componentList.splice(componentList.indexOf(beforeName), 0, this[newName]);
			} else if (componentList.indexOf(componentNames[beforeName]) != -1) {
				componentList.splice(componentList.indexOf(componentNames[beforeName]), 0, this[newName]);
			} else {
				componentList.splice(componentList.length - 1, 0, this[newName]);
			}
		}
	};
	var componentList = [componentNames.ADD_REMOVE, componentNames.TYPE_SELECTOR, componentNames.RENDERER];
	
	var contextIdCounter = 0;
	function RenderContext(elementIdPrefix) {
		this.uniqueId = contextIdCounter++;
		var thisContext = this;
		this.elementLookup = {};

		if (elementIdPrefix == undefined) {
			elementIdPrefix = prefixPrefix + "." + (prefixCounter++) + randomId(4) + ".";
		}
		var elementIdCounter = 0;
		this.getElementId = function () {
			return elementIdPrefix + (elementIdCounter++);
		};

		var renderDepth = 0;
		this.enhancementContexts = {};
		this.enhancementActions = {};
		this.enhancementInputs = {};

		if (typeof document != 'undefined') {
			Jsonary.registerChangeListener(function (patch, document) {
				patch.each(function (index, operation) {
					var dataObjects = document.affectedData(operation);
					for (var i = 0; i < dataObjects.length; i++) {
						thisContext.update(dataObjects[i], operation);
					}
				});
			});
			Jsonary.registerSchemaChangeListener(function (dataObjects) {
				var elementIdLookup = {};
				for (var i = 0; i < dataObjects.length; i++) {
					var data = dataObjects[i];
					var uniqueId = data.uniqueId;
					var elementIds = thisContext.elementLookup[uniqueId];
					if (elementIds == undefined || elementIds.length == 0) {
						return;
					}
					elementIdLookup[uniqueId] = elementIds.slice(0);
				}
				for (var j = 0; j < dataObjects.length; j++) {
					var data = dataObjects[j];
					var uniqueId = data.uniqueId;
					var elementIds = elementIdLookup[uniqueId];
					for (var i = 0; i < elementIds.length; i++) {
						var element = document.getElementById(elementIds[i]);
						if (element == undefined) {
							continue;
						}
						var prevContext = element.jsonaryContext;
						var prevUiState = copyValue(this.uiStartingState);
						var renderer = selectRenderer(data, prevUiState, prevContext.usedComponents);
						if (renderer.uniqueId == prevContext.renderer.uniqueId) {
							renderer.render(element, data, prevContext);
						} else {
							prevContext.baseContext.render(element, data, prevContext.label, prevUiState);
						}
					}
				}
			});
		}
		this.rootContext = this;
		this.subContexts = {};
		this.oldSubContexts = {};
	}
	RenderContext.prototype = {
		usedComponents: [],
		rootContext: null,
		baseContext: null,
		labelForData: function (data) {
			if (this.data && data.document.isDefinitive) {
				var selfLink = data.getLink('self');
				// Use "self" link for better persistence when data changes
				var dataUrl = selfLink ? selfLink.href : data.referenceUrl();
				if (dataUrl) {
					var baseUrl = this.data.referenceUrl() || this.data.resolveUrl('');
					var truncate = 0;
					while (dataUrl.substring(0, baseUrl.length - truncate) != baseUrl.substring(0, baseUrl.length - truncate)) {
						truncate++;
					}
					var remainder = dataUrl.substring(baseUrl.length - truncate);
					if (truncate) {
						return truncate + "!" + remainder;
					} else {
						return "!" + remainder;
					}
				}
			} else if (this.data && this.data.document == data.document) {
				var basePointer = this.data.pointerPath();
				var dataPointer = data.pointerPath();
				var truncate = 0;
				while (dataPointer.substring(0, basePointer.length - truncate) != basePointer.substring(0, basePointer.length - truncate)) {
					truncate++;
				}
				var remainder = dataPointer.substring(basePointer.length - truncate);
				if (truncate) {
					return truncate + "!" + remainder;
				} else {
					return "!" + remainder;
				}
			}
			if (this.renderer) {
				// This is bad because it makes the UI state less transferable
				Jsonary.log(Jsonary.logLevel.WARNING, "No label supplied for data in renderer " + JSON.stringify(this.renderer.name));
			}
			
			return "$" + data.uniqueId;
		},
		subContext: function (label, uiState) {
 			if (Jsonary.isData(label)) {
				label = this.labelForData(label);
			}
			uiState = uiState || {};
			var subContext = this.getSubContext(false, this.data, label, uiState);
			subContext.renderer = this.renderer;
			if (!subContext.uiState) {
				subContext.loadState(subContext.uiStartingState);
			}
			return subContext;
		},
		subContextSavedStates: {},
		saveState: function () {
			var subStates = {};
			for (var key in this.subContexts) {
				subStates[key] = this.subContexts[key].saveState();
			}
			for (var key in this.oldSubContexts) {
				subStates[key] = this.oldSubContexts[key].saveState();
			}
			
			var saveStateFunction = this.renderer ? this.renderer.saveState : Renderer.prototype.saveState;
			return saveStateFunction.call(this.renderer, this.uiState, subStates, this.data);
		},
		loadState: function (savedState) {
			var loadStateFunction = this.renderer ? this.renderer.loadState : Renderer.prototype.loadState;
			var result = loadStateFunction.call(this.renderer, savedState);
			this.uiState = result[0];
			this.subContextSavedStates = result[1];
		},
		saveCompleteState: function (skipEnhancements) {
			var state = {};
			state.rendererId = this.renderer ? this.renderer.uniqueId : null;
			state.sub = {};

			var result = {
				actions: {},
				inputs: {},
				rootContext: this.uniqueId,
				contexts: {},
				documents: {}
			};
			result.contexts[this.uniqueId] = state;
			for (var key in this.subContexts) {
				var subContext = this.subContexts[key];
				state.sub[key] = subContext.uniqueId;
				var subResult = subContext.saveCompleteState(true);
				for (var subKey in subResult.contexts) {
					result.contexts[subKey] = subResult.contexts[subKey];
				}
				for (var subKey in subResult.documents) {
					result.documents[subKey] = subResult.documents[subKey];
				}
			}
			for (var key in this.oldSubContexts) {
				var subContext = this.oldSubContexts[key];
				state.sub[key] = subContext.uniqueId;
				var subResult = subContext.saveCompleteState(true);
				for (var subKey in subResult.contexts) {
					result.contexts[subKey] = subResult.contexts[subKey];
				}
				for (var subKey in subResult.documents) {
					result.documents[subKey] = subResult.documents[subKey];
				}
			}

			if (!this.data) {
				state.data = null;
			} else {
				state.data = {
					path: this.data.pointerPath(),
					document: this.data.document.uniqueId
				};
				if (!result.documents[this.data.document.uniqueId]) {
					result.documents[this.data.document.uniqueId] = this.data.document.deflate(true);
				}
			}
			
			if (!skipEnhancements) {
				for (var key in this.enhancementActions) {
					var enhancement = this.enhancementActions[key];
					result.actions[key] = {
						context: enhancement.context.uniqueId,
						name: enhancement.actionName,
						params: enhancement.params
					};
					var c = enhancement.context;
					while (c && c.baseContext != c && result.contexts[c.uniqueId]) {
						result.contexts[c.uniqueId].keep = true;
						c = c.baseContext;
					}
				}
				for (var key in this.enhancementInputs) {
					var enhancement = this.enhancementInputs[key];
					result.inputs[key] = {
						context: enhancement.context.uniqueId,
						name: enhancement.actionName,
						params: enhancement.params
					};
					var c = enhancement.context;
					while (c && c.baseContext != c && result.contexts[c.uniqueId]) {
						result.contexts[c.uniqueId].keep = true;
						c = c.baseContext;
					}
				}
				var newContexts = {};
				for (var key in result.contexts) {
					if (result.contexts[key].keep) {
						newContexts[key] = result.contexts[key];
						delete newContexts[key].keep;
					}
				}
				result.contexts = newContexts;
				result.uiState = this.saveState();
			}
			return result;
		},
		getSubContext: function (elementId, data, label, uiStartingState) {
			if (typeof label == "object" && label != null) {
				throw new Error('Label cannot be an object');
			}
			if (label || label === "") {
				var labelKey = label;
			} else {
				var labelKey = this.labelForData(data);
			}
			if (this.oldSubContexts[labelKey] != undefined) {
				this.subContexts[labelKey] = this.oldSubContexts[labelKey];
			}
			if (this.subContexts[labelKey] != undefined) {
				if (this.subContexts[labelKey].data === null) {
					// null can be used as a placeholder, to get callbacks when rendering requests/urls
					this.subContexts[labelKey].data = data;
				} else if (this.subContexts[labelKey].data != data) {
					delete this.subContexts[labelKey];
					delete this.oldSubContexts[labelKey];
					delete this.subContextSavedStates[labelKey];
				}
			}
			if (this.subContextSavedStates[labelKey]) {
				uiStartingState = this.subContextSavedStates[labelKey];
				delete this.subContextSavedStates[labelKey];
			}
			if (this.subContexts[labelKey] == undefined) {
				var usedComponents = [];
				if (this.data == data) {
					usedComponents = this.usedComponents.slice(0);
					if (this.renderer != undefined) {
						usedComponents = usedComponents.concat(this.renderer.component);
					}
				}
				if (typeof elementId == "object") {
					elementId = elementId.id;
				}
				function Context(rootContext, baseContext, label, data, uiState, usedComponents) {
					this.uniqueId = contextIdCounter++;
					this.rootContext = rootContext;
					this.baseContext = baseContext;
					this.label = label;
					this.data = data;
					this.uiStartingState = copyValue(uiState || {});
					this.usedComponents = usedComponents;
					this.subContexts = {};
					this.oldSubContexts = {};
				}
				Context.prototype = this.rootContext;
				this.subContexts[labelKey] = new Context(this.rootContext, this, labelKey, data, uiStartingState, usedComponents);
			}
			var subContext = this.subContexts[labelKey];
			subContext.elementId = elementId;
			subContext.parent = this;
			return subContext;
		},
		clearOldSubContexts: function () {
			this.oldSubContexts = this.subContexts;
			this.subContexts = {};
		},
		rerender: function () {
			if (this.parent && !this.elementId) {
				return this.parent.rerender();
			}
			var element = document.getElementById(this.elementId);
			if (element != null) {
				this.renderer.render(element, this.data, this);
				this.clearOldSubContexts();
			}
		},
		asyncRerenderHtml: function (htmlCallback) {
			var thisContext = this;
			if (this.uiState == undefined) {
				this.loadState(this.uiStartingState);
			}
			
			var renderer = this.renderer;
			var data = this.data;
			
			renderer.asyncRenderHtml(data, this, function (error, innerHtml) {
				if (error) {
					return htmlCallback(error, innerHtml, thisContext);
				}
				thisContext.clearOldSubContexts();

				htmlCallback(null, innerHtml, thisContext);
			});
		},

		render: function (element, data, label, uiStartingState) {
			if (uiStartingState == undefined && typeof label == "object") {
				uiStartingState = label;
				label = null;
			}
			// If data is a URL, then fetch it and call back
			if (typeof data == "string") {
				data = Jsonary.getData(data);
			}
			if (data.getData != undefined) {
				var thisContext = this;
				element.innerHTML = '<div class="loading"></div>';
				var subContext = this.getSubContext(element.id, null, label, uiStartingState);
				var request = data.getData(function (actualData) {
					thisContext.render(element, actualData, label, uiStartingState);
				});
				return subContext;;
			}

			if (typeof uiStartingState != "object") {
				uiStartingState = {};
			}
			if (element.id == undefined || element.id == "") {
				element.id = this.getElementId();
			}

			var previousContext = element.jsonaryContext;
			var subContext = this.getSubContext(element.id, data, label, uiStartingState);
			element.jsonaryContext = subContext;

			if (previousContext) {
				// Something was rendered here before - remove this element from the lookup list for that data ID
				var previousId = previousContext.data.uniqueId;
				var index = this.elementLookup[previousId].indexOf(element.id);
				if (index >= 0) {
					this.elementLookup[previousId].splice(index, 1);
				}
			}
			var uniqueId = data.uniqueId;
			if (this.elementLookup[uniqueId] == undefined) {
				this.elementLookup[uniqueId] = [];
			}
			if (this.elementLookup[uniqueId].indexOf(element.id) == -1) {
				this.elementLookup[uniqueId].push(element.id);
			}
			var renderer = selectRenderer(data, uiStartingState, subContext.usedComponents);
			if (renderer != undefined) {
				subContext.renderer = renderer;
				if (subContext.uiState == undefined) {
					subContext.loadState(subContext.uiStartingState);
				}
				renderer.render(element, data, subContext);
				subContext.clearOldSubContexts();
			} else {
				element.innerHTML = "NO RENDERER FOUND";
			}
			return subContext;
		},
		renderHtml: function (data, label, uiStartingState) {
			if (uiStartingState == undefined && typeof label == "object") {
				uiStartingState = label;
				label = null;
			}
			var elementId = this.getElementId();
			if (typeof data == "string") {
				data = Jsonary.getData(data);
			}
			if (data.getData != undefined) {
				var thisContext = this;
				var rendered = false;
				data.getData(function (actualData) {
					if (!rendered) {
						rendered = true;
						data = actualData;
					} else {
						var element = document.getElementById(elementId);
						if (element) {
							thisContext.render(element, actualData, label, uiStartingState);
						} else {
							Jsonary.log(Jsonary.logLevel.WARNING, "Attempted delayed render to non-existent element: " + elementId);
						}
					}
				});
				if (!rendered) {
					rendered = true;
					return '<span id="' + elementId + '"><div class="loading"></div></span>';
				}
			}
			
			if (uiStartingState === true) {
				uiStartingState = this.uiStartingState;
			}
			if (typeof uiStartingState != "object") {
				uiStartingState = {};
			}
			var subContext = this.getSubContext(elementId, data, label, uiStartingState);

			var renderer = selectRenderer(data, uiStartingState, subContext.usedComponents);
			subContext.renderer = renderer;
			if (subContext.uiState == undefined) {
				subContext.loadState(subContext.uiStartingState);
			}
			
			var innerHtml = renderer.renderHtml(data, subContext);
			subContext.clearOldSubContexts();
			var uniqueId = data.uniqueId;
			if (this.elementLookup[uniqueId] == undefined) {
				this.elementLookup[uniqueId] = [];
			}
			if (this.elementLookup[uniqueId].indexOf(elementId) == -1) {
				this.elementLookup[uniqueId].push(elementId);
			}
			this.addEnhancement(elementId, subContext);
			return '<span id="' + elementId + '">' + innerHtml + '</span>';
		},
		asyncRenderHtml: function (data, label, uiStartingState, htmlCallback) {
			var thisContext = this;
			if (uiStartingState == undefined && typeof label == "object") {
				uiStartingState = label;
				label = null;
			}
			var elementId = this.getElementId();
			if (typeof data == "string") {
				data = Jsonary.getData(data);
			}
			if (data.getData != undefined) {
				label = label || 'async' + Math.random();
				var subContext = this.getSubContext(elementId, null, label, uiStartingState);
				data.getData(function (actualData) {
					thisContext.asyncRenderHtml(actualData, label, uiStartingState, htmlCallback);
				});
				return subContext;
			}
			
			if (uiStartingState === true) {
				uiStartingState = this.uiStartingState;
			}
			if (typeof uiStartingState != "object") {
				uiStartingState = {};
			}
			var subContext = this.getSubContext(elementId, data, label, uiStartingState);

			var renderer = selectRenderer(data, uiStartingState, subContext.usedComponents);
			subContext.renderer = renderer;
			if (subContext.uiState == undefined) {
				subContext.loadState(subContext.uiStartingState);
			}
			
			renderer.asyncRenderHtml(data, subContext, function (error, innerHtml) {
				subContext.clearOldSubContexts();
				htmlCallback(null, innerHtml, subContext);
			});
			return subContext;
		},
		update: function (data, operation) {
			var uniqueId = data.uniqueId;
			var elementIds = this.elementLookup[uniqueId];
			if (elementIds == undefined || elementIds.length == 0) {
				return;
			}
			var elementIds = elementIds.slice(0);
			for (var i = 0; i < elementIds.length; i++) {
				var element = document.getElementById(elementIds[i]);
				if (element == undefined) {
					continue;
				}
				// If the element doesn't have a context, but update is being called, then it's probably (inadvisedly) trying to change something during its initial render.
				// If so, check the enhancement contexts.
				var prevContext = element.jsonaryContext || this.enhancementContexts[elementIds[i]];
				var prevUiState = copyValue(this.uiStartingState);
				var renderer = selectRenderer(data, prevUiState, prevContext.usedComponents);
				if (renderer.uniqueId == prevContext.renderer.uniqueId) {
					renderer.update(element, data, prevContext, operation);
				} else {
					prevContext.baseContext.render(element, data, prevContext.label, prevUiState);
				}
			}
		},
		actionHtml: function(innerHtml, actionName) {
			var startingIndex = 2;
			var historyChange = false;
			var linkUrl = Jsonary.render.actionUrl(this, actionName);
			if (typeof actionName == "boolean") {
				historyChange = arguments[1];
				linkUrl = arguments[2] || linkUrl;
				actionName = arguments[3];
				startingIndex += 2;
			}
			var params = [];
			for (var i = startingIndex; i < arguments.length; i++) {
				params.push(arguments[i]);
			}
			var elementId = this.getElementId();
			this.addEnhancementAction(elementId, actionName, this, params, historyChange);
			return Jsonary.render.actionHtml(elementId, linkUrl, innerHtml);
		},
		inputNameForAction: function (actionName) {
			var historyChange = false;
			var startIndex = 1;
			if (typeof actionName == "boolean") {
				historyChange = actionName;
				actionName = arguments[1];
				startIndex++;
			}
			var params = [];
			for (var i = startIndex; i < arguments.length; i++) {
				params.push(arguments[i]);
			}
			var name = this.getElementId();
			this.enhancementInputs[name] = {
				inputName: name,
				actionName: actionName,
				context: this,
				params: params,
				historyChange: historyChange
			};
			return name;
		},
		addEnhancement: function(elementId, context) {
			this.enhancementContexts[elementId] = context;
		},
		addEnhancementAction: function (elementId, actionName, context, params, historyChange) {
			if (params == null) {
				params = [];
			}
			this.enhancementActions[elementId] = {
				actionName: actionName,
				context: context,
				params: params,
				historyChange: historyChange
			};
		},
		enhanceElement: function (element) {
			var rootElement = element;
			// Perform post-order depth-first walk of tree, calling enhanceElementSingle() on each element
			// Post-order reduces orphaned enhancements by enhancing all children before the parent
			while (element) {
				if (element.firstChild) {
					element = element.firstChild;
					continue;
				}
				while (!element.nextSibling && element != rootElement) {
					if (element.nodeType == 1) {
						this.enhanceElementSingle(element);
					}
					element = element.parentNode;
				}
				if (element.nodeType == 1) {
					this.enhanceElementSingle(element);
				}
				if (element == rootElement) {
					break;
				}
				if (element.parentNode != element.nextSibling.parentNode) {
					// This is IE 7+8's *brilliant* reaction to missing close tags (e.g. <div><span>...</div>)
					// element = element.parentNode;
					throw new Error("DOM mismatch - did you forget a close tag? " + element.innerHTML);
				}
				element = element.nextSibling;
			}
		},
		enhanceElementSingle: function (element) {
			var elementId = element.id;
			var context = this.enhancementContexts[elementId];
			if (context != undefined) {
				element.jsonaryContext = context;
				delete this.enhancementContexts[elementId];
				var renderer = context.renderer;
				if (renderer != undefined) {
					renderer.enhance(element, context.data, context);
				}
			}
			var action = this.enhancementActions[element.id];
			if (action != undefined) {
				delete this.enhancementActions[element.id];
				element.onclick = function () {
					var redrawElementId = action.context.elementId;
					var actionContext = action.context;
					var args = [actionContext, action.actionName].concat(action.params);
					if (actionContext.renderer.action.apply(actionContext.renderer, args)) {
						// Action returned positive - we should force a re-render
						actionContext.rerender();
					}
					notifyActionHandlers(actionContext.data, actionContext, action.actionName, action.historyChange);
					return false;
				};
			}
			var inputAction = this.enhancementInputs[element.name];
			if (inputAction != undefined) {
				delete this.enhancementInputs[element.name];
				element.onchange = function () {
					var value = this.value;
					if (this.getAttribute("type") == "checkbox") {
						value = this.checked;
					}
					if (this.tagName.toLowerCase() == "select" && this.getAttribute("multiple") != null) {
						value = [];
						for (var i = 0; i < this.options.length; i++) {
							var option = this.options[i];
							if (option.selected) {
								value.push(option.value);
							}
						}						
					}
					var redrawElementId = inputAction.context.elementId;
					var inputContext = inputAction.context;
					var args = [inputContext, inputAction.actionName, value].concat(inputAction.params);
					if (inputContext.renderer.action.apply(inputContext.renderer, args)) {
						inputContext.rerender();
					}
					notifyActionHandlers(inputContext.data, inputContext, inputAction.actionName, inputAction.historyChange);
				};
			}
			element = null;
		}
	};
	var pageContext = new RenderContext();
	render.loadDocumentsFromState = function (saved, callback) {
		var documents = {};
		var counter = 0;
		var error = null;
		for (var key in saved.documents) {
			counter++;
			documents[key] = Jsonary.inflate(saved.documents[key], function (err, document) {
				error = error || err;
				counter--;
				if (counter == 0 && callback) {
					callback(error, documents);
				}
			});
		}
		saved.parsedDocuments = function () {
			return documents;
		};
		return documents;
	};
	render.loadCompleteState = function (saved) {
		var contexts = {};
		if (!saved.parsedDocuments) {
			render.loadDocumentsFromState(saved);
		}
		var documents = saved.parsedDocuments();
		
		function contextData(id) {
			var state = saved.contexts[id];
			if (state.data) {
				var document = documents[state.data.document];
				return document.root.subPath(state.data.path);
			}
			return null;
		}
		
		function loadContext(context, id) {
			var state = saved.contexts[id];
			contexts[id] = context;
			
			var renderer = rendererLookup[state.rendererId];
			context.renderer = renderer;
			context.loadState(context.uiStartingState);
			if (state.sub) {
				for (var key in state.sub) {
					var savedId = state.sub[key];
					if (contexts[savedId] || !saved.contexts[savedId]) {
						continue;
					}
					var data = contextData(savedId);
					var subContext = context.getSubContext("", data, key);
					loadContext(subContext, savedId);
				}
			}
		}
		var rootContextId = saved.rootContext;
		var rootData = contextData(rootContextId);
		var rootContext = pageContext.getSubContext('', rootData, null, saved.uiState);
		loadContext(rootContext, rootContextId);

		var actions = {};		
		for (var key in saved.actions) {
			(function (key, action) {
				var action = saved.actions[key];
				var actionContext = contexts[action.context];
				actions[key] = function () {
					var args = [actionContext, action.name].concat(action.params || []);
					return actionContext.renderer.action.apply(actionContext.renderer, args);
				};
			})(key, saved.actions[key]);
		}
		var inputs = {};
		for (var key in saved.inputs) {
			(function (key, input) {
				var input = saved.inputs[key];
				var inputContext = contexts[input.context];
				inputs[key] = function (value) {
					var args = [inputContext, input.name, value].concat(input.params || []);
					return inputContext.renderer.action.apply(inputContext.renderer, args);
				};
			})(key, saved.inputs[key]);
		}

		return {
			context: rootContext,
			actions: actions,
			inputs: inputs
		};
	};
	
	function cleanup() {
		// Clean-up sweep of pageContext's element lookup
		var keysToRemove = [];
		for (var key in pageContext.elementLookup) {
			var elementIds = pageContext.elementLookup[key];
			var found = false;
			for (var i = 0; i < elementIds.length; i++) {
				var element = document.getElementById(elementIds[i]);
				if (element) {
					found = true;
					break;
				}
			}
			if (!found) {
				keysToRemove.push(key);
			}
		}
		for (var i = 0; i < keysToRemove.length; i++) {
			delete pageContext.elementLookup[keysToRemove[i]];
		}
		for (var key in pageContext.enhancementContexts) {
			if (pageContext.enhancementContexts[key]) {
				var context = pageContext.enhancementContexts[key];
				Jsonary.log(Jsonary.logLevel.WARNING, 'Orphaned context for element: ' + JSON.stringify(key)
					+ '\ncomponents:' + context.renderer.component.join(", ")
					+ '\ndata: ' + context.data.json());
				pageContext.enhancementContexts[key] = null;
			}
		}
		for (var key in pageContext.enhancementActions) {
			if (pageContext.enhancementActions[key]) {
				var context = pageContext.enhancementActions[key].context;
				Jsonary.log(Jsonary.logLevel.WARNING, 'Orphaned action for element: ' + JSON.stringify(key)
					+ '\ncomponents:' + context.renderer.component.join(", ")
					+ '\ndata: ' + context.data.json());
				pageContext.enhancementActions[key] = null;
			}
		}
		for (var key in pageContext.enhancementInputs) {
			if (pageContext.enhancementInputs[key]) {
				var context = pageContext.enhancementInputs[key].context;
				Jsonary.log(Jsonary.logLevel.WARNING, 'Orphaned action for input: ' + JSON.stringify(key)
					+ '\ncomponents:' + context.renderer.component.join(", ")
					+ '\ndata: ' + context.data.json());
				pageContext.enhancementInputs[key] = null;
			}
		}
	}
	if (typeof document != 'undefined') {
		setInterval(cleanup, 30000); // Every 30 seconds
	}
	if (typeof document != 'undefined') {
		Jsonary.cleanup = cleanup;
	}

	function render(element, data, uiStartingState) {
		if (typeof element == 'string') {
			element = document.getElementById(element);
		}
		var innerElement = document.createElement('span');
		innerElement.className = "jsonary";
		element.innerHTML = "";
		element.appendChild(innerElement);
		var context = pageContext.subContext(Math.random());
		pageContext.oldSubContexts = {};
		pageContext.subContexts = {};
		return context.render(innerElement, data, 'render', uiStartingState);
	}
	function renderHtml(data, uiStartingState) {
		var result = pageContext.renderHtml(data, null, uiStartingState);
		pageContext.oldSubContexts = {};
		pageContext.subContexts = {};
		return '<span class="jsonary">' + result + '</span>';
	}
	function asyncRenderHtml(data, uiStartingState, htmlCallback) {
		return pageContext.asyncRenderHtml(data, null, uiStartingState, function (error, innerHtml) {
			if (error) {
				htmlCallback(error, innerHtml);
			}
			htmlCallback('<span class="jsonary">' + result + '</span>');
		});
	}

	if (global.jQuery != undefined) {
		render.empty = function (element) {
			global.jQuery(element).empty();
		};
	} else {
		render.empty = function (element) {
			element.innerHTML = "";
		};
	}
	render.Components = componentNames;
	render.actionUrl = function (context, actionName) {
		return "javascript:void(0)";
	};
	render.actionHtml = function (elementId, linkUrl, innerHtml) {
		return '<a href="' + Jsonary.escapeHtml(linkUrl) + '" id="' + elementId + '" class="jsonary-action">' + innerHtml + '</a>';
	};
	render.rendered = function (data) {
		var uniqueId = data.uniqueId;
		if (!pageContext.elementLookup[uniqueId]) {
			return false;
		}
		var elementIds = pageContext.elementLookup[uniqueId];
		for (var i = 0; i < elementIds.length; i++) {
			var elementId = elementIds[i];
			var element = document.getElementById(elementId);
			if (element) {
				return true;
			}
		}
		return false;
	};
	
	/**********/
	
	render.saveData = function (data, saveDataId) {
		if (typeof localStorage == 'undefined') {
			return "LOCALSTORAGE_MISSING";
		}
		var deleteThreshhold = (new Date).getTime() - 1000*60*60*2; // Delete after two hours
		var keys = Object.keys(localStorage);
		for (var i = 0; i < keys.length; i++) {
			var key = keys[i];
			try {
				var storedData = JSON.parse(localStorage[key]);
				if (storedData.accessed < deleteThreshhold) {
					delete localStorage[key];
				}
			} catch (e) {
			}
		}
		localStorage[data.saveStateId] = JSON.stringify({
			accessed: (new Date).getTime(),
			data: data.deflate()
		});
		return saveDataId;
	};
	render.loadData = function (saveDataId) {
		if (typeof localStorage == "undefined") {
			return undefined;
		}
		var stored = localStorage[saveDataId];
		if (!stored) {
			return undefined;
		}
		stored = JSON.parse(stored);
		return Jsonary.inflate(stored.data);
	}

	var rendererIdCounter = 0;
	
	function Renderer(sourceObj) {
		this.renderFunction = sourceObj.render || sourceObj.enhance;
		this.renderHtmlFunction = sourceObj.renderHtml;
		this.updateFunction = sourceObj.update;
		this.filterFunction = sourceObj.filter;
		this.actionFunction = sourceObj.action;
		for (var key in sourceObj) {
			if (this[key] == undefined) {
				this[key] = sourceObj[key];
			}
		}
		this.uniqueId = rendererIdCounter++;
		this.name = sourceObj.name || ("#" + this.uniqueId);
		this.component = (sourceObj.component != undefined) ? sourceObj.component : componentList[componentList.length - 1];
		if (typeof this.component == "string") {
			this.component = [this.component];
		}
		if (sourceObj.saveState) {
			this.saveState = sourceObj.saveState;
		}
		if (sourceObj.loadState) {
			this.loadState = sourceObj.loadState;
		}
	}
	Renderer.prototype = {
		updateAll: function () {
			var elementIds = [];
			for (var uniqueId in pageContext.elementLookup) {
				elementIds = elementIds.concat(pageContext.elementLookup[uniqueId]);
			}
			for (var i = 0; i < elementIds.length; i++) {
				var element = document.getElementById(elementIds[i]);
				if (element == undefined) {
					continue;
				}
				var context = element.jsonaryContext;
				if (context.renderer.uniqueId = this.uniqueId) {
					context.rerender();
				}
			}
		},
		render: function (element, data, context) {
			if (element == null) {
				Jsonary.log(Jsonary.logLevel.WARNING, "Attempted to render to non-existent element.\n\tData path: " + data.pointerPath() + "\n\tDocument: " + data.document.url);
				return this;
			}
			if (element[0] != undefined) {
				element = element[0];
			}
			render.empty(element);
			element.innerHTML = this.renderHtml(data, context);
			if (this.renderFunction != null) {
				this.renderFunction(element, data, context);
			}
			context.enhanceElement(element);
			return this;
		},
		renderHtml: function (data, context) {
			var innerHtml = "";
			if (this.renderHtmlFunction != undefined) {
				innerHtml = this.renderHtmlFunction(data, context);
			}
			return innerHtml;
		},
		asyncRenderHtml: function (data, context, htmlCallback) {
			var innerHtml = "";
			var subCounter = 1;
			var subs = {};
			if (this.renderHtmlFunction != undefined) {
				// Create a substitute context for this render
				// uiState and other variables still point to the same place, but calls to renderHtml() are redirected to an async substitute
				var substituteRenderHtml = function (data, label, uiState) {
					var placeholderString = '<<ASYNC' + Math.random() + '>>';
					var actualString = null;
					subCounter++;
					this.asyncRenderHtml(data, label, uiState, function (error, innerHtml) {
						subs[placeholderString] = innerHtml;
						actualString = innerHtml;
						decrementSubRenderCount();
					});
					if (actualString !== null) {
						delete subs[placeholderString];
						return actualString;
					}
					return placeholderString;
				};
				function createAsyncContext(context) {
					var asyncContext = Object.create(context);
					asyncContext.renderHtml = substituteRenderHtml;
					asyncContext.subContext = function () {
						return createAsyncContext(context.subContext.apply(this, arguments));
					};
					return asyncContext;
				}
				// Render innerHtml with placeholders
				innerHtml = this.renderHtmlFunction(data, createAsyncContext(context));
			}
			function decrementSubRenderCount() {
				subCounter--;
				if (subCounter > 0) {
					return;
				}
				
				for (var placeholder in subs) {
					//innerHtml = innerHtml.replace(placeholder, subs[placeholder]);
				}
				htmlCallback(null, innerHtml, context);
			}
			decrementSubRenderCount();
		},
		enhance: function (element, data, context) {
			if (this.renderFunction != null) {
				this.renderFunction(element, data, context);
			}
			return this;
		},
		update: function (element, data, context, operation) {
			var redraw;
			if (this.updateFunction != undefined) {
				redraw = this.updateFunction(element, data, context, operation);
			} else {
				redraw = this.defaultUpdate(element, data, context, operation);
			}
			if (redraw) {
				this.render(element, data, context);
			}
			return this;
		},
		action: function (context, actionName) {
			var result = this.actionFunction.apply(this, arguments);
			return result;
		},
		canRender: function (data, schemas, uiState) {
			if (this.filterFunction != undefined) {
				return this.filterFunction(data, schemas, uiState);
			}
			return true;
		},
		defaultUpdate: function (element, data, context, operation) {
			var redraw = false;
			var checkChildren = operation.action() != "replace";
			var pointerPath = data.pointerPath();
			if (operation.subjectEquals(pointerPath) || (checkChildren && operation.subjectChild(pointerPath) !== false)) {
				redraw = true;
			} else if (operation.target() != undefined) {
				if (operation.targetEquals(pointerPath) || (checkChildren && operation.targetChild(pointerPath) !== false)) {
					redraw = true;
				}
			}
			return redraw;
		},
		saveState: function (uiState, subStates, data) {
			var result = {};
			for (key in uiState) {
				result[key] = uiState[key];
			}
			for (var label in subStates) {
				for (var subKey in subStates[label]) {
					result[label + "-" + subKey] = subStates[label][subKey];
				}
			}
			for (key in result) {
				if (Jsonary.isData(result[key])) {
					result[key] = this.saveStateData(result[key]);
				} else {
				}
			}
			return result;
		},
		saveStateData: function (data) {
			if (!data) {
				return undefined;
			}
			if (data.document.isDefinitive) {
				return "url:" + data.referenceUrl();
			}
			data.saveStateId = data.saveStateId || randomId();
			return render.saveData(data, data.saveStateId) || data.saveStateId;
		},
		loadState: function (savedState) {
			var uiState = {};
			var subStates = {};
			for (var key in savedState) {
				if (key.indexOf("-") != -1) {
					var parts = key.split('-');
					var subKey = parts.shift();
					var remainderKey = parts.join('-');
					if (!subStates[subKey]) {
						subStates[subKey] = {};
					}
					subStates[subKey][remainderKey] = savedState[key];
				} else {
					uiState[key] = this.loadStateData(savedState[key]) || savedState[key];
					if (Jsonary.isRequest(uiState[key])) {
						(function (key) {
							uiState[key].getData(function (data) {
								uiState[key] = data;
							});
						})(key);
					}
				}
			}
			return [
				uiState,
				subStates
			]
		},
		loadStateData: function (savedState) {
			if (!savedState || typeof savedState != "string") {
				return undefined;
			}
			if (savedState.substring(0, 4) == "url:") {
				var url = savedState.substring(4);
				var data = null;
				var request = Jsonary.getData(url, function (urlData) {
					data = urlData;
				});
				return data || request;
			}
			
			var data = render.loadData(savedState);
			if (data) {
				data.saveStateId = savedState;
			}
			return data;
		}
	}
	Renderer.prototype.super_ = Renderer.prototype;

	var rendererLookup = {};
	var rendererList = [];
	function register(obj) {
		var renderer = new Renderer(obj);
		rendererLookup[renderer.uniqueId] = renderer;
		rendererList.push(renderer);
		return renderer;
	}
	function deregister(rendererId) {
		if (typeof rendererId == "object") {
			rendererId = rendererId.uniqueId;
		}
		delete rendererLookup[rendererId];
		for (var i = 0; i < rendererList.length; i++) {
			if (rendererList[i].uniqueId == rendererId) {
				rendererList.splice(i, 1);
				i--;
			}
		}
	}
	render.register = register;
	render.deregister = deregister;
	
	var actionHandlers = [];
	render.addActionHandler = function (callback) {
		actionHandlers.push(callback);
	};
	function notifyActionHandlers(data, context, actionName, historyChange) {
		historyChange = !!historyChange || (historyChange == undefined);
		for (var i = 0; i < actionHandlers.length; i++) {
			var callback = actionHandlers[i];
			var result = callback(data, context, actionName, historyChange);
			if (result === false) {
				break;
			}
		}
	};
	
	function lookupRenderer(rendererId) {
		return rendererLookup[rendererId];
	}

	function selectRenderer(data, uiStartingState, usedComponents) {
		var schemas = data.schemas();
		for (var j = 0; j < componentList.length; j++) {
			if (usedComponents.indexOf(componentList[j]) == -1) {
				var component = componentList[j];
				for (var i = rendererList.length - 1; i >= 0; i--) {
					var renderer = rendererList[i];
					if (renderer.component.indexOf(component) == -1) {
						continue;
					}
					if (renderer.canRender(data, schemas, uiStartingState)) {
						return renderer;
					}
				}
			}
		}
	}

	if (typeof global.jQuery != "undefined") {
		var jQueryRender = function (data, uiStartingState) {
			var element = this[0];
			if (element != undefined) {
				render(element, data, uiStartingState);
			}
			return this;
		};
		Jsonary.extendData({
			$renderTo: function (query, uiState) {
				if (typeof query == "string") {
					query = jQuery(query);
				}
				var element = query[0];
				if (element != undefined) {
					render(element, this, uiState);
				}
			}
		});
		jQueryRender.register = function (jQueryObj) {
			if (jQueryObj.render != undefined) {
				var oldRender = jQueryObj.render;
				jQueryObj.render = function (element, data) {
					var query = $(element);
					oldRender.call(this, query, data);
				}
			}
			if (jQueryObj.update != undefined) {
				var oldUpdate = jQueryObj.update;
				jQueryObj.update = function (element, data, operation) {
					var query = $(element);
					oldUpdate.call(this, query, data, operation);
				}
			}
			render.register(jQueryObj);
		};
		jQueryRender.empty = function (query) {
			query.each(function (index, element) {
				render.empty(element);
			});
		};
		jQuery.fn.extend({renderJson: jQueryRender});
		jQuery.extend({renderJson: jQueryRender});
	}

	Jsonary.extend({
		render: render,
		renderHtml: renderHtml,
		asyncRenderHtml: asyncRenderHtml
	});
	Jsonary.extendData({
		renderTo: function (element, uiState) {
			if (typeof element == "string") {
				element = document.getElementById(element);
			}
			render(element, this, uiState);
		}
	});
	// Whenever anything is invalidated, call access() on every document we know about, to force a re-request.
	Jsonary.invalidate = function (oldFunction) {
		return function () {
			var result = oldFunction.apply(this, arguments);
			var elementIds = [];
			for (var uniqueId in pageContext.elementLookup) {
				var ids = pageContext.elementLookup[uniqueId];
				elementIds = elementIds.concat(ids);
			}
			for (var i = 0; i < elementIds.length; i++) {
				var element = document.getElementById(elementIds[i]);
				if (element && element.jsonaryContext) {
					element.jsonaryContext.data.document.access();
				}
			}
			return result;
		};
	}(Jsonary.invalidate);
})(this);
