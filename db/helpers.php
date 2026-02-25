<?php
/*
    questo file contiene funzioni di supporto che uso in più punti del progetto.
    invece di riscrivere lo stesso codice ogni volta, lo metto in una funzione
    e la chiamo quando serve.
*/

/*
    funzione: rispondiJson($dati, $statusCode)

    questa funzione fa tre cose:
    1. imposta l'header HTTP "Content-Type: application/json",
       che dice al client (Postman, browser, app) che la risposta è in formato JSON.
    2. imposta il codice di stato HTTP (es: 200, 201, 404...).
    3. converte l'array PHP in una stringa JSON e la stampa.

    parametri:
    - $dati:       l'array PHP che voglio restituire come risposta.
    - $statusCode: il codice HTTP da usare. il valore di default è 200,
                   quindi se non lo passo, usa 200 automaticamente.

    i codici HTTP più usati in questo progetto:
    200: ok, richiesta riuscita
    201: created, risorsa creata con successo
    404: not found, la risorsa cercata non esiste
    422: unprocessable entity, i dati inviati non sono validi
    500: internal server error, errore del server
*/
function rispondiJson($dati, $statusCode = 200) {
    header('Content-Type: application/json');
    http_response_code($statusCode);

    /*
        "json_encode()" converte un array PHP in una stringa JSON.
        esempio: ['id' => 1, 'nome' => 'Italia'] diventa {"id":1,"nome":"Italia"}
        è necessario perché HTTP trasporta testo, non strutture PHP.
    */
    echo json_encode($dati);
    exit;
}


/*
    funzione: leggiInputJson()

    quando il client invia una richiesta POST o PUT,
    il corpo (body) della richiesta contiene un JSON.
    PHP non lo legge automaticamente come fa con i normali form HTML:
    devo leggerlo manualmente da "php://input",
    che è uno stream speciale dove PHP riceve il corpo grezzo della richiesta.

    questa funzione:
    1. legge il corpo grezzo della richiesta.
    2. lo converte da stringa JSON ad array PHP con "json_decode()".
    3. lo restituisce.

    "json_decode($stringa, true)" converte la stringa JSON in un array associativo.
    il "true" è necessario: senza di esso, json_decode restituirebbe un oggetto PHP,
    non un array, e non potrei accedere ai valori con la sintassi $dati['nome'].

    "?? []" significa: se json_decode restituisce null (JSON malformato o body vuoto),
    uso un array vuoto come valore di default.
    l'operatore "??" si chiama "null coalescing": restituisce il valore a sinistra
    se non è null, altrimenti restituisce il valore a destra.
*/
function leggiInputJson() {
    $dati = json_decode(file_get_contents('php://input'), true);
    return $dati ?? [];
}
