# Report — "Đọc nhiều" (Most Read) tùy chỉnh qua CMS

**Tính năng:** Cho phép biên tập viên chọn **bài nào** xuất hiện trong khối "Đọc nhiều" ở
sidebar và **thứ tự** hiển thị, ngay trong trang soạn bài — thay vì để hệ thống tự xếp theo
số bình luận.

**Trạng thái:** Đã triển khai xong phần code + test. Test suite `cms-editor-regression`
chạy PASS trên môi trường Docker local. **Chưa commit** (4 file đang ở trạng thái modified).

**Ngày:** 2026-09-12

---

## 1. Bối cảnh và yêu cầu

- Khối "Đọc nhiều" ở sidebar trước đây chỉ xếp tự động theo `comment_count DESC, date DESC`
  (bài nào nhiều bình luận lên đầu). Biên tập viên không thể can thiệp.
- Khách hàng muốn **tùy chỉnh qua CMS**: chọn được bài nào xuất hiện, và thứ tự hiển thị.

Giải pháp áp dụng đúng mẫu **curated slot** đã có sẵn của khối "Tin nổi bật"
(`_pgds_is_featured` + `_pgds_feature_rank`): một cờ bật/tắt + một rank nguyên (1–4) để xếp
thứ tự thủ công. Cách này không cần plugin mới, không phá hành vi cũ, và đồng bộ với cách
mà trang chủ vốn đã tuyển bài.

## 2. Thiết kế

Hai meta mới, cùng nhóm `homepage` (nhóm "Điều kiện và vị trí hiển thị trang chủ"):

| Meta key | Kiểu | Ý nghĩa |
|---|---|---|
| `_pgds_is_popular` | boolean | Bật = bài đủ điều kiện xuất hiện trong khối "Đọc nhiều" |
| `_pgds_popular_rank` | integer (1–4) | Thứ tự hiển thị (1 = đầu danh sách) |

**Quy tắc xếp thứ tự** của `pgds_query_popular()`:

1. Các bài **được chọn** (có `_pgds_is_popular = 1`) hiện trước, xếp theo `_pgds_popular_rank`
   tăng dần (1 → 4).
2. Số slot còn thiếu **tự điền** theo `comment_count DESC, date DESC` — giữ nguyên hành vi
   cũ cho site chưa tuyển bài (không làm vỡ trang đang chạy).
3. Nếu trang chủ đã "tiêu thụ" gần hết bài (dedup §4.4), phần top-up được phép lặp lại để
   khối luôn render đủ số item, thay vì hiện khối trống trông như lỗi.

**Validate cứng** (giống `pgds_validate_featured_rank`): khi bật "Đọc nhiều" mà rank trống
hoặc ngoài 1–4, hệ thống **từ chối cập nhật cặp giá trị** và giữ nguyên giá trị hợp lệ trước
đó; khi tắt "Đọc nhiều" thì rank trống được chấp nhận (lưu `0`).

## 3. Các file thay đổi

| File | Thay đổi | Mục đích |
|---|---|---|
| `wp-content/themes/pgds/inc/meta-fields.php` | +2 field, +đăng ký meta, +`pgds_validate_popular_rank()`, +lưu trong `pgds_save_meta()`, +REST validation, +feedback VN/EN; bỏ `collapsed => true` cho nhóm homepage | Định nghĩa field, validate, lưu (classic + REST), và **mở sẵn nhóm ngay khi vào bài** |
| `wp-content/themes/pgds/inc/query-blocks.php` | Viết lại `pgds_query_popular()` thành curated-first + fill + top-up | Thay đổi thứ tự truy vấn để tôn trọng lựa chọn của biên tập |
| `wp-content/themes/pgds/inc/admin-ux.php` | Cột danh sách thêm badge "🔥 Đọc nhiều (#rank)"; JS `updatePopularRankControl()`/`bindPopularRankControl()`; sửa bug scope `querySelector` khi có 2 ô số | Hiển thị trạng thái + điều khiển ẩn/hiện ô vị trí theo checkbox |
| `tools/tests/cms-editor-regression.php` | Thêm 20+ assertion cho cặp popular; đảo 2 assertion "collapsed" → "expanded" | Chứng minh hành vi bằng test |

### Chi tiết theo `file:line`

**`inc/meta-fields.php`**
- `pgds_meta_fields()` — định nghĩa 2 field `_pgds_is_popular` (checkbox) và
  `_pgds_popular_rank` (number 1–4), nhóm `homepage`.
- Nhãn tiếng Anh cho surface `vietnam-buddhism`: "Most read" / "Most read position".
- `pgds_register_meta()` — khai báo type `boolean`/`integer` cho REST.
- `pgds_validate_popular_rank( $popular, $rank )` — validate cặp cờ + rank, trả `WP_Error`
  mã `pgds_invalid_popular_rank` khi bật cờ mà rank không hợp lệ.
- `pgds_meta_feedback_messages()` — thêm thông báo lỗi VN/EN cho `pgds_invalid_popular_rank`.
- `pgds_save_meta()` — trong nhánh nhóm `homepage`, đọc `_pgds_is_popular` +
  `_pgds_popular_rank`, validate rồi `update_post_meta` cả hai key.
