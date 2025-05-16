# API Manajemen Pengguna - Dokumentasi

## Ikhtisar

API manajemen pengguna menyediakan endpoint untuk membuat, membaca, memperbarui, dan menghapus (CRUD) data pengguna dalam sistem RTB AdServer. Semua endpoint dilindungi dengan autentikasi JWT, dengan hak akses yang berbeda berdasarkan peran pengguna.

## Autentikasi

Semua endpoint memerlukan autentikasi menggunakan JSON Web Token (JWT). Token ini diperoleh melalui endpoint `/api/login`. Sertakan token dalam header `Authorization` dengan format:

```
Authorization: Bearer {jwt_token}
```

## Endpoints

### 1. Mendapatkan Daftar Pengguna

Mengembalikan daftar semua pengguna. Hanya admin yang dapat mengakses endpoint ini.

**URL:** `/api/users`

**Method:** `GET`

**Headers:**
```
Authorization: Bearer {jwt_token}
```

**Query Parameters:**
- `page` - Nomor halaman (default: 1)
- `limit` - Jumlah item per halaman (default: 20)
- `role` - Filter berdasarkan peran (opsional)
- `status` - Filter berdasarkan status (opsional)
- `search` - Pencarian berdasarkan username atau email (opsional)

**Response Sukses:**
```json
{
  "data": [
    {
      "id": 1,
      "role": "admin",
      "username": "admin",
      "email": "admin@example.com",
      "status": "active",
      "first_name": "Admin",
      "last_name": "User",
      "company": "RTB AdServer",
      "last_login": "2023-07-15 10:30:45",
      "created_at": "2023-01-01 00:00:00",
      "updated_at": "2023-07-15 10:30:45"
    },
    {
      "id": 2,
      "role": "publisher",
      "username": "publisher1",
      "email": "publisher@example.com",
      "status": "active",
      "first_name": "John",
      "last_name": "Doe",
      "company": "Example Publisher",
      "last_login": "2023-07-14 15:20:10",
      "created_at": "2023-01-02 09:15:30",
      "updated_at": "2023-07-14 15:20:10"
    }
  ],
  "pagination": {
    "total": 50,
    "page": 1,
    "limit": 20,
    "pages": 3
  }
}
```

**Response Error:**
```json
{
  "error": "Only admin can access this endpoint"
}
```

### 2. Mendapatkan Detail Pengguna

Mengembalikan detail lengkap dari pengguna berdasarkan ID. Admin dapat mengakses detail semua pengguna, sedangkan pengguna lain hanya dapat mengakses data mereka sendiri.

**URL:** `/api/users/{id}`

**Method:** `GET`

**Headers:**
```
Authorization: Bearer {jwt_token}
```

**Response Sukses (Publisher):**
```json
{
  "data": {
    "id": 2,
    "role": "publisher",
    "username": "publisher1",
    "email": "publisher@example.com",
    "status": "active",
    "first_name": "John",
    "last_name": "Doe",
    "phone": "+123456789",
    "address": "123 Publisher Street",
    "company": "Example Publisher",
    "account_balance": 250.75,
    "last_login": "2023-07-14 15:20:10",
    "created_at": "2023-01-02 09:15:30",
    "updated_at": "2023-07-14 15:20:10",
    "publisher_details": {
      "id": 1,
      "website_url": "https://example.com",
      "website_name": "Example Website",
      "website_category": "News",
      "status": "active",
      "verification_status": "verified",
      "total_zones": 5
    }
  }
}
```

**Response Sukses (Advertiser):**
```json
{
  "data": {
    "id": 3,
    "role": "advertiser",
    "username": "advertiser1",
    "email": "advertiser@example.com",
    "status": "active",
    "first_name": "Jane",
    "last_name": "Smith",
    "phone": "+987654321",
    "address": "456 Advertiser Avenue",
    "company": "Example Advertiser",
    "account_balance": 1000.50,
    "last_login": "2023-07-15 08:45:22",
    "created_at": "2023-01-03 11:30:15",
    "updated_at": "2023-07-15 08:45:22",
    "advertiser_details": {
      "id": 1,
      "company_name": "Example Advertiser Inc.",
      "contact_person": "Jane Smith",
      "industry": "Technology",
      "status": "active",
      "total_campaigns": 3
    }
  }
}
```

