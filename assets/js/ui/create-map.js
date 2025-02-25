import L from "leaflet";

export const DEFAULT_POSITION = {
    lat: 47.0504,
    lng: 2.2347
};
export const DEFAULT_ZOOM = 6;

const MARKER_ICON = L.Icon.extend({
    options: {
        shadowUrl: '/media/map/marker-shadow.png',
        iconUrl: '/media/map/marker-icon.png',
        iconSize: [24,40],
        iconAnchor: [12,40]//correctly replaces the dot of the pointer
    }
});

export function createMap(
    elementIdAttr,
    lat = DEFAULT_POSITION.lat,
    lng = DEFAULT_POSITION.lng,
    zoom = DEFAULT_ZOOM,
    hasZoomControl = true,
    isDraggable = true,
    hasUniqueMarker = false,
) {
    const map = L.map(elementIdAttr, {zoomControl: hasZoomControl}).setView([lat, lng], zoom);
    map.markers = [];

    L.tileLayer(
    'https://osm.tela-botanica.org/tuiles/osmfr/{z}/{x}/{y}.png', {
        attribution: 'Data © <a href="http://osm.org/copyright">OpenStreetMap</a>',
        maxZoom: 18
    }).addTo(map);

    map.addLayer(new L.FeatureGroup());

    const options = {draggable: isDraggable};

    if (hasUniqueMarker) {
        options.icon = new MARKER_ICON();
    }

    let marker = new L.Marker(
        {
            'lat': lat,
            'lng': lng
        },
        options
    );

    map.addLayer(marker);

    if (hasUniqueMarker) {
        map.marker = marker
    } else {
        map.markers.push(marker);
    }

    return map;
}
