<?php

/**
 * HelpSpot Live Lookup Integration with DummyJSON API
 *
 * What this script does:
 * - Accepts incoming query params from HelpSpot (first_name, last_name, email, customer_id).
 * - Looks up matching users in the DummyJSON API.
 * - Returns an XML document in HelpSpot's expected <livelookup> format.
 *
 */

// Output must be XML for HelpSpot to parse correctly.
header('Content-Type: text/xml; charset=utf-8');

/**
 * Build the XML response for HelpSpot Live Lookup.
 *
 * @param array $customers List of user arrays from DummyJSON (expects keys: id, firstName, lastName, email, phone)
 * @return string XML string
 */
function createXMLResponse($customers)
{
    $xml = new \DOMDocument('1.0', 'utf-8');
    $xml->formatOutput = true;

    // Root element required by HelpSpot with version and column definitions.
    $livelookup = $xml->createElement('livelookup');
    $livelookup->setAttribute('version', '1.0');
    $livelookup->setAttribute('columns', 'first_name,last_name,email');
    $xml->appendChild($livelookup);

    // Add a <customer> node for every match returned by the data source.
    foreach ($customers as $user) {
        $customer = $xml->createElement('customer');

        $customerId = $xml->createElement('customer_id', htmlspecialchars((string)(isset($user['id']) ? $user['id'] : '')));
        $customer->appendChild($customerId);

        if (!empty($user['firstName'])) {
            $firstName = $xml->createElement('first_name', htmlspecialchars((string)$user['firstName']));
            $customer->appendChild($firstName);
        }

        if (!empty($user['lastName'])) {
            $lastName = $xml->createElement('last_name', htmlspecialchars((string)$user['lastName']))
            ;
            $customer->appendChild($lastName);
        }

        if (!empty($user['email'])) {
            $email = $xml->createElement('email', htmlspecialchars((string)$user['email']));
            $customer->appendChild($email);
        }

        if (!empty($user['phone'])) {
            $phone = $xml->createElement('phone', htmlspecialchars((string)$user['phone']));
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
 * @return array Array of users
 */
function queryDummyJSON($searchTerm)
{
    if ($searchTerm === '') {
        return [];
    }

    $url = 'https://dummyjson.com/users/search?q=' . urlencode($searchTerm);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10); //
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'HelpSpot-LiveLookup-Bridge/1.0 (+https://helpspot.com)');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
    ]);

    $response = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || $response === false || $response === '') {
        return [];
    }

    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
        return [];
    }

    return isset($data['users']) && is_array($data['users']) ? $data['users'] : [];
}

try {
    $first = (string)(($tmp = filter_input(INPUT_GET, 'first_name')) !== null ? $tmp : '');
    $last = (string)(($tmp = filter_input(INPUT_GET, 'last_name')) !== null ? $tmp : '');
    $email = (string)(($tmp = filter_input(INPUT_GET, 'email')) !== null ? $tmp : '');
    $customerId = (string)(($tmp = filter_input(INPUT_GET, 'customer_id')) !== null ? $tmp : '');

    $searchTerm = '';

    if ($first !== '' || $last !== '') {
        // If name fields are provided, prefer them as a combined search query.
        $searchTerm = trim($first . ' ' . $last);
    } elseif ($email !== '') {
        $searchTerm = $email;
    } elseif ($customerId !== '') {
        $searchTerm = $customerId;
    }

    // Perform lookup and emit XML response for HelpSpot.
    $users = queryDummyJSON($searchTerm);
    echo createXMLResponse($users);
} catch (Exception $e) {
    // Catch
    $xml = new \DOMDocument('1.0', 'utf-8');
    $livelookup = $xml->createElement('livelookup');
    $livelookup->setAttribute('version', '1.0');
    $livelookup->setAttribute('columns', 'first_name,last_name,email');
    $xml->appendChild($livelookup);
    echo $xml->saveXML();
}