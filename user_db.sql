-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Waktu pembuatan: 12 Jun 2025 pada 12.02
-- Versi server: 11.4.5-MariaDB-deb11
-- Versi PHP: 8.3.20

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `user_db`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `ad_creatives`
--

CREATE TABLE `ad_creatives` (
  `id` int(11) NOT NULL,
  `campaign_id` int(11) NOT NULL,
  `creative_type` varchar(100) DEFAULT NULL,
  `content` text DEFAULT NULL,
  `image_url` text DEFAULT NULL,
  `click_url` text DEFAULT NULL,
  `native_title` varchar(500) DEFAULT NULL,
  `native_description` text DEFAULT NULL,
  `native_sponsor` varchar(255) DEFAULT NULL,
  `native_cta_text` varchar(100) DEFAULT NULL,
  `native_icon_url` text DEFAULT NULL,
  `native_main_image` text DEFAULT NULL,
  `native_rating` decimal(2,1) DEFAULT NULL,
  `native_price` varchar(100) DEFAULT NULL,
  `width` int(11) DEFAULT NULL,
  `height` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `html_code` longtext DEFAULT NULL,
  `vast_url` text DEFAULT NULL,
  `video_file` text DEFAULT NULL,
  `format` varchar(50) DEFAULT NULL,
  `status` enum('active','paused','pending','rejected','draft') DEFAULT NULL,
  `title` varchar(500) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `destination_url` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Dumping data untuk tabel `ad_creatives`
--

INSERT INTO `ad_creatives` (`id`, `campaign_id`, `creative_type`, `content`, `image_url`, `click_url`, `native_title`, `native_description`, `native_sponsor`, `native_cta_text`, `native_icon_url`, `native_main_image`, `native_rating`, `native_price`, `width`, `height`, `created_at`, `html_code`, `vast_url`, `video_file`, `format`, `status`, `title`, `description`, `destination_url`) VALUES
(2, 8, 'html_js', '<script async type=\"application/javascript\" src=\"https://a.magsrv.com/ad-provider.js\"></script> \n <ins class=\"eas6a97888e2\" data-zoneid=\"5548370\"></ins> \n <script>(AdProvider = window.AdProvider || []).push({\"serve\": {}});</script>', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 300, 250, '2025-06-11 17:56:19', '<script async type=\"application/javascript\" src=\"https://a.magsrv.com/ad-provider.js\"></script> \r\n <ins class=\"eas6a97888e2\" data-zoneid=\"5548370\"></ins> \r\n <script>(AdProvider = window.AdProvider || []).push({\"serve\": {}});</script>', NULL, NULL, 'banner', 'active', '300x250', '', ''),
(6, 8, 'html_js', 'banner html_js: Manual Test Creative 04:59:23', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-06-12 05:00:09', '<script>\r\ndocument.write(\'<div>Test Banner</div>\');\r\n</script>', NULL, NULL, 'banner', 'active', 'Manual Test Creative 04:59:23', NULL, NULL);

-- --------------------------------------------------------

--
-- Struktur dari tabel `ad_server_domains`
--

CREATE TABLE `ad_server_domains` (
  `id` int(11) NOT NULL,
  `domain_name` varchar(255) NOT NULL,
  `domain_type` enum('primary','secondary','cdn') DEFAULT 'secondary',
  `ssl_enabled` tinyint(1) DEFAULT 0,
  `ssl_cert_path` varchar(500) DEFAULT NULL,
  `ssl_key_path` varchar(500) DEFAULT NULL,
  `status` enum('active','inactive','pending') DEFAULT 'pending',
  `last_checked` timestamp NULL DEFAULT NULL,
  `response_time` int(11) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `ad_zones`
--

CREATE TABLE `ad_zones` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `type` enum('banner','video','popunder','native') NOT NULL,
  `width` int(11) DEFAULT NULL,
  `height` int(11) DEFAULT NULL,
  `website_url` varchar(500) DEFAULT NULL,
  `zone_code` text DEFAULT NULL,
  `vast_url` varchar(500) DEFAULT NULL,
  `native_template` enum('feed','content','recommendation','custom') DEFAULT NULL,
  `native_custom_css` text DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Dumping data untuk tabel `ad_zones`
--

INSERT INTO `ad_zones` (`id`, `user_id`, `name`, `type`, `width`, `height`, `website_url`, `zone_code`, `vast_url`, `native_template`, `native_custom_css`, `status`, `created_at`) VALUES
(1, 1, 'Banner ', 'banner', 300, 250, 'https://adstors.com', '<script type=\"text/javascript\">\nvar adstart_zone_id = 1;\nvar adstart_width = 300;\nvar adstart_height = 250;\n</script>\n<script type=\"text/javascript\" src=\"https://ads.adstart.click/serve/banner.php?zone_id=1\"></script>\n<noscript>\n    <a href=\"https://ads.adstart.click/serve/banner.php?zone_id=1&noscript=1\" target=\"_blank\">\n        <img src=\"https://ads.adstart.click/serve/banner.php?zone_id=1&noscript=1&format=img\" \n             width=\"300\" height=\"250\" border=\"0\" alt=\"Advertisement\">\n    </a>\n</noscript>', NULL, NULL, NULL, 'active', '2025-06-11 18:03:21');

-- --------------------------------------------------------

--
-- Struktur dari tabel `bid_requests`
--

CREATE TABLE `bid_requests` (
  `id` int(11) NOT NULL,
  `request_id` varchar(255) NOT NULL,
  `zone_id` int(11) DEFAULT NULL,
  `campaign_id` int(11) DEFAULT NULL,
  `bid_price` decimal(8,4) DEFAULT NULL,
  `win_price` decimal(8,4) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `country` varchar(3) DEFAULT NULL,
  `device_type` varchar(50) DEFAULT NULL,
  `status` enum('request','response','win','loss') DEFAULT 'request',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `blocked_traffic`
--

CREATE TABLE `blocked_traffic` (
  `id` int(11) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text DEFAULT NULL,
  `block_reason` varchar(255) DEFAULT NULL,
  `block_type` enum('temporary','permanent') DEFAULT 'temporary',
  `blocked_until` timestamp NULL DEFAULT NULL,
  `attempts_count` int(11) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `campaigns`
--

CREATE TABLE `campaigns` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `type` enum('standard','ron','native','video','popunder') DEFAULT 'standard',
  `format` text DEFAULT NULL,
  `category` text DEFAULT NULL,
  `geo_targeting` text DEFAULT NULL,
  `device_targeting` text DEFAULT NULL,
  `browser_targeting` text DEFAULT NULL,
  `language` text DEFAULT NULL,
  `os_targeting` text DEFAULT NULL,
  `carrier_isp_targeting` text DEFAULT NULL,
  `blocked_domains` text DEFAULT NULL,
  `rtb_url` varchar(500) DEFAULT NULL,
  `pricing_model` enum('CPM','CPC') NOT NULL,
  `rate` decimal(8,4) NOT NULL,
  `status` enum('active','paused','stopped') DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `targeting_countries` text DEFAULT NULL,
  `targeting_os` text DEFAULT NULL,
  `targeting_devices` text DEFAULT NULL,
  `targeting_languages` text DEFAULT NULL,
  `targeting_categories` text DEFAULT NULL,
  `targeting_browsers` text DEFAULT NULL,
  `targeting_carriers` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Dumping data untuk tabel `campaigns`
--

INSERT INTO `campaigns` (`id`, `user_id`, `name`, `type`, `format`, `category`, `geo_targeting`, `device_targeting`, `browser_targeting`, `language`, `os_targeting`, `carrier_isp_targeting`, `blocked_domains`, `rtb_url`, `pricing_model`, `rate`, `status`, `created_at`, `targeting_countries`, `targeting_os`, `targeting_devices`, `targeting_languages`, `targeting_categories`, `targeting_browsers`, `targeting_carriers`) VALUES
(8, 1, 'Banner', 'ron', 'banner', 'news,entertainment,sports,technology,lifestyle,business', 'US,CA,GB,AU,DE,FR,IT,ES,NL,ID,MY,SG,TH,VN,PH', 'mobile,desktop,tablet,smart_tv,console', 'chrome,firefox,safari,edge,opera', 'en,es,fr,de,id,ms,th,zh', 'windows,macos,android,ios,linux', 'wifi,mobile_data,telkomsel,indosat,xl', NULL, NULL, 'CPM', 0.0010, 'active', '2025-06-11 17:56:19', 'US,CA,GB,AU,DE,FR,IT,ES,NL,ID,MY,SG,TH,VN,PH', 'windows,macos,android,ios,linux', 'mobile,desktop,tablet,smart_tv,console', 'en,es,fr,de,id,ms,th,zh', 'news,entertainment,sports,technology,lifestyle,business', 'chrome,firefox,safari,edge,opera', 'wifi,mobile_data,telkomsel,indosat,xl');

-- --------------------------------------------------------

--
-- Struktur dari tabel `campaign_banner_sizes`
--

CREATE TABLE `campaign_banner_sizes` (
  `id` int(11) NOT NULL,
  `campaign_id` int(11) NOT NULL,
  `size` varchar(20) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Dumping data untuk tabel `campaign_banner_sizes`
--

INSERT INTO `campaign_banner_sizes` (`id`, `campaign_id`, `size`, `created_at`) VALUES
(1, 8, '300x250', '2025-06-11 17:56:19');

-- --------------------------------------------------------

--
-- Struktur dari tabel `campaign_drafts`
--

CREATE TABLE `campaign_drafts` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `session_id` varchar(128) NOT NULL,
  `draft_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`draft_data`)),
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Dumping data untuk tabel `campaign_drafts`
--

INSERT INTO `campaign_drafts` (`id`, `user_id`, `session_id`, `draft_data`, `created_at`, `updated_at`) VALUES
(1, 1, 'pstgps6qn993mobjt1nrfm72iv', '{\"user_id\":1,\"name\":\"Banner \",\"pricing_model\":\"cpm\",\"rate\":\"0.0001\",\"status\":\"active\",\"countries\":\"AF,AL,DZ,AS,AD,AO,AI,AQ,AG,AR,AM,AW,AU,AT,AZ,BS,BH,BD,BB,BY,BE,BZ,BJ,BM,BT,BO,BA,BW,BR,BN,BG,BF,BI,KH,CM,CA,CV,KY,CF,TD,CL,CN,CO,KM,CD,CG,CR,CI,HR,CU,CY,CZ,DK,DJ,DM,DO,EC,EG,SV,GQ,ER,EE,ET,FJ,FI,FR,GA,GM,GE,DE,GH,GR,GD,GT,GN,GW,GY,HT,HN,HK,HU,IS,IN,ID,IR,IQ,IE,IL,IT,JM,JP,JO,KZ,KE,KI,KR,KW,KG,LA,LV,LB,LS,LR,LY,LI,LT,LU,MO,MK,MG,MW,MY,MV,ML,MT,MH,MR,MU,MX,FM,MD,MC,MN,ME,MA,MZ,MM,NA,NR,NP,NL,NZ,NI,NE,NG,NO,OM,PK,PW,PA,PG,PY,PE,PH,PL,PT,QA,RO,RU,RW,KN,LC,VC,WS,SM,ST,SA,SN,RS,SC,SL,SG,SK,SI,SB,SO,ZA,SS,ES,LK,SD,SR,SE,CH,SY,TW,TJ,TZ,TH,TL,TG,TO,TT,TN,TR,TM,TV,UG,UA,AE,GB,US,UY,UZ,VU,VE,VN,YE,ZM,ZW\",\"devices\":\"desktop,mobile,tablet,smart_tv,console,wearable,feature_phone,set_top_box,automotive,iot,other\",\"languages\":\"en,es,fr,de,it,pt,ru,ja,ko,zh,zh-tw,ar,hi,bn,id,ms,th,vi,tl,tr,fa,uk,pl,nl,sv,fi,no,da,he,ro,el,cs,hu,sk,sr,hr,bg,sl,et,lv,lt,ur,sw,zu,xh,am,other\",\"creative_format\":\"banner\",\"creative_type\":\"\",\"updated_at\":\"2025-06-11 09:28:44\"}', '2025-06-11 09:24:21', '2025-06-11 09:39:20');

-- --------------------------------------------------------

--
-- Struktur dari tabel `fraud_detection_rules`
--

CREATE TABLE `fraud_detection_rules` (
  `id` int(11) NOT NULL,
  `rule_name` varchar(255) NOT NULL,
  `rule_type` enum('ip_analysis','user_agent','behavioral','geographic','fingerprinting','machine_learning') NOT NULL,
  `rule_config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`rule_config`)),
  `severity` enum('low','medium','high','critical') DEFAULT 'medium',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `fraud_scores`
--

CREATE TABLE `fraud_scores` (
  `id` int(11) NOT NULL,
  `session_id` varchar(64) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text DEFAULT NULL,
  `fingerprint_hash` varchar(64) DEFAULT NULL,
  `total_score` int(11) DEFAULT 0,
  `risk_level` enum('low','medium','high','critical') DEFAULT 'low',
  `factors` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`factors`)),
  `action_taken` enum('allow','challenge','block') DEFAULT 'allow',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `rtb_endpoints`
--

CREATE TABLE `rtb_endpoints` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `endpoint_url` varchar(500) NOT NULL,
  `format` enum('banner','video','popup','native') DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `site_targeting` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`site_targeting`)),
  `user_targeting` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`user_targeting`)),
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `statistics`
--

