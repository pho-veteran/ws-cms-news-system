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
6. Hoàn thành khối **Nội dung và hiển thị PGDS** rồi chọn **Lưu bản nháp**, **Xem trước** hoặc **Đăng**.

> **Ảnh đại diện không phải tuỳ chọn.** Card không có ảnh sẽ để lại một ô trống trong lưới
> trang chủ. Hệ thống không tự tìm ảnh thay thế.

---

## 0:15 — Khối "Nội dung và hiển thị PGDS" *(15 phút)*

Cuộn xuống **phía dưới vùng soạn nội dung** — không phải cột bên phải. Khối này có ba nhóm
thông tin điều khiển cách bài xuất hiện trên trang.

### Biên tập

| Trường | Dùng để làm gì | Ai điền |
|---|---|---|
| Sa-pô | Đoạn dẫn hiển thị trên card, trong danh sách, và làm đoạn mở đầu bài. | Bạn |
| Chuyên mục chính | Chọn một chuyên mục đã chọn cho bài. | Bạn |
| Nguồn tin | Hiện ở cuối bài và dưới ảnh video. Ghi khi bài dẫn lại từ nơi khác. | Bạn |
| Tên tác giả hiển thị | Chỉ điền khi tên cần hiện khác với tên tài khoản đăng bài. | Bạn |

### Điều kiện và vị trí hiển thị trang chủ

| Trường | Dùng để làm gì | Ai điền |
|---|---|---|
| Tin nổi bật | Bật để bài được xét vào khối nổi bật đầu trang chủ. | Bạn |
| Vị trí Tin nổi bật | `1` = bài chính. `2`–`4` = ba bài bên cạnh. | Bạn |
| Tin ảnh | Bật để bài hiện trong khối "Tin ảnh". | Bạn |

### Video

| Trường | Dùng để làm gì | Ai điền |
|---|---|---|
| Video YouTube | Gắn một video cho bài. Xem chặng 0:40. | Bạn |
| Thời lượng | Số phút hiện trên ảnh video. | Tự động |
| Trạng thái | Cho biết video đã được kiểm tra đến đâu. | Tự động |

> **Sa-pô là trường đáng viết tay nhất.** Nếu bỏ trống, card sẽ tự lấy mấy chục chữ đầu của
> bài — thường cắt giữa câu — và trang bài sẽ **không có đoạn dẫn nào**. Một sa-pô viết
> riêng, khoảng 25–35 chữ, tóm ý và không lặp lại câu mở đầu.

**Thời lượng** và **Trạng thái** trong nhóm **Video** chỉ để xem. PGDS tự cập nhật, nên bạn
không cần và không thể tự nhập. Trạng thái có thể là **Chưa gắn video**, **Đang chờ đồng bộ**,
**Đã đồng bộ** hoặc **Video không còn khả dụng**.

---

## 0:30 — Đưa bài lên trang chủ *(10 phút)*

Khối **Điều kiện và vị trí hiển thị trang chủ** chỉ quyết định Tin nổi bật và Tin ảnh. Các
khối còn lại trên trang chủ tự chọn bài phù hợp theo chuyên mục và thời gian đăng; bạn không cần
đưa bài vào từng khối. Một bài được chọn cả Tin nổi bật và Tin ảnh chỉ hiện một lần trên trang chủ.

Khối nổi bật đầu trang chủ có bốn chỗ, và bạn là người quyết định bài nào vào chỗ nào.

1. Bật **Tin nổi bật**.
2. Đặt **Vị trí Tin nổi bật**: `1` cho bài lead, `2`, `3`, `4` cho ba bài nhỏ.
3. Cập nhật bài.

> **Đổi bài lead.** Muốn thay bài lead, hãy hạ bài cũ xuống số khác hoặc tắt **Tin nổi bật**
> của nó trước.
>
> **Nếu trùng vị trí.** Khi hai bài đã đăng cùng dùng một **Vị trí Tin nổi bật**, màn hình sẽ
> báo nhưng vẫn lưu bài. Bấm **Mở bài đang trùng vị trí** trong thông báo để mở và sửa bài kia,
> hoặc đổi vị trí của bài đang mở.

**Tin ảnh** là một panel riêng, không liên quan đến bốn slot trên. Bật cho những bài mà bức
ảnh chính là nội dung.

