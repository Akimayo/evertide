CREATE TABLE Device (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    first_login TEXT NOT NULL,
    last_login TEXT NOT NULL,
    cookie TEXT NULL UNIQUE
);