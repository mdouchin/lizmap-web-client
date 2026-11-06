// @ts-check
import { test, expect } from '@playwright/test';
import { expect as responseExpect } from './fixtures/expect-response.js';

const url = '/index.php/dataviz/service';
const projectParams = new URLSearchParams({
    repository: 'testsrepository',
    project: 'dataviz',
});

test.describe('Dataviz API tests',
    {
        tag: ['@requests', '@readonly'],
    }, () => {

        test('Test JSON data for plot 0 - Municipalities', async ({request}) => {
            const response = await request.get(
                `${url}?${projectParams}`,
                {
                    params:{
                        'request': 'getPlot',
                        'plot_id': '0',
                    }
                });
            responseExpect(response).toBeJson();
            const json = await response.json();

            expect(json).toHaveProperty('title', 'Municipalities');
            expect(json).toHaveProperty('data');

            // check data
            const data = json.data;
            expect(data).toHaveLength(1);
            expect(data[0]).toHaveProperty('type', 'bar');
            expect(data[0]).toHaveProperty('x');
            expect(data[0].x).toEqual(
                expect.arrayContaining([
                    "Grabels", "Clapiers", "Montferrier-sur-Lez", "Saint-Jean-de-Védas", "Lattes", "Montpellier",
                    "Lavérune", "Juvignac", "Le Crès", "Castelnau-le-Lez"
                ])
            );
            // Values are aggregated server side: one polygon per municipality
            expect(data[0]).not.toHaveProperty('transforms');
            expect(data[0].x).toHaveLength(10);
            expect(data[0]).toHaveProperty('y');
            expect(data[0].y).toEqual([1, 1, 1, 1, 1, 1, 1, 1, 1, 1]);

            expect(json).toHaveProperty('layout')
        });

        test('Test JSON data for plot 2 - Pie bakeries by municipalities', async ({request}) => {
            const response = await request.get(
                `${url}?${projectParams}`,
                {
                    params:{
                        'request': 'getPlot',
                        'plot_id': '2',
                    }
                });
            responseExpect(response).toBeJson();
            const json = await response.json();

            expect(json).toHaveProperty('title', 'Pie Bakeries by municipalities');
            expect(json).toHaveProperty('data');

            // check data
            const data = json.data;
            expect(data).toHaveLength(1);
            expect(data[0]).toHaveProperty('type', 'pie');
            expect(data[0]).toHaveProperty('labels');
            expect(data[0].labels).toEqual(
                expect.arrayContaining([
                    "Castelnau-le-Lez", "Clapiers", "Grabels", "Juvignac", "Lattes", "Lavérune", "Le Crès",
                    "Montferrier-sur-Lez", "Montpellier", "Saint-Jean-de-Védas"
                ])
            );
            expect(data[0]).toHaveProperty('values');
            expect(data[0].values).toEqual([4, 2, 6, 1, 2, 1, 2, 1, 4, 2]);

            expect(json).toHaveProperty('layout')
        });

        test('Test JSON data for plot 3 - Horizontal bar bakeries in municipalities', async ({request}) => {
            const response = await request.get(
                `${url}?${projectParams}`,
                {
                    params:{
                        'request': 'getPlot',
                        'plot_id': '3',
                    }
                });
            responseExpect(response).toBeJson();
            const json = await response.json();

            expect(json).toHaveProperty('title', 'Horizontal bar bakeries in municipalities');
            expect(json).toHaveProperty('data');

            // check data
            const data = json.data;
            expect(data).toHaveLength(1);
            expect(data[0]).toHaveProperty('type', 'bar');
            expect(data[0]).toHaveProperty('orientation', 'h');
            // Values are aggregated server side: count of bakeries per municipality
            expect(data[0]).not.toHaveProperty('transforms');
            expect(data[0]).toHaveProperty('x');
            expect(data[0]).toHaveProperty('y');
            expect(data[0].y).toHaveLength(10);
            expect(data[0].x).toHaveLength(10);
            const countByMunicipality = Object.fromEntries(
                data[0].y.map((municipality, i) => [municipality, data[0].x[i]])
            );
            expect(countByMunicipality).toEqual({
                "Castelnau-le-Lez": 4,
                "Clapiers": 2,
                "Grabels": 6,
                "Juvignac": 1,
                "Lattes": 2,
                "Lavérune": 1,
                "Le Crès": 2,
                "Montferrier-sur-Lez": 1,
                "Montpellier": 4,
                "Saint-Jean-de-Védas": 2,
            });

            expect(json).toHaveProperty('layout')
        });
    });

