-- Normalize existing search_title values to match the updated makeSearchTitle() logic.
-- Replaces hyphens, em-dashes, and common punctuation with spaces, then lowercases
-- and collapses whitespace.  Safe to re-run (idempotent).
--
-- Requires MySQL 8.0+ for REGEXP_REPLACE.

UPDATE sloka
SET search_title = LOWER(
    TRIM(
        REGEXP_REPLACE(
            REPLACE(
            REPLACE(
            REPLACE(
            REPLACE(
            REPLACE(
            REPLACE(
            REPLACE(
            REPLACE(
            REPLACE(
            REPLACE(
            REPLACE(
            REPLACE(
                search_title,
                '—', ' '),   /* em-dash */
                '–', ' '),   /* en-dash */
                '-', ' '),   /* hyphen  */
                ':', ' '),
                ';', ' '),
                ',', ' '),
                '.', ' '),
                '!', ' '),
                '?', ' '),
                '(', ' '),
                ')', ' '),
                '[', ' '),
                ']', ' '),
            ' {2,}', ' '    /* collapse multiple spaces */
        )
    )
)
WHERE search_title IS NOT NULL;
