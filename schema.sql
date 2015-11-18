CREATE TABLE extensions (
    extension TEXT NOT NULL PRIMARY KEY,
    mime_type TEXT NOT NULL,
    UNIQUE (extension) ON CONFLICT REPLACE
);
