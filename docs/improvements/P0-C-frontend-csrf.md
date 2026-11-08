# P0-C — SEC-01: token request bất biến và chặn mutation trước controller

Trạng thái: **đã triển khai và kiểm tra local, chờ review/nghiệm thu**. Phạm vi riêng frontend CSRF; không thêm payment gateway, sửa ledger/idempotency, database hoặc admin authentication.

## Đã giải quyết

`startup/startup` không còn gán token session vào `$request->get['member_token']`. Token dùng tạo link vẫn lấy từ session, nhưng không thể trở thành bằng chứng giả rằng client đã gửi token. Middleware mới `startup/csrf` chạy sau SEO resolution và trước dispatch nghiệp vụ. Nó so sánh token bằng `hash_equals`, kiểm tra kiểu chuỗi và từ chối token thiếu/sai/array với HTTP 403.

Mọi request không phải GET/HEAD/OPTIONS của member đã đăng nhập đều cần token. Các action ghi đã rà soát trong `csrf_post_routes` chỉ cho POST, kể cả khi URL dùng chữ hoa method, nhiều slash hoặc suffix được Action chấp nhận. GET/HEAD trả 405 + Allow: POST và không đi vào controller nghiệp vụ. Guest POST vào các action yêu cầu member bị 403; đăng nhập/đăng ký public không bị buộc có member token trước khi có session member.

Danh sách method được đối chiếu file/method thực tế, không dựa vào từ khóa “delete” đoán toàn repository. Các POST khác của member cũng chịu kiểm tra token dù không có trong danh sách method-only. Đây không phải tuyên bố mọi guest endpoint hoặc future GET mutation đều đã có CSRF policy.

## Tương thích giao diện

- Giữ `member_token` query parameter hiện hữu trong giai đoạn chuyển tiếp; không âm thầm đổi mọi controller sang token POST-body/header.
- Header xuất token riêng cho member đăng nhập và một policy JSON dùng chung với backend, đều escape HTML attribute. Helper `csrf.js` chỉ áp dụng đúng origin và đường dẫn app `index.php`; không thêm token vào external URL, admin URL hoặc public GET.
- Helper bổ sung token bị thiếu cho AJAX do chính trang app khởi tạo; **không ghi đè token sai được caller gửi rõ**. AJAX GET cũ của action ghi chuyển sang POST, giữ URL IDs và request data. Browser ở origin khác không có helper/token này.
- Các link điều hướng ghi đã rà soát (xóa store/extension/showcase/domain, point/unpoint domain, logout) được submit POST; giữ confirmation cho thao tác phá hủy, logout không thêm confirmation. JSON endpoints vẫn dùng callback AJAX hiện có.
- Nút Delete extension chuyển sang submit/`formaction`, có fallback POST ngay cả nếu helper không chạy. Các anchor legacy không có JavaScript có thể nhận 405 thay vì thực hiện GET mutation; đây là giới hạn an toàn có chủ đích.
- Một số trang danh sách private chỉ đọc được canonical redirect khi deep link thiếu token. Redirect tạo URL mới, không sửa request; không replay POST hoặc GET mutation. Token sai không được middleware thay bằng token session.
- PIN-reset AJAX cũ cũng chuyển từ GET sang POST; GET không còn gửi mail/reset PIN. Các gate PIN/IP giữ nguyên; verify/reset/security confirmation dùng capability link vẫn theo logic hiện hữu. Chưa audit lại độ mạnh/TTL của capability.

## File thay đổi

| File | Lý do |
|---|---|
| `application/controller/startup/startup.php` | Bỏ ghi đè input token |
| `application/controller/startup/csrf.php` | Pre-action kiểm token/method, resolve route giống Action, canonical read-only links |
| `application/controller/error/csrf.php` | 403/405 JSON không phản chiếu token, no-store |
| `system/config/application.php` | Đăng ký pre-action và policy routes dùng chung |
| `application/controller/common/header.php` | Xuất policy và token theo identity |
| `application/view/template/common/header.twig` | Meta escaped và nạp helper |
| `application/view/javascript/csrf.js` | Tương thích AJAX/link POST đúng origin/app path |
| `application/view/template/account/extension_list.twig` | Delete bằng POST/formaction |
| `tests/regression/frontend_csrf.php` | Startup/Cart Member/Router/Action/controller thật, services spies |
| `tests/regression/frontend_csrf_client.js` | Client policy, token không bị ghi đè/lộ ra ngoài, navigation/cancel/logout |
| `tests/regression/frontend_csrf_browser.py` | Chromium + jQuery thực, mọi HTTP intercept, không ghi ứng dụng |
| Tài liệu audit/improvements/architecture và README test | Ghi trạng thái, bằng chứng, giới hạn và quy trình kiểm tra |

