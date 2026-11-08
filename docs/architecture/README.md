# Kiến trúc và logic thực thi opencartvn

**Cập nhật P0-C:** xem [current-frontend-csrf.md](current-frontend-csrf.md) cho pre-action/token flow hiện hành. Diagram baseline trước sửa vẫn được giữ để đối chiếu.

**Cập nhật P0-B:** xem [current-payment-confirmation.md](current-payment-confirmation.md) cho luồng confirm/gateway/legacy hiện hành; sơ đồ/inventory baseline bên dưới vẫn là chứng cứ trước sửa.

**Cập nhật working tree P0-A:** success result page đã bỏ tạo đơn. Các sơ đồ/inventory bên dưới giữ snapshot trước sửa; trạng thái hiện hành được mô tả tại [current-payment-result.md](current-payment-result.md).

Bộ tài liệu đối chiếu source tại commit `7fcbe3b27bb7ac04e10895f9d5a64fa964659fbe` của `taxvui/opencartvn`, nhánh checkout `master`. Chỉ thêm tài liệu và công cụ dựng tài liệu trong thư mục này; không sửa mã nghiệp vụ, cấu hình ứng dụng hoặc dữ liệu.

## Phạm vi và mức độ xác minh

Inventory đọc và băm toàn bộ **3.590 file tracked**, quét **1.548 file PHP** (435 ngoài vendor), ghi nhận **166 controller** và **73 model**, bao gồm installer. Các entrypoint, engine, pre-action, auth, marketplace, checkout, payment, order, admin CRUD và DDL được lần theo mã thực tế. [Bản đồ source](source-map.md), [route candidate](routes.md) và [CSV chứng cứ](evidence/) cung cấp phạm vi đầy đủ để tra cứu. Inventory toàn repository không đồng nghĩa đã kiểm thử hoặc thẩm định thủ công mọi nhánh của 1.548 file PHP và thư viện bên thứ ba.

**Giới hạn công cụ:** phiên làm việc không cung cấp công cụ/plugin draw.io có thể gọi. Các file `.drawio` được tạo trực tiếp bằng mxGraph XML không nén, có node/edge/geometry riêng để chỉnh sửa. PNG được render từ SVG dùng chung dữ liệu và tọa độ bằng Chromium; **không phải export từ plugin draw.io**. Đã kiểm tra XML, ID, edge endpoint, dẫn chiếu source, toàn bộ PNG và xem trực quan các contact sheet/trang đại diện. **Chưa xác minh vòng mở → sửa → lưu bằng chính draw.io.** Không coi kiểm tra XML là bằng chứng editor round-trip đã thành công.

## Tổng quan kiến trúc thực tế

Đây là marketplace bán extension/theme và cổng member/seller/cloud/CMS. Không áp kiến trúc cửa hàng OpenCart tiêu chuẩn vào checkout này:

- Hàng hóa số: `oc_extension` và `oc_extension_category`.
- Người mua, người bán, đối tác: `oc_member`, `oc_member_seller`, `oc_member_partner`.
- Đơn `oc_order` gắn trực tiếp một `extension_id`, có quantity và giá snapshot. Không có `oc_order_product` hoặc `oc_cart` trong SQL seed.
- `system/library/cart/` có `Member`, `User`, `Currency`; không có class giỏ hàng `Cart`.
- Có các route marketplace/checkout mua trực tiếp; không tìm thấy module vận chuyển, tracking shipment hoặc giao diện quản lý sản phẩm vật lý `catalog/product`.

## Các tầng và thành phần

