# RUNBOOK — Phật giáo và Đời sống

Vận hành hệ thống WordPress trên Lightsail. Bổ sung cho `docs/PROPOSAL_01` và `docs/PROPOSAL_02`.

> `RTO đo thật`: điền sau khi test restore ở D4 (proposal §13).

## 1. Deploy theme

```bash
# CI (GitHub Actions push main): lint -> build -> rsync -> purge
# Thủ công (trong 4 ngày build):
cd wp-content/themes/pgds && npm ci && npm run build
rsync -az --delete wp-content/themes/pgds/ deploy@HOST:/var/www/pgds/wp-content/themes/pgds/
ssh deploy@HOST 'sudo systemctl reload php8.3-fpm && sudo find /var/cache/nginx/fcgi -type f -delete'
```

Thứ tự purge sau deploy (proposal §12.1): **origin trước, edge sau**.
1. rsync theme → 2. reload php-fpm (reset opcache) → 3. flush FastCGI → 4. purge Cloudflare (chỉ khi asset đổi).

## 2. Rollback

```bash
git revert <sha> && git push        # ~60s, CI tự deploy lại
# hoặc rsync lại bản trước; blast radius chỉ trong 1 thư mục theme.
```

## 3. Purge cache thủ công

```bash
sudo find /var/cache/nginx/fcgi -type f -delete      # flush toàn bộ HTML cache
# Kiểm tra: curl -sI https://site/ | grep X-Cache   (HIT ở request thứ 2)
```

Nội dung sửa trong admin đã tự purge (mu-plugin `pgds-cache-flush.php` hook `save_post`).

## 4. Restore từ snapshot (SPOF — RTO mục tiêu 30–60')

1. Lightsail console → Snapshots → tạo instance mới từ snapshot gần nhất.
2. Detach static IP khỏi instance cũ → attach vào instance mới.
3. `nginx -t && systemctl reload nginx`; verify `X-Cache`, trang chủ, 1 bài, 1 category.
4. **RTO thực đo: ______ phút** (điền khi diễn tập).

## 5. Nâng bundle khi traffic cao (proposal §8.2)

Lightsail **không** resize in-place:
snapshot → tạo instance bundle lớn hơn → đổi static IP → verify → xoá instance cũ.
Ngày lễ: nâng 2GB→4GB (~$0.40/ngày), xong hạ lại. Trước import 2.000 bài cũng có thể tạm nâng.

## 6. YouTube API quota

- `videos.list` = 1 unit/call, batch tối đa 50 ID/call. Quota mặc định 10.000 unit/ngày.
- Cron `pgds_fetch_yt_meta` 1 lần/ngày lấy `duration`+`title`. Fallback: dùng meta đã lưu, không ghi đè rỗng.
- Video private/removed → set meta `_pgds_video_unavailable=1` để ẩn facade + bỏ schema.

## 7. Chỉ số cần theo dõi (CloudWatch)

CPU burst < 20%, RAM > 85%, disk > 80%, 5xx > 1%, healthcheck fail. Snapshot 03:00 ICT + dump DB 6h/lần.

## 8. Exit plan (trước khi decommission)

```bash
wp export --dir=/backup/wxr        # export WXR toàn bộ nội dung
tar czf /backup/uploads.tgz wp-content/uploads
# Tải cả 2 về nơi an toàn TRƯỚC khi xoá hạ tầng.
```

- Ngày dự kiến decommission: ______  |  Người chịu trách nhiệm: ______

## 9. Người chịu trách nhiệm rollback

- Tên: ______  |  Liên hệ: ______  |  Trigger rollback: khi 5xx > 5% trong 5 phút hoặc trắng trang.