## Kiểm thử

**Trước sửa:** bộ test đầu 35 case có 25 failure, trong đó case cấu hình thiếu fixture DB constant (đã chỉnh fixture); các case thiếu/sai token ghi model spy và GET/HEAD mutation đều tái hiện lỗi bằng pipeline startup/controller thật. Không coi lỗi fixture là vulnerability. Không chạm dữ liệu DB trong suite.

**Sau sửa:**

- **39 case PHP pass**: token đầu vào missing/wrong/array giữ nguyên; POST sai token trả 403; valid POST ghi đúng một model spy; GET/HEAD/route aliases không ghi; public reads, deep links, guest login và PIN/IP gates; policy file/method tồn tại; header thật render policy đúng.
- **14 case Node pass**: helper same-origin/subdirectory, AJAX conversion, giữ IDs/body, không sửa explicit bad token, không gửi token sang external/admin/webhook URL, public GET không token, navigation/cancel/logout.
- **4 case Chromium pass**: jQuery bundled thật chuyển AJAX GET mutation thành POST, thêm token cho signed-in POST, không gửi token sang origin khác, link delete submit POST sau confirm. Mọi request bị intercept; không dùng account/gateway thật.
- Regression thanh toán: **65 case payment/controller + 15 result-page + 8 callback JavaScript pass**.
- HTTP smoke storefront/admin login/marketplace/sitemap/CSS, các probe 403/405 và lint/diff-check: ghi trong `P0-C-verification.json`.

Lệnh từ repository root:

```sh
docker exec -u 1000 -w /var/www/html opencartvn-web php tests/regression/frontend_csrf.php
node tests/regression/frontend_csrf_client.js
python3 tests/regression/frontend_csrf_browser.py
```

PHP suite dùng Cart\Member, Url, Language, Router/Action, startup và extension/store destructive controllers thật với DB/model/response spies. Việc ghi IP/session trong bootstrap không phải mục tiêu assertion “không business write”; test kiểm controller nghiệp vụ không được thực thi khi bị chặn. Browser suite dùng Chromium/Playwright sẵn trong cloud, không phải phiên đăng nhập production hay browser E2E với DB thật.

## Giới hạn và rủi ro còn mở

1. Token query vẫn tồn tại để tương thích. Chưa migrate sang CSRF token riêng trong POST/header, chưa xử lý toàn bộ Referrer/log exposure hoặc XSS (SEC-06).
2. Policy method-only phải cập nhật khi thêm action ghi mới. Các mutation qua capability/public GET nằm ngoài member policy cần audit riêng; không tự phá verify/reset/unsubscribe flows.
3. Client helper chỉ parse URL app index.php có route rõ. Private mutation URL bị SEO rewrite tùy chỉnh có thể cần cập nhật caller để POST rõ; seed hiện tại không có SEO URL records. Backend vẫn check sau SEO, không mở GET mutation vì client không nhận ra alias.
4. Guest login CSRF, anti-spam public endpoints, API tokens, admin/user_token và session rotation không được tuyên bố đã giải quyết bởi nhóm này.
5. PAY-01 sandbox/PayPal capture, PAY-03 concurrent idempotency, DB-01 atomicity vẫn mở; không có giao dịch/migration thật trong đợt này.

Không tự chuyển sang P1 sau nhóm này. Cần reviewer nghiệm thu policy routes, UI và compatibility trước release; patch local pass không phải chứng nhận production security.

Review cuối bổ sung hai red→green: GET PIN reset trước đó chưa vào POST-only policy; redirect phục hồi deep-link dùng raw route. Cả hai đã có test và sửa: policy thêm reset, redirect chỉ dùng controller route đã resolve, không phản chiếu raw route/header characters.