| Tầng | Thành phần có bằng chứng |
|---|---|
| Client | HTML, Twig-rendered pages, jQuery/AJAX, các SDK thanh toán JS được template tham chiếu |
| Web | `.htaccess`: sitemap, robots, SEO `_route_`, static assets; Apache là runtime dev đã chuẩn bị, không khẳng định deployment production |
| Entry / bootstrap | `index.php`, `admin/index.php`, `install/index.php`, config mỗi app; `system/startup.php::start()` → `system/framework.php` |
| Kernel / DI | `Registry` giữ services; `Controller`/`Model` truy cập registry bằng magic property |
| Routing | `Router::dispatch()` chạy pre-actions; `startup/router` chọn route; `Action` tìm file và method bằng route |
| Presentation | Controller + Loader + Template/Twig; `Language`, `event/language`, DB translations |
| Business logic | Nằm trong controller, model và library `PaymentGateway`, `CloudStorage`, `Cart\Member`, `Cart\User`; không có tầng service bắt buộc riêng |
| Data access | Model viết SQL qua `DB` và `DB\MySQLi`; một số controller như dashboard query DB trực tiếp |
| State | `Cache\File` mặc định; session mặc định `Session\DB` trong `oc_session`; có adapter khác trên đĩa nhưng không coi đã bật |
| Extension/hooks | Config `action_event`, bảng `oc_event`, Loader before/after hooks, `modification()` lookup override; marketplace listing là sản phẩm số, không tự động là plugin được cài |
| External | Stripe HTTP API, Braintree GraphQL, PayPal OAuth/JS; Mail/SMTP; CloudStorage Wasabi/R2/S3/GCS theo cấu hình |

[Danh mục package và phiên bản](third-party.md) đối chiếu composer.lock với metadata bundled. Các package vendor được commit sẵn trong `system/storage/vendor`; bootstrap load `vendor/autoload.php`. Composer manifest còn cấu hình `upload/system/storage/vendor`, khác layout checkout. `build.xml` cũng trỏ `upload/` và `tests/` không có. Đây là sai lệch cấu hình cần lưu ý, không phải kiến trúc ứng dụng đang chạy.

## Luồng request và khác biệt frontend/admin

Frontend đi từ `index.php` → config → startup → framework. Framework khởi tạo Registry, Config, Log, Event, Loader, Request/Response, DB, Session object, Cache và các thành phần chung. `session_autostart=false` ở frontend: pre-action `startup/session` mới start session/cookie. Thứ tự tiếp theo là `startup/startup` → `error` → `event` → `maintenance` → `seo_url`. Startup nạp setting, language, member, PIN/IP gate và currency. Sau đó `startup/router` phát controller-before, thực thi `Action`, phát controller-after. Controller gọi model/library, render view, setOutput; framework gọi `Response::output()`. Session ghi lại lúc shutdown qua `Session::close()`.

Admin khởi tạo session ngay trong framework. Pre-actions theo thứ tự `startup/startup` → `error` → `event` → `sass` → `login` → `permission`. `startup/login` tạo `Cart\User`, kiểm tra session và `user_token`; `startup/permission` kiểm tra quyền `access`. Nếu pre-action trả một `Action` khác, Router dừng vòng pre-actions và chuyển sang action đó. Các endpoint ghi dữ liệu như edit extension còn kiểm tra `modify` trong controller. Dashboard nằm trong ignore của permission gate, nhưng vẫn chịu login/token gate.

`Action` phân giải route dựa trên file controller có thật, không dùng một route registry cố định. [routes.md](routes.md) là candidate từ declaration; không khẳng định mọi method public đều qua được toàn bộ gate.

## Module và luồng nghiệp vụ

