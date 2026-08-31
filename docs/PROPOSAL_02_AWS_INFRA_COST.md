# PROPOSAL 02 — Hạ tầng & Chi phí

> **Vòng đời hệ thống:** ephemeral, tối đa 6 tháng.
> **Tiêu chí ưu tiên:** cost effective.
> **Nhân lực:** 1–2 engineer. **Thời gian:** 3 ngày.
> **Trần ngân sách:** USD 100 cho toàn vòng đời.

---

## 1. TL;DR

**Lightsail 2GB + Cloudflare (DNS + CDN) + S3 (Terraform state, DB dump) + SES.**

Chi phí ~**USD 13.9/tháng**, ~**USD 85** cho 6 tháng, trên **USD 200 credits còn lại** → net cash **~$0**. 86% chi phí là chính Lightsail và không có gì rẻ hơn ở tải này.

Không Route 53, không CloudFront, không ACM, không S3 media offload, không Reserved Instance. Media nằm trên disk instance. Danh sách loại đầy đủ ở mục 13.

Có Terraform (state trong S3 với `use_lockfile`). Có CI/CD (GitHub Actions, một môi trường).

**Account đích đã xác minh đủ điều kiện** (mục 8.2): standalone, không Organization, không SCP, Lightsail khả dụng ở `ap-southeast-1` với bundle `small_3_0` $12.00. Việc còn chặn ở D0 là **SES đang ở sandbox** — request production access mất 24h+.

---

## 2. Compute

Giá on-demand region `ap-southeast-1`, tháng 8/2026.

| Phương án | Instance/tháng | + Storage 60GB | + IPv4 | + Egress 3TB | **Tổng** |
|---|---|---|---|---|---|
| **Lightsail Small 2GB** | $12.00 | gồm 60GB SSD | gồm | **gồm 3TB** | **~$12** |
| EC2 t4g.small 2GB | $15.48 | $5.76 (gp3) | ~$3.65¹ | $0.12/GB sau 100GB free | ~$25+ |
| EC2 t4g.micro 1GB | $7.74 | $5.76 | ~$3.65 | idem | ~$17² |
| EC2 t3a.small 2GB (x86) | $17.23 | $5.76 | ~$3.65 | idem | ~$27 |
| Lightsail Medium 4GB | $24.00 | gồm 80GB | gồm | gồm 4TB | ~$24 |

¹ Public IPv4 $0.005/h. Cần xác nhận lại nếu chọn EC2.
² 1GB RAM không chạy nổi WordPress + MariaDB + Redis. Loại vì kỹ thuật, không vì giá.

**Giá đơn vị:**

| | Giá |
|---|---|
| Lightsail Small 2GB — bundle `small_3_0` (2 vCPU, 60GB SSD, 3TB transfer) | **$12.00/tháng** |
| Lightsail Medium 4GB — bundle `medium_3_0` (2 vCPU, 80GB, 4TB) | **$24.00/tháng** |
| Lightsail snapshot | $0.05/GB-month |
| EC2 t4g.small | $0.0212/h |
| EC2 t4g.micro | $0.0106/h |
| EC2 t4g.medium | $0.0424/h |
| EC2 t3a.small | $0.0236/h |
| EBS gp3 | $0.096/GB-month |
| EBS gp2 | $0.12/GB-month |
| EC2 egress (first 10TB, ngoài free tier) | $0.12/GB |
| S3 Standard (first 50TB) | $0.025/GB-month |
| SES outbound | $0.0001/recipient |

### 2.1 Vì sao Lightsail thắng dù instance-hour tương đương

Lightsail bundle sẵn 3 thứ mà EC2 tính riêng: **60GB SSD, IPv4, và 3TB egress**.

Egress là điểm quyết định. EC2 tính $0.12/GB (first 10TB, sau 100GB free/tháng). 3TB egress trên EC2 = **$360**. Bundle Lightsail = **$0**.

### 2.2 Các phương án đã loại

| Phương án | Lý do loại |
|---|---|
| Fargate / ECS / App Runner | WordPress cần persistent filesystem → phải thêm EFS + RDS. Đắt gấp 3–4×, setup không vừa 3 ngày. |
| Lambda + Bref | Không phù hợp WP admin/Gutenberg, cold start, cần EFS. |
| Graviton EC2 (t4g) | Rẻ hơn x86 ~10% nhưng vẫn thua Lightsail vì bundle. |
| Reserved Instance / Savings Plan | Commit 1 năm cho tài sản 6 tháng. |

