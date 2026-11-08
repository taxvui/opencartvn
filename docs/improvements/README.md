# Kế hoạch cải tiến theo nhóm có cổng nghiệm thu

Audit nền: `docs/audit/`, commit `7fcbe3b27bb7ac04e10895f9d5a64fa964659fbe`. Các finding là snapshot trước sửa; tiến độ hiện hành ghi tại đây. Không cộng effort các finding có chung boundary/test. Không tự động chuyển nhóm sau khi chỉ chạy test; cần review/nghiệm thu nhóm hiện tại.

| Nhóm / thứ tự | Phạm vi | Điều kiện nghiệm thu | Trạng thái |
|---|---|---|---|
| P0-A | PAY-02: bỏ tạo đơn từ result page, giữ route/template/links | Red→green; không write/consume pending qua success hoặc refresh; regression UI/401; đánh giá compatibility PayPal | Đã kiểm tra local; người dùng yêu cầu tiếp tục sang P0-B |
| P0-B | PAY-01: một boundary xác nhận tiền cho confirm và legacy; review PaymentGateway fallback | Mock missing credentials/error/requires_action/amount/currency/merchant mismatch không Paid; proof hợp lệ gắn đúng intent/member; legacy không bypass; sandbox staging | Đã chặn completion không proof local; PayPal unavailable, chờ review/sandbox |
| P0-C | SEC-01: token request bất biến, CSRF/HTTP method policy cho mutations | Token thiếu/sai bị từ chối; tests với pipeline startup thật, PIN/IP, frontend và legacy AJAX | Đã triển khai, kiểm tra local; chờ review/nghiệm thu |
| P1-A | SEC-02/08: TLS trust và runtime credentials/config | CA hợp lệ pass, untrusted fail; secrets không vào log; overlay/hosting backward compatibility | Chưa triển khai |
| P1-B | SEC-03/04/05/07: auth/hash/session/upload; tách từng subgroup | Legacy login + rehash, throttle TTL, session rotation/concurrency, quota/signature tests; từng subgroup nghiệm thu riêng | Chưa triển khai |
| P1-C | PAY-03 + DB-01: durable intent/idempotency, transaction/outbox | Fault injection, replay/concurrency, reconciliation; DDL rehearsal/backup/restore trước migration production; không destructive migration | Chưa triển khai |
| P1-D | QUAL-01 / OPS-01/02 / BIZ-01 | Reachability tests, reproducible install, CI suite, contract provisioning ngoài repo; chia subgroup riêng | Chưa triển khai |
| P2 | DB-02, QUAL-02/03/04, PERF-01..04, OPS-03/04, SEC-06/09 | Characterization trước refactor; EXPLAIN/benchmark trước index/cache; advisory version/range trước upgrade | Chưa triển khai |
| P3 | API/AI/CRM/ERP/omnichannel theo roadmap | Business case, data governance, baseline ROI và cost cap; không có finding P3 xác minh chỉ để lấp nhóm | Chưa triển khai |

Trong cùng P0, chọn PAY-02 trước vì đường lỗi nhỏ, có test xác định và không đòi hỏi schema/gateway protocol mới. PAY-01 vẫn là rủi ro tài chính lớn cần nhóm kế tiếp, không được coi đã đóng theo P0-A. Không gộp hardening hoặc refactor không liên quan vào bản sửa này.

## Quy trình mỗi nhóm

Đọc lại source + caller + template/schema → chốt phạm vi → test đỏ có ý nghĩa → sửa tối thiểu → test xanh + regression tương ứng → ghi changed files, compatibility, rủi ro → cập nhật kiến trúc hiện hành → review/nghiệm thu. Không sửa hoặc xóa chức năng legacy khi chưa trace caller; không khai thác môi trường thật. Nếu phát hiện phụ thuộc khác, ghi rõ và điều chỉnh nhóm trước khi sửa.

## Nhóm đã thực hiện

[P0-A — kết quả, test, compatibility và các file thay đổi](P0-A-result-pages.md).

[P0-B — kết quả và các giới hạn integration](P0-B-payment-confirmation.md). Trạng thái P0-C hiện hành xem biên bản riêng bên dưới.

Review tiếp trong cùng P0-B: 14 test proof sai kiểu được viết trước sửa, hiện suite provider/controller đạt 65/65. P0-B sandbox chưa chạy vì test credentials chưa có cấu hình; P0-C hiện được triển khai theo yêu cầu tiếp tục làm việc. Xem phần bổ sung của biên bản P0-B.

[P0-C — frontend CSRF](P0-C-frontend-csrf.md): triển khai sau yêu cầu tiếp tục làm việc; chưa chuyển nhóm P1.
