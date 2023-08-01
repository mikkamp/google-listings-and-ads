/**
 * External dependencies
 */
const { test, expect } = require( '@playwright/test' );

test.use( { storageState: process.env.ADMINSTATE } );

test.describe( 'Merchant who is getting started', () => {
	test.beforeEach( async ( { page } ) => {
		// Go to the setup page short way - directly via URL.
		await page.goto(
			'/wp-admin/admin.php?page=wc-admin&path=%2Fgoogle%2Fsetup-mc'
		);
	} );

	test( 'should see accounts step header, "Connect your WordPress.com account" & connect button', async ( {
		page,
	} ) => {
		// Wait for API calls and the page to render.
		await expect(
			page.waitForSelector( "//*[text()='Set up your accounts']" )
		).resolves.toBeTruthy();

		await expect(
			page.waitForSelector(
				"//*[text()='Connect the accounts required to use Google Listings & Ads.']"
			)
		).resolves.toBeTruthy();

		expect(
			page.$( "//button[text()='Connect'][not(@disabled)]" )
		).toBeTruthy();
	} );

	test( 'after clicking the "Connect your WordPress.com account" button, should send an API request to connect Jetpack, and redirect to the returned URL', async ( {
		page,
		baseURL,
	} ) => {
		// Mock Jetpack as connected
		await page.route( /\/wc\/gla\/jetpack\/connect\b/, ( route ) =>
			route.fulfill( {
				content: 'application/json',
				headers: { 'Access-Control-Allow-Origin': '*' },
				body: JSON.stringify( {
					url: baseURL + 'auth_url',
				} ),
			} )
		);

		// Wait for enabled button.
		const connectWPButton = await page.waitForSelector(
			"//button[text()='Connect'][not(@disabled)]"
		);

		// Click the button.
		await connectWPButton.click();
		await page.waitForLoadState( 'networkidle' );

		// Expect the user to be redirected
		expect( page.url() ).toEqual( baseURL + 'auth_url' );
	} );
} );

test.describe( 'Merchant with Jetpack connected & Google not connected', () => {
	test.beforeEach( async ( { page } ) => {
		// Mock Jetpack as connected
		await page.route( /\/wc\/gla\/jetpack\/connected\b/, ( route ) =>
			route.fulfill( {
				content: 'application/json',
				headers: { 'Access-Control-Allow-Origin': '*' },
				body: JSON.stringify( {
					active: 'yes',
					owner: 'yes',
					displayName: 'testUser',
					email: 'mail@example.com',
				} ),
			} )
		);

		// Mock google as not connected.
		// When pending even WPORG will not render yet.
		// If not mocked will fail and render nothing,
		// as Jetpack is mocked only on the client-side.
		await page.route( /\/wc\/gla\/google\/connected\b/, ( route ) =>
			route.fulfill( {
				content: 'application/json',
				headers: { 'Access-Control-Allow-Origin': '*' },
				body: JSON.stringify( {
					active: 'no',
					email: '',
				} ),
			} )
		);

		// Go to the setup page short way - directly via URL.
		await page.goto(
			'/wp-admin/admin.php?page=wc-admin&path=%2Fgoogle%2Fsetup-mc'
		);

		// Wait for API calls and the page to render.
		await page.waitForSelector( "//*[text()='Set up your accounts']" );
	} );

	test( 'should see their WPORG email, "Google" title & connect button', async ( {
		page,
	} ) => {
		await expect(
			page.waitForSelector( "//*[text()='mail@example.com']" )
		).resolves.toBeTruthy();

		await expect(
			page.waitForSelector( "//*[text()='Google']" )
		).resolves.toBeTruthy();

		await expect(
			page.$( "//button[text()='Connect'][not(@disabled)]" )
		).resolves.toBeTruthy();
	} );

	test( 'after clicking the "Connect your Google account" button should send an API request to connect Google account, and redirect to the returned URL', async ( {
		page,
		baseURL,
	} ) => {
		// Mock google as connected.
		await page.route( /\/wc\/gla\/google\/connect\b/, ( route ) =>
			route.fulfill( {
				content: 'application/json',
				headers: { 'Access-Control-Allow-Origin': '*' },
				body: JSON.stringify( {
					url: baseURL + 'google_auth',
				} ),
			} )
		);

		const connectGoogleButton = await page.waitForSelector(
			"//button[text()='Connect'][not(@disabled)]"
		);

		// Click the button
		await connectGoogleButton.click();
		await page.waitForLoadState( 'networkidle' );

		// Expect the user to be redirected
		expect( page.url() ).toEqual( baseURL + 'google_auth' );
	} );
} );
