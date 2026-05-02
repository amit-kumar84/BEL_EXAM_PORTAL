# Test Credentials — BEL Kotdwar Exam Portal

## Super Admin (seeded from `includes/config.php` ADMIN_EMAIL / ADMIN_PASSWORD)
- Email: `admin@belkotdwar.in`
- Password: `Admin@123`
- Login: `/admin/login.php`
- Note: After first login, the super admin can change their own email & password via the **Edit** button on the Admin Accounts page — requires answering the developer verification question.

## Super-admin self-edit verification (developer-set)
- Question: "In which city was BEL Kotdwar unit established?"
- Answer: `kotdwar` (case-insensitive, trimmed)
- Both values defined in `includes/config.php` — developer can change `SUPER_VERIFY_QUESTION` and `SUPER_VERIFY_ANSWER_HASH`.
- To generate a new answer hash:
  `php -r "echo hash('sha256', strtolower(trim('YOUR-ANSWER'))) . PHP_EOL;"`

## Password storage
- All admin passwords (super + regular) stored as **bcrypt** (`password_hash(..., PASSWORD_BCRYPT)`).
- `plain_password` column is explicitly set to `NULL` for admin rows by both the Reset and Super-Edit flows.
- Super admin email is **masked** (e.g. `a••••@••••••••.in`) for any viewer who is NOT the super admin themselves.
