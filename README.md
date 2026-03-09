# ✈️ Orizon – RESTful API for managing countries and trips

## 📖 Description

Orizon is an API for managing the catalogue of a travel agency. The application allows you to:

- Manage countries (full CRUD)
- Manage trips (full CRUD)
- Associate one or more countries to each trip
- Filter trips by country
- Filter trips by number of available seats

---

## 🛠️ Technologies used

- PHP (procedural, no framework)
- MySQL
- PDO for database connection and SQL injection protection

---

## 📁 Project structure

```
orizon/
├── config/
│   └── database.php          → database connection
├── db/
│   └── helpers.php           → utility functions (JSON response, input reading)
├── controllers/
│   ├── CountryController.php → CRUD logic for countries
│   └── TripController.php    → CRUD logic for trips and filters
├── routes/
│   ├── countries.php         → URL mapping for /countries
│   └── trips.php             → URL mapping for /trips
├── .env                      → database credentials (excluded from GitHub)
├── .env.example              → template for the .env file to share
├── .gitignore                → files and folders excluded from GitHub
├── .htaccess                 → URL rewriting for Apache
├── index.php                 → entry point, main router
└── migrations.sql            → SQL script to create the database from scratch
```

---

## ⚙️ Setup

### 1. Create the database

Run the `migrations.sql` file in your preferred MySQL client.
The script creates the `orizon` database and the `countries`, `trips`, `trip_countries` tables.

### 2. Configure environment variables

Copy the `.env.example` file and rename it to `.env`:

```
cp .env.example .env
```

Open `.env` and enter your credentials:

```
DB_HOST=localhost
DB_USER=root
DB_PASS=
DB_NAME=orizon
```

### 3. Start the server

**With XAMPP:** copy the `orizon/` folder inside `htdocs/` and start Apache and MySQL.

**With PHP's built-in server:** run from the terminal inside the project folder:

```
php -S localhost:8000
```

In this case the base URL will be `http://localhost:8000`.

---

## 🎯 Available routes

### 🗺️ Countries

| Method | URL              | Description              |
| ------ | ---------------- | ------------------------ |
| GET    | /countries       | returns all countries    |
| POST   | /countries       | creates a new country    |
| PUT    | /countries/{id}  | updates a country        |
| DELETE | /countries/{id}  | deletes a country        |

**Body for POST and PUT:**

```json
{
  "name": "Italy"
}
```

---

### 🧳 Trips

| Method | URL             | Description                  |
| ------ | --------------- | ---------------------------- |
| GET    | /trips          | returns all trips            |
| GET    | /trips?filters  | returns filtered trips       |
| POST   | /trips          | creates a new trip           |
| PUT    | /trips/{id}     | updates a trip               |
| DELETE | /trips/{id}     | deletes a trip               |

**Available filters (query string):**

| Parameter  | Example           | Description                               |
| ---------- | ----------------- | ----------------------------------------- |
| country_id | ?country_id=2     | filters trips that include that country   |
| min_seats  | ?min_seats=5      | filters trips with at least N seats       |

Filters can be combined: `?country_id=2&min_seats=5`

**Body for POST:**

```json
{
  "title": "Safari in Kenya",
  "seats": 12,
  "country_ids": [1, 2]
}
```

**Body for PUT:**

```json
{
  "title": "Safari in Kenya",
  "seats": 8,
  "country_ids": [1]
}
```

Note: `country_ids` must contain ids of countries that already exist in the database.
If omitted in a PUT request, the countries associated with the trip remain unchanged.

---

## 📝 Response examples

**GET /countries**

```json
[
  { "id": 1, "name": "Italy" },
  { "id": 2, "name": "France" }
]
```

**GET /trips**

```json
[
  {
    "id": 1,
    "title": "Provence Tour",
    "seats": 10,
    "countries": [{ "id": 2, "name": "France" }]
  }
]
```

---

## 📚 HTTP status codes used

| Code | Meaning                                           |
| ---- | ------------------------------------------------- |
| 200  | OK – request succeeded                            |
| 201  | Created – resource successfully created           |
| 404  | Not Found – resource not found                    |
| 422  | Unprocessable Entity – missing or invalid data    |
| 500  | Internal Server Error – server-side error         |

---

## 📬 Contacts

- **GitHub:**  
  https://github.com/p4wlee

- **LinkedIn:**  
  https://www.linkedin.com/in/davide-paulicelli-00295222b/

---

## 📄 License

This project is open source and available under the **MIT** license.
