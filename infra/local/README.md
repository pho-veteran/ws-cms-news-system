# Môi trường DEV local (Docker)

Chỉ để **verify theme chạy thật** trước khi deploy. Không phải môi trường prod
(prod dùng Nginx FastCGI cache — xem `infra/nginx/`).

## Yêu cầu
- Docker Desktop đang chạy.
- Đã build asset: `cd wp-content/themes/pgds && npm run build` (dist bị gitignore).

## Chạy

```powershell
cd infra/local
docker compose up -d                              # db + redis + wordpress (apache)

# php -l toàn bộ (bắt lỗi cú pháp)
docker compose run --rm wpcli /scripts/lint.sh

# cài WP + kích hoạt theme + seed + import sample data
docker compose run --rm wpcli /scripts/setup.sh
```

Mở http://localhost:8080 — trang chủ 11 block.
Admin: http://localhost:8080/wp-admin (admin / admin123).

## Dọn

```powershell
docker compose down          # giữ dữ liệu
docker compose down -v       # xoá cả volume (làm lại từ đầu)
```

## Lưu ý
- Theme + mu-plugins mount trực tiếp từ repo → sửa code thấy ngay (trừ khi cần build lại CSS/JS).
- Redis object cache cài qua plugin `redis-cache` (cần mạng lần đầu).
- Poster YouTube: dùng `wp pgds yt-sync` để tải thumbnail + duration về local.
