"""
map_sloka_scriptures.py
────────────────────────
Reads data/sloka_data.csv, matches each unique scripture_reference value
to one of the 32 scripture records in the DB, and generates
data/update_sloka_scripture_ids.sql.

Usage:
    cd /path/to/gokulbhavan
    python3 scripts/map_sloka_scriptures.py

Output:
    data/update_sloka_scripture_ids.sql  — run via phpMyAdmin
    Console report: matched / unmatched counts + unmatched ref list
"""

import csv
import re
import unicodedata
from collections import defaultdict
from pathlib import Path

ROOT       = Path(__file__).parent.parent
CSV_PATH   = ROOT / 'data' / 'sloka_data.csv'
OUTPUT_SQL = ROOT / 'data' / 'update_sloka_scripture_ids.sql'

# ── Scripture table (from DB — id, name, short_title) ─────────────────────────
SCRIPTURES = [
    {"id":   1, "name": "Brihad Bhagavatamrita",                    "short_title": "BB"},
    {"id":   2, "name": "Bhagavad Gita",                            "short_title": "BG"},
    {"id":   3, "name": "Srimad Bhagavad Gita",                     "short_title": "SBG"},
    {"id":   4, "name": "Braja Mandala Parikrama",                  "short_title": "BMP"},
    {"id":   5, "name": "Bhajana Rahasya",                          "short_title": "BR"},
    {"id":   6, "name": "Bhakti Rasamrita Sindhu",                  "short_title": "BRS"},
    {"id":   7, "name": "Bhakti Rasamrita Sindhu Bindhu",           "short_title": "BRSB"},
    {"id":   8, "name": "Brahma Samhita",                           "short_title": "BS"},
    {"id":   9, "name": "Bhakti Tattva Viveka",                     "short_title": "BTV"},
    {"id":  10, "name": "Caitanya Bhagavata",                       "short_title": "CB"},
    {"id":  11, "name": "Caitanya Caritamrita",                     "short_title": "CC"},
    {"id":  12, "name": "Caitanya Caritamrita - Adi Lila",          "short_title": "CC Adi"},
    {"id":  13, "name": "Caitanya Caritamrita - Madhya Lila",       "short_title": "CC Mad"},
    {"id":  14, "name": "Caitanya Caritamrita - Antya Lila",        "short_title": "CC Antya"},
    {"id":  15, "name": "Gita Govinda",                             "short_title": "GG"},
    {"id":  16, "name": "Gaudiya Kanthahara",                       "short_title": "GKH"},
    {"id":  17, "name": "Hari Bhakti Vilasa",                       "short_title": "HBV"},
    {"id":  18, "name": "Jaiva Dharma",                             "short_title": "JD"},
    {"id":  19, "name": "Kirtaniyah Sada Harih",                    "short_title": "KSD"},
    {"id":  20, "name": "Madhurya Kadambini",                       "short_title": "MK"},
    {"id":  21, "name": "Manah Siksa",                              "short_title": "MS"},
    {"id":  22, "name": "Origin of Ratha Yatra",                    "short_title": "ORY"},
    {"id":  23, "name": "Prapanna Jivanamritam",                    "short_title": "PJ"},
    {"id":  24, "name": "Prema Samputa",                            "short_title": "PS"},
    {"id":  25, "name": "Prabandha Pancakam",                       "short_title": "PP"},
    {"id":  26, "name": "Radha Rasa Sudha Nidhi",                   "short_title": "RRSN"},
    {"id":  27, "name": "Srimad Bhagavatam",                        "short_title": "SB"},
    {"id":  28, "name": "Sri Gaudiya Giti Guccha",                  "short_title": "SGGG"},
    {"id":  29, "name": "Siksastaka",                               "short_title": "SS"},
    {"id":  30, "name": "Venu Gita",                                "short_title": "VG"},
    {"id":  31, "name": "Vilapa Kusumanjalih",                      "short_title": "VK"},
    {"id":  32, "name": "Upadesamrita",                             "short_title": "Upad."},
    {"id":  33, "name": "Adi Purana",                               "short_title": "ADIP"},
    {"id":  34, "name": "Aditya Purana",                            "short_title": "ADTY"},
    {"id":  35, "name": "Amnaya Sutra",                             "short_title": "AMNS"},
    {"id":  36, "name": "Ananta Samhita",                           "short_title": "ANTS"},
    {"id":  37, "name": "Atma Nivedana",                            "short_title": "ATNV"},
    {"id":  38, "name": "Bhagavat Sandarbha",                       "short_title": "BHGS"},
    {"id":  39, "name": "Bhajahu Re Mana",                          "short_title": "BHRM"},
    {"id":  40, "name": "Bhakti Ratnakara",                         "short_title": "BHRK"},
    {"id":  41, "name": "Bhakti Sandarbha",                         "short_title": "BHSN"},
    {"id":  42, "name": "Bhavartha Dipika",                         "short_title": "BHDI"},
    {"id":  43, "name": "Bhavishya Purana",                         "short_title": "BHVP"},
    {"id":  44, "name": "Brahmanda Purana",                         "short_title": "BRMP"},
    {"id":  45, "name": "Brihad Aranyaka Upanishad",                "short_title": "BAUP"},
    {"id":  46, "name": "Brihad Gautamiya Tantra",                  "short_title": "BGTN"},
    {"id":  47, "name": "Brihad Vishnu Purana",                     "short_title": "BVSP"},
    {"id":  48, "name": "Brihan Naradiya Purana",                   "short_title": "BNRP"},
    {"id":  49, "name": "Brihat Bhakti Tattva Sara",                "short_title": "BBTS"},
    {"id":  50, "name": "Caitanya Candramrita",                     "short_title": "CCAM"},
    {"id":  51, "name": "Caitanya Candrodaya Nataka",               "short_title": "CCNT"},
    {"id":  52, "name": "Caitanya Mangala",                         "short_title": "CMGL"},
    {"id":  53, "name": "Caitanya Manjusa",                         "short_title": "CMNJ"},
    {"id":  54, "name": "Cauragraganya Purusastakam",               "short_title": "CAUP"},
    {"id":  55, "name": "Chandogya Upanishad",                      "short_title": "CHUP"},
    {"id":  56, "name": "Dana Keli Cintamani",                      "short_title": "DKC"},
    {"id":  57, "name": "Dasa Mula Tattva",                         "short_title": "DMT"},
    {"id":  58, "name": "Gadadharastakam",                          "short_title": "GDAST"},
    {"id":  59, "name": "Gandharva Samprarthanastakam",             "short_title": "GSAST"},
    {"id":  60, "name": "Garga Samhita",                            "short_title": "GASM"},
    {"id":  61, "name": "Garuda Purana",                            "short_title": "GRDP"},
    {"id":  62, "name": "Gaura Ganoddesadipika",                    "short_title": "GGDP"},
    {"id":  63, "name": "Gauranga Balite Habe",                     "short_title": "GBH"},
    {"id":  64, "name": "Gautamiya Tantra",                         "short_title": "GTAN"},
    {"id":  65, "name": "Gita Mala",                                "short_title": "GTML"},
    {"id":  66, "name": "Gitavali",                                 "short_title": "GTAV"},
    {"id":  67, "name": "Gopala Sahasra Nama",                      "short_title": "GSNAM"},
    {"id":  68, "name": "Gopinatha",                                "short_title": "GOPIN"},
    {"id":  69, "name": "Govardhana Vasa Prarthana",                "short_title": "GVPR"},
    {"id":  70, "name": "Guru Carana Padma",                        "short_title": "GCPAD"},
    {"id":  71, "name": "Gurudeva Krpa Bindu Diya",                 "short_title": "GKBD"},
    {"id":  72, "name": "Gurvastakam",                              "short_title": "GVAST"},
    {"id":  73, "name": "Hamsaduta",                                "short_title": "HAMS"},
    {"id":  74, "name": "Hari Bhakti Sudhodaya",                    "short_title": "HBSU"},
    {"id":  75, "name": "Hitopadesa",                               "short_title": "HITO"},
    {"id":  76, "name": "Isopanishad",                              "short_title": "ISOP"},
    {"id":  77, "name": "Jagannatha Vallabha Nataka",               "short_title": "JVNAT"},
    {"id":  78, "name": "Jaladakhyana Samhita",                     "short_title": "JALS"},
    {"id":  79, "name": "Je Anila Prema Dhana",                     "short_title": "JAPD"},
    {"id":  80, "name": "Jiva Jago",                                "short_title": "JIVJ"},
    {"id":  81, "name": "Kali Kukkura Kadana",                      "short_title": "KKKAD"},
    {"id":  82, "name": "Kali Santarana Upanishad",                 "short_title": "KSUP"},
    {"id":  83, "name": "Katha Upanishad",                          "short_title": "KATHU"},
    {"id":  84, "name": "Katyayana Samhita",                        "short_title": "KATS"},
    {"id":  85, "name": "Krama Dipika",                             "short_title": "KRMD"},
    {"id":  86, "name": "Krishna Karnamrita",                       "short_title": "KKAM"},
    {"id":  87, "name": "Krishna Nama Dhare Kata Bala",             "short_title": "KNDB"},
    {"id":  88, "name": "Krishna Sandarbha",                        "short_title": "KSAN"},
    {"id":  89, "name": "Krishna Virahe",                           "short_title": "KRVIR"},
    {"id":  90, "name": "Krpa Kara Vaishnava Thakura",              "short_title": "KKVT"},
    {"id":  91, "name": "Kunja Bihari Astakam",                     "short_title": "KBAST"},
    {"id":  92, "name": "Laghu Bhagavatamrita",                     "short_title": "LBAG"},
    {"id":  93, "name": "Lalita Madhava",                           "short_title": "LMAD"},
    {"id":  94, "name": "Mahabharata",                              "short_title": "MAHB"},
    {"id":  95, "name": "Mahajana Racita Gita",                     "short_title": "MRGIT"},
    {"id":  96, "name": "Mukta Carita",                             "short_title": "MUKC"},
    {"id":  97, "name": "Mukunda Mala Stotra",                      "short_title": "MMST"},
    {"id":  98, "name": "Mukunda Muktavali",                        "short_title": "MUKM"},
    {"id":  99, "name": "Mundaka Upanishad",                        "short_title": "MUND"},
    {"id": 100, "name": "Nama Sankirtana",                          "short_title": "NMSK"},
    {"id": 101, "name": "Namastaka",                                "short_title": "NAST"},
    {"id": 102, "name": "Narada Pancaratra",                        "short_title": "NPAN"},
    {"id": 103, "name": "Naradiya Purana",                          "short_title": "NPUR"},
    {"id": 104, "name": "Navadvipa Dhama Mahatmya",                 "short_title": "NDM"},
    {"id": 105, "name": "Navadvipa Sataka",                         "short_title": "NSAT"},
    {"id": 106, "name": "Nikunja Rahasya Stava",                    "short_title": "NRS"},
    {"id": 107, "name": "Nityananda Nistha Prarthana",              "short_title": "NNP"},
    {"id": 108, "name": "Nityanandastakam",                         "short_title": "NITAS"},
    {"id": 109, "name": "Padma Purana",                             "short_title": "PADP"},
    {"id": 110, "name": "Padyavali",                                "short_title": "PADY"},
    {"id": 111, "name": "Pancaratra Pradipa",                       "short_title": "PANP"},
    {"id": 112, "name": "Paramatma Sandarbha",                      "short_title": "PASAN"},
    {"id": 113, "name": "Paurnamasi Devi Pranama",                  "short_title": "PDPRA"},
    {"id": 114, "name": "Prameya Ratnavali",                        "short_title": "PRAMR"},
    {"id": 115, "name": "Prema Bhakti Chandrika",                   "short_title": "PBCH"},
    {"id": 116, "name": "Prema Vivarta",                            "short_title": "PVIV"},
    {"id": 117, "name": "Radha Bhajana Mahima",                     "short_title": "RBHM"},
    {"id": 118, "name": "Radha Kripa Kataksha Stava Raja",          "short_title": "RKKS"},
    {"id": 119, "name": "Radha Krishna Vijnapti",                   "short_title": "RKVJ"},
    {"id": 120, "name": "Radha Prarthana",                          "short_title": "RADP"},
    {"id": 121, "name": "Radhika Carana Padma",                     "short_title": "RCPAD"},
    {"id": 122, "name": "Radhikastakam",                            "short_title": "RAST"},
    {"id": 123, "name": "Rig Veda",                                 "short_title": "RGVED"},
    {"id": 124, "name": "Rupa Manjari Pada",                        "short_title": "RMPAD"},
    {"id": 125, "name": "Sad Anga Saranagati",                      "short_title": "SASAR"},
    {"id": 126, "name": "Sammohana Tantra",                         "short_title": "SAMT"},
    {"id": 127, "name": "Sanat Kumara Samhita",                     "short_title": "SKSAM"},
    {"id": 128, "name": "Sandilya Bhakti Sutra",                    "short_title": "SBSUT"},
    {"id": 129, "name": "Saranagati",                               "short_title": "SARAN"},
    {"id": 130, "name": "Sata Nama Stotra",                         "short_title": "SNST"},
    {"id": 131, "name": "Skanda Purana",                            "short_title": "SKP"},
    {"id": 132, "name": "Sri Radha Nistha",                         "short_title": "SRNIS"},
    {"id": 133, "name": "Stava Kalpadruma",                         "short_title": "STVK"},
    {"id": 134, "name": "Stava Mala",                               "short_title": "STVM"},
    {"id": 135, "name": "Stavavali",                                "short_title": "STAV"},
    {"id": 136, "name": "Stotra Ratna",                             "short_title": "STRAT"},
    {"id": 137, "name": "Sva Sankalpa Prakasa Stotra",              "short_title": "SSPS"},
    {"id": 138, "name": "Svarupa Damodara's Diary",                 "short_title": "SDDIA"},
    {"id": 139, "name": "Svarupa Damodara's Kadaca",                "short_title": "SDKAD"},
    {"id": 140, "name": "Svetasvatara Upanishad",                   "short_title": "SVUP"},
    {"id": 141, "name": "Taittiriya Upanishad",                     "short_title": "TAUP"},
    {"id": 142, "name": "Tattva Sandarbha",                         "short_title": "TATS"},
    {"id": 143, "name": "Thakura Vaishnava Pada",                   "short_title": "TVP"},
    {"id": 144, "name": "Tri Bhangi Pancakam",                      "short_title": "TBP"},
    {"id": 145, "name": "Ujjvala Nilamani",                         "short_title": "UNIL"},
    {"id": 146, "name": "Uttara Gopala Tapani Upanishad",           "short_title": "UGTU"},
    {"id": 147, "name": "Vaishnava Tantra",                         "short_title": "VATN"},
    {"id": 148, "name": "Vaishvanara Samhita",                      "short_title": "VSHS"},
    {"id": 149, "name": "Vamana Kalpa",                             "short_title": "VAMK"},
    {"id": 150, "name": "Varaha Purana",                            "short_title": "VARP"},
    {"id": 151, "name": "Vedanta Sutra",                            "short_title": "VEDS"},
    {"id": 152, "name": "Vidagdha Madhava",                         "short_title": "VDMAD"},
    {"id": 153, "name": "Vishnu Purana",                            "short_title": "VISP"},
    {"id": 154, "name": "Vishnu Yamala",                            "short_title": "VISY"},
    {"id": 155, "name": "Vraja Vilasa Stava",                       "short_title": "VVST"},
    {"id": 156, "name": "Vrinda Devi Astakam",                      "short_title": "VDAST"},
    {"id": 157, "name": "Vrindavana Mahimamrita",                   "short_title": "VRMA"},
    {"id": 158, "name": "Yamuna Stotram",                           "short_title": "YSTOT"},
    {"id": 159, "name": "Yamunastakam",                             "short_title": "YAST"},
]

