CREATE TEMPORARY TABLE nums (n INT);
INSERT INTO nums VALUES (1),(2),(3),(4),(5),(6);

INSERT INTO tps (id, village_id, tps_number, total_dpt, latitude, longitude)
SELECT
  CONCAT(v.id, LPAD(n.n, 3, '0')) AS id,
  v.id AS village_id,
  n.n AS tps_number,
  FLOOR(RAND()*200)+200 AS total_dpt,
  RAND()*1.5 - 6.3 AS latitude,
  RAND()*2.0 + 103.8 AS longitude
FROM villages v
JOIN nums n ON n.n <= (1 + (ABS(CRC32(v.id)) % 5));

SELECT COUNT(*) AS total_tps FROM tps;