test.describe('Dataviz API tests JSON data filtered for plot in popup',
    {
        tag: ['@requests', '@readonly'],
    }, () => {

        test('Number of bakeries by polygon', async ({request}) => {
            const projParams = new URLSearchParams({
                repository: 'testsrepository',
                project: 'dataviz_filtered_in_popup',
            });
            const response = await request.get(
                `${url}?${projParams}`,
                {
                    params:{
                        'request': 'getPlot',
                        'plot_id': '0',
                        'exp_filter': `"polygon_id" IN ('5')`,
                    }
                });
            responseExpect(response).toBeJson();
            const json = await response.json();

            expect(json).toHaveProperty('title', 'Number of bakeries by polygon');
            expect(json).toHaveProperty('data');

            // check data
            const data = json.data;
            expect(data).toHaveLength(1);
            expect(data[0]).toHaveProperty('type', 'bar');
            expect(data[0]).toHaveProperty('x');
            // Values are aggregated server side: 4 bakeries in polygon 5
            expect(data[0].x).toEqual([5]);
            expect(data[0]).toHaveProperty('y');
            expect(data[0].y).toEqual([4]);

            expect(json).toHaveProperty('layout')
        });

        test('Bakeries', async ({request}) => {
            const projParams = new URLSearchParams({
                repository: 'testsrepository',
                project: 'dataviz_filtered_in_popup',
            });
            const response = await request.get(
                `${url}?${projParams}`,
                {
                    params:{
                        'request': 'getPlot',
                        'plot_id': 1,
                        'exp_filter': `"polygon_id" IN ('5')`,
                    }
                });
            responseExpect(response).toBeJson();
            const json = await response.json();

            expect(json).toHaveProperty('title', 'Bakeries');
            expect(json).toHaveProperty('data');

            // check data
            const data = json.data;
            expect(data).toHaveLength(1);
            expect(data[0]).toHaveProperty('type', 'html');
            expect(data[0]).toHaveProperty('name', 'id');
            expect(data[0].x).toEqual(expect.arrayContaining([5]));
        });

        test('popup_bar users of point 1', async ({request}) => {
            const projParams = new URLSearchParams({
                repository: 'testsrepository',
                project: 'popup_bar',
            });
            const response = await request.get(
                `${url}?${projParams}`,
                {
                    params:{
                        'request': 'getPlot',
                        'plot_id': '0',
                        'exp_filter': `"fid_point" IN ('1')`,
                    }
                });
            responseExpect(response).toBeJson();
            const json = await response.json();

            expect(json).toHaveProperty('title', 'users');
            expect(json).toHaveProperty('data');

            // check data
            const data = json.data;
            expect(data).toHaveLength(1);
            expect(data[0]).toHaveProperty('type', 'scattergl');
            expect(data[0]).toHaveProperty('x');
            expect(data[0].x).toEqual(expect.arrayContaining(["2024-04-04", "2024-04-18", "2024-04-30"]));
            expect(data[0]).toHaveProperty('y');
            expect(data[0].y).toEqual(expect.arrayContaining([10, 15, 1]) );

            expect(json).toHaveProperty('layout')
        });

        test('popup_bar users of point 2', async ({request}) => {
            const projParams = new URLSearchParams({
                repository: 'testsrepository',
                project: 'popup_bar',
            });
            const response = await request.get(
                `${url}?${projParams}`,
                {
                    params:{
                        'request': 'getPlot',
                        'plot_id': '0',
                        'exp_filter': `"fid_point" IN ('2')`,
                    }
                });
            responseExpect(response).toBeJson();
            const json = await response.json();

            expect(json).toHaveProperty('title', 'users');
            expect(json).toHaveProperty('data');

            // check data
            const data = json.data;
            expect(data).toHaveLength(1);
            expect(data[0]).toHaveProperty('type', 'scattergl');
            expect(data[0]).toHaveProperty('x');
            expect(data[0].x).toEqual(expect.arrayContaining(["2024-04-04", "2024-04-30"]));
            expect(data[0]).toHaveProperty('y');
            expect(data[0].y).toEqual(expect.arrayContaining([45, 89]) );

            expect(json).toHaveProperty('layout')
        });

        test('popup_bar users of point 3 - empty', async ({request}) => {
            const projParams = new URLSearchParams({
                repository: 'testsrepository',
                project: 'popup_bar',
            });
            const response = await request.get(
                `${url}?${projParams}`,
                {
                    params:{
                        'request': 'getPlot',
                        'plot_id': '0',
                        'exp_filter': `"fid_point" IN ('3')`,
                    }
                });
            responseExpect(response).toBeJson();
            const json = await response.json();

            expect(json).toHaveProperty('title', 'users');
            expect(json).toHaveProperty('data');

            // check data
            const data = json.data;
            expect(data).toHaveLength(0);

            expect(json).toHaveProperty('layout')
        });
    });

