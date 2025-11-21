<?php
/**
 * HelpSpot Live Lookup Integration with DummyJSON API
 * This script queries dummyjson.com/users/search and returns XML for HelpSpot
 */

header('Content-Type: text/xml; charset=utf-8');

/**
 * Build the XML response for HelpSpot Live Lookup.
 *
 * @param array $customers List of user arrays from DummyJSON (expects keys: id, firstName, lastName, email, phone)
 * @return string XML string
 */
function createXMLResponse($customers) {
    $xml = new DOMDocument('1.0', 'utf-8');
    $xml->formatOutput = true;

    $livelookup = $xml->createElement('livelookup');
    $livelookup->setAttribute('version', '1.0');
    $livelookup->setAttribute('columns', 'first_name,last_name,email');
    $xml->appendChild($livelookup);

    foreach ($customers as $user) {
        $customer = $xml->createElement('customer');
        $customerId = $xml->createElement('customer_id', htmlspecialchars($user['id']));
        $customer->appendChild($customerId);
        if (!empty($user['firstName'])) {
            $firstName = $xml->createElement('first_name', htmlspecialchars($user['firstName']));
            $customer->appendChild($firstName);
        }
        if (!empty($user['lastName'])) {
            $lastName = $xml->createElement('last_name', htmlspecialchars($user['lastName']));
            $customer->appendChild($lastName);
        }
        if (!empty($user['email'])) {
            $email = $xml->createElement('email', htmlspecialchars($user['email']));
            $customer->appendChild($email);
        }
        if (!empty($user['phone'])) {
            $phone = $xml->createElement('phone', htmlspecialchars($user['phone']));
            $customer->appendChild($phone);
        }

        $livelookup->appendChild($customer);
    }

    return $xml->saveXML();
}

/**
 * Query the DummyJSON users search endpoint.
 *
 * @param string $searchTerm The term to search for (name, email, or id)
 * @return array Array of users (may be empty on no results or failure)
 */
function queryDummyJSON($searchTerm) {
    if (empty($searchTerm)) {
        return [];
    }

    $url = 'https://dummyjson.com/users/search?q=' . urlencode($searchTerm);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
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

try {
    $searchTerm = '';

    if (!empty($_GET['first_name']) || !empty($_GET['last_name'])) {
        $first = isset($_GET['first_name']) ? $_GET['first_name'] : '';
        $last = isset($_GET['last_name']) ? $_GET['last_name'] : '';
        $searchTerm = trim($first . ' ' . $last);
    } elseif (!empty($_GET['email'])) {
        $searchTerm = $_GET['email'];
    } elseif (!empty($_GET['customer_id'])) {
        $searchTerm = $_GET['customer_id'];
    }

    $users = queryDummyJSON($searchTerm);
    echo createXMLResponse($users);

} catch (Exception $e) {
    $xml = new DOMDocument('1.0', 'utf-8');
    $livelookup = $xml->createElement('livelookup');
    $livelookup->setAttribute('version', '1.0');
    $livelookup->setAttribute('columns', 'first_name,last_name,email');
    $xml->appendChild($livelookup);
    echo $xml->saveXML();
}