CREATE TABLE `statistics` (
  `id` int(11) NOT NULL,
  `campaign_id` int(11) DEFAULT NULL,
  `zone_id` int(11) DEFAULT NULL,
  `impressions` int(11) DEFAULT 0,
  `clicks` int(11) DEFAULT 0,
  `revenue` decimal(10,4) DEFAULT 0.0000,
  `date` date NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `traffic_patterns`
--

CREATE TABLE `traffic_patterns` (
  `id` int(11) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `hour_bucket` int(11) NOT NULL,
  `request_count` int(11) DEFAULT 1,
  `impression_count` int(11) DEFAULT 0,
  `click_count` int(11) DEFAULT 0,
  `conversion_count` int(11) DEFAULT 0,
  `last_seen` timestamp NULL DEFAULT current_timestamp(),
  `date_created` date DEFAULT curdate()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','advertiser','publisher') DEFAULT 'advertiser',
  `balance` decimal(10,4) DEFAULT 0.0000,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status` enum('active','inactive','suspended') DEFAULT 'active',
  `last_login` timestamp NULL DEFAULT NULL,
  `last_ip` varchar(45) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Dumping data untuk tabel `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `role`, `balance`, `created_at`, `updated_at`, `status`, `last_login`, `last_ip`) VALUES
(1, 'admin', 'admin@adstart.click', '$2y$10$cfDDFRefenTcUrZ8AuopuefrMuZYn90QAXELbcqwXfyfrW570ahhO', 'admin', 0.0000, '2025-06-11 05:09:13', '2025-06-11 05:09:13', 'active', NULL, NULL),
(2, 'ari513270', 'ari513270@gmail.com', '$2y$10$ClCgNAGInknmT.9MMqS9KeFP4QTo.sDPd.RiGwsdchL0SFOYkTqya', 'advertiser', 10000.0000, '2025-06-11 05:14:09', '2025-06-11 05:14:09', 'active', NULL, NULL),
(3, 'webpublhiser', 'webpublhiser@gmail.com', '$2y$10$em9W842vUNjkDrX/xq8Q3OeUw0Qne6zOFKH75WuBACsW5ahGox23W', 'publisher', 0.0000, '2025-06-11 05:14:32', '2025-06-11 05:14:32', 'active', NULL, NULL);

-- --------------------------------------------------------

--
-- Struktur dari tabel `websites`
--

CREATE TABLE `websites` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `domain` varchar(255) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Dumping data untuk tabel `websites`
--

INSERT INTO `websites` (`id`, `user_id`, `domain`, `name`, `category`, `description`, `status`, `created_at`, `updated_at`) VALUES
(1, 3, 'https://hornylust.com', 'Hornylust', 'news', '', 'approved', '2025-06-11 08:25:08', '2025-06-11 08:33:31');

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `ad_creatives`
--
ALTER TABLE `ad_creatives`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ad_creatives_campaign_format` (`campaign_id`,`format`),
  ADD KEY `idx_ad_creatives_status` (`status`),
  ADD KEY `idx_ad_creatives_type` (`creative_type`),
  ADD KEY `idx_ad_creatives_campaign` (`campaign_id`),
  ADD KEY `idx_ad_creatives_status_new` (`status`),
  ADD KEY `idx_ad_creatives_format_new` (`format`),
  ADD KEY `idx_ad_creatives_format` (`format`);

--
-- Indeks untuk tabel `ad_server_domains`
--
ALTER TABLE `ad_server_domains`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `ad_zones`
--
ALTER TABLE `ad_zones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indeks untuk tabel `bid_requests`
--
ALTER TABLE `bid_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `zone_id` (`zone_id`),
  ADD KEY `campaign_id` (`campaign_id`);

--
-- Indeks untuk tabel `blocked_traffic`
--
ALTER TABLE `blocked_traffic`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ip` (`ip_address`),
  ADD KEY `idx_blocked_until` (`blocked_until`);

--
-- Indeks untuk tabel `campaigns`
--
ALTER TABLE `campaigns`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_campaigns_type_status` (`type`,`status`),
  ADD KEY `idx_campaigns_user_type` (`user_id`,`type`),
  ADD KEY `idx_campaigns_pricing` (`pricing_model`,`rate`);

--
-- Indeks untuk tabel `campaign_banner_sizes`
--
ALTER TABLE `campaign_banner_sizes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_campaign_id` (`campaign_id`);

--
-- Indeks untuk tabel `campaign_drafts`
--
ALTER TABLE `campaign_drafts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_session` (`user_id`,`session_id`),
  ADD KEY `idx_updated_at` (`updated_at`);

--
-- Indeks untuk tabel `fraud_detection_rules`
--
ALTER TABLE `fraud_detection_rules`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `fraud_scores`
--
ALTER TABLE `fraud_scores`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ip` (`ip_address`),
  ADD KEY `idx_session` (`session_id`),
  ADD KEY `idx_score` (`total_score`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indeks untuk tabel `rtb_endpoints`
--
ALTER TABLE `rtb_endpoints`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indeks untuk tabel `statistics`
--
ALTER TABLE `statistics`
  ADD PRIMARY KEY (`id`),
  ADD KEY `campaign_id` (`campaign_id`),
  ADD KEY `zone_id` (`zone_id`);

--
-- Indeks untuk tabel `traffic_patterns`
--
ALTER TABLE `traffic_patterns`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_ip_hour_date` (`ip_address`,`hour_bucket`,`date_created`),
  ADD KEY `idx_ip_date` (`ip_address`,`date_created`);

--
-- Indeks untuk tabel `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indeks untuk tabel `websites`
--
ALTER TABLE `websites`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `ad_creatives`
--
ALTER TABLE `ad_creatives`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT untuk tabel `ad_server_domains`
--
ALTER TABLE `ad_server_domains`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `ad_zones`
--
ALTER TABLE `ad_zones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `bid_requests`
--
ALTER TABLE `bid_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `blocked_traffic`
--
ALTER TABLE `blocked_traffic`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `campaigns`
--
ALTER TABLE `campaigns`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT untuk tabel `campaign_banner_sizes`
--
ALTER TABLE `campaign_banner_sizes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `campaign_drafts`
--
ALTER TABLE `campaign_drafts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `fraud_detection_rules`
--
ALTER TABLE `fraud_detection_rules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `fraud_scores`
--
ALTER TABLE `fraud_scores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `rtb_endpoints`
--
ALTER TABLE `rtb_endpoints`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `statistics`
--
ALTER TABLE `statistics`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `traffic_patterns`
--
ALTER TABLE `traffic_patterns`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT untuk tabel `websites`
--
ALTER TABLE `websites`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `ad_creatives`
--
ALTER TABLE `ad_creatives`
  ADD CONSTRAINT `ad_creatives_ibfk_1` FOREIGN KEY (`campaign_id`) REFERENCES `campaigns` (`id`);

--
-- Ketidakleluasaan untuk tabel `ad_zones`
--
ALTER TABLE `ad_zones`
  ADD CONSTRAINT `ad_zones_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Ketidakleluasaan untuk tabel `bid_requests`
--
ALTER TABLE `bid_requests`
  ADD CONSTRAINT `bid_requests_ibfk_1` FOREIGN KEY (`zone_id`) REFERENCES `ad_zones` (`id`),
  ADD CONSTRAINT `bid_requests_ibfk_2` FOREIGN KEY (`campaign_id`) REFERENCES `campaigns` (`id`);

--
-- Ketidakleluasaan untuk tabel `campaigns`
--
ALTER TABLE `campaigns`
  ADD CONSTRAINT `campaigns_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Ketidakleluasaan untuk tabel `campaign_banner_sizes`
--
ALTER TABLE `campaign_banner_sizes`
  ADD CONSTRAINT `campaign_banner_sizes_ibfk_1` FOREIGN KEY (`campaign_id`) REFERENCES `campaigns` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `campaign_drafts`
--
ALTER TABLE `campaign_drafts`
  ADD CONSTRAINT `campaign_drafts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `rtb_endpoints`
--
ALTER TABLE `rtb_endpoints`
  ADD CONSTRAINT `rtb_endpoints_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Ketidakleluasaan untuk tabel `statistics`
--
ALTER TABLE `statistics`
  ADD CONSTRAINT `statistics_ibfk_1` FOREIGN KEY (`campaign_id`) REFERENCES `campaigns` (`id`),
  ADD CONSTRAINT `statistics_ibfk_2` FOREIGN KEY (`zone_id`) REFERENCES `ad_zones` (`id`);

--
-- Ketidakleluasaan untuk tabel `websites`
--
ALTER TABLE `websites`
  ADD CONSTRAINT `websites_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
