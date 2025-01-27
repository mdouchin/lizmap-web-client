// @ts-check
import { test, expect } from '@playwright/test';
import { gotoMap, expectParametersToContain } from './globals';

test.describe('Print', () => {

    test.beforeEach(async ({ page }) => {
        const url = '/index.php/view/map/?repository=testsrepository&project=print';
        await gotoMap(url, page)

        await page.locator('#button-print').click();

        await page.locator('#print-scale').selectOption('100000');
    });

    test('Print UI', async ({ page }) => {
        // Scales
        await expect(page.locator('#print-scale > option')).toHaveCount(6);
        await expect(page.locator('#print-scale > option')).toContainText(['500,000', '250,000', '100,000', '50,000', '25,000', '10,000']);
        // Templates
        await expect(page.locator('#print-template > option')).toHaveCount(3);
        await expect(page.locator('#print-template > option')).toContainText(['print_labels', 'print_map']);

        // Test `print_labels` template

        // Format and DPI are not displayed as there is only one value
        await expect(page.locator('#print-format')).toHaveCount(0);
        await expect(page.locator('.print-dpi')).toHaveCount(0);

        // Test `print_map` template
        await page.locator('#print-template').selectOption('1');

        // Format and DPI lists exist as there are multiple values
        await expect(page.locator('#print-format > option')).toHaveCount(2);
        await expect(page.locator('#print-format > option')).toContainText(['JPEG', 'PNG']);
        await expect(page.locator('.btn-print-dpis > option')).toHaveCount(2);
        await expect(page.locator('.btn-print-dpis > option')).toContainText(['100', '200']);

        // PNG is default
        expect(await page.locator('#print-format').inputValue()).toBe('jpeg');
        // 200 DPI is default
        expect(await page.locator('.btn-print-dpis').inputValue()).toBe('200');
    });

    test('Print requests', async ({ page }) => {
        // Required GetPrint parameters
        const expectedParameters = {
            'SERVICE': 'WMS',
            'REQUEST': 'GetPrint',
            'VERSION': '1.3.0',
            'FORMAT': 'pdf',
            'TRANSPARENT': 'true',
            'CRS': 'EPSG:2154',
            'DPI': '100',
            'TEMPLATE': 'print_labels',
        }
        // Test `print_labels` template
        let getPrintPromise = page.waitForRequest(request => request.method() === 'POST' && request.postData()?.includes('GetPrint') === true);

        // Launch print
        await page.locator('#print-launch').click();
        // check message
        await expect(page.locator('div.alert')).toHaveCount(1)
        // Close message
        await page.locator('div.alert button.btn-close').click();

        // check request
        let getPrintRequest = await getPrintPromise;
        // Extend GetPrint parameters
        const expectedParameters1 = Object.assign({}, expectedParameters, {
            'map0:EXTENT': /759249.\d+,6271892.\d+,781949.\d+,6286892.\d+/,
            'map0:SCALE': '100000',
            'map0:LAYERS': 'OpenStreetMap,quartiers,sousquartiers',
            'map0:STYLES': 'default,défaut,défaut',
            'map0:OPACITIES': '204,255,255',
            'simple_label': 'simple label',
            // Disabled because of the migration when project is saved with QGIS >= 3.32
            // 'multiline_label': 'Multiline label',
        })
        let getPrintParams = await expectParametersToContain('Print requests 1', getPrintRequest.postData() ?? '', expectedParameters1)
        await expect(getPrintParams.size).toBe(15)

        // Close message
        await page.locator('.btn-close').click();

        // Test `print_map` template
        await page.locator('#print-template').selectOption('1');

        getPrintPromise = page.waitForRequest(request => request.method() === 'POST' && request.postData()?.includes('GetPrint') === true);
        await page.locator('#print-launch').click();
        getPrintRequest = await getPrintPromise;
        // Extend and update GetPrint parameters
        const expectedParameters2 = Object.assign({}, expectedParameters, {
            'FORMAT': 'jpeg',
            'DPI': '200',
            'TEMPLATE': 'print_map',
            'map0:EXTENT': /765699.\d+,6271792.\d+,775499.\d+,6286992.\d+/,
            'map0:SCALE': '100000',
            'map0:LAYERS': 'OpenStreetMap,quartiers,sousquartiers',
            'map0:STYLES': 'default,défaut,défaut',
            'map0:OPACITIES': '204,255,255',
        })
        getPrintParams = await expectParametersToContain('Print requests 2', getPrintRequest.postData() ?? '', expectedParameters2)
        await expect(getPrintParams.size).toBe(13)

        // Test `print_overview` template
        await page.locator('#print-template').selectOption('2');
        getPrintPromise = page.waitForRequest(request => request.method() === 'POST' && request.postData()?.includes('GetPrint') === true);

        // Launch print
        await page.locator('#print-launch').click();
        // check message
        await expect(page.locator('div.alert')).toHaveCount(1)
        // Close message
        await page.locator('div.alert button.btn-close').click();

        // check request
        getPrintRequest = await getPrintPromise;
        // Extend and update GetPrint parameters
        const expectedParameters3 = Object.assign({}, expectedParameters, {
            'TEMPLATE': 'print_overview',
            'map1:EXTENT': /757949.\d+,6270842.\d+,783249.\d+,6287942.\d+/,
            'map1:SCALE': '100000',
            'map1:LAYERS': 'OpenStreetMap,quartiers,sousquartiers',
            'map1:STYLES': 'default,défaut,défaut',
            'map1:OPACITIES': '204,255,255',
            'map0:EXTENT': /761864.\d+,6274266.\d+,779334.\d+,6284518.\d+/,
        })
        getPrintParams = await expectParametersToContain('Print requests 3', getPrintRequest.postData() ?? '', expectedParameters3)
        await expect(getPrintParams.size).toBe(14)

        // Redlining with circle
        await page.locator('#button-draw').click();
        await page.getByRole('button', { name: 'Toggle Dropdown' }).click();
        await page.locator('#draw .digitizing-circle > svg').click();
        await page.locator('#newOlMap').click({
            position: {
                x: 610,
                y: 302
            }
        });
        await page.locator('#newOlMap').click({
            position: {
                x: 722,
                y: 300
            }
        });

        await page.locator('#button-print').click();
        await page.locator('#print-scale').selectOption('100000');

        getPrintPromise = page.waitForRequest(request => request.method() === 'POST' && request.postData()?.includes('GetPrint') === true);

        // Launch print
        await page.locator('#print-launch').click();
        // check message
        await expect(page.locator('div.alert')).toHaveCount(1)
        // Close message
        await page.locator('div.alert button.btn-close').click();

        // check request
        getPrintRequest = await getPrintPromise;
        // Extend and update GetPrint parameters
        const expectedParameters4 = Object.assign({}, expectedParameters, {
            'TEMPLATE': 'print_labels',
            'map0:EXTENT': /759249.\d+,6271892.\d+,781949.\d+,6286892.\d+/,
            'map0:SCALE': '100000',
            'map0:LAYERS': 'OpenStreetMap,quartiers,sousquartiers',
            'map0:STYLES': 'default,défaut,défaut',
            'map0:OPACITIES': '204,255,255',
            'map0:HIGHLIGHT_GEOM': /CURVEPOLYGON\(CIRCULARSTRING\(\n +772265.\d+ 6279008.\d+,\n +775229.\d+ 6281972.\d+,\n +778193.\d+ 6279008.\d+,\n +775229.\d+ 6276044.\d+,\n +772265.\d+ 6279008.\d+\)\)/,
            'map0:HIGHLIGHT_SYMBOL': `<?xml version=\"1.0\" encoding=\"UTF-8\"?>
    <StyledLayerDescriptor xmlns=\"http://www.opengis.net/sld\" xmlns:ogc=\"http://www.opengis.net/ogc\" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" version=\"1.1.0\" xmlns:xlink=\"http://www.w3.org/1999/xlink\" xsi:schemaLocation=\"http://www.opengis.net/sld http://schemas.opengis.net/sld/1.1.0/StyledLayerDescriptor.xsd\" xmlns:se=\"http://www.opengis.net/se\">
            <UserStyle>
                <FeatureTypeStyle>
                    <Rule>
                        <PolygonSymbolizer>
                <Stroke>
            <SvgParameter name=\"stroke\">#ff0000</SvgParameter>
            <SvgParameter name=\"stroke-opacity\">1</SvgParameter>
            <SvgParameter name=\"stroke-width\">2</SvgParameter>
        </Stroke>
        <Fill>
            <SvgParameter name=\"fill\">#ff0000</SvgParameter>
            <SvgParameter name=\"fill-opacity\">0.2</SvgParameter>
        </Fill>
            </PolygonSymbolizer>
                    </Rule>
                </FeatureTypeStyle>
            </UserStyle>
        </StyledLayerDescriptor>`,
            'simple_label': 'simple label',
            // Disabled because of the migration when project is saved with QGIS >= 3.32
            // 'multiline_label': 'Multiline label',
        })
        getPrintParams = await expectParametersToContain('Print requests 4', getPrintRequest.postData() ?? '', expectedParameters4)
        await expect(getPrintParams.size).toBe(17)
    });

    test('Print requests with selection', async ({ page }) => {
        // Select a feature
        await page.locator('#button-attributeLayers').click();
        await page.getByRole('button', { name: 'Detail' }).click();
        await page.locator('lizmap-feature-toolbar:nth-child(1) > div:nth-child(1) > button:nth-child(1)').first().click();
        await page.locator('#bottom-dock-window-buttons .btn-bottomdock-clear').click();

        const getPrintPromise = page.waitForRequest(request => request.method() === 'POST' && request.postData()?.includes('GetPrint') === true);

        // Launch print
        await page.locator('#print-launch').click();
        // check message
        await expect(page.locator('div.alert')).toHaveCount(1)
        // Close message
        await page.locator('div.alert button.btn-close').click();

        // check request
        const getPrintRequest = await getPrintPromise;
        const expectedParameters = {
            'SERVICE': 'WMS',
            'REQUEST': 'GetPrint',
            'VERSION': '1.3.0',
            'FORMAT': 'pdf',
            'TRANSPARENT': 'true',
            'CRS': 'EPSG:2154',
            'DPI': '100',
            'TEMPLATE': 'print_labels',
            'map0:EXTENT': /759249.\d+,6271892.\d+,781949.\d+,6286892.\d+/,
            'map0:SCALE': '100000',
            'map0:LAYERS': 'OpenStreetMap,quartiers,sousquartiers',
            'map0:STYLES': 'default,défaut,défaut',
            'map0:OPACITIES': '204,255,255',
            'simple_label': 'simple label',
            'SELECTIONTOKEN': /[a-z\d]+/,
        }
        const getPrintParams = await expectParametersToContain('Print requests with selection', getPrintRequest.postData() ?? '', expectedParameters)
        await expect(getPrintParams.size).toBe(16)
    });

    test('Print requests with filter', async ({ page }) => {
        // Select a feature
        await page.locator('#button-attributeLayers').click();
        await page.getByRole('button', { name: 'Detail' }).click();
        await page.locator('lizmap-feature-toolbar:nth-child(1) > div:nth-child(1) > button:nth-child(1)').first().click();
        await page.locator('#bottom-dock-window-buttons .btn-bottomdock-clear').click();

        // Filter selected feature
        await page.locator('#button-attributeLayers').click();
        const responseMatchGetFilterTokenFunc = function (response) {
            return (response.request().method() == 'POST' && response.request().postData().match(/GetFilterToken/i));
        };
        await page.locator('.btn-filter-attributeTable').click();
        let getFilterTokenPromise = page.waitForResponse(responseMatchGetFilterTokenFunc);
        await getFilterTokenPromise;

        await page.locator('#bottom-dock-window-buttons .btn-bottomdock-clear').click();
        const getPrintPromise = page.waitForRequest(request => request.method() === 'POST' && request.postData()?.includes('GetPrint') === true);

        // Launch print
        await page.locator('#print-launch').click();
        // check message
        await expect(page.locator('div.alert')).toHaveCount(1)
        // Close message
        await page.locator('div.alert button.btn-close').click();

        // check request
        const getPrintRequest = await getPrintPromise;
        const getPrintPostData = getPrintRequest.postData();
        expect(getPrintPostData).toContain('SERVICE=WMS')
        expect(getPrintPostData).toContain('REQUEST=GetPrint')
        expect(getPrintPostData).toContain('VERSION=1.3.0')
        expect(getPrintPostData).toContain('FORMAT=pdf')
        expect(getPrintPostData).toContain('TRANSPARENT=true')
        expect(getPrintPostData).toContain('CRS=EPSG%3A2154')
        expect(getPrintPostData).toContain('DPI=100')
        expect(getPrintPostData).toContain('TEMPLATE=print_labels')
        expect(getPrintPostData).toContain('map0%3ASCALE=100000')
        expect(getPrintPostData).toContain('map0%3ALAYERS=OpenStreetMap%2Cquartiers%2Csousquartiers')
        expect(getPrintPostData).toContain('map0%3ASTYLES=default%2Cd%C3%A9faut%2Cd%C3%A9faut')
        expect(getPrintPostData).toContain('map0%3AOPACITIES=204%2C255%2C255');
        expect(getPrintPostData).toContain('simple_label=simple%20label');
        expect(getPrintPostData).toContain('FILTERTOKEN=');
    });
});

