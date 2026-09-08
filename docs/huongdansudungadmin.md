# Hướng Dẫn Sử Dụng Trang Quản Trị Admin (PGDS - Hệ Thống Tin Tức Phật Giáo)

Tài liệu hướng dẫn chi tiết quy trình đăng bài, quản lý nội dung và sử dụng toàn bộ tính năng trên Trang Quản trị (Admin Dashboard) của hệ thống CMS PGDS. Mỗi mục đều kèm hình ảnh chụp thực tế từ hệ thống quản trị đang chạy.

---

## 1. Đăng nhập trang Quản trị (Admin Dashboard)

- **Đường dẫn truy cập trang quản trị (URL):** 👉 [http://localhost:8080/wp-admin](http://localhost:8080/wp-admin)
- **Tài khoản đăng nhập mặc định:**
  - **Tên đăng nhập (Username):** `admin`
  - **Mật khẩu (Password):** `admin123`

![1. Bảng điều khiển Admin Dashboard](image/01_dashboard.png)

---

## 2. Tổng quan Thanh Menu Quản trị PGDS

Trên thanh menu bên trái màn hình sau khi đăng nhập thành công, hệ thống phân chia rõ ràng từng khu vực chuyên môn:

| Menu | Chức năng chính | Ảnh minh họa |
| :--- | :--- | :--- |
| **Dashboard** | Bảng điều khiển tổng quan thông số bài viết, bình luận và trạng thái hệ thống. | `01_dashboard.png` |
| **Bài viết** | Quản lý các bài viết tin tức thông thường (*Tin Phật sự, Sống an lành, Phật tích, Tốt đời đẹp đạo, Lối sống xanh...*). | `02_baiviet_list.png` |
| **E-magazine** | Đăng bài và trình trình bày bài viết dạng Tạp chí điện tử đồ họa cao cấp. | `04_emagazine_list.png` |
| **Video** | Quản lý các bài viết dạng Video (tự động đồng bộ từ YouTube). | `06_video_list.png` |
| **Vietnam Buddhism** | Quản lý danh mục bài viết tiếng Anh dành cho độc giả quốc tế. | `08_vietnambuddhism_list.png` |
| **Lời Phật dạy** | Quản lý trích dẫn triết lý Lời Phật dạy hiển thị ở sidebar và trang chủ. | `09_loiphatday_list.png` |
| **Comments** | Duyệt, phản hồi và xử lý các bình luận từ độc giả gửi về. | `10_comments_list.png` |
| **Media** | Thư viện tải lên và quản lý hình ảnh, tệp đính kèm. | `11_media_library.png` |

---

## 3. Hướng dẫn chi tiết Đăng bài viết theo từng Chuyên mục

### 🎬 3.1. Đăng bài Video (Chuyên mục Media > Video)

1. **Truy cập danh sách Video:** Chọn menu **Video** trên thanh sidebar trái.
   
   ![Danh sách bài viết Video trong Admin](image/06_video_list.png)

2. **Tạo bài viết Video mới:** Nhấn nút **Add New Video** (hoặc vào menu **Bài viết** ➔ **Viết bài mới**).
3. **Nhập thông tin Video:**
   - **Tiêu đề bài viết:** Điền tiêu đề bài video.
   - **Thẻ Video (Video YouTube):** Dán đường dẫn YouTube (VD: `https://www.youtube.com/watch?v=...`) hoặc Mã Video 11 ký tự.
     > 💡 **Tự động hóa:** Hệ thống tự động lấy tiêu đề, ảnh Poster Thumbnail và Thời lượng (Duration) từ YouTube.
   - **Chuyên mục chính:** Hệ thống chọn mặc định là `Video`.
   - **Sa-pô:** Nhập đoạn dẫn tóm tắt video.
   - **Tên tác giả hiển thị:** Nhập tên tác giả hoặc đơn vị thực hiện (VD: `Minh Anh`).

   ![Giao diện soạn thảo bài viết Video mới](image/07_video_editor.png)

4. **Đăng bài:** Nhấn nút **Publish (Đăng bài)** ở góc phải trên.

---

### 📖 3.2. Đăng bài Tạp chí Điện tử (E-magazine)

1. **Truy cập danh sách E-magazine:** Chọn menu **E-magazine** trên thanh menu trái.

   ![Danh sách bài viết E-magazine](image/04_emagazine_list.png)

2. **Nhấn nút Add New E-magazine:**

   ![Giao diện soạn thảo E-magazine Gutenberg](image/05_emagazine_editor.png)

3. **Chèn các mẫu Gutenberg Block Patterns E-magazine:**
   - Khi viết bài E-magazine, nhấn nút `+` (Add Block) góc trên trái ➔ chọn tab **Patterns**.
   - Chọn các mẫu trình bày E-magazine đẹp mắt có sẵn:
     - *Wide Image:* Ảnh tràn viền chiều rộng.
     - *Pull Quote:* Khối trích dẫn điểm nhấn.
     - *Image Pair:* Bộ ảnh đôi 2 cột.
     - *Chapter Heading:* Tiêu đề phân đoạn/Chương.
     - *Full Image:* Ảnh tràn màn hình.
4. **Cấu hình thuộc tính PGDS:**
   - **Chuyên mục chính:** Đảm bảo chọn `E-magazine`.
   - **Sa-pô:** Nhập mở đầu hấp dẫn.
   - **Tên tác giả:** Nhập tên biên tập viên / tác giả.
   - **Featured Image (Ảnh đại diện):** Tải ảnh chất lượng cao làm bìa E-magazine.
5. **Đăng bài:** Nhấn **Publish**.

---

### 📰 3.3. Đăng bài Tin tức thông thường (Tin Phật sự, Sống an lành, Phật tích...)

1. **Truy cập:** Chọn menu **Bài viết (Posts)** ➔ Danh sách toàn bộ tin tức hiển thị.

   ![Danh sách bài viết Tin tức](image/02_baiviet_list.png)

2. **Nhấn Viết bài mới (Add New):**

   ![Giao diện viết bài mới chuẩn](image/03_baiviet_editor.png)

3. **Nhập dữ liệu:**
   - **Tiêu đề & Nội dung:** Điền tiêu đề và soạn nội dung chi tiết.
   - **Chuyên mục (Categories):** Tích chọn chuyên mục phù hợp (*Tin Phật sự, Phật tích, Lối sống xanh, Tốt đời đẹp đạo...*).
   - **Cấu hình PGDS:** Nhập Sa-pô, chọn Chuyên mục chính hiển thị, và nhập Tên tác giả hiển thị.
4. **Đăng bài:** Nhấn **Publish (Đăng bài)**.

---

### 🌏 3.4. Đăng bài Tiếng Anh (Vietnam Buddhism)

1. **Truy cập:** Chọn menu **Vietnam Buddhism** trên thanh điều hướng.

   ![Danh sách bài viết Vietnam Buddhism](image/08_vietnambuddhism_list.png)

2. **Soạn bài viết:**
   - Nhập Tiêu đề & Nội dung bài viết bằng Tiếng Anh.
   - Chuyên mục chính mặc định chọn `Vietnam Buddhism`.
   - Điền Sa-pô và Tác giả bài viết.
3. **Đăng bài:** Nhấn **Publish**.

---

## 4. Quản lý Nội dung bổ sung

### ☸️ 4.1. Quản lý Lời Phật dạy

1. Chọn menu **Lời Phật dạy** trên thanh Admin.

   ![Danh sách Lời Phật dạy](image/09_loiphatday_list.png)

2. Nhấn nút **Thêm lời dạy** để tạo mới câu trích dẫn triết lý Lời Phật dạy hiển thị ở khối Sidebar và Trang chủ.

---

### 💬 4.2. Quản lý Bình luận độc giả (Comments)

1. Vào menu **Comments (Bình luận)**.

   ![Giao diện Quản lý Bình luận](image/10_comments_list.png)

2. Rê chuột vào từng bình luận để thực hiện các thao tác:
   - **Approve:** Duyệt hiển thị công khai bình luận.
   - **Reply:** Trả lời trực tiếp bình luận của độc giả.
   - **Spam / Trash:** Loại bỏ bình luận rác hoặc xóa khỏi hệ thống.

---

### 🖼️ 4.3. Quản lý Thư viện Media (Thư viện tệp)

1. Chọn menu **Media** ➔ chọn **Thư viện (Library)**.

   ![Giao diện Thư viện Media](image/11_media_library.png)

2. Tải lên, tìm kiếm, chỉnh sửa thông tin hoặc xóa hình ảnh/tệp đính kèm.

---

## 5. Lưu ý quan trọng dành cho Biên tập viên

- **Định dạng thời gian công bố:** Hệ thống hiển thị chuẩn `YYYY-MM-DD HH:mm:ss` (Ví dụ: `2026-09-08 22:46:00`).
- **Tên tác giả:** Nhập trực tiếp tên tác giả trong ô cấu hình PGDS, hệ thống hiển thị chính xác tên tác giả mà không tự thêm từ *"Theo"* phía trước.
- **Chuyên mục chính (Primary Category):** Luôn kiểm tra ô chọn *Chuyên mục chính* để định vị chính xác bài viết xuất hiện ở trang giao diện tương ứng.