**Chốt: Lightsail Small 2GB.** Không có phương án nào rẻ hơn ở tải này.

---

## 3. Kiến trúc

```
                    ┌─────────────────┐
   Khách  ────────► │   Cloudflare    │  DNS + CDN + SSL (free)
                    │  cache: static  │  KHÔNG cache HTML
                    └────────┬────────┘
                             │ HTTPS, origin cert
                             ▼
                 ┌───────────────────────┐
                 │  Lightsail 2GB (SG)   │
                 │  ─────────────────────│
                 │  nginx + FastCGI cache│
                 │  PHP 8.3-FPM          │
                 │  MariaDB 10.11        │
                 │  Redis (object cache)  │
                 │  media trên disk       │
                 └───────┬───────────────┘
                         │
          ┌──────────────┼──────────────┐
          ▼              ▼              ▼
    Lightsail        S3 bucket        SES
    snapshot      ┌─────────────┐   (mail
    (4 rolling)   │ TF state    │  transactional)
                  │ DB dump     │
                  └─────────────┘
                   S3 Standard
                   không lifecycle
```

**Chấp nhận tường minh:** single instance, single AZ, **SPOF**. RTO 30–60 phút từ snapshot. Với site ephemeral 6 tháng và ngân sách này, HA không hợp lý về chi phí.

---

## 4. Cấu hình Lightsail

**Bundle:** Small 2GB — 2 vCPU, 2GB RAM, 60GB SSD, 3TB transfer. Region `ap-southeast-1` (Singapore).
**Blueprint:** Ubuntu 24.04 LTS (không dùng blueprint WordPress sẵn — cần kiểm soát nginx config).

### 4.1 Ngân sách RAM

Trên máy 2GB, ngân sách RAM phải tính trước rồi mới chọn số worker — không phải ngược lại. AWS docs (bảng tuning Lightsail WordPress) khuyến nghị ~10 worker cho máy 1.5–3GB; con số đó không định nghĩa trực tiếp PHP-FPM children, và với site còn phải chạy import + xử lý ảnh thì cần bảo thủ hơn.

| Thành phần | RAM |
|---|---|
| OS + nginx | ~250 MB |
| MariaDB (`innodb_buffer_pool_size=256M`) | ~400 MB |
| Redis (`maxmemory 160mb`) | ~160 MB |
| PHP-FPM: `pm=ondemand`, `pm.max_children=6` × ~60MB | ~360 MB |
| **Tổng** | **~1.17 GB** |
| **Còn lại cho OS page cache** | **~850 MB** |

- **`pm.max_children=6`** làm điểm khởi đầu, tune theo quan sát thật. Tăng chỉ khi thấy `max_children reached` trong log.
- **2GB swap** là bảo hiểm chống OOM, **không phải capacity**. Nếu swap được dùng thường xuyên → sai cấu hình.
- **FastCGI cache để trên disk, KHÔNG tmpfs.** tmpfs ăn thẳng vào 2GB RAM. Để OS page cache lo việc đó.
- Trong lúc import ảnh: giảm `pm.max_children` xuống 2 + `nice -n 19`.

### 4.2 Media trên disk — không offload S3

25–40 GB media vừa trong 60GB bundle. Serve qua Cloudflare, egress nằm trong 3TB.

Vì sao không S3 + Cloudflare: **S3 không thuộc Bandwidth Alliance**, nên egress S3 → Cloudflare vẫn tính $0.12/GB. Đưa media lên S3 làm tăng chi phí và thêm 1 plugin dependency.

Nếu sau này cần tách: **Cloudflare R2** (egress $0). Hoãn sang RUN, không làm trong 3 ngày.

**Hệ quả:** mọi bài viết accessible tức thời suốt 6 tháng. Không tiering, không retrieval latency.

---

## 5. Cloudflare — thay Route 53 + CloudFront + ACM

Đây là thay đổi tiết kiệm nhất trong toàn proposal.

### 5.1 Thay được những gì

