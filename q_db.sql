-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Nov 18, 2026 at 07:34 AM
-- Server version: 9.1.0
-- PHP Version: 8.3.14

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `q_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

DROP TABLE IF EXISTS `categories`;
CREATE TABLE IF NOT EXISTS `categories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text,
  `image` varchar(500) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `footer_settings`
--

DROP TABLE IF EXISTS `footer_settings`;
CREATE TABLE IF NOT EXISTS `footer_settings` (
  `id` int NOT NULL,
  `brand_initials` varchar(10) DEFAULT 'FN',
  `brand_name` varchar(150) DEFAULT 'Farshad Nabizade',
  `brand_title` varchar(150) DEFAULT 'Full-Stack Software Engineer',
  `brand_bio` text,
  `availability_status` varchar(150) DEFAULT 'Available for Engineering Opportunities',
  `nav_links` json DEFAULT NULL,
  `technologies` json DEFAULT NULL,
  `email` varchar(150) DEFAULT 'farshad.nabizade@gmail.com',
  `phone` varchar(50) DEFAULT '+98 912 345 6789',
  `location` varchar(150) DEFAULT 'Tehran, Iran',
  `contact_note` varchar(255) DEFAULT 'Feel free to reach out for collaborations, technical inquiries, or consulting.',
  `copyright_text` varchar(255) DEFAULT 'All rights reserved. • Engineered with modern web standards.',
  `visible_sections` json DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `footer_settings`
--

INSERT INTO `footer_settings` (`id`, `brand_initials`, `brand_name`, `brand_title`, `brand_bio`, `availability_status`, `nav_links`, `technologies`, `email`, `phone`, `location`, `contact_note`, `copyright_text`, `visible_sections`, `updated_at`) VALUES
(1, 'FN', 'Farshad Nabizade', 'Full-Stack & Systems Engineer', 'Dedicated to architecting high-performance web applications, scalable APIs, reactive frontends, and comprehensive computer science knowledge bases.', '', '[{\"url\": \"index.php\", \"label\": \"Questions Base\"}, {\"url\": \"projects.php\", \"label\": \"Projects Portfolio\"}, {\"url\": \"freelance.php\", \"label\": \"Freelance Hub\"}, {\"url\": \"profile.php\", \"label\": \"Resume & CV\"}, {\"url\": \"profile.php#skills\", \"label\": \"Technical Skills\"}, {\"url\": \"profile.php#experience\", \"label\": \"Career Timeline\"}]', '[\"React\", \"Next.js (SSR)\", \"TypeScript\", \"PHP & Laravel\", \"Node.js\", \"Tailwind CSS\", \"Redux Toolkit\", \"MySQL & Redis\", \"Docker & Nginx\", \"RESTful APIs\", \"Clean Code\"]', 'farshadnabizadeh1993@gmail.com', '+98 921 812 4475', 'Tehran, Iran', 'Feel free to reach out for collaborations, technical inquiries, or consulting.', 'All rights reserved. • Engineered with modern web standards.', '{\"nav\": true, \"tech\": true, \"brand\": true, \"contact\": true}', '2026-11-18 07:31:45');

-- --------------------------------------------------------

--
-- Table structure for table `freelance_companies`
--

DROP TABLE IF EXISTS `freelance_companies`;
CREATE TABLE IF NOT EXISTS `freelance_companies` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `client_name` varchar(255) DEFAULT NULL,
  `client_email` varchar(255) DEFAULT NULL,
  `color` varchar(30) DEFAULT '#12466f',
  `share_token` varchar(64) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `share_token` (`share_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `freelance_projects`
--

DROP TABLE IF EXISTS `freelance_projects`;
CREATE TABLE IF NOT EXISTS `freelance_projects` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_id` int NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text,
  `hourly_rate` decimal(15,2) DEFAULT '500000.00',
  `currency` varchar(20) DEFAULT 'تومان',
  `status` varchar(50) DEFAULT 'in_progress',
  `share_token` varchar(64) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `share_token` (`share_token`),
  KEY `idx_company` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `freelance_tasks`
--

DROP TABLE IF EXISTS `freelance_tasks`;
CREATE TABLE IF NOT EXISTS `freelance_tasks` (
  `id` int NOT NULL AUTO_INCREMENT,
  `project_id` int NOT NULL,
  `title` varchar(255) NOT NULL,
  `task_date` date NOT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `price_per_hour` decimal(15,2) NOT NULL DEFAULT '500000.00',
  `duration_hours` decimal(6,2) NOT NULL DEFAULT '0.00',
  `total_price` decimal(15,2) NOT NULL DEFAULT '0.00',
  `description` text,
  `status` varchar(50) DEFAULT 'completed',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_project` (`project_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `profile`
--

DROP TABLE IF EXISTS `profile`;
CREATE TABLE IF NOT EXISTS `profile` (
  `id` int NOT NULL AUTO_INCREMENT,
  `full_name` varchar(150) NOT NULL,
  `headline` varchar(255) NOT NULL,
  `avatar_url` varchar(255) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `location_province` varchar(100) DEFAULT NULL,
  `location_city` varchar(100) DEFAULT NULL,
  `address` varchar(150) DEFAULT NULL,
  `github` varchar(255) DEFAULT NULL,
  `linkedin` varchar(255) DEFAULT NULL,
  `website` varchar(255) DEFAULT 'https://blog.sibmlm.ir',
  `age` int DEFAULT '34',
  `gender` varchar(50) DEFAULT 'Male',
  `marital_status` varchar(50) DEFAULT 'Single',
  `military_status` varchar(100) DEFAULT 'Exempted',
  `work_experience_years` int DEFAULT '7',
  `salary_expectation` varchar(100) DEFAULT '45 - 60 Million Tomans',
  `education_degree` varchar(255) DEFAULT 'Bachelor: Computer Engineering',
  `education_school` varchar(255) DEFAULT 'IAU',
  `education_years` varchar(50) DEFAULT '2016 - 2019',
  `education_gpa` varchar(50) DEFAULT '16.4',
  `about_me` text,
  `skills` json DEFAULT NULL,
  `experiences` json DEFAULT NULL,
  `courses` json DEFAULT NULL,
  `languages` json DEFAULT NULL,
  `software` json DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `profile`
--

INSERT INTO `profile` (`id`, `full_name`, `headline`, `avatar_url`, `email`, `phone`, `location_province`, `location_city`, `address`, `github`, `linkedin`, `website`, `age`, `gender`, `marital_status`, `military_status`, `work_experience_years`, `salary_expectation`, `education_degree`, `education_school`, `education_years`, `education_gpa`, `about_me`, `skills`, `experiences`, `courses`, `languages`, `software`, `updated_at`) VALUES
(1, 'Farshad Nabizade', 'Software Engineer', NULL, 'farshadnabizadeh1993@gmail.com', '+98 921 812 4475', 'Tehran', 'Tehran', 'Tehran, Iran', 'https://github.com/farshadnabizadeh', 'https://linkedin.com/in/farshad-nabizadeh-37457b215', 'https://blog.sibmlm.ir', 34, 'Male', 'Single', 'Exempted', 7, '45 - 60 Million Tomans', 'B.Sc. in Computer Engineering', 'Islamic Azad University (IAU)', '2016 – 2019', '16.4 / 20', 'Systems-oriented Full-Stack Software Engineer with 7+ years of production experience across Iran and Turkey. Specialized in bridging modern reactive frontends (React, Next.js, TypeScript) with legacy enterprise backends (Java/JSF) and scalable APIs (Laravel, Node.js, MySQL, PostgreSQL, Redis). Hands-on infrastructure owner capable of taking code from local development to hardened Linux production environments (Docker, Nginx, Reverse Proxy, SSL, CI/CD). Grounded in SOLID principles, clean modular architecture, and pragmatic problem-solving.', '{\"Frontend\": [\"React\", \"Next.js (SSR / CSR / App Router)\", \"Redux Toolkit\", \"Tailwind CSS\", \"PrimeReact\", \"Framer Motion\", \"Plotly.js\"], \"Languages\": [\"TypeScript\", \"JavaScript (ES6+)\", \"PHP\", \"SQL\", \"HTML5\", \"CSS3/SASS\"], \"Architecture\": [\"Micro-Frontend / Hybrid Monolith Integration\", \"Multi-Tenant Systems\", \"RBAC\", \"SOLID\", \"OOP\", \"MVC\", \"TDD\"], \"Backend & Data\": [\"Laravel (Eloquent ORM, Concurrency/Race Conditions)\", \"Node.js\", \"Express.js\", \"RESTful APIs\", \"MySQL\", \"PostgreSQL\", \"Redis\"], \"Infra & Network\": [\"Linux (Ubuntu)\", \"Docker\", \"Nginx\", \"Reverse Proxy\", \"CI/CD\", \"DNS\", \"SSL/TLS\", \"CDN\", \"Firewall Hardening\", \"SMTP/Mail Servers\", \"VPN\"]}', '[{\"role\": \"Frontend Systems Engineer\", \"stack\": \"TypeScript, React, Next.js, Tailwind CSS, PrimeReact, Framer Motion, Plotly, JSF/Java 21 Bridge\", \"period\": \"June 2025 – Present (1 yr, 5 mos)\", \"company\": \"Gareno Pargas\", \"location\": \"Tehran\", \"responsibilities\": [\"Architected a hybrid navigation bridge integrating modern React modules directly into a legacy JSF/Java 21 enterprise monolith without disrupting existing workflows.\", \"Engineered a metadata-driven dynamic grid layout engine resolving widget configurations from backend APIs at runtime with zero hardcoded endpoints.\", \"Built interactive analytical views (Transport & Inventory) featuring custom Plotly bar/donut charts, treemap trace transforms, dynamic nice-scale axes, and cross-dashboard filter state propagation.\", \"Built and shipped an end-to-end enterprise AI Chatbot interface (ChatApp) enforcing role-based access control (RBAC), context-aware warnings, reactive message states, and multi-layer overflow containment.\", \"Overhauled multi-tenant invoicing modules across enterprise profiles (IKG, 3tou, Seto) and integrated Iranian Tax Administration (Moaddian) API synchronization with national ID validation.\", \"Standardized UI architecture with Tailwind CSS, PrimeReact, dynamic light/dark theming (Afta Mode), responsive print bill flows, and reusable sortable data tables.\"]}, {\"role\": \"Full-Stack Software Engineer\", \"stack\": \"Next.js, React, Redux Toolkit, Tailwind CSS, Laravel, MySQL, Redis, Nginx, Linux\", \"period\": \"July 2024 – September 2025 (1 yr, 2 mos)\", \"company\": \"Safiran-e-Yegane\", \"location\": \"Tehran\", \"responsibilities\": [\"Engineered high-performance SPAs leveraging Next.js Server/Client Component architecture, Redux Toolkit, and Tailwind CSS for user and administrative dashboards.\", \"Designed RESTful APIs backed by MySQL and Redis caching layers to minimize query overhead and improve endpoint latency.\", \"Owned end-to-end server operations: configured Nginx reverse proxies (proxy_pass), CORS policies, SSL/TLS certificates, DNS routing, CDN rules, firewalls, and organizational mail servers.\"]}, {\"role\": \"Senior Full-Stack Developer\", \"stack\": \"Laravel, PHP, React, Next.js, Tailwind CSS, MySQL, REST APIs\", \"period\": \"May 2022 – August 2024 (2 yrs, 3 mos)\", \"company\": \"Hotelistan Global Travel Services\", \"location\": \"Turkey\", \"responsibilities\": [\"Architected and built a custom Laravel CRM platform for customer lifecycle management alongside an internal operations panel for server and data monitoring.\", \"Developed responsive travel booking SPAs with React and Next.js, implementing an optimized location search interface and resilient third-party API integrations.\", \"Automated database backup pipelines and data integrity checks across production environments.\"]}, {\"role\": \"Freelance Full-Stack & Infrastructure Engineer\", \"stack\": \"PHP, Laravel, JavaScript, Ubuntu VPS, Nginx, SSH, VPN, SMTP\", \"period\": \"April 2021 – January 2022 (9 mos)\", \"company\": \"Independent\", \"location\": \"Turkey\", \"responsibilities\": [\"Refactored legacy client codebases and implemented server-side caching to eliminate bottlenecks and accelerate page load times.\", \"Provisioned and hardened bare-metal/VPS Linux servers, configuring SSH keys, firewall rules, private VPN tunnels, and authenticated SMTP mail servers.\"]}, {\"role\": \"Full-Stack Web Developer\", \"stack\": \"PHP, MySQL, Ubuntu Server, RBAC, Data Processing\", \"period\": \"March 2020 – May 2021 (1 yr, 2 mos)\", \"company\": \"Tema Istanbul\", \"location\": \"Turkey\", \"responsibilities\": [\"Built a real-time financial calculation engine and web panel in PHP/MySQL, automating Excel data ingestion, sanitization, and processing to eliminate manual entry errors.\", \"Deployed and maintained an on-premise Ubuntu server with secure remote access and granular role-based access control (RBAC).\", \"Designed an intuitive web panel interface optimized for non-technical users.\"]}, {\"role\": \"Full-Stack Developer\", \"stack\": \"Laravel, MySQL, Blade, SASS, jQuery\", \"period\": \"June 2018 – January 2019 (7 mos)\", \"company\": \"Vira Negar\", \"location\": \"Tehran\", \"responsibilities\": [\"Developed modular admin panels with custom data widgets, mobile-first responsive layouts, secure authentication, and optimized MySQL queries.\"]}]', '[{\"year\": \"2018\", \"focus\": \"Enterprise Routing & Switching (3 mos)\", \"title\": \"CCNP (Cisco Certified Network Professional)\", \"duration\": \"3 Mos\", \"institute\": \"Tehran Institute of Technology (TIT)\"}, {\"year\": \"2017\", \"focus\": \"Network Fundamentals & Security (3 mos)\", \"title\": \"CCNA (Cisco Certified Network Associate)\", \"duration\": \"3 Mos\", \"institute\": \"Tehran Institute of Technology (TIT)\"}, {\"year\": \"2016\", \"focus\": \"Core Networking Infrastructure\", \"title\": \"CompTIA Network+\", \"duration\": \"1 Mo\", \"institute\": \"Tehran Institute of Technology (TIT)\"}]', '[{\"language\": \"English\", \"proficiency\": \"Professional Working Proficiency\"}, {\"language\": \"Persian\", \"proficiency\": \"Native\"}]', '[{\"name\": \"Microsoft Word\", \"proficiency\": \"Intermediate\"}, {\"name\": \"Microsoft Excel\", \"proficiency\": \"Intermediate\"}, {\"name\": \"Microsoft Powerpoint\", \"proficiency\": \"Intermediate\"}]', '2026-11-18 07:33:37');

-- --------------------------------------------------------

--
-- Table structure for table `projects`
--

DROP TABLE IF EXISTS `projects`;
CREATE TABLE IF NOT EXISTS `projects` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `project_url` varchar(500) DEFAULT NULL,
  `github_url` varchar(500) DEFAULT NULL,
  `company` varchar(150) DEFAULT NULL,
  `duration` varchar(100) DEFAULT NULL,
  `role` varchar(150) DEFAULT NULL,
  `technologies` json DEFAULT NULL,
  `highlights` json DEFAULT NULL,
  `featured` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `questions`
--

DROP TABLE IF EXISTS `questions`;
CREATE TABLE IF NOT EXISTS `questions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `question` longtext COLLATE utf8mb4_bin,
  `answer` longtext COLLATE utf8mb4_bin,
  `category` varchar(255) COLLATE utf8mb4_bin DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `created_at`) VALUES
(1, 'admin', '$2y$10$bhf4ECFz7UdN6HmE03UMsORN4EZTcT2lO5OCV6ttzWu11fKI93lky', '2026-08-19 12:06:13');
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
