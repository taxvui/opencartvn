# SEC-01 — Startup ghi đè token request bằng token session

**P0-C đã triển khai local:** bỏ token overwrite, thêm CSRF pre-action và POST-only policy, cập nhật browser callers. Chờ review/nghiệm thu; token query và các giới hạn khác được ghi rõ trong [biên bản P0-C](../../improvements/P0-C-frontend-csrf.md). Bằng chứng dưới đây là baseline trước sửa.

- Ưu tiên: **P0**; mức độ tác động: **Critical** (đánh giá audit, không phải CVSS).
- Phân loại: **Đã xác minh bằng source**. “Đã xác minh” = chứng minh đường code/cấu hình, không đồng nghĩa đã khai thác runtime.
- Module: application.

## Bằng chứng

- [application/controller/startup/startup.php:137](../../../application/controller/startup/startup.php#L137) — `$this->request->get['member_token'] =`
- [application/controller/account/extension.php:150](../../../application/controller/account/extension.php#L150) — `public function delete`

## Mô tả và nguyên nhân

startup/startup chạy trước controller và thay member_token từ client bằng session token. So sánh token trong controller sau đó không chứng minh client đã gửi đúng token.

## Hậu quả và điều kiện

Cơ chế token chống CSRF ở frontend mất tác dụng phân biệt token thiếu/sai. Khả năng tấn công ngoài browser còn phụ thuộc cookie/SameSite/deployment.

## Phương án khắc phục (chưa thực hiện)

Không ghi đè input; dùng CSRF middleware kiểm tra token POST và hash_equals, tách token URL/session khỏi chống CSRF. Đổi mutation GET sang POST với chuyển tiếp an toàn.

Các file trong bằng chứng là điểm thay đổi dự kiến; service/policy/outbox mới chỉ là thiết kế đề xuất. Thay đổi bổ sung ở controller caller, model hoặc schema phải có review boundary riêng.

## Tương thích và rollout

Thay đổi GET mutation/token URL có tác động client và bookmark; phối hợp frontend, không silently phá legacy.

## Kiểm thử và tiêu chí nghiệm thu

Request ghi với token thiếu/sai bị 403; token đúng đi tiếp; GET không ghi; kiểm thử flow PIN/IP và deep link để tránh khóa người dùng.

Đây là các test đề xuất, **chưa được thực thi** trong audit. Nghiệm thu khi assertions nêu trên đạt trên staging disposable và regression legacy được reviewer duyệt.

## Công sức / chủ trì

5–8 ngày công gồm triển khai + kiểm thử cục bộ; chưa gồm chờ stakeholder, kiểm thử toàn hệ thống hoặc cutover production. Chủ trì PHP; Security review SEC/PAY; DBA review DB/PAY; DevOps review OPS/PERF. Ước lượng sơ bộ, sẽ chỉnh sau characterization.