| Yêu cầu nghiệp vụ | Luồng có thật / giới hạn |
|---|---|
| Trang chủ | `common/home::index()` → footer/header → `common/home.twig`; không load model riêng trong controller này |
| Danh mục, search | Cùng `marketplace/extension::index()`, truyền filter vào `getTotalExtensions()` / `getExtensions()` |
| Chi tiết | `marketplace/extension/info`, extension/category/member, ảnh/download/rating; comment AJAX riêng |
| Thêm giỏ hàng | Không có triển khai được tìm thấy; nút Buy mở `checkout/checkout` với extension_id và quantity |
| Đăng ký | `account/register` → `account/member::addMember()` → mail/register → success; email verification ở route riêng |
| Đăng nhập | `account/login::validate()` → member lookup → `Cart\Member::login()` → token/IP/PIN gate; có magic-link branch riêng |
| Tài khoản | account/profile, payment vault, seller, partner, extension management, downloads, order, subscription, transaction |
| Checkout | AJAX modal, gate member/token, đọc extension/payment vault, chọn card/PayPal, prepare pending order, confirm |
| Tạo đơn | `ModelAccountOrder::addOrder()` → INSERT order → tăng sales nếu complete → seller commission transaction nếu có → mail/order |
| Theo dõi đơn | account/order list/info, invoice/status và download entitlement; không thấy tracking vận chuyển |
| Admin | Dashboard; marketplace extension/category/comment/promotion; member/seller/partner; sale/order; CMS; settings/payment/store; cloud/subscription; users/permissions; localisation; tools |
| Report/statistics | Dashboard query KPI trực tiếp; có `event/statistics` nhưng tham chiếu model `setting/statistics` không có. Không xác minh được một report module hoàn chỉnh |
| Cloud | Subscription/plan/domain/history/transaction/backup metadata trong SQL. Chưa thấy bằng chứng đủ để khẳng định provision server/store hoặc backup hạ tầng thật |

### Các nhánh thanh toán phải phân biệt

Trong `checkout/payment::confirm()`, khi có pending Stripe/Braintree, code gọi `PaymentGateway::chargeStripe()`/`chargeBraintree()` trước khi `addOrder()`, chỉ tiếp tục khi không có lỗi trả về. Tuy nhiên còn các nhánh có thật khác:

- Không có pending: `confirm()` lấy extension rồi `addOrder()` trực tiếp, tự tạo transaction token.
- PayPal pending do `paypal()` tạo không có payment_gateway Stripe/Braintree; không đi vào hai lời gọi charge trên.
- `checkout/checkout::success()` cũng tạo đơn nếu session còn pending và member đã đăng nhập; method này không gọi gateway để xác minh capture.
- `marketplace/braintree::payment()` và `marketplace/paypal::send()` là các endpoint khác có lời gọi tạo đơn trực tiếp; callback PayPal chỉ redirect success.

Sơ đồ phản ánh đúng các nhánh này, **không khẳng định payment đã được xác nhận server ở mọi đường đi**. Chưa gửi giao dịch thật hoặc kiểm thử credential gateway; không sửa các hành vi phát hiện được.

## Database và cardinality

[database-schema.md](database-schema.md) ghi đầy đủ 59 bảng, mọi cột, kiểu, PK và index theo `install/opencart.sql`; [schema.json](evidence/schema.json) là bản máy đọc được. SQL seed dùng MyISAM và không khai báo FK. Quan hệ vẽ trong ER là quan hệ logic có JOIN/lookup/ghi ID trong source, được gắn bằng chứng riêng. Không thêm FK hoặc dùng hậu tố `_id` làm bằng chứng đủ.

Không khẳng định member/seller là quan hệ 1:1 được database bảo đảm: cột member_id phía seller không UNIQUE. N:N extension download ↔ release version có bảng nối `oc_extension_download_path` với PK kép và code đọc/ghi tương ứng. `oc_order_status` có PK `(order_status_id,language_id)`, vì vậy lookup status cần cả language. Các bảng nối có trong DDL nhưng chưa có bằng chứng được dùng trên một luồng không được tự nối vào luồng đó.

## Dependency, event và extension

`08-module-dependencies.drawio` chia theo app/domain. Mỗi hàng controller → model ứng với literal `load->model` cụ thể; model → bảng là tập tham chiếu SQL **cấp file**, không khẳng định method đang vẽ truy cập mọi bảng. [model-calls.csv](evidence/model-calls.csv) giữ lời gọi method cụ thể để đối chiếu tiếp. Controller/model có thể cùng dùng Registry và model có thể gọi mail controller, nên không ép mô hình phân lớp nghiêm ngặt một chiều.

