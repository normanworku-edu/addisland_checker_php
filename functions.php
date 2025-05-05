<?php
require_once 'config.php';
require_once 'utm.php';

function extractCoordinates($titleDeedNumber) {
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
                return ['error' => 'Coordinate table not found'];
            }

            $rows = $xpath->query(".//tr", $selectedTable);
            $coordinates = [];

            foreach ($rows as $row) {
                $cols = $xpath->query(".//td", $row);
                if ($cols->length == 2) {
                    $x = trim($cols->item(0)->nodeValue);
                    $y = trim($cols->item(1)->nodeValue);
                    
                    if (is_numeric($x) && is_numeric($y)) {
                        $coordinates[] = [(float)$x, (float)$y];
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
                    $coordinates[] = [(float)$x, (float)$y];
                }
            }
        }
        
        if (!empty($coordinates)) {
            return [
                'coordinates' => $coordinates,
                'addisland_url' => 'https://www.addisland.gov.et/'
            ];
        }
    }
    
    // If neither format matches
    return ['error' => 'Invalid input format. Expected either title deed number (e.g. AK1181145161502962) or coordinates (e.g. [477516.781, 980918.698; 477506.273, 980926.353])'];
}




function generatePrecalibratedMatrix($eastingNorthingMatrix) {
    // This would be your PHP implementation of the calibration logic
    // For now, we'll just return the input as-is
    return $eastingNorthingMatrix;
}


