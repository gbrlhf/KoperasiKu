# ADR-001: Versi stack pengembangan

- **Status:** Usulan (menunggu persetujuan seluruh PIC)
- **Pemilik:** PIC B

## Usulan
| Komponen | Versi |
|---|---|
| PHP | 8.3 |
| Laravel | rilis stabil terbaru yang mendukung PHP 8.3 |
| PostgreSQL | 16 |
| Redis | 7 |
| Node.js | LTS |
| Timezone aplikasi | UTC (konversi ke Asia/Jakarta saat tampil / hitung jatuh tempo) |

## Alasan
Seluruh PIC memakai versi yang sama agar perilaku migration, constraint, locking, dan tes konsisten.

## Persetujuan
- [ ] A  - [ ] B  - [ ] C  - [ ] D  - [ ] E
