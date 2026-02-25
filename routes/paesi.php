<?php
/*
    questo file contiene tutte le operazioni sui paesi.
    ogni operazione corrisponde a una funzione PHP.

    le rotte gestite sono:
    GET    /paesi      → legge tutti i paesi
    POST   /paesi      → crea un nuovo paese
    PUT    /paesi/{id} → modifica un paese esistente
    DELETE /paesi/{id} → elimina un paese esistente

    una SQL injection è un attacco in cui qualcuno inserisce codice SQL
    all'interno di un dato (es: il campo "nome") per manipolare il database.
    la soluzione sono i "prepared statement":
    invece di costruire la query come stringa con i valori dentro,
    scrivo la query con dei segnaposto (placeholder) come ":id" o ":nome",
    e poi passo i valori separatamente con "bindValue()".
    in questo modo il database riceve la struttura della query PRIMA,
    poi riceve i valori DOPO, come dati puri.
    non importa cosa contiene il valore: non verrà mai interpretato come SQL.

    nota sul nome "$stmt":
    "stmt" è un'abbreviazione di "statement" (istruzione SQL preparata).
    è una convenzione molto comune in PHP, non un obbligo.
    potrei chiamarla $query o $comando, ma $stmt è lo standard.

    nota su PDO::PARAM_INT:
    quando un valore è un numero intero (come un id), passo PDO::PARAM_INT
    come terzo argomento di bindValue().
    questo dice a PDO: "tratta questo valore come un numero intero, non come testo".
    per i valori di testo (come il nome) non è necessario specificarlo,
    perché PDO::PARAM_STR è il tipo di default.
*/


/*
    funzione: getAllPaesi($pdo)

    recupera tutti i paesi dalla tabella e li restituisce come JSON.

    il parametro "$pdo" è la connessione al database aperta in config/database.php.
    la ricevo come parametro perché è stata creata in index.php
    e la "passo" a questa funzione quando la chiamo.
    in questo modo non devo ricreare la connessione ogni volta.
*/
function getAllPaesi($pdo) {
    $stmt = $pdo->prepare('SELECT id, nome FROM paesi');
    $stmt->execute();

    /*
        "fetchAll()" recupera tutte le righe risultanti dalla query
        e le restituisce come array di array associativi.
        esempio:
            ['id' => 1, 'nome' => 'Italia'],
            ['id' => 2, 'nome' => 'Francia']

        grazie all'opzione FETCH_ASSOC impostata in config/database.php,
        ogni riga è automaticamente un array associativo.
    */
    $paesi = $stmt->fetchAll();

    rispondiJson($paesi, 200);
}


/*
    funzione: createPaese($pdo)

    crea un nuovo paese leggendo il campo "nome" dal body JSON della richiesta.
*/
function createPaese($pdo) {
    /*
        leggo i dati inviati dal client (il JSON nel body della richiesta).
        dopo questa riga, $dati è un array PHP, ad esempio:
        ['nome' => 'Italia']
    */
    $dati = leggiInputJson();

    /*
        "empty()" restituisce true se la variabile non esiste o è vuota ("", 0, null, []).
        se "nome" non è stato inviato o è una stringa vuota, rispondo con un errore 422.
        422 significa "i dati inviati non sono validi o incompleti".
    */
    if (empty($dati['nome'])) {
        rispondiJson(['errore' => 'Il campo "nome" è obbligatorio.'], 422);
    }

    $stmt = $pdo->prepare('INSERT INTO paesi (nome) VALUES (:nome)');
    $stmt->bindValue(':nome', $dati['nome']);
    $stmt->execute();

    /*
        "lastInsertId()" restituisce l'id generato automaticamente dall'ultimo INSERT.
        lo converto in intero con "(int)" perché PDO lo restituisce come stringa.
    */
    $id = (int)$pdo->lastInsertId();

    rispondiJson(['messaggio' => 'Paese creato.', 'id' => $id], 201);
}


/*
    funzione: updatePaese($pdo, $id)

    modifica il nome di un paese esistente.
    ricevo "$id" come parametro: è l'id estratto dall'URL in index.php.
    esempio: PUT /paesi/3 → $id vale 3.
*/
function updatePaese($pdo, $id) {
    $dati = leggiInputJson();

    if (empty($dati['nome'])) {
        rispondiJson(['errore' => 'Il campo "nome" è obbligatorio.'], 422);
    }

    /*
        prima di aggiornare, verifico che il paese con quell'id esista davvero.
        
        "fetch()" (senza "All") recupera una sola riga.
        se la query non trova nessuna riga, fetch() restituisce "false".
    */
    $check = $pdo->prepare('SELECT id FROM paesi WHERE id = :id');
    $check->bindValue(':id', $id, PDO::PARAM_INT);
    $check->execute();

    if (!$check->fetch()) { // if (!$check->fetch())" significa "se non ho trovato nessuna riga
        rispondiJson(['errore' => 'Paese non trovato.'], 404);
    }

    $stmt = $pdo->prepare('UPDATE paesi SET nome = :nome WHERE id = :id');
    $stmt->bindValue(':nome', $dati['nome']);
    $stmt->bindValue(':id',   $id, PDO::PARAM_INT);
    $stmt->execute();

    rispondiJson(['messaggio' => 'Paese aggiornato.'], 200);
}


/*
    funzione: deletePaese($pdo, $id)

    elimina un paese dato il suo id.
    grazie al CASCADE definito in migrations.sql, MySQL elimina automaticamente
    anche tutte le righe di "viaggi_paesi" collegate a questo paese.
*/
function deletePaese($pdo, $id) {
    $check = $pdo->prepare('SELECT id FROM paesi WHERE id = :id');
    $check->bindValue(':id', $id, PDO::PARAM_INT);
    $check->execute();

    if (!$check->fetch()) {
        rispondiJson(['errore' => 'Paese non trovato.'], 404);
    }

    $stmt = $pdo->prepare('DELETE FROM paesi WHERE id = :id');
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    rispondiJson(['messaggio' => 'Paese eliminato.'], 200);
}
