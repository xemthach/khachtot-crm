# Báo cáo đồng bộ GitHub CRM Khách Tốt

Ngày thực hiện: 2026-11-08 (Asia/Bangkok)

## Kết quả

- Repository: `https://github.com/xemthach/khachtot-crm.git`
- Branch đích: `main`
- Remote trước push: `faefd9b15c831b84305a843daca14b61d674d996`
- Commit đồng bộ localhost: `831eef61dbb028bc554810b9c8b02c5c29ab3e21`
- Kết quả push: fast-forward thành công, không dùng force-push.
- Xác nhận sau push: `git ls-remote origin refs/heads/main` trả về `831eef61dbb028bc554810b9c8b02c5c29ab3e21`.
- Commit message: `chore(sync): synchronize localhost CRM snapshot and deployment docs`.

## Phạm vi commit

- 405 file được stage từ worktree sạch dựa trên `origin/main`.
- Bao gồm source/module mới hoặc thay đổi, static assets, manifest, deployment scripts, test source không phải runtime artifact, tài liệu branch audit cần giữ và ba tài liệu khảo sát/triển khai/Cloudflare mới.
- File GitHub-only không xung đột được giữ nguyên.
- Không áp dụng các deletion từ working tree localhost vào GitHub khi deletion đó không cần thiết để giữ file GitHub-only.

## File bị loại khỏi commit

- `.env`, config runtime `application/config/config.php`, `database.php`, `app-config.php`.
- `vps_khachtot`, `vps_khachtot.pub`, private key/certificate, token, cookie và session.
- Upload, media, temp, cache, log, storage runtime, backup/dump và `node_modules`.
- Browser artifacts, HTML/JSON runtime result, test phase runtime output, evidence/archive và generated reports không cần cho source snapshot.
- Không có secret, dữ liệu khách hàng hoặc dữ liệu production được đưa vào commit theo kiểm tra tên file và marker private-key/API-token trên staged diff.

## Kiểm tra và giới hạn

- Đã kiểm tra danh sách path staged, diff summary và secret marker scan.
- `git diff --cached --check` có 19 cảnh báo blank line/trailing whitespace trong nội dung lấy nguyên trạng từ localhost; không format hoặc sửa nội dung vì ranh giới công việc cấm thay đổi ngoài đồng bộ.
- Không chạy test, migration, deployment, thao tác DB, thay đổi Nginx/PHP/Cloudflare hoặc thao tác live server.
