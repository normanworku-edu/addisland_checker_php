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

    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>

    <!-- Bootstrap JS (optional) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>



</head>

<body>

    <!-- Main content -->
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

            if (!empty($titleDeedNumber)) {
                $result = extractCoordinates($titleDeedNumber);

                if (isset($result['error'])) {
                    $validityMessage = '<div class="alert alert-danger">' . $result['error'] . '</div>';
                } else {
                    $eastingNorthingMatrix = $result['coordinates'];
                    $addislandHref = $result['addisland_url'];

                    list($lats, $lons) = convertToLatLon($eastingNorthingMatrix);
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
                        <h5 class='mb-0'><i class='bi bi-table me-2'></i>Google Map Coordinates for {$titleDeedNumber}</h5>
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
                }
            }
        }
        ?>

        <div class="container mt-4">
            <div class="row justify-content-center">
                <!-- Using a consistent column width for all cards -->
                <div class="col-12 col-lg-10 col-xl-8">


                    <!-- Header content -->
                    <header class="text-center m-4">
                        <h1>Welcome to EthioLand Checker</h1>
                        <p class="lead">Check validity and get google map location of land coordinates in Ethiopia using
                            title deed
                            numbers or Just XY coordinates.</p>
                    </header>

                    <!-- Main Card -->
                    <div class="card shadow-sm mb-4 w-100">
                        <div class="card-header bg-primary text-white">
                            <h4 class="mb-0"><i class="bi bi-card-checklist me-2"></i>Title Deed/Coordinate Validator
                            </h4>
                        </div>





                        <div class="card-body">
                            <form method="POST">
                                <!-- Region Selection (unchanged) -->
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

                                <!-- Input Type Selection -->
                                <div class="mb-3" id="input_type_container">
                                    <label for="input_type" class="form-label fw-bold">Input Type</label>
                                    <select class="form-select" id="input_type" name="input_type"
                                        onchange="updateInputField()">
                                        <option value="title_deed" selected>Title Deed Number</option>
                                        <option value="coordinates">XY Coordinates</option>
                                    </select>
                                </div>

                                <!-- Input Field (unchanged) -->
                                <div class="mb-3">
                                    <label for="title_deed" class="form-label fw-bold" id="input_label">Enter Title Deed
                                        Number</label>
                                    <input type="text" class="form-control" id="title_deed" name="title_deed"
                                        style="min-height: 60px;"
                                        placeholder="Enter Title Deed Number like AK1181145161502962" required>
                                </div>
                                <div class="text mb-3" id="format_text">Input Format: <br>Title deed number like
                                    AK1181145161502962</div>

                                <!-- Buttons (unchanged) -->
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
                                // Store reference to title deed option
                                const titleDeedOption = document.createElement('option');
                                titleDeedOption.value = 'title_deed';
                                titleDeedOption.textContent = 'Title Deed Number';

                                // Initialize on page load
                                document.addEventListener('DOMContentLoaded', function () {
                                    updateInputField();
                                });

                                function updateInputField() {
                                    const region = document.getElementById('region').value;
                                    const inputTypeContainer = document.getElementById('input_type_container');
                                    const inputTypeSelect = document.getElementById('input_type');
                                    const inputLabel = document.getElementById('input_label');
                                    const inputField = document.getElementById('title_deed');
                                    const formatText = document.getElementById('format_text');
                                    const addislandBtn = document.getElementById('openAddisland');

                                    if (region === 'Addis Ababa') {
                                        // Ensure title deed option exists for Addis Ababa
                                        if (!inputTypeSelect.querySelector('option[value="title_deed"]')) {
                                            inputTypeSelect.insertBefore(titleDeedOption, inputTypeSelect.firstChild);
                                        }

                                        // Set default to title deed if not set
                                        if (inputTypeSelect.value !== 'title_deed' && inputTypeSelect.value !== 'coordinates') {
                                            inputTypeSelect.value = 'title_deed';
                                        }

                                        // Update based on selected input type
                                        if (inputTypeSelect.value === 'title_deed') {
                                            inputLabel.textContent = 'Enter Title Deed Number';
                                            inputField.placeholder = 'Enter Title Deed Number like AK1181145161502962';
                                            formatText.innerHTML = 'Input Format: <br>Title deed number like AK1181145161502962';
                                            addislandBtn.style.display = 'block';
                                        } else {
                                            inputLabel.textContent = 'Enter XY Coordinates';
                                            inputField.placeholder = 'Enter XY Coordinates like [477516.781, 980918.698; 477506.273, 980926.353]';
                                            formatText.innerHTML = 'Input Format: <br>XY Coordinates like [477516.781, 980918.698; 477506.273, 980926.353]';
                                            addislandBtn.style.display = 'none';
                                        }
                                    } else {
                                        // For other regions, remove title deed option if it exists
                                        const existingTitleDeedOption = inputTypeSelect.querySelector('option[value="title_deed"]');
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
                                }




                                // Store the modal instances globally
                                let processingModal, resultModal;

                                document.addEventListener('DOMContentLoaded', function () {
                                    // Initialize modals
                                    processingModal = new bootstrap.Modal(document.getElementById('processingModal'));
                                    resultModal = new bootstrap.Modal(document.getElementById('resultModal'));

                                    // Initialize form fields
                                    updateInputField();

                                    // Handle form submission
                                    document.querySelector('form').addEventListener('submit', function (e) {
                                        e.preventDefault();

                                        // Always show processing modal
                                        processingModal.show();

                                        // Submit form data via AJAX
                                        const formData = new FormData(this);

                                        fetch('', {
                                            method: 'POST',
                                            body: formData
                                        })
                                            .then(response => response.text())
                                            .then(html => {
                                                // Parse the entire response
                                                const parser = new DOMParser();
                                                const doc = parser.parseFromString(html, 'text/html');

                                                // Extract all important sections
                                                const newValidityContainer = doc.getElementById('validity-container');
                                                const newCoordinatesTable = doc.querySelector('.card.shadow-sm.mb-4:not(:has(#map))');
                                                const newMapCard = doc.querySelector('.card.shadow-sm.mb-4:has(#map)');

                                                // Update current page
                                                if (newValidityContainer) {
                                                    document.getElementById('validity-container').innerHTML = newValidityContainer.innerHTML;
                                                }

                                                // Handle coordinates table
                                                const existingTable = document.querySelector('.card.shadow-sm.mb-4:not(:has(#map))');
                                                if (newCoordinatesTable) {
                                                    if (existingTable) {
                                                        existingTable.replaceWith(newCoordinatesTable.cloneNode(true));
                                                    } else {
                                                        document.querySelector('#validity-container').after(newCoordinatesTable.cloneNode(true));
                                                    }
                                                } else if (existingTable) {
                                                    existingTable.remove();
                                                }

                                                // Handle map card
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
                                                    // Reinitialize the map
                                                    initMap();
                                                } else if (existingMapCard) {
                                                    existingMapCard.remove();
                                                }

                                                // Hide processing modal
                                                processingModal.hide();

                                                // Show result modal
                                                const isSuccess = newValidityContainer &&
                                                    (newValidityContainer.querySelector('.alert-success') ||
                                                        newValidityContainer.querySelector('.alert-info'));

                                                document.getElementById('resultModalTitle').textContent = isSuccess ? 'Success' : 'Error';
                                                document.getElementById('resultModalBody').innerHTML =
                                                    isSuccess ? '✅ Successfully located the property' : '❌ Failed to locate the property';
                                                resultModal.show();
                                            })
                                            .catch(error => {
                                                processingModal.hide();
                                                document.getElementById('resultModalTitle').textContent = 'Error';
                                                document.getElementById('resultModalBody').innerHTML =
                                                    '<div class="alert alert-danger">Failed to process request. Please try again.</div>';
                                                resultModal.show();
                                            });
                                    });

                                    // Initialize map if it exists on page load
                                    initMap();
                                });

                                function initMap() {
                                    const mapElement = document.getElementById('map');
                                    if (mapElement && !mapElement._map) {
                                        const center = JSON.parse(mapElement.dataset.center || '[[9.005, 38.763]]');
                                        const map = L.map('map').setView(center[0], 18);

                                        L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
                                            attribution: 'Tiles © Esri &mdash; Source: Esri, Maxar, Earthstar Geographics'
                                        }).addTo(map);

                                        const polygonCoords = JSON.parse(mapElement.dataset.coords || '[]');
                                        if (polygonCoords.length > 0) {
                                            L.polygon(polygonCoords, { color: 'blue', weight: 2.5, opacity: 1, fillOpacity: 0.3 }).addTo(map);

                                            polygonCoords.forEach((coord, index) => {
                                                if (index < polygonCoords.length - 1) {
                                                    L.marker(coord).addTo(map)
                                                        .bindPopup(`Point ${index + 1}<br>Lat: ${coord[0].toFixed(6)}<br>Lon: ${coord[1].toFixed(6)}`);
                                                }
                                            });
                                        }
                                        mapElement._map = map;
                                    }
                                }


                            </script>
                        </div>

                        <!-- Results Section -->
                        <div id="validity-container" class="mb-4">
                            <?= $validityMessage ?>
                        </div>
                    </div>
                    <?php if (!empty($coordinatesTable)): ?>
                        <?= $coordinatesTable ?>
                    <?php endif; ?>

                    <?php if ($mapData): ?>
                        <!-- Map Card -->
                        <div class="card shadow-sm mb-4">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0"><i class="bi bi-geo-alt-fill me-2"></i>Map View</h5>
                            </div>
                            <div class="card-body p-0">
                                <div id="map" style="height: 500px; width: 100%;"
                                    data-center='<?= json_encode([$mapData['center']]) ?>'
                                    data-coords='<?= json_encode(array_map(null, $mapData['lats'], $mapData['lons'])) ?>'>
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
                // Handle button clicks
                document.getElementById('openGoogleMap').addEventListener('click', function () {
                    window.open('<?= $googleMapHref ?>', '_blank');
                });

                document.getElementById('openAddisland').addEventListener('click', function () {
                    window.open('<?= $addislandHref ?>', '_blank');
                });
            </script>


    </main>

    <!-- Footer content -->
    <footer class="mt-5 text-center text-muted">
        <p>EthioLand Title Deed and Coordinate Checker &copy; Norm <?= date('Y') ?> </p>
    </footer>



    <!-- Processing Modal -->
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

    <!-- Result Modal -->
    <div class="modal fade" id="resultModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="resultModalTitle">Result</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="resultModalBody">
                    <!-- Content will be inserted here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

</body>

</html>