| Bỏ | Tiết kiệm / lợi ích |
|---|---|
| Route 53 hosted zone + query | $0.50/tháng + query cost |
| CloudFront | Bỏ luôn cache behavior, Min TTL, CloudFront Function bypass cookie, purge ordering 2 lớp |
| ACM | Cloudflare cấp edge cert + origin cert free |
| S3 OAC | Không cần |
| Plugin S3 offload | Bỏ 1 dependency |

### 5.2 Tính năng cần dùng trên Free plan

| Tính năng | Free | Ghi chú |
|---|---|---|
| Cache Rules | ✅ | **Tối đa 10 rule** (Pro 25, Business 50, Ent 300). Cần 2. |
| Purge Everything | ✅ | |
| Purge by URL | ✅ | |
| Edge TTL / Browser TTL trong Cache Rule | ✅ | |
| Bypass cache (action) | ✅ | |
| Serve stale while revalidating | ✅ | |
| Origin Cache Control | ✅ nhưng **luôn bật, không tắt được** | Tắt là Enterprise-only |

Purge by tag / prefix / hostname: **thiết kế không phụ thuộc vào chúng** — chỉ dùng Purge Everything + Purge by URL. Nếu Free plan có tag purge thì đó là upside, cho phép nâng TTL và purge chính xác hơn.

Xác nhận trong dashboard trước khi chốt: DNS authoritative, proxy/CDN, bandwidth cho web content, Universal SSL, Origin CA cert, Brotli, HTTP/3.

### 5.3 Cấu hình

- Proxy **ON** (orange cloud) cho `@` và `www`
- SSL/TLS mode **Full (strict)** + Cloudflare Origin CA cert trên nginx
- 2 Cache Rule:

```
Rule 1  bypass : URI path chứa /wp-admin/, /wp-login.php, /wp-json/,
                 /wp-cron.php, /xmlrpc.php          → Bypass cache
Rule 2  static : extension css|js|woff2|webp|jpg|png|svg
                 → Eligible for cache, Edge TTL 1 month
```

**HTML không cache ở edge** — có chủ đích. Cloudflare chỉ cache static asset; page cache do Nginx FastCGI ở origin đảm nhiệm, purge explicit khi nội dung đổi. Nhờ vậy không cần rule bypass-theo-cookie, không có stale window ở edge, và biên tập viên sửa bài thì khách thấy ngay ở request kế tiếp. Dùng 2/10 slot free.
- Always Use HTTPS, Auto Minify OFF (asset đã minify trong CI)

### 5.4 Hai điều cần đọc trước khi chốt

- **ToS 2.8** — Free plan giới hạn serve lượng lớn non-HTML content (video, file lớn). Site này serve ảnh qua CDN là usage web bình thường, nhưng biết để tránh đẩy video lên sau này.
- **Rate limiting / WAF free** — có tier free nhưng số rule rất hạn chế. Nếu định dùng bảo vệ `wp-login.php` thì check trước; không đủ thì fail2ban ở origin gánh.

---

## 6. Backup

Toàn bộ S3 Standard, không lifecycle transition. Mọi bài viết phải truy cập được ngay trong suốt vòng đời.

### 6.1 Thiết kế

| Loại | Nơi lưu | Tần suất | Retention |
|---|---|---|---|
| Snapshot instance (gồm cả media) | Lightsail | 1×/ngày | 4 rolling |
| DB dump (`mysqldump` gzip) | S3 Standard | 2×/ngày | 7 ngày |
| Terraform state | S3 Standard | mỗi apply | versioning ON |

- **Snapshot chính là backup media** — không mirror media sang S3, đó là trùng lặp.
- 4 rolling thay vì 7: đủ cho site ephemeral. RPO 24h, RTO 30–60 phút.
- Snapshot Lightsail là **incremental** — chỉ block đổi mới tính tiền, không phải 4 bản full.

### 6.2 Isolation

Durability S3 ≠ chống xoá nhầm / ghi hỏng / lộ credential.

- **Versioning ON** cho bucket backup (rẻ, chống ghi đè/xoá nhầm)
- **IAM credential riêng cho backup**, tách khỏi credential SES. Policy chỉ `s3:PutObject` vào prefix backup — không `DeleteObject`.
- `prevent_destroy` trong Terraform cho bucket state + bucket backup