test.describe('Print in popup', () => {
    test.beforeEach(async ({ page }) => {
        const url = '/index.php/view/map/?repository=testsrepository&project=print';
        await gotoMap(url, page)
        let getFeatureInfoRequestPromise = page.waitForRequest(request => request.method() === 'POST' && request.postData()?.includes('GetFeatureInfo') === true);
        await page.locator('#newOlMap').click({ position: { x: 409, y: 186 } });
        let getFeatureInfoRequest = await getFeatureInfoRequestPromise;
        expect(getFeatureInfoRequest.postData()).toMatch(/GetFeatureInfo/);
    });

    test('Popup content print', async ({ page }) => {
        const featureAtlasQuartiers = page.locator('#popupcontent lizmap-feature-toolbar[value="quartiers_cc80709a_cd4a_41de_9400_1f492b32c9f7.1"] .feature-print');
        await expect(featureAtlasQuartiers).toHaveCount(1);

        const featureAtlasSousQuartiers = page.locator('#popupcontent lizmap-feature-toolbar[value="sousquartiers_e27e6af0_dcc5_4700_9730_361437f69862.2"] .feature-print');
        await expect(featureAtlasSousQuartiers).toHaveCount(1);
    });

    test('Atlas print in popup UI', async ({ page }) => {
        // "quartiers" layer has one atlas (name "atlas_quartiers") button configured with a custom icon
        const featureAtlasQuartiers = page.locator('#popupcontent lizmap-feature-toolbar[value="quartiers_cc80709a_cd4a_41de_9400_1f492b32c9f7.1"] .feature-atlas');
        await expect(featureAtlasQuartiers).toHaveCount(1);
        await expect(featureAtlasQuartiers.locator('button')).toHaveAttribute('data-bs-title', 'atlas_quartiers');
        await expect(featureAtlasQuartiers.locator('img')).toHaveAttribute('src', '/index.php/view/media/getMedia?repository=testsrepository&project=print&path=media/svg/tree-fill.svg');

        // "sousquartiers" layer has one atlas (name "atlas_sousquartiers") button configured with the default icon
        const featureAtlasSousQuartiers = page.locator('#popupcontent lizmap-feature-toolbar[value="sousquartiers_e27e6af0_dcc5_4700_9730_361437f69862.2"] .feature-atlas');
        await expect(featureAtlasSousQuartiers).toHaveCount(1);
        await expect(featureAtlasSousQuartiers.locator('button')).toHaveAttribute('data-bs-title', 'atlas_sousquartiers');
        await expect(featureAtlasSousQuartiers.locator('svg use')).toHaveAttribute('xlink:href', '#map-print');
    });

    test('Atlas print in popup requests', async ({ page }) => {
        // Test `atlas_quartiers` print atlas request
        const featureAtlasQuartiers = page.locator('#popupcontent lizmap-feature-toolbar[value="quartiers_cc80709a_cd4a_41de_9400_1f492b32c9f7.1"] .feature-atlas');

        const getPrintPromise = page.waitForRequest(request => request.method() === 'POST' && request.postData()?.includes('GetPrint') === true);
        await featureAtlasQuartiers.locator('button').click();
        const getPrintRequest = await getPrintPromise;
        const getPrintPostData = getPrintRequest.postData();
        expect(getPrintPostData).toContain('SERVICE=WMS')
        expect(getPrintPostData).toContain('REQUEST=GetPrintAtlas')
        expect(getPrintPostData).toContain('VERSION=1.3.0')
        expect(getPrintPostData).toContain('FORMAT=pdf')
        expect(getPrintPostData).toContain('TRANSPARENT=true')
        expect(getPrintPostData).not.toContain('CRS=EPSG%3A2154')
        expect(getPrintPostData).toContain('DPI=100')
        expect(getPrintPostData).toContain('TEMPLATE=atlas_quartiers')
        expect(getPrintPostData).not.toContain('LAYERS=quartiers')
        expect(getPrintPostData).toContain('LAYER=quartiers')
        expect(getPrintPostData).not.toContain('ATLAS_PK=1')
        expect(getPrintPostData).toContain('EXP_FILTER=%24id%20IN%20(1)')

        // Test `atlas_quartiers` print atlas response
        const response = await getPrintRequest.response();
        await expect(response?.status()).toBe(200)

        expect(response?.headers()['content-type']).toBe('application/pdf');
        expect(response?.headers()['content-disposition']).toBe('attachment; filename="print_atlas_quartiers.pdf"');
    });
});