test.describe('Dataviz API tests with Basic Auth',
    {
        tag: ['@requests', '@readonly'],
    }, () => {

        test('Create a new plot', async ({request}) => {
            const json_body = {
                "repository": "testsrepository",
                "project": "dataviz",
                "plot_config": {
                    "type": "bar",
                    "title": "Bakeries by district",
                    "layerId": "bakeries_1dbdac14_931c_4568_ad56_3a947a77d810",
                    "x_field": "polygons_name",
                    "aggregation": "count",
                    "traces": [
                        {
                            "color": "cyan",
                            "colorfield": "",
                            "y_field": "id",
                            "z_field": ""
                        }
                    ],
                    "popup_display_child_plot": "False",
                    "stacked": "False",
                    "horizontal": "False",
                    "only_show_child": "False",
                    "display_legend": "True",
                    "display_when_layer_visible": "False",
                    "order": 5
                }
            };
            const response = await request.post(url, {
                headers: {
                    authorization: "Basic " + btoa("admin:admin")
                },
                data: json_body,
            });
            responseExpect(response).toBeJson();
            const json = await response.json();

            expect(json).toHaveProperty('title', 'Bakeries by district');
            expect(json).toHaveProperty('data');

            // check data
            const data = json.data;
            expect(data).toHaveLength(1);
            expect(data[0]).toHaveProperty('type', 'bar');
            expect(data[0]).toHaveProperty('marker');
            expect(data[0].marker).toHaveProperty('color', 'cyan');
            // Values are aggregated server side: count of bakeries per district
            expect(data[0]).not.toHaveProperty('transforms');
            expect(data[0].x).toHaveLength(10);
            expect(data[0].y).toHaveLength(10);
            expect(data[0].y.reduce((total, count) => total + count, 0)).toBe(25);
        });

        test('Error with wrong layer ID', async ({request}) => {
            const json_body = {
                "repository": "testsrepository",
                "project": "dataviz",
                "plot_config": {
                    "type": "bar",
                    "title": "Bakeries by district",
                    "layerId": "bakeries_1dbdac14_931c_4568_ad56_3a947a77d810_WRONG_LAYER_ID",
                    "x_field": "polygons_name",
                    "aggregation": "count",
                    "traces": [
                        {
                            "color": "cyan",
                            "colorfield": "",
                            "y_field": "id",
                            "z_field": ""
                        }
                    ],
                    "popup_display_child_plot": "False",
                    "stacked": "False",
                    "horizontal": "False",
                    "only_show_child": "False",
                    "display_legend": "True",
                    "display_when_layer_visible": "False",
                    "order": 5
                }
            };
            const response = await request.post(url, {
                headers: {
                    authorization: "Basic " + btoa("admin:admin")
                },
                data: json_body,
            });
            responseExpect(response).toBeJson(404);
            const json = await response.json();
            expect(json).toHaveProperty('errors');
            // check errors
            const errors = json.errors;
            expect(errors).toHaveProperty('code', 404);
            expect(errors).toHaveProperty('error_code', 'layer_not_found');
        });
    });