### 6.3 Restore — phải test thật ở Ngày 3

Không phải checkbox. Quy trình:

1. Tạo instance mới từ snapshot mới nhất
2. Gắn static IP (hoặc test bằng IP tạm)
3. Verify: WP load được, post count đúng, media hiển thị
4. **Đo RTO thật**, ghi vào `RUNBOOK.md`
5. Xoá instance test

Restore dùng **clean-room instance**, không ghi đè production.

---

## 7. Monitoring — $0

**Chỉ dùng Lightsail built-in metrics + alarm.** Không CloudWatch agent, không custom metric.

Built-in Lightsail metrics: CPU utilization, burst capacity, network in/out, status check. Alarm trên các metric này miễn phí trong Lightsail console.

**Vì sao không CloudWatch agent:** RAM và disk usage **không** phải built-in Lightsail metric — muốn có phải cài agent + custom metric, và custom metric có phí:

| CloudWatch (ap-southeast-1) | Giá |
|---|---|
| Custom metric (first 10.000) | $0.30/metric-month |
| Standard-resolution alarm | $0.10/alarm-metric-month |
| Log ingestion (Standard class) | $0.70/GB |

Đừng giả định "10 custom metric + 10 alarm + 5GB log là miễn phí". Với 6 tháng, 10 metric + 10 alarm = **~$24**, chiếm 28% ngân sách — nhiều hơn toàn bộ chi phí backup.

**Thay thế cho RAM/disk:** cron script trên instance kiểm `free -m` và `df -h`, gửi mail qua SES nếu vượt ngưỡng. Chi phí ~$0.

**Alarm bắt buộc (Lightsail, free):**
- CPU utilization > 80% trong 10 phút
- Burst capacity < 20%
- Status check failed

---

## 8. Chi phí

### 8.1 Bảng chi phí

| Hạng mục | /tháng | 6 tháng |
|---|---|---|
| Lightsail Small 2GB | $12.00 | $72.00 |
| Lightsail snapshot (4 rolling, incremental, ~40GB base @ $0.05/GB-mo) | ~$1.80 | ~$10.80 |
| S3 Standard — TF state + DB dump (~2GB) | ~$0.05 | ~$0.30 |
| SES (volume thấp, $0.0001/recipient) | ~$0.01 | ~$0.06 |
| Cloudflare | $0 | $0 |
| CloudWatch | $0 | $0 |
| Route 53 | không dùng | $0 |
| CloudFront | không dùng | $0 |
| **Base** | **~$13.86** | **~$83.16** |
| Tạm nâng 4GB vài ngày lúc import (~$0.40/ngày) | — | ~$1.20 |
| **Tổng dự kiến** | | **~$85** |

$85 gross trên $200 credits — biên thoải mái. 86% chi phí là chính $12/tháng Lightsail, và không có gì rẻ hơn ở tải này, nên đây gần như là sàn kiến trúc.


### 8.2 Account — đã xác minh đủ điều kiện

Account đích: **Free Tier, còn USD 200 credits, region `ap-southeast-1`.** Toàn bộ stack chạy được ngay, không cần thao tác mở khoá nào.

| Kiểm | Lệnh | Kết quả |
|---|---|---|
| Organization | `organizations describe-organization` | `AWSOrganizationsNotInUseException` → **standalone, không nằm trong Organization** |
| SCP áp lên account | `organizations list-policies` | Cùng exception → **không có SCP chặn service hay region** |
| Identity Center | `sso-admin list-instances` | `[]` |
| Principal | `sts get-caller-identity` | IAM user thường, mang `AdministratorAccess` |
| **Lightsail** | `lightsail get-bundles --region ap-southeast-1` | `small_3_0` **$12.00/tháng**, 2 vCPU / 2GB / 60GB SSD / 3072GB transfer, `isActive: true` |
| Lightsail instance | `lightsail get-instances` | `[]` → service khả dụng, chưa có resource |
| Blueprint | `lightsail get-blueprints` | `ubuntu_24_04`, `isActive: true` |
| Static IP / snapshot / keypair / alarm | `get-static-ips`, `get-instance-snapshots`, `get-key-pairs`, `get-alarms` | `[]` → API sống, chưa có resource |
| Region | `account list-regions` | `ap-southeast-1  ENABLED_BY_DEFAULT` |
| S3 | `s3api list-buckets` | `[]` → 0 bucket, phải tạo cả hai |
| SES | `sesv2 get-account --region ap-southeast-1` | `ProductionAccessEnabled: false` → **SANDBOX**. `SendingEnabled: true`, quota 200 mail/24h, rate 1 msg/s |
| SES identities | `sesv2 list-email-identities` | `[]` → chưa verify domain nào |
| Root MFA | `iam get-account-summary` | `AccountMFAEnabled: 1` |

