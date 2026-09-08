import { chromium } from 'playwright';
import fs from 'fs';
import path from 'path';

(async () => {
  const outputDir = path.resolve('docs/image');
  if (!fs.existsSync(outputDir)) {
    fs.mkdirSync(outputDir, { recursive: true });
  }

  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ viewport: { width: 1280, height: 800 } });
  const page = await context.newPage();

  console.log('Logging in to WP Admin at http://localhost:8080/wp-admin...');
  await page.goto('http://localhost:8080/wp-admin');
  if (await page.$('#user_login')) {
    await page.fill('#user_login', 'admin');
    await page.fill('#user_pass', 'admin123');
    await page.click('#wp-submit');
    await page.waitForNavigation();
  }

  const pages = [
    { url: 'http://localhost:8080/wp-admin/', name: '01_dashboard.png' },
    { url: 'http://localhost:8080/wp-admin/edit.php?post_type=post&pgds_surface=article', name: '02_baiviet_list.png' },
    { url: 'http://localhost:8080/wp-admin/post-new.php?pgds_surface=article', name: '03_baiviet_editor.png' },
    { url: 'http://localhost:8080/wp-admin/edit.php?post_type=post&pgds_surface=emagazine', name: '04_emagazine_list.png' },
    { url: 'http://localhost:8080/wp-admin/post-new.php?pgds_surface=emagazine', name: '05_emagazine_editor.png' },
    { url: 'http://localhost:8080/wp-admin/edit.php?post_type=post&pgds_surface=video', name: '06_video_list.png' },
    { url: 'http://localhost:8080/wp-admin/post-new.php?pgds_surface=video', name: '07_video_editor.png' },
    { url: 'http://localhost:8080/wp-admin/edit.php?post_type=post&pgds_surface=vietnam-buddhism', name: '08_vietnambuddhism_list.png' },
    { url: 'http://localhost:8080/wp-admin/edit.php?post_type=pgds_teaching', name: '09_loiphatday_list.png' },
    { url: 'http://localhost:8080/wp-admin/edit-comments.php', name: '10_comments_list.png' },
    { url: 'http://localhost:8080/wp-admin/upload.php', name: '11_media_library.png' },
  ];

  for (const item of pages) {
    console.log(`Capturing ${item.name} (${item.url})...`);
    await page.goto(item.url, { waitUntil: 'networkidle' });
    await page.screenshot({ path: path.join(outputDir, item.name), fullPage: false });
  }

  await browser.close();
  console.log('All admin screenshots saved to docs/image/!');
})();
