-- ============================================================
-- Scripture Cleanup Migration
-- Generated from data/scripture_cleanup.csv
-- ============================================================
-- Order of operations:
--   1. Delete slokas whose scriptures are being removed outright
--   2. Remap slokas to consolidated scripture IDs
--   3. Delete all old/defunct scripture rows
-- ============================================================

START TRANSACTION;

-- ------------------------------------------------------------
-- STEP 1: Delete slokas tied to scriptures marked "need to delete" or "n/a"
--   These slokas have no valid home and should be removed.
-- ------------------------------------------------------------
DELETE FROM sloka
WHERE scripture_id IN (
    -- "need to delete"
    28,   -- Sri Gaudiya Giti Guccha
    39,   -- Bhajahu Re Mana
    58,   -- Gadadharastakam
    63,   -- Gauranga Balite Habe
    70,   -- Guru Carana Padma
    71,   -- Gurudeva Krpa Bindu Diya
    72,   -- Gurvastakam
    79,   -- Je Anila Prema Dhana
    80,   -- Jiva Jago
    81,   -- Kali Kukkura Kadana
    90,   -- Krpa Kara Vaishnava Thakura
    91,   -- Kunja Bihari Astakam
    108,  -- Nityanandastakam
    122,  -- Radhikastakam
    143,  -- Thakura Vaishnava Pada
    156,  -- Vrinda Devi Astakam
    159,  -- Yamunastakam
    -- "n/a"
    4,    -- Braja Mandala Parikrama
    18,   -- Jaiva Dharma
    19,   -- Kirtaniyah Sada Harih
    20,   -- Madhurya Kadambini
    22,   -- Origin of Ratha Yatra
    24,   -- Prema Samputa
    25    -- Prabandha Pancakam
);

-- ------------------------------------------------------------
-- STEP 2: Remap slokas to consolidated scriptures
-- ------------------------------------------------------------

-- → 11 (Caitanya Caritamrita): absorbs Adi Lila, Madhya Lila, Antya Lila, Candramrita
UPDATE sloka SET scripture_id = 11
WHERE scripture_id IN (12, 13, 14, 50);

-- → 27 (Srimad Bhagavatam): absorbs Venu Gita
UPDATE sloka SET scripture_id = 27
WHERE scripture_id IN (30);

-- → 86 (Krishna Karanmrta): absorbs Cauragraganya Purusastakam
UPDATE sloka SET scripture_id = 86
WHERE scripture_id IN (54);

-- → 160 (Purana): consolidates all Purana sub-texts
UPDATE sloka SET scripture_id = 160
WHERE scripture_id IN (
    33,   -- Adi Purana
    34,   -- Aditya Purana
    43,   -- Bhavishya Purana
    44,   -- Brahmanda Purana
    47,   -- Brihad Vishnu Purana
    48,   -- Brihan Naradiya Purana
    61,   -- Garuda Purana
    74,   -- Hari Bhakti Sudhodaya
    94,   -- Mahabharata
    103,  -- Naradiya Purana
    109,  -- Padma Purana
    131,  -- Skanda Purana
    150,  -- Varaha Purana
    153   -- Vishnu Purana
);

-- → 161 (Upanishad): consolidates all Upanishad texts
UPDATE sloka SET scripture_id = 161
WHERE scripture_id IN (
    45,   -- Brihad Aranyaka Upanishad
    55,   -- Chandogya Upanishad
    76,   -- Isopanishad
    82,   -- Kali Santarana Upanishad
    83,   -- Katha Upanishad
    99,   -- Mundaka Upanishad
    102,  -- Narada Pancaratra
    140,  -- Svetasvatara Upanishad
    141,  -- Taittiriya Upanishad
    146   -- Uttara Gopala Tapani Upanishad
);

