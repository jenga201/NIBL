-- Cascade-delete a bot's packs when the bot row is removed. Previously
-- deleteBot() left orphaned packs behind. Clean any existing orphans first
-- (a no-op on a clean table), then add the foreign key.

DELETE p
FROM updatepacklist_packs p
LEFT JOIN updatepacklist_bots b
  ON b.id = p.bot_id
WHERE b.id IS NULL;

ALTER TABLE updatepacklist_packs
  ADD CONSTRAINT updatepacklist_packs_bot_fk
  FOREIGN KEY (bot_id) REFERENCES updatepacklist_bots (id)
  ON DELETE CASCADE;