// Additional JavaScript if needed
    // Global reference to store the map instance
    let appMap = null;

    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('coordinateForm');
        const resultModalElement = document.getElementById('resultModal');
        const processingModalElement = document.getElementById('processingModal');
        const resultModal = new bootstrap.Modal(resultModalElement);
        const processingModal = new bootstrap.Modal(processingModalElement, {
            backdrop: 'static',
            keyboard: false
        });

        // Event listener for when the resultModal is about to be shown
        resultModalElement.addEventListener('show.bs.modal', function () {
            if (processingModalElement.classList.contains('show')) {
                processingModal.hide();
            }
        });

        // Event listener for when the processingModal is about to be shown
        processingModalElement.addEventListener('show.bs.modal', function () {
            if (resultModalElement.classList.contains('show')) {
                resultModal.hide();
            }
        });

        // Initialize map if it exists on page load
        //initMap();
        showMap()
        updateInputField(); // Call updateInputField on DOMContentLoaded

        form.addEventListener('submit', function (event) {
            event.preventDefault();

            processingModal.show();

            const formData = new FormData(form);

            fetch('', { // Make sure this is the correct URL for your processing script
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    console.log('Response data:', data);
                    const isSuccess = data.isSuccess;
                    const newValidityContainer_innerHTML = data.validityMessage;
                    const newCoordinatesTableContainer_innerHTML = data.coordinatesTable;

                    const newMapData = data.mapData;
                    const newAddislandHref = data.addislandHref;
                    const newGoogleMapHref = data.googleMapHref;

                    const existingValidityContainer = document.getElementById('validity-text-container');
                    

                    if (newValidityContainer_innerHTML) {
                        existingValidityContainer.innerHTML = newValidityContainer_innerHTML;

                    } else {
                        //existingValidityContainer.innerHTML = '';
                    }
                    const existingTableContainer = document.getElementById('coordinates-table-container');
                    if (newCoordinatesTableContainer_innerHTML) {
                        existingTableContainer.innerHTML = newCoordinatesTableContainer_innerHTML;
                    } else {
                        existingTableContainer.innerHTML = '';
                    }



                    // Use the mapData if it exists
                    if (data.mapData) {
                        // Initialize map (example using Leaflet.js)
                        showMap(data.mapData); // Re-initialize map after adding/replacing
                    } else {
                        showMap();
                    }

                    processingModal.hide();

                    document.getElementById('resultModalTitle').textContent = isSuccess ? 'Success' : 'Error';
                    document.getElementById('resultModalBody').innerHTML =
                        isSuccess ? '✅ Successfully located the property' : '❌ Failed to locate the property';
                    resultModal.show();


                   // Set up button handlers
                    document.getElementById('openGoogleMap').onclick = () => {
                        window.open(newGoogleMapHref, '_blank');
                    };

                    document.getElementById('openAddisland').onclick = () => {
                        window.open(newAddislandHref, '_blank');
                    };

                })
                .catch(error => {
                    console.error('Error:', error);
                    processingModal.hide();
                    document.getElementById('resultModalTitle').textContent = 'Error';
                    document.getElementById('resultModalBody').innerHTML =
                        '<div class="alert alert-danger">Failed to process request. Please try again.</div>';
                    resultModal.show();
                });
        });



        

        function destroyMap() {
            const mapContainer = document.getElementById('map');

            // Remove Leaflet instance if it exists
            if (appMap) {
                appMap.remove();
                appMap = null;
            }

            // Clear lingering Leaflet ID and contents
            if (mapContainer) {
                if (mapContainer._leaflet_id) {
                    mapContainer._leaflet_id = null;
                }
                mapContainer.innerHTML = '';
            }
        }

        function initializeMap(center, zoom) {
            const mapContainer = document.getElementById('map');

            // 1. Check if container exists
            if (!mapContainer) {
                console.error('Map container not found');
                return null;
            }

            // 2. Clean up any existing map before initializing
            destroyMap();

            // 3. Initialize new map with error handling
            try {
                appMap = L.map('map').setView(center, zoom);

                // Add base layer
                L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
                    attribution: 'Tiles © Esri — Source: Esri, Maxar, Earthstar Geographics'
                }).addTo(appMap);

                return appMap;
            } catch (e) {
                console.error('Map initialization failed:', e);
                return null;
            }
        }




        function showMap(mapDataGiven = {}) {

            // Default values
            const defaults = {
                center: [9.005, 38.763],  // Default to Addis Ababa coordinates
                zoom: 15,
                lats: [],
                lons: []
            };

            // Merge input with defaults
            const mapData = {
                ...defaults,
                ...mapDataGiven
            };

            const mapContainer = document.getElementById('map-container');
            if (!mapContainer) {
                console.error('Map container element not found');
                return;
            }

            let center = [9.005, 38.763]; // Default center as simple array
            let polygonCoords = [];
            let zoom = 18;


            // Use the data directly (no JSON.parse needed if PHP used json_encode)
            if (mapData.center && Array.isArray(mapData.center)) {
                center = mapData.center;
            }

            // Create coordinates array from lats/lons
            if (mapData.lats && mapData.lons &&
                mapData.lats.length === mapData.lons.length) {
                polygonCoords = mapData.lats.map((lat, i) => [lat, mapData.lons[i]]);
            }

            if (mapData.zoom) {
                zoom = mapData.zoom;
            }

            // Initialize or recreate map
            const map = initializeMap(center, zoom);

            if (!map) return; // Exit if map failed to initialize

            if (polygonCoords.length > 0) {
                L.polygon(polygonCoords, { color: 'blue', weight: 2.5, opacity: 1, fillOpacity: 0.3 }).addTo(map);

                polygonCoords.forEach((coord, index) => {
                    // Add markers for all points, including the closing point if it's a polygon
                    L.marker(coord).addTo(map)
                        .bindPopup(`Point ${index + 1}<br>Lat: ${coord[0].toFixed(6)}<br>Lon: ${coord[1].toFixed(6)}`);
                });

                // Fit map to polygon bounds
                const bounds = L.latLngBounds(polygonCoords);
                map.fitBounds(bounds);
            }
            // Store the map instance on the element
            mapContainer._map = map;
        }


        function updateInputField() {
            const region = document.getElementById('region').value;
            const inputTypeSelect = document.getElementById('input_type');
            const inputLabel = document.getElementById('input_label');
            const inputField = document.getElementById('title_deed');
            const formatText = document.getElementById('format_text');
            const addislandBtn = document.getElementById('openAddisland');
            const titleDeedOptionValue = 'title_deed';
            const existingTitleDeedOption = inputTypeSelect.querySelector(`option[value="${titleDeedOptionValue}"]`);

            if (region === 'Addis Ababa') {
                // Ensure title deed option exists for Addis Ababa
                if (!existingTitleDeedOption) {
                    const titleDeedOption = document.createElement('option');
                    titleDeedOption.value = titleDeedOptionValue;
                    titleDeedOption.textContent = 'Title Deed Number';
                    inputTypeSelect.insertBefore(titleDeedOption, inputTypeSelect.firstChild);
                }

                // Set default to title deed if not set or if coordinates was selected for a non-Addis region
                if (inputTypeSelect.value !== titleDeedOptionValue && inputTypeSelect.value !== 'coordinates') {
                    inputTypeSelect.value = titleDeedOptionValue;
                }


                // Update based on selected input type
                if (inputTypeSelect.value === titleDeedOptionValue) {
                    inputLabel.textContent = 'Enter Title Deed Number';
                    inputField.placeholder = 'Enter Title Deed Number like AK1181145161502962';
                    formatText.innerHTML = 'Input Format: <br>Title deed number like AK1181145161502962';
                    addislandBtn.style.display = 'block';
                } else { // inputTypeSelect.value === 'coordinates'
                    inputLabel.textContent = 'Enter XY Coordinates';
                    inputField.placeholder = 'Enter XY Coordinates like [477516.781, 980918.698; 477506.273, 980926.353]';
                    formatText.innerHTML = 'Input Format: <br>XY Coordinates like [477516.781, 980918.698; 477506.273, 980926.353]';
                    addislandBtn.style.display = 'none';
                }
            } else {
                // For other regions, remove title deed option if it exists
                if (existingTitleDeedOption) {
                    inputTypeSelect.removeChild(existingTitleDeedOption);
                }

                // Force coordinates selection
                inputTypeSelect.value = 'coordinates';
                inputLabel.textContent = 'Enter XY Coordinates';
                inputField.placeholder = 'Enter XY Coordinates like [477516.781, 980918.698; 477506.273, 980926.353]';
                formatText.innerHTML = 'Input Format: <br>XY Coordinates like [477516.781, 980918.698; 477506.273, 980926.353]';
                addislandBtn.style.display = 'none';
            }
            // Removed the line that dispatches the 'change' event on inputTypeSelect
            // const event = new Event('change');
            // inputTypeSelect.dispatchEvent(event);
        }

    });
