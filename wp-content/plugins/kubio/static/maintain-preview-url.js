(function (location, document, wpURL) {
	function makeRandomString(length) {
		let result = '';
		const characters =
			'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
		const charactersLength = characters.length;
		for (let i = 0; i < length; i++) {
			result += characters.charAt(
				Math.floor(Math.random() * charactersLength)
			);
		}
		return result;
	}

	const urlKey = 'kubio-preview';
	const kubioRandomKey = 'kubio-random';
	const queryParam = new URLSearchParams(location.search);

	const kubioPreviewUUID = queryParam.get(urlKey);
	const kubioRandomValue = queryParam.get(kubioRandomKey);

	const protocolRegExp = /^(?:[a-z]+:|#|\?|\.|\/)/i;

	if (!(kubioPreviewUUID || kubioRandomValue)) {
		return;
	}

	const baseURL = (window.kubioMaintainPreviewURLBase || location.toString())
		.replace(location.search, '')
		.replace('#' + location.hash, '')
		.replace(protocolRegExp, '')
		.replace(/\/$/, '');

	const keepAliveCurrentUrl = function (root) {
		const elements = Array.from(root.querySelectorAll('a'));

		if (root.nodeName.toLowerCase() === 'a') {
			elements.push(root);
		}

		elements.forEach(function (link) {
			// use get attr instead of .href to get the actual attribute value instead of the computed one
			let href = link.getAttribute('href') || '';
			let hash = '';
			try {
				const url = new URL(href);
				hash = url.hash;
				url.hash = '';
				href = url.toString();
			} catch (e) {}

			if (!href.trim()) {
				return;
			}

			const hrefWithoutProtocol = href.replace(protocolRegExp, '');

			if (hrefWithoutProtocol.indexOf(baseURL) === 0) {
				const nextArgs = {};
				if (kubioPreviewUUID) {
					nextArgs[urlKey] = kubioPreviewUUID;
				}
				nextArgs[kubioRandomKey] = kubioRandomValue
					? kubioRandomValue
					: makeRandomString(10);
				let nextURL = wpURL.addQueryArgs(href, nextArgs);

				if (hash) {
					nextURL += hash;
				}

				link.setAttribute('href', nextURL);
			}
		});
	};

	keepAliveCurrentUrl(document.body);

	const mutationObserver = new window.MutationObserver(function (
		mutationList
	) {
		mutationList.forEach(function (mutation) {
			switch (mutation.type) {
				case 'childList':
					mutation.addedNodes.forEach(function (node) {
						if (node.nodeName.toLowerCase() === 'a') {
							keepAliveCurrentUrl(node);
						}
					});
					break;
				case 'attributes':
					if (
						mutation.target.nodeName.toLowerCase() === 'a' &&
						mutation.attributeName === 'href'
					) {
						keepAliveCurrentUrl(mutation.target);
					}
					break;
			}
		});
	});
	mutationObserver.observe(document.body, {
		attributes: true,
		childList: true,
		subtree: true,
	});
})(window.location, window.document, window.wp.url);
