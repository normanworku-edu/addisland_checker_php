<?php
// At the VERY top of your file - no whitespace before this
//header('Content-Type: application/json');

// Start output buffering to prevent any accidental output
ob_start();

require_once 'utm.php';

// Basic configuration
define('BASE_URL', 'http://' . $_SERVER['HTTP_HOST'] . str_replace(basename($_SERVER['SCRIPT_NAME']), '', $_SERVER['SCRIPT_NAME']));
define('ADDLISLAND_URL_PREFIX', 'https://www.addisland.gov.et/en-us/certificate/');


$titleDeedNumber = '';
$validityMessage = '<div class="alert alert-info">Enter valid title deed number like AK1181145161502962 or Valid XY Coordinates like [477516.781, 980918.698; 477506.273, 980926.353] and Click Check</div>';
$googleMapHref = 'https://maps.app.goo.gl/jr4S1yaX8NeVXFsa8';
$addislandHref = 'https://www.addisland.gov.et/';
$coordinatesTable = '';
$mapData = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
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
                $isSuccess = false;
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
                    $isSuccess = true;
                    // Prepare data for the map
                    $mapData = [
                        'lats' => $lats,
                        'lons' => $lons,
                        'center' => [$centerLat, $centerLon]
                    ];
                } else {
                    $isSuccess = false;
                    $validityMessage = '<div class="alert alert-warning">No coordinates found for the provided input.</div>';
                    $coordinatesTable = '';
                    $mapData = null;
                }
            }
        }

        // Clear any output buffers
        while (ob_get_level()) {
            ob_end_clean();
        }
        // Prepare JSON response
        header('Content-Type: application/json');

        die(json_encode([
            'isSuccess' => $isSuccess ?? false,
            'lats' => $lats ?? null,
            'lons' => $lons ?? null,
            'centerLat' => $centerLat ?? null,
            'centerLon' => $centerLon ?? null,
            'addislandHref' => $addislandHref ?? '',
            'googleMapHref' => $googleMapHref ?? ''
        ]));// Terminate script after JSON output

    } catch (Exception $e) {
        // Clear buffers on error
        while (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
        exit;
    }
}
function extractCoordinates($titleDeedNumber)
{
    // Trim whitespace from input
    $input = trim($titleDeedNumber);

    // Case 1: Check if input is a valid title deed number (letters followed by numbers)
    if (preg_match('/^[A-Za-z]+\d+$/', $input)) {
        $url = ADDLISLAND_URL_PREFIX . $input;

        try {
            $html = file_get_contents($url);
            if ($html === false) {
                return ['error' => 'Failed to fetch data from Addisland'];
            }



            /* Try to extract all information from the website
            but as the table is not well organized, it was not possible to etract the data.
            //$propertyData = extractPropertyData_Deepseek($html);             // Extract data
            $propertyData = extractFullPropertyDetails_ChatGPT($html);
            savePropertyDataToTextFile($propertyData);              // Save to text file
            
            
            $propertyData = extractTitleDeedData_FIXED_INDEX($html);
            savePropertyDataToTextFile($propertyData);              // Save to text file
            */




            $doc = new DOMDocument();
            @$doc->loadHTML($html);
            $xpath = new DOMXPath($doc);

            // Find tables containing coordinates
            $tables = $xpath->query("//table");
            $selectedTable = null;

            foreach ($tables as $table) {
                $spans = $xpath->query(".//span", $table);
                foreach ($spans as $span) {
                    if (strpos($span->nodeValue, "Cordnates/") !== false) {
                        $selectedTable = $table;
                        break 2;
                    }
                }
            }

            if (!$selectedTable) {
                return ['error' => 'Invalid Input: Coordinate table not found.'];
            }

            $rows = $xpath->query(".//tr", $selectedTable);
            $coordinates = [];

            foreach ($rows as $row) {
                $cols = $xpath->query(".//td", $row);
                if ($cols->length == 2) {
                    $x = trim($cols->item(0)->nodeValue);
                    $y = trim($cols->item(1)->nodeValue);

                    if (is_numeric($x) && is_numeric($y)) {
                        $coordinates[] = [(float) $x, (float) $y];
                    }
                }
            }

            if (empty($coordinates)) {
                return ['error' => 'No valid coordinates found'];
            }

            return [
                'coordinates' => $coordinates,
                'addisland_url' => $url
            ];

        } catch (Exception $e) {
            return ['error' => 'Error fetching data: ' . $e->getMessage()];
        }
    }
    // Case 2: Check if input is valid XY coordinates format
    elseif (preg_match('/^\[\s*(-?\d+\.?\d*)\s*,\s*(-?\d+\.?\d*)(?:\s*;\s*(-?\d+\.?\d*)\s*,\s*(-?\d+\.?\d*)\s*)*;?\s*\]$/', $input)) {
        $coordinates = [];
        $cleanedInput = trim($input, '[]');
        $pairs = preg_split('/\s*;\s*/', $cleanedInput);

        foreach ($pairs as $pair) {
            $xy = preg_split('/\s*,\s*/', trim($pair));
            if (count($xy) == 2) {
                $x = $xy[0];
                $y = $xy[1];
                if (is_numeric($x) && is_numeric($y)) {
                    $coordinates[] = [(float) $x, (float) $y];
                }
            }
        }

        // add delay for 2 sec
        sleep(2);

        if (!empty($coordinates)) {
            return [
                'coordinates' => $coordinates,
                'addisland_url' => 'https://www.addisland.gov.et/'
            ];
        }
    }

    // If neither format matches
    // add delay for 2 sec
    sleep(2);
    return ['error' => 'Invalid input format. Expected either title deed number (e.g. AK1181145161502962) or coordinates (e.g. [477516.781, 980918.698; 477506.273, 980926.353])'];
}




function generatePrecalibratedMatrix($eastingNorthingMatrix)
{
    // This would be your PHP implementation of the calibration logic
    // For now, we'll just return the input as-is
    return $eastingNorthingMatrix;
}


function convertToLatLon($eastingNorthingMatrix)
{
    $lats = [];
    $lons = [];

    // Generate precalibrated matrix
    $precalibMatrix = UTMConverter::generatePrecalibratedMatrix($eastingNorthingMatrix);

    foreach ($precalibMatrix as $point) {
        // Convert using UTM zone 37 for Addis Ababa (northern hemisphere)
        list($lat, $lon) = UTMConverter::toLatLon($point[0], $point[1], 37, null, true);

        $lats[] = $lat;
        $lons[] = $lon;
    }

    // Close the polygon
    if (!empty($lats)) {
        $lats[] = $lats[0];
        $lons[] = $lons[0];
    }

    return [$lats, $lons];
}


function generateGoogleMapsLink($lat, $lon)
{
    return "https://www.google.com/maps?q={$lat},{$lon}";
}




function savePropertyDataToTextFile($result, $outputFile = 'property_data.txt')
{

    // Convert the result to a readable text format
    $text = "";
    foreach ($result as $key => $value) {
        if (is_array($value)) {
            $text .= "$key:\n";
            foreach ($value as $coord) {
                $text .= "  - [" . implode(", ", $coord) . "]\n";
            }
        } else {
            $text .= "$key: $value\n";
        }
    }

    // Save to file
    file_put_contents($outputFile, $text);
    echo "✅ Data saved to $outputFile\n";
}
?>