-- Pin "Other Scripture" (short_title = 'OTH') to the bottom of all scripture dropdowns.
-- sort_order 99 ensures it appears after all other rows (default sort_order is 0).

UPDATE scripture
SET    sort_order = 99
WHERE  short_title = 'OTH';
