<?php
/*
    this file contains all the operations on trips.

    the routes handled are:
    GET    /trips              → reads all trips (with associated countries)
    GET    /trips?filters      → reads trips filtered by country and/or seats
    POST   /trips              → creates a new trip
    PUT    /trips/{id}         → updates an existing trip
    DELETE /trips/{id}         → deletes an existing trip

    concept: query string
    the query string is the part of the URL that comes after "?".
    example: /trips?country_id=2&min_seats=5

    in PHP i read it through the "$_GET" superglobal array.
    "$_GET" is automatically available throughout all PHP code
    without needing to be passed as a parameter.
*/
class TripController {

    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }


    /*
        method: getAll()

        retrieves all trips and, for each one, fetches the associated countries.
        returns a JSON structure where every trip contains a "countries" array.
    */
    public function getAll(): void {
        $stmt = $this->pdo->prepare('SELECT id, title, seats FROM trips');
        $stmt->execute();
        $trips = $stmt->fetchAll();

        /*
            for each trip i need to retrieve the associated countries.
            i cannot do this with a single simple query because the countries
            are in a separate table connected through "trip_countries".

            i use a JOIN to merge the tables:
            - "JOIN trip_countries tc ON c.id = tc.country_id" means:
              join the "countries" table with "trip_countries" where the ids match.
            - "WHERE tc.trip_id = :trip_id" filters only the countries
              that belong to the trip i am currently examining.

            i prepare the query once outside the loop (more efficient),
            then execute it as many times as there are trips, changing only :trip_id.
        */
        $stmtCountries = $this->pdo->prepare('
            SELECT c.id, c.name
            FROM countries c
            JOIN trip_countries tc ON c.id = tc.country_id
            WHERE tc.trip_id = :trip_id
        ');

        /*
            "foreach" is a loop that iterates over each element of an array.
            the syntax is: foreach ($array as $index => $element)
            - $index: the numeric position in the array (0, 1, 2, ...)
            - $trip: the value at that position (the array of a single trip)

            i use "$index" because i need to modify the original "$trips" array:
            i write $trips[$index]['countries'] = ... to add the "countries" key
            directly into the original array.
            if i wrote only "foreach ($trips as $trip)", i would be working on a copy
            and the changes would not be reflected in the original array.
        */
        foreach ($trips as $index => $trip) {
            $stmtCountries->bindValue(':trip_id', $trip['id'], PDO::PARAM_INT);
            $stmtCountries->execute();
            $trips[$index]['countries'] = $stmtCountries->fetchAll();
        }

        sendJson($trips, 200);
    }


    /*
        method: getFiltered()

        works like getAll(), but adds dynamic WHERE conditions
        based on the filters present in the URL query string.

        concept: dynamic SQL query construction
        i don't know in advance which filters the client will send.
        it might send only ?country_id, only ?min_seats, both, or neither.
        so i build the SQL query as a PHP string, appending
        WHERE conditions only when the corresponding filter is present.

        i start with a base:
            SELECT id, title, seats FROM trips WHERE 1=1

        "WHERE 1=1" is always true, it serves as an initial "anchor":
        this way i can append further conditions with "AND ..." without
        worrying about whether it is the first or the nth condition.

        then i append SQL pieces based on the filters that are present.
    */
    public function getFiltered(): void {
        $sql = 'SELECT id, title, seats FROM trips WHERE 1=1';
        $params = [];

        /*
            filter by country: ?country_id=2

            "!empty($_GET['country_id'])" means:
            "if 'country_id' exists in the query string and is not empty".

            i add a subquery:
            "WHERE id IN (SELECT trip_id FROM trip_countries WHERE country_id = :country_id)"
            translation: "give me only the trips whose id appears in trip_countries
            with that country_id".

            why use a subquery and not a JOIN?
            because with a JOIN, if a trip has 3 countries, it would appear 3 times
            in the results. the subquery avoids duplicates.

            the filter values are added to the "$params" array.
            this array is then passed directly to execute(),
            which uses it as if i had called bindValue() for each element.
            it is an alternative and more compact way of binding values to placeholders.
        */
        if (!empty($_GET['country_id'])) {
            $sql .= ' AND id IN (SELECT trip_id FROM trip_countries WHERE country_id = :country_id)';
            $params[':country_id'] = (int) $_GET['country_id'];
        }

        /*
            filter by minimum available seats:

            i return only trips with seats >= the received value.
        */
        if (!empty($_GET['min_seats'])) {
            $sql .= ' AND seats >= :min_seats';
            $params[':min_seats'] = (int) $_GET['min_seats'];
        }

        /*
            i pass "$params" directly to execute().
            PDO automatically associates each key in the array (e.g. ':country_id')
            to the corresponding placeholder in the query.
            the result is identical to using bindValue(), but more concise
            when the values are already all collected in an array.
        */
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $trips = $stmt->fetchAll();

        /* i fetch the countries for each trip, identical to getAll() */
        $stmtCountries = $this->pdo->prepare('
            SELECT c.id, c.name
            FROM countries c
            JOIN trip_countries tc ON c.id = tc.country_id
            WHERE tc.trip_id = :trip_id
        ');

        foreach ($trips as $index => $trip) {
            $stmtCountries->bindValue(':trip_id', $trip['id'], PDO::PARAM_INT);
            $stmtCountries->execute();
            $trips[$index]['countries'] = $stmtCountries->fetchAll();
        }

        sendJson($trips, 200);
    }


    /*
        method: create()

        creates a new trip and associates the given countries.

        concept of SQL transactions:

        creating a trip requires two separate operations:
            1. INSERT into the "trips" table
            2. INSERT into the "trip_countries" table (one row per country)

        if the first succeeds but the second fails (e.g. a country does not exist),
        i would end up with a trip in the database but no associated countries.
        the data would be inconsistent.

        transactions solve this problem with a simple principle:
        "all or nothing".

        - beginTransaction(): "start recording the operations"
        - commit(): "confirm everything, make the changes permanent"
        - rollBack(): "cancel everything, go back to the initial state"

        if something goes wrong between beginTransaction() and commit(),
        i call rollBack() and the database returns exactly to how it was before.
    */
    public function create(): void {
        $data = readJsonInput();

        if (empty($data['title'])) {
            sendJson(['error' => 'The "title" field is required.'], 422);
        }

        /*
            i use "!isset()" instead of "empty()" for the "seats" field
            because "seats" is an integer and could be 0 (zero seats available).
            "empty(0)" returns true (considers 0 as empty), which would be wrong:
            0 seats is a valid and legitimate value.
            "!isset()" returns true only if the field does not exist at all.
        */
        if (!isset($data['seats'])) {
            sendJson(['error' => 'The "seats" field is required.'], 422);
        }

        /*
            "country_ids" must be an array with at least one element.
            "!is_array()" checks that it is actually an array.
            "count() === 0" checks that it is not an empty array.
        */
        if (empty($data['country_ids']) || !is_array($data['country_ids'])) {
            sendJson(['error' => 'The "country_ids" field is required and must be an array with at least one id.'], 422);
        }

        $this->pdo->beginTransaction();

        $stmt = $this->pdo->prepare('INSERT INTO trips (title, seats) VALUES (:title, :seats)');
        $stmt->bindValue(':title', $data['title']);
        $stmt->bindValue(':seats', (int) $data['seats'], PDO::PARAM_INT);
        $stmt->execute();

        $newId = (int) $this->pdo->lastInsertId();

        /*
            i loop through the "country_ids" array with foreach.
            for each country id:
            1. i verify that the country exists in the database.
            2. if it does not exist, i roll back the transaction and respond with an error.
            3. if it exists, i insert the row into "trip_countries".

            the syntax "foreach ($data['country_ids'] as $countryId)" means:
            "for each element of the country_ids array, call it $countryId in this loop".
        */
        $stmtCountry = $this->pdo->prepare('INSERT INTO trip_countries (trip_id, country_id) VALUES (:trip_id, :country_id)');

        foreach ($data['country_ids'] as $countryId) {
            $check = $this->pdo->prepare('SELECT id FROM countries WHERE id = :id');
            $check->bindValue(':id', (int) $countryId, PDO::PARAM_INT);
            $check->execute();

            if (!$check->fetch()) {
                $this->pdo->rollBack();
                sendJson(['error' => 'Country with id ' . (int) $countryId . ' not found.'], 422);
            }

            $stmtCountry->bindValue(':trip_id', $newId, PDO::PARAM_INT);
            $stmtCountry->bindValue(':country_id', (int) $countryId, PDO::PARAM_INT);
            $stmtCountry->execute();
        }

        $this->pdo->commit();

        sendJson(['message' => 'Trip created.', 'id' => $newId], 201);
    }


    /*
        method: update($id)

        updates the title and seats of an existing trip.
        if new "country_ids" are provided, it replaces the previous countries
        by first deleting all old associations and then re-inserting the new ones.
    */
    public function update(int $id): void {
        $data = readJsonInput();

        if (empty($data['title'])) {
            sendJson(['error' => 'The "title" field is required.'], 422);
        }
        if (!isset($data['seats'])) {
            sendJson(['error' => 'The "seats" field is required.'], 422);
        }

        $check = $this->pdo->prepare('SELECT id FROM trips WHERE id = :id');
        $check->bindValue(':id', $id, PDO::PARAM_INT);
        $check->execute();

        if (!$check->fetch()) {
            sendJson(['error' => 'Trip not found.'], 404);
        }

        $this->pdo->beginTransaction();

        $stmt = $this->pdo->prepare('UPDATE trips SET title = :title, seats = :seats WHERE id = :id');
        $stmt->bindValue(':title', $data['title']);
        $stmt->bindValue(':seats', (int) $data['seats'], PDO::PARAM_INT);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        /*
            if the client sent "country_ids", i update the associations.
            i first delete all existing associations for this trip,
            then re-insert the new ones.
            this approach is simple: delete everything and rewrite from scratch.
        */
        if (!empty($data['country_ids']) && is_array($data['country_ids'])) {
            $del = $this->pdo->prepare('DELETE FROM trip_countries WHERE trip_id = :trip_id');
            $del->bindValue(':trip_id', $id, PDO::PARAM_INT);
            $del->execute();

            $stmtCountry = $this->pdo->prepare('INSERT INTO trip_countries (trip_id, country_id) VALUES (:trip_id, :country_id)');

            foreach ($data['country_ids'] as $countryId) {
                $check = $this->pdo->prepare('SELECT id FROM countries WHERE id = :id');
                $check->bindValue(':id', (int) $countryId, PDO::PARAM_INT);
                $check->execute();

                if (!$check->fetch()) {
                    $this->pdo->rollBack();
                    sendJson(['error' => 'Country with id ' . (int) $countryId . ' not found.'], 422);
                }

                $stmtCountry->bindValue(':trip_id', $id, PDO::PARAM_INT);
                $stmtCountry->bindValue(':country_id', (int) $countryId, PDO::PARAM_INT);
                $stmtCountry->execute();
            }
        }

        $this->pdo->commit();

        sendJson(['message' => 'Trip updated.'], 200);
    }


    /*
        method: delete($id)

        deletes a trip by its id.
        thanks to the CASCADE in migrations.sql, MySQL automatically
        deletes all rows in "trip_countries" linked to this trip.
    */
    public function delete(int $id): void {
        $check = $this->pdo->prepare('SELECT id FROM trips WHERE id = :id');
        $check->bindValue(':id', $id, PDO::PARAM_INT);
        $check->execute();

        if (!$check->fetch()) {
            sendJson(['error' => 'Trip not found.'], 404);
        }

        $stmt = $this->pdo->prepare('DELETE FROM trips WHERE id = :id');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        sendJson(['message' => 'Trip deleted.'], 200);
    }
}