Event config được register trong framework. Event từ DB được nạp sau đó: frontend lọc `application/*`, admin lọc `admin/*`, bỏ prefix trước register. Seed không có INSERT vào `oc_event`: chỉ chứng minh cơ chế có, không chứng minh DB hook cụ thể nào bật mặc định. Config admin khai báo hai lần khóa `view/*/before`; PHP giữ mục sau, nên `event/theme` trong mục trước không được đăng ký qua cấu hình này. `modification()` tìm file override, nhưng chưa xác minh pipeline install/refresh OCMOD đầy đủ.

## Danh sách sơ đồ

| File | Nội dung | Trang |
|---|---|---|
| [01-project-structure.drawio](01-project-structure.drawio) | Gốc, frontend/admin MVC-L, kernel và vendor | 4 |
| [02-system-architecture.drawio](02-system-architecture.drawio) | Các tầng, state, hooks, external services | 2 |
| [03-application-logic.drawio](03-application-logic.drawio) | Bootstrap frontend/admin, routing, event/localization | 3 |
| [04-frontend-flow.drawio](04-frontend-flow.drawio) | Browse/search/detail, account, checkout và nhánh khác | 4 |
| [05-admin-flow.drawio](05-admin-flow.drawio) | Auth, quyền, CRUD và danh mục controller | Xem index |
| [06-sequence-diagrams.drawio](06-sequence-diagrams.drawio) | Request, member login, checkout, order, payment, admin edit; ghi rõ cart không có | 7 |
| [07-database-er.drawio](07-database-er.drawio) | Quan hệ logic có chứng cứ và từ điển đủ 59 bảng | 18 |
| [08-module-dependencies.drawio](08-module-dependencies.drawio) | Controller → model → bảng và thư viện/hook dùng chung | Xem index |

Tổng cộng **8 file / 91 trang**. [diagram-index.md](diagram-index.md) liên kết từng trang tới PNG và Mermaid. PNG trang đầu giữ đúng tên yêu cầu; các trang sau có hậu tố `-page-NN`. Các file `*-contact-sheet.png` dùng để xem toàn cảnh, PNG gốc giữ kích thước đầy đủ.

## Kiểm tra, tái tạo và phần chưa xác minh

Các công cụ trong `tools/` chỉ đọc source gốc và ghi tài liệu trong `docs/architecture/`:

```bash
python3 docs/architecture/tools/inventory.py
python3 docs/architecture/tools/build_diagrams.py
python3 docs/architecture/tools/build_docs.py
python3 docs/architecture/tools/render_validate.py
python3 docs/architecture/tools/verify.py
```

Inventory dựa trên `git ls-files`: khi docs đã được stage/commit, script loại `docs/architecture/` để thống kê source không tự bao gồm tài liệu sinh ra. Render cần Python Playwright, Pillow và Chromium; môi trường hiện tại có sẵn. Không cần dịch vụ payment hoặc database để dựng tài liệu.

`evidence/validation.json` ghi kiểm tra XML/endpoint và PNG; `evidence/verification.json` ghi kết quả đối chiếu cuối. Việc decode PNG, có nội dung và đúng kích thước không tự chứng minh mọi label đều dễ đọc; đã rà soát thêm contact sheet và trang đại diện, chỉnh đường nối chồng vào node ở sơ đồ kiến trúc. Source URL/method được validator kiểm tra, nhưng extractor tĩnh không giải quyết mọi biến route, SQL ghép động hoặc hook từ DB production.

Chưa xác minh: mở/sửa/lưu bằng plugin/editor draw.io; toàn bộ workflow có side effect ở runtime; giao dịch gateway live; email delivery; object storage thực; cloud provisioning; các model thiếu được liệt kê trong source-map; khác biệt schema production so với SQL seed. Những giới hạn này không được biểu diễn thành thành phần hay kết nối đã hoạt động.
