import { test, expect } from '@playwright/test';
import mysql from 'mysql2/promise';

const SITE = 'https://dev.nibl.co.uk';
const BOT_ID = 1026; // Gattsu

function connectionConfig() {
  return {
    host: '127.0.0.1',
    port: Number(process.env.SQL_PORT || 3306),
    user: process.env.SQL_USER,
    password: process.env.SQL_PASSWORD,
    database: process.env.SQL_DATABASE,
    connectTimeout: 10_000,
  };
}

async function insertTestPack(name, number) {
  const conn = await mysql.createConnection(connectionConfig());
  try {
    await conn.query(
      'INSERT INTO updatepacklist_packs ' +
        '(bot_id, number, name, size, sizekbits, last_modified) VALUES (?, ?, ?, ?, 0, ?) ' +
        'ON DUPLICATE KEY UPDATE name = VALUES(name), last_modified = VALUES(last_modified)',
      [BOT_ID, number, name, '1MB', '2099-01-01 00:00:00'],
    );
    // Simulate what niblirc does: bump the bot watermark so the minute sync sees a change.
    await conn.query('UPDATE updatepacklist_bots SET last_processed = NOW() WHERE id = ?', [BOT_ID]);
  } finally {
    await conn.end();
  }
}

test('Latest releases updates live when the sync broadcasts PacksUpdated', async ({ page }) => {
  const packName = `[E2E] live-update ${Date.now()}`;
  const packNumber = 9_900_000 + Math.floor(Math.random() * 9_999);

  await page.goto(SITE);

  // The browser must connect to Reverb over the socket and subscribe to `packs`
  // (Livewire registers this from the #[On('echo:packs,.PacksUpdated')] listener).
  await page.waitForFunction(
    () => {
      const w = window;
      const e = w.Echo;
      if (!e || !e.connector || !e.connector.pusher) return false;
      const p = e.connector.pusher;
      return (
        p.connection &&
        p.connection.state === 'connected' &&
        p.channels &&
        p.channels.channels &&
        Boolean(p.channels.channels.packs)
      );
    },
    null,
    { timeout: 25_000 },
  );

  // Sanity: the new pack is not on the page yet (it is inserted below).
  await expect(page.getByText(packName)).toHaveCount(0);

  // Cause a real change. The scheduler's minute `meilisearch:sync` will detect
  // the bumped `last_processed`, re-index and broadcast `PacksUpdated`.
  await insertTestPack(packName, packNumber);

  // Without any reload, the Livewire component should re-render and show it.
  // Deliberately left in the DB so a human watching the page sees it appear.
  await expect(page.getByText(packName).first()).toBeVisible({ timeout: 90_000 });
});