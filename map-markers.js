
function createNumberedMarker(lat, lng, number, color) {
    const markerHtmlStyles = `
        background-color: ${color};
        color: white;
        font-weight: bold;
        border-radius: 50%;
        width: 30px;
        height: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
        box-shadow: 0 0 3px #333;
    `;

    const icon = L.divIcon({
        className: "leaflet-div-icon",
        html: `<div style="${markerHtmlStyles}">${number}</div>`
    });

    return L.marker([lat, lng], { icon: icon });
}

// Example usage:
function addTourMarkers(map, tourStops, color) {
    for (let i = 0; i < tourStops.length; i++) {
        const stop = tourStops[i];
        const marker = createNumberedMarker(stop.lat, stop.lng, i + 1, color);
        marker.addTo(map);
    }
}
