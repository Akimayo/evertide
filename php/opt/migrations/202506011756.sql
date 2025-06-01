CREATE TABLE Instance (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    domain TEXT NOT NULL,
    link TEXT NOT NULL,
    `primary` TEXT NOT NULL,
    secondary TEXT NOT NULL,
    first_link_date TEXT NOT NULL,
    last_link_date TEXT NULL,
    last_link_status INTEGER NOT NULL DEFAULT -1,
    from_device INTEGER NOT NULL REFERENCES Device(id)
);
CREATE TABLE Category (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    icon TEXT NOT NULL,
    public INTEGER NOT NULL DEFAULT 0,
    parent INTEGER NULL REFERENCES Category(id),
    source INTEGER NULL REFERENCES Instance(id),
    create_date TEXT NOT NULL,
    update_date TEXT NOT NULL,
    from_device INTEGER NOT NULL REFERENCES Device(id)
);
CREATE TABLE Link (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    url TEXT NOT NULL,
    title TEXT NOT NULL,
    blurhash TEXT NULL,
    name TEXT NULL,
    description TEXT NULL,
    favicon TEXT NULL,
    public INTEGER NOT NULL DEFAULT 0,
    category INTEGER NOT NULL REFERENCES Category(id),
    create_date TEXT NOT NULL,
    update_date TEXT NOT NULL,
    from_device INTEGER NOT NULL REFERENCES Device(id)
);