- `pgds_rest_validate_article_meta()` — validate cặp popular qua REST, trả HTTP 400 khi sai,
  chuẩn hóa giá trị trước khi ghi.
- `pgds_meta_groups()` — **bỏ** `'collapsed' => true` ở nhóm `homepage` (yêu cầu: hiện mở
  sẵn để dễ tìm "Đọc nhiều").

**`inc/query-blocks.php`**
- `pgds_query_popular( $count, $dedup )` — curated-first (meta `_pgds_popular_rank`, order
  `meta_value_num ASC`) → fill (`comment_count DESC, date DESC`) → top-up.

**`inc/admin-ux.php`**
- `pgds_admin_column_content()` — cột `pgds_flags` thêm `🔥 Đọc nhiều (#rank)`.
- JS: `updatePopularRankControl()`/`bindPopularRankControl()` (đối xứng với featured), và sửa
  `updateFeatureRankControl()` dùng `rank.closest('.pgds-metabox__field')` để không nhầm ô
  trạng thái giữa hai ô số (featured rank vs popular rank).

**`tools/tests/cms-editor-regression.php`**
- `$expected_groups['homepage']` thêm `_pgds_is_popular`, `_pgds_popular_rank`.
- `$valid_values` thêm `_pgds_is_popular => '1'`, `_pgds_popular_rank => '3'`.
- Assertion mới: cờ popular lưu; rank popular lưu trong khoảng; rank đúng 1/2/3/4 lưu; rank
  sai (chữ/số thập phân/-1/0/5) bị chặn giữ giá trị cũ; tắt "Đọc nhiều" chấp nhận rank trống;
  submit homepage tường minh xóa cờ + rank khi bỏ chọn.
- Đảo 2 assertion "collapsed by default" → "expanded by default".

## 4. Cách hoạt động (người dùng thấy gì)

1. Trong trang soạn bài (Gutenberg), cuộn xuống ngăn kéo **"Meta Boxes"** ở đáy.
2. Trong hộp **"Nội dung và hiển thị PGDS"**, nhóm **"Điều kiện và vị trí hiển thị trang chủ"**
   giờ **hiện mở sẵn** (không cần bấm `▸`).
3. Tích **"Đọc nhiều"** → chọn **"Vị trí Đọc nhiều"** (1–4) → **Cập nhật**.
4. Trang chủ: khối "Đọc nhiều" ở sidebar hiển thị bài này theo đúng vị trí đã chọn.
5. Danh sách bài viết: cột trạng thái hiện thêm `🔥 Đọc nhiều (#vị trí)`.

**Hành vi fallback (quan trọng để không vỡ site):** nếu chưa có bài nào được tích "Đọc
nhiều", khối vẫn tự xếp theo số bình luận như trước — không thay đổi gì với site đang chạy.

## 5. Kiểm chứng

### Test tự động

```
$ cd infra/local
$ docker compose up -d --build
$ ./sync.sh
$ docker compose run --rm wpcli -c 'sh /var/www/html/.pgds-scripts/setup.sh'
$ docker compose run --rm wpcli -c 'sh /var/www/html/.pgds-tools/tests/cms-editor-regression.sh'
```

Kết quả thực tế (2026-09-12, môi trường Docker local):

```
Success: CMS editor regression suite passed.
==> CMS editor regression suite passed.
```

Harness chạy `set -eu` + `wp eval-file`; mỗi assertion fail gọi `WP_CLI::error()` → exit
non-zero → shell dừng, không bao giờ in dòng `Success`. Vì in `Success` nên **toàn bộ
assertion (bao gồm 20+ assertion "Đọc nhiều") đều PASS**.

### Kiểm tra bằng mắt

- Mở http://localhost:8080/wp-admin (`admin` / `admin123`), vào 1 bài viết → nhóm "Điều kiện
  và vị trí hiển thị trang chủ" hiện mở sẵn, có 2 ô mới "Đọc nhiều" + "Vị trí Đọc nhiều".
- Tích + đặt vị trí 1 → Cập nhật → mở http://localhost:8080 thấy bài đó đứng đầu khối
  "Đọc nhiều".

## 6. Lưu ý vận hành & việc còn lại

- **Assets chưa build:** thư mục `assets/dist/` bị gitignore và không có trong repo. Trước
  khi chạy local phải build: `cd wp-content/themes/pgds && npm install && npm run build &&
  npm run fonts`. Thiếu bước này trang sẽ trắng (không có CSS/JS). Đây là nguyên nhân giao
  diện "xấu" đã gặp, không liên quan tới tính năng "Đọc nhiều".
- **Chưa commit:** 4 file thay đổi đang ở trạng thái `modified`. Cần tạo branch + commit
  trước khi đưa lên `main`.
- **Không cần migration DB:** hai meta được tạo lười (lazy) khi lưu bài, không cần chạy lệnh
  gì trên production ngoài deploy code + xóa FastCGI cache.
- **Đồng nhất với Featured:** rank "Đọc nhiều" hiện **chưa** có kiểm tra trùng rank giữa các
  bài như `pgds_find_featured_rank_conflict()`. Nếu cần cảnh báo "hai bài cùng vị trí", có thể
  bổ sung hàm tương tự cho popular — chưa nằm trong phạm vi yêu cầu lần này.
