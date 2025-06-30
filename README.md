Middleware Database
- Tambah fitur update (saat ini baru action insert)

Menu DB Configuration:
- Support: pgsql & mysql V
- Password harus diinput sebelum di save V
- Tambah fitur: Check connection DB sebelum save V

Menu Database Tables
- Tambah fitur Select query tables dari database, agar tidak input manual menghindari human error V

Phase II:
- Buat fungsi heartbeat untuk cek koneksi in case ada issue koneksi/downtime database, bisa di system berbeda V
- Optimasi queue karena ada 1 job bisa 5-8 detik V
- Pengolahan data