test.describe('Print - user in group a', () => {
    test.use({ storageState: 'playwright/.auth/user_in_group_a.json' });

    test.beforeEach(async ({ page }) => {
        const url = '/index.php/view/map/?repository=testsrepository&project=print';
        await gotoMap(url, page)

        await page.locator('#button-print').click();

        await page.locator('#print-scale').selectOption('100000');
    });

    test('Print UI', async ({ page }) => {
        // Templates
        await expect(page.locator('#print-template > option')).toHaveCount(3);
        await expect(page.locator('#print-template > option')).toContainText(['print_labels', 'print_map']);

        // Test `print_labels` template

        // Format and DPI are not displayed as there is only one value
        await expect(page.locator('#print-format')).toHaveCount(0);
        await expect(page.locator('.print-dpi')).toHaveCount(0);

        // Test `print_map` template
        await page.locator('#print-template').selectOption('1');

        // Format and DPI lists exist as there are multiple values
        await expect(page.locator('#print-format > option')).toHaveCount(2);
        await expect(page.locator('#print-format > option')).toContainText(['JPEG', 'PNG']);
        await expect(page.locator('.btn-print-dpis > option')).toHaveCount(2);
        await expect(page.locator('.btn-print-dpis > option')).toContainText(['100', '200']);

        // PNG is default
        expect(await page.locator('#print-format').inputValue()).toBe('jpeg');
        // 200 DPI is default
        expect(await page.locator('.btn-print-dpis').inputValue()).toBe('200');
    });
});

