# Fonts (self-host)

Đặt các file `.woff2` (subset `vietnamese,latin`) vào thư mục này:

```
be-vietnam-pro-400.woff2
be-vietnam-pro-600.woff2
be-vietnam-pro-700.woff2
fraunces-400.woff2
fraunces-700.woff2
```

Cách lấy nhanh (không hotlink Google Fonts ở production):

1. Vào https://gwfh.mranftl.com/fonts (google-webfonts-helper)
2. Chọn **Be Vietnam Pro** (400, 600, 700) và **Fraunces** (400, 700),
   charset `vietnamese` + `latin`, định dạng `woff2`.
3. Tải về, đổi tên đúng như trên, copy vào đây.

Thiếu file → theme fallback `system-ui` / `Georgia`, vẫn chạy bình thường.
`@font-face` khai báo ở `src/scss/03-elements/_fonts.scss`;
preload 2 file critical ở `inc/enqueue.php`.
