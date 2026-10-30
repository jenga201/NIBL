-- utf8mb3 cannot store 4-byte (supplementary-plane) characters, but some pack
-- names contain them. Widen name to the table's utf8mb4 charset so filenames
-- round-trip losslessly.
ALTER TABLE updatepacklist_packs
  MODIFY name TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;