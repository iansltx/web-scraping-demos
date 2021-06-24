<?php

namespace Covie\Integration\InsuranceProvider\Hippo;

use GuzzleHttp\Psr7\Request;

require __DIR__ . '/vendor/autoload.php';

// base64_encode(json_encode(["name" => "auth0.js", "version" => "9.5.1"]))
define('AUTH0_CLIENT', 'eyJuYW1lIjoiYXV0aDAuanMiLCJ2ZXJzaW9uIjoiOS41LjEifQ==');
define('CLIENT_ID', 'nVCESNtUIXvWDikEDBI0Zdj4xFdKG2BO');
define('DEFAULT_HEADERS', [
    'Auth0-Client' => AUTH0_CLIENT,
    'Content-Type' => 'application/json'
]);

function getRandomString(): string
{
    $alphabet = str_split('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789._') ?: ['a']; // psalm :|
    return implode('', array_map(fn ($index) => $alphabet[$index], array_rand($alphabet, 32)));
}

$http = new \GuzzleHttp\Client(['cookies' => true]);
$emailOrPhoneNumber = readline('Please enter your email or phone number: ');

if (filter_var($emailOrPhoneNumber, FILTER_VALIDATE_EMAIL)) {
    $initParams = ['connection' => $realm = 'email', 'email' => $username = $emailOrPhoneNumber];
} elseif (strlen(preg_replace('/[^\d]/', '', $emailOrPhoneNumber)) === 10) {
    $initParams = [
        'connection' => $realm = 'sms',
        'phone_number' => $username = ('+1' . preg_replace('/[^\d]/', '', $emailOrPhoneNumber))
    ];
} else {
    throw new \InvalidArgumentException('Invalid email or phone number entered');
}

// Will throw a ClientException if we have an invalid request
$http->send(new Request(
    'POST',
    'https://auth0-customer.myhippo.com/passwordless/start',
    DEFAULT_HEADERS,
    json_encode(array_merge($initParams, [
        "client_id" => CLIENT_ID,
        "send" => "code",
        "authParams" => [
            "response_type" => "token id_token",
            "redirect_uri" => "https://myhippo.com/myaccount/login",
            "state" => getRandomString(),
            "nonce" => getRandomString()
        ],
    ]))
));

$code = readline('Enter the code you were sent: ');

// Will throw a ClientException if we have an invalid code
$codeResponse = json_decode($http->send(new Request(
    'POST',
    'https://auth0-customer.myhippo.com/co/authenticate',
    DEFAULT_HEADERS + ['Origin' => 'https://myhippo.com'],
    json_encode([
        "realm" => $realm,
        "username" => $username,
        "client_id" => CLIENT_ID,
        "otp" => $code,
        "credential_type" => "http://auth0.com/oauth/grant-type/passwordless/otp",
    ])
))->getBody()->getContents(), true);

// Will redirect with an implicit grant,
// so we tell Guzzle not to follow the redirect so we can grab the token from the URL
$authorizeResponse = $http->send(new Request(
    'GET',
    'https://auth0-customer.myhippo.com/authorize?' . http_build_query([
        "client_id" => CLIENT_ID,
        "response_type" => "token id_token",
        "redirect_uri" => "https://myhippo.com/myaccount/login",
        "realm" => $realm,
        "state" => getRandomString(),
        "nonce" => getRandomString(),
        "login_ticket" => $codeResponse['login_ticket'],
        "scope" => "openid profile email",
        "auth0Client" => AUTH0_CLIENT,
    ])
), ['allow_redirects' => false]);

parse_str(parse_url($authorizeResponse->getHeaderLine('Location'), PHP_URL_FRAGMENT), $redirectQueryParts);
$accessToken = $redirectQueryParts['id_token'];

// We're now logged in! Now let's use the access token to get the policy details
$policies = json_decode($http->send(new Request(
    'GET',
    'https://api.myhippo.com/v1/customer/policies',
    ['Authorization' => 'Bearer ' . $accessToken]
))->getBody()->getContents(), true);

echo json_encode($policies, JSON_PRETTY_PRINT);