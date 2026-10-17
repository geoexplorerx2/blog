-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Oct 17, 2026 at 07:36 AM
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
(1, 'FN', 'Farshad Nabizadeh', 'Full-Stack Software Engineer', 'Dedicated to architecting high-performance web applications, scalable APIs, reactive frontends, and comprehensive computer science knowledge bases.', '', '[{\"url\": \"index.php\", \"label\": \"Questions Base\"}, {\"url\": \"projects.php\", \"label\": \"Projects Portfolio\"}, {\"url\": \"freelance.php\", \"label\": \"Freelance Hub\"}, {\"url\": \"profile.php\", \"label\": \"Resume & CV\"}, {\"url\": \"profile.php#skills\", \"label\": \"Technical Skills\"}, {\"url\": \"profile.php#experience\", \"label\": \"Career Timeline\"}]', '[\"React\", \"Next.js (SSR)\", \"TypeScript\", \"PHP & Laravel\", \"Node.js\", \"Tailwind CSS\", \"Redux Toolkit\", \"MySQL & Redis\", \"Docker & Nginx\", \"RESTful APIs\", \"Clean Code\"]', 'farshad.nabizade@gmail.com', '+98 912 345 6789', 'Tehran, Iran', 'Feel free to reach out for collaborations, technical inquiries, or consulting.', 'All rights reserved. • Engineered with modern web standards.', '{\"nav\": true, \"tech\": true, \"brand\": true, \"contact\": true}', '2026-09-07 10:26:24');

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

