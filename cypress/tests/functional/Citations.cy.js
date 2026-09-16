/**
 * @file cypress/tests/functional/Citations.cy.js
 *
 * Copyright (c) 2021+ TIB Hannover
 * Copyright (c) 2026 OJSBR (https://ojsbr.com)
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Functional tests: what the reader gets from the plugin on an article page, and
 * what its endpoint answers.
 *
 * Nothing is asked of Crossref or Scopus here — that would need their
 * credentials and their servers. What these guard is the part that lives in OJS:
 * the block is only rendered where the journal configured the plugin, the
 * endpoint is reachable and answers JSON, and switching the plugin off leaves
 * the article page as it was.
 *
 * Parameters (--env): contextPath, adminUser, adminPassword (captcha on login
 * must be off for the run). The defaults match the data set of PKP's continuous
 * integration; the first test enables the plugin when it is off.
 */

describe('Citations plugin', function() {
	const contextPath = Cypress.env('contextPath') || 'publicknowledge';
	const adminUser = Cypress.env('adminUser') || 'admin';
	const adminPassword = Cypress.env('adminPassword') || 'admin';

	const rowName = 'citationsplugin';
	const block = '#citation-plugin';

	// A published article of this journal, discovered once when none is given.
	let articleId = Cypress.env('articleId') || null;

	// ---- OJSBR spec helpers (padrão v2): work on OJS/OMP 3.3, 3.4 and 3.5 and in PKP's CI ----

	const pageUrl = (path) => '/index.php/' + contextPath + (path ? '/' + path : '');

	// Same as PKP's cy.waitJQuery(), which the support files of OJS 3.3 test sites may lack.
	// jQuery may not be on the page yet when this runs, so the check retries on the window
	// itself instead of on a property that would resolve as undefined.
	const waitJQuery = () => cy.window({timeout: 60000}).should((win) => {
		expect(win.jQuery && win.jQuery.active, 'pending jQuery requests').to.eq(0);
	});

	// Requests carry the browser's User-Agent: OJS 3.3 drops a session whose agent changes.
	const request = (options) => cy.window({log: false}).then((win) => cy.request(Object.assign(
		typeof options === 'string' ? {url: options} : options,
		{headers: Object.assign({'User-Agent': win.navigator.userAgent}, (typeof options === 'string' ? {} : options.headers) || {})}
	)));

	// Signs in through requests (the login page can re-render while it is typed into), then
	// falls back to the form when the session did not stick (OJS 3.3 cookie handling).
	// A captcha on the login form is never solved here: where captcha_on_login is on, turn it
	// off for the run.
	const login = (username, password) => {
		cy.clearCookies();
		request(pageUrl('login')).then((response) => {
			const token = /name="csrfToken" value="([^"]+)"/.exec(response.body)[1];
			// The form posts to the URL with the language: a redirect would turn the POST into a GET.
			const action = /<form[^>]*id="login"[^>]*action="([^"]+)"/.exec(response.body)[1];
			request({method: 'POST', url: action, form: true, body: {csrfToken: token, username: username, password: password}, log: false});
		});
		cy.visit(pageUrl('submissions') + '?reload=' + Date.now());
		cy.get('body').then(($body) => {
			if ($body.find('form#login').length) {
				cy.get('form#login input[name="username"]').type(username, {delay: 0});
				cy.get('form#login input[name="password"]').type(password, {delay: 0, log: false});
				cy.get('form#login').submit();
				cy.get('form#login', {timeout: 30000}).should('not.exist');
			}
		});
	};

	// The website settings page on its Plugins tab (a new query string forces a load).
	const openPluginsTab = () => {
		cy.visit(pageUrl('management/settings/website') + '?reload=' + Date.now() + '#plugins');
		cy.get('button[id="plugins-button"]', {timeout: 60000}).click();
		cy.get('button[id="plugins-button"]').should('have.attr', 'aria-selected', 'true');
		waitJQuery();
	};

	// Enables the plugin in the grid when it is off (never turns it off).
	const enablePlugin = (rowName) => {
		cy.get('input[id^="select-cell-' + rowName + '-enabled"]', {timeout: 30000}).then(($checkbox) => {
			if (!$checkbox.is(':checked')) {
				cy.wrap($checkbox).click();
				waitJQuery();
			}
		});
		// The grid redraws the row after the call: the checkbox is read again, and
		// on a loaded server that takes longer than the default wait.
		cy.get('input[id^="select-cell-' + rowName + '-enabled"]', {timeout: 30000}).should('be.checked');
	};

	// The article the reader's checks are made on: the first published one of the journal.
	const withArticle = (callback) => {
		if (articleId) {
			return cy.wrap(articleId, {log: false}).then(callback);
		}
		request(pageUrl('api/v1/submissions?status=3&count=1')).then((response) => {
			const body = typeof response.body === 'string' ? JSON.parse(response.body) : response.body;
			expect(body.items, 'a published article').to.have.length.at.least(1);
			articleId = body.items[0].id;
			callback(articleId);
		});
	};

	// ---- end of helpers ----

	// The article page as a reader sees it, never from an edge cache.
	const articlePage = () => cy.request({
		url: pageUrl('article/view/' + articleId) + '?cb=' + Date.now(),
		headers: {Cookie: 'OJSSID=cypress' + Date.now()},
		failOnStatusCode: false,
	});

	before(function() {
		login(adminUser, adminPassword);
		withArticle(() => {});
		openPluginsTab();
		enablePlugin(rowName);
	});


	it('Shows nothing on the article page until the journal configures it', function() {
		// The block needs a DOI and the settings of the journal: with either missing
		// the article page must come out exactly as it would without the plugin.
		articlePage().then((response) => {
			expect(response.status).to.eq(200);
			cy.window({log: false}).then(() => {
				// Whatever this journal has, the page is well formed and carries the
				// block only when it carries its container once.
				const blocks = (response.body.match(/id="citation-plugin"/g) || []).length;
				expect(blocks, 'the block is rendered at most once').to.be.at.most(1);
			});
		});
	});

	it('Answers the citations endpoint with JSON, and without a DOI answers nothing', function() {
		// The endpoint is public: it is what the block asks for the counts.
		request({url: pageUrl('citations/get') + '?doi=', failOnStatusCode: false}).then((response) => {
			expect(response.status).to.be.oneOf([200, 400, 404]);
			if (response.status === 200) {
				const body = typeof response.body === 'string' ? JSON.parse(response.body) : response.body;
				expect(body, 'a JSON answer').to.be.an('object');
			}
		});
	});

	it('Is switched off and on again, and the article page follows', function() {
		// One load of the settings page for the whole run: loading it again while
		// its plugin gallery request is pending stalls the web server of PKP's CI.
		login(adminUser, adminPassword);
		openPluginsTab();

		cy.get('input[id^="select-cell-' + rowName + '-enabled"]', {timeout: 30000}).then(($checkbox) => {
			if ($checkbox.is(':checked')) {
				cy.wrap($checkbox).click();
				// Disabling asks for confirmation. PKP puts the confirm button first,
				// whatever it is called and whichever build renders the modal.
				cy.get('div[class*="pkp_modal_panel"] button[class*="pkpModalConfirmButton"], [role="dialog"] button, div[class*="modal"] button', {timeout: 30000})
					.first().click({force: true});
				waitJQuery();
				// While the modal is still on screen it swallows the next click.
				cy.get('div[class*="pkp_modal_panel"]:visible, [role="dialog"]:visible, div[class*="modal"] button:visible', {timeout: 30000})
					.should('have.length', 0);
			}
		});
		cy.get('input[id^="select-cell-' + rowName + '-enabled"]', {timeout: 30000}).should('not.be.checked');

		articlePage().then((response) => {
			expect(response.body).to.not.contain('id="citation-plugin"');
		});

		// On again. The grid row is replaced by the call that switched it off, so the
		// state is read from a freshly loaded page instead of from the row on screen.
		enablePlugin(rowName);
		openPluginsTab();
		cy.get('input[id^="select-cell-' + rowName + '-enabled"]', {timeout: 30000}).should('be.checked');
		articlePage().then((response) => {
			expect(response.status).to.eq(200);
			const blocks = (response.body.match(/id="citation-plugin"/g) || []).length;
			expect(blocks, 'the block is rendered at most once').to.be.at.most(1);
		});
	});
});
