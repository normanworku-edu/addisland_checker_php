<!DOCTYPE html>
<html lang="en">
<head>
    <?php
    require_once 'functions.php';
    ?>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EthioLand Checker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">

    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</head>

<body>

    <main>
        <?php
        $titleDeedNumber = '';
        $validityMessage = '<div class="alert alert-info">Enter valid title deed number like AK1181145161502962 or Valid XY Coordinates like [477516.781, 980918.698; 477506.273, 980926.353] and Click Check</div>';
        $googleMapHref = 'https://maps.app.goo.gl/jr4S1yaX8NeVXFsa8';
        $addislandHref = 'https://www.addisland.gov.et/';
        $coordinatesTable = '';
        $mapData = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $titleDeedNumber = trim($_POST['title_deed'] ?? '');
            $inputType = $_POST['input_type'] ?? 'title_deed'; // Get input type
            $region = $_POST['region'] ?? 'Addis Ababa'; // Get region

            // Basic validation based on input type and region
            if ($region === 'Addis Ababa' && $inputType === 'title_deed' && empty($titleDeedNumber)) {
                 $validityMessage = '<div class="alert alert-warning">Please enter a title deed number.</div>';
            } elseif ($inputType === 'coordinates' && empty($titleDeedNumber)) {
                 $validityMessage = '<div class="alert alert-warning">Please enter XY coordinates.</div>';
            } elseif (!empty($titleDeedNumber)) {
                // Pass input type and region to extractCoordinates if needed
                $result = extractCoordinates($titleDeedNumber, $inputType, $region);

                if (isset($result['error'])) {
                    $validityMessage = '<div class="alert alert-danger">' . $result['error'] . '</div>';
                    $coordinatesTable = ''; // Clear the table on error
                    $mapData = null; // Clear map data on error
                } else {
                    $eastingNorthingMatrix = $result['coordinates'];
                    // Update addislandHref only if it's provided in the result
                    if (isset($result['addisland_url'])) {
                         $addislandHref = $result['addisland_url'];
                    }


                    list($lats, $lons) = convertToLatLon($eastingNorthingMatrix);

                    // Calculate center only if coordinates are available
                    if (!empty($lats)) {
                        $centerLat = array_sum($lats) / count($lats);
                        $centerLon = array_sum($lons) / count($lons);
                        $googleMapHref = generateGoogleMapsLink($centerLat, $centerLon);

                        // Generate coordinates table
                        $tableRows = '';
                        foreach ($lats as $i => $lat) {
                            $lon = $lons[$i];
                            $tableRows .= "<tr><td class='text-nowrap'>{$lat}</td><td class='text-nowrap'>{$lon}</td></tr>";
                        }

                        $coordinatesTable = "
                            <div class='card shadow-sm mb-4'>
                                <div class='card-header bg-success text-white'>
                                    <h5 class='mb-0'><i class='bi bi-table me-2'></i>Google Map Coordinates for " . htmlspecialchars($titleDeedNumber) . "</h5>
                                </div>
                                <div class='card-body'>
                                    <div class='table-responsive'>
                                        <table class='table table-bordered table-hover mb-0'>
                                            <thead class='table-light'>
                                                <tr>
                                                    <th>Latitude</th>
                                                    <th>Longitude</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {$tableRows}
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        ";

                        $validityMessage = '<div class="alert alert-success">✅ Valid Input: Successfully retrieved coordinates.</div>';

                        // Prepare data for the map
                        $mapData = [
                            'lats' => $lats,
                            'lons' => $lons,
                            'center' => [$centerLat, $centerLon]
                        ];
                    } else {
                         $validityMessage = '<div class="alert alert-warning">No coordinates found for the provided input.</div>';
                         $coordinatesTable = '';
                         $mapData = null;
                    }
                }
            }
        }
        ?>

        <div class="container mt-4">
            <div class="row justify-content-center">
                <div class="col-12 col-lg-10 col-xl-8">

                    <header class="text-center m-4">
                        <h1>Welcome to EthioLand Checker</h1>
                        <p class="lead">Check validity and get google map location of land coordinates in Ethiopia using
                            title deed
                            numbers or Just XY coordinates.</p>
                    </header>

                    <div class="card shadow-sm mb-4 w-100">
                        <div class="card-header bg-primary text-white">
                            <h4 class="mb-0"><i class="bi bi-card-checklist me-2"></i>Title Deed/Coordinate Validator
                            </h4>
                        </div>

                        <div class="card-body">
                            <form method="POST" id="coordinateForm">
                                <div class="mb-3">
                                    <label for="region" class="form-label fw-bold">Select Region</label>
                                    <select class="form-select" id="region" name="region" required
                                        onchange="updateInputField()">
                                        <option value="" disabled>Select a region</option>
                                        <option value="Addis Ababa" selected>Addis Ababa</option>
                                        <option value="Afar">Afar</option>
                                        <option value="Amhara">Amhara</option>
                                        <option value="Benishangul-Gumuz">Benishangul-Gumuz</option>
                                        <option value="Dire Dawa">Dire Dawa</option>
                                        <option value="Gambela">Gambela</option>
                                        <option value="Harari">Harari</option>
                                        <option value="Oromia">Oromia</option>
                                        <option value="Sidama">Sidama</option>
                                        <option value="Somali">Somali</option>
                                        <option value="South West Ethiopia">South West Ethiopia</option>
                                        <option value="Central Ethiopia">Central Ethiopia</option>
                                        <option value="South Ethiopia">South Ethiopia</option>
                                        <option value="Tigray">Tigray</option>
                                    </select>
                                </div>

                                <div class="mb-3" id="input_type_container">
                                    <label for="input_type" class="form-label fw-bold">Input Type</label>
                                    <select class="form-select" id="input_type" name="input_type"
                                        onchange="updateInputField()">
                                        <option value="title_deed" selected>Title Deed Number</option>
                                        <option value="coordinates">XY Coordinates</option>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label for="title_deed" class="form-label fw-bold" id="input_label">Enter Title Deed
                                        Number</label>
                                    <input type="text" class="form-control" id="title_deed" name="title_deed"
                                        style="min-height: 60px;"
                                        placeholder="Enter Title Deed Number like AK1181145161502962" required>
                                </div>
                                <div class="text mb-3" id="format_text">Input Format: <br>Title deed number like
                                    AK1181145161502962</div>

                                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                    <button type="submit" class="btn btn-primary me-md-2 px-4">
                                        <i class="bi bi-check-circle me-2"></i>Check
                                    </button>
                                    <button type="button" id="openGoogleMap"
                                        class="btn btn-outline-primary me-md-2 px-4">
                                        <i class="bi bi-map me-2"></i>Google Map
                                    </button>
                                    <button type="button" id="openAddisland" class="btn btn-outline-secondary px-4">
                                        <i class="bi bi-file-earmark-text me-2"></i>Addisland Doc
                                    </button>
                                </div>
                            </form>

                            <script>
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
                                    initMap();
                                    updateInputField(); // Call updateInputField on DOMContentLoaded

                                    form.addEventListener('submit', function (event) {
                                        event.preventDefault();

                                        processingModal.show();

                                        const formData = new FormData(form);

                                        fetch('', { // Make sure this is the correct URL for your processing script
                                            method: 'POST',
                                            body: formData
                                        })
                                            .then(response => response.text())
                                            .then(html => {
                                                const parser = new DOMParser();
                                                const doc = parser.parseFromString(html, 'text/html');

                                                const newValidityContainer = doc.getElementById('validity-container');
                                                const newCoordinatesTableContainer = doc.getElementById('coordinates-table-container');
                                                const newMapCard = doc.querySelector('.card.shadow-sm.mb-4:has(#map)');

                                                if (newValidityContainer) {
                                                    document.getElementById('validity-container').innerHTML = newValidityContainer.innerHTML;
                                                }

                                                const existingTableContainer = document.getElementById('coordinates-table-container');
                                                if (newCoordinatesTableContainer) {
                                                    existingTableContainer.innerHTML = newCoordinatesTableContainer.innerHTML;
                                                } else {
                                                    existingTableContainer.innerHTML = '';
                                                }

                                                const existingMapCard = document.querySelector('.card.shadow-sm.mb-4:has(#map)');
                                                if (newMapCard) {
                                                    if (existingMapCard) {
                                                        existingMapCard.replaceWith(newMapCard.cloneNode(true));
                                                    } else {
                                                        const lastCard = document.querySelector('.card.shadow-sm.mb-4:last-of-type');
                                                        if (lastCard) {
                                                            lastCard.after(newMapCard.cloneNode(true));
                                                        } else {
                                                            document.querySelector('#validity-container').after(newMapCard.cloneNode(true));
                                                        }
                                                    }
                                                    initMap(); // Re-initialize map after adding/replacing
                                                } else if (existingMapCard) {
                                                    existingMapCard.remove();
                                                }

                                                processingModal.hide();

                                                const isSuccess = newValidityContainer &&
                                                    (newValidityContainer.querySelector('.alert-success') ||
                                                        newValidityContainer.querySelector('.alert-info'));

                                                document.getElementById('resultModalTitle').textContent = isSuccess ? 'Success' : 'Error';
                                                document.getElementById('resultModalBody').innerHTML =
                                                    isSuccess ? '✅ Successfully located the property' : '❌ Failed to locate the property';
                                                resultModal.show();
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

                                    function initMap() {
                                        const mapElement = document.getElementById('map');
                                        // Check if mapElement exists and if Leaflet map is not already initialized
                                        if (mapElement && !mapElement._leaflet_id) { // Use _leaflet_id to check if map is initialized
                                            const centerData = mapElement.dataset.center;
                                            const coordsData = mapElement.dataset.coords;

                                            let center = [[9.005, 38.763]]; // Default center
                                            let polygonCoords = [];

                                            try {
                                                if (centerData) {
                                                    center = JSON.parse(centerData);
                                                }
                                                if (coordsData) {
                                                    polygonCoords = JSON.parse(coordsData);
                                                }
                                            } catch (e) {
                                                console.error("Error parsing map data:", e);
                                                // Use default values if parsing fails
                                                center = [[9.005, 38.763]];
                                                polygonCoords = [];
                                            }


                                            const map = L.map('map').setView(center[0], 18);

                                            L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
                                                attribution: 'Tiles © Esri — Source: Esri, Maxar, Earthstar Geographics'
                                            }).addTo(map);

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
                                            mapElement._map = map;
                                        } else if (mapElement && mapElement._leaflet_id) {
                                            // If map already exists, update its view and layers if new data is available
                                            const map = mapElement._map;
                                            const centerData = mapElement.dataset.center;
                                            const coordsData = mapElement.dataset.coords;

                                            let center = [[9.005, 38.763]]; // Default center
                                            let polygonCoords = [];

                                            try {
                                                if (centerData) {
                                                    center = JSON.parse(centerData);
                                                }
                                                if (coordsData) {
                                                    polygonCoords = JSON.parse(coordsData);
                                                }
                                            } catch (e) {
                                                console.error("Error parsing map data:", e);
                                                // Use default values if parsing fails
                                                center = [[9.005, 38.763]];
                                                polygonCoords = [];
                                            }

                                            // Clear existing layers (polygons, markers)
                                            map.eachLayer(function(layer) {
                                                if (layer instanceof L.Polygon || layer instanceof L.Marker) {
                                                    map.removeLayer(layer);
                                                }
                                            });

                                            if (polygonCoords.length > 0) {
                                                L.polygon(polygonCoords, { color: 'blue', weight: 2.5, opacity: 1, fillOpacity: 0.3 }).addTo(map);

                                                polygonCoords.forEach((coord, index) => {
                                                     L.marker(coord).addTo(map)
                                                        .bindPopup(`Point ${index + 1}<br>Lat: ${coord[0].toFixed(6)}<br>Lon: ${coord[1].toFixed(6)}`);
                                                });

                                                const bounds = L.latLngBounds(polygonCoords);
                                                map.fitBounds(bounds);
                                            } else {
                                                // If no polygon, set view to center
                                                map.setView(center[0], 18);
                                            }
                                        }
                                    }

                                    // Handle button clicks
                                    document.getElementById('openGoogleMap').addEventListener('click', function () {
                                        // Get the current googleMapHref from the PHP variable rendered in the HTML
                                        const googleMapHref = '<?= $googleMapHref ?>';
                                        window.open(googleMapHref, '_blank');
                                    });

                                    document.getElementById('openAddisland').addEventListener('click', function () {
                                         // Get the current addislandHref from the PHP variable rendered in the HTML
                                        const addislandHref = '<?= $addislandHref ?>';
                                        window.open(addislandHref, '_blank');
                                    });
                                });

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
                                     // Trigger change event on input type select if its value was changed programmatically
                                    const event = new Event('change');
                                    inputTypeSelect.dispatchEvent(event);
                                }


                            </script>
                        </div>

                        <div id="validity-container" class="mb-4">
                            <?= $validityMessage ?>
                        </div>
                    </div>

                    <div id="coordinates-table-container">
                        <?= $coordinatesTable ?>
                    </div>

                    <?php if ($mapData): ?>
                        <div class="card shadow-sm mb-4">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0"><i class="bi bi-geo-alt-fill me-2"></i>Map View</h5>
                            </div>
                            <div class="card-body p-0">
                                <div id="map" style="height: 500px; width: 100%;"
                                    data-center='<?= htmlspecialchars(json_encode([$mapData['center']])) ?>'
                                    data-coords='<?= htmlspecialchars(json_encode(array_map(null, $mapData['lats'], $mapData['lons']))) ?>'>
                                </div>
                            </div>
                            <div class="card-footer text-muted">
                                <small>Click on markers to see point numbers and coordinates</small>
                            </div>
                        </div>
                    <?php endif; ?>

                </div>
            </div>

            <script>
                // Redundant button click handlers removed.
            </script>

    </main>

    <footer class="mt-5 text-center text-muted">
        <p>EthioLand Title Deed and Coordinate Checker © Norm <?= date('Y') ?> </p>
    </footer>

    <div class="modal fade" id="processingModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Processing Request</h5>
                </div>
                <div class="modal-body text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-3">Please wait while we process your request...</p>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="resultModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="resultModalTitle">Result</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="resultModalBody">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

</body>

</html>