# ── Helpers ────────────────────────────────────────────────────────────────────

def norm(text: str) -> str:
    """Strip diacritics, lowercase, collapse whitespace."""
    nfd = unicodedata.normalize('NFD', text or '')
    s   = ''.join(c for c in nfd if unicodedata.category(c) != 'Mn' and ord(c) < 128)
    s   = re.sub(r'[^\w\s]', ' ', s)   # punctuation → space
    return re.sub(r'\s+', ' ', s).strip().lower()


def sql_str(s: str) -> str:
    return "'" + s.replace("'", "''") + "'"

# ── Match rules (ordered: most specific first) ─────────────────────────────────
# Each rule: (pattern_on_norm_ref, scripture_id)
# Pattern matched against norm(scripture_ref).
RULES = [
    # ── CC sub-lilas (must come before generic CC) ──────────────────────────
    (r'^cc\s*(adi|ad[il])',          12),   # CC Adi Lila
    (r'^cc\s*(mad|madhya)',          13),   # CC Madhya Lila
    (r'^mad\s+\d',                   13),   # bare "Mad 19.xxx" → CC Madhya
    (r'^cc\s*antya',                 14),   # CC Antya Lila
    (r'^cc[\s,]',                    11),   # generic CC (space/comma after)
    (r'^cc$',                        11),   # bare "CC"
    (r'^cc\b',                       11),   # CC + word boundary

    # ── SB / Srimad Bhagavatam ──────────────────────────────────────────────
    (r'^sb[\s\d]',                   27),
    (r'^sb$',                        27),
    (r'^sb\b',                       27),
    (r'^srimad\s*bh[aā]gavatam',     27),
    (r'^srimad bh',                  27),

    # ── SBG (before BG so 'sbg' doesn't match 'bg') ─────────────────────────
    (r'^sbg\b',                       3),

    # ── BG / Bhagavad Gita ─────────────────────────────────────────────────
    (r'^bg\b',                        2),
    (r'^bhagavad\s*gita',             2),

    # ── BRSB (before BRS) ───────────────────────────────────────────────────
    (r'^brsb\b',                      7),
    (r'^brsb\s',                      7),

    # ── BRS / Bhakti Rasamrita Sindhu ───────────────────────────────────────
    (r'^brs\b',                       6),
    (r'^brs\d',                       6),   # em-dash stripped → "brs1.2.6" → "brs1 2 6"
    (r'^bhakti\s*rasam',              6),
    (r'^bhakti ras[aā]m',             6),
    (r'^sri\s*bhakti\s*ras',          6),
    (r'^bhakti\s*rasa\s*m[rṛ]ta',    6),

    # ── HBV / Hari Bhakti Vilasa ────────────────────────────────────────────
    (r'^hbv\b',                      17),
    (r'^hari[\s-]bhakti[\s-]vil',    17),
    (r'^hari bhakti vil',            17),
    (r'^sri\s*hari[\s-]bhakti',      17),
    (r'^arcana\s*(paddhati|pad|dip)', 17),  # Arcana Paddhati (HBV)

    # ── BB / Brihad Bhagavatamrita ──────────────────────────────────────────
    (r'^bb\b',                        1),
    (r'^br[ih]+ad[\s-]bh[aā]g',      1),
    (r'^brhad\s*bh',                  1),
    (r'^brhad\s*bag',                 1),
    (r'^brhad bhag',                  1),
    (r'^sri\s*br[ih]+ad[\s-]bh',      1),

    # ── BS / Brahma Samhita ─────────────────────────────────────────────────
    (r'^bs\b',                        8),
    (r'^brahma[\s-]sam[hṁ]it',        8),
    (r'^brahma samhita',              8),
    (r'^brahma sa[mn]',               8),
    (r'^sri\s*brahm',                 8),

    # ── BTV / Bhakti Tattva Viveka ──────────────────────────────────────────
    (r'^btv\b',                       9),
    (r'^bvt\b',                       9),   # typo variant in CSV
    (r'^bhakti[\s-]tattva[\s-]vivek', 9),

    # ── CB / Caitanya Bhagavata ─────────────────────────────────────────────
    (r'^cb\b',                       10),
    (r'^caitanya[\s-]bh[aā]g',       10),
    (r'^sri\s*caitanya\s*bh[aā]g',   10),
    (r'^caitanya bh',                10),

    # ── BR / Bhajana Rahasya ────────────────────────────────────────────────
    (r'^br\b',                        5),
    (r'^bhajan[a]?\s*rahasya',        5),
    (r'^bhajana rahasya',             5),

    # ── GG / Gita Govinda ───────────────────────────────────────────────────
    (r'^gg\b',                       15),
    (r'^g[iī]ta[\s-]govinda',        15),
    (r'^gita govinda',               15),
    (r'^sri\s*g[iī]ta[\s-]gov',      15),

    # ── GKH / Gaudiya Kanthahara ─────────────────────────────────────────────
    (r'^gkh\b',                      16),
    (r'^gkh\s',                      16),
    (r'^gkh\s*\(p\)',                16),
    (r'^gaudiya\s*kant',             16),

    # ── RRSN / Radha Rasa Sudha Nidhi ───────────────────────────────────────
    (r'^rrsn\b',                     26),
    (r'^r[aā]dh[aā][\s-]rasa[\s-]sudh', 26),
    (r'^sri\s*r[aā]dh[aā][\s-]rasa', 26),
    (r'^radha rasa sudha',           26),

    # ── SGGG / Sri Gaudiya Giti Guccha ──────────────────────────────────────
    (r'^sggg\b',                     28),
    (r'^sgg\b',                      28),
    (r'^sgg\s',                      28),

    # ── Siksastaka ──────────────────────────────────────────────────────────
    (r'^ss\b',                       29),
    (r'^sik[sś][aā][sṣ][tṭ]aka',    29),
    (r'^siksastaka',                 29),
    (r'^sri\s*sik',                  29),

    # ── VK / Vilapa Kusumanjalih ─────────────────────────────────────────────
    (r'^vk\b',                       31),
    (r'^vil[aā]pa[\s-]kusum',        31),
    (r'^vilapa kusum',               31),
    (r'^sri\s*vil[aā]pa',            31),
    (r'^vil[aā]pa\s*kusum',          31),

    # ── VG / Venu Gita ──────────────────────────────────────────────────────
    (r'^vg\b',                       30),
    (r'^ve[nṇ]u[\s-]g[iī]ta',       30),
    (r'^venu gita',                  30),

    # ── Upadesamrita ────────────────────────────────────────────────────────
    (r'^upad',                       32),
    (r'^upade[sś][aā]m[rṛ]ta',      32),
    (r'^sri\s*upade',                32),

    # ── Manah Siksa ─────────────────────────────────────────────────────────
    (r'^ms\b',                       21),
    (r'^manah?\s*sik',               21),
    (r'^mana[h][\s-]sik',            21),
    (r'^manas sik',                  21),
    (r'^sri\s*mana[h]',              21),
    (r'^mana[h]?-sik',               21),

    # ── Prapanna Jivanamritam ────────────────────────────────────────────────
    (r'^pj\b',                       23),
    (r'^prapanna[\s-]j[iī]van',      23),
    (r'^sri\s*prapanna',             23),

    # ── VG also matches "Venu Gita" abbrev in parens, e.g. "(Venu Gita 7)" ─
    (r'venu\s*g[iī]ta',              30),

    # ── CC Adi bare refs: "Ādi 4.192", "Ādi 4.124" ─────────────────────────
    (r'^[aā]di\s+\d',                12),

    # ════════════════════════════════════════════════════════════════════════
    # ── Scriptures added in ids 33–159 ──────────────────────────────────────
    # ════════════════════════════════════════════════════════════════════════

    # ── Padma Purana (109) ──────────────────────────────────────────────────
    (r'^p[aā]dma\s*pur[aā][nṇ]',    109),
    (r'^padma\s*pur[aā]',            109),

    # ── Skanda Purana (131) ─────────────────────────────────────────────────
    (r'^sk[aā]n[dḍ]a\s*pur[aā][nṇ]', 131),
    (r'^skand[aā]\s*pur[aā]',        131),
    (r'^skan[dḍ]a\s*pur',            131),

    # ── Naradiya Purana (103) ───────────────────────────────────────────────
    (r'^n[aā]rad[iī]ya\s*pur[aā]',  103),
    (r'^brh[aā]n[-\s]*n[aā]rad[iī]ya', 48),  # Brihan Naradiya = 48

    # ── Brahmanda Purana (44) ───────────────────────────────────────────────
    (r'^brahm[aā][nṇ][dḍ]a\s*pur',  44),

    # ── Varaha Purana (150) ─────────────────────────────────────────────────
    (r'^var[aā]ha\s*pur[aā]',        150),

    # ── Vishnu Purana (153) ─────────────────────────────────────────────────
    (r'^vi[sṣ][nṇ]u\s*pur[aā][nṇ]', 153),
    (r'^vi[sṣ]nu\s*pur[aā]',         153),

    # ── Vishnu Yamala (154) ─────────────────────────────────────────────────
    (r'^vi[sṣ][nṇ]u[-\s]*y[aā]m',   154),

    # ── Bhavishya Purana (43) ───────────────────────────────────────────────
    (r'^bhavi[sṣ]ya[-\s]*pur[aā]',  43),

    # ── Brihad Vishnu Purana (47) ───────────────────────────────────────────
    (r'^br[iī]had[-\s]*vi[sṣ][nṇ]u\s*pur', 47),
    (r'^brhad\s*vi[sṣ][nṇ]u\s*pur',        47),

    # ── Adi Purana (33) ─────────────────────────────────────────────────────
    (r'^[aā]di[-\s]*pur[aā][nṇ]',   33),

    # ── Aditya Purana (34) ──────────────────────────────────────────────────
    (r'^[aā]ditya[-\s]*pur[aā][nṇ]', 34),
    (r'^aditya\s*pur',               34),

    # ── Garuda Purana (61) ──────────────────────────────────────────────────
    (r'^gar[uū][dḍ][aā]\s*pur[aā][nṇ]', 61),
    (r'^garuda\s*pur',               61),
    (r'^g[aā]ru[dḍ][aā]\s*pur',     61),

    # ── Dasa Mula Tattva (57) ───────────────────────────────────────────────
    (r'^da[sś][aā][-\s]*m[uū]la',   57),
    (r'^dasa[-\s]*mula',             57),
    (r'^[sś]r[iī][-\s]*da[sś][aā][-\s]*m[uū]la', 57),

    # ── Bhakti Sandarbha (41) ───────────────────────────────────────────────
    (r'^bhakti[-\s]*sandarbha',      41),
    (r'^bhakti\s*san\b',             41),
    (r'^bhakti[-\s]*san\.?\s*\d',    41),

    # ── Bhagavat Sandarbha (38) ─────────────────────────────────────────────
    (r'^bh[aā]gavat[-\s]*sandarbha', 38),
    (r'^bhag\s*sand',                38),
    (r'^bhag\s*\.\s*sand',           38),

    # ── Paramatma Sandarbha (112) ────────────────────────────────────────────
    (r'^param[aā]tma\s*sandarbha',   112),

    # ── Tattva Sandarbha (142) ──────────────────────────────────────────────
    (r'^tattva[-\s]*sandarbha',      142),

    # ── Krishna Sandarbha (88) ──────────────────────────────────────────────
    (r'^k[rṛ][sṣ][nṇ]a[-\s]*sandarbha', 88),
    (r'^kr[sṣ][nṇ]a\s*sandarbha',   88),

    # ── Caitanya Candramrita (50) ────────────────────────────────────────────
    (r'^caitanya[-\s]*candr[aā]m[rṛ]ta', 50),
    (r'^caitanya[-\s]*candram[rṛ]ta',    50),

    # ── Caitanya Candrodaya Nataka (51) ─────────────────────────────────────
    (r'^caitanya[-\s]*candrodaya[-\s]*n[aā][tṭ]aka', 51),

    # ── Caitanya Mangala (52) ───────────────────────────────────────────────
    (r'^caitanya\s*ma[nṅ]gala',      52),

    # ── Caitanya Manjusa (53) ───────────────────────────────────────────────
    (r'^caitanya[-\s]*manjusa',      53),

    # ── Cauragraganya Purusastakam (54) ─────────────────────────────────────
    (r'^caur[aā]graga[nṇ]ya',       54),

    # ── Chandogya Upanishad (55) ─────────────────────────────────────────────
    (r'^ch?[aā]n[dḍ]ogya\s*up',    55),
    (r'^candogya\s*up',              55),

    # ── Mundaka Upanishad (99) ──────────────────────────────────────────────
    (r'^mu[nṇ][dḍ]aka\s*up',       99),
    (r'^mu[nṇ][dḍ]aka\s*\(',       99),    # "Muṇḍaka (3.1.2)"

    # ── Svetasvatara Upanishad (140) ─────────────────────────────────────────
    (r'^[sś]vet[aā][sś]vatara\s*up', 140),
    (r'^[sś]vet[aā]svata[rṛ]a\s*up', 140),
    (r'^[sś]vet\.?\s*up',            140),

    # ── Katha Upanishad (83) ─────────────────────────────────────────────────
    (r'^ka[tṭ]h[aā]\s*up',         83),

    # ── Brihad Aranyaka Upanishad (45) ──────────────────────────────────────
    (r'^br[iī]had[-\s]*[aā]ra[nṇ]yaka\s*up', 45),
    (r'^brhad[-\s]*ara[nṇ]yaka\s*up',         45),

    # ── Kali Santarana Upanishad (82) ───────────────────────────────────────
    (r'^kali[-\s]*santa[rṛ]a[nṇ]a\s*up',  82),

    # ── Uttara Gopala Tapani Upanishad (146) ─────────────────────────────────
    (r'^uttara[-\s]*gop[aā]la[-\s]*t[aā]pan[iī]', 146),

    # ── Isopanishad (76) ─────────────────────────────────────────────────────
    (r'^[iī][sś]opani[sś]ad',       76),
    (r'^[iī][sś]opani[sś]',         76),
    (r'^[sś]r[iī]\s*[iī][sś]opani', 76),

    # ── Taittiriya Upanishad (141) ───────────────────────────────────────────
    (r'^taittir[iī]ya\s*up',        141),

    # ── Stava Mala (134) ─────────────────────────────────────────────────────
    (r'^stava[-\s]*m[aā]l[aā]',     134),
    (r'^stavamala',                  134),
    (r'^[sś]r[iī]\s*stava[-\s]*m[aā]l', 134),

    # ── Stavavali (135) ──────────────────────────────────────────────────────
    (r'^stav[aā]val[iī]',           135),
    (r'^[sś]r[iī]\s*stav[aā]val',  135),

    # ── Padyavali (110) ──────────────────────────────────────────────────────
    (r'^pady[aā]val[iī]',           110),

    # ── Krishna Karnamrita (86) ──────────────────────────────────────────────
    (r'^k[rṛ][sṣ][nṇ]a[-\s]*kar[nṇ][aā]m[rṛ]ta', 86),
    (r'^kr[sṣ]na[-\s]*karn[aā]m[rṛ]ta',           86),
    (r'^[sś]r[iī]\s*kr[sṣ][nṇ]a[-\s]*kar[nṇ][aā]m', 86),

    # ── Laghu Bhagavatamrita (92) ────────────────────────────────────────────
    (r'^laghu[-\s]*bh[aā]gavatam[rṛ]ta', 92),

    # ── Mahabharata (94) ─────────────────────────────────────────────────────
    (r'^mah[aā]bh[aā]rata',         94),

    # ── Prema Bhakti Chandrika (115) ─────────────────────────────────────────
    (r'^prema[-\s]*bhakti[-\s]*candrik[aā]', 115),
    (r'^[sś]r[iī]\s*prema[-\s]*bhakti',     115),

    # ── Prema Vivarta (116) ──────────────────────────────────────────────────
    (r'^prema[-\s]*vivarta',         116),

    # ── Amnaya Sutra (35) ────────────────────────────────────────────────────
    (r'^[aā]mn[aā]ya[-\s]*s[uū]tra', 35),

    # ── Ananta Samhita (36) ──────────────────────────────────────────────────
    (r'^ananta[-\s]*sa[mṁ]hit[aā]', 36),

    # ── Atma Nivedana (37) ───────────────────────────────────────────────────
    (r'^[aā]tma[-\s]*nivedana',      37),

    # ── Bhajahu Re Mana (39) ─────────────────────────────────────────────────
    (r'^bhajah[uū]\s*re\s*m[aā]na', 39),

    # ── Bhakti Ratnakara (40) ────────────────────────────────────────────────
    (r'^bhakti[-\s]*ratn[aā]kara',  40),

    # ── Bhavartha Dipika (42) ────────────────────────────────────────────────
    (r'^bhav[aā]rtha\s*dip[iī]k[aā]', 42),

    # ── Brihad Gautamiya Tantra (46) ─────────────────────────────────────────
    (r'^br[iī]had[-\s]*gautam[iī]ya[-\s]*tantra', 46),
    (r'^brhad[-\s]*gautam[iī]ya',   46),

    # ── Brihan Naradiya Purana (48) ──────────────────────────────────────────
    (r'^br[iī]han[-\s]*n[aā]rad[iī]ya', 48),
    (r'^brhan[-\s]*n[aā]rad[iī]ya',     48),

    # ── Brihat Bhakti Tattva Sara (49) ───────────────────────────────────────
    (r'^br[iī]?hat[-\s]*bhakti[-\s]*tattva',             49),

    # ── Dana Keli Cintamani (56) ──────────────────────────────────────────────
    (r'^d[aā]na[-\s]*keli[-\s]*cint[aā]ma[nṇ]i', 56),

    # ── Gadadharastakam (58) ─────────────────────────────────────────────────
    (r'^gad[aā]dhar[aā][sṣ][tṭ]akam', 58),

    # ── Gandharva Samprarthanastakam (59) ────────────────────────────────────
    (r'^g[aā]ndharvā[-\s]*samprar', 59),
    (r'^gandharva[-\s]*samprarthan', 59),

    # ── Garga Samhita (60) ───────────────────────────────────────────────────
    (r'^garga\s*sa[mṁ]hit[aā]',     60),

    # ── Gaura Ganoddesadipika (62) ───────────────────────────────────────────
    (r'^gaura[-\s]*ga[nṇ]oddesa',   62),

    # ── Gauranga Balite Habe (63) ────────────────────────────────────────────
    (r'^gaura[nṅ]ga\s*balite',      63),

    # ── Gautamiya Tantra (64) ────────────────────────────────────────────────
    (r'^gautam[iī]ya[-\s]*tantra',  64),

    # ── Gita Mala (65) ───────────────────────────────────────────────────────
    (r'^g[iī]ta[-\s]*m[aā]l[aā]',  65),

    # ── Gitavali (66) ────────────────────────────────────────────────────────
    (r'^g[iī]t[aā]val[iī]',         66),

    # ── Gopala Sahasra Nama (67) ──────────────────────────────────────────────
    (r'^gop[aā]la\s*sahasra\s*n[aā]ma', 67),

    # ── Gopinatha (68) ───────────────────────────────────────────────────────
    (r'^gop[iī]n[aā]tha$',          68),

    # ── Govardhana Vasa Prarthana (69) ───────────────────────────────────────
    (r'^govardhana[-\s]*v[aā]sa[-\s]*pr[aā]rthan', 69),

    # ── Guru Carana Padma (70) ───────────────────────────────────────────────
    (r'^guru[-\s]*cara[nṇ]a[-\s]*padma', 70),

    # ── Gurudeva Krpa Bindu Diya (71) ────────────────────────────────────────
    (r'^gurudeva.*kr[pṗ][aā][-\s]*bindu', 71),

    # ── Gurvastakam (72) ─────────────────────────────────────────────────────
    (r'^gurva[sṣ][tṭ]akam',         72),
    (r'^[sś]r[iī]\s*gurva[sṣ][tṭ]akam', 72),
    (r'^gurv[aā][sṣ][tṭ]akam',      72),

    # ── Hamsaduta (73) ───────────────────────────────────────────────────────
    (r'^ha[mṁ]sad[uū]ta',           73),

    # ── Hari Bhakti Sudhodaya (74) ───────────────────────────────────────────
    (r'^hari[-\s]*bhakti[-\s]*sudhodaya', 74),

    # ── Hitopadesa (75) ──────────────────────────────────────────────────────
    (r'^hitopade[sś]a',             75),

    # ── Jagannatha Vallabha Nataka (77) ──────────────────────────────────────
    (r'^jagann[aā]tha[-\s]*vallabha[-\s]*n[aā][tṭ]aka', 77),

    # ── Jaladakhyana Samhita (78) ─────────────────────────────────────────────
    (r'^jalad[aā]khy[aā]na\s*sa[mṁ]hit[aā]', 78),

    # ── Je Anila Prema Dhana (79) ─────────────────────────────────────────────
    (r'^je\s*[aā]nila\s*prema',     79),

    # ── Jiva Jago (80) ───────────────────────────────────────────────────────
    (r'^j[iī]va\s*j[aā]go',         80),

    # ── Kali Kukkura Kadana (81) ──────────────────────────────────────────────
    (r'^kali[-\s]*kukkura\s*kadana', 81),

    # ── Katyayana Samhita (84) ───────────────────────────────────────────────
    (r'^k[aā]ty[aā]yana[-\s]*sa[mṁ]hit[aā]', 84),

    # ── Krama Dipika (85) ────────────────────────────────────────────────────
    (r'^krama[-\s]*d[iī]pik[aā]',   85),
    (r'^krama\s*san',                85),   # "Krama San." abbreviated

    # ── Krishna Nama Dhare Kata Bala (87) ────────────────────────────────────
    (r'^kr[sṣ][nṇ]a[-\s]*n[aā]ma\s*dhare', 87),

    # ── Krishna Virahe (89) ──────────────────────────────────────────────────
    (r'^kr[sṣ][nṇ]a[-\s]*virahe',  89),

    # ── Krpa Kara Vaishnava Thakura (90) ─────────────────────────────────────
    (r'^kr[pṗ][aā]\s*kara\s*vai[sś][nṇ]ava', 90),

    # ── Kunja Bihari Astakam (91) ─────────────────────────────────────────────
    (r'^ku[nṅ]ja\s*bih[aā]r[iī]\s*a[sṣ][tṭ]akam', 91),

    # ── Lalita Madhava (93) ──────────────────────────────────────────────────
    (r'^lalit[aā][-\s]*m[aā]dhava', 93),

    # ── Mahajana Racita Gita (95) ─────────────────────────────────────────────
    (r'^mah[aā]jana[-\s]*racita[-\s]*g[iī]ta', 95),

    # ── Mukta Carita (96) ────────────────────────────────────────────────────
    (r'^mukt[aā][-\s]*carita',      96),

    # ── Mukunda Mala Stotra (97) ──────────────────────────────────────────────
    (r'^mukunda[-\s]*m[aā]l[aā]',   97),

    # ── Mukunda Muktavali (98) ────────────────────────────────────────────────
    (r'^mukunda[-\s]*mukt[aā]val[iī]', 98),

    # ── Nama Sankirtana (100) ─────────────────────────────────────────────────
    (r'^n[aā]ma[-\s]*sa[nṅ]k[iī]rtana', 100),

    # ── Namastaka (101) ──────────────────────────────────────────────────────
    (r'^n[aā]m[aā][sṣ][tṭ]aka',    101),

    # ── Narada Pancaratra (102) ───────────────────────────────────────────────
    (r'^n[aā]rada[-\s]*pa[nñ]c[aā]r[aā]tra', 102),
    (r'^n[aā]rada[-\s]*pa[nñ]ca\.',          102),

    # ── Navadvipa Dhama Mahatmya (104) ───────────────────────────────────────
    (r'^navadv[iī]pa[-\s]*dh[aā]ma[-\s]*mah[aā]tmya', 104),

    # ── Navadvipa Sataka (105) ────────────────────────────────────────────────
    (r'^navadv[iī]pa[-\s]*[sś]ataka', 105),
    (r'^prabodhānanda.*navadvipa\s*sataka', 105),
    (r'^prabodhananda.*navadvipa',    105),

    # ── Nikunja Rahasya Stava (106) ───────────────────────────────────────────
    (r'^niku[nñ]ja\s*rahasya\s*stava', 106),

    # ── Nityananda Nistha Prarthana (107) ─────────────────────────────────────
    (r'^nity[aā]nanda\s*ni[sṣ][tṭ]h[aā]', 107),

    # ── Nityanandastakam (108) ────────────────────────────────────────────────
    (r'^nity[aā]nand[aā][sṣ][tṭ]akam', 108),

    # ── Pancaratra Pradipa (111) ──────────────────────────────────────────────
    (r'^pa[nñ]car[aā]tra[-\s]*prad[iī]pa', 111),

    # ── Paurnamasi Devi Pranama (113) ─────────────────────────────────────────
    (r'^p[aā]urn[aā]m[aā]si\s*devi\s*pra[nṇ]ama', 113),

    # ── Prameya Ratnavali (114) ───────────────────────────────────────────────
    (r'^prameya[-\s]*ratn[aā]val[iī]', 114),

    # ── Radha Bhajana Mahima (117) ────────────────────────────────────────────
    (r'^r[aā]dh[aā][-\s]*bhajana\s*mahim[aā]', 117),

    # ── Radha Kripa Kataksha Stava Raja (118) ────────────────────────────────
    (r'^r[aā]dh[aā][-\s]*kr[pṗ][aā][-\s]*ka[tṭ][aā]k[sṣ]a[-\s]*stava', 118),

    # ── Radha Krishna Vijnapti (119) ──────────────────────────────────────────
    (r'^r[aā]dh[aā][-\s]*kr[sṣ][nṇ]a\s*vij[nñ]apti', 119),

    # ── Radha Prarthana (120) ─────────────────────────────────────────────────
    (r'^r[aā]dh[aā][-\s]*pr[aā]rthan[aā]', 120),

    # ── Radhika Carana Padma (121) ────────────────────────────────────────────
    (r'^r[aā]dhik[aā][-\s]*cara[nṇ]a[-\s]*padma', 121),

    # ── Radhikastakam (122) ───────────────────────────────────────────────────
    (r'^r[aā]dhik[aā][sṣ][tṭ]akam', 122),

    # ── Rig Veda (123) ───────────────────────────────────────────────────────
    (r'^r[gṛ]\s*veda',              123),

    # ── Rupa Manjari Pada (124) ───────────────────────────────────────────────
    (r'^r[uū]pa[-\s]*ma[nñ]jar[iī][-\s]*pada', 124),

    # ── Sad Anga Saranagati (125) ─────────────────────────────────────────────
    (r'^[sś]a[dḍ][-\s]*a[nṅ]ga[-\s]*[sś]ara[nṇ][aā]gati', 125),

    # ── Sammohana Tantra (126) ────────────────────────────────────────────────
    (r'^sammohana[-\s]*tantra',     126),

    # ── Sanat Kumara Samhita (127) ────────────────────────────────────────────
    (r'^sanat[-\s]*kum[aā]ra[-\s]*sa[mṁ]hit[aā]', 127),
    (r'^sanatkumara\s*sa[mṁ]hita',  127),

    # ── Sandilya Bhakti Sutra (128) ───────────────────────────────────────────
    (r'^[sś][aā][nṇ][dḍ]ilya[-\s]*bhakti[-\s]*s[uū]tra', 128),

    # ── Saranagati (129) ─────────────────────────────────────────────────────
    (r'^[sś]ara[nṇ]agati$',         129),

    # ── Sata Nama Stotra (130) ────────────────────────────────────────────────
    (r'^[sś]ata[-\s]*n[aā]ma[-\s]*stotra', 130),

    # ── Sri Radha Nistha (132) ────────────────────────────────────────────────
    (r'^[sś]r[iī][-\s]*r[aā]dh[aā][-\s]*ni[sṣ][tṭ]h[aā]', 132),

    # ── Stava Kalpadruma (133) ────────────────────────────────────────────────
    (r'^stava\s*kalpadruma',        133),

    # ── Stotra Ratna (136) ───────────────────────────────────────────────────
    (r'^stotra[-\s]*ratna',         136),

    # ── Sva Sankalpa Prakasa Stotra (137) ─────────────────────────────────────
    (r'^sva[-\s]*sa[nṅ]kalpa[-\s]*prak[aā][sś]a[-\s]*stotra', 137),

    # ── Svarupa Damodara's Diary (138) ────────────────────────────────────────
    (r'^svar[uū]pa\s*d[aā]modara.*diary', 138),

    # ── Svarupa Damodara's Kadaca (139) ───────────────────────────────────────
    (r'^svar[uū]pa\s*d[aā]modara.*ka[dḍ]ac[aā]', 139),

    # ── Thakura Vaishnava Pada (143) ──────────────────────────────────────────
    (r'^[tṭ]h[aā]kura\s*vai[sś][nṇ]ava[-\s]*pada', 143),

    # ── Tri Bhangi Pancakam (144) ─────────────────────────────────────────────
    (r'^tri[-\s]*bha[nṅ]g[iī][-\s]*pa[nñ]cakam', 144),

    # ── Ujjvala Nilamani (145) ────────────────────────────────────────────────
    (r'^ujjvala[-\s]*n[iī]lama[nṇ]i', 145),

    # ── Vaishnava Tantra (147) ────────────────────────────────────────────────
    (r'^vai[sś][nṇ]ava[-\s]*tantra', 147),

    # ── Vaishvanara Samhita (148) ─────────────────────────────────────────────
    (r'^vai[sś]v[aā]nara[-\s]*sa[mṁ]hit[aā]', 148),

    # ── Vamana Kalpa (149) ───────────────────────────────────────────────────
    (r'^v[aā]mana[-\s]*kalpa',      149),

    # ── Vedanta Sutra (151) ───────────────────────────────────────────────────
    (r'^ved[aā]nta[-\s]*s[uū]tra',  151),

    # ── Vidagdha Madhava (152) ────────────────────────────────────────────────
    (r'^vidagdha[-\s]*m[aā]dhava',          152),
    (r'^[sś]r[iī]\s*vidagdha[-\s]*m[aā]dhava', 152),
    (r'^[sś]r[iī]\s+vidagdha',              152),

    # ── Vraja Vilasa Stava (155) ──────────────────────────────────────────────
    (r'^vraja[-\s]*vil[aā]sa[-\s]*stava', 155),

    # ── Vrinda Devi Astakam (156) ─────────────────────────────────────────────
    (r'^vr[nṇ]d[aā]\s*devy?[-\s]*a[sṣ][tṭ]akam', 156),

    # ── Vrindavana Mahimamrita (157) ──────────────────────────────────────────
    (r'^vr[nṇ]d[aā]vana[-\s]*mahim[aā]m[rṛ]ta', 157),

    # ── Yamuna Stotram (158) ──────────────────────────────────────────────────
    (r'^y[aā]mun[aā][-\s]*stotra',  158),

    # ── Yamunastakam (159) ───────────────────────────────────────────────────
    (r'^yamun[aā][sṣ][tṭ]akam',    159),
    (r'^[sś]r[iī]\s*yamun[aā][sṣ][tṭ]akam', 159),

    # ════════════════════════════════════════════════════════════════════════
    # ── Śrī-prefixed variants (norm: "sri <rest>") ───────────────────────────
    # ════════════════════════════════════════════════════════════════════════

    # ── Śrī Caitanya Candrāmṛta → 50 ────────────────────────────────────────
    (r'^[sś]r[iī]\s*caitanya[-\s]*candr[aā]m', 50),

    # ── Śrī Nityānandāṣṭakam → 108 ──────────────────────────────────────────
    (r'^[sś]r[iī]\s*nity[aā]nand[aā][sṣ][tṭ]akam', 108),

    # ── Śrī Nityānanda Niṣṭhā → 107 ─────────────────────────────────────────
    (r'^[sś]r[iī]\s*nity[aā]nanda\s*ni[sṣ][tṭ]h[aā]', 107),

    # ── Śrī Gadādharāṣṭakam → 58 ────────────────────────────────────────────
    (r'^[sś]r[iī]\s*gad[aā]dhar[aā][sṣ][tṭ]akam', 58),

    # ── Śrī Vraja-vilāsa-stava → 155 ────────────────────────────────────────
    (r'^[sś]r[iī]\s*vraja[-\s]*vil[aā]sa[-\s]*stava', 155),

    # ── Śrī Sanatkumara Samhita → 127 ───────────────────────────────────────
    (r'^[sś]r[iī]\s*sanat[-\s]*kumara\s*sa[mṁ]hita', 127),

    # ── Śrī Gopāla Sahasra Nāma → 67 ────────────────────────────────────────
    (r'^[sś]r[iī]\s*gop[aā]la\s*sahasra\s*n[aā]ma', 67),

    # ── Śrī Kuñja Bihāri Aṣṭakam → 91 ──────────────────────────────────────
    (r'^[sś]r[iī]\s*ku[nñ]ja\s*bih[aā]r[iī]\s*a[sṣ][tṭ]akam', 91),

    # ── Śrī Nārada Pañcarātra / Nārada-pañcarātrika → 102 ───────────────────
    (r'^[sś]r[iī]\s*n[aā]rada\s*pa[nñ]car[aā]tra', 102),
    (r'^n[aā]rada[-\s]*pa[nñ]car[aā]trika',         102),
    (r'^n[aā]rada[-\s]*pa[nñ]ca\b',                 102),

    # ── Śrī Amnaya Sutra → 35 ────────────────────────────────────────────────
    (r'^[sś]r[iī]\s*[aā]mn[aā]ya\s*sutra',         35),

    # ── Śrī Rādhā-Prārthanā → 120 ───────────────────────────────────────────
    (r'^[sś]r[iī]\s*r[aā]dh[aā][-\s]*pr[aā]rthan', 120),

    # ── Śrī Rādhā-Kṛṣṇa Vijñapti → 119 ────────────────────────────────────
    (r'^[sś]r[iī]\s*r[aā]dh[aā][-\s]*kr[sṣ][nṇ]a\s*vij[nñ]apti', 119),

    # ── Śrī Navadvipa Dhāma Mahātmya → 104 ──────────────────────────────────
    (r'^[sś]r[iī]\s*navadv[iī]pa[-\s]*dh[aā]ma', 104),

    # ── Śrī Kṛṣṇa Sandarbha → 88 ────────────────────────────────────────────
    (r'^[sś]r[iī]\s*kr[sṣ][nṇ]a[-\s]*sandarbha', 88),
    (r'^sri\s*kr[sṣ][nṇ]a\s*sandarbha',           88),

    # ── Ādi X.Y bare CC Adi ref (already handled above, repeated for clarity)─
    # covered by  ^[aā]di\s+\d  rule above

    # ── Śrī Govardhana-vāsa-prārthanā → 69 ──────────────────────────────────
    (r'^[sś]r[iī]\s*govardhana[-\s]*v[aā]sa[-\s]*pr[aā]rthan', 69),

    # ── Śrī Rūpa-mañjarī-pada → 124 ─────────────────────────────────────────
    (r'^[sś]r[iī]\s*r[uū]pa[-\s]*ma[nñ]jar[iī][-\s]*pada', 124),

    # ── Śrī Kṛṣṇa-Virahe → 89 ──────────────────────────────────────────────
    (r'^[sś]r[iī]\s*kr[sṣ][nṇ]a[-\s]*virahe', 89),

    # ── Śrī Rādhikāṣṭakam → 122 ─────────────────────────────────────────────
    (r'^[sś]r[iī]\s*r[aā]dhik[aā][sṣ][tṭ]akam', 122),

    # ── Śrī Rādhā-Kṛpā-Kaṭākṣa-Stava-Rāja → 118 ────────────────────────────
    (r'^[sś]r[iī]\s*r[aā]dh[aā][-\s]*kr[pṗ][aā][-\s]*ka[tṭ][aā]k[sṣ]a[-\s]*stava', 118),

    # ── Śrī Rādhā-Bhajana Mahimā → 117 ──────────────────────────────────────
    (r'^[sś]r[iī]\s*r[aā]dh[aā][-\s]*bhajana\s*mahim[aā]', 117),

    # ── Śrī Vṛndāvana-mahimāmṛta → 157 ──────────────────────────────────────
    (r'^[sś]r[iī]\s*vr[nṇ]d[aā]vana[-\s]*mahim[aā]m[rṛ]ta', 157),

    # ── Śrī Dāna-Keli-Cintāmaṇiḥ → 56 ───────────────────────────────────────
    (r'^[sś]r[iī]\s*d[aā]na[-\s]*keli[-\s]*cint[aā]ma[nṇ]i', 56),

    # ── Śrī Nikuñja Rahasya Stava → 106 ─────────────────────────────────────
    (r'^[sś]r[iī]\s*niku[nñ]ja\s*rahasya\s*stava', 106),

    # ── Śrī Gāndharvā-samprārthanāṣṭakam → 59 ───────────────────────────────
    (r'^[sś]r[iī]\s*g[aā]ndharvā?[-\s]*samprar', 59),
    (r'^[sś]r[iī]\s*gandharva[-\s]*samprarthan', 59),

    # ── Śrī Caurāgragaṇya-Puruṣāṣṭakam → 54 ─────────────────────────────────
    (r'^[sś]r[iī]\s*caur[aā]graga[nṇ]ya', 54),

    # ── Śrī Navadvīpa-Śataka → 105 ───────────────────────────────────────────
    (r'^[sś]r[iī]\s*navadv[iī]pa[-\s]*[sś]ataka', 105),

    # ── Śrī Yamunāṣṭakam → 159 ───────────────────────────────────────────────
    (r'^[sś]r[iī]\s*yamun[aā][sṣ][tṭ]akam',       159),

    # ── Śrī paurnamasi devi pranama → 113 ────────────────────────────────────
    (r'^[sś]r[iī]\s*p[aā]urn[aā]m[aā]si\s*devi',  113),
    (r'^sri\s*paurnamasi\s*devi',                   113),

    # ── Śrī Vṛndā Devy-aṣṭakam → 156 ───────────────────────────────────────
    (r'^[sś]r[iī]\s*vr[nṇ]d[aā]\s*devy?[-\s]*a[sṣ][tṭ]akam', 156),

    # ── Bṛhat-bhakti-tattva-sāra → 49 ───────────────────────────────────────
    (r'^br[iī]hat[-\s]*bhakti[-\s]*tattva',        49),

    # ── Śrī Muktā-carita → 96 ────────────────────────────────────────────────
    (r'^[sś]r[iī]\s*mukt[aā][-\s]*carita',         96),

    # ── Śrī Guru-Caraṇa-Padma → 70 ──────────────────────────────────────────
    (r'^[sś]r[iī]\s*guru[-\s]*cara[nṇ]a[-\s]*padma', 70),

    # ── Śrī Svarūpa Dāmodara → diary/kadaca ─────────────────────────────────
    (r'^[sś]r[iī]\s*svar[uū]pa\s*d[aā]modara.*diary', 138),
    (r'^[sś]r[iī]\s*svar[uū]pa\s*d[aā]modara.*ka[dḍ]ac[aā]', 139),

    # ── Mundaka (3.1.2) parenthetical form → 99 ─────────────────────────────
    (r'^mu[nṇ][dḍ]aka\b',                          99),

    # ── Anuvṛtti commentary on Śrī Śikṣāṣṭaka → 29 ──────────────────────────
    (r'[sś]ik[sś][aā][sṣ][tṭ]aka',                29),   # anywhere in string

    # ── Garga Samhita with Śrī prefix → 60 ───────────────────────────────────
    (r'^[sś]r[iī]\s*garga\s*sa[mṁ]hit[aā]',       60),
    (r'^garga\s*sa[mṁ]',                            60),
]

