<!DOCTYPE html>
<html lang="en">

<head>



    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EthioLand Checker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />

    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>


    <?php
    require_once 'functions.php';
    ?>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">

</head>

<body>

    <main>
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


                        </div>
                    </div>

                    <div id="validity-text-container" class="mb-4">
                            <p> Here validity info will be displayed!</p>
                    </div>

                    <div id="coordinates-table-container" class="mb-4">
                        <p> </p>
                    </div>

                    <div id="map-container">
                        <div class="card shadow-sm mb-4">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0"><i class="bi bi-geo-alt-fill me-2"></i>Map View</h5>
                            </div>
                            <div class="card-body p-0">
                                <div id="map" style="height: 500px; width: 100%;">

                                </div>
                            </div>
                            <div class="card-footer text-muted">
                                <small>Click on markers to see point numbers and coordinates</small>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

    </main>

    <footer class="mt-5 text-center text-muted">
        <p>EthioLand Title Deed and Coordinate Checker © Norm <?= date('Y') ?> </p>
    </footer>

    <div class="modal fade" id="processingModal" tabindex="-1" data-bs-backdrop="static">
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

    <div class="modal fade" id="resultModal" tabindex="-1">
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

<!-- Then load your local script -->
<script src="assets/js/script.js"></script>

</body>

</html>