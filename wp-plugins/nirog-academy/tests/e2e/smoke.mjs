/*
 * End-to-end smoke test for the Academy, run against a staging site.
 *
 *   NBA_BASE=https://staging.example.com \
 *   NBA_USER=participant@example.com NBA_PASS=... \
 *   NBA_LESSON=breath-awareness-sample NBA_PROGRAMME=yoga-for-diabetes \
 *   NBA_MAIL_LOG=/path/to/mail.log  (optional: lets the test read the device code) \
 *   NBA_SHOTS=./shots \
 *   node tests/e2e/smoke.mjs
 *
 * The participant must be enrolled, with no registered device, and the
 * lesson must have a protected HLS video. The test checks:
 *   1. login, orientation and the dashboard
 *   2. encrypted video actually plays and progress is recorded
 *   3. a second browser is blocked (one-device rule)
 *   4. a copied video link does not work in another browser
 *   5. the email-code device change moves access to the new browser
 */
import fs from 'node:fs';
import { createRequire } from 'node:module';

// require() also finds a globally installed Playwright (set NODE_PATH="$(npm root -g)").
const require = createRequire(import.meta.url);
const { chromium, devices } = require('playwright');

const BASE = process.env.NBA_BASE || 'http://localhost:8080';
const USER = process.env.NBA_USER;
const PASS = process.env.NBA_PASS;
const LESSON = process.env.NBA_LESSON;
const PROGRAMME = process.env.NBA_PROGRAMME;
const MAIL_LOG = process.env.NBA_MAIL_LOG || '';
const SHOTS = process.env.NBA_SHOTS || '';
const results = [];

function check(name, ok, detail = '') {
  results.push({ name, ok, detail });
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${name}${detail ? ' — ' + detail : ''}`);
}
async function shot(page, name) {
  if (SHOTS) {
    fs.mkdirSync(SHOTS, { recursive: true });
    await page.screenshot({ path: `${SHOTS}/${name}.png`, fullPage: true });
  }
}
async function login(page) {
  await page.goto(`${BASE}/courses/login/`);
  await page.fill('#nba-log', USER);
  await page.fill('#nba-pwd', PASS);
  await page.click('form .nba-btn[type=submit]');
  await page.waitForURL((u) => !u.pathname.includes('/login'));
  await page.waitForLoadState('load');
}

const browser = await chromium.launch();
try {
  // ---------------------------------------------------------------- device A
  const a = await browser.newContext({ viewport: { width: 1280, height: 860 } });
  const pa = await a.newPage();
  await login(pa);
  check('login lands on the dashboard', /\/courses\/?$/.test(new URL(pa.url()).pathname), pa.url());
  await shot(pa, '01-dashboard-desktop');

  await pa.goto(`${BASE}/courses/programme/${PROGRAMME}/`);
  check('programme page shows weeks', (await pa.locator('.nba-week').count()) > 0);
  await shot(pa, '02-programme');

  await pa.goto(`${BASE}/courses/lesson/${LESSON}/`);
  if (pa.url().includes('/orientation/')) {
    await shot(pa, '03-orientation');
    await pa.check('input[name=accept]');
    await pa.click('form:has(input[value=ack]) button');
    await pa.waitForURL((u) => u.pathname.includes('/lesson/'));
  }
  check('lesson page opens after acknowledgement', pa.url().includes(`/lesson/${LESSON}`), pa.url());

  const video = pa.locator('video.nba-media');
  await video.waitFor();
  await pa.evaluate(() => { const v = document.querySelector('video.nba-media'); v.muted = true; return v.play(); }).catch(() => {});
  await pa.waitForTimeout(6000);
  const played = await pa.evaluate(() => document.querySelector('video.nba-media').currentTime);
  check('encrypted HLS video plays', played > 2, `currentTime=${played.toFixed(1)}s`);
  await shot(pa, '04-lesson-playing');

  // Copy the playlist URL for the link-sharing test.
  const playlistUrl = JSON.parse(await pa.locator('.nba-player').getAttribute('data-config')).src;
  const keyStatus = await pa.evaluate(async (u) => {
    const master = await (await fetch(u)).text();
    const child = master.split('\n').find((l) => l.startsWith('http'));
    const variant = await (await fetch(child)).text();
    const key = variant.match(/URI="([^"]+)"/)[1];
    return (await fetch(key)).status;
  }, playlistUrl);
  check('key is served to the enrolled participant', keyStatus === 200, `status=${keyStatus}`);

  await pa.evaluate(() => document.querySelector('video.nba-media').pause());
  await pa.waitForTimeout(1500);
  const progress = await pa.evaluate(async (lesson) => {
    const r = await fetch(window.NBA.rest + 'lessons/' + lesson + '/progress', {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': window.NBA.nonce },
      body: JSON.stringify({ position: 0, duration: 0, ranges: [] }),
    });
    return r.json();
  }, Number(await pa.locator('.nba-lesson-page').getAttribute('data-lesson')));
  check('watch progress is recorded', (progress.watched_pct || 0) > 0, `watched_pct=${progress.watched_pct}`);

  // ---------------------------------------------------------------- device B
  const b = await browser.newContext({ ...devices['Pixel 7'] });
  const pb = await b.newPage();
  await login(pb);
  check('second device is sent to the device screen', pb.url().includes('/courses/device'), pb.url());
  await shot(pb, '05-device-blocked-mobile');

  const shared = await pb.evaluate(async (u) => (await fetch(u)).status, playlistUrl);
  check('copied video link does not work on another device', shared === 403, `status=${shared}`);

  if (MAIL_LOG && fs.existsSync(MAIL_LOG)) {
    const before = fs.readFileSync(MAIL_LOG, 'utf8').length;
    await pb.click('form:has(input[value=device_code]) button');
    await pb.waitForSelector('#nba-code');
    const mail = fs.readFileSync(MAIL_LOG, 'utf8').slice(before);
    const code = (mail.match(/\b(\d{6})\b/) || [])[1];
    check('device code email is sent', !!code);
    if (code) {
      await pb.fill('#nba-code', code);
      await pb.click('form:has(input[value=device_confirm]) button');
      await pb.waitForURL((u) => !u.pathname.includes('/device'));
      await pb.waitForLoadState('load');
      check('device change moves access to the new device', /\/courses\/?$/.test(new URL(pb.url()).pathname), pb.url());
      await shot(pb, '06-dashboard-mobile');
      await pa.goto(`${BASE}/courses/`);
      check('old device is signed out', pa.url().includes('/courses/login'), pa.url());
    }
  }
} finally {
  await browser.close();
}

const failed = results.filter((r) => !r.ok).length;
console.log(`\n${results.length - failed}/${results.length} checks passed`);
process.exit(failed ? 1 : 0);