Mỗi bài chỉ xuất hiện **một lần** trên trang chủ. Nếu bạn bật cả nổi bật và tin ảnh cho cùng
một bài, hệ thống chọn vị trí đầu tiên và bỏ vị trí sau — đây là chủ ý, để trang chủ không
lặp bài.

---

## 0:40 — Gắn video *(10 phút)*

Mỗi bài có **một** video chính. Không phải nhúng video vào giữa nội dung — chỉ cần điền mã.

1. Mở video trên YouTube, sao chép **toàn bộ đường dẫn**.
2. Dán vào trường **Video YouTube**. Hệ thống tự cắt lấy phần mã.
3. Cập nhật bài.

Trang bài sẽ hiện ảnh nền kèm nút phát. Video chỉ tải khi người đọc bấm vào.

> **Ảnh nền video.** Hệ thống tự tải ảnh từ YouTube về máy chủ mỗi đêm. Trong lúc chờ, ảnh
> đại diện của bài được dùng tạm — nên bài có video vẫn cần ảnh đại diện.
>
> **Thời lượng và Trạng thái.** Hai mục này trong nhóm **Video** chỉ để xem và được PGDS tự
> cập nhật. Bạn không cần và không thể tự nhập. Trạng thái có thể là **Chưa gắn video**, **Đang
> chờ đồng bộ**, **Đã đồng bộ** hoặc **Video không còn khả dụng**.

> **Nếu video bị xoá hoặc chuyển sang riêng tư.** Hệ thống phát hiện và tự ẩn nút phát, thay
> bằng dòng "Video không còn khả dụng". Hãy kiểm tra lại nguồn video trước khi đăng hoặc cập
> nhật bài.

---

## 0:50 — Lưu nháp, xem trước và kiểm tra *(7 phút)*

- **Lưu bản nháp** khi bài chưa hoàn tất. Chỉ người trong toà soạn thấy bản nháp.
- Bấm **Xem trước** để đọc lại như người xem sẽ thấy. Hãy xem cả trên điện thoại.
- Bấm **Đăng** khi bài đã sẵn sàng; với bài đã đăng, bấm **Cập nhật** sau khi sửa.
- Bạn có thể lưu nháp và xem trước nhiều lần. Khi đăng hoặc cập nhật, nội dung mới tự hiện
  cho độc giả.

### Các thông báo cần kiểm tra

Thông báo chỉ nhắc bạn kiểm tra, **không ngăn lưu bài**.

- Với Tin nổi bật hoặc Tin ảnh, thiếu **Ảnh đại diện** hoặc **Sa-pô**: bài vẫn lưu nhưng có
  thể thiếu ảnh hoặc đoạn giới thiệu trên trang chủ.
- Bật **Tin nổi bật** mà chưa chọn vị trí từ `1` đến `4`: lựa chọn mới không được áp dụng; hãy
  chọn lại vị trí.
- **Chuyên mục chính** chưa nằm trong các chuyên mục đã chọn cho bài: lựa chọn mới không được
  áp dụng; hãy chọn lại chuyên mục.
- Đường dẫn **Video YouTube** chưa đúng: video mới không được áp dụng; hãy dán lại đường dẫn.
- Nếu trùng **Vị trí Tin nổi bật**, dùng liên kết **Mở bài đang trùng vị trí** để mở bài kia và
  sắp xếp lại.

---

## 0:57 — Tự kiểm trước khi đăng *(3 phút)*

Chạy qua danh sách này cho bài đầu tiên bạn tự đăng một mình.

- [ ] Tiêu đề đọc được khi đứng riêng, không cần ngữ cảnh.
- [ ] Sa-pô viết tay, không lặp câu mở đầu của bài.
- [ ] Đã đặt ảnh đại diện, nhất là khi chọn Tin nổi bật hoặc Tin ảnh.
- [ ] Đã chọn chuyên mục; nếu có Chuyên mục chính thì chuyên mục đó cũng đã được chọn cho bài.
- [ ] Nếu bật Tin nổi bật: đã chọn vị trí từ `1` đến `4` và không trùng bài đã đăng khác.
- [ ] Nếu là Tin ảnh: đã bật Tin ảnh.
- [ ] Nếu có video: đã dán đường dẫn YouTube đúng và Trạng thái không báo video không còn khả dụng.
- [ ] Nếu dẫn lại: đã ghi nguồn tin.
- [ ] Đã đọc các thông báo trên màn hình.
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
