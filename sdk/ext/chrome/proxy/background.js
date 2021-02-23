var config = {
	mode: "fixed_servers",
	rules: {
		singleProxy: {
			scheme: "http",
			host: "%proxy_host",
			port: %proxy_port
		},
		bypassList: []
	}
};

chrome.proxy.settings.set({value: config, scope: "regular"}, function() {});

function callbackFn(details) {
	return {
		authCredentials: {
			username: "%username",
			password: "%password"
		}
	};
}

chrome.webRequest.onAuthRequired.addListener(
	callbackFn,
	{urls: ["<all_urls>"]},
	['blocking']
);
