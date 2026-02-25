# SolarIndexSpain — Installation & Setup Guide

> Panduan lengkap dari instalasi plugin sampai data historis pertama siap tayang.

---

## Daftar Isi

1. [Persyaratan Sistem](#1-persyaratan-sistem)
2. [Instalasi Plugin](#2-instalasi-plugin)
3. [Konfigurasi Awal WordPress](#3-konfigurasi-awal-wordpress)
4. [Verifikasi Database](#4-verifikasi-database)
5. [Memasukkan Data Awal](#5-memasukkan-data-awal)
   - [Opsi A — Fetch Otomatis (Direkomendasikan)](#opsi-a--fetch-otomatis-direkomendasikan)
   - [Opsi B — Backfill via CLI](#opsi-b--backfill-via-cli-untuk-data-historis)
   - [Opsi C — Input Manual](#opsi-c--input-manual-satu-per-satu)
6. [Membuat Halaman Data Hub](#6-membuat-halaman-data-hub)
7. [Setup Cron Job (Produksi)](#7-setup-cron-job-produksi)
8. [Verifikasi Akhir](#8-verifikasi-akhir)
9. [Troubleshooting](#9-troubleshooting)

---

## 1. Persyaratan Sistem

| Komponen | Minimum |
|----------|---------|
| PHP | 8.1+ |
| WordPress | 6.4+ |
| MySQL | 8.0+ atau MariaDB 10.6+ |
| Ekstensi PHP | `calendar`, `json`, `mysqli` |
| ACF Plugin | **Tidak wajib** (plugin berjalan tanpa ACF) |

Cek versi PHP di terminal:
```bash
php -v
```

---

## 2. Instalasi Plugin

### Via File Manager / FTP

1. Pastikan folder plugin sudah ada di path yang benar:
   ```
   wp-content/plugins/solar-index-spain/
   ```

2. Pastikan struktur folder lengkap:
   ```
   solar-index-spain/
   ├── solar-index-spain.php
   ├── includes/
   │   ├── class-database.php
   │   ├── class-post-types.php
   │   ├── class-acf-fields.php
   │   ├── class-derived-metrics.php
   │   ├── class-csv-exporter.php
   │   ├── class-admin-ui.php
   │   ├── class-cron.php
   │   ├── class-validator.php
   │   └── class-logger.php
   ├── fetchers/
   │   ├── class-redata-fetcher.php
   │   ├── class-redata-cap-fetcher.php
   │   └── class-ember-fetcher.php
   ├── templates/
   │   ├── generation-bulletin.php
   │   ├── capacity-bulletin.php
   │   ├── data-hub.php
   │   └── partials/
   │       └── bulletin-footer.php
   ├── assets/
   │   ├── js/
   │   │   ├── charts.js
   │   │   └── admin.js
   │   └── css/
   │       └── bulletin.css
   └── cron/
       └── run-fetch.php
   ```

3. Masuk ke **WordPress Admin → Plugins**.

4. Temukan **Solar Index Spain** di daftar → klik **Activate**.

   > Pada saat aktivasi, plugin otomatis membuat 3 tabel database:
   > - `wp_solar_generation_data`
   > - `wp_solar_capacity_data`
   > - `wp_solar_revision_log`

5. Setelah aktivasi, menu **Solar Index** akan muncul di sidebar kiri admin.

---

## 3. Konfigurasi Awal WordPress

### Flush Rewrite Rules

Setelah aktivasi, flush permalink agar slug custom post type terdaftar:

**Admin → Settings → Permalinks → klik Save Changes** (tanpa mengubah apapun).

Ini penting agar URL seperti `/solar-generation/januari-2025/` tidak menghasilkan 404.

### Permalink Structure

Pastikan permalink **bukan** Plain. Gunakan salah satu opsi berikut:
- Post name: `/%postname%/`
- Custom: `/%year%/%postname%/`

---

## 4. Verifikasi Database

Pastikan tabel berhasil dibuat. Bisa cek via phpMyAdmin atau WP-CLI:

```bash
wp db query "SHOW TABLES LIKE '%solar%';"
```

Output yang diharapkan:
```
wp_solar_capacity_data
wp_solar_generation_data
wp_solar_revision_log
```

Jika tabel tidak ada, re-aktivasi plugin:
```bash
wp plugin deactivate solar-index-spain
wp plugin activate solar-index-spain
```

---

## 5. Memasukkan Data Awal

Pilih salah satu opsi di bawah sesuai kebutuhan. **Opsi A atau B direkomendasikan** untuk efisiensi.

---

### Opsi A — Fetch Otomatis (Direkomendasikan)

Paling cepat untuk mengisi data bulan terakhir. Plugin mengambil langsung dari REData API.

1. Buka **Admin → Solar Index**.
2. Klik tombol **▶ Run Fetch Now**.
3. Tunggu 10–30 detik. Lihat log di kotak **Fetch Log**.
4. Jika berhasil, log akan menampilkan:
   ```
   [OK] Fetch completed for 2025-XX
   ```
5. Dua draft bulletin post otomatis terbuat (generation + capacity).

> **Kapan ini berhasil?** REE biasanya mempublikasikan data bulan lalu sekitar tanggal 5–8 setiap bulan. Jadi fetch paling awal bisa dilakukan sekitar tanggal 8–10 bulan berjalan.

---

### Opsi B — Backfill via CLI (Untuk Data Historis)

Gunakan ini untuk mengisi data dari **Januari 2021 sampai bulan terkini** secara otomatis.

Buka terminal di server, jalankan satu per satu atau dengan loop:

```bash
# Format: php run-fetch.php [year] [month]
php wp-content/plugins/solar-index-spain/cron/run-fetch.php 2021 1
php wp-content/plugins/solar-index-spain/cron/run-fetch.php 2021 2
php wp-content/plugins/solar-index-spain/cron/run-fetch.php 2021 3
# ... dst
```

Atau gunakan loop bash untuk mengisi semua bulan sekaligus:

```bash
PLUGIN_CRON="wp-content/plugins/solar-index-spain/cron/run-fetch.php"

for year in 2021 2022 2023 2024; do
  for month in $(seq 1 12); do
    echo "--- Fetching $year-$month ---"
    php "$PLUGIN_CRON" "$year" "$month"
    sleep 2   # jangan spam API REE
  done
done

# Untuk tahun berjalan (misal 2025, sampai bulan Januari):
for month in 1; do
  php "$PLUGIN_CRON" 2025 "$month"
  sleep 2
done
```

> **Catatan penting:** REData menyimpan data historis yang cukup panjang, tapi akurasi data lama perlu diverifikasi. Rekomendasi: mulai dari Jan 2022 ke atas karena kapasitas di bawah 20 GW (batas validasi) belum terpenuhi di periode awal.
>
> Jika validasi gagal karena nilai di luar range historis (misal kapasitas < 20 GW di 2021), gunakan input manual (Opsi C) untuk periode tersebut.

---

### Opsi C — Input Manual Satu per Satu

Gunakan ini jika fetch API gagal atau untuk periode tertentu yang perlu dikoreksi.

#### Langkah 1 — Ambil angka dari REData API

Buka URL berikut di browser (ganti tahun dan bulan sesuai kebutuhan):

**Generation (GWh):**
```
https://apidatos.ree.es/es/datos/generacion/estructura-generacion?start_date=2025-01-01T00:00&end_date=2025-01-31T23:59&time_trunc=month
```

Di response JSON, cari:
```json
{
  "id": "1458",
  "attributes": {
    "values": [{ "value": 3847210.5 }]
  }
}
```
Angka `value` dalam **MWh**. Bagi 1000 untuk dapat GWh:
```
3.847.210,5 MWh ÷ 1000 = 3.847,21 GWh
```

**Capacity (GW):**
```
https://apidatos.ree.es/es/datos/generacion/potencia-instalada?start_date=2025-01-01T00:00&end_date=2025-01-31T23:59&time_trunc=month
```

Cari `"id": "1486"`, ambil `value` dalam **MW**, bagi 1000:
```
32.145,7 MW ÷ 1000 = 32,146 GW
```

#### Langkah 2 — Input ke Admin Form

1. Buka **Admin → Solar Index**.
2. Di bagian **Manual Monthly Entry**, isi:

   | Field | Nilai contoh |
   |-------|-------------|
   | Year | `2025` |
   | Month | `1` |
   | Generation (GWh) | `3847.21` |
   | Capacity (GW) | `32.146` |
   | Data Source | `manual` |

3. Klik **Calculate & Save**.
4. Plugin akan menampilkan metrics yang dihitung:
   - MoM %, YoY %, Capacity Factor, Build Pace, dll.
5. Ulangi untuk setiap bulan yang ingin diisi, **mulai dari yang paling lama** (urutan kronologis) agar perhitungan MoM dan rolling 12m benar.

> **Urutan input penting!** Selalu input dari bulan terlama ke terbaru. Misalnya: Jan 2021 → Feb 2021 → Mar 2021 → ... → bulan terkini. Jika dibalik, MoM% dan rolling 12m akan salah saat disimpan (meski bisa di-recalculate nanti).

---

## 6. Membuat Halaman Data Hub

Data hub adalah halaman statis dengan URL permanen yang menampilkan seluruh data historis.

### Buat dua halaman WordPress:

**Halaman 1 — Generation Hub:**

1. **Admin → Pages → Add New**.
2. Title: `Solar Generation Spain`
3. Slug (URL): `solar-generation-spain`
   - Pastikan slug persis seperti ini agar template terdeteksi otomatis.
4. Content: biarkan kosong (template plugin yang akan mengisi konten).
5. Publish.
6. URL yang terbentuk: `https://yourdomain.com/data/solar-generation-spain/`
   - Jika URL-nya tidak ada `/data/` di depan, buat dulu halaman parent bernama `Data` dengan slug `data`, lalu set kedua halaman ini sebagai child-nya.

**Halaman 2 — Capacity Hub:**

1. **Admin → Pages → Add New**.
2. Title: `Solar Capacity Spain`
3. Slug: `solar-capacity-spain`
4. Content: kosong.
5. Publish.

> Template `data-hub.php` otomatis terdeteksi berdasarkan slug halaman. Tidak perlu mengatur page template secara manual.

---

## 7. Setup Cron Job (Produksi)

WP-Cron hanya berjalan saat ada visitor. Untuk produksi, gunakan server cron (crontab).

### Tambahkan ke crontab server:

```bash
crontab -e
```

Tambahkan baris berikut:
```bash
# SolarIndexSpain — fetch data bulan lalu setiap tanggal 10, jam 08:00 UTC
0 8 10 * * php /var/www/html/wp-content/plugins/solar-index-spain/cron/run-fetch.php >> /var/log/sis-fetch.log 2>&1
```

Sesuaikan path `/var/www/html/` dengan path WordPress di server kamu.

### Nonaktifkan WP-Cron (opsional tapi direkomendasikan):

Di `wp-config.php`, tambahkan:
```php
define('DISABLE_WP_CRON', true);
```

---

## 8. Verifikasi Akhir

Checklist sebelum dinyatakan siap:

```
[ ] Plugin aktif, menu "Solar Index" muncul di admin
[ ] Tabel database terbuat (cek via phpMyAdmin atau WP-CLI)
[ ] Permalink di-flush (Settings → Permalinks → Save)
[ ] Data minimal 1 bulan sudah masuk (cek di tabel "Recent Data" di admin)
[ ] CSV master ter-generate:
      wp-content/uploads/solar-index-spain/csv/solar-generation-spain-master.csv
      wp-content/uploads/solar-index-spain/csv/solar-capacity-spain-master.csv
[ ] Halaman data hub terbuat dan bisa diakses:
      /data/solar-generation-spain/
      /data/solar-capacity-spain/
[ ] Minimal 1 draft bulletin terbuat (Admin → Posts → Generation Bulletins)
[ ] Buka draft bulletin, preview — hero metric & tabel tampil dengan benar
[ ] Chart.js grafik muncul (perlu minimal 2 bulan data untuk trend)
[ ] Cron job terdaftar di crontab server (produksi)
```

### Cek via WP-CLI:
```bash
# Cek data di DB
wp db query "SELECT period_year, period_month, generation_gwh, mom_pct, yoy_pct FROM wp_solar_generation_data ORDER BY period_year DESC, period_month DESC LIMIT 5;"

# Cek file CSV
ls -lh wp-content/uploads/solar-index-spain/csv/

# Cek draft posts
wp post list --post_type=solar_gen_index --post_status=draft
wp post list --post_type=solar_cap_index --post_status=draft
```

---

## 9. Troubleshooting

### Plugin aktif tapi menu admin tidak muncul
- Cek error di `wp-content/debug.log` (aktifkan `WP_DEBUG` dan `WP_DEBUG_LOG` di `wp-config.php`).
- Pastikan semua file di `includes/` ada dan tidak corrupt.

### Tabel database tidak terbuat
- Deaktivasi lalu aktifkan kembali plugin.
- Pastikan user database MySQL memiliki privilege `CREATE TABLE`.

### "Run Fetch Now" gagal / timeout
- Cek apakah server bisa akses internet ke `apidatos.ree.es`.
- Coba buka URL REData di browser dari server: `curl "https://apidatos.ree.es/es/datos/generacion/estructura-generacion?start_date=2025-01-01T00:00&end_date=2025-01-31T23:59&time_trunc=month"`
- Jika REE sedang down, coba beberapa jam kemudian atau input manual.

### Validasi gagal: "Generation X GWh out of expected range"
- Nilai terlalu kecil (< 500 GWh) atau terlalu besar (> 8.000 GWh).
- Pastikan kamu sudah membagi nilai MWh dengan 1.000.
- Untuk data historis 2021 yang mungkin di bawah range, gunakan input manual dan ubah `data_source` ke `manual`.

### Validasi gagal: "Capacity dropped by more than 0.5 GW"
- REE kadang merevisi data kapasitas. Jika penurunan memang valid (revisi resmi), input via manual entry — form tidak melakukan validasi seketat cron.

### Chart tidak muncul di halaman bulletin
- Buka browser DevTools → Console. Jika ada error `sisChartData is not defined`, artinya `wp_localize_script` tidak terpanggil.
- Pastikan post type bulletin-nya benar (`solar_gen_index` atau `solar_cap_index`), bukan post biasa.

### URL bulletin 404
- Flush permalink: **Admin → Settings → Permalinks → Save Changes**.
- Pastikan post status = `publish` (bukan draft).

### CSV tidak bisa didownload
- Cek permission folder: `wp-content/uploads/solar-index-spain/csv/` harus `755`.
- Klik **↻ Regenerate Master CSVs** di admin panel.

---

## Referensi Cepat

| Yang Dibutuhkan | Sumber |
|----------------|--------|
| Data generation (GWh) | REData API, id `1458`, satuan MWh ÷ 1000 |
| Data capacity (GW) | REData API, id `1486`, satuan MW ÷ 1000 |
| Double-check kapasitas | CNMC → Estadísticas mensuales renovables |
| EU context (opsional) | Ember API |
| Log fetch terbaru | Admin → Solar Index → Fetch Log |
| Master CSV generation | `/uploads/solar-index-spain/csv/solar-generation-spain-master.csv` |
| Master CSV capacity | `/uploads/solar-index-spain/csv/solar-capacity-spain-master.csv` |

---

*Guide ini mengacu pada plugin versi 1.0.0. Update guide ini setiap ada perubahan workflow atau struktur plugin.*
