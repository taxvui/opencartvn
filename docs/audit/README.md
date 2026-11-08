# Audit kỹ thuật opencartvn

**P0-C hiện hành:** đã có patch frontend CSRF và kiểm thử pipeline/UI; chưa đổi admin/API/guest auth CSRF. Xem [biên bản P0-C](../improvements/P0-C-frontend-csrf.md). Chưa deploy hoặc nghiệm thu production.

**P0-B hiện hành:** các đường tạo đơn không proof đã được chặn local; PayPal hiện unavailable, manual capture chưa được hỗ trợ như Paid. Xem [biên bản và giới hạn](../improvements/P0-B-payment-confirmation.md). Chưa nghiệm thu sandbox, SEC-01/PAY-03/DB-01 vẫn mở.

**Tiến độ sau audit:** PAY-02 đã có patch tối thiểu loại bỏ tạo đơn qua trang success và test local; chờ nghiệm thu, chưa đóng mọi tiêu chí của finding. PAY-01/SEC-01 vẫn OPEN. Xem [kế hoạch và biên bản nhóm P0-A](../improvements/P0-A-result-pages.md). Nội dung audit bên dưới giữ bằng chứng trước sửa.

**Đánh giá tổng thể: 4,5/10 theo rubric định tính, chưa sẵn sàng nghiệm thu an toàn cho paid automation dựa trên source hiện tại.** Không có kết luận về production đã bị khai thác hoặc tải thực tế. Phạm vi là commit `7fcbe3b27bb7ac04e10895f9d5a64fa964659fbe` và tài liệu `docs/architecture/`; repository là marketplace extension số, không phải cửa hàng vật lý đầy đủ.

Audit này **không sửa source/config/lockfile, không commit, không migration, không giao dịch hay thao tác DB**. Chỉ bổ sung tài liệu. Một câu tổng quát sai trong tài liệu kiến trúc cũ được sửa: schema có **58 bảng MyISAM và `oc_session` InnoDB**, không phải toàn bộ 59 bảng MyISAM. Các bảng order/ledger liên quan vẫn là MyISAM theo DDL. Thay đổi này chỉ là đính chính tài liệu và script sinh tài liệu.

## Kết quả và giới hạn

27 finding OPEN, có file/dòng, phân loại chứng cứ, nguyên nhân, điều kiện/hậu quả, cách sửa dự kiến, effort, compatibility và test/nghiệm thu riêng. Xác minh bằng source không đồng nghĩa đã khai thác runtime. Đã đối chiếu hash 3.590 file gốc, DDL và mọi vị trí trích dẫn; không chạy business tests, benchmark hoặc vulnerability advisory scan. Inventory bao quát source; phân tích sâu tập trung trust/money boundaries, không tuyên bố mọi branch/vendor đều đã kiểm toán ngữ nghĩa.

Plugin draw.io không có công cụ gọi trong phiên. Năm sơ đồ bổ sung là mxGraph XML tạo trực tiếp, kiểm tra ID/edge/geometry; chưa kiểm chứng mở–sửa–lưu bằng editor/plugin draw.io. Sơ đồ mục tiêu và roadmap được ghi rõ **đề xuất**, không thể hiện như thành phần đã triển khai.

## Điểm mạnh

- Tổ chức MVC-L frontend/admin, bootstrap và hook truy vết được: [02](02-architecture-review.md), `docs/architecture/03-application-logic.drawio`.
- Catalog số/member/seller/order và SQL seed có cấu trúc domain rõ: [06](06-database-audit.md).
- Có DB/Template/Cache adapters và PaymentGateway/CloudStorage để tạo seam test: [02](02-architecture-review.md).
- Admin có login/access/modify gate; không đánh đồng mọi endpoint thiếu authorization: [04](04-security-audit.md).
- Có kiểm tra/cast/escape trong nhiều query, Request cleaning và upload basename; không suy ra SQLi/RCE chỉ từ code PHP cũ: [04](04-security-audit.md).

## Điểm yếu chính

Nhiều đường hoàn tất đơn và tiền chưa được ràng buộc bằng proof/idempotency; CSRF token frontend bị ghi đè; TLS client verification tắt; password legacy; schema tài chính không atomic; dependency/build drift; regression/CI artifact thiếu. Xem các finding dưới đây, không dùng điểm tổng để giảm mức P0.

## 10 vấn đề và 10 đề xuất quan trọng nhất

