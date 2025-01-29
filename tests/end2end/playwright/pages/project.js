// @ts-check
import {expect, Locator, Page} from '@playwright/test';
import { gotoMap } from '../globals';
import { BasePage } from './base';

export class ProjectPage extends BasePage {
    // Metadata
    /**
     * Project name metadata
     * @type {string}
     */
    project;
    /**
     * Repository name metadata
     * @type {string}
     */
    repository;

    // Maps
    /**
     * The OpenLayers main map
     * @type {Locator}
     */
    map;
    /**
     * The OpenLayers OL2 legacy map
     * @type {Locator}
     */
    mapOl2;

    // Menu
    /**
     * Layer switcher menu
     * @type {Locator}
     */
    switcher;
    /**
     * Editing menu
     * @type {Locator}
     */
    buttonEditing;

    // Docks
    /**
     * Attribute table dock
     * @type {Locator}
     */
    attributeTable;
    /**
     * Main left dock
     * @type {Locator}
     */
    dock;
    /**
     * Right dock
     * @type {Locator}
     */
    rightDock;
    /**
     * Bottom dock
     * @type {Locator}
     */
    bottomDock;
    /**
     * Mini dock
     * @type {Locator}
     */
    miniDock;
    /**
     * Popup dock
     * @type {Locator}
     */
    popupContent;
    /**
     * Top search bar
     * @type {Locator}
     */
    search;

    // Messages
    /**
     * Foreground message bar
     * @type {Locator}
     */
    warningMessage;

    /**
     * Attribute table for the given layer name
     * @param {string} name Name of the layer
     * @returns {Locator}
     */
    attributeTableHtml = (name) =>
        this.page.locator(`#attribute-layer-table-${name}`);

    /**
     * Editing field for the given field in the panel
     * @param {string} name Name of the field
     * @returns {Locator}
     */
    editingField = (name) =>
        this.page.locator(`#jforms_view_edition input[name="${name}"]`);

    /**
     * Constructor for a QGIS project page
     * @param {Page} page The playwright page
     * @param {string} project The project name
     * @param {string} repository The repository name, default to testsrepository
     */
    constructor(page, project, repository = 'testsrepository') {
        super(page);
        this.project = project;
        this.repository = repository;
        this.map = page.locator('#newOlMap');
        this.mapOl2 = page.locator('#map');
        this.dock = page.locator('#dock');
        this.rightDock = page.locator('#right-dock');
        this.bottomDock = page.locator('#bottom-dock');
        this.miniDock = page.locator('#mini-dock-content');
        this.popupContent = page.locator('#popupcontent');
        this.warningMessage = page.locator('#lizmap-warning-message');
        this.search = page.locator('#search-query');
        this.switcher = page.locator('#button-switcher');
        this.buttonEditing = page.locator('#button-edition');
    }

    /**
     * open function
     * Open the URL for the given project and repository
     */
    async open(){
        await gotoMap(`/index.php/view/map?repository=${this.repository}&project=${this.project}`, this.page);
    }

    /**
     * openAttributeTable function
     * Open the attribute table for the given layer
     * @param {string} layer Name of the layer
     * @param {boolean} maximise If the attribute table must be maximised
     */
    async openAttributeTable(layer, maximise = false){
        await this.page.locator('a#button-attributeLayers').click();
        if (maximise) {
            await this.page.getByRole('button', { name: 'Maximize' }).click();
        }
        await this.page.locator('#attribute-layer-list-table').locator(`button[value=${layer}]`).click();
    }

    /**
     * editingSubmitForm function
     * Submit the form
     * @param {string} futureAction The action to do after submit : can be close/create/edit.
     */
    async editingSubmitForm(futureAction = 'close'){
        await this.page.locator('#jforms_view_edition_liz_future_action').selectOption(futureAction);
        await this.page.locator('#jforms_view_edition__submit_submit').click();
        if (futureAction === 'close'){
            await expect(this.page.locator('#edition-form-container')).toBeHidden();
        } else {
            await expect(this.page.locator('#edition-form-container')).toBeVisible();
        }
        await expect(this.page.locator('#lizmap-edition-message')).toBeVisible();
    }

    /**
     * openEditingFormWithLayer function
     * Open the editing panel with the given layer name form
     * @param {string} layer Name of the layer
     */
    async openEditingFormWithLayer(layer){
        await this.buttonEditing.click();
        await this.page.locator('#edition-layer').selectOption({ label: layer });
        await this.page.locator('a#edition-draw').click();
    }

    /**
     * clickOnMap function
     * Click on the map at the given position
     * @param {number} x Position X on the map
     * @param {number} y Position Y on the map
     */
    async clickOnMap(x, y){
        await this.map.click({position: {x: x, y: y}});
    }

    /**
     * clickOnMapLegacy function
     * Click on the OL 2map at the given position
     * @param {number} x Position X on the map
     * @param {number} y Position Y on the map
     */
    async clickOnMapLegacy(x, y){
        await this.mapOl2.click({position: {x: x, y: y}});
    }

    /**
     * dblClickOnMapLegacy function
     * Double click on the OL 2map at the given position
     * @param {number} x Position X on the map
     * @param {number} y Position Y on the map
     */
    async dblClickOnMapLegacy(x, y){
        await this.mapOl2.dblclick({position: {x: x, y: y}});
    }
}
