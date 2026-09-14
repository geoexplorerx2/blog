<?php

namespace App\Models;

class Profile
{
    public function __construct(
        public int $id = 1,
        public string $fullName = 'Farshad Nabizade',
        public string $headline = 'Full-Stack Web Developer',
        public string $email = '',
        public string $phone = '',
        public string $address = '',
        public int $age = 34,
        public string $gender = 'Male',
        public string $maritalStatus = 'Single',
        public string $militaryStatus = 'Exempted',
        public int $workExperienceYears = 7,
        public string $salaryExpectation = '45 - 60 Million Tomans',
        public string $educationDegree = 'Bachelor: Computer Engineering',
        public string $educationSchool = 'IAU',
        public string $educationYears = '2016 - 2019',
        public string $educationGpa = '16.4',
        public string $aboutMe = '',
        public string $github = '',
        public string $linkedin = '',
        public array $skills = [],
        public array $experiences = [],
        public array $educations = [],
        public array $certificates = [],
        public array $stats = [],
        public ?string $updatedAt = null
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'full_name' => $this->fullName,
            'headline' => $this->headline,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'age' => $this->age,
            'gender' => $this->gender,
            'marital_status' => $this->maritalStatus,
            'military_status' => $this->militaryStatus,
            'work_experience_years' => $this->workExperienceYears,
            'salary_expectation' => $this->salaryExpectation,
            'education_degree' => $this->educationDegree,
            'education_school' => $this->educationSchool,
            'education_years' => $this->educationYears,
            'education_gpa' => $this->educationGpa,
            'about_me' => $this->aboutMe,
            'github' => $this->github,
            'linkedin' => $this->linkedin,
            'skills' => $this->skills,
            'experiences' => $this->experiences,
            'educations' => $this->educations,
            'certificates' => $this->certificates,
            'stats' => $this->stats,
            'updated_at' => $this->updatedAt,
        ];
    }
}
