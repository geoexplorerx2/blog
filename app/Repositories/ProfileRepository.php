<?php

namespace App\Repositories;

use App\Contracts\ProfileRepositoryInterface;
use App\Models\Profile;
use PDO;
use PDOException;
use RuntimeException;

class ProfileRepository extends BaseRepository implements ProfileRepositoryInterface
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

        $db->exec("CREATE TABLE IF NOT EXISTS profile (
            id INT AUTO_INCREMENT PRIMARY KEY,
            full_name VARCHAR(255) DEFAULT 'Farshad Nabizade',
            headline VARCHAR(255) DEFAULT 'Full-Stack Web Developer',
            email VARCHAR(255) DEFAULT '',
            phone VARCHAR(50) DEFAULT '',
            address VARCHAR(255) DEFAULT '',
            age INT DEFAULT 34,
            gender VARCHAR(50) DEFAULT 'Male',
            marital_status VARCHAR(50) DEFAULT 'Single',
            military_status VARCHAR(50) DEFAULT 'Exempted',
            work_experience_years INT DEFAULT 7,
            salary_expectation VARCHAR(100) DEFAULT '45 - 60 Million Tomans',
            education_degree VARCHAR(255) DEFAULT 'Bachelor: Computer Engineering',
            education_school VARCHAR(255) DEFAULT 'IAU',
            education_years VARCHAR(100) DEFAULT '2016 - 2019',
            education_gpa VARCHAR(50) DEFAULT '16.4',
            about_me TEXT DEFAULT NULL,
            github VARCHAR(255) DEFAULT '',
            linkedin VARCHAR(255) DEFAULT '',
            skills JSON DEFAULT NULL,
            experiences JSON DEFAULT NULL,
            courses JSON DEFAULT NULL,
            languages JSON DEFAULT NULL,
            software JSON DEFAULT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    public function getProfile(): ?Profile
    {
        $db = $this->getDb();
        if ($db === null) return null;

        try {
            $stmt = $db->query("SELECT * FROM profile ORDER BY id DESC LIMIT 1");
            $row = $stmt->fetch();
            if (!$row) return null;

            return new Profile(
                (int)$row['id'],
                $row['full_name'] ?? 'Farshad Nabizade',
                $row['headline'] ?? 'Full-Stack Web Developer',
                $row['email'] ?? '',
                $row['phone'] ?? '',
                $row['address'] ?? '',
                (int)($row['age'] ?? 34),
                $row['gender'] ?? 'Male',
                $row['marital_status'] ?? 'Single',
                $row['military_status'] ?? 'Exempted',
                (int)($row['work_experience_years'] ?? 7),
                $row['salary_expectation'] ?? '45 - 60 Million Tomans',
                $row['education_degree'] ?? 'Bachelor: Computer Engineering',
                $row['education_school'] ?? 'IAU',
                $row['education_years'] ?? '2016 - 2019',
                $row['education_gpa'] ?? '16.4',
                $row['about_me'] ?? '',
                $row['github'] ?? '',
                $row['linkedin'] ?? '',
                !empty($row['skills']) ? (is_array($row['skills']) ? $row['skills'] : (json_decode($row['skills'], true) ?: [])) : [],
                !empty($row['experiences']) ? (is_array($row['experiences']) ? $row['experiences'] : (json_decode($row['experiences'], true) ?: [])) : [],
                !empty($row['courses']) ? (is_array($row['courses']) ? $row['courses'] : (json_decode($row['courses'], true) ?: [])) : [],
                !empty($row['languages']) ? (is_array($row['languages']) ? $row['languages'] : (json_decode($row['languages'], true) ?: [])) : [],
                !empty($row['software']) ? (is_array($row['software']) ? $row['software'] : (json_decode($row['software'], true) ?: [])) : [],
                $row['updated_at'] ?? null
            );
        } catch (PDOException $e) {
            return null;
        }
    }

    public function getRawProfileData(): ?array
    {
        $db = $this->getDb();
        if ($db === null) return null;

        try {
            $stmt = $db->query("SELECT * FROM profile ORDER BY id DESC LIMIT 1");
            return $stmt->fetch() ?: null;
        } catch (PDOException $e) {
            return null;
        }
    }

    public function saveProfile(array $data): bool
    {
        $db = $this->getDb();
        if ($db === null) {
            throw new RuntimeException("Database connection not available.");
        }

        $existing = $this->getRawProfileData() ?: [];

        $fullName = trim($data['full_name'] ?? ($existing['full_name'] ?? 'Farshad Nabizade'));
        $headline = trim($data['headline'] ?? ($existing['headline'] ?? 'Full-Stack Web Developer'));
        $email = trim($data['email'] ?? ($existing['email'] ?? ''));
        $phone = trim($data['phone'] ?? ($existing['phone'] ?? ''));
        $address = trim($data['address'] ?? ($existing['address'] ?? ''));
        $age = isset($data['age']) && is_numeric($data['age']) ? (int)$data['age'] : (int)($existing['age'] ?? 34);
        $gender = trim($data['gender'] ?? ($existing['gender'] ?? 'Male'));
        $maritalStatus = trim($data['marital_status'] ?? ($existing['marital_status'] ?? 'Single'));
        $militaryStatus = trim($data['military_status'] ?? ($existing['military_status'] ?? 'Exempted'));
        $workExpYears = isset($data['work_experience_years']) && is_numeric($data['work_experience_years']) ? (int)$data['work_experience_years'] : (int)($existing['work_experience_years'] ?? 7);
        $salaryExpectation = trim($data['salary_expectation'] ?? ($existing['salary_expectation'] ?? '45 - 60 Million Tomans'));
        $eduDegree = trim($data['education_degree'] ?? ($existing['education_degree'] ?? 'Bachelor: Computer Engineering'));
        $eduSchool = trim($data['education_school'] ?? ($existing['education_school'] ?? 'IAU'));
        $eduYears = trim($data['education_years'] ?? ($existing['education_years'] ?? '2016 - 2019'));
        $eduGpa = trim($data['education_gpa'] ?? ($existing['education_gpa'] ?? '16.4'));
        $aboutMe = trim($data['about_me'] ?? ($existing['about_me'] ?? ''));
        $github = trim($data['github'] ?? ($existing['github'] ?? ''));
        $linkedin = trim($data['linkedin'] ?? ($existing['linkedin'] ?? ''));

        $skills = isset($data['skills']) 
            ? (is_array($data['skills']) ? json_encode($data['skills'], JSON_UNESCAPED_UNICODE) : (string)$data['skills'])
            : ($existing['skills'] ?? '{}');

        $experiences = isset($data['experiences']) 
            ? (is_array($data['experiences']) ? json_encode($data['experiences'], JSON_UNESCAPED_UNICODE) : (string)$data['experiences'])
            : ($existing['experiences'] ?? '[]');

        $courses = isset($data['courses']) 
            ? (is_array($data['courses']) ? json_encode($data['courses'], JSON_UNESCAPED_UNICODE) : (string)$data['courses'])
            : ($existing['courses'] ?? '[]');

        $languages = isset($data['languages']) 
            ? (is_array($data['languages']) ? json_encode($data['languages'], JSON_UNESCAPED_UNICODE) : (string)$data['languages'])
            : ($existing['languages'] ?? '[]');

        $software = isset($data['software']) 
            ? (is_array($data['software']) ? json_encode($data['software'], JSON_UNESCAPED_UNICODE) : (string)$data['software'])
            : ($existing['software'] ?? '[]');

        if (!empty($existing['id'])) {
            $update = $db->prepare("UPDATE profile SET
                full_name = :full_name,
                headline = :headline,
                email = :email,
                phone = :phone,
                address = :address,
                age = :age,
                gender = :gender,
                marital_status = :marital_status,
                military_status = :military_status,
                work_experience_years = :work_experience_years,
                salary_expectation = :salary_expectation,
                education_degree = :education_degree,
                education_school = :education_school,
                education_years = :education_years,
                education_gpa = :education_gpa,
                about_me = :about_me,
                github = :github,
                linkedin = :linkedin,
                skills = :skills,
                experiences = :experiences,
                courses = :courses,
                languages = :languages,
                software = :software
                WHERE id = :id");

            return $update->execute([
                ':id' => $existing['id'],
                ':full_name' => $fullName,
                ':headline' => $headline,
                ':email' => $email,
                ':phone' => $phone,
                ':address' => $address,
                ':age' => $age,
                ':gender' => $gender,
                ':marital_status' => $maritalStatus,
                ':military_status' => $militaryStatus,
                ':work_experience_years' => $workExpYears,
                ':salary_expectation' => $salaryExpectation,
                ':education_degree' => $eduDegree,
                ':education_school' => $eduSchool,
                ':education_years' => $eduYears,
                ':education_gpa' => $eduGpa,
                ':about_me' => $aboutMe,
                ':github' => $github,
                ':linkedin' => $linkedin,
                ':skills' => $skills,
                ':experiences' => $experiences,
                ':courses' => $courses,
                ':languages' => $languages,
                ':software' => $software
            ]);
        } else {
            $insert = $db->prepare("INSERT INTO profile (
                full_name, headline, email, phone, address, age, gender,
                marital_status, military_status, work_experience_years, salary_expectation,
                education_degree, education_school, education_years, education_gpa,
                about_me, github, linkedin, skills, experiences, courses, languages, software
            ) VALUES (
                :full_name, :headline, :email, :phone, :address, :age, :gender,
                :marital_status, :military_status, :work_experience_years, :salary_expectation,
                :education_degree, :education_school, :education_years, :education_gpa,
                :about_me, :github, :linkedin, :skills, :experiences, :courses, :languages, :software
            )");

            return $insert->execute([
                ':full_name' => $fullName,
                ':headline' => $headline,
                ':email' => $email,
                ':phone' => $phone,
                ':address' => $address,
                ':age' => $age,
                ':gender' => $gender,
                ':marital_status' => $maritalStatus,
                ':military_status' => $militaryStatus,
                ':work_experience_years' => $workExpYears,
                ':salary_expectation' => $salaryExpectation,
                ':education_degree' => $eduDegree,
                ':education_school' => $eduSchool,
                ':education_years' => $eduYears,
                ':education_gpa' => $eduGpa,
                ':about_me' => $aboutMe,
                ':github' => $github,
                ':linkedin' => $linkedin,
                ':skills' => $skills,
                ':experiences' => $experiences,
                ':courses' => $courses,
                ':languages' => $languages,
                ':software' => $software
            ]);
        }
    }
}
