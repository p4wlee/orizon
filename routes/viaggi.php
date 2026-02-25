<?php
/*
    questo file contiene tutte le operazioni sui viaggi.

    le rotte gestite sono:
    GET    /viaggi              → legge tutti i viaggi (con i paesi associati)
    GET    /viaggi?filtri       → legge i viaggi filtrati per paese e/o posti
    POST   /viaggi              → crea un nuovo viaggio
    PUT    /viaggi/{id}         → modifica un viaggio esistente
    DELETE /viaggi/{id}         → elimina un viaggio esistente

    concetto: query string
    la query string è la parte dell'URL che viene dopo il "?".
    esempio: /viaggi?paese_id=2&posti_min=5

    in PHP la leggo tramite l'array superglobale "$_GET".
    "$_GET" è disponibile automaticamente in tutto il codice PHP
    senza bisogno di passarlo come parametro.
*/


/*
    funzione: getAllViaggi($pdo)

    recupera tutti i viaggi e, per ciascuno, recupera i paesi associati.
    restituisce una struttura JSON dove ogni viaggio contiene un array "paesi".
*/
function getAllViaggi($pdo) {
    $stmt = $pdo->prepare('SELECT id, titolo, posti FROM viaggi');
    $stmt->execute();
    $viaggi = $stmt->fetchAll();

    /*
        per ogni viaggio devo recuperare i paesi associati.
        non posso farlo in una singola query semplice perché i paesi
        sono in una tabella separata collegata tramite "viaggi_paesi".

        uso una JOIN per unire le tabelle:
        - "JOIN viaggi_paesi vp ON p.id = vp.paese_id" dice:
          unisci la tabella "paesi" con "viaggi_paesi" dove gli id coincidono.
        - "WHERE vp.viaggio_id = :viaggio_id" filtra solo i paesi
          che appartengono al viaggio che sto esaminando.

        preparo la query una sola volta fuori dal ciclo (più efficiente),
        poi la eseguo tante volte quanti sono i viaggi, cambiando solo :viaggio_id.
    */
    $stmtPaesi = $pdo->prepare('
        SELECT p.id, p.nome
        FROM paesi p
        JOIN viaggi_paesi vp ON p.id = vp.paese_id
        WHERE vp.viaggio_id = :viaggio_id
    ');

    /*
        "foreach" è un ciclo che scorre ogni elemento di un array.
        la sintassi è: foreach ($array as $indice => $elemento)
        - $indice: la posizione numerica nell'array (0, 1, 2, ...)
        - $viaggio: il valore in quella posizione (l'array del singolo viaggio)

        uso "$indice" perché ho bisogno di modificare l'array originale "$viaggi":
        scrivo $viaggi[$indice]['paesi'] = ... per aggiungere la chiave "paesi"
        direttamente nell'array originale.
        se scrivessi solo "foreach ($viaggi as $viaggio)", lavorerei su una copia
        e le modifiche non si rifletterebbero sull'array originale.
    */
    foreach ($viaggi as $indice => $viaggio) {
        $stmtPaesi->bindValue(':viaggio_id', $viaggio['id'], PDO::PARAM_INT);
        $stmtPaesi->execute();
        $viaggi[$indice]['paesi'] = $stmtPaesi->fetchAll();
    }

    rispondiJson($viaggi, 200);
}


/*
    funzione: getViaggiFiltered($pdo)

    funziona come getAllViaggi, ma aggiunge condizioni WHERE dinamiche
    in base ai filtri presenti nella query string dell'URL.

    concetto: costruzione dinamica della query SQL:
    non so in anticipo quali filtri il client invierà.
    potrebbe inviare solo ?paese_id, solo ?posti_min, entrambi, o nessuno.
    quindi costruisco la query SQL come stringa PHP, aggiungendo
    condizioni WHERE solo se il relativo filtro è presente.

    parto da una base:
        SELECT id, titolo, posti FROM viaggi WHERE 1=1

    "WHERE 1=1" è sempre vera, serve come "aggancio" iniziale:
    così posso aggiungere ulteriori condizioni con "AND ..." senza
    preoccuparmi se è la prima o l'ennesima condizione.

    poi aggiungo i pezzi di SQL in base ai filtri presenti.
*/
function getViaggiFiltered($pdo) {
    $sql       = 'SELECT id, titolo, posti FROM viaggi WHERE 1=1';
    $parametri = [];

    /*
        filtro per paese: ?paese_id=2

        "!empty($_GET['paese_id'])" significa:
        "se nella query string esiste 'paese_id' ed è un valore non vuoto".

        aggiungo una sottoquery (subquery):
        "WHERE id IN (SELECT viaggio_id FROM viaggi_paesi WHERE paese_id = :paese_id)"
        traduzione: "dammi solo i viaggi il cui id compare in viaggi_paesi
        con quel paese_id".

        perché uso una sottoquery e non una JOIN?
        perché con la JOIN, se un viaggio ha 3 paesi, apparirebbe 3 volte
        nei risultati. la sottoquery evita i duplicati.

        i valori dei filtri li aggiungo all'array "$parametri".
        questo array viene poi passato direttamente a execute(),
        che lo usa come se avessi chiamato bindValue() per ogni elemento.
        è un modo alternativo e più compatto di legare i valori ai placeholder.
    */
    if (!empty($_GET['paese_id'])) {
        $sql .= ' AND id IN (SELECT viaggio_id FROM viaggi_paesi WHERE paese_id = :paese_id)';
        $parametri[':paese_id'] = (int)$_GET['paese_id'];
    }

    /*
        filtro per posti minimi:

        restituisco solo i viaggi con posti >= al valore ricevuto.
    */
    if (!empty($_GET['posti_min'])) {
        $sql .= ' AND posti >= :posti_min';
        $parametri[':posti_min'] = (int)$_GET['posti_min'];
    }

    /*
        passo "$parametri" direttamente a execute().
        PDO associa automaticamente ogni chiave dell'array (es: ':paese_id')
        al placeholder corrispondente nella query.
        il risultato è identico a usare bindValue(), ma più conciso
        quando i valori sono già tutti raccolti in un array.
    */
    $stmt = $pdo->prepare($sql);
    $stmt->execute($parametri);
    $viaggi = $stmt->fetchAll();

    /* recupero i paesi per ogni viaggio, identico ad getAllViaggi */
    $stmtPaesi = $pdo->prepare('
        SELECT p.id, p.nome
        FROM paesi p
        JOIN viaggi_paesi vp ON p.id = vp.paese_id
        WHERE vp.viaggio_id = :viaggio_id
    ');

    foreach ($viaggi as $indice => $viaggio) {
        $stmtPaesi->bindValue(':viaggio_id', $viaggio['id'], PDO::PARAM_INT);
        $stmtPaesi->execute();
        $viaggi[$indice]['paesi'] = $stmtPaesi->fetchAll();
    }

    rispondiJson($viaggi, 200);
}


/*
    funzione: createViaggio($pdo)

    crea un nuovo viaggio e associa i paesi indicati.

    concetto di transazioni SQL:

    creare un viaggio richiede due operazioni separate:
        1. INSERT nella tabella "viaggi"
        2. INSERT nella tabella "viaggi_paesi" (una riga per ogni paese)

    se la prima va a buon fine ma la seconda fallisce (es: un paese non esiste),
    mi ritroverei con un viaggio nel database ma senza paesi associati.
    i dati sarebbero inconsistenti.

    le transazioni risolvono questo problema con un principio semplice:
    "tutto o niente".

    - beginTransaction(): "inizia a registrare le operazioni"
    - commit(): "conferma tutto, rendi permanenti le modifiche"
    - rollBack(): "annulla tutto, torna allo stato iniziale"

    se qualcosa va storto tra beginTransaction() e commit(),
    chiamo rollBack() e il database torna esattamente com'era prima.
    -----------------------------------------------------------------
*/
function createViaggio($pdo) {
    $dati = leggiInputJson();

    if (empty($dati['titolo'])) {
        rispondiJson(['errore' => 'Il campo "titolo" è obbligatorio.'], 422);
    }

    /*
        uso "!isset()" invece di "empty()" per il campo "posti"
        perché "posti" è un numero intero e potrebbe valere 0 (zero posti disponibili).
        "empty(0)" restituisce true (considera 0 come vuoto), il che sarebbe sbagliato:
        0 posti è un valore valido e legittimo.
        "!isset()" restituisce true solo se il campo non esiste del tutto.
    */
    if (!isset($dati['posti'])) {
        rispondiJson(['errore' => 'Il campo "posti" è obbligatorio.'], 422);
    }

    /*
        "paesi_ids" deve essere un array con almeno un elemento.
        "!is_array()" verifica che sia effettivamente un array.
        "count() === 0" verifica che non sia un array vuoto.
    */
    if (empty($dati['paesi_ids']) || !is_array($dati['paesi_ids'])) {
        rispondiJson(['errore' => 'Il campo "paesi_ids" è obbligatorio e deve essere un array con almeno un id.'], 422);
    }

    $pdo->beginTransaction();

    $stmt = $pdo->prepare('INSERT INTO viaggi (titolo, posti) VALUES (:titolo, :posti)');
    $stmt->bindValue(':titolo', $dati['titolo']);
    $stmt->bindValue(':posti',  (int)$dati['posti'], PDO::PARAM_INT);
    $stmt->execute();

    $nuovoId = (int)$pdo->lastInsertId();

    /*
        scorro l'array "paesi_ids" con foreach.
        per ogni id di paese:
        1. verifico che il paese esista nel database.
        2. se non esiste, annullo la transazione e rispondo con errore.
        3. se esiste, inserisco la riga in "viaggi_paesi".

        la sintassi "foreach ($dati['paesi_ids'] as $paeseId)" significa:
        "per ogni elemento dell'array paesi_ids, chiamalo $paeseId in questo ciclo".
    */
    $stmtPaese = $pdo->prepare('INSERT INTO viaggi_paesi (viaggio_id, paese_id) VALUES (:viaggio_id, :paese_id)');

    foreach ($dati['paesi_ids'] as $paeseId) {
        $check = $pdo->prepare('SELECT id FROM paesi WHERE id = :id');
        $check->bindValue(':id', (int)$paeseId, PDO::PARAM_INT);
        $check->execute();

        if (!$check->fetch()) {
            $pdo->rollBack();
            rispondiJson(['errore' => 'Paese con id ' . (int)$paeseId . ' non trovato.'], 422);
        }

        $stmtPaese->bindValue(':viaggio_id', $nuovoId,       PDO::PARAM_INT);
        $stmtPaese->bindValue(':paese_id',   (int)$paeseId,  PDO::PARAM_INT);
        $stmtPaese->execute();
    }

    $pdo->commit();

    rispondiJson(['messaggio' => 'Viaggio creato.', 'id' => $nuovoId], 201);
}


/*
    funzione: updateViaggio($pdo, $id)

    aggiorna titolo e posti di un viaggio esistente.
    se vengono forniti nuovi "paesi_ids", sostituisce i paesi precedenti
    eliminando prima tutte le associazioni vecchie e reinserendo quelle nuove.
*/
function updateViaggio($pdo, $id) {
    $dati = leggiInputJson();

    if (empty($dati['titolo'])) {
        rispondiJson(['errore' => 'Il campo "titolo" è obbligatorio.'], 422);
    }
    if (!isset($dati['posti'])) {
        rispondiJson(['errore' => 'Il campo "posti" è obbligatorio.'], 422);
    }

    $check = $pdo->prepare('SELECT id FROM viaggi WHERE id = :id');
    $check->bindValue(':id', $id, PDO::PARAM_INT);
    $check->execute();

    if (!$check->fetch()) {
        rispondiJson(['errore' => 'Viaggio non trovato.'], 404);
    }

    $pdo->beginTransaction();

    $stmt = $pdo->prepare('UPDATE viaggi SET titolo = :titolo, posti = :posti WHERE id = :id');
    $stmt->bindValue(':titolo', $dati['titolo']);
    $stmt->bindValue(':posti',  (int)$dati['posti'], PDO::PARAM_INT);
    $stmt->bindValue(':id',     $id,                 PDO::PARAM_INT);
    $stmt->execute();

    /*
        se il client ha inviato "paesi_ids", aggiorno le associazioni.
        prima elimino tutte le associazioni esistenti per questo viaggio,
        poi reinserisco quelle nuove.
        questo approccio è semplice: cancella tutto e riscrivi da capo.
    */
    if (!empty($dati['paesi_ids']) && is_array($dati['paesi_ids'])) {
        $del = $pdo->prepare('DELETE FROM viaggi_paesi WHERE viaggio_id = :viaggio_id');
        $del->bindValue(':viaggio_id', $id, PDO::PARAM_INT);
        $del->execute();

        $stmtPaese = $pdo->prepare('INSERT INTO viaggi_paesi (viaggio_id, paese_id) VALUES (:viaggio_id, :paese_id)');

        foreach ($dati['paesi_ids'] as $paeseId) {
            $check = $pdo->prepare('SELECT id FROM paesi WHERE id = :id');
            $check->bindValue(':id', (int)$paeseId, PDO::PARAM_INT);
            $check->execute();

            if (!$check->fetch()) {
                $pdo->rollBack();
                rispondiJson(['errore' => 'Paese con id ' . (int)$paeseId . ' non trovato.'], 422);
            }

            $stmtPaese->bindValue(':viaggio_id', $id,           PDO::PARAM_INT);
            $stmtPaese->bindValue(':paese_id',   (int)$paeseId, PDO::PARAM_INT);
            $stmtPaese->execute();
        }
    }

    $pdo->commit();

    rispondiJson(['messaggio' => 'Viaggio aggiornato.'], 200);
}


/*
    funzione: deleteViaggio($pdo, $id)

    elimina un viaggio dato il suo id.
    grazie al CASCADE in migrations.sql, MySQL elimina automaticamente
    tutte le righe di "viaggi_paesi" collegate a questo viaggio.
*/
function deleteViaggio($pdo, $id) {
    $check = $pdo->prepare('SELECT id FROM viaggi WHERE id = :id');
    $check->bindValue(':id', $id, PDO::PARAM_INT);
    $check->execute();

    if (!$check->fetch()) {
        rispondiJson(['errore' => 'Viaggio non trovato.'], 404);
    }

    $stmt = $pdo->prepare('DELETE FROM viaggi WHERE id = :id');
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    rispondiJson(['messaggio' => 'Viaggio eliminato.'], 200);
}
