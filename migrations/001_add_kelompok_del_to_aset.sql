-- Migration: Add kelompok & del columns to aset table
-- Note: del uses SMALLINT (not TINYINT) so 4-digit deletion/sale years (e.g. 2025, 2026) fit safely.

ALTER TABLE aset
ADD kelompok ENUM(
    'Tanah',
    'Kelompok 1',
    'Kelompok 2',
    'Kelompok 3',
    'Kelompok 4',
    'Bangunan Permanen',
    'Bangunan Tidak Permanen'
) NOT NULL DEFAULT 'Tanah' AFTER keteranganlok,
ADD del SMALLINT NULL DEFAULT NULL AFTER kelompok;
