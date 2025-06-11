CREATE TABLE DeletedItems (
    type INTEGER,
    id INTEGER,
    delete_date TEXT NOT NULL,
    from_device INTEGER NOT NULL REFERENCES Device(id) ON UPDATE CASCADE ON DELETE CASCADE,
    PRIMARY KEY (type, id)
)