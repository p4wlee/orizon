# ✈️ Orizon – API RESTful per la gestione di paesi e viaggi

## 📖 Descrizione

Orizon è un'API per gestire il catalogo di un'agenzia di viaggi. L'applicazione permette di:

- Gestire paesi (CRUD completo)
- Gestire viaggi (CRUD completo)
- Associare uno o più paesi a ogni viaggio
- Filtrare i viaggi per paese
- Filtrare i viaggi per numero di posti disponibili

---

## 🛠️ tecnologie utilizzate

- PHP (procedurale, senza framework)
- MySQL
- PDO per la connessione al database e la protezione dalle SQL injection

---

## 📁 struttura del progetto

```
orizon/
├── config/
│   └── database.php      → connessione al database
├── db/
│   └── helpers.php       → funzioni di supporto (risposta JSON, lettura input)
├── routes/
│   ├── paesi.php         → operazioni CRUD sui paesi
│   └── viaggi.php        → operazioni CRUD sui viaggi e filtri
├── .env                  → credenziali del database (escluso da GitHub)
├── .env.example          → modello del file .env da condividere
├── .gitignore            → file e cartelle esclusi da GitHub
├── .htaccess             → riscrittura URL per Apache
├── index.php             → punto di ingresso, router principale
└── migrations.sql        → script SQL per creare il database da zero
```

---

## ⚙️ setup

### 1. crea il database

esegui il file `migrations.sql` nel tuo client MySQL preferito.
lo script crea il database `orizon` e le tabelle `paesi`, `viaggi`, `viaggi_paesi`.

### 2. configura le variabili d'ambiente

copia il file `.env.example` e rinominalo in `.env`:

```
cp .env.example .env
```

apri `.env` e inserisci le tue credenziali:

```
DB_HOST=localhost
DB_USER=root
DB_PASS=
DB_NAME=orizon
```

### 3. avvia il server

**con XAMPP:** copia la cartella `orizon/` dentro `htdocs/` e avvia Apache e MySQL.

**con il server built-in di PHP:** lancia da terminale nella cartella del progetto:

```
php -S localhost:8000
```

in questo caso il base URL sarà `http://localhost:8000`.

---

## 🎯 rotte disponibili

### 🗺️ paesi

| metodo | URL         | descrizione               |
| ------ | ----------- | ------------------------- |
| GET    | /paesi      | restituisce tutti i paesi |
| POST   | /paesi      | crea un nuovo paese       |
| PUT    | /paesi/{id} | modifica un paese         |
| DELETE | /paesi/{id} | elimina un paese          |

**body per POST e PUT:**

```json
{
  "nome": "Italia"
}
```

---

### 🧳 viaggi

| metodo | URL            | descrizione                   |
| ------ | -------------- | ----------------------------- |
| GET    | /viaggi        | restituisce tutti i viaggi    |
| GET    | /viaggi?filtri | restituisce i viaggi filtrati |
| POST   | /viaggi        | crea un nuovo viaggio         |
| PUT    | /viaggi/{id}   | modifica un viaggio           |
| DELETE | /viaggi/{id}   | elimina un viaggio            |

**filtri disponibili (query string):**

| parametro | esempio      | descrizione                              |
| --------- | ------------ | ---------------------------------------- |
| paese_id  | ?paese_id=2  | filtra i viaggi che includono quel paese |
| posti_min | ?posti_min=5 | filtra i viaggi con almeno N posti       |

i filtri si possono combinare: `?paese_id=2&posti_min=5`

**body per POST:**

```json
{
  "titolo": "Safari in Kenya",
  "posti": 12,
  "paesi_ids": [1, 2]
}
```

**body per PUT:**

```json
{
  "titolo": "Safari in Kenya",
  "posti": 8,
  "paesi_ids": [1]
}
```

nota: `paesi_ids` deve contenere id di paesi già esistenti nel database.
se viene omesso nella PUT, i paesi associati al viaggio rimangono invariati.

---

## 📝 esempi di risposta

**GET /paesi**

```json
[
  { "id": 1, "nome": "Italia" },
  { "id": 2, "nome": "Francia" }
]
```

**GET /viaggi**

```json
[
  {
    "id": 1,
    "titolo": "Tour della Provenza",
    "posti": 10,
    "paesi": [{ "id": 2, "nome": "Francia" }]
  }
]
```

---

## 📚 status code utilizzati

| codice | significato                                       |
| ------ | ------------------------------------------------- |
| 200    | OK – richiesta riuscita                           |
| 201    | Created – risorsa creata con successo             |
| 404    | Not Found – risorsa non trovata                   |
| 422    | Unprocessable Entity – dati mancanti o non validi |
| 500    | Internal Server Error – errore del server         |

---

## 📬 Contatti

- **GitHub:**  
  https://github.com/p4wlee

- **LinkedIn:**  
  https://www.linkedin.com/in/davide-paulicelli-00295222b/

---

## 📄 Licenza

Questo progetto è open source e disponibile sotto licenza **MIT**.
