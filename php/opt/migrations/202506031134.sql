ALTER TABLE Instance ADD COLUMN last_fetch_date TEXT NULL;
ALTER TABLE Instance ADD COLUMN blocked INTEGER NOT NULL DEFAULT 0;
ALTER TABLE Category ADD COLUMN source_id INTEGER NULL;
ALTER TABLE Link ADD COLUMN source_id INTEGER NULL;
CREATE UNIQUE INDEX U_Category_source_source_id ON Category(source, source_id);
INSERT INTO Device (id, name, first_login, last_login, cookie) VALUES (-1, '(remote)', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, NULL);