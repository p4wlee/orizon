<?php
/*
    this is the entry point of the entire API.
    every HTTP request that reaches the server passes through here.

    its job is to read:
    1) the URL of the request (e.g. /countries, /trips/3)
    2) the HTTP method (GET, POST, PUT, DELETE)

    and based on these two elements, require the correct route file.
*/

header('Content-Type: application/json');

/*
    "require_once" includes a PHP file and executes it.
    if the file does not exist, PHP stops with a fatal error.
    i use "require_once" (and not just "require") to avoid including
    the same file more than once by mistake.

    "__DIR__" is a PHP constant that contains the absolute path
    of the folder where the current file is located.
    i use __DIR__ to build stable paths that work
    regardless of where PHP is executed from.

    inclusion order:
    first the database and helpers (because the controllers use them),
    then the controller files,
    then the route files.
*/
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/db/helpers.php';
require_once __DIR__ . '/controllers/CountryController.php';
require_once __DIR__ . '/controllers/TripController.php';

/*
    i open the database connection by calling the function defined
    in config/database.php.
    from this point on, "$pdo" holds the open connection,
    ready to be used in queries.
*/
$pdo = getConnection();

/*
    "$_SERVER" is a PHP superglobal array: it is automatically available
    throughout all code without needing to be passed as a parameter.
    it contains information about the HTTP request received by the server.

    REQUEST_METHOD contains the HTTP method: "GET", "POST", "PUT" or "DELETE".
*/
$method = $_SERVER['REQUEST_METHOD'];

/*
    i build the clean URL path.

    "$_SERVER['REQUEST_URI']" contains the full URL including the query string.
    example: "/orizon/countries/3?foo=bar"

    "parse_url(..., PHP_URL_PATH)" extracts only the path, without the query string.
    result: "/orizon/countries/3"

    "dirname($_SERVER['SCRIPT_NAME'])" returns the folder of index.php.

    "str_replace($base, '', $path)" removes the initial part of the URL
    that corresponds to the project folder.
    final result: "/countries/3"

    this step is necessary because in XAMPP the URL includes the project folder name
    (/orizon/countries instead of just /countries),
    and i want to work only with the "pure" API path.
*/
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
$path = str_replace($base, '', $path);

/*
    i split the path into its parts using "/" as separator.

    "explode('/', '/countries/3')" produces: ['', 'countries', '3']
    the first element is empty because the path starts with "/".

    "array_filter()" removes empty elements from the array.
    "array_values()" re-indexes the array from 0 after the filter.

    final result:
        "/countries"    → ['countries']
        "/countries/3"  → ['countries', '3']
        "/trips"        → ['trips']
        "/trips/5"      → ['trips', '5']
*/
$parts = array_values(array_filter(explode('/', $path)));

/*
    i extract the resource name and the id.

    $parts[0] → the resource name ("countries" or "trips")
    $parts[1] → the id (present only for PUT and DELETE)

    the "??" (null coalescing) operator returns the left-hand value
    if it exists and is not null, otherwise it returns the right-hand value.
    so: if $parts[0] does not exist, $resource is null.
*/
$resource = $parts[0] ?? null;
$id = isset($parts[1]) ? (int) $parts[1] : null;

/*
    router: based on the resource name i require the correct route file.

    the route files can read $method, $parts, $id, and $pdo
    because they are included in this same scope.
*/
if ($resource === 'countries') {
    require __DIR__ . '/routes/countries.php';

} elseif ($resource === 'trips') {
    require __DIR__ . '/routes/trips.php';

} else {
    sendJson(['error' => 'Resource not found.'], 404);
}