test.describe('Print - admin', () => {
    test.use({ storageState: 'playwright/.auth/admin.json' });

    test.beforeEach(async ({ page }) => {
        const url = '/index.php/view/map/?repository=testsrepository&project=print';
        await gotoMap(url, page)

        await page.locator('#button-print').click();

        await page.locator('#print-scale').selectOption('100000');
    });

    test('Print UI', async ({ page }) => {
        // Templates
        await expect(page.locator('#print-template > option')).toHaveCount(4);
        await expect(page.locator('#print-template > option')).toContainText(['print_labels', 'print_map', 'print_allowed_groups']);

        // Test `print_labels` template

        // Format and DPI are not displayed as there is only one value
        await expect(page.locator('#print-format')).toHaveCount(0);
        await expect(page.locator('.print-dpi')).toHaveCount(0);

        // Test `print_map` template
        await page.locator('#print-template').selectOption('1');

        // Format and DPI lists exist as there are multiple values
        await expect(page.locator('#print-format > option')).toHaveCount(2);
        await expect(page.locator('#print-format > option')).toContainText(['JPEG', 'PNG']);
        await expect(page.locator('.btn-print-dpis > option')).toHaveCount(2);
        await expect(page.locator('.btn-print-dpis > option')).toContainText(['100', '200']);

        // PNG is default
        expect(await page.locator('#print-format').inputValue()).toBe('jpeg');
        // 200 DPI is default
        expect(await page.locator('.btn-print-dpis').inputValue()).toBe('200');

        // Test `print_allowed_groups` template
        await page.locator('#print-template').selectOption('2');

        // Format and DPI lists exist as there are multiple values
        await expect(page.locator('#print-format > option')).toHaveCount(4);
        await expect(page.locator('#print-format > option')).toContainText(['PDF', 'SVG', 'PNG', 'JPEG']);
        await expect(page.locator('.btn-print-dpis > option')).toHaveCount(3);
        await expect(page.locator('.btn-print-dpis > option')).toContainText(['100', '200', '300']);

        // PNG is default
        expect(await page.locator('#print-format').inputValue()).toBe('pdf');
        // 200 DPI is default
        expect(await page.locator('.btn-print-dpis').inputValue()).toBe('100');
    });
});

