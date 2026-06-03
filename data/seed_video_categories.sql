-- ============================================================
-- Gokul Bhavan — Video Category & Playlist Seed Data
-- Source: data/video_data.csv (manually curated)
-- Run once after creating video_category and video_playlist tables.
-- N3 (Nama Sankirtan) omitted — playlist does not exist yet on YouTube.
-- ============================================================

SET NAMES utf8mb4;

-- ── video_category ────────────────────────────────────────────

INSERT INTO video_category (category_name) VALUES
  ('Harikatha English'),           -- 1
  ('Harikatha-Tamil'),             -- 2
  ('Course-Tamil'),                -- 3
  ('Course-English'),              -- 4
  ('Drama and Dances'),            -- 5
  ('Children'),                    -- 6
  ('Nama Sankirtan'),              -- 7
  ('Events'),                      -- 8
  ('Holy Dhams'),                  -- 9
  ('Holy Days'),                   -- 10
  ('Gurudev and Guru Parampara'),  -- 11
  ('Bhajan Videos');               -- 12


-- ── video_playlist ────────────────────────────────────────────
-- Uses subquery to resolve category_id by name so the seed is
-- order-independent and re-runnable after category resets.

INSERT INTO video_playlist (playlist_id, playlist_name, category_id) VALUES

-- Harikatha English (1)
('PLA7jDWp35w8-A36LkcGvI-4GBJtHxGcW0', 'Harikatha English-01',   (SELECT category_id FROM video_category WHERE category_name = 'Harikatha English')),
('PLA7jDWp35w88h73FmF9FUys_j4VtQ2vpy', 'Harikatha English-02',   (SELECT category_id FROM video_category WHERE category_name = 'Harikatha English')),
('PLA7jDWp35w88hBD-RSscHughq0ypSkyOK', 'Harikatha English-03',   (SELECT category_id FROM video_category WHERE category_name = 'Harikatha English')),
('PLA7jDWp35w8_xahfSia2RysDtS9-TXPVM', 'Harikatha Supplements',  (SELECT category_id FROM video_category WHERE category_name = 'Harikatha English')),
('PLA7jDWp35w8_M2EKB1JrsZ7B4JT1kV2K5', 'Bhajana Katha',          (SELECT category_id FROM video_category WHERE category_name = 'Harikatha English')),
('PLA7jDWp35w8_nrgieUM8u5V_E7J252AvJ', 'Journey of the Soul',    (SELECT category_id FROM video_category WHERE category_name = 'Harikatha English')),

-- Harikatha-Tamil (2)
('PLA7jDWp35w8_uJ5Ui0fLl-3iOx2wKJUX5', 'Harikatha Tamil-01',        (SELECT category_id FROM video_category WHERE category_name = 'Harikatha-Tamil')),
('PLA7jDWp35w8_9POGCtkzIKusIlYUxWdPM', 'Harikatha Tamil-02',        (SELECT category_id FROM video_category WHERE category_name = 'Harikatha-Tamil')),
('PLA7jDWp35w8_UpVX9hxduC_4fBP03ImSG', 'Harikatha Tamil-03',        (SELECT category_id FROM video_category WHERE category_name = 'Harikatha-Tamil')),
('PLA7jDWp35w882K5Pvj_-inaK4ZXNzOdwW', 'Brhad Bhagavamrta Tamil',   (SELECT category_id FROM video_category WHERE category_name = 'Harikatha-Tamil')),
('PLA7jDWp35w8-gMKmsEzQAgV9D31mClkAb', 'Jeevita Padahu Tamil',      (SELECT category_id FROM video_category WHERE category_name = 'Harikatha-Tamil')),

-- Course-Tamil (3)
('PLA7jDWp35w884CCHjGqs-6ZVgtXeRit67', 'Jaiva Dharma Tamil',        (SELECT category_id FROM video_category WHERE category_name = 'Course-Tamil')),