# Compile patterns
COMPILED_RULES = [(re.compile(p, re.IGNORECASE), sid) for p, sid in RULES]


def match_scripture(ref: str):
    """Return scripture_id or None."""
    if not ref:
        return None
    n = norm(ref)
    for pat, sid in COMPILED_RULES:
        if pat.search(n):
            return sid
    return None


# ── Build scripture_id → name map ─────────────────────────────────────────────
ID_NAME = {s['id']: f"{s['name']} ({s['short_title']})" for s in SCRIPTURES}


# ── Main ───────────────────────────────────────────────────────────────────────

def main():
    # Read CSV: collect (sloka_id, scripture_ref) pairs
    rows = []  # (id, scripture_ref)
    with open(CSV_PATH, newline='', encoding='utf-8-sig') as f:
        for row in csv.DictReader(f):
            sloka_id = (row.get('id') or '').strip()
            ref      = (row.get('scripture_reference') or '').strip()
            if sloka_id.isdigit():
                rows.append((int(sloka_id), ref))

    # Match each row
    matched_by_scr: dict[int, list[int]] = defaultdict(list)  # scripture_id → [sloka_ids]
    unmatched_refs:  dict[str, list[int]] = defaultdict(list)  # ref → [sloka_ids]
    no_ref_ids:      list[int] = []

    for sloka_id, ref in rows:
        if not ref:
            no_ref_ids.append(sloka_id)
            continue
        sid = match_scripture(ref)
        if sid:
            matched_by_scr[sid].append(sloka_id)
        else:
            unmatched_refs[ref].append(sloka_id)

    # ── Report ────────────────────────────────────────────────────────────────
    total_rows    = len(rows)
    matched_count = sum(len(v) for v in matched_by_scr.values())
    unmatch_count = sum(len(v) for v in unmatched_refs.values())

    print(f'\nTotal slokas in CSV : {total_rows}')
    print(f'  No scripture_ref  : {len(no_ref_ids)}')
    print(f'  Matched           : {matched_count}')
    print(f'  Unmatched         : {unmatch_count}')
    print()

    print('=== MATCHED (by scripture) ===')
    for sid in sorted(matched_by_scr):
        print(f'  id={sid:2d}  {ID_NAME[sid]:<50}  {len(matched_by_scr[sid])} slokas')
    print()

    print('=== UNMATCHED scripture_refs ===')
    for ref in sorted(unmatched_refs, key=lambda r: -len(unmatched_refs[r])):
        ids = unmatched_refs[ref]
        print(f'  [{len(ids):3d} slokas]  {ref!r}')

    # ── Generate SQL ──────────────────────────────────────────────────────────
    lines = [
        '-- update_sloka_scripture_ids.sql — generated by scripts/map_sloka_scriptures.py',
        '-- Sets scripture_id on sloka rows where the scripture_ref matches a known scripture.',
        '-- Safe to re-run (UPDATE is idempotent). scripture_id is NULL for unmatched rows.',
        '',
        'SET NAMES utf8mb4;',
        '',
    ]

    for sid in sorted(matched_by_scr):
        ids   = matched_by_scr[sid]
        name  = ID_NAME[sid]
        chunk = ', '.join(str(i) for i in sorted(ids))
        lines.append(f'-- {name} — {len(ids)} slokas')
        lines.append(f'UPDATE sloka SET scripture_id = {sid} WHERE id IN ({chunk});')
        lines.append('')

    if unmatched_refs:
        lines.append('-- ── Unmatched (left as NULL) ─────────────────────────────────────────────────')
        for ref in sorted(unmatched_refs, key=lambda r: -len(unmatched_refs[r])):
            ids = unmatched_refs[ref]
            lines.append(f'-- [{len(ids):3d}]  {ref}')

    lines.append('')
    lines.append(f'-- Done: {matched_count} matched, {unmatch_count} unmatched, {len(no_ref_ids)} had no ref.')

    OUTPUT_SQL.write_text('\n'.join(lines), encoding='utf-8')
    print(f'\nSQL written to: {OUTPUT_SQL}')


if __name__ == '__main__':
    main()