Principal dùng để kiểm mang `AdministratorAccess`, nên các kết quả trên **không bị che bởi thiếu quyền IAM** — Lightsail khả dụng thật, không phải AccessDenied bị đọc nhầm. Lightsail trả `[]` là "chưa có resource", khác hẳn với bị chặn.

**Chốt cho Terraform:** `bundle_id = "small_3_0"`, `blueprint_id = "ubuntu_24_04"`. Thế hệ `small_2_0` không còn active.

**Bundle rẻ hơn đã cân nhắc và loại:** `small_ipv6_3_0` giá **$10.00/tháng**, cùng 2GB/2vCPU/60GB/3TB nhưng IPv6-only. Rẻ hơn $12 cả kỳ. Loại vì `aws_lightsail_static_ip` là IPv4 và instance IPv6-only không ra được endpoint IPv4-only (apt, WP core update, một số API). Ghi lại để không ai tưởng đây là $12 bỏ quên.

### 8.3 Bốn con số phải báo cáo riêng

| | |
|---|---|
| **Gross AWS cost (6 tháng)** | ~$85 |
| **Credits còn lại** | $200 |
| **Net cash outlay** | **~$0** |
| **Run-rate sau khi credits hết** | **~$13.9/tháng** |

Credits là phương tiện trả bill, **không** làm giảm gross cost kiến trúc. Đừng báo cáo net $0 mà bỏ gross.

$85 gross trên $200 credits là biên thoải mái — không còn là ràng buộc chặn thiết kế. Trần $100 vẫn giữ làm kỷ luật vận hành để phát hiện chi phí ngoài dự kiến.

### 8.4 Guardrail

- **AWS Budget alert** ở $50 và $85
- **Cost Anomaly Detection** — đã bật sẵn: `Default-Services-Monitor` (DIMENSIONAL/SERVICE) + subscription DAILY, CONFIRMED. Không phải làm lại.
- **Xử lý budget zero-spend đang có.** Account có một budget limit **$1.00/tháng**, ngưỡng ACTUAL > $0.01, hiện ở trạng thái **ALARM**. Khi Lightsail chạy $12/tháng nó sẽ kêu liên tục → alert fatigue → người ta tắt notification và mất luôn guardrail thật. **Sửa hoặc xoá khi tạo alert $50/$85.**
- **Không dùng được Organizations SCP** trên account standalone — đã xác minh. IAM deny policy chỉ chặn operational user, **không** chặn root hoặc admin khác. Kiểm region hằng tuần không phải enforcement — ghi giới hạn này vào runbook.
- **Tách credential vận hành.** Account hiện chỉ có **1 IAM user duy nhất, mang `AdministratorAccess`**. Không đặt key đó lên instance. Tạo credential riêng cho backup (chỉ `s3:PutObject` vào prefix) và cho SES, tách nhau — mục 6.2 và 10.2.

### 8.5 Chưa xác minh được

| Mục | Vì sao | Kiểm ở đâu |
|---|---|---|
| **Giá Lightsail snapshot $0.05/GB-month** | `get-bundles` không trả giá snapshot, không có API nào đọc được | Trang Lightsail pricing. Chiếm ~$10.80/6 tháng nên đáng kiểm |
| **Tên bucket `pgds-tfstate` còn trống** | Kiểm được chỉ bằng cách gọi tạo hoặc `head-bucket` | Lần `terraform apply` bootstrap đầu tiên sẽ báo nếu trùng |
| **Quota EC2 nếu cần instance test** | Standard On-Demand quota là **5 vCPU** — đủ 1 con t4g.small, không đủ dựng clean-room restore test song song bằng EC2 | Xin nâng trước nếu chọn cách test đó. Restore test mặc định dùng Lightsail instance mới nên không vướng |

