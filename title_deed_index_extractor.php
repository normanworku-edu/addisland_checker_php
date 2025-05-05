<?php
function extractTitleDeedData($html) {
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
?>