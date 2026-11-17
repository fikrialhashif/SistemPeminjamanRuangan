(function () {
    'use strict';

    if (!window.L) {
        document.querySelectorAll('[data-map-status]').forEach(function (status) {
            status.textContent = 'Peta tidak dapat dimuat. Isi koordinat secara manual.';
        });

        return;
    }

    document.querySelectorAll('[data-location-editor]').forEach(setupEditor);

    function setupEditor(editor) {
        var form = editor.closest('form');
        var dialog = editor.closest('dialog');
        var mapEl = editor.querySelector('[data-location-map]');
        var latInput = editor.querySelector('[data-lat-input]');
        var lngInput = editor.querySelector('[data-lng-input]');
        var radiusInput = editor.querySelector('[data-radius-input]');
        var radiusField = editor.querySelector('[data-radius-field]');
        var polygonField = editor.querySelector('[data-polygon-field]');
        var polygonInput = editor.querySelector('[data-polygon-input]');
        var polygonCount = editor.querySelector('[data-polygon-count]');
        var statusEl = editor.querySelector('[data-map-status]');
        var hintEl = editor.querySelector('[data-map-hint]');
        var actionsEl = editor.querySelector('[data-map-actions]');
        var undoButton = editor.querySelector('[data-map-undo]');
        var clearButton = editor.querySelector('[data-map-clear]');

        if (!form || !mapEl || !latInput || !lngInput || !radiusInput) {
            return;
        }

        var map = null;
        var centerMarker = null;
        var circle = null;
        var polygonLayer = null;
        var vertexMarkers = [];
        var points = readPoints(polygonInput ? polygonInput.value : '');
        var mode = getMode();
        var initialized = false;

        function getMode() {
            var checked = editor.querySelector('input[name="geofence_type"]:checked');

            return checked ? checked.value : 'radius';
        }

        function readPoints(raw) {
            try {
                var parsed = JSON.parse(raw || '[]');

                if (!Array.isArray(parsed)) {
                    return [];
                }

                return parsed
                    .map(function (point) {
                        return {
                            lat: parseFloat(point && point.lat),
                            lng: parseFloat(point && point.lng),
                        };
                    })
                    .filter(function (point) {
                        return Number.isFinite(point.lat) && Number.isFinite(point.lng);
                    });
            } catch (error) {
                return [];
            }
        }

        function readCoordinate(input, fallback) {
            var value = parseFloat(input.value);

            return Number.isFinite(value) ? value : fallback;
        }

        function currentCenter() {
            var lat = readCoordinate(latInput, -6.2);
            var lng = readCoordinate(lngInput, 106.816666);

            return [lat, lng];
        }

        function writeCenter(lat, lng) {
            latInput.value = lat.toFixed(7);
            lngInput.value = lng.toFixed(7);
        }

        function centroid() {
            if (!points.length) {
                return currentCenter();
            }

            var lat = points.reduce(function (total, point) {
                return total + point.lat;
            }, 0) / points.length;
            var lng = points.reduce(function (total, point) {
                return total + point.lng;
            }, 0) / points.length;

            return [lat, lng];
        }

        function syncPolygonValue() {
            if (!polygonInput) {
                return;
            }

            polygonInput.value = mode === 'polygon' ? JSON.stringify(points) : '';
        }

        function updateStatus() {
            if (statusEl) {
                statusEl.classList.remove('is-error');

                if (mode === 'polygon') {
                    statusEl.textContent = points.length < 3
                        ? points.length + ' titik. Tambahkan minimal 3 titik.'
                        : points.length + ' titik. Polygon siap disimpan.';
                } else {
                    statusEl.textContent = 'Radius ' + (parseInt(radiusInput.value, 10) || 0) + ' m dari titik pusat.';
                }
            }

            if (polygonCount) {
                polygonCount.textContent = points.length + ' titik';
            }
        }

        function updateVertexLayer() {
            if (!polygonLayer) {
                return;
            }

            polygonLayer.setLatLngs(points.map(function (point) {
                return [point.lat, point.lng];
            }));
        }

        function clearVertexMarkers() {
            if (!map) {
                return;
            }

            vertexMarkers.forEach(function (marker) {
                map.removeLayer(marker);
            });
            vertexMarkers = [];
        }

        function renderVertexMarkers() {
            clearVertexMarkers();

            if (!map || mode !== 'polygon') {
                return;
            }

            points.forEach(function (point, index) {
                var marker = L.marker([point.lat, point.lng], {
                    draggable: true,
                    icon: L.divIcon({
                        className: 'map-vertex',
                        html: '',
                        iconSize: [16, 16],
                        iconAnchor: [8, 8],
                    }),
                }).addTo(map);

                marker.on('drag', function (event) {
                    var position = event.target.getLatLng();
                    points[index] = { lat: position.lat, lng: position.lng };
                    updateVertexLayer();
                    syncPolygonValue();
                    writeCenter(centroid()[0], centroid()[1]);
                    updateStatus();
                });

                marker.on('contextmenu', function (event) {
                    if (event.originalEvent) {
                        L.DomEvent.preventDefault(event.originalEvent);
                    }

                    removePoint(index);
                });

                vertexMarkers.push(marker);
            });
        }

        function syncRadiusLayer() {
            var center = currentCenter();
            var radius = parseInt(radiusInput.value, 10) || 0;

            if (centerMarker && circle) {
                centerMarker.setLatLng(center);
                circle.setLatLng(center);
                circle.setRadius(radius);
            }
        }

        function setCenter(lat, lng) {
            writeCenter(lat, lng);
            syncRadiusLayer();
        }

        function updatePolygon() {
            updateVertexLayer();
            syncPolygonValue();
            updateStatus();
        }

        function addPoint(latlng) {
            points.push({ lat: latlng.lat, lng: latlng.lng });
            renderVertexMarkers();
            updatePolygon();
            writeCenter(centroid()[0], centroid()[1]);
        }

        function removePoint(index) {
            points.splice(index, 1);
            renderVertexMarkers();
            updatePolygon();
            writeCenter(centroid()[0], centroid()[1]);
        }

        function readModeInputs() {
            editor.querySelectorAll('input[name="geofence_type"]').forEach(function (input) {
                input.addEventListener('change', function () {
                    if (input.checked) {
                        setMode(input.value);
                    }
                });
            });
        }

        function setMode(nextMode) {
            mode = nextMode;
            var isPolygon = mode === 'polygon';

            radiusField.hidden = isPolygon;
            radiusInput.disabled = isPolygon;
            polygonField.hidden = !isPolygon;
            actionsEl.hidden = !isPolygon;
            latInput.readOnly = isPolygon;
            lngInput.readOnly = isPolygon;

            if (hintEl) {
                hintEl.textContent = isPolygon
                    ? 'Klik peta untuk menambah titik. Seret titik untuk menyesuaikan, klik kanan untuk menghapus.'
                    : 'Klik peta atau seret pin untuk memindahkan pusat.';
            }

            if (!map) {
                updateStatus();
                return;
            }

            if (isPolygon) {
                if (map.hasLayer(centerMarker)) {
                    map.removeLayer(centerMarker);
                }
                if (map.hasLayer(circle)) {
                    map.removeLayer(circle);
                }
                if (!map.hasLayer(polygonLayer)) {
                    polygonLayer.addTo(map);
                }

                renderVertexMarkers();
                updateVertexLayer();
                writeCenter(centroid()[0], centroid()[1]);
                map.setView(centroid(), map.getZoom());
            } else {
                if (map.hasLayer(polygonLayer)) {
                    map.removeLayer(polygonLayer);
                }
                clearVertexMarkers();

                if (!map.hasLayer(centerMarker)) {
                    centerMarker.addTo(map);
                }
                if (!map.hasLayer(circle)) {
                    circle.addTo(map);
                }

                syncRadiusLayer();
                map.setView(currentCenter(), map.getZoom());
            }

            syncPolygonValue();
            updateStatus();
        }

        function initMap() {
            if (initialized) {
                setTimeout(function () {
                    map.invalidateSize();
                }, 40);

                return;
            }

            initialized = true;

            var center = currentCenter();

            map = L.map(mapEl, {
                zoomControl: true,
                scrollWheelZoom: true,
                doubleClickZoom: false,
                maxZoom: 19,
            }).setView(center, 17);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
            }).addTo(map);

            centerMarker = L.marker(center, { draggable: true }).addTo(map);
            centerMarker.on('drag', function (event) {
                var position = event.target.getLatLng();
                writeCenter(position.lat, position.lng);
                circle.setLatLng(position);
                updateStatus();
            });

            circle = L.circle(center, {
                radius: parseInt(radiusInput.value, 10) || 0,
                color: '#2b5f4a',
                weight: 2,
                fillColor: '#2b5f4a',
                fillOpacity: 0.12,
            }).addTo(map);

            polygonLayer = L.polygon([], {
                color: '#a96516',
                weight: 2,
                fillColor: '#eca017',
                fillOpacity: 0.18,
            });

            map.on('click', function (event) {
                if (mode === 'polygon') {
                    addPoint(event.latlng);
                } else {
                    setCenter(event.latlng.lat, event.latlng.lng);
                }
            });

            radiusInput.addEventListener('input', function () {
                syncRadiusLayer();
                updateStatus();
            });

            [latInput, lngInput].forEach(function (input) {
                input.addEventListener('change', function () {
                    if (mode !== 'radius') {
                        return;
                    }

                    var center = currentCenter();
                    centerMarker.setLatLng(center);
                    circle.setLatLng(center);
                    map.panTo(center);
                });
            });

            if (undoButton) {
                undoButton.addEventListener('click', function () {
                    if (points.length) {
                        removePoint(points.length - 1);
                    }
                });
            }

            if (clearButton) {
                clearButton.addEventListener('click', function () {
                    points = [];
                    renderVertexMarkers();
                    updatePolygon();
                    writeCenter(currentCenter()[0], currentCenter()[1]);
                });
            }

            form.addEventListener('submit', function (event) {
                if (mode === 'polygon' && points.length < 3) {
                    event.preventDefault();

                    if (statusEl) {
                        statusEl.textContent = 'Tambahkan minimal 3 titik polygon sebelum menyimpan.';
                        statusEl.classList.add('is-error');
                    }
                }
            }, true);

            readModeInputs();
            setMode(mode);

            setTimeout(function () {
                map.invalidateSize();

                if (mode === 'polygon' && points.length >= 3) {
                    map.fitBounds(L.latLngBounds(points.map(function (point) {
                        return [point.lat, point.lng];
                    })), { padding: [26, 26] });
                } else if (mode === 'radius' && circle) {
                    map.fitBounds(circle.getBounds(), { padding: [26, 26], maxZoom: 18 });
                }
            }, 80);
        }

        if (dialog) {
            dialog.addEventListener('dialog:open', initMap);
        } else {
            initMap();
        }
    }
})();
