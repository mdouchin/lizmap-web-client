/**
 * @module components/PresentationCards.js
 * @name PresentationCards
 * @copyright 2023 3Liz
 * @author DOUCHIN Michaël
 * @license MPL-2.0
 */

import { mainLizmap } from '../modules/Globals.js';

/**
 * @class
 * @name PresentationCards
 * @augments HTMLElement
 */
export default class PresentationCards extends HTMLElement {

    constructor() {
        super();

        // Id of the component
        this.id = this.getAttribute('id');

        // Attribute to force the refresh of data
        // Store the last refresh timestamp
        this.updated = this.getAttribute('updated');

        // Presentation which must be shown
        this.detail = this.getAttribute('detail');
    }

    async load() {

        // Get presentations related to the element scope from query
        mainLizmap.presentation.getPresentations()
            .then(data => {
                // Set property
                this.presentations = data;

                // Render
                this.render();
            })
            .catch(err => console.log(err))

    }

    render() {

        // Check if a specific presentation must be shown
        const activePresentationId = parseInt(this.getAttribute('detail'));

        // Remove previous content
        this.innerHTML = '';

        // Get the base content of a card from the template
        const createTemplate = document.getElementById('lizmap-presentation-create-button-template');
        this.innerHTML = createTemplate.innerHTML;

        // Get the base content of a card from the template
        const cardTemplate = document.getElementById('lizmap-presentation-card-template');
        for (const a in this.presentations) {
            // Get the presentation
            const presentation = this.presentations[a];

            // Create the div and fill it with the template content
            let div = document.createElement("div");
            div.classList.add('lizmap-presentation-card');
            div.dataset.id = presentation.id;

            // Change display if given presentation ID is an integer
            let cardDisplay = 'normal';
            if (activePresentationId > 0) {
                const currentPresentationId = parseInt(presentation.id);
                const isDetail = (parseInt(currentPresentationId) == activePresentationId);
                if (isDetail) {
                    cardDisplay = 'detail';
                } else {
                    cardDisplay = 'none';
                }
            }
            div.dataset.display = cardDisplay;
            div.innerHTML = cardTemplate.innerHTML;

            // Title
            div.querySelector('h3.lizmap-presentation-title').innerHTML = `
                ${presentation.title}<span class="pull-right">${presentation.id}</span>
            `;

            // Description
            div.querySelector('p.lizmap-presentation-description').innerHTML = presentation.description;

            // Detailed information
            const table = div.querySelector('table.presentation-detail-table');
            const fields = [
                'footer', 'published', 'granted_groups',
                'created_by', 'created_at', 'updated_by', 'updated_at'
            ];
            fields.forEach(field => {
                table.querySelector(`td.presentation-detail-${field}`).innerHTML = presentation[field];
            })

            // Buttons
            const detailButton = div.querySelector('button.liz-presentation-detail')
            detailButton.value = presentation.id;
            detailButton.innerText = (cardDisplay != 'detail') ? detailButton.dataset.label : detailButton.dataset.reverseLabel;
            div.querySelector('button.liz-presentation-edit').value = presentation.id;
            div.querySelector('button.liz-presentation-delete').value = presentation.id;
            div.querySelector('button.liz-presentation-launch').value = presentation.id;
            div.querySelector('button.liz-presentation-create.page').value = presentation.id;

            // Pages
            let pageContainer = div.querySelector('div.lizmap-presentation-card-pages');
            const pagePreviewTemplate = document.getElementById('lizmap-presentation-page-preview-template');
            const previewHtml = pagePreviewTemplate.innerHTML;
            presentation.pages.forEach(page => {
                let pageDiv = document.createElement('div');
                pageDiv.classList.add('lizmap-presentation-page-preview');
                pageDiv.innerHTML = previewHtml;
                pageDiv.querySelector('h3.lizmap-presentation-page-preview-title').innerHTML = `
                    ${page.title}<span class="pull-right">${page.page_order}</span>
                `;

                // Detailed information
                const pageTable = pageDiv.querySelector('table.presentation-detail-table');
                const pageFields = [
                    'description', 'background_image'
                ];
                pageFields.forEach(field => {
                    const pageTd = pageTable.querySelector(`td.presentation-page-${field}`);
                    if (pageTd) {
                        pageTd.innerHTML = page[field];
                    }
                })

                pageDiv.querySelector('button.liz-presentation-edit').value = page.id;
                pageDiv.querySelector('button.liz-presentation-delete').value = page.id;
                pageContainer.appendChild(pageDiv);
            })


            // Add the card to the parent
            this.appendChild(div);
        }

        // Add click event on the create button
        const createButtons = this.querySelectorAll("button.liz-presentation-create");
        Array.from(createButtons).forEach(createButton => {
            createButton.addEventListener("click", this.onButtonCreateClick);
        });

        // Add click event on the presentation cards buttons
        const buttons = this.querySelectorAll("div.lizmap-presentation-card-toolbar button, div.lizmap-presentation-page-preview-toolbar button");
        Array.from(buttons).forEach(button => {
            const classes = button.classList;
            if (classes.contains('liz-presentation-edit')) {
                button.addEventListener('click', this.onButtonEditClick);
            } else if (classes.contains('liz-presentation-delete')) {
                button.addEventListener('click', this.onButtonDeleteClick);
            } else if (classes.contains('liz-presentation-detail')) {
                button.addEventListener('click', this.onButtonDetailClick);
            } else if (classes.contains('liz-presentation-launch')) {
                button.addEventListener('click', this.onButtonLaunchClick);
            }
        });
    }

