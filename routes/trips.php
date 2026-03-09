<?php
/*
    this file defines the routes for the /trips resource.
    its only job is to map each combination of HTTP method + URL
    to the correct method of TripController.
    no business logic lives here.

    the variables $method, $parts, and $id are set in index.php
    before this file is required.

    routes handled:
    GET    /trips              → TripController::getAll() or TripController::getFiltered()
    POST   /trips              → TripController::create()
    PUT    /trips/{id}         → TripController::update($id)
    DELETE /trips/{id}         → TripController::delete($id)
*/

$controller = new TripController($pdo);

if ($id === null) {
    if ($method === 'GET') {
        /*
            if the query string contains at least one parameter (e.g. ?country_id=2),
            "$_GET" will not be empty and i use the method with filters.
            otherwise i return all trips without filters.
        */
        if (!empty($_GET)) {
            $controller->getFiltered();
        } else {
            $controller->getAll();
        }
    }
    if ($method === 'POST') $controller->create();
} else {
    if ($method === 'PUT') $controller->update($id);
    if ($method === 'DELETE') $controller->delete($id);
}
