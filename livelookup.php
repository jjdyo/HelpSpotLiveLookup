<?php
/**
 * HelpSpot Live Lookup Integration with DummyJSON API
 * This script queries dummyjson.com/users/search and returns XML for HelpSpot
 */

// Set response headers
header('Content-Type: text/xml; charset=utf-8');

// Function to create XML response
function createXMLResponse($customers) {
    $xml = new DOMDocument('1.0', 'utf-8');
    $xml->formatOutput = true;

    // Create root element with columns attribute
    $livelookup = $xml->createElement('livelookup');
    $livelookup->setAttribute('version', '1.0');
    $livelookup->setAttribute('columns', 'first_name,last_name,email');
    $xml->appendChild($livelookup);

    // Add each customer
    foreach ($customers as $user) {
        $customer = $xml->createElement('customer');

        // Required: customer_id (using user ID from API)
        $customerId = $xml->createElement('customer_id', htmlspecialchars($user['id']));
        $customer->appendChild($customerId);

        // First name
        if (!empty($user['firstName'])) {
            $firstName = $xml->createElement('first_name', htmlspecialchars($user['firstName']));
            $customer->appendChild($firstName);
        }

        // Last name
        if (!empty($user['lastName'])) {
            $lastName = $xml->createElement('last_name', htmlspecialchars($user['lastName']));
            $customer->appendChild($lastName);
        }

        // Email
        if (!empty($user['email'])) {
            $email = $xml->createElement('email', htmlspecialchars($user['email']));
            $customer->appendChild($email);
        }

        // Optional: Add phone if available
        if (!empty($user['phone'])) {
            $phone = $xml->createElement('phone', htmlspecialchars($user['phone']));
            $customer->appendChild($phone);
        }

        $livelookup->appendChild($customer);
    }

    return $xml->saveXML();
}

// Function to query DummyJSON API
function queryDummyJSON($searchTerm) {
    if (empty($searchTerm)) {
        return [];
    }

    $url = 'https://dummyjson.com/users/search?q=' . urlencode($searchTerm);

    // Use cURL for API request
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'HelpSpot-LiveLookup-Bridge/1.0 (+https://helpspot.com)');
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Accept: application/json',
    ));

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$response) {
        return [];
    }

    $data = json_decode($response, true);
    return isset($data['users']) ? $data['users'] : [];
}

// Main logic
try {
    // Get search parameters from HelpSpot
    // HelpSpot sends: customer_id, first_name, last_name, email, phone, etc.
    $searchTerm = '';

    // Build search term from available parameters
    // Priority: first_name + last_name, then email, then customer_id
    if (!empty($_GET['first_name']) || !empty($_GET['last_name'])) {
        $first = isset($_GET['first_name']) ? $_GET['first_name'] : '';
        $last = isset($_GET['last_name']) ? $_GET['last_name'] : '';
        $searchTerm = trim($first . ' ' . $last);
    } elseif (!empty($_GET['email'])) {
        $searchTerm = $_GET['email'];
    } elseif (!empty($_GET['customer_id'])) {
        $searchTerm = $_GET['customer_id'];
    }

    // Query the API
    $users = queryDummyJSON($searchTerm);

    // Generate and output XML
    echo createXMLResponse($users);

} catch (Exception $e) {
    // Return empty result set on error
    $xml = new DOMDocument('1.0', 'utf-8');
    $livelookup = $xml->createElement('livelookup');
    $livelookup->setAttribute('version', '1.0');
    $livelookup->setAttribute('columns', 'first_name,last_name,email');
    $xml->appendChild($livelookup);
    echo $xml->saveXML();
}