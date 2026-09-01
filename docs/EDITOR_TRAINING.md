# Giờ đầu tiên trong toà soạn

Tài liệu tập huấn biên tập viên — chuyên trang **Phật giáo và Đời sống**.

> **Ghi chú cho người bảo trì:** tài liệu này viết bằng tiếng Việt vì người đọc là biên
> tập viên toà soạn, không phải lập trình viên — cùng loại nội dung hướng tới người dùng
> như nhãn trường trong màn hình quản trị. Xem quy ước ngôn ngữ trong `CLAUDE.md`.
> Đây là mục *"One-hour editor training"* mà PROPOSAL_01 §14 yêu cầu.
>
> Mọi tên trường dưới đây khớp đúng nhãn hiển thị trong
> `wp-content/themes/pgds/inc/meta-fields.php`. Nếu đổi nhãn ở đó, sửa cả tài liệu này.

| | |
|---|---|
| **Thời lượng** | 60 phút |
| **Dành cho** | Biên tập viên |
| **Cần trước** | Tài khoản + mật khẩu quản trị |
| **Không cần** | Kiến thức kỹ thuật |

---

## 0:00 — Đăng nhập và nhìn quanh *(5 phút)*

Vào địa chỉ trang, thêm `/wp-admin` vào sau tên miền. Đăng nhập bằng tài khoản toà soạn cấp.

Ba mục bên trái bạn sẽ dùng:

- **Bài viết** — nơi bạn làm việc gần như toàn bộ thời gian.
- **Lời Phật dạy** — mục riêng cho các đoạn kinh, lời dạy ngắn hiển thị ở cột bên.
- **Lịch vạn niên** — dữ liệu cho ô lịch âm ở cột bên. Thường chỉ cập nhật một lần.

> **Ghi nhớ.** Chuyên mục đã được thiết lập sẵn: 6 chuyên mục lớn và 10 chuyên mục con.
> **Đừng tạo chuyên mục mới** — trang chủ và menu được dựng từ đúng danh sách này, nên một
> chuyên mục lạ sẽ không xuất hiện ở đâu cả.

---

## 0:05 — Đăng bài đầu tiên *(10 phút)*

1. **Bài viết → Viết bài mới.**
2. Nhập tiêu đề. Đây cũng là tiêu đề hiển thị trên card ở trang chủ, nên viết đủ nghĩa khi
   đứng một mình.
3. Viết nội dung. Mỗi đoạn là một khối; nhấn Enter để sang đoạn mới.
4. Bên phải, chọn **Chuyên mục** — ít nhất một.
5. Đặt **Ảnh đại diện**. Ảnh này dùng cho card, cho ảnh mở đầu bài, và làm ảnh nền cho
   video nếu bài có video.
6. Nhấn **Đăng**.

> **Ảnh đại diện không phải tuỳ chọn.** Card không có ảnh sẽ để lại một ô trống trong lưới
> trang chủ. Hệ thống không tự tìm ảnh thay thế.

---

## 0:15 — Khối "Thông tin PGDS" *(15 phút)*

Cuộn xuống **phía dưới vùng soạn nội dung** — không phải cột bên phải. Khối này gồm 8 trường
điều khiển cách bài xuất hiện trên trang.

| Trường | Dùng để làm gì | Ai điền |
|---|---|---|
| Sa-pô | Đoạn dẫn hiển thị trên card, trong danh sách, và làm đoạn mở đầu bài. | Bạn |
| Chuyên mục chính | Quyết định đường dẫn (breadcrumb) và nhãn chuyên mục trên card. | Bạn |
| YouTube ID | Gắn một video cho bài. Xem chặng 0:40. | Bạn |
| Thời lượng video | Số phút hiện trên góc ảnh video. | Tự động |
| Tin nổi bật | Bật để bài được xét vào khối nổi bật đầu trang chủ. | Bạn |
| Thứ tự slot nổi bật | `1` = bài lead lớn nhất. `2`–`4` = ba bài nhỏ bên cạnh. | Bạn |
| Tin ảnh | Bật để bài hiện trong panel "Tin ảnh" bên phải khối nổi bật. | Bạn |
| Nguồn tin | Hiện ở cuối bài và dưới ảnh video. Ghi khi bài dẫn lại từ nơi khác. | Bạn |

> **Sa-pô là trường đáng viết tay nhất.** Nếu bỏ trống, card sẽ tự lấy mấy chục chữ đầu của
> bài — thường cắt giữa câu — và trang bài sẽ **không có đoạn dẫn nào**. Một sa-pô viết
> riêng, khoảng 25–35 chữ, tóm ý và không lặp lại câu mở đầu.

Trường **Thời lượng video** để hệ thống tự điền mỗi đêm. Nếu bạn nhập tay, lần cập nhật kế
tiếp sẽ ghi đè.

---

## 0:30 — Đưa bài lên trang chủ *(10 phút)*

Khối nổi bật đầu trang chủ có bốn chỗ, và bạn là người quyết định bài nào vào chỗ nào.

