ALTER TABLE Instance ADD COLUMN last_edit_date TEXT NOT NULL DEFAULT ""; -- Temporary empty string, filled in the UPDATE statement
ALTER TABLE Instance ADD COLUMN sticker_path TEXT NULL;
ALTER TABLE Instance ADD COLUMN display_sticker INTEGER NOT NULL DEFAULT 0;
UPDATE Instance SET last_edit_date = self.first_link_date FROM Instance self;
