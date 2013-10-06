<?php
# c
require_once "excel.php";
$houses_array = array();
$start_page = curl_init('http://grapevine.ca/search-results/classic?field_listing_rent_price_value[min]=0&field_listing_rent_price_value[max]=0&field_listing_sale_price_value[min]=0&field_listing_sale_price_value[max]=99999999&&&field_listing_bedrooms_value=&field_listing_bathrooms_value=&keys=&field_listing_go_live_date_value[value]=&created=&field_listing_open_house_value=&field_listing_address_thoroughfare=&sort_by=priority&sort_order=DESC&search_listings_type=Home&view-type=classic&op=Search');

curl_setopt($start_page, CURLOPT_RETURNTRANSFER, 1);
$search_page = curl_exec($start_page);

if ($search_page === false) {
    exit(curl_error($start_page));
}

$dom = new DOMDocument();
$dom->loadHTML($search_page);

$dom->validateOnParse = true;
$dom->preserveWhiteSpace = false;

$finder = new DOMXpath($dom);
$xpath_query = '//*[@id="block-system-main"]/div/div/div/div[1]/*/div[@class="views-field views-field-field-listing-gv-id"]';

$last_page = $finder->query('//*[@id="block-system-main"]/div/div/div/div[2]/ul/li[6]');

$last_page_number = preg_replace("/[^0-9]/", "", $last_page->item(0)->textContent);
$el = $finder->query($xpath_query);

foreach ($el as $e) {
    # Find string on the page which have the # sign
    echo $e->nodeValue . "\n";
    $pos_ns = strpos($e->nodeValue, '#');
    if ($pos_ns === false) {
        exit('Can not find number sign.');
    }
    $id = substr($e->nodeValue, $pos_ns + 1);
    $id = trim($id);
    process_home_listing($id);
}

$current_page_number = 0;
do {
    $current_page_number++;
    $page = load_next_page($current_page_number);
    $dom = new DOMDocument();
    $dom->loadHTML($page);
    $dom->validateOnParse = true;
    $dom->preserveWhiteSpace = false;
    $finder = new DOMXpath($dom);
    $el = $finder->query($xpath_query);

    foreach ($el as $e) {
        # Find string on the page which have the # sign
        echo $e->nodeValue . "\n";
        $pos_ns = strpos($e->nodeValue, '#');
        if ($pos_ns === false) {
            exit('Can not find number sign.');
        }
        $id = substr($e->nodeValue, $pos_ns + 1);
        $id = trim($id);
        process_home_listing($id);
        echo("Page number: " . $current_page_number . "\n");
    }
} while ($current_page_number !== $last_page_number);
$filename = "theFile.xls";
$export_file = "xlsfile://srv/www/htdocs/grapevine/" . $filename;
$fp = fopen($export_file, "wb");
if (!is_resource($fp)) {
    die("Cannot open $export_file");
}
fwrite($fp, serialize($houses_array));
fclose($fp);

function process_home_listing($id) {
    global $houses_array;
    $url = 'http://grapevine.ca/listing/' . $id;
    $page = load_page($url);

    $dom = new DOMDocument();
    $dom->loadHTML($page);
    $dom->validateOnParse = true;
    $dom->preserveWhiteSpace = false;
    $finder = new DOMXpath($dom);


    $all_divs = $dom->getElementsByTagName('div');
    /* Get price */
    # Select 1st parent class for price
    $cla = $finder->query('//*[@class="field field-name-field-listing-sale-price field-type-number-decimal field-label-hidden"]');
    # Second parent class for price
    $ccla = $cla->item(0);
    # Get text content of the node with price
    $price = $ccla->firstChild->textContent;
    /* Get price */

    /* Get creation date */
    $creation_date = $finder->query('//*[starts-with(@id,"slide-0-field_listing_photos")]/a/img');
    $creation_date = $creation_date->item(0)->getAttribute('src');
    $curl = curl_init($creation_date);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_FILETIME, true);
    $result = curl_exec($curl);
    if ($result === false) {
        die(curl_error($curl));
    }
    $timestamp = curl_getinfo($curl, CURLINFO_FILETIME);
    if ($timestamp != -1) {
        $creation_date = date("Y-m-d", $timestamp);
    } else {
        $creation_date = 'unknown';
    }

    /* Get creation date */

    /* Get phone number */
    $p = $finder->query('//*[@class="field field-name-field-listing-phone field-type-text field-label-hidden"]');
    $pp = $p->item(0);
    $phone = $pp->firstChild->textContent;
    /* Get phone number */

    /* Get the address */
    $thoroughfare = $finder->query('//*[@class="thoroughfare"]');
    $thoroughfare = $thoroughfare->item(0)->firstChild->textContent;

    /* Locality */
    $locality = $finder->query('//*[@class="locality"]');
    $locality = $locality->item(0)->firstChild->textContent;
    /* Locality */

    /* state */
    $state = $finder->query('//*[@class="state"]');
    $state = $state->item(0)->firstChild->textContent;
    /* state */

    /* postal-code */
    $postal_code = $finder->query('//*[@class="postal-code"]');
    $postal_code = $postal_code->item(0)->firstChild->textContent;
    /* postal-code */

    /* Get the address. End */

    /* Select commission */
    $div_commission = $finder->query('//*[starts-with(@id,"node-listing")]/div[1]/div[1]/div[6]/div/div');
    if ($div_commission === false || $div_commission->length == 0) {
        $commission = 'no_commission';
    } else {
        $div_commission = $div_commission->item(0)->textContent;
        $commission = find_commission($div_commission);
    }
    /* Select commission */

    $houses_array [] =
            array("Price" => $price,
                "Phone" => $phone,
                "thoroughfare" => $thoroughfare,
                "Locality" => $locality,
                "State" => $state,
                "Postal code" => $postal_code,
                "Commission" => $commission,
                "Creation date" => $creation_date
    );
}

function find_commission($div_commission) {
    $pattern = '/agents.*welcome*.at.*.*[%|percent]\./i';
    if (preg_match($pattern, $div_commission, $match)) {
        return $match[0];
    } else {
        return 'no_commission';
    }
}

function load_page($url) {
    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    $page = curl_exec($curl);
    if ($page === false) {
        exit(curl_error($page));
    } else {
        return $page;
    }
}

function load_next_page($next_page) {
    $start = 0;
    $current_page = curl_init('http://grapevine.ca/search-results/classic?field_listing_rent_price_value[min]=0&field_listing_rent_price_value[max]=0&field_listing_sale_price_value[min]=0&field_listing_sale_price_value[max]=99999999&&&field_listing_bedrooms_value=&field_listing_bathrooms_value=&keys=&field_listing_go_live_date_value[value]=&created=&field_listing_open_house_value=&field_listing_address_thoroughfare=&sort_by=priority&sort_order=DESC&page=' . $next_page . '&search_listings_type=Home&view-type=classic&op=Search');
    curl_setopt($current_page, CURLOPT_RETURNTRANSFER, 1);
    $current_page = curl_exec($current_page);
    if ($current_page === false) {
        exit(curl_error($current_page));
    }
    return $current_page;
}