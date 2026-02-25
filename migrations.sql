/*
    questo file serve a creare la struttura del database da zero.
    lo eseguo una volta sola, prima di avviare il progetto.
*/

CREATE DATABASE IF NOT EXISTS orizon;

/*
    "USE orizon" dice a MySQL: da questo momento in poi,
    tutte le operazioni che scrivo riguardano il database "orizon".
    senza questa riga, MySQL non saprebbe in quale database creare le tabelle.
*/
USE orizon;

/*
    tabella: paesi
    
    ogni paese ha:
    - id:   numero intero, generato automaticamente dal database (AUTO_INCREMENT),
            usato come chiave primaria (PRIMARY KEY) per identificare ogni riga
            in modo univoco.
    - nome: stringa di massimo 100 caratteri, obbligatoria (NOT NULL).
*/
CREATE TABLE IF NOT EXISTS paesi ( /* IF NOT EXISTS" significa: crea la tabella solo se non esiste già */
    id   INT          NOT NULL AUTO_INCREMENT,
    nome VARCHAR(100) NOT NULL,
    PRIMARY KEY (id)
);

/*
    tabella: viaggi
    
    ogni viaggio ha:
    - id:     chiave primaria, generata automaticamente.
    - titolo: stringa di massimo 200 caratteri, obbligatoria.
    - posti:  numero intero, obbligatorio.
*/
CREATE TABLE IF NOT EXISTS viaggi (
    id     INT          NOT NULL AUTO_INCREMENT,
    titolo VARCHAR(200) NOT NULL,
    posti  INT          NOT NULL,
    PRIMARY KEY (id)
);

/*
    tabella: viaggi_paesi
    
    un viaggio può toccare più paesi (es: Francia e Spagna),
    e lo stesso paese può appartenere a più viaggi.
    questa si chiama relazione "molti a molti".
    
    in SQL non si può rappresentare direttamente:
    la soluzione è creare una tabella intermedia che contiene
    coppie di id: (viaggio_id, paese_id).
    
    PRIMARY KEY (viaggio_id, paese_id):
    la chiave primaria è composta da entrambe le colonne insieme.
    questo impedisce di inserire la stessa coppia due volte.
    
    FOREIGN KEY:
    una chiave esterna è un vincolo che garantisce la coerenza dei dati.
    "viaggio_id deve essere un id che esiste nella tabella viaggi" e
    "paese_id deve essere un id che esiste nella tabella paesi".
    se provo a inserire un id che non esiste, MySQL restituisce un errore.
    
    ON DELETE CASCADE:
    se elimino un viaggio, MySQL elimina automaticamente
    tutte le righe di questa tabella che lo riguardano.
    stesso discorso per i paesi.
*/
CREATE TABLE IF NOT EXISTS viaggi_paesi (
    viaggio_id INT NOT NULL,
    paese_id   INT NOT NULL,
    PRIMARY KEY (viaggio_id, paese_id),
    FOREIGN KEY (viaggio_id) REFERENCES viaggi (id) ON DELETE CASCADE,
    FOREIGN KEY (paese_id)   REFERENCES paesi  (id) ON DELETE CASCADE
);