1. Bật **Tin nổi bật**.
2. Đặt **Thứ tự slot**: `1` cho bài lead, `2`, `3`, `4` cho ba bài nhỏ.
3. Cập nhật bài.

> **Đổi bài lead.** Muốn thay bài lead, hãy hạ bài cũ xuống số khác hoặc tắt **Tin nổi bật**
> của nó trước. Nếu hai bài cùng đặt số 1, chỉ một bài lên được và bài còn lại biến mất khỏi
> khối.

**Tin ảnh** là một panel riêng, không liên quan đến bốn slot trên. Bật cho những bài mà bức
ảnh chính là nội dung.

Mỗi bài chỉ xuất hiện **một lần** trên trang chủ. Nếu bạn bật cả nổi bật và tin ảnh cho cùng
một bài, hệ thống chọn vị trí đầu tiên và bỏ vị trí sau — đây là chủ ý, để trang chủ không
lặp bài.

---

## 0:40 — Gắn video *(10 phút)*

Mỗi bài có **một** video chính. Không phải nhúng video vào giữa nội dung — chỉ cần điền mã.

1. Mở video trên YouTube, sao chép **toàn bộ đường dẫn**.
2. Dán vào trường **YouTube ID**. Hệ thống tự cắt lấy phần mã.
3. Cập nhật bài.

Trang bài sẽ hiện ảnh nền kèm nút phát. Video chỉ tải khi người đọc bấm vào — nhờ vậy trang
mở nhanh và không gắn cookie theo dõi khi chưa cần.

> **Ảnh nền video.** Hệ thống tự tải ảnh từ YouTube về máy chủ mỗi đêm. Trong lúc chờ, ảnh
> đại diện của bài được dùng tạm — nên bài có video vẫn cần ảnh đại diện.

> **Nếu video bị xoá hoặc chuyển sang riêng tư.** Hệ thống phát hiện và tự ẩn nút phát, thay
> bằng dòng "Video không còn khả dụng". Bạn không phải làm gì — nhưng nếu thấy dòng đó, nghĩa
> là video nguồn đã mất và nên thay bài.

---

## 0:50 — "Sửa là thấy ngay" *(7 phút)*

Trang được lưu đệm để tải nhanh, nhưng điều đó không làm bạn thấy nội dung cũ.

- **Khi bạn đang đăng nhập**, bạn luôn thấy phiên bản mới nhất — kể cả bản nháp, qua nút
  **Xem trước**.
- **Với người đọc**, bộ đệm được xoá tự động mỗi lần bạn đăng hoặc cập nhật một bài đã đăng.
  Không cần bấm gì thêm.
- **Lưu nháp thì không xoá đệm** — vì bản nháp chưa có trên trang. Đúng như mong đợi.

> **Nếu người đọc báo vẫn thấy bản cũ.** Nhờ họ tải lại trang bỏ qua bộ đệm trình duyệt
> (Ctrl+F5, hoặc Cmd+Shift+R trên máy Mac). Nếu vẫn còn, báo người phụ trách kỹ thuật —
> đừng đăng lại bài, vì việc đó không giải quyết được nguyên nhân.

---

## 0:57 — Tự kiểm trước khi đăng *(3 phút)*

Chạy qua danh sách này cho bài đầu tiên bạn tự đăng một mình.

- [ ] Tiêu đề đọc được khi đứng riêng, không cần ngữ cảnh.
- [ ] Sa-pô viết tay, không lặp câu mở đầu của bài.
- [ ] Đã đặt ảnh đại diện.
- [ ] Đã chọn chuyên mục, và chọn cả chuyên mục chính.
- [ ] Nếu có video: đã dán đường dẫn YouTube.
- [ ] Nếu dẫn lại: đã ghi nguồn tin.
- [ ] Đã xem trước trên điện thoại, không chỉ trên máy tính.

---

## Ba việc còn chờ người trong toà soạn quyết

Không thuộc phần tập huấn, nhưng cần một người nhận trách nhiệm trước ngày phát hành.

**Tên miền và cấu hình biên (Cloudflare).** Cần một mã truy cập (API token) cho tên miền của
toà soạn, phạm vi `Zone:Read`, `Zone Settings:Edit`, `Cache Rules:Edit`. Không cần cài thêm
công cụ nào — script `infra/scripts/pgds-cloudflare-setup.sh` chỉ dùng `curl`. Chạy kèm
`--dry-run` trước để xem trước thay đổi.

**Địa chỉ email nhận cảnh báo.** Hệ thống theo dõi máy chủ đã hoạt động và đang ghi nhật ký
(syslog). Để cảnh báo gửi được vào hộp thư, điền địa chỉ vào biến Terraform
`alarm_notification_email`, apply, rồi bấm link xác nhận AWS gửi tới. Không cần SES, không
cần tên miền.

**Thông tin pháp lý ở chân trang.** Giấy phép hoạt động, cơ quan chủ quản, tổng biên tập, địa
chỉ, điện thoại, email. Điền tại **Giao diện → Tuỳ biến → Thông tin toà soạn**. Cần được
duyệt trước khi phát hành (PROPOSAL_01 §13 — không phải quyết định kỹ thuật).