function convertToLatLon($eastingNorthingMatrix) {
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


function generateGoogleMapsLink($lat, $lon) {
    return "https://www.google.com/maps?q={$lat},{$lon}";
}

























function extractPropertyData_Deepseek($html) {
    try {
        $doc = new DOMDocument();
        @$doc->loadHTML($html);
        $xpath = new DOMXPath($doc);

        $result = [];

        // Extract property number
        $propertyNoNodes = $xpath->query("//div[contains(., 'Property No')]/following-sibling::div");
        if ($propertyNoNodes->length > 0) {
            $result['property_number'] = trim($propertyNoNodes->item(0)->nodeValue);
        }

        // Extract owner name
        $ownerNameNodes = $xpath->query("//div[contains(., 'Possessor') and contains(., 'full name')]/following-sibling::div");
        if ($ownerNameNodes->length > 0) {
            $result['owner_name'] = trim($ownerNameNodes->item(0)->nodeValue);
        }

        // Extract title deed number
        $titleDeedNodes = $xpath->query("//div[contains(., 'Title deed No.')]/following-sibling::div");
        if ($titleDeedNodes->length > 0) {
            $result['title_deed_number'] = trim($titleDeedNodes->item(0)->nodeValue);
        }

        // Extract date issued
        $dateIssuedNodes = $xpath->query("//div[contains(., 'Date issued')]/following-sibling::div");
        if ($dateIssuedNodes->length > 0) {
            $result['date_issued'] = trim($dateIssuedNodes->item(0)->nodeValue);
        }

        // Extract transfer type
        $transferTypeNodes = $xpath->query("//div[contains(., 'Transfer Type')]/following-sibling::div");
        if ($transferTypeNodes->length > 0) {
            $result['transfer_type'] = trim($transferTypeNodes->item(0)->nodeValue);
        }

        // Extract possession type
        $possessionTypeNodes = $xpath->query("//div[contains(., 'Possession Type')]/following-sibling::div");
        if ($possessionTypeNodes->length > 0) {
            $result['possession_type'] = trim($possessionTypeNodes->item(0)->nodeValue);
        }

        // Extract sub city
        $subCityNodes = $xpath->query("//div[contains(., 'Sub city')]/following-sibling::div");
        if ($subCityNodes->length > 0) {
            $result['sub_city'] = trim($subCityNodes->item(0)->nodeValue);
        }

        // Extract folder number
        $folderNoNodes = $xpath->query("//div[contains(., 'Folder No.')]/following-sibling::div");
        if ($folderNoNodes->length > 0) {
            $result['folder_number'] = trim($folderNoNodes->item(0)->nodeValue);
        }

        // Extract coordinates
        $tables = $xpath->query("//table");
        $selectedTable = null;

        foreach ($tables as $table) {
            $spans = $xpath->query(".//span", $table);
            foreach ($spans as $span) {
                if (strpos($span->nodeValue, "Cordnates/") !== false || 
                    strpos($span->nodeValue, "የቦታ የቦታው መገኛ ነጥቦች") !== false) {
                    $selectedTable = $table;
                    break 2;
                }
            }
        }

        if ($selectedTable) {
            $rows = $xpath->query(".//tr", $selectedTable);
            $coordinates = [];

            foreach ($rows as $row) {
                $cols = $xpath->query(".//td", $row);
                if ($cols->length == 2) {
                    $x = trim($cols->item(0)->nodeValue);
                    $y = trim($cols->item(1)->nodeValue);

                    if (is_numeric($x) && is_numeric($y)) {
                        $coordinates[] = [(float)$x, (float)$y];
                    }
                }
            }

            if (!empty($coordinates)) {
                $result['coordinates'] = $coordinates;
            }
        }

        // Extract additional property details from the table at the bottom
        $propertyTables = $xpath->query("//table[contains(., 'Woreda')]");
        if ($propertyTables->length > 0) {
            $propertyTable = $propertyTables->item(0);
            $rows = $xpath->query(".//tr", $propertyTable);
            
            // Assuming the first row is headers and second row is data
            if ($rows->length >= 2) {
                $dataRow = $rows->item(1);
                $cols = $xpath->query(".//td", $dataRow);
                
                if ($cols->length >= 9) {
                    $result['woreda'] = trim($cols->item(0)->nodeValue);
                    $result['block_number'] = trim($cols->item(1)->nodeValue);
                    $result['parcel_number'] = trim($cols->item(2)->nodeValue);
                    $result['house_number'] = trim($cols->item(3)->nodeValue);
                    $result['area_m2'] = trim($cols->item(4)->nodeValue);
                    $result['built_up_area'] = trim($cols->item(5)->nodeValue);
                    $result['floor_number'] = trim($cols->item(6)->nodeValue);
                    $result['land_use'] = trim($cols->item(7)->nodeValue);
                    $result['house_use'] = trim($cols->item(8)->nodeValue);
                }
            }
        }

        return $result;

    } catch (Exception $e) {
        return ['error' => 'Error extracting data: ' . $e->getMessage()];
    }
}



function extractFullPropertyDetails_ChatGPT($html) {
    $doc = new DOMDocument();
    @$doc->loadHTML($html);
    $xpath = new DOMXPath($doc);

    $result = [];

    // Utility function for extracting text
    $getValue = function ($query) use ($xpath) {
        $nodes = $xpath->query($query);
        return ($nodes->length > 0) ? trim($nodes->item(0)->textContent) : null;
    };

    $result['property_number'] = $getValue("//div[contains(., 'Property No')]/following-sibling::div");
    
    $result['owner_name_english'] = $getValue("//div[contains(., 'Possessor (full name)')]/following-sibling::div");
    $result['owner_name_amharic'] = $getValue("//div[contains(., 'የባለቤት ሙሉ ስም')]/following-sibling::div");

    $result['title_deed_number'] = $getValue("//div[contains(., 'Title deed No.')]/following-sibling::div");

    $result['date_issued'] = $getValue("//div[contains(., 'Date issued')]/following-sibling::div");

    $result['transfer_type_english'] = $getValue("//div[contains(., 'Transfer Type')]/following-sibling::div");
    $result['transfer_type_amharic'] = $getValue("//div[contains(., 'የማስተላለፊያ አይነት')]/following-sibling::div");

    $result['possession_type_english'] = $getValue("//div[contains(., 'Possession Type')]/following-sibling::div");
    $result['possession_type_amharic'] = $getValue("//div[contains(., 'የባለቤትነት አይነት')]/following-sibling::div");

    $result['sub_city_english'] = $getValue("//div[contains(., 'Sub city')]/following-sibling::div");
    $result['sub_city_amharic'] = $getValue("//div[contains(., 'ክፍለ ከተማ')]/following-sibling::div");

    $result['folder_number'] = $getValue("//div[contains(., 'Folder No.')]/following-sibling::div");

    // Coordinates
    $coords = [];
    foreach ($xpath->query("//table//span[contains(text(),'Cordnates') or contains(text(),'መገኛ ነጥቦች')]") as $span) {
        $table = $span->parentNode;
        while ($table && $table->nodeName != "table") {
            $table = $table->parentNode;
        }
        if ($table) {
            foreach ($xpath->query(".//tr", $table) as $tr) {
                $tds = $xpath->query(".//td", $tr);
                if ($tds->length >= 2) {
                    $x = trim($tds->item(0)->textContent);
                    $y = trim($tds->item(1)->textContent);
                    if (is_numeric($x) && is_numeric($y)) {
                        $coords[] = [(float)$x, (float)$y];
                    }
                }
            }
        }
    }
    $result['coordinates'] = $coords;

    // Property detail table (bottom one with woreda etc.)
    $rows = $xpath->query("//table[contains(., 'Woreda')]/tr");
    if ($rows->length >= 2) {
        $tds = $xpath->query(".//td", $rows->item(1));
        if ($tds->length >= 9) {
            $result['woreda'] = trim($tds->item(0)->textContent);
            $result['block_number'] = trim($tds->item(1)->textContent);
            $result['parcel_number'] = trim($tds->item(2)->textContent);
            $result['house_number'] = trim($tds->item(3)->textContent);
            $result['area_m2'] = trim($tds->item(4)->textContent);
            $result['built_up_area'] = trim($tds->item(5)->textContent);
            $result['floor_number'] = trim($tds->item(6)->textContent);
            $result['land_use'] = trim($tds->item(7)->textContent);
            $result['house_use'] = trim($tds->item(8)->textContent);
        }
    }

    return $result;
}



function extractTitleDeedData_FIXED_INDEX($html) {
    $result = [];
    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $divs = $dom->getElementsByTagName('div');
    $tables = $dom->getElementsByTagName('table');

    // Helper function to clean and get text content safely
    $getCleanText = function($index, $nodeList) {
        if (!isset($nodeList[$index])) return '';
        $text = trim(preg_replace('/\s+/', ' ', $nodeList[$index]->nodeValue));
        return $text;
    };

    // Property Number
    $result['property_number'] = $getCleanText(18, $divs);

    // Owner Names
    $result['owner_name_amharic'] = $getCleanText(14, $divs);
    $result['owner_name_english'] = $getCleanText(29, $divs);

    // Title Deed Number
    $result['title_deed_number'] = $getCleanText(41, $divs);

    // Date Issued
    $result['date_issued'] = $getCleanText(45, $divs);

    // Transfer Type (split Amharic/English)
    $transfer = $getCleanText(49, $divs);
    $transferParts = explode('/', $transfer);
    $result['transfer_type_amharic'] = trim($transferParts[0] ?? '');
    $result['transfer_type_english'] = trim($transferParts[1] ?? '');

    // Possession Type (split Amharic/English)
    $possession = $getCleanText(53, $divs);
    $possessionParts = explode('/', $possession);
    $result['possession_type_amharic'] = trim($possessionParts[0] ?? '');
    $result['possession_type_english'] = trim($possessionParts[1] ?? '');

    // Sub City (split Amharic/English)
    $subCity = $getCleanText(87, $divs);
    $subCityParts = explode('/', $subCity);
    $result['sub_city_amharic'] = trim($subCityParts[0] ?? '');
    $result['sub_city_english'] = trim($subCityParts[1] ?? '');

    // Folder Number
    $result['folder_number'] = $getCleanText(91, $divs);

    // Coordinates
    $result['coordinates'] = [];
    $xIndices = [66,67,68,69,70,71];
    $yIndices = [72,73,74,76,77];
    for ($i = 0; $i < min(count($xIndices), count($yIndices)); $i++) {
        $x = $getCleanText($xIndices[$i], $divs);
        $y = $getCleanText($yIndices[$i], $divs);
        if ($x && $y) {
            $result['coordinates'][] = ['x' => $x, 'y' => $y];
        }
    }

    // Property Details Table
    if (isset($tables[0])) {
        $rows = $tables[0]->getElementsByTagName('tr');
        if ($rows->length > 1) {
            $cells = $rows->item(1)->getElementsByTagName('td');

            $result['woreda'] = $getCleanText(0, $cells);
            $result['block_number'] = $getCleanText(1, $cells);
            $result['parcel_number'] = $getCleanText(2, $cells);
            $result['house_number'] = $getCleanText(3, $cells);
            $result['area_m2'] = $getCleanText(4, $cells);
            $result['built_up_area'] = $getCleanText(5, $cells);
            $result['floor_number'] = $getCleanText(6, $cells);
            $result['land_use'] = $getCleanText(7, $cells);
            $result['house_use'] = $getCleanText(8, $cells);
        }
    }

    return $result;
    }




function savePropertyDataToTextFile($result, $outputFile= 'property_data.txt') {

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