---

## 9. Terraform

**Có.** Lý do — và vòng đời ephemeral là lý do **ủng hộ**, không phản đối:

1. **Teardown kiểm tra được.** `terraform destroy` là teardown đầy đủ. Xoá tay có thể để lại snapshot, static IP hoặc bucket tiếp tục phát sinh chi phí sau khi website đóng.
2. **Resize/rebuild trong lúc build.** Lightsail resize là migration work (mục 10). Chạy tay lúc 2h sáng Ngày 3 thì dễ sai; trong Terraform là đường code lặp lại được.
3. **Surface đã nhỏ.** Cloudflare thay Route 53 + CloudFront + ACM + OAC, nên phần AWS còn lại chỉ ~100–150 dòng.

### 9.1 State — S3, không DynamoDB

S3 backend hỗ trợ locking qua **`use_lockfile = true`**; **DynamoDB-based locking đã deprecated**. State + lock đều trong S3, không cần service nào thêm. Bucket S3 đã có sẵn cho DB dump. **Chi phí biên ~$0.**

```hcl
terraform {
  backend "s3" {
    bucket       = "pgds-tfstate"
    key          = "prod/terraform.tfstate"
    region       = "ap-southeast-1"
    encrypt      = true
    use_lockfile = true
  }
}
```

### 9.2 Chia stack

**Bootstrap** (apply 1 lần, `prevent_destroy`):
- S3 bucket state (versioning ON)
- S3 bucket backup (versioning ON)

Tách riêng để `destroy` stack chính không xoá mất backup và state của chính nó.

**Main:**
- `aws_lightsail_instance` — `bundle_id = "small_3_0"`, `blueprint_id = "ubuntu_24_04"` (cả hai đã xác minh `isActive` ở `ap-southeast-1`, mục 8.2)
- `aws_lightsail_static_ip` + `aws_lightsail_static_ip_attachment`
- `aws_lightsail_instance_public_ports`
- IAM user + policy: SES send, S3 backup write
- SES domain identity + DKIM

**Không đưa vào Terraform:**
- **Snapshot** — do cron/tay tạo. Đưa vào TF thì mỗi plan báo drift.
- **Toàn bộ tầng WordPress** — install, theme, plugin, content. `user_data` bootstrap LEMP thì được; đừng cố làm tầng WP declarative trong 3 ngày.

**Cloudflare provider:** tuỳ. DNS chỉ vài record, nhưng vì nó critical lúc cutover nên quản trong TF cũng hợp lý.

*Đọc registry provider khi viết module thật* để chốt argument của `aws_lightsail_instance` (`bundle_id`, `blueprint_id`, `ip_address_type`...).

---

## 10. Vận hành

### 10.1 Resize — là migration, không phải toggle

Lightsail resize **không** in-place. Quy trình thật:

1. Snapshot instance hiện tại
2. Tạo instance mới từ snapshot với bundle lớn hơn
3. Detach static IP khỏi instance cũ → attach vào instance mới
4. Verify: WP load, DB connect, media hiển thị
5. Cutover, xoá instance cũ

Không phải zero-risk autoscaling toggle. Có downtime ngắn ở bước 3. Trong Terraform thì lặp lại được, nhưng vẫn là thao tác có rủi ro — chỉ làm khi cần và đã có snapshot.

### 10.2 Bảo mật

**Làm:**
- Cloudflare proxy ON → origin IP không public
- Lightsail firewall: chỉ 80/443 từ **Cloudflare IP ranges**, SSH 22 chỉ từ IP admin
- Nginx verify **secret header** do Cloudflare Transform Rule chèn → chặn direct-IP access
- SSH key-only, `PasswordAuthentication no`, fail2ban
- 2FA cho WP admin (plugin Two Factor)
- `DISALLOW_FILE_EDIT = true`
- HSTS, CSP
- Cloudflare Origin CA cert, SSL Full (strict)

**Credential:** IAM access key tĩnh trên instance (Lightsail không có instance role như EC2). Ghi rõ đây là điểm yếu đã biết. Bù lại: policy tối thiểu (SES send + S3 put vào prefix backup), key lưu ở `/root` mode 600, rotate nếu nghi ngờ lộ.

