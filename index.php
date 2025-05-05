<?php
require_once 'config.php';
require_once 'functions.php';
require_once 'includes/header.php';

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
            
            <!-- Main Card -->
            <div class="card shadow-sm mb-4 w-100">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0"><i class="bi bi-card-checklist me-2"></i>Title Deed/Coordinate Validator</h4>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label for="title_deed" class="form-label fw-bold">Enter Title Deed Number or XY Coordinates</label>
                            <input type="text" class="form-control form-control" id="title_deed" name="title_deed" 
                                placeholder="e.g. AK1181145161502962 or [477516.781, 980918.698; 477506.273, 980926.353]" 
                                value="<?= htmlspecialchars($titleDeedNumber) ?>" required>
                        </div>
                        <div class="text mb-3">Input Format: Title deed number like AK1181145161502962 Or <br> XY Coordinates like [477516.781, 980918.698; 477506.273, 980926.353] </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <button type="submit" class="btn btn-primary me-md-2 px-4">
                                <i class="bi bi-check-circle me-2"></i>Check
                            </button>
                            <button type="button" id="openGoogleMap" class="btn btn-outline-primary me-md-2 px-4">
                                <i class="bi bi-map me-2"></i>Google Map
                            </button>
                            <button type="button" id="openAddisland" class="btn btn-outline-secondary px-4">
                                <i class="bi bi-file-earmark-text me-2"></i>Addisland Doc
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Results Section -->
            <div id="validity-container" class="mb-4">
                <?= $validityMessage ?>
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
                        <div id="map" style="height: 500px; width: 100%;"></div>
                    </div>
                    <div class="card-footer text-muted">
                        <small>Click on markers to see point numbers and coordinates</small>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($mapData): ?>
    <script>
        // Initialize the map
        const map = L.map('map').setView(<?= json_encode($mapData['center']) ?>, 18);
        
        // Add tile layer (similar to ESRI World Imagery)
        L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
            attribution: 'Tiles © Esri &mdash; Source: Esri, Maxar, Earthstar Geographics'
        }).addTo(map);
        
        // Add polygon
        const polygonCoords = <?= json_encode(array_map(null, $mapData['lats'], $mapData['lons'])) ?>;
        L.polygon(polygonCoords, {color: 'blue', weight: 2.5, opacity: 1, fillOpacity: 0.3}).addTo(map);
        
        // Add markers
        polygonCoords.forEach((coord, index) => {
            if (index < polygonCoords.length - 1) { // Skip the last point (closing point)
                L.marker(coord).addTo(map)
                    .bindPopup(`Point ${index + 1}<br>Lat: ${coord[0].toFixed(6)}<br>Lon: ${coord[1].toFixed(6)}`);
            }
        });
    </script>
<?php endif; ?>

<script>
    // Handle button clicks
    document.getElementById('openGoogleMap').addEventListener('click', function() {
        window.open('<?= $googleMapHref ?>', '_blank');
    });
    
    document.getElementById('openAddisland').addEventListener('click', function() {
        window.open('<?= $addislandHref ?>', '_blank');
    });
</script>

<?php
require_once 'includes/footer.php';