-- Course-English (4)
('PLA7jDWp35w895ydlscMRzxpmMSGnM32X_', 'Brahma Samhita',            (SELECT category_id FROM video_category WHERE category_name = 'Course-English')),
('PLA7jDWp35w88l9eg4b28zTRfIcCFNCj90', 'Jaiva Dharma English',      (SELECT category_id FROM video_category WHERE category_name = 'Course-English')),
('PLA7jDWp35w8-Ju7kiRm-e9z6W_j1OdYC6', 'Jaiva Dharma 2021',        (SELECT category_id FROM video_category WHERE category_name = 'Course-English')),
('PLA7jDWp35w8-eDa3loZTzlvCqa8L60MXx', 'Caitanya Caritamrta',      (SELECT category_id FROM video_category WHERE category_name = 'Course-English')),
('PLA7jDWp35w89ra_x7aYORzy_qwj9jS15F', 'Bhagavad Gita',             (SELECT category_id FROM video_category WHERE category_name = 'Course-English')),
('PLA7jDWp35w8_TiCpHQCmMKO1C-v5TDDCu', 'Bhajana Rahasya',          (SELECT category_id FROM video_category WHERE category_name = 'Course-English')),
('PLA7jDWp35w8-qrscrPCIfnTtHs9ffpfKl', 'Srimad Bhagavatam',        (SELECT category_id FROM video_category WHERE category_name = 'Course-English')),
('PLA7jDWp35w8-aS8L8SjvHzR-afd3CpVhU', 'Bhakti Rasamrta',          (SELECT category_id FROM video_category WHERE category_name = 'Course-English')),
('PLA7jDWp35w8_7Fxh-6w9Na2Key2IN99x2', 'Art of Sadhana',            (SELECT category_id FROM video_category WHERE category_name = 'Course-English')),
('PLA7jDWp35w8_YnHprxoX95RsYhvpzx6Pu', 'Prema Vivarta',             (SELECT category_id FROM video_category WHERE category_name = 'Course-English')),
('PLA7jDWp35w89iTh8XziumnKLnq-xz2vKC', 'Upadesamrta',               (SELECT category_id FROM video_category WHERE category_name = 'Course-English')),
('PLA7jDWp35w8_omiJ-GMXp9WEYhDFJPO5Q', 'Madhurya Kadambini',        (SELECT category_id FROM video_category WHERE category_name = 'Course-English')),

-- Drama and Dances (5)
('PLA7jDWp35w8_qONZJsvDyuc66iyVkj0aJ', 'Dramas',                        (SELECT category_id FROM video_category WHERE category_name = 'Drama and Dances')),
('PLA7jDWp35w89GrEAEke5CFBXXpO9iWRU0', 'Dances and Performances-01',    (SELECT category_id FROM video_category WHERE category_name = 'Drama and Dances')),
('PLA7jDWp35w8_oyr4gvVbg8olW9i5zeK1N', 'Dances and Performances-02',    (SELECT category_id FROM video_category WHERE category_name = 'Drama and Dances')),

-- Children (6)
('PLA7jDWp35w8_C4UwmI9Y3fdvClvsyMihL', 'Stories by Children',           (SELECT category_id FROM video_category WHERE category_name = 'Children')),
('PLA7jDWp35w89AnB9k9uqWr_qd0-tCh3WA', 'Connect 2 Krishna',             (SELECT category_id FROM video_category WHERE category_name = 'Children')),
('PLA7jDWp35w8-bg_2Zh9iRqwplUTn5K1lp', 'Theme Videos',                  (SELECT category_id FROM video_category WHERE category_name = 'Children')),
('PLA7jDWp35w8-pL8vPWXaEzE48rZhh4jMs', 'Children Presentations-01',     (SELECT category_id FROM video_category WHERE category_name = 'Children')),
('PLA7jDWp35w8_7yX0I5HLKoNGYs4kAq8fk', 'Children Presentations-02',     (SELECT category_id FROM video_category WHERE category_name = 'Children')),
('PLA7jDWp35w8-hiu-qDxFIrJXckud0MPTL', 'Podcasts',                      (SELECT category_id FROM video_category WHERE category_name = 'Children')),
('PLA7jDWp35w88sfMD-eChwCChi90SYGmaO', 'I Have a Dream',                (SELECT category_id FROM video_category WHERE category_name = 'Children')),

-- Nama Sankirtan (7) — N3 omitted (playlist not yet created on YouTube)
('PLA7jDWp35w89c8YN9AxwqwOrOHkV3tm7k', 'Sanga Nama Sankirtan',          (SELECT category_id FROM video_category WHERE category_name = 'Nama Sankirtan')),
('PLA7jDWp35w8__ny6tr8MlP7uzZRvBdLns', 'N1',                            (SELECT category_id FROM video_category WHERE category_name = 'Nama Sankirtan')),
('PLA7jDWp35w88TLyKrxwLP_SowTS4w8Lyt', 'N2',                            (SELECT category_id FROM video_category WHERE category_name = 'Nama Sankirtan')),

