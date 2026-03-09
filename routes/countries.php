<?php
/*
    this file defines the routes for the /countries resource.
    its only job is to map each combination of HTTP method + URL
    to the correct method of CountryController.
    no business logic lives here.

    the variables $method, $parts, and $id are set in index.php
    before this file is required.

    routes handled:
    GET    /countries      → CountryController::getAll()
    POST   /countries      → CountryController::create()
    PUT    /countries/{id} → CountryController::update($id)
    DELETE /countries/{id} → CountryController::delete($id)
*/

$controller = new CountryController($pdo);

if ($id === null) {
    if ($method === 'GET') $controller->getAll();
    if ($method === 'POST') $controller->create();
} else {
    if ($method === 'PUT') $controller->update($id);
    if ($method === 'DELETE') $controller->delete($id);
}
