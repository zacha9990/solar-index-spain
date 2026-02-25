# SolarIndexSpain — Project Documentation

> Dokumentasi teknis lengkap plugin WordPress `solar-index-spain`.
> Versi: **1.0.0** | Platform: WordPress 6.4+ | PHP: 8.1+

---

## Daftar Isi

1. [Gambaran Proyek](#1-gambaran-proyek)
2. [Arsitektur Sistem](#2-arsitektur-sistem)
3. [Struktur File Plugin](#3-struktur-file-plugin)
4. [Database Schema](#4-database-schema)
5. [Referensi Kelas PHP](#5-referensi-kelas-php)
6. [API Eksternal](#6-api-eksternal)
7. [Derived Metrics — Formula](#7-derived-metrics--formula)
8. [Validasi & Aturan Bisnis](#8-validasi--aturan-bisnis)
9. [Custom Post Types](#9-custom-post-types)
10. [Sistem Template](#10-sistem-template)
11. [Grafik (Chart.js)](#11-grafik-chartjs)
12. [CSV Export System](#12-csv-export-system)
13. [Admin Interface](#13-admin-interface)
14. [Sistem Cron & Data Pipeline](#14-sistem-cron--data-pipeline)
15. [Security](#15-security)
16. [Autoloader](#16-autoloader)
17. [Changelog](#17-changelog)

---

## 1. Gambaran Proyek

SolarIndexSpain adalah WordPress plugin yang membangun **referensi data bulanan** untuk solar energy di Spanyol. Plugin ini menerbitkan dua jenis halaman bulletin terstruktur:

- **Solar Generation Monthly Index** — data produksi solar PV bulanan (GWh)
- **Installed Solar Capacity Monthly Index** — data kapasitas terpasang solar PV bulanan (GW)

### Tujuan Utama

| Tujuan | Implementasi |
|--------|-------------|
| Data pipeline otomatis | Cron job fetch dari REData API (REE) |
| Penyimpanan time-series | Custom DB tables (bukan postmeta) |
| Bulletin pages interaktif | Custom post types + Chart.js |
| Download data terbuka | CSV export (monthly slice + master series) |
| Admin entry manual | Form dengan kalkulasi derived metrics otomatis |

### Prinsip Desain

- **Data layer terpisah dari presentation layer.** Template tidak boleh hardcode angka apapun — semua nilai dibaca dari database.
- **Semua derived metrics dihitung dari series yang tersimpan**, bukan dari input admin.
- **Minimal plugin dependency** — tidak butuh ACF, tidak butuh page builder. Custom code saja.
- **Revision-safe** — setiap perubahan data tercatat di tabel log.

---

## 2. Arsitektur Sistem

```
┌──────────────────────────────────────────────────────────────────┐
│                        FRONTEND (WordPress)                       │
│                                                                   │
│  /solar-generation/{slug}/   →  Template: generation-bulletin    │
│  /solar-capacity/{slug}/     →  Template: capacity-bulletin      │
│  /data/solar-generation-spain/  →  Template: data-hub           │
│  /data/solar-capacity-spain/    →  Template: data-hub           │
└─────────────────────────────┬────────────────────────────────────┘
                              │ reads from
┌─────────────────────────────▼────────────────────────────────────┐
│                       DATA LAYER (MySQL)                          │
│                                                                   │
│  wp_solar_generation_data   (time-series, monthly)               │
│  wp_solar_capacity_data     (time-series, monthly)               │
│  wp_solar_revision_log      (audit trail)                        │
│  /uploads/solar-index-spain/csv/   (static CSV files)           │
└─────────────────────────────┬────────────────────────────────────┘
                              │ populated by
┌─────────────────────────────▼────────────────────────────────────┐
│                       DATA PIPELINE                               │
│                                                                   │
│  [Level 1]  Admin manual entry form (class-admin-ui.php)        │
│  [Level 2]  Cron → REData API → Validate → Upsert → CSV → Draft │
└──────────────────────────────────────────────────────────────────┘
```

### Alur Data Level 2 (Otomatis)

```
Cron trigger (tanggal 10, 08:00 UTC)
    │
    ▼
SIS_REData_Fetcher::fetch_monthly_generation()   ←── REData API
SIS_REData_Cap_Fetcher::fetch_monthly_capacity()  ←── REData API
    │
    ▼
SIS_Validator::validate_generation()
SIS_Validator::validate_capacity()
    │ (gagal → throw Exception → email admin, berhenti)
    ▼
SIS_Derived_Metrics::calculate()
    │
    ▼
SIS_Database::upsert_generation()
SIS_Database::upsert_capacity()
    │
    ▼
SIS_CSV_Exporter::regenerate_master()
SIS_CSV_Exporter::generate_monthly_slice()
    │
    ▼
wp_insert_post() → draft bulletin posts (generation + capacity)
    │
    ▼
wp_mail() → notifikasi admin "Draft siap di-review"
```

---

## 3. Struktur File Plugin

```
solar-index-spain/
│
├── solar-index-spain.php          # Entry point: header, autoloader, hooks
│
├── includes/
│   ├── class-database.php         # DB schema (install) + semua query helper
│   ├── class-post-types.php       # Register CPT: solar_gen_index, solar_cap_index
│   ├── class-acf-fields.php       # Register post meta (ACF-compatible)
│   ├── class-derived-metrics.php  # Kalkulasi MoM%, YoY%, CF%, rolling totals
│   ├── class-validator.php        # Range & sanity validation + SIS_Validation_Exception
│   ├── class-logger.php           # Log ke WP option + CLI stdout
│   ├── class-csv-exporter.php     # Generate master CSV + monthly slice CSV
│   ├── class-admin-ui.php         # Admin page + AJAX handlers
│   └── class-cron.php             # WP-Cron schedule + run_monthly_fetch()
│
├── fetchers/
│   ├── class-redata-fetcher.php     # REData: generasi solar (widget struktura-generacion)
│   ├── class-redata-cap-fetcher.php # REData: kapasitas terpasang (widget potencia-instalada)
│   └── class-ember-fetcher.php      # Ember API: EU context, rank Spanyol (opsional)
│
├── templates/
│   ├── generation-bulletin.php    # Template halaman bulletin generasi
│   ├── capacity-bulletin.php      # Template halaman bulletin kapasitas
│   ├── data-hub.php               # Template halaman data hub (URL permanen)
│   └── partials/
│       └── bulletin-footer.php    # Reusable: downloads, metodologi, sitasi, update log
│
├── assets/
│   ├── js/
│   │   ├── charts.js              # Chart.js v4: 3 chart per bulletin
│   │   └── admin.js               # jQuery: form submit, fetch trigger, log refresh
│   └── css/
│       └── bulletin.css           # Stylesheet bulletin & data hub (responsive)
│
├── cron/
│   └── run-fetch.php              # CLI entry point (php sapi check); support backfill
│
├── INSTALL.md                     # Panduan instalasi & setup awal
└── README.md                      # Dokumentasi proyek ini
```

---

## 4. Database Schema

Plugin membuat **3 tabel custom** saat aktivasi via `dbDelta()`.

### `wp_solar_generation_data`

| Kolom | Tipe | Keterangan |
|-------|------|-----------|
| `id` | BIGINT UNSIGNED AI | Primary key |
| `period_year` | SMALLINT | Tahun data (e.g. 2025) |
| `period_month` | TINYINT | Bulan data (1–12) |
| `generation_gwh` | DECIMAL(10,2) | Produksi solar PV (GWh) |
| `mom_pct` | DECIMAL(6,3) | Month-over-Month % — dihitung saat insert |
| `yoy_pct` | DECIMAL(6,3) | Year-over-Year % — dihitung saat insert |
| `rolling_12m_gwh` | DECIMAL(12,2) | Rolling 12-month total (GWh) |
| `capacity_factor_pct` | DECIMAL(5,2) | Implied Capacity Factor (%) |
| `momentum_score` | TINYINT | Skor 0–100 (composite YoY + MoM) |
| `eu_position` | TINYINT | Ranking EU-27 (dari Ember, opsional) |
| `eu_share_pct` | DECIMAL(5,2) | Share of EU-27 solar generation |
| `data_source` | VARCHAR(50) | `manual` / `redata` / `esios` |
| `is_revised` | TINYINT(1) | 1 jika pernah direvisi |
| `created_at` | DATETIME | Timestamp insert |
| `updated_at` | DATETIME | Auto-update on change |

**Unique key:** `(period_year, period_month)` — satu record per bulan.

---

### `wp_solar_capacity_data`

| Kolom | Tipe | Keterangan |
|-------|------|-----------|
| `id` | BIGINT UNSIGNED AI | Primary key |
| `period_year` | SMALLINT | Tahun data |
| `period_month` | TINYINT | Bulan data (1–12) |
| `capacity_gw` | DECIMAL(8,3) | Kapasitas terpasang akhir bulan (GW) |
| `monthly_addition_gw` | DECIMAL(7,3) | Penambahan kapasitas bulan ini (GW) |
| `rolling_12m_added_gw` | DECIMAL(8,3) | Total penambahan 12 bulan terakhir |
| `build_pace_gw_yr` | DECIMAL(7,3) | Laju instalasi tahunan (= rolling 12m) |
| `data_source` | VARCHAR(50) | `manual` / `redata` |
| `is_revised` | TINYINT(1) | 1 jika pernah direvisi |
| `created_at` | DATETIME | Timestamp insert |
| `updated_at` | DATETIME | Auto-update on change |

---

### `wp_solar_revision_log`

| Kolom | Tipe | Keterangan |
|-------|------|-----------|
| `id` | BIGINT UNSIGNED AI | Primary key |
| `table_name` | VARCHAR(50) | Nama tabel yang direvisi |
| `period_year` | SMALLINT | Tahun data yang direvisi |
| `period_month` | TINYINT | Bulan data yang direvisi |
| `field_name` | VARCHAR(50) | Nama kolom yang berubah |
| `old_value` | VARCHAR(100) | Nilai sebelum revisi |
| `new_value` | VARCHAR(100) | Nilai setelah revisi |
| `reason` | TEXT | Alasan revisi |
| `created_at` | DATETIME | Waktu revisi |

---

## 5. Referensi Kelas PHP

### `SIS_Database`

Helper statis untuk semua operasi database.

```php
// Baca satu record
SIS_Database::get_generation(int $year, int $month): ?object
SIS_Database::get_capacity(int $year, int $month): ?object

// Baca N bulan terakhir (untuk chart)
SIS_Database::get_generation_last_n_months(int $n = 24): array
SIS_Database::get_capacity_last_n_months(int $n = 24): array

// Baca semua (untuk master CSV & data hub)
SIS_Database::get_all_generation(): array
SIS_Database::get_all_capacity(): array

// Upsert (insert atau update jika sudah ada)
SIS_Database::upsert_generation(int $year, int $month, float $gwh, array $metrics, string $source): bool
SIS_Database::upsert_capacity(int $year, int $month, float $gw, array $metrics, string $source): bool

// Log revisi
SIS_Database::log_revision(string $table, int $year, int $month, string $field, $old, $new, string $reason): void

// Instalasi DB (dipanggil saat aktivasi)
SIS_Database::install(): void
```

---

### `SIS_Derived_Metrics`

Semua kalkulasi derived metrics. **Tidak ada hardcode** — semua baca dari DB.

```php
// Hitung semua metrics untuk satu bulan
SIS_Derived_Metrics::calculate(
    int $year,
    int $month,
    float $gen_gwh,
    float $cap_gw
): array
// Return:
// [
//   'gen' => [
//     'mom_pct', 'yoy_pct', 'rolling_12m_gwh',
//     'capacity_factor_pct', 'momentum_score'
//   ],
//   'cap' => [
//     'monthly_addition_gw', 'rolling_12m_added_gw', 'build_pace_gw_yr'
//   ]
// ]

// Helper bulan sebelumnya (public, dipakai SIS_Validator juga)
SIS_Derived_Metrics::prev_month(int $year, int $month): array  // [year, month]
```

---

### `SIS_Validator`

Validasi data sebelum disimpan. Melempar `SIS_Validation_Exception` jika gagal.

```php
$v = new SIS_Validator();
$v->validate_generation(['gwh' => 3847.21], 2025, 1);
$v->validate_capacity(['gw' => 32.146], 2025, 1);
```

**Konstanta validasi:**

| Konstanta | Nilai | Keterangan |
|-----------|-------|-----------|
| `SOLAR_GEN_MIN_GWH` | 500 | Minimum wajar (Januari, musim dingin) |
| `SOLAR_GEN_MAX_GWH` | 8000 | Maksimum wajar (Juli, puncak musim panas) |
| `SOLAR_CAP_MIN_GW` | 15.0 | Spanyol ~16 GW di awal 2022, naik ke 20+ GW akhir 2022 |
| `SOLAR_CAP_MAX_GW` | 100.0 | Buffer besar untuk proyeksi masa depan |
| `MAX_MOM_CHANGE_PCT` | 80 | Flag jika MoM > 80% (kemungkinan error data) |

---

### `SIS_CSV_Exporter`

Generate dan simpan file CSV ke `/wp-content/uploads/solar-index-spain/csv/`.

```php
// Regenerasi semua master CSV (generation + capacity)
SIS_CSV_Exporter::regenerate_master(): void

// Generate monthly slice untuk satu bulan
SIS_CSV_Exporter::generate_monthly_slice(int $year, int $month): void

// URL publik file CSV
SIS_CSV_Exporter::get_generation_master_url(): string
SIS_CSV_Exporter::get_capacity_master_url(): string
SIS_CSV_Exporter::get_generation_slice_url(int $year, int $month): string
SIS_CSV_Exporter::get_capacity_slice_url(int $year, int $month): string
```

**Naming convention file:**
```
solar-generation-spain-master.csv
solar-capacity-spain-master.csv
solar-generation-spain-2025-01.csv
solar-capacity-spain-2025-01.csv
```

---

### `SIS_Logger`

Menulis log ke WordPress option `sis_fetch_log` (maks. 200 baris) dan ke stdout jika CLI.

```php
$log = new SIS_Logger('context-name');
$log->info('Starting fetch...');
$log->success('Completed.');
$log->error('Something failed.');
```

---

### `SIS_ACF_Fields`

Wrapper post meta yang kompatibel dengan plugin ACF (jika aktif) atau fallback ke `get/update_post_meta`.

```php
// Baca meta
SIS_ACF_Fields::get(int $post_id, string $key): mixed

// Tulis meta
SIS_ACF_Fields::set(int $post_id, string $key, mixed $value): void
```

**Meta keys yang digunakan:**

| Key | Tipe | Keterangan |
|-----|------|-----------|
| `sis_period_year` | int | Tahun bulletin (e.g. 2025) |
| `sis_period_month` | int | Bulan bulletin (1–12) |
| `sis_data_through` | string (Y-m-d) | Tanggal akhir data |
| `sis_published_date` | string (Y-m-d) | Tanggal publish bulletin |
| `sis_update_log` | string | Log revisi publik (tampil di halaman) |
| `sis_revision_note` | string | Catatan admin (tidak tampil publik) |
| `sis_bulletin_type` | string | `generation` atau `capacity` |

---

### `SIS_REData_Fetcher`

Fetch data produksi solar dari REData API (endpoint `estructura-generacion`).

```php
$f = new SIS_REData_Fetcher();
$result = $f->fetch_monthly_generation(2025, 1);
// Return: ['gwh' => 3847.21, 'source' => 'redata']
```

- **Endpoint:** `https://apidatos.ree.es/es/datos/generacion/estructura-generacion`
- **Solar PV ID:** `1458`
- **Unit response:** MWh → dikonversi ke GWh (÷ 1000)
- **Retry:** otomatis 3x dengan backoff 5 detik

---

### `SIS_REData_Cap_Fetcher`

Fetch kapasitas terpasang dari REData API (endpoint `potencia-instalada`).

```php
$f = new SIS_REData_Cap_Fetcher();
$result = $f->fetch_monthly_capacity(2025, 1);
// Return: ['gw' => 32.146, 'source' => 'redata']
```

- **Endpoint:** `https://apidatos.ree.es/es/datos/generacion/potencia-instalada`
- **Solar PV ID:** `1486` ← **berbeda** dari ID di endpoint generasi
- **Unit response:** MW → dikonversi ke GW (÷ 1000)
- **Retry:** otomatis 3x dengan backoff 5 detik

> **Penting:** Filter berdasarkan `id` (bukan `type` string) untuk menghindari false-match dengan Solar térmica (id `1487`).

---

### `SIS_Ember_Fetcher`

Opsional. Fetch EU context untuk ranking dan share Spanyol di EU-27.

```php
$f = new SIS_Ember_Fetcher();
$result = $f->fetch_eu_solar_rank(2025, 1);
// Return: ['rank' => 3, 'share_pct' => 12.4, 'total_eu_twh' => 28.5]
```

- **Endpoint:** `https://api.ember-climate.org/v1/electricity-generation`
- Tidak wajib — field EU di DB nullable, template menampilkan `—` jika kosong

---

## 6. API Eksternal

### REData (Red Eléctrica de España)

| Properti | Nilai |
|----------|-------|
| Base URL | `https://apidatos.ree.es/es/datos/generacion/` |
| Autentikasi | **Tidak diperlukan** |
| Format | JSON (`application/json`) |
| Rate limit | Tidak terdokumentasi; gunakan delay 2 detik antar request saat backfill |

**Parameter umum:**

```
start_date = YYYY-MM-DDT00:00
end_date   = YYYY-MM-DDT23:59
time_trunc = month
```

**Tanpa parameter `geo_trunc`** → default nasional (peninsular + Baleares + Canarias).

**Struktur response:**
```json
{
  "data": { ... },
  "included": [
    {
      "type": "Solar fotovoltaica",
      "id": "1458",
      "attributes": {
        "values": [
          { "value": 3847210.5, "datetime": "2025-01-31T..." }
        ]
      }
    }
  ]
}
```

### ID Referensi REData

| Widget | Sumber | ID | Unit |
|--------|--------|----|------|
| `estructura-generacion` | Produksi solar PV | `1458` | MWh |
| `potencia-instalada` | Kapasitas solar PV | `1486` | MW |
| `potencia-instalada` | Solar térmica (jangan pakai) | `1487` | MW |

### Ember Climate API

| Properti | Nilai |
|----------|-------|
| Base URL | `https://api.ember-climate.org/v1` |
| Autentikasi | Tidak diperlukan (public endpoints) |
| Docs | https://api.ember-climate.org/docs |

---

## 7. Derived Metrics — Formula

Semua kalkulasi dieksekusi di `SIS_Derived_Metrics::calculate()`.

### Generation Metrics

**Month-over-Month %**
```
MoM % = (generation_bulan_ini - generation_bulan_lalu) / generation_bulan_lalu × 100
```

**Year-over-Year %**
```
YoY % = (generation_bulan_ini - generation_bulan_sama_tahun_lalu) / generation_bulan_sama_tahun_lalu × 100
```

**Rolling 12-Month Total**
```
rolling_12m_gwh = SUM(generation_gwh) untuk 11 bulan sebelumnya + bulan ini
```

**Implied Capacity Factor**
```
CF % = generation_gwh / (capacity_gw × hours_in_month) × 100

dimana:
  hours_in_month = cal_days_in_month(month, year) × 24
```

Nilai normal untuk solar PV Spanyol: **12% – 22%**. Di luar range ini perlu diperiksa.

**Momentum Score**
```
momentum_score = clamp(0, 100, round(50 + (yoy_pct × 0.5) + (mom_pct × 0.3)))
```
- Nilai 50 = sesuai tren historis
- Nilai > 50 = di atas tren
- Nilai < 50 = di bawah tren

### Capacity Metrics

**Monthly Addition**
```
monthly_addition_gw = capacity_gw_bulan_ini - capacity_gw_bulan_lalu
```

**Rolling 12-Month Additions**
```
rolling_12m_added_gw = SUM(monthly_addition_gw) untuk 11 bulan sebelumnya + bulan ini
```

**Build Pace (Annualised)**
```
build_pace_gw_yr = rolling_12m_added_gw
```
(Karena sudah merupakan jumlah 12 bulan, nilainya identik dengan laju GW/tahun.)

---

## 8. Validasi & Aturan Bisnis

### Range Check

| Metric | Min | Max | Aksi jika di luar range |
|--------|-----|-----|------------------------|
| Generation GWh | 500 | 8.000 | Throw `SIS_Validation_Exception` |
| Capacity GW | 15.0 | 100.0 | Throw `SIS_Validation_Exception` |
| MoM change % | — | 80% | Throw `SIS_Validation_Exception` |
| Penurunan kapasitas | — | 0.5 GW | Throw `SIS_Validation_Exception` |

### Behavior Saat Validasi Gagal

- **Cron (Level 2):** Fetch dihentikan, tidak ada data yang disimpan, email alert dikirim ke admin.
- **Manual Entry (Level 1):** AJAX mengembalikan error message, form tetap terbuka.

### Upsert Behavior

Jika record untuk `(period_year, period_month)` sudah ada:
- Data **diupdate** (bukan duplikat)
- `is_revised` di-set ke `1`
- `updated_at` diperbarui otomatis oleh MySQL

### Double Check Manual

Jika nilai dari REData tampak janggal, verifikasi ke:
```
CNMC → Energía → Electricidad → Estadísticas mensuales renovables
```
Kolom: "Solar fotovoltaica — Potencia instalada (MW)". Perbedaan < 0.5% masih normal (perbedaan cut-off tanggal pelaporan REE vs CNMC).

---

## 9. Custom Post Types

### `solar_gen_index` — Generation Bulletin

| Properti | Nilai |
|----------|-------|
| Slug URL | `/solar-generation/{post-slug}/` |
| Archive | Tidak aktif |
| Supports | `title`, `custom-fields` |
| Template | `templates/generation-bulletin.php` |
| Gutenberg | Dinonaktifkan (`show_in_rest: false`) |

### `solar_cap_index` — Capacity Bulletin

| Properti | Nilai |
|----------|-------|
| Slug URL | `/solar-capacity/{post-slug}/` |
| Archive | Tidak aktif |
| Supports | `title`, `custom-fields` |
| Template | `templates/capacity-bulletin.php` |
| Gutenberg | Dinonaktifkan (`show_in_rest: false`) |

### Lifecycle Post

```
[Data disimpan ke DB]
        ↓
[create_bulletin_drafts() dipanggil]
        ↓
[Post dibuat dengan status: draft]
        ↓
[Admin review → Edit post → Publish]
        ↓
[Post live, URL aktif]
```

Admin harus **mereview dan mempublish** draft secara manual. Plugin tidak auto-publish.

---

## 10. Sistem Template

### Template Routing

Di `solar-index-spain.php`, hook `single_template` meng-override template default WordPress:

```php
add_filter('single_template', function (string $template): string {
    if (is_singular('solar_gen_index')) {
        return SIS_PLUGIN_DIR . 'templates/generation-bulletin.php';
    }
    if (is_singular('solar_cap_index')) {
        return SIS_PLUGIN_DIR . 'templates/capacity-bulletin.php';
    }
    return $template;
});
```

Hook `page_template` mendeteksi halaman data hub berdasarkan slug:

```php
// Aktif jika page slug = 'solar-generation-spain' atau 'solar-capacity-spain'
add_filter('page_template', function (string $template): string { ... });
```

### Variabel Tersedia di Template

Di `generation-bulletin.php` dan `capacity-bulletin.php`:

| Variabel | Tipe | Isi |
|----------|------|-----|
| `$post_id` | int | ID post saat ini |
| `$year` | int | Tahun bulletin |
| `$month` | int | Bulan bulletin |
| `$data` | object\|null | Record dari `wp_solar_generation_data` |
| `$chart_rows` | array | 24 bulan terakhir (untuk chart) |
| `$prev_data` | object\|null | Data bulan sebelumnya |
| `$ly_data` | object\|null | Data tahun lalu, bulan sama |
| `$period_label` | string | e.g. "January 2025" |
| `$bulletin_type` | string | `'generation'` atau `'capacity'` |

### Struktur Halaman Bulletin

```
1. Breadcrumb nav
2. HERO SECTION
   ├── Eyebrow label
   ├── Period title (H1)
   ├── Hero metric (GWh atau GW)
   └── Change badges (MoM%, YoY% atau monthly add)
3. KEY SIGNALS (4 kotak)
4. CHARTS (3 canvas)
   ├── chart-trend-24m  — Line chart 24 bulan
   ├── chart-yoy        — Bar chart YoY / Monthly additions
   └── chart-ytd        — Line chart YTD cumulative
5. KEY TABLE
6. FOOTER PARTIAL (bulletin-footer.php)
   ├── Downloads (CSV slice + master)
   ├── Data hub link
   ├── Methodology
   ├── Suggested citation
   ├── Update log (jika ada)
   └── Meta (data through, published date)
```

### Partial: `bulletin-footer.php`

Reusable partial. Membutuhkan variabel berikut tersedia di scope pemanggil:

- `$year` (int)
- `$month` (int)
- `$bulletin_type` (string: `'generation'` atau `'capacity'`)

---

## 11. Grafik (Chart.js)

Menggunakan **Chart.js v4** via CDN. Tidak perlu build step.

### Data Injection

Data di-pass dari PHP ke JavaScript via **inline `<script>` tag** langsung di template, tepat setelah `get_header()`. Pendekatan ini lebih reliable dari `wp_localize_script()` karena tidak bergantung pada timing WordPress hook.

```php
get_header();
?>
<script>var sisChartData = <?php echo wp_json_encode($chart_payload); ?>;</script>
```

Struktur `$chart_payload`:

```php
[
    'type'         => 'generation',   // atau 'capacity'
    'labels'       => [...],          // array string bulan (oldest first)
    'values'       => [...],          // array float GWh/GW
    'currentYear'  => 2025,
    'currentMonth' => 1,
    'yoy' => [
        'thisYear' => [...],          // array 12 nilai (Jan-Des tahun ini)
        'lastYear' => [...],          // array 12 nilai (Jan-Des tahun lalu)
    ],
    'ytd' => [
        'thisYear' => [...],          // cumulative s.d. bulan ini
        'lastYear' => [...],
    ],
    // untuk capacity:
    'additions' => [...],             // monthly additions array
]
```

### Chart 1 — Trend 24 Bulan

- Tipe: Line chart
- Data: 24 bulan terakhir (oldest first)
- Fill: area di bawah garis
- Y-axis: GWh (generation) atau GW (capacity)

### Chart 2 — YoY / Monthly Additions

- **Generation:** Bar chart grouped, membandingkan tahun ini vs tahun lalu per bulan (Jan–Des)
- **Capacity:** Bar chart tunggal, monthly additions dengan warna berbeda untuk nilai negatif

### Chart 3 — YTD Cumulative

- Tipe: Line chart
- Data: kumulatif dari Januari sampai bulan terkini
- Dua garis: tahun ini vs tahun lalu
- Garis tahun lalu menggunakan `borderDash` (putus-putus)

---

## 12. CSV Export System

### Lokasi File

```
wp-content/uploads/solar-index-spain/csv/
├── solar-generation-spain-master.csv
├── solar-capacity-spain-master.csv
├── solar-generation-spain-2025-01.csv
├── solar-generation-spain-2025-02.csv
├── solar-capacity-spain-2025-01.csv
└── ...
```

Direktori dilindungi dari directory listing dengan `.htaccess`:
```apache
Options -Indexes
```

### Format Master CSV — Generation

```csv
year,month,period,generation_gwh,mom_pct,yoy_pct,rolling_12m_gwh,capacity_factor_pct,momentum_score,data_source
2025,1,2025-01,3847.21,12.3,8.7,52341,17.2,61,redata
2025,2,2025-02,...
```

### Format Master CSV — Capacity

```csv
year,month,period,capacity_gw,monthly_addition_gw,rolling_12m_added_gw,build_pace_gw_yr,data_source
2025,1,2025-01,32.146,0.412,5.23,5.2,redata
```

### Kapan CSV di-regenerate?

| Event | Action |
|-------|--------|
| Manual entry berhasil disimpan | Master + monthly slice |
| Cron fetch berhasil | Master + monthly slice |
| Klik "Regenerate Master CSVs" di admin | Master saja |

---

## 13. Admin Interface

Halaman admin dapat diakses di **WordPress Admin → Solar Index** (slug: `solar-index-spain`). Hanya user dengan capability `manage_options`.

### Panel-panel di Admin

#### 1. Manual Monthly Entry
Form input 5 field: year, month, generation_gwh, capacity_gw, data_source.
Submit via AJAX → response menampilkan computed metrics.

#### 2. Automatic Fetch
Tombol **▶ Run Fetch Now** → trigger `SIS_Cron::run_monthly_fetch()` via AJAX.
Log fetch diperbarui di real-time.

#### 3. Fetch Log
Textarea readonly. Menampilkan isi option `sis_fetch_log` (200 baris terakhir).
Tombol **↻ Refresh Log** untuk memperbarui tanpa reload.

#### 4. Recent Generation Data
Tabel 5 record terbaru: period, GWh, MoM%, YoY%, CF%, rolling 12m, source, revised.

#### 5. Recent Capacity Data
Tabel 5 record terbaru: period, GW, monthly add, rolling 12m, build pace, source.

#### 6. CSV Downloads
Link ke master CSV + tombol **↻ Regenerate Master CSVs**.

### AJAX Endpoints

| Action | Handler | Nonce |
|--------|---------|-------|
| `sis_manual_entry` | `SIS_Admin_UI::handle_manual_entry()` | `sis_manual_entry` |
| `sis_run_fetch` | `SIS_Admin_UI::handle_run_fetch()` | `sis_admin_nonce` |
| `sis_get_log` | `SIS_Admin_UI::handle_get_log()` | `sis_admin_nonce` |
| `sis_regen_csv` | `SIS_Cron::handle_regen_csv()` | `sis_admin_nonce` |

---

## 14. Sistem Cron & Data Pipeline

### WP-Cron (Development / Staging)

Plugin mendaftarkan schedule custom `sis_monthly` (interval 30 hari) dan hook `sis_monthly_fetch`:

```php
// Dijadwalkan: hari ke-10 bulan berikutnya, jam 08:00 UTC
wp_schedule_event($next, 'sis_monthly', 'sis_monthly_fetch');
```

REE biasanya mempublikasikan data bulan lalu pada tanggal 5–8, sehingga fetch tanggal 10 memberikan buffer cukup.

### Server Cron (Produksi — Direkomendasikan)

```bash
# crontab -e
0 8 10 * * php /var/www/html/wp-content/plugins/solar-index-spain/cron/run-fetch.php >> /var/log/sis-fetch.log 2>&1
```

Tambahkan di `wp-config.php` untuk menonaktifkan WP-Cron:
```php
define('DISABLE_WP_CRON', true);
```

### CLI Entry Point (`cron/run-fetch.php`)

- Validasi `php_sapi_name() === 'cli'` — mati langsung jika dipanggil via HTTP
- Support backfill: `php run-fetch.php [year] [month]`
- Exit codes: `0` = sukses, `2` = validation error, `3` = unexpected error
- Backfill path memanggil `SIS_Cron::create_bulletin_drafts()` (public) untuk membuat draft post setelah data tersimpan

```bash
# Standard: fetch bulan lalu
php cron/run-fetch.php

# Backfill spesifik
php cron/run-fetch.php 2024 6

# Loop backfill semua bulan (dari Laragon Terminal)
for year in 2022 2023 2024 2025; do
  for month in 1 2 3 4 5 6 7 8 9 10 11 12; do
    php wp-content/plugins/solar-index-spain/cron/run-fetch.php $year $month
    sleep 1
  done
done
```

### Email Notifikasi

| Event | Subject | Penerima |
|-------|---------|---------|
| Fetch sukses | `[SolarIndexSpain] Draft Ready: YYYY-MM` | `get_option('admin_email')` |
| Validasi gagal | `[SolarIndexSpain] ⚠️ Fetch Failed: YYYY-MM` | `get_option('admin_email')` |
| Error tak terduga | `[SolarIndexSpain] ⚠️ Fetch Error: YYYY-MM` | `get_option('admin_email')` |

---

## 15. Security

### Checklist Implementasi

| Item | Status | Catatan |
|------|--------|---------|
| AJAX nonce verification | ✓ | `check_ajax_referer()` di setiap handler |
| Capability check | ✓ | `current_user_can('manage_options')` di setiap handler |
| Input sanitization | ✓ | `absint()`, `floatval()`, `sanitize_key()` |
| Output escaping | ✓ | `esc_html()`, `esc_url()`, `number_format()` di semua template |
| Prepared statements | ✓ | `$wpdb->prepare()` di semua query |
| Directory listing | ✓ | `.htaccess` dengan `Options -Indexes` di `/csv/` |
| CLI-only cron | ✓ | `php_sapi_name() !== 'cli'` check di `run-fetch.php` |
| No credentials exposed | ✓ | REData tidak membutuhkan token; tidak ada secret di frontend |

### SQL Injection Prevention

Semua query menggunakan `$wpdb->prepare()`:
```php
$wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}solar_generation_data
     WHERE period_year = %d AND period_month = %d",
    $year, $month
));
```

### XSS Prevention

Semua output di template menggunakan escaping:
```php
echo esc_html($data->generation_gwh);
echo esc_url(SIS_CSV_Exporter::get_generation_master_url());
echo number_format((float)$data->generation_gwh, 1);  // safe, no HTML
```

---

## 16. Autoloader

Plugin menggunakan `spl_autoload_register` tanpa Composer. Mapping class → file:

```
SIS_{ClassName} → strtolower(str_replace(['SIS_', '_'], ['', '-'], $class))
```

**Contoh mapping:**

| Class | File |
|-------|------|
| `SIS_Database` | `includes/class-database.php` |
| `SIS_Post_Types` | `includes/class-post-types.php` |
| `SIS_ACF_Fields` | `includes/class-acf-fields.php` |
| `SIS_Derived_Metrics` | `includes/class-derived-metrics.php` |
| `SIS_CSV_Exporter` | `includes/class-csv-exporter.php` |
| `SIS_Admin_UI` | `includes/class-admin-ui.php` |
| `SIS_Cron` | `includes/class-cron.php` |
| `SIS_Validator` | `includes/class-validator.php` |
| `SIS_Logger` | `includes/class-logger.php` |
| `SIS_REData_Fetcher` | `fetchers/class-redata-fetcher.php` |
| `SIS_REData_Cap_Fetcher` | `fetchers/class-redata-cap-fetcher.php` |
| `SIS_Ember_Fetcher` | `fetchers/class-ember-fetcher.php` |

`SIS_Validation_Exception` dimuat bersama `SIS_Validator` (satu file, tidak perlu file terpisah).

**Urutan lookup:** `includes/` dulu → jika tidak ada, cek `fetchers/`.

---

## 17. Changelog

### v1.0.0 — Initial Release (2026-02)

**Fitur baru:**
- Plugin entry point dengan autoloader dan activation hook
- Database schema: 3 tabel custom (`generation_data`, `capacity_data`, `revision_log`)
- Custom Post Types: `solar_gen_index`, `solar_cap_index`
- Post meta registration (ACF-compatible, fallback ke `get/update_post_meta`)
- `SIS_Derived_Metrics`: kalkulasi MoM%, YoY%, rolling 12m, capacity factor, momentum score, build pace
- `SIS_Validator`: range check dan sanity check dengan `SIS_Validation_Exception`
- `SIS_Logger`: log ke WP option + CLI stdout
- `SIS_CSV_Exporter`: master CSV + monthly slice, naming convention per spec
- `SIS_Admin_UI`: manual entry form, run-fetch button, fetch log viewer, CSV regen
- `SIS_Cron`: WP-Cron schedule + `run_monthly_fetch()` dengan email notifikasi
- `SIS_REData_Fetcher`: fetch generation dari `estructura-generacion`, 3x retry
- `SIS_REData_Cap_Fetcher`: fetch capacity dari `potencia-instalada` (id `1486`), 3x retry
- `SIS_Ember_Fetcher`: EU-27 rank & share dari Ember API (opsional)
- Template `generation-bulletin.php`: hero metric, 4 signal boxes, 3 charts, key table
- Template `capacity-bulletin.php`: hero metric, 4 signal boxes, 3 charts, key table
- Template `data-hub.php`: full historical table + methodology + bulletin index
- Partial `bulletin-footer.php`: downloads, methodology, citation, update log
- `assets/js/charts.js`: Chart.js v4 setup untuk 3 chart types
- `assets/js/admin.js`: jQuery AJAX handlers untuk semua admin actions
- `assets/css/bulletin.css`: responsive styles untuk bulletin & data hub
- `cron/run-fetch.php`: CLI entry point dengan backfill support

**Keputusan arsitektur:**
- CNMC di-drop sebagai sumber otomatis; seluruh pipeline kapasitas menggunakan REData `potencia-instalada` (dikonfirmasi return data nasional yang benar)
- ACF tidak dijadikan dependency wajib; plugin berjalan dengan `get/update_post_meta` biasa
- WP-Cron digunakan untuk development; rekomendasi server cron untuk produksi

---

### v1.0.1 — Post-Launch Fixes (2026-02)

**Bug fixes:**

- **Solar PV ID salah di `estructura-generacion`:** ID yang terdokumentasi di dev guide (`1739`) tidak valid di production. ID aktual yang dikonfirmasi dari live endpoint adalah `1458`. Fix di `fetchers/class-redata-fetcher.php`.

- **Chart tidak muncul di halaman bulletin:** `wp_localize_script()` yang dipanggil dari dalam template file tidak selalu reliable tergantung timing WordPress hook. Diganti dengan inline `<script>var sisChartData = ...;</script>` langsung di HTML setelah `get_header()`. Fix di `templates/generation-bulletin.php` dan `templates/capacity-bulletin.php`.

- **Validasi gagal untuk data historis 2022:** `SOLAR_CAP_MIN_GW` di-set `20.0` tapi kapasitas Spanyol di awal 2022 masih ~16 GW. Diturunkan ke `15.0`. Fix di `includes/class-validator.php`.

- **Backfill tidak membuat draft bulletin:** CLI backfill path di `cron/run-fetch.php` (dengan argumen year/month) tidak memanggil `create_bulletin_drafts()`. Fix: method dijadikan `public` di `SIS_Cron` dan dipanggil dari backfill path.

---

*Dokumentasi ini mengacu pada plugin versi 1.0.1.*
*Update dokumen ini setiap ada perubahan arsitektur, schema, atau behavior API.*
