import { mainEventDispatcher } from '../modules/Globals.js';

/**
 * Proxy old Lizmap events to new ones
 *
 * @export
 * @class ProxyEvents
 */
export default class ProxyEvents {
    constructor() {
        lizMap.events.on({
            layerSelectionChanged: () => {
                mainEventDispatcher.dispatch({
                    type: 'selection.changed'
                });
            },
            layerFilteredFeaturesChanged: () => {
                mainEventDispatcher.dispatch({
                    type: 'filteredFeatures.changed'
                });
            }
        });
    }
}
