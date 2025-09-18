## CloudDrive AWS S3 – Backend API (Laravel)

REST API for user auth and file management on AWS S3. Includes upload, download, temp URL generation, delete, move, and recursive listing. Auth handled via Laravel Sanctum token.

### Tech
- **Framework**: Laravel (PHP)
- **Storage**: AWS S3 (production), local disk (development)
- **Auth**: Laravel Sanctum (token-based)
- **DB**: MySQL/PostgreSQL/SQLite compatible via Eloquent

---

### Prerequisites
- PHP 8.2+
- Composer
- A database (MySQL/PostgreSQL/SQLite)
- AWS account and S3 bucket (for production features like temp URLs and move)

### Setup
1) Clone and install dependencies
```bash
cd backend
composer install
```

2) Environment
```bash
cp .env.example .env
php artisan key:generate
```
Configure database and AWS in `.env`:
```env
APP_NAME=CloudDrive
APP_URL=http://localhost:8000

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=clouddrive
DB_USERNAME=root
DB_PASSWORD=secret

# AWS (required for production S3 operations)
AWS_ACCESS_KEY_ID=your_access_key
AWS_SECRET_ACCESS_KEY=your_secret_key
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=your_bucket_name
# Optional
# AWS_URL=https://your-cdn-or-bucket-url
# AWS_USE_PATH_STYLE_ENDPOINT=false
```

3) Migrate database
```bash
php artisan migrate
```

4) Local storage symlink (for local disk downloads/URLs)
```bash
php artisan storage:link
```

5) Run server
```bash
php artisan serve
```

---

### Storage behavior
- In development (`APP_ENV` not `production`): files are stored on the `local` disk. Downloads and URLs are served from `public/storage` after `storage:link`.
- In production (`APP_ENV=production`): files are stored on `s3`. Public URLs use S3; temporary URLs and move operations require valid AWS credentials and bucket config.
- Note: `temp-url` and `move` endpoints always operate on S3.

---

### Authentication
Use token-based auth via Sanctum.

1) Register
```http
POST /api/register
Content-Type: application/json

{ "name": "John Doe", "email": "john@example.com", "password": "secret123" }
```

2) Login (get token)
```http
POST /api/login
Content-Type: application/json

{ "email": "john@example.com", "password": "secret123" }
```
Response includes `token`. Use it as `Authorization: Bearer <token>` for protected routes.

3) Logout
```http
POST /api/logout
Authorization: Bearer <token>
```

---

### File APIs (all require Bearer token unless stated)

- Upload
```http
POST /api/s3/upload
Authorization: Bearer <token>
Content-Type: multipart/form-data

file: <binary>
folder: optional/subfolder
```
Response: `{ status, message, file, url }`

- Download
```http
GET /api/s3/download/{filePath}
Authorization: Bearer <token>
```
`{filePath}` is the stored path (URL-encoded) returned on upload (e.g. `uploads/my.pdf`).

- Temporary URL (S3 only)
```http
GET /api/s3/temp-url/{filePath}
Authorization: Bearer <token>
```
Response: `{ status, url }` valid for 10 minutes.

- Delete
```http
DELETE /api/s3/delete/{filePath}
Authorization: Bearer <token>
```

- List (recursive)
```http
GET /api/s3/list/{folder?}
Authorization: Bearer <token>
```
Response: tree of files and folders with URLs (S3 or local).

- Move (S3 only)
```http
POST /api/s3/move
Authorization: Bearer <token>
Content-Type: application/json

{ "from": "uploads/old.pdf", "to": "archive/old.pdf" }
```

---

### Quick cURL examples
Login
```bash
curl -s -X POST http://localhost:8000/api/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"john@example.com","password":"secret123"}'
```

Upload
```bash
curl -s -X POST http://localhost:8000/api/s3/upload \
  -H "Authorization: Bearer $TOKEN" \
  -F file=@/path/to/file.pdf \
  -F folder=uploads
```

List
```bash
curl -s -H "Authorization: Bearer $TOKEN" \
  http://localhost:8000/api/s3/list
```

---

### Notes
- The `files` table stores metadata for each upload. Ensure migrations run.
- For local development downloads/URLs, run `php artisan storage:link` once.
- Set `APP_ENV=production` and AWS variables to enable S3 storage in production.

### Postman
- A ready-to-use environment is provided at `postman/CloudDrive-Local.postman_environment.json`.
- Import your API collection in Postman, select the environment, and set `token` via login response.
- Use `{{base_url}}` and `{{token}}` in requests. Example base URL: `http://localhost:8000`.

### License
MIT