| # | Vấn đề | Đề xuất ưu tiên |
|---|---|---|
| 1 | [PAY-01](findings/PAY-01.md): Complete không cần payment proof ở một số nhánh | Một completion boundary xác thực gateway/amount/currency/merchant/order |
| 2 | [PAY-02](findings/PAY-02.md): success tạo đơn từ pending | Trang success chỉ đọc; state transition phía server |
| 3 | [SEC-01](findings/SEC-01.md): ghi đè token client | CSRF middleware, token POST, không mutation GET |
| 4 | [SEC-02](findings/SEC-02.md): TLS verification tắt | Trust CA chuẩn, verify peer/host, fail closed |
| 5 | [SEC-03](findings/SEC-03.md): SHA1/MD5 | Rehash sau login thành công, giữ tương thích legacy có thời hạn |
| 6 | [SEC-04](findings/SEC-04.md): member attempt không chặn | Account/IP throttling, TTL và alert |
| 7 | [DB-01](findings/DB-01.md): order/sales/commission không atomic | Rehearsal InnoDB boundary + transaction + outbox |
| 8 | [PAY-03](findings/PAY-03.md): nguy cơ charge/order lặp | Durable intent + provider idempotency + unique event binding |
| 9 | [QUAL-01](findings/QUAL-01.md): model target vắng | Route characterization rồi sửa dependency đúng domain |
| 10 | [OPS-02](findings/OPS-02.md), [OPS-01](findings/OPS-01.md): regression/build chưa đáng tin | Clean-checkout CI, frozen dependencies, gateway mocks và DB disposable |

## Những việc cần làm ngay

Trong coding/operations task riêng: chủ hệ thống xác nhận exposure các route completion và chính sách tạm ngăn cấp đơn không proof; chốt một payment state machine; viết tests failure/cancel/retry trước sửa; khôi phục token/TLS controls; inventory các giao dịch cần reconciliation bằng truy vấn được duyệt trên bản sao. Không tự thu hồi đơn/quyền hoặc cập nhật dữ liệu lịch sử chỉ dựa audit. Các thao tác này chưa được thực hiện.

## Roadmap và nguồn lực

[Roadmap 6–12 tháng](10-development-roadmap.md): stabilization tuần 1–6; modernization tháng 2–4; expansion tháng 5–8; intelligent commerce có ROI tháng 9–12. Năng lực đề xuất: 2 PHP FTE, QA 0,5–1, DevOps 0,25–0,5, DBA/Security part-time, PO 0,25; estimate cần refine sau characterization. Không cộng effort các finding vì có overlap service/test/migration.

Nếu không cải tiến, các rủi ro chính là cấp entitlement/commission trước thu tiền, ghi tài chính dở dang hoặc lặp khi retry, auth abuse và regressions khó phát hiện. Đây là kịch bản có điều kiện từ code, không phải thống kê sự cố đã xảy ra.

Dài hạn giữ **modular monolith**, trích use-case/ports tại money/auth boundaries, DB transaction và outbox, API adapter dùng chung policy. Chưa có căn cứ kinh doanh/scale để microservices. Chỉ mở cart/shipping/tax/ERP hay AI theo phạm vi và dữ liệu đã xác nhận.

## Danh mục báo cáo

1. [Executive summary](01-executive-summary.md)
2. [Architecture review và điểm 10 tiêu chí](02-architecture-review.md)
3. [Code quality](03-code-quality-audit.md)
4. [Security coverage và phân loại](04-security-audit.md)
5. [Performance và kế hoạch đo](05-performance-audit.md)
6. [Database](06-database-audit.md)
7. [Business logic](07-business-logic-audit.md)
8. [Testing / DevOps](08-testing-devops-audit.md)
9. [Phương án sửa từng finding](09-improvement-proposals.md)
10. [Roadmap và tính khả thi Intelligent Commerce](10-development-roadmap.md)
11. [Risk register](11-risk-register.md)

[Registry JSON](evidence/findings.json), [kiểm tra offline](evidence/checks.json), [kiểm tra artifact](evidence/artifact-validation.json). Tài liệu kiến trúc và inventories gốc nằm tại [docs/architecture](../architecture/README.md).

Sơ đồ: [hiện tại](diagrams/current-architecture.drawio), [mục tiêu đề xuất](diagrams/proposed-architecture.drawio), [dependency problems](diagrams/dependency-problems.drawio), [roadmap](diagrams/improvement-roadmap.drawio), [ưu tiên rủi ro](diagrams/risk-priority.drawio).

## Khả năng lặp lại

`python3 docs/audit/tools/build_report.py` đối chiếu source/hash/DDL và tạo finding/reports; `python3 docs/audit/tools/diagrams_validate.py` tạo sơ đồ và kiểm link/artifact. Không gọi ứng dụng/DB. README và roadmap là nội dung biên tập, không bị script ghi đè. Scan advisory/live tests cần tác vụ xác minh riêng, được ghi “chưa chạy” thay vì báo pass.
