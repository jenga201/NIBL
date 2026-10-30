-- Sorting a bot's pack page by size uses the numeric `sizekbits` column and,
-- without a supporting index, forces a filesort on every page load. The
-- composite (bot_id, sizekbits) key covers both the bot_id equality filter and
-- the ORDER BY. Pack-number sorting is already served by the (bot_id, number)
-- unique key, and `name` is TEXT (not indexable for ordering).

ALTER TABLE updatepacklist_packs
  ADD KEY updatepacklist_packs_bot_sizekbits (bot_id, sizekbits);