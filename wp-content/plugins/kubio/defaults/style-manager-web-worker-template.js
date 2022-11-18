// defaults and polyfills
// eslint-disable-next-line no-undef
window = self;
// eslint-disable-next-line no-undef
top = self;
const kubioNoop = function () {};

document = {
	createElement() {
		return {
			style: [],
			setAttribute: kubioNoop,
			attachEvent: kubioNoop,
		};
	},
	attachEvent: kubioNoop,
	addEventListener: kubioNoop,
	querySelectorAll() {
		return [];
	},
};

// wp imported scripts need to load kubio-style-manager
// {{{importScriptsPlaceholder}}}

const fonts = {};

// eslint-disable-next-line no-undef
wp.hooks.addAction(
	'kubio.google-fonts.load',
	'kubio.google-fonts.load',
	function (nextFonts) {
		nextFonts.forEach(function (font) {
			fonts[font.family] = fonts[font.family] || [];
			// eslint-disable-next-line no-undef
			fonts[font.family] = lodash.uniq(
				fonts[font.family].concat(font.variants)
			);
		});
	}
);

const renderStyle = function (payload) {
	// eslint-disable-next-line no-undef
	const dynamicStyle = lodash.cloneDeep(
		lodash.get(payload.data, 'dynamicStyle', {})
	);
	// eslint-disable-next-line no-undef
	const renderer = new kubio.styleManager.BlockStyleRender(
		// eslint-disable-next-line no-undef
		lodash.omit(payload.data, 'dynamicStyle'),
		payload.parentDetails,
		payload.canUseHtml,
		payload.document || null
	);

	const styleRef = renderer.model ? renderer.model.styleRef : null;
	const localId = renderer.model ? renderer.model.id : null;

	return {
		css: renderer.export(),
		dynamicRules: renderer.exportDynamicStyle(dynamicStyle),
		styleRef,
		localId,
		responseHash: payload.hash,
		fonts: Object.keys(fonts).map((family) => ({
			family,
			variants: fonts[family],
		})),
	};
};

const medias = ['desktop', 'tablet', 'mobile'];

const recurseSetOrder = (object, order, level = 0, index = 0) => {
	if (!lodash.isObject(object)) {
		return object;
	}

	return {
		$order: order * 100000 + level * 1000 + index,
		...Object.keys(object).reduce((acc, item, currentIndex) => {
			return {
				...acc,
				[item]: recurseSetOrder(
					object[item],
					order,
					level + 1,
					currentIndex
				),
			};
		}, {}),
	};
};

const _setRulesOrder = (orderedKeys, rules) => {
	const orderedRules = [];

	orderedKeys.forEach((styleRef, index) => {
		if (rules?.[styleRef]) {
			const styleRefRules = lodash.cloneDeep(rules[styleRef]);

			Object.keys(styleRefRules).forEach((device) => {
				const deviceRules = styleRefRules[device];

				Object.keys(deviceRules).forEach((topSelector) => {
					const elementRules = deviceRules[topSelector];

					Object.keys(elementRules).forEach((selector) => {
						const selectorRules = elementRules[selector];
						lodash.set(
							styleRefRules,
							[device, topSelector, selector],
							recurseSetOrder(selectorRules, index)
						);
					});
				});
			});

			if (!lodash.isEmpty(styleRefRules)) {
				orderedRules.push(styleRefRules);
			}
		}
	});

	return orderedRules;
};

const mapRules = (rules, media, order) => {
	const keys = [].concat(
		order,
		// eslint-disable-next-line no-undef
		lodash.difference(Object.keys(rules), order)
	);

	const orderedRules = _setRulesOrder(keys, rules);
	// eslint-disable-next-line no-undef
	const mapped = lodash.map(orderedRules, media).reduce((acc, item) => {
		// eslint-disable-next-line no-undef
		return lodash.merge(acc, item);
	}, {});

	return mapped;
};

const renderCSS = ({ rulesMapping, order }) => {
	const result = [];

	medias.forEach((media) => {
		for (const sheetType in rulesMapping) {
			const sheetRules = rulesMapping[sheetType];
			let rules = {};
			for (const type in sheetRules) {
				if (sheetRules.hasOwnProperty(type)) {
					const typeRulesForMedia = mapRules(
						sheetRules[type].rules,
						media,
						order
					);
					// eslint-disable-next-line no-undef
					rules = lodash.merge(rules, typeRulesForMedia);
				}
			}
			// eslint-disable-next-line no-undef
			const cssobjInstance = kubio.styleManager.cssobj(rules);
			result.push({
				media,
				sheetType,
				root: cssobjInstance.root,
				rules,
				css: cssobjInstance.css,
			});
		}
	});

	return result;
};

// actual web worker runner
// eslint-disable-next-line no-undef
self.addEventListener('message', (event) => {
	const action = event.data.action;
	const hash = event.data.hash;
	const payload = JSON.parse(event.data.payload);

	let response = null;

	switch (action) {
		case 'EXPORT_CSS':
			response = renderStyle(payload);
			break;
		case 'RENDER_CSS':
			// eslint-disable-next-line no-undef
			response = renderCSS(payload);
			break;
		case 'TEST':
			response = 'test';
			break;
	}

	// eslint-disable-next-line no-undef
	self.postMessage({
		hash,
		payload: response,
	});
});

// eslint-disable-next-line no-undef
self.postMessage('WORKER_LOADED');
