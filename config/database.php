<?php
/*
    questo file ha un solo scopo: aprire la connessione al database
    e renderla disponibile al resto del progetto.

    in PHP ogni variabile inizia con il simbolo "$".
    è una regola sintattica obbligatoria: $nome, $id, $pdo, ecc.
    il nome della variabile lo scelgo io, il "$" è fisso.
*/

/*
    lettura del file .env :

    invece di scrivere le credenziali del database direttamente qui,
    le leggo dal file .env che si trova nella root del progetto.

    "file()" legge il file riga per riga e restituisce un array.
    le due opzioni eliminano automaticamente le righe vuote
    e i caratteri di fine riga (\n) da ogni elemento dell'array.

    "__DIR__" è il percorso assoluto della cartella di questo file (config/).
    "/../.env" risale di un livello (alla root del progetto) e cerca .env.
    quindi il percorso completo diventa: /percorso/progetto/orizon/.env
*/
$righeEnv = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

/*
    scorro ogni riga del file .env.
    ogni riga ha il formato: CHIAVE=valore
    esempio: DB_USER=root

    "strpos($riga, '#') === 0" controlla se la riga inizia con "#".
    le righe che iniziano con "#" sono commenti e le salto con "continue".

    "explode('=', $riga, 2)" divide la riga in due parti usando "=" come separatore.
    il "2" indica il numero massimo di parti: anche se il valore contenesse
    un "=" (es: una password come "abc=123"), verrebbe diviso solo al primo "=".
    risultato: ['DB_USER', 'root']

    poi definisco una costante PHP con il nome della chiave e il suo valore.
    "trim()" rimuove eventuali spazi prima e dopo la stringa.
*/
foreach ($righeEnv as $riga) {
    if (strpos($riga, '#') === 0) continue;
    [$chiave, $valore] = explode('=', $riga, 2);
    define(trim($chiave), trim($valore));
}


/*
    funzione: getConnection()

    questa funzione apre la connessione al database e la restituisce
    con "return", cioè la "consegna" a chi ha chiamato la funzione.

    uso PDO (PHP Data Objects) per connettermi a MySQL.
    PDO è una classe PHP che gestisce la connessione al database.
    il motivo per cui scelgo PDO e non altri metodi è che supporta
    i "prepared statement", che proteggono dalle SQL injection
    (questa parte la spiego in dettaglio dentro routes/paesi.php).
*/
function getConnection() {

    /*
        il DSN (Data Source Name) è una stringa che dice a PDO
        dove si trova il database e con quale charset comunicare.
        il charset "utf8mb4" supporta tutti i caratteri, incluse le lettere accentate.
    */
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';

    /*
        PDOException è il tipo di errore che PDO lancia in caso di problemi.
        la variabile "$e" (sta per "exception", cioè eccezione/errore)
        contiene le informazioni sull'errore.
    */
    try {
        /*
            "new PDO(...)" crea un nuovo oggetto di connessione.
            "new" è la parola chiave PHP per creare un'istanza di una classe.
            
            l'array che passo come terzo argomento contiene le opzioni:
            - ERRMODE_EXCEPTION: se c'è un errore SQL, PHP lancia un'eccezione
              invece di ignorarlo silenziosamente.
            - FETCH_ASSOC: quando leggo righe dal database, le ricevo come
              array associativi, cioè con i nomi delle colonne come chiavi.
              esempio: ['id' => 1, 'nome' => 'Italia']
              invece di: [0 => 1, 1 => 'Italia']
        */
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        return $pdo;

    } catch (PDOException $e) {
        /*
            se la connessione fallisce, rispondo con uno status HTTP 500
            (Internal Server Error) e un messaggio JSON.
            poi "exit" ferma completamente l'esecuzione di PHP:
            non ha senso continuare se non c'è il database.
        */
        http_response_code(500);
        echo json_encode(['errore' => 'Connessione al database fallita.']);
        exit;
    }
}
