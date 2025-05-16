# RTB AdServer API Documentation

## Ikhtisar

API RTB AdServer menyediakan akses ke fitur-fitur platform melalui endpoint RESTful. Semua request memerlukan autentikasi menggunakan token JWT kecuali endpoint register dan login.

## Autentikasi

API menggunakan JWT (JSON Web Token) untuk autentikasi.

**Headers**

```
Authorization: Bearer {jwt_token}
```

## Endpoint Autentikasi

### Register

Mendaftarkan pengguna baru sebagai publisher atau advertiser.

**URL:** `/api/register`

**Method:** `POST`

**Data (Publisher):**

```json
{
  "username": "publisher123",
  "password": "password123",
  "email": "publisher@example.com",
  "role": "publisher",
  "first_name": "John",
  "last_name": "Doe",
  "phone": "+628123456789",
  "website_url": "https://example.com",
  "website_name": "Example Site",
  "website_category": "News",
  "website_description": "News and articles about tech"
}
```

**Data (Advertiser):**

```json
{
  "username": "advertiser123",
  "password": "password123",
  "email": "advertiser@example.com",
  "role": "advertiser",
  "first_name": "Jane",
  "last_name": "Smith",
  "phone": "+628123456780",
  "company_name": "Example Company",
  "contact_person": "Jane Smith",
  "industry": "Technology"
}
```

**Response:**

```json
{
  "message": "Registration successful",
  "user_id": 1,
  "status": "pending",
  "role": "publisher"
}
```

### Login

Melakukan login dan mendapatkan token JWT.

**URL:** `/api/login`

**Method:** `POST`

**Data:**

```json
{
  "username": "publisher123",
  "password": "password123"
}
```

**Response:**

```json
{
  "message": "Login successful",
  "token": "eyJhbGciOiJIUzI1NiIsIn...",
  "user": {
    "id": 1,
    "username": "publisher123",
    "email": "publisher@example.com",
    "role": "publisher",
    "role_id": 1
  },
  "expires_in": 86400
}
```

## Endpoint Publisher

### Mendapatkan Daftar Publisher (Admin)

Mendapatkan daftar semua publisher. Hanya bisa diakses oleh admin.

**URL:** `/api/publishers`

**Method:** `GET`

**Query Parameters:**
- `page` - Nomor halaman (default: 1)
- `limit` - Jumlah item per halaman (default: 20)
- `status` - Filter berdasarkan status (optional)

**Response:**