test.describe('Print 3857', () => {

    test.beforeEach(async ({ page }) => {
        const url = '/index.php/view/map/?repository=testsrepository&project=print_3857';
        await gotoMap(url, page)

        await page.locator('#button-print').click();

        await page.locator('#print-scale').selectOption('72224');
    });

    test('Print UI', async ({ page }) => {
        // Scales
        await expect(page.locator('#print-scale > option')).toHaveCount(5);
        await expect(page.locator('#print-scale > option')).toContainText(['288,895', '144,448', '72,224', '36,112', '18,056']);
        // Templates
        await expect(page.locator('#print-template > option')).toHaveCount(2);
        await expect(page.locator('#print-template > option')).toContainText(['print_labels', 'print_map']);

        // Test `print_labels` template

        // Format and DPI are not displayed as there is only one value
        await expect(page.locator('#print-format')).toHaveCount(0);
        await expect(page.locator('.print-dpi')).toHaveCount(0);

        // Test `print_map` template
        await page.locator('#print-template').selectOption('1');

        // Format and DPI lists exist as there are multiple values
        await expect(page.locator('#print-format > option')).toHaveCount(2);
        await expect(page.locator('#print-format > option')).toContainText(['JPEG', 'PNG']);
        await expect(page.locator('.btn-print-dpis > option')).toHaveCount(2);
        await expect(page.locator('.btn-print-dpis > option')).toContainText(['100', '200']);

        // PNG is default
        expect(await page.locator('#print-format').inputValue()).toBe('jpeg');
        // 200 DPI is default
        expect(await page.locator('.btn-print-dpis').inputValue()).toBe('200');
    });

    test('Print requests', async ({ page }) => {
        // Test `print_labels` template
        let getPrintPromise = page.waitForRequest(request => request.method() === 'POST' && request.postData()?.includes('GetPrint') === true);

        // Launch print
        await page.locator('#print-launch').click();
        // check message
        await expect(page.locator('div.alert')).toHaveCount(1)
        // Close message
        await page.locator('div.alert button.btn-close').click();

        // check request
        let getPrintRequest = await getPrintPromise;
        let getPrintPostData = getPrintRequest.postData();
        expect(getPrintPostData).toContain('SERVICE=WMS')
        expect(getPrintPostData).toContain('REQUEST=GetPrint')
        expect(getPrintPostData).toContain('VERSION=1.3.0')
        expect(getPrintPostData).toContain('FORMAT=pdf')
        expect(getPrintPostData).toContain('TRANSPARENT=true')
        expect(getPrintPostData).toContain('CRS=EPSG%3A3857')
        expect(getPrintPostData).toContain('DPI=100')
        expect(getPrintPostData).toContain('TEMPLATE=print_labels')
        expect(getPrintPostData).toContain('map0%3AEXTENT=423093.00655000005%2C5399873.567900001%2C439487.85455000005%2C5410707.167900001')
        expect(getPrintPostData).toContain('map0%3ASCALE=72224')
        expect(getPrintPostData).toContain('map0%3ALAYERS=OpenStreetMap%2Cquartiers%2Csousquartiers')
        expect(getPrintPostData).toContain('map0%3ASTYLES=default%2Cd%C3%A9faut%2Cd%C3%A9faut')
        expect(getPrintPostData).toContain('map0%3AOPACITIES=204%2C255%2C255')
        expect(getPrintPostData).toContain('simple_label=simple%20label');
        // Disabled because of the migration when project is saved with QGIS >= 3.32
        // expect(getPrintPostData).toContain('multiline_label=Multiline%20label');

        // Test `print_map` template
        await page.locator('#print-template').selectOption('1');
        getPrintPromise = page.waitForRequest(request => request.method() === 'POST' && request.postData()?.includes('GetPrint') === true);

        // Launch print
        await page.locator('#print-launch').click();
        // check message
        await expect(page.locator('div.alert')).toHaveCount(1)
        // Close message
        await page.locator('div.alert button.btn-close').click();

        // check request
        getPrintRequest = await getPrintPromise;
        getPrintPostData = getPrintRequest.postData();
        expect(getPrintPostData).toContain('SERVICE=WMS')
        expect(getPrintPostData).toContain('REQUEST=GetPrint')
        expect(getPrintPostData).toContain('VERSION=1.3.0')
        expect(getPrintPostData).toContain('FORMAT=jpeg')
        expect(getPrintPostData).toContain('TRANSPARENT=true')
        expect(getPrintPostData).toContain('CRS=EPSG%3A3857')
        expect(getPrintPostData).toContain('DPI=200')
        expect(getPrintPostData).toContain('TEMPLATE=print_map')
        expect(getPrintPostData).toContain('map0%3AEXTENT=427751.45455%2C5399801.343900001%2C434829.4065500001%2C5410779.391900001')
        expect(getPrintPostData).toContain('map0%3ASCALE=72224')
        expect(getPrintPostData).toContain('map0%3ALAYERS=OpenStreetMap%2Cquartiers%2Csousquartiers')
        expect(getPrintPostData).toContain('map0%3ASTYLES=default%2Cd%C3%A9faut%2Cd%C3%A9faut')
        expect(getPrintPostData).toContain('map0%3AOPACITIES=204%2C255%2C255')

        // Redlining with circle
        await page.locator('#button-draw').click();
        await page.getByRole('button', { name: 'Toggle Dropdown' }).click();
        await page.locator('#draw .digitizing-circle > svg').click();
        await page.locator('#newOlMap').click({
            position: {
                x: 610,
                y: 302
            }
        });
        await page.locator('#newOlMap').click({
            position: {
                x: 722,
                y: 300
            }
        });

        await page.locator('#button-print').click();
        await page.locator('#print-scale').selectOption('72224');

        getPrintPromise = page.waitForRequest(request => request.method() === 'POST' && request.postData()?.includes('GetPrint') === true);

        // Launch print
        await page.locator('#print-launch').click();
        // check message
        await expect(page.locator('div.alert')).toHaveCount(1)
        // Close message
        await page.locator('div.alert button.btn-close').click();

        // check request
        getPrintRequest = await getPrintPromise;
        getPrintPostData = getPrintRequest.postData();
        expect(getPrintPostData).toContain('SERVICE=WMS')
        expect(getPrintPostData).toContain('REQUEST=GetPrint')
        expect(getPrintPostData).toContain('VERSION=1.3.0')
        expect(getPrintPostData).toContain('FORMAT=pdf')
        expect(getPrintPostData).toContain('TRANSPARENT=true')
        expect(getPrintPostData).toContain('CRS=EPSG%3A3857')
        expect(getPrintPostData).toContain('DPI=100')
        expect(getPrintPostData).toContain('TEMPLATE=print_labels')
        expect(getPrintPostData).toContain('map0%3AEXTENT=423093.00655000005%2C5399873.567900001%2C439487.85455000005%2C5410707.167900001')
        expect(getPrintPostData).toContain('map0%3ASCALE=72224')
        expect(getPrintPostData).toContain('map0%3ALAYERS=OpenStreetMap%2Cquartiers%2Csousquartiers')
        expect(getPrintPostData).toContain('map0%3ASTYLES=default%2Cd%C3%A9faut%2Cd%C3%A9faut')
        expect(getPrintPostData).toContain('map0%3AOPACITIES=204%2C255%2C255')
        expect(getPrintPostData).toContain('map0%3AHIGHLIGHT_GEOM=CURVEPOLYGON(CIRCULARSTRING(%0A%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20433697.51452157885%205404736.19944501%2C%0A%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20437978.67052402196%205409017.355447453%2C%0A%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20442259.82652646507%205404736.19944501%2C%0A%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20437978.67052402196%205400455.043442567%2C%0A%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20433697.51452157885%205404736.19944501))')
        expect(getPrintPostData).toContain('map0%3AHIGHLIGHT_SYMBOL=%3C%3Fxml%20version%3D%221.0%22%20encoding%3D%22UTF-8%22%3F%3E%0A%20%20%20%20%3CStyledLayerDescriptor%20xmlns%3D%22http%3A%2F%2Fwww.opengis.net%2Fsld%22%20xmlns%3Aogc%3D%22http%3A%2F%2Fwww.opengis.net%2Fogc%22%20xmlns%3Axsi%3D%22http%3A%2F%2Fwww.w3.org%2F2001%2FXMLSchema-instance%22%20version%3D%221.1.0%22%20xmlns%3Axlink%3D%22http%3A%2F%2Fwww.w3.org%2F1999%2Fxlink%22%20xsi%3AschemaLocation%3D%22http%3A%2F%2Fwww.opengis.net%2Fsld%20http%3A%2F%2Fschemas.opengis.net%2Fsld%2F1.1.0%2FStyledLayerDescriptor.xsd%22%20xmlns%3Ase%3D%22http%3A%2F%2Fwww.opengis.net%2Fse%22%3E%0A%20%20%20%20%20%20%20%20%20%20%20%20%3CUserStyle%3E%0A%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%3CFeatureTypeStyle%3E%0A%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%3CRule%3E%0A%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%3CPolygonSymbolizer%3E%0A%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%3CStroke%3E%0A%20%20%20%20%20%20%20%20%20%20%20%20%3CSvgParameter%20name%3D%22stroke%22%3E%23ff0000%3C%2FSvgParameter%3E%0A%20%20%20%20%20%20%20%20%20%20%20%20%3CSvgParameter%20name%3D%22stroke-opacity%22%3E1%3C%2FSvgParameter%3E%0A%20%20%20%20%20%20%20%20%20%20%20%20%3CSvgParameter%20name%3D%22stroke-width%22%3E2%3C%2FSvgParameter%3E%0A%20%20%20%20%20%20%20%20%3C%2FStroke%3E%0A%20%20%20%20%20%20%20%20%3CFill%3E%0A%20%20%20%20%20%20%20%20%20%20%20%20%3CSvgParameter%20name%3D%22fill%22%3E%23ff0000%3C%2FSvgParameter%3E%0A%20%20%20%20%20%20%20%20%20%20%20%20%3CSvgParameter%20name%3D%22fill-opacity%22%3E0.2%3C%2FSvgParameter%3E%0A%20%20%20%20%20%20%20%20%3C%2FFill%3E%0A%20%20%20%20%20%20%20%20%20%20%20%20%3C%2FPolygonSymbolizer%3E%0A%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%3C%2FRule%3E%0A%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%20%3C%2FFeatureTypeStyle%3E%0A%20%20%20%20%20%20%20%20%20%20%20%20%3C%2FUserStyle%3E%0A%20%20%20%20%20%20%20%20%3C%2FStyledLayerDescriptor%3E')
        expect(getPrintPostData).toContain('simple_label=simple%20label');
        // Disabled because of the migration when project is saved with QGIS >= 3.32
        // expect(getPrintPostData).toContain('multiline_label=Multiline%20label');
    });
});

