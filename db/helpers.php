<?php
/*
    this file contains utility functions used in multiple parts of the project.
    instead of rewriting the same code every time, i put it in a function
    and call it whenever needed.
*/

/*
    function: sendJson($data, $statusCode)

    this function does three things:
    1. sets the HTTP header "Content-Type: application/json",
       which tells the client (Postman, browser, app) that the response is in JSON format.
    2. sets the HTTP status code (e.g. 200, 201, 404...).
    3. converts the PHP array to a JSON string and prints it.

    parameters:
    - $data:       the PHP array i want to return as a response.
    - $statusCode: the HTTP code to use. the default value is 200,
                   so if i don't pass it, it automatically uses 200.

    the most common HTTP codes used in this project:
    200: ok, request succeeded
    201: created, resource successfully created
    404: not found, the requested resource does not exist
    422: unprocessable entity, the submitted data is not valid
    500: internal server error, a server-side error occurred
*/
function sendJson(mixed $data, int $statusCode = 200): void {
    header('Content-Type: application/json');
    http_response_code($statusCode);

    /*
        "json_encode()" converts a PHP array to a JSON string.
        example: ['id' => 1, 'name' => 'Italy'] becomes {"id":1,"name":"Italy"}
        this is necessary because HTTP transports text, not PHP structures.
    */
    echo json_encode($data);
    exit;
}


/*
    function: readJsonInput()

    when the client sends a POST or PUT request,
    the request body contains a JSON payload.
    PHP does not read it automatically like it does with regular HTML forms:
    i have to read it manually from "php://input",
    which is a special stream where PHP receives the raw request body.

    this function:
    1. reads the raw request body.
    2. converts it from a JSON string to a PHP array using "json_decode()".
    3. returns it.

    "json_decode($string, true)" converts the JSON string into an associative array.
    the "true" argument is required: without it, json_decode would return a PHP object,
    not an array, and i could not access values with the $data['name'] syntax.

    "?? []" means: if json_decode returns null (malformed JSON or empty body),
    use an empty array as the default value.
    the "??" operator is called "null coalescing": it returns the left-hand value
    if it is not null, otherwise it returns the right-hand value.
*/
function readJsonInput(): array {
    $data = json_decode(file_get_contents('php://input'), true);
    return $data ?? [];
}