    connectedCallback() {
        this.load();
    }

    static get observedAttributes() { return ['updated']; }

    attributeChangedCallback(name, oldValue, newValue) {
        // Listen to the change of the updated attribute
        // This will trigger the load (refresh the content)
        if (name === 'updated') {
            this.load();
        }
    }

    getPresentationById(presentationId) {
        for (let a in this.presentations) {
            let presentation = this.presentations[a];
            if (presentation.id == presentationId) {
                return presentation;
            }
        }

        return null;
    }

    onButtonCreateClick(event) {
        // Get the host component
        const host = event.target.closest("lizmap-presentation-cards");
        const button = event.currentTarget;
        const item = (button.classList.contains('presentation')) ? 'presentation' : 'page';
        if (item == 'presentation') {
            mainLizmap.presentation.launchPresentationCreationForm(item);
        } else {
            const presentation_id = button.value;
            mainLizmap.presentation.launchPresentationCreationForm(item, null, presentation_id);
        }
    }

    onButtonEditClick(event) {
        const button = event.currentTarget;
        const item = (button.classList.contains('presentation')) ? 'presentation' : 'page';
        const id = button.value;
        mainLizmap.presentation.launchPresentationCreationForm(item, id);
    }

    onButtonDetailClick(event) {
        const host = event.target.closest("lizmap-presentation-cards");
        const button = event.currentTarget;
        const presentationId = button.value;

        // Chosen card
        const chosenCard = host.querySelector(`[data-id='${presentationId}']`);
        const isActive = (chosenCard.dataset.display == 'detail');

        // Set other cards status
        host.presentations.forEach(item => {
            const card = host.querySelector(`[data-id='${item.id}']`);
            const display = (isActive) ? 'normal' : 'none';
            card.dataset.display = display;
        })

        // Set the clicked card display property
        chosenCard.dataset.display = (isActive) ? 'normal' : 'detail';

        // Set its detail button label & title
        button.innerText = (isActive) ? button.dataset.label : button.dataset.reverseLabel;
        button.setAttribute('title', (isActive) ? button.dataset.title : button.dataset.reverseTitle);

        // Set the full panel class
        const parentDiv = document.getElementById('presentation-container');
        parentDiv.dataset.display = (isActive) ? 'normal' : 'detail';
    }

    onButtonDeleteClick(event) {
        const host = event.target.closest("lizmap-presentation-cards");
        const button = event.currentTarget;
        const item = (button.classList.contains('presentation')) ? 'presentation' : 'page';
        const id = button.value;
        // Confirmation message
        const confirmMessage = button.dataset.confirm;
        const areYourSure = window.confirm(confirmMessage);
        if (!areYourSure) {
            console.log('Delete aborted');

            return false;
        }
        mainLizmap.presentation.deletePresentation(item, id);

        // Set all presentations visible
        const parentDiv = document.getElementById('presentation-container');
        parentDiv.dataset.display = 'normal';
        host.presentations.forEach(item => {
            const card = host.querySelector(`[data-id='${item.id}']`);
            card.dataset.display = 'normal';
        })
    }

    onButtonLaunchClick(event) {
        const button = event.currentTarget;
        const presentationId = button.value;
        mainLizmap.presentation.runLizmapPresentation(presentationId);
    }

    disconnectedCallback() {
        // Remove click events on the presentation buttons
        const createButton = this.querySelector("button.liz-presentation-create");
        createButton.removeEventListener("click", this.onButtonCreateClick);
        const buttons = this.querySelectorAll("div.lizmap-presentation-card-toolbar button, div.lizmap-presentation-page-preview-toolbar button");
        Array.from(buttons).forEach(button => {
            if (button.classList.contains('liz-presentation-edit')) {
                button.removeEventListener('click', this.onButtonEditClick);
            } else if (button.classList.contains('liz-presentation-delete')) {
                button.removeEventListener('click', this.onButtonDeleteClick);
            } else if (button.classList.contains('liz-presentation-detail')) {
                button.removeEventListener('click', this.onButtonDetailClick);
            } else if (button.classList.contains('liz-presentation-launch')) {
                button.removeEventListener('click', this.onButtonLaunchClick);
            }
        });
    }
}