test.describe('Print base layers', () => {
    test.beforeEach(async ({ page }) => {
        const url = '/index.php/view/map/?repository=testsrepository&project=base_layers';
        await gotoMap(url, page)

        await page.locator('#button-print').click();

        await page.locator('#print-scale').selectOption('72224');
    });

    test('Print requests', async ({ page }) => {
        // Print osm-mapnik
        let getPrintRequestPromise = page.waitForRequest(request => request.method() === 'POST' && request.postData()?.includes('GetPrint') === true);

        // Launch print
        await page.locator('#print-launch').click();
        // check message
        await expect(page.locator('div.alert')).toHaveCount(1)
        // Close message
        await page.locator('div.alert button.btn-close').click();

        // check request
        let getPrintRequest = await getPrintRequestPromise;
        let getPrintPostData = getPrintRequest.postData();
        expect(getPrintPostData).toContain('SERVICE=WMS')
        expect(getPrintPostData).toContain('REQUEST=GetPrint')
        expect(getPrintPostData).toContain('VERSION=1.3.0')
        expect(getPrintPostData).toContain('FORMAT=pdf')
        expect(getPrintPostData).toContain('TRANSPARENT=true')
        expect(getPrintPostData).toContain('CRS=EPSG%3A3857')
        expect(getPrintPostData).toContain('DPI=100')
        expect(getPrintPostData).toContain('TEMPLATE=simple')
        //expect(getPrintPostData).toContain('map0%3AEXTENT=')
        expect(getPrintPostData).toContain('map0%3ASCALE=72224')
        expect(getPrintPostData).toContain('map0%3ALAYERS=osm-mapnik&')
        expect(getPrintPostData).toContain('map0%3ASTYLES=d%C3%A9faut&')
        expect(getPrintPostData).toContain('map0%3AOPACITIES=255')

        let getPrintResponse = await getPrintRequest.response();
        await expect(getPrintResponse?.status()).toBe(200)
        expect(getPrintResponse?.headers()['content-type']).toBe('application/pdf');

        // Print osm-mapnik & quartiers
        let getMapRequestPromise = page.waitForRequest(/REQUEST=GetMap/);
        await page.getByLabel('quartiers').check();
        let getMapRequest = await getMapRequestPromise;
        await getMapRequest.response();

        getPrintRequestPromise = page.waitForRequest(request => request.method() === 'POST' && request.postData()?.includes('GetPrint') === true);

        // Launch print
        await page.locator('#print-launch').click();
        // check message
        await expect(page.locator('div.alert')).toHaveCount(1)
        // Close message
        await page.locator('div.alert button.btn-close').click();

        // check request
        getPrintRequest = await getPrintRequestPromise;
        getPrintPostData = getPrintRequest.postData();
        expect(getPrintPostData).not.toBeNull()
        expect(getPrintPostData).toContain('SERVICE=WMS')
        expect(getPrintPostData).toContain('REQUEST=GetPrint')
        expect(getPrintPostData).toContain('VERSION=1.3.0')
        expect(getPrintPostData).toContain('FORMAT=pdf')
        expect(getPrintPostData).toContain('TRANSPARENT=true')
        expect(getPrintPostData).toContain('CRS=EPSG%3A3857')
        expect(getPrintPostData).toContain('DPI=100')
        expect(getPrintPostData).toContain('TEMPLATE=simple')
        //expect(getPrintPostData).toContain('map0%3AEXTENT=')
        expect(getPrintPostData).toContain('map0%3ASCALE=72224')
        expect(getPrintPostData).toContain('map0%3ALAYERS=osm-mapnik%2Cquartiers&')
        expect(getPrintPostData).toContain('map0%3ASTYLES=d%C3%A9faut%2Cdefault&')
        expect(getPrintPostData).toContain('map0%3AOPACITIES=255%2C255')

        getPrintResponse = await getPrintRequest.response();
        await expect(getPrintResponse?.status()).toBe(200)
        expect(getPrintResponse?.headers()['content-type']).toBe('application/pdf');

        // Print quartiers not open-topo-map
        await page.locator('#switcher-baselayer').getByRole('combobox').selectOption('open-topo-map');

        await page.waitForResponse(response => response.status() === 200 && response.headers()['content-type'] === 'image/png');

        getPrintRequestPromise = page.waitForRequest(request => request.method() === 'POST' && request.postData()?.includes('GetPrint') === true);

        // Launch print
        await page.locator('#print-launch').click();
        // check message
        await expect(page.locator('div.alert')).toHaveCount(1)
        // Close message
        await page.locator('div.alert button.btn-close').click();

        // check request
        getPrintRequest = await getPrintRequestPromise;
        getPrintPostData = getPrintRequest.postData();
        expect(getPrintPostData).not.toBeNull()
        expect(getPrintPostData).toContain('SERVICE=WMS')
        expect(getPrintPostData).toContain('REQUEST=GetPrint')
        expect(getPrintPostData).toContain('VERSION=1.3.0')
        expect(getPrintPostData).toContain('FORMAT=pdf')
        expect(getPrintPostData).toContain('TRANSPARENT=true')
        expect(getPrintPostData).toContain('CRS=EPSG%3A3857')
        expect(getPrintPostData).toContain('DPI=100')
        expect(getPrintPostData).toContain('TEMPLATE=simple')
        //expect(getPrintPostData).toContain('map0%3AEXTENT=')
        expect(getPrintPostData).toContain('map0%3ASCALE=72224')
        expect(getPrintPostData).toContain('map0%3ALAYERS=quartiers&')
        expect(getPrintPostData).toContain('map0%3ASTYLES=default&')
        expect(getPrintPostData).toContain('map0%3AOPACITIES=255')

        getPrintResponse = await getPrintRequest.response();
        await expect(getPrintResponse?.status()).toBe(200)
        expect(getPrintResponse?.headers()['content-type']).toBe('application/pdf');

        // Print quartiers_baselayer & quartiers
        await page.locator('#switcher-baselayer').getByRole('combobox').selectOption('quartiers_baselayer');
        getMapRequest = await getMapRequestPromise;
        await getMapRequest.response();

        getPrintRequestPromise = page.waitForRequest(request => request.method() === 'POST' && request.postData()?.includes('GetPrint') === true);

        // Launch print
        await page.locator('#print-launch').click();
        // check message
        await expect(page.locator('div.alert')).toHaveCount(1)
        // Close message
        await page.locator('div.alert button.btn-close').click();

        // check request
        getPrintRequest = await getPrintRequestPromise;
        getPrintPostData = getPrintRequest.postData();
        expect(getPrintPostData).not.toBeNull()
        expect(getPrintPostData).toContain('SERVICE=WMS')
        expect(getPrintPostData).toContain('REQUEST=GetPrint')
        expect(getPrintPostData).toContain('VERSION=1.3.0')
        expect(getPrintPostData).toContain('FORMAT=pdf')
        expect(getPrintPostData).toContain('TRANSPARENT=true')
        expect(getPrintPostData).toContain('CRS=EPSG%3A3857')
        expect(getPrintPostData).toContain('DPI=100')
        expect(getPrintPostData).toContain('TEMPLATE=simple')
        //expect(getPrintPostData).toContain('map0%3AEXTENT=')
        expect(getPrintPostData).toContain('map0%3ASCALE=72224')
        expect(getPrintPostData).toContain('map0%3ALAYERS=quartiers_baselayer%2Cquartiers&')
        expect(getPrintPostData).toContain('map0%3ASTYLES=default%2Cdefault&')
        expect(getPrintPostData).toContain('map0%3AOPACITIES=255%2C255')

        getPrintResponse = await getPrintRequest.response();
        await expect(getPrintResponse?.status()).toBe(200)
        expect(getPrintResponse?.headers()['content-type']).toBe('application/pdf');
    });
});