**Response Error:**
```json
{
  "error": "Permission denied"
}
```

### 3. Membuat Pengguna Baru

Membuat pengguna baru. Hanya admin yang dapat mengakses endpoint ini.

**URL:** `/api/users`

**Method:** `POST`

**Headers:**
```
Authorization: Bearer {jwt_token}
Content-Type: application/json
```

**Data (Publisher):**
```json
{
  "username": "newpublisher",
  "password": "securepassword123",
  "email": "newpublisher@example.com",
  "role": "publisher",
  "first_name": "New",
  "last_name": "Publisher",
  "phone": "+123456789",
  "status": "active",
  "website_url": "https://newpublisher.com",
  "website_name": "New Publisher Website",
  "website_category": "Blog"
}
```

**Data (Advertiser):**
```json
{
  "username": "newadvertiser",
  "password": "securepassword123",
  "email": "newadvertiser@example.com",
  "role": "advertiser",
  "first_name": "New",
  "last_name": "Advertiser",
  "phone": "+987654321",
  "status": "active",
  "company_name": "New Advertiser Inc.",
  "contact_person": "New Advertiser",
  "industry": "Retail"
}
```

**Response Sukses:**
```json
{
  "message": "User created successfully",
  "user_id": 10,
  "role": "publisher",
  "status": "active"
}
```

**Response Error:**
```json
{
  "error": "Username or email already exists"
}
```

### 4. Memperbarui Pengguna

Memperbarui data pengguna yang sudah ada. Admin dapat memperbarui semua pengguna, sedangkan pengguna lain hanya dapat memperbarui data mereka sendiri.

**URL:** `/api/users/{id}`

**Method:** `PUT`

**Headers:**
```
Authorization: Bearer {jwt_token}
Content-Type: application/json
```

**Data:**
```json
{
  "first_name": "Updated",
  "last_name": "Name",
  "phone": "+111222333",
  "password": "newpassword123",
  "publisher_details": {
    "website_url": "https://updated-website.com",
    "website_name": "Updated Website Name"
  }
}
```

**Response Sukses:**
```json
{
  "message": "User updated successfully",
  "user_id": 2
}
```

**Response Error:**
```json
{
  "error": "Permission denied"
}
```

### 5. Menghapus Pengguna

Menghapus pengguna dari sistem. Hanya admin yang dapat mengakses endpoint ini, dan admin tidak dapat menghapus akun mereka sendiri.

**URL:** `/api/users/{id}`

**Method:** `DELETE`

**Headers:**
```
Authorization: Bearer {jwt_token}
```

**Response Sukses:**
```json
{
  "message": "User deleted successfully",
  "user_id": 5
}
```

**Response Error:**
```json
{
  "error": "You cannot delete your own account"
}
```

## Izin Akses

| Endpoint           | Admin | Publisher | Advertiser |
|--------------------|-------|-----------|------------|
| GET /api/users     | Ya    | Tidak     | Tidak      |
| GET /api/users/{id}| Semua | Hanya Sendiri | Hanya Sendiri |
| POST /api/users    | Ya    | Tidak     | Tidak      |
| PUT /api/users/{id}| Semua | Hanya Sendiri | Hanya Sendiri |
| DELETE /api/users/{id}| Ya  | Tidak     | Tidak      |

## Batasan Peran

- **Admin**: Dapat mengakses dan memodifikasi semua data pengguna
- **Publisher**: Hanya dapat mengakses dan memodifikasi data mereka sendiri
- **Advertiser**: Hanya dapat mengakses dan memodifikasi data mereka sendiri

## Kode Status HTTP

- `200 OK`: Permintaan berhasil
- `201 Created`: Pengguna berhasil dibuat
- `400 Bad Request`: Parameter yang diperlukan tidak ada atau tidak valid
- `401 Unauthorized`: Token autentikasi tidak disediakan atau tidak valid
- `403 Forbidden`: Tidak memiliki izin untuk mengakses sumber daya
- `404 Not Found`: Pengguna tidak ditemukan
- `409 Conflict`: Username atau email sudah ada
- `500 Internal Server Error`: Kesalahan server