```json
{
  "data": [
    {
      "id": 1,
      "user_id": 2,
      "website_url": "https://example.com",
      "website_name": "Example Site",
      "website_category": "News",
      "status": "active",
      "verification_status": "verified",
      "created_at": "2023-06-01 12:00:00",
      "username": "publisher123",
      "email": "publisher@example.com",
      "last_login": "2023-06-05 08:30:00"
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

### Mendapatkan Detail Publisher

Mendapatkan detail publisher berdasarkan ID. Admin bisa mengakses semua publisher, publisher hanya bisa mengakses datanya sendiri.

**URL:** `/api/publishers/{id}`

**Method:** `GET`

**Response:**

```json
{
  "data": {
    "id": 1,
    "user_id": 2,
    "website_url": "https://example.com",
    "website_name": "Example Site",
    "website_category": "News",
    "website_description": "News and articles about tech",
    "status": "active",
    "verification_status": "verified",
    "payout_method": "bank_transfer",
    "payout_details": "Bank XYZ, Account 123456789",
    "min_payout": 50,
    "created_at": "2023-06-01 12:00:00",
    "updated_at": "2023-06-05 09:30:00",
    "username": "publisher123",
    "email": "publisher@example.com",
    "user_status": "active",
    "recent_stats": [
      {
        "date": "2023-06-10",
        "impressions": 12500,
        "clicks": 250,
        "revenue": 15.75,
        "ctr": 0.02,
        "ecpm": 1.26
      }
    ],
    "zones": {
      "total_zones": 5,
      "active_zones": 4
    }
  }
}
```

## Endpoint Zona Iklan

### Mendapatkan Daftar Zona Iklan

Mendapatkan daftar zona iklan. Admin bisa melihat semua zona, publisher hanya bisa melihat zonanya sendiri.

**URL:** `/api/ad_zones`

**Method:** `GET`

**Query Parameters:**
- `page` - Nomor halaman (default: 1)
- `limit` - Jumlah item per halaman (default: 20)
- `status` - Filter berdasarkan status (optional)
- `zone_type` - Filter berdasarkan tipe zona (optional)

**Response:**

```json
{
  "data": [
    {
      "id": 1,
      "publisher_id": 1,
      "zone_name": "Homepage Banner",
      "zone_type": "banner",
      "width": 728,
      "height": 90,
      "rtb_url": "https://rtb.example.com/bid/abc123",
      "rtb_enabled": 1,
      "floor_price": 0.5,
      "status": "active",
      "created_at": "2023-06-02 14:30:00",
      "updated_at": "2023-06-02 14:30:00",
      "website_url": "https://example.com",
      "website_name": "Example Site"
    }
  ],
  "pagination": {
    "total": 25,
    "page": 1,
    "limit": 20,
    "pages": 2
  }
}
```

### Mendapatkan Detail Zona Iklan

Mendapatkan detail zona iklan berdasarkan ID.

**URL:** `/api/ad_zones/{id}`

**Method:** `GET`

**Response:**

```json
{
  "data": {
    "id": 1,
    "publisher_id": 1,
    "zone_name": "Homepage Banner",
    "zone_type": "banner",
    "width": 728,
    "height": 90,
    "rtb_url": "https://rtb.example.com/bid/abc123",
    "rtb_enabled": 1,
    "embed_code": "<script src=\"https://adserver.example.com/ad.js\" data-rtb=\"https://rtb.example.com/bid/abc123\" data-width=\"728\" data-height=\"90\"></script>",
    "fallback_ad": "<a href=\"https://example.com\"><img src=\"https://example.com/fallback.jpg\"></a>",
    "fallback_url": "https://example.com/fallback",
    "floor_price": 0.5,
    "max_refresh_rate": 0,
    "status": "active",
    "created_at": "2023-06-02 14:30:00",
    "updated_at": "2023-06-02 14:30:00",
    "website_url": "https://example.com",
    "website_name": "Example Site",
    "user_id": 2,
    "recent_stats": [
      {
        "date": "2023-06-10",
        "impressions": 5200,
        "clicks": 104,
        "revenue": 6.24,
        "ctr": 0.02,
        "ecpm": 1.2
      }
    ]
  }
}
```

### Membuat Zona Iklan Baru

Membuat zona iklan baru untuk publisher.

**URL:** `/api/ad_zones`

**Method:** `POST`

**Data:**

```json
{
  "zone_name": "Homepage Banner",
  "zone_type": "banner",
  "width": 728,
  "height": 90,
  "rtb_enabled": 1,
  "fallback_ad": "<a href=\"https://example.com\"><img src=\"https://example.com/fallback.jpg\"></a>",
  "fallback_url": "https://example.com/fallback",
  "floor_price": 0.5,
  "status": "active"
}
```

**Response:**

```json
{
  "message": "Ad zone created successfully",
  "zone_id": 1,
  "rtb_url": "https://rtb.example.com/bid/abc123",
  "embed_code": "<script src=\"https://adserver.example.com/ad.js\" data-rtb=\"https://rtb.example.com/bid/abc123\" data-width=\"728\" data-height=\"90\"></script>"
}
```

### Mengubah Zona Iklan

Mengubah zona iklan yang sudah ada.

**URL:** `/api/ad_zones/{id}`

**Method:** `PUT`

**Data:**

```json
{
  "zone_name": "Updated Homepage Banner",
  "floor_price": 0.75,
  "status": "paused"
}
```

**Response:**

```json
{
  "message": "Ad zone updated successfully",
  "zone_id": 1
}
```

### Menghapus Zona Iklan

Menghapus zona iklan (mengubah status menjadi 'deleted').

**URL:** `/api/ad_zones/{id}`

**Method:** `DELETE`

**Response:**

```json
{
  "message": "Ad zone deleted successfully",
  "zone_id": 1
}
```

## Endpoint Kampanye

### Mendapatkan Daftar Kampanye

Mendapatkan daftar kampanye. Admin bisa melihat semua kampanye, advertiser hanya bisa melihat kampanyenya sendiri.

**URL:** `/api/campaigns`

**Method:** `GET`

**Query Parameters:**
- `page` - Nomor halaman (default: 1)
- `limit` - Jumlah item per halaman (default: 20)
- `status` - Filter berdasarkan status (optional)
- `campaign_type` - Filter berdasarkan tipe kampanye (optional)

**Response:**

```json
{
  "data": [
    {
      "id": 1,
      "advertiser_id": 1,
      "campaign_name": "Summer Promotion",
      "campaign_type": "banner",
      "start_date": "2023-06-01 00:00:00",
      "end_date": "2023-06-30 23:59:59",
      "daily_budget": 50,
      "total_budget": 1500,
      "bid_type": "cpm",
      "bid_amount": 1.25,
      "status": "active",
      "created_at": "2023-05-25 10:15:00",
      "updated_at": "2023-05-25 10:15:00",
      "company_name": "Example Company"
    }
  ],
  "pagination": {
    "total": 30,
    "page": 1,
    "limit": 20,
    "pages": 2
  }
}
```

### Mendapatkan Detail Kampanye

Mendapatkan detail kampanye berdasarkan ID.

**URL:** `/api/campaigns/{id}`

**Method:** `GET`

**Response:**

```json
{
  "data": {
    "id": 1,
    "advertiser_id": 1,
    "campaign_name": "Summer Promotion",
    "campaign_type": "banner",
    "targeting_criteria": {
      "include_sites": ["example.com", "news.com"],
      "exclude_sites": ["adult.com"],
      "include_categories": ["news", "tech"],
      "exclude_categories": ["adult"]
    },
    "targeting_geos": "US,CA,UK",
    "targeting_devices": "desktop,mobile",
    "targeting_browsers": "chrome,firefox,safari",
    "targeting_os": "windows,macos,android,ios",
    "targeting_languages": "en",
    "targeting_hours": "8-20",
    "start_date": "2023-06-01 00:00:00",
    "end_date": "2023-06-30 23:59:59",
    "daily_budget": 50,
    "total_budget": 1500,
    "bid_type": "cpm",
    "bid_amount": 1.25,
    "frequency_cap": 3,
    "frequency_interval": 86400,
    "pacing": "standard",
    "status": "active",
    "created_at": "2023-05-25 10:15:00",
    "updated_at": "2023-05-25 10:15:00",
    "user_id": 3,
    "company_name": "Example Company",
    "ads": [
      {
        "id": 1,
        "ad_type": "banner",
        "ad_name": "Summer Sale Banner",
        "status": "active",
        "created_at": "2023-05-25 10:20:00",
        "updated_at": "2023-05-25 10:20:00"
      }
    ],
    "recent_stats": [
      {
        "date": "2023-06-10",
        "impressions": 8500,
        "clicks": 170,
        "conversions": 5,
        "cost": 10.63,
        "ctr": 0.02,
        "ecpm": 1.25,
        "cpc": 0.0625
      }
    ]
  }
}
```

### Membuat Kampanye Baru

Membuat kampanye baru untuk advertiser.

**URL:** `/api/campaigns`

**Method:** `POST`

**Data:**

```json
{
  "campaign_name": "Summer Promotion",
  "campaign_type": "banner",
  "targeting_criteria": {
    "include_sites": ["example.com", "news.com"],
    "exclude_sites": ["adult.com"],
    "include_categories": ["news", "tech"],
    "exclude_categories": ["adult"]
  },
  "targeting_geos": "US,CA,UK",
  "targeting_devices": "desktop,mobile",
  "targeting_browsers": "chrome,firefox,safari",
  "targeting_os": "windows,macos,android,ios",
  "targeting_languages": "en",
  "targeting_hours": "8-20",
  "start_date": "2023-06-01 00:00:00",
  "end_date": "2023-06-30 23:59:59",
  "daily_budget": 50,
  "total_budget": 1500,
  "bid_type": "cpm",
  "bid_amount": 1.25,
  "frequency_cap": 3,
  "status": "pending"
}
```

**Response:**

```json
{
  "message": "Campaign created successfully",
  "campaign_id": 1
}
```

### Mengubah Kampanye

Mengubah kampanye yang sudah ada.

**URL:** `/api/campaigns/{id}`

**Method:** `PUT`

**Data:**

```json
{
  "campaign_name": "Updated Summer Promotion",
  "bid_amount": 1.50,
  "status": "paused"
}
```

**Response:**

```json
{
  "message": "Campaign updated successfully",
  "campaign_id": 1
}
```

### Menghapus Kampanye

Menghapus kampanye (mengubah status menjadi 'deleted').

**URL:** `/api/campaigns/{id}`

**Method:** `DELETE`

**Response:**

```json
{
  "message": "Campaign deleted successfully",
  "campaign_id": 1
}
```

## Endpoint Statistik

### Mendapatkan Statistik

Mendapatkan statistik kampanye atau zona iklan dengan berbagai filter dan pengelompokan.

**URL:** `/api/statistics`

**Method:** `GET`

**Query Parameters:**
- `start_date` - Tanggal mulai dalam format YYYY-MM-DD (default: 7 hari yang lalu)
- `end_date` - Tanggal selesai dalam format YYYY-MM-DD (default: hari ini)
- `interval` - Interval data: 'daily' atau 'hourly' (default: 'daily')
- `group_by` - Pengelompokan data: 'date', 'campaign', 'ad_zone', 'ad', 'country', 'device_type', 'browser', 'os' (default: 'date')
- `campaign_id` - Filter berdasarkan ID kampanye (optional)
- `ad_zone_id` - Filter berdasarkan ID zona iklan (optional)
- `ad_id` - Filter berdasarkan ID iklan (optional)
- `country` - Filter berdasarkan kode negara (optional)
- `device_type` - Filter berdasarkan tipe perangkat (optional)

**Response:**

```json
{
  "data": [
    {
      "date": "2023-06-10",
      "requests": 15000,
      "impressions": 12500,
      "clicks": 250,
      "conversions": 8,
      "revenue": 15.75,
      "cost": 15.75,
      "profit": 3.15,
      "ctr": 0.02,
      "cvr": 0.032,
      "ecpm": 1.26,
      "ecpc": 0.063
    }
  ],
  "totals": {
    "requests": 105000,
    "impressions": 87500,
    "clicks": 1750,
    "conversions": 53,
    "revenue": 109.38,
    "cost": 109.38,
    "profit": 21.88,
    "ctr": 0.02,
    "cvr": 0.03,
    "ecpm": 1.25,
    "ecpc": 0.0625
  },
  "meta": {
    "start_date": "2023-06-04",
    "end_date": "2023-06-10",
    "interval": "daily",
    "group_by": "date"
  }
}
```