test.describe('Error while printing', () => {

    test.beforeEach(async ({ page }) => {
        const url = '/index.php/view/map/?repository=testsrepository&project=print';
        await gotoMap(url, page)
    });

    test('Print error', async ({ page }) => {
        await page.locator('#button-print').click();

        await page.locator('#print-scale').selectOption('100000');

        await page.route('**/service*', async route => {
            if (route.request()?.postData()?.includes('GetPrint'))
                await route.fulfill({
                    status: 404,
                    contentType: 'text/plain',
                    body: 'Not Found!'
                });
            else
                await route.continue();
        });

        await page.locator('#print-launch').click();

        await expect(page.getByText('The output is currently not available. Please contact the system administrator.')).toBeVisible();

        await expect(page.locator("#message > div:last-child")).toHaveClass(/alert-danger/);
    });


    test('Print Atlas error', async ({ page }) => {

        let getFeatureInfoRequestPromise = page.waitForRequest(request => request.method() === 'POST' && request.postData()?.includes('GetFeatureInfo') === true);
        await page.locator('#newOlMap').click({ position: { x: 409, y: 186 } });
        let getFeatureInfoRequest = await getFeatureInfoRequestPromise;
        expect(getFeatureInfoRequest.postData()).toMatch(/GetFeatureInfo/);

        // Test `atlas_quartiers` print atlas request
        const featureAtlasQuartiers = page.locator('#popupcontent lizmap-feature-toolbar[value="quartiers_cc80709a_cd4a_41de_9400_1f492b32c9f7.1"] .feature-atlas');

        await page.route('**/service*', async route => {
            if (route.request()?.postData()?.includes('GetPrint'))
                await route.fulfill({
                    status: 404,
                    contentType: 'text/plain',
                    body: 'Not Found!'
                });
            else
                await route.continue();
        });

        await featureAtlasQuartiers.locator('button').click();

        await expect(page.getByText('The output is currently not available. Please contact the system administrator.')).toBeVisible();

        await expect(page.locator("#message > div:last-child")).toHaveClass(/alert-danger/);
    });

    test('Remove print overlay when switching to another minidock', async ({ page }) => {
        await page.locator('#button-print').click();

        await page.locator('#button-selectiontool').click();

        await expect(page.locator('.ol-unselectable > canvas')).toHaveCount(0);
    });
});
