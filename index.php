<?php
/*
    questo è il punto di ingresso dell'intera API.
    ogni richiesta HTTP che arriva al server passa da qui.

    il suo compito è leggere:
    1) l'URL della richiesta (es: /paesi, /viaggi/3)
    2) il metodo HTTP (GET, POST, PUT, DELETE)

    e in base a questi due elementi, chiamare la funzione giusta.
*/

header('Content-Type: application/json');

/*
    "require_once" include un file PHP e lo esegue.
    se il file non esiste, PHP si ferma con un errore fatale.
    uso "require_once" (e non solo "require") per evitare di includere
    lo stesso file più volte per errore.

    "__DIR__" è una costante PHP che contiene il percorso
    assoluto della cartella in cui si trova il file corrente.
    uso __DIR__ per costruire percorsi stabili che funzionano
    indipendentemente da dove viene eseguito PHP.

    ordine di inclusione:
    prima il database e gli helper (perché le funzioni nelle rotte li usano),
    poi i file delle rotte.
*/
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/db/helpers.php';
require_once __DIR__ . '/routes/paesi.php';
require_once __DIR__ . '/routes/viaggi.php';

/*
    apro la connessione al database chiamando la funzione definita
    in config/database.php.
    da questo momento "$pdo" contiene la connessione aperta,
    pronta per essere usata nelle query.
*/
$pdo = getConnection();

/*
    "$_SERVER" è un array superglobale PHP: è disponibile automaticamente
    in tutto il codice senza bisogno di passarlo come parametro.
    contiene informazioni sulla richiesta HTTP ricevuta dal server.

    REQUEST_METHOD contiene il metodo HTTP: "GET", "POST", "PUT" o "DELETE".
*/
$metodo = $_SERVER['REQUEST_METHOD'];

/*
    costruisco il percorso pulito dell'URL.

    "$_SERVER['REQUEST_URI']" contiene l'URL completo inclusa la query string.
    esempio: "/orizon/paesi/3?foo=bar"

    "parse_url(..., PHP_URL_PATH)" estrae solo il percorso, senza query string.
    risultato: "/orizon/paesi/3"

    "dirname($_SERVER['SCRIPT_NAME'])" restituisce la cartella di index.php.

    "str_replace($radice, '', $percorso)" rimuove la parte iniziale dell'URL
    che corrisponde alla cartella del progetto.
    risultato finale: "/paesi/3"

    questo passaggio è necessario perché in XAMPP l'URL include il nome
    della cartella del progetto (/orizon/paesi invece di solo /paesi),
    e io voglio lavorare solo con la parte "pura" dell'API.
*/
$percorso = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$radice   = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
$percorso = str_replace($radice, '', $percorso);

/*
    divido il percorso nelle sue parti usando "/" come separatore.

    "explode('/', '/paesi/3')" produce: ['', 'paesi', '3']
    il primo elemento è vuoto perché il percorso inizia con "/".

    "array_filter()" rimuove gli elementi vuoti dall'array.
    "array_values()" re-indicizza l'array da 0 dopo il filtro.

    risultato finale:
        "/paesi"    → ['paesi']
        "/paesi/3"  → ['paesi', '3']
        "/viaggi"   → ['viaggi']
        "/viaggi/5" → ['viaggi', '5']
*/
$parti = array_values(array_filter(explode('/', $percorso)));

/*
    estraggo la risorsa e l'id.

    $parti[0] → il nome della risorsa ("paesi" o "viaggi")
    $parti[1] → l'id (presente solo per PUT e DELETE)

    l'operatore "??" (null coalescing) restituisce il valore a sinistra
    se esiste e non è null, altrimenti restituisce il valore a destra.
    quindi: se $parti[0] non esiste, $risorsa vale null.
*/
$risorsa = $parti[0] ?? null;
$id      = isset($parti[1]) ? (int)$parti[1] : null;

/*
    router: in base alla risorsa e al metodo HTTP chiamo la funzione giusta.

    uso "if/elseif" per confrontare la risorsa,
    e un secondo "if" annidato per distinguere tra
    operazioni sull'intera collezione (senza id) e sul singolo elemento (con id).
*/

if ($risorsa === 'paesi') {

    if ($id === null) {
        if ($metodo === 'GET')  getAllPaesi($pdo);
        if ($metodo === 'POST') createPaese($pdo);
    } else {
        if ($metodo === 'PUT')    updatePaese($pdo, $id);
        if ($metodo === 'DELETE') deletePaese($pdo, $id);
    }

} elseif ($risorsa === 'viaggi') {

    if ($id === null) {
        if ($metodo === 'GET') {
            /*
                se la query string contiene almeno un parametro (es: ?paese_id=2),
                "$_GET" non sarà vuoto e uso la funzione con i filtri.
                altrimenti restituisco tutti i viaggi senza filtri.
            */
            if (!empty($_GET)) {
                getViaggiFiltered($pdo);
            } else {
                getAllViaggi($pdo);
            }
        }
        if ($metodo === 'POST') createViaggio($pdo);
    } else {
        if ($metodo === 'PUT')    updateViaggio($pdo, $id);
        if ($metodo === 'DELETE') deleteViaggio($pdo, $id);
    }

} else {
    rispondiJson(['errore' => 'Risorsa non trovata.'], 404);
}
