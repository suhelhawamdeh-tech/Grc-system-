-- 017: composite KRIs: value calculated as a weighted sum of the latest readings of component KRIs.
ALTER TABLE kris ADD COLUMN is_composite TINYINT(1) NOT NULL DEFAULT 0 AFTER data_source;

CREATE TABLE IF NOT EXISTS kri_components (
  kri_id INT UNSIGNED NOT NULL,
  component_kri_id INT UNSIGNED NOT NULL,
  weight_milli INT UNSIGNED NOT NULL,
  PRIMARY KEY (kri_id, component_kri_id),
  CONSTRAINT fk_kc_kri FOREIGN KEY (kri_id) REFERENCES kris(id),
  CONSTRAINT fk_kc_comp FOREIGN KEY (component_kri_id) REFERENCES kris(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