INSERT INTO `profile` (`id`, `full_name`, `headline`, `avatar_url`, `email`, `phone`, `location_province`, `location_city`, `address`, `github`, `linkedin`, `age`, `gender`, `marital_status`, `military_status`, `work_experience_years`, `salary_expectation`, `education_degree`, `education_school`, `education_years`, `education_gpa`, `about_me`, `skills`, `experiences`, `courses`, `languages`, `software`, `updated_at`) VALUES
(1, 'Farshad Nabizade', 'Full-Stack Web Developer', NULL, 'geoexplorerx2@gmail.com', '09218124475', 'Tehran', 'Tehran', 'Piroozi, Tehran, Iran', 'https://github.com/farshad-nabizade', 'https://linkedin.com', 34, 'Male', 'Single', 'Exempted', 7, '45 - 60 Million Tomans', 'Bachelor: Computer Engineering', 'IAU', '2016 - 2019', '16.4', 'Full-stack developer with over 7 years of hands-on experience in designing and building scalable, secure, and maintainable systems. Proficient in frontend technologies including React, Next.js, Redux Toolkit, TypeScript, and Tailwind CSS, and experienced in backend development with PHP/Laravel, Node.js, Express, MySQL, PostgreSQL, Redis, and RESTful architectures. Skilled in implementing MVC architecture and applying object-oriented design principles such as SOLID to produce clean, extensible, and standards-compliant code. Familiar with SSR architectures, component-based design, advanced caching strategies, and modular system development. Strong DevOps background with practical experience in Docker, Nginx, Reverse Proxy, SSL, DNS, CI/CD pipelines, Linux server administration, service configuration, and performance optimization. Focused on delivering fast, reliable, user-centric, and well-documented products using modern architectures and a Clean Code approach.', '{\"Frontend & UI\": [\"React\", \"Next.js (SSR / CSR)\", \"TypeScript\", \"JavaScript\", \"Tailwind CSS\", \"Redux Toolkit\", \"Single Page Applications (SPA)\", \"Component-Driven Architecture\"], \"Backend & APIs\": [\"PHP\", \"Laravel\", \"Laravel Eloquent ORM\", \"Laravel Eloquent Relationships\", \"Laravel Race Conditions\", \"Node.js\", \"Express.js\", \"RESTful APIs\", \"MVC Architecture\"], \"Databases & Caching\": [\"MySQL\", \"PostgreSQL\", \"Redis\", \"Database Design Principles\"], \"Architecture & Quality\": [\"SOLID Principles\", \"Object Oriented Programming (OOP)\", \"Test-Driven Development (TDD)\", \"Reactive Architecture\", \"Clean Code\"], \"DevOps & Infrastructure\": [\"Docker\", \"Nginx\", \"Reverse Proxy\", \"SSL / TLS\", \"DNS Management\", \"CDN Rules & Firewall Policies\", \"CI/CD Pipelines\", \"Linux Server Administration (Ubuntu)\", \"VPS Security Hardening\", \"Mail Server (SMTP)\"]}', '[{\"role\": \"JavaScript Front-End Developer (TypeScript, React, Next.js)\", \"period\": \"June 2025 until now (1 year and 2 months)\", \"company\": \"Gareno Pargas\", \"location\": \"Iran, Tehran\", \"responsibilities\": [\"Improved the UI and responsiveness of an enterprise ERP system by redesigning multiple frontend modules using a modular and component-based architecture.\", \"Refactored existing Spring Boot client-side interfaces to resolve performance issues and deliver a smoother user experience.\", \"Developed a TypeScript-based website using a fully component-driven structure.\", \"Implemented a dealership information module using Next.js with SSR to enhance SEO and reduce time-to-first-byte.\", \"Built an internal Wallet module using Next.js, TypeScript, Tailwind CSS, and Redux Toolkit, enabling real-time endpoint communication and improving internal financial workflows.\", \"Designed and developed an end-to-end marketplace website (BazarGah GhateSto) using Next.js, TypeScript, Tailwind CSS, and Redux Toolkit with emphasis on scalability, modern UI/UX, and high performance.\", \"Ensured frontend optimization by applying reactive architecture principles and real-time asynchronous data handling.\"]}, {\"role\": \"Full-Stack Software Engineer (Next.js, React, Laravel)\", \"period\": \"July 2024 until September 2025 (1 year and 2 months)\", \"company\": \"Safiran-e-Yegane\", \"location\": \"Iran, Tehran\", \"responsibilities\": [\"Designed and developed high-performance Single Page Applications (SPA) using React, Next.js, Tailwind CSS, and Redux Toolkit.\", \"Built user and admin dashboards with smart, responsive, and user-friendly interfaces.\", \"Developed optimized RESTful APIs with efficient endpoints to improve data handling and reduce response time.\", \"Implemented Server-Client Component architecture in Next.js to achieve maximum performance and faster load times.\", \"Integrated MySQL and Redis for robust data management, effective caching, and overall system performance improvement.\", \"Configured and managed Cross-Origin policies to ensure secure resource sharing across services.\", \"Performed updates, security patching, system monitoring, and advanced performance optimization.\", \"Deployed and maintained SSL certificates to enable secure and encrypted communications.\", \"Configured and managed DNS settings for stable and reliable domain routing.\", \"Implemented CDN rules, firewall policies, and Nginx proxy_pass for secure and optimized traffic management.\", \"Set up and configured mail server services to support organizational communication needs.\", \"Enhanced network security through Reverse Proxy configuration, access control policies, and server hardening.\"]}, {\"role\": \"Senior Full-Stack Developer\", \"period\": \"May 2022 until August 2024 (2 years and 3 months)\", \"company\": \"Hotelistan Global Travel Services\", \"location\": \"Turkey\", \"responsibilities\": [\"Improved API connectivity to enable faster and more reliable data exchange.\", \"Implemented automated database backups to enhance data security and prevent data loss.\", \"Designed a user-friendly location search interface to improve usability and user experience.\", \"Developed responsive Single Page Applications (SPA) using React, Next.js, and Tailwind CSS.\", \"Designed and implemented a custom CRM system using Laravel for comprehensive customer management.\", \"Built an admin panel for managing server operations and monitoring system data.\"]}, {\"role\": \"Freelance Full-Stack Developer\", \"period\": \"April 2021 until January 2022 (9 months)\", \"company\": \"Freelance\", \"location\": \"Turkey\", \"responsibilities\": [\"Optimized website performance and loading speed through advanced caching, code refactoring, and server-side improvements.\", \"Built fully responsive UI layouts to ensure seamless user experience across mobile and desktop devices.\", \"Installed and configured VPS servers, including security hardening, firewall rules, SSH access, and service optimization.\", \"Implemented secure VPN environments for private networking, encrypted communication, and improved data protection.\", \"Debugged and resolved critical frontend and backend issues across multiple client websites.\", \"Set up and configured mail servers (mail-server, SMTP) for reliable email delivery, domain authentication, and organizational communication.\"]}, {\"role\": \"Full-Stack Web Developer (PHP, MySQL, Ubuntu Server)\", \"period\": \"March 2020 until May 2021 (1 year and 2 months)\", \"company\": \"Tema Istanbul\", \"location\": \"Turkey\", \"responsibilities\": [\"Developed a web-based panel using PHP/MySQL with real-time calculations based on complex business formulas.\", \"Automated Excel data extraction, cleaning, and processing to eliminate human errors and improve calculation accuracy.\", \"Implemented authentication and role-based access control to protect sensitive business data.\", \"Installed and configured an Ubuntu server and enabled secure online access without relying on cloud hosting.\", \"Designed a simple and intuitive user interface optimized for non-technical users, requiring minimal training.\"]}, {\"role\": \"Full-Stack Developer (Laravel, MySQL, jQuery)\", \"period\": \"June 2018 until January 2019 (7 months)\", \"company\": \"Vira Negar\", \"location\": \"Iran, Tehran\", \"responsibilities\": [\"Implemented a secure login system using Laravel to enhance security and enforce user access control.\", \"Designed and developed a dynamic admin panel using Laravel Blade, CSS/SASS, and jQuery to improve user experience.\", \"Created custom widgets to simplify data presentation and streamline user interactions.\", \"Built responsive, mobile-first user interfaces optimized for all device types.\", \"Optimized MySQL queries to improve data processing speed and overall server performance.\"]}]', '[{\"year\": \"2018\", \"title\": \"CCNP\", \"duration\": \"3 Months\", \"institute\": \"TIT\"}, {\"year\": \"2017\", \"title\": \"CCNA\", \"duration\": \"3 Months\", \"institute\": \"TIT\"}, {\"year\": \"2016\", \"title\": \"Network +\", \"duration\": \"1 Month\", \"institute\": \"TIT\"}]', '[{\"language\": \"English\", \"proficiency\": \"Intermediate\"}, {\"language\": \"Persian\", \"proficiency\": \"Native\"}]', '[{\"name\": \"Microsoft Word\", \"proficiency\": \"Intermediate\"}, {\"name\": \"Microsoft Excel\", \"proficiency\": \"Intermediate\"}, {\"name\": \"Microsoft Powerpoint\", \"proficiency\": \"Intermediate\"}]', '2026-09-14 11:32:09');

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
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

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