### 10.3 Exit plan — bắt buộc có

Site ephemeral thì cuối vòng đời phải quyết định 2.000 bài đi đâu. Không có exit plan thì hoặc mất dữ liệu, hoặc hạ tầng chạy tiếp trả tiền vô ích.

**Thứ tự (export TRƯỚC destroy):**

1. WXR export (`wp export`) + `mysqldump` → tải về local + upload S3
2. Tarball toàn bộ `wp-content/uploads` → tải về local
3. *(Tuỳ chọn)* static export toàn site bằng crawl, nếu cần giữ URL đọc được sau khi tắt
4. Verify các bản export mở được
5. `terraform destroy`
6. Xoá snapshot còn lại (không nằm trong TF state)
7. Xoá bucket backup (bootstrap stack, có `prevent_destroy` — phải bỏ flag trước)

Ghi vào `RUNBOOK.md` kèm **ngày dự kiến decommission** và **người chịu trách nhiệm**.

---

## 11. Lịch hạ tầng — Ngày 1

| Việc | Ước tính |
|---|---|
| Bootstrap TF (2 bucket) + main stack apply | 1.5h |
| Cài LEMP + PHP 8.3 + MariaDB + Redis, tuning mục 4.1 | 1.5h |
| WordPress core + 4 plugin + `wp-config.php` hardening | 1h |
| Cloudflare: zone, DNS, proxy, SSL Full strict, origin cert, 2 Cache Rule | 1h |
| SES: domain identity, DKIM, verify, test mail | 0.5h |
| Backup: cron snapshot + cron DB dump → S3 | 0.5h |
| Lightsail alarm + cron script RAM/disk | 0.5h |
| Firewall + secret header + fail2ban | 0.5h |
| **Tổng** | **~7h** |

**Kín cho 1 ngày.** Các phụ thuộc ngoài kiểm soát — tất cả phải xử ở **D0**, không để sang Ngày 1:

- **SES production access:** account đang ở sandbox (`ProductionAccessEnabled: false`, quota 200 mail/24h, chỉ gửi tới địa chỉ đã verify). Request mất **24h+** → đây là item D0 duy nhất còn nguy cơ trượt lịch.
- **DNS delegation:** nameserver phải trỏ về Cloudflare. Propagation có thể mất vài giờ → làm trước Ngày 1 nếu domain đang ở nhà cung cấp khác.
- **SES domain identity + DKIM:** `list-email-identities` đang rỗng. DKIM cần thêm DNS record ở Cloudflare nên phải làm **sau** khi nameserver đã trỏ về Cloudflare.
- **Đo tổng dung lượng media:** quyết bundle 60GB hay 80GB.
- **Sửa budget zero-spend $1/tháng đang ALARM** (mục 8.4) trước khi Lightsail chạy.

---

## 12. Rủi ro

| Rủi ro | Mức | Xử lý |
|---|---|---|
| SES sandbox chưa được nâng kịp | **Cao** | Request production access ở D0 — mất 24h+, là item chặn duy nhất còn lại |
| Budget zero-spend $1/tháng kêu liên tục → alert fatigue → tắt luôn guardrail thật | Trung bình | Sửa hoặc xoá khi tạo alert $50/$85 |
| DNS propagation chậm ở Ngày 1 | Trung bình | Delegate nameserver về Cloudflare trước Ngày 1 |
| SPOF một instance | Trung bình | **Chấp nhận tường minh.** RTO 30–60 phút |
| Xử lý ảnh đẩy 2GB vào swap | Trung bình | `nice` + giảm `pm.max_children`; tạm nâng 4GB |
| IAM key tĩnh trên instance | Thấp | Policy tối thiểu, mode 600, rotate khi nghi lộ |
| Media vượt 60GB disk | Thấp | Đo tổng dung lượng ở D0. Nếu vượt → 4GB bundle (80GB) hoặc R2 |
| Cloudflare ToS 2.8 (non-HTML content) | Thấp | Ảnh qua CDN là usage bình thường; không đẩy video lên |
| Không dùng được SCP trên account standalone | Thấp | Ghi giới hạn vào runbook |