-- → 162 (Other Scripture): all remaining misc texts
UPDATE sloka SET scripture_id = 162
WHERE scripture_id IN (
    5,    -- Bhajana Rahasya
    9,    -- Bhakti Tattva Viveka
    15,   -- Gita Govinda
    16,   -- Gaudiya Kanthahara
    23,   -- Prapanna Jivanamritam
    35,   -- Amnaya Sutra
    36,   -- Ananta Samhita
    37,   -- Atma Nivedana
    38,   -- Bhagavat Sandarbha
    40,   -- Bhakti Ratnakara
    41,   -- Bhakti Sandarbha
    42,   -- Bhavartha Dipika
    46,   -- Brihad Gautamiya Tantra
    49,   -- Brihat Bhakti Tattva Sara
    51,   -- Caitanya Candrodaya Nataka
    52,   -- Caitanya Mangala
    53,   -- Caitanya Manjusa
    56,   -- Dana Keli Cintamani
    59,   -- Gandharva Samprarthanastakam
    60,   -- Garga Samhita
    62,   -- Gaura Ganoddesadipika
    64,   -- Gautamiya Tantra
    65,   -- Gita Mala
    66,   -- Gitavali
    67,   -- Gopala Sahasra Nama
    68,   -- Gopinatha
    69,   -- Govardhana Vasa Prarthana
    73,   -- Hamsaduta
    75,   -- Hitopadesa
    77,   -- Jagannatha Vallabha Nataka
    78,   -- Jaladakhyana Samhita
    84,   -- Katyayana Samhita
    85,   -- Krama Dipika
    87,   -- Krishna Nama Dhare Kata Bala
    88,   -- Krishna Sandarbha
    89,   -- Krishna Virahe
    92,   -- Laghu Bhagavatamrita
    93,   -- Lalita Madhava
    95,   -- Mahajana Racita Gita
    96,   -- Mukta Carita
    97,   -- Mukunda Mala Stotra
    98,   -- Mukunda Muktavali
    100,  -- Nama Sankirtana
    101,  -- Namastaka
    104,  -- Navadvipa Dhama Mahatmya
    105,  -- Navadvipa Sataka
    106,  -- Nikunja Rahasya Stava
    107,  -- Nityananda Nistha Prarthana
    111,  -- Pancaratra Pradipa
    112,  -- Paramatma Sandarbha
    113,  -- Paurnamasi Devi Pranama
    114,  -- Prameya Ratnavali
    115,  -- Prema Bhakti Chandrika
    116,  -- Prema Vivarta
    117,  -- Radha Bhajana Mahima
    118,  -- Radha Kripa Kataksha Stava Raja
    119,  -- Radha Krishna Vijnapti
    120,  -- Radha Prarthana
    121,  -- Radhika Carana Padma
    123,  -- Rig Veda
    124,  -- Rupa Manjari Pada
    125,  -- Sad Anga Saranagati
    126,  -- Sammohana Tantra
    127,  -- Sanat Kumara Samhita
    128,  -- Sandilya Bhakti Sutra
    129,  -- Saranagati
    130,  -- Sata Nama Stotra
    132,  -- Sri Radha Nistha
    133,  -- Stava Kalpadruma
    136,  -- Stotra Ratna
    137,  -- Sva Sankalpa Prakasa Stotra
    138,  -- Svarupa Damodara's Diary
    139,  -- Svarupa Damodara's Kadaca
    142,  -- Tattva Sandarbha
    144,  -- Tri Bhangi Pancakam
    145,  -- Ujjvala Nilamani
    147,  -- Vaishnava Tantra
    148,  -- Vaishvanara Samhita
    149,  -- Vamana Kalpa
    151,  -- Vedanta Sutra
    152,  -- Vidagdha Madhava
    154,  -- Vishnu Yamala
    155,  -- Vraja Vilasa Stava
    157,  -- Vrindavana Mahimamrita
    158   -- Yamuna Stotram
);

-- ------------------------------------------------------------
-- STEP 3: Delete all old/defunct scripture rows
--   Includes both the "delete" entries and all remapped-away sources.
-- ------------------------------------------------------------
DELETE FROM scripture
WHERE id IN (
    -- "need to delete" / "n/a" — no sloka remapping, slokas deleted in step 1
    4, 18, 19, 20, 22, 24, 25,
    28, 39, 58, 63, 70, 71, 72,
    79, 80, 81, 90, 91, 108, 122, 143, 156, 159,
    -- Remapped → 11 (Caitanya Caritamrita)
    12, 13, 14, 50,
    -- Remapped → 27 (Srimad Bhagavatam)
    30,
    -- Remapped → 86 (Krishna Karanmrta)
    54,
    -- Remapped → 160 (Purana)
    33, 34, 43, 44, 47, 48, 61, 74, 94, 103, 109, 131, 150, 153,
    -- Remapped → 161 (Upanishad)
    45, 55, 76, 82, 83, 99, 102, 140, 141, 146,
    -- Remapped → 162 (Other Scripture)
    5, 9, 15, 16, 23, 35, 36, 37, 38, 40, 41, 42, 46, 49, 51, 52, 53,
    56, 59, 60, 62, 64, 65, 66, 67, 68, 69, 73, 75, 77, 78, 84, 85,
    87, 88, 89, 92, 93, 95, 96, 97, 98, 100, 101, 104, 105, 106, 107,
    111, 112, 113, 114, 115, 116, 117, 118, 119, 120, 121, 123, 124,
    125, 126, 127, 128, 129, 130, 132, 133, 136, 137, 138, 139, 142,
    144, 145, 147, 148, 149, 151, 152, 154, 155, 157, 158
);

COMMIT;

-- ============================================================
-- Sanity check queries (run after commit to verify):
-- ============================================================
-- SELECT COUNT(*) FROM sloka WHERE scripture_id IN (12,13,14,50,...);
--   → should be 0 (all remapped or deleted)
-- SELECT id, name FROM scripture ORDER BY id;
--   → should only contain the surviving/consolidated scriptures
-- ============================================================
