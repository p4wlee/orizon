<?php
/*
    this file has one single purpose: open the database connection
    and make it available to the rest of the project.

    in PHP every variable starts with the "$" symbol.
    it is a mandatory syntax rule: $name, $id, $pdo, etc.
    the variable name is chosen by me, the "$" is fixed.
*/

/*
    reading the .env file:

    instead of writing the database credentials directly here,
    i read them from the .env file located in the project root.

    "file()" reads the file line by line and returns an array.
    the two options automatically strip empty lines
    and end-of-line characters (\n) from each element of the array.

    "__DIR__" is the absolute path of the folder containing this file (config/).
    "/../.env" goes up one level (to the project root) and looks for .env.
    so the full path becomes: /path/to/project/orizon/.env
*/
$envLines = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

/*
    i loop through each line of the .env file.
    each line has the format: KEY=value
    example: DB_USER=root

    "strpos($line, '#') === 0" checks if the line starts with "#".
    lines starting with "#" are comments and are skipped with "continue".

    "explode('=', $line, 2)" splits the line into two parts using "=" as separator.
    the "2" is the maximum number of parts: even if the value contained
    a "=" (e.g. a password like "abc=123"), it would only split at the first "=".
    result: ['DB_USER', 'root']

    i then define a PHP constant with the key name and its value.
    "trim()" removes any leading and trailing whitespace from the string.
*/
foreach ($envLines as $line) {
    if (strpos($line, '#') === 0) continue;
    [$key, $value] = explode('=', $line, 2);
    define(trim($key), trim($value));
}


/*
    function: getConnection()

    this function opens the database connection and returns it
    with "return", meaning it "hands it" to whoever called the function.

    i use PDO (PHP Data Objects) to connect to MySQL.
    PDO is a PHP class that manages the database connection.
    the reason i choose PDO over other methods is that it supports
    "prepared statements", which protect against SQL injection
    (i explain this in detail inside controllers/CountryController.php).
*/
function getConnection(): PDO {

    /*
        the DSN (Data Source Name) is a string that tells PDO
        where the database is and which charset to use for communication.
        the charset "utf8mb4" supports all characters, including accented letters.
    */
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';

    /*
        PDOException is the type of error PDO throws when something goes wrong.
        the variable "$e" (short for "exception") contains the error details.
    */
    try {
        /*
            "new PDO(...)" creates a new connection object.
            "new" is the PHP keyword to instantiate a class.

            the array passed as the third argument contains the options:
            - ERRMODE_EXCEPTION: if there is a SQL error, PHP throws an exception
              instead of silently ignoring it.
            - FETCH_ASSOC: when i read rows from the database, i receive them as
              associative arrays, meaning column names are used as keys.
              example: ['id' => 1, 'name' => 'Italy']
              instead of: [0 => 1, 1 => 'Italy']
        */
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        return $pdo;

    } catch (PDOException $e) {
        /*
            if the connection fails, i respond with HTTP status 500
            (Internal Server Error) and a JSON message.
            then "exit" stops PHP execution entirely:
            there is no point continuing if there is no database.
        */
        http_response_code(500);
        echo json_encode(['error' => 'Database connection failed.']);
        exit;
    }
}
