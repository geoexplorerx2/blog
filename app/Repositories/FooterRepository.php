<?php

namespace App\Repositories;

use App\Contracts\FooterRepositoryInterface;
use App\Models\FooterSetting;
use PDO;
use PDOException;
use RuntimeException;

class FooterRepository extends BaseRepository implements FooterRepositoryInterface
{
    public function __construct(?PDO $db = null)
    {
        parent::__construct($db);
        if ($this->getDb() !== null) {
            $this->ensureTableExists();
        }
    }

    public function ensureTableExists(): void
    {
        $db = $this->getDb();
        if ($db === null) return;

        $db->exec("CREATE TABLE IF NOT EXISTS footer_settings (
            id INT PRIMARY KEY,
            brand_initials VARCHAR(10) DEFAULT 'FN',
            brand_name VARCHAR(150) DEFAULT 'Farshad Nabizade',
            brand_title VARCHAR(150) DEFAULT 'Full-Stack Software Engineer',
            brand_bio TEXT,
            availability_status VARCHAR(150) DEFAULT 'Available for Engineering Opportunities',
            nav_links JSON,
            technologies JSON,
            email VARCHAR(150) DEFAULT 'farshad.nabizade@gmail.com',
            phone VARCHAR(50) DEFAULT '+98 912 345 6789',
            location VARCHAR(150) DEFAULT 'Tehran, Iran',
            contact_note VARCHAR(255) DEFAULT 'Feel free to reach out for collaborations, technical inquiries, or consulting.',
            copyright_text VARCHAR(255) DEFAULT 'All rights reserved. • Engineered with modern web standards.',
            visible_sections JSON,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $count = (int)$db->query("SELECT COUNT(*) FROM footer_settings WHERE id = 1")->fetchColumn();
        if ($count === 0) {
            $defaults = $this->getDefaultData();
            $stmt = $db->prepare("INSERT INTO footer_settings (
                id, brand_initials, brand_name, brand_title, brand_bio, availability_status,
                nav_links, technologies, email, phone, location, contact_note, copyright_text, visible_sections
            ) VALUES (
                1, :brand_initials, :brand_name, :brand_title, :brand_bio, :availability_status,
                :nav_links, :technologies, :email, :phone, :location, :contact_note, :copyright_text, :visible_sections
            )");

            $stmt->execute([
                ':brand_initials' => $defaults['brand_initials'],
                ':brand_name' => $defaults['brand_name'],
                ':brand_title' => $defaults['brand_title'],
                ':brand_bio' => $defaults['brand_bio'],
                ':availability_status' => $defaults['availability_status'],
                ':nav_links' => json_encode($defaults['nav_links'], JSON_UNESCAPED_UNICODE),
                ':technologies' => json_encode($defaults['technologies'], JSON_UNESCAPED_UNICODE),
                ':email' => $defaults['email'],
                ':phone' => $defaults['phone'],
                ':location' => $defaults['location'],
                ':contact_note' => $defaults['contact_note'],
                ':copyright_text' => $defaults['copyright_text'],
                ':visible_sections' => json_encode($defaults['visible_sections'], JSON_UNESCAPED_UNICODE)
            ]);
        }
    }

    public function getDefaultData(): array
    {
        return [
            'id' => 1,
            'brand_initials' => 'FN',
            'brand_name' => 'Farshad Nabizade',
            'brand_title' => 'Full-Stack Software Engineer',
            'brand_bio' => 'Dedicated to architecting high-performance web applications, scalable APIs, reactive frontends, and comprehensive computer science knowledge bases.',
            'availability_status' => 'Available for Engineering Opportunities',
            'nav_links' => [
                ["label" => "Questions Base", "url" => "index.php"],
                ["label" => "Projects Portfolio", "url" => "projects.php"],
                ["label" => "Freelance Hub", "url" => "freelance.php"],
                ["label" => "Resume & CV", "url" => "profile.php"],
                ["label" => "Technical Skills", "url" => "profile.php#skills"],
                ["label" => "Career Timeline", "url" => "profile.php#experience"]
            ],
            'technologies' => [
                "React", "Next.js (SSR)", "TypeScript", "PHP & Laravel", "Node.js",
                "Tailwind CSS", "Redux Toolkit", "MySQL & Redis", "Docker & Nginx", "RESTful APIs", "Clean Code"
            ],
            'email' => 'farshad.nabizade@gmail.com',
            'phone' => '+98 912 345 6789',
            'location' => 'Tehran, Iran',
            'contact_note' => 'Feel free to reach out for collaborations, technical inquiries, or consulting.',
            'copyright_text' => 'All rights reserved. • Engineered with modern web standards.',
            'visible_sections' => [
                "brand" => true,
                "nav" => true,
                "tech" => true,
                "contact" => true
            ]
        ];
    }

    public function getSettings(): FooterSetting
    {
        $this->ensureTableExists();
        $db = $this->getDb();
        $defaults = $this->getDefaultData();

        if ($db === null) {
            return $this->arrayToModel($defaults);
        }

        try {
            $stmt = $db->query("SELECT * FROM footer_settings WHERE id = 1 LIMIT 1");
            $row = $stmt->fetch();
            if (!$row) {
                return $this->arrayToModel($defaults);
            }

            $row['nav_links'] = !empty($row['nav_links']) ? (is_array($row['nav_links']) ? $row['nav_links'] : (json_decode($row['nav_links'], true) ?: [])) : $defaults['nav_links'];
            $row['technologies'] = !empty($row['technologies']) ? (is_array($row['technologies']) ? $row['technologies'] : (json_decode($row['technologies'], true) ?: [])) : $defaults['technologies'];
            $row['visible_sections'] = !empty($row['visible_sections']) ? (is_array($row['visible_sections']) ? $row['visible_sections'] : (json_decode($row['visible_sections'], true) ?: [])) : $defaults['visible_sections'];

            return $this->arrayToModel($row);
        } catch (PDOException $e) {
            return $this->arrayToModel($defaults);
        }
    }

    public function getSettingsArray(): array
    {
        return $this->getSettings()->toArray();
    }

    private function arrayToModel(array $row): FooterSetting
    {
        return new FooterSetting(
            (int)($row['id'] ?? 1),
            $row['brand_initials'] ?? 'FN',
            $row['brand_name'] ?? 'Farshad Nabizade',
            $row['brand_title'] ?? 'Full-Stack Software Engineer',
            $row['brand_bio'] ?? '',
            $row['availability_status'] ?? '',
            $row['nav_links'] ?? [],
            $row['technologies'] ?? [],
            $row['email'] ?? '',
            $row['phone'] ?? '',
            $row['location'] ?? '',
            $row['contact_note'] ?? '',
            $row['copyright_text'] ?? 'All rights reserved.',
            $row['visible_sections'] ?? [],
            $row['updated_at'] ?? null
        );
    }

    public function saveSettings(array $data): bool
    {
        $db = $this->getDb();
        if ($db === null) {
            throw new RuntimeException("Database connection not available.");
        }

        $this->ensureTableExists();

        $brandInitials = trim($data['brand_initials'] ?? 'FN');
        $brandName = trim($data['brand_name'] ?? 'Farshad Nabizade');
        $brandTitle = trim($data['brand_title'] ?? 'Full-Stack Software Engineer');
        $brandBio = trim($data['brand_bio'] ?? '');
        $availability = trim($data['availability_status'] ?? '');
        $email = trim($data['email'] ?? '');
        $phone = trim($data['phone'] ?? '');
        $location = trim($data['location'] ?? '');
        $contactNote = trim($data['contact_note'] ?? '');
        $copyright = trim($data['copyright_text'] ?? 'All rights reserved.');

        $navLinks = is_array($data['nav_links'] ?? null) ? json_encode($data['nav_links'], JSON_UNESCAPED_UNICODE) : (string)($data['nav_links'] ?? '[]');
        $technologies = is_array($data['technologies'] ?? null) ? json_encode($data['technologies'], JSON_UNESCAPED_UNICODE) : (string)($data['technologies'] ?? '[]');
        $visibleSections = is_array($data['visible_sections'] ?? null) ? json_encode($data['visible_sections'], JSON_UNESCAPED_UNICODE) : (string)($data['visible_sections'] ?? '{}');

        $stmt = $db->prepare("UPDATE footer_settings SET
            brand_initials = :brand_initials,
            brand_name = :brand_name,
            brand_title = :brand_title,
            brand_bio = :brand_bio,
            availability_status = :availability_status,
            nav_links = :nav_links,
            technologies = :technologies,
            email = :email,
            phone = :phone,
            location = :location,
            contact_note = :contact_note,
            copyright_text = :copyright_text,
            visible_sections = :visible_sections
            WHERE id = 1");

        return $stmt->execute([
            ':brand_initials' => $brandInitials,
            ':brand_name' => $brandName,
            ':brand_title' => $brandTitle,
            ':brand_bio' => $brandBio,
            ':availability_status' => $availability,
            ':nav_links' => $navLinks,
            ':technologies' => $technologies,
            ':email' => $email,
            ':phone' => $phone,
            ':location' => $location,
            ':contact_note' => $contactNote,
            ':copyright_text' => $copyright,
            ':visible_sections' => $visibleSections
        ]);
    }
}
