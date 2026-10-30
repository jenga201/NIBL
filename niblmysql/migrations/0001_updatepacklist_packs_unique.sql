-- The application treats (bot_id, number) as the identity of a pack, but the
-- canonical schema never enforced it. Add the constraint, deduplicating first
-- (a no-op on a clean table) and then dropping the now-redundant single-column
-- bot_id index, whose lookups are covered by the unique key's leftmost prefix.

DELETE t1
FROM updatepacklist_packs t1
JOIN updatepacklist_packs t2
  ON t1.bot_id = t2.bot_id
 AND t1.number = t2.number
 AND t1.id > t2.id;

ALTER TABLE updatepacklist_packs
  ADD UNIQUE KEY updatepacklist_packs_bot_number (bot_id, number);

ALTER TABLE updatepacklist_packs
  DROP INDEX botid;