-- Events (8)
('PLA7jDWp35w89gub-qETBKJi5fPCNhmeWC', 'Nigeria',                       (SELECT category_id FROM video_category WHERE category_name = 'Events')),
('PLA7jDWp35w88qVKvYiJ5wkeeSS1JANCQx', 'Events',                        (SELECT category_id FROM video_category WHERE category_name = 'Events')),

-- Holy Dhams (9)
('PLA7jDWp35w8-yfFVkht9k5Q3WqHW0wIVj', 'Vrindavan-01',                  (SELECT category_id FROM video_category WHERE category_name = 'Holy Dhams')),
('PLA7jDWp35w898-TAO6NM7SlNwMq0idnWe', 'Vrindavan-02',                  (SELECT category_id FROM video_category WHERE category_name = 'Holy Dhams')),
('PLA7jDWp35w89uyB7vErLsb0XG4WCniMGV', 'Nawadwip',                      (SELECT category_id FROM video_category WHERE category_name = 'Holy Dhams')),
('PLA7jDWp35w89IipvkgQ2lGXQTL1s9oXwC', 'Jagannath Puri',                (SELECT category_id FROM video_category WHERE category_name = 'Holy Dhams')),
('PLA7jDWp35w8-FWfy3uboo8e-x2xhdOnYQ', 'Vrindavan Mahimamrta',          (SELECT category_id FROM video_category WHERE category_name = 'Holy Dhams')),

-- Holy Days (10)
('PLA7jDWp35w8_ZB5z0JeRFxlvktdsoAoyM', 'Janmastami',                    (SELECT category_id FROM video_category WHERE category_name = 'Holy Days')),
('PLA7jDWp35w8-L5veM6uCQVuRdjuKbits8', 'Kartik',                        (SELECT category_id FROM video_category WHERE category_name = 'Holy Days')),
('PLA7jDWp35w8_PVXyzHFrHNEaV7DIRYO7J', 'Other Holy Days',               (SELECT category_id FROM video_category WHERE category_name = 'Holy Days')),

-- Gurudev and Guru Parampara (11)
('PLA7jDWp35w89A5OhrXWUdjootXhAI6WRf', 'Srila Gurudev',                 (SELECT category_id FROM video_category WHERE category_name = 'Gurudev and Guru Parampara')),
('PLA7jDWp35w8-BAiWS3aVc_M8qsomKzhs2', 'Guru Darshan',                  (SELECT category_id FROM video_category WHERE category_name = 'Gurudev and Guru Parampara')),
('PLA7jDWp35w8-CJYwheL3kpsD9cywpDRhR', 'Bhakti Yoga Saints',            (SELECT category_id FROM video_category WHERE category_name = 'Gurudev and Guru Parampara')),

-- Bhajan Videos (12)
('PLA7jDWp35w89nKgJ5rDwI7gYhnzzfnf5j', 'Bhajan Videos A',               (SELECT category_id FROM video_category WHERE category_name = 'Bhajan Videos')),
('PLA7jDWp35w89c8e7JuA8KHdynAK0zb46J', 'Bhajan Videos B',               (SELECT category_id FROM video_category WHERE category_name = 'Bhajan Videos')),
('PLA7jDWp35w89dOfToMLcMRNSlGwjITvIR', 'Bhajan Videos C',               (SELECT category_id FROM video_category WHERE category_name = 'Bhajan Videos')),
('PLA7jDWp35w883IsoLi8ddzgu4Lu23yN5A', 'Bhajan Videos D',               (SELECT category_id FROM video_category WHERE category_name = 'Bhajan Videos')),
('PLA7jDWp35w88g1XwVq6EXZGHcHqGiEThi', 'Bhajan Videos E',               (SELECT category_id FROM video_category WHERE category_name = 'Bhajan Videos')),
('PLA7jDWp35w89dJWg709r0z0lr2JIkvbHQ', 'Bhajan Videos F',               (SELECT category_id FROM video_category WHERE category_name = 'Bhajan Videos')),
('PLA7jDWp35w8_DHP37G-P7j3vwCslEPYG-', 'Bhajan Videos G',               (SELECT category_id FROM video_category WHERE category_name = 'Bhajan Videos')),
('PLA7jDWp35w88dGCz7IaOm0PWBARVlLKBx', 'Bhajan Videos H',               (SELECT category_id FROM video_category WHERE category_name = 'Bhajan Videos')),
('PLA7jDWp35w8__bCvV1FCIc3Dna3SXZljy', 'Bhajan Videos T',               (SELECT category_id FROM video_category WHERE category_name = 'Bhajan Videos'));
